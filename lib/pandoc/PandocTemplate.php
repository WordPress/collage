<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

final class PandocTemplate
{
    /**
     * @param array<string, mixed> $context
     */
    public static function renderString(string $template, array $context): string
    {
        return (new self())->render($template, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        $offset = 0;
        [$rendered] = $this->renderSection($template, $offset, $context, []);

        return $rendered;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $stopNames
     * @return array{0:string, 1:array{name:string, content:string}|null}
     */
    private function renderSection(string $template, int &$offset, array $context, array $stopNames): array
    {
        $output = '';
        $length = strlen($template);

        while ($offset < $length) {
            $marker = strpos($template, '$', $offset);
            if ($marker === false) {
                $output .= substr($template, $offset);
                $offset = $length;
                break;
            }

            $output .= substr($template, $offset, $marker - $offset);

            if (str_starts_with(substr($template, $marker), '$--')) {
                $lineStart = $marker === 0 || $template[$marker - 1] === "\n" || $template[$marker - 1] === "\r";
                $newline = $this->findLineEnding($template, $marker + 3);
                if ($newline === null) {
                    $offset = $length;
                } elseif ($lineStart) {
                    $offset = $newline['end'];
                } else {
                    $offset = $newline['start'];
                }
                continue;
            }

            if (str_starts_with(substr($template, $marker), '$$')) {
                $output .= '$';
                $offset = $marker + 2;
                continue;
            }

            $directive = $this->readDirective($template, $marker);
            if ($directive === null) {
                $output .= '$';
                $offset = $marker + 1;
                continue;
            }

            $offset = $directive['end'];
            $content = trim($directive['content'], " \t");
            $tagName = $this->controlTagName($content);

            if ($tagName !== null && in_array($tagName, $stopNames, true)) {
                return [$output, ['name' => $tagName, 'content' => $content]];
            }

            if (preg_match('/^if\s*\((.*)\)$/s', $content, $matches) === 1) {
                $multiline = $this->consumeLineEnding($template, $offset);
                $output .= $this->renderConditional($template, $offset, $context, trim($matches[1]), $multiline);
                continue;
            }

            if (preg_match('/^for\s*\((.*)\)$/s', $content, $matches) === 1) {
                $multiline = $this->consumeLineEnding($template, $offset);
                $output .= $this->renderLoop($template, $offset, $context, trim($matches[1]), $multiline);
                continue;
            }

            if ($tagName !== null) {
                throw new InvalidArgumentException("Unexpected template directive: {$content}");
            }

            $output .= $this->renderInterpolation($content, $context);
        }

        return [$output, null];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderConditional(
        string $template,
        int &$offset,
        array $context,
        string $variable,
        bool $multiline
    ): string {
        $selected = null;
        $matched = false;
        $currentVariable = $variable;

        while (true) {
            [$branch, $tag] = $this->renderSection($template, $offset, $context, ['else', 'elseif', 'endif']);

            if (!$matched && ($currentVariable === null || $this->isTruthy($this->resolveExpression($currentVariable, $context)))) {
                $selected = $branch;
                $matched = true;
            }

            if ($tag === null) {
                throw new InvalidArgumentException('Unclosed template conditional');
            }

            if ($tag['name'] === 'endif') {
                if ($multiline) {
                    $this->consumeLineEnding($template, $offset);
                }
                return $selected ?? '';
            }

            if ($tag['name'] === 'else') {
                $currentVariable = null;
                if ($multiline) {
                    $this->consumeLineEnding($template, $offset);
                }
                continue;
            }

            if (preg_match('/^elseif\s*\((.*)\)$/s', $tag['content'], $matches) !== 1) {
                throw new InvalidArgumentException("Malformed template elseif directive: {$tag['content']}");
            }

            $currentVariable = trim($matches[1]);
            if ($multiline) {
                $this->consumeLineEnding($template, $offset);
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderLoop(string $template, int &$offset, array $context, string $variable, bool $multiline): string
    {
        [$bodyTemplate, $separatorTemplate] = $this->extractLoopTemplates($template, $offset, $multiline);
        $parsed = $this->parseExpression($variable);
        $value = $this->resolveParsedExpression($parsed, $context);
        $items = $this->iterationItems($value);
        if ($items === []) {
            return '';
        }

        $separatorOffset = 0;
        [$separator] = $this->renderSection($separatorTemplate, $separatorOffset, $context, []);
        $rendered = [];
        foreach ($items as $item) {
            $itemContext = $this->contextWithIterationValue($context, $parsed['parts'], $item);
            $bodyOffset = 0;
            [$rendered[]] = $this->renderSection($bodyTemplate, $bodyOffset, $itemContext, []);
        }

        return implode($separator, $rendered);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function extractLoopTemplates(string $template, int &$offset, bool $multiline): array
    {
        $start = $offset;
        $bodyEnd = null;
        $separatorStart = null;
        $depth = 0;
        $scan = $offset;
        $length = strlen($template);

        while ($scan < $length) {
            $marker = strpos($template, '$', $scan);
            if ($marker === false) {
                break;
            }

            if (str_starts_with(substr($template, $marker), '$--')) {
                $newline = $this->findLineEnding($template, $marker + 3);
                $scan = $newline === null ? $length : $newline['end'];
                continue;
            }

            if (str_starts_with(substr($template, $marker), '$$')) {
                $scan = $marker + 2;
                continue;
            }

            $directive = $this->readDirective($template, $marker);
            if ($directive === null) {
                $scan = $marker + 1;
                continue;
            }

            $content = trim($directive['content'], " \t");
            if (preg_match('/^for\s*\(/', $content) === 1) {
                $depth++;
            } elseif ($content === 'endfor') {
                if ($depth === 0) {
                    if ($bodyEnd === null) {
                        $bodyEnd = $marker;
                        $separatorTemplate = '';
                    } else {
                        $separatorTemplate = substr($template, (int) $separatorStart, $marker - (int) $separatorStart);
                    }
                    $bodyTemplate = substr($template, $start, $bodyEnd - $start);
                    $offset = $directive['end'];
                    if ($multiline) {
                        $this->consumeLineEnding($template, $offset);
                    }

                    return [$bodyTemplate, $separatorTemplate];
                }
                $depth--;
            } elseif ($content === 'sep' && $depth === 0 && $bodyEnd === null) {
                $bodyEnd = $marker;
                $separatorStart = $directive['end'];
                if ($multiline) {
                    $this->consumeLineEnding($template, $separatorStart);
                }
            }

            $scan = $directive['end'];
        }

        throw new InvalidArgumentException('Unclosed template loop');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderInterpolation(string $expression, array $context): string
    {
        [$expression, $separator] = $this->splitLiteralSeparator($expression);
        $parsed = $this->parseExpression($expression);
        $value = $this->resolveParsedExpression($parsed, $context);

        if ($separator !== null) {
            $items = $this->iterationItems($value);
            return implode($separator, array_map(fn (mixed $item): string => $this->valueToString($item), $items));
        }

        return $this->valueToString($value);
    }

    /**
     * @return array{0:string, 1:string|null}
     */
    private function splitLiteralSeparator(string $expression): array
    {
        $expression = trim($expression, " \t");
        if (!str_ends_with($expression, ']')) {
            return [$expression, null];
        }

        $open = strrpos($expression, '[');
        if ($open === false) {
            return [$expression, null];
        }

        return [rtrim(substr($expression, 0, $open), " \t"), substr($expression, $open + 1, -1)];
    }

    /**
     * @return array{parts:list<string>, pipes:list<array{name:string, args:list<string>}>}
     */
    private function parseExpression(string $expression): array
    {
        $segments = explode('/', trim($expression, " \t"));
        $path = array_shift($segments);
        if ($path === null || !preg_match('/^(?:it|[A-Za-z][A-Za-z0-9_-]*)(?:\.(?:it|[A-Za-z][A-Za-z0-9_-]*))*$/', $path)) {
            throw new InvalidArgumentException("Invalid template variable: {$expression}");
        }

        $pipes = [];
        foreach ($segments as $segment) {
            $segment = trim($segment, " \t");
            if ($segment === '') {
                continue;
            }
            if (preg_match('/^([A-Za-z]+)(?:\s+(.*))?$/', $segment, $matches) !== 1) {
                throw new InvalidArgumentException("Invalid template pipe: {$segment}");
            }
            $pipes[] = [
                'name' => strtolower($matches[1]),
                'args' => isset($matches[2]) ? $this->parsePipeArguments($matches[2]) : [],
            ];
        }

        return ['parts' => explode('.', $path), 'pipes' => $pipes];
    }

    /**
     * @return list<string>
     */
    private function parsePipeArguments(string $arguments): array
    {
        preg_match_all('/"((?:\\\\.|[^"\\\\])*)"|(\S+)/', $arguments, $matches, PREG_SET_ORDER);
        $parsed = [];
        foreach ($matches as $match) {
            $parsed[] = str_starts_with($match[0], '"')
                ? stripcslashes(substr($match[0], 1, -1))
                : $match[0];
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $context
     * @return mixed
     */
    private function resolveExpression(string $expression, array $context): mixed
    {
        return $this->resolveParsedExpression($this->parseExpression($expression), $context);
    }

    /**
     * @param array{parts:list<string>, pipes:list<array{name:string, args:list<string>}>} $parsed
     * @param array<string, mixed> $context
     */
    private function resolveParsedExpression(array $parsed, array $context): mixed
    {
        $value = $this->lookupPath($context, $parsed['parts']);
        foreach ($parsed['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe['name'], $pipe['args'], $value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $parts
     */
    private function lookupPath(array $context, array $parts): mixed
    {
        $value = $context;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * @param list<string> $arguments
     */
    private function applyPipe(string $name, array $arguments, mixed $value): mixed
    {
        return match ($name) {
            'uppercase' => $this->mapText($value, fn (string $text): string => function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text)),
            'lowercase' => $this->mapText($value, fn (string $text): string => function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text)),
            'length' => $this->valueLength($value),
            'pairs' => $this->pairsValue($value),
            'first' => is_array($value) && array_is_list($value) && $value !== [] ? $value[0] : $value,
            'last' => is_array($value) && array_is_list($value) && $value !== [] ? $value[array_key_last($value)] : $value,
            'rest' => is_array($value) && array_is_list($value) ? array_slice($value, 1) : $value,
            'allbutlast' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 0, -1) : $value,
            'reverse' => is_array($value) && array_is_list($value) ? array_reverse($value) : strrev($this->valueToString($value)),
            'chomp' => $this->mapText($value, static fn (string $text): string => rtrim($text, "\r\n")),
            'alpha' => $this->mapText($value, fn (string $text): string => $this->alphaPipe($text)),
            'roman' => $this->mapText($value, fn (string $text): string => $this->romanPipe($text)),
            default => throw new InvalidArgumentException("Unknown template pipe: {$name}"),
        };
    }

    private function valueLength(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }
        if (is_bool($value) || $value === null) {
            return 0;
        }

        $text = $this->valueToString($value);
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    /**
     * @return mixed
     */
    private function pairsValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $pairs = [];
        if (array_is_list($value)) {
            foreach (array_values($value) as $index => $item) {
                $pairs[] = ['key' => (string) ($index + 1), 'value' => $item];
            }
            return $pairs;
        }

        foreach ($value as $key => $item) {
            $pairs[] = ['key' => (string) $key, 'value' => $item];
        }

        return $pairs;
    }

    /**
     * @param callable(string): string $callback
     */
    private function mapText(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->mapText($item, $callback);
            }
            return $mapped;
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        return $callback($this->valueToString($value));
    }

    private function alphaPipe(string $text): string
    {
        if (!preg_match('/^-?\d+$/', $text)) {
            return $text;
        }

        $number = (int) $text;
        if ($number <= 0) {
            return $text;
        }

        return chr(ord('a') + (($number - 1) % 26));
    }

    private function romanPipe(string $text): string
    {
        if (!preg_match('/^-?\d+$/', $text)) {
            return $text;
        }

        $number = (int) $text;
        if ($number < 0 || $number >= 4000) {
            return $text;
        }
        if ($number === 0) {
            return '';
        }

        $map = [
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ];
        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @return list<mixed>
     */
    private function iterationItems(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        return [$value];
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $parts
     * @return array<string, mixed>
     */
    private function contextWithIterationValue(array $context, array $parts, mixed $value): array
    {
        $context['it'] = $value;
        if ($parts === ['it']) {
            return $context;
        }

        $this->setPath($context, $parts, $value);
        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $parts
     */
    private function setPath(array &$context, array $parts, mixed $value): void
    {
        $part = array_shift($parts);
        if ($part === null) {
            return;
        }
        if ($parts === []) {
            $context[$part] = $value;
            return;
        }
        if (!isset($context[$part]) || !is_array($context[$part]) || array_is_list($context[$part])) {
            $context[$part] = [];
        }

        $this->setPath($context[$part], $parts, $value);
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return true;
            }
            foreach ($value as $item) {
                if ($this->isTruthy($item)) {
                    return true;
                }
            }
            return false;
        }

        return $this->valueToString($value) !== '';
    }

    private function valueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->removeFinalNewline($value);
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return 'true';
            }

            $rendered = '';
            foreach ($value as $item) {
                $rendered .= $this->valueToString($item);
            }
            return $rendered;
        }

        return '';
    }

    private function removeFinalNewline(string $text): string
    {
        if (str_ends_with($text, "\r\n")) {
            return substr($text, 0, -2);
        }
        if (str_ends_with($text, "\n") || str_ends_with($text, "\r")) {
            return substr($text, 0, -1);
        }

        return $text;
    }

    private function controlTagName(string $content): ?string
    {
        return match (true) {
            $content === 'else' => 'else',
            $content === 'endif' => 'endif',
            $content === 'sep' => 'sep',
            $content === 'endfor' => 'endfor',
            preg_match('/^elseif\s*\(/', $content) === 1 => 'elseif',
            default => null,
        };
    }

    /**
     * @return array{content:string, end:int}|null
     */
    private function readDirective(string $template, int $offset): ?array
    {
        if (!isset($template[$offset]) || $template[$offset] !== '$') {
            return null;
        }

        if (isset($template[$offset + 1]) && $template[$offset + 1] === '{') {
            $end = strpos($template, '}', $offset + 2);
            if ($end === false) {
                return null;
            }
            return [
                'content' => substr($template, $offset + 2, $end - $offset - 2),
                'end' => $end + 1,
            ];
        }

        $end = strpos($template, '$', $offset + 1);
        if ($end === false) {
            return null;
        }

        return [
            'content' => substr($template, $offset + 1, $end - $offset - 1),
            'end' => $end + 1,
        ];
    }

    /**
     * @return array{start:int, end:int}|null
     */
    private function findLineEnding(string $template, int $offset): ?array
    {
        $length = strlen($template);
        for ($index = $offset; $index < $length; $index++) {
            if ($template[$index] === "\n") {
                return ['start' => $index, 'end' => $index + 1];
            }
            if ($template[$index] === "\r") {
                $end = isset($template[$index + 1]) && $template[$index + 1] === "\n" ? $index + 2 : $index + 1;
                return ['start' => $index, 'end' => $end];
            }
        }

        return null;
    }

    private function consumeLineEnding(string $template, int &$offset): bool
    {
        if (!isset($template[$offset])) {
            return false;
        }
        if ($template[$offset] === "\n") {
            $offset++;
            return true;
        }
        if ($template[$offset] === "\r") {
            $offset += isset($template[$offset + 1]) && $template[$offset + 1] === "\n" ? 2 : 1;
            return true;
        }

        return false;
    }
}
