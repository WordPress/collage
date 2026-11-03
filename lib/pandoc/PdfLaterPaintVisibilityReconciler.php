<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Reconcile the extractor's deliberately conservative later-paint risk only
 * after the final source and media graphs prove an exact visual disposition.
 *
 * This class does not reinterpret PDF painting. It validates immutable facts
 * produced by independent passes: one source occurrence/range, one later
 * Image paint, one live image placement, one whole-source artifact proof, and
 * one live output edge for the complete display counterpart. Any incomplete,
 * duplicated, truncated, stale, or internally inconsistent inventory fails
 * closed and leaves raw visibility incomplete.
 */
final class PdfLaterPaintVisibilityReconciler
{
    private const MAX_RISKS = 64;
    private const POLICY = 'exact-whole-source-later-image-artifact-v1';

    /**
     * @param array<string,mixed> $visibility
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param array<string,mixed> $sourceDisposition
     * @param array<string,array<string,mixed>|string> $boundDispositions
     * @param list<array<string,mixed>> $mediaProofs
     * @param list<array<string,mixed>> $imagePlacements
     * @param list<AstNode> $blocks
     * @return array<string,mixed>
     */
    public static function reconcile(
        string $sourceSha256,
        array $visibility,
        array $sourceLineItems,
        array $sourceDisposition,
        array $boundDispositions,
        array $mediaProofs,
        int $mediaProofTruncatedCount,
        array $imagePlacements,
        array $blocks
    ): array {
        $rawComplete = ($visibility['complete'] ?? false) === true;
        if ($rawComplete) {
            return PdfRawCompleteVisibilityReconciliation::result();
        }
        if (!self::isDigest($sourceSha256)) {
            return self::result(false, false, false, 0, [], [], 'invalid-source-digest');
        }
        if (($visibility['policy'] ?? null) !== 'visible-painted-text-v1') {
            return self::result(false, false, false, 0, [], [], 'unsupported-raw-visibility-policy');
        }

        $risks = $visibility['laterPaintRisks'] ?? null;
        $riskCount = $visibility['laterPaintRiskCount'] ?? null;
        if (!is_array($risks)
            || !array_is_list($risks)
            || !is_int($riskCount)
            || $riskCount < 1
            || $riskCount > self::MAX_RISKS
            || count($risks) !== $riskCount
            || ($visibility['laterPaintRiskRecordedCount'] ?? null) !== $riskCount
            || ($visibility['laterPaintRiskUnboundCount'] ?? null) !== 0
            || ($visibility['laterPaintRiskTruncatedCount'] ?? null) !== 0) {
            return self::result(false, false, false, max(0, is_int($riskCount) ? $riskCount : 0), [], [], 'incomplete-risk-inventory');
        }
        if (($visibility['unresolvedRuns'] ?? null) !== 0
            || ($visibility['unresolvedOcclusionRiskRuns'] ?? null) !== $riskCount
            || ($visibility['unresolvedReasons'] ?? null) !== ['later-paint-occlusion']
            || ($visibility['unresolvedReasonCounts'] ?? null) !== [
                'later-paint-occlusion' => $riskCount,
            ]) {
            return self::result(false, false, false, $riskCount, [], [], 'non-reconcilable-raw-visibility-state');
        }
        $riskInventoryDigest = $visibility['laterPaintRisksDigest'] ?? null;
        if (!self::isDigest($riskInventoryDigest)
            || !hash_equals($riskInventoryDigest, self::digest($risks, true))) {
            return self::result(false, false, false, $riskCount, [], [], 'risk-inventory-digest-mismatch');
        }
        if (!self::visibilityPageInventoryIsComplete($visibility, $riskCount)) {
            return self::result(false, false, false, $riskCount, [], [], 'risk-page-inventory-mismatch');
        }
        if (!array_is_list($sourceLineItems)) {
            return self::result(false, false, false, $riskCount, [], [], 'invalid-source-occurrence-inventory');
        }

        $sourceIndexesById = [];
        $duplicateSourceIds = [];
        foreach ($sourceLineItems as $sourceIndex => $sourceItem) {
            if (!is_array($sourceItem)) {
                continue;
            }
            $sourceId = is_string($sourceItem['id'] ?? null) ? $sourceItem['id'] : '';
            if ($sourceId === '') {
                continue;
            }
            if (isset($sourceIndexesById[$sourceId])) {
                $duplicateSourceIds[$sourceId] = true;
                unset($sourceIndexesById[$sourceId]);
                continue;
            }
            if (!isset($duplicateSourceIds[$sourceId])) {
                $sourceIndexesById[$sourceId] = $sourceIndex;
            }
        }

        $validatedRisks = [];
        $riskIds = [];
        $riskDigests = [];
        $riskSourceRanges = [];
        $riskOperationPairs = [];
        foreach ($risks as $risk) {
            if (!is_array($risk) || !self::laterPaintRiskIdentityIsValid($risk)) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'invalid-risk-record');
            }
            $riskId = $risk['id'];
            $riskDigest = $risk['riskDigest'];
            $sourceIndex = $risk['sourceOccurrenceIndex'];
            $sourceRangeKey = $sourceIndex . "\0"
                . $risk['sourceRange']['start'] . "\0" . $risk['sourceRange']['end'];
            $operationKey = $risk['page'] . "\0" . $risk['textOperation']
                . "\0" . $risk['paintOperation'];
            if (isset($riskIds[$riskId])
                || isset($riskDigests[$riskDigest])
                || isset($riskSourceRanges[$sourceRangeKey])
                || isset($riskOperationPairs[$operationKey])) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'duplicate-risk-record');
            }
            if (!hash_equals($sourceSha256, $risk['sourceSha256'])
                || $risk['paintOperation'] <= $risk['textOperation']
                || !self::boundsIntersect($risk['textBounds'], $risk['paintBounds'])) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'invalid-later-paint-evidence');
            }
            $sourceItem = $sourceLineItems[$sourceIndex] ?? null;
            $sourceProjection = is_array($sourceItem)
                ? self::sourceOccurrenceComparableText((string) ($sourceItem['text'] ?? ''))
                : '';
            $sourceId = is_array($sourceItem) && is_string($sourceItem['id'] ?? null)
                ? $sourceItem['id']
                : '';
            if (!is_array($sourceItem)
                || $sourceId === ''
                || isset($duplicateSourceIds[$sourceId])
                || ($sourceIndexesById[$sourceId] ?? null) !== $sourceIndex
                || !is_int($sourceItem['page'] ?? null)
                || !is_int($sourceItem['stream'] ?? null)
                || $sourceItem['page'] !== $risk['page']
                || $sourceItem['stream'] !== $risk['sourceStream']
                || $sourceProjection === ''
                || $risk['sourceRange'] !== ['start' => 0, 'end' => strlen($sourceProjection)]
                || !hash_equals(
                    $risk['sourceProjectionDigest'],
                    hash('sha256', $sourceProjection)
                )) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'risk-source-occurrence-mismatch');
            }
            $riskIds[$riskId] = true;
            $riskDigests[$riskDigest] = true;
            $riskSourceRanges[$sourceRangeKey] = true;
            $riskOperationPairs[$operationKey] = true;
            $validatedRisks[$riskId] = [
                'risk' => $risk,
                'sourceId' => $sourceId,
                'sourceIndex' => $sourceIndex,
                'sourceItem' => $sourceItem,
                'sourceProjection' => $sourceProjection,
            ];
        }

        $sourceEdgesByOccurrence = self::validatedSourceEdges($sourceDisposition);
        if ($sourceEdgesByOccurrence === null) {
            return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'invalid-source-edge-ledger');
        }
        if (!array_is_list($mediaProofs)
            || count($mediaProofs) !== $riskCount
            || count($mediaProofs) > self::MAX_RISKS
            || $mediaProofTruncatedCount !== 0) {
            return self::result(false, false, false, $riskCount, array_keys($riskIds), [], 'incomplete-media-proof-inventory');
        }

        $proofsByRiskId = [];
        $proofDigests = [];
        $proofArtifactIds = [];
        foreach ($mediaProofs as $proof) {
            if (!is_array($proof) || !self::mediaProofIdentityIsValid($proof)) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), array_keys($proofDigests), 'invalid-media-proof');
            }
            $proofDigest = $proof['proofDigest'];
            $riskId = $proof['laterPaintRiskId'];
            $artifactId = $proof['artifactSourceOccurrenceId'];
            if (isset($proofDigests[$proofDigest])
                || isset($proofsByRiskId[$riskId])
                || isset($proofArtifactIds[$artifactId])) {
                return self::result(false, false, false, $riskCount, array_keys($riskIds), array_keys($proofDigests), 'duplicate-media-proof');
            }
            $proofDigests[$proofDigest] = true;
            $proofsByRiskId[$riskId] = $proof;
            $proofArtifactIds[$artifactId] = true;
        }

        $liveGraph = self::validatedLiveOutputGraph($blocks);
        if ($liveGraph === null) {
            return self::result(false, false, false, $riskCount, array_keys($riskIds), array_keys($proofDigests), 'invalid-live-output-graph');
        }

        $reconciledRiskIds = [];
        $usedPlacementIds = [];
        $usedCounterpartIds = [];
        foreach ($validatedRisks as $riskId => $validatedRisk) {
            $risk = $validatedRisk['risk'];
            $sourceId = $validatedRisk['sourceId'];
            $sourceIndex = $validatedRisk['sourceIndex'];
            $sourceProjection = $validatedRisk['sourceProjection'];
            $proof = $proofsByRiskId[$riskId] ?? null;
            if (!is_array($proof)
                || !hash_equals($sourceSha256, $proof['sourceSha256'])
                || $proof['page'] !== $risk['page']
                || $proof['artifactSourceOccurrenceId'] !== $sourceId
                || !hash_equals($proof['artifactProjectionDigest'], $risk['sourceProjectionDigest'])
                || !hash_equals($proof['laterPaintRiskDigest'], $risk['riskDigest'])
                || $proof['laterPaintOperation'] !== $risk['paintOperation']
                || $proof['laterPaintOperator'] !== $risk['paintOperator']
                || $proof['laterPaintResource'] !== $risk['paintResource']
                || $proof['laterPaintObject'] !== $risk['paintObject']) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'risk-media-proof-mismatch');
            }

            $artifactDisposition = $boundDispositions[$sourceId] ?? null;
            $artifactEvidence = is_array($artifactDisposition)
                && is_array($artifactDisposition['evidence'] ?? null)
                    ? $artifactDisposition['evidence']
                    : null;
            $artifactMapping = is_array($artifactDisposition)
                && is_array($artifactDisposition['sourceMapping'] ?? null)
                    ? $artifactDisposition['sourceMapping']
                    : null;
            if (!is_array($artifactDisposition)
                || ($artifactDisposition['disposition'] ?? null) !== 'artifact'
                || !is_string($artifactDisposition['reason'] ?? null)
                || trim($artifactDisposition['reason']) === ''
                || !is_array($artifactEvidence)
                || !self::artifactEvidenceMatchesRisk(
                    $artifactEvidence,
                    $sourceSha256,
                    $sourceId,
                    $sourceIndex,
                    $risk,
                    $sourceProjection
                )
                || !is_array($artifactMapping)
                || ($artifactMapping['status'] ?? null) !== 'disposition'
                || ($artifactMapping['mappingMode'] ?? null) !== 'explicit-disposition'
                || ($artifactMapping['destinationNodeIds'] ?? []) !== []
                || ($artifactMapping['destinationInlineIds'] ?? []) !== []
                || !hash_equals($proof['artifactProofDigest'], $artifactEvidence['proofDigest'])) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'invalid-artifact-disposition-proof');
            }
            $artifactEdge = $sourceEdgesByOccurrence[$sourceId] ?? null;
            if (!is_array($artifactEdge)
                || $artifactEdge['page'] !== $risk['page']
                || $artifactEdge['disposition'] !== 'artifact'
                || $artifactEdge['target'] !== 'disposition'
                || $artifactEdge['mappingMode'] !== 'explicit-disposition'
                || $artifactEdge['destinationNodeIds'] !== []
                || $artifactEdge['destinationInlineIds'] !== []) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'artifact-source-edge-mismatch');
            }

            $counterpartEvidence = $artifactEvidence['completeDisplayCounterpart'];
            $counterpartId = $counterpartEvidence['sourceOccurrenceId'];
            $counterpartIndex = $sourceIndexesById[$counterpartId] ?? null;
            $counterpartItem = is_int($counterpartIndex)
                ? ($sourceLineItems[$counterpartIndex] ?? null)
                : null;
            $counterpartProjection = is_array($counterpartItem)
                ? self::sourceOccurrenceComparableText((string) ($counterpartItem['text'] ?? ''))
                : '';
            if (!is_int($counterpartIndex)
                || isset($duplicateSourceIds[$counterpartId])
                || !is_array($counterpartItem)
                || $counterpartEvidence['sourceIndex'] !== $counterpartIndex
                || $counterpartEvidence['sourceLocalIndex'] !== $counterpartIndex
                || $counterpartEvidence['page'] !== ($counterpartItem['page'] ?? null)
                || $counterpartEvidence['stream'] !== ($counterpartItem['stream'] ?? null)
                || $counterpartProjection === ''
                || !hash_equals(
                    $counterpartEvidence['projectionDigest'],
                    hash('sha256', $counterpartProjection)
                )
                || $proof['counterpartSourceOccurrenceId'] !== $counterpartId
                || !hash_equals(
                    $proof['counterpartProjectionDigest'],
                    hash('sha256', $counterpartProjection)
                )) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'counterpart-source-occurrence-mismatch');
            }
            if (isset($usedCounterpartIds[$counterpartId])) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'duplicate-counterpart-claim');
            }

            $counterpartDisposition = $boundDispositions[$counterpartId] ?? null;
            $counterpartMapping = is_array($counterpartDisposition)
                && is_array($counterpartDisposition['sourceMapping'] ?? null)
                    ? $counterpartDisposition['sourceMapping']
                    : null;
            $destinationNodeId = $proof['counterpartDestinationNodeId'];
            if (!is_array($counterpartDisposition)
                || !in_array(
                    $counterpartDisposition['disposition'] ?? null,
                    ['emitted', 'boundary-repair', 'semantic-structure'],
                    true
                )
                || !is_array($counterpartMapping)
                || ($counterpartMapping['status'] ?? null) !== 'output'
                || !in_array(
                    $counterpartMapping['mappingMode'] ?? null,
                    ['exact-sequence', 'exact-authorized-scope', 'exact-semantic-list-marker'],
                    true
                )
                || ($counterpartMapping['destinationNodeIds'] ?? null) !== [$destinationNodeId]
                || !is_array($counterpartMapping['destinationInlineIds'] ?? null)
                || !array_is_list($counterpartMapping['destinationInlineIds'])) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'invalid-counterpart-output-mapping');
            }
            $counterpartEdge = $sourceEdgesByOccurrence[$counterpartId] ?? null;
            if (!is_array($counterpartEdge)
                || $counterpartEdge['page'] !== $proof['page']
                || $counterpartEdge['target'] !== 'output'
                || $counterpartEdge['disposition'] !== $counterpartDisposition['disposition']
                || $counterpartEdge['mappingMode'] !== $counterpartMapping['mappingMode']
                || $counterpartEdge['destinationNodeIds'] !== [$destinationNodeId]
                || $counterpartEdge['destinationInlineIds'] !== $counterpartMapping['destinationInlineIds']) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'counterpart-source-edge-mismatch');
            }
            $destination = $liveGraph['nodesById'][$destinationNodeId] ?? null;
            if (!is_array($destination)
                || $destination['depth'] !== 0
                || ($liveGraph['sourceClaimCounts'][$counterpartId] ?? 0) !== 1
                || !isset($destination['sourceIds'][$counterpartId])
                || !self::nodeHasWholeSourceEdge(
                    $destination['node'],
                    $counterpartId,
                    strlen($counterpartProjection)
                )) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'live-counterpart-edge-mismatch');
            }

            $matchingPlacements = [];
            foreach ($imagePlacements as $placement) {
                if (!is_array($placement)
                    || ($placement['id'] ?? null) !== $proof['laterPaintPlacementId']
                    || !self::placementMatchesRisk($placement, $risk)
                    || !hash_equals(
                        $proof['laterPaintPlacementDigest'],
                        self::placementDigest($placement)
                    )) {
                    continue;
                }
                $matchingPlacements[] = $placement;
            }
            $placementId = $proof['laterPaintPlacementId'];
            if (count($matchingPlacements) !== 1 || isset($usedPlacementIds[$placementId])) {
                return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'later-paint-placement-mismatch');
            }

            $usedPlacementIds[$placementId] = true;
            $usedCounterpartIds[$counterpartId] = true;
            $reconciledRiskIds[] = $riskId;
        }

        if (count($reconciledRiskIds) !== $riskCount
            || count($proofsByRiskId) !== $riskCount
            || count($usedPlacementIds) !== $riskCount) {
            return self::result(false, false, false, $riskCount, $reconciledRiskIds, array_keys($proofDigests), 'unreconciled-later-paint-risk');
        }

        return self::result(
            false,
            true,
            true,
            $riskCount,
            $reconciledRiskIds,
            array_keys($proofDigests),
            null
        );
    }

    /** @param array<string,mixed> $visibility */
    private static function visibilityPageInventoryIsComplete(array $visibility, int $riskCount): bool
    {
        $pages = $visibility['pages'] ?? null;
        if (!is_array($pages) || !array_is_list($pages)) {
            return false;
        }
        $seenPages = [];
        $pageRisks = 0;
        foreach ($pages as $page) {
            if (!is_array($page)
                || !is_int($page['page'] ?? null)
                || $page['page'] < 1
                || isset($seenPages[$page['page']])
                || !is_int($page['unresolvedRuns'] ?? null)
                || $page['unresolvedRuns'] !== 0
                || !is_int($page['unresolvedOcclusionRiskRuns'] ?? null)
                || $page['unresolvedOcclusionRiskRuns'] < 0) {
                return false;
            }
            $seenPages[$page['page']] = true;
            $pageRisks += $page['unresolvedOcclusionRiskRuns'];
        }

        return $pageRisks === $riskCount;
    }

    /** @param array<string,mixed> $risk */
    private static function laterPaintRiskIdentityIsValid(array $risk): bool
    {
        if (array_keys($risk) !== [
            'id',
            'version',
            'sourceSha256',
            'page',
            'sourceOccurrenceIndex',
            'sourceStream',
            'sourceRange',
            'sourceProjectionDigest',
            'textOperation',
            'textBounds',
            'paintOperation',
            'paintStream',
            'paintOperator',
            'paintResource',
            'paintObject',
            'paintSubtype',
            'paintBounds',
            'riskDigest',
        ]
            || ($risk['version'] ?? null) !== 1
            || !self::isDigest($risk['sourceSha256'] ?? null)
            || !is_int($risk['page'] ?? null)
            || $risk['page'] < 1
            || !is_int($risk['sourceOccurrenceIndex'] ?? null)
            || $risk['sourceOccurrenceIndex'] < 0
            || !is_int($risk['sourceStream'] ?? null)
            || $risk['sourceStream'] < 1
            || !is_array($risk['sourceRange'] ?? null)
            || array_keys($risk['sourceRange']) !== ['start', 'end']
            || !is_int($risk['sourceRange']['start'] ?? null)
            || !is_int($risk['sourceRange']['end'] ?? null)
            || $risk['sourceRange']['start'] < 0
            || $risk['sourceRange']['end'] <= $risk['sourceRange']['start']
            || !self::isDigest($risk['sourceProjectionDigest'] ?? null)
            || !is_int($risk['textOperation'] ?? null)
            || $risk['textOperation'] < 0
            || !self::boundsAreFinite($risk['textBounds'] ?? null)
            || !is_int($risk['paintOperation'] ?? null)
            || $risk['paintOperation'] < 0
            || !is_int($risk['paintStream'] ?? null)
            || $risk['paintStream'] < 1
            || ($risk['paintOperator'] ?? null) !== 'Do'
            || !is_string($risk['paintResource'] ?? null)
            || $risk['paintResource'] === ''
            || !is_int($risk['paintObject'] ?? null)
            || $risk['paintObject'] < 1
            || ($risk['paintSubtype'] ?? null) !== 'Image'
            || !self::boundsAreFinite($risk['paintBounds'] ?? null)
            || !self::isDigest($risk['riskDigest'] ?? null)) {
            return false;
        }
        $payload = $risk;
        $id = array_shift($payload);
        $digest = array_pop($payload);
        $expectedDigest = self::digest($payload, true);

        return is_string($id)
            && hash_equals('pdf-later-paint-risk-' . substr($expectedDigest, 0, 32), $id)
            && is_string($digest)
            && hash_equals($expectedDigest, $digest);
    }

    /** @return array<string,array<string,mixed>>|null */
    private static function validatedSourceEdges(array $ledger): ?array
    {
        $edges = $ledger['sourceEdges'] ?? null;
        if (($ledger['version'] ?? null) !== 2
            || ($ledger['sourceEdgeMappingComplete'] ?? false) !== true
            || !is_array($edges)
            || !array_is_list($edges)
            || ($ledger['sourceEdgeCount'] ?? null) !== count($edges)
            || ($ledger['sourceOccurrenceCount'] ?? null) !== count($edges)
            || !self::isDigest($ledger['sourceEdgeDigest'] ?? null)
            || !hash_equals($ledger['sourceEdgeDigest'], self::streamingListDigest($edges))) {
            return null;
        }
        $bySource = [];
        $edgeIds = [];
        foreach ($edges as $edge) {
            if (!is_array($edge) || !self::sourceEdgeIdentityIsValid($edge)) {
                return null;
            }
            $sourceId = $edge['sourceOccurrenceId'];
            if (isset($bySource[$sourceId]) || isset($edgeIds[$edge['id']])) {
                return null;
            }
            $bySource[$sourceId] = $edge;
            $edgeIds[$edge['id']] = true;
        }

        return $bySource;
    }

    /** @param array<string,mixed> $edge */
    private static function sourceEdgeIdentityIsValid(array $edge): bool
    {
        if (array_keys($edge) !== [
            'id',
            'sourceOccurrenceId',
            'page',
            'disposition',
            'target',
            'mappingMode',
            'destinationNodeIds',
            'destinationInlineIds',
            'orderScopeId',
        ]
            || !is_string($edge['id'] ?? null)
            || !is_string($edge['sourceOccurrenceId'] ?? null)
            || $edge['sourceOccurrenceId'] === ''
            || !is_int($edge['page'] ?? null)
            || $edge['page'] < 1
            || !is_string($edge['disposition'] ?? null)
            || !is_string($edge['target'] ?? null)
            || !is_string($edge['mappingMode'] ?? null)
            || !self::uniqueStringList($edge['destinationNodeIds'] ?? null)
            || !self::uniqueStringList($edge['destinationInlineIds'] ?? null)
            || (($edge['orderScopeId'] ?? null) !== null
                && !is_string($edge['orderScopeId']))) {
            return false;
        }
        $identity = $edge;
        $id = array_shift($identity);
        $expectedId = 'pdf-source-edge-' . substr(self::digest($identity), 0, 32);

        return is_string($id) && hash_equals($expectedId, $id);
    }

    /** @param array<string,mixed> $proof */
    private static function mediaProofIdentityIsValid(array $proof): bool
    {
        if (array_keys($proof) !== [
            'version',
            'method',
            'sourceSha256',
            'page',
            'artifactSourceOccurrenceId',
            'artifactProjectionDigest',
            'counterpartSourceOccurrenceId',
            'counterpartProjectionDigest',
            'counterpartDestinationNodeId',
            'laterPaintRiskId',
            'laterPaintRiskDigest',
            'laterPaintOperation',
            'laterPaintOperator',
            'laterPaintResource',
            'laterPaintObject',
            'laterPaintPlacementId',
            'laterPaintPlacementDigest',
            'artifactProofDigest',
            'proofDigest',
        ]
            || ($proof['version'] ?? null) !== 2
            || ($proof['method'] ?? null)
                !== 'whole-source-clipped-display-artifact-media-anchor'
            || !self::isDigest($proof['sourceSha256'] ?? null)
            || !is_int($proof['page'] ?? null)
            || $proof['page'] < 1
            || !self::nonEmptyString($proof['artifactSourceOccurrenceId'] ?? null)
            || !self::isDigest($proof['artifactProjectionDigest'] ?? null)
            || !self::nonEmptyString($proof['counterpartSourceOccurrenceId'] ?? null)
            || $proof['counterpartSourceOccurrenceId'] === $proof['artifactSourceOccurrenceId']
            || !self::isDigest($proof['counterpartProjectionDigest'] ?? null)
            || !self::nonEmptyString($proof['counterpartDestinationNodeId'] ?? null)
            || !self::nonEmptyString($proof['laterPaintRiskId'] ?? null)
            || !self::isDigest($proof['laterPaintRiskDigest'] ?? null)
            || !is_int($proof['laterPaintOperation'] ?? null)
            || $proof['laterPaintOperation'] < 0
            || ($proof['laterPaintOperator'] ?? null) !== 'Do'
            || !self::nonEmptyString($proof['laterPaintResource'] ?? null)
            || !is_int($proof['laterPaintObject'] ?? null)
            || $proof['laterPaintObject'] < 1
            || !self::nonEmptyString($proof['laterPaintPlacementId'] ?? null)
            || !self::isDigest($proof['laterPaintPlacementDigest'] ?? null)
            || !self::isDigest($proof['artifactProofDigest'] ?? null)
            || !self::isDigest($proof['proofDigest'] ?? null)) {
            return false;
        }
        $payload = $proof;
        $digest = array_pop($payload);

        return is_string($digest) && hash_equals($digest, self::digest($payload, true));
    }

    /** @param array<string,mixed> $evidence @param array<string,mixed> $risk */
    private static function artifactEvidenceMatchesRisk(
        array $evidence,
        string $sourceSha256,
        string $sourceId,
        int $sourceIndex,
        array $risk,
        string $sourceProjection
    ): bool {
        $proofDigest = $evidence['proofDigest'] ?? null;
        $payload = $evidence;
        unset($payload['proofDigest']);
        $range = [
            'sourceIndex' => $sourceIndex,
            'sourceStart' => 0,
            'sourceEnd' => strlen($sourceProjection),
        ];
        $counterpart = $evidence['completeDisplayCounterpart'] ?? null;
        if (($evidence['version'] ?? null) !== 1
            || ($evidence['method'] ?? null) !== 'whole-source-clipped-display-artifact'
            || !self::isDigest($proofDigest)
            || !hash_equals($proofDigest, self::digest($payload, true))
            || ($evidence['sourceSha256'] ?? null) !== $sourceSha256
            || ($evidence['sourceOccurrenceId'] ?? null) !== $sourceId
            || ($evidence['sourceIndex'] ?? null) !== $sourceIndex
            || ($evidence['sourceLocalIndex'] ?? null) !== $sourceIndex
            || ($evidence['page'] ?? null) !== $risk['page']
            || ($evidence['stream'] ?? null) !== $risk['sourceStream']
            || ($evidence['range'] ?? null) !== $range
            || ($evidence['significantBytes'] ?? null) !== strlen($sourceProjection)
            || ($evidence['projectionDigest'] ?? null) !== $risk['sourceProjectionDigest']
            || !self::isDigest($evidence['wholeOccurrenceProofDigest'] ?? null)
            || !self::boundsAreFinite($evidence['sourceBounds'] ?? null)
            || !self::boundsAreFinite($evidence['layoutBounds'] ?? null)
            || !self::boundsMatch($evidence['sourceBounds'], $evidence['layoutBounds'], 0.01)
            || !is_int($evidence['significantBytes'] ?? null)
            || !is_numeric($evidence['pageMedianFontSize'] ?? null)
            || !is_numeric($evidence['fontSize'] ?? null)
            || !is_numeric($evidence['fontSizeRatio'] ?? null)
            || !is_finite((float) $evidence['pageMedianFontSize'])
            || !is_finite((float) $evidence['fontSize'])
            || !is_finite((float) $evidence['fontSizeRatio'])
            || (float) $evidence['fontSize'] < max(
                18.0,
                (float) $evidence['pageMedianFontSize'] * 2.20
            )
            || !is_array($counterpart)
            || array_keys($counterpart) !== [
                'sourceOccurrenceId',
                'sourceIndex',
                'sourceLocalIndex',
                'page',
                'stream',
                'projectionDigest',
                'matchedTokenOffset',
                'matchedTokenDigest',
            ]
            || !self::nonEmptyString($counterpart['sourceOccurrenceId'] ?? null)
            || !is_int($counterpart['sourceIndex'] ?? null)
            || $counterpart['sourceIndex'] < 0
            || !is_int($counterpart['sourceLocalIndex'] ?? null)
            || $counterpart['sourceLocalIndex'] < 0
            || !is_int($counterpart['page'] ?? null)
            || $counterpart['page'] < 1
            || !is_int($counterpart['stream'] ?? null)
            || $counterpart['stream'] < 1
            || !self::isDigest($counterpart['projectionDigest'] ?? null)
            || !is_int($counterpart['matchedTokenOffset'] ?? null)
            || $counterpart['matchedTokenOffset'] < 0
            || !self::isDigest($counterpart['matchedTokenDigest'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * @param list<AstNode> $blocks
     * @return array{nodesById:array<string,array{node:AstNode,depth:int,sourceIds:array<string,true>}>,sourceClaimCounts:array<string,int>}|null
     */
    private static function validatedLiveOutputGraph(array $blocks): ?array
    {
        if (!array_is_list($blocks)) {
            return null;
        }
        $nodesById = [];
        $duplicateNodeIds = [];
        $sourceClaimCounts = [];
        $walk = function (AstNode $node, int $depth) use (
            &$walk,
            &$nodesById,
            &$duplicateNodeIds,
            &$sourceClaimCounts
        ): bool {
            // Top-level source bindings have a public, independently
            // recomputable identity. Nested bindings deliberately use a
            // path-scoped inline identity, so only inventory their IDs here;
            // a replay of the requested top-level ID is still caught below.
            $validated = $depth === 0 ? self::validatedNodeSourceIds($node) : [];
            if ($validated === false) {
                return false;
            }
            $sourceSet = array_fill_keys($validated, true);
            if ($depth === 0) {
                foreach ($validated as $sourceId) {
                    $sourceClaimCounts[$sourceId] = ($sourceClaimCounts[$sourceId] ?? 0) + 1;
                }
            }
            $nodeId = $node->attr('sourceNodeId');
            if (is_string($nodeId) && $nodeId !== '') {
                if (isset($nodesById[$nodeId])) {
                    $duplicateNodeIds[$nodeId] = true;
                    unset($nodesById[$nodeId]);
                } elseif (!isset($duplicateNodeIds[$nodeId])) {
                    $nodesById[$nodeId] = [
                        'node' => $node,
                        'depth' => $depth,
                        'sourceIds' => $sourceSet,
                    ];
                }
            }
            foreach ($node->children as $child) {
                if (!$walk($child, $depth + 1)) {
                    return false;
                }
            }

            return true;
        };
        foreach ($blocks as $block) {
            if (!($block instanceof AstNode) || !$walk($block, 0)) {
                return null;
            }
        }
        if ($duplicateNodeIds !== []) {
            return null;
        }

        return ['nodesById' => $nodesById, 'sourceClaimCounts' => $sourceClaimCounts];
    }

    /** @return list<string>|false */
    private static function validatedNodeSourceIds(AstNode $node): array|false
    {
        $hasNodeId = array_key_exists('sourceNodeId', $node->attrs);
        $hasIds = array_key_exists('sourceLineIds', $node->attrs);
        $hasEdges = array_key_exists('sourceLineEdges', $node->attrs);
        if (!$hasNodeId && !$hasIds && !$hasEdges) {
            return [];
        }
        $nodeId = $node->attr('sourceNodeId');
        $ids = $node->attr('sourceLineIds');
        $edges = $node->attr('sourceLineEdges');
        if (!$hasNodeId
            || !$hasIds
            || !$hasEdges
            || !self::nonEmptyString($nodeId)
            || !self::uniqueStringList($ids)
            || $ids === []
            || !is_array($edges)
            || !array_is_list($edges)
            || $edges === []) {
            return false;
        }
        $derivedIds = [];
        $seenIds = [];
        $seenEdges = [];
        foreach ($edges as $edge) {
            if (!is_array($edge)
                || array_keys($edge) !== ['sourceLineId', 'startByte', 'endByte']
                || !self::nonEmptyString($edge['sourceLineId'] ?? null)
                || !is_int($edge['startByte'] ?? null)
                || !is_int($edge['endByte'] ?? null)
                || $edge['startByte'] < 0
                || $edge['endByte'] <= $edge['startByte']) {
                return false;
            }
            $edgeKey = $edge['sourceLineId'] . "\0"
                . $edge['startByte'] . "\0" . $edge['endByte'];
            if (isset($seenEdges[$edgeKey])) {
                return false;
            }
            $seenEdges[$edgeKey] = true;
            if (!isset($seenIds[$edge['sourceLineId']])) {
                $seenIds[$edge['sourceLineId']] = true;
                $derivedIds[] = $edge['sourceLineId'];
            }
        }
        if ($ids !== $derivedIds) {
            return false;
        }
        $identity = ['type' => $node->type, 'sourceLineEdges' => $edges];
        $expectedId = 'pdf-source-node-' . substr(self::digest($identity), 0, 32);

        return hash_equals($expectedId, $nodeId) ? $derivedIds : false;
    }

    private static function nodeHasWholeSourceEdge(AstNode $node, string $sourceId, int $bytes): bool
    {
        $matching = [];
        foreach ($node->attr('sourceLineEdges', []) as $edge) {
            if (is_array($edge) && ($edge['sourceLineId'] ?? null) === $sourceId) {
                $matching[] = $edge;
            }
        }

        return $bytes > 0
            && count($matching) === 1
            && $matching[0]['startByte'] === 0
            && $matching[0]['endByte'] === $bytes;
    }

    /** @param array<string,mixed> $placement @param array<string,mixed> $risk */
    private static function placementMatchesRisk(array $placement, array $risk): bool
    {
        return ($placement['kind'] ?? null) === 'image-xobject'
            && ($placement['page'] ?? null) === $risk['page']
            && ($placement['contentStream'] ?? null) === $risk['paintStream']
            && ($placement['object'] ?? null) === $risk['paintObject']
            && ($placement['resource'] ?? null) === $risk['paintResource']
            && ($placement['visible'] ?? null) === true
            && ($placement['placementEligible'] ?? null) === true
            && self::nonEmptyString($placement['id'] ?? null)
            && self::boundsMatch($placement['bbox'] ?? null, $risk['paintBounds']);
    }

    /** @param array<string,mixed> $placement */
    private static function placementDigest(array $placement): string
    {
        return self::digest([
            'id' => $placement['id'] ?? null,
            'kind' => $placement['kind'] ?? null,
            'page' => $placement['page'] ?? null,
            'contentStream' => $placement['contentStream'] ?? null,
            'paintOrder' => $placement['paintOrder'] ?? null,
            'object' => $placement['object'] ?? null,
            'resource' => $placement['resource'] ?? null,
            'bbox' => $placement['bbox'] ?? null,
        ], true);
    }

    private static function sourceOccurrenceComparableText(string $text): string
    {
        $text = self::repairControlLigatures($text);
        if ($text !== '' && preg_match('//u', $text) !== 1) {
            $decoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
            if (!is_string($decoded) || $decoded === '') {
                $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            }
            if (is_string($decoded)) {
                $text = self::repairControlLigatures($decoded);
            }
        }
        $controlClass = '[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]';
        if (preg_match('/' . $controlClass . '/u', $text) === 1) {
            $text = preg_replace_callback(
                '/(^|[ \t])(' . $controlClass . '{2,})[ \t]*(?=[\p{L}\p{N}])/u',
                static function (array $match): string {
                    $characters = preg_split('//u', $match[2], -1, PREG_SPLIT_NO_EMPTY) ?: [];

                    return count($characters) >= 2 && count(array_unique($characters)) === 1
                        ? $match[1] . "\u{2022} "
                        : $match[1] . ' ';
                },
                $text
            ) ?? $text;
            $text = preg_replace('/' . $controlClass . '+/u', ' ', $text) ?? $text;
        }
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $text) ?? '';
    }

    private static function repairControlLigatures(string $text): string
    {
        if (!str_contains($text, "\x02")) {
            return $text;
        }

        return preg_replace('/((?<=\p{L})\x02(?=\p{Ll})|\x02(?=\p{Ll}))/u', 'fi', $text) ?? $text;
    }

    private static function boundsAreFinite(mixed $bounds): bool
    {
        if (!is_array($bounds) || array_keys($bounds) !== ['x1', 'y1', 'x2', 'y2']) {
            return false;
        }
        foreach ($bounds as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                return false;
            }
        }

        return (float) $bounds['x2'] > (float) $bounds['x1']
            && (float) $bounds['y2'] > (float) $bounds['y1'];
    }

    private static function boundsIntersect(array $left, array $right): bool
    {
        return min((float) $left['x2'], (float) $right['x2'])
                - max((float) $left['x1'], (float) $right['x1']) > 0.000001
            && min((float) $left['y2'], (float) $right['y2'])
                - max((float) $left['y1'], (float) $right['y1']) > 0.000001;
    }

    private static function boundsMatch(mixed $left, mixed $right, float $tolerance = 0.000001): bool
    {
        if (!self::boundsAreFinite($left) || !self::boundsAreFinite($right)) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (abs((float) $left[$key] - (float) $right[$key]) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    private static function uniqueStringList(mixed $values): bool
    {
        if (!is_array($values) || !array_is_list($values)) {
            return false;
        }
        $seen = [];
        foreach ($values as $value) {
            if (!self::nonEmptyString($value) || isset($seen[$value])) {
                return false;
            }
            $seen[$value] = true;
        }

        return true;
    }

    private static function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '' && preg_match('//u', $value) === 1;
    }

    private static function isDigest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    /** @param list<array<string,mixed>> $values */
    private static function streamingListDigest(array $values): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $digest = hash_init('sha256');
        hash_update($digest, '[');
        foreach ($values as $index => $value) {
            $encoded = json_encode($value, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize($values));
            }
            if ($index > 0) {
                hash_update($digest, ',');
            }
            hash_update($digest, $encoded);
        }
        hash_update($digest, ']');

        return hash_final($digest);
    }

    private static function digest(mixed $value, bool $preserveZeroFraction = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($preserveZeroFraction) {
            $flags |= JSON_PRESERVE_ZERO_FRACTION;
        }
        $encoded = json_encode($value, $flags);

        return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
    }

    /**
     * @param list<string> $riskIds
     * @param list<string> $mediaProofDigests
     * @return array<string,mixed>
     */
    private static function result(
        bool $rawComplete,
        bool $complete,
        bool $reconciled,
        int $riskCount,
        array $riskIds,
        array $mediaProofDigests,
        ?string $failureReason
    ): array {
        $payload = [
            'version' => 1,
            'policy' => self::POLICY,
            'rawComplete' => $rawComplete,
            'complete' => $complete,
            'reconciled' => $reconciled,
            'riskCount' => $riskCount,
            'reconciledRiskCount' => $reconciled ? count($riskIds) : 0,
            'unresolvedRiskCount' => $complete ? 0 : $riskCount,
            'riskIds' => array_values($riskIds),
            'mediaProofDigests' => array_values($mediaProofDigests),
            'failureReason' => $failureReason,
        ];

        return $payload + ['proofDigest' => self::digest($payload, true)];
    }
}
