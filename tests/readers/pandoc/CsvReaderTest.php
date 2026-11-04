<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$upstreamCsvCommandFixture = static function (): array {
    $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-current-csv-reader/csv.md');
    if (preg_match('/% pandoc -f csv -t native\n(?P<input>.*?)\n\^D\n(?P<native>\[ Table.*\n\])\n```/s', $fixture, $matches) !== 1) {
        throw new RuntimeException('Unable to parse checked-in upstream CSV command fixture');
    }

    return [
        'input' => $matches['input'] . "\n",
        'native' => $matches['native'],
    ];
};

$upstreamCsvMultilineCommandFixture = static function (): array {
    $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-current-csv-reader/9797.md');
    if (preg_match('/% pandoc -t native -f csv\n(?P<input>.*?)\n\^D\n(?P<native>\[ Table.*\n\])\n```/s', $fixture, $matches) !== 1) {
        throw new RuntimeException('Unable to parse checked-in upstream CSV multiline command fixture');
    }

    return [
        'input' => $matches['input'] . "\n",
        'native' => $matches['native'],
    ];
};

$nativeTokenStream = static function (string $native): string {
    $native = str_replace('\\12', '\\f', $native);
    $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
    $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

    return (string) preg_replace('/\s+/', ' ', trim($native));
};

return [
    'maps csv input into a native table ast with review evidence and exports' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv(implode("\n", [
            'source_id,title,published',
            '42,"Legacy, ""quoted"" title",true',
            "43,\"Two\nline title\",false",
            '',
        ]));
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $geometry = $table->attr('tableGeometry');
        $wordpress = (new WordPressBlockWriter())->write($document);
        $json = (new PandocJsonWriter())->toArray($document);

        $t->same('document', $document->type);
        $t->same('csv', $document->attr('sourceFormat'));
        $t->same('table', $table->type);
        $t->same('csv', $table->attr('sourceFormat'));
        $t->same(3, TableGeometry::columnCount($table));
        $t->same('csv', $packet['format'] ?? null);
        $t->same(',', $packet['delimiter'] ?? null);
        $t->same(3, $packet['rowCount'] ?? null);
        $t->same(2, $packet['bodyRowCount'] ?? null);
        $t->same(3, $packet['columnCount'] ?? null);
        $t->same(9, $packet['fieldCount'] ?? null);
        $t->same(0, $packet['raggedRowCount'] ?? null);
        $t->same('"', $packet['quote'] ?? null);
        $t->same(true, array_key_exists('escape', $packet));
        $t->same(null, $packet['escape']);
        $t->same('quoted-fields', $packet['dialect']['quoteMode'] ?? null);
        $t->same('none', $packet['dialect']['escapeMode'] ?? null);
        $t->same(2, $packet['quotedFieldCount'] ?? null);
        $t->same(2, $packet['doubledQuoteEscapeCount'] ?? null);
        $t->same(0, $packet['escapedQuoteSequenceCount'] ?? null);
        $t->same(1, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->same(0, $packet['partialRecordCount'] ?? null);
        $t->same(1, $packet['diagnosticCount'] ?? null);
        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same([
            'test/command/csv.md',
            'test/command/01.csv',
            'test/command/9797.md',
        ], $packet['upstreamEvidence']['fixtures'] ?? null);
        $t->same('Legacy, "quoted" title', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same("Two\nline title", $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same(3, $geometry['columnCount'] ?? null);
        $t->same('Legacy, "quoted" title', $geometry['coverage'][4]['text'] ?? null);
        $t->contains('<th>source_id</th><th>title</th><th>published</th>', $wordpress);
        $t->contains('<td>Legacy, &quot;quoted&quot; title</td>', $wordpress);
        $t->same('Table', $json['blocks'][0]['t'] ?? null);
    },
    'matches pinned upstream csv command reader semantics' => static function (TestRunner $t) use ($upstreamCsvCommandFixture, $nativeTokenStream): void {
        $fixture = $upstreamCsvCommandFixture();
        $document = (new DelimitedTextReader())->readCsv($fixture['input']);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');

        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->contains(DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $packet['upstreamEvidence']['source'] ?? '');
        $t->same(['Fruit', 'Price', 'Quantity'], $table->attr('columnNames'));
        $t->same('Apple', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('25 cents', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->same('"Navel" Orange', $table->children[1]->children[1]->children[0]->attr('text'));
        $t->same('35 cents', $table->children[1]->children[1]->children[1]->attr('text'));
        $t->same('', $table->children[1]->children[2]->children[0]->attr('text'));
        $t->same([], $table->children[1]->children[2]->children[0]->children);
        $t->same('', $table->children[1]->children[2]->children[1]->attr('text'));
        $t->same([], $table->children[1]->children[2]->children[1]->children);
        $t->same('45', $table->children[1]->children[2]->children[2]->attr('text'));
        $t->contains('Plain [ Str "\"Navel\"" , Space , Str "Orange" ]', $native);
        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) []', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches upstream csv multiline cell command fixture' => static function (TestRunner $t) use ($upstreamCsvMultilineCommandFixture, $nativeTokenStream): void {
        $fixture = $upstreamCsvMultilineCommandFixture();
        $document = (new DelimitedTextReader())->readCsv($fixture['input']);
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $native = PandocConverter::write($document, 'native');

        $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, $packet['upstreamEvidence']['denominator'] ?? null);
        $t->same(1, $packet['rowCount'] ?? null);
        $t->same(0, $packet['bodyRowCount'] ?? null);
        $t->same(1, $packet['columnCount'] ?? null);
        $t->same(5, $packet['quotedLineBreakCount'] ?? null);
        $t->same(1, $packet['multilineFieldCount'] ?? null);
        $t->contains('Plain [ Str "one_line_break:" , LineBreak , Str "four_line_breaks:" , LineBreak , LineBreak , LineBreak , LineBreak , Str "last_line" ]', $native);
        $t->same($nativeTokenStream($fixture['native']), $nativeTokenStream($native));
    },
    'matches upstream csv header width when body rows are wider or shorter' => static function (TestRunner $t): void {
        $document = (new DelimitedTextReader())->readCsv("a,b\n1,2,3\n4\n");
        $table = $document->children[0];
        $packet = $table->attr('delimitedText');
        $body = $table->children[1];
        $native = PandocConverter::write($document, 'native');

        $t->same(['a', 'b'], $table->attr('columnNames'));
        $t->same(2, TableGeometry::columnCount($table));
        $t->same(2, $packet['columnCount'] ?? null);
        $t->same(3, $packet['sourceMaxFieldCount'] ?? null);
        $t->same(['1', '2'], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $body->children[0]->children));
        $t->same(['4', ''], array_map(static fn (AstNode $cell): string => $cell->attr('text'), $body->children[1]->children));
        $t->same('pandoc-first-row-width', $packet['rowRepairSummary']['policy'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['truncatedRowCount'] ?? null);
        $t->same(1, $packet['rowRepairSummary']['paddedRowCount'] ?? null);
        $t->true(!str_contains($native, 'Str "3"'));
    },
];
