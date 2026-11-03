<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            continue;
        }

        $parts[] = trim($inlineText($child));
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$nodeSummary = static function (AstNode $node) use (&$nodeSummary, $inlineText, $listItemText): array {
    $summary = ['type' => $node->type];
    if ($node->type === 'text' || $node->type === 'paragraph') {
        $summary['text'] = trim($inlineText($node));
    } elseif ($node->type === 'heading') {
        $summary['level'] = $node->attr('level');
        $summary['text'] = (string) $node->attr('text', '');
    } elseif ($node->type === 'code_block') {
        $summary['text'] = (string) $node->attr('text', '');
        $classes = $node->attr('classes', []);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }
    } elseif ($node->type === 'blockquote') {
        $summary['text'] = trim($inlineText($node));
    } elseif ($node->type === 'raw_html') {
        $summary['html'] = (string) $node->attr('html', '');
    } elseif ($node->type === 'div') {
        $summary['text'] = trim($inlineText($node));
        $classes = $node->attr('classes', []);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }
    } elseif ($node->type === 'line_block') {
        $summary['lines'] = array_map(
            static fn (AstNode $line): string => (string) $line->attr('text', ''),
            $node->children
        );
    } elseif ($node->type === 'definition_list') {
        $items = [];
        foreach ($node->children as $item) {
            $term = (string) $item->attr('term', '');
            $definitions = [];
            foreach ($item->children as $child) {
                if ($child->type === 'definition') {
                    $definitions[] = trim($inlineText($child));
                }
            }
            $items[] = ['term' => $term, 'definitions' => $definitions];
        }
        $summary['items'] = $items;
    } elseif ($node->type === 'bullet_list' || $node->type === 'ordered_list') {
        $summary['items'] = array_map($listItemText, $node->children);
        if ($node->type === 'ordered_list') {
            $summary['start'] = $node->attr('start');
            $summary['style'] = $node->attr('style');
            $summary['delimiter'] = $node->attr('delimiter');
        }
    } elseif ($node->type === 'table') {
        $summary['alignments'] = $node->attr('alignments', []);
    }

    return $summary;
};

