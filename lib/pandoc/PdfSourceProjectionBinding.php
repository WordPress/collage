<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Project normalized PDF source occurrences onto exact output byte ranges.
 *
 * The solver is compiled at the positioned-run spill trough. Keeping it out
 * of the late occurrence ledger avoids a large class compile in a fragmented
 * end-of-import heap.
 */
final class PdfSourceProjectionBinding
{
    private const ORDER_MATCH_CANDIDATE_LIMIT = 256;
    private const SOURCE_BINDING_STATE_LIMIT = 100000;

    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /**
     * An empty output projection is valid only for a standalone list marker
     * which names its immediately following source occurrence as the complete
     * text anchor for one final list item. This check is intentionally local;
     * the live AST target is validated after ordinary byte decoration.
     *
     * @param array<string,mixed> $record
     * @param array<string,mixed>|null $next
     */
    private static function sourceBindingStructuralMarkerRecordFailureReason(
        array $record,
        ?array $next
    ): ?string {
        $proof = $record['semanticStructureProof'] ?? null;
        if (($record['disposition'] ?? null) !== 'semantic-structure'
            || (string) ($record['significant'] ?? '') !== ''
            || ($record['textProjection'] ?? null) !== ''
            || !is_array($proof)
            || ($record['allowOrderChange'] ?? false) === true
            || is_array($record['orderProof'] ?? null)) {
            return 'empty-output-projection-has-no-exact-structural-target';
        }

        $sourceSignificant = (string) ($record['sourceSignificant'] ?? '');
        $listType = (string) ($proof['listType'] ?? '');
        $markerOrdinal = $proof['markerOrdinal'] ?? null;
        if ($listType === 'ordered') {
            if (preg_match('/^([0-9]+)[.)]$/D', $sourceSignificant, $match) !== 1
                || !is_int($markerOrdinal)
                || (int) $match[1] !== $markerOrdinal) {
                return 'semantic-list-marker-proof-does-not-match-source-marker';
            }
        } elseif ($listType === 'bullet') {
            if (preg_match('/^(?:\*|\x{2022}|\x{25CF}|\x{25AA}|\x{25A0}|\x{2043})$/uD', $sourceSignificant) !== 1
                || $markerOrdinal !== null) {
                return 'semantic-list-marker-proof-does-not-match-source-marker';
            }
        } else {
            return 'semantic-list-marker-proof-does-not-match-source-marker';
        }
        if (!hash_equals((string) ($proof['markerDigest'] ?? ''), hash('sha256', $sourceSignificant))) {
            return 'semantic-list-marker-proof-does-not-match-source-marker';
        }

        if (!is_array($next)
            || ($next['id'] ?? null) !== ($proof['anchorSourceOccurrenceId'] ?? null)
            || !isset(self::OUTPUT_DISPOSITIONS[$next['disposition'] ?? ''])
            || (string) ($next['significant'] ?? '') === ''
            || is_array($next['semanticStructureProof'] ?? null)
            || !is_int($record['stream'] ?? null)
            || !is_int($next['stream'] ?? null)
            || $record['stream'] < 1
            || $next['stream'] !== $record['stream']
            || (int) ($next['page'] ?? 0) !== (int) ($record['page'] ?? 0)) {
            return 'semantic-list-marker-anchor-is-not-the-next-same-stream-occurrence';
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return array{text:string,units:list<array<string,mixed>>,failureReason:?string}
     */
    public static function sourceBindingProjection(array $records): array
    {
        foreach ($records as $index => $record) {
            if (($record['disposition'] ?? null) === 'unresolved') {
                return ['text' => '', 'units' => [], 'failureReason' => 'source-occurrence-is-unresolved'];
            }
            $outputDisposition = isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? '']);
            $structuralProof = $record['semanticStructureProof'] ?? null;
            if (($outputDisposition && (string) ($record['significant'] ?? '') === '')
                || is_array($structuralProof)) {
                $failureReason = self::sourceBindingStructuralMarkerRecordFailureReason(
                    $record,
                    $records[$index + 1] ?? null
                );
                if ($failureReason !== null) {
                    return ['text' => '', 'units' => [], 'failureReason' => $failureReason];
                }
            }
        }
        $contributors = array_values(array_filter(
            $records,
            static fn (array $record): bool => isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                && (string) ($record['significant'] ?? '') !== ''
        ));
        $text = '';
        $units = [];
        $index = 0;
        while ($index < count($contributors)) {
            $record = $contributors[$index];
            $proof = ($record['allowOrderChange'] ?? false) === true
                ? ($record['orderProof'] ?? null)
                : null;
            if (($record['allowOrderChange'] ?? false) === true && !is_array($proof)) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'authorized-order-change-has-no-exact-proof',
                ];
            }
            if (!is_array($proof)) {
                $start = strlen($text);
                $text .= (string) $record['significant'];
                $units[] = [
                    'records' => [$record],
                    'projection' => (string) $record['significant'],
                    'start' => $start,
                    'end' => strlen($text),
                    'scopeId' => null,
                ];
                $index++;
                continue;
            }

