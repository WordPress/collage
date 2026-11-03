<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Validate an exact PDF source/output binding without constructing the
 * provenance-decorated AST or the complete disposition ledger.
 *
 * The page-candidate path deliberately lives in this small class so
 * speculative validation never compiles PdfSourceDispositionLedger. Semantic
 * list targets are checked against exact source/output spans without building
 * the provenance-decorated AST.
 */
final class PdfSourceBindingValidator
{
    private const ORDER_MATCH_CANDIDATE_LIMIT = 256;
    private const SOURCE_BINDING_STATE_LIMIT = 100000;

    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @var array<string,true> */
    private const ALLOWED_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
        'unresolved' => true,
    ];

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array{complete:bool,failureReason:?string}
     */
    public static function validateSourceLineItemsToOutput(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = [],
        array $bindingContext = []
    ): array {
        $records = self::sourceBindingRecords($sourceLineItems, $explicitDispositions);
        $projection = self::sourceBindingProjection($records);
        if ($projection['failureReason'] !== null) {
            return [
                'complete' => false,
                'failureReason' => $projection['failureReason'],
            ];
        }

        $output = self::sourceBindingOutputText($blocks);
        if (hash_equals($projection['text'], $output)) {
            $ranges = self::directSourceBindingRanges($projection['units']);
            if ($ranges === null) {
                return [
                    'complete' => false,
                    'failureReason' => 'authorized-order-scope-has-ambiguous-output-mapping',
                ];
            }
        } else {
            $ranges = self::uniqueInterleavedSourceBindingRanges(
                $projection['units'],
                $output
            );
            if ($ranges === null) {
                return [
                    'complete' => false,
                    'failureReason' => 'projected-source-stream-does-not-equal-final-output',
                ];
            }
        }
        // Exact ranges own every value needed by the remaining coverage and
        // semantic checks. Release the much larger per-unit projection packet
        // before semantic target planning so the two proof graphs never
        // overlap at the PDF candidate-validation peak.
        unset($projection);

        if (!self::sourceBindingRangesCoverOutput($ranges, strlen($output))) {
            return [
                'complete' => false,
                'failureReason' => 'final-output-node-spans-could-not-be-bound-exactly',
            ];
        }

        foreach ($records as $record) {
            if (!is_array($record['semanticStructureProof'] ?? null)) {
                continue;
            }
            $semanticFailureReason =
                PdfSourceSemanticBindingValidator::sourceBindingSemanticStructureFailureReason(
                    $records,
                    $blocks,
                    $ranges,
                    $bindingContext
                );
            if ($semanticFailureReason !== null) {
                return ['complete' => false, 'failureReason' => $semanticFailureReason];
            }
            break;
        }

        return ['complete' => true, 'failureReason' => null];
    }

    /** @param array<string,string> $sourceOccurrenceProjections */
    public static function hasUniqueTokenInterleavingOrderProof(
        array $sourceOccurrenceProjections,
        string $emittedProjection
    ): bool {
        return PdfSourceTokenInterleavingValidator::hasUniqueTokenInterleavingOrderProof(
            $sourceOccurrenceProjections,
            $emittedProjection
        );
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return list<array<string,mixed>>
     */
    private static function sourceBindingRecords(
        array $sourceLineItems,
        array $explicitDispositions
    ): array {
        $records = [];
        $seen = [];
        $normalizedProofs = [];
        foreach ($sourceLineItems as $index => $item) {
            $record = is_array($item) ? $item : ['text' => (string) $item];
            $sourceText = is_string($record['text'] ?? null) ? $record['text'] : '';
            $sourceSignificant = self::significantText($sourceText);
            if ($sourceSignificant === '') {
                continue;
            }
            $id = self::sourceOccurrenceId($record, $index, trim($sourceText));
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate PDF source occurrence ID ' . $id . '.');
            }
            $seen[$id] = true;
            $explicit = self::normalizedExplicitDisposition(
                $explicitDispositions[$id] ?? null,
                $id
            );
            if (is_array($explicit) && is_array($explicit['orderProof'] ?? null)) {
                $scopeId = (string) ($explicit['orderProof']['scopeId'] ?? '');
                if (isset($normalizedProofs[$scopeId])
                    && $normalizedProofs[$scopeId] === $explicit['orderProof']) {
                    $explicit['orderProof'] = $normalizedProofs[$scopeId];
                } elseif (!isset($normalizedProofs[$scopeId])) {
                    $normalizedProofs[$scopeId] = $explicit['orderProof'];
                }
            }
            $disposition = $explicit['disposition'] ?? 'emitted';
            $projectionText = isset(self::OUTPUT_DISPOSITIONS[$disposition])
                ? ($explicit['textProjection'] ?? $sourceText)
                : '';
            $records[] = [
                'id' => $id,
                'sourceIndex' => $index,
                'page' => max(1, (int) ($record['page'] ?? 1)),
                'stream' => isset($record['stream']) && is_numeric($record['stream'])
                    ? (int) $record['stream']
                    : null,
                'disposition' => $disposition,
                'sourceText' => $sourceText,
                'projectionText' => $projectionText,
                'textProjection' => $explicit['textProjection'] ?? null,
                'significant' => self::significantText($projectionText),
                'sourceSignificant' => $sourceSignificant,
                'evidence' => $explicit['evidence'] ?? [],
                'allowOrderChange' => ($explicit['allowOrderChange'] ?? false) === true,
                'orderProof' => $explicit['orderProof'] ?? null,
                'semanticStructureProof' => $explicit['semanticStructureProof'] ?? null,
            ];
        }

        return $records;
    }

    /** @return array<string,mixed>|null */
    private static function normalizedExplicitDisposition(
        array|string|null $value,
        string $id
    ): ?array {
        if ($value === null) {
            return null;
        }
        $record = is_string($value) ? ['disposition' => $value] : $value;
        $disposition = is_string($record['disposition'] ?? null)
            ? $record['disposition']
            : '';
        if (!isset(self::ALLOWED_DISPOSITIONS[$disposition])) {
            throw new InvalidArgumentException('Unknown PDF source disposition for ' . $id . '.');
        }
        self::normalizedSourceMapping($record['sourceMapping'] ?? null, $id);
        $semanticStructureProof = $record['semanticStructureProof'] ?? null;

        return [
            'disposition' => $disposition,
            'evidence' => is_array($record['evidence'] ?? null) ? $record['evidence'] : [],
            'textProjection' => is_string($record['textProjection'] ?? null)
                ? $record['textProjection']
                : null,
            'allowOrderChange' => ($record['allowOrderChange'] ?? false) === true,
            'orderProof' => self::normalizedExplicitOrderProof(
                $record['orderProof'] ?? null,
                $id
            ),
            'semanticStructureProof' => $semanticStructureProof === null
                ? null
                : PdfSourceSemanticBindingValidator::normalizedSemanticStructureProof(
                    $semanticStructureProof,
                    $id
                ),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function normalizedSourceMapping(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid source mapping.');
        }
        $status = is_string($value['status'] ?? null) ? $value['status'] : '';
        if (!in_array($status, ['output', 'disposition', 'unresolved'], true)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid mapping status.');
        }
        $nodeIds = self::normalizedDestinationIds($value['destinationNodeIds'] ?? [], $id);
        $inlineIds = self::normalizedDestinationIds($value['destinationInlineIds'] ?? [], $id);
        if ($status === 'output' && $nodeIds === []) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has no mapped output node.');
        }
        if ($status !== 'output' && ($nodeIds !== [] || $inlineIds !== [])) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' maps a disposition to output nodes.');
        }

        return $value;
    }

    /** @return list<string> */
    private static function normalizedDestinationIds(mixed $value, string $sourceId): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
        }
        $ids = [];
        foreach ($value as $id) {
            if (!is_string($id) || $id === '' || isset($ids[$id])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
            }
            $ids[$id] = true;
        }

        return array_keys($ids);
    }

    /**
     * @return array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string,sourcePages?:list<int>,emittedSourceOccurrenceIds?:list<string>,emittedSourceRanges?:list<array{sourceOccurrenceId:string,sourceStart:int,sourceEnd:int}>}|null
     */
    private static function normalizedExplicitOrderProof(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order proof.');
        }
        $scopeId = is_string($value['scopeId'] ?? null) ? trim($value['scopeId']) : '';
        $projection = is_string($value['emittedTextProjection'] ?? null)
            ? $value['emittedTextProjection']
            : null;
        $sourceIds = [];
        foreach (is_array($value['sourceOccurrenceIds'] ?? null)
            ? $value['sourceOccurrenceIds']
            : [] as $sourceId) {
            if (!is_string($sourceId) || $sourceId === '' || isset($sourceIds[$sourceId])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order-proof source set.');
            }
            $sourceIds[$sourceId] = true;
        }
        if ($scopeId === '' || $projection === null || $sourceIds === [] || !isset($sourceIds[$id])) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an incomplete order proof.');
        }

        $sourcePages = null;
        if (array_key_exists('sourcePages', $value)) {
            $sourcePages = [];
            foreach (is_array($value['sourcePages'] ?? null) ? $value['sourcePages'] : [] as $page) {
                if (!is_int($page) || $page < 1 || isset($sourcePages[$page])) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid cross-page order-proof page set.'
                    );
                }
                $sourcePages[$page] = true;
            }
            $declaredPages = array_keys($sourcePages);
            $sortedPages = $declaredPages;
            sort($sortedPages, SORT_NUMERIC);
            if (count($declaredPages) < 2 || $declaredPages !== $sortedPages) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has a non-canonical cross-page order-proof page set.'
                );
            }
        }

        $emittedSourceIds = null;
        if (array_key_exists('emittedSourceOccurrenceIds', $value)) {
            $emittedSourceIds = [];
            foreach (is_array($value['emittedSourceOccurrenceIds'] ?? null)
                ? $value['emittedSourceOccurrenceIds']
                : [] as $sourceId) {
                if (!is_string($sourceId) || $sourceId === '' || isset($emittedSourceIds[$sourceId])) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source set.'
                    );
                }
                $emittedSourceIds[$sourceId] = true;
            }
            $declared = array_keys($sourceIds);
            $emitted = array_keys($emittedSourceIds);
            $declaredSorted = $declared;
            $emittedSorted = $emitted;
            sort($declaredSorted, SORT_STRING);
            sort($emittedSorted, SORT_STRING);
            if ($emitted === [] || $emittedSorted !== $declaredSorted) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has a non-conserving emitted order-proof source set.'
                );
            }
        }

        $emittedSourceRanges = null;
        if (array_key_exists('emittedSourceRanges', $value)) {
            if (is_array($emittedSourceIds)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has competing emitted order-proof mappings.'
                );
            }
            $emittedSourceRanges = [];
            foreach (is_array($value['emittedSourceRanges'] ?? null)
                ? $value['emittedSourceRanges']
                : [] as $range) {
                $sourceId = is_array($range) && is_string($range['sourceOccurrenceId'] ?? null)
                    ? $range['sourceOccurrenceId']
                    : '';
                $sourceStart = is_array($range) && is_int($range['sourceStart'] ?? null)
                    ? $range['sourceStart']
                    : null;
                $sourceEnd = is_array($range) && is_int($range['sourceEnd'] ?? null)
                    ? $range['sourceEnd']
                    : null;
                if ($sourceId === ''
                    || !isset($sourceIds[$sourceId])
                    || $sourceStart === null
                    || $sourceEnd === null
                    || $sourceStart < 0
                    || $sourceEnd <= $sourceStart) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source range.'
                    );
                }
                $emittedSourceRanges[] = [
                    'sourceOccurrenceId' => $sourceId,
                    'sourceStart' => $sourceStart,
                    'sourceEnd' => $sourceEnd,
                ];
            }
            if ($emittedSourceRanges === []) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has an empty emitted order-proof source range set.'
                );
            }
        }

        $proof = [
            'scopeId' => $scopeId,
            'sourceOccurrenceIds' => array_keys($sourceIds),
            'emittedTextProjection' => $projection,
        ];
        if (is_array($sourcePages)) {
            $proof['sourcePages'] = array_keys($sourcePages);
        }
        if (is_array($emittedSourceIds)) {
            $proof['emittedSourceOccurrenceIds'] = array_keys($emittedSourceIds);
        }
        if (is_array($emittedSourceRanges)) {
            $proof['emittedSourceRanges'] = $emittedSourceRanges;
        }

        return $proof;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return array{text:string,units:list<array<string,mixed>>,failureReason:?string}
     */
    private static function sourceBindingProjection(array $records): array
    {
        $contributors = [];
        foreach ($records as $recordIndex => $record) {
            if (($record['disposition'] ?? null) === 'unresolved') {
                return ['text' => '', 'units' => [], 'failureReason' => 'source-occurrence-is-unresolved'];
            }
            $outputDisposition = isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? '']);
            $semanticStructureProof = $record['semanticStructureProof'] ?? null;
            if (($outputDisposition && (string) ($record['significant'] ?? '') === '')
                || is_array($semanticStructureProof)) {
                if (!is_array($semanticStructureProof)) {
                    return [
                        'text' => '',
                        'units' => [],
                        'failureReason' =>
                            'empty-output-projection-has-no-exact-structural-target',
                    ];
                }
                $failureReason =
                    PdfSourceSemanticBindingValidator::sourceBindingStructuralMarkerRecordFailureReason(
                    $record,
                    $records[$recordIndex + 1] ?? null
                );
                if ($failureReason !== null) {
                    return ['text' => '', 'units' => [], 'failureReason' => $failureReason];
                }
            }
            if ($outputDisposition && (string) ($record['significant'] ?? '') !== '') {
                $contributors[] = $record;
            }
        }

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
            $scopeProjection = self::significantText($proof['emittedTextProjection']);
            $sourceCharacters = [];
            $sourceBytes = 0;
            foreach ($scopeRecords as $scopeRecord) {
                $sourceBytes += self::updateSignificantCharacterInventory(
                    $sourceCharacters,
                    (string) $scopeRecord['significant']
                );
            }
            $emittedCharacters = [];
            $emittedBytes = self::updateSignificantCharacterInventory(
                $emittedCharacters,
                $scopeProjection
            );
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

    /** @param list<AstNode> $blocks */
    private static function sourceBindingOutputText(array $blocks): string
    {
        $text = '';
        foreach ($blocks as $block) {
            self::appendSourceBindingNodeText($block, $text);
        }

        return $text;
    }

    private static function appendSourceBindingNodeText(AstNode $node, string &$text): void
    {
        if ($node->type === 'text' || $node->type === 'code_block') {
            $text .= self::significantText((string) $node->attr('text', ''));
            return;
        }
        foreach ($node->children() as $child) {
            self::appendSourceBindingNodeText($child, $text);
        }
    }

    /** @param list<array<string,mixed>> $units @return list<array<string,mixed>>|null */
    private static function directSourceBindingRanges(array $units): ?array
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

    /** @param list<array<string,mixed>> $units @return list<array<string,mixed>>|null */
    private static function uniqueInterleavedSourceBindingRanges(
        array $units,
        string $output
    ): ?array {
        $scopeIndexes = [];
        $exactTexts = [];
        $exactUnitIndexes = [];
        foreach ($units as $unitIndex => $unit) {
            if (($unit['scopeId'] ?? null) !== null) {
                $scopeIndexes[] = $unitIndex;
            } else {
                $projection = (string) ($unit['projection'] ?? '');
                if ($projection === '') {
                    return null;
                }
                $exactTexts[] = $projection;
                $exactUnitIndexes[] = $unitIndex;
            }
        }
        if (count($scopeIndexes) !== 1 || $exactTexts === []) {
            return null;
        }

        $scopeUnit = $units[$scopeIndexes[0]];
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
        $localRanges = self::uniqueScopeInterleavingRanges($scopeUnit);
        if ($localRanges === null) {
            return null;
        }
        foreach ($localRanges as $localRange) {
            $translated = null;
            foreach ($layout['scopeSpans'] as $span) {
                if ($localRange['outputStart'] < $span['projectionStart']
                    || $localRange['outputEnd'] > $span['projectionEnd']) {
                    continue;
                }
                $translated = $localRange;
                $translated['outputStart'] = $span['outputStart']
                    + $localRange['outputStart'] - $span['projectionStart'];
                $translated['outputEnd'] = $span['outputStart']
                    + $localRange['outputEnd'] - $span['projectionStart'];
                break;
            }
            if ($translated === null) {
                return null;
            }
            $ranges[] = $translated;
        }

        return $ranges;
    }

    /** @param list<string> $exactTexts */
    private static function uniqueInterleavedExactProjectionLayout(
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
                $solution = ['exactRanges' => $exactRanges, 'scopeSpans' => $scopeSpans];
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
                    $nextRanges = $exactRanges;
                    $nextRanges[] = ['start' => $found, 'end' => $found + strlen($exact)];
                    $nextSpans = $scopeSpans;
                    if ($gapLength > 0) {
                        $nextSpans[] = [
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
                        $nextRanges,
                        $nextSpans
                    );
                }
                $searchOffset = $found + 1;
            }
        };
        $search(0, 0, 0, [], []);

        return !$limitExceeded && $solutionCount === 1 ? $solution : null;
    }

    /** @param array<string,mixed> $unit @return list<array<string,mixed>>|null */
    private static function uniqueScopeInterleavingRanges(array $unit): ?array
    {
        $declaredRanges = $unit['records'][0]['orderProof']['emittedSourceRanges'] ?? null;
        if (is_array($declaredRanges)) {
            return self::declaredScopeSourceRanges($unit, $declaredRanges);
        }
        $declaredIds = $unit['records'][0]['orderProof']['emittedSourceOccurrenceIds'] ?? null;
        if (is_array($declaredIds)) {
            return self::declaredScopeOccurrenceRanges($unit, $declaredIds);
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
                    || $sourceTokens[$sourceIndex][$position]['text']
                        !== $outputTokens[$outputIndex]['text']) {
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
        foreach ($outputTokens as $outputIndex => $outputToken) {
            $key = implode(',', $positions);
            $sourceIndex = $choices[$key] ?? null;
            if (!is_int($sourceIndex)) {
                return null;
            }
            $sourceToken = $sourceTokens[$sourceIndex][$positions[$sourceIndex]];
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

    /** @param list<array<string,mixed>> $declaredRanges */
    private static function declaredScopeSourceRanges(array $unit, array $declaredRanges): ?array
    {
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
        foreach ($declaredRanges as $range) {
            if (!is_array($range)) {
                return null;
            }
            $id = is_string($range['sourceOccurrenceId'] ?? null)
                ? $range['sourceOccurrenceId']
                : '';
            $start = is_int($range['sourceStart'] ?? null) ? $range['sourceStart'] : null;
            $end = is_int($range['sourceEnd'] ?? null) ? $range['sourceEnd'] : null;
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record) ? (string) ($record['significant'] ?? '') : '';
            if ($significant === ''
                || $start === null
                || $end === null
                || $start < 0
                || $end <= $start
                || $end > strlen($significant)
                || !self::isWholeUtf8CodePointRange($significant, $start, $end)) {
                return null;
            }
            $text = substr($significant, $start, $end - $start);
            $outputEnd = $cursor + strlen($text);
            $ranges[] = [
                'sourceOccurrenceId' => $id,
                'sourceStart' => $start,
                'sourceEnd' => $end,
                'outputStart' => $cursor,
                'outputEnd' => $outputEnd,
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $coverageById[$id][] = ['start' => $start, 'end' => $end];
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

        return ($start === 0 || (ord($text[$start]) & 0xC0) !== 0x80)
            && ($end === $length || (ord($text[$end]) & 0xC0) !== 0x80);
    }

    /** @param list<string> $emittedIds */
    private static function declaredScopeOccurrenceRanges(array $unit, array $emittedIds): ?array
    {
        $recordsById = [];
        foreach ($unit['records'] as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '' || isset($recordsById[$id])) {
                return null;
            }
            $recordsById[$id] = $record;
        }
        $declared = array_keys($recordsById);
        $declaredSorted = $declared;
        $emittedSorted = $emittedIds;
        sort($declaredSorted, SORT_STRING);
        sort($emittedSorted, SORT_STRING);
        if ($emittedSorted !== $declaredSorted) {
            return null;
        }

        $cursor = (int) ($unit['start'] ?? 0);
        $ranges = [];
        $projection = '';
        foreach ($emittedIds as $id) {
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record) ? (string) ($record['significant'] ?? '') : '';
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

    /** @param list<array<string,mixed>> $ranges */
    private static function sourceBindingRangesCoverOutput(array $ranges, int $outputLength): bool
    {
        usort($ranges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $cursor = 0;
        foreach ($ranges as $range) {
            if ((int) $range['outputStart'] !== $cursor
                || (int) $range['outputEnd'] <= (int) $range['outputStart']) {
                return false;
            }
            $cursor = (int) $range['outputEnd'];
        }

        return $cursor === $outputLength;
    }

    /** @param array<string,mixed> $record */
    private static function sourceOccurrenceId(array $record, int $index, string $text): string
    {
        if (is_string($record['id'] ?? null) && $record['id'] !== '') {
            return $record['id'];
        }
        $identity = json_encode([
            'page' => max(1, (int) ($record['page'] ?? 1)),
            'stream' => max(1, (int) ($record['stream'] ?? $index + 1)),
            'index' => $index,
            'text' => $text,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'line-' . substr(hash('sha256', is_string($identity) ? $identity : $text), 0, 24);
    }

    private static function significantText(string $chunk): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($chunk, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $chunk = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $chunk) ?? $chunk;
    }

    /** @param array<string,int> $characters */
    private static function updateSignificantCharacterInventory(
        array &$characters,
        string $chunk
    ): int {
        $significant = self::significantText($chunk);
        $offset = 0;
        $length = strlen($significant);
        while ($offset < $length) {
            $found = preg_match('/./us', $significant, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($found !== 1) {
                $character = $significant[$offset];
                $characters[$character] = ($characters[$character] ?? 0) + 1;
                $offset++;
                continue;
            }
            $character = (string) $match[0][0];
            $byteOffset = (int) $match[0][1];
            $characters[$character] = ($characters[$character] ?? 0) + 1;
            $offset = $byteOffset + strlen($character);
        }

        return $length;
    }
}