$cases = [
    '01 dash bullet interrupts paragraph' => [
        'markdown' => "Lead\n- dash item",
        'listType' => 'bullet_list',
        'items' => ['dash item'],
    ],
    '02 plus bullet interrupts paragraph' => [
        'markdown' => "Lead\n+ plus item",
        'listType' => 'bullet_list',
        'items' => ['plus item'],
    ],
    '03 asterisk bullet interrupts paragraph' => [
        'markdown' => "Lead\n* asterisk item",
        'listType' => 'bullet_list',
        'items' => ['asterisk item'],
    ],
    '04 one-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n - one-space item",
        'listType' => 'bullet_list',
        'items' => ['one-space item'],
    ],
    '05 two-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n  - two-space item",
        'listType' => 'bullet_list',
        'items' => ['two-space item'],
    ],
    '06 three-space indented bullet interrupts paragraph' => [
        'markdown' => "Lead\n   - three-space item",
        'listType' => 'bullet_list',
        'items' => ['three-space item'],
    ],
    '07 tab-padded plus bullet interrupts paragraph' => [
        'markdown' => "Lead\n+\ttab padded item",
        'listType' => 'bullet_list',
        'items' => ['tab padded item'],
    ],
    '08 spaced asterisk bullet interrupts paragraph' => [
        'markdown' => "Lead\n*    spaced item",
        'listType' => 'bullet_list',
        'items' => ['spaced item'],
    ],
    '09 unchecked task bullet interrupts paragraph' => [
        'markdown' => "Lead\n- [ ] unchecked task",
        'listType' => 'bullet_list',
        'items' => ['unchecked task'],
        'tasks' => [false],
        'taskList' => true,
    ],
    '10 checked task bullet interrupts paragraph' => [
        'markdown' => "Lead\n- [X] checked task",
        'listType' => 'bullet_list',
        'items' => ['checked task'],
        'tasks' => [true],
        'taskList' => true,
    ],
    '11 empty task marker interrupts paragraph' => [
        'markdown' => "Lead\n- [ ]",
        'listType' => 'bullet_list',
        'items' => [''],
        'tasks' => [false],
        'taskList' => true,
    ],
    '12 bullet lazy continuation remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  continuation",
        'listType' => 'bullet_list',
        'items' => ['item continuation'],
    ],
    '13 bullet two lazy continuations remain inside interrupted list' => [
        'markdown' => "Lead\n- item\n  continuation\n  finish",
        'listType' => 'bullet_list',
        'items' => ['item continuation finish'],
    ],
    '14 bullet loose continuation remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n\n  second paragraph",
        'listType' => 'bullet_list',
        'items' => ['item second paragraph'],
        'loose' => true,
    ],
    '15 bullet nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  - nested",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested']],
    ],
    '16 bullet four-space nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n    - nested four",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested four']],
    ],
    '17 bullet nested ordered list remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  1. nested ordered",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
    ],
    '18 bullet nested task list remains inside interrupted list' => [
        'markdown' => "Lead\n- item\n  - [x] nested task",
        'listType' => 'bullet_list',
        'items' => ['item'],
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested task'], 'tasks' => [true], 'taskList' => true],
    ],
    '19 bullet second item stays in interrupted list' => [
        'markdown' => "Lead\n- first\n- second",
        'listType' => 'bullet_list',
        'items' => ['first', 'second'],
    ],
    '20 blank between bullet items makes interrupted list loose' => [
        'markdown' => "Lead\n- first\n\n- second",
        'listType' => 'bullet_list',
        'items' => ['first', 'second'],
        'loose' => true,
    ],
    '21 paragraph after interrupted bullet remains separate' => [
        'markdown' => "Lead\n- item\n\nAfter",
        'blocks' => ['paragraph', 'bullet_list', 'paragraph'],
        'listType' => 'bullet_list',
        'items' => ['item'],
        'after' => 'After',
    ],
    '22 thematic break after interrupted bullet remains separate' => [
        'markdown' => "Lead\n- item\n---",
        'blocks' => ['paragraph', 'bullet_list', 'horizontal_rule'],
        'listType' => 'bullet_list',
        'items' => ['item'],
    ],
    '23 strong inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- **strong** item",
        'listType' => 'bullet_list',
        'items' => ['strong item'],
    ],
    '24 code inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- `code` item",
        'listType' => 'bullet_list',
        'items' => ['code item'],
    ],
    '25 link inline survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- [linked](/target) item",
        'listType' => 'bullet_list',
        'items' => ['linked item'],
    ],
    '26 escaped punctuation survives paragraph-interrupting bullet' => [
        'markdown' => "Lead\n- escaped \\* item",
        'listType' => 'bullet_list',
        'items' => ['escaped * item'],
    ],
    '27 decimal ordered period interrupts paragraph from one' => [
        'markdown' => "Lead\n1. decimal item",
        'listType' => 'ordered_list',
        'items' => ['decimal item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '28 decimal ordered paren interrupts paragraph from one' => [
        'markdown' => "Lead\n1) paren item",
        'listType' => 'ordered_list',
        'items' => ['paren item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '29 zero-padded ordered period interrupts paragraph from one' => [
        'markdown' => "Lead\n01. zero padded",
        'listType' => 'ordered_list',
        'items' => ['zero padded'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '30 zero-padded ordered paren interrupts paragraph from one' => [
        'markdown' => "Lead\n001) zero padded paren",
        'listType' => 'ordered_list',
        'items' => ['zero padded paren'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '31 two-space indented ordered period interrupts paragraph' => [
        'markdown' => "Lead\n  1. indented ordered",
        'listType' => 'ordered_list',
        'items' => ['indented ordered'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '32 three-space indented ordered paren interrupts paragraph' => [
        'markdown' => "Lead\n   1) indented ordered paren",
        'listType' => 'ordered_list',
        'items' => ['indented ordered paren'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '33 tab-padded ordered period interrupts paragraph' => [
        'markdown' => "Lead\n1.\ttab ordered",
        'listType' => 'ordered_list',
        'items' => ['tab ordered'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '34 ordered task item interrupts paragraph' => [
        'markdown' => "Lead\n1. [ ] ordered task",
        'listType' => 'ordered_list',
        'items' => ['ordered task'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'tasks' => [false],
    ],
    '35 ordered continuation remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   continuation",
        'listType' => 'ordered_list',
        'items' => ['item continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '36 ordered nested bullet remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   - nested bullet",
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'nested' => ['parent' => 0, 'type' => 'bullet_list', 'items' => ['nested bullet']],
    ],
    '37 ordered nested ordered remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n   1. nested ordered",
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'nested' => ['parent' => 0, 'type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
    ],
    '38 ordered second item stays in interrupted list' => [
        'markdown' => "Lead\n1. first\n2. second",
        'listType' => 'ordered_list',
        'items' => ['first', 'second'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '39 blank between ordered items makes interrupted list loose' => [
        'markdown' => "Lead\n1. first\n\n2. second",
        'listType' => 'ordered_list',
        'items' => ['first', 'second'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'loose' => true,
    ],
    '40 ordered loose continuation remains inside interrupted list' => [
        'markdown' => "Lead\n1. item\n\n   second paragraph",
        'listType' => 'ordered_list',
        'items' => ['item second paragraph'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'loose' => true,
    ],
    '41 paragraph after interrupted ordered list remains separate' => [
        'markdown' => "Lead\n1. item\n\nAfter",
        'blocks' => ['paragraph', 'ordered_list', 'paragraph'],
        'listType' => 'ordered_list',
        'items' => ['item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'after' => 'After',
    ],
    '42 pandoc default ordered period interrupts paragraph' => [
        'markdown' => "Lead\n#. default ordered",
        'listType' => 'ordered_list',
        'items' => ['default ordered'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '43 pandoc default ordered paren interrupts paragraph' => [
        'markdown' => "Lead\n#) default ordered paren",
        'listType' => 'ordered_list',
        'items' => ['default ordered paren'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '44 pandoc numbered example interrupts paragraph' => [
        'markdown' => "Lead\n(@) example item",
        'listType' => 'ordered_list',
        'items' => ['example item'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '45 pandoc labeled numbered example interrupts paragraph' => [
        'markdown' => "Lead\n(@review) labeled example",
        'listType' => 'ordered_list',
        'items' => ['labeled example'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '46 pandoc upper-alpha ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\nA.  alpha item",
        'listType' => 'ordered_list',
        'items' => ['alpha item'],
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
    ],
    '47 pandoc lower-alpha ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\na)  lower alpha item",
        'listType' => 'ordered_list',
        'items' => ['lower alpha item'],
        'start' => 1,
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
    ],
    '48 pandoc upper-roman ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\nI.  roman item",
        'listType' => 'ordered_list',
        'items' => ['roman item'],
        'start' => 1,
        'style' => 'upper_roman',
        'delimiter' => 'period',
    ],
    '49 pandoc lower-roman ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\ni.  lower roman item",
        'listType' => 'ordered_list',
        'items' => ['lower roman item'],
        'start' => 1,
        'style' => 'lower_roman',
        'delimiter' => 'period',
    ],
    '50 pandoc parenthesized ordered marker interrupts paragraph from one' => [
        'markdown' => "Lead\n(1) parenthesized item",
        'listType' => 'ordered_list',
        'items' => ['parenthesized item'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader block/list paragraph interruption surge ' . $name] = static function (TestRunner $t) use ($case, $listItemText): void {
        $document = (new MarkdownReader(['format' => 'markdown+lists_without_preceding_blankline']))->read($case['markdown']);
        $blockTypes = array_map(static fn (AstNode $node): string => $node->type, $document->children);
        $expectedBlockTypes = $case['blocks'] ?? ['paragraph', $case['listType']];

        $t->same($expectedBlockTypes, $blockTypes);
        $t->same('Lead', $document->children[0]->attr('text'));

        $list = $document->children[1];
        $t->same($case['listType'], $list->type);
        $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'));
        $t->same($case['items'], array_map($listItemText, $list->children));

        if (isset($case['start'])) {
            $t->same($case['start'], $list->attr('start'));
        }
        if (isset($case['style'])) {
            $t->same($case['style'], $list->attr('style'));
        }
        if (isset($case['delimiter'])) {
            $t->same($case['delimiter'], $list->attr('delimiter'));
        }
        if (isset($case['taskList'])) {
            $t->same($case['taskList'], (bool) $list->attr('taskList'));
        }
        if (isset($case['tasks'])) {
            $t->same($case['tasks'], array_map(
                static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                $list->children
            ));
        }
        if (isset($case['nested'])) {
            $nestedCase = $case['nested'];
            $parent = $list->children[$nestedCase['parent']];
            $nested = null;
            foreach ($parent->children as $child) {
                if ($child->type === $nestedCase['type']) {
                    $nested = $child;
                    break;
                }
            }

            $t->true($nested instanceof AstNode, 'Expected nested list to remain inside interrupted list item');
            if ($nested instanceof AstNode) {
                $t->same($nestedCase['type'], $nested->type);
                $t->same($nestedCase['items'], array_map($listItemText, $nested->children));
                if (isset($nestedCase['start'])) {
                    $t->same($nestedCase['start'], $nested->attr('start'));
                }
                if (isset($nestedCase['taskList'])) {
                    $t->same($nestedCase['taskList'], (bool) $nested->attr('taskList'));
                }
                if (isset($nestedCase['tasks'])) {
                    $t->same($nestedCase['tasks'], array_map(
                        static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                        $nested->children
                    ));
                }
            }
        }
        if (isset($case['after'])) {
            $t->same($case['after'], $document->children[2]->attr('text'));
        }
    };
}

$nonInterruptingOrderedCases = [
    'ordered marker starting from two' => ["Lead\n2. not a paragraph-interrupting list", 'Lead 2. not a paragraph-interrupting list'],
    'year-like ordered marker' => ["Lead\n1986. still paragraph text", 'Lead 1986. still paragraph text'],
];

foreach ($nonInterruptingOrderedCases as $name => [$markdown, $expectedText]) {
    $tests['keeps upstream markdown reader non-one ordered marker in paragraph ' . $name] =
        static function (TestRunner $t) use ($markdown, $expectedText, $inlineText): void {
            $document = (new MarkdownReader())->read($markdown);

            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same($expectedText, trim($inlineText($document->children[0])));
        };
}

$blockText = static function (AstNode $node) use (&$blockText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }
    if ($node->type === 'raw_html') {
        return (string) $node->attr('html', '');
    }
    if ($node->type === 'raw_block') {
        return (string) $node->attr('text', '');
    }
    if (($node->children ?? []) === [] && is_scalar($node->attr('text', null))) {
        return (string) $node->attr('text');
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $blockText($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

$continuationCases = [
    '01 bullet blockquote continuation' => [
        'markdown' => "- item\n  > quoted review",
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'quoted review',
    ],
    '02 ordered blockquote continuation' => [
        'markdown' => "1. item\n   > ordered quote",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'ordered quote',
    ],
    '03 task blockquote continuation' => [
        'markdown' => "- [x] item\n  > task quote",
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'task quote',
        'taskChecked' => true,
        'taskList' => true,
    ],
    '04 plus blockquote nested bullet continuation' => [
        'format' => 'markdown+lists_without_preceding_blankline',
        'markdown' => "+ item\n  > quote\n  > - nested",
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'quotenested',
    ],
    '05 ordered paren blockquote nested ordered continuation' => [
        'format' => 'markdown+lists_without_preceding_blankline',
        'markdown' => "1) item\n   > quote\n   > 1. nested",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'quotenested',
    ],
    '06 parenthesized marker blockquote continuation' => [
        'markdown' => "(1) item\n    > parenthesized quote",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'parenthesized quote',
    ],
    '07 blockquote continuation preserves sibling item' => [
        'markdown' => "- item\n  > quote\n- next",
        'childTypes' => ['text', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'quote',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '08 blank blockquote continuation makes loose list item' => [
        'markdown' => "- item\n\n  > loose quote",
        'childTypes' => ['paragraph', 'blockquote'],
        'blockType' => 'blockquote',
        'blockText' => 'loose quote',
        'loose' => true,
    ],
    '09 bullet fenced code continuation' => [
        'markdown' => "- item\n  ```\n  code\n  ```",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'code',
    ],
    '10 bullet tilde fenced code continuation' => [
        'markdown' => "- item\n  ~~~\n  tilde\n  ~~~",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'tilde',
    ],
    '11 ordered fenced code class continuation' => [
        'markdown' => "1. item\n   ``` php\n   echo 1;\n   ```",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'echo 1;',
        'blockAttrs' => ['classes' => ['php']],
    ],
    '12 task fenced code continuation' => [
        'markdown' => "- [ ] item\n  ```\n  task code\n  ```",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'task code',
        'taskChecked' => false,
        'taskList' => true,
    ],
    '13 fenced code continuation preserves sibling item' => [
        'markdown' => "- item\n  ```\n  code\n  ```\n- next",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'code',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '14 indented marker fenced code continuation' => [
        'markdown' => "  - item\n    ```\n    indented marker code\n    ```",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'indented marker code',
    ],
    '15 raw attribute fenced code continuation' => [
        'markdown' => "- item\n  ```{=html}\n  <span>raw</span>\n  ```",
        'childTypes' => ['text', 'raw_block'],
        'blockType' => 'raw_block',
        'blockText' => '<span>raw</span>',
        'blockAttrs' => ['format' => 'html'],
    ],
    '16 bullet indented code continuation' => [
        'markdown' => "- item\n      echo alpha",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'echo alpha',
    ],
    '17 ordered indented code continuation' => [
        'markdown' => "1. item\n       echo beta",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'echo beta',
    ],
    '18 task indented code continuation' => [
        'markdown' => "- [ ] item\n      echo task",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'echo task',
        'taskChecked' => false,
        'taskList' => true,
    ],
    '19 multi-line indented code continuation' => [
        'markdown' => "+ item\n      alpha\n      beta",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => "alpha\nbeta",
    ],
    '20 indented code continuation preserves sibling item' => [
        'markdown' => "- item\n      code\n- next",
        'childTypes' => ['text', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'code',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '21 blank indented code continuation makes loose item' => [
        'markdown' => "- item\n\n      loose code",
        'childTypes' => ['paragraph', 'code_block'],
        'blockType' => 'code_block',
        'blockText' => 'loose code',
        'loose' => true,
    ],
    '22 bullet thematic break continuation' => [
        'markdown' => "- item\n  ***",
        'childTypes' => ['text', 'horizontal_rule'],
        'blockType' => 'horizontal_rule',
    ],
    '23 ordered thematic break continuation' => [
        'markdown' => "1. item\n   ***",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'horizontal_rule'],
        'blockType' => 'horizontal_rule',
    ],
    '24 indented marker thematic break continuation' => [
        'markdown' => "  - item\n    ___",
        'childTypes' => ['text', 'horizontal_rule'],
        'blockType' => 'horizontal_rule',
    ],
    '25 thematic break continuation preserves sibling item' => [
        'markdown' => "- item\n  ***\n- next",
        'childTypes' => ['text', 'horizontal_rule'],
        'blockType' => 'horizontal_rule',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '26 task thematic break continuation' => [
        'markdown' => "- [x] item\n  * * *",
        'childTypes' => ['text', 'horizontal_rule'],
        'blockType' => 'horizontal_rule',
        'taskChecked' => true,
        'taskList' => true,
    ],
    '27 bullet atx heading continuation' => [
        'markdown' => "- item\n  # Child heading",
        'childTypes' => ['text', 'heading'],
        'blockType' => 'heading',
        'blockText' => 'Child heading',
        'blockAttrs' => ['level' => 1],
    ],
    '28 ordered attributed heading continuation' => [
        'markdown' => "1. item\n   ## Review {#child .queue}",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'heading'],
        'blockType' => 'heading',
        'blockText' => 'Review',
        'blockAttrs' => ['level' => 2, 'id' => 'child', 'classes' => ['queue']],
    ],
    '29 plus empty heading continuation' => [
        'markdown' => "+ item\n  #",
        'childTypes' => ['text', 'heading'],
        'blockType' => 'heading',
        'blockText' => '',
        'blockAttrs' => ['level' => 1],
    ],
    '30 parenthesized marker heading continuation' => [
        'markdown' => "(1) item\n    ### Parent child",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'heading'],
        'blockType' => 'heading',
        'blockText' => 'Parent child',
        'blockAttrs' => ['level' => 3],
    ],
    '31 heading continuation preserves sibling item' => [
        'markdown' => "- item\n  ## Child\n- next",
        'childTypes' => ['text', 'heading'],
        'blockType' => 'heading',
        'blockText' => 'Child',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '32 blank heading continuation makes loose item' => [
        'markdown' => "- item\n\n  ## Loose child",
        'childTypes' => ['paragraph', 'heading'],
        'blockType' => 'heading',
        'blockText' => 'Loose child',
        'loose' => true,
    ],
    '33 section raw html continuation' => [
        'markdown' => "- item\n  <section>\n  html\n  </section>",
        'childTypes' => ['text', 'raw_html'],
        'blockType' => 'raw_html',
        'blockText' => "<section>\nhtml\n</section>",
    ],
    '34 div raw html continuation' => [
        'markdown' => "- item\n  <div class=\"note\">\n  body\n  </div>",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
    ],
    '35 html comment continuation' => [
        'markdown' => "- item\n  <!-- review -->",
        'childTypes' => ['text', 'raw_html'],
        'blockType' => 'raw_html',
        'blockText' => '<!-- review -->',
    ],
    '36 pre raw html continuation' => [
        'markdown' => "- item\n  <pre>\n  code\n  </pre>",
        'childTypes' => ['text', 'raw_html'],
        'blockType' => 'raw_html',
        'blockText' => "<pre>\ncode\n</pre>",
    ],
    '37 ordered raw html continuation' => [
        'markdown' => "1. item\n   <aside>\n   note\n   </aside>",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'raw_html'],
        'blockType' => 'raw_html',
        'blockText' => "<aside>\nnote\n</aside>",
    ],
    '38 raw html continuation preserves sibling item' => [
        'markdown' => "- item\n  <section>one</section>\n- next",
        'childTypes' => ['text', 'raw_html'],
        'blockType' => 'raw_html',
        'blockText' => '<section>one</section>',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '39 bullet line block continuation' => [
        'markdown' => "- item\n  | one",
        'childTypes' => ['text', 'line_block'],
        'blockType' => 'line_block',
        'blockText' => 'one',
    ],
    '40 multi-line line block continuation' => [
        'markdown' => "- item\n  | one\n  | two",
        'childTypes' => ['text', 'line_block'],
        'blockType' => 'line_block',
        'blockText' => 'onetwo',
    ],
    '41 ordered line block continuation' => [
        'markdown' => "1. item\n   | ordered line",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'line_block'],
        'blockType' => 'line_block',
        'blockText' => 'ordered line',
    ],
    '42 task line block continuation' => [
        'markdown' => "- [ ] item\n  | task line",
        'childTypes' => ['text', 'line_block'],
        'blockType' => 'line_block',
        'blockText' => 'task line',
        'taskChecked' => false,
        'taskList' => true,
    ],
    '43 line block continuation preserves sibling item' => [
        'markdown' => "- item\n  | line\n- next",
        'childTypes' => ['text', 'line_block'],
        'blockType' => 'line_block',
        'blockText' => 'line',
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '44 simple fenced div continuation' => [
        'markdown' => "- item\n  :::\n  body\n  :::",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
    ],
    '45 classed fenced div continuation' => [
        'markdown' => "- item\n  ::: {.review}\n  body\n  :::",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
        'blockAttrs' => ['classes' => ['review']],
    ],
    '46 fenced div with nested list continuation' => [
        'markdown' => "- item\n  ::: {.review}\n  - nested\n  :::",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'nested',
        'blockAttrs' => ['classes' => ['review']],
    ],
    '47 ordered fenced div continuation' => [
        'markdown' => "1. item\n   ::: {.ordered}\n   body\n   :::",
        'listType' => 'ordered_list',
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
        'blockAttrs' => ['classes' => ['ordered']],
    ],
    '48 task fenced div continuation' => [
        'markdown' => "- [x] item\n  ::: {.task}\n  body\n  :::",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
        'blockAttrs' => ['classes' => ['task']],
        'taskChecked' => true,
        'taskList' => true,
    ],
    '49 fenced div continuation preserves sibling item' => [
        'markdown' => "- item\n  ::: {.one}\n  body\n  :::\n- next",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body',
        'blockAttrs' => ['classes' => ['one']],
        'itemCount' => 2,
        'siblingText' => 'next',
    ],
    '50 long fenced div continuation' => [
        'markdown' => "- item\n  :::: {.long}\n  body ::: marker\n  ::::",
        'childTypes' => ['text', 'div'],
        'blockType' => 'div',
        'blockText' => 'body ::: marker',
        'blockAttrs' => ['classes' => ['long']],
    ],
];

foreach ($continuationCases as $name => $case) {
    $tests['maps upstream markdown reader block/list continuation completion surge ' . $name] =
        static function (TestRunner $t) use ($case, $blockText, $name): void {
            $options = isset($case['format']) ? ['format' => $case['format']] : [];
            $document = (new MarkdownReader($options))->read($case['markdown']);
            $list = $document->children[0] ?? new AstNode('missing');
            $item = $list->children[0] ?? new AstNode('missing');
            $block = null;
            foreach ($item->children as $child) {
                if ($child->type === $case['blockType']) {
                    $block = $child;
                    break;
                }
            }

            $t->same($case['listType'] ?? 'bullet_list', $list->type, $name);
            $t->same($case['childTypes'], array_map(static fn (AstNode $node): string => $node->type, $item->children), $name);
            $t->true($block instanceof AstNode, 'Expected continuation block for ' . $name);
            if ($block instanceof AstNode) {
                $t->same($case['blockType'], $block->type, $name);
                if (array_key_exists('blockText', $case)) {
                    $t->same($case['blockText'], $blockText($block), $name);
                }
                foreach (($case['blockAttrs'] ?? []) as $attr => $expected) {
                    $t->same($expected, $block->attr($attr), $name . ' attr ' . $attr);
                }
            }

            if (isset($case['taskChecked'])) {
                $t->same($case['taskChecked'], $item->attr('taskChecked', null), $name);
            }
            if (isset($case['taskList'])) {
                $t->same($case['taskList'], (bool) $list->attr('taskList'), $name);
            }
            if (isset($case['loose'])) {
                $t->same($case['loose'], (bool) $list->attr('loose'), $name);
                $t->same($case['loose'], (bool) $item->attr('loose'), $name);
            }
            if (isset($case['itemCount'])) {
                $t->same($case['itemCount'], count($list->children), $name);
            }
            if (isset($case['siblingText'])) {
                $sibling = $list->children[1] ?? new AstNode('missing');
                $t->same($case['siblingText'], trim($blockText($sibling)), $name);
            }
        };
}

$tests['records upstream markdown reader block/list continuation completion mapped-case count'] =
    static function (TestRunner $t) use ($continuationCases): void {
        $t->same(50, count($continuationCases));
    };

$makeTableCaptionSurgeTable = static function (string $syntax, int $tableCaptionSurgeCaseNumber): string {
    $rowLabel = 'A' . str_pad((string) $tableCaptionSurgeCaseNumber, 3, '0', STR_PAD_LEFT);

    return match ($syntax) {
        'pipe' => implode("\n", [
            '| Term | Count |',
            '|:-----|------:|',
            '| ' . $rowLabel . ' | ' . $tableCaptionSurgeCaseNumber . ' |',
        ]),
        'grid' => implode("\n", [
            '+----------+-------+',
            '| Term     | Count |',
            '+==========+=======+',
            '| ' . str_pad($rowLabel, 8) . ' | ' . str_pad((string) $tableCaptionSurgeCaseNumber, 5) . ' |',
            '+----------+-------+',
        ]),
        'simple' => implode("\n", [
            sprintf('%-12s  %5s', 'Term', 'Count'),
            '------------  -----',
            sprintf('%-12s  %5s', $rowLabel, (string) $tableCaptionSurgeCaseNumber),
        ]),
    };
};

$makeTableCaptionSurgeMarkdown = static function (array $case) use ($makeTableCaptionSurgeTable): string {
    $caption = $case['marker'] . ' [' . $case['shortCaption'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .surge .' . $case['caseClass']
        . ' ' . $case['attributeSource'] . '}';
    $table = $makeTableCaptionSurgeTable($case['syntax'], $case['number']);

    return $case['position'] === 'leading'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$tableCaptionSurgeCases = [];
$tableCaptionSurgeCaseNumber = 1;
foreach (['pipe', 'grid', 'simple'] as $syntax) {
    foreach (['trailing', 'leading'] as $position) {
        foreach ([':', 'Table:', 'Caption:'] as $marker) {
            for ($variant = 1; $variant <= 3; $variant++) {
                $caseId = str_pad((string) $tableCaptionSurgeCaseNumber, 3, '0', STR_PAD_LEFT);
                $attributeSet = match ($variant) {
                    1 => [
                        'source' => 'data-source="upstream-' . $caseId . '" lang="en"',
                        'attributes' => ['data-source' => 'upstream-' . $caseId, 'lang' => 'en'],
                        'html' => 'data-source="upstream-' . $caseId . '" lang="en"',
                    ],
                    2 => [
                        'source' => 'role="table" title="Review table ' . $caseId . '"',
                        'attributes' => ['role' => 'table', 'title' => 'Review table ' . $caseId],
                        'html' => 'role="table" title="Review table ' . $caseId . '"',
                    ],
                    default => [
                        'source' => 'aria-label="Review table ' . $caseId . '" dir="ltr"',
                        'attributes' => ['aria-label' => 'Review table ' . $caseId, 'dir' => 'ltr'],
                        'html' => 'aria-label="Review table ' . $caseId . '" dir="ltr"',
                    ],
                };
                $tableCaptionSurgeCases[] = [
                    'number' => $tableCaptionSurgeCaseNumber,
                    'id' => 'md-table-caption-surge-' . $caseId,
                    'caseClass' => 'case-' . $caseId,
                    'attributeSource' => $attributeSet['source'],
                    'attributes' => $attributeSet['attributes'],
                    'htmlAttributeFragment' => $attributeSet['html'],
                    'syntax' => $syntax,
                    'position' => $position,
                    'marker' => $marker,
                    'caption' => 'Review *caption* ' . $caseId,
                    'shortCaption' => 'Queue ' . $caseId,
                    'rowLabel' => 'A' . $caseId,
                    'name' => sprintf(
                        'maps upstream markdown table caption surge case %s %s %s %s',
                        $caseId,
                        $syntax,
                        $position,
                        strtolower(rtrim($marker, ':')) ?: 'colon'
                    ),
                ];
                $tableCaptionSurgeCaseNumber++;
            }
        }
    }
}

foreach ($tableCaptionSurgeCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $makeTableCaptionSurgeMarkdown): void {
        $document = (new MarkdownReader())->read($makeTableCaptionSurgeMarkdown($case));
        $table = $document->children[0] ?? new AstNode('missing');
        $captionInlines = $table->attr('captionInlines', []);
        $shortCaptionInlines = $table->attr('shortCaptionInlines', []);
        $attributes = $table->attr('attributes', []);
        $htmlAttributes = $table->attr('htmlAttributes', []);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('table', $table->type);
        $t->same($case['caption'], $table->attr('caption'));
        $t->same($case['shortCaption'], $table->attr('shortCaption'));
        $t->same($case['id'], $table->attr('id'));
        $t->same(['surge', $case['caseClass']], $table->attr('classes'));
        $t->same($case['attributes'], $attributes);
        $t->same($case['id'], $htmlAttributes['id'] ?? null);
        $t->same('surge ' . $case['caseClass'], $htmlAttributes['class'] ?? null);
        $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same('caption', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same($case['shortCaption'], $shortCaptionInlines[0]->attr('text'));
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same($case['rowLabel'], $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same((string) $case['number'], $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains(
            '<figure class="wp-block-table" data-pandoc-short-caption="' . $case['shortCaption'] . '">',
            $blocks
        );
        $t->contains(
            '<table id="' . $case['id'] . '" class="surge ' . $case['caseClass']
                . '" ' . $case['htmlAttributeFragment'] . '>',
            $blocks
        );
        $t->contains(
            '<figcaption id="' . $case['id'] . '" class="wp-element-caption surge ' . $case['caseClass']
                . '" ' . $case['htmlAttributeFragment'] . '>Review <em>caption</em> '
                . str_pad((string) $case['number'], 3, '0', STR_PAD_LEFT)
                . '</figcaption>',
            $blocks
        );
    };
}


$read = static fn (string $markdown): AstNode => (new MarkdownReader())->read($markdown);
$readCommonmark = static fn (string $markdown): AstNode => (new MarkdownReader(['format' => 'commonmark']))->read($markdown);
$types = static fn (AstNode $node): array => array_map(static fn (AstNode $child): string => $child->type, $node->children);
$firstItem = static function (TestRunner $t, AstNode $document, string $listType): AstNode {
    $list = $document->children[0] ?? new AstNode('missing');
    $t->same($listType, $list->type);
    $t->same(2, count($list->children));
    $next = $list->children[1] ?? new AstNode('missing');
    $t->same('next', $next->attr('text'));

    return $list->children[0] ?? new AstNode('missing');
};

$markers = [
    'dash bullet' => ['marker' => '- ', 'next' => '- next', 'indent' => '  ', 'list' => 'bullet_list'],
    'plus bullet' => ['marker' => '+ ', 'next' => '+ next', 'indent' => '  ', 'list' => 'bullet_list'],
    'star bullet' => ['marker' => '* ', 'next' => '* next', 'indent' => '  ', 'list' => 'bullet_list'],
    'decimal period' => ['marker' => '1. ', 'next' => '2. next', 'indent' => '   ', 'list' => 'ordered_list'],
    'decimal paren' => ['marker' => '1) ', 'next' => '2) next', 'indent' => '   ', 'list' => 'ordered_list'],
    'two paren decimal' => ['marker' => '(1) ', 'next' => '(2) next', 'indent' => '    ', 'list' => 'ordered_list'],
    'upper alpha period' => ['marker' => 'A.  ', 'next' => 'B.  next', 'indent' => '    ', 'list' => 'ordered_list'],
    'lower alpha paren' => ['marker' => 'a)  ', 'next' => 'b)  next', 'indent' => '    ', 'list' => 'ordered_list'],
    'upper roman period' => ['marker' => 'IV.  ', 'next' => 'V.  next', 'indent' => '     ', 'list' => 'ordered_list'],
    'default ordered' => ['marker' => '#. ', 'next' => '#. next', 'indent' => '   ', 'list' => 'ordered_list'],
];

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list surge {$label} continuation blockquote"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '> quoted' . "\n" . $case['indent'] . '> tail' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $quote = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'blockquote'], $types($item));
        $t->same('blockquote', $quote->type);
        $t->same('quoted tail', $quote->children[0]->attr('text'));
    };

    $tests["maps commonmark block list surge {$label} continuation fenced code"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '``` php' . "\n" . $case['indent'] . 'echo 1;' . "\n" . $case['indent'] . '```' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $code = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'code_block'], $types($item));
        $t->same(['php'], $code->attr('classes'));
        $t->same('echo 1;', $code->attr('text'));
    };

    $tests["maps commonmark block list surge {$label} first text blockquote"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . '> quoted' . "\n" . $case['indent'] . '> tail' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $quote = $item->children[0] ?? new AstNode('missing');

        $t->same(['blockquote'], $types($item));
        $t->same('quoted tail', $quote->children[0]->attr('text'));
    };

    $tests["maps commonmark block list surge {$label} continuation heading"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '# Nested heading' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $heading = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'heading'], $types($item));
        $t->same(1, $heading->attr('level'));
        $t->same('Nested heading', $heading->attr('text'));
    };

    $tests["maps commonmark block list surge {$label} continuation indented code"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '    code' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $code = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'code_block'], $types($item));
        $t->same('code', $code->attr('text'));
    };

    $tests["maps commonmark block list surge {$label} continuation horizontal rule"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '***' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);

        $t->same(['text', 'horizontal_rule'], $types($item));
    };

    $tests["maps commonmark block list table surge {$label} continuation pipe table"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . '| Term | Count |'
            . "\n" . $case['indent'] . '|:-----|------:|'
            . "\n" . $case['indent'] . '| A001 | 7 |'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $table = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'table'], $types($item));
        $t->same('table', $table->type);
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('A001', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('7', $table->children[1]->children[0]->children[1]->attr('text'));
    };

    $tests["maps commonmark block list table surge {$label} continuation grid table"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . '+-------+-------+'
            . "\n" . $case['indent'] . '| Term  | Count |'
            . "\n" . $case['indent'] . '+=======+=======+'
            . "\n" . $case['indent'] . '| A002  | 8     |'
            . "\n" . $case['indent'] . '+-------+-------+'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $table = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'table'], $types($item));
        $t->same('table', $table->type);
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('A002', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('8', $table->children[1]->children[0]->children[1]->attr('text'));
    };

    $tests["maps commonmark block list table surge {$label} continuation simple table"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . 'Term          Count'
            . "\n" . $case['indent'] . '------------  -----'
            . "\n" . $case['indent'] . 'A003              9'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $table = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'table'], $types($item));
        $t->same('table', $table->type);
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('A003', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('9', $table->children[1]->children[0]->children[1]->attr('text'));
    };

    $tests["maps commonmark block list table surge {$label} continuation line block"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . '| first line'
            . "\n" . $case['indent'] . '| second line'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'line_block'], $types($item));
        $t->same('line_block', $lineBlock->type);
        $t->same('first line', $lineBlock->children[0]->attr('text'));
        $t->same('second line', $lineBlock->children[1]->attr('text'));
    };

    $tests["maps commonmark block list table surge {$label} continuation definition list"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . 'Term'
            . "\n" . $case['indent'] . ': definition one'
            . "\n" . $case['indent'] . ': definition two'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $definitionList = $item->children[1] ?? new AstNode('missing');
        $definitionItem = $definitionList->children[0] ?? new AstNode('missing');

        $t->same(['text', 'definition_list'], $types($item));
        $t->same('definition_list', $definitionList->type);
        $t->same('Term', $definitionItem->attr('term'));
        $t->same('definition one', $definitionItem->children[1]->children[0]->attr('text'));
        $t->same('definition two', $definitionItem->children[2]->children[0]->attr('text'));
    };

    $tests["maps commonmark block list table surge {$label} continuation raw tex environment"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead'
            . "\n" . $case['indent'] . '\\begin{center}'
            . "\n" . $case['indent'] . 'Review'
            . "\n" . $case['indent'] . '\\end{center}'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $tex = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'raw_tex'], $types($item));
        $t->same('raw_tex', $tex->type);
        $t->same('center', $tex->attr('environment'));
        $t->contains('\\begin{center}', $tex->attr('tex'));
    };
}

$blockCases = [
    '01 plus bullet opens with atx heading' => [
        'markdown' => "+ # Import heading",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Import heading'],
        ],
    ],
    '02 task bullet opens with atx heading' => [
        'markdown' => "- [ ] # Review task heading",
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [false],
        'children' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Review task heading'],
        ],
    ],
    '03 decimal ordered opens with heading' => [
        'markdown' => "1. ## Ordered heading",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Ordered heading'],
        ],
    ],
    '04 default ordered opens with heading' => [
        'markdown' => "#. ### Default heading",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
        'children' => [
            ['type' => 'heading', 'level' => 3, 'text' => 'Default heading'],
        ],
    ],
    '05 numbered example opens with heading' => [
        'markdown' => "(@) # Example heading",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'children' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Example heading'],
        ],
    ],
    '06 bullet opens with blockquote' => [
        'markdown' => "- > quoted import",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'blockquote', 'text' => 'quoted import'],
        ],
    ],
    '07 bullet opens with multiline blockquote' => [
        'markdown' => "- > quoted\n  > continued",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'blockquote', 'text' => 'quoted continued'],
        ],
    ],
    '08 ordered opens with blockquote' => [
        'markdown' => "1. > ordered quote",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'blockquote', 'text' => 'ordered quote'],
        ],
    ],
    '09 checked task opens with blockquote' => [
        'markdown' => "- [x] > checked quote",
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [true],
        'children' => [
            ['type' => 'blockquote', 'text' => 'checked quote'],
        ],
    ],
    '10 nested bullet item opens with heading' => [
        'markdown' => "- parent\n  - # Child heading",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'parent'],
            ['type' => 'bullet_list', 'items' => ['Child heading']],
        ],
    ],
    '11 nested ordered item opens with blockquote' => [
        'markdown' => "- parent\n  1. > Child quote",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'parent'],
            ['type' => 'ordered_list', 'items' => ['Child quote'], 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        ],
    ],
    '12 paragraph continues into heading block' => [
        'markdown' => "- item\n  # Nested heading",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'heading', 'level' => 1, 'text' => 'Nested heading'],
        ],
    ],
    '13 paragraph continues into blockquote block' => [
        'markdown' => "- item\n  > nested quote",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'blockquote', 'text' => 'nested quote'],
        ],
    ],
    '14 paragraph continues into fenced php code' => [
        'markdown' => "- item\n  ```php\n  echo 1;\n  ```",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'code_block', 'text' => 'echo 1;', 'classes' => ['php']],
        ],
    ],
    '15 bullet opens with fenced php code' => [
        'markdown' => "- ```php\n  echo 1;\n  ```",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'code_block', 'text' => 'echo 1;', 'classes' => ['php']],
        ],
    ],
    '16 bullet opens with tilde code attributes' => [
        'markdown' => "- ~~~ {.php}\n  echo 2;\n  ~~~",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'code_block', 'text' => 'echo 2;', 'classes' => ['php']],
        ],
    ],
    '17 ordered paragraph continues into tilde js code' => [
        'markdown' => "1. item\n   ~~~ js\n   const ok = true;\n   ~~~",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'code_block', 'text' => 'const ok = true;', 'classes' => ['js']],
        ],
    ],
    '18 bullet loose item contains indented code block' => [
        'markdown' => "- item\n\n      code",
        'listType' => 'bullet_list',
        'loose' => true,
        'children' => [
            ['type' => 'paragraph', 'text' => 'item'],
            ['type' => 'code_block', 'text' => 'code'],
        ],
    ],
    '19 ordered loose item contains indented code block' => [
        'markdown' => "1. item\n\n       code",
        'listType' => 'ordered_list',
        'loose' => true,
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'paragraph', 'text' => 'item'],
            ['type' => 'code_block', 'text' => 'code'],
        ],
    ],
    '20 bullet paragraph continues into dash thematic break' => [
        'markdown' => "- item\n\n  ---",
        'listType' => 'bullet_list',
        'loose' => true,
        'children' => [
            ['type' => 'paragraph', 'text' => 'item'],
            ['type' => 'horizontal_rule'],
        ],
    ],
    '21 bullet paragraph continues into star thematic break' => [
        'markdown' => "- item\n  ***",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'horizontal_rule'],
        ],
    ],
    '22 bullet paragraph continues into underscore thematic break' => [
        'markdown' => "- item\n  ___",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'horizontal_rule'],
        ],
    ],
    '23 bullet opens with fenced div' => [
        'markdown' => "- ::: {.review}\n  body\n  :::",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'div', 'text' => 'body', 'classes' => ['review']],
        ],
    ],
    '24 bullet paragraph continues into fenced div' => [
        'markdown' => "- item\n  ::: {.review}\n  body\n  :::",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'div', 'text' => 'body', 'classes' => ['review']],
        ],
    ],
    '25 bullet opens with section raw html' => [
        'markdown' => "- <section>\n    *raw*\n  </section>",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'raw_html', 'html' => "<section>\n  *raw*\n</section>"],
        ],
    ],
    '26 bullet opens with div html block' => [
        'markdown' => "- <div>\n  body\n  </div>",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'div', 'text' => 'body'],
        ],
    ],
    '27 bullet paragraph continues into aside raw html' => [
        'markdown' => "- item\n  <aside>\n  note\n  </aside>",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'raw_html', 'html' => "<aside>\nnote\n</aside>"],
        ],
    ],
    '28 bullet opens with raw html comment' => [
        'markdown' => "- <!-- review -->",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'raw_html', 'html' => '<!-- review -->'],
        ],
    ],
    '29 bullet opens with processing instruction raw html' => [
        'markdown' => "- <?review instruction?>",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'raw_html', 'html' => '<?review instruction?>'],
        ],
    ],
    '30 bullet opens with cdata raw html' => [
        'markdown' => "- <![CDATA[review]]>",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'raw_html', 'html' => '<![CDATA[review]]>'],
        ],
    ],
    '31 bullet opens with line block' => [
        'markdown' => "- | line one\n  | line two",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'line_block', 'lines' => ['line one', 'line two']],
        ],
    ],
    '32 bullet paragraph continues into line block' => [
        'markdown' => "- item\n  | line one\n  | line two",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'line_block', 'lines' => ['line one', 'line two']],
        ],
    ],
    '33 bullet opens with pipe table' => [
        'markdown' => "- | A | B |\n  |---|---|\n  | 1 | 2 |",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'table', 'alignments' => ['default', 'default']],
        ],
    ],
    '34 bullet paragraph continues into pipe table' => [
        'markdown' => "- item\n  | A | B |\n  |---|---|\n  | 1 | 2 |",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'table', 'alignments' => ['default', 'default']],
        ],
    ],
    '35 bullet opens with colon definition list' => [
        'markdown' => "- Term\n  : Definition",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Definition']]]],
        ],
    ],
    '36 bullet opens with tilde definition list' => [
        'markdown' => "- Term\n  ~ Alternate definition",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Alternate definition']]]],
        ],
    ],
    '37 bullet opens with loose definition list' => [
        'markdown' => "- Term\n\n  : Loose definition",
        'listType' => 'bullet_list',
        'loose' => true,
        'children' => [
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Loose definition']]]],
        ],
    ],
    '38 bullet paragraph followed by definition list' => [
        'markdown' => "- intro\n\n  Term\n  : Definition",
        'listType' => 'bullet_list',
        'loose' => true,
        'children' => [
            ['type' => 'paragraph', 'text' => 'intro'],
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Definition']]]],
        ],
    ],
    '39 ordered item opens with definition list' => [
        'markdown' => "1. Term\n   : Definition",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Definition']]]],
        ],
    ],
    '40 bullet paragraph continues into definition list after blank' => [
        'markdown' => "- item\n\n  Term\n  ~ Alternate",
        'listType' => 'bullet_list',
        'loose' => true,
        'children' => [
            ['type' => 'paragraph', 'text' => 'item'],
            ['type' => 'definition_list', 'items' => [['term' => 'Term', 'definitions' => ['Alternate']]]],
        ],
    ],
    '41 unchecked task opens with fenced code' => [
        'markdown' => "- [ ] ```\n  code\n  ```",
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [false],
        'children' => [
            ['type' => 'code_block', 'text' => 'code'],
        ],
    ],
    '42 checked task paragraph continues into fenced code' => [
        'markdown' => "- [x] task\n  ```\n  code\n  ```",
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [true],
        'children' => [
            ['type' => 'text', 'text' => 'task'],
            ['type' => 'code_block', 'text' => 'code'],
        ],
    ],
    '43 ordered paragraph continues into heading block' => [
        'markdown' => "1. item\n   # Ordered nested heading",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'heading', 'level' => 1, 'text' => 'Ordered nested heading'],
        ],
    ],
    '44 upper alpha item opens with heading' => [
        'markdown' => "A.  # Alpha heading",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Alpha heading'],
        ],
    ],
    '45 upper roman item opens with blockquote' => [
        'markdown' => "I.  > Roman quote",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'upper_roman',
        'delimiter' => 'period',
        'children' => [
            ['type' => 'blockquote', 'text' => 'Roman quote'],
        ],
    ],
    '46 parenthesized ordered item opens with fenced code' => [
        'markdown' => "(1) ```\n    code\n    ```",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
        'children' => [
            ['type' => 'code_block', 'text' => 'code'],
        ],
    ],
    '47 default ordered paragraph continues into thematic break' => [
        'markdown' => "#. item\n\n   ---",
        'listType' => 'ordered_list',
        'loose' => true,
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
        'children' => [
            ['type' => 'paragraph', 'text' => 'item'],
            ['type' => 'horizontal_rule'],
        ],
    ],
    '48 numbered example paragraph continues into blockquote' => [
        'markdown' => "(@) item\n    > example quote",
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'children' => [
            ['type' => 'text', 'text' => 'item'],
            ['type' => 'blockquote', 'text' => 'example quote'],
        ],
    ],
    '49 heading item keeps following nested bullet' => [
        'markdown' => "- # Parent heading\n  - child",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Parent heading'],
            ['type' => 'bullet_list', 'items' => ['child']],
        ],
    ],
    '50 heading item keeps following ordered child' => [
        'markdown' => "- ## Parent heading\n  1. child",
        'listType' => 'bullet_list',
        'children' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Parent heading'],
            ['type' => 'ordered_list', 'items' => ['child'], 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        ],
    ],
];