            $scopeIds = $proof['sourceOccurrenceIds'];
            $scopeRecords = array_slice($contributors, $index, count($scopeIds));
            if (array_column($scopeRecords, 'id') !== $scopeIds) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'mapped-order-proof-source-occurrences-are-not-contiguous',
                ];
            }
            foreach ($scopeRecords as $scopeRecord) {
                if (($scopeRecord['allowOrderChange'] ?? false) !== true
                    || ($scopeRecord['orderProof'] ?? null) !== $proof) {
                    return [
                        'text' => '',
                        'units' => [],
                        'failureReason' => 'mapped-order-proof-is-not-identical-across-scope',
                    ];
                }
            }
            $declaredPages = is_array($proof['sourcePages'] ?? null)
                ? array_values($proof['sourcePages'])
                : [];
            if ($declaredPages !== []) {
                $actualPages = array_values(array_unique(array_map(
                    static fn (array $scopeRecord): int => (int) ($scopeRecord['page'] ?? 0),
                    $scopeRecords
                ), SORT_NUMERIC));
                sort($actualPages, SORT_NUMERIC);
                if ($actualPages !== $declaredPages) {
                    return [
                        'text' => '',
                        'units' => [],
                        'failureReason' => 'mapped-order-proof-pages-do-not-match-scope',
                    ];
                }
            }
            $scopeProjection = PdfSourceDispositionLedger::significantText($proof['emittedTextProjection']);
            $sourceCharacters = [];
            $sourceBytes = 0;
            foreach ($scopeRecords as $scopeRecord) {
                $sourceBytes += PdfSourceDispositionLedger::updateSignificantCharacterInventory(
                    $sourceCharacters,
                    (string) $scopeRecord['significant']
                );
            }
            $emittedCharacters = [];
            $emittedBytes = PdfSourceDispositionLedger::updateSignificantCharacterInventory($emittedCharacters, $scopeProjection);
            ksort($sourceCharacters);
            ksort($emittedCharacters);
            if ($sourceBytes !== $emittedBytes || $sourceCharacters !== $emittedCharacters) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'mapped-order-proof-does-not-conserve-source-characters',
                ];
            }
            $start = strlen($text);
            $text .= $scopeProjection;
            $units[] = [
                'records' => $scopeRecords,
                'projection' => $scopeProjection,
                'start' => $start,
                'end' => strlen($text),
                'scopeId' => $proof['scopeId'],
            ];
            $index += count($scopeIds);
        }

        return ['text' => $text, 'units' => $units, 'failureReason' => null];
    }

    /**
     * @param list<AstNode> $blocks
     * @return array{text:string,leaves:list<array<string,mixed>>,blocks:list<array<string,mixed>>}
     */
    public static function sourceBindingOutput(array $blocks): array
    {
        $text = '';
        $leaves = [];
        $blockRanges = [];
        foreach ($blocks as $blockIndex => $block) {
            $start = strlen($text);
            self::appendSourceBindingNodeText(
                $block,
                (string) $blockIndex,
                $blockIndex,
                $text,
                $leaves
            );
            $blockRanges[] = [
                'index' => $blockIndex,
                'start' => $start,
                'end' => strlen($text),
            ];
        }

        return ['text' => $text, 'leaves' => $leaves, 'blocks' => $blockRanges];
    }

    /** @param list<array<string,mixed>> $leaves */
    private static function appendSourceBindingNodeText(
        AstNode $node,
        string $path,
        int $blockIndex,
        string &$text,
        array &$leaves
    ): void {
        if ($node->type === 'text' || $node->type === 'code_block') {
            $significant = PdfSourceDispositionLedger::significantText((string) $node->attr('text', ''));
            if ($significant === '') {
                return;
            }
            $start = strlen($text);
            $text .= $significant;
            $leaves[] = [
                'path' => $path,
                'blockIndex' => $blockIndex,
                'start' => $start,
                'end' => strlen($text),
            ];

            return;
        }
        foreach ($node->children() as $childIndex => $child) {
            self::appendSourceBindingNodeText(
                $child,
                $path . '.' . $childIndex,
                $blockIndex,
                $text,
                $leaves
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $units
     * @return list<array<string,mixed>>|null
     */
    public static function directSourceBindingRanges(array $units): ?array
    {
        $ranges = [];
        foreach ($units as $unit) {
            if (($unit['scopeId'] ?? null) === null) {
                $record = $unit['records'][0] ?? null;
                if (!is_array($record) || (string) ($record['significant'] ?? '') === '') {
                    return null;
                }
                $ranges[] = [
                    'sourceOccurrenceId' => $record['id'],
                    'sourceStart' => 0,
                    'sourceEnd' => strlen($record['significant']),
                    'outputStart' => $unit['start'],
                    'outputEnd' => $unit['end'],
                    'mappingMode' => 'exact-sequence',
                    'scopeId' => null,
                ];
                continue;
            }

            $scopeRanges = self::uniqueScopeInterleavingRanges($unit);
            if ($scopeRanges === null) {
                return null;
            }
            array_push($ranges, ...$scopeRanges);
        }

        return $ranges;
    }

    /**
     * Bind one mapped order scope even when unchanged occurrences split its
     * emitted projection. The unchanged occurrences must have one exact,
     * source-ordered placement, and removing them must produce exactly the
     * scope projection. A scope token that crosses an insertion is not
     * assigned a guessed discontiguous destination.
     *
     * @param list<array<string,mixed>> $units
     * @return list<array<string,mixed>>|null
     */
    public static function uniqueInterleavedSourceBindingRanges(array $units, string $output): ?array
    {
        $scopeIndexes = [];
        $exactTexts = [];
        $exactUnitIndexes = [];
        foreach ($units as $unitIndex => $unit) {
            if (($unit['scopeId'] ?? null) !== null) {
                $scopeIndexes[] = $unitIndex;
                continue;
            }
            $projection = (string) ($unit['projection'] ?? '');
            if ($projection === '') {
                return null;
            }
            $exactTexts[] = $projection;
            $exactUnitIndexes[] = $unitIndex;
        }
        if (count($scopeIndexes) !== 1 || $exactTexts === []) {
            return null;
        }

        $scopeIndex = $scopeIndexes[0];
        $scopeUnit = $units[$scopeIndex];
        $scopeProjection = (string) ($scopeUnit['projection'] ?? '');
        $layout = self::uniqueInterleavedExactProjectionLayout(
            $output,
            $exactTexts,
            $scopeProjection
        );
        if ($layout === null) {
            return null;
        }

        $ranges = [];
        foreach ($layout['exactRanges'] as $exactIndex => $outputRange) {
            $unit = $units[$exactUnitIndexes[$exactIndex]];
            $record = $unit['records'][0] ?? null;
            if (!is_array($record)) {
                return null;
            }
            $ranges[] = [
                'sourceOccurrenceId' => $record['id'],
                'sourceStart' => 0,
                'sourceEnd' => strlen((string) $record['significant']),
                'outputStart' => $outputRange['start'],
                'outputEnd' => $outputRange['end'],
                'mappingMode' => 'exact-sequence',
                'scopeId' => null,
            ];
        }

        $scopeUnit['start'] = 0;
        $scopeUnit['end'] = strlen($scopeProjection);
        $localScopeRanges = self::uniqueScopeInterleavingRanges($scopeUnit);
        if ($localScopeRanges === null) {
            return null;
        }
        foreach ($localScopeRanges as $localRange) {
            $translated = null;
            foreach ($layout['scopeSpans'] as $scopeSpan) {
                if ($localRange['outputStart'] < $scopeSpan['projectionStart']
                    || $localRange['outputEnd'] > $scopeSpan['projectionEnd']) {
                    continue;
                }
                $translated = $localRange;
                $translated['outputStart'] = $scopeSpan['outputStart']
                    + $localRange['outputStart'] - $scopeSpan['projectionStart'];
                $translated['outputEnd'] = $scopeSpan['outputStart']
                    + $localRange['outputEnd'] - $scopeSpan['projectionStart'];
                break;
            }
            if ($translated === null) {
                return null;
            }
            $ranges[] = $translated;
        }

        return $ranges;
    }

    /**
     * Find the sole placement of unchanged exact occurrences for which the
     * intervening output, concatenated byte-for-byte, equals a mapped scope's
     * declared projection.
     *
     * @param list<string> $exactTexts
     * @return array{exactRanges:list<array{start:int,end:int}>,scopeSpans:list<array{projectionStart:int,projectionEnd:int,outputStart:int,outputEnd:int}>}|null
     */
    public static function uniqueInterleavedExactProjectionLayout(
        string $output,
        array $exactTexts,
        string $scopeProjection
    ): ?array {
        $solutionCount = 0;
        $solution = null;
        $candidateCount = 0;
        $limitExceeded = false;
        $search = function (
            int $exactIndex,
            int $outputCursor,
            int $scopeCursor,
            array $exactRanges,
            array $scopeSpans
        ) use (
            &$search,
            &$solutionCount,
            &$solution,
            &$candidateCount,
            &$limitExceeded,
            $output,
            $exactTexts,
            $scopeProjection
        ): void {
            if ($solutionCount > 1 || $limitExceeded) {
                return;
            }
            if ($exactIndex >= count($exactTexts)) {
                $tail = substr($output, $outputCursor);
                $tailLength = strlen($tail);
                if ($scopeCursor + $tailLength !== strlen($scopeProjection)
                    || !hash_equals(substr($scopeProjection, $scopeCursor), $tail)) {
                    return;
                }
                if ($tailLength > 0) {
                    $scopeSpans[] = [
                        'projectionStart' => $scopeCursor,
                        'projectionEnd' => $scopeCursor + $tailLength,
                        'outputStart' => $outputCursor,
                        'outputEnd' => strlen($output),
                    ];
                }
                $solutionCount++;
                $solution = [
                    'exactRanges' => $exactRanges,
                    'scopeSpans' => $scopeSpans,
                ];

                return;
            }

            $exact = $exactTexts[$exactIndex];
            if ($exact === '') {
                $limitExceeded = true;

                return;
            }
            $searchOffset = $outputCursor;
            while (($found = strpos($output, $exact, $searchOffset)) !== false) {
                $candidateCount++;
                if ($candidateCount > self::ORDER_MATCH_CANDIDATE_LIMIT) {
                    $limitExceeded = true;

                    return;
                }
                $gap = substr($output, $outputCursor, $found - $outputCursor);
                $gapLength = strlen($gap);
                if ($scopeCursor + $gapLength <= strlen($scopeProjection)
                    && hash_equals(substr($scopeProjection, $scopeCursor, $gapLength), $gap)) {
                    $nextExactRanges = $exactRanges;
                    $nextExactRanges[] = [
                        'start' => $found,
                        'end' => $found + strlen($exact),
                    ];
                    $nextScopeSpans = $scopeSpans;
                    if ($gapLength > 0) {
                        $nextScopeSpans[] = [
                            'projectionStart' => $scopeCursor,
                            'projectionEnd' => $scopeCursor + $gapLength,
                            'outputStart' => $outputCursor,
                            'outputEnd' => $found,
                        ];
                    }
                    $search(
                        $exactIndex + 1,
                        $found + strlen($exact),
                        $scopeCursor + $gapLength,
                        $nextExactRanges,
                        $nextScopeSpans
                    );
                }
                $searchOffset = $found + 1;
            }
        };
        $search(0, 0, 0, [], []);

        return !$limitExceeded && $solutionCount === 1 ? $solution : null;
    }

    /**
     * @param array<string,mixed> $unit
     * @return list<array<string,mixed>>|null
     */
    private static function uniqueScopeInterleavingRanges(array $unit): ?array
    {
        $declaredEmittedRanges = $unit['records'][0]['orderProof']['emittedSourceRanges'] ?? null;
        if (is_array($declaredEmittedRanges)) {
            return self::declaredScopeSourceRanges($unit, $declaredEmittedRanges);
        }

        $declaredEmittedIds = $unit['records'][0]['orderProof']['emittedSourceOccurrenceIds'] ?? null;
        if (is_array($declaredEmittedIds)) {
            return self::declaredScopeOccurrenceRanges($unit, $declaredEmittedIds);
        }

        $sourceTokens = [];
        foreach ($unit['records'] as $record) {
            $tokens = self::sourceBindingTokens((string) $record['significant']);
            if ($tokens === []) {
                return null;
            }
            $sourceTokens[] = $tokens;
        }
        $outputTokens = self::sourceBindingTokens((string) $unit['projection']);
        $sourceTokenCount = array_sum(array_map('count', $sourceTokens));
        if ($outputTokens === [] || count($outputTokens) !== $sourceTokenCount) {
            return null;
        }

        $memo = [];
        $choices = [];
        $stateCount = 0;
        $solve = function (array $positions) use (
            &$solve,
            &$memo,
            &$choices,
            &$stateCount,
            $sourceTokens,
            $outputTokens
        ): int {
            $key = implode(',', $positions);
            if (isset($memo[$key])) {
                return $memo[$key];
            }
            $stateCount++;
            if ($stateCount > self::SOURCE_BINDING_STATE_LIMIT) {
                return $memo[$key] = 2;
            }
            $outputIndex = array_sum($positions);
            if ($outputIndex === count($outputTokens)) {
                foreach ($positions as $sourceIndex => $position) {
                    if ($position !== count($sourceTokens[$sourceIndex])) {
                        return $memo[$key] = 0;
                    }
                }

                return $memo[$key] = 1;
            }

            $count = 0;
            $choice = null;
            foreach ($positions as $sourceIndex => $position) {
                if (!isset($sourceTokens[$sourceIndex][$position])
                    || $sourceTokens[$sourceIndex][$position]['text'] !== $outputTokens[$outputIndex]['text']) {
                    continue;
                }
                $next = $positions;
                $next[$sourceIndex]++;
                $branchCount = $solve($next);
                if ($branchCount < 1) {
                    continue;
                }
                if ($count === 0 && $branchCount === 1) {
                    $choice = $sourceIndex;
                } else {
                    $choice = null;
                }
                $count = min(2, $count + $branchCount);
                if ($count > 1) {
                    break;
                }
            }
            if ($count === 1 && $choice !== null) {
                $choices[$key] = $choice;
            }

            return $memo[$key] = $count;
        };

        $positions = array_fill(0, count($sourceTokens), 0);
        if ($solve($positions) !== 1) {
            return null;
        }
        $ranges = [];
        for ($outputIndex = 0; $outputIndex < count($outputTokens); $outputIndex++) {
            $key = implode(',', $positions);
            $sourceIndex = $choices[$key] ?? null;
            if (!is_int($sourceIndex)) {
                return null;
            }
            $sourceToken = $sourceTokens[$sourceIndex][$positions[$sourceIndex]];
            $outputToken = $outputTokens[$outputIndex];
            $record = $unit['records'][$sourceIndex];
            $ranges[] = [
                'sourceOccurrenceId' => $record['id'],
                'sourceStart' => $sourceToken['start'],
                'sourceEnd' => $sourceToken['end'],
                'outputStart' => $unit['start'] + $outputToken['start'],
                'outputEnd' => $unit['start'] + $outputToken['end'],
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $positions[$sourceIndex]++;
        }

        return $ranges;
    }

    /**
     * A positioned-text producer can retain a finer proof than whole source
     * occurrences: the exact occurrence-local byte ranges painted into each
     * final visual line. Consume that declaration only when it is a complete,
     * non-overlapping partition of every source projection in the scope and
     * its emitted-order concatenation equals the declared output byte for
     * byte. Repeated spellings and equal character inventories therefore
     * cannot authorize a different range order.
     *
     * @param array<string,mixed> $unit
     * @param list<array{sourceOccurrenceId:string,sourceStart:int,sourceEnd:int}> $declaredRanges
     * @return list<array<string,mixed>>|null
     */
    private static function declaredScopeSourceRanges(
        array $unit,
        array $declaredRanges
    ): ?array {
        $recordsById = [];
        foreach ($unit['records'] as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '' || isset($recordsById[$id])) {
                return null;
            }
            $recordsById[$id] = $record;
        }
        if ($declaredRanges === [] || $recordsById === []) {
            return null;
        }

        $cursor = (int) ($unit['start'] ?? 0);
        $projection = '';
        $coverageById = [];
        $ranges = [];
        foreach ($declaredRanges as $declaredRange) {
            if (!is_array($declaredRange)) {
                return null;
            }
            $id = is_string($declaredRange['sourceOccurrenceId'] ?? null)
                ? $declaredRange['sourceOccurrenceId']
                : '';
            $sourceStart = is_int($declaredRange['sourceStart'] ?? null)
                ? $declaredRange['sourceStart']
                : null;
            $sourceEnd = is_int($declaredRange['sourceEnd'] ?? null)
                ? $declaredRange['sourceEnd']
                : null;
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record)
                ? (string) ($record['significant'] ?? '')
                : '';
            if ($significant === ''
                || $sourceStart === null
                || $sourceEnd === null
                || $sourceStart < 0
                || $sourceEnd <= $sourceStart
                || $sourceEnd > strlen($significant)
                || !self::isWholeUtf8CodePointRange($significant, $sourceStart, $sourceEnd)) {
                return null;
            }
            $text = substr($significant, $sourceStart, $sourceEnd - $sourceStart);
            if ($text === '') {
                return null;
            }
            $outputEnd = $cursor + strlen($text);
            $ranges[] = [
                'sourceOccurrenceId' => $id,
                'sourceStart' => $sourceStart,
                'sourceEnd' => $sourceEnd,
                'outputStart' => $cursor,
                'outputEnd' => $outputEnd,
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $coverageById[$id][] = ['start' => $sourceStart, 'end' => $sourceEnd];
            $projection .= $text;
            $cursor = $outputEnd;
        }

        foreach ($recordsById as $id => $record) {
            $coverage = $coverageById[$id] ?? [];
            usort($coverage, static fn (array $left, array $right): int =>
                ($left['start'] <=> $right['start']) ?: ($left['end'] <=> $right['end'])
            );
            $sourceCursor = 0;
            foreach ($coverage as $range) {
                if ($range['start'] !== $sourceCursor || $range['end'] <= $range['start']) {
                    return null;
                }
                $sourceCursor = $range['end'];
            }
            if ($sourceCursor !== strlen((string) ($record['significant'] ?? ''))) {
                return null;
            }
        }

        return hash_equals((string) ($unit['projection'] ?? ''), $projection)
            && $cursor === (int) ($unit['end'] ?? 0)
                ? $ranges
                : null;
    }

    private static function isWholeUtf8CodePointRange(string $text, int $start, int $end): bool
    {
        $length = strlen($text);
        if ($start < 0
            || $end <= $start
            || $end > $length
            || preg_match('//u', $text) !== 1) {
            return false;
        }

        // UTF-8 continuation bytes always begin with 10xxxxxx. For valid
        // UTF-8, every other byte (plus the terminal offset) is a code-point
        // boundary. Rejecting interior offsets prevents one emitted character
        // from being assembled out of byte fragments belonging to different
        // source occurrences.
        $startsOnBoundary = $start === 0 || (ord($text[$start]) & 0xC0) !== 0x80;
        $endsOnBoundary = $end === $length || (ord($text[$end]) & 0xC0) !== 0x80;

        return $startsOnBoundary && $endsOnBoundary;
    }

    /**
     * A producer which retained occurrence-local geometry may declare the
     * exact emitted occurrence sequence. Validate both the source-set
     * permutation and the byte-for-byte projection before using it; this is
     * what makes adjacent numeric cells and repeated spellings bindable
     * without guessing token boundaries.
     *
     * @param array<string,mixed> $unit
     * @param list<string> $emittedSourceIds
     * @return list<array<string,mixed>>|null
     */
    private static function declaredScopeOccurrenceRanges(
        array $unit,
        array $emittedSourceIds
    ): ?array {
        $recordsById = [];
        foreach ($unit['records'] as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '' || isset($recordsById[$id])) {
                return null;
            }
            $recordsById[$id] = $record;
        }
        $declared = array_keys($recordsById);
        $emitted = $emittedSourceIds;
        $declaredSorted = $declared;
        $emittedSorted = $emitted;
        sort($declaredSorted, SORT_STRING);
        sort($emittedSorted, SORT_STRING);
        if ($emittedSorted !== $declaredSorted) {
            return null;
        }

        $cursor = (int) ($unit['start'] ?? 0);
        $ranges = [];
        $projection = '';
        foreach ($emitted as $id) {
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record)
                ? (string) ($record['significant'] ?? '')
                : '';
            if ($significant === '') {
                return null;
            }
            $end = $cursor + strlen($significant);
            $ranges[] = [
                'sourceOccurrenceId' => $id,
                'sourceStart' => 0,
                'sourceEnd' => strlen($significant),
                'outputStart' => $cursor,
                'outputEnd' => $end,
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $projection .= $significant;
            $cursor = $end;
        }

        return hash_equals((string) ($unit['projection'] ?? ''), $projection)
            && $cursor === (int) ($unit['end'] ?? 0)
                ? $ranges
                : null;
    }

    /** @return list<array{text:string,start:int,end:int}> */
    private static function sourceBindingTokens(string $significant): array
    {
        if ($significant === '') {
            return [];
        }
        $matched = preg_match_all(
            '/[\p{L}\p{M}\p{N}]+|./us',
            $significant,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if ($matched === false || $matched === 0) {
            return [];
        }
        $tokens = [];
        $covered = '';
        foreach ($matches[0] as [$token, $offset]) {
            $token = (string) $token;
            $tokens[] = [
                'text' => $token,
                'start' => (int) $offset,
                'end' => (int) $offset + strlen($token),
            ];
            $covered .= $token;
        }

        return hash_equals($significant, $covered) ? $tokens : [];
    }
}
