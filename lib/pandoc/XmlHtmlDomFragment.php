<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtmlDomFragment
{
    /** @var list<string> */
    private const HTML_VOID_ELEMENTS = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
    ];

    /** @var list<string> */
    private const HTML_BOOLEAN_ATTRIBUTES = [
        'allowfullscreen',
        'async',
        'autofocus',
        'autoplay',
        'checked',
        'controls',
        'default',
        'defer',
        'disabled',
        'formnovalidate',
        'hidden',
        'ismap',
        'itemscope',
        'loop',
        'multiple',
        'muted',
        'nomodule',
        'novalidate',
        'open',
        'playsinline',
        'readonly',
        'required',
        'reversed',
        'selected',
    ];

    /** @var list<string> */
    private const HTML_ACTIVE_ELEMENTS = [
        'applet',
        'base',
        'embed',
        'frame',
        'frameset',
        'iframe',
        'link',
        'meta',
        'object',
        'script',
        'style',
        'template',
    ];

    /** @var list<string> */
    private const URL_ATTRIBUTES = [
        'action',
        'background',
        'cite',
        'formaction',
        'href',
        'longdesc',
        'poster',
        'src',
        'xlink:href',
    ];

    /** @var list<string> */
    private const HTML_MULTI_URL_ATTRIBUTES = [
        'srcset',
    ];

    /** @var list<string> */
    private const HTML_SIDE_EFFECT_ATTRIBUTES = [
        'ping',
    ];

    /**
     * @param list<array<string, string>> $diagnostics
     */
    private function __construct(
        private readonly string $format,
        private readonly AstNode $fragment,
        private readonly array $diagnostics,
    ) {
    }

    public static function parseHtml(string $html): self
    {
        self::assertSafeHtmlSource($html, 'HTML fragment');
        $html = self::escapeHtmlTextSyntax($html);

        $body = Html5Dom::parseHtmlFragment(
            $html,
            protectTemplateContentForParse: false,
            protectIframeContentForParse: false,
            protectNoscriptContentForParse: false
        );
        if (!$body instanceof \DOMElement) {
            throw new \InvalidArgumentException('Parsed HTML fragment did not contain a body element');
        }

        self::assertNoProcessingInstructions($body, 'HTML fragment');

        $diagnostics = [];
        $children = self::domChildrenToFragmentNodes($body, true, $diagnostics);

        return new self('html', new AstNode('dom_fragment', ['format' => 'html'], $children), $diagnostics);
    }

    private static function escapeHtmlTextSyntax(string $html): string
    {
        $length = strlen($html);
        $escaped = '';
        $offset = 0;

        while ($offset < $length) {
            $char = $html[$offset];
            if ($char === '<') {
                if (str_starts_with(substr($html, $offset, 4), '<!--')) {
                    [$section, $nextOffset] = self::copyHtmlSection($html, $offset, '-->');
                    $escaped .= $section;
                    $offset = $nextOffset;
                    continue;
                }
                if (str_starts_with(substr($html, $offset, 9), '<![CDATA[')) {
                    [$section, $nextOffset] = self::copyHtmlSection($html, $offset, ']]>');
                    $escaped .= $section;
                    $offset = $nextOffset;
                    continue;
                }
                if (self::isHtmlDeclarationLikeStartForTextSyntax($html, $offset)) {
                    [, $nextOffset] = self::copyHtmlTagSource($html, $offset);
                    $offset = $nextOffset;
                    continue;
                }
                if (self::isHtmlTagStartForTextSyntax($html, $offset)) {
                    [$tag, $nextOffset] = self::copyHtmlTagSource($html, $offset);
                    $escaped .= $tag;
                    $offset = $nextOffset;
                    continue;
                }

                $escaped .= '&lt;';
                ++$offset;
                continue;
            }

            if ($char === '&') {
                $entityLength = self::htmlEntityReferenceLength($html, $offset);
                if ($entityLength > 0) {
                    $escaped .= substr($html, $offset, $entityLength);
                    $offset += $entityLength;
                    continue;
                }

                $escaped .= '&amp;';
                ++$offset;
                continue;
            }

            $escaped .= $char;
            ++$offset;
        }

        return $escaped;
    }

    private static function isHtmlDeclarationLikeStartForTextSyntax(string $source, int $offset): bool
    {
        $next = $source[$offset + 1] ?? '';

        return $next === '!' || $next === '?';
    }

    private static function isHtmlTagStartForTextSyntax(string $source, int $offset): bool
    {
        $length = strlen($source);
        $nameOffset = $offset + 1;
        if ($nameOffset >= $length) {
            return false;
        }

        if ($source[$nameOffset] === '/') {
            ++$nameOffset;
        }

        return $nameOffset < $length && preg_match('/[A-Za-z]/', $source[$nameOffset]) === 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function copyHtmlSection(string $source, int $offset, string $terminator): array
    {
        $end = strpos($source, $terminator, $offset + strlen($terminator));
        if ($end === false) {
            return [substr($source, $offset), strlen($source)];
        }

        $nextOffset = $end + strlen($terminator);

        return [substr($source, $offset, $nextOffset - $offset), $nextOffset];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function copyHtmlTagSource(string $source, int $offset): array
    {
        $length = strlen($source);
        $tag = '';
        $quote = null;

        while ($offset < $length) {
            $char = $source[$offset];
            $tag .= $char;
            ++$offset;

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                break;
            }
        }

        return [$tag, $offset];
    }

    private static function htmlEntityReferenceLength(string $source, int $offset): int
    {
        if (preg_match('/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/', substr($source, $offset), $match) !== 1) {
            return 0;
        }

        return strlen($match[0]);
    }

    public static function parseXml(string $xml): self
    {
        self::assertSafeXmlSource($xml, 'XML fragment');

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML(
            '<pandoc-fragment>' . $xml . '</pandoc-fragment>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            throw new \InvalidArgumentException('Unable to parse XML fragment');
        }

        self::assertNoProcessingInstructions($dom->documentElement, 'XML fragment');

        $diagnostics = [];
        $children = self::domChildrenToFragmentNodes($dom->documentElement, false, $diagnostics);

        return new self('xml', new AstNode('dom_fragment', ['format' => 'xml'], $children), $diagnostics);
    }

    private static function assertSafeHtmlSource(string $html, string $label): void
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
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<\?xml\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException('XML declaration is not allowed inside a fragment');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException('XML fragments with DTD or entity declarations are not supported');
        }
    }

    private static function sourceForDeclarationScan(string $source): string
    {
        $length = strlen($source);
        $scan = '';
        $offset = 0;

        while ($offset < $length) {
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

        return $nameOffset < $length && preg_match('/[A-Za-z]/', $source[$nameOffset]) === 1;
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

    public function fragment(): AstNode
    {
        return $this->fragment;
    }

    /**
     * @return list<AstNode>
     */
    public function children(): array
    {
        return $this->fragment->children;
    }

    /**
     * @return list<array<string, string>>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return list<string>
     */
    public function elementNames(): array
    {
        $names = [];
        self::collectElementNames($this->fragment->children, $names);

        return $names;
    }

    public function textContent(): string
    {
        return self::nodeTextContent($this->fragment);
    }

    public function serializeHtml(): string
    {
        return self::serializeNodesAsHtml($this->fragment->children);
    }

    public function serializeXml(): string
    {
        return self::serializeNodesAsXml($this->fragment->children);
    }

    public function serialize(): string
    {
        return $this->format === 'xml' ? $this->serializeXml() : $this->serializeHtml();
    }

    /**
     * @param list<array<string, string>> $diagnostics
     * @return list<AstNode>
     */
    private static function domChildrenToFragmentNodes(\DOMNode $parent, bool $html, array &$diagnostics): array
    {
        $children = [];
        foreach ($parent->childNodes as $node) {
            foreach (self::domNodeToFragmentNodes($node, $html, $diagnostics) as $fragmentNode) {
                $children[] = $fragmentNode;
            }
        }

        return $children;
    }

    /**
     * @param list<array<string, string>> $diagnostics
     * @return list<AstNode>
     */
    private static function domNodeToFragmentNodes(\DOMNode $node, bool $html, array &$diagnostics): array
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            $text = $node->wholeText;

            return $text === '' ? [] : [new AstNode('dom_text', ['text' => $text])];
        }

        if ($node instanceof \DOMComment) {
            return [new AstNode('dom_comment', ['text' => $node->data])];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = $html ? strtolower($node->localName) : $node->nodeName;
        if ($html && $name === 'template') {
            $diagnostics[] = [
                'code' => 'unwrapped-template-element',
                'element' => $name,
            ];

            return self::domChildrenToFragmentNodes($node, $html, $diagnostics);
        }

        if ($html && in_array($name, self::HTML_ACTIVE_ELEMENTS, true)) {
            $diagnostics[] = [
                'code' => 'dropped-active-element',
                'element' => $name,
            ];

            return [];
        }

        $attrs = self::domElementAttributes($node, $html, $name, $diagnostics);
        $children = self::domChildrenToFragmentNodes($node, $html, $diagnostics);

        return [new AstNode('dom_element', [
            'name' => $name,
            'attributes' => $attrs,
        ], $children)];
    }

    /**
     * @param list<array<string, string>> $diagnostics
     * @return array<string, string|bool>
     */
    private static function domElementAttributes(\DOMElement $element, bool $html, string $elementName, array &$diagnostics): array
    {
        $attrs = [];
        if (!$html) {
            foreach (self::namespaceAttributesForElement($element) as $name => $value) {
                $attrs[$name] = $value;
            }
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = $html ? strtolower($attribute->nodeName) : $attribute->nodeName;
            $value = $attribute->value;

            if ($html && str_starts_with($name, 'on')) {
                $diagnostics[] = [
                    'code' => 'dropped-event-attribute',
                    'element' => $elementName,
                    'attribute' => $name,
                ];
                continue;
            }

            if ($html && in_array($name, self::HTML_SIDE_EFFECT_ATTRIBUTES, true)) {
                $diagnostics[] = [
                    'code' => 'dropped-side-effect-attribute',
                    'element' => $elementName,
                    'attribute' => $name,
                ];
                continue;
            }

            if ($html && in_array($name, self::HTML_MULTI_URL_ATTRIBUTES, true) && self::containsUnsafeUrlCandidate($value)) {
                $diagnostics[] = [
                    'code' => 'dropped-unsafe-url',
                    'element' => $elementName,
                    'attribute' => $name,
                ];
                continue;
            }

            if ($html && in_array($name, self::URL_ATTRIBUTES, true) && self::isUnsafeUrl($value)) {
                $diagnostics[] = [
                    'code' => 'dropped-unsafe-url',
                    'element' => $elementName,
                    'attribute' => $name,
                ];
                continue;
            }

            // This legacy facade has no CSS parser. Do not try to recognize
            // dangerous CSS with text matching: escapes, comments, and token
            // boundaries make that unsafe. Html5DomFragment is the
            // escape-aware path for callers that need reviewable style data.
            if ($html && $name === 'style') {
                $diagnostics[] = [
                    'code' => 'dropped-unsafe-style',
                    'element' => $elementName,
                    'attribute' => $name,
                ];
                continue;
            }

            $attrs[$name] = $html && in_array($name, self::HTML_BOOLEAN_ATTRIBUTES, true) ? true : $value;
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private static function namespaceAttributesForElement(\DOMElement $element): array
    {
        $attrs = [];
        $elementPrefix = $element->prefix;
        if ($element->namespaceURI !== null && $elementPrefix !== '') {
            $parentNamespace = $element->parentNode instanceof \DOMElement
                ? $element->parentNode->lookupNamespaceURI($elementPrefix)
                : null;
            if ($parentNamespace !== $element->namespaceURI) {
                $attrs['xmlns:' . $elementPrefix] = $element->namespaceURI;
            }
        } elseif ($element->namespaceURI !== null && $elementPrefix === '') {
            $parentNamespace = $element->parentNode instanceof \DOMElement
                ? $element->parentNode->lookupNamespaceURI(null)
                : null;
            if ($parentNamespace !== $element->namespaceURI) {
                $attrs['xmlns'] = $element->namespaceURI;
            }
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr || $attribute->prefix === '' || $attribute->namespaceURI === null) {
                continue;
            }

            $name = 'xmlns:' . $attribute->prefix;
            if (isset($attrs[$name])) {
                continue;
            }

            $parentNamespace = $element->parentNode instanceof \DOMElement
                ? $element->parentNode->lookupNamespaceURI($attribute->prefix)
                : null;
            if ($parentNamespace !== $attribute->namespaceURI) {
                $attrs[$name] = $attribute->namespaceURI;
            }
        }

        return $attrs;
    }

    private static function isUnsafeUrl(string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')));
        if ($normalized === '' || !str_contains($normalized, ':')) {
            return false;
        }

        [$scheme] = explode(':', $normalized, 2);

        return in_array($scheme, ['data', 'javascript', 'vbscript'], true);
    }

    private static function containsUnsafeUrlCandidate(string $value): bool
    {
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (self::isUnsafeUrl($candidate)) {
                return true;
            }

            $parts = preg_split('/\s+/', $candidate, 2);
            $url = is_array($parts) ? ($parts[0] ?? '') : $candidate;
            if ($url !== '' && self::isUnsafeUrl($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function collectElementNames(array $nodes, array &$names): void
    {
        foreach ($nodes as $node) {
            if ($node->type === 'dom_element') {
                $names[] = (string) $node->attr('name', '');
            }
            self::collectElementNames($node->children, $names);
        }
    }

    private static function nodeTextContent(AstNode $node): string
    {
        if ($node->type === 'dom_text') {
            return (string) $node->attr('text', '');
        }

        $text = '';
        foreach ($node->children as $child) {
            $text .= self::nodeTextContent($child);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function serializeNodesAsHtml(array $nodes): string
    {
        return implode('', array_map(self::serializeNodeAsHtml(...), $nodes));
    }

    private static function serializeNodeAsHtml(AstNode $node): string
    {
        if ($node->type === 'dom_text') {
            return htmlspecialchars((string) $node->attr('text', ''), ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        if ($node->type === 'dom_comment') {
            $text = str_replace('--', '- -', (string) $node->attr('text', ''));
            if (str_ends_with($text, '-')) {
                $text .= ' ';
            }

            return '<!--' . $text . '-->';
        }

        if ($node->type !== 'dom_element') {
            return '';
        }

        $name = strtolower((string) $node->attr('name', ''));
        $start = '<' . $name . self::serializeAttributes($node->attr('attributes', []), true) . '>';
        if ($node->children === [] && in_array($name, self::HTML_VOID_ELEMENTS, true)) {
            return $start;
        }

        return $start . self::serializeNodesAsHtml($node->children) . '</' . $name . '>';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function serializeNodesAsXml(array $nodes): string
    {
        return implode('', array_map(self::serializeNodeAsXml(...), $nodes));
    }

    private static function serializeNodeAsXml(AstNode $node): string
    {
        if ($node->type === 'dom_text') {
            return htmlspecialchars((string) $node->attr('text', ''), ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
        }

        if ($node->type === 'dom_comment') {
            $text = str_replace('--', '- -', (string) $node->attr('text', ''));
            if (str_ends_with($text, '-')) {
                $text .= ' ';
            }

            return '<!--' . $text . '-->';
        }

        if ($node->type !== 'dom_element') {
            return '';
        }

        $name = (string) $node->attr('name', '');
        $start = '<' . $name . self::serializeAttributes($node->attr('attributes', []), false);
        if ($node->children === []) {
            return $start . '/>';
        }

        return $start . '>' . self::serializeNodesAsXml($node->children) . '</' . $name . '>';
    }

    /**
     * @param mixed $attributes
     */
    private static function serializeAttributes(mixed $attributes, bool $html): string
    {
        if (!is_array($attributes)) {
            return '';
        }

        $serialized = '';
        foreach ($attributes as $name => $value) {
            $name = (string) $name;
            if ($value === true && $html) {
                $serialized .= ' ' . $name;
                continue;
            }

            $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ($html ? ENT_HTML5 : ENT_XML1), 'UTF-8');
            $serialized .= ' ' . $name . '="' . $escaped . '"';
        }

        return $serialized;
    }
}
