<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PlainWriter
{
    private const WRAPPED_SOURCE_LINE_SAMPLE_LIMIT = 16;

    /**
     * @param array{columns?: int, wrap?: string, ambiguousWidth?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        return $this->writeWithDiagnostics($document)['text'];
    }

    /**
     * @return array{
     *   text:string,
     *   diagnostics:array{
     *     writer:string,
     *     wrapMode:string,
     *     columns:int,
     *     blockCount:int,
     *     wrappedBlockCount:int,
     *     softWrapBreakCount:int,
     *     wrapSplitLineCount:int,
     *     generatedWrapBreakCount:int,
     *     maxGeneratedWrapBreaksPerSourceLine:int,
     *     wrappedSourceLineCount:int,
     *     wrappedSourceLineSampleLimit:int,
     *     wrappedSourceLinesTruncated:bool,
     *     wrappedSourceLines:list<array{
     *       blockIndex:int,
     *       lineIndex:int,
     *       sourceDisplayWidth:int,
     *       outputLineCount:int,
     *       generatedBreakCount:int,
     *       maxOutputDisplayWidth:int,
     *       forcedWrapBreakCount:int,
     *       text:string,
     *       truncated:bool
     *     }>,
     *     outputLineCount:int,
     *     blankSourceLineCount:int,
     *     blankOutputLineCount:int,
     *     maxOutputDisplayWidth:int,
     *     overColumnLineCount:int,
     *     maxOverColumnDisplayWidth:int,
     *     forcedWrapBreakCount:int,
     *     maxForcedWrapSegmentDisplayWidth:int,
     *     maxUnbreakableDisplayWidth:int,
     *     overlongUnbreakableSpanCount:int,
     *     overlongUnbreakableSpans:list<array{
     *       blockIndex:int,
     *       lineIndex:int,
     *       displayWidth:int,
     *       columns:int,
     *       text:string,
     *       truncated:bool
     *     }>,
     *     hardBreakCount:int,
     *     lineFeedBreakCount:int,
     *     lineSeparatorBreakCount:int,
     *     paragraphSeparatorBreakCount:int,
     *     softBreakOpportunityCount:int,
     *     spaceBreakOpportunityCount:int,
     *     unicodeSpaceBreakOpportunityCount:int,
     *     maxUnicodeSpaceDisplayAdvance:int,
     *     tabBreakOpportunityCount:int,
     *     maxTabDisplayAdvance:int,
     *     zeroWidthSpaceBreakOpportunityCount:int,
     *     softHyphenBreakOpportunityCount:int,
     *     visibleBreakAfterOpportunityCount:int,
     *     protectedSeparatorCount:int,
     *     lineEndingNormalizationCount:int,
     *     blocks:list<array{
     *       blockIndex:int,
     *       blockType:string,
     *       sourceLineCount:int,
     *       outputLineCount:int,
     *       blankSourceLineCount:int,
     *       blankOutputLineCount:int,
     *       maxSourceDisplayWidth:int,
     *       maxOutputDisplayWidth:int,
     *       overColumnLineCount:int,
     *       maxOverColumnDisplayWidth:int,
     *       forcedWrapBreakCount:int,
     *       maxForcedWrapSegmentDisplayWidth:int,
     *       wrapped:bool,
     *       softWrapBreakCount:int,
     *       wrapSplitLineCount:int,
     *       generatedWrapBreakCount:int,
     *       lineFeedBreakCount:int,
     *       lineSeparatorBreakCount:int,
     *       paragraphSeparatorBreakCount:int,
     *       spaceBreakOpportunityCount:int,
     *       unicodeSpaceBreakOpportunityCount:int,
     *       maxUnicodeSpaceDisplayAdvance:int,
     *       tabBreakOpportunityCount:int,
     *       maxTabDisplayAdvance:int,
     *       zeroWidthSpaceBreakOpportunityCount:int,
     *       softHyphenBreakOpportunityCount:int,
     *       visibleBreakAfterOpportunityCount:int,
     *       protectedSeparatorCount:int,
     *       lineEndingNormalizationCount:int
     *     }>
     *   }
     * }
     */
    public function writeWithDiagnostics(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Plain writer expects a document node');
        }

        $columns = $this->columns();
        $wrapMode = $this->wrapMode();
        $ambiguousWidth = (string) ($this->options['ambiguousWidth'] ?? 'narrow');
        $blocks = [];
        $blockDiagnostics = [];
        $wrappedBlockCount = 0;
        $softWrapBreakCount = 0;
        $wrapSplitLineCount = 0;
        $generatedWrapBreakCount = 0;
        $maxGeneratedWrapBreaksPerSourceLine = 0;
        $wrappedSourceLineCount = 0;
        $wrappedSourceLines = [];
        $outputLineCount = 0;
        $blankSourceLineCount = 0;
        $blankOutputLineCount = 0;
        $maxOutputDisplayWidth = 0;
        $overColumnLineCount = 0;
        $maxOverColumnDisplayWidth = 0;
        $forcedWrapBreakCount = 0;
        $maxForcedWrapSegmentDisplayWidth = 0;
        $maxUnbreakableDisplayWidth = 0;
        $overlongUnbreakableSpanCount = 0;
        $overlongUnbreakableSpans = [];
        $hardBreakCount = 0;
        $lineFeedBreakCount = 0;
        $lineSeparatorBreakCount = 0;
        $paragraphSeparatorBreakCount = 0;
        $softBreakOpportunityCount = 0;
        $spaceBreakOpportunityCount = 0;
        $unicodeSpaceBreakOpportunityCount = 0;
        $maxUnicodeSpaceDisplayAdvance = 0;
        $tabBreakOpportunityCount = 0;
        $maxTabDisplayAdvance = 0;
        $zeroWidthSpaceBreakOpportunityCount = 0;
        $softHyphenBreakOpportunityCount = 0;
        $visibleBreakAfterOpportunityCount = 0;
        $protectedSeparatorCount = 0;
        $lineEndingNormalizationCount = 0;

        foreach ($document->children as $index => $node) {
            if (!$node instanceof AstNode) {
                throw new \InvalidArgumentException('Plain writer document children must be AST nodes');
            }

            $source = $this->renderBlock($node);
            if ($source === '') {
                continue;
            }

            [$source, $lineEndings] = UnicodeText::normalizeLineEndings($source);
            $sourceLines = explode("\n", $source);
            $wrappedLines = $this->wrapLines($source, $columns, $wrapMode, $ambiguousWidth);
            $output = implode("\n", $wrappedLines);
            $blocks[] = $output;

            $sourceLineCount = count($sourceLines);
            $wrappedLineCount = count($wrappedLines);
            $blockBlankSourceLineCount = $this->blankLineCount($sourceLines);
            $blockBlankOutputLineCount = $this->blankLineCount($wrappedLines);
            $wrapped = $wrappedLineCount > $sourceLineCount;
            if ($wrapped) {
                ++$wrappedBlockCount;
            }
            $blockSoftWrapBreakCount = $this->softWrapBreakCount($source, $wrappedLineCount, $columns, $wrapMode);
            $softWrapBreakCount += $blockSoftWrapBreakCount;
            $wrapMetrics = $this->wrapLineMetrics($source, $columns, $wrapMode, $ambiguousWidth);
            $wrapSplitLineCount += $wrapMetrics['splitLineCount'];
            $generatedWrapBreakCount += $wrapMetrics['generatedBreakCount'];
            $maxGeneratedWrapBreaksPerSourceLine = max(
                $maxGeneratedWrapBreaksPerSourceLine,
                $wrapMetrics['maxGeneratedBreaksPerSourceLine']
            );
            $wrappedSourceLineCount += $wrapMetrics['splitLineCount'];
            foreach ($wrapMetrics['wrappedSourceLines'] as $lineDiagnostic) {
                if (count($wrappedSourceLines) >= self::WRAPPED_SOURCE_LINE_SAMPLE_LIMIT) {
                    break;
                }

                $wrappedSourceLines[] = [
                    'blockIndex' => $index,
                ] + $lineDiagnostic;
            }

            $sourceMax = $this->maxDisplayWidth($sourceLines, $ambiguousWidth);
            $outputMax = $this->maxDisplayWidth($wrappedLines, $ambiguousWidth);
            $overColumn = $this->overColumnLineMetrics($wrappedLines, $columns, $ambiguousWidth);
            $forcedWrap = $this->forcedWrapMetrics($sourceLines, $columns, $wrapMode, $ambiguousWidth);
            $unbreakable = $this->overlongUnbreakableSpanDiagnostics($sourceLines, $columns, $ambiguousWidth);
            $maxOutputDisplayWidth = max($maxOutputDisplayWidth, $outputMax);
            $overColumnLineCount += $overColumn['count'];
            $maxOverColumnDisplayWidth = max($maxOverColumnDisplayWidth, $overColumn['maxDisplayWidth']);
            $forcedWrapBreakCount += $forcedWrap['forcedWrapBreakCount'];
            $maxForcedWrapSegmentDisplayWidth = max(
                $maxForcedWrapSegmentDisplayWidth,
                $forcedWrap['maxForcedWrapSegmentDisplayWidth']
            );
            $maxUnbreakableDisplayWidth = max($maxUnbreakableDisplayWidth, $unbreakable['maxUnbreakableDisplayWidth']);
            $overlongUnbreakableSpanCount += $unbreakable['overlongUnbreakableSpanCount'];
            foreach ($unbreakable['overlongUnbreakableSpans'] as $span) {
                if (count($overlongUnbreakableSpans) >= 16) {
                    break;
                }
                $overlongUnbreakableSpans[] = [
                    'blockIndex' => $index,
                ] + $span;
            }
            $outputLineCount += $this->nonEmptyLineCount($wrappedLines);
            $blankSourceLineCount += $blockBlankSourceLineCount;
            $blankOutputLineCount += $blockBlankOutputLineCount;

            $opportunities = UnicodeText::lineBreakOpportunities($source, $ambiguousWidth);
            $typeCounts = $this->lineBreakOpportunityTypeCounts($opportunities['opportunities']);
            $hardBreakCount += $opportunities['hardBreakCount'];
            $lineFeedBreakCount += $typeCounts['lineFeed'];
            $lineSeparatorBreakCount += $typeCounts['lineSeparator'];
            $paragraphSeparatorBreakCount += $typeCounts['paragraphSeparator'];
            $softBreakOpportunityCount += $opportunities['softBreakCount'];
            $spaceBreakOpportunityCount += $typeCounts['space'];
            $unicodeSpaceBreakOpportunityCount += $typeCounts['unicodeSpace'];
            $maxUnicodeSpaceDisplayAdvance = max($maxUnicodeSpaceDisplayAdvance, $typeCounts['maxUnicodeSpaceDisplayAdvance']);
            $tabBreakOpportunityCount += $typeCounts['tab'];
            $maxTabDisplayAdvance = max($maxTabDisplayAdvance, $typeCounts['maxTabDisplayAdvance']);
            $zeroWidthSpaceBreakOpportunityCount += $typeCounts['zeroWidthSpace'];
            $softHyphenBreakOpportunityCount += $typeCounts['softHyphen'];
            $visibleBreakAfterOpportunityCount += $typeCounts['visibleBreakAfter'];
            $protectedSeparatorCount += $opportunities['protectedSeparatorCount'];
            $lineEndingNormalizationCount += $lineEndings['conversions'];

            $blockDiagnostics[] = [
                'blockIndex' => $index,
                'blockType' => $node->type,
                'sourceLineCount' => $sourceLineCount,
                'outputLineCount' => $wrappedLineCount,
                'blankSourceLineCount' => $blockBlankSourceLineCount,
                'blankOutputLineCount' => $blockBlankOutputLineCount,
                'maxSourceDisplayWidth' => $sourceMax,
                'maxOutputDisplayWidth' => $outputMax,
                'overColumnLineCount' => $overColumn['count'],
                'maxOverColumnDisplayWidth' => $overColumn['maxDisplayWidth'],
                'forcedWrapBreakCount' => $forcedWrap['forcedWrapBreakCount'],
                'maxForcedWrapSegmentDisplayWidth' => $forcedWrap['maxForcedWrapSegmentDisplayWidth'],
                'wrapped' => $wrapped,
                'softWrapBreakCount' => $blockSoftWrapBreakCount,
                'wrapSplitLineCount' => $wrapMetrics['splitLineCount'],
                'generatedWrapBreakCount' => $wrapMetrics['generatedBreakCount'],
                'lineFeedBreakCount' => $typeCounts['lineFeed'],
                'lineSeparatorBreakCount' => $typeCounts['lineSeparator'],
                'paragraphSeparatorBreakCount' => $typeCounts['paragraphSeparator'],
                'spaceBreakOpportunityCount' => $typeCounts['space'],
                'unicodeSpaceBreakOpportunityCount' => $typeCounts['unicodeSpace'],
                'maxUnicodeSpaceDisplayAdvance' => $typeCounts['maxUnicodeSpaceDisplayAdvance'],
                'tabBreakOpportunityCount' => $typeCounts['tab'],
                'maxTabDisplayAdvance' => $typeCounts['maxTabDisplayAdvance'],
                'zeroWidthSpaceBreakOpportunityCount' => $typeCounts['zeroWidthSpace'],
                'softHyphenBreakOpportunityCount' => $typeCounts['softHyphen'],
                'visibleBreakAfterOpportunityCount' => $typeCounts['visibleBreakAfter'],
                'protectedSeparatorCount' => $opportunities['protectedSeparatorCount'],
                'lineEndingNormalizationCount' => $lineEndings['conversions'],
            ];
        }

        return [
            'text' => implode("\n\n", $blocks),
            'diagnostics' => [
                'writer' => 'plain',
                'wrapMode' => $wrapMode,
                'columns' => $columns,
                'blockCount' => count($blocks),
                'wrappedBlockCount' => $wrappedBlockCount,
                'softWrapBreakCount' => $softWrapBreakCount,
                'wrapSplitLineCount' => $wrapSplitLineCount,
                'generatedWrapBreakCount' => $generatedWrapBreakCount,
                'maxGeneratedWrapBreaksPerSourceLine' => $maxGeneratedWrapBreaksPerSourceLine,
                'wrappedSourceLineCount' => $wrappedSourceLineCount,
                'wrappedSourceLineSampleLimit' => self::WRAPPED_SOURCE_LINE_SAMPLE_LIMIT,
                'wrappedSourceLinesTruncated' => $wrappedSourceLineCount > count($wrappedSourceLines),
                'wrappedSourceLines' => $wrappedSourceLines,
                'outputLineCount' => $outputLineCount,
                'blankSourceLineCount' => $blankSourceLineCount,
                'blankOutputLineCount' => $blankOutputLineCount,
                'maxOutputDisplayWidth' => $maxOutputDisplayWidth,
                'overColumnLineCount' => $overColumnLineCount,
                'maxOverColumnDisplayWidth' => $maxOverColumnDisplayWidth,
                'forcedWrapBreakCount' => $forcedWrapBreakCount,
                'maxForcedWrapSegmentDisplayWidth' => $maxForcedWrapSegmentDisplayWidth,
                'maxUnbreakableDisplayWidth' => $maxUnbreakableDisplayWidth,
                'overlongUnbreakableSpanCount' => $overlongUnbreakableSpanCount,
                'overlongUnbreakableSpans' => $overlongUnbreakableSpans,
                'hardBreakCount' => $hardBreakCount,
                'lineFeedBreakCount' => $lineFeedBreakCount,
                'lineSeparatorBreakCount' => $lineSeparatorBreakCount,
                'paragraphSeparatorBreakCount' => $paragraphSeparatorBreakCount,
                'softBreakOpportunityCount' => $softBreakOpportunityCount,
                'spaceBreakOpportunityCount' => $spaceBreakOpportunityCount,
                'unicodeSpaceBreakOpportunityCount' => $unicodeSpaceBreakOpportunityCount,
                'maxUnicodeSpaceDisplayAdvance' => $maxUnicodeSpaceDisplayAdvance,
                'tabBreakOpportunityCount' => $tabBreakOpportunityCount,
                'maxTabDisplayAdvance' => $maxTabDisplayAdvance,
                'zeroWidthSpaceBreakOpportunityCount' => $zeroWidthSpaceBreakOpportunityCount,
                'softHyphenBreakOpportunityCount' => $softHyphenBreakOpportunityCount,
                'visibleBreakAfterOpportunityCount' => $visibleBreakAfterOpportunityCount,
                'protectedSeparatorCount' => $protectedSeparatorCount,
                'lineEndingNormalizationCount' => $lineEndingNormalizationCount,
                'blocks' => $blockDiagnostics,
            ],
        ];
    }

    private function columns(): int
    {
        $columns = $this->options['columns'] ?? 72;

        return is_int($columns) ? max(0, $columns) : 72;
    }

    private function wrapMode(): string
    {
        $mode = strtolower((string) ($this->options['wrap'] ?? 'auto'));

        return in_array($mode, ['auto', 'none', 'preserve'], true) ? $mode : 'auto';
    }

    /**
     * @return list<string>
     */
    private function wrapLines(string $text, int $columns, string $wrapMode, string $ambiguousWidth): array
    {
        if ($wrapMode !== 'auto' || $columns <= 0) {
            return explode("\n", $text);
        }

        return UnicodeText::wrapByDisplayWidth($text, $columns, '', $ambiguousWidth);
    }

    /**
     * @param list<array{type:string, column?:int, columnAfter?:int}> $opportunities
     * @return array{lineFeed:int, lineSeparator:int, paragraphSeparator:int, space:int, unicodeSpace:int, maxUnicodeSpaceDisplayAdvance:int, tab:int, maxTabDisplayAdvance:int, zeroWidthSpace:int, softHyphen:int, visibleBreakAfter:int}
     */
    private function lineBreakOpportunityTypeCounts(array $opportunities): array
    {
        $counts = [
            'lineFeed' => 0,
            'lineSeparator' => 0,
            'paragraphSeparator' => 0,
            'space' => 0,
            'unicodeSpace' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tab' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpace' => 0,
            'softHyphen' => 0,
            'visibleBreakAfter' => 0,
        ];

        foreach ($opportunities as $opportunity) {
            $key = match ($opportunity['type']) {
                'line-feed' => 'lineFeed',
                'line-separator' => 'lineSeparator',
                'paragraph-separator' => 'paragraphSeparator',
                'space' => 'space',
                'tab' => 'tab',
                'zero-width-space' => 'zeroWidthSpace',
                'soft-hyphen' => 'softHyphen',
                'visible-break-after' => 'visibleBreakAfter',
                default => null,
            };
            if ($key !== null) {
                ++$counts[$key];
            }
            if ($opportunity['type'] === 'tab') {
                $advance = max(0, (int) ($opportunity['columnAfter'] ?? 0) - (int) ($opportunity['column'] ?? 0));
                $counts['maxTabDisplayAdvance'] = max($counts['maxTabDisplayAdvance'], $advance);
            }
            if ($this->isUnicodeSpaceBreakType($opportunity['type'])) {
                ++$counts['unicodeSpace'];
                $advance = max(0, (int) ($opportunity['columnAfter'] ?? 0) - (int) ($opportunity['column'] ?? 0));
                $counts['maxUnicodeSpaceDisplayAdvance'] = max($counts['maxUnicodeSpaceDisplayAdvance'], $advance);
            }
        }

        return $counts;
    }

    private function isUnicodeSpaceBreakType(string $type): bool
    {
        return in_array($type, [
            'unicode-space',
            'ogham-space-mark',
            'en-quad',
            'em-quad',
            'en-space',
            'em-space',
            'three-per-em-space',
            'four-per-em-space',
            'six-per-em-space',
            'punctuation-space',
            'thin-space',
            'hair-space',
            'medium-mathematical-space',
            'ideographic-space',
        ], true);
    }

    private function renderBlock(AstNode $node): string
    {
        return match ($node->type) {
            'paragraph', 'plain', 'heading' => $this->renderInlines($node->children),
            'blockquote', 'div' => $this->renderBlockCollection($node->children),
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'line_block' => $this->renderLineBlock($node),
            'table' => $this->renderTable($node),
            'code_block' => (string) $node->attr('text', ''),
            'raw_markdown', 'raw_tex', 'raw_block' => (string) $node->attr('text', $node->attr('markdown', $node->attr('tex', ''))),
            'horizontal_rule' => '',
            default => $node->children === []
                ? (string) $node->attr('text', '')
                : $this->renderBlockCollection($node->children),
        };
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlockCollection(array $blocks): string
    {
        $rendered = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $text = $this->isInlineNode($block)
                ? $this->renderInlines([$block])
                : $this->renderBlock($block);
            if ($text !== '') {
                $rendered[] = $text;
            }
        }

        return implode("\n", $rendered);
    }

    private function renderList(AstNode $node, bool $ordered): string
    {
        $lines = [];
        $number = (int) $node->attr('start', 1);
        foreach ($node->children as $item) {
            if (!$item instanceof AstNode || $item->type !== 'list_item') {
                continue;
            }

            $marker = $ordered ? $number . '. ' : '- ';
            $number++;
            $text = $this->renderBlockCollection($item->children);
            $itemLines = explode("\n", $text === '' ? (string) $item->attr('text', '') : $text);
            $first = array_shift($itemLines);
            $lines[] = $marker . (string) $first;
            foreach ($itemLines as $line) {
                $lines[] = str_repeat(' ', strlen($marker)) . $line;
            }
        }

        return implode("\n", $lines);
    }

    private function renderLineBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if (!$line instanceof AstNode || $line->type !== 'line') {
                continue;
            }

            $lines[] = $line->children === []
                ? (string) $line->attr('text', '')
                : $this->renderInlines($line->children);
        }

        return implode("\n", $lines);
    }

    private function renderTable(AstNode $node): string
    {
        $columnCount = TableGeometry::columnCount($node);
        if ($columnCount === 0) {
            return $this->renderCaption($node);
        }

        $lines = [];
        $caption = $this->renderCaption($node);
        if ($caption !== '') {
            $lines[] = $caption;
        }

        $previousGroup = null;
        foreach ($this->tableRowGroups($node) as $group) {
            if (
                $previousGroup !== null
                && $previousGroup['section'] === 'table_body'
                && $group['section'] === 'table_body'
            ) {
                $lines[] = '';
            }

            foreach (TableGeometry::layoutRows($group['rows'], $columnCount) as $layoutRow) {
                $cells = [];
                $visualColumn = 0;
                foreach ($layoutRow['cells'] as $layoutCell) {
                    $column = (int) $layoutCell['column'];
                    while ($visualColumn < $column) {
                        $cells[] = '';
                        $visualColumn++;
                    }

                    $colspan = max(1, (int) $layoutCell['colspan']);
                    $cells[] = $this->renderTableCell($layoutCell['node']);
                    $visualColumn += $colspan;
                }

                while ($visualColumn < $columnCount) {
                    $cells[] = '';
                    $visualColumn++;
                }

                $lines[] = rtrim(implode(' | ', $cells));
            }

            $previousGroup = $group;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{section:string, rows:list<AstNode>}>
     */
    private function tableRowGroups(AstNode $node): array
    {
        $groups = [];
        foreach ($node->children as $section) {
            if (!$section instanceof AstNode) {
                continue;
            }

            if ($section->type === 'table_head' || $section->type === 'table_body' || $section->type === 'table_foot') {
                $rows = [];
                if ($section->type === 'table_body') {
                    $headRows = $section->attr('headRows', []);
                    if (is_array($headRows)) {
                        foreach ($headRows as $row) {
                            if ($row instanceof AstNode && $row->type === 'table_row') {
                                $rows[] = $row;
                            }
                        }
                    }
                }

                foreach ($section->children as $row) {
                    if ($row instanceof AstNode && $row->type === 'table_row') {
                        $rows[] = $row;
                    }
                }

                if ($rows !== []) {
                    $groups[] = [
                        'section' => $section->type,
                        'rows' => $rows,
                    ];
                }
            }
        }

        return $groups;
    }

    private function renderTableCell(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->normalizeTableCellText((string) $cell->attr('text', ''));
        }

        $onlyInlines = true;
        foreach ($cell->children as $child) {
            if (!$child instanceof AstNode || !$this->isInlineNode($child)) {
                $onlyInlines = false;
                break;
            }
        }

        $text = $onlyInlines
            ? $this->renderInlines($cell->children)
            : $this->renderBlockCollection($cell->children);

        return $this->normalizeTableCellText($text);
    }

    private function renderCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', []);
        if (is_array($captionInlines) && $captionInlines !== [] && $this->allAstNodes($captionInlines)) {
            return $this->normalizeTableCellText($this->renderInlines(array_values($captionInlines)));
        }

        $captionBlocks = $node->attr('captionBlocks', []);
        if (is_array($captionBlocks) && $captionBlocks !== [] && $this->allAstNodes($captionBlocks)) {
            return $this->normalizeTableCellText($this->renderBlockCollection(array_values($captionBlocks)));
        }

        return $this->normalizeTableCellText((string) $node->attr('caption', ''));
    }

    private function normalizeTableCellText(string $text): string
    {
        return trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<mixed> $nodes
     */
    private function allAstNodes(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            $text .= $this->renderInline($node);
        }

        return $text;
    }

    private function renderInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => (string) $node->attr('text', ''),
            'space', 'softbreak' => ' ',
            'linebreak' => "\n",
            'code' => (string) $node->attr('text', ''),
            'math' => (string) $node->attr('text', ''),
            'link' => $this->renderLink($node),
            'image' => $this->renderImage($node),
            'citation', 'citation_group' => (string) $node->attr('rendered', $node->attr('text', $this->renderInlines($node->children))),
            'raw_inline', 'raw_markdown', 'raw_tex', 'raw_html_inline' => (string) $node->attr('text', $node->attr('markdown', $node->attr('tex', $node->attr('html', '')))),
            'note' => $this->renderBlockCollection($node->children),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderLink(AstNode $node): string
    {
        $label = $this->renderInlines($node->children);

        return $label === '' ? (string) $node->attr('url', '') : $label;
    }

    private function renderImage(AstNode $node): string
    {
        $alt = $this->renderInlines($node->children);

        return $alt === '' ? (string) $node->attr('alt', '') : $alt;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'softbreak',
            'linebreak',
            'code',
            'math',
            'link',
            'image',
            'citation',
            'citation_group',
            'raw_inline',
            'raw_markdown',
            'raw_tex',
            'raw_html_inline',
            'note',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'span',
            'quoted',
        ], true);
    }

    /**
     * @param list<string> $lines
     */
    private function maxDisplayWidth(array $lines, string $ambiguousWidth): int
    {
        $max = 0;
        foreach ($lines as $line) {
            $max = max($max, UnicodeText::displayWidth($line, $ambiguousWidth));
        }

        return $max;
    }

    /**
     * @param list<string> $lines
     * @return array{count:int, maxDisplayWidth:int}
     */
    private function overColumnLineMetrics(array $lines, int $columns, string $ambiguousWidth): array
    {
        if ($columns <= 0) {
            return ['count' => 0, 'maxDisplayWidth' => 0];
        }

        $count = 0;
        $max = 0;
        foreach ($lines as $line) {
            $width = UnicodeText::displayWidth($line, $ambiguousWidth);
            if ($width <= $columns) {
                continue;
            }

            ++$count;
            $max = max($max, $width);
        }

        return ['count' => $count, 'maxDisplayWidth' => $max];
    }

    private function softWrapBreakCount(string $source, int $wrappedLineCount, int $columns, string $wrapMode): int
    {
        if ($wrapMode !== 'auto' || $columns <= 0) {
            return 0;
        }

        return max(0, $wrappedLineCount - $this->physicalLineCount($source));
    }

    private function physicalLineCount(string $source): int
    {
        $lines = preg_split('/\R/u', $source);
        if ($lines === false) {
            $lines = explode("\n", $source);
        }

        return max(1, count($lines));
    }

    /**
     * @return array{
     *   splitLineCount:int,
     *   generatedBreakCount:int,
     *   maxGeneratedBreaksPerSourceLine:int,
     *   wrappedSourceLines:list<array{
     *     lineIndex:int,
     *     sourceDisplayWidth:int,
     *     outputLineCount:int,
     *     generatedBreakCount:int,
     *     maxOutputDisplayWidth:int,
     *     forcedWrapBreakCount:int,
     *     text:string,
     *     truncated:bool
     *   }>
     * }
     */
    private function wrapLineMetrics(string $source, int $columns, string $wrapMode, string $ambiguousWidth): array
    {
        if ($wrapMode !== 'auto' || $columns <= 0) {
            return [
                'splitLineCount' => 0,
                'generatedBreakCount' => 0,
                'maxGeneratedBreaksPerSourceLine' => 0,
                'wrappedSourceLines' => [],
            ];
        }

        $sourceLines = preg_split('/\R/u', $source);
        if ($sourceLines === false) {
            $sourceLines = explode("\n", $source);
        }

        $splitLineCount = 0;
        $generatedBreakCount = 0;
        $maxGeneratedBreaksPerSourceLine = 0;
        $wrappedSourceLines = [];
        foreach ($sourceLines as $lineIndex => $line) {
            $wrapped = UnicodeText::wrapByDisplayWidth($line, $columns, '', $ambiguousWidth);
            $generatedBreaks = max(0, count($wrapped) - 1);
            if ($generatedBreaks === 0) {
                continue;
            }

            ++$splitLineCount;
            $generatedBreakCount += $generatedBreaks;
            $maxGeneratedBreaksPerSourceLine = max($maxGeneratedBreaksPerSourceLine, $generatedBreaks);
            if (count($wrappedSourceLines) >= self::WRAPPED_SOURCE_LINE_SAMPLE_LIMIT) {
                continue;
            }

            [$text, $truncated] = $this->diagnosticTextSample($line);
            $wrappedSourceLines[] = [
                'lineIndex' => $lineIndex,
                'sourceDisplayWidth' => UnicodeText::displayWidth($line, $ambiguousWidth),
                'outputLineCount' => count($wrapped),
                'generatedBreakCount' => $generatedBreaks,
                'maxOutputDisplayWidth' => $this->maxDisplayWidth($wrapped, $ambiguousWidth),
                'forcedWrapBreakCount' => $this->forcedWrapBreakCountForLine($line, $columns, $ambiguousWidth),
                'text' => $text,
                'truncated' => $truncated,
            ];
        }

        return [
            'splitLineCount' => $splitLineCount,
            'generatedBreakCount' => $generatedBreakCount,
            'maxGeneratedBreaksPerSourceLine' => $maxGeneratedBreaksPerSourceLine,
            'wrappedSourceLines' => $wrappedSourceLines,
        ];
    }

    /**
     * @param list<string> $sourceLines
     * @return array{forcedWrapBreakCount:int, maxForcedWrapSegmentDisplayWidth:int}
     */
    private function forcedWrapMetrics(array $sourceLines, int $columns, string $wrapMode, string $ambiguousWidth): array
    {
        if ($wrapMode !== 'auto' || $columns <= 0) {
            return ['forcedWrapBreakCount' => 0, 'maxForcedWrapSegmentDisplayWidth' => 0];
        }

        $breaks = 0;
        $maxSegmentWidth = 0;
        foreach ($sourceLines as $line) {
            foreach ($this->unbreakableWrapSegments($line) as $segment) {
                $segmentWidth = UnicodeText::displayWidth($segment, $ambiguousWidth);
                $segmentBreaks = $this->forcedWrapBreaksForSegment($segment, $columns, $ambiguousWidth);
                if ($segmentBreaks === 0) {
                    continue;
                }

                $breaks += $segmentBreaks;
                $maxSegmentWidth = max($maxSegmentWidth, $segmentWidth);
            }
        }

        return [
            'forcedWrapBreakCount' => $breaks,
            'maxForcedWrapSegmentDisplayWidth' => $maxSegmentWidth,
        ];
    }

    private function forcedWrapBreaksForSegment(string $segment, int $columns, string $ambiguousWidth): int
    {
        $breaks = 0;
        $remaining = $segment;
        while ($remaining !== '' && UnicodeText::displayWidth($remaining, $ambiguousWidth) > $columns) {
            [, $tail] = UnicodeText::splitAtDisplayWidth($remaining, $columns, $ambiguousWidth);
            if ($tail === '') {
                break;
            }

            ++$breaks;
            $remaining = $tail;
        }

        return $breaks;
    }

    private function forcedWrapBreakCountForLine(string $line, int $columns, string $ambiguousWidth): int
    {
        $breaks = 0;
        foreach ($this->unbreakableWrapSegments($line) as $segment) {
            $breaks += $this->forcedWrapBreaksForSegment($segment, $columns, $ambiguousWidth);
        }

        return $breaks;
    }

    /**
     * @param list<string> $sourceLines
     * @return array{
     *   maxUnbreakableDisplayWidth:int,
     *   overlongUnbreakableSpanCount:int,
     *   overlongUnbreakableSpans:list<array{lineIndex:int, displayWidth:int, columns:int, text:string, truncated:bool}>
     * }
     */
    private function overlongUnbreakableSpanDiagnostics(array $sourceLines, int $columns, string $ambiguousWidth): array
    {
        $maxUnbreakableDisplayWidth = 0;
        $overlongUnbreakableSpanCount = 0;
        $overlongSpans = [];

        foreach ($sourceLines as $lineIndex => $line) {
            foreach ($this->unbreakableWrapSegments($line) as $span) {
                $displayWidth = UnicodeText::displayWidth($span, $ambiguousWidth);
                $maxUnbreakableDisplayWidth = max($maxUnbreakableDisplayWidth, $displayWidth);
                if ($columns <= 0 || $displayWidth <= $columns) {
                    continue;
                }

                ++$overlongUnbreakableSpanCount;
                if (count($overlongSpans) >= 16) {
                    continue;
                }

                [$text, $truncated] = $this->diagnosticTextSample($span);
                $overlongSpans[] = [
                    'lineIndex' => $lineIndex,
                    'displayWidth' => $displayWidth,
                    'columns' => $columns,
                    'text' => $text,
                    'truncated' => $truncated,
                ];
            }
        }

        return [
            'maxUnbreakableDisplayWidth' => $maxUnbreakableDisplayWidth,
            'overlongUnbreakableSpanCount' => $overlongUnbreakableSpanCount,
            'overlongUnbreakableSpans' => $overlongSpans,
        ];
    }

    /**
     * @return list<string>
     */
    private function unbreakableWrapSegments(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        $segments = [];
        $buffer = '';
        foreach (UnicodeText::characters($line) as $char) {
            if ($this->isDiagnosticWrapWhitespace($char) || $char === "\u{200B}" || $char === "\u{00AD}") {
                $this->appendDiagnosticSegment($segments, $buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
            if ($char === "\u{0F0B}") {
                $this->appendDiagnosticSegment($segments, $buffer);
                $buffer = '';
            }
        }
        $this->appendDiagnosticSegment($segments, $buffer);

        return $segments;
    }

    /**
     * @param list<string> $segments
     */
    private function appendDiagnosticSegment(array &$segments, string $segment): void
    {
        if ($segment !== '') {
            $segments[] = $segment;
        }
    }

    private function isDiagnosticWrapWhitespace(string $char): bool
    {
        return in_array($char, [
            ' ',
            "\t",
            "\x0B",
            "\x0C",
            "\u{1680}",
            "\u{2000}",
            "\u{2001}",
            "\u{2002}",
            "\u{2003}",
            "\u{2004}",
            "\u{2005}",
            "\u{2006}",
            "\u{2008}",
            "\u{2009}",
            "\u{200A}",
            "\u{205F}",
            "\u{3000}",
        ], true);
    }

    /**
     * @return array{0:string, 1:bool}
     */
    private function diagnosticTextSample(string $text): array
    {
        $graphemes = UnicodeText::graphemes($text);
        if (count($graphemes) <= 40) {
            return [$text, false];
        }

        return [implode('', array_slice($graphemes, 0, 40)), true];
    }

    /**
     * @param list<string> $lines
     */
    private function nonEmptyLineCount(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            if ($line !== '') {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $lines
     */
    private function blankLineCount(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            if ($line === '') {
                ++$count;
            }
        }

        return $count;
    }
}