foreach ($blockCases as $name => $case) {
    $tests['maps upstream markdown reader list-item block continuation surge ' . $name] = static function (TestRunner $t) use ($case, $nodeSummary): void {
        $document = (new MarkdownReader())->read($case['markdown']);
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');

        $t->same($case['listType'], $list->type);
        $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'));
        if (isset($case['start'])) {
            $t->same($case['start'], $list->attr('start'));
        }
        if (isset($case['style'])) {
            $t->same($case['style'], $list->attr('style'));
        }
        if (isset($case['delimiter'])) {
            $t->same($case['delimiter'], $list->attr('delimiter'));
        }
        if (isset($case['taskList'])) {
            $t->same($case['taskList'], (bool) $list->attr('taskList'));
        }
        if (isset($case['tasks'])) {
            $t->same($case['tasks'], array_map(
                static fn (AstNode $listItem): ?bool => $listItem->attr('taskChecked', null),
                $list->children
            ));
        }

        $t->same($case['children'], array_map($nodeSummary, $item->children));
    };
}

$lineTexts = static fn (AstNode $lineBlock): array => array_map(
    static fn (AstNode $line): string => (string) $line->attr('text', ''),
    $lineBlock->children
);

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list line-block surge {$label} first text"] = static function (TestRunner $t) use ($read, $types, $firstItem, $lineTexts, $case): void {
        $document = $read($case['marker'] . '| first' . "\n" . $case['indent'] . '| second' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[0] ?? new AstNode('missing');

        $t->same(['line_block'], $types($item));
        $t->same('line_block', $lineBlock->type);
        $t->same(['first', 'second'], $lineTexts($lineBlock));
    };

    $tests["maps commonmark block list line-block surge {$label} continuation"] = static function (TestRunner $t) use ($read, $types, $firstItem, $lineTexts, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '| first' . "\n" . $case['indent'] . '| second' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'line_block'], $types($item));
        $t->same('line_block', $lineBlock->type);
        $t->same(['first', 'second'], $lineTexts($lineBlock));
    };

    $tests["maps commonmark block list line-block surge {$label} loose continuation"] = static function (TestRunner $t) use ($read, $types, $lineTexts, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n\n" . $case['indent'] . '| first' . "\n" . $case['indent'] . '| second' . "\n" . $case['next']);
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $lineBlock = $item->children[1] ?? new AstNode('missing');

        $t->same($case['list'], $list->type);
        $t->same(true, (bool) $list->attr('loose'));
        $t->same(['paragraph', 'line_block'], $types($item));
        $t->same(['first', 'second'], $lineTexts($lineBlock));
    };

    $tests["maps commonmark block list line-block surge {$label} wrapped continuation"] = static function (TestRunner $t) use ($read, $types, $firstItem, $lineTexts, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '| first' . "\n" . $case['indent'] . '  wrapped' . "\n" . $case['indent'] . '| second' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'line_block'], $types($item));
        $t->same(['first wrapped', 'second'], $lineTexts($lineBlock));
    };

    $tests["maps commonmark block list line-block surge {$label} inline markup"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '| **strong** line' . "\n" . $case['indent'] . '| `code` line' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[1] ?? new AstNode('missing');
        $firstLine = $lineBlock->children[0] ?? new AstNode('missing');
        $secondLine = $lineBlock->children[1] ?? new AstNode('missing');

        $t->same(['text', 'line_block'], $types($item));
        $t->same(['strong', 'text'], $types($firstLine));
        $t->same(['code', 'text'], $types($secondLine));
    };

    $tests["maps commonmark block list line-block surge {$label} terminates before following paragraph"] = static function (TestRunner $t) use ($read, $types, $lineTexts, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . '| first' . "\n" . $case['indent'] . '| second' . "\nAfter");
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $lineBlock = $item->children[1] ?? new AstNode('missing');
        $after = $document->children[1] ?? new AstNode('missing');

        $t->same([$case['list'], 'paragraph'], $types($document));
        $t->same(['text', 'line_block'], $types($item));
        $t->same(['first', 'second'], $lineTexts($lineBlock));
        $t->same('After', $after->attr('text'));
    };
}

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list setext heading surge {$label} level one item"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'Setext heading' . "\n" . $case['indent'] . '===' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $heading = $item->children[0] ?? new AstNode('missing');

        $t->same(['heading'], $types($item));
        $t->same(1, $heading->attr('level'));
        $t->same('Setext heading', $heading->attr('text'));
        $t->same('setext-heading', $heading->attr('id'));
    };

    $tests["maps commonmark block list setext heading surge {$label} attributed level two item"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'Attributed setext {#list-setext .review data-source="block"}' . "\n" . $case['indent'] . '---' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $heading = $item->children[0] ?? new AstNode('missing');

        $t->same(['heading'], $types($item));
        $t->same(2, $heading->attr('level'));
        $t->same('Attributed setext', $heading->attr('text'));
        $t->same('list-setext', $heading->attr('id'));
        $t->same(['review'], $heading->attr('classes'));
        $t->same(['data-source' => 'block'], $heading->attr('attributes'));
    };

    $tests["maps commonmark block list setext heading surge {$label} loose paragraph before heading"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'intro' . "\n\n" . $case['indent'] . 'Loose setext' . "\n" . $case['indent'] . '---' . "\n" . $case['next']);
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $firstItem($t, $document, $case['list']);
        $paragraph = $item->children[0] ?? new AstNode('missing');
        $heading = $item->children[1] ?? new AstNode('missing');

        $t->same(true, (bool) $list->attr('loose'));
        $t->same(true, (bool) $item->attr('loose'));
        $t->same(['paragraph', 'heading'], $types($item));
        $t->same('intro', $paragraph->attr('text'));
        $t->same(2, $heading->attr('level'));
        $t->same('Loose setext', $heading->attr('text'));
    };

    $tests["maps commonmark block list setext heading surge {$label} inline markup"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'Setext **strong** `code`' . "\n" . $case['indent'] . '===' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $heading = $item->children[0] ?? new AstNode('missing');
        $childTypes = $types($heading);

        $t->same(['heading'], $types($item));
        $t->same(1, $heading->attr('level'));
        $t->same('Setext **strong** `code`', $heading->attr('text'));
        $t->true(in_array('strong', $childTypes, true), 'Setext heading should parse strong inline markup');
        $t->true(in_array('code', $childTypes, true), 'Setext heading should parse code inline markup');
    };

    $tests["maps commonmark block list setext heading surge {$label} terminates before following paragraph"] = static function (TestRunner $t) use ($read, $types, $case): void {
        $document = $read($case['marker'] . 'Terminal setext' . "\n" . $case['indent'] . '===' . "\nAfter");
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $heading = $item->children[0] ?? new AstNode('missing');
        $after = $document->children[1] ?? new AstNode('missing');

        $t->same([$case['list'], 'paragraph'], $types($document));
        $t->same(['heading'], $types($item));
        $t->same(1, $heading->attr('level'));
        $t->same('Terminal setext', $heading->attr('text'));
        $t->same('After', $after->attr('text'));
    };
}

