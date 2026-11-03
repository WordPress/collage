<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Prove one complete token interleaving without loading the full binder. */
final class PdfSourceTokenInterleavingValidator
{
    private const SOURCE_BINDING_STATE_LIMIT = 100000;

    /** @param array<string,string> $sourceOccurrenceProjections */
    public static function hasUniqueTokenInterleavingOrderProof(
        array $sourceOccurrenceProjections,
        string $emittedProjection
    ): bool {
        if ($sourceOccurrenceProjections === []) {
            return false;
        }
        $sourceTokens = [];
        foreach ($sourceOccurrenceProjections as $id => $projection) {
            if (!is_string($id) || $id === '' || !is_string($projection)) {
                return false;
            }
            $significant = self::significantText($projection);
            $tokens = self::sourceBindingTokens($significant);
            if ($significant === '' || $tokens === []) {
                return false;
            }
            $sourceTokens[] = $tokens;
        }
        $emitted = self::significantText($emittedProjection);
        $outputTokens = self::sourceBindingTokens($emitted);
        $sourceTokenCount = array_sum(array_map('count', $sourceTokens));
        if ($emitted === '' || $outputTokens === [] || count($outputTokens) !== $sourceTokenCount) {
            return false;
        }

        $memo = [];
        $stateCount = 0;
        $solve = function (array $positions) use (
            &$solve,
            &$memo,
            &$stateCount,
            $sourceTokens,
            $outputTokens
        ): int {
            $key = implode(',', $positions);
            if (isset($memo[$key])) {
                return $memo[$key];
            }
            $stateCount++;
            if ($stateCount > self::SOURCE_BINDING_STATE_LIMIT) {
                return $memo[$key] = 2;
            }
            $outputIndex = array_sum($positions);
            if ($outputIndex === count($outputTokens)) {
                foreach ($positions as $sourceIndex => $position) {
                    if ($position !== count($sourceTokens[$sourceIndex])) {
                        return $memo[$key] = 0;
                    }
                }

                return $memo[$key] = 1;
            }

            $count = 0;
            foreach ($positions as $sourceIndex => $position) {
                if (!isset($sourceTokens[$sourceIndex][$position])
                    || $sourceTokens[$sourceIndex][$position]['text']
                        !== $outputTokens[$outputIndex]['text']) {
                    continue;
                }
                $next = $positions;
                $next[$sourceIndex]++;
                $count = min(2, $count + $solve($next));
                if ($count > 1) {
                    break;
                }
            }

            return $memo[$key] = $count;
        };

        return $solve(array_fill(0, count($sourceTokens), 0)) === 1;
    }

    /** @return list<array{text:string,start:int,end:int}> */
    private static function sourceBindingTokens(string $significant): array
    {
        if ($significant === '') {
            return [];
        }
        $matched = preg_match_all(
            '/[\p{L}\p{M}\p{N}]+|./us',
            $significant,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if ($matched === false || $matched === 0) {
            return [];
        }
        $tokens = [];
        $covered = '';
        foreach ($matches[0] as [$token, $offset]) {
            $token = (string) $token;
            $tokens[] = [
                'text' => $token,
                'start' => (int) $offset,
                'end' => (int) $offset + strlen($token),
            ];
            $covered .= $token;
        }

        return hash_equals($significant, $covered) ? $tokens : [];
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
