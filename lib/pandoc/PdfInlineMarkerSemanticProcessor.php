<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Reattach detached superscript markers to the text run they annotate.
 *
 * PDF text streams often emit a footnote marker as a separate run even when
 * it is visually attached to the preceding word. The decision here is based
 * only on relative font size and geometry: a compact run must sit immediately
 * after and above a larger baseline on the same page and content stream.
 */
final class PdfInlineMarkerSemanticProcessor implements PdfSemanticRecordProcessor
{
    private int $markerCount = 0;

    public function __construct(private readonly string $sourceSha256 = '')
    {
    }

    public function name(): string
    {
        return 'inline-markers';
    }

    public function markerCount(): int
    {
        return $this->markerCount;
    }

    public function process(array $records): array
    {
        $this->markerCount = 0;
        $merged = [];
        foreach ($records as $record) {
            $previousIndex = array_key_last($merged);
            if ($previousIndex === null || !$this->isDetachedInlineMarker($merged[$previousIndex], $record)) {
                $merged[] = $record;
                continue;
            }

            $marker = trim($record['text']);
            $previous = $merged[$previousIndex];
            $baseRecord = $previous;
            $layout = $previous['layout'];
            $markerLayout = $record['layout'];
            $previous['text'] = rtrim($previous['text']) . $marker;
            $layout['x2'] = max((float) $layout['x2'], (float) $markerLayout['x2']);
            $layout['y2'] = max((float) $layout['y2'], (float) $markerLayout['y2']);
            foreach ([['textX1', 'min'], ['textY1', 'min'], ['textX2', 'max'], ['textY2', 'max']] as [$key, $operation]) {
                if (is_numeric($layout[$key] ?? null) && is_numeric($markerLayout[$key] ?? null)) {
                    $layout[$key] = $operation === 'min'
                        ? min((float) $layout[$key], (float) $markerLayout[$key])
                        : max((float) $layout[$key], (float) $markerLayout[$key]);
                }
            }
            if (isset($layout['sourceOrderEnd'], $markerLayout['sourceOrderEnd'])) {
                $layout['sourceOrderEnd'] = max((int) $layout['sourceOrderEnd'], (int) $markerLayout['sourceOrderEnd']);
            }
            $layout['sourcePdfInlineMarkerCount'] = (int) ($layout['sourcePdfInlineMarkerCount'] ?? 0) + 1;
            $layout['sourcePdfProtectedSemanticContent'] = true;
            $layout['text'] = $previous['text'];
            $exactUnion = $this->exactSourceInlineMarkerUnion(
                $baseRecord,
                $record,
                $previous['text']
            );
            $layout = $this->withoutStaleExactSourceIdentity($layout);
            if ($exactUnion !== null) {
                $layout['sourcePdfGlobalSourceIndexes'] = $exactUnion['sourceIndexes'];
                $layout['sourcePdfExactSourceRanges'] = $exactUnion['ranges'];
                $layout['sourcePdfInlineMarkerExactSourceUnionProof'] = $exactUnion['inlineProof'];
                $layout['sourcePdfStreams'] = $exactUnion['streams'];
                if ($exactUnion['sourceIds'] !== []) {
                    $layout['sourcePdfSourceIds'] = $exactUnion['sourceIds'];
                }
            }
            $previous['layout'] = $layout;
            $merged[$previousIndex] = $previous;
            $this->markerCount++;
        }

        return array_values($merged);
    }

