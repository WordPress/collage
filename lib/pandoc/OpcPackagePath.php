<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcPackagePath
{
    public static function canonicalPartName(string $partName, bool $allowRoot = false): string
    {
        if ($partName === '') {
            throw new \InvalidArgumentException('OPC part name must not be empty');
        }

        if (str_contains($partName, "\0") || str_contains($partName, '\\')) {
            throw new \InvalidArgumentException('OPC part names must use slash-separated package paths');
        }

        if (str_contains($partName, '?') || str_contains($partName, '#')) {
            throw new \InvalidArgumentException('OPC part names must not include URI query or fragment components');
        }

        $path = str_starts_with($partName, '/') ? $partName : '/' . $partName;
        if ($path === '/') {
            if ($allowRoot) {
                return '/';
            }

            throw new \InvalidArgumentException('OPC part name must identify a package part');
        }

        $segments = [];
        foreach (explode('/', $path) as $index => $segment) {
            if ($index === 0 && $segment === '') {
                continue;
            }

            if ($segment === '') {
                throw new \InvalidArgumentException('OPC part path must not contain empty path segments');
            }

            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new \InvalidArgumentException('OPC part path must not traverse above the package root');
                }
                array_pop($segments);
                continue;
            }

            if (str_ends_with($segment, '.')) {
                throw new \InvalidArgumentException('OPC part path segments must not end with a dot');
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $segment) === 1) {
                throw new \InvalidArgumentException('OPC part path segments must not contain control characters');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            if ($allowRoot) {
                return '/';
            }

            throw new \InvalidArgumentException('OPC part name must identify a package part');
        }

        return '/' . implode('/', $segments);
    }

    public static function canonicalPartNameFromUri(string $partName, bool $allowRoot = false): string
    {
        if ($partName === '') {
            throw new \InvalidArgumentException('OPC part name must not be empty');
        }

        if (str_contains($partName, '?') || str_contains($partName, '#')) {
            throw new \InvalidArgumentException('OPC part names must not include URI query or fragment components');
        }

        if (str_contains($partName, "\0") || str_contains($partName, '\\')) {
            throw new \InvalidArgumentException('OPC part names must use slash-separated package paths');
        }

        $path = str_starts_with($partName, '/') ? $partName : '/' . $partName;

        return self::canonicalPartName(self::decodeUriPath($path, 'OPC part name'), $allowRoot);
    }

    public static function partNameToUri(string $partName, bool $allowRoot = false): string
    {
        $partName = self::canonicalPartName($partName, $allowRoot);
        if ($partName === '/') {
            return '/';
        }

        $segments = array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', ltrim($partName, '/')),
        );

        return '/' . implode('/', $segments);
    }

    public static function resolveInternalTarget(string $sourcePartName, string $target): string
    {
        if ($target === '') {
            throw new \InvalidArgumentException('OPC relationship target must not be empty');
        }

        if (str_contains($target, "\0") || str_contains($target, '\\')) {
            throw new \InvalidArgumentException('OPC relationship targets must use slash-separated paths');
        }

        if (preg_match('/[\x00-\x20\x7F]/', $target) === 1) {
            throw new \InvalidArgumentException('OPC internal relationship target contains invalid URI bytes');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            throw new \InvalidArgumentException('OPC internal relationship target must not be an absolute URI');
        }

        $split = strcspn($target, '?#');
        $path = substr($target, 0, $split);
        $suffix = substr($target, $split);
        self::assertUriReferenceSuffix($suffix, 'OPC internal relationship target');
        if ($path === '') {
            $source = self::canonicalPartName($sourcePartName, true);
            if ($source === '/') {
                throw new \InvalidArgumentException('OPC relationship target path must not be empty');
            }

            return $source . $suffix;
        }

        $path = self::decodeUriPath($path, 'OPC relationship target');
        if (str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('OPC internal relationship target must not include a URI authority');
        }

        if (str_starts_with($path, '/')) {
            return self::canonicalPartName($path) . $suffix;
        }

        $source = self::canonicalPartName($sourcePartName, true);
        $base = $source === '/' ? '' : dirname($source);

        return self::canonicalPartName($base . '/' . $path) . $suffix;
    }

    public static function stripQueryAndFragment(string $partName): string
    {
        $split = strcspn($partName, '?#');

        return substr($partName, 0, $split);
    }

    private static function decodeUriPath(string $path, string $label): string
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $path) === 1) {
            throw new \InvalidArgumentException($label . ' contains malformed percent escape');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment);
            if (($decoded === '.' || $decoded === '..') && $segment !== $decoded) {
                throw new \InvalidArgumentException($label . ' contains unsafe percent-encoded dot segment');
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1 || str_contains($decoded, '/') || str_contains($decoded, '\\')) {
                throw new \InvalidArgumentException($label . ' contains unsafe percent-encoded path bytes');
            }

            $segments[] = $decoded;
        }

        return implode('/', $segments);
    }

    private static function assertUriReferenceSuffix(string $suffix, string $label): void
    {
        if ($suffix === '') {
            return;
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $suffix) === 1) {
            throw new \InvalidArgumentException($label . ' query or fragment contains malformed percent escape');
        }

        if (preg_match_all('/%([0-9A-Fa-f]{2})/', $suffix, $matches) === 0) {
            return;
        }

        foreach ($matches[1] as $hex) {
            $byte = hexdec($hex);
            if ($byte < 0x20 || $byte === 0x7F) {
                throw new \InvalidArgumentException($label . ' query or fragment contains unsafe percent-encoded byte');
            }
        }
    }
}
