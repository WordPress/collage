<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PdfMisorderedDiacriticBoundaryRepair
{
    /**
     * @param list<array<string,mixed>> $sourceItems
     * @param list<AstNode> $blocks
     * @param array<int,array{start:int,end:int,projection:string,leafEnds:list<int>}> $finalOutputPageProofs
     * @param \Closure(list<AstNode>, array<int,array{start:int,end:int,projection?:string,leafEnds?:list<int>}>): array<int,array{start:int,end:int,projection:string,leafEnds:list<int>}> $validatedFinalOutputPageProofs
     * @param \Closure(list<AstNode>): (array{start:int,end:int,projection:string,leafEnds:list<int>}|null) $wholeOutputPageProof
     * @param \Closure(array<int,array{start:int,end:int,projection:string,leafEnds:list<int>}>, int): (array{start:int,end:int,projection:string,leafEnds:list<int>}|null) $normalizedOutputPageProof
     * @param \Closure(string): string $sourceOccurrenceComparableText
     * @param \Closure(array<string,mixed>): bool $sourceBoundsAreValid
     * @param \Closure(array<string,mixed>): array{x1:float,y1:float,x2:float,y2:float} $sourceEvidenceBounds
     * @return list<array{
     *     markId:string,
     *     baseId:string,
     *     baseText:string,
     *     baseCharacter:string,
     *     composedCharacter:string,
     *     evidence:array<string,mixed>
     * }>
     */
    public static function repairs(
        array $sourceItems,
        array $blocks,
        array $finalOutputPageProofs,
        string $sourceSha256,
        \Closure $validatedFinalOutputPageProofs,
        \Closure $wholeOutputPageProof,
        \Closure $normalizedOutputPageProof,
        \Closure $sourceOccurrenceComparableText,
        \Closure $sourceBoundsAreValid,
        \Closure $sourceEvidenceBounds
    ): array {

        if (count($sourceItems) < 3 || $blocks === []) {
            return [];
        }
        $pages = [];
        $sourceProjectionByPage = [];
        foreach ($sourceItems as $sourceItem) {
            $page = max(1, (int) ($sourceItem['page'] ?? 1));
            $pages[$page] = true;
            $sourceProjectionByPage[$page] = ($sourceProjectionByPage[$page] ?? '')
                . $sourceOccurrenceComparableText(
                    (string) ($sourceItem['text'] ?? '')
                );
        }
        $outputProofs = $validatedFinalOutputPageProofs(
            $blocks,
            $finalOutputPageProofs
        );
        if ($outputProofs === [] && count($pages) === 1) {
            $page = (int) array_key_first($pages);
            $wholeProof = $wholeOutputPageProof($blocks);
            if ($wholeProof !== null) {
                $outputProofs[$page] = $wholeProof;
            }
        }
        if ($outputProofs === []) {
            return [];
        }
        $combiningMarks = [
            "\u{00A8}" => "\u{0308}",
            "\u{00AF}" => "\u{0304}",
            "\u{00B4}" => "\u{0301}",
            "\u{00B8}" => "\u{0327}",
            "\u{02C7}" => "\u{030C}",
            "\u{02D8}" => "\u{0306}",
            "\u{02D9}" => "\u{0307}",
            "\u{02DA}" => "\u{030A}",
            "\u{02DC}" => "\u{0303}",
        ];
        $idCounts = [];
        foreach ($sourceItems as $sourceItem) {
            $id = is_string($sourceItem['id'] ?? null) ? $sourceItem['id'] : '';
            if ($id !== '') {
                $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
            }
        }

        $repairs = [];
        $usedIds = [];
        for ($markIndex = 1, $count = count($sourceItems) - 1;
            $markIndex < $count;
            $markIndex++) {
            $previous = $sourceItems[$markIndex - 1] ?? null;
            $mark = $sourceItems[$markIndex] ?? null;
            $base = $sourceItems[$markIndex + 1] ?? null;
            if (!is_array($previous) || !is_array($mark) || !is_array($base)) {
                continue;
            }
            $previousId = is_string($previous['id'] ?? null) ? $previous['id'] : '';
            $markId = is_string($mark['id'] ?? null) ? $mark['id'] : '';
            $baseId = is_string($base['id'] ?? null) ? $base['id'] : '';
            if ($previousId === ''
                || $markId === ''
                || $baseId === ''
                || count(array_unique([$previousId, $markId, $baseId])) !== 3
                || ($idCounts[$previousId] ?? 0) !== 1
                || ($idCounts[$markId] ?? 0) !== 1
                || ($idCounts[$baseId] ?? 0) !== 1
                || isset($usedIds[$previousId], $usedIds[$markId], $usedIds[$baseId])) {
                continue;
            }
            $previousText = is_string($previous['text'] ?? null)
                ? $previous['text']
                : '';
            $markText = is_string($mark['text'] ?? null) ? $mark['text'] : '';
            $baseText = is_string($base['text'] ?? null) ? $base['text'] : '';
            if (!isset($combiningMarks[$markText])
                || preg_match('/(\p{L})\z/uD', $previousText, $previousMatch) !== 1
                || preg_match('/\A(\p{L})/uD', $baseText, $baseMatch) !== 1) {
                continue;
            }
            $baseCharacter = $baseMatch[1];
            $normalizedBase = match ($baseCharacter) {
                "\u{0131}" => 'i',
                "\u{0237}" => 'j',
                default => $baseCharacter,
            };
            $composedCharacter = $normalizedBase . $combiningMarks[$markText];
            if (class_exists(\Normalizer::class)) {
                $normalized = \Normalizer::normalize(
                    $composedCharacter,
                    \Normalizer::FORM_C
                );
                if (is_string($normalized)) {
                    $composedCharacter = $normalized;
                }
            }
            if ($composedCharacter === $markText . $baseCharacter
                || !self::geometryTripleIsExact(
                    $previous,
                    $mark,
                    $base,
                    $sourceBoundsAreValid,
                    $sourceEvidenceBounds
                )) {
                continue;
            }

            $page = (int) $mark['page'];
            $stream = (int) $mark['stream'];
            $identity = [
                'sourceSha256' => $sourceSha256,
                'page' => $page,
                'stream' => $stream,
                'sourceIndexes' => [
                    'previous' => $markIndex - 1,
                    'mark' => $markIndex,
                    'base' => $markIndex + 1,
                ],
                'sourceOccurrenceIds' => [
                    'previous' => $previousId,
                    'mark' => $markId,
                    'base' => $baseId,
                ],
                'sourceDigests' => [
                    'previous' => hash('sha256', $previousText),
                    'mark' => hash('sha256', $markText),
                    'base' => hash('sha256', $baseText),
                ],
                'sourceBounds' => [
                    'previous' => $sourceEvidenceBounds($previous['sourceGeometry']),
                    'mark' => $sourceEvidenceBounds($mark['sourceGeometry']),
                    'base' => $sourceEvidenceBounds($base['sourceGeometry']),
                ],
                'baseCharacterDigest' => hash('sha256', $baseCharacter),
                'composedCharacterDigest' => hash('sha256', $composedCharacter),
            ];
            $encoded = json_encode(
                $identity,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
            );
            $evidence = [
                'method' => 'exact-source-geometry-misordered-diacritic-boundary',
            ] + $identity + [
                'proofDigest' => hash(
                    'sha256',
                    is_string($encoded) ? $encoded : serialize($identity)
                ),
            ];
            $repairs[] = [
                'previousId' => $previousId,
                'markId' => $markId,
                'baseId' => $baseId,
                'previousText' => $previousText,
                'markText' => $markText,
                'baseText' => $baseText,
                'baseCharacter' => $baseCharacter,
                'composedCharacter' => $composedCharacter,
                'evidence' => $evidence,
            ];
            $usedIds[$previousId] = true;
            $usedIds[$markId] = true;
            $usedIds[$baseId] = true;
        }
        if ($repairs === []) {
            return [];
        }

        $authorized = [];
        $repairIndexesByPageAndCharacters = [];
        foreach ($repairs as $repairIndex => $repair) {
            $page = (int) ($repair['evidence']['page'] ?? 0);
            $groupKey = implode("\0", [
                (string) $page,
                $repair['markText'],
                $repair['baseCharacter'],
                $repair['composedCharacter'],
            ]);
            $repairIndexesByPageAndCharacters[$groupKey][] = $repairIndex;
        }
        foreach ($repairIndexesByPageAndCharacters as $repairIndexes) {
            $first = $repairs[$repairIndexes[0]] ?? null;
            if (!is_array($first)) {
                continue;
            }
            $page = (int) ($first['evidence']['page'] ?? 0);
            $outputProof = $normalizedOutputPageProof($outputProofs, $page);
            $sourceProjection = $sourceProjectionByPage[$page] ?? '';
            if ($outputProof === null || $sourceProjection === '') {
                continue;
            }
            $outputProjection = $outputProof['projection'];
            $markText = $first['markText'];
            $baseCharacter = $first['baseCharacter'];
            $composedCharacter = $first['composedCharacter'];
            $candidateCount = count($repairIndexes);
            $sourceMarkCount = substr_count($sourceProjection, $markText);
            $outputMarkCount = substr_count($outputProjection, $markText);
            $sourceBaseCount = substr_count($sourceProjection, $baseCharacter);
            $outputBaseCount = substr_count($outputProjection, $baseCharacter);
            $sourceComposedCount = substr_count($sourceProjection, $composedCharacter);
            $outputComposedCount = substr_count($outputProjection, $composedCharacter);
            if ($sourceMarkCount - $outputMarkCount !== $candidateCount
                || $sourceBaseCount - $outputBaseCount !== $candidateCount
                || $outputComposedCount - $sourceComposedCount !== $candidateCount) {
                continue;
            }

            $localTargets = [];
            $groupAuthorized = true;
            foreach ($repairIndexes as $repairIndex) {
                $repair = $repairs[$repairIndex];
                $previousProjection = $sourceOccurrenceComparableText(
                    $repair['previousText']
                );
                $markProjection = $sourceOccurrenceComparableText(
                    $repair['markText']
                );
                $baseProjection = $sourceOccurrenceComparableText(
                    $repair['baseText']
                );
                if ($previousProjection === ''
                    || $markProjection === ''
                    || !str_starts_with($baseProjection, $repair['baseCharacter'])) {
                    $groupAuthorized = false;
                    break;
                }
                $rawTarget = $previousProjection . $markProjection . $baseProjection;
                $composedTarget = $previousProjection
                    . $repair['composedCharacter']
                    . substr($baseProjection, strlen($repair['baseCharacter']));
                if (substr_count($outputProjection, $composedTarget) !== 1
                    || str_contains($outputProjection, $rawTarget)) {
                    $groupAuthorized = false;
                    break;
                }
                $targetDigest = hash('sha256', $composedTarget);
                if (isset($localTargets[$targetDigest])) {
                    $groupAuthorized = false;
                    break;
                }
                $localTargets[$targetDigest] = true;
            }
            if (!$groupAuthorized) {
                continue;
            }

            $repairIds = array_map(
                static fn (int $repairIndex): array => [
                    'previous' => $repairs[$repairIndex]['previousId'],
                    'mark' => $repairs[$repairIndex]['markId'],
                    'base' => $repairs[$repairIndex]['baseId'],
                ],
                $repairIndexes
            );
            $identity = [
                'method' => 'exact-final-page-diacritic-composition',
                'sourceSha256' => $sourceSha256,
                'page' => $page,
                'sourceMarkCount' => $sourceMarkCount,
                'outputMarkCount' => $outputMarkCount,
                'sourceBaseCount' => $sourceBaseCount,
                'outputBaseCount' => $outputBaseCount,
                'sourceComposedCount' => $sourceComposedCount,
                'outputComposedCount' => $outputComposedCount,
                'repairSourceOccurrenceIds' => $repairIds,
                'localTargetDigests' => array_keys($localTargets),
                'outputProjectionDigest' => hash('sha256', $outputProjection),
            ];
            $encoded = json_encode(
                $identity,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $identity['proofDigest'] = hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($identity)
            );
            foreach ($repairIndexes as $repairIndex) {
                $repair = $repairs[$repairIndex];
                unset(
                    $repair['previousId'],
                    $repair['previousText'],
                    $repair['markText']
                );
                $repair['evidence']['finalPageComposition'] = $identity;
                $authorized[] = $repair;
            }
        }

        return $authorized;
        }

    /**
     * @param array<string,mixed> $previous
     * @param array<string,mixed> $mark
     * @param array<string,mixed> $base
     * @param \Closure(array<string,mixed>): bool $sourceBoundsAreValid
     * @param \Closure(array<string,mixed>): array{x1:float,y1:float,x2:float,y2:float} $sourceEvidenceBounds
     */
    private static function geometryTripleIsExact(
        array $previous,
        array $mark,
        array $base,
        \Closure $sourceBoundsAreValid,
        \Closure $sourceEvidenceBounds
    ): bool {

        $page = $mark['page'] ?? null;
        $stream = $mark['stream'] ?? null;
        if (!is_int($page)
            || $page < 1
            || !is_int($stream)
            || $stream < 1) {
            return false;
        }
        $bounds = [];
        foreach ([$previous, $mark, $base] as $item) {
            $geometry = is_array($item['sourceGeometry'] ?? null)
                ? $item['sourceGeometry']
                : null;
            if (($item['page'] ?? null) !== $page
                || ($item['stream'] ?? null) !== $stream
                || ($item['sourceGeometryMethod'] ?? null)
                    !== 'exact-page-stream-character-offset'
                || !is_array($geometry)
                || !$sourceBoundsAreValid($geometry)
                || ($geometry['page'] ?? null) !== $page
                || ($geometry['stream'] ?? null) !== $stream
                || (isset($geometry['orientation'])
                    && $geometry['orientation'] !== 'horizontal')) {
                return false;
            }
            $bounds[] = $sourceEvidenceBounds($geometry);
        }
        [$previousBounds, $markBounds, $baseBounds] = $bounds;
        $height = max(
            1.0,
            $previousBounds['y2'] - $previousBounds['y1'],
            $markBounds['y2'] - $markBounds['y1'],
            $baseBounds['y2'] - $baseBounds['y1']
        );
        $baselineSpread = max(
            $previousBounds['y1'],
            $markBounds['y1'],
            $baseBounds['y1']
        ) - min(
            $previousBounds['y1'],
            $markBounds['y1'],
            $baseBounds['y1']
        );
        if ($baselineSpread > max(1.5, $height * 0.35)) {
            return false;
        }
        $leadingDelta = abs($markBounds['x1'] - $baseBounds['x1']);
        $previousGap = min($markBounds['x1'], $baseBounds['x1'])
            - $previousBounds['x2'];

        return $leadingDelta <= max(2.0, $height * 0.5)
            && $markBounds['x2'] >= $baseBounds['x1'] - max(1.0, $height * 0.2)
            && $previousGap >= -max(6.0, $height * 0.6)
            && $previousGap <= max(3.0, $height * 0.75);
        }
}
