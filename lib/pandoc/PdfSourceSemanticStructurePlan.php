<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Validate semantic marker targets against an undecorated AST.
 */
final class PdfSourceSemanticStructurePlan extends PdfSourceSemanticStructureFacts
{
    /**
     * Validate semantic marker targets against the undecorated AST and retain
     * only their stable list/item paths. The output binder can translate
     * those paths to final source-node IDs after ordinary byte decoration.
     *
     * @param list<array<string,mixed>> $records
     * @param list<AstNode> $blocks
     * @param list<array<string,mixed>> $ranges
     * @return array{targetsBySourceId:array<string,array{blockIndex:int,itemIndex:int}>,failureReason:?string}
     */
    public static function sourceBindingSemanticStructurePlan(
        array $records,
        array $blocks,
        array $ranges,
        array $bindingContext
    ): array {
        $structuralIndexes = [];
        foreach ($records as $recordIndex => $record) {
            if (is_array($record['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                    && (string) ($record['significant'] ?? '') === '')) {
                $structuralIndexes[] = $recordIndex;
            }
        }
        if ($structuralIndexes === []) {
            return ['targetsBySourceId' => [], 'failureReason' => null];
        }

        $targets = self::sourceBindingSemanticListTargets($blocks, $ranges);
        $mappedSourceIds = [];
        foreach ($ranges as $range) {
            $sourceId = is_string($range['sourceOccurrenceId'] ?? null)
                ? $range['sourceOccurrenceId']
                : '';
            if ($sourceId !== '') {
                $mappedSourceIds[$sourceId] = true;
            }
        }
        $claimedTargets = [];
        $targetsBySourceId = [];
        foreach ($structuralIndexes as $recordIndex) {
            $record = $records[$recordIndex];
            $failureReason = PdfSourceSemanticBindingValidator::
                sourceBindingStructuralMarkerRecordFailureReason(
                    $record,
                    $records[$recordIndex + 1] ?? null
                );
            if ($failureReason !== null) {
                return ['targetsBySourceId' => [], 'failureReason' => $failureReason];
            }
            $proof = $record['semanticStructureProof'];
            $anchorRecords = [$records[$recordIndex + 1] ?? null];
            if (($proof['version'] ?? null) === 2) {
                $anchorIds = $proof['anchorSourceOccurrenceIds'] ?? [];
                $anchorRecords = is_array($anchorIds)
                    ? array_slice($records, $recordIndex + 1, count($anchorIds))
                    : [];
                if (array_column($anchorRecords, 'id') !== $anchorIds) {
                    return [
                        'targetsBySourceId' => [],
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
                    'targetsBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-extended-anchor-proof-does-not-match-source',
                ];
            }
            $anchorSignificant = ($proof['version'] ?? null) === 2
                ? (string) ($anchorSequence['significant'] ?? '')
                : (string) (($records[$recordIndex + 1]['significant'] ?? ''));
            $anchorId = (string) ($proof['anchorSourceOccurrenceId'] ?? '');
            if (!isset($mappedSourceIds[$anchorId])) {
                return [
                    'targetsBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-anchor-has-no-exact-text-mapping',
                ];
            }

            $following = $records[$recordIndex + 2] ?? null;
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
                            $records[$recordIndex + 1],
                            $following,
                            $target['itemSignificant'],
                            $target['itemVisible']
                        ))) {
                    continue;
                }

                $sourcePrefixes = [];
                $allAnchorsReachTarget = true;
                foreach ($anchorRecords as $anchorRecord) {
                    if (!is_array($anchorRecord)) {
                        $allAnchorsReachTarget = false;
                        break;
                    }
                    $sourceId = (string) ($anchorRecord['id'] ?? '');
                    if (!isset($target['listSourceIds'][$sourceId])
                        || !isset($target['itemSourceIds'][$sourceId])) {
                        $allAnchorsReachTarget = false;
                        break;
                    }
                    $sourcePrefixes[] = [
                        'sourceOccurrenceId' => $sourceId,
                        'length' => strlen((string) ($anchorRecord['significant'] ?? '')),
                    ];
                }
                if (($proof['version'] ?? null) === 1
                    && !hash_equals($target['itemSignificant'], $anchorSignificant)) {
                    $followingId = is_array($following)
                        ? (string) ($following['id'] ?? '')
                        : '';
                    if ($followingId === ''
                        || !isset($target['listSourceIds'][$followingId])
                        || !isset($target['itemSourceIds'][$followingId])) {
                        $allAnchorsReachTarget = false;
                    } else {
                        $sourcePrefixes[] = [
                            'sourceOccurrenceId' => $followingId,
                            'length' => strlen((string) ($following['significant'] ?? '')),
                        ];
                    }
                }
                if (!$allAnchorsReachTarget
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
                    'targetsBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-has-no-unique-structural-target',
                ];
            }
            $targetKey = $candidates[0]['targetKey'];
            if (isset($claimedTargets[$targetKey])) {
                return [
                    'targetsBySourceId' => [],
                    'failureReason' =>
                        'semantic-list-marker-structural-target-is-reused',
                ];
            }
            $claimedTargets[$targetKey] = true;
            $targetsBySourceId[(string) ($record['id'] ?? '')] = [
                'blockIndex' => $candidates[0]['blockIndex'],
                'itemIndex' => $candidates[0]['itemIndex'],
            ];
        }

        return ['targetsBySourceId' => $targetsBySourceId, 'failureReason' => null];
    }
}
