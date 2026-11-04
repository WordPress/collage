<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\RtfReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

return [
    'reads rtf paragraphs and inline styles into pandoc ast' => static function (TestRunner $t) use ($plainText): void {
        $rtf = <<<'RTF'
{\rtf1\ansi{\fonttbl{\f0 Arial;}}\pard Plain {\b bold} and {\i italic} plus {\ul under}\ulnone.\par Second line\par}
RTF;

        $document = (new RtfReader())->read($rtf);
        $first = $document->children[0];

        $t->same('document', $document->type);
        $t->same('rtf', $document->attr('source'));
        $t->same(2, count($document->children));
        $t->same('paragraph', $first->type);
        $t->same('Plain bold and italic plus under.', $plainText($first));
        $t->same('strong', $first->children[1]->type);
        $t->same('bold', $plainText($first->children[1]));
        $t->same('emph', $first->children[3]->type);
        $t->same('italic', $plainText($first->children[3]));
        $t->same('underline', $first->children[5]->type);
        $t->same('under', $plainText($first->children[5]));
        $t->same('Second line', $plainText($document->children[1]));
    },
    'decodes rtf escaped braces hex bytes unicode fallback and tabs' => static function (TestRunner $t) use ($plainText): void {
        $rtf = <<<'RTF'
{\rtf1\ansi\pard Escaped \{brace\} caf\'e9 \uc1\u8212? dash\tab cell\par}
RTF;

        $text = $plainText((new RtfReader())->read($rtf));

        $t->contains('Escaped {brace}', $text);
        $t->contains('caf' . "\u{00E9}", $text);
        $t->contains("\u{2014} dash", $text);
        $t->contains("\t" . 'cell', $text);
        $t->true(!str_contains($text, '?'), 'Expected unicode fallback marker to be skipped');
    },
    'renders rtf reader output through wordpress blocks' => static function (TestRunner $t): void {
        $rtf = <<<'RTF'
{\rtf1\ansi\pard First {\b bold} and {\i italic} plus {\ul under}.\par Second {\strike removed}.\par}
RTF;

        $document = (new RtfReader())->read($rtf);
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('<p>First <strong>bold</strong> and <em>italic</em> plus <u>under</u>.</p>', $blocks);
        $t->contains('<p>Second <del>removed</del>.</p>', $blocks);
    },
    'treats pard as a paragraph boundary and line as an inline break' => static function (TestRunner $t) use ($plainText): void {
        $rtf = <<<'RTF'
{\rtf1\ansi Before\pard After\line Again\par}
RTF;

        $document = (new RtfReader())->read($rtf);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('Before', $plainText($document->children[0]));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $document->children[1]->children));
        $t->same("After\nAgain", $plainText($document->children[1]));
        $t->contains('<p>After<br/>Again</p>', $blocks);
    },
];
