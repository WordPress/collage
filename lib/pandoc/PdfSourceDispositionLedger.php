<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Account for each source text occurrence after PDF semantic inference.
 *
 * PdfTextFidelityLedger intentionally answers aggregate conservation
 * questions. This ledger answers the complementary occurrence question:
 * did every individual source line become output, receive an evidenced
 * suppression/replacement disposition, or remain unresolved?
 *
 * Automatic matching is deliberately conservative. It consumes emitted
 * token and significant-character inventories once, so duplicated source
 * lines cannot all claim the same emitted occurrence. Destructive or visual
 * replacement dispositions must be supplied explicitly with a reason.
 */
final class PdfSourceDispositionLedger
{
    private const SAMPLE_LIMIT = 32;
    private const ORDER_MATCH_CANDIDATE_LIMIT = 256;
    private const SOURCE_BINDING_STATE_LIMIT = 100000;
    private const SEMANTIC_STRUCTURE_PROOF_METHOD = 'exact-standalone-list-marker-to-item';
    private const SEMANTIC_STRUCTURE_MAPPING_MODE = 'exact-semantic-list-marker';

    /** @var array<string, true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @var array<string, true> */
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

    /** @var array<string, true> */
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
     * Validate a page-local order scope whose source occurrences may each
     * contribute several separated token ranges after column reconstruction.
     * The same bounded solver used by the binding path must find exactly one
     * complete interleaving; spelling inventory alone is never sufficient.
     *
     * @param array<string,string> $sourceOccurrenceProjections
     */
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
     * Bind the final AST to stable source occurrences before calculating the
     * disposition ledger. The alignment is exact after insignificant PDF
     * whitespace is removed. A named order-proof scope may interleave its
     * source occurrences, but only when there is exactly one token-level
     * interleaving which produces the declared emitted projection.
     *
     * Destination IDs are hashes of source IDs and numeric spans; neither the
     * node attributes nor the public edge graph contains source text. When an
     * alignment is not unique, no destination is guessed and the affected
     * occurrence remains unresolved.
     *
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array{blocks:list<AstNode>,explicitDispositions:array<string,array<string,mixed>|string>,complete:bool,failureReason:?string}
     */
    public static function bindSourceLineItemsToOutput(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = [],
        array $bindingContext = []
    ): array {
        return self::bindSourceLineItemsToOutputInPlace(
            $sourceLineItems,
            $blocks,
            $explicitDispositions,
            $bindingContext
        );
    }

