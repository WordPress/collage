<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily loaded exact nested-caution detector and source-order repair.
 */
final class PdfNestedCautionCalloutRepair
{
    private string $sourceSha256;
    private \Closure $comparableTextCallback;
    private \Closure $exactGeometryCallback;
    private \Closure $evidenceBoundsCallback;
    private \Closure $geometryDigestCallback;
    private \Closure $normalizeTextCallback;
    private \Closure $wordTokensCallback;
    private \Closure $lengthCallback;
    private \Closure $listEvidenceCallback;
    private \Closure $urlOnlyCallback;
    private \Closure $semanticPrefixCallback;
    private \Closure $medianCallback;
    private \Closure $signedProofCallback;
    private \Closure $exactRangesCallback;
    private \Closure $stampedProofsCallback;

    private function __construct(
        string $sourceSha256,
        callable $comparableText,
        callable $exactGeometry,
        callable $evidenceBounds,
        callable $geometryDigest,
        callable $normalizeText,
        callable $wordTokens,
        callable $length,
        callable $listEvidence,
        callable $urlOnly,
        callable $semanticPrefix,
        callable $median,
        callable $signedProof,
        callable $exactRanges,
        callable $stampedProofs
    ) {
        $this->sourceSha256 = $sourceSha256;
        $this->comparableTextCallback = \Closure::fromCallable($comparableText);
        $this->exactGeometryCallback = \Closure::fromCallable($exactGeometry);
        $this->evidenceBoundsCallback = \Closure::fromCallable($evidenceBounds);
        $this->geometryDigestCallback = \Closure::fromCallable($geometryDigest);
        $this->normalizeTextCallback = \Closure::fromCallable($normalizeText);
        $this->wordTokensCallback = \Closure::fromCallable($wordTokens);
        $this->lengthCallback = \Closure::fromCallable($length);
        $this->listEvidenceCallback = \Closure::fromCallable($listEvidence);
        $this->urlOnlyCallback = \Closure::fromCallable($urlOnly);
        $this->semanticPrefixCallback = \Closure::fromCallable($semanticPrefix);
        $this->medianCallback = \Closure::fromCallable($median);
        $this->signedProofCallback = \Closure::fromCallable($signedProof);
        $this->exactRangesCallback = \Closure::fromCallable($exactRanges);
        $this->stampedProofsCallback = \Closure::fromCallable($stampedProofs);
    }

    /** @param list<mixed> $arguments */
    public static function invoke(
        string $method,
        array $arguments,
        string $sourceSha256,
        callable $comparableText,
        callable $exactGeometry,
        callable $evidenceBounds,
        callable $geometryDigest,
        callable $normalizeText,
        callable $wordTokens,
        callable $length,
        callable $listEvidence,
        callable $urlOnly,
        callable $semanticPrefix,
        callable $median,
        callable $signedProof,
        callable $exactRanges,
        callable $stampedProofs
    ): mixed {
        $repair = new self(
            $sourceSha256,
            $comparableText,
            $exactGeometry,
            $evidenceBounds,
            $geometryDigest,
            $normalizeText,
            $wordTokens,
            $length,
            $listEvidence,
            $urlOnly,
            $semanticPrefix,
            $median,
            $signedProof,
            $exactRanges,
            $stampedProofs
        );

        return $repair->{$method}(...$arguments);
    }

