<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Preserve compact display-formula regions before prose merging.
 *
 * PDF source streams frequently split a formula into a base, a raised glyph,
 * and an operator tail. This processor requires local geometry plus operator
 * and operand evidence; it never recognizes a particular variable, number,
 * or equation. Positioned text may supply spacing only when it contains the
 * exact same non-whitespace characters as the source records it replaces.
 */
final class PdfFormulaSemanticProcessor implements PdfSemanticRecordProcessor
{
    public const LINE_PREFIX = "\x1EPDF-FORMULA\x1F";

    private int $regionCount = 0;

    public function name(): string
    {
        return 'formula-regions';
    }

    public function regionCount(): int
    {
        return $this->regionCount;
    }

    /** @param array<string,mixed>|null $item */
    public function positionedItemLooksLikeFormula(?array $item): bool
    {
        if (!is_array($item)
            || !$this->hasGeometry($item)
            || ($item['code'] ?? false) === true) {
            return false;
        }

        $text = trim((string) ($item['text'] ?? ''));

        return $this->textLength($this->compact($text)) <= 120
            && $this->looksLikeFormula($text);
    }

    /**
     * Prove that consecutive source atoms and one visual formula are the
     * same content. The source-order span counts painted glyph runs, not
     * source text records, so it may be wider than the fragment count when a
     * superscript or operator changes font or baseline.
     *
     * @param list<array{page:int,stream:int,text:string}> $sourceItems
     * @param list<int> $indexes
     * @param array<string,mixed>|null $positionedItem
     */
    public function sourceFragmentsMatchPositionedFormula(
        array $sourceItems,
        array $indexes,
        ?array $positionedItem
    ): bool {
        if (!$this->positionedItemLooksLikeFormula($positionedItem)
            || count($indexes) < 2
            || count($indexes) > 6
            || !isset($positionedItem['sourceOrderStart'], $positionedItem['sourceOrderEnd'])) {
            return false;
        }

        $firstIndex = $indexes[0] ?? null;
        if (!is_int($firstIndex)) {
            return false;
        }
        foreach ($indexes as $offset => $index) {
            if (!is_int($index) || $index !== $firstIndex + $offset) {
                return false;
            }
        }

        $sourceOrderSpan = (int) $positionedItem['sourceOrderEnd']
            - (int) $positionedItem['sourceOrderStart'] + 1;
        if ($sourceOrderSpan < count($indexes) || $sourceOrderSpan > 12) {
            return false;
        }

        $page = null;
        $stream = null;
        $sourceText = '';
        foreach ($indexes as $index) {
            $sourceItem = $sourceItems[$index] ?? null;
            if (!is_array($sourceItem)) {
                return false;
            }
            $page ??= (int) ($sourceItem['page'] ?? 0);
            $stream ??= (int) ($sourceItem['stream'] ?? 0);
            if ((int) ($sourceItem['page'] ?? 0) !== $page
                || (int) ($sourceItem['stream'] ?? 0) !== $stream) {
                return false;
            }
            $sourceText .= (string) ($sourceItem['text'] ?? '');
        }

        if ($page === null
            || $page < 1
            || $page !== (int) ($positionedItem['page'] ?? 0)
            || (isset($positionedItem['sourceStream'])
                && $stream !== (int) $positionedItem['sourceStream'])) {
            return false;
        }

        return $this->compact($sourceText)
            === $this->compact((string) ($positionedItem['text'] ?? ''));
    }

    public function process(array $records): array
    {
        $this->regionCount = 0;
        $processed = [];
        for ($index = 0, $count = count($records); $index < $count;) {
            $inlineFormula = $this->inlineFormulaProseAt($records, $index);
            if ($inlineFormula !== null) {
                $processed[] = [
                    'text' => $inlineFormula['text'],
                    'layout' => $inlineFormula['layout'],
                ];
                $index += $inlineFormula['consumed'];
                continue;
            }

            $formula = $this->formulaAt($records, $index);
            if ($formula === null) {
                $processed[] = $records[$index];
                $index++;
                continue;
            }

            $this->regionCount++;
            $layout = is_array($records[$index]['layout'] ?? null) ? $records[$index]['layout'] : [];
            $layout['sourcePdfRegionRole'] = 'formula';
            $layout['sourcePdfFormulaGroup'] = $this->regionCount;
            $layout['sourcePdfFormulaText'] = $formula['text'];
            unset($layout['sourcePdfWholeExactOccurrenceProof']);
            $processed[] = ['text' => $formula['text'], 'layout' => $layout];
            $index += $formula['consumed'];
        }

        return $processed;
    }

