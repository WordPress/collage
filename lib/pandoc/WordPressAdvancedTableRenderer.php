<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Lazily loaded renderer for tables requiring the full Pandoc table model.
 *
 * Core traversal and shared escaping/inline policy remain owned by
 * WordPressBlockWriter and are supplied explicitly as callbacks.
 */
final class WordPressAdvancedTableRenderer
{
    private const MAX_TABLE_ACCESSIBILITY_SLOTS = 2000;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function render(AstNode $node): string
    {
        return '<!-- wp:table -->'
            . "\n" . '<figure' . $this->renderTableFigureAttrs($node) . '>' . $this->renderHtml($node) . '</figure>'
            . "\n" . '<!-- /wp:table -->';
    }

    public function renderHtml(AstNode $node): string
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

        $caption = (string) $node->attr('caption', '');
        $captionHtml = $caption !== '' ? $this->renderTableCaptionHtml($node) : '';
        $figcaption = $caption !== ''
            ? '<figcaption' . $this->renderTableFigcaptionAttrs($node) . '>' . $captionHtml . '</figcaption>'
            : '';
        $accessibility = $this->tableAccessibilityByCell($node);
        $cellColumns = $this->tableVisualColumnsByCell($node);
        $html = '<table' . $this->renderTableElementAttrs($node) . '>' . $this->renderTableColgroup($node);
        if ($head instanceof AstNode && $head->children !== []) {
            $html .= '<thead' . $this->renderStoredHtmlAttrs($head, true, []) . '>';
            foreach ($head->children as $row) {
                $html .= $this->renderTableRow($row, $node, true, 0, $accessibility, $cellColumns);
            }
            $html .= '</thead>';
        }

        if ($bodies === []) {
            $bodies[] = new AstNode('table_body');
        }
        foreach ($bodies as $body) {
            $html .= '<tbody' . $this->renderStoredHtmlAttrs($body, true, []) . '>';
            $bodyHeadRows = $body->attr('headRows', []);
            if (is_array($bodyHeadRows)) {
                foreach ($bodyHeadRows as $row) {
                    if ($row instanceof AstNode) {
                        $html .= $this->renderTableRow($row, $node, true, 0, $accessibility, $cellColumns);
                    }
                }
            }
            $rowHeadColumns = max(0, (int) $body->attr('rowHeadColumns', 0));
            foreach ($body->children as $row) {
                $html .= $this->renderTableRow($row, $node, false, $rowHeadColumns, $accessibility, $cellColumns);
            }
            $html .= '</tbody>';
        }
        if ($foot instanceof AstNode && $foot->children !== []) {
            $html .= '<tfoot' . $this->renderStoredHtmlAttrs($foot, true, []) . '>';
            foreach ($foot->children as $row) {
                $html .= $this->renderTableRow($row, $node, false, 0, $accessibility, $cellColumns);
            }
            $html .= '</tfoot>';
        }
        $html .= '</table>';

