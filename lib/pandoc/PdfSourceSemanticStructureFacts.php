<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Shared semantic list facts used by pre- and post-decoration validation.
 */
abstract class PdfSourceSemanticStructureFacts
{
    /** @var array<string,true> */
    protected const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /**
     * @param list<AstNode> $blocks
     * @param list<array<string,mixed>> $ranges
     * @return list<array<string,mixed>>
     */
    protected static function sourceBindingSemanticListTargets(
        array $blocks,
        array $ranges
    ): array {
        $rangesAreOrdered = true;
        $previousStart = null;
        $previousEnd = null;
        foreach ($ranges as $range) {
            $start = (int) ($range['outputStart'] ?? 0);
            $end = (int) ($range['outputEnd'] ?? 0);
            if ($previousStart !== null
                && ($start < $previousStart
                    || ($start === $previousStart && $end < $previousEnd))) {
                $rangesAreOrdered = false;
                break;
            }
            $previousStart = $start;
            $previousEnd = $end;
        }
        if (!$rangesAreOrdered) {
            usort($ranges, static fn (array $left, array $right): int =>
                ((int) ($left['outputStart'] ?? 0) <=> (int) ($right['outputStart'] ?? 0))
                    ?: ((int) ($left['outputEnd'] ?? 0) <=> (int) ($right['outputEnd'] ?? 0))
            );
        }
        $targets = [];
        $cursor = 0;
        foreach ($blocks as $blockIndex => $block) {
            $blockStart = $cursor;
            $blockSignificant = self::sourceBindingNodeSignificantText($block);
            $blockEnd = $blockStart + strlen($blockSignificant);
            $listType = match ($block->type) {
                'ordered_list' => 'ordered',
                'bullet_list' => 'bullet',
                default => null,
            };
            if ($listType !== null) {
                $start = $listType === 'ordered' ? $block->attr('start', 1) : null;
                if ($listType !== 'ordered' || (is_int($start) && $start >= 1)) {
                    $itemCursor = $blockStart;
                    foreach ($block->children() as $itemIndex => $item) {
                        $itemSignificant = self::sourceBindingNodeSignificantText($item);
                        $itemStart = $itemCursor;
                        $itemEnd = $itemStart + strlen($itemSignificant);
                        $itemCursor = $itemEnd;
                        if ($item->type !== 'list_item') {
                            continue;
                        }
                        $itemVisible = trim(self::sourceBindingNodeVisibleText($item));
                        $itemStrictVisible = self::sourceBindingListItemStrictVisibleText($item);
                        $itemEdges = self::sourceBindingEdgesForOutputSpan(
                            $ranges,
                            $itemStart,
                            $itemEnd
                        );
                        if ($itemSignificant === ''
                            || $itemVisible === ''
                            || $itemStrictVisible === ''
                            || $itemEdges === []) {
                            continue;
                        }
                        $visibleBlockCount = 0;
                        foreach ($item->children() as $itemBlock) {
                            if (self::sourceBindingComparableVisibleText(
                                self::sourceBindingNodeVisibleText($itemBlock)
                            ) !== '') {
                                $visibleBlockCount++;
                            }
                        }
                        $listEdges = self::sourceBindingEdgesForOutputSpan(
                            $ranges,
                            $blockStart,
                            $blockEnd
                        );
                        $listSourceIds = [];
                        foreach ($listEdges as $edge) {
                            $listSourceIds[$edge['sourceLineId']] = true;
                        }
                        $itemSourceIds = [];
                        foreach ($itemEdges as $edge) {
                            $itemSourceIds[$edge['sourceLineId']] = true;
                        }
                        $targets[] = [
                            'targetKey' => $blockIndex . ':' . $itemIndex,
                            'blockIndex' => $blockIndex,
                            'itemIndex' => $itemIndex,
                            'listType' => $listType,
                            'ordinal' => $listType === 'ordered' ? $start + $itemIndex : null,
                            'itemSignificant' => $itemSignificant,
                            'itemVisible' => $itemVisible,
                            'itemStrictVisible' => $itemStrictVisible,
                            'itemProjectionDigest' => hash('sha256', $itemSignificant),
                            'itemEdges' => $itemEdges,
                            'listSourceIds' => $listSourceIds,
                            'itemSourceIds' => $itemSourceIds,
                            'visibleBlockCount' => $visibleBlockCount,
                        ];
                    }
                }
            }
            $cursor = $blockEnd;
        }

        return $targets;
    }

