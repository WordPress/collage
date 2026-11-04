<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlWriter
{
    /** @var list<array{number:int, node:AstNode}> */
    private array $footnotes = [];

    private int $nextFootnoteNumber = 1;

    private int $flushedFootnoteCount = 0;

    /**
     * @param array{htmlQTags?: bool, htmlMathMethod?: string|array<string, mixed>, mathMethod?: string, writerHTMLMathMethod?: string|array<string, mixed>, referenceLocation?: string, writerReferenceLocation?: string, sectionDivs?: bool, writerSectionDivs?: bool, writerWrapText?: string, wrap?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('HTML writer expects a document node');
        }

        $previousFootnotes = $this->footnotes;
        $previousNextFootnoteNumber = $this->nextFootnoteNumber;
        $previousFlushedFootnoteCount = $this->flushedFootnoteCount;
        $this->footnotes = [];
        $this->nextFootnoteNumber = 1;
        $this->flushedFootnoteCount = 0;

        try {
            return $this->renderDocument($document);
        } finally {
            $this->footnotes = $previousFootnotes;
            $this->nextFootnoteNumber = $previousNextFootnoteNumber;
            $this->flushedFootnoteCount = $previousFlushedFootnoteCount;
        }
    }

    private function renderDocument(AstNode $document): string
    {
        if ($this->usesSectionDivs()) {
            return $this->renderSectionDivDocument($document);
        }

        $referenceLocation = $this->referenceLocation();
        $blocks = [];
        foreach ($document->children as $node) {
            if ($referenceLocation === 'end_of_section' && $node->type === 'heading' && $blocks !== []) {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }

            $html = $this->renderBlock($node);
            if ($html !== '') {
                $blocks[] = $html;
            }

            if ($referenceLocation === 'end_of_block') {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }
        }

        $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));

        return implode("\n", $blocks);
    }

    private function usesSectionDivs(): bool
    {
        return (bool) ($this->options['writerSectionDivs'] ?? $this->options['sectionDivs'] ?? false);
    }

    private function renderSectionDivDocument(AstNode $document): string
    {
        $index = 0;
        $items = $this->collectSectionItems($document->children, $index, 0);
        $blocks = $this->renderSectionItems($items, true);

        $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($this->referenceLocation()));

        return implode("\n", $blocks);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}>
     */
    private function collectSectionItems(array $nodes, int &$index, int $parentLevel): array
    {
        $items = [];
        $count = count($nodes);
        while ($index < $count) {
            $node = $nodes[$index];
            if ($node->type === 'heading') {
                $level = max(1, min(6, (int) $node->attr('level', 1)));
                if ($level <= $parentLevel) {
                    break;
                }

                $index++;
                $items[] = [
                    'type' => 'section',
                    'heading' => $node,
                    'level' => $level,
                    'children' => $this->collectSectionItems($nodes, $index, $level),
                ];
                continue;
            }

            $items[] = [
                'type' => 'block',
                'node' => $node,
            ];
            $index++;
        }

        return $items;
    }

    /**
     * @param list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}> $items
     * @return list<string>
     */
    private function renderSectionItems(array $items, bool $root = false): array
    {
        $referenceLocation = $this->referenceLocation();
        $blocks = [];
        foreach ($items as $item) {
            if ($item['type'] === 'section') {
                if ($referenceLocation === 'end_of_section') {
                    $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
                }

                $heading = $item['heading'] ?? null;
                $children = $item['children'] ?? [];
                if ($heading instanceof AstNode && is_array($children)) {
                    $blocks[] = $this->renderSectionDiv($heading, (int) ($item['level'] ?? 1), $children);
                }
                continue;
            }

            $node = $item['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $html = $this->renderBlock($node);
            if ($html !== '') {
                $blocks[] = $html;
            }

            if ($referenceLocation === 'end_of_block') {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }
        }

        if ($root && $referenceLocation === 'end_of_section') {
            $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
        }

        return $blocks;
    }

    /**
     * @param list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}> $children
     */
    private function renderSectionDiv(AstNode $heading, int $level, array $children): string
    {
        $referenceLocation = $this->referenceLocation();
        $lines = ['<div' . $this->renderSectionAttributes($heading, $level) . '>'];
        $lines[] = $this->renderSectionHeading($heading);
        foreach ($this->renderSectionItems($children) as $html) {
            $lines[] = $html;
        }
        if ($referenceLocation === 'end_of_section') {
            $this->appendRenderedBlock($lines, $this->renderPendingFootnotes($referenceLocation));
        }
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    private function renderSectionAttributes(AstNode $heading, int $level): string
    {
        $attrs = $this->attrTuple($heading);
        $attrs['classes'] = array_values(array_unique(array_merge(
            ['section', 'level' . $level],
            $attrs['classes']
        )));

        return $this->renderAttrTuple($attrs);
    }

    private function renderSectionHeading(AstNode $heading): string
    {
        $level = max(1, min(6, (int) $heading->attr('level', 1)));
        $attrs = $this->attrTuple($heading);
        $attrs['id'] = '';

        return '<h' . $level . $this->renderAttrTuple($attrs, $this->allowedHeadingAttribute(...)) . '>'
            . $this->renderInlines($heading->children)
            . '</h' . $level . '>';
    }

    private function renderBlock(AstNode $node): string
    {
        return match ($node->type) {
            'plain' => $this->renderInlines($node->children),
            'paragraph' => $this->renderParagraph($node),
            'heading' => $this->renderHeading($node),
            'code_block' => $this->renderCodeBlock($node),
            'horizontal_rule' => $this->renderHorizontalRule(),
            'line_block' => $this->renderLineBlock($node),
            'figure' => $this->renderFigure($node),
            'table' => $this->renderTable($node),
            'div' => $this->renderDiv($node),
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'definition_list' => $this->renderDefinitionList($node),
            'blockquote' => $this->renderBlockQuote($node),
            'raw_html' => (string) $node->attr('html', $node->attr('text', '')),
            'raw_block' => $this->renderRawBlock($node),
            'raw_tex' => $this->renderRawTextBlock($node, 'tex'),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderHeading(AstNode $node): string
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));

        return '<h' . $level . $this->renderAttributes($node, $this->allowedHeadingAttribute(...)) . '>'
            . $this->renderInlines($node->children)
            . '</h' . $level . '>';
    }

    private function renderParagraph(AstNode $node): string
    {
        $chart = $this->renderablePptxChart($node);
        if ($chart !== null) {
            return $this->renderPptxChart($node, $chart);
        }

        return '<p' . $this->renderHtmlAttributes($node) . '>' . $this->renderInlines($node->children) . '</p>';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function renderablePptxChart(AstNode $node): ?array
    {
        $chart = $node->attr('pptxChart');
        if (!is_array($chart)) {
            return null;
        }

        if (($chart['issues'] ?? []) !== []) {
            return null;
        }

        $series = $chart['series'] ?? null;
        if (!is_array($series) || $series === []) {
            return null;
        }

        return $chart;
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function renderPptxChart(AstNode $node, array $chart): string
    {
        $title = (string) ($chart['title'] ?? '');
        if ($title === '') {
            $title = 'PPTX chart';
        }
        $type = (string) ($chart['chartType'] ?? 'unknown');
        $partName = (string) ($chart['partName'] ?? '');
        $placeholder = (string) $node->attr('text', '');
        $series = $this->normalizedPptxChartSeries($chart);
        $categories = $this->pptxChartCategories($series);

        $html = '<figure class="pandoc-pptx-chart" data-pandoc-source="pptx-chart"'
            . ($partName === '' ? '' : ' data-pptx-chart-part="' . $this->esc($partName) . '"')
            . ' data-pptx-chart-type="' . $this->esc($type) . '">';
        $html .= '<figcaption><strong>' . $this->esc($title) . '</strong>';
        if ($placeholder !== '') {
            $html .= ' <span class="pandoc-pptx-chart-placeholder">' . $this->esc($placeholder) . '</span>';
        }
        $html .= '</figcaption>';

        $html .= '<table><thead><tr><th>Category</th>';
        foreach ($series as $item) {
            $html .= '<th>' . $this->esc($item['name']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $rowCount = max(1, count($categories));
        for ($row = 0; $row < $rowCount; $row++) {
            $html .= '<tr><td>' . $this->esc((string) ($categories[$row] ?? 'Point ' . ($row + 1))) . '</td>';
            foreach ($series as $item) {
                $html .= '<td>' . $this->esc((string) ($item['rawValues'][$row] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= $this->renderPptxChartBars($series);
        $html .= '</figure>';

        return $html;
    }

    /**
     * @param array<string, mixed> $chart
     * @return list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}>
     */
    private function normalizedPptxChartSeries(array $chart): array
    {
        $normalized = [];
        foreach (array_values(is_array($chart['series'] ?? null) ? $chart['series'] : []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $categories = array_values(array_map('strval', is_array($item['categories'] ?? null) ? $item['categories'] : []));
            $rawValues = array_values(array_map('strval', is_array($item['values'] ?? null) ? $item['values'] : []));
            $values = array_map(static fn (string $value): float => is_numeric($value) ? (float) $value : 0.0, $rawValues);
            $normalized[] = [
                'name' => (string) ($item['name'] ?? 'Series ' . ($index + 1)),
                'categories' => $categories,
                'rawValues' => $rawValues,
                'values' => $values,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series
     * @return list<string>
     */
    private function pptxChartCategories(array $series): array
    {
        foreach ($series as $item) {
            if ($item['categories'] !== []) {
                return $item['categories'];
            }
        }

        return [];
    }

    /**
     * @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series
     */
    private function renderPptxChartBars(array $series): string
    {
        $max = 0.0;
        foreach ($series as $item) {
            foreach ($item['values'] as $value) {
                $max = max($max, abs($value));
            }
        }
        if ($max <= 0.0) {
            return '';
        }

        $html = '<div class="pandoc-pptx-chart-bars">';
        foreach ($series as $item) {
            foreach ($item['values'] as $index => $value) {
                $label = $item['name'] . ' / ' . (string) ($item['categories'][$index] ?? 'Point ' . ($index + 1));
                $width = (int) round((abs($value) / $max) * 100);
                $html .= '<div class="pandoc-pptx-chart-bar" style="display:grid;grid-template-columns:minmax(8rem,1fr) 3fr 3rem;gap:.5rem;align-items:center;margin:.25rem 0">'
                    . '<span>' . $this->esc($label) . '</span>'
                    . '<span style="display:block;background:#eef1f5;height:.8rem"><span style="display:block;background:#2563eb;height:.8rem;width:' . $width . '%"></span></span>'
                    . '<span>' . $this->esc((string) ($item['rawValues'][$index] ?? $value)) . '</span>'
                    . '</div>';
            }
        }

        return $html . '</div>';
    }

    private function renderCodeBlock(AstNode $node): string
    {
        return '<pre' . $this->renderAttributes($node) . '><code>'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    private function renderHorizontalRule(): string
    {
        return '<hr />';
    }

    private function renderLineBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $lines[] = $line->children === []
                ? $this->esc((string) $line->attr('text', ''))
                : $this->renderInlines($line->children);
        }

        return '<div class="line-block">' . implode('<br />', $lines) . '</div>';
    }

    private function renderDiv(AstNode $node): string
    {
        if ($this->divIsWrapper($node)) {
            $html = $this->renderBlock($node->children[0]);

            return $html === '' ? '' : $this->addRootAttributes($html, $this->wrapperDivAttributes($node));
        }

        $attrs = $this->attrTuple($node);
        $isCslBibBody = $attrs['id'] === 'refs' || in_array('csl-bib-body', $attrs['classes'], true);
        $isCslBibEntry = in_array('csl-entry', $attrs['classes'], true);
        if ($isCslBibBody && !array_key_exists('role', $attrs['attributes'])) {
            $attrs['attributes']['role'] = 'list';
        }
        if ($isCslBibEntry && !array_key_exists('role', $attrs['attributes'])) {
            $attrs['attributes']['role'] = 'listitem';
        }

        $tag = 'div';
        if (in_array('section', $attrs['classes'], true)) {
            $tag = 'section';
            $attrs['classes'] = array_values(array_filter(
                $attrs['classes'],
                static fn (string $class): bool => $class !== 'section'
            ));
        }

        if (in_array('column', $attrs['classes'], true) && isset($attrs['attributes']['width'])) {
            $width = $attrs['attributes']['width'];
            unset($attrs['attributes']['width']);
            $existingStyle = rtrim((string) ($attrs['attributes']['style'] ?? ''), ';');
            $attrs['attributes']['style'] = ($existingStyle === '' ? '' : $existingStyle . ';') . 'width:' . $width . ';';
        }

        $lines = ['<' . $tag . $this->renderAttrTuple($attrs) . '>'];
        foreach ($node->children as $child) {
            $html = $this->renderDivChild($child, $isCslBibEntry);
            if ($html !== '') {
                $lines[] = $html;
            }
        }
        $lines[] = '</' . $tag . '>';

        return implode("\n", $lines);
    }

    private function divIsWrapper(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $attrs = $this->attrTuple($node);

        return ($attrs['attributes']['wrapper'] ?? '') === '1';
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function wrapperDivAttributes(AstNode $node): array
    {
        $attrs = $this->attrTuple($node);
        unset($attrs['attributes']['wrapper']);

        return $attrs;
    }

    private function renderDivChild(AstNode $node, bool $cslEntry): string
    {
        if ($cslEntry && $node->type === 'paragraph') {
            return $this->renderInlines($node->children);
        }

        return $this->renderBlock($node);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function addRootAttributes(string $html, array $attrs): string
    {
        if ($attrs['id'] === '' && $attrs['classes'] === [] && $attrs['attributes'] === []) {
            return $html;
        }

        if (preg_match('/^<([A-Za-z][A-Za-z0-9:-]*)([^>]*)>/', $html, $match) !== 1) {
            return $html;
        }

        $openingTag = $match[0];
        $tag = $match[1];
        $tail = $match[2];
        $selfClosing = str_ends_with(rtrim($tail), '/');
        if ($selfClosing) {
            $tail = rtrim(substr(rtrim($tail), 0, -1));
        }

        $merged = $this->mergeRenderedAttributes($tail, $attrs);
        $replacement = '<' . $tag . $this->renderAttrTuple($merged) . ($selfClosing ? ' /' : '') . '>';

        return $replacement . substr($html, strlen($openingTag));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function mergeRenderedAttributes(string $tail, array $attrs): array
    {
        $merged = [
            'id' => '',
            'classes' => [],
            'attributes' => [],
        ];

        if (preg_match_all('/\s+([A-Za-z_:][A-Za-z0-9_.:-]*)="([^"]*)"/', $tail, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($name === 'id') {
                    $merged['id'] = $value;
                    continue;
                }
                if ($name === 'class') {
                    $classes = preg_split('/\s+/', trim($value)) ?: [];
                    $merged['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
                    continue;
                }

                $merged['attributes'][$name] = $value;
            }
        }

        if ($merged['id'] === '' && $attrs['id'] !== '') {
            $merged['id'] = $attrs['id'];
        }

        foreach ($attrs['classes'] as $class) {
            if (!in_array($class, $merged['classes'], true)) {
                $merged['classes'][] = $class;
            }
        }

        foreach ($attrs['attributes'] as $name => $value) {
            if (!array_key_exists($name, $merged['attributes'])) {
                $merged['attributes'][$name] = $value;
            }
        }

        return $merged;
    }

    private function renderRawBlock(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($this->isRawHtmlFormat($format)) {
            return (string) $node->attr('text', $node->attr('html', ''));
        }

        return $this->renderRawTextBlock($node, $format);
    }

    private function renderRawTextBlock(AstNode $node, string $fallbackFormat = 'raw'): string
    {
        $format = strtolower((string) $node->attr('format', $fallbackFormat));
        if ($format === '') {
            $format = $fallbackFormat;
        }
        $token = $this->rawFormatToken($format);
        $text = (string) $node->attr('text', $node->attr('tex', $node->attr('html', '')));

        return '<pre class="pandoc-raw-' . $this->esc($token) . '" data-pandoc-raw-format="' . $this->esc($format) . '"'
            . $this->rawTexDiagnosticAttrs($node) . '><code class="language-'
            . $this->esc($token) . '">' . $this->esc($text) . '</code></pre>';
    }

    private function isRawHtmlFormat(string $format): bool
    {
        return in_array($format, [
            'html',
            'html4',
            'html5',
            'epub',
            'epub2',
            'epub3',
            's5',
            'slidy',
            'slideous',
            'dzslides',
            'revealjs',
        ], true);
    }

    private function rawFormatToken(string $format): string
    {
        $token = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($format)) ?? 'raw';
        $token = trim($token, '-');

        return $token === '' ? 'raw' : $token;
    }

    private function renderFigure(AstNode $node): string
    {
        $lines = ['<figure' . $this->renderAttributes($node) . '>'];
        foreach ($node->children as $child) {
            $html = $this->renderFigureBodyBlock($child);
            if ($html !== '') {
                $lines[] = $html;
            }
        }

        $caption = $this->renderFigureCaption($node);
        if ($caption !== '') {
            $ariaHidden = $this->figureCaptionMatchesImageAlt($node) ? ' aria-hidden="true"' : '';
            $lines[] = '<figcaption' . $ariaHidden . '>' . $caption . '</figcaption>';
        }
        $lines[] = '</figure>';

        return implode("\n", $lines);
    }

    private function renderFigureBodyBlock(AstNode $node): string
    {
        if ($this->isInlineNode($node)) {
            return $this->renderInline($node);
        }

        return $this->renderBlock($node);
    }

    private function renderFigureCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $html = '';
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }
                $html .= $this->renderInline($inline);
            }
            if ($html !== '') {
                return $html;
            }
        }

        $caption = (string) $node->attr('caption', '');

        return $caption === '' ? '' : $this->esc($caption);
    }

    private function figureCaptionMatchesImageAlt(AstNode $node): bool
    {
        $caption = $this->plainFigureCaptionText($node);
        if ($caption === '') {
            return false;
        }

        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode) {
            return false;
        }

        $alt = (string) $image->attr('alt', '');
        if ($alt === '') {
            $alt = $this->plainInlineText($image->children);
        }

        return $caption === $alt;
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

    private function plainFigureCaptionText(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $allInlines = true;
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $allInlines = false;
                    break;
                }
            }
            if ($allInlines) {
                return $this->plainInlineText($captionInlines);
            }
        }

        return trim((string) $node->attr('caption', ''));
    }

    private function renderTable(AstNode $node): string
    {
        $head = null;
        $bodies = [];
        $foot = null;
        foreach ($node->children as $child) {
            if ($child->type === 'table_head') {
                $head = $child;
                continue;
            }
            if ($child->type === 'table_body') {
                $bodies[] = $child;
                continue;
            }
            if ($child->type === 'table_foot') {
                $foot = $child;
            }
        }

        $lines = ['<table' . $this->renderTableAttributes($node) . '>'];
        $this->appendRenderedBlock($lines, $this->renderTableCaption($node));
        $this->appendRenderedBlock($lines, $this->renderTableColgroup($node));

        if ($head instanceof AstNode && $this->tableRows($head) !== []) {
            $lines[] = '<thead' . $this->renderHtmlAttributes($head) . '>';
            foreach ($this->tableRows($head) as $row) {
                $lines[] = $this->renderTableRow($row, $node, true);
            }
            $lines[] = '</thead>';
        }

        foreach ($bodies as $body) {
            $lines[] = '<tbody' . $this->renderHtmlAttributes($body) . '>';
            $headRows = $body->attr('headRows', []);
            if (is_array($headRows)) {
                foreach ($headRows as $row) {
                    if ($row instanceof AstNode && $row->type === 'table_row') {
                        $lines[] = $this->renderTableRow($row, $node, true);
                    }
                }
            }

            $rowHeadColumns = max(0, (int) $body->attr('rowHeadColumns', 0));
            foreach ($this->tableRows($body) as $row) {
                $lines[] = $this->renderTableRow($row, $node, false, $rowHeadColumns);
            }
            $lines[] = '</tbody>';
        }

        if ($foot instanceof AstNode && $this->tableRows($foot) !== []) {
            $lines[] = '<tfoot' . $this->renderHtmlAttributes($foot) . '>';
            foreach ($this->tableRows($foot) as $row) {
                $lines[] = $this->renderTableRow($row, $node, false);
            }
            $lines[] = '</tfoot>';
        }
        $lines[] = '</table>';

        return implode("\n", $lines);
    }

    private function renderTableAttributes(AstNode $node): string
    {
        $attrs = $this->htmlAttrTuple($node);
        $totalWidth = $this->tableTotalExplicitWidth($node);
        if (
            $totalWidth > 0.0
            && $totalWidth < 1.0
            && !array_key_exists('style', $attrs['attributes'])
        ) {
            $attrs['attributes']['style'] = 'width:' . (int) round($totalWidth * 100) . '%;';
        }

        return $this->renderAttrTuple($attrs);
    }

    private function renderTableCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $html = '';
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }
                $html .= $this->renderInline($inline);
            }
            if ($html !== '') {
                return '<caption>' . $html . '</caption>';
            }
        }

        $caption = (string) $node->attr('caption', '');

        return $caption === '' ? '' : '<caption>' . $this->esc($caption) . '</caption>';
    }

    private function renderTableColgroup(AstNode $node): string
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $hasExplicitWidth = false;
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $hasExplicitWidth = true;
                break;
            }
        }
        if (!$hasExplicitWidth) {
            return '';
        }

        $cols = [];
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $cols[] = '<col style="width: ' . (int) floor((float) $width * 100) . '%" />';
                continue;
            }

            $cols[] = '<col />';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function tableTotalExplicitWidth(AstNode $node): float
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $total += (float) $width;
            }
        }

        return $total;
    }

    /**
     * @return list<AstNode>
     */
    private function tableRows(AstNode $section): array
    {
        return array_values(array_filter(
            $section->children,
            static fn (AstNode $child): bool => $child->type === 'table_row'
        ));
    }

    private function renderTableRow(AstNode $row, AstNode $table, bool $header, int $rowHeadColumns = 0): string
    {
        $html = '<tr' . $this->renderHtmlAttributes($row) . '>';
        $logicalColumn = 0;
        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }

            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $tag = $header
                || $cell->attr('header') === true
                || ($logicalColumn < $rowHeadColumns && $logicalColumn + $colspan <= $rowHeadColumns)
                ? 'th'
                : 'td';
            $html .= '<' . $tag . $this->renderTableCellAttributes($table, $logicalColumn, $cell) . '>'
                . $this->renderTableCellContents($cell)
                . '</' . $tag . '>';
            $logicalColumn += $colspan;
        }

        return $html . '</tr>';
    }

    private function renderTableCellAttributes(AstNode $table, int $logicalColumn, AstNode $cell): string
    {
        $attrs = $this->htmlAttrTuple($cell);
        $sourceStyle = trim((string) ($attrs['attributes']['style'] ?? ''));
        unset($attrs['attributes']['style']);

        $colspan = (int) $cell->attr('colspan', 1);
        if ($colspan > 1) {
            $attrs['attributes']['colspan'] = (string) $colspan;
        }

        $rowspan = (int) $cell->attr('rowspan', 1);
        if ($rowspan > 1) {
            $attrs['attributes']['rowspan'] = (string) $rowspan;
        }

        $styles = [];
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        $alignment = (string) $cell->attr('align', '');
        $alignments = $table->attr('alignments', []);
        if ($alignment === '' && is_array($alignments)) {
            $alignment = (string) ($alignments[$logicalColumn] ?? 'default');
        }

        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        if ($styles !== []) {
            $attrs['attributes']['style'] = implode('; ', $styles);
        }

        return $this->renderAttrTuple($attrs);
    }

    private function renderTableCellContents(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->esc((string) $cell->attr('text', ''));
        }

        $html = '';
        foreach ($cell->children as $child) {
            $html .= $this->isInlineNode($child)
                ? $this->renderInline($child)
                : $this->renderBlock($child);
        }

        return $html;
    }

    private function renderList(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $lines = ['<' . $tag . $this->renderListAttributes($node, $ordered) . '>'];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                $lines[] = $this->renderListItem($item);
            }
        }
        $lines[] = '</' . $tag . '>';

        return implode("\n", $lines);
    }

    private function renderListAttributes(AstNode $node, bool $ordered): string
    {
        $attrs = $this->attrTuple($node);
        if ($ordered) {
            $listAttributes = [];
            $start = (int) $node->attr('start', 1);
            if ($start !== 1) {
                $listAttributes['start'] = (string) $start;
            }

            $type = $this->orderedListHtmlType((string) $node->attr('style', 'default'));
            if ($type !== '') {
                $listAttributes['type'] = $type;
            }

            if ((string) $node->attr('style', '') === 'example' && !in_array('example', $attrs['classes'], true)) {
                $attrs['classes'][] = 'example';
            }

            $attrs['attributes'] = $listAttributes + $attrs['attributes'];

            return $this->renderAttrTuple($attrs);
        }

        if ($this->listIsTaskList($node) && !in_array('task-list', $attrs['classes'], true)) {
            $attrs['classes'][] = 'task-list';
        }

        return $this->renderAttrTuple($attrs);
    }

    private function orderedListHtmlType(string $style): string
    {
        return match ($style) {
            'decimal', 'example' => '1',
            'lower_alpha' => 'a',
            'upper_alpha' => 'A',
            'lower_roman' => 'i',
            'upper_roman' => 'I',
            default => '',
        };
    }

    private function renderListItem(AstNode $item): string
    {
        $parts = [];
        $inlineNodes = [];
        $taskChecked = $item->attr('taskChecked', null);
        $taskPending = is_bool($taskChecked);

        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineNodes[] = $child;
                continue;
            }

            $this->appendListItemInlinePart($parts, $inlineNodes, $taskChecked, $taskPending);
            if ($child->type === 'plain') {
                $html = $this->renderInlines($child->children);
                if ($taskPending) {
                    $html = $this->renderTaskListLabel((bool) $taskChecked, $html);
                    $taskPending = false;
                }
                $parts[] = $html;
                continue;
            }

            if ($child->type === 'paragraph') {
                $html = $this->renderInlines($child->children);
                if ($taskPending) {
                    $html = $this->renderTaskListLabel((bool) $taskChecked, $html);
                    $taskPending = false;
                }
                $parts[] = '<p>' . $html . '</p>';
                continue;
            }

            $html = $this->renderBlock($child);
            if ($html !== '') {
                $parts[] = $html;
            }
        }

        $this->appendListItemInlinePart($parts, $inlineNodes, $taskChecked, $taskPending);
        if ($taskPending) {
            $parts[] = $this->renderTaskListLabel((bool) $taskChecked, '');
        }

        if ($parts === []) {
            return '<li></li>';
        }

        return '<li>' . implode("\n", $parts) . '</li>';
    }

    /**
     * @param list<string> $parts
     * @param list<AstNode> $inlineNodes
     */
    private function appendListItemInlinePart(
        array &$parts,
        array &$inlineNodes,
        mixed $taskChecked,
        bool &$taskPending
    ): void {
        if ($inlineNodes === []) {
            return;
        }

        $html = $this->renderInlines($inlineNodes);
        if ($taskPending && is_bool($taskChecked)) {
            $html = $this->renderTaskListLabel($taskChecked, $html);
            $taskPending = false;
        }
        $parts[] = $html;
        $inlineNodes = [];
    }

    private function renderTaskListLabel(bool $checked, string $html): string
    {
        return '<label><input type="checkbox"' . ($checked ? ' checked=""' : '') . ' />' . $html . '</label>';
    }

    private function listIsTaskList(AstNode $node): bool
    {
        if ($node->attr('taskList') === true) {
            return true;
        }

        if ($node->children === []) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child->type !== 'list_item' || !is_bool($child->attr('taskChecked', null))) {
                return false;
            }
        }

        return true;
    }

    private function renderDefinitionList(AstNode $node): string
    {
        $lines = ['<dl>'];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || $term->type !== 'term') {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }

            $termHtml = $term->children === []
                ? $this->esc((string) $term->attr('text', ''))
                : $this->renderInlines($term->children);
            $lines[] = '<dt>' . $termHtml . '</dt>';

            foreach ($children as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }
                $lines[] = '<dd>';
                foreach ($this->renderDefinitionBlocks($definition) as $html) {
                    $lines[] = $html;
                }
                $lines[] = '</dd>';
            }
        }
        $lines[] = '</dl>';

        return implode("\n", $lines);
    }

    private function renderBlockQuote(AstNode $node): string
    {
        $lines = ['<blockquote>'];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $lines[] = $html;
            }
        }
        $lines[] = '</blockquote>';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBlocks(AstNode $node): array
    {
        $blocks = [];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $blocks[] = $html;
            }
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderInline($node);
        }

        return $html;
    }

    private function renderInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => $this->esc((string) $node->attr('text', '')),
            'softbreak' => $this->renderSoftBreak(),
            'linebreak' => $this->renderLineBreak(),
            'code' => $this->renderCode($node),
            'image' => $this->renderImage($node),
            'emph' => '<em>' . $this->renderInlines($node->children) . '</em>',
            'strong' => '<strong>' . $this->renderInlines($node->children) . '</strong>',
            'underline' => '<u>' . $this->renderInlines($node->children) . '</u>',
            'strikeout' => '<del>' . $this->renderInlines($node->children) . '</del>',
            'small_caps' => '<span class="smallcaps">' . $this->renderInlines($node->children) . '</span>',
            'superscript' => '<sup>' . $this->renderInlines($node->children) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node->children) . '</sub>',
            'span' => $this->renderSpan($node),
            'quoted' => $this->renderQuoted($node),
            'link' => $this->renderLink($node),
            'note' => $this->renderNoteReference($node),
            'citation', 'citation_group' => $this->renderCitation($node),
            'math' => $this->renderMath($node),
            'raw_html_inline' => (string) $node->attr('html', $node->attr('text', '')),
            'raw_inline' => $this->renderRawInline($node),
            'raw_tex_inline', 'raw_markdown' => $this->renderRawInline($node),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderSoftBreak(): string
    {
        return $this->htmlWrapText() === 'preserve' ? "\n" : ' ';
    }

    private function renderLineBreak(): string
    {
        return '<br />' . "\n";
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($this->isRawHtmlFormat($format)) {
            return (string) $node->attr('text', $node->attr('html', ''));
        }

        $token = $this->rawFormatToken($format === '' && in_array($node->type, ['raw_tex', 'raw_tex_inline'], true) ? 'tex' : ($format === '' ? 'raw' : $format));
        $text = (string) $node->attr('text', $node->attr('tex', $node->attr('html', '')));

        return '<span class="pandoc-raw-' . $this->esc($token) . '" data-pandoc-raw-format="' . $this->esc($format) . '"'
            . $this->rawTexDiagnosticAttrs($node) . '>'
            . $this->esc($text)
            . '</span>';
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

    private function htmlWrapText(): string
    {
        $value = (string) ($this->options['writerWrapText'] ?? $this->options['wrap'] ?? 'auto');
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $value));

        return match ($normalized) {
            'preserve', 'wrappreserve' => 'preserve',
            'none', 'wrapnone' => 'none',
            default => 'auto',
        };
    }

    private function renderLink(AstNode $node): string
    {
        $attrs = $this->attrTuple($node);
        $sourceHref = $attrs['attributes']['href'] ?? '';
        $sourceTitle = $attrs['attributes']['title'] ?? '';
        unset($attrs['attributes']['href'], $attrs['attributes']['title']);

        $url = (string) $node->attr('url', $node->attr('href', $sourceHref));
        $title = (string) $node->attr('title', $sourceTitle);
        $renderedAttrs = ' href="' . $this->esc($url) . '"';
        if ($title !== '') {
            $renderedAttrs .= ' title="' . $this->esc($title) . '"';
        }
        $renderedAttrs .= $this->renderAttrTuple($attrs);

        return '<a' . $renderedAttrs . '>' . $this->renderInlines($this->linksAsSpans($node->children)) . '</a>';
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

    private function renderSpan(AstNode $node): string
    {
        $attrs = $this->spanAttrsWithCslStyle($this->attrTuple($node));
        $spanLike = $this->spanLikeTagsAndClasses($attrs['classes']);
        $html = $this->renderInlines($node->children);

        if ($spanLike === null) {
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($attrs['classes']);

            return '<span' . $this->renderAttrTuple($attrs) . '>' . $html . '</span>';
        }

        $attrs['classes'] = $spanLike['classes'];
        $outerIndex = count($spanLike['tags']) - 1;
        foreach ($spanLike['tags'] as $index => $wrapper) {
            $tag = $wrapper['tag'];
            $wrapperClasses = $wrapper['classes'];
            if ($index === $outerIndex) {
                $outerAttrs = $attrs;
                $outerAttrs['classes'] = array_values(array_unique(array_merge($wrapperClasses, $attrs['classes'])));
                $tagAttrs = $this->renderAttrTuple($outerAttrs);
            } else {
                $tagAttrs = $this->renderClassOnlyAttr($wrapperClasses);
            }
            $html = '<' . $tag . $tagAttrs . '>' . $html . '</' . $tag . '>';
        }

        return $html;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function spanAttrsWithCslStyle(array $attrs): array
    {
        $styles = [];
        if (in_array('csl-no-emph', $attrs['classes'], true)) {
            $styles[] = 'font-style:normal;';
        }
        if (in_array('csl-no-strong', $attrs['classes'], true)) {
            $styles[] = 'font-weight:normal;';
        }
        if (in_array('csl-no-smallcaps', $attrs['classes'], true)) {
            $styles[] = 'font-variant:normal;';
        }

        if ($styles === []) {
            return $attrs;
        }

        $existingStyle = trim($attrs['attributes']['style'] ?? '');
        $attrs['attributes']['style'] = implode('', $styles)
            . ($existingStyle === '' ? '' : rtrim($existingStyle, ';') . ';');

        return $attrs;
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    private function classesWithoutCslNoStyleMarkers(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn (string $class): bool => !in_array($class, [
                'csl-no-emph',
                'csl-no-strong',
                'csl-no-smallcaps',
            ], true)
        ));
    }

    /**
     * @param list<string> $classes
     * @return array{tags:list<array{tag:string, classes:list<string>}>, classes:list<string>}|null
     */
    private function spanLikeTagsAndClasses(array $classes): ?array
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
                array_unshift($retainedClasses, $class);
            }
        }

        $tags = [];
        for ($index = count($classes) - 1; $index >= 0; $index--) {
            if ($classes[$index] === 'smallcaps') {
                $tags[] = ['tag' => 'span', 'classes' => ['smallcaps']];
                continue;
            }
            if ($classes[$index] === 'underline') {
                $tags[] = ['tag' => 'u', 'classes' => []];
            }
        }
        foreach ($classes as $class) {
            if ($this->isHtmlSpanLikeElement($class)) {
                $tags[] = ['tag' => $class, 'classes' => []];
            }
        }

        return $tags === [] ? null : [
            'tags' => $tags,
            'classes' => array_values(array_unique($retainedClasses)),
        ];
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

    /**
     * @param list<string> $classes
     */
    private function renderClassOnlyAttr(array $classes): string
    {
        return $classes === [] ? '' : ' class="' . $this->esc(implode(' ', $classes)) . '"';
    }

    private function renderCitation(AstNode $node): string
    {
        $citations = $this->citationEntries($node);
        $ids = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? $citation['citationId'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $rendered = trim((string) $node->attr('rendered', ''));
        $display = $rendered === '' ? $node->children : [new AstNode('text', ['text' => $rendered])];
        if ($display === []) {
            $text = (string) $node->attr('text', $ids === [] ? '' : '[' . implode('; ', array_map(
                static fn (string $id): string => '@' . $id,
                $ids
            )) . ']');
            $display = $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }

        $attrs = [
            'id' => '',
            'classes' => ['citation'],
            'attributes' => [],
        ];
        if ($ids !== []) {
            $attrs['attributes']['data-cites'] = implode(' ', $ids);
        }

        return '<span' . $this->renderAttrTuple($attrs) . '>'
            . $this->renderInlines($this->addBibliorefRoles($display))
            . '</span>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationEntries(AstNode $node): array
    {
        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = [
                        'id' => (string) $citation->attr('id', $citation->attr('citationId', '')),
                    ];
                    continue;
                }
                if (is_array($citation)) {
                    $entries[] = $citation;
                }
            }

            return $entries;
        }

        $id = (string) $node->attr('id', $node->attr('citationId', ''));

        if ($id === '' && $node->type === 'citation_group') {
            $entries = [];
            foreach ($node->children as $citation) {
                if ($citation->type !== 'citation') {
                    continue;
                }
                $citationId = (string) $citation->attr('id', $citation->attr('citationId', ''));
                if ($citationId !== '') {
                    $entries[] = ['id' => $citationId];
                }
            }

            return $entries;
        }

        return $id === '' ? [] : [['id' => $id]];
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function addBibliorefRoles(array $nodes): array
    {
        $mapped = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->addBibliorefRoles($node->children);
            if ($node->type !== 'link') {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $linkAttrs = $this->attrTuple($node);
            $url = (string) $node->attr('url', $node->attr('href', $linkAttrs['attributes']['href'] ?? ''));
            if (!str_starts_with($url, '#ref-')) {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $attrs = $node->attrs;
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            $attributes['role'] ??= 'doc-biblioref';
            $attrs['attributes'] = $attributes;
            $mapped[] = new AstNode('link', $attrs, $children);
        }

        return $mapped;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $number = $this->nextFootnoteNumber++;
        $this->footnotes[] = [
            'number' => $number,
            'node' => $node,
        ];

        return '<a href="#fn' . $number . '" class="footnote-ref" id="fnref' . $number . '" role="doc-noteref"><sup>'
            . $number
            . '</sup></a>';
    }

    private function renderCode(AstNode $node): string
    {
        $rawText = (string) $node->attr('text', '');
        $text = $this->esc($rawText);
        $attrs = $this->attrTuple($node);
        $highlightLanguage = $this->highlightLanguage($attrs['classes']);

        if ($highlightLanguage !== null) {
            $codeAttrs = $attrs;
            $codeAttrs['classes'] = $this->highlightedCodeClasses($attrs['classes'], $highlightLanguage);
            $code = '<code' . $this->renderAttrTuple($codeAttrs) . '>'
                . $this->highlightCodeText($rawText, $highlightLanguage)
                . '</code>';

            if (in_array('sample', $attrs['classes'], true)) {
                return '<samp>' . $code . '</samp>';
            }

            if (in_array('variable', $attrs['classes'], true)) {
                return '<var>' . $code . '</var>';
            }

            return $code;
        }

        if ($this->isBareCodeRole($attrs, 'sample')) {
            return '<samp>' . $text . '</samp>';
        }

        if ($this->isBareCodeRole($attrs, 'variable')) {
            return '<var>' . $text . '</var>';
        }

        return '<code' . $this->renderAttrTuple($attrs) . '>' . $text . '</code>';
    }

    private function renderMath(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $display = $node->attr('display') === true;
        $class = $display ? 'display' : 'inline';

        return match ($this->htmlMathMethod()) {
            'webtex' => $this->renderWebTeXMath($text, $display, $class),
            'gladtex' => $this->renderGladTeXMath($text, $display),
            'mathml' => $this->renderMathML($node, $text, $display, $class),
            'mathjax' => '<span class="math ' . $class . '">'
                . $this->esc(($display ? '\\[' : '\\(') . $text . ($display ? '\\]' : '\\)'))
                . '</span>',
            'katex' => '<span class="math ' . $class . '">' . $this->esc($text) . '</span>',
            default => '<span class="math ' . $class . '">' . $this->esc($text) . '</span>',
        };
    }

    private function renderMathML(AstNode $node, string $text, bool $display, string $class): string
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

    private function renderWebTeXMath(string $text, bool $display, string $class): string
    {
        $trimmed = trim($text);
        $stylePrefix = $display ? '\\displaystyle ' : '\\textstyle ';
        $src = $this->htmlMathUrl('webtex') . rawurlencode($stylePrefix . $trimmed);

        return '<img style="vertical-align:middle" src="' . $this->esc($src) . '"'
            . ' alt="' . $this->esc($trimmed) . '"'
            . ' title="' . $this->esc($trimmed) . '"'
            . ' class="math ' . $class . '" />';
    }

    private function renderGladTeXMath(string $text, bool $display): string
    {
        return '<eq env="' . ($display ? 'displaymath' : 'math') . '">'
            . $this->esc($text)
            . '</eq>';
    }

    private function htmlMathMethod(): string
    {
        $value = $this->htmlMathOptionValue();

        if (is_array($value)) {
            $value = $value['method'] ?? $value['type'] ?? $value['name'] ?? '';
        }

        $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $value));

        return match ($normalized) {
            'webtex' => 'webtex',
            'gladtex' => 'gladtex',
            'mathml' => 'mathml',
            'mathjax' => 'mathjax',
            'katex' => 'katex',
            default => 'plain',
        };
    }

    private function htmlMathOptionValue(): mixed
    {
        return $this->options['writerHTMLMathMethod']
            ?? $this->options['htmlMathMethod']
            ?? $this->options['mathMethod']
            ?? 'mathjax';
    }

    private function htmlMathUrl(string $method): string
    {
        $value = $this->htmlMathOptionValue();
        if (is_array($value)) {
            foreach (['url', 'baseUrl', 'src'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    return (string) $value[$key];
                }
            }
        }

        return $method === 'webtex' ? 'https://latex.codecogs.com/png.latex?' : '';
    }

    /**
     * @param list<string> $classes
     */
    private function highlightLanguage(array $classes): ?string
    {
        if (in_array('nolanguage', $classes, true)) {
            return null;
        }

        foreach ($classes as $class) {
            if ($class === 'haskell') {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    private function highlightedCodeClasses(array $classes, string $language): array
    {
        $ordered = ['sourceCode', $language];
        foreach ($classes as $class) {
            if (in_array($class, ['sourceCode', $language, 'nolanguage'], true)) {
                continue;
            }
            if (!in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        return $ordered;
    }

    private function highlightCodeText(string $text, string $language): string
    {
        if ($language !== 'haskell') {
            return $this->esc($text);
        }

        $parts = preg_split('/([!#$%&*+.\/<=>?@\\\\^|~:-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->esc($text);
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^[!#$%&*+.\/<=>?@\\\\^|~:-]+$/u', $part) === 1) {
                $html .= '<span class="op">' . $this->esc($part) . '</span>';
                continue;
            }
            $html .= $this->esc($part);
        }

        return $html;
    }

    private function renderImage(AstNode $node): string
    {
        $attrs = $this->attrTuple($node);
        $sourceAttr = $attrs['attributes']['src'] ?? null;
        $titleAttr = $attrs['attributes']['title'] ?? null;
        $altAttr = $attrs['attributes']['alt'] ?? null;
        $attrs['attributes'] = array_filter(
            $attrs['attributes'],
            static fn (string $name): bool => !in_array($name, ['src', 'title', 'alt'], true),
            ARRAY_FILTER_USE_KEY
        );

        $source = (string) $node->attr('url', $node->attr('src', is_string($sourceAttr) ? $sourceAttr : ''));
        $parts = ['src="' . $this->esc($source) . '"'];
        $title = (string) $node->attr('title', '');
        if ($title === '' && is_string($titleAttr)) {
            $title = $titleAttr;
        }
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $alt = $node->attr('alt', null);
        if ((!is_string($alt) || $alt === '') && is_string($altAttr)) {
            $alt = $altAttr;
        }
        if (!is_string($alt) || $alt === '') {
            $alt = $this->plainInlineText($node->children);
        }

        $category = $this->mediaCategory($source);
        if ($category === 'video' || $category === 'audio') {
            return $this->renderMediaImage($category, $source, $title, $alt, $attrs);
        }
        if ($category !== null && $category !== 'image') {
            return $this->renderEmbeddedMedia($source, $title, $attrs);
        }

        if ($alt !== '') {
            $parts[] = 'alt="' . $this->esc($alt) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }

        return '<img ' . implode(' ', $parts) . ' />';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderMediaImage(string $category, string $source, string $title, string $alt, array $attrs): string
    {
        $tag = $category === 'audio' ? 'audio' : 'video';
        $fallback = $alt === '' ? ucfirst($category) : $alt;
        $parts = ['src="' . $this->esc($source) . '"'];
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }
        $parts[] = 'controls=""';

        return '<' . $tag . ' ' . implode(' ', $parts) . '><a href="'
            . $this->esc($source) . '">' . $this->esc($fallback) . '</a></' . $tag . '>';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderEmbeddedMedia(string $source, string $title, array $attrs): string
    {
        $parts = ['src="' . $this->esc($source) . '"'];
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }

        return '<embed ' . implode(' ', $parts) . ' />';
    }

    private function mediaCategory(string $source): ?string
    {
        if (preg_match('/^data:([^;,]+)/i', $source, $m) === 1) {
            $mime = strtolower($m[1]);
            $slash = strpos($mime, '/');

            return $slash === false ? null : substr($mime, 0, $slash);
        }

        $sourceWithoutGzip = preg_replace('/\.gz$/i', '', $source) ?? $source;
        $path = parse_url($sourceWithoutGzip, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $sourceWithoutGzip;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'apng', 'avif', 'bmp', 'emf', 'gif', 'ico', 'jfif', 'jpeg', 'jpg', 'jxl', 'png', 'svg', 'svgz', 'tif', 'tiff', 'webp', 'wmf' => 'image',
            'avi', 'm4v', 'mkv', 'mov', 'mp4', 'mpe', 'mpeg', 'mpg', 'ogv', 'qt', 'webm' => 'video',
            'aac', 'flac', 'm4a', 'mid', 'midi', 'mp3', 'mpga', 'oga', 'opus', 'wav' => 'audio',
            'pdf' => 'application',
            default => null,
        };
    }

    private function renderQuoted(AstNode $node): string
    {
        if ((bool) ($this->options['htmlQTags'] ?? false)) {
            $quoted = $this->qTagContentsAndAttributes($node);

            return '<q' . $quoted['attrs'] . '>' . $quoted['contents'] . '</q>';
        }

        $open = $node->attr('kind') === 'single' ? "\u{2018}" : "\u{201C}";
        $close = $node->attr('kind') === 'single' ? "\u{2019}" : "\u{201D}";

        return $open . $this->renderInlines($node->children) . $close;
    }

    private function referenceLocation(): string
    {
        $value = (string) ($this->options['referenceLocation'] ?? $this->options['writerReferenceLocation'] ?? 'end_of_document');
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $value));

        return match ($normalized) {
            'endofblock' => 'end_of_block',
            'endofsection' => 'end_of_section',
            default => 'end_of_document',
        };
    }

    /**
     * @param list<string> $blocks
     */
    private function appendRenderedBlock(array &$blocks, string $html): void
    {
        if ($html !== '') {
            $blocks[] = $html;
        }
    }

    private function renderPendingFootnotes(string $referenceLocation): string
    {
        $pending = array_slice($this->footnotes, $this->flushedFootnoteCount);
        if ($pending === []) {
            return '';
        }

        $this->flushedFootnoteCount = count($this->footnotes);
        $class = 'footnotes footnotes-' . str_replace('_', '-', $referenceLocation);
        $lines = ['<div class="' . $class . '">'];
        if ($referenceLocation !== 'end_of_block') {
            $lines[] = '<hr />';
        }

        $start = $pending[0]['number'];
        $lines[] = $start === 1 ? '<ol>' : '<ol start="' . $start . '">';
        foreach ($pending as $entry) {
            $lines[] = $this->renderFootnoteItem($entry['number'], $entry['node']);
        }
        $lines[] = '</ol>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    private function renderFootnoteItem(int $number, AstNode $node): string
    {
        $blocks = [];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $blocks[] = $html;
            }
        }

        if ($blocks === []) {
            $blocks[] = '<p></p>';
        }

        $lastIndex = array_key_last($blocks);
        if ($lastIndex !== null) {
            $backlink = '<a href="#fnref' . $number . '" class="footnote-back" role="doc-backlink">' . "\u{21A9}\u{FE0E}" . '</a>';
            if (str_ends_with($blocks[$lastIndex], '</p>')) {
                $blocks[$lastIndex] = substr($blocks[$lastIndex], 0, -4) . $backlink . '</p>';
            } else {
                $blocks[$lastIndex] .= $backlink;
            }
        }

        return '<li id="fn' . $number . '">' . implode("\n", $blocks) . '</li>';
    }

    /**
     * @return array{contents:string, attrs:string}
     */
    private function qTagContentsAndAttributes(AstNode $node): array
    {
        if (count($node->children) === 1 && $node->children[0]->type === 'span') {
            $spanAttrs = $this->attrTuple($node->children[0]);
            if (
                $spanAttrs['id'] === ''
                && $spanAttrs['classes'] === []
                && array_keys($spanAttrs['attributes']) === ['cite']
            ) {
                return [
                    'contents' => $this->renderInlines($node->children[0]->children),
                    'attrs' => ' cite="' . $this->esc($spanAttrs['attributes']['cite']) . '"',
                ];
            }
        }

        return [
            'contents' => $this->renderInlines($node->children),
            'attrs' => '',
        ];
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function isBareCodeRole(array $attrs, string $role): bool
    {
        return $attrs['id'] === ''
            && $attrs['classes'] === [$role]
            && $attrs['attributes'] === [];
    }

    private function renderAttributes(AstNode $node, ?callable $attributeFilter = null): string
    {
        return $this->renderAttrTuple($this->attrTuple($node), $attributeFilter);
    }

    private function renderHtmlAttributes(AstNode $node): string
    {
        return $this->renderAttrTuple($this->htmlAttrTuple($node));
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function attrTuple(AstNode $node): array
    {
        $id = (string) $node->attr('id', '');
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);

        return [
            'id' => $id,
            'classes' => is_array($classes) ? array_values(array_map('strval', $classes)) : [],
            'attributes' => is_array($attributes)
                ? array_map('strval', array_filter($attributes, static fn (mixed $value): bool => is_scalar($value)))
                : [],
        ];
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function htmlAttrTuple(AstNode $node): array
    {
        $attrs = $this->attrTuple($node);
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            return $attrs;
        }

        if ($attrs['id'] === '' && isset($htmlAttributes['id']) && is_scalar($htmlAttributes['id'])) {
            $attrs['id'] = (string) $htmlAttributes['id'];
        }

        if ($attrs['classes'] === [] && isset($htmlAttributes['class']) && is_scalar($htmlAttributes['class'])) {
            $classes = preg_split('/\s+/', trim((string) $htmlAttributes['class'])) ?: [];
            $attrs['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = (string) $name;
            if (
                in_array(strtolower($name), ['id', 'class'], true)
                || !is_scalar($value)
                || array_key_exists($name, $attrs['attributes'])
            ) {
                continue;
            }

            $attrs['attributes'][$name] = (string) $value;
        }

        return $attrs;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderAttrTuple(array $attrs, ?callable $attributeFilter = null): string
    {
        $parts = [];
        if ($attrs['id'] !== '') {
            $parts[] = 'id="' . $this->esc($attrs['id']) . '"';
        }
        if ($attrs['classes'] !== []) {
            $parts[] = 'class="' . $this->esc(implode(' ', $attrs['classes'])) . '"';
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $name = (string) $name;
            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', $name) !== 1) {
                continue;
            }
            if ($attributeFilter !== null && !$attributeFilter($name)) {
                continue;
            }
            $parts[] = $name . '="' . $this->esc($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function allowedHeadingAttribute(string $name): bool
    {
        return in_array($name, ['lang', 'dir', 'title', 'style', 'align'], true)
            || str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-');
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'softbreak',
            'linebreak',
            'code',
            'image',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
            'span',
            'quoted',
            'link',
            'note',
            'citation',
            'math',
            'raw_html_inline',
            'raw_inline',
            'raw_tex_inline',
            'raw_markdown',
        ], true);
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
                'image' => (string) $node->attr('alt', $this->plainInlineText($node->children)),
                'citation' => (string) $node->attr('text', $this->plainInlineText($node->children)),
                'math' => (string) $node->attr('text', ''),
                'raw_html_inline' => '',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