    /**
     * Reassemble source-order atoms which geometry proves occupy one inline
     * formula row inside prose. Unlike a display formula, the result keeps no
     * formula region role, so the ordinary prose merger can retain it inside
     * its surrounding sentence.
     *
     * Every carrier must expose a validated whole-occurrence or inline-marker
     * union proof. Their exact ranges must be consecutive, their compact text
     * must account for the complete range inventory, and their bboxes must
     * remain on one visual row. This deliberately excludes positioned rows
     * whose source ranges interleave scripts with a baseline carrier: joining
     * those records in array order would change the source character order.
     *
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{text:string,layout:array<string,mixed>,consumed:int}|null
     */
    private function inlineFormulaProseAt(array $records, int $start): ?array
    {
        if (!$this->hasNearbyInlineFormulaScript($records, $start)) {
            return null;
        }
        $first = $this->validatedExactInlineCarrier($records[$start] ?? null);
        if ($first === null) {
            return null;
        }

        $parts = [(string) $records[$start]['text']];
        $layouts = [$first['layout']];
        $ranges = $first['ranges'];
        $sourceIds = $first['sourceIds'];
        $lastSourceIndex = $first['sourceIndexes'][array_key_last($first['sourceIndexes'])];
        $maximum = min(count($records), $start + 24);
        for ($end = $start + 1; $end < $maximum; $end++) {
            $carrier = $this->validatedExactInlineCarrier($records[$end] ?? null);
            if ($carrier === null
                || $carrier['page'] !== $first['page']
                || $carrier['stream'] !== $first['stream']
                || $carrier['sourceIndexes'][0] !== $lastSourceIndex + 1
                || !$this->sameInlineFormulaVisualRow(
                    $layouts[0],
                    $layouts[array_key_last($layouts)],
                    $carrier['layout']
                )) {
                break;
            }
            $parts[] = (string) $records[$end]['text'];
            $layouts[] = $carrier['layout'];
            array_push($ranges, ...$carrier['ranges']);
            array_push($sourceIds, ...$carrier['sourceIds']);
            $lastSourceIndex = $carrier['sourceIndexes'][array_key_last($carrier['sourceIndexes'])];
        }

        if (count($parts) < 3 || !$this->looksLikeInlineFormulaProse($parts, $layouts)) {
            return null;
        }
        $text = $this->joinInlineFormulaProseParts($parts, $layouts);
        $sourceText = implode('', $parts);
        if ($this->compact($text) === ''
            || !hash_equals($this->compact($sourceText), $this->compact($text))) {
            return null;
        }

        return [
            'text' => $text,
            'layout' => $this->mergedInlineFormulaProseLayout(
                $text,
                $layouts,
                $ranges,
                array_values(array_unique($sourceIds)),
                $first['page'],
                $first['stream']
            ),
            'consumed' => count($parts),
        ];
    }

