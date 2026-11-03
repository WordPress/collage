<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * The narrow package surface the EPUB content reader needs. The full
 * ZipPackage model remains available for rich package inspection elsewhere.
 */
interface EpubArchive
{
    /**
     * @return list<string>
     */
    public function names(): array;

    public function has(string $partName): bool;

    public function read(string $partName): string;

    public function readBounded(string $partName, int $maxUncompressedBytes): string;

    /**
     * @return array{byteLength:int, sha1:string}
     */
    public function entryDigest(string $partName): array;
}
