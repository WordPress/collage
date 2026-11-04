<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class RstReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    /** @var array<string, int> */
    private array $sectionLevels = [];

    /**
     * @param array{resourceBasePath?: string, sourceDirectory?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $text): AstNode
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $this->lines = explode("\n", $normalized);
        $this->index = 0;
        $this->sectionLevels = [];

        return new AstNode('document', [
            'sourceFormat' => 'rst',
            'rst' => [
                'reader' => self::class,
                'readerScope' => 'bounded-restructuredtext-reader-core-block-inline-semantics',
                'sourceBytes' => strlen($text),
                'upstreamEvidence' => [
                    'source' => 'Pandoc reStructuredText reader behavior probes and documented reStructuredText core syntax',
                    'readerUnitGroups' => [
                        'section titles',
                        'paragraphs',
                        'inline emphasis strong literal and links',
                        'bullet and enumerated lists',
                        'definition lists',
                        'csv-table directives',
                        'literal and code directives',
                        'image directives',
                    ],
                    'fixtureStatus' => 'Initial native PHP reader slice with csv-table bridge coverage; full Pandoc RST directive/role/table/substitution parity remains open.',
                ],
            ],
        ], $this->parseBlocks());
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(int $baseIndent = 0): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '') {
                ++$this->index;
                continue;
            }
            if ($this->indentOf($line) < $baseIndent) {
                break;
            }

            $section = $this->sectionTitleAt($this->index, $baseIndent);
            if ($section !== null) {
                $this->index += $section['consumed'];
                $inlines = $this->parseInlines($section['title']);
                $blocks[] = new AstNode('heading', [
                    'level' => $this->sectionLevel($section['marker']),
                    'text' => $this->plainInlineText($inlines),
                ], $inlines);
                continue;
            }

            if ($this->isDirective($trimmed, 'image')) {
                $blocks[] = $this->parseImageDirective($trimmed);
                continue;
            }
            if ($this->isDirective($trimmed, 'csv-table')) {
                $blocks[] = $this->parseCsvTableDirective($trimmed);
                continue;
            }
            if ($this->isDirective($trimmed, 'code') || $this->isDirective($trimmed, 'code-block')) {
                $blocks[] = $this->parseCodeDirective($trimmed);
                continue;
            }
            if ($trimmed === '::') {
                ++$this->index;
                $blocks[] = $this->parseIndentedCodeBlock($baseIndent + 1);
                continue;
            }
            if ($this->isTransitionLine($trimmed)) {
                ++$this->index;
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            if ($this->isListLine($line, $baseIndent)) {
                $blocks[] = $this->parseListBlock($baseIndent);
                continue;
            }
            if ($this->isFieldListLine($line, $baseIndent)) {
                $blocks[] = $this->parseFieldList($baseIndent);
                continue;
            }
            if ($this->isDefinitionListStart($baseIndent)) {
                $blocks[] = $this->parseDefinitionList($baseIndent);
                continue;
            }
            if ($this->indentOf($line) > $baseIndent) {
                $blocks[] = new AstNode('blockquote', [], $this->parseBlocks($this->indentOf($line)));
                continue;
            }

            array_push($blocks, ...$this->parseParagraphOrLiteral($baseIndent));
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseParagraphOrLiteral(int $baseIndent): array
    {
        $parts = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '' || $this->indentOf($line) < $baseIndent) {
                break;
            }
            if ($parts !== [] && $this->isBlockStart($line, $baseIndent)) {
                break;
            }
            $parts[] = trim($line);
            ++$this->index;
        }

        $text = implode(' ', $parts);
        $literalIndent = $this->nextIndentedLineIndent($this->index);
        if (str_ends_with($text, '::') && $literalIndent !== null && $literalIndent > $baseIndent) {
            $paragraphText = rtrim(substr($text, 0, -1));
            $blocks = [];
            if ($paragraphText !== '') {
                $blocks[] = $this->paragraphFromText($paragraphText);
            }
            $blocks[] = $this->parseIndentedCodeBlock($literalIndent);

            return $blocks;
        }

        return [$this->paragraphFromText($text)];
    }

    private function isBlockStart(string $line, int $baseIndent): bool
    {
        $trimmed = trim($line);

        return $this->sectionTitleAt($this->index, $baseIndent) !== null
            || $this->isDirective($trimmed, 'image')
            || $this->isDirective($trimmed, 'csv-table')
            || $this->isDirective($trimmed, 'code')
            || $this->isDirective($trimmed, 'code-block')
            || $trimmed === '::'
            || $this->isTransitionLine($trimmed)
            || $this->isListLine($line, $baseIndent)
            || $this->isFieldListLine($line, $baseIndent)
            || $this->isDefinitionListStart($baseIndent);
    }

    /**
     * @return array{title:string, marker:string, consumed:int}|null
     */
    private function sectionTitleAt(int $offset, int $baseIndent): ?array
    {
        $line = $this->lines[$offset] ?? null;
        if (!is_string($line) || trim($line) === '' || $this->indentOf($line) !== $baseIndent) {
            return null;
        }

        $next = $this->lines[$offset + 1] ?? null;
        if (is_string($next) && $this->isAdornmentLine(trim($next), trim($line))) {
            return [
                'title' => trim($line),
                'marker' => trim($next)[0],
                'consumed' => 2,
            ];
        }

        if (!$this->isAdornmentOnly(trim($line))) {
            return null;
        }

        $title = $this->lines[$offset + 1] ?? null;
        $closing = $this->lines[$offset + 2] ?? null;
        if (
            !is_string($title)
            || !is_string($closing)
            || trim($title) === ''
            || $this->indentOf($title) !== $baseIndent
            || trim($closing) !== trim($line)
        ) {
            return null;
        }

        return [
            'title' => trim($title),
            'marker' => trim($line)[0],
            'consumed' => 3,
        ];
    }

    private function sectionLevel(string $marker): int
    {
        if (!isset($this->sectionLevels[$marker])) {
            $this->sectionLevels[$marker] = count($this->sectionLevels) + 1;
        }

        return min(6, $this->sectionLevels[$marker]);
    }

    private function isAdornmentLine(string $line, string $title): bool
    {
        return $this->isAdornmentOnly($line) && strlen($line) >= min(3, max(1, strlen($title)));
    }

    private function isAdornmentOnly(string $line): bool
    {
        return preg_match('/^([=\\-`:\'"~^_*+#<>])\\1{2,}$/u', $line) === 1;
    }

    private function isTransitionLine(string $trimmed): bool
    {
        return $this->isAdornmentOnly($trimmed);
    }

    private function isDirective(string $trimmed, string $name): bool
    {
        return preg_match('/^\\.\\.\\s+' . preg_quote($name, '/') . '::(?:\\s+.*)?$/u', $trimmed) === 1;
    }

    private function parseImageDirective(string $trimmed): AstNode
    {
        preg_match('/^\\.\\.\\s+image::\\s*(.*)$/u', $trimmed, $match);
        $url = trim((string) ($match[1] ?? ''));
        ++$this->index;
        $options = $this->parseDirectiveOptions();
        $alt = $options['alt'] ?? '';
        $attrs = ['url' => $url, 'title' => '', 'alt' => $alt];
        $attributes = [];
        foreach ($options as $key => $value) {
            if ($key !== 'alt') {
                $attributes[$key] = $value;
            }
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return new AstNode('paragraph', ['text' => $alt], [
            new AstNode('image', $attrs, $this->textInlines($alt)),
        ]);
    }

    private function parseCodeDirective(string $trimmed): AstNode
    {
        preg_match('/^\\.\\.\\s+(?:code|code-block)::\\s*(.*)$/u', $trimmed, $match);
        $language = trim((string) ($match[1] ?? ''));
        ++$this->index;
        $this->parseDirectiveOptions();

        return $this->parseIndentedCodeBlock($this->nextIndentedLineIndent($this->index) ?? 1, $language);
    }

    private function parseCsvTableDirective(string $trimmed): AstNode
    {
        preg_match('/^\\.\\.\\s+csv-table::\\s*(.*)$/u', $trimmed, $match);
        $caption = trim((string) ($match[1] ?? ''));
        ++$this->index;
        $options = $this->parseDirectiveOptions();
        $body = $this->collectIndentedBlock($this->nextIndentedLineIndent($this->index) ?? 1);
        $header = trim((string) ($options['header'] ?? ''));
        $file = trim((string) ($options['file'] ?? ''));
        $fileEvidence = null;
        if ($file !== '') {
            $fileEvidence = $this->csvTableFileEvidence($file);
            $body = is_string($fileEvidence['contents']) ? $fileEvidence['contents'] : '';
        }
        $source = $header === '' ? $body : $header . "\n" . $body;
        $headerRows = $this->csvTableHeaderRows($options['header-rows'] ?? null);
        $readerOptions = [
            'cellLineBreak' => 'softbreak',
            'header' => $header !== '' || $headerRows > 0,
            'strictParsing' => false,
        ];
        if (isset($options['delim']) && $options['delim'] !== '') {
            $readerOptions['delimiter'] = $options['delim'];
        }
        if (isset($options['quote']) && $options['quote'] !== '') {
            $readerOptions['quote'] = $options['quote'];
        }
        if (isset($options['escape']) && $options['escape'] !== '') {
            $readerOptions['escape'] = $options['escape'];
        }
        if ($file !== '') {
            $readerOptions['sourcePath'] = $file;
        }
        $document = (new DelimitedTextReader())->readCsv($source, $readerOptions);
        $table = $document->children[0] ?? new AstNode('table');
        $packet = is_array($table->attr('delimitedText')) ? $table->attr('delimitedText') : [];
        $compactTable = $table->attributeResolver() instanceof CompactDelimitedTableAttributes;
        $attrs = array_replace($compactTable ? $table->baseAttrs() : $table->attrs, [
            'sourceFormat' => 'rst-csv-table',
            'caption' => $caption,
            'rstDirective' => 'csv-table',
            'rstCsvTable' => [
                'caption' => $caption,
                'headerOption' => $header !== '',
                'headerRowsOption' => $headerRows,
                'fileOption' => $file === '' ? null : $file,
                'file' => $fileEvidence === null ? null : [
                    'path' => $fileEvidence['path'],
                    'resolvedPath' => $fileEvidence['resolvedPath'],
                    'present' => $fileEvidence['present'],
                    'sha256' => $fileEvidence['sha256'],
                    'bytes' => $fileEvidence['bytes'],
                ],
                'optionNames' => array_keys($options),
                'bodyLineCount' => $body === '' ? 0 : count(explode("\n", rtrim($body, "\n"))),
                'delimitedText' => $packet,
            ],
        ]);
        $widths = $this->csvTableWidths($options['widths'] ?? null, count(is_array($attrs['columnNames'] ?? null) ? $attrs['columnNames'] : []));
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return new AstNode(
            'table',
            $attrs,
            $table->children,
            $compactTable ? new CompactDelimitedTableAttributes() : null
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseDirectiveOptions(): array
    {
        $options = [];
        while ($this->index < count($this->lines) && trim($this->lines[$this->index]) === '') {
            ++$this->index;
        }
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            if (preg_match('/^\\s+:([A-Za-z0-9_-]+):\\s*(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $options[strtolower($match[1])] = trim($match[2]);
            ++$this->index;
        }
        while ($this->index < count($this->lines) && trim($this->lines[$this->index]) === '') {
            ++$this->index;
        }

        return $options;
    }

    /**
     * @return array{path: string, resolvedPath: ?string, present: bool, sha256: ?string, bytes: ?int, contents: ?string}
     */
    private function csvTableFileEvidence(string $path): array
    {
        $resolved = $this->resolveCsvTableFilePath($path);
        $present = $resolved !== null && is_file($resolved);
        $contents = $present ? file_get_contents((string) $resolved) : null;

        return [
            'path' => $path,
            'resolvedPath' => $resolved,
            'present' => $present,
            'sha256' => $present ? hash_file('sha256', (string) $resolved) : null,
            'bytes' => $present ? filesize((string) $resolved) : null,
            'contents' => is_string($contents) ? $contents : null,
        ];
    }

    private function resolveCsvTableFilePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        $base = $this->options['resourceBasePath'] ?? $this->options['sourceDirectory'] ?? null;
        if (!is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function csvTableHeaderRows(mixed $value): int
    {
        if (!is_string($value)) {
            return 0;
        }

        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * @return list<float>|null
     */
    private function csvTableWidths(mixed $value, int $columnCount): ?array
    {
        if (!is_string($value)) {
            return null;
        }

        $parts = array_values(array_filter(
            preg_split('/[,\s]+/', trim($value)) ?: [],
            static fn (string $part): bool => $part !== ''
        ));
        if ($parts === []) {
            return null;
        }

        $weights = [];
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                return null;
            }
            $weights[] = max(0.0, (float) $part);
        }

        $sum = array_sum($weights);
        if ($sum <= 0.0) {
            return null;
        }
        if ($columnCount > 0 && count($weights) !== $columnCount) {
            return null;
        }

        return array_map(
            static fn (float $weight): float => $weight / $sum,
            $weights
        );
    }

    private function parseIndentedCodeBlock(int $minimumIndent, string $language = ''): AstNode
    {
        while ($this->index < count($this->lines) && trim($this->lines[$this->index]) === '') {
            ++$this->index;
        }

        $body = [];
        $baseIndent = $this->nextIndentedLineIndent($this->index) ?? $minimumIndent;
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (trim($line) === '') {
                $body[] = '';
                ++$this->index;
                continue;
            }
            if ($this->indentOf($line) < $baseIndent) {
                break;
            }
            $body[] = substr($line, min($baseIndent, strlen($line)));
            ++$this->index;
        }

        while ($body !== [] && end($body) === '') {
            array_pop($body);
        }

        return new AstNode('code_block', [
            'text' => implode("\n", $body),
            'classes' => $language === '' ? [] : [$language],
        ]);
    }

    private function collectIndentedBlock(int $minimumIndent): string
    {
        while ($this->index < count($this->lines) && trim($this->lines[$this->index]) === '') {
            ++$this->index;
        }

        $body = [];
        $baseIndent = $this->nextIndentedLineIndent($this->index) ?? $minimumIndent;
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (trim($line) === '') {
                $body[] = '';
                ++$this->index;
                continue;
            }
            if ($this->indentOf($line) < $baseIndent) {
                break;
            }
            $body[] = substr($line, min($baseIndent, strlen($line)));
            ++$this->index;
        }

        while ($body !== [] && end($body) === '') {
            array_pop($body);
        }

        return implode("\n", $body);
    }

    private function isListLine(string $line, int $baseIndent): bool
    {
        if ($this->indentOf($line) !== $baseIndent) {
            return false;
        }

        return preg_match('/^\\s*(?:[-+*]|(?:\\d+|#)[.)])\\s+\\S/u', $line) === 1;
    }

    private function parseListBlock(int $baseIndent): AstNode
    {
        $items = [];
        $ordered = false;
        $listStyle = null;
        $blankTerminated = false;
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if ($this->indentOf($line) !== $baseIndent) {
                break;
            }
            if (preg_match('/^\\s*((?:\\d+|#)[.)]|[-+*])\\s+(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $marker = $match[1];
            $ordered = preg_match('/^(?:\\d+|#)[.)]$/u', $marker) === 1;
            $style = $ordered ? 'ordered' : 'bullet';
            if ($listStyle !== null && $style !== $listStyle) {
                break;
            }
            $listStyle ??= $style;
            $text = rtrim($match[2]);
            ++$this->index;
            $continuation = [];
            while ($this->index < count($this->lines)) {
                $next = rtrim($this->lines[$this->index], "\n");
                if (trim($next) === '') {
                    ++$this->index;
                    $blankTerminated = true;
                    break;
                }
                if ($this->indentOf($next) <= $baseIndent) {
                    break;
                }
                $continuation[] = trim($next);
                ++$this->index;
            }
            if ($continuation !== []) {
                $text .= ' ' . implode(' ', $continuation);
            }
            $inlines = $this->parseInlines($text);
            $items[] = new AstNode('list_item', ['loose' => false], [
                new AstNode('plain', ['text' => $this->plainInlineText($inlines)], $inlines),
            ]);
            if ($blankTerminated) {
                break;
            }
        }

        if ($listStyle === 'ordered') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => false,
            ], $items);
        }

        return new AstNode('bullet_list', ['loose' => false], $items);
    }

    private function isFieldListLine(string $line, int $baseIndent): bool
    {
        return $this->indentOf($line) === $baseIndent
            && preg_match('/^\\s*:([^:]+):\\s+(.*)$/u', $line) === 1;
    }

    private function parseFieldList(int $baseIndent): AstNode
    {
        $items = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if ($this->indentOf($line) !== $baseIndent || preg_match('/^\\s*:([^:]+):\\s+(.*)$/u', $line, $match) !== 1) {
                break;
            }
            ++$this->index;
            $items[] = $this->definitionItem(trim($match[1]), trim($match[2]));
        }

        return new AstNode('definition_list', [], $items);
    }

    private function isDefinitionListStart(int $baseIndent): bool
    {
        $line = $this->lines[$this->index] ?? '';
        $next = $this->lines[$this->index + 1] ?? '';

        return is_string($line)
            && is_string($next)
            && trim($line) !== ''
            && $this->indentOf($line) === $baseIndent
            && $this->indentOf($next) > $baseIndent
            && !$this->isBlockStartCandidate(trim($line));
    }

    private function parseDefinitionList(int $baseIndent): AstNode
    {
        $items = [];
        while ($this->isDefinitionListStart($baseIndent)) {
            $term = trim($this->lines[$this->index]);
            ++$this->index;
            $definitionParts = [];
            while ($this->index < count($this->lines)) {
                $line = rtrim($this->lines[$this->index], "\n");
                if (trim($line) === '') {
                    ++$this->index;
                    break;
                }
                if ($this->indentOf($line) <= $baseIndent) {
                    break;
                }
                $definitionParts[] = trim($line);
                ++$this->index;
            }
            $items[] = $this->definitionItem($term, implode(' ', $definitionParts));
        }

        return new AstNode('definition_list', [], $items);
    }

    private function definitionItem(string $term, string $definition): AstNode
    {
        $termInlines = $this->parseInlines($term);
        $definitionInlines = $this->parseInlines($definition);

        return new AstNode('definition_item', ['term' => $this->plainInlineText($termInlines)], [
            new AstNode('definition_term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
            new AstNode('definition', [], [
                new AstNode('paragraph', ['text' => $this->plainInlineText($definitionInlines)], $definitionInlines),
            ]),
        ]);
    }

    private function isBlockStartCandidate(string $trimmed): bool
    {
        return $trimmed === '::'
            || $this->isAdornmentOnly($trimmed)
            || str_starts_with($trimmed, '.. ')
            || preg_match('/^(?:[-+*]|(?:\\d+|#)[.)])\\s+\\S/u', $trimmed) === 1
            || preg_match('/^:[^:]+:\\s+/u', $trimmed) === 1;
    }

    private function paragraphFromText(string $text): AstNode
    {
        $inlines = $this->parseInlines($text);

        return new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $pattern = '/(``([^`]+)``|`([^`<]+?)\\s*<([^>]+)>`_|\\*\\*([^*]+)\\*\\*|(?<!\\*)\\*([^*]+)\\*(?!\\*)|(https?:\\/\\/[^\\s<]+))/u';
        $nodes = [];
        $offset = 0;
        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $index => $match) {
                [$source, $position] = $match;
                if ($position > $offset) {
                    array_push($nodes, ...$this->textInlines(substr($text, $offset, $position - $offset)));
                }
                if (($matches[2][$index][1] ?? -1) >= 0) {
                    $nodes[] = new AstNode('code', ['text' => $matches[2][$index][0]]);
                } elseif (($matches[3][$index][1] ?? -1) >= 0) {
                    $label = trim($matches[3][$index][0]);
                    $url = trim($matches[4][$index][0]);
                    $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], $this->textInlines($label));
                } elseif (($matches[5][$index][1] ?? -1) >= 0) {
                    $nodes[] = new AstNode('strong', [], $this->parseInlines($matches[5][$index][0]));
                } elseif (($matches[6][$index][1] ?? -1) >= 0) {
                    $nodes[] = new AstNode('emph', [], $this->parseInlines($matches[6][$index][0]));
                } else {
                    $url = rtrim($matches[7][$index][0], '.,;:)');
                    $trailing = substr($matches[7][$index][0], strlen($url));
                    $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], $this->textInlines($url));
                    if ($trailing !== '') {
                        array_push($nodes, ...$this->textInlines($trailing));
                    }
                }
                $offset = $position + strlen($source);
            }
        }
        if ($offset < strlen($text)) {
            array_push($nodes, ...$this->textInlines(substr($text, $offset)));
        }

        return $nodes === [] ? [new AstNode('text', ['text' => ''])] : $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
            } elseif ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
                $text .= ' ';
            } else {
                $text .= $this->plainInlineText($node->children);
            }
        }

        return $text;
    }

    private function indentOf(string $line): int
    {
        preg_match('/^[ \\t]*/', $line, $match);
        $indent = $match[0] ?? '';

        return strlen(str_replace("\t", '    ', $indent));
    }

    private function nextIndentedLineIndent(int $offset): ?int
    {
        for ($i = $offset, $count = count($this->lines); $i < $count; ++$i) {
            $line = $this->lines[$i];
            if (trim($line) === '') {
                continue;
            }

            return $this->indentOf($line);
        }

        return null;
    }
}
