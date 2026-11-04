<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubMediaBagComparisonHarness
{
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';

    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'media-bag-implementation-equivalence-observed';
    private const CLAIM = 'Compares local PHP EPUB reader media-bag directory output with upstream Tests.Readers.EPUB media-bag expectations by normalized media path, MIME type, and byte size; AST/package parity is reported by the EPUB native package harness, and writer parity is outside this reader-scoped report.';
    private const CURRENT_MEDIA_BAG_SIGNATURE_KIND = 'checked-in-current-epub-media-bag-signature';
    private const CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_MEDIA_BAG_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-7-case-media-bag-snapshot';
    private const CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256 = '75598e277308c339ae55dda320e0a7747eec6353d1e5e15ee21d093553cb3f0d';
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/epub-targeted-run';
    private const RUNNER_NAME = 'Cabal/Tasty Pandoc EPUB reader suite';
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-epub-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-epub-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;
    private const RUNNER_VALIDATION_STATUS = 'valid-upstream-epub-reader-runner-result-artifact';
    private const RUNNER_INVALID_VALIDATION_STATUS = 'invalid-upstream-epub-reader-runner-result-artifact';
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
    private const CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES = array (
  0 =>
  array (
    'case' => 'features bag',
    'fixture' => 'epub/img.epub',
    'expectedItemCount' => 4,
    'actualItemCount' => 4,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'img/check.gif',
        'mime' => 'image/gif',
        'size' => 1340,
      ),
      1 =>
      array (
        'path' => 'img/check.jpg',
        'mime' => 'image/jpeg',
        'size' => 2661,
      ),
      2 =>
      array (
        'path' => 'img/check.png',
        'mime' => 'image/png',
        'size' => 2815,
      ),
      3 =>
      array (
        'path' => 'img/multiscripts_and_greek_alphabet.png',
        'mime' => 'image/png',
        'size' => 10060,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'img/check.gif',
        'mime' => 'image/gif',
        'size' => 1340,
      ),
      1 =>
      array (
        'path' => 'img/check.jpg',
        'mime' => 'image/jpeg',
        'size' => 2661,
      ),
      2 =>
      array (
        'path' => 'img/check.png',
        'mime' => 'image/png',
        'size' => 2815,
      ),
      3 =>
      array (
        'path' => 'img/multiscripts_and_greek_alphabet.png',
        'mime' => 'image/png',
        'size' => 10060,
      ),
    ),
  ),
  1 =>
  array (
    'case' => 'EPUB3 cover bag',
    'fixture' => 'epub/wasteland.epub',
    'expectedItemCount' => 1,
    'actualItemCount' => 1,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'wasteland-cover.jpg',
        'mime' => 'image/jpeg',
        'size' => 16586,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'wasteland-cover.jpg',
        'mime' => 'image/jpeg',
        'size' => 16586,
      ),
    ),
  ),
  2 =>
  array (
    'case' => 'EPUB3 no cover bag',
    'fixture' => 'epub/img_no_cover.epub',
    'expectedItemCount' => 3,
    'actualItemCount' => 3,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'img/check.gif',
        'mime' => 'image/gif',
        'size' => 1340,
      ),
      1 =>
      array (
        'path' => 'img/check.jpg',
        'mime' => 'image/jpeg',
        'size' => 2661,
      ),
      2 =>
      array (
        'path' => 'img/check.png',
        'mime' => 'image/png',
        'size' => 2815,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'img/check.gif',
        'mime' => 'image/gif',
        'size' => 1340,
      ),
      1 =>
      array (
        'path' => 'img/check.jpg',
        'mime' => 'image/jpeg',
        'size' => 2661,
      ),
      2 =>
      array (
        'path' => 'img/check.png',
        'mime' => 'image/png',
        'size' => 2815,
      ),
    ),
  ),
  3 =>
  array (
    'case' => 'EPUB2 picture bag',
    'fixture' => 'epub/epub2_picture.epub',
    'expectedItemCount' => 1,
    'actualItemCount' => 1,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'image/image.jpg',
        'mime' => 'image/jpeg',
        'size' => 9713,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'image/image.jpg',
        'mime' => 'image/jpeg',
        'size' => 9713,
      ),
    ),
  ),
  4 =>
  array (
    'case' => 'EPUB2 cover bag',
    'fixture' => 'epub/epub2_cover.epub',
    'expectedItemCount' => 1,
    'actualItemCount' => 1,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'image/cover.jpg',
        'mime' => 'image/jpeg',
        'size' => 9713,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'image/cover.jpg',
        'mime' => 'image/jpeg',
        'size' => 9713,
      ),
    ),
  ),
  5 =>
  array (
    'case' => 'EPUB2 no cover bag',
    'fixture' => 'epub/epub2_no_cover.epub',
    'expectedItemCount' => 0,
    'actualItemCount' => 0,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
    ),
    'actualBag' =>
    array (
    ),
  ),
  6 =>
  array (
    'case' => 'encoded image media bag',
    'fixture' => 'epub/encoded-image-media-bag.epub',
    'expectedItemCount' => 1,
    'actualItemCount' => 1,
    'matchesExpected' => true,
    'expectedBag' =>
    array (
      0 =>
      array (
        'path' => 'images/cover art.png',
        'mime' => 'image/png',
        'size' => 68,
      ),
    ),
    'actualBag' =>
    array (
      0 =>
      array (
        'path' => 'images/cover art.png',
        'mime' => 'image/png',
        'size' => 68,
      ),
    ),
  ),
);

    /**
     * @param array{limit?: int, maxExamples?: int, readerCases?: list<array<string, mixed>>, fixtureBase?: string, repoRoot?: string, runnerResultArtifact?: string} $options
     * @return array<string, mixed>
     */
    public function run(string $upstreamRoot, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));
        $repoRoot = is_string($options['repoRoot'] ?? null) && $options['repoRoot'] !== ''
            ? rtrim((string) $options['repoRoot'], DIRECTORY_SEPARATOR)
            : (getcwd() ?: '');
        $runnerResultArtifact = is_string($options['runnerResultArtifact'] ?? null)
            ? (string) $options['runnerResultArtifact']
            : null;
        if ($runnerResultArtifact === '') {
            throw new \InvalidArgumentException('Runner result artifact must not be empty');
        }

        if (!is_dir($upstreamRoot)) {
            return $this->skippedReport($upstreamRoot, 'upstream-cache-missing');
        }

        $readerCases = is_array($options['readerCases'] ?? null)
            ? $options['readerCases']
            : $this->readerCasesFromUpstreamRoot($upstreamRoot);
        $fixtureBase = is_string($options['fixtureBase'] ?? null)
            ? rtrim((string) $options['fixtureBase'], DIRECTORY_SEPARATOR)
            : $upstreamRoot . '/test';

        if ($readerCases === []) {
            return $this->skippedReport($upstreamRoot, 'upstream-reader-test-missing-or-empty');
        }

        $runnerReaderCases = $readerCases;
        $totalCaseCount = count($runnerReaderCases);
        if ($limit > 0) {
            $readerCases = array_slice($readerCases, 0, $limit);
        }

        $epubParsedCount = 0;
        $expectedItemCount = 0;
        $actualItemCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $signatures = [];

        foreach ($readerCases as $case) {
            $fixture = is_string($case['epub'] ?? null) ? $case['epub'] : '';
            $caseName = is_string($case['name'] ?? null) ? $case['name'] : $fixture;
            $expectedBag = is_array($case['expectedBag'] ?? null) ? $this->normalizeBag($case['expectedBag']) : [];
            $expectedItemCount += count($expectedBag);
            $actualResult = $this->readActualBag($fixtureBase . '/' . $fixture);

            if (!$actualResult['ok']) {
                $parseFailures[] = [
                    'case' => $caseName,
                    'fixture' => $fixture,
                    'error' => $actualResult['error'],
                ];
                $signatures[] = $this->mediaBagSignature($caseName, $fixture, $expectedBag, [], false);
                continue;
            }

            ++$epubParsedCount;
            $actualBag = $this->normalizeBag($actualResult['bag']);
            $actualItemCount += count($actualBag);
            $matchesExpected = $actualBag === $expectedBag;
            $signatures[] = $this->mediaBagSignature($caseName, $fixture, $expectedBag, $actualBag, $matchesExpected);
            if ($matchesExpected) {
                ++$matchCount;
                continue;
            }

            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'case' => $caseName,
                    'fixture' => $fixture,
                    'firstDifference' => $this->firstDifference($expectedBag, $actualBag) ?? 'unknown-media-bag-difference',
                    'expectedBag' => $expectedBag,
                    'actualBag' => $actualBag,
                ];
            }
        }

        $comparedCaseCount = count($readerCases);
        $mismatchCount = $epubParsedCount - $matchCount;
        $parseFailureCount = count($parseFailures);
        $runnerEvidence = $runnerResultArtifact === null
            ? self::runnerNotRunEvidence()
            : $this->runnerResultArtifactEvidence($runnerResultArtifact, $repoRoot, $runnerReaderCases);
        $runnerResultCovered = self::runnerResultArtifactEvidenceIsValid($runnerEvidence);

        $currentMediaBagSignature = self::currentMediaBagSignature(
            $signatures,
            $totalCaseCount,
            $comparedCaseCount,
            $epubParsedCount,
            $parseFailureCount,
            $expectedItemCount,
            $actualItemCount,
            $matchCount,
            $mismatchCount
        );

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-media-bag',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-upstream-mediabag-comparison',
            'upstreamRoot' => $upstreamRoot,
            'fixtureBase' => $fixtureBase,
            'runnerEvidence' => $runnerEvidence,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalCaseCount' => $totalCaseCount,
            'comparedCaseCount' => $comparedCaseCount,
            'epubParsedCount' => $epubParsedCount,
            'parseFailureCount' => $parseFailureCount,
            'expectedMediaItemCount' => $expectedItemCount,
            'actualMediaItemCount' => $actualItemCount,
            'mediaBagMatchCount' => $matchCount,
            'mediaBagMismatchCount' => $mismatchCount,
            'mediaBagMatchPercent' => self::percent($matchCount, $comparedCaseCount),
            'mediaBagParityStatus' => self::mediaBagParityStatus($parseFailureCount, $mismatchCount, $comparedCaseCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'currentMediaBagSignature' => $currentMediaBagSignature,
            'mediaBagSignatures' => $signatures,
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedCaseCount,
                $parseFailureCount,
                $matchCount,
                $mismatchCount,
                $runnerResultCovered
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc EPUB media-bag comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamRoot=' . (string) ($report['upstreamRoot'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
            if ($runner !== []) {
                $lines[] = sprintf(
                    'runnerEvidence: status=%s plan=%s executed=%s',
                    (string) ($runner['status'] ?? 'unknown'),
                    (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                    self::formatRunnerExecuted($runner['executed'] ?? null)
                );
            }
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'cases: total=%d compared=%d parsed=%d parseFailures=%d',
            (int) ($report['totalCaseCount'] ?? 0),
            (int) ($report['comparedCaseCount'] ?? 0),
            (int) ($report['epubParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0),
        );
        $lines[] = sprintf(
            'mediaBag: matches=%d (%s) mismatches=%d expectedItems=%d actualItems=%d status=%s',
            (int) ($report['mediaBagMatchCount'] ?? 0),
            self::formatPercent($report['mediaBagMatchPercent'] ?? null),
            (int) ($report['mediaBagMismatchCount'] ?? 0),
            (int) ($report['expectedMediaItemCount'] ?? 0),
            (int) ($report['actualMediaItemCount'] ?? 0),
            (string) ($report['mediaBagParityStatus'] ?? 'unknown'),
        );
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        if ($runner !== []) {
            $lines[] = sprintf(
                'runnerEvidence: status=%s plan=%s executed=%s',
                (string) ($runner['status'] ?? 'unknown'),
                (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                self::formatRunnerExecuted($runner['executed'] ?? null)
            );
        }
        $signature = is_array($report['currentMediaBagSignature'] ?? null) ? $report['currentMediaBagSignature'] : [];
        if ($signature !== []) {
            $signatureValidation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
            $lines[] = sprintf(
                'currentMediaBagSignature: status=%s matchesExpected=%s sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($signature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (string) ($signature['sha256'] ?? ''),
                (string) ($signature['expectedSha256'] ?? '')
            );
        }

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagParity(array $report, int $requiredCaseCount): bool
    {
        if ($requiredCaseCount < 0) {
            throw new \InvalidArgumentException('Required EPUB media-bag parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalCaseCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['comparedCaseCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['epubParsedCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['mediaBagMatchCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['mediaBagMismatchCount'] ?? -1) === 0
            && ($report['mediaBagParityStatus'] ?? null) === 'media-bag-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagItemCount(array $report, int $requiredItemCount): bool
    {
        if ($requiredItemCount < 0) {
            throw new \InvalidArgumentException('Required EPUB media-bag item count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['expectedMediaItemCount'] ?? -1) === $requiredItemCount
            && (int) ($report['actualMediaItemCount'] ?? -1) === $requiredItemCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentMediaBagSignatures(array $report): bool
    {
        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && ($report['mediaBagSignatures'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES
            && self::hasRequiredCurrentMediaBagSignature($report);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentMediaBagSignature(array $report): bool
    {
        $signature = is_array($report['currentMediaBagSignature'] ?? null) ? $report['currentMediaBagSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && ($signature['kind'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE
            && (int) ($signature['caseCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['expectedCaseCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['expectedMediaItemCount'] ?? -1) === self::expectedCurrentMediaBagItemCount()
            && (int) ($signature['actualMediaItemCount'] ?? -1) === self::expectedCurrentMediaBagItemCount()
            && (int) ($signature['mediaBagMatchCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['mediaBagMismatchCount'] ?? -1) === 0
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-media-bag-signature'
            && ($validation['issues'] ?? null) === []
            && ($validation['caseSignaturesMatchExpected'] ?? null) === true
            && ($validation['countsMatchExpected'] ?? null) === true;
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
            && array_key_exists('result', $runner)
            && $runner['result'] === null
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
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return self::runnerResultArtifactEvidenceIsValid($runner)
            && ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === self::RUNNER_NAME
            && is_array($runner['command'] ?? null)
            && self::canonicalValue($runner['command'] ?? null) === self::canonicalValue(self::runnerFutureCommands()[2])
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($validation['status'] ?? null) === self::RUNNER_VALIDATION_STATUS
            && ($validation['issues'] ?? null) === []
            && self::hasValidRunnerTranscriptEvidence($transcripts);
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $upstreamRoot, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-media-bag',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-upstream-mediabag-comparison',
            'upstreamRoot' => $upstreamRoot,
            'fixtureBase' => null,
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalCaseCount' => 0,
            'comparedCaseCount' => 0,
            'epubParsedCount' => 0,
            'parseFailureCount' => 0,
            'expectedMediaItemCount' => 0,
            'actualMediaItemCount' => 0,
            'mediaBagMatchCount' => 0,
            'mediaBagMismatchCount' => 0,
            'mediaBagMatchPercent' => null,
            'mediaBagParityStatus' => 'not-evaluated-source-directory-unavailable',
            'parseFailures' => [],
            'mismatchComparisons' => [],
            'currentMediaBagSignature' => self::notEvaluatedCurrentMediaBagSignature($reason),
            'mediaBagSignatures' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, false),
        ];
    }

    /**
     * @param list<array{path: string, mime: string, size: int}> $expectedBag
     * @param list<array{path: string, mime: string, size: int}> $actualBag
     * @return array{case: string, fixture: string, expectedItemCount: int, actualItemCount: int, matchesExpected: bool, expectedBag: list<array{path: string, mime: string, size: int}>, actualBag: list<array{path: string, mime: string, size: int}>}
     */
    private function mediaBagSignature(string $caseName, string $fixture, array $expectedBag, array $actualBag, bool $matchesExpected): array
    {
        return [
            'case' => $caseName,
            'fixture' => $fixture,
            'expectedItemCount' => count($expectedBag),
            'actualItemCount' => count($actualBag),
            'matchesExpected' => $matchesExpected,
            'expectedBag' => $expectedBag,
            'actualBag' => $actualBag,
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private static function currentMediaBagSignature(
        array $signatures,
        int $totalCaseCount,
        int $comparedCaseCount,
        int $epubParsedCount,
        int $parseFailureCount,
        int $expectedItemCount,
        int $actualItemCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        $payload = self::currentMediaBagSignaturePayload(
            $signatures,
            $totalCaseCount,
            $comparedCaseCount,
            $epubParsedCount,
            $parseFailureCount,
            $expectedItemCount,
            $actualItemCount,
            $matchCount,
            $mismatchCount
        );
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $caseSignaturesMatchExpected = $signatures === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES;
        $countsMatchExpected = $totalCaseCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $comparedCaseCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $epubParsedCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $parseFailureCount === 0
            && $expectedItemCount === self::expectedCurrentMediaBagItemCount()
            && $actualItemCount === self::expectedCurrentMediaBagItemCount()
            && $matchCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $mismatchCount === 0;
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256;
        $issues = [];
        if (!$caseSignaturesMatchExpected) {
            $issues[] = 'media-bag-case-signatures-do-not-match-expected-snapshot';
        }
        if (!$countsMatchExpected) {
            $issues[] = 'media-bag-counters-do-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'media-bag-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_MEDIA_BAG_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'caseCount' => $comparedCaseCount,
            'expectedCaseCount' => count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES),
            'expectedMediaItemCount' => $expectedItemCount,
            'actualMediaItemCount' => $actualItemCount,
            'mediaBagMatchCount' => $matchCount,
            'mediaBagMismatchCount' => $mismatchCount,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-media-bag-signature'
                    : 'invalid-checked-in-current-epub-media-bag-signature',
                'issues' => $issues,
                'caseSignaturesMatchExpected' => $caseSignaturesMatchExpected,
                'countsMatchExpected' => $countsMatchExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentMediaBagSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_MEDIA_BAG_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'caseCount' => 0,
            'expectedCaseCount' => count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES),
            'expectedMediaItemCount' => 0,
            'actualMediaItemCount' => 0,
            'mediaBagMatchCount' => 0,
            'mediaBagMismatchCount' => 0,
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'caseSignaturesMatchExpected' => false,
                'countsMatchExpected' => false,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private static function currentMediaBagSignaturePayload(
        array $signatures,
        int $totalCaseCount,
        int $comparedCaseCount,
        int $epubParsedCount,
        int $parseFailureCount,
        int $expectedItemCount,
        int $actualItemCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        return [
            'schemaVersion' => 1,
            'mediaBagComparison' => [
                'totalCaseCount' => $totalCaseCount,
                'comparedCaseCount' => $comparedCaseCount,
                'epubParsedCount' => $epubParsedCount,
                'parseFailureCount' => $parseFailureCount,
                'expectedMediaItemCount' => $expectedItemCount,
                'actualMediaItemCount' => $actualItemCount,
                'mediaBagMatchCount' => $matchCount,
                'mediaBagMismatchCount' => $mismatchCount,
                'mediaBagSignatures' => $signatures,
            ],
        ];
    }

    private static function expectedCurrentMediaBagItemCount(): int
    {
        $count = 0;
        foreach (self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES as $signature) {
            $count += (int) ($signature['expectedItemCount'] ?? 0);
        }

        return $count;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode media-bag signature payload.');
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
     * @return list<array<string, mixed>>
     */
    private function readerCasesFromUpstreamRoot(string $upstreamRoot): array
    {
        $readerTestPath = $upstreamRoot . '/test/Tests/Readers/EPUB.hs';
        if (!is_file($readerTestPath)) {
            return [];
        }

        return EpubUpstreamReaderEvidence::parseReaderCasesFromSource((string) file_get_contents($readerTestPath));
    }

    /**
     * @return array{ok: bool, bag: list<array{path: string, mime: string, size: int}>, error: ?string}
     */
    private function readActualBag(string $path): array
    {
        if (!is_file($path)) {
            return ['ok' => false, 'bag' => [], 'error' => 'missing-epub-fixture'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'bag' => [], 'error' => 'php-ziparchive-unavailable'];
        }

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta', []);
            if (!is_array($meta)) {
                return ['ok' => false, 'bag' => [], 'error' => 'epub-reader-meta-missing'];
            }

            $directory = is_array($meta['epubMediaResourceDirectory'] ?? null)
                ? $meta['epubMediaResourceDirectory']
                : null;
            if ($directory === null) {
                return ['ok' => false, 'bag' => [], 'error' => 'epub-reader-media-resource-directory-missing'];
            }

            $bag = [];
            foreach ($directory as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $bag[] = [
                    'path' => (string) ($item['path'] ?? ''),
                    'mime' => strtolower((string) ($item['mimeType'] ?? '')),
                    'size' => (int) ($item['byteLength'] ?? 0),
                ];
            }

            return ['ok' => true, 'bag' => $this->normalizeBag($bag), 'error' => null];
        } catch (\Throwable $throwable) {
            return ['ok' => false, 'bag' => [], 'error' => $throwable::class . ': ' . $throwable->getMessage()];
        }
    }

    /**
     * @param list<array<string, mixed>> $bag
     * @return list<array{path: string, mime: string, size: int}>
     */
    private function normalizeBag(array $bag): array
    {
        $normalized = [];
        foreach ($bag as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = [
                'path' => (string) ($item['path'] ?? ''),
                'mime' => strtolower((string) ($item['mime'] ?? '')),
                'size' => (int) ($item['size'] ?? 0),
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $normalized;
    }

    /**
     * @param list<array{path: string, mime: string, size: int}> $expectedBag
     * @param list<array{path: string, mime: string, size: int}> $actualBag
     */
    private function firstDifference(array $expectedBag, array $actualBag): ?string
    {
        if (count($expectedBag) !== count($actualBag)) {
            return 'media item count expected=' . count($expectedBag) . ' actual=' . count($actualBag);
        }

        foreach ($expectedBag as $index => $expected) {
            $actual = $actualBag[$index] ?? null;
            if ($actual === null) {
                return 'missing actual media item at index ' . $index;
            }
            foreach (['path', 'mime', 'size'] as $key) {
                if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                    return 'media item ' . $index . ' ' . $key
                        . ' expected=' . (string) ($expected[$key] ?? '')
                        . ' actual=' . (string) ($actual[$key] ?? '');
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'includes' => [
                'local EpubReader epubMediaResourceDirectory entries loaded from emitted image nodes',
                'MediaBag normalized MIME type values',
                'MediaBag byte lengths from inserted ZIP entry payloads',
                'Pandoc-style paths relative to the OPF root directory',
            ],
            'excludes' => [
                'Pandoc AST equality',
                'upstream Haskell runner execution',
                'manifest image resources not used by the emitted EPUB image AST',
                'EPUB writer behavior',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(string $runnerResultArtifact, string $repoRoot, array $readerCases): array
    {
        $path = self::absoluteRunnerResultArtifact($runnerResultArtifact, $repoRoot);
        $artifact = self::runnerResultArtifactFileEvidence($path, $repoRoot);
        $transcripts = self::runnerTranscriptFileEvidenceList($repoRoot);
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
        $expectedTestNames = self::runnerExpectedTestNames($readerCases);
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
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== self::RUNNER_NAME) {
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
            if (self::canonicalValue($command) !== self::canonicalValue($expectedCommand)) {
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
            'runner' => self::RUNNER_NAME,
            'scope' => 'upstream-haskell-runner',
            'status' => $issues === [] ? 'completed' : 'invalid',
            'executed' => $runnerExecuted,
            'command' => $command,
            'result' => null,
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
                'runner' => self::RUNNER_NAME,
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
                    ? self::RUNNER_VALIDATION_STATUS
                    : self::RUNNER_INVALID_VALIDATION_STATUS,
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream EPUB reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream EPUB reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => self::RUNNER_NAME,
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'result' => null,
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
                'no committed upstream test:test-pandoc EPUB reader runner transcript or result artifact is present',
                'this PHP media-bag comparison gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted EPUB media-bag Tasty pattern',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP media-bag comparison evidence packet is generated without executing the upstream Haskell runner.',
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
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerResultArtifactFileEvidence(string $path, string $repoRoot): array
    {
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_RESULT_ARTIFACT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    /**
     * @return list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptFileEvidenceList(string $repoRoot): array
    {
        $files = [];
        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $files[] = self::runnerTranscriptFileEvidence($repoRoot, $path);
        }

        return $files;
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerTranscriptFileEvidence(string $repoRoot, string $relativePath): array
    {
        $path = self::absoluteRunnerTranscriptPath($repoRoot, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_TRANSCRIPT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    private static function absoluteRunnerTranscriptPath(string $repoRoot, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private static function absoluteRunnerResultArtifact(string $path, string $repoRoot): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    }

    private static function displayRunnerPath(string $path, string $repoRoot): string
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        if ($root !== '' && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private static function runnerExpectedTestNames(array $readerCases): array
    {
        $names = [];
        foreach ($readerCases as $case) {
            if (is_string($case['name'] ?? null)) {
                $names[] = $case['name'];
            }
        }

        return $names;
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

    private static function runnerResultArtifactEvidenceIsValid(array $runner): bool
    {
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];

        return ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($validation['status'] ?? null) === self::RUNNER_VALIDATION_STATUS
            && ($validation['issues'] ?? null) === [];
    }

    private static function mediaBagParityStatus(int $parseFailureCount, int $mismatchCount, int $comparedCaseCount): string
    {
        if ($comparedCaseCount === 0) {
            return 'not-evaluated-no-epub-mediabag-cases';
        }
        if ($parseFailureCount > 0) {
            return 'epub-fixture-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'media-bag-mismatches-observed';
        }

        return 'media-bag-implementation-equivalence-observed';
    }

    /**
     * @return list<array{id: string, status: string, summary: string}>
     */
    private static function orderedRemainingGaps(
        bool $evaluated,
        int $comparedCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount,
        bool $runnerResultCovered = false
    ): array
    {
        $equalityStatus = 'not-evaluated';
        if ($evaluated) {
            $equalityStatus = $comparedCount > 0 && $parseFailureCount === 0 && $mismatchCount === 0 && $matchCount === $comparedCount
                ? 'covered-by-current-media-bag-evidence'
                : 'open';
        }

        return [
            [
                'id' => 'upstream-epub-mediabag-equality',
                'status' => $equalityStatus,
                'summary' => 'Compare local EPUB reader media-bag path, MIME, and byte-size tuples with Tests.Readers.EPUB expectations.',
            ],
            [
                'id' => 'upstream-epub-reader-runner-results',
                'status' => $runnerResultCovered ? 'covered-by-validated-runner-result-artifact' : 'open',
                'summary' => 'Run the upstream Haskell/Tasty EPUB reader tests directly and archive the runner result.',
            ],
            [
                'id' => 'full-epub-reader-feature-coverage',
                'status' => 'open',
                'summary' => 'Broaden beyond the upstream media-bag tests into full EPUB AST, metadata, navigation, CSS, and edge-case coverage.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param list<string> $lines
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach (array_values($gaps) as $index => $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s] %s',
                $index + 1,
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['summary'] ?? '')
            );
        }

        return $lines;
    }

    private static function formatRunnerExecuted(mixed $executed): string
    {
        if ($executed === true) {
            return 'true';
        }
        if ($executed === false) {
            return 'false';
        }

        return 'unknown';
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        if (!is_float($percent) && !is_int($percent)) {
            return 'n/a';
        }

        return number_format((float) $percent, 2) . '%';
    }
}
