<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-not-full-docx-parity';
    private const CLAIM = 'Compares local PHP DOCX reader output with paired upstream .native fixtures by normalized AST shape; local provenance attrs, derived paragraph text caches, and adjacent text-run segmentation are excluded, but no upstream Haskell runner or DOCX writer parity is asserted.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'alignmentConstructor' => true,
        'alignmentConstructors' => true,
        'alignmentNative' => true,
        'alignmentNatives' => true,
        'attrConstructor' => true,
        'attrNative' => true,
        'captionConstructor' => true,
        'captionNative' => true,
        'citationConstructor' => true,
        'citationModeConstructor' => true,
        'citationModeNative' => true,
        'citationNative' => true,
        'citationPrefixNative' => true,
        'citationRecordsNative' => true,
        'citationSuffixNative' => true,
        'colSpanConstructor' => true,
        'colSpanNative' => true,
        'columnSpecNatives' => true,
        'columnWidthConstructors' => true,
        'columnWidthNatives' => true,
        'constructor' => true,
        'definitionDefinitionsNative' => true,
        'definitionItemNative' => true,
        'definitionNative' => true,
        'definitionTermNative' => true,
        'documentConstructor' => true,
        'documentNative' => true,
        'formatConstructor' => true,
        'formatNative' => true,
        'legacyTableCellBlocksNative' => true,
        'lineNative' => true,
        'listAttributesConstructor' => true,
        'listAttributesNative' => true,
        'listDelimiterConstructor' => true,
        'listDelimiterNative' => true,
        'listItemNative' => true,
        'listStyleConstructor' => true,
        'listStyleNative' => true,
        'mathTypeConstructor' => true,
        'mathTypeNative' => true,
        'metaConstructorProvenance' => true,
        'native' => true,
        'nativeFormat' => true,
        'nativeInlineConstructors' => true,
        'nativeInlineParts' => true,
        'pandocApiVersion' => true,
        'quoteTypeConstructor' => true,
        'quoteTypeNative' => true,
        'rowHeadColumnsConstructor' => true,
        'rowHeadColumnsNative' => true,
        'rowSpanConstructor' => true,
        'rowSpanNative' => true,
        'shortCaptionConstructor' => true,
        'shortCaptionMaybeConstructor' => true,
        'shortCaptionMaybeNative' => true,
        'shortCaptionNative' => true,
        'targetConstructor' => true,
        'targetNative' => true,
    ];

    /**
     * @return array{matches:bool, firstDifference:?string}
     */
    public function compareDocuments(AstNode $localDocument, AstNode $nativeDocument): array
    {
        $local = $this->normalizedNode($localDocument);
        $native = $this->normalizedNode($nativeDocument);

        return [
            'matches' => $local === $native,
            'firstDifference' => $local === $native
                ? null
                : ($this->firstDifference($local, $native) ?? 'unknown-normalized-ast-difference'),
        ];
    }

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $docxDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($docxDirectory)) {
            return [
                'schemaVersion' => 1,
                'tool' => 'pandoc-docx-native-ast',
                'status' => 'skipped',
                'skipped' => true,
                'reason' => 'upstream-cache-missing',
                'verdict' => self::VERDICT,
                'claim' => self::CLAIM,
                'evidenceKind' => 'docx-native-normalized-ast-comparison',
                'upstreamDocxDirectory' => $docxDirectory,
                'normalizationPolicy' => self::normalizationPolicy(),
                'totalPairCount' => 0,
                'comparedPairCount' => 0,
                'docxParsedCount' => 0,
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
                'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0),
            ];
        }

        $docxFiles = $this->filesByBasename($docxDirectory, 'docx');
        $nativeFiles = $this->filesByBasename($docxDirectory, 'native');
        $pairNames = array_values(array_intersect(array_keys($docxFiles), array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $docxParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $docxResult = $this->readDocx($docxFiles[$pairName]);
            if ($docxResult['ok']) {
                ++$docxParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$docxResult['ok'] || !$nativeResult['ok']) {
                $failure = [
                    'fixture' => $pairName,
                    'docxError' => $docxResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $parseFailures[] = $failure;
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $docxDocument */
            $docxDocument = $docxResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $docxAst = $this->normalizedNode($docxDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($docxAst === $nativeAst) {
                ++$matchCount;
                continue;
            }

            $difference = $this->firstDifference($docxAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }

            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'docxTopTypes' => $this->topTypeSequence($docxDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedPairCount = count($pairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'docx-native-normalized-ast-comparison',
            'upstreamDocxDirectory' => $docxDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'docxParsedCount' => $docxParsedCount,
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
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamDocxDirectory=' . (string) ($report['upstreamDocxDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'pairs: total=%d compared=%d parsedBoth=%d parseFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0),
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown'),
        );

        $categories = $report['mismatchCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'mismatchCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText
                );
            }
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
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required mapped parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['docxParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-not-runner-or-writer-parity';
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type',
                'non-provenance node attributes',
                'child order and child count',
                'inline and block AST shape after local readers normalize native constructors and adjacent text runs',
            ],
            'excludes' => [
                'local native/parser provenance attrs',
                'document-level metadata added by local DOCX package parsing',
                'local DOCX raw bookmark markers and visible field/content-control provenance wrappers',
                'DOCX data-docx-* provenance attributes retained for local writer diagnostics',
                'DOCX table captionSource retained for local writer diagnostics',
                'derived text attrs on plain, paragraph, heading, and table_cell nodes',
                'DOCX paragraph and heading presentation attrs that Pandoc native AST cannot encode',
                'empty bookmark anchor spans retained for local HTML and WordPress link targets',
                'derived figure caption inline caches when caption blocks are present',
                'DOCX package media target roots when upstream native uses document-relative media paths',
                'default table cell alignment, spans, and row-head counts omitted by native Attr tuples',
                'DOCX tab separator encoding when upstream native exposes equivalent spacing',
                'reader-specific adjacent Str/Space text-node segmentation',
                'nested links with the same target, which cannot be represented by valid HTML anchors',
                'floating-point serialization noise in table column width fractions',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'DOCX writer golden package parity',
                'byte-level DOCX package equality',
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

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readDocx(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new DocxReader())->readDocxFile($path), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
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
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($this->isIgnoredTableCellAttr($node, (string) $key, $value)) {
                continue;
            }
            if ($this->isPandocUnrepresentableDocxPresentationAttr($node, $key)) {
                continue;
            }
            if ($key === 'attributes' && $this->isDocxProvenanceAttributeMap($value)) {
                continue;
            }
            if ($node->type === 'document' && $key === 'meta') {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell'], true)) {
                continue;
            }
            if ($node->type === 'table' && $key === 'captionSource') {
                continue;
            }
            if ($this->isDerivedFigureCaptionInlineCache($node, $key)) {
                continue;
            }
            if ($node->type === 'table_cell' && in_array($key, ['colspan', 'rowspan'], true) && (int) $value === 1) {
                continue;
            }
            if ($node->type === 'table_body' && $key === 'rowHeadColumns' && (int) $value === 0) {
                continue;
            }
            if ($node->type === 'image' && in_array($key, ['width', 'height'], true) && $this->imageDimensionMirrorsAttrTuple($node, $key, $value)) {
                continue;
            }
            if (in_array($key, ['captionInlines', 'shortCaptionInlines'], true) && is_array($value) && $this->isAstNodeList($value)) {
                $attrs[$key] = $this->normalizedCaptionInlineListValue($value);
                continue;
            }
            if (in_array($key, ['captionBlocks', 'shortCaptionBlocks'], true) && is_array($value) && $this->isAstNodeList($value)) {
                $attrs[$key] = $this->normalizedCaptionBlockListValue($value);
                continue;
            }
            if ($node->type === 'image' && $key === 'url' && is_string($value)) {
                $value = $this->normalizedImageUrl($value);
            }
            if ($node->type === 'table' && $key === 'widths' && is_array($value)) {
                $attrs[$key] = $this->normalizedTableWidths($value);
                continue;
            }
            $normalizedValue = $this->normalizedValue($value);
            if (in_array($key, ['attributes', 'htmlAttributes'], true) && $normalizedValue === []) {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        $children = $node->type === 'figure'
            ? $this->normalizedFigureChildren($node->children)
            : $this->normalizedChildren($node->children);
        if ($node->type === 'link') {
            $children = $this->flattenNestedEquivalentLinks($children, $attrs);
        }

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $children,
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedFigureChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            if ($child->type === 'image') {
                $this->appendNormalizedChild($normalized, [
                    'type' => 'plain',
                    'attrs' => [],
                    'children' => [$this->normalizedNode($child)],
                ]);
                continue;
            }

            $this->appendNormalizedChild($normalized, $this->normalizedNode($child));
        }

        return $this->trimBoundaryWhitespaceText($normalized);
    }

    private function isDerivedFigureCaptionInlineCache(AstNode $node, string $key): bool
    {
        if ($node->type !== 'figure') {
            return false;
        }

        $blockKey = match ($key) {
            'captionInlines' => 'captionBlocks',
            'shortCaptionInlines' => 'shortCaptionBlocks',
            default => null,
        };
        if ($blockKey === null) {
            return false;
        }

        $blocks = $node->attr($blockKey, null);
        if (!is_array($blocks) || $blocks === [] || !array_is_list($blocks)) {
            return false;
        }

        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function normalizedImageUrl(string $url): string
    {
        if (str_starts_with($url, 'word/media/')) {
            return substr($url, strlen('word/'));
        }

        return $url;
    }

    /**
     * @param array<mixed> $widths
     * @return list<float>
     */
    private function normalizedTableWidths(array $widths): array
    {
        $normalized = [];
        foreach ($widths as $width) {
            $normalized[] = $width === null ? 0.0 : round((float) $width, 12);
        }

        return $normalized;
    }

    private function isIgnoredTableCellAttr(AstNode $node, string $key, mixed $value): bool
    {
        if ($node->type !== 'table_cell') {
            return false;
        }

        if ($key === 'text' || $key === 'htmlAttributes') {
            return true;
        }
        if ($key === 'colspan' || $key === 'rowspan') {
            return (int) $value === 1;
        }
        if ($key === 'align') {
            return (string) $value === 'default';
        }

        return false;
    }

    private function isPandocUnrepresentableDocxPresentationAttr(AstNode $node, string $key): bool
    {
        return in_array($node->type, ['plain', 'paragraph', 'heading'], true)
            && in_array($key, ['align', 'backgroundColor'], true);
    }

    private function isDocxProvenanceAttributeMap(mixed $value): bool
    {
        if (!is_array($value) || array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $key => $_) {
            if (!is_string($key) || !str_starts_with($key, 'data-docx-')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $replacementChildren = $this->comparisonReplacementChildren($child);
            if (is_array($replacementChildren)) {
                foreach ($this->normalizedChildren($replacementChildren) as $replacementChild) {
                    $this->appendNormalizedChild($normalized, $replacementChild);
                }
                continue;
            }

            $this->appendNormalizedChild($normalized, $this->normalizedNode($child));
        }

        return $this->trimBoundaryWhitespaceText($normalized);
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
     * Returns a replacement child list for DOCX-only comparison wrappers, an
     * empty list for ignored markers, or null when the node should compare as-is.
     *
     * @return list<AstNode>|null
     */
    private function comparisonReplacementChildren(AstNode $node): ?array
    {
        if ($this->isRawBookmarkInline($node)) {
            return [];
        }

        if ($this->isEmptyBookmarkAnchorSpan($node)) {
            return [];
        }

        if ($this->isWhitespaceOnlyStyledInline($node)) {
            return [];
        }

        if ($this->isTransparentDocxProvenanceSpan($node)) {
            return $node->children;
        }

        return null;
    }

    private function isEmptyBookmarkAnchorSpan(AstNode $node): bool
    {
        if ($node->type !== 'span' || $node->children !== [] || (string) $node->attr('id', '') === '') {
            return false;
        }

        $classes = $node->attr('classes', []);

        return is_array($classes) && in_array('anchor', array_map('strval', $classes), true);
    }

    private function isRawBookmarkInline(AstNode $node): bool
    {
        if ($node->type !== 'raw_inline' || $node->attr('format') !== 'openxml') {
            return false;
        }

        return preg_match('/^<w:bookmark(?:Start|End)\b/', (string) $node->attr('text', '')) === 1;
    }

    private function isWhitespaceOnlyStyledInline(AstNode $node): bool
    {
        if (!in_array($node->type, ['emph', 'strong', 'underline', 'strikeout', 'small_caps', 'superscript', 'subscript'], true)) {
            return false;
        }

        return $this->normalizedInlineText($node) !== '' && trim($this->normalizedInlineText($node)) === '';
    }

    private function isTransparentDocxProvenanceSpan(AstNode $node): bool
    {
        if ($node->type !== 'span') {
            return false;
        }

        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }
        $classes = array_map('strval', $classes);

        if (in_array('indexref', $classes, true) || in_array('docx-index-entry', $classes, true)) {
            return false;
        }

        return in_array('docx-field', $classes, true)
            || in_array('docx-generated-field', $classes, true)
            || in_array('docx-content-control', $classes, true)
            || in_array('docx-content-control-inline', $classes, true);
    }

    /**
     * Pandoc's DOCX reader can expose a field link nested in a hyperlink with
     * the same target. HTML cannot represent nested anchors, so local output
     * flattens that shape. Compare the equivalent inline content here.
     *
     * @param list<array<string, mixed>> $children
     * @param array<string, mixed> $attrs
     * @return list<array<string, mixed>>
     */
    private function flattenNestedEquivalentLinks(array $children, array $attrs): array
    {
        $url = $attrs['url'] ?? null;
        $title = $attrs['title'] ?? null;
        if (!is_string($url) || !is_string($title)) {
            return $children;
        }

        $flattened = [];
        foreach ($children as $child) {
            $childAttrs = $child['attrs'] ?? null;
            if (
                ($child['type'] ?? null) === 'link'
                && is_array($childAttrs)
                && ($childAttrs['url'] ?? null) === $url
                && ($childAttrs['title'] ?? null) === $title
                && is_array($child['children'] ?? null)
            ) {
                foreach ($child['children'] as $grandchild) {
                    if (is_array($grandchild)) {
                        $this->appendNormalizedChild($flattened, $grandchild);
                    }
                }
                continue;
            }

            $this->appendNormalizedChild($flattened, $child);
        }

        return $this->trimBoundaryWhitespaceText($flattened);
    }

    private function normalizedInlineText(AstNode $node): string
    {
        if (array_key_exists('text', $node->attrs) && is_string($node->attrs['text'])) {
            return $node->attrs['text'];
        }

        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->normalizedInlineText($child);
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function trimBoundaryWhitespaceText(array $nodes): array
    {
        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[0])) {
            array_shift($nodes);
        }

        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[count($nodes) - 1])) {
            array_pop($nodes);
        }

        return array_values($nodes);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWhitespaceOnlyPlainTextNode(array $node): bool
    {
        if (!$this->isPlainTextNode($node)) {
            return false;
        }

        return trim((string) $node['attrs']['text']) === '';
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\t", ' ', $value);
        }
        if (is_float($value)) {
            return round($value, 12);
        }
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            if ($this->isAstNodeList($value)) {
                return $this->normalizedChildren($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizedValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (self::isIgnoredAttrKey((string) $key)) {
                continue;
            }
            $normalized[(string) $key] = $this->normalizedValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function normalizedCaptionInlineListValue(array $nodes): array
    {
        $normalized = [];
        $text = '';
        foreach ($nodes as $node) {
            $replacementChildren = $this->comparisonReplacementChildren($node);
            if (is_array($replacementChildren)) {
                foreach ($this->normalizedCaptionInlineListValue($replacementChildren) as $replacementNode) {
                    $this->appendNormalizedCaptionInlineNode($normalized, $text, $replacementNode);
                }
                continue;
            }

            if ($node->type === 'text') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'space') {
                $text .= ' ';
                continue;
            }
            if ($node->type === 'softbreak' || $node->type === 'linebreak') {
                $text .= ' ';
                continue;
            }

            $this->appendCaptionInlineText($normalized, $text);
            $text = '';
            $normalized[] = $this->normalizedNode($node);
        }
        $this->appendCaptionInlineText($normalized, $text);

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedCaptionInlineNode(array &$normalized, string &$text, array $node): void
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['attrs']['text'] ?? null)) {
            $text .= $node['attrs']['text'];
            return;
        }

        $this->appendCaptionInlineText($normalized, $text);
        $text = '';
        $normalized[] = $node;
    }

    /**
     * @param list<array<string, mixed>> $normalized
     */
    private function appendCaptionInlineText(array &$normalized, string $text): void
    {
        $text = preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? str_replace("\t", ' ', $text);
        if ($text === '') {
            return;
        }

        $this->appendNormalizedChild($normalized, [
            'type' => 'text',
            'attrs' => ['text' => $text],
            'children' => [],
        ]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function normalizedCaptionBlockListValue(array $nodes): array
    {
        return array_map(fn (AstNode $node): array => $this->normalizedCaptionBlockValue($node), $nodes);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedCaptionBlockValue(AstNode $node): array
    {
        $normalized = $this->normalizedNode($node);
        if (in_array($node->type, ['plain', 'paragraph'], true)) {
            $normalized['children'] = $this->normalizedCaptionInlineListValue($node->children);
        }

        return $normalized;
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key])
            || str_starts_with($key, 'data-docx-')
            || self::isNativeProvenanceAttrKey($key);
    }

    private static function isNativeProvenanceAttrKey(string $key): bool
    {
        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors')
            || in_array($key, ['constructor', 'pandocApiVersion'], true);
    }

    /**
     * @param list<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $value !== [];
    }

    private function imageDimensionMirrorsAttrTuple(AstNode $node, string $key, mixed $value): bool
    {
        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes) || !array_key_exists($key, $attributes)) {
            return false;
        }

        return $this->normalizedValue($attributes[$key]) === $this->normalizedValue($value);
    }

    private function firstDifference(mixed $docx, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($docx) !== gettype($native)) {
            return "{$path} type " . gettype($docx) . ' vs ' . gettype($native);
        }
        if (!is_array($docx)) {
            return $docx === $native ? null : "{$path} value " . self::shortJson($docx) . ' vs ' . self::shortJson($native);
        }

        $docxKeys = array_keys($docx);
        $nativeKeys = array_keys($native);
        if ($docxKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($docxKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($docxKeys as $key) {
            $difference = $this->firstDifference($docx[$key], $native[$key], $path . '.' . $key);
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
        $lower = strtolower($difference);
        $categories = [];
        if (str_contains($lower, '.children keys')) {
            $categories[] = 'child-count-or-inline-granularity';
        }
        if (str_contains($lower, '.attrs keys') || str_contains($lower, '.attrs.')) {
            $categories[] = 'attribute-shape';
        }
        if (str_contains($lower, 'numid') || str_contains($lower, 'delimiter') || str_contains($lower, 'list')) {
            $categories[] = 'list-attribute-normalization';
        }
        if (str_contains($lower, 'alignment') || str_contains($lower, 'width') || str_contains($lower, 'table')) {
            $categories[] = 'table-attribute-normalization';
        }
        if (str_contains($lower, '.type')) {
            $categories[] = 'node-type';
        }
        if (str_contains($lower, ' value ')) {
            $categories[] = 'scalar-value';
        }
        if ($categories === []) {
            $categories[] = 'uncategorized-normalized-ast-drift';
        }

        return array_values(array_unique($categories));
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
            return 'not-evaluated-no-paired-fixtures';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-equality-observed-not-runner-or-writer-parity';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $sourceDirectoryPresent,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        $sourceEvidence = $sourceDirectoryPresent
            ? "compared pairs={$comparedPairCount}; parse failures={$parseFailureCount}; normalized AST matches={$matchCount}; normalized AST mismatches={$mismatchCount}"
            : 'optional upstream DOCX cache absent; normalized AST comparison did not run';

        return [
            [
                'rank' => 1,
                'id' => 'normalized-docx-native-ast-equality',
                'status' => !$sourceDirectoryPresent
                    ? 'not-evaluated'
                    : (($parseFailureCount === 0 && $mismatchCount === 0 && $comparedPairCount > 0) ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $sourceEvidence,
                'evidenceRequired' => 'Keep parse failures and normalized AST mismatches at zero for every paired root-level upstream DOCX/native fixture.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-docx-runner-results',
                'status' => 'open',
                'currentEvidence' => 'No upstream Haskell/Cabal test-pandoc DOCX reader or writer runner result is recorded by this AST lane.',
                'evidenceRequired' => 'Record reproducible upstream DOCX reader/writer runner results or a native-PHP equivalent denominator with per-fixture pass/fail rows.',
            ],
            [
                'rank' => 3,
                'id' => 'writer-golden-docx-package-parity',
                'status' => 'open',
                'currentEvidence' => 'The AST harness reads paired DOCX/native reader fixtures only; it does not generate DOCX or compare writer golden packages.',
                'evidenceRequired' => 'Generate DOCX output for upstream writer golden cases and compare package parts, relationships, content types, and document XML semantics.',
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
                '%d. %s [%s] current=%s required=%s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown-gap'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? ''),
                (string) ($gap['evidenceRequired'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private static function formatPercent(mixed $value): string
    {
        if (!is_int($value) && !is_float($value)) {
            return 'n/a';
        }

        return number_format((float) $value, 2) . '%';
    }

    private static function shortJson(mixed $value): string
    {
        try {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return gettype($value);
        }

        return strlen($json) > 180 ? substr($json, 0, 177) . '...' : $json;
    }
}
