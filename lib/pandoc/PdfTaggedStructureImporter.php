<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily loaded tagged-PDF structure interpreter. Ordinary untagged documents
 * are rejected by PdfReader's empty-facts gate before this class is compiled.
 */
final class PdfTaggedStructureImporter
{
    private \Closure $blocksFromLinesCallback;
    private \Closure $tableFromTaggedTableCallback;
    private \Closure $paragraphCallback;
    private \Closure $inlinesCallback;
    private \Closure $visibleTextCallback;
    private \Closure $comparableTextCallback;
    private \Closure $tableCellTextCallback;
    private \Closure $itemRoleCallback;
    private \Closure $astAttrsCallback;

    private function __construct(
        callable $blocksFromLines,
        callable $tableFromTaggedTable,
        callable $paragraph,
        callable $inlines,
        callable $visibleText,
        callable $comparableText,
        callable $tableCellText,
        callable $itemRole,
        callable $astAttrs
    ) {
        $this->blocksFromLinesCallback = \Closure::fromCallable($blocksFromLines);
        $this->tableFromTaggedTableCallback = \Closure::fromCallable(
            $tableFromTaggedTable
        );
        $this->paragraphCallback = \Closure::fromCallable($paragraph);
        $this->inlinesCallback = \Closure::fromCallable($inlines);
        $this->visibleTextCallback = \Closure::fromCallable($visibleText);
        $this->comparableTextCallback = \Closure::fromCallable($comparableText);
        $this->tableCellTextCallback = \Closure::fromCallable($tableCellText);
        $this->itemRoleCallback = \Closure::fromCallable($itemRole);
        $this->astAttrsCallback = \Closure::fromCallable($astAttrs);
    }

    /**
     * Compatibility bridge for PdfReader's production entry-point wrappers.
     *
     * @param list<mixed> $arguments
     */
    public static function invoke(
        string $method,
        array $arguments,
        callable $blocksFromLines,
        callable $tableFromTaggedTable,
        callable $paragraph,
        callable $inlines,
        callable $visibleText,
        callable $comparableText,
        callable $tableCellText,
        callable $itemRole,
        callable $astAttrs
    ): mixed {
        $importer = new self(
            $blocksFromLines,
            $tableFromTaggedTable,
            $paragraph,
            $inlines,
            $visibleText,
            $comparableText,
            $tableCellText,
            $itemRole,
            $astAttrs
        );

        return $importer->{$method}(...$arguments);
    }

    /**
     * Select document-global tagged facts for one bounded source-page range.
     * A tag is eligible only when MarkerPDF tied its StructElem/MCR source to
     * known physical pages. Unscoped and contradictory records are omitted so
     * the ordinary text/geometry path remains the conservative fallback.
     *
     * @param list<array<string, mixed>> $structures
     * @return list<array<string, mixed>>
     */
    private function taggedStructuresForPageRange(array $structures, int $startPage, int $endPage): array
    {
        $selected = [];
        foreach ($structures as $structure) {
            if (!is_array($structure)) {
                continue;
            }
            $pages = $this->taggedStructureEvidencedPages($structure);
            if ($pages === []) {
                continue;
            }
            $overlapping = array_values(array_filter(
                $pages,
                static fn (int $page): bool => $page >= $startPage && $page <= $endPage
            ));
            if ($overlapping === []) {
                continue;
            }
            if (count($overlapping) === count($pages)) {
                $selected[] = $structure;
                continue;
            }

            $slicedTable = $this->taggedTableForPageRange($structure, $startPage, $endPage);
            if ($slicedTable !== null) {
                $selected[] = $slicedTable;
            }
        }

        return $selected;
    }

