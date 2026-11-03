<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Calibrates the external Pandoc reference budget from package expansion.
 *
 * Office packages can be small ZIP files that expand into large XML trees.
 * A timeout based only on compressed source bytes incorrectly classifies
 * those normal documents as unavailable external baselines.
 */
final class ShowcaseHaskellReferenceTimeout
{
    private const SMALL_SOURCE_BYTES = 131072;
    private const MEDIUM_SOURCE_BYTES = 524288;
    private const SMALL_TIMEOUT_SECONDS = 35;
    private const MEDIUM_TIMEOUT_SECONDS = 90;
    private const LARGE_TIMEOUT_SECONDS = 300;
    private const MAX_TIMEOUT_SECONDS = 900;
    private const EXPANDED_PACKAGE_BASE_SECONDS = 120;
    private const EXPANDED_MEBIBYTE_SECONDS = 45;

    public static function secondsFor(string $path): int
    {
        $sourceBytes = is_file($path) ? filesize($path) : false;
        $timeout = !is_int($sourceBytes) || $sourceBytes <= self::SMALL_SOURCE_BYTES
            ? self::SMALL_TIMEOUT_SECONDS
            : ($sourceBytes <= self::MEDIUM_SOURCE_BYTES
                ? self::MEDIUM_TIMEOUT_SECONDS
                : self::LARGE_TIMEOUT_SECONDS);

        $expandedBytes = self::archiveUncompressedBytes($path);
        if ($expandedBytes === null) {
            return $timeout;
        }

        $expandedMebibytes = (int) ceil($expandedBytes / 1048576);
        $expandedTimeout = self::EXPANDED_PACKAGE_BASE_SECONDS
            + ($expandedMebibytes * self::EXPANDED_MEBIBYTE_SECONDS);

        return min(self::MAX_TIMEOUT_SECONDS, max($timeout, $expandedTimeout));
    }

    private static function archiveUncompressedBytes(string $path): ?int
    {
        if (!is_file($path) || !class_exists(\ZipArchive::class)) {
            return null;
        }

        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) {
            return null;
        }

        $total = 0;
        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $size = is_array($entry) ? (int) ($entry['size'] ?? 0) : 0;
                $total = min(PHP_INT_MAX, $total + max(0, $size));
            }
        } finally {
            $archive->close();
        }

        return $total;
    }
}
