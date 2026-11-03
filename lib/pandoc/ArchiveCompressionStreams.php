<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ArchiveCompressionStreams
{
    private const GZIP_HEADER_SIGNATURE = "\x1f\x8b";
    private const TAR_BLOCK_SIZE = 512;

    /**
     * @return list<array{
     *     offset:int,
     *     compressedOffset:int,
     *     compressedSize:int,
     *     flags:int,
     *     modifiedAt:?int,
     *     extra:string,
     *     originalName:?string,
     *     comment:?string,
     *     os:int,
     *     crc32:int,
     *     isize:int,
     *     data:string
     * }>
     */
    public static function gzipMembers(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('GZIP stream is empty');
        }

        $members = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            $memberOffset = $cursor;
            if (!self::hasGzipMemberHeaderAt($bytes, $cursor)) {
                $padding = substr($bytes, $cursor);
                if ($members !== [] && trim($padding, "\0") === '') {
                    break;
                }

                throw new \RuntimeException("Invalid GZIP member signature at offset {$cursor}");
            }

            self::assertRange($bytes, $cursor, 10, 'gzip member header');

            $method = ord($bytes[$cursor + 2]);
            if ($method !== 8) {
                throw new \RuntimeException("Unsupported GZIP compression method {$method}");
            }

            $flags = ord($bytes[$cursor + 3]);
            if (($flags & 0xe0) !== 0) {
                throw new \RuntimeException('Reserved GZIP header flags are not supported');
            }

            $modifiedAtValue = self::readUInt32($bytes, $cursor + 4);
            $modifiedAt = $modifiedAtValue === 0 ? null : $modifiedAtValue;
            $os = ord($bytes[$cursor + 9]);
            $cursor += 10;

            $extra = '';
            if (($flags & 0x04) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'gzip extra length');
                $extraLength = self::readUInt16($bytes, $cursor);
                $cursor += 2;
                self::assertRange($bytes, $cursor, $extraLength, 'gzip extra field');
                $extra = substr($bytes, $cursor, $extraLength);
                $cursor += $extraLength;
            }

            $originalName = null;
            if (($flags & 0x08) !== 0) {
                [$originalName, $cursor] = self::readNullTerminatedField($bytes, $cursor, 'gzip original name');
            }

            $comment = null;
            if (($flags & 0x10) !== 0) {
                [$comment, $cursor] = self::readNullTerminatedField($bytes, $cursor, 'gzip comment');
            }

            if (($flags & 0x02) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'gzip header crc');
                $storedHeaderCrc = self::readUInt16($bytes, $cursor);
                $computedHeaderCrc = self::unsignedCrc32(substr($bytes, $memberOffset, $cursor - $memberOffset)) & 0xffff;
                if ($storedHeaderCrc !== $computedHeaderCrc) {
                    throw new \RuntimeException('GZIP header CRC16 does not match the member header');
                }
                $cursor += 2;
            }

            $compressedOffset = $cursor;
            $context = inflate_init(ZLIB_ENCODING_RAW);
            $data = @inflate_add($context, substr($bytes, $compressedOffset), ZLIB_FINISH);
            if ($data === false || inflate_get_status($context) !== ZLIB_STREAM_END) {
                throw new \RuntimeException("Unable to inflate GZIP member at offset {$memberOffset}");
            }

            $compressedSize = inflate_get_read_len($context);
            if ($compressedSize <= 0) {
                throw new \RuntimeException("GZIP member at offset {$memberOffset} has no completed deflate stream");
            }

            $trailerOffset = $compressedOffset + $compressedSize;
            self::assertRange($bytes, $trailerOffset, 8, 'gzip member trailer');
            $crc32 = self::readUInt32($bytes, $trailerOffset);
            $isize = self::readUInt32($bytes, $trailerOffset + 4);

            if ($crc32 !== self::unsignedCrc32($data)) {
                throw new \RuntimeException("GZIP member at offset {$memberOffset} failed CRC32 verification");
            }

            if ($isize !== (strlen($data) & 0xffffffff)) {
                throw new \RuntimeException("GZIP member at offset {$memberOffset} has an invalid uncompressed size trailer");
            }

            $members[] = [
                'offset' => $memberOffset,
                'compressedOffset' => $compressedOffset,
                'compressedSize' => $compressedSize,
                'flags' => $flags,
                'modifiedAt' => $modifiedAt,
                'extra' => $extra,
                'originalName' => $originalName,
                'comment' => $comment,
                'os' => $os,
                'crc32' => $crc32,
                'isize' => $isize,
                'data' => $data,
            ];
            $cursor = $trailerOffset + 8;
        }

        return $members;
    }

    private static function hasGzipMemberHeaderAt(string $bytes, int $offset): bool
    {
        return $offset + 2 <= strlen($bytes)
            && substr($bytes, $offset, 2) === self::GZIP_HEADER_SIGNATURE;
    }

    public static function gzipDecode(string $bytes): string
    {
        $decoded = '';
        foreach (self::gzipMembers($bytes) as $member) {
            $decoded .= $member['data'];
        }

        return $decoded;
    }

    /**
     * @return list<array{name:string, type:string, size:int, data:string, modifiedAt:int, mode:int}>
     */
    public static function tarGzipEntries(string $bytes): array
    {
        return self::tarEntries(self::gzipDecode($bytes));
    }

    /**
     * @return array<string, string>
     */
    public static function tarGzipFiles(string $bytes): array
    {
        $files = [];
        foreach (self::tarGzipEntries($bytes) as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            if (isset($files[$entry['name']])) {
                throw new \RuntimeException("Duplicate tar file entry: {$entry['name']}");
            }

            $files[$entry['name']] = $entry['data'];
        }

        return $files;
    }

    /**
     * @return list<array{name:string, type:string, size:int, data:string, modifiedAt:int, mode:int}>
     */
    public static function tarEntries(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        $entries = [];
        $cursor = 0;
        $length = strlen($bytes);
        $foundEnd = false;

        while ($cursor < $length) {
            self::assertRange($bytes, $cursor, self::TAR_BLOCK_SIZE, 'tar header');
            $header = substr($bytes, $cursor, self::TAR_BLOCK_SIZE);

            if (self::isZeroBlock($header)) {
                self::assertRemainingTarPadding($bytes, $cursor);
                $foundEnd = true;
                break;
            }

            self::assertTarChecksum($header);

            $name = self::tarHeaderString(substr($header, 0, 100));
            $prefix = self::tarHeaderString(substr($header, 345, 155));
            $fullName = $prefix === '' ? $name : $prefix . '/' . $name;
            $mode = self::readTarOctal(substr($header, 100, 8), "mode for tar entry {$fullName}");
            $size = self::readTarOctal(substr($header, 124, 12), "size for tar entry {$fullName}");
            $modifiedAt = self::readTarOctal(substr($header, 136, 12), "mtime for tar entry {$fullName}");
            $typeFlag = substr($header, 156, 1);
            $typeFlag = $typeFlag === "\0" ? '0' : $typeFlag;

            $dataStart = $cursor + self::TAR_BLOCK_SIZE;
            $paddedSize = self::paddedTarSize($size);
            self::assertRange($bytes, $dataStart, $paddedSize, "tar data for {$fullName}");
            $data = substr($bytes, $dataStart, $size);

            if ($typeFlag !== '0' && $typeFlag !== '5') {
                throw new \RuntimeException("Unsupported tar entry type {$typeFlag} for {$fullName}");
            }

            self::assertSafeArchivePath($fullName, 'tar entry');

            $type = $typeFlag === '5' ? 'directory' : 'file';
            if ($type === 'directory' && $size !== 0) {
                throw new \RuntimeException("TAR directory entry {$fullName} must not contain file data");
            }

            $entries[] = [
                'name' => $fullName,
                'type' => $type,
                'size' => $size,
                'data' => $type === 'directory' ? '' : $data,
                'modifiedAt' => $modifiedAt,
                'mode' => $mode,
            ];

            $cursor = $dataStart + $paddedSize;
        }

        if (!$foundEnd) {
            throw new \RuntimeException('TAR archive is missing an end-of-archive zero block');
        }

        return $entries;
    }

    private static function paddedTarSize(int $size): int
    {
        if ($size === 0) {
            return 0;
        }

        return intdiv($size + self::TAR_BLOCK_SIZE - 1, self::TAR_BLOCK_SIZE) * self::TAR_BLOCK_SIZE;
    }

    private static function assertTarChecksum(string $header): void
    {
        $expected = self::readTarOctal(substr($header, 148, 8), 'tar header checksum');
        $checkHeader = substr($header, 0, 148) . str_repeat(' ', 8) . substr($header, 156);
        $actual = 0;
        for ($offset = 0, $length = strlen($checkHeader); $offset < $length; $offset++) {
            $actual += ord($checkHeader[$offset]);
        }

        if ($expected !== $actual) {
            throw new \RuntimeException("TAR header checksum mismatch: expected {$expected}, computed {$actual}");
        }
    }

    private static function readTarOctal(string $field, string $label): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            throw new \RuntimeException("Base-256 {$label} is not supported by this bounded tar reader");
        }

        $value = trim(str_replace("\0", ' ', $field));
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^[0-7]+$/', $value) !== 1) {
            throw new \RuntimeException("Invalid octal {$label}");
        }

        return intval($value, 8);
    }

    private static function tarHeaderString(string $field): string
    {
        $nul = strpos($field, "\0");
        if ($nul !== false) {
            return substr($field, 0, $nul);
        }

        return rtrim($field, "\0");
    }

    private static function isZeroBlock(string $block): bool
    {
        return $block === str_repeat("\0", self::TAR_BLOCK_SIZE);
    }

    private static function assertRemainingTarPadding(string $bytes, int $offset): void
    {
        $remaining = substr($bytes, $offset);
        if (strlen($remaining) < self::TAR_BLOCK_SIZE * 2) {
            throw new \RuntimeException('TAR archive end marker must contain two zero blocks');
        }

        if ($remaining === '' || strspn($remaining, "\0") !== strlen($remaining)) {
            throw new \RuntimeException('TAR archive contains non-zero data after the end-of-archive block');
        }
    }

    private static function assertSafeArchivePath(string $name, string $label): void
    {
        if ($name === '') {
            throw new \RuntimeException("{$label} path must not be empty");
        }

        if (str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '\\')) {
            throw new \RuntimeException("Unsafe {$label} path: {$name}");
        }

        $segments = explode('/', $name);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException("Unsafe {$label} path: {$name}");
            }
        }
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function readNullTerminatedField(string $bytes, int $offset, string $label): array
    {
        $terminator = strpos($bytes, "\0", $offset);
        if ($terminator === false) {
            throw new \RuntimeException("GZIP {$label} is not null terminated");
        }

        return [substr($bytes, $offset, $terminator - $offset), $terminator + 1];
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("Archive {$label} extends beyond available bytes");
        }
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 2, 'uint16');
        $values = unpack('vvalue', substr($bytes, $offset, 2));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read archive uint16 value');
        }

        return (int) $values['value'];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 4, 'uint32');
        $values = unpack('Vvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read archive uint32 value');
        }

        return (int) $values['value'];
    }

    private static function unsignedCrc32(string $bytes): int
    {
        return (int) sprintf('%u', crc32($bytes));
    }
}
