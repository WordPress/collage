<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * A compact, read-only EPUB archive view backed by PHP's ZipArchive. It
 * indexes only the names and integrity fields needed by EpubReader, rather
 * than retaining the complete generic package model in PHP memory.
 */
final class EpubZipArchive implements EpubArchive
{
    /**
     * @param array<string, array{index:int, size:int, crc:int, directory:bool}> $entries
     */
    private function __construct(
        private readonly \ZipArchive $archive,
        private readonly array $entries,
        private readonly ?string $temporaryPath,
    ) {
    }

    public static function fromString(string $bytes): self
    {
        $path = tempnam(sys_get_temp_dir(), 'port-libs-epub-');
        if (!is_string($path)) {
            throw new \RuntimeException('Unable to create a temporary EPUB archive file');
        }

        try {
            if (file_put_contents($path, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('Unable to write a temporary EPUB archive file');
            }

            $archive = new \ZipArchive();
            $opened = $archive->open($path, \ZipArchive::RDONLY);
            if ($opened !== true) {
                throw new \RuntimeException('Unable to open EPUB ZIP package');
            }

            try {
                $entries = self::indexEntries($archive);
            } catch (\Throwable $exception) {
                $archive->close();
                throw $exception;
            }

            return new self($archive, $entries, $path);
        } catch (\Throwable $exception) {
            @unlink($path);
            if ($exception instanceof \RuntimeException) {
                throw $exception;
            }
            throw new \RuntimeException('Unable to open EPUB ZIP package', 0, $exception);
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Unable to open EPUB archive '{$path}'");
        }

        $archive = new \ZipArchive();
        $opened = $archive->open($path, \ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new \RuntimeException("Unable to open EPUB ZIP package '{$path}'");
        }

        try {
            $entries = self::indexEntries($archive);
        } catch (\Throwable $exception) {
            $archive->close();
            throw $exception;
        }

        return new self($archive, $entries, null);
    }

    public function __destruct()
    {
        $this->archive->close();
        if ($this->temporaryPath !== null) {
            @unlink($this->temporaryPath);
        }
    }

    public function has(string $partName): bool
    {
        return isset($this->entries[self::normalizeLookupPartName($partName)]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    public function read(string $partName): string
    {
        return $this->readBounded($partName, PHP_INT_MAX);
    }

    public function readBounded(string $partName, int $maxUncompressedBytes): string
    {
        if ($maxUncompressedBytes < 0) {
            throw new \InvalidArgumentException('ZIP entry read limit must not be negative');
        }

        [$name, $entry] = $this->entry($partName);
        if ($entry['size'] > $maxUncompressedBytes) {
            throw new \RuntimeException(
                "ZIP package entry {$name} exceeds maximum uncompressed read size {$maxUncompressedBytes} bytes"
            );
        }
        if ($entry['directory']) {
            return '';
        }

        $stream = $this->openEntryStream($name);
        $contents = '';
        $length = 0;
        $crc = hash_init('crc32b');
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw new \RuntimeException("Unable to read ZIP package entry: {$name}");
                }
                if ($chunk === '') {
                    continue;
                }
                $length += strlen($chunk);
                if ($length > $maxUncompressedBytes) {
                    throw new \RuntimeException(
                        "ZIP package entry {$name} exceeds maximum uncompressed read size {$maxUncompressedBytes} bytes"
                    );
                }
                hash_update($crc, $chunk);
                $contents .= $chunk;
            }
        } finally {
            fclose($stream);
        }

        $this->assertEntryIntegrity($name, $entry, $length, hash_final($crc));

        return $contents;
    }

    /**
     * @return array{byteLength:int, sha1:string}
     */
    public function entryDigest(string $partName): array
    {
        [$name, $entry] = $this->entry($partName);
        if ($entry['directory']) {
            return [
                'byteLength' => 0,
                'sha1' => sha1(''),
            ];
        }

        $stream = $this->openEntryStream($name);
        $length = 0;
        $crc = hash_init('crc32b');
        $sha1 = hash_init('sha1');
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw new \RuntimeException("Unable to read ZIP package entry: {$name}");
                }
                if ($chunk === '') {
                    continue;
                }
                $length += strlen($chunk);
                hash_update($crc, $chunk);
                hash_update($sha1, $chunk);
            }
        } finally {
            fclose($stream);
        }

        $this->assertEntryIntegrity($name, $entry, $length, hash_final($crc));

        return [
            'byteLength' => $length,
            'sha1' => hash_final($sha1),
        ];
    }

    /**
     * @return array{0:string, 1:array{index:int, size:int, crc:int, directory:bool}}
     */
    private function entry(string $partName): array
    {
        $name = self::normalizeLookupPartName($partName);
        $entry = $this->entries[$name] ?? null;
        if (!is_array($entry)) {
            throw new \RuntimeException("ZIP package entry not found: {$partName}");
        }

        return [$name, $entry];
    }

    /**
     * @return resource
     */
    private function openEntryStream(string $name)
    {
        $stream = $this->archive->getStream($name);
        if (!is_resource($stream)) {
            throw new \RuntimeException("Unable to open ZIP package entry stream: {$name}");
        }

        return $stream;
    }

    /**
     * @param array{index:int, size:int, crc:int, directory:bool} $entry
     */
    private function assertEntryIntegrity(string $name, array $entry, int $length, string $crc32b): void
    {
        if ($length !== $entry['size']) {
            throw new \RuntimeException("ZIP package entry {$name} expanded to an unexpected size");
        }
        if ((int) hexdec($crc32b) !== $entry['crc']) {
            throw new \RuntimeException("ZIP package entry {$name} failed CRC32 verification");
        }
    }

    /**
     * @return array<string, array{index:int, size:int, crc:int, directory:bool}>
     */
    private static function indexEntries(\ZipArchive $archive): array
    {
        $entries = [];
        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $stat = $archive->statIndex($index);
            $name = is_array($stat) && is_string($stat['name'] ?? null) ? $stat['name'] : '';
            self::assertSafePartName($name);
            if (isset($entries[$name])) {
                throw new \RuntimeException("Duplicate ZIP package entry: {$name}");
            }

            $size = is_array($stat) && isset($stat['size']) ? (int) $stat['size'] : -1;
            $crc = is_array($stat) && isset($stat['crc']) ? (int) $stat['crc'] : -1;
            if ($size < 0 || $crc < 0) {
                throw new \RuntimeException("ZIP package entry has invalid metadata: {$name}");
            }

            $entries[$name] = [
                'index' => $index,
                'size' => $size,
                'crc' => $crc,
                'directory' => str_ends_with($name, '/'),
            ];
        }

        return $entries;
    }

    private static function normalizeLookupPartName(string $partName): string
    {
        $name = ltrim($partName, '/');
        self::assertSafePartName($name);

        return $name;
    }

    private static function assertSafePartName(string $name): void
    {
        if ($name === '') {
            throw new \RuntimeException('ZIP package entry names must not be empty');
        }
        if (
            preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || (preg_match('//u', $name) === 1 && preg_match('/\p{Cc}/u', $name) === 1)
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('/^[A-Za-z]:/', $name) === 1
        ) {
            throw new \RuntimeException("Unsafe ZIP package entry name: {$name}");
        }

        $segments = explode('/', $name);
        $lastSegment = count($segments) - 1;
        foreach ($segments as $index => $segment) {
            if ($index === $lastSegment && $segment === '') {
                continue;
            }
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException("Unsafe ZIP package entry name: {$name}");
            }
        }
    }

}
