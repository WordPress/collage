<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DeflateStream
{
    public const FORMAT_ZLIB = 'zlib';
    public const FORMAT_RAW = 'raw';

    private const ZLIB_COMPRESSION_METHOD_DEFLATE = 8;
    private const ZLIB_PRESET_DICTIONARY_FLAG = 0x20;

    /**
     * @param array{format?:string, compressionLevel?:int} $options
     */
    public static function build(string $data, array $options = []): string
    {
        $format = self::normalizeFormat($options['format'] ?? self::FORMAT_ZLIB);
        $compressionLevel = $options['compressionLevel'] ?? -1;
        if (!is_int($compressionLevel) || $compressionLevel < -1 || $compressionLevel > 9) {
            throw new \RuntimeException('DEFLATE compression level must be between -1 and 9');
        }

        if ($format === self::FORMAT_RAW) {
            $encoded = gzdeflate($data, $compressionLevel);
            if ($encoded === false) {
                throw new \RuntimeException('Unable to encode raw DEFLATE stream');
            }

            return $encoded;
        }

        $encoded = zlib_encode($data, ZLIB_ENCODING_DEFLATE, $compressionLevel);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode zlib-wrapped DEFLATE stream');
        }

        return $encoded;
    }

    public static function decode(
        string $bytes,
        string $format = self::FORMAT_ZLIB,
        ?int $maxUncompressedBytes = null
    ): string {
        $format = self::normalizeFormat($format);
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');

        if ($format === self::FORMAT_ZLIB) {
            return self::inspectZlib($bytes, $maxUncompressedBytes)['data'];
        }

        return self::inspectRaw($bytes, $maxUncompressedBytes)['data'];
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeZlibWithDictionaries(
        string $bytes,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::inspectZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes)['data'];
    }

    /**
     * @return array{
     *     format:string,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     hasPresetDictionary:bool,
     *     uncompressedSize:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int,
     *     adler32:int,
     *     adler32Hex:string,
     *     storedAdler32:int,
     *     storedAdler32Hex:string,
     *     computedAdler32:int,
     *     computedAdler32Hex:string,
     *     adler32Matches:bool,
     *     extractionPolicy:string,
     *     diagnostics:list<string>
     * }
     */
    public static function adler32IntegrityPreflight(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('ZLIB stream is too short to contain a DEFLATE header and trailer');
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);
        if ((($cmf << 8) + $flg) % 31 !== 0) {
            throw new \RuntimeException('ZLIB stream header check bits do not match');
        }

        $compressionMethod = $cmf & 0x0f;
        if ($compressionMethod !== self::ZLIB_COMPRESSION_METHOD_DEFLATE) {
            throw new \RuntimeException("Unsupported ZLIB compression method {$compressionMethod}");
        }

        $windowCode = ($cmf >> 4) & 0x0f;
        if ($windowCode > 7) {
            throw new \RuntimeException('ZLIB DEFLATE window size is outside the supported range');
        }

        $hasPresetDictionary = ($flg & self::ZLIB_PRESET_DICTIONARY_FLAG) !== 0;
        if ($hasPresetDictionary) {
            throw new \RuntimeException('Preset-dictionary ZLIB streams require the preset dictionary policy preflight');
        }

        $headerSize = 2;
        $trailerSize = 4;
        $compressedPayloadSize = strlen($bytes) - $headerSize - $trailerSize;
        $compressedPayload = substr($bytes, $headerSize, $compressedPayloadSize);
        $inflated = self::inflateComplete($compressedPayload, ZLIB_ENCODING_RAW, 'ZLIB compressed payload');
        $data = $inflated['data'];
        self::assertDecodedSize($data, $maxUncompressedBytes, 'ZLIB stream');

        $storedAdler32 = self::readUInt32BE($bytes, strlen($bytes) - $trailerSize);
        $computedAdler32 = self::adler32($data);
        $adler32Matches = $storedAdler32 === $computedAdler32;

        return [
            'format' => self::FORMAT_ZLIB,
            'compressionMethod' => $compressionMethod,
            'windowSize' => 1 << ($windowCode + 8),
            'compressionLevelHint' => self::compressionLevelHint(($flg >> 6) & 0x03),
            'hasPresetDictionary' => false,
            'uncompressedSize' => strlen($data),
            'compressedSize' => $compressedPayloadSize,
            'compressedPayloadSize' => $compressedPayloadSize,
            'headerSize' => $headerSize,
            'compressedPayloadOffset' => $headerSize,
            'trailerOffset' => strlen($bytes) - $trailerSize,
            'trailerSize' => $trailerSize,
            'consumedBytes' => $headerSize + $inflated['consumedBytes'] + $trailerSize,
            'adler32' => $storedAdler32,
            'adler32Hex' => sprintf('%08x', $storedAdler32),
            'storedAdler32' => $storedAdler32,
            'storedAdler32Hex' => sprintf('%08x', $storedAdler32),
            'computedAdler32' => $computedAdler32,
            'computedAdler32Hex' => sprintf('%08x', $computedAdler32),
            'adler32Matches' => $adler32Matches,
            'extractionPolicy' => $adler32Matches ? 'metadata-only-no-extraction' : 'zlib-adler32-integrity-review',
            'diagnostics' => $adler32Matches ? [] : ['zlib-adler32-mismatch'],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     hasPresetDictionary:bool,
     *     presetDictionaryId:?int,
     *     presetDictionaryIdHex:?string,
     *     dictionaryStreamCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     adler32:int,
     *     adler32Hex:string,
     *     extractionPolicy:string,
     *     policy:string,
     *     diagnostics:list<string>
     * }
     */
    public static function presetDictionaryPolicyPreflight(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('ZLIB stream is too short to contain a DEFLATE header and trailer');
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);
        if ((($cmf << 8) + $flg) % 31 !== 0) {
            throw new \RuntimeException('ZLIB stream header check bits do not match');
        }

        $compressionMethod = $cmf & 0x0f;
        if ($compressionMethod !== self::ZLIB_COMPRESSION_METHOD_DEFLATE) {
            throw new \RuntimeException("Unsupported ZLIB compression method {$compressionMethod}");
        }

        $windowCode = ($cmf >> 4) & 0x0f;
        if ($windowCode > 7) {
            throw new \RuntimeException('ZLIB DEFLATE window size is outside the supported range');
        }

        $hasPresetDictionary = ($flg & self::ZLIB_PRESET_DICTIONARY_FLAG) !== 0;
        if ($hasPresetDictionary && strlen($bytes) < 10) {
            throw new \RuntimeException('ZLIB preset-dictionary stream is too short to contain a dictionary id and trailer');
        }

        $dictionaryId = $hasPresetDictionary ? self::readUInt32BE($bytes, 2) : null;
        $adler32 = self::readUInt32BE($bytes, strlen($bytes) - 4);

        return [
            'format' => self::FORMAT_ZLIB,
            'compressionMethod' => $compressionMethod,
            'windowSize' => 1 << ($windowCode + 8),
            'compressionLevelHint' => self::compressionLevelHint(($flg >> 6) & 0x03),
            'hasPresetDictionary' => $hasPresetDictionary,
            'presetDictionaryId' => $dictionaryId,
            'presetDictionaryIdHex' => $dictionaryId === null ? null : sprintf('%08x', $dictionaryId),
            'dictionaryStreamCount' => $hasPresetDictionary ? 1 : 0,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => strlen($bytes) - ($hasPresetDictionary ? 10 : 6),
            'adler32' => $adler32,
            'adler32Hex' => sprintf('%08x', $adler32),
            'extractionPolicy' => $hasPresetDictionary
                ? 'preset-dictionary-streams-blocked'
                : 'no-preset-dictionary-streams',
            'policy' => $hasPresetDictionary ? 'blocked' : 'decodable-without-preset-dictionary',
            'diagnostics' => $hasPresetDictionary
                ? ['zlib-preset-dictionary-stream-not-decoded', 'zlib-external-preset-dictionary-required']
                : [],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     data:string,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     hasPresetDictionary:bool,
     *     adler32:int,
     *     adler32Hex:string,
     *     uncompressedSize:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int
     * }
     */
    public static function inspectZlib(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('ZLIB stream is too short to contain a DEFLATE header and trailer');
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);
        if ((($cmf << 8) + $flg) % 31 !== 0) {
            throw new \RuntimeException('ZLIB stream header check bits do not match');
        }

        $compressionMethod = $cmf & 0x0f;
        if ($compressionMethod !== self::ZLIB_COMPRESSION_METHOD_DEFLATE) {
            throw new \RuntimeException("Unsupported ZLIB compression method {$compressionMethod}");
        }

        $windowCode = ($cmf >> 4) & 0x0f;
        if ($windowCode > 7) {
            throw new \RuntimeException('ZLIB DEFLATE window size is outside the supported range');
        }

        $hasPresetDictionary = ($flg & self::ZLIB_PRESET_DICTIONARY_FLAG) !== 0;
        if ($hasPresetDictionary) {
            throw new \RuntimeException('Preset-dictionary ZLIB streams are not supported by the pandoc archive reader');
        }

        $inflated = self::inflateComplete($bytes, ZLIB_ENCODING_DEFLATE, 'ZLIB stream');
        $data = $inflated['data'];

        $adler32 = self::readUInt32BE($bytes, strlen($bytes) - 4);
        if (self::adler32($data) !== $adler32) {
            throw new \RuntimeException('ZLIB stream Adler-32 trailer does not match decoded payload');
        }

        self::assertDecodedSize($data, $maxUncompressedBytes, 'ZLIB stream');
        $headerSize = 2;
        $trailerSize = 4;
        $compressedPayloadSize = strlen($bytes) - $headerSize - $trailerSize;

        return [
            'format' => self::FORMAT_ZLIB,
            'data' => $data,
            'compressionMethod' => $compressionMethod,
            'windowSize' => 1 << ($windowCode + 8),
            'compressionLevelHint' => self::compressionLevelHint(($flg >> 6) & 0x03),
            'hasPresetDictionary' => false,
            'adler32' => $adler32,
            'adler32Hex' => sprintf('%08x', $adler32),
            'uncompressedSize' => strlen($data),
            'compressedSize' => $compressedPayloadSize,
            'compressedPayloadSize' => $compressedPayloadSize,
            'headerSize' => $headerSize,
            'compressedPayloadOffset' => $headerSize,
            'trailerOffset' => strlen($bytes) - $trailerSize,
            'trailerSize' => $trailerSize,
            'consumedBytes' => $inflated['consumedBytes'],
        ];
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array{
     *     format:string,
     *     data:string,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     hasPresetDictionary:bool,
     *     presetDictionaryId:?int,
     *     presetDictionaryIdHex:?string,
     *     dictionarySupplied:bool,
     *     dictionarySize:?int,
     *     dictionaryAdler32:?int,
     *     dictionaryAdler32Hex:?string,
     *     adler32:int,
     *     adler32Hex:string,
     *     uncompressedSize:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int
     * }
     */
    public static function inspectZlibWithDictionaries(
        string $bytes,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('ZLIB stream is too short to contain a DEFLATE header and trailer');
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);
        if ((($cmf << 8) + $flg) % 31 !== 0) {
            throw new \RuntimeException('ZLIB stream header check bits do not match');
        }

        $compressionMethod = $cmf & 0x0f;
        if ($compressionMethod !== self::ZLIB_COMPRESSION_METHOD_DEFLATE) {
            throw new \RuntimeException("Unsupported ZLIB compression method {$compressionMethod}");
        }

        $windowCode = ($cmf >> 4) & 0x0f;
        if ($windowCode > 7) {
            throw new \RuntimeException('ZLIB DEFLATE window size is outside the supported range');
        }

        $hasPresetDictionary = ($flg & self::ZLIB_PRESET_DICTIONARY_FLAG) !== 0;
        if (!$hasPresetDictionary) {
            $metadata = self::inspectZlib($bytes, $maxUncompressedBytes);

            return $metadata + [
                'presetDictionaryId' => null,
                'presetDictionaryIdHex' => null,
                'dictionarySupplied' => false,
                'dictionarySize' => null,
                'dictionaryAdler32' => null,
                'dictionaryAdler32Hex' => null,
            ];
        }

        if (strlen($bytes) < 10) {
            throw new \RuntimeException('ZLIB preset-dictionary stream is too short to contain a dictionary id and trailer');
        }

        $dictionaryId = self::readUInt32BE($bytes, 2);
        $dictionaryMap = self::normalizeExternalDictionaries($dictionaries);
        if (!array_key_exists($dictionaryId, $dictionaryMap)) {
            throw new \RuntimeException(sprintf(
                'Missing ZLIB preset dictionary for dictionary id 0x%08x',
                $dictionaryId
            ));
        }

        $dictionary = $dictionaryMap[$dictionaryId];
        $dictionaryAdler32 = self::adler32($dictionary);
        if ($dictionaryAdler32 !== $dictionaryId) {
            throw new \RuntimeException(sprintf(
                'Supplied ZLIB preset dictionary Adler-32 0x%08x does not match stream dictionary id 0x%08x',
                $dictionaryAdler32,
                $dictionaryId
            ));
        }

        $inflated = self::inflateComplete(
            $bytes,
            ZLIB_ENCODING_DEFLATE,
            'ZLIB stream',
            ['dictionary' => $dictionary],
            6
        );
        $data = $inflated['data'];

        $adler32 = self::readUInt32BE($bytes, strlen($bytes) - 4);
        if (self::adler32($data) !== $adler32) {
            throw new \RuntimeException('ZLIB stream Adler-32 trailer does not match decoded payload');
        }

        self::assertDecodedSize($data, $maxUncompressedBytes, 'ZLIB stream');
        $headerSize = 6;
        $trailerSize = 4;
        $compressedPayloadSize = strlen($bytes) - $headerSize - $trailerSize;

        return [
            'format' => self::FORMAT_ZLIB,
            'data' => $data,
            'compressionMethod' => $compressionMethod,
            'windowSize' => 1 << ($windowCode + 8),
            'compressionLevelHint' => self::compressionLevelHint(($flg >> 6) & 0x03),
            'hasPresetDictionary' => true,
            'presetDictionaryId' => $dictionaryId,
            'presetDictionaryIdHex' => sprintf('%08x', $dictionaryId),
            'dictionarySupplied' => true,
            'dictionarySize' => strlen($dictionary),
            'dictionaryAdler32' => $dictionaryAdler32,
            'dictionaryAdler32Hex' => sprintf('%08x', $dictionaryAdler32),
            'adler32' => $adler32,
            'adler32Hex' => sprintf('%08x', $adler32),
            'uncompressedSize' => strlen($data),
            'compressedSize' => $compressedPayloadSize,
            'compressedPayloadSize' => $compressedPayloadSize,
            'headerSize' => $headerSize,
            'compressedPayloadOffset' => $headerSize,
            'trailerOffset' => strlen($bytes) - $trailerSize,
            'trailerSize' => $trailerSize,
            'consumedBytes' => $inflated['consumedBytes'],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     data:string,
     *     uncompressedSize:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:?int,
     *     trailerSize:int,
     *     consumedBytes:int
     * }
     */
    public static function inspectRaw(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');
        if ($bytes === '') {
            throw new \RuntimeException('Raw DEFLATE stream is empty');
        }

        $inflated = self::inflateComplete($bytes, ZLIB_ENCODING_RAW, 'raw DEFLATE stream');
        $data = $inflated['data'];
        self::assertDecodedSize($data, $maxUncompressedBytes, 'raw DEFLATE stream');

        return [
            'format' => self::FORMAT_RAW,
            'data' => $data,
            'uncompressedSize' => strlen($data),
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => strlen($bytes),
            'headerSize' => 0,
            'compressedPayloadOffset' => 0,
            'trailerOffset' => null,
            'trailerSize' => 0,
            'consumedBytes' => $inflated['consumedBytes'],
        ];
    }

    private static function normalizeFormat(mixed $format): string
    {
        if ($format !== self::FORMAT_ZLIB && $format !== self::FORMAT_RAW) {
            throw new \RuntimeException('DEFLATE format must be either zlib or raw');
        }

        return $format;
    }

    private static function compressionLevelHint(int $flag): string
    {
        return match ($flag) {
            0 => 'fastest',
            1 => 'fast',
            2 => 'default',
            3 => 'maximum',
        };
    }

    /**
     * @return array{data:string, consumedBytes:int}
     */
    private static function inflateComplete(
        string $bytes,
        int $encoding,
        string $label,
        array $options = [],
        int $headerPrefixBytes = 0
    ): array
    {
        if ($headerPrefixBytes < 0) {
            throw new \RuntimeException("{$label} header prefix byte count must not be negative");
        }

        $context = inflate_init($encoding, $options);
        if ($context === false) {
            throw new \RuntimeException("Unable to initialize decoder for {$label}");
        }

        $data = @inflate_add($context, $bytes, ZLIB_FINISH);
        if ($data === false || inflate_get_status($context) !== ZLIB_STREAM_END) {
            throw new \RuntimeException("Unable to decode {$label}");
        }

        $consumedBytes = inflate_get_read_len($context) + $headerPrefixBytes;
        if ($consumedBytes !== strlen($bytes)) {
            throw new \RuntimeException("{$label} contains trailing bytes after the complete DEFLATE payload");
        }

        return [
            'data' => $data,
            'consumedBytes' => $consumedBytes,
        ];
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<int, string>
     */
    private static function normalizeExternalDictionaries(array $dictionaries): array
    {
        $normalized = [];
        foreach ($dictionaries as $id => $dictionary) {
            if (!is_string($dictionary)) {
                throw new \RuntimeException('ZLIB preset dictionaries must be byte strings');
            }

            if ($dictionary === '') {
                throw new \RuntimeException('ZLIB preset dictionaries must not be empty');
            }

            if (is_int($id)) {
                $dictionaryId = $id;
            } elseif (is_string($id) && preg_match('/^(?:0|[1-9][0-9]*)$/', $id) === 1) {
                $dictionaryId = (int) $id;
            } else {
                throw new \RuntimeException('ZLIB preset dictionary ids must be unsigned 32-bit integers');
            }

            if ($dictionaryId < 0 || $dictionaryId > 0xffffffff) {
                throw new \RuntimeException('ZLIB preset dictionary ids must be unsigned 32-bit integers');
            }

            $normalized[$dictionaryId] = $dictionary;
        }

        return $normalized;
    }

    private static function readUInt32BE(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('ZLIB uint32 field extends beyond available bytes');
        }

        $values = unpack('Nvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read ZLIB uint32 value');
        }

        return (int) $values['value'];
    }

    private static function adler32(string $bytes): int
    {
        if (!in_array('adler32', hash_algos(), true)) {
            throw new \RuntimeException('ZLIB stream checksums require PHP hash algorithm adler32');
        }

        return intval(hash('adler32', $bytes), 16);
    }

    private static function assertLimit(?int $maxUncompressedBytes, string $label): void
    {
        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }

    private static function assertDecodedSize(string $data, ?int $maxUncompressedBytes, string $label): void
    {
        if ($maxUncompressedBytes !== null && strlen($data) > $maxUncompressedBytes) {
            throw new \RuntimeException("{$label} exceeds the configured uncompressed byte limit");
        }
    }
}
