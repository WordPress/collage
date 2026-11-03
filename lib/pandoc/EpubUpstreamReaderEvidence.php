<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-epub-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-epub-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-epub-root';

    private const CURRENT_READER_STATIC_SIGNATURE_KIND = 'checked-in-current-epub-reader-static-signature';
    private const CURRENT_READER_STATIC_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_READER_STATIC_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-static-7-case-media-expectation-and-fixture-identity-snapshot';
    private const CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256 = '5cc23a2823aac0b77e2e3150da0761011927f93fa34094e490ca137280e5342d';
    private const CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT = 7;
    private const CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT = 7;
    private const CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT = 11;
    private const CHECKED_IN_CURRENT_READER_FIXTURE_ROOT = 'lanes/pandoc/fixtures/upstream-current-epub-reader';
    private const CHECKED_IN_CURRENT_NATIVE_PACKAGE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
    private const CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT = 76;
    private const CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT = 76;
    private const EXECUTABLE_NATIVE_AST_PARITY_KIND = 'checked-in-current-epub-pandoc-executable-native-ast-parity';
    private const REFERENCED_FIXTURE_IDENTITY_KIND = 'checked-in-current-epub-reader-referenced-fixture-identity';
    private const REFERENCED_FIXTURE_IDENTITY_SCOPE = 'checked-in-current-upstream-epub-reader-7-referenced-epub-fixture-snapshot';
    private const REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM = 'sha256';
    private const CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES = array (
  'epub/encoded-image-media-bag.epub' =>
  array (
    'sha256' => '38642e1a951ac69c1f8ff3874e2cb7d9b752310406efd42bb0109a6fd4c0f329',
    'bytes' => 1302,
  ),
  'epub/epub2_cover.epub' =>
  array (
    'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
    'bytes' => 11794,
  ),
  'epub/epub2_no_cover.epub' =>
  array (
    'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
    'bytes' => 3584,
  ),
  'epub/epub2_picture.epub' =>
  array (
    'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
    'bytes' => 11742,
  ),
  'epub/img.epub' =>
  array (
    'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
    'bytes' => 20478,
  ),
  'epub/img_no_cover.epub' =>
  array (
    'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
    'bytes' => 10602,
  ),
  'epub/wasteland.epub' =>
  array (
    'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
    'bytes' => 25840,
  ),
);
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/epub-targeted-run';
    private const RUNNER_TASTY_GROUP_PATH = ['Readers', 'EPUB', 'EPUB Mediabag'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/epub-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/epub-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/epub-targeted-run/result.json',
    ];
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-epub-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-epub-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?string $fixtureBase;
    private readonly ?string $runnerResultArtifact;
    private readonly ?string $pandocBin;
    private readonly bool $includeExecutableNativeAstParity;

    public function __construct(
        string $repoRoot,
        string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        ?string $fixtureBase = null,
        ?string $runnerResultArtifact = null,
        ?string $pandocBin = null,
        bool $includeExecutableNativeAstParity = false
    ) {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }
        if ($fixtureBase === '') {
            throw new \InvalidArgumentException('Fixture base must not be empty');
        }
        if ($runnerResultArtifact === '') {
            throw new \InvalidArgumentException('Runner result artifact must not be empty');
        }
        if ($pandocBin === '') {
            throw new \InvalidArgumentException('Pandoc executable path must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
        $this->fixtureBase = $fixtureBase;
        $this->runnerResultArtifact = $runnerResultArtifact;
        $this->pandocBin = $pandocBin;
        $this->includeExecutableNativeAstParity = $includeExecutableNativeAstParity || $pandocBin !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        if (!is_dir($root)) {
            $denominator = $this->emptyDenominator();

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
                'denominator' => $denominator,
                'sourceInventory' => $this->emptySourceInventory(),
                'referencedFixtureIdentity' => self::notEvaluatedReferencedFixtureIdentity('missing-upstream-root'),
                'currentReaderStaticSignature' => self::notEvaluatedCurrentReaderStaticSignature('missing-upstream-root'),
                'nativeAstPackageParity' => $this->currentNativeAstPackageParity(),
                'executableNativeAstParity' => $this->currentExecutableNativeAstParity(),
                'runnerEvidence' => $this->runnerEvidence($denominator, false),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/EPUB.hs';
        $fixtureRoot = $this->absoluteFixtureBase() ?? $root;
        $readerCases = is_file($readerTestPath)
            ? self::parseReaderCasesFromSource((string) file_get_contents($readerTestPath))
            : [];
        $validationIssues = $this->validationIssues($root, $fixtureRoot, $readerCases);
        $denominator = [
            'mediaBagTestCount' => count($readerCases),
            'fixtureReferenceCount' => count($this->fixtureReferences($readerCases)),
            'expectedMediaItemCount' => $this->expectedMediaItemCount($readerCases),
            'readerCases' => $readerCases,
            'referencedFixtures' => $this->fixtureReferences($readerCases),
            'missingReferencedFiles' => $this->missingReferencedFiles($root, $fixtureRoot, $readerCases),
            'unreferencedEpubFixtures' => $this->unreferencedEpubFixtures($root, $fixtureRoot, $readerCases),
        ];
        $referencedFixtureIdentity = $this->referencedFixtureIdentity($root, $fixtureRoot, $readerCases);
        $checkedInCurrentFixtureSnapshot = $this->usesCheckedInCurrentFixtureSnapshot($root, $fixtureRoot);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $checkedInCurrentFixtureSnapshot ? self::EXPECTED_UPSTREAM_COMMIT : $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'commitSource' => $checkedInCurrentFixtureSnapshot ? 'checked-in-current-fixture-snapshot' : 'git-head',
                'provenanceMode' => $checkedInCurrentFixtureSnapshot ? 'checked-in-current-fixture-snapshot' : 'hydrated-upstream-checkout',
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
                'fixtureDirectory' => 'test/epub',
                'resolvedFixtureBase' => $this->displayPath($fixtureRoot),
                'resolvedFixtureDirectory' => $this->fixtureDirectoryDisplayPath(),
                'readerSource' => 'src/Text/Pandoc/Readers/EPUB.hs',
                'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
            ],
            'denominator' => $denominator,
            'sourceInventory' => $this->sourceInventory($root),
            'referencedFixtureIdentity' => $referencedFixtureIdentity,
            'currentReaderStaticSignature' => self::currentReaderStaticSignature($denominator, $validationIssues, $referencedFixtureIdentity),
            'nativeAstPackageParity' => $this->currentNativeAstPackageParity(),
            'executableNativeAstParity' => $this->currentExecutableNativeAstParity(),
            'runnerEvidence' => $this->runnerEvidence($denominator, $validationIssues === []),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-epub-reader-mediabag-denominator' : 'invalid-upstream-epub-reader-mediabag-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return list<array{name: string, epub: string, bagName: string, expectedBag: list<array{path: string, mime: string, size: int}>, expectedBagItemCount: int, expectedBagMissing: bool}>
     */
    public static function parseReaderCasesFromSource(string $source): array
    {
        $bagDefinitions = self::parseBagDefinitions($source);
        $cases = [];
        $pattern = '/\btestCase\s+"([^"]+)"\s*\(\s*testMediaBag\s+"([^"]+\.epub)"\s+([A-Za-z_][A-Za-z0-9_\']*)\s*\)/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $bagName = (string) $match[3];
            $expectedBag = $bagDefinitions[$bagName] ?? [];
            $cases[] = [
                'name' => (string) $match[1],
                'epub' => (string) $match[2],
                'bagName' => $bagName,
                'expectedBag' => $expectedBag,
                'expectedBagItemCount' => count($expectedBag),
                'expectedBagMissing' => !array_key_exists($bagName, $bagDefinitions),
            ];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $signature = is_array($report['currentReaderStaticSignature'] ?? null) ? $report['currentReaderStaticSignature'] : [];
        $signatureValidation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
        $nativePackage = is_array($report['nativeAstPackageParity'] ?? null) ? $report['nativeAstPackageParity'] : [];
        $executableParity = is_array($report['executableNativeAstParity'] ?? null) ? $report['executableNativeAstParity'] : [];
        $identity = is_array($report['referencedFixtureIdentity'] ?? null) ? $report['referencedFixtureIdentity'] : [];
        $identityValidation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];
        $runnerResultLine = self::hasRunnerResultArtifactEvidence($report)
            ? 'Supplied upstream Haskell/Cabal runner result artifact is validated; EPUB writer parity is not asserted.'
            : 'No upstream Haskell/Cabal runner result artifact is supplied; runtime runner execution is not asserted.';

        return implode(PHP_EOL, [
            'Pandoc EPUB reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Media bag tests: ' . (int) ($denominator['mediaBagTestCount'] ?? 0),
            'Referenced EPUB fixtures: ' . (int) ($denominator['fixtureReferenceCount'] ?? 0),
            'Expected media items: ' . (int) ($denominator['expectedMediaItemCount'] ?? 0),
            'Referenced fixture identity: ' . (string) ($identityValidation['status'] ?? 'unknown'),
            'Static current signature: ' . (string) ($signatureValidation['status'] ?? 'unknown'),
            'Native/package parity: package=' . (int) ($nativePackage['packageParsedCount'] ?? 0)
                . '/' . (int) ($nativePackage['requiredEpubCount'] ?? self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT)
                . ' nativeAst=' . (int) ($nativePackage['normalizedAstMatchCount'] ?? 0)
                . '/' . (int) ($nativePackage['requiredPairCount'] ?? self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT)
                . ' status=' . (string) ($nativePackage['astParityStatus'] ?? 'unknown'),
            'Executable/native parity: localPandoc=' . (int) ($executableParity['normalizedAstMatchCount'] ?? 0)
                . '/' . (int) ($executableParity['requiredEpubCount'] ?? self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT)
                . ' checkedNative=' . (int) ($executableParity['pandocNativeFixtureMatchCount'] ?? 0)
                . '/' . (int) ($executableParity['requiredEpubCount'] ?? self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT)
                . ' status=' . (string) ($executableParity['astParityStatus'] ?? 'unknown')
                . ' version=' . (string) (($executableParity['pandocVersion'] ?? null) ?? 'not-evaluated'),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Runner plan: ' . (string) ($runner['commandPlanStatus'] ?? 'unknown'),
            'Runner result artifact: ' . (string) (($runner['validation']['status'] ?? null) ?? 'not-evaluated'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            $runnerResultLine,
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['mediaBagTestCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureReferenceCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixtureReferenceCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredExpectedMediaItemCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['expectedMediaItemCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-epub-reader-mediabag-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerPlanEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];

        return self::hasRunnerNotRunEvidence($report)
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['entryPoint'] ?? null) === 'test/test-pandoc.hs'
            && ($binding['readerTestModule'] ?? null) === 'test/Tests/Readers/EPUB.hs'
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerResultArtifactEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $artifact = is_array($runner['resultArtifact'] ?? null) ? $runner['resultArtifact'] : [];
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === 'Cabal/Tasty Pandoc EPUB reader suite'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['observedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($validation['status'] ?? null) === 'valid-upstream-epub-reader-runner-result-artifact'
            && ($validation['issues'] ?? null) === []
            && self::hasValidRunnerTranscriptEvidence($transcripts);
    }

    /**
     * @param list<mixed> $transcripts
     */
    private static function hasValidRunnerTranscriptEvidence(array $transcripts): bool
    {
        if (count($transcripts) !== count(self::RUNNER_REQUIRED_TRANSCRIPTS)) {
            return false;
        }

        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $index => $path) {
            $transcript = $transcripts[$index] ?? null;
            if (!is_array($transcript)) {
                return false;
            }
            if (($transcript['kind'] ?? null) !== self::RUNNER_TRANSCRIPT_KIND) {
                return false;
            }
            if (($transcript['path'] ?? null) !== $path) {
                return false;
            }
            if (($transcript['present'] ?? null) !== true) {
                return false;
            }
            if (!is_string($transcript['sha256'] ?? null) || !is_int($transcript['bytes'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentSignature(array $report): bool
    {
        $signature = is_array($report['currentReaderStaticSignature'] ?? null) ? $report['currentReaderStaticSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['status'] ?? null) === self::STATUS_COMPLETED
            && self::hasNoValidationIssues($report)
            && (self::hasRunnerNotRunEvidence($report) || self::hasRunnerResultArtifactEvidence($report))
            && self::hasRequiredReferencedFixtureIdentity($report)
            && (int) ($signature['mediaBagTestCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT
            && (int) ($signature['fixtureReferenceCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT
            && (int) ($signature['expectedMediaItemCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT
            && ($signature['kind'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-reader-static-signature'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeAstPackageParity(array $report): bool
    {
        $parity = is_array($report['nativeAstPackageParity'] ?? null) ? $report['nativeAstPackageParity'] : [];

        return ($parity['hasRequiredPackageParity'] ?? null) === true
            && ($parity['hasRequiredNativeReadiness'] ?? null) === true
            && ($parity['hasRequiredMappedParity'] ?? null) === true
            && ($parity['hasRequiredFixtureIdentity'] ?? null) === true
            && ($parity['hasRequiredCurrentPackageFeatureCoverage'] ?? null) === true
            && ($parity['hasRequiredCurrentPackageFeatureSignature'] ?? null) === true
            && ($parity['hasRequiredCurrentNativeAstSignature'] ?? null) === true
            && ($parity['hasRunnerPlanEvidence'] ?? null) === true
            && (int) ($parity['requiredEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['requiredPairCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            && (int) ($parity['totalEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['comparedEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['packageParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['readerParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['packageParseFailureCount'] ?? -1) === 0
            && (int) ($parity['readerParseFailureCount'] ?? -1) === 0
            && (int) ($parity['totalPairCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            && (int) ($parity['comparedPairCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            && (int) ($parity['bothParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            && (int) ($parity['astParseFailureCount'] ?? -1) === 0
            && (int) ($parity['nativeParseFailureCount'] ?? -1) === 0
            && (int) ($parity['normalizedAstMatchCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            && (int) ($parity['normalizedAstMismatchCount'] ?? -1) === 0
            && ($parity['packageAcceptanceStatus'] ?? null) === 'package-and-reader-implementation-equivalence-observed'
            && ($parity['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed'
            && ($parity['fixtureIdentityStatus'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($parity['packageFeatureSignatureStatus'] ?? null) === 'valid-checked-in-current-epub-package-feature-signature'
            && ($parity['currentNativeAstSignatureStatus'] ?? null) === 'valid-checked-in-current-epub-normalized-native-ast-signature';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredExecutableNativeAstParity(array $report, ?string $requiredPandocVersion = null): bool
    {
        if ($requiredPandocVersion === '') {
            throw new \InvalidArgumentException('Required Pandoc version must not be empty');
        }

        $parity = is_array($report['executableNativeAstParity'] ?? null) ? $report['executableNativeAstParity'] : [];
        $hasExecutableParity = ($parity['hasRequiredExecutableParity'] ?? null) === true
            && (int) ($parity['requiredEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['totalEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['comparedEpubCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['localParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['pandocParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['nativeFixtureParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['bothParsedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['parseFailureCount'] ?? -1) === 0
            && (int) ($parity['normalizedAstMatchCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['normalizedAstMismatchCount'] ?? -1) === 0
            && (int) ($parity['pandocNativeFixtureComparedCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['pandocNativeFixtureMatchCount'] ?? -1) === self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            && (int) ($parity['pandocNativeFixtureMismatchCount'] ?? -1) === 0
            && ($parity['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-against-pandoc-executable';

        if (!$hasExecutableParity) {
            return false;
        }

        return $requiredPandocVersion === null
            || ($parity['pandocVersion'] ?? null) === $requiredPandocVersion;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReferencedFixtureIdentity(array $report): bool
    {
        $identity = is_array($report['referencedFixtureIdentity'] ?? null) ? $report['referencedFixtureIdentity'] : [];
        $validation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return ($report['status'] ?? null) === self::STATUS_COMPLETED
            && self::hasNoValidationIssues($report)
            && ($identity['kind'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_KIND
            && ($identity['scope'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_SCOPE
            && ($identity['hashAlgorithm'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM
            && (int) ($identity['expectedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['observedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['presentFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['missingFileCount'] ?? -1) === 0
            && ($identity['expectedPaths'] ?? null) === array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && ($identity['observedPaths'] ?? null) === array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && ($identity['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
            && ($validation['issues'] ?? null) === [];
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.EPUB module to establish the current EPUB reader media-bag test denominator and expected media directory tuples.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and fixture paths of upstream EPUB media-bag tests in Tests.Readers.EPUB',
                'the expected media-bag path, MIME type, and byte-size tuples embedded in the upstream test module',
                'that every referenced EPUB fixture exists in the upstream checkout or explicit checked-in fixture base',
                'that the upstream EPUB reader source file is present when validating a full upstream checkout without an explicit checked-in fixture base',
                'the checked-in current referenced EPUB fixture SHA-256 and byte identity snapshot when explicitly gated',
                'the checked-in current static EPUB reader denominator and referenced fixture identity signature when explicitly gated',
                'the checked-in current EPUB package acceptance, package-feature signature, and normalized native AST parity snapshot when explicitly gated',
                'the checked-in current EPUB local pandoc executable/native AST parity snapshot when explicitly requested and gated',
                'that upstream Haskell runner evidence is explicitly not-run',
                'the future upstream runner command plan targets test:test-pandoc Readers/EPUB/EPUB Mediabag at the pinned upstream commit without execution',
                'a supplied upstream runner result artifact is validated against the pinned EPUB Tasty target, commit, test names, pass/fail counts, and transcript file identities when explicitly provided',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'local pandoc executable evaluation unless explicitly requested or a pandoc binary is supplied',
                'EPUB writer parity',
                'byte-for-byte identity with Pandoc Haskell reader internals',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runnerEvidence(array $denominator, bool $denominatorIsValid): array
    {
        if ($this->runnerResultArtifact === null) {
            return self::runnerNotRunEvidence();
        }

        return $this->runnerResultArtifactEvidence($denominator, $denominatorIsValid);
    }

    /**
     * @param array<string, mixed> $denominator
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(array $denominator, bool $denominatorIsValid): array
    {
        $path = $this->absoluteRunnerResultArtifact();
        $artifact = $this->runnerResultArtifactFileEvidence($path);
        $transcripts = $this->runnerTranscriptFileEvidenceList();
        $issues = [];
        $payload = [];

        if (($artifact['present'] ?? false) !== true) {
            $issues[] = 'missing-runner-result-artifact';
        } else {
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    $issues[] = 'invalid-runner-result-artifact-json';
                } else {
                    $payload = $decoded;
                }
            } catch (\JsonException) {
                $issues[] = 'invalid-runner-result-artifact-json';
            }
        }

        $upstream = is_array($payload['upstream'] ?? null) ? $payload['upstream'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $command = is_array($payload['command'] ?? null) ? $payload['command'] : null;
        $expectedCommand = self::runnerFutureCommands()[2];
        $expectedTestNames = self::readerCaseNames($denominator);
        $observedTestNames = self::stringList($payload['testNames'] ?? ($payload['listedTests'] ?? []));
        $observedTranscriptPaths = self::stringList($payload['transcriptPaths'] ?? []);
        $observedTranscriptRecords = self::runnerTranscriptRecords($payload['transcripts'] ?? []);
        if ($observedTranscriptPaths === [] && $observedTranscriptRecords !== []) {
            $observedTranscriptPaths = self::runnerTranscriptRecordPaths($observedTranscriptRecords);
        }
        $runnerExecuted = ($payload['runnerExecuted'] ?? $payload['executed'] ?? null) === true;
        $exitCode = is_int($payload['exitCode'] ?? null) ? (int) $payload['exitCode'] : null;
        $testCount = is_int($payload['testCount'] ?? null) ? (int) $payload['testCount'] : null;
        $passedCount = is_int($payload['passedCount'] ?? null) ? (int) $payload['passedCount'] : null;
        $failedCount = is_int($payload['failedCount'] ?? null) ? (int) $payload['failedCount'] : null;
        $skippedCount = is_int($payload['skippedCount'] ?? null) ? (int) $payload['skippedCount'] : null;

        if ($payload !== []) {
            if (!$denominatorIsValid) {
                $issues[] = 'runner-result-denominator-invalid';
            }
            if ($expectedTestNames === []) {
                $issues[] = 'runner-result-denominator-empty';
            }
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== 'Cabal/Tasty Pandoc EPUB reader suite') {
                $issues[] = 'runner-result-runner-name-mismatch';
            }
            if (!$runnerExecuted) {
                $issues[] = 'runner-result-executed-flag-missing-or-false';
            }
            if (($upstream['name'] ?? null) !== 'jgm/pandoc' || ($upstream['commit'] ?? null) !== self::EXPECTED_UPSTREAM_COMMIT) {
                $issues[] = 'runner-result-upstream-commit-mismatch';
            }
            if (
                ($target['testSuite'] ?? null) !== self::RUNNER_TEST_SUITE
                || ($target['tastyGroupPath'] ?? null) !== self::RUNNER_TASTY_GROUP_PATH
                || ($target['tastyPattern'] ?? null) !== self::RUNNER_TASTY_PATTERN
            ) {
                $issues[] = 'runner-result-target-mismatch';
            }
            if ($command !== $expectedCommand) {
                $issues[] = 'runner-result-command-mismatch';
            }
            if ($exitCode !== 0) {
                $issues[] = 'runner-result-exit-code-nonzero';
            }
            if (
                $testCount !== count($expectedTestNames)
                || $passedCount !== count($expectedTestNames)
                || $failedCount !== 0
                || $skippedCount !== 0
            ) {
                $issues[] = 'runner-result-counts-mismatch';
            }
            if ($observedTestNames !== $expectedTestNames) {
                $issues[] = 'runner-result-test-names-mismatch';
            }
            if ($observedTranscriptPaths !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
                $issues[] = 'runner-result-transcript-paths-mismatch';
            }
            foreach (self::runnerTranscriptValidationIssues($observedTranscriptRecords, $transcripts) as $issue) {
                $issues[] = $issue;
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => $issues === [] ? 'completed' : 'invalid',
            'executed' => $runnerExecuted,
            'command' => $command,
            'resultArtifact' => $artifact,
            'commandPlanStatus' => $issues === [] ? 'runner-result-artifact-validated' : 'runner-result-artifact-invalid',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'observedCommit' => is_string($upstream['commit'] ?? null) ? $upstream['commit'] : null,
                'entryPoint' => 'test/test-pandoc.hs',
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
            ],
            'target' => [
                'testSuite' => is_string($target['testSuite'] ?? null) ? $target['testSuite'] : null,
                'tastyGroupPath' => is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : null,
                'tastyPattern' => is_string($target['tastyPattern'] ?? null) ? $target['tastyPattern'] : null,
            ],
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
                'testCount' => count($expectedTestNames),
                'passedCount' => count($expectedTestNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $expectedTestNames,
                'transcriptPaths' => self::RUNNER_REQUIRED_TRANSCRIPTS,
                'transcripts' => self::runnerTranscriptRecordsFromEvidence($transcripts),
                'command' => $expectedCommand,
            ],
            'observed' => [
                'schemaVersion' => $payload['schemaVersion'] ?? null,
                'runner' => $payload['runner'] ?? null,
                'exitCode' => $exitCode,
                'testCount' => $testCount,
                'passedCount' => $passedCount,
                'failedCount' => $failedCount,
                'skippedCount' => $skippedCount,
                'testNames' => $observedTestNames,
                'transcriptPaths' => $observedTranscriptPaths,
                'transcripts' => $observedTranscriptRecords,
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'transcripts' => $transcripts,
            'validation' => [
                'status' => $issues === []
                    ? 'valid-upstream-epub-reader-runner-result-artifact'
                    : 'invalid-upstream-epub-reader-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream EPUB reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream EPUB reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
        ];
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private function runnerResultArtifactFileEvidence(string $path): array
    {
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_RESULT_ARTIFACT_KIND,
            'path' => $this->displayPath($path),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    /**
     * @return list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}>
     */
    private function runnerTranscriptFileEvidenceList(): array
    {
        $files = [];
        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $files[] = $this->runnerTranscriptFileEvidence($path);
        }

        return $files;
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private function runnerTranscriptFileEvidence(string $relativePath): array
    {
        $path = $this->absoluteRunnerTranscriptPath($relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_TRANSCRIPT_KIND,
            'path' => $this->displayPath($path),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    private function absoluteRunnerTranscriptPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $records[] = [
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'sha256' => is_string($item['sha256'] ?? null) ? $item['sha256'] : null,
                'bytes' => is_int($item['bytes'] ?? null) ? $item['bytes'] : null,
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $records
     * @return list<string>
     */
    private static function runnerTranscriptRecordPaths(array $records): array
    {
        return array_map(
            static fn (array $record): string => $record['path'],
            $records
        );
    }

    /**
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecordsFromEvidence(array $files): array
    {
        $records = [];
        foreach ($files as $file) {
            $records[] = [
                'path' => $file['path'],
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $observedRecords
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<string>
     */
    private static function runnerTranscriptValidationIssues(array $observedRecords, array $files): array
    {
        $issues = [];
        if ($observedRecords === []) {
            $issues[] = 'runner-result-transcript-records-missing';
        }
        if (self::runnerTranscriptRecordPaths($observedRecords) !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
            $issues[] = 'runner-result-transcript-record-paths-mismatch';
        }

        $recordsByPath = [];
        foreach ($observedRecords as $record) {
            if (isset($recordsByPath[$record['path']])) {
                $issues[] = 'runner-result-transcript-record-paths-not-unique';
                continue;
            }
            $recordsByPath[$record['path']] = $record;
        }

        $filesByPath = [];
        foreach ($files as $file) {
            $filesByPath[$file['path']] = $file;
        }

        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $file = $filesByPath[$path] ?? null;
            if (!is_array($file) || ($file['present'] ?? null) !== true) {
                $issues[] = 'runner-result-transcript-file-missing';
                continue;
            }

            $record = $recordsByPath[$path] ?? null;
            if (!is_array($record)) {
                $issues[] = 'runner-result-transcript-record-missing';
                continue;
            }
            if (($record['sha256'] ?? null) !== $file['sha256']) {
                $issues[] = 'runner-result-transcript-sha256-mismatch';
            }
            if (($record['bytes'] ?? null) !== $file['bytes']) {
                $issues[] = 'runner-result-transcript-bytes-mismatch';
            }
        }

        return array_values(array_unique($issues));
    }

    private function absoluteRunnerResultArtifact(): string
    {
        $path = (string) $this->runnerResultArtifact;
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<string, mixed> $denominator
     * @return list<string>
     */
    private static function readerCaseNames(array $denominator): array
    {
        $cases = is_array($denominator['readerCases'] ?? null) ? $denominator['readerCases'] : [];
        $names = [];
        foreach ($cases as $case) {
            if (is_array($case) && is_string($case['name'] ?? null)) {
                $names[] = $case['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'commandPlanStatus' => 'planned-not-run',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'entryPoint' => 'test/test-pandoc.hs',
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
            ],
            'target' => [
                'testSuite' => self::RUNNER_TEST_SUITE,
                'tastyGroupPath' => self::RUNNER_TASTY_GROUP_PATH,
                'tastyPattern' => self::RUNNER_TASTY_PATTERN,
            ],
            'blockers' => [
                'no committed upstream test:test-pandoc EPUB runner transcript or result artifact is present',
                'this PHP evidence gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted EPUB Tasty pattern',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return list<array{purpose: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare runner dependencies in an isolated build directory',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--dry-run',
                    '--only-dependencies',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                ],
            ],
            [
                'purpose' => 'list targeted EPUB reader media-bag tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--list-tests',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
            ],
            [
                'purpose' => 'run targeted EPUB reader media-bag tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'mediaBagTestCount' => 0,
            'fixtureReferenceCount' => 0,
            'expectedMediaItemCount' => 0,
            'readerCases' => [],
            'referencedFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedEpubFixtures' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentNativeAstPackageParity(): array
    {
        $fixtureDirectory = $this->repoRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_FIXTURE_DIRECTORY);
        $report = (new EpubNativeAstPackageComparisonHarness())->run($fixtureDirectory);

        return self::nativeAstPackageParityEvidence($report);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentExecutableNativeAstParity(): array
    {
        if (!$this->includeExecutableNativeAstParity) {
            return self::notEvaluatedExecutableNativeAstParity('not-requested');
        }

        $fixtureDirectory = $this->repoRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_FIXTURE_DIRECTORY);
        $options = [];
        if ($this->pandocBin !== null) {
            $options['pandocBin'] = $this->pandocBin;
        }
        $report = (new EpubExecutableNativeAstComparisonHarness())->run($fixtureDirectory, $options);

        return self::executableNativeAstParityEvidence($report);
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function nativeAstPackageParityEvidence(array $report): array
    {
        $fixtureIdentity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        $packageFeatureSignature = is_array($report['packageFeatureSignature'] ?? null) ? $report['packageFeatureSignature'] : [];
        $packageFeatureSignatureValidation = is_array($packageFeatureSignature['validation'] ?? null) ? $packageFeatureSignature['validation'] : [];
        $nativeAstSignature = is_array($report['currentNativeAstSignature'] ?? null) ? $report['currentNativeAstSignature'] : [];
        $nativeAstSignatureValidation = is_array($nativeAstSignature['validation'] ?? null) ? $nativeAstSignature['validation'] : [];
        $packageFeatureCoverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return [
            'kind' => 'checked-in-current-epub-native-ast-package-parity',
            'tool' => (string) ($report['tool'] ?? 'pandoc-epub-native-ast-package'),
            'status' => (string) ($report['status'] ?? 'unknown'),
            'skipped' => (bool) ($report['skipped'] ?? false),
            'reason' => $report['reason'] ?? null,
            'evidenceKind' => (string) ($report['evidenceKind'] ?? 'epub-native-ast-package-comparison'),
            'requiredEpubCount' => self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT,
            'requiredPairCount' => self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT,
            'upstreamEpubDirectory' => (string) ($report['upstreamEpubDirectory'] ?? ''),
            'totalEpubCount' => (int) ($report['totalEpubCount'] ?? 0),
            'comparedEpubCount' => (int) ($report['comparedEpubCount'] ?? 0),
            'packageParsedCount' => (int) ($report['packageParsedCount'] ?? 0),
            'readerParsedCount' => (int) ($report['readerParsedCount'] ?? 0),
            'packageParseFailureCount' => (int) ($report['packageParseFailureCount'] ?? 0),
            'readerParseFailureCount' => (int) ($report['readerParseFailureCount'] ?? 0),
            'packageAcceptanceStatus' => (string) ($report['packageAcceptanceStatus'] ?? 'unknown'),
            'totalPairCount' => (int) ($report['totalPairCount'] ?? 0),
            'comparedPairCount' => (int) ($report['comparedPairCount'] ?? 0),
            'epubPairParsedCount' => (int) ($report['epubPairParsedCount'] ?? 0),
            'nativeParsedCount' => (int) ($report['nativeParsedCount'] ?? 0),
            'bothParsedCount' => (int) ($report['bothParsedCount'] ?? 0),
            'astParseFailureCount' => (int) ($report['astParseFailureCount'] ?? 0),
            'nativeParseFailureCount' => (int) ($report['nativeParseFailureCount'] ?? 0),
            'normalizedAstMatchCount' => (int) ($report['normalizedAstMatchCount'] ?? 0),
            'normalizedAstMismatchCount' => (int) ($report['normalizedAstMismatchCount'] ?? 0),
            'normalizedAstMatchPercent' => $report['normalizedAstMatchPercent'] ?? null,
            'astParityStatus' => (string) ($report['astParityStatus'] ?? 'unknown'),
            'fixtureIdentityStatus' => (string) ($fixtureValidation['status'] ?? 'unknown'),
            'fixtureIdentityExpectedFileCount' => (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
            'fixtureIdentityObservedFileCount' => (int) ($fixtureIdentity['observedFileCount'] ?? 0),
            'packageFeatureCoverageFixtureCount' => (int) ($packageFeatureCoverage['fixtureCount'] ?? 0),
            'packageFeatureSignatureStatus' => (string) ($packageFeatureSignatureValidation['status'] ?? 'unknown'),
            'packageFeatureSignatureSha256' => $packageFeatureSignature['sha256'] ?? null,
            'packageFeatureSignatureExpectedSha256' => $packageFeatureSignature['expectedSha256'] ?? null,
            'currentNativeAstSignatureStatus' => (string) ($nativeAstSignatureValidation['status'] ?? 'unknown'),
            'currentNativeAstSignatureSha256' => $nativeAstSignature['sha256'] ?? null,
            'currentNativeAstSignatureExpectedSha256' => $nativeAstSignature['expectedSha256'] ?? null,
            'runnerStatus' => (string) ($runner['status'] ?? 'unknown'),
            'runnerPlanStatus' => (string) ($runner['commandPlanStatus'] ?? 'unknown'),
            'hasRequiredPackageParity' => EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity(
                $report,
                self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            ),
            'hasRequiredNativeReadiness' => EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness(
                $report,
                self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            ),
            'hasRequiredMappedParity' => EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity(
                $report,
                self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_PAIR_COUNT
            ),
            'hasRequiredFixtureIdentity' => EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report),
            'hasRequiredCurrentPackageFeatureCoverage' => EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureCoverage($report),
            'hasRequiredCurrentPackageFeatureSignature' => EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($report),
            'hasRequiredCurrentNativeAstSignature' => EpubNativeAstPackageComparisonHarness::hasRequiredCurrentNativeAstSignature($report),
            'hasRunnerPlanEvidence' => EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($report),
            'mismatchCategories' => is_array($report['mismatchCategories'] ?? null) ? $report['mismatchCategories'] : [],
            'orderedRemainingGaps' => is_array($report['orderedRemainingGaps'] ?? null) ? $report['orderedRemainingGaps'] : [],
            'claim' => (string) ($report['claim'] ?? 'Checked-in current EPUB native/package comparison.'),
            'claimBoundaries' => [
                'doesAssert' => [
                    'checked-in current EPUB packages parse through package and reader paths',
                    'checked-in current EPUB/native fixtures are equal after documented native AST normalization',
                    'checked-in current EPUB package feature and normalized native AST signatures match expected snapshots',
                ],
                'doesNotAssert' => [
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'EPUB writer parity',
                    'byte-level EPUB writer package equality',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function executableNativeAstParityEvidence(array $report): array
    {
        return [
            'kind' => self::EXECUTABLE_NATIVE_AST_PARITY_KIND,
            'tool' => (string) ($report['tool'] ?? 'pandoc-epub-executable-native-ast'),
            'status' => (string) ($report['status'] ?? 'unknown'),
            'skipped' => (bool) ($report['skipped'] ?? false),
            'reason' => $report['reason'] ?? null,
            'evidenceKind' => (string) ($report['evidenceKind'] ?? 'epub-pandoc-executable-normalized-ast-comparison'),
            'requiredEpubCount' => self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT,
            'epubDirectory' => (string) ($report['epubDirectory'] ?? ''),
            'pandocExecutable' => is_string($report['pandocExecutable'] ?? null) ? $report['pandocExecutable'] : null,
            'pandocVersion' => is_string($report['pandocVersion'] ?? null) ? $report['pandocVersion'] : null,
            'totalEpubCount' => (int) ($report['totalEpubCount'] ?? 0),
            'comparedEpubCount' => (int) ($report['comparedEpubCount'] ?? 0),
            'localParsedCount' => (int) ($report['localParsedCount'] ?? 0),
            'pandocParsedCount' => (int) ($report['pandocParsedCount'] ?? 0),
            'nativeFixtureParsedCount' => (int) ($report['nativeFixtureParsedCount'] ?? 0),
            'bothParsedCount' => (int) ($report['bothParsedCount'] ?? 0),
            'parseFailureCount' => (int) ($report['parseFailureCount'] ?? 0),
            'normalizedAstMatchCount' => (int) ($report['normalizedAstMatchCount'] ?? 0),
            'normalizedAstMismatchCount' => (int) ($report['normalizedAstMismatchCount'] ?? 0),
            'normalizedAstMatchPercent' => $report['normalizedAstMatchPercent'] ?? null,
            'pandocNativeFixtureComparedCount' => (int) ($report['pandocNativeFixtureComparedCount'] ?? 0),
            'pandocNativeFixtureMatchCount' => (int) ($report['pandocNativeFixtureMatchCount'] ?? 0),
            'pandocNativeFixtureMismatchCount' => (int) ($report['pandocNativeFixtureMismatchCount'] ?? 0),
            'pandocNativeFixtureMatchPercent' => $report['pandocNativeFixtureMatchPercent'] ?? null,
            'pandocNativeFixtureByteComparedCount' => (int) ($report['pandocNativeFixtureByteComparedCount'] ?? 0),
            'pandocNativeFixtureByteMatchCount' => (int) ($report['pandocNativeFixtureByteMatchCount'] ?? 0),
            'pandocNativeFixtureByteMismatchCount' => (int) ($report['pandocNativeFixtureByteMismatchCount'] ?? 0),
            'pandocNativeFixtureByteMatchPercent' => $report['pandocNativeFixtureByteMatchPercent'] ?? null,
            'astParityStatus' => (string) ($report['astParityStatus'] ?? 'unknown'),
            'hasRequiredExecutableParity' => EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity(
                $report,
                self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT
            ),
            'mismatchCategories' => is_array($report['mismatchCategories'] ?? null) ? $report['mismatchCategories'] : [],
            'orderedRemainingGaps' => is_array($report['orderedRemainingGaps'] ?? null) ? $report['orderedRemainingGaps'] : [],
            'byteMismatchExamples' => is_array($report['pandocNativeFixtureByteMismatchComparisons'] ?? null)
                ? $report['pandocNativeFixtureByteMismatchComparisons']
                : [],
            'claim' => (string) ($report['claim'] ?? 'Checked-in current EPUB pandoc executable/native AST comparison.'),
            'claimBoundaries' => [
                'doesAssert' => [
                    'checked-in current EPUB packages parse through the local PHP reader and a local pandoc executable',
                    'local PHP EPUB reader output and local pandoc executable native output are equal after documented native AST normalization',
                    'local pandoc executable native output and checked-in current .native fixtures are equal after documented native AST normalization',
                ],
                'doesNotAssert' => [
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'byte-level native writer formatting equality',
                    'EPUB writer parity',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedExecutableNativeAstParity(string $reason): array
    {
        return [
            'kind' => self::EXECUTABLE_NATIVE_AST_PARITY_KIND,
            'tool' => 'pandoc-epub-executable-native-ast',
            'status' => 'not-evaluated',
            'skipped' => true,
            'reason' => $reason,
            'evidenceKind' => 'epub-pandoc-executable-normalized-ast-comparison',
            'requiredEpubCount' => self::CHECKED_IN_CURRENT_NATIVE_PACKAGE_EPUB_COUNT,
            'epubDirectory' => '',
            'pandocExecutable' => null,
            'pandocVersion' => null,
            'totalEpubCount' => 0,
            'comparedEpubCount' => 0,
            'localParsedCount' => 0,
            'pandocParsedCount' => 0,
            'nativeFixtureParsedCount' => 0,
            'bothParsedCount' => 0,
            'parseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'pandocNativeFixtureComparedCount' => 0,
            'pandocNativeFixtureMatchCount' => 0,
            'pandocNativeFixtureMismatchCount' => 0,
            'pandocNativeFixtureMatchPercent' => null,
            'pandocNativeFixtureByteComparedCount' => 0,
            'pandocNativeFixtureByteMatchCount' => 0,
            'pandocNativeFixtureByteMismatchCount' => 0,
            'pandocNativeFixtureByteMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-' . $reason,
            'hasRequiredExecutableParity' => false,
            'mismatchCategories' => [],
            'orderedRemainingGaps' => [
                [
                    'rank' => 1,
                    'id' => 'pandoc-executable-epub-native-ast-equality',
                    'status' => 'not-evaluated',
                    'currentEvidence' => 'local pandoc executable comparison was not requested by this evidence command',
                    'evidenceRequired' => 'Run local PHP EPUB reader, a pandoc executable, and paired checked-in .native fixtures against the same EPUB files, keeping parse failures and normalized AST mismatches at zero.',
                ],
            ],
            'byteMismatchExamples' => [],
            'claim' => 'Local pandoc executable/native AST comparison was not evaluated for this report.',
            'claimBoundaries' => [
                'doesAssert' => [],
                'doesNotAssert' => [
                    'local pandoc executable parity',
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'EPUB writer parity',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $denominator
     * @param list<string> $validationIssues
     * @param array<string, mixed> $referencedFixtureIdentity
     * @return array<string, mixed>
     */
    private static function currentReaderStaticSignature(array $denominator, array $validationIssues, array $referencedFixtureIdentity): array
    {
        $payload = self::currentReaderStaticSignaturePayload($denominator, $referencedFixtureIdentity);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $countsMatchExpected = (int) ($denominator['mediaBagTestCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT
            && (int) ($denominator['fixtureReferenceCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT
            && (int) ($denominator['expectedMediaItemCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT;
        $denominatorValidationMatchesExpected = $validationIssues === [];
        $identityValidation = is_array($referencedFixtureIdentity['validation'] ?? null)
            ? $referencedFixtureIdentity['validation']
            : [];
        $referencedFixtureIdentityMatchesExpected = ($referencedFixtureIdentity['matchesExpected'] ?? null) === true
            && ($identityValidation['status'] ?? null) === 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
            && ($identityValidation['issues'] ?? null) === [];
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256;
        $issues = [];
        if (!$countsMatchExpected) {
            $issues[] = 'reader-static-denominator-counts-do-not-match-expected-snapshot';
        }
        if (!$denominatorValidationMatchesExpected) {
            $issues[] = 'reader-static-denominator-validation-issues';
        }
        if (!$referencedFixtureIdentityMatchesExpected) {
            $issues[] = 'reader-static-referenced-fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'reader-static-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_READER_STATIC_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'payloadIncludes' => [
                'Tests.Readers.EPUB media-bag test case names',
                'testMediaBag EPUB fixture references',
                'expected media-bag path, MIME type, and byte-size tuples',
                'missing referenced fixture file list',
                'referenced EPUB fixture SHA-256 and byte identities',
            ],
            'mediaBagTestCount' => (int) ($denominator['mediaBagTestCount'] ?? 0),
            'expectedMediaBagTestCount' => self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT,
            'fixtureReferenceCount' => (int) ($denominator['fixtureReferenceCount'] ?? 0),
            'expectedFixtureReferenceCount' => self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT,
            'expectedMediaItemCount' => (int) ($denominator['expectedMediaItemCount'] ?? 0),
            'expectedExpectedMediaItemCount' => self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-reader-static-signature'
                    : 'invalid-checked-in-current-epub-reader-static-signature',
                'issues' => $issues,
                'countsMatchExpected' => $countsMatchExpected,
                'denominatorValidationMatchesExpected' => $denominatorValidationMatchesExpected,
                'referencedFixtureIdentityMatchesExpected' => $referencedFixtureIdentityMatchesExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentReaderStaticSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_READER_STATIC_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'payloadIncludes' => [
                'Tests.Readers.EPUB media-bag test case names',
                'testMediaBag EPUB fixture references',
                'expected media-bag path, MIME type, and byte-size tuples',
                'missing referenced fixture file list',
                'referenced EPUB fixture SHA-256 and byte identities',
            ],
            'mediaBagTestCount' => 0,
            'expectedMediaBagTestCount' => self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT,
            'fixtureReferenceCount' => 0,
            'expectedFixtureReferenceCount' => self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT,
            'expectedMediaItemCount' => 0,
            'expectedExpectedMediaItemCount' => self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT,
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'countsMatchExpected' => false,
                'denominatorValidationMatchesExpected' => false,
                'referencedFixtureIdentityMatchesExpected' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedReferencedFixtureIdentity(string $reason): array
    {
        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'snapshotSchemaVersion' => 1,
            'hashAlgorithm' => self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM,
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedFileCount' => 0,
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'expectedPaths' => array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedPaths' => [],
            'missingExpectedPaths' => array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'unexpectedObservedPaths' => [],
            'totalBytes' => 0,
            'expectedTotalBytes' => array_sum(array_column(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES, 'bytes')),
            'files' => [],
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'pathsMatchExpected' => false,
                'contentsMatchExpected' => false,
                'allReferencedFilesPresent' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $denominator
     * @param array<string, mixed> $referencedFixtureIdentity
     * @return array<string, mixed>
     */
    private static function currentReaderStaticSignaturePayload(array $denominator, array $referencedFixtureIdentity): array
    {
        $readerCases = is_array($denominator['readerCases'] ?? null) ? $denominator['readerCases'] : [];

        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'snapshotSchemaVersion' => 1,
            'expectedUpstreamCommit' => self::EXPECTED_UPSTREAM_COMMIT,
            'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
            'fixtureDirectory' => 'test/epub',
            'counts' => [
                'mediaBagTestCount' => (int) ($denominator['mediaBagTestCount'] ?? 0),
                'fixtureReferenceCount' => (int) ($denominator['fixtureReferenceCount'] ?? 0),
                'expectedMediaItemCount' => (int) ($denominator['expectedMediaItemCount'] ?? 0),
            ],
            'readerCases' => self::readerCaseSignatureSnapshot($readerCases),
            'referencedFixtures' => self::stringList($denominator['referencedFixtures'] ?? []),
            'missingReferencedFiles' => self::pathList($denominator['missingReferencedFiles'] ?? []),
            'referencedFixtureIdentity' => self::referencedFixtureIdentitySignatureSnapshot($referencedFixtureIdentity),
        ];
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    private static function referencedFixtureIdentitySignatureSnapshot(array $identity): array
    {
        $files = is_array($identity['files'] ?? null) ? $identity['files'] : [];

        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'expectedFileCount' => (int) ($identity['expectedFileCount'] ?? 0),
            'observedFileCount' => (int) ($identity['observedFileCount'] ?? 0),
            'files' => self::referencedFixtureIdentityFileSignatureSnapshot($files),
        ];
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function referencedFixtureIdentityFileSignatureSnapshot(array $files): array
    {
        $snapshot = [];
        foreach ($files as $file) {
            $snapshot[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($file['bytes'] ?? null) ? $file['bytes'] : null,
            ];
        }

        usort(
            $snapshot,
            static fn (array $left, array $right): int => $left['path'] <=> $right['path']
        );

        return $snapshot;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<array<string, mixed>>
     */
    private static function readerCaseSignatureSnapshot(array $readerCases): array
    {
        $snapshot = [];
        foreach ($readerCases as $case) {
            $expectedBag = is_array($case['expectedBag'] ?? null) ? $case['expectedBag'] : [];
            $snapshot[] = [
                'name' => (string) ($case['name'] ?? ''),
                'epub' => (string) ($case['epub'] ?? ''),
                'bagName' => (string) ($case['bagName'] ?? ''),
                'expectedBagItemCount' => (int) ($case['expectedBagItemCount'] ?? 0),
                'expectedBagMissing' => ($case['expectedBagMissing'] ?? null) === true,
                'expectedBag' => self::expectedBagSignatureSnapshot($expectedBag),
            ];
        }

        return $snapshot;
    }

    /**
     * @param list<array<string, mixed>> $expectedBag
     * @return list<array{path: string, mime: string, size: int}>
     */
    private static function expectedBagSignatureSnapshot(array $expectedBag): array
    {
        $snapshot = [];
        foreach ($expectedBag as $item) {
            $snapshot[] = [
                'path' => (string) ($item['path'] ?? ''),
                'mime' => strtolower((string) ($item['mime'] ?? '')),
                'size' => (int) ($item['size'] ?? 0),
            ];
        }

        usort(
            $snapshot,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private static function pathList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $item) {
            if (is_array($item) && is_string($item['path'] ?? null)) {
                $paths[] = $item['path'];
            }
        }

        return $paths;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode EPUB reader static signature payload.');
        }

        return $json;
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string) $key] = self::canonicalValue($value[$key]);
        }

        return $normalized;
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
     * @return array<string, list<array{path: string, mime: string, size: int}>>
     */
    private static function parseBagDefinitions(string $source): array
    {
        $definitions = [];
        if (preg_match_all('/^([A-Za-z_][A-Za-z0-9_\']*)\s*=\s*(\[[^\]]*\])/m', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $definitions[(string) $match[1]] = self::parseBagTuples((string) $match[2]);
        }

        return $definitions;
    }

    /**
     * @return list<array{path: string, mime: string, size: int}>
     */
    private static function parseBagTuples(string $source): array
    {
        $items = [];
        if (preg_match_all('/\("([^"]+)"\s*,\s*"([^"]+)"\s*,\s*(\d+)\)/', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $items[] = [
                'path' => (string) $match[1],
                'mime' => strtolower((string) $match[2]),
                'size' => (int) $match[3],
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function fixtureReferences(array $readerCases): array
    {
        $seen = [];
        $references = [];
        foreach ($readerCases as $case) {
            $fixture = is_string($case['epub'] ?? null) ? $case['epub'] : '';
            if ($fixture === '' || isset($seen[$fixture])) {
                continue;
            }
            $seen[$fixture] = true;
            $references[] = $fixture;
        }

        return $references;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     */
    private function expectedMediaItemCount(array $readerCases): int
    {
        $count = 0;
        foreach ($readerCases as $case) {
            $expectedBag = $case['expectedBag'] ?? [];
            if (is_array($expectedBag)) {
                $count += count($expectedBag);
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<array{path: string}>
     */
    private function missingReferencedFiles(string $root, string $fixtureRoot, array $readerCases): array
    {
        $missing = [];
        foreach ($this->fixtureReferences($readerCases) as $fixture) {
            $path = $this->fixturePath($root, $fixtureRoot, $fixture);
            if (!is_file($path)) {
                $missing[] = ['path' => $this->fixtureDisplayPath($fixture)];
            }
        }

        return $missing;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return array<string, mixed>
     */
    private function referencedFixtureIdentity(string $root, string $fixtureRoot, array $readerCases): array
    {
        $references = $this->fixtureReferences($readerCases);
        sort($references, SORT_STRING);

        $files = [];
        foreach ($references as $fixture) {
            $path = $this->fixturePath($root, $fixtureRoot, $fixture);
            $present = is_file($path);
            $sha256 = $present ? hash_file('sha256', $path) : null;
            $sha256 = is_string($sha256) ? $sha256 : null;
            $bytes = $present ? filesize($path) : null;
            $bytes = is_int($bytes) ? $bytes : null;
            $expected = self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES[$fixture] ?? null;
            $expectedSha256 = is_array($expected) ? (string) $expected['sha256'] : null;
            $expectedBytes = is_array($expected) ? (int) $expected['bytes'] : null;
            $matchesExpected = $present
                && $sha256 === $expectedSha256
                && $bytes === $expectedBytes;

            $files[] = [
                'path' => $fixture,
                'displayPath' => $this->fixtureDisplayPath($fixture),
                'present' => $present,
                'sha256' => $sha256,
                'bytes' => $bytes,
                'expectedSha256' => $expectedSha256,
                'expectedBytes' => $expectedBytes,
                'matchesExpected' => $matchesExpected,
            ];
        }

        $expectedPaths = array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES);
        $observedPaths = array_map(static fn (array $file): string => (string) $file['path'], $files);
        sort($observedPaths, SORT_STRING);
        $missingExpectedPaths = array_values(array_diff($expectedPaths, $observedPaths));
        $unexpectedObservedPaths = array_values(array_diff($observedPaths, $expectedPaths));
        $presentFileCount = count(array_filter($files, static fn (array $file): bool => $file['present'] === true));
        $missingFileCount = count($files) - $presentFileCount;
        $contentsMatchExpected = $files !== []
            && count(array_filter($files, static fn (array $file): bool => $file['matchesExpected'] !== true)) === 0;
        $pathsMatchExpected = $observedPaths === $expectedPaths;
        $allReferencedFilesPresent = $missingFileCount === 0;
        $issues = [];
        if (!$pathsMatchExpected) {
            $issues[] = 'referenced-fixture-paths-do-not-match-expected-snapshot';
        }
        if (!$allReferencedFilesPresent) {
            $issues[] = 'missing-referenced-fixture-identity-files';
        }
        if (!$contentsMatchExpected) {
            $issues[] = 'referenced-fixture-identity-content-does-not-match-expected-snapshot';
        }

        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'snapshotSchemaVersion' => 1,
            'hashAlgorithm' => self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM,
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedFileCount' => count($files),
            'presentFileCount' => $presentFileCount,
            'missingFileCount' => $missingFileCount,
            'expectedPaths' => $expectedPaths,
            'observedPaths' => $observedPaths,
            'missingExpectedPaths' => $missingExpectedPaths,
            'unexpectedObservedPaths' => $unexpectedObservedPaths,
            'totalBytes' => array_sum(array_map(
                static fn (array $file): int => is_int($file['bytes'] ?? null) ? $file['bytes'] : 0,
                $files
            )),
            'expectedTotalBytes' => array_sum(array_column(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES, 'bytes')),
            'files' => $files,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
                    : 'invalid-checked-in-current-epub-reader-referenced-fixture-identity',
                'issues' => $issues,
                'pathsMatchExpected' => $pathsMatchExpected,
                'contentsMatchExpected' => $contentsMatchExpected,
                'allReferencedFilesPresent' => $allReferencedFilesPresent,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function unreferencedEpubFixtures(string $root, string $fixtureRoot, array $readerCases): array
    {
        $fixtureDirectory = $this->fixtureDirectory($root, $fixtureRoot);
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $referenced = array_fill_keys($this->fixtureReferences($readerCases), true);
        $unreferenced = [];
        foreach (glob($fixtureDirectory . '/*.epub') ?: [] as $path) {
            $relative = 'epub/' . basename($path);
            if (!isset($referenced[$relative])) {
                $unreferenced[] = $relative;
            }
        }
        sort($unreferenced, SORT_STRING);

        return $unreferenced;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function validationIssues(string $root, string $fixtureRoot, array $readerCases): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!$this->hasExplicitFixtureBase() && !is_file($root . '/src/Text/Pandoc/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-source';
        }
        if ($readerCases === []) {
            $issues[] = 'no-epub-mediabag-tests';
        }

        foreach ($readerCases as $case) {
            if (($case['expectedBagMissing'] ?? false) === true) {
                $issues[] = 'missing-expected-mediabag-definition';
                break;
            }
        }

        if ($this->missingReferencedFiles($root, $fixtureRoot, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach ([
            'test/Tests/Readers/EPUB.hs',
            'src/Text/Pandoc/Readers/EPUB.hs',
        ] as $relativePath) {
            $path = $root . '/' . $relativePath;
            $present = is_file($path);
            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'lineCount' => $present ? $this->lineCount($path) : 0,
            ];
        }

        return [
            'files' => $files,
            'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
            'presentFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === true)),
            'missingFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === false)),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) $file['lineCount'], $files)),
        ];
    }

    private function lineCount(string $path): int
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function absoluteFixtureBase(): ?string
    {
        if (!$this->hasExplicitFixtureBase()) {
            return null;
        }

        $fixtureBase = (string) $this->fixtureBase;
        if (str_starts_with($fixtureBase, DIRECTORY_SEPARATOR)) {
            return rtrim($fixtureBase, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($fixtureBase, DIRECTORY_SEPARATOR);
    }

    private function hasExplicitFixtureBase(): bool
    {
        return is_string($this->fixtureBase) && $this->fixtureBase !== '';
    }

    private function fixturePath(string $root, string $fixtureRoot, string $fixture): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/' . $fixture
            : $root . '/test/' . $fixture;
    }

    private function fixtureDirectory(string $root, string $fixtureRoot): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/epub'
            : $root . '/test/epub';
    }

    private function fixtureDisplayPath(string $fixture): string
    {
        return $this->hasExplicitFixtureBase() ? $fixture : 'test/' . $fixture;
    }

    private function fixtureDirectoryDisplayPath(): string
    {
        return $this->hasExplicitFixtureBase() ? 'epub' : 'test/epub';
    }

    private function usesCheckedInCurrentFixtureSnapshot(string $root, string $fixtureRoot): bool
    {
        return $this->hasExplicitFixtureBase()
            && $this->isCheckedInCurrentFixtureRoot($root)
            && $this->isCheckedInCurrentFixtureRoot($fixtureRoot);
    }

    private function isCheckedInCurrentFixtureRoot(string $path): bool
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($path, DIRECTORY_SEPARATOR));
        $repoRelative = self::CHECKED_IN_CURRENT_READER_FIXTURE_ROOT;

        return $this->displayPath($path) === $repoRelative
            || $normalized === $repoRelative
            || str_ends_with($normalized, '/' . $repoRelative);
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $command = 'git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        return trim((string) $output[0]) ?: null;
    }
}
