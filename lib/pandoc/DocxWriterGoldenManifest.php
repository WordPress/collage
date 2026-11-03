<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxWriterGoldenManifest
{
    private const NS_WORDPROCESSINGML = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_OFFICE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_WORDPROCESSING_DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const NS_DRAWINGML = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_DRAWINGML_PICTURE = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const NS_VML = 'urn:schemas-microsoft-com:vml';
    private const NS_WORDPROCESSING_SHAPE = 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape';

    /**
     * @var list<string>
     */
    private const XML_FEATURE_NAMES = [
        'wordParagraph',
        'wordRun',
        'wordTable',
        'wordTableRow',
        'wordTableCell',
        'wordTableGrid',
        'wordGridSpan',
        'wordVerticalMerge',
        'wordTableCaption',
        'wordDrawing',
        'wordPict',
        'wordSdt',
        'wordSdtPr',
        'wordSdtContent',
        'wordTextBoxContent',
        'wordParagraphCaptionStyle',
        'wordParagraphTableCaptionStyle',
        'wordStyleCaption',
        'wordStyleTableCaption',
        'drawingInline',
        'drawingAnchor',
        'drawingGraphic',
        'drawingBlip',
        'drawingPicture',
        'drawingRelationshipEmbed',
        'drawingRelationshipLink',
        'vmlShape',
        'vmlTextBox',
        'vmlImageData',
        'wordprocessingShape',
        'wordprocessingShapeTextBox',
    ];

    public const DEFAULT_RELATIVE_DOCX_DIR = DocxParityCorpusAudit::DEFAULT_RELATIVE_DOCX_DIR;
    public const PINNED_UPSTREAM_GOLDEN_PACKAGE_COUNT = 38;
    public const STATUS_REPORTED = 'reported_writer_golden_package_inventory';
    public const STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY = 'skipped_missing_writer_golden_directory';
    public const STATUS_SKIPPED_UNREADABLE_GOLDEN_DIRECTORY = 'skipped_unreadable_writer_golden_directory';
    public const EVIDENCE_KIND = 'writer-golden-package-inventory-only';
    public const EVIDENCE_KIND_GENERATED_COMPARISON = 'writer-golden-package-generated-stable-comparison';
    public const EXPECTED_WRITER_CLASS = 'PortLibs\\Pandoc\\DocxWriter';
    public const EXPECTED_WRITER_RELATIVE_PATH = 'lanes/pandoc/src/DocxWriter.php';
    public const OPEN_REASON = 'writer-unsupported-no-DocxWriter-implementation-and-docx-output-registry-unsupported';
    public const COMPARISON_NOT_RECORDED_REASON = 'docx-writer-comparison-still-not-recorded';
    public const GENERATION_DIRECTORY_NOT_CONFIGURED_REASON = 'docx-writer-generation-directory-not-configured';
    public const GENERATION_SOURCE_DIRECTORY_MISSING_REASON = 'upstream-docx-source-directory-missing';
    public const GENERATION_SOURCE_DIRECTORY_UNREADABLE_REASON = 'upstream-docx-source-directory-unreadable';
    public const GENERATION_OUTPUT_DIRECTORY_UNWRITABLE_REASON = 'docx-writer-generation-output-directory-unwritable';
    public const GENERATION_WRITER_UNAVAILABLE_REASON = 'docx-writer-implementation-unavailable';
    public const GENERATION_WRITE_FAILED_REASON = 'docx-writer-generation-failed';
    public const GENERATED_DIRECTORY_NOT_CONFIGURED_REASON = 'generated-docx-directory-not-configured';
    public const GENERATED_DIRECTORY_MISSING_REASON = 'generated-docx-directory-missing';
    public const GENERATED_DIRECTORY_UNREADABLE_REASON = 'generated-docx-directory-unreadable';
    public const GOLDEN_DIRECTORY_MISSING_REASON = 'upstream-writer-golden-directory-missing';
    public const GOLDEN_DIRECTORY_UNREADABLE_REASON = 'upstream-writer-golden-directory-unreadable';
    public const GENERATED_COMPARISON_MISMATCH_REASON = 'generated-docx-package-stable-semantic-mismatches-or-coverage-gaps';
    public const GENERATED_COMPARISON_MATCH_REASON = 'all-generated-docx-packages-match-upstream-golden-stable-semantics';
    public const CLAIM = 'Inventories upstream golden DOCX packages and local writer support status; when generated DOCX packages are supplied, compares them to upstream writer golden packages by stable package semantics. No writer parity is asserted without generated comparisons.';

    /**
     * @var list<array{goldenFile:string, nativeFile:string, referenceDoc?:string}>
     */
    private const WRITER_GOLDEN_CASES = [
        ['goldenFile' => 'block_quotes.docx', 'nativeFile' => 'block_quotes.native'],
        ['goldenFile' => 'codeblock.docx', 'nativeFile' => 'codeblock.native'],
        ['goldenFile' => 'comments.docx', 'nativeFile' => 'comments.native'],
        ['goldenFile' => 'custom_style_no_reference.docx', 'nativeFile' => 'custom_style.native'],
        ['goldenFile' => 'custom_style_preserve.docx', 'nativeFile' => 'custom-style-preserve.native'],
        ['goldenFile' => 'custom_style_reference.docx', 'nativeFile' => 'custom_style.native', 'referenceDoc' => 'custom-style-reference.docx'],
        ['goldenFile' => 'definition_list.docx', 'nativeFile' => 'definition_list.native'],
        ['goldenFile' => 'document-properties.docx', 'nativeFile' => 'document-properties.native'],
        ['goldenFile' => 'document-properties-short-desc.docx', 'nativeFile' => 'document-properties-short-desc.native'],
        ['goldenFile' => 'headers.docx', 'nativeFile' => 'headers.native'],
        ['goldenFile' => 'image.docx', 'nativeFile' => 'image_writer_test.native'],
        ['goldenFile' => 'inline_code.docx', 'nativeFile' => 'inline_code.native'],
        ['goldenFile' => 'inline_formatting.docx', 'nativeFile' => 'inline_formatting.native'],
        ['goldenFile' => 'inline_images.docx', 'nativeFile' => 'inline_images_writer_test.native'],
        ['goldenFile' => 'link_in_notes.docx', 'nativeFile' => 'link_in_notes.native'],
        ['goldenFile' => 'links.docx', 'nativeFile' => 'links.native'],
        ['goldenFile' => 'lists.docx', 'nativeFile' => 'lists.native'],
        ['goldenFile' => 'lists_9994.docx', 'nativeFile' => 'lists_9994.native'],
        ['goldenFile' => 'lists_continuing.docx', 'nativeFile' => 'lists_continuing.native'],
        ['goldenFile' => 'lists_div_bullets.docx', 'nativeFile' => 'lists_div_bullets.native'],
        ['goldenFile' => 'lists_multiple_initial.docx', 'nativeFile' => 'lists_multiple_initial.native'],
        ['goldenFile' => 'lists_restarting.docx', 'nativeFile' => 'lists_restarting.native'],
        ['goldenFile' => 'nested_anchors_in_header.docx', 'nativeFile' => 'nested_anchors_in_header.native'],
        ['goldenFile' => 'notes.docx', 'nativeFile' => 'notes.native'],
        ['goldenFile' => 'raw-blocks.docx', 'nativeFile' => 'raw-blocks.native'],
        ['goldenFile' => 'raw-bookmarks.docx', 'nativeFile' => 'raw-bookmarks.native'],
        ['goldenFile' => 'table_one_row.docx', 'nativeFile' => 'table_one_row.native'],
        ['goldenFile' => 'table_with_list_cell.docx', 'nativeFile' => 'table_with_list_cell.native'],
        ['goldenFile' => 'tables.docx', 'nativeFile' => 'tables.native'],
        ['goldenFile' => 'tables-default-widths.docx', 'nativeFile' => 'tables-default-widths.native'],
        ['goldenFile' => 'tables_separated_with_rawblock.docx', 'nativeFile' => 'tables_separated_with_rawblock.native'],
        ['goldenFile' => 'task_list.docx', 'nativeFile' => 'task_list.native'],
        ['goldenFile' => 'track_changes_deletion.docx', 'nativeFile' => 'track_changes_deletion_all.native'],
        ['goldenFile' => 'track_changes_insertion.docx', 'nativeFile' => 'track_changes_insertion_all.native'],
        ['goldenFile' => 'track_changes_move.docx', 'nativeFile' => 'track_changes_move_all.native'],
        ['goldenFile' => 'track_changes_scrubbed_metadata.docx', 'nativeFile' => 'track_changes_scrubbed_metadata.native'],
        ['goldenFile' => 'unicode.docx', 'nativeFile' => 'unicode.native'],
        ['goldenFile' => 'verbatim_subsuper.docx', 'nativeFile' => 'verbatim_subsuper.native'],
    ];

    private readonly string $repoRoot;
    private readonly string $docxDirectory;
    private readonly ?string $generatedDocxDirectory;
    private readonly ?string $generationOutputDirectory;
    /** @var list<string> */
    private readonly array $caseFilters;

    public function __construct(
        string $repoRoot,
        string $docxDirectory = self::DEFAULT_RELATIVE_DOCX_DIR,
        private readonly int $sampleLimit = 8,
        ?string $generatedDocxDirectory = null,
        ?string $generationOutputDirectory = null,
        array $caseFilters = []
    ) {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($docxDirectory === '') {
            throw new \InvalidArgumentException('DOCX directory must not be empty');
        }
        if ($generatedDocxDirectory === '') {
            throw new \InvalidArgumentException('Generated DOCX directory must not be empty when provided');
        }
        if ($generationOutputDirectory === '') {
            throw new \InvalidArgumentException('Generated DOCX output directory must not be empty when provided');
        }
        if ($sampleLimit < 0) {
            throw new \InvalidArgumentException('Sample limit must not be negative');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->docxDirectory = $docxDirectory;
        $this->generationOutputDirectory = $generationOutputDirectory;
        $this->generatedDocxDirectory = $generatedDocxDirectory ?? $generationOutputDirectory;
        $this->caseFilters = self::normalizeCaseFilters($caseFilters);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $goldenDir = $this->absoluteGoldenDirectory();
        $writer = $this->localWriterEvidence();
        $generation = $this->generationEvidence($writer);

        if (!is_dir($goldenDir)) {
            return $this->skipReport(
                self::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY,
                "Upstream DOCX writer golden directory does not exist: {$goldenDir}",
                $writer,
                $generation
            );
        }
        if (!is_readable($goldenDir)) {
            return $this->skipReport(
                self::STATUS_SKIPPED_UNREADABLE_GOLDEN_DIRECTORY,
                "Upstream DOCX writer golden directory is not readable: {$goldenDir}",
                $writer,
                $generation
            );
        }

        $goldenPackageFileNames = $this->packageFileNames($goldenDir);
        $caseFilter = $this->caseFilterReport($goldenPackageFileNames);
        $packageRows = $this->packageRows($goldenDir);
        $packageCount = count($packageRows);
        $readablePackages = count(array_filter(
            $packageRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'readable'
        ));
        $partCount = array_sum(array_map(
            static fn (array $row): int => (int) ($row['partCount'] ?? 0),
            $packageRows
        ));
        $readablePartCount = array_sum(array_map(
            static fn (array $row): int => (int) ($row['readablePartCount'] ?? 0),
            $packageRows
        ));

        $packageComparison = $this->packageComparisonEvidence($writer, $packageRows);
        $goldenPackageCommonShape = $this->goldenPackageCommonShape($packageRows);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-writer-golden-audit',
            'status' => self::STATUS_REPORTED,
            'skipped' => false,
            'claim' => self::CLAIM,
            'evidenceKind' => (($packageComparison['run'] ?? false) === true)
                ? self::EVIDENCE_KIND_GENERATED_COMPARISON
                : self::EVIDENCE_KIND,
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'goldenDirectory' => $goldenDir,
            'goldenDirectoryDisplay' => $this->displayPath($goldenDir),
            'goldenDirectoryPresent' => true,
            'expectedUpstreamWriterSourceReferences' => self::expectedUpstreamWriterSourceReferences(),
            'caseFilter' => $caseFilter,
            'localWriter' => $writer,
            'generation' => $generation,
            'packageComparison' => $packageComparison,
            'unfilteredGoldenPackageCount' => count($goldenPackageFileNames),
            'goldenPackageCount' => $packageCount,
            'readableGoldenPackageCount' => $readablePackages,
            'unreadableGoldenPackageCount' => $packageCount - $readablePackages,
            'packagePartCount' => $partCount,
            'readablePackagePartCount' => $readablePartCount,
            'goldenPackageCommonShape' => $goldenPackageCommonShape,
            'packageRows' => $packageRows,
            'packageSamples' => array_slice($packageRows, 0, $this->sampleLimit),
            'notes' => [
                'Each part hash is the SHA-256 of the uncompressed package part bytes; raw package bytes are not emitted.',
                'goldenPackageCommonShape aggregates common and optional package parts, content-type records, and relationship records across readable upstream writer golden DOCX packages.',
                'These upstream golden DOCX files are expected writer outputs; generated outputs are recorded separately when --generate-supported-dir is supplied.',
                'Writer parity remains open until generated local DOCX packages match upstream package parts, relationships, content types, and document XML semantics.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX writer golden package manifest',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'Golden directory: ' . (string) ($report['goldenDirectoryDisplay'] ?? $report['goldenDirectory'] ?? ''),
        ];

        $caseFilter = $report['caseFilter'] ?? [];
        if (is_array($caseFilter) && ($caseFilter['active'] ?? false) === true) {
            $lines[] = 'Case filter: active; values='
                . implode(',', array_map('strval', is_array($caseFilter['values'] ?? null) ? $caseFilter['values'] : []))
                . '; selected pinned cases='
                . (int) ($caseFilter['selectedPinnedGoldenCaseCount'] ?? 0)
                . '/'
                . (int) ($caseFilter['totalPinnedGoldenCaseCount'] ?? self::PINNED_UPSTREAM_GOLDEN_PACKAGE_COUNT)
                . '; matching golden packages='
                . (int) ($caseFilter['matchingGoldenPackageCount'] ?? 0)
                . '/'
                . (int) ($caseFilter['unfilteredGoldenPackageCount'] ?? 0);
        }

        $writer = $report['localWriter'] ?? [];
        if (is_array($writer)) {
            $lines[] = 'DOCX writer implementation: '
                . (string) ($writer['status'] ?? 'unknown')
                . '; classExists='
                . self::boolText($writer['classExists'] ?? false)
                . '; fileExists='
                . self::boolText($writer['fileExists'] ?? false)
                . '; registryStatus='
                . (string) ($writer['registryStatus'] ?? 'unknown');
        }

        $generation = $report['generation'] ?? [];
        if (is_array($generation)) {
            $expected = (int) ($generation['expectedGoldenCaseCount'] ?? self::PINNED_UPSTREAM_GOLDEN_PACKAGE_COUNT);
            $lines[] = 'Generated package production: '
                . ((($generation['run'] ?? false) === true) ? 'run' : 'not run')
                . '; generated='
                . (int) ($generation['generatedPackageCount'] ?? 0)
                . '/'
                . $expected
                . '; skipped='
                . (int) ($generation['skippedCaseCount'] ?? 0)
                . '; failed='
                . (int) ($generation['failedCaseCount'] ?? 0)
                . '; reason='
                . (string) ($generation['reason'] ?? 'unknown');
        }

        $comparison = $report['packageComparison'] ?? [];
        if (is_array($comparison)) {
            $expected = (int) ($comparison['expectedGoldenPackageCount'] ?? $report['goldenPackageCount'] ?? 0);
            $compared = (int) ($comparison['comparedPackageCount'] ?? 0);
            $lines[] = 'Generated package comparison: '
                . ((($comparison['run'] ?? false) === true) ? 'run' : 'not run')
                . '; compared='
                . $compared
                . '/'
                . $expected
                . '; matched='
                . (int) ($comparison['matchedPackageCount'] ?? 0)
                . '; mismatched='
                . (int) ($comparison['mismatchedPackageCount'] ?? 0)
                . '; missing='
                . (int) ($comparison['missingGeneratedPackageCount'] ?? 0)
                . '; unexpected='
                . (int) ($comparison['unexpectedGeneratedPackageCount'] ?? 0)
                . '; reason='
                . (string) ($comparison['reason'] ?? 'unknown');

            $diagnostics = $comparison['mismatchDiagnostics'] ?? [];
            if (is_array($diagnostics) && (int) ($diagnostics['stableMismatchPackageCount'] ?? 0) > 0) {
                $partDeltas = is_array($diagnostics['partNameDeltas'] ?? null) ? $diagnostics['partNameDeltas'] : [];
                $contentTypeDeltas = is_array($diagnostics['contentTypeDeltas'] ?? null) ? $diagnostics['contentTypeDeltas'] : [];
                $relationshipDeltas = is_array($diagnostics['relationshipDeltas'] ?? null) ? $diagnostics['relationshipDeltas'] : [];
                $xmlPartDeltas = is_array($diagnostics['xmlPartDeltas'] ?? null) ? $diagnostics['xmlPartDeltas'] : [];
                $lines[] = 'Mismatch diagnostics: stable mismatches='
                    . (int) ($diagnostics['stableMismatchPackageCount'] ?? 0)
                    . '; missing-part packages='
                    . (int) ($partDeltas['packagesWithMissingParts'] ?? 0)
                    . '; extra-part packages='
                    . (int) ($partDeltas['packagesWithExtraParts'] ?? 0)
                    . '; content-type delta packages='
                    . max(
                        (int) ($contentTypeDeltas['packagesWithMissingRecords'] ?? 0),
                        (int) ($contentTypeDeltas['packagesWithExtraRecords'] ?? 0)
                    )
                    . '; relationship delta packages='
                    . max(
                        (int) ($relationshipDeltas['packagesWithMissingRecords'] ?? 0),
                        (int) ($relationshipDeltas['packagesWithExtraRecords'] ?? 0)
                    )
                    . '; changed-xml packages='
                    . (int) ($xmlPartDeltas['packagesWithChangedXmlParts'] ?? 0);
            }
        }

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'Result: skipped';
            $lines[] = 'Reason: ' . (string) ($report['reason'] ?? 'golden directory unavailable');
            $lines[] = 'No DOCX writer parity is asserted.';

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = 'Golden packages: '
            . (int) ($report['goldenPackageCount'] ?? 0)
            . '; readable packages: '
            . (int) ($report['readableGoldenPackageCount'] ?? 0)
            . '; package parts: '
            . (int) ($report['packagePartCount'] ?? 0)
            . '; readable parts: '
            . (int) ($report['readablePackagePartCount'] ?? 0);

        $shape = $report['goldenPackageCommonShape'] ?? [];
        if (is_array($shape) && (int) ($shape['readablePackageCount'] ?? 0) > 0) {
            $lines[] = 'Golden package common shape: common parts='
                . (int) ($shape['commonPartNameCount'] ?? 0)
                . '; optional parts='
                . (int) ($shape['optionalPartNameCount'] ?? 0)
                . '; common content types='
                . (int) ($shape['commonContentTypeRecordCount'] ?? 0)
                . '; optional content types='
                . (int) ($shape['optionalContentTypeRecordCount'] ?? 0)
                . '; common relationships='
                . (int) ($shape['commonRelationshipRecordCount'] ?? 0)
                . '; optional relationships='
                . (int) ($shape['optionalRelationshipRecordCount'] ?? 0);
        }

        $samples = $report['packageSamples'] ?? [];
        if (is_array($samples) && $samples !== []) {
            $lines[] = 'Golden package samples:';
            foreach ($samples as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $partNames = $row['partNames'] ?? [];
                $partSummary = is_array($partNames) && $partNames !== []
                    ? implode(', ', array_slice(array_map('strval', $partNames), 0, 6))
                    : 'none';
                $lines[] = '- '
                    . (string) ($row['fileName'] ?? 'unknown.docx')
                    . ': parts='
                    . (int) ($row['partCount'] ?? 0)
                    . '; packageSha256='
                    . (string) ($row['packageSha256'] ?? '')
                    . '; firstParts='
                    . $partSummary;
            }
        }

        $lines[] = (($comparison['run'] ?? false) === true)
            ? 'Generated DOCX packages were compared by stable package semantics; full writer parity is still limited to this generated package evidence.'
            : 'No generated DOCX package parity is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredGeneratedStableMatches(array $report, int $requiredPackageCount): bool
    {
        if ($requiredPackageCount < 0) {
            throw new \InvalidArgumentException('Required generated stable match count must not be negative');
        }

        if (($report['skipped'] ?? false) === true) {
            return false;
        }

        $comparison = $report['packageComparison'] ?? null;
        if (!is_array($comparison) || ($comparison['run'] ?? false) !== true) {
            return false;
        }

        return ($comparison['status'] ?? null) === 'matched-stable-package-semantics'
            && ($comparison['allStableSemanticsMatch'] ?? false) === true
            && (int) ($comparison['expectedGoldenPackageCount'] ?? -1) === $requiredPackageCount
            && (int) ($comparison['generatedPackageCount'] ?? -1) === $requiredPackageCount
            && (int) ($comparison['comparedPackageCount'] ?? -1) === $requiredPackageCount
            && (int) ($comparison['matchedPackageCount'] ?? -1) === $requiredPackageCount
            && (int) ($comparison['mismatchedPackageCount'] ?? -1) === 0
            && (int) ($comparison['missingGeneratedPackageCount'] ?? -1) === 0
            && (int) ($comparison['unexpectedGeneratedPackageCount'] ?? -1) === 0
            && (int) ($comparison['unreadableComparisonPackageCount'] ?? -1) === 0;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function expectedUpstreamWriterSourceReferences(): array
    {
        return [
            [
                'kind' => 'writer-implementation',
                'path' => 'src/Text/Pandoc/Writers/Docx.hs',
                'role' => 'upstream DOCX writer implementation to port or compare against',
            ],
            [
                'kind' => 'writer-tests',
                'path' => 'test/Tests/Writers/Docx.hs',
                'role' => 'upstream DOCX writer golden-test definitions',
            ],
            [
                'kind' => 'writer-golden-packages',
                'path' => 'test/docx/golden/*.docx',
                'role' => 'expected upstream DOCX writer package outputs',
            ],
            [
                'kind' => 'reference-docx-template-directory',
                'path' => 'data/docx',
                'role' => 'upstream reference DOCX template directory used by writer behavior',
            ],
        ];
    }

    /**
     * @param array<mixed> $filters
     * @return list<string>
     */
    private static function normalizeCaseFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            if (!is_scalar($filter)) {
                throw new \InvalidArgumentException('Writer golden case filters must be strings');
            }

            foreach (explode(',', (string) $filter) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    throw new \InvalidArgumentException('Writer golden case filter must not be empty');
                }
                if (str_contains($candidate, "\0")) {
                    throw new \InvalidArgumentException('Writer golden case filter must not contain NUL bytes');
                }

                $normalized[$candidate] = true;
            }
        }

        $values = array_keys($normalized);
        sort($values, SORT_STRING);

        return $values;
    }

    private function caseFilterActive(): bool
    {
        return $this->caseFilters !== [];
    }

    /**
     * @return list<array{goldenFile:string, nativeFile:string, referenceDoc?:string}>
     */
    private function selectedWriterGoldenCases(): array
    {
        if (!$this->caseFilterActive()) {
            return self::WRITER_GOLDEN_CASES;
        }

        $selected = [];
        foreach (self::WRITER_GOLDEN_CASES as $case) {
            if (self::writerGoldenCaseMatchesFilters($case, $this->caseFilters)) {
                $selected[] = $case;
            }
        }

        return $selected;
    }

    private function selectedGoldenCaseCount(): int
    {
        return count($this->selectedWriterGoldenCases());
    }

    /**
     * @param list<string>|null $goldenPackageFileNames
     * @return array<string, mixed>
     */
    private function caseFilterReport(?array $goldenPackageFileNames = null): array
    {
        $selectedCases = $this->selectedWriterGoldenCases();
        $selectedGoldenFiles = array_map(
            static fn (array $case): string => $case['goldenFile'],
            $selectedCases
        );
        $selectedNativeFiles = array_map(
            static fn (array $case): string => $case['nativeFile'],
            $selectedCases
        );
        $matchingGoldenPackageFiles = [];
        if ($goldenPackageFileNames !== null) {
            foreach ($goldenPackageFileNames as $fileName) {
                if (!$this->caseFilterActive() || self::fileNameMatchesCaseFilters($fileName, $this->caseFilters)) {
                    $matchingGoldenPackageFiles[] = $fileName;
                }
            }
        }

        return [
            'active' => $this->caseFilterActive(),
            'values' => $this->caseFilters,
            'totalPinnedGoldenCaseCount' => count(self::WRITER_GOLDEN_CASES),
            'selectedPinnedGoldenCaseCount' => count($selectedCases),
            'selectedPinnedGoldenFiles' => $selectedGoldenFiles,
            'selectedPinnedNativeFiles' => $selectedNativeFiles,
            'unfilteredGoldenPackageCount' => $goldenPackageFileNames === null ? null : count($goldenPackageFileNames),
            'matchingGoldenPackageCount' => $goldenPackageFileNames === null ? null : count($matchingGoldenPackageFiles),
            'matchingGoldenPackageFiles' => $goldenPackageFileNames === null ? null : $matchingGoldenPackageFiles,
            'unmatchedValues' => $this->unmatchedCaseFilters($goldenPackageFileNames),
            'claim' => $this->caseFilterActive()
                ? 'Filters scope writer-golden inventory, generation, and generated comparison to selected case/package names; matching a focused subset is not full writer parity evidence.'
                : 'No writer-golden case filter is active.',
        ];
    }

    /**
     * @param list<string>|null $goldenPackageFileNames
     * @return list<string>
     */
    private function unmatchedCaseFilters(?array $goldenPackageFileNames): array
    {
        $unmatched = [];
        foreach ($this->caseFilters as $filter) {
            $matched = false;
            foreach (self::WRITER_GOLDEN_CASES as $case) {
                if (self::writerGoldenCaseMatchesFilter($case, $filter)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched && $goldenPackageFileNames !== null) {
                foreach ($goldenPackageFileNames as $fileName) {
                    if (self::fileNameMatchesCaseFilter($fileName, $filter)) {
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) {
                $unmatched[] = $filter;
            }
        }

        return $unmatched;
    }

    /**
     * @param array{goldenFile:string, nativeFile:string, referenceDoc?:string} $case
     * @param list<string> $filters
     */
    private static function writerGoldenCaseMatchesFilters(array $case, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (self::writerGoldenCaseMatchesFilter($case, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{goldenFile:string, nativeFile:string, referenceDoc?:string} $case
     */
    private static function writerGoldenCaseMatchesFilter(array $case, string $filter): bool
    {
        $candidates = [
            $case['goldenFile'],
            $case['nativeFile'],
        ];
        if (isset($case['referenceDoc'])) {
            $candidates[] = $case['referenceDoc'];
        }

        return self::filterMatchesCandidates($filter, $candidates);
    }

    /**
     * @param list<string> $filters
     */
    private static function fileNameMatchesCaseFilters(string $fileName, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (self::fileNameMatchesCaseFilter($fileName, $filter)) {
                return true;
            }
        }

        return false;
    }

    private static function fileNameMatchesCaseFilter(string $fileName, string $filter): bool
    {
        return self::filterMatchesCandidates($filter, [$fileName]);
    }

    /**
     * @param list<string> $candidates
     */
    private static function filterMatchesCandidates(string $filter, array $candidates): bool
    {
        $needle = self::caseFilterKey($filter);
        $needleBase = basename($needle);
        $needleStem = self::caseFilterStem($needleBase);
        foreach ($candidates as $candidate) {
            $candidateKey = self::caseFilterKey($candidate);
            $candidateBase = basename($candidateKey);
            $candidateStem = self::caseFilterStem($candidateBase);
            if (
                $needle === $candidateKey
                || $needleBase === $candidateBase
                || $needleStem === $candidateStem
            ) {
                return true;
            }
        }

        return false;
    }

    private static function caseFilterKey(string $value): string
    {
        return strtolower(str_replace('\\', '/', trim($value)));
    }

    private static function caseFilterStem(string $value): string
    {
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        if (in_array($extension, ['docx', 'native'], true)) {
            return substr($value, 0, -strlen($extension) - 1);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $writer
     * @param array<string, mixed> $generation
     * @return array<string, mixed>
     */
    private function skipReport(string $status, string $reason, array $writer, array $generation): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-writer-golden-audit',
            'status' => $status,
            'skipped' => true,
            'claim' => self::CLAIM,
            'evidenceKind' => self::EVIDENCE_KIND,
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'goldenDirectory' => $this->absoluteGoldenDirectory(),
            'goldenDirectoryDisplay' => $this->displayPath($this->absoluteGoldenDirectory()),
            'goldenDirectoryPresent' => false,
            'reason' => $reason,
            'expectedUpstreamWriterSourceReferences' => self::expectedUpstreamWriterSourceReferences(),
            'caseFilter' => $this->caseFilterReport(),
            'localWriter' => $writer,
            'generation' => $generation,
            'packageComparison' => $this->packageComparisonEvidence(
                $writer,
                [],
                $this->selectedGoldenCaseCount(),
                $status === self::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY
                    ? self::GOLDEN_DIRECTORY_MISSING_REASON
                    : self::GOLDEN_DIRECTORY_UNREADABLE_REASON
            ),
            'unfilteredGoldenPackageCount' => 0,
            'goldenPackageCount' => 0,
            'readableGoldenPackageCount' => 0,
            'unreadableGoldenPackageCount' => 0,
            'packagePartCount' => 0,
            'readablePackagePartCount' => 0,
            'goldenPackageCommonShape' => self::emptyGoldenPackageCommonShape(),
            'packageRows' => [],
            'packageSamples' => [],
            'notes' => [
                'The local upstream DOCX corpus cache is optional for CI.',
                'No writer golden package inventory or parity result is inferred when the golden directory is absent.',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $packageRows
     * @return array<string, mixed>
     */
    private function goldenPackageCommonShape(array $packageRows): array
    {
        $readableRows = array_values(array_filter(
            $packageRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'readable'
        ));
        $readableCount = count($readableRows);
        if ($readableCount === 0) {
            return self::emptyGoldenPackageCommonShape(count($packageRows));
        }

        $partNameCounts = [];
        $contentTypeRecordCounts = [];
        $relationshipPartNameCounts = [];
        $relationshipRecordCounts = [];
        $relationshipTypeTargetCounts = [];
        $partCountDistribution = [];
        $contentTypeRecordCountDistribution = [];
        $relationshipRecordCountDistribution = [];

        foreach ($readableRows as $row) {
            $partCount = (string) (int) ($row['partCount'] ?? 0);
            $partCountDistribution[$partCount] = ($partCountDistribution[$partCount] ?? 0) + 1;

            foreach (array_unique(array_map('strval', $row['partNames'] ?? [])) as $partName) {
                $partNameCounts[$partName] = ($partNameCounts[$partName] ?? 0) + 1;
            }

            $semantics = is_array($row['stableSemantics'] ?? null) ? $row['stableSemantics'] : [];
            $contentTypes = is_array($semantics['contentTypes'] ?? null) ? $semantics['contentTypes'] : [];
            $contentTypeRecordCount = (string) (int) ($contentTypes['recordCount'] ?? 0);
            $contentTypeRecordCountDistribution[$contentTypeRecordCount] = ($contentTypeRecordCountDistribution[$contentTypeRecordCount] ?? 0) + 1;
            foreach ($contentTypes['records'] ?? [] as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $key = self::stableJson($record);
                $contentTypeRecordCounts[$key] = ($contentTypeRecordCounts[$key] ?? 0) + 1;
            }

            $relationships = is_array($semantics['relationships'] ?? null) ? $semantics['relationships'] : [];
            $relationshipRecordCount = (string) (int) ($relationships['relationshipRecordCount'] ?? 0);
            $relationshipRecordCountDistribution[$relationshipRecordCount] = ($relationshipRecordCountDistribution[$relationshipRecordCount] ?? 0) + 1;
            foreach ($relationships['relationshipParts'] ?? [] as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $relationshipPartName = (string) ($part['relationshipPartName'] ?? '');
                if ($relationshipPartName === '') {
                    continue;
                }
                $relationshipPartNameCounts[$relationshipPartName] = ($relationshipPartNameCounts[$relationshipPartName] ?? 0) + 1;
            }
            foreach ($relationships['records'] ?? [] as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $key = self::stableJson($record);
                $relationshipRecordCounts[$key] = ($relationshipRecordCounts[$key] ?? 0) + 1;

                $typeTarget = [
                    'sourcePartName' => $record['sourcePartName'] ?? null,
                    'relationshipType' => $record['relationshipType'] ?? null,
                    'targetMode' => $record['targetMode'] ?? null,
                    'resolvedTarget' => $record['resolvedTarget'] ?? null,
                ];
                $typeTargetKey = self::stableJson($typeTarget);
                $relationshipTypeTargetCounts[$typeTargetKey] = ($relationshipTypeTargetCounts[$typeTargetKey] ?? 0) + 1;
            }
        }

        ksort($partCountDistribution, SORT_NUMERIC);
        ksort($contentTypeRecordCountDistribution, SORT_NUMERIC);
        ksort($relationshipRecordCountDistribution, SORT_NUMERIC);

        $commonPartNames = self::commonKeys($partNameCounts, $readableCount);
        $optionalPartNameRows = self::frequencyRows($partNameCounts, $readableCount, false);
        $commonContentTypeRecordRows = self::decodedFrequencyRows($contentTypeRecordCounts, $readableCount, true);
        $optionalContentTypeRecordRows = self::decodedFrequencyRows($contentTypeRecordCounts, $readableCount, false);
        $commonRelationshipPartNames = self::commonKeys($relationshipPartNameCounts, $readableCount);
        $optionalRelationshipPartNameRows = self::frequencyRows($relationshipPartNameCounts, $readableCount, false);
        $commonRelationshipRecordRows = self::decodedFrequencyRows($relationshipRecordCounts, $readableCount, true);
        $optionalRelationshipRecordRows = self::decodedFrequencyRows($relationshipRecordCounts, $readableCount, false);
        $commonRelationshipTypeTargetRows = self::decodedFrequencyRows($relationshipTypeTargetCounts, $readableCount, true);
        $optionalRelationshipTypeTargetRows = self::decodedFrequencyRows($relationshipTypeTargetCounts, $readableCount, false);

        return [
            'schemaVersion' => 1,
            'packageCount' => count($packageRows),
            'readablePackageCount' => $readableCount,
            'commonThresholdCount' => $readableCount,
            'partCountDistribution' => $partCountDistribution,
            'contentTypeRecordCountDistribution' => $contentTypeRecordCountDistribution,
            'relationshipRecordCountDistribution' => $relationshipRecordCountDistribution,
            'commonPartNameCount' => count($commonPartNames),
            'optionalPartNameCount' => count($optionalPartNameRows),
            'commonPartNames' => $commonPartNames,
            'optionalPartNameRows' => $optionalPartNameRows,
            'commonContentTypeRecordCount' => count($commonContentTypeRecordRows),
            'optionalContentTypeRecordCount' => count($optionalContentTypeRecordRows),
            'commonContentTypeRecordRows' => $commonContentTypeRecordRows,
            'optionalContentTypeRecordRows' => $optionalContentTypeRecordRows,
            'commonRelationshipPartNames' => $commonRelationshipPartNames,
            'optionalRelationshipPartNameRows' => $optionalRelationshipPartNameRows,
            'commonRelationshipRecordCount' => count($commonRelationshipRecordRows),
            'optionalRelationshipRecordCount' => count($optionalRelationshipRecordRows),
            'commonRelationshipRecordRows' => $commonRelationshipRecordRows,
            'optionalRelationshipRecordRows' => $optionalRelationshipRecordRows,
            'commonRelationshipTypeTargetRows' => $commonRelationshipTypeTargetRows,
            'optionalRelationshipTypeTargetRows' => $optionalRelationshipTypeTargetRows,
            'claim' => 'Aggregate package-shape inventory only; records common and optional upstream golden DOCX part names, content-type records, and relationship records without asserting generated writer parity.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyGoldenPackageCommonShape(int $packageCount = 0): array
    {
        return [
            'schemaVersion' => 1,
            'packageCount' => $packageCount,
            'readablePackageCount' => 0,
            'commonThresholdCount' => 0,
            'partCountDistribution' => [],
            'contentTypeRecordCountDistribution' => [],
            'relationshipRecordCountDistribution' => [],
            'commonPartNameCount' => 0,
            'optionalPartNameCount' => 0,
            'commonPartNames' => [],
            'optionalPartNameRows' => [],
            'commonContentTypeRecordCount' => 0,
            'optionalContentTypeRecordCount' => 0,
            'commonContentTypeRecordRows' => [],
            'optionalContentTypeRecordRows' => [],
            'commonRelationshipPartNames' => [],
            'optionalRelationshipPartNameRows' => [],
            'commonRelationshipRecordCount' => 0,
            'optionalRelationshipRecordCount' => 0,
            'commonRelationshipRecordRows' => [],
            'optionalRelationshipRecordRows' => [],
            'commonRelationshipTypeTargetRows' => [],
            'optionalRelationshipTypeTargetRows' => [],
            'claim' => 'Aggregate package-shape inventory only; no readable upstream writer golden DOCX packages were available.',
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return list<string>
     */
    private static function commonKeys(array $counts, int $threshold): array
    {
        $keys = [];
        foreach ($counts as $key => $count) {
            if ($count === $threshold) {
                $keys[] = $key;
            }
        }
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{value:string,count:int}>
     */
    private static function frequencyRows(array $counts, int $threshold, bool $common): array
    {
        $rows = [];
        foreach ($counts as $value => $count) {
            if (($count === $threshold) !== $common) {
                continue;
            }
            $rows[] = [
                'value' => $value,
                'count' => $count,
            ];
        }

        usort(
            $rows,
            static function (array $left, array $right): int {
                if ($left['count'] !== $right['count']) {
                    return $right['count'] <=> $left['count'];
                }

                return strcmp($left['value'], $right['value']);
            }
        );

        return $rows;
    }

    /**
     * @param array<string, int> $counts
     * @return list<array<string, mixed>>
     */
    private static function decodedFrequencyRows(array $counts, int $threshold, bool $common): array
    {
        $rows = [];
        foreach (self::frequencyRows($counts, $threshold, $common) as $row) {
            $decoded = json_decode($row['value'], true);
            if (!is_array($decoded)) {
                $decoded = ['value' => $row['value']];
            }
            $decoded['count'] = $row['count'];
            $rows[] = $decoded;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function localWriterEvidence(): array
    {
        $writerPath = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::EXPECTED_WRITER_RELATIVE_PATH);
        $writerFileExists = is_file($writerPath);
        $writerClassExists = class_exists(self::EXPECTED_WRITER_CLASS);
        $registry = PandocFormatRegistry::phpOutputSupport()['docx'] ?? [
            'status' => 'unknown',
            'implementation' => '',
            'notes' => 'DOCX output format is missing from the registry.',
        ];
        $registryStatus = (string) ($registry['status'] ?? 'unknown');
        $unsupported = !$writerFileExists && !$writerClassExists && $registryStatus === 'unsupported';

        return [
            'status' => $unsupported ? 'unsupported' : 'implementation-present-or-registered',
            'expectedClass' => self::EXPECTED_WRITER_CLASS,
            'classExists' => $writerClassExists,
            'expectedPath' => self::EXPECTED_WRITER_RELATIVE_PATH,
            'absolutePath' => $writerPath,
            'fileExists' => $writerFileExists,
            'registryFormat' => 'docx',
            'registryStatus' => $registryStatus,
            'registryImplementation' => (string) ($registry['implementation'] ?? ''),
            'registryNotes' => (string) ($registry['notes'] ?? ''),
            'unsupportedReason' => $unsupported ? self::OPEN_REASON : self::COMPARISON_NOT_RECORDED_REASON,
        ];
    }

    /**
     * @param array<string, mixed> $writer
     * @return array<string, mixed>
     */
    private function generationEvidence(array $writer): array
    {
        $outputDir = $this->absoluteGenerationOutputDirectory();
        $outputDirConfigured = $outputDir !== null;
        $sourceDir = $this->absoluteDocxDirectory();
        $writerGoldenCases = $this->selectedWriterGoldenCases();
        $expectedCount = count($writerGoldenCases);
        $base = [
            'run' => false,
            'status' => 'not-run',
            'reason' => self::GENERATION_DIRECTORY_NOT_CONFIGURED_REASON,
            'sourceDirectory' => $sourceDir,
            'sourceDirectoryDisplay' => $this->displayPath($sourceDir),
            'sourceDirectoryPresent' => is_dir($sourceDir),
            'outputDirectoryConfigured' => $outputDirConfigured,
            'outputDirectory' => $outputDir,
            'outputDirectoryDisplay' => $outputDir === null ? null : $this->displayPath($outputDir),
            'outputDirectoryPresent' => $outputDir !== null && is_dir($outputDir),
            'caseFilterActive' => $this->caseFilterActive(),
            'totalPinnedGoldenCaseCount' => count(self::WRITER_GOLDEN_CASES),
            'expectedGoldenCaseCount' => $expectedCount,
            'attemptedCaseCount' => 0,
            'generatedPackageCount' => 0,
            'skippedCaseCount' => $outputDirConfigured ? 0 : $expectedCount,
            'failedCaseCount' => 0,
            'generationCoveragePercent' => self::percent(0, $expectedCount),
            'blockerCounts' => $outputDirConfigured ? [] : [self::GENERATION_DIRECTORY_NOT_CONFIGURED_REASON => 1],
            'caseRows' => $outputDirConfigured ? [] : $this->generationCaseRows('skipped-generation-directory-not-configured', self::GENERATION_DIRECTORY_NOT_CONFIGURED_REASON),
            'caseSamples' => [],
        ];

        if ($outputDir === null) {
            return array_replace($base, [
                'status' => 'not-run-generation-directory-not-configured',
                'caseSamples' => array_slice($base['caseRows'], 0, $this->sampleLimit),
            ]);
        }

        if (($writer['status'] ?? null) === 'unsupported') {
            $rows = $this->generationCaseRows('skipped-writer-unavailable', self::GENERATION_WRITER_UNAVAILABLE_REASON);

            return array_replace($base, [
                'status' => 'not-run-writer-unavailable',
                'reason' => self::GENERATION_WRITER_UNAVAILABLE_REASON,
                'skippedCaseCount' => $expectedCount,
                'blockerCounts' => [self::GENERATION_WRITER_UNAVAILABLE_REASON => 1],
                'caseRows' => $rows,
                'caseSamples' => array_slice($rows, 0, $this->sampleLimit),
            ]);
        }

        if (!is_dir($sourceDir)) {
            $rows = $this->generationCaseRows('skipped-upstream-source-directory-missing', self::GENERATION_SOURCE_DIRECTORY_MISSING_REASON);

            return array_replace($base, [
                'status' => 'not-run-upstream-docx-source-directory-missing',
                'reason' => self::GENERATION_SOURCE_DIRECTORY_MISSING_REASON,
                'skippedCaseCount' => $expectedCount,
                'blockerCounts' => [self::GENERATION_SOURCE_DIRECTORY_MISSING_REASON => 1],
                'caseRows' => $rows,
                'caseSamples' => array_slice($rows, 0, $this->sampleLimit),
            ]);
        }

        if (!is_readable($sourceDir)) {
            $rows = $this->generationCaseRows('skipped-upstream-source-directory-unreadable', self::GENERATION_SOURCE_DIRECTORY_UNREADABLE_REASON);

            return array_replace($base, [
                'status' => 'not-run-upstream-docx-source-directory-unreadable',
                'reason' => self::GENERATION_SOURCE_DIRECTORY_UNREADABLE_REASON,
                'sourceDirectoryPresent' => true,
                'skippedCaseCount' => $expectedCount,
                'blockerCounts' => [self::GENERATION_SOURCE_DIRECTORY_UNREADABLE_REASON => 1],
                'caseRows' => $rows,
                'caseSamples' => array_slice($rows, 0, $this->sampleLimit),
            ]);
        }

        if ((!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) || !is_writable($outputDir)) {
            $rows = $this->generationCaseRows('skipped-output-directory-unwritable', self::GENERATION_OUTPUT_DIRECTORY_UNWRITABLE_REASON);

            return array_replace($base, [
                'status' => 'not-run-output-directory-unwritable',
                'reason' => self::GENERATION_OUTPUT_DIRECTORY_UNWRITABLE_REASON,
                'sourceDirectoryPresent' => true,
                'skippedCaseCount' => $expectedCount,
                'blockerCounts' => [self::GENERATION_OUTPUT_DIRECTORY_UNWRITABLE_REASON => 1],
                'caseRows' => $rows,
                'caseSamples' => array_slice($rows, 0, $this->sampleLimit),
            ]);
        }

        $nativeReader = new NativeReader();
        $baseWriterOptions = [
            'mediaBasePaths' => array_values(array_unique([$sourceDir, dirname($sourceDir)])),
        ];
        $rows = [];
        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $blockerCounts = [];

        foreach ($writerGoldenCases as $case) {
            $nativeFile = $case['nativeFile'];
            $goldenFile = $case['goldenFile'];
            $nativePath = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $nativeFile);
            $outputPath = $outputDir . DIRECTORY_SEPARATOR . $goldenFile;
            $row = [
                'goldenFile' => $goldenFile,
                'nativeFile' => $nativeFile,
                'nativeSource' => $this->displayPath($nativePath),
                'generatedPackage' => $this->displayPath($outputPath),
            ];
            $writerOptions = $baseWriterOptions;
            $referencePath = null;
            if (isset($case['referenceDoc'])) {
                $referencePath = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $case['referenceDoc']);
                $row['referenceDoc'] = $case['referenceDoc'];
                $row['referenceDocSource'] = $this->displayPath($referencePath);
                $row['referenceDocHandled'] = true;
                $writerOptions['referenceDocxPath'] = $referencePath;
            }

            if (!is_file($nativePath)) {
                ++$skipped;
                $reason = 'native-source-missing';
                $blockerCounts[$reason] = ($blockerCounts[$reason] ?? 0) + 1;
                $rows[] = array_replace($row, [
                    'status' => 'skipped-native-source-missing',
                    'reason' => $reason,
                ]);
                continue;
            }
            if ($referencePath !== null && !is_file($referencePath)) {
                ++$skipped;
                $reason = 'reference-doc-missing';
                $blockerCounts[$reason] = ($blockerCounts[$reason] ?? 0) + 1;
                $rows[] = array_replace($row, [
                    'status' => 'skipped-reference-doc-missing',
                    'reason' => $reason,
                ]);
                continue;
            }

            try {
                $native = file_get_contents($nativePath);
                if (!is_string($native)) {
                    throw new \RuntimeException("Unable to read native fixture: {$nativePath}");
                }
                $document = $nativeReader->read($native);
                $writerInstance = new DocxWriter($writerOptions);
                $docx = $writerInstance->write($document);
                if (file_put_contents($outputPath, $docx) === false) {
                    throw new \RuntimeException("Unable to write generated DOCX package: {$outputPath}");
                }

                ++$generated;
                $rows[] = array_replace($row, [
                    'status' => 'generated',
                    'reason' => 'generated-docx-package',
                    'packageBytes' => strlen($docx),
                    'packageSha256' => hash('sha256', $docx),
                ]);
            } catch (\Throwable $throwable) {
                ++$failed;
                $blockerCounts[self::GENERATION_WRITE_FAILED_REASON] = ($blockerCounts[self::GENERATION_WRITE_FAILED_REASON] ?? 0) + 1;
                $rows[] = array_replace($row, [
                    'status' => 'failed',
                    'reason' => self::GENERATION_WRITE_FAILED_REASON,
                    'errorClass' => $throwable::class,
                    'message' => self::oneLine($throwable->getMessage()),
                ]);
            }
        }

        $status = $generated === $expectedCount && $failed === 0 && $skipped === 0
            ? 'generated-all-writer-golden-cases'
            : ($generated > 0 ? 'generated-supported-writer-golden-subset' : 'generated-no-writer-golden-packages');

        return array_replace($base, [
            'run' => true,
            'status' => $status,
            'reason' => $status,
            'sourceDirectoryPresent' => true,
            'outputDirectoryPresent' => true,
            'attemptedCaseCount' => $expectedCount - $skipped,
            'generatedPackageCount' => $generated,
            'skippedCaseCount' => $skipped,
            'failedCaseCount' => $failed,
            'generationCoveragePercent' => self::percent($generated, $expectedCount),
            'blockerCounts' => $blockerCounts,
            'caseRows' => $rows,
            'caseSamples' => array_slice($rows, 0, $this->sampleLimit),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generationCaseRows(string $status, string $reason): array
    {
        $sourceDir = $this->absoluteDocxDirectory();
        $outputDir = $this->absoluteGenerationOutputDirectory();
        $rows = [];
        foreach ($this->selectedWriterGoldenCases() as $case) {
            $nativePath = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $case['nativeFile']);
            $row = [
                'goldenFile' => $case['goldenFile'],
                'nativeFile' => $case['nativeFile'],
                'nativeSource' => $this->displayPath($nativePath),
                'status' => $status,
                'reason' => $reason,
            ];
            if ($outputDir !== null) {
                $row['generatedPackage'] = $this->displayPath($outputDir . DIRECTORY_SEPARATOR . $case['goldenFile']);
            }
            if (isset($case['referenceDoc'])) {
                $row['referenceDoc'] = $case['referenceDoc'];
                $row['referenceDocHandled'] = false;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $writer
     * @return array<string, mixed>
     */
    private function packageComparisonEvidence(
        array $writer,
        array $goldenPackageRows,
        ?int $expectedGoldenPackageCount = null,
        ?string $goldenUnavailableReason = null
    ): array
    {
        $expectedGoldenPackageCount ??= count($goldenPackageRows);
        $generatedDir = $this->absoluteGeneratedDirectory();
        $generatedDirConfigured = $generatedDir !== null;
        $base = [
            'run' => false,
            'status' => 'not-run',
            'generatedDirectoryConfigured' => $generatedDirConfigured,
            'generatedDirectory' => $generatedDir,
            'generatedDirectoryDisplay' => $generatedDir === null ? null : $this->displayPath($generatedDir),
            'generatedDirectoryPresent' => $generatedDir !== null && is_dir($generatedDir),
            'caseFilterActive' => $this->caseFilterActive(),
            'caseFilterValues' => $this->caseFilters,
            'stableComparisonContract' => self::stableComparisonContract(),
            'expectedGoldenPackageCount' => $expectedGoldenPackageCount,
            'generatedPackageCount' => 0,
            'comparedPackageCount' => 0,
            'matchedPackageCount' => 0,
            'mismatchedPackageCount' => 0,
            'missingGeneratedPackageCount' => $expectedGoldenPackageCount,
            'unexpectedGeneratedPackageCount' => 0,
            'unreadableComparisonPackageCount' => 0,
            'comparisonCoveragePercent' => self::percent(0, $expectedGoldenPackageCount),
            'stableMatchPercent' => self::percent(0, $expectedGoldenPackageCount),
            'allStableSemanticsMatch' => false,
            'reason' => (string) ($writer['unsupportedReason'] ?? self::OPEN_REASON),
            'requiredBeforeParityClaim' => 'Run the native PHP DocxWriter against upstream writer golden cases and compare package parts, relationships, content types, and document XML semantics.',
            'mismatchDiagnostics' => self::emptyMismatchDiagnostics(),
            'comparisonRows' => [],
            'comparisonSamples' => [],
        ];

        if ($generatedDir === null) {
            $reason = (($writer['status'] ?? null) === 'unsupported')
                ? (string) ($writer['unsupportedReason'] ?? self::OPEN_REASON)
                : self::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON;

            return array_replace($base, [
                'status' => 'not-run-generated-directory-not-configured',
                'reason' => $reason,
            ]);
        }

        if ($goldenUnavailableReason !== null) {
            $status = $goldenUnavailableReason === self::GOLDEN_DIRECTORY_UNREADABLE_REASON
                ? 'not-run-golden-directory-unreadable'
                : 'not-run-golden-directory-missing';

            return array_replace($base, [
                'status' => $status,
                'reason' => $goldenUnavailableReason,
            ]);
        }

        if (!is_dir($generatedDir)) {
            return array_replace($base, [
                'status' => 'not-run-generated-directory-missing',
                'reason' => self::GENERATED_DIRECTORY_MISSING_REASON,
            ]);
        }

        if (!is_readable($generatedDir)) {
            return array_replace($base, [
                'status' => 'not-run-generated-directory-unreadable',
                'reason' => self::GENERATED_DIRECTORY_UNREADABLE_REASON,
            ]);
        }

        $generatedRows = $this->packageRows($generatedDir);
        $comparisonRows = $this->comparisonRows($goldenPackageRows, $generatedRows);
        $generatedPackageCount = count($generatedRows);
        $comparedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['compared'] ?? false) === true
        ));
        $matchedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'stable-match'
        ));
        $mismatchedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'stable-mismatch'
        ));
        $missingCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'missing-generated'
        ));
        $unexpectedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'unexpected-generated'
        ));
        $unreadableCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'unreadable-package'
        ));
        $allMatch = $expectedGoldenPackageCount > 0
            && $matchedCount === $expectedGoldenPackageCount
            && $mismatchedCount === 0
            && $missingCount === 0
            && $unexpectedCount === 0
            && $unreadableCount === 0;
        $mismatchDiagnostics = self::mismatchDiagnostics(
            $goldenPackageRows,
            $generatedRows,
            $comparisonRows,
            max(8, $this->sampleLimit)
        );

        return array_replace($base, [
            'run' => true,
            'status' => $allMatch ? 'matched-stable-package-semantics' : 'mismatched-stable-package-semantics',
            'generatedDirectoryPresent' => true,
            'generatedPackageCount' => $generatedPackageCount,
            'comparedPackageCount' => $comparedCount,
            'matchedPackageCount' => $matchedCount,
            'mismatchedPackageCount' => $mismatchedCount,
            'missingGeneratedPackageCount' => $missingCount,
            'unexpectedGeneratedPackageCount' => $unexpectedCount,
            'unreadableComparisonPackageCount' => $unreadableCount,
            'comparisonCoveragePercent' => self::percent($comparedCount, $expectedGoldenPackageCount),
            'stableMatchPercent' => self::percent($matchedCount, $expectedGoldenPackageCount),
            'allStableSemanticsMatch' => $allMatch,
            'reason' => $allMatch ? self::GENERATED_COMPARISON_MATCH_REASON : self::GENERATED_COMPARISON_MISMATCH_REASON,
            'mismatchDiagnostics' => $mismatchDiagnostics,
            'comparisonRows' => $comparisonRows,
            'comparisonSamples' => array_slice($comparisonRows, 0, $this->sampleLimit),
            'generatedPackageSamples' => array_slice($generatedRows, 0, $this->sampleLimit),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyMismatchDiagnostics(): array
    {
        return [
            'schemaVersion' => 1,
            'stableMismatchPackageCount' => 0,
            'mismatchKindCounts' => [],
            'partNameDeltas' => [
                'packagesWithMissingParts' => 0,
                'packagesWithExtraParts' => 0,
                'missingPartNameCounts' => [],
                'extraPartNameCounts' => [],
            ],
            'contentTypeDeltas' => [
                'packagesWithMissingRecords' => 0,
                'packagesWithExtraRecords' => 0,
                'missingRecordCounts' => [],
                'extraRecordCounts' => [],
            ],
            'relationshipDeltas' => [
                'packagesWithMissingRecords' => 0,
                'packagesWithExtraRecords' => 0,
                'missingRecordCounts' => [],
                'extraRecordCounts' => [],
            ],
            'xmlPartDeltas' => [
                'packagesWithMissingXmlParts' => 0,
                'packagesWithExtraXmlParts' => 0,
                'packagesWithChangedXmlParts' => 0,
                'missingXmlPartCounts' => [],
                'extraXmlPartCounts' => [],
                'changedXmlPartCounts' => [],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $goldenRows
     * @param list<array<string, mixed>> $generatedRows
     * @param list<array<string, mixed>> $comparisonRows
     * @return array<string, mixed>
     */
    private static function mismatchDiagnostics(array $goldenRows, array $generatedRows, array $comparisonRows, int $limit): array
    {
        $generatedByName = [];
        foreach ($generatedRows as $row) {
            $generatedByName[(string) ($row['fileName'] ?? '')] = $row;
        }
        $goldenByName = [];
        foreach ($goldenRows as $row) {
            $goldenByName[(string) ($row['fileName'] ?? '')] = $row;
        }

        $kindCounts = [];
        $missingPartNames = [];
        $extraPartNames = [];
        $missingContentTypeRecords = [];
        $extraContentTypeRecords = [];
        $missingRelationshipRecords = [];
        $extraRelationshipRecords = [];
        $missingXmlParts = [];
        $extraXmlParts = [];
        $changedXmlParts = [];
        $packagesWithMissingParts = 0;
        $packagesWithExtraParts = 0;
        $packagesWithMissingContentTypes = 0;
        $packagesWithExtraContentTypes = 0;
        $packagesWithMissingRelationships = 0;
        $packagesWithExtraRelationships = 0;
        $packagesWithMissingXmlParts = 0;
        $packagesWithExtraXmlParts = 0;
        $packagesWithChangedXmlParts = 0;
        $stableMismatchPackageCount = 0;

        foreach ($comparisonRows as $comparisonRow) {
            if (($comparisonRow['status'] ?? null) !== 'stable-mismatch') {
                continue;
            }

            ++$stableMismatchPackageCount;
            $fileName = (string) ($comparisonRow['fileName'] ?? '');
            foreach (self::stringList($comparisonRow['mismatchKinds'] ?? []) as $kind) {
                $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            }

            $goldenSemantics = self::rowStableSemantics($goldenByName[$fileName] ?? []);
            $generatedSemantics = self::rowStableSemantics($generatedByName[$fileName] ?? []);

            $partDelta = self::stringSetDelta(
                self::stringList($goldenSemantics['partNames'] ?? []),
                self::stringList($generatedSemantics['partNames'] ?? [])
            );
            if ($partDelta['missing'] !== []) {
                ++$packagesWithMissingParts;
                foreach ($partDelta['missing'] as $partName) {
                    self::addDiagnosticBucket($missingPartNames, $partName, ['partName' => $partName], $fileName);
                }
            }
            if ($partDelta['extra'] !== []) {
                ++$packagesWithExtraParts;
                foreach ($partDelta['extra'] as $partName) {
                    self::addDiagnosticBucket($extraPartNames, $partName, ['partName' => $partName], $fileName);
                }
            }

            $contentTypeDelta = self::recordSetDelta(
                self::recordList($goldenSemantics['contentTypes']['records'] ?? []),
                self::recordList($generatedSemantics['contentTypes']['records'] ?? []),
                'contentTypeRecordKey'
            );
            if ($contentTypeDelta['missing'] !== []) {
                ++$packagesWithMissingContentTypes;
                foreach ($contentTypeDelta['missing'] as $record) {
                    self::addDiagnosticBucket($missingContentTypeRecords, self::contentTypeRecordKey($record), ['record' => $record], $fileName);
                }
            }
            if ($contentTypeDelta['extra'] !== []) {
                ++$packagesWithExtraContentTypes;
                foreach ($contentTypeDelta['extra'] as $record) {
                    self::addDiagnosticBucket($extraContentTypeRecords, self::contentTypeRecordKey($record), ['record' => $record], $fileName);
                }
            }

            $relationshipDelta = self::recordSetDelta(
                self::recordList($goldenSemantics['relationships']['records'] ?? []),
                self::recordList($generatedSemantics['relationships']['records'] ?? []),
                'relationshipRecordKey'
            );
            if ($relationshipDelta['missing'] !== []) {
                ++$packagesWithMissingRelationships;
                foreach ($relationshipDelta['missing'] as $record) {
                    self::addDiagnosticBucket($missingRelationshipRecords, self::relationshipRecordKey($record), ['record' => $record], $fileName);
                }
            }
            if ($relationshipDelta['extra'] !== []) {
                ++$packagesWithExtraRelationships;
                foreach ($relationshipDelta['extra'] as $record) {
                    self::addDiagnosticBucket($extraRelationshipRecords, self::relationshipRecordKey($record), ['record' => $record], $fileName);
                }
            }

            $xmlDelta = self::xmlPartDelta(
                self::recordList($goldenSemantics['xmlPartRows'] ?? []),
                self::recordList($generatedSemantics['xmlPartRows'] ?? [])
            );
            if ($xmlDelta['missing'] !== []) {
                ++$packagesWithMissingXmlParts;
                foreach ($xmlDelta['missing'] as $row) {
                    $partName = (string) ($row['name'] ?? '');
                    self::addDiagnosticBucket($missingXmlParts, $partName, ['partName' => $partName], $fileName);
                }
            }
            if ($xmlDelta['extra'] !== []) {
                ++$packagesWithExtraXmlParts;
                foreach ($xmlDelta['extra'] as $row) {
                    $partName = (string) ($row['name'] ?? '');
                    self::addDiagnosticBucket($extraXmlParts, $partName, ['partName' => $partName], $fileName);
                }
            }
            if ($xmlDelta['changed'] !== []) {
                ++$packagesWithChangedXmlParts;
                foreach ($xmlDelta['changed'] as $row) {
                    $partName = (string) ($row['partName'] ?? '');
                    $key = implode("\0", [
                        $partName,
                        (string) ($row['goldenSemanticStatus'] ?? ''),
                        (string) ($row['generatedSemanticStatus'] ?? ''),
                    ]);
                    self::addDiagnosticBucket($changedXmlParts, $key, $row, $fileName);
                }
            }
        }

        ksort($kindCounts, SORT_STRING);

        return [
            'schemaVersion' => 1,
            'stableMismatchPackageCount' => $stableMismatchPackageCount,
            'mismatchKindCounts' => $kindCounts,
            'partNameDeltas' => [
                'packagesWithMissingParts' => $packagesWithMissingParts,
                'packagesWithExtraParts' => $packagesWithExtraParts,
                'missingPartNameCounts' => self::topDiagnosticRows($missingPartNames, $limit),
                'extraPartNameCounts' => self::topDiagnosticRows($extraPartNames, $limit),
            ],
            'contentTypeDeltas' => [
                'packagesWithMissingRecords' => $packagesWithMissingContentTypes,
                'packagesWithExtraRecords' => $packagesWithExtraContentTypes,
                'missingRecordCounts' => self::topDiagnosticRows($missingContentTypeRecords, $limit),
                'extraRecordCounts' => self::topDiagnosticRows($extraContentTypeRecords, $limit),
            ],
            'relationshipDeltas' => [
                'packagesWithMissingRecords' => $packagesWithMissingRelationships,
                'packagesWithExtraRecords' => $packagesWithExtraRelationships,
                'missingRecordCounts' => self::topDiagnosticRows($missingRelationshipRecords, $limit),
                'extraRecordCounts' => self::topDiagnosticRows($extraRelationshipRecords, $limit),
            ],
            'xmlPartDeltas' => [
                'packagesWithMissingXmlParts' => $packagesWithMissingXmlParts,
                'packagesWithExtraXmlParts' => $packagesWithExtraXmlParts,
                'packagesWithChangedXmlParts' => $packagesWithChangedXmlParts,
                'missingXmlPartCounts' => self::topDiagnosticRows($missingXmlParts, $limit),
                'extraXmlPartCounts' => self::topDiagnosticRows($extraXmlParts, $limit),
                'changedXmlPartCounts' => self::topDiagnosticRows($changedXmlParts, $limit),
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function stableComparisonContract(): array
    {
        return [
            'compares' => [
                'DOCX package file names against upstream test/docx/golden/*.docx names',
                'non-directory OPC package part-name set',
                'OPC [Content_Types].xml Default and Override records sorted by semantic key',
                'all OPC .rels relationship records sorted by source part, relationship id, type, target mode, and resolved target',
                'XML document-part semantics using namespace/local-name element and attribute records with formatting-only whitespace ignored',
                'targeted XML feature-count summaries for tables, captions, drawings, VML textboxes, and SDT markers',
                'binary part uncompressed byte size and SHA-256 payload digests',
            ],
            'ignores' => [
                'raw ZIP package byte equality',
                'ZIP central-directory order, local-entry order, compression method, comments, and timestamps',
                'docProps/core.xml dcterms:created and dcterms:modified values, which reflect writer run time when the native source has no timestamp',
                'XML attribute order, namespace prefix spelling, indentation, and formatting-only whitespace',
                'content-types child order and relationship child order',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $goldenRows
     * @param list<array<string, mixed>> $generatedRows
     * @return list<array<string, mixed>>
     */
    private function comparisonRows(array $goldenRows, array $generatedRows): array
    {
        $generatedByName = [];
        foreach ($generatedRows as $row) {
            $generatedByName[(string) ($row['fileName'] ?? '')] = $row;
        }

        $goldenNames = [];
        $rows = [];
        foreach ($goldenRows as $goldenRow) {
            $fileName = (string) ($goldenRow['fileName'] ?? '');
            $goldenNames[$fileName] = true;
            $generatedRow = $generatedByName[$fileName] ?? null;
            if (!is_array($generatedRow)) {
                $rows[] = [
                    'fileName' => $fileName,
                    'status' => 'missing-generated',
                    'compared' => false,
                    'goldenPackageSha256' => $goldenRow['packageSha256'] ?? null,
                    'generatedPackageSha256' => null,
                    'goldenStablePackageSha256' => $goldenRow['stableSemantics']['packageStableSemanticsSha256'] ?? null,
                    'generatedStablePackageSha256' => null,
                    'mismatchKinds' => ['missing-generated-package'],
                ];
                continue;
            }

            if (($goldenRow['status'] ?? null) !== 'readable' || ($generatedRow['status'] ?? null) !== 'readable') {
                $rows[] = [
                    'fileName' => $fileName,
                    'status' => 'unreadable-package',
                    'compared' => false,
                    'goldenStatus' => (string) ($goldenRow['status'] ?? 'unknown'),
                    'generatedStatus' => (string) ($generatedRow['status'] ?? 'unknown'),
                    'goldenMessage' => $goldenRow['message'] ?? null,
                    'generatedMessage' => $generatedRow['message'] ?? null,
                    'mismatchKinds' => ['unreadable-package'],
                ];
                continue;
            }

            $goldenSemantics = is_array($goldenRow['stableSemantics'] ?? null) ? $goldenRow['stableSemantics'] : [];
            $generatedSemantics = is_array($generatedRow['stableSemantics'] ?? null) ? $generatedRow['stableSemantics'] : [];
            $mismatchKinds = self::stableSemanticMismatchKinds($goldenSemantics, $generatedSemantics);
            $row = [
                'fileName' => $fileName,
                'status' => $mismatchKinds === [] ? 'stable-match' : 'stable-mismatch',
                'compared' => true,
                'goldenPackageBytes' => $goldenRow['packageBytes'] ?? null,
                'generatedPackageBytes' => $generatedRow['packageBytes'] ?? null,
                'goldenPackageSha256' => $goldenRow['packageSha256'] ?? null,
                'generatedPackageSha256' => $generatedRow['packageSha256'] ?? null,
                'goldenStablePackageSha256' => $goldenSemantics['packageStableSemanticsSha256'] ?? null,
                'generatedStablePackageSha256' => $generatedSemantics['packageStableSemanticsSha256'] ?? null,
                'mismatchKinds' => $mismatchKinds,
            ];
            if ($mismatchKinds !== []) {
                $row['mismatchDetails'] = self::caseMismatchDetails($goldenSemantics, $generatedSemantics, max(8, $this->sampleLimit));
            }

            $rows[] = $row;
        }

        foreach ($generatedRows as $generatedRow) {
            $fileName = (string) ($generatedRow['fileName'] ?? '');
            if (isset($goldenNames[$fileName])) {
                continue;
            }

            $rows[] = [
                'fileName' => $fileName,
                'status' => 'unexpected-generated',
                'compared' => false,
                'goldenPackageSha256' => null,
                'generatedPackageSha256' => $generatedRow['packageSha256'] ?? null,
                'goldenStablePackageSha256' => null,
                'generatedStablePackageSha256' => $generatedRow['stableSemantics']['packageStableSemanticsSha256'] ?? null,
                'mismatchKinds' => ['unexpected-generated-package'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $golden
     * @param array<string, mixed> $generated
     * @return list<string>
     */
    private static function stableSemanticMismatchKinds(array $golden, array $generated): array
    {
        $checks = [
            'partNameSetSha256' => 'part-name-set',
            'contentTypesSha256' => 'content-types',
            'relationshipsSha256' => 'relationships',
            'xmlPartSemanticsSha256' => 'xml-part-semantics',
            'binaryPartPayloadSha256' => 'binary-part-payloads',
            'packageStableSemanticsSha256' => 'package-stable-semantics',
        ];

        $mismatches = [];
        foreach ($checks as $key => $kind) {
            if (($golden[$key] ?? null) !== ($generated[$key] ?? null)) {
                $mismatches[] = $kind;
            }
        }

        if (count($mismatches) > 1 && in_array('package-stable-semantics', $mismatches, true)) {
            $mismatches = array_values(array_filter(
                $mismatches,
                static fn (string $kind): bool => $kind !== 'package-stable-semantics'
            ));
        }

        return $mismatches;
    }

    /**
     * @param array<string, mixed> $golden
     * @param array<string, mixed> $generated
     * @return array<string, mixed>
     */
    private static function caseMismatchDetails(array $golden, array $generated, int $limit): array
    {
        $partDelta = self::stringSetDelta(
            self::stringList($golden['partNames'] ?? []),
            self::stringList($generated['partNames'] ?? [])
        );
        $contentTypeDelta = self::recordSetDelta(
            self::recordList($golden['contentTypes']['records'] ?? []),
            self::recordList($generated['contentTypes']['records'] ?? []),
            'contentTypeRecordKey'
        );
        $relationshipDelta = self::recordSetDelta(
            self::recordList($golden['relationships']['records'] ?? []),
            self::recordList($generated['relationships']['records'] ?? []),
            'relationshipRecordKey'
        );
        $xmlDelta = self::xmlPartDelta(
            self::recordList($golden['xmlPartRows'] ?? []),
            self::recordList($generated['xmlPartRows'] ?? [])
        );

        return [
            'schemaVersion' => 1,
            'sampleLimit' => $limit,
            'partNameDeltas' => [
                'missingPartCount' => count($partDelta['missing']),
                'extraPartCount' => count($partDelta['extra']),
                'missingPartNames' => array_slice($partDelta['missing'], 0, $limit),
                'extraPartNames' => array_slice($partDelta['extra'], 0, $limit),
            ],
            'contentTypeDeltas' => [
                'missingRecordCount' => count($contentTypeDelta['missing']),
                'extraRecordCount' => count($contentTypeDelta['extra']),
                'missingRecords' => array_slice($contentTypeDelta['missing'], 0, $limit),
                'extraRecords' => array_slice($contentTypeDelta['extra'], 0, $limit),
            ],
            'relationshipDeltas' => [
                'missingRecordCount' => count($relationshipDelta['missing']),
                'extraRecordCount' => count($relationshipDelta['extra']),
                'missingRecords' => array_slice($relationshipDelta['missing'], 0, $limit),
                'extraRecords' => array_slice($relationshipDelta['extra'], 0, $limit),
            ],
            'xmlPartDeltas' => [
                'missingXmlPartCount' => count($xmlDelta['missing']),
                'extraXmlPartCount' => count($xmlDelta['extra']),
                'changedXmlPartCount' => count($xmlDelta['changed']),
                'missingXmlParts' => array_slice($xmlDelta['missing'], 0, $limit),
                'extraXmlParts' => array_slice($xmlDelta['extra'], 0, $limit),
                'changedXmlParts' => array_slice($xmlDelta['changed'], 0, $limit),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function rowStableSemantics(array $row): array
    {
        return is_array($row['stableSemantics'] ?? null) ? $row['stableSemantics'] : [];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }
        sort($strings, SORT_STRING);

        return array_values(array_unique($strings));
    }

    /**
     * @param list<string> $golden
     * @param list<string> $generated
     * @return array{missing:list<string>, extra:list<string>}
     */
    private static function stringSetDelta(array $golden, array $generated): array
    {
        $missing = array_values(array_diff($golden, $generated));
        $extra = array_values(array_diff($generated, $golden));
        sort($missing, SORT_STRING);
        sort($extra, SORT_STRING);

        return [
            'missing' => $missing,
            'extra' => $extra,
        ];
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    private static function recordList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $record) {
            if (is_array($record)) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $golden
     * @param list<array<string, mixed>> $generated
     * @return array{missing:list<array<string, mixed>>, extra:list<array<string, mixed>>}
     */
    private static function recordSetDelta(array $golden, array $generated, string $keyMethod): array
    {
        $goldenByKey = [];
        foreach ($golden as $record) {
            $goldenByKey[self::{$keyMethod}($record)] = $record;
        }
        $generatedByKey = [];
        foreach ($generated as $record) {
            $generatedByKey[self::{$keyMethod}($record)] = $record;
        }

        $missing = [];
        foreach (array_diff(array_keys($goldenByKey), array_keys($generatedByKey)) as $key) {
            $missing[] = $goldenByKey[$key];
        }
        $extra = [];
        foreach (array_diff(array_keys($generatedByKey), array_keys($goldenByKey)) as $key) {
            $extra[] = $generatedByKey[$key];
        }

        return [
            'missing' => $missing,
            'extra' => $extra,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function contentTypeRecordKey(array $record): string
    {
        return implode("\0", [
            (string) ($record['kind'] ?? ''),
            (string) ($record['extension'] ?? ''),
            (string) ($record['partName'] ?? ''),
            (string) ($record['contentType'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function relationshipRecordKey(array $record): string
    {
        return implode("\0", [
            (string) ($record['relationshipPartName'] ?? ''),
            (string) ($record['sourcePartName'] ?? ''),
            (string) ($record['relationshipId'] ?? ''),
            (string) ($record['relationshipType'] ?? ''),
            (string) ($record['targetMode'] ?? ''),
            (string) ($record['resolvedTarget'] ?? ''),
            (string) ($record['targetStatus'] ?? ''),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $golden
     * @param list<array<string, mixed>> $generated
     * @return array{missing:list<array<string, mixed>>, extra:list<array<string, mixed>>, changed:list<array<string, mixed>>}
     */
    private static function xmlPartDelta(array $golden, array $generated): array
    {
        $goldenByName = [];
        foreach ($golden as $row) {
            $goldenByName[(string) ($row['name'] ?? '')] = $row;
        }
        $generatedByName = [];
        foreach ($generated as $row) {
            $generatedByName[(string) ($row['name'] ?? '')] = $row;
        }

        $missing = [];
        foreach (array_diff(array_keys($goldenByName), array_keys($generatedByName)) as $name) {
            $missing[] = $goldenByName[$name];
        }
        $extra = [];
        foreach (array_diff(array_keys($generatedByName), array_keys($goldenByName)) as $name) {
            $extra[] = $generatedByName[$name];
        }
        $changed = [];
        foreach (array_intersect(array_keys($goldenByName), array_keys($generatedByName)) as $name) {
            $goldenRow = $goldenByName[$name];
            $generatedRow = $generatedByName[$name];
            if (($goldenRow['semanticSha256'] ?? null) === ($generatedRow['semanticSha256'] ?? null)) {
                continue;
            }
            $changedRow = [
                'partName' => $name,
                'goldenSemanticStatus' => (string) ($goldenRow['semanticStatus'] ?? 'unknown'),
                'generatedSemanticStatus' => (string) ($generatedRow['semanticStatus'] ?? 'unknown'),
                'goldenSemanticSha256' => (string) ($goldenRow['semanticSha256'] ?? ''),
                'generatedSemanticSha256' => (string) ($generatedRow['semanticSha256'] ?? ''),
            ];
            $goldenFeatureSha256 = (string) ($goldenRow['xmlFeatureSha256'] ?? '');
            $generatedFeatureSha256 = (string) ($generatedRow['xmlFeatureSha256'] ?? '');
            if ($goldenFeatureSha256 !== '' || $generatedFeatureSha256 !== '') {
                $changedRow['goldenXmlFeatureSha256'] = $goldenFeatureSha256;
                $changedRow['generatedXmlFeatureSha256'] = $generatedFeatureSha256;
            }
            if ($goldenFeatureSha256 !== $generatedFeatureSha256) {
                $changedRow['xmlFeatureDeltas'] = self::xmlFeatureCountDelta(
                    is_array($goldenRow['xmlFeatureCounts'] ?? null) ? $goldenRow['xmlFeatureCounts'] : [],
                    is_array($generatedRow['xmlFeatureCounts'] ?? null) ? $generatedRow['xmlFeatureCounts'] : []
                );
            }

            $changed[] = $changedRow;
        }

        return [
            'missing' => $missing,
            'extra' => $extra,
            'changed' => $changed,
        ];
    }

    /**
     * @param array<string, mixed> $golden
     * @param array<string, mixed> $generated
     * @return list<array{feature:string, goldenCount:int, generatedCount:int}>
     */
    private static function xmlFeatureCountDelta(array $golden, array $generated): array
    {
        $rows = [];
        foreach (self::XML_FEATURE_NAMES as $feature) {
            $goldenCount = (int) ($golden[$feature] ?? 0);
            $generatedCount = (int) ($generated[$feature] ?? 0);
            if ($goldenCount === $generatedCount) {
                continue;
            }

            $rows[] = [
                'feature' => $feature,
                'goldenCount' => $goldenCount,
                'generatedCount' => $generatedCount,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, mixed>> $buckets
     * @param array<string, mixed> $row
     */
    private static function addDiagnosticBucket(array &$buckets, string $key, array $row, string $fileName): void
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = array_replace($row, [
                'count' => 0,
                'fileSamples' => [],
            ]);
        }

        ++$buckets[$key]['count'];
        if (count($buckets[$key]['fileSamples']) < 5 && !in_array($fileName, $buckets[$key]['fileSamples'], true)) {
            $buckets[$key]['fileSamples'][] = $fileName;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $buckets
     * @return list<array<string, mixed>>
     */
    private static function topDiagnosticRows(array $buckets, int $limit): array
    {
        $rows = array_values($buckets);
        usort($rows, static function (array $left, array $right): int {
            $countComparison = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp(self::stableJson($left), self::stableJson($right));
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageRows(string $goldenDir): array
    {
        $rows = [];
        foreach ($this->packagePaths($goldenDir) as $path) {
            $rows[] = $this->packageRow($path);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function packagePaths(string $directory): array
    {
        $paths = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || !$entry->isFile() || strtolower($entry->getExtension()) !== 'docx') {
                continue;
            }

            $fileName = $entry->getFilename();
            if ($this->caseFilterActive() && !self::fileNameMatchesCaseFilters($fileName, $this->caseFilters)) {
                continue;
            }

            $paths[] = $entry->getPathname();
        }
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function packageFileNames(string $directory): array
    {
        $fileNames = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && strtolower($entry->getExtension()) === 'docx') {
                $fileNames[] = $entry->getFilename();
            }
        }
        sort($fileNames, SORT_STRING);

        return $fileNames;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageRow(string $path): array
    {
        $fileName = basename($path);
        $row = [
            'fileName' => $fileName,
            'file' => $this->displayPath($path),
            'expectedUpstreamGoldenReference' => 'test/docx/golden/' . $fileName,
            'status' => 'unreadable',
            'packageBytes' => 0,
            'packageSha256' => null,
            'partCount' => 0,
            'readablePartCount' => 0,
            'partNames' => [],
            'partRows' => [],
        ];

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read writer golden DOCX package: {$path}");
            }

            $package = ZipPackage::fromString($bytes);
            $partRows = $this->partRows($package);
            $partNames = array_map(
                static fn (array $partRow): string => (string) $partRow['name'],
                $partRows
            );

            return array_replace($row, [
                'status' => 'readable',
                'packageBytes' => strlen($bytes),
                'packageSha256' => hash('sha256', $bytes),
                'stableSemantics' => $this->stablePackageSemantics($package),
                'partCount' => count($partRows),
                'readablePartCount' => count(array_filter(
                    $partRows,
                    static fn (array $partRow): bool => ($partRow['status'] ?? null) === 'readable'
                )),
                'partNames' => $partNames,
                'partRows' => $partRows,
            ]);
        } catch (\Throwable $throwable) {
            return array_replace($row, [
                'status' => 'unreadable',
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partRows(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $rows = [];
        foreach ($entries as $entry) {
            $row = [
                'name' => $entry->name,
                'status' => 'unreadable',
                'isDirectory' => $entry->isDirectory(),
                'compressionMethod' => $entry->compressionMethod,
                'compressedBytes' => $entry->compressedSize,
                'declaredUncompressedBytes' => $entry->uncompressedSize,
                'crc32' => $entry->crc32Hex(),
                'sha256' => null,
                'uncompressedBytes' => 0,
            ];

            try {
                $data = $entry->isDirectory() ? '' : $package->read($entry->name);
                $rows[] = array_replace($row, [
                    'status' => 'readable',
                    'sha256' => hash('sha256', $data),
                    'uncompressedBytes' => strlen($data),
                ]);
            } catch (\Throwable $throwable) {
                $rows[] = array_replace($row, [
                    'status' => 'unreadable',
                    'errorClass' => $throwable::class,
                    'message' => self::oneLine($throwable->getMessage()),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function stablePackageSemantics(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $partNames = [];
        $xmlPartRows = [];
        $xmlFeaturePartRows = [];
        $binaryPartRows = [];
        foreach ($entries as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $partNames[] = $entry->name;
            $data = $package->read($entry->name);
            $partRow = self::stablePartSemanticRow($entry->name, $data);
            if (($partRow['semanticKind'] ?? null) === 'xml') {
                $xmlPartRows[] = self::xmlPartComparisonRow($partRow);
                $featureRow = self::xmlFeaturePartRow($partRow);
                if ($featureRow !== null) {
                    $xmlFeaturePartRows[] = $featureRow;
                }
            } elseif (($partRow['semanticKind'] ?? null) === 'binary') {
                $binaryPartRows[] = $partRow;
            }
        }

        $contentTypes = self::contentTypesSemantics($package);
        $relationships = self::relationshipsSemantics($package);
        $xmlFeatureSummary = [
            'partRows' => $xmlFeaturePartRows,
            'totals' => self::xmlFeatureTotals($xmlFeaturePartRows),
        ];
        $signature = [
            'partNames' => $partNames,
            'contentTypes' => $contentTypes,
            'relationships' => $relationships,
            'xmlParts' => $xmlPartRows,
            'xmlFeatureSummary' => $xmlFeatureSummary,
            'binaryParts' => $binaryPartRows,
        ];

        return [
            'schemaVersion' => 1,
            'partCount' => count($partNames),
            'partNames' => $partNames,
            'partNameSetSha256' => self::stableHash($partNames),
            'contentTypeStatus' => $contentTypes['status'],
            'contentTypeRecordCount' => $contentTypes['recordCount'],
            'contentTypesSha256' => self::stableHash($contentTypes),
            'relationshipPartCount' => $relationships['relationshipPartCount'],
            'relationshipRecordCount' => $relationships['relationshipRecordCount'],
            'relationshipsSha256' => self::stableHash($relationships),
            'xmlPartCount' => count($xmlPartRows),
            'xmlPartSemanticsSha256' => self::stableHash($xmlPartRows),
            'xmlFeaturePartCount' => count($xmlFeaturePartRows),
            'xmlFeatureSummarySha256' => self::stableHash($xmlFeatureSummary),
            'xmlFeatureSummary' => $xmlFeatureSummary,
            'binaryPartCount' => count($binaryPartRows),
            'binaryPartPayloadSha256' => self::stableHash($binaryPartRows),
            'packageStableSemanticsSha256' => self::stableHash($signature),
            'contentTypes' => $contentTypes,
            'relationships' => $relationships,
            'xmlPartRows' => $xmlPartRows,
            'binaryPartRows' => $binaryPartRows,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function xmlPartComparisonRow(array $row): array
    {
        $comparison = [
            'name' => (string) ($row['name'] ?? ''),
            'semanticKind' => 'xml',
            'semanticStatus' => (string) ($row['semanticStatus'] ?? 'unknown'),
            'semanticSha256' => (string) ($row['semanticSha256'] ?? ''),
        ];

        if (isset($row['message'])) {
            $comparison['message'] = (string) $row['message'];
        }
        if (is_array($row['xmlFeatureCounts'] ?? null)) {
            $comparison['xmlFeatureCounts'] = $row['xmlFeatureCounts'];
            $comparison['xmlFeatureSha256'] = (string) ($row['xmlFeatureSha256'] ?? self::stableHash($row['xmlFeatureCounts']));
        }

        return $comparison;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function xmlFeaturePartRow(array $row): ?array
    {
        $counts = is_array($row['xmlFeatureCounts'] ?? null) ? $row['xmlFeatureCounts'] : [];
        if ($counts === []) {
            return null;
        }

        return [
            'partName' => (string) ($row['name'] ?? ''),
            'featureCounts' => $counts,
            'featureSha256' => (string) ($row['xmlFeatureSha256'] ?? self::stableHash($counts)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stablePartSemanticRow(string $name, string $data): array
    {
        $kind = self::stablePartKind($name);
        $row = [
            'name' => $name,
            'semanticKind' => $kind,
            'uncompressedBytes' => strlen($data),
            'rawSha256' => hash('sha256', $data),
        ];

        if ($kind !== 'xml') {
            return $row;
        }

        try {
            $dom = self::loadXmlDocument($data, $name);
            if (!$dom->documentElement instanceof \DOMElement) {
                throw new \RuntimeException("XML part has no document element: {$name}");
            }

            $xmlSemantics = self::stableXmlNodeSemantics($name, $dom->documentElement);
            $xmlFeatureCounts = self::xmlFeatureCounts($dom);

            return array_replace($row, [
                'semanticStatus' => 'parsed-xml',
                'semanticSha256' => self::stableHash($xmlSemantics),
                'xmlFeatureCounts' => $xmlFeatureCounts,
                'xmlFeatureSha256' => self::stableHash($xmlFeatureCounts),
            ]);
        } catch (\Throwable $throwable) {
            return array_replace($row, [
                'semanticStatus' => 'raw-xml-parse-failed',
                'semanticSha256' => hash('sha256', $data),
                'message' => self::oneLine($throwable->getMessage()),
            ]);
        }
    }

    private static function stablePartKind(string $name): string
    {
        if ($name === '[Content_Types].xml') {
            return 'content-types';
        }
        if (OpcRelationships::isRelationshipPartName($name)) {
            return 'relationships';
        }

        $lower = strtolower($name);
        if (str_ends_with($lower, '.xml') || str_ends_with($lower, '.vml')) {
            return 'xml';
        }

        return 'binary';
    }

    /**
     * @param list<array<string, mixed>> $partRows
     * @return array<string, int>
     */
    private static function xmlFeatureTotals(array $partRows): array
    {
        $totals = self::emptyXmlFeatureCounts();
        foreach ($partRows as $row) {
            $counts = is_array($row['featureCounts'] ?? null) ? $row['featureCounts'] : [];
            foreach ($counts as $feature => $count) {
                if (!isset($totals[$feature])) {
                    continue;
                }

                $totals[$feature] += (int) $count;
            }
        }

        return self::compactXmlFeatureCounts($totals);
    }

    /**
     * @return array<string, int>
     */
    private static function xmlFeatureCounts(\DOMDocument $dom): array
    {
        if (!$dom->documentElement instanceof \DOMElement) {
            return [];
        }

        $counts = self::emptyXmlFeatureCounts();
        self::accumulateXmlFeatureCounts($dom->documentElement, $counts);

        return self::compactXmlFeatureCounts($counts);
    }

    /**
     * @return array<string, int>
     */
    private static function emptyXmlFeatureCounts(): array
    {
        return array_fill_keys(self::XML_FEATURE_NAMES, 0);
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private static function compactXmlFeatureCounts(array $counts): array
    {
        $compact = [];
        foreach (self::XML_FEATURE_NAMES as $feature) {
            $count = (int) ($counts[$feature] ?? 0);
            if ($count > 0) {
                $compact[$feature] = $count;
            }
        }

        return $compact;
    }

    /**
     * @param array<string, int> $counts
     */
    private static function accumulateXmlFeatureCounts(\DOMNode $node, array &$counts): void
    {
        if ($node instanceof \DOMElement) {
            self::countXmlElementFeature($node, $counts);
        }

        foreach ($node->childNodes as $child) {
            self::accumulateXmlFeatureCounts($child, $counts);
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private static function countXmlElementFeature(\DOMElement $element, array &$counts): void
    {
        $namespace = (string) ($element->namespaceURI ?? '');
        $name = (string) ($element->localName ?: $element->nodeName);

        if ($namespace === self::NS_WORDPROCESSINGML) {
            self::countWordprocessingFeature($element, $name, $counts);
            return;
        }

        if ($namespace === self::NS_WORDPROCESSING_DRAWING) {
            if ($name === 'inline') {
                ++$counts['drawingInline'];
            } elseif ($name === 'anchor') {
                ++$counts['drawingAnchor'];
            }
            return;
        }

        if ($namespace === self::NS_DRAWINGML) {
            if ($name === 'graphic') {
                ++$counts['drawingGraphic'];
            } elseif ($name === 'blip') {
                ++$counts['drawingBlip'];
                if (self::xmlAttributeValue($element, self::NS_OFFICE_RELATIONSHIPS, 'embed') !== null) {
                    ++$counts['drawingRelationshipEmbed'];
                }
                if (self::xmlAttributeValue($element, self::NS_OFFICE_RELATIONSHIPS, 'link') !== null) {
                    ++$counts['drawingRelationshipLink'];
                }
            }
            return;
        }

        if ($namespace === self::NS_DRAWINGML_PICTURE && $name === 'pic') {
            ++$counts['drawingPicture'];
            return;
        }

        if ($namespace === self::NS_VML) {
            if ($name === 'shape') {
                ++$counts['vmlShape'];
            } elseif ($name === 'textbox') {
                ++$counts['vmlTextBox'];
            } elseif ($name === 'imagedata') {
                ++$counts['vmlImageData'];
            }
            return;
        }

        if ($namespace === self::NS_WORDPROCESSING_SHAPE) {
            if ($name === 'wsp') {
                ++$counts['wordprocessingShape'];
            } elseif ($name === 'txbx') {
                ++$counts['wordprocessingShapeTextBox'];
            }
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private static function countWordprocessingFeature(\DOMElement $element, string $name, array &$counts): void
    {
        switch ($name) {
            case 'p':
                ++$counts['wordParagraph'];
                break;
            case 'r':
                ++$counts['wordRun'];
                break;
            case 'tbl':
                ++$counts['wordTable'];
                break;
            case 'tr':
                ++$counts['wordTableRow'];
                break;
            case 'tc':
                ++$counts['wordTableCell'];
                break;
            case 'tblGrid':
                ++$counts['wordTableGrid'];
                break;
            case 'gridSpan':
                ++$counts['wordGridSpan'];
                break;
            case 'vMerge':
                ++$counts['wordVerticalMerge'];
                break;
            case 'tblCaption':
                ++$counts['wordTableCaption'];
                break;
            case 'drawing':
                ++$counts['wordDrawing'];
                break;
            case 'pict':
                ++$counts['wordPict'];
                break;
            case 'sdt':
                ++$counts['wordSdt'];
                break;
            case 'sdtPr':
                ++$counts['wordSdtPr'];
                break;
            case 'sdtContent':
                ++$counts['wordSdtContent'];
                break;
            case 'txbxContent':
                ++$counts['wordTextBoxContent'];
                break;
            case 'pStyle':
                self::countCaptionStyleFeature(
                    self::xmlAttributeValue($element, self::NS_WORDPROCESSINGML, 'val'),
                    $counts,
                    'wordParagraphCaptionStyle',
                    'wordParagraphTableCaptionStyle'
                );
                break;
            case 'style':
                self::countCaptionStyleFeature(
                    self::xmlAttributeValue($element, self::NS_WORDPROCESSINGML, 'styleId'),
                    $counts,
                    'wordStyleCaption',
                    'wordStyleTableCaption'
                );
                break;
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private static function countCaptionStyleFeature(
        ?string $styleId,
        array &$counts,
        string $captionFeature,
        string $tableCaptionFeature
    ): void {
        $normalized = self::normalizedStyleId($styleId);
        if ($normalized === 'caption') {
            ++$counts[$captionFeature];
        } elseif ($normalized === 'tablecaption') {
            ++$counts[$tableCaptionFeature];
        }
    }

    private static function normalizedStyleId(?string $styleId): string
    {
        if ($styleId === null) {
            return '';
        }

        return strtolower(str_replace([' ', '-', '_'], '', trim($styleId)));
    }

    private static function xmlAttributeValue(\DOMElement $element, string $namespace, string $localName): ?string
    {
        if (!$element->hasAttributeNS($namespace, $localName)) {
            return null;
        }

        return $element->getAttributeNS($namespace, $localName);
    }

    /**
     * @return array<string, mixed>
     */
    private static function contentTypesSemantics(ZipPackage $package): array
    {
        if (!in_array('[Content_Types].xml', $package->names(), true)) {
            return [
                'status' => 'missing',
                'recordCount' => 0,
                'defaultCount' => 0,
                'overrideCount' => 0,
                'records' => [],
            ];
        }

        $xml = $package->read('[Content_Types].xml');
        try {
            $contentTypes = OpcContentTypes::fromXml($xml);
            $records = [];
            $defaults = $contentTypes->defaults();
            ksort($defaults, SORT_STRING);
            foreach ($defaults as $extension => $contentType) {
                $records[] = [
                    'kind' => 'default',
                    'extension' => strtolower((string) $extension),
                    'contentType' => (string) $contentType,
                ];
            }

            $overrides = $contentTypes->overrides();
            ksort($overrides, SORT_STRING);
            foreach ($overrides as $partName => $contentType) {
                $records[] = [
                    'kind' => 'override',
                    'partName' => OpcPackagePath::canonicalPartName($partName),
                    'contentType' => (string) $contentType,
                ];
            }

            return [
                'status' => 'readable',
                'recordCount' => count($records),
                'defaultCount' => count($defaults),
                'overrideCount' => count($overrides),
                'records' => $records,
            ];
        } catch (\Throwable $throwable) {
            return [
                'status' => 'unreadable',
                'recordCount' => 0,
                'defaultCount' => 0,
                'overrideCount' => 0,
                'rawSha256' => hash('sha256', $xml),
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
                'records' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function relationshipsSemantics(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $partRows = [];
        $records = [];
        foreach ($entries as $entry) {
            if ($entry->isDirectory() || !OpcRelationships::isRelationshipPartName($entry->name)) {
                continue;
            }

            $xml = $package->read($entry->name);
            try {
                $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($entry->name);
                $relationships = OpcRelationships::fromXml($xml, $sourcePartName);
                $partRows[] = [
                    'relationshipPartName' => OpcPackagePath::canonicalPartName($entry->name),
                    'sourcePartName' => $sourcePartName,
                    'status' => 'readable',
                    'relationshipCount' => count($relationships->all()),
                ];

                foreach ($relationships->all() as $relationship) {
                    try {
                        $resolvedTarget = $relationships->resolveTarget($relationship);
                        $targetStatus = 'resolved';
                    } catch (\Throwable $throwable) {
                        $resolvedTarget = $relationship->target;
                        $targetStatus = 'unresolved:' . self::oneLine($throwable->getMessage());
                    }

                    $records[] = [
                        'relationshipPartName' => OpcPackagePath::canonicalPartName($entry->name),
                        'sourcePartName' => $sourcePartName,
                        'relationshipId' => $relationship->id,
                        'relationshipType' => $relationship->type,
                        'targetMode' => $relationship->targetMode,
                        'resolvedTarget' => $resolvedTarget,
                        'targetStatus' => $targetStatus,
                    ];
                }
            } catch (\Throwable $throwable) {
                $partRows[] = [
                    'relationshipPartName' => $entry->name,
                    'sourcePartName' => null,
                    'status' => 'unreadable',
                    'rawSha256' => hash('sha256', $xml),
                    'errorClass' => $throwable::class,
                    'message' => self::oneLine($throwable->getMessage()),
                    'relationshipCount' => 0,
                ];
            }
        }

        usort(
            $records,
            static fn (array $left, array $right): int => strcmp(
                implode("\0", [
                    (string) ($left['relationshipPartName'] ?? ''),
                    (string) ($left['sourcePartName'] ?? ''),
                    (string) ($left['relationshipId'] ?? ''),
                    (string) ($left['relationshipType'] ?? ''),
                    (string) ($left['targetMode'] ?? ''),
                    (string) ($left['resolvedTarget'] ?? ''),
                ]),
                implode("\0", [
                    (string) ($right['relationshipPartName'] ?? ''),
                    (string) ($right['sourcePartName'] ?? ''),
                    (string) ($right['relationshipId'] ?? ''),
                    (string) ($right['relationshipType'] ?? ''),
                    (string) ($right['targetMode'] ?? ''),
                    (string) ($right['resolvedTarget'] ?? ''),
                ])
            )
        );

        return [
            'relationshipPartCount' => count($partRows),
            'relationshipRecordCount' => count($records),
            'relationshipParts' => $partRows,
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xmlSemantics(string $xml, string $label): array
    {
        $dom = self::loadXmlDocument($xml, $label);
        if (!$dom->documentElement instanceof \DOMElement) {
            throw new \RuntimeException("XML part has no document element: {$label}");
        }

        return self::stableXmlNodeSemantics($label, $dom->documentElement);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stableXmlNodeSemantics(string $partName, \DOMElement $root): array
    {
        $semantics = self::xmlNodeSemantics($root);

        return self::normalizeStableXmlSemantics($partName, $semantics);
    }

    /**
     * @param array<string, mixed> $semantics
     * @return array<string, mixed>
     */
    private static function normalizeStableXmlSemantics(string $partName, array $semantics): array
    {
        if ($partName !== 'docProps/core.xml') {
            return $semantics;
        }

        return self::normalizeCorePropertiesTimestampSemantics($semantics);
    }

    /**
     * @param array<string, mixed> $semantics
     * @return array<string, mixed>
     */
    private static function normalizeCorePropertiesTimestampSemantics(array $semantics): array
    {
        if (($semantics['kind'] ?? null) !== 'element') {
            return $semantics;
        }

        $namespace = (string) ($semantics['namespace'] ?? '');
        $name = (string) ($semantics['name'] ?? '');
        if (
            $namespace === self::NS_DCTERMS
            && in_array($name, ['created', 'modified'], true)
            && is_array($semantics['children'] ?? null)
        ) {
            $semantics['children'] = [
                [
                    'kind' => 'text',
                    'value' => '{core-property-timestamp}',
                ],
            ];

            return $semantics;
        }

        if (is_array($semantics['children'] ?? null)) {
            $children = [];
            foreach ($semantics['children'] as $child) {
                $children[] = is_array($child)
                    ? self::normalizeCorePropertiesTimestampSemantics($child)
                    : $child;
            }
            $semantics['children'] = $children;
        }

        return $semantics;
    }

    private static function loadXmlDocument(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->resolveExternals = false;
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
            if ($loaded !== true) {
                throw new \RuntimeException("Unable to parse XML part {$label}: " . self::libxmlErrorSummary());
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function xmlNodeSemantics(\DOMNode $node): array
    {
        if ($node instanceof \DOMElement) {
            $attributes = [];
            foreach ($node->attributes as $attribute) {
                if (!$attribute instanceof \DOMAttr || $attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
                    continue;
                }

                $attributes[] = [
                    'namespace' => (string) ($attribute->namespaceURI ?? ''),
                    'name' => (string) ($attribute->localName ?: $attribute->name),
                    'value' => $attribute->value,
                ];
            }
            usort(
                $attributes,
                static fn (array $left, array $right): int => strcmp(
                    ($left['namespace'] ?? '') . "\0" . ($left['name'] ?? '') . "\0" . ($left['value'] ?? ''),
                    ($right['namespace'] ?? '') . "\0" . ($right['name'] ?? '') . "\0" . ($right['value'] ?? '')
                )
            );

            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMText && self::isFormattingWhitespace($child)) {
                    continue;
                }
                if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    continue;
                }

                $children[] = self::xmlNodeSemantics($child);
            }

            return [
                'kind' => 'element',
                'namespace' => (string) ($node->namespaceURI ?? ''),
                'name' => (string) ($node->localName ?: $node->nodeName),
                'attributes' => $attributes,
                'children' => $children,
            ];
        }

        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return [
                'kind' => 'text',
                'value' => $node->nodeValue ?? '',
            ];
        }

        return [
            'kind' => $node->nodeType,
            'name' => $node->nodeName,
            'value' => $node->nodeValue,
        ];
    }

    private static function isFormattingWhitespace(\DOMText $text): bool
    {
        if (trim($text->data) !== '') {
            return false;
        }

        $parent = $text->parentNode;
        if (!$parent instanceof \DOMElement) {
            return false;
        }

        foreach ($parent->childNodes as $sibling) {
            if ($sibling instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    private static function libxmlErrorSummary(): string
    {
        $errors = libxml_get_errors();
        if ($errors === []) {
            return 'unknown XML parse error';
        }

        $error = $errors[0];

        return self::oneLine(trim($error->message) . ' at line ' . $error->line . ', column ' . $error->column);
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', self::stableJson($value));
    }

    private static function stableJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function absoluteDocxDirectory(): string
    {
        if (str_starts_with($this->docxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->docxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->docxDirectory);
    }

    private function absoluteGoldenDirectory(): string
    {
        return $this->absoluteDocxDirectory() . DIRECTORY_SEPARATOR . 'golden';
    }

    private function absoluteGeneratedDirectory(): ?string
    {
        if ($this->generatedDocxDirectory === null) {
            return null;
        }

        if (str_starts_with($this->generatedDocxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->generatedDocxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->generatedDocxDirectory);
    }

    private function absoluteGenerationOutputDirectory(): ?string
    {
        if ($this->generationOutputDirectory === null) {
            return null;
        }

        if (str_starts_with($this->generationOutputDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->generationOutputDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->generationOutputDirectory);
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    private static function boolText(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }

    private static function oneLine(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message));
        if (!is_string($message)) {
            return '';
        }

        return strlen($message) > 240 ? substr($message, 0, 237) . '...' : $message;
    }
}
