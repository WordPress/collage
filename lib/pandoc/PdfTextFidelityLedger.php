<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Account for text before PDF layout inference and after AST construction.
 *
 * PDF reading order is inherently ambiguous, so this ledger deliberately
 * reports three independent signals:
 *
 * - token coverage catches removed words and numbers;
 * - significant-character coverage survives spacing and word-boundary repair;
 * - token-adjacency coverage makes large reordering visible without claiming
 *   that source-stream order is always the correct reading order.
 *
 * This is an audit boundary, not another repair heuristic.  Later pipeline
 * stages can attach explicit suppression dispositions to source facts; until
 * then, anything absent from the emitted AST remains unresolved.
 */
final class PdfTextFidelityLedger
{
    private const SAMPLE_LIMIT = 32;
    private const ADJACENCY_DIGEST_BYTES = 32;
    private const ADJACENCY_CHUNK_BYTES = 32768;

    /**
     * @param list<array{text:string, page?:int, stream?:int}|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @return array<string, mixed>
     */
    public static function fromSourceLineItems(array $sourceLineItems, array $blocks): array
    {
        $sourceChunks = static function () use ($sourceLineItems): iterable {
            foreach ($sourceLineItems as $item) {
                $text = is_array($item) ? ($item['text'] ?? '') : $item;
                if (is_string($text) && $text !== '') {
                    yield $text;
                }
            }
        };

        return self::fromChunkStreams($sourceChunks(), self::textChunksFromNodes($blocks));
    }

    /**
     * Public for focused audit tools and fixtures which already have two text
     * projections and do not need to construct a PDF AST.
     *
     * @return array<string, mixed>
     */
    public static function fromText(string $sourceText, string $emittedText): array
    {
        return self::fromChunkStreams([$sourceText], [$emittedText]);
    }

