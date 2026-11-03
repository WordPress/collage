<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Preserve first-page title, credit, and summary regions before column flow.
 *
 * The boundary is inferred from source order and geometry: a top, larger-font
 * title must precede a compact numbered section heading. A short display line
 * separated from the credit band and followed by smaller body text identifies
 * the summary heading without depending on its language or spelling.
 */
final class PdfFrontMatterSemanticProcessor implements PdfSemanticRecordProcessor
{
    private int $recordCount = 0;

    public function name(): string
    {
        return 'front-matter';
    }

    public function recordCount(): int
    {
        return $this->recordCount;
    }

    public function process(array $records): array
    {
        $this->recordCount = 0;
        if ($records === []) {
            return [];
        }

        $pages = [];
        foreach ($records as $index => $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if ($this->hasGeometryAndSourceOrder($layout)) {
                $pages[(int) $layout['page']][] = $index;
            }
        }
        if ($pages === []) {
            return $records;
        }
        ksort($pages, SORT_NUMERIC);
        $pageIndexes = $pages[array_key_first($pages)];

        $alreadyClassified = array_values(array_filter(
            $pageIndexes,
            static fn (int $index): bool => ($records[$index]['layout']['sourcePdfFrontMatter'] ?? false) === true
        ));
        $sectionOrder = null;
        if ($alreadyClassified === []) {
            $classification = $this->classify($records, $pageIndexes);
            if ($classification === null) {
                return $records;
            }
            $sectionOrder = $classification['sectionOrder'];
            $firstSummaryBody = true;
            foreach ($classification['roles'] as $index => $role) {
                $layout = $records[$index]['layout'];
                $layout['sourcePdfFrontMatter'] = true;
                $layout['sourcePdfProtectedSemanticContent'] = true;
                $layout['sourcePdfFrontMatterRole'] = $role;
                if ($role === 'title' || $role === 'summary-heading') {
                    $layout['sourcePdfDisplayHeading'] = true;
                }
                if ($role === 'summary-body' && $firstSummaryBody) {
                    $layout['forceBlockBreakBefore'] = true;
                    $firstSummaryBody = false;
                }
                $records[$index]['layout'] = $layout;
                $this->recordCount++;
            }
        } else {
            $this->recordCount = count($alreadyClassified);
            foreach ($pageIndexes as $index) {
                if (($records[$index]['layout']['sourcePdfFrontMatter'] ?? false) !== true) {
                    continue;
                }
                $sectionOrder = max(
                    $sectionOrder ?? 0,
                    (int) $records[$index]['layout']['sourceOrderEnd'] + 1
                );
            }
            foreach ($pageIndexes as $index) {
                $layout = $records[$index]['layout'];
                if (($layout['sourcePdfFrontMatter'] ?? false) === true) {
                    continue;
                }
                $order = (int) ($layout['sourceOrderStart'] ?? PHP_INT_MAX);
                $text = trim((string) ($records[$index]['text'] ?? ''));
                if ($order >= ($sectionOrder ?? PHP_INT_MAX) && $this->looksLikeSectionHeading($text)) {
                    $sectionOrder = $order;
                    break;
                }
            }
        }

        return $this->moveClassifiedRecordsBeforeSection($records, $sectionOrder);
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @param list<int> $pageIndexes
     * @return array{sectionOrder:int,roles:array<int,string>}|null
     */
    private function classify(array $records, array $pageIndexes): ?array
    {
        usort($pageIndexes, fn (int $left, int $right): int => $this->sourceOrder($records[$left]) <=> $this->sourceOrder($records[$right]));
        foreach ($pageIndexes as $headingIndex) {
            $heading = $records[$headingIndex];
            $headingLayout = $heading['layout'];
            $headingText = trim($heading['text']);
            if (!$this->looksLikeSectionHeading($headingText)) {
                continue;
            }
            $headingOrder = $this->sourceOrder($heading);
            $headingFont = max(1.0, (float) $headingLayout['fontSize']);
            $headingY = (float) $headingLayout['y1'];

            $titleIndex = null;
            foreach ($pageIndexes as $candidateIndex) {
                $candidate = $records[$candidateIndex];
                $layout = $candidate['layout'];
                $text = trim($candidate['text']);
                if ($this->sourceOrder($candidate) >= $headingOrder
                    || (float) $layout['y1'] <= $headingY + $headingFont * 3.0
                    || (float) $layout['fontSize'] < $headingFont * 1.12
                    || $this->textLength($text) < 15
                    || $this->textLength($text) > 180
                    || $this->wordCount($text) < 3) {
                    continue;
                }
                if ($titleIndex === null
                    || (float) $layout['y1'] > (float) $records[$titleIndex]['layout']['y1']) {
                    $titleIndex = $candidateIndex;
                }
            }
            if ($titleIndex === null) {
                continue;
            }

            $titleContinuationIndexes = $this->titleContinuationIndexes(
                $records,
                $pageIndexes,
                $titleIndex,
                $headingIndex
            );

            $titleOrder = $this->sourceOrder($records[$titleIndex]);
            $frontIndexes = array_values(array_filter(
                $pageIndexes,
                fn (int $index): bool => $this->sourceOrder($records[$index]) >= $titleOrder
                    && $this->sourceOrder($records[$index]) < $headingOrder
            ));
            if (count($frontIndexes) < 3) {
                continue;
            }

            $summaryHeadingIndex = $this->summaryHeadingIndex($records, $frontIndexes, $titleIndex);
            $summaryOrder = $summaryHeadingIndex === null
                ? null
                : $this->sourceOrder($records[$summaryHeadingIndex]);
            $roles = [];
            foreach ($frontIndexes as $index) {
                $order = $this->sourceOrder($records[$index]);
                if ($index === $titleIndex || in_array($index, $titleContinuationIndexes, true)) {
                    $roles[$index] = 'title';
                } elseif ($index === $summaryHeadingIndex) {
                    $roles[$index] = 'summary-heading';
                } elseif ($summaryOrder !== null && $order > $summaryOrder) {
                    $roles[$index] = 'summary-body';
                } else {
                    $roles[$index] = 'credits';
                }
            }
            foreach ($titleContinuationIndexes as $index) {
                $roles[$index] = 'title';
            }

            return ['sectionOrder' => $headingOrder, 'roles' => $roles];
        }

        return null;
    }

    /**
     * A centered display title can be painted as several independent source
     * records. Recover only a tight same-font vertical stack so a later
     * stream-ordered body label cannot be mistaken for a title continuation.
     *
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @param list<int> $pageIndexes
     * @return list<int>
     */
    private function titleContinuationIndexes(
        array $records,
        array $pageIndexes,
        int $titleIndex,
        int $sectionHeadingIndex
    ): array {
        $title = $records[$titleIndex];
        $titleLayout = $title['layout'];
        $font = max(1.0, (float) $titleLayout['fontSize']);
        $centerX = ((float) $titleLayout['x1'] + (float) $titleLayout['x2']) / 2.0;
        $previousY = (float) $titleLayout['y1'];
        $continuations = [];

        while (true) {
            $best = null;
            $bestGap = INF;
            foreach ($pageIndexes as $index) {
                if ($index === $titleIndex
                    || $index === $sectionHeadingIndex
                    || in_array($index, $continuations, true)) {
                    continue;
                }
                $candidate = $records[$index];
                $layout = $candidate['layout'];
                $text = trim($candidate['text']);
                $candidateFont = max(1.0, (float) $layout['fontSize']);
                $candidateCenterX = ((float) $layout['x1'] + (float) $layout['x2']) / 2.0;
                $gap = $previousY - (float) $layout['y1'];
                if ($text === ''
                    || $this->wordCount($text) > 10
                    || $this->textLength($text) > 96
                    || $gap <= 0.0
                    || $gap > $font * 1.65
                    || abs($candidateFont - $font) > max(0.75, $font * 0.30)
                    || abs($candidateCenterX - $centerX) > $font * 1.5) {
                    continue;
                }
                if ($gap < $bestGap) {
                    $best = $index;
                    $bestGap = $gap;
                }
            }
            if ($best === null) {
                break;
            }
            $continuations[] = $best;
            $previousY = (float) $records[$best]['layout']['y1'];
        }

        return $continuations;
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @param list<int> $frontIndexes
     */
    private function summaryHeadingIndex(array $records, array $frontIndexes, int $titleIndex): ?int
    {
        usort($frontIndexes, fn (int $left, int $right): int => $this->sourceOrder($records[$left]) <=> $this->sourceOrder($records[$right]));
        $candidate = null;
        foreach ($frontIndexes as $offset => $index) {
            if ($index === $titleIndex || !isset($frontIndexes[$offset - 1], $frontIndexes[$offset + 1])) {
                continue;
            }
            $previous = $records[$frontIndexes[$offset - 1]];
            $current = $records[$index];
            $next = $records[$frontIndexes[$offset + 1]];
            $text = trim($current['text']);
            $font = max(1.0, (float) $current['layout']['fontSize']);
            $gapBefore = (float) $previous['layout']['y1'] - (float) $current['layout']['y1'];
            $gapAfter = (float) $current['layout']['y1'] - (float) $next['layout']['y1'];
            if ($this->wordCount($text) > 4
                || $this->textLength($text) > 48
                || (float) $current['layout']['fontSize'] < (float) $next['layout']['fontSize'] * 1.08
                || $gapBefore < max(20.0, $font * 1.8)
                || $gapAfter <= 0.0
                || $gapAfter > max(36.0, $font * 3.0)) {
                continue;
            }
            $candidate = $index;
        }

        return $candidate;
    }

    /** @param list<array{text:string,layout:array<string,mixed>|null}> $records */
    private function moveClassifiedRecordsBeforeSection(array $records, ?int $sectionOrder): array
    {
        if ($sectionOrder === null) {
            return $records;
        }
        $front = [];
        $remaining = [];
        foreach ($records as $record) {
            if (($record['layout']['sourcePdfFrontMatter'] ?? false) === true) {
                $front[] = $record;
            } else {
                $remaining[] = $record;
            }
        }
        if ($front === []) {
            return $records;
        }
        usort($front, function (array $left, array $right): int {
            $leftRole = $left['layout']['sourcePdfFrontMatterRole'] ?? null;
            $rightRole = $right['layout']['sourcePdfFrontMatterRole'] ?? null;
            $roleRank = [
                'title' => 0,
                'credits' => 1,
                'summary-heading' => 2,
                'summary-body' => 3,
            ];
            $rankComparison = ($roleRank[$leftRole] ?? 4) <=> ($roleRank[$rightRole] ?? 4);
            if ($rankComparison !== 0) {
                return $rankComparison;
            }
            if ($leftRole === 'title') {
                return (float) $right['layout']['y1'] <=> (float) $left['layout']['y1'];
            }

            return $this->sourceOrder($left) <=> $this->sourceOrder($right);
        });

        $insertAt = count($remaining);
        $page = (int) $front[0]['layout']['page'];
        foreach ($remaining as $index => $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if ($this->hasGeometryAndSourceOrder($layout)
                && (int) $layout['page'] === $page
                && (int) $layout['sourceOrderStart'] >= $sectionOrder) {
                $insertAt = $index;
                break;
            }
        }

        return array_merge(
            array_slice($remaining, 0, $insertAt),
            $front,
            array_slice($remaining, $insertAt)
        );
    }

    private function looksLikeSectionHeading(string $text): bool
    {
        return $this->wordCount($text) <= 12
            && preg_match('/^\s*(?:\d+(?:\.\d+)*\s+){1,2}\p{Lu}[\p{L}\p{N}]/u', $text) === 1;
    }

    /** @param array{text:string,layout:array<string,mixed>|null} $record */
    private function sourceOrder(array $record): int
    {
        return (int) ($record['layout']['sourceOrderStart'] ?? PHP_INT_MAX);
    }

    /** @param array<string,mixed>|null $layout */
    private function hasGeometryAndSourceOrder(?array $layout): bool
    {
        if (!is_array($layout) || !isset($layout['sourceOrderStart'], $layout['sourceOrderEnd'])) {
            return false;
        }
        foreach (['page', 'x1', 'y1', 'x2', 'y2', 'fontSize'] as $key) {
            if (!is_int($layout[$key] ?? null) && !is_float($layout[$key] ?? null)) {
                return false;
            }
        }

        return (int) $layout['page'] > 0;
    }

    private function wordCount(string $text): int
    {
        $count = preg_match_all('/[\p{L}\p{N}]+/u', $text);

        return $count === false ? 0 : $count;
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