    /** @param list<array{text:string,layout:array<string,mixed>|null}> $records */
    private function hasNearbyInlineFormulaScript(array $records, int $start): bool
    {
        $firstLayout = is_array($records[$start]['layout'] ?? null)
            ? $records[$start]['layout']
            : null;
        if (!$this->hasGeometry($firstLayout)) {
            return false;
        }
        $mainFont = max(1.0, (float) $firstLayout['fontSize']);
        for ($index = $start + 1; $index <= $start + 2; $index++) {
            $layout = is_array($records[$index]['layout'] ?? null)
                ? $records[$index]['layout']
                : null;
            $text = is_string($records[$index]['text'] ?? null)
                ? $records[$index]['text']
                : '';
            if ($this->hasGeometry($layout)
                && (int) $layout['page'] === (int) $firstLayout['page']
                && $this->inlineFormulaPartIsScript($text, $layout, $firstLayout, $mainFont)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null}|null $record
     * @return array{
     *   layout:array<string,mixed>,
     *   ranges:list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}>,
     *   sourceIndexes:list<int>,
     *   sourceIds:list<string>,
     *   page:int,
     *   stream:int
     * }|null
     */
    private function validatedExactInlineCarrier(?array $record): ?array
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $text = is_string($record['text'] ?? null) ? $record['text'] : '';
        $projection = $this->compact($text);
        if ($projection === ''
            || !$this->hasGeometry($layout)
            || ($layout['code'] ?? false) === true
            || !is_int($layout['sourceStream'] ?? null)
            || $layout['sourceStream'] < 1) {
            return null;
        }

        $sourceRanges = $layout['sourcePdfExactSourceRanges'] ?? null;
        if (!is_array($sourceRanges) || !array_is_list($sourceRanges) || $sourceRanges === []) {
            return null;
        }
        $ranges = [];
        $sourceIndexes = [];
        $coveredBytes = 0;
        $previousSourceIndex = null;
        foreach ($sourceRanges as $range) {
            if (!is_array($range)
                || array_keys($range) !== ['sourceIndex', 'sourceStart', 'sourceEnd']
                || !is_int($range['sourceIndex'] ?? null)
                || !is_int($range['sourceStart'] ?? null)
                || !is_int($range['sourceEnd'] ?? null)
                || $range['sourceIndex'] < 0
                || $range['sourceStart'] !== 0
                || $range['sourceEnd'] <= 0
                || ($previousSourceIndex !== null
                    && $range['sourceIndex'] !== $previousSourceIndex + 1)) {
                return null;
            }
            $ranges[] = $range;
            $sourceIndexes[] = $range['sourceIndex'];
            $coveredBytes += $range['sourceEnd'];
            $previousSourceIndex = $range['sourceIndex'];
        }
        if ($coveredBytes !== strlen($projection)) {
            return null;
        }

        $sourceIds = [];
        if (count($ranges) === 1) {
            $proof = is_array($layout['sourcePdfWholeExactSourceOccurrenceProof'] ?? null)
                ? $layout['sourcePdfWholeExactSourceOccurrenceProof']
                : null;
            if (!is_array($proof)
                || ($proof['version'] ?? null) !== 1
                || ($proof['method'] ?? null) !== 'source-inventory-whole-source-occurrence'
                || ($proof['page'] ?? null) !== (int) $layout['page']
                || ($proof['stream'] ?? null) !== $layout['sourceStream']
                || ($proof['globalSourceIndex'] ?? null) !== $sourceIndexes[0]
                || ($proof['exactRange'] ?? null) !== $ranges[0]
                || !is_string($proof['sourceOccurrenceId'] ?? null)
                || $proof['sourceOccurrenceId'] === ''
                || !is_string($proof['projectionDigest'] ?? null)
                || !hash_equals($proof['projectionDigest'], hash('sha256', $projection))
                || !$this->exactProofDigestMatches($proof)) {
                return null;
            }
            $sourceIds[] = $proof['sourceOccurrenceId'];
        } else {
            $proof = is_array($layout['sourcePdfInlineMarkerExactSourceUnionProof'] ?? null)
                ? $layout['sourcePdfInlineMarkerExactSourceUnionProof']
                : null;
            $layoutSourceIndexes = is_array($layout['sourcePdfGlobalSourceIndexes'] ?? null)
                ? array_values($layout['sourcePdfGlobalSourceIndexes'])
                : [];
            $layoutSourceIds = is_array($layout['sourcePdfSourceIds'] ?? null)
                ? array_values($layout['sourcePdfSourceIds'])
                : [];
            if (!is_array($proof)
                || ($proof['version'] ?? null) !== 1
                || ($proof['method'] ?? null) !== 'exact-source-inline-marker-union'
                || ($proof['page'] ?? null) !== (int) $layout['page']
                || ($proof['layoutStream'] ?? null) !== $layout['sourceStream']
                || ($proof['ranges'] ?? null) !== $ranges
                || $layoutSourceIndexes !== $sourceIndexes
                || count($layoutSourceIds) !== count($ranges)
                || count($layoutSourceIds) !== count(array_unique($layoutSourceIds))
                || !is_string($proof['projectionDigest'] ?? null)
                || !hash_equals($proof['projectionDigest'], hash('sha256', $projection))
                || !$this->exactProofDigestMatches($proof)) {
                return null;
            }
            $sourceIds = $layoutSourceIds;
        }

        return [
            'layout' => $layout,
            'ranges' => $ranges,
            'sourceIndexes' => $sourceIndexes,
            'sourceIds' => $sourceIds,
            'page' => (int) $layout['page'],
            'stream' => $layout['sourceStream'],
        ];
    }