    /**
     * The PDF reader already holds positioned facts and the constructed AST.
     * Building two more document-sized strings and two token lists here can
     * exhaust a normal 128 MiB WordPress worker at the very end of an import.
     * Accumulate the same ledger one source line / AST text node at a time.
     *
     * @param iterable<string> $sourceChunks
     * @param iterable<string> $emittedChunks
     * @return array<string, mixed>
     */
    private static function fromChunkStreams(iterable $sourceChunks, iterable $emittedChunks): array
    {
        $source = self::inventoryFromChunks($sourceChunks);
        $emitted = self::reconcileChunksAgainstSourceInventory($emittedChunks, $source);
        $unresolvedTokens = $source['tokenCounts'];
        $addedTokens = $emitted['addedTokenCounts'];
        $sourceTokenCount = $source['tokenCount'];
        $emittedTokenCount = $emitted['tokenCount'];
        $unresolvedTokenCount = array_sum($unresolvedTokens);
        $sourceAdjacencyCount = max(0, $sourceTokenCount - 1);
        $sourceAdjacencyDigestChunks = $source['adjacencyDigestChunks'];
        $emittedAdjacencyDigestChunks = $emitted['adjacencyDigestChunks'];
        unset($source['adjacencyDigestChunks'], $emitted['adjacencyDigestChunks']);
        $unresolvedAdjacencyCount = self::packedRecordLeftDifferenceCount(
            $sourceAdjacencyDigestChunks,
            $emittedAdjacencyDigestChunks,
            self::ADJACENCY_DIGEST_BYTES
        );
        unset($sourceAdjacencyDigestChunks, $emittedAdjacencyDigestChunks);
        $unresolvedTokenSample = self::sampleCounts($unresolvedTokens);
        $addedTokenSample = self::sampleCounts($addedTokens);
        $unresolvedCharacters = $source['characterCounts'];
        $addedCharacters = $emitted['addedCharacterCounts'];
        $sourceCharacterCount = $source['characterCount'];
        $emittedCharacterCount = $emitted['characterCount'];
        $unresolvedCharacterCount = array_sum($unresolvedCharacters);
        $sourceAccounted = $unresolvedTokenCount === 0 && $unresolvedCharacterCount === 0;
        $sourceTokenDigest = $source['tokenDigest'];
        $emittedTokenDigest = $emitted['tokenDigest'];
        $addedTokenCount = array_sum($addedTokens);
        $addedCharacterCount = array_sum($addedCharacters);
        $unresolvedCharacterSample = self::sampleCharacterCounts($unresolvedCharacters);
        $exactProjection = $sourceAccounted
            && $addedTokens === []
            && $addedCharacters === []
            && $unresolvedAdjacencyCount === 0;
        unset($source, $emitted);
        // Empty PHP hash tables retain their bucket allocation. None of the
        // complete residual maps is public, so release them after scalarizing
        // the counts/samples instead of carrying their peak capacity through
        // construction of the returned metadata graph.
        unset(
            $unresolvedTokens,
            $addedTokens,
            $unresolvedCharacters,
            $addedCharacters
        );

        return [
            'version' => 1,
            'sourceTokenCount' => $sourceTokenCount,
            'emittedTokenCount' => $emittedTokenCount,
            'accountedSourceTokenCount' => max(0, $sourceTokenCount - $unresolvedTokenCount),
            'unresolvedTokenCount' => $unresolvedTokenCount,
            'addedTokenCount' => $addedTokenCount,
            'tokenCoverage' => self::coverage($sourceTokenCount, $unresolvedTokenCount),
            'sourceSignificantCharacterCount' => $sourceCharacterCount,
            'emittedSignificantCharacterCount' => $emittedCharacterCount,
            'accountedSourceSignificantCharacterCount' => max(0, $sourceCharacterCount - $unresolvedCharacterCount),
            'unresolvedSignificantCharacterCount' => $unresolvedCharacterCount,
            'addedSignificantCharacterCount' => $addedCharacterCount,
            'significantCharacterCoverage' => self::coverage($sourceCharacterCount, $unresolvedCharacterCount),
            'sourceTokenAdjacencyCount' => $sourceAdjacencyCount,
            'accountedSourceTokenAdjacencyCount' => max(0, $sourceAdjacencyCount - $unresolvedAdjacencyCount),
            'unresolvedTokenAdjacencyCount' => $unresolvedAdjacencyCount,
            'tokenAdjacencyCoverage' => self::coverage($sourceAdjacencyCount, $unresolvedAdjacencyCount),
            'sourceAccounted' => $sourceAccounted,
            'exactProjection' => $exactProjection,
            'unresolvedTokenSample' => $unresolvedTokenSample,
            'addedTokenSample' => $addedTokenSample,
            'unresolvedCharacterSample' => $unresolvedCharacterSample,
            'sourceTokenDigest' => $sourceTokenDigest,
            'emittedTokenDigest' => $emittedTokenDigest,
        ];
    }

