<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily attaches conservative whole-source text anchors to PDF image and Form
 * XObject placements.
 */
final class PdfImagePlacementAnchorer
{
    private \Closure $lengthCallback;
    private \Closure $exactRangesCallback;
    private \Closure $comparableTextCallback;
    private \Closure $semanticPrefixCallback;
    private \Closure $exactGeometryCallback;

    private function __construct(
        callable $length,
        callable $exactRanges,
        callable $comparableText,
        callable $semanticPrefix,
        callable $exactGeometry
    ) {
        $this->lengthCallback = \Closure::fromCallable($length);
        $this->exactRangesCallback = \Closure::fromCallable($exactRanges);
        $this->comparableTextCallback = \Closure::fromCallable($comparableText);
        $this->semanticPrefixCallback = \Closure::fromCallable($semanticPrefix);
        $this->exactGeometryCallback = \Closure::fromCallable($exactGeometry);
    }

    /**
     * @param list<array<string,mixed>> $placements
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceLineItems
     * @return list<array<string,mixed>>
     */
    public static function anchor(
        array $placements,
        array $layouts,
        array $sourceLineItems,
        callable $length,
        callable $exactRanges,
        callable $comparableText,
        callable $semanticPrefix,
        callable $exactGeometry
    ): array {
        $anchorer = new self(
            $length,
            $exactRanges,
            $comparableText,
            $semanticPrefix,
            $exactGeometry
        );

        return $anchorer->imagePlacementsWithTextAnchors(
            $placements,
            $layouts,
            $sourceLineItems
        );
    }

    /** @param array<string,mixed> $placement */
    public static function placementIsEligible(array $placement): bool
    {
        return self::pdfImagePlacementIsEligible($placement);
    }

