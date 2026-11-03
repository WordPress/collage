<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5Dom
{
    public const HTML_TREE_CONSTRUCTION_HTML_DOCUMENT = 'Dom\\HTMLDocument';
    public const HTML_FRAGMENT_CONTEXT_BODY = 'html-body-fragment-context';
    public const HTML_FRAGMENT_CONTEXT_TABLE = 'html-table-fragment-context';
    public const HTML_DOCUMENT_PARSE_OPTIONS = LIBXML_NOERROR | LIBXML_COMPACT;

    /** @var array<string, true> */
    private const HTML5_VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    public static function htmlDocumentTreeConstructionBackend(): string
    {
        self::requireNativeHtmlDocument('HTML document');

        return self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT;
    }

    public static function htmlFragmentTreeConstructionBackend(string $html): string
    {
        self::requireNativeHtmlDocument('HTML fragment');

        return self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT;
    }

    public static function nativeHtmlDocumentAvailable(): bool
    {
        return class_exists('Dom\\HTMLDocument');
    }

    /**
     * HTML5 tree construction must receive the original source. Literal
     * payload preservation happens after Dom\HTMLDocument has built the tree.
     */
    public static function htmlTreeConstructionInput(
        string $html,
        bool $protectTemplateContent = true,
        bool $protectIframeContent = true,
        bool $protectRawTextContent = false,
        bool $protectNoscriptContent = true
    ): string {
        return $html;
    }

    /**
     * Parse a bounded HTML fragment under a synthetic body element.
     */
    public static function parseHtmlFragment(
        string $html,
        bool $protectRawTextContentForParse = false,
        bool $protectTemplateContentForParse = true,
        bool $protectIframeContentForParse = true,
        bool $protectNoscriptContentForParse = true
    ): \DOMElement
    {
        self::assertNoNullByte($html, 'HTML fragment');
        $html = self::normalizePandocHtmlInputEncoding($html);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoHtmlFragmentDeclarations($preflight, 'HTML fragment');
        self::requireNativeHtmlDocument('HTML fragment');

        $treeInput = self::html5ProtectedLiteralTreeInput(
            $html,
            protectTemplateContent: $protectTemplateContentForParse,
            protectRawTextContent: $protectRawTextContentForParse,
            protectNoscriptContent: $protectNoscriptContentForParse
        );
        $fragment = self::html5TreeConstructedFragment(
            $treeInput,
            protectTemplateContentForBridge: $protectTemplateContentForParse,
            protectIframeContentForBridge: $protectIframeContentForParse,
            protectRawTextContentForBridge: $protectRawTextContentForParse,
            protectNoscriptContentForBridge: $protectNoscriptContentForParse
        );
        if ($fragment === null) {
            throw new \RuntimeException('Unable to parse HTML fragment through Dom\\HTMLDocument');
        }

        try {
            $dom = self::loadLegacyHtml('<!doctype html><html><body>' . $fragment['source'] . '</body></html>', 'HTML fragment');
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to bridge Dom\\HTMLDocument output for HTML fragment', 0, $exception);
        }

        return self::requireBody($dom, 'HTML fragment');
    }

    public static function htmlFragmentTreeConstructionContext(string $html): string
    {
        self::assertNoNullByte($html, 'HTML fragment');
        $html = self::normalizePandocHtmlInputEncoding($html);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoHtmlFragmentDeclarations($preflight, 'HTML fragment');
        self::requireNativeHtmlDocument('HTML fragment');

        $treeInput = self::html5ProtectedLiteralTreeInput(
            $html,
            protectTemplateContent: true,
            protectRawTextContent: false,
            protectNoscriptContent: true
        );
        $fragment = self::html5TreeConstructedFragment($treeInput);
        if ($fragment === null) {
            throw new \RuntimeException('Unable to parse HTML fragment through Dom\\HTMLDocument');
        }

        return $fragment['context'];
    }

    /**
     * Parse a complete HTML document while keeping libxml network access off.
     */
    public static function parseHtmlDocument(string $html): \DOMDocument
    {
        self::assertSafeHtmlDocumentSource($html, 'HTML document');
        $html = self::normalizePandocHtmlInputEncoding($html);

        return self::loadHtml($html, 'HTML document');
    }

    /**
     * Parse HTML that a caller has already accepted through a strict parser.
     * EPUB uses this only after its XHTML content document has passed XML
     * parsing, avoiding a second broad source preflight for every chapter.
     */
    public static function parsePrevalidatedHtmlDocument(string $html, string $label = 'HTML document'): \DOMDocument
    {
        self::assertNoNullByte($html, $label);
        $html = self::normalizePandocHtmlInputEncoding($html);

        return self::loadHtml($html, $label);
    }

    /**
     * This is only a conservative source preflight: a false positive still
     * takes the full DOM path, while a false result proves that no HTML
     * microdata item can exist because every item needs `itemscope`.
     */
    public static function sourceMayContainMicrodataItemScope(string $html): bool
    {
        return stripos($html, 'itemscope') !== false;
    }

    public static function parseHtmlDocumentPreservingSourceLines(string $html, string $label = 'HTML document'): \DOMDocument
    {
        self::assertSafeHtmlDocumentSource($html, $label);
        $html = self::normalizePandocHtmlInputEncoding($html);

        return self::loadHtml($html, $label, prependEncodingDeclaration: false);
    }

    public static function treeConstructedHtmlSource(string $html): ?string
    {
        self::assertNoNullByte($html, 'HTML document');
        $html = self::normalizePandocHtmlInputEncoding($html);

        return self::html5TreeConstructedSource($html);
    }

    public static function normalizePandocHtmlInputEncoding(string $html): string
    {
        if (preg_match('//u', $html) === 1) {
            return $html;
        }

        return self::latin1BytesToUtf8($html);
    }

    /**
     * Lex a single raw HTML start tag for Markdown raw-HTML boundaries.
     * HtmlReader tree construction must go through parseHtmlDocument() or
     * parseHtmlFragment(), which route to Dom\HTMLDocument when available.
     *
     * @return array{name:string,selfClosing:bool,source:string,next:int,attributeNames:list<string>}|null
     */
    public static function rawHtmlOpeningTagAt(string $source, int $offset = 0): ?array
    {
        $length = strlen($source);
        if ($offset < 0 || $offset >= $length || ($source[$offset] ?? '') !== '<') {
            return null;
        }

        $nameStart = $offset + 1;
        if (!self::isAsciiAlpha($source[$nameStart] ?? '')) {
            return null;
        }

        $cursor = $nameStart + 1;
        while ($cursor < $length && self::isHtmlTagNameChar($source[$cursor])) {
            $cursor++;
        }

        $name = strtolower(substr($source, $nameStart, $cursor - $nameStart));
        $attributeNames = [];
        while ($cursor < $length) {
            $cursor = self::skipHtmlSpace($source, $cursor);
            if ($cursor >= $length) {
                return null;
            }

            if ($source[$cursor] === '>') {
                $next = $cursor + 1;
                $candidate = substr($source, $offset, $next - $offset);

                return self::htmlDocumentAcceptsRawTagCandidate($candidate)
                    ? [
                        'name' => $name,
                        'selfClosing' => false,
                        'source' => $candidate,
                        'next' => $next,
                        'attributeNames' => array_keys($attributeNames),
                    ]
                    : null;
            }

            if ($source[$cursor] === '/' && ($source[$cursor + 1] ?? '') === '>') {
                $next = $cursor + 2;
                $candidate = substr($source, $offset, $next - $offset);

                return self::htmlDocumentAcceptsRawTagCandidate($candidate)
                    ? [
                        'name' => $name,
                        'selfClosing' => true,
                        'source' => $candidate,
                        'next' => $next,
                        'attributeNames' => array_keys($attributeNames),
                    ]
                    : null;
            }

            if (!self::isHtmlAttributeNameStart($source[$cursor])) {
                return null;
            }

            $attributeStart = $cursor;
            $cursor++;
            while ($cursor < $length && self::isHtmlAttributeNameChar($source[$cursor])) {
                $cursor++;
            }
            $attributeNames[strtolower(substr($source, $attributeStart, $cursor - $attributeStart))] = true;

            $cursor = self::skipHtmlSpace($source, $cursor);
            if (($source[$cursor] ?? '') !== '=') {
                continue;
            }

            $cursor++;
            $cursor = self::skipHtmlSpace($source, $cursor);
            if ($cursor >= $length) {
                return null;
            }

            $quote = $source[$cursor];
            if ($quote === '"' || $quote === "'") {
                $end = strpos($source, $quote, $cursor + 1);
                if ($end === false) {
                    return null;
                }
                $cursor = $end + 1;
                continue;
            }

            $valueStart = $cursor;
            while ($cursor < $length && self::isHtmlUnquotedAttributeValueChar($source[$cursor])) {
                $cursor++;
            }
            if ($cursor === $valueStart) {
                return null;
            }
        }

        return null;
    }

    /**
     * Lex a single raw HTML end tag for Markdown raw-HTML boundaries.
     * This helper is intentionally not used as an HTML tree-construction
     * parser.
     *
     * @return array{name:string,source:string,next:int}|null
     */
    public static function rawHtmlClosingTagAt(string $source, int $offset = 0): ?array
    {
        $length = strlen($source);
        if (
            $offset < 0
            || $offset + 2 >= $length
            || ($source[$offset] ?? '') !== '<'
            || ($source[$offset + 1] ?? '') !== '/'
            || !self::isAsciiAlpha($source[$offset + 2] ?? '')
        ) {
            return null;
        }

        $nameStart = $offset + 2;
        $cursor = $nameStart + 1;
        while ($cursor < $length && self::isHtmlTagNameChar($source[$cursor])) {
            $cursor++;
        }

        $name = strtolower(substr($source, $nameStart, $cursor - $nameStart));
        $cursor = self::skipHtmlSpace($source, $cursor);
        if (($source[$cursor] ?? '') !== '>') {
            return null;
        }

        $next = $cursor + 1;
        $candidate = '<' . $name . '></' . $name . '>';
        if (!self::htmlDocumentAcceptsRawTagCandidate($candidate)) {
            return null;
        }

        return [
            'name' => $name,
            'source' => substr($source, $offset, $next - $offset),
            'next' => $next,
        ];
    }

    /**
     * @return array{name:string,selfClosing:bool,source:string,next:int,attributeNames:list<string>}|null
     */
    public static function markdownRawHtmlOpeningTagBoundary(string $line, int $maxIndent = 3): ?array
    {
        $offset = self::markdownRawHtmlBoundaryOffset($line, $maxIndent);

        return $offset === null ? null : self::rawHtmlOpeningTagAt($line, $offset);
    }

    /**
     * @return array{name:string,source:string,next:int}|null
     */
    public static function markdownRawHtmlClosingTagBoundary(string $line, int $maxIndent = 3): ?array
    {
        $offset = self::markdownRawHtmlBoundaryOffset($line, $maxIndent);
        if ($offset === null) {
            return null;
        }

        $tag = self::rawHtmlClosingTagAt($line, $offset);
        if ($tag === null) {
            return null;
        }

        return self::onlyHtmlSpaceAfter($line, $tag['next']) ? $tag : null;
    }

    public static function rawHtmlOpeningTagLineIsStandalone(string $line, string $name, int $maxIndent = 3): bool
    {
        $tag = self::markdownRawHtmlOpeningTagBoundary($line, $maxIndent);

        return $tag !== null
            && $tag['name'] === strtolower($name)
            && self::onlyHtmlSpaceAfter($line, $tag['next']);
    }

    public static function rawHtmlLineHasOpeningAndClosingTag(string $line, string $name, int $maxIndent = 3): bool
    {
        $tag = self::markdownRawHtmlOpeningTagBoundary($line, $maxIndent);
        if ($tag === null || $tag['name'] !== strtolower($name) || $tag['selfClosing']) {
            return false;
        }

        return self::rawHtmlClosingTagEndAfter($line, $tag['next'], $name) !== null;
    }

    public static function rawHtmlClosingTagEndAfter(string $source, int $offset, string $name): ?int
    {
        $name = strtolower($name);
        $cursor = max(0, $offset);
        while (($candidateOffset = strpos($source, '<', $cursor)) !== false) {
            $tag = self::rawHtmlClosingTagAt($source, $candidateOffset);
            if ($tag !== null && $tag['name'] === $name) {
                return $tag['next'];
            }
            $cursor = $candidateOffset + 1;
        }

        return null;
    }

    /**
     * @return array{name:string,source:string,next:int,offset:int}|null
     */
    public static function rawHtmlClosingTagBoundaryAfter(string $source, int $offset, string $name): ?array
    {
        $name = strtolower($name);
        $cursor = max(0, $offset);
        while (($candidateOffset = strpos($source, '<', $cursor)) !== false) {
            $tag = self::rawHtmlClosingTagAt($source, $candidateOffset);
            if ($tag !== null && $tag['name'] === $name && self::onlyHtmlSpaceAfter($source, $tag['next'])) {
                return $tag + ['offset' => $candidateOffset];
            }
            $cursor = $candidateOffset + 1;
        }

        return null;
    }

    public static function rawHtmlSourceContainsOpeningTag(string $source, string $name): bool
    {
        $name = strtolower($name);
        $cursor = 0;
        while (($candidateOffset = strpos($source, '<', $cursor)) !== false) {
            $tag = self::rawHtmlOpeningTagAt($source, $candidateOffset);
            if ($tag !== null && $tag['name'] === $name) {
                return true;
            }
            $cursor = $candidateOffset + 1;
        }

        return false;
    }

    public static function rawHtmlSourceContainsClosingTag(string $source, string $name): bool
    {
        return self::rawHtmlClosingTagEndAfter($source, 0, $name) !== null;
    }

    public static function htmlDocumentBoundaryAtStart(string $source, ?int $maxLeadingSpaces = null): bool
    {
        $offset = self::htmlDocumentBoundaryOffset($source, $maxLeadingSpaces);
        if ($offset === null) {
            return false;
        }

        $opening = self::rawHtmlOpeningTagAt($source, $offset);
        if ($opening !== null && $opening['name'] === 'html') {
            return true;
        }

        return self::htmlDoctypeHtmlAt($source, $offset) !== null;
    }

    public static function stripContentDocumentPreamble(string $source): string
    {
        if (str_starts_with($source, "\xEF\xBB\xBF")) {
            $source = substr($source, 3);
        }

        $offset = self::skipHtmlSpace($source, 0);
        $xmlDeclarationEnd = self::xmlDeclarationEndAt($source, $offset);
        if ($xmlDeclarationEnd !== null) {
            $source = substr($source, $xmlDeclarationEnd);
        }

        $offset = self::skipHtmlSpace($source, 0);
        $doctypeEnd = self::htmlDoctypeHtmlAt($source, $offset);
        if ($doctypeEnd !== null) {
            $source = substr($source, $doctypeEnd);
        }

        return ltrim($source);
    }

    /**
     * Parse one or more XML fragment roots under a synthetic wrapper element.
     */
    public static function parseXmlFragment(string $xml, string $wrapperName = 'pandoc-fragment'): \DOMElement
    {
        if (!self::isSimpleXmlWrapperName($wrapperName)) {
            throw new \InvalidArgumentException('XML fragment wrapper name must be a simple XML name');
        }

        self::assertSafeXmlSource($xml, 'XML fragment');
        $dom = self::loadXml('<' . $wrapperName . '>' . $xml . '</' . $wrapperName . '>', 'XML fragment');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XML fragment parser did not produce a wrapper element');
        }

        return $root;
    }

    /**
     * Parse one complete XML document without external entity expansion.
     */
    public static function parseXmlDocument(string $xml, string $label = 'XML document'): \DOMDocument
    {
        self::assertSafeXmlDocumentSource($xml, $label);

        return self::loadXml($xml, $label);
    }

    public static function serializeHtmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('HTML element must belong to a DOMDocument');
        }

        return XmlHtmlDom::serializeHtmlChildren($element);
    }

    public static function serializeXmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('XML element must belong to a DOMDocument');
        }

        $xml = '';
        foreach ($element->childNodes as $child) {
            $serialized = $document->saveXML($child);
            if ($serialized === false) {
                throw new \RuntimeException('Failed to serialize XML fragment child');
            }
            $xml .= $serialized;
        }

        return $xml;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (!self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    public static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function descendantElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $descendants = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement || !self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $descendants[] = $child;
        }

        return $descendants;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(\DOMElement $element): array
    {
        $attributes = [];
        $isHtmlForeignElement = self::isHtmlDocumentElement($element) && self::isHtmlForeignElement($element);
        foreach ($element->attributes as $attribute) {
            if ($attribute instanceof \DOMAttr) {
                $name = self::isHtmlDocumentElement($element)
                    ? strtolower($attribute->name)
                    : ($attribute->prefix !== '' ? $attribute->prefix . ':' . $attribute->localName : $attribute->name);
                if ($isHtmlForeignElement) {
                    $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
                }
                $attributes[$name] = $attribute->value;
            }
        }

        return $attributes;
    }

    public static function normalizedText(\DOMNode $node): string
    {
        $raw = self::textForNormalization($node);
        $text = preg_replace('/\s+/u', ' ', $raw) ?? $raw;

        return trim($text);
    }

    private static function loadHtml(
        string $html,
        string $label,
        bool $prependEncodingDeclaration = true
    ): \DOMDocument
    {
        self::requireNativeHtmlDocument($label);
        $html = self::normalizePandocHtmlInputEncoding($html);
        $treeInput = self::html5ProtectedLiteralTreeInput(
            $html,
            protectTemplateContent: true,
            protectRawTextContent: false,
            protectNoscriptContent: true
        );
        $html5 = self::html5TreeConstructedBridgeSource($treeInput);
        if ($html5 === null) {
            throw new \RuntimeException('Unable to parse ' . $label . ' through Dom\\HTMLDocument');
        }

        try {
            return self::loadLegacyHtml($html5, $label, $prependEncodingDeclaration);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to bridge Dom\\HTMLDocument output for ' . $label, 0, $exception);
        }
    }

    private static function html5ProtectedLiteralTreeInput(
        string $html,
        bool $protectTemplateContent,
        bool $protectRawTextContent,
        bool $protectNoscriptContent
    ): string {
        return $html;
    }

    private static function latin1BytesToUtf8(string $bytes): string
    {
        $result = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; ++$offset) {
            $result .= self::unicodeCodepointToUtf8(ord($bytes[$offset]));
        }

        return $result;
    }

    private static function unicodeCodepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return "\u{FFFD}";
    }

    private static function loadLegacyHtml(string $html, string $label, bool $prependEncodingDeclaration = true): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadHTML(
            ($prependEncodingDeclaration ? '<?xml encoding="UTF-8">' : '') . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        self::repairLegacyVoidElementChildren($dom);

        return $dom;
    }

    private static function repairLegacyVoidElementChildren(\DOMDocument $dom): void
    {
        do {
            $moved = false;
            $elements = iterator_to_array($dom->getElementsByTagName('*'));
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement || !$element->hasChildNodes()) {
                    continue;
                }

                $name = strtolower($element->localName);
                if (!isset(self::HTML5_VOID_ELEMENTS[$name])) {
                    continue;
                }

                $parent = $element->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                $reference = $element->nextSibling;
                foreach (iterator_to_array($element->childNodes) as $child) {
                    if (!$child instanceof \DOMNode) {
                        continue;
                    }

                    $parent->insertBefore($child, $reference);
                    $moved = true;
                }
            }
        } while ($moved);
    }

    private static function markdownRawHtmlBoundaryOffset(string $line, int $maxIndent): ?int
    {
        $length = strlen($line);
        $offset = 0;
        $indent = 0;
        while ($offset < $length && $line[$offset] === ' ' && $indent < $maxIndent) {
            $offset++;
            $indent++;
        }

        return ($line[$offset] ?? '') === '<' ? $offset : null;
    }

    private static function htmlDocumentBoundaryOffset(string $source, ?int $maxLeadingSpaces): ?int
    {
        $length = strlen($source);
        $offset = 0;
        if ($maxLeadingSpaces === null) {
            while ($offset < $length && self::isHtmlSpace($source[$offset])) {
                $offset++;
            }
        } else {
            $indent = 0;
            while ($offset < $length && $source[$offset] === ' ' && $indent < $maxLeadingSpaces) {
                $offset++;
                $indent++;
            }
        }

        return ($source[$offset] ?? '') === '<' ? $offset : null;
    }

    private static function onlyHtmlSpaceAfter(string $source, int $offset): bool
    {
        $length = strlen($source);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($source[$cursor] !== ' ' && $source[$cursor] !== "\t") {
                return false;
            }
        }

        return true;
    }

    private static function skipHtmlSpace(string $source, int $offset): int
    {
        $length = strlen($source);
        while ($offset < $length && self::isHtmlSpace($source[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function htmlDoctypeHtmlAt(string $source, int $offset): ?int
    {
        $length = strlen($source);
        if (($source[$offset] ?? '') !== '<' || ($source[$offset + 1] ?? '') !== '!') {
            return null;
        }

        $cursor = self::skipHtmlSpace($source, $offset + 2);
        if (!self::asciiKeywordAt($source, $cursor, 'doctype')) {
            return null;
        }

        $cursor += strlen('doctype');
        if (!self::isHtmlSpace($source[$cursor] ?? '')) {
            return null;
        }

        $cursor = self::skipHtmlSpace($source, $cursor);
        if (!self::asciiKeywordAt($source, $cursor, 'html')) {
            return null;
        }

        $cursor += strlen('html');
        if (self::isHtmlTagNameChar($source[$cursor] ?? '')) {
            return null;
        }

        while ($cursor < $length && $source[$cursor] !== '>') {
            $cursor++;
        }

        return $cursor < $length ? $cursor + 1 : null;
    }

    private static function xmlDeclarationEndAt(string $source, int $offset): ?int
    {
        if (($source[$offset] ?? '') !== '<' || ($source[$offset + 1] ?? '') !== '?') {
            return null;
        }

        $targetOffset = $offset + 2;
        if (!self::asciiKeywordBoundaryAt($source, $targetOffset, 'xml')) {
            return null;
        }

        $afterTarget = $targetOffset + strlen('xml');
        $next = $source[$afterTarget] ?? '';
        if ($next !== '?' && !self::isHtmlSpace($next)) {
            return null;
        }

        $end = strpos($source, '?>', $afterTarget);
        if ($end !== false) {
            return $end + 2;
        }

        $legacyEnd = strpos($source, '>', $afterTarget);

        return $legacyEnd === false ? null : $legacyEnd + 1;
    }

    private static function asciiKeywordAt(string $source, int $offset, string $keyword): bool
    {
        return strncasecmp(substr($source, $offset, strlen($keyword)), $keyword, strlen($keyword)) === 0;
    }

    private static function htmlDocumentAcceptsRawTagCandidate(string $candidate): bool
    {
        self::requireNativeHtmlDocument('raw HTML tag candidate');

        $previous = libxml_use_internal_errors(true);
        try {
            \Dom\HTMLDocument::createFromString(
                '<!doctype html><html><body>' . $candidate . '</body></html>',
                self::HTML_DOCUMENT_PARSE_OPTIONS,
                'UTF-8'
            );

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function isHtmlTagNameChar(string $char): bool
    {
        return self::isAsciiAlpha($char) || ctype_digit($char) || in_array($char, ['-', ':'], true);
    }

    private static function isHtmlAttributeNameStart(string $char): bool
    {
        return self::isAsciiAlpha($char) || in_array($char, ['_', ':'], true);
    }

    private static function isHtmlAttributeNameChar(string $char): bool
    {
        return self::isHtmlAttributeNameStart($char)
            || ctype_digit($char)
            || in_array($char, ['.', '-'], true);
    }

    private static function isHtmlUnquotedAttributeValueChar(string $char): bool
    {
        return !self::isHtmlSpace($char)
            && !in_array($char, ['"', "'", '=', '<', '>', '`'], true);
    }

    private static function isHtmlSpace(string $char): bool
    {
        return in_array($char, ["\t", "\n", "\f", "\r", ' '], true);
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z');
    }

    private static function isSimpleXmlWrapperName(string $name): bool
    {
        $length = strlen($name);
        if ($length === 0 || (!self::isAsciiAlpha($name[0]) && $name[0] !== '_')) {
            return false;
        }

        for ($offset = 1; $offset < $length; $offset++) {
            $char = $name[$offset];
            if (!self::isAsciiAlpha($char) && !ctype_digit($char) && !in_array($char, ['_', '.', '-'], true)) {
                return false;
            }
        }

        return true;
    }

    private static function html5TreeConstructedSource(string $html): ?string
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = \Dom\HTMLDocument::createFromString(
                $html,
                self::HTML_DOCUMENT_PARSE_OPTIONS,
                'UTF-8'
            );

            return $document->saveHtml();
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function html5TreeConstructedBridgeSource(string $html): ?string
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = \Dom\HTMLDocument::createFromString(
                $html,
                self::HTML_DOCUMENT_PARSE_OPTIONS,
                'UTF-8'
            );

            return self::html5SerializeDocumentForLegacyBridge(
                $document,
                self::html5LiteralPayloadBridgeElementNames()
            );
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function html5TreeConstructedFragmentSource(string $html): ?string
    {
        $fragment = self::html5TreeConstructedFragment($html);

        return $fragment['source'] ?? null;
    }

    /**
     * @return array{source:string, context:string}|null
     */
    private static function html5TreeConstructedFragment(
        string $html,
        bool $protectTemplateContentForBridge = true,
        bool $protectIframeContentForBridge = true,
        bool $protectRawTextContentForBridge = false,
        bool $protectNoscriptContentForBridge = true
    ): ?array
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $literalPayloadElements = self::html5LiteralPayloadBridgeElementNames(
                protectTemplateContent: $protectTemplateContentForBridge,
                protectIframeContent: $protectIframeContentForBridge,
                protectRawTextContent: $protectRawTextContentForBridge,
                protectNoscriptContent: $protectNoscriptContentForBridge
            );
            $normal = self::html5BodyContextFragment($html, $literalPayloadElements);
            if ($normal === null) {
                return null;
            }

            $table = self::html5TableContextFragment($html, $literalPayloadElements);
            if (
                $table !== null
                && !self::html5ElementHasTableStructure($normal['body'])
                && self::html5ElementChildrenHaveTableStructure($table['table'])
            ) {
                return [
                    'source' => $table['source'],
                    'context' => self::HTML_FRAGMENT_CONTEXT_TABLE,
                ];
            }

            return [
                'source' => $normal['source'],
                'context' => self::HTML_FRAGMENT_CONTEXT_BODY,
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @param array<string, true> $literalPayloadElements
     * @return array{body:\Dom\Element, source:string}|null
     */
    private static function html5BodyContextFragment(string $html, array $literalPayloadElements): ?array
    {
        $insertDocument = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body></body></html>',
            self::HTML_DOCUMENT_PARSE_OPTIONS,
            'UTF-8'
        );
        $insertBody = self::html5FirstElementByTagName($insertDocument, 'body');
        $insertCandidate = null;
        if (
            $insertBody instanceof \Dom\Element
            && self::html5AppendFragmentWithNativeDom($insertBody, $html)
        ) {
            $insertCandidate = [
                'body' => $insertBody,
                'source' => self::html5SerializeChildren($insertDocument, $insertBody, $literalPayloadElements),
            ];
        }

        $document = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body>' . $html . '</body></html>',
            self::HTML_DOCUMENT_PARSE_OPTIONS,
            'UTF-8'
        );
        $body = self::html5FirstElementByTagName($document, 'body');
        if (!$body instanceof \Dom\Element) {
            return $insertCandidate;
        }
        $documentCandidate = [
            'body' => $body,
            'source' => self::html5SerializeChildren($document, $body, $literalPayloadElements),
        ];

        return $insertCandidate === null
            ? $documentCandidate
            : self::html5PreferredFragmentCandidate($insertCandidate, $documentCandidate);
    }

    /**
     * @param array<string, mixed> $insertCandidate
     * @param array<string, mixed> $documentCandidate
     * @return array<string, mixed>
     */
    private static function html5PreferredFragmentCandidate(array $insertCandidate, array $documentCandidate): array
    {
        if (
            self::html5FragmentCandidateLeaksSyntheticWrapper((string) $documentCandidate['source'])
            && !self::html5FragmentCandidateLeaksSyntheticWrapper((string) $insertCandidate['source'])
        ) {
            return $insertCandidate;
        }

        return self::html5FragmentCandidateScore((string) $documentCandidate['source']) > self::html5FragmentCandidateScore((string) $insertCandidate['source'])
            ? $documentCandidate
            : $insertCandidate;
    }

    /**
     * @param array<string, true> $literalPayloadElements
     * @return array{table:\Dom\Element, source:string}|null
     */
    private static function html5TableContextFragment(string $html, array $literalPayloadElements): ?array
    {
        $insertDocument = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body><table id="pandoc-html-fragment-context"></table></body></html>',
            self::HTML_DOCUMENT_PARSE_OPTIONS,
            'UTF-8'
        );
        $insertTable = $insertDocument->getElementById('pandoc-html-fragment-context');
        $insertCandidate = null;
        if (
            $insertTable instanceof \Dom\HTMLElement
            && self::html5AppendFragmentWithNativeDom($insertTable, $html)
        ) {
            $insertCandidate = [
                'table' => $insertTable,
                'source' => self::html5TableContextSource($insertDocument, $insertTable, $literalPayloadElements),
            ];
        }

        $document = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body><table id="pandoc-html-fragment-context">' . $html . '</table></body></html>',
            self::HTML_DOCUMENT_PARSE_OPTIONS,
            'UTF-8'
        );
        $table = $document->getElementById('pandoc-html-fragment-context');
        if (!$table instanceof \Dom\HTMLElement) {
            return $insertCandidate;
        }
        $documentCandidate = [
            'table' => $table,
            'source' => self::html5TableContextSource($document, $table, $literalPayloadElements),
        ];

        return $insertCandidate === null
            ? $documentCandidate
            : self::html5PreferredFragmentCandidate($insertCandidate, $documentCandidate);
    }

    /**
     * Native adjacent HTML insertion was added after Dom\HTMLDocument itself.
     * It is useful when available because it gives the browser-style fragment
     * parser a concrete context, but the direct wrapped-document candidate is
     * still a complete HTML5 tree-construction path on PHP 8.4.
     */
    private static function html5AppendFragmentWithNativeDom(\Dom\Element $element, string $html): bool
    {
        if (!method_exists($element, 'insertAdjacentHTML')) {
            return false;
        }

        try {
            $element->insertAdjacentHTML(\Dom\AdjacentPosition::BeforeEnd, $html);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function html5FragmentCandidateLeaksSyntheticWrapper(string $source): bool
    {
        return preg_match('/<\/(?:body|html)>/i', $source) === 1;
    }

    private static function html5FragmentCandidateScore(string $source): int
    {
        $text = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', '', $text) ?? $text;

        return strlen($text);
    }

    /**
     * @param array<string, true> $literalPayloadElements
     */
    private static function html5TableContextSource(
        \Dom\HTMLDocument $document,
        \Dom\Element $table,
        array $literalPayloadElements
    ): string
    {
        $source = '';
        $tableChildren = '';
        foreach ($table->childNodes as $child) {
            if ($child instanceof \Dom\Element && self::isHtml5TableContextChildName(strtolower($child->localName))) {
                $tableChildren .= self::html5SerializeNodeForLegacyBridge(
                    $document,
                    $child,
                    $literalPayloadElements
                );
                continue;
            }

            if ($tableChildren !== '') {
                $source .= '<table>' . $tableChildren . '</table>';
                $tableChildren = '';
            }

            $source .= self::html5SerializeNodeForLegacyBridge($document, $child, $literalPayloadElements);
        }

        if ($tableChildren !== '') {
            $source .= '<table>' . $tableChildren . '</table>';
        }

        return $source;
    }

    private static function isHtml5TableContextChildName(string $name): bool
    {
        return in_array($name, ['caption', 'col', 'colgroup', 'script', 'tbody', 'template', 'tfoot', 'thead', 'tr'], true);
    }

    private static function html5ElementHasTableStructure(\Dom\Element $element): bool
    {
        if (self::isHtml5TableStructureName(strtolower($element->localName))) {
            return true;
        }

        return self::html5ElementChildrenHaveTableStructure($element);
    }

    private static function html5ElementChildrenHaveTableStructure(\Dom\Element $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Element && self::html5ElementHasTableStructure($child)) {
                return true;
            }
        }

        return false;
    }

    private static function isHtml5TableStructureName(string $name): bool
    {
        return in_array($name, ['caption', 'col', 'colgroup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true);
    }

    private static function html5FirstElementByTagName(\Dom\HTMLDocument $document, string $name): ?\Dom\Element
    {
        $elements = $document->getElementsByTagName($name);
        $element = $elements->item(0);

        return $element instanceof \Dom\Element ? $element : null;
    }

    /**
     * @return array<string, true>
     */
    private static function html5LiteralPayloadBridgeElementNames(
        bool $protectTemplateContent = true,
        bool $protectIframeContent = true,
        bool $protectRawTextContent = false,
        bool $protectNoscriptContent = true
    ): array {
        $names = [
            'noembed' => true,
            'noframes' => true,
            'plaintext' => true,
            'textarea' => true,
            'title' => true,
            'xmp' => true,
        ];
        if ($protectTemplateContent) {
            $names['template'] = true;
        }
        if ($protectIframeContent) {
            $names['iframe'] = true;
        }
        if ($protectNoscriptContent) {
            $names['noscript'] = true;
        }
        if ($protectRawTextContent) {
            $names['script'] = true;
            $names['style'] = true;
        }

        return $names;
    }

    /**
     * @param array<string, true> $literalPayloadElements
     */
    private static function html5SerializeDocumentForLegacyBridge(
        \Dom\HTMLDocument $document,
        array $literalPayloadElements
    ): string {
        $source = '';
        foreach ($document->childNodes as $child) {
            $source .= self::html5SerializeNodeForLegacyBridge($document, $child, $literalPayloadElements);
        }

        return $source;
    }

    /**
     * @param array<string, true> $literalPayloadElements
     */
    private static function html5SerializeChildren(
        \Dom\HTMLDocument $document,
        \Dom\Element $element,
        array $literalPayloadElements
    ): string
    {
        $source = '';
        foreach ($element->childNodes as $child) {
            $source .= self::html5SerializeNodeForLegacyBridge($document, $child, $literalPayloadElements);
        }

        return $source;
    }

    /**
     * @param array<string, true> $literalPayloadElements
     */
    private static function html5SerializeNodeForLegacyBridge(
        \Dom\HTMLDocument $document,
        \Dom\Node $node,
        array $literalPayloadElements
    ): string {
        if (!$node instanceof \Dom\Element) {
            return $document->saveHtml($node);
        }

        $name = strtolower($node->localName);
        if (!self::html5ElementIsHtmlNamespace($node)) {
            return $document->saveHtml($node);
        }
        if (
            !isset($literalPayloadElements[$name])
            && !self::html5ElementContainsLiteralPayloadBridgeElement($node, $literalPayloadElements)
        ) {
            return $document->saveHtml($node);
        }

        if (isset($literalPayloadElements[$name])) {
            return self::html5ElementStartTagForLegacyBridge($node, $name)
                . self::escapeHtmlTextForLegacyBridge(self::html5LiteralPayloadForLegacyBridge($node, $name))
                . '</' . $name . '>';
        }

        $source = self::html5ElementStartTagForLegacyBridge($node, $name);
        if (isset(self::HTML5_VOID_ELEMENTS[$name])) {
            return $source;
        }

        foreach ($node->childNodes as $child) {
            $source .= self::html5SerializeNodeForLegacyBridge($document, $child, $literalPayloadElements);
        }

        return $source . '</' . $name . '>';
    }

    /**
     * @param array<string, true> $literalPayloadElements
     */
    private static function html5ElementContainsLiteralPayloadBridgeElement(
        \Dom\Element $element,
        array $literalPayloadElements
    ): bool {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \Dom\Element && isset($literalPayloadElements[strtolower($descendant->localName)])) {
                return true;
            }
        }

        return false;
    }

    private static function html5ElementIsHtmlNamespace(\Dom\Element $element): bool
    {
        return $element->namespaceURI === null || $element->namespaceURI === 'http://www.w3.org/1999/xhtml';
    }

    private static function html5LiteralPayloadForLegacyBridge(\Dom\Element $element, string $name): string
    {
        if ($name === 'template' && property_exists($element, 'innerHTML')) {
            return html_entity_decode((string) $element->innerHTML, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($name === 'script' || $name === 'style') {
            return html_entity_decode((string) $element->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (in_array($name, ['iframe', 'noembed', 'noframes', 'noscript', 'plaintext', 'xmp'], true) && property_exists($element, 'innerHTML')) {
            $payload = (string) $element->innerHTML;
            if ($name === 'plaintext') {
                $payload = preg_replace('/<\/body>\s*<\/html>\s*$/i', '', $payload) ?? $payload;
            }

            return $payload;
        }

        return (string) $element->textContent;
    }

    private static function html5ElementStartTagForLegacyBridge(\Dom\Element $element, string $name): string
    {
        $source = '<' . $name;
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \Dom\Attr) {
                continue;
            }

            $source .= ' ' . $attribute->name . '="'
                . self::escapeHtmlAttributeForLegacyBridge($attribute->value)
                . '"';
        }

        return $source . '>';
    }

    private static function escapeHtmlTextForLegacyBridge(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function escapeHtmlAttributeForLegacyBridge(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        self::assertNoProcessingInstructions($dom, $label);

        return $dom;
    }

    private static function requireBody(\DOMDocument $dom, string $label): \DOMElement
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            throw new \RuntimeException($label . ' parser did not produce a body element');
        }

        return $body;
    }

    private static function requireNativeHtmlDocument(string $label): void
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            throw new \RuntimeException('Dom\\HTMLDocument is required to parse ' . $label);
        }
    }

    private static function elementMatches(\DOMElement $element, ?string $localName, ?string $namespace): bool
    {
        if ($localName !== null) {
            $matchesRawName = $element->localName === $localName;
            $matchesAdjustedName = self::isHtmlDocumentElement($element)
                && XmlHtmlDom::htmlElementName($element) === $localName;
            if (!$matchesRawName && !$matchesAdjustedName) {
                return false;
            }
        }

        if ($namespace !== null && $element->namespaceURI !== $namespace) {
            return false;
        }

        return true;
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (self::containsXmlDeclaration($declarationScanSource)) {
            throw new \InvalidArgumentException($label . ' must not include an XML declaration');
        }
        if (self::containsProcessingInstruction($declarationScanSource)) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (self::containsMarkupDeclaration($declarationScanSource, ['DOCTYPE', 'ENTITY', 'ELEMENT', 'ATTLIST', 'NOTATION'])) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertSafeXmlDocumentSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (self::containsMarkupDeclaration($declarationScanSource, ['DOCTYPE', 'ENTITY', 'ELEMENT', 'ATTLIST', 'NOTATION'])) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertNoHtmlFragmentDeclarations(string $html, string $label): void
    {
        $declarationScanSource = self::sourceForDeclarationScan($html);
        if (self::containsMarkupDeclaration($declarationScanSource, ['DOCTYPE', 'ENTITY', 'ELEMENT', 'ATTLIST', 'NOTATION'])) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (self::containsProcessingInstruction($declarationScanSource)) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeHtmlDocumentSource(string $html, string $label): void
    {
        self::assertNoNullByte($html, $label);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        $declarationScanSource = self::sourceForDeclarationScan($preflight);
        self::assertSimpleHtmlDocumentDoctype($declarationScanSource, $label);
        if (self::containsHtmlDoctypeInternalSubset($declarationScanSource)) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (self::containsMarkupDeclaration($declarationScanSource, ['ENTITY', 'ELEMENT', 'ATTLIST', 'NOTATION'])) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (self::containsProcessingInstruction($declarationScanSource)) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function sourceForDeclarationScan(string $source): string
    {
        $length = strlen($source);
        $scan = '';
        $offset = 0;

        while ($offset < $length) {
            if (str_starts_with(substr($source, $offset, 9), '<![CDATA[')) {
                $cdataEnd = strpos($source, ']]>', $offset + 9);
                if ($cdataEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $cdataLength = $cdataEnd + 3 - $offset;
                $scan .= str_repeat(' ', $cdataLength);
                $offset += $cdataLength;
                continue;
            }

            if (str_starts_with(substr($source, $offset, 4), '<!--')) {
                $commentEnd = strpos($source, '-->', $offset + 4);
                if ($commentEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $commentLength = $commentEnd + 3 - $offset;
                $scan .= str_repeat(' ', $commentLength);
                $offset += $commentLength;
                continue;
            }

            if ($source[$offset] === '<' && self::isHtmlTagStartForDeclarationScan($source, $offset)) {
                [$tagSource, $nextOffset] = self::maskQuotedTagAttributeValuesForDeclarationScan($source, $offset);
                $scan .= $tagSource;
                $offset = $nextOffset;
                continue;
            }

            $scan .= $source[$offset];
            ++$offset;
        }

        return $scan;
    }

    private static function isHtmlTagStartForDeclarationScan(string $source, int $offset): bool
    {
        $length = strlen($source);
        $nameOffset = $offset + 1;
        if ($nameOffset >= $length) {
            return false;
        }

        if ($source[$nameOffset] === '/') {
            ++$nameOffset;
        }

        return $nameOffset < $length && self::isAsciiAlpha($source[$nameOffset]);
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function maskQuotedTagAttributeValuesForDeclarationScan(string $source, int $offset): array
    {
        $length = strlen($source);
        $tag = '';

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $quoteEnd = strpos($source, $char, $offset + 1);
                if ($quoteEnd === false) {
                    $tag .= substr($source, $offset);

                    return [$tag, $length];
                }

                $tag .= $char . str_repeat(' ', $quoteEnd - $offset - 1) . $char;
                $offset = $quoteEnd + 1;
                continue;
            }

            $tag .= $char;
            ++$offset;
            if ($char === '>') {
                break;
            }
        }

        return [$tag, $offset];
    }

    private static function containsXmlDeclaration(string $source): bool
    {
        $cursor = 0;
        while (($offset = strpos($source, '<?', $cursor)) !== false) {
            $targetOffset = $offset + 2;
            if (self::asciiKeywordBoundaryAt($source, $targetOffset, 'xml')) {
                return true;
            }
            $cursor = $targetOffset;
        }

        return false;
    }

    private static function containsProcessingInstruction(string $source): bool
    {
        $cursor = 0;
        while (($offset = strpos($source, '<?', $cursor)) !== false) {
            $targetOffset = $offset + 2;
            if (self::isAsciiAlpha($source[$targetOffset] ?? '') || ($source[$targetOffset] ?? '') === '_') {
                return true;
            }
            $cursor = $targetOffset;
        }

        return false;
    }

    /**
     * @param list<string> $keywords
     */
    private static function containsMarkupDeclaration(string $source, array $keywords): bool
    {
        $cursor = 0;
        while (($offset = strpos($source, '<!', $cursor)) !== false) {
            if (self::markupDeclarationKeywordOffset($source, $offset, $keywords) !== null) {
                return true;
            }
            $cursor = $offset + 2;
        }

        return false;
    }

    private static function containsHtmlDoctypeInternalSubset(string $source): bool
    {
        foreach (self::htmlDoctypeDeclarations($source) as $doctype) {
            if ($doctype['hasInternalSubset']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{contents:string,complete:bool,hasInternalSubset:bool}>
     */
    private static function htmlDoctypeDeclarations(string $source): array
    {
        $declarations = [];
        $cursor = 0;
        while (($offset = strpos($source, '<!', $cursor)) !== false) {
            $keywordOffset = self::markupDeclarationKeywordOffset($source, $offset, ['DOCTYPE']);
            if ($keywordOffset === null) {
                $cursor = $offset + 2;
                continue;
            }

            $contentsOffset = $keywordOffset + strlen('DOCTYPE');
            $end = strpos($source, '>', $contentsOffset);
            if ($end === false) {
                $contents = substr($source, $contentsOffset);
                $declarations[] = [
                    'contents' => $contents,
                    'complete' => false,
                    'hasInternalSubset' => str_contains($contents, '['),
                ];
                break;
            }

            $contents = substr($source, $contentsOffset, $end - $contentsOffset);
            $declarations[] = [
                'contents' => $contents,
                'complete' => true,
                'hasInternalSubset' => str_contains($contents, '['),
            ];
            $cursor = $end + 1;
        }

        return $declarations;
    }

    /**
     * @param list<string> $keywords
     */
    private static function markupDeclarationKeywordOffset(string $source, int $offset, array $keywords): ?int
    {
        if (($source[$offset] ?? '') !== '<' || ($source[$offset + 1] ?? '') !== '!') {
            return null;
        }

        $keywordOffset = self::skipHtmlSpace($source, $offset + 2);
        foreach ($keywords as $keyword) {
            if (self::asciiKeywordBoundaryAt($source, $keywordOffset, $keyword)) {
                return $keywordOffset;
            }
        }

        return null;
    }

    private static function asciiKeywordBoundaryAt(string $source, int $offset, string $keyword): bool
    {
        if (!self::asciiKeywordAt($source, $offset, $keyword)) {
            return false;
        }

        return !self::isAsciiKeywordContinuation($source[$offset + strlen($keyword)] ?? '');
    }

    private static function isAsciiKeywordContinuation(string $char): bool
    {
        return $char !== '' && (self::isAsciiAlpha($char) || ctype_digit($char) || $char === '_');
    }

    private static function collapseHtmlSpace(string $source): string
    {
        $length = strlen($source);
        $normalized = '';
        $inSpace = false;
        for ($offset = 0; $offset < $length; $offset++) {
            if (self::isHtmlSpace($source[$offset])) {
                if (!$inSpace) {
                    $normalized .= ' ';
                    $inSpace = true;
                }
                continue;
            }

            $normalized .= $source[$offset];
            $inSpace = false;
        }

        return trim($normalized);
    }

    private static function assertSimpleHtmlDocumentDoctype(string $html, string $label): void
    {
        $doctypes = self::htmlDoctypeDeclarations($html);
        foreach ($doctypes as $doctype) {
            if (!$doctype['complete']) {
                throw new \InvalidArgumentException($label . ' must use a complete simple HTML doctype');
            }
        }

        $doctypeCount = count($doctypes);
        if ($doctypeCount > 1) {
            throw new \InvalidArgumentException($label . ' must not declare multiple doctypes');
        }
        if ($doctypeCount === 0) {
            return;
        }

        $doctypeName = self::collapseHtmlSpace($doctypes[0]['contents']);
        if (strcasecmp($doctypeName, 'html') !== 0) {
            throw new \InvalidArgumentException($label . ' must use a simple HTML doctype without external identifiers or subsets');
        }
    }

    private static function assertNoNullByte(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
    }

    private static function assertNoProcessingInstructions(\DOMNode $node, string $label): void
    {
        if ($node instanceof \DOMProcessingInstruction) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }

        foreach ($node->childNodes as $child) {
            self::assertNoProcessingInstructions($child, $label);
        }
    }

    private static function isHtmlDocumentElement(\DOMElement $element): bool
    {
        $document = $element->ownerDocument;
        $root = $document instanceof \DOMDocument ? $document->documentElement : null;

        return $root instanceof \DOMElement && strtolower($root->localName) === 'html';
    }

    private static function isHtmlForeignElement(\DOMElement $element): bool
    {
        $node = $element;
        $child = null;
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                if (
                    !self::isMathMlTextIntegrationPointName($name)
                    || !$child instanceof \DOMElement
                    || !self::isMathMlTextIntegrationExceptionName(strtolower($child->localName))
                ) {
                    return false;
                }
            }
            if ($name === 'svg' || $name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $parent = $node->parentNode;
            $child = $node;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return false;
    }

    private static function isHtmlIntegrationPoint(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if (self::isSvgHtmlIntegrationPointName($name)) {
            return true;
        }
        if (self::isMathMlTextIntegrationPointName($name)) {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
    }

    private static function isSvgHtmlIntegrationPointName(string $name): bool
    {
        return in_array($name, ['foreignobject', 'desc', 'title'], true);
    }

    private static function isMathMlTextIntegrationPointName(string $name): bool
    {
        return in_array($name, ['mi', 'mn', 'mo', 'ms', 'mtext'], true);
    }

    private static function isMathMlTextIntegrationExceptionName(string $name): bool
    {
        return $name === 'mglyph' || $name === 'malignmark';
    }

    private static function textForNormalization(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->nodeValue ?? '';
        }

        if ($node instanceof \DOMElement && strtolower($node->localName) === 'br') {
            return "\n";
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::textForNormalization($child);
        }

        return $text;
    }
}
