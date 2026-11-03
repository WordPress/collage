<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpmlReader
{
    /** @var array<string, mixed> */
    private array $documentMeta = [];
    private int $outlineCount = 0;
    private int $linkOutlineCount = 0;
    private int $noteCount = 0;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $this->documentMeta = [];
        $this->outlineCount = 0;
        $this->linkOutlineCount = 0;
        $this->noteCount = 0;

        $dom = XmlHtmlDom::loadXmlDocument($bytes, 'OPML input', false);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('OPML reader requires a document element.');
        }

        $blocks = $this->blocksFromElement($root, 0);
        $meta = array_replace([
            'sourceFormat' => 'opml',
            'reader' => self::class,
            'readerScope' => 'bounded-opml-reader',
            'sourceBytes' => strlen($bytes),
            'sourceSha256' => hash('sha256', $bytes),
            'rootName' => $this->name($root),
            'rootAttributes' => $this->attributes($root),
            'opmlOutlineCount' => $this->outlineCount,
            'opmlLinkOutlineCount' => $this->linkOutlineCount,
            'opmlNoteCount' => $this->noteCount,
            'payloadExposurePolicy' => 'opml-xml-text-and-structural-metadata-only',
        ], $this->documentMeta);

        return new AstNode('document', [
            'sourceFormat' => 'opml',
            'meta' => $meta,
        ], $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function blocksFromElement(\DOMElement $element, int $sectionLevel): array
    {
        $name = $this->name($element);
        if ($name === 'ownername') {
            $author = $this->cleanText($element->textContent);
            if ($author !== '') {
                $this->documentMeta['author'] = [$author];
                $this->documentMeta['authors'] = [$author];
                $this->documentMeta['authorInlines'] = [$this->textInlines($author)];
            }

            return [];
        }
        if ($name === 'datemodified') {
            $date = $this->cleanText($element->textContent);
            if ($date !== '') {
                $this->documentMeta['date'] = $date;
                $this->documentMeta['dateInlines'] = $this->textInlines($date);
            }

            return [];
        }
        if ($name === 'title') {
            $title = $this->cleanText($element->textContent);
            if ($title !== '') {
                $this->documentMeta['title'] = $title;
                $this->documentMeta['titleInlines'] = $this->textInlines($title);
            }

            return [];
        }
        if ($name === 'outline') {
            return $this->outlineBlocks($element, $sectionLevel + 1);
        }

        return $this->childBlocks($element, $sectionLevel);
    }

    /**
     * @return list<AstNode>
     */
    private function outlineBlocks(\DOMElement $outline, int $level): array
    {
        $this->outlineCount++;
        $level = max(1, min(6, $level));
        $inlines = $this->htmlTextInlines($outline->getAttribute('text'));
        $text = $this->plainInlineText($inlines);
        if (strcasecmp(trim($outline->getAttribute('type')), 'link') === 0) {
            $this->linkOutlineCount++;
            $inlines = [new AstNode('link', ['url' => trim($outline->getAttribute('url')), 'title' => ''], $inlines)];
        }

        $blocks = [
            new AstNode('heading', [
                'level' => $level,
                'text' => $text,
                'sourceFormat' => 'opml',
            ], $inlines),
        ];

        $note = $outline->getAttribute('_note');
        if (trim($note) !== '') {
            $this->noteCount++;
            array_push($blocks, ...(new MarkdownReader($this->options))->read($note)->children);
        }

        array_push($blocks, ...$this->childBlocks($outline, $level));

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function childBlocks(\DOMElement $element, int $sectionLevel): array
    {
        $blocks = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blocksFromElement($child, $sectionLevel));
            }
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function htmlTextInlines(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        try {
            $fragment = XmlHtmlDom::loadHtmlFragment($html, 'OPML outline text');
            $root = XmlHtmlDom::fragmentRoot($fragment);
            $inlines = $root instanceof \DOMElement ? $this->inlineNodes($root) : [];
            if ($inlines !== []) {
                return $inlines;
            }
        } catch (\Throwable) {
        }

        return $this->textInlines(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextNode($nodes, preg_replace('/\s+/u', ' ', $child->nodeValue ?? '') ?? '');
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $this->name($child);
            if ($name === 'br') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($name === 'img') {
                $alt = $this->cleanText($child->getAttribute('alt'));
                $nodes[] = new AstNode('image', [
                    'src' => trim($child->getAttribute('src')),
                    'title' => $child->getAttribute('title'),
                    'alt' => $alt,
                ], $this->textInlines($alt));
                continue;
            }

            $children = $this->inlineNodes($child);
            if ($children === []) {
                $children = $this->textInlines(XmlHtmlDom::normalizedText($child));
            }
            if ($children === []) {
                continue;
            }

            if ($name === 'a') {
                $nodes[] = new AstNode('link', [
                    'url' => trim($child->getAttribute('href')),
                    'title' => $child->getAttribute('title'),
                ], $children);
                continue;
            }

            $nodes[] = match ($name) {
                'b', 'strong' => new AstNode('strong', [], $children),
                'em', 'i' => new AstNode('emph', [], $children),
                'code', 'kbd', 'samp', 'tt' => new AstNode('code', ['text' => $this->plainInlineText($children)]),
                'del', 's', 'strike' => new AstNode('strikeout', [], $children),
                'mark' => new AstNode('mark', [], $children),
                'sub' => new AstNode('subscript', [], $children),
                'sup' => new AstNode('superscript', [], $children),
                'u' => new AstNode('underline', [], $children),
                'span' => new AstNode('span', $this->nodeAttributes($child), $children),
                default => count($children) === 1 ? $children[0] : new AstNode('span', $this->nodeAttributes($child), $children),
            };
        }

        return $this->trimInlineBoundary($this->coalesceTextNodes($nodes));
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendTextNode(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $lastIndex = array_key_last($coalesced);
                $last = $coalesced[$lastIndex];
                if ($last instanceof AstNode && $last->type === 'text') {
                    $coalesced[$lastIndex] = new AstNode('text', [
                        'text' => (string) $last->attr('text', '') . (string) $node->attr('text', ''),
                    ]);
                    continue;
                }
            }
            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function trimInlineBoundary(array $nodes): array
    {
        if ($nodes === []) {
            return [];
        }

        $first = $nodes[0];
        if ($first->type === 'text') {
            $text = ltrim((string) $first->attr('text', ''));
            if ($text === '') {
                array_shift($nodes);
            } else {
                $nodes[0] = new AstNode('text', ['text' => $text]);
            }
        }
        if ($nodes === []) {
            return [];
        }

        $lastIndex = array_key_last($nodes);
        $last = $nodes[$lastIndex];
        if ($last->type === 'text') {
            $text = rtrim((string) $last->attr('text', ''));
            if ($text === '') {
                array_pop($nodes);
            } else {
                $nodes[$lastIndex] = new AstNode('text', ['text' => $text]);
            }
        }

        return array_values($nodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeAttributes(\DOMElement $element): array
    {
        $attrs = [
            'tagName' => $this->name($element),
        ];
        $attributes = $this->attributes($element);
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $text = $this->cleanText($text);

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'math' => (string) $node->attr('text', ''),
                'image' => (string) $node->attr('alt', $this->plainInlineText($node->children)),
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->cleanText($text);
    }

    private function cleanText(?string $text): string
    {
        $text ??= '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function name(\DOMElement $element): string
    {
        return strtolower($element->localName ?: $element->nodeName);
    }

    /**
     * @return array<string, string>
     */
    private function attributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attr) {
            if ($attr instanceof \DOMAttr) {
                $attrs[$attr->name] = $attr->value;
            }
        }

        return $attrs;
    }
}
