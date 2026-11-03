<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged footnote registry and endnotes renderer. */
final class WordPressFootnoteRenderer
{
    /** @var list<array{number:int, anchor:string, label:string, node:AstNode}> */
    private array $footnotes = [];

    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    /** @return list<array{number:int, anchor:string, label:string, node:AstNode}> */
    public function beginScope(): array
    {
        $previous = $this->footnotes;
        $this->footnotes = [];

        return $previous;
    }

    /** @param list<array{number:int, anchor:string, label:string, node:AstNode}> $previous */
    public function restoreScope(array $previous): void
    {
        $this->footnotes = $previous;
    }

    public function hasFootnotes(): bool
    {
        return $this->footnotes !== [];
    }

    /** @return array{number:int, anchor:string, label:string, node:AstNode} */
    public function register(AstNode $node): array
    {
        $number = count($this->footnotes) + 1;
        $label = $this->noteSourceLabel($node);
        $anchor = $this->resolvedFootnoteAnchor($label, $number);
        $entry = ['number' => $number, 'anchor' => $anchor, 'label' => $label, 'node' => $node];
        $this->footnotes[] = $entry;

        return $entry;
    }

    public function render(): string
    {
        $items = [];
        foreach ($this->footnotes as $footnote) {
            $anchor = $footnote['anchor'];
            $label = $footnote['label'];
            $labelAttr = $label === '' ? '' : ' data-pandoc-note-label="' . $this->esc($label) . '"';
            $backlinkAttrs = $label === ''
                ? ' href="#fnref-' . $this->esc($anchor) . '" aria-label="Back to content"'
                : ' href="#fnref-' . $this->esc($anchor) . '" class="footnote-back" role="doc-backlink" aria-label="Back to content"';
            $items[] = '<li id="fn-' . $this->esc($anchor) . '"' . $labelAttr . '>'
                . $this->call('renderBlocksAsHtml', $footnote['node']->children, false)
                . ' <a' . $backlinkAttrs . '>Back</a>'
                . '</li>';
        }

        $inner = '<!-- wp:list {"ordered":true} -->'
            . "\n" . '<ol>' . implode('', $items) . '</ol>'
            . "\n" . '<!-- /wp:list -->';
        $group = new AstNode('div', ['htmlAttributes' => ['role' => 'doc-endnotes']]);

        return $this->call('renderGroupBlock', $group, ['footnotes'], $inner);
    }

    private function noteSourceLabel(AstNode $node): string
    {
        if (!$this->containsProcessedCslNoteCitation($node)) {
            return '';
        }

        foreach (['label', 'noteLabel'] as $attribute) {
            $label = $node->attr($attribute);
            if (!is_scalar($label)) {
                continue;
            }

            $label = trim(preg_replace('/\s+/', ' ', (string) $label) ?? (string) $label);
            if ($this->isSafeFootnoteAnchorLabel($label)) {
                return $label;
            }
        }

        return '';
    }

    private function containsProcessedCslNoteCitation(AstNode $node): bool
    {
        if ($node->type === 'citation' && (string) $node->attr('cslStyleClass', '') === 'note') {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->containsProcessedCslNoteCitation($child)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeFootnoteAnchorLabel(string $label): bool
    {
        return $label !== ''
            && strlen($label) <= 999
            && preg_match('/^[A-Za-z0-9_.:-]+$/', $label) === 1;
    }

    private function resolvedFootnoteAnchor(string $label, int $number): string
    {
        $base = $label === '' ? (string) $number : $label;
        $anchor = $base;
        $suffix = 2;
        while ($this->footnoteAnchorExists($anchor)) {
            $anchor = $base . '-' . $suffix++;
        }

        return $anchor;
    }

    private function footnoteAnchorExists(string $anchor): bool
    {
        $key = strtolower($anchor);
        foreach ($this->footnotes as $footnote) {
            if (strtolower($footnote['anchor']) === $key) {
                return true;
            }
        }

        return false;
    }

    private function esc(string $value): string
    {
        return $this->call('escape', $value);
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }
}