    /**
     * Accept only the parser's own structure provenance. A caller-provided
     * `pageNumbers` field without the matching source object and page scope is
     * not enough to move content between pages.
     *
     * @param array<string, mixed> $structure
     * @return list<int>
     */
    private function taggedStructureEvidencedPages(array $structure): array
    {
        $provenance = $structure['sourceProvenance'] ?? null;
        $provenanceObjectNumber = is_array($provenance) && is_int($provenance['objectNumber'] ?? null)
            ? $provenance['objectNumber']
            : null;
        $structureObjectNumber = is_int($structure['objectNumber'] ?? null)
            ? $structure['objectNumber']
            : $provenanceObjectNumber;
        if (!is_array($provenance)
            || ($provenance['kind'] ?? null) !== 'pdf-structure-element'
            || !is_int($provenanceObjectNumber)
            || !is_int($structureObjectNumber)
            || $provenanceObjectNumber !== $structureObjectNumber
            || !in_array($provenance['pageScope'] ?? null, ['unique-page', 'multiple-pages'], true)
            || !is_array($provenance['pageNumbers'] ?? null)
            || !is_array($structure['pageNumbers'] ?? null)
            || array_values($provenance['pageNumbers']) !== array_values($structure['pageNumbers'])
        ) {
            return [];
        }

        $pages = [];
        foreach ($structure['pageNumbers'] as $page) {
            if (!is_int($page) || $page < 1) {
                return [];
            }
            $pages[$page] = $page;
        }
        $pages = array_values($pages);
        sort($pages, SORT_NUMERIC);

        return $pages;
    }

