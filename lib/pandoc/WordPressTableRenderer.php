<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged strict table fast path with advanced-table fallback. */
final class WordPressTableRenderer
{
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
        $simpleHtml = $this->tryRenderSimpleTableHtml($node);
        if ($simpleHtml !== null) {
            return '<!-- wp:table -->'
                . "\n" . '<figure class="wp-block-table">' . $simpleHtml . '</figure>'
                . "\n" . '<!-- /wp:table -->';
        }

        return $this->advancedRenderer()->render($node);
    }

    public function renderHtml(AstNode $node): string
    {
        return $this->tryRenderSimpleTableHtml($node) ?? $this->advancedRenderer()->renderHtml($node);
    }

    private function tryRenderSimpleTableHtml(AstNode $table): ?string
    {
        if (
            $table->type !== 'table'
            || (bool) ($this->options['markEmptyTableCells'] ?? false)
            || !$this->hasOnlySimpleTableAttrs(
                $table,
                ['sourceNodeId', 'sourceLineIds', 'sourceLineEdges']
            )
        ) {
            return null;
        }

        $sections = $table->children;
        $sectionIndex = 0;
        $columnCount = null;
        $html = '<table>';
        if (($sections[0] ?? null) instanceof AstNode && $sections[0]->type === 'table_head') {
            $head = $this->tryRenderSimpleTableSection($sections[0], 'thead', 'th', $columnCount);
            if ($head === null) {
                return null;
            }
            $html .= $head;
            $sectionIndex++;
        }

        $body = $sections[$sectionIndex] ?? null;
        if (
            !$body instanceof AstNode
            || $body->type !== 'table_body'
            || count($sections) !== $sectionIndex + 1
        ) {
            return null;
        }

        $bodyHtml = $this->tryRenderSimpleTableSection($body, 'tbody', 'td', $columnCount);
        if ($bodyHtml === null) {
            return null;
        }

        return $html . $bodyHtml . '</table>';
    }

    private function tryRenderSimpleTableSection(
        AstNode $section,
        string $sectionTag,
        string $cellTag,
        ?int &$columnCount
    ): ?string {
        if ($section->attrs !== [] || $section->children === []) {
            return null;
        }

        $html = '<' . $sectionTag . '>';
        foreach ($section->children as $row) {
            if (
                $row->type !== 'table_row'
                || $row->attrs !== []
                || $row->children === []
                || ($columnCount !== null && count($row->children) !== $columnCount)
            ) {
                return null;
            }
            $columnCount ??= count($row->children);
            $html .= '<tr>';
            foreach ($row->children as $cell) {
                $plain = $this->simpleTableCellPlainBlock($cell);
                if ($plain === null) {
                    return null;
                }
                $html .= '<' . $cellTag . '>' . $this->writer->renderInlines($plain) . '</' . $cellTag . '>';
            }
            $html .= '</tr>';
        }

        return $html . '</' . $sectionTag . '>';
    }

    private function simpleTableCellPlainBlock(AstNode $cell): ?AstNode
    {
        if (
            $cell->type !== 'table_cell'
            || !$this->hasOnlySimpleTableAttrs(
                $cell,
                ['text', 'sourceNodeId', 'sourceLineIds', 'sourceLineEdges'],
                ['text']
            )
            || count($cell->children) !== 1
        ) {
            return null;
        }

        $plain = $cell->children[0];
        if (
            $plain->type !== 'plain'
            || !$this->hasOnlySimpleTableAttrs($plain, ['text'], ['text'])
            || (string) $plain->attr('text', '') !== (string) $cell->attr('text', '')
        ) {
            return null;
        }

        foreach ($plain->children as $inline) {
            if (!$this->isSimpleTableInline($inline)) {
                return null;
            }
        }

        return $plain;
    }

    /**
     * @param list<string> $allowed
     * @param list<string> $required
     */
    private function hasOnlySimpleTableAttrs(AstNode $node, array $allowed, array $required = []): bool
    {
        foreach (array_keys($node->attrs) as $name) {
            if (!in_array($name, $allowed, true)) {
                return false;
            }
        }
        foreach ($required as $name) {
            if (!array_key_exists($name, $node->attrs)) {
                return false;
            }
        }

        return true;
    }

    private function isSimpleTableInline(AstNode $node, bool $insideLink = false): bool
    {
        if ($node->type === 'text') {
            return $node->children === []
                && is_string($node->attr('text'))
                && $this->hasOnlySimpleTableAttrs($node, ['text'], ['text']);
        }
        if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
            return $node->attrs === [] && $node->children === [];
        }
        if (in_array($node->type, ['emph', 'strong'], true)) {
            if ($node->attrs !== []) {
                return false;
            }
            foreach ($node->children as $child) {
                if (!$this->isSimpleTableInline($child, $insideLink)) {
                    return false;
                }
            }

            return true;
        }
        if (
            $node->type !== 'link'
            || $insideLink
            || !$this->hasOnlySimpleTableAttrs($node, ['url', 'title'], ['url'])
            || !is_string($node->attr('url'))
        ) {
            return false;
        }
        foreach ($node->children as $child) {
            if (!$this->isSimpleTableInline($child, true)) {
                return false;
            }
        }

        return true;
    }

    private function advancedRenderer(): WordPressAdvancedTableRenderer
    {
        return new WordPressAdvancedTableRenderer($this->options, $this->writer);
    }
}