    /**
     * Validate an exact candidate without constructing a decorated AST or a
     * public occurrence ledger. PdfReader uses this after the compact binding
     * core is already resident, avoiding a second validator-class compile at
     * the import's allocator high-water mark.
     *
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
        $projection = PdfSourceProjectionBinding::sourceBindingProjection($records);
        if ($projection['failureReason'] !== null) {
            return ['complete' => false, 'failureReason' => $projection['failureReason']];
        }
        $output = PdfSourceProjectionBinding::sourceBindingOutput($blocks);
        $outputText = $output['text'];
        unset($output);
        if (hash_equals($projection['text'], $outputText)) {
            $ranges = PdfSourceProjectionBinding::directSourceBindingRanges(
                $projection['units']
            );
            if ($ranges === null) {
                return [
                    'complete' => false,
                    'failureReason' =>
                        'authorized-order-scope-has-ambiguous-output-mapping',
                ];
            }
        } else {
            $ranges = PdfSourceProjectionBinding::uniqueInterleavedSourceBindingRanges(
                $projection['units'],
                $outputText
            );
            if ($ranges === null) {
                return [
                    'complete' => false,
                    'failureReason' =>
                        'projected-source-stream-does-not-equal-final-output',
                ];
            }
        }
        usort($ranges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $cursor = 0;
        foreach ($ranges as $range) {
            if ((int) $range['outputStart'] !== $cursor
                || (int) $range['outputEnd'] <= (int) $range['outputStart']) {
                return [
                    'complete' => false,
                    'failureReason' =>
                        'final-output-node-spans-could-not-be-bound-exactly',
                ];
            }
            $cursor = (int) $range['outputEnd'];
        }
        if ($cursor !== strlen($outputText)) {
            return [
                'complete' => false,
                'failureReason' =>
                    'final-output-node-spans-could-not-be-bound-exactly',
            ];
        }
        foreach ($records as $record) {
            if (!is_array($record['semanticStructureProof'] ?? null)) {
                continue;
            }
            $semanticPlan =
                PdfSourceSemanticBindingValidator::sourceBindingSemanticStructurePlan(
                    $records,
                    $blocks,
                    $ranges,
                    $bindingContext
                );
            if ($semanticPlan['failureReason'] !== null) {
                return [
                    'complete' => false,
                    'failureReason' => $semanticPlan['failureReason'],
                ];
            }
            break;
        }

        return ['complete' => true, 'failureReason' => null];
    }

    /**
     * Memory-bounded variant for an owner which no longer needs the unbound
     * disposition map. The ordinary public method above remains non-mutating.
     *
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array{blocks:list<AstNode>,explicitDispositions:array<string,array<string,mixed>|string>,complete:bool,failureReason:?string}
     */
    public static function bindSourceLineItemsToOutputInPlace(
        array $sourceLineItems,
        array $blocks,
        array &$explicitDispositions,
        array $bindingContext = []
    ): array {
        $records = self::sourceBindingRecordsInPlace(
            $sourceLineItems,
            $explicitDispositions
        );
        // Each normalized record now owns the canonical disposition fields
        // needed after decoration. The consumed raw proof graph would merely
        // duplicate that data across the highest-memory binding phase. Only
        // dispositions which did not correspond to a significant source
        // occurrence remain in the caller-owned map.
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
        $projection = PdfSourceProjectionBinding::sourceBindingProjection($records);
        $output = PdfSourceProjectionBinding::sourceBindingOutput($blocks);
        $complete = $projection['failureReason'] === null;
        $failureReason = $projection['failureReason'];

        $ranges = [];
        if ($complete) {
            if (hash_equals($projection['text'], $output['text'])) {
                $ranges = PdfSourceProjectionBinding::directSourceBindingRanges(
                    $projection['units']
                );
                if ($ranges === null) {
                    $complete = false;
                    $failureReason = 'authorized-order-scope-has-ambiguous-output-mapping';
                }
            } else {
                $ranges = PdfSourceProjectionBinding::uniqueInterleavedSourceBindingRanges(
                    $projection['units'],
                    $output['text']
                );
                if ($ranges === null) {
                    $complete = false;
                    $failureReason = 'projected-source-stream-does-not-equal-final-output';
                }
            }
        }
        $semanticStructureTargetsBySourceId = [];
        if ($complete) {
            $hasSemanticStructureRecords = false;
            foreach ($records as $record) {
                if (is_array($record['semanticStructureProof'] ?? null)
                    || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                        && (string) ($record['significant'] ?? '') === '')) {
                    $hasSemanticStructureRecords = true;
                    break;
                }
            }
            if ($hasSemanticStructureRecords) {
                $semanticStructurePlan =
                    PdfSourceSemanticBindingValidator::sourceBindingSemanticStructurePlan(
                        $records,
                        $blocks,
                        $ranges,
                        $bindingContext
                    );
                if ($semanticStructurePlan['failureReason'] !== null) {
                    $complete = false;
                    $failureReason = $semanticStructurePlan['failureReason'];
                } else {
                    $semanticStructureTargetsBySourceId =
                        $semanticStructurePlan['targetsBySourceId'];
                }
                unset($semanticStructurePlan);
            }
            unset($hasSemanticStructureRecords);
        }
        unset($projection);

