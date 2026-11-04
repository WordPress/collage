<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class GzipStream
{
    private const ID1 = 0x1f;
    private const ID2 = 0x8b;
    private const COMPRESSION_METHOD_DEFLATE = 8;
    private const FLAG_TEXT = 0x01;
    private const FLAG_HEADER_CRC = 0x02;
    private const FLAG_EXTRA = 0x04;
    private const FLAG_FILENAME = 0x08;
    private const FLAG_COMMENT = 0x10;
    private const FLAG_RESERVED = 0xe0;

    /**
     * @param array{
     *     modifiedAt?:int,
     *     extraFlags?:int,
     *     operatingSystem?:int,
     *     extraFieldData?:string,
     *     filename?:string,
     *     comment?:string,
     *     headerCrc?:bool,
     *     textHint?:bool,
     *     compressionLevel?:int
     * } $options
     */
    public static function build(string $data, array $options = []): string
    {
        $modifiedAt = $options['modifiedAt'] ?? 0;
        if (!is_int($modifiedAt) || $modifiedAt < 0 || $modifiedAt > 0xffffffff) {
            throw new \RuntimeException('GZIP modifiedAt timestamp must fit in an unsigned 32-bit field');
        }

        $extraFlags = $options['extraFlags'] ?? 0;
        self::assertUInt8($extraFlags, 'GZIP extra flags');

        $operatingSystem = $options['operatingSystem'] ?? 255;
        self::assertUInt8($operatingSystem, 'GZIP operating system');

        $extraFieldData = $options['extraFieldData'] ?? null;
        if ($extraFieldData !== null && !is_string($extraFieldData)) {
            throw new \RuntimeException('GZIP extra field data must be a string');
        }

        if (is_string($extraFieldData) && strlen($extraFieldData) > 0xffff) {
            throw new \RuntimeException('GZIP extra field data is too long for a bounded stream');
        }

        if (is_string($extraFieldData)) {
            self::extraFieldsFromData($extraFieldData, 'generated extra field data');
        }

        $filename = $options['filename'] ?? null;
        if ($filename !== null) {
            self::assertTerminatedStringInput($filename, 'GZIP filename');
        }

        $comment = $options['comment'] ?? null;
        if ($comment !== null) {
            self::assertTerminatedStringInput($comment, 'GZIP comment');
        }

        $headerCrc = (bool) ($options['headerCrc'] ?? false);
        $textHint = (bool) ($options['textHint'] ?? false);
        $compressionLevel = $options['compressionLevel'] ?? -1;
        if (!is_int($compressionLevel) || $compressionLevel < -1 || $compressionLevel > 9) {
            throw new \RuntimeException('GZIP compression level must be between -1 and 9');
        }

        $flags = 0;
        if ($textHint) {
            $flags |= self::FLAG_TEXT;
        }

        if (is_string($extraFieldData)) {
            $flags |= self::FLAG_EXTRA;
        }

        if (is_string($filename)) {
            $flags |= self::FLAG_FILENAME;
        }

        if (is_string($comment)) {
            $flags |= self::FLAG_COMMENT;
        }

        if ($headerCrc) {
            $flags |= self::FLAG_HEADER_CRC;
        }

        $header = chr(self::ID1)
            . chr(self::ID2)
            . chr(self::COMPRESSION_METHOD_DEFLATE)
            . chr($flags)
            . pack('VCC', $modifiedAt, $extraFlags, $operatingSystem);

        if (is_string($extraFieldData)) {
            $header .= pack('v', strlen($extraFieldData)) . $extraFieldData;
        }

        if (is_string($filename)) {
            $header .= $filename . "\0";
        }

        if (is_string($comment)) {
            $header .= $comment . "\0";
        }

        if ($headerCrc) {
            $header .= pack('v', self::unsignedCrc32($header) & 0xffff);
        }

        $compressed = gzdeflate($data, $compressionLevel);
        if ($compressed === false) {
            throw new \RuntimeException('Unable to deflate GZIP stream payload');
        }

        return $header
            . $compressed
            . pack('VV', self::unsignedCrc32($data), strlen($data) & 0xffffffff);
    }

    public static function decode(string $bytes, ?int $maxUncompressedBytes = null): string
    {
        $data = '';
        foreach (self::inspect($bytes, $maxUncompressedBytes)['members'] as $member) {
            $data .= $member['data'];
        }

        return $data;
    }

    /**
     * @return array{
     *     members:list<array{
     *         data:string,
     *         modifiedAt:int,
     *         flags:int,
     *         textHint:bool,
     *         extraFlags:int,
     *         operatingSystem:int,
     *         extraFieldData:?string,
     *         extraFields:list<array{identifier:string,id1:int,id2:int,length:int,data:string}>,
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         headerCrcPresent:bool,
     *         headerCrc16:?int,
     *         headerCrc16Hex:?string,
     *         headerCrcOffset:?int,
     *         headerCrcCoverageSize:?int,
     *         crc32:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         memberSize:int,
     *         memberOffset:int,
     *         headerSize:int,
     *         compressedDataOffset:int,
     *         trailerOffset:int,
     *         nextMemberOffset:int,
     *         modifiedAtKnown:bool,
     *         modifiedAtText:?string,
     *         extraFlagsMeaning:string,
     *         operatingSystemName:string
     *     }>,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     trailingPaddingBytes:int
     * }
     */
    public static function inspect(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('GZIP stream is empty');
        }

        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException('GZIP max uncompressed byte limit must not be negative');
        }

        $members = [];
        $cursor = 0;
        $totalUncompressedBytes = 0;
        $length = strlen($bytes);
        $trailingPaddingBytes = 0;

        while ($cursor < $length) {
            $memberStart = $cursor;
            if (!self::hasMemberHeaderAt($bytes, $cursor)) {
                $padding = substr($bytes, $cursor);
                if ($members !== [] && trim($padding, "\0") === '') {
                    $trailingPaddingBytes = strlen($padding);
                    $cursor = $length;
                    break;
                }

                throw new \RuntimeException('Invalid GZIP member header signature');
            }

            self::assertRange($bytes, $cursor, 10, 'member header');

            $method = ord($bytes[$cursor + 2]);
            if ($method !== self::COMPRESSION_METHOD_DEFLATE) {
                throw new \RuntimeException("Unsupported GZIP compression method {$method}");
            }

            $flags = ord($bytes[$cursor + 3]);
            if (($flags & self::FLAG_RESERVED) !== 0) {
                throw new \RuntimeException('GZIP member header uses reserved flag bits');
            }

            $modifiedAt = self::readUInt32($bytes, $cursor + 4);
            $textHint = ($flags & self::FLAG_TEXT) !== 0;
            $extraFlags = ord($bytes[$cursor + 8]);
            $operatingSystem = ord($bytes[$cursor + 9]);
            $cursor += 10;

            $extraFieldData = null;
            $extraFields = [];
            if (($flags & self::FLAG_EXTRA) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'extra field length');
                $extraLength = self::readUInt16($bytes, $cursor);
                $cursor += 2;
                self::assertRange($bytes, $cursor, $extraLength, 'extra field data');
                $extraFieldData = substr($bytes, $cursor, $extraLength);
                $extraFields = self::extraFieldsFromData($extraFieldData, 'member extra field data');
                $cursor += $extraLength;
            }

            $filename = null;
            $filenameText = null;
            $filenameEncoding = null;
            if (($flags & self::FLAG_FILENAME) !== 0) {
                [$filename, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'filename');
                $filenameText = self::latin1ToUtf8($filename);
                $filenameEncoding = 'gzip-latin1';
            }

            $comment = null;
            $commentText = null;
            $commentEncoding = null;
            if (($flags & self::FLAG_COMMENT) !== 0) {
                [$comment, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'comment');
                $commentText = self::latin1ToUtf8($comment);
                $commentEncoding = 'gzip-latin1';
            }

            $headerCrc16 = null;
            $headerCrc16Hex = null;
            $headerCrcOffset = null;
            $headerCrcCoverageSize = null;
            if (($flags & self::FLAG_HEADER_CRC) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'header CRC16');
                $headerCrcOffset = $cursor - $memberStart;
                $headerCrcCoverageSize = $headerCrcOffset;
                $headerCrc16 = self::readUInt16($bytes, $cursor);
                $expectedHeaderCrc16 = self::unsignedCrc32(substr($bytes, $memberStart, $cursor - $memberStart)) & 0xffff;
                if ($headerCrc16 !== $expectedHeaderCrc16) {
                    throw new \RuntimeException('GZIP member header CRC16 does not match header bytes');
                }
                $headerCrc16Hex = sprintf('%04x', $headerCrc16);
                $cursor += 2;
            }

            $compressedStart = $cursor;
            $payload = self::inflateMemberPayload(substr($bytes, $compressedStart));
            $compressedSize = $payload['compressedSize'];
            $data = $payload['data'];
            if ($compressedSize <= 0) {
                throw new \RuntimeException('GZIP member contains no complete deflate payload');
            }

            $trailerOffset = $compressedStart + $compressedSize;
            self::assertRange($bytes, $trailerOffset, 8, 'member trailer');
            $crc32 = self::readUInt32($bytes, $trailerOffset);
            $uncompressedSize = self::readUInt32($bytes, $trailerOffset + 4);

            if (self::unsignedCrc32($data) !== $crc32) {
                throw new \RuntimeException('GZIP member CRC32 does not match inflated payload');
            }

            if ((strlen($data) & 0xffffffff) !== $uncompressedSize) {
                throw new \RuntimeException('GZIP member uncompressed size does not match trailer');
            }

            $decodedDataOffset = $totalUncompressedBytes;
            $decodedDataEndOffset = $decodedDataOffset + strlen($data);
            $totalUncompressedBytes = $decodedDataEndOffset;
            if ($maxUncompressedBytes !== null && $totalUncompressedBytes > $maxUncompressedBytes) {
                throw new \RuntimeException('GZIP stream exceeds the configured uncompressed byte limit');
            }

            $cursor = $trailerOffset + 8;
            $members[] = [
                'data' => $data,
                'modifiedAt' => $modifiedAt,
                'modifiedAtKnown' => $modifiedAt !== 0,
                'modifiedAtText' => self::modifiedAtText($modifiedAt),
                'flags' => $flags,
                'textHint' => $textHint,
                'extraFlags' => $extraFlags,
                'extraFlagsMeaning' => self::extraFlagsMeaning($extraFlags),
                'operatingSystem' => $operatingSystem,
                'operatingSystemName' => self::operatingSystemName($operatingSystem),
                'extraFieldData' => $extraFieldData,
                'extraFields' => $extraFields,
                'filename' => $filename,
                'filenameText' => $filenameText,
                'filenameEncoding' => $filenameEncoding,
                'comment' => $comment,
                'commentText' => $commentText,
                'commentEncoding' => $commentEncoding,
                'headerCrcPresent' => $headerCrc16 !== null,
                'headerCrc16' => $headerCrc16,
                'headerCrc16Hex' => $headerCrc16Hex,
                'headerCrcOffset' => $headerCrcOffset,
                'headerCrcCoverageSize' => $headerCrcCoverageSize,
                'crc32' => $crc32,
                'uncompressedSize' => $uncompressedSize,
                'compressedSize' => $compressedSize,
                'decodedDataOffset' => $decodedDataOffset,
                'decodedDataEndOffset' => $decodedDataEndOffset,
                'memberSize' => $cursor - $memberStart,
                'memberOffset' => $memberStart,
                'headerSize' => $compressedStart - $memberStart,
                'compressedDataOffset' => $compressedStart,
                'trailerOffset' => $trailerOffset,
                'nextMemberOffset' => $cursor,
            ];
        }

        return [
            'members' => $members,
            'memberCount' => count($members),
            'compressedSize' => $length,
            'uncompressedSize' => $totalUncompressedBytes,
            'trailingPaddingBytes' => $trailingPaddingBytes,
        ];
    }

    /**
     * @return array{
     *     members:list<array{
     *         memberIndex:int,
     *         modifiedAt:int,
     *         modifiedAtKnown:bool,
     *         modifiedAtText:?string,
     *         flags:int,
     *         textHint:bool,
     *         extraFlags:int,
     *         extraFlagsMeaning:string,
     *         operatingSystem:int,
     *         operatingSystemName:string,
     *         extraFieldData:?string,
     *         extraFields:list<array{identifier:string,id1:int,id2:int,length:int,data:string}>,
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         headerCrcPresent:bool,
     *         headerCrc16:?int,
     *         headerCrc16Hex:?string,
     *         headerCrcOffset:?int,
     *         headerCrcCoverageSize:?int,
     *         storedCrc32:int,
     *         storedCrc32Hex:string,
     *         computedCrc32:int,
     *         computedCrc32Hex:string,
     *         crc32Matches:bool,
     *         isize:int,
     *         decodedSize:int,
     *         isizeMatches:bool,
     *         compressedSize:int,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         memberSize:int,
     *         memberOffset:int,
     *         headerSize:int,
     *         compressedDataOffset:int,
     *         trailerOffset:int,
     *         nextMemberOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     failedMemberCount:int,
     *     crcMismatchMemberCount:int,
     *     isizeMismatchMemberCount:int,
     *     firstFailedMemberIndex:?int,
     *     trailingPaddingBytes:int,
     *     extractionPolicy:string
     * }
     */
    public static function trailerIntegrityPreflight(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('GZIP stream is empty');
        }

        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException('GZIP max uncompressed byte limit must not be negative');
        }

        $members = [];
        $cursor = 0;
        $totalUncompressedBytes = 0;
        $length = strlen($bytes);
        $trailingPaddingBytes = 0;
        $failedMemberCount = 0;
        $crcMismatchMemberCount = 0;
        $isizeMismatchMemberCount = 0;
        $firstFailedMemberIndex = null;

        while ($cursor < $length) {
            $memberStart = $cursor;
            if (!self::hasMemberHeaderAt($bytes, $cursor)) {
                $padding = substr($bytes, $cursor);
                if ($members !== [] && trim($padding, "\0") === '') {
                    $trailingPaddingBytes = strlen($padding);
                    $cursor = $length;
                    break;
                }

                throw new \RuntimeException('Invalid GZIP member header signature');
            }

            self::assertRange($bytes, $cursor, 10, 'member header');

            $method = ord($bytes[$cursor + 2]);
            if ($method !== self::COMPRESSION_METHOD_DEFLATE) {
                throw new \RuntimeException("Unsupported GZIP compression method {$method}");
            }

            $flags = ord($bytes[$cursor + 3]);
            if (($flags & self::FLAG_RESERVED) !== 0) {
                throw new \RuntimeException('GZIP member header uses reserved flag bits');
            }

            $modifiedAt = self::readUInt32($bytes, $cursor + 4);
            $textHint = ($flags & self::FLAG_TEXT) !== 0;
            $extraFlags = ord($bytes[$cursor + 8]);
            $operatingSystem = ord($bytes[$cursor + 9]);
            $cursor += 10;

            $extraFieldData = null;
            $extraFields = [];
            if (($flags & self::FLAG_EXTRA) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'extra field length');
                $extraLength = self::readUInt16($bytes, $cursor);
                $cursor += 2;
                self::assertRange($bytes, $cursor, $extraLength, 'extra field data');
                $extraFieldData = substr($bytes, $cursor, $extraLength);
                $extraFields = self::extraFieldsFromData($extraFieldData, 'member extra field data');
                $cursor += $extraLength;
            }

            $filename = null;
            $filenameText = null;
            $filenameEncoding = null;
            if (($flags & self::FLAG_FILENAME) !== 0) {
                [$filename, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'filename');
                $filenameText = self::latin1ToUtf8($filename);
                $filenameEncoding = 'gzip-latin1';
            }

            $comment = null;
            $commentText = null;
            $commentEncoding = null;
            if (($flags & self::FLAG_COMMENT) !== 0) {
                [$comment, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'comment');
                $commentText = self::latin1ToUtf8($comment);
                $commentEncoding = 'gzip-latin1';
            }

            $headerCrc16 = null;
            $headerCrc16Hex = null;
            $headerCrcOffset = null;
            $headerCrcCoverageSize = null;
            if (($flags & self::FLAG_HEADER_CRC) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'header CRC16');
                $headerCrcOffset = $cursor - $memberStart;
                $headerCrcCoverageSize = $headerCrcOffset;
                $headerCrc16 = self::readUInt16($bytes, $cursor);
                $expectedHeaderCrc16 = self::unsignedCrc32(substr($bytes, $memberStart, $cursor - $memberStart)) & 0xffff;
                if ($headerCrc16 !== $expectedHeaderCrc16) {
                    throw new \RuntimeException('GZIP member header CRC16 does not match header bytes');
                }
                $headerCrc16Hex = sprintf('%04x', $headerCrc16);
                $cursor += 2;
            }

            $compressedStart = $cursor;
            $payload = self::inflateMemberPayload(substr($bytes, $compressedStart));
            $compressedSize = $payload['compressedSize'];
            $data = $payload['data'];
            if ($compressedSize <= 0) {
                throw new \RuntimeException('GZIP member contains no complete deflate payload');
            }

            $trailerOffset = $compressedStart + $compressedSize;
            self::assertRange($bytes, $trailerOffset, 8, 'member trailer');
            $storedCrc32 = self::readUInt32($bytes, $trailerOffset);
            $isize = self::readUInt32($bytes, $trailerOffset + 4);
            $computedCrc32 = self::unsignedCrc32($data);
            $decodedSize = strlen($data);
            $crc32Matches = $storedCrc32 === $computedCrc32;
            $isizeMatches = $isize === ($decodedSize & 0xffffffff);

            $diagnostics = [];
            if (!$crc32Matches) {
                $diagnostics[] = 'gzip-member-crc32-mismatch';
                $crcMismatchMemberCount++;
            }

            if (!$isizeMatches) {
                $diagnostics[] = 'gzip-member-isize-mismatch';
                $isizeMismatchMemberCount++;
            }

            $memberIndex = count($members);
            if ($diagnostics !== []) {
                $failedMemberCount++;
                if ($firstFailedMemberIndex === null) {
                    $firstFailedMemberIndex = $memberIndex;
                }
            }

            $decodedDataOffset = $totalUncompressedBytes;
            $decodedDataEndOffset = $decodedDataOffset + $decodedSize;
            $totalUncompressedBytes = $decodedDataEndOffset;
            if ($maxUncompressedBytes !== null && $totalUncompressedBytes > $maxUncompressedBytes) {
                throw new \RuntimeException('GZIP stream exceeds the configured uncompressed byte limit');
            }

            $cursor = $trailerOffset + 8;
            $members[] = [
                'memberIndex' => $memberIndex,
                'modifiedAt' => $modifiedAt,
                'modifiedAtKnown' => $modifiedAt !== 0,
                'modifiedAtText' => self::modifiedAtText($modifiedAt),
                'flags' => $flags,
                'textHint' => $textHint,
                'extraFlags' => $extraFlags,
                'extraFlagsMeaning' => self::extraFlagsMeaning($extraFlags),
                'operatingSystem' => $operatingSystem,
                'operatingSystemName' => self::operatingSystemName($operatingSystem),
                'extraFieldData' => $extraFieldData,
                'extraFields' => $extraFields,
                'filename' => $filename,
                'filenameText' => $filenameText,
                'filenameEncoding' => $filenameEncoding,
                'comment' => $comment,
                'commentText' => $commentText,
                'commentEncoding' => $commentEncoding,
                'headerCrcPresent' => $headerCrc16 !== null,
                'headerCrc16' => $headerCrc16,
                'headerCrc16Hex' => $headerCrc16Hex,
                'headerCrcOffset' => $headerCrcOffset,
                'headerCrcCoverageSize' => $headerCrcCoverageSize,
                'storedCrc32' => $storedCrc32,
                'storedCrc32Hex' => sprintf('%08x', $storedCrc32),
                'computedCrc32' => $computedCrc32,
                'computedCrc32Hex' => sprintf('%08x', $computedCrc32),
                'crc32Matches' => $crc32Matches,
                'isize' => $isize,
                'decodedSize' => $decodedSize,
                'isizeMatches' => $isizeMatches,
                'compressedSize' => $compressedSize,
                'decodedDataOffset' => $decodedDataOffset,
                'decodedDataEndOffset' => $decodedDataEndOffset,
                'memberSize' => $cursor - $memberStart,
                'memberOffset' => $memberStart,
                'headerSize' => $compressedStart - $memberStart,
                'compressedDataOffset' => $compressedStart,
                'trailerOffset' => $trailerOffset,
                'nextMemberOffset' => $cursor,
                'policy' => $diagnostics === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $diagnostics,
            ];
        }

        return [
            'members' => $members,
            'memberCount' => count($members),
            'compressedSize' => $length,
            'uncompressedSize' => $totalUncompressedBytes,
            'failedMemberCount' => $failedMemberCount,
            'crcMismatchMemberCount' => $crcMismatchMemberCount,
            'isizeMismatchMemberCount' => $isizeMismatchMemberCount,
            'firstFailedMemberIndex' => $firstFailedMemberIndex,
            'trailingPaddingBytes' => $trailingPaddingBytes,
            'extractionPolicy' => $failedMemberCount === 0
                ? 'gzip-trailer-integrity-verified'
                : 'gzip-trailer-integrity-review',
        ];
    }

    /**
     * @return array{
     *     members:list<array{
     *         memberIndex:int,
     *         modifiedAt:int,
     *         modifiedAtKnown:bool,
     *         modifiedAtText:?string,
     *         flags:int,
     *         textHint:bool,
     *         extraFlags:int,
     *         extraFlagsMeaning:string,
     *         operatingSystem:int,
     *         operatingSystemName:string,
     *         extraFieldData:?string,
     *         extraFields:list<array{identifier:string,id1:int,id2:int,length:int,data:string}>,
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         headerCrcPresent:bool,
     *         headerCrcMatches:?bool,
     *         headerCrc16:?int,
     *         headerCrc16Hex:?string,
     *         expectedHeaderCrc16:?int,
     *         expectedHeaderCrc16Hex:?string,
     *         headerCrcOffset:?int,
     *         headerCrcCoverageSize:?int,
     *         crc32:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         memberSize:int,
     *         memberOffset:int,
     *         headerSize:int,
     *         compressedDataOffset:int,
     *         trailerOffset:int,
     *         nextMemberOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     headerCrcMemberCount:int,
     *     missingHeaderCrcMemberCount:int,
     *     mismatchedHeaderCrcMemberCount:int,
     *     firstMismatchedMemberIndex:?int,
     *     trailingPaddingBytes:int,
     *     extractionPolicy:string
     * }
     */
    public static function headerCrcPolicyPreflight(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('GZIP stream is empty');
        }

        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException('GZIP max uncompressed byte limit must not be negative');
        }

        $members = [];
        $cursor = 0;
        $totalUncompressedBytes = 0;
        $length = strlen($bytes);
        $trailingPaddingBytes = 0;
        $headerCrcMemberCount = 0;
        $missingHeaderCrcMemberCount = 0;
        $mismatchedHeaderCrcMemberCount = 0;
        $firstMismatchedMemberIndex = null;

        while ($cursor < $length) {
            $memberStart = $cursor;
            if (!self::hasMemberHeaderAt($bytes, $cursor)) {
                $padding = substr($bytes, $cursor);
                if ($members !== [] && trim($padding, "\0") === '') {
                    $trailingPaddingBytes = strlen($padding);
                    $cursor = $length;
                    break;
                }

                throw new \RuntimeException('Invalid GZIP member header signature');
            }

            self::assertRange($bytes, $cursor, 10, 'member header');

            $method = ord($bytes[$cursor + 2]);
            if ($method !== self::COMPRESSION_METHOD_DEFLATE) {
                throw new \RuntimeException("Unsupported GZIP compression method {$method}");
            }

            $flags = ord($bytes[$cursor + 3]);
            if (($flags & self::FLAG_RESERVED) !== 0) {
                throw new \RuntimeException('GZIP member header uses reserved flag bits');
            }

            $modifiedAt = self::readUInt32($bytes, $cursor + 4);
            $textHint = ($flags & self::FLAG_TEXT) !== 0;
            $extraFlags = ord($bytes[$cursor + 8]);
            $operatingSystem = ord($bytes[$cursor + 9]);
            $cursor += 10;

            $extraFieldData = null;
            $extraFields = [];
            if (($flags & self::FLAG_EXTRA) !== 0) {
                self::assertRange($bytes, $cursor, 2, 'extra field length');
                $extraLength = self::readUInt16($bytes, $cursor);
                $cursor += 2;
                self::assertRange($bytes, $cursor, $extraLength, 'extra field data');
                $extraFieldData = substr($bytes, $cursor, $extraLength);
                $extraFields = self::extraFieldsFromData($extraFieldData, 'member extra field data');
                $cursor += $extraLength;
            }

            $filename = null;
            $filenameText = null;
            $filenameEncoding = null;
            if (($flags & self::FLAG_FILENAME) !== 0) {
                [$filename, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'filename');
                $filenameText = self::latin1ToUtf8($filename);
                $filenameEncoding = 'gzip-latin1';
            }

            $comment = null;
            $commentText = null;
            $commentEncoding = null;
            if (($flags & self::FLAG_COMMENT) !== 0) {
                [$comment, $cursor] = self::readZeroTerminatedField($bytes, $cursor, 'comment');
                $commentText = self::latin1ToUtf8($comment);
                $commentEncoding = 'gzip-latin1';
            }

            $headerCrc16 = null;
            $headerCrc16Hex = null;
            $expectedHeaderCrc16 = null;
            $expectedHeaderCrc16Hex = null;
            $headerCrcOffset = null;
            $headerCrcCoverageSize = null;
            $headerCrcMatches = null;
            $diagnostics = [];
            if (($flags & self::FLAG_HEADER_CRC) !== 0) {
                $headerCrcMemberCount++;
                self::assertRange($bytes, $cursor, 2, 'header CRC16');
                $headerCrcOffset = $cursor - $memberStart;
                $headerCrcCoverageSize = $headerCrcOffset;
                $headerCrc16 = self::readUInt16($bytes, $cursor);
                $expectedHeaderCrc16 = self::unsignedCrc32(substr($bytes, $memberStart, $cursor - $memberStart)) & 0xffff;
                $headerCrcMatches = $headerCrc16 === $expectedHeaderCrc16;
                $headerCrc16Hex = sprintf('%04x', $headerCrc16);
                $expectedHeaderCrc16Hex = sprintf('%04x', $expectedHeaderCrc16);
                if (!$headerCrcMatches) {
                    $diagnostics[] = 'gzip-member-header-crc-mismatch';
                    $mismatchedHeaderCrcMemberCount++;
                    if ($firstMismatchedMemberIndex === null) {
                        $firstMismatchedMemberIndex = count($members);
                    }
                }
                $cursor += 2;
            } else {
                $missingHeaderCrcMemberCount++;
            }

            $compressedStart = $cursor;
            $payload = self::inflateMemberPayload(substr($bytes, $compressedStart));
            $compressedSize = $payload['compressedSize'];
            $data = $payload['data'];
            if ($compressedSize <= 0) {
                throw new \RuntimeException('GZIP member contains no complete deflate payload');
            }

            $trailerOffset = $compressedStart + $compressedSize;
            self::assertRange($bytes, $trailerOffset, 8, 'member trailer');
            $crc32 = self::readUInt32($bytes, $trailerOffset);
            $uncompressedSize = self::readUInt32($bytes, $trailerOffset + 4);

            if (self::unsignedCrc32($data) !== $crc32) {
                throw new \RuntimeException('GZIP member CRC32 does not match inflated payload');
            }

            if ((strlen($data) & 0xffffffff) !== $uncompressedSize) {
                throw new \RuntimeException('GZIP member uncompressed size does not match trailer');
            }

            $decodedDataOffset = $totalUncompressedBytes;
            $decodedDataEndOffset = $decodedDataOffset + strlen($data);
            $totalUncompressedBytes = $decodedDataEndOffset;
            if ($maxUncompressedBytes !== null && $totalUncompressedBytes > $maxUncompressedBytes) {
                throw new \RuntimeException('GZIP stream exceeds the configured uncompressed byte limit');
            }

            $cursor = $trailerOffset + 8;
            $members[] = [
                'memberIndex' => count($members),
                'modifiedAt' => $modifiedAt,
                'modifiedAtKnown' => $modifiedAt !== 0,
                'modifiedAtText' => self::modifiedAtText($modifiedAt),
                'flags' => $flags,
                'textHint' => $textHint,
                'extraFlags' => $extraFlags,
                'extraFlagsMeaning' => self::extraFlagsMeaning($extraFlags),
                'operatingSystem' => $operatingSystem,
                'operatingSystemName' => self::operatingSystemName($operatingSystem),
                'extraFieldData' => $extraFieldData,
                'extraFields' => $extraFields,
                'filename' => $filename,
                'filenameText' => $filenameText,
                'filenameEncoding' => $filenameEncoding,
                'comment' => $comment,
                'commentText' => $commentText,
                'commentEncoding' => $commentEncoding,
                'headerCrcPresent' => $headerCrc16 !== null,
                'headerCrcMatches' => $headerCrcMatches,
                'headerCrc16' => $headerCrc16,
                'headerCrc16Hex' => $headerCrc16Hex,
                'expectedHeaderCrc16' => $expectedHeaderCrc16,
                'expectedHeaderCrc16Hex' => $expectedHeaderCrc16Hex,
                'headerCrcOffset' => $headerCrcOffset,
                'headerCrcCoverageSize' => $headerCrcCoverageSize,
                'crc32' => $crc32,
                'uncompressedSize' => $uncompressedSize,
                'compressedSize' => $compressedSize,
                'decodedDataOffset' => $decodedDataOffset,
                'decodedDataEndOffset' => $decodedDataEndOffset,
                'memberSize' => $cursor - $memberStart,
                'memberOffset' => $memberStart,
                'headerSize' => $compressedStart - $memberStart,
                'compressedDataOffset' => $compressedStart,
                'trailerOffset' => $trailerOffset,
                'nextMemberOffset' => $cursor,
                'policy' => $diagnostics === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $diagnostics,
            ];
        }

        return [
            'members' => $members,
            'memberCount' => count($members),
            'compressedSize' => $length,
            'uncompressedSize' => $totalUncompressedBytes,
            'headerCrcMemberCount' => $headerCrcMemberCount,
            'missingHeaderCrcMemberCount' => $missingHeaderCrcMemberCount,
            'mismatchedHeaderCrcMemberCount' => $mismatchedHeaderCrcMemberCount,
            'firstMismatchedMemberIndex' => $firstMismatchedMemberIndex,
            'trailingPaddingBytes' => $trailingPaddingBytes,
            'extractionPolicy' => $mismatchedHeaderCrcMemberCount === 0
                ? 'gzip-header-crc-verified-or-absent'
                : 'gzip-header-crc-review',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function members(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        return self::inspect($bytes, $maxUncompressedBytes)['members'];
    }

    /**
     * @return list<array{identifier:string,id1:int,id2:int,length:int,data:string}>
     */
    public static function extraFieldsFromData(string $bytes, string $label = 'extra field data'): array
    {
        $fields = [];
        $seen = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            self::assertRange($bytes, $cursor, 4, "{$label} subfield header");
            $id1 = ord($bytes[$cursor]);
            $id2 = ord($bytes[$cursor + 1]);
            $identifier = $bytes[$cursor] . $bytes[$cursor + 1];
            if ($id1 === 0 || $id2 === 0) {
                throw new \RuntimeException('GZIP extra subfield identifiers must not contain NUL bytes');
            }

            $fieldLength = self::readUInt16($bytes, $cursor + 2);
            $cursor += 4;
            self::assertRange($bytes, $cursor, $fieldLength, "{$label} subfield {$identifier}");
            if (isset($seen[$identifier])) {
                throw new \RuntimeException("Duplicate GZIP extra subfield identifier: {$identifier}");
            }
            $seen[$identifier] = true;

            $fields[] = [
                'identifier' => $identifier,
                'id1' => $id1,
                'id2' => $id2,
                'length' => $fieldLength,
                'data' => substr($bytes, $cursor, $fieldLength),
            ];
            $cursor += $fieldLength;
        }

        return $fields;
    }

    private static function modifiedAtText(int $modifiedAt): ?string
    {
        if ($modifiedAt === 0) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $modifiedAt);
    }

    private static function extraFlagsMeaning(int $extraFlags): string
    {
        return match ($extraFlags) {
            0 => 'unspecified',
            2 => 'maximum-compression',
            4 => 'fastest-compression',
            default => 'unknown',
        };
    }

    private static function operatingSystemName(int $operatingSystem): string
    {
        return match ($operatingSystem) {
            0 => 'fat-filesystem',
            1 => 'amiga',
            2 => 'vms',
            3 => 'unix',
            4 => 'vm-cms',
            5 => 'atari-tos',
            6 => 'hpfs-filesystem',
            7 => 'macintosh',
            8 => 'z-system',
            9 => 'cp-m',
            10 => 'tops-20',
            11 => 'ntfs-filesystem',
            12 => 'qdos',
            13 => 'acorn-riscos',
            255 => 'unknown',
            default => 'reserved-or-unknown',
        };
    }

    /**
     * @return array{data:string, compressedSize:int}
     */
    private static function inflateMemberPayload(string $bytes): array
    {
        $context = inflate_init(ZLIB_ENCODING_RAW);
        if ($context === false) {
            throw new \RuntimeException('Unable to initialize raw DEFLATE decoder for GZIP member');
        }

        $data = @inflate_add($context, $bytes, ZLIB_FINISH);
        if ($data === false || inflate_get_status($context) !== ZLIB_STREAM_END) {
            throw new \RuntimeException('Unable to inflate GZIP member payload');
        }

        return [
            'data' => $data,
            'compressedSize' => inflate_get_read_len($context),
        ];
    }

    private static function hasMemberHeaderAt(string $bytes, int $offset): bool
    {
        return $offset + 2 <= strlen($bytes)
            && ord($bytes[$offset]) === self::ID1
            && ord($bytes[$offset + 1]) === self::ID2;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function readZeroTerminatedField(string $bytes, int $offset, string $label): array
    {
        $terminator = strpos($bytes, "\0", $offset);
        if ($terminator === false) {
            throw new \RuntimeException("GZIP {$label} field is missing a NUL terminator");
        }

        return [
            substr($bytes, $offset, $terminator - $offset),
            $terminator + 1,
        ];
    }

    private static function latin1ToUtf8(string $bytes): string
    {
        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            if ($byte < 0x80) {
                $text .= chr($byte);
                continue;
            }

            $text .= chr(0xc0 | ($byte >> 6)) . chr(0x80 | ($byte & 0x3f));
        }

        return $text;
    }

    private static function assertTerminatedStringInput(mixed $value, string $label): void
    {
        if (!is_string($value)) {
            throw new \RuntimeException("{$label} must be a string");
        }

        if (str_contains($value, "\0")) {
            throw new \RuntimeException("{$label} must not contain NUL bytes");
        }
    }

    private static function assertUInt8(mixed $value, string $label): void
    {
        if (!is_int($value) || $value < 0 || $value > 0xff) {
            throw new \RuntimeException("{$label} must fit in an unsigned 8-bit field");
        }
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("GZIP {$label} extends beyond available bytes");
        }
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 2, 'uint16');
        $values = unpack('vvalue', substr($bytes, $offset, 2));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read GZIP uint16 value');
        }

        return (int) $values['value'];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 4, 'uint32');
        $values = unpack('Vvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read GZIP uint32 value');
        }

        return (int) $values['value'];
    }

    private static function unsignedCrc32(string $bytes): int
    {
        return (int) sprintf('%u', crc32($bytes));
    }
}
