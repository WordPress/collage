<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-xlsx-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-xlsx-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-xlsx-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-xlsx-reader';
    public const EXPECTED_STATIC_FIXTURE_PAIR_COUNT = 1;

    private const CHECKED_IN_CURRENT_FIXTURES = [
        'basic.xlsx' => [
            'role' => 'upstream-xlsx-reader-input-fixture',
            'upstreamPath' => 'test/xlsx-reader/basic.xlsx',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-xlsx-reader/basic.xlsx',
            'sha256' => '68f8e01ee5463f3f6596a12745f5d9de982c0140a728e97ddfbac1177b80b2ae',
            'bytes' => 13604,
        ],
        'basic.native' => [
            'role' => 'upstream-xlsx-reader-native-golden',
            'upstreamPath' => 'test/xlsx-reader/basic.native',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-xlsx-reader/basic.native',
            'sha256' => 'c170d89ab26fd10b3d9d6d401399c568f2043908c5ab68575029c6394a870247',
            'bytes' => 11298,
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        $staticEvidence = self::checkedInCurrentEvidence($this->repoRoot);
        if (!is_dir($root)) {
            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => $staticEvidence,
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/Xlsx.hs';
        $fixtureDirectory = $root . '/test/xlsx-reader';
        $readerCases = is_file($readerTestPath)
            ? $this->parseReaderCases((string) file_get_contents($readerTestPath))
            : [];
        $fixturePairs = $this->fixturePairs($fixtureDirectory);
        $validationIssues = $this->validationIssues($root, $readerCases, $fixturePairs);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Xlsx.hs',
                'fixtureDirectory' => 'test/xlsx-reader',
            ],
            'denominator' => [
                'readerTestCompareCount' => count($readerCases),
                'fixturePairCount' => count($fixturePairs),
                'referencedPairCount' => count($readerCases),
                'readerCases' => $readerCases,
                'fixturePairs' => array_values($fixturePairs),
                'missingReferencedFiles' => $this->missingReferencedFiles($root, $readerCases),
                'unreferencedFixturePairs' => $this->unreferencedFixturePairs($readerCases, $fixturePairs),
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'staticCurrentEvidence' => $staticEvidence,
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-xlsx-reader-denominator' : 'invalid-upstream-xlsx-reader-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-xlsx-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-xlsx-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-xlsx-fixture-byte-count-mismatch';
            }
        }

        return [
            'kind' => 'static-checked-in-current-upstream-xlsx-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/xlsx-reader',
                'readerTestModule' => 'test/Tests/Readers/Xlsx.hs',
            ],
            'readerDenominator' => [
                'expectedCompareCount' => self::EXPECTED_STATIC_FIXTURE_PAIR_COUNT,
                'fixturePairCount' => self::EXPECTED_STATIC_FIXTURE_PAIR_COUNT,
                'fixtureScope' => 'checked-in upstream XLSX/native reader golden pair',
                'expectedReaderCases' => [
                    [
                        'name' => 'xlsx',
                        'xlsx' => 'xlsx-reader/basic.xlsx',
                        'native' => 'xlsx-reader/basic.native',
                    ],
                ],
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-xlsx-reader-evidence' : 'invalid-checked-in-current-xlsx-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the checked-in upstream XLSX reader golden pair to SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in basic.xlsx and basic.native snapshots match the pinned upstream reader fixture hashes',
                    'the checked-in upstream XLSX reader fixture corpus has one XLSX/native golden pair',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that local PHP output matches upstream native output beyond the separate native AST comparator',
                    'full Excel feature parity beyond Pandoc reader behavior',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return implode(PHP_EOL, [
            'Pandoc XLSX reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Reader test comparisons: ' . (int) ($denominator['readerTestCompareCount'] ?? 0),
            'Fixture pairs: ' . (int) ($denominator['fixturePairCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result or full Excel feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReaderTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['readerTestCompareCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixturePairCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixturePairCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-xlsx-reader-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-xlsx-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($staticEvidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURES);
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.Xlsx test module and test/xlsx-reader fixture directory to establish the current XLSX reader golden-test denominator.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file paths of upstream XLSX reader golden comparisons in Tests.Readers.Xlsx',
                'that every referenced XLSX/native fixture file exists in the pinned sparse upstream checkout',
                'that root-level test/xlsx-reader XLSX/native fixture pairs are accounted for',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches upstream native output',
                'XLSX writer parity',
                'full Excel feature parity beyond Pandoc reader behavior',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'readerTestCompareCount' => 0,
            'fixturePairCount' => 0,
            'referencedPairCount' => 0,
            'readerCases' => [],
            'fixturePairs' => [],
            'missingReferencedFiles' => [],
            'unreferencedFixturePairs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return list<array{name: string, xlsx: string, native: string, pairKey: string}>
     */
    private function parseReaderCases(string $source): array
    {
        $cases = [];
        $pattern = '/\btestCompare(?:WithOpts\s+\S+)?\s+"([^"]+)"\s+"([^"]+\.xlsx)"\s+"([^"]+\.native)"/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $xlsx = (string) $match[2];
                $native = (string) $match[3];
                $cases[] = [
                    'name' => (string) $match[1],
                    'xlsx' => $xlsx,
                    'native' => $native,
                    'pairKey' => self::pairKey($xlsx, $native),
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{xlsx: string, native: string, pairKey: string}>
     */
    private function fixturePairs(string $fixtureDirectory): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $xlsxByStem = [];
        foreach (glob($fixtureDirectory . '/*.xlsx') ?: [] as $path) {
            $stem = basename($path, '.xlsx');
            $xlsxByStem[$stem] = 'xlsx-reader/' . basename($path);
        }

        $nativeByStem = [];
        foreach (glob($fixtureDirectory . '/*.native') ?: [] as $path) {
            $stem = basename($path, '.native');
            $nativeByStem[$stem] = 'xlsx-reader/' . basename($path);
        }

        $pairs = [];
        foreach (array_intersect(array_keys($xlsxByStem), array_keys($nativeByStem)) as $stem) {
            $xlsx = $xlsxByStem[$stem];
            $native = $nativeByStem[$stem];
            $pairs[$stem] = [
                'xlsx' => $xlsx,
                'native' => $native,
                'pairKey' => self::pairKey($xlsx, $native),
            ];
        }
        ksort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @param list<array{name: string, xlsx: string, native: string, pairKey: string}> $readerCases
     * @return list<array{case: string, path: string}>
     */
    private function missingReferencedFiles(string $root, array $readerCases): array
    {
        $missing = [];
        foreach ($readerCases as $case) {
            foreach (['xlsx', 'native'] as $kind) {
                $relative = 'test/' . $case[$kind];
                if (!is_file($root . '/' . $relative)) {
                    $missing[] = [
                        'case' => $case['name'],
                        'path' => $relative,
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * @param list<array{name: string, xlsx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{xlsx: string, native: string, pairKey: string}> $fixturePairs
     * @return list<array{xlsx: string, native: string, pairKey: string}>
     */
    private function unreferencedFixturePairs(array $readerCases, array $fixturePairs): array
    {
        $referenced = [];
        foreach ($readerCases as $case) {
            $referenced[$case['pairKey']] = true;
        }

        $unreferenced = [];
        foreach ($fixturePairs as $pair) {
            if (!isset($referenced[$pair['pairKey']])) {
                $unreferenced[] = $pair;
            }
        }

        return $unreferenced;
    }

    /**
     * @param list<array{name: string, xlsx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{xlsx: string, native: string, pairKey: string}> $fixturePairs
     * @return list<string>
     */
    private function validationIssues(string $root, array $readerCases, array $fixturePairs): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/Xlsx.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!is_dir($root . '/test/xlsx-reader')) {
            $issues[] = 'missing-xlsx-reader-fixture-directory';
        }
        if ($readerCases === []) {
            $issues[] = 'no-reader-test-comparisons-found';
        }
        if ($fixturePairs === []) {
            $issues[] = 'no-xlsx-native-fixture-pairs-found';
        }
        if ($this->missingReferencedFiles($root, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }
        if ($this->unreferencedFixturePairs($readerCases, $fixturePairs) !== []) {
            $issues[] = 'unreferenced-fixture-pairs';
        }
        if (count($readerCases) !== count($fixturePairs)) {
            $issues[] = 'reader-test-count-does-not-match-fixture-pair-count';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $relativeFiles = [
            'test/Tests/Readers/Xlsx.hs',
            'src/Text/Pandoc/Readers/Xlsx.hs',
            'src/Text/Pandoc/Readers/Xlsx/Parse.hs',
            'src/Text/Pandoc/Readers/Xlsx/Sheets.hs',
            'src/Text/Pandoc/Readers/Xlsx/Cells.hs',
        ];
        $files = [];
        $presentCount = 0;
        $lineCount = 0;
        foreach ($relativeFiles as $relative) {
            $path = $root . '/' . $relative;
            $present = is_file($path);
            $lines = $present ? count(file($path) ?: []) : 0;
            $files[] = [
                'path' => $relative,
                'present' => $present,
                'lines' => $lines,
            ];
            if ($present) {
                ++$presentCount;
                $lineCount += $lines;
            }
        }

        return [
            'files' => $files,
            'presentFileCount' => $presentCount,
            'missingFileCount' => count($relativeFiles) - $presentCount,
            'presentLineCount' => $lineCount,
        ];
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function displayPath(string $path): string
    {
        $prefix = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        if (!is_dir($root . '/.git')) {
            return null;
        }

        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !is_string($output[0] ?? null)) {
            return null;
        }

        return trim($output[0]);
    }

    private static function pairKey(string $xlsx, string $native): string
    {
        return $xlsx . '|' . $native;
    }
}
