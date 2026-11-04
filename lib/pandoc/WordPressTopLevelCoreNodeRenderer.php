<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Core heading, paragraph, list, table, and code top-level nodes. */
final class WordPressTopLevelCoreNodeRenderer
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function render(AstNode $node): ?string
    {
        if ($node->type === 'heading') {
            $level = (int) $node->attr('level', 2);
            $headingAttrs = ['level' => $level];
            $alignment = $this->call('blockTextAlignment', $node);
            if ($alignment !== '') {
                $headingAttrs['textAlign'] = $alignment;
            }
            $headingAttrs = array_replace($headingAttrs, $this->call('blockColorCommentAttrs', $node));

            return $this->call('blockComment', 'heading', $headingAttrs)
                . "\n" . '<h' . $level . $this->call('renderHeadingAttrs', $node) . '>' . $this->call('renderInlines', $node) . '</h' . $level . '>'
                . "\n" . '<!-- /wp:heading -->';
        }

        return match ($node->type) {
            'paragraph' => $this->renderParagraph($node),
            'plain' => $this->renderPlain($node),
            'bullet_list' => $this->call('renderList', $node, false),
            'ordered_list' => $this->call('renderList', $node, true),
            'definition_list' => $this->call('renderDefinitionList', $node),
            'table' => $this->call('renderTable', $node),
            'code_block' => $this->call('renderCodeBlock', $node),
            default => null,
        };
    }

    public function renderParagraph(AstNode $node): string
    {
        $chart = $this->renderablePptxChart($node);
        if ($chart !== null) {
            return '<!-- wp:html -->' . "\n" . $this->call('renderPptxChart', $node, $chart) . "\n" . '<!-- /wp:html -->';
        }
        if (count($node->children) === 1 && $node->children[0]->type === 'image') {
            return $this->extendedRenderer()->renderParagraphImageBlock($node->children[0]);
        }

        $alignment = $this->call('blockTextAlignment', $node);
        $commentAttrs = array_replace(
            $alignment === '' ? [] : ['align' => $alignment],
            $this->call('blockColorCommentAttrs', $node)
        );
        $htmlAttrs = $alignment === ''
            ? $this->call('renderBlockHtmlAttrs', $node)
            : $this->call('renderBlockHtmlAttrsWithClasses', $node, ['has-text-align-' . $alignment]);
        $htmlAttrs .= $this->call('blockColorHtmlAttr', $node);
        $html = '<p' . $htmlAttrs . '>' . $this->call('renderInlines', $node) . '</p>';

        if ($this->containsActiveRawHtmlInline($node)) {
            return '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
        }

        return $this->call('blockComment', 'paragraph', $commentAttrs)
            . "\n" . $html
            . "\n" . '<!-- /wp:paragraph -->';
    }

    private function renderPlain(AstNode $node): string
    {
        $html = '<p>' . $this->call('renderInlines', $node) . '</p>';
        if ($this->containsActiveRawHtmlInline($node)) {
            return '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
        }

        return '<!-- wp:paragraph -->' . "\n" . $html . "\n" . '<!-- /wp:paragraph -->';
    }

    private function containsActiveRawHtmlInline(AstNode $node): bool
    {
        if ($node->type === 'raw_html_inline') {
            return $this->isActiveRawHtml((string) $node->attr('html', ''));
        }

        if (
            $node->type === 'raw_inline'
            && MarkdownFormatProfile::rawFamily((string) $node->attr('format', 'raw')) === 'html'
        ) {
            return $this->isActiveRawHtml((string) $node->attr('text', ''));
        }

        foreach ($node->children as $child) {
            if ($this->containsActiveRawHtmlInline($child)) {
                return true;
            }
        }

        return false;
    }

    private function isActiveRawHtml(string $html): bool
    {
        return preg_match(
            '/<\s*\/?\s*(?:script|style|iframe|object|svg|math|form|input|textarea|select|button|canvas|video|audio)\b/iu',
            $html
        ) === 1
            || preg_match('/\son[a-z][a-z0-9_:-]*\s*=/iu', $html) === 1
            || preg_match(
                '/\b(?:href|src|xlink:href|formaction)\s*=\s*(?:"\s*javascript:|\'\s*javascript:|javascript:)/iu',
                $html
            ) === 1;
    }

    /** @return array<string, mixed>|null */
    private function renderablePptxChart(AstNode $node): ?array
    {
        $chart = $node->attr('pptxChart');
        $series = is_array($chart) ? ($chart['series'] ?? null) : null;

        return is_array($chart) && ($chart['issues'] ?? []) === [] && is_array($series) && $series !== []
            ? $chart
            : null;
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
