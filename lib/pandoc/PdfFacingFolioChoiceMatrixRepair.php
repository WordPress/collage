<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily loaded implementation for two uncommon, source-bound PDF boundary
 * repairs. PdfReader owns the cheap eligibility checks so ordinary documents
 * never compile this specialized geometry/proof machinery.
 */
final class PdfFacingFolioChoiceMatrixRepair
{
    private \Closure $sourceItemHasExactGeometry;
    private \Closure $comparableText;
    private \Closure $layoutHasGeometry;
    private \Closure $profileOrientation;
    private \Closure $exactSourceRanges;
    private \Closure $sourceEvidenceBounds;

    private function __construct(
        private string $sourceSha256,
        callable $sourceItemHasExactGeometry,
        callable $comparableText,
        callable $layoutHasGeometry,
        callable $profileOrientation,
        callable $exactSourceRanges,
        callable $sourceEvidenceBounds
    ) {
        $this->sourceItemHasExactGeometry = \Closure::fromCallable(
            $sourceItemHasExactGeometry
        );
        $this->comparableText = \Closure::fromCallable($comparableText);
        $this->layoutHasGeometry = \Closure::fromCallable($layoutHasGeometry);
        $this->profileOrientation = \Closure::fromCallable($profileOrientation);
        $this->exactSourceRanges = \Closure::fromCallable($exactSourceRanges);
        $this->sourceEvidenceBounds = \Closure::fromCallable($sourceEvidenceBounds);
    }

    /**
     * @param list<array<string,mixed>> $lineItems
     * @param list<array<string,mixed>> $positionedRuns
     */
    public static function mark(
        array &$lineItems,
        array $positionedRuns,
        string $sourceSha256,
        callable $sourceItemHasExactGeometry,
        callable $comparableText,
        callable $layoutHasGeometry,
        callable $profileOrientation,
        callable $exactSourceRanges,
        callable $sourceEvidenceBounds
    ): void {
        $repair = new self(
            $sourceSha256,
            $sourceItemHasExactGeometry,
            $comparableText,
            $layoutHasGeometry,
            $profileOrientation,
            $exactSourceRanges,
            $sourceEvidenceBounds
        );
        $repair->markSourcePdfFacingFolioAndChoiceMatrixRepairs(
            $lineItems,
            $positionedRuns
        );
    }

    /**
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     */
    public static function apply(
        array &$layouts,
        array $sourceItems,
        string $sourceSha256,
        callable $sourceItemHasExactGeometry,
        callable $comparableText,
        callable $layoutHasGeometry,
        callable $profileOrientation,
        callable $exactSourceRanges,
        callable $sourceEvidenceBounds
    ): void {
        $repair = new self(
            $sourceSha256,
            $sourceItemHasExactGeometry,
            $comparableText,
            $layoutHasGeometry,
            $profileOrientation,
            $exactSourceRanges,
            $sourceEvidenceBounds
        );
        $repair->pdfSourceLayoutsWithFacingFolioAndChoiceMatrixProjectionsInPlace(
            $layouts,
            $sourceItems
        );
    }

    /**
     * @param array<string,mixed> $proof
     * @param array<string,mixed> $item
     */
    public static function proofMatches(
        array $proof,
        array $item,
        int $sourceIndex,
        string $sourceSha256,
        callable $sourceItemHasExactGeometry,
        callable $comparableText,
        callable $layoutHasGeometry,
        callable $profileOrientation,
        callable $exactSourceRanges,
        callable $sourceEvidenceBounds
    ): bool {
        $repair = new self(
            $sourceSha256,
            $sourceItemHasExactGeometry,
            $comparableText,
            $layoutHasGeometry,
            $profileOrientation,
            $exactSourceRanges,
            $sourceEvidenceBounds
        );

        return $repair->sourcePdfBoundaryProjectionProofMatchesItem(
            $proof,
            $item,
            $sourceIndex
        );
    }

    /**
     * Compatibility bridge for PdfReader's private Reflection-tested wrappers.
     *
     * @param list<mixed> $arguments
     */
    public static function invoke(
        string $method,
        array $arguments,
        string $sourceSha256,
        callable $sourceItemHasExactGeometry,
        callable $comparableText,
        callable $layoutHasGeometry,
        callable $profileOrientation,
        callable $exactSourceRanges,
        callable $sourceEvidenceBounds
    ): mixed {
        $repair = new self(
            $sourceSha256,
            $sourceItemHasExactGeometry,
            $comparableText,
            $layoutHasGeometry,
            $profileOrientation,
            $exactSourceRanges,
            $sourceEvidenceBounds
        );

        return $repair->{$method}(...$arguments);
    }

