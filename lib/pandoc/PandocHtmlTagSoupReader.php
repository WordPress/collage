<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocHtmlTagSoupReader
{
    private const TOKEN_PREFIX_RELEASE_INTERVAL = 4096;

    private ?TagSoupTokenStream $tokens = null;
    private int $index = 0;
    private int $tokenEnd = 0;
    private int $releasedTokenIndex = 0;
    private ?string $htmlBaseHref = null;
    private int $listItemDepth = 0;
    private int $quoteDepth = 0;
    private bool $inlineRunEndedAtTextBoundary = false;
    /** @var array<string, list<AstNode>> */
    private array $footnotes = [];
    /** @var list<string> */
    private array $containerIdStack = [];
    private ?PandocHtmlAttributeMapPool $attributeMapPool = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $html): AstNode
    {
        $this->attributeMapPool = new PandocHtmlAttributeMapPool();
        $parser = new TagSoupParser();
        $tokens = $parser->parseCanonicalStream($html);
        // The stream retains payloads rather than one object per token. It can
        // release consumed payloads after global metadata and endnotes have
        // been scanned below.
        unset($parser);
        $this->htmlBaseHref = $this->firstBaseHref($tokens);
        $documentMeta = $this->documentMetadataFromTokens($tokens);
        $this->footnotes = (new PandocHtmlTagSoupTableReader($this->attributeMapPool))->footnoteDefinitionsFromTokenStream($tokens);
        $this->tokens = $tokens;
        [$this->index, $this->tokenEnd] = $this->firstMainElementRange($tokens);
        $this->releasedTokenIndex = $this->index;
        unset($tokens);
        $this->listItemDepth = 0;
        $this->quoteDepth = 0;
        $this->inlineRunEndedAtTextBoundary = false;
        $this->containerIdStack = [];
        $blocks = $this->parseBlocksUntil([]);
        $this->tokens = null;
        $this->tokenEnd = 0;
        $this->releasedTokenIndex = 0;
        $this->attributeMapPool = null;

        return new AstNode('document', [
            'sourceFormat' => 'html',
            'meta' => array_replace($documentMeta, [
                'sourceFormat' => 'html',
                'reader' => self::class,
                'readerScope' => 'tagsoup-pandoc-html-reader-port',
                'htmlTokenizer' => TagSoupParser::class,
                'sourceBytes' => strlen($html),
                'sourceSha256' => hash('sha256', $html),
            ]),
        ], $blocks);
    }

    /**
     * @return list<TagSoupTag>
     */
    public function tokenize(string $html): array
    {
        return (new TagSoupParser())->parseCanonical($html);
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseBlocksUntil(array $stopTags): array
    {
        $blocks = [];
        while (true) {
            $this->releaseConsumedTokenPrefix();
            $token = $this->current();
            if (!$token instanceof TagSoupTag) {
                break;
            }
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $this->openTagImpliesClose($token->name, $stopTags)) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT && trim($token->text) === '') {
                $this->index++;
                continue;
            }

            if ($this->skipIgnorable($token)) {
                continue;
            }

            if ($token->type === TagSoupTag::OPEN) {
                if ($token->name === 'details' || $token->name === 'summary') {
                    $this->index++;
                    array_push($blocks, ...$this->parseBlocksUntil([$token->name]));
                    $this->consumeClose($token->name);
                    continue;
                }
                if (str_starts_with($token->name, '!') || str_starts_with($token->name, '?')) {
                    $this->index++;
                    continue;
                }
                if ($token->name === 'html' || $token->name === 'body') {
                    $this->index++;
                    array_push($blocks, ...$this->parseBlocksUntil([$token->name]));
                    $this->consumeClose($token->name);
                    continue;
                }
                if ($token->name === 'head') {
                    $this->index++;
                    $this->skipUntilClose('head');
                    continue;
                }
                $block = $this->parseOpenBlock($token, $stopTags);
                if ($block instanceof AstNode) {
                    $blocks[] = $block;
                    continue;
                }
                if (is_array($block)) {
                    array_push($blocks, ...$block);
                    continue;
                }
            }

            $beforeInlineIndex = $this->index;
            $this->inlineRunEndedAtTextBoundary = false;
            $inlines = $this->parseInlinesUntil($stopTags, stopBeforeBlock: true);
            if ($inlines !== []) {
                array_push($blocks, ...$this->inlineFallbackBlocks(
                    $inlines,
                    $stopTags === [] && $blocks === [] && !$this->inlineRunEndedAtTextBoundary,
                    $this->inlineRunEndedAtTextBoundary
                ));
                continue;
            }

            if ($this->index === $beforeInlineIndex) {
                $this->index++;
            }
        }

        return $blocks;
    }

    private function releaseConsumedTokenPrefix(): void
    {
        if ($this->index - $this->releasedTokenIndex < self::TOKEN_PREFIX_RELEASE_INTERVAL) {
            return;
        }

        $this->tokens?->releaseBefore($this->index);
        $this->releasedTokenIndex = $this->index;
    }

    /**
     * @param list<string> $outerStopTags
     * @return AstNode|list<AstNode>|null
     */
    private function parseOpenBlock(TagSoupTag $token, array $outerStopTags): AstNode|array|null
    {
        $name = $token->name;
        if (in_array($name, ['article', 'nav', 'fieldset', 'search', 'hgroup'], true)) {
            if ($this->isTitlepageElement($token) || $this->isFootnoteContainer($token)) {
                $this->index++;
                $this->skipBalancedElement($name);

                return [];
            }

            $this->index++;
            $children = $this->parseBlocksUntil([$name, ...$outerStopTags]);
            $this->consumeClose($name);

            return $children;
        }

        if (in_array($name, ['main', 'section', 'article', 'header', 'footer', 'aside'], true)) {
            if ($this->isTitlepageElement($token)) {
                $this->index++;
                $this->skipUntilClose($name);

                return [];
            }
            if ($this->isFootnoteContainer($token)) {
                $this->index++;
                $this->skipBalancedElement($name);

                return [];
            }
            $hasExplicitParagraph = $name === 'main' && $this->balancedElementContainsOpenTag('main', 'p');
            $this->index++;
            $pushedContainerId = $this->pushContainerId($token);
            $children = $this->parseBlocksUntil([$name]);
            if ($pushedContainerId) {
                array_pop($this->containerIdStack);
            }
            $this->consumeClose($name);
            $attrs = $this->nativeContainerAttrs($token);
            if ($name === 'main') {
                if ($attrs === []) {
                    return $hasExplicitParagraph ? $children : $this->flattenPlainMainChildren($children);
                }

                if (!$hasExplicitParagraph) {
                    $children = $this->flattenSingleParagraphBlock($children);
                }
            }

            return new AstNode('div', $attrs, $children);
        }

        if ($name === 'p' || $name === 'address') {
            $this->index++;
            $inlines = $this->parseInlinesUntil([$name, ...$outerStopTags], stopBeforeBlock: true);
            $this->consumeClose($name);

            return AstNode::withTextFromChildren('paragraph', [], $inlines);
        }

        if (preg_match('/^h([1-6])$/', $name, $m) === 1) {
            $this->index++;
            $inlines = $this->parseInlinesUntil([$name], stopBeforeBlock: true);
            $this->consumeClose($name);
            $text = $this->plainTextFromInlines($inlines);
            $attrs = $this->pandocAttrs($token);
            $attrs['level'] = (int) $m[1];
            $attrs['text'] = $text;
            if ($this->hasAttribute($token, 'id') && in_array($this->attribute($token, 'id'), $this->containerIdStack, true)) {
                unset($attrs['id']);
            } else {
                $identifier = $this->htmlHeadingIdentifier($text);
                if ($identifier !== '') {
                    $attrs['id'] = $identifier;
                }
            }

            return new AstNode('heading', $attrs, $inlines);
        }

        if ($name === 'blockquote') {
            $this->index++;
            $blocks = $this->parseBlocksUntil(['blockquote']);
            $this->consumeClose('blockquote');

            return new AstNode('blockquote', $this->pandocAttrs($token), $blocks);
        }

        if ($name === 'ul' || $name === 'ol') {
            return $this->parseListBlock($token);
        }

        if ($name === 'dl') {
            return $this->parseDefinitionListBlock($token);
        }

        if ($name === 'figure') {
            return $this->parseFigureBlock($token);
        }

        if (in_array($name, ['audio', 'video', 'progress'], true)) {
            return $this->parseTopLevelMediaFallback($token, $outerStopTags);
        }

        if (in_array($name, ['form', 'noscript', 'object', 'iframe', 'template', 'noembed', 'noframes', 'plaintext'], true)) {
            if ($this->rawHtmlEnabled() && in_array($name, ['noscript', 'object', 'iframe'], true) && !$this->balancedElementHasBlockChild($name)) {
                return $this->parseRawInlineWrapperBlock($token, $outerStopTags);
            }
            $this->index++;
            $children = $this->parseBlocksUntil([$name, ...$outerStopTags]);
            $this->consumeClose($name);

            return $children;
        }

        if ($name === 'menu') {
            return $this->parseMenuBlock($token);
        }

        if ($name === 'div' && in_array('line-block', $this->classes($token), true)) {
            return $this->parseLineBlock($token);
        }

        if ($name === 'pre') {
            return $this->parsePreBlock($token);
        }

        if ($name === 'hr') {
            $this->index++;
            return new AstNode('horizontal_rule');
        }

        if (in_array($name, ['table'], true)) {
            $tableEnd = $this->balancedTokenStreamEnd($this->index, 'table');
            $tableTokens = $this->tokenStream()->slice($this->index, $tableEnd - $this->index);
            $result = (new PandocHtmlTagSoupTableReader($this->attributeMapPool()))->parseTableAt($tableTokens, 0, $this->footnotes);
            if (is_array($result)) {
                $this->index += max(1, (int) $result['nextIndex']);

                return $result['blocks'];
            }

            $raw = $this->renderBalancedTokenSource($name);

            return new AstNode('raw_html', ['format' => 'html', 'html' => $raw, 'text' => $raw]);
        }

        if (in_array($name, ['tbody', 'thead', 'tfoot', 'tr'], true)) {
            $this->index++;
            $children = $this->parseBlocksUntil([$name, ...$outerStopTags]);
            $this->consumeClose($name);

            return $children;
        }

        if (in_array($name, ['td', 'th', 'caption'], true)) {
            $this->index++;
            $inlines = $this->parseInlinesUntil([$name, ...$outerStopTags], stopBeforeBlock: true);
            $this->consumeClose($name);

            return $inlines === [] ? [] : [AstNode::withTextFromChildren('paragraph', [], $inlines)];
        }

        if (in_array($name, ['col', 'colgroup'], true)) {
            $this->index++;
            $this->consumeClose($name);

            return [];
        }

        if (in_array($name, ['script', 'style', 'textarea', 'xmp'], true)) {
            return $this->parseRawTextBlock($token);
        }

        if ($name === 'button' && $this->balancedElementContainsOpenTag('button', 'p')) {
            return $this->parseButtonScopeBlock($outerStopTags);
        }

        if ($this->isBlockElement($name)) {
            $this->index++;
            $children = $this->parseBlocksUntil([$name, ...$outerStopTags]);
            $this->consumeClose($name);

            return new AstNode('div', $this->pandocAttrs($token), $children);
        }

        return null;
    }

    private function parseListBlock(TagSoupTag $list): AstNode
    {
        $ordered = $list->name === 'ol';
        $attrs = $ordered
            ? [
                'start' => (int) ($this->attribute($list, 'start') ?: 1),
                'style' => $this->orderedListStyle($list),
                'delimiter' => 'default',
            ]
            : [];
        $items = [];
        $this->index++;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $list->name) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT && trim($token->text) === '') {
                $this->index++;
                continue;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'li') {
                $this->index++;
                $explicitParagraph = ($this->current() instanceof TagSoupTag)
                    && $this->current()->type === TagSoupTag::OPEN
                    && $this->current()->name === 'p';
                $this->listItemDepth++;
                $blocks = $this->parseBlocksUntil(['li', $list->name]);
                $this->listItemDepth--;
                $this->consumeClose('li');
                $blocks = $this->applyListItemId($token, $this->listItemBlocks($blocks, $explicitParagraph), $explicitParagraph);
                $items[] = $this->listItemNode($blocks);
                continue;
            }
            $orphanBlocks = $this->parseListOrphanBlocks($list->name);
            if ($orphanBlocks !== []) {
                $lastIndex = array_key_last($items);
                if ($lastIndex === null) {
                    $items[] = $this->listItemNode($orphanBlocks);
                    continue;
                }

                $items[$lastIndex] = $this->listItemNode([...$items[$lastIndex]->children, ...$orphanBlocks]);
                continue;
            }
            $this->index++;
        }
        $this->consumeClose($list->name);

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function listItemNode(array $blocks): AstNode
    {
        if ($blocks === []) {
            $blocks[] = new AstNode('plain');
        }

        return new AstNode('list_item', ['text' => $this->plainTextFromBlocks($blocks)], $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function parseListOrphanBlocks(string $listName): array
    {
        $token = $this->current();
        if (!$token instanceof TagSoupTag) {
            return [];
        }

        if ($token->type === TagSoupTag::OPEN) {
            $block = $this->parseOpenBlock($token, [$listName]);
            if ($block instanceof AstNode) {
                return [$block];
            }
            if (is_array($block)) {
                return $block;
            }
        }

        $beforeInlineIndex = $this->index;
        $inlines = $this->parseInlinesUntil([$listName], stopBeforeBlock: true);
        if ($inlines !== []) {
            return $this->inlineFallbackBlocks($inlines);
        }
        if ($this->index === $beforeInlineIndex) {
            return [];
        }

        return [];
    }

    private function orderedListStyle(TagSoupTag $list): string
    {
        $type = $this->orderedListStyleFromType($this->attribute($list, 'type'));
        if ($type !== 'default') {
            return $type;
        }

        foreach ($this->classes($list) as $class) {
            $style = $this->orderedListStyleFromCssValue($class);
            if ($style !== 'default') {
                return $style;
            }
        }

        foreach ($this->orderedListStyleDeclarations($this->attribute($list, 'style')) as $value) {
            $style = $this->orderedListStyleFromCssValue($value);
            if ($style !== 'default') {
                return $style;
            }
        }

        return 'default';
    }

    private function orderedListStyleFromType(string $type): string
    {
        return match ($type) {
            'A' => 'upper_alpha',
            'a' => 'lower_alpha',
            'I' => 'upper_roman',
            'i' => 'lower_roman',
            '1' => 'decimal',
            default => 'default',
        };
    }

    private function orderedListStyleFromCssValue(string $value): string
    {
        return match (strtolower(trim($value))) {
            'decimal' => 'decimal',
            'lower-alpha' => 'lower_alpha',
            'upper-alpha' => 'upper_alpha',
            'lower-roman' => 'lower_roman',
            'upper-roman' => 'upper_roman',
            default => 'default',
        };
    }

    /**
     * @return list<string>
     */
    private function orderedListStyleDeclarations(string $style): array
    {
        $value = CssDeclarationScanner::lastValidValue(
            $style,
            ['list-style-type', 'list-style'],
            fn (string $value): bool => $this->orderedListStyleFromCssValue($value) !== 'default'
        );

        return $value === null ? [] : [$value];
    }

    private function parseDefinitionListBlock(TagSoupTag $list): AstNode
    {
        $items = [];
        $currentTerm = null;
        $definitions = [];
        $flush = function () use (&$items, &$currentTerm, &$definitions): void {
            if ($currentTerm instanceof AstNode) {
                $items[] = new AstNode('definition_item', [], [$currentTerm, ...$definitions]);
            }
            $currentTerm = null;
            $definitions = [];
        };

        $this->index++;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'dl') {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'dt') {
                $this->index++;
                $inlines = $this->parseInlinesUntil(['dt']);
                $this->consumeClose('dt');
                if ($currentTerm instanceof AstNode && $definitions === []) {
                    $currentTerm = new AstNode('term', [
                        'text' => $this->plainTextFromInlines($currentTerm->children) . "\n" . $this->plainTextFromInlines($inlines),
                    ], [
                        ...$currentTerm->children,
                        new AstNode('linebreak'),
                        ...$inlines,
                    ]);
                    continue;
                }
                if ($currentTerm instanceof AstNode) {
                    $flush();
                }
                $currentTerm = AstNode::withTextFromChildren('term', [], $inlines);
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'dd') {
                $this->index++;
                $blocks = $this->definitionBlocksUntilDdClose();
                $this->consumeClose('dd');
                $definitions[] = new AstNode('definition', [], $blocks);
                continue;
            }
            $this->index++;
        }

        $flush();
        $this->consumeClose('dl');

        return new AstNode('definition_list', $this->pandocAttrs($list), $items);
    }

    /**
     * @return list<AstNode>
     */
    private function definitionBlocksUntilDdClose(): array
    {
        $token = $this->current();
        if ($token instanceof TagSoupTag && $token->type === TagSoupTag::OPEN && $this->isBlockElement($token->name)) {
            return $this->parseBlocksUntil(['dd', 'dl']);
        }

        $inlines = $this->parseInlinesUntil(['dd', 'dl']);

        return $inlines === [] ? [] : [new AstNode('plain', [], $inlines)];
    }

    /**
     * @return list<AstNode>
     */
    private function parseMenuBlock(TagSoupTag $menu): array
    {
        $blocks = [];
        $this->index++;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'menu') {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'li') {
                $this->index++;
                array_push($blocks, ...$this->parseBlocksUntil(['li']));
                $this->consumeClose('li');
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $block = $this->parseOpenBlock($token, ['menu']);
                if ($block instanceof AstNode) {
                    $blocks[] = $block;
                    continue;
                }
                if (is_array($block)) {
                    array_push($blocks, ...$block);
                    continue;
                }
            }
            $inlines = $this->parseInlinesUntil(['menu'], stopBeforeBlock: true);
            if ($inlines !== []) {
                array_push($blocks, ...$this->inlineFallbackBlocks($inlines));
                continue;
            }
            $this->index++;
        }
        $this->consumeClose('menu');

        return $blocks;
    }

    private function parseLineBlock(TagSoupTag $div): AstNode
    {
        $this->index++;
        $inlines = $this->parseInlinesUntil(['div']);
        $this->consumeClose('div');
        $lines = [[]];
        foreach ($inlines as $inline) {
            if ($inline->type === 'linebreak') {
                $lines[] = [];
                continue;
            }
            $lines[count($lines) - 1][] = $inline;
        }

        $children = [];
        foreach ($lines as $line) {
            $children[] = AstNode::withTextFromChildren('line', [], $line);
        }

        return new AstNode('line_block', $this->pandocAttrs($div, ['class']), $children);
    }

    private function parseFigureBlock(TagSoupTag $figure): AstNode
    {
        $this->index++;
        $bodyBlocks = [];
        $captionInlines = [];
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'figure') {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::TEXT && trim($token->text) === '') {
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'figcaption') {
                $this->index++;
                $captionInlines = $this->parseInlinesUntil(['figcaption']);
                $this->consumeClose('figcaption');
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $block = $this->parseOpenBlock($token, ['figure']);
                if ($block instanceof AstNode) {
                    $bodyBlocks[] = $block;
                    continue;
                }
                if (is_array($block)) {
                    array_push($bodyBlocks, ...$block);
                    continue;
                }
            }
            $inlines = $this->parseInlinesUntil(['figure'], stopBeforeBlock: true);
            if ($inlines !== []) {
                array_push($bodyBlocks, ...$this->inlineFallbackBlocks($inlines));
                continue;
            }
            $this->index++;
        }
        $this->consumeClose('figure');

        $attrs = $this->pandocAttrs($figure);
        $attrs['caption'] = $this->plainTextFromInlines($captionInlines);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        return new AstNode('figure', $attrs, $bodyBlocks);
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseInlinesUntil(array $stopTags, bool $stopBeforeBlock = false, bool $trimBoundary = true): array
    {
        $inlines = [];
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $this->openTagImpliesClose($token->name, $stopTags)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $stopBeforeBlock && $this->isBlockElement($token->name)) {
                break;
            }
            if (
                $token->type === TagSoupTag::OPEN
                && $stopBeforeBlock
                && in_array('p', $stopTags, true)
                && $token->name === 'button'
                && $this->balancedElementContainsOpenTag('button', 'p')
            ) {
                break;
            }
            if (
                $token->type === TagSoupTag::OPEN
                && $stopBeforeBlock
                && !in_array('p', $stopTags, true)
                && in_array($token->name, ['script', 'style', 'textarea', 'xmp'], true)
            ) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $this->rawDisabledScriptBreaksParagraph($token)) {
                $this->index++;
                $this->collectTextUntilClose('script');
                $this->consumeClose('script');
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::COMMENT) {
                $inlines[] = $this->rawHtmlInline('<!--' . $token->text . '-->');
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                if (($split = $this->topLevelInlineTextBoundary($token->text, $inlines, $stopTags, $stopBeforeBlock)) !== null) {
                    [$before, $after] = $split;
                    if ($before !== '') {
                        $inlines[] = new AstNode('text', ['text' => $before]);
                    }
                    if ($after !== '') {
                        // The next block consumes the remainder from this
                        // same token slot. Replacing it avoids shifting a
                        // large token array merely to split one text span.
                        $this->tokenStream()->replaceAt($this->index, TagSoupTag::text($after));
                    } else {
                        $this->index++;
                    }
                    break;
                }
                $inlines[] = new AstNode('text', ['text' => $token->text]);
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                array_push($inlines, ...$this->parseOpenInline($token));
                continue;
            }
            $this->index++;
        }

        return $this->normalizeInlineText($inlines, $trimBoundary);
    }

    /**
     * @return list<AstNode>
     */
    private function parseOpenInline(TagSoupTag $token): array
    {
        $name = $token->name;
        if ($name === 'br') {
            $this->index++;
            $this->consumeClose('br');
            return [new AstNode('linebreak')];
        }

        if ($name === 'em' || $name === 'i') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace('emph', [], $children);
        }

        if ($name === 'strong' || $name === 'b') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace('strong', [], $children);
        }

        if ($name === 'code' || $name === 'tt') {
            $this->index++;
            $text = $this->collectTextUntilClose($name);
            $this->consumeClose($name);

            return [new AstNode('code', ['text' => $text])];
        }

        if ($name === 'samp' || $name === 'var') {
            $this->index++;
            $text = $this->collectTextUntilClose($name);
            $this->consumeClose($name);

            return [new AstNode('code', [
                'classes' => [$name === 'samp' ? 'sample' : 'variable'],
                'text' => $text,
            ])];
        }

        if ($name === 'kbd') {
            $this->index++;
            $children = $this->parseInlinesUntil(['kbd'], trimBoundary: false);
            $this->consumeClose('kbd');

            return $this->inlineWrapperWithBoundaryWhitespace('span', $this->withPrependedClass($this->pandocAttrs($token), 'kbd'), $children);
        }

        if ($name === 'a') {
            if ($this->isFootnoteReference($token)) {
                $this->index++;
                $this->skipUntilClose('a');
                $this->consumeClose('a');
                $id = ltrim($this->attribute($token, 'href'), '#');

                return [new AstNode('note', [], $this->footnotes[$id] ?? [])];
            }
            $this->index++;
            $children = $this->parseInlinesUntil(['a'], trimBoundary: false);
            $this->consumeClose('a');
            $url = $this->attribute($token, 'href');
            if (!$this->hasAttribute($token, 'href')) {
                $attrs = $this->pandocAttrs($token, ['name']);
                $nameAttr = trim($this->attribute($token, 'name'));
                if ($nameAttr !== '' && !isset($attrs['id'])) {
                    $attrs['id'] = $nameAttr;
                    $htmlAttributes = $attrs['htmlAttributes'] ?? [];
                    if (!is_array($htmlAttributes)) {
                        $htmlAttributes = [];
                    }
                    $htmlAttributes['id'] = $nameAttr;
                    $attrs['htmlAttributes'] = $htmlAttributes;
                }
                if ($attrs !== []) {
                    return $this->inlineWrapperWithBoundaryWhitespace('span', $attrs, $children);
                }

                return $this->normalizeInlineText($children);
            }

            return $this->inlineWrapperWithBoundaryWhitespace('link', ['url' => $this->resolveHtmlUrl($url), 'title' => $this->attribute($token, 'title')], $children);
        }

        if ($name === 'img') {
            $this->index++;
            $alt = $this->attribute($token, 'alt');
            $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
            $attrs = $this->pandocAttrs($token, ['src', 'alt', 'title']);
            $attrs['url'] = $this->resolveHtmlUrl($this->attribute($token, 'src'));
            $attrs['alt'] = $alt;
            $title = $this->attribute($token, 'title');
            if ($title !== '') {
                $attrs['title'] = $title;
            }

            return [new AstNode('image', $attrs, $children)];
        }

        if ($name === 'input') {
            $this->index++;
            if ($this->listItemDepth > 0 && strtolower($this->attribute($token, 'type')) === 'checkbox') {
                return [
                    new AstNode('text', ['text' => $this->hasAttribute($token, 'checked') ? "\u{2612}" : "\u{2610}"]),
                    new AstNode('text', ['text' => ' ']),
                ];
            }

            return [];
        }

        if (in_array($name, ['area', 'base', 'col', 'embed', 'link', 'meta', 'param', 'source', 'track'], true)) {
            $this->index++;
            $this->consumeClose($name);
            if ($this->rawHtmlEnabled()) {
                return [$this->rawHtmlInline($this->renderOpenToken($token))];
            }

            return [];
        }

        if ($name === 'bdo') {
            $this->index++;
            $children = $this->parseInlinesUntil(['bdo'], trimBoundary: false);
            $this->consumeClose('bdo');
            $dir = strtolower(trim($this->attribute($token, 'dir')));
            if ($dir === 'rtl' || $dir === 'ltr') {
                return $this->inlineWrapperWithBoundaryWhitespace('span', ['attributes' => ['dir' => $dir]], $children);
            }

            return $this->normalizeInlineText($children);
        }

        if ($name === 'cite' && $this->hasAnyAttribute($token)) {
            $this->index++;
            $children = $this->parseInlinesUntil(['cite']);
            $this->consumeClose('cite');

            return [
                $this->rawHtmlInline($this->renderOpenToken($token)),
                ...$children,
                $this->rawHtmlInline('</cite>'),
            ];
        }

        if ($name === 'button') {
            $this->index++;
            $children = $this->parseInlinesUntil(['button']);
            $this->consumeClose('button');
            if (!$this->rawHtmlEnabled()) {
                return $children;
            }

            return [
                $this->rawHtmlInline($this->renderOpenToken($token)),
                ...$children,
                $this->rawHtmlInline('</button>'),
            ];
        }

        if ($name === 'wbr') {
            $this->index++;

            return [$this->rawHtmlInline($this->renderOpenToken($token))];
        }

        if (in_array($name, ['applet', 'map'], true) && $this->rawHtmlEnabled()) {
            return $this->parseRawInlineWrapper($token);
        }

        if ($name === 'svg') {
            return [$this->rawHtmlInline($this->renderBalancedInlineSource('svg'))];
        }

        if ($name === 'math') {
            return $this->parseMathElementInline($token);
        }

        if ($name === 'span') {
            if ($this->hasAnyClass($token, ['mjx-chtml', 'katex-html', 'MathJax_Preview', 'MathJax_CHTML'])) {
                $this->index++;
                $this->skipBalancedElement('span');

                return [];
            }
            if ($this->hasAnyClass($token, ['MJX_Assistive_MathML'])) {
                $this->index++;
                $children = $this->parseInlinesUntil(['span']);
                $this->consumeClose('span');

                return $children;
            }
            $this->index++;
            $children = $this->parseInlinesUntil(['span'], trimBoundary: false);
            $this->consumeClose('span');
            if ($this->isSmallCapsSpan($token)) {
                return $this->inlineWrapperWithBoundaryWhitespace('small_caps', [], $children);
            }
            $attrs = $this->pandocAttrs($token);
            if ($attrs !== []) {
                return $this->inlineWrapperWithBoundaryWhitespace('span', $attrs, $children);
            }

            return $this->normalizeInlineText($children);
        }

        if ($name === 'small') {
            $this->index++;
            $children = $this->parseInlinesUntil(['small'], trimBoundary: false);
            $this->consumeClose('small');

            return $this->inlineWrapperWithBoundaryWhitespace('span', ['classes' => ['small']], $children);
        }

        if ($name === 'mark' || $name === 'abbr' || $name === 'dfn') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace('span', $this->withPrependedClass($this->pandocAttrs($token), $name), $children);
        }

        if ($name === 'q') {
            return $this->parseQuotedInline($token);
        }

        if ($name === 'sub' || $name === 'sup') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace($name === 'sub' ? 'subscript' : 'superscript', $this->pandocAttrs($token), $children);
        }

        if ($name === 'u' || $name === 'ins') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace('underline', $this->pandocAttrs($token, ['cite', 'datetime']), $children);
        }

        if ($name === 's' || $name === 'del' || $name === 'strike') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], trimBoundary: false);
            $this->consumeClose($name);

            return $this->inlineWrapperWithBoundaryWhitespace('strikeout', $this->pandocAttrs($token, ['cite', 'datetime']), $children);
        }

        if ($name === 'script') {
            $this->index++;
            $text = $this->collectTextUntilClose('script');
            $this->consumeClose('script');
            $math = $this->mathNodeFromScriptText($token, $text);
            if ($math instanceof AstNode) {
                return [$math];
            }
            if (!$this->rawHtmlEnabled()) {
                return [];
            }

            return [$this->rawHtmlInline($this->renderRawTextElement($token, $text))];
        }

        if ($name === 'style') {
            $this->index++;
            $text = $this->collectTextUntilClose('style');
            $this->consumeClose('style');

            return [$this->rawHtmlInline($this->renderRawTextElement($token, $text))];
        }

        if (in_array($name, ['cite', 'label', 'time', 'output'], true)) {
            $this->index++;
            $children = $this->parseInlinesUntil([$name]);
            $this->consumeClose($name);

            return $children;
        }

        $this->index++;
        $children = $this->parseInlinesUntil([$name]);
        $this->consumeClose($name);

        return $children;
    }

    private function current(): ?TagSoupTag
    {
        if ($this->index >= $this->tokenEnd) {
            return null;
        }

        return $this->tokens?->tokenAt($this->index);
    }

    private function skipIgnorable(TagSoupTag $token): bool
    {
        if (in_array($token->type, [TagSoupTag::POSITION, TagSoupTag::WARNING], true)) {
            $this->index++;
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function classes(TagSoupTag $tag): array
    {
        return preg_split('/\s+/', trim($this->attribute($tag, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param list<string> $classes
     */
    private function hasAnyClass(TagSoupTag $tag, array $classes): bool
    {
        return array_intersect($this->classes($tag), $classes) !== [];
    }

    private function isTitlepageElement(TagSoupTag $tag): bool
    {
        foreach (['type', 'epub:type'] as $name) {
            $tokens = preg_split('/\s+/', trim($this->attribute($tag, $name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (in_array('titlepage', $tokens, true)) {
                return true;
            }
        }

        return in_array('titlepage', $this->classes($tag), true);
    }

    private function isFootnoteReference(TagSoupTag $tag): bool
    {
        return $tag->name === 'a'
            && strtolower(trim($this->attribute($tag, 'role'))) === 'doc-noteref'
            && str_starts_with(trim($this->attribute($tag, 'href')), '#');
    }

    private function isFootnoteContainer(TagSoupTag $tag): bool
    {
        return strtolower(trim($this->attribute($tag, 'role'))) === 'doc-endnotes'
            || in_array('footnotes', $this->classes($tag), true);
    }

    private function pushContainerId(TagSoupTag $tag): bool
    {
        $id = trim($this->attribute($tag, 'id'));
        if ($id === '') {
            return false;
        }

        $this->containerIdStack[] = $id;

        return true;
    }

    private function consumeClose(string $name): void
    {
        $token = $this->current();
        if ($token instanceof TagSoupTag && $token->type === TagSoupTag::CLOSE && $token->name === $name) {
            $this->index++;
        }
    }

    private function skipUntilClose(string $name): void
    {
        while (($token = $this->current()) instanceof TagSoupTag) {
            $this->index++;
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                return;
            }
        }
    }

    private function skipBalancedElement(string $name): void
    {
        $depth = 1;
        while (($token = $this->current()) instanceof TagSoupTag) {
            $this->index++;
            if ($token->type === TagSoupTag::OPEN && $token->name === $name) {
                $depth++;
            } elseif ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                $depth--;
                if ($depth <= 0) {
                    return;
                }
            }
        }
    }

    private function collectTextUntilClose(string $name): string
    {
        $text = '';
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $text .= $token->text;
            }
            $this->index++;
        }

        return $text;
    }

    private function renderBalancedTokenSource(string $name): string
    {
        $depth = 0;
        $tokens = [];
        while (($token = $this->current()) instanceof TagSoupTag) {
            $tokens[] = $token;
            if ($token->type === TagSoupTag::OPEN && $token->name === $name) {
                $depth++;
            } elseif ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                $depth--;
                $this->index++;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }
            $this->index++;
        }

        return (new TagSoupRenderer())->render($tokens);
    }

    private function firstBaseHref(TagSoupTokenStream $tokens): ?string
    {
        $insideHead = false;
        $count = count($tokens);
        for ($index = 0; $index < $count; ++$index) {
            $type = $tokens->typeAt($index);
            $name = $tokens->nameAt($index);
            if ($type === TagSoupTag::OPEN && $name === 'head') {
                $insideHead = true;
                continue;
            }
            if ($type === TagSoupTag::CLOSE && $name === 'head') {
                return null;
            }
            if ($type === TagSoupTag::OPEN && $name === 'body') {
                return null;
            }
            if ($type === TagSoupTag::OPEN && $name === 'base' && ($insideHead || $index === 0)) {
                $href = trim($tokens->attributeAt($index, 'href'));
                return $href === '' ? null : $href;
            }
        }

        return null;
    }

    private function resolveHtmlUrl(string $url): string
    {
        return HtmlResourceUrlResolver::resolve($url, $this->htmlBaseHref) ?? $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMetadataFromTokens(TagSoupTokenStream $tokens): array
    {
        $meta = [];
        $insideHead = false;
        $titleDepth = 0;
        $titleText = '';

        $count = count($tokens);
        for ($index = 0; $index < $count; ++$index) {
            $type = $tokens->typeAt($index);
            $name = $tokens->nameAt($index);

            if ($type === TagSoupTag::OPEN && ($name === 'html' || $name === 'body')) {
                $lang = $this->tokenLanguage($tokens, $index);
                if ($lang !== '') {
                    $meta['lang'] = $lang;
                }
            }

            if ($insideHead && $titleDepth > 0) {
                if ($type === TagSoupTag::TEXT) {
                    $titleText .= $tokens->textAt($index) ?? '';
                    continue;
                }
                if ($type === TagSoupTag::OPEN && $name === 'title') {
                    ++$titleDepth;
                    continue;
                }
                if ($type === TagSoupTag::CLOSE && $name === 'title') {
                    --$titleDepth;
                    if ($titleDepth <= 0) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleText) ?? $titleText);
                        if ($title !== '') {
                            $meta['title'] = $title;
                        }
                        $titleText = '';
                    }
                    continue;
                }
            }

            if ($type === TagSoupTag::OPEN && $name === 'head') {
                $insideHead = true;
                continue;
            }
            if ($type === TagSoupTag::CLOSE && $name === 'head') {
                $insideHead = false;
                continue;
            }
            if (!$insideHead || $type !== TagSoupTag::OPEN) {
                continue;
            }

            if ($name === 'title') {
                $titleDepth = 1;
                $titleText = '';
                continue;
            }

            if ($name === 'meta') {
                $metaName = $tokens->attributeAt($index, 'name');
                if ($metaName !== '') {
                    $this->appendDocumentMetadataField($meta, $metaName, $tokens->attributeAt($index, 'content'));
                }
            }
        }

        return $meta;
    }

    private function tagLanguage(TagSoupTag $token): string
    {
        $lang = $this->attribute($token, 'lang');
        if ($lang !== '') {
            return $lang;
        }

        return $this->attribute($token, 'xml:lang');
    }

    private function tokenLanguage(TagSoupTokenStream $tokens, int $index): string
    {
        $lang = $tokens->attributeAt($index, 'lang');
        if ($lang !== '') {
            return $lang;
        }

        return $tokens->attributeAt($index, 'xml:lang');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function appendDocumentMetadataField(array &$meta, string $name, string $content): void
    {
        if (!array_key_exists($name, $meta)) {
            $meta[$name] = $content;
            return;
        }

        $existing = $meta[$name];
        if (is_array($existing) && array_is_list($existing)) {
            $existing[] = $content;
            $meta[$name] = $existing;
            return;
        }

        $meta[$name] = [$existing, $content];
    }

    private function rawHtmlInline(string $html): AstNode
    {
        return new AstNode('raw_html_inline', [
            'format' => 'html',
            'html' => $html,
            'text' => $html,
        ]);
    }

    private function renderOpenToken(TagSoupTag $token): string
    {
        return (new TagSoupRenderer())->render([$token]);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function firstMainElementRange(TagSoupTokenStream $tokens): array
    {
        $count = count($tokens);
        for ($index = 0; $index < $count; ++$index) {
            if ($tokens->typeAt($index) === TagSoupTag::OPEN && $tokens->nameAt($index) === 'main') {
                $token = $tokens->tokenAt($index);
                if ($this->isTitlepageElement($token)) {
                    continue;
                }

                return [$index, $this->tokenStreamElementEnd($tokens, $index, 'main', $count)];
            }
        }

        return [0, $count];
    }

    private function tokenStream(): TagSoupTokenStream
    {
        if (!$this->tokens instanceof TagSoupTokenStream) {
            throw new \LogicException('The tag-soup token stream is not initialized.');
        }

        return $this->tokens;
    }

    private function balancedTokenStreamEnd(int $start, string $name): int
    {
        return $this->tokenStreamElementEnd($this->tokenStream(), $start, $name, $this->tokenEnd);
    }

    private function tokenStreamElementEnd(TagSoupTokenStream $tokens, int $start, string $name, int $limit): int
    {
        $depth = 0;
        for ($index = $start; $index < $limit; ++$index) {
            $type = $tokens->typeAt($index);
            if ($type === TagSoupTag::OPEN && $tokens->nameAt($index) === $name) {
                ++$depth;
            } elseif ($type === TagSoupTag::CLOSE && $tokens->nameAt($index) === $name) {
                --$depth;
                if ($depth <= 0) {
                    return $index + 1;
                }
            }
        }

        return $limit;
    }

    /**
     * @return list<AstNode>
     */
    private function flattenPlainMainChildren(array $children): array
    {
        if (count($children) === 1 && in_array($children[0]->type, ['paragraph', 'plain'], true)) {
            return $children[0]->children;
        }

        return $children;
    }

    /**
     * @return list<AstNode>
     */
    private function flattenSingleParagraphBlock(array $children): array
    {
        if (count($children) === 1 && $children[0]->type === 'paragraph') {
            return $children[0]->children;
        }

        return $children;
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeContainerAttrs(TagSoupTag $token): array
    {
        $attrs = $this->pandocAttrs($token);
        if ($token->name === 'main') {
            $attributes = is_array($attrs['attributes'] ?? null) ? $attrs['attributes'] : [];
            if ($attrs !== [] && !array_key_exists('role', $attributes)) {
                $attributes = ['role' => 'main'] + $attributes;
                $attrs['attributes'] = $attributes;
            }

            return $attrs;
        }

        return $this->withPrependedClass($attrs, $token->name);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function withPrependedClass(array $attrs, string $class): array
    {
        $classes = is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [];
        $classes = array_values(array_filter($classes, 'is_string'));
        if (!in_array($class, $classes, true)) {
            array_unshift($classes, $class);
        }
        $attrs['classes'] = $classes;

        return $attrs;
    }

    private function parseQuotedInline(TagSoupTag $quote): array
    {
        $kind = $this->quoteDepth % 2 === 0 ? 'double' : 'single';
        $this->quoteDepth++;
        $this->index++;
        $children = $this->parseInlinesUntil(['q']);
        $this->consumeClose('q');
        $this->quoteDepth--;

        $cite = trim($this->attribute($quote, 'cite'));
        if ($cite !== '') {
            $children = [
                new AstNode('span', ['attributes' => ['cite' => $this->resolveHtmlUrl($cite)]], $children),
            ];
        }

        return [new AstNode('quoted', ['kind' => $kind], $children)];
    }

    /**
     * @return list<AstNode>
     */
    private function parseMathElementInline(TagSoupTag $math): array
    {
        $end = $this->balancedTokenStreamEnd($this->index, 'math');
        $tokens = $this->tokenStream()->slice($this->index, $end - $this->index);
        $this->index = $end;
        $annotation = $this->texAnnotationFromTokens($tokens);
        if ($annotation !== null) {
            return [new AstNode('math', [
                'display' => strtolower($this->attribute($math, 'display')) === 'block',
                'text' => $annotation,
            ])];
        }
        $mathMlTex = (new MathMlToTexReader())->texFromString((new TagSoupRenderer())->render($tokens));
        if ($mathMlTex !== null) {
            return [new AstNode('math', [
                'display' => strtolower($this->attribute($math, 'display')) === 'block',
                'text' => $mathMlTex,
            ])];
        }

        $text = $this->plainTextFromTokens($tokens, ['annotation']);
        $children = $text === '' ? [] : [new AstNode('text', ['text' => $text])];

        return [new AstNode('span', $this->withPrependedClass($this->pandocAttrs($math), 'math'), $children)];
    }

    /**
     * @param list<TagSoupTag> $tokens
     */
    private function texAnnotationFromTokens(array $tokens): ?string
    {
        $depth = 0;
        $text = '';
        foreach ($tokens as $token) {
            if (!$token instanceof TagSoupTag) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'annotation') {
                if (str_contains(strtolower($this->attribute($token, 'encoding')), 'tex')) {
                    $depth = 1;
                    $text = '';
                }
                continue;
            }
            if ($depth > 0) {
                if ($token->type === TagSoupTag::OPEN) {
                    $depth++;
                    continue;
                }
                if ($token->type === TagSoupTag::CLOSE) {
                    $depth--;
                    if ($depth === 0) {
                        $trimmed = trim($text);
                        if ($trimmed !== '') {
                            return $trimmed;
                        }
                    }
                    continue;
                }
                if ($token->type === TagSoupTag::TEXT) {
                    $text .= $token->text;
                }
            }
        }

        return null;
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @param list<string> $skipElementNames
     */
    private function plainTextFromTokens(array $tokens, array $skipElementNames = []): string
    {
        $skipDepth = 0;
        $text = '';
        foreach ($tokens as $token) {
            if (!$token instanceof TagSoupTag) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && in_array($token->name, $skipElementNames, true)) {
                $skipDepth = 1;
                continue;
            }
            if ($skipDepth > 0) {
                if ($token->type === TagSoupTag::OPEN) {
                    $skipDepth++;
                } elseif ($token->type === TagSoupTag::CLOSE) {
                    $skipDepth--;
                }
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $text .= $token->text;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function parsePreBlock(TagSoupTag $pre): AstNode
    {
        $this->index++;
        $leading = '';
        while (($token = $this->current()) instanceof TagSoupTag && $token->type === TagSoupTag::TEXT && trim($token->text) === '') {
            $leading .= $token->text;
            $this->index++;
        }

        $code = null;
        if (($token = $this->current()) instanceof TagSoupTag && $token->type === TagSoupTag::OPEN && $token->name === 'code') {
            $code = $token;
            $this->index++;
            $text = $this->collectPreTextUntilClose('code');
            $this->consumeClose('code');
            $this->skipUntilClose('pre');
        } else {
            $text = $leading . $this->collectPreTextUntilClose('pre');
        }
        $this->consumeClose('pre');

        return new AstNode('code_block', $this->codeBlockAttrs($pre, $code) + ['text' => rtrim($text, "\n")]);
    }

    private function parseRawTextBlock(TagSoupTag $token): AstNode|array|null
    {
        $this->index++;
        $text = $this->collectTextUntilClose($token->name);
        $this->consumeClose($token->name);
        if ($token->name === 'script') {
            $math = $this->mathNodeFromScriptText($token, $text);
            if ($math instanceof AstNode) {
                return new AstNode('paragraph', ['text' => $math->attr('text', '')], [$math]);
            }
            if (!$this->rawHtmlEnabled()) {
                return [];
            }

            $raw = $this->renderRawTextElement($token, $text);
            return new AstNode('raw_html', ['format' => 'html', 'html' => $raw, 'text' => $raw]);
        }

        if ($token->name === 'style') {
            $raw = $this->renderRawTextElement($token, $text);
            return new AstNode('paragraph', [], [$this->rawHtmlInline($raw)]);
        }

        if ($token->name === 'xmp') {
            return $this->readFallbackHtmlBlocks($text);
        }

        if (!$this->rawHtmlEnabled()) {
            return [];
        }

        $raw = $this->renderRawTextElement($token, $text);
        return new AstNode('raw_html', ['format' => 'html', 'html' => $raw, 'text' => $raw]);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineFallbackBlocks(array $inlines, bool $topLevelInlineFragment = false, bool $forceParagraph = false): array
    {
        if (
            count($inlines) >= 2
            && $inlines[0]->type === 'linebreak'
            && $inlines[1]->type === 'linebreak'
        ) {
            return [
                new AstNode('paragraph', [], [$inlines[0]]),
                AstNode::withTextFromChildren('paragraph', [], array_slice($inlines, 1)),
            ];
        }
        if ($topLevelInlineFragment && !$this->containsRawHtmlInline($inlines)) {
            return $inlines;
        }

        $type = $forceParagraph ? 'paragraph' : $this->inlineFallbackBlockType($inlines);
        $attrs = $type === 'paragraph' ? ['text' => $this->plainTextFromInlines($inlines)] : [];

        return [new AstNode($type, $attrs, $inlines)];
    }

    private function collectPreTextUntilClose(string $name): string
    {
        $text = '';
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $text .= $token->text;
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'br') {
                $text .= "\n";
                $this->index++;
                $this->consumeClose('br');
                continue;
            }
            $this->index++;
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function codeBlockAttrs(TagSoupTag $pre, ?TagSoupTag $code): array
    {
        $preAttrs = $this->pandocAttrs($pre);
        if ($preAttrs !== [] || !$code instanceof TagSoupTag) {
            return $preAttrs;
        }

        $attrs = $this->pandocAttrs($code);
        if (isset($attrs['classes']) && is_array($attrs['classes'])) {
            $attrs['classes'] = array_values(array_map(
                static fn (mixed $class): string => is_string($class) && str_starts_with($class, 'language-')
                    ? substr($class, 9)
                    : (string) $class,
                $attrs['classes']
            ));
        }

        return $attrs;
    }

    private function rawHtmlEnabled(): bool
    {
        return ($this->options['htmlRawHtml'] ?? false) !== false;
    }

    private function mathNodeFromScriptText(TagSoupTag $token, string $text): ?AstNode
    {
        $type = strtolower($this->attribute($token, 'type'));
        if (!str_starts_with($type, 'math/tex')) {
            return null;
        }

        return new AstNode('math', [
            'display' => str_contains($type, 'mode=display'),
            'text' => $text,
        ]);
    }

    private function renderRawTextElement(TagSoupTag $token, string $text): string
    {
        return (new TagSoupRenderer())->render([
            $token,
            TagSoupTag::text($text),
            TagSoupTag::close($token->name),
        ]);
    }

    /**
     * @param list<string> $outerStopTags
     * @return list<AstNode>
     */
    private function parseTopLevelMediaFallback(TagSoupTag $token, array $outerStopTags): array
    {
        $this->index++;
        $children = $this->parseInlinesUntil([$token->name]);
        $this->consumeClose($token->name);
        $tail = $this->parseInlinesUntil($outerStopTags, stopBeforeBlock: true);

        return [...$children, ...$tail];
    }

    /**
     * @param list<string> $outerStopTags
     * @return list<AstNode>
     */
    private function parseButtonScopeBlock(array $outerStopTags): array
    {
        $this->index++;
        while (($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'button') {
                $this->index++;
                return [];
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'p') {
                $this->index++;
                $inlines = $this->parseInlinesUntil(['button', 'p', ...$outerStopTags], stopBeforeBlock: true);
                $this->consumeClose('p');
                $this->consumeClose('button');
                $tail = $this->parseInlinesUntil($outerStopTags, stopBeforeBlock: true);
                $inlines = [...$inlines, ...$tail];

                return $inlines === []
                    ? []
                    : [AstNode::withTextFromChildren('paragraph', [], $inlines)];
            }
            $this->index++;
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function parseRawInlineWrapper(TagSoupTag $token): array
    {
        $this->index++;
        $children = $this->parseInlinesUntil([$token->name]);
        $this->consumeClose($token->name);

        return [
            $this->rawHtmlInline($this->renderOpenToken($token)),
            ...$children,
            $this->rawHtmlInline('</' . $token->name . '>'),
        ];
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<string> $stopTags
     * @return array{0:string,1:string}|null
     */
    private function topLevelInlineTextBoundary(string $text, array $inlines, array $stopTags, bool $stopBeforeBlock): ?array
    {
        if (!$stopBeforeBlock || $inlines === [] || $stopTags !== [] || !str_contains($text, "\n")) {
            return null;
        }

        $position = strcspn($text, "\r\n");
        $before = substr($text, 0, $position);
        $after = substr($text, $position);
        $after = ltrim($after, "\r\n\t ");
        if ($after === '' && !$this->hasMeaningfulTokenAfterCurrentTextBoundary()) {
            return null;
        }
        $this->inlineRunEndedAtTextBoundary = true;

        return [$before, $after];
    }

    private function hasMeaningfulTokenAfterCurrentTextBoundary(): bool
    {
        $stream = $this->tokenStream();
        for ($index = $this->index + 1; $index < $this->tokenEnd; ++$index) {
            $type = $stream->typeAt($index);
            if ($type === TagSoupTag::TEXT && trim($stream->textAt($index) ?? '') === '') {
                continue;
            }
            if (in_array($type, [TagSoupTag::POSITION, TagSoupTag::WARNING, TagSoupTag::COMMENT], true)) {
                continue;
            }

            return $type !== TagSoupTag::CLOSE;
        }

        return false;
    }

    private function renderBalancedInlineSource(string $name): string
    {
        $raw = $this->renderBalancedTokenSource($name);

        return $raw;
    }

    /**
     * @param list<string> $outerStopTags
     * @return list<AstNode>
     */
    private function parseRawInlineWrapperBlock(TagSoupTag $token, array $outerStopTags): array
    {
        $this->index++;
        $children = $this->parseInlinesUntil([$token->name]);
        $this->consumeClose($token->name);
        $preserveLeadingSpace = false;
        $next = $this->current();
        if ($next instanceof TagSoupTag && $next->type === TagSoupTag::TEXT && preg_match('/^\s/', $next->text) === 1) {
            $preserveLeadingSpace = true;
        }
        $tail = $this->parseInlinesUntil($outerStopTags, stopBeforeBlock: true);
        if ($preserveLeadingSpace && $tail !== [] && $tail[0]->type === 'text') {
            $text = (string) $tail[0]->attr('text', '');
            if ($text !== '' && preg_match('/^\s/', $text) !== 1) {
                $tail[0] = new AstNode('text', ['text' => ' ' . $text]);
            }
        }

        return $this->inlineFallbackBlocks([
            $this->rawHtmlInline($this->renderOpenToken($token)),
            ...$children,
            $this->rawHtmlInline('</' . $token->name . '>'),
            ...$tail,
        ]);
    }

    private function rawDisabledScriptBreaksParagraph(TagSoupTag $token): bool
    {
        return $token->name === 'script'
            && !$this->rawHtmlEnabled()
            && !str_starts_with(strtolower($this->attribute($token, 'type')), 'math/tex');
    }

    private function balancedElementHasBlockChild(string $name): bool
    {
        $depth = 0;
        $stream = $this->tokenStream();
        for ($index = $this->index; $index < $this->tokenEnd; ++$index) {
            $type = $stream->typeAt($index);
            $tokenName = $stream->nameAt($index);
            if ($type === TagSoupTag::OPEN && $tokenName === $name) {
                ++$depth;
                continue;
            }
            if ($type === TagSoupTag::CLOSE && $tokenName === $name) {
                --$depth;
                if ($depth <= 0) {
                    return false;
                }
                continue;
            }
            if ($depth === 1 && $type === TagSoupTag::OPEN && $this->isBlockElement($tokenName ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function balancedElementContainsOpenTag(string $name, string $target): bool
    {
        $depth = 0;
        $stream = $this->tokenStream();
        for ($index = $this->index; $index < $this->tokenEnd; ++$index) {
            $type = $stream->typeAt($index);
            $tokenName = $stream->nameAt($index);
            if ($type === TagSoupTag::OPEN && $tokenName === $name) {
                ++$depth;
                continue;
            }
            if ($type === TagSoupTag::CLOSE && $tokenName === $name) {
                --$depth;
                if ($depth <= 0) {
                    return false;
                }
                continue;
            }
            if ($depth > 0 && $type === TagSoupTag::OPEN && $tokenName === $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AstNode>
     */
    private function readFallbackHtmlBlocks(string $html): array
    {
        $document = (new self($this->options))->read(trim($html));

        return $document->children;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function containsRawHtmlInline(array $inlines): bool
    {
        foreach ($inlines as $inline) {
            if ($inline->type === 'raw_html_inline' || $this->containsRawHtmlInline($inline->children)) {
                return true;
            }
        }

        return false;
    }

    private function htmlHeadingIdentifier(string $text): string
    {
        $identifier = strtolower($text);
        $identifier = preg_replace('/[^a-z0-9]+/', '-', $identifier) ?? '';

        return trim($identifier, '-');
    }

    private function isSmallCapsSpan(TagSoupTag $token): bool
    {
        $classes = $this->classes($token);
        if (array_intersect($classes, ['smallcaps', 'small-caps']) !== []) {
            return true;
        }

        return preg_match('/font-variant(?:-caps)?\s*:\s*small-caps/i', $this->attribute($token, 'style')) === 1;
    }

    /**
     * @param list<string> $stopTags
     */
    private function openTagImpliesClose(string $openName, array $stopTags): bool
    {
        if (in_array('li', $stopTags, true) && $openName === 'li') {
            return true;
        }
        if ((in_array('dt', $stopTags, true) || in_array('dd', $stopTags, true)) && in_array($openName, ['dt', 'dd'], true)) {
            return true;
        }
        if (in_array('p', $stopTags, true) && $this->isBlockElement($openName)) {
            return true;
        }
        foreach ($stopTags as $stopTag) {
            if (preg_match('/^h[1-6]$/', $stopTag) === 1 && preg_match('/^h[1-6]$/', $openName) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function listItemBlocks(array $blocks, bool $explicitParagraph): array
    {
        if (!$explicitParagraph && count($blocks) === 1 && $blocks[0]->type === 'paragraph') {
            return [new AstNode('plain', $blocks[0]->attrs, $blocks[0]->children)];
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function applyListItemId(TagSoupTag $listItem, array $blocks, bool $explicitParagraph): array
    {
        $attrs = $this->pandocAttrs($listItem);
        if ($attrs === []) {
            return $this->normalizeImplicitListItemFirstParagraph($blocks, $explicitParagraph);
        }

        if ($explicitParagraph) {
            return [new AstNode('div', $attrs, $blocks)];
        }

        if ($blocks !== [] && $blocks[0]->type === 'paragraph') {
            $blocks[0] = new AstNode('plain', [], $blocks[0]->children);
        }

        if ($blocks !== [] && $blocks[0]->type === 'plain') {
            $blocks[0] = new AstNode('plain', [], [new AstNode('span', $attrs, $blocks[0]->children)]);
            return $blocks;
        }

        return [new AstNode('div', $attrs, $blocks)];
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function normalizeImplicitListItemFirstParagraph(array $blocks, bool $explicitParagraph): array
    {
        if ($explicitParagraph || $blocks === [] || $blocks[0]->type !== 'paragraph') {
            return $blocks;
        }

        $blocks[0] = new AstNode('plain', [], $blocks[0]->children);

        return $blocks;
    }

    private function attribute(TagSoupTag $tag, string $name): string
    {
        foreach ($tag->attributes as $attribute) {
            if ($attribute['name'] === $name) {
                return $attribute['value'];
            }
        }

        return '';
    }

    private function hasAttribute(TagSoupTag $tag, string $name): bool
    {
        foreach ($tag->attributes as $attribute) {
            if ($attribute['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasAnyAttribute(TagSoupTag $tag): bool
    {
        return $tag->attributes !== [];
    }

    /**
     * @param list<string> $skip
     * @return array<string, mixed>
     */
    private function pandocAttrs(TagSoupTag $tag, array $skip = []): array
    {
        $id = $this->attribute($tag, 'id');
        $classes = in_array('class', $skip, true)
            ? []
            : (preg_split('/\s+/', trim($this->attribute($tag, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $attributes = [];
        $htmlAttributes = [];
        foreach ($tag->attributes as $attribute) {
            $name = strtolower($attribute['name']);
            if (in_array($name, $skip, true)) {
                continue;
            }
            $value = trim($attribute['value']);
            if ($name === 'id') {
                if ($value !== '') {
                    $htmlAttributes['id'] = $value;
                }
                continue;
            }
            if ($name === 'class') {
                if ($classes !== []) {
                    $htmlAttributes['class'] = implode(' ', $classes);
                }
                continue;
            }

            $key = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
            if ($key === '') {
                continue;
            }
            $attributes[$key] = $value;
            $htmlAttributes[$name] = $value;
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $this->attributeMapPool()->intern($classes);
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $this->attributeMapPool()->intern($attributes);
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $this->attributeMapPool()->intern($htmlAttributes);
        }

        return $attrs;
    }

    private function attributeMapPool(): PandocHtmlAttributeMapPool
    {
        return $this->attributeMapPool ??= new PandocHtmlAttributeMapPool();
    }

    private function isBlockElement(string $name): bool
    {
        return in_array($name, [
            'caption',
            'address', 'article', 'aside', 'blockquote', 'body', 'dd', 'details', 'dialog', 'div', 'dl', 'dt',
            'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head',
            'header', 'hgroup', 'hr', 'html', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'search',
            'section', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
        ], true);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function inlineFallbackBlockType(array $inlines): string
    {
        foreach ($inlines as $inline) {
            if ($inline->type === 'text' || $inline->type === 'linebreak' || $inline->type === 'raw_html_inline') {
                return 'paragraph';
            }
        }

        return 'plain';
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function normalizeInlineText(array $inlines, bool $trimBoundary = true): array
    {
        $normalized = [];
        foreach ($inlines as $inline) {
            if ($inline->type === 'text') {
                $text = preg_replace('/\s+/', ' ', (string) $inline->attr('text', '')) ?? '';
                $lastIndex = array_key_last($normalized);
                $last = $lastIndex === null ? null : $normalized[$lastIndex];
                if ($last instanceof AstNode && $last->type === 'linebreak') {
                    $text = ltrim($text);
                }
                if ($text === '') {
                    continue;
                }
                if ($last instanceof AstNode && $last->type === 'text') {
                    $normalized[$lastIndex] = new AstNode('text', ['text' => (string) $last->attr('text', '') . $text]);
                    continue;
                }
                $normalized[] = new AstNode('text', ['text' => $text]);
                continue;
            }

            $normalized[] = $inline;
        }

        if ($trimBoundary) {
            if ($normalized !== [] && $normalized[0]->type === 'text') {
                $normalized[0] = new AstNode('text', ['text' => ltrim((string) $normalized[0]->attr('text', ''))]);
                if ($normalized[0]->attr('text', '') === '') {
                    array_shift($normalized);
                }
            }
            $lastIndex = array_key_last($normalized);
            if ($lastIndex !== null && $normalized[$lastIndex]->type === 'text') {
                $normalized[$lastIndex] = new AstNode('text', ['text' => rtrim((string) $normalized[$lastIndex]->attr('text', ''))]);
                if ($normalized[$lastIndex]->attr('text', '') === '') {
                    array_pop($normalized);
                }
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function inlineWrapperWithBoundaryWhitespace(string $type, array $attrs, array $children): array
    {
        $first = $children[0] ?? null;
        $last = $children === [] ? null : $children[array_key_last($children)];
        $hasLeadingSpace = $first instanceof AstNode
            && $first->type === 'text'
            && preg_match('/^\s/u', (string) $first->attr('text', '')) === 1;
        $hasTrailingSpace = $last instanceof AstNode
            && $last->type === 'text'
            && preg_match('/\s$/u', (string) $last->attr('text', '')) === 1;
        $result = [];
        if ($hasLeadingSpace) {
            $result[] = new AstNode('text', ['text' => ' ']);
        }
        $result[] = AstNode::withCompactTextChildren($type, $attrs, $this->normalizeInlineText($children));
        if ($hasTrailingSpace) {
            $result[] = new AstNode('text', ['text' => ' ']);
        }

        return $result;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainTextFromInlines(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            if ($inline->type === 'text' || $inline->type === 'code') {
                $text .= (string) $inline->attr('text', '');
            } elseif ($inline->type === 'linebreak') {
                $text .= "\n";
            } else {
                $text .= $this->plainTextFromInlines($inline->children);
            }
        }

        return trim(preg_replace('/[ \t\f\v]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainTextFromBlocks(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = (string) $block->attr('text', '');
            if ($text === '') {
                $text = $this->plainTextFromBlocks($block->children);
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }
}
