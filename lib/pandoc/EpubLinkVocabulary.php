<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * EPUB OPF metadata-link vocabulary annotations used by the content reader.
 * This deliberately owns the small reader-facing surface instead of loading
 * the full package inspection model for every EPUB import.
 */
final class EpubLinkVocabulary
{
    /** @var array<string, string> */
    private const RESERVED_PREFIXES = [
        'a11y' => 'http://www.idpf.org/epub/vocab/package/a11y/#',
        'dcterms' => 'http://purl.org/dc/terms/',
        'media' => 'http://www.idpf.org/epub/vocab/overlays/#',
        'rendition' => 'http://www.idpf.org/vocab/rendition/#',
        'schema' => 'http://schema.org/',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];

    /**
     * @param list<array<string, mixed>> $links
     * @return array{links:list<array<string, mixed>>, summary:array<string, mixed>}
     */
    public static function annotate(array $links, string $prefixDeclaration): array
    {
        $bindings = array_replace(self::RESERVED_PREFIXES, self::prefixBindings($prefixDeclaration));
        foreach ($links as $index => $link) {
            $linkIndex = (int) ($link['index'] ?? $index);
            $rel = self::stringTokens($link['rel'] ?? []);
            $properties = self::stringTokens($link['properties'] ?? []);
            $link['relVocabulary'] = self::tokenReport($rel, $bindings, 'rel', $linkIndex);
            $link['propertyVocabulary'] = self::tokenReport($properties, $bindings, 'properties', $linkIndex);
            $links[$index] = $link;
        }

        return [
            'links' => $links,
            'summary' => self::summary($links),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function prefixBindings(string $raw): array
    {
        $value = trim($raw);
        $bindings = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $offset += strspn($value, " \t\r\n", $offset);
            if ($offset >= $length) {
                break;
            }

            $segment = substr($value, $offset);
            if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):[ \t\r\n]+([^ \t\r\n]+)/', $segment, $match) !== 1) {
                break;
            }

            $bindings[$match[1]] = $match[2];
            $offset += strlen($match[0]);
        }