    /**
     * Preserve only compact text anchors with image-placement metadata. The
     * final AST deliberately does not retain page geometry, so the media
     * pass uses these anchors to insert a verified image near the text which
     * surrounded it on the source page. Ambiguous or overlapping placements
     * are left unanchored and therefore never become surprise image galleries.
     *
     * @param list<array<string, mixed>> $placements
     * @param list<array<string, mixed>> $layouts
     * @param list<array<string, mixed>> $sourceLineItems
     * @return list<array<string, mixed>>
     */
    private function imagePlacementsWithTextAnchors(
        array $placements,
        array $layouts,
        array $sourceLineItems = []
    ): array
    {
        $layoutsByPage = [];
        foreach ($layouts as $layout) {
            $normalized = $this->pdfImagePlacementTextLayout($layout);
            if ($normalized !== null) {
                $layoutsByPage[$normalized['page']][] = $normalized;
            }
        }

        $anchored = [];
        $sourceIdentityCache = [];
        foreach ($placements as $placement) {
            if (!is_array($placement)) {
                continue;
            }
            $page = max(1, (int) ($placement['page'] ?? 1));
            $bbox = $placement['bbox'] ?? null;
            if (!is_array($bbox)
                || !isset($bbox['x1'], $bbox['y1'], $bbox['x2'], $bbox['y2'])
                || !is_numeric($bbox['x1']) || !is_numeric($bbox['y1'])
                || !is_numeric($bbox['x2']) || !is_numeric($bbox['y2'])) {
                continue;
            }

            $record = $placement;
            $record['page'] = $page;
            $record['precedingText'] = null;
            $record['followingText'] = null;
            $record['precedingSourceOccurrenceId'] = null;
            $record['precedingSourceProjectionDigest'] = null;
            $record['followingSourceOccurrenceId'] = null;
            $record['followingSourceProjectionDigest'] = null;
            if (!self::pdfImagePlacementIsEligible($placement)) {
                $anchored[] = $record;
                continue;
            }

            $image = [
                'x1' => min((float) $bbox['x1'], (float) $bbox['x2']),
                'y1' => min((float) $bbox['y1'], (float) $bbox['y2']),
                'x2' => max((float) $bbox['x1'], (float) $bbox['x2']),
                'y2' => max((float) $bbox['y1'], (float) $bbox['y2']),
            ];
            if ($image['x2'] - $image['x1'] <= 0.000001 || $image['y2'] - $image['y1'] <= 0.000001) {
                $anchored[] = $record;
                continue;
            }

            $pageLayouts = $layoutsByPage[$page] ?? [];
            if ($pageLayouts === []) {
                $anchored[] = $record;
                continue;
            }

            // Form XObjects often contain their own visible labels: axis
            // ticks, legends, and flow-chart nodes are all extracted as text
            // even though they belong to the graphic.  Treating every one of
            // those labels as an unsafe overlap causes a perfectly ordinary
            // chart to lose both of its surrounding anchors and get appended
            // at the end of the imported document.  Geometry, rather than a
            // word list, tells the two cases apart: text wholly inside the
            // painted rectangle is part of the visual object; text which
            // crosses its edge is document flow overlaid on it and remains
            // unsafe.
            $formCanContainItsOwnText = is_array($placement['formBBox'] ?? null);
            $surroundingLayouts = [];
            foreach ($pageLayouts as $layout) {
                if ($formCanContainItsOwnText && $this->pdfImagePlacementContainsText($image, $layout)) {
                    continue;
                }
                $surroundingLayouts[] = $layout;
            }
            if ($this->pdfImagePlacementOverlapsText($image, $surroundingLayouts)) {
                $anchored[] = $record;
                continue;
            }

            $imageCenterY = ($image['y1'] + $image['y2']) / 2.0;
            $imageCenterX = ($image['x1'] + $image['x2']) / 2.0;
            $preceding = [];
            $following = [];
            foreach ($surroundingLayouts as $layout) {
                // A vertically-near line in another newspaper/brochure
                // column is not a safe document-flow anchor. Prefer losing
                // an image to placing it inside unrelated text.
                if (!$this->pdfImagePlacementSharesHorizontalBand($image, $layout)) {
                    continue;
                }
                $centerY = ($layout['y1'] + $layout['y2']) / 2.0;
                if ($centerY > $imageCenterY + 0.5) {
                    $preceding[] = $layout;
                } elseif ($centerY < $imageCenterY - 0.5) {
                    $following[] = $layout;
                }
            }

            $precedingLayout = $this->nearestPdfImagePlacementTextAnchor($preceding, $imageCenterX, $imageCenterY);
            $followingLayout = $this->nearestPdfImagePlacementTextAnchor($following, $imageCenterX, $imageCenterY);
            $precedingSourceIdentity = is_array($precedingLayout)
                ? $this->pdfImagePlacementWholeExactSourceOccurrenceIdentity(
                    $precedingLayout,
                    $sourceLineItems,
                    $sourceIdentityCache
                )
                : null;
            $followingSourceIdentity = is_array($followingLayout)
                ? $this->pdfImagePlacementWholeExactSourceOccurrenceIdentity(
                    $followingLayout,
                    $sourceLineItems,
                    $sourceIdentityCache
                )
                : null;
            $record['precedingText'] = $precedingLayout['text'] ?? null;
            $record['followingText'] = $followingLayout['text'] ?? null;
            $record['precedingSourceOccurrenceId'] = $precedingSourceIdentity['sourceOccurrenceId'] ?? null;
            $record['precedingSourceProjectionDigest'] = $precedingSourceIdentity['projectionDigest'] ?? null;
            $record['followingSourceOccurrenceId'] = $followingSourceIdentity['sourceOccurrenceId'] ?? null;
            $record['followingSourceProjectionDigest'] = $followingSourceIdentity['projectionDigest'] ?? null;
            $anchored[] = $record;
        }

        return $anchored;
    }

