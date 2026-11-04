<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ArchiveCompressionStream
{
    public const FORMAT_TAR = 'tar';
    public const FORMAT_GZIP_TAR = 'gzip-tar';
    public const FORMAT_ZLIB_TAR = 'zlib-tar';
    public const FORMAT_RAW_DEFLATE_TAR = 'raw-deflate-tar';
    public const FORMAT_LZ4_TAR = 'lz4-tar';
    public const FORMAT_ZIP = 'zip';
    public const FORMAT_GZIP_ZIP = 'gzip-zip';
    public const FORMAT_ZLIB_ZIP = 'zlib-zip';
    public const FORMAT_RAW_DEFLATE_ZIP = 'raw-deflate-zip';
    public const FORMAT_LZ4_ZIP = 'lz4-zip';
    public const PACKAGE_KIND_TAR = 'tar';
    public const PACKAGE_KIND_ZIP = 'zip';

    /**
     * @return list<string>
     */
    public static function supportedTarFormats(): array
    {
        return [
            self::FORMAT_TAR,
            self::FORMAT_GZIP_TAR,
            self::FORMAT_ZLIB_TAR,
            self::FORMAT_RAW_DEFLATE_TAR,
            self::FORMAT_LZ4_TAR,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedZipFormats(): array
    {
        return [
            self::FORMAT_ZIP,
            self::FORMAT_GZIP_ZIP,
            self::FORMAT_ZLIB_ZIP,
            self::FORMAT_RAW_DEFLATE_ZIP,
            self::FORMAT_LZ4_ZIP,
        ];
    }

    public static function openTar(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);

        return TarArchive::fromString($tarBytes, $maxUnpackedBytes);
    }

    public static function openTarAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['archive'];
    }

    public static function detectTarFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['format'];
    }

    public static function decodeTarBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['tarBytes'];
    }

    public static function openZip(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return ZipPackage::fromString(self::decodeZipBytes($bytes, $format, $maxUncompressedBytes));
    }

    public static function openZipAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['package'];
    }

    public static function detectZipFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['format'];
    }

    public static function decodeZipBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['zipBytes'];
    }

    public static function detectPackageKindAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['kind'];
    }

    /**
     * @return array{
     *     rootKind:string,
     *     rootFormat:string,
     *     rootEntryCount:int,
     *     maxDepth:int,
     *     policy:string,
     *     candidateCount:int,
     *     packageCount:int,
     *     unsupportedCompressionCount:int,
     *     diagnosticCount:int,
     *     depthLimitReachedCount:int,
     *     depthLimitedCandidateCount:int,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function inspectNestedPackageStreamsAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        int $maxDepth = 1
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($maxDepth < 0) {
            throw new \RuntimeException('Nested archive inspection max depth must not be negative');
        }

        $root = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $entries = [];
        if ($maxDepth > 0) {
            self::collectNestedPackageEntries(
                $root,
                '',
                1,
                $maxDepth,
                $maxUncompressedBytes,
                $maxUnpackedBytes,
                $entries
            );
        }

        $packageCount = 0;
        $unsupportedCompressionCount = 0;
        $diagnosticCount = 0;
        $depthLimitReachedCount = 0;
        $depthLimitedCandidateCount = 0;
        foreach ($entries as $entry) {
            if (($entry['status'] ?? null) === 'package') {
                $packageCount++;
            }

            if (($entry['status'] ?? null) === 'unsupported-compression') {
                $unsupportedCompressionCount++;
            }

            if (($entry['diagnostics'] ?? []) !== []) {
                $diagnosticCount++;
            }

            if (($entry['depthLimitReached'] ?? false) === true) {
                $depthLimitReachedCount++;
            }

            $depthLimitedCandidateCount += (int) ($entry['depthLimitedCandidateCount'] ?? 0);
        }

        return [
            'rootKind' => $root['kind'],
            'rootFormat' => $root['format'],
            'rootEntryCount' => count(self::candidateEntryNames($root)),
            'maxDepth' => $maxDepth,
            'policy' => 'metadata-only-no-extraction',
            'candidateCount' => count($entries),
            'packageCount' => $packageCount,
            'unsupportedCompressionCount' => $unsupportedCompressionCount,
            'diagnosticCount' => $diagnosticCount,
            'depthLimitReachedCount' => $depthLimitReachedCount,
            'depthLimitedCandidateCount' => $depthLimitedCandidateCount,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        if ($candidate['kind'] === self::PACKAGE_KIND_TAR) {
            return [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::tarStreamInspection(
                $bytes,
                $candidate['format'],
                $candidate['tarBytes'],
                $candidate['archive'],
                $maxUncompressedBytes
            );
        }

        return [
            'kind' => self::PACKAGE_KIND_ZIP,
        ] + self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    /**
     * @return array{
     *     type:string,
     *     sourceName:string,
     *     sourceNameCandidate:bool,
     *     sourceNameReason:?string,
     *     expectedKind:?string,
     *     expectedFormat:?string,
     *     detectedKind:string,
     *     detectedFormat:string,
     *     compressedSize:int,
     *     decodedPackageSize:?int,
     *     entryCount:int,
     *     entryNames:list<string>,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectPackageSourceNamePolicyAuto(
        string $bytes,
        string $sourceName,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($sourceName === '') {
            throw new \RuntimeException('Archive package source-name policy requires a non-empty source name');
        }

        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $nameCandidate = self::supportedPackageSourceNameCandidate($sourceName);
        $entryNames = self::candidateEntryNames($candidate);
        $diagnostics = [];

        if ($nameCandidate === null) {
            $diagnostics[] = 'archive-source-name-package-type-unknown';
        } else {
            if ($nameCandidate['kind'] !== $candidate['kind']) {
                $diagnostics[] = 'archive-source-name-package-kind-mismatch';
            }

            if ($nameCandidate['format'] !== $candidate['format']) {
                $diagnostics[] = 'archive-source-name-compression-format-mismatch';
            }
        }

        return [
            'type' => 'archive-package-source-name-policy',
            'sourceName' => $sourceName,
            'sourceNameCandidate' => $nameCandidate !== null,
            'sourceNameReason' => $nameCandidate['reason'] ?? null,
            'expectedKind' => $nameCandidate['kind'] ?? null,
            'expectedFormat' => $nameCandidate['format'] ?? null,
            'detectedKind' => $candidate['kind'],
            'detectedFormat' => $candidate['format'],
            'compressedSize' => strlen($bytes),
            'decodedPackageSize' => self::candidatePackageByteSize($candidate),
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'stream' => self::streamInspection($bytes, $candidate['format'], $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     maxMemberCount:int,
     *     overLimitMemberCount:int,
     *     firstOverLimitMemberIndex:?int,
     *     trailingPaddingBytes:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array{
     *         memberIndex:int,
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         memberOffset:int,
     *         memberSize:int,
     *         nextMemberOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>
     * }
     */
    public static function inspectGzipMemberCountPolicy(
        string $bytes,
        string $format,
        int $maxMemberCount,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP member-count policy requires a GZIP archive stream format: {$format}");
        }

        if ($maxMemberCount <= 0) {
            throw new \RuntimeException('GZIP member-count policy threshold must be positive');
        }

        $stream = self::gzipStreamInspection($bytes, $maxUncompressedBytes);
        $members = [];
        $overLimitMemberCount = 0;
        $firstOverLimitMemberIndex = null;

        foreach ($stream['members'] as $index => $member) {
            $overLimit = $index >= $maxMemberCount;
            $memberDiagnostics = [];
            if ($overLimit) {
                $overLimitMemberCount++;
                $memberDiagnostics[] = 'gzip-member-over-limit';
                if ($firstOverLimitMemberIndex === null) {
                    $firstOverLimitMemberIndex = $index;
                }
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'memberSize' => $member['memberSize'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'policy' => $overLimit ? 'review-before-conversion' : 'metadata',
                'diagnostics' => $memberDiagnostics,
            ];
        }

        $diagnostics = $overLimitMemberCount > 0 ? ['gzip-member-count-exceeds-threshold'] : [];

        return [
            'type' => 'archive-gzip-member-count-policy',
            'format' => $format,
            'compressedSize' => $stream['compressedSize'],
            'uncompressedSize' => $stream['uncompressedSize'],
            'memberCount' => $stream['memberCount'],
            'maxMemberCount' => $maxMemberCount,
            'overLimitMemberCount' => $overLimitMemberCount,
            'firstOverLimitMemberIndex' => $firstOverLimitMemberIndex,
            'trailingPaddingBytes' => $stream['trailingPaddingBytes'],
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'gzip-member-count-review',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     maxMemberUncompressedBytes:int,
     *     overLimitMemberCount:int,
     *     firstOverLimitMemberIndex:?int,
     *     largestMemberUncompressedSize:int,
     *     trailingPaddingBytes:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array{
     *         memberIndex:int,
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         memberOffset:int,
     *         memberSize:int,
     *         nextMemberOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>
     * }
     */
    public static function inspectGzipMemberByteLimitPolicy(
        string $bytes,
        string $format,
        int $maxMemberUncompressedBytes,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP member byte-limit policy requires a GZIP archive stream format: {$format}");
        }

        if ($maxMemberUncompressedBytes <= 0) {
            throw new \RuntimeException('GZIP member byte-limit policy threshold must be positive');
        }

        $stream = self::gzipStreamInspection($bytes, $maxUncompressedBytes);
        $members = [];
        $overLimitMemberCount = 0;
        $firstOverLimitMemberIndex = null;
        $largestMemberUncompressedSize = 0;

        foreach ($stream['members'] as $index => $member) {
            $memberUncompressedSize = $member['uncompressedSize'];
            $largestMemberUncompressedSize = max($largestMemberUncompressedSize, $memberUncompressedSize);
            $overLimit = $memberUncompressedSize > $maxMemberUncompressedBytes;
            $memberDiagnostics = [];
            if ($overLimit) {
                $overLimitMemberCount++;
                $memberDiagnostics[] = 'gzip-member-byte-limit-over-limit';
                if ($firstOverLimitMemberIndex === null) {
                    $firstOverLimitMemberIndex = $index;
                }
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'uncompressedSize' => $memberUncompressedSize,
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'memberSize' => $member['memberSize'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'policy' => $overLimit ? 'review-before-conversion' : 'metadata',
                'diagnostics' => $memberDiagnostics,
            ];
        }

        $diagnostics = $overLimitMemberCount > 0 ? ['gzip-member-byte-limit-exceeds-threshold'] : [];

        return [
            'type' => 'archive-gzip-member-byte-limit-policy',
            'format' => $format,
            'compressedSize' => $stream['compressedSize'],
            'uncompressedSize' => $stream['uncompressedSize'],
            'memberCount' => $stream['memberCount'],
            'maxMemberUncompressedBytes' => $maxMemberUncompressedBytes,
            'overLimitMemberCount' => $overLimitMemberCount,
            'firstOverLimitMemberIndex' => $firstOverLimitMemberIndex,
            'largestMemberUncompressedSize' => $largestMemberUncompressedSize,
            'trailingPaddingBytes' => $stream['trailingPaddingBytes'],
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'gzip-member-byte-limit-review',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     failedMemberCount:int,
     *     crcMismatchMemberCount:int,
     *     isizeMismatchMemberCount:int,
     *     firstFailedMemberIndex:?int,
     *     trailingPaddingBytes:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectGzipTrailerIntegrityPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP trailer-integrity policy requires a GZIP archive stream format: {$format}");
        }

        $policy = GzipStream::trailerIntegrityPreflight($bytes, $maxUncompressedBytes);
        $diagnostics = [];
        if ($policy['failedMemberCount'] > 0) {
            $diagnostics[] = 'gzip-member-trailer-integrity-failed';
        }

        if ($policy['crcMismatchMemberCount'] > 0) {
            $diagnostics[] = 'gzip-member-crc32-mismatch';
        }

        if ($policy['isizeMismatchMemberCount'] > 0) {
            $diagnostics[] = 'gzip-member-isize-mismatch';
        }

        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'type' => 'archive-gzip-trailer-integrity-policy',
            'format' => $format,
            'compressedSize' => $policy['compressedSize'],
            'uncompressedSize' => $policy['uncompressedSize'],
            'memberCount' => $policy['memberCount'],
            'failedMemberCount' => $policy['failedMemberCount'],
            'crcMismatchMemberCount' => $policy['crcMismatchMemberCount'],
            'isizeMismatchMemberCount' => $policy['isizeMismatchMemberCount'],
            'firstFailedMemberIndex' => $policy['firstFailedMemberIndex'],
            'trailingPaddingBytes' => $policy['trailingPaddingBytes'],
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'gzip-trailer-integrity-review',
            'diagnostics' => $diagnostics,
            'members' => $policy['members'],
            'stream' => [
                'type' => 'gzip',
                'memberCount' => $policy['memberCount'],
                'compressedSize' => $policy['compressedSize'],
                'uncompressedSize' => $policy['uncompressedSize'],
                'failedMemberCount' => $policy['failedMemberCount'],
                'crcMismatchMemberCount' => $policy['crcMismatchMemberCount'],
                'isizeMismatchMemberCount' => $policy['isizeMismatchMemberCount'],
                'firstFailedMemberIndex' => $policy['firstFailedMemberIndex'],
                'trailingPaddingBytes' => $policy['trailingPaddingBytes'],
                'extractionPolicy' => $policy['extractionPolicy'],
                'members' => $policy['members'],
            ],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     headerCrcMemberCount:int,
     *     missingHeaderCrcMemberCount:int,
     *     mismatchedHeaderCrcMemberCount:int,
     *     firstMismatchedMemberIndex:?int,
     *     trailingPaddingBytes:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectGzipHeaderCrcPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP header-CRC policy requires a GZIP archive stream format: {$format}");
        }

        $policy = GzipStream::headerCrcPolicyPreflight($bytes, $maxUncompressedBytes);
        $diagnostics = $policy['mismatchedHeaderCrcMemberCount'] > 0
            ? ['gzip-member-header-crc-mismatch']
            : [];
        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'type' => 'archive-gzip-header-crc-policy',
            'format' => $format,
            'compressedSize' => $policy['compressedSize'],
            'uncompressedSize' => $policy['uncompressedSize'],
            'memberCount' => $policy['memberCount'],
            'headerCrcMemberCount' => $policy['headerCrcMemberCount'],
            'missingHeaderCrcMemberCount' => $policy['missingHeaderCrcMemberCount'],
            'mismatchedHeaderCrcMemberCount' => $policy['mismatchedHeaderCrcMemberCount'],
            'firstMismatchedMemberIndex' => $policy['firstMismatchedMemberIndex'],
            'trailingPaddingBytes' => $policy['trailingPaddingBytes'],
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'gzip-header-crc-review',
            'diagnostics' => $diagnostics,
            'members' => $policy['members'],
            'stream' => [
                'type' => 'gzip',
                'memberCount' => $policy['memberCount'],
                'compressedSize' => $policy['compressedSize'],
                'uncompressedSize' => $policy['uncompressedSize'],
                'headerCrcMemberCount' => $policy['headerCrcMemberCount'],
                'missingHeaderCrcMemberCount' => $policy['missingHeaderCrcMemberCount'],
                'mismatchedHeaderCrcMemberCount' => $policy['mismatchedHeaderCrcMemberCount'],
                'firstMismatchedMemberIndex' => $policy['firstMismatchedMemberIndex'],
                'trailingPaddingBytes' => $policy['trailingPaddingBytes'],
                'extractionPolicy' => $policy['extractionPolicy'],
                'members' => $policy['members'],
            ],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     kind:string,
     *     format:string,
     *     decodedFormat:string,
     *     compressedSize:int,
     *     decodedPackageSize:?int,
     *     entryCount:int,
     *     entryNames:list<string>,
     *     memberCount:int,
     *     memberFilenameCandidateCount:int,
     *     missingMemberFilenameCount:int,
     *     mismatchedMemberCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectGzipMemberSourceNamePolicyAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        if ($candidate['format'] !== self::FORMAT_GZIP_TAR && $candidate['format'] !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException('Archive gzip member source-name policy requires a gzip-compressed package stream');
        }

        $stream = self::streamInspection($bytes, $candidate['format'], $maxUncompressedBytes);
        $decodedFormat = self::gzipDecodedPackageFormat($candidate['format']);
        $entryNames = self::candidateEntryNames($candidate);
        $diagnostics = [];
        $members = [];
        $memberFilenameCandidateCount = 0;
        $missingMemberFilenameCount = 0;
        $mismatchedMemberCount = 0;

        foreach ($stream['members'] as $index => $member) {
            if (!is_array($member)) {
                continue;
            }

            $filename = is_string($member['filenameText'] ?? null)
                ? $member['filenameText']
                : (is_string($member['filename'] ?? null) ? $member['filename'] : null);
            $memberCandidate = null;
            $memberDiagnostics = [];

            if ($filename === null || $filename === '') {
                $missingMemberFilenameCount++;
                $memberDiagnostics[] = 'archive-gzip-member-source-name-missing';
            } else {
                $memberCandidate = self::supportedPackageSourceNameCandidate($filename);
                if ($memberCandidate === null) {
                    $memberDiagnostics[] = 'archive-gzip-member-source-name-package-type-unknown';
                } else {
                    $memberFilenameCandidateCount++;
                    if ($memberCandidate['kind'] !== $candidate['kind']) {
                        $memberDiagnostics[] = 'archive-gzip-member-source-name-package-kind-mismatch';
                    }

                    if ($memberCandidate['format'] !== $decodedFormat) {
                        $memberDiagnostics[] = 'archive-gzip-member-source-name-compression-format-mismatch';
                    }
                }
            }

            if ($memberDiagnostics !== []) {
                $mismatchedMemberCount++;
                $diagnostics = array_merge($diagnostics, $memberDiagnostics);
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'] ?? null,
                'filenameText' => $member['filenameText'] ?? null,
                'filenameEncoding' => $member['filenameEncoding'] ?? null,
                'memberFilenameCandidate' => $memberCandidate !== null,
                'memberNameReason' => $memberCandidate['reason'] ?? null,
                'expectedKind' => $memberCandidate['kind'] ?? null,
                'expectedDecodedFormat' => $memberCandidate['format'] ?? null,
                'detectedKind' => $candidate['kind'],
                'detectedDecodedFormat' => $decodedFormat,
                'policy' => $memberDiagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
                'diagnostics' => $memberDiagnostics,
                'memberOffset' => $member['memberOffset'] ?? null,
                'nextMemberOffset' => $member['nextMemberOffset'] ?? null,
            ];
        }

        $diagnostics = array_values(array_unique($diagnostics));

        return [
            'type' => 'archive-gzip-member-source-name-policy',
            'kind' => $candidate['kind'],
            'format' => $candidate['format'],
            'decodedFormat' => $decodedFormat,
            'compressedSize' => strlen($bytes),
            'decodedPackageSize' => self::candidatePackageByteSize($candidate),
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'memberCount' => count($members),
            'memberFilenameCandidateCount' => $memberFilenameCandidateCount,
            'missingMemberFilenameCount' => $missingMemberFilenameCount,
            'mismatchedMemberCount' => $mismatchedMemberCount,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'members' => $members,
            'stream' => $stream,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     kind:string,
     *     format:string,
     *     compressedSize:int,
     *     decodedPackageSize:int,
     *     chunkSize:int,
     *     chunkCount:int,
     *     entryCount:int,
     *     entryNames:list<string>,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     chunks:list<array{
     *         chunkIndex:int,
     *         decodedOffset:int,
     *         decodedEndOffset:int,
     *         decodedSize:int,
     *         sourceSegmentCount:int,
     *         crossesSourceBoundary:bool,
     *         policy:string,
     *         sourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             chunkOffset:int,
     *             chunkEndOffset:int
     *         }>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectDecodedPackageChunksAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        int $chunkSize = 1048576
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($chunkSize <= 0) {
            throw new \RuntimeException('Archive decoded package chunk size must be positive');
        }

        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $decodedPackageSize = self::candidatePackageByteSize($candidate);
        if ($decodedPackageSize === null) {
            throw new \RuntimeException('Detected archive package candidate is missing decoded package bytes');
        }

        $stream = self::streamInspection($bytes, $candidate['format'], $maxUncompressedBytes);
        $sourceSegments = self::decodedStreamSourceSegments($stream, $decodedPackageSize);
        $entryNames = self::candidateEntryNames($candidate);

        return [
            'type' => 'archive-decoded-package-chunk-policy',
            'kind' => $candidate['kind'],
            'format' => $candidate['format'],
            'compressedSize' => strlen($bytes),
            'decodedPackageSize' => $decodedPackageSize,
            'chunkSize' => $chunkSize,
            'chunkCount' => (int) ceil($decodedPackageSize / $chunkSize),
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'handoffPolicy' => 'within-thresholds',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'chunks' => self::decodedPackageChunks($decodedPackageSize, $chunkSize, $sourceSegments),
            'stream' => $stream,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     expectedKind:string,
     *     format:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     decodedSize:int,
     *     combinedPackageStatus:string,
     *     combinedPackageError:?string,
     *     combinedEntryCount:int,
     *     combinedEntryNames:list<string>,
     *     standalonePackageMemberCount:int,
     *     policy:string,
     *     diagnostics:list<string>,
     *     members:list<array{
     *         memberIndex:int,
     *         filename:?string,
     *         filenameText:?string,
     *         comment:?string,
     *         commentText:?string,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         decodedSize:int,
     *         compressedSize:int,
     *         memberOffset:int,
     *         memberSize:int,
     *         standalonePackage:bool,
     *         kind:?string,
     *         format:?string,
     *         entryCount:int,
     *         entryNames:list<string>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>
     * }
     */
    public static function inspectGzipMemberPackageBoundaryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $expectedKind = match ($format) {
            self::FORMAT_GZIP_TAR => self::PACKAGE_KIND_TAR,
            self::FORMAT_GZIP_ZIP => self::PACKAGE_KIND_ZIP,
            default => throw new \RuntimeException("GZIP member package-boundary policy requires a GZIP archive stream format: {$format}"),
        };

        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $decodedBytes = '';
        foreach ($inspection['members'] as $member) {
            $decodedBytes .= $member['data'];
        }

        $combined = self::gzipMemberPackageSummary($decodedBytes, $expectedKind, $maxUnpackedBytes);
        $members = [];
        $standalonePackageMemberCount = 0;

        foreach ($inspection['members'] as $index => $member) {
            $summary = self::gzipMemberPackageSummary($member['data'], $expectedKind, $maxUnpackedBytes);
            $standalonePackage = $summary['status'] === 'package';
            if ($standalonePackage) {
                $standalonePackageMemberCount++;
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'decodedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'memberSize' => $member['memberSize'],
                'standalonePackage' => $standalonePackage,
                'kind' => $standalonePackage ? $expectedKind : null,
                'format' => $standalonePackage
                    ? ($expectedKind === self::PACKAGE_KIND_TAR ? self::FORMAT_TAR : self::FORMAT_ZIP)
                    : null,
                'entryCount' => $summary['entryCount'],
                'entryNames' => $summary['entryNames'],
                'policy' => $standalonePackage ? 'standalone-gzip-member-package' : 'package-segment',
                'diagnostics' => $standalonePackage ? ['gzip-member-is-standalone-package'] : [],
            ];
        }

        $diagnostics = [];
        if ($combined['status'] !== 'package') {
            $diagnostics[] = 'gzip-combined-package-decode-failed';
        }

        if ($inspection['memberCount'] > 1 && $standalonePackageMemberCount > 0) {
            $diagnostics[] = 'gzip-members-contain-standalone-packages';
        }

        if ($standalonePackageMemberCount > 1) {
            $diagnostics[] = 'gzip-multiple-standalone-package-members';
        }

        return [
            'type' => 'archive-gzip-member-package-boundary-policy',
            'expectedKind' => $expectedKind,
            'format' => $format,
            'memberCount' => $inspection['memberCount'],
            'compressedSize' => strlen($bytes),
            'decodedSize' => $inspection['uncompressedSize'],
            'combinedPackageStatus' => $combined['status'],
            'combinedPackageError' => $combined['error'],
            'combinedEntryCount' => $combined['entryCount'],
            'combinedEntryNames' => $combined['entryNames'],
            'standalonePackageMemberCount' => $standalonePackageMemberCount,
            'policy' => $diagnostics === []
                ? ($inspection['memberCount'] === 1 ? 'single-gzip-member-package-stream' : 'single-decoded-package-stream')
                : 'review-before-conversion',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     expectedKind:string,
     *     format:string,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     compressedSize:int,
     *     decodedSize:int,
     *     combinedPackageStatus:string,
     *     combinedPackageError:?string,
     *     combinedEntryCount:int,
     *     combinedEntryNames:list<string>,
     *     standalonePackageFrameCount:int,
     *     policy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     frames:list<array<string, mixed>>
     * }
     */
    public static function inspectLz4FrameSourceBoundaryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $expectedKind = match ($format) {
            self::FORMAT_LZ4_TAR => self::PACKAGE_KIND_TAR,
            self::FORMAT_LZ4_ZIP => self::PACKAGE_KIND_ZIP,
            default => throw new \RuntimeException("LZ4 frame source-boundary policy requires an LZ4 archive stream format: {$format}"),
        };

        $rawFrames = Lz4Frame::frames($bytes, $maxUncompressedBytes);
        $decodedBytes = '';
        foreach ($rawFrames as $frame) {
            if (($frame['type'] ?? null) === 'frame') {
                $decodedBytes .= (string) $frame['data'];
            }
        }

        $combined = self::archivePackageBoundarySummary(
            $decodedBytes,
            $expectedKind,
            $maxUnpackedBytes,
            'LZ4 frame source-boundary'
        );

        $frames = [];
        $dataFrameIndex = 0;
        $skippableFrameCount = 0;
        $standalonePackageFrameCount = 0;

        foreach ($rawFrames as $frameIndex => $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $payload = (string) ($frame['data'] ?? '');
                $frames[] = [
                    'type' => 'skippable',
                    'frameIndex' => $frameIndex,
                    'id' => (int) $frame['id'],
                    'payloadSize' => strlen($payload),
                    'payloadSha256' => hash('sha256', $payload),
                    'payloadPreview' => self::boundedPrintablePreview($payload, 64),
                    'frameOffset' => (int) $frame['frameOffset'],
                    'frameSize' => (int) $frame['frameSize'],
                    'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                    'policy' => 'metadata-only-no-extraction',
                    'diagnostics' => [],
                ];
                $skippableFrameCount++;
                continue;
            }

            if (($frame['type'] ?? null) !== 'frame') {
                throw new \RuntimeException('Unexpected LZ4 frame metadata record');
            }

            $frameData = (string) $frame['data'];
            $summary = self::archivePackageBoundarySummary(
                $frameData,
                $expectedKind,
                $maxUnpackedBytes,
                'LZ4 frame source-boundary'
            );
            $standalonePackage = $summary['status'] === 'package';
            $frameDiagnostics = $standalonePackage ? ['lz4-frame-is-standalone-package'] : [];
            if ($standalonePackage) {
                $standalonePackageFrameCount++;
            }

            $frames[] = [
                'type' => 'frame',
                'frameIndex' => $frameIndex,
                'dataFrameIndex' => $dataFrameIndex,
                'contentSize' => $frame['contentSize'],
                'dictionaryId' => $frame['dictionaryId'],
                'blockMaxSize' => (int) $frame['blockMaxSize'],
                'blockIndependent' => (bool) $frame['blockIndependent'],
                'blockChecksum' => (bool) $frame['blockChecksum'],
                'contentChecksum' => (bool) $frame['contentChecksum'],
                'blockCount' => (int) $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => (int) $frame['compressedSize'],
                'decodedDataOffset' => (int) $frame['decodedDataOffset'],
                'decodedDataEndOffset' => (int) $frame['decodedDataEndOffset'],
                'decodedSize' => strlen($frameData),
                'frameOffset' => (int) $frame['frameOffset'],
                'frameSize' => (int) $frame['frameSize'],
                'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                'standalonePackage' => $standalonePackage,
                'packageStatus' => $summary['status'],
                'packageError' => $summary['error'],
                'kind' => $standalonePackage ? $expectedKind : null,
                'format' => $standalonePackage
                    ? ($expectedKind === self::PACKAGE_KIND_TAR ? self::FORMAT_TAR : self::FORMAT_ZIP)
                    : null,
                'entryCount' => $summary['entryCount'],
                'entryNames' => $summary['entryNames'],
                'policy' => $standalonePackage ? 'standalone-lz4-frame-package' : 'package-segment',
                'diagnostics' => $frameDiagnostics,
            ] + self::lz4FrameDescriptorMetadata($frame);
            $dataFrameIndex++;
        }

        $diagnostics = [];
        if ($combined['status'] !== 'package') {
            $diagnostics[] = 'lz4-combined-package-decode-failed';
        }

        if ($dataFrameIndex > 1 && $standalonePackageFrameCount > 0) {
            $diagnostics[] = 'lz4-frames-contain-standalone-packages';
        }

        if ($standalonePackageFrameCount > 1) {
            $diagnostics[] = 'lz4-multiple-standalone-package-frames';
        }

        $policy = $diagnostics === []
            ? ($dataFrameIndex === 1 ? 'single-lz4-frame-package-stream' : 'single-decoded-package-stream')
            : 'review-before-conversion';

        return [
            'type' => 'archive-lz4-frame-source-boundary-policy',
            'expectedKind' => $expectedKind,
            'format' => $format,
            'frameCount' => count($rawFrames),
            'dataFrameCount' => $dataFrameIndex,
            'skippableFrameCount' => $skippableFrameCount,
            'compressedSize' => strlen($bytes),
            'decodedSize' => strlen($decodedBytes),
            'combinedPackageStatus' => $combined['status'],
            'combinedPackageError' => $combined['error'],
            'combinedEntryCount' => $combined['entryCount'],
            'combinedEntryNames' => $combined['entryNames'],
            'standalonePackageFrameCount' => $standalonePackageFrameCount,
            'policy' => $policy,
            'extractionPolicy' => $diagnostics === []
                ? 'metadata-only-no-extraction'
                : 'lz4-frame-source-boundary-review',
            'diagnostics' => $diagnostics,
            'frames' => $frames,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     expectedKind:string,
     *     format:string,
     *     compressedSize:int,
     *     decodedPackageSize:int,
     *     packageStatus:string,
     *     packageError:?string,
     *     entryCount:int,
     *     entryNames:list<string>,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     maxDataFrameCount:int,
     *     countOverLimitDataFrameCount:int,
     *     firstCountOverLimitDataFrameIndex:?int,
     *     maxFrameDecodedBytes:int,
     *     byteOverLimitFrameCount:int,
     *     firstByteOverLimitDataFrameIndex:?int,
     *     largestFrameDecodedSize:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     frames:list<array<string, mixed>>
     * }
     */
    public static function inspectLz4DataFrameLimitPolicy(
        string $bytes,
        string $format,
        int $maxDataFrameCount,
        int $maxFrameDecodedBytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($maxDataFrameCount <= 0) {
            throw new \RuntimeException('LZ4 data frame-count policy threshold must be positive');
        }

        if ($maxFrameDecodedBytes <= 0) {
            throw new \RuntimeException('LZ4 data frame decoded byte-limit threshold must be positive');
        }

        $expectedKind = match ($format) {
            self::FORMAT_LZ4_TAR => self::PACKAGE_KIND_TAR,
            self::FORMAT_LZ4_ZIP => self::PACKAGE_KIND_ZIP,
            default => throw new \RuntimeException("LZ4 data frame limit policy requires an LZ4 archive stream format: {$format}"),
        };

        $rawFrames = Lz4Frame::frames($bytes, $maxUncompressedBytes);
        $decodedBytes = '';
        foreach ($rawFrames as $frame) {
            if (($frame['type'] ?? null) === 'frame') {
                $decodedBytes .= (string) $frame['data'];
            }
        }

        $package = self::archivePackageBoundarySummary(
            $decodedBytes,
            $expectedKind,
            $maxUnpackedBytes,
            'LZ4 data frame limit'
        );

        $frames = [];
        $dataFrameIndex = 0;
        $skippableFrameCount = 0;
        $countOverLimitDataFrameCount = 0;
        $firstCountOverLimitDataFrameIndex = null;
        $byteOverLimitFrameCount = 0;
        $firstByteOverLimitDataFrameIndex = null;
        $largestFrameDecodedSize = 0;

        foreach ($rawFrames as $frameIndex => $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $payload = (string) ($frame['data'] ?? '');
                $frames[] = [
                    'type' => 'skippable',
                    'frameIndex' => $frameIndex,
                    'id' => (int) $frame['id'],
                    'payloadSize' => strlen($payload),
                    'payloadSha256' => hash('sha256', $payload),
                    'payloadPreview' => self::boundedPrintablePreview($payload, 64),
                    'frameOffset' => (int) $frame['frameOffset'],
                    'frameSize' => (int) $frame['frameSize'],
                    'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                    'policy' => 'metadata-only-no-extraction',
                    'diagnostics' => [],
                ];
                $skippableFrameCount++;
                continue;
            }

            if (($frame['type'] ?? null) !== 'frame') {
                throw new \RuntimeException('Unexpected LZ4 frame metadata record');
            }

            $decodedSize = strlen((string) $frame['data']);
            $largestFrameDecodedSize = max($largestFrameDecodedSize, $decodedSize);
            $countOverLimit = $dataFrameIndex >= $maxDataFrameCount;
            $decodedBytesOverLimit = $decodedSize > $maxFrameDecodedBytes;
            $frameDiagnostics = [];

            if ($countOverLimit) {
                $frameDiagnostics[] = 'lz4-data-frame-count-over-limit';
                $countOverLimitDataFrameCount++;
                if ($firstCountOverLimitDataFrameIndex === null) {
                    $firstCountOverLimitDataFrameIndex = $dataFrameIndex;
                }
            }

            if ($decodedBytesOverLimit) {
                $frameDiagnostics[] = 'lz4-data-frame-byte-limit-over-limit';
                $byteOverLimitFrameCount++;
                if ($firstByteOverLimitDataFrameIndex === null) {
                    $firstByteOverLimitDataFrameIndex = $dataFrameIndex;
                }
            }

            $frames[] = [
                'type' => 'frame',
                'frameIndex' => $frameIndex,
                'dataFrameIndex' => $dataFrameIndex,
                'contentSize' => $frame['contentSize'],
                'dictionaryId' => $frame['dictionaryId'],
                'blockMaxSize' => (int) $frame['blockMaxSize'],
                'blockIndependent' => (bool) $frame['blockIndependent'],
                'blockChecksum' => (bool) $frame['blockChecksum'],
                'contentChecksum' => (bool) $frame['contentChecksum'],
                'blockCount' => (int) $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => (int) $frame['compressedSize'],
                'decodedSize' => $decodedSize,
                'decodedDataOffset' => (int) $frame['decodedDataOffset'],
                'decodedDataEndOffset' => (int) $frame['decodedDataEndOffset'],
                'frameOffset' => (int) $frame['frameOffset'],
                'frameSize' => (int) $frame['frameSize'],
                'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                'countOverLimit' => $countOverLimit,
                'decodedBytesOverLimit' => $decodedBytesOverLimit,
                'policy' => $frameDiagnostics === [] ? 'metadata-only-no-extraction' : 'review-before-conversion',
                'diagnostics' => $frameDiagnostics,
            ] + self::lz4FrameDescriptorMetadata($frame);
            $dataFrameIndex++;
        }

        $diagnostics = [];
        if ($package['status'] !== 'package') {
            $diagnostics[] = 'lz4-combined-package-decode-failed';
        }

        if ($countOverLimitDataFrameCount > 0) {
            $diagnostics[] = 'lz4-data-frame-count-exceeds-threshold';
        }

        if ($byteOverLimitFrameCount > 0) {
            $diagnostics[] = 'lz4-data-frame-byte-limit-exceeds-threshold';
        }

        return [
            'type' => 'archive-lz4-data-frame-limit-policy',
            'expectedKind' => $expectedKind,
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'decodedPackageSize' => strlen($decodedBytes),
            'packageStatus' => $package['status'],
            'packageError' => $package['error'],
            'entryCount' => $package['entryCount'],
            'entryNames' => $package['entryNames'],
            'frameCount' => count($rawFrames),
            'dataFrameCount' => $dataFrameIndex,
            'skippableFrameCount' => $skippableFrameCount,
            'maxDataFrameCount' => $maxDataFrameCount,
            'countOverLimitDataFrameCount' => $countOverLimitDataFrameCount,
            'firstCountOverLimitDataFrameIndex' => $firstCountOverLimitDataFrameIndex,
            'maxFrameDecodedBytes' => $maxFrameDecodedBytes,
            'byteOverLimitFrameCount' => $byteOverLimitFrameCount,
            'firstByteOverLimitDataFrameIndex' => $firstByteOverLimitDataFrameIndex,
            'largestFrameDecodedSize' => $largestFrameDecodedSize,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === []
                ? 'metadata-only-no-extraction'
                : 'lz4-data-frame-limit-review',
            'diagnostics' => $diagnostics,
            'frames' => $frames,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectGzipTarRecordBoundaryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_GZIP_TAR) {
            throw new \RuntimeException("GZIP TAR record-boundary policy requires a GZIP TAR archive stream format: {$format}");
        }

        $inspection = self::inspectTarStream($bytes, $format, $maxUncompressedBytes, $maxUnpackedBytes);
        $stream = $inspection['stream'];
        $members = array_values(array_filter($stream['members'] ?? [], 'is_array'));
        $entryLayouts = array_values(array_filter($inspection['entryLayouts'] ?? [], 'is_array'));
        $metadataLayouts = array_values(array_filter($inspection['metadataLayouts'] ?? [], 'is_array'));
        $boundaries = [];
        $splitBoundaryCount = 0;
        $splitRecordCount = 0;
        $splitEntryRecordCount = 0;
        $splitMetadataRecordCount = 0;

        for ($index = 0; $index + 1 < count($members); $index++) {
            $member = $members[$index];
            $nextMember = $members[$index + 1];
            $boundaryOffset = (int) ($member['decodedDataEndOffset'] ?? 0);
            $splitRecords = [];

            foreach ($metadataLayouts as $layout) {
                $splitRecord = self::gzipTarBoundarySplitMetadataRecord($layout, $boundaryOffset);
                if ($splitRecord !== null) {
                    $splitRecords[] = $splitRecord;
                }
            }

            foreach ($entryLayouts as $layout) {
                $splitRecord = self::gzipTarBoundarySplitEntryRecord($layout, $boundaryOffset);
                if ($splitRecord !== null) {
                    $splitRecords[] = $splitRecord;
                }
            }

            $entrySplitCount = count(array_filter(
                $splitRecords,
                static fn (array $record): bool => ($record['recordKind'] ?? null) === 'entry'
            ));
            $metadataSplitCount = count($splitRecords) - $entrySplitCount;
            $boundaryDiagnostics = [];
            if ($splitRecords !== []) {
                $splitBoundaryCount++;
                $boundaryDiagnostics[] = 'gzip-member-boundary-splits-tar-record';
            }

            if ($entrySplitCount > 0) {
                $boundaryDiagnostics[] = 'gzip-member-boundary-splits-tar-entry-record';
            }

            if ($metadataSplitCount > 0) {
                $boundaryDiagnostics[] = 'gzip-member-boundary-splits-tar-metadata-record';
            }

            $splitRecordCount += count($splitRecords);
            $splitEntryRecordCount += $entrySplitCount;
            $splitMetadataRecordCount += $metadataSplitCount;

            $boundaries[] = [
                'boundaryIndex' => $index,
                'previousMemberIndex' => $index,
                'nextMemberIndex' => $index + 1,
                'previousMemberLabel' => self::gzipMemberReviewLabel($member),
                'nextMemberLabel' => self::gzipMemberReviewLabel($nextMember),
                'decodedBoundaryOffset' => $boundaryOffset,
                'splitRecordCount' => count($splitRecords),
                'splitEntryRecordCount' => $entrySplitCount,
                'splitMetadataRecordCount' => $metadataSplitCount,
                'policy' => $splitRecords === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $boundaryDiagnostics,
                'splitRecords' => $splitRecords,
            ];
        }

        $diagnostics = [];
        if ($splitRecordCount > 0) {
            $diagnostics[] = 'gzip-member-boundary-splits-tar-record';
        }

        if ($splitEntryRecordCount > 0) {
            $diagnostics[] = 'gzip-member-boundary-splits-tar-entry-record';
        }

        if ($splitMetadataRecordCount > 0) {
            $diagnostics[] = 'gzip-member-boundary-splits-tar-metadata-record';
        }

        return [
            'type' => 'archive-gzip-tar-record-boundary-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => (int) $inspection['uncompressedSize'],
            'memberCount' => count($members),
            'boundaryCount' => count($boundaries),
            'alignedBoundaryCount' => count($boundaries) - $splitBoundaryCount,
            'splitBoundaryCount' => $splitBoundaryCount,
            'splitRecordCount' => $splitRecordCount,
            'splitEntryRecordCount' => $splitEntryRecordCount,
            'splitMetadataRecordCount' => $splitMetadataRecordCount,
            'entryCount' => count($entryLayouts),
            'metadataLayoutCount' => count($metadataLayouts),
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'gzip-tar-record-boundary-review',
            'diagnostics' => $diagnostics,
            'boundaries' => $boundaries,
            'stream' => $stream,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectLz4TarRecordBoundaryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_LZ4_TAR) {
            throw new \RuntimeException("LZ4 TAR record-boundary policy requires an LZ4 TAR archive stream format: {$format}");
        }

        $inspection = self::inspectTarStream($bytes, $format, $maxUncompressedBytes, $maxUnpackedBytes);
        $stream = $inspection['stream'];
        $frames = [];
        $dataFrameIndex = 0;
        foreach (($stream['frames'] ?? []) as $frameIndex => $frame) {
            if (!is_array($frame) || ($frame['type'] ?? null) !== 'frame') {
                continue;
            }

            $frames[] = $frame + [
                'frameIndex' => (int) $frameIndex,
                'dataFrameIndex' => $dataFrameIndex,
            ];
            $dataFrameIndex++;
        }

        $entryLayouts = array_values(array_filter($inspection['entryLayouts'] ?? [], 'is_array'));
        $metadataLayouts = array_values(array_filter($inspection['metadataLayouts'] ?? [], 'is_array'));
        $boundaries = [];
        $splitBoundaryCount = 0;
        $splitRecordCount = 0;
        $splitEntryRecordCount = 0;
        $splitMetadataRecordCount = 0;

        for ($index = 0; $index + 1 < count($frames); $index++) {
            $frame = $frames[$index];
            $nextFrame = $frames[$index + 1];
            $boundaryOffset = (int) ($frame['decodedDataEndOffset'] ?? 0);
            $splitRecords = [];

            foreach ($metadataLayouts as $layout) {
                $splitRecord = self::lz4TarBoundarySplitMetadataRecord($layout, $boundaryOffset);
                if ($splitRecord !== null) {
                    $splitRecords[] = $splitRecord;
                }
            }

            foreach ($entryLayouts as $layout) {
                $splitRecord = self::lz4TarBoundarySplitEntryRecord($layout, $boundaryOffset);
                if ($splitRecord !== null) {
                    $splitRecords[] = $splitRecord;
                }
            }

            $entrySplitCount = count(array_filter(
                $splitRecords,
                static fn (array $record): bool => ($record['recordKind'] ?? null) === 'entry'
            ));
            $metadataSplitCount = count($splitRecords) - $entrySplitCount;
            $boundaryDiagnostics = [];
            if ($splitRecords !== []) {
                $splitBoundaryCount++;
                $boundaryDiagnostics[] = 'lz4-frame-boundary-splits-tar-record';
            }

            if ($entrySplitCount > 0) {
                $boundaryDiagnostics[] = 'lz4-frame-boundary-splits-tar-entry-record';
            }

            if ($metadataSplitCount > 0) {
                $boundaryDiagnostics[] = 'lz4-frame-boundary-splits-tar-metadata-record';
            }

            $splitRecordCount += count($splitRecords);
            $splitEntryRecordCount += $entrySplitCount;
            $splitMetadataRecordCount += $metadataSplitCount;

            $boundaries[] = [
                'boundaryIndex' => $index,
                'previousFrameIndex' => (int) $frame['frameIndex'],
                'nextFrameIndex' => (int) $nextFrame['frameIndex'],
                'previousDataFrameIndex' => (int) $frame['dataFrameIndex'],
                'nextDataFrameIndex' => (int) $nextFrame['dataFrameIndex'],
                'previousFrameOffset' => (int) ($frame['frameOffset'] ?? 0),
                'nextFrameOffset' => (int) ($nextFrame['frameOffset'] ?? 0),
                'decodedBoundaryOffset' => $boundaryOffset,
                'splitRecordCount' => count($splitRecords),
                'splitEntryRecordCount' => $entrySplitCount,
                'splitMetadataRecordCount' => $metadataSplitCount,
                'policy' => $splitRecords === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $boundaryDiagnostics,
                'splitRecords' => $splitRecords,
            ];
        }

        $diagnostics = [];
        if ($splitRecordCount > 0) {
            $diagnostics[] = 'lz4-frame-boundary-splits-tar-record';
        }

        if ($splitEntryRecordCount > 0) {
            $diagnostics[] = 'lz4-frame-boundary-splits-tar-entry-record';
        }

        if ($splitMetadataRecordCount > 0) {
            $diagnostics[] = 'lz4-frame-boundary-splits-tar-metadata-record';
        }

        return [
            'type' => 'archive-lz4-tar-record-boundary-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => (int) $inspection['uncompressedSize'],
            'frameCount' => (int) ($stream['frameCount'] ?? count($stream['frames'] ?? [])),
            'dataFrameCount' => count($frames),
            'skippableFrameCount' => (int) ($stream['skippableFrameCount'] ?? 0),
            'boundaryCount' => count($boundaries),
            'alignedBoundaryCount' => count($boundaries) - $splitBoundaryCount,
            'splitBoundaryCount' => $splitBoundaryCount,
            'splitRecordCount' => $splitRecordCount,
            'splitEntryRecordCount' => $splitEntryRecordCount,
            'splitMetadataRecordCount' => $splitMetadataRecordCount,
            'entryCount' => count($entryLayouts),
            'metadataLayoutCount' => count($metadataLayouts),
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'lz4-tar-record-boundary-review',
            'diagnostics' => $diagnostics,
            'boundaries' => $boundaries,
            'stream' => $stream,
        ];
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        return match ($format) {
            self::FORMAT_ZLIB_TAR => [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::inspectTarStreamWithZlibDictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes,
                $maxUnpackedBytes
            ),
            self::FORMAT_ZLIB_ZIP => [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + self::inspectZipStreamWithZlibDictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes
            ),
            default => throw new \RuntimeException("ZLIB dictionary package inspection requires a ZLIB archive stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        return match ($format) {
            self::FORMAT_LZ4_TAR => [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::inspectTarStreamWithLz4Dictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes,
                $maxUnpackedBytes
            ),
            self::FORMAT_LZ4_ZIP => [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + self::inspectZipStreamWithLz4Dictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes
            ),
            default => throw new \RuntimeException("LZ4 dictionary package inspection requires an LZ4 archive stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     textHintMemberCount:int,
     *     binaryTextHintMemberCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>
     * }
     */
    public static function inspectGzipTextHintPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP text-hint policy requires a GZIP archive stream format: {$format}");
        }

        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $members = [];
        $textHintMemberCount = 0;
        $binaryTextHintMemberCount = 0;

        foreach ($inspection['members'] as $index => $member) {
            $data = $member['data'];
            $probe = substr($data, 0, 4096);
            $payloadDiagnostics = self::gzipTextHintPayloadDiagnostics($probe);
            $payloadLooksBinary = $payloadDiagnostics !== [];
            $textHint = (bool) $member['textHint'];
            if ($textHint) {
                $textHintMemberCount++;
            }

            $diagnostics = [];
            if ($textHint && $payloadLooksBinary) {
                $diagnostics = array_merge(['gzip-text-hint-binary-payload'], $payloadDiagnostics);
                $binaryTextHintMemberCount++;
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'textHint' => $textHint,
                'flags' => $member['flags'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'memberOffset' => $member['memberOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'payloadLooksBinary' => $payloadLooksBinary,
                'payloadProbeBytes' => strlen($probe),
                'policy' => $diagnostics === [] ? 'metadata' : 'review',
                'diagnostics' => $diagnostics,
            ];
        }

        return [
            'type' => 'gzip-text-hint-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'memberCount' => $inspection['memberCount'],
            'textHintMemberCount' => $textHintMemberCount,
            'binaryTextHintMemberCount' => $binaryTextHintMemberCount,
            'handoffPolicy' => $binaryTextHintMemberCount === 0 ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $binaryTextHintMemberCount === 0 ? [] : ['gzip-text-hint-binary-payload'],
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     metadataMemberCount:int,
     *     filenameMemberCount:int,
     *     commentMemberCount:int,
     *     unsafeFilenameMemberCount:int,
     *     unsafeCommentMemberCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>
     * }
     */
    public static function inspectGzipMemberMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP member metadata policy requires a GZIP archive stream format: {$format}");
        }

        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $members = [];
        $metadataMemberCount = 0;
        $filenameMemberCount = 0;
        $commentMemberCount = 0;
        $unsafeFilenameMemberCount = 0;
        $unsafeCommentMemberCount = 0;
        $diagnostics = [];

        foreach ($inspection['members'] as $index => $member) {
            $filenameText = is_string($member['filenameText'] ?? null)
                ? $member['filenameText']
                : null;
            $commentText = is_string($member['commentText'] ?? null)
                ? $member['commentText']
                : null;
            $hasFilename = $filenameText !== null;
            $hasComment = $commentText !== null;
            if ($hasFilename || $hasComment) {
                $metadataMemberCount++;
            }
            if ($hasFilename) {
                $filenameMemberCount++;
            }
            if ($hasComment) {
                $commentMemberCount++;
            }

            $filenameDiagnostics = $hasFilename
                ? self::gzipMemberMetadataTextDiagnostics($filenameText, 'gzip-member-filename', true)
                : [];
            $commentDiagnostics = $hasComment
                ? self::gzipMemberMetadataTextDiagnostics($commentText, 'gzip-member-comment', false)
                : [];
            if ($filenameDiagnostics !== []) {
                $unsafeFilenameMemberCount++;
            }
            if ($commentDiagnostics !== []) {
                $unsafeCommentMemberCount++;
            }

            $memberDiagnostics = array_values(array_unique(array_merge($filenameDiagnostics, $commentDiagnostics)));
            foreach ($memberDiagnostics as $diagnostic) {
                if (!in_array($diagnostic, $diagnostics, true)) {
                    $diagnostics[] = $diagnostic;
                }
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $filenameText,
                'filenameEncoding' => $member['filenameEncoding'],
                'filenameLength' => $filenameText === null ? 0 : strlen($filenameText),
                'filenameSegmentCount' => $filenameText === null || $filenameText === ''
                    ? 0
                    : count(explode('/', str_replace('\\', '/', $filenameText))),
                'comment' => $member['comment'],
                'commentText' => $commentText,
                'commentEncoding' => $member['commentEncoding'],
                'commentLength' => $commentText === null ? 0 : strlen($commentText),
                'hasFilenameMetadata' => $hasFilename,
                'hasCommentMetadata' => $hasComment,
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'policy' => $memberDiagnostics === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $memberDiagnostics,
            ];
        }

        return [
            'type' => 'archive-gzip-member-metadata-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'memberCount' => $inspection['memberCount'],
            'metadataMemberCount' => $metadataMemberCount,
            'filenameMemberCount' => $filenameMemberCount,
            'commentMemberCount' => $commentMemberCount,
            'unsafeFilenameMemberCount' => $unsafeFilenameMemberCount,
            'unsafeCommentMemberCount' => $unsafeCommentMemberCount,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'gzip-member-metadata-review',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     timestampedMemberCount:int,
     *     unknownModifiedAtMemberCount:int,
     *     earliestModifiedAt:?int,
     *     earliestModifiedAtText:?string,
     *     latestModifiedAt:?int,
     *     latestModifiedAtText:?string,
     *     timestampSpreadSeconds:?int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>
     * }
     */
    public static function inspectGzipTimestampPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP timestamp policy requires a GZIP archive stream format: {$format}");
        }

        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $timestampedMemberCount = 0;
        $unknownModifiedAtMemberCount = 0;
        $knownModifiedAtValues = [];
        $members = [];

        foreach ($inspection['members'] as $index => $member) {
            $modifiedAtKnown = (bool) $member['modifiedAtKnown'];
            $memberDiagnostics = [];
            if ($modifiedAtKnown) {
                $timestampedMemberCount++;
                $knownModifiedAtValues[] = (int) $member['modifiedAt'];
                $memberDiagnostics[] = 'gzip-member-mtime-present';
            } else {
                $unknownModifiedAtMemberCount++;
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'modifiedAt' => $member['modifiedAt'],
                'modifiedAtKnown' => $modifiedAtKnown,
                'modifiedAtText' => $member['modifiedAtText'],
                'extraFlagsMeaning' => $member['extraFlagsMeaning'],
                'operatingSystemName' => $member['operatingSystemName'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'policy' => $modifiedAtKnown ? 'review-before-conversion' : 'metadata',
                'diagnostics' => $memberDiagnostics,
            ];
        }

        $diagnostics = [];
        $earliestModifiedAt = null;
        $latestModifiedAt = null;
        $timestampSpreadSeconds = null;
        if ($knownModifiedAtValues !== []) {
            $earliestModifiedAt = min($knownModifiedAtValues);
            $latestModifiedAt = max($knownModifiedAtValues);
            $timestampSpreadSeconds = $latestModifiedAt - $earliestModifiedAt;
            $diagnostics[] = 'gzip-member-timestamp-metadata-present';
        }

        if ($timestampSpreadSeconds !== null && $timestampSpreadSeconds > 0) {
            $diagnostics[] = 'gzip-member-timestamp-metadata-varies';
            foreach ($members as &$member) {
                if (($member['modifiedAtKnown'] ?? false) === true) {
                    $member['diagnostics'][] = 'gzip-member-mtime-varies';
                }
            }
            unset($member);
        }

        return [
            'type' => 'archive-gzip-timestamp-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'memberCount' => $inspection['memberCount'],
            'timestampedMemberCount' => $timestampedMemberCount,
            'unknownModifiedAtMemberCount' => $unknownModifiedAtMemberCount,
            'earliestModifiedAt' => $earliestModifiedAt,
            'earliestModifiedAtText' => self::gzipModifiedAtText($earliestModifiedAt),
            'latestModifiedAt' => $latestModifiedAt,
            'latestModifiedAtText' => self::gzipModifiedAtText($latestModifiedAt),
            'timestampSpreadSeconds' => $timestampSpreadSeconds,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     platformMetadataMemberCount:int,
     *     knownOperatingSystemMemberCount:int,
     *     unknownOperatingSystemMemberCount:int,
     *     optimizedCompressionMemberCount:int,
     *     unknownExtraFlagsMemberCount:int,
     *     knownOperatingSystemNames:list<string>,
     *     extraFlagsMeanings:list<string>,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     members:list<array<string, mixed>>
     * }
     */
    public static function inspectGzipPlatformMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_GZIP_TAR && $format !== self::FORMAT_GZIP_ZIP) {
            throw new \RuntimeException("GZIP platform metadata policy requires a GZIP archive stream format: {$format}");
        }

        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $knownOperatingSystemNames = [];
        $extraFlagsMeanings = [];

        foreach ($inspection['members'] as $member) {
            if ((int) $member['operatingSystem'] !== 255) {
                $operatingSystemName = (string) $member['operatingSystemName'];
                if (!in_array($operatingSystemName, $knownOperatingSystemNames, true)) {
                    $knownOperatingSystemNames[] = $operatingSystemName;
                }
            }

            if ((int) $member['extraFlags'] !== 0) {
                $extraFlagsMeaning = (string) $member['extraFlagsMeaning'];
                if (!in_array($extraFlagsMeaning, $extraFlagsMeanings, true)) {
                    $extraFlagsMeanings[] = $extraFlagsMeaning;
                }
            }
        }

        $operatingSystemVaries = count($knownOperatingSystemNames) > 1;
        $members = [];
        $platformMetadataMemberCount = 0;
        $knownOperatingSystemMemberCount = 0;
        $unknownOperatingSystemMemberCount = 0;
        $optimizedCompressionMemberCount = 0;
        $unknownExtraFlagsMemberCount = 0;

        foreach ($inspection['members'] as $index => $member) {
            $operatingSystem = (int) $member['operatingSystem'];
            $extraFlags = (int) $member['extraFlags'];
            $extraFlagsMeaning = (string) $member['extraFlagsMeaning'];
            $hasPlatformMetadata = false;
            $memberDiagnostics = [];

            if ($operatingSystem === 255) {
                $unknownOperatingSystemMemberCount++;
            } else {
                $knownOperatingSystemMemberCount++;
                $hasPlatformMetadata = true;
                $memberDiagnostics[] = 'gzip-member-operating-system-present';
            }

            if ($extraFlags !== 0) {
                $hasPlatformMetadata = true;
                $memberDiagnostics[] = 'gzip-member-extra-flags-present';
                if ($extraFlags === 2 || $extraFlags === 4) {
                    $optimizedCompressionMemberCount++;
                } else {
                    $unknownExtraFlagsMemberCount++;
                    $memberDiagnostics[] = 'gzip-member-extra-flags-unknown';
                }
            }

            if ($operatingSystem !== 255 && $operatingSystemVaries) {
                $memberDiagnostics[] = 'gzip-member-operating-system-varies';
            }

            if ($hasPlatformMetadata) {
                $platformMetadataMemberCount++;
            }

            $members[] = [
                'memberIndex' => $index,
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'flags' => $member['flags'],
                'extraFlags' => $extraFlags,
                'extraFlagsMeaning' => $extraFlagsMeaning,
                'operatingSystem' => $operatingSystem,
                'operatingSystemName' => $member['operatingSystemName'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberOffset' => $member['memberOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'policy' => $memberDiagnostics === [] ? 'metadata' : 'review-before-conversion',
                'diagnostics' => $memberDiagnostics,
            ];
        }

        $diagnostics = [];
        if ($platformMetadataMemberCount > 0) {
            $diagnostics[] = 'gzip-platform-metadata-present';
        }

        if ($operatingSystemVaries) {
            $diagnostics[] = 'gzip-platform-operating-system-varies';
        }

        if ($optimizedCompressionMemberCount > 0) {
            $diagnostics[] = 'gzip-compression-strategy-metadata-present';
        }

        if ($unknownExtraFlagsMemberCount > 0) {
            $diagnostics[] = 'gzip-extra-flags-unknown';
        }

        return [
            'type' => 'archive-gzip-platform-metadata-policy',
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'memberCount' => $inspection['memberCount'],
            'platformMetadataMemberCount' => $platformMetadataMemberCount,
            'knownOperatingSystemMemberCount' => $knownOperatingSystemMemberCount,
            'unknownOperatingSystemMemberCount' => $unknownOperatingSystemMemberCount,
            'optimizedCompressionMemberCount' => $optimizedCompressionMemberCount,
            'unknownExtraFlagsMemberCount' => $unknownExtraFlagsMemberCount,
            'knownOperatingSystemNames' => $knownOperatingSystemNames,
            'extraFlagsMeanings' => $extraFlagsMeanings,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     kind:string,
     *     format:string,
     *     compressedSize:int,
     *     decodedPackageSize:int,
     *     entryUncompressedSize:int,
     *     entryCount:int,
     *     streamCompressionRatio:float,
     *     packageExpansionRatio:float,
     *     totalExpansionRatio:float,
     *     maxStreamCompressionRatio:float,
     *     maxPackageExpansionRatio:float,
     *     maxTotalExpansionRatio:float,
     *     diagnosticCount:int,
     *     diagnostics:list<string>,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectArchiveBombPolicyAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        float $maxStreamCompressionRatio = 100.0,
        float $maxPackageExpansionRatio = 100.0,
        float $maxTotalExpansionRatio = 100.0
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        self::assertPositiveRatio($maxStreamCompressionRatio, 'archive stream compression-ratio threshold');
        self::assertPositiveRatio($maxPackageExpansionRatio, 'archive package expansion-ratio threshold');
        self::assertPositiveRatio($maxTotalExpansionRatio, 'archive total expansion-ratio threshold');

        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $kind = $candidate['kind'];
        $format = $candidate['format'];
        $compressedSize = strlen($bytes);

        if ($kind === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'];
            if (!$archive instanceof TarArchive) {
                throw new \RuntimeException('Detected TAR archive candidate is missing archive metadata');
            }

            $decodedPackageSize = strlen($candidate['tarBytes']);
            $entryUncompressedSize = self::archiveUnpackedSize($archive);
            $entryCount = count($archive->names());
        } else {
            $package = $candidate['package'];
            if (!$package instanceof ZipPackage) {
                throw new \RuntimeException('Detected ZIP package candidate is missing package metadata');
            }

            $decodedPackageSize = strlen($candidate['zipBytes']);
            $entryUncompressedSize = self::zipPackageUncompressedSize($package);
            $entryCount = count($package->names());
        }

        $streamCompressionRatio = self::expansionRatio($decodedPackageSize, $compressedSize);
        $packageExpansionRatio = self::expansionRatio($entryUncompressedSize, $decodedPackageSize);
        $totalExpansionRatio = self::expansionRatio($entryUncompressedSize, $compressedSize);
        $diagnostics = [];
        if ($streamCompressionRatio > $maxStreamCompressionRatio) {
            $diagnostics[] = 'archive-stream-compression-ratio-exceeds-threshold';
        }

        if ($packageExpansionRatio > $maxPackageExpansionRatio) {
            $diagnostics[] = 'archive-package-expansion-ratio-exceeds-threshold';
        }

        if ($totalExpansionRatio > $maxTotalExpansionRatio) {
            $diagnostics[] = 'archive-total-expansion-ratio-exceeds-threshold';
        }

        return [
            'kind' => $kind,
            'format' => $format,
            'compressedSize' => $compressedSize,
            'decodedPackageSize' => $decodedPackageSize,
            'entryUncompressedSize' => $entryUncompressedSize,
            'entryCount' => $entryCount,
            'streamCompressionRatio' => $streamCompressionRatio,
            'packageExpansionRatio' => $packageExpansionRatio,
            'totalExpansionRatio' => $totalExpansionRatio,
            'maxStreamCompressionRatio' => $maxStreamCompressionRatio,
            'maxPackageExpansionRatio' => $maxPackageExpansionRatio,
            'maxTotalExpansionRatio' => $maxTotalExpansionRatio,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     rootKind:string,
     *     rootFormat:string,
     *     compressedSize:int,
     *     maxDepth:int,
     *     packageCount:int,
     *     nestedCandidateCount:int,
     *     nestedPackageCount:int,
     *     nestedUnreadableCount:int,
     *     nestedUnsupportedCompressionCount:int,
     *     nestedDiagnosticCount:int,
     *     recordDiagnosticCount:int,
     *     ratioDiagnosticCount:int,
     *     depthLimitReachedCount:int,
     *     depthLimitedCandidateCount:int,
     *     maxObservedStreamCompressionRatio:float,
     *     maxObservedPackageExpansionRatio:float,
     *     maxObservedTotalExpansionRatio:float,
     *     maxStreamCompressionRatio:float,
     *     maxPackageExpansionRatio:float,
     *     maxTotalExpansionRatio:float,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     root:array<string, mixed>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function inspectNestedArchiveBombPolicyAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        int $maxDepth = 1,
        float $maxStreamCompressionRatio = 100.0,
        float $maxPackageExpansionRatio = 100.0,
        float $maxTotalExpansionRatio = 100.0
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($maxDepth < 0) {
            throw new \RuntimeException('Nested archive bomb policy max depth must not be negative');
        }

        self::assertPositiveRatio($maxStreamCompressionRatio, 'archive stream compression-ratio threshold');
        self::assertPositiveRatio($maxPackageExpansionRatio, 'archive package expansion-ratio threshold');
        self::assertPositiveRatio($maxTotalExpansionRatio, 'archive total expansion-ratio threshold');

        $thresholds = [
            'stream' => $maxStreamCompressionRatio,
            'package' => $maxPackageExpansionRatio,
            'total' => $maxTotalExpansionRatio,
        ];
        $rootCandidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $root = self::archiveBombPackageRecord(
            $rootCandidate,
            '(root)',
            null,
            null,
            null,
            0,
            strlen($bytes),
            [],
            $maxDepth,
            $thresholds
        );

        $entries = [];
        if ($maxDepth > 0) {
            self::collectNestedArchiveBombEntries(
                $rootCandidate,
                '',
                1,
                $maxDepth,
                $maxUncompressedBytes,
                $maxUnpackedBytes,
                $thresholds,
                $entries
            );
        }

        $records = array_merge([$root], $entries);
        $packageCount = 0;
        $nestedPackageCount = 0;
        $nestedUnreadableCount = 0;
        $nestedUnsupportedCompressionCount = 0;
        $nestedDiagnosticCount = 0;
        $recordDiagnosticCount = 0;
        $ratioDiagnosticCount = 0;
        $depthLimitReachedCount = 0;
        $depthLimitedCandidateCount = 0;
        $maxObservedStreamCompressionRatio = 0.0;
        $maxObservedPackageExpansionRatio = 0.0;
        $maxObservedTotalExpansionRatio = 0.0;

        foreach ($records as $index => $record) {
            $isNested = $index > 0;
            if (($record['status'] ?? null) === 'package') {
                $packageCount++;
                if ($isNested) {
                    $nestedPackageCount++;
                }
                $maxObservedStreamCompressionRatio = max(
                    $maxObservedStreamCompressionRatio,
                    (float) ($record['streamCompressionRatio'] ?? 0.0)
                );
                $maxObservedPackageExpansionRatio = max(
                    $maxObservedPackageExpansionRatio,
                    (float) ($record['packageExpansionRatio'] ?? 0.0)
                );
                $maxObservedTotalExpansionRatio = max(
                    $maxObservedTotalExpansionRatio,
                    (float) ($record['totalExpansionRatio'] ?? 0.0)
                );

                if ((int) ($record['ratioDiagnosticCount'] ?? 0) > 0) {
                    $ratioDiagnosticCount++;
                }
            } elseif ($isNested && ($record['status'] ?? null) === 'unreadable') {
                $nestedUnreadableCount++;
            } elseif ($isNested && ($record['status'] ?? null) === 'unsupported-compression') {
                $nestedUnsupportedCompressionCount++;
            }

            if (($record['diagnostics'] ?? []) !== []) {
                $recordDiagnosticCount++;
                if ($isNested) {
                    $nestedDiagnosticCount++;
                }
            }

            if (($record['depthLimitReached'] ?? false) === true) {
                $depthLimitReachedCount++;
            }

            $depthLimitedCandidateCount += (int) ($record['depthLimitedCandidateCount'] ?? 0);
        }

        $diagnostics = [];
        if ($ratioDiagnosticCount > 0) {
            $diagnostics[] = 'nested-archive-expansion-ratio-exceeds-threshold';
        }

        if ($nestedUnreadableCount > 0) {
            $diagnostics[] = 'nested-package-detection-failed';
        }

        if ($nestedUnsupportedCompressionCount > 0) {
            $diagnostics[] = 'nested-package-unsupported-compression';
        }

        if ($depthLimitReachedCount > 0) {
            $diagnostics[] = 'nested-package-depth-limit-reached';
        }

        return [
            'type' => 'nested-archive-bomb-policy',
            'rootKind' => $rootCandidate['kind'],
            'rootFormat' => $rootCandidate['format'],
            'compressedSize' => strlen($bytes),
            'maxDepth' => $maxDepth,
            'packageCount' => $packageCount,
            'nestedCandidateCount' => count($entries),
            'nestedPackageCount' => $nestedPackageCount,
            'nestedUnreadableCount' => $nestedUnreadableCount,
            'nestedUnsupportedCompressionCount' => $nestedUnsupportedCompressionCount,
            'nestedDiagnosticCount' => $nestedDiagnosticCount,
            'recordDiagnosticCount' => $recordDiagnosticCount,
            'ratioDiagnosticCount' => $ratioDiagnosticCount,
            'depthLimitReachedCount' => $depthLimitReachedCount,
            'depthLimitedCandidateCount' => $depthLimitedCandidateCount,
            'maxObservedStreamCompressionRatio' => $maxObservedStreamCompressionRatio,
            'maxObservedPackageExpansionRatio' => $maxObservedPackageExpansionRatio,
            'maxObservedTotalExpansionRatio' => $maxObservedTotalExpansionRatio,
            'maxStreamCompressionRatio' => $maxStreamCompressionRatio,
            'maxPackageExpansionRatio' => $maxPackageExpansionRatio,
            'maxTotalExpansionRatio' => $maxTotalExpansionRatio,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'root' => $root,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     dictionaryFrameCount:int,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectLz4DictionaryPolicy(string $bytes): array
    {
        $policy = Lz4Frame::dictionaryPolicyPreflight($bytes);

        return [
            'format' => 'lz4',
            'type' => 'lz4-dictionary-policy',
            'compressedSize' => strlen($bytes),
            'frameCount' => $policy['frameCount'],
            'dataFrameCount' => $policy['dataFrameCount'],
            'skippableFrameCount' => $policy['skippableFrameCount'],
            'dictionaryFrameCount' => $policy['dictionaryFrameCount'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'stream' => $policy,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     skippablePayloadBytes:int,
     *     maxSkippablePayloadBytes:int,
     *     overLimitSkippableFrameCount:int,
     *     firstOverLimitSkippableFrameIndex:?int,
     *     largestSkippablePayloadSize:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectLz4SkippableFramePolicy(string $bytes, int $maxSkippablePayloadBytes): array
    {
        if ($maxSkippablePayloadBytes <= 0) {
            throw new \RuntimeException('LZ4 skippable frame payload byte limit must be positive');
        }

        $policy = Lz4Frame::dictionaryPolicyPreflight($bytes);
        $frames = [];
        $skippableFrameIndex = 0;
        $dataFrameIndex = 0;
        $skippablePayloadBytes = 0;
        $overLimitSkippableFrameCount = 0;
        $firstOverLimitSkippableFrameIndex = null;
        $largestSkippablePayloadSize = 0;

        foreach ($policy['frames'] as $frameIndex => $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $payload = (string) ($frame['data'] ?? '');
                $payloadSize = strlen($payload);
                $diagnostics = [];
                $framePolicy = 'metadata-only-no-extraction';
                if ($payloadSize > $maxSkippablePayloadBytes) {
                    $diagnostics[] = 'lz4-skippable-frame-byte-limit-over-limit';
                    $framePolicy = 'review-before-conversion';
                    if ($firstOverLimitSkippableFrameIndex === null) {
                        $firstOverLimitSkippableFrameIndex = $skippableFrameIndex;
                    }
                    $overLimitSkippableFrameCount++;
                }

                $skippablePayloadBytes += $payloadSize;
                $largestSkippablePayloadSize = max($largestSkippablePayloadSize, $payloadSize);
                $frames[] = [
                    'type' => 'skippable',
                    'frameIndex' => $frameIndex,
                    'skippableFrameIndex' => $skippableFrameIndex,
                    'id' => (int) $frame['id'],
                    'payloadSize' => $payloadSize,
                    'payloadSha256' => hash('sha256', $payload),
                    'payloadPreview' => self::boundedPrintablePreview($payload, 64),
                    'frameOffset' => (int) $frame['frameOffset'],
                    'frameSize' => (int) $frame['frameSize'],
                    'policy' => $framePolicy,
                    'diagnostics' => $diagnostics,
                ];
                $skippableFrameIndex++;
                continue;
            }

            $frames[] = [
                'type' => 'frame',
                'frameIndex' => $frameIndex,
                'dataFrameIndex' => $dataFrameIndex,
                'dictionaryId' => $frame['dictionaryId'],
                'contentSize' => $frame['contentSize'],
                'blockMaxSize' => (int) $frame['blockMaxSize'],
                'blockIndependent' => (bool) $frame['blockIndependent'],
                'blockChecksum' => (bool) $frame['blockChecksum'],
                'contentChecksum' => (bool) $frame['contentChecksum'],
                'blockCount' => (int) $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => (int) $frame['compressedSize'],
                'frameOffset' => (int) $frame['frameOffset'],
                'frameSize' => (int) $frame['frameSize'],
                'policy' => (string) $frame['policy'],
                'diagnostics' => $frame['diagnostics'],
            ] + self::lz4FrameDescriptorMetadata($frame);
            $dataFrameIndex++;
        }

        $diagnostics = [];
        if ($overLimitSkippableFrameCount > 0) {
            $diagnostics[] = 'lz4-skippable-frame-byte-limit-exceeds-threshold';
        }
        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'format' => 'lz4',
            'type' => 'lz4-skippable-frame-policy',
            'compressedSize' => strlen($bytes),
            'frameCount' => $policy['frameCount'],
            'dataFrameCount' => $policy['dataFrameCount'],
            'skippableFrameCount' => $policy['skippableFrameCount'],
            'skippablePayloadBytes' => $skippablePayloadBytes,
            'maxSkippablePayloadBytes' => $maxSkippablePayloadBytes,
            'overLimitSkippableFrameCount' => $overLimitSkippableFrameCount,
            'firstOverLimitSkippableFrameIndex' => $firstOverLimitSkippableFrameIndex,
            'largestSkippablePayloadSize' => $largestSkippablePayloadSize,
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'lz4-skippable-frame-review',
            'diagnostics' => $diagnostics,
            'stream' => [
                'frameCount' => $policy['frameCount'],
                'dataFrameCount' => $policy['dataFrameCount'],
                'skippableFrameCount' => $policy['skippableFrameCount'],
                'dictionaryFrameCount' => $policy['dictionaryFrameCount'],
                'extractionPolicy' => 'metadata-only-no-extraction',
                'frames' => $frames,
            ],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     declaredContentSizeFrameCount:int,
     *     missingContentSizeFrameCount:int,
     *     mismatchedContentSizeFrameCount:int,
     *     firstMismatchedFrameIndex:?int,
     *     firstMismatchedDataFrameIndex:?int,
     *     declaredContentSizeBytes:int,
     *     decodedContentBytes:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectLz4ContentSizePolicy(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $policy = Lz4Frame::contentSizePolicyPreflight($bytes, $maxUncompressedBytes);
        $frames = [];
        foreach ($policy['frames'] as $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $payload = (string) ($frame['data'] ?? '');
                $frames[] = [
                    'type' => 'skippable',
                    'frameIndex' => (int) $frame['frameIndex'],
                    'id' => (int) $frame['id'],
                    'payloadSize' => strlen($payload),
                    'payloadSha256' => hash('sha256', $payload),
                    'payloadPreview' => self::boundedPrintablePreview($payload, 64),
                    'frameOffset' => (int) $frame['frameOffset'],
                    'frameSize' => (int) $frame['frameSize'],
                    'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                    'policy' => 'metadata-only-no-extraction',
                    'diagnostics' => [],
                ];
                continue;
            }

            $frames[] = [
                'type' => 'frame',
                'frameIndex' => (int) $frame['frameIndex'],
                'dataFrameIndex' => (int) $frame['dataFrameIndex'],
                'contentSize' => $frame['contentSize'],
                'decodedDataSize' => (int) $frame['decodedDataSize'],
                'contentSizeMatches' => $frame['contentSizeMatches'],
                'contentSizeDelta' => $frame['contentSizeDelta'],
                'dictionaryId' => $frame['dictionaryId'],
                'blockMaxSize' => (int) $frame['blockMaxSize'],
                'blockIndependent' => (bool) $frame['blockIndependent'],
                'blockChecksum' => (bool) $frame['blockChecksum'],
                'contentChecksum' => (bool) $frame['contentChecksum'],
                'blockCount' => (int) $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => (int) $frame['compressedSize'],
                'decodedDataOffset' => (int) $frame['decodedDataOffset'],
                'decodedDataEndOffset' => (int) $frame['decodedDataEndOffset'],
                'frameOffset' => (int) $frame['frameOffset'],
                'frameSize' => (int) $frame['frameSize'],
                'nextFrameOffset' => (int) $frame['nextFrameOffset'],
                'policy' => (string) $frame['policy'],
                'diagnostics' => $frame['diagnostics'],
            ] + self::lz4FrameDescriptorMetadata($frame);
        }

        $diagnostics = $policy['mismatchedContentSizeFrameCount'] > 0
            ? ['lz4-content-size-mismatch']
            : [];
        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'format' => 'lz4',
            'type' => 'lz4-content-size-policy',
            'compressedSize' => strlen($bytes),
            'frameCount' => $policy['frameCount'],
            'dataFrameCount' => $policy['dataFrameCount'],
            'skippableFrameCount' => $policy['skippableFrameCount'],
            'declaredContentSizeFrameCount' => $policy['declaredContentSizeFrameCount'],
            'missingContentSizeFrameCount' => $policy['missingContentSizeFrameCount'],
            'mismatchedContentSizeFrameCount' => $policy['mismatchedContentSizeFrameCount'],
            'firstMismatchedFrameIndex' => $policy['firstMismatchedFrameIndex'],
            'firstMismatchedDataFrameIndex' => $policy['firstMismatchedDataFrameIndex'],
            'declaredContentSizeBytes' => $policy['declaredContentSizeBytes'],
            'decodedContentBytes' => $policy['decodedContentBytes'],
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'lz4-content-size-review',
            'diagnostics' => $diagnostics,
            'stream' => [
                'frameCount' => $policy['frameCount'],
                'dataFrameCount' => $policy['dataFrameCount'],
                'skippableFrameCount' => $policy['skippableFrameCount'],
                'declaredContentSizeFrameCount' => $policy['declaredContentSizeFrameCount'],
                'missingContentSizeFrameCount' => $policy['missingContentSizeFrameCount'],
                'mismatchedContentSizeFrameCount' => $policy['mismatchedContentSizeFrameCount'],
                'extractionPolicy' => $policy['extractionPolicy'],
                'frames' => $frames,
            ],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     dictionaryFrameCount:int,
     *     blockCount:int,
     *     maxBlockPayloadBytes:int,
     *     declaredOverLimitFrameCount:int,
     *     payloadOverLimitBlockCount:int,
     *     firstOverLimitFrameIndex:?int,
     *     firstOverLimitDataFrameIndex:?int,
     *     largestDeclaredBlockMaxSize:int,
     *     largestBlockPayloadSize:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectLz4BlockSizePolicy(string $bytes, int $maxBlockPayloadBytes): array
    {
        if ($maxBlockPayloadBytes <= 0) {
            throw new \RuntimeException('LZ4 block payload byte limit must be positive');
        }

        $policy = Lz4Frame::dictionaryPolicyPreflight($bytes);
        $frames = [];
        $dataFrameIndex = 0;
        $blockCount = 0;
        $declaredOverLimitFrameCount = 0;
        $payloadOverLimitBlockCount = 0;
        $firstOverLimitFrameIndex = null;
        $firstOverLimitDataFrameIndex = null;
        $largestDeclaredBlockMaxSize = 0;
        $largestBlockPayloadSize = 0;
        $diagnostics = [];

        foreach ($policy['frames'] as $frameIndex => $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $payload = (string) ($frame['data'] ?? '');
                $frames[] = [
                    'type' => 'skippable',
                    'frameIndex' => $frameIndex,
                    'id' => (int) $frame['id'],
                    'payloadSize' => strlen($payload),
                    'payloadSha256' => hash('sha256', $payload),
                    'payloadPreview' => self::boundedPrintablePreview($payload, 64),
                    'frameOffset' => (int) $frame['frameOffset'],
                    'frameSize' => (int) $frame['frameSize'],
                    'policy' => 'metadata-only-no-extraction',
                    'diagnostics' => [],
                ];
                continue;
            }

            $blockMaxSize = (int) $frame['blockMaxSize'];
            $declaredOverLimit = $blockMaxSize > $maxBlockPayloadBytes;
            $frameDiagnostics = $frame['diagnostics'];
            if ($declaredOverLimit) {
                $frameDiagnostics[] = 'lz4-declared-block-max-size-exceeds-threshold';
                $diagnostics[] = 'lz4-declared-block-max-size-exceeds-threshold';
                $declaredOverLimitFrameCount++;
                if ($firstOverLimitFrameIndex === null) {
                    $firstOverLimitFrameIndex = $frameIndex;
                }
                if ($firstOverLimitDataFrameIndex === null) {
                    $firstOverLimitDataFrameIndex = $dataFrameIndex;
                }
            }

            $largestDeclaredBlockMaxSize = max($largestDeclaredBlockMaxSize, $blockMaxSize);
            $framePayloadOverLimitBlockCount = 0;
            $frameLargestBlockPayloadSize = 0;
            $blocks = [];
            foreach (($frame['blockPayloadSizes'] ?? []) as $blockIndex => $payloadSize) {
                $payloadSize = (int) $payloadSize;
                $blockOverLimit = $payloadSize > $maxBlockPayloadBytes;
                $blockDiagnostics = $blockOverLimit ? ['lz4-block-payload-size-exceeds-threshold'] : [];
                if ($blockOverLimit) {
                    $diagnostics[] = 'lz4-block-payload-size-exceeds-threshold';
                    $framePayloadOverLimitBlockCount++;
                    $payloadOverLimitBlockCount++;
                    if ($firstOverLimitFrameIndex === null) {
                        $firstOverLimitFrameIndex = $frameIndex;
                    }
                    if ($firstOverLimitDataFrameIndex === null) {
                        $firstOverLimitDataFrameIndex = $dataFrameIndex;
                    }
                }

                $frameLargestBlockPayloadSize = max($frameLargestBlockPayloadSize, $payloadSize);
                $largestBlockPayloadSize = max($largestBlockPayloadSize, $payloadSize);
                $blocks[] = [
                    'blockIndex' => $blockIndex,
                    'type' => $frame['blockTypes'][$blockIndex] ?? 'unknown',
                    'payloadSize' => $payloadSize,
                    'overLimit' => $blockOverLimit,
                    'policy' => $blockOverLimit ? 'review-before-conversion' : 'metadata-only-no-extraction',
                    'diagnostics' => $blockDiagnostics,
                ];
            }

            if ($framePayloadOverLimitBlockCount > 0) {
                $frameDiagnostics[] = 'lz4-block-payload-size-exceeds-threshold';
            }
            $frameDiagnostics = array_values(array_unique($frameDiagnostics));
            $blockCount += (int) $frame['blockCount'];

            $frames[] = [
                'type' => 'frame',
                'frameIndex' => $frameIndex,
                'dataFrameIndex' => $dataFrameIndex,
                'dictionaryId' => $frame['dictionaryId'],
                'contentSize' => $frame['contentSize'],
                'blockMaxSize' => $blockMaxSize,
                'declaredBlockMaxOverLimit' => $declaredOverLimit,
                'blockIndependent' => (bool) $frame['blockIndependent'],
                'blockChecksum' => (bool) $frame['blockChecksum'],
                'contentChecksum' => (bool) $frame['contentChecksum'],
                'blockCount' => (int) $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'blocks' => $blocks,
                'largestBlockPayloadSize' => $frameLargestBlockPayloadSize,
                'payloadOverLimitBlockCount' => $framePayloadOverLimitBlockCount,
                'compressedSize' => (int) $frame['compressedSize'],
                'frameOffset' => (int) $frame['frameOffset'],
                'frameSize' => (int) $frame['frameSize'],
                'policy' => $frameDiagnostics === []
                    ? 'metadata-only-no-extraction'
                    : 'review-before-conversion',
                'diagnostics' => $frameDiagnostics,
            ] + self::lz4FrameDescriptorMetadata($frame);
            $dataFrameIndex++;
        }

        $diagnostics = array_values(array_unique($diagnostics));
        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'format' => 'lz4',
            'type' => 'lz4-block-size-policy',
            'compressedSize' => strlen($bytes),
            'frameCount' => $policy['frameCount'],
            'dataFrameCount' => $policy['dataFrameCount'],
            'skippableFrameCount' => $policy['skippableFrameCount'],
            'dictionaryFrameCount' => $policy['dictionaryFrameCount'],
            'blockCount' => $blockCount,
            'maxBlockPayloadBytes' => $maxBlockPayloadBytes,
            'declaredOverLimitFrameCount' => $declaredOverLimitFrameCount,
            'payloadOverLimitBlockCount' => $payloadOverLimitBlockCount,
            'firstOverLimitFrameIndex' => $firstOverLimitFrameIndex,
            'firstOverLimitDataFrameIndex' => $firstOverLimitDataFrameIndex,
            'largestDeclaredBlockMaxSize' => $largestDeclaredBlockMaxSize,
            'largestBlockPayloadSize' => $largestBlockPayloadSize,
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'lz4-block-size-review',
            'diagnostics' => $diagnostics,
            'stream' => [
                'frameCount' => $policy['frameCount'],
                'dataFrameCount' => $policy['dataFrameCount'],
                'skippableFrameCount' => $policy['skippableFrameCount'],
                'dictionaryFrameCount' => $policy['dictionaryFrameCount'],
                'extractionPolicy' => 'metadata-only-no-extraction',
                'frames' => $frames,
            ],
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     dictionaryStreamCount:int,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZlibPresetDictionaryPolicy(string $bytes): array
    {
        $policy = DeflateStream::presetDictionaryPolicyPreflight($bytes);

        return [
            'format' => DeflateStream::FORMAT_ZLIB,
            'type' => 'zlib-preset-dictionary-policy',
            'compressedSize' => strlen($bytes),
            'dictionaryStreamCount' => $policy['dictionaryStreamCount'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'stream' => $policy,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     wrapperKind:string,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:?int,
     *     trailerSize:int,
     *     consumedBytes:int,
     *     checksumPresent:bool,
     *     checksumAlgorithm:?string,
     *     adler32:?int,
     *     adler32Hex:?string,
     *     windowSize:?int,
     *     compressionMethod:?int,
     *     compressionLevelHint:?string,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectDeflateWrapperPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'Maximum uncompressed byte count');

        $isZlib = $format === self::FORMAT_ZLIB_TAR || $format === self::FORMAT_ZLIB_ZIP;
        $isRaw = $format === self::FORMAT_RAW_DEFLATE_TAR || $format === self::FORMAT_RAW_DEFLATE_ZIP;
        if (!$isZlib && !$isRaw) {
            throw new \RuntimeException("DEFLATE wrapper policy requires a zlib or raw-deflate archive stream format: {$format}");
        }

        $stream = $isZlib
            ? self::zlibStreamInspection($bytes, $maxUncompressedBytes)
            : self::rawDeflateStreamInspection($bytes, $maxUncompressedBytes);
        $diagnostics = $isRaw ? ['raw-deflate-wrapper-integrity-missing'] : [];

        return [
            'type' => 'archive-deflate-wrapper-policy',
            'format' => $format,
            'wrapperKind' => $isZlib ? 'zlib' : 'raw-deflate',
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => (int) $stream['compressedPayloadSize'],
            'uncompressedSize' => (int) $stream['uncompressedSize'],
            'memberCount' => (int) $stream['memberCount'],
            'headerSize' => (int) $stream['headerSize'],
            'compressedPayloadOffset' => (int) $stream['compressedPayloadOffset'],
            'trailerOffset' => $stream['trailerOffset'] === null ? null : (int) $stream['trailerOffset'],
            'trailerSize' => (int) $stream['trailerSize'],
            'consumedBytes' => (int) $stream['consumedBytes'],
            'checksumPresent' => $isZlib,
            'checksumAlgorithm' => $isZlib ? 'adler32' : null,
            'adler32' => $isZlib ? (int) $stream['adler32'] : null,
            'adler32Hex' => $isZlib ? (string) $stream['adler32Hex'] : null,
            'windowSize' => $isZlib ? (int) $stream['windowSize'] : null,
            'compressionMethod' => $isZlib ? (int) $stream['compressionMethod'] : null,
            'compressionLevelHint' => $isZlib ? (string) $stream['compressionLevelHint'] : null,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === []
                ? 'metadata-only-no-extraction'
                : 'raw-deflate-wrapper-integrity-review',
            'diagnostics' => $diagnostics,
            'stream' => $stream,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     wrapperKind:string,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     memberCount:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int,
     *     checksumAlgorithm:string,
     *     adler32:int,
     *     adler32Hex:string,
     *     storedAdler32:int,
     *     storedAdler32Hex:string,
     *     computedAdler32:int,
     *     computedAdler32Hex:string,
     *     adler32Matches:bool,
     *     windowSize:int,
     *     compressionMethod:int,
     *     compressionLevelHint:string,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZlibAdler32IntegrityPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_TAR && $format !== self::FORMAT_ZLIB_ZIP) {
            throw new \RuntimeException("ZLIB Adler-32 integrity policy requires a zlib archive stream format: {$format}");
        }

        $stream = DeflateStream::adler32IntegrityPreflight($bytes, $maxUncompressedBytes);
        $diagnostics = $stream['adler32Matches'] ? [] : ['zlib-adler32-mismatch'];
        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'type' => 'archive-zlib-adler32-integrity-policy',
            'format' => $format,
            'wrapperKind' => 'zlib',
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $stream['compressedPayloadSize'],
            'uncompressedSize' => $stream['uncompressedSize'],
            'memberCount' => 1,
            'headerSize' => $stream['headerSize'],
            'compressedPayloadOffset' => $stream['compressedPayloadOffset'],
            'trailerOffset' => $stream['trailerOffset'],
            'trailerSize' => $stream['trailerSize'],
            'consumedBytes' => $stream['consumedBytes'],
            'checksumAlgorithm' => 'adler32',
            'adler32' => $stream['adler32'],
            'adler32Hex' => $stream['adler32Hex'],
            'storedAdler32' => $stream['storedAdler32'],
            'storedAdler32Hex' => $stream['storedAdler32Hex'],
            'computedAdler32' => $stream['computedAdler32'],
            'computedAdler32Hex' => $stream['computedAdler32Hex'],
            'adler32Matches' => $stream['adler32Matches'],
            'windowSize' => $stream['windowSize'],
            'compressionMethod' => $stream['compressionMethod'],
            'compressionLevelHint' => $stream['compressionLevelHint'],
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'zlib-adler32-integrity-review',
            'diagnostics' => $diagnostics,
            'stream' => [
                'type' => 'zlib-deflate',
                'memberCount' => 1,
                'compressedSize' => strlen($bytes),
                'compressedPayloadSize' => $stream['compressedPayloadSize'],
                'uncompressedSize' => $stream['uncompressedSize'],
                'headerSize' => $stream['headerSize'],
                'compressedPayloadOffset' => $stream['compressedPayloadOffset'],
                'trailerOffset' => $stream['trailerOffset'],
                'trailerSize' => $stream['trailerSize'],
                'consumedBytes' => $stream['consumedBytes'],
                'compressionMethod' => $stream['compressionMethod'],
                'windowSize' => $stream['windowSize'],
                'compressionLevelHint' => $stream['compressionLevelHint'],
                'adler32' => $stream['adler32'],
                'adler32Hex' => $stream['adler32Hex'],
                'storedAdler32' => $stream['storedAdler32'],
                'storedAdler32Hex' => $stream['storedAdler32Hex'],
                'computedAdler32' => $stream['computedAdler32'],
                'computedAdler32Hex' => $stream['computedAdler32Hex'],
                'adler32Matches' => $stream['adler32Matches'],
                'extractionPolicy' => $stream['extractionPolicy'],
                'diagnostics' => $stream['diagnostics'],
            ],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     sourceName:?string,
     *     candidateKind:?string,
     *     candidateFormat:?string,
     *     sourceNameCandidate:bool,
     *     sourceNameReason:?string,
     *     sourceNameKind:?string,
     *     sourceNameFormat:?string,
     *     sourceNameCandidateFormat:?string,
     *     signatureFormat:?string,
     *     signatureSourceNameMismatch:bool,
     *     compressedSize:int,
     *     payloadSha256:string,
     *     payloadPreviewBytes:int,
     *     payloadPreview:string,
     *     signatureMatched:bool,
     *     signatureName:?string,
     *     signatureBytesHex:?string,
     *     streamHeaderSize:?int,
     *     streamFlagsHex:?string,
     *     blockSize100k:?int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>
     * }
     */
    public static function inspectUnsupportedCompressionStreamPolicy(string $bytes, ?string $sourceName = null): array
    {
        $signature = self::unsupportedCompressionSignature($bytes);
        $nameCandidate = $sourceName === null ? null : self::unsupportedCompressionNameCandidate($sourceName);

        if ($signature === null && $nameCandidate === null) {
            throw new \RuntimeException('Unsupported archive compression policy requires a BZip2, XZ, or Zstandard stream signature or source name');
        }

        $format = $signature['format'] ?? $nameCandidate['format'];
        $candidateKind = $nameCandidate['kind'] ?? null;
        $diagnostics = [
            'archive-compression-format-unsupported',
            'archive-compression-format-' . $format . '-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ];

        $signatureSourceNameMismatch = $signature !== null
            && $nameCandidate !== null
            && $nameCandidate['format'] !== $signature['format'];

        if ($signature === null) {
            $diagnostics[] = 'archive-compression-signature-unverified';
        } elseif ($signatureSourceNameMismatch) {
            $diagnostics[] = 'archive-compression-signature-source-name-mismatch';
        }

        $sourceNameCandidateFormat = null;
        if ($nameCandidate !== null && $nameCandidate['kind'] !== null) {
            $sourceNameCandidateFormat = $nameCandidate['format'] . '-' . $nameCandidate['kind'];
        }

        return [
            'type' => 'unsupported-archive-compression-stream',
            'format' => $format,
            'sourceName' => $sourceName,
            'candidateKind' => $candidateKind,
            'candidateFormat' => $candidateKind === null ? null : $format . '-' . $candidateKind,
            'sourceNameCandidate' => $nameCandidate !== null,
            'sourceNameReason' => $nameCandidate['reason'] ?? null,
            'sourceNameKind' => $nameCandidate['kind'] ?? null,
            'sourceNameFormat' => $nameCandidate['format'] ?? null,
            'sourceNameCandidateFormat' => $sourceNameCandidateFormat,
            'signatureFormat' => $signature['format'] ?? null,
            'signatureSourceNameMismatch' => $signatureSourceNameMismatch,
            'compressedSize' => strlen($bytes),
            'payloadSha256' => hash('sha256', $bytes),
            'payloadPreviewBytes' => min(strlen($bytes), 32),
            'payloadPreview' => self::boundedPrintablePreview($bytes, 32),
            'signatureMatched' => $signature !== null,
            'signatureName' => $signature['name'] ?? null,
            'signatureBytesHex' => $signature['signatureBytesHex'] ?? null,
            'streamHeaderSize' => $signature['streamHeaderSize'] ?? null,
            'streamFlagsHex' => $signature['streamFlagsHex'] ?? null,
            'blockSize100k' => $signature['blockSize100k'] ?? null,
            'handoffPolicy' => 'review-before-conversion',
            'extractionPolicy' => 'unsupported-compression-stream-blocked',
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         typeFlag:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     metadataLayoutCount:int,
     *     metadataLayouts:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection($bytes, $format, $tarBytes, $archive, $maxUncompressedBytes);
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         typeFlag:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     metadataLayoutCount:int,
     *     metadataLayouts:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['tarBytes'],
            $candidate['archive'],
            $maxUncompressedBytes
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectTarStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_ZLIB_TAR) {
            throw new \RuntimeException("ZLIB dictionary TAR inspection requires ZLIB TAR stream format: {$format}");
        }

        $metadata = DeflateStream::inspectZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
        $tarBytes = $metadata['data'];
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $format,
            $tarBytes,
            $archive,
            $maxUncompressedBytes,
            self::zlibDictionaryStreamInspection($bytes, $metadata)
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectTarStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_LZ4_TAR) {
            throw new \RuntimeException("LZ4 dictionary TAR inspection requires LZ4 TAR stream format: {$format}");
        }

        $dictionaryMap = self::normalizeLz4ExternalDictionaries($dictionaries);
        $frames = Lz4Frame::framesWithDictionaries($bytes, $dictionaryMap, $maxUncompressedBytes);
        $tarBytes = self::decodedLz4DataFromFrames($frames);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $format,
            $tarBytes,
            $archive,
            $maxUncompressedBytes,
            self::lz4DictionaryStreamInspection($bytes, $frames, $dictionaryMap)
        );
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     blockSize:int,
     *     blockAligned:bool,
     *     hasEndMarker:bool,
     *     endMarkerOffset:?int,
     *     endMarkerEndOffset:?int,
     *     requiredEndMarkerBytes:int,
     *     trailingByteCount:int,
     *     trailingZeroByteCount:int,
     *     trailingNonZeroByteCount:int,
     *     firstTrailingNonZeroOffset:?int,
     *     firstTrailingNonZeroRelativeOffset:?int,
     *     trailingBytesSha256:?string,
     *     trailingBytesPreview:?string,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarEndMarkerPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if (!in_array($format, self::supportedTarFormats(), true)) {
            throw new \RuntimeException("TAR end-marker policy requires a TAR archive stream format: {$format}");
        }

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);

        return [
            'format' => $format,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => strlen($tarBytes),
        ] + self::tarEndMarkerPolicySummary($tarBytes) + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     type:string,
     *     headerRecordCount:int,
     *     entryCount:int,
     *     metadataRecordCount:int,
     *     unsignedChecksumRecordCount:int,
     *     signedChecksumRecordCount:int,
     *     ambiguousChecksumRecordCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     entries:list<array{
     *         name:string,
     *         role:string,
     *         typeFlag:string,
     *         nameSource:string,
     *         linkTarget:?string,
     *         linkTargetSource:?string,
     *         linkTargetSize:?int,
     *         headerOffset:int,
     *         dataOffset:int,
     *         recordEndOffset:int,
     *         payloadSize:int,
     *         headerPayloadSize:int,
     *         checksumField:string,
     *         storedChecksum:int,
     *         storedChecksumOctal:string,
     *         unsignedChecksum:int,
     *         signedChecksum:int,
     *         matchesUnsigned:bool,
     *         matchesSigned:bool,
     *         checksumKind:string,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarChecksumPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::checksumPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     linkEntryCount:int,
     *     hardLinkCount:int,
     *     symbolicLinkCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         linkType:string,
     *         linkTarget:string,
     *         linkTargetSource:string,
     *         targetEntryExists:bool,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarLinkPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::linkPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     specialFileEntryCount:int,
     *     characterDeviceCount:int,
     *     blockDeviceCount:int,
     *     fifoCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         specialType:string,
     *         typeFlag:string,
     *         deviceMajor:?int,
     *         deviceMinor:?int,
     *         deviceNumberSource:string,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarSpecialFilePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::specialFilePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     sparseEntryCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         sparseType:string,
     *         sparseHeaderFamilies:list<string>,
     *         sparseHeaderKeys:list<string>,
     *         realSize:?int,
     *         sparseMapSource:?string,
     *         sparseMapSegments:list<array{offset:int, length:int, endOffset:int}>,
     *         sparseMapSegmentCount:int,
     *         sparseMapPayloadBytes:int,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarSparsePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::sparsePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     multiVolumeEntryCount:int,
     *     typeflagEntryCount:int,
     *     paxMetadataEntryCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         multiVolumeType:string,
     *         volumeHeaderFamilies:list<string>,
     *         volumeHeaderKeys:list<string>,
     *         continuationOffset:?int,
     *         continuationOffsetSource:?string,
     *         originalName:?string,
     *         declaredVolumeSize:?int,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarMultiVolumePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::multiVolumePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     incrementalEntryCount:int,
     *     typeflagEntryCount:int,
     *     paxMetadataEntryCount:int,
     *     dumpdirRecordCount:int,
     *     deletedRecordCount:int,
     *     directoryRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         incrementalType:string,
     *         incrementalHeaderFamilies:list<string>,
     *         incrementalHeaderKeys:list<string>,
     *         dumpdirRecordCount:int,
     *         deletedRecordCount:int,
     *         directoryRecordCount:int,
     *         dumpdirRecords:list<array{source:string, marker:string, name:string, action:string, raw:string}>,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarIncrementalSnapshotPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::incrementalSnapshotPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     paxEntryCount:int,
     *     duplicatePaxEntryCount:int,
     *     duplicateKeywordCount:int,
     *     duplicateRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         paxEntryName:string,
     *         paxType:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         payloadSize:int,
     *         recordCount:int,
     *         duplicateKeywordCount:int,
     *         duplicateRecordCount:int,
     *         duplicateKeywords:list<string>,
     *         duplicateRecords:list<array{keyword:string, occurrences:int, values:list<string>, firstValue:string, duplicateValues:list<string>}>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarPaxDuplicateKeywordPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::paxDuplicateKeywordPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     filesystemMetadataEntryCount:int,
     *     metadataRecordCount:int,
     *     extendedAttributeRecordCount:int,
     *     accessControlListRecordCount:int,
     *     fileFlagRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         metadataRecordCount:int,
     *         extendedAttributeRecordCount:int,
     *         accessControlListRecordCount:int,
     *         fileFlagRecordCount:int,
     *         metadataKeys:list<string>,
     *         records:list<array{keyword:string, category:string, name:string, source:string, value:string}>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarFilesystemMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::paxFilesystemMetadataPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     type:string,
     *     entryCount:int,
     *     scannedEntryCount:int,
     *     headerRecordCount:int,
     *     metadataRecordCount:int,
     *     entryNames:list<string>,
     *     hasDuplicateEntryNames:bool,
     *     duplicateEntryNameGroupCount:int,
     *     duplicateEntryNameEntryCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     diagnostics:list<string>,
     *     duplicateEntryNameGroups:list<array{name:string, count:int, entryIndexes:list<int>, nameSources:list<string>, roles:list<string>, headerOffsets:list<int>}>,
     *     duplicateEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarDuplicateEntryNamePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::duplicateEntryNamePreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     type:string,
     *     entryCount:int,
     *     attributeEntryCount:int,
     *     modeFlagEntryCount:int,
     *     ownerMetadataEntryCount:int,
     *     nonRootOwnerEntryCount:int,
     *     regularExecutableEntryCount:int,
     *     worldWritableEntryCount:int,
     *     setuidEntryCount:int,
     *     setgidEntryCount:int,
     *     stickyEntryCount:int,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     entries:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarFilesystemAttributePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::filesystemAttributePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     type:string,
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     collisionGroups:list<array{caseFoldKey:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarCaseInsensitiveNamePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);
        $policy = $archive->caseInsensitiveNamePreflight();

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     entryLayouts:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection($bytes, $format, $zipBytes, $package, $maxUncompressedBytes);
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipEncryptionPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::encryptionPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipLocalHeaderSpanPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::localHeaderSpanPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipLocalHeaderOrderPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);
        $policy = $package->localHeaderOrderPreflight();
        $diagnostics = ($policy['hasCentralDirectoryOrderMismatch'] ?? false) === true
            ? ['central-directory-local-header-order-mismatch']
            : [];

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-local-header-order-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'local-header-order-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipDuplicateEntryNamePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::centralDirectoryInventoryPreflight($zipBytes);
        $diagnostics = array_values(array_unique($policy['issues'] ?? []));

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-duplicate-entry-name-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'zip-duplicate-entry-name-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipPackagePrefixPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::packagePrefixPreflight($zipBytes);
        $diagnostics = $policy['issues'];

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-package-prefix-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'package-prefix-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipLocalHeaderMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::localHeaderMetadataPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipLocalHeaderNamePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::localHeaderNamePreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZip64EndOfCentralDirectoryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipEndOfCentralDirectoryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $trailing = ZipPackage::endOfCentralDirectoryTrailingBytesPreflight($zipBytes);
        $offset = ZipPackage::endOfCentralDirectoryOffsetPreflight($zipBytes);
        $issues = array_values(array_unique(array_merge($trailing['issues'] ?? [], $offset['issues'] ?? [])));

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-end-of-central-directory-policy',
            'archiveLength' => strlen($zipBytes),
            'hasEndOfCentralDirectoryCandidate' => $trailing['hasEndOfCentralDirectoryCandidate'],
            'hasEndOfCentralDirectoryRecord' => $offset['hasEndOfCentralDirectoryRecord'],
            'eocdOffset' => $offset['eocdOffset'] ?? $trailing['eocdOffset'],
            'declaredArchiveEndOffset' => $offset['declaredArchiveEndOffset'] ?? $trailing['declaredArchiveEndOffset'],
            'declaredPackageCommentLength' => $offset['declaredPackageCommentLength']
                ?? $trailing['declaredPackageCommentLength'],
            'availablePackageCommentBytes' => $trailing['availablePackageCommentBytes'],
            'trailingByteCount' => $trailing['trailingByteCount'],
            'hasTrailingBytes' => $trailing['hasTrailingBytes'],
            'hasTruncatedComment' => $trailing['hasTruncatedComment'],
            'diskNumber' => $offset['diskNumber'],
            'centralDirectoryDisk' => $offset['centralDirectoryDisk'],
            'diskEntryCount' => $offset['diskEntryCount'],
            'totalEntryCount' => $offset['totalEntryCount'] ?? $trailing['totalEntryCount'],
            'centralDirectoryOffset' => $offset['centralDirectoryOffset'] ?? $trailing['centralDirectoryOffset'],
            'centralDirectorySize' => $offset['centralDirectorySize'] ?? $trailing['centralDirectorySize'],
            'centralDirectoryEnd' => $offset['centralDirectoryEnd'] ?? $trailing['centralDirectoryEnd'],
            'centralDirectoryRangeAvailable' => $offset['centralDirectoryRangeAvailable'],
            'centralDirectoryRangeBeforeEocd' => $offset['centralDirectoryRangeBeforeEocd'],
            'centralDirectoryEndMatchesEocdOffset' => $offset['centralDirectoryEndMatchesEocdOffset'],
            'centralDirectoryGapExplainedBySignature' => $offset['centralDirectoryGapExplainedBySignature'],
            'centralDirectoryStartSignature' => $offset['centralDirectoryStartSignature'],
            'centralDirectoryOffsetLocation' => $offset['centralDirectoryOffsetLocation'],
            'centralDirectoryRangeStartsWithCentralHeader' => $offset['centralDirectoryRangeStartsWithCentralHeader'],
            'requiresZip64' => $offset['requiresZip64'],
            'isSupportedByBoundedReader' => $trailing['isSupportedByBoundedReader']
                && $offset['isSupportedByBoundedReader'],
            'handoffPolicy' => $issues === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $issues === [] ? 'metadata-only-no-extraction' : 'zip-eocd-review',
            'issues' => $issues,
            'diagnostics' => $issues,
            'trailingIssues' => $trailing['issues'],
            'offsetIssues' => $offset['issues'],
            'endOfCentralDirectoryTrailingBytes' => $trailing,
            'endOfCentralDirectoryOffset' => $offset,
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipUnicodeExtraFieldPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::unicodeExtraFieldPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZip64ExtraFieldPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::zip64ExtraFieldPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipCentralDirectoryInventoryPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::centralDirectoryInventoryPreflight($zipBytes);
        $diagnostics = $policy['issues'];

        if (($policy['hasCentralDirectorySignature'] ?? false) === true) {
            $diagnostics[] = 'central-directory-signature-unverified';
        }

        $diagnostics = array_values(array_unique($diagnostics));
        $signature = is_array($policy['centralDirectorySignature'] ?? null)
            ? $policy['centralDirectorySignature']
            : null;

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-central-directory-inventory-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'central-directory-inventory-review',
            'diagnostics' => $diagnostics,
            'centralDirectorySignatureVerification' => $signature === null
                ? 'not-present'
                : 'not-performed-native-bounded-reader',
            'centralDirectorySignatureLength' => $signature === null ? 0 : (int) $signature['dataLength'],
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipArchiveExtraDataRecordPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::archiveExtraDataRecordPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipDataDescriptorIntegrityPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::dataDescriptorIntegrityPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipCompressionMethodPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::compressionMethodPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipModificationTimePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);
        $policy = $package->modificationTimePreflight();
        $diagnostics = ($policy['invalidDosTimestampEntryCount'] ?? 0) === 0
            ? []
            : ['invalid-modification-times'];

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-modification-time-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'zip-modification-time-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipGeneralPurposeFlagPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);
        $policy = $package->generalPurposeFlagPreflight();

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipPlatformMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::platformMetadataPolicyPreflight($zipBytes);
        $diagnostics = $policy['issues'];

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-platform-metadata-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'zip-platform-metadata-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipCommentPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::commentPolicyPreflight($zipBytes);
        $diagnostics = $policy['issues'];

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
            'type' => 'zip-comment-policy',
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'zip-comment-review',
            'diagnostics' => $diagnostics,
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipCreatorHostSystemPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::creatorHostSystemPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipExternalAttributePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::externalAttributePolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipDataDescriptorPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);
        $policy = $package->dataDescriptorPreflight();

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipSplitArchivePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::splitArchivePreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     entryLayouts:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): array {
        $candidate = self::detectZipCandidate($bytes, $maxUncompressedBytes);

        return self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectZipStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_ZIP) {
            throw new \RuntimeException("ZLIB dictionary ZIP inspection requires ZLIB ZIP stream format: {$format}");
        }

        $metadata = DeflateStream::inspectZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
        $zipBytes = $metadata['data'];
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection(
            $bytes,
            $format,
            $zipBytes,
            $package,
            $maxUncompressedBytes,
            self::zlibDictionaryStreamInspection($bytes, $metadata)
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectZipStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_ZIP) {
            throw new \RuntimeException("LZ4 dictionary ZIP inspection requires LZ4 ZIP stream format: {$format}");
        }

        $dictionaryMap = self::normalizeLz4ExternalDictionaries($dictionaries);
        $frames = Lz4Frame::framesWithDictionaries($bytes, $dictionaryMap, $maxUncompressedBytes);
        $zipBytes = self::decodedLz4DataFromFrames($frames);
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection(
            $bytes,
            $format,
            $zipBytes,
            $package,
            $maxUncompressedBytes,
            self::lz4DictionaryStreamInspection($bytes, $frames, $dictionaryMap)
        );
    }

    public static function decodeTarBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_TAR => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain TAR stream'),
            self::FORMAT_GZIP_TAR => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeTarBytesWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_TAR) {
            throw new \RuntimeException("LZ4 dictionary decoding requires LZ4 TAR stream format: {$format}");
        }

        return Lz4Frame::decodeWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeTarBytesWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_TAR) {
            throw new \RuntimeException("ZLIB dictionary decoding requires ZLIB TAR stream format: {$format}");
        }

        return DeflateStream::decodeZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    public static function decodeZipBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_ZIP => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain ZIP package stream'),
            self::FORMAT_GZIP_ZIP => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_ZIP => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeZipBytesWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_ZIP) {
            throw new \RuntimeException("LZ4 dictionary decoding requires LZ4 ZIP stream format: {$format}");
        }

        return Lz4Frame::decodeWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeZipBytesWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_ZIP) {
            throw new \RuntimeException("ZLIB dictionary decoding requires ZLIB ZIP stream format: {$format}");
        }

        return DeflateStream::decodeZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    private static function boundedPlainBytes(string $bytes, ?int $maxUncompressedBytes, string $label): string
    {
        if ($maxUncompressedBytes !== null && strlen($bytes) > $maxUncompressedBytes) {
            throw new \RuntimeException("{$label} exceeds the configured uncompressed byte limit");
        }

        return $bytes;
    }

    /**
     * @return array{format:string, tarBytes:string, archive:TarArchive}
     */
    private static function detectTarCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateTarFormats($bytes) as $format) {
            try {
                $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'tarBytes' => $tarBytes,
                    'archive' => TarArchive::fromString($tarBytes, $maxUnpackedBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched TAR candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as TAR' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{format:string, zipBytes:string, package:ZipPackage}
     */
    private static function detectZipCandidate(
        string $bytes,
        ?int $maxUncompressedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateZipFormats($bytes) as $format) {
            try {
                $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'zipBytes' => $zipBytes,
                    'package' => ZipPackage::fromString($zipBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched ZIP candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array<string, mixed>
     */
    private static function detectPackageCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        $matches = [];
        $errors = [];

        try {
            $tar = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + $tar;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_TAR] = $exception->getMessage();
        }

        try {
            $zip = self::detectZipCandidate($bytes, $maxUncompressedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + $zip;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_ZIP] = $exception->getMessage();
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $descriptions = implode(', ', array_map(
                static fn (array $match): string => $match['kind'] . '/' . $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive package stream; matched candidates: {$descriptions}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive package stream as TAR or ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         typeFlag:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    private static function tarStreamInspection(
        string $bytes,
        string $format,
        string $tarBytes,
        TarArchive $archive,
        ?int $maxUncompressedBytes,
        ?array $streamInspection = null
    ): array {
        $stream = $streamInspection ?? self::streamInspection($bytes, $format, $maxUncompressedBytes);
        $entryNames = $archive->names();
        $endMarkerOffset = self::tarEndMarkerOffset($tarBytes);
        $entryLayouts = self::tarEntryLayouts($archive, $stream);
        $metadataLayouts = self::tarMetadataLayouts($tarBytes, $stream);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'archive' => $archive,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'regularFileCount' => count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isRegularFile()
            )),
            'directoryCount' => count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isDirectory()
            )),
            'uncompressedSize' => strlen($tarBytes),
            'unpackedSize' => self::archiveUnpackedSize($archive),
            'endMarkerOffset' => $endMarkerOffset,
            'trailingZeroBytes' => strlen($tarBytes) - $endMarkerOffset,
            'entryLayouts' => $entryLayouts,
            'metadataLayoutCount' => count($metadataLayouts),
            'metadataLayouts' => $metadataLayouts,
            'stream' => $stream,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     entryLayouts:list<array<string, mixed>>,
     *     stream:array<string, mixed>
     * }
     */
    private static function zipStreamInspection(
        string $bytes,
        string $format,
        string $zipBytes,
        ZipPackage $package,
        ?int $maxUncompressedBytes,
        ?array $streamInspection = null
    ): array {
        $stream = $streamInspection ?? self::streamInspection($bytes, $format, $maxUncompressedBytes);
        $entryNames = $package->names();

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'package' => $package,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'packageByteSize' => strlen($zipBytes),
            'entryUncompressedSize' => self::zipPackageUncompressedSize($package),
            'entryLayouts' => self::zipEntryLayouts($package, $stream),
            'stream' => $stream,
        ];
    }

    /**
     * @return list<array{
     *     name:string,
     *     type:string,
     *     centralDirectoryIndex:int,
     *     localHeaderOrder:int,
     *     compressionMethod:int,
     *     generalPurposeFlags:int,
     *     crc32:int,
     *     crc32Hex:string,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     localHeaderOffset:int,
     *     localHeaderLength:int,
     *     localNameLength:int,
     *     localExtraFieldLength:int,
     *     compressedDataOffset:int,
     *     compressedDataEndOffset:int,
     *     usesDataDescriptor:bool,
     *     descriptorOffset:?int,
     *     descriptorLength:?int,
     *     recordEndOffset:int,
     *     nextOffset:int,
     *     isContiguousWithNext:bool,
     *     recordSize:int,
     *     decodedSourceSegmentCount:int,
     *     decodedSourceSegments:list<array{
     *         sourceType:string,
     *         sourceIndex:int,
     *         sourceLabel:?string,
     *         sourceDecodedOffset:int,
     *         sourceDecodedEndOffset:int,
     *         entryRecordOffset:int,
     *         entryRecordEndOffset:int
     *     }>
     * }>
     */
    private static function zipEntryLayouts(ZipPackage $package, array $streamInspection): array
    {
        $decodedSourceSegments = self::decodedStreamSourceSegments(
            $streamInspection,
            strlen($package->bytes())
        );
        $entriesByName = [];
        $centralIndexByName = [];
        foreach ($package->entries() as $index => $entry) {
            $entriesByName[$entry->name] = $entry;
            $centralIndexByName[$entry->name] = $index;
        }

        $layouts = [];
        foreach ($package->localHeaderPreflight()['entries'] as $localHeaderOrder => $layout) {
            $name = $layout['name'];
            $entry = $entriesByName[$name] ?? null;
            if (!$entry instanceof ZipPackageEntry) {
                throw new \RuntimeException("ZIP local header entry {$name} is missing from the central directory");
            }

            $recordStart = (int) $layout['localHeaderOffset'];
            $recordEnd = (int) $layout['recordEnd'];
            $entrySourceSegments = self::entryDecodedSourceSegments(
                $recordStart,
                $recordEnd,
                $decodedSourceSegments
            );

            $layouts[] = [
                'name' => $name,
                'type' => $entry->isDirectory() ? 'directory' : 'file',
                'centralDirectoryIndex' => (int) $centralIndexByName[$name],
                'localHeaderOrder' => (int) $localHeaderOrder,
                'compressionMethod' => (int) $layout['compressionMethod'],
                'generalPurposeFlags' => (int) $layout['generalPurposeFlags'],
                'crc32' => $entry->crc32,
                'crc32Hex' => $entry->crc32Hex(),
                'compressedSize' => (int) $layout['compressedSize'],
                'uncompressedSize' => $entry->uncompressedSize,
                'localHeaderOffset' => $recordStart,
                'localHeaderLength' => (int) $layout['localHeaderLength'],
                'localNameLength' => (int) $layout['localNameLength'],
                'localExtraFieldLength' => (int) $layout['localExtraFieldLength'],
                'compressedDataOffset' => (int) $layout['dataStart'],
                'compressedDataEndOffset' => (int) $layout['compressedDataEnd'],
                'usesDataDescriptor' => (bool) $layout['usesDataDescriptor'],
                'descriptorOffset' => $layout['descriptorOffset'],
                'descriptorLength' => $layout['descriptorLength'],
                'recordEndOffset' => $recordEnd,
                'nextOffset' => (int) $layout['nextOffset'],
                'isContiguousWithNext' => (bool) $layout['isContiguousWithNext'],
                'recordSize' => $recordEnd - $recordStart,
                'decodedSourceSegmentCount' => count($entrySourceSegments),
                'decodedSourceSegments' => $entrySourceSegments,
            ];
        }

        return $layouts;
    }

    private static function archiveUnpackedSize(TarArchive $archive): int
    {
        $size = 0;
        foreach ($archive->entries() as $entry) {
            if ($entry->isRegularFile()) {
                $size += $entry->size;
            }
        }

        return $size;
    }

    /**
     * @return array{status:string, error:?string, entryCount:int, entryNames:list<string>}
     */
    private static function gzipMemberPackageSummary(string $bytes, string $expectedKind, ?int $maxUnpackedBytes): array
    {
        return self::archivePackageBoundarySummary(
            $bytes,
            $expectedKind,
            $maxUnpackedBytes,
            'gzip member package-boundary'
        );
    }

    /**
     * @return array{status:string, error:?string, entryCount:int, entryNames:list<string>}
     */
    private static function archivePackageBoundarySummary(
        string $bytes,
        string $expectedKind,
        ?int $maxUnpackedBytes,
        string $label
    ): array
    {
        try {
            if ($expectedKind === self::PACKAGE_KIND_TAR) {
                $archive = TarArchive::fromString($bytes, $maxUnpackedBytes);
                $entryNames = $archive->names();

                return [
                    'status' => 'package',
                    'error' => null,
                    'entryCount' => count($entryNames),
                    'entryNames' => $entryNames,
                ];
            }

            if ($expectedKind === self::PACKAGE_KIND_ZIP) {
                $package = ZipPackage::fromString($bytes);
                $entryNames = $package->names();

                return [
                    'status' => 'package',
                    'error' => null,
                    'entryCount' => count($entryNames),
                    'entryNames' => $entryNames,
                ];
            }
        } catch (\RuntimeException $exception) {
            return [
                'status' => 'invalid',
                'error' => $exception->getMessage(),
                'entryCount' => 0,
                'entryNames' => [],
            ];
        }

        throw new \RuntimeException("Unsupported {$label} kind: {$expectedKind}");
    }

    private static function gzipMemberReviewLabel(array $member): ?string
    {
        if (is_string($member['filenameText'] ?? null) && $member['filenameText'] !== '') {
            return $member['filenameText'];
        }

        if (is_string($member['filename'] ?? null) && $member['filename'] !== '') {
            return $member['filename'];
        }

        return null;
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function gzipTarBoundarySplitEntryRecord(array $layout, int $boundaryOffset): ?array
    {
        $headerOffset = (int) ($layout['headerOffset'] ?? 0);
        $recordSize = (int) ($layout['recordSize'] ?? 0);
        $recordEndOffset = $headerOffset + $recordSize;
        if ($recordSize <= 0 || $boundaryOffset <= $headerOffset || $boundaryOffset >= $recordEndOffset) {
            return null;
        }

        return [
            'recordKind' => 'entry',
            'name' => (string) ($layout['name'] ?? ''),
            'role' => (string) ($layout['type'] ?? ''),
            'headerOffset' => $headerOffset,
            'dataOffset' => (int) ($layout['dataOffset'] ?? ($headerOffset + 512)),
            'dataEndOffset' => (int) ($layout['dataEndOffset'] ?? ($headerOffset + 512)),
            'recordEndOffset' => $recordEndOffset,
            'recordSize' => $recordSize,
            'splitOffsetInRecord' => $boundaryOffset - $headerOffset,
            'policy' => 'review-before-conversion',
            'diagnostics' => ['gzip-member-boundary-splits-tar-entry-record'],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function gzipTarBoundarySplitMetadataRecord(array $layout, int $boundaryOffset): ?array
    {
        $headerOffset = (int) ($layout['headerOffset'] ?? 0);
        $recordEndOffset = (int) ($layout['recordEndOffset'] ?? $headerOffset);
        if ($boundaryOffset <= $headerOffset || $boundaryOffset >= $recordEndOffset) {
            return null;
        }

        return [
            'recordKind' => 'metadata',
            'name' => (string) ($layout['name'] ?? ''),
            'role' => (string) ($layout['role'] ?? ''),
            'metadataKind' => (string) ($layout['metadataKind'] ?? ''),
            'paxHeaderKeys' => array_values($layout['paxHeaderKeys'] ?? []),
            'headerOffset' => $headerOffset,
            'dataOffset' => (int) ($layout['dataOffset'] ?? ($headerOffset + 512)),
            'dataEndOffset' => (int) ($layout['dataEndOffset'] ?? ($headerOffset + 512)),
            'recordEndOffset' => $recordEndOffset,
            'recordSize' => $recordEndOffset - $headerOffset,
            'splitOffsetInRecord' => $boundaryOffset - $headerOffset,
            'policy' => 'review-before-conversion',
            'diagnostics' => ['gzip-member-boundary-splits-tar-metadata-record'],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function lz4TarBoundarySplitEntryRecord(array $layout, int $boundaryOffset): ?array
    {
        $headerOffset = (int) ($layout['headerOffset'] ?? 0);
        $recordSize = (int) ($layout['recordSize'] ?? 0);
        $recordEndOffset = $headerOffset + $recordSize;
        if ($recordSize <= 0 || $boundaryOffset <= $headerOffset || $boundaryOffset >= $recordEndOffset) {
            return null;
        }

        return [
            'recordKind' => 'entry',
            'name' => (string) ($layout['name'] ?? ''),
            'role' => (string) ($layout['type'] ?? ''),
            'headerOffset' => $headerOffset,
            'dataOffset' => (int) ($layout['dataOffset'] ?? ($headerOffset + 512)),
            'dataEndOffset' => (int) ($layout['dataEndOffset'] ?? ($headerOffset + 512)),
            'recordEndOffset' => $recordEndOffset,
            'recordSize' => $recordSize,
            'splitOffsetInRecord' => $boundaryOffset - $headerOffset,
            'policy' => 'review-before-conversion',
            'diagnostics' => ['lz4-frame-boundary-splits-tar-entry-record'],
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function lz4TarBoundarySplitMetadataRecord(array $layout, int $boundaryOffset): ?array
    {
        $headerOffset = (int) ($layout['headerOffset'] ?? 0);
        $recordEndOffset = (int) ($layout['recordEndOffset'] ?? $headerOffset);
        if ($boundaryOffset <= $headerOffset || $boundaryOffset >= $recordEndOffset) {
            return null;
        }

        return [
            'recordKind' => 'metadata',
            'name' => (string) ($layout['name'] ?? ''),
            'role' => (string) ($layout['role'] ?? ''),
            'metadataKind' => (string) ($layout['metadataKind'] ?? ''),
            'paxHeaderKeys' => array_values($layout['paxHeaderKeys'] ?? []),
            'headerOffset' => $headerOffset,
            'dataOffset' => (int) ($layout['dataOffset'] ?? ($headerOffset + 512)),
            'dataEndOffset' => (int) ($layout['dataEndOffset'] ?? ($headerOffset + 512)),
            'recordEndOffset' => $recordEndOffset,
            'recordSize' => $recordEndOffset - $headerOffset,
            'splitOffsetInRecord' => $boundaryOffset - $headerOffset,
            'policy' => 'review-before-conversion',
            'diagnostics' => ['lz4-frame-boundary-splits-tar-metadata-record'],
        ];
    }

    /**
     * @return list<array{
     *     name:string,
     *     type:string,
     *     size:int,
     *     mode:int,
     *     modifiedAt:int,
     *     accessedAt:?int,
     *     changedAt:?int,
     *     createdAt:?int,
     *     uid:int,
     *     gid:int,
     *     userName:string,
     *     groupName:string,
     *     paxHeaderCount:int,
     *     paxHeaderKeys:list<string>,
     *     paxGlobalHeaderCount:int,
     *     paxGlobalHeaderKeys:list<string>,
     *     paxLocalHeaderCount:int,
     *     paxLocalHeaderKeys:list<string>,
     *     paxDeletedHeaderKeys:list<string>,
     *     nameSource:string,
     *     gnuLongName:?string,
     *     headerOffset:int,
     *     dataOffset:int,
     *     dataEndOffset:int,
     *     paddedDataSize:int,
     *     recordSize:int,
     *     decodedSourceSegmentCount:int,
     *     decodedSourceSegments:list<array{
     *         sourceType:string,
     *         sourceIndex:int,
     *         sourceLabel:?string,
     *         sourceDecodedOffset:int,
     *         sourceDecodedEndOffset:int,
     *         entryRecordOffset:int,
     *         entryRecordEndOffset:int
     *     }>
     * }>
     */
    private static function tarEntryLayouts(TarArchive $archive, array $streamInspection): array
    {
        $layouts = [];
        $decodedSourceSegments = self::decodedStreamSourceSegments(
            $streamInspection,
            strlen($archive->bytes())
        );

        foreach ($archive->entries() as $entry) {
            $headerOffset = $entry->dataOffset - 512;
            $paddedDataSize = self::paddedTarPayloadSize($entry->size);
            $recordSize = 512 + $paddedDataSize;
            $paxHeaderKeys = array_keys($entry->paxHeaders);
            sort($paxHeaderKeys);
            $paxGlobalHeaderKeys = array_keys($entry->globalPaxHeaders);
            sort($paxGlobalHeaderKeys);
            $paxLocalHeaderKeys = array_keys($entry->localPaxHeaders);
            sort($paxLocalHeaderKeys);
            $entrySourceSegments = self::entryDecodedSourceSegments(
                $headerOffset,
                $headerOffset + $recordSize,
                $decodedSourceSegments
            );

            $layouts[] = [
                'name' => $entry->name,
                'type' => $entry->type,
                'typeFlag' => $entry->typeFlag,
                'size' => $entry->size,
                'mode' => $entry->mode,
                'modifiedAt' => $entry->modifiedAt,
                'accessedAt' => $entry->accessedAt,
                'changedAt' => $entry->changedAt,
                'createdAt' => $entry->createdAt,
                'uid' => $entry->uid,
                'gid' => $entry->gid,
                'userName' => $entry->userName,
                'groupName' => $entry->groupName,
                'paxHeaderCount' => count($entry->paxHeaders),
                'paxHeaderKeys' => $paxHeaderKeys,
                'paxGlobalHeaderCount' => count($entry->globalPaxHeaders),
                'paxGlobalHeaderKeys' => $paxGlobalHeaderKeys,
                'paxLocalHeaderCount' => count($entry->localPaxHeaders),
                'paxLocalHeaderKeys' => $paxLocalHeaderKeys,
                'paxDeletedHeaderKeys' => $entry->deletedPaxHeaderKeys,
                'nameSource' => $entry->nameSource,
                'gnuLongName' => $entry->gnuLongName,
                'headerOffset' => $headerOffset,
                'dataOffset' => $entry->dataOffset,
                'dataEndOffset' => $entry->dataOffset + $entry->size,
                'paddedDataSize' => $paddedDataSize,
                'recordSize' => $recordSize,
                'decodedSourceSegmentCount' => count($entrySourceSegments),
                'decodedSourceSegments' => $entrySourceSegments,
            ];
        }

        return $layouts;
    }

    /**
     * @return list<array{
     *     name:string,
     *     role:string,
     *     typeFlag:string,
     *     metadataKind:string,
     *     metadataValueSize:?int,
     *     paxHeaderCount:int,
     *     paxHeaderKeys:list<string>,
     *     headerOffset:int,
     *     dataOffset:int,
     *     dataEndOffset:int,
     *     recordEndOffset:int,
     *     payloadSize:int,
     *     headerPayloadSize:int,
     *     paddedDataSize:int,
     *     recordSize:int,
     *     policy:string,
     *     diagnostics:list<string>,
     *     decodedSourceSegmentCount:int,
     *     decodedSourceSegments:list<array{
     *         sourceType:string,
     *         sourceIndex:int,
     *         sourceLabel:?string,
     *         sourceDecodedOffset:int,
     *         sourceDecodedEndOffset:int,
     *         recordOffset:int,
     *         recordEndOffset:int
     *     }>
     * }>
     */
    private static function tarMetadataLayouts(string $tarBytes, array $streamInspection): array
    {
        $decodedSourceSegments = self::decodedStreamSourceSegments($streamInspection, strlen($tarBytes));
        $checksumPolicy = TarArchive::checksumPolicyPreflight($tarBytes);
        $layouts = [];

        foreach ($checksumPolicy['entries'] as $record) {
            if (!is_array($record) || !is_string($record['metadataKind'] ?? null)) {
                continue;
            }

            $headerOffset = (int) $record['headerOffset'];
            $dataOffset = (int) $record['dataOffset'];
            $recordEndOffset = (int) $record['recordEndOffset'];
            $payloadSize = (int) $record['payloadSize'];
            $sourceSegments = self::recordDecodedSourceSegments(
                $headerOffset,
                $recordEndOffset,
                $decodedSourceSegments
            );

            $layouts[] = [
                'name' => (string) $record['name'],
                'role' => (string) $record['role'],
                'typeFlag' => (string) $record['typeFlag'],
                'metadataKind' => (string) $record['metadataKind'],
                'metadataValueSize' => $record['metadataValueSize'],
                'paxHeaderCount' => (int) $record['paxHeaderCount'],
                'paxHeaderKeys' => array_values($record['paxHeaderKeys']),
                'headerOffset' => $headerOffset,
                'dataOffset' => $dataOffset,
                'dataEndOffset' => $dataOffset + $payloadSize,
                'recordEndOffset' => $recordEndOffset,
                'payloadSize' => $payloadSize,
                'headerPayloadSize' => (int) $record['headerPayloadSize'],
                'paddedDataSize' => $recordEndOffset - $dataOffset,
                'recordSize' => $recordEndOffset - $headerOffset,
                'policy' => (string) $record['policy'],
                'diagnostics' => array_values($record['diagnostics']),
                'decodedSourceSegmentCount' => count($sourceSegments),
                'decodedSourceSegments' => $sourceSegments,
            ];
        }

        return $layouts;
    }

    /**
     * @return list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }>
     */
    private static function decodedStreamSourceSegments(array $streamInspection, int $decodedSize): array
    {
        $type = (string) ($streamInspection['type'] ?? '');
        if ($type === 'gzip') {
            $segments = [];
            foreach (($streamInspection['members'] ?? []) as $index => $member) {
                if (!is_array($member)) {
                    continue;
                }

                $label = null;
                if (is_string($member['filenameText'] ?? null)) {
                    $label = $member['filenameText'];
                } elseif (is_string($member['filename'] ?? null)) {
                    $label = $member['filename'];
                }

                $segments[] = [
                    'sourceType' => 'gzip-member',
                    'sourceIndex' => (int) $index,
                    'sourceLabel' => $label,
                    'decodedDataOffset' => (int) ($member['decodedDataOffset'] ?? 0),
                    'decodedDataEndOffset' => (int) ($member['decodedDataEndOffset'] ?? 0),
                ];
            }

            return $segments;
        }

        if ($type === 'lz4') {
            $segments = [];
            $dataFrameIndex = 0;
            foreach (($streamInspection['frames'] ?? []) as $frame) {
                if (!is_array($frame) || ($frame['type'] ?? null) !== 'frame') {
                    continue;
                }

                $segments[] = [
                    'sourceType' => 'lz4-frame',
                    'sourceIndex' => $dataFrameIndex,
                    'sourceLabel' => null,
                    'decodedDataOffset' => (int) ($frame['decodedDataOffset'] ?? 0),
                    'decodedDataEndOffset' => (int) ($frame['decodedDataEndOffset'] ?? 0),
                ];
                $dataFrameIndex++;
            }

            return $segments;
        }

        $sourceType = match ($type) {
            'plain-tar' => 'plain-tar',
            'zlib-deflate' => ($streamInspection['hasPresetDictionary'] ?? false) === true
                ? 'zlib-preset-dictionary-deflate'
                : 'zlib-deflate',
            'raw-deflate' => 'raw-deflate',
            default => $type === '' ? 'unknown' : $type,
        };
        $sourceLabel = null;
        if ($sourceType === 'zlib-preset-dictionary-deflate' && is_string($streamInspection['presetDictionaryIdHex'] ?? null)) {
            $sourceLabel = 'dictid:0x' . $streamInspection['presetDictionaryIdHex'];
        }

        return [[
            'sourceType' => $sourceType,
            'sourceIndex' => 0,
            'sourceLabel' => $sourceLabel,
            'decodedDataOffset' => 0,
            'decodedDataEndOffset' => $decodedSize,
        ]];
    }

    /**
     * @param list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }> $sourceSegments
     * @return list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     sourceDecodedOffset:int,
     *     sourceDecodedEndOffset:int,
     *     entryRecordOffset:int,
     *     entryRecordEndOffset:int
     * }>
     */
    private static function entryDecodedSourceSegments(int $recordStart, int $recordEnd, array $sourceSegments): array
    {
        $segments = [];
        foreach ($sourceSegments as $source) {
            $sourceStart = $source['decodedDataOffset'];
            $sourceEnd = $source['decodedDataEndOffset'];
            $overlapStart = max($recordStart, $sourceStart);
            $overlapEnd = min($recordEnd, $sourceEnd);
            if ($overlapStart >= $overlapEnd) {
                continue;
            }

            $segments[] = [
                'sourceType' => $source['sourceType'],
                'sourceIndex' => $source['sourceIndex'],
                'sourceLabel' => $source['sourceLabel'],
                'sourceDecodedOffset' => $overlapStart,
                'sourceDecodedEndOffset' => $overlapEnd,
                'entryRecordOffset' => $overlapStart - $recordStart,
                'entryRecordEndOffset' => $overlapEnd - $recordStart,
            ];
        }

        return $segments;
    }

    /**
     * @param list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }> $sourceSegments
     * @return list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     sourceDecodedOffset:int,
     *     sourceDecodedEndOffset:int,
     *     recordOffset:int,
     *     recordEndOffset:int
     * }>
     */
    private static function recordDecodedSourceSegments(int $recordStart, int $recordEnd, array $sourceSegments): array
    {
        $segments = [];
        foreach ($sourceSegments as $source) {
            $sourceStart = $source['decodedDataOffset'];
            $sourceEnd = $source['decodedDataEndOffset'];
            $overlapStart = max($recordStart, $sourceStart);
            $overlapEnd = min($recordEnd, $sourceEnd);
            if ($overlapStart >= $overlapEnd) {
                continue;
            }

            $segments[] = [
                'sourceType' => $source['sourceType'],
                'sourceIndex' => $source['sourceIndex'],
                'sourceLabel' => $source['sourceLabel'],
                'sourceDecodedOffset' => $overlapStart,
                'sourceDecodedEndOffset' => $overlapEnd,
                'recordOffset' => $overlapStart - $recordStart,
                'recordEndOffset' => $overlapEnd - $recordStart,
            ];
        }

        return $segments;
    }

    /**
     * @param list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }> $sourceSegments
     * @return list<array{
     *     chunkIndex:int,
     *     decodedOffset:int,
     *     decodedEndOffset:int,
     *     decodedSize:int,
     *     sourceSegmentCount:int,
     *     crossesSourceBoundary:bool,
     *     policy:string,
     *     sourceSegments:list<array{
     *         sourceType:string,
     *         sourceIndex:int,
     *         sourceLabel:?string,
     *         sourceDecodedOffset:int,
     *         sourceDecodedEndOffset:int,
     *         chunkOffset:int,
     *         chunkEndOffset:int
     *     }>
     * }>
     */
    private static function decodedPackageChunks(int $decodedSize, int $chunkSize, array $sourceSegments): array
    {
        $chunks = [];
        for ($offset = 0, $index = 0; $offset < $decodedSize; $offset += $chunkSize, $index++) {
            $endOffset = min($decodedSize, $offset + $chunkSize);
            $segments = [];
            foreach ($sourceSegments as $source) {
                $sourceStart = $source['decodedDataOffset'];
                $sourceEnd = $source['decodedDataEndOffset'];
                $overlapStart = max($offset, $sourceStart);
                $overlapEnd = min($endOffset, $sourceEnd);
                if ($overlapStart >= $overlapEnd) {
                    continue;
                }

                $segments[] = [
                    'sourceType' => $source['sourceType'],
                    'sourceIndex' => $source['sourceIndex'],
                    'sourceLabel' => $source['sourceLabel'],
                    'sourceDecodedOffset' => $overlapStart,
                    'sourceDecodedEndOffset' => $overlapEnd,
                    'chunkOffset' => $overlapStart - $offset,
                    'chunkEndOffset' => $overlapEnd - $offset,
                ];
            }

            $chunks[] = [
                'chunkIndex' => $index,
                'decodedOffset' => $offset,
                'decodedEndOffset' => $endOffset,
                'decodedSize' => $endOffset - $offset,
                'sourceSegmentCount' => count($segments),
                'crossesSourceBoundary' => count($segments) > 1,
                'policy' => 'metadata-only-no-extraction',
                'sourceSegments' => $segments,
            ];
        }

        return $chunks;
    }

    private static function paddedTarPayloadSize(int $size): int
    {
        $remainder = $size % 512;

        return $remainder === 0 ? $size : $size + (512 - $remainder);
    }

    private static function tarEndMarkerOffset(string $tarBytes): int
    {
        $zeroBlock = str_repeat("\0", 512);
        $length = strlen($tarBytes);
        for ($offset = 0; $offset + 1024 <= $length; $offset += 512) {
            if (substr($tarBytes, $offset, 512) === $zeroBlock
                && substr($tarBytes, $offset + 512, 512) === $zeroBlock
            ) {
                return $offset;
            }
        }

        throw new \RuntimeException('TAR archive is missing the required two-block end marker');
    }

    /**
     * @return array{
     *     type:string,
     *     blockSize:int,
     *     blockAligned:bool,
     *     hasEndMarker:bool,
     *     endMarkerOffset:?int,
     *     endMarkerEndOffset:?int,
     *     requiredEndMarkerBytes:int,
     *     trailingByteCount:int,
     *     trailingZeroByteCount:int,
     *     trailingNonZeroByteCount:int,
     *     firstTrailingNonZeroOffset:?int,
     *     firstTrailingNonZeroRelativeOffset:?int,
     *     trailingBytesSha256:?string,
     *     trailingBytesPreview:?string,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>
     * }
     */
    private static function tarEndMarkerPolicySummary(string $tarBytes): array
    {
        $blockSize = 512;
        $requiredEndMarkerBytes = $blockSize * 2;
        $length = strlen($tarBytes);
        $diagnostics = [];
        $blockAligned = $length > 0 && $length % $blockSize === 0;
        if ($length === 0) {
            $diagnostics[] = 'tar-stream-empty';
        }

        if (!$blockAligned) {
            $diagnostics[] = 'tar-record-size-unaligned';
        }

        $zeroBlock = str_repeat("\0", $blockSize);
        $endMarkerOffset = null;
        if ($blockAligned) {
            for ($offset = 0; $offset + $requiredEndMarkerBytes <= $length; $offset += $blockSize) {
                if (substr($tarBytes, $offset, $blockSize) === $zeroBlock
                    && substr($tarBytes, $offset + $blockSize, $blockSize) === $zeroBlock
                ) {
                    $endMarkerOffset = $offset;
                    break;
                }
            }
        }

        if ($endMarkerOffset === null) {
            $diagnostics[] = 'tar-end-marker-missing';

            return [
                'type' => 'tar-end-marker-policy',
                'blockSize' => $blockSize,
                'blockAligned' => $blockAligned,
                'hasEndMarker' => false,
                'endMarkerOffset' => null,
                'endMarkerEndOffset' => null,
                'requiredEndMarkerBytes' => $requiredEndMarkerBytes,
                'trailingByteCount' => 0,
                'trailingZeroByteCount' => 0,
                'trailingNonZeroByteCount' => 0,
                'firstTrailingNonZeroOffset' => null,
                'firstTrailingNonZeroRelativeOffset' => null,
                'trailingBytesSha256' => null,
                'trailingBytesPreview' => null,
                'handoffPolicy' => 'review-before-conversion',
                'extractionPolicy' => 'tar-end-marker-review',
                'diagnostics' => array_values(array_unique($diagnostics)),
            ];
        }

        $endMarkerEndOffset = $endMarkerOffset + $requiredEndMarkerBytes;
        $trailingBytes = substr($tarBytes, $endMarkerEndOffset);
        $trailingByteCount = strlen($trailingBytes);
        $trailingZeroByteCount = 0;
        $trailingNonZeroByteCount = 0;
        $firstTrailingNonZeroRelativeOffset = null;

        for ($index = 0; $index < $trailingByteCount; $index++) {
            if ($trailingBytes[$index] === "\0") {
                $trailingZeroByteCount++;
                continue;
            }

            $trailingNonZeroByteCount++;
            if ($firstTrailingNonZeroRelativeOffset === null) {
                $firstTrailingNonZeroRelativeOffset = $index;
            }
        }

        if ($trailingNonZeroByteCount > 0) {
            $diagnostics[] = 'tar-end-marker-trailing-non-zero-bytes';
        }

        $handoffPolicy = $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion';

        return [
            'type' => 'tar-end-marker-policy',
            'blockSize' => $blockSize,
            'blockAligned' => $blockAligned,
            'hasEndMarker' => true,
            'endMarkerOffset' => $endMarkerOffset,
            'endMarkerEndOffset' => $endMarkerEndOffset,
            'requiredEndMarkerBytes' => $requiredEndMarkerBytes,
            'trailingByteCount' => $trailingByteCount,
            'trailingZeroByteCount' => $trailingZeroByteCount,
            'trailingNonZeroByteCount' => $trailingNonZeroByteCount,
            'firstTrailingNonZeroOffset' => $firstTrailingNonZeroRelativeOffset === null
                ? null
                : $endMarkerEndOffset + $firstTrailingNonZeroRelativeOffset,
            'firstTrailingNonZeroRelativeOffset' => $firstTrailingNonZeroRelativeOffset,
            'trailingBytesSha256' => $trailingByteCount === 0 ? null : hash('sha256', $trailingBytes),
            'trailingBytesPreview' => $trailingByteCount === 0
                ? null
                : self::boundedPrintablePreview($trailingBytes, 64),
            'handoffPolicy' => $handoffPolicy,
            'extractionPolicy' => $handoffPolicy === 'within-thresholds'
                ? 'metadata-only-no-extraction'
                : 'tar-end-marker-review',
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    private static function zipPackageUncompressedSize(ZipPackage $package): int
    {
        $size = 0;
        foreach ($package->entries() as $entry) {
            if (!$entry->isDirectory()) {
                $size += $entry->uncompressedSize;
            }
        }

        return $size;
    }

    private static function expansionRatio(int $expandedBytes, int $baseBytes): float
    {
        if ($expandedBytes === 0) {
            return 0.0;
        }

        if ($baseBytes <= 0) {
            return INF;
        }

        return $expandedBytes / $baseBytes;
    }

    private static function assertPositiveRatio(float $value, string $label): void
    {
        if (!is_finite($value) || $value <= 0.0) {
            throw new \RuntimeException("{$label} must be a positive finite number");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function streamInspection(string $bytes, string $format, ?int $maxUncompressedBytes): array
    {
        return match ($format) {
            self::FORMAT_TAR => [
                'type' => 'plain-tar',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_ZIP => [
                'type' => 'plain-zip',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_GZIP_TAR, self::FORMAT_GZIP_ZIP => self::gzipStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR, self::FORMAT_ZLIB_ZIP => self::zlibStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR, self::FORMAT_RAW_DEFLATE_ZIP => self::rawDeflateStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR, self::FORMAT_LZ4_ZIP => self::lz4StreamInspection($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     trailingPaddingBytes:int,
     *     members:list<array{
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
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
     *         extraFieldCount:int,
     *         headerCrcPresent:bool,
     *         headerCrc16:?int,
     *         headerCrc16Hex:?string,
     *         headerCrcOffset:?int,
     *         headerCrcCoverageSize:?int
     *     }>
     * }
     */
    private static function gzipStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $members = array_map(
            static fn (array $member): array => [
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'modifiedAt' => $member['modifiedAt'],
                'modifiedAtKnown' => $member['modifiedAtKnown'],
                'modifiedAtText' => $member['modifiedAtText'],
                'flags' => $member['flags'],
                'textHint' => $member['textHint'],
                'extraFlags' => $member['extraFlags'],
                'extraFlagsMeaning' => $member['extraFlagsMeaning'],
                'operatingSystem' => $member['operatingSystem'],
                'operatingSystemName' => $member['operatingSystemName'],
                'extraFieldData' => $member['extraFieldData'],
                'extraFields' => $member['extraFields'],
                'crc32' => $member['crc32'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'memberSize' => $member['memberSize'],
                'memberOffset' => $member['memberOffset'],
                'headerSize' => $member['headerSize'],
                'compressedDataOffset' => $member['compressedDataOffset'],
                'trailerOffset' => $member['trailerOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'extraFieldCount' => count($member['extraFields']),
                'headerCrcPresent' => $member['headerCrcPresent'],
                'headerCrc16' => $member['headerCrc16'],
                'headerCrc16Hex' => $member['headerCrc16Hex'],
                'headerCrcOffset' => $member['headerCrcOffset'],
                'headerCrcCoverageSize' => $member['headerCrcCoverageSize'],
            ],
            $inspection['members']
        );

        return [
            'type' => 'gzip',
            'memberCount' => $inspection['memberCount'],
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'trailingPaddingBytes' => $inspection['trailingPaddingBytes'],
            'members' => $members,
        ];
    }

    private static function gzipDecodedPackageFormat(string $format): string
    {
        return match ($format) {
            self::FORMAT_GZIP_TAR => self::FORMAT_TAR,
            self::FORMAT_GZIP_ZIP => self::FORMAT_ZIP,
            default => throw new \RuntimeException("Unsupported gzip package stream format: {$format}"),
        };
    }

    private static function gzipModifiedAtText(?int $modifiedAt): ?string
    {
        if ($modifiedAt === null || $modifiedAt === 0) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $modifiedAt);
    }

    /**
     * @return list<string>
     */
    private static function gzipTextHintPayloadDiagnostics(string $probe): array
    {
        $diagnostics = [];
        if (str_contains($probe, "\0")) {
            $diagnostics[] = 'gzip-text-hint-payload-contains-nul';
        }

        $length = strlen($probe);
        for ($index = 0; $index < $length; $index++) {
            $byte = ord($probe[$index]);
            if (($byte >= 1 && $byte <= 8) || $byte === 11 || $byte === 12 || ($byte >= 14 && $byte <= 31)) {
                $diagnostics[] = 'gzip-text-hint-payload-contains-control-bytes';
                break;
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<string>
     */
    private static function gzipMemberMetadataTextDiagnostics(string $text, string $prefix, bool $checkPath): array
    {
        $diagnostics = [];

        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f\x{0080}-\x{009f}]/u', $text) === 1) {
            $diagnostics[] = "{$prefix}-control-bytes";
        }

        if (preg_match('/[\x{200b}-\x{200f}\x{202a}-\x{202e}\x{2060}-\x{206f}\x{feff}]/u', $text) === 1) {
            $diagnostics[] = "{$prefix}-unicode-format-control";
        }

        if (preg_match('/[\x{061c}\x{200e}\x{200f}\x{202a}-\x{202e}\x{2066}-\x{2069}]/u', $text) === 1) {
            $diagnostics[] = "{$prefix}-bidi-format-control";
        }

        if (!$checkPath) {
            return $diagnostics;
        }

        if ($text === '') {
            $diagnostics[] = "{$prefix}-empty";
        }

        if (str_starts_with($text, '/')) {
            $diagnostics[] = "{$prefix}-absolute-path";
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $text) === 1) {
            $diagnostics[] = "{$prefix}-drive-letter-path";
        }

        if (str_contains($text, '\\')) {
            $diagnostics[] = "{$prefix}-backslash-path";
        }

        foreach (explode('/', str_replace('\\', '/', $text)) as $segment) {
            if ($segment === '..') {
                $diagnostics[] = "{$prefix}-parent-segment";
                break;
            }
        }

        return array_values(array_unique($diagnostics));
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     adler32:int,
     *     adler32Hex:string
     * }
     */
    private static function zlibStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectZlib($bytes, $maxUncompressedBytes);

        return [
            'type' => 'zlib-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'adler32' => $metadata['adler32'],
            'adler32Hex' => $metadata['adler32Hex'],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function zlibDictionaryStreamInspection(string $bytes, array $metadata): array
    {
        return [
            'type' => 'zlib-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'hasPresetDictionary' => $metadata['hasPresetDictionary'],
            'presetDictionaryId' => $metadata['presetDictionaryId'],
            'presetDictionaryIdHex' => $metadata['presetDictionaryIdHex'],
            'dictionarySupplied' => $metadata['dictionarySupplied'],
            'dictionarySize' => $metadata['dictionarySize'],
            'dictionaryAdler32' => $metadata['dictionaryAdler32'],
            'dictionaryAdler32Hex' => $metadata['dictionaryAdler32Hex'],
            'adler32' => $metadata['adler32'],
            'adler32Hex' => $metadata['adler32Hex'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:?int,
     *     trailerSize:int,
     *     consumedBytes:int
     * }
     */
    private static function rawDeflateStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectRaw($bytes, $maxUncompressedBytes);

        return [
            'type' => 'raw-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     blockCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     frames:list<array<string, mixed>>
     * }
     */
    private static function lz4StreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $frames = [];
        $dataFrameCount = 0;
        $skippableFrameCount = 0;
        $blockCount = 0;
        $uncompressedSize = 0;

        foreach (Lz4Frame::frames($bytes, $maxUncompressedBytes) as $frame) {
            if ($frame['type'] === 'skippable') {
                $skippableFrameCount++;
                $frames[] = [
                    'type' => 'skippable',
                    'id' => $frame['id'],
                    'data' => $frame['data'],
                    'frameSize' => $frame['frameSize'],
                    'frameOffset' => $frame['frameOffset'],
                    'nextFrameOffset' => $frame['nextFrameOffset'],
                ];
                continue;
            }

            $dataSize = strlen($frame['data']);
            $dataFrameCount++;
            $blockCount += $frame['blockCount'];
            $uncompressedSize += $dataSize;
            $frames[] = [
                'type' => 'frame',
                'contentSize' => $frame['contentSize'],
                'blockMaxSize' => $frame['blockMaxSize'],
                'blockIndependent' => $frame['blockIndependent'],
                'blockChecksum' => $frame['blockChecksum'],
                'contentChecksum' => $frame['contentChecksum'],
                'blockCount' => $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => $frame['compressedSize'],
                'decodedDataSize' => $dataSize,
                'decodedDataOffset' => $frame['decodedDataOffset'],
                'decodedDataEndOffset' => $frame['decodedDataEndOffset'],
                'frameSize' => $frame['frameSize'],
                'frameOffset' => $frame['frameOffset'],
                'nextFrameOffset' => $frame['nextFrameOffset'],
            ] + self::lz4FrameDescriptorMetadata($frame);
        }

        return [
            'type' => 'lz4',
            'frameCount' => count($frames),
            'dataFrameCount' => $dataFrameCount,
            'skippableFrameCount' => $skippableFrameCount,
            'blockCount' => $blockCount,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $uncompressedSize,
            'frames' => $frames,
        ];
    }

    /**
     * @param array<string, mixed> $frame
     * @return array{
     *     flags:int,
     *     flagsHex:string,
     *     blockDescriptor:int,
     *     blockDescriptorHex:string,
     *     descriptorOffset:int,
     *     descriptorSize:int,
     *     headerChecksum:int,
     *     headerChecksumHex:string,
     *     headerChecksumOffset:int,
     *     headerSize:int
     * }
     */
    private static function lz4FrameDescriptorMetadata(array $frame): array
    {
        $flags = (int) ($frame['flags'] ?? 0);
        $blockDescriptor = (int) ($frame['blockDescriptor'] ?? 0);
        $headerChecksum = (int) ($frame['headerChecksum'] ?? 0);

        return [
            'flags' => $flags,
            'flagsHex' => (string) ($frame['flagsHex'] ?? sprintf('%02x', $flags)),
            'blockDescriptor' => $blockDescriptor,
            'blockDescriptorHex' => (string) ($frame['blockDescriptorHex'] ?? sprintf('%02x', $blockDescriptor)),
            'descriptorOffset' => (int) ($frame['descriptorOffset'] ?? 0),
            'descriptorSize' => (int) ($frame['descriptorSize'] ?? 0),
            'headerChecksum' => $headerChecksum,
            'headerChecksumHex' => (string) ($frame['headerChecksumHex'] ?? sprintf('%02x', $headerChecksum)),
            'headerChecksumOffset' => (int) ($frame['headerChecksumOffset'] ?? 0),
            'headerSize' => (int) ($frame['headerSize'] ?? 0),
        ];
    }

    /**
     * @param list<array<string, mixed>> $frames
     * @param array<int, string> $dictionaryMap
     * @return array<string, mixed>
     */
    private static function lz4DictionaryStreamInspection(string $bytes, array $frames, array $dictionaryMap): array
    {
        $summaryFrames = [];
        $dataFrameCount = 0;
        $skippableFrameCount = 0;
        $dictionaryFrameCount = 0;
        $blockCount = 0;
        $uncompressedSize = 0;

        foreach ($frames as $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $skippableFrameCount++;
                $summaryFrames[] = [
                    'type' => 'skippable',
                    'id' => $frame['id'],
                    'data' => $frame['data'],
                    'frameSize' => $frame['frameSize'],
                    'frameOffset' => $frame['frameOffset'],
                    'nextFrameOffset' => $frame['nextFrameOffset'],
                ];
                continue;
            }

            if (($frame['type'] ?? null) !== 'frame') {
                throw new \RuntimeException('Unexpected LZ4 frame metadata record');
            }

            $dictionaryId = $frame['dictionaryId'];
            $dictionarySize = null;
            if ($dictionaryId !== null) {
                $dictionaryFrameCount++;
                $dictionarySize = strlen($dictionaryMap[$dictionaryId] ?? '');
            }

            $dataSize = strlen($frame['data']);
            $decodedDataOffset = $uncompressedSize;
            $decodedDataEndOffset = $decodedDataOffset + $dataSize;
            $dataFrameCount++;
            $blockCount += $frame['blockCount'];
            $uncompressedSize = $decodedDataEndOffset;
            $summaryFrames[] = [
                'type' => 'frame',
                'contentSize' => $frame['contentSize'],
                'dictionaryId' => $dictionaryId,
                'dictionarySupplied' => $dictionaryId !== null,
                'dictionarySize' => $dictionarySize,
                'blockMaxSize' => $frame['blockMaxSize'],
                'blockIndependent' => $frame['blockIndependent'],
                'blockChecksum' => $frame['blockChecksum'],
                'contentChecksum' => $frame['contentChecksum'],
                'blockCount' => $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => $frame['compressedSize'],
                'decodedDataSize' => $dataSize,
                'decodedDataOffset' => $decodedDataOffset,
                'decodedDataEndOffset' => $decodedDataEndOffset,
                'frameSize' => $frame['frameSize'],
                'frameOffset' => $frame['frameOffset'],
                'nextFrameOffset' => $frame['nextFrameOffset'],
            ] + self::lz4FrameDescriptorMetadata($frame);
        }

        return [
            'type' => 'lz4',
            'frameCount' => count($summaryFrames),
            'dataFrameCount' => $dataFrameCount,
            'skippableFrameCount' => $skippableFrameCount,
            'dictionaryFrameCount' => $dictionaryFrameCount,
            'blockCount' => $blockCount,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $uncompressedSize,
            'frames' => $summaryFrames,
        ];
    }

    /**
     * @param list<array<string, mixed>> $frames
     */
    private static function decodedLz4DataFromFrames(array $frames): string
    {
        $data = '';
        foreach ($frames as $frame) {
            if (($frame['type'] ?? null) === 'frame') {
                $data .= $frame['data'];
            }
        }

        return $data;
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<int, string>
     */
    private static function normalizeLz4ExternalDictionaries(array $dictionaries): array
    {
        $normalized = [];
        foreach ($dictionaries as $id => $dictionary) {
            if (!is_string($dictionary)) {
                throw new \RuntimeException('LZ4 external dictionaries must be byte strings');
            }

            if ($dictionary === '') {
                throw new \RuntimeException('LZ4 external dictionaries must not be empty');
            }

            if (is_int($id)) {
                $dictionaryId = $id;
            } elseif (is_string($id) && preg_match('/^(?:0|[1-9][0-9]*)$/', $id) === 1) {
                $dictionaryId = (int) $id;
            } else {
                throw new \RuntimeException('LZ4 external dictionary ids must be unsigned 32-bit integers');
            }

            if ($dictionaryId < 0 || $dictionaryId > 0xffffffff) {
                throw new \RuntimeException('LZ4 external dictionary id must fit in an unsigned 32-bit field');
            }

            $normalized[$dictionaryId] = $dictionary;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param list<string> $candidateReasons
     * @param array{stream:float, package:float, total:float} $thresholds
     * @return array<string, mixed>
     */
    private static function archiveBombPackageRecord(
        array $candidate,
        string $path,
        ?string $parentPath,
        ?string $parentKind,
        ?string $entryName,
        int $depth,
        int $compressedSize,
        array $candidateReasons,
        int $maxDepth,
        array $thresholds
    ): array {
        $decodedPackageSize = self::candidatePackageByteSize($candidate);
        if ($decodedPackageSize === null) {
            throw new \RuntimeException('Detected archive package candidate is missing decoded package bytes');
        }

        $entryNames = self::candidateEntryNames($candidate);
        $entryUncompressedSize = self::candidateEntryUncompressedSize($candidate);
        $streamCompressionRatio = self::expansionRatio($decodedPackageSize, $compressedSize);
        $packageExpansionRatio = self::expansionRatio($entryUncompressedSize, $decodedPackageSize);
        $totalExpansionRatio = self::expansionRatio($entryUncompressedSize, $compressedSize);
        $diagnostics = [];
        $ratioDiagnosticCount = 0;

        if ($streamCompressionRatio > $thresholds['stream']) {
            $diagnostics[] = 'archive-stream-compression-ratio-exceeds-threshold';
            $ratioDiagnosticCount++;
        }

        if ($packageExpansionRatio > $thresholds['package']) {
            $diagnostics[] = 'archive-package-expansion-ratio-exceeds-threshold';
            $ratioDiagnosticCount++;
        }

        if ($totalExpansionRatio > $thresholds['total']) {
            $diagnostics[] = 'archive-total-expansion-ratio-exceeds-threshold';
            $ratioDiagnosticCount++;
        }

        $depthLimitedCandidates = $depth >= $maxDepth
            ? self::nestedPackageDepthLimitCandidates($candidate)
            : [];
        if ($depthLimitedCandidates !== []) {
            $diagnostics[] = 'nested-package-depth-limit-reached';
        }

        $record = [
            'status' => 'package',
            'path' => $path,
            'parentPath' => $parentPath,
            'parentKind' => $parentKind,
            'entryName' => $entryName,
            'depth' => $depth,
            'kind' => $candidate['kind'],
            'format' => $candidate['format'],
            'compressedSize' => $compressedSize,
            'decodedPackageSize' => $decodedPackageSize,
            'entryUncompressedSize' => $entryUncompressedSize,
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'candidateReasons' => $candidateReasons,
            'streamCompressionRatio' => $streamCompressionRatio,
            'packageExpansionRatio' => $packageExpansionRatio,
            'totalExpansionRatio' => $totalExpansionRatio,
            'maxStreamCompressionRatio' => $thresholds['stream'],
            'maxPackageExpansionRatio' => $thresholds['package'],
            'maxTotalExpansionRatio' => $thresholds['total'],
            'ratioDiagnosticCount' => $ratioDiagnosticCount,
            'policy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $diagnostics,
            'depthLimitReached' => $depthLimitedCandidates !== [],
            'depthLimitedCandidateCount' => count($depthLimitedCandidates),
            'depthLimitedCandidateNames' => array_column($depthLimitedCandidates, 'entryName'),
            'depthLimitedCandidates' => $depthLimitedCandidates,
        ];

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && ($candidate['archive'] ?? null) instanceof TarArchive) {
            $archive = $candidate['archive'];
            $record['regularFileCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isRegularFile()
            ));
            $record['directoryCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isDirectory()
            ));
            $record['unpackedSize'] = self::archiveUnpackedSize($archive);
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array{stream:float, package:float, total:float} $thresholds
     * @param list<array<string, mixed>> $entries
     */
    private static function collectNestedArchiveBombEntries(
        array $candidate,
        string $parentPath,
        int $depth,
        int $maxDepth,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes,
        array $thresholds,
        array &$entries
    ): void {
        foreach (self::nestedPackagePayloads($candidate, $maxUncompressedBytes) as $payload) {
            $reasons = self::nestedPackageCandidateReasons($payload['entryName'], $payload['data']);
            if ($reasons === []) {
                continue;
            }

            $path = $parentPath === ''
                ? $payload['entryName']
                : $parentPath . '!' . $payload['entryName'];

            if ($payload['readError'] !== null) {
                $entries[] = self::unreadableNestedArchiveBombRecord(
                    $path,
                    $parentPath,
                    $payload,
                    $depth,
                    $reasons,
                    'nested-package-read-failed: ' . $payload['readError']
                );
                continue;
            }

            $unsupportedPolicy = self::nestedUnsupportedCompressionPolicy($payload['entryName'], $payload['data']);
            if ($unsupportedPolicy !== null) {
                $entries[] = self::unsupportedNestedArchiveBombRecord(
                    $path,
                    $parentPath,
                    $payload,
                    $depth,
                    $reasons,
                    $unsupportedPolicy
                );
                continue;
            }

            try {
                $nested = self::detectPackageCandidate(
                    $payload['data'],
                    $maxUncompressedBytes,
                    $maxUnpackedBytes
                );
            } catch (\RuntimeException $exception) {
                $entries[] = self::unreadableNestedArchiveBombRecord(
                    $path,
                    $parentPath,
                    $payload,
                    $depth,
                    $reasons,
                    'nested-package-detection-failed: ' . $exception->getMessage()
                );
                continue;
            }

            $entries[] = self::archiveBombPackageRecord(
                $nested,
                $path,
                $parentPath,
                $payload['parentKind'],
                $payload['entryName'],
                $depth,
                $payload['size'],
                $reasons,
                $maxDepth,
                $thresholds
            );

            if ($depth < $maxDepth) {
                self::collectNestedArchiveBombEntries(
                    $nested,
                    $path,
                    $depth + 1,
                    $maxDepth,
                    $maxUncompressedBytes,
                    $maxUnpackedBytes,
                    $thresholds,
                    $entries
                );
            }
        }
    }

    /**
     * @param array{parentKind:string, entryName:string, size:int, data:?string, readError:?string} $payload
     * @param list<string> $candidateReasons
     * @return array<string, mixed>
     */
    private static function unreadableNestedArchiveBombRecord(
        string $path,
        string $parentPath,
        array $payload,
        int $depth,
        array $candidateReasons,
        string $diagnostic
    ): array {
        return [
            'status' => 'unreadable',
            'path' => $path,
            'parentPath' => $parentPath,
            'parentKind' => $payload['parentKind'],
            'entryName' => $payload['entryName'],
            'depth' => $depth,
            'kind' => null,
            'format' => null,
            'compressedSize' => $payload['size'],
            'decodedPackageSize' => null,
            'entryUncompressedSize' => null,
            'entryCount' => 0,
            'entryNames' => [],
            'candidateReasons' => $candidateReasons,
            'streamCompressionRatio' => null,
            'packageExpansionRatio' => null,
            'totalExpansionRatio' => null,
            'ratioDiagnosticCount' => 0,
            'policy' => 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => [$diagnostic],
            'depthLimitReached' => false,
            'depthLimitedCandidateCount' => 0,
            'depthLimitedCandidateNames' => [],
            'depthLimitedCandidates' => [],
        ];
    }

    /**
     * @param array{parentKind:string, entryName:string, size:int, data:?string, readError:?string} $payload
     * @param list<string> $candidateReasons
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private static function unsupportedNestedArchiveBombRecord(
        string $path,
        string $parentPath,
        array $payload,
        int $depth,
        array $candidateReasons,
        array $policy
    ): array {
        return [
            'status' => 'unsupported-compression',
            'path' => $path,
            'parentPath' => $parentPath,
            'parentKind' => $payload['parentKind'],
            'entryName' => $payload['entryName'],
            'depth' => $depth,
            'kind' => $policy['candidateKind'],
            'format' => $policy['format'],
            'candidateFormat' => $policy['candidateFormat'],
            'sourceName' => $policy['sourceName'],
            'sourceNameCandidate' => $policy['sourceNameCandidate'],
            'sourceNameReason' => $policy['sourceNameReason'],
            'sourceNameKind' => $policy['sourceNameKind'],
            'sourceNameFormat' => $policy['sourceNameFormat'],
            'sourceNameCandidateFormat' => $policy['sourceNameCandidateFormat'],
            'signatureFormat' => $policy['signatureFormat'],
            'signatureSourceNameMismatch' => $policy['signatureSourceNameMismatch'],
            'compressedSize' => $payload['size'],
            'payloadSha256' => $policy['payloadSha256'],
            'payloadPreviewBytes' => $policy['payloadPreviewBytes'],
            'payloadPreview' => $policy['payloadPreview'],
            'decodedPackageSize' => null,
            'entryUncompressedSize' => null,
            'entryCount' => 0,
            'entryNames' => [],
            'candidateReasons' => $candidateReasons,
            'streamCompressionRatio' => null,
            'packageExpansionRatio' => null,
            'totalExpansionRatio' => null,
            'signatureMatched' => $policy['signatureMatched'],
            'signatureName' => $policy['signatureName'],
            'signatureBytesHex' => $policy['signatureBytesHex'],
            'streamHeaderSize' => $policy['streamHeaderSize'],
            'streamFlagsHex' => $policy['streamFlagsHex'],
            'blockSize100k' => $policy['blockSize100k'],
            'ratioDiagnosticCount' => 0,
            'policy' => $policy['handoffPolicy'],
            'handoffPolicy' => $policy['handoffPolicy'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'diagnostics' => $policy['diagnostics'],
            'depthLimitReached' => false,
            'depthLimitedCandidateCount' => 0,
            'depthLimitedCandidateNames' => [],
            'depthLimitedCandidates' => [],
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @param list<array<string, mixed>> $entries
     */
    private static function collectNestedPackageEntries(
        array $candidate,
        string $parentPath,
        int $depth,
        int $maxDepth,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes,
        array &$entries
    ): void {
        foreach (self::nestedPackagePayloads($candidate, $maxUncompressedBytes) as $payload) {
            $reasons = self::nestedPackageCandidateReasons($payload['entryName'], $payload['data']);
            if ($reasons === []) {
                continue;
            }

            $path = $parentPath === ''
                ? $payload['entryName']
                : $parentPath . '!' . $payload['entryName'];
            $base = [
                'path' => $path,
                'parentPath' => $parentPath,
                'parentKind' => $payload['parentKind'],
                'entryName' => $payload['entryName'],
                'depth' => $depth,
                'size' => $payload['size'],
                'candidateReasons' => $reasons,
                'policy' => 'metadata-only-no-extraction',
            ];

            if ($payload['readError'] !== null) {
                $entries[] = $base + [
                    'status' => 'unreadable',
                    'kind' => null,
                    'format' => null,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'uncompressedSize' => null,
                    'packageByteSize' => null,
                    'diagnostics' => ['nested-package-read-failed: ' . $payload['readError']],
                ];
                continue;
            }

            $unsupportedPolicy = self::nestedUnsupportedCompressionPolicy($payload['entryName'], $payload['data']);
            if ($unsupportedPolicy !== null) {
                $entries[] = self::unsupportedNestedPackageRecord(
                    $base,
                    $unsupportedPolicy
                );
                continue;
            }

            try {
                $nested = self::detectPackageCandidate(
                    $payload['data'],
                    $maxUncompressedBytes,
                    $maxUnpackedBytes
                );
            } catch (\RuntimeException $exception) {
                $entries[] = $base + [
                    'status' => 'unreadable',
                    'kind' => null,
                    'format' => null,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'uncompressedSize' => null,
                    'packageByteSize' => null,
                    'diagnostics' => ['nested-package-detection-failed: ' . $exception->getMessage()],
                ];
                continue;
            }

            $entries[] = $base + self::nestedPackageSummary($nested, $depth, $maxDepth);
            if ($depth < $maxDepth) {
                self::collectNestedPackageEntries(
                    $nested,
                    $path,
                    $depth + 1,
                    $maxDepth,
                    $maxUncompressedBytes,
                    $maxUnpackedBytes,
                    $entries
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private static function unsupportedNestedPackageRecord(array $base, array $policy): array
    {
        return $base + [
            'status' => 'unsupported-compression',
            'kind' => $policy['candidateKind'],
            'format' => $policy['format'],
            'candidateFormat' => $policy['candidateFormat'],
            'sourceName' => $policy['sourceName'],
            'sourceNameCandidate' => $policy['sourceNameCandidate'],
            'sourceNameReason' => $policy['sourceNameReason'],
            'sourceNameKind' => $policy['sourceNameKind'],
            'sourceNameFormat' => $policy['sourceNameFormat'],
            'sourceNameCandidateFormat' => $policy['sourceNameCandidateFormat'],
            'signatureFormat' => $policy['signatureFormat'],
            'signatureSourceNameMismatch' => $policy['signatureSourceNameMismatch'],
            'entryCount' => 0,
            'entryNames' => [],
            'uncompressedSize' => null,
            'packageByteSize' => null,
            'payloadSha256' => $policy['payloadSha256'],
            'payloadPreviewBytes' => $policy['payloadPreviewBytes'],
            'payloadPreview' => $policy['payloadPreview'],
            'signatureMatched' => $policy['signatureMatched'],
            'signatureName' => $policy['signatureName'],
            'signatureBytesHex' => $policy['signatureBytesHex'],
            'streamHeaderSize' => $policy['streamHeaderSize'],
            'streamFlagsHex' => $policy['streamFlagsHex'],
            'blockSize100k' => $policy['blockSize100k'],
            'handoffPolicy' => $policy['handoffPolicy'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'diagnostics' => $policy['diagnostics'],
            'depthLimitReached' => false,
            'depthLimitedCandidateCount' => 0,
            'depthLimitedCandidateNames' => [],
            'depthLimitedCandidates' => [],
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return iterable<array{parentKind:string, entryName:string, size:int, data:?string, readError:?string}>
     */
    private static function nestedPackagePayloads(array $candidate, ?int $maxUncompressedBytes): iterable
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'] ?? null;
            if (!$archive instanceof TarArchive) {
                return;
            }

            foreach ($archive->entries() as $entry) {
                if (!$entry->isRegularFile()) {
                    continue;
                }

                yield [
                    'parentKind' => self::PACKAGE_KIND_TAR,
                    'entryName' => $entry->name,
                    'size' => $entry->size,
                    'data' => $archive->read($entry->name),
                    'readError' => null,
                ];
            }

            return;
        }

        $package = $candidate['package'] ?? null;
        if (!$package instanceof ZipPackage) {
            return;
        }

        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            try {
                $data = $package->read($entry->name, $maxUncompressedBytes);
                $readError = null;
            } catch (\RuntimeException $exception) {
                $data = null;
                $readError = $exception->getMessage();
            }

            yield [
                'parentKind' => self::PACKAGE_KIND_ZIP,
                'entryName' => $entry->name,
                'size' => $entry->uncompressedSize,
                'data' => $data,
                'readError' => $readError,
            ];
        }
    }

    /**
     * @param array<string, mixed> $candidate
     * @return list<array{entryName:string, candidateReasons:list<string>, size:int}>
     */
    private static function nestedPackageDepthLimitCandidates(array $candidate): array
    {
        $candidates = [];
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && ($candidate['archive'] ?? null) instanceof TarArchive) {
            foreach ($candidate['archive']->entries() as $entry) {
                if (!$entry->isRegularFile()) {
                    continue;
                }

                $reasons = self::nestedPackageNameCandidateReasons($entry->name);
                if ($reasons === []) {
                    continue;
                }

                $candidates[] = [
                    'entryName' => $entry->name,
                    'candidateReasons' => $reasons,
                    'size' => $entry->size,
                ];
            }

            return $candidates;
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && ($candidate['package'] ?? null) instanceof ZipPackage) {
            foreach ($candidate['package']->entries() as $entry) {
                if ($entry->isDirectory()) {
                    continue;
                }

                $reasons = self::nestedPackageNameCandidateReasons($entry->name);
                if ($reasons === []) {
                    continue;
                }

                $candidates[] = [
                    'entryName' => $entry->name,
                    'candidateReasons' => $reasons,
                    'size' => $entry->uncompressedSize,
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private static function nestedPackageSummary(array $candidate, int $depth, int $maxDepth): array
    {
        $entryNames = self::candidateEntryNames($candidate);
        $depthLimitedCandidates = $depth >= $maxDepth
            ? self::nestedPackageDepthLimitCandidates($candidate)
            : [];
        $summary = [
            'status' => 'package',
            'kind' => $candidate['kind'],
            'format' => $candidate['format'],
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'uncompressedSize' => self::candidateUncompressedSize($candidate),
            'packageByteSize' => self::candidatePackageByteSize($candidate),
            'diagnostics' => $depthLimitedCandidates === [] ? [] : ['nested-package-depth-limit-reached'],
            'depthLimitReached' => $depthLimitedCandidates !== [],
            'depthLimitedCandidateCount' => count($depthLimitedCandidates),
            'depthLimitedCandidateNames' => array_column($depthLimitedCandidates, 'entryName'),
            'depthLimitedCandidates' => $depthLimitedCandidates,
        ];

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'];
            $summary['regularFileCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isRegularFile()
            ));
            $summary['directoryCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isDirectory()
            ));
            $summary['unpackedSize'] = self::archiveUnpackedSize($archive);
        } elseif (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP) {
            $summary['entryUncompressedSize'] = self::zipPackageUncompressedSize($candidate['package']);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return list<string>
     */
    private static function candidateEntryNames(array $candidate): array
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && ($candidate['archive'] ?? null) instanceof TarArchive) {
            return $candidate['archive']->names();
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && ($candidate['package'] ?? null) instanceof ZipPackage) {
            return $candidate['package']->names();
        }

        return [];
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private static function candidateUncompressedSize(array $candidate): ?int
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && isset($candidate['tarBytes']) && is_string($candidate['tarBytes'])) {
            return strlen($candidate['tarBytes']);
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && isset($candidate['zipBytes']) && is_string($candidate['zipBytes'])) {
            return strlen($candidate['zipBytes']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private static function candidatePackageByteSize(array $candidate): ?int
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && isset($candidate['tarBytes']) && is_string($candidate['tarBytes'])) {
            return strlen($candidate['tarBytes']);
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && isset($candidate['zipBytes']) && is_string($candidate['zipBytes'])) {
            return strlen($candidate['zipBytes']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private static function candidateEntryUncompressedSize(array $candidate): int
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && ($candidate['archive'] ?? null) instanceof TarArchive) {
            return self::archiveUnpackedSize($candidate['archive']);
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && ($candidate['package'] ?? null) instanceof ZipPackage) {
            return self::zipPackageUncompressedSize($candidate['package']);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private static function nestedPackageCandidateReasons(string $entryName, ?string $data): array
    {
        $reasons = self::nestedPackageNameCandidateReasons($entryName);
        if ($data !== null) {
            if (self::startsWithZipHeader($data)) {
                $reasons[] = 'signature:zip';
            }
            if (self::startsWithGzipHeader($data)) {
                $reasons[] = 'signature:gzip';
            }
            if (self::startsWithZlibHeader($data)) {
                $reasons[] = 'signature:zlib';
            }
            if (self::startsWithLz4Header($data)) {
                $reasons[] = 'signature:lz4';
            }
            if (self::startsWithTarHeader($data)) {
                $reasons[] = 'signature:tar';
            }
            $unsupportedSignature = self::unsupportedCompressionSignature($data);
            if ($unsupportedSignature !== null) {
                $reasons[] = 'signature:unsupported-' . $unsupportedSignature['format'];
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return list<string>
     */
    private static function nestedPackageNameCandidateReasons(string $entryName): array
    {
        $lower = strtolower($entryName);
        $extensionMap = [
            '.tar.gz' => 'extension:gzip-tar',
            '.tgz' => 'extension:gzip-tar',
            '.tar.zlib' => 'extension:zlib-tar',
            '.tar.deflate' => 'extension:raw-deflate-tar',
            '.tar.lz4' => 'extension:lz4-tar',
            '.tlz4' => 'extension:lz4-tar',
            '.tar' => 'extension:tar',
            '.zip.gz' => 'extension:gzip-zip',
            '.zip.zlib' => 'extension:zlib-zip',
            '.zip.deflate' => 'extension:raw-deflate-zip',
            '.zip.lz4' => 'extension:lz4-zip',
            '.zip' => 'extension:zip',
            '.docx' => 'extension:zip-package',
            '.dotx' => 'extension:zip-package',
            '.docm' => 'extension:zip-package',
            '.odt' => 'extension:zip-package',
            '.ods' => 'extension:zip-package',
            '.odp' => 'extension:zip-package',
            '.epub' => 'extension:zip-package',
        ];

        $reasons = [];
        foreach ($extensionMap as $suffix => $reason) {
            if (str_ends_with($lower, $suffix)) {
                $reasons[] = $reason;
                break;
            }
        }

        $unsupported = self::nestedUnsupportedCompressionNameCandidateReasons($entryName);
        if ($unsupported !== []) {
            $reasons = array_merge($reasons, $unsupported);
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return list<string>
     */
    private static function nestedUnsupportedCompressionNameCandidateReasons(string $entryName): array
    {
        $candidate = self::unsupportedCompressionNameCandidate($entryName);
        if ($candidate === null) {
            return [];
        }

        $reason = 'extension:unsupported-' . $candidate['format'];
        if ($candidate['kind'] !== null) {
            $reason .= '-' . $candidate['kind'];
        }

        return [$reason];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function nestedUnsupportedCompressionPolicy(string $entryName, ?string $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (self::unsupportedCompressionSignature($data) === null
            && self::unsupportedCompressionNameCandidate($entryName) === null
        ) {
            return null;
        }

        return self::inspectUnsupportedCompressionStreamPolicy($data, $entryName);
    }

    /**
     * @return list<string>
     */
    private static function candidateTarFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_TAR;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_TAR;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_TAR;
        }

        $formats[] = self::FORMAT_TAR;
        $formats[] = self::FORMAT_RAW_DEFLATE_TAR;

        return array_values(array_unique($formats));
    }

    /**
     * @return list<string>
     */
    private static function candidateZipFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_ZIP;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_ZIP;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_ZIP;
        }

        if (self::startsWithZipHeader($bytes)) {
            $formats[] = self::FORMAT_ZIP;
        }

        $formats[] = self::FORMAT_RAW_DEFLATE_ZIP;

        return array_values(array_unique($formats));
    }

    private static function startsWithZipHeader(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "PK\x05\x06");
    }

    private static function startsWithGzipHeader(string $bytes): bool
    {
        return strlen($bytes) >= 2 && ord($bytes[0]) === 0x1f && ord($bytes[1]) === 0x8b;
    }

    private static function startsWithZlibHeader(string $bytes): bool
    {
        if (strlen($bytes) < 2) {
            return false;
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);

        return ($cmf & 0x0f) === 8
            && (($cmf >> 4) & 0x0f) <= 7
            && (($cmf << 8) + $flg) % 31 === 0;
    }

    private static function startsWithLz4Header(string $bytes): bool
    {
        if (strlen($bytes) < 4) {
            return false;
        }

        $values = unpack('Vmagic', substr($bytes, 0, 4));
        if (!is_array($values)) {
            return false;
        }

        $magic = (int) $values['magic'];

        return $magic === 0x184d2204 || ($magic >= 0x184d2a50 && $magic <= 0x184d2a5f);
    }

    private static function startsWithTarHeader(string $bytes): bool
    {
        return strlen($bytes) >= 512
            && (
                substr($bytes, 257, 6) === "ustar\0"
                || substr($bytes, 257, 8) === "ustar  \0"
            );
    }

    /**
     * @return array{
     *     format:string,
     *     name:string,
     *     signatureBytesHex:string,
     *     streamHeaderSize:int,
     *     streamFlagsHex:?string,
     *     blockSize100k:?int
     * }|null
     */
    private static function unsupportedCompressionSignature(string $bytes): ?array
    {
        if (strlen($bytes) >= 4
            && str_starts_with($bytes, 'BZh')
            && ord($bytes[3]) >= ord('1')
            && ord($bytes[3]) <= ord('9')
        ) {
            return [
                'format' => 'bzip2',
                'name' => 'bzip2',
                'signatureBytesHex' => bin2hex(substr($bytes, 0, 4)),
                'streamHeaderSize' => 4,
                'streamFlagsHex' => null,
                'blockSize100k' => (int) $bytes[3],
            ];
        }

        if (str_starts_with($bytes, "\xfd" . '7zXZ' . "\0")) {
            return [
                'format' => 'xz',
                'name' => 'xz',
                'signatureBytesHex' => bin2hex(substr($bytes, 0, 6)),
                'streamHeaderSize' => 12,
                'streamFlagsHex' => strlen($bytes) >= 8 ? bin2hex(substr($bytes, 6, 2)) : null,
                'blockSize100k' => null,
            ];
        }

        if (str_starts_with($bytes, "\x28\xb5\x2f\xfd")) {
            return [
                'format' => 'zstandard',
                'name' => 'zstandard',
                'signatureBytesHex' => bin2hex(substr($bytes, 0, 4)),
                'streamHeaderSize' => 5,
                'streamFlagsHex' => strlen($bytes) >= 5 ? bin2hex($bytes[4]) : null,
                'blockSize100k' => null,
            ];
        }

        return null;
    }

    /**
     * @return array{kind:string, format:string, reason:string}|null
     */
    private static function supportedPackageSourceNameCandidate(string $sourceName): ?array
    {
        $lower = strtolower($sourceName);
        $zipPackageExtensions = [
            '.docx',
            '.dotx',
            '.docm',
            '.odt',
            '.ods',
            '.odp',
            '.epub',
        ];
        $zipPackageCompressionSuffixes = [
            '.gz' => [self::FORMAT_GZIP_ZIP, 'extension:gzip-zip-package'],
            '.zlib' => [self::FORMAT_ZLIB_ZIP, 'extension:zlib-zip-package'],
            '.deflate' => [self::FORMAT_RAW_DEFLATE_ZIP, 'extension:raw-deflate-zip-package'],
            '.lz4' => [self::FORMAT_LZ4_ZIP, 'extension:lz4-zip-package'],
        ];

        foreach ($zipPackageCompressionSuffixes as $compressionSuffix => [$format, $reason]) {
            foreach ($zipPackageExtensions as $packageExtension) {
                if (str_ends_with($lower, $packageExtension . $compressionSuffix)) {
                    return [
                        'kind' => self::PACKAGE_KIND_ZIP,
                        'format' => $format,
                        'reason' => $reason,
                    ];
                }
            }
        }

        $suffixes = [
            '.tar.gz' => [self::PACKAGE_KIND_TAR, self::FORMAT_GZIP_TAR, 'extension:gzip-tar'],
            '.tgz' => [self::PACKAGE_KIND_TAR, self::FORMAT_GZIP_TAR, 'extension:gzip-tar'],
            '.tar.zlib' => [self::PACKAGE_KIND_TAR, self::FORMAT_ZLIB_TAR, 'extension:zlib-tar'],
            '.tar.deflate' => [self::PACKAGE_KIND_TAR, self::FORMAT_RAW_DEFLATE_TAR, 'extension:raw-deflate-tar'],
            '.tar.lz4' => [self::PACKAGE_KIND_TAR, self::FORMAT_LZ4_TAR, 'extension:lz4-tar'],
            '.tlz4' => [self::PACKAGE_KIND_TAR, self::FORMAT_LZ4_TAR, 'extension:lz4-tar'],
            '.tar' => [self::PACKAGE_KIND_TAR, self::FORMAT_TAR, 'extension:tar'],
            '.zip.gz' => [self::PACKAGE_KIND_ZIP, self::FORMAT_GZIP_ZIP, 'extension:gzip-zip'],
            '.zip.zlib' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZLIB_ZIP, 'extension:zlib-zip'],
            '.zip.deflate' => [self::PACKAGE_KIND_ZIP, self::FORMAT_RAW_DEFLATE_ZIP, 'extension:raw-deflate-zip'],
            '.zip.lz4' => [self::PACKAGE_KIND_ZIP, self::FORMAT_LZ4_ZIP, 'extension:lz4-zip'],
            '.zip' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip'],
            '.docx' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.dotx' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.docm' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.odt' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.ods' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.odp' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
            '.epub' => [self::PACKAGE_KIND_ZIP, self::FORMAT_ZIP, 'extension:zip-package'],
        ];

        foreach ($suffixes as $suffix => [$kind, $format, $reason]) {
            if (str_ends_with($lower, $suffix)) {
                return [
                    'kind' => $kind,
                    'format' => $format,
                    'reason' => $reason,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{format:string, kind:?string, reason:string}|null
     */
    private static function unsupportedCompressionNameCandidate(string $sourceName): ?array
    {
        $lower = strtolower($sourceName);
        $zipPackageExtensions = [
            '.docx',
            '.dotx',
            '.docm',
            '.odt',
            '.ods',
            '.odp',
            '.epub',
        ];
        $zipPackageCompressionSuffixes = [
            '.bz2' => 'bzip2',
            '.xz' => 'xz',
            '.zst' => 'zstandard',
            '.zstd' => 'zstandard',
        ];

        foreach ($zipPackageCompressionSuffixes as $compressionSuffix => $format) {
            foreach ($zipPackageExtensions as $packageExtension) {
                if (str_ends_with($lower, $packageExtension . $compressionSuffix)) {
                    return [
                        'format' => $format,
                        'kind' => self::PACKAGE_KIND_ZIP,
                        'reason' => 'extension:unsupported-' . $format . '-zip-package',
                    ];
                }
            }
        }

        $compressedSuffixes = [
            '.tar.bz2' => ['bzip2', self::PACKAGE_KIND_TAR, 'extension:unsupported-bzip2-tar'],
            '.tbz2' => ['bzip2', self::PACKAGE_KIND_TAR, 'extension:unsupported-bzip2-tar'],
            '.tbz' => ['bzip2', self::PACKAGE_KIND_TAR, 'extension:unsupported-bzip2-tar'],
            '.zip.bz2' => ['bzip2', self::PACKAGE_KIND_ZIP, 'extension:unsupported-bzip2-zip'],
            '.tar.xz' => ['xz', self::PACKAGE_KIND_TAR, 'extension:unsupported-xz-tar'],
            '.txz' => ['xz', self::PACKAGE_KIND_TAR, 'extension:unsupported-xz-tar'],
            '.zip.xz' => ['xz', self::PACKAGE_KIND_ZIP, 'extension:unsupported-xz-zip'],
            '.tar.zst' => ['zstandard', self::PACKAGE_KIND_TAR, 'extension:unsupported-zstandard-tar'],
            '.tar.zstd' => ['zstandard', self::PACKAGE_KIND_TAR, 'extension:unsupported-zstandard-tar'],
            '.tzst' => ['zstandard', self::PACKAGE_KIND_TAR, 'extension:unsupported-zstandard-tar'],
            '.zip.zst' => ['zstandard', self::PACKAGE_KIND_ZIP, 'extension:unsupported-zstandard-zip'],
            '.zip.zstd' => ['zstandard', self::PACKAGE_KIND_ZIP, 'extension:unsupported-zstandard-zip'],
            '.bz2' => ['bzip2', null, 'extension:unsupported-bzip2'],
            '.xz' => ['xz', null, 'extension:unsupported-xz'],
            '.zst' => ['zstandard', null, 'extension:unsupported-zstandard'],
            '.zstd' => ['zstandard', null, 'extension:unsupported-zstandard'],
        ];

        foreach ($compressedSuffixes as $suffix => [$format, $kind, $reason]) {
            if (str_ends_with($lower, $suffix)) {
                return [
                    'format' => $format,
                    'kind' => $kind,
                    'reason' => $reason,
                ];
            }
        }

        return null;
    }

    private static function boundedPrintablePreview(string $bytes, int $maxBytes): string
    {
        if ($maxBytes <= 0) {
            throw new \RuntimeException('Printable preview byte limit must be positive');
        }

        $preview = substr($bytes, 0, $maxBytes);
        $printable = '';
        for ($index = 0, $length = strlen($preview); $index < $length; $index++) {
            $value = ord($preview[$index]);
            $printable .= $value >= 0x20 && $value <= 0x7e
                ? chr($value)
                : sprintf('\\x%02x', $value);
        }

        return $printable;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function formatDetectionDetails(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $parts = [];
        foreach ($errors as $format => $message) {
            $parts[] = "{$format}: {$message}";
        }

        return implode('; ', $parts);
    }

    private static function assertLimit(?int $limit, string $label): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }
}
