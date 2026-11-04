<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlReader
{
    public const BACKEND_TAGSOUP_PANDOC_PORT = 'tagsoup-pandoc-port';
    public const BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE = 'html-document-markdown-bridge';

    private const MICRODATA_MAX_ITEMS = 32;
    private const MICRODATA_MAX_PROPERTIES_PER_ITEM = 64;
    private const MICRODATA_MAX_VALUE_BYTES = 512;

    private ?MarkdownReader $reader = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    private function markdownReader(): MarkdownReader
    {
        if ($this->reader instanceof MarkdownReader) {
            return $this->reader;
        }

        return $this->reader = new MarkdownReader(array_replace([
            'htmlNativeDivs' => true,
            'htmlReader' => true,
            'htmlRawHtml' => false,
        ], $this->options));
    }

    public function read(string $bytes): AstNode
    {
        $backend = $this->options['htmlReaderBackend'] ?? self::BACKEND_TAGSOUP_PANDOC_PORT;
        if ($backend === self::BACKEND_TAGSOUP_PANDOC_PORT) {
            $document = (new PandocHtmlTagSoupReader($this->options))->read($bytes);

            return $this->repairHtmlLocalFragmentLinks(
                $this->restoreStandaloneTextHtmlFallback($document, $bytes)
            );
        }
        if ($backend !== self::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE) {
            throw new \InvalidArgumentException('Unknown HTML reader backend: ' . (string) $backend);
        }

        $htmlTreeConstructionBackend = self::htmlTreeConstructionBackendForSource($bytes);
        $structuralBytes = self::flattenHtmlPictureContainers($bytes);
        $probeStandaloneFragments = ($this->options['htmlSkipStandaloneFragmentProbes'] ?? false) !== true;
        $standaloneImageChildren = $probeStandaloneFragments && self::sourceMayContainHtmlOpeningTag($structuralBytes, 'img')
            ? self::standaloneImageFragmentChildren($structuralBytes)
            : null;
        if ($standaloneImageChildren !== null) {
            $children = $standaloneImageChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } elseif (
            $probeStandaloneFragments
            && self::sourceMayContainHtmlOpeningTag($structuralBytes, 'progress')
            && ($standaloneProgressChildren = $this->standaloneProgressFragmentChildren($structuralBytes)) !== null
        ) {
            $children = $standaloneProgressChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } elseif (
            $probeStandaloneFragments
            && (self::sourceMayContainHtmlOpeningTag($structuralBytes, 'audio')
                || self::sourceMayContainHtmlOpeningTag($structuralBytes, 'video'))
            && ($standaloneMediaFallbackChildren = $this->standaloneMediaFallbackFragmentChildren($structuralBytes)) !== null
        ) {
            $children = $standaloneMediaFallbackChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } elseif (
            $probeStandaloneFragments
            && self::sourceMayContainHtmlOpeningTag($structuralBytes, 'time')
            && ($standaloneTransparentChildren = $this->standaloneTransparentInlineFragmentChildren($structuralBytes)) !== null
        ) {
            $children = $standaloneTransparentChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } else {
            $readerBytes = self::flattenHtmlTemplateContainers($structuralBytes);
            $readerBytes = self::flattenHtmlFallbackContentContainers($readerBytes);
            $readerBytes = self::flattenHtmlRawTextFallbackContainers($readerBytes);
            $readerBytes = self::flattenHtmlAttributeLessButtonContainers($readerBytes);
            if ($this->shouldFlattenHtmlDetailsSummaryContainers()) {
                $readerBytes = self::flattenHtmlDetailsSummaryContainers($readerBytes);
            }
            $readerBytes = self::flattenHtmlMenuContainers($readerBytes);
            $readerBytes = self::flattenOrphanTableFragmentContainers($readerBytes);
            $segmentedDocument = self::htmlSourceLooksLikeWholeDocument($readerBytes)
                ? null
                : $this->readInlineTopLevelSourceSegments($readerBytes);
            if ($segmentedDocument !== null) {
                [$children, $attrs] = $segmentedDocument;
                $consumedFootnoteContainerCount = 0;
            } else {
                $document = $this->markdownReader()->readHtml($readerBytes);
                [$children, $consumedFootnoteContainerCount] = self::containsAstNodeType($document->children, 'note')
                    && $this->consumeFootnoteContainers()
                    ? self::stripConsumedHtmlFootnoteContainers($document->children)
                    : [$document->children, 0];
                if (self::shouldRestoreImplicitPlainBody($readerBytes)) {
                    $children = self::restoreImplicitPlainBody($children);
                }
                $attrs = $document->attrs;
            }
            $children = $this->restoreImplicitNativeMainPlainBlocks($readerBytes, $children);
            if ($this->stripRawInlineWrappers()) {
                $children = self::stripHtmlRawInlineWrappers(
                    $children,
                    self::standaloneRawInlineWrapperTags($readerBytes)
                );
            }
        }
        $children = self::restoreHtmlTableBodyRowHeadColumns($children);
        $children = $this->restoreHtmlDefinitionPlainBlocks($bytes, $children);
        $meta = $attrs['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $attrs['sourceFormat'] = 'html';
        $attrs['meta'] = array_replace($meta, [
            'sourceFormat' => 'html',
            'reader' => self::class,
            'readerScope' => 'bounded-html-reader',
            'htmlReaderDelegate' => MarkdownReader::class . '::readHtml',
            'htmlTreeConstruction' => $htmlTreeConstructionBackend,
            'htmlNativeDivs' => (bool) (($this->options['htmlNativeDivs'] ?? true)),
            'sourceBytes' => strlen($bytes),
            'sourceSha256' => hash('sha256', $bytes),
            'payloadExposurePolicy' => 'html-dom-text-and-structural-metadata-only',
            'htmlFootnoteContainerPolicy' => 'doc-endnotes-containers-consumed-after-note-resolution',
            'htmlConsumedFootnoteContainerCount' => $consumedFootnoteContainerCount,
        ], $this->microdataMetadata($bytes));

        return $this->repairHtmlLocalFragmentLinks(new AstNode('document', $attrs, $children));
    }

    private function restoreStandaloneTextHtmlFallback(AstNode $document, string $bytes): AstNode
    {
        // A block node proves this cannot be a standalone-text import. Avoid
        // tokenizing the complete source a second time just to reject that
        // fallback on ordinary HTML documents.
        if ($document->children !== [] && !$this->htmlNodesAreStandaloneInlines($document->children)) {
            return $document;
        }
        if (!$this->htmlSourceIsStandaloneText($bytes)) {
            return $document;
        }
        if ($document->children !== []) {
            $text = $this->collapseHtmlImportWhitespace($this->plainTextFromAstNodes($document->children));
            if ($text !== '') {
                return new AstNode($document->type, $document->attrs, [
                    new AstNode('paragraph', ['text' => $text], $document->children),
                ]);
            }
        }
        if ($document->children !== []) {
            return $document;
        }

        $fallback = $this->markdownReader()->read($bytes);
        if ($fallback->children === []) {
            return $document;
        }

        $attrs = $document->attrs;
        $meta = $attrs['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $attrs['meta'] = array_replace($meta, [
            'htmlStandaloneTextFallback' => true,
            'htmlStandaloneTextFallbackReader' => MarkdownReader::class,
        ]);

        return new AstNode($document->type, $attrs, $fallback->children);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function htmlNodesAreStandaloneInlines(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!in_array($node->type, [
                'text',
                'space',
                'softbreak',
                'linebreak',
                'emph',
                'strong',
                'strikeout',
                'superscript',
                'subscript',
                'smallcaps',
                'code',
                'link',
                'image',
                'span',
                'math',
                'raw_html_inline',
            ], true)) {
                return false;
            }
        }

        return $nodes !== [];
    }

    private function htmlSourceIsStandaloneText(string $bytes): bool
    {
        $tokens = (new PandocHtmlTagSoupReader($this->options))->tokenize($bytes);
        $hasText = false;
        foreach ($tokens as $token) {
            if (!$token instanceof TagSoupTag) {
                continue;
            }
            if ($token->type !== TagSoupTag::TEXT) {
                return false;
            }
            if (trim($token->text) !== '') {
                $hasText = true;
            }
        }

        return $hasText;
    }

    private function repairHtmlLocalFragmentLinks(AstNode $document): AstNode
    {
        if (($this->options['htmlRepairPairedLocalFragmentLinks'] ?? true) === false) {
            return $document;
        }

        $targets = [];
        $this->collectHtmlLocalFragmentTargets($document->children, $targets);
        $linksByLabel = [];
        $this->collectHtmlUnresolvedLocalFragmentLinks($document->children, $targets, [], $linksByLabel);
        $repairs = [];
        foreach ($linksByLabel as $links) {
            if (count($links) !== 2) {
                continue;
            }
            $first = $links[0];
            $second = $links[1];
            if ($first['fragment'] === $second['fragment']) {
                continue;
            }
            $repairs[$first['pathKey']] = $second['fragment'];
            $repairs[$second['pathKey']] = $first['fragment'];
        }
        if ($repairs === []) {
            return $document;
        }

        $attrs = $document->attrs;
        $attrs['htmlLocalFragmentLinkRepairCount'] = count($repairs);

        return new AstNode(
            $document->type,
            $attrs,
            $this->applyHtmlLocalFragmentLinkRepairs($document->children, [], $repairs)
        );
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, true> $targets
     */
    private function collectHtmlLocalFragmentTargets(array $nodes, array &$targets): void
    {
        foreach ($nodes as $node) {
            $id = (string) $node->attr('id', '');
            if ($id !== '') {
                $targets[$id] = true;
            }
            foreach (['htmlAttributes', 'attributes'] as $attrName) {
                $attributes = $node->attr($attrName, []);
                if (!is_array($attributes)) {
                    continue;
                }
                foreach (['id', 'name'] as $name) {
                    $value = (string) ($attributes[$name] ?? '');
                    if ($value !== '') {
                        $targets[$value] = true;
                    }
                }
            }
            if ($node->children !== []) {
                $this->collectHtmlLocalFragmentTargets($node->children, $targets);
            }
        }
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, true> $targets
     * @param list<int> $path
     * @param array<string, list<array{pathKey:string, fragment:string}>> $linksByLabel
     */
    private function collectHtmlUnresolvedLocalFragmentLinks(array $nodes, array $targets, array $path, array &$linksByLabel): void
    {
        foreach ($nodes as $index => $node) {
            $nodePath = [...$path, $index];
            if ($node->type === 'link' && (string) $node->attr('id', '') === '') {
                $url = (string) $node->attr('url', '');
                if (str_starts_with($url, '#') && strlen($url) > 1) {
                    $fragment = rawurldecode(substr($url, 1));
                    $label = $this->normalizedHtmlLinkLabel($node->children);
                    if ($fragment !== '' && $label !== '' && !isset($targets[$fragment])) {
                        $linksByLabel[$label][] = [
                            'pathKey' => implode('.', $nodePath),
                            'fragment' => $fragment,
                        ];
                    }
                }
            }
            if ($node->children !== []) {
                $this->collectHtmlUnresolvedLocalFragmentLinks($node->children, $targets, $nodePath, $linksByLabel);
            }
        }
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function normalizedHtmlLinkLabel(array $nodes): string
    {
        $text = $this->collapseHtmlImportWhitespace($this->plainTextFromAstNodes($nodes));
        if ($text === '') {
            return '';
        }

        return mb_strtolower($text, 'UTF-8');
    }

    private function collapseHtmlImportWhitespace(string $text): string
    {
        $result = '';
        $pendingSpace = false;
        foreach (mb_str_split($text) as $char) {
            if ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t" || $char === "\f" || $char === "\v") {
                $pendingSpace = $result !== '';
                continue;
            }
            if ($pendingSpace) {
                $result .= ' ';
                $pendingSpace = false;
            }
            $result .= $char;
        }

        return $result;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainTextFromAstNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= (string) $node->attr('text', '');
            if ($node->children !== []) {
                $text .= $this->plainTextFromAstNodes($node->children);
            }
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     * @param list<int> $path
     * @param array<string, string> $repairs
     * @return list<AstNode>
     */
    private function applyHtmlLocalFragmentLinkRepairs(array $nodes, array $path, array $repairs): array
    {
        $rebuilt = [];
        foreach ($nodes as $index => $node) {
            $nodePath = [...$path, $index];
            $pathKey = implode('.', $nodePath);
            $children = $node->children === []
                ? []
                : $this->applyHtmlLocalFragmentLinkRepairs($node->children, $nodePath, $repairs);
            $attrs = $node->attrs;
            if ($node->type === 'link' && isset($repairs[$pathKey]) && (string) ($attrs['id'] ?? '') === '') {
                $attrs['id'] = $repairs[$pathKey];
                $attrs['htmlLocalFragmentLinkRepair'] = true;
                $htmlAttributes = is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [];
                $htmlAttributes['id'] = $repairs[$pathKey];
                $attrs['htmlAttributes'] = $htmlAttributes;
            }
            $rebuilt[] = new AstNode($node->type, $attrs, $children);
        }

        return $rebuilt;
    }

    private static function htmlTreeConstructionBackendForSource(string $bytes): string
    {
        return self::htmlSourceLooksLikeWholeDocument($bytes)
            ? Html5Dom::htmlDocumentTreeConstructionBackend()
            : Html5Dom::htmlFragmentTreeConstructionBackend($bytes);
    }

    private function consumeFootnoteContainers(): bool
    {
        return ($this->options['htmlConsumeFootnoteContainers'] ?? true) !== false;
    }

    private function stripRawInlineWrappers(): bool
    {
        return ($this->options['htmlStripRawInlineWrappers'] ?? true) !== false;
    }

    private function shouldFlattenHtmlDetailsSummaryContainers(): bool
    {
        return ($this->options['htmlFlattenDetailsSummaryContainers'] ?? true) !== false;
    }

    private static function flattenHtmlPictureContainers(string $bytes): string
    {
        if (stripos($bytes, '<picture') === false) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
            if ($source === null || !self::flattenHtmlPictureElements($source['dom'])) {
                return $bytes;
            }

            return self::serializeHtmlRewriteSource($source) ?? $bytes;
        } catch (\Throwable) {
            return $bytes;
        }
    }

    private static function sourceMayContainHtmlOpeningTag(string $bytes, string $tag): bool
    {
        return stripos($bytes, '<' . $tag) !== false;
    }

    /**
     * @param list<string> $tags
     */
    private static function sourceMayContainAnyHtmlOpeningTag(string $bytes, array $tags): bool
    {
        foreach ($tags as $tag) {
            if (self::sourceMayContainHtmlOpeningTag($bytes, $tag)) {
                return true;
            }
        }

        return false;
    }

    private static function flattenHtmlPictureElements(?\DOMDocument $dom): bool
    {
        if (!$dom instanceof \DOMDocument) {
            return false;
        }

        $changed = false;
        for ($pass = 0; $pass < 8; ++$pass) {
            $pictures = [];
            foreach ($dom->getElementsByTagName('picture') as $picture) {
                if ($picture instanceof \DOMElement) {
                    $pictures[] = $picture;
                }
            }

            if ($pictures === []) {
                return $changed;
            }

            foreach ($pictures as $picture) {
                $parent = $picture->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                self::moveHtmlPictureFallbackChildren($picture, $parent, $picture);
                $parent->removeChild($picture);
                $changed = true;
            }
        }

        return $changed;
    }

    private static function moveHtmlPictureFallbackChildren(\DOMElement $source, \DOMNode $destination, \DOMNode $reference): void
    {
        foreach (iterator_to_array($source->childNodes) as $child) {
            if (!$child instanceof \DOMNode) {
                continue;
            }

            if ($child instanceof \DOMElement && strtolower($child->localName) === 'source') {
                self::moveHtmlPictureFallbackChildren($child, $destination, $reference);
                $source->removeChild($child);
                continue;
            }

            $destination->insertBefore($child, $reference);
        }
    }

    private static function flattenHtmlTemplateContainers(string $bytes): string
    {
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'template')) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
            if ($source === null || !self::flattenHtmlTemplateElements($source['dom'])) {
                return $bytes;
            }

            return self::serializeHtmlRewriteSource($source) ?? $bytes;
        } catch (\Throwable) {
            return $bytes;
        }
    }

    private static function flattenHtmlTemplateElements(?\DOMDocument $dom): bool
    {
        if (!$dom instanceof \DOMDocument) {
            return false;
        }

        $changed = false;
        for ($pass = 0; $pass < 8; ++$pass) {
            $templates = [];
            foreach ($dom->getElementsByTagName('template') as $template) {
                if ($template instanceof \DOMElement) {
                    $templates[] = $template;
                }
            }

            if ($templates === []) {
                return $changed;
            }

            foreach ($templates as $template) {
                $parent = $template->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                $content = $template->textContent;
                $fragment = Html5Dom::parseHtmlFragment($content);
                foreach (iterator_to_array($fragment->childNodes) as $child) {
                    if (!$child instanceof \DOMNode) {
                        continue;
                    }
                    $parent->insertBefore($dom->importNode($child, true), $template);
                }
                $parent->removeChild($template);
                $changed = true;
            }
        }

        return $changed;
    }

    private static function flattenHtmlFallbackContentContainers(string $bytes): string
    {
        if (!self::sourceMayContainAnyHtmlOpeningTag($bytes, ['iframe', 'noscript', 'object'])) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
            if ($source === null || !self::flattenHtmlFallbackContentElements($source['dom'])) {
                return $bytes;
            }

            return self::serializeHtmlRewriteSource($source) ?? $bytes;
        } catch (\Throwable) {
            return $bytes;
        }
    }

    private static function flattenHtmlFallbackContentElements(?\DOMDocument $dom): bool
    {
        if (!$dom instanceof \DOMDocument) {
            return false;
        }

        $changed = false;
        for ($pass = 0; $pass < 8; ++$pass) {
            $elements = [];
            foreach (self::htmlFallbackContentElementNames() as $name) {
                foreach ($dom->getElementsByTagName($name) as $element) {
                    if ($element instanceof \DOMElement && strtolower($element->localName) === $name) {
                        $elements[] = $element;
                    }
                }
            }

            if ($elements === []) {
                return $changed;
            }

            foreach ($elements as $element) {
                $parent = $element->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                $replacementNodes = self::htmlFallbackContentReplacementNodes($dom, $element);
                if ($replacementNodes === null) {
                    continue;
                }

                foreach ($replacementNodes as $replacement) {
                    $parent->insertBefore($replacement, $element);
                }
                $parent->removeChild($element);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private static function htmlFallbackContentElementNames(): array
    {
        return ['iframe', 'noscript', 'object'];
    }

    /**
     * @return list<\DOMNode>|null
     */
    private static function htmlFallbackContentReplacementNodes(\DOMDocument $dom, \DOMElement $element): ?array
    {
        $name = strtolower($element->localName);
        if (in_array($name, ['iframe', 'noscript'], true)) {
            $fragment = Html5Dom::parseHtmlFragment($element->textContent);
            if (
                !self::htmlElementContainsBlockContainer($fragment)
                && self::isTopLevelHtmlBodyChild($element)
            ) {
                return null;
            }

            $nodes = [];
            foreach (iterator_to_array($fragment->childNodes) as $child) {
                if ($child instanceof \DOMNode) {
                    $nodes[] = $dom->importNode($child, true);
                }
            }

            return $nodes;
        }

        if (
            !self::htmlElementContainsBlockContainer($element)
            && self::isTopLevelHtmlBodyChild($element)
        ) {
            return null;
        }

        $nodes = [];
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'param') {
                continue;
            }
            if ($child instanceof \DOMNode) {
                $nodes[] = $child->cloneNode(true);
            }
        }

        return $nodes;
    }

    private static function isTopLevelHtmlBodyChild(\DOMElement $element): bool
    {
        $parent = $element->parentNode;

        return $parent instanceof \DOMElement && strtolower($parent->localName) === 'body';
    }

    private static function flattenHtmlRawTextFallbackContainers(string $bytes): string
    {
        if (!self::sourceMayContainAnyHtmlOpeningTag($bytes, ['noembed', 'noframes', 'plaintext', 'xmp'])) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
            if ($source === null || !self::flattenHtmlRawTextFallbackElements($source['dom'])) {
                return $bytes;
            }

            return self::serializeHtmlRewriteSource($source) ?? $bytes;
        } catch (\Throwable) {
            return $bytes;
        }
    }

    private static function flattenHtmlRawTextFallbackElements(?\DOMDocument $dom): bool
    {
        if (!$dom instanceof \DOMDocument) {
            return false;
        }

        $changed = false;
        foreach (self::htmlRawTextFallbackElementNames() as $name) {
            for ($pass = 0; $pass < 8; ++$pass) {
                $elements = [];
                foreach ($dom->getElementsByTagName($name) as $element) {
                    if ($element instanceof \DOMElement && strtolower($element->localName) === $name) {
                        $elements[] = $element;
                    }
                }

                if ($elements === []) {
                    break;
                }

                foreach ($elements as $element) {
                    $parent = $element->parentNode;
                    if (!$parent instanceof \DOMNode) {
                        continue;
                    }

                    $fragment = Html5Dom::parseHtmlFragment($element->textContent);
                    foreach (iterator_to_array($fragment->childNodes) as $child) {
                        if ($child instanceof \DOMNode) {
                            $parent->insertBefore($dom->importNode($child, true), $element);
                        }
                    }
                    $parent->removeChild($element);
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private static function htmlRawTextFallbackElementNames(): array
    {
        return ['noembed', 'noframes', 'plaintext', 'xmp'];
    }

    private static function flattenHtmlAttributeLessButtonContainers(string $bytes): string
    {
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'button')) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
        } catch (\Throwable) {
            return $bytes;
        }
        if ($source === null || !self::flattenHtmlAttributeLessButtonElements($source['dom'])) {
            return $bytes;
        }

        return self::serializeHtmlRewriteSource($source) ?? $bytes;
    }

    private static function flattenHtmlAttributeLessButtonElements(\DOMDocument $dom): bool
    {
        $changed = false;
        foreach (self::htmlElementsByName($dom, 'button') as $button) {
            if ($button->attributes->length > 0 || !self::htmlElementHasOnlyInlineContent($button)) {
                continue;
            }

            $parent = $button->parentNode;
            if (!$parent instanceof \DOMNode) {
                continue;
            }

            while ($button->firstChild instanceof \DOMNode) {
                $parent->insertBefore($button->firstChild, $button);
            }
            $parent->removeChild($button);
            $changed = true;
        }

        return $changed;
    }

    private static function flattenHtmlDetailsSummaryContainers(string $bytes): string
    {
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'details')) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
        } catch (\Throwable) {
            return $bytes;
        }
        if ($source === null) {
            return $bytes;
        }

        $changed = false;
        foreach (self::htmlElementsByName($source['dom'], 'summary') as $summary) {
            if (!self::htmlElementHasAncestorName($summary, 'details')) {
                continue;
            }

            $parent = $summary->parentNode;
            if (!$parent instanceof \DOMNode) {
                continue;
            }

            $paragraph = $source['dom']->createElement('p');
            while ($summary->firstChild instanceof \DOMNode) {
                $paragraph->appendChild($summary->firstChild);
            }
            $parent->replaceChild($paragraph, $summary);
            $changed = true;
        }

        foreach (self::htmlElementsByName($source['dom'], 'details') as $details) {
            $parent = $details->parentNode;
            if (!$parent instanceof \DOMNode) {
                continue;
            }

            while ($details->firstChild instanceof \DOMNode) {
                $parent->insertBefore($details->firstChild, $details);
            }
            $parent->removeChild($details);
            $changed = true;
        }

        if (!$changed) {
            return $bytes;
        }

        return self::serializeHtmlRewriteSource($source) ?? $bytes;
    }

    private static function flattenHtmlMenuContainers(string $bytes): string
    {
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'menu')) {
            return $bytes;
        }

        try {
            $source = self::parseHtmlRewriteSource($bytes);
        } catch (\Throwable) {
            return $bytes;
        }
        if ($source === null || !self::flattenHtmlMenuElements($source['dom'])) {
            return $bytes;
        }

        return self::serializeHtmlRewriteSource($source) ?? $bytes;
    }

    private static function flattenHtmlMenuElements(\DOMDocument $dom): bool
    {
        $changed = false;
        for ($pass = 0; $pass < 8; ++$pass) {
            $menus = self::htmlElementsByName($dom, 'menu');
            if ($menus === []) {
                return $changed;
            }

            foreach (array_reverse($menus) as $menu) {
                $parent = $menu->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                foreach (self::htmlMenuReplacementNodes($dom, $menu) as $replacement) {
                    $parent->insertBefore($replacement, $menu);
                }
                $parent->removeChild($menu);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @return list<\DOMNode>
     */
    private static function htmlMenuReplacementNodes(\DOMDocument $dom, \DOMElement $menu): array
    {
        $replacementNodes = [];
        foreach (iterator_to_array($menu->childNodes) as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }

            if (!$child instanceof \DOMElement || strtolower($child->localName) !== 'li') {
                $replacementNodes[] = $child->cloneNode(true);
                continue;
            }

            array_push($replacementNodes, ...self::htmlMenuItemBlockNodes($dom, $child));
        }

        return $replacementNodes;
    }

    /**
     * @return list<\DOMNode>
     */
    private static function htmlMenuItemBlockNodes(\DOMDocument $dom, \DOMElement $item): array
    {
        $blocks = [];
        $pendingInlineNodes = [];
        foreach (iterator_to_array($item->childNodes) as $child) {
            if ($child instanceof \DOMElement && self::isHtmlBlockContainerName(strtolower($child->localName))) {
                self::flushHtmlMenuInlineNodes($dom, $pendingInlineNodes, $blocks);
                $blocks[] = $child->cloneNode(true);
                continue;
            }

            $pendingInlineNodes[] = $child->cloneNode(true);
        }

        self::flushHtmlMenuInlineNodes($dom, $pendingInlineNodes, $blocks);

        return $blocks;
    }

    /**
     * @param list<\DOMNode> $pendingInlineNodes
     * @param list<\DOMNode> $blocks
     */
    private static function flushHtmlMenuInlineNodes(\DOMDocument $dom, array &$pendingInlineNodes, array &$blocks): void
    {
        if (!self::htmlNodesContainMeaningfulContent($pendingInlineNodes)) {
            $pendingInlineNodes = [];

            return;
        }

        $paragraph = $dom->createElement('p');
        foreach ($pendingInlineNodes as $node) {
            $paragraph->appendChild($node);
        }
        $blocks[] = $paragraph;
        $pendingInlineNodes = [];
    }

    /**
     * @param list<\DOMNode> $nodes
     */
    private static function htmlNodesContainMeaningfulContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
                if (trim($node->nodeValue ?? '') !== '') {
                    return true;
                }
                continue;
            }

            if ($node instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    private static function flattenOrphanTableFragmentContainers(string $bytes): string
    {
        if (self::htmlSourceLooksLikeWholeDocument($bytes)) {
            return $bytes;
        }

        try {
            $body = Html5Dom::parseHtmlFragment($bytes);
            $fragmentContext = Html5Dom::htmlFragmentTreeConstructionContext($bytes);
        } catch (\Throwable) {
            return $bytes;
        }
        if (
            $fragmentContext !== Html5Dom::HTML_FRAGMENT_CONTEXT_TABLE
            || !self::htmlElementContainsBlockContainer($body)
        ) {
            return $bytes;
        }

        $parts = [];
        $changed = false;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }

            if ($child instanceof \DOMElement && self::isOrphanTableFragmentElementName(strtolower($child->localName))) {
                $blockSources = self::orphanTableFragmentElementBlockSources($child);
                if ($blockSources !== []) {
                    array_push($parts, ...$blockSources);
                }
                $changed = true;
                continue;
            }

            $serialized = self::htmlNodeSource($child);
            if ($serialized !== '') {
                $parts[] = $serialized;
            }
        }

        return $changed && $parts !== [] ? implode("\n", $parts) : $bytes;
    }

    private static function isOrphanTableFragmentElementName(string $name): bool
    {
        return in_array($name, self::orphanTableFragmentElementNames(), true);
    }

    /**
     * @return list<string>
     */
    private static function orphanTableFragmentElementNames(): array
    {
        return ['caption', 'col', 'colgroup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'];
    }

    /**
     * @return list<string>
     */
    private static function orphanTableFragmentElementBlockSources(\DOMElement $element): array
    {
        $name = strtolower($element->localName);
        if (in_array($name, ['col', 'colgroup'], true)) {
            return [];
        }
        if (in_array($name, ['caption', 'td', 'th'], true)) {
            $blockSource = self::orphanTableBlockSource(self::htmlChildNodesSource($element));

            return $blockSource === null ? [] : [$blockSource];
        }

        $sources = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $childName = strtolower($child->localName);
            if (!self::isOrphanTableFragmentElementName($childName)) {
                continue;
            }

            array_push($sources, ...self::orphanTableFragmentElementBlockSources($child));
        }

        return $sources;
    }

    private static function orphanTableBlockSource(string $source): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }
        if (self::htmlSourceContainsBlockContainer($source)) {
            return $source;
        }

        return '<p>' . $source . '</p>';
    }

    /**
     * @return list<\DOMElement>
     */
    private static function htmlElementsByName(\DOMDocument $dom, string $name): array
    {
        $elements = [];
        foreach ($dom->getElementsByTagName($name) as $element) {
            if ($element instanceof \DOMElement && strtolower($element->localName) === $name) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private static function htmlElementHasAncestorName(\DOMElement $element, string $name): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->localName) === $name) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * @return list<AstNode>|null
     */
    private static function standaloneImageFragmentChildren(string $bytes): ?array
    {
        $body = self::parseHtmlFragmentBody($bytes);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $image = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || strtolower($child->localName) !== 'img' || $image instanceof \DOMElement) {
                return null;
            }
            $image = $child;
        }

        if (!$image instanceof \DOMElement) {
            return null;
        }

        $alt = $image->getAttribute('alt');
        $attrs = self::standaloneImageAttrs($image);
        $attrs['url'] = $image->getAttribute('src');
        $attrs['alt'] = $alt;
        $title = $image->getAttribute('title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
        $imageNode = new AstNode('image', $attrs, $children);

        return [new AstNode('plain', ['text' => $alt], [$imageNode])];
    }

    /**
     * @return list<AstNode>|null
     */
    private function standaloneProgressFragmentChildren(string $bytes): ?array
    {
        $body = self::parseHtmlFragmentBody($bytes);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $children = [];
        $pendingInlineSource = '';
        $sawTopLevelProgress = false;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'progress') {
                $this->appendStandalonePlainBlock($children, $pendingInlineSource);
                $pendingInlineSource = '';
                $this->appendStandalonePlainBlock($children, self::htmlChildNodesSource($child));
                $sawTopLevelProgress = true;
                continue;
            }

            if ($child instanceof \DOMElement && !self::isStandaloneProgressInlineSiblingName(strtolower($child->localName))) {
                return null;
            }

            $pendingInlineSource .= self::htmlNodeSource($child);
        }

        if (!$sawTopLevelProgress) {
            return null;
        }

        $this->appendStandalonePlainBlock($children, $pendingInlineSource);

        return $children;
    }

    /**
     * @param list<AstNode> $children
     */
    private function appendStandalonePlainBlock(array &$children, string $inlineSource): void
    {
        $plain = $this->plainBlockFromHtmlInlineSource($inlineSource);
        if ($plain instanceof AstNode) {
            $children[] = $plain;
        }
    }

    private function plainBlockFromHtmlInlineSource(string $inlineSource): ?AstNode
    {
        $inlineSource = trim($inlineSource);
        if ($inlineSource === '') {
            return null;
        }

        $reader = new MarkdownReader(array_replace(
            ['htmlNativeDivs' => true, 'htmlReader' => true],
            $this->options,
            ['htmlRawHtml' => false]
        ));
        $document = $reader->readHtml($inlineSource);
        if (count($document->children) !== 1) {
            return null;
        }

        $block = $document->children[0];
        if ($block->type === 'plain') {
            return $block;
        }
        if ($block->type === 'paragraph') {
            return self::paragraphToPlain($block);
        }

        return null;
    }

    private static function isStandaloneProgressInlineSiblingName(string $name): bool
    {
        return in_array($name, [
            'a',
            'abbr',
            'b',
            'bdi',
            'bdo',
            'br',
            'cite',
            'code',
            'data',
            'dfn',
            'em',
            'i',
            'kbd',
            'mark',
            'meter',
            'q',
            's',
            'samp',
            'small',
            'span',
            'strong',
            'sub',
            'sup',
            'time',
            'tt',
            'u',
            'var',
            'wbr',
        ], true);
    }

    /**
     * @return list<AstNode>|null
     */
    private function standaloneMediaFallbackFragmentChildren(string $bytes): ?array
    {
        $body = self::parseHtmlFragmentBody($bytes);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $children = [];
        $pendingInlineSource = '';
        $sawTopLevelMedia = false;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && self::isHtmlMediaFallbackContainerName(strtolower($child->localName))) {
                $this->appendStandalonePlainBlock($children, $pendingInlineSource);
                $pendingInlineSource = '';
                $this->appendStandalonePlainBlock($children, self::htmlMediaFallbackChildNodesSource($child));
                $sawTopLevelMedia = true;
                continue;
            }

            if ($child instanceof \DOMElement && !self::isStandaloneProgressInlineSiblingName(strtolower($child->localName))) {
                return null;
            }

            $pendingInlineSource .= self::htmlNodeSource($child);
        }

        if (!$sawTopLevelMedia) {
            return null;
        }

        $this->appendStandalonePlainBlock($children, $pendingInlineSource);

        return $children;
    }

    private static function isHtmlMediaFallbackContainerName(string $name): bool
    {
        return in_array($name, ['audio', 'video'], true);
    }

    private static function htmlMediaFallbackChildNodesSource(\DOMElement $element): string
    {
        $source = '';
        foreach ($element->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && in_array(strtolower($child->localName), ['source', 'track'], true)
            ) {
                continue;
            }

            $source .= self::htmlNodeSource($child);
        }

        return $source;
    }

    private static function htmlChildNodesSource(\DOMElement $element): string
    {
        $source = '';
        foreach ($element->childNodes as $child) {
            $source .= self::htmlNodeSource($child);
        }

        return $source;
    }

    private static function htmlNodeSource(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return '';
        }

        $source = $document->saveHTML($node);

        return is_string($source) ? $source : '';
    }

    /**
     * @return list<AstNode>|null
     */
    private function standaloneTransparentInlineFragmentChildren(string $bytes): ?array
    {
        if (self::standaloneTransparentInlineFragmentTag($bytes) !== 'time') {
            return null;
        }

        $reader = new MarkdownReader(array_replace(
            ['htmlNativeDivs' => true, 'htmlReader' => true],
            $this->options,
            ['htmlRawHtml' => false]
        ));
        $document = $reader->readHtml($bytes);
        if (count($document->children) !== 1 || $document->children[0]->type !== 'paragraph') {
            return null;
        }

        return [self::paragraphToPlain($document->children[0])];
    }

    private static function standaloneTransparentInlineFragmentTag(string $bytes): ?string
    {
        $body = self::parseHtmlFragmentBody($bytes);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $element = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || $element instanceof \DOMElement) {
                return null;
            }
            $element = $child;
        }

        if (!$element instanceof \DOMElement) {
            return null;
        }

        $tag = strtolower($element->localName);

        return in_array($tag, ['time'], true) ? $tag : null;
    }

    private static function parseHtmlFragmentBody(string $bytes): ?\DOMElement
    {
        try {
            return Html5Dom::parseHtmlFragment($bytes);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function standaloneImageAttrs(\DOMElement $image): array
    {
        $id = '';
        $classes = [];
        $attributes = [];
        $htmlAttributes = [];

        foreach ($image->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (in_array($name, ['src', 'alt', 'title'], true)) {
                continue;
            }

            $value = trim($attribute->value);
            if ($name === 'id') {
                $id = $value;
                if ($value !== '') {
                    $htmlAttributes['id'] = $value;
                }
                continue;
            }

            if ($name === 'class') {
                $classes = self::spaceSeparatedTokens($value);
                if ($classes !== []) {
                    $htmlAttributes['class'] = implode(' ', $classes);
                }
                continue;
            }

            $key = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
            if ($key === '') {
                continue;
            }

            $attributes[$key] = $value;
            $htmlAttributes[$name] = $value;
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function readInlineTopLevelSourceSegments(string $bytes): ?array
    {
        $segments = self::inlineTopLevelSourceSegments($bytes);
        if ($segments === null) {
            return null;
        }

        $children = [];
        $attrs = [];
        foreach ($segments as $segment) {
            $document = $this->markdownReader()->readHtml($segment);
            array_push($children, ...$document->children);
            if ($attrs === []) {
                $attrs = $document->attrs;
            }
        }

        return [$children, $attrs];
    }

    /**
     * @return list<string>|null
     */
    private static function inlineTopLevelSourceSegments(string $bytes): ?array
    {
        if (!str_contains($bytes, "\n") && !str_contains($bytes, "\r")) {
            return null;
        }

        try {
            $body = Html5Dom::parseHtmlFragment($bytes);
        } catch (\Throwable) {
            return null;
        }

        if (!self::htmlElementHasOnlyInlineTopLevelContent($body)) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $bytes);
        $segments = [];
        foreach (explode("\n", $normalized) as $line) {
            $segment = trim($line);
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return count($segments) > 1 ? $segments : null;
    }

    private static function htmlElementHasOnlyInlineTopLevelContent(\DOMElement $element): bool
    {
        $hasContent = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (trim($child->nodeValue ?? '') !== '') {
                    $hasContent = true;
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (self::isHtmlBlockContainerName(strtolower($child->localName))) {
                return false;
            }
            $hasContent = true;
        }

        return $hasContent;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function shouldRestoreImplicitPlainBody(string $bytes): bool
    {
        $trimmed = ltrim($bytes);
        if (strncasecmp($trimmed, '<!doctype', 9) === 0 || strncasecmp($trimmed, '<html', 5) === 0) {
            return false;
        }

        try {
            if (strncasecmp($trimmed, '<head', 5) === 0 || strncasecmp($trimmed, '<body', 5) === 0) {
                $dom = Html5Dom::parseHtmlDocument('<html>' . $bytes . '</html>');
                $body = $dom->getElementsByTagName('body')->item(0);

                return $body instanceof \DOMElement && self::htmlElementHasOnlyPlainEligibleTopLevelContent($body);
            }

            $body = Html5Dom::parseHtmlFragment($bytes);

            return self::htmlElementHasOnlyPlainEligibleTopLevelContent($body);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function htmlElementHasOnlyPlainEligibleTopLevelContent(\DOMElement $element): bool
    {
        $hasContent = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (trim($child->nodeValue ?? '') !== '') {
                    $hasContent = true;
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (self::isHtmlBlockContainerName(strtolower($child->localName))) {
                return false;
            }
            if (!self::isPlainEligibleInlineElementName(strtolower($child->localName))) {
                return false;
            }
            $hasContent = true;
        }

        return $hasContent;
    }

    private static function isPlainEligibleInlineElementName(string $name): bool
    {
        return in_array($name, [
            'a',
            'abbr',
            'b',
            'bdi',
            'bdo',
            'cite',
            'code',
            'data',
            'del',
            'dfn',
            'em',
            'i',
            'img',
            'ins',
            'kbd',
            'mark',
            'meter',
            'output',
            'q',
            's',
            'samp',
            'select',
            'small',
            'span',
            'strike',
            'strong',
            'sub',
            'sup',
            'time',
            'tt',
            'u',
            'var',
        ], true);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function restoreImplicitPlainBody(array $children): array
    {
        if (count($children) !== 1 || $children[0]->type !== 'paragraph') {
            return $children;
        }

        return [new AstNode('plain', $children[0]->attrs, $children[0]->children)];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function restoreImplicitNativeMainPlainBlocks(string $bytes, array $children): array
    {
        if (($this->options['htmlNativeDivs'] ?? true) !== true) {
            return $children;
        }
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'main')) {
            return $children;
        }

        try {
            $dom = $this->parseHtmlDocumentForPostprocessing($bytes);
        } catch (\Throwable) {
            return $children;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return $children;
        }

        $main = self::firstHtmlElement($body, 'main');
        if (!$main instanceof \DOMElement || !self::htmlElementHasOnlyInlineContent($main)) {
            return $children;
        }

        if (count($children) !== 1) {
            return $children;
        }

        $child = $children[0];
        if ($child->type === 'paragraph') {
            return [self::paragraphToPlain($child)];
        }

        if ($child->type === 'div' && count($child->children) === 1 && $child->children[0]->type === 'paragraph') {
            return [new AstNode('div', $child->attrs, [self::paragraphToPlain($child->children[0])])];
        }

        return $children;
    }

    private static function firstHtmlElement(\DOMElement $root, string $name): ?\DOMElement
    {
        if (strtolower($root->localName) === $name) {
            return $root;
        }

        foreach ($root->getElementsByTagName($name) as $element) {
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private static function htmlElementHasOnlyInlineContent(\DOMElement $element): bool
    {
        $hasContent = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (trim($child->nodeValue ?? '') !== '') {
                    $hasContent = true;
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (self::isHtmlBlockContainerName(strtolower($child->localName))) {
                return false;
            }
            $hasContent = true;
        }

        return $hasContent;
    }

    private static function isHtmlBlockContainerName(string $name): bool
    {
        return in_array($name, [
            'address',
            'article',
            'aside',
            'blockquote',
            'center',
            'details',
            'dialog',
            'dir',
            'div',
            'dl',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'form',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hgroup',
            'hr',
            'main',
            'menu',
            'nav',
            'ol',
            'p',
            'pre',
            'search',
            'section',
            'summary',
            'table',
            'ul',
        ], true);
    }

    private static function paragraphToPlain(AstNode $paragraph): AstNode
    {
        return new AstNode('plain', $paragraph->attrs, $paragraph->children);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function restoreHtmlTableBodyRowHeadColumns(array $children): array
    {
        $restored = [];
        foreach ($children as $child) {
            $originalChildren = $child->children;
            $nestedChildren = self::restoreHtmlTableBodyRowHeadColumns($originalChildren);
            $baseAttrs = $child->baseAttrs();
            $attrs = $baseAttrs;
            if ($child->type === 'table_body' && !array_key_exists('rowHeadColumns', $attrs)) {
                $rowHeadColumns = self::htmlTableBodyRowHeadColumns($nestedChildren);
                if ($rowHeadColumns > 0) {
                    $attrs['rowHeadColumns'] = $rowHeadColumns;
                }
            }

            if ($attrs === $baseAttrs && $nestedChildren === $originalChildren) {
                $restored[] = $child;
                continue;
            }

            $resolver = $child->attributeResolver();
            if ($resolver instanceof LazyTableGeometryAttributes) {
                $restored[] = new AstNode($child->type, $attrs, $nestedChildren, $resolver->forRebuiltNode());
                continue;
            }

            $restored[] = new AstNode($child->type, $attrs, $nestedChildren);
        }

        return $restored;
    }

    /**
     * @param list<AstNode> $rows
     */
    private static function htmlTableBodyRowHeadColumns(array $rows): int
    {
        $rowCounts = [];
        $activeRowspans = [];

        foreach ($rows as $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $rowSlots = [];
            $nextActiveRowspans = [];
            foreach ($activeRowspans as $column => $cover) {
                $remaining = max(0, (int) ($cover['remaining'] ?? 0));
                if ($remaining <= 0) {
                    continue;
                }

                $rowSlots[(int) $column] = (bool) ($cover['header'] ?? false);
                if ($remaining > 1) {
                    $nextActiveRowspans[(int) $column] = [
                        'remaining' => $remaining - 1,
                        'header' => (bool) ($cover['header'] ?? false),
                    ];
                }
            }

            $column = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                while (array_key_exists($column, $rowSlots)) {
                    ++$column;
                }

                $colspan = self::positiveTableSpanAttr($cell, 'colspan');
                $rowspan = self::positiveTableSpanAttr($cell, 'rowspan');
                $header = (bool) ($cell->attrs['header'] ?? false);
                for ($coveredColumn = $column; $coveredColumn < $column + $colspan; ++$coveredColumn) {
                    $rowSlots[$coveredColumn] = $header;
                    if ($rowspan > 1) {
                        $nextActiveRowspans[$coveredColumn] = [
                            'remaining' => $rowspan - 1,
                            'header' => $header,
                        ];
                    }
                }

                $column += $colspan;
            }

            $leadingHeaderColumns = 0;
            while (($rowSlots[$leadingHeaderColumns] ?? false) === true) {
                ++$leadingHeaderColumns;
            }
            $rowCounts[] = $leadingHeaderColumns;
            $activeRowspans = $nextActiveRowspans;
        }

        return $rowCounts === [] ? 0 : min($rowCounts);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function restoreHtmlDefinitionPlainBlocks(string $bytes, array $children): array
    {
        if (!self::sourceMayContainHtmlOpeningTag($bytes, 'dd')) {
            return $children;
        }

        $plainDefinitionFlags = $this->htmlDefinitionPlainBlockFlags($bytes);
        if ($plainDefinitionFlags === []) {
            return $children;
        }

        $index = 0;

        return self::restoreHtmlDefinitionPlainBlockChildren($children, $plainDefinitionFlags, $index);
    }

    /**
     * @return list<bool>
     */
    private function htmlDefinitionPlainBlockFlags(string $bytes): array
    {
        try {
            $dom = $this->parseHtmlDocumentForPostprocessing($bytes);
        } catch (\Throwable) {
            return [];
        }

        $flags = [];
        foreach ($dom->getElementsByTagName('dd') as $element) {
            if (!$element instanceof \DOMElement || strtolower($element->localName) !== 'dd') {
                continue;
            }

            $flags[] = !self::htmlElementHasDefinitionBlockChild($element);
        }

        return $flags;
    }

    private function parseHtmlDocumentForPostprocessing(string $bytes): \DOMDocument
    {
        if (($this->options['htmlSourcePrevalidated'] ?? false) === true) {
            return Html5Dom::parsePrevalidatedHtmlDocument($bytes);
        }

        return Html5Dom::parseHtmlDocument($bytes);
    }

    private static function htmlElementHasDefinitionBlockChild(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && self::isHtmlDefinitionBlockChildName(strtolower($child->localName))) {
                return true;
            }
        }

        return false;
    }

    private static function isHtmlDefinitionBlockChildName(string $name): bool
    {
        return in_array($name, [
            'address',
            'article',
            'aside',
            'blockquote',
            'center',
            'dialog',
            'dir',
            'div',
            'dl',
            'fieldset',
            'figure',
            'footer',
            'form',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hgroup',
            'hr',
            'main',
            'menu',
            'nav',
            'ol',
            'p',
            'pre',
            'script',
            'search',
            'section',
            'style',
            'summary',
            'table',
            'textarea',
            'ul',
        ], true);
    }

    /**
     * @param list<AstNode> $children
     * @param list<bool> $plainDefinitionFlags
     * @return list<AstNode>
     */
    private static function restoreHtmlDefinitionPlainBlockChildren(array $children, array $plainDefinitionFlags, int &$index): array
    {
        $restored = [];
        foreach ($children as $child) {
            if ($child->type !== 'definition') {
                $nestedChildren = self::restoreHtmlDefinitionPlainBlockChildren($child->children, $plainDefinitionFlags, $index);
                $restored[] = new AstNode($child->type, $child->attrs, $nestedChildren);
                continue;
            }

            $shouldBePlain = $plainDefinitionFlags[$index] ?? false;
            ++$index;
            $nestedChildren = self::restoreHtmlDefinitionPlainBlockChildren($child->children, $plainDefinitionFlags, $index);
            if ($shouldBePlain && count($nestedChildren) === 1 && $nestedChildren[0]->type === 'paragraph') {
                $paragraph = $nestedChildren[0];
                $nestedChildren = [new AstNode('plain', $paragraph->attrs, $paragraph->children)];
            }

            $restored[] = new AstNode($child->type, $child->attrs, $nestedChildren);
        }

        return $restored;
    }

    private static function positiveTableSpanAttr(AstNode $node, string $name): int
    {
        $value = $node->attrs[$name] ?? 1;
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 1;
        }

        return max(1, (int) $value);
    }

    /**
     * @param list<AstNode> $children
     * @param list<string> $extraWrapperTags
     * @return list<AstNode>
     */
    private static function stripHtmlRawInlineWrappers(array $children, array $extraWrapperTags = []): array
    {
        $stripped = [];
        $activeMediaWrapperDepth = 0;
        foreach ($children as $child) {
            $rawHtml = self::htmlRawInlineSource($child);
            $wrapperName = $rawHtml === null ? null : self::htmlRawInlineWrapperSourceName($rawHtml, $extraWrapperTags);
            if ($wrapperName !== null && self::isHtmlMediaFallbackContainerName($wrapperName)) {
                if (str_starts_with(ltrim($rawHtml), '</')) {
                    $activeMediaWrapperDepth = max(0, $activeMediaWrapperDepth - 1);
                } else {
                    ++$activeMediaWrapperDepth;
                }
                continue;
            }
            if (
                $activeMediaWrapperDepth > 0
                && $rawHtml !== null
                && self::isHtmlMediaRawInlineAuxiliaryName(
                    self::htmlRawInlineWrapperSourceName($rawHtml, ['source', 'track'])
                )
            ) {
                continue;
            }
            if (self::isHtmlRawInlineWrapper($child, $extraWrapperTags)) {
                continue;
            }

            $stripped[] = new AstNode(
                $child->type,
                self::stripHtmlRawInlineWrapperAttrs($child->attrs, $extraWrapperTags),
                self::stripHtmlRawInlineWrappers($child->children, $extraWrapperTags)
            );
        }

        return $stripped;
    }

    private static function htmlRawInlineSource(AstNode $node): ?string
    {
        if ($node->type !== 'raw_html_inline') {
            return null;
        }

        $html = trim((string) ($node->attrs['html'] ?? $node->attrs['text'] ?? ''));

        return $html === '' ? null : $html;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function isHtmlRawInlineWrapper(AstNode $node, array $extraWrapperTags = []): bool
    {
        if ($node->type !== 'raw_html_inline') {
            return false;
        }

        $html = trim((string) ($node->attrs['html'] ?? $node->attrs['text'] ?? ''));

        return self::isHtmlRawInlineWrapperSource($html, $extraWrapperTags);
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<string> $extraWrapperTags
     * @return array<string, mixed>
     */
    private static function stripHtmlRawInlineWrapperAttrs(array $attrs, array $extraWrapperTags = []): array
    {
        foreach ($attrs as $key => $value) {
            $attrs[$key] = $key === 'text' && is_string($value)
                ? self::stripHtmlRawInlineWrapperText($value, $extraWrapperTags)
                : self::stripHtmlRawInlineWrapperValue($value, $extraWrapperTags);
        }

        return $attrs;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function stripHtmlRawInlineWrapperText(string $text, array $extraWrapperTags = []): string
    {
        if (!str_contains($text, '<')) {
            return $text;
        }

        try {
            $body = Html5Dom::parseHtmlFragment($text);
        } catch (\Throwable) {
            return $text;
        }

        return self::unwrapHtmlRawInlineWrapperElements($body, $extraWrapperTags)
            ? Html5Dom::serializeHtmlChildren($body)
            : $text;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function unwrapHtmlRawInlineWrapperElements(\DOMElement $root, array $extraWrapperTags = []): bool
    {
        $changed = false;
        do {
            $unwrapped = false;
            foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
                if (
                    $element instanceof \DOMElement
                    && self::isHtmlMediaRawInlineAuxiliaryName(strtolower($element->localName))
                    && self::htmlElementHasMediaFallbackContainerAncestor($element)
                ) {
                    $parent = $element->parentNode;
                    if ($parent instanceof \DOMNode) {
                        $parent->removeChild($element);
                        $changed = true;
                        $unwrapped = true;
                        break;
                    }
                }
            }
            if ($unwrapped) {
                continue;
            }

            foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
                if (
                    !$element instanceof \DOMElement
                    || !in_array(strtolower($element->localName), self::htmlRawInlineWrapperTagNames($extraWrapperTags), true)
                ) {
                    continue;
                }

                $parent = $element->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                while ($element->firstChild instanceof \DOMNode) {
                    $parent->insertBefore($element->firstChild, $element);
                }
                $parent->removeChild($element);
                $changed = true;
                $unwrapped = true;
                break;
            }
        } while ($unwrapped);

        return $changed;
    }

    private static function isHtmlMediaRawInlineAuxiliaryName(?string $name): bool
    {
        return $name !== null && in_array($name, ['source', 'track'], true);
    }

    private static function htmlElementHasMediaFallbackContainerAncestor(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (self::isHtmlMediaFallbackContainerName(strtolower($parent->localName))) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function stripHtmlRawInlineWrapperValue(mixed $value, array $extraWrapperTags = []): mixed
    {
        if ($value instanceof AstNode) {
            if (self::isHtmlRawInlineWrapper($value, $extraWrapperTags)) {
                return null;
            }

            return new AstNode(
                $value->type,
                self::stripHtmlRawInlineWrapperAttrs($value->attrs, $extraWrapperTags),
                self::stripHtmlRawInlineWrappers($value->children, $extraWrapperTags)
            );
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $stripped = [];
            foreach ($value as $child) {
                if ($child instanceof AstNode && self::isHtmlRawInlineWrapper($child, $extraWrapperTags)) {
                    continue;
                }

                $stripped[] = self::stripHtmlRawInlineWrapperValue($child, $extraWrapperTags);
            }

            return $stripped;
        }

        foreach ($value as $key => $child) {
            $value[$key] = self::stripHtmlRawInlineWrapperValue($child, $extraWrapperTags);
        }

        return $value;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function isHtmlRawInlineWrapperSource(string $html, array $extraWrapperTags = []): bool
    {
        $name = self::htmlRawInlineWrapperSourceName($html, $extraWrapperTags);

        return $name !== null && in_array($name, self::htmlRawInlineWrapperTagNames($extraWrapperTags), true);
    }

    /**
     * @return list<string>
     */
    private static function standaloneRawInlineWrapperTags(string $bytes): array
    {
        return self::standaloneElementName($bytes) === 'cite'
            ? ['cite']
            : [];
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function htmlRawInlineWrapperSourceName(string $html, array $extraWrapperTags = []): ?string
    {
        $html = trim($html);
        if ($html === '' || $html[0] !== '<') {
            return null;
        }

        if (str_starts_with($html, '</')) {
            return self::htmlClosingRawInlineWrapperSourceName($html, $extraWrapperTags);
        }

        $body = self::parseHtmlFragmentBody($html);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $element = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || $element instanceof \DOMElement) {
                return null;
            }
            $element = $child;
        }

        return $element instanceof \DOMElement && trim($element->textContent) === ''
            ? strtolower($element->localName)
            : null;
    }

    /**
     * @param list<string> $extraWrapperTags
     */
    private static function htmlClosingRawInlineWrapperSourceName(string $html, array $extraWrapperTags = []): ?string
    {
        foreach (self::htmlRawInlineWrapperTagNames($extraWrapperTags) as $name) {
            try {
                $body = Html5Dom::parseHtmlFragment(
                    '<' . $name . ' data-pandoc-wrapper-probe="1">x'
                    . $html
                    . '<span id="pandoc-wrapper-tail"></span>'
                );
            } catch (\Throwable) {
                continue;
            }

            $probe = Html5Dom::firstChildElement($body, $name);
            $tail = null;
            foreach ($body->getElementsByTagName('span') as $span) {
                if ($span instanceof \DOMElement && $span->getAttribute('id') === 'pandoc-wrapper-tail') {
                    $tail = $span;
                    break;
                }
            }

            if (
                $probe instanceof \DOMElement
                && $tail instanceof \DOMElement
                && $probe->parentNode === $body
                && $tail->parentNode === $body
                && $probe->nextSibling === $tail
            ) {
                return $name;
            }
        }

        return null;
    }
    /**
     * @param list<string> $extraWrapperTags
     * @return list<string>
     */
    private static function htmlRawInlineWrapperTagNames(array $extraWrapperTags = []): array
    {
        $tags = ['audio', 'data', 'meter', 'progress', 'time', 'video'];
        foreach ($extraWrapperTags as $tag) {
            $normalized = strtolower($tag);
            if (self::isSafeHtmlTagName($normalized) && !in_array($normalized, $tags, true)) {
                $tags[] = $normalized;
            }
        }

        return $tags;
    }

    private static function isSafeHtmlTagName(string $name): bool
    {
        if ($name === '' || !self::isAsciiLowerAlpha($name[0])) {
            return false;
        }

        $length = strlen($name);
        for ($offset = 1; $offset < $length; $offset++) {
            $char = $name[$offset];
            if (!self::isAsciiLowerAlpha($char) && !ctype_digit($char) && $char !== '-') {
                return false;
            }
        }

        return true;
    }

    private static function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }

    /**
     * @return array{dom:\DOMDocument, root:\DOMElement, fragment:bool}|null
     */
    private static function parseHtmlRewriteSource(string $bytes): ?array
    {
        if (self::htmlSourceLooksLikeWholeDocument($bytes)) {
            $dom = Html5Dom::parseHtmlDocument($bytes);
            $root = $dom->documentElement;

            return $root instanceof \DOMElement
                ? ['dom' => $dom, 'root' => $root, 'fragment' => false]
                : null;
        }

        $root = Html5Dom::parseHtmlFragment($bytes);
        $dom = $root->ownerDocument;

        return $dom instanceof \DOMDocument
            ? ['dom' => $dom, 'root' => $root, 'fragment' => true]
            : null;
    }

    /**
     * @param array{dom:\DOMDocument, root:\DOMElement, fragment:bool} $source
     */
    private static function serializeHtmlRewriteSource(array $source): ?string
    {
        if ($source['fragment']) {
            return Html5Dom::serializeHtmlChildren($source['root']);
        }

        $html = $source['dom']->saveHTML($source['root']);

        return is_string($html) ? $html : null;
    }

    private static function htmlSourceLooksLikeWholeDocument(string $bytes): bool
    {
        $trimmed = ltrim($bytes);

        return strncasecmp($trimmed, '<!doctype', 9) === 0
            || strncasecmp($trimmed, '<html', 5) === 0
            || strncasecmp($trimmed, '<head', 5) === 0
            || strncasecmp($trimmed, '<body', 5) === 0;
    }

    private static function htmlSourceContainsBlockContainer(string $source): bool
    {
        try {
            return self::htmlElementContainsBlockContainer(Html5Dom::parseHtmlFragment($source));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function htmlElementContainsBlockContainer(\DOMElement $element): bool
    {
        foreach (self::htmlElementAndDescendants($element) as $candidate) {
            if (self::isHtmlBlockContainerName(strtolower($candidate->localName))) {
                return true;
            }
        }

        return false;
    }

    private static function htmlElementContainsElementName(\DOMElement $element, string $name): bool
    {
        return self::htmlElementContainsAnyElementName($element, [$name]);
    }

    /**
     * @param list<string> $names
     */
    private static function htmlElementContainsAnyElementName(\DOMElement $element, array $names): bool
    {
        $lookup = array_fill_keys($names, true);
        foreach (self::htmlElementAndDescendants($element) as $candidate) {
            if (isset($lookup[strtolower($candidate->localName)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function htmlElementAndDescendants(\DOMElement $element): array
    {
        $elements = [$element];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }

    private static function standaloneElementName(string $bytes): ?string
    {
        $body = self::parseHtmlFragmentBody($bytes);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $element = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || $element instanceof \DOMElement) {
                return null;
            }
            $element = $child;
        }

        return $element instanceof \DOMElement ? strtolower($element->localName) : null;
    }

    /**
     * @param list<AstNode> $children
     */
    private static function containsAstNodeType(array $children, string $type): bool
    {
        foreach ($children as $child) {
            if ($child->type === $type || self::containsAstNodeType($child->children, $type)) {
                return true;
            }

            foreach ($child->baseAttrs() as $value) {
                if (self::attributeValueContainsAstNodeType($value, $type)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function attributeValueContainsAstNodeType(mixed $value, string $type): bool
    {
        if ($value instanceof AstNode) {
            return $value->type === $type
                || self::containsAstNodeType($value->children, $type)
                || self::attributeValueContainsAstNodeType($value->attrs, $type);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $child) {
            if (self::attributeValueContainsAstNodeType($child, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $children
     * @return array{0: list<AstNode>, 1: int}
     */
    private static function stripConsumedHtmlFootnoteContainers(array $children): array
    {
        $removed = 0;

        return [self::stripConsumedHtmlFootnoteContainerChildren($children, $removed), $removed];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function stripConsumedHtmlFootnoteContainerChildren(array $children, int &$removed): array
    {
        $stripped = [];
        foreach ($children as $child) {
            if (self::isConsumedHtmlFootnoteContainerNode($child)) {
                $removed++;
                continue;
            }

            $nestedRemovedBefore = $removed;
            $nestedChildren = self::stripConsumedHtmlFootnoteContainerChildren($child->children, $removed);
            if ($removed !== $nestedRemovedBefore) {
                $child = new AstNode($child->type, $child->attrs, $nestedChildren);
            }

            $stripped[] = $child;
        }

        return $stripped;
    }

    private static function isConsumedHtmlFootnoteContainerNode(AstNode $node): bool
    {
        if ($node->type !== 'div') {
            return false;
        }

        if (self::htmlNodeAttributeValue($node, 'role') === 'doc-endnotes') {
            return true;
        }

        return in_array(self::htmlNodeSemanticType($node), ['footnotes', 'rearnotes'], true)
            || self::htmlNodeHasClass($node, 'footnotes');
    }

    private static function htmlNodeSemanticType(AstNode $node): string
    {
        $type = self::htmlNodeAttributeValue($node, 'type');
        if ($type !== '') {
            return $type;
        }

        return self::htmlNodeAttributeValue($node, 'epub:type');
    }

    private static function htmlNodeAttributeValue(AstNode $node, string $name): string
    {
        foreach (['htmlAttributes', 'attributes'] as $attributeSet) {
            $attributes = $node->attrs[$attributeSet] ?? [];
            if (!is_array($attributes) || !array_key_exists($name, $attributes)) {
                continue;
            }

            $value = $attributes[$name];
            if (is_scalar($value)) {
                return strtolower(trim((string) $value));
            }
        }

        return '';
    }

    private static function htmlNodeHasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attrs['classes'] ?? [];
        if (is_array($classes) && in_array($class, array_map('strval', $classes), true)) {
            return true;
        }

        $classAttribute = self::htmlNodeAttributeValue($node, 'class');
        if ($classAttribute === '') {
            return false;
        }

        $normalized = str_replace(["\t", "\n", "\r", "\f"], ' ', $classAttribute);
        $tokens = array_values(array_filter(explode(' ', $normalized), static fn (string $token): bool => $token !== ''));

        return in_array($class, $tokens, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function microdataMetadata(string $bytes): array
    {
        $base = [
            'htmlMicrodataReviewPolicy' => 'html-microdata-metadata-only',
            'htmlMicrodataItemLimit' => self::MICRODATA_MAX_ITEMS,
            'htmlMicrodataPropertyLimitPerItem' => self::MICRODATA_MAX_PROPERTIES_PER_ITEM,
            'htmlMicrodataValueByteLimit' => self::MICRODATA_MAX_VALUE_BYTES,
        ];

        // An HTML document cannot expose a microdata item without an
        // `itemscope` attribute. EPUB's prevalidated XHTML path can skip a
        // second complete DOM parse when that marker is absent.
        if (
            ($this->options['htmlSkipEmptyMicrodataMetadata'] ?? false) === true
            && !Html5Dom::sourceMayContainMicrodataItemScope($bytes)
        ) {
            return $this->emptyMicrodataMetadata($base);
        }

        try {
            $dom = Html5Dom::parseHtmlDocument($bytes);
        } catch (\Throwable) {
            return $base + [
                'htmlMicrodataParseStatus' => 'unavailable',
                'htmlMicrodataItemCount' => 0,
                'htmlMicrodataReportedItemCount' => 0,
                'htmlMicrodataTopLevelItemCount' => 0,
                'htmlMicrodataPropertyCount' => 0,
                'htmlMicrodataPropertyNames' => [],
                'htmlMicrodataItems' => [],
                'htmlMicrodataTopLevelItemIndexes' => [],
                'htmlMicrodataDiagnostics' => ['html-microdata-dom-parse-failed'],
            ];
        }

        $idIndex = self::microdataElementIdIndex($dom);
        $baseHref = self::htmlDocumentBaseHref($dom);
        $itemElements = self::microdataItemElements($dom);
        $items = [];
        $topLevelIndexes = [];
        $globalPropertyNames = [];
        $globalPropertyCount = 0;
        $diagnostics = [];
        $reportedItemCount = min(count($itemElements), self::MICRODATA_MAX_ITEMS);

        if (count($itemElements) > self::MICRODATA_MAX_ITEMS) {
            $diagnostics[] = 'html-microdata-item-limit-exceeded';
        }

        foreach (array_slice($itemElements, 0, self::MICRODATA_MAX_ITEMS) as $index => $element) {
            [$item, $itemDiagnostics] = self::microdataItemSummary($element, $idIndex, $baseHref);
            $items[] = $item;
            array_push($diagnostics, ...$itemDiagnostics);

            if (!self::hasAncestorItemScope($element)) {
                $topLevelIndexes[] = $index;
            }

            $globalPropertyCount += (int) $item['propertyCount'];
            foreach ($item['propertyNames'] as $name) {
                if (!in_array($name, $globalPropertyNames, true)) {
                    $globalPropertyNames[] = $name;
                }
            }
        }

        return $base + [
            'htmlMicrodataParseStatus' => 'parsed',
            'htmlMicrodataItemCount' => count($itemElements),
            'htmlMicrodataReportedItemCount' => $reportedItemCount,
            'htmlMicrodataTopLevelItemCount' => count($topLevelIndexes),
            'htmlMicrodataPropertyCount' => $globalPropertyCount,
            'htmlMicrodataPropertyNames' => $globalPropertyNames,
            'htmlMicrodataItems' => $items,
            'htmlMicrodataTopLevelItemIndexes' => $topLevelIndexes,
            'htmlMicrodataDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function emptyMicrodataMetadata(array $base): array
    {
        return $base + [
            'htmlMicrodataParseStatus' => 'parsed',
            'htmlMicrodataItemCount' => 0,
            'htmlMicrodataReportedItemCount' => 0,
            'htmlMicrodataTopLevelItemCount' => 0,
            'htmlMicrodataPropertyCount' => 0,
            'htmlMicrodataPropertyNames' => [],
            'htmlMicrodataItems' => [],
            'htmlMicrodataTopLevelItemIndexes' => [],
            'htmlMicrodataDiagnostics' => [],
        ];
    }

    /**
     * @return array<string, \DOMElement>
     */
    private static function microdataElementIdIndex(\DOMDocument $dom): array
    {
        $index = [];
        foreach (self::documentElements($dom) as $element) {
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                if ($id !== '' && !isset($index[$id])) {
                    $index[$id] = $element;
                }
            }
        }

        return $index;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function microdataItemElements(\DOMDocument $dom): array
    {
        return array_values(array_filter(
            self::documentElements($dom),
            static fn (\DOMElement $element): bool => $element->hasAttribute('itemscope')
        ));
    }

    /**
     * @return list<\DOMElement>
     */
    private static function documentElements(\DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $elements = [$root];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param array<string, \DOMElement> $idIndex
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private static function microdataItemSummary(\DOMElement $element, array $idIndex, ?string $baseHref): array
    {
        $properties = [];
        $seenPropertyElements = [];
        $diagnostics = [];
        foreach ($element->childNodes as $child) {
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }

        $itemrefIds = self::spaceSeparatedTokens($element->getAttribute('itemref'));
        $missingItemrefIds = [];
        foreach ($itemrefIds as $id) {
            if (!isset($idIndex[$id])) {
                $missingItemrefIds[] = $id;
                $diagnostics[] = 'missing-itemref:' . $id;
                continue;
            }

            self::collectMicrodataProperties($idIndex[$id], $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }

        $properties = array_slice($properties, 0, self::MICRODATA_MAX_PROPERTIES_PER_ITEM);
        $propertyNameCounts = self::microdataPropertyNameCounts($properties);

        $summary = [
            'microdataReviewPolicy' => 'html-microdata-metadata-only',
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itemTypes' => self::spaceSeparatedTokens($element->getAttribute('itemtype')),
            'itemId' => $element->hasAttribute('itemid') ? $element->getAttribute('itemid') : null,
            'itemrefIds' => $itemrefIds,
            'missingItemrefIds' => $missingItemrefIds,
            'propertyCount' => count($properties),
            'propertyNames' => array_keys($propertyNameCounts),
            'propertyNameCounts' => $propertyNameCounts,
            'properties' => $properties,
        ];

        if ($element->hasAttribute('id')) {
            $summary['elementId'] = $element->getAttribute('id');
        }
        if (count($seenPropertyElements) > self::MICRODATA_MAX_PROPERTIES_PER_ITEM) {
            $diagnostics[] = 'html-microdata-property-limit-exceeded';
        }

        return [$summary, $diagnostics];
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @param array<string, true> $seenPropertyElements
     * @param list<string> $diagnostics
     */
    private static function collectMicrodataProperties(
        \DOMNode $node,
        array &$properties,
        array &$seenPropertyElements,
        array &$diagnostics,
        ?string $baseHref
    ): void {
        if (!$node instanceof \DOMElement) {
            return;
        }

        $hasItemScope = $node->hasAttribute('itemscope');
        $hasItemProp = $node->hasAttribute('itemprop');
        if ($hasItemProp) {
            $propertyKey = self::microdataElementPath($node);
            if (!isset($seenPropertyElements[$propertyKey])) {
                $seenPropertyElements[$propertyKey] = true;
                if (count($properties) < self::MICRODATA_MAX_PROPERTIES_PER_ITEM) {
                    $properties[] = self::microdataPropertySummary($node, $baseHref);
                } else {
                    $diagnostics[] = 'html-microdata-property-limit-exceeded';
                }
            }
            if ($hasItemScope) {
                return;
            }
        } elseif ($hasItemScope) {
            return;
        }

        foreach ($node->childNodes as $child) {
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function microdataPropertySummary(\DOMElement $element, ?string $baseHref): array
    {
        [$value, $valueSource, $valueType] = self::microdataPropertyValue($element, $baseHref);
        $summary = [
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itempropRaw' => $element->getAttribute('itemprop'),
            'names' => self::spaceSeparatedTokens($element->getAttribute('itemprop')),
            'value' => self::boundedMicrodataValue($value),
            'valueSource' => $valueSource,
            'valueType' => $valueType,
        ];

        if ($element->hasAttribute('id')) {
            $summary['elementId'] = $element->getAttribute('id');
        }
        if ($element->hasAttribute('itemscope')) {
            $summary['item'] = self::microdataItemReference($element);
        }

        return $summary;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function microdataPropertyValue(\DOMElement $element, ?string $baseHref): array
    {
        if ($element->hasAttribute('itemscope')) {
            return [Html5Dom::normalizedText($element), 'item', 'item'];
        }

        $name = XmlHtmlDom::htmlElementName($element);
        if ($name === 'meta') {
            return [$element->getAttribute('content'), 'content', 'string'];
        }
        if (in_array($name, ['audio', 'embed', 'iframe', 'img', 'source', 'track', 'video'], true)) {
            return [self::resolveHtmlUrl($element->getAttribute('src'), $baseHref), 'src', 'url'];
        }
        if (in_array($name, ['a', 'area', 'link'], true)) {
            return [self::resolveHtmlUrl($element->getAttribute('href'), $baseHref), 'href', 'url'];
        }
        if ($name === 'object') {
            return [self::resolveHtmlUrl($element->getAttribute('data'), $baseHref), 'data', 'url'];
        }
        if ($name === 'data' || $name === 'meter') {
            return [$element->getAttribute('value'), 'value', 'string'];
        }
        if ($name === 'time' && $element->hasAttribute('datetime')) {
            return [$element->getAttribute('datetime'), 'datetime', 'datetime'];
        }

        return [Html5Dom::normalizedText($element), 'text', 'string'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function microdataItemReference(\DOMElement $element): array
    {
        $reference = [
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itemTypes' => self::spaceSeparatedTokens($element->getAttribute('itemtype')),
            'itemId' => $element->hasAttribute('itemid') ? $element->getAttribute('itemid') : null,
            'text' => self::boundedMicrodataValue(Html5Dom::normalizedText($element)),
        ];

        if ($element->hasAttribute('id')) {
            $reference['elementId'] = $element->getAttribute('id');
        }

        return $reference;
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @return array<string, int>
     */
    private static function microdataPropertyNameCounts(array $properties): array
    {
        $counts = [];
        foreach ($properties as $property) {
            foreach ($property['names'] as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private static function spaceSeparatedTokens(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $tokens = [];
        $length = strlen($value);
        $offset = 0;
        while ($offset < $length) {
            while ($offset < $length && ctype_space($value[$offset])) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }

            $start = $offset;
            while ($offset < $length && !ctype_space($value[$offset])) {
                ++$offset;
            }
            $tokens[] = substr($value, $start, $offset - $start);
        }

        return $tokens;
    }

    private static function hasAncestorItemScope(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->hasAttribute('itemscope')) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function microdataElementPath(\DOMElement $element): string
    {
        $segments = [];
        $node = $element;
        while ($node instanceof \DOMElement) {
            $index = 1;
            $sibling = $node->previousSibling;
            while ($sibling instanceof \DOMNode) {
                if ($sibling instanceof \DOMElement) {
                    $index++;
                }
                $sibling = $sibling->previousSibling;
            }
            $segments[] = XmlHtmlDom::htmlElementName($node) . '[' . $index . ']';
            $parent = $node->parentNode;
            $node = $parent instanceof \DOMElement ? $parent : null;
        }

        return implode('/', array_reverse($segments));
    }

    private static function boundedMicrodataValue(string $value): string
    {
        if (strlen($value) <= self::MICRODATA_MAX_VALUE_BYTES) {
            return $value;
        }

        return substr($value, 0, self::MICRODATA_MAX_VALUE_BYTES);
    }

    private static function htmlDocumentBaseHref(\DOMDocument $dom): ?string
    {
        foreach ($dom->getElementsByTagName('base') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href !== '') {
                return $href;
            }
        }

        return null;
    }

    private static function resolveHtmlUrl(string $url, ?string $baseHref): string
    {
        return HtmlResourceUrlResolver::resolve($url, $baseHref) ?? $url;
    }
}
