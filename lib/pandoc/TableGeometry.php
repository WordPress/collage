<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TableGeometry
{
    private const WIDTH_EPSILON = 0.000001;
    private const SOURCE_HTML_HEADER_SCOPES = ['col', 'row', 'colgroup', 'rowgroup'];
    private const SOURCE_HTML_HEADER_SCOPE_VALUES = ['auto', 'col', 'row', 'colgroup', 'rowgroup'];

    public static function columnCount(AstNode $table): int
    {
        $columnCount = max(
            self::tableAttributeColumnCount($table->attr('alignments', [])),
            self::tableAttributeColumnCount($table->attr('widths', [])),
            self::tableAttributeColumnCount($table->attr('columnSpecs', []))
        );

        foreach ($table->children as $section) {
            if ($section->type === 'table_body') {
                $columnCount = max($columnCount, self::columnCountForRows(self::bodyRows($section)));
                continue;
            }

            if ($section->type === 'table_row') {
                $columnCount = max($columnCount, self::columnCountForRows([$section]));
                continue;
            }

            if ($section->type !== 'table_head' && $section->type !== 'table_foot') {
                continue;
            }

            $columnCount = max($columnCount, self::columnCountForRows(self::sectionRows($section)));
        }

        return $columnCount;
    }

    /**
     * @param list<AstNode> $rows
     */
    public static function columnCountForRows(array $rows): int
    {
        $rows = array_values(array_filter(
            $rows,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
        $rowCount = count($rows);
        $columnCount = 0;
        $activeRowspans = [];

        foreach ($rows as $rowIndex => $row) {
            $previousActiveColumns = self::activeColumns($activeRowspans);
            $consumedActiveColumns = [];
            $column = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                self::skipCoveredColumns($activeRowspans, $column, $consumedActiveColumns);

                $colspan = self::cellColspan($cell);
                $rowspan = self::cellRowspanForRows($cell, $rowIndex, $rowCount);
                if ($rowspan > 1) {
                    self::activateRowspan($activeRowspans, $column, $colspan, $rowspan);
                }

                $column += $colspan;
                $columnCount = max($columnCount, $column);
            }

            self::consumeUnusedActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
            $columnCount = max($columnCount, $column);
        }

        return $columnCount;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int,sourceRow:int,sourceRowspan:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>}>}>
     */
    public static function layoutRows(array $rows, int $columnCount): array
    {
        $rows = array_values(array_filter(
            $rows,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
        $rowCount = count($rows);
        $columnCount = max(0, $columnCount);
        if ($columnCount === 0) {
            $emptyRows = [];
            foreach ($rows as $row) {
                $emptyRows[] = [
                    'row' => $row,
                    'cells' => [],
                ];
            }

            return $emptyRows;
        }

        $layoutRows = [];
        $activeRowspans = [];

        foreach ($rows as $rowIndex => $row) {
            $previousActiveColumns = self::activeColumns($activeRowspans);
            $consumedActiveColumns = [];
            $layoutCells = [];
            $column = 0;
            $sourceCell = 0;
            $sourceColumn = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                $cellSourceCell = $sourceCell;
                $cellSourceColumn = $sourceColumn;
                $rawColspan = self::cellColspan($cell);
                self::skipCoveredColumns($activeRowspans, $column, $consumedActiveColumns);
                if ($column >= $columnCount) {
                    break;
                }

                $colspan = min($rawColspan, $columnCount - $column);
                $rowspanToEnd = self::cellRowspanToEnd($cell);
                $sourceRowspan = max(1, self::cellRowspanForRows($cell, $rowIndex, $rowCount));
                $sourceRowEnd = $rowIndex + $sourceRowspan;
                $rowspan = min($sourceRowspan, max(1, $rowCount - $rowIndex));
                $layoutCell = [
                    'node' => $cell,
                    'column' => $column,
                    'colspan' => $colspan,
                    'rowspan' => $rowspan,
                    'rowspanToEnd' => $rowspanToEnd,
                    'sourceCell' => $cellSourceCell,
                    'sourceColumn' => $cellSourceColumn,
                    'sourceRow' => $rowIndex,
                    'sourceRowspan' => $sourceRowspan,
                    'sourceRowEnd' => $sourceRowEnd,
                    'sourceRowRange' => [$rowIndex, $sourceRowEnd],
                    'sourceRows' => self::integerRange($rowIndex, $sourceRowEnd),
                ];
                if ($rowspanToEnd) {
                    $layoutCell['sourceRowspanAttribute'] = 0;
                    $layoutCell['sourceRowspanMode'] = 'to-section-end';
                }
                $layoutCells[] = $layoutCell;

                if ($rowspan > 1) {
                    self::activateRowspan($activeRowspans, $column, $colspan, $rowspan);
                }

                $column += $colspan;
                $sourceColumn += $rawColspan;
                $sourceCell++;
            }

            self::consumeUnusedActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
            $layoutRows[] = [
                'row' => $row,
                'cells' => $layoutCells,
            ];
        }

        return $layoutRows;
    }

    /**
     * @return list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }>
     */
    public static function sectionGrids(AstNode $table): array
    {
        $columnCount = self::columnCount($table);
        $sectionGrids = [];
        $globalRowStart = 0;

        foreach (self::sectionRowGroups($table, $columnCount) as $group) {
            $rowCount = count($group['rowEntries']);
            $globalRowEnd = $globalRowStart + $rowCount;
            $sectionGrids[] = [
                'section' => $group['section'],
                'node' => $group['node'],
                'columnCount' => $columnCount,
                'globalRowStart' => $globalRowStart,
                'globalRowEnd' => $globalRowEnd,
                'rowRange' => [$globalRowStart, $globalRowEnd],
                'rowEntries' => $group['rowEntries'],
                'rows' => self::sectionGridRowsWithGlobalCoordinates(
                    self::sectionGridForEntries($group['rowEntries'], $columnCount),
                    $globalRowStart
                ),
            ];
            $globalRowStart = $globalRowEnd;
        }

        return $sectionGrids;
    }

    /**
     * @return list<array{
     *     section:string,
     *     rows:list<AstNode>,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     * }>
     */
    public static function sectionRowEntryGroups(AstNode $table, ?int $columnCount = null): array
    {
        return self::sectionRowGroups($table, $columnCount ?? self::columnCount($table));
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<array<string, mixed>>>
     */
    public static function sectionGrid(array $rows, int $columnCount): array
    {
        $layoutRows = self::layoutRows($rows, $columnCount);
        $columnCount = max(0, $columnCount);
        $grid = [];

        foreach ($layoutRows as $rowIndex => $_layoutRow) {
            $grid[$rowIndex] = [];
            for ($column = 0; $column < $columnCount; $column++) {
                $grid[$rowIndex][$column] = self::missingGridSlot($rowIndex, $column);
            }
        }

        foreach ($layoutRows as $rowIndex => $layoutRow) {
            foreach ($layoutRow['cells'] as $cell) {
                $anchorColumn = $cell['column'];
                if ($anchorColumn >= $columnCount) {
                    continue;
                }

                $grid[$rowIndex][$anchorColumn] = self::cellGridSlot($rowIndex, $cell, count($layoutRows), $columnCount);
                for ($column = $anchorColumn + 1; $column < $anchorColumn + $cell['colspan'] && $column < $columnCount; $column++) {
                    $grid[$rowIndex][$column] = self::coveredGridSlot($rowIndex, $column, $rowIndex, $anchorColumn, 'colspan', $cell);
                }

                $rowLimit = min(count($layoutRows), $rowIndex + $cell['rowspan']);
                for ($coveredRow = $rowIndex + 1; $coveredRow < $rowLimit; $coveredRow++) {
                    for ($column = $anchorColumn; $column < $anchorColumn + $cell['colspan'] && $column < $columnCount; $column++) {
                        $covering = $column === $anchorColumn ? 'rowspan' : 'rowspan-colspan';
                        $grid[$coveredRow][$column] = self::coveredGridSlot(
                            $coveredRow,
                            $column,
                            $rowIndex,
                            $anchorColumn,
                            $covering,
                            $cell
                        );
                    }
                }
            }
        }

        return $grid;
    }

    /**
     * @return list<array{
     *     section:string,
     *     row:int,
     *     column:int,
     *     endColumn:int,
     *     rawEndColumn:int,
     *     columns:list<int>,
     *     sourceCell:int,
     *     sourceColumn:int,
     *     sourceEndColumn:int,
     *     sourceColumns:list<int>,
     *     sourceRow:int,
     *     sourceRowEnd:int,
     *     sourceRowRange:array{0:int,1:int},
     *     sourceRows:list<int>,
     *     sourceRowspan:int,
     *     visualShift:int,
     *     colspan:int,
     *     rawColspan:int,
     *     rowspan:int,
     *     rawRowspan:int,
     *     rowspanToEnd?:bool,
     *     alignment:string,
     *     verticalAlignment:string,
     *     columnAlignments:list<string>,
     *     widths:list<?float>,
     *     normalizedWidths:list<?float>,
     *     percentWidths:list<?float>,
     *     widthTotal:float,
     *     normalizedWidthTotal:float,
     *     percentWidthTotal:float,
     *     widthColumnCount:int,
     *     missingWidthColumnCount:int,
     *     hasCompleteWidths:bool,
     *     hasPartialWidths:bool,
     *     declaredColumns:list<bool>,
     *     columnSources?:list<array<string, mixed>|null>,
     *     occupiedSlots:list<array{row:int,column:int,covering:string}>,
     *     node:AstNode
     * }>
     */
    public static function cellCoverage(AstNode $table): array
    {
        $columnCount = self::columnCount($table);
        if ($columnCount <= 0) {
            return [];
        }

        $columnSpecs = self::columnSpecs($table, $columnCount);
        $coverage = [];
        $globalRowStart = 0;
        $tableDirection = self::sourceDirection($table);
        $tableLanguage = self::sourceLanguageRecord($table);
        $tableTranslate = self::sourceTranslateRecord($table);
        foreach (self::sectionRowGroups($table, $columnCount) as $group) {
            $sectionDirection = self::sourceDirection($group['node'] ?? null);
            $sectionLanguage = self::sourceLanguageRecord($group['node'] ?? null);
            $sectionTranslate = self::sourceTranslateRecord($group['node'] ?? null);
            $layoutRows = self::layoutRows($group['rows'], $columnCount);
            $sectionRowCount = count($layoutRows);
            foreach ($layoutRows as $rowIndex => $layoutRow) {
                $globalRow = $globalRowStart + $rowIndex;
                $rowEntry = $group['rowEntries'][$rowIndex] ?? [
                    'header' => false,
                    'rowHeadColumns' => 0,
                    'rowRole' => $group['section'],
                ];
                $rowDirection = self::sourceDirection($rowEntry['row'] ?? null);
                $rowLanguage = self::sourceLanguageRecord($rowEntry['row'] ?? null);
                $rowTranslate = self::sourceTranslateRecord($rowEntry['row'] ?? null);
                $headerRow = (bool) $rowEntry['header'];
                $rowHeadColumns = (int) $rowEntry['rowHeadColumns'];
                foreach ($layoutRow['cells'] as $cell) {
                    $columns = [];
                    $columnAlignments = [];
                    $widths = [];
                    $normalizedWidths = [];
                    $percentWidths = [];
                    $widthTotal = 0.0;
                    $normalizedWidthTotal = 0.0;
                    $percentWidthTotal = 0.0;
                    $widthColumnCount = 0;
                    $missingWidthColumnCount = 0;
                    $declaredColumns = [];
                    $columnSources = [];
                    $hasColumnSources = false;
                    for ($column = $cell['column']; $column < $cell['column'] + $cell['colspan'] && $column < $columnCount; $column++) {
                        $spec = $columnSpecs[$column] ?? [
                            'alignment' => 'default',
                            'width' => null,
                            'normalizedWidth' => null,
                            'percentWidth' => null,
                            'declared' => false,
                        ];
                        $width = isset($spec['width']) && is_numeric($spec['width']) ? (float) $spec['width'] : null;
                        $normalizedWidth = isset($spec['normalizedWidth']) && is_numeric($spec['normalizedWidth']) ? (float) $spec['normalizedWidth'] : null;
                        $percentWidth = isset($spec['percentWidth']) && is_numeric($spec['percentWidth']) ? (float) $spec['percentWidth'] : null;
                        $columns[] = $column;
                        $columnAlignments[] = (string) $spec['alignment'];
                        $widths[] = $width;
                        $normalizedWidths[] = $normalizedWidth;
                        $percentWidths[] = $percentWidth;
                        if ($width !== null) {
                            $widthTotal += $width;
                            $widthColumnCount++;
                        } else {
                            $missingWidthColumnCount++;
                        }
                        if ($normalizedWidth !== null) {
                            $normalizedWidthTotal += $normalizedWidth;
                        }
                        if ($percentWidth !== null) {
                            $percentWidthTotal += $percentWidth;
                        }
                        $declaredColumns[] = (bool) $spec['declared'];
                        $source = isset($spec['source']) && is_array($spec['source']) ? $spec['source'] : null;
                        $columnSources[] = $source;
                        $hasColumnSources = $hasColumnSources || $source !== null;
                    }

                    $rawColspan = self::cellColspan($cell['node']);
                    $rawRowspan = self::cellRowspanForRows($cell['node'], $rowIndex, $sectionRowCount);
                    $sourceEndColumn = $cell['sourceColumn'] + $rawColspan;
                    $sourceRow = (int) ($cell['sourceRow'] ?? $rowIndex);
                    $sourceRowspan = max(1, (int) ($cell['sourceRowspan'] ?? $rawRowspan));
                    $sourceRowEnd = $sourceRow + $sourceRowspan;
                    $cellDirection = self::sourceDirection($cell['node']);
                    [$direction, $directionSource] = self::effectiveDirection([
                        'table' => $tableDirection,
                        'section' => $sectionDirection,
                        'row' => $rowDirection,
                        'cell' => $cellDirection,
                    ]);
                    $cellLanguage = self::sourceLanguageRecord($cell['node']);
                    $cellTranslate = self::sourceTranslateRecord($cell['node']);
                    [$language, $languageSource, $languageAttribute] = self::effectiveLocalizationValue([
                        'table' => $tableLanguage,
                        'section' => $sectionLanguage,
                        'row' => $rowLanguage,
                        'cell' => $cellLanguage,
                    ], 'language', 'attribute');
                    [$translate, $translateSource, $translateAttribute] = self::effectiveLocalizationValue([
                        'table' => $tableTranslate,
                        'section' => $sectionTranslate,
                        'row' => $rowTranslate,
                        'cell' => $cellTranslate,
                    ], 'translate', 'translateAttribute');
                    $record = [
                        'section' => $group['section'],
                        'row' => $rowIndex,
                        'globalRow' => $globalRow,
                        'globalRowEnd' => $globalRow + $cell['rowspan'],
                        'globalRowRange' => [$globalRow, $globalRow + $cell['rowspan']],
                        'globalRows' => self::integerRange($globalRow, $globalRow + $cell['rowspan']),
                        'column' => $cell['column'],
                        'endColumn' => $cell['column'] + $cell['colspan'],
                        'rawEndColumn' => $cell['column'] + $rawColspan,
                        'columns' => $columns,
                        'rowRole' => (string) $rowEntry['rowRole'],
                        'headerRow' => $headerRow,
                        'rowHeadColumns' => $rowHeadColumns,
                        'headerCell' => self::isHeaderCell($headerRow, $rowHeadColumns, $cell['column'], $cell['node']),
                        'sourceCell' => $cell['sourceCell'],
                        'sourceColumn' => $cell['sourceColumn'],
                        'sourceEndColumn' => $sourceEndColumn,
                        'sourceColumns' => self::sourceColumns($cell['sourceColumn'], $rawColspan),
                        'sourceRow' => $sourceRow,
                        'sourceRowEnd' => $sourceRowEnd,
                        'sourceRowRange' => [$sourceRow, $sourceRowEnd],
                        'sourceRows' => self::integerRange($sourceRow, $sourceRowEnd),
                        'sourceRowspan' => $sourceRowspan,
                        'visualShift' => $cell['column'] - $cell['sourceColumn'],
                        'colspan' => $cell['colspan'],
                        'rawColspan' => $rawColspan,
                        'rowspan' => $cell['rowspan'],
                        'rawRowspan' => $rawRowspan,
                        'alignment' => self::cellAlignment($table, $cell['column'], $cell['node']),
                        'verticalAlignment' => self::cellVerticalAlignment($cell['node']),
                        'columnAlignments' => $columnAlignments,
                        'widths' => $widths,
                        'normalizedWidths' => $normalizedWidths,
                        'percentWidths' => $percentWidths,
                        'widthTotal' => self::roundWidth($widthTotal),
                        'normalizedWidthTotal' => self::roundWidth($normalizedWidthTotal),
                        'percentWidthTotal' => self::roundWidth($percentWidthTotal),
                        'widthColumnCount' => $widthColumnCount,
                        'missingWidthColumnCount' => $missingWidthColumnCount,
                        'hasCompleteWidths' => $columns !== [] && $missingWidthColumnCount === 0,
                        'hasPartialWidths' => $widthColumnCount > 0 && $missingWidthColumnCount > 0,
                        'declaredColumns' => $declaredColumns,
                        'occupiedSlots' => self::occupiedSlotRecords(
                            $rowIndex,
                            $cell['column'],
                            $cell['colspan'],
                            $cell['rowspan'],
                            $sectionRowCount,
                            $columnCount
                        ),
                        'node' => $cell['node'],
                    ];
                    if (($cell['rowspanToEnd'] ?? false) === true) {
                        $record['rowspanToEnd'] = true;
                        $record['sourceRowspanAttribute'] = 0;
                        $record['sourceRowspanMode'] = 'to-section-end';
                    }
                    if ($direction !== '') {
                        $record['direction'] = $direction;
                        $record['directionSource'] = $directionSource;
                        if ($cellDirection !== '') {
                            $record['sourceDirection'] = $cellDirection;
                        }
                        if ($rowDirection !== '') {
                            $record['rowDirection'] = $rowDirection;
                        }
                        if ($sectionDirection !== '') {
                            $record['sectionDirection'] = $sectionDirection;
                        }
                        if ($tableDirection !== '') {
                            $record['tableDirection'] = $tableDirection;
                        }
                    }
                    if ($language !== '') {
                        $record['language'] = $language;
                        $record['languageSource'] = $languageSource;
                        $record['languageAttribute'] = $languageAttribute;
                        foreach ([
                            'sourceLanguage' => $cellLanguage,
                            'rowLanguage' => $rowLanguage,
                            'sectionLanguage' => $sectionLanguage,
                            'tableLanguage' => $tableLanguage,
                        ] as $key => $languageRecord) {
                            $value = (string) ($languageRecord['language'] ?? '');
                            if ($value !== '') {
                                $record[$key] = $value;
                                $record[$key . 'Attribute'] = (string) ($languageRecord['attribute'] ?? '');
                            }
                        }
                    }
                    if ($translate !== '') {
                        $record['translate'] = $translate;
                        $record['translateSource'] = $translateSource;
                        $record['translateAttribute'] = $translateAttribute;
                        foreach ([
                            'sourceTranslate' => $cellTranslate,
                            'rowTranslate' => $rowTranslate,
                            'sectionTranslate' => $sectionTranslate,
                            'tableTranslate' => $tableTranslate,
                        ] as $key => $translateRecord) {
                            $value = (string) ($translateRecord['translate'] ?? '');
                            if ($value !== '') {
                                $record[$key] = $value;
                                $record[$key . 'Attribute'] = (string) ($translateRecord['translateAttribute'] ?? '');
                            }
                        }
                    }
                    if ($hasColumnSources) {
                        $record['columnSources'] = $columnSources;
                    }

                    $coverage[] = $record;
                }
            }
            $globalRowStart += $sectionRowCount;
        }

        return $coverage;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function accessibilityAttributes(AstNode $table, string $idPrefix = 'pandoc-table'): array
    {
        $idPrefix = self::normalizeHtmlId($idPrefix);
        $sectionGrids = self::sectionGrids($table);
        $columnGroups = self::columnGroups($table);
        $headers = [];
        $attributes = [];

        foreach ($sectionGrids as $sectionGrid) {
            $section = (string) $sectionGrid['section'];
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell' || ($slot['headerCell'] ?? false) !== true) {
                        continue;
                    }

                    $key = self::accessibilityKey(
                        $section,
                        (int) $rowIndex,
                        (int) ($slot['sourceCell'] ?? 0),
                        (int) ($slot['sourceColumn'] ?? 0)
                    );
                    $node = $slot['node'] ?? null;
                    $sourceId = self::cellSourceHtmlId($node);
                    $id = $sourceId !== '' ? $sourceId : $idPrefix . '-' . self::normalizeHtmlId($section)
                        . '-r' . ((int) $rowIndex + 1)
                        . 'c' . ((int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0) + 1);
                    $sourceScope = self::cellSourceHtmlScope($node);
                    $scope = $sourceScope === '' ? self::headerScope($slot) : $sourceScope;
                    $sourceHeaders = self::cellSourceHtmlHeaders($node);
                    $sourceColumnGroup = $sourceScope === 'colgroup'
                        ? self::columnGroupForSlot($slot, $columnGroups)
                        : [];
                    $columns = self::accessibilityHeaderColumns($slot, $sourceScope, $sourceColumnGroup);

                    $record = [
                        'key' => $key,
                        'id' => $id,
                        'section' => $section,
                        'row' => (int) $rowIndex,
                        'columns' => $columns,
                        'rowspan' => max(1, (int) ($slot['rowspan'] ?? 1)),
                        'scope' => $scope,
                    ];
                    if ($sourceScope !== '') {
                        $record['sourceScope'] = $sourceScope;
                    }
                    if ($sourceColumnGroup !== []) {
                        $record['sourceColumnGroup'] = $sourceColumnGroup;
                    }
                    $headers[] = $record;
                    $attributes[$key] = [
                        'id' => $id,
                        'scope' => $scope,
                        'headers' => $sourceHeaders,
                        'columns' => $columns,
                    ];
                    if ($sourceColumnGroup !== []) {
                        $attributes[$key]['sourceColumnGroup'] = $sourceColumnGroup;
                    }
                }
            }
        }

        foreach ($sectionGrids as $sectionGrid) {
            $section = (string) $sectionGrid['section'];
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell' || ($slot['headerCell'] ?? false) === true) {
                        continue;
                    }

                    $columns = self::associationColumns($slot);

                    $headerIds = [];
                    foreach ($headers as $header) {
                        $scope = (string) $header['scope'];
                        $applies = false;
                        if (($scope === 'col' || $scope === 'colgroup') && self::columnsOverlap($columns, $header['columns'])) {
                            $applies = $header['section'] === 'head'
                                || ($header['section'] === $section && (int) $header['row'] <= (int) $rowIndex);
                        } elseif ($scope === 'row' && $header['section'] === $section) {
                            $applies = (int) $rowIndex === (int) $header['row'];
                        } elseif ($scope === 'rowgroup' && $header['section'] === $section) {
                            $headerRow = (int) $header['row'];
                            $applies = ($header['sourceScope'] ?? '') === 'rowgroup'
                                || ((int) $rowIndex >= $headerRow && (int) $rowIndex < $headerRow + (int) $header['rowspan']);
                        }

                        if ($applies) {
                            $headerIds[] = (string) $header['id'];
                        }
                    }

                    $key = self::accessibilityKey(
                        $section,
                        (int) $rowIndex,
                        (int) ($slot['sourceCell'] ?? 0),
                        (int) ($slot['sourceColumn'] ?? 0)
                    );
                    $sourceHeaders = self::cellSourceHtmlHeaders($slot['node'] ?? null);
                    $attributes[$key] = [
                        'headers' => $sourceHeaders === [] ? array_values(array_unique($headerIds)) : $sourceHeaders,
                    ];
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array{
     *     headerCells:list<array<string, mixed>>,
     *     dataCells:list<array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public static function headerAssociations(AstNode $table, string $idPrefix = 'pandoc-table'): array
    {
        $accessibility = self::accessibilityAttributes($table, $idPrefix);
        $headerCells = [];
        $dataCells = [];
        $headerScopes = [];
        $associationCount = 0;
        $associatedDataCellCount = 0;
        $sourceHeaderOverrideCount = 0;
        $headerAbbreviationCount = 0;
        $headerAxisCount = 0;
        $headerAxes = [];

        foreach (self::sectionGrids($table) as $sectionGrid) {
            $section = (string) $sectionGrid['section'];
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell') {
                        continue;
                    }

                    $key = self::accessibilityKey(
                        $section,
                        (int) $rowIndex,
                        (int) ($slot['sourceCell'] ?? 0),
                        (int) ($slot['sourceColumn'] ?? 0)
                    );
                    $attributes = $accessibility[$key] ?? [];
                    $record = self::headerAssociationCellRecord($section, (int) $rowIndex, $slot, $key);

                    if (($slot['headerCell'] ?? false) === true) {
                        $id = trim((string) ($attributes['id'] ?? ''));
                        if ($id !== '') {
                            $record['id'] = $id;
                        }

                        $accessibilityColumns = self::intList($attributes['columns'] ?? []);
                        if ($accessibilityColumns !== []) {
                            $record['columns'] = $accessibilityColumns;
                        }
                        if (is_array($attributes['sourceColumnGroup'] ?? null)) {
                            $record['sourceColumnGroup'] = $attributes['sourceColumnGroup'];
                        }

                        $scope = trim((string) ($attributes['scope'] ?? self::headerScope($slot)));
                        if ($scope !== '') {
                            $record['scope'] = $scope;
                            $headerScopes[] = $scope;
                        }

                        $abbr = self::cellSourceHtmlAbbr($slot['node'] ?? null);
                        if ($abbr !== '') {
                            $record['abbr'] = $abbr;
                            $headerAbbreviationCount++;
                        }

                        $axis = self::cellSourceHtmlAxis($slot['node'] ?? null);
                        if ($axis !== []) {
                            $record['axis'] = $axis;
                            $headerAxisCount++;
                            array_push($headerAxes, ...$axis);
                        }

                        $record['headers'] = self::stringList($attributes['headers'] ?? []);
                        $headerCells[] = $record;
                        continue;
                    }

                    $headers = self::stringList($attributes['headers'] ?? []);
                    $record['headers'] = $headers;
                    if ($headers !== []) {
                        $associatedDataCellCount++;
                        $associationCount += count($headers);
                    }

                    $sourceHeaders = self::cellSourceHtmlHeaders($slot['node'] ?? null);
                    if ($sourceHeaders !== []) {
                        $record['sourceHeaders'] = $sourceHeaders;
                        $sourceHeaderOverrideCount++;
                    }

                    $dataCells[] = $record;
                }
            }
        }

        $sourceHeaderReferenceSummary = self::attachSourceHeaderReferences($headerCells, $dataCells);

        return [
            'headerCells' => $headerCells,
            'dataCells' => $dataCells,
            'summary' => [
                'headerCellCount' => count($headerCells),
                'dataCellCount' => count($dataCells),
                'associatedDataCellCount' => $associatedDataCellCount,
                'unassociatedDataCellCount' => count($dataCells) - $associatedDataCellCount,
                'associationCount' => $associationCount,
                'headerScopes' => array_values(array_unique($headerScopes)),
                'sourceHeaderOverrideCount' => $sourceHeaderOverrideCount,
                'hasSourceHeaderOverrides' => $sourceHeaderOverrideCount > 0,
                'sourceHeaderReferencingCellCount' => $sourceHeaderReferenceSummary['referencingCellCount'],
                'sourceHeaderReferenceCount' => $sourceHeaderReferenceSummary['referenceCount'],
                'sourceHeaderResolvedReferenceCount' => $sourceHeaderReferenceSummary['resolvedReferenceCount'],
                'sourceHeaderUnresolvedReferenceCount' => $sourceHeaderReferenceSummary['unresolvedReferenceCount'],
                'hasUnresolvedSourceHeaderReferences' => $sourceHeaderReferenceSummary['unresolvedReferenceCount'] > 0,
                'unresolvedSourceHeaderReferences' => $sourceHeaderReferenceSummary['unresolvedReferences'],
                'duplicateSourceHeaderTokenCellCount' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokenCellCount'],
                'duplicateSourceHeaderTokenCount' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokenCount'],
                'hasDuplicateSourceHeaderTokens' => $sourceHeaderReferenceSummary['hasDuplicateSourceHeaderTokens'],
                'duplicateSourceHeaderTokens' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokens'],
                'sourceHeaderDuplicateTokenCellCount' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokenCellCount'],
                'sourceHeaderDuplicateTokenCount' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokenCount'],
                'sourceHeaderDuplicateTokens' => $sourceHeaderReferenceSummary['duplicateSourceHeaderTokens'],
                'sourceHeaderAmbiguousReferenceCount' => $sourceHeaderReferenceSummary['ambiguousReferenceCount'],
                'hasAmbiguousSourceHeaderReferences' => $sourceHeaderReferenceSummary['ambiguousReferenceCount'] > 0,
                'ambiguousSourceHeaderReferences' => $sourceHeaderReferenceSummary['ambiguousReferences'],
                'duplicateHeaderIdCount' => $sourceHeaderReferenceSummary['duplicateHeaderIdCount'],
                'hasDuplicateHeaderIds' => $sourceHeaderReferenceSummary['duplicateHeaderIdCount'] > 0,
                'duplicateHeaderIds' => $sourceHeaderReferenceSummary['duplicateHeaderIds'],
                'headerAbbreviationCount' => $headerAbbreviationCount,
                'hasHeaderAbbreviations' => $headerAbbreviationCount > 0,
                'headerAxisCount' => $headerAxisCount,
                'hasHeaderAxes' => $headerAxisCount > 0,
                'headerAxes' => array_values(array_unique($headerAxes)),
            ],
        ];
    }

    /**
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    public static function rowHeaderMap(AstNode $table, string $idPrefix = 'pandoc-table'): array
    {
        return self::rowHeaderMapFromAssociations(
            self::sectionGrids($table),
            self::headerAssociations($table, $idPrefix)
        );
    }

    /**
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    public static function rowMatrix(AstNode $table, string $idPrefix = 'pandoc-table'): array
    {
        return self::rowMatrixFromAssociations(
            self::sectionGrids($table),
            self::headerAssociations($table, $idPrefix)
        );
    }

    /**
     * @return array{columnCount:int,rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    public static function flatGrid(AstNode $table): array
    {
        return self::flatGridFromSections(self::sectionGrids($table));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function flatGridFallbackDiagnostics(AstNode $table): array
    {
        return self::flatGridFallbackDiagnosticsFromGrid(self::flatGrid($table));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function diagnostics(AstNode $table): array
    {
        $diagnostics = self::columnDiagnostics($table);
        array_push($diagnostics, ...self::emptyTableDiagnostics($table));
        array_push($diagnostics, ...self::widthDiagnostics($table, $diagnostics !== []));
        array_push($diagnostics, ...self::spanNormalizationDiagnostics($table));
        array_push($diagnostics, ...self::duplicateHeaderIdDiagnostics($table));
        array_push($diagnostics, ...self::duplicateSourceIdDiagnostics($table));
        array_push($diagnostics, ...self::duplicateSourceHeaderTokenDiagnostics($table));
        array_push($diagnostics, ...self::invalidSourceScopeDiagnostics($table));
        $declaredColumnCount = self::declaredColumnCount($table);
        foreach (self::sectionRowGroups($table, null) as $group) {
            $rows = $group['rows'];
            $rowCount = count($rows);
            array_push($diagnostics, ...self::rowspanOverlapDiagnostics($group['section'], $rows, $declaredColumnCount));
            $layoutColumnCount = max(1, $declaredColumnCount, self::columnCountForRows($rows));
            $layoutRows = self::layoutRows($rows, $layoutColumnCount);
            foreach ($layoutRows as $rowIndex => $layoutRow) {
                $availableRows = max(1, $rowCount - $rowIndex);
                foreach ($layoutRow['cells'] as $cell) {
                    $rowspan = self::cellRowspanForRows($cell['node'], $rowIndex, $rowCount);
                    if ($rowspan <= $availableRows) {
                        if ($declaredColumnCount > 0) {
                            self::appendDeclaredColumnDiagnostic(
                                $diagnostics,
                                $group['section'],
                                $rowIndex,
                                $cell,
                                $declaredColumnCount
                            );
                        }

                        continue;
                    }

                    $diagnostics[] = [
                        'code' => 'rowspan-crosses-section-boundary',
                        'section' => $group['section'],
                        'row' => $rowIndex,
                        'column' => $cell['column'],
                        'sourceCell' => $cell['sourceCell'],
                        'sourceColumn' => $cell['sourceColumn'],
                        ...self::sourceRowCoordinateFields(
                            (int) ($cell['sourceRow'] ?? $rowIndex),
                            max(1, (int) ($cell['sourceRowspan'] ?? $rowspan))
                        ),
                        'rowspan' => $rowspan,
                        'availableRows' => $availableRows,
                    ];

                    if ($declaredColumnCount > 0) {
                        self::appendDeclaredColumnDiagnostic(
                            $diagnostics,
                            $group['section'],
                            $rowIndex,
                            $cell,
                            $declaredColumnCount
                        );
                    }
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function duplicateHeaderIdDiagnostics(AstNode $table): array
    {
        $associations = self::headerAssociations($table);
        $summary = is_array($associations['summary'] ?? null) ? $associations['summary'] : [];
        $duplicateIds = self::stringList($summary['duplicateHeaderIds'] ?? []);
        if ($duplicateIds === []) {
            return [];
        }

        $headersById = [];
        foreach (is_array($associations['headerCells'] ?? null) ? $associations['headerCells'] : [] as $headerCell) {
            if (!is_array($headerCell)) {
                continue;
            }

            $id = trim((string) ($headerCell['id'] ?? ''));
            if ($id === '' || !in_array($id, $duplicateIds, true)) {
                continue;
            }

            $headersById[$id][] = self::duplicateHeaderLocationRecord($headerCell);
        }

        $diagnostics = [];
        foreach ($duplicateIds as $id) {
            $locations = array_values($headersById[$id] ?? []);
            if (count($locations) < 2) {
                continue;
            }

            $diagnostics[] = [
                'code' => 'table-header-id-duplicated',
                'source' => 'html-table-headers',
                'id' => $id,
                'headerCellCount' => count($locations),
                'locations' => $locations,
            ];
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function duplicateSourceIdDiagnostics(AstNode $table): array
    {
        $duplicates = self::duplicateSourceIdRecords(self::sourceIdRecords($table, self::cellCoverage($table)));
        if ($duplicates === []) {
            return [];
        }

        return [[
            'code' => 'table-source-id-duplicated',
            'source' => 'html-table-source-ids',
            'caption' => (string) $table->attr('caption', ''),
            'duplicateIdCount' => count($duplicates),
            'duplicateLocationCount' => self::duplicateSourceIdLocationCount($duplicates),
            'duplicateIds' => self::duplicateSourceIdStrings($duplicates),
            'duplicateScopes' => self::duplicateSourceIdScopes($duplicates),
            'duplicates' => $duplicates,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function duplicateSourceHeaderTokenDiagnostics(AstNode $table): array
    {
        $associations = self::headerAssociations($table);
        $summary = is_array($associations['summary'] ?? null) ? $associations['summary'] : [];
        $duplicateTokenCount = (int) ($summary['duplicateSourceHeaderTokenCount'] ?? 0);
        if ($duplicateTokenCount <= 0) {
            return [];
        }

        return [[
            'code' => 'table-source-headers-duplicate-tokens',
            'source' => 'html-table-headers',
            'caption' => (string) $table->attr('caption', ''),
            'duplicateTokenCellCount' => (int) ($summary['duplicateSourceHeaderTokenCellCount'] ?? 0),
            'duplicateTokenCount' => $duplicateTokenCount,
            'duplicateTokens' => self::stringList($summary['duplicateSourceHeaderTokens'] ?? []),
            'cells' => self::sourceHeaderDuplicateTokenCells($associations),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function invalidSourceScopeDiagnostics(AstNode $table): array
    {
        $diagnostics = [];
        foreach (self::sectionGrids($table) as $sectionGrid) {
            $section = (string) ($sectionGrid['section'] ?? '');
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell') {
                        continue;
                    }

                    $node = $slot['node'] ?? null;
                    $rawScope = self::cellSourceHtmlScopeRaw($node);
                    if ($rawScope === '' || self::isSourceHtmlScopeValue($rawScope)) {
                        continue;
                    }

                    $presence = self::sourceHtmlAttributePresence($node, 'scope');
                    $diagnostic = [
                        'code' => 'table-header-scope-invalid',
                        'source' => 'html-table-scope',
                        'attributeSource' => (string) ($presence['source'] ?? ''),
                        'section' => $section,
                        'row' => (int) $rowIndex,
                        'column' => (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0),
                        'sourceCell' => (int) ($slot['sourceCell'] ?? 0),
                        'sourceColumn' => (int) ($slot['sourceColumn'] ?? 0),
                        ...self::sourceRowCoordinateFields(
                            (int) ($slot['sourceRow'] ?? $rowIndex),
                            max(1, (int) ($slot['sourceRowspan'] ?? $slot['rowspan'] ?? 1))
                        ),
                        'rawScope' => $rawScope,
                        'allowedScopes' => self::SOURCE_HTML_HEADER_SCOPE_VALUES,
                        'fallbackScope' => self::headerScope($slot),
                        'headerCell' => (bool) ($slot['headerCell'] ?? false),
                        'text' => $node instanceof AstNode ? self::plainText($node) : '',
                    ];

                    $diagnostics[] = $diagnostic;
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $headerCell
     * @return array<string, mixed>
     */
    private static function duplicateHeaderLocationRecord(array $headerCell): array
    {
        $record = [];
        foreach ([
            'key',
            'section',
            'rowRole',
            'scope',
            'text',
        ] as $attribute) {
            $value = trim((string) ($headerCell[$attribute] ?? ''));
            if ($value !== '') {
                $record[$attribute] = $value;
            }
        }

        foreach ([
            'row',
            'column',
            'sourceCell',
            'sourceColumn',
            'colspan',
            'rowspan',
            'rowHeadColumns',
        ] as $attribute) {
            if (isset($headerCell[$attribute]) && is_numeric($headerCell[$attribute])) {
                $record[$attribute] = (int) $headerCell[$attribute];
            }
        }

        $columns = self::intList($headerCell['columns'] ?? []);
        if ($columns !== []) {
            $record['columns'] = $columns;
        }

        return $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function emptyTableDiagnostics(AstNode $table): array
    {
        if (self::cellCoverage($table) !== []) {
            return [];
        }

        $columnCount = self::columnCount($table);
        $rowGroups = self::rowGroups($table, $columnCount);
        $rowGroupSummary = self::rowGroupSummary($rowGroups);

        return [[
            'code' => 'table-has-no-cells',
            'source' => 'pandoc-table-geometry',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $columnCount,
            'declaredColumnCount' => self::declaredColumnCount($table),
            'sectionCount' => (int) $rowGroupSummary['rowGroupCount'],
            'rowCount' => self::tableRowCountFromRowGroupSummary($rowGroupSummary),
            'bodyCount' => (int) $rowGroupSummary['bodyGroupCount'],
            'headRowCount' => (int) $rowGroupSummary['tableHeadRowCount'],
            'bodyHeadRowCount' => (int) $rowGroupSummary['bodyHeadRowCount'],
            'bodyRowCount' => (int) $rowGroupSummary['bodyRowCount'],
            'footRowCount' => (int) $rowGroupSummary['tableFootRowCount'],
            'hasTableFoot' => (bool) $rowGroupSummary['hasTableFoot'],
            'hasBodyHeadRows' => (bool) $rowGroupSummary['hasBodyHeadRows'],
            'sections' => self::emptyTableDiagnosticSections($rowGroups),
        ]];
    }

    /**
     * @param list<array<string, mixed>> $rowGroups
     * @return list<array<string, mixed>>
     */
    private static function emptyTableDiagnosticSections(array $rowGroups): array
    {
        $sections = [];
        foreach ($rowGroups as $rowGroup) {
            $section = [
                'section' => (string) ($rowGroup['section'] ?? ''),
                'kind' => (string) ($rowGroup['kind'] ?? ''),
                'rowCount' => max(0, (int) ($rowGroup['rowCount'] ?? 0)),
                'cellCount' => max(0, (int) ($rowGroup['cellCount'] ?? 0)),
                'rowRoles' => self::stringList($rowGroup['rowRoles'] ?? []),
            ];
            if (array_key_exists('bodyIndex', $rowGroup)) {
                $section['bodyIndex'] = max(0, (int) $rowGroup['bodyIndex']);
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $rowGroupSummary
     */
    private static function tableRowCountFromRowGroupSummary(array $rowGroupSummary): int
    {
        return max(0, (int) $rowGroupSummary['tableHeadRowCount'])
            + max(0, (int) $rowGroupSummary['bodyHeadRowCount'])
            + max(0, (int) $rowGroupSummary['bodyRowCount'])
            + max(0, (int) $rowGroupSummary['tableFootRowCount']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function widthDiagnostics(AstNode $table, bool $suppressUnderfull = false): array
    {
        $summary = self::columnWidthSummary($table);
        $diagnostics = [];
        $invalidWidths = $summary['invalidWidths'] ?? [];
        if (is_array($invalidWidths) && $invalidWidths !== []) {
            $diagnostics[] = [
                'code' => 'table-widths-have-invalid-values',
                'source' => 'table-widths',
                'columnCount' => (int) $summary['columnCount'],
                'invalidWidthCount' => count($invalidWidths),
                'invalidColumns' => array_values(array_map(
                    static fn (array $record): int => (int) $record['column'],
                    $invalidWidths
                )),
                'validWidthColumns' => $summary['validWidthColumns'],
                'missingColumns' => $summary['missingColumns'],
                'invalidWidths' => $invalidWidths,
            ];
        }

        $columns = [];
        foreach ($summary['percentWidths'] as $column => $percentWidth) {
            if ($percentWidth !== null) {
                $columns[] = (int) $column;
            }
        }

        if (($summary['overfull'] ?? false) === true) {
            $diagnostics[] = [
                'code' => 'table-widths-exceed-full-width',
                'source' => 'table-widths',
                'columnCount' => (int) $summary['columnCount'],
                'columns' => $columns,
                'widthTotal' => (float) $summary['widthTotal'],
                'overflowAmount' => (float) $summary['overflowAmount'],
                'normalizedWidths' => $summary['normalizedWidths'],
                'percentWidths' => $summary['percentWidths'],
            ];
        }

        if (!$suppressUnderfull && ($summary['underfull'] ?? false) === true) {
            $diagnostics[] = [
                'code' => 'table-widths-underfill-full-width',
                'source' => 'table-widths',
                'columnCount' => (int) $summary['columnCount'],
                'columns' => $columns,
                'widthTotal' => (float) $summary['widthTotal'],
                'underflowAmount' => (float) $summary['underflowAmount'],
                'normalizedWidths' => $summary['normalizedWidths'],
                'percentWidths' => $summary['percentWidths'],
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function markdownGridTableWriterDiagnostics(array $coverage, string $writer, ?AstNode $table): array
    {
        $spannedCells = [];
        $requiredSlots = [];
        $spanTypes = [];
        $blockCells = [];
        $blockTypes = [];

        foreach ($coverage as $record) {
            $rawColspan = max(1, (int) ($record['rawColspan'] ?? 1));
            $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
            $hasSpan = $rawColspan > 1 || $rawRowspan > 1;
            if ($rawColspan > 1) {
                $spanTypes[] = 'colspan';
                array_push($requiredSlots, ...self::sectionedFlattenedSlotRecords($record, 'colspan'));
            }
            if ($rawRowspan > 1) {
                $spanTypes[] = 'rowspan';
                array_push($requiredSlots, ...self::sectionedFlattenedSlotRecords($record, 'rowspan'));
            }
            if ($hasSpan) {
                $spannedCells[] = [
                    'section' => (string) ($record['section'] ?? ''),
                    'row' => (int) ($record['row'] ?? 0),
                    'column' => (int) ($record['column'] ?? 0),
                    'columns' => self::intList($record['columns'] ?? []),
                    'rawColspan' => $rawColspan,
                    'rawRowspan' => $rawRowspan,
                ];
            }

            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode || self::nestedTableSummaries($node) !== []) {
                continue;
            }

            $content = self::cellContentSummary($node);
            if ($content === []) {
                continue;
            }

            $cellBlockTypes = self::stringList($content['blockTypes'] ?? []);
            foreach ($cellBlockTypes as $blockType) {
                $blockTypes[] = $blockType;
            }
            $blockCells[] = [
                'section' => (string) ($record['section'] ?? ''),
                'row' => (int) ($record['row'] ?? 0),
                'column' => (int) ($record['column'] ?? 0),
                'columns' => self::intList($record['columns'] ?? []),
                'blockCount' => (int) ($content['blockCount'] ?? 0),
                'blockTypes' => $cellBlockTypes,
            ];
        }

        if ($spannedCells === [] && $blockCells === []) {
            return [];
        }

        $reason = $spannedCells !== [] ? 'spans' : 'block-content';
        if ($spannedCells !== [] && $blockCells !== []) {
            $reason = 'spans-and-block-content';
        }

        return [[
            'code' => 'markdown-grid-table-required',
            'writer' => $writer,
            'reason' => $reason,
            'requiredFeature' => 'grid_tables',
            'source' => 'pandoc-markdown-grid-tables',
            'caption' => $table instanceof AstNode ? (string) $table->attr('caption', '') : '',
            'hasCaption' => $table instanceof AstNode && trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $table instanceof AstNode ? self::columnCount($table) : 0,
            'spanTypes' => array_values(array_unique($spanTypes)),
            'spannedCellCount' => count($spannedCells),
            'spannedCells' => $spannedCells,
            'requiredSlotCount' => count($requiredSlots),
            'requiredSlots' => $requiredSlots,
            'blockContentCellCount' => count($blockCells),
            'blockCells' => $blockCells,
            'blockTypes' => array_values(array_unique($blockTypes)),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function spanNormalizationDiagnostics(AstNode $table): array
    {
        $diagnostics = [];
        foreach (self::sectionRowGroups($table, null) as $group) {
            $rows = $group['rows'];
            $layoutColumnCount = max(1, self::columnCountForRows($rows));
            foreach (self::layoutRows($rows, $layoutColumnCount) as $rowIndex => $layoutRow) {
                foreach ($layoutRow['cells'] as $cell) {
                    array_push(
                        $diagnostics,
                        ...self::cellSpanNormalizationDiagnostics((string) $group['section'], $rowIndex, $cell)
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int,sourceRow:int,sourceRowspan:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>} $cell
     * @return list<array<string, mixed>>
     */
    private static function cellSpanNormalizationDiagnostics(string $section, int $rowIndex, array $cell): array
    {
        $node = $cell['node'];
        $diagnostics = [];
        if ($node->hasAttr('colspan') && !self::spanAttributeIsValid($node->attr('colspan'), false)) {
            $diagnostics[] = self::spanNormalizationDiagnostic(
                $section,
                $rowIndex,
                $cell,
                'colspan',
                $node->attr('colspan'),
                self::cellColspan($node),
                false
            );
        }

        if ($node->hasAttr('rowspan') && !self::spanAttributeIsValid($node->attr('rowspan'), true)) {
            $diagnostics[] = self::spanNormalizationDiagnostic(
                $section,
                $rowIndex,
                $cell,
                'rowspan',
                $node->attr('rowspan'),
                self::cellRowspan($node),
                true
            );
        }

        return $diagnostics;
    }

    private static function spanAttributeIsValid(mixed $value, bool $allowZero): bool
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^\d+$/', $value) !== 1) {
                return false;
            }

            $value = (int) $value;

            return $allowZero ? $value >= 0 : $value >= 1;
        }

        if (!is_int($value) && !is_float($value)) {
            return false;
        }

        if ((float) $value !== (float) (int) $value) {
            return false;
        }

        $value = (int) $value;

        return $allowZero ? $value >= 0 : $value >= 1;
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int,sourceRow:int,sourceRowspan:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>} $cell
     * @return array<string, mixed>
     */
    private static function spanNormalizationDiagnostic(
        string $section,
        int $rowIndex,
        array $cell,
        string $attribute,
        mixed $rawValue,
        int $normalizedValue,
        bool $allowZero
    ): array {
        $diagnostic = [
            'code' => 'cell-span-normalized',
            'section' => $section,
            'row' => $rowIndex,
            'column' => $cell['column'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            ...self::sourceRowCoordinateFields(
                (int) ($cell['sourceRow'] ?? $rowIndex),
                max(1, (int) ($cell['sourceRowspan'] ?? 1))
            ),
            'attribute' => $attribute,
            'rawType' => get_debug_type($rawValue),
            'normalizedValue' => $normalizedValue,
            'minimumValue' => $allowZero ? 0 : 1,
            'zeroMeansRowGroup' => $attribute === 'rowspan',
        ];

        if (is_scalar($rawValue) || $rawValue === null) {
            $diagnostic['rawValue'] = $rawValue;
        }

        return $diagnostic;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnDiagnostics(AstNode $table): array
    {
        $diagnostics = $table->attr('columnDiagnostics', []);
        if (!is_array($diagnostics)) {
            return [];
        }

        $records = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $record = [];
            foreach ($diagnostic as $key => $value) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $record[$key] = $value;
                    continue;
                }

                if (!is_array($value) || !array_is_list($value)) {
                    continue;
                }

                $list = [];
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $list[] = $item;
                    }
                }
                $record[$key] = $list;
            }

            if (isset($record['code']) && is_scalar($record['code']) && trim((string) $record['code']) !== '') {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    public static function alignments(AstNode $table, int $columnCount): array
    {
        $alignments = $table->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }
        $sourceSpecs = self::sourceColumnSpecs($table->attr('columnSpecs', []));

        $normalized = [];
        for ($index = 0; $index < max(0, $columnCount); $index++) {
            $alignment = (string) ($alignments[$index] ?? ($sourceSpecs[$index]['alignment'] ?? 'default'));
            $normalized[] = self::normalizeAlignment($alignment);
        }

        return $normalized;
    }

    public static function cellAlignment(AstNode $table, int $column, AstNode $cell): string
    {
        $alignment = (string) $cell->attr('align', '');
        if ($alignment !== '') {
            return self::normalizeAlignment($alignment);
        }

        $alignments = $table->attr('alignments', []);
        if (is_array($alignments)) {
            return self::normalizeAlignment((string) ($alignments[$column] ?? 'default'));
        }

        return 'default';
    }

    public static function cellVerticalAlignment(AstNode $cell): string
    {
        $alignment = self::normalizeVerticalAlignment((string) $cell->attr('valign', ''));
        if ($alignment !== 'default') {
            return $alignment;
        }

        $alignment = self::normalizeVerticalAlignment(self::sourceHtmlAttribute($cell, 'valign'));
        if ($alignment !== 'default') {
            return $alignment;
        }

        $style = self::sourceHtmlAttribute($cell, 'style');
        if (preg_match('/(?:^|;)\s*vertical-align\s*:\s*(baseline|top|middle|bottom)\b/i', $style, $m) === 1) {
            return strtolower($m[1]);
        }

        return 'default';
    }

    /**
     * @return list<array{column:int,alignment:string,width:?float,declared:bool,source?:array<string, mixed>}>
     */
    public static function columnSpecs(AstNode $table, int $columnCount): array
    {
        $columnCount = max(0, $columnCount);
        $alignments = self::alignments($table, $columnCount);
        $widths = $table->attr('widths', []);
        if (!is_array($widths)) {
            $widths = [];
        } else {
            $widths = array_values($widths);
        }
        $sourceSpecs = self::sourceColumnSpecs($table->attr('columnSpecs', []));
        if ($widths === [] && $sourceSpecs !== []) {
            foreach ($sourceSpecs as $sourceSpec) {
                $widths[] = $sourceSpec['width'] ?? null;
            }
        }

        $declaredColumnCount = self::declaredColumnCount($table);
        $columnSources = self::columnSources($table);
        $widthTotal = self::positiveWidthTotal($widths, $columnCount);
        $specs = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $width = null;
            if (array_key_exists($column, $widths) && is_numeric($widths[$column]) && (float) $widths[$column] > 0.0) {
                $width = (float) $widths[$column];
            }

            $spec = [
                'column' => $column,
                'alignment' => $alignments[$column] ?? 'default',
                'width' => $width,
                'normalizedWidth' => $width !== null && $widthTotal > 0.0 ? self::roundWidth($width / $widthTotal) : null,
                'percentWidth' => $width !== null ? self::roundWidth($width * 100.0) : null,
                'declared' => $column < $declaredColumnCount,
            ];
            if (isset($columnSources[$column])) {
                $spec['source'] = $columnSources[$column];
            } elseif (isset($sourceSpecs[$column]['source']) && is_array($sourceSpecs[$column]['source'])) {
                $spec['source'] = $sourceSpecs[$column]['source'];
            }

            $specs[] = $spec;
        }

        return $specs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function columnGroups(AstNode $table, ?int $columnCount = null): array
    {
        $columnCount = max(0, $columnCount ?? self::columnCount($table));
        if ($columnCount === 0) {
            return [];
        }

        $groups = [];
        $activeGroup = null;
        $activeKey = '';
        foreach (self::columnSpecs($table, $columnCount) as $spec) {
            $source = $spec['source'] ?? null;
            if (!is_array($source)) {
                if (is_array($activeGroup)) {
                    $groups[] = $activeGroup;
                    $activeGroup = null;
                    $activeKey = '';
                }
                continue;
            }

            $key = self::columnGroupSourceKey($source);
            if (!is_array($activeGroup) || $key !== $activeKey) {
                if (is_array($activeGroup)) {
                    $groups[] = $activeGroup;
                }
                $activeGroup = self::columnGroupFromSpec($spec, $source);
                $activeKey = $key;
                continue;
            }

            self::appendColumnGroupSpec($activeGroup, $spec, $source);
        }

        if (is_array($activeGroup)) {
            $groups[] = $activeGroup;
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rowGroups(AstNode $table, ?int $columnCount = null): array
    {
        $columnCount = max(0, $columnCount ?? self::columnCount($table));
        $groups = [];
        $bodyIndex = 0;
        $groupOrdinal = 0;
        $globalRowOffset = 0;
        foreach ($table->children as $section) {
            if ($section->type === 'table_head') {
                $rows = self::sectionRows($section);
                $record = [
                    'section' => 'head',
                    'kind' => 'table-head',
                    'rowCount' => count($rows),
                    'headRowCount' => count($rows),
                    'bodyHeadRowCount' => 0,
                    'bodyRowCount' => 0,
                    'footRowCount' => 0,
                    'rowHeadColumns' => 0,
                    'hasBodyHeadRows' => false,
                    'hasRowHeadColumns' => false,
                    'cellCount' => self::rowCellCount($rows),
                    'rowRoles' => $rows === [] ? [] : ['head'],
                ];
                $sourceAttributes = self::sourceAttributeSummary($section);
                if ($sourceAttributes !== []) {
                    $record['sourceAttributes'] = $sourceAttributes;
                }
                $record = self::rowGroupRecordWithRange($record, $groupOrdinal, $globalRowOffset);
                $groups[] = $record;
                $groupOrdinal++;
                $globalRowOffset = (int) $record['globalRowEnd'];
                continue;
            }

            if ($section->type === 'table_body') {
                $bodyHeadRows = self::bodyHeadRows($section);
                $bodyRows = self::sectionRows($section);
                $rowHeadColumns = self::rowHeadColumns($section, $columnCount);
                $rowRoles = [];
                if ($bodyHeadRows !== []) {
                    $rowRoles[] = 'body-head';
                }
                if ($bodyRows !== []) {
                    $rowRoles[] = 'body';
                }

                $record = [
                    'section' => 'body' . ($bodyIndex === 0 ? '' : (string) $bodyIndex),
                    'kind' => 'table-body',
                    'bodyIndex' => $bodyIndex,
                    'bodyOrdinal' => $bodyIndex,
                    'rowCount' => count($bodyHeadRows) + count($bodyRows),
                    'headRowCount' => count($bodyHeadRows),
                    'bodyHeadRowCount' => count($bodyHeadRows),
                    'bodyRowCount' => count($bodyRows),
                    'footRowCount' => 0,
                    'rowHeadColumns' => $rowHeadColumns,
                    'hasBodyHeadRows' => $bodyHeadRows !== [],
                    'hasRowHeadColumns' => $rowHeadColumns > 0,
                    'cellCount' => self::rowCellCount([...$bodyHeadRows, ...$bodyRows]),
                    'rowRoles' => $rowRoles,
                ];
                $sourceAttributes = self::sourceAttributeSummary($section);
                if ($sourceAttributes !== []) {
                    $record['sourceAttributes'] = $sourceAttributes;
                }
                $record = self::rowGroupRecordWithRange($record, $groupOrdinal, $globalRowOffset);
                $groups[] = $record;
                $groupOrdinal++;
                $globalRowOffset = (int) $record['globalRowEnd'];
                $bodyIndex++;
                continue;
            }

            if ($section->type === 'table_foot') {
                $rows = self::sectionRows($section);
                $record = [
                    'section' => 'foot',
                    'kind' => 'table-foot',
                    'rowCount' => count($rows),
                    'headRowCount' => 0,
                    'bodyHeadRowCount' => 0,
                    'bodyRowCount' => 0,
                    'footRowCount' => count($rows),
                    'rowHeadColumns' => 0,
                    'hasBodyHeadRows' => false,
                    'hasRowHeadColumns' => false,
                    'cellCount' => self::rowCellCount($rows),
                    'rowRoles' => $rows === [] ? [] : ['foot'],
                ];
                $sourceAttributes = self::sourceAttributeSummary($section);
                if ($sourceAttributes !== []) {
                    $record['sourceAttributes'] = $sourceAttributes;
                }
                $record = self::rowGroupRecordWithRange($record, $groupOrdinal, $globalRowOffset);
                $groups[] = $record;
                $groupOrdinal++;
                $globalRowOffset = (int) $record['globalRowEnd'];
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private static function rowGroupRecordWithRange(array $record, int $ordinal, int $globalRowStart): array
    {
        $kind = (string) ($record['kind'] ?? '');
        $rowCount = max(0, (int) ($record['rowCount'] ?? 0));
        $globalRowStart = max(0, $globalRowStart);
        $globalRowEnd = $globalRowStart + $rowCount;
        $headRowCount = max(0, (int) ($record['headRowCount'] ?? 0));
        $bodyHeadRowCount = max(0, (int) ($record['bodyHeadRowCount'] ?? 0));
        $bodyRowCount = max(0, (int) ($record['bodyRowCount'] ?? 0));
        $footRowCount = max(0, (int) ($record['footRowCount'] ?? 0));

        if ($kind === 'table-head') {
            $headerLikeRowCount = $headRowCount > 0 ? $headRowCount : $rowCount;
            $dataLikeRowCount = 0;
        } elseif ($kind === 'table-body') {
            $headerLikeRowCount = $bodyHeadRowCount;
            $dataLikeRowCount = $bodyRowCount;
        } elseif ($kind === 'table-foot') {
            $headerLikeRowCount = 0;
            $dataLikeRowCount = $footRowCount > 0 ? $footRowCount : $rowCount;
        } else {
            $headerLikeRowCount = 0;
            $dataLikeRowCount = $rowCount;
        }

        $record['ordinal'] = max(0, $ordinal);
        $record['globalRowStart'] = $globalRowStart;
        $record['globalRowEnd'] = $globalRowEnd;
        $record['rowRange'] = [$globalRowStart, $globalRowEnd];
        $record['headerLikeRowCount'] = $headerLikeRowCount;
        $record['dataLikeRowCount'] = $dataLikeRowCount;
        $record['hasHeaderLikeRows'] = $headerLikeRowCount > 0;
        $record['hasDataLikeRows'] = $dataLikeRowCount > 0;
        $record['rowRoleCounts'] = self::rowRoleCountsForRowGroup(
            $kind,
            $headRowCount,
            $bodyHeadRowCount,
            $bodyRowCount,
            $footRowCount,
            $rowCount
        );

        return $record;
    }

    /**
     * @return array<string, int>
     */
    private static function rowRoleCountsForRowGroup(
        string $kind,
        int $headRowCount,
        int $bodyHeadRowCount,
        int $bodyRowCount,
        int $footRowCount,
        int $rowCount
    ): array
    {
        $counts = [];
        if ($kind === 'table-head') {
            $count = $headRowCount > 0 ? $headRowCount : $rowCount;
            if ($count > 0) {
                $counts['head'] = $count;
            }

            return $counts;
        }

        if ($kind === 'table-body') {
            if ($bodyHeadRowCount > 0) {
                $counts['body-head'] = $bodyHeadRowCount;
            }
            if ($bodyRowCount > 0) {
                $counts['body'] = $bodyRowCount;
            }

            return $counts;
        }

        if ($kind === 'table-foot') {
            $count = $footRowCount > 0 ? $footRowCount : $rowCount;
            if ($count > 0) {
                $counts['foot'] = $count;
            }
        }

        return $counts;
    }

    /**
     * @return array{
     *     columnCount:int,
     *     hasExplicitWidths:bool,
     *     explicitWidthCount:int,
     *     validWidthCount:int,
     *     missingWidthCount:int,
     *     validWidthColumns:list<int>,
     *     invalidWidthColumns:list<int>,
     *     invalidWidths:list<array<string, mixed>>,
     *     hasCompleteWidths:bool,
     *     hasPartialWidths:bool,
     *     widthTotal:float,
     *     normalizedWidths:list<?float>,
     *     percentWidths:list<?float>,
     *     missingColumns:list<int>,
     *     overfull:bool,
     *     underfull:bool,
     *     overflowAmount:float,
     *     underflowAmount:float
     * }
     */
    public static function columnWidthSummary(AstNode $table, ?int $columnCount = null): array
    {
        $columnCount = max(0, $columnCount ?? self::columnCount($table));
        $rawWidths = $table->attr('widths', []);
        $hasExplicitWidths = is_array($rawWidths) && $rawWidths !== [];
        $explicitWidthCount = is_array($rawWidths) ? count($rawWidths) : 0;
        $specs = self::columnSpecs($table, $columnCount);

        $widthTotal = 0.0;
        $validWidthCount = 0;
        $validWidthColumns = [];
        $missingColumns = [];
        $invalidWidths = self::invalidWidthRecords($rawWidths, $columnCount);
        $invalidWidthColumns = array_map(static fn (array $record): int => (int) $record['column'], $invalidWidths);
        foreach ($specs as $spec) {
            $width = $spec['width'];
            if ($width === null) {
                $missingColumns[] = (int) $spec['column'];
                continue;
            }

            $widthTotal += $width;
            $validWidthCount++;
            $validWidthColumns[] = (int) $spec['column'];
        }

        $normalizedWidths = [];
        $percentWidths = [];
        foreach ($specs as $spec) {
            $width = $spec['width'];
            $normalizedWidths[] = $width !== null && $widthTotal > 0.0 ? self::roundWidth($width / $widthTotal) : null;
            $percentWidths[] = $width !== null ? self::roundWidth($width * 100.0) : null;
        }

        $hasCompleteWidths = $columnCount > 0 && $validWidthCount === $columnCount;
        $hasPartialWidths = $hasExplicitWidths && $validWidthCount > 0 && !$hasCompleteWidths;
        $overfull = $widthTotal > 1.0 + self::WIDTH_EPSILON;
        $underfull = $hasCompleteWidths && $widthTotal > self::WIDTH_EPSILON && $widthTotal < 1.0 - self::WIDTH_EPSILON;

        return [
            'columnCount' => $columnCount,
            'hasExplicitWidths' => $hasExplicitWidths,
            'explicitWidthCount' => $explicitWidthCount,
            'validWidthCount' => $validWidthCount,
            'missingWidthCount' => count($missingColumns),
            'validWidthColumns' => $validWidthColumns,
            'invalidWidthColumns' => $invalidWidthColumns,
            'invalidWidths' => $invalidWidths,
            'hasCompleteWidths' => $hasCompleteWidths,
            'hasPartialWidths' => $hasPartialWidths,
            'widthTotal' => self::roundWidth($widthTotal),
            'normalizedWidths' => $normalizedWidths,
            'percentWidths' => $percentWidths,
            'missingColumns' => $missingColumns,
            'overfull' => $overfull,
            'underfull' => $underfull,
            'overflowAmount' => $overfull ? self::roundWidth($widthTotal - 1.0) : 0.0,
            'underflowAmount' => $underfull ? self::roundWidth(1.0 - $widthTotal) : 0.0,
        ];
    }

    public static function rowHeadColumns(AstNode $body, int $columnCount): int
    {
        $value = $body->attr('rowHeadColumns', 0);
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || !is_numeric($value)) {
                return 0;
            }
        } elseif (!is_int($value) && !is_float($value)) {
            return 0;
        }

        $count = (int) $value;
        if ($count <= 0) {
            return 0;
        }

        return min($count, max(0, $columnCount));
    }

    public static function isHeaderCell(bool $headerRow, int $rowHeadColumns, int $column, AstNode $cell): bool
    {
        return $headerRow || $cell->attr('header') === true || ($rowHeadColumns > 0 && $column < $rowHeadColumns);
    }

    public static function accessibilityKey(string $section, int $row, int $sourceCell, int $sourceColumn): string
    {
        return $section . ':' . $row . ':' . $sourceCell . ':' . $sourceColumn;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string,writers?:list<string>} $options
     * @return array{
     *     caption:string,
     *     columnCount:int,
     *     declaredColumnCount:int,
     *     columns:list<array{column:int,alignment:string,width:?float,declared:bool}>,
     *     columnGroups:list<array<string, mixed>>,
     *     columnDecimalAlignments:list<array<string, mixed>>,
     *     columnBackgrounds:list<array<string, mixed>>,
     *     columnBorderPresentations:list<array<string, mixed>>,
     *     cellDecimalAlignments:list<array<string, mixed>>,
     *     cellNoWraps:list<array<string, mixed>>,
     *     cellDimensions:list<array<string, mixed>>,
     *     directionality:array<string, mixed>,
     *     tableLayout?:array<string, mixed>,
     *     tableSpacing?:array<string, mixed>,
     *     tableBorderCollapse?:array<string, mixed>,
     *     rowGroups:list<array<string, mixed>>,
     *     captions:array<string, array<string, mixed>>,
     *     sections:list<array<string, mixed>>,
     *     coverage:list<array<string, mixed>>,
     *     sourceCoordinateShifts:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>,
     *     accessibility:array<string, array{id?:string,scope?:string,headers?:list<string>}>,
     *     headerAssociations:array<string, mixed>,
     *     sourceSummary?:array{text:string,source:string,attribute:string},
     *     rowMatrix:array{rows:list<array<string, mixed>>,summary:array<string, mixed>},
     *     flatGrid:array{columnCount:int,rows:list<array<string, mixed>>,summary:array<string, mixed>},
     *     flatGridFallbacks:list<array<string, mixed>>,
     *     summary:array<string, mixed>,
     *     sectionBackgrounds:list<array<string, mixed>>,
     *     sectionBorderPresentations:list<array<string, mixed>>,
     *     rowBackgrounds:list<array<string, mixed>>,
     *     rowBorderPresentations:list<array<string, mixed>>,
     *     cellBackgrounds:list<array<string, mixed>>,
     *     cellBorderPresentations:list<array<string, mixed>>
     * }
     */
    public static function reviewPacket(AstNode $table, array $options = []): array
    {
        $columnCount = self::columnCount($table);
        $sections = self::sectionGrids($table);
        $coverageRecords = self::cellCoverage($table);
        $coverage = self::serializableCoverage($coverageRecords);
        $sourceCoordinateShifts = self::sourceCoordinateShiftRecords($coverageRecords);
        $diagnostics = self::diagnostics($table);
        $captions = self::captionMetadata($table);
        $widthSummary = self::columnWidthSummary($table, $columnCount);
        $columnGroups = self::columnGroups($table, $columnCount);
        $columnDecimalAlignments = self::columnDecimalAlignments($columnGroups);
        $columnBackgrounds = self::columnBackgrounds($columnGroups);
        $columnBorderPresentations = self::columnBorderPresentations($columnGroups);
        $cellDecimalAlignments = self::cellDecimalAlignments($coverageRecords);
        $cellNoWraps = self::cellNoWraps($coverageRecords);
        $cellDimensions = self::cellDimensions($coverageRecords);
        $sectionBackgrounds = self::sectionBackgrounds($sections);
        $sectionBorderPresentations = self::sectionBorderPresentations($sections);
        $rowBackgrounds = self::rowBackgrounds($sections);
        $rowBorderPresentations = self::rowBorderPresentations($sections);
        $cellBackgrounds = self::cellBackgrounds($coverageRecords);
        $cellBorderPresentations = self::cellBorderPresentations($coverageRecords);
        $rowGroups = self::rowGroups($table, $columnCount);
        $sourceSummary = self::sourceSummaryRecord($table);
        $sourceIds = self::sourceIdRecords($table, $coverageRecords);
        $duplicateSourceIds = self::duplicateSourceIdRecords($sourceIds);
        $tableLayout = self::tableLayoutMetadata($table);
        $tableAlignment = self::tableAlignmentMetadata($table);
        $tableFrame = self::tableFrameMetadata($table);
        $tableSpacing = self::tableSpacingMetadata($table);
        $tableBackground = self::tableBackgroundMetadata($table);
        $tableBorderCollapse = self::tableBorderCollapseMetadata($table);
        $tableBorderPresentation = self::tableBorderPresentationMetadata($table);
        $directionality = self::directionalityMetadata($table, $sections, $coverageRecords);
        $localization = self::localizationMetadata($table, $sections, $coverageRecords);
        $includeAccessibility = ($options['accessibility'] ?? true) !== false;
        $idPrefix = self::reviewPacketIdPrefix($table, $options);
        $writerDowngrades = [];
        foreach (self::reviewPacketWriters($options['writers'] ?? ['markdown']) as $writer) {
            $writerDowngrades[$writer] = self::writerDowngradeDiagnosticsFromCoverage($coverageRecords, $writer, $table, $idPrefix);
        }
        $accessibility = $includeAccessibility
            ? self::accessibilityAttributes($table, $idPrefix)
            : [];
        $headerAssociations = $includeAccessibility
            ? self::headerAssociations($table, $idPrefix)
            : self::emptyHeaderAssociations();
        $rowHeaderMap = $includeAccessibility
            ? self::rowHeaderMapFromAssociations($sections, $headerAssociations)
            : self::emptyRowHeaderMap();
        $rowMatrix = self::rowMatrixFromAssociations($sections, $headerAssociations);
        $flatGrid = self::flatGridFromSections($sections);
        $flatGridFallbacks = self::flatGridFallbackDiagnosticsFromGrid($flatGrid);

        $packet = [
            'caption' => (string) $table->attr('caption', ''),
            'captions' => $captions,
            'columnCount' => $columnCount,
            'declaredColumnCount' => self::declaredColumnCount($table),
            'columns' => self::columnSpecs($table, $columnCount),
            'columnGroups' => $columnGroups,
            'columnDecimalAlignments' => $columnDecimalAlignments,
            'columnBackgrounds' => $columnBackgrounds,
            'columnBorderPresentations' => $columnBorderPresentations,
            'cellDecimalAlignments' => $cellDecimalAlignments,
            'cellNoWraps' => $cellNoWraps,
            'cellDimensions' => $cellDimensions,
            'sectionBackgrounds' => $sectionBackgrounds,
            'sectionBorderPresentations' => $sectionBorderPresentations,
            'rowBackgrounds' => $rowBackgrounds,
            'rowBorderPresentations' => $rowBorderPresentations,
            'cellBackgrounds' => $cellBackgrounds,
            'cellBorderPresentations' => $cellBorderPresentations,
            'directionality' => $directionality,
            'localization' => $localization,
            'widthSummary' => $widthSummary,
            'sections' => self::serializableSectionGrids($sections),
            'rowGroups' => $rowGroups,
            'coverage' => $coverage,
            'sourceCoordinateShifts' => $sourceCoordinateShifts,
            'sourceIds' => $sourceIds,
            'duplicateSourceIds' => $duplicateSourceIds,
            'diagnostics' => $diagnostics,
            'writerDowngrades' => $writerDowngrades,
            'accessibility' => $accessibility,
            'headerAssociations' => $headerAssociations,
            'rowHeaderMap' => $rowHeaderMap,
            'rowMatrix' => $rowMatrix,
            'flatGrid' => $flatGrid,
            'flatGridFallbacks' => $flatGridFallbacks,
            'summary' => self::reviewPacketSummary(
                $sections,
                $coverage,
                $diagnostics,
                $writerDowngrades,
                $captions,
                $columnGroups,
                $columnDecimalAlignments,
                $columnBackgrounds,
                $columnBorderPresentations,
                $cellDecimalAlignments,
                $cellNoWraps,
                $cellDimensions,
                $sectionBackgrounds,
                $sectionBorderPresentations,
                $rowBackgrounds,
                $rowBorderPresentations,
                $cellBackgrounds,
                $cellBorderPresentations,
                $duplicateSourceIds,
                $rowGroups,
                $headerAssociations,
                $rowHeaderMap,
                $rowMatrix,
                $flatGrid,
                $flatGridFallbacks,
                $tableLayout,
                $tableAlignment,
                $tableFrame,
                $tableSpacing,
                $tableBackground,
                $tableBorderCollapse,
                $tableBorderPresentation,
                $directionality,
                $localization,
                (string) ($sourceSummary['text'] ?? '')
            ),
        ];

        if ($tableLayout !== []) {
            $packet['tableLayout'] = $tableLayout;
        }

        if ($tableAlignment !== []) {
            $packet['tableAlignment'] = $tableAlignment;
        }

        if ($tableFrame !== []) {
            $packet['tableFrame'] = $tableFrame;
        }

        if ($tableSpacing !== []) {
            $packet['tableSpacing'] = $tableSpacing;
        }

        if ($tableBackground !== []) {
            $packet['tableBackground'] = $tableBackground;
        }

        if ($tableBorderCollapse !== []) {
            $packet['tableBorderCollapse'] = $tableBorderCollapse;
        }

        if ($tableBorderPresentation !== []) {
            $packet['tableBorderPresentation'] = $tableBorderPresentation;
        }

        if ($sourceSummary !== []) {
            $packet['sourceSummary'] = $sourceSummary;
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $packet['sourceAttributes'] = $sourceAttributes;
        }

        return $packet;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string} $options
     */
    public static function withReviewPacket(AstNode $table, array $options = []): AstNode
    {
        if ($table->type !== 'table') {
            return $table;
        }

        return new AstNode(
            $table->type,
            array_replace($table->attrs, ['tableGeometry' => self::reviewPacket($table, $options)]),
            $table->children
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function writerDowngradeDiagnostics(AstNode $table, string $writer): array
    {
        return self::writerDowngradeDiagnosticsFromCoverage(self::cellCoverage($table), $writer, $table);
    }

    private static function normalizeAlignment(string $alignment): string
    {
        $normalized = strtolower(trim($alignment));
        $normalized = rtrim($normalized, ';');
        if (preg_match('/^text-align\s*:\s*(left|right|center)\s*$/', $normalized, $match) === 1) {
            return $match[1];
        }

        $compact = str_replace(['-', '_', ' '], '', $normalized);

        return match ($compact) {
            'left', 'alignleft' => 'left',
            'right', 'alignright' => 'right',
            'center', 'aligncenter' => 'center',
            default => 'default',
        };
    }

    /**
     * @param array<string, mixed> $slot
     */
    private static function headerScope(array $slot): string
    {
        $headerRow = (bool) ($slot['headerRow'] ?? false);
        $rowHeadColumns = (int) ($slot['rowHeadColumns'] ?? 0);
        $anchorColumn = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
        $colspan = max(1, (int) ($slot['colspan'] ?? 1));
        $rowspan = max(1, (int) ($slot['rowspan'] ?? 1));

        if (!$headerRow && $rowHeadColumns > 0 && $anchorColumn < $rowHeadColumns) {
            return $rowspan > 1 ? 'rowgroup' : 'row';
        }

        if ($headerRow) {
            return $colspan > 1 ? 'colgroup' : 'col';
        }

        return $rowspan > 1 ? 'rowgroup' : 'row';
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     */
    private static function columnsOverlap(array $left, array $right): bool
    {
        foreach ($left as $column) {
            if (in_array($column, $right, true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeHtmlId(string $value): string
    {
        $id = strtolower(trim($value));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?? '';
        $id = trim($id, '-');
        if ($id === '') {
            return 'pandoc-table';
        }

        if (preg_match('/^[a-z]/', $id) !== 1) {
            return 'pandoc-' . $id;
        }

        return $id;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string} $options
     */
    private static function reviewPacketIdPrefix(AstNode $table, array $options): string
    {
        $prefix = trim((string) ($options['idPrefix'] ?? ''));
        if ($prefix !== '') {
            return $prefix;
        }

        $prefix = trim((string) $table->attr('accessibilityIdPrefix', ''));
        if ($prefix !== '') {
            return $prefix;
        }

        $htmlAttributes = $table->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['id'])) {
            $prefix = trim((string) $htmlAttributes['id']);
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $prefix = trim((string) $table->attr('id', ''));

        return $prefix === '' ? 'pandoc-table' : $prefix;
    }

    /**
     * @return array{headerCells:list<array<string, mixed>>,dataCells:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function emptyHeaderAssociations(): array
    {
        return [
            'headerCells' => [],
            'dataCells' => [],
            'summary' => [
                'headerCellCount' => 0,
                'dataCellCount' => 0,
                'associatedDataCellCount' => 0,
                'unassociatedDataCellCount' => 0,
                'associationCount' => 0,
                'headerScopes' => [],
                'sourceHeaderOverrideCount' => 0,
                'hasSourceHeaderOverrides' => false,
                'sourceHeaderReferencingCellCount' => 0,
                'sourceHeaderReferenceCount' => 0,
                'sourceHeaderResolvedReferenceCount' => 0,
                'sourceHeaderUnresolvedReferenceCount' => 0,
                'hasUnresolvedSourceHeaderReferences' => false,
                'unresolvedSourceHeaderReferences' => [],
                'duplicateSourceHeaderTokenCellCount' => 0,
                'duplicateSourceHeaderTokenCount' => 0,
                'hasDuplicateSourceHeaderTokens' => false,
                'duplicateSourceHeaderTokens' => [],
                'sourceHeaderDuplicateTokenCellCount' => 0,
                'sourceHeaderDuplicateTokenCount' => 0,
                'sourceHeaderDuplicateTokens' => [],
                'sourceHeaderAmbiguousReferenceCount' => 0,
                'hasAmbiguousSourceHeaderReferences' => false,
                'ambiguousSourceHeaderReferences' => [],
                'duplicateHeaderIdCount' => 0,
                'hasDuplicateHeaderIds' => false,
                'duplicateHeaderIds' => [],
                'headerAbbreviationCount' => 0,
                'hasHeaderAbbreviations' => false,
                'headerAxisCount' => 0,
                'hasHeaderAxes' => false,
                'headerAxes' => [],
            ],
        ];
    }

    /**
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function emptyRowHeaderMap(): array
    {
        return [
            'rows' => [],
            'summary' => [
                'dataRowCount' => 0,
                'labeledDataRowCount' => 0,
                'unlabeledDataRowCount' => 0,
                'rowHeaderCellCount' => 0,
                'rowHeaderReferenceCount' => 0,
                'maxRowHeaderCount' => 0,
                'rowHeaderScopes' => [],
                'hasRowHeaders' => false,
                'hasUnlabeledDataRows' => false,
                'hasRowspanRowHeaders' => false,
                'rowspannedRowHeaderReferenceCount' => 0,
            ],
        ];
    }

    /**
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function emptyRowMatrix(): array
    {
        return [
            'rows' => [],
            'summary' => [
                'rowCount' => 0,
                'headerRowCount' => 0,
                'dataRowCount' => 0,
                'rowWithHeaderCellCount' => 0,
                'rowWithDataCellCount' => 0,
                'completeRowCount' => 0,
                'incompleteRowCount' => 0,
                'coveredRowCount' => 0,
                'missingRowCount' => 0,
                'cellCount' => 0,
                'headerCellCount' => 0,
                'dataCellCount' => 0,
                'coveredSlotCount' => 0,
                'missingSlotCount' => 0,
                'associatedDataCellCount' => 0,
                'unassociatedDataCellCount' => 0,
                'maxCellCountPerRow' => 0,
                'maxHeaderCellsPerRow' => 0,
                'maxDataCellsPerRow' => 0,
                'maxVisualWidth' => 0,
                'rowRoleCounts' => [],
                'sections' => [],
                'hasHeaderAssociations' => false,
                'hasUnassociatedDataCells' => false,
            ],
        ];
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @return array{columnCount:int,rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function flatGridFromSections(array $sections): array
    {
        $columnCount = 0;
        $rows = [];
        $summary = [
            'rowCount' => 0,
            'columnCount' => 0,
            'slotCount' => 0,
            'anchorSlotCount' => 0,
            'coveredSlotCount' => 0,
            'missingSlotCount' => 0,
            'headerRowCount' => 0,
            'dataRowCount' => 0,
            'headerSlotCount' => 0,
            'dataSlotCount' => 0,
            'spanAnchorCount' => 0,
            'colspanAnchorCount' => 0,
            'rowspanAnchorCount' => 0,
            'rowspanToEndAnchorCount' => 0,
            'maxVisualWidth' => 0,
            'sections' => [],
            'hasCoveredSlots' => false,
            'hasMissingSlots' => false,
            'hasSpans' => false,
        ];

        foreach ($sections as $sectionGrid) {
            $section = (string) ($sectionGrid['section'] ?? '');
            if ($section !== '' && !in_array($section, $summary['sections'], true)) {
                $summary['sections'][] = $section;
            }

            $columnCount = max($columnCount, max(0, (int) ($sectionGrid['columnCount'] ?? 0)));
            $globalRowStart = max(0, (int) ($sectionGrid['globalRowStart'] ?? 0));
            foreach ($sectionGrid['rows'] ?? [] as $rowIndex => $slots) {
                if (!is_array($slots)) {
                    continue;
                }

                $entry = $sectionGrid['rowEntries'][$rowIndex] ?? [
                    'header' => false,
                    'rowHeadColumns' => 0,
                    'rowRole' => $section,
                ];
                $globalRow = self::rowGlobalRow($slots, $globalRowStart + (int) $rowIndex);
                $cells = [];
                $rowAnchorSlotCount = 0;
                $rowCoveredSlotCount = 0;
                $rowMissingSlotCount = 0;
                $rowHeaderSlotCount = 0;
                $rowDataSlotCount = 0;

                foreach ($slots as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }

                    $record = self::flatGridSlotRecord($section, (int) $rowIndex, $globalRow, $slot);
                    $cells[] = $record;
                    $summary['slotCount']++;
                    $kind = (string) ($record['kind'] ?? '');
                    if ($kind === 'cell') {
                        $rowAnchorSlotCount++;
                        $summary['anchorSlotCount']++;
                        $colspan = max(1, (int) ($record['colspan'] ?? 1));
                        $rowspan = max(1, (int) ($record['rowspan'] ?? 1));
                        if ($colspan > 1 || $rowspan > 1) {
                            $summary['spanAnchorCount']++;
                            $summary['hasSpans'] = true;
                        }
                        if ($colspan > 1) {
                            $summary['colspanAnchorCount']++;
                        }
                        if ($rowspan > 1) {
                            $summary['rowspanAnchorCount']++;
                        }
                        if (($record['rowspanToEnd'] ?? false) === true) {
                            $summary['rowspanToEndAnchorCount']++;
                        }
                    } elseif ($kind === 'covered') {
                        $rowCoveredSlotCount++;
                        $summary['coveredSlotCount']++;
                    } elseif ($kind === 'missing') {
                        $rowMissingSlotCount++;
                        $summary['missingSlotCount']++;
                    }

                    if (($record['headerCell'] ?? false) === true) {
                        $rowHeaderSlotCount++;
                        $summary['headerSlotCount']++;
                    } elseif ($kind !== 'missing') {
                        $rowDataSlotCount++;
                        $summary['dataSlotCount']++;
                    }
                }

                $summary['rowCount']++;
                if (($entry['header'] ?? false) === true) {
                    $summary['headerRowCount']++;
                } else {
                    $summary['dataRowCount']++;
                }
                $summary['maxVisualWidth'] = max($summary['maxVisualWidth'], count($cells));

                $rows[] = [
                    'section' => $section,
                    'row' => (int) $rowIndex,
                    'globalRow' => $globalRow,
                    'rowRole' => (string) ($entry['rowRole'] ?? $section),
                    'header' => (bool) ($entry['header'] ?? false),
                    'rowHeadColumns' => max(0, (int) ($entry['rowHeadColumns'] ?? 0)),
                    'slotCount' => count($cells),
                    'anchorSlotCount' => $rowAnchorSlotCount,
                    'coveredSlotCount' => $rowCoveredSlotCount,
                    'missingSlotCount' => $rowMissingSlotCount,
                    'headerSlotCount' => $rowHeaderSlotCount,
                    'dataSlotCount' => $rowDataSlotCount,
                    'cells' => $cells,
                ];
            }
        }

        $summary['columnCount'] = $columnCount;
        $summary['hasCoveredSlots'] = $summary['coveredSlotCount'] > 0;
        $summary['hasMissingSlots'] = $summary['missingSlotCount'] > 0;

        return [
            'columnCount' => $columnCount,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function flatGridSlotRecord(string $section, int $rowIndex, int $globalRow, array $slot): array
    {
        $kind = (string) ($slot['kind'] ?? 'missing');
        if (!in_array($kind, ['cell', 'covered', 'missing'], true)) {
            $kind = 'missing';
        }

        $record = [
            'kind' => $kind,
            'section' => $section,
            'row' => $rowIndex,
            'globalRow' => $globalRow,
            'column' => max(0, (int) ($slot['column'] ?? 0)),
            'text' => '',
        ];

        if ($kind === 'missing') {
            return $record;
        }

        $node = $slot['node'] ?? null;
        $anchorRow = max(0, (int) ($slot['anchorRow'] ?? $slot['row'] ?? $rowIndex));
        $anchorColumn = max(0, (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0));
        $sourceCell = max(0, (int) ($slot['sourceCell'] ?? 0));
        $sourceColumn = max(0, (int) ($slot['sourceColumn'] ?? 0));
        $anchorText = $node instanceof AstNode ? self::plainText($node) : '';
        $record = array_replace($record, [
            'anchorKey' => self::accessibilityKey($section, $anchorRow, $sourceCell, $sourceColumn),
            'anchorRow' => $anchorRow,
            'anchorColumn' => $anchorColumn,
            'sourceCell' => $sourceCell,
            'sourceColumn' => $sourceColumn,
            'headerCell' => (bool) ($slot['headerCell'] ?? false),
            'colspan' => max(1, (int) ($slot['colspan'] ?? 1)),
            'rowspan' => max(1, (int) ($slot['rowspan'] ?? 1)),
            'spanColumns' => self::flatGridSpanColumns($slot),
        ]);

        foreach ([
            'sourceRow',
            'sourceRowEnd',
            'sourceRowspan',
            'sourceRowspanAttribute',
            'globalRowEnd',
            'anchorSourceRow',
            'anchorSourceRowEnd',
            'anchorSourceRowspanAttribute',
            'anchorGlobalRow',
            'anchorGlobalRowEnd',
        ] as $attribute) {
            if (isset($slot[$attribute]) && is_numeric($slot[$attribute])) {
                $record[$attribute] = (int) $slot[$attribute];
            }
        }

        foreach (['sourceRowspanMode', 'anchorSourceRowspanMode'] as $attribute) {
            $value = trim((string) ($slot[$attribute] ?? ''));
            if ($value !== '') {
                $record[$attribute] = $value;
            }
        }

        foreach ([
            'sourceRows',
            'sourceRowRange',
            'globalRows',
            'globalRowRange',
            'anchorSourceRows',
            'anchorSourceRowRange',
            'anchorGlobalRows',
            'anchorGlobalRowRange',
        ] as $attribute) {
            $values = self::intList($slot[$attribute] ?? []);
            if ($values !== []) {
                $record[$attribute] = $values;
            }
        }

        if (($slot['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        if ($kind === 'covered') {
            $record['covering'] = (string) ($slot['covering'] ?? '');
            $record['anchorText'] = $anchorText;

            return $record;
        }

        $record['text'] = $anchorText;

        return $record;
    }

    /**
     * @param array<string, mixed> $slot
     * @return list<int>
     */
    private static function flatGridSpanColumns(array $slot): array
    {
        $anchorColumn = max(0, (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0));
        $colspan = max(1, (int) ($slot['colspan'] ?? 1));

        return self::integerRange($anchorColumn, $anchorColumn + $colspan);
    }

    /**
     * @param array{rows?:list<array<string, mixed>>} $flatGrid
     * @return list<array<string, mixed>>
     */
    private static function flatGridFallbackDiagnosticsFromGrid(array $flatGrid): array
    {
        $coveredSlots = [];
        $missingSlots = [];
        $rows = is_array($flatGrid['rows'] ?? null) ? $flatGrid['rows'] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
            foreach ($cells as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $kind = (string) ($slot['kind'] ?? '');
                if ($kind === 'covered') {
                    $coveredSlots[] = self::flatGridFallbackSlotRecord($slot);
                } elseif ($kind === 'missing') {
                    $missingSlots[] = self::flatGridFallbackSlotRecord($slot);
                }
            }
        }

        $diagnostics = [];
        if ($coveredSlots !== []) {
            $diagnostics[] = self::flatGridFallbackRecord(
                'flat-grid-covered-slots-require-anchor-replay',
                'covered-slots',
                'span-anchor-replay',
                $coveredSlots
            );
        }
        if ($missingSlots !== []) {
            $diagnostics[] = self::flatGridFallbackRecord(
                'flat-grid-missing-slots-require-empty-placeholders',
                'missing-slots',
                'empty-cell-placeholders',
                $missingSlots
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function flatGridFallbackSlotRecord(array $slot): array
    {
        $kind = (string) ($slot['kind'] ?? 'missing');
        if (!in_array($kind, ['covered', 'missing'], true)) {
            $kind = 'missing';
        }

        $record = [
            'kind' => $kind,
            'section' => (string) ($slot['section'] ?? ''),
            'row' => max(0, (int) ($slot['row'] ?? 0)),
            'globalRow' => max(0, (int) ($slot['globalRow'] ?? 0)),
            'column' => max(0, (int) ($slot['column'] ?? 0)),
            'text' => is_scalar($slot['text'] ?? null) ? (string) $slot['text'] : '',
        ];

        if (isset($slot['headerCell'])) {
            $record['headerCell'] = (bool) $slot['headerCell'];
        }

        if ($kind === 'missing') {
            return $record;
        }

        foreach (['covering', 'anchorKey', 'anchorText'] as $attribute) {
            if (is_scalar($slot[$attribute] ?? null)) {
                $value = (string) $slot[$attribute];
                if ($value !== '') {
                    $record[$attribute] = $value;
                }
            }
        }

        foreach ([
            'anchorRow',
            'anchorColumn',
            'sourceCell',
            'sourceColumn',
            'colspan',
            'rowspan',
            'sourceRow',
            'sourceRowEnd',
            'sourceRowspan',
            'sourceRowspanAttribute',
            'globalRowEnd',
            'anchorSourceRow',
            'anchorSourceRowEnd',
            'anchorSourceRowspanAttribute',
            'anchorGlobalRow',
            'anchorGlobalRowEnd',
        ] as $attribute) {
            if (is_numeric($slot[$attribute] ?? null)) {
                $record[$attribute] = (int) $slot[$attribute];
            }
        }

        foreach (['sourceRowspanMode', 'anchorSourceRowspanMode'] as $attribute) {
            $value = trim((string) ($slot[$attribute] ?? ''));
            if ($value !== '') {
                $record[$attribute] = $value;
            }
        }

        foreach ([
            'spanColumns',
            'sourceRows',
            'sourceRowRange',
            'globalRows',
            'globalRowRange',
            'anchorSourceRows',
            'anchorSourceRowRange',
            'anchorGlobalRows',
            'anchorGlobalRowRange',
        ] as $attribute) {
            $values = self::intList($slot[$attribute] ?? []);
            if ($values !== []) {
                $record[$attribute] = $values;
            }
        }

        if (($slot['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return array<string, mixed>
     */
    private static function flatGridFallbackRecord(
        string $code,
        string $reason,
        string $requiredFeature,
        array $slots
    ): array {
        $sections = [];
        $rows = [];
        $globalRows = [];
        $columns = [];
        $coverings = [];
        foreach ($slots as $slot) {
            $section = (string) ($slot['section'] ?? '');
            if ($section !== '') {
                $sections[] = $section;
            }
            if (is_numeric($slot['row'] ?? null)) {
                $rows[] = (int) $slot['row'];
            }
            if (is_numeric($slot['globalRow'] ?? null)) {
                $globalRows[] = (int) $slot['globalRow'];
            }
            if (is_numeric($slot['column'] ?? null)) {
                $columns[] = (int) $slot['column'];
            }

            $covering = (string) ($slot['covering'] ?? '');
            if ($covering !== '') {
                $coverings[] = $covering;
            }
        }

        $record = [
            'code' => $code,
            'source' => 'pandoc-flat-grid',
            'reason' => $reason,
            'requiredFeature' => $requiredFeature,
            'slotCount' => count($slots),
            'sections' => array_values(array_unique($sections)),
            'rows' => self::uniqueIntList($rows),
            'globalRows' => self::uniqueIntList($globalRows),
            'columns' => self::uniqueIntList($columns),
            'slots' => $slots,
        ];

        if ($coverings !== []) {
            $record['coverings'] = array_values(array_unique($coverings));
        }

        return $record;
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @param array<string, mixed> $headerAssociations
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function rowMatrixFromAssociations(array $sections, array $headerAssociations): array
    {
        if ($sections === []) {
            return self::emptyRowMatrix();
        }

        $headerCellsByKey = self::associationRecordsByKey($headerAssociations['headerCells'] ?? []);
        $dataCellsByKey = self::associationRecordsByKey($headerAssociations['dataCells'] ?? []);
        $rows = [];
        $summary = self::emptyRowMatrix()['summary'];

        foreach ($sections as $sectionGrid) {
            $section = (string) ($sectionGrid['section'] ?? '');
            if ($section !== '' && !in_array($section, $summary['sections'], true)) {
                $summary['sections'][] = $section;
            }

            $globalRowStart = max(0, (int) ($sectionGrid['globalRowStart'] ?? 0));
            foreach ($sectionGrid['rows'] ?? [] as $rowIndex => $slots) {
                if (!is_array($slots)) {
                    continue;
                }

                $rowEntry = is_array($sectionGrid['rowEntries'][$rowIndex] ?? null)
                    ? $sectionGrid['rowEntries'][$rowIndex]
                    : [];
                $row = self::rowMatrixRowRecord(
                    $section,
                    (int) $rowIndex,
                    $globalRowStart + (int) $rowIndex,
                    $rowEntry,
                    $slots,
                    $headerCellsByKey,
                    $dataCellsByKey
                );
                $rows[] = $row;
                self::appendRowMatrixSummaryRow($summary, $row);
            }
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function associationRecordsByKey(mixed $records): array
    {
        if (!is_array($records)) {
            return [];
        }

        $recordsByKey = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $key = trim((string) ($record['key'] ?? ''));
            if ($key !== '') {
                $recordsByKey[$key] = $record;
            }
        }

        return $recordsByKey;
    }

    /**
     * @param array<string, mixed> $rowEntry
     * @param list<array<string, mixed>> $slots
     * @param array<string, array<string, mixed>> $headerCellsByKey
     * @param array<string, array<string, mixed>> $dataCellsByKey
     * @return array<string, mixed>
     */
    private static function rowMatrixRowRecord(
        string $section,
        int $rowIndex,
        int $fallbackGlobalRow,
        array $rowEntry,
        array $slots,
        array $headerCellsByKey,
        array $dataCellsByKey
    ): array {
        $globalRow = self::rowGlobalRow($slots, $fallbackGlobalRow);
        $rowRole = (string) ($rowEntry['rowRole'] ?? '');
        $headerRow = (bool) ($rowEntry['header'] ?? false);
        $rowHeadColumns = max(0, (int) ($rowEntry['rowHeadColumns'] ?? 0));

        $cells = [];
        $headerCells = [];
        $dataCells = [];
        $coveredSlots = [];
        $missingColumns = [];
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        $maxOccupiedColumn = -1;

        foreach ($slots as $column => $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $kind = (string) ($slot['kind'] ?? '');
            if ($kind === 'missing') {
                $missingSlotCount++;
                $missingColumns[] = (int) ($slot['column'] ?? $column);
                continue;
            }

            $maxOccupiedColumn = max($maxOccupiedColumn, (int) ($slot['column'] ?? $column));
            if ($kind === 'covered') {
                $coveredSlotCount++;
                $coveredSlots[] = self::rowMatrixCoveredSlotRecord($section, $slot);
                continue;
            }

            if ($kind !== 'cell') {
                continue;
            }

            $key = self::accessibilityKey(
                $section,
                (int) $rowIndex,
                (int) ($slot['sourceCell'] ?? 0),
                (int) ($slot['sourceColumn'] ?? 0)
            );
            $headerCell = ($slot['headerCell'] ?? false) === true;
            $association = $headerCell ? ($headerCellsByKey[$key] ?? []) : ($dataCellsByKey[$key] ?? []);
            $cell = self::rowMatrixCellRecord($section, (int) $rowIndex, $globalRow, $slot, $key, $association);
            $cells[] = $cell;
            if ($headerCell) {
                $headerCells[] = $cell;
            } else {
                $dataCells[] = $cell;
            }
        }

        return [
            'section' => $section,
            'row' => $rowIndex,
            'globalRow' => $globalRow,
            'rowRole' => $rowRole,
            'header' => $headerRow,
            'rowHeadColumns' => $rowHeadColumns,
            'slotCount' => count($slots),
            'cellCount' => count($cells),
            'headerCellCount' => count($headerCells),
            'dataCellCount' => count($dataCells),
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'visualWidth' => max(count($slots), $maxOccupiedColumn + 1),
            'complete' => $slots !== [] && $missingSlotCount === 0,
            'cells' => $cells,
            'headerCells' => $headerCells,
            'dataCells' => $dataCells,
            'coveredSlots' => $coveredSlots,
            'missingColumns' => $missingColumns,
        ];
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $association
     * @return array<string, mixed>
     */
    private static function rowMatrixCellRecord(
        string $section,
        int $rowIndex,
        int $globalRow,
        array $slot,
        string $key,
        array $association
    ): array {
        $node = $slot['node'] ?? null;
        $headerCell = ($slot['headerCell'] ?? false) === true;
        $record = [
            'key' => $key,
            'role' => $headerCell ? 'header' : 'data',
            'section' => $section,
            'row' => $rowIndex,
            'globalRow' => $globalRow,
            'rowRole' => (string) ($slot['rowRole'] ?? ''),
            'headerRow' => (bool) ($slot['headerRow'] ?? false),
            'rowHeadColumns' => max(0, (int) ($slot['rowHeadColumns'] ?? 0)),
            'column' => (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0),
            'columns' => self::associationColumns($slot),
            'sourceCell' => (int) ($slot['sourceCell'] ?? 0),
            'sourceColumn' => (int) ($slot['sourceColumn'] ?? 0),
            'colspan' => max(1, (int) ($slot['colspan'] ?? 1)),
            'rowspan' => max(1, (int) ($slot['rowspan'] ?? 1)),
            'headerCell' => $headerCell,
            'text' => $node instanceof AstNode ? self::plainText($node) : '',
        ];

        foreach ([
            'sourceRow',
            'sourceRowEnd',
            'sourceRowspan',
            'sourceRowspanAttribute',
            'globalRowEnd',
            'anchorSourceRow',
            'anchorSourceRowEnd',
            'anchorSourceRowspanAttribute',
            'anchorGlobalRow',
            'anchorGlobalRowEnd',
        ] as $attribute) {
            if (isset($slot[$attribute]) && is_numeric($slot[$attribute])) {
                $record[$attribute] = (int) $slot[$attribute];
            }
        }

        foreach (['sourceRowspanMode', 'anchorSourceRowspanMode'] as $attribute) {
            $value = trim((string) ($slot[$attribute] ?? ''));
            if ($value !== '') {
                $record[$attribute] = $value;
            }
        }

        foreach ([
            'sourceRows',
            'sourceRowRange',
            'globalRows',
            'globalRowRange',
            'anchorSourceRows',
            'anchorSourceRowRange',
            'anchorGlobalRows',
            'anchorGlobalRowRange',
        ] as $attribute) {
            $values = self::intList($slot[$attribute] ?? []);
            if ($values !== []) {
                $record[$attribute] = $values;
            }
        }

        if (($slot['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        if ($headerCell) {
            foreach (['id', 'scope', 'abbr'] as $attribute) {
                $value = trim((string) ($association[$attribute] ?? ''));
                if ($value !== '') {
                    $record[$attribute] = $value;
                }
            }

            $axis = self::stringList($association['axis'] ?? []);
            if ($axis !== []) {
                $record['axis'] = $axis;
            }

            $headers = self::stringList($association['headers'] ?? []);
            if ($headers !== []) {
                $record['headers'] = $headers;
            }
            $associationColumns = self::intList($association['columns'] ?? []);
            if ($associationColumns !== []) {
                $record['columns'] = $associationColumns;
            }
            if (is_array($association['sourceColumnGroup'] ?? null)) {
                $record['sourceColumnGroup'] = $association['sourceColumnGroup'];
            }
            self::appendSourceHeaderAssociationFields($record, $association);

            return $record;
        }

        $headers = self::stringList($association['headers'] ?? []);
        $record['headers'] = $headers;
        $record['headerCount'] = count($headers);
        $record['associated'] = $headers !== [];
        self::appendSourceHeaderAssociationFields($record, $association);

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $association
     */
    private static function appendSourceHeaderAssociationFields(array &$record, array $association): void
    {
        $sourceHeaders = self::stringList($association['sourceHeaders'] ?? []);
        if ($sourceHeaders !== []) {
            $record['sourceHeaders'] = $sourceHeaders;
        }

        $references = $association['sourceHeaderReferences'] ?? [];
        if (is_array($references) && $references !== []) {
            $record['sourceHeaderReferences'] = $references;
        }
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function rowMatrixCoveredSlotRecord(string $section, array $slot): array
    {
        $anchorRow = max(0, (int) ($slot['anchorRow'] ?? $slot['row'] ?? 0));
        $sourceCell = max(0, (int) ($slot['sourceCell'] ?? 0));
        $sourceColumn = max(0, (int) ($slot['sourceColumn'] ?? 0));
        $record = [
            'kind' => 'covered',
            'section' => $section,
            'row' => max(0, (int) ($slot['row'] ?? 0)),
            'globalRow' => max(0, (int) ($slot['globalRow'] ?? 0)),
            'column' => max(0, (int) ($slot['column'] ?? 0)),
            'covering' => (string) ($slot['covering'] ?? ''),
            'anchorRow' => $anchorRow,
            'anchorColumn' => max(0, (int) ($slot['anchorColumn'] ?? 0)),
            'anchorKey' => self::accessibilityKey($section, $anchorRow, $sourceCell, $sourceColumn),
            'sourceCell' => $sourceCell,
            'sourceColumn' => $sourceColumn,
        ];

        foreach ([
            'sourceRow',
            'sourceRowEnd',
            'sourceRowspan',
            'sourceRowspanAttribute',
            'anchorSourceRow',
            'anchorSourceRowEnd',
            'anchorSourceRowspanAttribute',
            'anchorGlobalRow',
            'anchorGlobalRowEnd',
        ] as $attribute) {
            if (isset($slot[$attribute]) && is_numeric($slot[$attribute])) {
                $record[$attribute] = (int) $slot[$attribute];
            }
        }

        foreach (['sourceRowspanMode', 'anchorSourceRowspanMode'] as $attribute) {
            $value = trim((string) ($slot[$attribute] ?? ''));
            if ($value !== '') {
                $record[$attribute] = $value;
            }
        }

        foreach ([
            'sourceRows',
            'sourceRowRange',
            'anchorSourceRows',
            'anchorSourceRowRange',
            'anchorGlobalRows',
            'anchorGlobalRowRange',
        ] as $attribute) {
            $values = self::intList($slot[$attribute] ?? []);
            if ($values !== []) {
                $record[$attribute] = $values;
            }
        }

        if (($slot['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $row
     */
    private static function appendRowMatrixSummaryRow(array &$summary, array $row): void
    {
        $summary['rowCount']++;
        if (($row['header'] ?? false) === true) {
            $summary['headerRowCount']++;
        }

        $rowRole = trim((string) ($row['rowRole'] ?? ''));
        if ($rowRole !== '') {
            $summary['rowRoleCounts'][$rowRole] = ($summary['rowRoleCounts'][$rowRole] ?? 0) + 1;
        }

        $cellCount = max(0, (int) ($row['cellCount'] ?? 0));
        $headerCellCount = max(0, (int) ($row['headerCellCount'] ?? 0));
        $dataCellCount = max(0, (int) ($row['dataCellCount'] ?? 0));
        $coveredSlotCount = max(0, (int) ($row['coveredSlotCount'] ?? 0));
        $missingSlotCount = max(0, (int) ($row['missingSlotCount'] ?? 0));

        if ($dataCellCount > 0) {
            $summary['dataRowCount']++;
            $summary['rowWithDataCellCount']++;
        }
        if ($headerCellCount > 0) {
            $summary['rowWithHeaderCellCount']++;
        }
        if (($row['complete'] ?? false) === true) {
            $summary['completeRowCount']++;
        } else {
            $summary['incompleteRowCount']++;
        }
        if ($coveredSlotCount > 0) {
            $summary['coveredRowCount']++;
        }
        if ($missingSlotCount > 0) {
            $summary['missingRowCount']++;
        }

        $summary['cellCount'] += $cellCount;
        $summary['headerCellCount'] += $headerCellCount;
        $summary['dataCellCount'] += $dataCellCount;
        $summary['coveredSlotCount'] += $coveredSlotCount;
        $summary['missingSlotCount'] += $missingSlotCount;
        $summary['maxCellCountPerRow'] = max($summary['maxCellCountPerRow'], $cellCount);
        $summary['maxHeaderCellsPerRow'] = max($summary['maxHeaderCellsPerRow'], $headerCellCount);
        $summary['maxDataCellsPerRow'] = max($summary['maxDataCellsPerRow'], $dataCellCount);
        $summary['maxVisualWidth'] = max($summary['maxVisualWidth'], max(0, (int) ($row['visualWidth'] ?? 0)));

        foreach (is_array($row['dataCells'] ?? null) ? $row['dataCells'] : [] as $cell) {
            if (!is_array($cell)) {
                continue;
            }
            if (($cell['associated'] ?? false) === true) {
                $summary['associatedDataCellCount']++;
            } else {
                $summary['unassociatedDataCellCount']++;
            }
        }

        $summary['hasHeaderAssociations'] = $summary['associatedDataCellCount'] > 0;
        $summary['hasUnassociatedDataCells'] = $summary['unassociatedDataCellCount'] > 0;
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @param array<string, mixed> $headerAssociations
     * @return array{rows:list<array<string, mixed>>,summary:array<string, mixed>}
     */
    private static function rowHeaderMapFromAssociations(array $sections, array $headerAssociations): array
    {
        $headerCells = is_array($headerAssociations['headerCells'] ?? null)
            ? $headerAssociations['headerCells']
            : [];
        $rowHeaderCells = [];
        foreach ($headerCells as $headerCell) {
            if (!is_array($headerCell)) {
                continue;
            }

            $scope = (string) ($headerCell['scope'] ?? '');
            if ($scope !== 'row' && $scope !== 'rowgroup') {
                continue;
            }

            $rowHeaderCells[] = self::rowHeaderReferenceRecord($headerCell);
        }

        $rows = [];
        $usedRowHeaderKeys = [];
        $rowHeaderScopes = [];
        $rowHeaderReferenceCount = 0;
        $rowspannedRowHeaderReferenceCount = 0;
        $maxRowHeaderCount = 0;
        $labeledDataRowCount = 0;
        $unlabeledDataRowCount = 0;

        foreach ($sections as $sectionGrid) {
            $section = (string) ($sectionGrid['section'] ?? '');
            foreach ($sectionGrid['rows'] ?? [] as $rowIndex => $slots) {
                if (!is_array($slots)) {
                    continue;
                }

                $dataCellCount = 0;
                foreach ($slots as $slot) {
                    if (!is_array($slot) || ($slot['kind'] ?? '') !== 'cell' || ($slot['headerCell'] ?? false) === true) {
                        continue;
                    }

                    $dataCellCount++;
                }

                if ($dataCellCount === 0) {
                    continue;
                }

                $headers = [];
                foreach ($rowHeaderCells as $header) {
                    if ((string) ($header['section'] ?? '') !== $section) {
                        continue;
                    }

                    $scope = (string) ($header['scope'] ?? '');
                    $headerRow = (int) ($header['row'] ?? -1);
                    $rowspan = max(1, (int) ($header['rowspan'] ?? 1));
                    if ($scope === 'row') {
                        $applies = $headerRow === (int) $rowIndex;
                    } elseif (($header['sourceScope'] ?? '') === 'rowgroup') {
                        $applies = true;
                    } else {
                        $applies = (int) $rowIndex >= $headerRow && (int) $rowIndex < $headerRow + $rowspan;
                    }
                    if (!$applies) {
                        continue;
                    }

                    $headers[] = $header;
                    $key = (string) ($header['key'] ?? '');
                    if ($key !== '') {
                        $usedRowHeaderKeys[$key] = true;
                    }
                    $rowHeaderScopes[] = $scope;
                    if ($rowspan > 1) {
                        $rowspannedRowHeaderReferenceCount++;
                    }
                }

                $headerCount = count($headers);
                $rowHeaderReferenceCount += $headerCount;
                $maxRowHeaderCount = max($maxRowHeaderCount, $headerCount);
                if ($headerCount > 0) {
                    $labeledDataRowCount++;
                } else {
                    $unlabeledDataRowCount++;
                }

                $rowEntry = $sectionGrid['rowEntries'][$rowIndex] ?? [];
                $record = [
                    'section' => $section,
                    'row' => (int) $rowIndex,
                    'rowRole' => (string) ($rowEntry['rowRole'] ?? ''),
                    'rowHeadColumns' => (int) ($rowEntry['rowHeadColumns'] ?? 0),
                    'dataCellCount' => $dataCellCount,
                    'headerCount' => $headerCount,
                    'headerIds' => array_values(array_map(
                        static fn (array $header): string => (string) ($header['id'] ?? ''),
                        array_filter($headers, static fn (array $header): bool => (string) ($header['id'] ?? '') !== '')
                    )),
                    'headerTexts' => array_values(array_map(
                        static fn (array $header): string => (string) ($header['text'] ?? ''),
                        array_filter($headers, static fn (array $header): bool => (string) ($header['text'] ?? '') !== '')
                    )),
                    'headers' => $headers,
                    'unlabeled' => $headerCount === 0,
                ];
                $rows[] = $record;
            }
        }

        $rowHeaderScopes = array_values(array_unique($rowHeaderScopes));
        sort($rowHeaderScopes);

        return [
            'rows' => $rows,
            'summary' => [
                'dataRowCount' => count($rows),
                'labeledDataRowCount' => $labeledDataRowCount,
                'unlabeledDataRowCount' => $unlabeledDataRowCount,
                'rowHeaderCellCount' => count($usedRowHeaderKeys),
                'rowHeaderReferenceCount' => $rowHeaderReferenceCount,
                'maxRowHeaderCount' => $maxRowHeaderCount,
                'rowHeaderScopes' => $rowHeaderScopes,
                'hasRowHeaders' => $rowHeaderReferenceCount > 0,
                'hasUnlabeledDataRows' => $unlabeledDataRowCount > 0,
                'hasRowspanRowHeaders' => $rowspannedRowHeaderReferenceCount > 0,
                'rowspannedRowHeaderReferenceCount' => $rowspannedRowHeaderReferenceCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $headerCell
     * @return array<string, mixed>
     */
    private static function rowHeaderReferenceRecord(array $headerCell): array
    {
        $record = [];
        foreach ([
            'key',
            'id',
            'text',
            'scope',
            'sourceScope',
            'section',
            'rowRole',
            'abbr',
        ] as $attribute) {
            $value = $headerCell[$attribute] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $record[$attribute] = (string) $value;
            }
        }

        foreach ([
            'row',
            'column',
            'sourceCell',
            'sourceColumn',
            'colspan',
            'rowspan',
            'rowHeadColumns',
        ] as $attribute) {
            if (isset($headerCell[$attribute]) && is_numeric($headerCell[$attribute])) {
                $record[$attribute] = (int) $headerCell[$attribute];
            }
        }

        $columns = self::intList($headerCell['columns'] ?? []);
        if ($columns !== []) {
            $record['columns'] = $columns;
        }

        $axis = self::stringList($headerCell['axis'] ?? []);
        if ($axis !== []) {
            $record['axis'] = $axis;
        }

        if (($headerCell['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        if (isset($headerCell['sourceAttributes']) && is_array($headerCell['sourceAttributes']) && $headerCell['sourceAttributes'] !== []) {
            $record['sourceAttributes'] = $headerCell['sourceAttributes'];
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $headerCells
     * @param list<array<string, mixed>> $dataCells
     * @return array{
     *     referencingCellCount:int,
     *     referenceCount:int,
     *     resolvedReferenceCount:int,
     *     unresolvedReferenceCount:int,
     *     unresolvedReferences:list<string>,
     *     duplicateSourceHeaderTokenCellCount:int,
     *     duplicateSourceHeaderTokenCount:int,
     *     hasDuplicateSourceHeaderTokens:bool,
     *     duplicateSourceHeaderTokens:list<string>,
     *     ambiguousReferenceCount:int,
     *     ambiguousReferences:list<string>,
     *     duplicateHeaderIdCount:int,
     *     hasDuplicateHeaderIds:bool,
     *     duplicateHeaderIds:list<string>
     * }
     */
    private static function attachSourceHeaderReferences(array &$headerCells, array &$dataCells): array
    {
        $headersById = [];
        foreach ($headerCells as $headerCell) {
            $id = trim((string) ($headerCell['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $headersById[$id][] = self::sourceHeaderTargetRecord($headerCell);
        }

        $duplicateHeaderIds = [];
        foreach ($headersById as $id => $targets) {
            if (count($targets) > 1) {
                $duplicateHeaderIds[] = (string) $id;
            }
        }
        sort($duplicateHeaderIds);

        $summary = [
            'referencingCellCount' => 0,
            'referenceCount' => 0,
            'resolvedReferenceCount' => 0,
            'unresolvedReferenceCount' => 0,
            'unresolvedReferences' => [],
            'duplicateSourceHeaderTokenCellCount' => 0,
            'duplicateSourceHeaderTokenCount' => 0,
            'hasDuplicateSourceHeaderTokens' => false,
            'duplicateSourceHeaderTokens' => [],
            'ambiguousReferenceCount' => 0,
            'ambiguousReferences' => [],
            'duplicateHeaderIdCount' => count($duplicateHeaderIds),
            'hasDuplicateHeaderIds' => $duplicateHeaderIds !== [],
            'duplicateHeaderIds' => $duplicateHeaderIds,
        ];

        foreach ($headerCells as &$headerCell) {
            $sourceHeaders = self::stringList($headerCell['headers'] ?? []);
            if ($sourceHeaders === []) {
                continue;
            }

            $headerCell['sourceHeaders'] = $sourceHeaders;
            $headerCell['sourceHeaderReferences'] = self::sourceHeaderReferenceRecords($sourceHeaders, $headersById, $summary);
        }
        unset($headerCell);

        foreach ($dataCells as &$dataCell) {
            $sourceHeaders = self::stringList($dataCell['sourceHeaders'] ?? []);
            if ($sourceHeaders === []) {
                continue;
            }

            $dataCell['sourceHeaderReferences'] = self::sourceHeaderReferenceRecords($sourceHeaders, $headersById, $summary);
        }
        unset($dataCell);

        $summary['unresolvedReferences'] = array_values(array_unique($summary['unresolvedReferences']));
        $summary['ambiguousReferences'] = array_values(array_unique($summary['ambiguousReferences']));
        $duplicateTokenSummary = self::duplicateSourceHeaderTokenSummary($headerCells, $dataCells);
        $summary['duplicateSourceHeaderTokenCellCount'] = $duplicateTokenSummary['cellCount'];
        $summary['duplicateSourceHeaderTokenCount'] = $duplicateTokenSummary['tokenCount'];
        $summary['hasDuplicateSourceHeaderTokens'] = $duplicateTokenSummary['tokenCount'] > 0;
        $summary['duplicateSourceHeaderTokens'] = $duplicateTokenSummary['tokens'];

        return $summary;
    }

    /**
     * @param array<string, mixed> $headerCell
     * @return array<string, mixed>
     */
    private static function sourceHeaderTargetRecord(array $headerCell): array
    {
        $record = [];
        foreach ([
            'key' => 'targetKey',
            'section' => 'targetSection',
        ] as $source => $target) {
            $value = trim((string) ($headerCell[$source] ?? ''));
            if ($value !== '') {
                $record[$target] = $value;
            }
        }

        foreach ([
            'row' => 'targetRow',
            'column' => 'targetColumn',
            'rowHeadColumns' => 'targetRowHeadColumns',
            'sourceCell' => 'targetSourceCell',
            'sourceColumn' => 'targetSourceColumn',
            'colspan' => 'targetColspan',
            'rowspan' => 'targetRowspan',
            'sourceRow' => 'targetSourceRow',
            'sourceRowEnd' => 'targetSourceRowEnd',
            'sourceRowspan' => 'targetSourceRowspan',
            'sourceRowspanAttribute' => 'targetSourceRowspanAttribute',
            'globalRow' => 'targetGlobalRow',
            'globalRowEnd' => 'targetGlobalRowEnd',
        ] as $source => $target) {
            if (isset($headerCell[$source]) && is_numeric($headerCell[$source])) {
                $record[$target] = (int) $headerCell[$source];
            }
        }

        foreach ([
            'rowRole' => 'targetRowRole',
            'scope' => 'targetScope',
            'text' => 'targetText',
            'sourceRowspanMode' => 'targetSourceRowspanMode',
        ] as $source => $target) {
            $value = trim((string) ($headerCell[$source] ?? ''));
            if ($value !== '') {
                $record[$target] = $value;
            }
        }

        $columns = self::intList($headerCell['columns'] ?? []);
        if ($columns !== []) {
            $record['targetColumns'] = $columns;
        }

        $axis = self::stringList($headerCell['axis'] ?? []);
        if ($axis !== []) {
            $record['targetAxis'] = $axis;
        }

        foreach ([
            'sourceRows' => 'targetSourceRows',
            'sourceRowRange' => 'targetSourceRowRange',
            'globalRows' => 'targetGlobalRows',
            'globalRowRange' => 'targetGlobalRowRange',
        ] as $source => $target) {
            $values = self::intList($headerCell[$source] ?? []);
            if ($values !== []) {
                $record[$target] = $values;
            }
        }

        if (($headerCell['rowspanToEnd'] ?? false) === true) {
            $record['targetRowspanToEnd'] = true;
        }

        return $record;
    }

    /**
     * @param list<string> $sourceHeaders
     * @param array<string, list<array<string, mixed>>> $headersById
     * @param array{
     *     referencingCellCount:int,
     *     referenceCount:int,
     *     resolvedReferenceCount:int,
     *     unresolvedReferenceCount:int,
     *     unresolvedReferences:list<string>,
     *     ambiguousReferenceCount:int,
     *     ambiguousReferences:list<string>
     * } $summary
     * @return list<array<string, mixed>>
     */
    private static function sourceHeaderReferenceRecords(array $sourceHeaders, array $headersById, array &$summary): array
    {
        $summary['referencingCellCount']++;
        $records = [];
        foreach ($sourceHeaders as $sourceHeader) {
            $id = trim($sourceHeader);
            if ($id === '') {
                continue;
            }

            $summary['referenceCount']++;
            $targets = $headersById[$id] ?? [];
            if ($targets !== []) {
                $summary['resolvedReferenceCount']++;
                if (count($targets) > 1) {
                    $summary['ambiguousReferenceCount']++;
                    $summary['ambiguousReferences'][] = $id;
                    $records[] = array_merge([
                        'id' => $id,
                        'resolved' => true,
                        'ambiguous' => true,
                        'targetCount' => count($targets),
                        'targets' => $targets,
                    ], $targets[0]);
                    continue;
                }

                $records[] = array_merge([
                    'id' => $id,
                    'resolved' => true,
                ], $targets[0]);
                continue;
            }

            $summary['unresolvedReferenceCount']++;
            $summary['unresolvedReferences'][] = $id;
            $records[] = [
                'id' => $id,
                'resolved' => false,
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $headerCells
     * @param list<array<string, mixed>> $dataCells
     * @return array{cellCount:int,tokenCount:int,tokens:list<string>}
     */
    private static function duplicateSourceHeaderTokenSummary(array $headerCells, array $dataCells): array
    {
        $cellCount = 0;
        $tokenCount = 0;
        $tokens = [];
        foreach ([...$headerCells, ...$dataCells] as $record) {
            if (!is_array($record)) {
                continue;
            }

            $duplicates = self::stringList($record['duplicateSourceHeaderTokens'] ?? []);
            if ($duplicates === []) {
                continue;
            }

            $cellCount++;
            $tokenCount += max(0, (int) ($record['duplicateSourceHeaderTokenCount'] ?? count($duplicates)));
            array_push($tokens, ...$duplicates);
        }

        return [
            'cellCount' => $cellCount,
            'tokenCount' => $tokenCount,
            'tokens' => array_values(array_unique($tokens)),
        ];
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function headerAssociationCellRecord(string $section, int $rowIndex, array $slot, string $key): array
    {
        $node = $slot['node'] ?? null;
        $record = [
            'key' => $key,
            'section' => $section,
            'row' => $rowIndex,
            'rowRole' => (string) ($slot['rowRole'] ?? ''),
            'headerRow' => (bool) ($slot['headerRow'] ?? false),
            'rowHeadColumns' => (int) ($slot['rowHeadColumns'] ?? 0),
            'column' => (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0),
            'columns' => self::associationColumns($slot),
            'sourceCell' => (int) ($slot['sourceCell'] ?? 0),
            'sourceColumn' => (int) ($slot['sourceColumn'] ?? 0),
            'colspan' => max(1, (int) ($slot['colspan'] ?? 1)),
            'rowspan' => max(1, (int) ($slot['rowspan'] ?? 1)),
            'text' => $node instanceof AstNode ? self::plainText($node) : '',
        ];

        if (($slot['rowspanToEnd'] ?? false) === true) {
            $record['rowspanToEnd'] = true;
        }

        $duplicateSourceHeaderMetadata = self::duplicateSourceHeaderTokenMetadata($node);
        if ($duplicateSourceHeaderMetadata !== []) {
            $record = array_replace($record, $duplicateSourceHeaderMetadata);
        }

        foreach ([
            'sourceRow',
            'sourceRowEnd',
            'sourceRowspan',
            'sourceRowspanAttribute',
            'globalRow',
            'globalRowEnd',
        ] as $attribute) {
            if (isset($slot[$attribute]) && is_numeric($slot[$attribute])) {
                $record[$attribute] = (int) $slot[$attribute];
            }
        }

        $sourceRowspanMode = trim((string) ($slot['sourceRowspanMode'] ?? ''));
        if ($sourceRowspanMode !== '') {
            $record['sourceRowspanMode'] = $sourceRowspanMode;
        }

        foreach ([
            'sourceRows',
            'sourceRowRange',
            'globalRows',
            'globalRowRange',
        ] as $attribute) {
            $values = self::intList($slot[$attribute] ?? []);
            if ($values !== []) {
                $record[$attribute] = $values;
            }
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        $sourceScope = self::cellSourceHtmlScope($node);
        if ($sourceScope !== '') {
            $record['sourceScope'] = $sourceScope;
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $slot
     * @return list<int>
     */
    private static function associationColumns(array $slot): array
    {
        $startColumn = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
        $colspan = max(1, (int) ($slot['colspan'] ?? 1));
        $columns = [];
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $sourceColumnGroup
     * @return list<int>
     */
    private static function accessibilityHeaderColumns(array $slot, string $sourceScope, array $sourceColumnGroup): array
    {
        $columns = self::associationColumns($slot);
        if ($sourceScope !== 'colgroup' || $sourceColumnGroup === []) {
            return $columns;
        }

        $groupColumns = self::intList($sourceColumnGroup['columns'] ?? []);

        return $groupColumns === [] ? $columns : $groupColumns;
    }

    /**
     * @param array<string, mixed> $slot
     * @param list<array<string, mixed>> $columnGroups
     * @return array<string, mixed>
     */
    private static function columnGroupForSlot(array $slot, array $columnGroups): array
    {
        $column = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
        foreach ($columnGroups as $group) {
            $columns = self::intList($group['columns'] ?? []);
            if (in_array($column, $columns, true)) {
                return $group;
            }
        }

        return [];
    }

    private static function cellSourceHtmlId(mixed $node): string
    {
        if (!$node instanceof AstNode) {
            return '';
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['id'])) {
            $id = trim((string) $htmlAttributes['id']);
            if ($id !== '') {
                return $id;
            }
        }

        return trim((string) $node->attr('id', ''));
    }

    private static function cellSourceHtmlScope(mixed $node): string
    {
        $scope = self::cellSourceHtmlScopeRaw($node);

        return self::isSourceHtmlScope($scope) ? $scope : '';
    }

    private static function cellSourceHtmlScopeRaw(mixed $node): string
    {
        return strtolower(self::sourceHtmlAttribute($node, 'scope'));
    }

    private static function isSourceHtmlScope(string $scope): bool
    {
        return in_array($scope, self::SOURCE_HTML_HEADER_SCOPES, true);
    }

    private static function isSourceHtmlScopeValue(string $scope): bool
    {
        return in_array($scope, self::SOURCE_HTML_HEADER_SCOPE_VALUES, true);
    }

    private static function cellSourceHtmlAbbr(mixed $node): string
    {
        return self::sourceHtmlAttribute($node, 'abbr');
    }

    /**
     * @return list<string>
     */
    private static function cellSourceHtmlAxis(mixed $node): array
    {
        $axis = preg_split('/[\s,;]+/', self::sourceHtmlAttribute($node, 'axis'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            static fn (string $value): string => trim($value),
            $axis
        )));
    }

    /**
     * @return list<string>
     */
    private static function cellSourceHtmlHeaders(mixed $node): array
    {
        return array_values(array_unique(self::sourceHeaderTokens($node)));
    }

    /**
     * @return list<string>
     */
    private static function sourceHeaderTokens(mixed $node): array
    {
        $tokens = preg_split('/\s+/', self::sourceHtmlAttribute($node, 'headers'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $headers = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                $headers[] = $token;
            }
        }

        return $headers;
    }

    /**
     * @return array{
     *     sourceHeaderTokenCount:int,
     *     sourceHeaderUniqueTokenCount:int,
     *     duplicateSourceHeaderTokenCount:int,
     *     duplicateSourceHeaderTokens:list<string>
     * }
     */
    private static function duplicateSourceHeaderTokenMetadata(mixed $node): array
    {
        $tokens = self::sourceHeaderTokens($node);
        if ($tokens === []) {
            return [];
        }

        $uniqueTokens = array_values(array_unique($tokens));
        $duplicateTokens = self::duplicateStringValues($tokens);
        if ($duplicateTokens === []) {
            return [];
        }

        return [
            'sourceHeaderTokenCount' => count($tokens),
            'sourceHeaderUniqueTokenCount' => count($uniqueTokens),
            'duplicateSourceHeaderTokenCount' => count($tokens) - count($uniqueTokens),
            'duplicateSourceHeaderTokens' => $duplicateTokens,
        ];
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function duplicateStringValues(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (!isset($seen[$value])) {
                $seen[$value] = 1;
                continue;
            }

            $seen[$value]++;
            if ($seen[$value] === 2) {
                $duplicates[] = $value;
            }
        }

        return $duplicates;
    }

    private static function sourceHtmlAttribute(mixed $node, string $name): string
    {
        if (!$node instanceof AstNode) {
            return '';
        }

        foreach (['htmlAttributes', 'attributes'] as $attributeName) {
            $attributes = $node->attr($attributeName, []);
            if (!is_array($attributes)) {
                continue;
            }

            $attributes = array_change_key_case($attributes, CASE_LOWER);
            if (isset($attributes[$name]) && is_scalar($attributes[$name])) {
                return trim((string) $attributes[$name]);
            }
        }

        return '';
    }

    /**
     * @return array{present:bool,value:string,source:string}
     */
    private static function sourceHtmlAttributePresence(mixed $node, string $name): array
    {
        if (!$node instanceof AstNode) {
            return ['present' => false, 'value' => '', 'source' => ''];
        }

        $name = strtolower(trim($name));
        if ($name === '') {
            return ['present' => false, 'value' => '', 'source' => ''];
        }

        foreach (['htmlAttributes', 'attributes'] as $attributeName) {
            $attributes = $node->attr($attributeName, []);
            if (!is_array($attributes)) {
                continue;
            }

            foreach ($attributes as $attributeKey => $attributeValue) {
                if (
                    strtolower(trim((string) $attributeKey)) !== $name
                    || !is_scalar($attributeValue)
                ) {
                    continue;
                }

                return [
                    'present' => true,
                    'value' => trim((string) $attributeValue),
                    'source' => $attributeName,
                ];
            }
        }

        return ['present' => false, 'value' => '', 'source' => ''];
    }

    private static function sourceDirection(mixed $node): string
    {
        return self::normalizeTableDirectionAttribute(self::sourceHtmlAttribute($node, 'dir'));
    }

    /**
     * @return array{language?:string,attribute?:string,xmlLanguage?:string,xmlAttribute?:string}
     */
    private static function sourceLanguageRecord(mixed $node): array
    {
        $language = '';
        $attribute = '';
        $lang = self::sourceHtmlAttributePresence($node, 'lang');
        if (($lang['present'] ?? false) === true) {
            $normalized = self::normalizeTableLanguageAttribute((string) ($lang['value'] ?? ''));
            if ($normalized !== '') {
                $language = $normalized;
                $attribute = 'lang';
            }
        }

        $xmlLanguage = '';
        $xml = self::sourceHtmlAttributePresence($node, 'xml:lang');
        if (($xml['present'] ?? false) === true) {
            $xmlLanguage = self::normalizeTableLanguageAttribute((string) ($xml['value'] ?? ''));
            if ($language === '' && $xmlLanguage !== '') {
                $language = $xmlLanguage;
                $attribute = 'xml:lang';
            }
        }

        if ($language === '' && $xmlLanguage === '') {
            return [];
        }

        $record = [];
        if ($language !== '') {
            $record['language'] = $language;
            $record['attribute'] = $attribute;
        }
        if ($xmlLanguage !== '') {
            $record['xmlLanguage'] = $xmlLanguage;
            $record['xmlAttribute'] = 'xml:lang';
        }

        return $record;
    }

    /**
     * @return array{translate?:string,translateAttribute?:string}
     */
    private static function sourceTranslateRecord(mixed $node): array
    {
        $translate = self::sourceHtmlAttributePresence($node, 'translate');
        if (($translate['present'] ?? false) !== true) {
            return [];
        }

        $state = self::normalizeTableTranslateAttribute((string) ($translate['value'] ?? ''));
        if ($state === '') {
            return [];
        }

        return [
            'translate' => $state,
            'translateAttribute' => 'translate',
        ];
    }

    private static function normalizeTableDirectionAttribute(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['ltr', 'rtl', 'auto'], true) ? $value : '';
    }

    private static function normalizeTableLanguageAttribute(string $value): string
    {
        $tag = trim($value);
        if ($tag === '' || strlen($tag) > 64 || preg_match('/\s/u', $tag) === 1) {
            return '';
        }

        if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
            return '';
        }

        $parts = explode('-', $tag);
        if (count($parts) === 1 && strlen($parts[0]) === 1) {
            return '';
        }

        $normalized = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || strtolower($parts[0]) === 'x') {
                $normalized[] = strtolower($part);
                continue;
            }

            if (preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $normalized[] = ucfirst(strtolower($part));
                continue;
            }

            if (preg_match('/^[A-Za-z]{2}$/', $part) === 1) {
                $normalized[] = strtoupper($part);
                continue;
            }

            $normalized[] = strtolower($part);
        }

        return implode('-', $normalized);
    }

    private static function normalizeTableTranslateAttribute(string $value): string
    {
        $state = strtolower(trim($value));
        if ($state === '') {
            return 'yes';
        }

        return in_array($state, ['yes', 'no'], true) ? $state : '';
    }

    /**
     * @param array{table?:string,section?:string,row?:string,cell?:string} $directions
     * @return array{0:string,1:string}
     */
    private static function effectiveDirection(array $directions): array
    {
        foreach (['cell', 'row', 'section', 'table'] as $source) {
            $direction = self::normalizeTableDirectionAttribute((string) ($directions[$source] ?? ''));
            if ($direction !== '') {
                return [$direction, $source];
            }
        }

        return ['', ''];
    }

    /**
     * @param array<string, array<string, mixed>> $records
     * @return array{0:string,1:string,2:string}
     */
    private static function effectiveLocalizationValue(array $records, string $valueKey, string $attributeKey): array
    {
        foreach (['cell', 'row', 'section', 'table'] as $source) {
            $record = $records[$source] ?? [];
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$valueKey] ?? ''));
            if ($value === '') {
                continue;
            }

            return [$value, $source, trim((string) ($record[$attributeKey] ?? ''))];
        }

        return ['', '', ''];
    }

    /**
     * @return array{text:string,source:string,attribute:string}
     */
    private static function sourceSummaryRecord(AstNode $table): array
    {
        $summary = self::sourceHtmlAttribute($table, 'summary');
        if ($summary === '') {
            return [];
        }

        return [
            'text' => $summary,
            'source' => 'html-table-summary',
            'attribute' => 'summary',
        ];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<array<string, mixed>> $coverage
     * @return array{
     *     table:array<string, mixed>,
     *     sections:list<array<string, mixed>>,
     *     rows:list<array<string, mixed>>,
     *     cells:list<array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private static function directionalityMetadata(AstNode $table, array $sections, array $coverage): array
    {
        $tableRecord = [];
        $sectionRecords = [];
        $rowRecords = [];
        $cellRecords = [];
        $directions = [];

        $tableDirection = self::sourceDirection($table);
        if ($tableDirection !== '') {
            $tableRecord = [
                'source' => 'html-table-dir',
                'attribute' => 'dir',
                'direction' => $tableDirection,
            ];
            $directions[] = $tableDirection;
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionName = (string) ($section['section'] ?? '');
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $rowRange = self::sectionGridRowRange($section, $globalRowStart, count($rowEntries));
            $sectionDirection = self::sourceDirection($section['node'] ?? null);
            if ($sectionDirection !== '') {
                $sectionRecords[] = [
                    'source' => 'html-table-section-dir',
                    'attribute' => 'dir',
                    'section' => $sectionName,
                    'direction' => $sectionDirection,
                    'globalRowStart' => $rowRange[0],
                    'globalRowEnd' => $rowRange[1],
                    'rowRange' => $rowRange,
                    'rowCount' => max(0, $rowRange[1] - $rowRange[0]),
                ];
                $directions[] = $sectionDirection;
            }

            foreach ($rowEntries as $rowIndex => $rowEntry) {
                if (!is_array($rowEntry)) {
                    continue;
                }

                $rowDirection = self::sourceDirection($rowEntry['row'] ?? null);
                if ($rowDirection === '') {
                    continue;
                }

                $rowRecords[] = [
                    'source' => 'html-table-row-dir',
                    'attribute' => 'dir',
                    'section' => $sectionName,
                    'rowRole' => (string) ($rowEntry['rowRole'] ?? $sectionName),
                    'row' => (int) $rowIndex,
                    'globalRow' => $globalRowStart + (int) $rowIndex,
                    'direction' => $rowDirection,
                ];
                $directions[] = $rowDirection;
            }
        }

        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $direction = self::normalizeTableDirectionAttribute((string) ($record['direction'] ?? ''));
            if ($direction === '') {
                continue;
            }

            $node = $record['node'] ?? null;
            $cell = [
                'source' => (string) ($record['directionSource'] ?? 'cell'),
                'attribute' => 'dir',
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => max(0, (int) ($record['column'] ?? 0)),
                'sourceCell' => max(0, (int) ($record['sourceCell'] ?? 0)),
                'sourceColumn' => max(0, (int) ($record['sourceColumn'] ?? 0)),
                'direction' => $direction,
                'text' => $node instanceof AstNode ? self::plainText($node) : (string) ($record['text'] ?? ''),
            ];

            foreach (['columns', 'sourceColumns', 'globalRows', 'sourceRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            foreach (['colspan', 'rowspan', 'rawColspan', 'rawRowspan', 'sourceRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = max(0, (int) $record[$key]);
                }
            }

            foreach (['sourceDirection', 'rowDirection', 'sectionDirection', 'tableDirection'] as $key) {
                $value = self::normalizeTableDirectionAttribute((string) ($record[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            $cellRecords[] = $cell;
            $directions[] = $direction;
        }

        $directions = array_values(array_unique(array_filter(
            $directions,
            static fn (string $direction): bool => $direction !== ''
        )));
        sort($directions);
        $explicitCellDirectionCount = 0;
        foreach ($cellRecords as $cell) {
            if (($cell['source'] ?? '') === 'cell') {
                $explicitCellDirectionCount++;
            }
        }

        $directionRecordCount = ($tableRecord === [] ? 0 : 1)
            + count($sectionRecords)
            + count($rowRecords)
            + count($cellRecords);
        $summary = [
            'hasDirectionality' => $directionRecordCount > 0,
            'directionRecordCount' => $directionRecordCount,
            'directions' => $directions,
            'hasTableDirection' => $tableRecord !== [],
            'sectionDirectionCount' => count($sectionRecords),
            'rowDirectionCount' => count($rowRecords),
            'directionalCellCount' => count($cellRecords),
            'explicitCellDirectionCount' => $explicitCellDirectionCount,
            'inheritedCellDirectionCount' => count($cellRecords) - $explicitCellDirectionCount,
        ];

        return [
            'table' => $tableRecord,
            'sections' => $sectionRecords,
            'rows' => $rowRecords,
            'cells' => $cellRecords,
            'summary' => $summary,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<array<string, mixed>> $coverage
     * @return array{
     *     table:array<string, mixed>,
     *     sections:list<array<string, mixed>>,
     *     rows:list<array<string, mixed>>,
     *     cells:list<array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private static function localizationMetadata(AstNode $table, array $sections, array $coverage): array
    {
        $tableRecord = self::localizationSourceRecord(
            self::sourceLanguageRecord($table),
            self::sourceTranslateRecord($table),
            [
                'source' => 'html-table-localization',
                'element' => 'table',
            ]
        );
        $sectionRecords = [];
        $rowRecords = [];
        $cellRecords = [];
        $languages = [];
        $translateStates = [];

        self::collectLocalizationRecordValues($tableRecord, $languages, $translateStates);

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionName = (string) ($section['section'] ?? '');
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $rowRange = self::sectionGridRowRange($section, $globalRowStart, count($rowEntries));
            $sectionRecord = self::localizationSourceRecord(
                self::sourceLanguageRecord($section['node'] ?? null),
                self::sourceTranslateRecord($section['node'] ?? null),
                [
                    'source' => 'html-table-section-localization',
                    'element' => 'table-section',
                    'section' => $sectionName,
                    'globalRowStart' => $rowRange[0],
                    'globalRowEnd' => $rowRange[1],
                    'rowRange' => $rowRange,
                    'rowCount' => max(0, $rowRange[1] - $rowRange[0]),
                ]
            );
            if ($sectionRecord !== []) {
                $sectionRecords[] = $sectionRecord;
                self::collectLocalizationRecordValues($sectionRecord, $languages, $translateStates);
            }

            foreach ($rowEntries as $rowIndex => $rowEntry) {
                if (!is_array($rowEntry)) {
                    continue;
                }

                $rowRecord = self::localizationSourceRecord(
                    self::sourceLanguageRecord($rowEntry['row'] ?? null),
                    self::sourceTranslateRecord($rowEntry['row'] ?? null),
                    [
                        'source' => 'html-table-row-localization',
                        'element' => 'table-row',
                        'section' => $sectionName,
                        'rowRole' => (string) ($rowEntry['rowRole'] ?? $sectionName),
                        'row' => (int) $rowIndex,
                        'globalRow' => $globalRowStart + (int) $rowIndex,
                    ]
                );
                if ($rowRecord === []) {
                    continue;
                }

                $rowRecords[] = $rowRecord;
                self::collectLocalizationRecordValues($rowRecord, $languages, $translateStates);
            }
        }

        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $language = self::normalizeTableLanguageAttribute((string) ($record['language'] ?? ''));
            $translate = isset($record['translate'])
                ? self::normalizeTableTranslateAttribute((string) $record['translate'])
                : '';
            if ($language === '' && $translate === '') {
                continue;
            }

            $node = $record['node'] ?? null;
            $cell = [
                'source' => 'html-table-cell-localization',
                'element' => 'table-cell',
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => max(0, (int) ($record['column'] ?? 0)),
                'sourceCell' => max(0, (int) ($record['sourceCell'] ?? 0)),
                'sourceColumn' => max(0, (int) ($record['sourceColumn'] ?? 0)),
                'text' => $node instanceof AstNode ? self::plainText($node) : (string) ($record['text'] ?? ''),
            ];

            if ($language !== '') {
                $cell['language'] = $language;
                $cell['languageSource'] = (string) ($record['languageSource'] ?? '');
                $cell['languageAttribute'] = (string) ($record['languageAttribute'] ?? '');
                foreach (['sourceLanguage', 'rowLanguage', 'sectionLanguage', 'tableLanguage'] as $key) {
                    $value = self::normalizeTableLanguageAttribute((string) ($record[$key] ?? ''));
                    if ($value !== '') {
                        $cell[$key] = $value;
                    }
                }
            }

            if ($translate !== '') {
                $cell['translate'] = $translate;
                $cell['translateSource'] = (string) ($record['translateSource'] ?? '');
                $cell['translateAttribute'] = (string) ($record['translateAttribute'] ?? '');
                foreach (['sourceTranslate', 'rowTranslate', 'sectionTranslate', 'tableTranslate'] as $key) {
                    if (!isset($record[$key])) {
                        continue;
                    }

                    $value = self::normalizeTableTranslateAttribute((string) $record[$key]);
                    if ($value !== '') {
                        $cell[$key] = $value;
                    }
                }
            }

            foreach (['columns', 'sourceColumns', 'globalRows', 'sourceRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            foreach (['colspan', 'rowspan', 'rawColspan', 'rawRowspan', 'sourceRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = max(0, (int) $record[$key]);
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            $cellRecords[] = $cell;
            self::collectLocalizationRecordValues($cell, $languages, $translateStates);
        }

        $languages = self::sortedUniqueStrings($languages);
        $translateStates = self::sortedUniqueStrings($translateStates);
        $explicitCellLanguageCount = 0;
        $translatedCellCount = 0;
        foreach ($cellRecords as $cell) {
            if (($cell['languageSource'] ?? '') === 'cell') {
                $explicitCellLanguageCount++;
            }
            if (isset($cell['translate'])) {
                $translatedCellCount++;
            }
        }

        $localizationRecordCount = ($tableRecord === [] ? 0 : 1)
            + count($sectionRecords)
            + count($rowRecords)
            + count($cellRecords);
        $summary = [
            'hasLocalization' => $localizationRecordCount > 0,
            'localizationRecordCount' => $localizationRecordCount,
            'languages' => $languages,
            'translateStates' => $translateStates,
            'hasTableLanguage' => isset($tableRecord['language']),
            'hasTableTranslate' => isset($tableRecord['translate']),
            'sectionLocalizationCount' => count($sectionRecords),
            'rowLocalizationCount' => count($rowRecords),
            'localizedCellCount' => count($cellRecords),
            'explicitCellLanguageCount' => $explicitCellLanguageCount,
            'inheritedCellLanguageCount' => count($cellRecords) - $explicitCellLanguageCount,
            'translatedCellCount' => $translatedCellCount,
        ];

        return [
            'table' => $tableRecord,
            'sections' => $sectionRecords,
            'rows' => $rowRecords,
            'cells' => $cellRecords,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, mixed> $language
     * @param array<string, mixed> $translate
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private static function localizationSourceRecord(array $language, array $translate, array $base): array
    {
        $record = $base;
        $hasLocalization = false;
        $languageValue = (string) ($language['language'] ?? '');
        if ($languageValue !== '') {
            $record['language'] = $languageValue;
            $record['attribute'] = (string) ($language['attribute'] ?? '');
            $hasLocalization = true;
        }
        $xmlLanguage = (string) ($language['xmlLanguage'] ?? '');
        if ($xmlLanguage !== '') {
            $record['xmlLanguage'] = $xmlLanguage;
            $record['xmlAttribute'] = (string) ($language['xmlAttribute'] ?? 'xml:lang');
            $hasLocalization = true;
        }
        $translateValue = (string) ($translate['translate'] ?? '');
        if ($translateValue !== '') {
            $record['translate'] = $translateValue;
            $record['translateAttribute'] = (string) ($translate['translateAttribute'] ?? 'translate');
            $hasLocalization = true;
        }

        return $hasLocalization ? $record : [];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $languages
     * @param list<string> $translateStates
     */
    private static function collectLocalizationRecordValues(array $record, array &$languages, array &$translateStates): void
    {
        foreach (['language', 'xmlLanguage', 'sourceLanguage', 'rowLanguage', 'sectionLanguage', 'tableLanguage'] as $key) {
            $language = self::normalizeTableLanguageAttribute((string) ($record[$key] ?? ''));
            if ($language !== '') {
                $languages[] = $language;
            }
        }

        foreach (['translate', 'sourceTranslate', 'rowTranslate', 'sectionTranslate', 'tableTranslate'] as $key) {
            if (!isset($record[$key])) {
                continue;
            }

            $translate = self::normalizeTableTranslateAttribute((string) $record[$key]);
            if ($translate !== '') {
                $translateStates[] = $translate;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyDirectionalitySummary(): array
    {
        return [
            'hasDirectionality' => false,
            'directionRecordCount' => 0,
            'directions' => [],
            'hasTableDirection' => false,
            'sectionDirectionCount' => 0,
            'rowDirectionCount' => 0,
            'directionalCellCount' => 0,
            'explicitCellDirectionCount' => 0,
            'inheritedCellDirectionCount' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyLocalizationSummary(): array
    {
        return [
            'hasLocalization' => false,
            'localizationRecordCount' => 0,
            'languages' => [],
            'translateStates' => [],
            'hasTableLanguage' => false,
            'hasTableTranslate' => false,
            'sectionLocalizationCount' => 0,
            'rowLocalizationCount' => 0,
            'localizedCellCount' => 0,
            'explicitCellLanguageCount' => 0,
            'inheritedCellLanguageCount' => 0,
            'translatedCellCount' => 0,
        ];
    }

    private static function normalizeVerticalAlignment(string $alignment): string
    {
        return match (strtolower(trim($alignment))) {
            'baseline' => 'baseline',
            'top' => 'top',
            'middle', 'center' => 'middle',
            'bottom' => 'bottom',
            default => 'default',
        };
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @return list<array<string, mixed>>
     */
    private static function serializableSectionGrids(array $sections): array
    {
        $reports = [];
        foreach ($sections as $section) {
            $rows = [];
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            foreach ($section['rowEntries'] as $rowIndex => $entry) {
                $slots = [];
                foreach ($section['rows'][$rowIndex] ?? [] as $slot) {
                    $slots[] = self::serializableGridSlot($slot);
                }

                $rowRecord = [
                    'row' => $rowIndex,
                    'globalRow' => $globalRowStart + (int) $rowIndex,
                    'rowRole' => (string) $entry['rowRole'],
                    'header' => (bool) $entry['header'],
                    'rowHeadColumns' => (int) $entry['rowHeadColumns'],
                    'slots' => $slots,
                ];
                $sourceAttributes = self::sourceAttributeSummary($entry['row'] ?? null);
                if ($sourceAttributes !== []) {
                    $rowRecord['sourceAttributes'] = $sourceAttributes;
                }

                $rows[] = $rowRecord;
            }

            $report = [
                'section' => (string) $section['section'],
                'columnCount' => (int) $section['columnCount'],
                'globalRowStart' => $globalRowStart,
                'globalRowEnd' => max($globalRowStart, (int) ($section['globalRowEnd'] ?? ($globalRowStart + count($rows)))),
                'rowRange' => self::sectionGridRowRange($section, $globalRowStart, count($rows)),
                'rowCount' => count($rows),
                'summary' => self::sectionGridSummary($section['rows'], $globalRowStart),
                'rows' => $rows,
            ];
            $sourceAttributes = self::sourceAttributeSummary($section['node'] ?? null);
            if ($sourceAttributes !== []) {
                $report['sourceAttributes'] = $sourceAttributes;
            }

            $reports[] = $report;
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $section
     * @return list<int>
     */
    private static function sectionGridRowRange(array $section, int $globalRowStart, int $rowCount): array
    {
        $range = $section['rowRange'] ?? null;
        if (is_array($range) && array_key_exists(0, $range) && array_key_exists(1, $range)) {
            $start = max(0, (int) $range[0]);

            return [$start, max($start, (int) $range[1])];
        }

        $globalRowEnd = array_key_exists('globalRowEnd', $section)
            ? max($globalRowStart, (int) $section['globalRowEnd'])
            : $globalRowStart + max(0, $rowCount);

        return [$globalRowStart, $globalRowEnd];
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function serializableGridSlot(array $slot): array
    {
        $node = $slot['node'] ?? null;
        unset($slot['node']);
        if ($node instanceof AstNode) {
            $slot['text'] = self::plainText($node);
            $sourceAttributes = self::sourceAttributeSummary($node);
            if ($sourceAttributes !== []) {
                $slot['sourceAttributes'] = $sourceAttributes;
            }
            $content = self::cellContentSummary($node);
            if ($content !== []) {
                $slot['content'] = $content;
            }
        }

        return $slot;
    }

    /**
     * @return array{long:array<string, mixed>,short:array<string, mixed>}
     */
    private static function captionMetadata(AstNode $table): array
    {
        return [
            'long' => self::captionRecord($table, 'caption', 'captionInlines', 'captionBlocks'),
            'short' => self::captionRecord($table, 'shortCaption', 'shortCaptionInlines', 'shortCaptionBlocks'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function captionRecord(AstNode $table, string $textAttribute, string $inlineAttribute, string $blockAttribute): array
    {
        $rawText = trim((string) $table->attr($textAttribute, ''));
        $blocks = self::blockNodes($table->attr($blockAttribute, []));
        if ($blocks !== []) {
            $inlines = [];
            $text = self::plainTextFromBlockNodes($blocks);
            $source = $blockAttribute;
        } else {
            $inlines = self::inlineNodes($table->attr($inlineAttribute, []));
            $inlineText = self::plainTextFromNodes($inlines);
            $text = $inlines === [] ? $rawText : $inlineText;
            $source = $inlines === [] ? ($rawText === '' ? 'none' : $textAttribute) : $inlineAttribute;
        }

        $record = [
            'text' => $text,
            'source' => $source,
            'inlineCount' => count($inlines),
            'inlineTypes' => self::inlineTypes($inlines),
            'hasInlineFormatting' => self::inlinesHaveFormatting($inlines),
            'inlines' => self::serializableInlines($inlines),
            'blockCount' => count($blocks),
            'blockTypes' => self::blockTypes($blocks),
            'hasBlockContent' => $blocks !== [],
            'blocks' => self::serializableBlocks($blocks),
        ];
        if ($rawText !== '' && $rawText !== $text) {
            $record['rawText'] = $rawText;
        }
        if ($textAttribute === 'caption') {
            $captionSource = self::captionSourceMetadata($table);
            if ($captionSource !== []) {
                $record = array_replace($record, $captionSource);
            }
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private static function captionSourceMetadata(AstNode $table): array
    {
        $source = $table->attr('captionSource', []);
        if (!is_array($source)) {
            return [];
        }

        $record = [];
        $element = trim((string) ($source['element'] ?? ''));
        if ($element !== '') {
            $record['sourceElement'] = $element;
        }

        $position = trim((string) ($source['position'] ?? ''));
        if ($position !== '') {
            $record['sourcePosition'] = $position;
        }

        if (isset($source['childIndex']) && is_numeric($source['childIndex'])) {
            $record['sourceChildIndex'] = max(0, (int) $source['childIndex']);
        }

        $captionSide = trim((string) ($source['captionSide'] ?? ''));
        if ($captionSide !== '') {
            $record['captionSide'] = $captionSide;
            $captionSideSource = trim((string) ($source['captionSideSource'] ?? ''));
            if ($captionSideSource !== '') {
                $record['captionSideSource'] = $captionSideSource;
            }
            $record['captionSideSupported'] = self::captionSideSupported($captionSide);
            $captionPlacement = self::captionPlacementFromSide($captionSide);
            if ($captionPlacement !== '') {
                $record['captionPlacement'] = $captionPlacement;
                $record['captionBeforeTable'] = $captionPlacement === 'before-table';
                $record['captionAfterTable'] = $captionPlacement === 'after-table';
            } else {
                $record['captionSideReviewRequired'] = true;
                $record['captionPlacement'] = 'after-table';
                $record['captionPlacementFallback'] = 'after-table';
                $record['captionBeforeTable'] = false;
                $record['captionAfterTable'] = true;
            }
        }

        $sourceAttributes = self::captionSourceAttributeSummary($source['sourceAttributes'] ?? []);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    private static function captionPlacementFromSide(string $captionSide): string
    {
        return match (strtolower(trim($captionSide))) {
            'top' => 'before-table',
            'bottom' => 'after-table',
            default => '',
        };
    }

    private static function captionSideSupported(string $captionSide): bool
    {
        return in_array(strtolower(trim($captionSide)), ['top', 'bottom'], true);
    }

    /**
     * @return array{id?:string,classes?:list<string>,attributes?:array<string, string>,htmlAttributes?:array<string, string>}
     */
    private static function captionSourceAttributeSummary(mixed $sourceAttributes): array
    {
        if (!is_array($sourceAttributes)) {
            return [];
        }

        $summary = [];
        $id = trim((string) ($sourceAttributes['id'] ?? ''));
        if ($id === '' && isset($sourceAttributes['htmlAttributes']) && is_array($sourceAttributes['htmlAttributes'])) {
            $id = trim((string) ($sourceAttributes['htmlAttributes']['id'] ?? ''));
        }
        if ($id !== '') {
            $summary['id'] = $id;
        }

        $classes = [];
        if (isset($sourceAttributes['classes']) && is_array($sourceAttributes['classes'])) {
            foreach ($sourceAttributes['classes'] as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
        if ($classes === [] && isset($sourceAttributes['htmlAttributes']) && is_array($sourceAttributes['htmlAttributes'])) {
            $classes = preg_split('/\s+/', trim((string) ($sourceAttributes['htmlAttributes']['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if ($classes !== []) {
            $summary['classes'] = array_values(array_unique($classes));
        }

        $attributes = self::stringAttributeMap($sourceAttributes['attributes'] ?? [], false);
        if ($attributes !== []) {
            $summary['attributes'] = $attributes;
        }

        $htmlAttributes = self::stringAttributeMap($sourceAttributes['htmlAttributes'] ?? [], true);
        if ($htmlAttributes !== []) {
            $summary['htmlAttributes'] = $htmlAttributes;
        }

        return $summary;
    }

    /**
     * @return list<AstNode>
     */
    private static function inlineNodes(mixed $nodes): array
    {
        if (!is_array($nodes)) {
            return [];
        }

        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof AstNode));
    }

    /**
     * @return list<AstNode>
     */
    private static function blockNodes(mixed $nodes): array
    {
        if (!is_array($nodes)) {
            return [];
        }

        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof AstNode));
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private static function inlineTypes(array $nodes): array
    {
        $types = [];
        foreach ($nodes as $node) {
            $type = trim($node->type);
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private static function blockTypes(array $nodes): array
    {
        $types = [];
        foreach ($nodes as $node) {
            $type = trim($node->type);
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function inlinesHaveFormatting(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!in_array($node->type, ['text', 'space', 'softbreak', 'linebreak'], true)) {
                return true;
            }

            if (self::inlinesHaveFormatting($node->children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private static function serializableInlines(array $nodes): array
    {
        $records = [];
        foreach ($nodes as $node) {
            $records[] = self::serializableInline($node);
        }

        return $records;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private static function serializableBlocks(array $nodes): array
    {
        $records = [];
        foreach ($nodes as $node) {
            $records[] = self::serializableBlock($node);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializableBlock(AstNode $node): array
    {
        $record = [
            'type' => $node->type,
            'text' => self::plainText($node),
        ];

        foreach (['level', 'start', 'style', 'format', 'html', 'tex'] as $attribute) {
            $value = $node->attr($attribute, null);
            if (is_scalar($value) && (string) $value !== '') {
                $record[$attribute] = is_int($value) ? $value : (string) $value;
            }
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        if ($node->children === []) {
            return $record;
        }

        if (self::childrenAreInlineNodes($node->children)) {
            $record['inlines'] = self::serializableInlines($node->children);
        } else {
            $record['children'] = self::serializableBlocks($node->children);
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellContentSummary(AstNode $cell): array
    {
        if ($cell->children === []) {
            return [];
        }

        $inlines = [];
        $blocks = [];
        foreach ($cell->children as $child) {
            if (self::isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            $blocks[] = $child;
        }

        if ($blocks === []) {
            return [];
        }

        $record = [
            'text' => self::plainTextFromBlockNodes($blocks),
            'childCount' => count($cell->children),
            'inlineCount' => count($inlines),
            'inlineTypes' => self::inlineTypes($inlines),
            'hasInlineContent' => $inlines !== [],
            'hasInlineFormatting' => self::inlinesHaveFormatting($inlines),
            'blockCount' => count($blocks),
            'blockTypes' => self::blockTypes($blocks),
            'hasBlockContent' => true,
            'hasMixedInlineAndBlockContent' => $inlines !== [],
            'blocks' => self::serializableBlocks($blocks),
        ];

        if ($inlines !== []) {
            $record['inlines'] = self::serializableInlines($inlines);
        }

        return $record;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function childrenAreInlineNodes(array $nodes): bool
    {
        if ($nodes === []) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!self::isInlineNode($node)) {
                return false;
            }
        }

        return true;
    }

    private static function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'small_caps',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'space',
            'softbreak',
            'linebreak',
            'span',
            'quoted',
            'math',
            'raw_tex',
            'raw_html_inline',
            'code',
            'link',
            'image',
            'note',
            'citation',
            'citation_group',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializableInline(AstNode $node): array
    {
        $record = [
            'type' => $node->type,
            'text' => self::plainText($node),
        ];
        foreach (['url', 'title', 'alt', 'tex', 'format', 'html'] as $attribute) {
            $value = $node->attr($attribute, null);
            if (is_scalar($value) && (string) $value !== '') {
                $record[$attribute] = (string) $value;
            }
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }
        if ($node->children !== []) {
            $record['children'] = self::serializableInlines($node->children);
        }

        return $record;
    }

    /**
     * @param list<list<array<string, mixed>>> $rows
     * @return array<string, mixed>
     */
    private static function sectionGridSummary(array $rows, int $globalRowStart = 0): array
    {
        $globalRowStart = max(0, $globalRowStart);
        $cellCount = 0;
        $headerCellCount = 0;
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        $completeRowCount = 0;
        $incompleteRowCount = 0;
        $coveredRowCount = 0;
        $missingRowCount = 0;
        $rowSlotCounts = [];
        $rowVisualWidths = [];
        $rowSummaries = [];
        $nestedTableCount = 0;
        $nestedTableCellCount = 0;
        $nestedTableDiagnosticCount = 0;
        $nestedTableCaptions = [];
        $nestedTableDescendantCaptions = [];
        $nestedTableDiagnosticCodes = [];
        $blockContentCellCount = 0;
        $multiBlockCellCount = 0;
        $cellBlockTypes = [];
        $columnSummaries = [];

        foreach ($rows as $rowIndex => $slots) {
            $rowCellCount = 0;
            $rowHeaderCellCount = 0;
            $rowCoveredSlotCount = 0;
            $rowMissingSlotCount = 0;
            $rowMaxOccupiedColumn = -1;
            $globalRow = self::rowGlobalRow($slots, $globalRowStart + (int) $rowIndex);

            foreach ($slots as $column => $slot) {
                $kind = (string) ($slot['kind'] ?? '');
                self::appendColumnSummarySlot($columnSummaries, (int) $column, (int) $rowIndex, $globalRow, $kind, $slot);
                if ($kind === 'covered') {
                    $coveredSlotCount++;
                    $rowCoveredSlotCount++;
                    $rowMaxOccupiedColumn = max($rowMaxOccupiedColumn, (int) $column);
                    continue;
                }

                if ($kind === 'missing') {
                    $missingSlotCount++;
                    $rowMissingSlotCount++;
                    continue;
                }

                if ($kind !== 'cell') {
                    continue;
                }

                $cellCount++;
                $rowCellCount++;
                $rowMaxOccupiedColumn = max($rowMaxOccupiedColumn, (int) $column);
                if (($slot['headerCell'] ?? false) === true) {
                    $headerCellCount++;
                    $rowHeaderCellCount++;
                }

                $node = $slot['node'] ?? null;
                if (!$node instanceof AstNode) {
                    continue;
                }

                $content = self::cellContentSummary($node);
                if ($content !== []) {
                    $blockContentCellCount++;
                    if ((int) ($content['blockCount'] ?? 0) > 1) {
                        $multiBlockCellCount++;
                    }

                    foreach ($content['blockTypes'] ?? [] as $blockType) {
                        $blockType = trim((string) $blockType);
                        if ($blockType !== '') {
                            $cellBlockTypes[] = $blockType;
                        }
                    }
                }

                $nestedTables = self::nestedTableSummaries($node);
                if ($nestedTables === []) {
                    continue;
                }

                $nestedTableCellCount++;
                $nestedTableCount += count($nestedTables);
                foreach ($nestedTables as $nestedTable) {
                    $caption = (string) ($nestedTable['caption'] ?? '');
                    if ($caption !== '') {
                        $nestedTableCaptions[] = $caption;
                        $path = $nestedTable['path'] ?? [];
                        if (is_array($path) && count($path) > 1) {
                            $nestedTableDescendantCaptions[] = $caption;
                        }
                    }

                    $diagnosticCount = (int) ($nestedTable['diagnosticCount'] ?? 0);
                    $nestedTableDiagnosticCount += $diagnosticCount;
                    $diagnosticCodes = $nestedTable['diagnosticCodes'] ?? [];
                    if (is_array($diagnosticCodes)) {
                        foreach ($diagnosticCodes as $code) {
                            $code = (string) $code;
                            if ($code !== '') {
                                $nestedTableDiagnosticCodes[] = $code;
                            }
                        }
                    }
                }
            }

            $rowVisualWidth = $rowMaxOccupiedColumn + 1;
            $rowComplete = $slots !== [] && $rowMissingSlotCount === 0;
            if ($rowComplete) {
                $completeRowCount++;
            } else {
                $incompleteRowCount++;
            }
            if ($rowCoveredSlotCount > 0) {
                $coveredRowCount++;
            }
            if ($rowMissingSlotCount > 0) {
                $missingRowCount++;
            }

            $rowSlotCounts[] = count($slots);
            $rowVisualWidths[] = $rowVisualWidth;
            $rowSummaries[] = [
                'row' => (int) $rowIndex,
                'globalRow' => $globalRow,
                'slotCount' => count($slots),
                'cellCount' => $rowCellCount,
                'headerCellCount' => $rowHeaderCellCount,
                'coveredSlotCount' => $rowCoveredSlotCount,
                'missingSlotCount' => $rowMissingSlotCount,
                'occupiedSlotCount' => $rowCellCount + $rowCoveredSlotCount,
                'visualWidth' => $rowVisualWidth,
                'complete' => $rowComplete,
                'hasCoveredSlots' => $rowCoveredSlotCount > 0,
                'hasMissingSlots' => $rowMissingSlotCount > 0,
            ];
        }

        sort($nestedTableCaptions);
        sort($nestedTableDescendantCaptions);
        sort($nestedTableDiagnosticCodes);
        $columnRollup = self::columnSummaryRollup($columnSummaries);

        return [
            'cellCount' => $cellCount,
            'headerCellCount' => $headerCellCount,
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'rowSlotCounts' => $rowSlotCounts,
            'rowVisualWidths' => $rowVisualWidths,
            'completeRowCount' => $completeRowCount,
            'incompleteRowCount' => $incompleteRowCount,
            'coveredRowCount' => $coveredRowCount,
            'missingRowCount' => $missingRowCount,
            'maxVisualWidth' => $rowVisualWidths === [] ? 0 : max($rowVisualWidths),
            'completeRectangle' => $rowSummaries !== [] && $incompleteRowCount === 0,
            'hasIncompleteRows' => $incompleteRowCount > 0,
            'hasCoveredRows' => $coveredRowCount > 0,
            'hasMissingRows' => $missingRowCount > 0,
            'rowSummaries' => $rowSummaries,
            'columnSummaries' => $columnRollup['columnSummaries'],
            'columnSlotCounts' => $columnRollup['columnSlotCounts'],
            'columnCellCounts' => $columnRollup['columnCellCounts'],
            'columnHeaderCellCounts' => $columnRollup['columnHeaderCellCounts'],
            'columnDataCellCounts' => $columnRollup['columnDataCellCounts'],
            'columnCoveredSlotCounts' => $columnRollup['columnCoveredSlotCounts'],
            'columnMissingSlotCounts' => $columnRollup['columnMissingSlotCounts'],
            'completeColumnCount' => $columnRollup['completeColumnCount'],
            'incompleteColumnCount' => $columnRollup['incompleteColumnCount'],
            'coveredColumnCount' => $columnRollup['coveredColumnCount'],
            'missingColumnCount' => $columnRollup['missingColumnCount'],
            'maxColumnCellCount' => $columnRollup['maxColumnCellCount'],
            'nestedTableCount' => $nestedTableCount,
            'nestedTableCellCount' => $nestedTableCellCount,
            'hasNestedTables' => $nestedTableCount > 0,
            'nestedTableCaptions' => array_values(array_unique($nestedTableCaptions)),
            'nestedTableDescendantCaptions' => array_values(array_unique($nestedTableDescendantCaptions)),
            'nestedTableDiagnosticCount' => $nestedTableDiagnosticCount,
            'nestedTableDiagnosticCodes' => array_values(array_unique($nestedTableDiagnosticCodes)),
            'blockContentCellCount' => $blockContentCellCount,
            'multiBlockCellCount' => $multiBlockCellCount,
            'hasBlockContentCells' => $blockContentCellCount > 0,
            'cellBlockTypes' => array_values(array_unique($cellBlockTypes)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columnSummaries
     * @param array<string, mixed> $slot
     */
    private static function appendColumnSummarySlot(
        array &$columnSummaries,
        int $column,
        int $row,
        int $globalRow,
        string $kind,
        array $slot
    ): void {
        if (!isset($columnSummaries[$column])) {
            $columnSummaries[$column] = [
                'column' => $column,
                'slotCount' => 0,
                'cellCount' => 0,
                'headerCellCount' => 0,
                'dataCellCount' => 0,
                'coveredSlotCount' => 0,
                'missingSlotCount' => 0,
                'occupiedSlotCount' => 0,
                'rows' => [],
                'globalRows' => [],
                'cellRows' => [],
                'coveredRows' => [],
                'missingRows' => [],
            ];
        }

        $columnSummaries[$column]['slotCount']++;
        $columnSummaries[$column]['rows'][] = $row;
        $columnSummaries[$column]['globalRows'][] = $globalRow;

        if ($kind === 'cell') {
            $columnSummaries[$column]['cellCount']++;
            $columnSummaries[$column]['occupiedSlotCount']++;
            $columnSummaries[$column]['cellRows'][] = $row;
            if (($slot['headerCell'] ?? false) === true) {
                $columnSummaries[$column]['headerCellCount']++;
            } else {
                $columnSummaries[$column]['dataCellCount']++;
            }

            return;
        }

        if ($kind === 'covered') {
            $columnSummaries[$column]['coveredSlotCount']++;
            $columnSummaries[$column]['occupiedSlotCount']++;
            $columnSummaries[$column]['coveredRows'][] = $row;

            return;
        }

        if ($kind === 'missing') {
            $columnSummaries[$column]['missingSlotCount']++;
            $columnSummaries[$column]['missingRows'][] = $row;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $columnSummaries
     * @return array{
     *     columnSummaries:list<array<string, mixed>>,
     *     columnSlotCounts:list<int>,
     *     columnCellCounts:list<int>,
     *     columnHeaderCellCounts:list<int>,
     *     columnDataCellCounts:list<int>,
     *     columnCoveredSlotCounts:list<int>,
     *     columnMissingSlotCounts:list<int>,
     *     completeColumnCount:int,
     *     incompleteColumnCount:int,
     *     coveredColumnCount:int,
     *     missingColumnCount:int,
     *     maxColumnCellCount:int
     * }
     */
    private static function columnSummaryRollup(array $columnSummaries): array
    {
        $summaries = self::finalizedColumnSummaries($columnSummaries);
        $completeColumnCount = 0;
        $incompleteColumnCount = 0;
        $coveredColumnCount = 0;
        $missingColumnCount = 0;
        $maxColumnCellCount = 0;

        foreach ($summaries as $summary) {
            if (($summary['complete'] ?? false) === true) {
                $completeColumnCount++;
            } else {
                $incompleteColumnCount++;
            }
            if (($summary['hasCoveredSlots'] ?? false) === true) {
                $coveredColumnCount++;
            }
            if (($summary['hasMissingSlots'] ?? false) === true) {
                $missingColumnCount++;
            }
            $maxColumnCellCount = max($maxColumnCellCount, (int) ($summary['cellCount'] ?? 0));
        }

        return [
            'columnSummaries' => $summaries,
            'columnSlotCounts' => array_map(static fn (array $summary): int => (int) $summary['slotCount'], $summaries),
            'columnCellCounts' => array_map(static fn (array $summary): int => (int) $summary['cellCount'], $summaries),
            'columnHeaderCellCounts' => array_map(static fn (array $summary): int => (int) $summary['headerCellCount'], $summaries),
            'columnDataCellCounts' => array_map(static fn (array $summary): int => (int) $summary['dataCellCount'], $summaries),
            'columnCoveredSlotCounts' => array_map(static fn (array $summary): int => (int) $summary['coveredSlotCount'], $summaries),
            'columnMissingSlotCounts' => array_map(static fn (array $summary): int => (int) $summary['missingSlotCount'], $summaries),
            'completeColumnCount' => $completeColumnCount,
            'incompleteColumnCount' => $incompleteColumnCount,
            'coveredColumnCount' => $coveredColumnCount,
            'missingColumnCount' => $missingColumnCount,
            'maxColumnCellCount' => $maxColumnCellCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columnSummaries
     * @return list<array<string, mixed>>
     */
    private static function finalizedColumnSummaries(array $columnSummaries): array
    {
        ksort($columnSummaries, SORT_NUMERIC);
        $records = [];
        foreach ($columnSummaries as $column => $summary) {
            $slotCount = max(0, (int) ($summary['slotCount'] ?? 0));
            $cellCount = max(0, (int) ($summary['cellCount'] ?? 0));
            $headerCellCount = max(0, (int) ($summary['headerCellCount'] ?? 0));
            $dataCellCount = max(0, (int) ($summary['dataCellCount'] ?? 0));
            $coveredSlotCount = max(0, (int) ($summary['coveredSlotCount'] ?? 0));
            $missingSlotCount = max(0, (int) ($summary['missingSlotCount'] ?? 0));
            $occupiedSlotCount = max(0, (int) ($summary['occupiedSlotCount'] ?? ($cellCount + $coveredSlotCount)));

            $records[] = [
                'column' => (int) $column,
                'slotCount' => $slotCount,
                'cellCount' => $cellCount,
                'headerCellCount' => $headerCellCount,
                'dataCellCount' => $dataCellCount,
                'coveredSlotCount' => $coveredSlotCount,
                'missingSlotCount' => $missingSlotCount,
                'occupiedSlotCount' => $occupiedSlotCount,
                'complete' => $slotCount > 0 && $missingSlotCount === 0,
                'hasCells' => $cellCount > 0,
                'hasHeaderCells' => $headerCellCount > 0,
                'hasDataCells' => $dataCellCount > 0,
                'hasCoveredSlots' => $coveredSlotCount > 0,
                'hasMissingSlots' => $missingSlotCount > 0,
                'rows' => self::uniqueIntList($summary['rows'] ?? []),
                'globalRows' => self::uniqueIntList($summary['globalRows'] ?? []),
                'cellRows' => self::uniqueIntList($summary['cellRows'] ?? []),
                'coveredRows' => self::uniqueIntList($summary['coveredRows'] ?? []),
                'missingRows' => self::uniqueIntList($summary['missingRows'] ?? []),
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $slots
     */
    private static function rowGlobalRow(array $slots, int $fallback): int
    {
        foreach ($slots as $slot) {
            if (isset($slot['globalRow']) && is_numeric($slot['globalRow'])) {
                return max(0, (int) $slot['globalRow']);
            }
        }

        return max(0, $fallback);
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function serializableCoverage(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            unset($record['node']);
            if ($node instanceof AstNode) {
                $record['text'] = self::plainText($node);
                $sourceAttributes = self::sourceAttributeSummary($node);
                if ($sourceAttributes !== []) {
                    $record['sourceAttributes'] = $sourceAttributes;
                }
                $decimalAlignment = self::cellDecimalAlignmentFromNode($node);
                if ($decimalAlignment !== []) {
                    $record['decimalAlignment'] = $decimalAlignment;
                }
                $nestedTables = self::nestedTableSummaries($node);
                if ($nestedTables !== []) {
                    $record['nestedTables'] = $nestedTables;
                }
                $content = self::cellContentSummary($node);
                if ($content !== []) {
                    $record['content'] = $content;
                }
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function sourceCoordinateShiftRecords(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            $visualShift = (int) ($record['visualShift'] ?? 0);
            if ($visualShift === 0) {
                continue;
            }

            $node = $record['node'] ?? null;
            $shift = [
                'section' => (string) ($record['section'] ?? ''),
                'row' => (int) ($record['row'] ?? 0),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'column' => (int) ($record['column'] ?? 0),
                'endColumn' => (int) ($record['endColumn'] ?? 0),
                'columns' => self::intList($record['columns'] ?? []),
                'sourceCell' => (int) ($record['sourceCell'] ?? 0),
                'sourceColumn' => (int) ($record['sourceColumn'] ?? 0),
                'sourceEndColumn' => (int) ($record['sourceEndColumn'] ?? 0),
                'sourceColumns' => self::intList($record['sourceColumns'] ?? []),
                'visualShift' => $visualShift,
                'absoluteVisualShift' => abs($visualShift),
                'colspan' => max(1, (int) ($record['colspan'] ?? 1)),
                'rawColspan' => (int) ($record['rawColspan'] ?? 1),
                'rowspan' => max(1, (int) ($record['rowspan'] ?? 1)),
                'rawRowspan' => (int) ($record['rawRowspan'] ?? 1),
                'headerCell' => ($record['headerCell'] ?? false) === true,
                'headerRow' => ($record['headerRow'] ?? false) === true,
                'rowHeadColumns' => (int) ($record['rowHeadColumns'] ?? 0),
            ];

            if (($record['rowspanToEnd'] ?? false) === true) {
                $shift['rowspanToEnd'] = true;
            }

            if ($node instanceof AstNode) {
                $shift['text'] = self::plainText($node);
                $sourceAttributes = self::sourceAttributeSummary($node);
                if ($sourceAttributes !== []) {
                    $shift['sourceAttributes'] = $sourceAttributes;
                }
            }

            $records[] = $shift;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function sourceIdRecords(AstNode $table, array $coverage): array
    {
        $records = [];
        self::appendSourceIdRecord($records, 'table', $table);

        foreach (self::sectionRowGroups($table, self::columnCount($table)) as $group) {
            $section = (string) ($group['section'] ?? '');
            $node = $group['node'] ?? null;
            if ($node instanceof AstNode) {
                self::appendSourceIdRecord($records, 'section', $node, [
                    'section' => $section,
                    'rowRole' => str_starts_with($section, 'body') ? 'body' : $section,
                ]);
            }

            foreach ($group['rowEntries'] as $rowIndex => $entry) {
                $row = $entry['row'] ?? null;
                if (!$row instanceof AstNode) {
                    continue;
                }

                self::appendSourceIdRecord($records, 'row', $row, [
                    'section' => $section,
                    'row' => (int) $rowIndex,
                    'rowRole' => (string) ($entry['rowRole'] ?? ''),
                    'headerRow' => ($entry['header'] ?? false) === true,
                    'rowHeadColumns' => max(0, (int) ($entry['rowHeadColumns'] ?? 0)),
                ]);
            }
        }

        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            self::appendSourceIdRecord($records, 'cell', $node, [
                'section' => (string) ($record['section'] ?? ''),
                'row' => (int) ($record['row'] ?? 0),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'column' => (int) ($record['column'] ?? 0),
                'endColumn' => (int) ($record['endColumn'] ?? 0),
                'columns' => self::intList($record['columns'] ?? []),
                'sourceCell' => (int) ($record['sourceCell'] ?? 0),
                'sourceColumn' => (int) ($record['sourceColumn'] ?? 0),
                'sourceEndColumn' => (int) ($record['sourceEndColumn'] ?? 0),
                'sourceColumns' => self::intList($record['sourceColumns'] ?? []),
                'colspan' => max(1, (int) ($record['colspan'] ?? 1)),
                'rowspan' => max(1, (int) ($record['rowspan'] ?? 1)),
                'headerCell' => ($record['headerCell'] ?? false) === true,
                'headerRow' => ($record['headerRow'] ?? false) === true,
                'rowHeadColumns' => max(0, (int) ($record['rowHeadColumns'] ?? 0)),
                'text' => self::plainText($node),
            ]);
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed> $context
     */
    private static function appendSourceIdRecord(array &$records, string $scope, AstNode $node, array $context = []): void
    {
        $sourceAttributes = self::sourceAttributeSummary($node);
        $id = trim((string) ($sourceAttributes['id'] ?? ''));
        if ($id === '') {
            return;
        }

        $record = array_replace([
            'id' => $id,
            'scope' => $scope,
        ], $context);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        $records[] = $record;
    }

    /**
     * @param list<array<string, mixed>> $sourceIds
     * @return list<array<string, mixed>>
     */
    private static function duplicateSourceIdRecords(array $sourceIds): array
    {
        $groups = [];
        foreach ($sourceIds as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id = trim((string) ($record['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $groups[$id][] = $record;
        }

        $duplicates = [];
        foreach ($groups as $id => $locations) {
            if (count($locations) < 2 || self::allSourceIdLocationsAreHeaderCells($locations)) {
                continue;
            }

            $duplicates[] = [
                'id' => $id,
                'locationCount' => count($locations),
                'scopes' => self::sourceIdLocationScopes($locations),
                'locations' => array_values($locations),
            ];
        }

        return $duplicates;
    }

    /**
     * @param list<array<string, mixed>> $locations
     */
    private static function allSourceIdLocationsAreHeaderCells(array $locations): bool
    {
        if ($locations === []) {
            return false;
        }

        foreach ($locations as $location) {
            if (($location['scope'] ?? '') !== 'cell' || ($location['headerCell'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $locations
     * @return list<string>
     */
    private static function sourceIdLocationScopes(array $locations): array
    {
        $scopes = [];
        foreach ($locations as $location) {
            $scope = trim((string) ($location['scope'] ?? ''));
            if ($scope !== '') {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @param list<array<string, mixed>> $duplicates
     * @return list<string>
     */
    private static function duplicateSourceIdStrings(array $duplicates): array
    {
        $ids = [];
        foreach ($duplicates as $duplicate) {
            $id = trim((string) ($duplicate['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<array<string, mixed>> $duplicates
     * @return list<string>
     */
    private static function duplicateSourceIdScopes(array $duplicates): array
    {
        $scopes = [];
        foreach ($duplicates as $duplicate) {
            array_push($scopes, ...self::stringList($duplicate['scopes'] ?? []));
        }
        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes;
    }

    /**
     * @param list<array<string, mixed>> $duplicates
     */
    private static function duplicateSourceIdLocationCount(array $duplicates): int
    {
        $count = 0;
        foreach ($duplicates as $duplicate) {
            $count += max(0, (int) ($duplicate['locationCount'] ?? 0));
        }

        return $count;
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @param list<array<string, mixed>> $coverage
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, list<array<string, mixed>>> $writerDowngrades
     * @param array{long:array<string, mixed>,short:array<string, mixed>} $captions
     * @param list<array<string, mixed>> $columnGroups
     * @param list<array<string, mixed>> $columnDecimalAlignments
     * @param list<array<string, mixed>> $columnBackgrounds
     * @param list<array<string, mixed>> $columnBorderPresentations
     * @param list<array<string, mixed>> $cellDecimalAlignments
     * @param list<array<string, mixed>> $cellNoWraps
     * @param list<array<string, mixed>> $cellDimensions
     * @param list<array<string, mixed>> $sectionBackgrounds
     * @param list<array<string, mixed>> $sectionBorderPresentations
     * @param list<array<string, mixed>> $rowBackgrounds
     * @param list<array<string, mixed>> $rowBorderPresentations
     * @param list<array<string, mixed>> $cellBackgrounds
     * @param list<array<string, mixed>> $cellBorderPresentations
     * @param list<array<string, mixed>> $duplicateSourceIds
     * @param list<array<string, mixed>> $rowGroups
     * @param array<string, mixed> $headerAssociations
     * @param array<string, mixed> $rowHeaderMap
     * @param array<string, mixed> $rowMatrix
     * @param array<string, mixed> $flatGrid
     * @param list<array<string, mixed>> $flatGridFallbacks
     * @param array<string, mixed> $tableLayout
     * @param array<string, mixed> $tableAlignment
     * @param array<string, mixed> $tableFrame
     * @param array<string, mixed> $tableSpacing
     * @param array<string, mixed> $tableBackground
     * @param array<string, mixed> $tableBorderCollapse
     * @param array<string, mixed> $directionality
     * @param array<string, mixed> $localization
     * @return array<string, mixed>
     */
    private static function reviewPacketSummary(
        array $sections,
        array $coverage,
        array $diagnostics,
        array $writerDowngrades,
        array $captions,
        array $columnGroups,
        array $columnDecimalAlignments,
        array $columnBackgrounds,
        array $columnBorderPresentations,
        array $cellDecimalAlignments,
        array $cellNoWraps,
        array $cellDimensions,
        array $sectionBackgrounds,
        array $sectionBorderPresentations,
        array $rowBackgrounds,
        array $rowBorderPresentations,
        array $cellBackgrounds,
        array $cellBorderPresentations,
        array $duplicateSourceIds,
        array $rowGroups,
        array $headerAssociations,
        array $rowHeaderMap,
        array $rowMatrix,
        array $flatGrid,
        array $flatGridFallbacks,
        array $tableLayout,
        array $tableAlignment,
        array $tableFrame,
        array $tableSpacing,
        array $tableBackground,
        array $tableBorderCollapse,
        array $tableBorderPresentation,
        array $directionality,
        array $localization,
        string $sourceSummary
    ): array
    {
        $rowCount = 0;
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        $completeRowCount = 0;
        $incompleteRowCount = 0;
        $coveredRowCount = 0;
        $missingRowCount = 0;
        $maxVisualWidth = 0;
        $globalRows = [];
        $columnSummaries = [];
        foreach ($sections as $section) {
            $rowCount += count($section['rowEntries']);
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $sectionSummary = self::sectionGridSummary(
                $section['rows'],
                $globalRowStart
            );
            $completeRowCount += (int) ($sectionSummary['completeRowCount'] ?? 0);
            $incompleteRowCount += (int) ($sectionSummary['incompleteRowCount'] ?? 0);
            $coveredRowCount += (int) ($sectionSummary['coveredRowCount'] ?? 0);
            $missingRowCount += (int) ($sectionSummary['missingRowCount'] ?? 0);
            $maxVisualWidth = max($maxVisualWidth, (int) ($sectionSummary['maxVisualWidth'] ?? 0));
            $sectionRowRange = self::sectionGridRowRange(
                $section,
                $globalRowStart,
                count($section['rowEntries'])
            );
            for ($globalRow = $sectionRowRange[0]; $globalRow < $sectionRowRange[1]; $globalRow++) {
                $globalRows[$globalRow] = true;
            }
            foreach ($section['rows'] as $rowIndex => $slots) {
                $globalRow = self::rowGlobalRow($slots, $globalRowStart + (int) $rowIndex);
                foreach ($slots as $column => $slot) {
                    $kind = (string) ($slot['kind'] ?? '');
                    self::appendColumnSummarySlot($columnSummaries, (int) $column, $globalRow, $globalRow, $kind, $slot);
                    if (($slot['kind'] ?? '') === 'covered') {
                        $coveredSlotCount++;
                    } elseif (($slot['kind'] ?? '') === 'missing') {
                        $missingSlotCount++;
                    }
                }
            }
        }

        $headerCellCount = 0;
        $hasSpans = false;
        $sourceCoordinateShiftCount = 0;
        $maxVisualShift = 0;
        $nestedTableCellCount = 0;
        $nestedTableCount = 0;
        $blockContentCellCount = 0;
        $multiBlockCellCount = 0;
        $cellBlockTypes = [];
        $rowspanToEndCellCount = 0;
        $rowspanToEndSections = [];
        foreach ($coverage as $record) {
            if (($record['headerCell'] ?? false) === true) {
                $headerCellCount++;
            }

            if ((int) ($record['rawColspan'] ?? 1) > 1 || (int) ($record['rawRowspan'] ?? 1) > 1) {
                $hasSpans = true;
            }

            if (($record['rowspanToEnd'] ?? false) === true) {
                $rowspanToEndCellCount++;
                $section = trim((string) ($record['section'] ?? ''));
                if ($section !== '') {
                    $rowspanToEndSections[] = $section;
                }
            }

            $visualShift = abs((int) ($record['visualShift'] ?? 0));
            if ($visualShift > 0) {
                $sourceCoordinateShiftCount++;
                $maxVisualShift = max($maxVisualShift, $visualShift);
            }

            $nestedTables = $record['nestedTables'] ?? [];
            if (is_array($nestedTables) && $nestedTables !== []) {
                $nestedTableCellCount++;
                $nestedTableCount += count($nestedTables);
            }

            $content = $record['content'] ?? [];
            if (!is_array($content) || ($content['hasBlockContent'] ?? false) !== true) {
                continue;
            }

            $blockContentCellCount++;
            if ((int) ($content['blockCount'] ?? 0) > 1) {
                $multiBlockCellCount++;
            }
            $blockTypes = $content['blockTypes'] ?? [];
            if (is_array($blockTypes)) {
                foreach ($blockTypes as $blockType) {
                    $blockType = trim((string) $blockType);
                    if ($blockType !== '') {
                        $cellBlockTypes[] = $blockType;
                    }
                }
            }
        }

        $diagnosticCodes = [];
        $normalizedSpanCount = 0;
        $normalizedColumnSpanCount = 0;
        $normalizedColumnSpanSourceElements = [];
        $emptyTableSectionCount = 0;
        $emptyTableRowCount = 0;
        $invalidSourceScopeCount = 0;
        $invalidSourceScopes = [];
        $duplicateSourceHeaderTokenCount = 0;
        $duplicateSourceHeaderTokenCellCount = 0;
        $duplicateSourceHeaderTokens = [];
        foreach ($diagnostics as $diagnostic) {
            $code = (string) ($diagnostic['code'] ?? '');
            if ($code !== '') {
                $diagnosticCodes[] = $code;
            }
            if ($code === 'cell-span-normalized') {
                $normalizedSpanCount++;
            } elseif ($code === 'html-column-span-normalized') {
                $normalizedColumnSpanCount++;
                $sourceElement = trim((string) ($diagnostic['sourceElement'] ?? ''));
                if ($sourceElement !== '') {
                    $normalizedColumnSpanSourceElements[] = $sourceElement;
                }
            } elseif ($code === 'table-has-no-cells') {
                $emptyTableSectionCount = max($emptyTableSectionCount, (int) ($diagnostic['sectionCount'] ?? 0));
                $emptyTableRowCount = max($emptyTableRowCount, (int) ($diagnostic['rowCount'] ?? 0));
            } elseif ($code === 'table-header-scope-invalid') {
                $invalidSourceScopeCount++;
                $invalidSourceScope = trim((string) ($diagnostic['rawScope'] ?? ''));
                if ($invalidSourceScope !== '') {
                    $invalidSourceScopes[] = $invalidSourceScope;
                }
            } elseif ($code === 'table-source-headers-duplicate-tokens') {
                $duplicateSourceHeaderTokenCount += (int) ($diagnostic['duplicateTokenCount'] ?? 0);
                $duplicateSourceHeaderTokenCellCount += (int) ($diagnostic['duplicateTokenCellCount'] ?? 0);
                array_push($duplicateSourceHeaderTokens, ...self::stringList($diagnostic['duplicateTokens'] ?? []));
            }
        }

        $writerDowngradeCount = 0;
        $writerDowngradeCodes = [];
        $writerDowngradeWriters = [];
        foreach ($writerDowngrades as $writer => $diagnosticsForWriter) {
            $writer = (string) $writer;
            if ($diagnosticsForWriter === []) {
                continue;
            }

            $writerDowngradeWriters[] = $writer;
            foreach ($diagnosticsForWriter as $diagnostic) {
                $writerDowngradeCount++;
                $code = (string) ($diagnostic['code'] ?? '');
                if ($code !== '') {
                    $writerDowngradeCodes[] = $code;
                }
            }
        }
        sort($writerDowngradeWriters);

        $rowGroupSummary = self::rowGroupSummary($rowGroups);
        $headerAssociationSummary = is_array($headerAssociations['summary'] ?? null)
            ? $headerAssociations['summary']
            : [];
        $headerAssociationScopes = $headerAssociationSummary['headerScopes'] ?? [];
        if (!is_array($headerAssociationScopes)) {
            $headerAssociationScopes = [];
        }
        $rowHeaderSummary = is_array($rowHeaderMap['summary'] ?? null)
            ? $rowHeaderMap['summary']
            : [];
        $rowHeaderScopes = $rowHeaderSummary['rowHeaderScopes'] ?? [];
        if (!is_array($rowHeaderScopes)) {
            $rowHeaderScopes = [];
        }
        $rowMatrixSummary = is_array($rowMatrix['summary'] ?? null)
            ? $rowMatrix['summary']
            : [];
        $flatGridSummary = is_array($flatGrid['summary'] ?? null)
            ? $flatGrid['summary']
            : [];
        $flatGridFallbackSummary = self::flatGridFallbackSummary($flatGridFallbacks);
        $directionalitySummary = is_array($directionality['summary'] ?? null)
            ? $directionality['summary']
            : self::emptyDirectionalitySummary();
        $localizationSummary = is_array($localization['summary'] ?? null)
            ? $localization['summary']
            : self::emptyLocalizationSummary();
        $globalRowIndexes = array_keys($globalRows);
        sort($globalRowIndexes, SORT_NUMERIC);
        $columnRollup = self::columnSummaryRollup($columnSummaries);
        $duplicateSourceIdCount = count($duplicateSourceIds);
        $duplicateSourceIdLocationCount = self::duplicateSourceIdLocationCount($duplicateSourceIds);

        return [
            'sectionCount' => count($sections),
            'rowCount' => $rowCount,
            'globalRowCount' => count($globalRowIndexes),
            'globalRowRange' => $globalRowIndexes === []
                ? [0, 0]
                : [min($globalRowIndexes), max($globalRowIndexes) + 1],
            'maxGlobalRow' => $globalRowIndexes === [] ? 0 : max($globalRowIndexes),
            'cellCount' => count($coverage),
            'headerCellCount' => $headerCellCount,
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'completeRowCount' => $completeRowCount,
            'incompleteRowCount' => $incompleteRowCount,
            'coveredRowCount' => $coveredRowCount,
            'missingRowCount' => $missingRowCount,
            'maxVisualWidth' => $maxVisualWidth,
            'completeRectangle' => $rowCount > 0 && $incompleteRowCount === 0,
            'hasIncompleteRows' => $incompleteRowCount > 0,
            'hasCoveredRows' => $coveredRowCount > 0,
            'hasMissingRows' => $missingRowCount > 0,
            'columnSummaries' => $columnRollup['columnSummaries'],
            'columnSlotCounts' => $columnRollup['columnSlotCounts'],
            'columnCellCounts' => $columnRollup['columnCellCounts'],
            'columnHeaderCellCounts' => $columnRollup['columnHeaderCellCounts'],
            'columnDataCellCounts' => $columnRollup['columnDataCellCounts'],
            'columnCoveredSlotCounts' => $columnRollup['columnCoveredSlotCounts'],
            'columnMissingSlotCounts' => $columnRollup['columnMissingSlotCounts'],
            'completeColumnCount' => $columnRollup['completeColumnCount'],
            'incompleteColumnCount' => $columnRollup['incompleteColumnCount'],
            'coveredColumnCount' => $columnRollup['coveredColumnCount'],
            'missingColumnCount' => $columnRollup['missingColumnCount'],
            'maxColumnCellCount' => $columnRollup['maxColumnCellCount'],
            'diagnosticCount' => count($diagnostics),
            'diagnosticCodes' => array_values(array_unique($diagnosticCodes)),
            'hasNormalizedSpans' => $normalizedSpanCount > 0,
            'normalizedSpanCount' => $normalizedSpanCount,
            'hasNormalizedColumnSpans' => $normalizedColumnSpanCount > 0,
            'normalizedColumnSpanCount' => $normalizedColumnSpanCount,
            'normalizedColumnSpanSourceElements' => array_values(array_unique($normalizedColumnSpanSourceElements)),
            'hasEmptyTable' => in_array('table-has-no-cells', $diagnosticCodes, true),
            'emptyTableSectionCount' => $emptyTableSectionCount,
            'emptyTableRowCount' => $emptyTableRowCount,
            'hasInvalidSourceScopes' => $invalidSourceScopeCount > 0,
            'invalidSourceScopeCount' => $invalidSourceScopeCount,
            'invalidSourceScopes' => array_values(array_unique($invalidSourceScopes)),
            'hasDuplicateSourceHeaderTokens' => $duplicateSourceHeaderTokenCount > 0,
            'duplicateSourceHeaderTokenCount' => $duplicateSourceHeaderTokenCount,
            'duplicateSourceHeaderTokenCellCount' => $duplicateSourceHeaderTokenCellCount,
            'duplicateSourceHeaderTokens' => array_values(array_unique($duplicateSourceHeaderTokens)),
            'hasDuplicateSourceIds' => $duplicateSourceIdCount > 0,
            'duplicateSourceIdCount' => $duplicateSourceIdCount,
            'duplicateSourceIdLocationCount' => $duplicateSourceIdLocationCount,
            'duplicateSourceIds' => self::duplicateSourceIdStrings($duplicateSourceIds),
            'duplicateSourceIdScopes' => self::duplicateSourceIdScopes($duplicateSourceIds),
            'hasCaption' => (string) ($captions['long']['text'] ?? '') !== '',
            'hasShortCaption' => (string) ($captions['short']['text'] ?? '') !== '',
            'captionInlineTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['long']['inlineTypes'] ?? null) ? $captions['long']['inlineTypes'] : []
            )),
            'shortCaptionInlineTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['short']['inlineTypes'] ?? null) ? $captions['short']['inlineTypes'] : []
            )),
            'hasCaptionBlocks' => (int) ($captions['long']['blockCount'] ?? 0) > 0,
            'captionBlockCount' => (int) ($captions['long']['blockCount'] ?? 0),
            'captionBlockTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['long']['blockTypes'] ?? null) ? $captions['long']['blockTypes'] : []
            )),
            'hasCaptionSourceAttributes' => is_array($captions['long']['sourceAttributes'] ?? null)
                && ($captions['long']['sourceAttributes'] ?? []) !== [],
            'captionSourceElement' => (string) ($captions['long']['sourceElement'] ?? ''),
            'captionSourcePosition' => (string) ($captions['long']['sourcePosition'] ?? ''),
            'captionSourceChildIndex' => is_numeric($captions['long']['sourceChildIndex'] ?? null)
                ? (int) $captions['long']['sourceChildIndex']
                : null,
            'captionSide' => (string) ($captions['long']['captionSide'] ?? ''),
            'captionSideSource' => (string) ($captions['long']['captionSideSource'] ?? ''),
            'captionSideSupported' => (bool) ($captions['long']['captionSideSupported'] ?? false),
            'captionSideReviewRequired' => (bool) ($captions['long']['captionSideReviewRequired'] ?? false),
            'captionPlacement' => (string) ($captions['long']['captionPlacement'] ?? ''),
            'captionPlacementFallback' => (string) ($captions['long']['captionPlacementFallback'] ?? ''),
            'captionBeforeTable' => (bool) ($captions['long']['captionBeforeTable'] ?? false),
            'captionAfterTable' => (bool) ($captions['long']['captionAfterTable'] ?? false),
            'hasSourceSummary' => $sourceSummary !== '',
            'sourceSummaryText' => $sourceSummary,
            'hasTableLayout' => $tableLayout !== [],
            'tableWidth' => (string) ($tableLayout['width'] ?? ''),
            'tableWidthType' => (string) ($tableLayout['widthType'] ?? ''),
            'tableHeight' => (string) ($tableLayout['height'] ?? ''),
            'tableHeightType' => (string) ($tableLayout['heightType'] ?? ''),
            'tableLayoutMode' => (string) ($tableLayout['layoutMode'] ?? ''),
            'tableLayoutModeSource' => (string) ($tableLayout['layoutModeSource'] ?? ''),
            'tableLayoutAttributeCount' => count(is_array($tableLayout['attributes'] ?? null) ? $tableLayout['attributes'] : []),
            'hasTableAlignment' => $tableAlignment !== [],
            'tableAlignment' => (string) ($tableAlignment['alignment'] ?? ''),
            'tableAlignmentAttributeCount' => count(is_array($tableAlignment['attributes'] ?? null) ? $tableAlignment['attributes'] : []),
            'hasTableFrame' => $tableFrame !== [],
            'tableFrame' => (string) ($tableFrame['frame'] ?? ''),
            'tableRules' => (string) ($tableFrame['rules'] ?? ''),
            'tableBorder' => (string) ($tableFrame['border'] ?? ''),
            'hasTableSpacing' => $tableSpacing !== [],
            'tableCellPadding' => (string) ($tableSpacing['cellPadding'] ?? ''),
            'tableCellSpacing' => (string) ($tableSpacing['cellSpacing'] ?? ''),
            'tableSpacingAttributeCount' => count(is_array($tableSpacing['attributes'] ?? null) ? $tableSpacing['attributes'] : []),
            'hasTableBackground' => $tableBackground !== [],
            'tableBackgroundColor' => (string) ($tableBackground['backgroundColor'] ?? ''),
            'tableBackgroundColorSource' => (string) ($tableBackground['backgroundColorSource'] ?? ''),
            'tableBackgroundAttributeCount' => count(is_array($tableBackground['attributes'] ?? null) ? $tableBackground['attributes'] : []),
            'hasTableBorderCollapse' => $tableBorderCollapse !== [],
            'tableBorderCollapse' => (string) ($tableBorderCollapse['borderCollapse'] ?? ''),
            'tableBorderCollapseSource' => (string) ($tableBorderCollapse['borderCollapseSource'] ?? ''),
            'tableBorderCollapseAttributeCount' => count(is_array($tableBorderCollapse['attributes'] ?? null) ? $tableBorderCollapse['attributes'] : []),
            'hasTableBorderPresentation' => $tableBorderPresentation !== [],
            'tableBorderColor' => (string) ($tableBorderPresentation['borderColor'] ?? ''),
            'tableBorderColorSource' => (string) ($tableBorderPresentation['borderColorSource'] ?? ''),
            'tableLegacyBorderColor' => (string) ($tableBorderPresentation['legacyBorderColor'] ?? ''),
            'tableCssBorderColor' => (string) ($tableBorderPresentation['cssBorderColor'] ?? ''),
            'tableBorderStyle' => (string) ($tableBorderPresentation['borderStyle'] ?? ''),
            'tableBorderWidth' => (string) ($tableBorderPresentation['borderWidth'] ?? ''),
            'tableBorderPresentationAttributeCount' => count(is_array($tableBorderPresentation['attributes'] ?? null) ? $tableBorderPresentation['attributes'] : []),
            'hasTableDirectionality' => (bool) ($directionalitySummary['hasDirectionality'] ?? false),
            'directionRecordCount' => (int) ($directionalitySummary['directionRecordCount'] ?? 0),
            'directionalCellCount' => (int) ($directionalitySummary['directionalCellCount'] ?? 0),
            'explicitCellDirectionCount' => (int) ($directionalitySummary['explicitCellDirectionCount'] ?? 0),
            'inheritedCellDirectionCount' => (int) ($directionalitySummary['inheritedCellDirectionCount'] ?? 0),
            'tableDirections' => self::stringList($directionalitySummary['directions'] ?? []),
            'hasTableLocalization' => (bool) ($localizationSummary['hasLocalization'] ?? false),
            'localizationRecordCount' => (int) ($localizationSummary['localizationRecordCount'] ?? 0),
            'localizedCellCount' => (int) ($localizationSummary['localizedCellCount'] ?? 0),
            'explicitCellLanguageCount' => (int) ($localizationSummary['explicitCellLanguageCount'] ?? 0),
            'inheritedCellLanguageCount' => (int) ($localizationSummary['inheritedCellLanguageCount'] ?? 0),
            'translatedCellCount' => (int) ($localizationSummary['translatedCellCount'] ?? 0),
            'tableLanguages' => self::stringList($localizationSummary['languages'] ?? []),
            'tableTranslateStates' => self::stringList($localizationSummary['translateStates'] ?? []),
            'hasShortCaptionBlocks' => (int) ($captions['short']['blockCount'] ?? 0) > 0,
            'shortCaptionBlockCount' => (int) ($captions['short']['blockCount'] ?? 0),
            'shortCaptionBlockTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['short']['blockTypes'] ?? null) ? $captions['short']['blockTypes'] : []
            )),
            'hasSpans' => $hasSpans,
            'hasRowspanToEndCells' => $rowspanToEndCellCount > 0,
            'rowspanToEndCellCount' => $rowspanToEndCellCount,
            'rowspanToEndSections' => array_values(array_unique($rowspanToEndSections)),
            'hasSourceCoordinateShifts' => $sourceCoordinateShiftCount > 0,
            'sourceCoordinateShiftCount' => $sourceCoordinateShiftCount,
            'maxVisualShift' => $maxVisualShift,
            'nestedTableCount' => $nestedTableCount,
            'nestedTableCellCount' => $nestedTableCellCount,
            'blockContentCellCount' => $blockContentCellCount,
            'multiBlockCellCount' => $multiBlockCellCount,
            'hasBlockContentCells' => $blockContentCellCount > 0,
            'cellBlockTypes' => array_values(array_unique($cellBlockTypes)),
            'columnGroupCount' => count($columnGroups),
            'hasColumnGroups' => $columnGroups !== [],
            'columnDecimalAlignmentCount' => count($columnDecimalAlignments),
            'hasColumnDecimalAlignments' => $columnDecimalAlignments !== [],
            'columnDecimalAlignmentColumns' => self::columnDecimalAlignmentColumns($columnDecimalAlignments),
            'columnDecimalAlignmentChars' => self::columnDecimalAlignmentStringValues($columnDecimalAlignments, 'char'),
            'columnDecimalAlignmentOffsets' => self::columnDecimalAlignmentStringValues($columnDecimalAlignments, 'charoff'),
            'columnBackgroundCount' => count($columnBackgrounds),
            'hasColumnBackgrounds' => $columnBackgrounds !== [],
            'columnBackgroundColumns' => self::columnBackgroundColumns($columnBackgrounds),
            'columnBackgroundColors' => self::columnBackgroundStringValues($columnBackgrounds, 'backgroundColor'),
            'columnBackgroundSources' => self::columnBackgroundStringValues($columnBackgrounds, 'backgroundColorSource'),
            'columnBackgroundSourceElements' => self::columnBackgroundStringValues($columnBackgrounds, 'sourceElement'),
            'columnBorderPresentationCount' => count($columnBorderPresentations),
            'hasColumnBorderPresentations' => $columnBorderPresentations !== [],
            'columnBorderPresentationColumns' => self::columnBorderPresentationColumns($columnBorderPresentations),
            'columnBorderPresentationColors' => self::columnBorderPresentationStringValues($columnBorderPresentations, 'borderColor'),
            'columnBorderPresentationStyles' => self::columnBorderPresentationStringValues($columnBorderPresentations, 'borderStyle'),
            'columnBorderPresentationWidths' => self::columnBorderPresentationStringValues($columnBorderPresentations, 'borderWidth'),
            'columnBorderPresentationSourceElements' => self::columnBorderPresentationStringValues($columnBorderPresentations, 'sourceElement'),
            'columnBorderPresentationEdgeCount' => self::columnBorderPresentationEdgeCount($columnBorderPresentations),
            'hasColumnBorderPresentationEdges' => self::columnBorderPresentationEdgeCount($columnBorderPresentations) > 0,
            'columnBorderPresentationEdges' => self::columnBorderPresentationEdgeStringValues($columnBorderPresentations, 'edge'),
            'columnBorderPresentationEdgeColors' => self::columnBorderPresentationEdgeStringValues($columnBorderPresentations, 'borderColor'),
            'columnBorderPresentationEdgeStyles' => self::columnBorderPresentationEdgeStringValues($columnBorderPresentations, 'borderStyle'),
            'columnBorderPresentationEdgeWidths' => self::columnBorderPresentationEdgeStringValues($columnBorderPresentations, 'borderWidth'),
            'cellDecimalAlignmentCount' => count($cellDecimalAlignments),
            'hasCellDecimalAlignments' => $cellDecimalAlignments !== [],
            'cellDecimalAlignmentColumns' => self::cellDecimalAlignmentColumns($cellDecimalAlignments),
            'cellDecimalAlignmentChars' => self::cellDecimalAlignmentStringValues($cellDecimalAlignments, 'char'),
            'cellDecimalAlignmentOffsets' => self::cellDecimalAlignmentStringValues($cellDecimalAlignments, 'charoff'),
            'cellNoWrapCount' => count($cellNoWraps),
            'hasCellNoWraps' => $cellNoWraps !== [],
            'cellNoWrapColumns' => self::cellNoWrapColumns($cellNoWraps),
            'cellNoWrapSections' => self::cellNoWrapStringValues($cellNoWraps, 'section'),
            'cellDimensionCount' => count($cellDimensions),
            'hasCellDimensions' => $cellDimensions !== [],
            'cellDimensionColumns' => self::cellDimensionColumns($cellDimensions),
            'cellDimensionSections' => self::cellDimensionStringValues($cellDimensions, 'section'),
            'cellDimensionWidthTypes' => self::cellDimensionStringValues($cellDimensions, 'widthType'),
            'cellDimensionHeightTypes' => self::cellDimensionStringValues($cellDimensions, 'heightType'),
            'cellDimensionWidthSources' => self::cellDimensionStringValues($cellDimensions, 'widthSource'),
            'cellDimensionHeightSources' => self::cellDimensionStringValues($cellDimensions, 'heightSource'),
            'sectionBackgroundCount' => count($sectionBackgrounds),
            'hasSectionBackgrounds' => $sectionBackgrounds !== [],
            'sectionBackgroundSections' => self::rowBackgroundStringValues($sectionBackgrounds, 'section'),
            'sectionBackgroundColors' => self::rowBackgroundStringValues($sectionBackgrounds, 'backgroundColor'),
            'sectionBackgroundSources' => self::rowBackgroundStringValues($sectionBackgrounds, 'backgroundColorSource'),
            'sectionBorderPresentationCount' => count($sectionBorderPresentations),
            'hasSectionBorderPresentations' => $sectionBorderPresentations !== [],
            'sectionBorderPresentationSections' => self::rowBorderPresentationStringValues($sectionBorderPresentations, 'section'),
            'sectionBorderPresentationColors' => self::rowBorderPresentationStringValues($sectionBorderPresentations, 'borderColor'),
            'sectionBorderPresentationStyles' => self::rowBorderPresentationStringValues($sectionBorderPresentations, 'borderStyle'),
            'sectionBorderPresentationWidths' => self::rowBorderPresentationStringValues($sectionBorderPresentations, 'borderWidth'),
            'sectionBorderPresentationEdgeCount' => self::rowBorderPresentationEdgeCount($sectionBorderPresentations),
            'hasSectionBorderPresentationEdges' => self::rowBorderPresentationEdgeCount($sectionBorderPresentations) > 0,
            'sectionBorderPresentationEdges' => self::rowBorderPresentationEdgeStringValues($sectionBorderPresentations, 'edge'),
            'sectionBorderPresentationEdgeColors' => self::rowBorderPresentationEdgeStringValues($sectionBorderPresentations, 'borderColor'),
            'sectionBorderPresentationEdgeStyles' => self::rowBorderPresentationEdgeStringValues($sectionBorderPresentations, 'borderStyle'),
            'sectionBorderPresentationEdgeWidths' => self::rowBorderPresentationEdgeStringValues($sectionBorderPresentations, 'borderWidth'),
            'rowBackgroundCount' => count($rowBackgrounds),
            'hasRowBackgrounds' => $rowBackgrounds !== [],
            'rowBackgroundRows' => self::rowBackgroundRows($rowBackgrounds, 'row'),
            'rowBackgroundGlobalRows' => self::rowBackgroundRows($rowBackgrounds, 'globalRow'),
            'rowBackgroundSections' => self::rowBackgroundStringValues($rowBackgrounds, 'section'),
            'rowBackgroundColors' => self::rowBackgroundStringValues($rowBackgrounds, 'backgroundColor'),
            'rowBackgroundSources' => self::rowBackgroundStringValues($rowBackgrounds, 'backgroundColorSource'),
            'rowBorderPresentationCount' => count($rowBorderPresentations),
            'hasRowBorderPresentations' => $rowBorderPresentations !== [],
            'rowBorderPresentationRows' => self::rowBorderPresentationRows($rowBorderPresentations, 'row'),
            'rowBorderPresentationGlobalRows' => self::rowBorderPresentationRows($rowBorderPresentations, 'globalRow'),
            'rowBorderPresentationSections' => self::rowBorderPresentationStringValues($rowBorderPresentations, 'section'),
            'rowBorderPresentationColors' => self::rowBorderPresentationStringValues($rowBorderPresentations, 'borderColor'),
            'rowBorderPresentationStyles' => self::rowBorderPresentationStringValues($rowBorderPresentations, 'borderStyle'),
            'rowBorderPresentationWidths' => self::rowBorderPresentationStringValues($rowBorderPresentations, 'borderWidth'),
            'rowBorderPresentationEdgeCount' => self::rowBorderPresentationEdgeCount($rowBorderPresentations),
            'hasRowBorderPresentationEdges' => self::rowBorderPresentationEdgeCount($rowBorderPresentations) > 0,
            'rowBorderPresentationEdges' => self::rowBorderPresentationEdgeStringValues($rowBorderPresentations, 'edge'),
            'rowBorderPresentationEdgeColors' => self::rowBorderPresentationEdgeStringValues($rowBorderPresentations, 'borderColor'),
            'rowBorderPresentationEdgeStyles' => self::rowBorderPresentationEdgeStringValues($rowBorderPresentations, 'borderStyle'),
            'rowBorderPresentationEdgeWidths' => self::rowBorderPresentationEdgeStringValues($rowBorderPresentations, 'borderWidth'),
            'cellBackgroundCount' => count($cellBackgrounds),
            'hasCellBackgrounds' => $cellBackgrounds !== [],
            'cellBackgroundColumns' => self::cellBackgroundColumns($cellBackgrounds),
            'cellBackgroundSections' => self::cellBackgroundStringValues($cellBackgrounds, 'section'),
            'cellBackgroundColors' => self::cellBackgroundStringValues($cellBackgrounds, 'backgroundColor'),
            'cellBackgroundSources' => self::cellBackgroundStringValues($cellBackgrounds, 'backgroundColorSource'),
            'cellBorderPresentationCount' => count($cellBorderPresentations),
            'hasCellBorderPresentations' => $cellBorderPresentations !== [],
            'cellBorderPresentationColumns' => self::cellBorderPresentationColumns($cellBorderPresentations),
            'cellBorderPresentationSections' => self::cellBorderPresentationStringValues($cellBorderPresentations, 'section'),
            'cellBorderPresentationColors' => self::cellBorderPresentationStringValues($cellBorderPresentations, 'borderColor'),
            'cellBorderPresentationStyles' => self::cellBorderPresentationStringValues($cellBorderPresentations, 'borderStyle'),
            'cellBorderPresentationWidths' => self::cellBorderPresentationStringValues($cellBorderPresentations, 'borderWidth'),
            'cellBorderPresentationEdgeCount' => self::cellBorderPresentationEdgeCount($cellBorderPresentations),
            'hasCellBorderPresentationEdges' => self::cellBorderPresentationEdgeCount($cellBorderPresentations) > 0,
            'cellBorderPresentationEdges' => self::cellBorderPresentationEdgeStringValues($cellBorderPresentations, 'edge'),
            'cellBorderPresentationEdgeColors' => self::cellBorderPresentationEdgeStringValues($cellBorderPresentations, 'borderColor'),
            'cellBorderPresentationEdgeStyles' => self::cellBorderPresentationEdgeStringValues($cellBorderPresentations, 'borderStyle'),
            'cellBorderPresentationEdgeWidths' => self::cellBorderPresentationEdgeStringValues($cellBorderPresentations, 'borderWidth'),
            'rowGroupCount' => $rowGroupSummary['rowGroupCount'],
            'bodyGroupCount' => $rowGroupSummary['bodyGroupCount'],
            'hasMultipleBodyGroups' => $rowGroupSummary['hasMultipleBodyGroups'],
            'tableHeadRowCount' => $rowGroupSummary['tableHeadRowCount'],
            'bodyHeadRowCount' => $rowGroupSummary['bodyHeadRowCount'],
            'bodyRowCount' => $rowGroupSummary['bodyRowCount'],
            'tableFootRowCount' => $rowGroupSummary['tableFootRowCount'],
            'hasTableFoot' => $rowGroupSummary['hasTableFoot'],
            'hasBodyHeadRows' => $rowGroupSummary['hasBodyHeadRows'],
            'bodyHeadRowGroupCount' => $rowGroupSummary['bodyHeadRowGroupCount'],
            'rowHeadGroupCount' => $rowGroupSummary['rowHeadGroupCount'],
            'maxRowHeadColumns' => $rowGroupSummary['maxRowHeadColumns'],
            'rowHeadSections' => $rowGroupSummary['rowHeadSections'],
            'rowHeadColumnCounts' => $rowGroupSummary['rowHeadColumnCounts'],
            'rowHeadGroupRanges' => $rowGroupSummary['rowHeadGroupRanges'],
            'hasDifferingRowHeadColumns' => $rowGroupSummary['hasDifferingRowHeadColumns'],
            'headerLikeRowCount' => $rowGroupSummary['headerLikeRowCount'],
            'dataLikeRowCount' => $rowGroupSummary['dataLikeRowCount'],
            'maxRowGroupRowCount' => $rowGroupSummary['maxRowGroupRowCount'],
            'nonEmptyRowGroupCount' => $rowGroupSummary['nonEmptyRowGroupCount'],
            'emptyRowGroupCount' => $rowGroupSummary['emptyRowGroupCount'],
            'rowRoleCounts' => $rowGroupSummary['rowRoleCounts'],
            'rowGroupSections' => $rowGroupSummary['rowGroupSections'],
            'rowGroupRanges' => $rowGroupSummary['rowGroupRanges'],
            'headerAssociationCount' => (int) ($headerAssociationSummary['associationCount'] ?? 0),
            'associatedDataCellCount' => (int) ($headerAssociationSummary['associatedDataCellCount'] ?? 0),
            'unassociatedDataCellCount' => (int) ($headerAssociationSummary['unassociatedDataCellCount'] ?? 0),
            'sourceHeaderOverrideCount' => (int) ($headerAssociationSummary['sourceHeaderOverrideCount'] ?? 0),
            'hasSourceHeaderOverrides' => (bool) ($headerAssociationSummary['hasSourceHeaderOverrides'] ?? false),
            'sourceHeaderReferencingCellCount' => (int) ($headerAssociationSummary['sourceHeaderReferencingCellCount'] ?? 0),
            'sourceHeaderReferenceCount' => (int) ($headerAssociationSummary['sourceHeaderReferenceCount'] ?? 0),
            'sourceHeaderResolvedReferenceCount' => (int) ($headerAssociationSummary['sourceHeaderResolvedReferenceCount'] ?? 0),
            'sourceHeaderUnresolvedReferenceCount' => (int) ($headerAssociationSummary['sourceHeaderUnresolvedReferenceCount'] ?? 0),
            'hasUnresolvedSourceHeaderReferences' => (bool) ($headerAssociationSummary['hasUnresolvedSourceHeaderReferences'] ?? false),
            'unresolvedSourceHeaderReferences' => self::stringList($headerAssociationSummary['unresolvedSourceHeaderReferences'] ?? []),
            'sourceHeaderDuplicateTokenCellCount' => (int) ($headerAssociationSummary['duplicateSourceHeaderTokenCellCount'] ?? 0),
            'sourceHeaderDuplicateTokenCount' => (int) ($headerAssociationSummary['duplicateSourceHeaderTokenCount'] ?? 0),
            'sourceHeaderDuplicateTokens' => self::stringList($headerAssociationSummary['duplicateSourceHeaderTokens'] ?? []),
            'sourceHeaderAmbiguousReferenceCount' => (int) ($headerAssociationSummary['sourceHeaderAmbiguousReferenceCount'] ?? 0),
            'hasAmbiguousSourceHeaderReferences' => (bool) ($headerAssociationSummary['hasAmbiguousSourceHeaderReferences'] ?? false),
            'ambiguousSourceHeaderReferences' => self::stringList($headerAssociationSummary['ambiguousSourceHeaderReferences'] ?? []),
            'duplicateHeaderIdCount' => (int) ($headerAssociationSummary['duplicateHeaderIdCount'] ?? 0),
            'hasDuplicateHeaderIds' => (bool) ($headerAssociationSummary['hasDuplicateHeaderIds'] ?? false),
            'duplicateHeaderIds' => self::stringList($headerAssociationSummary['duplicateHeaderIds'] ?? []),
            'headerAbbreviationCount' => (int) ($headerAssociationSummary['headerAbbreviationCount'] ?? 0),
            'hasHeaderAbbreviations' => (bool) ($headerAssociationSummary['hasHeaderAbbreviations'] ?? false),
            'headerAxisCount' => (int) ($headerAssociationSummary['headerAxisCount'] ?? 0),
            'hasHeaderAxes' => (bool) ($headerAssociationSummary['hasHeaderAxes'] ?? false),
            'headerAxes' => self::stringList($headerAssociationSummary['headerAxes'] ?? []),
            'headerAssociationScopes' => array_values(array_map(
                static fn (mixed $scope): string => (string) $scope,
                $headerAssociationScopes
            )),
            'rowHeaderDataRowCount' => (int) ($rowHeaderSummary['dataRowCount'] ?? 0),
            'rowHeaderLabeledDataRowCount' => (int) ($rowHeaderSummary['labeledDataRowCount'] ?? 0),
            'rowHeaderUnlabeledDataRowCount' => (int) ($rowHeaderSummary['unlabeledDataRowCount'] ?? 0),
            'rowHeaderCellCount' => (int) ($rowHeaderSummary['rowHeaderCellCount'] ?? 0),
            'rowHeaderReferenceCount' => (int) ($rowHeaderSummary['rowHeaderReferenceCount'] ?? 0),
            'rowHeaderMaxHeaderCount' => (int) ($rowHeaderSummary['maxRowHeaderCount'] ?? 0),
            'hasRowHeaders' => (bool) ($rowHeaderSummary['hasRowHeaders'] ?? false),
            'hasUnlabeledDataRows' => (bool) ($rowHeaderSummary['hasUnlabeledDataRows'] ?? false),
            'hasRowspanRowHeaders' => (bool) ($rowHeaderSummary['hasRowspanRowHeaders'] ?? false),
            'rowspannedRowHeaderReferenceCount' => (int) ($rowHeaderSummary['rowspannedRowHeaderReferenceCount'] ?? 0),
            'rowHeaderScopes' => array_values(array_map(
                static fn (mixed $scope): string => (string) $scope,
                $rowHeaderScopes
            )),
            'rowMatrixRowCount' => (int) ($rowMatrixSummary['rowCount'] ?? 0),
            'rowMatrixHeaderRowCount' => (int) ($rowMatrixSummary['headerRowCount'] ?? 0),
            'rowMatrixDataRowCount' => (int) ($rowMatrixSummary['dataRowCount'] ?? 0),
            'rowMatrixHeaderCellCount' => (int) ($rowMatrixSummary['headerCellCount'] ?? 0),
            'rowMatrixDataCellCount' => (int) ($rowMatrixSummary['dataCellCount'] ?? 0),
            'rowMatrixAssociatedDataCellCount' => (int) ($rowMatrixSummary['associatedDataCellCount'] ?? 0),
            'rowMatrixUnassociatedDataCellCount' => (int) ($rowMatrixSummary['unassociatedDataCellCount'] ?? 0),
            'hasRowMatrixHeaderAssociations' => (bool) ($rowMatrixSummary['hasHeaderAssociations'] ?? false),
            'hasRowMatrixUnassociatedDataCells' => (bool) ($rowMatrixSummary['hasUnassociatedDataCells'] ?? false),
            'rowMatrixMaxCellCountPerRow' => (int) ($rowMatrixSummary['maxCellCountPerRow'] ?? 0),
            'rowMatrixMaxHeaderCellsPerRow' => (int) ($rowMatrixSummary['maxHeaderCellsPerRow'] ?? 0),
            'rowMatrixMaxDataCellsPerRow' => (int) ($rowMatrixSummary['maxDataCellsPerRow'] ?? 0),
            'flatGridRowCount' => (int) ($flatGridSummary['rowCount'] ?? 0),
            'flatGridColumnCount' => (int) ($flatGridSummary['columnCount'] ?? 0),
            'flatGridSlotCount' => (int) ($flatGridSummary['slotCount'] ?? 0),
            'flatGridAnchorSlotCount' => (int) ($flatGridSummary['anchorSlotCount'] ?? 0),
            'flatGridCoveredSlotCount' => (int) ($flatGridSummary['coveredSlotCount'] ?? 0),
            'flatGridMissingSlotCount' => (int) ($flatGridSummary['missingSlotCount'] ?? 0),
            'flatGridSpanAnchorCount' => (int) ($flatGridSummary['spanAnchorCount'] ?? 0),
            'hasFlatGridSpans' => (bool) ($flatGridSummary['hasSpans'] ?? false),
            'flatGridFallbackCount' => count($flatGridFallbacks),
            'hasFlatGridFallbacks' => $flatGridFallbacks !== [],
            'flatGridFallbackCodes' => $flatGridFallbackSummary['codes'],
            'flatGridFallbackSections' => $flatGridFallbackSummary['sections'],
            'flatGridFallbackRows' => $flatGridFallbackSummary['rows'],
            'flatGridFallbackGlobalRows' => $flatGridFallbackSummary['globalRows'],
            'flatGridFallbackColumns' => $flatGridFallbackSummary['columns'],
            'flatGridFallbackCoveredSlotCount' => $flatGridFallbackSummary['coveredSlotCount'],
            'flatGridFallbackMissingSlotCount' => $flatGridFallbackSummary['missingSlotCount'],
            'writerDowngradeCount' => $writerDowngradeCount,
            'writerDowngradeCodes' => array_values(array_unique($writerDowngradeCodes)),
            'writerDowngradeWriters' => array_values(array_unique($writerDowngradeWriters)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $fallbacks
     * @return array{
     *     codes:list<string>,
     *     sections:list<string>,
     *     rows:list<int>,
     *     globalRows:list<int>,
     *     columns:list<int>,
     *     coveredSlotCount:int,
     *     missingSlotCount:int
     * }
     */
    private static function flatGridFallbackSummary(array $fallbacks): array
    {
        $codes = [];
        $sections = [];
        $rows = [];
        $globalRows = [];
        $columns = [];
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        foreach ($fallbacks as $fallback) {
            if (!is_array($fallback)) {
                continue;
            }

            $code = (string) ($fallback['code'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }

            foreach (self::stringList($fallback['sections'] ?? []) as $section) {
                $sections[] = $section;
            }
            foreach (self::intList($fallback['rows'] ?? []) as $row) {
                $rows[] = $row;
            }
            foreach (self::intList($fallback['globalRows'] ?? []) as $globalRow) {
                $globalRows[] = $globalRow;
            }
            foreach (self::intList($fallback['columns'] ?? []) as $column) {
                $columns[] = $column;
            }

            $slotCount = max(0, (int) ($fallback['slotCount'] ?? 0));
            $reason = (string) ($fallback['reason'] ?? '');
            if ($reason === 'covered-slots') {
                $coveredSlotCount += $slotCount;
            } elseif ($reason === 'missing-slots') {
                $missingSlotCount += $slotCount;
            }
        }

        return [
            'codes' => array_values(array_unique($codes)),
            'sections' => array_values(array_unique($sections)),
            'rows' => self::uniqueIntList($rows),
            'globalRows' => self::uniqueIntList($globalRows),
            'columns' => self::uniqueIntList($columns),
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rowGroups
     * @return array{
     *     rowGroupCount:int,
     *     bodyGroupCount:int,
     *     hasMultipleBodyGroups:bool,
     *     tableHeadRowCount:int,
     *     bodyHeadRowCount:int,
     *     bodyRowCount:int,
     *     tableFootRowCount:int,
     *     hasTableFoot:bool,
     *     hasBodyHeadRows:bool,
     *     bodyHeadRowGroupCount:int,
     *     rowHeadGroupCount:int,
     *     maxRowHeadColumns:int,
     *     rowHeadSections:list<string>,
     *     rowHeadColumnCounts:list<int>,
     *     rowHeadGroupRanges:list<array{section:string,rowRange:list<int>,rowCount:int,rowRole:string,rowHeadColumns:int,bodyIndex:int,bodyOrdinal:int}>,
     *     hasDifferingRowHeadColumns:bool,
     *     headerLikeRowCount:int,
     *     dataLikeRowCount:int,
     *     maxRowGroupRowCount:int,
     *     nonEmptyRowGroupCount:int,
     *     emptyRowGroupCount:int,
     *     rowRoleCounts:array<string, int>,
     *     rowGroupSections:list<string>,
     *     rowGroupRanges:list<array{section:string,kind:string,rowRange:list<int>,rowCount:int}>
     * }
     */
    private static function rowGroupSummary(array $rowGroups): array
    {
        $bodyGroupCount = 0;
        $tableHeadRowCount = 0;
        $bodyHeadRowCount = 0;
        $bodyRowCount = 0;
        $tableFootRowCount = 0;
        $bodyHeadRowGroupCount = 0;
        $rowHeadGroupCount = 0;
        $maxRowHeadColumns = 0;
        $rowHeadSections = [];
        $rowHeadColumnCounts = [];
        $rowHeadGroupRanges = [];
        $headerLikeRowCount = 0;
        $dataLikeRowCount = 0;
        $maxRowGroupRowCount = 0;
        $nonEmptyRowGroupCount = 0;
        $emptyRowGroupCount = 0;
        $rowRoleCounts = [];
        $rowGroupSections = [];
        $rowGroupRanges = [];
        foreach ($rowGroups as $rowGroup) {
            $kind = (string) ($rowGroup['kind'] ?? '');
            $section = (string) ($rowGroup['section'] ?? '');
            $rowCount = max(0, (int) ($rowGroup['rowCount'] ?? 0));
            $rowRange = self::rowGroupRange($rowGroup);
            $rowGroupSections[] = $section;
            $rowGroupRanges[] = [
                'section' => $section,
                'kind' => $kind,
                'rowRange' => $rowRange,
                'rowCount' => $rowCount,
            ];
            $maxRowGroupRowCount = max($maxRowGroupRowCount, $rowCount);
            if ($rowCount > 0) {
                $nonEmptyRowGroupCount++;
            } else {
                $emptyRowGroupCount++;
            }

            $headerLikeRowCount += max(0, (int) ($rowGroup['headerLikeRowCount'] ?? self::fallbackHeaderLikeRowCount($rowGroup)));
            $dataLikeRowCount += max(0, (int) ($rowGroup['dataLikeRowCount'] ?? self::fallbackDataLikeRowCount($rowGroup)));
            $groupRoleCounts = $rowGroup['rowRoleCounts'] ?? self::fallbackRowRoleCounts($rowGroup);
            if (is_array($groupRoleCounts)) {
                foreach ($groupRoleCounts as $role => $count) {
                    $role = trim((string) $role);
                    if ($role === '') {
                        continue;
                    }
                    $rowRoleCounts[$role] = ($rowRoleCounts[$role] ?? 0) + max(0, (int) $count);
                }
            }

            if ($kind === 'table-head') {
                $tableHeadRowCount += max(0, (int) ($rowGroup['headRowCount'] ?? $rowGroup['rowCount'] ?? 0));
                continue;
            }

            if ($kind === 'table-body') {
                $bodyGroupCount++;
                $groupBodyHeadRows = max(0, (int) ($rowGroup['bodyHeadRowCount'] ?? 0));
                $groupBodyRows = max(0, (int) ($rowGroup['bodyRowCount'] ?? 0));
                $groupRowHeadColumns = max(0, (int) ($rowGroup['rowHeadColumns'] ?? 0));
                $bodyHeadRowCount += $groupBodyHeadRows;
                $bodyRowCount += $groupBodyRows;
                if ($groupBodyHeadRows > 0) {
                    $bodyHeadRowGroupCount++;
                }
                if ($groupRowHeadColumns > 0) {
                    $rowHeadGroupCount++;
                    $maxRowHeadColumns = max($maxRowHeadColumns, $groupRowHeadColumns);
                    $rowHeadSections[] = $section;
                    $rowHeadColumnCounts[] = $groupRowHeadColumns;
                    $rowHeadGroupRanges[] = self::rowHeadGroupRangeRecord($rowGroup);
                }
                continue;
            }

            if ($kind === 'table-foot') {
                $tableFootRowCount += max(0, (int) ($rowGroup['footRowCount'] ?? $rowGroup['rowCount'] ?? 0));
            }
        }

        return [
            'rowGroupCount' => count($rowGroups),
            'bodyGroupCount' => $bodyGroupCount,
            'hasMultipleBodyGroups' => $bodyGroupCount > 1,
            'tableHeadRowCount' => $tableHeadRowCount,
            'bodyHeadRowCount' => $bodyHeadRowCount,
            'bodyRowCount' => $bodyRowCount,
            'tableFootRowCount' => $tableFootRowCount,
            'hasTableFoot' => $tableFootRowCount > 0,
            'hasBodyHeadRows' => $bodyHeadRowCount > 0,
            'bodyHeadRowGroupCount' => $bodyHeadRowGroupCount,
            'rowHeadGroupCount' => $rowHeadGroupCount,
            'maxRowHeadColumns' => $maxRowHeadColumns,
            'rowHeadSections' => $rowHeadSections,
            'rowHeadColumnCounts' => $rowHeadColumnCounts,
            'rowHeadGroupRanges' => $rowHeadGroupRanges,
            'hasDifferingRowHeadColumns' => count(array_unique($rowHeadColumnCounts)) > 1,
            'headerLikeRowCount' => $headerLikeRowCount,
            'dataLikeRowCount' => $dataLikeRowCount,
            'maxRowGroupRowCount' => $maxRowGroupRowCount,
            'nonEmptyRowGroupCount' => $nonEmptyRowGroupCount,
            'emptyRowGroupCount' => $emptyRowGroupCount,
            'rowRoleCounts' => $rowRoleCounts,
            'rowGroupSections' => $rowGroupSections,
            'rowGroupRanges' => $rowGroupRanges,
        ];
    }

    /**
     * @param array<string, mixed> $rowGroup
     * @return list<int>
     */
    private static function rowGroupRange(array $rowGroup): array
    {
        $range = $rowGroup['rowRange'] ?? null;
        if (is_array($range) && array_key_exists(0, $range) && array_key_exists(1, $range)) {
            $start = max(0, (int) $range[0]);

            return [$start, max($start, (int) $range[1])];
        }

        $start = max(0, (int) ($rowGroup['globalRowStart'] ?? 0));
        $end = array_key_exists('globalRowEnd', $rowGroup)
            ? max($start, (int) $rowGroup['globalRowEnd'])
            : $start + max(0, (int) ($rowGroup['rowCount'] ?? 0));

        return [$start, $end];
    }

    /**
     * @param array<string, mixed> $rowGroup
     */
    private static function fallbackHeaderLikeRowCount(array $rowGroup): int
    {
        $kind = (string) ($rowGroup['kind'] ?? '');
        if ($kind === 'table-head') {
            return max(0, (int) ($rowGroup['headRowCount'] ?? $rowGroup['rowCount'] ?? 0));
        }

        if ($kind === 'table-body') {
            return max(0, (int) ($rowGroup['bodyHeadRowCount'] ?? 0));
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $rowGroup
     */
    private static function fallbackDataLikeRowCount(array $rowGroup): int
    {
        $kind = (string) ($rowGroup['kind'] ?? '');
        if ($kind === 'table-body') {
            return max(0, (int) ($rowGroup['bodyRowCount'] ?? 0));
        }

        if ($kind === 'table-foot') {
            return max(0, (int) ($rowGroup['footRowCount'] ?? $rowGroup['rowCount'] ?? 0));
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $rowGroup
     * @return array<string, int>
     */
    private static function fallbackRowRoleCounts(array $rowGroup): array
    {
        return self::rowRoleCountsForRowGroup(
            (string) ($rowGroup['kind'] ?? ''),
            max(0, (int) ($rowGroup['headRowCount'] ?? 0)),
            max(0, (int) ($rowGroup['bodyHeadRowCount'] ?? 0)),
            max(0, (int) ($rowGroup['bodyRowCount'] ?? 0)),
            max(0, (int) ($rowGroup['footRowCount'] ?? 0)),
            max(0, (int) ($rowGroup['rowCount'] ?? 0))
        );
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function writerDowngradeDiagnosticsFromCoverage(array $coverage, string $writer, ?AstNode $table = null, ?string $idPrefix = null): array
    {
        $writer = self::normalizeWriterName($writer);
        if ($writer !== 'markdown') {
            if ($writer === 'latex') {
                $diagnostics = $table instanceof AstNode ? self::captionWriterDiagnostics($table, $writer) : [];
                if ($table instanceof AstNode) {
                    array_push($diagnostics, ...self::emptyTableWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBodyGroupWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnGroupWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnDecimalAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellDecimalAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellNoWrapWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellDimensionWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sectionBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sectionBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::rowBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::rowBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sourceAttributeWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::duplicateSourceIdWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableSummaryWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableLayoutWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableFrameWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableSpacingWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBorderCollapseWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableDirectionWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::tableLocalizationWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::rowHeaderWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::sourceHeaderWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::headerAbbreviationWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::headerAxisWriterDiagnostics($table, $writer, $idPrefix));
                }
                foreach ($coverage as $record) {
                    $rawColspan = max(1, (int) ($record['rawColspan'] ?? 1));
                    $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
                    if ($rawColspan > 1) {
                        $diagnostics[] = self::writerRequirementRecord(
                            'latex-multicolumn-required',
                            $writer,
                            $record,
                            'colspan',
                            'multicolumn',
                            self::flattenedSlotRecords($record, 'colspan')
                        );
                    }

                    if ($rawRowspan > 1) {
                        $diagnostics[] = self::writerRequirementRecord(
                            'latex-multirow-required',
                            $writer,
                            $record,
                            'rowspan',
                            'multirow',
                            self::flattenedSlotRecords($record, 'rowspan')
                        );
                    }

                    $node = $record['node'] ?? null;
                    if (!$node instanceof AstNode) {
                        continue;
                    }

                    $nestedTables = self::nestedTableSummaries($node);
                    if ($nestedTables !== []) {
                        $diagnostics[] = self::writerNestedTableRequirementRecord(
                            'latex-nested-table-required',
                            $writer,
                            $record,
                            $nestedTables,
                            'nested-tabular-minipage'
                        );
                        continue;
                    }

                    $content = self::cellContentSummary($node);
                    if ($content !== []) {
                        $diagnostics[] = self::writerCellBlockRequirementRecord(
                            'latex-cell-block-required',
                            $writer,
                            $record,
                            $content,
                            'parbox-or-minipage-cell'
                        );
                    }
                }

                return $diagnostics;
            }

            if ($writer === 'asciidoc') {
                $diagnostics = $table instanceof AstNode ? self::captionWriterDiagnostics($table, $writer) : [];
                if ($table instanceof AstNode) {
                    array_push($diagnostics, ...self::emptyTableWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableFootSectionWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBodyGroupWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnGroupWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnDecimalAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::columnBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellDecimalAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellNoWrapWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellDimensionWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sectionBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sectionBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::rowBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::rowBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::cellBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::sourceAttributeWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::duplicateSourceIdWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableSummaryWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableLayoutWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableAlignmentWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableFrameWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableSpacingWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBackgroundWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBorderCollapseWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableBorderPresentationWriterDiagnostics($table, $writer));
                    array_push($diagnostics, ...self::tableDirectionWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::tableLocalizationWriterDiagnostics($table, $writer, $coverage));
                    array_push($diagnostics, ...self::rowHeaderWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::sourceHeaderWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::headerAbbreviationWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::headerAxisWriterDiagnostics($table, $writer, $idPrefix));
                    array_push($diagnostics, ...self::tableBodyHeadRowWriterDiagnostics($table, $writer));
                }
                foreach ($coverage as $record) {
                    $node = $record['node'] ?? null;
                    if (!$node instanceof AstNode) {
                        continue;
                    }

                    $nestedTables = self::nestedTableSummaries($node);
                    if ($nestedTables === []) {
                        $content = self::cellContentSummary($node);
                        if ($content !== []) {
                            $diagnostics[] = self::writerCellBlockRequirementRecord(
                                'asciidoc-block-cell-required',
                                $writer,
                                $record,
                                $content,
                                'asciidoc-block-cell'
                            );
                        }

                        continue;
                    }

                    $diagnostics[] = self::writerNestedTableRequirementRecord(
                        'asciidoc-nested-table-raw-html-required',
                        $writer,
                        $record,
                        $nestedTables
                    );
                }

                return $diagnostics;
            }

            if ($writer === 'markdown-grid-table') {
                return self::markdownGridTableWriterDiagnostics($coverage, $writer, $table);
            }

            if ($writer !== 'rst') {
                return [];
            }

            $diagnostics = [];
            foreach ($coverage as $record) {
                $rawColspan = max(1, (int) ($record['rawColspan'] ?? 1));
                if ($rawColspan > 1) {
                    $diagnostics[] = self::writerRequirementRecord(
                        'rst-grid-table-required',
                        $writer,
                        $record,
                        'colspan',
                        'grid-table',
                        self::flattenedSlotRecords($record, 'colspan')
                    );
                }

                $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
                if ($rawRowspan <= 1) {
                    continue;
                }

                $diagnostics[] = self::writerRequirementRecord(
                    'rst-grid-table-required',
                    $writer,
                    $record,
                    'rowspan',
                    'grid-table',
                    self::flattenedSlotRecords($record, 'rowspan')
                );
            }

            return $diagnostics;
        }

        $diagnostics = $table instanceof AstNode ? self::captionWriterDiagnostics($table, $writer) : [];
        if ($table instanceof AstNode) {
            array_push($diagnostics, ...self::emptyTableWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableFootSectionWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::markdownColumnWidthDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableBodyGroupWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::columnGroupWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::columnDecimalAlignmentWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::columnBackgroundWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::columnBorderPresentationWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::cellDecimalAlignmentWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::cellNoWrapWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::cellDimensionWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::sectionBackgroundWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::sectionBorderPresentationWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::rowBackgroundWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::rowBorderPresentationWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::cellBackgroundWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::cellBorderPresentationWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::sourceAttributeWriterDiagnostics($table, $writer, $coverage));
            array_push($diagnostics, ...self::duplicateSourceIdWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableSummaryWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableLayoutWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableAlignmentWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableFrameWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableSpacingWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableBackgroundWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableBorderCollapseWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableBorderPresentationWriterDiagnostics($table, $writer));
            array_push($diagnostics, ...self::tableDirectionWriterDiagnostics($table, $writer, $coverage));
            array_push($diagnostics, ...self::tableLocalizationWriterDiagnostics($table, $writer, $coverage));
            array_push($diagnostics, ...self::rowHeaderWriterDiagnostics($table, $writer, $idPrefix));
            array_push($diagnostics, ...self::sourceHeaderWriterDiagnostics($table, $writer, $idPrefix));
            array_push($diagnostics, ...self::headerAbbreviationWriterDiagnostics($table, $writer, $idPrefix));
            array_push($diagnostics, ...self::headerAxisWriterDiagnostics($table, $writer, $idPrefix));
            array_push($diagnostics, ...self::tableBodyHeadRowWriterDiagnostics($table, $writer));
        }
        foreach ($coverage as $record) {
            $rawColspan = max(1, (int) ($record['rawColspan'] ?? 1));
            $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
            if ($rawColspan > 1) {
                $diagnostics[] = self::writerDowngradeRecord(
                    'markdown-colspan-flattened',
                    $writer,
                    $record,
                    self::flattenedSlotRecords($record, 'colspan')
                );
            }

            if ($rawRowspan > 1) {
                $diagnostics[] = self::writerDowngradeRecord(
                    'markdown-rowspan-flattened',
                    $writer,
                    $record,
                    self::flattenedSlotRecords($record, 'rowspan')
                );
            }

            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode || self::nestedTableSummaries($node) !== []) {
                continue;
            }

            $content = self::cellContentSummary($node);
            if ($content !== []) {
                $diagnostics[] = self::writerCellBlockRequirementRecord(
                    'markdown-cell-blocks-flattened',
                    $writer,
                    $record,
                    $content,
                    'multiline-or-grid-table-cell'
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function emptyTableWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-empty-table-omitted', 'raw-html-empty-table-or-placeholder'],
            'asciidoc' => ['asciidoc-empty-table-review-required', 'empty-table-placeholder'],
            'latex' => ['latex-empty-table-review-required', 'empty-tabular-placeholder'],
        ];
        if (!isset($requirements[$writer]) || self::cellCoverage($table) !== []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];
        $columnCount = self::columnCount($table);
        $rowGroups = self::rowGroups($table, $columnCount);
        $rowGroupSummary = self::rowGroupSummary($rowGroups);

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'empty-table',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-empty-table',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $columnCount,
            'declaredColumnCount' => self::declaredColumnCount($table),
            'sectionCount' => (int) $rowGroupSummary['rowGroupCount'],
            'rowCount' => self::tableRowCountFromRowGroupSummary($rowGroupSummary),
            'bodyCount' => (int) $rowGroupSummary['bodyGroupCount'],
            'headRowCount' => (int) $rowGroupSummary['tableHeadRowCount'],
            'bodyHeadRowCount' => (int) $rowGroupSummary['bodyHeadRowCount'],
            'bodyRowCount' => (int) $rowGroupSummary['bodyRowCount'],
            'footRowCount' => (int) $rowGroupSummary['tableFootRowCount'],
            'sections' => self::emptyTableDiagnosticSections($rowGroups),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowHeaderWriterDiagnostics(AstNode $table, string $writer, ?string $idPrefix = null): array
    {
        $requirements = [
            'markdown' => ['markdown-row-headers-flattened', 'pipe-table-row-header-semantics'],
            'asciidoc' => ['asciidoc-row-headers-review-required', 'row-header-review'],
            'latex' => ['latex-row-headers-review-required', 'row-header-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $map = self::rowHeaderMap($table, $idPrefix ?? self::reviewPacketIdPrefix($table, []));
        $summary = is_array($map['summary'] ?? null) ? $map['summary'] : [];
        if (($summary['hasRowHeaders'] ?? false) !== true) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];
        $rows = is_array($map['rows'] ?? null) ? $map['rows'] : [];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'row-headers',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-row-head-columns',
            'caption' => (string) $table->attr('caption', ''),
            'dataRowCount' => (int) ($summary['dataRowCount'] ?? 0),
            'labeledDataRowCount' => (int) ($summary['labeledDataRowCount'] ?? 0),
            'unlabeledDataRowCount' => (int) ($summary['unlabeledDataRowCount'] ?? 0),
            'rowHeaderCellCount' => (int) ($summary['rowHeaderCellCount'] ?? 0),
            'rowHeaderReferenceCount' => (int) ($summary['rowHeaderReferenceCount'] ?? 0),
            'maxRowHeaderCount' => (int) ($summary['maxRowHeaderCount'] ?? 0),
            'rowHeaderScopes' => self::stringList($summary['rowHeaderScopes'] ?? []),
            'hasUnlabeledDataRows' => (bool) ($summary['hasUnlabeledDataRows'] ?? false),
            'hasRowspanRowHeaders' => (bool) ($summary['hasRowspanRowHeaders'] ?? false),
            'rowspannedRowHeaderReferenceCount' => (int) ($summary['rowspannedRowHeaderReferenceCount'] ?? 0),
            'rows' => $rows,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sourceHeaderWriterDiagnostics(AstNode $table, string $writer, ?string $idPrefix = null): array
    {
        $requirements = [
            'markdown' => ['markdown-source-headers-require-raw-html', 'raw-html-table-headers'],
            'asciidoc' => ['asciidoc-source-headers-review-required', 'source-header-reference-review'],
            'latex' => ['latex-source-headers-review-required', 'table-header-reference-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $associations = self::headerAssociations($table, $idPrefix ?? self::reviewPacketIdPrefix($table, []));
        $summary = is_array($associations['summary'] ?? null) ? $associations['summary'] : [];
        $referenceCount = (int) ($summary['sourceHeaderReferenceCount'] ?? 0);
        if ($referenceCount <= 0) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'source-headers',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-headers',
            'caption' => (string) $table->attr('caption', ''),
            'referencingCellCount' => (int) ($summary['sourceHeaderReferencingCellCount'] ?? 0),
            'referenceCount' => $referenceCount,
            'resolvedReferenceCount' => (int) ($summary['sourceHeaderResolvedReferenceCount'] ?? 0),
            'unresolvedReferenceCount' => (int) ($summary['sourceHeaderUnresolvedReferenceCount'] ?? 0),
            'hasUnresolvedReferences' => (bool) ($summary['hasUnresolvedSourceHeaderReferences'] ?? false),
            'unresolvedReferences' => self::stringList($summary['unresolvedSourceHeaderReferences'] ?? []),
            'duplicateTokenCellCount' => (int) ($summary['duplicateSourceHeaderTokenCellCount'] ?? 0),
            'duplicateTokenCount' => (int) ($summary['duplicateSourceHeaderTokenCount'] ?? 0),
            'hasDuplicateTokens' => (bool) ($summary['hasDuplicateSourceHeaderTokens'] ?? false),
            'duplicateTokens' => self::stringList($summary['duplicateSourceHeaderTokens'] ?? []),
            'ambiguousReferenceCount' => (int) ($summary['sourceHeaderAmbiguousReferenceCount'] ?? 0),
            'hasAmbiguousReferences' => (bool) ($summary['hasAmbiguousSourceHeaderReferences'] ?? false),
            'ambiguousReferences' => self::stringList($summary['ambiguousSourceHeaderReferences'] ?? []),
            'sourceHeaderOverrideCount' => (int) ($summary['sourceHeaderOverrideCount'] ?? 0),
            'hasSourceHeaderOverrides' => (bool) ($summary['hasSourceHeaderOverrides'] ?? false),
            'cells' => self::sourceHeaderReferenceCells($associations),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function headerAbbreviationWriterDiagnostics(AstNode $table, string $writer, ?string $idPrefix = null): array
    {
        $requirements = [
            'markdown' => ['markdown-header-abbreviation-require-raw-html', 'raw-html-table-header-abbr'],
            'asciidoc' => ['asciidoc-header-abbreviation-review-required', 'header-abbreviation-review'],
            'latex' => ['latex-header-abbreviation-review-required', 'table-header-abbreviation-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $associations = self::headerAssociations($table, $idPrefix ?? self::reviewPacketIdPrefix($table, []));
        $summary = is_array($associations['summary'] ?? null) ? $associations['summary'] : [];
        $abbreviationCount = (int) ($summary['headerAbbreviationCount'] ?? 0);
        if ($abbreviationCount <= 0) {
            return [];
        }

        $headerCells = self::headerAbbreviationCells($associations);
        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'header-abbreviation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-abbr',
            'caption' => (string) $table->attr('caption', ''),
            'headerAbbreviationCount' => $abbreviationCount,
            'hasHeaderAbbreviations' => (bool) ($summary['hasHeaderAbbreviations'] ?? false),
            'abbreviations' => array_values(array_unique(array_map(
                static fn (array $cell): string => (string) ($cell['abbr'] ?? ''),
                $headerCells
            ))),
            'headerCells' => $headerCells,
        ]];
    }

    /**
     * @param array<string, mixed> $associations
     * @return list<array<string, mixed>>
     */
    private static function headerAbbreviationCells(array $associations): array
    {
        $cells = [];
        $headerCells = is_array($associations['headerCells'] ?? null) ? $associations['headerCells'] : [];
        foreach ($headerCells as $record) {
            if (!is_array($record)) {
                continue;
            }

            $abbr = trim((string) ($record['abbr'] ?? ''));
            if ($abbr === '') {
                continue;
            }

            $cell = [];
            foreach (['key', 'section', 'rowRole', 'id', 'scope', 'text'] as $key) {
                $value = trim((string) ($record[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            foreach (['row', 'column', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns !== []) {
                $cell['columns'] = $columns;
            }

            $axis = self::stringList($record['axis'] ?? []);
            if ($axis !== []) {
                $cell['axis'] = $axis;
            }

            $cell['abbr'] = $abbr;
            $cells[] = $cell;
        }

        return $cells;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function headerAxisWriterDiagnostics(AstNode $table, string $writer, ?string $idPrefix = null): array
    {
        $requirements = [
            'markdown' => ['markdown-header-axis-require-raw-html', 'raw-html-table-header-axis'],
            'asciidoc' => ['asciidoc-header-axis-review-required', 'header-axis-review'],
            'latex' => ['latex-header-axis-review-required', 'table-header-axis-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $associations = self::headerAssociations($table, $idPrefix ?? self::reviewPacketIdPrefix($table, []));
        $summary = is_array($associations['summary'] ?? null) ? $associations['summary'] : [];
        $axisCount = (int) ($summary['headerAxisCount'] ?? 0);
        if ($axisCount <= 0) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'header-axis',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-axis',
            'caption' => (string) $table->attr('caption', ''),
            'headerAxisCount' => $axisCount,
            'hasHeaderAxes' => (bool) ($summary['hasHeaderAxes'] ?? false),
            'axes' => self::stringList($summary['headerAxes'] ?? []),
            'headerCells' => self::headerAxisCells($associations),
        ]];
    }

    /**
     * @param array<string, mixed> $associations
     * @return list<array<string, mixed>>
     */
    private static function headerAxisCells(array $associations): array
    {
        $cells = [];
        $headerCells = is_array($associations['headerCells'] ?? null) ? $associations['headerCells'] : [];
        foreach ($headerCells as $record) {
            if (!is_array($record)) {
                continue;
            }

            $axis = self::stringList($record['axis'] ?? []);
            if ($axis === []) {
                continue;
            }

            $cell = [];
            foreach (['key', 'section', 'rowRole', 'id', 'scope', 'text', 'abbr'] as $key) {
                $value = trim((string) ($record[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            foreach (['row', 'column', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns !== []) {
                $cell['columns'] = $columns;
            }
            $cell['axis'] = $axis;
            $cells[] = $cell;
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $associations
     * @return list<array<string, mixed>>
     */
    private static function sourceHeaderDuplicateTokenCells(array $associations): array
    {
        $cells = [];
        foreach (['headerCells' => 'header', 'dataCells' => 'data'] as $associationKey => $role) {
            $records = is_array($associations[$associationKey] ?? null) ? $associations[$associationKey] : [];
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $duplicates = self::stringList($record['duplicateSourceHeaderTokens'] ?? []);
                if ($duplicates === []) {
                    continue;
                }

                $cell = ['role' => $role];
                foreach (['key', 'section', 'rowRole', 'id', 'scope', 'text'] as $key) {
                    $value = trim((string) ($record[$key] ?? ''));
                    if ($value !== '') {
                        $cell[$key] = $value;
                    }
                }

                foreach (['row', 'column', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'sourceHeaderTokenCount', 'sourceHeaderUniqueTokenCount', 'duplicateSourceHeaderTokenCount'] as $key) {
                    if (isset($record[$key]) && is_numeric($record[$key])) {
                        $cell[$key] = (int) $record[$key];
                    }
                }

                foreach (['columns', 'headers', 'sourceHeaders'] as $key) {
                    $values = $key === 'columns' ? self::intList($record[$key] ?? []) : self::stringList($record[$key] ?? []);
                    if ($values !== []) {
                        $cell[$key] = $values;
                    }
                }

                $cell['duplicateSourceHeaderTokens'] = $duplicates;
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $associations
     * @return list<array<string, mixed>>
     */
    private static function sourceHeaderReferenceCells(array $associations): array
    {
        $cells = [];
        foreach (['headerCells' => 'header', 'dataCells' => 'data'] as $associationKey => $role) {
            $records = is_array($associations[$associationKey] ?? null) ? $associations[$associationKey] : [];
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $references = is_array($record['sourceHeaderReferences'] ?? null)
                    ? array_values(array_filter(
                        $record['sourceHeaderReferences'],
                        static fn (mixed $reference): bool => is_array($reference)
                    ))
                    : [];
                if ($references === []) {
                    continue;
                }

                $cell = ['role' => $role];
                foreach (['key', 'section', 'rowRole', 'id', 'scope', 'text'] as $key) {
                    $value = trim((string) ($record[$key] ?? ''));
                    if ($value !== '') {
                        $cell[$key] = $value;
                    }
                }

                foreach (['row', 'column', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'sourceHeaderTokenCount', 'sourceHeaderUniqueTokenCount', 'duplicateSourceHeaderTokenCount'] as $key) {
                    if (isset($record[$key]) && is_numeric($record[$key])) {
                        $cell[$key] = (int) $record[$key];
                    }
                }

                foreach (['columns', 'headers', 'sourceHeaders', 'axis', 'duplicateSourceHeaderTokens'] as $key) {
                    $values = $key === 'columns' ? self::intList($record[$key] ?? []) : self::stringList($record[$key] ?? []);
                    if ($values !== []) {
                        $cell[$key] = $values;
                    }
                }

                $cell['sourceHeaderReferences'] = $references;
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $columnGroups
     * @return list<array<string, mixed>>
     */
    private static function columnDecimalAlignments(array $columnGroups): array
    {
        $records = [];
        foreach ($columnGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $source = $group['source'] ?? null;
            if (!is_array($source)) {
                continue;
            }

            $alignment = self::columnDecimalAlignmentFromSource($source);
            if ($alignment === []) {
                continue;
            }

            $columns = self::intList($group['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $record = [
                'columns' => $columns,
                'startColumn' => min($columns),
                'endColumn' => max($columns) + 1,
                'span' => count($columns),
                'source' => 'html-colgroup-char-alignment',
                'sourceElement' => (string) ($alignment['sourceElement'] ?? ''),
                'alignment' => 'char',
                'char' => (string) ($alignment['char'] ?? ''),
                'charoff' => (string) ($alignment['charoff'] ?? ''),
                'htmlAttributes' => $alignment['htmlAttributes'] ?? [],
            ];

            foreach (['kind', 'colgroupIndex', 'colIndex', 'sourceSpan'] as $key) {
                if (isset($group[$key]) && is_numeric($group[$key])) {
                    $record[$key] = (int) $group[$key];
                } elseif (isset($source[$key]) && is_numeric($source[$key])) {
                    $record[$key] = (int) $source[$key];
                } elseif (isset($group[$key]) && is_scalar($group[$key])) {
                    $record[$key] = (string) $group[$key];
                }
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function columnDecimalAlignmentFromSource(array $source): array
    {
        foreach ([
            'colAttributes' => 'col',
            'colgroupAttributes' => 'colgroup',
        ] as $attributeKey => $sourceElement) {
            $sourceAttributes = $source[$attributeKey] ?? null;
            if (!is_array($sourceAttributes)) {
                continue;
            }

            $htmlAttributes = self::stringAttributeMap($sourceAttributes['htmlAttributes'] ?? [], true);
            $align = strtolower(trim((string) ($htmlAttributes['align'] ?? '')));
            $char = trim((string) ($htmlAttributes['char'] ?? ''));
            $charoff = trim((string) ($htmlAttributes['charoff'] ?? ''));
            if ($align !== 'char' && $char === '' && $charoff === '') {
                continue;
            }

            return [
                'sourceElement' => $sourceElement,
                'char' => $char,
                'charoff' => $charoff,
                'htmlAttributes' => $htmlAttributes,
            ];
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function columnDecimalAlignmentColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function columnDecimalAlignmentStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnDecimalAlignmentWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-column-char-alignment-require-raw-html', 'raw-html-column-char-alignment'],
            'asciidoc' => ['asciidoc-column-char-alignment-review-required', 'column-char-alignment-review'],
            'latex' => ['latex-column-char-alignment-review-required', 'decimal-column-alignment-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::columnDecimalAlignments(self::columnGroups($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'column-char-alignment',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-colgroup-char-alignment',
            'caption' => (string) $table->attr('caption', ''),
            'alignmentCount' => count($records),
            'columns' => self::columnDecimalAlignmentColumns($records),
            'chars' => self::columnDecimalAlignmentStringValues($records, 'char'),
            'charOffsets' => self::columnDecimalAlignmentStringValues($records, 'charoff'),
            'alignments' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $columnGroups
     * @return list<array<string, mixed>>
     */
    private static function columnBackgrounds(array $columnGroups): array
    {
        $records = [];
        foreach ($columnGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $source = is_array($group['source'] ?? null) ? $group['source'] : [];
            $background = self::columnBackgroundFromSource($source);
            if ($background === []) {
                continue;
            }

            $columns = self::intList($group['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $record = [
                'source' => 'html-table-column-background',
                'kind' => (string) ($group['kind'] ?? ''),
                'startColumn' => min($columns),
                'endColumn' => max($columns) + 1,
                'span' => count($columns),
                'columns' => $columns,
                'sourceElement' => (string) ($background['sourceElement'] ?? ''),
                'backgroundColor' => (string) ($background['backgroundColor'] ?? ''),
                'backgroundColorSource' => (string) ($background['backgroundColorSource'] ?? ''),
            ];

            foreach (['colgroupIndex', 'colIndex', 'sourceSpan'] as $key) {
                if (isset($group[$key]) && is_numeric($group[$key])) {
                    $record[$key] = (int) $group[$key];
                }
            }

            $spanOffsets = self::intList($group['spanOffsets'] ?? []);
            if ($spanOffsets !== []) {
                $record['spanOffsets'] = $spanOffsets;
            }

            foreach (['legacyBackgroundColor', 'cssBackgroundColor'] as $key) {
                $value = trim((string) ($background[$key] ?? ''));
                if ($value !== '') {
                    $record[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($background[$key] ?? null) ? $background[$key] : [];
                if ($attributes !== []) {
                    $record[$key] = $attributes;
                }
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function columnBackgroundFromSource(array $source): array
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) ? $source['colAttributes'] : [];
        $background = self::columnBackgroundFromSourceAttributes($colAttributes, 'col');
        if ($background !== []) {
            return $background;
        }

        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];

        return self::columnBackgroundFromSourceAttributes($colgroupAttributes, 'colgroup');
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @return array<string, mixed>
     */
    private static function columnBackgroundFromSourceAttributes(array $sourceAttributes, string $sourceElement): array
    {
        $htmlAttributes = self::stringAttributeMap($sourceAttributes['htmlAttributes'] ?? [], true);
        if ($htmlAttributes === []) {
            return [];
        }

        $legacyBackgroundColor = self::normalizeTableBackgroundColorAttribute((string) ($htmlAttributes['bgcolor'] ?? ''));
        $cssBackgroundColor = self::normalizeTableBackgroundStyleAttribute((string) ($htmlAttributes['style'] ?? ''));
        if ($legacyBackgroundColor === '' && $cssBackgroundColor === '') {
            return [];
        }

        $attributes = [];
        if ($cssBackgroundColor !== '') {
            $attributes['background-color'] = $cssBackgroundColor;
        }
        if ($legacyBackgroundColor !== '') {
            $attributes['bgcolor'] = $legacyBackgroundColor;
        }

        $record = [
            'source' => 'html-table-column-background',
            'sourceElement' => $sourceElement,
            'attributes' => $attributes,
            'backgroundColor' => $cssBackgroundColor !== '' ? $cssBackgroundColor : $legacyBackgroundColor,
            'backgroundColorSource' => $cssBackgroundColor !== '' ? 'style' : 'bgcolor',
            'sourceAttributes' => $sourceAttributes,
        ];

        if ($legacyBackgroundColor !== '') {
            $record['legacyBackgroundColor'] = $legacyBackgroundColor;
        }
        if ($cssBackgroundColor !== '') {
            $record['cssBackgroundColor'] = $cssBackgroundColor;
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function columnBackgroundColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function columnBackgroundStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnBackgroundWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-column-background-require-raw-html', 'raw-html-column-background'],
            'asciidoc' => ['asciidoc-column-background-review-required', 'column-background-review'],
            'latex' => ['latex-column-background-review-required', 'table-column-background-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::columnBackgrounds(self::columnGroups($table, self::columnCount($table)));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'column-background',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-column-background',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnBackgroundCount' => count($records),
            'columns' => self::columnBackgroundColumns($records),
            'colors' => self::columnBackgroundStringValues($records, 'backgroundColor'),
            'backgroundColorSources' => self::columnBackgroundStringValues($records, 'backgroundColorSource'),
            'sourceElements' => self::columnBackgroundStringValues($records, 'sourceElement'),
            'backgrounds' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $columnGroups
     * @return list<array<string, mixed>>
     */
    private static function columnBorderPresentations(array $columnGroups): array
    {
        $records = [];
        foreach ($columnGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $source = is_array($group['source'] ?? null) ? $group['source'] : [];
            $border = self::columnBorderPresentationFromSource($source);
            if ($border === []) {
                continue;
            }

            $columns = self::intList($group['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $record = [
                'source' => 'html-table-column-border-presentation',
                'kind' => (string) ($group['kind'] ?? ''),
                'startColumn' => min($columns),
                'endColumn' => max($columns) + 1,
                'span' => count($columns),
                'columns' => $columns,
                'sourceElement' => (string) ($border['sourceElement'] ?? ''),
            ];

            foreach (['colgroupIndex', 'colIndex', 'sourceSpan'] as $key) {
                if (isset($group[$key]) && is_numeric($group[$key])) {
                    $record[$key] = (int) $group[$key];
                }
            }

            $spanOffsets = self::intList($group['spanOffsets'] ?? []);
            if ($spanOffsets !== []) {
                $record['spanOffsets'] = $spanOffsets;
            }

            foreach (['borderColor', 'borderStyle', 'borderWidth'] as $key) {
                $value = trim((string) ($border[$key] ?? ''));
                if ($value !== '') {
                    $record[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($border[$key] ?? null) ? $border[$key] : [];
                if ($attributes !== []) {
                    $record[$key] = $attributes;
                }
            }

            $borderEdges = is_array($border['borderEdges'] ?? null) ? $border['borderEdges'] : [];
            if ($borderEdges !== []) {
                $record['borderEdges'] = $borderEdges;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function columnBorderPresentationFromSource(array $source): array
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) ? $source['colAttributes'] : [];
        $border = self::columnBorderPresentationFromSourceAttributes($colAttributes, 'col');
        if ($border !== []) {
            return $border;
        }

        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];

        return self::columnBorderPresentationFromSourceAttributes($colgroupAttributes, 'colgroup');
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @return array<string, mixed>
     */
    private static function columnBorderPresentationFromSourceAttributes(array $sourceAttributes, string $sourceElement): array
    {
        $htmlAttributes = self::stringAttributeMap($sourceAttributes['htmlAttributes'] ?? [], true);
        foreach (self::stringAttributeMap($sourceAttributes['attributes'] ?? [], false) as $name => $value) {
            if (!array_key_exists($name, $htmlAttributes)) {
                $htmlAttributes[$name] = $value;
            }
        }

        $style = trim((string) ($htmlAttributes['style'] ?? ''));
        if ($style === '') {
            return [];
        }

        $attributes = [];
        $borderColor = self::normalizeTableBorderColorStyleAttribute($style);
        if ($borderColor !== '') {
            $attributes['border-color'] = $borderColor;
        }

        $borderStyle = self::normalizeTableBorderStyleStyleAttribute($style);
        if ($borderStyle !== '') {
            $attributes['border-style'] = $borderStyle;
        }

        $borderWidth = self::normalizeTableBorderWidthStyleAttribute($style);
        if ($borderWidth !== '') {
            $attributes['border-width'] = $borderWidth;
        }

        $borderEdges = self::normalizeTableBorderSideStyleAttributes($style);
        foreach ($borderEdges as $edgeRecord) {
            $edgeAttributes = is_array($edgeRecord['attributes'] ?? null) ? $edgeRecord['attributes'] : [];
            foreach ($edgeAttributes as $name => $value) {
                $attributes[$name] = $value;
            }
        }

        if ($attributes === []) {
            return [];
        }

        ksort($attributes);
        $record = [
            'source' => 'html-table-column-border-presentation',
            'sourceElement' => $sourceElement,
            'attributes' => $attributes,
            'sourceAttributes' => $sourceAttributes,
        ];
        if ($borderColor !== '') {
            $record['borderColor'] = $borderColor;
        }
        if ($borderStyle !== '') {
            $record['borderStyle'] = $borderStyle;
        }
        if ($borderWidth !== '') {
            $record['borderWidth'] = $borderWidth;
        }
        if ($borderEdges !== []) {
            $record['borderEdges'] = $borderEdges;
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function columnBorderPresentationColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function columnBorderPresentationStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private static function columnBorderPresentationEdgeCount(array $records): int
    {
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            $count += count($record['borderEdges']);
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function columnBorderPresentationEdgeStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            foreach ($record['borderEdges'] as $edge) {
                if (!is_array($edge)) {
                    continue;
                }

                $value = trim((string) ($edge[$key] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnBorderPresentationWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-column-border-presentation-require-raw-html', 'raw-html-column-border-presentation'],
            'asciidoc' => ['asciidoc-column-border-presentation-review-required', 'column-border-presentation-review'],
            'latex' => ['latex-column-border-presentation-review-required', 'table-column-border-presentation-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::columnBorderPresentations(self::columnGroups($table, self::columnCount($table)));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'column-border-presentation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-column-border-presentation',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnBorderPresentationCount' => count($records),
            'columns' => self::columnBorderPresentationColumns($records),
            'colors' => self::columnBorderPresentationStringValues($records, 'borderColor'),
            'styles' => self::columnBorderPresentationStringValues($records, 'borderStyle'),
            'widths' => self::columnBorderPresentationStringValues($records, 'borderWidth'),
            'sourceElements' => self::columnBorderPresentationStringValues($records, 'sourceElement'),
            'edgeCount' => self::columnBorderPresentationEdgeCount($records),
            'edges' => self::columnBorderPresentationEdgeStringValues($records, 'edge'),
            'edgeColors' => self::columnBorderPresentationEdgeStringValues($records, 'borderColor'),
            'edgeStyles' => self::columnBorderPresentationEdgeStringValues($records, 'borderStyle'),
            'edgeWidths' => self::columnBorderPresentationEdgeStringValues($records, 'borderWidth'),
            'records' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function cellDecimalAlignments(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $node = $record['node'] ?? null;
            $alignment = self::cellDecimalAlignmentFromNode($node);
            if ($alignment === []) {
                continue;
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $cell = [
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => min($columns),
                'endColumn' => max($columns) + 1,
                'columns' => $columns,
                'source' => 'html-table-cell-char-alignment',
                'alignment' => 'char',
                'char' => (string) ($alignment['char'] ?? ''),
                'charoff' => (string) ($alignment['charoff'] ?? ''),
                'text' => $node instanceof AstNode ? self::plainText($node) : '',
            ];

            foreach (['sourceRow', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'rawColspan', 'rawRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            foreach (['sourceRows', 'globalRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            $htmlAttributes = is_array($alignment['htmlAttributes'] ?? null) ? $alignment['htmlAttributes'] : [];
            if ($htmlAttributes !== []) {
                $cell['htmlAttributes'] = $htmlAttributes;
            }

            $records[] = $cell;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellDecimalAlignmentFromNode(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $align = strtolower(self::sourceHtmlAttribute($node, 'align'));
        $char = self::sourceHtmlAttribute($node, 'char');
        $charoff = self::sourceHtmlAttribute($node, 'charoff');
        if ($align !== 'char' && $char === '' && $charoff === '') {
            return [];
        }

        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);

        return [
            'source' => 'html-table-cell-char-alignment',
            'alignment' => 'char',
            'char' => $char,
            'charoff' => $charoff,
            'htmlAttributes' => $htmlAttributes,
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function cellDecimalAlignmentColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellDecimalAlignmentStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellDecimalAlignmentWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-cell-char-alignment-require-raw-html', 'raw-html-cell-char-alignment'],
            'asciidoc' => ['asciidoc-cell-char-alignment-review-required', 'cell-char-alignment-review'],
            'latex' => ['latex-cell-char-alignment-review-required', 'decimal-cell-alignment-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::cellDecimalAlignments(self::cellCoverage($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'cell-char-alignment',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-cell-char-alignment',
            'caption' => (string) $table->attr('caption', ''),
            'cellCount' => count($records),
            'columns' => self::cellDecimalAlignmentColumns($records),
            'chars' => self::cellDecimalAlignmentStringValues($records, 'char'),
            'charOffsets' => self::cellDecimalAlignmentStringValues($records, 'charoff'),
            'cells' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function cellBackgrounds(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $node = $record['node'] ?? null;
            $background = self::cellBackgroundFromNode($node);
            if ($background === []) {
                continue;
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $cell = [
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => min($columns),
                'endColumn' => max($columns) + 1,
                'columns' => $columns,
                'source' => 'html-table-cell-background',
                'backgroundColor' => (string) ($background['backgroundColor'] ?? ''),
                'backgroundColorSource' => (string) ($background['backgroundColorSource'] ?? ''),
                'text' => $node instanceof AstNode ? self::plainText($node) : '',
            ];

            foreach (['sourceRow', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'rawColspan', 'rawRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            foreach (['sourceRows', 'globalRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            foreach (['legacyBackgroundColor', 'cssBackgroundColor'] as $key) {
                $value = trim((string) ($background[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($background[$key] ?? null) ? $background[$key] : [];
                if ($attributes !== []) {
                    $cell[$key] = $attributes;
                }
            }

            $records[] = $cell;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellBackgroundFromNode(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $legacyBackgroundColor = self::normalizeTableBackgroundColorAttribute(
            self::sourceHtmlAttribute($node, 'bgcolor')
        );
        $cssBackgroundColor = self::normalizeTableBackgroundStyleAttribute(
            self::sourceHtmlAttribute($node, 'style')
        );
        if ($legacyBackgroundColor === '' && $cssBackgroundColor === '') {
            return [];
        }

        $attributes = [];
        if ($cssBackgroundColor !== '') {
            $attributes['background-color'] = $cssBackgroundColor;
        }
        if ($legacyBackgroundColor !== '') {
            $attributes['bgcolor'] = $legacyBackgroundColor;
        }

        $sourceAttributes = [];
        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes !== []) {
            $sourceAttributes['htmlAttributes'] = $htmlAttributes;
        }
        $nodeAttributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($nodeAttributes !== []) {
            $sourceAttributes['attributes'] = $nodeAttributes;
        }

        $record = [
            'source' => 'html-table-cell-background',
            'attributes' => $attributes,
            'backgroundColor' => $cssBackgroundColor !== '' ? $cssBackgroundColor : $legacyBackgroundColor,
            'backgroundColorSource' => $cssBackgroundColor !== '' ? 'style' : 'bgcolor',
        ];

        if ($legacyBackgroundColor !== '') {
            $record['legacyBackgroundColor'] = $legacyBackgroundColor;
        }
        if ($cssBackgroundColor !== '') {
            $record['cssBackgroundColor'] = $cssBackgroundColor;
        }
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function cellBackgroundColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellBackgroundStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellBackgroundWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-cell-background-require-raw-html', 'raw-html-cell-background'],
            'asciidoc' => ['asciidoc-cell-background-review-required', 'cell-background-review'],
            'latex' => ['latex-cell-background-review-required', 'table-cell-background-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::cellBackgrounds(self::cellCoverage($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'cell-background',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-cell-background',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'cellCount' => count($records),
            'columns' => self::cellBackgroundColumns($records),
            'sections' => self::cellBackgroundStringValues($records, 'section'),
            'colors' => self::cellBackgroundStringValues($records, 'backgroundColor'),
            'backgroundColorSources' => self::cellBackgroundStringValues($records, 'backgroundColorSource'),
            'cells' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function cellBorderPresentations(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $node = $record['node'] ?? null;
            $border = self::cellBorderPresentationFromNode($node);
            if ($border === []) {
                continue;
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $cell = [
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => min($columns),
                'endColumn' => max($columns) + 1,
                'columns' => $columns,
                'source' => 'html-table-cell-border-presentation',
                'text' => $node instanceof AstNode ? self::plainText($node) : '',
            ];

            foreach (['sourceRow', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'rawColspan', 'rawRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            foreach (['sourceRows', 'globalRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            foreach (['borderColor', 'borderStyle', 'borderWidth'] as $key) {
                $value = trim((string) ($border[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($border[$key] ?? null) ? $border[$key] : [];
                if ($attributes !== []) {
                    $cell[$key] = $attributes;
                }
            }

            $borderEdges = is_array($border['borderEdges'] ?? null) ? $border['borderEdges'] : [];
            if ($borderEdges !== []) {
                $cell['borderEdges'] = $borderEdges;
            }

            $records[] = $cell;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellBorderPresentationFromNode(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $style = self::sourceHtmlAttribute($node, 'style');
        if ($style === '') {
            return [];
        }

        $attributes = [];
        $borderColor = self::normalizeTableBorderColorStyleAttribute($style);
        if ($borderColor !== '') {
            $attributes['border-color'] = $borderColor;
        }

        $borderStyle = self::normalizeTableBorderStyleStyleAttribute($style);
        if ($borderStyle !== '') {
            $attributes['border-style'] = $borderStyle;
        }

        $borderWidth = self::normalizeTableBorderWidthStyleAttribute($style);
        if ($borderWidth !== '') {
            $attributes['border-width'] = $borderWidth;
        }

        $borderEdges = self::normalizeTableBorderSideStyleAttributes($style);
        foreach ($borderEdges as $edgeRecord) {
            $edgeAttributes = is_array($edgeRecord['attributes'] ?? null) ? $edgeRecord['attributes'] : [];
            foreach ($edgeAttributes as $name => $value) {
                $attributes[$name] = $value;
            }
        }

        if ($attributes === []) {
            return [];
        }

        ksort($attributes);
        $sourceAttributes = [];
        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes !== []) {
            $sourceAttributes['htmlAttributes'] = $htmlAttributes;
        }
        $nodeAttributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($nodeAttributes !== []) {
            $sourceAttributes['attributes'] = $nodeAttributes;
        }

        $record = [
            'source' => 'html-table-cell-border-presentation',
            'attributes' => $attributes,
        ];
        if ($borderColor !== '') {
            $record['borderColor'] = $borderColor;
        }
        if ($borderStyle !== '') {
            $record['borderStyle'] = $borderStyle;
        }
        if ($borderWidth !== '') {
            $record['borderWidth'] = $borderWidth;
        }
        if ($borderEdges !== []) {
            $record['borderEdges'] = $borderEdges;
        }
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeTableBorderSideStyleAttributes(string $style): array
    {
        $edges = [];
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name === '' || $value === '') {
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)$/', $name, $match) === 1) {
                $edge = $match[1];
                $shorthand = self::normalizeTableBorderShorthandAttribute($value);
                if ($shorthand === []) {
                    continue;
                }

                $edges[$edge] = self::mergeTableBorderEdgeRecord(
                    $edges[$edge] ?? self::emptyTableBorderEdgeRecord($edge),
                    [
                        'property' => 'border-' . $edge,
                        'value' => self::tableBorderEdgeValue($shorthand),
                        'attributes' => ['border-' . $edge => self::tableBorderEdgeValue($shorthand)],
                        ...$shorthand,
                    ]
                );
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)-(color|style|width)$/', $name, $match) !== 1) {
                continue;
            }

            $edge = $match[1];
            $kind = $match[2];
            $normalized = match ($kind) {
                'color' => self::normalizeTableBackgroundColorAttribute($value),
                'style' => self::normalizeTableBorderLineStyleAttribute($value),
                'width' => self::normalizeTableBorderWidthToken(strtolower(trim($value))),
            };
            if ($normalized === '') {
                continue;
            }

            $field = match ($kind) {
                'color' => 'borderColor',
                'style' => 'borderStyle',
                'width' => 'borderWidth',
            };
            $edges[$edge] = self::mergeTableBorderEdgeRecord(
                $edges[$edge] ?? self::emptyTableBorderEdgeRecord($edge),
                [
                    'property' => 'border-' . $edge,
                    $field => $normalized,
                    'attributes' => [$name => $normalized],
                ]
            );
        }

        $records = [];
        foreach ($edges as $edgeRecord) {
            $attributes = is_array($edgeRecord['attributes'] ?? null) ? $edgeRecord['attributes'] : [];
            if ($attributes === []) {
                continue;
            }

            ksort($attributes);
            $edgeRecord['attributes'] = $attributes;
            if (!isset($edgeRecord['value'])) {
                $value = self::tableBorderEdgeValue($edgeRecord);
                if ($value !== '') {
                    $edgeRecord['value'] = $value;
                }
            }
            $records[] = $edgeRecord;
        }

        return $records;
    }

    /**
     * @return array{edge:string,property:string,attributes:array<string, string>}
     */
    private static function emptyTableBorderEdgeRecord(string $edge): array
    {
        return [
            'edge' => $edge,
            'property' => 'border-' . $edge,
            'attributes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $update
     * @return array<string, mixed>
     */
    private static function mergeTableBorderEdgeRecord(array $base, array $update): array
    {
        $attributes = is_array($base['attributes'] ?? null) ? $base['attributes'] : [];
        foreach (is_array($update['attributes'] ?? null) ? $update['attributes'] : [] as $name => $value) {
            $attributes[(string) $name] = (string) $value;
        }
        unset($update['attributes']);

        foreach ($update as $key => $value) {
            if ($value === '') {
                continue;
            }

            $base[(string) $key] = $value;
        }
        $base['attributes'] = $attributes;

        return $base;
    }

    /**
     * @return array{borderColor?:string,borderStyle?:string,borderWidth?:string}
     */
    private static function normalizeTableBorderShorthandAttribute(string $value): array
    {
        $tokens = self::cssValueTokens($value);
        if ($tokens === []) {
            return [];
        }

        $record = [];
        foreach ($tokens as $token) {
            $normalizedWidth = self::normalizeTableBorderWidthToken(strtolower($token));
            if ($normalizedWidth !== '' && !isset($record['borderWidth'])) {
                $record['borderWidth'] = $normalizedWidth;
                continue;
            }

            $normalizedStyle = self::normalizeTableBorderLineStyleAttribute($token);
            if ($normalizedStyle !== '' && !isset($record['borderStyle'])) {
                $record['borderStyle'] = $normalizedStyle;
                continue;
            }

            $normalizedColor = self::normalizeTableBackgroundColorAttribute($token);
            if ($normalizedColor !== '' && !isset($record['borderColor'])) {
                $record['borderColor'] = $normalizedColor;
                continue;
            }

            return [];
        }

        return $record;
    }

    /**
     * @return list<string>
     */
    private static function cssValueTokens(string $value): array
    {
        $tokens = [];
        $token = '';
        $depth = 0;
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];
            if ($char === '(') {
                $depth++;
                $token .= $char;
                continue;
            }

            if ($char === ')' && $depth > 0) {
                $depth--;
                $token .= $char;
                continue;
            }

            if ($depth === 0 && ctype_space($char)) {
                if ($token !== '') {
                    $tokens[] = $token;
                    $token = '';
                }
                continue;
            }

            $token .= $char;
        }

        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function tableBorderEdgeValue(array $record): string
    {
        $values = [];
        foreach (['borderWidth', 'borderStyle', 'borderColor'] as $key) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode(' ', $values);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function cellBorderPresentationColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellBorderPresentationStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private static function cellBorderPresentationEdgeCount(array $records): int
    {
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            $count += count($record['borderEdges']);
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellBorderPresentationEdgeStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            foreach ($record['borderEdges'] as $edge) {
                if (!is_array($edge)) {
                    continue;
                }

                $value = trim((string) ($edge[$key] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellBorderPresentationWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-cell-border-presentation-require-raw-html', 'raw-html-cell-border-presentation'],
            'asciidoc' => ['asciidoc-cell-border-presentation-review-required', 'cell-border-presentation-review'],
            'latex' => ['latex-cell-border-presentation-review-required', 'table-cell-border-presentation-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::cellBorderPresentations(self::cellCoverage($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'cell-border-presentation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-cell-border-presentation',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'cellCount' => count($records),
            'columns' => self::cellBorderPresentationColumns($records),
            'sections' => self::cellBorderPresentationStringValues($records, 'section'),
            'colors' => self::cellBorderPresentationStringValues($records, 'borderColor'),
            'styles' => self::cellBorderPresentationStringValues($records, 'borderStyle'),
            'widths' => self::cellBorderPresentationStringValues($records, 'borderWidth'),
            'edgeCount' => self::cellBorderPresentationEdgeCount($records),
            'edges' => self::cellBorderPresentationEdgeStringValues($records, 'edge'),
            'edgeColors' => self::cellBorderPresentationEdgeStringValues($records, 'borderColor'),
            'edgeStyles' => self::cellBorderPresentationEdgeStringValues($records, 'borderStyle'),
            'edgeWidths' => self::cellBorderPresentationEdgeStringValues($records, 'borderWidth'),
            'cells' => $records,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellNoWraps(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $node = $record['node'] ?? null;
            $noWrap = self::cellNoWrapFromNode($node);
            if ($noWrap === []) {
                continue;
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $cell = [
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => min($columns),
                'endColumn' => max($columns) + 1,
                'columns' => $columns,
                'source' => 'html-table-cell-nowrap',
                'attribute' => 'nowrap',
                'attributeValue' => (string) ($noWrap['attributeValue'] ?? ''),
                'normalizedValue' => 'nowrap',
                'text' => $node instanceof AstNode ? self::plainText($node) : '',
            ];

            foreach (['sourceRow', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'rawColspan', 'rawRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            foreach (['sourceRows', 'globalRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            foreach (['htmlAttributes', 'attributes'] as $key) {
                $attributes = is_array($noWrap[$key] ?? null) ? $noWrap[$key] : [];
                if ($attributes !== []) {
                    $cell[$key] = $attributes;
                }
            }

            $records[] = $cell;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellNoWrapFromNode(mixed $node): array
    {
        $presence = self::sourceHtmlAttributePresence($node, 'nowrap');
        if (($presence['present'] ?? false) !== true) {
            return [];
        }

        $value = (string) ($presence['value'] ?? '');
        if (!self::isTruthyNoWrapAttribute($value)) {
            return [];
        }

        $source = (string) ($presence['source'] ?? '');
        $record = [
            'source' => 'html-table-cell-nowrap',
            'attribute' => 'nowrap',
            'attributeValue' => $value,
            'normalizedValue' => 'nowrap',
        ];

        if ($source === 'attributes') {
            $record['attributes'] = ['nowrap' => $value];
        } else {
            $record['htmlAttributes'] = ['nowrap' => $value];
        }

        return $record;
    }

    private static function isTruthyNoWrapAttribute(string $value): bool
    {
        $value = strtolower(trim($value));

        return !in_array($value, ['false', '0', 'no', 'off'], true);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function cellNoWrapColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellNoWrapStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellNoWrapWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-cell-nowrap-require-raw-html', 'raw-html-cell-nowrap'],
            'asciidoc' => ['asciidoc-cell-nowrap-review-required', 'cell-nowrap-review'],
            'latex' => ['latex-cell-nowrap-review-required', 'table-cell-nowrap-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::cellNoWraps(self::cellCoverage($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'cell-nowrap',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-cell-nowrap',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'cellCount' => count($records),
            'columns' => self::cellNoWrapColumns($records),
            'sections' => self::cellNoWrapStringValues($records, 'section'),
            'cells' => $records,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellDimensions(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            if (!is_array($record)) {
                continue;
            }

            $node = $record['node'] ?? null;
            $dimensions = self::cellDimensionFromNode($node);
            if ($dimensions === []) {
                continue;
            }

            $columns = self::intList($record['columns'] ?? []);
            if ($columns === []) {
                continue;
            }

            $cell = [
                'section' => (string) ($record['section'] ?? ''),
                'rowRole' => (string) ($record['rowRole'] ?? ''),
                'row' => max(0, (int) ($record['row'] ?? 0)),
                'globalRow' => max(0, (int) ($record['globalRow'] ?? 0)),
                'column' => min($columns),
                'endColumn' => max($columns) + 1,
                'columns' => $columns,
                'source' => 'html-table-cell-dimensions',
                'attributes' => is_array($dimensions['attributes'] ?? null) ? $dimensions['attributes'] : [],
                'text' => $node instanceof AstNode ? self::plainText($node) : '',
            ];

            foreach (['width', 'widthType', 'height', 'heightType', 'widthSource', 'heightSource'] as $key) {
                $value = trim((string) ($dimensions[$key] ?? ''));
                if ($value !== '') {
                    $cell[$key] = $value;
                }
            }

            foreach (['widthValue', 'heightValue'] as $key) {
                if (isset($dimensions[$key]) && is_numeric($dimensions[$key])) {
                    $cell[$key] = (float) $dimensions[$key];
                }
            }

            foreach (['sourceRow', 'sourceCell', 'sourceColumn', 'colspan', 'rowspan', 'rawColspan', 'rawRowspan'] as $key) {
                if (isset($record[$key]) && is_numeric($record[$key])) {
                    $cell[$key] = (int) $record[$key];
                }
            }

            foreach (['sourceRows', 'globalRows'] as $key) {
                $values = self::intList($record[$key] ?? []);
                if ($values !== []) {
                    $cell[$key] = $values;
                }
            }

            if (($record['headerCell'] ?? false) === true) {
                $cell['headerCell'] = true;
            }

            $sourceAttributes = is_array($dimensions['sourceAttributes'] ?? null) ? $dimensions['sourceAttributes'] : [];
            if ($sourceAttributes !== []) {
                $cell['sourceAttributes'] = $sourceAttributes;
            }

            $records[] = $cell;
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cellDimensionFromNode(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $recordAttributes = [];
        $width = [];
        $height = [];
        $widthSource = '';
        $heightSource = '';

        $widthPresence = self::sourceHtmlAttributePresence($node, 'width');
        if (($widthPresence['present'] ?? false) === true) {
            $width = self::normalizeTableWidthAttribute((string) ($widthPresence['value'] ?? ''));
            if ($width !== []) {
                $recordAttributes['width'] = (string) $width['width'];
                $widthSource = 'width';
            }
        }

        $heightPresence = self::sourceHtmlAttributePresence($node, 'height');
        if (($heightPresence['present'] ?? false) === true) {
            $height = self::normalizeTableHeightAttribute((string) ($heightPresence['value'] ?? ''));
            if ($height !== []) {
                $recordAttributes['height'] = (string) $height['height'];
                $heightSource = 'height';
            }
        }

        $stylePresence = self::sourceHtmlAttributePresence($node, 'style');
        if (($stylePresence['present'] ?? false) === true) {
            $style = (string) ($stylePresence['value'] ?? '');
            $styleWidth = self::normalizeTableWidthAttribute(self::tableStyleDeclarationValue($style, 'width'));
            if ($styleWidth !== []) {
                $width = $styleWidth;
                $recordAttributes['width'] = (string) $styleWidth['width'];
                $widthSource = 'style';
            }

            $styleHeight = self::normalizeTableHeightAttribute(self::tableStyleDeclarationValue($style, 'height'));
            if ($styleHeight !== []) {
                $height = $styleHeight;
                $recordAttributes['height'] = (string) $styleHeight['height'];
                $heightSource = 'style';
            }
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $record = [
            'source' => 'html-table-cell-dimensions',
            'attributes' => $recordAttributes,
        ];

        if ($width !== []) {
            $record['width'] = (string) $width['width'];
            $record['widthType'] = (string) $width['widthType'];
            $record['widthValue'] = (float) $width['widthValue'];
            $record['widthSource'] = $widthSource;
        }

        if ($height !== []) {
            $record['height'] = (string) $height['height'];
            $record['heightType'] = (string) $height['heightType'];
            $record['heightValue'] = (float) $height['heightValue'];
            $record['heightSource'] = $heightSource;
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    private static function tableStyleDeclarationValue(string $style, string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return '';
        }

        foreach (explode(';', $style) as $declaration) {
            [$declarationName, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($declarationName)) === $name) {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function cellDimensionColumns(array $records): array
    {
        $columns = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            foreach (self::intList($record['columns'] ?? []) as $column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function cellDimensionStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cellDimensionWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-cell-dimensions-require-raw-html', 'raw-html-cell-dimensions'],
            'asciidoc' => ['asciidoc-cell-dimensions-review-required', 'cell-dimensions-review'],
            'latex' => ['latex-cell-dimensions-review-required', 'table-cell-dimensions-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::cellDimensions(self::cellCoverage($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'cell-dimensions',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-cell-dimensions',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'cellCount' => count($records),
            'columns' => self::cellDimensionColumns($records),
            'sections' => self::cellDimensionStringValues($records, 'section'),
            'widthTypes' => self::cellDimensionStringValues($records, 'widthType'),
            'heightTypes' => self::cellDimensionStringValues($records, 'heightType'),
            'widthSources' => self::cellDimensionStringValues($records, 'widthSource'),
            'heightSources' => self::cellDimensionStringValues($records, 'heightSource'),
            'cells' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private static function sectionBackgrounds(array $sections): array
    {
        $records = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $node = $section['node'] ?? null;
            $background = self::rowBackgroundFromNode($node);
            if ($background === []) {
                continue;
            }

            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $rowCount = count($rowEntries);
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $globalRowRange = self::sectionGridRowRange($section, $globalRowStart, $rowCount);
            $record = [
                'section' => (string) ($section['section'] ?? ''),
                'rowCount' => $rowCount,
                'rowRange' => [0, $rowCount],
                'globalRowStart' => $globalRowRange[0],
                'globalRowEnd' => $globalRowRange[1],
                'globalRowRange' => $globalRowRange,
                'columnCount' => max(0, (int) ($section['columnCount'] ?? 0)),
                'source' => 'html-table-section-background',
                'backgroundColor' => (string) ($background['backgroundColor'] ?? ''),
                'backgroundColorSource' => (string) ($background['backgroundColorSource'] ?? ''),
            ];

            foreach (['legacyBackgroundColor', 'cssBackgroundColor'] as $key) {
                $value = trim((string) ($background[$key] ?? ''));
                if ($value !== '') {
                    $record[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($background[$key] ?? null) ? $background[$key] : [];
                if ($attributes !== []) {
                    $record[$key] = $attributes;
                }
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sectionBackgroundWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-section-background-require-raw-html', 'raw-html-section-background'],
            'asciidoc' => ['asciidoc-section-background-review-required', 'section-background-review'],
            'latex' => ['latex-section-background-review-required', 'table-section-background-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::sectionBackgrounds(self::sectionGrids($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'section-background',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-section-background',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'sectionCount' => count($records),
            'sections' => self::rowBackgroundStringValues($records, 'section'),
            'colors' => self::rowBackgroundStringValues($records, 'backgroundColor'),
            'backgroundColorSources' => self::rowBackgroundStringValues($records, 'backgroundColorSource'),
            'records' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private static function sectionBorderPresentations(array $sections): array
    {
        $records = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $node = $section['node'] ?? null;
            $border = self::rowBorderPresentationFromNode($node);
            if ($border === []) {
                continue;
            }

            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $rowCount = count($rowEntries);
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $globalRowRange = self::sectionGridRowRange($section, $globalRowStart, $rowCount);
            $record = [
                'section' => (string) ($section['section'] ?? ''),
                'rowCount' => $rowCount,
                'rowRange' => [0, $rowCount],
                'globalRowStart' => $globalRowRange[0],
                'globalRowEnd' => $globalRowRange[1],
                'globalRowRange' => $globalRowRange,
                'columnCount' => max(0, (int) ($section['columnCount'] ?? 0)),
                'source' => 'html-table-section-border-presentation',
            ];

            foreach (['borderColor', 'borderStyle', 'borderWidth'] as $key) {
                $value = trim((string) ($border[$key] ?? ''));
                if ($value !== '') {
                    $record[$key] = $value;
                }
            }

            foreach (['attributes', 'sourceAttributes'] as $key) {
                $attributes = is_array($border[$key] ?? null) ? $border[$key] : [];
                if ($attributes !== []) {
                    $record[$key] = $attributes;
                }
            }

            $borderEdges = is_array($border['borderEdges'] ?? null) ? $border['borderEdges'] : [];
            if ($borderEdges !== []) {
                $record['borderEdges'] = $borderEdges;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sectionBorderPresentationWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-section-border-presentation-require-raw-html', 'raw-html-section-border-presentation'],
            'asciidoc' => ['asciidoc-section-border-presentation-review-required', 'section-border-presentation-review'],
            'latex' => ['latex-section-border-presentation-review-required', 'table-section-border-presentation-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::sectionBorderPresentations(self::sectionGrids($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'section-border-presentation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-section-border-presentation',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'sectionCount' => count($records),
            'sections' => self::rowBorderPresentationStringValues($records, 'section'),
            'colors' => self::rowBorderPresentationStringValues($records, 'borderColor'),
            'styles' => self::rowBorderPresentationStringValues($records, 'borderStyle'),
            'widths' => self::rowBorderPresentationStringValues($records, 'borderWidth'),
            'edgeCount' => self::rowBorderPresentationEdgeCount($records),
            'edges' => self::rowBorderPresentationEdgeStringValues($records, 'edge'),
            'edgeColors' => self::rowBorderPresentationEdgeStringValues($records, 'borderColor'),
            'edgeStyles' => self::rowBorderPresentationEdgeStringValues($records, 'borderStyle'),
            'edgeWidths' => self::rowBorderPresentationEdgeStringValues($records, 'borderWidth'),
            'records' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private static function rowBackgrounds(array $sections): array
    {
        $records = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionName = (string) ($section['section'] ?? '');
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $columnCount = max(0, (int) ($section['columnCount'] ?? 0));
            foreach ($rowEntries as $rowIndex => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $row = $entry['row'] ?? null;
                $background = self::rowBackgroundFromNode($row);
                if ($background === []) {
                    continue;
                }

                $globalRow = $globalRowStart + max(0, (int) $rowIndex);
                $record = [
                    'section' => $sectionName,
                    'rowRole' => (string) ($entry['rowRole'] ?? ''),
                    'row' => max(0, (int) $rowIndex),
                    'globalRow' => $globalRow,
                    'rowRange' => [max(0, (int) $rowIndex), max(0, (int) $rowIndex) + 1],
                    'globalRowRange' => [$globalRow, $globalRow + 1],
                    'columnCount' => $columnCount,
                    'source' => 'html-table-row-background',
                    'backgroundColor' => (string) ($background['backgroundColor'] ?? ''),
                    'backgroundColorSource' => (string) ($background['backgroundColorSource'] ?? ''),
                    'text' => $row instanceof AstNode ? self::plainText($row) : '',
                    'headerRow' => (bool) ($entry['header'] ?? false),
                    'rowHeadColumns' => max(0, (int) ($entry['rowHeadColumns'] ?? 0)),
                ];

                foreach (['legacyBackgroundColor', 'cssBackgroundColor'] as $key) {
                    $value = trim((string) ($background[$key] ?? ''));
                    if ($value !== '') {
                        $record[$key] = $value;
                    }
                }

                foreach (['attributes', 'sourceAttributes'] as $key) {
                    $attributes = is_array($background[$key] ?? null) ? $background[$key] : [];
                    if ($attributes !== []) {
                        $record[$key] = $attributes;
                    }
                }

                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowBackgroundFromNode(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $legacyBackgroundColor = self::normalizeTableBackgroundColorAttribute(
            self::sourceHtmlAttribute($node, 'bgcolor')
        );
        $cssBackgroundColor = self::normalizeTableBackgroundStyleAttribute(
            self::sourceHtmlAttribute($node, 'style')
        );
        if ($legacyBackgroundColor === '' && $cssBackgroundColor === '') {
            return [];
        }

        $attributes = [];
        if ($cssBackgroundColor !== '') {
            $attributes['background-color'] = $cssBackgroundColor;
        }
        if ($legacyBackgroundColor !== '') {
            $attributes['bgcolor'] = $legacyBackgroundColor;
        }

        $sourceAttributes = [];
        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes !== []) {
            $sourceAttributes['htmlAttributes'] = $htmlAttributes;
        }
        $nodeAttributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($nodeAttributes !== []) {
            $sourceAttributes['attributes'] = $nodeAttributes;
        }

        $record = [
            'source' => 'html-table-row-background',
            'attributes' => $attributes,
            'backgroundColor' => $cssBackgroundColor !== '' ? $cssBackgroundColor : $legacyBackgroundColor,
            'backgroundColorSource' => $cssBackgroundColor !== '' ? 'style' : 'bgcolor',
        ];

        if ($legacyBackgroundColor !== '') {
            $record['legacyBackgroundColor'] = $legacyBackgroundColor;
        }
        if ($cssBackgroundColor !== '') {
            $record['cssBackgroundColor'] = $cssBackgroundColor;
        }
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function rowBackgroundRows(array $records, string $key): array
    {
        $rows = [];
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record[$key]) || !is_numeric($record[$key])) {
                continue;
            }

            $rows[] = max(0, (int) $record[$key]);
        }

        return array_values(array_unique($rows));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function rowBackgroundStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowBackgroundWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-row-background-require-raw-html', 'raw-html-row-background'],
            'asciidoc' => ['asciidoc-row-background-review-required', 'row-background-review'],
            'latex' => ['latex-row-background-review-required', 'table-row-background-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::rowBackgrounds(self::sectionGrids($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'row-background',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-row-background',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'rowCount' => count($records),
            'rows' => self::rowBackgroundRows($records, 'row'),
            'globalRows' => self::rowBackgroundRows($records, 'globalRow'),
            'sections' => self::rowBackgroundStringValues($records, 'section'),
            'colors' => self::rowBackgroundStringValues($records, 'backgroundColor'),
            'backgroundColorSources' => self::rowBackgroundStringValues($records, 'backgroundColorSource'),
            'records' => $records,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private static function rowBorderPresentations(array $sections): array
    {
        $records = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionName = (string) ($section['section'] ?? '');
            $globalRowStart = max(0, (int) ($section['globalRowStart'] ?? 0));
            $rowEntries = is_array($section['rowEntries'] ?? null) ? $section['rowEntries'] : [];
            $columnCount = max(0, (int) ($section['columnCount'] ?? 0));
            foreach ($rowEntries as $rowIndex => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $row = $entry['row'] ?? null;
                $border = self::rowBorderPresentationFromNode($row);
                if ($border === []) {
                    continue;
                }

                $globalRow = $globalRowStart + max(0, (int) $rowIndex);
                $record = [
                    'section' => $sectionName,
                    'rowRole' => (string) ($entry['rowRole'] ?? ''),
                    'row' => max(0, (int) $rowIndex),
                    'globalRow' => $globalRow,
                    'rowRange' => [max(0, (int) $rowIndex), max(0, (int) $rowIndex) + 1],
                    'globalRowRange' => [$globalRow, $globalRow + 1],
                    'columnCount' => $columnCount,
                    'source' => 'html-table-row-border-presentation',
                    'text' => $row instanceof AstNode ? self::plainText($row) : '',
                    'headerRow' => (bool) ($entry['header'] ?? false),
                    'rowHeadColumns' => max(0, (int) ($entry['rowHeadColumns'] ?? 0)),
                ];

                foreach (['borderColor', 'borderStyle', 'borderWidth'] as $key) {
                    $value = trim((string) ($border[$key] ?? ''));
                    if ($value !== '') {
                        $record[$key] = $value;
                    }
                }

                foreach (['attributes', 'sourceAttributes'] as $key) {
                    $attributes = is_array($border[$key] ?? null) ? $border[$key] : [];
                    if ($attributes !== []) {
                        $record[$key] = $attributes;
                    }
                }

                $borderEdges = is_array($border['borderEdges'] ?? null) ? $border['borderEdges'] : [];
                if ($borderEdges !== []) {
                    $record['borderEdges'] = $borderEdges;
                }

                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowBorderPresentationFromNode(mixed $node): array
    {
        $record = self::cellBorderPresentationFromNode($node);
        if ($record === []) {
            return [];
        }

        $record['source'] = 'html-table-row-border-presentation';

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<int>
     */
    private static function rowBorderPresentationRows(array $records, string $key): array
    {
        $rows = [];
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record[$key]) || !is_numeric($record[$key])) {
                continue;
            }

            $rows[] = max(0, (int) $record[$key]);
        }

        return array_values(array_unique($rows));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function rowBorderPresentationStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private static function rowBorderPresentationEdgeCount(array $records): int
    {
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            $count += count($record['borderEdges']);
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private static function rowBorderPresentationEdgeStringValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_array($record['borderEdges'] ?? null)) {
                continue;
            }

            foreach ($record['borderEdges'] as $edge) {
                if (!is_array($edge)) {
                    continue;
                }

                $value = trim((string) ($edge[$key] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowBorderPresentationWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-row-border-presentation-require-raw-html', 'raw-html-row-border-presentation'],
            'asciidoc' => ['asciidoc-row-border-presentation-review-required', 'row-border-presentation-review'],
            'latex' => ['latex-row-border-presentation-review-required', 'table-row-border-presentation-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $records = self::rowBorderPresentations(self::sectionGrids($table));
        if ($records === []) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'row-border-presentation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-row-border-presentation',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'rowCount' => count($records),
            'rows' => self::rowBorderPresentationRows($records, 'row'),
            'globalRows' => self::rowBorderPresentationRows($records, 'globalRow'),
            'sections' => self::rowBorderPresentationStringValues($records, 'section'),
            'colors' => self::rowBorderPresentationStringValues($records, 'borderColor'),
            'styles' => self::rowBorderPresentationStringValues($records, 'borderStyle'),
            'widths' => self::rowBorderPresentationStringValues($records, 'borderWidth'),
            'edgeCount' => self::rowBorderPresentationEdgeCount($records),
            'edges' => self::rowBorderPresentationEdgeStringValues($records, 'edge'),
            'edgeColors' => self::rowBorderPresentationEdgeStringValues($records, 'borderColor'),
            'edgeStyles' => self::rowBorderPresentationEdgeStringValues($records, 'borderStyle'),
            'edgeWidths' => self::rowBorderPresentationEdgeStringValues($records, 'borderWidth'),
            'records' => $records,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableSummaryWriterDiagnostics(AstNode $table, string $writer): array
    {
        $summary = self::sourceHtmlAttribute($table, 'summary');
        if ($summary === '') {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-summary-require-raw-html', 'raw-html-table-summary'],
            'asciidoc' => ['asciidoc-table-summary-review-required', 'table-summary-review'],
            'latex' => ['latex-table-summary-review-required', 'table-summary-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];
        $caption = (string) $table->attr('caption', '');

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-summary',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-summary',
            'attribute' => 'summary',
            'caption' => $caption,
            'hasCaption' => trim($caption) !== '',
            'summaryText' => $summary,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableLayoutWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableLayout = self::tableLayoutMetadata($table);
        if ($tableLayout === []) {
            return [];
        }

        $requirements = [
            'width' => [
                'markdown' => ['markdown-table-width-requires-raw-html', 'raw-html-table-width'],
                'asciidoc' => ['asciidoc-table-width-review-required', 'table-width-review'],
                'latex' => ['latex-table-width-review-required', 'table-width-review-comments'],
            ],
            'height' => [
                'markdown' => ['markdown-table-height-requires-raw-html', 'raw-html-table-height'],
                'asciidoc' => ['asciidoc-table-height-review-required', 'table-height-review'],
                'latex' => ['latex-table-height-review-required', 'table-height-review-comments'],
            ],
        ];

        $diagnostics = [];
        foreach ($requirements as $dimension => $writerRequirements) {
            if (!isset($writerRequirements[$writer]) || !array_key_exists($dimension, $tableLayout)) {
                continue;
            }

            [$code, $requiredFeature] = $writerRequirements[$writer];
            $diagnostic = [
                'code' => $code,
                'writer' => $writer,
                'reason' => 'table-layout-' . $dimension,
                'requiredFeature' => $requiredFeature,
                'source' => 'html-table-layout',
                'caption' => (string) $table->attr('caption', ''),
                'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
                'attributeCount' => count(is_array($tableLayout['attributes'] ?? null) ? $tableLayout['attributes'] : []),
                'attributes' => $tableLayout['attributes'] ?? [],
                'sourceAttributes' => $tableLayout['sourceAttributes'] ?? [],
            ];
            $diagnostic[$dimension] = (string) ($tableLayout[$dimension] ?? '');
            $diagnostic[$dimension . 'Type'] = (string) ($tableLayout[$dimension . 'Type'] ?? '');
            $diagnostic[$dimension . 'Value'] = is_numeric($tableLayout[$dimension . 'Value'] ?? null)
                ? (float) $tableLayout[$dimension . 'Value']
                : null;
            $diagnostics[] = $diagnostic;
        }

        $layoutModeRequirements = [
            'markdown' => ['markdown-table-layout-mode-requires-raw-html', 'raw-html-table-layout-mode'],
            'asciidoc' => ['asciidoc-table-layout-mode-review-required', 'table-layout-mode-review'],
            'latex' => ['latex-table-layout-mode-review-required', 'table-layout-mode-review-comments'],
        ];
        if (isset($layoutModeRequirements[$writer]) && array_key_exists('layoutMode', $tableLayout)) {
            [$code, $requiredFeature] = $layoutModeRequirements[$writer];
            $diagnostics[] = [
                'code' => $code,
                'writer' => $writer,
                'reason' => 'table-layout-mode',
                'requiredFeature' => $requiredFeature,
                'source' => 'html-table-layout-style',
                'caption' => (string) $table->attr('caption', ''),
                'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
                'attributeCount' => count(is_array($tableLayout['attributes'] ?? null) ? $tableLayout['attributes'] : []),
                'attributes' => $tableLayout['attributes'] ?? [],
                'sourceAttributes' => $tableLayout['sourceAttributes'] ?? [],
                'layoutMode' => (string) ($tableLayout['layoutMode'] ?? ''),
                'layoutModeSource' => (string) ($tableLayout['layoutModeSource'] ?? ''),
            ];
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableAlignmentWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableAlignment = self::tableAlignmentMetadata($table);
        if ($tableAlignment === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-alignment-requires-raw-html', 'raw-html-table-alignment'],
            'asciidoc' => ['asciidoc-table-alignment-review-required', 'table-alignment-review'],
            'latex' => ['latex-table-alignment-review-required', 'table-alignment-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-alignment',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-alignment',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'alignment' => (string) ($tableAlignment['alignment'] ?? ''),
            'attributeCount' => count(is_array($tableAlignment['attributes'] ?? null) ? $tableAlignment['attributes'] : []),
            'attributes' => $tableAlignment['attributes'] ?? [],
            'sourceAttributes' => $tableAlignment['sourceAttributes'] ?? [],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableFrameWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableFrame = self::tableFrameMetadata($table);
        if ($tableFrame === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-frame-requires-raw-html', 'raw-html-table-frame'],
            'asciidoc' => ['asciidoc-table-frame-review-required', 'table-frame-review'],
            'latex' => ['latex-table-frame-review-required', 'table-frame-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-frame',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-attributes',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'frame' => (string) ($tableFrame['frame'] ?? ''),
            'rules' => (string) ($tableFrame['rules'] ?? ''),
            'border' => (string) ($tableFrame['border'] ?? ''),
            'attributeCount' => count(is_array($tableFrame['attributes'] ?? null) ? $tableFrame['attributes'] : []),
            'attributes' => $tableFrame['attributes'] ?? [],
            'sourceAttributes' => $tableFrame['sourceAttributes'] ?? [],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableSpacingWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableSpacing = self::tableSpacingMetadata($table);
        if ($tableSpacing === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-spacing-requires-raw-html', 'raw-html-table-spacing'],
            'asciidoc' => ['asciidoc-table-spacing-review-required', 'table-spacing-review'],
            'latex' => ['latex-table-spacing-review-required', 'table-spacing-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-spacing',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-spacing',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'cellPadding' => (string) ($tableSpacing['cellPadding'] ?? ''),
            'cellSpacing' => (string) ($tableSpacing['cellSpacing'] ?? ''),
            'attributeCount' => count(is_array($tableSpacing['attributes'] ?? null) ? $tableSpacing['attributes'] : []),
            'attributes' => $tableSpacing['attributes'] ?? [],
            'sourceAttributes' => $tableSpacing['sourceAttributes'] ?? [],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableBackgroundWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableBackground = self::tableBackgroundMetadata($table);
        if ($tableBackground === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-background-requires-raw-html', 'raw-html-table-background'],
            'asciidoc' => ['asciidoc-table-background-review-required', 'table-background-review'],
            'latex' => ['latex-table-background-review-required', 'table-background-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-background',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-background',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'backgroundColor' => (string) ($tableBackground['backgroundColor'] ?? ''),
            'backgroundColorSource' => (string) ($tableBackground['backgroundColorSource'] ?? ''),
            'attributeCount' => count(is_array($tableBackground['attributes'] ?? null) ? $tableBackground['attributes'] : []),
            'attributes' => $tableBackground['attributes'] ?? [],
            'sourceAttributes' => $tableBackground['sourceAttributes'] ?? [],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableBorderCollapseWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableBorderCollapse = self::tableBorderCollapseMetadata($table);
        if ($tableBorderCollapse === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-border-collapse-requires-raw-html', 'raw-html-table-border-collapse'],
            'asciidoc' => ['asciidoc-table-border-collapse-review-required', 'table-border-collapse-review'],
            'latex' => ['latex-table-border-collapse-review-required', 'table-border-collapse-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-border-collapse',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-border-collapse',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'borderCollapse' => (string) ($tableBorderCollapse['borderCollapse'] ?? ''),
            'borderCollapseSource' => (string) ($tableBorderCollapse['borderCollapseSource'] ?? ''),
            'attributeCount' => count(is_array($tableBorderCollapse['attributes'] ?? null) ? $tableBorderCollapse['attributes'] : []),
            'attributes' => $tableBorderCollapse['attributes'] ?? [],
            'sourceAttributes' => $tableBorderCollapse['sourceAttributes'] ?? [],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableBorderPresentationWriterDiagnostics(AstNode $table, string $writer): array
    {
        $tableBorderPresentation = self::tableBorderPresentationMetadata($table);
        if ($tableBorderPresentation === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-border-presentation-requires-raw-html', 'raw-html-table-border-presentation'],
            'asciidoc' => ['asciidoc-table-border-presentation-review-required', 'table-border-presentation-review'],
            'latex' => ['latex-table-border-presentation-review-required', 'table-border-presentation-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];
        $diagnostic = [
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-border-presentation',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-border-presentation',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'attributeCount' => count(is_array($tableBorderPresentation['attributes'] ?? null) ? $tableBorderPresentation['attributes'] : []),
            'attributes' => $tableBorderPresentation['attributes'] ?? [],
            'sourceAttributes' => $tableBorderPresentation['sourceAttributes'] ?? [],
        ];
        foreach (['borderColor', 'borderColorSource', 'legacyBorderColor', 'cssBorderColor', 'borderStyle', 'borderWidth'] as $key) {
            if (isset($tableBorderPresentation[$key]) && trim((string) $tableBorderPresentation[$key]) !== '') {
                $diagnostic[$key] = (string) $tableBorderPresentation[$key];
            }
        }

        return [$diagnostic];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function tableDirectionWriterDiagnostics(AstNode $table, string $writer, array $coverage): array
    {
        $requirements = [
            'markdown' => ['markdown-table-direction-requires-raw-html', 'raw-html-table-direction'],
            'asciidoc' => ['asciidoc-table-direction-review-required', 'table-direction-review'],
            'latex' => ['latex-table-direction-review-required', 'table-direction-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $directionality = self::directionalityMetadata($table, self::sectionGrids($table), $coverage);
        $summary = is_array($directionality['summary'] ?? null)
            ? $directionality['summary']
            : self::emptyDirectionalitySummary();
        if (($summary['hasDirectionality'] ?? false) !== true) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-direction',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-dir',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'directions' => self::stringList($summary['directions'] ?? []),
            'directionRecordCount' => (int) ($summary['directionRecordCount'] ?? 0),
            'directionalCellCount' => (int) ($summary['directionalCellCount'] ?? 0),
            'explicitCellDirectionCount' => (int) ($summary['explicitCellDirectionCount'] ?? 0),
            'inheritedCellDirectionCount' => (int) ($summary['inheritedCellDirectionCount'] ?? 0),
            'table' => $directionality['table'] ?? [],
            'sections' => $directionality['sections'] ?? [],
            'rows' => $directionality['rows'] ?? [],
            'cells' => $directionality['cells'] ?? [],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function tableLocalizationWriterDiagnostics(AstNode $table, string $writer, array $coverage): array
    {
        $requirements = [
            'markdown' => ['markdown-table-localization-requires-raw-html', 'raw-html-table-localization'],
            'asciidoc' => ['asciidoc-table-localization-review-required', 'table-localization-review'],
            'latex' => ['latex-table-localization-review-required', 'table-localization-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $localization = self::localizationMetadata($table, self::sectionGrids($table), $coverage);
        $summary = is_array($localization['summary'] ?? null)
            ? $localization['summary']
            : self::emptyLocalizationSummary();
        if (($summary['hasLocalization'] ?? false) !== true) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-localization',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-localization',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'languages' => self::stringList($summary['languages'] ?? []),
            'translateStates' => self::stringList($summary['translateStates'] ?? []),
            'localizationRecordCount' => (int) ($summary['localizationRecordCount'] ?? 0),
            'localizedCellCount' => (int) ($summary['localizedCellCount'] ?? 0),
            'explicitCellLanguageCount' => (int) ($summary['explicitCellLanguageCount'] ?? 0),
            'inheritedCellLanguageCount' => (int) ($summary['inheritedCellLanguageCount'] ?? 0),
            'translatedCellCount' => (int) ($summary['translatedCellCount'] ?? 0),
            'table' => $localization['table'] ?? [],
            'sections' => $localization['sections'] ?? [],
            'rows' => $localization['rows'] ?? [],
            'cells' => $localization['cells'] ?? [],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function sourceAttributeWriterDiagnostics(AstNode $table, string $writer, array $coverage): array
    {
        $locations = self::nativeAttributeLocations($table, $coverage);
        if ($locations === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-table-source-attributes-require-raw-html', 'raw-html-table-attributes'],
            'asciidoc' => ['asciidoc-table-source-attributes-require-raw-html', 'raw-html-table-attributes'],
            'latex' => ['latex-table-source-attributes-review-required', 'table-attribute-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];
        $attributeCount = 0;
        $scopes = [];
        foreach ($locations as $location) {
            $attributeCount += count($location['attributes'] ?? []);
            $scope = trim((string) ($location['scope'] ?? ''));
            if ($scope !== '') {
                $scopes[] = $scope;
            }
        }

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'source-attributes',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-table-attributes',
            'caption' => (string) $table->attr('caption', ''),
            'attributeScopeCount' => count($locations),
            'attributeCount' => $attributeCount,
            'scopes' => array_values(array_unique($scopes)),
            'locations' => $locations,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function duplicateSourceIdWriterDiagnostics(AstNode $table, string $writer): array
    {
        $duplicates = self::duplicateSourceIdRecords(self::sourceIdRecords($table, self::cellCoverage($table)));
        if ($duplicates === []) {
            return [];
        }

        $requirements = [
            'markdown' => ['markdown-source-ids-duplicated', 'raw-html-table-source-ids'],
            'asciidoc' => ['asciidoc-source-ids-duplicated-review-required', 'source-id-review'],
            'latex' => ['latex-source-ids-duplicated-review-required', 'source-id-review-comments'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'duplicate-source-ids',
            'requiredFeature' => $requiredFeature,
            'source' => 'html-table-source-ids',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'duplicateIdCount' => count($duplicates),
            'duplicateLocationCount' => self::duplicateSourceIdLocationCount($duplicates),
            'duplicateIds' => self::duplicateSourceIdStrings($duplicates),
            'duplicateScopes' => self::duplicateSourceIdScopes($duplicates),
            'duplicates' => $duplicates,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnGroupWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-colgroup-provenance-require-raw-html', 'raw-html-colgroup-provenance'],
            'asciidoc' => ['asciidoc-colgroup-provenance-review-required', 'colgroup-provenance-review'],
            'latex' => ['latex-colgroup-provenance-review-required', 'colgroup-provenance-review'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $columnCount = self::columnCount($table);
        $columnGroups = self::columnGroups($table, $columnCount);
        if ($columnGroups === []) {
            return [];
        }

        $groupedColumnCount = 0;
        $sourceAttributeGroupCount = 0;
        $sourceAttributeCount = 0;
        $groupKinds = [];
        foreach ($columnGroups as $group) {
            $groupedColumnCount += count(is_array($group['columns'] ?? null) ? $group['columns'] : []);
            $sourceAttributes = self::columnGroupSourceAttributeCount($group);
            if ($sourceAttributes > 0) {
                $sourceAttributeGroupCount++;
                $sourceAttributeCount += $sourceAttributes;
            }

            $kind = trim((string) ($group['kind'] ?? ''));
            if ($kind !== '') {
                $groupKinds[] = $kind;
            }
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'colgroup-provenance',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-column-sources',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $columnCount,
            'columnGroupCount' => count($columnGroups),
            'groupedColumnCount' => $groupedColumnCount,
            'sourceAttributeGroupCount' => $sourceAttributeGroupCount,
            'sourceAttributeCount' => $sourceAttributeCount,
            'groupKinds' => array_values(array_unique($groupKinds)),
            'groups' => $columnGroups,
        ]];
    }

    /**
     * @param array<string, mixed> $group
     */
    private static function columnGroupSourceAttributeCount(array $group): int
    {
        $source = $group['source'] ?? null;
        if (!is_array($source)) {
            return 0;
        }

        return self::sourceAttributeSummaryCount($source['colgroupAttributes'] ?? null)
            + self::sourceAttributeSummaryCount($source['colAttributes'] ?? null);
    }

    private static function sourceAttributeSummaryCount(mixed $summary): int
    {
        if (!is_array($summary)) {
            return 0;
        }

        $count = 0;
        $id = trim((string) ($summary['id'] ?? ''));
        if ($id !== '') {
            $count++;
        }

        $classes = $summary['classes'] ?? [];
        if (is_array($classes)) {
            foreach ($classes as $class) {
                if (is_scalar($class) && trim((string) $class) !== '') {
                    $count++;
                }
            }
        }

        foreach (['attributes', 'htmlAttributes'] as $attributeKey) {
            $attributes = $summary[$attributeKey] ?? [];
            if (!is_array($attributes)) {
                continue;
            }

            foreach ($attributes as $name => $value) {
                if (!is_scalar($name) || trim((string) $name) === '') {
                    continue;
                }

                if (is_scalar($value) && trim((string) $value) !== '') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function nativeAttributeLocations(AstNode $table, array $coverage): array
    {
        $locations = [];
        self::appendNativeAttributeLocation($locations, 'table', $table);
        foreach (self::sectionRowGroups($table, self::columnCount($table)) as $group) {
            $section = (string) ($group['section'] ?? '');
            $node = $group['node'] ?? null;
            if ($node instanceof AstNode) {
                self::appendNativeAttributeLocation($locations, 'section', $node, [
                    'section' => $section,
                ]);
            }

            foreach ($group['rowEntries'] as $rowIndex => $entry) {
                $row = $entry['row'] ?? null;
                if (!$row instanceof AstNode) {
                    continue;
                }

                self::appendNativeAttributeLocation($locations, 'row', $row, [
                    'section' => $section,
                    'row' => (int) $rowIndex,
                    'rowRole' => (string) ($entry['rowRole'] ?? ''),
                ]);
            }
        }

        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            self::appendNativeAttributeLocation($locations, 'cell', $node, [
                'section' => (string) ($record['section'] ?? ''),
                'row' => (int) ($record['row'] ?? 0),
                'column' => (int) ($record['column'] ?? 0),
                'sourceCell' => (int) ($record['sourceCell'] ?? 0),
                'sourceColumn' => (int) ($record['sourceColumn'] ?? 0),
                'columns' => self::intList($record['columns'] ?? []),
            ]);
        }

        return $locations;
    }

    /**
     * @param list<array<string, mixed>> $locations
     * @param array<string, mixed> $context
     */
    private static function appendNativeAttributeLocation(array &$locations, string $scope, AstNode $node, array $context = []): void
    {
        $attributes = self::nativeAttributeMap($node);
        if ($attributes === []) {
            return;
        }

        $locations[] = array_replace([
            'scope' => $scope,
            'attributeCount' => count($attributes),
            'attributes' => $attributes,
        ], $context);
    }

    /**
     * @return array<string, string>
     */
    private static function nativeAttributeMap(AstNode $node): array
    {
        $attributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($attributes === []) {
            return [];
        }

        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes === []) {
            return $attributes;
        }

        $nativeAttributes = [];
        foreach ($attributes as $name => $value) {
            if (!self::isHtmlBackedAttribute($name, $value, $htmlAttributes)) {
                $nativeAttributes[$name] = $value;
            }
        }

        return $nativeAttributes === [] ? [] : $attributes;
    }

    /**
     * @param array<string, string> $htmlAttributes
     */
    private static function isHtmlBackedAttribute(string $name, string $value, array $htmlAttributes): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return false;
        }

        $candidates = [$name];
        if (!str_starts_with($name, 'data-')) {
            $candidates[] = 'data-' . $name;
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (array_key_exists($candidate, $htmlAttributes) && $htmlAttributes[$candidate] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function markdownColumnWidthDiagnostics(AstNode $table, string $writer): array
    {
        if ($writer !== 'markdown') {
            return [];
        }

        $summary = self::columnWidthSummary($table);
        if (($summary['hasExplicitWidths'] ?? false) !== true || (int) ($summary['validWidthCount'] ?? 0) <= 0) {
            return [];
        }

        $percentWidths = $summary['percentWidths'] ?? [];
        $pipeWidths = [];
        foreach (is_array($percentWidths) ? $percentWidths : [] as $percentWidth) {
            $pipeWidths[] = $percentWidth === null ? null : max(1, (int) ceil(((float) $percentWidth / 100.0) * 40.0));
        }

        return [[
            'code' => 'markdown-column-widths-approximated',
            'writer' => $writer,
            'reason' => 'column-widths',
            'requiredFeature' => 'pipe-table-character-padding',
            'source' => 'table-widths',
            'columnCount' => (int) $summary['columnCount'],
            'explicitWidthCount' => (int) $summary['explicitWidthCount'],
            'validWidthCount' => (int) $summary['validWidthCount'],
            'missingWidthCount' => (int) $summary['missingWidthCount'],
            'validWidthColumns' => self::intList($summary['validWidthColumns'] ?? []),
            'missingColumns' => self::intList($summary['missingColumns'] ?? []),
            'widthTotal' => (float) $summary['widthTotal'],
            'normalizedWidths' => $summary['normalizedWidths'],
            'percentWidths' => $summary['percentWidths'],
            'pipeCharacterWidths' => $pipeWidths,
            'hasCompleteWidths' => (bool) ($summary['hasCompleteWidths'] ?? false),
            'hasPartialWidths' => (bool) ($summary['hasPartialWidths'] ?? false),
            'overfull' => (bool) ($summary['overfull'] ?? false),
            'underfull' => (bool) ($summary['underfull'] ?? false),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function captionWriterDiagnostics(AstNode $table, string $writer): array
    {
        $captions = self::captionMetadata($table);
        $long = $captions['long'] ?? [];
        $short = $captions['short'] ?? [];
        $diagnostics = [];

        $shortCaption = trim((string) ($short['text'] ?? ''));
        if ($shortCaption !== '') {
            $shortCaptionRequirements = [
                'markdown' => ['markdown-short-caption-prefix-required', 'pandoc-short-caption-prefix'],
                'asciidoc' => ['asciidoc-short-caption-review-required', 'table-short-title-review'],
            ];
            if (isset($shortCaptionRequirements[$writer])) {
                [$code, $requiredFeature] = $shortCaptionRequirements[$writer];
                $diagnostics[] = [
                    'code' => $code,
                    'writer' => $writer,
                    'reason' => 'short-caption',
                    'requiredFeature' => $requiredFeature,
                    'caption' => (string) ($long['text'] ?? ''),
                    'captionSource' => (string) ($long['source'] ?? 'none'),
                    'hasCaption' => trim((string) ($long['text'] ?? '')) !== '',
                    'shortCaption' => $shortCaption,
                    'shortCaptionSource' => (string) ($short['source'] ?? 'none'),
                    'shortCaptionInlineTypes' => self::stringList($short['inlineTypes'] ?? []),
                    'shortCaptionBlockTypes' => self::stringList($short['blockTypes'] ?? []),
                    'hasShortCaptionFormatting' => (bool) ($short['hasInlineFormatting'] ?? false)
                        || (int) ($short['blockCount'] ?? 0) > 0,
                ];
            }
        }

        $blockCount = (int) ($long['blockCount'] ?? 0);
        if ($blockCount > 0) {
            $blockCaptionRequirements = [
                'markdown' => ['markdown-caption-blocks-flattened', 'inline-caption-markdown'],
                'asciidoc' => ['asciidoc-caption-blocks-flattened', 'plain-caption-text'],
                'latex' => ['latex-caption-blocks-flattened', 'caption-text'],
            ];
            if (isset($blockCaptionRequirements[$writer])) {
                [$code, $requiredFeature] = $blockCaptionRequirements[$writer];
                $diagnostic = [
                    'code' => $code,
                    'writer' => $writer,
                    'reason' => 'caption-blocks',
                    'requiredFeature' => $requiredFeature,
                    'captionText' => (string) ($long['text'] ?? ''),
                    'captionSource' => (string) ($long['source'] ?? 'none'),
                    'hasCaption' => trim((string) ($long['text'] ?? '')) !== '',
                    'blockCount' => $blockCount,
                    'blockTypes' => self::stringList($long['blockTypes'] ?? []),
                    'shortCaption' => $shortCaption,
                    'hasShortCaption' => $shortCaption !== '',
                ];
                if (array_key_exists('rawText', $long)) {
                    $diagnostic['rawCaption'] = (string) $long['rawText'];
                }

                $diagnostics[] = $diagnostic;
            }
        }

        $captionPlacement = (string) ($long['captionPlacement'] ?? '');
        $captionSideReviewRequired = (bool) ($long['captionSideReviewRequired'] ?? false);
        if ($captionSideReviewRequired) {
            $captionSideRequirements = [
                'markdown' => ['markdown-caption-side-review-required', 'raw-html-caption-side'],
                'asciidoc' => ['asciidoc-caption-side-review-required', 'table-caption-side-review'],
                'latex' => ['latex-caption-side-review-required', 'caption-position-review'],
            ];
            if (isset($captionSideRequirements[$writer])) {
                [$code, $requiredFeature] = $captionSideRequirements[$writer];
                $diagnostics[] = [
                    'code' => $code,
                    'writer' => $writer,
                    'reason' => 'caption-side',
                    'requiredFeature' => $requiredFeature,
                    'caption' => (string) ($long['text'] ?? ''),
                    'captionSource' => (string) ($long['source'] ?? 'none'),
                    'sourceElement' => (string) ($long['sourceElement'] ?? ''),
                    'sourcePosition' => (string) ($long['sourcePosition'] ?? ''),
                    'sourceChildIndex' => is_numeric($long['sourceChildIndex'] ?? null) ? (int) $long['sourceChildIndex'] : null,
                    'captionSide' => (string) ($long['captionSide'] ?? ''),
                    'captionSideSource' => (string) ($long['captionSideSource'] ?? ''),
                    'captionSideSupported' => (bool) ($long['captionSideSupported'] ?? false),
                    'captionSideReviewRequired' => true,
                    'captionPlacement' => $captionPlacement,
                    'captionPlacementFallback' => (string) ($long['captionPlacementFallback'] ?? ''),
                ];
            }
        } elseif ($captionPlacement === 'before-table') {
            $captionSideRequirements = [
                'asciidoc' => ['asciidoc-caption-side-review-required', 'table-caption-top-placement'],
                'latex' => ['latex-caption-side-review-required', 'caption-position-review'],
            ];
            if (isset($captionSideRequirements[$writer])) {
                [$code, $requiredFeature] = $captionSideRequirements[$writer];
                $diagnostics[] = [
                    'code' => $code,
                    'writer' => $writer,
                    'reason' => 'caption-side',
                    'requiredFeature' => $requiredFeature,
                    'caption' => (string) ($long['text'] ?? ''),
                    'captionSource' => (string) ($long['source'] ?? 'none'),
                    'sourceElement' => (string) ($long['sourceElement'] ?? ''),
                    'sourcePosition' => (string) ($long['sourcePosition'] ?? ''),
                    'sourceChildIndex' => is_numeric($long['sourceChildIndex'] ?? null) ? (int) $long['sourceChildIndex'] : null,
                    'captionSide' => (string) ($long['captionSide'] ?? ''),
                    'captionSideSource' => (string) ($long['captionSideSource'] ?? ''),
                    'captionPlacement' => $captionPlacement,
                ];
            }
        }

        $sourceAttributes = is_array($long['sourceAttributes'] ?? null) ? $long['sourceAttributes'] : [];
        if ($sourceAttributes !== []) {
            $captionSourceRequirements = [
                'markdown' => ['markdown-caption-source-attributes-require-raw-html', 'raw-html-caption-attributes'],
                'asciidoc' => ['asciidoc-caption-source-attributes-require-raw-html', 'raw-html-caption-attributes'],
                'latex' => ['latex-caption-source-attributes-review-required', 'caption-attribute-review-comments'],
            ];
            if (isset($captionSourceRequirements[$writer])) {
                [$code, $requiredFeature] = $captionSourceRequirements[$writer];
                $attributes = self::captionSourceNativeAttributeMap($sourceAttributes);
                $diagnostics[] = [
                    'code' => $code,
                    'writer' => $writer,
                    'reason' => 'caption-source-attributes',
                    'requiredFeature' => $requiredFeature,
                    'source' => 'html-caption',
                    'caption' => (string) ($long['text'] ?? ''),
                    'captionSource' => (string) ($long['source'] ?? 'none'),
                    'sourceElement' => (string) ($long['sourceElement'] ?? ''),
                    'sourcePosition' => (string) ($long['sourcePosition'] ?? ''),
                    'sourceChildIndex' => is_numeric($long['sourceChildIndex'] ?? null) ? (int) $long['sourceChildIndex'] : null,
                    'captionSide' => (string) ($long['captionSide'] ?? ''),
                    'captionSideSource' => (string) ($long['captionSideSource'] ?? ''),
                    'captionPlacement' => $captionPlacement,
                    'attributeCount' => count($attributes),
                    'attributes' => $attributes,
                    'sourceAttributes' => $sourceAttributes,
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @return array<string, string>
     */
    private static function captionSourceNativeAttributeMap(array $sourceAttributes): array
    {
        $attributes = self::stringAttributeMap($sourceAttributes['attributes'] ?? [], false);
        $htmlAttributes = self::stringAttributeMap($sourceAttributes['htmlAttributes'] ?? [], true);

        foreach ($htmlAttributes as $name => $value) {
            if ($name === 'id' || $name === 'class' || array_key_exists($name, $attributes)) {
                continue;
            }

            $attributes[$name] = $value;
        }

        if (isset($sourceAttributes['id']) && is_scalar($sourceAttributes['id'])) {
            $attributes['id'] = (string) $sourceAttributes['id'];
        }
        if (isset($sourceAttributes['classes']) && is_array($sourceAttributes['classes']) && $sourceAttributes['classes'] !== []) {
            $attributes['class'] = implode(' ', array_map(static fn (mixed $class): string => (string) $class, $sourceAttributes['classes']));
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableFootSectionWriterDiagnostics(AstNode $table, string $writer): array
    {
        $sectionSummary = self::tableSectionSummary($table);
        if (($sectionSummary['footRowCount'] ?? 0) <= 0) {
            return [];
        }

        if ($writer === 'markdown') {
            $code = 'markdown-table-foot-flattened';
            $requiredFeature = 'body-row-flattening';
        } elseif ($writer === 'asciidoc') {
            $code = 'asciidoc-table-foot-required';
            $requiredFeature = 'table-footer';
        } else {
            return [];
        }

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'table-foot',
            'requiredFeature' => $requiredFeature,
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => self::columnCount($table),
            'sectionCount' => $sectionSummary['sectionCount'],
            'rowCount' => $sectionSummary['rowCount'],
            'bodyCount' => $sectionSummary['bodyCount'],
            'headRowCount' => $sectionSummary['headRowCount'],
            'bodyRowCount' => $sectionSummary['bodyRowCount'],
            'footRowCount' => $sectionSummary['footRowCount'],
            'sectionRanges' => $sectionSummary['sectionRanges'],
            'footSectionRanges' => self::sectionRangeRecordsByRole($sectionSummary, 'foot'),
            'sections' => $sectionSummary['sections'],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableBodyGroupWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-table-bodies-flattened', 'body-row-group-boundaries'],
            'asciidoc' => ['asciidoc-table-bodies-review-required', 'table-body-groups'],
            'latex' => ['latex-table-bodies-review-required', 'longtable-body-group-review'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $columnCount = self::columnCount($table);
        $rowGroups = self::rowGroups($table, $columnCount);
        $rowGroupSummary = self::rowGroupSummary($rowGroups);
        $sectionSummary = self::tableSectionSummary($table);
        if (($sectionSummary['bodyCount'] ?? 0) <= 1) {
            return [];
        }

        $bodySections = [];
        $bodySectionRowCounts = [];
        foreach ($sectionSummary['sections'] as $section) {
            if (($section['rowRole'] ?? '') !== 'body') {
                continue;
            }

            $bodySections[] = (string) ($section['section'] ?? '');
            $bodySectionRowCounts[] = max(0, (int) ($section['rowCount'] ?? 0));
        }

        $bodySectionRowHeadColumns = [];
        $rowHeadBodySections = [];
        $rowHeadColumnCounts = [];
        $rowHeadSectionRanges = [];
        foreach ($rowGroups as $rowGroup) {
            if (($rowGroup['kind'] ?? '') !== 'table-body') {
                continue;
            }

            $rowHeadColumns = max(0, (int) ($rowGroup['rowHeadColumns'] ?? 0));
            $bodySectionRowHeadColumns[] = $rowHeadColumns;
            if ($rowHeadColumns <= 0) {
                continue;
            }

            $rowHeadBodySections[] = (string) ($rowGroup['section'] ?? '');
            $rowHeadColumnCounts[] = $rowHeadColumns;
            $rowHeadSectionRanges[] = self::rowHeadGroupRangeRecord($rowGroup);
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'multiple-table-bodies',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-table-bodies',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $columnCount,
            'sectionCount' => $sectionSummary['sectionCount'],
            'rowCount' => $sectionSummary['rowCount'],
            'bodyCount' => $sectionSummary['bodyCount'],
            'headRowCount' => $sectionSummary['headRowCount'],
            'bodyRowCount' => $sectionSummary['bodyRowCount'],
            'footRowCount' => $sectionSummary['footRowCount'],
            'bodySections' => $bodySections,
            'bodySectionRowCounts' => $bodySectionRowCounts,
            'bodySectionRowHeadColumns' => $bodySectionRowHeadColumns,
            'rowHeadBodySections' => $rowHeadBodySections,
            'rowHeadColumnCounts' => $rowHeadColumnCounts,
            'rowHeadGroupCount' => (int) $rowGroupSummary['rowHeadGroupCount'],
            'maxRowHeadColumns' => (int) $rowGroupSummary['maxRowHeadColumns'],
            'hasDifferingRowHeadColumns' => (bool) $rowGroupSummary['hasDifferingRowHeadColumns'],
            'rowHeadSectionRanges' => $rowHeadSectionRanges,
            'sectionRanges' => $sectionSummary['sectionRanges'],
            'bodySectionRanges' => self::sectionRangeRecordsByRole($sectionSummary, 'body'),
            'sections' => $sectionSummary['sections'],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tableBodyHeadRowWriterDiagnostics(AstNode $table, string $writer): array
    {
        $requirements = [
            'markdown' => ['markdown-body-head-rows-flattened', 'body-local-header-row-boundaries'],
            'asciidoc' => ['asciidoc-body-head-rows-review-required', 'body-local-header-rows'],
        ];
        if (!isset($requirements[$writer])) {
            return [];
        }

        $columnCount = self::columnCount($table);
        $rowGroups = self::rowGroups($table, $columnCount);
        $summary = self::rowGroupSummary($rowGroups);
        if (($summary['hasBodyHeadRows'] ?? false) !== true) {
            return [];
        }

        $bodySections = [];
        $bodyHeadRowCounts = [];
        $bodySectionRowCounts = [];
        $sectionRanges = [];
        $bodySectionRanges = [];
        $bodyHeadRowRanges = [];
        $sections = [];
        foreach ($rowGroups as $rowGroup) {
            $kind = (string) ($rowGroup['kind'] ?? '');
            $rowCount = max(0, (int) ($rowGroup['rowCount'] ?? 0));
            if ($rowCount <= 0) {
                continue;
            }

            $rowRole = match ($kind) {
                'table-head' => 'head',
                'table-body' => 'body',
                'table-foot' => 'foot',
                default => '',
            };
            if ($rowRole === '') {
                continue;
            }

            $section = (string) ($rowGroup['section'] ?? '');
            $sectionRange = self::rowGroupWriterRangeRecord($rowGroup, $rowRole);
            $sectionRanges[] = $sectionRange;
            $bodyHeadRowCount = max(0, (int) ($rowGroup['bodyHeadRowCount'] ?? 0));
            $bodyRowCount = max(0, (int) ($rowGroup['bodyRowCount'] ?? 0));
            $sectionRecord = [
                'section' => $section,
                'rowCount' => $rowCount,
                'rowRole' => $rowRole,
            ];
            if ($kind === 'table-body') {
                $sectionRecord['bodyHeadRowCount'] = $bodyHeadRowCount;
                $sectionRecord['bodyRowCount'] = $bodyRowCount;
                $sectionRecord['rowRoles'] = self::stringList($rowGroup['rowRoles'] ?? []);
                $bodySectionRanges[] = $sectionRange;
                if ($bodyHeadRowCount > 0) {
                    $bodySections[] = $section;
                    $bodyHeadRowCounts[] = $bodyHeadRowCount;
                    $bodySectionRowCounts[] = $bodyRowCount;
                    $bodyHeadRowRanges[] = array_replace($sectionRange, [
                        'bodyHeadRowCount' => $bodyHeadRowCount,
                        'bodyHeadRowRange' => [
                            max(0, (int) ($rowGroup['globalRowStart'] ?? 0)),
                            max(0, (int) ($rowGroup['globalRowStart'] ?? 0)) + $bodyHeadRowCount,
                        ],
                        'bodyRowCount' => $bodyRowCount,
                    ]);
                }
            }
            $sections[] = $sectionRecord;
        }

        [$code, $requiredFeature] = $requirements[$writer];

        return [[
            'code' => $code,
            'writer' => $writer,
            'reason' => 'body-head-rows',
            'requiredFeature' => $requiredFeature,
            'source' => 'pandoc-table-body-head-rows',
            'caption' => (string) $table->attr('caption', ''),
            'hasCaption' => trim((string) $table->attr('caption', '')) !== '',
            'columnCount' => $columnCount,
            'sectionCount' => count($sections),
            'rowCount' => $summary['tableHeadRowCount'] + $summary['bodyHeadRowCount'] + $summary['bodyRowCount'] + $summary['tableFootRowCount'],
            'bodyCount' => $summary['bodyGroupCount'],
            'tableHeadRowCount' => $summary['tableHeadRowCount'],
            'bodyHeadRowCount' => $summary['bodyHeadRowCount'],
            'bodyHeadRowGroupCount' => $summary['bodyHeadRowGroupCount'],
            'bodyRowCount' => $summary['bodyRowCount'],
            'footRowCount' => $summary['tableFootRowCount'],
            'bodySections' => $bodySections,
            'bodyHeadRowCounts' => $bodyHeadRowCounts,
            'bodySectionRowCounts' => $bodySectionRowCounts,
            'sectionRanges' => $sectionRanges,
            'bodySectionRanges' => $bodySectionRanges,
            'bodyHeadRowRanges' => $bodyHeadRowRanges,
            'sections' => $sections,
        ]];
    }

    /**
     * @return array{
     *     sectionCount:int,
     *     rowCount:int,
     *     bodyCount:int,
     *     headRowCount:int,
     *     bodyRowCount:int,
     *     footRowCount:int,
     *     sectionRanges:list<array{section:string,rowRange:array{0:int,1:int},rowCount:int,rowRole:string}>,
     *     sections:list<array{section:string,rowCount:int,rowRole:string}>
     * }
     */
    private static function tableSectionSummary(AstNode $table): array
    {
        $sections = [];
        $sectionRanges = [];
        $rowCount = 0;
        $bodyCount = 0;
        $headRowCount = 0;
        $bodyRowCount = 0;
        $footRowCount = 0;
        $globalRowStart = 0;

        foreach (self::sectionRowGroups($table, self::columnCount($table)) as $group) {
            $section = (string) $group['section'];
            $rowsInSection = count($group['rowEntries']);
            if ($rowsInSection === 0) {
                continue;
            }
            $globalRowEnd = $globalRowStart + $rowsInSection;

            $rowRole = str_starts_with($section, 'body') ? 'body' : $section;
            if ($rowRole === 'head') {
                $headRowCount += $rowsInSection;
            } elseif ($rowRole === 'body') {
                $bodyCount++;
                $bodyRowCount += $rowsInSection;
            } elseif ($rowRole === 'foot') {
                $footRowCount += $rowsInSection;
            }

            $rowCount += $rowsInSection;
            $sections[] = [
                'section' => $section,
                'rowCount' => $rowsInSection,
                'rowRole' => $rowRole,
            ];
            $sectionRanges[] = [
                'section' => $section,
                'rowRange' => [$globalRowStart, $globalRowEnd],
                'rowCount' => $rowsInSection,
                'rowRole' => $rowRole,
            ];
            $globalRowStart = $globalRowEnd;
        }

        return [
            'sectionCount' => count($sections),
            'rowCount' => $rowCount,
            'bodyCount' => $bodyCount,
            'headRowCount' => $headRowCount,
            'bodyRowCount' => $bodyRowCount,
            'footRowCount' => $footRowCount,
            'sectionRanges' => $sectionRanges,
            'sections' => $sections,
        ];
    }

    /**
     * @param array<string, mixed> $sectionSummary
     * @return list<array<string, mixed>>
     */
    private static function sectionRangeRecordsByRole(array $sectionSummary, string $rowRole): array
    {
        $ranges = is_array($sectionSummary['sectionRanges'] ?? null) ? $sectionSummary['sectionRanges'] : [];
        $filtered = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || (string) ($range['rowRole'] ?? '') !== $rowRole) {
                continue;
            }

            $filtered[] = $range;
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $rowGroup
     * @return array<string, mixed>
     */
    private static function rowGroupWriterRangeRecord(array $rowGroup, string $rowRole): array
    {
        $globalRowStart = max(0, (int) ($rowGroup['globalRowStart'] ?? 0));
        $globalRowEnd = max($globalRowStart, (int) ($rowGroup['globalRowEnd'] ?? $globalRowStart));
        $rowRange = self::intList($rowGroup['rowRange'] ?? []);
        if (count($rowRange) !== 2) {
            $rowRange = [$globalRowStart, $globalRowEnd];
        }

        return [
            'section' => (string) ($rowGroup['section'] ?? ''),
            'rowRange' => $rowRange,
            'rowCount' => max(0, (int) ($rowGroup['rowCount'] ?? 0)),
            'rowRole' => $rowRole,
        ];
    }

    /**
     * @param array<string, mixed> $rowGroup
     * @return array<string, mixed>
     */
    private static function rowHeadGroupRangeRecord(array $rowGroup): array
    {
        return array_replace(self::rowGroupWriterRangeRecord($rowGroup, 'body'), [
            'rowHeadColumns' => max(0, (int) ($rowGroup['rowHeadColumns'] ?? 0)),
            'bodyIndex' => max(0, (int) ($rowGroup['bodyIndex'] ?? $rowGroup['bodyOrdinal'] ?? 0)),
            'bodyOrdinal' => max(0, (int) ($rowGroup['bodyOrdinal'] ?? $rowGroup['bodyIndex'] ?? 0)),
        ]);
    }

    private static function normalizeWriterName(string $writer): string
    {
        $writer = strtolower(trim(str_replace('_', '-', $writer)));
        if (in_array($writer, [
            'markdown-grid-table',
            'markdown-grid-tables',
            'markdown+grid-tables',
            'pandoc-markdown-grid-table',
            'pandoc-markdown-grid-tables',
            'pandoc-markdown+grid-tables',
        ], true)) {
            return 'markdown-grid-table';
        }

        if (in_array($writer, ['rst', 'rst-grid-table', 'restructuredtext', 'restructured-text', 'restructured-text-grid-table'], true)) {
            return 'rst';
        }

        if (in_array($writer, ['adoc', 'asciidoc', 'asciidoc-legacy', 'asciidoctor'], true)) {
            return 'asciidoc';
        }

        if (in_array($writer, ['latex', 'tex', 'pdflatex', 'xelatex', 'lualatex', 'latexmk'], true)) {
            return 'latex';
        }

        return in_array($writer, ['markdown', 'markdown-pipe-table', 'pipe-table'], true) ? 'markdown' : $writer;
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array{row:int,column:int,covering:string}> $flattenedSlots
     * @return array<string, mixed>
     */
    private static function writerDowngradeRecord(string $code, string $writer, array $record, array $flattenedSlots): array
    {
        $writerRecord = [
            'code' => $code,
            'writer' => $writer,
            'section' => (string) ($record['section'] ?? ''),
            'row' => (int) ($record['row'] ?? 0),
            'column' => (int) ($record['column'] ?? 0),
            'endColumn' => (int) ($record['endColumn'] ?? 0),
            'sourceCell' => (int) ($record['sourceCell'] ?? 0),
            'sourceColumn' => (int) ($record['sourceColumn'] ?? 0),
            ...self::sourceRowCoordinateFields(
                (int) ($record['sourceRow'] ?? $record['row'] ?? 0),
                max(1, (int) ($record['sourceRowspan'] ?? $record['rawRowspan'] ?? $record['rowspan'] ?? 1))
            ),
            'columns' => self::intList($record['columns'] ?? []),
            'rawColspan' => max(1, (int) ($record['rawColspan'] ?? 1)),
            'colspan' => max(1, (int) ($record['colspan'] ?? 1)),
            'rawRowspan' => max(1, (int) ($record['rawRowspan'] ?? 1)),
            'rowspan' => max(1, (int) ($record['rowspan'] ?? 1)),
            'flattenedSlots' => $flattenedSlots,
        ];

        if (($record['rowspanToEnd'] ?? false) === true) {
            $writerRecord['rowspanToEnd'] = true;
            $writerRecord['sourceRowspanAttribute'] = 0;
            $writerRecord['sourceRowspanMode'] = 'to-section-end';
        }

        return $writerRecord;
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array{row:int,column:int,covering:string}> $requiredSlots
     * @return array<string, mixed>
     */
    private static function writerRequirementRecord(
        string $code,
        string $writer,
        array $record,
        string $reason,
        string $requiredFeature,
        array $requiredSlots
    ): array {
        $writerRecord = self::writerDowngradeRecord($code, $writer, $record, $requiredSlots);
        unset($writerRecord['flattenedSlots']);
        $writerRecord['reason'] = $reason;
        $writerRecord['requiredFeature'] = $requiredFeature;
        $writerRecord['requiredSlots'] = $requiredSlots;

        return $writerRecord;
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array<string, mixed>> $nestedTables
     * @return array<string, mixed>
     */
    private static function writerNestedTableRequirementRecord(
        string $code,
        string $writer,
        array $record,
        array $nestedTables,
        string $requiredFeature = 'raw-html-table-passthrough'
    ): array {
        $writerRecord = self::writerDowngradeRecord($code, $writer, $record, []);
        unset($writerRecord['flattenedSlots']);
        $writerRecord['reason'] = 'nested-table';
        $writerRecord['requiredFeature'] = $requiredFeature;
        $writerRecord['nestedTableCount'] = count($nestedTables);
        $writerRecord['nestedTables'] = $nestedTables;

        $captions = [];
        $diagnosticCodes = [];
        foreach ($nestedTables as $nestedTable) {
            $caption = trim((string) ($nestedTable['caption'] ?? ''));
            if ($caption !== '') {
                $captions[] = $caption;
            }

            $nestedCodes = $nestedTable['diagnosticCodes'] ?? [];
            if (!is_array($nestedCodes)) {
                continue;
            }

            foreach ($nestedCodes as $diagnosticCode) {
                $diagnosticCode = trim((string) $diagnosticCode);
                if ($diagnosticCode !== '') {
                    $diagnosticCodes[] = $diagnosticCode;
                }
            }
        }

        $writerRecord['nestedTableCaptions'] = array_values(array_unique($captions));
        $writerRecord['nestedTableDiagnosticCodes'] = array_values(array_unique($diagnosticCodes));

        return $writerRecord;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private static function writerCellBlockRequirementRecord(
        string $code,
        string $writer,
        array $record,
        array $content,
        string $requiredFeature
    ): array {
        $writerRecord = self::writerDowngradeRecord($code, $writer, $record, []);
        unset($writerRecord['flattenedSlots']);
        $writerRecord['reason'] = 'block-content';
        $writerRecord['requiredFeature'] = $requiredFeature;
        $writerRecord['blockCount'] = (int) ($content['blockCount'] ?? 0);

        $blockTypes = $content['blockTypes'] ?? [];
        $writerRecord['blockTypes'] = is_array($blockTypes)
            ? array_values(array_map(static fn (mixed $type): string => (string) $type, $blockTypes))
            : [];
        $writerRecord['hasMixedInlineAndBlockContent'] = (bool) ($content['hasMixedInlineAndBlockContent'] ?? false);

        return $writerRecord;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array{row:int,column:int,covering:string}>
     */
    private static function flattenedSlotRecords(array $record, string $spanAxis): array
    {
        $anchorRow = (int) ($record['row'] ?? 0);
        $slots = $record['occupiedSlots'] ?? [];
        if (!is_array($slots)) {
            return [];
        }

        $flattened = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $covering = (string) ($slot['covering'] ?? '');
            $row = (int) ($slot['row'] ?? 0);
            if ($spanAxis === 'colspan') {
                $include = $row === $anchorRow && in_array($covering, ['colspan', 'rowspan-colspan'], true);
            } else {
                $include = $row !== $anchorRow && in_array($covering, ['rowspan', 'rowspan-colspan'], true);
            }

            if (!$include) {
                continue;
            }

            $flattened[] = [
                'row' => $row,
                'column' => (int) ($slot['column'] ?? 0),
                'covering' => $covering,
            ];
        }

        return $flattened;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array{section:string,row:int,column:int,covering:string}>
     */
    private static function sectionedFlattenedSlotRecords(array $record, string $spanAxis): array
    {
        $section = (string) ($record['section'] ?? '');
        $records = [];
        foreach (self::flattenedSlotRecords($record, $spanAxis) as $slot) {
            $records[] = [
                'section' => $section,
                'row' => (int) $slot['row'],
                'column' => (int) $slot['column'],
                'covering' => (string) $slot['covering'],
            ];
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private static function reviewPacketWriters(mixed $writers): array
    {
        if (!is_array($writers)) {
            return ['markdown'];
        }

        $normalized = [];
        foreach ($writers as $writer) {
            if (!is_scalar($writer)) {
                continue;
            }

            $writer = self::normalizeWriterName((string) $writer);
            if ($writer !== '') {
                $normalized[] = $writer;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $values));
    }

    /**
     * @return list<int>
     */
    private static function uniqueIntList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $unique = [];
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $unique[(int) $value] = true;
        }

        $integers = array_keys($unique);
        sort($integers, SORT_NUMERIC);

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $integers));
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $strings = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $strings[] = $value;
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function sortedUniqueStrings(array $values): array
    {
        $strings = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $strings[$value] = true;
            }
        }

        $strings = array_keys($strings);
        sort($strings, SORT_STRING);

        return array_values($strings);
    }

    /**
     * @return list<int>
     */
    private static function sourceColumns(int $sourceColumn, int $colspan): array
    {
        $columns = [];
        for ($column = $sourceColumn; $column < $sourceColumn + max(1, $colspan); $column++) {
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @return list<int>
     */
    private static function integerRange(int $start, int $end): array
    {
        $start = max(0, $start);
        $end = max($start, $end);
        $values = [];
        for ($value = $start; $value < $end; $value++) {
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @return array{sourceRow:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>,sourceRowspan:int}
     */
    private static function sourceRowCoordinateFields(int $sourceRow, int $sourceRowspan): array
    {
        $sourceRow = max(0, $sourceRow);
        $sourceRowspan = max(1, $sourceRowspan);
        $sourceRowEnd = $sourceRow + $sourceRowspan;

        return [
            'sourceRow' => $sourceRow,
            'sourceRowEnd' => $sourceRowEnd,
            'sourceRowRange' => [$sourceRow, $sourceRowEnd],
            'sourceRows' => self::integerRange($sourceRow, $sourceRowEnd),
            'sourceRowspan' => $sourceRowspan,
        ];
    }

    /**
     * @return array{anchorSourceRow:int,anchorSourceRowEnd:int,anchorSourceRowRange:array{0:int,1:int},anchorSourceRows:list<int>,anchorSourceRowspan:int}
     */
    private static function anchorSourceRowCoordinateFields(int $sourceRow, int $sourceRowspan): array
    {
        $sourceRow = max(0, $sourceRow);
        $sourceRowspan = max(1, $sourceRowspan);
        $sourceRowEnd = $sourceRow + $sourceRowspan;

        return [
            'anchorSourceRow' => $sourceRow,
            'anchorSourceRowEnd' => $sourceRowEnd,
            'anchorSourceRowRange' => [$sourceRow, $sourceRowEnd],
            'anchorSourceRows' => self::integerRange($sourceRow, $sourceRowEnd),
            'anchorSourceRowspan' => $sourceRowspan,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function nestedTableSummaries(AstNode $node): array
    {
        $summaries = [];
        foreach ($node->children as $index => $child) {
            self::collectNestedTableSummaries($child, [(int) $index], $summaries);
        }

        return $summaries;
    }

    /**
     * @param list<int> $path
     * @param list<array<string, mixed>> $summaries
     */
    private static function collectNestedTableSummaries(AstNode $node, array $path, array &$summaries): void
    {
        if ($node->type === 'table') {
            $packet = $node->attr('tableGeometry', null);
            if (!is_array($packet)) {
                $packet = self::reviewPacket($node, ['accessibility' => false]);
            }

            $summary = $packet['summary'] ?? [];
            if (!is_array($summary)) {
                $summary = [];
            }
            $diagnosticCodes = $summary['diagnosticCodes'] ?? [];
            if (!is_array($diagnosticCodes)) {
                $diagnosticCodes = [];
            }
            $nestedTableCount = (int) ($summary['nestedTableCount'] ?? 0);

            $summaries[] = [
                'path' => array_values($path),
                'caption' => (string) ($packet['caption'] ?? ''),
                'columnCount' => (int) ($packet['columnCount'] ?? self::columnCount($node)),
                'declaredColumnCount' => (int) ($packet['declaredColumnCount'] ?? self::declaredColumnCount($node)),
                'sectionCount' => (int) ($summary['sectionCount'] ?? 0),
                'rowCount' => (int) ($summary['rowCount'] ?? 0),
                'cellCount' => (int) ($summary['cellCount'] ?? 0),
                'headerCellCount' => (int) ($summary['headerCellCount'] ?? 0),
                'coveredSlotCount' => (int) ($summary['coveredSlotCount'] ?? 0),
                'missingSlotCount' => (int) ($summary['missingSlotCount'] ?? 0),
                'diagnosticCount' => (int) ($summary['diagnosticCount'] ?? 0),
                'diagnosticCodes' => array_values(array_map(
                    static fn (mixed $code): string => (string) $code,
                    $diagnosticCodes
                )),
                'hasSpans' => (bool) ($summary['hasSpans'] ?? false),
                'nestedTableCount' => $nestedTableCount,
                'hasNestedTables' => $nestedTableCount > 0,
            ];
        }

        foreach ($node->children as $index => $child) {
            $childPath = $path;
            $childPath[] = (int) $index;
            self::collectNestedTableSummaries($child, $childPath, $summaries);
        }
    }

    private static function plainText(AstNode $node): string
    {
        if ($node->type === 'text') {
            return (string) $node->attr('text', '');
        }

        if ($node->type === 'space' || $node->type === 'softbreak') {
            return ' ';
        }

        if ($node->type === 'linebreak') {
            return "\n";
        }

        if ($node->children !== []) {
            $text = '';
            foreach ($node->children as $child) {
                $text .= self::plainText($child);
            }

            return $text;
        }

        foreach (['text', 'alt', 'caption', 'tex'] as $attr) {
            $value = $node->attr($attr, null);
            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function plainTextFromNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= self::plainText($node);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function plainTextFromBlockNodes(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $parts[] = self::plainText($node);
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{id?:string,classes?:list<string>,attributes?:array<string, string>,htmlAttributes?:array<string, string>}
     */
    private static function sourceAttributeSummary(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $summary = [];
        $id = trim((string) $node->attr('id', ''));
        if ($id === '') {
            $htmlAttributes = $node->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && isset($htmlAttributes['id']) && is_scalar($htmlAttributes['id'])) {
                $id = trim((string) $htmlAttributes['id']);
            }
        }
        if ($id !== '') {
            $summary['id'] = $id;
        }

        $classes = self::sourceClasses($node);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }

        $attributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($attributes !== []) {
            $summary['attributes'] = $attributes;
        }

        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes !== []) {
            $summary['htmlAttributes'] = $htmlAttributes;
        }

        return $summary;
    }

    /**
     * @return list<string>
     */
    private static function sourceClasses(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        if (is_array($classes)) {
            $normalized = [];
            foreach ($classes as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $normalized[] = $class;
                }
            }

            if ($normalized !== []) {
                return array_values(array_unique($normalized));
            }
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes) || !isset($htmlAttributes['class']) || !is_scalar($htmlAttributes['class'])) {
            return [];
        }

        $classes = preg_split('/\s+/', trim((string) $htmlAttributes['class']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($classes));
    }

    /**
     * @return array<string, string>
     */
    private static function stringAttributeMap(mixed $attributes, bool $lowercaseKeys): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || !is_scalar($value)) {
                continue;
            }

            if ($lowercaseKeys) {
                $name = strtolower($name);
            }
            $normalized[$name] = (string) $value;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,width?:string,widthType?:string,widthValue?:float,height?:string,heightType?:string,heightValue?:float,layoutMode?:string,layoutModeSource?:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableLayoutMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        $recordAttributes = [];
        $width = [];
        if (array_key_exists('width', $attributes)) {
            $width = self::normalizeTableWidthAttribute((string) $attributes['width']);
            if ($width !== []) {
                $recordAttributes['width'] = (string) $width['width'];
            }
        }

        $height = [];
        if (array_key_exists('height', $attributes)) {
            $height = self::normalizeTableHeightAttribute((string) $attributes['height']);
            if ($height !== []) {
                $recordAttributes['height'] = (string) $height['height'];
            }
        }

        $layoutMode = '';
        if (array_key_exists('style', $attributes)) {
            $layoutMode = self::normalizeTableLayoutStyleAttribute((string) $attributes['style']);
            if ($layoutMode !== '') {
                $recordAttributes['table-layout'] = $layoutMode;
            }
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $record = [
            'source' => 'html-table-layout',
            'attributes' => $recordAttributes,
        ];
        if ($width !== []) {
            $record['width'] = (string) $width['width'];
            $record['widthType'] = (string) $width['widthType'];
            $record['widthValue'] = (float) $width['widthValue'];
        }
        if ($height !== []) {
            $record['height'] = (string) $height['height'];
            $record['heightType'] = (string) $height['heightType'];
            $record['heightValue'] = (float) $height['heightValue'];
        }
        if ($layoutMode !== '') {
            $record['layoutMode'] = $layoutMode;
            $record['layoutModeSource'] = 'style';
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,alignment:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableAlignmentMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        if (!array_key_exists('align', $attributes)) {
            return [];
        }

        $alignment = self::normalizeTablePlacementAlignmentAttribute((string) $attributes['align']);
        if ($alignment === '') {
            return [];
        }

        $record = [
            'source' => 'html-table-alignment',
            'attributes' => [
                'align' => $alignment,
            ],
            'alignment' => $alignment,
        ];

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,frame?:string,rules?:string,border?:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableFrameMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        $recordAttributes = [];
        $frame = self::normalizeTableFrameAttribute((string) ($attributes['frame'] ?? ''));
        if ($frame !== '') {
            $recordAttributes['frame'] = $frame;
        }

        $rules = self::normalizeTableRulesAttribute((string) ($attributes['rules'] ?? ''));
        if ($rules !== '') {
            $recordAttributes['rules'] = $rules;
        }

        if (array_key_exists('border', $attributes)) {
            $border = self::normalizeTableBorderAttribute((string) $attributes['border']);
            if ($border !== '') {
                $recordAttributes['border'] = $border;
            }
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $record = [
            'source' => 'html-table-attributes',
            'attributes' => $recordAttributes,
        ];
        foreach (['frame', 'rules', 'border'] as $name) {
            if (isset($recordAttributes[$name])) {
                $record[$name] = $recordAttributes[$name];
            }
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,cellPadding?:string,cellSpacing?:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableSpacingMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        $recordAttributes = [];
        if (array_key_exists('cellpadding', $attributes)) {
            $cellPadding = self::normalizeTableSpacingAttribute((string) $attributes['cellpadding']);
            if ($cellPadding !== '') {
                $recordAttributes['cellpadding'] = $cellPadding;
            }
        }

        if (array_key_exists('cellspacing', $attributes)) {
            $cellSpacing = self::normalizeTableSpacingAttribute((string) $attributes['cellspacing']);
            if ($cellSpacing !== '') {
                $recordAttributes['cellspacing'] = $cellSpacing;
            }
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $record = [
            'source' => 'html-table-spacing',
            'attributes' => $recordAttributes,
        ];
        if (isset($recordAttributes['cellpadding'])) {
            $record['cellPadding'] = $recordAttributes['cellpadding'];
        }
        if (isset($recordAttributes['cellspacing'])) {
            $record['cellSpacing'] = $recordAttributes['cellspacing'];
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,backgroundColor:string,backgroundColorSource:string,legacyBackgroundColor?:string,cssBackgroundColor?:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableBackgroundMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        $recordAttributes = [];
        $legacyBackgroundColor = '';
        if (array_key_exists('bgcolor', $attributes)) {
            $legacyBackgroundColor = self::normalizeTableBackgroundColorAttribute((string) $attributes['bgcolor']);
            if ($legacyBackgroundColor !== '') {
                $recordAttributes['bgcolor'] = $legacyBackgroundColor;
            }
        }

        $cssBackgroundColor = '';
        if (array_key_exists('style', $attributes)) {
            $cssBackgroundColor = self::normalizeTableBackgroundStyleAttribute((string) $attributes['style']);
            if ($cssBackgroundColor !== '') {
                $recordAttributes['background-color'] = $cssBackgroundColor;
            }
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $backgroundColor = $cssBackgroundColor !== '' ? $cssBackgroundColor : $legacyBackgroundColor;
        $backgroundColorSource = $cssBackgroundColor !== '' ? 'style' : 'bgcolor';
        $record = [
            'source' => 'html-table-background',
            'attributes' => $recordAttributes,
            'backgroundColor' => $backgroundColor,
            'backgroundColorSource' => $backgroundColorSource,
        ];
        if ($legacyBackgroundColor !== '') {
            $record['legacyBackgroundColor'] = $legacyBackgroundColor;
        }
        if ($cssBackgroundColor !== '') {
            $record['cssBackgroundColor'] = $cssBackgroundColor;
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,borderCollapse:string,borderCollapseSource:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableBorderCollapseMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        if (!array_key_exists('style', $attributes)) {
            return [];
        }

        $borderCollapse = self::normalizeTableBorderCollapseStyleAttribute((string) $attributes['style']);
        if ($borderCollapse === '') {
            return [];
        }

        $record = [
            'source' => 'html-table-border-collapse',
            'attributes' => [
                'border-collapse' => $borderCollapse,
            ],
            'borderCollapse' => $borderCollapse,
            'borderCollapseSource' => 'style',
        ];

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    /**
     * @return array{source:string,attributes:array<string, string>,borderColor?:string,borderColorSource?:string,legacyBorderColor?:string,cssBorderColor?:string,borderStyle?:string,borderWidth?:string,sourceAttributes?:array<string, mixed>}
     */
    private static function tableBorderPresentationMetadata(AstNode $table): array
    {
        $attributes = self::stringAttributeMap($table->attr('htmlAttributes', []), true);
        foreach (self::stringAttributeMap($table->attr('attributes', []), false) as $name => $value) {
            $key = strtolower(trim($name));
            if ($key !== '' && !array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        $recordAttributes = [];
        $legacyBorderColor = '';
        if (array_key_exists('bordercolor', $attributes)) {
            $legacyBorderColor = self::normalizeTableBackgroundColorAttribute((string) $attributes['bordercolor']);
            if ($legacyBorderColor !== '') {
                $recordAttributes['bordercolor'] = $legacyBorderColor;
            }
        }

        $style = (string) ($attributes['style'] ?? '');
        $cssBorderColor = self::normalizeTableBorderColorStyleAttribute($style);
        if ($cssBorderColor !== '') {
            $recordAttributes['border-color'] = $cssBorderColor;
        }

        $borderStyle = self::normalizeTableBorderStyleStyleAttribute($style);
        if ($borderStyle !== '') {
            $recordAttributes['border-style'] = $borderStyle;
        }

        $borderWidth = self::normalizeTableBorderWidthStyleAttribute($style);
        if ($borderWidth !== '') {
            $recordAttributes['border-width'] = $borderWidth;
        }

        if ($recordAttributes === []) {
            return [];
        }

        ksort($recordAttributes);
        $borderColor = $cssBorderColor !== '' ? $cssBorderColor : $legacyBorderColor;
        $borderColorSource = $cssBorderColor !== '' ? 'style' : ($legacyBorderColor !== '' ? 'bordercolor' : '');
        $record = [
            'source' => 'html-table-border-presentation',
            'attributes' => $recordAttributes,
        ];
        if ($borderColor !== '') {
            $record['borderColor'] = $borderColor;
            $record['borderColorSource'] = $borderColorSource;
        }
        if ($legacyBorderColor !== '') {
            $record['legacyBorderColor'] = $legacyBorderColor;
        }
        if ($cssBorderColor !== '') {
            $record['cssBorderColor'] = $cssBorderColor;
        }
        if ($borderStyle !== '') {
            $record['borderStyle'] = $borderStyle;
        }
        if ($borderWidth !== '') {
            $record['borderWidth'] = $borderWidth;
        }

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    private static function normalizeTableFrameAttribute(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['void', 'above', 'below', 'hsides', 'lhs', 'rhs', 'vsides', 'box', 'border'], true)
            ? $value
            : '';
    }

    private static function normalizeTablePlacementAlignmentAttribute(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['left', 'right', 'center'], true) ? $value : '';
    }

    private static function normalizeTableRulesAttribute(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['none', 'groups', 'rows', 'cols', 'all'], true) ? $value : '';
    }

    private static function normalizeTableBorderAttribute(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'border') {
            return '1';
        }

        return preg_match('/^\d{1,3}$/', $value) === 1 ? $value : '';
    }

    private static function normalizeTableSpacingAttribute(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,3}$/', $value) === 1 ? $value : '';
    }

    private static function normalizeTableBackgroundStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'background-color') {
                continue;
            }

            $color = self::normalizeTableBackgroundColorAttribute($value);
            if ($color !== '') {
                return $color;
            }
        }

        return '';
    }

    private static function normalizeTableBorderCollapseStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'border-collapse') {
                continue;
            }

            $borderCollapse = strtolower(trim($value));
            if (in_array($borderCollapse, ['collapse', 'separate'], true)) {
                return $borderCollapse;
            }
        }

        return '';
    }

    private static function normalizeTableBorderColorStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'border-color') {
                continue;
            }

            $color = self::normalizeTableBackgroundColorAttribute($value);
            if ($color !== '') {
                return $color;
            }
        }

        return '';
    }

    private static function normalizeTableBorderStyleStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'border-style') {
                continue;
            }

            return self::normalizeTableBorderLineStyleAttribute($value);
        }

        return '';
    }

    private static function normalizeTableBorderLineStyleAttribute(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'], true)
            ? $value
            : '';
    }

    private static function normalizeTableBorderWidthStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'border-width') {
                continue;
            }

            return self::normalizeTableBorderWidthAttribute($value);
        }

        return '';
    }

    private static function normalizeTableBorderWidthAttribute(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === [] || count($tokens) > 4) {
            return '';
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $width = self::normalizeTableBorderWidthToken($token);
            if ($width === '') {
                return '';
            }

            $normalized[] = $width;
        }

        return implode(' ', $normalized);
    }

    private static function normalizeTableBorderWidthToken(string $value): string
    {
        if (in_array($value, ['thin', 'medium', 'thick'], true)) {
            return $value;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(px|pt|pc|in|cm|mm|em|rem)$/i', $value, $match) !== 1) {
            return '';
        }

        $number = (float) $match[1];
        if ($number < 0.0 || $number > 10000.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . strtolower($match[2]);
    }

    private static function normalizeTableBackgroundColorAttribute(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2]);
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1]);
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $match) === 1) {
            $channels = [(int) $match[1], (int) $match[2], (int) $match[3]];
            foreach ($channels as $channel) {
                if ($channel < 0 || $channel > 255) {
                    return '';
                }
            }

            return 'rgb(' . implode(', ', array_map(static fn (int $channel): string => (string) $channel, $channels)) . ')';
        }

        $name = strtolower($value);
        return in_array($name, [
            'aqua',
            'black',
            'blue',
            'fuchsia',
            'gray',
            'green',
            'grey',
            'lime',
            'maroon',
            'navy',
            'olive',
            'orange',
            'purple',
            'red',
            'silver',
            'teal',
            'transparent',
            'white',
            'yellow',
        ], true) ? $name : '';
    }

    private static function normalizeTableLayoutStyleAttribute(string $style): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) !== 'table-layout') {
                continue;
            }

            $layout = strtolower(trim($value));
            if (in_array($layout, ['auto', 'fixed'], true)) {
                return $layout;
            }
        }

        return '';
    }

    /**
     * @return array{width:string,widthType:string,widthValue:float}
     */
    private static function normalizeTableWidthAttribute(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^[1-9]\d{0,3}$/', $value) === 1) {
            return [
                'width' => (string) (int) $value,
                'widthType' => 'pixels',
                'widthValue' => (float) (int) $value,
            ];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', $value, $match) !== 1) {
            return [];
        }

        $width = (float) $match[1];
        if ($width <= 0.0 || $width > 100.0) {
            return [];
        }

        $formatted = rtrim(rtrim(number_format($width, 4, '.', ''), '0'), '.');

        return [
            'width' => ($formatted === '' ? '0' : $formatted) . '%',
            'widthType' => 'percent',
            'widthValue' => $width,
        ];
    }

    /**
     * @return array{height:string,heightType:string,heightValue:float}
     */
    private static function normalizeTableHeightAttribute(string $value): array
    {
        $height = self::normalizeTableWidthAttribute($value);
        if ($height === []) {
            return [];
        }

        return [
            'height' => (string) $height['width'],
            'heightType' => (string) $height['widthType'],
            'heightValue' => (float) $height['widthValue'],
        ];
    }

    private static function tableAttributeColumnCount(mixed $columns): int
    {
        return is_array($columns) ? count($columns) : 0;
    }

    private static function declaredColumnCount(AstNode $table): int
    {
        return max(
            self::tableAttributeColumnCount($table->attr('alignments', [])),
            self::tableAttributeColumnCount($table->attr('widths', [])),
            self::tableAttributeColumnCount($table->attr('columnSpecs', []))
        );
    }

    /**
     * @return array<int, array{alignment?:string,width?:float|null,source?:array<string, mixed>}>
     */
    private static function sourceColumnSpecs(mixed $specs): array
    {
        if (!is_array($specs)) {
            return [];
        }

        $normalized = [];
        foreach (array_values($specs) as $index => $spec) {
            $record = self::sourceColumnSpec($spec);
            if ($record === []) {
                continue;
            }

            $normalized[$index] = $record;
        }

        return $normalized;
    }

    /**
     * @return array{alignment?:string,width?:float|null,source?:array<string, mixed>}
     */
    private static function sourceColumnSpec(mixed $spec): array
    {
        if (!is_array($spec)) {
            return [];
        }

        $alignmentSource = null;
        $widthSource = null;
        $source = [];
        if (array_is_list($spec)) {
            $alignmentSource = $spec[0] ?? null;
            $widthSource = $spec[1] ?? null;
            $source['kind'] = 'column-spec';
            $source['sourceShape'] = 'tuple';
        } else {
            $alignmentSource = $spec['alignment'] ?? $spec['align'] ?? $spec['alignmentNative'] ?? null;
            $widthSource = $spec['width'] ?? $spec['colWidth'] ?? $spec['widthNative'] ?? null;
            if (isset($spec['source']) && is_array($spec['source'])) {
                $source = self::serializableColumnSource($spec['source']);
            }
            if ($source === []) {
                $source = [
                    'kind' => (string) ($spec['kind'] ?? 'column-spec'),
                    'sourceShape' => 'record',
                ];
            }
        }

        $record = [];
        $alignment = self::sourceColumnSpecAlignment($alignmentSource);
        if ($alignment !== null) {
            $record['alignment'] = $alignment;
            $source['alignment'] = $alignment;
        }

        $width = self::sourceColumnSpecWidth($widthSource);
        if ($width !== null || $widthSource !== null) {
            $record['width'] = $width;
            if ($width !== null) {
                $source['width'] = $width;
            }
        }

        if ($record !== [] && $source !== []) {
            $record['source'] = $source;
        }

        return $record;
    }

    private static function sourceColumnSpecAlignment(mixed $alignment): ?string
    {
        if (is_array($alignment) && !array_is_list($alignment) && isset($alignment['t'])) {
            return self::normalizeAlignment((string) $alignment['t']);
        }

        if (is_array($alignment) && array_is_list($alignment) && count($alignment) === 1) {
            return self::sourceColumnSpecAlignment($alignment[0]);
        }

        if (!is_scalar($alignment)) {
            return null;
        }

        return self::normalizeAlignment((string) $alignment);
    }

    private static function sourceColumnSpecWidth(mixed $width): ?float
    {
        if (is_array($width) && !array_is_list($width) && isset($width['t'])) {
            $tag = (string) $width['t'];
            if ($tag === 'ColWidthDefault') {
                return null;
            }

            if ($tag === 'ColWidth') {
                return self::sourceColumnSpecWidth($width['c'] ?? null);
            }
        }

        if (is_array($width) && array_is_list($width) && count($width) === 1) {
            return self::sourceColumnSpecWidth($width[0]);
        }

        if (!is_numeric($width) || (float) $width <= 0.0) {
            return null;
        }

        return (float) $width;
    }

    /**
     * @param list<mixed> $widths
     */
    private static function positiveWidthTotal(array $widths, int $columnCount): float
    {
        $total = 0.0;
        for ($column = 0; $column < max(0, $columnCount); $column++) {
            $width = $widths[$column] ?? null;
            if (is_numeric($width) && (float) $width > 0.0) {
                $total += (float) $width;
            }
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function invalidWidthRecords(mixed $rawWidths, int $columnCount): array
    {
        if (!is_array($rawWidths)) {
            return [];
        }

        $widths = array_values($rawWidths);
        $records = [];
        for ($column = 0; $column < max(0, $columnCount); $column++) {
            if (!array_key_exists($column, $widths)) {
                continue;
            }

            $value = $widths[$column];
            if ($value === null) {
                continue;
            }

            if (is_numeric($value) && (float) $value >= 0.0) {
                continue;
            }

            $record = [
                'column' => $column,
                'rawType' => get_debug_type($value),
            ];
            if (is_scalar($value)) {
                $record['rawValue'] = $value;
            }

            $records[] = $record;
        }

        return $records;
    }

    private static function roundWidth(float $width): float
    {
        return round($width, 6);
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function columnGroupSourceKey(array $source): string
    {
        return implode('|', [
            (string) ($source['kind'] ?? ''),
            (string) ($source['colgroupIndex'] ?? ''),
            (string) ($source['colIndex'] ?? ''),
            (string) ($source['sourceSpan'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function columnGroupFromSpec(array $spec, array $source): array
    {
        $column = (int) ($spec['column'] ?? 0);
        $group = [
            'kind' => (string) ($source['kind'] ?? 'column'),
            'startColumn' => $column,
            'endColumn' => $column + 1,
            'columns' => [$column],
            'span' => 1,
            'spanOffsets' => [self::columnSourceOffset($source)],
            'alignments' => [(string) ($spec['alignment'] ?? 'default')],
            'widths' => [$spec['width'] ?? null],
            'declaredColumns' => [(bool) ($spec['declared'] ?? false)],
            'source' => self::columnGroupSource($source),
        ];

        foreach (['colgroupIndex', 'colIndex', 'sourceSpan'] as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                $group[$key] = (int) $source[$key];
            }
        }

        return $group;
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $source
     */
    private static function appendColumnGroupSpec(array &$group, array $spec, array $source): void
    {
        $column = (int) ($spec['column'] ?? 0);
        $group['endColumn'] = $column + 1;
        $group['columns'][] = $column;
        $group['span'] = count($group['columns']);
        $group['spanOffsets'][] = self::columnSourceOffset($source);
        $group['alignments'][] = (string) ($spec['alignment'] ?? 'default');
        $group['widths'][] = $spec['width'] ?? null;
        $group['declaredColumns'][] = (bool) ($spec['declared'] ?? false);
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function columnSourceOffset(array $source): int
    {
        return isset($source['spanOffset']) && is_numeric($source['spanOffset']) ? (int) $source['spanOffset'] : 0;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function columnGroupSource(array $source): array
    {
        $record = $source;
        unset($record['column'], $record['spanOffset'], $record['alignment'], $record['width']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function columnSources(AstNode $table): array
    {
        $sources = $table->attr('columnSources', []);
        if (!is_array($sources)) {
            return [];
        }

        $normalized = [];
        foreach ($sources as $index => $source) {
            if (!is_array($source)) {
                continue;
            }

            $record = self::serializableColumnSource($source);
            if ($record === []) {
                continue;
            }

            $column = isset($record['column']) && is_numeric($record['column'])
                ? (int) $record['column']
                : (int) $index;
            if ($column < 0) {
                continue;
            }

            $record['column'] = $column;
            $normalized[$column] = $record;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string|int, mixed> $source
     * @return array<string, mixed>
     */
    private static function serializableColumnSource(array $source): array
    {
        $record = [];
        foreach ($source as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized = self::serializableColumnSourceValue($value);
            if ($normalized['valid'] === true) {
                $record[$key] = $normalized['value'];
            }
        }

        return $record;
    }

    /**
     * @return array{valid:bool,value:mixed}
     */
    private static function serializableColumnSourceValue(mixed $value): array
    {
        if ($value === null || is_scalar($value)) {
            return ['valid' => true, 'value' => $value];
        }

        if (!is_array($value)) {
            return ['valid' => false, 'value' => null];
        }

        $normalized = [];
        foreach ($value as $key => $nestedValue) {
            $key = is_int($key) ? $key : trim((string) $key);
            if ($key === '') {
                continue;
            }

            $nested = self::serializableColumnSourceValue($nestedValue);
            if ($nested['valid'] === true) {
                $normalized[$key] = $nested['value'];
            }
        }

        return ['valid' => true, 'value' => $normalized];
    }

    /**
     * @return array{kind:string,row:int,column:int}
     */
    private static function missingGridSlot(int $row, int $column): array
    {
        return [
            'kind' => 'missing',
            'row' => $row,
            'column' => $column,
        ];
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int} $cell
     * @return array<string, mixed>
     */
    private static function cellGridSlot(int $row, array $cell, int $rowCount, int $columnCount): array
    {
        $sourceRow = (int) ($cell['sourceRow'] ?? $row);
        $sourceRowspan = max(1, (int) ($cell['sourceRowspan'] ?? $cell['rowspan'] ?? 1));
        $slot = [
            'kind' => 'cell',
            'row' => $row,
            'column' => $cell['column'],
            'node' => $cell['node'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            ...self::sourceRowCoordinateFields($sourceRow, $sourceRowspan),
            'colspan' => $cell['colspan'],
            'rowspan' => $cell['rowspan'],
            'verticalAlignment' => self::cellVerticalAlignment($cell['node']),
            'anchorRow' => $row,
            'anchorColumn' => $cell['column'],
            ...self::anchorSourceRowCoordinateFields($sourceRow, $sourceRowspan),
            'occupiedSlots' => self::occupiedSlotRecords(
                $row,
                $cell['column'],
                $cell['colspan'],
                $cell['rowspan'],
                $rowCount,
                $columnCount
            ),
        ];
        if (($cell['rowspanToEnd'] ?? false) === true) {
            $slot['rowspanToEnd'] = true;
            $slot['sourceRowspanAttribute'] = 0;
            $slot['sourceRowspanMode'] = 'to-section-end';
            $slot['anchorSourceRowspanAttribute'] = 0;
            $slot['anchorSourceRowspanMode'] = 'to-section-end';
        }

        return $slot;
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int,sourceRow:int,sourceRowspan:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>} $cell
     * @return array<string, mixed>
     */
    private static function coveredGridSlot(
        int $row,
        int $column,
        int $anchorRow,
        int $anchorColumn,
        string $covering,
        array $cell
    ): array {
        $sourceRow = (int) ($cell['sourceRow'] ?? $anchorRow);
        $sourceRowspan = max(1, (int) ($cell['sourceRowspan'] ?? $cell['rowspan'] ?? 1));
        $slot = [
            'kind' => 'covered',
            'row' => $row,
            'column' => $column,
            'node' => $cell['node'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            ...self::sourceRowCoordinateFields($sourceRow, $sourceRowspan),
            'colspan' => $cell['colspan'],
            'rowspan' => $cell['rowspan'],
            'anchorRow' => $anchorRow,
            'anchorColumn' => $anchorColumn,
            ...self::anchorSourceRowCoordinateFields($sourceRow, $sourceRowspan),
            'covering' => $covering,
        ];
        if (($cell['rowspanToEnd'] ?? false) === true) {
            $slot['rowspanToEnd'] = true;
            $slot['sourceRowspanAttribute'] = 0;
            $slot['sourceRowspanMode'] = 'to-section-end';
            $slot['anchorSourceRowspanAttribute'] = 0;
            $slot['anchorSourceRowspanMode'] = 'to-section-end';
        }

        return $slot;
    }

    /**
     * @return list<array{row:int,column:int,covering:string}>
     */
    private static function occupiedSlotRecords(
        int $anchorRow,
        int $anchorColumn,
        int $colspan,
        int $rowspan,
        int $rowCount,
        int $columnCount
    ): array {
        $slots = [];
        $rowLimit = min(max(0, $rowCount), $anchorRow + max(1, $rowspan));
        $columnLimit = min(max(0, $columnCount), $anchorColumn + max(1, $colspan));

        for ($row = $anchorRow; $row < $rowLimit; $row++) {
            for ($column = $anchorColumn; $column < $columnLimit; $column++) {
                if ($row === $anchorRow && $column === $anchorColumn) {
                    $covering = 'anchor';
                } elseif ($row === $anchorRow) {
                    $covering = 'colspan';
                } elseif ($column === $anchorColumn) {
                    $covering = 'rowspan';
                } else {
                    $covering = 'rowspan-colspan';
                }

                $slots[] = [
                    'row' => $row,
                    'column' => $column,
                    'covering' => $covering,
                ];
            }
        }

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int,sourceRow:int,sourceRowspan:int,sourceRowEnd:int,sourceRowRange:array{0:int,1:int},sourceRows:list<int>} $cell
     */
    private static function appendDeclaredColumnDiagnostic(
        array &$diagnostics,
        string $section,
        int $rowIndex,
        array $cell,
        int $declaredColumnCount
    ): void {
        $rawColspan = self::cellColspan($cell['node']);
        $endColumn = $cell['column'] + $rawColspan;
        if ($endColumn <= $declaredColumnCount) {
            return;
        }

        $diagnostics[] = [
            'code' => 'cell-exceeds-declared-columns',
            'section' => $section,
            'row' => $rowIndex,
            'column' => $cell['column'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            ...self::sourceRowCoordinateFields(
                (int) ($cell['sourceRow'] ?? $rowIndex),
                max(1, (int) ($cell['sourceRowspan'] ?? 1))
            ),
            'colspan' => $rawColspan,
            'declaredColumns' => $declaredColumnCount,
            'endColumn' => $endColumn,
        ];
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array<string, mixed>>
     */
    private static function rowspanOverlapDiagnostics(string $section, array $rows, int $declaredColumnCount): array
    {
        $diagnostics = [];
        $activeRowspans = [];
        $rowCount = count($rows);

        foreach ($rows as $rowIndex => $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $rowActiveRowspans = $activeRowspans;
            $previousActiveColumns = self::activeRichColumns($activeRowspans);
            $consumedActiveColumns = [];
            $column = 0;
            $sourceCell = 0;
            $sourceColumn = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                $rawColspan = self::cellColspan($cell);
                $sourceRowspan = max(1, self::cellRowspanForRows($cell, $rowIndex, $rowCount));
                $overlapColumns = [];
                $coveredBy = [];
                for ($coveredColumn = $sourceColumn; $coveredColumn < $sourceColumn + $rawColspan; $coveredColumn++) {
                    if (($rowActiveRowspans[$coveredColumn]['remainingRows'] ?? 0) <= 0) {
                        continue;
                    }

                    $coveringCell = $rowActiveRowspans[$coveredColumn];
                    $overlapColumns[] = $coveredColumn;
                    $coveredBy[] = [
                        'row' => (int) $coveringCell['anchorRow'],
                        'column' => (int) $coveringCell['anchorColumn'],
                        'sourceCell' => (int) $coveringCell['sourceCell'],
                        'sourceColumn' => (int) $coveringCell['sourceColumn'],
                        ...self::sourceRowCoordinateFields(
                            (int) ($coveringCell['sourceRow'] ?? $coveringCell['anchorRow']),
                            max(1, (int) ($coveringCell['sourceRowspan'] ?? $coveringCell['rowspan'] ?? 1))
                        ),
                        'colspan' => (int) $coveringCell['colspan'],
                        'rowspan' => (int) $coveringCell['rowspan'],
                    ];
                }

                self::skipRichCoveredColumns($activeRowspans, $column, $consumedActiveColumns);
                $endColumn = $column + $rawColspan;
                if ($overlapColumns !== [] && $declaredColumnCount > 0 && $endColumn > $declaredColumnCount) {
                    $diagnostics[] = [
                        'code' => 'cell-overlaps-rowspan',
                        'section' => $section,
                        'row' => $rowIndex,
                        'column' => $column,
                        'endColumn' => $endColumn,
                        'sourceCell' => $sourceCell,
                        'sourceColumn' => $sourceColumn,
                        'sourceEndColumn' => $sourceColumn + $rawColspan,
                        ...self::sourceRowCoordinateFields($rowIndex, $sourceRowspan),
                        'visualShift' => $column - $sourceColumn,
                        'colspan' => $rawColspan,
                        'declaredColumns' => $declaredColumnCount,
                        'overlapColumns' => $overlapColumns,
                        'overlapColumnCount' => count($overlapColumns),
                        'coveredBy' => $coveredBy,
                    ];
                }

                $rowspan = min($sourceRowspan, max(1, $rowCount - $rowIndex));
                if ($rowspan > 1) {
                    self::activateRichRowspan(
                        $activeRowspans,
                        $column,
                        $rawColspan,
                        $rowspan,
                        $rowIndex,
                        $sourceCell,
                        $sourceColumn,
                        $sourceRowspan
                    );
                }

                $column += $rawColspan;
                $sourceColumn += $rawColspan;
                $sourceCell++;
            }

            self::consumeUnusedRichActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
        }

        return $diagnostics;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}> $rowEntries
     * @return list<list<array<string, mixed>>>
     */
    private static function sectionGridForEntries(array $rowEntries, int $columnCount): array
    {
        $rows = [];
        foreach ($rowEntries as $entry) {
            $rows[] = $entry['row'];
        }

        $grid = self::sectionGrid($rows, $columnCount);
        foreach ($grid as $rowIndex => $slots) {
            $entry = $rowEntries[$rowIndex] ?? [
                'header' => false,
                'rowHeadColumns' => 0,
                'rowRole' => '',
            ];
            $headerRow = (bool) $entry['header'];
            $rowHeadColumns = (int) $entry['rowHeadColumns'];
            foreach ($slots as $column => $slot) {
                $slot['rowRole'] = (string) $entry['rowRole'];
                $slot['headerRow'] = $headerRow;
                $slot['rowHeadColumns'] = $rowHeadColumns;
                $slot['headerCell'] = false;
                if (($slot['kind'] ?? '') !== 'missing' && ($slot['node'] ?? null) instanceof AstNode) {
                    $slot['headerCell'] = self::isHeaderCell(
                        $headerRow,
                        $rowHeadColumns,
                        (int) ($slot['anchorColumn'] ?? $slot['column'] ?? $column),
                        $slot['node']
                    );
                }
                $grid[$rowIndex][$column] = $slot;
            }
        }

        return $grid;
    }

    /**
     * @param list<list<array<string, mixed>>> $rows
     * @return list<list<array<string, mixed>>>
     */
    private static function sectionGridRowsWithGlobalCoordinates(array $rows, int $globalRowStart): array
    {
        $globalRowStart = max(0, $globalRowStart);
        foreach ($rows as $rowIndex => $slots) {
            $globalRow = $globalRowStart + (int) $rowIndex;
            foreach ($slots as $column => $slot) {
                $slot['globalRow'] = $globalRow;
                if (isset($slot['anchorRow']) && is_numeric($slot['anchorRow'])) {
                    $anchorGlobalRow = $globalRowStart + max(0, (int) $slot['anchorRow']);
                    $anchorGlobalRowEnd = $anchorGlobalRow + max(1, (int) ($slot['rowspan'] ?? 1));
                    $slot['anchorGlobalRow'] = $anchorGlobalRow;
                    $slot['anchorGlobalRowEnd'] = $anchorGlobalRowEnd;
                    $slot['anchorGlobalRowRange'] = [$anchorGlobalRow, $anchorGlobalRowEnd];
                    $slot['anchorGlobalRows'] = self::integerRange($anchorGlobalRow, $anchorGlobalRowEnd);
                }

                if (($slot['kind'] ?? '') === 'cell') {
                    $globalRowEnd = $globalRow + max(1, (int) ($slot['rowspan'] ?? 1));
                    $slot['globalRowEnd'] = $globalRowEnd;
                    $slot['globalRowRange'] = [$globalRow, $globalRowEnd];
                    $slot['globalRows'] = self::integerRange($globalRow, $globalRowEnd);
                }

                $slots[$column] = $slot;
            }
            $rows[$rowIndex] = $slots;
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     section:string,
     *     rows:list<AstNode>,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     * }>
     */
    private static function sectionRowGroups(AstNode $table, ?int $columnCount): array
    {
        $groups = [];
        $bodyIndex = 0;
        foreach ($table->children as $section) {
            if ($section->type === 'table_head') {
                $entries = self::rowEntries(self::sectionRows($section), true, 0, 'head');
                $groups[] = [
                    'section' => 'head',
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
                continue;
            }

            if ($section->type === 'table_body') {
                $entries = [];
                foreach (self::bodyHeadRows($section) as $row) {
                    $entries[] = [
                        'row' => $row,
                        'header' => true,
                        'rowHeadColumns' => 0,
                        'rowRole' => 'body-head',
                    ];
                }
                $rowHeadColumns = self::rowHeadColumns($section, max(0, $columnCount ?? self::columnCountForRows(self::bodyRows($section))));
                array_push($entries, ...self::rowEntries(self::sectionRows($section), false, $rowHeadColumns, 'body'));
                $groups[] = [
                    'section' => 'body' . ($bodyIndex === 0 ? '' : (string) $bodyIndex),
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
                $bodyIndex++;
                continue;
            }

            if ($section->type === 'table_foot') {
                $entries = self::rowEntries(self::sectionRows($section), false, 0, 'foot');
                $groups[] = [
                    'section' => 'foot',
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<AstNode>
     */
    private static function sectionRows(AstNode $section): array
    {
        return array_values(array_filter(
            $section->children,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
    }

    /**
     * @return list<AstNode>
     */
    private static function bodyRows(AstNode $body): array
    {
        $rows = self::bodyHeadRows($body);

        array_push($rows, ...self::sectionRows($body));

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private static function bodyHeadRows(AstNode $body): array
    {
        $rows = [];
        $bodyHeadRows = $body->attr('headRows', []);
        if (is_array($bodyHeadRows)) {
            foreach ($bodyHeadRows as $row) {
                if ($row instanceof AstNode && $row->type === 'table_row') {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param list<AstNode> $rows
     */
    private static function rowCellCount(array $rows): int
    {
        $cellCount = 0;
        foreach ($rows as $row) {
            foreach ($row->children as $child) {
                if ($child instanceof AstNode && $child->type === 'table_cell') {
                    $cellCount++;
                }
            }
        }

        return $cellCount;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     */
    private static function rowEntries(array $rows, bool $header, int $rowHeadColumns, string $rowRole): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'row' => $row,
                'header' => $header,
                'rowHeadColumns' => $rowHeadColumns,
                'rowRole' => $rowRole,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}> $entries
     * @return list<AstNode>
     */
    private static function entryRows(array $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $entry['row'];
        }

        return $rows;
    }

    private static function cellColspan(AstNode $cell): int
    {
        return max(1, (int) $cell->attr('colspan', 1));
    }

    private static function cellRowspan(AstNode $cell): int
    {
        return max(1, self::cellRawRowspan($cell));
    }

    private static function cellRowspanForRows(AstNode $cell, int $rowIndex, int $rowCount): int
    {
        if (self::cellRowspanToEnd($cell)) {
            return max(1, $rowCount - $rowIndex);
        }

        return self::cellRowspan($cell);
    }

    private static function cellRowspanToEnd(AstNode $cell): bool
    {
        return self::cellRawRowspan($cell) === 0;
    }

    private static function cellRawRowspan(AstNode $cell): int
    {
        $value = $cell->attr('rowspan', 1);
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^-?\d+$/', $value) !== 1) {
                return 1;
            }

            return (int) $value;
        }

        if (!is_int($value) && !is_float($value)) {
            return 1;
        }

        return (int) $value;
    }

    /**
     * @param array<int, int> $activeRowspans
     * @return list<int>
     */
    private static function activeColumns(array $activeRowspans): array
    {
        $columns = [];
        foreach ($activeRowspans as $column => $remainingRows) {
            if ($remainingRows > 0) {
                $columns[] = (int) $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @return list<int>
     */
    private static function activeRichColumns(array $activeRowspans): array
    {
        $columns = [];
        foreach ($activeRowspans as $column => $rowspan) {
            if (($rowspan['remainingRows'] ?? 0) > 0) {
                $columns[] = (int) $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int, int> $activeRowspans
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function skipCoveredColumns(array &$activeRowspans, int &$column, array &$consumedActiveColumns): void
    {
        while (($activeRowspans[$column] ?? 0) > 0) {
            $activeRowspans[$column]--;
            $consumedActiveColumns[$column] = true;
            if ($activeRowspans[$column] <= 0) {
                unset($activeRowspans[$column]);
            }
            $column++;
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function skipRichCoveredColumns(array &$activeRowspans, int &$column, array &$consumedActiveColumns): void
    {
        while (($activeRowspans[$column]['remainingRows'] ?? 0) > 0) {
            $activeRowspans[$column]['remainingRows']--;
            $consumedActiveColumns[$column] = true;
            if (($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                unset($activeRowspans[$column]);
            }
            $column++;
        }
    }

    /**
     * @param array<int, int> $activeRowspans
     */
    private static function activateRowspan(array &$activeRowspans, int $startColumn, int $colspan, int $rowspan): void
    {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            $activeRowspans[$column] = max($activeRowspans[$column] ?? 0, $rowspan - 1);
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     */
    private static function activateRichRowspan(
        array &$activeRowspans,
        int $startColumn,
        int $colspan,
        int $rowspan,
        int $anchorRow,
        int $sourceCell,
        int $sourceColumn,
        int $sourceRowspan
    ): void {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            if (($activeRowspans[$column]['remainingRows'] ?? 0) >= $rowspan - 1) {
                continue;
            }

            $activeRowspans[$column] = [
                'remainingRows' => $rowspan - 1,
                'anchorRow' => $anchorRow,
                'anchorColumn' => $startColumn,
                'sourceCell' => $sourceCell,
                'sourceColumn' => $sourceColumn,
                'sourceRow' => $anchorRow,
                'sourceRowspan' => $sourceRowspan,
                'colspan' => $colspan,
                'rowspan' => $rowspan,
            ];
        }
    }

    /**
     * @param array<int, int> $activeRowspans
     * @param list<int> $previousActiveColumns
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function consumeUnusedActiveColumns(
        array &$activeRowspans,
        array $previousActiveColumns,
        array $consumedActiveColumns
    ): void
    {
        foreach ($previousActiveColumns as $column) {
            if (($consumedActiveColumns[$column] ?? false) || ($activeRowspans[$column] ?? 0) <= 0) {
                continue;
            }

            $activeRowspans[$column]--;
            if ($activeRowspans[$column] <= 0) {
                unset($activeRowspans[$column]);
            }
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @param list<int> $previousActiveColumns
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function consumeUnusedRichActiveColumns(
        array &$activeRowspans,
        array $previousActiveColumns,
        array $consumedActiveColumns
    ): void {
        foreach ($previousActiveColumns as $column) {
            if (($consumedActiveColumns[$column] ?? false) || ($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                continue;
            }

            $activeRowspans[$column]['remainingRows']--;
            if (($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                unset($activeRowspans[$column]);
            }
        }
    }
}