    /**
     * Slice a multi-page tagged table only at rows whose cells all carry one
     * unambiguous physical page. A spanning/ambiguous row is left for the text
     * fallback rather than copied into either page.
     *
     * @param array<string, mixed> $table
     * @return array<string, mixed>|null
     */
    private function taggedTableForPageRange(array $table, int $startPage, int $endPage): ?array
    {
        $rows = self::tableRows($table);
        if ($rows === []) {
            return null;
        }
        $selectedRows = [];
        $selectedPages = [];
        foreach ($rows as $row) {
            $rowPages = [];
            foreach ($row as $cell) {
                if (!is_array($cell) || $this->taggedTableCellText($cell) === '') {
                    continue;
                }
                $cellPages = $this->taggedStructureEvidencedPages($cell);
                if (count($cellPages) !== 1) {
                    $rowPages = [];
                    break;
                }
                $rowPages[$cellPages[0]] = $cellPages[0];
            }
            if (count($rowPages) !== 1) {
                continue;
            }
            $page = array_values($rowPages)[0];
            if ($page < $startPage || $page > $endPage) {
                continue;
            }
            $selectedRows[] = $row;
            $selectedPages[$page] = $page;
        }
        if ($selectedRows === []) {
            return null;
        }

        $sliced = $table;
        $sliced['rows'] = $selectedRows;
        // Sections describe the unsliced source table and could reintroduce
        // rows from another page. Cell roles (TH/TD) and spans remain intact.
        unset($sliced['sections']);
        $sliced['text'] = implode("\n", array_values(array_filter(array_map(
            fn (array $row): string => implode("\n", array_values(array_filter(array_map(
                fn (array $cell): string => $this->taggedTableCellText($cell),
                $row
            ), static fn (string $text): bool => $text !== ''))),
            $selectedRows
        ), static fn (string $text): bool => $text !== '')));
        $sliced['pageNumbers'] = array_values($selectedPages);
        sort($sliced['pageNumbers'], SORT_NUMERIC);
        $sliced['rangeSelection'] = [
            'kind' => 'tagged-table-row-slice',
            'startPage' => $startPage,
            'endPage' => $endPage,
            'sourcePageNumbers' => $table['pageNumbers'] ?? [],
            'selectedPageNumbers' => $sliced['pageNumbers'],
        ];

        return $sliced;
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedStructureBlocks(array $structureBlocks, array $limitedLines): array
    {
        if ($structureBlocks === [] || $limitedLines === []) {
            return [];
        }

        $structureBlockGroups = $this->taggedStructureBlockLineGroups($structureBlocks);
        if ($structureBlockGroups === []) {
            return [];
        }

        $blocks = [];
        $pendingItems = [];
        $pendingOrdered = false;
        $lineIndex = 0;
        $exactCoverage = $this->taggedStructureBlockLines($structureBlocks) === $limitedLines;

        $flushList = function () use (&$blocks, &$pendingItems, &$pendingOrdered): void {
            if ($pendingItems === []) {
                return;
            }
            $blocks[] = new AstNode($pendingOrdered ? 'ordered_list' : 'bullet_list', [], $pendingItems);
            $pendingItems = [];
            $pendingOrdered = false;
        };

        foreach ($structureBlockGroups as $group) {
            $structureBlock = $group['block'];
            $structureLines = $group['lines'];

            if ($exactCoverage) {
                $matchIndex = $lineIndex;
            } else {
                $matches = $this->lineSequenceIndexes($limitedLines, $structureLines, $lineIndex);
                if (count($matches) !== 1) {
                    return [];
                }
                $matchIndex = $matches[0];
            }

            if ($matchIndex > $lineIndex) {
                $flushList();
                foreach ($this->blocksFromLines(array_slice($limitedLines, $lineIndex, $matchIndex - $lineIndex)) as $fallbackBlock) {
                    $blocks[] = $fallbackBlock;
                }
            }

            if (($structureBlock['kind'] ?? '') === 'table') {
                $rows = self::tableRows($structureBlock);
                if ($rows !== []) {
                    $flushList();
                    $blocks[] = $this->tableFromTaggedTable($structureBlock, $this->taggedAstAttrs($structureBlock));
                }
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $text = $this->taggedStructureBlockText($structureBlock);
            if ($text === '') {
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $role = $this->taggedStructureItemRole($structureBlock);
            $headingLevel = $this->headingLevelFromTaggedRole($role);
            if ($headingLevel !== null) {
                $flushList();
                $blocks[] = new AstNode(
                    'heading',
                    array_replace($this->taggedAstAttrs($structureBlock), ['level' => $headingLevel, 'text' => $text]),
                    $this->inlines($text)
                );
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            if ($this->isTaggedListItemRole($role)) {
                $ordered = $this->taggedListItemIsOrdered($structureBlock);
                if ($pendingItems !== [] && $pendingOrdered !== $ordered) {
                    $flushList();
                }
                $pendingOrdered = $ordered;
                $pendingItems[] = new AstNode('list_item', $this->taggedAstAttrs($structureBlock), [$this->paragraph($text)]);
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $flushList();
            $blocks[] = $this->paragraph($text, $this->taggedAstAttrs($structureBlock));
            $lineIndex = $matchIndex + count($structureLines);
        }

        if ($lineIndex < count($limitedLines)) {
            $flushList();
            foreach ($this->blocksFromLines(array_slice($limitedLines, $lineIndex)) as $fallbackBlock) {
                $blocks[] = $fallbackBlock;
            }
        }
        $flushList();

        return $blocks;
    }

    /**
     * Bind tagged structure groups to unique, ordered source-line
     * occurrences. Gaps are deliberately retained: they identify the regions
     * where geometry/prose evidence must remain eligible. If a tagged spelling
     * has more than one possible occurrence, reject the whole mapping rather
     * than assigning semantic authority to the wrong duplicate.
     *
     * @param list<array<string, mixed>> $structureBlocks
     * @param list<string> $limitedLines
     * @return array{complete:bool,regions:list<array{block:array<string,mixed>,lines:list<string>,sourceStart:int,sourceEnd:int,pageNumbers:list<int>,role:string}>,groupCount:int,failureReason:?string,failedGroupIndex:?int,candidateCount:?int}
     */
    private function taggedStructureBlockCoverage(array $structureBlocks, array $limitedLines): array
    {
        if ($structureBlocks === []) {
            return [
                'complete' => false,
                'regions' => [],
                'groupCount' => 0,
                'failureReason' => null,
                'failedGroupIndex' => null,
                'candidateCount' => null,
            ];
        }
        if ($limitedLines === []) {
            return [
                'complete' => false,
                'regions' => [],
                'groupCount' => 0,
                'failureReason' => 'no-source-text-lines',
                'failedGroupIndex' => null,
                'candidateCount' => 0,
            ];
        }

        $groups = $this->taggedStructureBlockLineGroups($structureBlocks);
        if ($groups === []) {
            return [
                'complete' => false,
                'regions' => [],
                'groupCount' => 0,
                'failureReason' => 'no-tagged-text-groups',
                'failedGroupIndex' => null,
                'candidateCount' => 0,
            ];
        }

        $regions = [];
        $lineIndex = 0;
        foreach ($groups as $groupIndex => $group) {
            $matches = $this->lineSequenceIndexes($limitedLines, $group['lines'], $lineIndex);
            if (count($matches) !== 1) {
                return [
                    'complete' => false,
                    'regions' => [],
                    'groupCount' => count($groups),
                    'failureReason' => $matches === []
                        ? 'tagged-source-occurrence-not-found'
                        : 'ambiguous-tagged-source-occurrence',
                    'failedGroupIndex' => $groupIndex,
                    'candidateCount' => count($matches),
                ];
            }

            $sourceStart = $matches[0];
            $sourceEnd = $sourceStart + count($group['lines']) - 1;
            $block = $group['block'];
            $pages = $this->taggedStructureEvidencedPages($block);
            $regions[] = [
                'block' => $block,
                'lines' => $group['lines'],
                'sourceStart' => $sourceStart,
                'sourceEnd' => $sourceEnd,
                'pageNumbers' => $pages,
                'role' => $this->taggedStructureItemRole($block),
            ];
            $lineIndex = $sourceEnd + 1;
        }

        return [
            'complete' => $this->taggedStructureBlockLines($structureBlocks) === $limitedLines,
            'regions' => $regions,
            'groupCount' => count($groups),
            'failureReason' => null,
            'failedGroupIndex' => null,
            'candidateCount' => null,
        ];
    }

    /**
     * @param array{complete?:bool,regions?:list<array{block:array<string,mixed>,lines:list<string>,sourceStart:int,sourceEnd:int,pageNumbers:list<int>,role:string}>,groupCount?:int,failureReason?:string|null,failedGroupIndex?:int|null,candidateCount?:int|null} $coverage
     * @return list<array{taggedBlocks:list<AstNode>,lines:list<string>,sourceStart:int,sourceEnd:int,pageNumbers:list<int>,role:string}>
     */
    private function partialTaggedRegionsFromCoverage(array $coverage): array
    {
        $rawRegions = is_array($coverage['regions'] ?? null) ? $coverage['regions'] : [];
        if ($rawRegions === []) {
            return [];
        }

        $regions = [];
        foreach ($rawRegions as $region) {
            $block = is_array($region['block'] ?? null) ? $region['block'] : [];
            $lines = is_array($region['lines'] ?? null)
                ? array_values(array_filter($region['lines'], 'is_string'))
                : [];
            if ($block === [] || $lines === []) {
                continue;
            }
            $regions[] = [
                'taggedBlocks' => $this->blocksFromTaggedStructureBlocks([$block], $lines),
                'lines' => $lines,
                'sourceStart' => max(0, (int) ($region['sourceStart'] ?? 0)),
                'sourceEnd' => max(0, (int) ($region['sourceEnd'] ?? 0)),
                'pageNumbers' => is_array($region['pageNumbers'] ?? null)
                    ? array_values(array_map('intval', $region['pageNumbers']))
                    : [],
                'role' => is_string($region['role'] ?? null) ? $region['role'] : '',
            ];
        }

        return $regions;
    }

    /**
     * Replace only an exact consecutive fallback-block occurrence with its
     * locally mapped tagged AST. Uncovered blocks are left byte-for-byte in
     * place, so table, list, code, column, and prose decisions survive. A
     * substring match would require splitting an AST node and an ambiguous
     * duplicate would require guessing; both correctly remain on fallback.
     *
     * @param list<AstNode> $blocks
     * @param list<array{taggedBlocks:list<AstNode>,lines:list<string>,sourceStart:int,sourceEnd:int,pageNumbers:list<int>,role:string}> $regions
     * @return array{blocks:list<AstNode>,diagnostics:list<array<string,mixed>>,complete:bool,appliedCount:int}
     */
    private function blocksWithTaggedRegionArbitration(array $blocks, array $regions): array
    {
        if ($regions === []) {
            return [
                'blocks' => $blocks,
                'diagnostics' => [],
                'complete' => true,
                'appliedCount' => 0,
            ];
        }

        $blockKeys = [];
        foreach ($blocks as $block) {
            $blockKeys[] = $this->pdfComparableLineText($this->pdfAstNodeVisibleText($block));
        }

        $occupied = [];
        $replacements = [];
        $diagnostics = [];
        $appliedCount = 0;
        foreach ($regions as $regionIndex => $region) {
            $targetKey = $this->pdfComparableLineText(implode(' ', $region['lines']));
            $candidates = $targetKey === ''
                ? []
                : $this->taggedFallbackBlockRangeCandidates($blockKeys, $targetKey, $occupied);
            $diagnostic = [
                'regionIndex' => $regionIndex,
                'sourceStartLine' => $region['sourceStart'] + 1,
                'sourceEndLine' => $region['sourceEnd'] + 1,
                'sourceOccurrenceCount' => max(0, $region['sourceEnd'] - $region['sourceStart'] + 1),
                'pageNumbers' => $region['pageNumbers'],
                'taggedRole' => $region['role'],
                'matchedTextDigest' => $targetKey === '' ? null : hash('sha256', $targetKey),
            ];

            if ($region['taggedBlocks'] === []) {
                $diagnostic['status'] = 'fallback-no-tagged-ast';
                $diagnostic['selectedEvidence'] = 'fallback';
                $diagnostics[] = $diagnostic;
                continue;
            }
            if (count($candidates) !== 1) {
                $diagnostic['status'] = count($candidates) > 1
                    ? 'fallback-ambiguous-block-span'
                    : 'fallback-no-exact-block-span';
                $diagnostic['selectedEvidence'] = 'fallback';
                $diagnostic['candidateCount'] = count($candidates);
                $diagnostics[] = $diagnostic;
                continue;
            }

            [$start, $end] = $candidates[0];
            for ($index = $start; $index <= $end; $index++) {
                $occupied[$index] = true;
            }
            $replacements[$start] = [
                'end' => $end,
                'blocks' => $region['taggedBlocks'],
            ];
            $diagnostic['status'] = 'applied';
            $diagnostic['selectedEvidence'] = 'tagged-structure';
            $diagnostic['fallbackBlockStart'] = $start;
            $diagnostic['fallbackBlockEnd'] = $end;
            $diagnostic['fallbackBlockTypes'] = array_map(
                static fn (AstNode $block): string => $block->type,
                array_slice($blocks, $start, $end - $start + 1)
            );
            $diagnostics[] = $diagnostic;
            $appliedCount++;
        }

        if ($replacements === []) {
            return [
                'blocks' => $blocks,
                'diagnostics' => $diagnostics,
                'complete' => false,
                'appliedCount' => 0,
            ];
        }

        ksort($replacements, SORT_NUMERIC);
        $arbitrated = [];
        for ($index = 0, $count = count($blocks); $index < $count; $index++) {
            $replacement = $replacements[$index] ?? null;
            if (is_array($replacement)) {
                foreach ($replacement['blocks'] as $taggedBlock) {
                    $arbitrated[] = $taggedBlock;
                }
                $index = (int) $replacement['end'];
                continue;
            }
            if (!isset($occupied[$index])) {
                $arbitrated[] = $blocks[$index];
            }
        }

        return [
            'blocks' => $arbitrated,
            'diagnostics' => $diagnostics,
            'complete' => $appliedCount === count($regions),
            'appliedCount' => $appliedCount,
        ];
    }

    /**
     * @param list<string> $blockKeys
     * @param array<int, true> $occupied
     * @return list<array{0:int,1:int}>
     */
    private function taggedFallbackBlockRangeCandidates(array $blockKeys, string $targetKey, array $occupied): array
    {
        $candidates = [];
        $count = count($blockKeys);
        for ($start = 0; $start < $count; $start++) {
            if (isset($occupied[$start]) || $blockKeys[$start] === '') {
                continue;
            }
            $candidateKey = '';
            for ($end = $start; $end < $count; $end++) {
                if (isset($occupied[$end]) || $blockKeys[$end] === '') {
                    break;
                }
                $candidateKey .= $blockKeys[$end];
                if (!str_starts_with($targetKey, $candidateKey)) {
                    break;
                }
                if ($candidateKey === $targetKey) {
                    $candidates[] = [$start, $end];
                    break;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @return list<array{block: array<string, mixed>, lines: list<string>}>
     */
    private function taggedStructureBlockLineGroups(array $structureBlocks): array
    {
        $groups = [];
        foreach ($structureBlocks as $structureBlock) {
            $lines = [];
            if (($structureBlock['kind'] ?? '') === 'table') {
                foreach (self::tableRows($structureBlock) as $row) {
                    foreach ($row as $cell) {
                        $text = $this->taggedTableCellText($cell);
                        if ($text !== '') {
                            $lines[] = $text;
                        }
                    }
                }
            } else {
                $text = $this->taggedStructureBlockText($structureBlock);
                if ($text !== '') {
                    $lines[] = $text;
                }
            }

            if ($lines !== []) {
                $groups[] = [
                    'block' => $structureBlock,
                    'lines' => $lines,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param list<string> $lines
     * @param list<string> $sequence
     * @return list<int>
     */
    private function lineSequenceIndexes(array $lines, array $sequence, int $startIndex): array
    {
        if ($sequence === []) {
            return [];
        }

        $matches = [];
        $lineCount = count($lines);
        $sequenceCount = count($sequence);
        $lastStart = $lineCount - $sequenceCount;
        for ($index = max(0, $startIndex); $index <= $lastStart; $index++) {
            for ($offset = 0; $offset < $sequenceCount; $offset++) {
                if ($lines[$index + $offset] !== $sequence[$offset]) {
                    continue 2;
                }
            }
            $matches[] = $index;
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @return list<string>
     */
    private function taggedStructureBlockLines(array $structureBlocks): array
    {
        $lines = [];
        foreach ($structureBlocks as $structureBlock) {
            if (($structureBlock['kind'] ?? '') === 'table') {
                foreach (self::tableRows($structureBlock) as $row) {
                    foreach ($row as $cell) {
                        $text = $this->taggedTableCellText($cell);
                        if ($text !== '') {
                            $lines[] = $text;
                        }
                    }
                }
                continue;
            }

            $text = $this->taggedStructureBlockText($structureBlock);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedTables(array $tables, array $limitedLines): array
    {
        if ($tables === [] || $this->taggedTableLines($tables) !== $limitedLines) {
            return [];
        }

        $blocks = [];
        foreach ($tables as $table) {
            $rows = self::tableRows($table);
            if ($rows !== []) {
                $blocks[] = $this->tableFromTaggedTable($table, $this->taggedAstAttrs($table));
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @return list<string>
     */
    private function taggedTableLines(array $tables): array
    {
        $lines = [];
        foreach ($tables as $table) {
            foreach (self::tableRows($table) as $row) {
                foreach ($row as $cell) {
                    $text = $this->taggedTableCellText($cell);
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $table
     * @return list<list<array<string, mixed>>>
     */
    public static function tableRows(array $table): array
    {
        $rawRows = $table['rows'] ?? [];
        if (!is_array($rawRows)) {
            return [];
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $row = [];
            foreach ($rawRow as $rawCell) {
                if (is_array($rawCell)) {
                    $row[] = $rawCell;
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $table
     * @return list<array<string, mixed>>
     */
    public static function tableSections(array $table): array
    {
        $rawSections = $table['sections'] ?? [];
        if (!is_array($rawSections)) {
            return [];
        }

        $sections = [];
        foreach ($rawSections as $rawSection) {
            if (!is_array($rawSection) || self::tableRows($rawSection) === []) {
                continue;
            }

            $sections[] = $rawSection;
        }

        return $sections;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedStructureItems(array $items, array $limitedLines): array
    {
        if ($items === [] || $this->taggedStructureItemLines($items) !== $limitedLines) {
            return [];
        }

        $blocks = [];
        $pendingItems = [];
        $pendingOrdered = false;

        $flushList = function () use (&$blocks, &$pendingItems, &$pendingOrdered): void {
            if ($pendingItems === []) {
                return;
            }
            $blocks[] = new AstNode($pendingOrdered ? 'ordered_list' : 'bullet_list', [], $pendingItems);
            $pendingItems = [];
            $pendingOrdered = false;
        };

        foreach ($items as $item) {
            $text = $this->taggedStructureItemText($item);
            if ($text === '') {
                continue;
            }

            $role = $this->taggedStructureItemRole($item);
            $headingLevel = $this->headingLevelFromTaggedRole($role);
            if ($headingLevel !== null) {
                $flushList();
                $blocks[] = new AstNode(
                    'heading',
                    array_replace($this->taggedAstAttrs($item), ['level' => $headingLevel, 'text' => $text]),
                    $this->inlines($text)
                );
                continue;
            }

            if ($this->isTaggedListItemRole($role)) {
                $ordered = $this->taggedListItemIsOrdered($item);
                if ($pendingItems !== [] && $pendingOrdered !== $ordered) {
                    $flushList();
                }
                $pendingOrdered = $ordered;
                $pendingItems[] = new AstNode('list_item', $this->taggedAstAttrs($item), [$this->paragraph($text)]);
                continue;
            }

            $flushList();
            $blocks[] = $this->paragraph($text, $this->taggedAstAttrs($item));
        }
        $flushList();

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function taggedStructureItemLines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            $text = $this->taggedStructureItemText($item);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function taggedStructureItemText(array $item): string
    {
        $text = $item['text'] ?? '';
        if (!is_string($text)) {
            return '';
        }

        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<string, mixed> $structureBlock
     */
    private function taggedStructureBlockText(array $structureBlock): string
    {
        return $this->taggedStructureItemText($structureBlock);
    }

    private function headingLevelFromTaggedRole(string $role): ?int
    {
        if (preg_match('/^H([1-6])$/', $role, $match) === 1) {
            return (int) $match[1];
        }

        return $role === 'H' ? 1 : null;
    }

    private function isTaggedListItemRole(string $role): bool
    {
        return in_array($role, ['LI', 'LBODY'], true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function taggedListItemIsOrdered(array $item): bool
    {
        $attributes = $item['attributes'] ?? [];
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($attributes as $attributeDictionary) {
            if (!is_array($attributeDictionary)) {
                continue;
            }

            $numbering = $attributeDictionary['ListNumbering'] ?? null;
            if (!is_string($numbering)) {
                continue;
            }

            return !in_array($numbering, ['None', 'Disc', 'Circle', 'Square'], true);
        }

        return false;
    }

    /** @param list<string> $lines @return list<AstNode> */
    private function blocksFromLines(array $lines): array
    {
        return ($this->blocksFromLinesCallback)($lines);
    }

    /** @param array<string,mixed> $table @param array<string,mixed> $attrs */
    private function tableFromTaggedTable(array $table, array $attrs = []): AstNode
    {
        return ($this->tableFromTaggedTableCallback)($table, $attrs);
    }

    /** @param array<string,mixed> $attrs */
    private function paragraph(string $text, array $attrs = []): AstNode
    {
        return ($this->paragraphCallback)($text, $attrs);
    }

    /** @return list<AstNode> */
    private function inlines(string $text): array
    {
        return ($this->inlinesCallback)($text);
    }

    private function pdfAstNodeVisibleText(AstNode $node): string
    {
        return ($this->visibleTextCallback)($node);
    }

    private function pdfComparableLineText(string $text): string
    {
        return ($this->comparableTextCallback)($text);
    }

    /** @param array<string,mixed> $cell */
    private function taggedTableCellText(array $cell): string
    {
        return ($this->tableCellTextCallback)($cell);
    }

    /** @param array<string,mixed> $item */
    private function taggedStructureItemRole(array $item): string
    {
        return ($this->itemRoleCallback)($item);
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function taggedAstAttrs(array $item): array
    {
        return ($this->astAttrsCallback)($item);
    }
}
