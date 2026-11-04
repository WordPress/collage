<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackageEntry
{
    private const FILETIME_UNIX_EPOCH_OFFSET_SECONDS = 11644473600;
    private const FILETIME_TICKS_PER_SECOND = 10000000;
    private const UINT32_FACTOR = 4294967296;
    private const UNIX_HOST_SYSTEM = 3;
    private const UNIX_FILE_TYPE_MASK = 0xf000;
    private const UNIX_FIFO_TYPE = 0x1000;
    private const UNIX_CHARACTER_DEVICE_TYPE = 0x2000;
    private const UNIX_DIRECTORY_TYPE = 0x4000;
    private const UNIX_BLOCK_DEVICE_TYPE = 0x6000;
    private const UNIX_REGULAR_FILE_TYPE = 0x8000;
    private const UNIX_SYMLINK_TYPE = 0xa000;
    private const UNIX_SOCKET_TYPE = 0xc000;
    private const DOS_READ_ONLY_ATTRIBUTE = 0x01;
    private const DOS_HIDDEN_ATTRIBUTE = 0x02;
    private const DOS_SYSTEM_ATTRIBUTE = 0x04;
    private const DOS_VOLUME_LABEL_ATTRIBUTE = 0x08;
    private const DOS_DIRECTORY_ATTRIBUTE = 0x10;
    private const DOS_ARCHIVE_ATTRIBUTE = 0x20;
    private const INTERNAL_TEXT_ATTRIBUTE = 0x0001;
    private const ZIP64_EXTENDED_INFORMATION_EXTRA_ID = 0x0001;
    private const INFOZIP_UNIX_UID_GID_EXTRA_ID = 0x7875;
    private const WINZIP_AES_EXTRA_ID = 0x9901;

    public function __construct(
        public readonly string $name,
        public readonly int $compressionMethod,
        public readonly int $generalPurposeFlags,
        public readonly int $crc32,
        public readonly int $compressedSize,
        public readonly int $uncompressedSize,
        public readonly int $localHeaderOffset,
        public readonly string $comment = '',
        public readonly int $lastModifiedTime = 0,
        public readonly int $lastModifiedDate = 0,
        public readonly int $externalFileAttributes = 0,
        public readonly int $internalFileAttributes = 0,
        public readonly string $centralExtraFieldData = '',
        public readonly int $versionMadeBy = 0,
        ?string $rawName = null,
        ?string $rawComment = null,
        public readonly string $nameEncoding = 'utf-8',
        public readonly string $commentEncoding = 'utf-8',
        public readonly int $versionNeededToExtract = 20,
        public readonly ?int $centralDirectoryRecordOffset = null,
        public readonly ?int $centralDirectoryRecordEnd = null,
    ) {
        $this->rawName = $rawName ?? $this->name;
        $this->rawComment = $rawComment ?? $this->comment;
        self::parseExtraFields($this->centralExtraFieldData, "central extra fields for {$this->name}");
    }

    public readonly string $rawName;

    public readonly string $rawComment;

    public function isDirectory(): bool
    {
        return str_ends_with($this->name, '/');
    }

    public function hasDosDirectoryAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_DIRECTORY_ATTRIBUTE) !== 0;
    }

    public function hasDosReadOnlyAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_READ_ONLY_ATTRIBUTE) !== 0;
    }

    public function hasDosHiddenAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_HIDDEN_ATTRIBUTE) !== 0;
    }

    public function hasDosSystemAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_SYSTEM_ATTRIBUTE) !== 0;
    }

    public function hasDosVolumeLabelAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_VOLUME_LABEL_ATTRIBUTE) !== 0;
    }

    public function hasDosArchiveAttribute(): bool
    {
        return ($this->externalFileAttributes & self::DOS_ARCHIVE_ATTRIBUTE) !== 0;
    }

    /**
     * @return list<string>
     */
    public function dosAttributeNames(): array
    {
        $names = [];
        if ($this->hasDosReadOnlyAttribute()) {
            $names[] = 'read-only';
        }
        if ($this->hasDosHiddenAttribute()) {
            $names[] = 'hidden';
        }
        if ($this->hasDosSystemAttribute()) {
            $names[] = 'system';
        }
        if ($this->hasDosVolumeLabelAttribute()) {
            $names[] = 'volume-label';
        }
        if ($this->hasDosDirectoryAttribute()) {
            $names[] = 'directory';
        }
        if ($this->hasDosArchiveAttribute()) {
            $names[] = 'archive';
        }

        return $names;
    }

    public function hasTextInternalAttribute(): bool
    {
        return ($this->internalFileAttributes & self::INTERNAL_TEXT_ATTRIBUTE) !== 0;
    }

    public function unknownInternalAttributeBits(): int
    {
        return $this->internalFileAttributes & ~self::INTERNAL_TEXT_ATTRIBUTE;
    }

    /**
     * @return list<string>
     */
    public function internalAttributeNames(): array
    {
        $names = [];
        if ($this->hasTextInternalAttribute()) {
            $names[] = 'apparently-text';
        }

        $unknownBits = $this->unknownInternalAttributeBits();
        if ($unknownBits !== 0) {
            $names[] = sprintf('unknown-0x%04x', $unknownBits);
        }

        return $names;
    }

    public function crc32Hex(): string
    {
        return sprintf('%08x', $this->crc32);
    }

    public function modifiedDosTime(): int
    {
        return $this->lastModifiedTime;
    }

    public function modifiedDosDate(): int
    {
        return $this->lastModifiedDate;
    }

    public function madeByHostSystem(): int
    {
        return ($this->versionMadeBy >> 8) & 0xff;
    }

    public function madeByVersion(): int
    {
        return $this->versionMadeBy & 0xff;
    }

    public function neededToExtractVersion(): int
    {
        return $this->versionNeededToExtract;
    }

    public function unixMode(): ?int
    {
        if ($this->madeByHostSystem() !== self::UNIX_HOST_SYSTEM) {
            return null;
        }

        $mode = ($this->externalFileAttributes >> 16) & 0xffff;

        return $mode === 0 ? null : $mode;
    }

    public function unixFileType(): ?int
    {
        $mode = $this->unixMode();
        if ($mode === null) {
            return null;
        }

        $type = $mode & self::UNIX_FILE_TYPE_MASK;

        return $type === 0 ? null : $type;
    }

    public function unixFileTypeName(): ?string
    {
        return match ($this->unixFileType()) {
            null => null,
            self::UNIX_FIFO_TYPE => 'fifo',
            self::UNIX_CHARACTER_DEVICE_TYPE => 'character-device',
            self::UNIX_DIRECTORY_TYPE => 'directory',
            self::UNIX_BLOCK_DEVICE_TYPE => 'block-device',
            self::UNIX_REGULAR_FILE_TYPE => 'regular-file',
            self::UNIX_SYMLINK_TYPE => 'symlink',
            self::UNIX_SOCKET_TYPE => 'socket',
            default => 'unknown',
        };
    }

    public function isUnixSpecialFile(): bool
    {
        $type = $this->unixFileType();

        return $type !== null
            && $type !== self::UNIX_REGULAR_FILE_TYPE
            && $type !== self::UNIX_DIRECTORY_TYPE
            && $type !== self::UNIX_SYMLINK_TYPE;
    }

    public function isUnixSymlink(): bool
    {
        return $this->unixFileType() === self::UNIX_SYMLINK_TYPE;
    }

    public function unixPermissionBits(): ?int
    {
        $mode = $this->unixMode();

        return $mode === null ? null : $mode & 07777;
    }

    public function isUnixExecutableFile(): bool
    {
        $permissions = $this->unixPermissionBits();

        return !$this->isDirectory()
            && !$this->isUnixSymlink()
            && $this->unixFileType() === self::UNIX_REGULAR_FILE_TYPE
            && $permissions !== null
            && ($permissions & 0111) !== 0;
    }

    public function hasDosLastModifiedTimestamp(): bool
    {
        return $this->lastModifiedTime !== 0 || $this->lastModifiedDate !== 0;
    }

    public function dosLastModifiedTimestamp(): ?int
    {
        if (!$this->hasDosLastModifiedTimestamp()) {
            return null;
        }

        $year = (($this->lastModifiedDate >> 9) & 0x7f) + 1980;
        $month = ($this->lastModifiedDate >> 5) & 0x0f;
        $day = $this->lastModifiedDate & 0x1f;
        $hour = ($this->lastModifiedTime >> 11) & 0x1f;
        $minute = ($this->lastModifiedTime >> 5) & 0x3f;
        $second = ($this->lastModifiedTime & 0x1f) * 2;

        if (
            !checkdate($month, $day, $year)
            || $hour > 23
            || $minute > 59
            || $second > 59
        ) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
            new \DateTimeZone('UTC')
        );

        return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : null;
    }

    public function lastModifiedTimestamp(): ?int
    {
        $extendedTimestamp = $this->extendedLastModifiedTimestamp();
        if ($extendedTimestamp !== null) {
            return $extendedTimestamp;
        }

        $ntfsTimestamp = $this->ntfsLastModifiedTimestamp();
        if ($ntfsTimestamp !== null) {
            return $ntfsTimestamp;
        }

        return $this->dosLastModifiedTimestamp();
    }

    /**
     * @return list<array{id:int, data:string}>
     */
    public function centralExtraFields(): array
    {
        return self::parseExtraFields($this->centralExtraFieldData, "central extra fields for {$this->name}");
    }

    public function centralExtraField(int $id): ?string
    {
        if ($id < 0 || $id > 0xffff) {
            throw new \InvalidArgumentException('ZIP extra field id must fit in an unsigned 16-bit field');
        }

        foreach ($this->centralExtraFields() as $field) {
            if ($field['id'] === $id) {
                return $field['data'];
            }
        }

        return null;
    }

    public function extendedLastModifiedTimestamp(): ?int
    {
        return self::extendedTimestampFromExtraField(
            $this->centralExtraField(0x5455),
            "central extra fields for {$this->name}"
        );
    }

    /**
     * @return array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null
     */
    public function extendedTimestamps(): ?array
    {
        return self::extendedTimestampsFromExtraField(
            $this->centralExtraField(0x5455),
            "central extra fields for {$this->name}"
        );
    }

    public function extendedAccessedTimestamp(): ?int
    {
        $timestamps = $this->extendedTimestamps();

        return $timestamps['accessedAt'] ?? null;
    }

    public function extendedCreatedTimestamp(): ?int
    {
        $timestamps = $this->extendedTimestamps();

        return $timestamps['createdAt'] ?? null;
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    public function ntfsTimestamps(): ?array
    {
        return self::ntfsTimestampsFromExtraField(
            $this->centralExtraField(0x000a),
            "central extra fields for {$this->name}"
        );
    }

    public function ntfsLastModifiedTimestamp(): ?int
    {
        $timestamps = $this->ntfsTimestamps();

        return $timestamps['modifiedAt'] ?? null;
    }

    /**
     * @return array{version:int, uid:int, gid:int, uidByteLength:int, gidByteLength:int}|null
     */
    public function unixUidGid(): ?array
    {
        return self::unixUidGidFromExtraField(
            $this->centralExtraField(self::INFOZIP_UNIX_UID_GID_EXTRA_ID),
            "central extra fields for {$this->name}"
        );
    }

    /**
     * @return list<array{id:int, data:string}>
     */
    public static function extraFieldsFromData(string $bytes, string $label, bool $allowZip64 = false): array
    {
        return self::parseExtraFields($bytes, $label, $allowZip64);
    }

    public static function extendedTimestampFromExtraField(?string $data, string $label): ?int
    {
        $timestamps = self::extendedTimestampsFromExtraField($data, $label);
        if ($timestamps === null) {
            return null;
        }

        return $timestamps['modifiedAt'] ?? null;
    }

    /**
     * @return array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null
     */
    public static function extendedTimestampsFromExtraField(?string $data, string $label): ?array
    {
        return self::parseExtendedTimestamps($data, $label);
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    public static function ntfsTimestampsFromExtraField(?string $data, string $label): ?array
    {
        if ($data === null) {
            return null;
        }

        return self::parseNtfsTimestamps($data, $label);
    }

    /**
     * @return array{version:int, uid:int, gid:int, uidByteLength:int, gidByteLength:int}|null
     */
    public static function unixUidGidFromExtraField(?string $data, string $label): ?array
    {
        return self::parseUnixUidGid($data, $label);
    }

    public static function validateExtraFieldData(string $bytes, string $label): void
    {
        self::parseExtraFields($bytes, $label);
    }

    /**
     * @return list<array{id:int, data:string}>
     */
    private static function parseExtraFields(string $bytes, string $label, bool $allowZip64 = false): array
    {
        $fields = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field header");
            }

            $header = unpack('vid/vsize', substr($bytes, $cursor, 4));
            if (!is_array($header)) {
                throw new \RuntimeException("Unable to read ZIP {$label}");
            }

            $id = (int) $header['id'];
            $size = (int) $header['size'];
            $dataStart = $cursor + 4;
            if ($dataStart + $size > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field payload");
            }

            $data = substr($bytes, $dataStart, $size);
            if ($id === self::ZIP64_EXTENDED_INFORMATION_EXTRA_ID && !$allowZip64) {
                throw new \RuntimeException("ZIP64 extra field for {$label} is not supported by this bounded package reader");
            }
            if ($id === self::WINZIP_AES_EXTRA_ID) {
                throw new \RuntimeException("WinZip AES extra field for {$label} is not supported by this bounded package reader");
            }
            if ($id === self::INFOZIP_UNIX_UID_GID_EXTRA_ID) {
                self::parseUnixUidGid($data, $label);
            }
            if ($id === 0x5455) {
                self::parseExtendedTimestamps($data, $label);
            }
            if ($id === 0x000a) {
                self::parseNtfsTimestamps($data, $label);
            }

            $fields[] = [
                'id' => $id,
                'data' => $data,
            ];
            $cursor = $dataStart + $size;
        }

        return $fields;
    }

    /**
     * @return array{version:int, uid:int, gid:int, uidByteLength:int, gidByteLength:int}|null
     */
    private static function parseUnixUidGid(?string $data, string $label): ?array
    {
        if ($data === null) {
            return null;
        }

        $length = strlen($data);
        if ($length < 3) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} is truncated");
        }

        $version = ord($data[0]);
        if ($version !== 1) {
            throw new \RuntimeException(
                "Info-ZIP Unix UID/GID extra field for {$label} uses unsupported version {$version}"
            );
        }

        $cursor = 1;
        $uidByteLength = ord($data[$cursor]);
        $cursor++;
        if ($uidByteLength < 1) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} has an empty UID");
        }

        if ($cursor + $uidByteLength > $length) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} has a truncated UID");
        }

        $uidBytes = substr($data, $cursor, $uidByteLength);
        $cursor += $uidByteLength;
        if ($cursor >= $length) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} is missing a GID length");
        }

        $gidByteLength = ord($data[$cursor]);
        $cursor++;
        if ($gidByteLength < 1) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} has an empty GID");
        }

        if ($cursor + $gidByteLength > $length) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} has a truncated GID");
        }

        $gidBytes = substr($data, $cursor, $gidByteLength);
        $cursor += $gidByteLength;
        if ($cursor !== $length) {
            throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} has trailing bytes");
        }

        return [
            'version' => $version,
            'uid' => self::parseLittleEndianUnsignedInteger($uidBytes, $label, 'UID'),
            'gid' => self::parseLittleEndianUnsignedInteger($gidBytes, $label, 'GID'),
            'uidByteLength' => $uidByteLength,
            'gidByteLength' => $gidByteLength,
        ];
    }

    private static function parseLittleEndianUnsignedInteger(string $bytes, string $label, string $fieldName): int
    {
        $value = 0;
        $factor = 1;
        $length = strlen($bytes);

        for ($index = 0; $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            if ($byte !== 0 && $factor > intdiv(PHP_INT_MAX, $byte)) {
                throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} {$fieldName} is too large");
            }

            $addend = $byte * $factor;
            if ($value > PHP_INT_MAX - $addend) {
                throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} {$fieldName} is too large");
            }

            $value += $addend;
            if ($index === $length - 1) {
                continue;
            }

            if ($factor > intdiv(PHP_INT_MAX, 256)) {
                if (substr($bytes, $index + 1) !== str_repeat("\0", $length - $index - 1)) {
                    throw new \RuntimeException("Info-ZIP Unix UID/GID extra field for {$label} {$fieldName} is too large");
                }
                break;
            }

            $factor *= 256;
        }

        return $value;
    }

    /**
     * @return array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null
     */
    private static function parseExtendedTimestamps(?string $data, string $label): ?array
    {
        if ($data === null || strlen($data) < 1) {
            return null;
        }

        $flags = ord($data[0]);
        $unknownFlags = $flags & ~0x07;
        if ($unknownFlags !== 0) {
            return null;
        }

        $cursor = 1;
        $timestamps = [];
        $fields = [
            0x01 => 'modifiedAt',
            0x02 => 'accessedAt',
            0x04 => 'createdAt',
        ];

        foreach ($fields as $bit => $name) {
            if (($flags & $bit) === 0) {
                continue;
            }

            if ($cursor + 4 > strlen($data)) {
                if (
                    $name === 'accessedAt'
                    && $flags === 0x03
                    && array_key_exists('modifiedAt', $timestamps)
                    && $cursor === strlen($data)
                ) {
                    break;
                }
                return null;
            }

            $values = unpack('Vtimestamp', substr($data, $cursor, 4));
            if (!is_array($values)) {
                throw new \RuntimeException("Unable to read ZIP extended timestamp extra field for {$label}");
            }

            $timestamps[$name] = (int) $values['timestamp'];
            $cursor += 4;
        }

        if ($cursor !== strlen($data)) {
            return null;
        }

        return $timestamps === [] ? null : $timestamps;
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    private static function parseNtfsTimestamps(string $data, string $label): ?array
    {
        $length = strlen($data);
        if ($length < 4) {
            throw new \RuntimeException("ZIP NTFS extra field for {$label} is truncated");
        }

        $reserved = unpack('Vvalue', substr($data, 0, 4));
        if (!is_array($reserved)) {
            throw new \RuntimeException("Unable to read ZIP NTFS extra field for {$label}");
        }

        if ((int) $reserved['value'] !== 0) {
            throw new \RuntimeException("ZIP NTFS extra field for {$label} contains nonzero reserved bytes");
        }

        $cursor = 4;
        while ($cursor < $length) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException("ZIP NTFS extra field for {$label} contains a truncated attribute header");
            }

            $header = unpack('vtag/vsize', substr($data, $cursor, 4));
            if (!is_array($header)) {
                throw new \RuntimeException("Unable to read ZIP NTFS extra field for {$label}");
            }

            $tag = (int) $header['tag'];
            $size = (int) $header['size'];
            $valueStart = $cursor + 4;
            if ($valueStart + $size > $length) {
                throw new \RuntimeException("ZIP NTFS extra field for {$label} contains a truncated attribute payload");
            }

            $value = substr($data, $valueStart, $size);
            if ($tag === 0x0001) {
                if ($size !== 24) {
                    throw new \RuntimeException("ZIP NTFS timestamp attribute for {$label} must contain three FILETIME values");
                }

                return [
                    'modifiedAt' => self::fileTimeToUnixTimestamp(substr($value, 0, 8), $label),
                    'accessedAt' => self::fileTimeToUnixTimestamp(substr($value, 8, 8), $label),
                    'createdAt' => self::fileTimeToUnixTimestamp(substr($value, 16, 8), $label),
                ];
            }

            $cursor = $valueStart + $size;
        }

        return null;
    }

    private static function fileTimeToUnixTimestamp(string $bytes, string $label): int
    {
        $parts = unpack('Vlow/Vhigh', $bytes);
        if (!is_array($parts)) {
            throw new \RuntimeException("Unable to read ZIP NTFS FILETIME value for {$label}");
        }

        $low = (int) $parts['low'];
        $high = (int) $parts['high'];
        $maxHigh = intdiv(PHP_INT_MAX, self::UINT32_FACTOR);
        if ($high > $maxHigh) {
            throw new \RuntimeException("ZIP NTFS FILETIME value for {$label} is too large for this platform");
        }

        $fileTime = ($high * self::UINT32_FACTOR) + $low;
        if ($fileTime > PHP_INT_MAX) {
            throw new \RuntimeException("ZIP NTFS FILETIME value for {$label} is too large for this platform");
        }

        return intdiv($fileTime, self::FILETIME_TICKS_PER_SECOND) - self::FILETIME_UNIX_EPOCH_OFFSET_SECONDS;
    }
}
