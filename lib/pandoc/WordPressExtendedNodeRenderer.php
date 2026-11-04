<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily loaded renderers for node families outside the core WordPress path.
 *
 * Core traversal and shared escaping/attribute policy remain owned by
 * WordPressBlockWriter; this class receives those operations explicitly.
 */
final class WordPressExtendedNodeRenderer
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function renderRawTexInline(AstNode $node): string
    {
        return '<span class="pandoc-raw-tex"' . $this->rawTexDiagnosticAttrs($node) . '>'
            . $this->esc((string) $node->attr('tex', $node->attr('text', '')))
            . '</span>';
    }

    public function renderRawHtmlInline(AstNode $node): string
    {
        return (string) $node->attr('html', '');
    }

    public function renderFigureHtmlBlock(AstNode $node): string
    {
        return $this->isImageOnlyFigure($node)
            ? $this->renderFigureHtml($node)
            : $this->renderMixedFigureHtml($node);
    }

    public function renderRawHtmlBlockHtml(AstNode $node): string
    {
        return (string) $node->attr('html', '');
    }

    public function renderMetadataReviewBlock(AstNode $document): string
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta) || $meta === []) {
            return '';
        }

        $entries = [];
        foreach ($meta as $key => $value) {
            $key = (string) $key;
            if (in_array($key, ['titleInlines', 'authorInlines', 'dateInlines', 'authors'], true)) {
                continue;
            }

            $entries[] = '<li><strong data-pandoc-meta-key="' . $this->esc($key) . '">' . $this->esc($key) . '</strong>: '
                . $this->renderMetadataValue($value) . '</li>';
        }

        if ($entries === []) {
            return '';
        }

        $inner = '<!-- wp:list -->'
            . "\n" . '<ul>' . implode('', $entries) . '</ul>'
            . "\n" . '<!-- /wp:list -->';
        $group = new AstNode('div', ['htmlAttributes' => ['data-pandoc-source' => 'native-meta']]);

        return $this->renderGroupBlock($group, ['pandoc-document-metadata'], $inner);
    }

    private function renderMetadataValue(mixed $value): string
    {
        if ($value instanceof AstNode) {
            return '<span>' . $this->esc($this->metadataNodeText($value)) . '</span>';
        }

        if (is_array($value) && isset($value['type'])) {
            $payload = $value['value'] ?? null;

            return match ((string) $value['type']) {
                'MetaInlines' => '<span>' . $this->esc($this->metadataInlinesText(is_array($payload) ? $payload : [])) . '</span>',
                'MetaBlocks' => $this->renderMetadataBlocks(is_array($payload) ? $payload : []),
                'MetaList' => $this->renderMetadataList(is_array($payload) ? $payload : []),
                'MetaMap' => $this->renderMetadataMap(is_array($payload) ? $payload : []),
                default => '<span>' . $this->esc((string) $payload) . '</span>',
            };
        }

        if (is_array($value)) {
            if ($this->metadataArrayIsAstNodes($value)) {
                return '<span>' . $this->esc($this->metadataNodesText($value)) . '</span>';
            }

            if ($this->metadataArrayIsSequential($value)) {
                return $this->renderMetadataList($value);
            }

            return $this->renderMetadataMap($value);
        }

        if (is_bool($value)) {
            return '<span>' . ($value ? 'true' : 'false') . '</span>';
        }

        return '<span>' . $this->esc((string) $value) . '</span>';
    }

    private function renderMetadataList(array $items): string
    {
        if ($items === []) {
            return '<span>[]</span>';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->renderMetadataValue($item) . '</li>';
        }

        return $html . '</ul>';
    }

    private function renderMetadataMap(array $items): string
    {
        if ($items === []) {
            return '<span>{}</span>';
        }

        $html = '<dl>';
        foreach ($items as $key => $item) {
            $html .= '<dt data-pandoc-meta-key="' . $this->esc((string) $key) . '">' . $this->esc((string) $key) . '</dt>'
                . '<dd>' . $this->renderMetadataValue($item) . '</dd>';
        }

        return $html . '</dl>';
    }

    private function renderMetadataBlocks(array $blocks): string
    {
        if ($blocks === []) {
            return '<span></span>';
        }

        $html = '';
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $text = $this->metadataNodeText($block);
            $html .= '<p>' . $this->esc($text) . '</p>';
        }

        return $html === '' ? '<span></span>' : $html;
    }

    private function metadataInlinesText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                $text .= $this->metadataInlineText($node);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function metadataInlineText(AstNode $node): string
    {
        return match ($node->type) {
            'text', 'code' => (string) $node->attr('text', ''),
            'softbreak', 'linebreak' => ' ',
            'math' => (string) $node->attr('text', ''),
            'raw_html_inline' => (string) $node->attr('html', ''),
            'raw_tex', 'raw_tex_inline' => (string) $node->attr('tex', $node->attr('text', '')),
            'raw_inline' => (string) $node->attr('text', ''),
            'image' => (string) $node->attr('alt', $this->metadataInlinesText($node->children)),
            default => $this->metadataInlinesText($node->children),
        };
    }

    private function metadataNodeText(AstNode $node): string
    {
        return match ($node->type) {
            'paragraph', 'plain', 'term', 'line' => $this->metadataInlinesText($node->children),
            'code_block' => (string) $node->attr('text', ''),
            'raw_html' => (string) $node->attr('html', ''),
            'raw_tex' => (string) $node->attr('tex', ''),
            'raw_block' => (string) $node->attr('text', ''),
            'line_block' => implode(' / ', array_map(fn (AstNode $line): string => $this->metadataNodeText($line), $node->children)),
            default => $this->metadataNodesText($node->children) !== ''
                ? $this->metadataNodesText($node->children)
                : (string) $node->attr('text', ''),
        };
    }

    private function metadataNodesText(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $text = $this->metadataNodeText($node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function metadataArrayIsAstNodes(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function metadataArrayIsSequential(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    public function renderParagraphImageBlock(AstNode $node): string
    {
        return '<!-- wp:image -->'
            . "\n" . '<figure class="wp-block-image">' . $this->renderImageHtml($node) . '</figure>'
            . "\n" . '<!-- /wp:image -->';
    }

    public function tryRenderRawHtmlInlineContainerParagraph(AstNode $open, AstNode $body, AstNode $close): ?string
    {
        if (
            $open->type !== 'raw_html'
            || !in_array($body->type, ['paragraph', 'plain'], true)
            || $close->type !== 'raw_html'
            || $this->shouldSkipEmptyParagraphLikeBlock($body)
        ) {
            return null;
        }

        $openHtml = trim((string) $open->attr('html', ''));
        $closeHtml = trim((string) $close->attr('html', ''));
        if (preg_match('/^<(del|ins|button)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?>$/iu', $openHtml, $match) !== 1) {
            return null;
        }

        $tag = preg_quote((string) $match[1], '/');
        if (preg_match('/^<\/' . $tag . '\s*>$/iu', $closeHtml) !== 1) {
            return null;
        }

        return '<!-- wp:paragraph -->'
            . "\n" . '<p>' . $openHtml . $this->renderInlines($body) . $closeHtml . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
    }

    public function renderRawHtmlBlock(AstNode $node): string
    {
        return '<!-- wp:html -->'
            . "\n" . (string) $node->attr('html', '')
            . "\n" . '<!-- /wp:html -->';
    }

    public function renderRawTexBlock(AstNode $node): string
    {
        return '<!-- wp:code -->'
            . "\n" . $this->renderRawTexBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    public function renderRawFormatBlock(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $rawFamily = MarkdownFormatProfile::rawFamily($format);
        if ($rawFamily === 'html') {
            return '<!-- wp:html -->'
                . "\n" . (string) $node->attr('text', '')
                . "\n" . '<!-- /wp:html -->';
        }

        if ($rawFamily === 'tex') {
            return '<!-- wp:code -->'
                . "\n" . $this->renderRawTexBlockHtml($node)
                . "\n" . '<!-- /wp:code -->';
        }

        return '<!-- wp:code -->'
            . "\n" . $this->renderRawFormatBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    public function renderRawTexBlockHtml(AstNode $node): string
    {
        return '<pre class="wp-block-code"' . $this->rawTexDiagnosticAttrs($node) . '><code class="language-tex">'
            . $this->esc((string) $node->attr('tex', $node->attr('text', '')))
            . '</code></pre>';
    }

    private function rawTexDiagnosticAttrs(AstNode $node): string
    {
        $location = $node->attr('latexSourceLocation', []);
        if (!is_array($location) || !is_string($location['source'] ?? null)) {
            return '';
        }
        $source = trim($location['source']);
        $line = max(0, (int) ($location['line'] ?? 0));
        $column = max(0, (int) ($location['column'] ?? 0));
        if ($source === '') {
            return '';
        }
        $label = $source;
        if ($line > 0) {
            $label .= ':' . $line;
            if ($column > 0) {
                $label .= ':' . $column;
            }
        }
        $command = trim((string) ($location['command'] ?? $location['environment'] ?? ''));
        if ($command !== '') {
            $label .= ' (' . $command . ')';
        }
        $diagnostic = trim((string) $node->attr('latexDiagnostic', ''));
        if ($diagnostic !== '') {
            $label .= ' - ' . $diagnostic;
        }

        $attrs = ' data-pandoc-latex-source="' . $this->esc($source) . '"';
        if ($line > 0) {
            $attrs .= ' data-pandoc-latex-line="' . $line . '"';
        }
        if ($column > 0) {
            $attrs .= ' data-pandoc-latex-column="' . $column . '"';
        }

        return $attrs . ' title="' . $this->esc($label) . '"';
    }

    public function renderRawFormatBlockHtml(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $formatToken = $this->rawFormatToken($format);
        $language = $formatToken === 'openxml' ? 'xml' : $formatToken;

        return '<pre class="wp-block-code pandoc-raw-' . $this->esc($formatToken) . '" data-pandoc-raw-format="' . $this->esc($format) . '"><code class="language-' . $this->esc($language) . '">'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    public function renderRawInlineNode(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $text = (string) $node->attr('text', '');
        $formatToken = $this->rawFormatToken($format);
        $rawFamily = MarkdownFormatProfile::rawFamily($format);

        if ($rawFamily === 'html') {
            return $text;
        }

        if ($rawFamily === 'tex') {
            return '<span class="pandoc-raw-tex"' . $this->rawTexDiagnosticAttrs($node) . '>' . $this->esc($text) . '</span>';
        }

        if (strtolower($format) === 'openxml') {
            $bookmark = $this->parseOpenXmlBookmark($text);
            if ($bookmark !== null) {
                $attrs = ' class="pandoc-openxml-bookmark-' . $this->esc($bookmark['kind']) . '"'
                    . ' data-pandoc-raw-format="openxml"';
                if ($bookmark['id'] !== '') {
                    $attrs .= ' data-pandoc-bookmark-id="' . $this->esc($bookmark['id']) . '"';
                }
                if ($bookmark['name'] !== '') {
                    $attrs .= ' data-pandoc-bookmark-name="' . $this->esc($bookmark['name']) . '"';
                }

                return '<span' . $attrs . '></span>';
            }
        }

        return '<span class="pandoc-raw-' . $this->esc($formatToken) . '" data-pandoc-raw-format="' . $this->esc($format) . '">'
            . $this->esc($text)
            . '</span>';
    }

    private function rawFormatToken(string $format): string
    {
        $token = $this->sanitizeCodeClass(strtolower(str_replace(['.', ':'], '-', $format)));

        return $token === '' ? 'raw' : $token;
    }

    private function parseOpenXmlBookmark(string $xml): ?array
    {
        if (preg_match('/^<w:bookmark(Start|End)\b([^>]*)\/>$/u', trim($xml), $match) !== 1) {
            return null;
        }

        $attrs = $this->parseOpenXmlAttributes($match[2]);

        return [
            'kind' => strtolower($match[1]),
            'id' => (string) ($attrs['id'] ?? ''),
            'name' => (string) ($attrs['name'] ?? ''),
        ];
    }

    private function parseOpenXmlAttributes(string $source): array
    {
        preg_match_all('/\bw:([A-Za-z0-9_.:-]+)="([^"]*)"/u', $source, $matches, PREG_SET_ORDER);
        $attrs = [];
        foreach ($matches as $match) {
            $attrs[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $attrs;
    }

    public function renderFigureBlock(AstNode $node): string
    {
        if (!$this->shouldRenderFigureAsImageBlock($node)) {
            return $this->renderMixedFigureBlock($node);
        }

        return '<!-- wp:image -->'
            . "\n" . $this->renderFigureHtml($node)
            . "\n" . '<!-- /wp:image -->';
    }

    private function renderMixedFigureBlock(AstNode $node): string
    {
        $blocks = $this->renderBlocksAsNativeBlocks($node->children, true);
        $caption = trim((string) $node->attr('caption', ''));
        if ($caption === '') {
            $caption = $this->figureCaptionText($node);
        }
        if ($caption !== '') {
            $blocks .= ($blocks === '' ? '' : "\n\n")
                . '<!-- wp:paragraph -->'
                . "\n" . '<p class="pandoc-figure-caption">' . $this->esc($caption) . '</p>'
                . "\n" . '<!-- /wp:paragraph -->';
        }

        return $this->renderGroupBlock($node, ['pandoc-figure'], $blocks);
    }

    private function shouldRenderFigureAsImageBlock(AstNode $node): bool
    {
        if ($this->isImageOnlyFigure($node)) {
            return true;
        }

        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode || !$this->hasFigureCaptionContent($node)) {
            return false;
        }

        if ((string) $image->attr('alt', '') === '' && $this->figureBodyTextWithoutImages($node) !== '') {
            return true;
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);

        return is_array($htmlAttributes) && $htmlAttributes !== [];
    }

    private function isImageOnlyFigure(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $child = $node->children[0];
        if ($child->type === 'image') {
            return true;
        }

        if (!in_array($child->type, ['plain', 'paragraph'], true) || count($child->children) !== 1) {
            return false;
        }

        return $child->children[0]->type === 'image';
    }

    private function hasFigureCaptionContent(AstNode $node): bool
    {
        if (trim((string) $node->attr('caption', '')) !== '') {
            return true;
        }

        $inlines = $node->attr('captionInlines', null);

        return is_array($inlines) && $inlines !== [];
    }

    public function renderMixedFigureHtml(AstNode $node): string
    {
        $html = '<figure' . $this->renderBlockHtmlAttrs($node) . '>' . $this->renderFigureBlocksAsHtml($node->children);
        $caption = trim((string) $node->attr('caption', ''));
        if ($caption === '') {
            $caption = $this->figureCaptionText($node);
        }
        if ($caption !== '') {
            $html .= '<figcaption>' . $this->esc($caption) . '</figcaption>';
        }

        return $html . '</figure>';
    }

    private function renderFigureBlocksAsHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($this->shouldSkipEmptyParagraphLikeBlock($block)) {
                continue;
            }
            if ($block->type === 'plain') {
                $html .= '<p' . $this->renderBlockHtmlAttrs($block) . '>' . $this->renderInlines($block) . '</p>';
                continue;
            }
            if ($block->type === 'code_block') {
                $html .= $this->renderFigureCodeBlockHtml($block);
                continue;
            }

            $html .= $this->renderBlocksAsHtml([$block]);
        }

        return $html;
    }

    private function renderFigureCodeBlockHtml(AstNode $node): string
    {
        $language = $this->codeBlockLanguage($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';

        return '<pre class="wp-block-code"' . $this->renderCodeBlockPreAttrs($node) . '><code' . $codeAttrs . '>'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    public function renderFigureHtml(AstNode $node): string
    {
        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode) {
            $image = new AstNode('image', [
                'url' => '',
                'alt' => (string) $node->attr('caption', ''),
            ]);
        } else {
            $image = $this->imageWithFigureAltFallback($image, $node);
        }

        $html = '<figure' . $this->renderImageFigureAttrs($node) . '>' . $this->renderImageHtml($image);
        $caption = $this->renderFigureCaption($node, $image);
        if ($caption !== '') {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        }

        return $html . '</figure>';
    }

    private function firstFigureImage(AstNode $node): ?AstNode
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                return $child;
            }

            $nested = $this->firstFigureImage($child);
            if ($nested instanceof AstNode) {
                return $nested;
            }
        }

        return null;
    }

    private function imageWithFigureAltFallback(AstNode $image, AstNode $figure): AstNode
    {
        if ((string) $image->attr('alt', '') !== '') {
            return $image;
        }

        $alt = $this->figureBodyTextWithoutImages($figure);
        if ($alt !== '') {
            return new AstNode('image', array_replace($image->attrs, ['alt' => $alt]), $image->children);
        }

        $alt = $this->figureCaptionText($figure);
        if ($alt === '') {
            return $image;
        }

        $attrs = array_replace($image->attrs, ['alt' => $alt]);
        $sourceAttrs = $attrs['attributes'] ?? [];
        if (!is_array($sourceAttrs)) {
            $sourceAttrs = [];
        }
        if (!isset($sourceAttrs['data-pandoc-alt-source'])) {
            $sourceAttrs['data-pandoc-alt-source'] = 'figure-caption';
        }
        $attrs['attributes'] = $sourceAttrs;

        return new AstNode('image', $attrs, $image->children);
    }

    private function figureBodyTextWithoutImages(AstNode $figure): string
    {
        $parts = [];
        foreach ($figure->children as $child) {
            if ($this->nodeContainsImage($child)) {
                continue;
            }

            $text = $this->metadataNodeText($child);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function figureCaptionText(AstNode $figure): string
    {
        $inlines = $figure->attr('captionInlines', null);
        if (is_array($inlines)) {
            $caption = $this->metadataInlinesText($inlines);
            if ($caption !== '') {
                return $caption;
            }
        }

        return trim((string) $figure->attr('caption', ''));
    }

    private function nodeContainsImage(AstNode $node): bool
    {
        if ($node->type === 'image') {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsImage($child)) {
                return true;
            }
        }

        return false;
    }

    private function renderFigureCaption(AstNode $node, AstNode $image): string
    {
        $inlines = $node->attr('captionInlines', null);
        if (is_array($inlines)) {
            $html = '';
            foreach ($inlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }

                $html .= $this->renderInlineNode($inline);
            }
            if ($html !== '') {
                return $html;
            }
        }

        $caption = (string) $node->attr('caption', $image->attr('alt', ''));

        return $caption === '' ? '' : $this->esc($caption);
    }

    private function renderImageFigureAttrs(AstNode $node): string
    {
        $attrs = $this->renderBlockHtmlAttrsWithClasses($node, ['wp-block-image'], ['class', 'id', 'role', 'title'], $this->imageFigureAttrSkipNames($node));
        $shortCaption = (string) $node->attr('shortCaption', '');
        if ($shortCaption !== '') {
            $attrs .= ' data-pandoc-short-caption="' . $this->esc($shortCaption) . '"';
        }

        $attributes = $node->attr('attributes', []);
        if (
            is_array($attributes)
            && isset($attributes['latex-placement'])
            && !isset($attributes['data-pandoc-latex-placement'])
        ) {
            $attrs .= ' data-pandoc-latex-placement="' . $this->esc((string) $attributes['latex-placement']) . '"';
        }

        return $attrs;
    }

    private function imageFigureAttrSkipNames(AstNode $node): array
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        $attributes = $node->attr('attributes', []);
        if (
            is_array($htmlAttributes)
            && is_array($attributes)
            && array_key_exists('data-review', $htmlAttributes)
            && array_key_exists('review', $attributes)
            && (string) $htmlAttributes['data-review'] === (string) $attributes['review']
        ) {
            return ['data-review'];
        }

        return [];
    }

    public function renderImageHtml(AstNode $node): string
    {
        $attrs = ' src="' . $this->esc((string) $node->attr('url', '')) . '"'
            . ' alt="' . $this->esc((string) $node->attr('alt', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $dimensionAttrs = $sourceAttrs;
        foreach (['width', 'height'] as $dimension) {
            if (!isset($dimensionAttrs[$dimension])) {
                $dimensionValue = trim((string) $node->attr($dimension, ''));
                if ($dimensionValue !== '') {
                    $dimensionAttrs[$dimension] = $dimensionValue;
                }
            }
        }
        $sourceFormat = $this->unsupportedWordPressImageSourceFormat((string) $node->attr('url', ''));
        $attrs .= $this->renderImageDimensionAttrs($dimensionAttrs);
        if ($sourceFormat !== '' && !isset($sourceAttrs['data-pandoc-source-format'])) {
            $attrs .= ' data-pandoc-source-format="' . $this->esc($sourceFormat) . '"';
        }
        foreach ($sourceAttrs as $name => $value) {
            $name = strtolower((string) $name);
            if (
                in_array($name, ['src', 'href', 'alt', 'title', 'width', 'height', 'style', 'data-pandoc-source-format'], true)
                || !$this->isAllowedImageHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return '<img' . $attrs . '/>';
    }

    private function unsupportedWordPressImageSourceFormat(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['emf', 'wmf', 'tif', 'tiff', 'heic', 'heif'], true)) {
            return '';
        }

        return $extension;
    }

    private function renderImageDimensionAttrs(array $sourceAttrs): string
    {
        $attrs = '';
        $styles = [];
        foreach (['width', 'height'] as $dimension) {
            $value = trim((string) ($sourceAttrs[$dimension] ?? ''));
            if ($value === '') {
                continue;
            }

            if (preg_match('/^[1-9][0-9]{0,4}$/', $value) === 1) {
                $attrs .= ' ' . $dimension . '="' . $this->esc((string) (int) $value) . '"';
                continue;
            }

            $attrs .= ' data-pandoc-' . $dimension . '="' . $this->esc($value) . '"';
            $cssDimension = $this->safeCssDimension($value);
            if ($cssDimension !== '') {
                $styles[] = $dimension . ':' . $cssDimension;
            }
        }

        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode('; ', $styles)) . '"';
        }

        return $attrs;
    }

    public function renderCitationInline(AstNode $node): string
    {
        $citations = $this->citationEntries($node);
        $ids = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $attrs = ' class="pandoc-citation"';
        if (count($ids) === 1) {
            $attrs .= ' data-pandoc-citation-id="' . $this->esc($ids[0]) . '"';
        }
        $attrs .= ' data-pandoc-citation-count="' . count($citations) . '"';
        if ($ids !== []) {
            $attrs .= ' data-pandoc-citation-ids="' . $this->esc(json_encode($ids, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
        }

        $payload = json_encode($citations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attrs .= ' data-pandoc-citations="' . $this->esc($payload) . '"';

        $display = $this->renderCitationInlineDisplay($node, $citations, $ids);

        return '<span' . $attrs . '>' . $display . '</span>';
    }

    private function renderCitationInlineDisplay(AstNode $node, array $citations, array $ids): string
    {
        if ($node->type === 'citation_group' && $citations !== []) {
            return $this->esc($this->renderCitationMarkdownDisplay($citations));
        }

        $display = $this->renderInlines($node);
        if ($display !== '') {
            return $display;
        }

        $text = (string) $node->attr('text', '');
        if ($text !== '') {
            return $this->esc($text);
        }

        if ($citations !== []) {
            return $this->esc($this->renderCitationMarkdownDisplay($citations));
        }

        return $ids === []
            ? ''
            : $this->esc('[' . implode('; ', array_map(static fn (string $id): string => '@' . $id, $ids)) . ']');
    }

    public function renderCslDisplayParts(array $parts): string
    {
        $html = '';
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $display = strtolower(trim((string) ($part['display'] ?? '')));
            if (!in_array($display, ['left-margin', 'right-inline', 'indent', 'block'], true)) {
                continue;
            }

            $text = (string) ($part['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $formatting = $part['formatting'] ?? [];
            $html .= '<div'
                . $this->renderCslFormattingAttrs('csl-' . $display, is_array($formatting) ? $formatting : [])
                . '>'
                . $this->esc($text)
                . '</div>';
        }

        return $html === '' ? '' : '<div class="csl-entry">' . $html . '</div>';
    }

    public function renderCslInlineParts(array $parts): string
    {
        $html = '';
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $text = (string) ($part['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $formatting = $part['formatting'] ?? [];
            if (!is_array($formatting) || $formatting === []) {
                $html .= $this->esc($text);
                continue;
            }

            $html .= '<span' . $this->renderCslFormattingAttrs('', $formatting) . '>' . $this->esc($text) . '</span>';
        }

        return $html;
    }

    private function renderCslFormattingAttrs(string $baseClass, array $formatting): string
    {
        $classes = $baseClass === '' ? [] : [$baseClass];
        $styles = [];
        foreach ($formatting as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }

            if ($name === 'fontStyle' && in_array($value, ['normal', 'italic', 'oblique'], true)) {
                $classes[] = 'csl-font-style-' . $value;
                $styles[] = 'font-style:' . $value;
                continue;
            }

            if ($name === 'fontVariant' && in_array($value, ['normal', 'small-caps'], true)) {
                $classes[] = 'csl-font-variant-' . $value;
                $styles[] = 'font-variant:' . $value;
                continue;
            }

            if ($name === 'fontWeight' && in_array($value, ['normal', 'bold', 'light'], true)) {
                $classes[] = 'csl-font-weight-' . $value;
                $styles[] = 'font-weight:' . ($value === 'light' ? '300' : $value);
                continue;
            }

            if ($name === 'textDecoration' && in_array($value, ['none', 'underline'], true)) {
                $classes[] = 'csl-text-decoration-' . $value;
                $styles[] = 'text-decoration:' . $value;
                continue;
            }

            if ($name === 'verticalAlign' && in_array($value, ['baseline', 'sup', 'sub'], true)) {
                $classes[] = 'csl-vertical-align-' . $value;
                $styles[] = 'vertical-align:' . ($value === 'sup' ? 'super' : $value);
            }
        }

        $attrs = $classes === [] ? '' : ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode(';', $styles)) . '"';
        }

        return $attrs;
    }

    private function citationEntries(AstNode $node): array
    {
        if ($node->type === 'citation_group') {
            $entries = [];
            foreach ($node->children as $child) {
                if ($child->type === 'citation') {
                    $entries[] = $this->citationEntryFromNode($child);
                }
            }

            return $entries;
        }

        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = $this->citationEntryFromNode($citation);
                } elseif (is_array($citation)) {
                    $entries[] = $this->citationEntryFromArray($citation);
                }
            }

            return $entries;
        }

        $entry = $this->citationEntryFromNode($node);

        return $entry['id'] === '' && (string) $node->attr('text', '') === '' ? [] : [$entry];
    }

    private function renderCitationMarkdownDisplay(array $citations): string
    {
        if ($citations === []) {
            return '';
        }

        $first = $citations[0];
        if ($first['mode'] === 'author_in_text') {
            $suffix = $first['suffix'];
            $rest = $this->renderCitationEntries(array_slice($citations, 1));
            $inside = $suffix;
            if ($inside !== '' && $rest !== '') {
                $inside .= ';';
            }
            if ($rest !== '') {
                $inside = $this->joinInlinePartsWithSpace($inside, $rest);
            }

            return '@' . $this->renderCitationKey($first['id'])
                . ($inside === '' ? '' : ' [' . $inside . ']');
        }

        return '[' . $this->renderCitationEntries($citations) . ']';
    }

    private function renderCitationEntries(array $citations): string
    {
        $rendered = [];
        foreach ($citations as $citation) {
            $entry = $this->renderCitationEntry($citation);
            if ($entry !== '') {
                $rendered[] = $entry;
            }
        }

        return implode('; ', $rendered);
    }

    private function renderCitationEntry(array $citation): string
    {
        $prefix = $citation['prefix'];
        $suffix = $citation['suffix'];
        $key = ($citation['mode'] === 'suppress_author' ? '-' : '')
            . '@'
            . $this->renderCitationKey($citation['id']);

        if ($suffix !== '') {
            $first = mb_substr($suffix, 0, 1, 'UTF-8');
            $key .= ($first === ' ' || in_array($first, [',', ';', ']', '@'], true))
                ? $suffix
                : ' ' . $suffix;
        }

        return $this->joinInlinePartsWithSpace($prefix, $key);
    }

    private function renderCitationKey(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-]$/u', $id) === 1
            ? $id
            : '{' . strtr($id, [
                '\\' => '\\\\',
                '[' => '\\[',
                ']' => '\\]',
                '}' => '\\}',
            ]) . '}';
    }

    private function joinInlinePartsWithSpace(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $left . ' ' . $right;
    }

    private function citationEntryFromNode(AstNode $node): array
    {
        return $this->citationEntryFromArray([
            'id' => $node->attr('id', ''),
            'mode' => $node->attr('mode', 'normal'),
            'noteNum' => $node->attr('noteNum', $node->attr('citationNoteNum', 1)),
            'hash' => $node->attr('hash', $node->attr('citationHash', 0)),
            'prefix' => $node->attr('prefix', []),
            'suffix' => $node->attr('suffix', []),
        ]);
    }

    private function citationEntryFromArray(array $citation): array
    {
        return [
            'id' => (string) ($citation['id'] ?? $citation['citationId'] ?? ''),
            'mode' => $this->citationModeName($citation['mode'] ?? $citation['citationMode'] ?? 'normal'),
            'noteNum' => (int) ($citation['noteNum'] ?? $citation['citationNoteNum'] ?? 1),
            'hash' => (int) ($citation['hash'] ?? $citation['citationHash'] ?? 0),
            'prefix' => $this->citationAffixText($citation['prefix'] ?? $citation['citationPrefix'] ?? []),
            'suffix' => $this->citationAffixText($citation['suffix'] ?? $citation['citationSuffix'] ?? []),
        ];
    }

    private function citationModeName(mixed $mode): string
    {
        return match (strtolower(str_replace(['-', '_'], '', (string) $mode))) {
            'authorintext' => 'author_in_text',
            'suppressauthor' => 'suppress_author',
            default => 'normal',
        };
    }

    private function citationAffixText(mixed $value): string
    {
        if ($value instanceof AstNode) {
            return trim($this->metadataInlineText($value));
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (!is_array($value)) {
            return '';
        }

        $nodes = [];
        $text = '';
        foreach ($value as $item) {
            if ($item instanceof AstNode) {
                $nodes[] = $item;
                continue;
            }
            if (is_string($item)) {
                $text .= $item;
            }
        }

        if ($nodes !== []) {
            return $this->metadataInlinesText($nodes);
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public function renderNoteReference(AstNode $node): string
    {
        $entry = $this->registerFootnote($node);
        $number = $entry['number'];
        $anchor = $entry['anchor'];
        $label = $entry['label'];
        $labelAttr = $label === '' ? '' : ' data-pandoc-note-label="' . $this->esc($label) . '"';

        return '<sup id="fnref-' . $this->esc($anchor) . '"' . $labelAttr . '><a href="#fn-' . $this->esc($anchor) . '" role="doc-noteref">'
            . $number
            . '</a></sup>';
    }

    public function renderSpanInline(AstNode $node): string
    {
        $classes = $this->spanClasses($node);
        if (in_array('insertion', $classes, true)) {
            return '<ins' . $this->renderTrackedChangeAttrs($node) . '>' . $this->renderInlines($node) . '</ins>';
        }

        if (in_array('deletion', $classes, true)) {
            return '<del' . $this->renderTrackedChangeAttrs($node) . '>' . $this->renderInlines($node) . '</del>';
        }

        if (in_array('paragraph-insertion', $classes, true)) {
            return '<span' . $this->renderParagraphChangeSpanAttrs($node, 'insertion') . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('paragraph-deletion', $classes, true)) {
            return '<span' . $this->renderParagraphChangeSpanAttrs($node, 'deletion') . '>' . $this->renderInlines($node) . '</span>';
        }

        if (
            in_array('comment-start', $classes, true)
            || in_array('comment-end', $classes, true)
        ) {
            return '<span' . $this->renderCommentSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('indexref', $classes, true)) {
            return '<span' . $this->renderIndexReferenceSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('diagram', $classes, true)) {
            return '<span' . $this->renderDiagramSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('anchor', $classes, true) && $node->children === []) {
            return '<span' . $this->renderEmptyAnchorSpanAttrs($node) . '></span>';
        }

        $spanLike = $this->renderSpanLikeInline($node, $classes);
        if ($spanLike !== null) {
            return $spanLike;
        }

        return '<span' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
    }

    private function renderSpanLikeInline(AstNode $node, array $classes): ?string
    {
        $firstSpecial = null;
        foreach ($classes as $index => $class) {
            if ($this->isSpanLikeClass($class)) {
                $firstSpecial = $index;
                break;
            }
        }

        if ($firstSpecial === null) {
            return null;
        }

        $retainedClasses = [];
        foreach (array_slice($classes, $firstSpecial + 1) as $class) {
            if (!$this->isSpanLikeClass($class)) {
                $retainedClasses[] = $class;
            }
        }

        $wrappers = [];
        for ($index = count($classes) - 1; $index >= 0; $index--) {
            if ($classes[$index] === 'smallcaps') {
                $wrappers[] = ['tag' => 'span', 'classes' => ['smallcaps']];
            } elseif ($classes[$index] === 'underline') {
                $wrappers[] = ['tag' => 'u', 'classes' => []];
            }
        }
        foreach ($classes as $class) {
            if ($this->isHtmlSpanLikeElement($class)) {
                $wrappers[] = $class === 'mark'
                    ? ['tag' => 'mark', 'classes' => []]
                    : ['tag' => $class, 'classes' => []];
            }
        }

        if ($wrappers === []) {
            return null;
        }

        $html = $this->renderInlines($node);
        $outerIndex = count($wrappers) - 1;
        foreach ($wrappers as $index => $wrapper) {
            $tag = $wrapper['tag'];
            $wrapperClasses = $wrapper['classes'];
            $attrs = $index === $outerIndex
                ? $this->renderSpanLikeAttrs($node, array_values(array_unique(array_merge($wrapperClasses, $retainedClasses))))
                : $this->renderClassAttr($wrapperClasses);
            $html = '<' . $tag . $attrs . '>' . $html . '</' . $tag . '>';
        }

        return $html;
    }

    private function isSpanLikeClass(string $class): bool
    {
        return $class === 'smallcaps'
            || $class === 'underline'
            || $this->isHtmlSpanLikeElement($class);
    }

    private function isHtmlSpanLikeElement(string $class): bool
    {
        return in_array($class, ['kbd', 'mark', 'dfn', 'abbr'], true);
    }

    private function spanClasses(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return [];
        }

        $normalized = [];
        foreach ($classes as $class) {
            $class = (string) $class;
            if ($class !== '') {
                $normalized[] = $class;
            }
        }

        return $normalized;
    }

    private function renderTrackedChangeAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-change-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-change-date="' . $this->esc($date) . '"';
            $attrs .= ' datetime="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-change-date-status="missing"';
        }

        return $attrs;
    }

    private function renderCommentSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $id = (string) ($sourceAttrs['id'] ?? '');
        if ($id !== '') {
            $attrs .= ' data-pandoc-comment-id="' . $this->esc($id) . '"';
        }

        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-comment-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-comment-date="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-comment-date-status="missing"';
        }

        return $attrs;
    }

    private function renderParagraphChangeSpanAttrs(AstNode $node, string $kind): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $attrs .= ' data-pandoc-paragraph-change="' . $this->esc($kind) . '"';
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-change-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-change-date="' . $this->esc($date) . '"';
            $attrs .= ' datetime="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-change-date-status="missing"';
        }

        return $attrs;
    }

    private function renderIndexReferenceSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        foreach ($sourceAttrs as $name => $value) {
            $name = strtolower((string) $name);
            if (in_array($name, ['class', 'entry', 'crossref', 'yomi', 'bold', 'italic'], true)) {
                continue;
            }
            if (!str_starts_with($name, 'data-docx-')) {
                continue;
            }
            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }
            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        $entry = trim((string) ($sourceAttrs['entry'] ?? ''));
        if ($entry !== '') {
            $attrs .= ' data-pandoc-index-entry="' . $this->esc($entry) . '"';
        }

        return $attrs;
    }

    private function renderDiagramSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderInlineSpanAttrs($node);
        $hasDiagramAttr = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $_value) {
            if (strtolower((string) $name) === 'data-pandoc-diagram') {
                $hasDiagramAttr = true;
                break;
            }
        }

        if (!$hasDiagramAttr) {
            $attrs .= ' data-pandoc-diagram="unsupported-docx-diagram"';
        }

        return $attrs;
    }

    private function renderEmptyAnchorSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderInlineSpanAttrs($node);
        foreach ($this->inlineHtmlAttributes($node) as $name => $_value) {
            if (strtolower((string) $name) === 'data-pandoc-anchor') {
                return $attrs;
            }
        }

        return $attrs . ' data-pandoc-anchor="empty-target"';
    }

    private function renderClassAttr(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        return ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
    }

    public function renderMathInline(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $display = $node->attr('display') === true;
        $class = $display ? 'display' : 'inline';

        if ($this->htmlMathMethod() === 'mathml') {
            return $this->renderMathMLInline($node, $text, $display, $class);
        }

        $open = $display ? '\\[' : '\\(';
        $close = $display ? '\\]' : '\\)';

        return '<span class="math ' . $class . '">'
            . $this->esc($open . $text . $close)
            . '</span>';
    }

    private function renderMathMLInline(AstNode $node, string $text, bool $display, string $class): string
    {
        $mathml = $node->attr('mathml', $node->attr('html', ''));
        if (is_scalar($mathml)) {
            $mathml = trim((string) $mathml);
            if ($this->looksLikeMathMLElement($mathml)) {
                return $this->mathMLWithRequiredAttributes($mathml, $display);
            }
        }

        try {
            return (new MathTexConverter())->texToMathMl($text, $display);
        } catch (\InvalidArgumentException) {
            return '<span class="math ' . $class . '">' . $this->esc($text) . '</span>';
        }
    }

    private function htmlMathMethod(): string
    {
        $value = $this->options['writerHTMLMathMethod']
            ?? $this->options['htmlMathMethod']
            ?? $this->options['mathMethod']
            ?? 'mathjax';

        if (is_array($value)) {
            $value = $value['method'] ?? $value['type'] ?? $value['name'] ?? '';
        }

        return strtolower(str_replace(['-', '_', ' '], '', (string) $value)) === 'mathml'
            ? 'mathml'
            : 'mathjax';
    }

    private function looksLikeMathMLElement(string $mathml): bool
    {
        return preg_match('/^<math(?:\s|>|\/)/i', $mathml) === 1
            && !str_contains($mathml, '<?')
            && stripos($mathml, '<script') === false;
    }

    private function mathMLWithRequiredAttributes(string $mathml, bool $display): string
    {
        return preg_replace_callback('/^<math\b([^>]*)>/i', function (array $match) use ($display): string {
            $tail = $match[1];
            $attrs = rtrim($tail);
            if (preg_match('/\sxmlns\s*=/i', $tail) !== 1) {
                $attrs .= ' xmlns="http://www.w3.org/1998/Math/MathML"';
            }
            if ($display && preg_match('/\sdisplay\s*=/i', $tail) !== 1) {
                $attrs .= ' display="block"';
            }

            return '<math' . $attrs . '>';
        }, $mathml, 1) ?? $mathml;
    }

    public function renderQuotedInline(AstNode $node): string
    {
        if ($node->attr('kind') === 'single') {
            return "\u{2018}" . $this->renderInlines($node) . "\u{2019}";
        }

        return "\u{201C}" . $this->renderInlines($node) . "\u{201D}";
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }

    /**
     * @param list<string> $classes
     */
    private function renderGroupBlock(AstNode $node, array $classes, string $innerBlocks): string
    {
        return $this->writer->renderGroupBlock($node, $classes, $innerBlocks);
    }

    private function shouldSkipEmptyParagraphLikeBlock(AstNode $node): bool
    {
        return $this->writer->shouldSkipEmptyParagraphLikeBlock($node);
    }

    private function renderInlines(AstNode $node): string
    {
        return $this->writer->renderInlines($node);
    }

    private function renderBlockHtmlAttrs(AstNode $node): string
    {
        return $this->writer->renderBlockHtmlAttrs($node);
    }

    /**
     * @param list<string> $baseClasses
     * @param list<string> $priorityNames
     * @param list<string> $skipNames
     */
    private function renderBlockHtmlAttrsWithClasses(
        AstNode $node,
        array $baseClasses,
        array $priorityNames = ['id', 'class', 'lang', 'dir', 'role', 'title'],
        array $skipNames = []
    ): string {
        return $this->writer->renderBlockHtmlAttrsWithClasses(
            $node,
            $baseClasses,
            $priorityNames,
            $skipNames
        );
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsNativeBlocks(array $blocks, bool $wrapPlainBlocks = false): string
    {
        return $this->writer->renderBlocksAsNativeBlocks($blocks, $wrapPlainBlocks);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsHtml(array $blocks, bool $wrapPlainBlocks = false): string
    {
        return $this->writer->renderBlocksAsHtml($blocks, $wrapPlainBlocks);
    }

    private function renderInlineNode(AstNode $node): string
    {
        return $this->writer->renderInlineNode($node);
    }

    private function codeBlockLanguage(AstNode $node): string
    {
        return $this->writer->codeBlockLanguage($node);
    }

    private function renderCodeBlockPreAttrs(AstNode $node): string
    {
        return $this->writer->renderCodeBlockPreAttrs($node);
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineHtmlAttributes(AstNode $node): array
    {
        return $this->writer->inlineHtmlAttributes($node);
    }

    private function isAllowedImageHtmlAttr(string $name): bool
    {
        return $this->writer->isAllowedImageHtmlAttr($name);
    }

    private function sanitizeCodeClass(string $class): string
    {
        return $this->writer->sanitizeCodeClass($class);
    }

    private function isAllowedInlineHtmlAttr(string $name): bool
    {
        return $this->writer->isAllowedInlineHtmlAttr($name);
    }

    private function renderInlineSpanAttrs(AstNode $node): string
    {
        return $this->writer->renderInlineSpanAttrs($node);
    }

    /**
     * @param list<string> $classes
     */
    private function renderSpanLikeAttrs(AstNode $node, array $classes): string
    {
        return $this->writer->renderSpanLikeAttrs($node, $classes);
    }

    /**
     * @return array{number:int, anchor:string, label:string, node:AstNode}
     */
    private function registerFootnote(AstNode $node): array
    {
        return $this->writer->registerFootnote($node);
    }

    private function safeCssDimension(string $value): string
    {
        return $this->writer->safeCssDimension($value);
    }
}