    /**
     * Preserve source recovery only when both unchanged records carry one
     * complete exact-offset occurrence and those occurrences are consecutive.
     * Exact-geometry fallbacks intentionally do not receive the broader whole
     * occurrence proof, so this narrower proof is built while their immutable
     * IDs, indexes, source SHA, ranges, and geometry are still present.
     *
     * @param array{text:string,layout:array<string,mixed>|null} $base
     * @param array{text:string,layout:array<string,mixed>|null} $marker
     * @return array{
     *   sourceIndexes:list<int>,
     *   ranges:list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}>,
     *   streams:list<int>,
     *   sourceIds:list<string>,
     *   inlineProof:array<string,mixed>
     * }|null
     */
    private function exactSourceInlineMarkerUnion(
        array $base,
        array $marker,
        string $mergedText
    ): ?array
    {
        $baseCarrier = $this->validatedExactSourceCarrier($base);
        $markerCarrier = $this->validatedExactSourceCarrier($marker);
        if ($baseCarrier === null
            || $markerCarrier === null
            || $baseCarrier['page'] !== $markerCarrier['page']
            || $baseCarrier['stream'] !== $markerCarrier['stream']
            || !hash_equals($baseCarrier['sourceSha256'], $markerCarrier['sourceSha256'])) {
            return null;
        }

        $components = array_merge($baseCarrier['components'], $markerCarrier['components']);
        $previousComponent = null;
        $ranges = [];
        foreach ($components as $component) {
            if ($previousComponent !== null
                && ($component['sourceIndex'] !== $previousComponent['sourceIndex'] + 1
                    || $component['pageLocalSourceIndex']
                        !== $previousComponent['pageLocalSourceIndex'] + 1)) {
                return null;
            }
            $ranges[] = $component['range'];
            $previousComponent = $component;
        }
        $sourceIds = array_column($components, 'sourceId');
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            return null;
        }

        $projection = $baseCarrier['projection'] . $markerCarrier['projection'];
        if ($projection === ''
            || !hash_equals($projection, $this->significantProjection($mergedText))) {
            return null;
        }

