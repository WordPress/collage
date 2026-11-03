<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Staged renderer for inline nodes and their shared HTML attribute policy.
 *
 * Keeping this policy outside WordPressBlockWriter bounds the largest PHP
 * source unit compiled after a PDF AST has already occupied the process.
 */
final class WordPressInlineRenderer
{
    /** @var list<string> */
    private const SAFE_GLOBAL_HTML_ATTRIBUTES = [
        'autocapitalize',
        'autocorrect',
        'enterkeyhint',
        'exportparts',
        'inputmode',
        'part',
        'slot',
        'spellcheck',
        'virtualkeyboardpolicy',
        'writingsuggestions',
    ];

    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    public function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'emph',
            'strong',
            'small_caps',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'softbreak',
            'linebreak',
            'span',
            'quoted',
            'math',
            'raw_tex',
            'raw_tex_inline',
            'raw_html_inline',
            'raw_inline',
            'code',
            'link',
            'image',
            'note',
            'citation',
        ], true);
    }

    public function styleDeclarationValue(string $style, string $property): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) === $property) {
                return trim($value);
            }
        }

        return '';
    }

    public function styleDeclarationColor(string $style, string $property): string
    {
        return $this->normalizeCssColor($this->styleDeclarationValue($style, $property));
    }

    public function normalizeCssColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2]);
        }
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1]);
        }
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $match) === 1) {
            $channels = [(int) $match[1], (int) $match[2], (int) $match[3]];
            foreach ($channels as $channel) {
                if ($channel < 0 || $channel > 255) {
                    return '';
                }
            }

            return 'rgb(' . implode(', ', array_map(static fn (int $channel): string => (string) $channel, $channels)) . ')';
        }

        $name = strtolower($value);

        return in_array($name, [
            'aqua', 'black', 'blue', 'fuchsia', 'gray', 'green', 'grey', 'lime', 'maroon', 'navy',
            'olive', 'orange', 'purple', 'red', 'silver', 'teal', 'transparent', 'white', 'yellow',
        ], true) ? $name : '';
    }

    public function renderInlines(AstNode $node): string
    {
        if ($node->children === []) {
            return $this->esc((string) $node->attr('text', ''));
        }

        return $this->renderInlineNodes($node->children);
    }

    /**
     * @param list<AstNode> $nodes
     */
    public function renderInlineNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $child) {
            $html .= $this->renderInlineNode($child);
        }

        return $html;
    }

    /**
     * @param list<AstNode> $nodes
     */
    public function renderInlineNodesWithoutLeadingTaskGlyph(array $nodes): string
    {
        $html = '';
        $stripGlyph = true;
        $stripFollowingSpace = false;

        foreach ($nodes as $node) {
            if ($stripGlyph && $node->type === 'text') {
                $text = (string) $node->attr('text', '');
                $stripped = preg_replace('/^(?:\x{2610}|\x{2612})/u', '', $text, 1);
                if ($stripped !== null && $stripped !== $text) {
                    $stripGlyph = false;
                    $stripFollowingSpace = $stripped === '';
                    if ($stripped !== '') {
                        $html .= $this->esc(ltrim($stripped));
                    }
                    continue;
                }
            }

            if ($stripFollowingSpace && $node->type === 'text') {
                $text = ltrim((string) $node->attr('text', ''));
                $stripFollowingSpace = false;
                if ($text === '') {
                    continue;
                }

                $html .= $this->esc($text);
                continue;
            }

            if ($stripFollowingSpace && $node->type === 'space') {
                $stripFollowingSpace = false;
                continue;
            }

            $stripGlyph = false;
            $stripFollowingSpace = false;
            $html .= $this->renderInlineNode($node);
        }

        return $html;
    }

    public function renderInlineNode(AstNode $node): string
    {
        $rendered = $node->attr('rendered', null);
        if (
            ($node->type === 'citation' || $node->type === 'citation_group')
            && is_scalar($rendered)
            && (string) $rendered !== ''
        ) {
            $inlineParts = $node->attr('cslInlineParts', []);
            if (is_array($inlineParts)) {
                $inlineHtml = $this->extendedRenderer()->renderCslInlineParts($inlineParts);
                if ($inlineHtml !== '') {
                    return $inlineHtml;
                }
            }

            return $this->esc((string) $rendered);
        }

        return match ($node->type) {
            'text' => $this->esc((string) $node->attr('text', '')),
            'space' => ' ',
            'emph' => '<em>' . $this->renderInlines($node) . '</em>',
            'strong' => '<strong>' . $this->renderInlines($node) . '</strong>',
            'small_caps' => '<span style="font-variant:small-caps">' . $this->renderInlines($node) . '</span>',
            'underline' => '<u' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderInlines($node) . '</u>',
            'strikeout' => '<del>' . $this->renderInlines($node) . '</del>',
            'superscript' => '<sup>' . $this->renderInlines($node) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node) . '</sub>',
            'softbreak' => "\n",
            'linebreak' => '<br/>',
            'span' => $this->extendedRenderer()->renderSpanInline($node),
            'quoted' => $this->extendedRenderer()->renderQuotedInline($node),
            'math' => $this->extendedRenderer()->renderMathInline($node),
            'raw_tex', 'raw_tex_inline' => $this->extendedRenderer()->renderRawTexInline($node),
            'raw_html_inline' => $this->extendedRenderer()->renderRawHtmlInline($node),
            'raw_inline' => $this->extendedRenderer()->renderRawInlineNode($node),
            'code' => '<code' . $this->renderInlineCodeAttrs($node) . '>' . $this->esc((string) $node->attr('text', '')) . '</code>',
            'link' => $this->renderLinkInline($node),
            'image' => $this->extendedRenderer()->renderImageHtml($node),
            'note' => $this->extendedRenderer()->renderNoteReference($node),
            'citation', 'citation_group' => $this->extendedRenderer()->renderCitationInline($node),
            default => $this->renderInlines($node),
        };
    }

    public function renderInlineSpanAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
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

            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<string> $classes
     */
    public function renderSpanLikeAttrs(AstNode $node, array $classes): string
    {
        $attrs = '';
        $renderedSourceId = false;
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
        if ($id !== '') {
            $htmlId = $this->htmlFragmentIdNeedsNormalization($id)
                ? $this->normalizeHtmlFragmentId($id)
                : $id;
            if ($htmlId !== '') {
                $attrs .= ' id="' . $this->esc($htmlId) . '"';
            }
            if ($htmlId !== $id) {
                $attrs .= ' data-pandoc-source-id="' . $this->esc($id) . '"';
                $renderedSourceId = true;
            }
        }

        $attrs .= $this->renderClassAttr($classes);

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id' || $name === 'class') {
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    public function renderDivAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
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

    public function renderCustomStyleDataAttr(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        return ' data-pandoc-custom-style="' . $this->esc($style) . '"';
    }

    /**
     * @return array<string, mixed>
     */
    public function inlineHtmlAttributes(AstNode $node): array
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $id = (string) $node->attr('id', '');
        if ($id !== '' && !isset($htmlAttributes['id'])) {
            $htmlAttributes['id'] = $id;
        }

        if (!isset($htmlAttributes['class'])) {
            $classes = $node->attr('classes', []);
            if (is_array($classes) && $classes !== []) {
                $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                if ($class !== '') {
                    $htmlAttributes['class'] = $class;
                }
            }
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                if (isset($htmlAttributes['data-' . $name])) {
                    continue;
                }
                if (!isset($htmlAttributes[$name])) {
                    $htmlAttributes[$name] = $value;
                }
            }
        }

        return $htmlAttributes;
    }

    public function isAllowedInlineHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['cite', 'class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'xml:lang'], true);
    }

    public function isAllowedBlockHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'xml:lang'], true);
    }

    public function isAllowedImageHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'decoding', 'dir', 'fetchpriority', 'id', 'lang', 'loading', 'sizes', 'srcset', 'xml:lang'], true);
    }

    private function renderLinkInline(AstNode $node): string
    {
        return '<a' . $this->renderLinkAttrs($node) . '>'
            . $this->renderInlineNodes($this->linksAsSpans($node->children))
            . '</a>';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function linksAsSpans(array $nodes): array
    {
        $mapped = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->linksAsSpans($node->children);
            if ($node->type !== 'link') {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $attrs = $node->attrs;
            unset($attrs['url'], $attrs['href'], $attrs['title']);
            $attributes = $attrs['attributes'] ?? [];
            if (is_array($attributes)) {
                unset($attributes['href'], $attributes['title']);
                if ($attributes === []) {
                    unset($attrs['attributes']);
                } else {
                    $attrs['attributes'] = $attributes;
                }
            }

            $mapped[] = new AstNode('span', $attrs, $children);
        }

        return $mapped;
    }

    private function renderLinkAttrs(AstNode $node): string
    {
        $sourceUrl = (string) $node->attr('url', '');
        $url = $this->normalizeInternalFragmentUrl($sourceUrl);
        $attrs = ' href="' . $this->esc($url) . '"';
        if ($url !== $sourceUrl) {
            $attrs .= ' data-pandoc-source-href="' . $this->esc($sourceUrl) . '"';
        }
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if (
            count($htmlAttributes) === 1
            && isset($htmlAttributes['class'])
            && in_array((string) $htmlAttributes['class'], ['uri', 'email'], true)
        ) {
            $htmlAttributes = [];
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (
                $name === 'href'
                || ($name === 'title' && $title !== '')
                || ($name === 'data-pandoc-source-href' && $url !== $sourceUrl)
                || !$this->isAllowedInlineHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function inlineStyleAttribute(string $style): string
    {
        $declarations = $this->inlineStyleDeclarations($style);

        return $declarations === [] ? '' : implode('; ', $declarations);
    }

    /** @return list<string> */
    private function inlineStyleDeclarations(string $style): array
    {
        $declarations = [];

        $color = $this->styleDeclarationColor($style, 'color');
        if ($color !== '') {
            $declarations[] = 'color:' . $color;
        }

        $backgroundColor = $this->styleDeclarationColor($style, 'background-color');
        if ($backgroundColor !== '') {
            $declarations[] = 'background-color:' . $backgroundColor;
        }

        $fontSize = $this->normalizeInlineFontSize($this->styleDeclarationValue($style, 'font-size'));
        if ($fontSize !== '') {
            $declarations[] = 'font-size:' . $fontSize;
        }

        $fontVariant = strtolower(trim($this->styleDeclarationValue($style, 'font-variant')));
        if ($fontVariant === 'small-caps') {
            $declarations[] = 'font-variant:small-caps';
        }

        $textDecoration = $this->normalizeInlineTextDecoration($this->styleDeclarationValue($style, 'text-decoration'));
        if ($textDecoration !== '') {
            $declarations[] = 'text-decoration:' . $textDecoration;
        }

        $textDecorationLine = $this->normalizeInlineTextDecorationLine($this->styleDeclarationValue($style, 'text-decoration-line'));
        if ($textDecorationLine !== '') {
            $declarations[] = 'text-decoration-line:' . $textDecorationLine;
        }

        $textDecorationColor = $this->styleDeclarationColor($style, 'text-decoration-color');
        if ($textDecorationColor !== '') {
            $declarations[] = 'text-decoration-color:' . $textDecorationColor;
        }

        return $declarations;
    }

    private function normalizeInlineFontSize(string $value): string
    {
        $value = trim($value);
        $keyword = strtolower($value);
        if (in_array($keyword, ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'smaller', 'larger'], true)) {
            return $keyword;
        }
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|pt|pc|in|cm|mm|em|rem|%)$/i', $value, $match) !== 1) {
            return '';
        }

        $number = (float) $match[1];
        if ($number <= 0.0 || $number > 10000.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . strtolower($match[2]);
    }

    private function normalizeInlineTextDecoration(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $lineValues = [];
        $style = '';
        $color = '';
        foreach ($tokens as $token) {
            $line = $this->normalizeInlineTextDecorationLine($token);
            if ($line !== '') {
                $lineValues[] = $line;
                continue;
            }

            if ($style === '' && in_array($token, ['solid', 'double', 'dotted', 'dashed', 'wavy'], true)) {
                $style = $token;
                continue;
            }

            if ($color === '') {
                $candidate = $this->normalizeCssColor($token);
                if ($candidate !== '') {
                    $color = $candidate;
                    continue;
                }
            }

            return '';
        }

        $lineValues = array_values(array_unique($lineValues));
        $parts = array_values(array_filter([implode(' ', $lineValues), $style, $color], static fn (string $part): bool => $part !== ''));

        return $parts === [] ? '' : implode(' ', $parts);
    }

    private function normalizeInlineTextDecorationLine(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $lines = [];
        foreach ($tokens as $token) {
            if (!in_array($token, ['none', 'underline', 'overline', 'line-through'], true)) {
                return '';
            }
            $lines[] = $token;
        }

        if (in_array('none', $lines, true) && count(array_unique($lines)) > 1) {
            return '';
        }

        return implode(' ', array_values(array_unique($lines)));
    }

    private function normalizeInternalFragmentUrl(string $url): string
    {
        if (!str_starts_with($url, '#')) {
            return $url;
        }

        $fragment = substr($url, 1);
        if (!$this->htmlFragmentIdNeedsNormalization($fragment)) {
            return $url;
        }

        return '#' . $this->normalizeHtmlFragmentId($fragment);
    }

    public function htmlFragmentIdNeedsNormalization(string $id): bool
    {
        return preg_match('/\s/u', $id) === 1;
    }

    public function normalizeHtmlFragmentId(string $id): string
    {
        $normalized = trim($id);
        $normalized = preg_replace('/\s+/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized === '' ? 'pandoc-anchor-' . substr(sha1($id), 0, 8) : $normalized;
    }

    private function renderInlineCodeAttrs(AstNode $node): string
    {
        return $this->renderInlineSpanAttrs($node);
    }

    /** @param list<string> $classes */
    private function renderClassAttr(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        return ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
    }

    public function isAllowedSafeGlobalHtmlAttr(string $name): bool
    {
        return in_array($name, self::SAFE_GLOBAL_HTML_ATTRIBUTES, true);
    }

    private function extendedRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->writer->extendedRenderer();
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }
}
