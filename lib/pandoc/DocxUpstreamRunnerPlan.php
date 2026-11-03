<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxUpstreamRunnerPlan
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const PINNED_UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';
    public const STATUS_READY = 'preflight-ready-no-runner-executed';
    public const STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE = 'blocked-missing-docx-upstream-source';
    public const EVIDENCE_KIND = 'targeted-docx-runner-preflight-plan-only';
    public const SELECTED_TEST_INVENTORY_EVIDENCE_KIND = 'static-docx-selected-test-inventory-only';
    public const SELECTED_TEST_INVENTORY_STATUS_REPORTED = 'reported-static-docx-selected-test-inventory';
    public const SELECTED_TEST_INVENTORY_STATUS_BLOCKED = 'blocked-missing-docx-upstream-source';
    public const RESULT_ARTIFACT_GATE_EVIDENCE_KIND = 'targeted-docx-runner-result-artifact-gate-only';
    public const RESULT_ARTIFACT_GATE_STATUS_MISSING = 'blocked-missing-targeted-runner-result-artifacts';
    public const RESULT_ARTIFACT_GATE_STATUS_INVALID = 'blocked-invalid-targeted-runner-result-artifacts';
    public const RESULT_ARTIFACT_GATE_STATUS_ADMISSIBLE = 'admissible-targeted-runner-result-artifacts-no-parity-claim';
    public const RESULT_ARTIFACT_EVIDENCE_KIND = 'targeted-docx-runner-result-artifact-from-transcripts';
    public const DEFAULT_RELATIVE_ARTIFACT_ROOT = '.port-libs/pandoc-runner/artifacts/docx-targeted-run';
    public const DEFAULT_RELATIVE_LOG_ROOT = '.port-libs/pandoc-runner/logs';
    public const LOCAL_READINESS_STATUS_READY = 'ready-for-targeted-docx-runner-execution';
    public const LOCAL_READINESS_STATUS_BLOCKED = 'blocked-targeted-docx-runner-local-prerequisites';
    public const LOCAL_READINESS_EVIDENCE_KIND = 'targeted-docx-runner-local-readiness-gate-only';
    public const LOCAL_READINESS_BLOCKER_MISSING_SOURCE = 'missing-docx-upstream-source';
    public const LOCAL_READINESS_BLOCKER_UNVERIFIED_PINNED_SOURCE = 'unverified-pinned-upstream-commit';
    public const LOCAL_READINESS_BLOCKER_MISSING_CABAL = 'missing-cabal-executable';
    public const LOCAL_READINESS_BLOCKER_MISSING_GHC = 'missing-ghc-executable';
    public const LOCAL_READINESS_BLOCKER_INSUFFICIENT_DISK = 'insufficient-disk-for-targeted-runner-workspace';
    public const PINNED_SOURCE_STATUS_MATCHED = 'matched-pinned-upstream-commit';
    public const PINNED_SOURCE_STATUS_MISMATCHED = 'mismatched-pinned-upstream-commit';
    public const PINNED_SOURCE_STATUS_ROOT_MISSING = 'not-checked-upstream-root-missing';
    public const PINNED_SOURCE_STATUS_GIT_MISSING = 'not-checked-git-executable-missing';
    public const PINNED_SOURCE_STATUS_NOT_GIT_CHECKOUT = 'not-checked-not-a-git-checkout';
    public const TASTY_PATTERN = '($2 == "Readers" || $2 == "Writers") && $3 == "Docx"';
    public const RUNNER_TARGET = 'test:test-pandoc';
    public const MINIMUM_SUGGESTED_FREE_BYTES = 1073741824;

    /** @var list<string> */
    private const SELECTED_GROUPS = [
        'Tests.Readers.Docx.tests',
        'Tests.Writers.Docx.tests',
    ];

    /** @var list<string> */
    private const REQUIRED_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'test/test-pandoc.hs',
        'test/Tests/Readers/Docx.hs',
        'test/Tests/Writers/Docx.hs',
        'data/docx/[Content_Types].xml',
    ];

    /** @var list<string> */
    private const REQUIRED_DIRECTORIES = [
        'test/docx',
        'test/docx/golden',
        'data/docx',
    ];

    /** @var array<string, string> */
    private const WORKSPACE_ENVIRONMENT = [
        'CABAL_DIR' => '.port-libs/pandoc-runner/cabal-home',
        'CABAL_CONFIG' => '.port-libs/pandoc-runner/cabal-config/config',
        'XDG_CACHE_HOME' => '.port-libs/pandoc-runner/cache',
        'XDG_STATE_HOME' => '.port-libs/pandoc-runner/state',
        'TMPDIR' => '.port-libs/pandoc-runner/tmp',
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?\Closure $executableResolver;
    private readonly ?\Closure $freeSpaceResolver;

    public function __construct(
        string $repoRoot,
        string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        ?callable $executableResolver = null,
        ?callable $freeSpaceResolver = null
    ) {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $this->absolutePath($upstreamRoot);
        $this->executableResolver = $executableResolver === null ? null : \Closure::fromCallable($executableResolver);
        $this->freeSpaceResolver = $freeSpaceResolver === null ? null : \Closure::fromCallable($freeSpaceResolver);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $sourcePreflight = $this->sourcePreflight();
        $ready = $sourcePreflight['missingFiles'] === [] && $sourcePreflight['missingDirectories'] === [];
        $selectedTestInventory = $this->selectedTestInventory($sourcePreflight, $ready);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-upstream-runner-plan',
            'status' => $ready ? self::STATUS_READY : self::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE,
            'evidenceKind' => self::EVIDENCE_KIND,
            'runnerExecuted' => false,
            'resultRecorded' => false,
            'willExecute' => false,
            'claim' => 'Preflight-only plan for a future targeted upstream DOCX Tasty run; this is not an upstream DOCX runner result.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'required DOCX runner source paths and fixture directories to check before a targeted run',
                    'whether the hydrated upstream root can be tied to the pinned Pandoc commit before result admission',
                    'static selected DOCX reader/writer source and fixture inventory when the upstream checkout is hydrated',
                    'exact Cabal commands and workspace artifact paths a future runner slice can execute',
                    'result artifact contract required before recording upstream DOCX runner evidence',
                ],
                'doesNotAssert' => [
                    'that Cabal or upstream Pandoc tests were executed',
                    'that selected DOCX reader or writer tests pass',
                    'that local PHP DOCX parsing, writing, or package output matches upstream',
                    'full DOCX/OpenXML parity',
                ],
            ],
            'upstream' => [
                'pinnedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'expectedCheckoutRoot' => $this->displayPath($this->upstreamRoot),
            ],
            'runnerTarget' => self::RUNNER_TARGET,
            'tastyPattern' => self::TASTY_PATTERN,
            'sourcePreflight' => $sourcePreflight,
            'selectedTestInventory' => $selectedTestInventory,
            'localExecutionReadiness' => $this->localExecutionReadiness($sourcePreflight, $ready),
            'workspace' => $this->workspace(),
            'commands' => $this->commands(),
            'resultArtifactContract' => $this->resultArtifactContract(),
            'activationGate' => $this->activationGate($ready),
            'executionPolicy' => 'This PHP tool emits preflight evidence and command descriptors only; it never invokes Cabal or upstream Pandoc tests.',
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX upstream runner preflight plan',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Evidence kind: ' . (string) ($report['evidenceKind'] ?? self::EVIDENCE_KIND),
            'Runner executed: ' . self::boolText($report['runnerExecuted'] ?? false),
            'Result recorded: ' . self::boolText($report['resultRecorded'] ?? false),
            'Upstream root: ' . (string) ($report['upstream']['expectedCheckoutRoot'] ?? ''),
            'Pinned upstream commit: ' . (string) ($report['upstream']['pinnedCommit'] ?? self::PINNED_UPSTREAM_COMMIT),
        ];

        $source = $report['sourcePreflight'] ?? [];
        if (is_array($source)) {
            $lines[] = 'Missing files: ' . implode(', ', array_map('strval', $source['missingFiles'] ?? []));
            $lines[] = 'Missing directories: ' . implode(', ', array_map('strval', $source['missingDirectories'] ?? []));
            $pinnedSource = $source['pinnedSource'] ?? [];
            if (is_array($pinnedSource)) {
                $lines[] = 'Pinned source: '
                    . (string) ($pinnedSource['status'] ?? 'unknown')
                    . '; observed='
                    . (string) ($pinnedSource['observedCommit'] ?? 'none');
            }
        }

        $inventory = $report['selectedTestInventory'] ?? [];
        if (is_array($inventory)) {
            $fixtureCounts = $inventory['fixtures']['counts'] ?? [];
            $lines[] = 'Selected inventory: '
                . (string) ($inventory['status'] ?? 'unknown')
                . '; static label hints='
                . (int) ($inventory['staticCandidateTestLabelCount'] ?? 0)
                . '; paired reader fixture stems='
                . (int) ($fixtureCounts['pairedRootDocxNativeStems'] ?? 0)
                . '; writer golden packages='
                . (int) ($fixtureCounts['goldenDocxPackageFiles'] ?? 0);
        }

        $readiness = $report['localExecutionReadiness'] ?? [];
        if (is_array($readiness)) {
            $lines[] = 'Local execution readiness: '
                . (string) ($readiness['status'] ?? 'unknown')
                . '; blockers='
                . count(is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : []);
            $codes = is_array($readiness['blockerCodes'] ?? null) ? $readiness['blockerCodes'] : [];
            $lines[] = 'Local execution blocker codes: ' . implode(', ', array_map('strval', $codes));
            foreach (is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [] as $blocker) {
                $lines[] = 'Local execution blocker: ' . (string) $blocker;
            }
        }

        $artifact = $report['selectedTestInventoryArtifact'] ?? [];
        if (is_array($artifact) && ($artifact['written'] ?? false) === true) {
            $lines[] = 'Selected inventory artifact: ' . (string) ($artifact['path'] ?? '');
        }

        $gate = $report['resultArtifactGate'] ?? [];
        if (is_array($gate)) {
            $lines[] = 'Result artifact gate: '
                . (string) ($gate['status'] ?? 'unknown')
                . '; admissionReady='
                . self::boolText($gate['admissionReady'] ?? false)
                . '; problems='
                . count(is_array($gate['problems'] ?? null) ? $gate['problems'] : []);
        }

        $commands = $report['commands'] ?? [];
        if (is_array($commands)) {
            foreach (['dependencyDryRun', 'listDocxTests', 'targetedDocxRun'] as $name) {
                $command = $commands[$name] ?? null;
                if (is_array($command)) {
                    $lines[] = $name . ': ' . (string) ($command['commandLine'] ?? '');
                }
            }
        }

        $lines[] = 'No upstream DOCX runner result or parity claim is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return list<string>
     */
    public static function requiredFiles(): array
    {
        return self::REQUIRED_FILES;
    }

    /**
     * @return list<string>
     */
    public static function requiredDirectories(): array
    {
        return self::REQUIRED_DIRECTORIES;
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePreflight(): array
    {
        $presentFiles = [];
        $missingFiles = [];
        foreach (self::REQUIRED_FILES as $relativePath) {
            $absolutePath = $this->upstreamPath($relativePath);
            if ($this->hasNonEmptyFile($absolutePath)) {
                $presentFiles[] = $relativePath;
            } else {
                $missingFiles[] = $relativePath;
            }
        }

        $presentDirectories = [];
        $missingDirectories = [];
        foreach (self::REQUIRED_DIRECTORIES as $relativePath) {
            $absolutePath = $this->upstreamPath($relativePath);
            if (is_dir($absolutePath)) {
                $presentDirectories[] = $relativePath;
            } else {
                $missingDirectories[] = $relativePath;
            }
        }
        $pinnedSource = $this->pinnedSourceEvidence();

        return [
            'upstreamRoot' => $this->upstreamRoot,
            'upstreamRootDisplay' => $this->displayPath($this->upstreamRoot),
            'upstreamRootPresent' => is_dir($this->upstreamRoot),
            'pinnedSource' => $pinnedSource,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'requiredDirectories' => self::REQUIRED_DIRECTORIES,
            'presentDirectories' => $presentDirectories,
            'missingDirectories' => $missingDirectories,
            'sourceBlockers' => $this->sourceBlockers($missingFiles, $missingDirectories, $pinnedSource),
            'hydrationPlan' => $this->hydrationPlan(),
            'artifactCounts' => [
                'rootDocxPackageFiles' => $this->countDirectFiles($this->upstreamPath('test/docx'), 'docx'),
                'rootNativeExpectedFiles' => $this->countDirectFiles($this->upstreamPath('test/docx'), 'native'),
                'goldenDocxPackageFiles' => $this->countDirectFiles($this->upstreamPath('test/docx/golden'), 'docx'),
            ],
            'packageBytePolicy' => 'filesystem names/counts only; preflight does not read DOCX package bytes',
        ];
    }

    /**
     * @param array<string, mixed> $sourcePreflight
     * @return array<string, mixed>
     */
    private function localExecutionReadiness(array $sourcePreflight, bool $sourceReady): array
    {
        $cabalPath = $this->resolvedExecutable('cabal');
        $ghcPath = $this->resolvedExecutable('ghc');
        $freeBytes = $this->resolvedFreeBytes($this->repoRoot);
        $sufficientDisk = $freeBytes === null ? null : $freeBytes >= self::MINIMUM_SUGGESTED_FREE_BYTES;
        $pinnedSource = is_array($sourcePreflight['pinnedSource'] ?? null) ? $sourcePreflight['pinnedSource'] : [];
        $pinnedSourceMatched = ($pinnedSource['matchesPinnedCommit'] ?? false) === true;

        $blockers = [];
        $blockerCodes = [];
        $blockerEvidence = [];
        $addBlocker = static function (string $code, string $message, array $evidence = []) use (&$blockers, &$blockerCodes, &$blockerEvidence): void {
            $blockers[] = $message;
            $blockerCodes[] = $code;
            $blockerEvidence[] = ['code' => $code, 'message' => $message] + $evidence;
        };

        if (!$sourceReady) {
            $missingFiles = array_values(array_map('strval', $sourcePreflight['missingFiles'] ?? []));
            $missingDirectories = array_values(array_map('strval', $sourcePreflight['missingDirectories'] ?? []));
            $addBlocker(self::LOCAL_READINESS_BLOCKER_MISSING_SOURCE, 'missing DOCX upstream source paths under '
                . $this->displayPath($this->upstreamRoot)
                . ': files='
                . implode(',', $missingFiles)
                . '; directories='
                . implode(',', $missingDirectories), [
                    'upstreamRoot' => $this->displayPath($this->upstreamRoot),
                    'missingFiles' => $missingFiles,
                    'missingDirectories' => $missingDirectories,
                ]);
        }
        if ($sourceReady && !$pinnedSourceMatched) {
            $addBlocker(self::LOCAL_READINESS_BLOCKER_UNVERIFIED_PINNED_SOURCE, 'pinned upstream commit is not verified for targeted runner admission: status='
                . (string) ($pinnedSource['status'] ?? 'unknown')
                . '; expectedCommit='
                . self::PINNED_UPSTREAM_COMMIT
                . '; observedCommit='
                . (string) ($pinnedSource['observedCommit'] ?? 'none'), [
                    'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
                    'observedCommit' => $pinnedSource['observedCommit'] ?? null,
                    'pinnedSourceStatus' => $pinnedSource['status'] ?? 'unknown',
                ]);
        }
        if ($cabalPath === null) {
            $addBlocker(self::LOCAL_READINESS_BLOCKER_MISSING_CABAL, 'cabal executable not found on PATH', [
                'tool' => 'cabal',
            ]);
        }
        if ($ghcPath === null) {
            $addBlocker(self::LOCAL_READINESS_BLOCKER_MISSING_GHC, 'ghc executable not found on PATH', [
                'tool' => 'ghc',
            ]);
        }
        if ($sufficientDisk === false) {
            $addBlocker(self::LOCAL_READINESS_BLOCKER_INSUFFICIENT_DISK, 'available disk space is below the suggested targeted-runner workspace floor: freeBytes='
                . (string) $freeBytes
                . '; minimumSuggestedFreeBytes='
                . (string) self::MINIMUM_SUGGESTED_FREE_BYTES, [
                    'freeBytes' => $freeBytes,
                    'minimumSuggestedFreeBytes' => self::MINIMUM_SUGGESTED_FREE_BYTES,
                ]);
        }

        return [
            'schemaVersion' => 1,
            'evidenceKind' => self::LOCAL_READINESS_EVIDENCE_KIND,
            'status' => $blockers === [] ? self::LOCAL_READINESS_STATUS_READY : self::LOCAL_READINESS_STATUS_BLOCKED,
            'runnerExecutionAttemptedByThisTool' => false,
            'resultRecordedByThisTool' => false,
            'checks' => [
                'sourcePreflightReady' => $sourceReady,
                'upstreamRootPresent' => (bool) ($sourcePreflight['upstreamRootPresent'] ?? false),
                'pinnedSourceStatus' => $pinnedSource['status'] ?? null,
                'sourceCommit' => $pinnedSource['observedCommit'] ?? null,
                'sourceCommitMatchesPinned' => $pinnedSourceMatched,
                'gitExecutable' => $pinnedSource['gitExecutable'] ?? null,
                'requiredPathGitStatusEntries' => $pinnedSource['requiredPathStatusEntries'] ?? [],
                'cabalExecutable' => $cabalPath,
                'ghcExecutable' => $ghcPath,
                'freeBytes' => $freeBytes,
                'minimumSuggestedFreeBytes' => self::MINIMUM_SUGGESTED_FREE_BYTES,
                'sufficientDiskForTargetedWorkspace' => $sufficientDisk,
            ],
            'blockerCodes' => $blockerCodes,
            'blockerEvidence' => $blockerEvidence,
            'blockers' => $blockers,
            'claim' => 'Local source/tool/disk readiness for a possible targeted DOCX Cabal/Tasty run only; this does not execute the runner or admit artifacts.',
        ];
    }

    /**
     * @param array<string, mixed> $sourcePreflight
     * @return array<string, mixed>
     */
    private function selectedTestInventory(array $sourcePreflight, bool $ready): array
    {
        $base = [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-upstream-runner-plan',
            'status' => $ready ? self::SELECTED_TEST_INVENTORY_STATUS_REPORTED : self::SELECTED_TEST_INVENTORY_STATUS_BLOCKED,
            'evidenceKind' => self::SELECTED_TEST_INVENTORY_EVIDENCE_KIND,
            'runnerExecuted' => false,
            'cabalExecuted' => false,
            'docxPackageBytesRead' => false,
            'generatedAt' => gmdate('Y-m-d'),
            'upstream' => [
                'pinnedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'expectedCheckoutRoot' => $this->displayPath($this->upstreamRoot),
                'sourceCommit' => $sourcePreflight['pinnedSource']['observedCommit'] ?? null,
                'sourceCommitMatchesPinned' => $sourcePreflight['pinnedSource']['matchesPinnedCommit'] ?? false,
                'sourceCommitEvidenceStatus' => $sourcePreflight['pinnedSource']['status'] ?? null,
            ],
            'selection' => [
                'runnerTarget' => self::RUNNER_TARGET,
                'tastyPattern' => self::TASTY_PATTERN,
                'selectedGroups' => self::SELECTED_GROUPS,
            ],
            'claim' => 'Static selected DOCX reader/writer source and fixture inventory only; this is not Tasty --list-tests output and not an upstream DOCX runner result.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'hydrated checkout contains the selected DOCX reader/writer source files required by the planned Tasty pattern',
                    'filesystem-level names, stems, and byte sizes for selected DOCX root and writer-golden fixtures',
                    'candidate static source labels extracted from DOCX Haskell test source text',
                ],
                'doesNotAssert' => [
                    'that Cabal, Tasty, or upstream Pandoc tests were executed',
                    'that the static label hints equal final Tasty --list-tests output',
                    'that any selected DOCX reader or writer test passes',
                    'that DOCX package bytes were read, generated, or compared',
                    'full DOCX/OpenXML parity',
                ],
            ],
            'sourcePreflightSummary' => [
                'upstreamRootPresent' => $sourcePreflight['upstreamRootPresent'] ?? false,
                'missingFiles' => $sourcePreflight['missingFiles'] ?? [],
                'missingDirectories' => $sourcePreflight['missingDirectories'] ?? [],
            ],
        ];

        if (!$ready) {
            $inventory = $base + [
                'skipped' => true,
                'reason' => 'Required DOCX runner source paths or fixture directories are missing.',
                'sourceGroups' => [],
                'fixtures' => $this->emptyFixtureInventory(),
                'staticCandidateTestLabelCount' => 0,
            ];
            $inventory['inventorySha256'] = hash('sha256', self::canonicalJson($inventory));

            return $inventory;
        }

        $sourceGroups = [
            $this->sourceGroupInventory(
                'reader',
                'Tests.Readers.Docx',
                'Tests.Readers.Docx.tests',
                'test/Tests/Readers/Docx.hs'
            ),
            $this->sourceGroupInventory(
                'writer',
                'Tests.Writers.Docx',
                'Tests.Writers.Docx.tests',
                'test/Tests/Writers/Docx.hs'
            ),
        ];
        $staticCandidateTestLabelCount = array_sum(array_map(
            static fn (array $group): int => count($group['candidateStaticLabels'] ?? []),
            $sourceGroups
        ));

        $inventory = $base + [
            'skipped' => false,
            'sourceGroups' => $sourceGroups,
            'fixtures' => $this->fixtureInventory(),
            'staticCandidateTestLabelCount' => $staticCandidateTestLabelCount,
            'staticExtractionLimits' => [
                'candidateStaticLabels are quoted string literals found on DOCX-relevant Haskell source lines',
                'dynamic Tasty names, helper-generated cases, and conditional tests still require the future --list-tests runner artifact',
                'DOCX package files are inventoried by filename and filesystem size only; package bytes are not read or hashed',
            ],
        ];
        $inventory['inventorySha256'] = hash('sha256', self::canonicalJson($inventory));

        return $inventory;
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceGroupInventory(string $kind, string $module, string $entryPoint, string $sourceFile): array
    {
        $path = $this->upstreamPath($sourceFile);
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            return [
                'kind' => $kind,
                'module' => $module,
                'entryPointSnippet' => $entryPoint,
                'sourceFile' => $sourceFile,
                'present' => false,
                'sourceBytes' => 0,
                'sourceSha256' => null,
                'candidateStaticLabels' => [],
            ];
        }

        return [
            'kind' => $kind,
            'module' => $module,
            'entryPointSnippet' => $entryPoint,
            'sourceFile' => $sourceFile,
            'present' => true,
            'sourceBytes' => strlen($contents),
            'sourceSha256' => hash('sha256', $contents),
            'candidateStaticLabels' => self::candidateStaticLabels($contents),
        ];
    }

    /**
     * @return list<array{label:string,line:int}>
     */
    private static function candidateStaticLabels(string $contents): array
    {
        $labels = [];
        $seen = [];
        $lines = preg_split('/\R/', $contents);
        if (!is_array($lines)) {
            return [];
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/\b(?:testGroup|testCase|test|golden|Golden|docx|Docx|native|Native)\b/', $line) !== 1) {
                continue;
            }
            if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $line, $matches) !== false) {
                foreach ($matches[1] as $label) {
                    if ($label === '' || isset($seen[$label])) {
                        continue;
                    }
                    $seen[$label] = true;
                    $labels[] = [
                        'label' => $label,
                        'line' => $index + 1,
                    ];
                }
            }
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureInventory(): array
    {
        $rootDocxPackages = $this->directFileRows($this->upstreamPath('test/docx'), 'test/docx', 'docx');
        $rootNativeExpectations = $this->directFileRows($this->upstreamPath('test/docx'), 'test/docx', 'native');
        $goldenDocxPackages = $this->directFileRows($this->upstreamPath('test/docx/golden'), 'test/docx/golden', 'docx');
        $rootDocxStems = array_column($rootDocxPackages, 'stem');
        $rootNativeStems = array_column($rootNativeExpectations, 'stem');
        $pairedStems = array_values(array_intersect($rootDocxStems, $rootNativeStems));
        $unpairedDocxStems = array_values(array_diff($rootDocxStems, $rootNativeStems));
        $unpairedNativeStems = array_values(array_diff($rootNativeStems, $rootDocxStems));
        sort($pairedStems);
        sort($unpairedDocxStems);
        sort($unpairedNativeStems);

        return [
            'counts' => [
                'rootDocxPackageFiles' => count($rootDocxPackages),
                'rootNativeExpectedFiles' => count($rootNativeExpectations),
                'pairedRootDocxNativeStems' => count($pairedStems),
                'unpairedRootDocxPackageStems' => count($unpairedDocxStems),
                'unpairedRootNativeExpectedStems' => count($unpairedNativeStems),
                'goldenDocxPackageFiles' => count($goldenDocxPackages),
            ],
            'pairedRootDocxNativeStems' => $pairedStems,
            'unpairedRootDocxPackageStems' => $unpairedDocxStems,
            'unpairedRootNativeExpectedStems' => $unpairedNativeStems,
            'rootDocxPackages' => $rootDocxPackages,
            'rootNativeExpectations' => $rootNativeExpectations,
            'goldenDocxPackages' => $goldenDocxPackages,
            'packageBytePolicy' => 'filesystem names and byte sizes only; DOCX package bytes are not read or hashed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFixtureInventory(): array
    {
        return [
            'counts' => [
                'rootDocxPackageFiles' => 0,
                'rootNativeExpectedFiles' => 0,
                'pairedRootDocxNativeStems' => 0,
                'unpairedRootDocxPackageStems' => 0,
                'unpairedRootNativeExpectedStems' => 0,
                'goldenDocxPackageFiles' => 0,
            ],
            'pairedRootDocxNativeStems' => [],
            'unpairedRootDocxPackageStems' => [],
            'unpairedRootNativeExpectedStems' => [],
            'rootDocxPackages' => [],
            'rootNativeExpectations' => [],
            'goldenDocxPackages' => [],
            'packageBytePolicy' => 'filesystem names and byte sizes only; DOCX package bytes are not read or hashed',
        ];
    }

    /**
     * @return list<array{path:string,fileName:string,stem:string,extension:string,bytes:int}>
     */
    private function directFileRows(string $directory, string $relativeDirectory, string $extension): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $rows = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || !$entry->isFile() || strtolower($entry->getExtension()) !== $extension) {
                continue;
            }

            $fileName = $entry->getFilename();
            $rows[] = [
                'path' => $relativeDirectory . '/' . $fileName,
                'fileName' => $fileName,
                'stem' => pathinfo($fileName, PATHINFO_FILENAME),
                'extension' => $extension,
                'bytes' => $entry->getSize(),
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => $left['path'] <=> $right['path']
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function resultArtifactGate(
        string $artifactRoot = self::DEFAULT_RELATIVE_ARTIFACT_ROOT,
        string $logRoot = self::DEFAULT_RELATIVE_LOG_ROOT
    ): array {
        if ($artifactRoot === '') {
            throw new \InvalidArgumentException('Runner artifact root must not be empty');
        }
        if ($logRoot === '') {
            throw new \InvalidArgumentException('Runner log root must not be empty');
        }

        $artifactRootAbsolute = $this->absolutePath($artifactRoot);
        $logRootAbsolute = $this->absolutePath($logRoot);
        $artifacts = [
            'dependencyDryRunTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'runner-test-dependencies.txt'
            ),
            'listTestsTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'docx-targeted-list-tests.txt'
            ),
            'targetedRunTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'docx-targeted-run.txt'
            ),
            'selectedTestInventory' => $this->artifactFileRow(
                $artifactRootAbsolute . DIRECTORY_SEPARATOR . 'selected-test-inventory.json'
            ),
            'resultJson' => $this->artifactFileRow(
                $artifactRootAbsolute . DIRECTORY_SEPARATOR . 'result.json'
            ),
        ];

        $problems = [];
        foreach ($artifacts as $name => $row) {
            if (($row['present'] ?? false) !== true) {
                $problems[] = "Missing required artifact: {$name}";
                continue;
            }
            if (($row['readable'] ?? false) !== true) {
                $problems[] = "Required artifact is not readable: {$name}";
                continue;
            }
            if ((int) ($row['bytes'] ?? 0) <= 0) {
                $problems[] = "Required artifact is empty: {$name}";
            }
        }

        $selectedInventory = $this->decodeJsonArtifact($artifacts['selectedTestInventory']['absolutePath'], 'selectedTestInventory', $problems);
        if (is_array($selectedInventory)) {
            $this->validateSelectedInventoryArtifact($selectedInventory, $problems);
        }

        $result = $this->decodeJsonArtifact($artifacts['resultJson']['absolutePath'], 'resultJson', $problems);
        if (is_array($result)) {
            $this->validateResultJson($result, $artifacts, $problems);
        }

        $missing = array_filter(
            $artifacts,
            static fn (array $row): bool => ($row['present'] ?? false) !== true
                || ($row['readable'] ?? false) !== true
                || (int) ($row['bytes'] ?? 0) <= 0
        );
        $admissionReady = $missing === [] && $problems === [];
        $status = $admissionReady
            ? self::RESULT_ARTIFACT_GATE_STATUS_ADMISSIBLE
            : ($missing !== [] ? self::RESULT_ARTIFACT_GATE_STATUS_MISSING : self::RESULT_ARTIFACT_GATE_STATUS_INVALID);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-upstream-runner-plan',
            'status' => $status,
            'evidenceKind' => self::RESULT_ARTIFACT_GATE_EVIDENCE_KIND,
            'admissionReady' => $admissionReady,
            'runnerExecutedByThisTool' => false,
            'resultRecordedByThisTool' => false,
            'runnerExecutedClaimFromResult' => is_array($result) ? ($result['runnerExecuted'] ?? null) : null,
            'artifactRoot' => $this->displayPath($artifactRootAbsolute),
            'logRoot' => $this->displayPath($logRootAbsolute),
            'requiredArtifacts' => $artifacts,
            'evidenceGap' => $admissionReady
                ? null
                : 'No admissible targeted Cabal/Tasty DOCX runner result is present; missing or invalid artifacts remain a hard evidence gap.',
            'resultSummary' => is_array($result) ? [
                'upstreamCommit' => $result['upstreamCommit'] ?? null,
                'exitCode' => $result['exitCode'] ?? null,
                'selectedTestCount' => $result['selectedTestCount'] ?? null,
                'passedCount' => $result['passedCount'] ?? null,
                'failedCount' => $result['failedCount'] ?? null,
                'skippedCount' => $result['skippedCount'] ?? null,
            ] : null,
            'problems' => $problems,
            'claim' => 'Validates targeted DOCX runner artifact shape and hash binding only; it does not execute Cabal/Tasty or independently prove runner results.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'required transcript, selected inventory, and result JSON artifacts are present and structurally valid when admissionReady=true',
                    'result JSON references the pinned DOCX runner target, Tasty pattern, counts, and artifact SHA-256 hashes',
                    'transcripts include the exact Cabal command line, exit-code marker, and Tasty DOCX list/run output required by the artifact contract',
                ],
                'doesNotAssert' => [
                    'that this PHP tool executed Cabal, Tasty, or upstream Pandoc',
                    'that a runner result is authentic without external transcript review',
                    'that this local machine currently has hydrated upstream source, Cabal/GHC tooling, or enough disk to reproduce the run',
                    'that selected DOCX reader or writer tests pass unless the result artifact records those counts',
                    'writer package parity or full DOCX/OpenXML parity',
                ],
            ],
        ];
    }

    /**
     * Build a runner result artifact from existing transcript files.
     *
     * This method intentionally does not execute Cabal/Tasty. It requires the
     * workflow wrapper to have captured the exact planned commands first, then
     * binds result.json to those files by SHA-256 for resultArtifactGate().
     *
     * @return array<string, mixed>
     */
    public function resultArtifactFromTranscripts(
        string $artifactRoot = self::DEFAULT_RELATIVE_ARTIFACT_ROOT,
        string $logRoot = self::DEFAULT_RELATIVE_LOG_ROOT,
        string $startedAtUtc = '',
        string $finishedAtUtc = ''
    ): array {
        if ($artifactRoot === '') {
            throw new \InvalidArgumentException('Runner artifact root must not be empty');
        }
        if ($logRoot === '') {
            throw new \InvalidArgumentException('Runner log root must not be empty');
        }
        if (!self::validUtcTimestamp($startedAtUtc)) {
            throw new \InvalidArgumentException('Result startedAtUtc must be an ISO-8601 UTC timestamp');
        }
        if (!self::validUtcTimestamp($finishedAtUtc)) {
            throw new \InvalidArgumentException('Result finishedAtUtc must be an ISO-8601 UTC timestamp');
        }
        $started = self::parseUtcTimestamp($startedAtUtc);
        $finished = self::parseUtcTimestamp($finishedAtUtc);
        if ($started !== null && $finished !== null && $finished < $started) {
            throw new \InvalidArgumentException('Result finishedAtUtc must not be earlier than startedAtUtc');
        }

        $artifactRootAbsolute = $this->absolutePath($artifactRoot);
        $logRootAbsolute = $this->absolutePath($logRoot);
        $artifacts = [
            'dependencyDryRunTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'runner-test-dependencies.txt'
            ),
            'listTestsTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'docx-targeted-list-tests.txt'
            ),
            'targetedRunTranscript' => $this->artifactFileRow(
                $logRootAbsolute . DIRECTORY_SEPARATOR . 'docx-targeted-run.txt'
            ),
            'selectedTestInventory' => $this->artifactFileRow(
                $artifactRootAbsolute . DIRECTORY_SEPARATOR . 'selected-test-inventory.json'
            ),
        ];

        $problems = [];
        foreach ($artifacts as $name => $row) {
            if (($row['present'] ?? false) !== true) {
                $problems[] = "Missing required artifact: {$name}";
                continue;
            }
            if (($row['readable'] ?? false) !== true) {
                $problems[] = "Required artifact is not readable: {$name}";
                continue;
            }
            if ((int) ($row['bytes'] ?? 0) <= 0) {
                $problems[] = "Required artifact is empty: {$name}";
            }
        }

        $selectedInventory = $this->decodeJsonArtifact(
            $artifacts['selectedTestInventory']['absolutePath'],
            'selectedTestInventory',
            $problems
        );
        if (is_array($selectedInventory)) {
            $this->validateSelectedInventoryArtifact($selectedInventory, $problems);
        }

        $commands = $this->commands();
        $dependencyTranscript = $this->artifactContents($artifacts['dependencyDryRunTranscript']['absolutePath'] ?? null);
        $listTestsTranscript = $this->artifactContents($artifacts['listTestsTranscript']['absolutePath'] ?? null);
        $targetedRunTranscript = $this->artifactContents($artifacts['targetedRunTranscript']['absolutePath'] ?? null);

        $this->requireTranscriptContains(
            $dependencyTranscript,
            $commands['dependencyDryRun']['commandLine'],
            'dependency dry-run transcript',
            'the exact Cabal dry-run command line',
            $problems
        );
        $this->requireTranscriptExitCode(
            $dependencyTranscript,
            0,
            'dependency dry-run transcript',
            'the expected successful dependency dry-run exitCode marker',
            $problems
        );
        $this->requireTranscriptContains(
            $listTestsTranscript,
            $commands['listDocxTests']['commandLine'],
            'list-tests transcript',
            'the exact Cabal/Tasty --list-tests command line',
            $problems
        );
        $this->requireTranscriptExitCode(
            $listTestsTranscript,
            0,
            'list-tests transcript',
            'the expected successful --list-tests exitCode marker',
            $problems
        );
        $this->requireTranscriptContains(
            $targetedRunTranscript,
            $commands['targetedDocxRun']['commandLine'],
            'targeted-run transcript',
            'the exact targeted Cabal/Tasty command line',
            $problems
        );

        $targetedExitCode = self::transcriptExitCode($targetedRunTranscript);
        if ($targetedExitCode === null || $targetedExitCode < 0) {
            $problems[] = 'targeted-run transcript must include a non-negative exitCode marker';
            $targetedExitCode = 0;
        }

        $selectedTestCount = $this->countDocxListTestLines($listTestsTranscript);
        if ($selectedTestCount === 0) {
            $problems[] = 'list-tests transcript must contain DOCX Tasty test labels before writing result.json';
        }
        $counts = $this->tastyResultOutcomeCounts($targetedRunTranscript, $selectedTestCount);
        $observedResultCount = $counts['passedCount'] + $counts['failedCount'] + $counts['skippedCount'];
        if ($observedResultCount === 0) {
            $problems[] = 'targeted-run transcript must contain Tasty DOCX outcome lines before writing result.json';
        } elseif ($selectedTestCount > 0 && $observedResultCount !== $selectedTestCount) {
            $problems[] = 'targeted-run transcript outcome count must match the DOCX --list-tests count before writing result.json';
        }
        if ($targetedExitCode === 0 && $counts['failedCount'] > 0) {
            $problems[] = 'targeted-run transcript records failures but exitCode is 0';
        }

        if ($problems !== []) {
            throw new \RuntimeException('Cannot write targeted DOCX runner result artifact: ' . implode('; ', $problems));
        }

        return [
            'schemaVersion' => 1,
            'evidenceKind' => self::RESULT_ARTIFACT_EVIDENCE_KIND,
            'runnerExecuted' => true,
            'upstreamCommit' => self::PINNED_UPSTREAM_COMMIT,
            'commandLine' => $commands['targetedDocxRun']['commandLine'],
            'exitCode' => $targetedExitCode,
            'runnerTarget' => self::RUNNER_TARGET,
            'tastyPattern' => self::TASTY_PATTERN,
            'selectedTestCount' => $selectedTestCount,
            'passedCount' => $counts['passedCount'],
            'failedCount' => $counts['failedCount'],
            'skippedCount' => $counts['skippedCount'],
            'startedAtUtc' => $startedAtUtc,
            'finishedAtUtc' => $finishedAtUtc,
            'selectedTestInventorySha256' => $artifacts['selectedTestInventory']['sha256'],
            'dependencyDryRunTranscriptSha256' => $artifacts['dependencyDryRunTranscript']['sha256'],
            'listTestsTranscriptSha256' => $artifacts['listTestsTranscript']['sha256'],
            'targetedRunTranscriptSha256' => $artifacts['targetedRunTranscript']['sha256'],
            'claim' => 'Result artifact assembled from captured targeted Cabal/Tasty DOCX transcripts; this PHP tool did not execute Cabal/Tasty.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function artifactFileRow(string $absolutePath): array
    {
        $present = is_file($absolutePath);
        $readable = $present && is_readable($absolutePath);
        $contents = $readable ? file_get_contents($absolutePath) : false;

        return [
            'path' => $this->displayPath($absolutePath),
            'absolutePath' => $absolutePath,
            'present' => $present,
            'readable' => $readable,
            'bytes' => $contents === false ? 0 : strlen($contents),
            'sha256' => $contents === false ? null : hash('sha256', $contents),
        ];
    }

    /**
     * @param list<string> $problems
     * @return array<string, mixed>|null
     */
    private function decodeJsonArtifact(string $absolutePath, string $artifactName, array &$problems): ?array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($absolutePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $problems[] = "{$artifactName} is not valid JSON: " . $exception->getMessage();

            return null;
        }

        if (!is_array($decoded)) {
            $problems[] = "{$artifactName} must decode to a JSON object";

            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $selectedInventory
     * @param list<string> $problems
     */
    private function validateSelectedInventoryArtifact(array $selectedInventory, array &$problems): void
    {
        if (($selectedInventory['evidenceKind'] ?? null) !== self::SELECTED_TEST_INVENTORY_EVIDENCE_KIND) {
            $problems[] = 'Selected test inventory artifact has an unexpected evidenceKind';
        }
        if (($selectedInventory['status'] ?? null) !== self::SELECTED_TEST_INVENTORY_STATUS_REPORTED) {
            $problems[] = 'Selected test inventory artifact status must be reported before gating a runner result';
        }
        if (($selectedInventory['skipped'] ?? true) !== false) {
            $problems[] = 'Selected test inventory artifact is skipped and cannot gate a runner result';
        }
        foreach (['runnerExecuted', 'cabalExecuted', 'docxPackageBytesRead'] as $field) {
            if (($selectedInventory[$field] ?? null) !== false) {
                $problems[] = "Selected test inventory artifact must record {$field}=false";
            }
        }

        $upstream = $selectedInventory['upstream'] ?? null;
        if (!is_array($upstream) || ($upstream['pinnedCommit'] ?? null) !== self::PINNED_UPSTREAM_COMMIT) {
            $problems[] = 'Selected test inventory artifact upstream pinnedCommit does not match the DOCX runner commit';
        }
        if (!is_array($upstream) || ($upstream['sourceCommit'] ?? null) !== self::PINNED_UPSTREAM_COMMIT) {
            $problems[] = 'Selected test inventory artifact sourceCommit must match the pinned DOCX runner commit';
        }
        if (!is_array($upstream) || ($upstream['sourceCommitMatchesPinned'] ?? null) !== true) {
            $problems[] = 'Selected test inventory artifact sourceCommitMatchesPinned must be true before gating a runner result';
        }

        $selection = $selectedInventory['selection'] ?? null;
        if (!is_array($selection)) {
            $problems[] = 'Selected test inventory artifact is missing selection metadata';

            return;
        }
        if (($selection['runnerTarget'] ?? null) !== self::RUNNER_TARGET) {
            $problems[] = 'Selected test inventory artifact runnerTarget does not match the targeted DOCX runner';
        }
        if (($selection['tastyPattern'] ?? null) !== self::TASTY_PATTERN) {
            $problems[] = 'Selected test inventory artifact tastyPattern does not match the targeted DOCX pattern';
        }
        if (($selection['selectedGroups'] ?? null) !== self::SELECTED_GROUPS) {
            $problems[] = 'Selected test inventory artifact selectedGroups must be the DOCX reader and writer groups';
        }
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, array<string, mixed>> $artifacts
     * @param list<string> $problems
     */
    private function validateResultJson(array $result, array $artifacts, array &$problems): void
    {
        foreach ($this->resultArtifactContract()['resultJsonRequiredFields'] as $field) {
            if (!array_key_exists($field, $result)) {
                $problems[] = "result.json is missing required field: {$field}";
            }
        }

        if (($result['runnerExecuted'] ?? null) !== true) {
            $problems[] = 'result.json must record runnerExecuted=true before a runner result can be admitted';
        }
        if (($result['upstreamCommit'] ?? null) !== self::PINNED_UPSTREAM_COMMIT) {
            $problems[] = 'result.json upstreamCommit does not match the pinned DOCX runner commit';
        }
        if (($result['runnerTarget'] ?? null) !== self::RUNNER_TARGET) {
            $problems[] = 'result.json runnerTarget does not match the targeted DOCX runner';
        }
        if (($result['tastyPattern'] ?? null) !== self::TASTY_PATTERN) {
            $problems[] = 'result.json tastyPattern does not match the targeted DOCX pattern';
        }

        foreach (['exitCode', 'selectedTestCount', 'passedCount', 'failedCount', 'skippedCount'] as $field) {
            if (!array_key_exists($field, $result) || !is_int($result[$field]) || $result[$field] < 0) {
                $problems[] = "result.json {$field} must be a non-negative integer";
            }
        }

        if (
            is_int($result['selectedTestCount'] ?? null)
            && is_int($result['passedCount'] ?? null)
            && is_int($result['failedCount'] ?? null)
            && is_int($result['skippedCount'] ?? null)
            && $result['selectedTestCount'] !== $result['passedCount'] + $result['failedCount'] + $result['skippedCount']
        ) {
            $problems[] = 'result.json selectedTestCount must equal passedCount + failedCount + skippedCount';
        }
        if (is_int($result['selectedTestCount'] ?? null) && $result['selectedTestCount'] === 0) {
            $problems[] = 'result.json selectedTestCount must be greater than zero for a targeted DOCX runner result';
        }

        $expectedHashes = [
            'selectedTestInventorySha256' => $artifacts['selectedTestInventory']['sha256'] ?? null,
            'dependencyDryRunTranscriptSha256' => $artifacts['dependencyDryRunTranscript']['sha256'] ?? null,
            'listTestsTranscriptSha256' => $artifacts['listTestsTranscript']['sha256'] ?? null,
            'targetedRunTranscriptSha256' => $artifacts['targetedRunTranscript']['sha256'] ?? null,
        ];
        foreach ($expectedHashes as $field => $expectedHash) {
            if (is_string($expectedHash) && ($result[$field] ?? null) !== $expectedHash) {
                $problems[] = "result.json {$field} does not match the recorded artifact SHA-256";
            }
        }

        $expectedCommandLine = $this->commands()['targetedDocxRun']['commandLine'];
        if (!is_string($result['commandLine'] ?? null)) {
            $problems[] = 'result.json commandLine must be a string';
        } elseif ($result['commandLine'] !== $expectedCommandLine) {
            $problems[] = 'result.json commandLine must exactly match the targeted DOCX runner command descriptor';
        }

        $timestamps = [];
        foreach (['startedAtUtc', 'finishedAtUtc'] as $field) {
            if (!is_string($result[$field] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result[$field]) !== 1) {
                $problems[] = "result.json {$field} must be an ISO-8601 UTC timestamp";
                continue;
            }
            $timestamp = self::parseUtcTimestamp($result[$field]);
            if ($timestamp === null) {
                $problems[] = "result.json {$field} must be a valid UTC timestamp";
                continue;
            }
            $timestamps[$field] = $timestamp;
        }
        if (
            isset($timestamps['startedAtUtc'], $timestamps['finishedAtUtc'])
            && $timestamps['finishedAtUtc'] < $timestamps['startedAtUtc']
        ) {
            $problems[] = 'result.json finishedAtUtc must not be earlier than startedAtUtc';
        }

        $this->validateTranscriptEvidence($result, $artifacts, $problems);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, array<string, mixed>> $artifacts
     * @param list<string> $problems
     */
    private function validateTranscriptEvidence(array $result, array $artifacts, array &$problems): void
    {
        $commands = $this->commands();
        $dependencyTranscript = $this->artifactContents($artifacts['dependencyDryRunTranscript']['absolutePath'] ?? null);
        $listTestsTranscript = $this->artifactContents($artifacts['listTestsTranscript']['absolutePath'] ?? null);
        $targetedRunTranscript = $this->artifactContents($artifacts['targetedRunTranscript']['absolutePath'] ?? null);

        $this->requireTranscriptContains(
            $dependencyTranscript,
            $commands['dependencyDryRun']['commandLine'],
            'dependency dry-run transcript',
            'the exact Cabal dry-run command line',
            $problems
        );
        $this->requireTranscriptExitCode(
            $dependencyTranscript,
            0,
            'dependency dry-run transcript',
            'the expected successful dependency dry-run exitCode marker',
            $problems
        );

        $this->requireTranscriptContains(
            $listTestsTranscript,
            $commands['listDocxTests']['commandLine'],
            'list-tests transcript',
            'the exact Cabal/Tasty --list-tests command line',
            $problems
        );
        $this->requireTranscriptExitCode(
            $listTestsTranscript,
            0,
            'list-tests transcript',
            'the expected successful --list-tests exitCode marker',
            $problems
        );

        $listedDocxTests = $this->countDocxListTestLines($listTestsTranscript);
        if ($listedDocxTests === 0) {
            $problems[] = 'list-tests transcript must contain DOCX Tasty test labels before a runner result can be admitted';
        } elseif (is_int($result['selectedTestCount'] ?? null) && $listedDocxTests !== $result['selectedTestCount']) {
            $problems[] = 'list-tests transcript DOCX test label count must equal result.json selectedTestCount';
        }

        $this->requireTranscriptContains(
            $targetedRunTranscript,
            $commands['targetedDocxRun']['commandLine'],
            'targeted-run transcript',
            'the exact targeted Cabal/Tasty command line',
            $problems
        );
        $this->requireTranscriptExitCode(
            $targetedRunTranscript,
            is_int($result['exitCode'] ?? null) ? $result['exitCode'] : null,
            'targeted-run transcript',
            'an exitCode marker matching result.json exitCode',
            $problems
        );
        if (!$this->hasTastyDocxResultEvidence($targetedRunTranscript)) {
            $problems[] = 'targeted-run transcript must contain Tasty result output for DOCX tests before a runner result can be admitted';
        }
    }

    /**
     * @param list<string> $problems
     */
    private function requireTranscriptContains(
        ?string $contents,
        string $needle,
        string $label,
        string $requirement,
        array &$problems
    ): void {
        if ($contents === null || !str_contains($contents, $needle)) {
            $problems[] = "{$label} must include {$requirement}";
        }
    }

    /**
     * @param list<string> $problems
     */
    private function requireTranscriptExitCode(
        ?string $contents,
        ?int $expectedExitCode,
        string $label,
        string $requirement,
        array &$problems
    ): void {
        if ($expectedExitCode === null) {
            return;
        }

        $observedExitCode = self::transcriptExitCode($contents);
        if ($observedExitCode === null) {
            $problems[] = "{$label} must include {$requirement}";

            return;
        }
        if ($observedExitCode !== $expectedExitCode) {
            $problems[] = "{$label} exitCode marker must match the expected result code";
        }
    }

    private function artifactContents(mixed $absolutePath): ?string
    {
        if (!is_string($absolutePath) || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        return $contents === false ? null : $contents;
    }

    private function countDocxListTestLines(?string $contents): int
    {
        if ($contents === null) {
            return 0;
        }

        $count = 0;
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_contains($trimmed, 'cabal v2-run') || str_starts_with($trimmed, 'exitCode')) {
                continue;
            }
            if (preg_match('/\b(?:Readers|Writers)\.Docx\./', $trimmed) === 1) {
                ++$count;
            }
        }

        return $count;
    }

    private function hasTastyDocxResultEvidence(?string $contents): bool
    {
        if ($contents === null || preg_match('/\b(?:Readers|Writers)\b[\s\S]*\bDocx\b/', $contents) !== 1) {
            return false;
        }

        return preg_match('/(?:^|\R)\s*(?:[^\r\n:]+:\s*)?(?:OK|FAIL|SKIP|All \d+ tests? passed)\b/', $contents) === 1;
    }

    /**
     * @return array{passedCount:int, failedCount:int, skippedCount:int}
     */
    private function tastyResultOutcomeCounts(?string $contents, int $selectedTestCount): array
    {
        $counts = [
            'passedCount' => 0,
            'failedCount' => 0,
            'skippedCount' => 0,
        ];
        if ($contents === null) {
            return $counts;
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/:\s*OK\b/', $trimmed) === 1) {
                ++$counts['passedCount'];
                continue;
            }
            if (preg_match('/:\s*FAIL\b/', $trimmed) === 1) {
                ++$counts['failedCount'];
                continue;
            }
            if (preg_match('/:\s*SKIP(?:PED)?\b/', $trimmed) === 1) {
                ++$counts['skippedCount'];
            }
        }

        $observed = $counts['passedCount'] + $counts['failedCount'] + $counts['skippedCount'];
        if ($observed === 0 && preg_match('/\bAll\s+(\d+)\s+tests?\s+passed\b/', $contents, $matches) === 1) {
            $summaryCount = (int) $matches[1];
            $counts['passedCount'] = $selectedTestCount > 0 && $summaryCount !== $selectedTestCount
                ? $summaryCount
                : $selectedTestCount;
        }

        return $counts;
    }

    private static function transcriptExitCode(?string $contents): ?int
    {
        if ($contents === null) {
            return null;
        }
        if (preg_match('/(?:^|\R)\s*(?:exitCode|exit-code|exit code|EXIT_CODE)\s*[:=]\s*(-?\d+)\s*(?:\R|$)/i', $contents, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function workspace(): array
    {
        return [
            'root' => '.port-libs/pandoc-runner',
            'directories' => [
                'dependencyBuild' => '.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                'targetedRunBuild' => '.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                'logs' => '.port-libs/pandoc-runner/logs',
                'artifacts' => '.port-libs/pandoc-runner/artifacts/docx-targeted-run',
                'tmp' => '.port-libs/pandoc-runner/tmp',
            ],
            'evidenceReports' => [
                'preflightPlan' => '.port-libs/pandoc-runner/artifacts/docx-targeted-run/preflight-plan.json',
                'resultArtifactGate' => '.port-libs/pandoc-runner/artifacts/docx-targeted-run/result-artifact-gate.json',
            ],
            'environmentVariables' => self::WORKSPACE_ENVIRONMENT,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function commands(): array
    {
        return [
            'dependencyDryRun' => $this->commandDescriptor(
                'dependencyDryRun',
                [
                    'v2-build',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                    '--dry-run',
                    '--only-dependencies',
                    '--enable-tests',
                    '--disable-benchmarks',
                    'test:test-pandoc',
                    'test:test-pandoc-lua-engine',
                ],
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                'non-mutating dependency plan only; record transcript before any targeted run'
            ),
            'listDocxTests' => $this->commandDescriptor(
                'listDocxTests',
                [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                    self::RUNNER_TARGET,
                    '--',
                    '--list-tests',
                    '--pattern',
                    self::TASTY_PATTERN,
                ],
                '.port-libs/pandoc-runner/logs/docx-targeted-list-tests.txt',
                'future inventory command only; run after reviewed dependency dry-run'
            ),
            'targetedDocxRun' => $this->commandDescriptor(
                'targetedDocxRun',
                [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                    self::RUNNER_TARGET,
                    '--',
                    '--pattern',
                    self::TASTY_PATTERN,
                ],
                '.port-libs/pandoc-runner/logs/docx-targeted-run.txt',
                'future targeted runner only; not executed by this PHP preflight'
            ),
        ];
    }

    /**
     * @param list<string> $arguments
     * @return array<string, mixed>
     */
    private function commandDescriptor(string $name, array $arguments, string $transcriptFile, string $executionPolicy): array
    {
        return [
            'name' => $name,
            'program' => 'cabal',
            'arguments' => $arguments,
            'commandLine' => self::commandLine(array_merge(['cabal'], $arguments)),
            'workingDirectory' => $this->displayPath($this->upstreamRoot),
            'environmentVariables' => self::WORKSPACE_ENVIRONMENT,
            'transcriptFile' => $transcriptFile,
            'executionPolicy' => $executionPolicy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultArtifactContract(): array
    {
        return [
            'requiredBeforeResultRecorded' => [
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                '.port-libs/pandoc-runner/logs/docx-targeted-list-tests.txt',
                '.port-libs/pandoc-runner/logs/docx-targeted-run.txt',
                '.port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json',
                '.port-libs/pandoc-runner/artifacts/docx-targeted-run/result.json',
            ],
            'ciEvidenceReports' => [
                '.port-libs/pandoc-runner/artifacts/docx-targeted-run/preflight-plan.json',
                '.port-libs/pandoc-runner/artifacts/docx-targeted-run/result-artifact-gate.json',
            ],
            'resultJsonRequiredFields' => [
                'runnerExecuted',
                'upstreamCommit',
                'commandLine',
                'exitCode',
                'runnerTarget',
                'tastyPattern',
                'selectedTestCount',
                'passedCount',
                'failedCount',
                'skippedCount',
                'startedAtUtc',
                'finishedAtUtc',
                'selectedTestInventorySha256',
                'dependencyDryRunTranscriptSha256',
                'listTestsTranscriptSha256',
                'targetedRunTranscriptSha256',
            ],
            'transcriptEvidenceRequirements' => [
                'dependencyDryRunTranscript' => 'must include the exact Cabal dry-run command line and exitCode: 0 marker',
                'listTestsTranscript' => 'must include the exact Cabal/Tasty --list-tests command line, exitCode: 0 marker, and DOCX Tasty test labels',
                'targetedRunTranscript' => 'must include the exact targeted Cabal/Tasty command line, an exitCode marker matching result.json, and DOCX Tasty result output',
            ],
            'admissionRule' => 'Do not set resultRecorded=true until command transcript, exit code, selected test inventory, pass/fail counts, and concrete Tasty DOCX list/run output are captured for the pinned upstream checkout.',
        ];
    }

    /**
     * @return list<string>
     */
    private function activationGate(bool $ready): array
    {
        if (!$ready) {
            return [
                'hydrate Pandoc upstream checkout at ' . self::PINNED_UPSTREAM_COMMIT,
                'restore required DOCX Tasty source files and fixture directories',
                'verify the upstream root HEAD equals ' . self::PINNED_UPSTREAM_COMMIT,
                'rerun this preflight tool before executing Cabal commands',
            ];
        }

        return [
            'verify selectedTestInventory upstream.sourceCommitMatchesPinned=true',
            'record dependencyDryRun transcript first',
            'record listDocxTests transcript before the targeted run',
            'record targetedDocxRun transcript and result.json before claiming any upstream DOCX runner result',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pinnedSourceEvidence(): array
    {
        if (!is_dir($this->upstreamRoot)) {
            return [
                'status' => self::PINNED_SOURCE_STATUS_ROOT_MISSING,
                'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'observedCommit' => null,
                'matchesPinnedCommit' => false,
                'gitExecutable' => self::executableOnPath('git'),
                'requiredPathStatusEntries' => [],
            ];
        }

        $gitPath = self::executableOnPath('git');
        if ($gitPath === null) {
            return [
                'status' => self::PINNED_SOURCE_STATUS_GIT_MISSING,
                'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'observedCommit' => null,
                'matchesPinnedCommit' => false,
                'gitExecutable' => null,
                'requiredPathStatusEntries' => [],
            ];
        }

        $head = self::runLocalCommand(['git', '-C', $this->upstreamRoot, 'rev-parse', 'HEAD']);
        if (($head['exitCode'] ?? 1) !== 0) {
            return [
                'status' => self::PINNED_SOURCE_STATUS_NOT_GIT_CHECKOUT,
                'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'observedCommit' => null,
                'matchesPinnedCommit' => false,
                'gitExecutable' => $gitPath,
                'revParseExitCode' => $head['exitCode'],
                'revParseOutput' => $head['output'],
                'requiredPathStatusEntries' => [],
            ];
        }

        $observedCommit = trim(implode("\n", array_map('strval', $head['output'] ?? [])));
        $matchesPinnedCommit = hash_equals(self::PINNED_UPSTREAM_COMMIT, $observedCommit);
        $status = $matchesPinnedCommit
            ? self::PINNED_SOURCE_STATUS_MATCHED
            : self::PINNED_SOURCE_STATUS_MISMATCHED;
        $requiredPathStatus = self::runLocalCommand(array_merge(
            ['git', '-C', $this->upstreamRoot, 'status', '--short', '--untracked-files=no', '--'],
            self::REQUIRED_FILES,
            self::REQUIRED_DIRECTORIES
        ));

        return [
            'status' => $status,
            'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
            'observedCommit' => $observedCommit,
            'matchesPinnedCommit' => $matchesPinnedCommit,
            'gitExecutable' => $gitPath,
            'requiredPathStatusExitCode' => $requiredPathStatus['exitCode'],
            'requiredPathStatusEntries' => array_values(array_filter(
                array_map('strval', $requiredPathStatus['output'] ?? []),
                static fn (string $line): bool => $line !== ''
            )),
        ];
    }

    /**
     * @param list<string> $missingFiles
     * @param list<string> $missingDirectories
     * @param array<string, mixed> $pinnedSource
     * @return list<array<string, mixed>>
     */
    private function sourceBlockers(array $missingFiles, array $missingDirectories, array $pinnedSource): array
    {
        $blockers = [];
        foreach ($missingFiles as $path) {
            $blockers[] = [
                'kind' => 'missing-required-file',
                'path' => $path,
                'action' => 'hydrate the pinned Pandoc checkout before attempting the DOCX runner',
            ];
        }
        foreach ($missingDirectories as $path) {
            $blockers[] = [
                'kind' => 'missing-required-directory',
                'path' => $path,
                'action' => 'hydrate the pinned Pandoc DOCX fixture directory before attempting the DOCX runner',
            ];
        }
        if (($missingFiles === [] && $missingDirectories === []) && ($pinnedSource['matchesPinnedCommit'] ?? false) !== true) {
            $blockers[] = [
                'kind' => 'unverified-pinned-upstream-commit',
                'expectedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'observedCommit' => $pinnedSource['observedCommit'] ?? null,
                'status' => $pinnedSource['status'] ?? 'unknown',
                'action' => 'checkout the pinned Pandoc commit and rerun the preflight before recording result artifacts',
            ];
        }

        return $blockers;
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrationPlan(): array
    {
        $target = $this->displayPath($this->upstreamRoot);

        return [
            'targetPath' => $target,
            'upstreamCommit' => self::PINNED_UPSTREAM_COMMIT,
            'commands' => [
                'git clone --filter=blob:none https://github.com/jgm/pandoc.git ' . self::shellArgument($target),
                'git -C ' . self::shellArgument($target) . ' checkout ' . self::PINNED_UPSTREAM_COMMIT,
                'git -C ' . self::shellArgument($target) . ' sparse-checkout set cabal.project pandoc.cabal test/test-pandoc.hs test/Tests/Readers/Docx.hs test/Tests/Writers/Docx.hs test/docx data/docx',
                'php tools/pandoc-docx-upstream-runner-plan.php --json --write-selected-inventory .port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json',
            ],
            'note' => 'Hydration commands are descriptors only; this preflight does not clone, fetch, run Cabal, or execute Tasty.',
        ];
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function upstreamPath(string $relativePath): string
    {
        return $this->upstreamRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    private function hasNonEmptyFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $size = filesize($path);

        return $size !== false && $size > 0;
    }

    private function countDirectFiles(string $directory, string $extension): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;
        foreach (new \DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && strtolower($entry->getExtension()) === $extension) {
                ++$count;
            }
        }

        return $count;
    }

    private function resolvedExecutable(string $name): ?string
    {
        if ($this->executableResolver !== null) {
            $resolved = ($this->executableResolver)($name);

            return is_string($resolved) && $resolved !== '' ? $resolved : null;
        }

        return self::executableOnPath($name);
    }

    private function resolvedFreeBytes(string $path): ?int
    {
        if ($this->freeSpaceResolver !== null) {
            $resolved = ($this->freeSpaceResolver)($path);

            return is_int($resolved) && $resolved >= 0 ? $resolved : null;
        }

        $freeBytes = disk_free_space($path);

        return $freeBytes === false ? null : (int) $freeBytes;
    }

    /**
     * @param list<string> $parts
     */
    private static function commandLine(array $parts): string
    {
        return implode(' ', array_map(self::shellArgument(...), $parts));
    }

    private static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::sortForJson($value),
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private static function sortForJson(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sortForJson(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortForJson($item);
        }

        return $value;
    }

    private static function shellArgument(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.,:\/=@%+-]+$/', $value) === 1) {
            return $value;
        }

        return escapeshellarg($value);
    }

    private static function executableOnPath(string $name): ?string
    {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<string> $parts
     * @return array{exitCode:int, output:list<string>}
     */
    private static function runLocalCommand(array $parts): array
    {
        $output = [];
        $exitCode = 0;
        exec(self::commandLine($parts) . ' 2>&1', $output, $exitCode);

        return [
            'exitCode' => $exitCode,
            'output' => array_slice(array_map('strval', $output), 0, 40),
        ];
    }

    private static function boolText(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }

    private static function parseUtcTimestamp(string $value): ?\DateTimeImmutable
    {
        $timestamp = \DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $value,
            new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            is_array($errors)
            && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0)
        ) {
            return null;
        }

        return $timestamp === false ? null : $timestamp;
    }

    private static function validUtcTimestamp(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1
            && self::parseUtcTimestamp($value) !== null;
    }
}
