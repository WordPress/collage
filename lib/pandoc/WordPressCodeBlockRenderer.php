<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged renderer and CSS policy for WordPress code blocks. */
final class WordPressCodeBlockRenderer
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function render(AstNode $node): string
    {
        if ((bool) ($this->options['syntaxHighlighterCodeBlocks'] ?? false)) {
            return $this->renderSyntaxHighlighterCodeBlock($node);
        }

        if ((bool) ($this->options['highlightCodeBlocks'] ?? false)) {
            $highlighted = (new SyntaxHighlighter())->highlightCodeBlock(
                $node,
                (string) ($this->options['highlightStyle'] ?? 'pygments')
            );

            return '<!-- wp:html -->'
                . "\n" . '<style data-pandoc-highlight-style="' . $this->esc((string) $highlighted['style']) . '">' . (string) $highlighted['css'] . '</style>'
                . "\n" . (string) $highlighted['html']
                . "\n" . '<!-- /wp:html -->';
        }

        return '<!-- wp:code' . $this->commentAttrs($node) . ' -->'
            . "\n" . $this->renderHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    public function renderHtml(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        $language = $this->language($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';
        $preClasses = $this->preClasses($classes);
        $preAttrs = $this->renderPreAttrs($node);

        return '<pre class="' . $this->esc(implode(' ', $preClasses)) . '"' . $preAttrs . '><code' . $codeAttrs . '>' . $this->esc((string) $node->attr('text', '')) . '</code></pre>';
    }

    public function language(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);
        $hasLanguageAttribute = is_array($attributes)
            && array_filter(
                ['language', 'data-language', 'lang'],
                static fn (string $name): bool => isset($attributes[$name])
                    && trim((string) $attributes[$name]) !== ''
            ) !== [];
        if ((!is_array($classes) || $classes === [])
            && !$hasLanguageAttribute
            && trim((string) $node->attr('info', '')) === '') {
            return '';
        }
        $language = SyntaxHighlighter::languageFromCodeBlock($node);
        $normalized = SyntaxHighlighter::normalizeLanguage($language);

        return $this->sanitizeCodeClass($normalized ?? $language);
    }

    public function renderPreAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'class') {
                continue;
            }

            if ($name === 'id') {
                $sourceId = (string) $value;
                $htmlId = $this->htmlFragmentIdNeedsNormalization($sourceId)
                    ? $this->normalizeHtmlFragmentId($sourceId)
                    : $sourceId;
                if ($htmlId !== '') {
                    $attrs .= ' id="' . $this->esc($htmlId) . '"';
                }
                if ($htmlId !== $sourceId) {
                    $attrs .= ' data-pandoc-source-id="' . $this->esc($sourceId) . '"';
                    $renderedSourceId = true;
                }
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if (!$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    public function safeCssDimension(string $value): string
    {
        $value = trim($value);
        if ($value === '0') {
            return '0';
        }

        if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:%|px|em|rem|in|cm|mm|pt|pc|vw|vh|vmin|vmax)$/i', $value) === 1) {
            return $value;
        }

        return '';
    }

    public function normalizeBlockCssLength(string $value, bool $allowNegative): string
    {
        $value = trim($value);
        if ($value === '0') {
            return '0';
        }
        if (preg_match('/^(-?)(\d+(?:\.\d+)?|\.\d+)(px|pt|pc|in|cm|mm|em|rem|%)$/i', $value, $match) !== 1) {
            return '';
        }

        $negative = $match[1] === '-';
        if ($negative && !$allowNegative) {
            return '';
        }

        $number = (float) $match[2];
        if ($number < 0.0 || $number > 10000.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
        if ($formatted === '') {
            $formatted = '0';
        }

        return ($negative ? '-' : '') . $formatted . strtolower($match[3]);
    }

    public function normalizeBlockLineHeight(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)$/', $value, $match) === 1) {
            $number = (float) $match[1];
            if ($number > 0.0 && $number <= 20.0) {
                return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
            }

            return '';
        }

        return $this->normalizeBlockCssLength($value, false);
    }

    private function renderSyntaxHighlighterCodeBlock(AstNode $node): string
    {
        $language = $this->language($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';
        $preAttrs = $language === '' ? '' : ' data-language="' . $this->esc($language) . '"';
        $preAttrs .= $this->renderPreAttrs($node);

        return '<!-- wp:syntaxhighlighter/code' . $this->commentAttrs($node) . ' -->'
            . "\n" . '<pre class="wp-block-syntaxhighlighter-code"' . $preAttrs . '><code' . $codeAttrs . '>' . $this->esc((string) $node->attr('text', '')) . '</code></pre>'
            . "\n" . '<!-- /wp:syntaxhighlighter/code -->';
    }

    private function commentAttrs(AstNode $node): string
    {
        $language = $this->language($node);

        return $language === ''
            ? ''
            : ' ' . json_encode(['language' => $language], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function preClasses(mixed $classes): array
    {
        $preClasses = ['wp-block-code'];
        if (!is_array($classes)) {
            return $preClasses;
        }

        $sanitized = [];
        foreach ($classes as $class) {
            $sanitized[] = $this->sanitizeCodeClass((string) $class);
        }
        $preserveLanguageClass = array_intersect(['numberLines', 'lineAnchors'], $sanitized) !== [];

        foreach ($sanitized as $index => $class) {
            if ($class === '' || $class === 'literate') {
                continue;
            }
            if ($index === 0 && !$preserveLanguageClass) {
                continue;
            }
            if ($class !== '' && !in_array($class, $preClasses, true)) {
                $preClasses[] = $class;
            }
        }

        return $preClasses;
    }

    /** @return array<string, mixed> */
    private function inlineHtmlAttributes(AstNode $node): array
    {
        return $this->writer->inlineHtmlAttributes($node);
    }

    private function renderCustomStyleDataAttr(string $value): string
    {
        return $this->writer->renderCustomStyleDataAttr($value);
    }

    private function isAllowedBlockHtmlAttr(string $name): bool
    {
        return $this->writer->isAllowedBlockHtmlAttr($name);
    }

    private function sanitizeCodeClass(string $class): string
    {
        return $this->writer->sanitizeCodeClass($class);
    }

    private function htmlFragmentIdNeedsNormalization(string $id): bool
    {
        return $this->writer->htmlFragmentIdNeedsNormalization($id);
    }

    private function normalizeHtmlFragmentId(string $id): string
    {
        return $this->writer->normalizeHtmlFragmentId($id);
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }
}