    /** @param list<array<string,mixed>> $ranges @return list<array<string,mixed>> */
    private static function sourceBindingEdgesForOutputSpan(
        array $ranges,
        int $spanStart,
        int $spanEnd
    ): array {
        if ($spanEnd <= $spanStart) {
            return [];
        }
        $edges = [];
        foreach ($ranges as $range) {
            $rangeStart = (int) ($range['outputStart'] ?? 0);
            $rangeEnd = (int) ($range['outputEnd'] ?? 0);
            $start = max($rangeStart, $spanStart);
            $end = min($rangeEnd, $spanEnd);
            if ($end <= $start) {
                continue;
            }
            $sourceStart = (int) ($range['sourceStart'] ?? 0) + ($start - $rangeStart);
            $edges[] = [
                'sourceLineId' => (string) ($range['sourceOccurrenceId'] ?? ''),
                'startByte' => $sourceStart,
                'endByte' => $sourceStart + ($end - $start),
            ];
        }

        return $edges;
    }

    /**
     * @param list<array<string,mixed>> $edges
     * @param list<array{sourceOccurrenceId:string,length:int}> $prefixes
     */
    protected static function sourceBindingItemStartsWithSourceRanges(
        array $edges,
        array $prefixes
    ): bool {
        $edgeIndex = 0;
        foreach ($prefixes as $prefix) {
            $sourceId = $prefix['sourceOccurrenceId'];
            $length = $prefix['length'];
            if ($sourceId === '' || $length < 1) {
                return false;
            }
            $cursor = 0;
            while ($cursor < $length) {
                $edge = $edges[$edgeIndex] ?? null;
                if (!is_array($edge)
                    || ($edge['sourceLineId'] ?? null) !== $sourceId
                    || ($edge['startByte'] ?? null) !== $cursor
                    || !is_int($edge['endByte'] ?? null)
                    || $edge['endByte'] <= $cursor
                    || $edge['endByte'] > $length) {
                    return false;
                }
                $cursor = $edge['endByte'];
                $edgeIndex++;
            }
        }

        return true;
    }