$tests['records upstream markdown reader list setext heading mapped-case count'] =
    static function (TestRunner $t) use ($markers): void {
        $t->same(50, count($markers) * 5);
    };

$assertListAttrs = static function (TestRunner $t, AstNode $list, array $case): void {
    if (isset($case['start'])) {
        $t->same($case['start'], $list->attr('start'));
    }
    if (isset($case['style'])) {
        $t->same($case['style'], $list->attr('style'));
    }
    if (isset($case['delimiter'])) {
        $t->same($case['delimiter'], $list->attr('delimiter'));
    }
};

$emptyMarkerCases = [
    'dash bullet' => ['marker' => '-', 'next' => '- next', 'indent' => '  ', 'list' => 'bullet_list'],
    'plus bullet' => ['marker' => '+', 'next' => '+ next', 'indent' => '  ', 'list' => 'bullet_list'],
    'star bullet' => ['marker' => '*', 'next' => '* next', 'indent' => '  ', 'list' => 'bullet_list'],
    'decimal period' => ['marker' => '1.', 'next' => '2. next', 'indent' => '   ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    'decimal paren' => ['marker' => '1)', 'next' => '2) next', 'indent' => '   ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    'zero padded decimal' => ['marker' => '001.', 'next' => '002. next', 'indent' => '     ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    'default period' => ['marker' => '#.', 'next' => '#. next', 'indent' => '   ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    'default paren' => ['marker' => '#)', 'next' => '#) next', 'indent' => '   ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    'two paren decimal' => ['marker' => '(1)', 'next' => '(2) next', 'indent' => '    ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
    'numbered example' => ['marker' => '(@)', 'next' => '(@) next', 'indent' => '    ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    'labeled numbered example' => ['marker' => '(@review)', 'next' => '(@next) next', 'indent' => '          ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    'upper roman period' => ['marker' => 'IV.', 'next' => 'V.  next', 'indent' => '    ', 'list' => 'ordered_list', 'start' => 4, 'style' => 'upper_roman', 'delimiter' => 'period'],
    'lower roman period' => ['marker' => 'iv.', 'next' => 'v.  next', 'indent' => '    ', 'list' => 'ordered_list', 'start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'],
    'indented upper alpha period' => ['marker' => '  A.', 'next' => '  B.  next', 'indent' => '     ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
    'indented lower alpha paren' => ['marker' => '  a)', 'next' => '  b)  next', 'indent' => '     ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'lower_alpha', 'delimiter' => 'one_paren'],
    'indented parenthesized alpha' => ['marker' => '  (A)', 'next' => '  (B)  next', 'indent' => '      ', 'list' => 'ordered_list', 'start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'two_parens'],
];

foreach ($emptyMarkerCases as $label => $case) {
    $tests["maps commonmark block list empty marker continuation {$label}"] = static function (TestRunner $t) use ($read, $listItemText, $assertListAttrs, $case): void {
        $document = $read($case['marker'] . "\n" . $case['indent'] . 'continued' . "\n" . $case['next']);
        $list = $document->children[0] ?? new AstNode('missing');
        $first = $list->children[0] ?? new AstNode('missing');
        $second = $list->children[1] ?? new AstNode('missing');

        $t->same([$case['list']], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same($case['list'], $list->type);
        $assertListAttrs($t, $list, $case);
        $t->same('', $first->attr('text'));
        $t->same('continued', $listItemText($first));
        $t->same('next', $listItemText($second));
    };

    $tests["maps commonmark block list empty marker item {$label}"] = static function (TestRunner $t) use ($read, $listItemText, $assertListAttrs, $case): void {
        $document = $read($case['marker'] . "\n" . $case['next']);
        $list = $document->children[0] ?? new AstNode('missing');
        $first = $list->children[0] ?? new AstNode('missing');
        $second = $list->children[1] ?? new AstNode('missing');

        $t->same([$case['list']], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same($case['list'], $list->type);
        $assertListAttrs($t, $list, $case);
        $t->same('', $first->attr('text'));
        $t->same([], $first->children);
        $t->same('next', $listItemText($second));
    };
}

$bulletBoundaryCases = [];
foreach ([
    'dash' => '-',
    'plus' => '+',
    'star' => '*',
] as $leftName => $leftMarker) {
    foreach ([
        'dash' => '-',
        'plus' => '+',
        'star' => '*',
    ] as $rightName => $rightMarker) {
        if ($leftMarker === $rightMarker) {
            continue;
        }
        $bulletBoundaryCases["{$leftName} then {$rightName}"] = [$leftMarker, $rightMarker];
    }
}

foreach ($bulletBoundaryCases as $label => [$leftMarker, $rightMarker]) {
    $tests["maps commonmark block list bullet marker boundary {$label}"] = static function (TestRunner $t) use ($readCommonmark, $listItemText, $leftMarker, $rightMarker): void {
        $document = $readCommonmark($leftMarker . ' first' . "\n" . $rightMarker . ' second');
        $t->same(['bullet_list', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('first', $listItemText($document->children[0]->children[0]));
        $t->same('second', $listItemText($document->children[1]->children[0]));
    };

    $tests["maps commonmark block list blank bullet marker boundary {$label}"] = static function (TestRunner $t) use ($readCommonmark, $listItemText, $leftMarker, $rightMarker): void {
        $document = $readCommonmark($leftMarker . ' first' . "\n\n" . $rightMarker . ' second');
        $t->same(['bullet_list', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(false, (bool) $document->children[0]->attr('loose'));
        $t->same(false, (bool) $document->children[1]->attr('loose'));
        $t->same('first', $listItemText($document->children[0]->children[0]));
        $t->same('second', $listItemText($document->children[1]->children[0]));
    };
}

$tests['maps pandoc markdown and markdown_github mixed bullet markers as one tight list'] =
    static function (TestRunner $t) use ($listItemText): void {
        $markdown = "- first\n\n* second\n+ third\n- fourth";
        foreach (['markdown', 'markdown_github'] as $format) {
            $document = (new MarkdownReader(['format' => $format]))->read($markdown);
            $list = $document->children[0] ?? new AstNode('missing');

            $t->same(['bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $format);
            $t->same(false, (bool) $list->attr('loose'), $format);
            $t->same(['first', 'second', 'third', 'fourth'], array_map($listItemText, $list->children), $format);
        }
    };

$emptyMarkerBlockCases = [
    'dash blockquote' => ['markdown' => "-\n  > quoted\n- next", 'list' => 'bullet_list', 'child' => 'blockquote', 'text' => 'quoted'],
    'plus fenced code' => ['markdown' => "+\n  ``` php\n  echo 1;\n  ```\n+ next", 'list' => 'bullet_list', 'child' => 'code_block', 'code' => 'echo 1;', 'classes' => ['php']],
    'star heading' => ['markdown' => "*\n  # Nested\n* next", 'list' => 'bullet_list', 'child' => 'heading', 'text' => 'Nested'],
    'decimal paren horizontal rule' => ['markdown' => "1)\n   ---\n2) next", 'list' => 'ordered_list', 'child' => 'horizontal_rule', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    'default blockquote' => ['markdown' => "#.\n   > default\n#. next", 'list' => 'ordered_list', 'child' => 'blockquote', 'text' => 'default', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    'two paren fenced code' => ['markdown' => "(1)\n    ```\n    code\n    ```\n(2) next", 'list' => 'ordered_list', 'child' => 'code_block', 'code' => 'code', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
];

foreach ($emptyMarkerBlockCases as $label => $case) {
    $tests["maps commonmark block list empty marker block child {$label}"] = static function (TestRunner $t) use ($read, $listItemText, $assertListAttrs, $case): void {
        $document = $read($case['markdown']);
        $list = $document->children[0] ?? new AstNode('missing');
        $first = $list->children[0] ?? new AstNode('missing');
        $block = $first->children[0] ?? new AstNode('missing');

        $t->same([$case['list']], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same($case['list'], $list->type);
        $assertListAttrs($t, $list, $case);
        $t->same('', $first->attr('text'));
        $t->same($case['child'], $block->type);
        $t->same('next', $listItemText($list->children[1]));
        if (isset($case['text'])) {
            $t->same($case['text'], $block->attr('text', $listItemText($block)));
        }
        if (isset($case['code'])) {
            $t->same($case['code'], $block->attr('text'));
        }
        if (isset($case['classes'])) {
            $t->same($case['classes'], $block->attr('classes'));
        }
    };
}

$tableCellText = static function (AstNode $table, int $sectionIndex, int $rowIndex, int $cellIndex): string {
    $section = $table->children[$sectionIndex] ?? new AstNode('missing');
    $row = $section->children[$rowIndex] ?? new AstNode('missing');
    $cell = $row->children[$cellIndex] ?? new AstNode('missing');

    return (string) $cell->attr('text', '');
};

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list continuation surge {$label} setext heading"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . "\n" . $case['indent'] . 'Nested heading' . "\n" . $case['indent'] . '---' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $heading = $item->children[0] ?? new AstNode('missing');

        $t->same(['heading'], $types($item));
        $t->same(2, $heading->attr('level'));
        $t->same('Nested heading', $heading->attr('text'));
        $t->same('nested-heading', $heading->attr('id'));
    };

    $tests["maps commonmark block list continuation surge {$label} line block"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . '| first line' . "\n" . $case['indent'] . '| second line' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $lineBlock = $item->children[0] ?? new AstNode('missing');

        $t->same(['line_block'], $types($item));
        $t->same('line_block', $lineBlock->type);
        $t->same(['first line', 'second line'], array_map(
            static fn (AstNode $line): string => (string) $line->attr('text', ''),
            $lineBlock->children
        ));
    };

    $tests["maps commonmark block list continuation surge {$label} definition list"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n" . $case['indent'] . 'Term' . "\n" . $case['indent'] . ': definition' . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $definitionList = $item->children[1] ?? new AstNode('missing');
        $definitionItem = $definitionList->children[0] ?? new AstNode('missing');
        $term = $definitionItem->children[0] ?? new AstNode('missing');
        $definition = $definitionItem->children[1] ?? new AstNode('missing');
        $paragraph = $definition->children[0] ?? new AstNode('missing');

        $t->same(['text', 'definition_list'], $types($item));
        $t->same('definition_list', $definitionList->type);
        $t->same('Term', $definitionItem->attr('term'));
        $t->same('term', $term->type);
        $t->same('Term', $term->attr('text'));
        $t->same('definition', $definition->type);
        $t->same('definition', $paragraph->attr('text'));
    };

    $tests["maps commonmark block list continuation surge {$label} pipe table"] = static function (TestRunner $t) use ($read, $types, $firstItem, $tableCellText, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n"
            . $case['indent'] . '| Term | Count |' . "\n"
            . $case['indent'] . '|:-----|------:|' . "\n"
            . $case['indent'] . '| Alpha | 2 |' . "\n"
            . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $table = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'table'], $types($item));
        $t->same('table', $table->type);
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same('Term', $tableCellText($table, 0, 0, 0));
        $t->same('Count', $tableCellText($table, 0, 0, 1));
        $t->same('Alpha', $tableCellText($table, 1, 0, 0));
        $t->same('2', $tableCellText($table, 1, 0, 1));
    };

    $tests["maps commonmark block list continuation surge {$label} raw tex block"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'lead' . "\n"
            . $case['indent'] . '\\begin{note}' . "\n"
            . $case['indent'] . 'body' . "\n"
            . $case['indent'] . '\\end{note}' . "\n"
            . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $rawTex = $item->children[1] ?? new AstNode('missing');

        $t->same(['text', 'raw_tex'], $types($item));
        $t->same('raw_tex', $rawTex->type);
        $t->same('note', $rawTex->attr('environment'));
        $t->same("\\begin{note}\nbody\n\\end{note}", $rawTex->attr('tex'));
    };
}

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list setext heading surge {$label} opening equals underline"] =
        static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
            $document = $read($case['marker'] . 'Review title' . "\n" . $case['indent'] . '===' . "\n" . $case['next']);
            $item = $firstItem($t, $document, $case['list']);
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $types($item));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('Review title', $heading->attr('text'));
            $t->same('review-title', $heading->attr('id'));
        };

    $tests["maps commonmark block list setext heading surge {$label} multiline equals underline"] =
        static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
            $document = $read($case['marker'] . 'Review title' . "\n" . $case['indent'] . 'wrapped detail' . "\n" . $case['indent'] . '====' . "\n" . $case['next']);
            $item = $firstItem($t, $document, $case['list']);
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $types($item));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('Review title wrapped detail', $heading->attr('text'));
            $t->same('review-title-wrapped-detail', $heading->attr('id'));
        };

    $tests["maps commonmark block list setext heading surge {$label} attributed equals underline"] =
        static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
            $document = $read($case['marker'] . 'Review title {#nested-setext .queue data-case="setext"}' . "\n" . $case['indent'] . '===' . "\n" . $case['next']);
            $item = $firstItem($t, $document, $case['list']);
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $types($item));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('Review title', $heading->attr('text'));
            $t->same('nested-setext', $heading->attr('id'));
            $t->same(['queue'], $heading->attr('classes'));
            $t->same(['data-case' => 'setext'], $heading->attr('attributes'));
        };

    $tests["maps commonmark block list setext heading surge {$label} loose equals continuation"] =
        static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
            $document = $read($case['marker'] . 'lead' . "\n\n" . $case['indent'] . 'Review title' . "\n" . $case['indent'] . '===' . "\n" . $case['next']);
            $list = $document->children[0] ?? new AstNode('missing');
            $item = $firstItem($t, $document, $case['list']);
            $paragraph = $item->children[0] ?? new AstNode('missing');
            $heading = $item->children[1] ?? new AstNode('missing');

            $t->same(true, (bool) $list->attr('loose'));
            $t->same(['paragraph', 'heading'], $types($item));
            $t->same('lead', $paragraph->attr('text'));
            $t->same('Review title', $heading->attr('text'));
            $t->same('review-title', $heading->attr('id'));
        };

    $tests["maps commonmark block list setext heading surge {$label} followed by paragraph"] =
        static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
            $document = $read($case['marker'] . 'Review title' . "\n" . $case['indent'] . '===' . "\n" . $case['indent'] . 'after words' . "\n" . $case['next']);
            $item = $firstItem($t, $document, $case['list']);
            $heading = $item->children[0] ?? new AstNode('missing');
            $paragraph = $item->children[1] ?? new AstNode('missing');

            $t->same(['heading', 'paragraph'], $types($item));
            $t->same('Review title', $heading->attr('text'));
            $t->same('review-title', $heading->attr('id'));
            $t->same('after words', $paragraph->attr('text'));
        };
}

$setextListHeadingVariants = [
    'marker line h1' => ['lines' => ['Import heading'], 'marker' => '====', 'level' => 1, 'text' => 'Import heading'],
    'marker line h1 trailing spaces' => ['lines' => ['Review heading'], 'marker' => '====   ', 'level' => 1, 'text' => 'Review heading'],
    'marker line multiline h1' => ['lines' => ['Wrapped heading', 'second line'], 'marker' => '====', 'level' => 1, 'text' => 'Wrapped heading second line'],
    'marker line attributed h1' => ['lines' => ['Attributed heading'], 'marker' => '====', 'level' => 1, 'text' => 'Attributed heading', 'attributed' => true],
    'marker line inline h1' => ['lines' => ['Inline **strong** heading'], 'marker' => '====', 'level' => 1, 'text' => 'Inline **strong** heading', 'inline' => true],
];

foreach ($markers as $label => $case) {
    foreach ($setextListHeadingVariants as $variantLabel => $variant) {
        $tests["maps commonmark block list setext heading surge {$label} {$variantLabel}"] = static function (TestRunner $t) use ($read, $types, $firstItem, $label, $case, $variant): void {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?? $label;
            $headingLines = $variant['lines'];
            if (($variant['attributed'] ?? false) === true) {
                $headingLines = [$headingLines[0] . ' {#list-setext-' . trim($slug, '-') . ' .review}'];
            }
            $markdown = $case['marker'] . $headingLines[0];
            foreach (array_slice($headingLines, 1) as $line) {
                $markdown .= "\n" . $case['indent'] . $line;
            }
            $markdown .= "\n" . $case['indent'] . $variant['marker'] . "\n" . $case['next'];
            $document = $read($markdown);
            $item = $firstItem($t, $document, $case['list']);
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $types($item));
            $t->same('heading', $heading->type);
            $t->same($variant['level'], $heading->attr('level'));
            $t->same($variant['text'], $heading->attr('text'));
            if (($variant['attributed'] ?? false) === true) {
                $t->same('list-setext-' . trim($slug, '-'), $heading->attr('id'));
                $t->same(['review'], $heading->attr('classes'));
            }
            if (($variant['inline'] ?? false) === true) {
                $t->same(['text', 'strong', 'text'], $types($heading));
                $t->same('strong', $heading->children[1]->children[0]->attr('text'));
            }
        };
    }
}

foreach ($markers as $label => $case) {
    $tests["maps commonmark block list simple table marker-line surge {$label}"] = static function (TestRunner $t) use ($read, $types, $firstItem, $case): void {
        $document = $read($case['marker'] . 'Term          Count'
            . "\n" . $case['indent'] . '------------  -----'
            . "\n" . $case['indent'] . 'A001              7'
            . "\n" . $case['next']);
        $item = $firstItem($t, $document, $case['list']);
        $table = $item->children[0] ?? new AstNode('missing');

        $t->same(['table'], $types($item));
        $t->same('table', $table->type);
        $t->same(['left', 'default'], $table->attr('alignments'));
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Count', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same('A001', $table->children[1]->children[0]->children[0]->attr('text'));
        $t->same('7', $table->children[1]->children[0]->children[1]->attr('text'));
    };
}

return $tests;
