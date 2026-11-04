<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily builds clipped-display artifact media-anchor proofs from bound source
 * identities and independently verified later-paint image placements.
 */
final class PdfClippedDisplayMediaAnchorProofBuilder
{
    private string $sourceSha256;
    private int $maxProofs;
    private \Closure $comparableTextCallback;

    private function __construct(
        string $sourceSha256,
        int $maxProofs,
        callable $comparableText
    ) {
        $this->sourceSha256 = $sourceSha256;
        $this->maxProofs = max(1, $maxProofs);
        $this->comparableTextCallback = \Closure::fromCallable($comparableText);
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $boundDispositions
     * @param list<array<string,mixed>> $laterPaintRisks
     * @param list<array<string,mixed>> $imagePlacements
     * @return array{proofs:list<array<string,mixed>>,truncatedCount:int}
     */
    public static function build(
        string $sourceSha256,
        int $maxProofs,
        array $sourceLineItems,
        array $blocks,
        array $boundDispositions,
        array $laterPaintRisks,
        array $imagePlacements,
        callable $comparableText
    ): array {
        $builder = new self($sourceSha256, $maxProofs, $comparableText);

        return $builder->pdfClippedDisplayArtifactMediaAnchorProofs(
            $sourceLineItems,
            $blocks,
            $boundDispositions,
            $laterPaintRisks,
            $imagePlacements
        );
    }

    /**
     * Preserve a media-only hop across one narrowly validated text
     * disposition. A clipped display artifact remains absent from emitted
     * text, but an image which used that exact source occurrence as its
     * placement anchor may still use the existing same-page geometry fallback
     * when the artifact proof names one uniquely bound complete counterpart.
     *
     * The public artifact source edge intentionally remains a disposition with
     * no output destination. This compact proof records the separate
     * artifact-to-counterpart relationship without claiming that the clipped
     * characters were emitted.
     *
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $boundDispositions
     * @param list<array<string,mixed>> $laterPaintRisks
     * @param list<array<string,mixed>> $imagePlacements
     * @return array{proofs:list<array<string,mixed>>,truncatedCount:int}
     */
    private function pdfClippedDisplayArtifactMediaAnchorProofs(
        array $sourceLineItems,
        array $blocks,
        array $boundDispositions,
        array $laterPaintRisks,
        array $imagePlacements
    ): array {
        // Select the rare proof candidates before building lookup maps. Dense
        // documents can have tens of thousands of source items and output
        // nodes; retaining full ID-indexed copies here would overlap the
        // source-binding peak for a feature capped at 64 records.
        $candidates = [];
        $requestedSourceIds = [];
        $requestedNodeIds = [];
        $truncatedCount = 0;
        foreach ($boundDispositions as $artifactId => $artifactDisposition) {
            $evidence = is_array($artifactDisposition)
                && is_array($artifactDisposition['evidence'] ?? null)
                    ? $artifactDisposition['evidence']
                    : null;
            $counterpartEvidence = is_array($evidence['completeDisplayCounterpart'] ?? null)
                ? $evidence['completeDisplayCounterpart']
                : null;
            $counterpartId = is_string($counterpartEvidence['sourceOccurrenceId'] ?? null)
                ? $counterpartEvidence['sourceOccurrenceId']
                : '';
            $counterpartDisposition = is_array($boundDispositions[$counterpartId] ?? null)
                ? $boundDispositions[$counterpartId]
                : null;
            $counterpartMapping = is_array($counterpartDisposition['sourceMapping'] ?? null)
                ? $counterpartDisposition['sourceMapping']
                : null;
            $destinationNodeIds = is_array($counterpartMapping['destinationNodeIds'] ?? null)
                ? $counterpartMapping['destinationNodeIds']
                : [];
            $destinationNodeId = count($destinationNodeIds) === 1
                && is_string($destinationNodeIds[0] ?? null)
                ? $destinationNodeIds[0]
                : '';
            if (!is_string($artifactId)
                || $artifactId === ''
                || !is_array($artifactDisposition)
                || ($artifactDisposition['disposition'] ?? null) !== 'artifact'
                || !is_array($evidence)
                || ($evidence['method'] ?? null) !== 'whole-source-clipped-display-artifact'
                || $counterpartId === '') {
                continue;
            }
            if (count($candidates) >= $this->maxProofs) {
                $truncatedCount++;
                continue;
            }
            $candidates[] = [
                'artifactId' => $artifactId,
                'artifactDisposition' => $artifactDisposition,
                'evidence' => $evidence,
                'counterpartEvidence' => $counterpartEvidence,
                'counterpartId' => $counterpartId,
                'counterpartDisposition' => $counterpartDisposition,
                'counterpartMapping' => $counterpartMapping,
                'destinationNodeId' => $destinationNodeId,
            ];
            $requestedSourceIds[$artifactId] = true;
            $requestedSourceIds[$counterpartId] = true;
            if ($destinationNodeId !== '') {
                $requestedNodeIds[$destinationNodeId] = true;
            }
        }

        $sourceItemsById = [];
        $duplicateSourceIds = [];
        foreach ($sourceLineItems as $sourceItem) {
            if (!is_array($sourceItem)) {
                continue;
            }
            $sourceId = is_string($sourceItem['id'] ?? null) ? $sourceItem['id'] : '';
            if ($sourceId === '' || !isset($requestedSourceIds[$sourceId])) {
                continue;
            }
            if (isset($sourceItemsById[$sourceId])) {
                $duplicateSourceIds[$sourceId] = true;
                unset($sourceItemsById[$sourceId]);
                continue;
            }
            if (!isset($duplicateSourceIds[$sourceId])) {
                $sourceItemsById[$sourceId] = $sourceItem;
            }
        }

        $liveTopLevelNodes = [];
        $duplicateLiveNodeIds = [];
        foreach ($blocks as $block) {
            $nodeId = is_string($block->attr('sourceNodeId'))
                ? $block->attr('sourceNodeId')
                : '';
            if ($nodeId === '' || !isset($requestedNodeIds[$nodeId])) {
                continue;
            }
            if (isset($liveTopLevelNodes[$nodeId])) {
                $duplicateLiveNodeIds[$nodeId] = true;
                unset($liveTopLevelNodes[$nodeId]);
                continue;
            }
            if (!isset($duplicateLiveNodeIds[$nodeId])) {
                $liveTopLevelNodes[$nodeId] = $block;
            }
        }

        $proofs = [];
        foreach ($candidates as $candidate) {
            $artifactId = $candidate['artifactId'];
            $artifactDisposition = $candidate['artifactDisposition'];
            $evidence = $candidate['evidence'];
            $artifactMapping = is_array($artifactDisposition['sourceMapping'] ?? null)
                ? $artifactDisposition['sourceMapping']
                : null;
            $counterpartEvidence = $candidate['counterpartEvidence'];
            $artifactItem = $sourceItemsById[$artifactId] ?? null;
            $counterpartId = $candidate['counterpartId'];
            $counterpartItem = $sourceItemsById[$counterpartId] ?? null;
            $counterpartDisposition = $candidate['counterpartDisposition'];
            $counterpartMapping = $candidate['counterpartMapping'];
            $destinationNodeId = $candidate['destinationNodeId'];
            $liveNode = $liveTopLevelNodes[$destinationNodeId] ?? null;
            $liveSourceLineIds = $liveNode instanceof AstNode
                && is_array($liveNode->attr('sourceLineIds'))
                    ? $liveNode->attr('sourceLineIds')
                    : [];
            $artifactProjection = is_array($artifactItem)
                ? $this->pdfSourceOccurrenceComparableText((string) ($artifactItem['text'] ?? ''))
                : '';
            $counterpartProjection = is_array($counterpartItem)
                ? $this->pdfSourceOccurrenceComparableText((string) ($counterpartItem['text'] ?? ''))
                : '';
            $artifactProofDigest = is_string($evidence['proofDigest'] ?? null)
                ? $evidence['proofDigest']
                : '';
            $artifactProofPayload = is_array($evidence) ? $evidence : [];
            unset($artifactProofPayload['proofDigest']);
            $encodedArtifactProof = json_encode(
                $artifactProofPayload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $recomputedArtifactProofDigest = hash(
                'sha256',
                is_string($encodedArtifactProof) ? $encodedArtifactProof : serialize($artifactProofPayload)
            );
            $page = is_array($artifactItem)
                && is_int($artifactItem['page'] ?? null)
                && $artifactItem['page'] >= 1
                    ? $artifactItem['page']
                    : 0;
            $counterpartPage = is_array($counterpartItem)
                && is_int($counterpartItem['page'] ?? null)
                && $counterpartItem['page'] >= 1
                    ? $counterpartItem['page']
                    : 0;
            $laterPaintProof = $this->pdfClippedDisplayArtifactLaterPaintProof(
                $evidence,
                $laterPaintRisks,
                $imagePlacements
            );

            if (!is_array($evidence)
                || ($evidence['version'] ?? null) !== 1
                || ($evidence['method'] ?? null) !== 'whole-source-clipped-display-artifact'
                || ($evidence['sourceSha256'] ?? null) !== $this->sourceSha256
                || ($evidence['sourceOccurrenceId'] ?? null) !== $artifactId
                || ($evidence['page'] ?? null) !== $page
                || $artifactProofDigest === ''
                || !hash_equals($artifactProofDigest, $recomputedArtifactProofDigest)
                || !is_array($artifactItem)
                || $artifactProjection === ''
                || !is_string($evidence['projectionDigest'] ?? null)
                || !hash_equals($evidence['projectionDigest'], hash('sha256', $artifactProjection))
                || !is_array($counterpartEvidence)
                || $counterpartId === ''
                || $counterpartId === $artifactId
                || !is_array($counterpartItem)
                || $counterpartPage !== $page
                || ($counterpartEvidence['page'] ?? null) !== $page
                || $counterpartProjection === ''
                || !is_string($counterpartEvidence['projectionDigest'] ?? null)
                || !hash_equals(
                    $counterpartEvidence['projectionDigest'],
                    hash('sha256', $counterpartProjection)
                )
                || ($artifactMapping['status'] ?? null) !== 'disposition'
                || ($artifactMapping['mappingMode'] ?? null) !== 'explicit-disposition'
                || ($artifactMapping['destinationNodeIds'] ?? []) !== []
                || !is_array($counterpartDisposition)
                || !in_array(
                    $counterpartDisposition['disposition'] ?? null,
                    ['emitted', 'boundary-repair', 'semantic-structure'],
                    true
                )
                || ($counterpartMapping['status'] ?? null) !== 'output'
                || !in_array(
                    $counterpartMapping['mappingMode'] ?? null,
                    ['exact-sequence', 'exact-authorized-scope', 'exact-semantic-list-marker'],
                    true
                )
                || $destinationNodeId === ''
                || isset($duplicateLiveNodeIds[$destinationNodeId])
                || !($liveNode instanceof AstNode)
                || !in_array($counterpartId, $liveSourceLineIds, true)
                || $laterPaintProof === null) {
                continue;
            }

            $proof = [
                'version' => 2,
                'method' => 'whole-source-clipped-display-artifact-media-anchor',
                'sourceSha256' => $this->sourceSha256,
                'page' => $page,
                'artifactSourceOccurrenceId' => $artifactId,
                'artifactProjectionDigest' => hash('sha256', $artifactProjection),
                'counterpartSourceOccurrenceId' => $counterpartId,
                'counterpartProjectionDigest' => hash('sha256', $counterpartProjection),
                'counterpartDestinationNodeId' => $destinationNodeId,
                'laterPaintRiskId' => $laterPaintProof['riskId'],
                'laterPaintRiskDigest' => $laterPaintProof['riskDigest'],
                'laterPaintOperation' => $laterPaintProof['operation'],
                'laterPaintOperator' => $laterPaintProof['operator'],
                'laterPaintResource' => $laterPaintProof['resource'],
                'laterPaintObject' => $laterPaintProof['object'],
                'laterPaintPlacementId' => $laterPaintProof['placementId'],
                'laterPaintPlacementDigest' => $laterPaintProof['placementDigest'],
                'artifactProofDigest' => $artifactProofDigest,
            ];
            $encodedProof = json_encode(
                $proof,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $proof['proofDigest'] = hash(
                'sha256',
                is_string($encodedProof) ? $encodedProof : serialize($proof)
            );
            $proofs[] = $proof;
        }

        return ['proofs' => $proofs, 'truncatedCount' => $truncatedCount];
    }

    /**
     * Bind one whole-source artifact to the exact later Image paint which
     * created its raw visibility risk. The placement inventory is an
     * independent interpreter pass, so requiring one exact match prevents a
     * resource name, object number, or coincident rectangle from being
     * substituted after the source disposition was signed.
     *
     * @param array<string,mixed> $artifactEvidence
     * @param list<array<string,mixed>> $laterPaintRisks
     * @param list<array<string,mixed>> $imagePlacements
     * @return array{riskId:string,riskDigest:string,operation:int,operator:string,resource:string,object:int,placementId:string,placementDigest:string}|null
     */
    private function pdfClippedDisplayArtifactLaterPaintProof(
        array $artifactEvidence,
        array $laterPaintRisks,
        array $imagePlacements
    ): ?array {
        $range = is_array($artifactEvidence['range'] ?? null)
            ? $artifactEvidence['range']
            : null;
        if (!is_int($artifactEvidence['sourceIndex'] ?? null)
            || !is_int($artifactEvidence['page'] ?? null)
            || !is_int($artifactEvidence['stream'] ?? null)
            || !is_string($artifactEvidence['projectionDigest'] ?? null)
            || !is_array($range)
            || !is_int($range['sourceStart'] ?? null)
            || !is_int($range['sourceEnd'] ?? null)) {
            return null;
        }

        $matchingRisks = [];
        foreach ($laterPaintRisks as $risk) {
            if (!is_array($risk)
                || !$this->pdfLaterPaintRiskIdentityIsValid($risk)
                || ($risk['sourceSha256'] ?? null) !== $this->sourceSha256
                || ($risk['page'] ?? null) !== $artifactEvidence['page']
                || ($risk['sourceOccurrenceIndex'] ?? null) !== $artifactEvidence['sourceIndex']
                || ($risk['sourceStream'] ?? null) !== $artifactEvidence['stream']
                || ($risk['sourceRange'] ?? null) !== [
                    'start' => $range['sourceStart'],
                    'end' => $range['sourceEnd'],
                ]
                || ($risk['sourceProjectionDigest'] ?? null) !== $artifactEvidence['projectionDigest']
                || ($risk['paintOperator'] ?? null) !== 'Do'
                || ($risk['paintSubtype'] ?? null) !== 'Image'
                || !is_string($risk['paintResource'] ?? null)
                || $risk['paintResource'] === ''
                || !is_int($risk['paintObject'] ?? null)
                || $risk['paintObject'] <= 0
                || !is_array($risk['paintBounds'] ?? null)) {
                continue;
            }
            $matchingRisks[] = $risk;
        }
        if (count($matchingRisks) !== 1) {
            return null;
        }
        $risk = $matchingRisks[0];

        $matchingPlacements = [];
        foreach ($imagePlacements as $placement) {
            if (!is_array($placement)
                || ($placement['kind'] ?? null) !== 'image-xobject'
                || ($placement['page'] ?? null) !== $risk['page']
                || ($placement['contentStream'] ?? null) !== $risk['paintStream']
                || ($placement['object'] ?? null) !== $risk['paintObject']
                || ($placement['resource'] ?? null) !== $risk['paintResource']
                || ($placement['visible'] ?? null) !== true
                || ($placement['placementEligible'] ?? null) !== true
                || !is_string($placement['id'] ?? null)
                || $placement['id'] === ''
                || !is_array($placement['bbox'] ?? null)
                || !$this->pdfLaterPaintBoundsMatch($placement['bbox'], $risk['paintBounds'])) {
                continue;
            }
            $matchingPlacements[] = $placement;
        }
        if (count($matchingPlacements) !== 1) {
            return null;
        }
        $placement = $matchingPlacements[0];
        $placementDigest = $this->pdfLaterPaintPlacementDigest($placement);

        return [
            'riskId' => $risk['id'],
            'riskDigest' => $risk['riskDigest'],
            'operation' => $risk['paintOperation'],
            'operator' => $risk['paintOperator'],
            'resource' => $risk['paintResource'],
            'object' => $risk['paintObject'],
            'placementId' => $placement['id'],
            'placementDigest' => $placementDigest,
        ];
    }

    /** @param array<string,mixed> $risk */
    private function pdfLaterPaintRiskIdentityIsValid(array $risk): bool
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
            || !is_string($risk['sourceSha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['sourceSha256']) !== 1
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
            || !is_string($risk['sourceProjectionDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['sourceProjectionDigest']) !== 1
            || !is_int($risk['textOperation'] ?? null)
            || $risk['textOperation'] < 0
            || !$this->pdfLaterPaintBoundsAreFinite($risk['textBounds'] ?? null)
            || !is_int($risk['paintOperation'] ?? null)
            || $risk['paintOperation'] < 0
            || !is_int($risk['paintStream'] ?? null)
            || $risk['paintStream'] < 1
            || !is_string($risk['paintOperator'] ?? null)
            || !is_string($risk['riskDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['riskDigest']) !== 1) {
            return false;
        }
        $payload = $risk;
        $id = array_shift($payload);
        $digest = array_pop($payload);
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        $expectedDigest = hash('sha256', is_string($encoded) ? $encoded : serialize($payload));

        return is_string($id)
            && hash_equals('pdf-later-paint-risk-' . substr($expectedDigest, 0, 32), $id)
            && hash_equals($expectedDigest, $digest);
    }

    private function pdfLaterPaintBoundsAreFinite(mixed $bounds): bool
    {
        if (!is_array($bounds) || array_keys($bounds) !== ['x1', 'y1', 'x2', 'y2']) {
            return false;
        }
        foreach ($bounds as $value) {
            if (!is_int($value) && !is_float($value)) {
                return false;
            }
            if (!is_finite((float) $value)) {
                return false;
            }
        }

        return (float) $bounds['x2'] > (float) $bounds['x1']
            && (float) $bounds['y2'] > (float) $bounds['y1'];
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function pdfLaterPaintBoundsMatch(array $left, array $right): bool
    {
        if (!$this->pdfLaterPaintBoundsAreFinite($left)
            || !$this->pdfLaterPaintBoundsAreFinite($right)) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (abs((float) $left[$key] - (float) $right[$key]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $placement */
    private function pdfLaterPaintPlacementDigest(array $placement): string
    {
        $identity = [
            'id' => $placement['id'] ?? null,
            'kind' => $placement['kind'] ?? null,
            'page' => $placement['page'] ?? null,
            'contentStream' => $placement['contentStream'] ?? null,
            'paintOrder' => $placement['paintOrder'] ?? null,
            'object' => $placement['object'] ?? null,
            'resource' => $placement['resource'] ?? null,
            'bbox' => $placement['bbox'] ?? null,
        ];
        $encoded = json_encode(
            $identity,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        return hash('sha256', is_string($encoded) ? $encoded : serialize($identity));
    }

    private function pdfSourceOccurrenceComparableText(string $text): string
    {
        return ($this->comparableTextCallback)($text);
    }
}
