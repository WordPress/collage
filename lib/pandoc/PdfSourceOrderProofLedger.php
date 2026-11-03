<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Validate and audit explicit PDF source-order proofs.
 *
 * This code is compiled at the dense-table spill trough so the final source
 * ledger remains small enough to load after source ordering without growing
 * PHP's allocator arena.
 */
final class PdfSourceOrderProofLedger
{
    private const ORDER_MATCH_CANDIDATE_LIMIT = 256;

    /**
     * An optional exact mapping is deliberately part of the disposition API
     * even though PdfReader currently supplies only page/region geometry.
     * Once emitted nodes retain source IDs, the reader can name the exact
     * occurrence set and expected emitted projection here. Until then the
     * fallback proof below is explicitly only region-bounded.
     *
     * @return array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string,sourcePages?:list<int>,emittedSourceOccurrenceIds?:list<string>,emittedSourceRanges?:list<array{sourceOccurrenceId:string,sourceStart:int,sourceEnd:int}>}|null
     */
    public static function normalizedExplicitOrderProof(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order proof.');
        }
        $scopeId = is_string($value['scopeId'] ?? null) ? trim($value['scopeId']) : '';
        $projection = is_string($value['emittedTextProjection'] ?? null)
            ? $value['emittedTextProjection']
            : null;
        $sourceIds = [];
        foreach (is_array($value['sourceOccurrenceIds'] ?? null) ? $value['sourceOccurrenceIds'] : [] as $sourceId) {
            if (!is_string($sourceId) || $sourceId === '' || isset($sourceIds[$sourceId])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order-proof source set.');
            }
            $sourceIds[$sourceId] = true;
        }
        if ($scopeId === '' || $projection === null || $sourceIds === [] || !isset($sourceIds[$id])) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an incomplete order proof.');
        }

        $sourcePages = null;
        if (array_key_exists('sourcePages', $value)) {
            $sourcePages = [];
            foreach (is_array($value['sourcePages'] ?? null) ? $value['sourcePages'] : [] as $page) {
                if (!is_int($page) || $page < 1 || isset($sourcePages[$page])) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid cross-page order-proof page set.'
                    );
                }
                $sourcePages[$page] = true;
            }
            $declaredPages = array_keys($sourcePages);
            $sortedPages = $declaredPages;
            sort($sortedPages, SORT_NUMERIC);
            if (count($declaredPages) < 2 || $declaredPages !== $sortedPages) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has a non-canonical cross-page order-proof page set.'
                );
            }
        }

        $emittedSourceIds = null;
        if (array_key_exists('emittedSourceOccurrenceIds', $value)) {
            $emittedSourceIds = [];
            foreach (is_array($value['emittedSourceOccurrenceIds'] ?? null)
                ? $value['emittedSourceOccurrenceIds']
                : [] as $sourceId) {
                if (!is_string($sourceId) || $sourceId === '' || isset($emittedSourceIds[$sourceId])) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source set.'
                    );
                }
                $emittedSourceIds[$sourceId] = true;
            }
            $declared = array_keys($sourceIds);
            $emitted = array_keys($emittedSourceIds);
            $declaredSorted = $declared;
            $emittedSorted = $emitted;
            sort($declaredSorted, SORT_STRING);
            sort($emittedSorted, SORT_STRING);
            if ($emitted === [] || $emittedSorted !== $declaredSorted) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has a non-conserving emitted order-proof source set.'
                );
            }
        }

        $emittedSourceRanges = null;
        if (array_key_exists('emittedSourceRanges', $value)) {
            if (is_array($emittedSourceIds)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has competing emitted order-proof mappings.'
                );
            }
            $emittedSourceRanges = [];
            foreach (is_array($value['emittedSourceRanges'] ?? null)
                ? $value['emittedSourceRanges']
                : [] as $range) {
                $sourceId = is_array($range) && is_string($range['sourceOccurrenceId'] ?? null)
                    ? $range['sourceOccurrenceId']
                    : '';
                $sourceStart = is_array($range) && is_int($range['sourceStart'] ?? null)
                    ? $range['sourceStart']
                    : null;
                $sourceEnd = is_array($range) && is_int($range['sourceEnd'] ?? null)
                    ? $range['sourceEnd']
                    : null;
                if ($sourceId === ''
                    || !isset($sourceIds[$sourceId])
                    || $sourceStart === null
                    || $sourceEnd === null
                    || $sourceStart < 0
                    || $sourceEnd <= $sourceStart) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source range.'
                    );
                }
                $emittedSourceRanges[] = [
                    'sourceOccurrenceId' => $sourceId,
                    'sourceStart' => $sourceStart,
                    'sourceEnd' => $sourceEnd,
                ];
            }
            if ($emittedSourceRanges === []) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has an empty emitted order-proof source range set.'
                );
            }
        }

        $proof = [
            'scopeId' => $scopeId,
            'sourceOccurrenceIds' => array_keys($sourceIds),
            'emittedTextProjection' => $projection,
        ];
        if (is_array($sourcePages)) {
            $proof['sourcePages'] = array_keys($sourcePages);
        }
        if (is_array($emittedSourceIds)) {
            $proof['emittedSourceOccurrenceIds'] = array_keys($emittedSourceIds);
        }
        if (is_array($emittedSourceRanges)) {
            $proof['emittedSourceRanges'] = $emittedSourceRanges;
        }

        return $proof;
    }

    /** @param array<string,mixed> $explicitDispositions */
    public static function hasRequestedOrderChange(array $explicitDispositions): bool
    {
        foreach ($explicitDispositions as $disposition) {
            if (is_array($disposition) && ($disposition['allowOrderChange'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{disposition:string,reason:string,evidence:array<string,mixed>,textProjection:?string,allowOrderChange:bool,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}} $explicit
     * @return array{key:string,mode:string,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|null
     */
    public static function localOrderChangeScope(array $explicit, string $id, int $page): ?array
    {
        $evidence = $explicit['evidence'];
        $hypothesis = is_string($evidence['hypothesis'] ?? null) ? trim($evidence['hypothesis']) : '';
        $bounds = self::normalizedBounds($evidence['bounds'] ?? null);
        $sourceBounds = self::normalizedBounds($evidence['sourceBounds'] ?? null);
        if ($hypothesis === ''
            || $bounds === null
            || $sourceBounds === null
            || !self::boundsIntersect($bounds, $sourceBounds)) {
            return null;
        }
        $featureDigest = is_string($evidence['featureDigest'] ?? null)
            ? $evidence['featureDigest']
            : '';
        $scope = [
            'page' => $page,
            'hypothesis' => $hypothesis,
            'bounds' => $bounds,
            'featureDigest' => $featureDigest,
        ];
        $scopeJson = json_encode($scope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $scopeKey = hash('sha256', is_string($scopeJson) ? $scopeJson : $id);
        $orderProof = $explicit['orderProof'];
        if ($orderProof !== null) {
            // The exact proof, not the local detector that first authorized a
            // contributor, defines one mapped reorder scope. A page proof can
            // legitimately cover full-width boundary prose and a nested
            // independent-column region, whose hypotheses/bounds differ.
            // Retain the geometry checks above as the authorization gate, but
            // group only identical proofs on the same physical page. Including
            // the page prevents a reused/malformed proof from coalescing two
            // unrelated page-local scopes.
            $proofKey = is_string($explicit['orderProofKey'] ?? null)
                ? $explicit['orderProofKey']
                : '';
            if ($proofKey === '') {
                $proofJson = json_encode(
                    $orderProof,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                $proofKey = hash(
                    'sha256',
                    is_string($proofJson) ? $proofJson : serialize($orderProof)
                );
            }
            $sourcePages = is_array($orderProof['sourcePages'] ?? null)
                ? array_values($orderProof['sourcePages'])
                : [];
            if ($sourcePages !== [] && !in_array($page, $sourcePages, true)) {
                return null;
            }
            // Ordinary exact scopes remain page-local. A producer may name a
            // canonical set of two or more pages only when one contiguous
            // source-occurrence permutation genuinely crosses that boundary;
            // the complete page set is revalidated when the segment closes.
            $scopeDomain = $sourcePages === []
                ? (string) $page
                : 'pages:' . implode(',', $sourcePages);
            $scopeKey = hash(
                'sha256',
                $scopeDomain . "\0" . $orderProof['scopeId'] . "\0" . $proofKey
            );
        }

        return [
            'key' => $scopeKey,
            'mode' => $orderProof === null ? 'region-bounded-inventory' : 'mapped-occurrence-exact',
            'orderProof' => $orderProof,
        ];
    }

    /** @return array{x1:float,y1:float,x2:float,y2:float}|null */
    private static function normalizedBounds(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (!is_numeric($value[$key] ?? null) || !is_finite((float) $value[$key])) {
                return null;
            }
        }

        return [
            'x1' => round(min((float) $value['x1'], (float) $value['x2']), 4),
            'y1' => round(min((float) $value['y1'], (float) $value['y2']), 4),
            'x2' => round(max((float) $value['x1'], (float) $value['x2']), 4),
            'y2' => round(max((float) $value['y1'], (float) $value['y2']), 4),
        ];
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $left
     * @param array{x1:float,y1:float,x2:float,y2:float} $right
     */
    private static function boundsIntersect(array $left, array $right): bool
    {
        return max($left['x1'], $right['x1']) <= min($left['x2'], $right['x2'])
            && max($left['y1'], $right['y1']) <= min($left['y2'], $right['y2']);
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param array<string,mixed>|null $current
     * @param array{key:string,mode:string,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|null $scope
     */
    public static function appendOrderProofSegment(
        array &$segments,
        ?array &$current,
        string $text,
        string $sourceOccurrenceId,
        int $sourcePage,
        ?array $scope
    ): void {
        $kind = $scope === null ? 'exact' : $scope['mode'];
        $scopeKey = $scope['key'] ?? '';
        if ($kind === 'exact') {
            self::flushOrderProofSegment($segments, $current);
            $segments[] = PdfSourceDispositionLedger::significantText($text);

            return;
        }
        if (is_array($current)
            && (($current['kind'] ?? null) !== $kind
                || ($current['scopeKey'] ?? '') !== $scopeKey)) {
            self::flushOrderProofSegment($segments, $current);
        }
        if ($current === null) {
            $current = [
                'kind' => $kind,
                'scopeKey' => $scopeKey,
                'bytes' => 0,
                'digestContext' => null,
                'characters' => [],
                'sourceOccurrenceIds' => [],
                'sourcePages' => [],
                'orderProof' => $scope['orderProof'] ?? null,
            ];
        }
        $current['bytes'] += PdfSourceDispositionLedger::updateSignificantCharacterInventory($current['characters'], $text);
        $current['sourceOccurrenceIds'][] = $sourceOccurrenceId;
        $current['sourcePages'][$sourcePage] = true;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param array<string,mixed>|null $current
     */
    public static function flushOrderProofSegment(array &$segments, ?array &$current): void
    {
        if ($current === null) {
            return;
        }
        if ($current['kind'] === 'exact') {
            $current['digest'] = hash_final($current['digestContext']);
        } else {
            ksort($current['characters']);
            if ($current['kind'] === 'mapped-occurrence-exact') {
                $proof = $current['orderProof'];
                $actualPages = array_keys($current['sourcePages']);
                sort($actualPages, SORT_NUMERIC);
                $declaredPages = is_array($proof['sourcePages'] ?? null)
                    ? array_values($proof['sourcePages'])
                    : [];
                if (!is_array($proof)
                    || $proof['sourceOccurrenceIds'] !== $current['sourceOccurrenceIds']
                    || ($declaredPages !== [] && $declaredPages !== $actualPages)) {
                    $current['invalidReason'] = 'mapped-order-proof-source-occurrences-do-not-match-scope';
                } else {
                    $expectedSummary = PdfSourceDispositionLedger::significantCharacterSummary([$proof['emittedTextProjection']]);
                    $expectedCharacters = [];
                    PdfSourceDispositionLedger::updateSignificantCharacterInventory(
                        $expectedCharacters,
                        $proof['emittedTextProjection']
                    );
                    ksort($expectedCharacters);
                    if ($expectedSummary['bytes'] !== $current['bytes']
                        || $expectedCharacters !== $current['characters']) {
                        $current['invalidReason'] = 'mapped-order-proof-does-not-conserve-source-characters';
                    } else {
                        $current['digest'] = $expectedSummary['digest'];
                        $current['mappedProjection'] = PdfSourceDispositionLedger::significantText(
                            $proof['emittedTextProjection']
                        );
                    }
                }
            }
        }
        unset($current['digestContext'], $current['orderProof'], $current['sourcePages']);
        $segments[] = $current;
        $current = null;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param list<AstNode> $blocks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    public static function proveLocalOrderSegments(array $segments, array $blocks): array
    {
        $sequential = self::proveSequentialLocalOrderSegments(
            $segments,
            PdfSourceDispositionLedger::textChunksFromNodes($blocks)
        );
        if ($sequential['preserved']) {
            return $sequential;
        }
        $mappedInterleaved = self::proveOneMappedRegionAroundOrderedOccurrences(
            $segments,
            PdfSourceDispositionLedger::textChunksFromNodes($blocks)
        );
        if ($mappedInterleaved['preserved']) {
            return $mappedInterleaved;
        }
        $interleaved = self::proveOneRegionAroundOrderedOccurrences(
            $segments,
            PdfSourceDispositionLedger::textChunksFromNodes($blocks)
        );

        return $interleaved['preserved'] ? $interleaved : $sequential;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveSequentialLocalOrderSegments(array $segments, iterable $emittedChunks): array
    {
        $characters = PdfSourceDispositionLedger::significantCharactersFromChunks($emittedChunks);
        $characters->rewind();
        $strength = 'mapped-occurrence-exact';
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $segment = [
                    'kind' => 'exact',
                    'bytes' => strlen($segment),
                    'digest' => hash('sha256', $segment),
                ];
            }
            if (is_string($segment['invalidReason'] ?? null)) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => $segment['invalidReason'],
                ];
            }
            $expectedBytes = max(0, (int) ($segment['bytes'] ?? 0));
            $consumedBytes = 0;
            $digest = hash_init('sha256');
            $inventory = [];
            while ($consumedBytes < $expectedBytes) {
                if (!$characters->valid()) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'emitted-significant-characters-ended-before-order-segment',
                    ];
                }
                $character = (string) $characters->current();
                $consumedBytes += strlen($character);
                if ($consumedBytes > $expectedBytes) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'emitted-significant-character-crossed-order-segment-boundary',
                    ];
                }
                if ($segment['kind'] === 'region-bounded-inventory') {
                    $inventory[$character] = ($inventory[$character] ?? 0) + 1;
                    $strength = 'region-bounded-inventory';
                } else {
                    hash_update($digest, $character);
                }
                $characters->next();
            }
            if ($segment['kind'] === 'region-bounded-inventory') {
                ksort($inventory);
                if ($inventory !== ($segment['characters'] ?? [])) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'region-bounded-order-segment-character-mismatch',
                    ];
                }
            } else {
                $actualDigest = hash_final($digest);
                if (!is_string($segment['digest'] ?? null)
                    || !hash_equals($segment['digest'], $actualDigest)) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => $segment['kind'] === 'exact'
                            ? 'non-authorized-order-segment-mismatch'
                            : 'mapped-order-segment-mismatch',
                    ];
                }
            }
        }
        if ($characters->valid()) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'unclaimed-significant-characters-remain-after-order-segments',
            ];
        }

        return ['preserved' => true, 'strength' => $strength, 'failureReason' => null];
    }

    /**
     * One exact mapped scope may be split by unchanged occurrences, such as a
     * page marker between two independent-column flows. The unchanged texts
     * retain their exact relative order, and their sole valid removal must
     * leave precisely the proof's emitted projection.
     *
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveOneMappedRegionAroundOrderedOccurrences(
        array $segments,
        iterable $emittedChunks
    ): array {
        $scopeKeys = [];
        $mappedProjection = null;
        $exactOccurrences = [];
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $exactOccurrences[] = $segment;
                continue;
            }
            if ($segment['kind'] === 'exact') {
                $exactOccurrences[] = (string) ($segment['text'] ?? '');
                continue;
            }
            if ($segment['kind'] !== 'mapped-occurrence-exact'
                || is_string($segment['invalidReason'] ?? null)
                || $mappedProjection !== null) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-requires-one-mapped-scope',
                ];
            }
            $scopeKey = is_string($segment['scopeKey'] ?? null) ? $segment['scopeKey'] : '';
            $projection = $segment['mappedProjection'] ?? null;
            if ($scopeKey === '' || !is_string($projection) || $projection === '') {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-mapped-order-proof-is-incomplete',
                ];
            }
            $scopeKeys[$scopeKey] = true;
            $mappedProjection = $projection;
        }
        if (count($scopeKeys) !== 1 || $mappedProjection === null || $exactOccurrences === []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'interleaved-order-proof-requires-one-mapped-scope',
            ];
        }

        $emitted = '';
        foreach ($emittedChunks as $chunk) {
            if (is_string($chunk) && $chunk !== '') {
                $emitted .= PdfSourceDispositionLedger::significantText($chunk);
            }
        }
        if (PdfSourceProjectionBinding::uniqueInterleavedExactProjectionLayout(
            $emitted,
            $exactOccurrences,
            $mappedProjection
        ) === null) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'mapped-order-segment-interleaving-mismatch',
            ];
        }

        return [
            'preserved' => true,
            'strength' => 'mapped-occurrence-exact',
            'failureReason' => null,
        ];
    }

    /**
     * Native text lines can contain a full visual row while an independent-
     * column output emits the left flow, a full-width atomic item, and then
     * the right flow. In that case one authorized region is non-contiguous in
     * emitted text. Permit only that region's exact character inventory to
     * fill gaps around non-authorized source occurrences; those occurrences
     * must still match internally and in their original relative order.
     *
     * This is intentionally limited to one geometry scope. Multiple regions
     * require the explicit mapped-occurrence proof accepted by the API.
     *
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveOneRegionAroundOrderedOccurrences(array $segments, iterable $emittedChunks): array
    {
        $scopeKeys = [];
        $authorizedCharacters = [];
        $exactOccurrences = [];
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $exactOccurrences[] = $segment;
                continue;
            }
            if ($segment['kind'] === 'exact') {
                $exactOccurrences[] = (string) ($segment['text'] ?? '');
                continue;
            }
            if ($segment['kind'] !== 'region-bounded-inventory'
                || is_string($segment['invalidReason'] ?? null)) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-requires-one-unmapped-region',
                ];
            }
            $scopeKey = is_string($segment['scopeKey'] ?? null) ? $segment['scopeKey'] : '';
            if ($scopeKey === '') {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-has-no-region-scope',
                ];
            }
            $scopeKeys[$scopeKey] = true;
            foreach ($segment['characters'] ?? [] as $character => $count) {
                $authorizedCharacters[$character] = ($authorizedCharacters[$character] ?? 0) + (int) $count;
            }
        }
        if (count($scopeKeys) !== 1 || $authorizedCharacters === []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'interleaved-order-proof-requires-one-region-scope',
            ];
        }

        $emitted = '';
        foreach ($emittedChunks as $chunk) {
            if (is_string($chunk) && $chunk !== '') {
                $emitted .= PdfSourceDispositionLedger::significantText($chunk);
            }
        }
        $cursor = 0;
        foreach ($exactOccurrences as $exact) {
            if ($exact === '') {
                continue;
            }
            $searchOffset = $cursor;
            $matched = false;
            $candidateCount = 0;
            while (($found = strpos($emitted, $exact, $searchOffset)) !== false) {
                $candidateCount++;
                if ($candidateCount > self::ORDER_MATCH_CANDIDATE_LIMIT) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'non-authorized-occurrence-match-candidate-limit',
                    ];
                }
                $gap = substr($emitted, $cursor, $found - $cursor);
                $gapCharacters = [];
                PdfSourceDispositionLedger::updateSignificantCharacterInventory($gapCharacters, $gap);
                if (PdfSourceDispositionLedger::canConsume($authorizedCharacters, $gapCharacters)) {
                    PdfSourceDispositionLedger::consume($authorizedCharacters, $gapCharacters);
                    $cursor = $found + strlen($exact);
                    $matched = true;
                    break;
                }
                $searchOffset = $found + 1;
            }
            if (!$matched) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'non-authorized-occurrence-order-mismatch',
                ];
            }
        }
        $tailCharacters = [];
        PdfSourceDispositionLedger::updateSignificantCharacterInventory($tailCharacters, substr($emitted, $cursor));
        if (!PdfSourceDispositionLedger::canConsume($authorizedCharacters, $tailCharacters)) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'region-bounded-order-tail-character-mismatch',
            ];
        }
        PdfSourceDispositionLedger::consume($authorizedCharacters, $tailCharacters);
        if ($authorizedCharacters !== []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'region-bounded-order-characters-remain-unclaimed',
            ];
        }

        return [
            'preserved' => true,
            'strength' => 'region-bounded-ordered-occurrences',
            'failureReason' => null,
        ];
    }
}
