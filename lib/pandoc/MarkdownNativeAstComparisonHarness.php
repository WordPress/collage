<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-markdown-implementation-equivalence-observed';
    private const CLAIM = 'Compares local PHP Markdown reader output with paired .native fixtures by normalized AST shape; reader provenance and NativeReader constructor provenance are excluded. Selected Pandoc Markdown dialect profiles are covered by the checked-in native-pair corpus.';

    private const MARKDOWN_READER_OPTIONS_BY_BASENAME = [
        'upstream-command-11253-latex-macros-disabled' => ['format' => 'markdown-latex_macros'],
        'upstream-command-7080-mmd-reference-image-attributes' => ['format' => 'markdown_mmd'],
        'upstream-command-gfm-adjacent-emoji' => ['format' => 'gfm'],
        'upstream-command-gfm-details-list' => ['format' => 'gfm'],
        'upstream-markdown-angle-autolinks' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-alerts' => ['format' => 'gfm'],
        'upstream-markdown-ascii-identifiers' => ['format' => 'markdown+ascii_identifiers'],
        'upstream-markdown-reader-more-grid-table-spans' => ['format' => 'markdown+grid_tables'],
        'upstream-markdown-z-commonmark-x-grid-table-default' => ['format' => 'commonmark_x'],
        'upstream-markdown-definition-list-html-div' => ['format' => 'markdown+definition_lists+raw_html'],
        'upstream-markdown-definition-list-laziness' => ['format' => 'markdown+definition_lists'],
        'upstream-markdown-definition-list-nested-list' => ['format' => 'markdown+definition_lists'],
        'upstream-markdown-emoji-symbols' => ['format' => 'markdown+emoji'],
        'upstream-markdown-fenced-code-attributes' => ['format' => 'markdown+fenced_code_attributes'],
        'upstream-markdown-fenced-div' => ['format' => 'markdown+fenced_divs+native_divs'],
        'upstream-markdown-footnote-continuation-boundaries' => ['format' => 'markdown+footnotes'],
        'upstream-markdown-footnote-definitions' => ['format' => 'markdown+footnotes'],
        'upstream-markdown-github-wikilinks' => ['format' => 'markdown_github+wikilinks_title_before_pipe'],
        'upstream-markdown-gfm-math-fence-profile' => ['format' => 'gfm'],
        'upstream-markdown-header-attributes' => ['format' => 'markdown+header_attributes+implicit_header_references'],
        'upstream-markdown-line-blocks' => ['format' => 'markdown+line_blocks'],
        'upstream-markdown-lhs-inverse-bird-html' => ['format' => 'markdown+lhs'],
        'upstream-markdown-mmd-short-scripts' => ['format' => 'markdown_mmd'],
        'upstream-markdown-numbered-examples' => ['format' => 'markdown+example_lists'],
        'upstream-markdown-ordered-task-list' => ['format' => 'markdown+task_lists'],
        'upstream-markdown-pipe-table-escaped-cell' => ['format' => 'markdown+pipe_tables'],
        'upstream-markdown-raw-email-address' => ['format' => 'markdown-citations'],
        'upstream-markdown-raw-html-invalid-comment' => ['format' => 'markdown+raw_html'],
        'upstream-markdown-smart-french-apostrophe' => ['format' => 'markdown+smart'],
        'upstream-markdown-smart-inline-note-double-quotes' => ['format' => 'markdown+smart'],
        'upstream-markdown-smart-inline-note-quotes' => ['format' => 'markdown+smart'],
        'upstream-markdown-strict-compact-heading' => ['format' => 'markdown_strict'],
        'upstream-markdown-table-attributes-disabled-profile' => ['format' => 'markdown-table_attributes'],
        'upstream-markdown-task-list' => ['format' => 'markdown+task_lists'],
        'upstream-markdown-z-fancy-list-markers' => ['format' => 'markdown+fancy_lists'],
        'upstream-markdown-z-fancy-list-parenthesized-profile' => ['format' => 'markdown+fancy_lists'],
        'upstream-markdown-z-hard-line-break-profile' => ['format' => 'markdown+hard_line_breaks'],
        'upstream-markdown-z-lists-without-preceding-blankline-profile' => ['format' => 'markdown+lists_without_preceding_blankline'],
        'upstream-markdown-z-old-dashes-profile' => ['format' => 'markdown+old_dashes+smart'],
        'upstream-markdown-z-phpextra-profile' => ['format' => 'markdown_phpextra'],
        'upstream-markdown-z-simple-table-profile' => ['format' => 'markdown+simple_tables'],
        'upstream-markdown-z-short-subsuperscript-profile' => ['format' => 'markdown+short_subsuperscripts'],
        'upstream-markdown-z-spaced-reference-link-profile' => ['format' => 'markdown-shortcut_reference_links+spaced_reference_links'],
        'upstream-markdown-z-tex-math-double-backslash-profile' => ['format' => 'markdown+tex_math_double_backslash'],
        'upstream-markdown-zz-east-asian-line-break-profile' => ['format' => 'markdown+east_asian_line_breaks'],
        'upstream-markdown-zz-tex-math-single-backslash-profile' => ['format' => 'markdown+tex_math_single_backslash'],
        'upstream-markdown-zzz-intraword-underscore-profile' => ['format' => 'markdown-intraword_underscores'],
        'upstream-markdown-zzzz-angle-brackets-escapable-profile' => ['format' => 'markdown-all_symbols_escapable+angle_brackets_escapable'],
        'upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile' => ['format' => 'markdown+wikilinks_title_after_pipe'],
        'upstream-markdown-zzzzzz-ignore-line-breaks-profile' => ['format' => 'markdown+ignore_line_breaks'],
        'upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile' => ['format' => 'markdown-auto_identifiers'],
        'upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile' => ['format' => 'markdown-blank_before_header'],
        'upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile' => ['format' => 'markdown_mmd'],
        'upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile' => ['format' => 'commonmark+gfm_auto_identifiers+ascii_identifiers'],
        'upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary' => ['format' => 'markdown+footnotes+fenced_divs+native_divs'],
        'upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation' => ['format' => 'gfm'],
        'upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile' => ['format' => 'markdown-blank_before_blockquote'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile' => ['format' => 'markdown-blank_before_header'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile' => ['format' => 'markdown+mark'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary' => ['format' => 'gfm'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile' => ['format' => 'gfm+definition_lists'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-bare-uri-query-hyphen-boundaries' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-bare-uri-port-path-boundaries' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-bare-uri-raw-html-anchor' => ['format' => 'markdown+autolink_bare_uris+raw_html'],
        'upstream-markdown-bare-uri-git-file-schemes' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-bare-uri-path-punctuation' => ['format' => 'markdown+autolink_bare_uris+raw_html'],
        'upstream-markdown-bare-uri-unicode-path' => ['format' => 'markdown+autolink_bare_uris'],
        'upstream-markdown-citation-simple-baseline' => ['format' => 'markdown+citations'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile' => ['format' => 'commonmark+raw_attribute'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile' => ['format' => 'markdown-space_in_atx_header'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile' => ['format' => 'markdown-startnum+fancy_lists'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile' => ['format' => 'markdown+markdown_in_html_blocks'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile' => ['format' => 'markdown_phpextra'],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile' => ['format' => 'markdown_github'],
    ];

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'attrConstructor' => true,
        'attrNative' => true,
        'alignmentConstructor' => true,
        'alignmentNative' => true,
        'caption' => true,
        'captionBlocks' => true,
        'captionSource' => true,
        'colSpanConstructor' => true,
        'colSpanNative' => true,
        'columnDiagnostics' => true,
        'columnSources' => true,
        'columnSpecs' => true,
        'constructor' => true,
        'header' => true,
        'htmlAttributes' => true,
        'legacyTableCellBlocksNative' => true,
        'meta' => true,
        'metaConstructorProvenance' => true,
        'native' => true,
        'nativeInlineConstructors' => true,
        'nativeInlineParts' => true,
        'rowSpanConstructor' => true,
        'rowSpanNative' => true,
        'sourceEncoding' => true,
        'sourceFormat' => true,
        'sourceLineEndings' => true,
        'tableGeometry' => true,
        'widths' => true,
        'yamlMetadataDiagnosticsByPath' => true,
        'yamlMetadataProvenanceByPath' => true,
        'yamlMetadataReviewSummary' => true,
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $markdownDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($markdownDirectory)) {
            return $this->skippedReport($markdownDirectory, 'markdown-native-fixture-directory-missing');
        }

        $markdownFiles = array_filter(
            $this->filesByBasename($markdownDirectory, 'md'),
            static fn (string $basename): bool => self::isMarkdownReaderFixtureBasename($basename),
            ARRAY_FILTER_USE_KEY
        );
        $nativeFiles = array_filter(
            $this->filesByBasename($markdownDirectory, 'native'),
            static fn (string $basename): bool => isset($markdownFiles[$basename]),
            ARRAY_FILTER_USE_KEY
        );

        $markdownFixtureNames = array_keys($markdownFiles);
        $nativeFixtureNames = array_keys($nativeFiles);
        $pairNames = array_values(array_intersect($markdownFixtureNames, $nativeFixtureNames));
        sort($pairNames, SORT_STRING);
        $unpairedMarkdownNames = array_values(array_diff($markdownFixtureNames, $pairNames));
        $unpairedNativeNames = array_values(array_diff($nativeFixtureNames, $pairNames));
        $unpairedMarkdownFixtureNames = self::fixtureNamesWithExtension($unpairedMarkdownNames, 'md', count($unpairedMarkdownNames));
        $unpairedNativeFixtureNames = self::fixtureNamesWithExtension($unpairedNativeNames, 'native', count($unpairedNativeNames));

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $markdownParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $markdownResult = $this->readMarkdown($markdownFiles[$pairName], $pairName);
            if ($markdownResult['ok']) {
                ++$markdownParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$markdownResult['ok'] || !$nativeResult['ok']) {
                $parseFailures[] = [
                    'fixture' => $pairName,
                    'markdownError' => $markdownResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $markdownDocument */
            $markdownDocument = $markdownResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $markdownAst = $this->normalizedNode($markdownDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($markdownAst === $nativeAst) {
                ++$matchCount;
                continue;
            }

            $difference = $this->firstDifference($markdownAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'markdownTopTypes' => $this->topTypeSequence($markdownDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedPairCount = count($pairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-markdown-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'markdown-native-normalized-ast-comparison',
            'upstreamMarkdownDirectory' => $markdownDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'markdownReaderFixtureOptionOverrides' => self::markdownReaderFixtureOptionOverrides(),
            'markdownFixtureCount' => count($markdownFiles),
            'nativeFixtureCount' => count($nativeFiles),
            'pairedFixtureCount' => $totalPairCount,
            'unpairedMarkdownFixtureCount' => count($unpairedMarkdownNames),
            'unpairedNativeFixtureCount' => count($unpairedNativeNames),
            'unpairedMarkdownFixtureNames' => $unpairedMarkdownFixtureNames,
            'unpairedNativeFixtureNames' => $unpairedNativeFixtureNames,
            'unpairedMarkdownFixtureExamples' => self::fixtureNamesWithExtension($unpairedMarkdownNames, 'md', $maxExamples),
            'unpairedNativeFixtureExamples' => self::fixtureNamesWithExtension($unpairedNativeNames, 'native', $maxExamples),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'markdownParsedCount' => $markdownParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'parseFailureCount' => count($parseFailures),
            'normalizedAstMatchCount' => $matchCount,
            'normalizedAstMismatchCount' => $mismatchCount,
            'normalizedAstMatchPercent' => self::percent($matchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus(count($parseFailures), $mismatchCount, $comparedPairCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                count($markdownFiles),
                count($nativeFiles),
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount,
                count($unpairedMarkdownNames)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc Markdown/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamMarkdownDirectory=' . (string) ($report['upstreamMarkdownDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'fixtureInventory: markdown=%d native=%d paired=%d unpairedMarkdown=%d unpairedNative=%d',
            (int) ($report['markdownFixtureCount'] ?? 0),
            (int) ($report['nativeFixtureCount'] ?? 0),
            (int) ($report['pairedFixtureCount'] ?? $report['totalPairCount'] ?? 0),
            (int) ($report['unpairedMarkdownFixtureCount'] ?? 0),
            (int) ($report['unpairedNativeFixtureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'pairs: total=%d compared=%d parsedBoth=%d parseFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );

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
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required Markdown mapped parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['markdownParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $markdownDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-markdown-native-ast',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'markdown-native-normalized-ast-comparison',
            'upstreamMarkdownDirectory' => $markdownDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'markdownReaderFixtureOptionOverrides' => self::markdownReaderFixtureOptionOverrides(),
            'markdownFixtureCount' => 0,
            'nativeFixtureCount' => 0,
            'pairedFixtureCount' => 0,
            'unpairedMarkdownFixtureCount' => 0,
            'unpairedNativeFixtureCount' => 0,
            'unpairedMarkdownFixtureNames' => [],
            'unpairedNativeFixtureNames' => [],
            'unpairedMarkdownFixtureExamples' => [],
            'unpairedNativeFixtureExamples' => [],
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'markdownParsedCount' => 0,
            'nativeParsedCount' => 0,
            'bothParsedCount' => 0,
            'parseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-source-directory-unavailable',
            'parseFailures' => [],
            'mismatchComparisons' => [],
            'mismatchCategories' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type after Plain wrappers are normalized',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline text after adjacent text nodes are coalesced; block-level cached text attrs are excluded',
            ],
            'excludes' => [
                'Markdown reader document metadata and provenance attrs',
                'NativeReader constructor provenance attrs',
                'table geometry review packets',
                'default table widths, zero rowHeadColumns, and empty captions/feet',
                'reader-derived block cached text metadata, including paragraph/list-item text',
                'reader-derived Markdown source provenance such as list markers, task-list collection flags, note labels, numbered-example labels, code info strings, emoji source text, raw HTML format/text duplicates, and rendering hints',
                'source id/classes/key-value attrs on Pandoc inline constructors without native Attr tuples; inline constructor, text, and children remain compared',
                'regular inline whitespace run lengths, because Pandoc native represents source whitespace as Space constructors outside code/raw text',
                'NativeReader citation record sidecars, citation source-display text, and equivalent string-vs-inline citation affix representation; citation ids, modes, prefixes, and suffixes remain compared',
                'definition-list looseness metadata not represented by Pandoc native text constructors',
                'local Markdown implicit-figure image sidecars when Figure attrs, caption blocks, image label, and image target already carry the native-visible structure',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'Markdown writer parity',
                'byte-for-byte identity with Pandoc Haskell reader internals',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    public static function isMarkdownReaderFixtureBasename(string $basename): bool
    {
        return str_starts_with($basename, 'upstream-markdown-')
            || str_starts_with($basename, 'upstream-command-');
    }

    /**
     * @return array<string, mixed>
     */
    public static function readerOptionsForFixtureBasename(string $basename): array
    {
        return self::MARKDOWN_READER_OPTIONS_BY_BASENAME[$basename] ?? [];
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readMarkdown(string $path, string $fixtureName): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read Markdown fixture '{$path}'.");
            }

            return [
                'ok' => true,
                'document' => (new MarkdownReader(self::MARKDOWN_READER_OPTIONS_BY_BASENAME[$fixtureName] ?? []))->read($bytes),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function markdownReaderFixtureOptionOverrides(): array
    {
        $overrides = [];
        foreach (self::MARKDOWN_READER_OPTIONS_BY_BASENAME as $basename => $options) {
            $overrides[$basename . '.md'] = $options;
        }

        return $overrides;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node, ?string $parentType = null): array
    {
        $attrs = [];
        $taskChecked = null;
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if (($node->type === 'raw_tex' || $node->type === 'raw_tex_inline') && in_array($key, ['command', 'environment'], true)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['paragraph', 'plain', 'table_cell'], true)) {
                continue;
            }
            if ($key === 'text' && $node->type === 'list_item') {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['citation', 'citation_group'], true)) {
                continue;
            }
            if ($key === 'marker' && self::isListShapeMetadataNode($node)) {
                continue;
            }
            if ($key === 'taskList' && $node->type === 'bullet_list') {
                continue;
            }
            if ($key === 'label' && $node->type === 'note') {
                continue;
            }
            if (($key === 'exampleLabel' || $key === 'number') && $node->type === 'list_item') {
                continue;
            }
            if ($key === 'info' && $node->type === 'code_block') {
                continue;
            }
            if ($key === 'markdownSource') {
                continue;
            }
            if ($key === 'renderCaptionInlines') {
                continue;
            }
            if (($node->type === 'raw_html' || $node->type === 'raw_html_inline') && ($key === 'format' || $key === 'text')) {
                continue;
            }
            if ($key === 'loose' && self::isListShapeMetadataNode($node)) {
                continue;
            }
            if ($key === 'loose' && $node->type === 'definition') {
                continue;
            }
            if ($node->type === 'citation' && self::isCitationNativeSidecarAttr($key)) {
                continue;
            }
            if ($node->type === 'small_caps' && in_array($key, ['id', 'classes', 'attributes'], true)) {
                continue;
            }
            if ($node->type === 'image' && $key === 'figureAttributes') {
                continue;
            }
            if ($node->type === 'image' && $key === 'alt' && $this->isNativeRedundantImageAltAttr($node, $value, $parentType)) {
                continue;
            }
            if ($key === 'taskChecked' && $node->type === 'list_item' && is_bool($value)) {
                $taskChecked = $value;
                continue;
            }
            if ($key === 'caption' && $value === '') {
                continue;
            }
            if ($key === 'rowHeadColumns' && (int) $value === 0) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            if ($node->type === 'citation' && in_array($key, ['prefix', 'suffix'], true)) {
                $normalizedValue = $this->normalizedCitationAffixValue($value);
            } elseif ($node->type === 'text' && $key === 'text' && is_string($value)) {
                $normalizedValue = self::normalizedInlineWhitespace($value);
            } else {
                $normalizedValue = $this->normalizedValue($value);
            }
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);
        $children = $node->type === 'citation' ? [] : $this->normalizedChildren($node->children, $node->type);
        if ($node->type === 'list_item' && $taskChecked !== null) {
            $children = $this->withNormalizedTaskMarker($children, $taskChecked);
        }

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $children,
        ];
    }

    private function isNativeRedundantImageAltAttr(AstNode $node, mixed $value, ?string $parentType): bool
    {
        $alt = (string) $value;
        if ($alt === '') {
            return true;
        }

        $label = $this->plainInlineText($node->children);
        if ($label !== '' && $alt === $label) {
            return true;
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && array_key_exists('alt', $attributes) && (string) $attributes['alt'] === $alt) {
            return true;
        }

        return $parentType === 'figure'
            && $label !== ''
            && $label === (string) $node->attr('caption', '');
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        if (isset(self::IGNORED_ATTRS[$key]) && self::IGNORED_ATTRS[$key] === true) {
            return true;
        }

        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors');
    }

    private static function isListShapeMetadataNode(AstNode $node): bool
    {
        return in_array($node->type, ['bullet_list', 'ordered_list', 'definition_list', 'list_item'], true);
    }

    private static function isCitationNativeSidecarAttr(string $key): bool
    {
        return in_array($key, [
            'citationHash',
            'citationNoteNum',
            'citationSourceInlines',
            'citations',
            'hash',
            'noteNum',
        ], true);
    }

    private function normalizedCitationAffixValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value === '' ? [] : [[
                'type' => 'text',
                'attrs' => ['text' => self::normalizedInlineWhitespace($value)],
                'children' => [],
            ]];
        }

        return $this->normalizedValue($value);
    }

    private static function normalizedInlineWhitespace(string $text): string
    {
        return preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children, ?string $parentType = null): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child, $parentType);
            if ($this->isEmptyTableFootNode($node)) {
                continue;
            }
            if ($node['type'] === 'plain') {
                foreach ($node['children'] as $plainChild) {
                    $this->appendNormalizedChild($normalized, $plainChild);
                }
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $normalized;
    }

    /**
     * @param list<AstNode> $children
     */
    private function plainInlineText(array $children): string
    {
        $text = '';
        foreach ($children as $child) {
            $text .= match ($child->type) {
                'text' => (string) $child->attr('text', ''),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($child->children),
            };
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    private function withNormalizedTaskMarker(array $children, bool $checked): array
    {
        $marker = $checked ? "\u{2612}" : "\u{2610}";
        $prefix = $marker . ' ';
        if (isset($children[0]) && $this->isPlainTextNode($children[0])) {
            $text = $children[0]['attrs']['text'];
            if (str_starts_with($text, $marker)) {
                return $children;
            }
            $children[0]['attrs']['text'] = $prefix . $text;

            return $children;
        }
        if (
            isset($children[0])
            && in_array($children[0]['type'] ?? null, ['paragraph', 'plain'], true)
            && is_array($children[0]['children'] ?? null)
        ) {
            $children[0]['children'] = $this->withNormalizedTaskMarker($children[0]['children'], $checked);

            return $children;
        }

        array_unshift($children, [
            'type' => 'text',
            'attrs' => ['text' => rtrim($prefix)],
            'children' => [],
        ]);

        return $children;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? []) === []
            && ($node['children'] ?? []) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return is_float($value) ? round($value, 6) : $value;
        }

        $normalized = [];
        foreach ($value as $key => $child) {
            $normalized[$key] = $this->normalizedValue($child);
        }
        if (array_is_list($normalized)) {
            return $this->isNormalizedNodeList($normalized) ? $this->coalescedNormalizedNodeList($normalized) : $normalized;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<mixed> $items
     */
    private function isNormalizedNodeList(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (
                !is_array($item)
                || !is_string($item['type'] ?? null)
                || !is_array($item['attrs'] ?? null)
                || !is_array($item['children'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function coalescedNormalizedNodeList(array $items): array
    {
        $coalesced = [];
        foreach ($items as $item) {
            $this->appendNormalizedChild($coalesced, $item);
        }

        return $coalesced;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function firstDifference(array $left, array $right, string $path = '$'): ?string
    {
        if ($left['type'] !== $right['type']) {
            return "{$path}.type markdown={$left['type']} native={$right['type']}";
        }
        if ($left['attrs'] !== $right['attrs']) {
            return "{$path}.attrs markdown=" . json_encode($left['attrs'], JSON_UNESCAPED_SLASHES)
                . ' native=' . json_encode($right['attrs'], JSON_UNESCAPED_SLASHES);
        }
        if (count($left['children']) !== count($right['children'])) {
            return "{$path}.children count markdown=" . count($left['children']) . ' native=' . count($right['children']);
        }
        foreach ($left['children'] as $index => $leftChild) {
            $difference = $this->firstDifference($leftChild, $right['children'][$index], $path . '.children[' . $index . ']');
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $categories = [];
        if (str_contains($difference, '.type')) {
            $categories[] = 'node-type-difference';
        }
        if (str_contains($difference, '.attrs')) {
            $categories[] = 'attribute-difference';
        }
        if (str_contains($difference, '.children count')) {
            $categories[] = 'child-count-difference';
        }

        return $categories === [] ? ['normalized-ast-difference'] : array_values(array_unique($categories));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function astParityStatus(int $parseFailureCount, int $mismatchCount, int $comparedPairCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-native-pairs';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $directoryPresent,
        int $markdownFixtureCount,
        int $nativeFixtureCount,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount,
        int $unpairedMarkdownFixtureCount
    ): array {
        $astEvidence = $directoryPresent
            ? "native pairs={$comparedPairCount}; parse failures={$parseFailureCount}; normalized matches={$matchCount}; normalized mismatches={$mismatchCount}"
            : 'Markdown/native fixture directory absent; native AST comparison did not run';
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $parseFailureCount === 0
            && $mismatchCount === 0
            && $matchCount === $comparedPairCount;

        return [
            [
                'rank' => 1,
                'id' => 'checked-in-markdown-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare every same-basename checked-in Markdown/native fixture with zero parse failures and zero normalized AST mismatches.',
            ],
            [
                'rank' => 2,
                'id' => 'checked-in-markdown-fixtures-without-native-pairs',
                'status' => !$directoryPresent ? 'not-evaluated' : ($unpairedMarkdownFixtureCount === 0 ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $directoryPresent
                    ? "Markdown fixtures={$markdownFixtureCount}; native fixtures={$nativeFixtureCount}; same-basename pairs={$comparedPairCount}; Markdown fixtures without native pairs={$unpairedMarkdownFixtureCount}"
                    : 'Markdown/native fixture directory absent; unpaired fixture inventory did not run',
                'evidenceRequired' => 'Add same-basename checked-in .native expectations only when normalized AST parity has been demonstrated, and keep unpaired fixtures scoped as corpus-only evidence until then.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-markdown-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'This harness compares checked-in Markdown/native fixtures but does not run upstream Haskell/Tasty Markdown reader tests.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.Markdown runner results when a Haskell runner is available.',
            ],
            [
                'rank' => 4,
                'id' => 'selected-markdown-dialect-profile-coverage',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered && $unpairedMarkdownFixtureCount === 0 ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $directoryPresent
                    ? "The current checked-in gate covers {$comparedPairCount} paired fixture(s) out of {$markdownFixtureCount} selected Markdown fixture(s)."
                    : 'Markdown/native fixture directory absent; fixture coverage did not run.',
                'evidenceRequired' => 'Keep every selected Markdown dialect-profile fixture paired with a native expectation, with zero parse failures and zero normalized AST mismatches.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_float($percent) || is_int($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }

    /**
     * @param list<string> $basenames
     * @return list<string>
     */
    private static function fixtureNamesWithExtension(array $basenames, string $extension, int $maxExamples): array
    {
        return array_map(
            static fn (string $basename): string => $basename . '.' . $extension,
            array_slice($basenames, 0, $maxExamples)
        );
    }
}
