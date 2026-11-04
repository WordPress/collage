<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Validate semantic PDF source proofs and their exact structural AST targets.
 *
 * Kept separate from PdfSourceBindingValidator so ordinary source/output
 * candidate validation does not compile semantic receipt machinery.
 */
final class PdfSourceSemanticBindingValidator
{
    private const SEMANTIC_STRUCTURE_PROOF_METHOD = 'exact-standalone-list-marker-to-item';

    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @return array<string,mixed>|null */
    public static function normalizedSemanticStructureProof(
        mixed $value,
        string $id,
        bool $strictReceipts = false
    ): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $id . ' has an invalid semantic-structure proof.'
            );
        }
        $version = $value['version'] ?? null;
        $method = is_string($value['method'] ?? null) ? $value['method'] : '';
        $listType = is_string($value['listType'] ?? null) ? $value['listType'] : '';
        $markerOrdinal = $value['markerOrdinal'] ?? null;
        $markerDigest = is_string($value['markerDigest'] ?? null) ? $value['markerDigest'] : '';
        $anchorId = is_string($value['anchorSourceOccurrenceId'] ?? null)
            ? $value['anchorSourceOccurrenceId']
            : '';
        $itemDigest = is_string($value['itemProjectionDigest'] ?? null)
            ? $value['itemProjectionDigest']
            : '';
        $anchorIds = $value['anchorSourceOccurrenceIds'] ?? null;
        $anchorProjectionDigest = is_string($value['anchorProjectionDigest'] ?? null)
            ? $value['anchorProjectionDigest']
            : '';
        $compositeReceipt = $value['compositeLayoutPresentationReceipt'] ?? null;
        if ($compositeReceipt !== null) {
            if (!is_array($compositeReceipt)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id
                        . ' has an invalid composite list-layout presentation receipt.'
                );
            }
            if ($strictReceipts) {
                $compositeReceipt = PdfSourceSemanticReceiptBindingValidator::
                    normalizedCompositeLayoutPresentationReceipt($compositeReceipt, $id);
            }
        }
        $presentationReceipt = $value['presentationRepairReceipt'] ?? null;
        if ($presentationReceipt !== null) {
            if (!is_array($presentationReceipt)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id
                        . ' has an invalid list presentation-repair receipt.'
                );
            }
            if ($strictReceipts) {
                $presentationReceipt = PdfSourceSemanticReceiptBindingValidator::
                    normalizedListPresentationRepairReceipt($presentationReceipt, $id);
            }
        }
        $ordinalIsValid = $listType === 'ordered'
            ? is_int($markerOrdinal) && $markerOrdinal >= 1
            : $markerOrdinal === null;
        $extendedAnchorIsValid = $version === 2
            && is_array($anchorIds)
            && array_is_list($anchorIds)
            && count($anchorIds) >= (is_array($compositeReceipt)
                ? 1
                : (is_array($presentationReceipt) ? 2 : 3))
            && count($anchorIds) <= 16
            && ($anchorIds[0] ?? null) === $anchorId
            && count(array_unique($anchorIds, SORT_STRING)) === count($anchorIds)
            && array_reduce(
                $anchorIds,
                static fn (bool $valid, mixed $candidate): bool =>
                    $valid && is_string($candidate) && $candidate !== '',
                true
            )
            && preg_match('/^[a-f0-9]{64}$/D', $anchorProjectionDigest) === 1;
        if (!in_array($version, [1, 2], true)
            || $method !== self::SEMANTIC_STRUCTURE_PROOF_METHOD
            || !in_array($listType, ['ordered', 'bullet'], true)
            || !$ordinalIsValid
            || preg_match('/^[a-f0-9]{64}$/D', $markerDigest) !== 1
            || $anchorId === ''
            || $anchorId === $id
            || preg_match('/^[a-f0-9]{64}$/D', $itemDigest) !== 1
            || ($version === 2 && !$extendedAnchorIsValid)
            || ($compositeReceipt !== null && $presentationReceipt !== null)
            || ($version === 1
                && ($anchorIds !== null
                    || $anchorProjectionDigest !== ''
                    || $compositeReceipt !== null
                    || $presentationReceipt !== null))) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $id . ' has an incomplete semantic-structure proof.'
            );
        }

        $normalized = [
            'version' => $version,
            'method' => self::SEMANTIC_STRUCTURE_PROOF_METHOD,
            'listType' => $listType,
            'markerOrdinal' => $markerOrdinal,
            'markerDigest' => $markerDigest,
            'anchorSourceOccurrenceId' => $anchorId,
            'itemProjectionDigest' => $itemDigest,
        ];
        if ($version === 2) {
            $normalized['anchorSourceOccurrenceIds'] = $anchorIds;
            $normalized['anchorProjectionDigest'] = $anchorProjectionDigest;
            if ($compositeReceipt !== null) {
                $normalized['compositeLayoutPresentationReceipt'] = $compositeReceipt;
            } elseif ($presentationReceipt !== null) {
                $normalized['presentationRepairReceipt'] = $presentationReceipt;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,mixed>|null $next
     */
    public static function sourceBindingStructuralMarkerRecordFailureReason(
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
        if (!hash_equals(
            (string) ($proof['markerDigest'] ?? ''),
            hash('sha256', $sourceSignificant)
        )) {
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
     * @param list<AstNode> $blocks
     * @param list<array<string,mixed>> $ranges
     */
    public static function sourceBindingSemanticStructureFailureReason(
        array $records,
        array $blocks,
        array $ranges,
        array $bindingContext
    ): ?string {
        return self::sourceBindingSemanticStructurePlan(
            $records,
            $blocks,
            $ranges,
            $bindingContext
        )['failureReason'];
    }

    /**
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
        return PdfSourceSemanticStructurePlan::sourceBindingSemanticStructurePlan(
            $records,
            $blocks,
            $ranges,
            $bindingContext
        );
    }

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
        return PdfSourceSemanticStructureMapping::sourceBindingSemanticStructureMappings(
            $records,
            $blocks,
            $textMappingsBySourceId,
            $bindingContext
        );
    }
}