        $decoratedBlocks = $blocks;
        $mappingBySourceId = [];
        if ($complete) {
            $decoration = PdfSourceOutputDecorator::decorate(
                $blocks,
                $output,
                $ranges
            );
            if ($decoration === null) {
                $complete = false;
                $failureReason = 'final-output-node-spans-could-not-be-bound-exactly';
            } else {
                $decoratedBlocks = $decoration['blocks'];
                $mappingBySourceId = $decoration['mappingBySourceId'];
                unset($decoration, $projection, $output, $ranges);
                $structural = PdfSourceOutputDecorator::semanticStructureMappingsFromPlan(
                    $decoratedBlocks,
                    $semanticStructureTargetsBySourceId
                );
                if ($structural['failureReason'] !== null) {
                    $complete = false;
                    $failureReason = $structural['failureReason'];
                    $decoratedBlocks = $blocks;
                    $mappingBySourceId = [];
                } else {
                    $mappingBySourceId = array_replace(
                        $mappingBySourceId,
                        $structural['mappingBySourceId']
                    );
                    unset($structural, $blocks);
                }
            }
        }
        unset($output, $ranges, $semanticStructureTargetsBySourceId);

        $boundDispositions =& $explicitDispositions;
        $recordCount = count($records);
        for ($recordIndex = 0; $recordIndex < $recordCount; $recordIndex++) {
            $record = $records[$recordIndex];
            unset($records[$recordIndex]);
            $id = $record['id'];
            $disposition = $record['disposition'];
            $orderProofScopeId = is_array($record['orderProof'] ?? null)
                && is_string($record['orderProof']['scopeId'] ?? null)
                    ? $record['orderProof']['scopeId']
                    : null;
            $bound = self::explicitDispositionFromBindingRecord($record);
            unset($record);
            $mapping = $mappingBySourceId[$id] ?? null;
            unset($mappingBySourceId[$id]);
            if (!isset(self::OUTPUT_DISPOSITIONS[$disposition])) {
                $bound['disposition'] = $disposition;
                $bound['sourceMapping'] = [
                    'status' => $disposition === 'unresolved' ? 'unresolved' : 'disposition',
                    'mappingMode' => $disposition === 'unresolved'
                        ? 'unresolved'
                        : 'explicit-disposition',
                ];
            } elseif ($complete && is_array($mapping)) {
                $bound['disposition'] = $disposition;
                $bound['sourceMapping'] = [
                    'status' => 'output',
                    'mappingMode' => $mapping['mappingMode'],
                    'destinationNodeIds' => $mapping['destinationNodeIds'],
                    'destinationInlineIds' => $mapping['destinationInlineIds'],
                    'scopeId' => $mapping['scopeId'],
                ];
            } else {
                // Keep a valid mapped order proof in place so the ledger can
                // name the exact unauthorized segment. Ordinary occurrences
                // without a destination are explicitly unresolved rather
                // than falling back to the global character inventory.
                if ($orderProofScopeId === null) {
                    $bound = [
                        'disposition' => 'unresolved',
                        'reason' => 'No unique exact source-to-output destination mapping was available.',
                        'evidence' => [
                            'method' => 'exact-source-output-binding',
                            'failureReason' => $failureReason,
                            'requestedDisposition' => $disposition,
                        ],
                        'sourceMapping' => [
                            'status' => 'unresolved',
                            'mappingMode' => 'unresolved',
                        ],
                    ];
                } else {
                    $bound['sourceMapping'] = [
                        'status' => 'unresolved',
                        'mappingMode' => 'unresolved',
                        'scopeId' => $orderProofScopeId,
                    ];
                }
            }
            $boundDispositions[$id] = $bound;
            unset($id, $disposition, $orderProofScopeId, $mapping, $bound);
        }
        unset($records, $mappingBySourceId);