    protected static function sourceBindingComparableVisibleText(string $text): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return trim(preg_replace('/[\s\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '');
    }

    /** @param list<array<string,mixed>|null> $anchors */
    protected static function sourceBindingExactOrdinaryAnchorSequence(
        array $marker,
        array $anchors,
        ?array $followingAnchor = null,
        array $bindingContext = []
    ): ?array {
        $hasCompositeReceipt = is_array(
            $marker['semanticStructureProof']['compositeLayoutPresentationReceipt'] ?? null
        );
        $hasPresentationReceipt = is_array(
            $marker['semanticStructureProof']['presentationRepairReceipt'] ?? null
        );
        $minimumAnchorCount = $hasCompositeReceipt ? 1 : ($hasPresentationReceipt ? 2 : 3);
        if (($hasCompositeReceipt && $hasPresentationReceipt)
            || count($anchors) < $minimumAnchorCount
            || count($anchors) > 16) {
            return null;
        }
        $page = $marker['page'] ?? null;
        $stream = $marker['stream'] ?? null;
        if (!is_int($page) || !is_int($stream) || $page < 1 || $stream < 1) {
            return null;
        }

        $significant = '';
        $visible = '';
        $significantBoundaryOffsets = [];
        $visibleBoundarySpaces = [];
        $seen = [];
        foreach ($anchors as $index => $anchor) {
            if (!is_array($anchor)) {
                return null;
            }
            $sourceId = (string) ($anchor['id'] ?? '');
            $sourceText = (string) ($anchor['sourceText'] ?? '');
            $projectionText = (string) ($anchor['projectionText'] ?? '');
            $recordSignificant = (string) ($anchor['significant'] ?? '');
            $recordVisible = self::sourceBindingComparableVisibleText($projectionText);
            if ($sourceId === ''
                || isset($seen[$sourceId])
                || ($anchor['page'] ?? null) !== $page
                || ($anchor['stream'] ?? null) !== $stream
                || !isset(self::OUTPUT_DISPOSITIONS[$anchor['disposition'] ?? ''])
                || is_array($anchor['semanticStructureProof'] ?? null)
                || $recordSignificant === ''
                || $recordVisible === ''
                || !hash_equals($sourceText, $projectionText)) {
                return null;
            }
            $seen[$sourceId] = true;
            if ($index > 0) {
                $hasSpace = preg_match('/^[,.;:!?\)\]\}]/u', ltrim($projectionText)) !== 1;
                $significantBoundaryOffsets[] = strlen($significant);
                $visibleBoundarySpaces[] = $hasSpace;
                $visible .= $hasSpace ? ' ' : '';
            }
            $significant .= $recordSignificant;
            $visible .= $recordVisible;
        }

        $sequence = [
            'significant' => $significant,
            'visible' => $visible,
            'significantBoundaryOffsets' => $significantBoundaryOffsets,
            'visibleBoundarySpaces' => $visibleBoundarySpaces,
        ];
        $compositeReceipt =
            $marker['semanticStructureProof']['compositeLayoutPresentationReceipt'] ?? null;
        $presentationReceipt =
            $marker['semanticStructureProof']['presentationRepairReceipt'] ?? null;
        if ($compositeReceipt === null && $presentationReceipt === null) {
            return $sequence;
        }
        if (($compositeReceipt !== null && !is_array($compositeReceipt))
            || ($presentationReceipt !== null && !is_array($presentationReceipt))) {
            return null;
        }

        return PdfSourceSemanticReceiptBindingValidator::sourceBindingReceiptAnchorSequence(
            $marker,
            $anchors,
            $followingAnchor,
            $bindingContext,
            $sequence
        );
    }

    protected static function sourceBindingExtendedAnchorMatchesListTarget(
        array $sequence,
        array $target
    ): bool {
        if (is_string($sequence['compositeVisiblePrefix'] ?? null)
            || is_string($sequence['presentationRepairVisiblePrefix'] ?? null)) {
            return PdfSourceSemanticReceiptBindingValidator::
                sourceBindingReceiptSequenceMatchesTarget($sequence, $target);
        }

        return str_starts_with($target['itemVisible'], $sequence['visible']);
    }

    private static function sourceBindingStrictVisibleText(string $text): string
    {
        if ($text !== '' && preg_match('//u', $text) !== 1) {
            return '';
        }
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return trim($text);
    }

    protected static function sourceBindingListItemStrictVisibleText(AstNode $item): string
    {
        $parts = [];
        foreach ($item->children() as $block) {
            $text = $block->attr('text');
            if (!is_string($text)) {
                $text = self::sourceBindingNodeVisibleText($block);
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return self::sourceBindingStrictVisibleText(implode(' ', $parts));
    }

    /** @param array<string,mixed>|null $following */
    protected static function sourceBindingAnchorMatchesListItem(
        array $anchor,
        ?array $following,
        string $itemSignificant,
        string $itemVisible
    ): bool {
        $anchorSignificant = (string) ($anchor['significant'] ?? '');
        if ($anchorSignificant === '') {
            return false;
        }
        $projectionText = (string) ($anchor['projectionText'] ?? '');
        $anchorVisible = self::sourceBindingComparableVisibleText($projectionText);
        if ($anchorVisible === '') {
            return false;
        }
        if (hash_equals($itemSignificant, $anchorSignificant)) {
            return hash_equals($itemVisible, $anchorVisible);
        }
        if (!str_starts_with($itemSignificant, $anchorSignificant) || !is_array($following)) {
            return false;
        }

        $followingSignificant = (string) ($following['significant'] ?? '');
        $page = $anchor['page'] ?? null;
        $stream = $anchor['stream'] ?? null;
        if (!is_int($page)
            || !is_int($stream)
            || $stream < 1
            || ($following['page'] ?? null) !== $page
            || ($following['stream'] ?? null) !== $stream
            || !isset(self::OUTPUT_DISPOSITIONS[$following['disposition'] ?? ''])
            || $followingSignificant === ''
            || !str_starts_with(
                $itemSignificant,
                $anchorSignificant . $followingSignificant
            )) {
            return false;
        }

        $sourceText = (string) ($anchor['sourceText'] ?? '');
        $followingText = (string) ($following['sourceText'] ?? '');
        $followingProjection = (string) ($following['projectionText'] ?? '');
        $followingVisible = self::sourceBindingComparableVisibleText($followingProjection);
        if ($followingVisible === '') {
            return false;
        }
        $anchorEvidence = is_array($anchor['evidence'] ?? null) ? $anchor['evidence'] : [];
        if (array_key_exists('wrappedHyphenBoundaryRepair', $anchorEvidence)) {
            $evidence = $anchorEvidence['wrappedHyphenBoundaryRepair'];
            $sourceIds = is_array($evidence) ? ($evidence['sourceOccurrenceIds'] ?? null) : null;
            if (!is_array($evidence)
                || ($evidence['method'] ?? null)
                    !== 'exact-directional-source-wrapped-hyphen-boundary'
                || !is_array($sourceIds)
                || ($sourceIds['preceding'] ?? null) !== ($anchor['id'] ?? null)
                || ($sourceIds['following'] ?? null) !== ($following['id'] ?? null)
                || ($evidence['page'] ?? null) !== $page
                || ($evidence['stream'] ?? null) !== $stream
                || !self::sourceBindingHasExactWrappedHyphenProjection(
                    $sourceText,
                    $projectionText,
                    (string) ($evidence['suppressionKind'] ?? '')
                )
                || !is_string($evidence['originalDigest'] ?? null)
                || !hash_equals(hash('sha256', $sourceText), $evidence['originalDigest'])
                || !is_string($evidence['projectedDigest'] ?? null)
                || !hash_equals(hash('sha256', $projectionText), $evidence['projectedDigest'])
                || !is_string($evidence['followingOriginalDigest'] ?? null)
                || !hash_equals(
                    hash('sha256', $followingText),
                    $evidence['followingOriginalDigest']
                )) {
                return false;
            }

            return str_starts_with($itemVisible, $anchorVisible . $followingVisible);
        }

        $separator = preg_match('/^[,.;:!?\)\]\}]/u', ltrim($followingProjection)) === 1
            ? ''
            : ' ';

        return str_starts_with(
            $itemVisible,
            $anchorVisible . $separator . $followingVisible
        );
    }

    private static function sourceBindingHasExactWrappedHyphenProjection(
        string $sourceText,
        string $projectionText,
        string $suppressionKind
    ): bool {
        if (preg_match('/(?:\x{00AD}|[-\x{2010}\x{2011}])$/u', $sourceText, $match) !== 1) {
            return false;
        }
        $separator = $match[0];
        $prefix = substr($sourceText, 0, -strlen($separator));

        return match ($suppressionKind) {
            'discretionary-hard-hyphen' => $separator !== "\u{00AD}"
                && hash_equals($prefix, $projectionText),
            'discretionary-soft-hyphen' => $separator === "\u{00AD}"
                && hash_equals($prefix, $projectionText),
            'semantic-soft-hyphen' => $separator === "\u{00AD}"
                && hash_equals($prefix . '-', $projectionText),
            default => false,
        };
    }

    protected static function sourceBindingNodeSignificantText(AstNode $node): string
    {
        if ($node->type === 'text' || $node->type === 'code_block') {
            return self::significantText((string) $node->attr('text', ''));
        }
        $text = '';
        foreach ($node->children() as $child) {
            $text .= self::sourceBindingNodeSignificantText($child);
        }

        return $text;
    }

    protected static function sourceBindingNodeVisibleText(AstNode $node): string
    {
        if ($node->type === 'text' || $node->type === 'code_block') {
            return (string) $node->attr('text', '');
        }
        $text = '';
        foreach ($node->children() as $child) {
            $text .= self::sourceBindingNodeVisibleText($child);
        }

        return $text;
    }

    private static function significantText(string $chunk): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($chunk, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $chunk = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $chunk) ?? $chunk;
    }
}
