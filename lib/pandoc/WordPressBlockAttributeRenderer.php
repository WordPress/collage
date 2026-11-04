<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged WordPress block-comment, color, style, and HTML attribute policy. */
final class WordPressBlockAttributeRenderer
{
    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    public function renderHeadingAttrs(AstNode $node): string
    {
        $alignment = $this->blockTextAlignment($node);
        $attrs = $alignment === ''
            ? $this->renderBlockHtmlAttrs($node)
            : $this->renderBlockHtmlAttrsWithClasses($node, ['has-text-align-' . $alignment]);

        return $attrs . $this->blockColorHtmlAttr($node);
    }

    /** @param array<string, mixed> $attrs */
    public function blockComment(string $name, array $attrs = []): string
    {
        if ($attrs === []) {
            return '<!-- wp:' . $name . ' -->';
        }

        return '<!-- wp:' . $name . ' ' . json_encode($attrs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . ' -->';
    }

    public function blockTextAlignment(AstNode $node): string
    {
        $alignment = strtolower(trim((string) $node->attr('align', $node->attr('textAlign', ''))));

        return in_array($alignment, ['left', 'center', 'right', 'justify'], true) ? $alignment : '';
    }

    /** @return array{style?: array{color: array<string, string>}} */
    public function blockColorCommentAttrs(AstNode $node): array
    {
        $color = [];
        $text = $this->blockCssColor((string) $node->attr('textColor', ''));
        if ($text !== '') {
            $color['text'] = $text;
        }
        $background = $this->blockCssColor((string) $node->attr('backgroundColor', ''));
        if ($background !== '') {
            $color['background'] = $background;
        }

        return $color === [] ? [] : ['style' => ['color' => $color]];
    }

    public function blockColorHtmlAttr(AstNode $node): string
    {
        $styles = $this->blockStyleDeclarations((string) ($this->inlineHtmlAttributes($node)['style'] ?? ''));
        $text = $this->blockCssColor((string) $node->attr('textColor', ''));
        if ($text !== '') {
            $styles['color'] = $text;
        }
        $background = $this->blockCssColor((string) $node->attr('backgroundColor', ''));
        if ($background !== '') {
            $styles['background-color'] = $background;
        }

        if ($styles === []) {
            return '';
        }

        $declarations = [];
        foreach ($styles as $property => $value) {
            $declarations[] = $property . ':' . $value;
        }

        return ' style="' . $this->esc(implode('; ', $declarations)) . '"';
    }

    public function blockCssColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtoupper($color) : '';
    }

    /** @return array<string, string> */
    public function blockStyleDeclarations(string $style): array
    {
        $declarations = [];
        foreach (['margin-left', 'margin-right', 'margin-top', 'margin-bottom'] as $property) {
            $length = $this->normalizeBlockCssLength($this->styleDeclarationValue($style, $property), false);
            if ($length !== '') {
                $declarations[$property] = $length;
            }
        }

        $textIndent = $this->normalizeBlockCssLength($this->styleDeclarationValue($style, 'text-indent'), true);
        if ($textIndent !== '') {
            $declarations['text-indent'] = $textIndent;
        }

        $lineHeight = $this->normalizeBlockLineHeight($this->styleDeclarationValue($style, 'line-height'));
        if ($lineHeight !== '') {
            $declarations['line-height'] = $lineHeight;
        }

        $breakBefore = strtolower(trim($this->styleDeclarationValue($style, 'break-before')));
        if (in_array($breakBefore, ['auto', 'avoid', 'page'], true)) {
            $declarations['break-before'] = $breakBefore;
        }

        $pageBreakBefore = strtolower(trim($this->styleDeclarationValue($style, 'page-break-before')));
        if (in_array($pageBreakBefore, ['auto', 'always', 'avoid'], true)) {
            $declarations['page-break-before'] = $pageBreakBefore;
        }

        return $declarations;
    }

    public function renderBlockHtmlAttrs(AstNode $node): string
    {
        $htmlAttributes = [];
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'custom-style') {
                $htmlAttributes['data-pandoc-custom-style'] = (string) $value;
                continue;
            }
            if (!$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }
            $htmlAttributes[$name] = $value;
        }

        $orderedNames = [];
        $priority = array_key_exists('id', $htmlAttributes)
            ? ['id', 'class', 'lang', 'dir', 'role', 'title']
            : ['lang', 'dir', 'role', 'title'];
        foreach ($priority as $name) {
            if (array_key_exists($name, $htmlAttributes)) {
                $orderedNames[] = $name;
            }
        }
        foreach (array_keys($htmlAttributes) as $name) {
            if ($name !== 'class' && !in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }
        if (!array_key_exists('id', $htmlAttributes) && array_key_exists('class', $htmlAttributes)) {
            $orderedNames[] = 'class';
        }

        $attrs = '';
        foreach ($orderedNames as $name) {
            $attrs .= ' ' . $name . '="' . $this->esc((string) $htmlAttributes[$name]) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<string> $baseClasses
     * @param list<string> $priorityNames
     * @param list<string> $skipNames
     */
    public function renderBlockHtmlAttrsWithClasses(
        AstNode $node,
        array $baseClasses,
        array $priorityNames = ['id', 'class', 'lang', 'dir', 'role', 'title'],
        array $skipNames = [],
    ): string {
        $htmlAttributes = [];
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'custom-style') {
                $htmlAttributes['data-pandoc-custom-style'] = (string) $value;
                continue;
            }
            $htmlAttributes[$name] = $value;
        }
        $classes = $baseClasses;
        $existingClass = trim((string) ($htmlAttributes['class'] ?? ''));
        if ($existingClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $existingClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $nodeClasses = $node->attr('classes', []);
        if (is_array($nodeClasses)) {
            foreach ($nodeClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
        if ($classes !== []) {
            $htmlAttributes['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $orderedNames = [];
        foreach ($priorityNames as $name) {
            if (array_key_exists($name, $htmlAttributes)) {
                $orderedNames[] = $name;
            }
        }
        foreach (array_keys($htmlAttributes) as $name) {
            $name = strtolower((string) $name);
            if (!in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }

        $attrs = '';
        foreach ($orderedNames as $name) {
            $value = $htmlAttributes[$name];
            $name = strtolower((string) $name);
            if (in_array($name, $skipNames, true) || !$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }
            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /** @return array<string, mixed> */
    private function inlineHtmlAttributes(AstNode $node): array
    {
        return $this->writer->inlineHtmlAttributes($node);
    }

    private function isAllowedBlockHtmlAttr(string $name): bool
    {
        return $this->writer->isAllowedBlockHtmlAttr($name);
    }

    private function normalizeBlockCssLength(string $value, bool $allowNegative): string
    {
        return $this->writer->normalizeBlockCssLength($value, $allowNegative);
    }

    private function normalizeBlockLineHeight(string $value): string
    {
        return $this->writer->normalizeBlockLineHeight($value);
    }

    private function styleDeclarationValue(string $style, string $property): string
    {
        return $this->writer->styleDeclarationValue($style, $property);
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }
}
