<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * A bounded, package-wide OPC relationship index.
 *
 * Relationship consumers commonly need both one-off source lookups and a
 * complete inventory. Repeatedly resolving each source through
 * OpcRelationships::fromPackageBounded() rescans the entire ZIP directory
 * and rereads [Content_Types].xml for every relationship part. This index
 * establishes the package view once, then loads each selected .rels payload
 * at most once.
 */
final class OpcRelationshipInventory
{
    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';

    /**
     * @var array<string, list<array{relationshipPartName:string, sourcePartName:string, entryName:string, uncompressedSize:int}>>
     */
    private array $partsBySource = [];

    /** @var array<string, OpcRelationships> */
    private array $relationshipsBySource = [];

    /** @var array<string, \Throwable> */
    private array $errorsBySource = [];

    /** @var list<array{relationshipPartName:string, sourcePartName:string}>|null */
    private ?array $sourceParts = null;

    /** @var list<array{relationshipPart:string, sourcePart:?string, error:string}> */
    private array $parseErrors = [];

    private function __construct(
        private readonly ZipPackage $package,
        private readonly int $maxUncompressedBytes,
        private readonly ?OpcContentTypes $contentTypes,
        private readonly ?string $contentTypesEntryName,
        private readonly ?string $contentTypesParseError,
    ) {
    }

    public static function fromPackage(
        ZipPackage $package,
        int $maxUncompressedBytes,
        ?int $maxRelationshipParts = null,
        ?int $maxRelationshipBytes = null,
    ): self {
        if ($maxUncompressedBytes < 0) {
            throw new \InvalidArgumentException('OPC relationship read limit must not be negative');
        }
        if ($maxRelationshipParts !== null && $maxRelationshipParts < 0) {
            throw new \InvalidArgumentException('OPC relationship inventory part limit must not be negative');
        }
        if ($maxRelationshipBytes !== null && $maxRelationshipBytes < 0) {
            throw new \InvalidArgumentException('OPC relationship inventory byte limit must not be negative');
        }

        $relationshipParts = [];
        $contentTypesEntryName = null;
        $parseErrors = [];

        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            try {
                $relationshipPartName = OpcPackagePath::canonicalPartName($entry->name);
                if (strtolower($relationshipPartName) === '/[content_types].xml' && $contentTypesEntryName === null) {
                    $contentTypesEntryName = $entry->name;
                }

                if (!OpcRelationships::isRelationshipPartName($relationshipPartName)) {
                    continue;
                }

                $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
            } catch (\Throwable $exception) {
                if (self::looksLikeRelationshipPartName($entry->name)) {
                    $parseErrors[] = [
                        'relationshipPart' => $entry->name,
                        'sourcePart' => null,
                        'error' => $exception->getMessage(),
                    ];
                }
                continue;
            }

            $relationshipParts[] = [
                'relationshipPartName' => $relationshipPartName,
                'sourcePartName' => $sourcePartName,
                'entryName' => $entry->name,
                'uncompressedSize' => $entry->uncompressedSize,
            ];
        }

        $contentTypes = null;
        $contentTypesParseError = null;
        if ($contentTypesEntryName !== null) {
            try {
                $contentTypes = OpcContentTypes::fromXml(
                    $package->readBounded($contentTypesEntryName, $maxUncompressedBytes)
                );
            } catch (\Throwable $exception) {
                $contentTypesParseError = $exception->getMessage();
            }
        }

        $inventory = new self(
            $package,
            $maxUncompressedBytes,
            $contentTypes,
            $contentTypesEntryName,
            $contentTypesParseError,
        );
        $inventory->parseErrors = $parseErrors;
        $partCount = 0;
        $totalBytes = 0;

