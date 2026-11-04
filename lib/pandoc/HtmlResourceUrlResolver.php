<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Resolves the HTML resource URLs used by document readers without loading
 * the broad XML/HTML inspection utility. Keep this behavior aligned with
 * XmlHtmlDom::resolveHtmlResourceUrlReference().
 */
final class HtmlResourceUrlResolver
{
    public static function resolve(?string $url, ?string $baseUrl): ?string
    {
        $url = $url === null ? null : trim($url);
        $review = self::review($url);
        if ($url === null || $review['unsafe'] || in_array($review['kind'], ['missing', 'empty', 'invalid'], true)) {
            return null;
        }
        if ($review['kind'] === 'absolute') {
            return $url;
        }
        if ($review['kind'] === 'fragment') {
            return $baseUrl === null ? $url : self::stripFragment($baseUrl) . $url;
        }

        $baseParts = $baseUrl === null ? null : self::baseParts($baseUrl);
        if ($review['kind'] === 'scheme-relative') {
            return $baseParts !== null && $baseParts['scheme'] !== ''
                ? $baseParts['scheme'] . $url
                : null;
        }
        if ($baseParts === null) {
            return null;
        }

        return self::resolveRelative($url, $baseParts);
    }

    /**
     * @return array{kind:string, scheme:?string, unsafe:bool}
     */
    private static function review(?string $url): array
    {
        if ($url === null) {
            return ['kind' => 'missing', 'scheme' => null, 'unsafe' => false];
        }
        if ($url === '') {
            return ['kind' => 'empty', 'scheme' => null, 'unsafe' => false];
        }
        if (preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $url) === 1) {
            return ['kind' => 'invalid', 'scheme' => null, 'unsafe' => true];
        }
        if (str_starts_with($url, '#')) {
            return ['kind' => 'fragment', 'scheme' => null, 'unsafe' => false];
        }
        if (str_starts_with($url, '//')) {
            return ['kind' => 'scheme-relative', 'scheme' => null, 'unsafe' => false];
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $matches) === 1) {
            $scheme = strtolower((string) $matches[1]);

            return [
                'kind' => 'absolute',
                'scheme' => $scheme,
                'unsafe' => in_array($scheme, ['data', 'file', 'javascript', 'vbscript'], true),
            ];
        }

        return ['kind' => 'relative', 'scheme' => null, 'unsafe' => false];
    }

    /**
     * @param array{scheme:string, authority:string, path:string} $baseParts
     */
    private static function resolveRelative(string $relativeUrl, array $baseParts): string
    {
        [$relativePath, $query, $fragment] = self::splitPathQueryFragment($relativeUrl);
        if ($relativePath === '') {
            $targetPath = $baseParts['path'];
        } elseif (str_starts_with($relativePath, '/')) {
            $targetPath = self::normalizePath($relativePath);
        } else {
            $targetPath = self::normalizePath(self::directory($baseParts['path']) . $relativePath);
        }

        return $baseParts['scheme'] . $baseParts['authority'] . $targetPath . $query . $fragment;
    }

    /**
     * @return array{scheme:string, authority:string, path:string}|null
     */
    private static function baseParts(string $url): ?array
    {
        [$pathAndAuthority] = self::splitPathQueryFragment($url);
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*:)(\/\/[^\/?#]*)(.*)$/', $pathAndAuthority, $matches) === 1) {
            return [
                'scheme' => (string) $matches[1],
                'authority' => (string) $matches[2],
                'path' => (string) $matches[3],
            ];
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $pathAndAuthority) === 1) {
            return null;
        }

        return ['scheme' => '', 'authority' => '', 'path' => $pathAndAuthority];
    }

    /**
     * @return array{0:string, 1:string, 2:string}
     */
    private static function splitPathQueryFragment(string $url): array
    {
        $fragment = '';
        $fragmentOffset = strpos($url, '#');
        if ($fragmentOffset !== false) {
            $fragment = substr($url, $fragmentOffset);
            $url = substr($url, 0, $fragmentOffset);
        }

        $query = '';
        $queryOffset = strpos($url, '?');
        if ($queryOffset !== false) {
            $query = substr($url, $queryOffset);
            $url = substr($url, 0, $queryOffset);
        }

        return [$url, $query, $fragment];
    }

    private static function stripFragment(string $url): string
    {
        $fragmentOffset = strpos($url, '#');

        return $fragmentOffset === false ? $url : substr($url, 0, $fragmentOffset);
    }

    private static function directory(string $path): string
    {
        if ($path === '' || str_ends_with($path, '/')) {
            return $path;
        }

        $slash = strrpos($path, '/');

        return $slash === false ? '' : substr($path, 0, $slash + 1);
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $absolute = str_starts_with($path, '/');
        $trailingSlash = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                $last = $segments === [] ? null : $segments[count($segments) - 1];
                if ($last !== null && $last !== '..') {
                    array_pop($segments);
                    continue;
                }
                if (!$absolute) {
                    $segments[] = '..';
                }
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = ($absolute ? '/' : '') . implode('/', $segments);
        if ($normalized === '' && $absolute) {
            $normalized = '/';
        }
        if ($trailingSlash && $normalized !== '' && !str_ends_with($normalized, '/')) {
            $normalized .= '/';
        }

        return $normalized;
    }
}