    /**
     * Repair two producer-level table/picture boundary conventions only after
     * exact source ranges and painted font provenance agree. Neither rule is
     * keyed to fixture text: the first recognizes consecutive facing folios
     * painted at opposite spread edges, and the second recognizes a recurring
     * ActualText/symbol prefix paired with aligned choice columns.
     *
     * @param list<array<string,mixed>> $lineItems
     * @param list<array<string,mixed>> $positionedRuns
     */
    private function markSourcePdfFacingFolioAndChoiceMatrixRepairs(
        array &$lineItems,
        array $positionedRuns
    ): void {
        if ($lineItems === [] || $positionedRuns === [] || $this->sourceSha256 === '') {
            return;
        }

        // The complete run inventory is large, while both repairs begin with
        // narrow source-text predicates. Retain run wrappers only for source
        // occurrences which can still satisfy those necessary predicates.
        // This is a superset preflight: every accepted proof is still checked
        // by the full geometry/font routines below.
        $candidateSourceIndexes = [];
        $candidatePages = [];
        foreach ($lineItems as $sourceIndex => $item) {
            $globalSourceIndex = is_int($item['sourcePdfGlobalSourceIndex'] ?? null)
                ? $item['sourcePdfGlobalSourceIndex']
                : $sourceIndex;
            $text = is_string($item['text'] ?? null) ? $item['text'] : '';
            if ($this->sourcePdfSourceItemHasExactGeometry($item)
                && preg_match('/\A\s*\d{1,4}\s+\d{1,4}(?!\d).+\z/us', $text) === 1) {
                $candidateSourceIndexes[$globalSourceIndex] = true;
                $candidatePages[max(1, (int) ($item['page'] ?? 1))] = true;
            }

            $marker = $lineItems[$sourceIndex + 1] ?? null;
            if (!is_array($marker)
                || !$this->sourcePdfSourceItemHasExactGeometry($item)
                || !$this->sourcePdfSourceItemHasExactGeometry($marker)) {
                continue;
            }
            $labelComparable = $this->pdfSourceOccurrenceComparableText($text);
            $markerComparable = $this->pdfSourceOccurrenceComparableText(
                (string) ($marker['text'] ?? '')
            );
            preg_match_all('/\X/u', $markerComparable, $markerGraphemes);
            $graphemes = $markerGraphemes[0] ?? [];
            if (strlen($labelComparable) < 4
                || count($graphemes) !== 2
                || preg_match('/[\p{L}\p{N}\s]/u', implode('', $graphemes)) === 1) {
                continue;
            }
            $markerGlobalIndex = is_int($marker['sourcePdfGlobalSourceIndex'] ?? null)
                ? $marker['sourcePdfGlobalSourceIndex']
                : $sourceIndex + 1;
            $candidateSourceIndexes[$globalSourceIndex] = true;
            $candidateSourceIndexes[$markerGlobalIndex] = true;
            $candidatePages[max(1, (int) ($item['page'] ?? 1))] = true;
        }
        if ($candidateSourceIndexes === []) {
            return;
        }

        $runsBySourceIndex = [];
        $pageBounds = [];
        foreach ($positionedRuns as $runIndex => $run) {
            if (!is_array($run) || !$this->pdfLayoutHasGeometry($run)) {
                continue;
            }
            $page = max(1, (int) ($run['page'] ?? 1));
            if (isset($candidatePages[$page])
                && $this->pdfSourceProfileOrientation($run) === 'horizontal') {
                if (!isset($pageBounds[$page])) {
                    $pageBounds[$page] = [
                        'x1' => (float) $run['x1'],
                        'y1' => (float) $run['y1'],
                        'x2' => (float) $run['x2'],
                        'y2' => (float) $run['y2'],
                    ];
                } else {
                    $pageBounds[$page]['x1'] = min($pageBounds[$page]['x1'], (float) $run['x1']);
                    $pageBounds[$page]['y1'] = min($pageBounds[$page]['y1'], (float) $run['y1']);
                    $pageBounds[$page]['x2'] = max($pageBounds[$page]['x2'], (float) $run['x2']);
                    $pageBounds[$page]['y2'] = max($pageBounds[$page]['y2'], (float) $run['y2']);
                }
            }
            foreach ($this->pdfPositionedExactSourceRanges($run) as $range) {
                if (!isset($candidateSourceIndexes[$range['sourceIndex']])) {
                    continue;
                }
                $runsBySourceIndex[$range['sourceIndex']][] = [
                    'runIndex' => $runIndex,
                    'sourceStart' => $range['sourceStart'],
                    'sourceEnd' => $range['sourceEnd'],
                    'run' => $run,
                ];
            }
        }
        foreach ($runsBySourceIndex as &$entries) {
            usort(
                $entries,
                static fn (array $left, array $right): int =>
                    ($left['sourceStart'] <=> $right['sourceStart'])
                        ?: ($left['sourceEnd'] <=> $right['sourceEnd'])
                        ?: ($left['runIndex'] <=> $right['runIndex'])
            );
        }
        unset($entries);

        foreach ($lineItems as $sourceIndex => &$item) {
            $globalSourceIndex = is_int($item['sourcePdfGlobalSourceIndex'] ?? null)
                ? $item['sourcePdfGlobalSourceIndex']
                : $sourceIndex;
            $proof = $this->sourcePdfFacingFolioBoundaryProof(
                $item,
                $globalSourceIndex,
                $runsBySourceIndex[$globalSourceIndex] ?? [],
                $pageBounds[(int) ($item['page'] ?? 0)] ?? null
            );
            if ($proof !== null) {
                $item['sourcePdfFacingFolioBoundaryRepair'] = $proof;
            }
        }
        unset($item);

        $choiceRows = [];
        for ($sourceIndex = 0, $count = count($lineItems) - 1; $sourceIndex < $count; $sourceIndex++) {
            $label = $lineItems[$sourceIndex];
            $marker = $lineItems[$sourceIndex + 1];
            $labelGlobalIndex = is_int($label['sourcePdfGlobalSourceIndex'] ?? null)
                ? $label['sourcePdfGlobalSourceIndex']
                : $sourceIndex;
            $markerGlobalIndex = is_int($marker['sourcePdfGlobalSourceIndex'] ?? null)
                ? $marker['sourcePdfGlobalSourceIndex']
                : $sourceIndex + 1;
            $row = $this->sourcePdfChoiceMatrixRowCandidate(
                $label,
                $marker,
                $sourceIndex,
                $sourceIndex + 1,
                $labelGlobalIndex,
                $markerGlobalIndex,
                $runsBySourceIndex[$labelGlobalIndex] ?? [],
                $runsBySourceIndex[$markerGlobalIndex] ?? []
            );
            if ($row === null) {
                continue;
            }
            $key = $row['page'] . "\0" . $row['stream'] . "\0"
                . $row['prefixSymbolFontKey'] . "\0" . $row['markerFontKey'];
            $choiceRows[$key][] = $row;
        }

        foreach ($choiceRows as $rows) {
            $groupProof = $this->sourcePdfChoiceMatrixGroupProof(
                $rows,
                $positionedRuns,
                $pageBounds[(int) ($rows[0]['page'] ?? 0)] ?? null
            );
            if ($groupProof === null) {
                continue;
            }
            foreach ($rows as $row) {
                $labelIndex = $row['labelSourceIndex'];
                $markerIndex = $row['markerSourceIndex'];
                if (!isset($lineItems[$labelIndex], $lineItems[$markerIndex])) {
                    continue;
                }
                $lineItems[$labelIndex]['sourcePdfChoiceMatrixBoundaryRepair'] =
                    $this->sourcePdfBoundaryProjectionReceipt(
                        $lineItems[$labelIndex],
                        $row['labelGlobalIndex'],
                        'choice-matrix-label',
                        $row['labelProjection'],
                        $groupProof
                    );
                $lineItems[$markerIndex]['sourcePdfChoiceMatrixBoundaryRepair'] =
                    $this->sourcePdfBoundaryProjectionReceipt(
                        $lineItems[$markerIndex],
                        $row['markerGlobalIndex'],
                        'choice-matrix-marker-row',
                        '',
                        $groupProof
                    );
            }
        }
    }