        foreach ($relationshipParts as $part) {
            if (
                $contentTypes instanceof OpcContentTypes
                && !self::contentTypeMatches(
                    $contentTypes->contentTypeForPart($part['relationshipPartName']),
                    self::RELATIONSHIP_PART_CONTENT_TYPE
                )
            ) {
                continue;
            }

            ++$partCount;
            if ($maxRelationshipParts !== null && $partCount > $maxRelationshipParts) {
                throw new \RuntimeException(
                    "OPC relationship inventory exceeds maximum part count {$maxRelationshipParts}"
                );
            }

            $totalBytes += $part['uncompressedSize'];
            if ($maxRelationshipBytes !== null && $totalBytes > $maxRelationshipBytes) {
                throw new \RuntimeException(
                    "OPC relationship inventory exceeds maximum uncompressed size {$maxRelationshipBytes} bytes"
                );
            }

            $sourceKey = self::sourceKey($part['sourcePartName']);
            $inventory->partsBySource[$sourceKey][] = $part;
        }

        foreach ($inventory->partsBySource as &$parts) {
            usort(
                $parts,
                static fn (array $left, array $right): int => $left['relationshipPartName'] <=> $right['relationshipPartName'],
            );
        }
        unset($parts);

        return $inventory;
    }

    public function hasContentTypes(): bool
    {
        return $this->contentTypesEntryName !== null;
    }

    public function contentTypes(): ?OpcContentTypes
    {
        return $this->contentTypes;
    }

    public function contentTypesParseError(): ?string
    {
        return $this->contentTypesParseError;
    }

    /**
     * @return list<array{relationshipPart:string, sourcePart:?string, error:string}>
     */
    public function parseErrors(): array
    {
        return $this->parseErrors;
    }

    public function hasRelationshipsForSource(string $sourcePartName = '/'): bool
    {
        return isset($this->partsBySource[self::sourceKey($sourcePartName)]);
    }

    public function relationshipsForSource(string $sourcePartName = '/'): OpcRelationships
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $sourceKey = self::sourceKey($sourcePartName);
        if (isset($this->relationshipsBySource[$sourceKey])) {
            return $this->relationshipsBySource[$sourceKey];
        }
        if (isset($this->errorsBySource[$sourceKey])) {
            throw $this->errorsBySource[$sourceKey];
        }

        $parts = $this->partsBySource[$sourceKey] ?? [];
        if ($parts === []) {
            throw new \RuntimeException('OPC relationship part not found: ' . OpcRelationships::relationshipPartNameForSource($sourcePartName));
        }
        if (count($parts) > 1) {
            throw new \RuntimeException(
                'Duplicate OPC relationship parts for source: '
                . implode(', ', array_column($parts, 'relationshipPartName'))
            );
        }

        $part = $parts[0];
        try {
            $relationships = OpcRelationships::fromXml(
                $this->package->readBounded($part['entryName'], $this->maxUncompressedBytes),
                $part['sourcePartName'],
            );
            $this->relationshipsBySource[$sourceKey] = $relationships;

            return $relationships;
        } catch (\Throwable $exception) {
            $this->errorsBySource[$sourceKey] = $exception;
            throw $exception;
        }
    }

    /**
     * @return list<array{relationshipPartName:string, sourcePartName:string}>
     */
    public function sourceParts(): array
    {
        if ($this->sourceParts !== null) {
            return $this->sourceParts;
        }

        $sources = [];
        foreach ($this->partsBySource as $parts) {
            if ($parts === []) {
                continue;
            }
            $sources[] = [
                'relationshipPartName' => $parts[0]['relationshipPartName'],
                'sourcePartName' => $parts[0]['sourcePartName'],
            ];
        }

        usort(
            $sources,
            static fn (array $left, array $right): int => $left['relationshipPartName'] <=> $right['relationshipPartName'],
        );

        $this->sourceParts = $sources;

        return $this->sourceParts;
    }

    private static function sourceKey(string $sourcePartName): string
    {
        return strtolower(OpcPackagePath::canonicalPartName($sourcePartName, true));
    }

    private static function looksLikeRelationshipPartName(string $name): bool
    {
        $name = strtolower($name);

        return str_ends_with($name, '.rels') || str_contains($name, '/_rels/');
    }

    private static function contentTypeMatches(?string $actual, string $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        return self::contentTypeComparisonKey($actual) === self::contentTypeComparisonKey($expected);
    }

    private static function contentTypeComparisonKey(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }
}
