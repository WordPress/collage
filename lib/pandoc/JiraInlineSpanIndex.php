<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Stateful, bounded-memory cursor cache for Jira inline delimiters.
 *
 * The inline grammar deliberately uses the first following delimiter rather
 * than balanced pairs for several constructs. Precomputing matched spans
 * changes that grammar and storing every delimiter makes malformed runs a
 * memory sink. Instead, each delimiter family remembers the result of its
 * most recent forward search. As the reader advances through the source,
 * every byte is searched at most once per family; an unsuccessful search is
 * a sentinel for all later offsets.
 */
final class JiraInlineSpanIndex
{
    /** @var array<string, array{from:int,result:?int}> */
    private array $rawSearches = [];

    /** @var array<string, array{from:int,result:?int}> */
    private array $unescapedSearches = [];

    private int $attachmentCaretScannedThrough = 0;

    private ?int $lastAttachmentCaret = null;

    private bool $utf8SourceScanned = false;

    private ?int $rightmostInvalidUtf8Offset = null;

    private int $utf8RangeScannedThrough = 0;

    private ?int $utf8RangeLastInvalidOffset = null;

    public function __construct(private readonly string $text)
    {
    }

    public function bracketEnd(int $offset): ?int
    {
        return $this->nextRaw('bracket-close', ']', $offset + 1);
    }

    public function codeEnd(int $offset): ?int
    {
        return $this->nextRaw('code-close', '}}', $offset + 2);
    }

    /**
     * @return array{headerEnd:int,end:int}|null
     */
    public function colorSpan(int $offset): ?array
    {
        $headerEnd = $this->nextRaw('color-header-end', '}', $offset + strlen('{color:'));
        if ($headerEnd === null) {
            return null;
        }

        $end = $this->nextRaw('color-close', '{color}', $headerEnd + 1);
        if ($end === null) {
            return null;
        }

        return ['headerEnd' => $headerEnd, 'end' => $end];
    }

    public function anchorEnd(int $offset): ?int
    {
        return $this->nextRaw('anchor-end', '}', $offset + strlen('{anchor:'));
    }

    public function simplePairEnd(string $token, int $offset): ?int
    {
        return $this->nextRaw('simple-pair:' . $token, $token, $offset + strlen($token));
    }

    public function delimitedStyleEnd(string $marker, int $offset): ?int
    {
        return $this->nextUnescaped('style:' . $marker, $marker, $offset + 2);
    }

    public function linkPipe(int $from, int $ordinal): ?int
    {
        return $this->nextRaw('link-pipe-' . $ordinal, '|', $from);
    }

    public function attachmentCaretBefore(int $before): ?int
    {
        $before = min(max(0, $before), strlen($this->text));
        // Mirrors the greedy labelled-attachment expression
        // /^(.+)\^([^|]+)$/u: find the rightmost caret with a
        // nonempty suffix that contains no pipe. The parser asks in source
        // order, so carry the most recent caret since a pipe forward and
        // inspect every source byte at most once. Excluding before - 1 keeps
        // a terminal caret from hiding an earlier valid delimiter.
        $limit = $before - 2;
        if ($limit < $this->attachmentCaretScannedThrough - 1) {
            return $this->attachmentCaretBeforeOutOfOrder($limit);
        }

        for ($offset = $this->attachmentCaretScannedThrough; $offset <= $limit; ++$offset) {
            $char = $this->text[$offset] ?? '';
            if ($char === '|') {
                $this->lastAttachmentCaret = null;
                continue;
            }
            if ($char === '^') {
                $this->lastAttachmentCaret = $offset;
            }
        }
        $this->attachmentCaretScannedThrough = max($this->attachmentCaretScannedThrough, $limit + 1);

        return $this->lastAttachmentCaret;
    }

    private function attachmentCaretBeforeOutOfOrder(int $limit): ?int
    {
        $result = null;
        for ($offset = 0; $offset <= $limit; ++$offset) {
            $char = $this->text[$offset] ?? '';
            if ($char === '|') {
                $result = null;
            } elseif ($char === '^') {
                $result = $offset;
            }
        }

        return $result;
    }

    public function hasValidUtf8(): bool
    {
        $this->scanUtf8Source();

        return $this->rightmostInvalidUtf8Offset === null;
    }

    /**
     * The legacy anchored `/u` macro patterns validate from their current
     * offset through the rest of the subject. Keep that suffix behavior while
     * remembering only the final malformed byte, rather than matching every
     * macro prefix against the whole remaining source.
     */
    public function hasValidUtf8From(int $offset): bool
    {
        $this->scanUtf8Source();

        return $this->rightmostInvalidUtf8Offset === null || $this->rightmostInvalidUtf8Offset < $offset;
    }

