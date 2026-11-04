<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Rebuild and coalesce only source-proved form-row currency tails.
 *
 * Reader-specific primitives are supplied lazily under their existing method
 * names so this specialized bytecode need not be loaded for PDFs which cannot
 * contain an exact form-row tail.
 */
final class PdfExactFormRowTailRepair
{
    /** @param array<string,callable> $callbacks */
    private function __construct(private array $callbacks)
    {
    }

    /**
     * @param list<array<string,mixed>> $sourceLayouts
     * @param list<array<string,mixed>> $sourceItems
     * @param array<string,callable> $callbacks
     * @return list<array<string,mixed>>
     */
    public static function decorateLayouts(
        array $sourceLayouts,
        array $sourceItems,
        array $callbacks
    ): array {
        return (new self($callbacks))->pdfSourceLayoutsWithExactFormTailOccurrenceProofs(
            $sourceLayouts,
            $sourceItems
        );
    }

    /**
     * @param list<string> $lines
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     * @param array<string,callable> $callbacks
     * @return array{lines:list<string>,layouts:list<array<string,mixed>>,geometryPageNumbers:list<int>}
     */
    public static function reorder(
        array $lines,
        array $layouts,
        array $sourceItems,
        array $callbacks
    ): array {
        return (new self($callbacks))->pdfRepairSourceInProvenFormRowBundleOrder(
            $lines,
            $layouts,
            $sourceItems
        );
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @param array<string,callable> $callbacks
     * @return list<array{text:string,layout:array<string,mixed>|null}>
     */
    public static function coalesce(array $records, array $callbacks): array
    {
        return (new self($callbacks))->coalesceProvenPdfFormTailRecords($records);
    }

    /**
     * Join only repeated form-field tails whose source occurrences are exact
     * and consecutive.  This is deliberately directional: a currency glyph
     * attaches to the field identifier immediately before it, and a detached
     * identifier attaches backward only when a dotted form row and that
     * currency glyph form one consecutive triad.  Nothing is ever joined
     * forward from a currency field into the next instruction or notice.
     *
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return list<array{text:string,layout:array<string,mixed>|null}>
     */
    private function coalesceProvenPdfFormTailRecords(array $records): array
    {
        $formTailsByPage = [];
        $compositeProofsByRecord = [];
        foreach ($records as $index => $record) {
            $proof = $this->pdfRecordProvenCompositeCurrencyTailProof($record);
            if ($proof === null) {
                continue;
            }
            $layout = $record['layout'];
            $page = max(1, (int) ($layout['page'] ?? 1));
            $formTailsByPage[$page] = ($formTailsByPage[$page] ?? 0) + 1;
            $compositeProofsByRecord[$index] = $proof;
        }
        for ($index = 1, $count = count($records); $index < $count; $index++) {
            if (!$this->pdfRecordsFormProvenCurrencyTail($records[$index - 1], $records[$index])) {
                continue;
            }
            $layout = $records[$index]['layout'];
            $page = max(1, (int) ($layout['page'] ?? 1));
            $formTailsByPage[$page] = ($formTailsByPage[$page] ?? 0) + 1;
        }
        $formPages = array_fill_keys(array_keys(array_filter(
            $formTailsByPage,
            static fn (int $count): bool => $count >= 3
        )), true);
        if ($formPages === []) {
            return $records;
        }

        $merged = [];
        foreach ($records as $index => $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $page = is_array($layout) ? max(1, (int) ($layout['page'] ?? 1)) : 0;
            if (isset($formPages[$page], $compositeProofsByRecord[$index])) {
                $layout['sourcePdfFormTailComposite'] = true;
                $layout['sourcePdfFormTailEnd'] = true;
                $layout['sourcePdfFormTailProof'] = $compositeProofsByRecord[$index];
                $record['layout'] = $layout;
            }
            $lastIndex = array_key_last($merged);
            if (!isset($formPages[$page]) || $lastIndex === null) {
                $merged[] = $record;
                continue;
            }

            $last = $merged[$lastIndex];
            $next = $records[$index + 1] ?? null;
            $detachedRowId = is_array($next)
                && $this->pdfRecordLooksLikeCompactFormFieldId($record)
                && (($layout['forceBlockBreakBefore'] ?? false) !== true)
                && $this->pdfRecordEndsWithDottedFormLeader($last)
                && $this->pdfRecordsHaveConsecutiveSourceOccurrences($last, $record)
                && $this->pdfRecordsFormProvenCurrencyTail($record, $next);
            if ($detachedRowId) {
                $merged[$lastIndex] = $this->mergeProvenPdfFormTailRecords($last, $record);
                continue;
            }

            if ($this->pdfRecordsFormProvenCurrencyTail($last, $record)) {
                $merged[$lastIndex] = $this->mergeProvenPdfFormTailRecords($last, $record);
                continue;
            }

            $merged[] = $record;
        }

        $rowAttached = [];
        foreach ($merged as $record) {
            $lastIndex = array_key_last($rowAttached);
            if ($lastIndex !== null
                && $this->pdfRecordsShareExactFormRowBundleForBackwardTailAttachment(
                    $rowAttached[$lastIndex],
                    $record
                )) {
                $rowAttached[$lastIndex] = $this->mergeProvenPdfFormTailRecords(
                    $rowAttached[$lastIndex],
                    $record
                );
                continue;
            }
            $rowAttached[] = $record;
        }

        return $rowAttached;
    }

    /**
     * A rebuilt form page assigns every consecutive source occurrence to the
     * currency tail which closes its row bundle. Attach only the immediate
     * final prose occurrence backward; ordinary prose repair can then consume
     * earlier wrapped lines from the left, while list delimiters and the next
     * row remain hard boundaries.
     *
     * @param array{text:string,layout:array<string,mixed>|null} $left
     * @param array{text:string,layout:array<string,mixed>|null} $tail
     */
    private function pdfRecordsShareExactFormRowBundleForBackwardTailAttachment(
        array $left,
        array $tail
    ): bool {
        $leftLayout = is_array($left['layout'] ?? null) ? $left['layout'] : null;
        $tailLayout = is_array($tail['layout'] ?? null) ? $tail['layout'] : null;
        if (!is_array($leftLayout)
            || !is_array($tailLayout)
            || ($leftLayout['sourcePdfFormTailEnd'] ?? false) === true
            || ($tailLayout['sourcePdfFormTailEnd'] ?? false) !== true
            || $this->pdfLineStartsWithSemanticPrefix((string) ($left['text'] ?? ''))
            || $this->lineHasPdfListBlockEvidence((string) ($left['text'] ?? ''))
            || $this->lineIsOnlyPdfNoise((string) ($left['text'] ?? ''))
            || $this->pdfPositionedExactSourceRanges($leftLayout) === []
            || $this->pdfPositionedExactSourceRanges($tailLayout) === []) {
            return false;
        }

        $leftProof = $this->pdfValidatedFormRowBundleProof($left);
        $tailProof = $this->pdfValidatedFormRowBundleProof($tail);
        if ($leftProof === null
            || $tailProof === null
            || !hash_equals($leftProof['proofDigest'], $tailProof['proofDigest'])) {
            return false;
        }
        $leftIndexes = $this->pdfSemanticRecordGlobalSourceIndexes($left);
        $tailIndexes = $this->pdfSemanticRecordGlobalSourceIndexes($tail);

        return $leftIndexes !== []
            && $tailIndexes !== []
            && $tailIndexes[0] === $leftIndexes[array_key_last($leftIndexes)] + 1
            && $tailIndexes[array_key_last($tailIndexes)] === $tailProof['sourceEndIndex'];
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $record
     * @return array<string,mixed>|null
     */
    private function pdfValidatedFormRowBundleProof(array $record): ?array
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $proof = is_array($layout['sourcePdfFormRowBundleProof'] ?? null)
            ? $layout['sourcePdfFormRowBundleProof']
            : null;
        if (!is_array($layout)
            || $proof === null
            || ($proof['version'] ?? null) !== 1
            || ($proof['method'] ?? null) !== 'exact-source-occurrence-form-row-bundle'
            || !is_int($proof['page'] ?? null)
            || !is_int($proof['sourceStartIndex'] ?? null)
            || !is_int($proof['sourceEndIndex'] ?? null)
            || $proof['sourceStartIndex'] < 0
            || $proof['sourceEndIndex'] < $proof['sourceStartIndex']
            || !is_float($proof['tailCenter'] ?? null)
            || !is_float($proof['tailX2'] ?? null)
            || !is_string($proof['proofDigest'] ?? null)
            || max(1, (int) ($layout['page'] ?? 1)) !== $proof['page']) {
            return null;
        }
        $payload = $proof;
        unset($payload['proofDigest']);
        $digest = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        ) ?: '');
        if (!hash_equals($proof['proofDigest'], $digest)) {
            return null;
        }
        $sourceIndexes = $this->pdfSemanticRecordGlobalSourceIndexes($record);
        $ranges = $this->pdfPositionedExactSourceRanges($layout);
        $rangeIndexes = [];
        foreach ($ranges as $range) {
            $lastIndex = array_key_last($rangeIndexes);
            if ($lastIndex !== null && $rangeIndexes[$lastIndex] === $range['sourceIndex']) {
                continue;
            }
            if ($lastIndex !== null
                && $range['sourceIndex'] !== $rangeIndexes[$lastIndex] + 1) {
                return null;
            }
            $rangeIndexes[] = $range['sourceIndex'];
        }
        if ($sourceIndexes === [] || $rangeIndexes !== $sourceIndexes) {
            return null;
        }
        foreach ($sourceIndexes as $sourceIndex) {
            if ($sourceIndex < $proof['sourceStartIndex']
                || $sourceIndex > $proof['sourceEndIndex']) {
                return null;
            }
        }

