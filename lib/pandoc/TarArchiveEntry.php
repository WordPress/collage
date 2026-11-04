<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TarArchiveEntry
{
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';
    public const NAME_SOURCE_HEADER = 'header';
    public const NAME_SOURCE_USTAR_PREFIX = 'ustar-prefix';
    public const NAME_SOURCE_PAX_PATH = 'pax-path';
    public const NAME_SOURCE_GNU_LONG_NAME = 'gnu-long-name';

    /**
     * @param array<string, string> $paxHeaders Effective PAX metadata after inherited records, local overrides, and deletions.
     * @param array<string, string> $globalPaxHeaders Effective global PAX metadata inherited before local overrides.
     * @param array<string, string> $localPaxHeaders Raw local PAX metadata records attached to this entry.
     * @param list<string> $deletedPaxHeaderKeys Local PAX keys deleted by zero-length records.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly int $size,
        public readonly int $modifiedAt,
        public readonly ?int $accessedAt,
        public readonly ?int $changedAt,
        public readonly ?int $createdAt,
        public readonly int $mode,
        public readonly int $uid,
        public readonly int $gid,
        public readonly string $linkName,
        public readonly string $userName,
        public readonly string $groupName,
        public readonly array $paxHeaders,
        public readonly int $dataOffset,
        public readonly array $globalPaxHeaders = [],
        public readonly array $localPaxHeaders = [],
        public readonly array $deletedPaxHeaderKeys = [],
        public readonly string $nameSource = self::NAME_SOURCE_HEADER,
        public readonly ?string $gnuLongName = null,
        public readonly string $typeFlag = '0',
    ) {
    }

    public function isRegularFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isDirectory(): bool
    {
        return $this->type === self::TYPE_DIRECTORY;
    }
}
