<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BboxGeometry;

return [
    'maps upstream bbox merge tolerance for adjacent text boxes' => static function (TestRunner $t): void {
        $geometry = new BboxGeometry();
        $left = [10.0, 20.0, 110.0, 40.0];
        $right = [113.0, 22.0, 190.0, 42.0];

        $t->true($geometry->shouldMergeBlocks($left, $right));
        $t->same([10.0, 20.0, 190.0, 42.0], $geometry->mergeBoxes($left, $right));
        $t->true(!$geometry->shouldMergeBlocks($left, [116.0, 22.0, 190.0, 42.0]), 'Boxes too far apart should not merge.');
        $t->true(!$geometry->shouldMergeBlocks($left, [113.0, 30.0, 190.0, 50.0]), 'Y drift beyond tolerance should not merge.');
        $t->true(!$geometry->shouldMergeBlocks($left, [8.0, 22.0, 90.0, 42.0]), 'Second box must be to the right.');
    },
    'uses upstream strict bbox intersection and box1-area percentage semantics' => static function (TestRunner $t): void {
        $geometry = new BboxGeometry();

        $t->true($geometry->boxesIntersect([0.0, 0.0, 100.0, 100.0], [50.0, 50.0, 150.0, 150.0]));
        $t->true(!$geometry->boxesIntersect([0.0, 0.0, 100.0, 100.0], [100.0, 20.0, 120.0, 80.0]), 'Touching edges are not intersections upstream.');
        $t->same(0.25, $geometry->boxIntersectionPct([0.0, 0.0, 100.0, 100.0], [50.0, 50.0, 150.0, 150.0]));
        $t->same(0.0, $geometry->boxIntersectionPct([0.0, 0.0, 0.0, 100.0], [0.0, 0.0, 10.0, 10.0]));
        $t->true($geometry->multipleBoxesIntersect([20.0, 20.0, 40.0, 40.0], [[0.0, 0.0, 10.0, 10.0], [35.0, 35.0, 60.0, 60.0]]));
    },
    'maps upstream normalized bbox scaling and BboxElement metrics' => static function (TestRunner $t): void {
        $geometry = new BboxGeometry();

        $t->same([60.0, 160.0, 300.0, 560.0], $geometry->unnormalizeBox([100.0, 200.0, 500.0, 700.0], 600.0, 800.0));
        $t->same([60.0, 100.0, 280.0, 240.0], $geometry->rescaleBbox(
            [0.0, 0.0, 1200.0, 1600.0],
            [0.0, 0.0, 600.0, 800.0],
            [120.0, 200.0, 560.0, 480.0]
        ));
        $t->same([60.0, 80.0], $geometry->getCenter([10.0, 20.0, 110.0, 140.0]));
        $t->same(
            [
                'bbox' => [10.0, 20.0, 110.0, 140.0],
                'height' => 120.0,
                'width' => 100.0,
                'x_start' => 10.0,
                'y_start' => 20.0,
                'area' => 12000.0,
            ],
            $geometry->elementMetrics([10.0, 20.0, 110.0, 140.0])
        );
        $t->same(50.0, $geometry->elementDistance([0.0, 0.0, 40.0, 40.0], [30.0, 40.0, 70.0, 80.0]));
    },
    'rejects invalid BboxElement-shaped input like upstream validation' => static function (TestRunner $t): void {
        $geometry = new BboxGeometry();

        $t->throws(InvalidArgumentException::class, static fn (): bool => $geometry->boxesIntersect([0.0, 0.0, 1.0], [0.0, 0.0, 1.0, 1.0]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $geometry->elementMetrics([0.0, 'x', 1.0, 1.0]));
    },
    'drives a WordPress geometry preflight for merged text and image overlap' => static function (TestRunner $t): void {
        $geometry = new BboxGeometry();
        $spans = [
            ['text' => 'Intro', 'bbox' => [72.0, 96.0, 118.0, 112.0]],
            ['text' => 'duction', 'bbox' => [121.0, 97.0, 188.0, 113.0]],
        ];
        $mergedText = $spans[0]['text'];
        $mergedBbox = $spans[0]['bbox'];

        if ($geometry->shouldMergeBlocks($mergedBbox, $spans[1]['bbox'])) {
            $mergedText .= $spans[1]['text'];
            $mergedBbox = $geometry->mergeBoxes($mergedBbox, $spans[1]['bbox']);
        }

        $imageRegion = [70.0, 90.0, 190.0, 120.0];
        $html = "<!-- wp:paragraph -->\n";
        $html .= '<p data-marker-bbox="' . implode(',', $mergedBbox) . '">' . htmlspecialchars($mergedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
        $html .= "<!-- /wp:paragraph -->\n";
        if ($geometry->boxesIntersect($mergedBbox, $imageRegion)) {
            $html .= "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"0_image_0.png\" alt=\"0_image_0.png\"/></figure>\n<!-- /wp:image -->\n";
        }

        $t->contains('<p data-marker-bbox="72,96,188,113">Introduction</p>', $html);
        $t->contains('<!-- wp:image -->', $html);
    },
];