        $inlineProof = [
            'version' => 1,
            'method' => 'exact-source-inline-marker-union',
            'sourceSha256' => $baseCarrier['sourceSha256'],
            'page' => $baseCarrier['page'],
            'layoutStream' => $baseCarrier['stream'],
            'markerCount' => $baseCarrier['markerCount'] + 1,
            'components' => $components,
            'ranges' => $ranges,
            'projectionDigest' => hash('sha256', $projection),
        ];
        if ($inlineProof['markerCount'] !== count($components) - 1) {
            return null;
        }
        $inlineProof['proofDigest'] = hash('sha256', json_encode(
            $inlineProof,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');

        return [
            'sourceIndexes' => array_column($components, 'sourceIndex'),
            'ranges' => $ranges,
            'streams' => [$baseCarrier['stream']],
            'sourceIds' => $sourceIds,
            'inlineProof' => $inlineProof,
        ];
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $record
     * @return array{
     *   projection:string,
     *   sourceSha256:string,
     *   page:int,
     *   stream:int,
     *   markerCount:int,
     *   components:list<array<string,mixed>>
     * }|null
     */
    private function validatedExactSourceCarrier(array $record): ?array
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $projection = $this->significantProjection((string) ($record['text'] ?? ''));
        $layoutProjection = is_array($layout)
            ? $this->significantProjection((string) ($layout['text'] ?? ''))
            : '';
        if (!is_array($layout)
            || $projection === ''
            || !hash_equals($projection, $layoutProjection)) {
            return null;
        }

        if ((int) ($layout['sourcePdfInlineMarkerCount'] ?? 0) > 0) {
            return $this->validatedPriorInlineMarkerUnion($layout, $projection);
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $this->sourceSha256) !== 1
            || !is_string($layout['id'] ?? null)
            || $layout['id'] === ''
            || !is_int($layout['page'] ?? null)
            || $layout['page'] < 1
            || !is_int($layout['sourceStream'] ?? null)
            || $layout['sourceStream'] < 1
            || !is_int($layout['sourcePdfGlobalSourceIndex'] ?? null)
            || !is_int($layout['sourceOrderStart'] ?? null)
            || !is_int($layout['sourceOrderEnd'] ?? null)
            || $layout['sourceOrderStart'] !== $layout['sourcePdfGlobalSourceIndex']
            || $layout['sourceOrderEnd'] !== $layout['sourcePdfGlobalSourceIndex']) {
            return null;
        }

        $pageLocalIndexes = is_array($layout['sourcePdfSourceIndexes'] ?? null)
            ? array_values($layout['sourcePdfSourceIndexes'])
            : [];
        $pageLocalIndex = $layout['sourcePdfSourceIndex'] ?? null;
        $pageLocalEnd = $layout['sourcePdfSourceIndexEnd'] ?? $pageLocalIndex;
        $ranges = $this->exactSourceRanges($layout);
        $range = count($ranges) === 1 ? $ranges[0] : null;
        $geometry = is_array($layout['sourceGeometry'] ?? null)
            ? $layout['sourceGeometry']
            : null;
        $isExactFallback = ($layout['sourcePdfExactGeometryFallback'] ?? false) === true
            && ($layout['sourceUnmatchedFallback'] ?? false) === true;
        if ((!$isExactFallback && ($layout['sourcePdfExactPositionedText'] ?? false) !== true)
            || !is_int($pageLocalIndex)
            || $pageLocalIndex < 0
            || $pageLocalEnd !== $pageLocalIndex
            || $pageLocalIndexes !== [$pageLocalIndex]
            || !is_array($range)
            || $range['sourceIndex'] !== $layout['sourcePdfGlobalSourceIndex']
            || $range['sourceStart'] !== 0
            || $range['sourceEnd'] !== strlen($projection)
            || ($layout['sourceGeometryMethod'] ?? null)
                !== 'exact-page-stream-character-offset'
            || !$this->hasExactSourceGeometry(
                $geometry,
                $layout['page'],
                $layout['sourceStream']
            )) {
            return null;
        }

        $component = [
            'version' => 1,
            'method' => $isExactFallback
                ? 'exact-source-geometry-fallback-occurrence'
                : 'exact-source-positioned-occurrence',
            'sourceSha256' => $this->sourceSha256,
            'sourceId' => $layout['id'],
            'sourceIndex' => $layout['sourcePdfGlobalSourceIndex'],
            'pageLocalSourceIndex' => $pageLocalIndex,
            'page' => $layout['page'],
            'stream' => $layout['sourceStream'],
            'range' => $range,
            'significantBytes' => strlen($projection),
            'projectionDigest' => hash('sha256', $projection),
            'geometryDigest' => $this->exactSourceGeometryDigest($geometry),
        ];
        $component['evidenceDigest'] = hash('sha256', json_encode(
            $component,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');

        return [
            'projection' => $projection,
            'sourceSha256' => $this->sourceSha256,
            'page' => $layout['page'],
            'stream' => $layout['sourceStream'],
            'markerCount' => 0,
            'components' => [$component],
        ];
    }

    /**
     * @param array<string,mixed> $layout
     * @return array{
     *   projection:string,
     *   sourceSha256:string,
     *   page:int,
     *   stream:int,
     *   markerCount:int,
     *   components:list<array<string,mixed>>
     * }|null
     */
    private function validatedPriorInlineMarkerUnion(
        array $layout,
        string $projection
    ): ?array
    {
        $proof = is_array($layout['sourcePdfInlineMarkerExactSourceUnionProof'] ?? null)
            ? $layout['sourcePdfInlineMarkerExactSourceUnionProof']
            : null;
        if (!$this->inlineMarkerUnionProofIsValid($proof, $layout, $projection)) {
            return null;
        }

        return [
            'projection' => $projection,
            'sourceSha256' => $proof['sourceSha256'],
            'page' => $proof['page'],
            'stream' => $proof['layoutStream'],
            'markerCount' => $proof['markerCount'],
            'components' => array_values($proof['components']),
        ];
    }

    /** @param array<string,mixed>|null $proof @param array<string,mixed> $layout */
    private function inlineMarkerUnionProofIsValid(
        ?array $proof,
        array $layout,
        string $projection
    ): bool
    {
        if ($proof === null
            || ($proof['version'] ?? null) !== 1
            || ($proof['method'] ?? null) !== 'exact-source-inline-marker-union'
            || !is_string($proof['sourceSha256'] ?? null)
            || !hash_equals($this->sourceSha256, $proof['sourceSha256'])
            || !is_int($proof['page'] ?? null)
            || $proof['page'] !== (int) ($layout['page'] ?? 0)
            || !is_int($proof['layoutStream'] ?? null)
            || $proof['layoutStream'] !== (int) ($layout['sourceStream'] ?? 0)
            || !is_int($proof['markerCount'] ?? null)
            || $proof['markerCount'] < 1
            || $proof['markerCount'] !== (int) ($layout['sourcePdfInlineMarkerCount'] ?? 0)
            || !is_array($proof['components'] ?? null)
            || !array_is_list($proof['components'])
            || count($proof['components']) !== $proof['markerCount'] + 1
            || !is_array($proof['ranges'] ?? null)
            || !array_is_list($proof['ranges'])
            || !is_string($proof['projectionDigest'] ?? null)
            || !hash_equals($proof['projectionDigest'], hash('sha256', $projection))
            || !is_string($proof['proofDigest'] ?? null)) {
            return false;
        }

        $coveredBytes = 0;
        $ranges = [];
        $sourceIndexes = [];
        $sourceIds = [];
        $previous = null;
        foreach ($proof['components'] as $component) {
            if (!$this->inlineMarkerUnionComponentIsValid(
                $component,
                $proof['sourceSha256'],
                $proof['page'],
                $proof['layoutStream']
            )) {
                return false;
            }
            if ($previous !== null
                && ($component['sourceIndex'] !== $previous['sourceIndex'] + 1
                    || $component['pageLocalSourceIndex']
                        !== $previous['pageLocalSourceIndex'] + 1)) {
                return false;
            }
            $componentProjection = substr(
                $projection,
                $coveredBytes,
                $component['significantBytes']
            );
            if (strlen($componentProjection) !== $component['significantBytes']
                || !hash_equals(
                    $component['projectionDigest'],
                    hash('sha256', $componentProjection)
                )) {
                return false;
            }
            $coveredBytes += $component['significantBytes'];
            $ranges[] = $component['range'];
            $sourceIndexes[] = $component['sourceIndex'];
            $sourceIds[] = $component['sourceId'];
            $previous = $component;
        }
        if ($coveredBytes !== strlen($projection)
            || array_values($proof['ranges']) !== $ranges
            || $this->exactSourceRanges($layout) !== $ranges
            || array_values($layout['sourcePdfGlobalSourceIndexes'] ?? [])
                !== $sourceIndexes
            || count($sourceIds) !== count(array_unique($sourceIds))
            || array_values($layout['sourcePdfSourceIds'] ?? []) !== $sourceIds) {
            return false;
        }

        $payload = $proof;
        unset($payload['proofDigest']);

        return hash_equals($proof['proofDigest'], hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: ''));
    }

    /** @param array<string,mixed> $component */
    private function inlineMarkerUnionComponentIsValid(
        array $component,
        string $sourceSha256,
        int $page,
        int $stream
    ): bool
    {
        $range = is_array($component['range'] ?? null) ? $component['range'] : null;
        if (($component['version'] ?? null) !== 1
            || !in_array($component['method'] ?? null, [
                'exact-source-geometry-fallback-occurrence',
                'exact-source-positioned-occurrence',
            ], true)
            || !is_string($component['sourceSha256'] ?? null)
            || !hash_equals($sourceSha256, $component['sourceSha256'])
            || !is_string($component['sourceId'] ?? null)
            || $component['sourceId'] === ''
            || !is_int($component['sourceIndex'] ?? null)
            || $component['sourceIndex'] < 0
            || !is_int($component['pageLocalSourceIndex'] ?? null)
            || $component['pageLocalSourceIndex'] < 0
            || ($component['page'] ?? null) !== $page
            || ($component['stream'] ?? null) !== $stream
            || !is_array($range)
            || $range !== [
                'sourceIndex' => $component['sourceIndex'],
                'sourceStart' => 0,
                'sourceEnd' => $component['significantBytes'] ?? null,
            ]
            || !is_int($component['significantBytes'] ?? null)
            || $component['significantBytes'] < 1) {
            return false;
        }
        foreach (['projectionDigest', 'geometryDigest', 'evidenceDigest'] as $digestKey) {
            if (!is_string($component[$digestKey] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $component[$digestKey]) !== 1) {
                return false;
            }
        }
        $payload = $component;
        unset($payload['evidenceDigest']);

        return hash_equals($component['evidenceDigest'], hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: ''));
    }

