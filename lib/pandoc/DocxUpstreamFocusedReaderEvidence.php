<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxUpstreamFocusedReaderEvidence
{
    public const DEFAULT_RELATIVE_DOCX_DIR = '.upstream-cache/pandoc-current/test/docx';
    public const CHECKED_IN_REPORT_PATH = 'lanes/pandoc/UPSTREAM_DOCX_HASKELL_FOCUSED_READER_EVIDENCE.json';
    public const CURRENT_UPSTREAM_COMMIT = '612e143fbe6d735b612c4800d21e61b7d44e4dca';
    public const TOOL_NAME = 'pandoc-docx-focused-reader-evidence';
    public const STATUS_REPORTED = 'reported-focused-docx-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-docx-directory';
    public const STATUS_COMPLETED = 'completed-targeted-docx-reader-checks';

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
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $inventory = $this->readInventory();
        $readerInventory = is_array($inventory['haskellReaderInventory'] ?? null)
            ? $inventory['haskellReaderInventory']
            : [];
        $denominatorRows = is_array($readerInventory['notCoveredCases'] ?? null)
            ? $readerInventory['notCoveredCases']
            : [];

        $coveredDefinitions = self::coveredCaseDefinitions();
        $openDefinitions = self::openCaseDefinitions();
        $denominatorKeys = [];
        $caseRows = [];
        $coverageKindCounts = [];
        $coveredCount = 0;
        $remainingOpenCount = 0;

        foreach ($denominatorRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = self::caseKey($row);
            $denominatorKeys[$key] = true;
            $caseRow = [
                'caseKey' => $key,
                'call' => (string) ($row['call'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'docx' => (string) ($row['docx'] ?? ''),
                'native' => is_string($row['native'] ?? null) ? $row['native'] : null,
                'inventoryReason' => (string) ($row['reason'] ?? ''),
            ];

            if (isset($coveredDefinitions[$key])) {
                $definition = $coveredDefinitions[$key];
                $coverageKind = (string) $definition['coverageKind'];
                $caseRow = array_replace($caseRow, [
                    'coverageStatus' => 'covered-by-focused-native-php-or-mapped-evidence',
                    'coverageKind' => $coverageKind,
                    'focusedEvidence' => $definition['focusedEvidence'],
                    'localEvidence' => $definition['localEvidence'],
                    'targetedCheckId' => $definition['targetedCheckId'] ?? null,
                    'limitations' => $definition['limitations'],
                ]);
                ++$coveredCount;
                $coverageKindCounts[$coverageKind] = ($coverageKindCounts[$coverageKind] ?? 0) + 1;
            } else {
                $definition = $openDefinitions[$key] ?? [
                    'openReason' => 'not-yet-mapped-by-focused-docx-reader-evidence',
                    'nextEvidence' => 'Add a focused native PHP behavior check or a mapped native expectation before claiming coverage.',
                ];
                $caseRow = array_replace($caseRow, [
                    'coverageStatus' => 'remaining-open',
                    'openReason' => $definition['openReason'],
                    'nextEvidence' => $definition['nextEvidence'],
                ]);
                ++$remainingOpenCount;
            }

            $caseRows[] = $caseRow;
        }

        ksort($coverageKindCounts);
        $validationIssues = self::mappingValidationIssues($denominatorKeys, $coveredDefinitions, $openDefinitions);
        $targetedChecks = $this->targetedFixtureChecks($caseRows);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_REPORTED,
            'generatedAt' => gmdate('Y-m-d'),
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => (string) ($inventory['upstream']['commit'] ?? self::CURRENT_UPSTREAM_COMMIT),
                'expectedCommit' => self::CURRENT_UPSTREAM_COMMIT,
                'sourceFile' => (string) ($readerInventory['sourceFile'] ?? 'test/Tests/Readers/Docx.hs'),
                'entryPoint' => (string) ($readerInventory['entryPoint'] ?? 'Tests.Readers.Docx.tests'),
            ],
            'denominator' => [
                'inventoryPath' => 'lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json',
                'denominatorField' => 'haskellReaderInventory.notCoveredCases',
                'totalCasesNotCoveredByLocal74GateSemantics' => (int) ($readerInventory['casesNotCoveredByLocal74GateSemantics'] ?? count($denominatorRows)),
                'denominatorCaseRows' => count($denominatorRows),
            ],
            'evidenceKind' => 'focused-native-php-and-mapped-reader-evidence',
            'claim' => 'Maps the 36 upstream DOCX Haskell reader cases outside the local 74/74 parser-acceptance gate to additional focused native PHP checks or mapped native evidence where available.',
            'claimBoundaries' => self::claimBoundaries(),
            'focusedCoverage' => [
                'coveredCaseCount' => $coveredCount,
                'remainingOpenCaseCount' => $remainingOpenCount,
                'coverageKindCounts' => $coverageKindCounts,
                'coveredPercentOf36CaseDenominator' => self::percent($coveredCount, count($denominatorRows)),
                'remainingOpenLabels' => array_values(array_map(
                    static fn (array $row): string => (string) $row['label'],
                    array_filter($caseRows, static fn (array $row): bool => ($row['coverageStatus'] ?? '') === 'remaining-open')
                )),
            ],
            'targetedFixtureChecks' => $targetedChecks,
            'mappingValidation' => [
                'status' => $validationIssues === [] ? 'valid-denominator-map' : 'invalid-denominator-map',
                'issues' => $validationIssues,
            ],
            'caseRows' => $caseRows,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $coverage = is_array($report['focusedCoverage'] ?? null) ? $report['focusedCoverage'] : [];
        $targeted = is_array($report['targetedFixtureChecks'] ?? null) ? $report['targetedFixtureChecks'] : [];
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        $lines = [
            'Pandoc DOCX focused reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Evidence kind: ' . (string) ($report['evidenceKind'] ?? 'unknown'),
            'Denominator: ' . (int) ($denominator['denominatorCaseRows'] ?? 0) . ' upstream reader cases outside the local 74/74 gate',
            'Focused coverage: ' . (int) ($coverage['coveredCaseCount'] ?? 0) . ' covered, ' . (int) ($coverage['remainingOpenCaseCount'] ?? 0) . ' remaining open',
            'Targeted checks: ' . (string) ($targeted['status'] ?? 'unknown')
                . '; passed=' . (int) ($targeted['passedTargetedCaseCount'] ?? 0)
                . '; failed=' . (int) ($targeted['failedTargetedCaseCount'] ?? 0)
                . '; skipped=' . (int) ($targeted['skippedTargetedCaseCount'] ?? 0),
            'No Cabal/Tasty runner result or full DOCX/OpenXML parity is asserted.',
        ];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public static function hasRequiredFocusedCoverage(array $report, int $requiredCoveredCases): bool
    {
        $coverage = is_array($report['focusedCoverage'] ?? null) ? $report['focusedCoverage'] : [];

        return (int) ($coverage['coveredCaseCount'] ?? 0) >= $requiredCoveredCases;
    }

    public static function hasRequiredTargetedChecks(array $report, int $requiredPassedChecks): bool
    {
        $targeted = is_array($report['targetedFixtureChecks'] ?? null) ? $report['targetedFixtureChecks'] : [];

        return (int) ($targeted['passedTargetedCaseCount'] ?? 0) >= $requiredPassedChecks;
    }

    public static function hasTargetedCheckFailures(array $report): bool
    {
        $targeted = is_array($report['targetedFixtureChecks'] ?? null) ? $report['targetedFixtureChecks'] : [];

        return (int) ($targeted['failedTargetedCaseCount'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function readInventory(): array
    {
        $path = $this->repoRoot . '/lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json';
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unable to decode DOCX Haskell inventory manifest');
        }

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $caseRows
     * @return array<string, mixed>
     */
    private function targetedFixtureChecks(array $caseRows): array
    {
        $docxDir = $this->absoluteDocxDirectory();
        if (!is_dir($docxDir)) {
            return [
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'sourceDirectoryPresent' => false,
                'upstreamDocxDirectory' => $this->displayPath($docxDir),
                'passedTargetedCaseCount' => 0,
                'failedTargetedCaseCount' => 0,
                'skippedTargetedCaseCount' => 0,
                'mappedOnlyCaseCount' => 0,
                'caseCheckRows' => [],
            ];
        }

        $rows = [];
        foreach ($caseRows as $caseRow) {
            if (($caseRow['coverageStatus'] ?? '') !== 'covered-by-focused-native-php-or-mapped-evidence') {
                continue;
            }

            $checkId = $caseRow['targetedCheckId'] ?? null;
            if (!is_string($checkId) || $checkId === '') {
                $rows[] = [
                    'caseKey' => $caseRow['caseKey'],
                    'label' => $caseRow['label'],
                    'status' => 'not-applicable-mapped-evidence-only',
                    'reason' => 'This case is covered by checked-in mapped native evidence, not by an optional DOCX package check.',
                ];
                continue;
            }

            $result = $this->runTargetedCheck($checkId);
            $rows[] = array_replace([
                'caseKey' => $caseRow['caseKey'],
                'label' => $caseRow['label'],
                'targetedCheckId' => $checkId,
            ], $result);
        }

        return [
            'status' => self::STATUS_COMPLETED,
            'sourceDirectoryPresent' => true,
            'upstreamDocxDirectory' => $this->displayPath($docxDir),
            'passedTargetedCaseCount' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'passed')),
            'failedTargetedCaseCount' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'failed')),
            'skippedTargetedCaseCount' => count(array_filter($rows, static fn (array $row): bool => str_starts_with((string) ($row['status'] ?? ''), 'skipped'))),
            'mappedOnlyCaseCount' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'not-applicable-mapped-evidence-only')),
            'caseCheckRows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runTargetedCheck(string $checkId): array
    {
        try {
            return match ($checkId) {
                'media:image' => $this->checkMediaFixture('image'),
                'media:textbox_image' => $this->checkMediaFixture('textbox_image'),
                'media:textbox_image_duplicate_encoding' => $this->checkMediaFixture('textbox_image_duplicate_encoding'),
                'media:image_with_textbox_caption' => $this->checkMediaFixture('image_with_textbox_caption'),
                'citation:zotero' => $this->checkCitationFixture('zotero_citations', 'zotero'),
                'citation:mendeley' => $this->checkCitationFixture('mendeley_citations', 'mendeley'),
                'revision:track_changes_insertion:accept' => $this->checkRevisionFixture(
                    'track_changes_insertion',
                    'accept',
                    ['This is a text with two exciting insertions.'],
                    [],
                    ['insertion', 'deletion']
                ),
                'revision:track_changes_insertion:reject' => $this->checkRevisionFixture(
                    'track_changes_insertion',
                    'reject',
                    ['This is a text with insertions.'],
                    [],
                    ['insertion', 'deletion']
                ),
                'revision:track_changes_deletion:accept' => $this->checkRevisionFixture(
                    'track_changes_deletion',
                    'accept',
                    ['This is a text with a deletion.'],
                    [],
                    ['insertion', 'deletion']
                ),
                'revision:track_changes_deletion:reject' => $this->checkRevisionFixture(
                    'track_changes_deletion',
                    'reject',
                    ['This is a text with an excessively modified deletion.'],
                    [],
                    ['insertion', 'deletion']
                ),
                'revision:track_changes_deletion:preserve' => $this->checkRevisionFixture(
                    'track_changes_deletion',
                    'preserve',
                    ['This is a text with an excessively modified deletion.'],
                    ['deletion'],
                    []
                ),
                'revision:track_changes_move:accept' => $this->checkRevisionFixture(
                    'track_changes_move',
                    'accept',
                    ['Here is some text.', 'Here is the text to be moved.', 'Here is some more text.'],
                    [],
                    ['insertion.move-to', 'deletion.move-from']
                ),
                'revision:track_changes_move:reject' => $this->checkRevisionFixture(
                    'track_changes_move',
                    'reject',
                    ['Here is some text.', 'Here is some more text.', 'Here is the text to be moved.'],
                    [],
                    ['insertion.move-to', 'deletion.move-from']
                ),
                'revision:track_changes_move:preserve' => $this->checkRevisionFixture(
                    'track_changes_move',
                    'preserve',
                    ['Here is some text.', 'Here is the text to be moved.', 'Here is some more text.', 'Here is the text to be moved.'],
                    ['insertion.move-to', 'deletion.move-from'],
                    []
                ),
                'revision:paragraph_insertion_deletion:accept' => $this->checkNativeAstFixture(
                    'paragraph_insertion_deletion',
                    ['revisionMode' => 'accept'],
                    'paragraph_insertion_deletion_accept.native'
                ),
                'revision:paragraph_insertion_deletion:reject' => $this->checkNativeAstFixture(
                    'paragraph_insertion_deletion',
                    ['revisionMode' => 'reject'],
                    'paragraph_insertion_deletion_reject.native'
                ),
                'revision:paragraph_insertion_deletion:preserve' => $this->checkNativeAstFixture(
                    'paragraph_insertion_deletion',
                    ['revisionMode' => 'preserve'],
                    'paragraph_insertion_deletion_all.native'
                ),
                'revision:track_changes_scrubbed_metadata:preserve' => $this->checkScrubbedRevisionFixture(),
                'comments:all' => $this->checkCommentsAllFixture(),
                'comments:accept-no-comments' => $this->checkCommentsNoCommentsFixture('accept'),
                'comments:reject-no-comments' => $this->checkCommentsNoCommentsFixture('reject'),
                'warning:comments_warning:accept' => $this->checkCommentsWarningNoWarningsFixture('accept'),
                'warning:comments_warning:reject' => $this->checkCommentsWarningNoWarningsFixture('reject'),
                'warning:comments_warning:all' => $this->checkCommentsWarningAllFixture(),
                'warning:comments-with-styles' => $this->checkCommentsWithStylesWarningsFixture(),
                'custom-style-reference:default' => $this->checkCustomStyleDefaultFixture(),
                'custom-style-reference:styles' => $this->checkNativeAstFixture(
                    'custom-style-reference',
                    ['stylesExtension' => true],
                    'custom-style-with-styles.native'
                ),
                'compact-style-removal:styles' => $this->checkCompactStyleFixture(),
                'metadata:styles' => $this->checkMetadataFixture('metadata', false),
                'metadata_after_normal:styles' => $this->checkMetadataFixture('metadata_after_normal', true),
                default => [
                    'status' => 'failed',
                    'reason' => "Unknown targeted check id: {$checkId}",
                ],
            };
        } catch (\Throwable $throwable) {
            return [
                'status' => 'failed',
                'reason' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMediaFixture(string $stem): array
    {
        $loaded = $this->readDocxFixture($stem);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        /** @var ZipPackage $package */
        $package = $loaded['package'];
        $mediaFiles = $document->attr('meta', [])['docxMediaFiles'] ?? [];
        $images = array_values(array_filter(
            self::flattenNodes($document),
            static fn (AstNode $node): bool => $node->type === 'image'
        ));
        $this->assertTrue(is_array($mediaFiles) && $mediaFiles !== [], "{$stem} should expose DOCX media files");
        $this->assertTrue($images !== [], "{$stem} should expose at least one image node");

        $bag = new MediaBag();
        foreach ($mediaFiles as $partName) {
            $partName = (string) $partName;
            $source = str_starts_with($partName, 'word/') ? substr($partName, strlen('word/')) : $partName;
            $bag->insertMedia($source, null, $package->read($partName));
        }

        $directory = $bag->directory();
        $directorySources = array_column($directory, 'source');
        foreach ($images as $image) {
            $url = (string) $image->attr('url', '');
            $this->assertTrue($url !== '', "{$stem} image should have a URL");
            $this->assertTrue(in_array($url, $directorySources, true), "{$stem} image URL should be loadable from the media bag");
        }

        return [
            'status' => 'passed',
            'fixture' => "{$stem}.docx",
            'details' => [
                'imageUrls' => array_values(array_map(static fn (AstNode $node): string => (string) $node->attr('url', ''), $images)),
                'mediaBagPaths' => array_values(array_column($directory, 'path')),
                'mediaBagByteLengths' => array_values(array_column($directory, 'byteLength')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCitationFixture(string $stem, string $provider): array
    {
        $loaded = $this->readDocxFixture($stem);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $providerSpans = [];
        foreach (self::flattenNodes($document) as $node) {
            if ($node->type !== 'span') {
                continue;
            }
            $attrs = $node->attr('attributes', []);
            if (is_array($attrs) && ($attrs['data-docx-addin-provider'] ?? null) === $provider) {
                $providerSpans[] = $node;
            }
        }

        $this->assertTrue($providerSpans !== [], "{$stem} should expose {$provider} add-in spans");
        $classes = array_values(array_unique(array_merge(...array_map(
            static fn (AstNode $node): array => is_array($node->attr('classes', [])) ? $node->attr('classes', []) : [],
            $providerSpans
        ))));
        $this->assertTrue(in_array('docx-addin-csl-citation', $classes, true), "{$stem} should expose CSL citation add-in spans");

        return [
            'status' => 'passed',
            'fixture' => "{$stem}.docx",
            'details' => [
                'provider' => $provider,
                'providerSpanCount' => count($providerSpans),
                'classes' => $classes,
            ],
        ];
    }

    /**
     * @param list<string> $expectedTexts
     * @param list<string> $requiredClasses
     * @param list<string> $forbiddenClasses
     * @return array<string, mixed>
     */
    private function checkRevisionFixture(
        string $stem,
        string $mode,
        array $expectedTexts,
        array $requiredClasses,
        array $forbiddenClasses
    ): array {
        $loaded = $this->readDocxFixture($stem, ['revisionMode' => $mode]);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $texts = self::blockTexts($document);
        $classes = self::classNames($document);
        $this->assertSame($expectedTexts, $texts, "{$stem} {$mode} revision text mismatch");
        foreach ($requiredClasses as $class) {
            $this->assertTrue(in_array($class, $classes, true), "{$stem} {$mode} missing class {$class}");
        }
        foreach ($forbiddenClasses as $class) {
            $this->assertTrue(!in_array($class, $classes, true), "{$stem} {$mode} should not expose class {$class}");
        }

        return [
            'status' => 'passed',
            'fixture' => "{$stem}.docx",
            'details' => [
                'revisionMode' => $mode,
                'blockTexts' => $texts,
                'classes' => $classes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function checkNativeAstFixture(string $stem, array $options, string $nativeFilename): array
    {
        $loaded = $this->readDocxFixture($stem, $options);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        $nativePath = $this->absoluteDocxDirectory() . DIRECTORY_SEPARATOR . $nativeFilename;
        if (!is_file($nativePath)) {
            return [
                'status' => 'skipped-missing-fixture',
                'fixture' => $nativeFilename,
                'reason' => "Required upstream native expectation is missing: {$nativeFilename}",
            ];
        }

        $native = file_get_contents($nativePath);
        if (!is_string($native)) {
            throw new \RuntimeException("Unable to read native fixture {$nativePath}");
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $expected = (new NativeReader())->read($native);
        $comparison = (new DocxNativeAstComparisonHarness())->compareDocuments($document, $expected);
        $this->assertTrue(
            $comparison['matches'],
            "{$stem}.docx does not match {$nativeFilename}: " . (string) ($comparison['firstDifference'] ?? 'unknown difference')
        );

        return [
            'status' => 'passed',
            'fixture' => "{$stem}.docx",
            'nativeFixture' => $nativeFilename,
            'details' => [
                'readerOptions' => $options,
                'blockTexts' => self::blockTexts($document),
                'normalizedAstFirstDifference' => $comparison['firstDifference'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkScrubbedRevisionFixture(): array
    {
        $loaded = $this->readDocxFixture('track_changes_scrubbed_metadata');
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $spans = array_values(array_filter(
            self::flattenNodes($document),
            static fn (AstNode $node): bool => $node->type === 'span'
        ));
        $classes = self::classNames($document);
        $this->assertTrue(in_array('deletion', $classes, true), 'scrubbed metadata should keep deletion provenance');
        $this->assertTrue(in_array('insertion', $classes, true), 'scrubbed metadata should keep insertion provenance');
        $this->assertTrue(in_array('comment-start', $classes, true), 'scrubbed metadata should keep comment provenance');

        foreach ($spans as $span) {
            $attrs = $span->attr('attributes', []);
            if (!is_array($attrs) || !isset($attrs['author'])) {
                continue;
            }
            $this->assertTrue(!isset($attrs['date']), 'scrubbed review metadata should not invent dates');
        }

        return [
            'status' => 'passed',
            'fixture' => 'track_changes_scrubbed_metadata.docx',
            'details' => [
                'classes' => $classes,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCommentsAllFixture(): array
    {
        $loaded = $this->readDocxFixture('comments');
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $meta = $document->attr('meta', []);
        $classes = self::classNames($document);
        $this->assertTrue((int) ($meta['docxComments'] ?? 0) > 0, 'comments.docx should load comments.xml records');
        $this->assertTrue(in_array('comment-start', $classes, true), 'comments.docx should expose comment-start spans');
        $this->assertTrue(in_array('comment-end', $classes, true), 'comments.docx should expose comment-end spans');

        return [
            'status' => 'passed',
            'fixture' => 'comments.docx',
            'details' => [
                'docxComments' => (int) ($meta['docxComments'] ?? 0),
                'classes' => $classes,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCommentsNoCommentsFixture(string $revisionMode): array
    {
        $loaded = $this->readDocxFixture('comments', [
            'revisionMode' => $revisionMode,
            'commentsMode' => 'omit',
        ]);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        $nativePath = $this->absoluteDocxDirectory() . DIRECTORY_SEPARATOR . 'comments_no_comments.native';
        if (!is_file($nativePath)) {
            return [
                'status' => 'skipped-missing-fixture',
                'fixture' => 'comments_no_comments.native',
                'reason' => 'Required upstream native expectation is missing: comments_no_comments.native',
            ];
        }

        $native = file_get_contents($nativePath);
        if (!is_string($native)) {
            throw new \RuntimeException("Unable to read native fixture {$nativePath}");
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $expected = (new NativeReader())->read($native);
        $expectedTexts = self::blockTexts($expected);
        $actualTexts = self::blockTexts($document);
        $classes = self::classNames($document);
        $noteCount = count(array_filter(
            self::flattenNodes($document),
            static fn (AstNode $node): bool => $node->type === 'note'
        ));

        $this->assertSame($expectedTexts, $actualTexts, "comments.docx {$revisionMode} no-comments text mismatch");
        $this->assertTrue(!in_array('comment-start', $classes, true), "comments.docx {$revisionMode} no-comments should omit comment-start spans");
        $this->assertTrue(!in_array('comment-end', $classes, true), "comments.docx {$revisionMode} no-comments should omit comment-end spans");
        $this->assertSame(0, $noteCount, "comments.docx {$revisionMode} no-comments should omit comment notes");

        return [
            'status' => 'passed',
            'fixture' => 'comments.docx',
            'nativeFixture' => 'comments_no_comments.native',
            'details' => [
                'revisionMode' => $revisionMode,
                'commentsMode' => 'omit',
                'blockTexts' => $actualTexts,
                'classes' => $classes,
                'noteCount' => $noteCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCommentsWarningNoWarningsFixture(string $revisionMode): array
    {
        $loaded = $this->readDocxFixture('comments_warning', ['revisionMode' => $revisionMode]);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $meta = $document->attr('meta', []);
        $warnings = $meta['docxWarnings'] ?? null;
        $this->assertSame([], is_array($warnings) ? $warnings : null, "comments_warning {$revisionMode} mode should expose no DOCX reader warnings");
        $this->assertSame(0, (int) ($meta['docxWarningCount'] ?? -1), "comments_warning {$revisionMode} mode should count zero DOCX reader warnings");
        $this->assertTrue((int) ($meta['docxComments'] ?? 0) > 0, 'comments_warning.docx should load comments.xml records');

        return [
            'status' => 'passed',
            'fixture' => 'comments_warning.docx',
            'details' => [
                'revisionMode' => $revisionMode,
                'docxWarningCount' => (int) ($meta['docxWarningCount'] ?? 0),
                'docxWarnings' => is_array($warnings) ? $warnings : [],
                'docxComments' => (int) ($meta['docxComments'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCommentsWarningAllFixture(): array
    {
        $loaded = $this->readDocxFixture('comments_warning', ['revisionMode' => 'preserve']);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $meta = $document->attr('meta', []);
        $warnings = is_array($meta['docxWarnings'] ?? null) ? $meta['docxWarnings'] : [];
        $this->assertSame(['Docx comment 1 will not retain formatting'], $warnings, 'comments_warning.docx all-comments warning mismatch');
        $this->assertSame(1, (int) ($meta['docxWarningCount'] ?? 0), 'comments_warning.docx should expose one DOCX warning');

        return [
            'status' => 'passed',
            'fixture' => 'comments_warning.docx',
            'details' => [
                'revisionMode' => 'preserve',
                'docxWarnings' => $warnings,
                'docxWarningCount' => (int) ($meta['docxWarningCount'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCommentsWithStylesWarningsFixture(): array
    {
        $loaded = $this->readDocxFixture('comments', [
            'collectWarnings' => true,
            'stylesExtension' => true,
        ]);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $meta = $document->attr('meta', []);
        $warnings = is_array($meta['docxWarnings'] ?? null) ? $meta['docxWarnings'] : [];
        $this->assertSame([], $warnings, 'comments.docx +styles warning collector should be empty');
        $this->assertSame(0, (int) ($meta['docxWarningCount'] ?? -1), 'comments.docx +styles should not emit DOCX parser warnings');

        return [
            'status' => 'passed',
            'fixture' => 'comments.docx',
            'details' => [
                'stylesExtension' => true,
                'docxWarnings' => $warnings,
                'docxWarningCount' => (int) ($meta['docxWarningCount'] ?? 0),
                'docxComments' => (int) ($meta['docxComments'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCustomStyleDefaultFixture(): array
    {
        $loaded = $this->readDocxFixture('custom-style-reference');
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $this->assertTrue(!self::hasNestedAttributeKey($document, 'custom-style'), 'default custom-style-reference check should not emit custom-style attributes');

        return [
            'status' => 'passed',
            'fixture' => 'custom-style-reference.docx',
            'details' => [
                'blockTexts' => self::blockTexts($document),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCompactStyleFixture(): array
    {
        $loaded = $this->readDocxFixture('compact-style-removal');
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $this->assertTrue(($document->children[0] ?? null) instanceof AstNode, 'compact-style-removal should have a body block');
        $this->assertSame('ordered_list', $document->children[0]->type, 'compact-style-removal should parse as an ordered list');
        $this->assertTrue(!in_array('Compact', self::classNames($document), true), 'Compact style should not leak as a class');

        return [
            'status' => 'passed',
            'fixture' => 'compact-style-removal.docx',
            'details' => [
                'topType' => $document->children[0]->type,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMetadataFixture(string $stem, bool $afterNormal): array
    {
        $loaded = $this->readDocxFixture($stem);
        if (is_array($loaded['skipped'] ?? null)) {
            return $loaded['skipped'];
        }

        /** @var AstNode $document */
        $document = $loaded['document'];
        $meta = $document->attr('meta', []);
        $this->assertSame('This Is the Title', (string) ($meta['title'] ?? ''), "{$stem} should collect leading title metadata");
        $this->assertSame(['Mary Ann Evans', 'Aurore Dupin'], $meta['author'] ?? [], "{$stem} should collect leading author metadata");
        $this->assertSame('And now this is normal text.', (string) ($document->children[0]->attr('text', '') ?? ''), "{$stem} should keep normal body text");
        if ($afterNormal) {
            $this->assertSame(6, count($document->children), 'metadata_after_normal should keep metadata-styled paragraphs after normal text visible');
        }

        return [
            'status' => 'passed',
            'fixture' => "{$stem}.docx",
            'details' => [
                'title' => $meta['title'] ?? null,
                'authorCount' => is_array($meta['author'] ?? null) ? count($meta['author']) : 0,
                'bodyBlockCount' => count($document->children),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{document?:AstNode,package?:ZipPackage,reader?:DocxReader,skipped?:array<string,mixed>}
     */
    private function readDocxFixture(string $stem, array $options = []): array
    {
        $path = $this->absoluteDocxDirectory() . DIRECTORY_SEPARATOR . $stem . '.docx';
        if (!is_file($path)) {
            return [
                'skipped' => [
                    'status' => 'skipped-missing-fixture',
                    'fixture' => "{$stem}.docx",
                    'reason' => "Required upstream DOCX fixture is missing: {$stem}.docx",
                ],
            ];
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read DOCX fixture {$path}");
        }

        $package = ZipPackage::fromString($bytes);
        $reader = new DocxReader($options);

        return [
            'document' => $reader->readDocument($package),
            'package' => $package,
            'reader' => $reader,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function coveredCaseDefinitions(): array
    {
        $definitions = [];
        $add = static function (
            string $call,
            string $label,
            string $docx,
            ?string $native,
            string $coverageKind,
            array $focusedEvidence,
            array $localEvidence,
            array $limitations,
            ?string $targetedCheckId = null
        ) use (&$definitions): void {
            $definitions[self::caseKeyFromParts($call, $label, $docx, $native)] = [
                'coverageKind' => $coverageKind,
                'focusedEvidence' => $focusedEvidence,
                'localEvidence' => $localEvidence,
                'limitations' => $limitations,
                'targetedCheckId' => $targetedCheckId,
            ];
        };

        $mediaEvidence = ['DocxReader image/media metadata plus MediaBag extraction from package media parts'];
        $mediaLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/src/MediaBag.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $mediaLimits = ['does not compare Haskell MediaBag byte map equality', 'does not execute upstream testMediaBag'];
        $add('testCompare', 'inline image', 'docx/image.docx', 'docx/image_no_embed.native', 'focused-media-bag-native-php-check', $mediaEvidence, $mediaLocal, $mediaLimits, 'media:image');
        $add('testMediaBag', 'image extraction', 'docx/image.docx', null, 'focused-media-bag-native-php-check', $mediaEvidence, $mediaLocal, $mediaLimits, 'media:image');
        $add('testMediaBag', 'image inside textbox content populates media bag', 'docx/textbox_image.docx', null, 'focused-media-bag-native-php-check', $mediaEvidence, $mediaLocal, $mediaLimits, 'media:textbox_image');
        $add('testMediaBag', 'image inside textbox content with duplicate encoding populates media bag', 'docx/textbox_image_duplicate_encoding.docx', null, 'focused-media-bag-native-php-check', $mediaEvidence, $mediaLocal, $mediaLimits, 'media:textbox_image_duplicate_encoding');
        $add('testMediaBag', 'image with textbox caption in same paragraph populates media bag', 'docx/image_with_textbox_caption.docx', null, 'focused-media-bag-native-php-check', $mediaEvidence, $mediaLocal, $mediaLimits, 'media:image_with_textbox_caption');

        $citationLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $citationLimits = ['does not run citeproc', 'does not compare Haskell citation extension AST equality'];
        $add('testCompare', 'zotero with -citations', 'docx/zotero_citations.docx', 'docx/zotero_citations_minus.native', 'focused-citation-addin-native-php-check', ['visible citation text and Zotero add-in provenance are parsed from DOCX fields'], $citationLocal, $citationLimits, 'citation:zotero');
        $add('testCompareWithOpts', 'zotero with +citations', 'docx/zotero_citations.docx', 'docx/zotero_citations_plus.native', 'focused-citation-addin-native-php-check', ['Zotero CSL citation add-in spans preserve payload metadata'], $citationLocal, $citationLimits, 'citation:zotero');
        $add('testCompare', 'mendeley with -citations', 'docx/mendeley_citations.docx', 'docx/mendeley_citations_minus.native', 'focused-citation-addin-native-php-check', ['visible citation text and Mendeley add-in provenance are parsed from generic CSL_CITATION fields'], $citationLocal, $citationLimits, 'citation:mendeley');
        $add('testCompareWithOpts', 'mendeley with +citations', 'docx/mendeley_citations.docx', 'docx/mendeley_citations_plus.native', 'focused-citation-addin-native-php-check', ['Mendeley CSL citation add-in spans preserve payload metadata'], $citationLocal, $citationLimits, 'citation:mendeley');

        $revisionLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/tests/DocxReaderTest.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $revisionLimits = ['local explicit revisionMode evidence is not an upstream Haskell ReaderOptions run'];
        $add('testCompare', 'insertion (default)', 'docx/track_changes_insertion.docx', 'docx/track_changes_insertion_accept.native', 'focused-revision-mode-native-php-check', ['explicit local revisionMode=accept produces the upstream accept text for insertion changes'], $revisionLocal, ['does not prove local default revisionMode equals Haskell default'], 'revision:track_changes_insertion:accept');
        $add('testCompareWithOpts', 'insert insertion (accept)', 'docx/track_changes_insertion.docx', 'docx/track_changes_insertion_accept.native', 'focused-revision-mode-native-php-check', ['local revisionMode=accept keeps inserted text without residual change spans'], $revisionLocal, $revisionLimits, 'revision:track_changes_insertion:accept');
        $add('testCompareWithOpts', 'remove insertion (reject)', 'docx/track_changes_insertion.docx', 'docx/track_changes_insertion_reject.native', 'focused-revision-mode-native-php-check', ['local revisionMode=reject removes inserted text without residual change spans'], $revisionLocal, $revisionLimits, 'revision:track_changes_insertion:reject');
        $add('testCompare', 'deletion (default)', 'docx/track_changes_deletion.docx', 'docx/track_changes_deletion_accept.native', 'focused-revision-mode-native-php-check', ['explicit local revisionMode=accept produces the upstream accept text for deletion changes'], $revisionLocal, ['does not prove local default revisionMode equals Haskell default'], 'revision:track_changes_deletion:accept');
        $add('testCompareWithOpts', 'remove deletion (accept)', 'docx/track_changes_deletion.docx', 'docx/track_changes_deletion_accept.native', 'focused-revision-mode-native-php-check', ['local revisionMode=accept removes deleted text without residual change spans'], $revisionLocal, $revisionLimits, 'revision:track_changes_deletion:accept');
        $add('testCompareWithOpts', 'insert deletion (reject)', 'docx/track_changes_deletion.docx', 'docx/track_changes_deletion_reject.native', 'focused-revision-mode-native-php-check', ['local revisionMode=reject keeps deleted text without residual change spans'], $revisionLocal, $revisionLimits, 'revision:track_changes_deletion:reject');
        $add('testCompareWithOpts', 'keep insertion (all)', 'docx/track_changes_deletion.docx', 'docx/track_changes_deletion_all.native', 'focused-revision-mode-native-php-check', ['local revisionMode=preserve keeps tracked deletion provenance for all-changes output'], $revisionLocal, $revisionLimits, 'revision:track_changes_deletion:preserve');
        $add('testCompareWithOpts', 'keep deletion (all)', 'docx/track_changes_deletion.docx', 'docx/track_changes_deletion_all.native', 'focused-revision-mode-native-php-check', ['local revisionMode=preserve keeps tracked deletion provenance for all-changes output'], $revisionLocal, $revisionLimits, 'revision:track_changes_deletion:preserve');
        $add('testCompareWithOpts', 'move text (accept)', 'docx/track_changes_move.docx', 'docx/track_changes_move_accept.native', 'focused-revision-mode-native-php-check', ['local revisionMode=accept keeps moved-to text at the accepted position'], $revisionLocal, $revisionLimits, 'revision:track_changes_move:accept');
        $add('testCompareWithOpts', 'move text (reject)', 'docx/track_changes_move.docx', 'docx/track_changes_move_reject.native', 'focused-revision-mode-native-php-check', ['local revisionMode=reject keeps moved-from text at the rejected position'], $revisionLocal, $revisionLimits, 'revision:track_changes_move:reject');
        $add('testCompareWithOpts', 'move text (all)', 'docx/track_changes_move.docx', 'docx/track_changes_move_all.native', 'focused-revision-mode-native-php-check', ['local revisionMode=preserve keeps paired move-from and move-to provenance'], $revisionLocal, $revisionLimits, 'revision:track_changes_move:preserve');

        $add('testCompareWithOpts', 'paragraph insertion/deletion (accept)', 'docx/paragraph_insertion_deletion.docx', 'docx/paragraph_insertion_deletion_accept.native', 'focused-revision-mode-native-ast-php-check', ['local DOCX revisionMode=accept matches the upstream native paragraph-break expectation'], $revisionLocal, $revisionLimits, 'revision:paragraph_insertion_deletion:accept');
        $add('testCompareWithOpts', 'paragraph insertion/deletion (reject)', 'docx/paragraph_insertion_deletion.docx', 'docx/paragraph_insertion_deletion_reject.native', 'focused-revision-mode-native-ast-php-check', ['local DOCX revisionMode=reject matches the upstream native paragraph-break expectation'], $revisionLocal, $revisionLimits, 'revision:paragraph_insertion_deletion:reject');
        $add('testCompareWithOpts', 'paragraph insertion/deletion (all)', 'docx/paragraph_insertion_deletion.docx', 'docx/paragraph_insertion_deletion_all.native', 'focused-revision-mode-native-ast-php-check', ['local DOCX revisionMode=preserve matches the upstream native paragraph-break expectation'], $revisionLocal, $revisionLimits, 'revision:paragraph_insertion_deletion:preserve');
        $add('testCompareWithOpts', 'paragraph insertion/deletion (all)', 'docx/track_changes_scrubbed_metadata.docx', 'docx/track_changes_scrubbed_metadata.native', 'focused-revision-mode-native-php-check', ['local DOCX reader preserves scrubbed review authors without inventing dates'], $revisionLocal, $revisionLimits, 'revision:track_changes_scrubbed_metadata:preserve');

        $commentsLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/tests/DocxReaderTest.php',
            'lanes/pandoc/tests/MarkdownReaderTest.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $add('testCompareWithOpts', 'comments (all comments)', 'docx/comments.docx', 'docx/comments.native', 'focused-comments-native-php-check', ['local DOCX reader loads comments.xml records and exposes comment-start/comment-end spans'], $commentsLocal, ['does not cover accept/reject no-comment ReaderOptions'], 'comments:all');
        $add('testCompareWithOpts', 'comments (accept -- no comments)', 'docx/comments.docx', 'docx/comments_no_comments.native', 'focused-comments-native-php-check', ['local DOCX reader revisionMode=accept plus commentsMode=omit matches upstream no-comments native block text'], $commentsLocal, ['local explicit commentsMode evidence is not an upstream Haskell ReaderOptions run'], 'comments:accept-no-comments');
        $add('testCompareWithOpts', 'comments (reject -- comments)', 'docx/comments.docx', 'docx/comments_no_comments.native', 'focused-comments-native-php-check', ['local DOCX reader revisionMode=reject plus commentsMode=omit matches upstream no-comments native block text'], $commentsLocal, ['local explicit commentsMode evidence is not an upstream Haskell ReaderOptions run'], 'comments:reject-no-comments');

        $warningLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/tests/DocxReaderTest.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $warningLimits = ['focused warning metadata check; does not execute upstream testForWarningsWithOpts'];
        $add('testForWarningsWithOpts', 'comment warnings (accept -- no warnings)', 'docx/comments_warning.docx', null, 'focused-docx-warning-native-php-check', ['local DOCX reader revisionMode=accept exposes an empty bounded docxWarnings metadata list for comments_warning.docx'], $warningLocal, $warningLimits, 'warning:comments_warning:accept');
        $add('testForWarningsWithOpts', 'comment warnings (reject -- no warnings)', 'docx/comments_warning.docx', null, 'focused-docx-warning-native-php-check', ['local DOCX reader revisionMode=reject exposes an empty bounded docxWarnings metadata list for comments_warning.docx'], $warningLocal, $warningLimits, 'warning:comments_warning:reject');
        $add('testForWarningsWithOpts', 'comment warnings (all)', 'docx/comments_warning.docx', null, 'focused-docx-warning-native-php-check', ['local DOCX reader exposes the upstream all-comments DocxParserWarning text for non-inline comment body formatting loss'], $warningLocal, $warningLimits, 'warning:comments_warning:all');
        $add('testForWarningsWithOpts', 'comments (with styles extension)', 'docx/comments.docx', null, 'focused-docx-warning-native-php-check', ['local DOCX reader verifies comments.docx +styles has no comment-formatting-loss warnings'], $warningLocal, $warningLimits, 'warning:comments-with-styles');

        $styleLocal = [
            'lanes/pandoc/src/DocxReader.php',
            'lanes/pandoc/tests/DocxReaderTest.php',
            'lanes/pandoc/tests/MarkdownReaderTest.php',
            'lanes/pandoc/tests/DocxUpstreamFocusedReaderEvidenceTest.php',
        ];
        $add('testCompare', 'custom styles (`+styles`) not enabled (default)', 'docx/custom-style-reference.docx', 'docx/custom-style-no-styles.native', 'focused-style-default-native-php-check', ['local DOCX reader default output does not leak custom-style attributes'], $styleLocal, ['does not prove Haskell default AST equality'], 'custom-style-reference:default');
        $add('testCompareWithOpts', 'custom styles (`+styles`) enabled', 'docx/custom-style-reference.docx', 'docx/custom-style-with-styles.native', 'focused-style-native-ast-php-check', ['local DOCX stylesExtension output matches the upstream native custom-style expectation'], $styleLocal, ['local stylesExtension evidence is not an upstream Haskell ReaderOptions run'], 'custom-style-reference:styles');
        $add('testCompareWithOpts', 'custom styles (`+styles`): Compact style is removed from output', 'docx/compact-style-removal.docx', 'docx/compact-style-removal.native', 'focused-style-default-native-php-check', ['local DOCX reader parses Compact-style list output without leaking Compact as a class'], $styleLocal, ['does not prove Haskell +styles AST equality'], 'compact-style-removal:styles');
        $add('testCompareWithOpts', 'metadata fields', 'docx/metadata.docx', 'docx/metadata.native', 'focused-metadata-native-php-check', ['local DOCX reader collects leading Title/Author/Date/Abstract style paragraphs as metadata'], $styleLocal, ['does not prove Haskell +styles AST equality'], 'metadata:styles');
        $add('testCompareWithOpts', 'stop recording metadata with normal text', 'docx/metadata_after_normal.docx', 'docx/metadata_after_normal.native', 'focused-metadata-native-php-check', ['local DOCX reader stops metadata collection after normal text and keeps later metadata-styled paragraphs visible'], $styleLocal, ['does not prove Haskell +styles AST equality'], 'metadata_after_normal:styles');

        return $definitions;
    }

    /**
     * @return array<string, array{openReason:string,nextEvidence:string}>
     */
    private static function openCaseDefinitions(): array
    {
        return [];
    }

    /**
     * @param array<string, true> $denominatorKeys
     * @param array<string, array<string, mixed>> $coveredDefinitions
     * @param array<string, array<string, mixed>> $openDefinitions
     * @return list<string>
     */
    private static function mappingValidationIssues(array $denominatorKeys, array $coveredDefinitions, array $openDefinitions): array
    {
        $issues = [];
        foreach (array_keys($coveredDefinitions + $openDefinitions) as $key) {
            if (!isset($denominatorKeys[$key])) {
                $issues[] = "mapped case is not present in denominator: {$key}";
            }
        }
        foreach (array_keys($denominatorKeys) as $key) {
            if (!isset($coveredDefinitions[$key]) && !isset($openDefinitions[$key])) {
                $issues[] = "denominator case has no focused evidence status: {$key}";
            }
        }

        return $issues;
    }

    /**
     * @return array{doesAssert:list<string>,doesNotAssert:list<string>}
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the denominator is the 36-case haskellReaderInventory.notCoveredCases list from UPSTREAM_DOCX_HASKELL_INVENTORY.json',
                'which denominator cases have additional focused native PHP checks or mapped upstream native evidence',
                'optional targeted DOCX fixture checks when a hydrated upstream test/docx cache is supplied',
                'the listed focused DOCX warning metadata checks only, without executing upstream testForWarningsWithOpts',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local DOCX reader output equals upstream native expectations for mapped-native-only cases',
                'that upstream testForWarningsWithOpts itself was executed locally',
                'that full DOCX/OpenXML parity is achieved',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function caseKey(array $row): string
    {
        return self::caseKeyFromParts(
            (string) ($row['call'] ?? ''),
            (string) ($row['label'] ?? ''),
            (string) ($row['docx'] ?? ''),
            is_string($row['native'] ?? null) ? $row['native'] : null
        );
    }

    private static function caseKeyFromParts(string $call, string $label, string $docx, ?string $native): string
    {
        return $call . '|' . $label . '|' . $docx . '|' . ($native ?? '');
    }

    /**
     * @return list<AstNode>
     */
    private static function flattenNodes(AstNode $node): array
    {
        $nodes = [$node];
        foreach ($node->children as $child) {
            array_push($nodes, ...self::flattenNodes($child));
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private static function blockTexts(AstNode $document): array
    {
        return array_values(array_map(
            static fn (AstNode $node): string => (string) $node->attr('text', ''),
            $document->children
        ));
    }

    /**
     * @return list<string>
     */
    private static function classNames(AstNode $document): array
    {
        $classes = [];
        foreach (self::flattenNodes($document) as $node) {
            $nodeClasses = $node->attr('classes', []);
            if (!is_array($nodeClasses) || $nodeClasses === []) {
                continue;
            }
            $classes[] = implode('.', array_map('strval', $nodeClasses));
        }

        return array_values(array_unique($classes));
    }

    private static function hasNestedAttributeKey(AstNode $node, string $key): bool
    {
        if (array_key_exists($key, $node->attrs)) {
            return true;
        }
        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && array_key_exists($key, $attributes)) {
            return true;
        }
        foreach ($node->children as $child) {
            if (self::hasNestedAttributeKey($child, $key)) {
                return true;
            }
        }

        return false;
    }

    private function absoluteDocxDirectory(): string
    {
        if (str_starts_with($this->docxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->docxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->docxDirectory);
    }

    private function displayPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->repoRoot);
        $cacheSuffix = '/' . self::DEFAULT_RELATIVE_DOCX_DIR;
        if (str_ends_with($normalized, $cacheSuffix)) {
            return self::DEFAULT_RELATIVE_DOCX_DIR;
        }
        if ($normalized === $root) {
            return '.';
        }
        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $normalized;
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . '; expected=' . json_encode($expected) . '; actual=' . json_encode($actual));
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}
