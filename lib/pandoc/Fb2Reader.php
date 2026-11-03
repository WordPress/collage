<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Fb2Reader
{
    private int $sectionLevel = 1;

    /** @var array<string, list<AstNode>> */
    private array $notes = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var list<string> */
    private array $authors = [];

    public function read(string $xml): AstNode
    {
        $document = XmlHtmlDom::loadXmlDocument($xml, 'FB2 document', false);
        $root = XmlHtmlDom::rootElement($document, 'FictionBook');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('FB2 document must have a FictionBook root');
        }

        $this->sectionLevel = 1;
        $this->notes = [];
        $this->meta = [];
        $this->authors = [];

        foreach ($this->childElements($root, 'body') as $body) {
            if ($body->getAttribute('name') === 'notes') {
                $this->parseNotesBody($body);
            }
        }

        $blocks = [];
        foreach ($this->childElements($root, null) as $child) {
            if ($child->localName === 'description') {
                $this->parseDescription($child);
                continue;
            }
            if ($child->localName === 'body' && $child->getAttribute('name') !== 'notes') {
                array_push($blocks, ...$this->parseBody($child));
            }
        }

        if ($this->authors !== []) {
            $this->meta['author'] = $this->authors;
        }

        return new AstNode('document', [
            'sourceFormat' => 'fb2',
            'meta' => $this->meta,
            'fb2' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-fb2-reader-golden-fixtures',
                'sourceBytes' => strlen($xml),
                'upstreamEvidence' => [
                    'denominator' => 6,
                    'fixtures' => [
                        'test/fb2/reader/emphasis.fb2',
                        'test/fb2/reader/titles.fb2',
                        'test/fb2/reader/epigraph.fb2',
                        'test/fb2/reader/poem.fb2',
                        'test/fb2/reader/meta.fb2',
                        'test/fb2/reader/notes.fb2',
                    ],
                    'source' => 'Pandoc 912bfa5e Text.Pandoc.Readers.FB2 and test/Tests/Readers/FB2.hs',
                ],
            ],
        ], $blocks);
    }

    private function parseDescription(\DOMElement $description): void
    {
        foreach ($this->childElements($description, 'title-info') as $titleInfo) {
            foreach ($this->childElements($titleInfo, null) as $child) {
                match ($child->localName) {
                    'author' => $this->authors[] = $this->parseAuthor($child),
                    'book-title' => $this->meta['title'] = $this->textContent($child),
                    'annotation' => $this->meta['abstract'] = ['type' => 'MetaBlocks', 'value' => $this->parseAnnotation($child)],
                    'keywords' => $this->meta['keywords'] = [
                        'type' => 'MetaList',
                        'value' => array_map(
                            static fn (string $keyword): array => ['type' => 'MetaString', 'value' => trim($keyword)],
                            array_filter(explode(',', $this->textContent($child)), static fn (string $keyword): bool => trim($keyword) !== '')
                        ),
                    ],
                    'date' => $this->meta['date'] = $this->textContent($child),
                    default => null,
                };
            }
        }
    }

    private function parseAuthor(\DOMElement $author): string
    {
        $parts = [];
        foreach ($this->childElements($author, null) as $child) {
            if (in_array($child->localName, ['first-name', 'middle-name', 'last-name', 'nickname', 'home-page', 'email'], true)) {
                $text = $this->textContent($child);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode(' ', $parts);
    }

    private function parseNotesBody(\DOMElement $body): void
    {
        foreach ($this->childElements($body, 'section') as $section) {
            $id = $section->getAttribute('id');
            if ($id === '') {
                continue;
            }

            $blocks = [];
            $skipTitle = true;
            foreach ($this->childElements($section, null) as $child) {
                if ($skipTitle && $child->localName === 'title') {
                    $skipTitle = false;
                    continue;
                }
                $skipTitle = false;
                array_push($blocks, ...$this->parseSectionChild($child));
            }
            $this->notes['#' . $id] = $blocks;
        }
    }

    /**
     * @return list<AstNode>
     */
    private function parseBody(\DOMElement $body): array
    {
        $blocks = [];
        foreach ($this->childElements($body, null) as $child) {
            array_push($blocks, ...$this->parseBodyChild($child));
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBodyChild(\DOMElement $child): array
    {
        return match ($child->localName) {
            'title' => [$this->header($this->sectionLevel, $this->parseTitleInlines($child))],
            'epigraph' => [$this->parseEpigraph($child)],
            'section' => [$this->parseSection($child)],
            'image' => [$this->paragraph([$this->parseImage($child)])],
            default => [],
        };
    }

    private function parseSection(\DOMElement $section): AstNode
    {
        $previousLevel = $this->sectionLevel;
        $this->sectionLevel++;
        $children = [];
        foreach ($this->childElements($section, null) as $child) {
            array_push($children, ...$this->parseSectionChild($child));
        }
        $this->sectionLevel = $previousLevel;

        return new AstNode('div', [
            'id' => $section->getAttribute('id'),
            'classes' => ['section'],
        ], $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseSectionChild(\DOMElement $child): array
    {
        return match ($child->localName) {
            'title' => [$this->header($this->sectionLevel, $this->parseTitleInlines($child))],
            'epigraph' => [$this->parseEpigraph($child)],
            'annotation' => $this->parseAnnotation($child),
            'poem' => $this->parsePoem($child),
            'subtitle' => [$this->header($this->sectionLevel, $this->parseInlinesFromElement($child), ['classes' => ['unnumbered']])],
            'p' => [$this->paragraph($this->parseInlinesFromElement($child))],
            'section' => [$this->parseSection($child)],
            'image' => [$this->paragraph([$this->parseImage($child)])],
            'empty-line' => [new AstNode('horizontal_rule')],
            default => [],
        };
    }

    /**
     * @return list<AstNode>
     */
    private function parseAnnotation(\DOMElement $annotation): array
    {
        $blocks = [];
        foreach ($this->childElements($annotation, null) as $child) {
            array_push($blocks, ...match ($child->localName) {
                'p' => [$this->paragraph($this->parseInlinesFromElement($child))],
                'poem' => $this->parsePoem($child),
                'epigraph' => [$this->parseEpigraph($child)],
                'subtitle' => [$this->header($this->sectionLevel, $this->parseInlinesFromElement($child), ['classes' => ['unnumbered']])],
                'empty-line' => [new AstNode('horizontal_rule')],
                default => [],
            });
        }

        return $blocks;
    }

    private function parseEpigraph(\DOMElement $epigraph): AstNode
    {
        $children = [];
        foreach ($this->childElements($epigraph, null) as $child) {
            array_push($children, ...match ($child->localName) {
                'p', 'text-author' => [$this->paragraph($this->parseInlinesFromElement($child))],
                'poem' => $this->parsePoem($child),
                'empty-line' => [new AstNode('horizontal_rule')],
                default => [],
            });
        }

        return new AstNode('div', [
            'id' => $epigraph->getAttribute('id'),
            'classes' => ['epigraph'],
        ], $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parsePoem(\DOMElement $poem): array
    {
        $blocks = [];
        foreach ($this->childElements($poem, null) as $child) {
            array_push($blocks, ...match ($child->localName) {
                'title' => [$this->header($this->sectionLevel, $this->parseTitleInlines($child))],
                'subtitle' => [$this->header($this->sectionLevel, $this->parseInlinesFromElement($child), ['classes' => ['unnumbered']])],
                'epigraph' => [$this->parseEpigraph($child)],
                'stanza' => $this->parseStanza($child),
                'text-author', 'date' => [$this->paragraph($this->parseInlinesFromElement($child))],
                default => [],
            });
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseStanza(\DOMElement $stanza): array
    {
        $blocks = [];
        $pendingLines = [];
        $flushLines = function () use (&$blocks, &$pendingLines): void {
            if ($pendingLines !== []) {
                $blocks[] = new AstNode('line_block', [], $pendingLines);
                $pendingLines = [];
            }
        };

        foreach ($this->childElements($stanza, null) as $child) {
            if ($child->localName === 'v') {
                $pendingLines[] = new AstNode('line', [], $this->parseInlinesFromElement($child));
                continue;
            }

            $flushLines();
            if ($child->localName === 'title') {
                $blocks[] = $this->header($this->sectionLevel, $this->parseTitleInlines($child));
            } elseif ($child->localName === 'subtitle') {
                $blocks[] = $this->header($this->sectionLevel, $this->parseInlinesFromElement($child), ['classes' => ['unnumbered']]);
            }
        }
        $flushLines();

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseTitleInlines(\DOMElement $title): array
    {
        $inlines = [];
        $first = true;
        foreach ($this->childElements($title, null) as $child) {
            if ($child->localName !== 'p' && $child->localName !== 'empty-line') {
                continue;
            }
            if (!$first) {
                $inlines[] = new AstNode('linebreak');
            }
            $first = false;
            if ($child->localName === 'p') {
                array_push($inlines, ...$this->parseInlinesFromElement($child));
            }
        }

        return $inlines;
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlinesFromElement(\DOMElement $element): array
    {
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = $child->nodeValue ?? '';
                if ($text !== '') {
                    $this->appendText($inlines, $text);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $mapped = match ($child->localName) {
                'strong' => [new AstNode('strong', [], $this->parseInlinesFromElement($child))],
                'emphasis' => [new AstNode('emph', [], $this->parseInlinesFromElement($child))],
                'strikethrough' => [new AstNode('strikeout', [], $this->parseInlinesFromElement($child))],
                'sub' => [new AstNode('subscript', [], $this->parseInlinesFromElement($child))],
                'sup' => [new AstNode('superscript', [], $this->parseInlinesFromElement($child))],
                'code' => [new AstNode('code', ['text' => $this->textContent($child)])],
                'style' => [new AstNode('span', [
                    'classes' => $child->hasAttribute('name') ? [$child->getAttribute('name')] : [],
                ], $this->parseInlinesFromElement($child))],
                'a' => [$this->parseLink($child)],
                'image' => [$this->parseImage($child)],
                default => [],
            };
            array_push($inlines, ...array_filter($mapped));
        }

        return $inlines;
    }

    private function parseLink(\DOMElement $link): ?AstNode
    {
        $href = $link->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        if ($href === '') {
            $href = $link->getAttribute('href');
        }
        if ($href === '') {
            return null;
        }

        $content = $this->parseInlinesFromElement($link);
        if ($link->getAttribute('type') === 'note' && isset($this->notes[$href])) {
            return new AstNode('note', [], $this->notes[$href]);
        }

        return new AstNode('link', ['url' => $href], $content);
    }

    private function parseImage(\DOMElement $image): AstNode
    {
        $href = $image->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        if ($href === '') {
            $href = $image->getAttribute('href');
        }
        $src = ltrim($href, '#');
        $alt = $image->getAttribute('alt');

        return new AstNode('image', [
            'id' => $image->getAttribute('id'),
            'url' => $src,
            'src' => $src,
            'title' => $image->getAttribute('title'),
            'alt' => $alt,
        ], $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, mixed> $attrs
     */
    private function header(int $level, array $children, array $attrs = []): AstNode
    {
        return new AstNode('heading', ['level' => $level] + $attrs, $children);
    }

    /**
     * @param list<AstNode> $children
     */
    private function paragraph(array $children): AstNode
    {
        return new AstNode('paragraph', [], $children);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function appendText(array &$inlines, string $text): void
    {
        $last = $inlines[count($inlines) - 1] ?? null;
        if ($last instanceof AstNode && $last->type === 'text') {
            $attrs = $last->attrs;
            $attrs['text'] = (string) ($attrs['text'] ?? '') . $text;
            $inlines[count($inlines) - 1] = new AstNode('text', $attrs);
            return;
        }

        $inlines[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, ?string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && ($localName === null || $child->localName === $localName)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function textContent(\DOMElement $element): string
    {
        return trim($element->textContent);
    }
}
