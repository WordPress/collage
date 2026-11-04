<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Packed source provenance for regular CSV/TSV table cells.
 *
 * The conventional AST stores about twenty associative-array entries on every
 * cell. Delimited tables are rectangular, so the row and column portions are
 * derivable and the remaining numeric locations fit in six uint32 streams.
 */
final class CompactDelimitedCellStore
{
    private const RETAINED = 1;
    private const QUOTED = 2;
    private const QUOTE_CLOSED = 4;
    private const MULTILINE = 8;
    private const SOURCE_TEXT = 16;
    private const NULL_UINT32 = 0xffffffff;
    private const LOCATION_FIELD_COUNT = 6;

    private string $rowSourceRows = '';
    private string $rowOriginalColumnCounts = '';
    private string $flags = '';
    private string $locations = '';

    /** @var array<int, string> */
    private array $sourceTexts = [];

    private int $cellCount = 0;

    public function __construct(private readonly int $columnCount)
    {
        if ($columnCount <= 0) {
            throw new \InvalidArgumentException('Compact delimited cell storage requires at least one column.');
        }
    }

    /**
     * @param array{sourceRow:int|null, sourceRowNumber:int|null} $rowSource
     */
    public function beginRow(int $originalColumnCount, array $rowSource): void
    {
        $sourceRow = $rowSource['sourceRow'] ?? null;
        $this->rowSourceRows .= $this->packUint32(is_int($sourceRow) ? $sourceRow : self::NULL_UINT32);
        $this->rowOriginalColumnCounts .= $this->packUint32($originalColumnCount);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function appendCell(?array $metadata, string $sourceText, string $text): int
    {
        $index = $this->cellCount++;
        $retained = $metadata !== null;
        $flags = $retained ? self::RETAINED : 0;

        if ($retained && (bool) ($metadata['sourceQuoted'] ?? false)) {
            $flags |= self::QUOTED;
        }
        if ($retained && (bool) ($metadata['sourceQuoteClosed'] ?? true)) {
            $flags |= self::QUOTE_CLOSED;
        }
        if ($retained && (bool) ($metadata['sourceMultiline'] ?? false)) {
            $flags |= self::MULTILINE;
        }
        if ($sourceText !== $text) {
            $flags |= self::SOURCE_TEXT;
            $this->sourceTexts[$index] = $sourceText;
        }

        $this->flags .= chr($flags);
        $this->locations .= pack(
            'V6',
            $retained ? (int) ($metadata['sourceStartOffset'] ?? 0) : 0,
            $retained ? (int) ($metadata['sourceEndOffset'] ?? 0) : 0,
            $retained ? (int) ($metadata['sourceStartLine'] ?? 0) : 0,
            $retained ? (int) ($metadata['sourceStartByteColumn'] ?? 0) : 0,
            $retained ? (int) ($metadata['sourceEndLine'] ?? 0) : 0,
            $retained ? (int) ($metadata['sourceEndByteColumn'] ?? 0) : 0,
        );

        return $index;
    }

    public function hasAttribute(int $index, string $name): bool
    {
        if (in_array($name, ['sourceFieldStatus', 'sourceColumn', 'originalColumnCount', 'repairedColumnCount', 'rowRepair'], true)) {
            return true;
        }

        $retained = $this->retained($index);
        if ($name === 'sourceText') {
            return ($this->flagsAt($index) & self::SOURCE_TEXT) !== 0;
        }

        if (in_array($name, ['sourceRow', 'sourceRowNumber'], true)) {
            return $this->sourceRow($index) !== null;
        }

        return $retained && in_array($name, [
            'sourceField',
            'sourceFieldNumber',
            'sourceStartOffset',
            'sourceEndOffset',
            'sourceByteRange',
            'sourceByteLength',
            'sourceStartLine',
            'sourceStartLineNumber',
            'sourceStartByteColumn',
            'sourceStartByteColumnNumber',
            'sourceEndLine',
            'sourceEndLineNumber',
            'sourceEndByteColumn',
            'sourceEndByteColumnNumber',
            'sourceLocationUnit',
            'sourceEndOffsetPolicy',
            'sourceQuoted',
            'sourceQuoteClosed',
            'sourceMultiline',
        ], true);
    }

    public function attribute(int $index, string $name): mixed
    {
        $column = $index % $this->columnCount;
        $row = intdiv($index, $this->columnCount);
        $sourceRow = $this->sourceRow($index);
        $retained = $this->retained($index);

        return match ($name) {
            'sourceFieldStatus' => $retained ? 'retained' : 'padded',
            'sourceColumn' => $column,
            'originalColumnCount' => $this->rowOriginalColumnCount($row),
            'repairedColumnCount' => $this->columnCount,
            'rowRepair' => $this->rowRepair($row),
            'sourceRow' => $sourceRow,
            'sourceRowNumber' => $sourceRow === null ? null : $sourceRow + 1,
            'sourceField' => $retained ? $column : null,
            'sourceFieldNumber' => $retained ? $column + 1 : null,
            'sourceStartOffset' => $retained ? $this->locationAt($index, 0) : null,
            'sourceEndOffset' => $retained ? $this->locationAt($index, 1) : null,
            'sourceByteRange' => $retained ? [$this->locationAt($index, 0), $this->locationAt($index, 1)] : null,
            'sourceByteLength' => $retained ? $this->locationAt($index, 1) - $this->locationAt($index, 0) : null,
            'sourceStartLine' => $retained ? $this->locationAt($index, 2) : null,
            'sourceStartLineNumber' => $retained ? $this->locationAt($index, 2) + 1 : null,
            'sourceStartByteColumn' => $retained ? $this->locationAt($index, 3) : null,
            'sourceStartByteColumnNumber' => $retained ? $this->locationAt($index, 3) + 1 : null,
            'sourceEndLine' => $retained ? $this->locationAt($index, 4) : null,
            'sourceEndLineNumber' => $retained ? $this->locationAt($index, 4) + 1 : null,
            'sourceEndByteColumn' => $retained ? $this->locationAt($index, 5) : null,
            'sourceEndByteColumnNumber' => $retained ? $this->locationAt($index, 5) + 1 : null,
            'sourceLocationUnit' => $retained ? 'byte-column' : null,
            'sourceEndOffsetPolicy' => $retained ? 'exclusive' : null,
            'sourceQuoted' => $retained ? (($this->flagsAt($index) & self::QUOTED) !== 0) : null,
            'sourceQuoteClosed' => $retained ? (($this->flagsAt($index) & self::QUOTE_CLOSED) !== 0) : null,
            'sourceMultiline' => $retained ? (($this->flagsAt($index) & self::MULTILINE) !== 0) : null,
            'sourceText' => $this->sourceTexts[$index] ?? null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(int $index): array
    {
        $attrs = [
            'sourceColumn' => $this->attribute($index, 'sourceColumn'),
            'originalColumnCount' => $this->attribute($index, 'originalColumnCount'),
            'repairedColumnCount' => $this->attribute($index, 'repairedColumnCount'),
            'rowRepair' => $this->attribute($index, 'rowRepair'),
            'sourceFieldStatus' => $this->attribute($index, 'sourceFieldStatus'),
        ];

        foreach ([
            'sourceRow',
            'sourceRowNumber',
            'sourceField',
            'sourceFieldNumber',
            'sourceStartOffset',
            'sourceEndOffset',
            'sourceByteRange',
            'sourceByteLength',
            'sourceStartLine',
            'sourceStartLineNumber',
            'sourceStartByteColumn',
            'sourceStartByteColumnNumber',
            'sourceEndLine',
            'sourceEndLineNumber',
            'sourceEndByteColumn',
            'sourceEndByteColumnNumber',
            'sourceLocationUnit',
            'sourceEndOffsetPolicy',
            'sourceQuoted',
            'sourceQuoteClosed',
            'sourceMultiline',
            'sourceText',
        ] as $name) {
            if ($this->hasAttribute($index, $name)) {
                $attrs[$name] = $this->attribute($index, $name);
            }
        }

        return $attrs;
    }

    private function retained(int $index): bool
    {
        return ($this->flagsAt($index) & self::RETAINED) !== 0;
    }

    private function flagsAt(int $index): int
    {
        return isset($this->flags[$index]) ? ord($this->flags[$index]) : 0;
    }

    private function sourceRow(int $index): ?int
    {
        $row = intdiv($index, $this->columnCount);
        $value = $this->uint32At($this->rowSourceRows, $row);

        return $value === self::NULL_UINT32 ? null : $value;
    }

    private function rowOriginalColumnCount(int $row): int
    {
        return $this->uint32At($this->rowOriginalColumnCounts, $row);
    }

    private function rowRepair(int $row): string
    {
        $original = $this->rowOriginalColumnCount($row);

        return $original < $this->columnCount
            ? 'padded'
            : ($original > $this->columnCount ? 'truncated' : 'unchanged');
    }

    private function locationAt(int $index, int $field): int
    {
        return $this->uint32At($this->locations, ($index * self::LOCATION_FIELD_COUNT) + $field);
    }

    private function uint32At(string $packed, int $index): int
    {
        $offset = $index * 4;
        if ($offset < 0 || $offset + 4 > strlen($packed)) {
            return 0;
        }

        $value = unpack('Vvalue', $packed, $offset);

        return is_array($value) ? (int) ($value['value'] ?? 0) : 0;
    }

    private function packUint32(int $value): string
    {
        return pack('V', max(0, min(self::NULL_UINT32, $value)));
    }
}
