<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Build the final public PDF source-occurrence audit after exact decoration.
 *
 * This proof-rich audit is compiled at the positioned-run spill trough so the
 * small binding facade can load later without expanding PHP's allocator.
 */
final class PdfSourceOccurrenceLedger
{
    private const SAMPLE_LIMIT = 32;
    private const SEMANTIC_STRUCTURE_MAPPING_MODE = 'exact-semantic-list-marker';

    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @var array<string,true> */
    private const EXPLICIT_REASON_REQUIRED = [
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
    ];

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string, array{disposition:string,reason?:string,evidence?:array<string,mixed>,textProjection?:string,allowOrderChange?:bool,orderProof?:array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItems(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = [],
        array $bindingContext = []
    ): array {
        return self::fromSourceLineItemsInPlace(
            $sourceLineItems,
            $blocks,
            $explicitDispositions,
            $bindingContext
        );
    }

    /**
     * Memory-bounded ledger construction for an owner which no longer needs
     * the complete bound-disposition map. The ordinary public method above
     * remains non-mutating.
     *
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string, array{disposition:string,reason?:string,evidence?:array<string,mixed>,textProjection?:string,allowOrderChange?:bool,orderProof?:array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItemsInPlace(
        array $sourceLineItems,
        array $blocks,
        array &$explicitDispositions,
        array $bindingContext = []
    ): array {
        // Establish the lightweight ordered-character summary first. When
        // exact source edges cover the complete output, token boundaries are
        // deliberately governed by those edges and the much larger token and
        // character inventories would immediately be discarded below.
        $emittedSignificant = PdfSourceDispositionLedger::significantCharacterSummary(PdfSourceDispositionLedger::textChunksFromNodes($blocks));
        $orderProofRequested =
            PdfSourceOrderProofLedger::hasRequestedOrderChange($explicitDispositions);
        $bindingRecords = PdfSourceDispositionLedger::sourceBindingRecordsInPlace(
            $sourceLineItems,
            $explicitDispositions
        );
        // Every remaining audit reads the validated normalized records. Drop
        // the raw string-keyed disposition graph before constructing the
        // public source-edge ledger; the caller explicitly surrendered it.
        $explicitDispositions = [];
        $structureSensitiveIds = [];
        foreach ($bindingRecords as $bindingRecord) {
            if (is_array($bindingRecord['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$bindingRecord['disposition'] ?? ''])
                    && (string) ($bindingRecord['significant'] ?? '') === '')) {
                $structureSensitiveIds[(string) $bindingRecord['id']] = true;
            }
        }
        $structuralValidation = self::validatedExplicitSemanticStructureMappings(
            $bindingRecords,
            $blocks,
            $bindingContext
        );
        $validStructuralIds = $structuralValidation['sourceOccurrenceIds'];
        $exactMappedOutputCoverage = self::hasExactMappedOutputCoverage(
            $bindingRecords,
            $blocks,
            $validStructuralIds,
            $emittedSignificant['bytes']
        );
        if ($exactMappedOutputCoverage) {
            // The occurrence edges cover every emitted significant byte and
            // every projected source byte exactly. Token boundaries may still
            // move when adjacent PDF fragments become one word, so the edge
            // graph—not a second spelling inventory—is the correct claim on
            // the emitted output in this path.
            $tokenCounts = [];
            $characterCounts = [];
        } else {
            // Walk the AST independently instead of retaining a second copy
            // of every emitted text chunk. Large PDFs commonly contain tens
            // of thousands of nodes.
            $emitted = self::inventoryFromChunks(PdfSourceDispositionLedger::textChunksFromNodes($blocks));
            $tokenCounts = $emitted['tokens'];
            $characterCounts = $emitted['characters'];
            unset($emitted);
        }
        $sourceSignificantDigest = hash_init('sha256');
        $sourceSignificantCharacterBytes = 0;
        $counts = [];
        $pageCounts = [];
        $unresolvedSample = [];
        $suppressedSample = [];
        $occurrenceCount = 0;
        $evidencedOrderChangeOccurrenceCount = 0;
        $rejectedOrderChangeOccurrenceCount = 0;
        $evidencedOrderChangeScopeKeys = [];
        $orderProofSegments = [];
        $currentOrderProofSegment = null;
        $mappedScopeInventoryStatus = [];
        $digest = hash_init('sha256');

        foreach ($bindingRecords as $recordIndex => $record) {
            $text = trim((string) ($record['sourceText'] ?? ''));
            $page = max(1, (int) ($record['page'] ?? 1));
            $id = (string) ($record['id'] ?? '');
            $explicit = ($record['hasExplicitDisposition'] ?? false) === true
                ? $record
                : null;
            if ($explicit === null) {
                $inventory = self::inventoryFromChunks([$text]);
                $matched = PdfSourceDispositionLedger::canConsume($tokenCounts, $inventory['tokens'])
                    && PdfSourceDispositionLedger::canConsume($characterCounts, $inventory['characters']);
                $disposition = $matched ? 'emitted' : 'unresolved';
                $reason = $matched
                    ? 'The emitted AST contains one unclaimed character-equivalent occurrence.'
                    : 'No unclaimed character-equivalent emitted occurrence or explicit disposition was available.';
                $evidence = ['method' => 'conservative-inventory-consumption'];
                if ($matched) {
                    PdfSourceDispositionLedger::consume($tokenCounts, $inventory['tokens']);
                    PdfSourceDispositionLedger::consume($characterCounts, $inventory['characters']);
                }
            } else {
                $disposition = $explicit['disposition'];
                $reason = $explicit['reason'];
                $evidence = $explicit['evidence'];
                $accountingText = $explicit['textProjection'] ?? $text;
                if (isset(self::EXPLICIT_REASON_REQUIRED[$disposition]) && $reason === '') {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' requires evidence for disposition ' . $disposition . '.'
                    );
                }
                if (isset($structureSensitiveIds[$id]) && !isset($validStructuralIds[$id])) {
                    $requestedDisposition = $disposition;
                    $disposition = 'unresolved';
                    $reason = 'The semantic list marker did not map to one exact live list-item structure.';
                    $evidence = [
                        'method' => 'exact-semantic-list-marker-binding',
                        'failureReason' => $structuralValidation['failureReason'],
                        'requestedDisposition' => $requestedDisposition,
                    ];
                } elseif (in_array($disposition, ['emitted', 'boundary-repair', 'semantic-structure'], true)) {
                    if ($exactMappedOutputCoverage) {
                        $matchedProjection = true;
                    } else {
                        $mappedScopeId = ($explicit['allowOrderChange'] ?? false) === true
                        && is_array($explicit['orderProof'] ?? null)
                        && ($explicit['sourceMapping']['status'] ?? null) === 'output'
                        && ($explicit['sourceMapping']['mappingMode'] ?? null) === 'exact-authorized-scope'
                            ? (string) ($explicit['orderProof']['scopeId'] ?? '')
                            : '';
                        if ($mappedScopeId !== '') {
                            if (!array_key_exists($mappedScopeId, $mappedScopeInventoryStatus)) {
                                $inventory = self::inventoryFromChunks([
                                    (string) $explicit['orderProof']['emittedTextProjection'],
                                ]);
                                $mappedScopeInventoryStatus[$mappedScopeId] = PdfSourceDispositionLedger::canConsume(
                                    $tokenCounts,
                                    $inventory['tokens']
                                ) && PdfSourceDispositionLedger::canConsume($characterCounts, $inventory['characters']);
                                if ($mappedScopeInventoryStatus[$mappedScopeId]) {
                                    PdfSourceDispositionLedger::consume($tokenCounts, $inventory['tokens']);
                                    PdfSourceDispositionLedger::consume($characterCounts, $inventory['characters']);
                                }
                            }
                            $matchedProjection = $mappedScopeInventoryStatus[$mappedScopeId];
                        } else {
                            $inventory = self::inventoryFromChunks([$accountingText]);
                            $matchedProjection = PdfSourceDispositionLedger::canConsume($tokenCounts, $inventory['tokens'])
                                && PdfSourceDispositionLedger::canConsume($characterCounts, $inventory['characters']);
                            if ($matchedProjection) {
                                PdfSourceDispositionLedger::consume($tokenCounts, $inventory['tokens']);
                                PdfSourceDispositionLedger::consume($characterCounts, $inventory['characters']);
                            }
                        }
                    }
                    if (!$matchedProjection) {
                        $disposition = 'unresolved';
                        $reason = 'The evidenced text projection could not be reconciled with one unclaimed emitted occurrence.';
                        $evidence['requestedDisposition'] = $explicit['disposition'];
                    }
                }
            }

            $occurrenceCount++;
            $orderAccountingText = null;
            if (!isset(self::EXPLICIT_REASON_REQUIRED[$disposition])) {
                $orderAccountingText = $disposition === 'unresolved' || $explicit === null
                    ? $text
                    : ($explicit['textProjection'] ?? $text);
            } elseif (in_array($disposition, ['boundary-repair', 'semantic-structure'], true)) {
                $orderAccountingText = $explicit['textProjection'] ?? $text;
            }
            if (isset($validStructuralIds[$id])) {
                // Its significant bytes are represented by list structure,
                // not by an empty textual segment which could split an exact
                // mapped order scope on either side.
                $orderAccountingText = null;
            }
            if ($orderAccountingText !== null) {
                $sourceSignificantCharacterBytes += PdfSourceDispositionLedger::updateSignificantCharacterDigest(
                    $sourceSignificantDigest,
                    $orderAccountingText
                );
                if ($orderProofRequested) {
                    $orderScope = null;
                    if ($explicit !== null
                        && $disposition !== 'unresolved'
                        && $explicit['allowOrderChange']) {
                        $orderScope = PdfSourceOrderProofLedger::localOrderChangeScope(
                            $explicit,
                            $id,
                            $page
                        );
                        if ($orderScope === null) {
                            $rejectedOrderChangeOccurrenceCount++;
                        } else {
                            $evidencedOrderChangeOccurrenceCount++;
                            $evidencedOrderChangeScopeKeys[$orderScope['key']] = true;
                        }
                    }
                    PdfSourceOrderProofLedger::appendOrderProofSegment(
                        $orderProofSegments,
                        $currentOrderProofSegment,
                        $orderAccountingText,
                        $id,
                        $page,
                        $orderScope
                    );
                }
            }
            $counts[$disposition] = ($counts[$disposition] ?? 0) + 1;
            $pageCounts[$page][$disposition] = ($pageCounts[$page][$disposition] ?? 0) + 1;
            hash_update($digest, $id . "\0" . $disposition . "\0" . $reason . "\n");
            $collectUnresolvedSample = $disposition === 'unresolved'
                && count($unresolvedSample) < self::SAMPLE_LIMIT;
            $collectSuppressedSample = !$collectUnresolvedSample
                && isset(self::EXPLICIT_REASON_REQUIRED[$disposition])
                && count($suppressedSample) < self::SAMPLE_LIMIT;
            if ($collectUnresolvedSample || $collectSuppressedSample) {
                $sample = [
                    'id' => $id,
                    'page' => $page,
                    'text' => self::sampleText($text),
                    'disposition' => $disposition,
                    'reason' => $reason,
                ];
                if ($evidence !== []) {
                    $sample['evidence'] = $evidence;
                }
                if ($collectUnresolvedSample) {
                    $unresolvedSample[] = $sample;
                } else {
                    $suppressedSample[] = $sample;
                }
            }
            // No later audit reads the proof-rich normalized record. Replace
            // it immediately with the four values needed to emit its public
            // source edge, so thousands of source strings, proof wrappers,
            // and evidence arrays do not survive until the final edge pass.
            $mapping = is_array($record['sourceMapping'] ?? null)
                ? $record['sourceMapping']
                : null;
            $bindingRecords[$recordIndex] = [
                $id,
                $page,
                $disposition,
                is_array($mapping) ? ($mapping['status'] ?? 'unresolved') : 'unresolved',
                is_array($mapping) ? ($mapping['mappingMode'] ?? 'unmapped') : 'unmapped',
                is_array($mapping) && is_array($mapping['destinationNodeIds'] ?? null)
                    ? $mapping['destinationNodeIds']
                    : [],
                is_array($mapping) && is_array($mapping['destinationInlineIds'] ?? null)
                    ? $mapping['destinationInlineIds']
                    : [],
                is_array($mapping) ? ($mapping['scopeId'] ?? null) : null,
            ];
            unset(
                $record,
                $explicit,
                $inventory,
                $sample,
                $evidence,
                $text,
                $accountingText,
                $orderAccountingText,
                $mapping
            );
        }

        ksort($counts);
        ksort($pageCounts, SORT_NUMERIC);
        foreach ($pageCounts as &$pageSummary) {
            ksort($pageSummary);
        }
        unset($pageSummary);
        if ($orderProofRequested) {
            PdfSourceOrderProofLedger::flushOrderProofSegment(
                $orderProofSegments,
                $currentOrderProofSegment
            );
        }
        $unresolvedCount = (int) ($counts['unresolved'] ?? 0);
        $sourceSignificantCharacterDigest = hash_final($sourceSignificantDigest);
        $emittedSignificantCharacterDigest = $emittedSignificant['digest'];
        $exactOrderedSignificantCharactersPreserved = $sourceSignificantCharacterBytes === $emittedSignificant['bytes']
            && hash_equals($sourceSignificantCharacterDigest, $emittedSignificantCharacterDigest);
        $remainingTokenCount = array_sum($tokenCounts);
        $remainingCharacterCount = array_sum($characterCounts);
        $localOrderProof = $orderProofRequested && !$exactOrderedSignificantCharactersPreserved
            ? PdfSourceOrderProofLedger::proveLocalOrderSegments(
                $orderProofSegments,
                $blocks
            )
            : [
                'preserved' => false,
                'strength' => $exactOrderedSignificantCharactersPreserved ? 'source-order-exact' : 'not-requested',
                'failureReason' => null,
            ];
        $evidencedOrderChangePreserved = !$exactOrderedSignificantCharactersPreserved
            && $unresolvedCount === 0
            && $evidencedOrderChangeOccurrenceCount > 0
            && $rejectedOrderChangeOccurrenceCount === 0
            && $remainingTokenCount === 0
            && $remainingCharacterCount === 0
            && $sourceSignificantCharacterBytes === $emittedSignificant['bytes']
            && $localOrderProof['preserved'];
        $orderedSignificantCharactersPreserved = $exactOrderedSignificantCharactersPreserved
            || $evidencedOrderChangePreserved;

        // Public source edges repeat each normalized occurrence's destination
        // vectors. Building them during accounting retains the complete,
        // proof-rich binding-record inventory beside a steadily growing second
        // graph. Finish every check which needs those records first, then
        // replace them one at a time with their public edge representation.
        // This preserves edge order and identity while bounding the overlap on
        // dense PDFs with tens of thousands of source occurrences.
        unset(
            $structureSensitiveIds,
            $structuralValidation,
            $validStructuralIds,
            $orderProofSegments,
            $currentOrderProofSegment,
            $mappedScopeInventoryStatus,
            $tokenCounts,
            $characterCounts,
            $record,
            $explicit,
            $inventory,
            $sample,
            $evidence
        );
        $sourceEdges = $bindingRecords;
        unset($bindingRecords);
        $sourceEdgeMappingComplete = true;
        $sourceEdgeCount = count($sourceEdges);
        for ($recordIndex = 0; $recordIndex < $sourceEdgeCount; $recordIndex++) {
            $sourceId = (string) ($sourceEdges[$recordIndex][0] ?? '');
            $page = max(1, (int) ($sourceEdges[$recordIndex][1] ?? 1));
            $disposition = is_string($sourceEdges[$recordIndex][2] ?? null)
                ? $sourceEdges[$recordIndex][2]
                : 'unresolved';
            $mappingStatus = is_string($sourceEdges[$recordIndex][3] ?? null)
                ? $sourceEdges[$recordIndex][3]
                : 'unresolved';
            $mappingMode = $sourceEdges[$recordIndex][4] ?? 'unmapped';
            $nodeIds = is_array($sourceEdges[$recordIndex][5] ?? null)
                ? $sourceEdges[$recordIndex][5]
                : [];
            $inlineIds = is_array($sourceEdges[$recordIndex][6] ?? null)
                ? $sourceEdges[$recordIndex][6]
                : [];
            $orderScopeId = $sourceEdges[$recordIndex][7] ?? null;
            // The compact public edge needs only these scalars and the shared
            // mapping vectors. Release the proof-rich source record before
            // allocating its replacement and hashing its identity.
            $sourceEdges[$recordIndex] = null;
            $sourceEdge = PdfSourceEdgeLedger::sourceEdgeForOccurrence(
                $sourceId,
                $page,
                $disposition,
                $mappingStatus,
                $mappingMode,
                $nodeIds,
                $inlineIds,
                $orderScopeId
            );
            $sourceEdges[$recordIndex] = $sourceEdge;
            if (($sourceEdge['target'] ?? null) === 'unresolved') {
                $sourceEdgeMappingComplete = false;
            }
            unset(
                $sourceId,
                $page,
                $disposition,
                $mappingStatus,
                $mappingMode,
                $nodeIds,
                $inlineIds,
                $orderScopeId,
                $sourceEdge
            );
        }

        return [
            'version' => 2,
            'sourceOccurrenceCount' => $occurrenceCount,
            'dispositionedOccurrenceCount' => array_sum($counts),
            'resolvedOccurrenceCount' => max(0, $occurrenceCount - $unresolvedCount),
            'unresolvedOccurrenceCount' => $unresolvedCount,
            'allOccurrencesDispositioned' => array_sum($counts) === $occurrenceCount,
            'allOccurrencesResolved' => $unresolvedCount === 0,
            'orderedSignificantCharactersPreserved' => $orderedSignificantCharactersPreserved,
            'orderedSignificantCharacterBasis' => $exactOrderedSignificantCharactersPreserved
                ? 'source-order-exact'
                : ($evidencedOrderChangePreserved ? 'evidenced-layout-reorder' : 'mismatch'),
            'evidencedOrderChangeOccurrenceCount' => $evidencedOrderChangeOccurrenceCount,
            'rejectedOrderChangeOccurrenceCount' => $rejectedOrderChangeOccurrenceCount,
            'evidencedOrderChangeScopeCount' => count($evidencedOrderChangeScopeKeys),
            'orderProofStrength' => $exactOrderedSignificantCharactersPreserved
                ? 'source-order-exact'
                : ($evidencedOrderChangePreserved ? $localOrderProof['strength'] : 'mismatch'),
            'orderProofFailureReason' => $evidencedOrderChangePreserved
                ? null
                : $localOrderProof['failureReason'],
            'unclaimedEmittedTokenCount' => $remainingTokenCount,
            'unclaimedEmittedSignificantCharacterCount' => $remainingCharacterCount,
            'sourceSignificantCharacterBytes' => $sourceSignificantCharacterBytes,
            'emittedSignificantCharacterBytes' => $emittedSignificant['bytes'],
            'sourceSignificantCharacterDigest' => $sourceSignificantCharacterDigest,
            'emittedSignificantCharacterDigest' => $emittedSignificantCharacterDigest,
            'dispositionCounts' => $counts,
            'pageDispositionCounts' => $pageCounts,
            'unresolvedOccurrenceSample' => $unresolvedSample,
            'evidencedSuppressionSample' => $suppressedSample,
            'sourceEdges' => $sourceEdges,
            'sourceEdgeCount' => count($sourceEdges),
            'sourceEdgeMappingComplete' => $sourceEdgeMappingComplete
                && count($sourceEdges) === $occurrenceCount,
            'sourceEdgeDigest' => PdfSourceEdgeLedger::sourceEdgeDigest($sourceEdges),
            'dispositionDigest' => hash_final($digest),
        ];
    }

    /**
     * Verify the public top-level edge graph before allowing it to account for
     * token-boundary repairs. Every output contributor must name real
     * destination nodes, its source byte spans must cover its exact projected
     * text once, and the aggregate edge bytes must equal the final AST text.
     *
     * @param list<array<string,mixed>> $bindingRecords
     * @param list<AstNode> $blocks
     * @param array<string,true> $validStructuralIds
     */
    private static function hasExactMappedOutputCoverage(
        array $bindingRecords,
        array $blocks,
        array $validStructuralIds,
        int $emittedSignificantBytes
    ): bool {
        // Keep coverage in packed parallel lists. A string-keyed state table
        // expands in large 80 KiB hash buckets at exactly this end-of-import
        // peak; strict lookup across a few thousand stable source IDs is both
        // exact and materially smaller.
        $coverageSourceIds = [];
        $coverageLengths = [];
        $coverageNodes = [];
        $coveragePackedRanges = [];
        $coverageSeenNodeBits = [];
        $coverageSeenNodeCounts = [];
        foreach ($bindingRecords as $record) {
            $id = (string) ($record['id'] ?? '');
            $explicit = ($record['hasExplicitDisposition'] ?? false) === true
                ? $record
                : null;
            if ($explicit === null || $explicit['disposition'] === 'unresolved') {
                return false;
            }
            if (!isset(self::OUTPUT_DISPOSITIONS[$explicit['disposition']])) {
                if (($explicit['sourceMapping']['status'] ?? null) !== 'disposition') {
                    return false;
                }
                continue;
            }
            $mapping = $explicit['sourceMapping'];
            if (!is_array($mapping)
                || ($mapping['status'] ?? null) !== 'output'
                || ($mapping['destinationNodeIds'] ?? []) === []) {
                return false;
            }
            $length = strlen((string) ($record['significant'] ?? ''));
            if (isset($validStructuralIds[$id])) {
                if ($length !== 0
                    || ($mapping['mappingMode'] ?? null) !== self::SEMANTIC_STRUCTURE_MAPPING_MODE) {
                    return false;
                }
                continue;
            }
            if (!in_array(
                $mapping['mappingMode'] ?? null,
                ['exact-sequence', 'exact-authorized-scope'],
                true
            ) || $length < 1) {
                return false;
            }
            $nodes = $mapping['destinationNodeIds'];
            $coverageSourceIds[] = $id;
            $coverageLengths[] = $length;
            $coverageNodes[] = $nodes;
            $coveragePackedRanges[] = '';
            $coverageSeenNodeBits[] = str_repeat("\0", intdiv(count($nodes) + 7, 8));
            $coverageSeenNodeCounts[] = 0;
        }
        if ($coverageSourceIds === []) {
            return $emittedSignificantBytes === 0;
        }

        $edgeBytes = 0;
        foreach ($blocks as $block) {
            $nodeId = $block->attr('sourceNodeId');
            $edges = $block->attr('sourceLineEdges', []);
            if (!is_array($edges) || $edges === []) {
                continue;
            }
            if (!is_string($nodeId) || $nodeId === '') {
                return false;
            }
            foreach ($edges as $edge) {
                if (!is_array($edge)) {
                    return false;
                }
                $sourceId = is_string($edge['sourceLineId'] ?? null)
                    ? $edge['sourceLineId']
                    : '';
                $start = is_int($edge['startByte'] ?? null) ? $edge['startByte'] : -1;
                $end = is_int($edge['endByte'] ?? null) ? $edge['endByte'] : -1;
                $coverageIndex = array_search($sourceId, $coverageSourceIds, true);
                if (!is_int($coverageIndex)
                    || $start < 0
                    || $end <= $start
                    || $end > $coverageLengths[$coverageIndex]) {
                    return false;
                }
                $nodeIndex = array_search(
                    $nodeId,
                    $coverageNodes[$coverageIndex],
                    true
                );
                if (!is_int($nodeIndex)) {
                    return false;
                }
                $coveragePackedRanges[$coverageIndex] .= pack('N2', $start, $end);
                $byteIndex = intdiv($nodeIndex, 8);
                $mask = 1 << ($nodeIndex % 8);
                $seen = ord($coverageSeenNodeBits[$coverageIndex][$byteIndex]);
                if (($seen & $mask) === 0) {
                    $coverageSeenNodeBits[$coverageIndex][$byteIndex] = chr($seen | $mask);
                    $coverageSeenNodeCounts[$coverageIndex]++;
                }
                $edgeBytes += $end - $start;
            }
        }
        if ($edgeBytes !== $emittedSignificantBytes) {
            return false;
        }

        $coverageCount = count($coverageSourceIds);
        for ($coverageIndex = 0; $coverageIndex < $coverageCount; $coverageIndex++) {
            $sourceRanges = str_split($coveragePackedRanges[$coverageIndex], 8);
            sort($sourceRanges, SORT_STRING);
            $cursor = 0;
            foreach ($sourceRanges as $packedRange) {
                $range = unpack('Nstart/Nend', $packedRange);
                if (!is_array($range) || $range['start'] !== $cursor) {
                    return false;
                }
                $cursor = $range['end'];
            }
            if ($cursor !== $coverageLengths[$coverageIndex]
                || $coverageSeenNodeCounts[$coverageIndex]
                    !== count($coverageNodes[$coverageIndex])) {
                return false;
            }
            unset(
                $coverageSourceIds[$coverageIndex],
                $coverageLengths[$coverageIndex],
                $coverageNodes[$coverageIndex],
                $coveragePackedRanges[$coverageIndex],
                $coverageSeenNodeBits[$coverageIndex],
                $coverageSeenNodeCounts[$coverageIndex]
            );
        }

        return true;
    }

    /**
     * Re-derive semantic marker destinations from the live decorated AST and
     * compare them with the public mapping. This prevents callers from making
     * an empty inventory look resolved by forging output node IDs.
     *
     * @param list<array<string,mixed>> $records
     * @param list<AstNode> $blocks
     * @return array{sourceOccurrenceIds:array<string,true>,failureReason:?string}
     */
    private static function validatedExplicitSemanticStructureMappings(
        array $records,
        array $blocks,
        array $bindingContext = []
    ): array {
        $sensitiveIds = [];
        $normalizedById = [];
        $neededTextMappingIds = [];
        foreach ($records as $recordIndex => $record) {
            $id = (string) ($record['id'] ?? '');
            if (($record['hasExplicitDisposition'] ?? false) !== true) {
                continue;
            }
            $mapping = $record['sourceMapping'] ?? null;
            if (is_array($record['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                    && (string) ($record['significant'] ?? '') === '')) {
                $sensitiveIds[$id] = true;
                $normalizedById[$id] = $mapping;
                $proof = is_array($record['semanticStructureProof'] ?? null)
                    ? $record['semanticStructureProof']
                    : null;
                $anchorCount = is_array($proof)
                    && ($proof['version'] ?? null) === 2
                    && is_array($proof['anchorSourceOccurrenceIds'] ?? null)
                        ? count($proof['anchorSourceOccurrenceIds'])
                        : 2;
                for ($offset = 1; $offset <= $anchorCount; $offset++) {
                    $anchorId = (string) ($records[$recordIndex + $offset]['id'] ?? '');
                    if ($anchorId !== '') {
                        $neededTextMappingIds[$anchorId] = true;
                    }
                }
            }
        }
        if ($sensitiveIds === []) {
            return ['sourceOccurrenceIds' => [], 'failureReason' => null];
        }

        $textMappings = [];
        foreach ($records as $record) {
            if (($record['hasExplicitDisposition'] ?? false) !== true) {
                continue;
            }
            $id = (string) ($record['id'] ?? '');
            if (!isset($neededTextMappingIds[$id])) {
                continue;
            }
            $mapping = $record['sourceMapping'] ?? null;
            if (is_array($mapping) && ($mapping['status'] ?? null) === 'output') {
                // The semantic validator is read-only. Reuse the normalized
                // wrapper already owned by this record rather than allocating
                // another four-key mapping for every emitted occurrence.
                $textMappings[$id] = $mapping;
            }
        }
        unset($neededTextMappingIds);

        $derived = PdfSourceSemanticBindingValidator::sourceBindingSemanticStructureMappings(
            $records,
            $blocks,
            $textMappings,
            $bindingContext
        );
        if ($derived['failureReason'] !== null) {
            return ['sourceOccurrenceIds' => [], 'failureReason' => $derived['failureReason']];
        }
        foreach (array_keys($sensitiveIds) as $id) {
            foreach ($blocks as $block) {
                if (PdfSourceOutputDecorator::nodeHasSourceLineEdge($block, $id)) {
                    return [
                        'sourceOccurrenceIds' => [],
                        'failureReason' => 'semantic-list-marker-has-text-byte-edge',
                    ];
                }
            }
            $expected = $derived['mappingBySourceId'][$id] ?? null;
            $actual = $normalizedById[$id] ?? null;
            if (!is_array($expected)
                || !is_array($actual)
                || ($actual['status'] ?? null) !== 'output'
                || ($actual['mappingMode'] ?? null) !== self::SEMANTIC_STRUCTURE_MAPPING_MODE
                || ($actual['scopeId'] ?? null) !== null) {
                return [
                    'sourceOccurrenceIds' => [],
                    'failureReason' => 'semantic-list-marker-public-mapping-does-not-match-live-structure',
                ];
            }
            $expectedNodes = $expected['destinationNodeIds'];
            $actualNodes = $actual['destinationNodeIds'];
            $expectedInlineIds = $expected['destinationInlineIds'];
            $actualInlineIds = $actual['destinationInlineIds'];
            sort($expectedNodes, SORT_STRING);
            sort($actualNodes, SORT_STRING);
            sort($expectedInlineIds, SORT_STRING);
            sort($actualInlineIds, SORT_STRING);
            if ($expectedNodes !== $actualNodes || $expectedInlineIds !== $actualInlineIds) {
                return [
                    'sourceOccurrenceIds' => [],
                    'failureReason' => 'semantic-list-marker-public-mapping-does-not-match-live-structure',
                ];
            }
        }

        return ['sourceOccurrenceIds' => $sensitiveIds, 'failureReason' => null];
    }


    /**
     * @param iterable<string> $chunks
     * @return array{tokens:array<string,int>,characters:array<string,int>}
     */
    private static function inventoryFromChunks(iterable $chunks): array
    {
        $tokens = [];
        $characters = [];
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            $matched = preg_match_all(
                "/[\p{L}\p{M}\p{N}]+(?:['\x{2019}][\p{L}\p{M}\p{N}]+)*/u",
                $normalized,
                $tokenMatches
            );
            if ($matched !== false) {
                foreach ($tokenMatches[0] as $token) {
                    $tokens[$token] = ($tokens[$token] ?? 0) + 1;
                }
            }
            $offset = 0;
            $length = strlen($normalized);
            while ($offset < $length) {
                $found = preg_match('/[^\s\p{Cc}\p{Cf}]/u', $normalized, $match, PREG_OFFSET_CAPTURE, $offset);
                if ($found !== 1) {
                    break;
                }
                $character = (string) $match[0][0];
                $byteOffset = (int) $match[0][1];
                $characters[$character] = ($characters[$character] ?? 0) + 1;
                $offset = $byteOffset + strlen($character);
            }
        }

        return compact('tokens', 'characters');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{2060}"], '', $text);
        if (class_exists('Normalizer')) {
            // Canonical normalization joins equivalent combining sequences,
            // but deliberately keeps compatibility characters such as ²,
            // ﬀ, and circled digits distinct. An occurrence disposition is
            // allowed to prove boundary/order changes, never substitutions.
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private static function sampleText(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 160, 'UTF-8');
        }

        return substr($text, 0, 160);
    }
}