    /**
     * @param list<AstNode> $nodes
     * @return iterable<string>
     */
    private static function textChunksFromNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            yield from self::textChunksFromNode($node);
        }
    }

    /** @return iterable<string> */
    private static function textChunksFromNode(AstNode $node): iterable
    {
        if ($node->type === 'text') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }

        foreach ($node->children() as $child) {
            yield from self::textChunksFromNode($child);
        }
    }

    /**
     * @param iterable<string> $chunks
     * @return array{tokenCounts:array<string,int>,adjacencyDigestChunks:list<string>,tokenCount:int,tokenDigest:string,characterCounts:array<string,int>,characterCount:int}
     */
    private static function inventoryFromChunks(iterable $chunks): array
    {
        $tokenCounts = [];
        $adjacencyDigestChunks = [];
        $adjacencyDigestBuffer = '';
        $tokenCount = 0;
        $previousToken = null;
        $tokenHash = hash_init('sha256');
        $characterCounts = [];
        $characterCount = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            foreach (self::tokensFromNormalizedText($normalized) as $token) {
                $tokenCounts[$token] = ($tokenCounts[$token] ?? 0) + 1;
                if ($previousToken !== null) {
                    $adjacencyDigestBuffer .= hash(
                        'sha256',
                        $previousToken . "\0" . $token,
                        true
                    );
                    if (strlen($adjacencyDigestBuffer) >= self::ADJACENCY_CHUNK_BYTES) {
                        $adjacencyDigestChunks[] = $adjacencyDigestBuffer;
                        $adjacencyDigestBuffer = '';
                    }
                    hash_update($tokenHash, "\0");
                }
                hash_update($tokenHash, $token);
                $previousToken = $token;
                $tokenCount++;
            }
            $characters = self::significantCharacterInventory($normalized);
            $characterCount += $characters['total'];
            foreach ($characters['counts'] as $character => $count) {
                $characterCounts[$character] = ($characterCounts[$character] ?? 0) + $count;
            }
        }

        if ($adjacencyDigestBuffer !== '') {
            $adjacencyDigestChunks[] = $adjacencyDigestBuffer;
        }

        return [
            'tokenCounts' => $tokenCounts,
            'adjacencyDigestChunks' => $adjacencyDigestChunks,
            'tokenCount' => $tokenCount,
            'tokenDigest' => hash_final($tokenHash),
            'characterCounts' => $characterCounts,
            'characterCount' => $characterCount,
        ];
    }

    /**
     * Consume emitted counts directly from the source inventory. Retaining a
     * second complete token, adjacency, and character inventory until the
     * final differences are calculated doubles the end-of-import peak; dense
     * documents make nearly every adjacency a distinct PHP array key. The
     * residual source maps are exactly the prior positive differences, while
     * only genuinely added token/character counts need separate storage.
     *
     * @param iterable<string> $chunks
     * @param array{tokenCounts:array<string,int>,adjacencyDigestChunks:list<string>,tokenCount:int,tokenDigest:string,characterCounts:array<string,int>,characterCount:int} $source
     * @return array{addedTokenCounts:array<string,int>,adjacencyDigestChunks:list<string>,tokenCount:int,tokenDigest:string,addedCharacterCounts:array<string,int>,characterCount:int}
     */
    private static function reconcileChunksAgainstSourceInventory(iterable $chunks, array &$source): array
    {
        $addedTokenCounts = [];
        $tokenCount = 0;
        $previousToken = null;
        $tokenHash = hash_init('sha256');
        $adjacencyDigestChunks = [];
        $adjacencyDigestBuffer = '';
        $addedCharacterCounts = [];
        $characterCount = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            foreach (self::tokensFromNormalizedText($normalized) as $token) {
                $availableTokenCount = $source['tokenCounts'][$token] ?? 0;
                if ($availableTokenCount > 1) {
                    $source['tokenCounts'][$token] = $availableTokenCount - 1;
                } elseif ($availableTokenCount === 1) {
                    unset($source['tokenCounts'][$token]);
                } else {
                    $addedTokenCounts[$token] = ($addedTokenCounts[$token] ?? 0) + 1;
                }
                if ($previousToken !== null) {
                    $adjacencyDigestBuffer .= hash(
                        'sha256',
                        $previousToken . "\0" . $token,
                        true
                    );
                    if (strlen($adjacencyDigestBuffer) >= self::ADJACENCY_CHUNK_BYTES) {
                        $adjacencyDigestChunks[] = $adjacencyDigestBuffer;
                        $adjacencyDigestBuffer = '';
                    }
                    hash_update($tokenHash, "\0");
                }
                hash_update($tokenHash, $token);
                $previousToken = $token;
                $tokenCount++;
            }
            $characters = self::significantCharacterInventory($normalized);
            $characterCount += $characters['total'];
            foreach ($characters['counts'] as $character => $count) {
                $availableCharacterCount = $source['characterCounts'][$character] ?? 0;
                $consumed = min($availableCharacterCount, $count);
                if ($consumed === $availableCharacterCount) {
                    unset($source['characterCounts'][$character]);
                } else {
                    $source['characterCounts'][$character] = $availableCharacterCount - $consumed;
                }
                $added = $count - $consumed;
                if ($added > 0) {
                    $addedCharacterCounts[$character] = ($addedCharacterCounts[$character] ?? 0) + $added;
                }
            }
        }

        if ($adjacencyDigestBuffer !== '') {
            $adjacencyDigestChunks[] = $adjacencyDigestBuffer;
        }

        return [
            'addedTokenCounts' => $addedTokenCounts,
            'adjacencyDigestChunks' => $adjacencyDigestChunks,
            'tokenCount' => $tokenCount,
            'tokenDigest' => hash_final($tokenHash),
            'addedCharacterCounts' => $addedCharacterCounts,
            'characterCount' => $characterCount,
        ];
    }

    /**
     * Count the multiset records present on the left after consuming equal
     * records from the right. Fixed-width digests avoid one PHP hash bucket
     * and one variable-length key allocation per token adjacency.
     */
    private static function packedRecordLeftDifferenceCount(
        array &$left,
        array &$right,
        int $width
    ): int {
        if ($width < 1) {
            throw new \LogicException('Packed fidelity records had an invalid width.');
        }
        foreach ([$left, $right] as $chunks) {
            foreach ($chunks as $chunk) {
                if (!is_string($chunk) || strlen($chunk) % $width !== 0) {
                    throw new \LogicException('Packed fidelity records had an invalid width.');
                }
            }
        }
        self::sortFixedWidthRecordChunks($left, $width);
        self::sortFixedWidthRecordChunks($right, $width);
        $leftRecords = self::fixedWidthRecords($left, $width);
        $rightRecords = self::fixedWidthRecords($right, $width);
        $leftRecords->rewind();
        $rightRecords->rewind();
        $unresolved = 0;
        while ($leftRecords->valid() && $rightRecords->valid()) {
            $leftRecord = $leftRecords->current();
            $rightRecord = $rightRecords->current();
            $comparison = strcmp($leftRecord, $rightRecord);
            if ($comparison === 0) {
                $leftRecords->next();
                $rightRecords->next();
            } elseif ($comparison < 0) {
                $unresolved++;
                $leftRecords->next();
            } else {
                $rightRecords->next();
            }
        }
        while ($leftRecords->valid()) {
            $unresolved++;
            $leftRecords->next();
        }

        return $unresolved;
    }

    /**
     * @param list<string> $chunks
     */
    private static function sortFixedWidthRecordChunks(array &$chunks, int $width): void
    {
        foreach ($chunks as &$chunk) {
            $records = str_split($chunk, $width);
            sort($records, SORT_STRING);
            $chunk = implode('', $records);
            unset($records);
        }
        unset($chunk);
    }

    /** @param list<string> $chunks @return \Generator<int,string> */
    private static function fixedWidthRecords(array &$chunks, int $width): \Generator
    {
        /** @var list<array{record:string,chunk:int,offset:int}> $heap */
        $heap = [];
        foreach ($chunks as $chunkIndex => $chunk) {
            if ($chunk === '') {
                continue;
            }
            self::pushFixedWidthRecord($heap, [
                'record' => substr($chunk, 0, $width),
                'chunk' => $chunkIndex,
                'offset' => 0,
            ]);
        }
        while ($heap !== []) {
            $entry = self::popFixedWidthRecord($heap);
            yield $entry['record'];
            $nextOffset = $entry['offset'] + $width;
            $chunk = $chunks[$entry['chunk']];
            if ($nextOffset < strlen($chunk)) {
                self::pushFixedWidthRecord($heap, [
                    'record' => substr($chunk, $nextOffset, $width),
                    'chunk' => $entry['chunk'],
                    'offset' => $nextOffset,
                ]);
            }
        }
    }

    /**
     * @param list<array{record:string,chunk:int,offset:int}> $heap
     * @param array{record:string,chunk:int,offset:int} $entry
     */
    private static function pushFixedWidthRecord(array &$heap, array $entry): void
    {
        $heap[] = $entry;
        $index = count($heap) - 1;
        while ($index > 0) {
            $parent = intdiv($index - 1, 2);
            if (!self::fixedWidthRecordPrecedes($heap[$index], $heap[$parent])) {
                break;
            }
            [$heap[$parent], $heap[$index]] = [$heap[$index], $heap[$parent]];
            $index = $parent;
        }
    }

    /**
     * @param list<array{record:string,chunk:int,offset:int}> $heap
     * @return array{record:string,chunk:int,offset:int}
     */
    private static function popFixedWidthRecord(array &$heap): array
    {
        $first = $heap[0];
        $last = array_pop($heap);
        if ($heap === []) {
            return $first;
        }
        $heap[0] = $last;
        $index = 0;
        $count = count($heap);
        while (true) {
            $left = $index * 2 + 1;
            if ($left >= $count) {
                break;
            }
            $right = $left + 1;
            $next = $right < $count
                && self::fixedWidthRecordPrecedes($heap[$right], $heap[$left])
                ? $right
                : $left;
            if (!self::fixedWidthRecordPrecedes($heap[$next], $heap[$index])) {
                break;
            }
            [$heap[$index], $heap[$next]] = [$heap[$next], $heap[$index]];
            $index = $next;
        }

        return $first;
    }

    /**
     * @param array{record:string,chunk:int,offset:int} $left
     * @param array{record:string,chunk:int,offset:int} $right
     */
    private static function fixedWidthRecordPrecedes(array $left, array $right): bool
    {
        $comparison = strcmp($left['record'], $right['record']);

        return $comparison < 0
            || ($comparison === 0 && $left['chunk'] < $right['chunk']);
    }

    /** @return iterable<string> */
    private static function tokensFromNormalizedText(string $text): iterable
    {
        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            $matched = preg_match(
                "/[\p{L}\p{M}\p{N}]+(?:['\x{2019}][\p{L}\p{M}\p{N}]+)*/u",
                $text,
                $match,
                PREG_OFFSET_CAPTURE,
                $offset
            );
            if ($matched !== 1) {
                return;
            }
            $token = is_string($match[0][0] ?? null) ? $match[0][0] : '';
            $tokenOffset = is_int($match[0][1] ?? null) ? $match[0][1] : $offset;
            if ($token === '') {
                return;
            }
            yield $token;
            $offset = $tokenOffset + strlen($token);
        }
    }

    /**
     * Count characters without materializing one PHP string zval per glyph.
     * That distinction matters while a dense PDF AST and diagnostics are
     * already resident under a conventional 128 MB WordPress memory limit.
     *
     * @return array{counts:array<string,int>,total:int}
     */
    private static function significantCharacterInventory(string $text): array
    {
        $counts = [];
        $total = 0;
        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            $matched = preg_match('/[^\s\p{Cc}\p{Cf}]/u', $text, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($matched !== 1) {
                break;
            }
            $character = (string) $match[0][0];
            $byteOffset = (int) $match[0][1];
            $counts[$character] = ($counts[$character] ?? 0) + 1;
            $total++;
            $offset = $byteOffset + strlen($character);
        }

        return compact('counts', 'total');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{2060}"], '', $text);
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private static function coverage(int $sourceCount, int $unresolvedCount): float
    {
        if ($sourceCount === 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($sourceCount - $unresolvedCount) / $sourceCount));
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{text:string,count:int}>
     */
    private static function sampleCounts(array $counts): array
    {
        $records = [];
        foreach ($counts as $text => $count) {
            $records[] = ['text' => (string) $text, 'count' => $count];
        }
        usort($records, static fn (array $left, array $right): int =>
            ($right['count'] <=> $left['count']) ?: strcmp($left['text'], $right['text'])
        );

        return array_slice($records, 0, self::SAMPLE_LIMIT);
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{character:string,codePoint:string,count:int}>
     */
    private static function sampleCharacterCounts(array $counts): array
    {
        $records = [];
        foreach (self::sampleCounts($counts) as $record) {
            $records[] = [
                'character' => $record['text'],
                'codePoint' => self::unicodeCodePoint($record['text']),
                'count' => $record['count'],
            ];
        }

        return $records;
    }

    private static function unicodeCodePoint(string $character): string
    {
        if (function_exists('mb_ord')) {
            return sprintf('U+%04X', mb_ord($character, 'UTF-8'));
        }

        $bytes = array_values(unpack('C*', $character) ?: []);
        $first = $bytes[0] ?? 0;
        $codePoint = match (true) {
            $first < 0x80 => $first,
            $first < 0xE0 => (($first & 0x1F) << 6) | (($bytes[1] ?? 0) & 0x3F),
            $first < 0xF0 => (($first & 0x0F) << 12) | ((($bytes[1] ?? 0) & 0x3F) << 6) | (($bytes[2] ?? 0) & 0x3F),
            default => (($first & 0x07) << 18) | ((($bytes[1] ?? 0) & 0x3F) << 12)
                | ((($bytes[2] ?? 0) & 0x3F) << 6) | (($bytes[3] ?? 0) & 0x3F),
        };

        return sprintf('U+%04X', $codePoint);
    }
}
