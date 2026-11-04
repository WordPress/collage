<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Re-derive semantic marker destinations from a decorated AST.
 */
final class PdfSourceSemanticStructureMapping extends PdfSourceSemanticStructureFacts
{
    private const SEMANTIC_STRUCTURE_MAPPING_MODE = 'exact-semantic-list-marker';

    /**
     * @param list<array<string,mixed>> $records
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>> $textMappingsBySourceId
     * @return array{mappingBySourceId:array<string,array<string,mixed>>,failureReason:?string}
     */
    public static function sourceBindingSemanticStructureMappings(
        array $records,
        array $blocks,
        array $textMappingsBySourceId,
        array $bindingContext = []
    ): array {
        $structuralRecordIndexes = [];
        foreach ($records as $recordIndex => $record) {
            if (is_array($record['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                    && (string) ($record['significant'] ?? '') === '')) {
                $structuralRecordIndexes[] = $recordIndex;
            }
        }
        if ($structuralRecordIndexes === []) {
            return ['mappingBySourceId' => [], 'failureReason' => null];
        }

        $targets = [];
        foreach ($blocks as $blockIndex => $block) {
            $listType = match ($block->type) {
                'ordered_list' => 'ordered',
                'bullet_list' => 'bullet',
                default => null,
            };
            if ($listType === null) {
                continue;
            }
            $listNodeId = $block->attr('sourceNodeId');
            if (!is_string($listNodeId) || $listNodeId === '') {
                continue;
            }
            $start = $listType === 'ordered' ? $block->attr('start', 1) : null;
            if ($listType === 'ordered' && (!is_int($start) || $start < 1)) {
                continue;
            }
            foreach ($block->children() as $itemIndex => $item) {
                if ($item->type !== 'list_item') {
                    continue;
                }
                $itemNodeId = $item->attr('sourceNodeId');
                $itemSignificant = self::sourceBindingNodeSignificantText($item);
                $itemVisible = trim(self::sourceBindingNodeVisibleText($item));
                $itemStrictVisible = self::sourceBindingListItemStrictVisibleText($item);
                $itemEdges = $item->attr('sourceLineEdges', []);
                $visibleBlockCount = 0;
                foreach ($item->children() as $itemBlock) {
                    if (self::sourceBindingComparableVisibleText(
                        self::sourceBindingNodeVisibleText($itemBlock)
                    ) !== '') {
                        $visibleBlockCount++;
                    }
                }
                if (!is_string($itemNodeId)
                    || $itemNodeId === ''
                    || $itemSignificant === ''
                    || $itemVisible === ''
                    || $itemStrictVisible === ''
                    || !is_array($itemEdges)
                    || $itemEdges === []) {
                    continue;
                }
                $targets[] = [
                    'blockIndex' => $blockIndex,
                    'listType' => $listType,
                    'ordinal' => $listType === 'ordered' ? $start + $itemIndex : null,
                    'listNodeId' => $listNodeId,
                    'itemNodeId' => $itemNodeId,
                    'itemSignificant' => $itemSignificant,
                    'itemVisible' => $itemVisible,
                    'itemStrictVisible' => $itemStrictVisible,
                    'itemProjectionDigest' => hash('sha256', $itemSignificant),
                    'itemEdges' => $itemEdges,
                    'visibleBlockCount' => $visibleBlockCount,
                ];
            }
        }

        $claimedItems = [];
        $mappings = [];
        foreach ($structuralRecordIndexes as $recordIndex) {
            $record = $records[$recordIndex];
            $id = (string) ($record['id'] ?? '');
            $next = $records[$recordIndex + 1] ?? null;
            $failureReason = PdfSourceSemanticBindingValidator::
                sourceBindingStructuralMarkerRecordFailureReason($record, $next);
            if ($failureReason !== null) {
                return ['mappingBySourceId' => [], 'failureReason' => $failureReason];
            }
            $proof = $record['semanticStructureProof'];
            $anchorId = (string) $proof['anchorSourceOccurrenceId'];
            $anchorRecords = [$records[$recordIndex + 1] ?? null];
            if (($proof['version'] ?? null) === 2) {
                $anchorIds = $proof['anchorSourceOccurrenceIds'] ?? [];
                $anchorRecords = is_array($anchorIds)
                    ? array_slice($records, $recordIndex + 1, count($anchorIds))
                    : [];
                if (array_column($anchorRecords, 'id') !== $anchorIds) {
                    return [
                        'mappingBySourceId' => [],
                        'failureReason' =>
                            'semantic-list-marker-extended-anchor-is-not-consecutive',
                    ];
                }
            }
            $followingAnchorRecord =
                $records[$recordIndex + 1 + count($anchorRecords)] ?? null;
            $anchorSequence = ($proof['version'] ?? null) === 2
                ? self::sourceBindingExactOrdinaryAnchorSequence(
                    $record,
                    $anchorRecords,
                    $followingAnchorRecord,
                    $bindingContext
                )
                : null;
            if (($proof['version'] ?? null) === 2
                && (!is_array($anchorSequence)
                    || !hash_equals(
                        (string) ($proof['anchorProjectionDigest'] ?? ''),
                        hash('sha256', $anchorSequence['significant'])
                    ))) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-extended-anchor-proof-does-not-match-source',
                ];
            }
            $anchorSignificant = ($proof['version'] ?? null) === 2
                ? (string) ($anchorSequence['significant'] ?? '')
                : (string) ($next['significant'] ?? '');
            $following = $records[$recordIndex + 2] ?? null;
            $anchorMapping = $textMappingsBySourceId[$anchorId] ?? null;
            if (!is_array($anchorMapping)) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-anchor-has-no-exact-text-mapping',
                ];
            }