    /**
     * The labelled-attachment expression receives only bracket content, so
     * it needs range validity rather than document or suffix validity. Link
     * candidates are queried in source order; retain a monotone byte cursor
     * and just the latest malformed byte for O(1) memory.
     */
    public function hasValidUtf8Range(int $start, int $end): bool
    {
        $length = strlen($this->text);
        $start = min(max(0, $start), $length);
        $end = min(max($start, $end), $length);
        if ($start >= $end || $this->hasValidUtf8()) {
            return true;
        }

        // JiraReader queries bracket candidates from left to right. Retain
        // exact behavior for a future out-of-order caller without retaining
        // a per-offset validity map.
        if ($end < $this->utf8RangeScannedThrough - 1) {
            return preg_match('//u', substr($this->text, $start, $end - $start)) === 1;
        }

        $this->scanUtf8RangeThrough($end);

        return $this->utf8RangeLastInvalidOffset === null || $this->utf8RangeLastInvalidOffset < $start;
    }

    private function scanUtf8Source(): void
    {
        if ($this->utf8SourceScanned) {
            return;
        }

        $this->utf8SourceScanned = true;
        $length = strlen($this->text);
        for ($offset = 0; $offset < $length;) {
            $sequenceLength = $this->validUtf8SequenceLengthAt($offset);
            if ($sequenceLength === 0) {
                $this->rightmostInvalidUtf8Offset = $offset;
                ++$offset;
                continue;
            }

            $offset += $sequenceLength;
        }
    }

    private function scanUtf8RangeThrough(int $limit): void
    {
        $limit = min($limit, strlen($this->text) - 1);
        while ($this->utf8RangeScannedThrough <= $limit) {
            $offset = $this->utf8RangeScannedThrough;
            $sequenceLength = $this->validUtf8SequenceLengthAt($offset);
            if ($sequenceLength === 0) {
                $this->utf8RangeLastInvalidOffset = $offset;
                ++$this->utf8RangeScannedThrough;
                continue;
            }

            $this->utf8RangeScannedThrough += $sequenceLength;
        }
    }

    private function validUtf8SequenceLengthAt(int $offset): int
    {
        $length = strlen($this->text);
        $first = ord($this->text[$offset] ?? "\0");
        if ($first <= 0x7F) {
            return 1;
        }

        $second = $offset + 1 < $length ? ord($this->text[$offset + 1]) : -1;
        $third = $offset + 2 < $length ? ord($this->text[$offset + 2]) : -1;
        $fourth = $offset + 3 < $length ? ord($this->text[$offset + 3]) : -1;
        $continuation = static fn (int $byte): bool => $byte >= 0x80 && $byte <= 0xBF;

        if ($first >= 0xC2 && $first <= 0xDF) {
            return $continuation($second) ? 2 : 0;
        }
        if ($first === 0xE0) {
            return $second >= 0xA0 && $second <= 0xBF && $continuation($third) ? 3 : 0;
        }
        if (($first >= 0xE1 && $first <= 0xEC) || ($first >= 0xEE && $first <= 0xEF)) {
            return $continuation($second) && $continuation($third) ? 3 : 0;
        }
        if ($first === 0xED) {
            return $second >= 0x80 && $second <= 0x9F && $continuation($third) ? 3 : 0;
        }
        if ($first === 0xF0) {
            return $second >= 0x90 && $second <= 0xBF && $continuation($third) && $continuation($fourth) ? 4 : 0;
        }
        if ($first >= 0xF1 && $first <= 0xF3) {
            return $continuation($second) && $continuation($third) && $continuation($fourth) ? 4 : 0;
        }
        if ($first === 0xF4) {
            return $second >= 0x80 && $second <= 0x8F && $continuation($third) && $continuation($fourth) ? 4 : 0;
        }

        return 0;
    }

    private function nextRaw(string $key, string $needle, int $from): ?int
    {
        $from = max(0, $from);
        $cached = $this->rawSearches[$key] ?? null;
        if ($cached !== null && $from >= $cached['from']) {
            if ($cached['result'] === null || $from <= $cached['result']) {
                return $cached['result'];
            }
        }

        $result = strpos($this->text, $needle, $from);
        $resolved = $result === false ? null : $result;
        $this->rawSearches[$key] = ['from' => $from, 'result' => $resolved];

        return $resolved;
    }

    private function nextUnescaped(string $key, string $needle, int $from): ?int
    {
        $from = max(0, $from);
        $cached = $this->unescapedSearches[$key] ?? null;
        if ($cached !== null && $from >= $cached['from']) {
            if ($cached['result'] === null || $from <= $cached['result']) {
                return $cached['result'];
            }
        }

        $result = null;
        $search = $from;
        $needleLength = strlen($needle);
        while (($candidate = strpos($this->text, $needle, $search)) !== false) {
            if (!$this->isEscaped($candidate)) {
                $result = $candidate;
                break;
            }
            $search = $candidate + $needleLength;
        }

        $this->unescapedSearches[$key] = ['from' => $from, 'result' => $result];

        return $result;
    }

    private function isEscaped(int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && ($this->text[$cursor] ?? '') === '\\'; --$cursor) {
            ++$slashes;
        }

        return $slashes % 2 === 1;
    }
}
