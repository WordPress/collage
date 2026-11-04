<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CompoundFileBinary
{
    private const SIGNATURE = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";
    private const FREESECT = 0xffffffff;
    private const ENDOFCHAIN = 0xfffffffe;
    private const FATSECT = 0xfffffffd;
    private const DIFSECT = 0xfffffffc;
    private const MAXREGSECT = 0xfffffffa;

    /** @var list<int> */
    private array $fat;

    /** @var list<array<string,mixed>> */
    private array $entries;

    /** @var array<string, int> */
    private array $entriesByName;

    /** @var list<int>|null */
    private ?array $miniFat = null;

    private ?string $miniStream = null;

    /**
     * @param list<int> $fat
     * @param list<array<string,mixed>> $entries
     * @param array<string, int> $entriesByName
     */
    private function __construct(
        private readonly string $bytes,
        private readonly int $sectorSize,
        private readonly int $miniSectorSize,
        private readonly int $miniStreamCutoff,
        private readonly int $firstDirectorySector,
        private readonly int $firstMiniFatSector,
        private readonly int $miniFatSectorCount,
        array $fat,
        array $entries,
        array $entriesByName,
        private readonly int $maxStreamBytes
    ) {
        $this->fat = $fat;
        $this->entries = $entries;
        $this->entriesByName = $entriesByName;
    }

    public static function fromBytes(string $bytes, int $maxStreamBytes = 16777216): self
    {
        if (strlen($bytes) < 512 || substr($bytes, 0, 8) !== self::SIGNATURE) {
            throw new \InvalidArgumentException('CFB file is missing the compound-file signature');
        }
        if (substr($bytes, 8, 16) !== str_repeat("\0", 16)) {
            throw new \RuntimeException('CFB header CLSID must be CLSID_NULL');
        }
        if (substr($bytes, 34, 6) !== str_repeat("\0", 6)) {
            throw new \RuntimeException('CFB header reserved bytes must be zero');
        }

        $minorVersion = self::u16($bytes, 24);
        $majorVersion = self::u16($bytes, 26);
        $byteOrder = self::u16($bytes, 28);
        $sectorShift = self::u16($bytes, 30);
        $miniSectorShift = self::u16($bytes, 32);
        if ($byteOrder !== 0xfffe) {
            throw new \InvalidArgumentException('CFB file must use little-endian byte order');
        }
        if (!in_array($minorVersion, [0x0021, 0x003e], true)) {
            throw new \InvalidArgumentException('CFB file uses an unsupported minor version');
        }
        if (!in_array($majorVersion, [3, 4], true)) {
            throw new \InvalidArgumentException('CFB file uses an unsupported major version');
        }
        if (!in_array($sectorShift, [9, 12], true)) {
            throw new \InvalidArgumentException('CFB file uses an unsupported sector size');
        }
        if ($majorVersion === 3 && $sectorShift !== 9) {
            throw new \InvalidArgumentException('CFB version 3 files must use 512-byte sectors');
        }
        if ($majorVersion === 4 && $sectorShift !== 12) {
            throw new \InvalidArgumentException('CFB version 4 files must use 4096-byte sectors');
        }
        if ($miniSectorShift !== 6) {
            throw new \InvalidArgumentException('CFB file uses an unsupported mini-sector size');
        }

        $sectorSize = 1 << $sectorShift;
        $miniSectorSize = 1 << $miniSectorShift;
        if ($majorVersion === 4) {
            if (strlen($bytes) < $sectorSize) {
                throw new \RuntimeException('CFB version 4 file is shorter than the header sector size');
            }
            if (substr($bytes, 512, $sectorSize - 512) !== str_repeat("\0", $sectorSize - 512)) {
                throw new \RuntimeException('CFB version 4 header sector padding must be zero');
            }
        }
        if ((strlen($bytes) - $sectorSize) % $sectorSize !== 0) {
            throw new \RuntimeException('CFB file length must end on a sector boundary');
        }
        $directorySectorCount = self::u32($bytes, 40);
        $fatSectorCount = self::u32($bytes, 44);
        $firstDirectorySector = self::u32($bytes, 48);
        $transactionSignature = self::u32($bytes, 52);
        $miniStreamCutoff = self::u32($bytes, 56);
        $firstMiniFatSector = self::u32($bytes, 60);
        $miniFatSectorCount = self::u32($bytes, 64);
        $firstDifatSector = self::u32($bytes, 68);
        $difatSectorCount = self::u32($bytes, 72);
        if ($majorVersion === 3 && $directorySectorCount !== 0) {
            throw new \RuntimeException('CFB version 3 files must not declare directory sectors in the header');
        }
        if ($transactionSignature !== 0) {
            throw new \RuntimeException('CFB transaction signature number must be zero');
        }
        if ($miniStreamCutoff !== 4096) {
            throw new \RuntimeException('CFB mini stream cutoff size must be 4096 bytes');
        }
        if ($miniFatSectorCount === 0 && $firstMiniFatSector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB header declares no MiniFAT sectors but the MiniFAT start sector is not ENDOFCHAIN');
        }
        if ($miniFatSectorCount > 0 && !self::isRegularSector($firstMiniFatSector)) {
            throw new \RuntimeException('CFB header declares MiniFAT sectors without a valid MiniFAT start sector');
        }
        if ($difatSectorCount === 0 && $firstDifatSector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB header declares no DIFAT sectors but the DIFAT start sector is not ENDOFCHAIN');
        }
        if ($difatSectorCount > 0 && !self::isRegularSector($firstDifatSector)) {
            throw new \RuntimeException('CFB header declares DIFAT sectors without a valid DIFAT start sector');
        }

        $difat = [];
        for ($index = 0; $index < 109; $index++) {
            $sector = self::u32($bytes, 76 + ($index * 4));
            if (self::isRegularSector($sector)) {
                $difat[] = $sector;
            } elseif ($sector !== self::FREESECT) {
                throw new \RuntimeException('CFB DIFAT FAT-sector entries must be regular sectors or FREESECT');
            }
        }

        $sectorCount = self::sectorCount($bytes, $sectorSize);
        $difatSector = $firstDifatSector;
        $seenDifat = [];
        for ($index = 0; $index < $difatSectorCount; $index++) {
            if (!self::isRegularSector($difatSector) || $difatSector >= $sectorCount || isset($seenDifat[$difatSector])) {
                throw new \RuntimeException('CFB DIFAT chain is invalid');
            }
            $seenDifat[$difatSector] = true;
            $sectorBytes = self::sectorBytes($bytes, $sectorSize, $difatSector);
            $entryCount = intdiv($sectorSize, 4) - 1;
            for ($entry = 0; $entry < $entryCount; $entry++) {
                $fatSector = self::u32($sectorBytes, $entry * 4);
                if (self::isRegularSector($fatSector)) {
                    $difat[] = $fatSector;
                } elseif ($fatSector !== self::FREESECT) {
                    throw new \RuntimeException('CFB DIFAT FAT-sector entries must be regular sectors or FREESECT');
                }
            }
            $difatSector = self::u32($sectorBytes, $sectorSize - 4);
        }
        if ($difatSectorCount > 0 && $difatSector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB DIFAT chain is not terminated');
        }
        if (count($difat) > $fatSectorCount) {
            throw new \RuntimeException('CFB DIFAT contains FAT sector entries beyond the declared FAT sector count');
        }

        $fatSectorIds = array_slice($difat, 0, $fatSectorCount);
        if (count($fatSectorIds) !== $fatSectorCount) {
            throw new \RuntimeException('CFB header does not provide all FAT sectors');
        }

        $seenFatSectors = [];
        foreach ($fatSectorIds as $fatSectorId) {
            if (!self::isRegularSector($fatSectorId) || $fatSectorId >= $sectorCount) {
                throw new \RuntimeException('CFB FAT sector points outside the file');
            }
            if (isset($seenFatSectors[$fatSectorId])) {
                throw new \RuntimeException('CFB DIFAT contains duplicate FAT sectors');
            }
            if (isset($seenDifat[$fatSectorId])) {
                throw new \RuntimeException('CFB DIFAT reuses a DIFAT sector as a FAT sector');
            }

            $seenFatSectors[$fatSectorId] = true;
        }

        $fat = [];
        foreach ($fatSectorIds as $fatSectorId) {
            $sectorBytes = self::sectorBytes($bytes, $sectorSize, $fatSectorId);
            for ($offset = 0; $offset < $sectorSize; $offset += 4) {
                $fat[] = self::u32($sectorBytes, $offset);
            }
        }
        self::validateFatEntryValues($fat, $sectorCount, $fatSectorIds, array_map('intval', array_keys($seenDifat)));
        for ($sectorId = $sectorCount, $fatEntryCount = count($fat); $sectorId < $fatEntryCount; $sectorId++) {
            if ($fat[$sectorId] !== self::FREESECT) {
                throw new \RuntimeException('CFB FAT entries beyond the physical file must be marked FREESECT');
            }
        }
        foreach ($fatSectorIds as $fatSectorId) {
            if (($fat[$fatSectorId] ?? null) !== self::FATSECT) {
                throw new \RuntimeException('CFB FAT sector is not marked as FATSECT');
            }
        }
        foreach (array_keys($seenDifat) as $difatSectorId) {
            if (($fat[(int) $difatSectorId] ?? null) !== self::DIFSECT) {
                throw new \RuntimeException('CFB DIFAT sector is not marked as DIFSECT');
            }
        }

        $reader = new self(
            $bytes,
            $sectorSize,
            $miniSectorSize,
            $miniStreamCutoff,
            $firstDirectorySector,
            $firstMiniFatSector,
            $miniFatSectorCount,
            $fat,
            [],
            [],
            $maxStreamBytes
        );

        $directorySectorIds = $reader->regularSectorChainIds($firstDirectorySector, null, 'directory');
        if ($majorVersion === 4 && count($directorySectorIds) !== $directorySectorCount) {
            throw new \RuntimeException('CFB version 4 directory sector count does not match the directory chain length');
        }
        $directoryBytes = '';
        foreach ($directorySectorIds as $directorySectorId) {
            $directoryBytes .= self::sectorBytes($bytes, $sectorSize, $directorySectorId);
        }
        [$entries, $entriesByName] = self::parseDirectory($directoryBytes, $majorVersion);

        $reader = new self(
            $bytes,
            $sectorSize,
            $miniSectorSize,
            $miniStreamCutoff,
            $firstDirectorySector,
            $firstMiniFatSector,
            $miniFatSectorCount,
            $fat,
            $entries,
            $entriesByName,
            $maxStreamBytes
        );
        $reader->validateSectorAllocation($fatSectorIds, array_map('intval', array_keys($seenDifat)));

        return $reader;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function streamNames(): array
    {
        $names = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 2) {
                $names[] = $entry['path'];
            }
        }

        return $names;
    }

    public function hasStream(string $name): bool
    {
        $entry = $this->findEntry($name);

        return $entry !== null && $entry['type'] === 2;
    }

    public function streamSize(string $name): int
    {
        $entry = $this->requireStreamEntry($name);

        return $entry['size'];
    }

    public function readStream(string $name): string
    {
        $entry = $this->requireStreamEntry($name);
        if ($entry['size'] === 0) {
            return '';
        }
        if ($entry['size'] > $this->maxStreamBytes) {
            throw new \RuntimeException('CFB stream exceeds the configured size limit: ' . $entry['name']);
        }

        if ($this->streamUsesMiniStream($entry)) {
            return $this->readMiniSectorChain($entry['startSector'], $entry['size'], $entry['name']);
        }

        return $this->readRegularSectorChain($entry['startSector'], $entry['size'], $entry['name']);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findEntry(string $name): ?array
    {
        $normalized = self::normalizeName($name);
        $index = $this->entriesByName[$normalized] ?? null;

        return $index === null ? null : $this->entries[$index];
    }

    /**
     * @return array<string,mixed>
     */
    private function requireStreamEntry(string $name): array
    {
        $entry = $this->findEntry($name);
        if ($entry === null || $entry['type'] !== 2) {
            throw new \RuntimeException('CFB stream is missing: ' . $name);
        }

        return $entry;
    }

    private function readRegularSectorChain(int $startSector, ?int $expectedSize, string $label): string
    {
        $sectorIds = $this->regularSectorChainIds($startSector, $expectedSize, $label);
        $data = '';
        foreach ($sectorIds as $sector) {
            $data .= self::sectorBytes($this->bytes, $this->sectorSize, $sector);
        }

        if ($expectedSize !== null) {
            if (strlen($data) < $expectedSize) {
                throw new \RuntimeException('CFB sector chain is shorter than declared for ' . $label);
            }

            return substr($data, 0, $expectedSize);
        }

        return $data;
    }

    /**
     * @return list<int>
     */
    private function regularSectorChainIds(int $startSector, ?int $expectedSize, string $label): array
    {
        if (!self::isRegularSector($startSector)) {
            if ($expectedSize === 0) {
                return [];
            }
            throw new \RuntimeException('CFB sector chain is missing for ' . $label);
        }

        $sectorCount = self::sectorCount($this->bytes, $this->sectorSize);
        $seen = [];
        $sectorIds = [];
        $bytes = 0;
        $sector = $startSector;
        while (self::isRegularSector($sector)) {
            if ($sector >= $sectorCount || isset($seen[$sector])) {
                throw new \RuntimeException('CFB sector chain is invalid for ' . $label);
            }
            $seen[$sector] = true;
            $sectorIds[] = $sector;
            $bytes += $this->sectorSize;
            if ($bytes > $this->maxStreamBytes + $this->sectorSize) {
                throw new \RuntimeException('CFB sector chain exceeds the configured size limit for ' . $label);
            }
            $sector = $this->fat[$sector] ?? self::FREESECT;
        }

        if ($sector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB sector chain is not terminated for ' . $label);
        }
        if ($expectedSize !== null) {
            if ($bytes < $expectedSize) {
                throw new \RuntimeException('CFB sector chain is shorter than declared for ' . $label);
            }

            $expectedSectorCount = $expectedSize === 0 ? 0 : intdiv($expectedSize + $this->sectorSize - 1, $this->sectorSize);
            if (count($sectorIds) > $expectedSectorCount) {
                throw new \RuntimeException('CFB sector chain is longer than declared for ' . $label);
            }
        }

        return $sectorIds;
    }

    private function readMiniSectorChain(int $startMiniSector, int $expectedSize, string $label): string
    {
        $miniSectorIds = $this->miniSectorChainIds($startMiniSector, $expectedSize, $label);
        $miniStream = $this->loadMiniStream();
        $data = '';
        foreach ($miniSectorIds as $sector) {
            $offset = $sector * $this->miniSectorSize;
            $data .= substr($miniStream, $offset, $this->miniSectorSize);
        }
        if (strlen($data) < $expectedSize) {
            throw new \RuntimeException('CFB mini-sector chain is shorter than declared for ' . $label);
        }

        return substr($data, 0, $expectedSize);
    }

    /**
     * @return list<int>
     */
    private function miniSectorChainIds(int $startMiniSector, int $expectedSize, string $label): array
    {
        if (!self::isRegularSector($startMiniSector)) {
            if ($expectedSize === 0) {
                return [];
            }
            throw new \RuntimeException('CFB mini-sector chain is missing for ' . $label);
        }

        $miniFat = $this->loadMiniFat();
        $miniStream = $this->loadMiniStream();
        $seen = [];
        $sectorIds = [];
        $bytes = 0;
        $sector = $startMiniSector;
        while (self::isRegularSector($sector)) {
            $offset = $sector * $this->miniSectorSize;
            if ($offset + $this->miniSectorSize > strlen($miniStream) || isset($seen[$sector])) {
                throw new \RuntimeException('CFB mini-sector chain is invalid for ' . $label);
            }
            $seen[$sector] = true;
            $sectorIds[] = $sector;
            $bytes += $this->miniSectorSize;
            if ($bytes > $this->maxStreamBytes + $this->miniSectorSize) {
                throw new \RuntimeException('CFB mini-sector chain exceeds the configured size limit for ' . $label);
            }
            $sector = $miniFat[$sector] ?? self::FREESECT;
        }

        if ($sector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB mini-sector chain is not terminated for ' . $label);
        }
        if ($bytes < $expectedSize) {
            throw new \RuntimeException('CFB mini-sector chain is shorter than declared for ' . $label);
        }
        $expectedMiniSectorCount = $expectedSize === 0 ? 0 : intdiv($expectedSize + $this->miniSectorSize - 1, $this->miniSectorSize);
        if (count($sectorIds) > $expectedMiniSectorCount) {
            throw new \RuntimeException('CFB mini-sector chain is longer than declared for ' . $label);
        }

        return $sectorIds;
    }

    /**
     * @param list<int> $fatSectorIds
     * @param list<int> $difatSectorIds
     */
    private function validateSectorAllocation(array $fatSectorIds, array $difatSectorIds): void
    {
        $reserved = [];
        $ownedSectors = [];
        $markReserved = static function (array $sectorIds, string $role) use (&$reserved, &$ownedSectors): void {
            foreach ($sectorIds as $sectorId) {
                if (isset($reserved[$sectorId])) {
                    throw new \RuntimeException('CFB sector is assigned to multiple metadata chains: ' . $role);
                }
                $reserved[$sectorId] = $role;
                $ownedSectors[$sectorId] = $role;
            }
        };

        $markReserved($fatSectorIds, 'FAT');
        $markReserved($difatSectorIds, 'DIFAT');
        $fatSectorLookup = array_fill_keys($fatSectorIds, true);
        $difatSectorLookup = array_fill_keys($difatSectorIds, true);
        $sectorCount = self::sectorCount($this->bytes, $this->sectorSize);
        for ($sectorId = 0; $sectorId < $sectorCount; $sectorId++) {
            $fatEntry = $this->fat[$sectorId] ?? self::FREESECT;
            if ($fatEntry === self::FATSECT && !isset($fatSectorLookup[$sectorId])) {
                throw new \RuntimeException('CFB FAT marks an unlisted sector as FATSECT');
            }
            if ($fatEntry === self::DIFSECT && !isset($difatSectorLookup[$sectorId])) {
                throw new \RuntimeException('CFB FAT marks an unlisted sector as DIFSECT');
            }
        }

        $markReserved($this->regularSectorChainIds($this->firstDirectorySector, null, 'directory'), 'directory');
        if (self::isRegularSector($this->firstMiniFatSector) && $this->miniFatSectorCount > 0) {
            $miniFatSectorIds = $this->regularSectorChainIds(
                $this->firstMiniFatSector,
                $this->miniFatSectorCount * $this->sectorSize,
                'MiniFAT'
            );
            if (count($miniFatSectorIds) !== $this->miniFatSectorCount) {
                throw new \RuntimeException('CFB MiniFAT sector count does not match the MiniFAT chain length');
            }
            $markReserved($miniFatSectorIds, 'MiniFAT');
        }

        $root = null;
        foreach ($this->entries as $entry) {
            if (($entry['type'] ?? null) === 5) {
                $root = $entry;
                break;
            }
        }
        $rootMiniStreamSize = 0;
        if ($root !== null && (int) ($root['size'] ?? 0) > 0) {
            if (!self::isRegularSector($this->firstMiniFatSector) || $this->miniFatSectorCount <= 0) {
                throw new \RuntimeException('CFB root mini stream requires MiniFAT metadata');
            }
            $rootMiniStreamSize = (int) $root['size'];
            $markReserved(
                $this->regularSectorChainIds((int) $root['startSector'], $rootMiniStreamSize, 'Root Entry mini stream'),
                'root mini stream'
            );
        }

        $regularStreamSectors = [];
        $miniStreamSectors = [];
        foreach ($this->entries as $entry) {
            if (($entry['type'] ?? null) !== 2) {
                continue;
            }

            $name = (string) ($entry['path'] ?? $entry['name'] ?? 'stream');
            $size = (int) ($entry['size'] ?? 0);
            if ($size === 0) {
                continue;
            }

            if ($this->streamUsesMiniStream($entry)) {
                foreach ($this->miniSectorChainIds((int) $entry['startSector'], $size, $name) as $miniSectorId) {
                    if (isset($miniStreamSectors[$miniSectorId])) {
                        throw new \RuntimeException('CFB mini-sector is shared by multiple streams: ' . $name);
                    }
                    $miniStreamSectors[$miniSectorId] = $name;
                }
                continue;
            }

            foreach ($this->regularSectorChainIds((int) $entry['startSector'], $size, $name) as $sectorId) {
                if (isset($reserved[$sectorId])) {
                    throw new \RuntimeException('CFB stream sector overlaps ' . $reserved[$sectorId] . ': ' . $name);
                }
                if (isset($regularStreamSectors[$sectorId])) {
                    throw new \RuntimeException('CFB stream sector is shared by multiple streams: ' . $name);
                }
                $regularStreamSectors[$sectorId] = $name;
                $ownedSectors[$sectorId] = $name;
            }
        }

        if ($rootMiniStreamSize > 0) {
            $this->validateMiniFatAllocation($miniStreamSectors, $rootMiniStreamSize);
        }

        for ($sectorId = 0; $sectorId < $sectorCount; $sectorId++) {
            $fatEntry = $this->fat[$sectorId] ?? self::FREESECT;
            if ($fatEntry !== self::FREESECT && !isset($ownedSectors[$sectorId])) {
                throw new \RuntimeException('CFB FAT marks an unreferenced sector as allocated');
            }
        }
    }

    /**
     * @param array<int,string> $miniStreamSectors
     */
    private function validateMiniFatAllocation(array $miniStreamSectors, int $rootMiniStreamSize): void
    {
        $miniFat = $this->loadMiniFat();
        $miniStreamSectorCount = intdiv($rootMiniStreamSize + $this->miniSectorSize - 1, $this->miniSectorSize);

        foreach ($miniFat as $miniSectorId => $miniFatEntry) {
            $miniFatEntry = (int) $miniFatEntry;
            if ($miniSectorId >= $miniStreamSectorCount) {
                continue;
            }

            if (self::isRegularSector($miniFatEntry)) {
                if ($miniFatEntry >= $miniStreamSectorCount) {
                    throw new \RuntimeException('CFB MiniFAT entry points outside the root mini stream');
                }
            } elseif ($miniFatEntry !== self::FREESECT && $miniFatEntry !== self::ENDOFCHAIN) {
                throw new \RuntimeException('CFB MiniFAT entry contains a reserved sector marker');
            }

            if (!isset($miniStreamSectors[$miniSectorId]) && $miniFatEntry !== self::FREESECT) {
                throw new \RuntimeException('CFB MiniFAT marks an unreferenced mini-sector as allocated');
            }
        }
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function streamUsesMiniStream(array $entry): bool
    {
        $size = (int) ($entry['size'] ?? 0);
        if ($size === 0 || $size >= $this->miniStreamCutoff) {
            return false;
        }

        if (!self::isRegularSector($this->firstMiniFatSector) || $this->miniFatSectorCount <= 0) {
            $name = (string) ($entry['path'] ?? $entry['name'] ?? 'stream');
            throw new \RuntimeException('CFB stream below the mini stream cutoff requires MiniFAT metadata: ' . $name);
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private function loadMiniFat(): array
    {
        if ($this->miniFat !== null) {
            return $this->miniFat;
        }
        if (!self::isRegularSector($this->firstMiniFatSector) || $this->miniFatSectorCount <= 0) {
            throw new \RuntimeException('CFB file does not contain a MiniFAT');
        }

        $bytes = $this->readRegularSectorChain(
            $this->firstMiniFatSector,
            $this->miniFatSectorCount * $this->sectorSize,
            'MiniFAT'
        );
        $fat = [];
        for ($offset = 0, $length = strlen($bytes); $offset + 4 <= $length; $offset += 4) {
            $fat[] = self::u32($bytes, $offset);
        }

        $this->miniFat = $fat;

        return $fat;
    }

    private function loadMiniStream(): string
    {
        if ($this->miniStream !== null) {
            return $this->miniStream;
        }

        $root = null;
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 5) {
                $root = $entry;
                break;
            }
        }
        if ($root === null || $root['size'] === 0) {
            throw new \RuntimeException('CFB file does not contain a root mini stream');
        }

        $this->miniStream = $this->readRegularSectorChain($root['startSector'], $root['size'], 'Root Entry mini stream');

        return $this->miniStream;
    }

    /**
     * @return array{0:list<array<string,mixed>>,1:array<string,int>}
     */
    private static function parseDirectory(string $directoryBytes, int $majorVersion): array
    {
        $rawEntries = [];
        $root = null;
        $byName = [];
        for ($offset = 0, $length = strlen($directoryBytes); $offset + 128 <= $length; $offset += 128) {
            $directoryId = intdiv($offset, 128);
            $entryBytes = substr($directoryBytes, $offset, 128);
            $type = ord($entryBytes[66]);
            if ($type === 0) {
                self::validateUnallocatedDirectoryEntry($entryBytes);
                $rawEntries[$directoryId] = null;
                continue;
            }
            if (!in_array($type, [1, 2, 5], true)) {
                throw new \RuntimeException('CFB directory entry has an unsupported object type');
            }

            $nameLength = self::u16($entryBytes, 64);
            if ($nameLength < 2 || $nameLength > 64 || ($nameLength % 2) !== 0) {
                throw new \RuntimeException('CFB active directory entry has an invalid name length');
            }
            if (substr($entryBytes, $nameLength - 2, 2) !== "\0\0") {
                throw new \RuntimeException('CFB active directory entry name is missing its UTF-16LE terminator');
            }
            if ($nameLength < 64 && substr($entryBytes, $nameLength, 64 - $nameLength) !== str_repeat("\0", 64 - $nameLength)) {
                throw new \RuntimeException('CFB active directory entry name padding must be zero');
            }
            $nameBytes = substr($entryBytes, 0, $nameLength - 2);
            $name = self::decodeUtf16Le($nameBytes);
            if ($name === '') {
                throw new \RuntimeException('CFB active directory entry name must not be empty');
            }
            if (strpos($name, "\0") !== false) {
                throw new \RuntimeException('CFB active directory entry name contains an embedded null');
            }
            if (strpbrk($name, '/\\:!') !== false) {
                throw new \RuntimeException('CFB directory entry name contains an illegal character: ' . $name);
            }
            $clsid = self::readClsid($entryBytes, 80);
            $stateBits = self::u32($entryBytes, 96);
            $streamSizeLow = self::u32($entryBytes, 120);
            $streamSizeHigh = self::u32($entryBytes, 124);
            $entry = [
                'name' => $name,
                'path' => $name,
                'type' => $type,
                'colorFlag' => ord($entryBytes[67]),
                'nameLength' => $nameLength,
                'startSector' => self::u32($entryBytes, 116),
                'size' => $majorVersion === 3 ? $streamSizeLow : self::u64($entryBytes, 120),
                'leftSiblingId' => self::u32($entryBytes, 68),
                'rightSiblingId' => self::u32($entryBytes, 72),
                'childId' => self::u32($entryBytes, 76),
                'clsid' => $clsid,
                'stateBits' => $stateBits,
                'createdAt' => self::readFiletime($entryBytes, 100),
                'modifiedAt' => self::readFiletime($entryBytes, 108),
                'hasCreationTimeBytes' => substr($entryBytes, 100, 8) !== str_repeat("\0", 8),
                'hasModificationTimeBytes' => substr($entryBytes, 108, 8) !== str_repeat("\0", 8),
                'directoryId' => $directoryId,
            ];
            if ($majorVersion === 3 && $streamSizeHigh !== 0) {
                $entry['ignoredStreamSizeHighDword'] = $streamSizeHigh;
            }
            self::validateDirectoryEntryObjectFields($entry);
            unset($entry['hasCreationTimeBytes'], $entry['hasModificationTimeBytes']);
            if ($clsid === null) {
                unset($entry['clsid']);
            }
            if ($stateBits === 0) {
                unset($entry['stateBits']);
            }
            $rawEntries[$directoryId] = $entry;
            if ($type === 5 && $root === null) {
                $root = $entry;
            }
        }

        if ($root === null) {
            throw new \RuntimeException('CFB directory is missing the Root Entry storage');
        }
        $root = $rawEntries[0] ?? null;
        if ($root === null || $root['type'] !== 5) {
            throw new \RuntimeException('CFB directory must begin with the Root Entry storage');
        }
        if ($root['name'] !== 'Root Entry') {
            throw new \RuntimeException('CFB root storage name must be Root Entry');
        }
        // The root directory has no siblings, so its red-black color does not
        // participate in a tree. Only reject values outside the CFB enum.
        if (!in_array($root['colorFlag'], [0, 1], true)) {
            throw new \RuntimeException('CFB root storage entry has an invalid red-black color flag');
        }

        $root['path'] = '';
        $entries = [$root];
        $visited = [$root['directoryId'] => true];
        self::collectDirectoryTree($root['childId'], '', $rawEntries, $entries, $byName, $visited, null, null, false, true);
        foreach ($rawEntries as $directoryId => $entry) {
            if ($entry !== null && !isset($visited[$directoryId])) {
                throw new \RuntimeException('CFB directory contains an unreachable active entry: ' . $entry['name']);
            }
        }

        return [$entries, $byName];
    }

    private static function validateUnallocatedDirectoryEntry(string $entryBytes): void
    {
        $expected = str_repeat("\0", 128);
        $expected = substr_replace($expected, pack('V', self::FREESECT), 68, 4);
        $expected = substr_replace($expected, pack('V', self::FREESECT), 72, 4);
        $expected = substr_replace($expected, pack('V', self::FREESECT), 76, 4);
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function validateDirectoryEntryObjectFields(array $entry): void
    {
        $type = (int) $entry['type'];
        $name = (string) $entry['name'];
        $startSector = (int) $entry['startSector'];
        $size = (int) $entry['size'];
        if ($type === 2 && (int) $entry['childId'] !== self::FREESECT) {
            throw new \RuntimeException('CFB stream directory entry must not reference child entries: ' . $name);
        }
        if ($type === 2 && ($entry['clsid'] ?? null) !== null) {
            throw new \RuntimeException('CFB stream directory entry must not declare a storage CLSID: ' . $name);
        }
        if ($type === 2 && (int) ($entry['stateBits'] ?? 0) !== 0) {
            throw new \RuntimeException('CFB stream directory entry must not declare storage state bits: ' . $name);
        }
        if ($type === 2 && (($entry['hasCreationTimeBytes'] ?? false) === true || ($entry['hasModificationTimeBytes'] ?? false) === true)) {
            throw new \RuntimeException('CFB stream directory entry must not declare storage timestamps: ' . $name);
        }
        if ($type === 2 && $size === 0 && $startSector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB zero-length stream directory entry must use ENDOFCHAIN start sector: ' . $name);
        }
        if ($type === 1 && $size !== 0) {
            throw new \RuntimeException('CFB storage directory entry must not declare stream bytes: ' . $name);
        }
        // Some producer-written storages carry stale start sectors even though
        // storages do not own stream bytes. Ignore the start sector and keep
        // validating real stream chains for stream/root entries.
        if ($type === 5 && (int) ($entry['directoryId'] ?? -1) !== 0) {
            throw new \RuntimeException('CFB directory contains a duplicate Root Entry storage: ' . $name);
        }
        if ($type === 5 && ((int) $entry['leftSiblingId'] !== self::FREESECT || (int) $entry['rightSiblingId'] !== self::FREESECT)) {
            throw new \RuntimeException('CFB root directory entry must not reference sibling entries');
        }
        if ($type === 5 && ($entry['hasCreationTimeBytes'] ?? false) === true) {
            throw new \RuntimeException('CFB root directory entry must not declare a creation timestamp');
        }
        if ($type === 5 && $size === 0 && $startSector !== self::ENDOFCHAIN) {
            throw new \RuntimeException('CFB root directory entry must use ENDOFCHAIN start sector when the mini stream is empty');
        }
    }

    /**
     * @param array<int,array<string,mixed>|null> $rawEntries
     * @param list<array<string,mixed>> $entries
     * @param array<string,int> $byName
     * @param array<int,bool> $visited
     * @param array<string,mixed>|null $minEntry
     * @param array<string,mixed>|null $maxEntry
     */
    private static function collectDirectoryTree(
        int $nodeId,
        string $parentPath,
        array $rawEntries,
        array &$entries,
        array &$byName,
        array &$visited,
        ?array $minEntry,
        ?array $maxEntry,
        bool $parentIsRed,
        bool $treeRoot = false
    ): int {
        if ($nodeId === self::FREESECT) {
            return 1;
        }
        if (!self::isRegularSector($nodeId)) {
            throw new \RuntimeException('CFB directory tree contains an invalid stream ID');
        }
        if (!array_key_exists($nodeId, $rawEntries) || $rawEntries[$nodeId] === null) {
            throw new \RuntimeException('CFB directory tree points outside the directory');
        }
        if (isset($visited[$nodeId])) {
            throw new \RuntimeException('CFB directory tree contains a cycle');
        }

        $visited[$nodeId] = true;
        $entry = $rawEntries[$nodeId];
        if (!in_array($entry['colorFlag'], [0, 1], true)) {
            throw new \RuntimeException('CFB directory entry has an invalid red-black color flag: ' . $entry['name']);
        }
        if ($treeRoot && $entry['colorFlag'] === 0) {
            throw new \RuntimeException('CFB directory sibling tree root must be black: ' . $entry['name']);
        }
        if ($parentIsRed && $entry['colorFlag'] === 0) {
            throw new \RuntimeException('CFB directory sibling tree contains consecutive red nodes');
        }
        if ($minEntry !== null && self::compareDirectoryEntries($entry, $minEntry) <= 0) {
            throw new \RuntimeException('CFB directory sibling tree is not sorted by name: ' . $entry['name']);
        }
        if ($maxEntry !== null && self::compareDirectoryEntries($entry, $maxEntry) >= 0) {
            throw new \RuntimeException('CFB directory sibling tree is not sorted by name: ' . $entry['name']);
        }

        $entryIsRed = $entry['colorFlag'] === 0;
        $leftBlackHeight = self::collectDirectoryTree($entry['leftSiblingId'], $parentPath, $rawEntries, $entries, $byName, $visited, $minEntry, $entry, $entryIsRed, false);

        $entry['path'] = $parentPath === '' ? $entry['name'] : $parentPath . '/' . $entry['name'];
        $entryIndex = count($entries);
        $entries[] = $entry;
        if ($entry['type'] === 2) {
            $fullName = self::normalizeName($entry['path']);
            if (isset($byName[$fullName])) {
                throw new \RuntimeException('CFB directory contains a duplicate stream path: ' . $entry['path']);
            }
            $byName[$fullName] = $entryIndex;
            if ($parentPath === '') {
                $rootName = self::normalizeName($entry['name']);
                if ($rootName !== $fullName && isset($byName[$rootName])) {
                    throw new \RuntimeException('CFB directory contains a duplicate root stream name: ' . $entry['name']);
                }
                $byName[$rootName] = $entryIndex;
            }
        }
        if ($entry['type'] === 1) {
            self::collectDirectoryTree($entry['childId'], $entry['path'], $rawEntries, $entries, $byName, $visited, null, null, false, true);
        }

        $rightBlackHeight = self::collectDirectoryTree($entry['rightSiblingId'], $parentPath, $rawEntries, $entries, $byName, $visited, $entry, $maxEntry, $entryIsRed, false);
        return $leftBlackHeight + ($entryIsRed ? 0 : 1);
    }

    /**
     * @param array{name:string,nameLength:int} $left
     * @param array{name:string,nameLength:int} $right
     */
    private static function compareDirectoryEntries(array $left, array $right): int
    {
        if ($left['nameLength'] !== $right['nameLength']) {
            return $left['nameLength'] <=> $right['nameLength'];
        }

        $leftUnits = self::directoryNameSortUnits($left['name']);
        $rightUnits = self::directoryNameSortUnits($right['name']);
        $count = min(count($leftUnits), count($rightUnits));
        for ($index = 0; $index < $count; $index++) {
            if ($leftUnits[$index] !== $rightUnits[$index]) {
                return $leftUnits[$index] <=> $rightUnits[$index];
            }
        }

        return count($leftUnits) <=> count($rightUnits);
    }

    /**
     * @return list<int>
     */
    private static function directoryNameSortUnits(string $name): array
    {
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
        $bytes = iconv('UTF-8', 'UTF-16LE//IGNORE', $upper);
        if (!is_string($bytes)) {
            $bytes = '';
        }

        $units = [];
        for ($offset = 0, $length = strlen($bytes); $offset + 2 <= $length; $offset += 2) {
            $units[] = self::u16($bytes, $offset);
        }

        return $units;
    }

    private static function normalizeName(string $name): string
    {
        $name = str_replace('\\', '/', ltrim($name, '/\\'));
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($name, 'UTF-8');
        }

        return strtolower($name);
    }

    private static function isRegularSector(int $sector): bool
    {
        return $sector >= 0 && $sector <= self::MAXREGSECT;
    }

    /**
     * @param list<int> $fat
     * @param list<int> $fatSectorIds
     * @param list<int> $difatSectorIds
     */
    private static function validateFatEntryValues(array $fat, int $sectorCount, array $fatSectorIds, array $difatSectorIds): void
    {
        $fatSectorSet = array_fill_keys($fatSectorIds, true);
        $difatSectorSet = array_fill_keys($difatSectorIds, true);
        for ($sectorId = 0; $sectorId < $sectorCount; $sectorId++) {
            if (!array_key_exists($sectorId, $fat)) {
                throw new \RuntimeException('CFB FAT does not cover all physical sectors');
            }

            $value = (int) $fat[$sectorId];
            if (self::isRegularSector($value)) {
                if ($value >= $sectorCount) {
                    throw new \RuntimeException('CFB FAT entry points outside the file');
                }
                continue;
            }

            if ($value === self::FREESECT || $value === self::ENDOFCHAIN) {
                continue;
            }

            if ($value === self::FATSECT) {
                if (!isset($fatSectorSet[$sectorId])) {
                    throw new \RuntimeException('CFB FATSECT marker appears on a non-FAT sector');
                }
                continue;
            }

            if ($value === self::DIFSECT) {
                if (!isset($difatSectorSet[$sectorId])) {
                    throw new \RuntimeException('CFB DIFSECT marker appears on a non-DIFAT sector');
                }
                continue;
            }

            throw new \RuntimeException('CFB FAT entry contains a reserved sector marker');
        }
    }

    private static function sectorCount(string $bytes, int $sectorSize): int
    {
        return intdiv(max(0, strlen($bytes) - $sectorSize), $sectorSize);
    }

    private static function sectorBytes(string $bytes, int $sectorSize, int $sector): string
    {
        $offset = $sectorSize + ($sector * $sectorSize);
        if ($offset < $sectorSize || $offset + $sectorSize > strlen($bytes)) {
            throw new \RuntimeException('CFB sector points outside the file');
        }

        return substr($bytes, $offset, $sectorSize);
    }

    private static function decodeUtf16Le(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $decoded = @iconv('UTF-16LE', 'UTF-8', $bytes);
        if (!is_string($decoded)) {
            throw new \RuntimeException('CFB active directory entry name is not valid UTF-16LE');
        }

        return $decoded;
    }

    private static function readClsid(string $bytes, int $offset): ?string
    {
        if ($offset < 0 || $offset + 16 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of CFB directory CLSID data');
        }

        $raw = substr($bytes, $offset, 16);
        if ($raw === str_repeat("\0", 16)) {
            return null;
        }

        return sprintf(
            '%08x-%04x-%04x-%02x%02x-%s',
            self::u32($raw, 0),
            self::u16($raw, 4),
            self::u16($raw, 6),
            ord($raw[8]),
            ord($raw[9]),
            bin2hex(substr($raw, 10, 6))
        );
    }

    private static function u16(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of CFB data');
        }
        $values = unpack('vvalue', substr($bytes, $offset, 2));

        return (int) $values['value'];
    }

    private static function u32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of CFB data');
        }
        $values = unpack('Vvalue', substr($bytes, $offset, 4));

        return (int) $values['value'];
    }

    private static function u64(string $bytes, int $offset): int
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            throw new \RuntimeException('CFB stream size exceeds PHP integer range');
        }

        return ($high * 4294967296) + $low;
    }

    private static function readFiletime(string $bytes, int $offset): ?string
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($low === 0 && $high === 0) {
            return null;
        }
        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            throw new \RuntimeException('CFB directory FILETIME exceeds PHP integer range');
        }

        $ticks = ($high * 4294967296) + $low;
        $seconds = intdiv($ticks, 10000000) - 11644473600;
        $timestamp = (new \DateTimeImmutable('@' . $seconds))->setTimezone(new \DateTimeZone('UTC'));

        return $timestamp->format('Y-m-d\TH:i:s\Z');
    }
}
