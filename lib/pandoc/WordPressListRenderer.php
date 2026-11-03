<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged renderer for native and nested WordPress lists. */
final class WordPressListRenderer
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
        private readonly WordPressBlockWriter $writer,
    ) {
    }

    public function render(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $start = (int) $node->attr('start', 1);
        $comment = '<!-- wp:list -->';
        if ($ordered) {
            $attrs = ['ordered' => true];
            if ($start > 1) {
                $attrs['start'] = $start;
            }
            $comment = '<!-- wp:list ' . json_encode($attrs, JSON_THROW_ON_ERROR) . ' -->';
        }
        $tagAttrs = $this->renderListTagAttrs($node, $ordered);
        $blocks = [];
        $items = [];

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            if ($this->listItemIsHeader($item)) {
                $blocks[] = $this->renderListHeaderBlock($item);
                continue;
            }
            $items[] = $this->renderListItem($item);
        }

        if ($items !== [] || $node->children === []) {
            $blocks[] = $comment
                . "\n" . '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>'
                . "\n" . '<!-- /wp:list -->';
        }

        return implode("\n\n", $blocks);
    }

    public function renderHtml(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $tagAttrs = $this->renderListTagAttrs($node, $ordered);
        $blocks = [];
        $items = [];
        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            if ($this->listItemIsHeader($item)) {
                $blocks[] = $this->renderListHeaderHtml($item);
                continue;
            }
            $items[] = $this->renderListItem($item);
        }
        if ($items !== [] || $node->children === []) {
            $blocks[] = '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>';
        }

        return implode('', $blocks);
    }

    private function listItemIsHeader(AstNode $item): bool
    {
        return $item->attr('listHeader') === true;
    }

    private function renderListHeaderBlock(AstNode $item): string
    {
        return $this->call('renderGroupBlock',
            new AstNode('div', $item->attrs, $item->children),
            ['pandoc-list-header'],
            $this->call('renderBlocksAsNativeBlocks', $item->children, true)
        );
    }

    private function renderListHeaderHtml(AstNode $item): string
    {
        return '<div' . $this->call('renderDivAttrs', new AstNode('div', $item->attrs, $item->children)) . '>'
            . $this->call('renderBlocksAsHtml', $item->children, false)
            . '</div>';
    }

    private function renderListTagAttrs(AstNode $node, bool $ordered): string
    {
        $attrs = $ordered ? $this->renderOrderedListTagAttrs($node) : '';
        $extraClasses = !$ordered && $this->listIsTaskList($node) ? ['task-list'] : [];

        return $attrs . $this->call('renderBlockHtmlAttrsWithClasses', $node, $extraClasses);
    }

    private function renderOrderedListTagAttrs(AstNode $node): string
    {
        $attrs = '';
        $start = (int) $node->attr('start', 1);
        if ($start > 1) {
            $attrs .= ' start="' . $start . '"';
        }

        $type = $this->orderedListHtmlType((string) $node->attr('style', 'default'));
        if ($type !== '') {
            $attrs .= ' type="' . $this->esc($type) . '"';
        }

        if ((bool) ($this->options['preserveListAttributes'] ?? false)) {
            $style = (string) $node->attr('style', 'default');
            $styleIsSourceSpecific = !in_array($style, ['', 'default', 'decimal'], true);
            if ($styleIsSourceSpecific) {
                $attrs .= ' data-pandoc-list-style="' . $this->esc($style) . '"';
            }

            $delimiter = (string) $node->attr('delimiter', 'default');
            if (
                !in_array($delimiter, ['', 'default'], true)
                && ($delimiter !== 'period' || $styleIsSourceSpecific)
            ) {
                $attrs .= ' data-pandoc-list-delimiter="' . $this->esc($delimiter) . '"';
            }
        }

        return $attrs;
    }

    private function orderedListHtmlType(string $style): string
    {
        return match ($style) {
            'lower_alpha' => 'a',
            'upper_alpha' => 'A',
            'lower_roman' => 'i',
            'upper_roman' => 'I',
            default => '',
        };
    }

    private function renderListItem(AstNode $item): string
    {
        $html = '';
        $paragraphCount = 0;
        foreach ($item->children as $child) {
            if ($child->type === 'paragraph') {
                $paragraphCount++;
            }
        }
        $wrapParagraphs = (bool) $item->attr('loose', false) || $paragraphCount > 1;
        $explicitTaskChecked = $item->attr('taskChecked', null);
        $taskChecked = is_bool($explicitTaskChecked) ? $explicitTaskChecked : $this->taskGlyphChecked($item);
        $taskPending = is_bool($taskChecked);
        $stripTaskGlyph = $taskPending && !is_bool($explicitTaskChecked);
        $children = $item->children;

        for ($index = 0, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child->type === 'bullet_list') {
                $html .= $this->renderHtml($child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->renderHtml($child, true);
                continue;
            }
            if (!$this->call('isInlineNode', $child) && $child->type !== 'paragraph') {
                if ($child->type === 'plain') {
                    $rendered = $stripTaskGlyph
                        ? $this->call('renderInlineNodesWithoutLeadingTaskGlyph', $child->children)
                        : $this->call('renderInlines', $child);
                    if ($taskPending) {
                        $rendered = $this->renderTaskListLabel($taskChecked, $rendered);
                        $taskPending = false;
                        $stripTaskGlyph = false;
                    }
                    $html .= $rendered;
                    continue;
                }
                $html .= $this->call('renderBlocksAsHtml', [$child], false);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $stripTaskGlyph
                    ? $this->call('renderInlineNodesWithoutLeadingTaskGlyph', $child->children)
                    : $this->call('renderInlines', $child);
                if ($taskPending) {
                    $rendered = $this->renderTaskListLabel($taskChecked, $rendered);
                    $taskPending = false;
                    $stripTaskGlyph = false;
                }
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }

            if ($taskPending) {
                $inlineNodes = [];
                while ($index < $count && $this->call('isInlineNode', $children[$index])) {
                    $inlineNodes[] = $children[$index];
                    $index++;
                }
                $index--;
                $inlineHtml = $stripTaskGlyph
                    ? $this->call('renderInlineNodesWithoutLeadingTaskGlyph', $inlineNodes)
                    : $this->call('renderInlineNodes', $inlineNodes);
                $html .= $this->renderTaskListLabel($taskChecked, $inlineHtml);
                $taskPending = false;
                $stripTaskGlyph = false;
                continue;
            }

            $html .= $this->call('renderInlineNode', $child);
        }

        return '<li' . $this->call('renderBlockHtmlAttrs', $item) . '>' . $html . '</li>';
    }

    private function renderTaskListLabel(bool $checked, string $html): string
    {
        $checkbox = '<input type="checkbox"' . ($checked ? ' checked=""' : '') . ' />';

        return '<label>' . $checkbox . $html . '</label>';
    }

    private function listIsTaskList(AstNode $node): bool
    {
        if ($node->attr('taskList') === true) {
            return true;
        }

        if (!$this->taskGlyphsAsCheckboxesEnabled()) {
            return false;
        }

        $hasItems = false;
        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $hasItems = true;
            if ($this->taskGlyphChecked($item) === null) {
                return false;
            }
        }

        return $hasItems;
    }

    private function taskGlyphsAsCheckboxesEnabled(): bool
    {
        return (bool) ($this->options['taskGlyphsAsCheckboxes'] ?? false);
    }

    private function taskGlyphChecked(AstNode $item): ?bool
    {
        if (!$this->taskGlyphsAsCheckboxesEnabled()) {
            return null;
        }

        foreach ($item->children as $child) {
            if (in_array($child->type, ['paragraph', 'plain'], true)) {
                return $this->taskGlyphCheckedFromInlineNodes($child->children);
            }

            if ($this->call('isInlineNode', $child)) {
                return $this->taskGlyphCheckedFromInlineNodes([$child]);
            }

            return null;
        }

        return null;
    }

    /** @param list<AstNode> $nodes */
    private function taskGlyphCheckedFromInlineNodes(array $nodes): ?bool
    {
        foreach ($nodes as $node) {
            if ($node->type !== 'text') {
                return null;
            }

            $text = (string) $node->attr('text', '');
            if ($text === '') {
                continue;
            }

            if (preg_match('/^\x{2610}/u', $text) === 1) {
                return false;
            }

            if (preg_match('/^\x{2612}/u', $text) === 1) {
                return true;
            }

            return null;
        }

        return null;
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }

    private function esc(string $value): string
    {
        return $this->call('escape', $value);
    }
}