    /** @param array<string,mixed> $proof */
    private function exactProofDigestMatches(array $proof): bool
    {
        $digest = $proof['proofDigest'] ?? null;
        if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
            return false;
        }
        $payload = $proof;
        unset($payload['proofDigest']);

        return hash_equals($digest, hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: ''));
    }

    /** @param array<string,mixed> $first @param array<string,mixed> $previous @param array<string,mixed> $candidate */
    private function sameInlineFormulaVisualRow(
        array $first,
        array $previous,
        array $candidate
    ): bool {
        $largestFont = max(
            1.0,
            (float) $first['fontSize'],
            (float) $previous['fontSize'],
            (float) $candidate['fontSize']
        );
        $firstCenter = ((float) $first['y1'] + (float) $first['y2']) / 2.0;
        $candidateCenter = ((float) $candidate['y1'] + (float) $candidate['y2']) / 2.0;
        if (abs($candidateCenter - $firstCenter) > max(8.0, $largestFont * 0.95)) {
            return false;
        }

        return (float) $candidate['x1']
            >= (float) $previous['x1'] - max(8.0, $largestFont * 0.8);
    }

    /** @param list<string> $parts @param list<array<string,mixed>> $layouts */
    private function looksLikeInlineFormulaProse(array $parts, array $layouts): bool
    {
        $text = implode('', $parts);
        $wordCount = preg_match_all('/\p{Ll}{4,}/u', $text, $unused);
        if ($wordCount === false || $wordCount < 2) {
            return false;
        }
        $mainFont = max(array_map(
            static fn (array $layout): float => max(1.0, (float) $layout['fontSize']),
            $layouts
        ));
        $scriptIndexes = [];
        for ($index = 1, $count = count($parts) - 1; $index < $count; $index++) {
            if ($this->inlineFormulaPartIsScript(
                $parts[$index],
                $layouts[$index],
                $layouts[0],
                $mainFont
            )) {
                $scriptIndexes[] = $index;
            }
        }
        if ($scriptIndexes === []) {
            return false;
        }

        $notation = preg_match(
            '/(?:\p{L}\([^)]{1,4}\)|[|=+\x{2026}]|\x{00B7}{3}|\.{3})/u',
            $text
        ) === 1;
        $subscriptedSymbolBeforeProse = false;
        foreach ($scriptIndexes as $scriptIndex) {
            $previous = rtrim($parts[$scriptIndex - 1]);
            $following = ltrim($parts[$scriptIndex + 1]);
            if (preg_match('/\b\p{L}(?:\([^)]{1,4}\))?$/u', $previous) === 1
                && preg_match('/^\p{Ll}{3,}(?:\b|\s)/u', $following) === 1) {
                $subscriptedSymbolBeforeProse = true;
                break;
            }
        }

        return $notation || $subscriptedSymbolBeforeProse;
    }

    /** @param array<string,mixed> $layout @param array<string,mixed> $baselineLayout */
    private function inlineFormulaPartIsScript(
        string $text,
        array $layout,
        array $baselineLayout,
        float $mainFont
    ): bool {
        $compact = $this->compact($text);
        if ($compact === '' || $this->textLength($compact) > 4) {
            return false;
        }
        $center = ((float) $layout['y1'] + (float) $layout['y2']) / 2.0;
        $baselineCenter = ((float) $baselineLayout['y1'] + (float) $baselineLayout['y2']) / 2.0;

        return (float) $layout['fontSize'] <= $mainFont * 0.86
            && abs($center - $baselineCenter) >= max(0.8, $mainFont * 0.08);
    }

    /** @param list<string> $parts @param list<array<string,mixed>> $layouts */
    private function joinInlineFormulaProseParts(array $parts, array $layouts): string
    {
        $text = trim($parts[0]);
        $mainFont = max(array_map(
            static fn (array $layout): float => max(1.0, (float) $layout['fontSize']),
            $layouts
        ));
        for ($index = 1, $count = count($parts); $index < $count; $index++) {
            $left = rtrim($parts[$index - 1]);
            $right = ltrim($parts[$index]);
            $separator = '';
            $leftIsScript = $this->inlineFormulaPartIsScript(
                $parts[$index - 1],
                $layouts[$index - 1],
                $layouts[0],
                $mainFont
            );
            if ($leftIsScript
                && preg_match('/^\p{Ll}{3,}(?:\b|\s)/u', $right) === 1) {
                $separator = ' ';
            } else {
                $gap = (float) $layouts[$index]['x1'] - (float) $layouts[$index - 1]['x2'];
                $smallerFont = min(
                    (float) $layouts[$index - 1]['fontSize'],
                    (float) $layouts[$index]['fontSize']
                );
                if ($gap > max(2.25, $smallerFont * 0.22)
                    && preg_match('/[\p{L}\p{N}]$/u', $left) === 1
                    && preg_match('/^[\p{L}\p{N}]/u', $right) === 1) {
                    $separator = ' ';
                }
            }
            $text .= $separator . $right;
        }

        return trim($text);
    }

    /**
     * @param list<array<string,mixed>> $layouts
     * @param list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}> $ranges
     * @param list<string> $sourceIds
     * @return array<string,mixed>
     */
    private function mergedInlineFormulaProseLayout(
        string $text,
        array $layouts,
        array $ranges,
        array $sourceIds,
        int $page,
        int $stream
    ): array {
        $layout = $layouts[0];
        foreach ($layouts as $candidate) {
            $layout['x1'] = min((float) $layout['x1'], (float) $candidate['x1']);
            $layout['y1'] = min((float) $layout['y1'], (float) $candidate['y1']);
            $layout['x2'] = max((float) $layout['x2'], (float) $candidate['x2']);
            $layout['y2'] = max((float) $layout['y2'], (float) $candidate['y2']);
            $layout['fontSize'] = max((float) $layout['fontSize'], (float) $candidate['fontSize']);
            foreach ([['textX1', 'min'], ['textY1', 'min'], ['textX2', 'max'], ['textY2', 'max']] as [$key, $operation]) {
                if (is_numeric($layout[$key] ?? null) && is_numeric($candidate[$key] ?? null)) {
                    $layout[$key] = $operation === 'min'
                        ? min((float) $layout[$key], (float) $candidate[$key])
                        : max((float) $layout[$key], (float) $candidate[$key]);
                }
            }
        }
        foreach ([
            'id',
            'positionedTextCandidate',
            'sourceGeometry',
            'sourceGeometryMethod',
            'sourcePdfExactPositionedText',
            'sourcePdfExactGeometryFallback',
            'sourceUnmatchedFallback',
            'sourcePdfGlobalSourceIndex',
            'sourcePdfSourceIndex',
            'sourcePdfSourceIndexEnd',
            'sourcePdfSourceIndexes',
            'sourcePdfWholeExactSourceOccurrenceProof',
            'sourcePdfInlineMarkerCount',
            'sourcePdfInlineMarkerExactSourceUnionProof',
            'sourceVerifiedBoundarySeparators',
        ] as $staleKey) {
            unset($layout[$staleKey]);
        }

        $sourceIndexes = array_column($ranges, 'sourceIndex');
        $layout['text'] = $text;
        $layout['page'] = $page;
        $layout['sourceStream'] = $stream;
        $layout['sourceOrderStart'] = $sourceIndexes[0];
        $layout['sourceOrderEnd'] = $sourceIndexes[array_key_last($sourceIndexes)];
        $layout['sourcePdfGlobalSourceIndexes'] = $sourceIndexes;
        $layout['sourcePdfExactSourceRanges'] = $ranges;
        $layout['sourcePdfStreams'] = [$stream];
        if ($sourceIds !== []) {
            $layout['sourcePdfSourceIds'] = $sourceIds;
        }
        $layout['sourcePdfProtectedSemanticContent'] = true;
        $layout['sourcePdfInlineFormulaProse'] = true;
        $proof = [
            'version' => 1,
            'method' => 'exact-source-inline-formula-prose-union',
            'page' => $page,
            'stream' => $stream,
            'sourceIndexes' => $sourceIndexes,
            'ranges' => $ranges,
            'projectionDigest' => hash('sha256', $this->compact($text)),
            'geometry' => [
                'x1' => $layout['x1'],
                'y1' => $layout['y1'],
                'x2' => $layout['x2'],
                'y2' => $layout['y2'],
            ],
        ];
        $proof['proofDigest'] = hash('sha256', json_encode(
            $proof,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');
        $layout['sourcePdfInlineFormulaExactSourceUnionProof'] = $proof;

        return $layout;
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{text:string,consumed:int}|null
     */
    private function formulaAt(array $records, int $start): ?array
    {
        $firstLayout = is_array($records[$start]['layout'] ?? null) ? $records[$start]['layout'] : null;
        if (!$this->hasGeometry($firstLayout) || ($firstLayout['code'] ?? false) === true) {
            return null;
        }
        $page = (int) $firstLayout['page'];
        $sourceParts = [];
        $layouts = [];
        $maximum = min(count($records), $start + 6);
        for ($end = $start; $end < $maximum; $end++) {
            $record = $records[$end];
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $text = trim((string) ($record['text'] ?? ''));
            if ($text === ''
                || !$this->hasGeometry($layout)
                || (int) $layout['page'] !== $page
                || ($layout['code'] ?? false) === true
                || $this->textLength($text) > 64) {
                break;
            }
            $sourceParts[] = $text;
            $layouts[] = $layout;
            $consumed = $end - $start + 1;
            $sourceText = implode(' ', $sourceParts);
            $sourceCompact = $this->compact($sourceText);
            if ($sourceCompact === '' || $this->textLength($sourceCompact) > 120) {
                break;
            }

            $candidates = [$sourceText];
            foreach ($layouts as $candidateLayout) {
                $candidate = trim((string) ($candidateLayout['positionedTextCandidate'] ?? ''));
                if ($candidate !== '' && $this->compact($candidate) === $sourceCompact) {
                    array_unshift($candidates, $candidate);
                }
            }
            foreach (array_values(array_unique($candidates)) as $candidate) {
                if ($this->looksLikeFormula($candidate) && $this->geometryIsCoherent($layouts)) {
                    return ['text' => $this->normalizeFormulaSpacing($candidate), 'consumed' => $consumed];
                }
            }
        }

        return null;
    }

    private function looksLikeFormula(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || str_contains($text, '://') || str_contains($text, '@')) {
            return false;
        }
        // Multiple statement separators on one compact visual line are
        // strong programming/control-flow evidence (for example a loop
        // header), not display mathematics. One semicolon remains available
        // for ordinary mathematical notation.
        $semicolonCount = substr_count($text, ';');
        if ($semicolonCount >= 2) {
            return false;
        }
        $operatorCount = preg_match_all('/[=≠≤≥<>+−×÷±∑∫√∂∞^]/u', $text, $operators);
        $operandCount = preg_match_all('/[\p{L}\p{N}]+/u', $text, $operands);
        if ($operatorCount === false || $operandCount === false || $operatorCount < 1 || $operandCount < 2) {
            return false;
        }
        // Equality/inequality is sufficient by itself. Other notation needs
        // two operators so prose such as “A + B” is not promoted eagerly.
        if (preg_match('/[=≠≤≥<>]/u', $text) !== 1 && $operatorCount < 2) {
            return false;
        }
        foreach ($operands[0] as $operand) {
            if (preg_match('/^\p{Ll}{5,}$/u', $operand) === 1) {
                return false;
            }
        }
        $forbidden = preg_match_all('/[^\p{L}\p{M}\p{N}\s=≠≤≥<>+−–—×÷±∑∫√∂∞^()\[\]{}.,:;_\-\/]/u', $text, $invalid);

        return $forbidden !== false && $forbidden <= 1;
    }

    /** @param list<array<string,mixed>> $layouts */
    private function geometryIsCoherent(array $layouts): bool
    {
        if ($layouts === []) {
            return false;
        }
        $page = (int) $layouts[0]['page'];
        $minimumX = INF;
        $maximumX = -INF;
        $minimumY = INF;
        $maximumY = -INF;
        $largestFont = 1.0;
        foreach ($layouts as $layout) {
            if ((int) $layout['page'] !== $page) {
                return false;
            }
            $minimumX = min($minimumX, (float) $layout['x1']);
            $maximumX = max($maximumX, (float) $layout['x2']);
            $minimumY = min($minimumY, (float) $layout['y1']);
            $maximumY = max($maximumY, (float) $layout['y2']);
            $largestFont = max($largestFont, (float) $layout['fontSize']);
        }

        return $maximumX - $minimumX <= max(180.0, $largestFont * 24.0)
            && $maximumY - $minimumY <= max(36.0, $largestFont * 3.5);
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

        return (int) $layout['page'] > 0;
    }

    private function compact(string $text): string
    {
        return preg_replace('/\s+/u', '', $text) ?? '';
    }

    private function normalizeFormulaSpacing(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        $text = preg_replace('/\s*([=≠≤≥<>+−×÷±])\s*/u', ' $1 ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