            $candidates = [];
            foreach ($targets as $target) {
                if ($target['listType'] !== $proof['listType']
                    || $target['ordinal'] !== $proof['markerOrdinal']
                    || !hash_equals(
                        $target['itemProjectionDigest'],
                        $proof['itemProjectionDigest']
                    )
                    || (($proof['version'] ?? null) === 2
                        ? (!is_array($anchorSequence)
                            || !str_starts_with(
                                $target['itemSignificant'],
                                $anchorSequence['significant']
                            )
                            || !self::sourceBindingExtendedAnchorMatchesListTarget(
                                $anchorSequence,
                                $target
                            ))
                        : !self::sourceBindingAnchorMatchesListItem(
                            $next,
                            $following,
                            $target['itemSignificant'],
                            $target['itemVisible']
                        ))) {
                    continue;
                }
                $sourcePrefixes = [];
                $allAnchorMappingsReachTarget = true;
                foreach ($anchorRecords as $anchorRecord) {
                    if (!is_array($anchorRecord)) {
                        $allAnchorMappingsReachTarget = false;
                        break;
                    }
                    $sourceOccurrenceId = (string) ($anchorRecord['id'] ?? '');
                    $mapping = $textMappingsBySourceId[$sourceOccurrenceId] ?? null;
                    if (!is_array($mapping)
                        || !in_array(
                            $target['listNodeId'],
                            $mapping['destinationNodeIds'] ?? [],
                            true
                        )
                        || !in_array(
                            $target['itemNodeId'],
                            $mapping['destinationInlineIds'] ?? [],
                            true
                        )) {
                        $allAnchorMappingsReachTarget = false;
                        break;
                    }
                    $sourcePrefixes[] = [
                        'sourceOccurrenceId' => $sourceOccurrenceId,
                        'length' => strlen((string) ($anchorRecord['significant'] ?? '')),
                    ];
                }
                if (($proof['version'] ?? null) === 1
                    && !hash_equals($target['itemSignificant'], $anchorSignificant)) {
                    $followingMapping = is_array($following)
                        ? ($textMappingsBySourceId[(string) ($following['id'] ?? '')]
                            ?? null)
                        : null;
                    if (!is_array($following)
                        || !is_array($followingMapping)
                        || !in_array(
                            $target['listNodeId'],
                            $followingMapping['destinationNodeIds'] ?? [],
                            true
                        )
                        || !in_array(
                            $target['itemNodeId'],
                            $followingMapping['destinationInlineIds'] ?? [],
                            true
                        )) {
                        $allAnchorMappingsReachTarget = false;
                    } else {
                        $sourcePrefixes[] = [
                            'sourceOccurrenceId' => (string) ($following['id'] ?? ''),
                            'length' => strlen((string) ($following['significant'] ?? '')),
                        ];
                    }
                }
                if (!$allAnchorMappingsReachTarget
                    || !self::sourceBindingItemStartsWithSourceRanges(
                        $target['itemEdges'],
                        $sourcePrefixes
                    )) {
                    continue;
                }
                $candidates[] = $target;
            }
            if (count($candidates) !== 1) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-has-no-unique-structural-target',
                ];
            }
            $target = $candidates[0];
            if (isset($claimedItems[$target['itemNodeId']])) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-structural-target-is-reused',
                ];
            }
            $claimedItems[$target['itemNodeId']] = true;
            $mappings[$id] = [
                'destinationNodeIds' => [$target['listNodeId']],
                'destinationInlineIds' => [$target['itemNodeId']],
                'mappingMode' => self::SEMANTIC_STRUCTURE_MAPPING_MODE,
                'scopeId' => null,
            ];
        }

        return ['mappingBySourceId' => $mappings, 'failureReason' => null];
    }
}
