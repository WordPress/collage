<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Retain PDF reading-direction semantics without guessing a language.
 *
 * PDF text runs and tagged structure may expose direction or language, but
 * untagged searchable PDFs commonly expose only Unicode text.  In that case
 * the Unicode first-strong rule is sufficient to mark an RTL block while
 * leaving LTR and neutral blocks at the document default.  The pass is
 * deliberately attribute-only: it never reverses, reshapes, or rewrites text.
 */
final class PdfLanguageDirectionDecorator
{
    /** @var array<string, true> */
    private const DIRECTIONAL_BLOCK_TYPES = [
        'blockquote' => true,
        'bullet_list' => true,
        'caption' => true,
        'definition' => true,
        'definition_item' => true,
        'div' => true,
        'figure' => true,
        'heading' => true,
        'line' => true,
        'line_block' => true,
        'list_item' => true,
        'ordered_list' => true,
        'paragraph' => true,
        'plain' => true,
        'table' => true,
        'table_body' => true,
        'table_cell' => true,
        'table_foot' => true,
        'table_head' => true,
        'table_row' => true,
    ];

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    public function decorate(array $blocks): array
    {
        $decorated = [];
        foreach ($blocks as $block) {
            if ($block instanceof AstNode) {
                $decorated[] = $this->decorateNode($block);
            }
        }

        return $decorated;
    }

    private function decorateNode(AstNode $node): AstNode
    {
        $children = [];
        $childrenChanged = false;
        foreach ($node->children() as $child) {
            $decoratedChild = $this->decorateNode($child);
            $children[] = $decoratedChild;
            $childrenChanged = $childrenChanged || $decoratedChild !== $child;
        }

        $attrs = $node->baseAttrs();
        $attrsChanged = false;
        if (isset(self::DIRECTIONAL_BLOCK_TYPES[$node->type])
            && !$this->hasExplicitDirection($node, $attrs)
            && $this->firstStrongDirection($this->visibleText($node)) === 'rtl') {
            $attributes = is_array($attrs['attributes'] ?? null) ? $attrs['attributes'] : [];
            $htmlAttributes = is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [];
            $attributes['dir'] = 'rtl';
            $htmlAttributes['dir'] = 'rtl';
            $attrs['attributes'] = $attributes;
            $attrs['htmlAttributes'] = $htmlAttributes;
            $attrsChanged = true;
        }

        if (!$attrsChanged && !$childrenChanged) {
            return $node;
        }

        return new AstNode(
            $node->type,
            $attrs,
            $children,
            $node->attributeResolver()
        );
    }

    /** @param array<string, mixed> $attrs */
    private function hasExplicitDirection(AstNode $node, array $attrs): bool
    {
        if ($node->hasAttr('dir') || $node->hasAttr('direction')) {
            return true;
        }
        foreach (['attributes', 'htmlAttributes'] as $key) {
            $container = $attrs[$key] ?? null;
            if (is_array($container) && array_key_exists('dir', $container)) {
                return true;
            }
        }

        return array_key_exists('dir', $attrs) || array_key_exists('direction', $attrs);
    }

    private function firstStrongDirection(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            return null;
        }
        foreach ($characters as $character) {
            if (preg_match('/\p{L}/u', $character) !== 1) {
                continue;
            }
            if (preg_match('/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}\p{Nko}\p{Adlam}]/u', $character) === 1) {
                return 'rtl';
            }

            return 'ltr';
        }

        return null;
    }

    private function visibleText(AstNode $node): string
    {
        $text = $node->attr('text');
        if (is_string($text) && trim($text) !== '') {
            return $text;
        }

        $parts = [];
        foreach ($node->children() as $child) {
            $part = $this->visibleText($child);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }
}
