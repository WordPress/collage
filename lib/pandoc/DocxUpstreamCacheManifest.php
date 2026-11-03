<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxUpstreamCacheManifest
{
    public const DEFAULT_RELATIVE_DOCX_DIR = '.upstream-cache/pandoc-current/test/docx';
    public const CURRENT_UPSTREAM_COMMIT = '612e143fbe6d735b612c4800d21e61b7d44e4dca';
    public const CHECKED_IN_REPORT_PATH = 'lanes/pandoc/UPSTREAM_DOCX_CACHE_MANIFEST.json';
    public const TOOL_NAME = 'pandoc-docx-cache-manifest';
    public const STATUS_REPORTED = 'reported_optional_upstream_docx_cache_manifest';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped_missing_upstream_docx_directory';
    public const STATUS_SKIPPED_UNREADABLE_SOURCE = 'skipped_unreadable_upstream_docx_directory';

    private readonly string $repoRoot;
    private readonly string $docxDirectory;

    public function __construct(string $repoRoot, string $docxDirectory = self::DEFAULT_RELATIVE_DOCX_DIR)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($docxDirectory === '') {
            throw new \InvalidArgumentException('DOCX directory must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->docxDirectory = $docxDirectory;
    }

    /**
     * @param array<string, mixed> $sourceProvenance
     * @return array<string, mixed>
     */
    public function report(array $sourceProvenance = []): array
    {
        $docxDir = $this->absoluteDocxDirectory();
        if (!is_dir($docxDir)) {
            return $this->skipReport(self::STATUS_SKIPPED_MISSING_SOURCE, "Upstream DOCX directory does not exist: {$docxDir}", $sourceProvenance);
        }
        if (!is_readable($docxDir)) {
            return $this->skipReport(self::STATUS_SKIPPED_UNREADABLE_SOURCE, "Upstream DOCX directory is not readable: {$docxDir}", $sourceProvenance);
        }

        try {
            $inventory = $this->inventory($docxDir);
        } catch (\UnexpectedValueException $exception) {
            return $this->skipReport(self::STATUS_SKIPPED_UNREADABLE_SOURCE, $exception->getMessage(), $sourceProvenance);
        }

        $artifactRows = $inventory['artifactRows'];
        $artifactSetRows = array_map(
            static fn (array $row): array => [
                'kind' => $row['kind'],
                'path' => $row['path'],
                'bytes' => $row['bytes'],
                'sha256' => $row['sha256'],
            ],
            $artifactRows
        );

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_REPORTED,
            'skipped' => false,
            'generatedAt' => gmdate('Y-m-d'),
            'upstream' => [
                'name' => 'jgm/pandoc',
                'url' => 'https://github.com/jgm/pandoc',
                'commit' => (string) ($sourceProvenance['observedUpstreamCommit'] ?? self::CURRENT_UPSTREAM_COMMIT),
                'expectedCommit' => self::CURRENT_UPSTREAM_COMMIT,
                'commitMatchesExpected' => (string) ($sourceProvenance['observedUpstreamCommit'] ?? self::CURRENT_UPSTREAM_COMMIT) === self::CURRENT_UPSTREAM_COMMIT,
            ],
            'source' => [
                'cachePath' => self::DEFAULT_RELATIVE_DOCX_DIR,
                'observedCachePath' => $this->displayPath($docxDir),
                'workingTreeCleanForTestDocx' => $sourceProvenance['workingTreeCleanForTestDocx'] ?? null,
                'upstreamRoot' => $sourceProvenance['upstreamRootDisplay'] ?? null,
            ],
            'evidenceKind' => 'artifact-identity-manifest-only',
            'claim' => 'Records optional local upstream DOCX/native/golden artifact identity using paths, stems, byte counts, and SHA-256 hashes only.',
            'claimBoundaries' => self::claimBoundaries(),
            'hydration' => self::hydrationInstructions(),
            'artifactCounts' => $inventory['artifactCounts'],
            'rootDocxPackageStems' => $inventory['rootDocxPackageStems'],
            'rootNativeExpectedStems' => $inventory['rootNativeExpectedStems'],
            'pairedRootDocxNativeStems' => $inventory['pairedRootDocxNativeStems'],
            'unpairedRootDocxPackageStems' => $inventory['unpairedRootDocxPackageStems'],
            'unpairedRootNativeExpectedStems' => $inventory['unpairedRootNativeExpectedStems'],
            'goldenDocxPackageStems' => $inventory['goldenDocxPackageStems'],
            'artifactSetSha256' => hash('sha256', self::canonicalJson($artifactSetRows)),
            'artifactRows' => $artifactRows,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX upstream cache manifest',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Evidence kind: ' . (string) ($report['evidenceKind'] ?? 'unknown'),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'Result: skipped';
            $lines[] = 'Reason: ' . (string) ($report['reason'] ?? 'source directory unavailable');
            $lines[] = 'No artifact identity or DOCX parity is asserted.';

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $counts = is_array($report['artifactCounts'] ?? null) ? $report['artifactCounts'] : [];
        $lines[] = 'Upstream commit: ' . (string) ($report['upstream']['commit'] ?? '');
        $lines[] = 'Cache path: ' . (string) ($report['source']['observedCachePath'] ?? '');
        $lines[] = 'Artifacts: '
            . (int) ($counts['totalDocxNativeGoldenArtifacts'] ?? 0)
            . ' total; '
            . (int) ($counts['rootDocxPackageArtifacts'] ?? 0)
            . ' root .docx; '
            . (int) ($counts['rootNativeExpectedArtifacts'] ?? 0)
            . ' root .native; '
            . (int) ($counts['goldenDocxPackageArtifacts'] ?? 0)
            . ' golden .docx';
        $lines[] = 'Paired root stems: ' . (int) ($counts['pairedRootDocxNativeStems'] ?? 0);
        $lines[] = 'Artifact set SHA-256: ' . (string) ($report['artifactSetSha256'] ?? '');
        $lines[] = 'No package bytes, AST equality, upstream runner parity, or writer golden parity is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function skipReport(string $status, string $reason, array $sourceProvenance): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => $status,
            'skipped' => true,
            'generatedAt' => gmdate('Y-m-d'),
            'upstream' => [
                'name' => 'jgm/pandoc',
                'url' => 'https://github.com/jgm/pandoc',
                'commit' => (string) ($sourceProvenance['observedUpstreamCommit'] ?? self::CURRENT_UPSTREAM_COMMIT),
                'expectedCommit' => self::CURRENT_UPSTREAM_COMMIT,
                'commitMatchesExpected' => (string) ($sourceProvenance['observedUpstreamCommit'] ?? self::CURRENT_UPSTREAM_COMMIT) === self::CURRENT_UPSTREAM_COMMIT,
            ],
            'source' => [
                'cachePath' => self::DEFAULT_RELATIVE_DOCX_DIR,
                'observedCachePath' => $this->displayPath($this->absoluteDocxDirectory()),
                'workingTreeCleanForTestDocx' => $sourceProvenance['workingTreeCleanForTestDocx'] ?? null,
                'upstreamRoot' => $sourceProvenance['upstreamRootDisplay'] ?? null,
            ],
            'evidenceKind' => 'artifact-identity-manifest-only',
            'claim' => 'Records optional local upstream DOCX/native/golden artifact identity using paths, stems, byte counts, and SHA-256 hashes only.',
            'claimBoundaries' => self::claimBoundaries(),
            'hydration' => self::hydrationInstructions(),
            'reason' => $reason,
            'artifactCounts' => [
                'totalDocxNativeGoldenArtifacts' => 0,
                'rootDocxPackageArtifacts' => 0,
                'rootNativeExpectedArtifacts' => 0,
                'rootDocxAndNativeArtifacts' => 0,
                'goldenDocxPackageArtifacts' => 0,
                'totalDocxPackageArtifacts' => 0,
                'pairedRootDocxNativeStems' => 0,
                'unpairedRootDocxPackageStems' => 0,
                'unpairedRootNativeExpectedStems' => 0,
            ],
            'artifactSetSha256' => hash('sha256', self::canonicalJson([])),
            'artifactRows' => [],
        ];
    }

    private function absoluteDocxDirectory(): string
    {
        if (str_starts_with($this->docxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->docxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->docxDirectory);
    }

    /**
     * @return array{
     *     artifactCounts:array<string, int>,
     *     rootDocxPackageStems:list<string>,
     *     rootNativeExpectedStems:list<string>,
     *     pairedRootDocxNativeStems:list<string>,
     *     unpairedRootDocxPackageStems:list<string>,
     *     unpairedRootNativeExpectedStems:list<string>,
     *     goldenDocxPackageStems:list<string>,
     *     artifactRows:list<array{kind:string,path:string,stem:string,bytes:int,sha256:string}>
     * }
     */
    private function inventory(string $docxDir): array
    {
        $rootDocxByStem = [];
        $rootNativeByStem = [];
        $goldenDocxByStem = [];
        $artifactRows = [];

        foreach (new \DirectoryIterator($docxDir) as $entry) {
            if ($entry->isDot() || !$entry->isFile()) {
                continue;
            }

            $extension = strtolower($entry->getExtension());
            if ($extension !== 'docx' && $extension !== 'native') {
                continue;
            }

            $stem = pathinfo($entry->getFilename(), PATHINFO_FILENAME);
            $path = $entry->getPathname();
            if ($extension === 'docx') {
                $rootDocxByStem[$stem] = $path;
                $artifactRows[] = $this->artifactRow('root-docx-package', 'test/docx/' . $entry->getFilename(), $stem, $path);
            } else {
                $rootNativeByStem[$stem] = $path;
                $artifactRows[] = $this->artifactRow('root-native-expected', 'test/docx/' . $entry->getFilename(), $stem, $path);
            }
        }

        $goldenDir = $docxDir . DIRECTORY_SEPARATOR . 'golden';
        if (is_dir($goldenDir) && is_readable($goldenDir)) {
            foreach (new \DirectoryIterator($goldenDir) as $entry) {
                if ($entry->isDot() || !$entry->isFile() || strtolower($entry->getExtension()) !== 'docx') {
                    continue;
                }

                $stem = pathinfo($entry->getFilename(), PATHINFO_FILENAME);
                $goldenDocxByStem[$stem] = $entry->getPathname();
                $artifactRows[] = $this->artifactRow('golden-docx-package', 'test/docx/golden/' . $entry->getFilename(), $stem, $entry->getPathname());
            }
        } elseif (is_dir($goldenDir) && !is_readable($goldenDir)) {
            throw new \UnexpectedValueException("Upstream DOCX golden directory is not readable: {$goldenDir}");
        }

        ksort($rootDocxByStem, SORT_STRING);
        ksort($rootNativeByStem, SORT_STRING);
        ksort($goldenDocxByStem, SORT_STRING);
        usort($artifactRows, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        $rootDocxStems = array_keys($rootDocxByStem);
        $rootNativeStems = array_keys($rootNativeByStem);
        $pairedStems = array_values(array_intersect($rootDocxStems, $rootNativeStems));
        $unpairedDocxStems = array_values(array_diff($rootDocxStems, $rootNativeStems));
        $unpairedNativeStems = array_values(array_diff($rootNativeStems, $rootDocxStems));
        sort($pairedStems, SORT_STRING);
        sort($unpairedDocxStems, SORT_STRING);
        sort($unpairedNativeStems, SORT_STRING);

        return [
            'artifactCounts' => [
                'totalDocxNativeGoldenArtifacts' => count($artifactRows),
                'rootDocxPackageArtifacts' => count($rootDocxByStem),
                'rootNativeExpectedArtifacts' => count($rootNativeByStem),
                'rootDocxAndNativeArtifacts' => count($rootDocxByStem) + count($rootNativeByStem),
                'goldenDocxPackageArtifacts' => count($goldenDocxByStem),
                'totalDocxPackageArtifacts' => count($rootDocxByStem) + count($goldenDocxByStem),
                'pairedRootDocxNativeStems' => count($pairedStems),
                'unpairedRootDocxPackageStems' => count($unpairedDocxStems),
                'unpairedRootNativeExpectedStems' => count($unpairedNativeStems),
            ],
            'rootDocxPackageStems' => $rootDocxStems,
            'rootNativeExpectedStems' => $rootNativeStems,
            'pairedRootDocxNativeStems' => $pairedStems,
            'unpairedRootDocxPackageStems' => $unpairedDocxStems,
            'unpairedRootNativeExpectedStems' => $unpairedNativeStems,
            'goldenDocxPackageStems' => array_keys($goldenDocxByStem),
            'artifactRows' => $artifactRows,
        ];
    }

    /**
     * @return array{kind:string,path:string,stem:string,bytes:int,sha256:string}
     */
    private function artifactRow(string $kind, string $relativePath, string $stem, string $path): array
    {
        $bytes = filesize($path);
        $sha256 = hash_file('sha256', $path);
        if (!is_int($bytes) || !is_string($sha256)) {
            throw new \UnexpectedValueException("Unable to hash upstream DOCX artifact: {$path}");
        }

        return [
            'kind' => $kind,
            'path' => $relativePath,
            'stem' => $stem,
            'bytes' => $bytes,
            'sha256' => $sha256,
        ];
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'asserts' => [
                'optional upstream cache file identity for root-level .docx packages',
                'optional upstream cache file identity for root-level .native expectations',
                'optional upstream cache file identity for golden/*.docx writer package artifacts',
                'artifact stems, relative paths, byte counts, and SHA-256 hashes',
            ],
            'doesNotAssert' => [
                'checked-in DOCX package bytes',
                'pinned upstream DOCX package corpus availability in every worktree or CI job',
                'local DOCX parser acceptance',
                'Pandoc AST equality between DOCX reader output and upstream .native expectations',
                'upstream Haskell/Cabal test-pandoc DOCX runner parity',
                'DOCX writer golden package round-trip parity',
                'full DOCX/OpenXML semantic parity',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function hydrationInstructions(): array
    {
        return [
            'targetPath' => self::DEFAULT_RELATIVE_DOCX_DIR,
            'upstreamCommit' => self::CURRENT_UPSTREAM_COMMIT,
            'commands' => [
                'git clone https://github.com/jgm/pandoc.git .upstream-cache/pandoc-current',
                'git -C .upstream-cache/pandoc-current checkout ' . self::CURRENT_UPSTREAM_COMMIT,
                'php tools/pandoc-docx-cache-manifest.php --repo-root=. --docx-dir=.upstream-cache/pandoc-current/test/docx --json',
            ],
            'note' => 'The manifest is metadata-only. Hydrating the binary cache is optional and is required only when regenerating or auditing artifact hashes locally.',
        ];
    }

    private static function canonicalJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