        return [
            'blocks' => $decoratedBlocks,
            'explicitDispositions' => $boundDispositions,
            'complete' => $complete,
            'failureReason' => $failureReason,
        ];
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private static function explicitDispositionFromBindingRecord(array $record): array
    {
        if (($record['hasExplicitDisposition'] ?? false) !== true) {
            return [];
        }

        $disposition = ['disposition' => (string) ($record['disposition'] ?? 'unresolved')];
        if ((string) ($record['reason'] ?? '') !== '') {
            $disposition['reason'] = $record['reason'];
        }
        if (is_array($record['evidence'] ?? null) && $record['evidence'] !== []) {
            $disposition['evidence'] = $record['evidence'];
        }
        if (is_string($record['textProjection'] ?? null)) {
            $disposition['textProjection'] = $record['textProjection'];
        }
        if (($record['allowOrderChange'] ?? false) === true) {
            $disposition['allowOrderChange'] = true;
        }
        if (is_array($record['orderProof'] ?? null)) {
            $disposition['orderProof'] = $record['orderProof'];
        }
        if (is_array($record['semanticStructureProof'] ?? null)) {
            $disposition['semanticStructureProof'] = $record['semanticStructureProof'];
        }

        return $disposition;
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItems(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = [],
        array $bindingContext = []
    ): array {
        return PdfSourceOccurrenceLedger::fromSourceLineItems(
            $sourceLineItems,
            $blocks,
            $explicitDispositions,
            $bindingContext
        );
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItemsInPlace(
        array $sourceLineItems,
        array $blocks,
        array &$explicitDispositions,
        array $bindingContext = []
    ): array {
        return PdfSourceOccurrenceLedger::fromSourceLineItemsInPlace(
            $sourceLineItems,
            $blocks,
            $explicitDispositions,
            $bindingContext
        );
    }

    /**
     * @param array<string,mixed>|string|null $value
     * @return array{disposition:string,reason:string,evidence:array<string,mixed>,textProjection:?string,allowOrderChange:bool,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string},semanticStructureProof:?array{version:int,method:string,listType:string,markerOrdinal:?int,markerDigest:string,anchorSourceOccurrenceId:string,itemProjectionDigest:string},sourceMapping:?array<string,mixed>}|null
     */
    private static function normalizedExplicitDisposition(array|string|null $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        $record = is_string($value) ? ['disposition' => $value] : $value;
        $disposition = is_string($record['disposition'] ?? null) ? $record['disposition'] : '';
        if (!isset(self::ALLOWED_DISPOSITIONS[$disposition])) {
            throw new InvalidArgumentException('Unknown PDF source disposition for ' . $id . '.');
        }
        $semanticStructureProof = $record['semanticStructureProof'] ?? null;

        return [
            'disposition' => $disposition,
            'reason' => is_string($record['reason'] ?? null) ? trim($record['reason']) : '',
            'evidence' => is_array($record['evidence'] ?? null) ? $record['evidence'] : [],
            'textProjection' => is_string($record['textProjection'] ?? null) ? $record['textProjection'] : null,
            'allowOrderChange' => ($record['allowOrderChange'] ?? false) === true,
            'orderProof' => PdfSourceOrderProofLedger::normalizedExplicitOrderProof(
                $record['orderProof'] ?? null,
                $id
            ),
            'semanticStructureProof' => $semanticStructureProof === null
                ? null
                : PdfSourceSemanticBindingValidator::normalizedSemanticStructureProof(
                    $semanticStructureProof,
                    $id,
                    true
                ),
            'sourceMapping' => self::normalizedSourceMapping($record['sourceMapping'] ?? null, $id),
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

        return [
            'status' => $status,
            'mappingMode' => is_string($value['mappingMode'] ?? null) && $value['mappingMode'] !== ''
                ? $value['mappingMode']
                : 'unresolved',
            'destinationNodeIds' => $nodeIds,
            'destinationInlineIds' => $inlineIds,
            'scopeId' => is_string($value['scopeId'] ?? null) && $value['scopeId'] !== ''
                ? $value['scopeId']
                : null,
        ];
    }

    /** @return list<string> */
    private static function normalizedDestinationIds(mixed $value, string $sourceId): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
        }
        $isList = array_is_list($value);
        $ids = [];
        foreach ($value as $id) {
            if (!is_string($id) || $id === '' || isset($ids[$id])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
            }
            $ids[$id] = true;
        }

        // Bound reader mappings already carry canonical lists. Preserve their
        // copy-on-write storage after validation instead of rebuilding the
        // same potentially long destination vector for every occurrence.
        return $isList ? $value : array_keys($ids);
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
        return self::sourceBindingRecordsFromMap(
            $sourceLineItems,
            $explicitDispositions,
            false
        );
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return list<array<string,mixed>>
     */
    public static function sourceBindingRecordsInPlace(
        array $sourceLineItems,
        array &$explicitDispositions
    ): array {
        return self::sourceBindingRecordsFromMap(
            $sourceLineItems,
            $explicitDispositions,
            true
        );
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return list<array<string,mixed>>
     */
    private static function sourceBindingRecordsFromMap(
        array $sourceLineItems,
        array &$explicitDispositions,
        bool $consumeExplicitDispositions
    ): array
    {
        $records = [];
        $seen = [];
        // One page-local order proof is deliberately attached to every
        // occurrence in its scope. Normalization rebuilds its nested source
        // ID/range arrays, so retaining that fresh copy per occurrence turns
        // a valid N-occurrence page proof into O(N^2) live memory. Intern only
        // strictly identical normalized proofs; a conflicting reuse of a
        // scope ID remains distinct and is rejected by the existing checks.
        $normalizedOrderProofsByScopeId = [];
        $normalizedOrderProofKeysByScopeId = [];
        $consumedDispositionSentinel = $consumeExplicitDispositions
            ? new \stdClass()
            : null;
        foreach ($sourceLineItems as $index => $item) {
            $record = is_array($item) ? $item : ['text' => (string) $item];
            $sourceText = is_string($record['text'] ?? null) ? $record['text'] : '';
            $text = trim($sourceText);
            $sourceSignificant = self::significantText($sourceText);
            if ($sourceSignificant === '') {
                // Match the ledger's actual Unicode significance policy.
                // ASCII trim() does not recognize PDF alignment blanks such
                // as U+2007 FIGURE SPACE, but they carry no bindable text.
                continue;
            }
            $id = self::sourceOccurrenceId($record, $index, $text);
            if (!$consumeExplicitDispositions) {
                if (isset($seen[$id])) {
                    throw new InvalidArgumentException(
                        'Duplicate PDF source occurrence ID ' . $id . '.'
                    );
                }
                $seen[$id] = true;
            } elseif (array_key_exists($id, $explicitDispositions)
                && $explicitDispositions[$id] === $consumedDispositionSentinel) {
                throw new InvalidArgumentException(
                    'Duplicate PDF source occurrence ID ' . $id . '.'
                );
            }
            $explicit = self::normalizedExplicitDisposition(
                $explicitDispositions[$id] ?? null,
                $id
            );
            if ($consumeExplicitDispositions) {
                // Reuse this existing ID bucket as the exact duplicate marker
                // while dropping its proof-rich raw value immediately.
                $explicitDispositions[$id] = $consumedDispositionSentinel;
            }
            $orderProofKey = null;
            if (is_array($explicit) && is_array($explicit['orderProof'] ?? null)) {
                $normalizedOrderProof = $explicit['orderProof'];
                $scopeId = (string) ($normalizedOrderProof['scopeId'] ?? '');
                if (isset($normalizedOrderProofsByScopeId[$scopeId])
                    && $normalizedOrderProofsByScopeId[$scopeId] === $normalizedOrderProof) {
                    $explicit['orderProof'] = $normalizedOrderProofsByScopeId[$scopeId];
                    $orderProofKey = $normalizedOrderProofKeysByScopeId[$scopeId];
                } elseif (!isset($normalizedOrderProofsByScopeId[$scopeId])) {
                    $normalizedOrderProofsByScopeId[$scopeId] = $normalizedOrderProof;
                    $encodedOrderProof = json_encode(
                        $normalizedOrderProof,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                    $orderProofKey = hash(
                        'sha256',
                        is_string($encodedOrderProof)
                            ? $encodedOrderProof
                            : serialize($normalizedOrderProof)
                    );
                    $normalizedOrderProofKeysByScopeId[$scopeId] = $orderProofKey;
                } else {
                    $encodedOrderProof = json_encode(
                        $normalizedOrderProof,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                    $orderProofKey = hash(
                        'sha256',
                        is_string($encodedOrderProof)
                            ? $encodedOrderProof
                            : serialize($normalizedOrderProof)
                    );
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
                'hasExplicitDisposition' => $explicit !== null,
                'reason' => $explicit['reason'] ?? '',
                'sourceText' => $sourceText,
                'projectionText' => $projectionText,
                'textProjection' => $explicit['textProjection'] ?? null,
                'significant' => self::significantText($projectionText),
                'sourceSignificant' => $sourceSignificant,
                'evidence' => $explicit['evidence'] ?? [],
                'allowOrderChange' => ($explicit['allowOrderChange'] ?? false) === true,
                'orderProof' => $explicit['orderProof'] ?? null,
                'orderProofKey' => $orderProofKey,
                'semanticStructureProof' => $explicit['semanticStructureProof'] ?? null,
                'sourceMapping' => $explicit['sourceMapping'] ?? null,
            ];
        }

        if ($consumeExplicitDispositions) {
            // The raw map has transferred every significant occurrence into
            // normalized records and served as the duplicate-ID index itself.
            // Preserve only unmatched entries (for example an explicit
            // disposition attached to an insignificant source item); matched
            // values have already been released in favor of the canonical
            // record representation above.
            $unmatchedExplicitDispositions = [];
            foreach ($explicitDispositions as $sourceId => $disposition) {
                if ($disposition !== $consumedDispositionSentinel) {
                    $unmatchedExplicitDispositions[$sourceId] = $disposition;
                }
            }
            $explicitDispositions = $unmatchedExplicitDispositions;
        }

        return $records;
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


    /** @param iterable<string> $chunks @return array{bytes:int,digest:string} */
    public static function significantCharacterSummary(iterable $chunks): array
    {
        $digest = hash_init('sha256');
        $bytes = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $bytes += self::updateSignificantCharacterDigest($digest, $chunk);
        }

        return ['bytes' => $bytes, 'digest' => hash_final($digest)];
    }

    /** @param \HashContext $digest */
    public static function updateSignificantCharacterDigest(object $digest, string $chunk): int
    {
        $significant = self::significantText($chunk);
        hash_update($digest, $significant);

        return strlen($significant);
    }

    public static function significantText(string $chunk): string
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
    public static function updateSignificantCharacterInventory(array &$characters, string $chunk): int
    {
        $significant = self::significantText($chunk);
        $offset = 0;
        $length = strlen($significant);
        while ($offset < $length) {
            $found = preg_match('/./us', $significant, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($found !== 1) {
                // Invalid UTF-8 is already a fidelity mismatch elsewhere. It
                // must not turn into an order authorization here.
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

    /** @param iterable<string> $chunks @return \Generator<int,string> */
    public static function significantCharactersFromChunks(iterable $chunks): \Generator
    {
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $significant = self::significantText($chunk);
            $offset = 0;
            $length = strlen($significant);
            while ($offset < $length) {
                $found = preg_match('/./us', $significant, $match, PREG_OFFSET_CAPTURE, $offset);
                if ($found !== 1) {
                    yield $significant[$offset];
                    $offset++;
                    continue;
                }
                $character = (string) $match[0][0];
                $byteOffset = (int) $match[0][1];
                yield $character;
                $offset = $byteOffset + strlen($character);
            }
        }
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    public static function canConsume(array $available, array $needed): bool
    {
        foreach ($needed as $value => $count) {
            if (($available[$value] ?? 0) < $count) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    public static function consume(array &$available, array $needed): void
    {
        foreach ($needed as $value => $count) {
            $remaining = ($available[$value] ?? 0) - $count;
            if ($remaining > 0) {
                $available[$value] = $remaining;
            } else {
                unset($available[$value]);
            }
        }
    }

    /** @param list<AstNode> $nodes @return iterable<string> */
    public static function textChunksFromNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                yield from self::textChunksFromNode($node);
            }
        }
    }

    /** @return iterable<string> */
    private static function textChunksFromNode(AstNode $node): iterable
    {
        if ($node->type === 'text') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }
        // CodeBlock is a Pandoc leaf whose payload is stored in its `text`
        // attribute rather than in child Text nodes. Ignoring that payload
        // makes a verbatim emitted listing look wholly unresolved and also
        // invalidates the ordered-character proof. Keep this deliberately
        // type-specific: arbitrary container `text` attributes can be
        // derived summaries of children and would otherwise be double-counted.
        if ($node->type === 'code_block') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }
        foreach ($node->children() as $child) {
            yield from self::textChunksFromNode($child);
        }
    }

}
