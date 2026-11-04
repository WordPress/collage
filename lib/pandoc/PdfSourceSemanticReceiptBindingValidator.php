<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Validate presentation and composite-layout receipts attached to semantic
 * PDF list-marker proofs.
 *
 * This class is loaded only for proofs that actually carry a receipt.
 */
final class PdfSourceSemanticReceiptBindingValidator
{
    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @return array<string,mixed>|null */
    public static function normalizedListPresentationRepairReceipt(
        mixed $value,
        string $markerId
    ): ?array {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has an invalid list presentation-repair receipt.'
            );
        }
        $sourceIds = $value['sourceOccurrenceIds'] ?? null;
        $globalIndexes = $value['globalSourceIndexes'] ?? null;
        $boundaryOffsets = $value['significantBoundaryOffsets'] ?? null;
        $boundarySpaces = $value['visibleBoundarySpaces'] ?? null;
        $visiblePrefix = is_string($value['visiblePrefix'] ?? null)
            ? $value['visiblePrefix']
            : '';
        $significantPrefix = self::significantText($visiblePrefix);
        if (($value['version'] ?? null) !== 1
            || ($value['method'] ?? null)
                !== 'exact-consecutive-list-presentation-repair-prefix'
            || !self::isSha256($value['sourceSha256'] ?? null)
            || !is_int($value['page'] ?? null)
            || $value['page'] < 1
            || !is_int($value['stream'] ?? null)
            || $value['stream'] < 1
            || !is_array($sourceIds)
            || !array_is_list($sourceIds)
            || count($sourceIds) < 2
            || count(array_unique($sourceIds, SORT_STRING)) !== count($sourceIds)
            || array_reduce(
                $sourceIds,
                static fn (bool $valid, mixed $id): bool =>
                    $valid && is_string($id) && $id !== '',
                true
            ) !== true
            || !is_array($globalIndexes)
            || !array_is_list($globalIndexes)
            || count($globalIndexes) !== count($sourceIds)
            || $visiblePrefix === ''
            || !hash_equals(
                $visiblePrefix,
                self::sourceBindingStrictVisibleText($visiblePrefix)
            )
            || $significantPrefix === ''
            || !is_string($value['prefixProjectionDigest'] ?? null)
            || !hash_equals(
                $value['prefixProjectionDigest'],
                hash('sha256', $significantPrefix)
            )
            || !is_string($value['visiblePrefixDigest'] ?? null)
            || !hash_equals(
                $value['visiblePrefixDigest'],
                hash('sha256', $visiblePrefix)
            )
            || !self::isSha256($value['finalPageProjectionDigest'] ?? null)
            || !is_array($boundaryOffsets)
            || !array_is_list($boundaryOffsets)
            || count($boundaryOffsets) !== count($sourceIds) - 1
            || !is_array($boundarySpaces)
            || !array_is_list($boundarySpaces)
            || count($boundarySpaces) !== count($boundaryOffsets)
            || !self::sourceBindingAuthorizedTargetsAreCanonical(
                $value['authorizedTargets'] ?? null
            )
            || !self::receiptDigestMatches($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has an incomplete list presentation-repair receipt.'
            );
        }
        $previousBoundary = 0;
        foreach ($globalIndexes as $offset => $globalIndex) {
            if (!is_int($globalIndex)
                || $globalIndex < 0
                || ($offset > 0 && $globalIndex !== $globalIndexes[$offset - 1] + 1)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $markerId
                        . ' has a nonconsecutive list presentation-repair receipt.'
                );
            }
        }
        foreach ($boundaryOffsets as $offset => $boundaryOffset) {
            if (!is_int($boundaryOffset)
                || $boundaryOffset <= $previousBoundary
                || $boundaryOffset >= strlen($significantPrefix)
                || !is_bool($boundarySpaces[$offset] ?? null)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $markerId
                        . ' has invalid list presentation boundaries.'
                );
            }
            $previousBoundary = $boundaryOffset;
        }

        return $value;
    }

    /** @return array<string,mixed>|null */
    public static function normalizedCompositeLayoutPresentationReceipt(
        mixed $value,
        string $markerId
    ): ?array {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has an invalid composite list-layout presentation receipt.'
            );
        }
        $sourceIds = $value['sourceOccurrenceIds'] ?? null;
        $globalIndexes = $value['globalSourceIndexes'] ?? null;
        $listType = is_string($value['listType'] ?? null) ? $value['listType'] : '';
        $markerOrdinal = $value['markerOrdinal'] ?? null;
        $layoutVisible = is_string($value['layoutVisibleProjection'] ?? null)
            ? $value['layoutVisibleProjection']
            : '';
        $anchorVisible = is_string($value['anchorVisibleProjection'] ?? null)
            ? $value['anchorVisibleProjection']
            : '';
        $boundarySpace = $value['continuationBoundarySpace'] ?? null;
        $followingId = $value['followingSourceOccurrenceId'] ?? null;
        $followingDigest = $value['followingProjectionDigest'] ?? null;
        $nested = self::normalizedListPresentationOccurrenceReceipt(
            $value['followingPresentationRepairReceipt'] ?? null,
            $markerId
        );
        $ordinalIsValid = $listType === 'ordered'
            ? is_int($markerOrdinal) && $markerOrdinal >= 1
            : $markerOrdinal === null;
        if (($value['version'] ?? null) !== 1
            || ($value['method'] ?? null)
                !== 'exact-composite-list-layout-presentation-prefix'
            || !self::isSha256($value['sourceSha256'] ?? null)
            || !is_int($value['page'] ?? null)
            || $value['page'] < 1
            || !is_int($value['stream'] ?? null)
            || $value['stream'] < 1
            || !in_array($listType, ['ordered', 'bullet'], true)
            || !$ordinalIsValid
            || !is_array($sourceIds)
            || !array_is_list($sourceIds)
            || count($sourceIds) < 2
            || count($sourceIds) > 17
            || ($sourceIds[0] ?? null) !== $markerId
            || count(array_unique($sourceIds, SORT_STRING)) !== count($sourceIds)
            || array_reduce(
                $sourceIds,
                static fn (bool $valid, mixed $id): bool =>
                    $valid && is_string($id) && $id !== '',
                true
            ) !== true
            || !is_array($globalIndexes)
            || !array_is_list($globalIndexes)
            || count($globalIndexes) !== count($sourceIds)
            || $layoutVisible === ''
            || $anchorVisible === ''
            || !hash_equals(
                self::sourceBindingComparableVisibleText($layoutVisible),
                $layoutVisible
            )
            || !hash_equals(
                self::sourceBindingComparableVisibleText($anchorVisible),
                $anchorVisible
            )
            || !array_key_exists('continuationBoundarySpace', $value)
            || (!is_bool($boundarySpace) && $boundarySpace !== null)
            || ($boundarySpace === null
                && ($followingId !== null || $followingDigest !== null || $nested !== null))
            || (is_bool($boundarySpace)
                && (!is_string($followingId)
                    || $followingId === ''
                    || !self::isSha256($followingDigest)))
            || !is_array($value['wholeOccurrenceUnionProof'] ?? null)
            || !self::sourceBindingAuthorizedTargetsAreCanonical(
                $value['authorizedTargets'] ?? null
            )) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has an incomplete composite list-layout presentation receipt.'
            );
        }
        foreach ([
            'sourceSignificantDigest',
            'anchorSignificantDigest',
            'layoutVisibleProjectionDigest',
            'anchorVisibleProjectionDigest',
            'finalPageProjectionDigest',
        ] as $digestKey) {
            if (!self::isSha256($value[$digestKey] ?? null)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $markerId
                        . ' has an incomplete composite list-layout presentation receipt.'
                );
            }
        }
        foreach ($globalIndexes as $offset => $globalIndex) {
            if (!is_int($globalIndex)
                || $globalIndex < 0
                || ($offset > 0 && $globalIndex !== $globalIndexes[$offset - 1] + 1)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $markerId
                        . ' has a nonconsecutive composite list-layout presentation receipt.'
                );
            }
        }
        if ($nested !== null
            && (($nested['sourceSha256'] ?? null) !== ($value['sourceSha256'] ?? null)
                || ($nested['sourceOccurrenceId'] ?? null) !== $followingId
                || ($nested['globalSourceIndex'] ?? null)
                    !== $globalIndexes[array_key_last($globalIndexes)] + 1
                || ($nested['page'] ?? null) !== ($value['page'] ?? null)
                || ($nested['stream'] ?? null) !== ($value['stream'] ?? null)
                || ($nested['projectionDigest'] ?? null) !== $followingDigest
                || ($nested['finalPageProjectionDigest'] ?? null)
                    !== ($value['finalPageProjectionDigest'] ?? null)
                || !self::sourceBindingAuthorizedTargetsAreSubset(
                    $value['authorizedTargets'] ?? null,
                    $nested['authorizedTargets'] ?? null
                ))) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has a mismatched nested list presentation-repair receipt.'
            );
        }
        if (!self::receiptDigestMatches($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has a stale composite list-layout presentation receipt.'
            );
        }

        return $value;
    }

    /** @return array<string,mixed>|null */
    private static function normalizedListPresentationOccurrenceReceipt(
        mixed $value,
        string $markerId
    ): ?array {
        if ($value === null) {
            return null;
        }
        $visible = is_array($value) && is_string($value['visibleProjection'] ?? null)
            ? $value['visibleProjection']
            : '';
        if (!is_array($value)
            || ($value['version'] ?? null) !== 1
            || ($value['method'] ?? null)
                !== 'exact-whole-source-list-presentation-occurrence'
            || !self::isSha256($value['sourceSha256'] ?? null)
            || !is_string($value['sourceOccurrenceId'] ?? null)
            || $value['sourceOccurrenceId'] === ''
            || !is_int($value['globalSourceIndex'] ?? null)
            || $value['globalSourceIndex'] < 0
            || !is_int($value['page'] ?? null)
            || $value['page'] < 1
            || !is_int($value['stream'] ?? null)
            || $value['stream'] < 1
            || !self::isSha256($value['projectionDigest'] ?? null)
            || $visible === ''
            || !hash_equals($visible, self::sourceBindingStrictVisibleText($visible))
            || self::significantText($visible) === ''
            || !hash_equals(
                $value['projectionDigest'],
                hash('sha256', self::significantText($visible))
            )
            || !is_string($value['visibleProjectionDigest'] ?? null)
            || !hash_equals(
                $value['visibleProjectionDigest'],
                hash('sha256', $visible)
            )
            || !self::isSha256($value['finalPageProjectionDigest'] ?? null)
            || !self::sourceBindingAuthorizedTargetsAreCanonical(
                $value['authorizedTargets'] ?? null
            )
            || !self::receiptDigestMatches($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $markerId
                    . ' has an incomplete nested list presentation-repair receipt.'
            );
        }

        return $value;
    }

    private static function isSha256(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    /** @param array<string,mixed> $receipt */
    private static function receiptDigestMatches(array $receipt): bool
    {
        if (!self::isSha256($receipt['proofDigest'] ?? null)) {
            return false;
        }
        $payload = $receipt;
        $digest = $payload['proofDigest'];
        unset($payload['proofDigest']);

        return hash_equals($digest, hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        ) ?: ''));
    }

    /** @param list<array<string,mixed>|null> $anchors */
    public static function sourceBindingReceiptAnchorSequence(
        array $marker,
        array $anchors,
        ?array $followingAnchor,
        array $bindingContext,
        array $sequence
    ): ?array {
        $compositeReceipt =
            $marker['semanticStructureProof']['compositeLayoutPresentationReceipt'] ?? null;
        $presentationReceipt =
            $marker['semanticStructureProof']['presentationRepairReceipt'] ?? null;
        if (($compositeReceipt === null && $presentationReceipt === null)
            || ($compositeReceipt !== null && $presentationReceipt !== null)
            || ($compositeReceipt !== null && !is_array($compositeReceipt))
            || ($presentationReceipt !== null && !is_array($presentationReceipt))) {
            return null;
        }

        $significant = is_string($sequence['significant'] ?? null)
            ? $sequence['significant']
            : '';
        $significantBoundaryOffsets =
            is_array($sequence['significantBoundaryOffsets'] ?? null)
                ? $sequence['significantBoundaryOffsets']
                : [];
        $visibleBoundarySpaces = is_array($sequence['visibleBoundarySpaces'] ?? null)
            ? $sequence['visibleBoundarySpaces']
            : [];
        if ($significant === '') {
            return null;
        }

        if (is_array($compositeReceipt)) {
            $presentation = self::sourceBindingExactCompositeListLayoutVisiblePrefix(
                $marker,
                $anchors,
                $compositeReceipt,
                $significant,
                $followingAnchor,
                $bindingContext
            );
            if (!is_array($presentation)) {
                return null;
            }
            $sequence['compositeVisiblePrefix'] = $presentation['visiblePrefix'];
            $sequence['compositeAuthorizedTargets'] = $presentation['authorizedTargets'];
            $sequence['compositeContinuationBoundarySpace'] =
                $presentation['continuationBoundarySpace'];
            $sequence['compositeFollowingVisible'] = $presentation['followingVisible'];

            return $sequence;
        }

        $presentation = self::sourceBindingExactListPresentationRepairPrefix(
            $marker,
            $anchors,
            $presentationReceipt,
            $significant,
            $significantBoundaryOffsets,
            $visibleBoundarySpaces,
            $bindingContext
        );
        if (!is_array($presentation)) {
            return null;
        }
        $sequence['presentationRepairVisiblePrefix'] = $presentation['visiblePrefix'];
        $sequence['presentationRepairAuthorizedTargets'] =
            $presentation['authorizedTargets'];

        return $sequence;
    }

    public static function sourceBindingReceiptSequenceMatchesTarget(
        array $sequence,
        array $target
    ): bool {
        if (is_string($sequence['compositeVisiblePrefix'] ?? null)) {
            return self::sourceBindingCompositeVisiblePrefixMatchesTarget($sequence, $target);
        }
        if (is_string($sequence['presentationRepairVisiblePrefix'] ?? null)) {
            return self::sourceBindingPresentationRepairPrefixMatchesTarget($sequence, $target);
        }

        return false;
    }

    /** @param list<array<string,mixed>|null> $anchors */
    private static function sourceBindingExactListPresentationRepairPrefix(
        array $marker,
        array $anchors,
        array $receipt,
        string $anchorSignificant,
        array $significantBoundaryOffsets,
        array $visibleBoundarySpaces,
        array $bindingContext
    ): ?array {
        $sourceIds = $receipt['sourceOccurrenceIds'] ?? null;
        $globalIndexes = $receipt['globalSourceIndexes'] ?? null;
        $authorizedTargets = $receipt['authorizedTargets'] ?? null;
        $visiblePrefix = is_string($receipt['visiblePrefix'] ?? null)
            ? $receipt['visiblePrefix']
            : '';
        $page = $marker['page'] ?? null;
        $stream = $marker['stream'] ?? null;
        $markerSourceIndex = $marker['sourceIndex'] ?? null;
        $sourceSha = is_string($bindingContext['sourceSha256'] ?? null)
            ? $bindingContext['sourceSha256']
            : '';
        $pageDigests = is_array($bindingContext['finalPageProjectionDigests'] ?? null)
            ? $bindingContext['finalPageProjectionDigests']
            : [];
        if (!is_int($page)
            || !is_int($stream)
            || !is_int($markerSourceIndex)
            || $page < 1
            || $stream < 1
            || $anchorSignificant === ''
            || ($receipt['page'] ?? null) !== $page
            || ($receipt['stream'] ?? null) !== $stream
            || preg_match('/^[a-f0-9]{64}$/D', $sourceSha) !== 1
            || !hash_equals((string) ($receipt['sourceSha256'] ?? ''), $sourceSha)
            || !is_string($pageDigests[$page] ?? null)
            || !hash_equals(
                (string) ($receipt['finalPageProjectionDigest'] ?? ''),
                $pageDigests[$page]
            )
            || !is_array($sourceIds)
            || !array_is_list($sourceIds)
            || count($sourceIds) !== count($anchors)
            || count($sourceIds) < 2
            || count($sourceIds) > 16
            || $sourceIds !== ($marker['semanticStructureProof']['anchorSourceOccurrenceIds'] ?? null)
            || !is_array($globalIndexes)
            || !array_is_list($globalIndexes)
            || count($globalIndexes) !== count($anchors)
            || ($globalIndexes[0] ?? null) !== $markerSourceIndex + 1
            || !hash_equals(
                (string) ($receipt['prefixProjectionDigest'] ?? ''),
                hash('sha256', $anchorSignificant)
            )
            || $visiblePrefix === ''
            || !hash_equals($visiblePrefix, self::sourceBindingStrictVisibleText($visiblePrefix))
            || !hash_equals(self::significantText($visiblePrefix), $anchorSignificant)
            || !hash_equals(
                (string) ($receipt['visiblePrefixDigest'] ?? ''),
                hash('sha256', $visiblePrefix)
            )
            || ($receipt['significantBoundaryOffsets'] ?? null) !== $significantBoundaryOffsets
            || ($receipt['visibleBoundarySpaces'] ?? null) !== $visibleBoundarySpaces
            || !self::sourceBindingVisibleTextHasExactSignificantBoundaries(
                $visiblePrefix,
                $anchorSignificant,
                $significantBoundaryOffsets,
                $visibleBoundarySpaces
            )
            || !self::sourceBindingAuthorizedTargetsAreCanonical($authorizedTargets)) {
            return null;
        }
        foreach ($anchors as $anchorIndex => $anchor) {
            if (!is_array($anchor)
                || ($sourceIds[$anchorIndex] ?? null) !== ($anchor['id'] ?? null)
                || !is_int($anchor['sourceIndex'] ?? null)
                || ($globalIndexes[$anchorIndex] ?? null) !== $anchor['sourceIndex']
                || ($anchorIndex > 0
                    && $globalIndexes[$anchorIndex]
                        !== $globalIndexes[$anchorIndex - 1] + 1)) {
                return null;
            }
        }
        $payload = $receipt;
        unset($payload['proofDigest']);
        if (!is_string($receipt['proofDigest'] ?? null)
            || !hash_equals($receipt['proofDigest'], hash('sha256', json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
            ) ?: ''))) {
            return null;
        }

        return ['visiblePrefix' => $visiblePrefix, 'authorizedTargets' => $authorizedTargets];
    }

    /** @param list<array<string,mixed>|null> $anchors */
    private static function sourceBindingExactCompositeListLayoutVisiblePrefix(
        array $marker,
        array $anchors,
        array $receipt,
        string $anchorSignificant,
        ?array $followingAnchor,
        array $bindingContext
    ): ?array {
        $records = array_merge([$marker], $anchors);
        $sourceIds = $receipt['sourceOccurrenceIds'] ?? null;
        $globalIndexes = $receipt['globalSourceIndexes'] ?? null;
        $unionProof = is_array($receipt['wholeOccurrenceUnionProof'] ?? null)
            ? $receipt['wholeOccurrenceUnionProof']
            : null;
        $proof = is_array($marker['semanticStructureProof'] ?? null)
            ? $marker['semanticStructureProof']
            : null;
        $page = $marker['page'] ?? null;
        $stream = $marker['stream'] ?? null;
        $markerSignificant = (string) ($marker['sourceSignificant'] ?? '');
        $fullSignificant = $markerSignificant . $anchorSignificant;
        $layoutVisible = is_string($receipt['layoutVisibleProjection'] ?? null)
            ? $receipt['layoutVisibleProjection']
            : '';
        $anchorVisible = is_string($receipt['anchorVisibleProjection'] ?? null)
            ? $receipt['anchorVisibleProjection']
            : '';
        $authorizedTargets = is_array($receipt['authorizedTargets'] ?? null)
            ? $receipt['authorizedTargets']
            : [];
        $sourceSha = is_string($bindingContext['sourceSha256'] ?? null)
            ? $bindingContext['sourceSha256']
            : '';
        $pageDigests = is_array($bindingContext['finalPageProjectionDigests'] ?? null)
            ? $bindingContext['finalPageProjectionDigests']
            : [];
        if ($unionProof === null
            || $proof === null
            || !is_int($page)
            || !is_int($stream)
            || $page < 1
            || $stream < 1
            || ($receipt['page'] ?? null) !== $page
            || ($receipt['stream'] ?? null) !== $stream
            || $sourceSha === ''
            || !hash_equals((string) ($receipt['sourceSha256'] ?? ''), $sourceSha)
            || !is_string($pageDigests[$page] ?? null)
            || !hash_equals(
                (string) ($receipt['finalPageProjectionDigest'] ?? ''),
                $pageDigests[$page]
            )
            || ($receipt['listType'] ?? null) !== ($proof['listType'] ?? null)
            || ($receipt['markerOrdinal'] ?? null) !== ($proof['markerOrdinal'] ?? null)
            || !is_array($sourceIds)
            || !is_array($globalIndexes)
            || count($sourceIds) !== count($records)
            || count($globalIndexes) !== count($records)
            || array_slice($sourceIds, 1) !== ($proof['anchorSourceOccurrenceIds'] ?? null)
            || !hash_equals(
                (string) ($receipt['sourceSignificantDigest'] ?? ''),
                hash('sha256', $fullSignificant)
            )
            || !hash_equals(
                (string) ($receipt['anchorSignificantDigest'] ?? ''),
                hash('sha256', $anchorSignificant)
            )
            || !hash_equals(
                (string) ($receipt['layoutVisibleProjectionDigest'] ?? ''),
                hash('sha256', $layoutVisible)
            )
            || !hash_equals(
                (string) ($receipt['anchorVisibleProjectionDigest'] ?? ''),
                hash('sha256', $anchorVisible)
            )
            || !hash_equals(self::significantText($layoutVisible), $fullSignificant)
            || !hash_equals(self::significantText($anchorVisible), $anchorSignificant)
            || !self::sourceBindingAuthorizedTargetsAreCanonical($authorizedTargets)) {
            return null;
        }
        foreach ($records as $recordIndex => $record) {
            if (!is_array($record)
                || ($sourceIds[$recordIndex] ?? null) !== ($record['id'] ?? null)
                || ($record['page'] ?? null) !== $page
                || ($record['stream'] ?? null) !== $stream
                || !is_int($record['sourceIndex'] ?? null)
                || ($globalIndexes[$recordIndex] ?? null) !== $record['sourceIndex']) {
                return null;
            }
        }
        $markerPattern = ($proof['listType'] ?? null) === 'ordered'
            ? '/^' . preg_quote((string) $proof['markerOrdinal'], '/') . '[.)]\s+(.+)$/uD'
            : '/^(?:\*|\x{2022}|\x{25CF}|\x{25AA}|\x{25A0}|\x{2043})\s+(.+)$/uD';
        if (preg_match($markerPattern, $layoutVisible, $match) !== 1
            || !hash_equals($anchorVisible, $match[1])) {
            return null;
        }

        $occurrences = is_array($unionProof['occurrences'] ?? null)
            ? array_values($unionProof['occurrences'])
            : [];
        $proofRanges = is_array($unionProof['ranges'] ?? null)
            ? array_values($unionProof['ranges'])
            : [];
        if (($unionProof['version'] ?? null) !== 1
            || ($unionProof['method'] ?? null) !== 'source-inventory-whole-occurrence-union'
            || ($unionProof['page'] ?? null) !== $page
            || (!is_int($unionProof['layoutStream'] ?? null)
                && ($unionProof['layoutStream'] ?? null) !== null)
            || (is_int($unionProof['layoutStream'] ?? null)
                && $unionProof['layoutStream'] !== $stream)
            || count($occurrences) !== count($records)
            || count($proofRanges) !== count($records)
            || !is_string($unionProof['projectionDigest'] ?? null)
            || !hash_equals(
                $unionProof['projectionDigest'],
                hash('sha256', $fullSignificant)
            )
            || !is_string($unionProof['proofDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $unionProof['proofDigest']) !== 1) {
            return null;
        }
        foreach ($records as $recordIndex => $record) {
            $occurrence = $occurrences[$recordIndex] ?? null;
            $proofRange = $proofRanges[$recordIndex] ?? null;
            $recordSignificant = $recordIndex === 0
                ? $markerSignificant
                : (string) ($record['significant'] ?? '');
            if (!is_array($occurrence)
                || !is_array($proofRange)
                || ($occurrence['sourceIndex'] ?? null) !== ($globalIndexes[$recordIndex] ?? null)
                || ($occurrence['sourceLocalIndex'] ?? null) !== $record['sourceIndex']
                || ($occurrence['page'] ?? null) !== $page
                || ($occurrence['stream'] ?? null) !== $stream
                || ($occurrence['significantBytes'] ?? null) !== strlen($recordSignificant)
                || !is_string($occurrence['significantDigest'] ?? null)
                || !hash_equals(
                    $occurrence['significantDigest'],
                    hash('sha256', $recordSignificant)
                )
                || $proofRange !== [
                    'sourceIndex' => $globalIndexes[$recordIndex],
                    'sourceStart' => 0,
                    'sourceEnd' => strlen($recordSignificant),
                ]) {
                return null;
            }
        }
        $unionPayload = [
            'version' => 1,
            'method' => 'source-inventory-whole-occurrence-union',
            'page' => $unionProof['page'],
            'layoutStream' => $unionProof['layoutStream'],
            'occurrences' => $occurrences,
            'ranges' => $proofRanges,
            'projectionDigest' => $unionProof['projectionDigest'],
        ];
        if (!hash_equals($unionProof['proofDigest'], hash('sha256', json_encode(
            $unionPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: ''))) {
            return null;
        }

        $boundarySpace = $receipt['continuationBoundarySpace'] ?? null;
        $followingReceipt = is_array($receipt['followingPresentationRepairReceipt'] ?? null)
            ? $receipt['followingPresentationRepairReceipt']
            : null;
        $followingVisible = null;
        if (is_bool($boundarySpace)) {
            $followingProjection = is_array($followingAnchor)
                ? (string) ($followingAnchor['projectionText'] ?? '')
                : '';
            $followingSignificant = is_array($followingAnchor)
                ? (string) ($followingAnchor['significant'] ?? '')
                : '';
            if (!is_array($followingAnchor)
                || ($followingAnchor['id'] ?? null)
                    !== ($receipt['followingSourceOccurrenceId'] ?? null)
                || ($followingAnchor['page'] ?? null) !== $page
                || ($followingAnchor['stream'] ?? null) !== $stream
                || !isset(self::OUTPUT_DISPOSITIONS[$followingAnchor['disposition'] ?? ''])
                || is_array($followingAnchor['semanticStructureProof'] ?? null)
                || $followingSignificant === ''
                || !hash_equals(
                    (string) ($followingAnchor['sourceText'] ?? ''),
                    $followingProjection
                )
                || !hash_equals(
                    (string) ($receipt['followingProjectionDigest'] ?? ''),
                    hash('sha256', $followingSignificant)
                )
                || $boundarySpace !== (
                    preg_match('/^[,.;:!?\)\]\}]/u', ltrim($followingProjection)) !== 1
                )) {
                return null;
            }
            $followingVisible = $followingReceipt !== null
                ? self::sourceBindingExactListPresentationOccurrenceVisibleText(
                    $followingAnchor,
                    $followingReceipt,
                    $authorizedTargets,
                    $sourceSha,
                    $pageDigests[$page]
                )
                : self::sourceBindingComparableVisibleText($followingProjection);
            if ($followingVisible === '') {
                return null;
            }
        } elseif ($boundarySpace !== null
            || ($receipt['followingSourceOccurrenceId'] ?? null) !== null
            || ($receipt['followingProjectionDigest'] ?? null) !== null
            || $followingReceipt !== null) {
            return null;
        }

        return [
            'visiblePrefix' => $anchorVisible,
            'authorizedTargets' => $authorizedTargets,
            'continuationBoundarySpace' => $boundarySpace,
            'followingVisible' => $followingVisible,
        ];
    }

    private static function sourceBindingExactListPresentationOccurrenceVisibleText(
        array $followingAnchor,
        array $receipt,
        array $outerTargets,
        string $sourceSha,
        string $pageDigest
    ): ?string {
        $sourceId = is_string($followingAnchor['id'] ?? null) ? $followingAnchor['id'] : '';
        $sourceIndex = $followingAnchor['sourceIndex'] ?? null;
        $page = $followingAnchor['page'] ?? null;
        $stream = $followingAnchor['stream'] ?? null;
        $sourceText = is_string($followingAnchor['sourceText'] ?? null)
            ? $followingAnchor['sourceText']
            : '';
        $projectionText = is_string($followingAnchor['projectionText'] ?? null)
            ? $followingAnchor['projectionText']
            : '';
        $significant = is_string($followingAnchor['significant'] ?? null)
            ? $followingAnchor['significant']
            : '';
        $visible = is_string($receipt['visibleProjection'] ?? null)
            ? $receipt['visibleProjection']
            : '';
        $nestedTargets = $receipt['authorizedTargets'] ?? null;
        if ($sourceId === ''
            || !is_int($sourceIndex)
            || !is_int($page)
            || !is_int($stream)
            || $page < 1
            || $stream < 1
            || $sourceText === ''
            || !hash_equals($sourceText, $projectionText)
            || $significant === ''
            || ($receipt['version'] ?? null) !== 1
            || ($receipt['method'] ?? null)
                !== 'exact-whole-source-list-presentation-occurrence'
            || !hash_equals((string) ($receipt['sourceSha256'] ?? ''), $sourceSha)
            || ($receipt['sourceOccurrenceId'] ?? null) !== $sourceId
            || ($receipt['globalSourceIndex'] ?? null) !== $sourceIndex
            || ($receipt['page'] ?? null) !== $page
            || ($receipt['stream'] ?? null) !== $stream
            || !hash_equals(
                (string) ($receipt['projectionDigest'] ?? ''),
                hash('sha256', $significant)
            )
            || $visible === ''
            || !hash_equals($visible, self::sourceBindingStrictVisibleText($visible))
            || !hash_equals(self::significantText($visible), $significant)
            || !hash_equals(
                (string) ($receipt['visibleProjectionDigest'] ?? ''),
                hash('sha256', $visible)
            )
            || !hash_equals(
                (string) ($receipt['finalPageProjectionDigest'] ?? ''),
                $pageDigest
            )
            || !self::sourceBindingAuthorizedTargetsAreSubset($outerTargets, $nestedTargets)) {
            return null;
        }
        $payload = $receipt;
        unset($payload['proofDigest']);
        if (!is_string($receipt['proofDigest'] ?? null)
            || !hash_equals($receipt['proofDigest'], hash('sha256', json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
            ) ?: ''))) {
            return null;
        }

        return $visible;
    }

    private static function sourceBindingPresentationRepairPrefixMatchesTarget(
        array $sequence,
        array $target
    ): bool {
        $visiblePrefix = is_string($sequence['presentationRepairVisiblePrefix'] ?? null)
            ? $sequence['presentationRepairVisiblePrefix']
            : '';
        $targetVisible = is_string($target['itemStrictVisible'] ?? null)
            ? $target['itemStrictVisible']
            : '';

        return $visiblePrefix !== ''
            && $targetVisible !== ''
            && str_starts_with($targetVisible, $visiblePrefix)
            && self::sourceBindingTargetHasExactVisibleAuthorization(
                $sequence['presentationRepairAuthorizedTargets'] ?? [],
                $target
            );
    }

    private static function sourceBindingCompositeVisiblePrefixMatchesTarget(
        array $sequence,
        array $target
    ): bool {
        $visiblePrefix = is_string($sequence['compositeVisiblePrefix'] ?? null)
            ? $sequence['compositeVisiblePrefix']
            : '';
        $targetVisible = (string) ($target['itemStrictVisible'] ?? '');
        $prefixSignificant = (string) ($sequence['significant'] ?? '');
        $targetSignificant = (string) ($target['itemSignificant'] ?? '');
        $boundarySpace = $sequence['compositeContinuationBoundarySpace'] ?? null;
        if ($visiblePrefix === ''
            || ($target['visibleBlockCount'] ?? 0) !== 1
            || !self::sourceBindingTargetHasExactVisibleAuthorization(
                $sequence['compositeAuthorizedTargets'] ?? [],
                $target
            )
            || !str_starts_with($targetVisible, $visiblePrefix)) {
            return false;
        }
        if (hash_equals($targetSignificant, $prefixSignificant)) {
            return $boundarySpace === null && hash_equals($targetVisible, $visiblePrefix);
        }
        $followingVisible = $sequence['compositeFollowingVisible'] ?? null;

        return is_bool($boundarySpace)
            && is_string($followingVisible)
            && $followingVisible !== ''
            && str_starts_with(
                $targetVisible,
                $visiblePrefix . ($boundarySpace ? ' ' : '') . $followingVisible
            );
    }

    public static function sourceBindingAuthorizedTargetsAreCanonical(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }
        $canonical = [];
        foreach ($value as $target) {
            $significantDigest = is_array($target) ? ($target['significantDigest'] ?? null) : null;
            $strictVisibleDigest = is_array($target) ? ($target['strictVisibleDigest'] ?? null) : null;
            if (!is_string($significantDigest)
                || preg_match('/^[a-f0-9]{64}$/D', $significantDigest) !== 1
                || !is_string($strictVisibleDigest)
                || preg_match('/^[a-f0-9]{64}$/D', $strictVisibleDigest) !== 1
                || count($target) !== 2) {
                return false;
            }
            $canonical[$significantDigest . "\0" . $strictVisibleDigest] = [
                'significantDigest' => $significantDigest,
                'strictVisibleDigest' => $strictVisibleDigest,
            ];
        }
        ksort($canonical, SORT_STRING);

        return $value === array_values($canonical);
    }

    public static function sourceBindingAuthorizedTargetsAreSubset(
        mixed $subset,
        mixed $superset
    ): bool {
        if (!self::sourceBindingAuthorizedTargetsAreCanonical($subset)
            || !self::sourceBindingAuthorizedTargetsAreCanonical($superset)) {
            return false;
        }
        foreach ($subset as $target) {
            if (!in_array($target, $superset, true)) {
                return false;
            }
        }

        return true;
    }

    private static function sourceBindingTargetHasExactVisibleAuthorization(
        mixed $authorizedTargets,
        array $target
    ): bool {
        $significant = is_string($target['itemSignificant'] ?? null)
            ? $target['itemSignificant']
            : '';
        $strictVisible = is_string($target['itemStrictVisible'] ?? null)
            ? $target['itemStrictVisible']
            : '';
        if ($significant === ''
            || $strictVisible === ''
            || !self::sourceBindingAuthorizedTargetsAreCanonical($authorizedTargets)) {
            return false;
        }

        return in_array([
            'significantDigest' => hash('sha256', $significant),
            'strictVisibleDigest' => hash('sha256', $strictVisible),
        ], $authorizedTargets, true);
    }

    private static function sourceBindingVisibleTextHasExactSignificantBoundaries(
        string $visible,
        string $significantPrefix,
        array $boundaryOffsets,
        array $boundarySpaces
    ): bool {
        if ($significantPrefix === '' || count($boundaryOffsets) !== count($boundarySpaces)) {
            return false;
        }
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($visible, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $visible = $normalized;
            }
        }
        $characters = preg_split('//u', $visible, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            return false;
        }
        $significantOffset = 0;
        $ignored = [];
        foreach ($characters as $character) {
            $part = self::significantText($character);
            if ($part === '') {
                $ignored[$significantOffset] = ($ignored[$significantOffset] ?? '') . $character;
                continue;
            }
            $significantOffset += strlen($part);
            if ($significantOffset >= strlen($significantPrefix)) {
                break;
            }
        }
        $previous = 0;
        foreach ($boundaryOffsets as $index => $offset) {
            if (!is_int($offset)
                || $offset <= $previous
                || $offset >= strlen($significantPrefix)
                || !is_bool($boundarySpaces[$index] ?? null)
                || !hash_equals(
                    $boundarySpaces[$index] ? ' ' : '',
                    $ignored[$offset] ?? ''
                )) {
                return false;
            }
            $previous = $offset;
        }

        return $significantOffset >= strlen($significantPrefix);
    }

    public static function sourceBindingComparableVisibleText(string $text): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return trim(preg_replace('/[\s\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '');
    }

    public static function sourceBindingStrictVisibleText(string $text): string
    {
        if ($text !== '' && preg_match('//u', $text) !== 1) {
            return '';
        }
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return trim($text);
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
}