        return $bindings;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     * @return array<string, mixed>
     */
    private static function tokenReport(array $tokens, array $prefixBindings, string $kind, int $linkIndex): array
    {
        $items = [];
        $diagnostics = [];
        $seen = [];
        $validCount = 0;
        $resolvedCount = 0;
        $absoluteUrlCount = 0;
        $duplicateCount = 0;

        foreach ($tokens as $index => $token) {
            $value = trim($token);
            if ($value === '') {
                continue;
            }

            $diagnosticsForToken = [];
            $prefix = null;
            $localName = null;
            $iri = null;
            $resolved = false;
            $absoluteUrlWithFragment = self::isAbsoluteUrlWithFragment($value);
            $looksAbsolute = self::isAbsoluteUri($value);
            $tokenKind = 'nmtoken';
            $valid = true;

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)$/', $value, $matches) === 1) {
                $tokenKind = 'prefixed-nmtoken';
                $prefix = $matches[1];
                $localName = $matches[2];
                if (isset($prefixBindings[$prefix])) {
                    $resolved = true;
                    $iri = $prefixBindings[$prefix] . $localName;
                    ++$resolvedCount;
                } else {
                    $diagnosticsForToken[] = self::unknownPrefixDiagnostic($kind, $linkIndex, (int) $index, $value, $prefix);
                }
            } elseif ($absoluteUrlWithFragment) {
                $tokenKind = 'absolute-url-with-fragment';
                $iri = $value;
                ++$absoluteUrlCount;
            } elseif ($looksAbsolute) {
                $tokenKind = 'absolute-url';
                $valid = false;
                $diagnosticsForToken[] = self::invalidAbsoluteUrlDiagnostic($kind, $linkIndex, (int) $index, $value);
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $value) !== 1) {
                $tokenKind = 'invalid';
                $valid = false;
                $diagnosticsForToken[] = self::invalidTokenDiagnostic($kind, $linkIndex, (int) $index, $value);
            }

            if (isset($seen[$value])) {
                ++$duplicateCount;
                $diagnosticsForToken[] = [
                    'type' => 'duplicate-metadata-link-' . $kind . '-token',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'previousIndex' => $seen[$value],
                    'value' => $value,
                    'message' => 'EPUB OPF metadata link vocabulary value is repeated',
                ];
            } else {
                $seen[$value] = (int) $index;
            }

            if ($valid) {
                ++$validCount;
            }

            $items[] = [
                'index' => (int) $index,
                'value' => $value,
                'kind' => $tokenKind,
                'valid' => $valid,
                'prefix' => $prefix,
                'localName' => $localName,
                'iri' => $iri,
                'resolved' => $resolved,
                'absoluteUrlWithFragment' => $absoluteUrlWithFragment,
                'diagnostics' => $diagnosticsForToken,
            ];
            array_push($diagnostics, ...$diagnosticsForToken);
        }

        return [
            'raw' => array_values($tokens),
            'kind' => $kind,
            'linkIndex' => $linkIndex,
            'count' => count($items),
            'validCount' => $validCount,
            'invalidCount' => count($items) - $validCount,
            'resolvedCount' => $resolvedCount,
            'absoluteUrlCount' => $absoluteUrlCount,
            'duplicateCount' => $duplicateCount,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, mixed>
     */
    private static function summary(array $links): array
    {
        $rels = [];
        $properties = [];
        $diagnostics = [];
        $relTokenCount = 0;
        $propertyTokenCount = 0;
        $resolvedTokenCount = 0;
        $absoluteUrlTokenCount = 0;
        $duplicateTokenCount = 0;

        foreach ($links as $link) {
            foreach (['rel' => 'relVocabulary', 'properties' => 'propertyVocabulary'] as $tokenField => $reportField) {
                foreach (self::stringTokens($link[$tokenField] ?? []) as $token) {
                    if ($tokenField === 'rel') {
                        $rels[$token] = ($rels[$token] ?? 0) + 1;
                        ++$relTokenCount;
                    } else {
                        $properties[$token] = ($properties[$token] ?? 0) + 1;
                        ++$propertyTokenCount;
                    }
                }

                $report = is_array($link[$reportField] ?? null) ? $link[$reportField] : [];
                $resolvedTokenCount += (int) ($report['resolvedCount'] ?? 0);
                $absoluteUrlTokenCount += (int) ($report['absoluteUrlCount'] ?? 0);
                $duplicateTokenCount += (int) ($report['duplicateCount'] ?? 0);
                foreach ($report['diagnostics'] ?? [] as $diagnostic) {
                    if (is_array($diagnostic)) {
                        $diagnostics[] = $diagnostic;
                    }
                }
            }
        }

        ksort($rels);
        ksort($properties);

        return [
            'present' => $relTokenCount > 0 || $propertyTokenCount > 0,
            'linkCount' => count($links),
            'relTokenCount' => $relTokenCount,
            'propertyTokenCount' => $propertyTokenCount,
            'resolvedTokenCount' => $resolvedTokenCount,
            'absoluteUrlTokenCount' => $absoluteUrlTokenCount,
            'duplicateTokenCount' => $duplicateTokenCount,
            'diagnosticCount' => count($diagnostics),
            'rels' => $rels,
            'properties' => $properties,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return list<string> */
    private static function stringTokens(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $tokens = [];
        foreach ($value as $token) {
            if (is_string($token)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /** @return array<string, mixed> */
    private static function unknownPrefixDiagnostic(string $kind, int $linkIndex, int $index, string $value, string $prefix): array
    {
        return [
            'type' => 'unknown-metadata-link-' . $kind . '-prefix',
            'kind' => $kind,
            'linkIndex' => $linkIndex,
            'index' => $index,
            'value' => $value,
            'prefix' => $prefix,
            'message' => 'EPUB OPF metadata link vocabulary token uses a prefix that is not declared on the package element',
        ];
    }

    /** @return array<string, mixed> */
    private static function invalidAbsoluteUrlDiagnostic(string $kind, int $linkIndex, int $index, string $value): array
    {
        return [
            'type' => 'invalid-metadata-link-' . $kind . '-url-fragment',
            'kind' => $kind,
            'linkIndex' => $linkIndex,
            'index' => $index,
            'value' => $value,
            'message' => 'EPUB OPF metadata link vocabulary URLs must include a fragment identifier',
        ];
    }

    /** @return array<string, mixed> */
    private static function invalidTokenDiagnostic(string $kind, int $linkIndex, int $index, string $value): array
    {
        return [
            'type' => 'invalid-metadata-link-' . $kind . '-token',
            'kind' => $kind,
            'linkIndex' => $linkIndex,
            'index' => $index,
            'value' => $value,
            'message' => 'EPUB OPF metadata link vocabulary values must be NMTOKENs, prefixed names, or absolute URLs with fragments',
        ];
    }

    private static function isAbsoluteUri(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1;
    }

    private static function isAbsoluteUrlWithFragment(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:[^#\s]*#[^\s]+$/', $value) === 1;
    }
}
