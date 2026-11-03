<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged coordinator for top-level WordPress block dispatch. */
final class WordPressTopLevelBlockRenderer
{
    private ?WordPressTopLevelCoreNodeRenderer $coreRenderer = null;

    private ?WordPressTopLevelSpecialNodeRenderer $specialRenderer = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    /**
     * @param list<AstNode> $lookahead
     * @param list<string> $pendingList
     * @param callable(string): void $appendBlock
     */
    public function consume(array &$lookahead, array &$pendingList, callable $appendBlock): void
    {
        $node = $lookahead[0];
        $inlineContainer = count($lookahead) >= 3
            && $lookahead[0]->type === 'raw_html'
            && in_array($lookahead[1]->type, ['paragraph', 'plain'], true)
            && $lookahead[2]->type === 'raw_html'
                ? $this->extendedRenderer()->tryRenderRawHtmlInlineContainerParagraph(
                    $lookahead[0],
                    $lookahead[1],
                    $lookahead[2]
                )
                : null;
        if ($inlineContainer !== null) {
            $this->flushList($pendingList, $appendBlock);
            array_shift($lookahead);
            array_shift($lookahead);
            array_shift($lookahead);
            $appendBlock($inlineContainer);

            return;
        }

        array_shift($lookahead);
        if ($node->type !== 'list_item') {
            $this->flushList($pendingList, $appendBlock);
        }
        if ($this->shouldSkipEmptyParagraphLikeBlock($node)) {
            return;
        }
        if ($node->type === 'list_item') {
            $pendingList[] = '<li>' . $this->call('renderInlines', $node) . '</li>';

            return;
        }

        $rendered = in_array($node->type, [
            'heading', 'paragraph', 'plain', 'bullet_list', 'ordered_list',
            'definition_list', 'table', 'code_block',
        ], true)
            ? $this->coreRenderer()->render($node)
            : $this->specialRenderer()->render($node);
        if ($rendered !== null) {
            $appendBlock($rendered);
        }
    }

    public function shouldSkipEmptyParagraphLikeBlock(AstNode $node): bool
    {
        return !(bool) ($this->options['preserveEmptyParagraphs'] ?? false)
            && in_array($node->type, ['paragraph', 'plain'], true)
            && $node->children === []
            && trim((string) $node->attr('text', '')) === '';
    }

    public function renderParagraphBlock(AstNode $node): string
    {
        return $this->coreRenderer()->renderParagraph($node);
    }

    /**
     * @param list<string> $items
     * @param callable(string): void $appendBlock
     */
    public function flushList(array &$items, callable $appendBlock): void
    {
        if ($items === []) {
            return;
        }

        $appendBlock('<!-- wp:list -->' . "\n" . '<ul>' . implode('', $items) . '</ul>' . "\n" . '<!-- /wp:list -->');
        $items = [];
    }

    private function coreRenderer(): WordPressTopLevelCoreNodeRenderer
    {
        return $this->coreRenderer ??= new WordPressTopLevelCoreNodeRenderer($this->options, $this->writer);
    }

    private function specialRenderer(): WordPressTopLevelSpecialNodeRenderer
    {
        return $this->specialRenderer ??= new WordPressTopLevelSpecialNodeRenderer($this->writer);
    }

    private function extendedRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->call('extendedRenderer');
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }
}
