<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Raw, figure, quote, verse, division, and separator top-level nodes. */
final class WordPressTopLevelSpecialNodeRenderer
{
    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    public function render(AstNode $node): ?string
    {
        return match ($node->type) {
            'raw_html' => $this->extendedRenderer()->renderRawHtmlBlock($node),
            'raw_tex' => $this->extendedRenderer()->renderRawTexBlock($node),
            'raw_block' => $this->extendedRenderer()->renderRawFormatBlock($node),
            'figure' => $this->extendedRenderer()->renderFigureBlock($node),
            'blockquote' => $this->call('renderBlockQuote', $node),
            'line_block' => $this->call('renderLineBlockBlock', $node),
            'div' => $this->call('renderDivBlock', $node),
            'horizontal_rule' => $this->call('renderHorizontalRule'),
            default => null,
        };
    }

    private function extendedRenderer(): WordPressExtendedNodeRenderer
    {
        return $this->call('extendedRenderer');
    }

    private function call(string $name, mixed ...$arguments): mixed
    {
        return $this->writer->{$name}(...$arguments);
    }
}