    /**
     * Detect a compact admonition without relying on its spelling. The source
     * must paint one oversized symbol, a nested punctuation atom, and a small
     * uppercase label as consecutive exact occurrences. At least two further
     * exact occurrences must form a stable prose lane immediately to its
     * right and the final row must close a sentence. This is strong enough to
     * distinguish a decorative icon from literal punctuation while retaining
     * every label and warning byte as editable text.
     *
     * @param list<array<string,mixed>> $lineItems
     * @return list<array<string,mixed>>
     */
    private function sourcePdfExactNestedCautionCalloutProofs(array $lineItems): array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $this->sourceSha256) !== 1
            || count($lineItems) < 5) {
            return [];
        }

        $proofs = [];
        for ($sourceIndex = 0, $count = count($lineItems); $sourceIndex + 4 < $count; $sourceIndex++) {
            $symbol = $this->sourcePdfExactNestedCautionCalloutMember(
                $lineItems[$sourceIndex],
                $sourceIndex
            );
            $punctuation = $this->sourcePdfExactNestedCautionCalloutMember(
                $lineItems[$sourceIndex + 1],
                $sourceIndex + 1
            );
            $label = $this->sourcePdfExactNestedCautionCalloutMember(
                $lineItems[$sourceIndex + 2],
                $sourceIndex + 2
            );
            if ($symbol === null
                || $punctuation === null
                || $label === null
                || !$this->sourcePdfExactNestedCautionMembersAreConsecutive([
                    $symbol,
                    $punctuation,
                    $label,
                ])
                || preg_match('/^\p{S}$/u', $symbol['text']) !== 1
                || preg_match('/^\p{P}$/u', $punctuation['text']) !== 1
                || !$this->sourcePdfTextIsCompactUppercaseCalloutLabel($label['text'])) {
                continue;
            }

            $symbolBounds = $symbol['bounds'];
            $punctuationBounds = $punctuation['bounds'];
            $labelBounds = $label['bounds'];
            if (!$this->sourcePdfBoundsContainBounds($symbolBounds, $punctuationBounds, 0.75)
                || !$this->sourcePdfBoundsContainBounds($symbolBounds, $labelBounds, 0.75)
                || $symbol['height'] < $punctuation['height'] * 1.35
                || $symbol['height'] < $label['height'] * 3.0
                || $symbol['width'] < $label['width'] * 1.15
                || $punctuation['height'] < $label['height'] * 1.5) {
                continue;
            }

            $proseRows = [];
            $rowHeight = null;
            $laneX1 = null;
            $previousY1 = null;
            $verticalDirection = null;
            $expectedGlobalIndex = $label['sourceGlobalIndex'] + 1;
            for ($rowIndex = $sourceIndex + 3;
                $rowIndex < $count && count($proseRows) < 8;
                $rowIndex++, $expectedGlobalIndex++) {
                $row = $this->sourcePdfExactNestedCautionCalloutMember(
                    $lineItems[$rowIndex],
                    $rowIndex
                );
                if ($row === null
                    || $row['page'] !== $symbol['page']
                    || $row['stream'] !== $symbol['stream']
                    || $row['sourceGlobalIndex'] !== $expectedGlobalIndex
                    || !$this->sourcePdfTextIsSubstantiveCalloutProseRow($row['text'])) {
                    break;
                }

                $rowHeight ??= $row['height'];
                $laneX1 ??= $row['bounds']['x1'];
                $heightTolerance = max(0.75, $rowHeight * 0.15);
                $laneTolerance = max(3.0, $rowHeight * 0.45);
                $symbolLaneTolerance = max(3.0, $rowHeight * 0.35);
                $verticalOverlap = min($symbolBounds['y2'], $row['bounds']['y2'])
                    - max($symbolBounds['y1'], $row['bounds']['y1']);
                if (abs($row['height'] - $rowHeight) > $heightTolerance
                    || abs($row['bounds']['x1'] - $laneX1) > $laneTolerance
                    || $row['bounds']['x1'] < $symbolBounds['x2'] - $symbolLaneTolerance
                    || $row['width'] < max(96.0, $symbol['width'] * 2.5)
                    || ($proseRows === [] && $verticalOverlap <= 0.0)) {
                    break;
                }
                if ($previousY1 !== null) {
                    $step = $row['bounds']['y1'] - $previousY1;
                    $direction = $step < 0.0 ? -1 : 1;
                    if (abs($step) < $rowHeight * 0.45
                        || abs($step) > $rowHeight * 1.5
                        || ($verticalDirection !== null && $direction !== $verticalDirection)) {
                        break;
                    }
                    $verticalDirection ??= $direction;
                }
                $proseRows[] = $row;
                $previousY1 = $row['bounds']['y1'];
            }
            if (count($proseRows) < 2
                || $verticalDirection === null
                || preg_match(
                    '/[.!?](?:["\'\x{2019}\x{201D})\]])*$/u',
                    $proseRows[array_key_last($proseRows)]['text']
                ) !== 1) {
                continue;
            }

            $retainedMembers = array_merge([$label], $proseRows);
            $retainedProjection = implode('', array_column(
                $retainedMembers,
                'projection'
            ));
            if ($retainedProjection === '') {
                continue;
            }
            $proof = [
                'version' => 1,
                'method' => 'exact-source-nested-symbol-label-right-lane-warning',
                'sourceSha256' => $this->sourceSha256,
                'page' => $symbol['page'],
                'sourceStream' => $symbol['stream'],
                'iconAtoms' => [
                    $this->sourcePdfNestedCautionCalloutProofMember(
                        $symbol,
                        'decorative-symbol'
                    ),
                    $this->sourcePdfNestedCautionCalloutProofMember(
                        $punctuation,
                        'decorative-punctuation'
                    ),
                ],
                'label' => $this->sourcePdfNestedCautionCalloutProofMember($label),
                'proseRows' => array_map(
                    fn (array $row): array =>
                        $this->sourcePdfNestedCautionCalloutProofMember($row),
                    $proseRows
                ),
                'geometryEvidence' => [
                    'symbolToLabelHeightRatio' => round(
                        $symbol['height'] / max(0.01, $label['height']),
                        4
                    ),
                    'punctuationToLabelHeightRatio' => round(
                        $punctuation['height'] / max(0.01, $label['height']),
                        4
                    ),
                    'proseRowCount' => count($proseRows),
                    'proseMedianHeight' => round($this->median(array_column($proseRows, 'height')), 4),
                    'proseX1Spread' => round(
                        max(array_column(array_column($proseRows, 'bounds'), 'x1'))
                            - min(array_column(array_column($proseRows, 'bounds'), 'x1')),
                        4
                    ),
                    'verticalDirection' => $verticalDirection,
                ],
                'retainedProjectionDigest' => hash('sha256', $retainedProjection),
            ];
            $encoded = json_encode(
                $proof,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $proof['proofDigest'] = hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($proof)
            );
            $proofs[] = $proof;
            $sourceIndex = $proseRows[array_key_last($proseRows)]['sourceIndex'];
        }

        return $proofs;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>|null
     */
    private function sourcePdfExactNestedCautionCalloutMember(
        array $item,
        int $sourceIndex
    ): ?array {
        $text = trim((string) ($item['text'] ?? ''));
        $sourceOccurrenceId = is_string($item['id'] ?? null) ? $item['id'] : '';
        $declaredGlobalIndex = $item['sourcePdfGlobalSourceIndex'] ?? null;
        $sourceGlobalIndex = $declaredGlobalIndex === null
            ? $sourceIndex
            : (is_int($declaredGlobalIndex) ? $declaredGlobalIndex : null);
        $page = (int) ($item['page'] ?? 0);
        $stream = (int) ($item['stream'] ?? 0);
        $geometry = is_array($item['sourceGeometry'] ?? null)
            ? $item['sourceGeometry']
            : null;
        $projection = $this->pdfSourceOccurrenceComparableText($text);
        if ($sourceOccurrenceId === ''
            || $sourceGlobalIndex === null
            || $sourceGlobalIndex < 0
            || $page < 1
            || $stream < 1
            || $geometry === null
            || !$this->sourcePdfSourceItemHasExactGeometry($item)
            || (int) ($geometry['page'] ?? 0) !== $page
            || (int) ($geometry['stream'] ?? 0) !== $stream
            || !in_array((string) ($geometry['orientation'] ?? ''), ['', 'horizontal'], true)
            || $projection === '') {
            return null;
        }
        $sourceBounds = $this->pdfSourceEvidenceBounds($geometry);
        $bounds = [
            'x1' => min($sourceBounds['x1'], $sourceBounds['x2']),
            'y1' => min($sourceBounds['y1'], $sourceBounds['y2']),
            'x2' => max($sourceBounds['x1'], $sourceBounds['x2']),
            'y2' => max($sourceBounds['y1'], $sourceBounds['y2']),
        ];
        $width = $bounds['x2'] - $bounds['x1'];
        $height = $bounds['y2'] - $bounds['y1'];
        if ($width <= 0.0 || $height <= 0.0) {
            return null;
        }

        return [
            'sourceIndex' => $sourceIndex,
            'sourceGlobalIndex' => $sourceGlobalIndex,
            'sourceOccurrenceId' => $sourceOccurrenceId,
            'page' => $page,
            'stream' => $stream,
            'text' => $text,
            'projection' => $projection,
            'projectionDigest' => hash('sha256', $projection),
            'geometryDigest' => $this->sourcePdfExactGeometryProofDigest($geometry),
            'bounds' => $bounds,
            'width' => $width,
            'height' => $height,
        ];
    }

    /** @param list<array<string,mixed>> $members */
    private function sourcePdfExactNestedCautionMembersAreConsecutive(array $members): bool
    {
        if ($members === []) {
            return false;
        }
        $previous = null;
        foreach ($members as $member) {
            if ($previous !== null
                && ($member['page'] !== $previous['page']
                    || $member['stream'] !== $previous['stream']
                    || $member['sourceIndex'] !== $previous['sourceIndex'] + 1
                    || $member['sourceGlobalIndex'] !== $previous['sourceGlobalIndex'] + 1)) {
                return false;
            }
            $previous = $member;
        }

        return true;
    }

    private function sourcePdfTextIsCompactUppercaseCalloutLabel(string $text): bool
    {
        $text = trim($this->normalizePdfTextEncoding($text));
        $wordCount = count($this->pdfLineWordTokens($text));

        return $this->length($text) >= 3
            && $this->length($text) <= 28
            && $wordCount >= 1
            && $wordCount <= 3
            && preg_match('/\p{L}/u', $text) === 1
            && preg_match('/\p{Ll}/u', $text) !== 1
            && preg_match('/^[\p{Lu}\p{Lt}\p{N}\h&\/-]+$/u', $text) === 1;
    }

    private function sourcePdfTextIsSubstantiveCalloutProseRow(string $text): bool
    {
        $text = trim($this->normalizePdfTextEncoding($text));
        $wordCount = count($this->pdfLineWordTokens($text));

        return $this->length($text) >= 18
            && $this->length($text) <= 200
            && $wordCount >= 4
            && $wordCount <= 40
            && preg_match('/\p{Ll}/u', $text) === 1
            && !$this->lineHasPdfListBlockEvidence($text)
            && !$this->lineLooksLikeUrlOnly($text)
            && !$this->pdfLineStartsWithSemanticPrefix($text);
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $outer
     * @param array{x1:float,y1:float,x2:float,y2:float} $inner
     */
    private function sourcePdfBoundsContainBounds(
        array $outer,
        array $inner,
        float $tolerance
    ): bool {
        return $inner['x1'] >= $outer['x1'] - $tolerance
            && $inner['y1'] >= $outer['y1'] - $tolerance
            && $inner['x2'] <= $outer['x2'] + $tolerance
            && $inner['y2'] <= $outer['y2'] + $tolerance;
    }

    /**
     * @param array<string,mixed> $member
     * @return array<string,mixed>
     */
    private function sourcePdfNestedCautionCalloutProofMember(
        array $member,
        ?string $role = null
    ): array {
        $proofMember = [
            'sourceIndex' => $member['sourceIndex'],
            'sourceGlobalIndex' => $member['sourceGlobalIndex'],
            'sourceOccurrenceId' => $member['sourceOccurrenceId'],
            'projectionDigest' => $member['projectionDigest'],
            'geometryDigest' => $member['geometryDigest'],
            'bounds' => array_map(
                static fn (float $coordinate): float => round($coordinate, 4),
                $member['bounds']
            ),
        ];
        if ($role !== null) {
            $proofMember['role'] = $role;
        }

        return $proofMember;
    }

    /**
     * Replace every exact visual carrier participating in a proved nested
     * symbol callout with one canonical label-plus-prose carrier. Positioned
     * reconstruction can combine the icon atoms with one prose row, restore
     * duplicate atom fallbacks, and move the small label into a minor-font
     * flow. Rebuilding from the immutable source occurrences is safe only
     * when every participating occurrence has complete exact-range coverage
     * and no carrier mixes a participating range with unrelated source text.
     *
     * @param list<string> $lines
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     * @return array{lines:list<string>,layouts:list<array<string,mixed>>,geometryPageNumbers:list<int>}
     */
    private function pdfRepairSourceInProvenNestedCautionCalloutOrder(
        array $lines,
        array $layouts,
        array $sourceItems
    ): array {
        $unchanged = static fn (): array => [
            'lines' => $lines,
            'layouts' => $layouts,
            'geometryPageNumbers' => [],
        ];
        if ($lines === []
            || count($lines) !== count($layouts)
            || $sourceItems === []) {
            return $unchanged();
        }
        foreach ($layouts as $index => $layout) {
            if (!is_array($layout)
                || (string) ($layout['text'] ?? '') !== (string) ($lines[$index] ?? '')) {
                return $unchanged();
            }
        }

        $proofs = $this->sourcePdfStampedNestedCautionCalloutProofs($sourceItems);
        if ($proofs === []) {
            return $unchanged();
        }
        $workingLayouts = array_values($layouts);
        $geometryPageNumbers = [];
        foreach ($proofs as $proof) {
            if (!$this->sourcePdfSignedProofDigestMatches($proof)) {
                continue;
            }
            $members = array_merge(
                $proof['iconAtoms'],
                [$proof['label']],
                $proof['proseRows']
            );
            $participants = [];
            $fullRangeByGlobalIndex = [];
            foreach ($members as $member) {
                $localIndex = is_int($member['sourceIndex'] ?? null)
                    ? $member['sourceIndex']
                    : null;
                $globalIndex = is_int($member['sourceGlobalIndex'] ?? null)
                    ? $member['sourceGlobalIndex']
                    : null;
                $sourceItem = is_int($localIndex) ? ($sourceItems[$localIndex] ?? null) : null;
                $projection = is_array($sourceItem)
                    ? $this->pdfSourceOccurrenceComparableText(
                        (string) ($sourceItem['text'] ?? '')
                    )
                    : '';
                if (!is_array($sourceItem)
                    || !is_int($globalIndex)
                    || $projection === ''
                    || ($sourceItem['id'] ?? null) !== ($member['sourceOccurrenceId'] ?? null)
                    || !hash_equals(
                        (string) ($member['projectionDigest'] ?? ''),
                        hash('sha256', $projection)
                    )) {
                    continue 2;
                }
                $participants[$globalIndex] = true;
                $fullRangeByGlobalIndex[$globalIndex] = [
                    'sourceIndex' => $globalIndex,
                    'sourceStart' => 0,
                    'sourceEnd' => strlen($projection),
                ];
            }

            $matchedLayoutIndexes = [];
            $fullCoverage = [];
            $unsafeMixedCarrier = false;
            foreach ($workingLayouts as $layoutIndex => $layout) {
                $ranges = $this->pdfPositionedExactSourceRanges($layout);
                if ($ranges === []) {
                    continue;
                }
                $touchesProof = false;
                foreach ($ranges as $range) {
                    if (isset($participants[(int) $range['sourceIndex']])) {
                        $touchesProof = true;
                        break;
                    }
                }
                if (!$touchesProof) {
                    continue;
                }
                foreach ($ranges as $range) {
                    $globalIndex = (int) $range['sourceIndex'];
                    if (!isset($participants[$globalIndex])) {
                        $unsafeMixedCarrier = true;
                        break 2;
                    }
                    if ($range === $fullRangeByGlobalIndex[$globalIndex]) {
                        $fullCoverage[$globalIndex] = true;
                    }
                }
                $matchedLayoutIndexes[$layoutIndex] = true;
            }
            if ($unsafeMixedCarrier
                || $matchedLayoutIndexes === []
                || array_diff_key($participants, $fullCoverage) !== []) {
                continue;
            }

            $retainedMembers = array_merge([$proof['label']], $proof['proseRows']);
            $retainedTexts = [];
            $retainedRanges = [];
            $retainedBounds = [];
            $proseHeights = [];
            foreach ($retainedMembers as $offset => $member) {
                $localIndex = (int) $member['sourceIndex'];
                $globalIndex = (int) $member['sourceGlobalIndex'];
                $sourceItem = $sourceItems[$localIndex];
                $projection = $this->pdfSourceOccurrenceComparableText(
                    (string) ($sourceItem['text'] ?? '')
                );
                $retainedTexts[] = trim((string) $sourceItem['text']);
                $retainedRanges[] = [
                    'sourceIndex' => $globalIndex,
                    'sourceStart' => 0,
                    'sourceEnd' => strlen($projection),
                ];
                $bounds = is_array($member['bounds'] ?? null) ? $member['bounds'] : [];
                if (!isset($bounds['x1'], $bounds['y1'], $bounds['x2'], $bounds['y2'])) {
                    continue 2;
                }
                $retainedBounds[] = $bounds;
                if ($offset > 0) {
                    $proseHeights[] = (float) $bounds['y2'] - (float) $bounds['y1'];
                }
            }
            $text = implode(' ', $retainedTexts);
            $projection = $this->pdfSourceOccurrenceComparableText($text);
            if ($projection === ''
                || !hash_equals(
                    (string) ($proof['retainedProjectionDigest'] ?? ''),
                    hash('sha256', $projection)
                )) {
                continue;
            }

            $matchedLayouts = [];
            foreach (array_keys($matchedLayoutIndexes) as $layoutIndex) {
                $matchedLayouts[] = $workingLayouts[$layoutIndex];
            }
            $sourceOrderStarts = array_values(array_filter(
                array_column($matchedLayouts, 'sourceOrderStart'),
                'is_int'
            ));
            $sourceOrderEnds = array_values(array_filter(
                array_column($matchedLayouts, 'sourceOrderEnd'),
                'is_int'
            ));
            $layout = [
                'text' => $text,
                'page' => (int) $proof['page'],
                'sourceStream' => (int) $proof['sourceStream'],
                'positionedTextCandidate' => $text,
                'x1' => min(array_column($retainedBounds, 'x1')),
                'y1' => min(array_column($retainedBounds, 'y1')),
                'x2' => max(array_column($retainedBounds, 'x2')),
                'y2' => max(array_column($retainedBounds, 'y2')),
                'fontSize' => max(1.0, $this->median($proseHeights) * 0.8),
                'sourcePdfExactSourceRanges' => $retainedRanges,
                'sourcePdfExactPositionedText' => true,
                'sourceVerifiedGeometryText' => true,
                'sourcePdfPageExactInventoryPreserved' => true,
                'sourcePdfProtectedSemanticContent' => true,
                'sourcePdfLayoutLabel' => true,
                'sourcePdfNestedCautionCalloutProof' => $proof,
                'forceBlockBreakBefore' => true,
            ];
            if ($sourceOrderStarts !== []) {
                $layout['sourceOrderStart'] = min($sourceOrderStarts);
            }
            if ($sourceOrderEnds !== []) {
                $layout['sourceOrderEnd'] = max($sourceOrderEnds);
            }

            $insertAt = min(array_keys($matchedLayoutIndexes));
            $rebuiltLayouts = [];
            foreach ($workingLayouts as $layoutIndex => $existingLayout) {
                if ($layoutIndex === $insertAt) {
                    $rebuiltLayouts[] = $layout;
                }
                if (!isset($matchedLayoutIndexes[$layoutIndex])) {
                    $rebuiltLayouts[] = $existingLayout;
                }
            }
            $followingIndex = $insertAt + 1;
            if (isset($rebuiltLayouts[$followingIndex])) {
                $rebuiltLayouts[$followingIndex]['forceBlockBreakBefore'] = true;
            }
            $workingLayouts = array_values($rebuiltLayouts);
            $geometryPageNumbers[(int) $proof['page']] = true;
        }

        return [
            'lines' => array_values(array_map(
                static fn (array $layout): string => (string) ($layout['text'] ?? ''),
                $workingLayouts
            )),
            'layouts' => $workingLayouts,
            'geometryPageNumbers' => array_map('intval', array_keys($geometryPageNumbers)),
        ];
    }

    private function pdfSourceOccurrenceComparableText(string $text): string
    {
        return ($this->comparableTextCallback)($text);
    }

    /** @param array<string,mixed> $sourceItem */
    private function sourcePdfSourceItemHasExactGeometry(array $sourceItem): bool
    {
        return ($this->exactGeometryCallback)($sourceItem);
    }

    /** @param array<string,mixed> $geometry @return array<string,float> */
    private function pdfSourceEvidenceBounds(array $geometry): array
    {
        return ($this->evidenceBoundsCallback)($geometry);
    }

    /** @param array<string,mixed> $geometry */
    private function sourcePdfExactGeometryProofDigest(array $geometry): string
    {
        return ($this->geometryDigestCallback)($geometry);
    }

    private function normalizePdfTextEncoding(string $text): string
    {
        return ($this->normalizeTextCallback)($text);
    }

    /** @return list<string> */
    private function pdfLineWordTokens(string $line): array
    {
        return ($this->wordTokensCallback)($line);
    }

    private function length(string $text): int
    {
        return ($this->lengthCallback)($text);
    }

    private function lineHasPdfListBlockEvidence(string $line): bool
    {
        return ($this->listEvidenceCallback)($line);
    }

    private function lineLooksLikeUrlOnly(string $line): bool
    {
        return ($this->urlOnlyCallback)($line);
    }

    private function pdfLineStartsWithSemanticPrefix(string $line): bool
    {
        return ($this->semanticPrefixCallback)($line);
    }

    /** @param list<float|int> $values */
    private function median(array $values): float
    {
        return ($this->medianCallback)($values);
    }

    /** @param array<string,mixed> $proof */
    private function sourcePdfSignedProofDigestMatches(array $proof): bool
    {
        return ($this->signedProofCallback)($proof);
    }

    /** @param array<string,mixed> $item @return list<array<string,int>> */
    private function pdfPositionedExactSourceRanges(array $item): array
    {
        return ($this->exactRangesCallback)($item);
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function sourcePdfStampedNestedCautionCalloutProofs(array $items): array
    {
        return ($this->stampedProofsCallback)($items);
    }
}