        return $figcaption !== '' && $this->tableCaptionBeforeTable($node)
            ? $figcaption . $html
            : $html . $figcaption;
    }

    private function tableCaptionBeforeTable(AstNode $node): bool
    {
        if ((string) $node->attr('caption', '') === '') {
            return false;
        }

        $geometry = $node->attr('tableGeometry', []);
        if (is_array($geometry)) {
            $captions = $geometry['captions'] ?? [];
            $long = is_array($captions) && is_array($captions['long'] ?? null) ? $captions['long'] : [];
            if (($long['captionBeforeTable'] ?? false) === true) {
                return true;
            }
        }

        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return false;
        }

        return strtolower(trim((string) ($captionSource['captionSide'] ?? ''))) === 'top';
    }

    private function renderTableFigcaptionAttrs(AstNode $node): string
    {
        $attrs = $this->tableCaptionSourceAttributes($node);
        $classes = ['wp-element-caption'];
        $sourceClass = trim((string) ($attrs['class'] ?? ''));
        if ($sourceClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $sourceClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $attrs['class'] = implode(' ', array_values(array_unique($classes)));

        return $this->renderTableCaptionAttrMap($attrs);
    }

    private function tableCaptionSourceAttributes(AstNode $node): array
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return [];
        }

        $sourceAttributes = $captionSource['sourceAttributes'] ?? [];
        if (!is_array($sourceAttributes)) {
            return [];
        }

        $attrs = [];
        $htmlAttributes = $sourceAttributes['htmlAttributes'] ?? [];
        if (is_array($htmlAttributes)) {
            foreach ($htmlAttributes as $name => $value) {
                $attrs[strtolower((string) $name)] = $value;
            }
        }

        if (!array_key_exists('id', $attrs)) {
            $id = (string) ($sourceAttributes['id'] ?? '');
            if ($id !== '') {
                $attrs['id'] = $id;
            }
        }

        $classes = [];
        $htmlClass = trim((string) ($attrs['class'] ?? ''));
        if ($htmlClass !== '') {
            $classes = array_merge($classes, preg_split('/\s+/', $htmlClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $sourceClasses = $sourceAttributes['classes'] ?? [];
        if (is_array($sourceClasses)) {
            foreach ($sourceClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
        if ($classes !== []) {
            $attrs['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $attributes = $sourceAttributes['attributes'] ?? [];
        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                $name = strtolower((string) $name);
                if (!array_key_exists($name, $attrs)) {
                    $attrs[$name] = $value;
                }
            }
        }

        return $attrs;
    }

    private function renderTableFigureAttrs(AstNode $node): string
    {
        $attrs = ' class="wp-block-table"';
        $shortCaption = (string) $node->attr('shortCaption', '');
        if ($shortCaption !== '') {
            $attrs .= ' data-pandoc-short-caption="' . $this->esc($shortCaption) . '"';
        }

        return $attrs;
    }

    private function renderTableElementAttrs(AstNode $node): string
    {
        $attrs = $this->storedTableAttrMap($node, true, []);
        $this->sanitizeTableElementAttrs($attrs);

        return $this->renderHtmlAttrMap($attrs);
    }

    private function renderTableCaptionHtml(AstNode $node): string
    {
        $blocks = $node->attr('captionBlocks', null);
        if (is_array($blocks) && $blocks !== []) {
            foreach ($blocks as $block) {
                if (!$block instanceof AstNode) {
                    return $this->renderCaptionInlines($node);
                }
            }

            if (count($blocks) === 1 && in_array($blocks[0]->type, ['plain', 'paragraph'], true)) {
                if ($this->tableCaptionShouldPreserveBlockWrapper($node)) {
                    return $this->renderBlocksAsHtml($blocks, true);
                }

                return $this->renderInlines($blocks[0]);
            }

            return $this->renderBlocksAsHtml($blocks);
        }

        return $this->renderCaptionInlines($node);
    }

    private function tableCaptionShouldPreserveBlockWrapper(AstNode $node): bool
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource) || $captionSource === []) {
            return false;
        }

        return is_string($captionSource['source'] ?? null)
            || is_string($captionSource['sourcePosition'] ?? null)
            || is_array($captionSource['sourceAttributes'] ?? null);
    }

    private function renderCaptionInlines(AstNode $node): string
    {
        $inlines = $node->attr('captionInlines', null);
        if (!is_array($inlines)) {
            return $this->esc((string) $node->attr('caption', ''));
        }

        $html = '';
        foreach ($inlines as $inline) {
            if (!$inline instanceof AstNode) {
                return $this->esc((string) $node->attr('caption', ''));
            }

            $html .= $this->renderInlineNode($inline);
        }

        return $html;
    }

    private function renderTableColgroup(AstNode $node): string
    {
        $sourceColumns = $node->attr('columnSources', []);
        if (is_array($sourceColumns) && $sourceColumns !== []) {
            $columnCount = TableGeometry::columnCount($node);
            if (count($sourceColumns) < $columnCount) {
                return '';
            }

            $html = '';
            foreach ($this->sourceColumnGroups($sourceColumns) as $group) {
                $source = is_array($group['source'] ?? null) ? $group['source'] : [];
                $groupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];
                $groupHtmlAttributes = is_array($groupAttributes['htmlAttributes'] ?? null) ? $groupAttributes['htmlAttributes'] : [];

                $html .= '<colgroup' . $this->renderHtmlAttrMap($this->sanitizeTableColumnSourceAttrs($groupHtmlAttributes, true)) . '>';

                $columns = is_array($group['columns'] ?? null) ? array_values($group['columns']) : [];
                foreach ($columns as $offset => $column) {
                    $column = (int) $column;
                    $columnSource = $this->sourceForColumn($node, $column);
                    $colAttributes = is_array($columnSource['colAttributes'] ?? null) && is_array($columnSource['colAttributes']['htmlAttributes'] ?? null)
                        ? $columnSource['colAttributes']['htmlAttributes']
                        : [];
                    $attrs = $this->sanitizeTableColumnSourceAttrs($colAttributes, false);

                    $width = $columnSource['width'] ?? null;
                    if (!is_numeric($width) || (float) $width <= 0.0) {
                        $tableWidths = $node->attr('widths', []);
                        $width = is_array($tableWidths) && is_numeric($tableWidths[$column] ?? null)
                            ? (float) $tableWidths[$column]
                            : null;
                    }
                    if (!is_numeric($width) || (float) $width <= 0.0) {
                        continue;
                    }

                    $styles = ['width:' . $this->formatTableWidth((float) $width)];
                    array_push($styles, ...$this->columnSourceStyleDeclarations($columnSource));
                    $attrs['style'] = implode('; ', $styles);
                    $html .= '<col' . $this->renderHtmlAttrMap($attrs) . '/>';
                }

                $html .= '</colgroup>';
            }

            return $html;
        }

        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $cols = [];
        foreach ($widths as $width) {
            if (!is_numeric($width) || (float) $width <= 0.0) {
                return '';
            }

            $cols[] = '<col style="width:' . $this->esc($this->formatTableWidth((float) $width)) . '"/>';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function formatTableWidth(float $width): string
    {
        $formatted = rtrim(rtrim(number_format($width * 100, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    private function renderTableRow(
        AstNode $row,
        AstNode $table,
        bool $header,
        int $rowHeadColumns = 0,
        array $accessibility = [],
        array $cellColumns = []
    ): string
    {
        $html = '<tr' . $this->renderStoredHtmlAttrs($row, true, []) . '>';
        $logicalColumn = 0;
        foreach ($row->children as $index => $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }
            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $cellId = spl_object_id($cell);
            $visualColumn = $cellColumns[$cellId] ?? $logicalColumn;
            $attrs = $this->renderTableCellAttrs($table, $visualColumn, $cell, $accessibility[$cellId] ?? []);
            $tag = $header || $cell->attr('header') === true || ($visualColumn < $rowHeadColumns && $visualColumn + $colspan <= $rowHeadColumns) ? 'th' : 'td';
            $html .= '<' . $tag . $attrs . '>' . $this->renderTableCellContent($cell) . '</' . $tag . '>';
            $logicalColumn += $colspan;
        }

        return $html . '</tr>';
    }

    private function renderTableCellContent(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->esc((string) $cell->attr('text', ''));
        }

        $html = '';
        foreach ($cell->children as $child) {
            if ($this->isInlineNode($child)) {
                $html .= $this->renderInlineNode($child);
                continue;
            }

            $html .= $this->renderBlocksAsHtml([$child]);
        }

        return $html;
    }

    private function renderTableCellAttrs(AstNode $table, int $column, AstNode $cell, array $accessibility = []): string
    {
        $attrMap = $this->storedTableAttrMap($cell, true, ['style']);
        $this->sanitizeTableCellAttrs($attrMap, $cell, $accessibility);
        if ($this->shouldMarkEmptyTableCell($cell)) {
            $attrMap['data-pandoc-empty-cell'] = 'true';
        }

        $colspan = (int) $cell->attr('colspan', 1);
        if ($colspan > 1) {
            $attrMap['colspan'] = (string) $colspan;
        }

        $rowspan = (int) $cell->attr('renderRowspan', $cell->attr('rowspan', 1));
        if ($rowspan > 1) {
            $attrMap['rowspan'] = (string) $rowspan;
        }

        $alignments = $table->attr('alignments', []);
        $alignment = (string) $cell->attr('align', '');
        if ($alignment === '' && is_array($alignments)) {
            $alignment = (string) ($alignments[$column] ?? 'default');
        }

        $styles = [];
        $sourceStyle = $this->storedHtmlStyle($cell);
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        $verticalAlignment = (string) $cell->attr('valign', '');
        if (
            in_array($verticalAlignment, ['baseline', 'top', 'middle', 'bottom'], true)
            && !($verticalAlignment === 'top' && $table->attr('sourceFormat') === 'docbook')
            && !isset($attrMap['valign'])
            && preg_match('/(?:^|;)\s*vertical-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'vertical-align:' . $verticalAlignment;
        }

        if ($styles !== []) {
            $attrMap['style'] = implode('; ', $styles);
        }

        return $this->renderHtmlAttrMap($attrMap);
    }

    private function shouldMarkEmptyTableCell(AstNode $cell): bool
    {
        return (bool) ($this->options['markEmptyTableCells'] ?? false)
            && $this->isEmptyTableCell($cell);
    }

    private function isEmptyTableCell(AstNode $cell): bool
    {
        if ($cell->children === []) {
            return trim((string) $cell->attr('text', '')) === '';
        }

        foreach ($cell->children as $child) {
            if ($this->nodeHasVisibleTableCellContent($child)) {
                return false;
            }
        }

        return true;
    }

    private function nodeHasVisibleTableCellContent(AstNode $node): bool
    {
        if (trim((string) $node->attr('text', '')) !== '') {
            return true;
        }

        if (in_array($node->type, ['image', 'code_block', 'raw_html', 'raw_tex', 'raw_block', 'table', 'horizontal_rule'], true)) {
            foreach (['url', 'src', 'alt', 'html', 'tex', 'format'] as $attr) {
                if (trim((string) $node->attr($attr, '')) !== '') {
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->nodeHasVisibleTableCellContent($child)) {
                return true;
            }
        }

        return false;
    }

    private function tableAccessibilityByCell(AstNode $table): array
    {
        if ($this->tableCanUseDirectLayout($table)) {
            return [];
        }

        if ($table->attributeResolver() instanceof CompactDelimitedTableAttributes) {
            return [];
        }

        if ($this->tableAccessibilitySlotCount($table) > self::MAX_TABLE_ACCESSIBILITY_SLOTS) {
            return [];
        }

        $accessibility = TableGeometry::accessibilityAttributes($table);
        if ($accessibility === []) {
            return [];
        }

        $byCell = [];
        $coverage = TableGeometry::cellCoverage($table);
        $renderGeneratedHeaders = false;
        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }
            $htmlAttributes = $node->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && strtolower(trim((string) ($htmlAttributes['scope'] ?? ''))) === 'auto') {
                $renderGeneratedHeaders = true;
                break;
            }
        }

        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $key = implode(':', [
                (string) ($record['section'] ?? ''),
                (string) ((int) ($record['row'] ?? 0)),
                (string) ((int) ($record['sourceCell'] ?? 0)),
                (string) ((int) ($record['sourceColumn'] ?? 0)),
            ]);
            if (isset($accessibility[$key]) && is_array($accessibility[$key])) {
                $attrs = $accessibility[$key];
                if ($renderGeneratedHeaders) {
                    $attrs['__renderGeneratedHeaders'] = true;
                }
                $byCell[spl_object_id($node)] = $attrs;
            }
        }

        return $byCell;
    }

    private function tableAccessibilitySlotCount(AstNode $table): int
    {
        $columnCount = TableGeometry::columnCount($table);
        if ($columnCount <= 0) {
            return 0;
        }

        return $columnCount * $this->tableRowCount($table);
    }

    private function tableRowCount(AstNode $node): int
    {
        $count = $node->type === 'table_row' ? 1 : 0;
        foreach ($node->children as $child) {
            $count += $this->tableRowCount($child);
        }

        return $count;
    }

    private function tableVisualColumnsByCell(AstNode $table): array
    {
        if ($this->tableCanUseDirectLayout($table)) {
            return [];
        }

        if ($table->attributeResolver() instanceof CompactDelimitedTableAttributes) {
            return [];
        }

        $byCell = [];
        foreach (TableGeometry::cellCoverage($table) as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $byCell[spl_object_id($node)] = max(0, (int) ($record['column'] ?? 0));
        }

        return $byCell;
    }

    private function tableCanUseDirectLayout(AstNode $table): bool
    {
        $sourceColumns = $table->attr('columnSources', []);
        if (is_array($sourceColumns) && $sourceColumns !== []) {
            return false;
        }

        foreach ($table->children as $section) {
            if ($section->type === 'table_head' || $section->type === 'table_foot') {
                if ($section->children !== []) {
                    return false;
                }
                continue;
            }
            if ($section->type !== 'table_body') {
                return false;
            }

            if ((int) $section->attr('rowHeadColumns', 0) > 0) {
                return false;
            }
            $headRows = $section->attr('headRows', []);
            if (is_array($headRows) && $headRows !== []) {
                return false;
            }

            foreach ($section->children as $row) {
                if ($row->type !== 'table_row') {
                    return false;
                }
                foreach ($row->children as $cell) {
                    if ($cell->type !== 'table_cell') {
                        continue;
                    }
                    if (
                        (int) $cell->attr('colspan', 1) !== 1
                        || (int) $cell->attr('renderRowspan', $cell->attr('rowspan', 1)) !== 1
                        || $cell->attr('header') === true
                    ) {
                        return false;
                    }
                    $htmlAttributes = $cell->attr('htmlAttributes', []);
                    if (
                        is_array($htmlAttributes)
                        && (trim((string) ($htmlAttributes['scope'] ?? '')) !== ''
                            || trim((string) ($htmlAttributes['headers'] ?? '')) !== '')
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function storedTableAttrMap(AstNode $node, bool $includeIdentity, array $skip): array
    {
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if ($htmlAttributes === []) {
            return [];
        }

        $attrs = [];
        if ($includeIdentity) {
            $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
            if ($id !== '') {
                $attrs['id'] = $id;
            }

            $class = (string) ($htmlAttributes['class'] ?? '');
            if ($class === '') {
                $classes = $node->attr('classes', []);
                if (is_array($classes) && $classes !== []) {
                    $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                }
            }
            if ($class !== '') {
                $attrs['class'] = $class;
            }
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (
                $name === 'id'
                || $name === 'class'
                || in_array($name, $skip, true)
                || !$this->isAllowedStoredTableHtmlAttr($name)
            ) {
                continue;
            }

            $attrs[$name] = (string) $value;
        }

        return $attrs;
    }

    private function renderHtmlAttrMap(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === '' || $value === null || $value === false) {
                continue;
            }

            $html .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $html;
    }

    private function renderTableCaptionAttrMap(array $attrs): string
    {
        $rendered = [];
        foreach (['id', 'class'] as $priorityName) {
            if (!array_key_exists($priorityName, $attrs)) {
                continue;
            }
            $value = $attrs[$priorityName];
            if ($this->isAllowedTableCaptionHtmlAttr($priorityName)) {
                $rendered[$priorityName] = (string) $value;
            }
        }
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            if (array_key_exists($name, $rendered)) {
                continue;
            }
            if (
                !$this->isAllowedTableCaptionHtmlAttr($name)
                || ($name === 'translate' && $this->normalizeTranslateAttr((string) $value) === '')
            ) {
                continue;
            }
            if ($name === 'lang' || $name === 'xml:lang') {
                $value = $this->normalizeLanguageAttr((string) $value);
                if ($value === '') {
                    continue;
                }
            }
            $rendered[$name] = (string) $value;
        }

        return $this->renderHtmlAttrMap($rendered);
    }

    private function sanitizeTableElementAttrs(array &$attrs): void
    {
        $source = $attrs;
        $sanitized = [];
        $xmlLanguage = isset($source['xml:lang']) ? $this->normalizeLanguageAttr((string) $source['xml:lang']) : '';

        foreach ($source as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;

            if ($name === 'style') {
                $styles = $this->tableStyleDeclarations($value);
                if ($styles !== []) {
                    $sanitized['style'] = implode('; ', $styles);
                }
                continue;
            }

            if ($name === 'lang') {
                $language = $xmlLanguage !== '' ? $xmlLanguage : $this->normalizeLanguageAttr($value);
                if ($language !== '') {
                    $sanitized[$name] = $language;
                }
                continue;
            }

            if ($name === 'xml:lang') {
                if ($xmlLanguage !== '') {
                    $sanitized[$name] = $xmlLanguage;
                }
                continue;
            }

            if ($name === 'translate') {
                $translate = $this->normalizeTranslateAttr($value);
                if ($translate !== '') {
                    $sanitized[$name] = $translate;
                }
                continue;
            }

            if ($name === 'width' || $name === 'height') {
                $dimension = $this->normalizeTableDimensionAttr($value);
                if ($dimension !== '') {
                    $sanitized[$name] = $dimension;
                }
                continue;
            }

            if ($name === 'align') {
                $alignment = $this->normalizePlacementAlignAttr($value);
                if ($alignment !== '') {
                    $sanitized[$name] = $alignment;
                }
                continue;
            }

            if ($name === 'frame') {
                $frame = $this->normalizeTableFrameAttr($value);
                if ($frame !== '') {
                    $sanitized[$name] = $frame;
                }
                continue;
            }

            if ($name === 'rules') {
                $rules = $this->normalizeTableRulesAttr($value);
                if ($rules !== '') {
                    $sanitized[$name] = $rules;
                }
                continue;
            }

            if ($name === 'border') {
                $border = $this->normalizeTableBorderAttr($value);
                if ($border !== '') {
                    $sanitized[$name] = $border;
                }
                continue;
            }

            if ($name === 'cellpadding' || $name === 'cellspacing') {
                $spacing = $this->normalizeTableSpacingAttr($value);
                if ($spacing !== '') {
                    $sanitized[$name] = $spacing;
                }
                continue;
            }

            if ($name === 'bgcolor') {
                $color = $this->normalizeCssColor($value);
                if ($color !== '') {
                    $sanitized[$name] = $color;
                }
                continue;
            }

            if ($name === 'bordercolor') {
                continue;
            }

            if ($this->isAllowedTableElementPassthroughAttr($name)) {
                $sanitized[$name] = $value;
            }
        }

        $attrs = $this->orderTableElementAttrs($sanitized);
    }

    private function sanitizeTableCellAttrs(array &$attrs, AstNode $cell, array $accessibility): void
    {
        $source = $attrs;
        $sanitized = [];
        $sourceScope = strtolower(trim((string) ($source['scope'] ?? '')));
        if ($sourceScope === 'auto') {
            $scope = strtolower(trim((string) ($accessibility['scope'] ?? '')));
            if (in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                $sanitized['scope'] = $scope;
            }
        }

        foreach ($source as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;

            if ($name === 'align') {
                if (strtolower(trim($value)) === 'char') {
                    $sanitized[$name] = 'char';
                }
                continue;
            }

            if ($name === 'scope') {
                $scope = strtolower(trim($value));
                if ($scope !== 'auto' && in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                    $sanitized[$name] = $scope;
                }
                continue;
            }

            if ($name === 'nowrap') {
                if (!in_array(strtolower(trim($value)), ['0', 'false', 'no'], true)) {
                    $sanitized[$name] = $value === '' ? 'nowrap' : $value;
                }
                continue;
            }

            if ($name === 'valign') {
                $valign = strtolower(trim($value));
                if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
                    $sanitized[$name] = $valign;
                }
                continue;
            }

            if ($name === 'width' || $name === 'height') {
                $dimension = $this->normalizeTableDimensionAttr($value);
                if ($dimension !== '') {
                    $sanitized[$name] = $dimension;
                }
                continue;
            }

            if ($name === 'lang' || $name === 'xml:lang') {
                $language = $this->normalizeLanguageAttr($value);
                if ($language !== '') {
                    $sanitized[$name] = $language;
                }
                continue;
            }

            if ($name === 'translate') {
                $translate = $this->normalizeTranslateAttr($value);
                if ($translate !== '') {
                    $sanitized[$name] = $translate;
                }
                continue;
            }

            if ($name === 'bgcolor') {
                if ($this->normalizeCssColor($value) !== '') {
                    $sanitized[$name] = $value;
                }
                continue;
            }

            if (in_array($name, ['id', 'class', 'abbr', 'axis', 'char', 'charoff', 'dir', 'headers', 'role', 'title'], true)
                || str_starts_with($name, 'data-')
                || str_starts_with($name, 'aria-')
                || $this->isAllowedSafeGlobalHtmlAttr($name)
            ) {
                $sanitized[$name] = $value;
            }
        }

        if (!isset($sanitized['headers']) && ($accessibility['__renderGeneratedHeaders'] ?? false) === true) {
            $headers = $accessibility['headers'] ?? [];
            if (is_array($headers) && $headers !== [] && $cell->attr('header') !== true) {
                $sanitized['headers'] = implode(' ', array_map(static fn (mixed $header): string => (string) $header, $headers));
            }
        }

        $attrs = $sanitized;
    }

    private function sanitizeTableColumnSourceAttrs(array $attrs, bool $colgroup): array
    {
        $kept = [];
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;
            if (
                in_array($name, ['span', 'style', 'width'], true)
                || !$this->isAllowedTableColumnHtmlAttr($name)
            ) {
                continue;
            }
            if ($name === 'bgcolor' && $this->normalizeCssColor($value) === '') {
                continue;
            }
            if ($name === 'align') {
                if (strtolower(trim($value)) !== 'char') {
                    continue;
                }
                $value = 'char';
            }
            if ($name === 'valign' && !in_array(strtolower(trim($value)), ['baseline', 'top', 'middle', 'bottom'], true)) {
                continue;
            }

            $kept[$name] = $value;
        }

        return $this->orderTableColumnAttrs($kept, $colgroup);
    }

    private function sourceForColumn(AstNode $table, int $column): array
    {
        $sources = $table->attr('columnSources', []);
        if (is_array($sources) && isset($sources[$column]) && is_array($sources[$column])) {
            return $sources[$column];
        }

        return [];
    }

    private function sourceColumnGroups(array $sources): array
    {
        $groups = [];
        $active = null;
        $activeKey = null;
        foreach ($sources as $column => $source) {
            if (!is_array($source)) {
                continue;
            }

            $key = (string) ($source['colgroupIndex'] ?? '');
            if ($active === null || $key !== $activeKey) {
                if ($active !== null) {
                    $groups[] = $active;
                }
                $active = [
                    'source' => $source,
                    'columns' => [(int) $column],
                ];
                $activeKey = $key;
                continue;
            }

            $active['columns'][] = (int) $column;
        }

        if ($active !== null) {
            $groups[] = $active;
        }

        return $groups;
    }

    private function columnSourceStyleDeclarations(array $source): array
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) && is_array($source['colAttributes']['htmlAttributes'] ?? null)
            ? $source['colAttributes']['htmlAttributes']
            : [];
        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) && is_array($source['colgroupAttributes']['htmlAttributes'] ?? null)
            ? $source['colgroupAttributes']['htmlAttributes']
            : [];

        $styles = [];
        $background = $this->columnBackgroundDeclaration($colAttributes);
        if ($background === '') {
            $background = $this->columnBackgroundDeclaration($colgroupAttributes);
        }
        if ($background !== '') {
            $styles[] = $background;
        }

        $borderStyles = $this->columnBorderDeclarations($colAttributes);
        if ($borderStyles === []) {
            $borderStyles = $this->columnBorderDeclarations($colgroupAttributes);
        }
        array_push($styles, ...$borderStyles);

        return $styles;
    }

    private function columnBackgroundDeclaration(array $attrs): string
    {
        $styleColor = $this->styleDeclarationColor((string) ($attrs['style'] ?? ''), 'background-color');
        if ($styleColor !== '') {
            return 'background-color:' . $styleColor;
        }

        $legacyColor = $this->normalizeCssColor((string) ($attrs['bgcolor'] ?? ''));

        return $legacyColor === '' ? '' : 'background-color:' . $legacyColor;
    }

    private function columnBorderDeclarations(array $attrs): array
    {
        $style = (string) ($attrs['style'] ?? '');
        if ($style === '') {
            return [];
        }

        $declarations = [];
        $borderColor = $this->styleDeclarationColor($style, 'border-color');
        if ($borderColor !== '') {
            $declarations[] = 'border-color:' . $borderColor;
        }
        $borderStyle = $this->styleDeclarationLineStyle($style, 'border-style');
        if ($borderStyle !== '') {
            $declarations[] = 'border-style:' . $borderStyle;
        }
        $borderWidth = $this->styleDeclarationBorderWidth($style, 'border-width');
        if ($borderWidth !== '') {
            $declarations[] = 'border-width:' . $borderWidth;
        }
        array_push($declarations, ...$this->styleDeclarationBorderEdges($style));

        return $declarations;
    }

    private function tableStyleDeclarations(string $style): array
    {
        $declarations = [];
        $backgroundColor = $this->styleDeclarationColor($style, 'background-color');
        if ($backgroundColor !== '') {
            $declarations[] = 'background-color:' . $backgroundColor;
        }

        $width = $this->safeCssDimension($this->styleDeclarationValue($style, 'width'));
        if ($width !== '') {
            $declarations[] = 'width:' . $width;
        }

        $tableLayout = strtolower(trim($this->styleDeclarationValue($style, 'table-layout')));
        if (in_array($tableLayout, ['auto', 'fixed'], true)) {
            $declarations[] = 'table-layout:' . $tableLayout;
        }

        $borderCollapse = strtolower(trim($this->styleDeclarationValue($style, 'border-collapse')));
        if (in_array($borderCollapse, ['collapse', 'separate'], true)) {
            $declarations[] = 'border-collapse:' . $borderCollapse;
        }

        $borderColor = $this->styleDeclarationColor($style, 'border-color');
        if ($borderColor !== '') {
            $declarations[] = 'border-color:' . $borderColor;
        }
        $borderStyle = $this->styleDeclarationLineStyle($style, 'border-style');
        if ($borderStyle !== '') {
            $declarations[] = 'border-style:' . $borderStyle;
        }
        $borderWidth = $this->styleDeclarationBorderWidth($style, 'border-width');
        if ($borderWidth !== '') {
            $declarations[] = 'border-width:' . $borderWidth;
        }

        return $declarations;
    }

    private function styleDeclarationLineStyle(string $style, string $property): string
    {
        return $this->normalizeBorderLineStyle($this->styleDeclarationValue($style, $property));
    }

    private function styleDeclarationBorderWidth(string $style, string $property): string
    {
        return $this->normalizeBorderWidth($this->styleDeclarationValue($style, $property));
    }

    private function styleDeclarationBorderEdges(string $style): array
    {
        $declarations = [];
        foreach (['top', 'right', 'bottom', 'left'] as $edge) {
            $shorthand = $this->normalizeBorderShorthand($this->styleDeclarationValue($style, 'border-' . $edge));
            if ($shorthand !== '') {
                $declarations[] = 'border-' . $edge . ':' . $shorthand;
            }

            $width = $this->styleDeclarationBorderWidth($style, 'border-' . $edge . '-width');
            if ($width !== '') {
                $declarations[] = 'border-' . $edge . '-width:' . $width;
            }
            $lineStyle = $this->styleDeclarationLineStyle($style, 'border-' . $edge . '-style');
            if ($lineStyle !== '') {
                $declarations[] = 'border-' . $edge . '-style:' . $lineStyle;
            }
            $color = $this->styleDeclarationColor($style, 'border-' . $edge . '-color');
            if ($color !== '') {
                $declarations[] = 'border-' . $edge . '-color:' . $color;
            }
        }

        return $declarations;
    }

    private function normalizeBorderShorthand(string $value): string
    {
        $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $width = '';
        $style = '';
        $color = '';
        foreach ($tokens as $token) {
            if ($width === '') {
                $candidate = $this->normalizeBorderWidth($token);
                if ($candidate !== '') {
                    $width = $candidate;
                    continue;
                }
            }
            if ($style === '') {
                $candidate = $this->normalizeBorderLineStyle($token);
                if ($candidate !== '') {
                    $style = $candidate;
                    continue;
                }
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

        $parts = array_values(array_filter([$width, $style, $color], static fn (string $part): bool => $part !== ''));

        return count($parts) >= 2 ? implode(' ', $parts) : '';
    }

    private function normalizeBorderLineStyle(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'], true)
            ? $value
            : '';
    }

    private function normalizeBorderWidth(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === [] || count($tokens) > 4) {
            return '';
        }

        $widths = [];
        foreach ($tokens as $token) {
            $width = $this->normalizeBorderWidthToken($token);
            if ($width === '') {
                return '';
            }
            $widths[] = $width;
        }

        return implode(' ', $widths);
    }

    private function normalizeBorderWidthToken(string $value): string
    {
        if (in_array($value, ['thin', 'medium', 'thick'], true)) {
            return $value;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(px|pt|pc|in|cm|mm|em|rem)$/i', $value, $match) !== 1) {
            return '';
        }

        $number = (float) $match[1];
        if ($number < 0.0 || $number > 10000.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . strtolower($match[2]);
    }

    private function normalizeTableDimensionAttr(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[1-9]\d{0,3}$/', $value) === 1) {
            return (string) (int) $value;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', $value, $match) !== 1) {
            return '';
        }

        $width = (float) $match[1];
        if ($width <= 0.0 || $width > 100.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($width, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    private function normalizeTableSpacingAttr(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,3}$/', $value) === 1 ? (string) (int) $value : '';
    }

    private function normalizeTableBorderAttr(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'border') {
            return '1';
        }

        return preg_match('/^\d{1,3}$/', $value) === 1 ? (string) (int) $value : '';
    }

    private function normalizeTableFrameAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['void', 'above', 'below', 'hsides', 'lhs', 'rhs', 'vsides', 'box', 'border'], true) ? $value : '';
    }

    private function normalizeTableRulesAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'groups', 'rows', 'cols', 'all'], true) ? $value : '';
    }

    private function normalizePlacementAlignAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['left', 'right', 'center'], true) ? $value : '';
    }

    private function normalizeLanguageAttr(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $value) !== 1) {
            return '';
        }

        $parts = explode('-', $value);
        $normalized = [strtolower(array_shift($parts))];
        foreach ($parts as $part) {
            $normalized[] = strlen($part) === 2 ? strtoupper($part) : $part;
        }

        return implode('-', $normalized);
    }

    private function normalizeTranslateAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    private function orderTableElementAttrs(array $attrs): array
    {
        if (!isset($attrs['border']) && !isset($attrs['frame']) && !isset($attrs['rules'])) {
            return $attrs;
        }

        $ordered = [];
        $insertedLegacy = false;
        foreach ($attrs as $name => $value) {
            if (in_array($name, ['border', 'frame', 'rules'], true)) {
                if (!$insertedLegacy) {
                    foreach (['border', 'frame', 'rules'] as $legacyName) {
                        if (isset($attrs[$legacyName])) {
                            $ordered[$legacyName] = $attrs[$legacyName];
                        }
                    }
                    $insertedLegacy = true;
                }
                continue;
            }

            $ordered[$name] = $value;
        }

        return $ordered;
    }

    private function orderTableColumnAttrs(array $attrs, bool $colgroup): array
    {
        $ordered = [];
        foreach (['id', 'class'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (str_starts_with($name, 'aria-')) {
                $ordered[$name] = $value;
            }
        }
        foreach (['align', 'char', 'charoff', 'bgcolor'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (str_starts_with($name, 'data-')) {
                $ordered[$name] = $value;
            }
        }
        foreach (['title', 'valign', 'dir', 'lang', 'xml:lang', 'translate', 'role'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (!isset($ordered[$name])) {
                $ordered[$name] = $value;
            }
        }

        return $ordered;
    }

    private function renderStoredHtmlAttrs(AstNode $node, bool $includeIdentity, array $skip): string
    {
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if ($htmlAttributes === []) {
            return '';
        }

        $attrs = '';
        if ($includeIdentity) {
            $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
            if ($id !== '') {
                $attrs .= ' id="' . $this->esc($id) . '"';
            }

            $class = (string) ($htmlAttributes['class'] ?? '');
            if ($class === '') {
                $classes = $node->attr('classes', []);
                if (is_array($classes) && $classes !== []) {
                    $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                }
            }
            if ($class !== '') {
                $attrs .= ' class="' . $this->esc($class) . '"';
            }
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (
                $name === 'id'
                || $name === 'class'
                || in_array($name, $skip, true)
                || !$this->isAllowedTableHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function storedHtmlStyle(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['style'])) {
            return trim((string) $htmlAttributes['style']);
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['style'])) {
            return trim((string) $attributes['style']);
        }

        return '';
    }

    private function isAllowedTableHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, [
                'abbr',
                'axis',
                'bgcolor',
                'border',
                'cellpadding',
                'cellspacing',
                'char',
                'charoff',
                'class',
                'dir',
                'frame',
                'headers',
                'height',
                'id',
                'lang',
                'nowrap',
                'role',
                'rules',
                'scope',
                'style',
                'summary',
                'title',
                'translate',
                'valign',
                'width',
                'xml:lang',
            ], true);
    }

    private function isAllowedStoredTableHtmlAttr(string $name): bool
    {
        return $this->isAllowedTableHtmlAttr($name)
            || in_array($name, ['align', 'bordercolor'], true);
    }

    private function isAllowedTableElementPassthroughAttr(string $name): bool
    {
        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['id', 'class', 'dir', 'role', 'summary', 'title'], true);
    }

    private function isAllowedTableCaptionHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'dir', 'id', 'lang', 'role', 'style', 'title', 'translate', 'xml:lang'], true);
    }

    private function isAllowedTableColumnHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['align', 'bgcolor', 'char', 'charoff', 'class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'valign', 'xml:lang'], true);
    }

    private function isInlineNode(AstNode $node): bool
    {
        return $this->writer->isInlineNode($node);
    }

    private function styleDeclarationValue(string $style, string $property): string
    {
        return $this->writer->styleDeclarationValue($style, $property);
    }

    private function styleDeclarationColor(string $style, string $property): string
    {
        return $this->writer->styleDeclarationColor($style, $property);
    }

    private function normalizeCssColor(string $value): string
    {
        return $this->writer->normalizeCssColor($value);
    }

    private function safeCssDimension(string $value): string
    {
        return $this->writer->safeCssDimension($value);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsHtml(array $blocks, bool $wrapPlainBlocks = false): string
    {
        return $this->writer->renderBlocksAsHtml($blocks, $wrapPlainBlocks);
    }

    private function renderInlines(AstNode $node): string
    {
        return $this->writer->renderInlines($node);
    }

    private function renderInlineNode(AstNode $node): string
    {
        return $this->writer->renderInlineNode($node);
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineHtmlAttributes(AstNode $node): array
    {
        return $this->writer->inlineHtmlAttributes($node);
    }

    private function isAllowedSafeGlobalHtmlAttr(string $name): bool
    {
        return $this->writer->isAllowedSafeGlobalHtmlAttr($name);
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }
}
