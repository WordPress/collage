<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfPackageCrc32Inventory
{
    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(array $parts): array
    {
        $groups = [];

        foreach ($parts as $name => $part) {
            if (!is_array($part) || ($part['isDirectory'] ?? false) === true) {
                continue;
            }

            $crc32 = is_string($part['crc32'] ?? null) && $part['crc32'] !== ''
                ? strtolower($part['crc32'])
                : null;
            if ($crc32 === null) {
                continue;
            }

            $entryName = self::entryName($part, (string) $name);
            $byteLength = self::intField($part, 'byteLength');
            $compressedByteLength = self::intField($part, 'compressedByteLength');
            $sourceRecordBytes = self::intField($part, 'zipSourceRecordBytes');
            $compressionMethodKey = is_int($part['compressionMethod'] ?? null)
                ? (string) $part['compressionMethod']
                : '(missing)';
            $byteExposurePolicy = self::stringKey($part['byteExposurePolicy'] ?? null);
            $manifestMediaFamily = self::stringKey($part['manifestMediaFamily'] ?? null);
            $manifestMediaTypeBase = self::stringKey($part['manifestMediaTypeBase'] ?? null);
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));

            if (!isset($groups[$crc32])) {
                $groups[$crc32] = [
                    'crc32' => $crc32,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'sourceRecordBytes' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'manifestDeclaredEntryCount' => 0,
                    'undeclaredEntryCount' => 0,
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                ];
            }

            ++$groups[$crc32]['entryCount'];
            $groups[$crc32]['entryNames'][] = $entryName;
            $groups[$crc32]['byteLength'] += $byteLength;
            $groups[$crc32]['compressedByteLength'] += $compressedByteLength;
            $groups[$crc32]['sourceRecordBytes'] += $sourceRecordBytes;
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$groups[$crc32]['exposableEntryCount'];
            } else {
                ++$groups[$crc32]['blockedEntryCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$groups[$crc32]['manifestDeclaredEntryCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$groups[$crc32]['undeclaredEntryCount'];
            }

            $groups[$crc32]['compressionMethodCounts'][$compressionMethodKey] =
                ($groups[$crc32]['compressionMethodCounts'][$compressionMethodKey] ?? 0) + 1;
            $groups[$crc32]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($groups[$crc32]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $groups[$crc32]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($groups[$crc32]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $groups[$crc32]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($groups[$crc32]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;

            foreach ($roles as $role) {
                if ($role === '') {
                    continue;
                }

                $groups[$crc32]['roleCounts'][$role] = ($groups[$crc32]['roleCounts'][$role] ?? 0) + 1;
            }
        }

        $counts = [];
        $byteLengths = [];
        $compressedByteLengths = [];
        $sourceRecordBytes = [];
        $entryNamesByCrc32 = [];
        $duplicateGroups = [];
        $duplicateEntryCount = 0;

        ksort($groups, SORT_STRING);
        foreach ($groups as $crc32 => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $groups[$crc32] = $summary;

            $counts[$crc32] = $summary['entryCount'];
            $byteLengths[$crc32] = $summary['byteLength'];
            $compressedByteLengths[$crc32] = $summary['compressedByteLength'];
            $sourceRecordBytes[$crc32] = $summary['sourceRecordBytes'];
            $entryNamesByCrc32[$crc32] = $summary['entryNames'];

            if ($summary['entryCount'] > 1) {
                $duplicateGroups[] = $summary;
                $duplicateEntryCount += $summary['entryCount'];
            }
        }

        return [
            'packageCrc32EntryCount' => array_sum($counts),
            'packageCrc32Count' => count($groups),
            'packageDuplicateCrc32Count' => count($duplicateGroups),
            'packageDuplicateCrc32EntryCount' => $duplicateEntryCount,
            'packageCrc32Counts' => $counts,
            'packageCrc32ByteLengths' => $byteLengths,
            'packageCrc32CompressedByteLengths' => $compressedByteLengths,
            'packageCrc32SourceRecordBytes' => $sourceRecordBytes,
            'entryNamesByPackageCrc32' => $entryNamesByCrc32,
            'packageCrc32Summaries' => array_values($groups),
            'packageDuplicateCrc32Summaries' => $duplicateGroups,
        ];
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function intField(array $part, string $field): int
    {
        $value = $part[$field] ?? null;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function entryName(array $part, string $fallback): string
    {
        if (is_string($part['path'] ?? null) && $part['path'] !== '') {
            return $part['path'];
        }
        if (is_string($part['part'] ?? null) && $part['part'] !== '') {
            return $part['part'];
        }

        return $fallback;
    }

    private static function stringKey(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '(missing)';
    }
}
