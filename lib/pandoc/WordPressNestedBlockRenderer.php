<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged renderer for nested, grouped, and HTML-fallback block content. */
final class WordPressNestedBlockRenderer
{
    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    public function renderBlockQuote(AstNode $node): string
    {
        return '<!-- wp:quote -->'
            . "\n" . '<blockquote' . $this->call('renderBlockHtmlAttrsWithClasses', $node, ['wp-block-quote']) . '>' . $this->renderBlocksAsHtml($node->children, true) . '</blockquote>'
            . "\n" . '<!-- /wp:quote -->';
    }

    public function renderLineBlockBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type === 'line') {
                $lines[] = $this->call('renderInlines', $line);
            }
        }

        return '<!-- wp:verse -->'
            . "\n" . '<pre' . $this->call('renderBlockHtmlAttrsWithClasses', $node, ['wp-block-verse']) . '>'
            . implode("\n", $lines) . '</pre>'
            . "\n" . '<!-- /wp:verse -->';
    }

    public function renderLineBlockHtml(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type === 'line') {
                $lines[] = $this->call('renderInlines', $line);
            }
        }

        return '<p>' . implode('<br/>', $lines) . '</p>';
    }

    public function renderDivBlock(AstNode $node): string
    {
        if ($this->hasClass($node, 'linegroup')) {
            return $this->renderLineGroupBlock($node);
        }

        return $this->renderGroupBlock(
            $node,
            [],
            $this->renderBlocksAsNativeBlocks($node->children, !$this->divContainsOnlyPlainImage($node))
        );
    }

    public function divContainsOnlyPlainImage(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $child = $node->children[0];

        return $child->type === 'plain'
            && count($child->children) === 1
            && $child->children[0]->type === 'image';
    }

    public function renderHorizontalRule(): string
    {
        return '<!-- wp:separator -->'
            . "\n" . '<hr class="wp-block-separator has-alpha-channel-opacity"/>'
            . "\n" . '<!-- /wp:separator -->';
    }

    public function renderDefinitionBlocks(AstNode $definition): string
    {
        $html = '';
        $paragraphCount = 0;
        foreach ($definition->children as $child) {
            if ($child->type === 'paragraph') {
                $paragraphCount++;
            }
        }
        $wrapParagraphs = (bool) $definition->attr('loose', false) || $paragraphCount > 1;

        foreach ($definition->children as $child) {
            if ($child->type === 'bullet_list') {
                $html .= $this->call('renderListHtml', $child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->call('renderListHtml', $child, true);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $this->call('renderInlines', $child);
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }
            if ($child->type === 'raw_html') {
                $html .= (string) $child->attr('html', '');
                continue;
            }
            if ($child->type === 'raw_tex') {
                $html .= $this->extendedRenderer()->renderRawTexBlockHtml($child);
                continue;
            }
            if (!$this->call('isInlineNode', $child)) {
                $html .= $this->renderBlocksAsHtml([$child]);
                continue;
            }

            $html .= $this->call('renderInlineNode', $child);
        }

        return $html;
    }

    /** @param list<AstNode> $blocks */
    public function renderBlocksAsNativeBlocks(array $blocks, bool $wrapPlainBlocks = false): string
    {
        $renderedBlocks = [];
        foreach ($blocks as $block) {
            if ($this->call('shouldSkipEmptyParagraphLikeBlock', $block)) {
                continue;
            }

            if ($block->type === 'paragraph') {
                $renderedBlocks[] = $this->call('renderParagraphBlock', $block);
                continue;
            }
            if ($block->type === 'plain') {
                if (count($block->children) === 1 && $block->children[0]->type === 'image') {
                    $renderedBlocks[] = $this->extendedRenderer()->renderParagraphImageBlock($block->children[0]);
                    continue;
                }
                $renderedBlocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p' . $this->call('renderBlockHtmlAttrs', $block) . '>' . $this->call('renderInlines', $block) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
                continue;
            }
            if ($block->type === 'heading') {
                $level = (int) $block->attr('level', 2);
                $renderedBlocks[] = '<!-- wp:heading {"level":' . $level . '} -->'
                    . "\n" . '<h' . $level . $this->call('renderHeadingAttrs', $block) . '>' . $this->call('renderInlines', $block) . '</h' . $level . '>'
                    . "\n" . '<!-- /wp:heading -->';
                continue;
            }
            if ($block->type === 'bullet_list') {
                $renderedBlocks[] = $this->call('renderList', $block, false);
                continue;
            }
            if ($block->type === 'ordered_list') {
                $renderedBlocks[] = $this->call('renderList', $block, true);
                continue;
            }
            if ($block->type === 'definition_list') {
                $renderedBlocks[] = $this->call('renderDefinitionList', $block);
                continue;
            }
            if ($block->type === 'table') {
                $renderedBlocks[] = $this->call('renderTable', $block);
                continue;
            }
            if ($block->type === 'code_block') {
                $renderedBlocks[] = $this->call('renderCodeBlock', $block);
                continue;
            }
            if ($block->type === 'figure') {
                $renderedBlocks[] = $this->extendedRenderer()->renderFigureBlock($block);
                continue;
            }
            if ($block->type === 'image') {
                $renderedBlocks[] = $this->extendedRenderer()->renderParagraphImageBlock($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $renderedBlocks[] = $this->renderBlockQuote($block);
                continue;
            }
            if ($block->type === 'line_block') {
                $renderedBlocks[] = $this->renderLineBlockBlock($block);
                continue;
            }
            if ($block->type === 'horizontal_rule') {
                $renderedBlocks[] = $this->renderHorizontalRule();
                continue;
            }
            if ($block->type === 'raw_html') {
                $renderedBlocks[] = $this->extendedRenderer()->renderRawHtmlBlock($block);
                continue;
            }
            if ($block->type === 'raw_tex') {
                $renderedBlocks[] = $this->extendedRenderer()->renderRawTexBlock($block);
                continue;
            }
            if ($block->type === 'raw_block') {
                $renderedBlocks[] = $this->extendedRenderer()->renderRawFormatBlock($block);
                continue;
            }
            if ($block->type === 'div') {
                $renderedBlocks[] = $this->renderDivBlock($block);
                continue;
            }
            if ($this->call('isInlineNode', $block)) {
                $renderedBlocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p>' . $this->call('renderInlineNode', $block) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
                continue;
            }

            $html = $this->renderBlocksAsHtml([$block], $wrapPlainBlocks);
            if ($html !== '') {
                $renderedBlocks[] = '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
            }
        }

        return implode("\n\n", $renderedBlocks);
    }

    /** @param list<string> $classes */
    public function renderGroupBlock(AstNode $node, array $classes, string $innerBlocks): string
    {
        $attrs = $this->call('renderBlockHtmlAttrsWithClasses', $node, array_merge(['wp-block-group'], $classes));

        return '<!-- wp:group -->'
            . "\n" . '<div' . $attrs . '>'
            . ($innerBlocks === '' ? '' : "\n" . $innerBlocks . "\n")
            . '</div>'
            . "\n" . '<!-- /wp:group -->';
    }

    /** @param list<AstNode> $blocks */
    public function renderBlocksAsHtml(array $blocks, bool $wrapPlainBlocks = false): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($this->call('shouldSkipEmptyParagraphLikeBlock', $block)) {
                continue;
            }

            if ($block->type === 'paragraph') {
                $html .= '<p' . $this->call('renderBlockHtmlAttrs', $block) . '>' . $this->call('renderInlines', $block) . '</p>';
                continue;
            }
            if ($block->type === 'plain') {
                $rendered = $this->call('renderInlines', $block);
                $html .= $wrapPlainBlocks
                    ? '<p' . $this->call('renderBlockHtmlAttrs', $block) . '>' . $rendered . '</p>'
                    : $rendered;
                continue;
            }
            if ($block->type === 'heading') {
                $level = (int) $block->attr('level', 2);
                $html .= '<h' . $level . $this->call('renderHeadingAttrs', $block) . '>' . $this->call('renderInlines', $block) . '</h' . $level . '>';
                continue;
            }
            if ($block->type === 'bullet_list') {
                $html .= $this->call('renderListHtml', $block, false);
                continue;
            }
            if ($block->type === 'ordered_list') {
                $html .= $this->call('renderListHtml', $block, true);
                continue;
            }
            if ($block->type === 'definition_list') {
                $html .= $this->call('renderDefinitionListHtml', $block);
                continue;
            }
            if ($block->type === 'table') {
                $html .= $this->call('renderTableHtml', $block);
                continue;
            }
            if ($block->type === 'code_block') {
                $html .= $this->call('renderCodeBlockHtml', $block);
                continue;
            }
            if ($block->type === 'figure') {
                $html .= $this->extendedRenderer()->renderFigureHtmlBlock($block);
                continue;
            }
            if ($block->type === 'image') {
                $html .= $this->extendedRenderer()->renderImageHtml($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $html .= '<blockquote>' . $this->renderBlocksAsHtml($block->children, $wrapPlainBlocks) . '</blockquote>';
                continue;
            }
            if ($block->type === 'line_block') {
                $html .= $this->renderLineBlockHtml($block);
                continue;
            }
            if ($block->type === 'horizontal_rule') {
                $html .= '<hr/>';
                continue;
            }
            if ($block->type === 'raw_html') {
                $html .= $this->extendedRenderer()->renderRawHtmlBlockHtml($block);
                continue;
            }
            if ($block->type === 'raw_tex') {
                $html .= $this->extendedRenderer()->renderRawTexBlockHtml($block);
                continue;
            }
            if ($block->type === 'raw_block') {
                $html .= $this->extendedRenderer()->renderRawFormatBlockHtml($block);
                continue;
            }
            if ($block->type === 'div') {
                $html .= '<div' . $this->call('renderDivAttrs', $block) . '>'
                    . $this->renderBlocksAsHtml($block->children, !$this->divContainsOnlyPlainImage($block))
                    . '</div>';
            }
        }

        return $html;
    }

    private function hasClass(AstNode $node, string $class): bool
    {
        return in_array($class, $this->nodeClassList($node), true);
    }

    /** @return list<string> */
    private function nodeClassList(AstNode $node): array
    {
        $classes = [];
        $nodeClasses = $node->attr('classes', []);
        if (is_array($nodeClasses)) {
            foreach ($nodeClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }

        $htmlAttributes = $this->call('inlineHtmlAttributes', $node);
        $htmlClass = trim((string) ($htmlAttributes['class'] ?? ''));
        if ($htmlClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $htmlClass, -1, PREG_SPLIT_NO_EMPTY));
        }

        return array_values(array_unique($classes));
    }

    private function renderLineGroupBlock(AstNode $node): string
    {
        $lineRuns = [];
        $lineRun = [];
        $hasNonLineChildren = false;
        foreach ($node->children as $child) {
            if ($this->isLineGroupLineNode($child)) {
                $lineRun[] = $child;
                continue;
            }

            if ($lineRun !== []) {
                $lineRuns[] = $lineRun;
                $lineRun = [];
            }
            $lineRuns[] = [$child];
            $hasNonLineChildren = true;
        }
        if ($lineRun !== []) {
            $lineRuns[] = $lineRun;
        }

        if (!$hasNonLineChildren) {
            return $this->renderLineGroupParagraphBlock($node->children, $node);
        }

        $innerBlocks = [];
        foreach ($lineRuns as $run) {
            $innerBlocks[] = count($run) === 1 && !$this->isLineGroupLineNode($run[0])
                ? $this->renderBlocksAsNativeBlocks($run, true)
                : $this->renderLineGroupParagraphBlock($run);
        }

        return $this->renderGroupBlock($node, [], implode("\n\n", array_filter($innerBlocks, static fn (string $block): bool => $block !== '')));
    }

    private function isLineGroupDiv(AstNode $node): bool
    {
        return $this->hasClass($node, 'linegroup');
    }

    private function isLineGroupLineNode(AstNode $node): bool
    {
        if (in_array($node->type, ['paragraph', 'plain'], true)) {
            return true;
        }

        return $node->type === 'div'
            && !$this->isLineGroupDiv($node)
            && count($node->children) === 1
            && in_array($node->children[0]->type, ['paragraph', 'plain'], true);
    }

    /** @param list<AstNode> $lines */
    private function renderLineGroupParagraphBlock(array $lines, ?AstNode $container = null): string
    {
        $renderedLines = [];
        foreach ($lines as $line) {
            $renderedLines[] = $this->renderLineGroupLine($line);
        }

        return '<!-- wp:paragraph -->'
            . "\n" . '<p' . ($container instanceof AstNode ? $this->call('renderBlockHtmlAttrs', $container) : '') . '>' . implode('<br/>', $renderedLines) . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
    }

    private function renderLineGroupLine(AstNode $line): string
    {
        $lineBlocks = $line->type === 'div' ? $line->children : [$line];
        $html = count($lineBlocks) === 1 && in_array($lineBlocks[0]->type, ['paragraph', 'plain'], true)
            ? $this->call('renderInlines', $lineBlocks[0])
            : $this->renderBlocksAsHtml($lineBlocks, true);

        if ($line->type !== 'div') {
            return $html;
        }

        $attrs = $this->call('renderBlockHtmlAttrs', $line);

        return $attrs === '' ? $html : '<span' . $attrs . '>' . $html . '</span>';
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
