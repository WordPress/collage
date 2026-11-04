<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$streamingFactsPdf = static function (): string {
    $content = "q 0 0 120 12 re f Q\n"
        . "BT /F1 12 Tf 72 720 Td (Alpha) Tj 0 -18 Td (Beta) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'streams import facts with the same extraction boundaries as the public PDF APIs' => static function (
        TestRunner $t
    ) use ($streamingFactsPdf): void {
        $pdf = $streamingFactsPdf();
        $extractor = new PdfTextExtractor();
        $lines = [];
        $runs = [];
        $positionedRuns = [];
        $rectangles = [];

        foreach ($extractor->streamImportFacts($pdf) as $facts) {
            $t->same(1, $facts['page']);
            $t->same(3, $facts['pageObject']);
            $t->same(1, $facts['stream']);
            array_push($lines, ...$facts['textLineItems']);
            array_push($runs, ...$facts['textRuns']);
            array_push($positionedRuns, ...$facts['positionedTextRuns']);
            array_push($rectangles, ...$facts['filledRectangles']);
        }

        $t->same($extractor->extractTextLineItems($pdf), $lines);
        $t->same($extractor->extractTextRuns($pdf), $runs);
        $t->same($extractor->extractPositionedTextRuns($pdf), $positionedRuns);
        $t->same($extractor->extractFilledRectangles($pdf), $rectangles);
    },
    'can omit streamed PDF facts that a bounded consumer does not retain' => static function (
        TestRunner $t
    ) use ($streamingFactsPdf): void {
        $facts = iterator_to_array((new PdfTextExtractor())->streamImportFacts($streamingFactsPdf(), false, false, false), false);

        $t->same(1, count($facts));
        $t->same(['Alpha', 'Beta'], array_column($facts[0]['textLineItems'], 'text'));
        $t->same([], $facts[0]['textRuns']);
        $t->same([], $facts[0]['positionedTextRuns']);
        $t->same([], $facts[0]['filledRectangles']);
    },
];
