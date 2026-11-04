<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Minimal CSS declaration tokenizer for extracting presentation hints.
 *
 * It intentionally does not evaluate CSS. It only separates declarations at
 * top-level semicolons and properties at top-level colons, so strings, URL
 * arguments, comments, and escaped delimiters cannot masquerade as a CSS
 * declaration.
 */
final class CssDeclarationScanner
{
    /**
     * @return list<array{name:string,value:string,source:string,normalized:string,important:bool}>
     */
    public static function declarations(string $style): array
    {
        $declarations = [];
        $length = strlen($style);
        $start = 0;
        $quote = null;
        $parentheses = 0;
        $inComment = false;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $style[$offset];
            if ($inComment) {
                if ($char === '*' && ($style[$offset + 1] ?? '') === '/') {
                    $inComment = false;
                    $offset++;
                }
                continue;
            }
            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '/' && ($style[$offset + 1] ?? '') === '*') {
                $inComment = true;
                $offset++;
                continue;
            }
            if ($char === '\\') {
                $offset++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')' && $parentheses > 0) {
                $parentheses--;
                continue;
            }
            if ($char !== ';' || $parentheses !== 0) {
                continue;
            }

            self::appendDeclaration($declarations, substr($style, $start, $offset - $start));
            $start = $offset + 1;
        }

        self::appendDeclaration($declarations, substr($style, $start));

        return $declarations;
    }

    public static function firstValue(string $style, string $name): ?string
    {
        $name = strtolower($name);
        foreach (self::declarations($style) as $declaration) {
            if ($declaration['name'] === $name) {
                return $declaration['value'];
            }
        }

        return null;
    }

    /**
     * Resolve one inline-style property using CSS declaration order for the
     * narrow presentation hints consumed by the readers. Invalid values are
     * ignored by the caller-provided validator, while CSS-wide keywords and
     * an optional property-specific reset value clear an earlier hint. An
     * earlier !important value still wins over later normal declarations.
     *
     * This is intentionally not a full CSS cascade: there is one inline
     * declaration list and no selectors, origins, inheritance, or custom
     * property resolution involved here.
     *
     * @param string|list<string> $names
     * @param callable(string):bool $isValidValue
     * @param null|callable(string):bool $isResetValue
     */
    public static function lastValidValue(
        string $style,
        string|array $names,
        callable $isValidValue,
        ?callable $isResetValue = null
    ): ?string
    {
        $acceptedNames = [];
        foreach ((array) $names as $name) {
            $acceptedNames[strtolower($name)] = true;
        }

        $normal = null;
        $important = null;
        $hasNormal = false;
        $hasImportant = false;
        foreach (self::declarations($style) as $declaration) {
            if (!isset($acceptedNames[$declaration['name']])) {
                continue;
            }

            $value = $declaration['value'];
            $resetsValue = self::isCssWideResetValue($value)
                || ($isResetValue !== null && $isResetValue($value));
            if (!$resetsValue && !$isValidValue($value)) {
                continue;
            }

            $resolvedValue = $resetsValue ? null : $value;

            if ($declaration['important']) {
                $important = $resolvedValue;
                $hasImportant = true;
                continue;
            }

            $normal = $resolvedValue;
            $hasNormal = true;
        }

        if ($hasImportant) {
            return $important;
        }

        return $hasNormal ? $normal : null;
    }

    private static function isCssWideResetValue(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true);
    }

    /**
     * @param list<array{name:string,value:string,source:string,normalized:string,important:bool}> $declarations
     */
    public static function render(array $declarations): string
    {
        return implode('; ', array_column($declarations, 'source'));
    }

    /**
     * @param list<array{name:string,value:string,source:string,normalized:string,important:bool}> $declarations
     */
    private static function appendDeclaration(array &$declarations, string $source): void
    {
        $source = trim($source);
        if ($source === '') {
            return;
        }

        // CSS comments are whitespace outside strings.  Keep the original
        // declaration for lossless re-rendering, while using a comment-free
        // form for property matching and value extraction.
        $normalized = trim(self::withoutComments($source));
        if ($normalized === '') {
            return;
        }

        $colon = self::topLevelColonOffset($normalized);
        if ($colon === null) {
            return;
        }

        $name = strtolower(trim(substr($normalized, 0, $colon)));
        if ($name === '') {
            return;
        }

        $value = trim(substr($normalized, $colon + 1));
        [$value, $important] = self::valueAndImportance($value);
        if ($value === '') {
            return;
        }

        $declarations[] = [
            'name' => $name,
            'value' => $value,
            'source' => $source,
            'normalized' => $normalized,
            'important' => $important,
        ];
    }

    /**
     * @return array{0:string, 1:bool}
     */
    private static function valueAndImportance(string $value): array
    {
        $length = strlen($value);
        $quote = null;
        $parentheses = 0;
        $lastTopLevelBang = null;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];
            if ($quote !== null) {
                if ($char === '\\') {
                    ++$offset;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '\\') {
                ++$offset;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$parentheses;
                continue;
            }
            if ($char === ')' && $parentheses > 0) {
                --$parentheses;
                continue;
            }
            if ($char !== '!' || $parentheses !== 0) {
                continue;
            }

            // Check the suffix once after the scan.  Copying and matching the
            // remaining suffix for every top-level ! makes an otherwise
            // linear tokenizer quadratic on inputs such as "!!!!...".
            $lastTopLevelBang = $offset;
        }

        if (
            $lastTopLevelBang !== null
            && preg_match('/^!\s*important\s*$/i', substr($value, $lastTopLevelBang)) === 1
        ) {
            return [rtrim(substr($value, 0, $lastTopLevelBang)), true];
        }

        return [$value, false];
    }

    private static function withoutComments(string $source): string
    {
        $result = '';
        $length = strlen($source);
        $quote = null;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                $result .= $char;
                if ($char === '\\' && $offset + 1 < $length) {
                    $result .= $source[++$offset];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\\' && $offset + 1 < $length) {
                $result .= $char . $source[++$offset];
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $result .= $char;
                continue;
            }
            if ($char === '/' && ($source[$offset + 1] ?? '') === '*') {
                $commentEnd = strpos($source, '*/', $offset + 2);
                $result .= ' ';
                if ($commentEnd === false) {
                    break;
                }
                $offset = $commentEnd + 1;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private static function topLevelColonOffset(string $source): ?int
    {
        $length = strlen($source);
        $quote = null;
        $parentheses = 0;
        $inComment = false;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($inComment) {
                if ($char === '*' && ($source[$offset + 1] ?? '') === '/') {
                    $inComment = false;
                    $offset++;
                }
                continue;
            }
            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '/' && ($source[$offset + 1] ?? '') === '*') {
                $inComment = true;
                $offset++;
                continue;
            }
            if ($char === '\\') {
                $offset++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')' && $parentheses > 0) {
                $parentheses--;
                continue;
            }
            if ($char === ':' && $parentheses === 0) {
                return $offset;
            }
        }

        return null;
    }
}
