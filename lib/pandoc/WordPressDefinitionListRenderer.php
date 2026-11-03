<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged renderer for Pandoc definition lists and CSL bibliography options. */
final class WordPressDefinitionListRenderer
{
    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    public function render(AstNode $node): string
    {
        $blocks = [];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || !in_array($term->type, ['term', 'definition_term'], true)) {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }

            $blocks[] = '<!-- wp:paragraph -->'
                . "\n" . '<p class="pandoc-definition-term"><strong>' . $this->call('renderInlines', $term) . '</strong></p>'
                . "\n" . '<!-- /wp:paragraph -->';

            $items = [];
            $displayParts = $item->attr('cslDisplayParts', []);
            if (is_array($displayParts)) {
                $displayHtml = $this->extendedRenderer()->renderCslDisplayParts($displayParts);
                if ($displayHtml !== '') {
                    $items[] = '<li>' . $displayHtml . '</li>';
                }
            }

            if ($items === []) {
                foreach ($children as $definition) {
                    if ($definition->type === 'definition') {
                        $items[] = '<li>' . $this->call('renderDefinitionBlocks', $definition) . '</li>';
                    }
                }
            }

            if ($items !== []) {
                $blocks[] = '<!-- wp:list -->'
                    . "\n" . '<ul class="pandoc-definition-values">' . implode('', $items) . '</ul>'
                    . "\n" . '<!-- /wp:list -->';
            }
        }

        return $this->call('renderGroupBlock', $node, ['pandoc-definition-list'], implode("\n\n", $blocks));
    }

    public function renderHtml(AstNode $node): string
    {
        $html = '<dl' . $this->call('renderBlockHtmlAttrs', $node) . $this->renderCslBibliographyOptionAttrs($node) . '>';
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || !in_array($term->type, ['term', 'definition_term'], true)) {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }
            $html .= '<dt>' . $this->call('renderInlines', $term) . '</dt>';

            $displayParts = $item->attr('cslDisplayParts', []);
            if (is_array($displayParts)) {
                $displayHtml = $this->extendedRenderer()->renderCslDisplayParts($displayParts);
                if ($displayHtml !== '') {
                    $html .= '<dd>' . $displayHtml . '</dd>';
                    continue;
                }
            }

            foreach ($children as $definition) {
                if ($definition->type === 'definition') {
                    $html .= '<dd>' . $this->call('renderDefinitionBlocks', $definition) . '</dd>';
                }
            }
        }

        return $html . '</dl>';
    }

    private function renderCslBibliographyOptionAttrs(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes) || !in_array('pandoc-csl-bibliography', $classes, true)) {
            return '';
        }

        $existingAttrs = array_change_key_case($this->call('inlineHtmlAttributes', $node), CASE_LOWER);
        $attrs = '';
        if ($node->attr('hangingIndent') === true && !array_key_exists('data-csl-hanging-indent', $existingAttrs)) {
            $attrs .= ' data-csl-hanging-indent="true"';
        }

        $entrySpacing = $node->attr('entrySpacing');
        if ($entrySpacing !== null && !array_key_exists('data-csl-entry-spacing', $existingAttrs)) {
            $attrs .= ' data-csl-entry-spacing="' . $this->esc((string) $entrySpacing) . '"';
        }

        $lineSpacing = $node->attr('lineSpacing');
        if ($lineSpacing !== null && !array_key_exists('data-csl-line-spacing', $existingAttrs)) {
            $attrs .= ' data-csl-line-spacing="' . $this->esc((string) $lineSpacing) . '"';
        }

        $secondFieldAlign = (string) $node->attr('secondFieldAlign', '');
        if ($secondFieldAlign !== '' && !array_key_exists('data-csl-second-field-align', $existingAttrs)) {
            $attrs .= ' data-csl-second-field-align="' . $this->esc($secondFieldAlign) . '"';
        }

        return $attrs;
    }

    private function extendedRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->call('extendedRenderer');
    }

    private function esc(string $value): string
    {
        return $this->call('escape', $value);
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }
}
