<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpmlWriter
{
    /**
     * @param array{standalone?: bool, preferAscii?: bool, columns?: int, wrap?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('OPML writer expects a document node');
        }

        $index = 0;
        $outlines = $this->renderSections($this->collectSections($document->children, $index, 0), 0);
        $body = implode("\n", $outlines);
        if (($this->options['standalone'] ?? true) === false) {
            return $this->maybePreferAscii($body);
        }

        $meta = $document->attr('meta', []);
        $meta = is_array($meta) ? $meta : [];
        $xml = implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<opml version="2.0">',
            '  <head>',
            '    <title>' . $this->escapeText($this->metaTitle($meta)) . '</title>',
            '    <dateModified>' . $this->escapeText($this->metaDate($meta)) . '</dateModified>',
            '    <ownerName>' . $this->escapeText(implode('; ', $this->metaAuthors($meta))) . '</ownerName>',
            '  </head>',
            '  <body>',
            $body,
            '  </body>',
            '</opml>',
        ]);

        return $this->maybePreferAscii($xml . "\n");
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array{heading:AstNode, content:list<AstNode>, children:array<int, mixed>}>
     */
    private function collectSections(array $blocks, int &$index, int $parentLevel): array
    {
        $sections = [];
        $count = count($blocks);
        while ($index < $count) {
            $block = $blocks[$index];
            if ($block->type !== 'heading') {
                $index++;
                continue;
            }

            $level = $this->headingLevel($block);
            if ($level <= $parentLevel) {
                break;
            }

            $index++;
            $content = [];
            $children = [];
            while ($index < $count) {
                $next = $blocks[$index];
                if ($next->type !== 'heading') {
                    $content[] = $next;
                    $index++;
                    continue;
                }

                $nextLevel = $this->headingLevel($next);
                if ($nextLevel <= $level) {
                    break;
                }

                array_push($children, ...$this->collectSections($blocks, $index, $level));
            }

            $sections[] = [
                'heading' => $block,
                'content' => $content,
                'children' => $children,
            ];
        }

        return $sections;
    }

    private function headingLevel(AstNode $heading): int
    {
        return max(1, min(6, (int) $heading->attr('level', 1)));
    }

    /**
     * @param list<array{heading:AstNode, content:list<AstNode>, children:array<int, mixed>}> $sections
     * @return list<string>
     */
    private function renderSections(array $sections, int $depth): array
    {
        $lines = [];
        foreach ($sections as $section) {
            $lines[] = $this->renderSection($section, $depth);
        }

        return $lines;
    }

    /**
     * @param array{heading:AstNode, content:list<AstNode>, children:array<int, mixed>} $section
     */
    private function renderSection(array $section, int $depth): string
    {
        $heading = $section['heading'];
        $content = $section['content'];
        $children = $section['children'];
        $attrs = [
            'text' => $this->renderHeadingHtmlInlines($heading),
        ];
        if ($content !== []) {
            $attrs['_note'] = $this->renderPlainBlocks($content);
        }

        $indent = str_repeat('  ', $depth);
        $lines = [$indent . '<outline' . $this->renderAttributes($attrs) . '>'];
        foreach ($this->renderSections($children, $depth + 1) as $child) {
            $lines[] = $child;
        }
        $lines[] = $indent . '</outline>';

        return implode("\n", $lines);
    }

    private function renderHeadingHtmlInlines(AstNode $heading): string
    {
        $inlines = $heading->children;
        if ($inlines === []) {
            $text = (string) $heading->attr('text', '');
            $inlines = $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }

        $document = new AstNode('document', [], [
            new AstNode('plain', [], $inlines),
        ]);

        return trim((new HtmlWriter())->write($document));
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderPlainBlocks(array $blocks): string
    {
        $document = new AstNode('document', [], $blocks);

        return rtrim((new PlainWriter($this->plainOptions()))->write($document));
    }

    /**
     * @return array<string, mixed>
     */
    private function plainOptions(): array
    {
        $options = [];
        foreach (['columns', 'wrap'] as $key) {
            if (array_key_exists($key, $this->options)) {
                $options[$key] = $this->options[$key];
            }
        }

        return $options;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function renderAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            if ($value === '') {
                continue;
            }
            $parts[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttribute(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_COMPAT | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = str_replace("\r\n", "\n", $escaped);
        $escaped = str_replace("\r", '&#13;', $escaped);

        return str_replace("\n", '&#10;', $escaped);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function metaTitle(array $meta): string
    {
        if (isset($meta['titleInlines']) && is_array($meta['titleInlines'])) {
            return $this->plainInlineText($this->nodeList($meta['titleInlines']));
        }

        return (string) ($meta['title'] ?? '');
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function metaAuthors(array $meta): array
    {
        if (isset($meta['authorInlines']) && is_array($meta['authorInlines'])) {
            $authors = [];
            foreach ($meta['authorInlines'] as $inlines) {
                if (is_array($inlines)) {
                    $author = $this->plainInlineText($this->nodeList($inlines));
                    if ($author !== '') {
                        $authors[] = $author;
                    }
                }
            }
            if ($authors !== []) {
                return $authors;
            }
        }

        $source = $meta['authors'] ?? $meta['author'] ?? [];
        if (is_string($source)) {
            $source = [$source];
        }
        if (!is_array($source)) {
            return [];
        }

        $authors = [];
        foreach ($source as $author) {
            if (!is_scalar($author)) {
                continue;
            }
            $author = trim((string) $author);
            if ($author !== '') {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function metaDate(array $meta): string
    {
        $date = '';
        if (isset($meta['dateInlines']) && is_array($meta['dateInlines'])) {
            $date = $this->plainInlineText($this->nodeList($meta['dateInlines']));
        }
        if ($date === '') {
            $date = trim((string) ($meta['date'] ?? ''));
        }
        if ($date === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $date .= ' 00:00:00 UTC';
        }

        try {
            return (new \DateTimeImmutable($date))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('D, d M Y H:i:s \U\T\C');
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * @param array<mixed> $nodes
     * @return list<AstNode>
     */
    private function nodeList(array $nodes): array
    {
        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof AstNode));
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

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function maybePreferAscii(string $text): string
    {
        if (($this->options['preferAscii'] ?? false) !== true) {
            return $text;
        }

        return preg_replace_callback('/[^\x00-\x7F]/u', static function (array $match): string {
            $codepoint = mb_ord($match[0], 'UTF-8');

            return $codepoint === false ? $match[0] : '&#' . $codepoint . ';';
        }, $text) ?? $text;
    }
}