    /**
     * @param array<string,mixed> $item
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $entries
     * @param array{x1:float,y1:float,x2:float,y2:float}|null $pageBounds
     * @return array<string,mixed>|null
     */
    private function sourcePdfFacingFolioBoundaryProof(
        array $item,
        int $globalSourceIndex,
        array $entries,
        ?array $pageBounds
    ): ?array {
        $sourceId = is_string($item['id'] ?? null) ? $item['id'] : '';
        $sourceText = is_string($item['text'] ?? null) ? $item['text'] : '';
        $page = (int) ($item['page'] ?? 0);
        $stream = (int) ($item['stream'] ?? 0);
        if ($sourceId === ''
            || $page < 1
            || $stream < 1
            || !$this->sourcePdfSourceItemHasExactGeometry($item)
            || $pageBounds === null
            || preg_match('/\A\s*(\d{1,4})\s+(\d{1,4})(?!\d)(.+)\z/us', $sourceText, $match) !== 1) {
            return null;
        }
        $leftFolio = $match[1];
        $rightFolio = $match[2];
        $titleProjection = trim($match[3]);
        $titleComparable = $this->pdfSourceOccurrenceComparableText($titleProjection);
        $sourceComparable = $this->pdfSourceOccurrenceComparableText($sourceText);
        $prefixEnd = strlen($leftFolio) + strlen($rightFolio);
        if ($titleProjection === ''
            || strlen($titleComparable) < 12
            || preg_match_all('/\p{L}+/u', $titleProjection, $words) < 3
            || !hash_equals($leftFolio . $rightFolio . $titleComparable, $sourceComparable)
            || abs((int) $rightFolio - (int) $leftFolio) !== 1) {
            return null;
        }
        $leftEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $entries,
            0,
            strlen($leftFolio)
        );
        $rightEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $entries,
            strlen($leftFolio),
            $prefixEnd
        );
        $titleEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $entries,
            $prefixEnd,
            strlen($sourceComparable)
        );
        if (count($leftEntries) !== 1
            || count($rightEntries) !== 1
            || $titleEntries === []) {
            return null;
        }
        $leftRun = $leftEntries[0]['run'];
        $rightRun = $rightEntries[0]['run'];
        if (!hash_equals(
            $leftFolio,
            $this->pdfSourceOccurrenceComparableText((string) ($leftRun['text'] ?? ''))
        ) || !hash_equals(
            $rightFolio,
            $this->pdfSourceOccurrenceComparableText((string) ($rightRun['text'] ?? ''))
        )) {
            return null;
        }

        $width = (float) $pageBounds['x2'] - (float) $pageBounds['x1'];
        $height = (float) $pageBounds['y2'] - (float) $pageBounds['y1'];
        $leftCenterX = ((float) $leftRun['x1'] + (float) $leftRun['x2']) / 2.0;
        $rightCenterX = ((float) $rightRun['x1'] + (float) $rightRun['x2']) / 2.0;
        $leftCenterY = ((float) $leftRun['y1'] + (float) $leftRun['y2']) / 2.0;
        $rightCenterY = ((float) $rightRun['y1'] + (float) $rightRun['y2']) / 2.0;
        $runHeight = max(
            1.0,
            (float) $leftRun['y2'] - (float) $leftRun['y1'],
            (float) $rightRun['y2'] - (float) $rightRun['y1']
        );
        $fontKey = static fn (array $run): string => implode("\0", [
            (string) ($run['fontResource'] ?? ''),
            (string) ($run['baseFont'] ?? ''),
            (string) ($run['fontSubtype'] ?? ''),
        ]);
        $sameBottomEdge = max($leftCenterY, $rightCenterY)
            <= (float) $pageBounds['y1'] + ($height * 0.10);
        $sameTopEdge = min($leftCenterY, $rightCenterY)
            >= (float) $pageBounds['y2'] - ($height * 0.10);
        if ($width <= 0.0
            || $height <= 0.0
            || $leftCenterX > (float) $pageBounds['x1'] + ($width * 0.10)
            || $rightCenterX < (float) $pageBounds['x2'] - ($width * 0.10)
            || $rightCenterX - $leftCenterX < $width * 0.75
            || abs($leftCenterY - $rightCenterY) > max(0.75, $runHeight * 0.12)
            || (!$sameBottomEdge && !$sameTopEdge)
            || $fontKey($leftRun) === "\0\0"
            || !hash_equals($fontKey($leftRun), $fontKey($rightRun))) {
            return null;
        }
        $titleBounds = $this->sourcePdfRunEntryBounds($titleEntries);
        if ($titleBounds === null
            || (float) $titleBounds['x1'] <= (float) $leftRun['x2']
            || (float) $titleBounds['x2'] >= (float) $rightRun['x1']) {
            return null;
        }

        $identity = [
            'version' => 1,
            'method' => 'exact-line-local-facing-folio-title-projection',
            'sourceSha256' => $this->sourceSha256,
            'sourceOccurrenceId' => $sourceId,
            'globalSourceIndex' => $globalSourceIndex,
            'page' => $page,
            'stream' => $stream,
            'sourceProjectionDigest' => hash('sha256', $sourceComparable),
            'textProjection' => $titleProjection,
            'textProjectionDigest' => hash('sha256', $titleComparable),
            'leftFolioRange' => [0, strlen($leftFolio)],
            'rightFolioRange' => [strlen($leftFolio), $prefixEnd],
            'titleRange' => [$prefixEnd, strlen($sourceComparable)],
            'folioRelation' => 'consecutive-facing-values',
            'edge' => $sameBottomEdge ? 'bottom' : 'top',
            'pageBounds' => $pageBounds,
            'leftRun' => $this->sourcePdfBoundaryRunEvidence($leftEntries[0]),
            'rightRun' => $this->sourcePdfBoundaryRunEvidence($rightEntries[0]),
            'titleRunDigest' => $this->sourcePdfBoundaryRunEntriesDigest($titleEntries),
            'sourceBounds' => $this->pdfSourceEvidenceBounds($item['sourceGeometry']),
        ];
        $encoded = json_encode(
            $identity,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return $identity + [
            'proofDigest' => hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($identity)
            ),
        ];
    }

    /**
     * @param array<string,mixed> $label
     * @param array<string,mixed> $marker
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $labelEntries
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $markerEntries
     * @return array<string,mixed>|null
     */
    private function sourcePdfChoiceMatrixRowCandidate(
        array $label,
        array $marker,
        int $labelSourceIndex,
        int $markerSourceIndex,
        int $labelGlobalIndex,
        int $markerGlobalIndex,
        array $labelEntries,
        array $markerEntries
    ): ?array {
        $labelId = is_string($label['id'] ?? null) ? $label['id'] : '';
        $markerId = is_string($marker['id'] ?? null) ? $marker['id'] : '';
        $page = (int) ($label['page'] ?? 0);
        $stream = (int) ($label['stream'] ?? 0);
        $labelComparable = $this->pdfSourceOccurrenceComparableText(
            (string) ($label['text'] ?? '')
        );
        $markerComparable = $this->pdfSourceOccurrenceComparableText(
            (string) ($marker['text'] ?? '')
        );
        if ($labelId === ''
            || $markerId === ''
            || $page < 1
            || $stream < 1
            || (int) ($marker['page'] ?? 0) !== $page
            || (int) ($marker['stream'] ?? 0) !== $stream
            || !$this->sourcePdfSourceItemHasExactGeometry($label)
            || !$this->sourcePdfSourceItemHasExactGeometry($marker)
            || strlen($labelComparable) < 4) {
            return null;
        }
        $orderedLabelEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $labelEntries,
            0,
            strlen($labelComparable)
        );
        if (count($orderedLabelEntries) < 3) {
            return null;
        }
        $first = $orderedLabelEntries[0];
        $second = $orderedLabelEntries[1];
        $firstText = $this->pdfSourceOccurrenceComparableText(
            (string) ($first['run']['text'] ?? '')
        );
        $secondText = $this->pdfSourceOccurrenceComparableText(
            (string) ($second['run']['text'] ?? '')
        );
        if ($first['sourceStart'] !== 0
            || $first['sourceEnd'] !== $second['sourceStart']
            || $firstText === ''
            || !hash_equals($firstText, $secondText)
            || preg_match('/\A\X\z/u', $firstText) !== 1
            || preg_match('/\A\X\z/u', $secondText) !== 1
            || ($first['run']['textOrigin'] ?? null) !== 'actual-text-replacement'
            || ($first['run']['actualTextPaintedWhitespaceOnly'] ?? null) !== true
            || ($second['run']['fontSymbolic'] ?? null) !== true) {
            return null;
        }
        $labelStart = $second['sourceEnd'];
        $labelProjection = $this->sourcePdfTextWithoutLeadingMarkers(
            (string) ($label['text'] ?? ''),
            [$firstText, $secondText]
        );
        $labelProjectionComparable = $this->pdfSourceOccurrenceComparableText($labelProjection);
        if ($labelProjection === ''
            || preg_match('/\p{L}/u', $labelProjection) !== 1
            || !hash_equals(substr($labelComparable, $labelStart), $labelProjectionComparable)) {
            return null;
        }
        $labelTextEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $orderedLabelEntries,
            $labelStart,
            strlen($labelComparable)
        );
        $labelTextRun = null;
        foreach ($labelTextEntries as $entry) {
            if (preg_match('/\p{L}/u', (string) ($entry['run']['text'] ?? '')) === 1
                && ($entry['run']['fontSymbolic'] ?? false) !== true) {
                $labelTextRun = $entry['run'];
                break;
            }
        }
        if ($labelTextRun === null) {
            return null;
        }

        preg_match_all('/\X/u', $markerComparable, $markerGraphemes);
        if (count($markerGraphemes[0] ?? []) !== 2) {
            return null;
        }
        foreach ($markerGraphemes[0] as $grapheme) {
            if (preg_match('/[\p{L}\p{N}\s]/u', $grapheme) === 1) {
                return null;
            }
        }
        $firstMarkerEnd = strlen($markerGraphemes[0][0]);
        $orderedMarkerEntries = $this->sourcePdfExactRunEntriesCoverRange(
            $markerEntries,
            0,
            strlen($markerComparable)
        );
        if (count($orderedMarkerEntries) !== 2
            || $orderedMarkerEntries[0]['sourceStart'] !== 0
            || $orderedMarkerEntries[0]['sourceEnd'] !== $firstMarkerEnd
            || $orderedMarkerEntries[1]['sourceStart'] !== $firstMarkerEnd
            || $orderedMarkerEntries[1]['sourceEnd'] !== strlen($markerComparable)) {
            return null;
        }
        $markerRunA = $orderedMarkerEntries[0]['run'];
        $markerRunB = $orderedMarkerEntries[1]['run'];
        $markerFontKey = $this->sourcePdfPositionedFontKey($markerRunA);
        $labelFontKey = $this->sourcePdfPositionedFontKey($labelTextRun);
        $prefixSymbolFontKey = $this->sourcePdfPositionedFontKey($second['run']);
        if ($markerFontKey === ''
            || $labelFontKey === ''
            || $prefixSymbolFontKey === ''
            || !hash_equals($markerFontKey, $this->sourcePdfPositionedFontKey($markerRunB))
            || hash_equals($markerFontKey, $labelFontKey)) {
            return null;
        }
        $labelBaseline = $this->sourcePdfRunBaseline($labelTextRun);
        $markerBaselineA = $this->sourcePdfRunBaseline($markerRunA);
        $markerBaselineB = $this->sourcePdfRunBaseline($markerRunB);
        $rowHeight = max(
            1.0,
            (float) $label['sourceGeometry']['y2'] - (float) $label['sourceGeometry']['y1'],
            (float) $marker['sourceGeometry']['y2'] - (float) $marker['sourceGeometry']['y1']
        );
        $slotA = $this->sourcePdfRunCenterX($markerRunA);
        $slotB = $this->sourcePdfRunCenterX($markerRunB);
        if ($slotB - $slotA < $rowHeight * 2.0
            || abs($markerBaselineA - $markerBaselineB) > max(0.75, $rowHeight * 0.12)
            || abs($labelBaseline - $markerBaselineA) > max(1.0, $rowHeight * 0.22)
            || $slotA <= (float) $label['sourceGeometry']['x2'] + $rowHeight) {
            return null;
        }

        return [
            'page' => $page,
            'stream' => $stream,
            'labelSourceIndex' => $labelSourceIndex,
            'markerSourceIndex' => $markerSourceIndex,
            'labelGlobalIndex' => $labelGlobalIndex,
            'markerGlobalIndex' => $markerGlobalIndex,
            'labelId' => $labelId,
            'markerId' => $markerId,
            'labelProjection' => $labelProjection,
            'labelProjectionDigest' => hash('sha256', $labelProjectionComparable),
            'labelSourceDigest' => hash('sha256', $labelComparable),
            'markerSourceDigest' => hash('sha256', $markerComparable),
            'labelBaseline' => $labelBaseline,
            'markerBaseline' => ($markerBaselineA + $markerBaselineB) / 2.0,
            'rowHeight' => $rowHeight,
            'prefixCenter' => $this->sourcePdfRunCenterX($second['run']),
            'labelStartX' => (float) ($labelTextRun['x1'] ?? 0.0),
            'slotCenters' => [$slotA, $slotB],
            'prefixSymbolFontKey' => $prefixSymbolFontKey,
            'markerFontKey' => $markerFontKey,
            'prefixRunEvidence' => [
                $this->sourcePdfBoundaryRunEvidence($first),
                $this->sourcePdfBoundaryRunEvidence($second),
            ],
            'markerRunEvidence' => [
                $this->sourcePdfBoundaryRunEvidence($orderedMarkerEntries[0]),
                $this->sourcePdfBoundaryRunEvidence($orderedMarkerEntries[1]),
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $positionedRuns
     * @param array{x1:float,y1:float,x2:float,y2:float}|null $pageBounds
     * @return array<string,mixed>|null
     */
    private function sourcePdfChoiceMatrixGroupProof(
        array $rows,
        array $positionedRuns,
        ?array $pageBounds
    ): ?array {
        if (count($rows) < 3 || $pageBounds === null) {
            return null;
        }
        usort(
            $rows,
            static fn (array $left, array $right): int =>
                (float) $right['labelBaseline'] <=> (float) $left['labelBaseline']
        );
        $steps = [];
        for ($index = 1; $index < count($rows); $index++) {
            $step = (float) $rows[$index - 1]['labelBaseline']
                - (float) $rows[$index]['labelBaseline'];
            if ($step <= 0.0) {
                return null;
            }
            $steps[] = $step;
        }
        $sortedSteps = $steps;
        sort($sortedSteps, SORT_NUMERIC);
        $cadence = $sortedSteps[intdiv(count($sortedSteps), 2)] ?? 0.0;
        $rowHeight = array_sum(array_column($rows, 'rowHeight')) / count($rows);
        $cadenceTolerance = max(1.0, $rowHeight * 0.28, $cadence * 0.15);
        foreach ($steps as $step) {
            if (abs($step - $cadence) > $cadenceTolerance) {
                return null;
            }
        }
        $prefixCenters = array_column($rows, 'prefixCenter');
        $labelStarts = array_column($rows, 'labelStartX');
        $slotA = array_map(static fn (array $row): float => $row['slotCenters'][0], $rows);
        $slotB = array_map(static fn (array $row): float => $row['slotCenters'][1], $rows);
        $columnTolerance = max(2.0, $rowHeight * 0.45);
        foreach ([$prefixCenters, $labelStarts, $slotA, $slotB] as $values) {
            if (max($values) - min($values) > $columnTolerance) {
                return null;
            }
        }
        foreach ($rows as $row) {
            if (abs((float) $row['labelBaseline'] - (float) $row['markerBaseline'])
                > max(1.0, $rowHeight * 0.22)) {
                return null;
            }
        }

        $slotCenters = [array_sum($slotA) / count($slotA), array_sum($slotB) / count($slotB)];
        $topBaseline = max(array_column($rows, 'labelBaseline'));
        $pageHeight = max(1.0, (float) $pageBounds['y2'] - (float) $pageBounds['y1']);
        $headerEvidence = [];
        foreach ($slotCenters as $slotCenter) {
            $best = null;
            foreach ($positionedRuns as $runIndex => $run) {
                if (!is_array($run)
                    || (int) ($run['page'] ?? 0) !== (int) $rows[0]['page']
                    || (int) ($run['stream'] ?? 0) !== (int) $rows[0]['stream']
                    || !$this->pdfLayoutHasGeometry($run)
                    || preg_match('/\p{L}/u', (string) ($run['text'] ?? '')) !== 1
                    || hash_equals(
                        (string) $rows[0]['markerFontKey'],
                        $this->sourcePdfPositionedFontKey($run)
                    )) {
                    continue;
                }
                $baseline = $this->sourcePdfRunBaseline($run);
                $deltaY = $baseline - $topBaseline;
                $deltaX = abs($this->sourcePdfRunCenterX($run) - $slotCenter);
                if ($deltaY < $rowHeight * 0.4
                    || $deltaY > $pageHeight * 0.25
                    || $deltaX > max(18.0, $rowHeight * 3.0)) {
                    continue;
                }
                $score = $deltaX + ($deltaY * 0.05);
                if ($best === null || $score < $best['score']) {
                    $best = [
                        'score' => $score,
                        'runIndex' => $runIndex,
                        'textDigest' => hash('sha256', (string) $run['text']),
                        'centerX' => $this->sourcePdfRunCenterX($run),
                        'baseline' => $baseline,
                        'fontKeyDigest' => hash(
                            'sha256',
                            $this->sourcePdfPositionedFontKey($run)
                        ),
                    ];
                }
            }
            if ($best === null) {
                return null;
            }
            unset($best['score']);
            $headerEvidence[] = $best;
        }

        $rowEvidence = array_map(
            static fn (array $row): array => [
                'labelId' => $row['labelId'],
                'markerId' => $row['markerId'],
                'labelGlobalIndex' => $row['labelGlobalIndex'],
                'markerGlobalIndex' => $row['markerGlobalIndex'],
                'labelSourceDigest' => $row['labelSourceDigest'],
                'labelProjectionDigest' => $row['labelProjectionDigest'],
                'markerSourceDigest' => $row['markerSourceDigest'],
                'labelBaseline' => $row['labelBaseline'],
                'markerBaseline' => $row['markerBaseline'],
                'prefixRunEvidence' => $row['prefixRunEvidence'],
                'markerRunEvidence' => $row['markerRunEvidence'],
            ],
            $rows
        );
        $identity = [
            'version' => 1,
            'method' => 'exact-recurring-actualtext-symbol-choice-matrix',
            'sourceSha256' => $this->sourceSha256,
            'page' => (int) $rows[0]['page'],
            'stream' => (int) $rows[0]['stream'],
            'rowCount' => count($rows),
            'slotCount' => 2,
            'verticalCadence' => $cadence,
            'prefixColumn' => array_sum($prefixCenters) / count($prefixCenters),
            'labelColumn' => array_sum($labelStarts) / count($labelStarts),
            'slotCenters' => $slotCenters,
            'prefixSymbolFontDigest' => hash('sha256', (string) $rows[0]['prefixSymbolFontKey']),
            'markerFontDigest' => hash('sha256', (string) $rows[0]['markerFontKey']),
            'headerEvidence' => $headerEvidence,
            'rows' => $rowEvidence,
        ];
        $encoded = json_encode(
            $identity,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return $identity + [
            'proofDigest' => hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($identity)
            ),
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $groupProof
     * @return array<string,mixed>
     */
    private function sourcePdfBoundaryProjectionReceipt(
        array $item,
        int $globalSourceIndex,
        string $role,
        string $textProjection,
        array $groupProof
    ): array {
        $sourceComparable = $this->pdfSourceOccurrenceComparableText(
            (string) ($item['text'] ?? '')
        );
        $projectionComparable = $this->pdfSourceOccurrenceComparableText($textProjection);
        $identity = [
            'version' => 1,
            'method' => 'exact-choice-matrix-source-projection',
            'sourceSha256' => $this->sourceSha256,
            'sourceOccurrenceId' => (string) ($item['id'] ?? ''),
            'globalSourceIndex' => $globalSourceIndex,
            'page' => (int) ($item['page'] ?? 0),
            'stream' => (int) ($item['stream'] ?? 0),
            'role' => $role,
            'sourceProjectionDigest' => hash('sha256', $sourceComparable),
            'textProjection' => $textProjection,
            'textProjectionDigest' => hash('sha256', $projectionComparable),
            'groupProofDigest' => (string) ($groupProof['proofDigest'] ?? ''),
            'groupEvidence' => $groupProof,
        ];
        $encoded = json_encode(
            $identity,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return $identity + [
            'proofDigest' => hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($identity)
            ),
        ];
    }

    /**
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $entries
     * @return list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}>
     */
    private function sourcePdfExactRunEntriesCoverRange(
        array $entries,
        int $start,
        int $end
    ): array {
        if ($start < 0 || $end <= $start) {
            return [];
        }
        $covered = [];
        $cursor = $start;
        foreach ($entries as $entry) {
            $entryStart = (int) ($entry['sourceStart'] ?? -1);
            $entryEnd = (int) ($entry['sourceEnd'] ?? -1);
            if ($entryEnd <= $start || $entryStart >= $end) {
                continue;
            }
            if ($entryStart !== $cursor || $entryEnd > $end) {
                return [];
            }
            $covered[] = $entry;
            $cursor = $entryEnd;
        }

        return $cursor === $end ? $covered : [];
    }

    /**
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $entries
     * @return array{x1:float,y1:float,x2:float,y2:float}|null
     */
    private function sourcePdfRunEntryBounds(array $entries): ?array
    {
        $bounds = null;
        foreach ($entries as $entry) {
            $run = $entry['run'] ?? null;
            if (!is_array($run) || !$this->pdfLayoutHasGeometry($run)) {
                return null;
            }
            if ($bounds === null) {
                $bounds = [
                    'x1' => (float) $run['x1'],
                    'y1' => (float) $run['y1'],
                    'x2' => (float) $run['x2'],
                    'y2' => (float) $run['y2'],
                ];
                continue;
            }
            $bounds['x1'] = min($bounds['x1'], (float) $run['x1']);
            $bounds['y1'] = min($bounds['y1'], (float) $run['y1']);
            $bounds['x2'] = max($bounds['x2'], (float) $run['x2']);
            $bounds['y2'] = max($bounds['y2'], (float) $run['y2']);
        }

        return $bounds;
    }

    /** @param array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>} $entry */
    private function sourcePdfBoundaryRunEvidence(array $entry): array
    {
        $run = $entry['run'];

        return [
            'runIndex' => $entry['runIndex'],
            'sourceStart' => $entry['sourceStart'],
            'sourceEnd' => $entry['sourceEnd'],
            'textDigest' => hash(
                'sha256',
                $this->pdfSourceOccurrenceComparableText((string) ($run['text'] ?? ''))
            ),
            'bounds' => [
                'x1' => (float) ($run['x1'] ?? 0.0),
                'y1' => (float) ($run['y1'] ?? 0.0),
                'x2' => (float) ($run['x2'] ?? 0.0),
                'y2' => (float) ($run['y2'] ?? 0.0),
            ],
            'fontResource' => is_string($run['fontResource'] ?? null)
                ? $run['fontResource']
                : null,
            'baseFont' => is_string($run['baseFont'] ?? null)
                ? $run['baseFont']
                : null,
            'fontSubtype' => is_string($run['fontSubtype'] ?? null)
                ? $run['fontSubtype']
                : null,
            'fontSymbolic' => is_bool($run['fontSymbolic'] ?? null)
                ? $run['fontSymbolic']
                : null,
            'textOrigin' => is_string($run['textOrigin'] ?? null)
                ? $run['textOrigin']
                : null,
            'actualTextPaintedWhitespaceOnly' =>
                ($run['actualTextPaintedWhitespaceOnly'] ?? null) === true,
        ];
    }

    /**
     * @param list<array{runIndex:int,sourceStart:int,sourceEnd:int,run:array<string,mixed>}> $entries
     */
    private function sourcePdfBoundaryRunEntriesDigest(array $entries): string
    {
        $evidence = array_map(
            fn (array $entry): array => $this->sourcePdfBoundaryRunEvidence($entry),
            $entries
        );

        return hash('sha256', json_encode(
            $evidence,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        ) ?: serialize($evidence));
    }

    /** @param array<string,mixed> $run */
    private function sourcePdfPositionedFontKey(array $run): string
    {
        $resource = is_string($run['fontResource'] ?? null) ? $run['fontResource'] : '';
        $baseFont = is_string($run['baseFont'] ?? null) ? $run['baseFont'] : '';
        $subtype = is_string($run['fontSubtype'] ?? null) ? $run['fontSubtype'] : '';

        return $resource === '' || $baseFont === ''
            ? ''
            : implode("\0", [$resource, $baseFont, $subtype]);
    }

    /** @param array<string,mixed> $run */
    private function sourcePdfRunBaseline(array $run): float
    {
        return is_numeric($run['textY1'] ?? null)
            ? (float) $run['textY1']
            : (((float) ($run['y1'] ?? 0.0) + (float) ($run['y2'] ?? 0.0)) / 2.0);
    }

    /** @param array<string,mixed> $run */
    private function sourcePdfRunCenterX(array $run): float
    {
        $x1 = is_numeric($run['textX1'] ?? null) ? (float) $run['textX1'] : (float) ($run['x1'] ?? 0.0);
        $x2 = is_numeric($run['textX2'] ?? null) ? (float) $run['textX2'] : (float) ($run['x2'] ?? 0.0);

        return ($x1 + $x2) / 2.0;
    }

    /** @param list<string> $markers */
    private function sourcePdfTextWithoutLeadingMarkers(string $text, array $markers): string
    {
        $remaining = $text;
        foreach ($markers as $marker) {
            if ($marker === ''
                || preg_match(
                    '/\A[\s\p{Cc}\p{Cf}]*' . preg_quote($marker, '/') . '/u',
                    $remaining,
                    $match
                ) !== 1) {
                return '';
            }
            $remaining = substr($remaining, strlen($match[0]));
        }

        return trim($remaining);
    }

    /**
     * Project only source-derived layouts whose immutable occurrence and
     * signed repair receipt still match. Positioned-only lookalikes, stale
     * receipts, and ambiguous composites retain their literal text.
     *
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     * @return list<array<string,mixed>>
     */
    private function pdfSourceLayoutsWithFacingFolioAndChoiceMatrixProjections(
        array $layouts,
        array $sourceItems
    ): array {
        $this->pdfSourceLayoutsWithFacingFolioAndChoiceMatrixProjectionsInPlace(
            $layouts,
            $sourceItems
        );

        return $layouts;
    }

    /**
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     */
    private function pdfSourceLayoutsWithFacingFolioAndChoiceMatrixProjectionsInPlace(
        array &$layouts,
        array $sourceItems
    ): void {
        $hasProjectionReceipt = false;
        foreach ($sourceItems as $sourceItem) {
            if (is_array($sourceItem['sourcePdfFacingFolioBoundaryRepair'] ?? null)
                || is_array($sourceItem['sourcePdfChoiceMatrixBoundaryRepair'] ?? null)) {
                $hasProjectionReceipt = true;
                break;
            }
        }
        if (!$hasProjectionReceipt) {
            return;
        }
        $sourcesById = [];
        $sourcesByGlobalIndex = [];
        foreach ($layouts as $layout) {
            $layoutId = is_string($layout['id'] ?? null) ? $layout['id'] : '';
            if ($layoutId !== '') {
                $sourcesById[$layoutId] = false;
            } elseif (is_int($layout['sourcePdfGlobalSourceIndex'] ?? null)) {
                $sourcesByGlobalIndex[$layout['sourcePdfGlobalSourceIndex']] = false;
            }
        }
        foreach ($sourceItems as $sourceIndex => $sourceItem) {
            $id = is_string($sourceItem['id'] ?? null) ? $sourceItem['id'] : '';
            $globalIndex = is_int($sourceItem['sourcePdfGlobalSourceIndex'] ?? null)
                ? $sourceItem['sourcePdfGlobalSourceIndex']
                : $sourceIndex;
            if ($id !== '' && array_key_exists($id, $sourcesById)) {
                $sourcesById[$id] = $sourcesById[$id] === false
                    ? $sourceIndex
                    : null;
            }
            if (array_key_exists($globalIndex, $sourcesByGlobalIndex)) {
                $sourcesByGlobalIndex[$globalIndex] =
                    $sourcesByGlobalIndex[$globalIndex] === false
                        ? $sourceIndex
                        : null;
            }
        }

        foreach ($layouts as &$layout) {
            $layoutId = is_string($layout['id'] ?? null) ? $layout['id'] : '';
            if ($layoutId !== '') {
                $sourceIndex = $sourcesById[$layoutId] ?? null;
            } elseif (is_int($layout['sourcePdfGlobalSourceIndex'] ?? null)) {
                $sourceIndex = $sourcesByGlobalIndex[
                    $layout['sourcePdfGlobalSourceIndex']
                ] ?? null;
            } else {
                $sourceIndex = null;
            }
            if (!is_int($sourceIndex)) {
                continue;
            }
            $sourceItem = $sourceItems[$sourceIndex];
            $sourceComparable = $this->pdfSourceOccurrenceComparableText(
                (string) ($sourceItem['text'] ?? '')
            );
            $layoutComparable = $this->pdfSourceOccurrenceComparableText(
                (string) ($layout['text'] ?? '')
            );
            if ($sourceComparable === '' || !hash_equals($sourceComparable, $layoutComparable)) {
                continue;
            }
            $proof = is_array($sourceItem['sourcePdfFacingFolioBoundaryRepair'] ?? null)
                ? $sourceItem['sourcePdfFacingFolioBoundaryRepair']
                : (is_array($sourceItem['sourcePdfChoiceMatrixBoundaryRepair'] ?? null)
                    ? $sourceItem['sourcePdfChoiceMatrixBoundaryRepair']
                    : null);
            if ($proof === null
                || !$this->sourcePdfBoundaryProjectionProofMatchesItem(
                    $proof,
                    $sourceItem,
                    $sourceIndex
                )) {
                continue;
            }
            $layout['text'] = (string) $proof['textProjection'];
            $layout['sourcePdfBoundaryProjectionReceipt'] = $proof;
        }
        unset($layout);
    }

    /**
     * @param array<string,mixed> $proof
     * @param array<string,mixed> $item
     */
    private function sourcePdfBoundaryProjectionProofMatchesItem(
        array $proof,
        array $item,
        int $sourceIndex
    ): bool {
        $method = $proof['method'] ?? null;
        $sourceId = is_string($item['id'] ?? null) ? $item['id'] : '';
        $globalIndex = is_int($item['sourcePdfGlobalSourceIndex'] ?? null)
            ? $item['sourcePdfGlobalSourceIndex']
            : $sourceIndex;
        $textProjection = $proof['textProjection'] ?? null;
        if (!in_array($method, [
            'exact-line-local-facing-folio-title-projection',
            'exact-choice-matrix-source-projection',
        ], true)
            || $sourceId === ''
            || !is_string($textProjection)
            || ($proof['sourceSha256'] ?? null) !== $this->sourceSha256
            || ($proof['sourceOccurrenceId'] ?? null) !== $sourceId
            || ($proof['globalSourceIndex'] ?? null) !== $globalIndex
            || ($proof['page'] ?? null) !== (int) ($item['page'] ?? 0)
            || ($proof['stream'] ?? null) !== (int) ($item['stream'] ?? 0)
            || ($proof['sourceProjectionDigest'] ?? null) !== hash(
                'sha256',
                $this->pdfSourceOccurrenceComparableText((string) ($item['text'] ?? ''))
            )
            || ($proof['textProjectionDigest'] ?? null) !== hash(
                'sha256',
                $this->pdfSourceOccurrenceComparableText($textProjection)
            )
            || !$this->sourcePdfSignedProofDigestMatches($proof)) {
            return false;
        }

        if ($method === 'exact-line-local-facing-folio-title-projection') {
            return $textProjection !== ''
                && ($proof['folioRelation'] ?? null) === 'consecutive-facing-values'
                && in_array($proof['edge'] ?? null, ['top', 'bottom'], true)
                && is_array($proof['leftRun'] ?? null)
                && is_array($proof['rightRun'] ?? null);
        }

        $role = $proof['role'] ?? null;
        $groupProof = is_array($proof['groupEvidence'] ?? null)
            ? $proof['groupEvidence']
            : null;
        if (!in_array($role, ['choice-matrix-label', 'choice-matrix-marker-row'], true)
            || ($role === 'choice-matrix-label' && $textProjection === '')
            || ($role === 'choice-matrix-marker-row' && $textProjection !== '')
            || $groupProof === null
            || ($groupProof['method'] ?? null)
                !== 'exact-recurring-actualtext-symbol-choice-matrix'
            || ($groupProof['sourceSha256'] ?? null) !== $this->sourceSha256
            || (int) ($groupProof['rowCount'] ?? 0) < 3
            || (int) ($groupProof['slotCount'] ?? 0) < 2
            || ($proof['groupProofDigest'] ?? null) !== ($groupProof['proofDigest'] ?? null)
            || !$this->sourcePdfSignedProofDigestMatches($groupProof)) {
            return false;
        }

        foreach ($groupProof['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($role === 'choice-matrix-label'
                && ($row['labelId'] ?? null) === $sourceId
                && ($row['labelGlobalIndex'] ?? null) === $globalIndex) {
                return true;
            }
            if ($role === 'choice-matrix-marker-row'
                && ($row['markerId'] ?? null) === $sourceId
                && ($row['markerGlobalIndex'] ?? null) === $globalIndex) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $item */
    private function sourcePdfSourceItemHasExactGeometry(array $item): bool
    {
        return ($this->sourceItemHasExactGeometry)($item);
    }

    private function pdfSourceOccurrenceComparableText(string $text): string
    {
        return ($this->comparableText)($text);
    }

    /** @param array<string,mixed>|null $layout */
    private function pdfLayoutHasGeometry(?array $layout): bool
    {
        return ($this->layoutHasGeometry)($layout);
    }

    /** @param array<string,mixed> $run */
    private function pdfSourceProfileOrientation(array $run): string
    {
        return ($this->profileOrientation)($run);
    }

    /**
     * @param array<string,mixed> $item
     * @return list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}>
     */
    private function pdfPositionedExactSourceRanges(array $item): array
    {
        return ($this->exactSourceRanges)($item);
    }

    /**
     * @param array<string,mixed> $geometry
     * @return array{x1:float,y1:float,x2:float,y2:float}
     */
    private function pdfSourceEvidenceBounds(array $geometry): array
    {
        return ($this->sourceEvidenceBounds)($geometry);
    }

    /** @param array<string,mixed> $proof */
    private function sourcePdfSignedProofDigestMatches(array $proof): bool
    {
        $declared = $proof['proofDigest'] ?? null;
        if (!is_string($declared) || preg_match('/\\A[0-9a-f]{64}\\z/D', $declared) !== 1) {
            return false;
        }
        unset($proof['proofDigest']);
        $encoded = json_encode(
            $proof,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return hash_equals(
            $declared,
            hash('sha256', is_string($encoded) ? $encoded : serialize($proof))
        );
    }
}