    /**
     * A clipped image can have an inexact bounding box while remaining safe
     * to place when it has a unique surrounding-text anchor. New extractor
     * records state that explicitly; retain the older high-confidence rule
     * for metadata produced by previous versions.
     *
     * @param array<string, mixed> $placement
     */
    private static function pdfImagePlacementIsEligible(array $placement): bool
    {
        if (array_key_exists('placementEligible', $placement)) {
            return $placement['placementEligible'] === true;
        }

        return ($placement['visible'] ?? false) === true
            && ($placement['confidence'] ?? '') === 'high';
    }

    /**
     * @param array<string, mixed> $layout
     * @return array{page:int,text:string,x1:float,y1:float,x2:float,y2:float,sourceIndex:?int,sourceStart:?int,sourceEnd:?int,sourceGlobalIndex:?int,sourceStream:?int}|null
     */
    private function pdfImagePlacementTextLayout(array $layout): ?array
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) ($layout['text'] ?? '')) ?? (string) ($layout['text'] ?? ''));
        if ($text === '' || $this->length($text) < 3
            || !isset($layout['x1'], $layout['y1'], $layout['x2'], $layout['y2'])
            || !is_numeric($layout['x1']) || !is_numeric($layout['y1'])
            || !is_numeric($layout['x2']) || !is_numeric($layout['y2'])) {
            return null;
        }

        $pageValue = $layout['page'] ?? 1;
        if (!is_numeric($pageValue)
            || (float) $pageValue !== (float) (int) $pageValue
            || (int) $pageValue < 1
            || (array_key_exists('sourcePdfGlobalSourceIndex', $layout)
                && (!is_int($layout['sourcePdfGlobalSourceIndex'])
                    || $layout['sourcePdfGlobalSourceIndex'] < 0))) {
            return null;
        }
        $sourceStreams = [];
        foreach (['sourceStream', 'stream'] as $streamField) {
            if (!array_key_exists($streamField, $layout)) {
                continue;
            }
            $streamValue = $layout[$streamField];
            if (!is_numeric($streamValue)
                || (float) $streamValue !== (float) (int) $streamValue
                || (int) $streamValue < 1) {
                return null;
            }
            $sourceStreams[(int) $streamValue] = true;
        }
        if (count($sourceStreams) > 1) {
            return null;
        }
        $sourceStream = $sourceStreams === [] ? null : array_key_first($sourceStreams);

        $x1 = min((float) $layout['x1'], (float) $layout['x2']);
        $y1 = min((float) $layout['y1'], (float) $layout['y2']);
        $x2 = max((float) $layout['x1'], (float) $layout['x2']);
        $y2 = max((float) $layout['y1'], (float) $layout['y2']);
        if ($x2 - $x1 <= 0.000001 || $y2 - $y1 <= 0.000001) {
            return null;
        }
        $ranges = $this->pdfPositionedExactSourceRanges($layout);
        $range = count($ranges) === 1 ? $ranges[0] : null;

        return [
            'page' => (int) $pageValue,
            'text' => $text,
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'sourceIndex' => is_array($range) ? $range['sourceIndex'] : null,
            'sourceStart' => is_array($range) ? $range['sourceStart'] : null,
            'sourceEnd' => is_array($range) ? $range['sourceEnd'] : null,
            'sourceGlobalIndex' => is_int($layout['sourcePdfGlobalSourceIndex'] ?? null)
                ? $layout['sourcePdfGlobalSourceIndex']
                : null,
            'sourceStream' => $sourceStream,
        ];
    }

    /**
     * Resolve the parser-stamped whole source range retained on a raw
     * positioned media anchor. This proof is intentionally narrower than the
     * later semantic-carrier proof: raw placement lines do not yet carry an
     * ID or stream, so the exact global range is checked back against the
     * immutable source inventory instead of being inferred from text.
     *
     * @param array<string,mixed> $layout
     * @param list<array<string,mixed>> $sourceLineItems
     * @param array<int,array{sourceItem:array<string,mixed>,localIndex:int,globalIndex:int}|false> $sourceIdentityCache
     * @return array{sourceOccurrenceId:string,sourceIndex:int,sourceLocalIndex:int,page:int,stream:int,projectionDigest:string}|null
     */
    private function pdfImagePlacementWholeExactSourceOccurrenceIdentity(
        array $layout,
        array $sourceLineItems,
        array &$sourceIdentityCache
    ): ?array {
        $sourceIndex = $layout['sourceIndex'] ?? null;
        $sourceStart = $layout['sourceStart'] ?? null;
        $sourceEnd = $layout['sourceEnd'] ?? null;
        $page = $layout['page'] ?? null;
        if (!is_int($sourceIndex)
            || !is_int($sourceStart)
            || !is_int($sourceEnd)
            || !is_int($page)
            || $sourceIndex < 0
            || $sourceStart < 0
            || $sourceEnd < $sourceStart
            || $page < 1) {
            return null;
        }

        if (!array_key_exists($sourceIndex, $sourceIdentityCache)) {
            $match = null;
            foreach ($sourceLineItems as $localIndex => $sourceItem) {
                if (!is_array($sourceItem)) {
                    continue;
                }
                if (array_key_exists('sourcePdfGlobalSourceIndex', $sourceItem)
                    && (!is_int($sourceItem['sourcePdfGlobalSourceIndex'])
                        || $sourceItem['sourcePdfGlobalSourceIndex'] < 0)) {
                    continue;
                }
                $globalIndex = $sourceItem['sourcePdfGlobalSourceIndex'] ?? $localIndex;
                if ($globalIndex !== $sourceIndex) {
                    continue;
                }
                if ($match !== null) {
                    $match = false;
                    break;
                }
                $match = [
                    'sourceItem' => $sourceItem,
                    'localIndex' => $localIndex,
                    'globalIndex' => $globalIndex,
                ];
            }
            $sourceIdentityCache[$sourceIndex] = is_array($match) ? $match : false;
        }
        $match = $sourceIdentityCache[$sourceIndex];
        if (!is_array($match)) {
            return null;
        }
        $sourceItem = $match['sourceItem'];
        $sourceId = is_string($sourceItem['id'] ?? null) ? $sourceItem['id'] : '';
        $sourcePage = is_int($sourceItem['page'] ?? null) && $sourceItem['page'] >= 1
            ? $sourceItem['page']
            : 0;
        $sourceStream = is_int($sourceItem['stream'] ?? null) && $sourceItem['stream'] >= 1
            ? $sourceItem['stream']
            : 0;
        $sourceGeometry = is_array($sourceItem['sourceGeometry'] ?? null)
            ? $sourceItem['sourceGeometry']
            : null;
        $sourceProjection = $this->pdfSourceOccurrenceComparableText(
            (string) ($sourceItem['text'] ?? '')
        );
        $layoutProjection = $this->pdfSourceOccurrenceComparableText(
            $this->pdfTextWithoutSemanticPrefixes((string) ($layout['text'] ?? ''))
        );
        if ($sourceId === ''
            || $sourcePage !== $page
            || $sourceStream < 1
            || !$this->sourcePdfSourceItemHasExactGeometry($sourceItem)
            || !is_array($sourceGeometry)
            || !is_int($sourceGeometry['page'] ?? null)
            || $sourceGeometry['page'] !== $sourcePage
            || !is_int($sourceGeometry['stream'] ?? null)
            || $sourceGeometry['stream'] !== $sourceStream
            || (($layout['sourceGlobalIndex'] ?? null) !== null
                && !is_int($layout['sourceGlobalIndex']))
            || (is_int($layout['sourceGlobalIndex'] ?? null)
                && $layout['sourceGlobalIndex'] !== $sourceIndex)
            || (($layout['sourceStream'] ?? null) !== null
                && !is_int($layout['sourceStream']))
            || (is_int($layout['sourceStream'] ?? null)
                && $layout['sourceStream'] !== $sourceStream)
            || $sourceProjection === ''
            || $layoutProjection === ''
            || !hash_equals($sourceProjection, $layoutProjection)
            || $sourceStart !== 0
            || $sourceEnd !== strlen($sourceProjection)) {
            return null;
        }

        return [
            'sourceOccurrenceId' => $sourceId,
            'sourceIndex' => $sourceIndex,
            'sourceLocalIndex' => $match['localIndex'],
            'page' => $sourcePage,
            'stream' => $sourceStream,
            'projectionDigest' => hash('sha256', $sourceProjection),
        ];
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $image
     * @param list<array{page:int,text:string,x1:float,y1:float,x2:float,y2:float}> $layouts
     */
    private function pdfImagePlacementOverlapsText(array $image, array $layouts): bool
    {
        foreach ($layouts as $layout) {
            $overlapWidth = min($image['x2'], $layout['x2']) - max($image['x1'], $layout['x1']);
            $overlapHeight = min($image['y2'], $layout['y2']) - max($image['y1'], $layout['y1']);
            if ($overlapWidth > 0.5 && $overlapHeight > 0.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Text entirely within a painted object's rectangle belongs to that
     * object for placement purposes.  The small tolerance accounts for PDF
     * glyph bounds that extend a fraction beyond their advance width while
     * keeping text that genuinely crosses an edge in the unsafe-overlap path.
     *
     * @param array{x1:float,y1:float,x2:float,y2:float} $image
     * @param array{page:int,text:string,x1:float,y1:float,x2:float,y2:float} $layout
     */
    private function pdfImagePlacementContainsText(array $image, array $layout): bool
    {
        $tolerance = 1.5;

        return $layout['x1'] >= $image['x1'] - $tolerance
            && $layout['x2'] <= $image['x2'] + $tolerance
            && $layout['y1'] >= $image['y1'] - $tolerance
            && $layout['y2'] <= $image['y2'] + $tolerance;
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $image
     * @param array{page:int,text:string,x1:float,y1:float,x2:float,y2:float} $layout
     */
    private function pdfImagePlacementSharesHorizontalBand(array $image, array $layout): bool
    {
        // Twelve PDF points is enough to tolerate tiny measurement drift
        // without reaching into a separate page column.
        $tolerance = 12.0;

        return $layout['x2'] + $tolerance > $image['x1']
            && $layout['x1'] - $tolerance < $image['x2'];
    }

    /**
     * @param list<array{page:int,text:string,x1:float,y1:float,x2:float,y2:float}> $layouts
     * @return array{page:int,text:string,x1:float,y1:float,x2:float,y2:float}|null
     */
    private function nearestPdfImagePlacementTextAnchor(array $layouts, float $imageCenterX, float $imageCenterY): ?array
    {
        if ($layouts === []) {
            return null;
        }

        usort($layouts, static function (array $left, array $right) use ($imageCenterX, $imageCenterY): int {
            $leftY = abs((($left['y1'] + $left['y2']) / 2.0) - $imageCenterY);
            $rightY = abs((($right['y1'] + $right['y2']) / 2.0) - $imageCenterY);
            if (abs($leftY - $rightY) > 0.001) {
                return $leftY <=> $rightY;
            }
            $leftX = abs((($left['x1'] + $left['x2']) / 2.0) - $imageCenterX);
            $rightX = abs((($right['x1'] + $right['x2']) / 2.0) - $imageCenterX);

            return $leftX <=> $rightX;
        });

        return $layouts[0];
    }

    private function length(string $text): int
    {
        return ($this->lengthCallback)($text);
    }

    /** @param array<string,mixed> $item @return list<array<string,int>> */
    private function pdfPositionedExactSourceRanges(array $item): array
    {
        return ($this->exactRangesCallback)($item);
    }

    private function pdfSourceOccurrenceComparableText(string $text): string
    {
        return ($this->comparableTextCallback)($text);
    }

    private function pdfTextWithoutSemanticPrefixes(string $text): string
    {
        return ($this->semanticPrefixCallback)($text);
    }

    /** @param array<string,mixed> $sourceItem */
    private function sourcePdfSourceItemHasExactGeometry(array $sourceItem): bool
    {
        return ($this->exactGeometryCallback)($sourceItem);
    }
}
