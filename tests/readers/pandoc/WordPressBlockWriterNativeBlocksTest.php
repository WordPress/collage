<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);

$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $value])]);
$tableCell = static fn (string $value, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, [$paragraph($value)]);
$tableRow = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

return [
    'emits the same wordpress blocks through a streaming sink' => static function (TestRunner $t) use ($paragraph, $text): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['html' => '<del>']),
            $paragraph('Deleted text.'),
            new AstNode('raw_html', ['html' => '</del>']),
            new AstNode('list_item', [], [$text('First')]),
            new AstNode('list_item', [], [$text('Second')]),
            new AstNode('paragraph', [], [
                $text('With a note'),
                new AstNode('note', [], [$paragraph('Footnote body.')]),
            ]),
        ]);

        $expected = (new WordPressBlockWriter())->write($document);
        $chunks = [];
        (new WordPressBlockWriter())->writeTo($document, static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });
        $t->same($expected, implode('', $chunks));
        $t->true(count($chunks) > 1, 'streaming writer should emit multiple bounded chunks');

        $nodes = static function () use ($document): iterable {
            foreach ($document->children as $node) {
                yield $node;
            }
        };
        $nodeChunks = [];
        (new WordPressBlockWriter())->writeNodesTo($nodes(), static function (string $chunk) use (&$nodeChunks): void {
            $nodeChunks[] = $chunk;
        });
        $t->same($expected, implode('', $nodeChunks));
    },

    'renders metadata review as native group and list blocks' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [
            'meta' => [
                'keywords' => [
                    'type' => 'MetaList',
                    'value' => [
                        ['type' => 'MetaInlines', 'value' => [$text('migration')]],
                        ['type' => 'MetaInlines', 'value' => [$text('blocks')]],
                    ],
                ],
            ],
        ]);

        $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($document);

        $t->contains('<!-- wp:group -->', $blocks);
        $t->contains('class="wp-block-group pandoc-document-metadata"', $blocks);
        $t->contains('<!-- wp:list -->', $blocks);
        $t->same(false, str_contains($blocks, '<!-- wp:html -->'), 'metadata review should not be serialized as a Custom HTML block');
    },

    'renders div line definition and footnote content as native blocks' => static function (TestRunner $t) use ($paragraph, $text): void {
        $document = new AstNode('document', [], [
            new AstNode('div', ['classes' => ['callout']], [
                new AstNode('heading', ['level' => 3], [$text('Grouped')]),
                $paragraph('Editable child paragraph.'),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [$text('First line')]),
                new AstNode('line', [], [$text('Second line')]),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('term', [], [$text('Term')]),
                    new AstNode('definition', [], [$paragraph('Definition body.')]),
                ]),
            ]),
            new AstNode('paragraph', [], [
                $text('With note'),
                new AstNode('note', [], [$paragraph('Footnote body.')]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('class="wp-block-group callout"', $blocks);
        $t->contains('<!-- wp:heading {"level":3} -->', $blocks);
        $t->contains('<!-- wp:verse -->', $blocks);
        $t->contains('<pre class="wp-block-verse">First line' . "\n" . 'Second line</pre>', $blocks);
        $t->contains('class="wp-block-group pandoc-definition-list"', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Term</strong></p>', $blocks);
        $t->contains('class="wp-block-group footnotes"', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->same(false, str_contains($blocks, '<!-- wp:html -->'), 'structured non-raw blocks should avoid Custom HTML blocks');
    },

    'renders paragraph and heading text alignment as native block attrs' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', ['align' => 'center'], [$text('Centered paragraph.')]),
            new AstNode('heading', ['level' => 2, 'align' => 'right'], [$text('Right heading')]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:paragraph {"align":"center"} -->', $blocks);
        $t->contains('<p class="has-text-align-center">Centered paragraph.</p>', $blocks);
        $t->contains('<!-- wp:heading {"level":2,"textAlign":"right"} -->', $blocks);
        $t->contains('<h2 class="has-text-align-right">Right heading</h2>', $blocks);
    },

    'renders paragraph and heading custom colors as native block style attrs' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', ['backgroundColor' => '#D9EAF7'], [$text('Shaded paragraph.')]),
            new AstNode('heading', ['level' => 2, 'textColor' => '#1F2937', 'backgroundColor' => '#FFF2CC'], [$text('Colored heading')]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:paragraph {"style":{"color":{"background":"#D9EAF7"}}} -->', $blocks);
        $t->contains('<p style="background-color:#D9EAF7">Shaded paragraph.</p>', $blocks);
        $t->contains('<!-- wp:heading {"level":2,"style":{"color":{"text":"#1F2937","background":"#FFF2CC"}}} -->', $blocks);
        $t->contains('<h2 style="color:#1F2937; background-color:#FFF2CC">Colored heading</h2>', $blocks);
    },

    'keeps raw html as an explicit custom html block' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['html' => '<aside>Source HTML</aside>']),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->' . "\n" . '<aside>Source HTML</aside>' . "\n" . '<!-- /wp:html -->', $blocks);
    },

    'renders html linegroup divs as one paragraph block with line breaks' => static function (TestRunner $t) use ($paragraph): void {
        $document = new AstNode('document', [], [
            new AstNode('div', ['classes' => ['linegroup']], [
                new AstNode('div', [], [$paragraph('April is the cruellest month, breeding')]),
                new AstNode('div', ['attributes' => ['lang' => 'de']], [$paragraph('Bin gar keine Russin')]),
                new AstNode('div', ['id' => 'ln20'], [$paragraph('Out of this stony rubbish?')]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('<p class="linegroup">April is the cruellest month, breeding<br/><span lang="de">Bin gar keine Russin</span><br/><span id="ln20">Out of this stony rubbish?</span></p>', $blocks);
        $t->same(1, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->same(0, substr_count($blocks, '<!-- wp:group -->'));
    },

    'collapses adjacent linegroup lines around mixed child blocks' => static function (TestRunner $t) use ($paragraph): void {
        $document = new AstNode('document', [], [
            new AstNode('div', ['classes' => ['linegroup']], [
                new AstNode('div', [], [$paragraph('What are the roots that clutch')]),
                new AstNode('div', [], [$paragraph('Out of this stony rubbish?')]),
                new AstNode('blockquote', [], [$paragraph('Frisch weht der Wind')]),
                new AstNode('div', [], [$paragraph('You gave me hyacinths first')]),
                new AstNode('div', [], [$paragraph('They called me the hyacinth girl')]),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<p>What are the roots that clutch<br/>Out of this stony rubbish?</p>', $blocks);
        $t->contains('<!-- wp:quote -->', $blocks);
        $t->contains('<p>You gave me hyacinths first<br/>They called me the hyacinth girl</p>', $blocks);
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->same(1, substr_count($blocks, '<!-- wp:group -->'));
    },

    'renders large tables without generated accessibility geometry blowups' => static function (TestRunner $t) use ($tableCell, $tableRow): void {
        $rows = [];
        for ($index = 0; $index < 1002; $index++) {
            $rows[] = $tableRow([
                $tableCell('Name ' . $index, ['header' => $index === 0]),
                $tableCell('Value ' . $index),
            ]);
        }

        $document = new AstNode('document', [], [
            new AstNode('table', ['alignments' => ['left', 'left']], [
                new AstNode('table_body', [], $rows),
            ]),
        ]);

        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<table>', $blocks);
        $t->contains('Name 1001', $blocks);
        $t->same(false, str_contains($blocks, 'headers="pandoc-table-'), 'large generated tables should skip expensive generated headers');
    },
];
