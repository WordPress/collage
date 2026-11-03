<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class WordPressBlockWriter
{
    private const BLOCK_ATTRIBUTE_METHODS = [
        'renderHeadingAttrs', 'blockComment', 'blockTextAlignment', 'blockColorCommentAttrs',
        'blockColorHtmlAttr', 'blockCssColor', 'blockStyleDeclarations', 'renderBlockHtmlAttrs',
        'renderBlockHtmlAttrsWithClasses',
    ];

    private const CODE_BLOCK_METHODS = [
        'renderCodeBlock', 'renderCodeBlockHtml', 'codeBlockLanguage', 'renderCodeBlockPreAttrs',
        'safeCssDimension', 'normalizeBlockCssLength', 'normalizeBlockLineHeight',
    ];

    private const DEFINITION_LIST_METHODS = ['renderDefinitionList', 'renderDefinitionListHtml'];

    private const INLINE_METHODS = [
        'isInlineNode', 'styleDeclarationValue', 'styleDeclarationColor', 'normalizeCssColor',
        'renderInlines', 'renderInlineNodes', 'renderInlineNodesWithoutLeadingTaskGlyph',
        'renderInlineNode', 'renderInlineSpanAttrs', 'renderSpanLikeAttrs', 'renderDivAttrs',
        'renderCustomStyleDataAttr', 'inlineHtmlAttributes', 'htmlFragmentIdNeedsNormalization',
        'normalizeHtmlFragmentId', 'isAllowedInlineHtmlAttr', 'isAllowedBlockHtmlAttr',
        'isAllowedImageHtmlAttr', 'isAllowedSafeGlobalHtmlAttr',
    ];

    private const NESTED_BLOCK_METHODS = [
        'renderBlockQuote', 'renderLineBlockBlock', 'renderLineBlockHtml', 'renderDivBlock',
        'divContainsOnlyPlainImage', 'renderHorizontalRule', 'renderDefinitionBlocks',
        'renderBlocksAsNativeBlocks', 'renderGroupBlock', 'renderBlocksAsHtml',
    ];

    private const METHOD_ALIASES = [
        'renderList' => 'render',
        'renderListHtml' => 'renderHtml',
        'renderDefinitionList' => 'render',
        'renderDefinitionListHtml' => 'renderHtml',
        'renderTable' => 'render',
        'renderTableHtml' => 'renderHtml',
        'renderCodeBlock' => 'render',
        'renderCodeBlockHtml' => 'renderHtml',
        'codeBlockLanguage' => 'language',
        'renderCodeBlockPreAttrs' => 'renderPreAttrs',
        'renderPptxChart' => 'render',
        'registerFootnote' => 'register',
    ];

    private ?WordPressBlockAttributeRenderer $blockAttributeRenderer = null;

    private ?WordPressCodeBlockRenderer $codeBlockRenderer = null;

    private ?WordPressDefinitionListRenderer $definitionListRenderer = null;

    private ?WordPressFootnoteRenderer $footnoteRenderer = null;

    private ?WordPressInlineRenderer $inlineRenderer = null;

    private ?WordPressListRenderer $listRenderer = null;

    private ?WordPressNestedBlockRenderer $nestedBlockRenderer = null;

    private ?WordPressPptxChartRenderer $pptxChartRenderer = null;

    private ?WordPressTableRenderer $tableRenderer = null;

    private ?WordPressTopLevelBlockRenderer $topLevelRenderer = null;

    private ?WordPressExtendedNodeRenderer $extendedRenderer = null;

    /**
     * @param array{includeMetadata?: bool, preserveListAttributes?: bool, preserveEmptyParagraphs?: bool, taskGlyphsAsCheckboxes?: bool, markEmptyTableCells?: bool, highlightCodeBlocks?: bool, highlightStyle?: string, syntaxHighlighterCodeBlocks?: bool, htmlMathMethod?: string|array<string, mixed>, mathMethod?: string, writerHTMLMathMethod?: string|array<string, mixed>} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        $output = '';
        $this->writeTo($document, static function (string $chunk) use (&$output): void {
            $output .= $chunk;
        });

        return $output;
    }

    /**
     * Emit WordPress block markup without retaining the final document string.
     *
     * @param callable(string): void $sink
     */
    public function writeTo(AstNode $document, callable $sink): void
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('WordPress writer expects a document node');
        }

        $this->writeNodesTo($document->children, $sink, $document);
    }

    /**
     * Emit a lazy sequence of top-level document nodes. The optional document
     * carries metadata when callers request the metadata review block.
     *
     * @param iterable<AstNode> $nodes
     * @param callable(string): void $sink
     */
    public function writeNodesTo(iterable $nodes, callable $sink, ?AstNode $metadataDocument = null): void
    {
        if ($metadataDocument !== null && $metadataDocument->type !== 'document') {
            throw new \InvalidArgumentException('WordPress writer metadata source expects a document node');
        }

        $previousFootnotes = $this->footnoteRenderer()->beginScope();
        try {
            $outputBlockCount = 0;
            $appendBlock = static function (string $block) use ($sink, &$outputBlockCount): void {
                if ($outputBlockCount > 0) {
                    $sink("\n\n");
                }
                $sink($block);
                $outputBlockCount++;
            };
            $pendingList = [];
            if ((bool) ($this->options['includeMetadata'] ?? false) && $metadataDocument !== null) {
                $metadataBlock = $this->extendedRenderer()->renderMetadataReviewBlock($metadataDocument);
                if ($metadataBlock !== '') {
                    $appendBlock($metadataBlock);
                }
            }
            $lookahead = [];
            foreach ($nodes as $node) {
                if (!$node instanceof AstNode) {
                    throw new \InvalidArgumentException('WordPress writer expects AstNode top-level nodes');
                }
                $lookahead[] = $node;
                if (count($lookahead) >= 3) {
                    $this->topLevelRenderer()->consume($lookahead, $pendingList, $appendBlock);
                }
            }
            while ($lookahead !== []) {
                $this->topLevelRenderer()->consume($lookahead, $pendingList, $appendBlock);
            }
            $this->topLevelRenderer()->flushList($pendingList, $appendBlock);
            if ($this->footnoteRenderer()->hasFootnotes()) {
                $appendBlock($this->footnoteRenderer()->render());
            }
        } finally {
            $this->footnoteRenderer()->restoreScope($previousFootnotes);
        }
    }












    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'escape') {
            return htmlspecialchars((string) ($arguments[0] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($name === 'sanitizeCodeClass') {
            return preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($arguments[0] ?? '')) ?? '';
        }
        if ($name === 'extendedRenderer') {
            return $this->extendedNodeRenderer();
        }

        $renderer = match (true) {
            in_array($name, self::BLOCK_ATTRIBUTE_METHODS, true) => $this->blockAttributeRenderer(),
            in_array($name, self::CODE_BLOCK_METHODS, true) => $this->codeBlockRenderer(),
            in_array($name, self::DEFINITION_LIST_METHODS, true) => $this->definitionListRenderer(),
            in_array($name, self::INLINE_METHODS, true) => $this->inlineRenderer(),
            in_array($name, self::NESTED_BLOCK_METHODS, true) => $this->nestedBlockRenderer(),
            in_array($name, ['renderList', 'renderListHtml'], true) => $this->listRenderer(),
            in_array($name, ['renderTable', 'renderTableHtml'], true) => $this->tableRenderer(),
            in_array($name, ['shouldSkipEmptyParagraphLikeBlock', 'renderParagraphBlock'], true) => $this->topLevelRenderer(),
            $name === 'renderPptxChart' => $this->pptxChartRenderer(),
            $name === 'registerFootnote' => $this->footnoteRenderer(),
            default => throw new \BadMethodCallException("Unknown WordPress writer bridge '{$name}'."),
        };
        $method = self::METHOD_ALIASES[$name] ?? $name;

        return $renderer->{$method}(...$arguments);
    }
































































































    private function blockAttributeRenderer(): WordPressBlockAttributeRenderer
    {
        return $this->blockAttributeRenderer ??= new WordPressBlockAttributeRenderer($this);
    }

    private function codeBlockRenderer(): WordPressCodeBlockRenderer
    {
        return $this->codeBlockRenderer ??= new WordPressCodeBlockRenderer(
            options: $this->options,
            writer: $this,
        );
    }

    private function definitionListRenderer(): WordPressDefinitionListRenderer
    {
        return $this->definitionListRenderer ??= new WordPressDefinitionListRenderer($this);
    }

    private function footnoteRenderer(): WordPressFootnoteRenderer
    {
        return $this->footnoteRenderer ??= new WordPressFootnoteRenderer($this);
    }

    private function inlineRenderer(): WordPressInlineRenderer
    {
        return $this->inlineRenderer ??= new WordPressInlineRenderer($this);
    }

    private function listRenderer(): WordPressListRenderer
    {
        return $this->listRenderer ??= new WordPressListRenderer(
            options: $this->options,
            writer: $this,
        );
    }

    private function nestedBlockRenderer(): WordPressNestedBlockRenderer
    {
        return $this->nestedBlockRenderer ??= new WordPressNestedBlockRenderer($this);
    }

    private function pptxChartRenderer(): WordPressPptxChartRenderer
    {
        return $this->pptxChartRenderer ??= new WordPressPptxChartRenderer($this);
    }

    private function tableRenderer(): WordPressTableRenderer
    {
        return $this->tableRenderer ??= new WordPressTableRenderer(
            options: $this->options,
            writer: $this,
        );
    }

    private function topLevelRenderer(): WordPressTopLevelBlockRenderer
    {
        return $this->topLevelRenderer ??= new WordPressTopLevelBlockRenderer(
            options: $this->options,
            writer: $this,
        );
    }

    private function extendedNodeRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->extendedRenderer ??= new WordPressExtendedNodeRenderer(
            options: $this->options,
            writer: $this,
        );
    }
}