    /** @param array<string,mixed>|null $geometry */
    private function hasExactSourceGeometry(?array $geometry, int $page, int $stream): bool
    {
        if (!is_array($geometry)
            || ($geometry['orientation'] ?? null) !== 'horizontal'
            || (int) ($geometry['page'] ?? 0) !== $page
            || (int) ($geometry['stream'] ?? 0) !== $stream) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (!is_int($geometry[$key] ?? null) && !is_float($geometry[$key] ?? null)) {
                return false;
            }
        }

        return (float) $geometry['x2'] >= (float) $geometry['x1']
            && (float) $geometry['y2'] >= (float) $geometry['y1'];
    }

    /** @param array<string,mixed> $geometry */
    private function exactSourceGeometryDigest(array $geometry): string
    {
        $payload = [
            'page' => (int) ($geometry['page'] ?? 0),
            'stream' => (int) ($geometry['stream'] ?? 0),
            'x1' => (float) ($geometry['x1'] ?? 0.0),
            'y1' => (float) ($geometry['y1'] ?? 0.0),
            'x2' => (float) ($geometry['x2'] ?? 0.0),
            'y2' => (float) ($geometry['y2'] ?? 0.0),
            'orientation' => (string) ($geometry['orientation'] ?? ''),
        ];

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');
    }

    /** @param array<string,mixed> $layout @return array<string,mixed> */
    private function withoutStaleExactSourceIdentity(array $layout): array
    {
        unset(
            $layout['id'],
            $layout['sourcePdfGlobalSourceIndex'],
            $layout['sourcePdfGlobalSourceIndexes'],
            $layout['sourcePdfSourceIndex'],
            $layout['sourcePdfSourceIndexEnd'],
            $layout['sourcePdfSourceIndexes'],
            $layout['sourcePdfSourceIds'],
            $layout['sourcePdfExactSourceIndex'],
            $layout['sourcePdfExactSourceStart'],
            $layout['sourcePdfExactSourceEnd'],
            $layout['sourcePdfExactSourceRanges'],
            $layout['sourcePdfWholeExactOccurrenceProof'],
            $layout['sourcePdfInlineMarkerExactSourceUnionProof'],
            $layout['sourcePdfExactFormTailOccurrenceProof'],
            $layout['sourcePdfFormRowBundleProof'],
            $layout['sourcePdfStreams'],
            $layout['sourceGeometry'],
            $layout['sourceGeometryMethod'],
            $layout['sourcePdfExactPositionedText'],
            $layout['sourcePdfPageExactInventoryPreserved'],
            $layout['sourcePdfExactRangeFragmentSequence'],
            $layout['sourceCompositeComplementaryExactRanges'],
            $layout['sourceCompositePositionedFragments'],
            $layout['sourceVerifiedPartialInlineGeometry'],
            $layout['sourcePdfExactGeometryFallbackLineItem'],
            $layout['sourcePdfExactGeometryProof'],
            $layout['sourcePdfExactGeometryFallback'],
            $layout['sourceUnmatchedFallback']
        );

        return $layout;
    }

    /** @param array<string,mixed> $layout @return list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}> */
    private function exactSourceRanges(array $layout): array
    {
        $candidates = is_array($layout['sourcePdfExactSourceRanges'] ?? null)
            ? $layout['sourcePdfExactSourceRanges']
            : (isset(
                $layout['sourcePdfExactSourceIndex'],
                $layout['sourcePdfExactSourceStart'],
                $layout['sourcePdfExactSourceEnd']
            ) ? [[
                'sourceIndex' => $layout['sourcePdfExactSourceIndex'],
                'sourceStart' => $layout['sourcePdfExactSourceStart'],
                'sourceEnd' => $layout['sourcePdfExactSourceEnd'],
            ]] : []);
        $ranges = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)
                || !is_int($candidate['sourceIndex'] ?? null)
                || !is_int($candidate['sourceStart'] ?? null)
                || !is_int($candidate['sourceEnd'] ?? null)
                || $candidate['sourceIndex'] < 0
                || $candidate['sourceStart'] < 0
                || $candidate['sourceEnd'] <= $candidate['sourceStart']) {
                return [];
            }
            $ranges[] = [
                'sourceIndex' => $candidate['sourceIndex'],
                'sourceStart' => $candidate['sourceStart'],
                'sourceEnd' => $candidate['sourceEnd'],
            ];
        }

        return $ranges;
    }

    private function significantProjection(string $text): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $text) ?? '';
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $base
     * @param array{text:string,layout:array<string,mixed>|null} $candidate
     */
    private function isDetachedInlineMarker(array $base, array $candidate): bool
    {
        $text = trim($candidate['text']);
        if (preg_match('/^(?:[\p{L}\p{N}*\x{2020}\x{2021}\x{00A7}\x{00B6}]{1,3}|[\[(][\p{L}\p{N}]{1,2}[\])])$/u', $text) !== 1) {
            return false;
        }
        $baseLayout = is_array($base['layout'] ?? null) ? $base['layout'] : null;
        $markerLayout = is_array($candidate['layout'] ?? null) ? $candidate['layout'] : null;
        if (!$this->hasGeometry($baseLayout) || !$this->hasGeometry($markerLayout)) {
            return false;
        }
        if ((int) $baseLayout['page'] !== (int) $markerLayout['page']) {
            return false;
        }
        if (isset($baseLayout['sourceStream'], $markerLayout['sourceStream'])
            && (int) $baseLayout['sourceStream'] !== (int) $markerLayout['sourceStream']) {
            return false;
        }
        if (($baseLayout['code'] ?? false) === true || ($markerLayout['code'] ?? false) === true) {
            return false;
        }
        foreach (['sourcePdfTableGroup', 'sourcePdfLineOrientedGroup'] as $structuralKey) {
            if (isset($baseLayout[$structuralKey]) || isset($markerLayout[$structuralKey])) {
                return false;
            }
        }
        if (($baseLayout['sourcePdfRegionRole'] ?? null) === 'formula'
            || ($markerLayout['sourcePdfRegionRole'] ?? null) === 'formula') {
            return false;
        }

        $baseText = rtrim($base['text']);
        $baseFont = max(1.0, (float) $baseLayout['fontSize']);
        $markerFont = max(1.0, (float) $markerLayout['fontSize']);
        if ($baseText === '' || $markerFont >= $baseFont * 0.84) {
            return false;
        }

        $horizontalGap = (float) $markerLayout['x1'] - (float) $baseLayout['x2'];
        if ($horizontalGap < -$baseFont * 0.60 || $horizontalGap > $baseFont * 1.10) {
            return false;
        }
        $baseCenterY = ((float) $baseLayout['y1'] + (float) $baseLayout['y2']) / 2.0;
        $markerCenterY = ((float) $markerLayout['y1'] + (float) $markerLayout['y2']) / 2.0;
        $rise = $markerCenterY - $baseCenterY;
        if ($rise < max(0.75, $baseFont * 0.10) || $rise > $baseFont * 0.90) {
            return false;
        }

        return (float) $markerLayout['y1'] <= (float) $baseLayout['y2'] + $baseFont * 0.25
            && (float) $markerLayout['y2'] >= (float) $baseLayout['y1'];
    }

    /** @param array<string,mixed>|null $layout */
    private function hasGeometry(?array $layout): bool
    {
        if (!is_array($layout)) {
            return false;
        }
        foreach (['page', 'x1', 'y1', 'x2', 'y2', 'fontSize'] as $key) {
            if (!is_int($layout[$key] ?? null) && !is_float($layout[$key] ?? null)) {
                return false;
            }
        }

        return (int) $layout['page'] > 0
            && (float) $layout['x2'] >= (float) $layout['x1']
            && (float) $layout['y2'] >= (float) $layout['y1'];
    }
}
