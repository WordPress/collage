<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubArchiveFactory
{
    public static function fromString(string $bytes): EpubArchive
    {
        if (class_exists(\ZipArchive::class)) {
            try {
                return EpubZipArchive::fromString($bytes);
            } catch (\RuntimeException) {
                // The bounded pure-PHP package reader remains the fallback
                // for ZIP variants the extension cannot open.
            }
        }

        return ZipPackage::fromString($bytes);
    }

    public static function fromFile(string $path): EpubArchive
    {
        if (class_exists(\ZipArchive::class)) {
            try {
                return EpubZipArchive::fromFile($path);
            } catch (\RuntimeException) {
                // The bounded pure-PHP package reader remains the fallback
                // for ZIP variants the extension cannot open.
            }
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Unable to open EPUB package '{$path}'");
        }

        return ZipPackage::fromString($bytes);
    }
}
