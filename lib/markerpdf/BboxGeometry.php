<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class BboxGeometry
{
    /**
     * Native boundary for marker.schema.bbox::should_merge_blocks.
     *
     * @param list<float|int> $box1
     * @param list<float|int> $box2
     */
    public function shouldMergeBlocks(array $box1, array $box2, float $tolerance = 5.0): bool
    {
        $box1 = $this->bbox($box1);
        $box2 = $this->bbox($box2);

        return $box2[0] > $box1[0]
            && abs($box2[1] - $box1[1]) < $tolerance
            && abs($box2[3] - $box1[3]) < $tolerance
            && abs($box2[0] - $box1[2]) < $tolerance;
    }

    /**
     * Native boundary for marker.schema.bbox::merge_boxes.
     *
     * @param list<float|int> $box1
     * @param list<float|int> $box2
     * @return list<float>
     */
    public function mergeBoxes(array $box1, array $box2): array
    {
        $box1 = $this->bbox($box1);
        $box2 = $this->bbox($box2);

        return [
            min($box1[0], $box2[0]),
            min($box1[1], $box2[1]),
            max($box2[2], $box1[2]),
            max($box1[3], $box2[3]),
        ];
    }

    /**
     * Native boundary for marker.schema.bbox::boxes_intersect.
     *
     * @param list<float|int> $box1
     * @param list<float|int> $box2
     */
    public function boxesIntersect(array $box1, array $box2): bool
    {
        $box1 = $this->bbox($box1);
        $box2 = $this->bbox($box2);

        return $box1[0] < $box2[2]
            && $box1[2] > $box2[0]
            && $box1[1] < $box2[3]
            && $box1[3] > $box2[1];
    }

    /**
     * Native boundary for marker.schema.bbox::box_intersection_pct.
     *
     * @param list<float|int> $box1
     * @param list<float|int> $box2
     */
    public function boxIntersectionPct(array $box1, array $box2): float
    {
        $box1 = $this->bbox($box1);
        $box2 = $this->bbox($box2);

        $xLeft = max($box1[0], $box2[0]);
        $yTop = max($box1[1], $box2[1]);
        $xRight = min($box1[2], $box2[2]);
        $yBottom = min($box1[3], $box2[3]);

        if ($xRight < $xLeft || $yBottom < $yTop) {
            return 0.0;
        }

        $box1Area = $this->area($box1);
        if ($box1Area == 0.0) {
            return 0.0;
        }

        return (($xRight - $xLeft) * ($yBottom - $yTop)) / $box1Area;
    }

    /**
     * Native boundary for marker.schema.bbox::multiple_boxes_intersect.
     *
     * @param list<float|int> $box1
     * @param list<list<float|int>> $boxes
     */
    public function multipleBoxesIntersect(array $box1, array $boxes): bool
    {
        foreach ($boxes as $box2) {
            if ($this->boxesIntersect($box1, $box2)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Native boundary for marker.schema.bbox::unnormalize_box.
     *
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function unnormalizeBox(array $bbox, float $width, float $height): array
    {
        $bbox = $this->bbox($bbox);

        return [
            $width * ($bbox[0] / 1000.0),
            $height * ($bbox[1] / 1000.0),
            $width * ($bbox[2] / 1000.0),
            $height * ($bbox[3] / 1000.0),
        ];
    }

    /**
     * Native boundary for marker.schema.bbox::rescale_bbox.
     *
     * @param list<float|int> $origDim
     * @param list<float|int> $newDim
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function rescaleBbox(array $origDim, array $newDim, array $bbox): array
    {
        $origDim = $this->bbox($origDim);
        $newDim = $this->bbox($newDim);
        $bbox = $this->bbox($bbox);

        $pageWidth = $newDim[2] - $newDim[0];
        $pageHeight = $newDim[3] - $newDim[1];
        $detectedWidth = $origDim[2] - $origDim[0];
        $detectedHeight = $origDim[3] - $origDim[1];

        if ($pageWidth == 0.0 || $pageHeight == 0.0 || $detectedWidth == 0.0 || $detectedHeight == 0.0) {
            return $bbox;
        }

        $widthScaler = $detectedWidth / $pageWidth;
        $heightScaler = $detectedHeight / $pageHeight;

        return [
            $bbox[0] / $widthScaler,
            $bbox[1] / $heightScaler,
            $bbox[2] / $widthScaler,
            $bbox[3] / $heightScaler,
        ];
    }

    /**
     * Native boundary for marker.schema.bbox::get_center.
     *
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function getCenter(array $bbox): array
    {
        $bbox = $this->bbox($bbox);

        return [
            ($bbox[0] + $bbox[2]) / 2.0,
            ($bbox[1] + $bbox[3]) / 2.0,
        ];
    }

    /**
     * Native boundary for marker.schema.bbox::BboxElement dimensions/properties.
     *
     * @param list<float|int> $bbox
     * @return array{bbox: list<float>, height: float, width: float, x_start: float, y_start: float, area: float}
     */
    public function elementMetrics(array $bbox): array
    {
        $bbox = $this->bbox($bbox);

        return [
            'bbox' => $bbox,
            'height' => $bbox[3] - $bbox[1],
            'width' => $bbox[2] - $bbox[0],
            'x_start' => $bbox[0],
            'y_start' => $bbox[1],
            'area' => $this->area($bbox),
        ];
    }

    /**
     * Native boundary for marker.schema.bbox::BboxElement.intersection_pct.
     *
     * @param list<float|int> $bbox
     * @param list<float|int> $otherBbox
     */
    public function elementIntersectionPct(array $bbox, array $otherBbox): float
    {
        return $this->elementMetrics($bbox)['area'] == 0.0
            ? 0.0
            : $this->boxIntersectionPct($bbox, $otherBbox);
    }

    /**
     * Native boundary for marker.schema.bbox::BboxElement.distance.
     *
     * @param list<float|int> $bbox
     * @param list<float|int> $otherBbox
     */
    public function elementDistance(array $bbox, array $otherBbox): float
    {
        $center = $this->getCenter($bbox);
        $otherCenter = $this->getCenter($otherBbox);

        return sqrt(($center[0] - $otherCenter[0]) ** 2 + ($center[1] - $otherCenter[1]) ** 2);
    }

    /**
     * @param array<mixed> $bbox
     * @return list<float>
     */
    private function bbox(array $bbox): array
    {
        $values = array_values($bbox);
        if (count($values) !== 4) {
            throw new InvalidArgumentException('bbox must have 4 elements');
        }

        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('bbox values must be numeric');
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }

    /**
     * @param list<float> $bbox
     */
    private function area(array $bbox): float
    {
        return ($bbox[2] - $bbox[0]) * ($bbox[3] - $bbox[1]);
    }
}
