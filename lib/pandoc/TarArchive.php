<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TarArchive
{
    private const BLOCK_SIZE = 512;
    private const USTAR_MAGIC = "ustar\0";
    private const USTAR_VERSION = '00';
    private const TYPE_REGULAR = '0';
    private const TYPE_DIRECTORY = '5';
    private const TYPE_HARD_LINK = '1';
    private const TYPE_SYMBOLIC_LINK = '2';
    private const TYPE_CHARACTER_DEVICE = '3';
    private const TYPE_BLOCK_DEVICE = '4';
    private const TYPE_FIFO = '6';
    private const TYPE_PAX_EXTENDED = 'x';
    private const TYPE_PAX_GLOBAL = 'g';
    private const TYPE_GNU_LONG_NAME = 'L';
    private const TYPE_GNU_LONG_LINK = 'K';
    private const TYPE_GNU_SPARSE = 'S';
    private const TYPE_GNU_MULTIVOLUME = 'M';
    private const TYPE_GNU_DUMPDIR = 'D';
    private const TYPE_CONTIGUOUS_FILE = '7';
    private const PAX_HDRCHARSET_BINARY = 'BINARY';
    private const PAX_HDRCHARSET_UTF8 = 'ISO-IR 10646 2000 UTF-8';
    private const PAX_HDRCHARSET_UTF8_SHORT = 'UTF-8';
    private const BOUNDED_UNICODE_CASE_FOLD_FALLBACKS = [
        "\u{00C9}" => "\u{00E9}",
    ];
    private const BOUNDED_LATIN_COMPOSITION_FALLBACKS = [
        "e\u{0301}" => "\u{00E9}",
    ];

    /**
     * @param array<string, TarArchiveEntry> $entriesByName
     * @param list<TarArchiveEntry> $entries
     * @param array<string, string> $globalPaxHeaders
     */
    private function __construct(
        private readonly string $bytes,
        private readonly array $entriesByName,
        private readonly array $entries,
        private readonly array $globalPaxHeaders,
    ) {
    }

    public static function fromString(string $bytes, ?int $maxUnpackedBytes = null): self
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if ($maxUnpackedBytes !== null && $maxUnpackedBytes < 0) {
            throw new \RuntimeException('TAR max unpacked byte limit must not be negative');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $entries = [];
        $entriesByName = [];
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $totalUnpackedBytes = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                throw new \RuntimeException('TAR GNU long-link metadata is not supported by the pandoc archive reader');
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $deletedPaxHeaderKeys = self::deletedPaxHeaderKeys($pendingPaxHeaders);
            self::assertSafePath($name, 'TAR entry name');
            $size = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $size, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($size);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                throw new \RuntimeException("TAR link entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag === self::TYPE_GNU_SPARSE || self::hasSparsePaxHeaders($metadataHeaders)) {
                throw new \RuntimeException("TAR sparse file entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag === self::TYPE_GNU_MULTIVOLUME || self::hasMultiVolumePaxHeaders($metadataHeaders)) {
                throw new \RuntimeException("TAR multi-volume entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag === self::TYPE_GNU_DUMPDIR || self::hasIncrementalSnapshotPaxHeaders($metadataHeaders)) {
                throw new \RuntimeException("TAR incremental snapshot entries are not supported by the pandoc archive reader: {$name}");
            }

            if ($typeFlag !== self::TYPE_REGULAR
                && $typeFlag !== self::TYPE_DIRECTORY
                && $typeFlag !== self::TYPE_CONTIGUOUS_FILE
            ) {
                throw new \RuntimeException("Unsupported TAR entry type {$typeFlag} for {$name}");
            }

            $entryType = TarArchiveEntry::TYPE_FILE;
            if ($typeFlag === self::TYPE_DIRECTORY) {
                $entryType = TarArchiveEntry::TYPE_DIRECTORY;
            } elseif ($typeFlag === self::TYPE_REGULAR && str_ends_with($name, '/')) {
                if ($size !== 0) {
                    throw new \RuntimeException("TAR directory-like regular entry {$name} must not contain payload bytes");
                }
                $entryType = TarArchiveEntry::TYPE_DIRECTORY;
            }

            if ($entryType === TarArchiveEntry::TYPE_DIRECTORY && $size !== 0) {
                throw new \RuntimeException("TAR directory entry {$name} must not contain payload bytes");
            }

            if ($entryType === TarArchiveEntry::TYPE_FILE) {
                $totalUnpackedBytes += $size;
                if ($maxUnpackedBytes !== null && $totalUnpackedBytes > $maxUnpackedBytes) {
                    throw new \RuntimeException('TAR archive exceeds the configured unpacked byte limit');
                }
            }

            if (isset($entriesByName[$name])) {
                throw new \RuntimeException("Duplicate TAR archive entry: {$name}");
            }

            $entry = new TarArchiveEntry(
                $name,
                $entryType,
                $size,
                self::resolvedModifiedAtFromHeader($header, $metadataHeaders),
                self::resolvedAccessedAtFromHeader($metadataHeaders),
                self::resolvedChangedAtFromHeader($metadataHeaders),
                self::resolvedCreatedAtFromHeader($metadataHeaders),
                self::readNumericField(substr($header, 100, 8), "TAR mode for {$name}"),
                self::resolvedUidFromHeader($header, $metadataHeaders, $name),
                self::resolvedGidFromHeader($header, $metadataHeaders, $name),
                self::trimNullField(substr($header, 157, 100)),
                self::resolvedUserNameFromHeader($header, $metadataHeaders),
                self::resolvedGroupNameFromHeader($header, $metadataHeaders),
                $metadataHeaders,
                $dataOffset,
                $globalPaxHeaders,
                $pendingPaxHeaders,
                $deletedPaxHeaderKeys,
                $nameSource,
                $pendingGnuLongName,
                $typeFlag
            );

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return new self($bytes, $entriesByName, $entries, $globalPaxHeaders);
    }

    /**
     * @return array{
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
     *         metadataKind:?string,
     *         metadataValue:?string,
     *         metadataValueSize:?int,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>
     * }
     */
    public static function checksumPolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entries = [];
        $headerRecordCount = 0;
        $entryCount = 0;
        $metadataRecordCount = 0;
        $unsignedChecksumRecordCount = 0;
        $signedChecksumRecordCount = 0;
        $ambiguousChecksumRecordCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            $checksum = self::validateHeaderChecksum($header);
            $headerRecordCount++;
            if ($checksum['matchesUnsigned']) {
                $unsignedChecksumRecordCount++;
            }
            if (!$checksum['matchesUnsigned'] && $checksum['matchesSigned']) {
                $signedChecksumRecordCount++;
            }
            if ($checksum['matchesUnsigned'] && $checksum['matchesSigned']) {
                $ambiguousChecksumRecordCount++;
            }

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $metadataRecordCount++;
                $entries[] = self::checksumPolicyRecord(
                    $checksum,
                    $headerOffset,
                    $dataOffset,
                    $nextCursor,
                    $headerSize,
                    $headerSize,
                    self::trimNullField(substr($header, 0, 100)),
                    'gnu-long-name',
                    $typeFlag,
                    TarArchiveEntry::NAME_SOURCE_HEADER,
                    [
                        'metadataKind' => 'gnu-long-name',
                        'metadataValue' => $pendingGnuLongName,
                    ]
                );
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $metadataRecordCount++;
                $entries[] = self::checksumPolicyRecord(
                    $checksum,
                    $headerOffset,
                    $dataOffset,
                    $nextCursor,
                    $headerSize,
                    $headerSize,
                    self::trimNullField(substr($header, 0, 100)),
                    'gnu-long-link',
                    $typeFlag,
                    TarArchiveEntry::NAME_SOURCE_HEADER,
                    [
                        'metadataKind' => 'gnu-long-link',
                        'metadataValue' => $pendingGnuLongLink,
                    ]
                );
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }

                $paxHeaderKeys = array_keys($headers);
                sort($paxHeaderKeys);
                $metadataRecordCount++;
                $entries[] = self::checksumPolicyRecord(
                    $checksum,
                    $headerOffset,
                    $dataOffset,
                    $nextCursor,
                    $headerSize,
                    $headerSize,
                    self::trimNullField(substr($header, 0, 100)),
                    $typeFlag === self::TYPE_PAX_EXTENDED ? 'pax-local' : 'pax-global',
                    $typeFlag,
                    TarArchiveEntry::NAME_SOURCE_HEADER,
                    [
                        'metadataKind' => $typeFlag === self::TYPE_PAX_EXTENDED ? 'pax-local' : 'pax-global',
                        'paxHeaderKeys' => $paxHeaderKeys,
                    ]
                );
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($pendingGnuLongLink !== null && $typeFlag !== self::TYPE_HARD_LINK && $typeFlag !== self::TYPE_SYMBOLIC_LINK) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $recordMetadata = [];
            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                $linkTarget = self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink);
                $linkTargetSource = self::resolvedLinkTargetSourceFromHeader($metadataHeaders, $pendingGnuLongLink);
                self::assertSafePath($linkTarget, 'TAR link target');
                $recordMetadata = [
                    'linkTarget' => $linkTarget,
                    'linkTargetSource' => $linkTargetSource,
                ];
            }

            $entryCount++;
            $entries[] = self::checksumPolicyRecord(
                $checksum,
                $headerOffset,
                $dataOffset,
                $nextCursor,
                $entrySize,
                $headerSize,
                $name,
                self::checksumPolicyTypeName($typeFlag),
                $typeFlag,
                $nameSource,
                $recordMetadata
            );
            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'type' => 'tar-checksum-policy',
            'headerRecordCount' => $headerRecordCount,
            'entryCount' => $entryCount,
            'metadataRecordCount' => $metadataRecordCount,
            'unsignedChecksumRecordCount' => $unsignedChecksumRecordCount,
            'signedChecksumRecordCount' => $signedChecksumRecordCount,
            'ambiguousChecksumRecordCount' => $ambiguousChecksumRecordCount,
            'handoffPolicy' => 'within-thresholds',
            'extractionPolicy' => 'checksum-provenance-only-no-extraction',
            'diagnostics' => $signedChecksumRecordCount > 0 ? ['tar-header-historic-signed-checksum'] : [],
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function linkPolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $seenEntryNames = [];
        $entryCount = 0;
        $linkEntries = [];
        $hardLinkCount = 0;
        $symbolicLinkCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                if ($entrySize !== 0) {
                    throw new \RuntimeException("TAR link entry {$name} must not contain payload bytes");
                }

                $linkTarget = self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink);
                $linkTargetSource = self::resolvedLinkTargetSourceFromHeader($metadataHeaders, $pendingGnuLongLink);
                self::assertSafePath($linkTarget, 'TAR link target');

                $isHardLink = $typeFlag === self::TYPE_HARD_LINK;
                if ($isHardLink) {
                    $hardLinkCount++;
                } else {
                    $symbolicLinkCount++;
                }

                $diagnostics = ['tar-link-entry-not-extracted'];
                $targetEntryExists = isset($seenEntryNames[$linkTarget]);
                if ($isHardLink && !$targetEntryExists) {
                    $diagnostics[] = 'hard-link-target-not-yet-seen';
                }

                $linkEntries[] = [
                    'name' => $name,
                    'linkType' => $isHardLink ? 'hard-link' : 'symbolic-link',
                    'linkTarget' => $linkTarget,
                    'linkTargetSource' => $linkTargetSource,
                    'targetEntryExists' => $targetEntryExists,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'policy' => 'blocked',
                    'diagnostics' => $diagnostics,
                ];
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $seenEntryNames[$name] = true;
            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'linkEntryCount' => count($linkEntries),
            'hardLinkCount' => $hardLinkCount,
            'symbolicLinkCount' => $symbolicLinkCount,
            'extractionPolicy' => $linkEntries === [] ? 'no-link-entries' : 'link-entries-blocked',
            'entries' => $linkEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function specialFilePolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $specialEntries = [];
        $characterDeviceCount = 0;
        $blockDeviceCount = 0;
        $fifoCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $specialType = self::specialFileType($typeFlag);
            if ($specialType !== null) {
                if ($entrySize !== 0) {
                    throw new \RuntimeException("TAR special file entry {$name} must not contain payload bytes");
                }

                $deviceMajor = null;
                $deviceMinor = null;
                $deviceNumberSource = 'none';
                if ($typeFlag === self::TYPE_CHARACTER_DEVICE || $typeFlag === self::TYPE_BLOCK_DEVICE) {
                    $deviceMajor = self::resolvedDeviceMajorFromHeader($header, $metadataHeaders, $name);
                    $deviceMinor = self::resolvedDeviceMinorFromHeader($header, $metadataHeaders, $name);
                    $deviceNumberSource = self::resolvedDeviceNumberSource($metadataHeaders);
                }

                if ($typeFlag === self::TYPE_CHARACTER_DEVICE) {
                    $characterDeviceCount++;
                } elseif ($typeFlag === self::TYPE_BLOCK_DEVICE) {
                    $blockDeviceCount++;
                } else {
                    $fifoCount++;
                }

                $specialEntries[] = [
                    'name' => $name,
                    'specialType' => $specialType,
                    'typeFlag' => $typeFlag,
                    'deviceMajor' => $deviceMajor,
                    'deviceMinor' => $deviceMinor,
                    'deviceNumberSource' => $deviceNumberSource,
                    'payloadSize' => $entrySize,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'policy' => 'blocked',
                    'diagnostics' => [
                        'tar-special-file-not-extracted',
                        'tar-' . $specialType . '-not-extracted',
                    ],
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'specialFileEntryCount' => count($specialEntries),
            'characterDeviceCount' => $characterDeviceCount,
            'blockDeviceCount' => $blockDeviceCount,
            'fifoCount' => $fifoCount,
            'extractionPolicy' => $specialEntries === [] ? 'no-special-file-entries' : 'special-file-entries-blocked',
            'entries' => $specialEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function sparsePolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $sparseEntries = [];
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $isGnuSparseType = $typeFlag === self::TYPE_GNU_SPARSE;
            $sparseHeaderKeys = self::sparsePaxHeaderKeys($metadataHeaders);
            if ($isGnuSparseType || $sparseHeaderKeys !== []) {
                $families = self::sparseHeaderFamilies($metadataHeaders, $isGnuSparseType);
                $realSize = self::sparseRealSize($metadataHeaders);
                $sparseMapSource = self::sparseMapSource($metadataHeaders);
                $sparseMapSegments = self::sparseMapSegments($metadataHeaders, $realSize);
                $sparseMapPayloadBytes = 0;
                foreach ($sparseMapSegments as $segment) {
                    if ($sparseMapPayloadBytes > PHP_INT_MAX - $segment['length']) {
                        throw new \RuntimeException('TAR PAX sparse map payload byte count is too large for this PHP runtime');
                    }
                    $sparseMapPayloadBytes += $segment['length'];
                }
                $diagnostics = ['tar-sparse-entry-not-extracted'];
                foreach ($families as $family) {
                    $diagnostics[] = $family;
                }

                $sparseEntries[] = [
                    'name' => $name,
                    'sparseType' => $isGnuSparseType ? 'gnu-sparse-typeflag' : 'pax-sparse',
                    'sparseHeaderFamilies' => $families,
                    'sparseHeaderKeys' => $sparseHeaderKeys,
                    'realSize' => $realSize,
                    'sparseMapSource' => $sparseMapSource,
                    'sparseMapSegments' => $sparseMapSegments,
                    'sparseMapSegmentCount' => count($sparseMapSegments),
                    'sparseMapPayloadBytes' => $sparseMapPayloadBytes,
                    'payloadSize' => $entrySize,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'policy' => 'blocked',
                    'diagnostics' => $diagnostics,
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'sparseEntryCount' => count($sparseEntries),
            'extractionPolicy' => $sparseEntries === [] ? 'no-sparse-entries' : 'sparse-entries-blocked',
            'entries' => $sparseEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function multiVolumePolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $multiVolumeEntries = [];
        $typeflagEntryCount = 0;
        $paxMetadataEntryCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $isGnuMultiVolumeType = $typeFlag === self::TYPE_GNU_MULTIVOLUME;
            $volumeHeaderKeys = self::multiVolumePaxHeaderKeys($metadataHeaders);
            if ($isGnuMultiVolumeType || $volumeHeaderKeys !== []) {
                if ($isGnuMultiVolumeType) {
                    $typeflagEntryCount++;
                }
                if ($volumeHeaderKeys !== []) {
                    $paxMetadataEntryCount++;
                }

                $families = self::multiVolumeHeaderFamilies($metadataHeaders, $isGnuMultiVolumeType);
                $continuationOffset = self::multiVolumeContinuationOffset($header, $metadataHeaders);
                $diagnostics = ['tar-multi-volume-entry-not-extracted'];
                foreach ($families as $family) {
                    $diagnostics[] = $family;
                }

                $multiVolumeEntries[] = [
                    'name' => $name,
                    'multiVolumeType' => $isGnuMultiVolumeType ? 'gnu-multivolume-typeflag' : 'pax-gnu-volume-metadata',
                    'volumeHeaderFamilies' => $families,
                    'volumeHeaderKeys' => $volumeHeaderKeys,
                    'continuationOffset' => $continuationOffset['offset'],
                    'continuationOffsetSource' => $continuationOffset['source'],
                    'originalName' => self::multiVolumeOriginalName($metadataHeaders),
                    'declaredVolumeSize' => self::multiVolumeDeclaredSize($metadataHeaders),
                    'payloadSize' => $entrySize,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'policy' => 'blocked',
                    'diagnostics' => $diagnostics,
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'multiVolumeEntryCount' => count($multiVolumeEntries),
            'typeflagEntryCount' => $typeflagEntryCount,
            'paxMetadataEntryCount' => $paxMetadataEntryCount,
            'extractionPolicy' => $multiVolumeEntries === [] ? 'no-multi-volume-entries' : 'multi-volume-entries-blocked',
            'entries' => $multiVolumeEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function incrementalSnapshotPolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $incrementalEntries = [];
        $typeflagEntryCount = 0;
        $paxMetadataEntryCount = 0;
        $dumpdirRecordCount = 0;
        $deletedRecordCount = 0;
        $directoryRecordCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $isGnuDumpdirType = $typeFlag === self::TYPE_GNU_DUMPDIR;
            $incrementalHeaderKeys = self::incrementalSnapshotPaxHeaderKeys($metadataHeaders);
            if ($isGnuDumpdirType || $incrementalHeaderKeys !== []) {
                if ($isGnuDumpdirType) {
                    $typeflagEntryCount++;
                }
                if ($incrementalHeaderKeys !== []) {
                    $paxMetadataEntryCount++;
                }

                $families = self::incrementalSnapshotHeaderFamilies($metadataHeaders, $isGnuDumpdirType);
                $records = self::incrementalSnapshotDumpdirRecords(
                    substr($bytes, $dataOffset, $entrySize),
                    $metadataHeaders,
                    $isGnuDumpdirType
                );
                $entryDeletedRecordCount = 0;
                $entryDirectoryRecordCount = 0;
                foreach ($records as $record) {
                    if ($record['action'] === 'deleted') {
                        $entryDeletedRecordCount++;
                    } elseif ($record['action'] === 'directory') {
                        $entryDirectoryRecordCount++;
                    }
                }

                $dumpdirRecordCount += count($records);
                $deletedRecordCount += $entryDeletedRecordCount;
                $directoryRecordCount += $entryDirectoryRecordCount;
                $diagnostics = ['tar-incremental-snapshot-not-extracted'];
                foreach ($families as $family) {
                    $diagnostics[] = $family;
                }

                $incrementalEntries[] = [
                    'name' => $name,
                    'incrementalType' => $isGnuDumpdirType ? 'gnu-dumpdir-typeflag' : 'pax-gnu-dumpdir-metadata',
                    'incrementalHeaderFamilies' => $families,
                    'incrementalHeaderKeys' => $incrementalHeaderKeys,
                    'dumpdirRecordCount' => count($records),
                    'deletedRecordCount' => $entryDeletedRecordCount,
                    'directoryRecordCount' => $entryDirectoryRecordCount,
                    'dumpdirRecords' => $records,
                    'payloadSize' => $entrySize,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'policy' => 'blocked',
                    'diagnostics' => $diagnostics,
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'incrementalEntryCount' => count($incrementalEntries),
            'typeflagEntryCount' => $typeflagEntryCount,
            'paxMetadataEntryCount' => $paxMetadataEntryCount,
            'dumpdirRecordCount' => $dumpdirRecordCount,
            'deletedRecordCount' => $deletedRecordCount,
            'directoryRecordCount' => $directoryRecordCount,
            'extractionPolicy' => $incrementalEntries === [] ? 'no-incremental-snapshot-entries' : 'incremental-snapshot-entries-blocked',
            'entries' => $incrementalEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function paxDuplicateKeywordPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $paxEntryCount = 0;
        $duplicateEntries = [];
        $duplicateKeywordCount = 0;
        $duplicateRecordCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }

                $parsed = self::parsePaxHeadersWithDuplicateReport(substr($bytes, $dataOffset, $headerSize));
                $headers = $parsed['headers'];
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }

                $paxEntryCount++;
                if ($parsed['duplicateRecords'] !== []) {
                    $entryDuplicateRecordCount = 0;
                    foreach ($parsed['duplicateRecords'] as $record) {
                        $entryDuplicateRecordCount += $record['occurrences'] - 1;
                    }

                    $duplicateKeywordCount += count($parsed['duplicateRecords']);
                    $duplicateRecordCount += $entryDuplicateRecordCount;
                    $duplicateEntries[] = [
                        'paxEntryName' => self::resolvedNameFromHeader($header, [], null),
                        'paxType' => $typeFlag === self::TYPE_PAX_EXTENDED ? 'local' : 'global',
                        'headerOffset' => $headerOffset,
                        'dataOffset' => $dataOffset,
                        'payloadSize' => $headerSize,
                        'recordCount' => count($parsed['records']),
                        'duplicateKeywordCount' => count($parsed['duplicateRecords']),
                        'duplicateRecordCount' => $entryDuplicateRecordCount,
                        'duplicateKeywords' => array_map(
                            static fn (array $record): string => $record['keyword'],
                            $parsed['duplicateRecords']
                        ),
                        'duplicateRecords' => $parsed['duplicateRecords'],
                        'policy' => 'blocked',
                        'diagnostics' => ['tar-pax-duplicate-keyword-not-extracted'],
                    ];
                }

                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'paxEntryCount' => $paxEntryCount,
            'duplicatePaxEntryCount' => count($duplicateEntries),
            'duplicateKeywordCount' => $duplicateKeywordCount,
            'duplicateRecordCount' => $duplicateRecordCount,
            'extractionPolicy' => $duplicateEntries === [] ? 'no-duplicate-pax-keywords' : 'duplicate-pax-keywords-blocked',
            'entries' => $duplicateEntries,
        ];
    }

    /**
     * @return array{
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
     *     }>
     * }
     */
    public static function paxFilesystemMetadataPolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $metadataEntries = [];
        $metadataRecordCount = 0;
        $extendedAttributeRecordCount = 0;
        $accessControlListRecordCount = 0;
        $fileFlagRecordCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $records = self::paxFilesystemMetadataRecords($metadataHeaders, $globalPaxHeaders, $pendingPaxHeaders);
            if ($records !== []) {
                $entryExtendedAttributeRecordCount = 0;
                $entryAccessControlListRecordCount = 0;
                $entryFileFlagRecordCount = 0;
                foreach ($records as $record) {
                    if ($record['category'] === 'extended-attribute') {
                        $entryExtendedAttributeRecordCount++;
                    } elseif ($record['category'] === 'access-control-list') {
                        $entryAccessControlListRecordCount++;
                    } elseif ($record['category'] === 'file-flags') {
                        $entryFileFlagRecordCount++;
                    }
                }

                $metadataRecordCount += count($records);
                $extendedAttributeRecordCount += $entryExtendedAttributeRecordCount;
                $accessControlListRecordCount += $entryAccessControlListRecordCount;
                $fileFlagRecordCount += $entryFileFlagRecordCount;

                $metadataEntries[] = [
                    'name' => $name,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'metadataRecordCount' => count($records),
                    'extendedAttributeRecordCount' => $entryExtendedAttributeRecordCount,
                    'accessControlListRecordCount' => $entryAccessControlListRecordCount,
                    'fileFlagRecordCount' => $entryFileFlagRecordCount,
                    'metadataKeys' => array_map(
                        static fn (array $record): string => $record['keyword'],
                        $records
                    ),
                    'records' => $records,
                    'policy' => 'metadata-only-not-applied',
                    'diagnostics' => ['tar-pax-filesystem-metadata-not-applied'],
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'entryCount' => $entryCount,
            'filesystemMetadataEntryCount' => count($metadataEntries),
            'metadataRecordCount' => $metadataRecordCount,
            'extendedAttributeRecordCount' => $extendedAttributeRecordCount,
            'accessControlListRecordCount' => $accessControlListRecordCount,
            'fileFlagRecordCount' => $fileFlagRecordCount,
            'extractionPolicy' => $metadataEntries === [] ? 'no-filesystem-pax-metadata' : 'filesystem-pax-metadata-not-applied',
            'entries' => $metadataEntries,
        ];
    }

    /**
     * @return array{
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
     *     entries:list<array{
     *         name:string,
     *         role:string,
     *         typeFlag:string,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         payloadSize:int,
     *         mode:int,
     *         modeOctal:string,
     *         permissionBits:int,
     *         specialBits:int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         uidSource:string,
     *         gidSource:string,
     *         userNameSource:string,
     *         groupNameSource:string,
     *         modeFlags:list<string>,
     *         ownerFlags:list<string>,
     *         attributeFlags:list<string>,
     *         modePolicy:string,
     *         ownerPolicy:string,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>
     * }
     */
    public static function filesystemAttributePolicyPreflight(string $bytes): array
    {
        if ($bytes === '') {
            throw new \RuntimeException('TAR archive is empty');
        }

        if (strlen($bytes) % self::BLOCK_SIZE !== 0) {
            throw new \RuntimeException('TAR archive length must be aligned to 512-byte records');
        }

        $cursor = 0;
        $length = strlen($bytes);
        $pendingPaxHeaders = [];
        $globalPaxHeaders = [];
        $pendingGnuLongName = null;
        $pendingGnuLongLink = null;
        $entryCount = 0;
        $attributeEntries = [];
        $modeFlagEntryCount = 0;
        $ownerMetadataEntryCount = 0;
        $nonRootOwnerEntryCount = 0;
        $regularExecutableEntryCount = 0;
        $worldWritableEntryCount = 0;
        $setuidEntryCount = 0;
        $setgidEntryCount = 0;
        $stickyEntryCount = 0;
        $sawEndMarker = false;

        while ($cursor < $length) {
            $headerOffset = $cursor;
            $header = substr($bytes, $cursor, self::BLOCK_SIZE);
            if (self::isZeroBlock($header)) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                self::assertTrailingZeroBlocks($bytes, $cursor);
                $sawEndMarker = true;
                break;
            }

            self::validateHeaderChecksum($header);

            $typeFlag = substr($header, 156, 1);
            if ($typeFlag === "\0" || $typeFlag === '') {
                $typeFlag = self::TYPE_REGULAR;
            }

            $headerSize = self::readNumericField(substr($header, 124, 12), 'TAR entry size');
            $dataOffset = $cursor + self::BLOCK_SIZE;
            self::assertRange($bytes, $dataOffset, $headerSize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($headerSize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }

            if ($typeFlag === self::TYPE_GNU_LONG_NAME) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongName = self::parseGnuLongName(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_GNU_LONG_LINK) {
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }

                $pendingGnuLongLink = self::parseGnuLongLink(substr($bytes, $dataOffset, $headerSize));
                $cursor = $nextCursor;
                continue;
            }

            if ($typeFlag === self::TYPE_PAX_EXTENDED || $typeFlag === self::TYPE_PAX_GLOBAL) {
                $headers = self::parsePaxHeaders(substr($bytes, $dataOffset, $headerSize));
                if ($pendingPaxHeaders !== []) {
                    throw new \RuntimeException('TAR PAX extended metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongName !== null) {
                    throw new \RuntimeException('TAR GNU long-name metadata is not followed by an archive entry');
                }
                if ($pendingGnuLongLink !== null) {
                    throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
                }
                if ($typeFlag === self::TYPE_PAX_EXTENDED) {
                    self::assertLinkPolicyLocalPaxHeaders($headers);
                    $pendingPaxHeaders = $headers;
                } else {
                    self::assertGlobalPaxHeaders($headers);
                    $globalPaxHeaders = self::applyPaxHeaderRecords($globalPaxHeaders, $headers);
                }
                $cursor = $nextCursor;
                continue;
            }

            $metadataHeaders = self::mergePaxHeaderRecords($globalPaxHeaders, $pendingPaxHeaders);
            $name = self::resolvedNameFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            $nameSource = self::resolvedNameSourceFromHeader($header, $metadataHeaders, $pendingGnuLongName);
            self::assertSafePath($name, 'TAR entry name');
            $entrySize = self::resolvedSizeFromHeader($header, $metadataHeaders);
            self::assertRange($bytes, $dataOffset, $entrySize, 'entry payload');
            $nextCursor = $dataOffset + self::paddedSize($entrySize);
            if ($nextCursor > $length) {
                throw new \RuntimeException('TAR entry payload extends beyond archive bytes');
            }
            $entryCount++;

            if ($typeFlag === self::TYPE_HARD_LINK || $typeFlag === self::TYPE_SYMBOLIC_LINK) {
                self::assertSafePath(
                    self::resolvedLinkTargetFromHeader($header, $metadataHeaders, $pendingGnuLongLink),
                    'TAR link target'
                );
            } elseif ($pendingGnuLongLink !== null) {
                throw new \RuntimeException('TAR GNU long-link metadata is not followed by a link entry');
            }

            $mode = self::readNumericField(substr($header, 100, 8), "TAR mode for {$name}");
            $uid = self::resolvedUidFromHeader($header, $metadataHeaders, $name);
            $gid = self::resolvedGidFromHeader($header, $metadataHeaders, $name);
            $userName = self::resolvedUserNameFromHeader($header, $metadataHeaders);
            $groupName = self::resolvedGroupNameFromHeader($header, $metadataHeaders);
            $modeFlags = self::filesystemAttributeModeFlags($mode, $typeFlag);
            $ownerFlags = self::filesystemAttributeOwnerFlags($uid, $gid, $userName, $groupName);

            if ($modeFlags !== []) {
                $modeFlagEntryCount++;
            }
            if ($ownerFlags !== []) {
                $ownerMetadataEntryCount++;
            }
            if (in_array('non-root-uid', $ownerFlags, true) || in_array('non-root-gid', $ownerFlags, true)) {
                $nonRootOwnerEntryCount++;
            }
            if (in_array('regular-executable', $modeFlags, true)) {
                $regularExecutableEntryCount++;
            }
            if (in_array('world-writable', $modeFlags, true)) {
                $worldWritableEntryCount++;
            }
            if (in_array('setuid', $modeFlags, true)) {
                $setuidEntryCount++;
            }
            if (in_array('setgid', $modeFlags, true)) {
                $setgidEntryCount++;
            }
            if (in_array('sticky', $modeFlags, true)) {
                $stickyEntryCount++;
            }

            if ($modeFlags !== [] || $ownerFlags !== []) {
                $attributeEntries[] = [
                    'name' => $name,
                    'role' => self::checksumPolicyTypeName($typeFlag),
                    'typeFlag' => $typeFlag,
                    'nameSource' => $nameSource,
                    'headerOffset' => $headerOffset,
                    'dataOffset' => $dataOffset,
                    'payloadSize' => $entrySize,
                    'mode' => $mode,
                    'modeOctal' => self::filesystemAttributeModeOctal($mode),
                    'permissionBits' => $mode & 0777,
                    'specialBits' => $mode & 07000,
                    'uid' => $uid,
                    'gid' => $gid,
                    'userName' => $userName,
                    'groupName' => $groupName,
                    'uidSource' => isset($metadataHeaders['uid']) ? 'pax-uid' : 'header-uid',
                    'gidSource' => isset($metadataHeaders['gid']) ? 'pax-gid' : 'header-gid',
                    'userNameSource' => isset($metadataHeaders['uname']) ? 'pax-uname' : 'header-uname',
                    'groupNameSource' => isset($metadataHeaders['gname']) ? 'pax-gname' : 'header-gname',
                    'modeFlags' => $modeFlags,
                    'ownerFlags' => $ownerFlags,
                    'attributeFlags' => array_merge($modeFlags, $ownerFlags),
                    'modePolicy' => $modeFlags === [] ? 'default-mode' : 'metadata-only-not-applied',
                    'ownerPolicy' => $ownerFlags === [] ? 'default-owner' : 'metadata-only-not-applied',
                    'policy' => 'metadata-only-not-applied',
                    'diagnostics' => self::filesystemAttributeDiagnostics($modeFlags, $ownerFlags),
                ];
            }

            $pendingPaxHeaders = [];
            $pendingGnuLongName = null;
            $pendingGnuLongLink = null;
            $cursor = $nextCursor;
        }

        if (!$sawEndMarker) {
            throw new \RuntimeException('TAR archive is missing the required two-block end marker');
        }

        return [
            'type' => 'tar-filesystem-attribute-policy',
            'entryCount' => $entryCount,
            'attributeEntryCount' => count($attributeEntries),
            'modeFlagEntryCount' => $modeFlagEntryCount,
            'ownerMetadataEntryCount' => $ownerMetadataEntryCount,
            'nonRootOwnerEntryCount' => $nonRootOwnerEntryCount,
            'regularExecutableEntryCount' => $regularExecutableEntryCount,
            'worldWritableEntryCount' => $worldWritableEntryCount,
            'setuidEntryCount' => $setuidEntryCount,
            'setgidEntryCount' => $setgidEntryCount,
            'stickyEntryCount' => $stickyEntryCount,
            'extractionPolicy' => $attributeEntries === [] ? 'no-filesystem-attributes' : 'filesystem-attributes-metadata-only',
            'diagnostics' => $attributeEntries === [] ? [] : ['tar-filesystem-attributes-not-applied'],
            'entries' => $attributeEntries,
        ];
    }

    /**
     * @return array{
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
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function duplicateEntryNamePreflight(string $bytes): array
    {
        $checksum = self::checksumPolicyPreflight($bytes);
        $entries = [];
        $entryNamesByName = [];
        foreach ($checksum['entries'] as $record) {
            if (($record['metadataKind'] ?? null) !== null) {
                continue;
            }

            $entryIndex = count($entries);
            $name = (string) $record['name'];
            $entryNamesByName[$name][] = $entryIndex;
            $entries[] = [
                'entryIndex' => $entryIndex,
                'name' => $name,
                'role' => (string) $record['role'],
                'typeFlag' => (string) $record['typeFlag'],
                'nameSource' => (string) $record['nameSource'],
                'headerOffset' => (int) $record['headerOffset'],
                'dataOffset' => (int) $record['dataOffset'],
                'payloadSize' => (int) $record['payloadSize'],
                'recordEndOffset' => (int) $record['recordEndOffset'],
                'hasDuplicateEntryName' => false,
                'duplicateEntryIndexes' => [],
                'issues' => [],
            ];
        }

        $duplicateGroups = [];
        $duplicateEntryIndexes = [];
        foreach ($entryNamesByName as $name => $entryIndexes) {
            if (count($entryIndexes) < 2) {
                continue;
            }

            $duplicateGroups[] = [
                'name' => $name,
                'count' => count($entryIndexes),
                'entryIndexes' => $entryIndexes,
                'nameSources' => array_map(
                    static fn (int $entryIndex): string => (string) $entries[$entryIndex]['nameSource'],
                    $entryIndexes
                ),
                'roles' => array_map(
                    static fn (int $entryIndex): string => (string) $entries[$entryIndex]['role'],
                    $entryIndexes
                ),
                'headerOffsets' => array_map(
                    static fn (int $entryIndex): int => (int) $entries[$entryIndex]['headerOffset'],
                    $entryIndexes
                ),
            ];

            foreach ($entryIndexes as $entryIndex) {
                $duplicateEntryIndexes[$entryIndex] = $entryIndexes;
            }
        }

        $duplicateEntries = [];
        foreach ($entries as $entryIndex => $entry) {
            $duplicateIndexes = $duplicateEntryIndexes[$entryIndex] ?? [];
            if ($duplicateIndexes === []) {
                continue;
            }

            $entries[$entryIndex]['hasDuplicateEntryName'] = true;
            $entries[$entryIndex]['duplicateEntryIndexes'] = $duplicateIndexes;
            $entries[$entryIndex]['issues'] = ['duplicate-tar-entry-name'];
            $duplicateEntries[] = $entries[$entryIndex];
        }

        $diagnostics = $duplicateEntries === [] ? [] : ['duplicate-tar-entry-names'];

        return [
            'type' => 'tar-duplicate-entry-name-policy',
            'entryCount' => count($entries),
            'scannedEntryCount' => (int) $checksum['entryCount'],
            'headerRecordCount' => (int) $checksum['headerRecordCount'],
            'metadataRecordCount' => (int) $checksum['metadataRecordCount'],
            'entryNames' => array_column($entries, 'name'),
            'hasDuplicateEntryNames' => $duplicateEntries !== [],
            'duplicateEntryNameGroupCount' => count($duplicateGroups),
            'duplicateEntryNameEntryCount' => count($duplicateEntries),
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => $diagnostics === [] ? 'metadata-only-no-extraction' : 'tar-duplicate-entry-name-review',
            'isSupportedByBoundedReader' => $diagnostics === [],
            'issues' => $diagnostics,
            'diagnostics' => $diagnostics,
            'duplicateEntryNameGroups' => $duplicateGroups,
            'duplicateEntries' => $duplicateEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @param list<array{name:string, data?:string, type?:string, modifiedAt?:int, accessedAt?:int, changedAt?:int, mode?:int, uid?:int, gid?:int, userName?:string, groupName?:string}> $entries
     * @param array{globalPaxHeaders?:array<string, string>} $options
     */
    public static function fromEntries(array $entries, array $options = []): self
    {
        return self::fromString(self::build($entries, $options));
    }

    /**
     * @param list<array{name:string, data?:string, type?:string, modifiedAt?:int, accessedAt?:int, changedAt?:int, createdAt?:int, mode?:int, uid?:int, gid?:int, userName?:string, groupName?:string}> $entries
     * @param array{globalPaxHeaders?:array<string, string>} $options
     */
    public static function build(array $entries, array $options = []): string
    {
        $bytes = '';
        $names = [];
        $globalPaxHeaders = self::normalizePaxHeaders($options['globalPaxHeaders'] ?? [], 'TAR global PAX headers');
        self::assertGlobalPaxHeaders($globalPaxHeaders);

        if ($globalPaxHeaders !== []) {
            $globalPayload = self::buildPaxPayload($globalPaxHeaders);
            $bytes .= self::buildHeader('GlobalHead/PaxGlobal', self::TYPE_PAX_GLOBAL, strlen($globalPayload), [
                'mode' => 0644,
                'uid' => 0,
                'gid' => 0,
                'modifiedAt' => 0,
                'userName' => '',
                'groupName' => '',
            ]);
            $bytes .= $globalPayload . str_repeat("\0", self::paddingSize(strlen($globalPayload)));
        }

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException("TAR archive entry {$index} must be an array");
            }

            if (!isset($entry['name']) || !is_string($entry['name'])) {
                throw new \RuntimeException("TAR archive entry {$index} is missing a string name");
            }

            $name = $entry['name'];
            self::assertSafePath($name, "TAR archive entry {$index} name");
            if (isset($names[$name])) {
                throw new \RuntimeException("Duplicate TAR archive entry: {$name}");
            }
            $names[$name] = true;

            $type = $entry['type'] ?? (str_ends_with($name, '/') ? TarArchiveEntry::TYPE_DIRECTORY : TarArchiveEntry::TYPE_FILE);
            if ($type !== TarArchiveEntry::TYPE_FILE && $type !== TarArchiveEntry::TYPE_DIRECTORY) {
                throw new \RuntimeException("Unsupported TAR archive entry type for {$name}");
            }

            $data = $entry['data'] ?? '';
            if (!is_string($data)) {
                throw new \RuntimeException("TAR archive entry {$name} data must be a string");
            }

            if ($type === TarArchiveEntry::TYPE_DIRECTORY && $data !== '') {
                throw new \RuntimeException("TAR directory entry {$name} must not contain file data");
            }

            $modifiedAt = $entry['modifiedAt'] ?? 0;
            self::assertNonNegativeInt($modifiedAt, "TAR entry {$name} modifiedAt");
            $accessedAt = $entry['accessedAt'] ?? null;
            if ($accessedAt !== null) {
                self::assertNonNegativeInt($accessedAt, "TAR entry {$name} accessedAt");
            }
            $changedAt = $entry['changedAt'] ?? null;
            if ($changedAt !== null) {
                self::assertNonNegativeInt($changedAt, "TAR entry {$name} changedAt");
            }
            $createdAt = $entry['createdAt'] ?? null;
            if ($createdAt !== null) {
                self::assertNonNegativeInt($createdAt, "TAR entry {$name} createdAt");
            }

            $mode = $entry['mode'] ?? ($type === TarArchiveEntry::TYPE_DIRECTORY ? 0755 : 0644);
            self::assertOctalFieldValue($mode, 8, "TAR entry {$name} mode");
            $uid = $entry['uid'] ?? 0;
            self::assertOctalFieldValue($uid, 8, "TAR entry {$name} uid");
            $gid = $entry['gid'] ?? 0;
            self::assertOctalFieldValue($gid, 8, "TAR entry {$name} gid");

            $userName = $entry['userName'] ?? '';
            $groupName = $entry['groupName'] ?? '';
            if (!is_string($userName) || !is_string($groupName)) {
                throw new \RuntimeException("TAR entry {$name} user and group names must be strings");
            }
            self::assertUtf8($userName, "TAR entry {$name} user name");
            self::assertUtf8($groupName, "TAR entry {$name} group name");

            $typeFlag = $type === TarArchiveEntry::TYPE_DIRECTORY ? self::TYPE_DIRECTORY : self::TYPE_REGULAR;
            $headerName = $name;
            $paxHeaders = [];
            if (self::splitUstarPath($name) === null) {
                $paxHeaders['path'] = $name;
                $headerName = 'PaxFiles/' . substr(sha1($name), 0, 24);
            }
            if ($accessedAt !== null) {
                $paxHeaders['atime'] = (string) $accessedAt;
            }
            if ($changedAt !== null) {
                $paxHeaders['ctime'] = (string) $changedAt;
            }
            if ($createdAt !== null) {
                $paxHeaders['LIBARCHIVE.creationtime'] = (string) $createdAt;
            }

            $headerOptions = [
                'mode' => $mode,
                'uid' => $uid,
                'gid' => $gid,
                'modifiedAt' => $modifiedAt,
                'userName' => $userName,
                'groupName' => $groupName,
            ];

            if ($paxHeaders !== []) {
                $paxPayload = self::buildPaxPayload($paxHeaders);
                $paxName = 'PaxHeaders/' . substr(sha1($name), 0, 24);
                $bytes .= self::buildHeader($paxName, self::TYPE_PAX_EXTENDED, strlen($paxPayload), $headerOptions);
                $bytes .= $paxPayload . str_repeat("\0", self::paddingSize(strlen($paxPayload)));
            }

            $bytes .= self::buildHeader($headerName, $typeFlag, strlen($data), $headerOptions);
            $bytes .= $data . str_repeat("\0", self::paddingSize(strlen($data)));
        }

        return $bytes . str_repeat("\0", self::BLOCK_SIZE * 2);
    }

    /**
     * @return list<TarArchiveEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (TarArchiveEntry $entry): string => $entry->name, $this->entries);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * @return array<string, string>
     */
    public function globalPaxHeaders(): array
    {
        return $this->globalPaxHeaders;
    }

    public function has(string $name): bool
    {
        $path = ltrim($name, '/');
        self::assertSafePath($path, 'TAR entry lookup name');

        return isset($this->entriesByName[$path]);
    }

    public function entry(string $name): TarArchiveEntry
    {
        $path = ltrim($name, '/');
        self::assertSafePath($path, 'TAR entry lookup name');
        if (!isset($this->entriesByName[$path])) {
            throw new \RuntimeException("TAR archive entry not found: {$name}");
        }

        return $this->entriesByName[$path];
    }

    public function read(string $name): string
    {
        $entry = $this->entry($name);
        if ($entry->isDirectory()) {
            return '';
        }

        return substr($this->bytes, $entry->dataOffset, $entry->size);
    }

    /**
     * @return array{
     *     type:string,
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     collisionGroups:list<array{caseFoldKey:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>
     * }
     */
    public function caseInsensitiveNamePreflight(): array
    {
        $entryNamesByCaseFoldKey = [];
        foreach ($this->entries as $entry) {
            $entryNamesByCaseFoldKey[self::caseFoldTarEntryName($entry->name)][] = $entry->name;
        }

        $collisionGroups = [];
        foreach ($entryNamesByCaseFoldKey as $caseFoldKey => $entryNames) {
            if (count($entryNames) > 1) {
                $collisionGroups[] = [
                    'caseFoldKey' => $caseFoldKey,
                    'entryNames' => $entryNames,
                ];
            }
        }

        $entries = [];
        $collisionEntries = [];
        foreach ($this->entries as $entry) {
            $caseFoldKey = self::caseFoldTarEntryName($entry->name);
            $equivalentEntryNames = $entryNamesByCaseFoldKey[$caseFoldKey] ?? [];
            $hasCollision = count($equivalentEntryNames) > 1;
            $issues = $hasCollision ? ['case-insensitive-name-collision'] : [];
            $summary = [
                'name' => $entry->name,
                'caseFoldKey' => $caseFoldKey,
                'equivalentEntryNames' => $equivalentEntryNames,
                'hasCaseInsensitiveNameCollision' => $hasCollision,
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if ($hasCollision) {
                $collisionEntries[] = $summary;
            }
        }

        return [
            'type' => 'tar-case-insensitive-name-policy',
            'entryCount' => count($this->entries),
            'collisionGroupCount' => count($collisionGroups),
            'collisionEntryCount' => count($collisionEntries),
            'handoffPolicy' => $collisionEntries === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'diagnostics' => $collisionEntries === [] ? [] : ['tar-case-insensitive-name-collision'],
            'collisionGroups' => $collisionGroups,
            'collisionEntries' => $collisionEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>,
     *     collisionGroups:list<array{caseFoldKey:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>
     * }
     */
    public function assertNoCaseInsensitiveNameCollisions(): array
    {
        $summary = $this->caseInsensitiveNamePreflight();
        if ($summary['collisionEntryCount'] > 0) {
            $groups = implode(
                ', ',
                array_map(
                    static fn (array $group): string => $group['caseFoldKey'] . ' (' . implode(', ', $group['entryNames']) . ')',
                    $summary['collisionGroups']
                )
            );

            throw new \RuntimeException(
                'TAR archive contains case-insensitive entry name collisions that require explicit import review: '
                . $groups
            );
        }

        return $summary;
    }

    private static function caseFoldTarEntryName(string $name): string
    {
        $name = self::normalizeTarEntryNameForCollisionKey($name);
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtr(strtolower($name), self::BOUNDED_UNICODE_CASE_FOLD_FALLBACKS);
        }

        return self::normalizeTarEntryNameForCollisionKey($name);
    }

    private static function normalizeTarEntryNameForCollisionKey(string $name): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return strtr($name, self::BOUNDED_LATIN_COMPOSITION_FALLBACKS);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildHeader(string $name, string $typeFlag, int $size, array $options): string
    {
        $path = self::splitUstarPath($name);
        if ($path === null) {
            throw new \RuntimeException("TAR entry name {$name} is too long for a ustar header without PAX metadata");
        }

        $mode = $options['mode'] ?? 0644;
        $uid = $options['uid'] ?? 0;
        $gid = $options['gid'] ?? 0;
        $modifiedAt = $options['modifiedAt'] ?? 0;
        $userName = $options['userName'] ?? '';
        $groupName = $options['groupName'] ?? '';

        self::assertOctalFieldValue($size, 12, "TAR entry {$name} size");
        self::assertOctalFieldValue($modifiedAt, 12, "TAR entry {$name} modifiedAt");

        $header = self::stringField($path['name'], 100)
            . self::octalField($mode, 8)
            . self::octalField($uid, 8)
            . self::octalField($gid, 8)
            . self::octalField($size, 12)
            . self::octalField($modifiedAt, 12)
            . str_repeat(' ', 8)
            . $typeFlag
            . self::stringField('', 100)
            . self::USTAR_MAGIC
            . self::USTAR_VERSION
            . self::stringField($userName, 32)
            . self::stringField($groupName, 32)
            . self::octalField(0, 8)
            . self::octalField(0, 8)
            . self::stringField($path['prefix'], 155)
            . str_repeat("\0", 12);

        if (strlen($header) !== self::BLOCK_SIZE) {
            throw new \RuntimeException('Internal TAR header construction error');
        }

        $checksum = self::checksum($header);
        $checksumField = sprintf('%06o', $checksum) . "\0 ";

        return substr_replace($header, $checksumField, 148, 8);
    }

    /**
     * @return array{name:string, prefix:string}|null
     */
    private static function splitUstarPath(string $name): ?array
    {
        if (strlen($name) <= 100) {
            return ['name' => $name, 'prefix' => ''];
        }

        $segments = explode('/', $name);
        for ($index = count($segments) - 1; $index > 0; $index--) {
            $prefix = implode('/', array_slice($segments, 0, $index));
            $basename = implode('/', array_slice($segments, $index));
            if (strlen($prefix) <= 155 && strlen($basename) <= 100) {
                return ['name' => $basename, 'prefix' => $prefix];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedCreatedAtFromHeader(array $headers): ?int
    {
        foreach (['LIBARCHIVE.creationtime', 'SCHILY.birthtime', 'birthtime'] as $key) {
            if (isset($headers[$key])) {
                return self::parsePaxIntegerTimestamp($headers[$key], "TAR PAX {$key}");
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedNameFromHeader(string $header, array $headers, ?string $gnuLongName): string
    {
        if (isset($headers['path'])) {
            return $headers['path'];
        }

        if ($gnuLongName !== null) {
            return $gnuLongName;
        }

        $name = self::trimNullField(substr($header, 0, 100));
        $prefix = self::trimNullField(substr($header, 345, 155));

        return $prefix === '' ? $name : $prefix . '/' . $name;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedNameSourceFromHeader(string $header, array $headers, ?string $gnuLongName): string
    {
        if (isset($headers['path'])) {
            return TarArchiveEntry::NAME_SOURCE_PAX_PATH;
        }

        if ($gnuLongName !== null) {
            return TarArchiveEntry::NAME_SOURCE_GNU_LONG_NAME;
        }

        $prefix = self::trimNullField(substr($header, 345, 155));

        return $prefix === ''
            ? TarArchiveEntry::NAME_SOURCE_HEADER
            : TarArchiveEntry::NAME_SOURCE_USTAR_PREFIX;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedSizeFromHeader(string $header, array $headers): int
    {
        if (isset($headers['size'])) {
            return self::parsePaxNonNegativeInteger($headers['size'], 'TAR PAX size');
        }

        return self::readNumericField(substr($header, 124, 12), 'TAR entry size');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedModifiedAtFromHeader(string $header, array $headers): int
    {
        if (isset($headers['mtime'])) {
            return self::parsePaxIntegerTimestamp($headers['mtime'], 'TAR PAX mtime');
        }

        return self::readNumericField(substr($header, 136, 12), 'TAR entry mtime');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedAccessedAtFromHeader(array $headers): ?int
    {
        if (isset($headers['atime'])) {
            return self::parsePaxIntegerTimestamp($headers['atime'], 'TAR PAX atime');
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedChangedAtFromHeader(array $headers): ?int
    {
        if (isset($headers['ctime'])) {
            return self::parsePaxIntegerTimestamp($headers['ctime'], 'TAR PAX ctime');
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedUidFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['uid'])) {
            return self::parsePaxNonNegativeInteger($headers['uid'], "TAR PAX uid for {$name}");
        }

        return self::readNumericField(substr($header, 108, 8), "TAR uid for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedGidFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['gid'])) {
            return self::parsePaxNonNegativeInteger($headers['gid'], "TAR PAX gid for {$name}");
        }

        return self::readNumericField(substr($header, 116, 8), "TAR gid for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedUserNameFromHeader(string $header, array $headers): string
    {
        $userName = $headers['uname'] ?? self::trimNullField(substr($header, 265, 32));
        self::assertUtf8($userName, isset($headers['uname']) ? 'TAR PAX uname metadata' : 'TAR ustar user name metadata');

        return $userName;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedGroupNameFromHeader(string $header, array $headers): string
    {
        $groupName = $headers['gname'] ?? self::trimNullField(substr($header, 297, 32));
        self::assertUtf8($groupName, isset($headers['gname']) ? 'TAR PAX gname metadata' : 'TAR ustar group name metadata');

        return $groupName;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedDeviceMajorFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['devmajor']) && $headers['devmajor'] !== '') {
            return self::parsePaxNonNegativeInteger($headers['devmajor'], "TAR PAX devmajor for {$name}");
        }

        return self::readNumericField(substr($header, 329, 8), "TAR device major for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedDeviceMinorFromHeader(string $header, array $headers, string $name): int
    {
        if (isset($headers['devminor']) && $headers['devminor'] !== '') {
            return self::parsePaxNonNegativeInteger($headers['devminor'], "TAR PAX devminor for {$name}");
        }

        return self::readNumericField(substr($header, 337, 8), "TAR device minor for {$name}");
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedDeviceNumberSource(array $headers): string
    {
        $hasMajor = isset($headers['devmajor']) && $headers['devmajor'] !== '';
        $hasMinor = isset($headers['devminor']) && $headers['devminor'] !== '';

        if ($hasMajor && $hasMinor) {
            return 'pax-device-numbers';
        }

        if ($hasMajor || $hasMinor) {
            return 'mixed-device-numbers';
        }

        return 'header-device-numbers';
    }

    /**
     * @return array{
     *     checksumField:string,
     *     storedChecksum:int,
     *     storedChecksumOctal:string,
     *     unsignedChecksum:int,
     *     signedChecksum:int,
     *     matchesUnsigned:bool,
     *     matchesSigned:bool,
     *     checksumKind:string
     * }
     */
    private static function validateHeaderChecksum(string $header): array
    {
        $stored = self::readOctalField(substr($header, 148, 8), 'TAR header checksum');
        $checksumField = trim(substr($header, 148, 8), " \0");
        $checksummedHeader = substr_replace($header, str_repeat(' ', 8), 148, 8);
        $actual = self::checksum($checksummedHeader);
        $signedActual = self::signedChecksum($checksummedHeader);
        $matchesUnsigned = $stored === $actual;
        $matchesSigned = $stored === $signedActual;
        if (!$matchesUnsigned && !$matchesSigned) {
            throw new \RuntimeException('TAR header checksum does not match header bytes');
        }

        $magic = substr($header, 257, 6);
        if ($magic !== self::USTAR_MAGIC && self::trimNullField($magic) !== '') {
            throw new \RuntimeException('Unsupported TAR header magic');
        }

        if ($magic === self::USTAR_MAGIC && substr($header, 263, 2) !== self::USTAR_VERSION) {
            throw new \RuntimeException('Unsupported TAR header ustar version');
        }

        return [
            'checksumField' => $checksumField,
            'storedChecksum' => $stored,
            'storedChecksumOctal' => decoct($stored),
            'unsignedChecksum' => $actual,
            'signedChecksum' => $signedActual,
            'matchesUnsigned' => $matchesUnsigned,
            'matchesSigned' => $matchesSigned,
            'checksumKind' => self::checksumKind($matchesUnsigned, $matchesSigned),
        ];
    }

    private static function checksumKind(bool $matchesUnsigned, bool $matchesSigned): string
    {
        if ($matchesUnsigned && $matchesSigned) {
            return 'posix-unsigned-and-historic-signed';
        }

        if ($matchesUnsigned) {
            return 'posix-unsigned';
        }

        return 'historic-signed';
    }

    /**
     * @param array{
     *     checksumField:string,
     *     storedChecksum:int,
     *     storedChecksumOctal:string,
     *     unsignedChecksum:int,
     *     signedChecksum:int,
     *     matchesUnsigned:bool,
     *     matchesSigned:bool,
     *     checksumKind:string
     * } $checksum
     * @return array{
     *     name:string,
     *     role:string,
     *     typeFlag:string,
     *     nameSource:string,
     *     linkTarget:?string,
     *     linkTargetSource:?string,
     *     linkTargetSize:?int,
     *     headerOffset:int,
     *     dataOffset:int,
     *     recordEndOffset:int,
     *     payloadSize:int,
     *     headerPayloadSize:int,
     *     checksumField:string,
     *     storedChecksum:int,
     *     storedChecksumOctal:string,
     *     unsignedChecksum:int,
     *     signedChecksum:int,
     *     matchesUnsigned:bool,
     *     matchesSigned:bool,
     *     checksumKind:string,
     *     metadataKind:?string,
     *     metadataValue:?string,
     *     metadataValueSize:?int,
     *     paxHeaderCount:int,
     *     paxHeaderKeys:list<string>,
     *     policy:string,
     *     diagnostics:list<string>
     * }
     */
    private static function checksumPolicyRecord(
        array $checksum,
        int $headerOffset,
        int $dataOffset,
        int $recordEndOffset,
        int $payloadSize,
        int $headerPayloadSize,
        string $name,
        string $role,
        string $typeFlag,
        string $nameSource,
        array $metadata = []
    ): array {
        $diagnostics = $checksum['checksumKind'] === 'historic-signed'
            ? ['tar-header-historic-signed-checksum']
            : [];
        $metadataKind = is_string($metadata['metadataKind'] ?? null) ? $metadata['metadataKind'] : null;
        $metadataValue = is_string($metadata['metadataValue'] ?? null) ? $metadata['metadataValue'] : null;
        $paxHeaderKeys = is_array($metadata['paxHeaderKeys'] ?? null) ? array_values($metadata['paxHeaderKeys']) : [];
        $linkTarget = is_string($metadata['linkTarget'] ?? null) ? $metadata['linkTarget'] : null;
        $linkTargetSource = is_string($metadata['linkTargetSource'] ?? null) ? $metadata['linkTargetSource'] : null;

        return [
            'name' => $name,
            'role' => $role,
            'typeFlag' => $typeFlag,
            'nameSource' => $nameSource,
            'linkTarget' => $linkTarget,
            'linkTargetSource' => $linkTargetSource,
            'linkTargetSize' => $linkTarget === null ? null : strlen($linkTarget),
            'headerOffset' => $headerOffset,
            'dataOffset' => $dataOffset,
            'recordEndOffset' => $recordEndOffset,
            'payloadSize' => $payloadSize,
            'headerPayloadSize' => $headerPayloadSize,
            'checksumField' => $checksum['checksumField'],
            'storedChecksum' => $checksum['storedChecksum'],
            'storedChecksumOctal' => $checksum['storedChecksumOctal'],
            'unsignedChecksum' => $checksum['unsignedChecksum'],
            'signedChecksum' => $checksum['signedChecksum'],
            'matchesUnsigned' => $checksum['matchesUnsigned'],
            'matchesSigned' => $checksum['matchesSigned'],
            'checksumKind' => $checksum['checksumKind'],
            'metadataKind' => $metadataKind,
            'metadataValue' => $metadataValue,
            'metadataValueSize' => $metadataValue === null ? null : strlen($metadataValue),
            'paxHeaderCount' => count($paxHeaderKeys),
            'paxHeaderKeys' => $paxHeaderKeys,
            'policy' => 'accepted-checksum-provenance',
            'diagnostics' => $diagnostics,
        ];
    }

    private static function checksumPolicyTypeName(string $typeFlag): string
    {
        return match ($typeFlag) {
            self::TYPE_REGULAR => 'regular-file',
            self::TYPE_CONTIGUOUS_FILE => 'contiguous-file',
            self::TYPE_DIRECTORY => 'directory',
            self::TYPE_HARD_LINK => 'hard-link',
            self::TYPE_SYMBOLIC_LINK => 'symbolic-link',
            self::TYPE_CHARACTER_DEVICE => 'character-device',
            self::TYPE_BLOCK_DEVICE => 'block-device',
            self::TYPE_FIFO => 'fifo',
            self::TYPE_GNU_SPARSE => 'gnu-sparse',
            self::TYPE_GNU_MULTIVOLUME => 'gnu-multivolume',
            self::TYPE_GNU_DUMPDIR => 'gnu-dumpdir',
            default => 'type-' . $typeFlag,
        };
    }

    private static function checksum(string $header): int
    {
        $sum = 0;
        for ($index = 0, $length = strlen($header); $index < $length; $index++) {
            $sum += ord($header[$index]);
        }

        return $sum;
    }

    private static function signedChecksum(string $header): int
    {
        $sum = 0;
        for ($index = 0, $length = strlen($header); $index < $length; $index++) {
            $byte = ord($header[$index]);
            $sum += $byte < 128 ? $byte : $byte - 256;
        }

        return $sum;
    }

    private static function readOctalField(string $field, string $label): int
    {
        $value = trim($field, " \0");
        if ($value === '') {
            return 0;
        }

        if (!preg_match('/^[0-7]+$/', $value)) {
            throw new \RuntimeException("{$label} is not a supported octal TAR field");
        }

        return intval($value, 8);
    }

    private static function readNumericField(string $field, string $label): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            return self::readBase256Field($field, $label);
        }

        return self::readOctalField($field, $label);
    }

    private static function readBase256Field(string $field, string $label): int
    {
        if ($field === '') {
            return 0;
        }

        $first = ord($field[0]);
        if (($first & 0x40) !== 0) {
            throw new \RuntimeException("{$label} is a negative base-256 TAR field, which is not supported");
        }

        $field[0] = chr($first & 0x7f);
        $value = 0;
        for ($index = 0, $length = strlen($field); $index < $length; $index++) {
            $byte = ord($field[$index]);
            if ($value > intdiv(PHP_INT_MAX - $byte, 256)) {
                throw new \RuntimeException("{$label} is too large for this PHP runtime");
            }
            $value = ($value * 256) + $byte;
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function parsePaxHeaders(string $bytes): array
    {
        $headers = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            $space = strpos($bytes, ' ', $cursor);
            if ($space === false) {
                throw new \RuntimeException('TAR PAX header record is missing a length separator');
            }

            $lengthText = substr($bytes, $cursor, $space - $cursor);
            if ($lengthText === '' || !ctype_digit($lengthText)) {
                throw new \RuntimeException('TAR PAX header record length is invalid');
            }

            $recordLength = (int) $lengthText;
            if ($recordLength <= 0 || $cursor + $recordLength > $length) {
                throw new \RuntimeException('TAR PAX header record extends beyond payload bytes');
            }

            $record = substr($bytes, $cursor, $recordLength);
            if (!str_ends_with($record, "\n")) {
                throw new \RuntimeException('TAR PAX header record must end with a newline');
            }

            $recordBody = substr($record, strlen($lengthText) + 1, -1);
            $equals = strpos($recordBody, '=');
            if ($equals === false || $equals === 0) {
                throw new \RuntimeException('TAR PAX header record is missing a key/value separator');
            }

            $key = substr($recordBody, 0, $equals);
            $value = substr($recordBody, $equals + 1);
            if (str_contains($key, "\0") || str_contains($value, "\0")) {
                throw new \RuntimeException('TAR PAX header records must not contain NUL bytes');
            }
            self::assertUtf8($key, 'TAR PAX header key metadata');
            self::assertUtf8($value, "TAR PAX {$key} metadata");

            if (array_key_exists($key, $headers)) {
                throw new \RuntimeException("TAR PAX header record contains duplicate keyword {$key}");
            }

            $headers[$key] = $value;
            $cursor += $recordLength;
        }

        return $headers;
    }

    /**
     * @return array{
     *     headers:array<string, string>,
     *     records:list<array{keyword:string, value:string}>,
     *     duplicateRecords:list<array{keyword:string, occurrences:int, values:list<string>, firstValue:string, duplicateValues:list<string>}>
     * }
     */
    private static function parsePaxHeadersWithDuplicateReport(string $bytes): array
    {
        $headers = [];
        $records = [];
        $valuesByKey = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            $space = strpos($bytes, ' ', $cursor);
            if ($space === false) {
                throw new \RuntimeException('TAR PAX header record is missing a length separator');
            }

            $lengthText = substr($bytes, $cursor, $space - $cursor);
            if ($lengthText === '' || !ctype_digit($lengthText)) {
                throw new \RuntimeException('TAR PAX header record length is invalid');
            }

            $recordLength = (int) $lengthText;
            if ($recordLength <= 0 || $cursor + $recordLength > $length) {
                throw new \RuntimeException('TAR PAX header record extends beyond payload bytes');
            }

            $record = substr($bytes, $cursor, $recordLength);
            if (!str_ends_with($record, "\n")) {
                throw new \RuntimeException('TAR PAX header record must end with a newline');
            }

            $recordBody = substr($record, strlen($lengthText) + 1, -1);
            $equals = strpos($recordBody, '=');
            if ($equals === false || $equals === 0) {
                throw new \RuntimeException('TAR PAX header record is missing a key/value separator');
            }

            $key = substr($recordBody, 0, $equals);
            $value = substr($recordBody, $equals + 1);
            if (str_contains($key, "\0") || str_contains($value, "\0")) {
                throw new \RuntimeException('TAR PAX header records must not contain NUL bytes');
            }
            self::assertUtf8($key, 'TAR PAX header key metadata');
            self::assertUtf8($value, "TAR PAX {$key} metadata");

            $headers[$key] = $value;
            $records[] = [
                'keyword' => $key,
                'value' => $value,
            ];
            $valuesByKey[$key] ??= [];
            $valuesByKey[$key][] = $value;
            $cursor += $recordLength;
        }

        $duplicateRecords = [];
        foreach ($valuesByKey as $keyword => $values) {
            if (count($values) <= 1) {
                continue;
            }

            $duplicateRecords[] = [
                'keyword' => $keyword,
                'occurrences' => count($values),
                'values' => $values,
                'firstValue' => $values[0],
                'duplicateValues' => array_slice($values, 1),
            ];
        }

        return [
            'headers' => $headers,
            'records' => $records,
            'duplicateRecords' => $duplicateRecords,
        ];
    }

    private static function parseGnuLongName(string $bytes): string
    {
        if (!str_ends_with($bytes, "\0")) {
            throw new \RuntimeException('TAR GNU long-name metadata must end with a NUL terminator');
        }

        $name = rtrim($bytes, "\0");
        self::assertUtf8($name, 'TAR GNU long name metadata');
        self::assertSafePath($name, 'TAR GNU long name');

        return $name;
    }

    private static function parseGnuLongLink(string $bytes): string
    {
        if (!str_ends_with($bytes, "\0")) {
            throw new \RuntimeException('TAR GNU long-link metadata must end with a NUL terminator');
        }

        $name = rtrim($bytes, "\0");
        self::assertUtf8($name, 'TAR GNU long link metadata');
        self::assertSafePath($name, 'TAR GNU long link');

        return $name;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedLinkTargetFromHeader(string $header, array $headers, ?string $gnuLongLink): string
    {
        if (isset($headers['linkpath'])) {
            return $headers['linkpath'];
        }

        if ($gnuLongLink !== null) {
            return $gnuLongLink;
        }

        return self::trimNullField(substr($header, 157, 100));
    }

    /**
     * @param array<string, string> $headers
     */
    private static function resolvedLinkTargetSourceFromHeader(array $headers, ?string $gnuLongLink): string
    {
        if (isset($headers['linkpath'])) {
            return 'pax-linkpath';
        }

        if ($gnuLongLink !== null) {
            return 'gnu-long-link';
        }

        return 'header-linkname';
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $records
     * @return array<string, string>
     */
    private static function applyPaxHeaderRecords(array $base, array $records): array
    {
        foreach ($records as $key => $value) {
            if ($value === '') {
                unset($base[$key]);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string, string> $globalHeaders
     * @param array<string, string> $localHeaders
     * @return array<string, string>
     */
    private static function mergePaxHeaderRecords(array $globalHeaders, array $localHeaders): array
    {
        return self::applyPaxHeaderRecords($globalHeaders, $localHeaders);
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function deletedPaxHeaderKeys(array $headers): array
    {
        $keys = [];
        foreach ($headers as $key => $value) {
            if ($value === '') {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasSparsePaxHeaders(array $headers): bool
    {
        return self::sparsePaxHeaderKeys($headers) !== [];
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasMultiVolumePaxHeaders(array $headers): bool
    {
        return self::multiVolumePaxHeaderKeys($headers) !== [];
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasIncrementalSnapshotPaxHeaders(array $headers): bool
    {
        return self::incrementalSnapshotPaxHeaderKeys($headers) !== [];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function incrementalSnapshotPaxHeaderKeys(array $headers): array
    {
        $keys = [];
        foreach ($headers as $key => $value) {
            if ($value !== '' && str_starts_with($key, 'GNU.dumpdir')) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function incrementalSnapshotHeaderFamilies(array $headers, bool $isGnuDumpdirType): array
    {
        $families = [];
        if ($isGnuDumpdirType) {
            $families['gnu-typeflag'] = true;
        }

        foreach ($headers as $key => $value) {
            if ($value !== '' && str_starts_with($key, 'GNU.dumpdir')) {
                $families['gnu-pax'] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * @param array<string, string> $headers
     * @return list<array{source:string, marker:string, name:string, action:string, raw:string}>
     */
    private static function incrementalSnapshotDumpdirRecords(
        string $payload,
        array $headers,
        bool $isGnuDumpdirType
    ): array {
        $records = [];
        if ($isGnuDumpdirType) {
            $records = array_merge(
                $records,
                self::parseIncrementalSnapshotDumpdirList($payload, 'typeflag-payload', 'TAR GNU dumpdir payload')
            );
        }

        foreach ($headers as $key => $value) {
            if ($value === '' || !str_starts_with($key, 'GNU.dumpdir')) {
                continue;
            }

            $records = array_merge(
                $records,
                self::parseIncrementalSnapshotDumpdirList($value, 'pax-gnu-dumpdir', "TAR PAX {$key} metadata")
            );
        }

        return $records;
    }

    /**
     * @return list<array{source:string, marker:string, name:string, action:string, raw:string}>
     */
    private static function parseIncrementalSnapshotDumpdirList(string $bytes, string $source, string $label): array
    {
        $separator = str_contains($bytes, "\0") ? "\0" : "\n";
        $records = [];
        foreach (explode($separator, $bytes) as $record) {
            $record = trim($record, "\r\n");
            if ($record === '') {
                continue;
            }

            $marker = $record[0];
            $name = substr($record, 1);
            if ($name === '') {
                throw new \RuntimeException("{$label} contains an empty incremental member name");
            }

            $action = self::incrementalSnapshotAction($marker, $label);
            self::assertSafePath($name, "{$label} member name");
            $records[] = [
                'source' => $source,
                'marker' => $marker,
                'name' => $name,
                'action' => $action,
                'raw' => $record,
            ];
        }

        return $records;
    }

    private static function incrementalSnapshotAction(string $marker, string $label): string
    {
        return match ($marker) {
            'Y' => 'present',
            'N' => 'deleted',
            'D' => 'directory',
            'R' => 'renamed',
            default => throw new \RuntimeException("{$label} contains unsupported incremental marker {$marker}"),
        };
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function multiVolumePaxHeaderKeys(array $headers): array
    {
        $keys = [];
        foreach ($headers as $key => $value) {
            if ($value !== '' && str_starts_with($key, 'GNU.volume.')) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function multiVolumeHeaderFamilies(array $headers, bool $isGnuMultiVolumeType): array
    {
        $families = [];
        if ($isGnuMultiVolumeType) {
            $families['gnu-typeflag'] = true;
        }

        foreach ($headers as $key => $value) {
            if ($value !== '' && str_starts_with($key, 'GNU.volume.')) {
                $families['gnu-pax'] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * @param array<string, string> $headers
     * @return array{offset:?int, source:?string}
     */
    private static function multiVolumeContinuationOffset(string $header, array $headers): array
    {
        if (isset($headers['GNU.volume.offset']) && $headers['GNU.volume.offset'] !== '') {
            return [
                'offset' => self::parsePaxNonNegativeInteger($headers['GNU.volume.offset'], 'TAR PAX GNU.volume.offset'),
                'source' => 'pax-gnu-volume-offset',
            ];
        }

        $oldGnuOffset = substr($header, 369, 12);
        if (trim($oldGnuOffset, " \0") !== '') {
            return [
                'offset' => self::readNumericField($oldGnuOffset, 'TAR GNU multi-volume offset'),
                'source' => 'oldgnu-offset-field',
            ];
        }

        return [
            'offset' => null,
            'source' => null,
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    private static function multiVolumeOriginalName(array $headers): ?string
    {
        if (!isset($headers['GNU.volume.filename']) || $headers['GNU.volume.filename'] === '') {
            return null;
        }

        $name = $headers['GNU.volume.filename'];
        self::assertSafePath($name, 'TAR PAX GNU.volume.filename metadata');

        return $name;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function multiVolumeDeclaredSize(array $headers): ?int
    {
        if (!isset($headers['GNU.volume.size']) || $headers['GNU.volume.size'] === '') {
            return null;
        }

        return self::parsePaxNonNegativeInteger($headers['GNU.volume.size'], 'TAR PAX GNU.volume.size');
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function sparsePaxHeaderKeys(array $headers): array
    {
        $keys = [];
        foreach ($headers as $key => $value) {
            if (str_starts_with($key, 'GNU.sparse.')
                || str_starts_with($key, 'SCHILY.sparse.')
                || $key === 'SCHILY.realsize'
                || ($key === 'SCHILY.filetype' && strtolower(trim($value)) === 'sparse')
            ) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function sparseHeaderFamilies(array $headers, bool $isGnuSparseType): array
    {
        $families = [];
        if ($isGnuSparseType) {
            $families['gnu-typeflag'] = true;
        }

        foreach ($headers as $key => $value) {
            if (str_starts_with($key, 'GNU.sparse.')) {
                $families['gnu-pax'] = true;
            }

            if (str_starts_with($key, 'SCHILY.sparse.')
                || $key === 'SCHILY.realsize'
                || ($key === 'SCHILY.filetype' && strtolower(trim($value)) === 'sparse')
            ) {
                $families['schily-pax'] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function sparseRealSize(array $headers): ?int
    {
        foreach ([
            'GNU.sparse.realsize',
            'GNU.sparse.size',
            'SCHILY.realsize',
            'SCHILY.sparse.size',
        ] as $key) {
            if (isset($headers[$key])) {
                return self::parsePaxNonNegativeInteger($headers[$key], "TAR PAX {$key}");
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function sparseMapSource(array $headers): ?string
    {
        $sources = [];
        foreach (['GNU.sparse.map', 'SCHILY.sparse.map'] as $key) {
            if (isset($headers[$key])) {
                $sources[] = $key;
            }
        }

        if (count($sources) > 1) {
            throw new \RuntimeException('TAR PAX sparse map metadata must not mix GNU and SCHILY map records');
        }

        return $sources[0] ?? null;
    }

    /**
     * @param array<string, string> $headers
     * @return list<array{offset:int, length:int, endOffset:int}>
     */
    private static function sparseMapSegments(array $headers, ?int $realSize): array
    {
        $source = self::sparseMapSource($headers);
        if ($source === null) {
            return [];
        }

        $value = $headers[$source];
        if ($value === '') {
            throw new \RuntimeException("TAR PAX {$source} sparse map must not be empty");
        }

        $tokens = explode(',', $value);
        if (count($tokens) % 2 !== 0) {
            throw new \RuntimeException("TAR PAX {$source} sparse map must contain offset,length pairs");
        }

        $segments = [];
        $previousEndOffset = 0;
        for ($index = 0; $index < count($tokens); $index += 2) {
            $offset = self::parsePaxNonNegativeInteger($tokens[$index], "TAR PAX {$source} sparse map offset");
            $length = self::parsePaxNonNegativeInteger($tokens[$index + 1], "TAR PAX {$source} sparse map length");
            if ($length === 0) {
                throw new \RuntimeException("TAR PAX {$source} sparse map contains a zero-length segment");
            }

            if ($offset < $previousEndOffset) {
                throw new \RuntimeException("TAR PAX {$source} sparse map segments must be sorted and non-overlapping");
            }

            if ($offset > PHP_INT_MAX - $length) {
                throw new \RuntimeException("TAR PAX {$source} sparse map segment is too large for this PHP runtime");
            }

            $endOffset = $offset + $length;
            if ($realSize !== null && $endOffset > $realSize) {
                throw new \RuntimeException("TAR PAX {$source} sparse map segment exceeds the declared real size");
            }

            $segments[] = [
                'offset' => $offset,
                'length' => $length,
                'endOffset' => $endOffset,
            ];
            $previousEndOffset = $endOffset;
        }

        return $segments;
    }

    private static function specialFileType(string $typeFlag): ?string
    {
        return match ($typeFlag) {
            self::TYPE_CHARACTER_DEVICE => 'character-device',
            self::TYPE_BLOCK_DEVICE => 'block-device',
            self::TYPE_FIFO => 'fifo',
            default => null,
        };
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $globalHeaders
     * @param array<string, string> $localHeaders
     * @return list<array{keyword:string, category:string, name:string, source:string, value:string}>
     */
    private static function paxFilesystemMetadataRecords(array $headers, array $globalHeaders, array $localHeaders): array
    {
        $records = [];
        foreach ($headers as $key => $value) {
            $category = self::paxFilesystemMetadataCategory($key);
            if ($category === null) {
                continue;
            }

            $source = 'effective-pax';
            if (array_key_exists($key, $localHeaders)) {
                $source = 'local-pax';
            } elseif (array_key_exists($key, $globalHeaders)) {
                $source = 'global-pax';
            }

            $records[] = [
                'keyword' => $key,
                'category' => $category,
                'name' => self::paxFilesystemMetadataName($key, $category),
                'source' => $source,
                'value' => $value,
            ];
        }

        return $records;
    }

    private static function paxFilesystemMetadataCategory(string $key): ?string
    {
        if (str_starts_with($key, 'SCHILY.xattr.') || str_starts_with($key, 'LIBARCHIVE.xattr.')) {
            return 'extended-attribute';
        }

        if (str_starts_with($key, 'SCHILY.acl.') || str_starts_with($key, 'LIBARCHIVE.acl.')) {
            return 'access-control-list';
        }

        if ($key === 'SCHILY.fflags' || $key === 'LIBARCHIVE.fflags') {
            return 'file-flags';
        }

        return null;
    }

    private static function paxFilesystemMetadataName(string $key, string $category): string
    {
        if ($category === 'extended-attribute') {
            return preg_replace('/^(?:SCHILY|LIBARCHIVE)\.xattr\./', '', $key) ?? $key;
        }

        if ($category === 'access-control-list') {
            return preg_replace('/^(?:SCHILY|LIBARCHIVE)\.acl\./', '', $key) ?? $key;
        }

        return $key;
    }

    /**
     * @return list<string>
     */
    private static function filesystemAttributeModeFlags(int $mode, string $typeFlag): array
    {
        $flags = [];
        if (($mode & 04000) !== 0) {
            $flags[] = 'setuid';
        }

        if (($mode & 02000) !== 0) {
            $flags[] = 'setgid';
        }

        if (($mode & 01000) !== 0) {
            $flags[] = 'sticky';
        }

        if (($mode & 0002) !== 0) {
            $flags[] = 'world-writable';
        }

        if (($mode & 0111) !== 0 && ($typeFlag === self::TYPE_REGULAR || $typeFlag === self::TYPE_CONTIGUOUS_FILE)) {
            $flags[] = 'regular-executable';
        }

        return $flags;
    }

    /**
     * @return list<string>
     */
    private static function filesystemAttributeOwnerFlags(int $uid, int $gid, string $userName, string $groupName): array
    {
        $flags = [];
        if ($uid !== 0) {
            $flags[] = 'non-root-uid';
        }

        if ($gid !== 0) {
            $flags[] = 'non-root-gid';
        }

        if ($userName !== '' && $userName !== 'root') {
            $flags[] = 'user-name';
        }

        if ($groupName !== '' && $groupName !== 'root') {
            $flags[] = 'group-name';
        }

        return $flags;
    }

    private static function filesystemAttributeModeOctal(int $mode): string
    {
        return str_pad(decoct($mode), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<string> $modeFlags
     * @param list<string> $ownerFlags
     * @return list<string>
     */
    private static function filesystemAttributeDiagnostics(array $modeFlags, array $ownerFlags): array
    {
        $diagnostics = ['tar-filesystem-attributes-not-applied'];
        foreach ($modeFlags as $flag) {
            $diagnostics[] = match ($flag) {
                'setuid' => 'tar-mode-setuid-not-applied',
                'setgid' => 'tar-mode-setgid-not-applied',
                'sticky' => 'tar-mode-sticky-not-applied',
                'world-writable' => 'tar-mode-world-writable-not-applied',
                'regular-executable' => 'tar-mode-executable-not-applied',
                default => 'tar-mode-metadata-not-applied',
            };
        }

        if ($ownerFlags !== []) {
            $diagnostics[] = 'tar-owner-metadata-not-applied';
        }

        return $diagnostics;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function assertLocalPaxHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            if ($key === 'hdrcharset') {
                self::assertPaxHeaderCharsetValue($value, 'TAR local PAX hdrcharset metadata');
            }

            if ($key === 'path') {
                self::assertUtf8($value, 'TAR PAX path metadata');
            }

            if ($key === 'linkpath' && $value !== '') {
                throw new \RuntimeException('TAR local PAX linkpath metadata is not supported by the pandoc archive reader');
            }

            if ($key === 'GNU.volume.filename' && $value !== '') {
                self::assertSafePath($value, 'TAR PAX GNU.volume.filename metadata');
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private static function assertLinkPolicyLocalPaxHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            if ($key === 'hdrcharset') {
                self::assertPaxHeaderCharsetValue($value, 'TAR local PAX hdrcharset metadata');
            }

            if ($key === 'path') {
                self::assertUtf8($value, 'TAR PAX path metadata');
            }

            if ($key === 'linkpath' && $value !== '') {
                self::assertSafePath($value, 'TAR PAX linkpath metadata');
            }

            if ($key === 'GNU.volume.filename' && $value !== '') {
                self::assertSafePath($value, 'TAR PAX GNU.volume.filename metadata');
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private static function assertGlobalPaxHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            if ($value === '') {
                continue;
            }

            if ($key === 'hdrcharset') {
                self::assertPaxHeaderCharsetValue($value, 'TAR global PAX hdrcharset metadata');
            }

            if ($key === 'path' || $key === 'linkpath' || $key === 'size') {
                throw new \RuntimeException("TAR global PAX header {$key} is per-entry metadata and is not supported");
            }

            if (str_starts_with($key, 'GNU.sparse.') || str_starts_with($key, 'SCHILY.sparse.')) {
                throw new \RuntimeException("TAR global PAX sparse header {$key} is not supported");
            }

            if ($key === 'SCHILY.filetype' && strtolower(trim($value)) === 'sparse') {
                throw new \RuntimeException('TAR global PAX sparse file metadata is not supported');
            }

            if (str_starts_with($key, 'GNU.volume.')) {
                throw new \RuntimeException("TAR global PAX multi-volume header {$key} is not supported");
            }

            if (str_starts_with($key, 'GNU.dumpdir')) {
                throw new \RuntimeException("TAR global PAX incremental snapshot header {$key} is not supported");
            }
        }
    }

    private static function assertPaxHeaderCharsetValue(string $value, string $label): void
    {
        if ($value === '') {
            return;
        }

        if (!in_array($value, [
            self::PAX_HDRCHARSET_BINARY,
            self::PAX_HDRCHARSET_UTF8,
            self::PAX_HDRCHARSET_UTF8_SHORT,
        ], true)) {
            throw new \RuntimeException("{$label} is not supported by the pandoc archive reader");
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private static function buildPaxPayload(array $headers): string
    {
        $headers = self::normalizePaxHeaders($headers, 'TAR PAX headers');
        $payload = '';
        foreach ($headers as $key => $value) {
            $payload .= self::buildPaxRecord($key, $value);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizePaxHeaders(mixed $headers, string $label): array
    {
        if (!is_array($headers)) {
            throw new \RuntimeException("{$label} must be an associative array");
        }

        $normalized = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || $key === '' || str_contains($key, "\0") || str_contains($key, '=')) {
                throw new \RuntimeException("{$label} keys must be non-empty strings without NUL bytes or equals signs");
            }
            self::assertUtf8($key, "{$label} key metadata");

            if (!is_string($value) || str_contains($value, "\0")) {
                throw new \RuntimeException("{$label} values must be strings without NUL bytes");
            }
            self::assertUtf8($value, "{$label} {$key} metadata");

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function buildPaxRecord(string $key, string $value): string
    {
        $body = " {$key}={$value}\n";
        $recordLength = strlen($body) + 1;
        do {
            $nextLength = strlen((string) $recordLength) + strlen($body);
            if ($nextLength === $recordLength) {
                return $recordLength . $body;
            }
            $recordLength = $nextLength;
        } while (true);
    }

    private static function parsePaxIntegerTimestamp(string $value, string $label): int
    {
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new \RuntimeException("{$label} is not a supported non-negative timestamp");
        }

        $integerPart = explode('.', $value, 2)[0];

        return self::parsePaxNonNegativeInteger($integerPart, $label);
    }

    private static function parsePaxNonNegativeInteger(string $value, string $label): int
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new \RuntimeException("{$label} is not a supported non-negative integer");
        }

        $integer = (int) $value;
        if ((string) $integer !== ltrim($value, '0') && ltrim($value, '0') !== '') {
            throw new \RuntimeException("{$label} is too large for this PHP runtime");
        }

        return $integer;
    }

    private static function isZeroBlock(string $block): bool
    {
        return $block === str_repeat("\0", self::BLOCK_SIZE);
    }

    private static function assertTrailingZeroBlocks(string $bytes, int $offset): void
    {
        $remaining = substr($bytes, $offset);
        if (strlen($remaining) < self::BLOCK_SIZE * 2) {
            throw new \RuntimeException('TAR archive end marker must contain two zero blocks');
        }

        if (trim($remaining, "\0") !== '') {
            throw new \RuntimeException('TAR archive contains non-zero bytes after the end marker');
        }
    }

    private static function paddedSize(int $size): int
    {
        return $size + self::paddingSize($size);
    }

    private static function paddingSize(int $size): int
    {
        $remainder = $size % self::BLOCK_SIZE;

        return $remainder === 0 ? 0 : self::BLOCK_SIZE - $remainder;
    }

    private static function octalField(int $value, int $length): string
    {
        self::assertOctalFieldValue($value, $length, 'TAR numeric field');
        $digits = $length - 1;

        return str_pad(decoct($value), $digits, '0', STR_PAD_LEFT) . "\0";
    }

    private static function assertOctalFieldValue(mixed $value, int $length, string $label): void
    {
        if (!is_int($value) || $value < 0) {
            throw new \RuntimeException("{$label} must be a non-negative integer");
        }

        $max = intval(str_repeat('7', $length - 1), 8);
        if ($value > $max) {
            throw new \RuntimeException("{$label} is too large for a bounded TAR octal field");
        }
    }

    private static function assertNonNegativeInt(mixed $value, string $label): void
    {
        if (!is_int($value) || $value < 0) {
            throw new \RuntimeException("{$label} must be a non-negative integer");
        }
    }

    private static function stringField(string $value, int $length): string
    {
        if (strlen($value) > $length) {
            throw new \RuntimeException('TAR string field is too long');
        }

        if (str_contains($value, "\0")) {
            throw new \RuntimeException('TAR string fields must not contain NUL bytes');
        }

        return str_pad($value, $length, "\0");
    }

    private static function trimNullField(string $field): string
    {
        return rtrim($field, "\0");
    }

    private static function assertSafePath(string $path, string $label): void
    {
        if ($path === '') {
            throw new \RuntimeException("{$label} must not be empty");
        }

        self::assertUtf8($path, $label);

        if (preg_match('/[\x00-\x1f\x7f]/', $path) === 1) {
            throw new \RuntimeException("Unsafe {$label}: path contains control bytes");
        }

        if (str_contains($path, "\0") || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new \RuntimeException("Unsafe {$label}: {$path}");
        }

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new \RuntimeException("Unsafe {$label}: {$path}");
        }

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException("Unsafe {$label}: {$path}");
            }
        }
    }

    private static function assertUtf8(string $text, string $label): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new \RuntimeException("{$label} must be valid UTF-8");
        }
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("TAR archive {$label} extends beyond available bytes");
        }
    }
}