        return $proof;
    }

    /**
     * Restore the canonical source-occurrence prefix on a page whose repeated
     * exact currency tails independently prove a vertically ordered form.
     * Positioned line construction may interleave the next row's left label
     * ahead of the prior row's right field, and it may combine two list items
     * painted on one baseline. Rebuilding only a completely covered source
     * prefix keeps each row bundle in logical source order while tail geometry
     * proves that those bundles already descend in visual order.
     *
     * The replacement happens before semantic repair and before source-order
     * proof layouts are captured. A later record-only permutation would leave
     * that proof sidecar stale and must remain fail-closed.
     *
     * @param list<string> $lines
     * @param list<array<string,mixed>> $layouts
     * @param list<array<string,mixed>> $sourceItems
     * @return array{lines:list<string>,layouts:list<array<string,mixed>>,geometryPageNumbers:list<int>}
     */
    private function pdfRepairSourceInProvenFormRowBundleOrder(
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

        $records = [];
        $tailAnchorsByPage = [];
        foreach ($lines as $index => $line) {
            $record = [
                'text' => $line,
                'layout' => is_array($layouts[$index] ?? null) ? $layouts[$index] : null,
            ];
            $records[] = $record;
            $proof = $this->pdfRecordProvenCompositeCurrencyTailProof($record);
            if ($proof === null) {
                continue;
            }
            $layout = $record['layout'];
            if (!$this->pdfLayoutHasGeometry($layout)
                || !is_int($proof['currencySourceIndex'] ?? null)) {
                continue;
            }
            $page = max(1, (int) ($layout['page'] ?? 1));
            $tailAnchorsByPage[$page][] = [
                'sourceIndex' => $proof['currencySourceIndex'],
                'center' => ((float) $layout['y1'] + (float) $layout['y2']) / 2.0,
                'x2' => (float) $layout['x2'],
                'fontSize' => max(1.0, (float) $layout['fontSize']),
            ];
        }

        $sourceItemsByGlobalIndex = [];
        $sourceIndexesByPage = [];
        foreach ($sourceItems as $localIndex => $sourceItem) {
            $globalIndex = is_int($sourceItem['sourcePdfGlobalSourceIndex'] ?? null)
                ? $sourceItem['sourcePdfGlobalSourceIndex']
                : $localIndex;
            if (isset($sourceItemsByGlobalIndex[$globalIndex])) {
                return $unchanged();
            }
            $sourceItemsByGlobalIndex[$globalIndex] = $sourceItem;
            $sourceIndexesByPage[max(1, (int) ($sourceItem['page'] ?? 1))][] = $globalIndex;
        }

        $replacementRecordsByPage = [];
        foreach ($tailAnchorsByPage as $page => $tailAnchors) {
            if (count($tailAnchors) < 3 || !isset($sourceIndexesByPage[$page])) {
                continue;
            }
            usort($tailAnchors, static fn (array $left, array $right): int =>
                $left['sourceIndex'] <=> $right['sourceIndex']
            );
            $laneX2 = $this->median(array_column($tailAnchors, 'x2'));
            $laneFontSize = max(1.0, $this->median(array_column($tailAnchors, 'fontSize')));
            $previousSourceIndex = null;
            $previousCenter = null;
            $anchorsAreOrdered = true;
            foreach ($tailAnchors as $anchor) {
                if (($previousSourceIndex !== null
                        && $anchor['sourceIndex'] <= $previousSourceIndex)
                    || ($previousCenter !== null
                        && $anchor['center'] >= $previousCenter - max(1.0, $laneFontSize * 0.10))
                    || abs($anchor['x2'] - $laneX2) > max(12.0, $laneFontSize * 2.0)) {
                    $anchorsAreOrdered = false;
                    break;
                }
                $previousSourceIndex = $anchor['sourceIndex'];
                $previousCenter = $anchor['center'];
            }
            if (!$anchorsAreOrdered) {
                continue;
            }

            $pageSourceIndexes = $sourceIndexesByPage[$page];
            sort($pageSourceIndexes, SORT_NUMERIC);
            $sourceStart = $pageSourceIndexes[0];
            $sourceEnd = $tailAnchors[array_key_last($tailAnchors)]['sourceIndex'];
            if ($sourceEnd < $sourceStart) {
                continue;
            }
            $expectedSourceIndexes = range($sourceStart, $sourceEnd);
            if (array_slice($pageSourceIndexes, 0, count($expectedSourceIndexes))
                !== $expectedSourceIndexes) {
                continue;
            }

            $formRowBundleProofsBySourceIndex = [];
            $bundleStart = $sourceStart;
            $bundleProofsAreValid = true;
            foreach ($tailAnchors as $anchor) {
                $bundleEnd = $anchor['sourceIndex'];
                if ($bundleEnd < $bundleStart) {
                    $bundleProofsAreValid = false;
                    break;
                }
                $bundleProof = [
                    'version' => 1,
                    'method' => 'exact-source-occurrence-form-row-bundle',
                    'page' => $page,
                    'sourceStartIndex' => $bundleStart,
                    'sourceEndIndex' => $bundleEnd,
                    'tailCenter' => $anchor['center'],
                    'tailX2' => $anchor['x2'],
                ];
                $bundleProof['proofDigest'] = hash('sha256', json_encode(
                    $bundleProof,
                    JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_PRESERVE_ZERO_FRACTION
                ) ?: '');
                for ($sourceIndex = $bundleStart; $sourceIndex <= $bundleEnd; $sourceIndex++) {
                    $formRowBundleProofsBySourceIndex[$sourceIndex] = $bundleProof;
                }
                $bundleStart = $bundleEnd + 1;
            }
            if (!$bundleProofsAreValid || $bundleStart !== $sourceEnd + 1) {
                continue;
            }

            $coverageBySourceIndex = [];
            $pageRecords = [];
            $pageCanBeRebuilt = true;
            foreach ($records as $record) {
                $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
                if (!is_array($layout) || max(1, (int) ($layout['page'] ?? 1)) !== $page) {
                    continue;
                }
                $pageRecords[] = $record;
                $ranges = $this->pdfPositionedExactSourceRanges($layout);
                if ($ranges === []) {
                    if ($this->pdfSourceOccurrenceComparableText((string) ($record['text'] ?? '')) !== '') {
                        $pageCanBeRebuilt = false;
                        break;
                    }
                    continue;
                }
                $inside = false;
                $outside = false;
                foreach ($ranges as $range) {
                    if ($range['sourceIndex'] >= $sourceStart
                        && $range['sourceIndex'] <= $sourceEnd) {
                        $inside = true;
                        $coverageBySourceIndex[$range['sourceIndex']][] = [
                            'start' => $range['sourceStart'],
                            'end' => $range['sourceEnd'],
                        ];
                    } else {
                        $outside = true;
                    }
                }
                if ($inside && $outside) {
                    $pageCanBeRebuilt = false;
                    break;
                }
            }
            if (!$pageCanBeRebuilt || $pageRecords === []) {
                continue;
            }

            $rebuiltRecords = [];
            foreach ($expectedSourceIndexes as $sourceIndex) {
                $sourceItem = $sourceItemsByGlobalIndex[$sourceIndex] ?? null;
                $sourceProjection = is_array($sourceItem)
                    ? $this->pdfSourceOccurrenceComparableText((string) ($sourceItem['text'] ?? ''))
                    : '';
                $sourceGeometry = is_array($sourceItem['sourceGeometry'] ?? null)
                    ? $sourceItem['sourceGeometry']
                    : null;
                $coverage = $coverageBySourceIndex[$sourceIndex] ?? [];
                usort($coverage, static fn (array $left, array $right): int =>
                    ($left['start'] <=> $right['start']) ?: ($left['end'] <=> $right['end'])
                );
                $cursor = 0;
                foreach ($coverage as $covered) {
                    if ($covered['start'] !== $cursor || $covered['end'] <= $covered['start']) {
                        $cursor = -1;
                        break;
                    }
                    $cursor = $covered['end'];
                }
                if (!is_array($sourceItem)
                    || $sourceProjection === ''
                    || ($sourceItem['sourceGeometryMethod'] ?? null)
                        !== 'exact-page-stream-character-offset'
                    || !$this->pdfSourceBoundsAreValid($sourceGeometry)
                    || $cursor !== strlen($sourceProjection)) {
                    $pageCanBeRebuilt = false;
                    break;
                }

                $sourceItem['sourcePdfGlobalSourceIndex'] = $sourceIndex;
                $layout = $this->sourcePdfLineItem($sourceItem);
                $bounds = $this->pdfSourceEvidenceBounds($sourceGeometry);
                $layout = array_replace($layout, [
                    'x1' => $bounds['x1'],
                    'y1' => $bounds['y1'],
                    'x2' => $bounds['x2'],
                    'y2' => $bounds['y2'],
                    'fontSize' => max(1.0, ($bounds['y2'] - $bounds['y1']) * 0.80),
                    'sourceGeometry' => $sourceGeometry,
                    'sourceGeometryMethod' => 'exact-page-stream-character-offset',
                    'sourcePdfExactPositionedText' => true,
                    'sourcePdfExactSourceRanges' => [[
                        'sourceIndex' => $sourceIndex,
                        'sourceStart' => 0,
                        'sourceEnd' => strlen($sourceProjection),
                    ]],
                    'sourcePdfFormRowBundleProof' => $formRowBundleProofsBySourceIndex[$sourceIndex],
                ]);
                $layout = $this->markSourcePdfVerifiedGeometryText($layout, $layout);
                $rebuiltRecords[] = ['text' => $layout['text'], 'layout' => $layout];
            }
            if (!$pageCanBeRebuilt) {
                continue;
            }

            foreach ($pageRecords as $record) {
                $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];
                $ranges = $this->pdfPositionedExactSourceRanges($layout);
                $belongsToRebuiltPrefix = $ranges !== [];
                foreach ($ranges as $range) {
                    if ($range['sourceIndex'] < $sourceStart
                        || $range['sourceIndex'] > $sourceEnd) {
                        $belongsToRebuiltPrefix = false;
                        break;
                    }
                }
                if (!$belongsToRebuiltPrefix) {
                    $rebuiltRecords[] = $record;
                }
            }
            $replacementRecordsByPage[$page] = $rebuiltRecords;
        }

        if ($replacementRecordsByPage === []) {
            return $unchanged();
        }

        $orderedRecords = [];
        $emittedReplacementPages = [];
        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $page = is_array($layout) ? max(1, (int) ($layout['page'] ?? 1)) : 0;
            if (!isset($replacementRecordsByPage[$page])) {
                $orderedRecords[] = $record;
                continue;
            }
            if (!isset($emittedReplacementPages[$page])) {
                array_push($orderedRecords, ...$replacementRecordsByPage[$page]);
                $emittedReplacementPages[$page] = true;
            }
        }

        $orderedLayouts = array_column($orderedRecords, 'layout');
        $orderedLayouts = $this->pdfSourceLayoutsWithWholeExactOccurrenceProofs(
            $orderedLayouts,
            $sourceItems
        );
        $orderedLayouts = $this->pdfSourceLayoutsWithExactFormTailOccurrenceProofs(
            $orderedLayouts,
            $sourceItems
        );

        return [
            'lines' => array_column($orderedRecords, 'text'),
            'layouts' => $orderedLayouts,
            'geometryPageNumbers' => array_map('intval', array_keys($replacementRecordsByPage)),
        ];
    }

    /**
     * Recognize a form tail that an earlier exact-layout pass already rebuilt
     * as one record. The final currency glyph must still be its own complete
     * source occurrence; a text suffix that merely resembles a field is not
     * enough to establish the hard row boundary.
     *
     * @param array{text:string,layout:array<string,mixed>|null} $record
     * @return array<string,mixed>|null
     */
    private function pdfRecordProvenCompositeCurrencyTailProof(array $record): ?array
    {
        if (!$this->pdfRecordIsEligibleForExactFormTailMerge($record)) {
            return null;
        }
        $layout = $record['layout'];
        $projection = $this->pdfSourceOccurrenceComparableText(
            $this->pdfTextWithoutSemanticPrefixes((string) ($record['text'] ?? ''))
        );
        if ($projection === ''
            || preg_match('/\p{N}{1,3}(?:\p{L}|\(\p{L}\))?\p{Sc}$/u', $projection) !== 1) {
            return null;
        }

        $currencyOccurrence = null;
        $sourceProofDigest = null;
        if ($this->repairedPdfWholeExactOccurrenceProofMatchesLayout($layout, $projection)) {
            $wholeProof = $layout['sourcePdfWholeExactOccurrenceProof'];
            $occurrences = array_values($wholeProof['occurrences']);
            if (count($occurrences) >= 2) {
                $currencyOccurrence = $occurrences[array_key_last($occurrences)];
                $sourceProofDigest = $wholeProof['proofDigest'];
            }
        } elseif ($this->pdfExactFormTailOccurrenceProofMatchesLayout($layout, $projection)) {
            $exactTailProof = $layout['sourcePdfExactFormTailOccurrenceProof'];
            $currencyOccurrence = $exactTailProof['terminalOccurrence'];
            $sourceProofDigest = $exactTailProof['proofDigest'];
        }
        if (!is_array($currencyOccurrence) || !is_string($sourceProofDigest)) {
            return null;
        }
        $currencyBytes = $currencyOccurrence['significantBytes'];
        $currencyProjection = substr($projection, -$currencyBytes);
        $carrierProjection = substr($projection, 0, strlen($projection) - $currencyBytes);
        if ($currencyProjection === ''
            || $carrierProjection === ''
            || preg_match('/^\p{Sc}$/u', $currencyProjection) !== 1
            || preg_match('/\p{N}{1,3}(?:\p{L}|\(\p{L}\))?$/u', $carrierProjection) !== 1) {
            return null;
        }

        $proof = [
            'version' => 1,
            'method' => 'exact-source-composite-form-tail',
            'sourceIndexes' => $this->pdfSemanticRecordGlobalSourceIndexes($record),
            'currencySourceIndex' => $currencyOccurrence['sourceIndex'],
            'sourceOccurrenceProofDigest' => $sourceProofDigest,
            'tailProjectionDigest' => hash('sha256', $currencyProjection),
        ];
        $proof['proofDigest'] = hash('sha256', json_encode(
            $proof,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');

        return $proof;
    }

    /** @param array<string,mixed> $layout */
    private function pdfExactFormTailOccurrenceProofMatchesLayout(
        array $layout,
        string $carrierProjection
    ): bool {
        $proof = is_array($layout['sourcePdfExactFormTailOccurrenceProof'] ?? null)
            ? $layout['sourcePdfExactFormTailOccurrenceProof']
            : null;
        if ($proof === null
            || ($proof['version'] ?? null) !== 1
            || ($proof['method'] ?? null) !== 'source-inventory-exact-form-tail-occurrence'
            || !is_int($proof['page'] ?? null)
            || !is_array($proof['ranges'] ?? null)
            || !array_is_list($proof['ranges'])
            || !is_array($proof['terminalOccurrence'] ?? null)
            || !is_string($proof['projectionDigest'] ?? null)
            || !is_string($proof['proofDigest'] ?? null)
            || (int) ($layout['page'] ?? 0) !== $proof['page']
            || $this->pdfPositionedExactSourceRanges($layout) !== array_values($proof['ranges'])
            || !hash_equals($proof['projectionDigest'], hash('sha256', $carrierProjection))) {
            return false;
        }

        $terminal = $proof['terminalOccurrence'];
        $ranges = array_values($proof['ranges']);
        $terminalRange = $ranges[array_key_last($ranges)] ?? null;
        if (!is_array($terminalRange)
            || !is_int($terminal['sourceIndex'] ?? null)
            || !is_int($terminal['sourceLocalIndex'] ?? null)
            || ($terminal['page'] ?? null) !== $proof['page']
            || !is_int($terminal['stream'] ?? null)
            || $terminal['stream'] < 1
            || !is_int($terminal['significantBytes'] ?? null)
            || $terminal['significantBytes'] <= 0
            || !is_string($terminal['significantDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $terminal['significantDigest']) !== 1
            || $terminalRange !== [
                'sourceIndex' => $terminal['sourceIndex'],
                'sourceStart' => 0,
                'sourceEnd' => $terminal['significantBytes'],
            ]) {
            return false;
        }
        $terminalProjection = substr($carrierProjection, -$terminal['significantBytes']);
        if (strlen($terminalProjection) !== $terminal['significantBytes']
            || preg_match('/^\p{Sc}$/u', $terminalProjection) !== 1
            || !hash_equals(
                $terminal['significantDigest'],
                hash('sha256', $terminalProjection)
            )) {
            return false;
        }

        $payload = [
            'version' => 1,
            'method' => 'source-inventory-exact-form-tail-occurrence',
            'page' => $proof['page'],
            'ranges' => $ranges,
            'terminalOccurrence' => $terminal,
            'projectionDigest' => $proof['projectionDigest'],
        ];
        $expectedDigest = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');

        return hash_equals($proof['proofDigest'], $expectedDigest);
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $left
     * @param array{text:string,layout:array<string,mixed>|null} $currency
     */
    private function pdfRecordsFormProvenCurrencyTail(array $left, array $currency): bool
    {
        $currencyLayout = is_array($currency['layout'] ?? null) ? $currency['layout'] : null;

        return preg_match('/^\p{Sc}$/u', trim((string) ($currency['text'] ?? ''))) === 1
            && is_array($currencyLayout)
            && (($currencyLayout['forceBlockBreakBefore'] ?? false) !== true)
            && $this->pdfRecordLooksLikeFormFieldCarrier($left)
            && $this->pdfRecordsHaveConsecutiveSourceOccurrences($left, $currency);
    }

    /** @param array{text:string,layout:array<string,mixed>|null} $record */
    private function pdfRecordLooksLikeFormFieldCarrier(array $record): bool
    {
        $text = trim((string) ($record['text'] ?? ''));

        return $this->pdfRecordIsEligibleForExactFormTailMerge($record)
            && preg_match('/\p{N}{1,3}(?:\p{L}|\(\p{L}\))?\h*$/u', $text) === 1;
    }

    /** @param array{text:string,layout:array<string,mixed>|null} $record */
    private function pdfRecordLooksLikeCompactFormFieldId(array $record): bool
    {
        return $this->pdfRecordIsEligibleForExactFormTailMerge($record)
            && preg_match(
                '/^\p{N}{1,3}(?:\p{L}|\(\p{L}\))?$/u',
                trim((string) ($record['text'] ?? ''))
            ) === 1;
    }

    /** @param array{text:string,layout:array<string,mixed>|null} $record */
    private function pdfRecordEndsWithDottedFormLeader(array $record): bool
    {
        if (!$this->pdfRecordIsEligibleForExactFormTailMerge($record)
            || count($this->pdfLineWordTokens((string) ($record['text'] ?? ''))) < 4) {
            return false;
        }

        return preg_match(
            '/(?:(?:\.\h*){4,}|(?:\.\h+){1,}\.)'
                . '(?:\h*\p{N}{1,3}(?:\p{L}|\(\p{L}\))?)?\h*$/u',
            trim((string) ($record['text'] ?? ''))
        ) === 1;
    }

    /** @param array{text:string,layout:array<string,mixed>|null} $record */
    private function pdfRecordIsEligibleForExactFormTailMerge(array $record): bool
    {
        $text = (string) ($record['text'] ?? '');
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        if ($text === ''
            || !is_array($layout)
            || $this->pdfLineStartsWithSemanticPrefix($text)
            || ($layout['code'] ?? false) === true
            || is_string($layout['sourcePdfRegionRole'] ?? null)
            || ($layout['sourcePdfLayoutLabel'] ?? false) === true
            || ($layout['sourcePdfFrontMatter'] ?? false) === true
            || ($layout['sourceSupplementalPositioned'] ?? false) === true
            || (int) ($layout['sourcePdfInlineMarkerCount'] ?? 0) > 0
            || $this->lineHasPdfListBlockEvidence($text)) {
            return false;
        }

        $layoutText = is_string($layout['text'] ?? null) ? $layout['text'] : '';

        return $layoutText !== ''
            && hash_equals(
                $this->pdfSourceOccurrenceComparableText($layoutText),
                $this->pdfSourceOccurrenceComparableText($text)
            )
            && $this->pdfSemanticRecordGlobalSourceIndexes($record) !== [];
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $left
     * @param array{text:string,layout:array<string,mixed>|null} $right
     */
    private function pdfRecordsHaveConsecutiveSourceOccurrences(array $left, array $right): bool
    {
        if (!$this->pdfRecordIsEligibleForExactFormTailMerge($left)
            || !$this->pdfRecordIsEligibleForExactFormTailMerge($right)) {
            return false;
        }
        $leftLayout = $left['layout'];
        $rightLayout = $right['layout'];
        if ((int) ($leftLayout['page'] ?? 0) !== (int) ($rightLayout['page'] ?? 0)) {
            return false;
        }
        $leftIndexes = $this->pdfSemanticRecordGlobalSourceIndexes($left);
        $rightIndexes = $this->pdfSemanticRecordGlobalSourceIndexes($right);
        if ($leftIndexes === []
            || $rightIndexes === []
            || $leftIndexes[array_key_last($leftIndexes)] + 1 !== $rightIndexes[0]) {
            return false;
        }

        $leftStreams = $this->pdfSemanticRecordSourceStreams($left);
        $rightStreams = $this->pdfSemanticRecordSourceStreams($right);
        if (count($leftStreams) === 1
            && count($rightStreams) === 1
            && $leftStreams[0] === $rightStreams[0]) {
            return true;
        }

        // A page can paint one form field through adjacent /Contents streams.
        // Crossing that boundary additionally requires exact inline geometry;
        // document-global adjacency by itself is not a visual-order proof.
        if (!$this->pdfLayoutHasGeometry($leftLayout)
            || !$this->pdfLayoutHasGeometry($rightLayout)) {
            return false;
        }
        $fontSize = max(1.0, (float) $leftLayout['fontSize'], (float) $rightLayout['fontSize']);
        $leftCenter = ((float) $leftLayout['y1'] + (float) $leftLayout['y2']) / 2.0;
        $rightCenter = ((float) $rightLayout['y1'] + (float) $rightLayout['y2']) / 2.0;
        $horizontalGap = (float) $rightLayout['x1'] - (float) $leftLayout['x2'];

        return abs($leftCenter - $rightCenter) <= max(2.0, $fontSize * 0.30)
            && (float) $rightLayout['x1'] >= (float) $leftLayout['x1']
            && $horizontalGap >= -max(2.0, $fontSize * 0.20)
            && $horizontalGap <= max(24.0, $fontSize * 2.0);
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $record
     * @return list<int>
     */
    private function pdfSemanticRecordGlobalSourceIndexes(array $record): array
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        if (!is_array($layout)) {
            return [];
        }
        $candidates = [];
        if (is_array($layout['sourcePdfGlobalSourceIndexes'] ?? null)) {
            $candidates = $layout['sourcePdfGlobalSourceIndexes'];
        } else {
            $ranges = $this->pdfPositionedExactSourceRanges($layout);
            if ($ranges !== []) {
                foreach ($ranges as $range) {
                    if ($candidates === [] || $candidates[array_key_last($candidates)] !== $range['sourceIndex']) {
                        $candidates[] = $range['sourceIndex'];
                    }
                }
            } elseif (is_int($layout['sourcePdfGlobalSourceIndex'] ?? null)) {
                $candidates = [$layout['sourcePdfGlobalSourceIndex']];
            }
        }
        $indexes = [];
        foreach ($candidates as $candidate) {
            if (!is_int($candidate)
                || $candidate < 0
                || ($indexes !== [] && $candidate !== $indexes[array_key_last($indexes)] + 1)) {
                return [];
            }
            $indexes[] = $candidate;
        }

        return $indexes;
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $record
     * @return list<int>
     */
    private function pdfSemanticRecordSourceStreams(array $record): array
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        if (!is_array($layout)) {
            return [];
        }
        $candidates = is_array($layout['sourcePdfStreams'] ?? null)
            ? $layout['sourcePdfStreams']
            : (isset($layout['sourceStream']) && is_numeric($layout['sourceStream'])
                ? [(int) $layout['sourceStream']]
                : (isset($layout['stream']) && is_numeric($layout['stream'])
                    ? [(int) $layout['stream']]
                    : []));
        $streams = [];
        foreach ($candidates as $candidate) {
            if (!is_int($candidate) && !is_numeric($candidate)) {
                return [];
            }
            $streams[(int) $candidate] = true;
        }
        $streams = array_keys($streams);
        sort($streams, SORT_NUMERIC);

        return $streams;
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $left
     * @param array{text:string,layout:array<string,mixed>|null} $right
     * @return array{text:string,layout:array<string,mixed>}
     */
    private function mergeProvenPdfFormTailRecords(array $left, array $right): array
    {
        $text = rtrim($left['text']) . ' ' . ltrim($right['text']);
        $leftLayout = is_array($left['layout'] ?? null) ? $left['layout'] : [];
        $rightLayout = is_array($right['layout'] ?? null) ? $right['layout'] : [];
        $layout = $leftLayout;
        $sourceIds = [];
        foreach ([$leftLayout, $rightLayout] as $sourceLayout) {
            $ids = is_array($sourceLayout['sourcePdfSourceIds'] ?? null)
                ? $sourceLayout['sourcePdfSourceIds']
                : (is_string($sourceLayout['id'] ?? null) && $sourceLayout['id'] !== ''
                    ? [$sourceLayout['id']]
                    : []);
            foreach ($ids as $id) {
                if (is_string($id) && $id !== '') {
                    $sourceIds[$id] = true;
                }
            }
        }

        $proofItems = [
            array_replace($leftLayout, ['text' => $left['text']]),
            array_replace($rightLayout, ['text' => $right['text']]),
        ];
        $exactRanges = $this->positionedTableExactSourceRangesForItems($proofItems);
        $indexCandidates = $exactRanges !== null && $exactRanges !== []
            ? array_column($exactRanges, 'sourceIndex')
            : array_merge(
                $this->pdfSemanticRecordGlobalSourceIndexes($left),
                $this->pdfSemanticRecordGlobalSourceIndexes($right)
            );
        $indexes = [];
        foreach ($indexCandidates as $sourceIndex) {
            $lastIndex = array_key_last($indexes);
            if ($lastIndex !== null && $indexes[$lastIndex] === $sourceIndex) {
                continue;
            }
            if (!is_int($sourceIndex)
                || $sourceIndex < 0
                || ($lastIndex !== null && $sourceIndex !== $indexes[$lastIndex] + 1)) {
                $indexes = [];
                break;
            }
            $indexes[] = $sourceIndex;
        }
        $streams = array_values(array_unique(array_merge(
            $this->pdfSemanticRecordSourceStreams($left),
            $this->pdfSemanticRecordSourceStreams($right)
        ), SORT_NUMERIC));
        sort($streams, SORT_NUMERIC);

        unset(
            $layout['id'],
            $layout['sourcePdfGlobalSourceIndex'],
            $layout['sourcePdfSourceIndex'],
            $layout['sourcePdfSourceIndexEnd'],
            $layout['sourcePdfSourceIndexes'],
            $layout['sourcePdfExactSourceIndex'],
            $layout['sourcePdfExactSourceStart'],
            $layout['sourcePdfExactSourceEnd'],
            $layout['sourcePdfExactSourceRanges'],
            $layout['sourcePdfWholeExactOccurrenceProof'],
            $layout['sourceStream'],
            $layout['stream']
        );
        $layout['text'] = $text;
        $layout['sourcePdfGlobalSourceIndexes'] = $indexes;
        if ($sourceIds !== []) {
            $layout['sourcePdfSourceIds'] = array_keys($sourceIds);
        }
        if ($exactRanges !== null && $exactRanges !== []) {
            $layout['sourcePdfExactSourceRanges'] = $exactRanges;
        }
        if ($streams !== []) {
            $layout['sourcePdfStreams'] = $streams;
            if (count($streams) === 1) {
                $layout['sourceStream'] = $streams[0];
            }
        }
        if ($this->pdfLayoutHasGeometry($leftLayout) && $this->pdfLayoutHasGeometry($rightLayout)) {
            $layout['x1'] = min((float) $leftLayout['x1'], (float) $rightLayout['x1']);
            $layout['y1'] = min((float) $leftLayout['y1'], (float) $rightLayout['y1']);
            $layout['x2'] = max((float) $leftLayout['x2'], (float) $rightLayout['x2']);
            $layout['y2'] = max((float) $leftLayout['y2'], (float) $rightLayout['y2']);
            $layout['fontSize'] = max(1.0, (float) $leftLayout['fontSize'], (float) $rightLayout['fontSize']);
        } else {
            unset($layout['x1'], $layout['y1'], $layout['x2'], $layout['y2'], $layout['fontSize']);
        }
        $layout['sourcePdfFormTailComposite'] = true;
        // The ordinary prose merger may still attach wrapped row text from
        // the left. Once this carrier becomes pending, however, the proved
        // form field is a hard right edge and must not absorb the next row,
        // Step instruction, or legal notice.
        $layout['sourcePdfFormTailEnd'] = true;
        $layout['sourcePdfFormTailProof'] = [
            'method' => 'consecutive-source-occurrence-form-tail',
            'sourceIndexes' => $indexes,
            'proofDigest' => hash('sha256', json_encode(
                [$indexes, $streams, array_keys($sourceIds), $text],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?: ''),
        ];

        return ['text' => $text, 'layout' => $layout];
    }

    /**
     * A positioned form row may begin at a proved suffix of one source
     * occurrence and end with complete adjacent field-ID and currency
     * occurrences. It is therefore ineligible for a whole-union proof even
     * though its terminal currency occurrence is exact.
     *
     * @param list<array<string,mixed>> $sourceLayouts
     * @param list<array<string,mixed>> $sourceItems
     * @return list<array<string,mixed>>
     */
    private function pdfSourceLayoutsWithExactFormTailOccurrenceProofs(
        array $sourceLayouts,
        array $sourceItems
    ): array {
        foreach ($sourceLayouts as &$layout) {
            if (!is_array($layout)) {
                continue;
            }
            unset($layout['sourcePdfExactFormTailOccurrenceProof']);
            $proof = $this->pdfSourceLayoutExactFormTailOccurrenceProof($layout, $sourceItems);
            if ($proof !== null) {
                $layout['sourcePdfExactFormTailOccurrenceProof'] = $proof;
            }
        }
        unset($layout);

        return $sourceLayouts;
    }

    /**
     * @param array<string,mixed> $layout
     * @param list<array<string,mixed>> $sourceItems
     * @return array<string,mixed>|null
     */
    private function pdfSourceLayoutExactFormTailOccurrenceProof(
        array $layout,
        array $sourceItems
    ): ?array {
        if (!$this->pdfLayoutHasGeometry($layout)
            || ($layout['sourcePdfExactGeometryFallback'] ?? false) === true
            || ($layout['sourceUnmatchedFallback'] ?? false) === true
            || ($layout['sourceSupplementalPositioned'] ?? false) === true
            || ($layout['sourceShortSupplementalCandidate'] ?? false) === true
            || ($layout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true
            || !is_numeric($layout['page'] ?? null)) {
            return null;
        }
        $projection = $this->pdfSourceOccurrenceComparableText(
            $this->pdfTextWithoutSemanticPrefixes((string) ($layout['text'] ?? ''))
        );
        $ranges = $this->pdfPositionedExactSourceRanges($layout);
        if ($projection === ''
            || count($ranges) < 2
            || preg_match('/\p{N}{1,3}(?:\p{L}|\(\p{L}\))?\p{Sc}$/u', $projection) !== 1) {
            return null;
        }

        $localIndexByGlobalIndex = [];
        foreach ($sourceItems as $localIndex => $sourceItem) {
            $globalIndex = is_int($sourceItem['sourcePdfGlobalSourceIndex'] ?? null)
                ? $sourceItem['sourcePdfGlobalSourceIndex']
                : $localIndex;
            if (isset($localIndexByGlobalIndex[$globalIndex])) {
                return null;
            }
            $localIndexByGlobalIndex[$globalIndex] = $localIndex;
        }

        $page = max(1, (int) $layout['page']);
        $sourceProjection = '';
        $previousGlobalIndex = null;
        $previousLocalIndex = null;
        $terminalOccurrence = null;
        foreach ($ranges as $offset => $range) {
            $globalIndex = $range['sourceIndex'];
            $localIndex = $localIndexByGlobalIndex[$globalIndex] ?? null;
            $sourceItem = is_int($localIndex) ? ($sourceItems[$localIndex] ?? null) : null;
            $sourceSignificant = is_array($sourceItem)
                ? $this->pdfSourceOccurrenceComparableText((string) ($sourceItem['text'] ?? ''))
                : '';
            if (!is_array($sourceItem)
                || $sourceSignificant === ''
                || !$this->sourcePdfSourceItemHasExactGeometry($sourceItem)
                || (int) ($sourceItem['page'] ?? 0) !== $page
                || $range['sourceEnd'] !== strlen($sourceSignificant)
                || ($offset > 0 && $range['sourceStart'] !== 0)
                || ($previousGlobalIndex !== null && $globalIndex !== $previousGlobalIndex + 1)
                || ($previousLocalIndex !== null && $localIndex !== $previousLocalIndex + 1)) {
                return null;
            }
            $sourceProjection .= substr(
                $sourceSignificant,
                $range['sourceStart'],
                $range['sourceEnd'] - $range['sourceStart']
            );
            $previousGlobalIndex = $globalIndex;
            $previousLocalIndex = $localIndex;
            if ($offset === array_key_last($ranges)) {
                $terminalOccurrence = [
                    'sourceIndex' => $globalIndex,
                    'sourceLocalIndex' => $localIndex,
                    'page' => $page,
                    'stream' => max(1, (int) ($sourceItem['stream'] ?? 1)),
                    'significantBytes' => strlen($sourceSignificant),
                    'significantDigest' => hash('sha256', $sourceSignificant),
                ];
            }
        }
        if (!hash_equals($sourceProjection, $projection)
            || !is_array($terminalOccurrence)
            || preg_match(
                '/^\p{Sc}$/u',
                substr($projection, -$terminalOccurrence['significantBytes'])
            ) !== 1) {
            return null;
        }

        $proof = [
            'version' => 1,
            'method' => 'source-inventory-exact-form-tail-occurrence',
            'page' => $page,
            'ranges' => $ranges,
            'terminalOccurrence' => $terminalOccurrence,
            'projectionDigest' => hash('sha256', $projection),
        ];
        $proof['proofDigest'] = hash('sha256', json_encode(
            $proof,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '');

        return $proof;
    }

    private function callback(string $name): callable
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \LogicException('Missing PdfExactFormRowTailRepair callback: ' . $name);
        }

        return $callback;
    }

    private function lineHasPdfListBlockEvidence(string $line): bool
    {
        return (bool) ($this->callback('lineHasPdfListBlockEvidence'))($line);
    }

    private function lineIsOnlyPdfNoise(string $line): bool
    {
        return (bool) ($this->callback('lineIsOnlyPdfNoise'))($line);
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $positionedItem */
    private function markSourcePdfVerifiedGeometryText(array $item, array $positionedItem): array
    {
        return ($this->callback('markSourcePdfVerifiedGeometryText'))($item, $positionedItem);
    }

    /** @param list<int|float> $values */
    private function median(array $values): float
    {
        return (float) ($this->callback('median'))($values);
    }

    /** @param array<string,mixed>|null $layout */
    private function pdfLayoutHasGeometry(?array $layout): bool
    {
        return (bool) ($this->callback('pdfLayoutHasGeometry'))($layout);
    }

    private function pdfLineStartsWithSemanticPrefix(string $line): bool
    {
        return (bool) ($this->callback('pdfLineStartsWithSemanticPrefix'))($line);
    }

    /** @return list<string> */
    private function pdfLineWordTokens(string $line): array
    {
        return ($this->callback('pdfLineWordTokens'))($line);
    }

    /** @param array<string,mixed> $item @return list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}> */
    private function pdfPositionedExactSourceRanges(array $item): array
    {
        return ($this->callback('pdfPositionedExactSourceRanges'))($item);
    }

    /** @param array<string,mixed> $bounds */
    private function pdfSourceBoundsAreValid(array $bounds): bool
    {
        return (bool) ($this->callback('pdfSourceBoundsAreValid'))($bounds);
    }

    /** @param array<string,mixed> $geometry @return array{x1:float,y1:float,x2:float,y2:float} */
    private function pdfSourceEvidenceBounds(array $geometry): array
    {
        return ($this->callback('pdfSourceEvidenceBounds'))($geometry);
    }

    /**
     * @param list<array<string,mixed>> $sourceLayouts
     * @param list<array<string,mixed>> $sourceItems
     * @return list<array<string,mixed>>
     */
    private function pdfSourceLayoutsWithWholeExactOccurrenceProofs(
        array $sourceLayouts,
        array $sourceItems
    ): array {
        return ($this->callback('pdfSourceLayoutsWithWholeExactOccurrenceProofs'))(
            $sourceLayouts,
            $sourceItems
        );
    }

    private function pdfSourceOccurrenceComparableText(string $text): string
    {
        return (string) ($this->callback('pdfSourceOccurrenceComparableText'))($text);
    }

    private function pdfTextWithoutSemanticPrefixes(string $text): string
    {
        return (string) ($this->callback('pdfTextWithoutSemanticPrefixes'))($text);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array{sourceIndex:int,sourceStart:int,sourceEnd:int}>|null
     */
    private function positionedTableExactSourceRangesForItems(array $items): ?array
    {
        return ($this->callback('positionedTableExactSourceRangesForItems'))($items);
    }

    /** @param array<string,mixed> $layout */
    private function repairedPdfWholeExactOccurrenceProofMatchesLayout(
        array $layout,
        string $carrierProjection
    ): bool {
        return (bool) ($this->callback('repairedPdfWholeExactOccurrenceProofMatchesLayout'))(
            $layout,
            $carrierProjection
        );
    }

    /** @param array<string,mixed> $sourceItem @return array<string,mixed> */
    private function sourcePdfLineItem(array $sourceItem): array
    {
        return ($this->callback('sourcePdfLineItem'))($sourceItem);
    }

    /** @param array<string,mixed> $sourceItem */
    private function sourcePdfSourceItemHasExactGeometry(array $sourceItem): bool
    {
        return (bool) ($this->callback('sourcePdfSourceItemHasExactGeometry'))($sourceItem);
    }
}
