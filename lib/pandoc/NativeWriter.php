<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeWriter
{
    private const NATIVE_COMPARISON_PROVENANCE_ATTRS = [
        'alignmentConstructor',
        'alignmentConstructors',
        'alignmentNative',
        'alignmentNatives',
        'attrConstructor',
        'attrNative',
        'captionConstructor',
        'captionNative',
        'citationConstructor',
        'citationModeConstructor',
        'citationModeNative',
        'citationNative',
        'citationPrefixNative',
        'citationRecordsNative',
        'citationSuffixNative',
        'colSpanConstructor',
        'colSpanNative',
        'columnSpecNatives',
        'columnSpecsNative',
        'columnWidthConstructors',
        'columnWidthNatives',
        'constructor',
        'definitionDefinitionsNative',
        'definitionItemNative',
        'definitionNative',
        'definitionTermNative',
        'formatConstructor',
        'formatNative',
        'legacyTableCellBlocksNative',
        'lineNative',
        'listAttributesConstructor',
        'listAttributesNative',
        'listDelimiterConstructor',
        'listDelimiterNative',
        'listItemNative',
        'listStyleConstructor',
        'listStyleNative',
        'mathTypeConstructor',
        'mathTypeNative',
        'native',
        'nativeInlineConstructors',
        'nativeInlineParts',
        'quoteTypeConstructor',
        'quoteTypeNative',
        'rowHeadColumnsConstructor',
        'rowHeadColumnsNative',
        'rowSpanConstructor',
        'rowSpanNative',
        'shortCaptionConstructor',
        'shortCaptionMaybeConstructor',
        'shortCaptionMaybeNative',
        'shortCaptionNative',
        'headRowsNative',
        'tableBodiesNative',
        'tableCellsNative',
        'tableRowsNative',
        'targetConstructor',
        'targetNative',
    ];

    private const NATIVE_METADATA_CONSTRUCTORS = [
        'MetaString',
        'MetaBool',
        'MetaInlines',
        'MetaBlocks',
        'MetaList',
        'MetaMap',
    ];

    /**
     * @param array{standalone?: bool, blocksOnly?: bool} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Native writer expects a document node');
        }

        $blocksOnly = (bool) ($this->options['blocksOnly'] ?? false);
        $standalone = (bool) ($this->options['standalone'] ?? false);
        if (!$blocksOnly && !$standalone && $this->shouldWriteNativeJson($document)) {
            return (new PandocJsonWriter())->write($this->nativeJsonDocument($document));
        }

        $meta = $document->attr('meta', []);
        if ($blocksOnly || (!$standalone && (!is_array($meta) || $meta === []))) {
            return $this->renderBlockList($document->children, 0);
        }

        $metaNativeValues = $this->metaNativeValues($document);
        $metaProvenance = $this->metaConstructorProvenance($document);

        return 'Pandoc' . "\n"
            . '  ' . $this->renderMeta(is_array($meta) ? $meta : [], $metaNativeValues, $metaProvenance) . "\n"
            . '  ' . $this->renderBlockList($document->children, 2);
    }

    private function shouldWriteNativeJson(AstNode $document): bool
    {
        if ($document->attr('nativeFormat') === 'pandoc-json') {
            return true;
        }

        if ($document->attr('pandocApiVersion') !== null) {
            return true;
        }

        if ($document->attr('documentConstructor') === 'Pandoc') {
            return true;
        }

        if ($document->attr('nativeFormat') === 'pandoc-native-text') {
            return false;
        }

        return is_array($document->attr('documentNative'))
            || $this->hasJsonNativeProvenance($document)
            || $this->hasJsonNativeSidecar($document)
            || $this->hasMetadataRawFamily($document);
    }

    private function hasMetadataRawFamily(AstNode $document): bool
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta) || $meta === []) {
            return false;
        }

        return $this->hasRawFamilyNode($document);
    }

    private function hasRawFamilyNode(AstNode $node): bool
    {
        if ($this->rawFamilyForJsonMode($node) !== null) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->hasRawFamilyNode($child)) {
                return true;
            }
        }

        return false;
    }

    private function rawFamilyForJsonMode(AstNode $node): ?string
    {
        return match ($node->type) {
            'raw_html', 'raw_html_inline' => MarkdownFormatProfile::rawFamily($this->rawFormat($node, 'html')),
            'raw_tex', 'raw_tex_inline' => MarkdownFormatProfile::rawFamily($this->rawFormat($node, 'tex')),
            'raw_markdown' => MarkdownFormatProfile::rawFamily($this->rawFormat($node, 'markdown')),
            'raw_block', 'raw_inline' => MarkdownFormatProfile::rawFamily($this->rawFormat($node, '')),
            default => null,
        };
    }

    private function hasJsonNativeSidecar(AstNode $node): bool
    {
        if ($node->type === 'note' && $this->safeNoteLabel($node) !== null) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->hasJsonNativeSidecar($child)) {
                return true;
            }
        }

        return false;
    }

    private function safeNoteLabel(AstNode $node): ?string
    {
        $label = trim((string) $node->attr('label', ''));
        if ($label === '') {
            $label = trim((string) $node->attr('noteLabel', ''));
        }

        return $label !== '' && preg_match('/[\]\s]/u', $label) !== 1 ? $label : null;
    }

    private function hasJsonNativeProvenance(AstNode $node): bool
    {
        if ($node->type === 'note' && $this->sourceNoteLabel($node) !== null) {
            return true;
        }

        foreach ($node->attrs as $attr => $value) {
            if (in_array($attr, ['metaConstructorProvenance', 'metaNativeValues'], true)) {
                continue;
            }
            if (in_array($attr, self::NATIVE_COMPARISON_PROVENANCE_ATTRS, true) && $value !== null) {
                return true;
            }
            if ($this->valueHasJsonNativeProvenance($value)) {
                return true;
            }
        }

        foreach ($node->children as $child) {
            if ($this->hasJsonNativeProvenance($child)) {
                return true;
            }
        }

        return false;
    }

    private function sourceNoteLabel(AstNode $node): ?string
    {
        $label = trim((string) $node->attr('label', ''));
        if ($label === '' || preg_match('/[\]\s]/u', $label) === 1) {
            return null;
        }

        return $label;
    }

    private function valueHasJsonNativeProvenance(mixed $value): bool
    {
        if ($value instanceof AstNode) {
            return $this->hasJsonNativeProvenance($value);
        }

        if (!is_array($value)) {
            return false;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && in_array($value['t'], self::NATIVE_METADATA_CONSTRUCTORS, true)
        ) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->valueHasJsonNativeProvenance($item)) {
                return true;
            }
        }

        return false;
    }

    private function nativeJsonDocument(AstNode $document): AstNode
    {
        return $this->nativeJsonNode($document);
    }

    private function nativeJsonNode(AstNode $node): AstNode
    {
        $currentNativeBlock = $this->preservedCurrentNativeBlock($node);
        if ($currentNativeBlock instanceof AstNode) {
            return $currentNativeBlock;
        }

        $nativeBlock = $this->preservedLegacyNativeBlock($node);
        if ($nativeBlock instanceof AstNode) {
            return $nativeBlock;
        }

        $hasInlineChildren = $this->hasJsonNativeInlineChildren($node->type);
        $sourceChildren = $node->children;
        if ($hasInlineChildren && $sourceChildren === []) {
            $text = $node->attr('text', null);
            if (is_string($text) && $text !== '') {
                $sourceChildren = $this->textInlines($text);
            }
        }

        $children = [];
        foreach ($sourceChildren as $child) {
            if ($hasInlineChildren) {
                array_push($children, ...$this->nativeJsonInlineNodes($child));
            } else {
                $children[] = $this->nativeJsonNode($child);
            }
        }

        return new AstNode($node->type, $this->nativeJsonAttrs($node->attrs), $children);
    }

    private function preservedCurrentNativeBlock(AstNode $node): ?AstNode
    {
        $native = $node->attr('native');
        if (!is_array($native) || array_is_list($native) || !is_string($native['t'] ?? null)) {
            return null;
        }

        if ($this->hasNonCurrentNativePayload($native)) {
            return null;
        }

        foreach ($this->nativeBlockPayloadReaders($native) as $fresh) {
            if ($fresh instanceof AstNode && $this->comparisonNode($node) === $this->comparisonNode($fresh)) {
                return new AstNode('native_block', ['native' => $native]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $native
     * @return list<AstNode|null>
     */
    private function nativeBlockPayloadReaders(array $native): array
    {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$native],
        ];

        $nodes = [];
        try {
            $nodes[] = (new PandocJsonReader())->readPacket($packet)->children[0] ?? null;
        } catch (\Throwable) {
        }

        try {
            $nodes[] = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR))->children[0] ?? null;
        } catch (\Throwable) {
        }

        return $nodes;
    }

    private function preservedLegacyNativeBlock(AstNode $node): ?AstNode
    {
        $native = $node->attr('native');
        if (
            $node->type !== 'table'
            || !is_array($native)
            || array_is_list($native)
            || ($native['t'] ?? null) !== 'Table'
        ) {
            return null;
        }

        $content = $this->singleWrappedNativeTuple($native['c'] ?? null);
        if (!is_array($content) || !array_is_list($content) || count($content) !== 5) {
            return null;
        }

        try {
            $fresh = (new NativeReader())->read(json_encode([
                'pandoc-api-version' => [1, 23, 1],
                'meta' => [],
                'blocks' => [$native],
            ], JSON_THROW_ON_ERROR))->children[0] ?? null;
        } catch (\Throwable) {
            return null;
        }

        if (!$fresh instanceof AstNode || $this->comparisonNode($node) !== $this->comparisonNode($fresh)) {
            return null;
        }

        return new AstNode('native_block', ['native' => $native]);
    }

    private function singleWrappedNativeTuple(mixed $content): mixed
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            return $content[0];
        }

        return $content;
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function comparisonNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            if (in_array($key, self::NATIVE_COMPARISON_PROVENANCE_ATTRS, true)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading'], true)) {
                continue;
            }
            $attrs[$key] = $this->comparisonValue($value);
        }
        ksort($attrs);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => array_map(fn (AstNode $child): array => $this->comparisonNode($child), $node->children),
        ];
    }

    private function comparisonValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->comparisonNode($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->comparisonValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->comparisonValue($item);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @return list<AstNode>
     */
    private function nativeJsonInlineNodes(AstNode $node): array
    {
        $currentNativeInline = $this->preservedCurrentNativeInline($node);
        if ($currentNativeInline instanceof AstNode) {
            return [$currentNativeInline];
        }

        $nativeInline = $this->preservedLegacyNativeInline($node);
        if ($nativeInline instanceof AstNode) {
            return [$nativeInline];
        }

        if (
            $node->type === 'text'
            && !$this->hasDirectNativePayload($node)
            && !$this->nativeInlinePartsMatchText($node)
        ) {
            return $this->jsonNativeTextInlines((string) $node->attr('text', ''));
        }

        return [$this->nativeJsonNode($node)];
    }

    private function preservedCurrentNativeInline(AstNode $node): ?AstNode
    {
        $native = $node->attr('native');
        if (
            !in_array($node->type, ['citation', 'citation_group', 'note'], true)
            || !is_array($native)
            || array_is_list($native)
            || !in_array($native['t'] ?? null, ['Cite', 'Note'], true)
        ) {
            return null;
        }

        if ($this->hasNonCurrentNativePayload($native)) {
            return null;
        }

        $content = $this->singleWrappedNativeTuple($native['c'] ?? null);
        if (!is_array($content) || !array_is_list($content)) {
            return null;
        }
        if (($native['t'] ?? null) === 'Cite' && count($content) !== 2) {
            return null;
        }

        foreach ($this->nativeInlinePayloadReaders($native) as $fresh) {
            if ($fresh instanceof AstNode && $this->comparisonNode($node) === $this->comparisonNode($fresh)) {
                return new AstNode('native_inline', ['native' => $native]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $native
     * @return list<AstNode|null>
     */
    private function nativeInlinePayloadReaders(array $native): array
    {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Plain', 'c' => [$native]],
            ],
        ];

        $nodes = [];
        try {
            $nodes[] = (new PandocJsonReader())->readPacket($packet)->children[0]->children[0] ?? null;
        } catch (\Throwable) {
        }

        try {
            $nodes[] = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR))->children[0]->children[0] ?? null;
        } catch (\Throwable) {
        }

        return $nodes;
    }

    private function hasNonCurrentNativePayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && $this->isNullaryNativeBlockConstructor($value['t'])
            && array_key_exists('c', $value)
        ) {
            return true;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && in_array($value['t'], ['Space', 'SoftBreak', 'LineBreak'], true)
            && array_key_exists('c', $value)
        ) {
            return true;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && $this->isNullaryNativeHelperConstructor($value['t'])
            && array_key_exists('c', $value)
            && $value['c'] !== []
        ) {
            return true;
        }

        if ($this->hasLegacyTargetInlinePayload($value)) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->hasNonCurrentNativePayload($item)) {
                return true;
            }
        }

        return false;
    }

    private function hasLegacyTargetInlinePayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!array_is_list($value) && in_array($value['t'] ?? null, ['Link', 'Image'], true)) {
            $content = $this->singleWrappedNativeTuple($value['c'] ?? null);

            return is_array($content) && array_is_list($content) && count($content) === 2;
        }

        foreach ($value as $item) {
            if ($this->hasLegacyTargetInlinePayload($item)) {
                return true;
            }
        }

        return false;
    }

    private function isNullaryNativeBlockConstructor(string $constructor): bool
    {
        return in_array($constructor, ['HorizontalRule', 'Null'], true);
    }

    private function isNullaryNativeHelperConstructor(string $constructor): bool
    {
        return in_array($constructor, [
            'SingleQuote',
            'DoubleQuote',
            'InlineMath',
            'DisplayMath',
            'NormalCitation',
            'AuthorInText',
            'SuppressAuthor',
            'DefaultStyle',
            'Decimal',
            'Example',
            'LowerRoman',
            'UpperRoman',
            'LowerAlpha',
            'UpperAlpha',
            'DefaultDelim',
            'Period',
            'OneParen',
            'TwoParens',
            'AlignLeft',
            'AlignRight',
            'AlignCenter',
            'AlignDefault',
            'ColWidthDefault',
            'Nothing',
        ], true);
    }

    private function preservedLegacyNativeInline(AstNode $node): ?AstNode
    {
        $native = $node->attr('native');
        if (
            !in_array($node->type, ['link', 'image'], true)
            || !is_array($native)
            || array_is_list($native)
            || count(array_diff(array_keys($native), ['t', 'c'])) !== 0
            || !in_array($native['t'] ?? null, ['Link', 'Image'], true)
        ) {
            return null;
        }

        $content = $this->singleWrappedNativeTuple($native['c'] ?? null);
        if (!is_array($content) || !array_is_list($content) || count($content) !== 2) {
            return null;
        }

        try {
            $fresh = (new NativeReader())->read(json_encode([
                'pandoc-api-version' => [1, 23, 1],
                'meta' => [],
                'blocks' => [
                    ['t' => 'Plain', 'c' => [$native]],
                ],
            ], JSON_THROW_ON_ERROR))->children[0]->children[0] ?? null;
        } catch (\Throwable) {
            return null;
        }

        if (!$fresh instanceof AstNode || $this->comparisonNode($node) !== $this->comparisonNode($fresh)) {
            return null;
        }

        return new AstNode('native_inline', ['native' => $native]);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function nativeJsonAttrs(array $attrs): array
    {
        foreach (['prefix', 'suffix', 'citationSourceInlines', 'captionInlines', 'shortCaptionInlines'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key]) && array_is_list($attrs[$key]) && $this->allAstNodes($attrs[$key])) {
                $expanded = [];
                foreach ($attrs[$key] as $child) {
                    array_push($expanded, ...$this->nativeJsonInlineNodes($child));
                }
                $attrs[$key] = $expanded;
            }
        }

        foreach (['captionBlocks', 'shortCaptionBlocks'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key]) && array_is_list($attrs[$key]) && $this->allAstNodes($attrs[$key])) {
                $attrs[$key] = array_map(fn (AstNode $child): AstNode => $this->nativeJsonNode($child), $attrs[$key]);
            }
        }

        return $attrs;
    }

    private function hasNativeInlineParts(AstNode $node): bool
    {
        $parts = $node->attr('nativeInlineParts', []);

        return is_array($parts) && array_is_list($parts) && $parts !== [];
    }

    private function hasDirectNativePayload(AstNode $node): bool
    {
        $native = $node->attr('native');

        return is_array($native) && !array_is_list($native);
    }

    private function nativeInlinePartsMatchText(AstNode $node): bool
    {
        $parts = $node->attr('nativeInlineParts', []);
        if (!is_array($parts) || !array_is_list($parts) || $parts === []) {
            return false;
        }

        $text = '';
        foreach ($parts as $part) {
            if (!is_array($part) || array_is_list($part) || !is_string($part['t'] ?? null)) {
                return false;
            }

            if ($part['t'] === 'Str') {
                $content = $this->nativeStringContent($part['c'] ?? null);
                if ($content === null) {
                    return false;
                }
                $text .= $content;
                continue;
            }

            if ($part['t'] === 'Space') {
                if (array_key_exists('c', $part)) {
                    return false;
                }
                $text .= ' ';
                continue;
            }

            if ($part['t'] === 'SoftBreak' || $part['t'] === 'LineBreak') {
                if (array_key_exists('c', $part)) {
                    return false;
                }
                $text .= ' ';
                continue;
            }

            return false;
        }

        return $text === (string) $node->attr('text', '');
    }

    private function nativeStringContent(mixed $content): ?string
    {
        while (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        return is_string($content) ? $content : null;
    }

    private function hasJsonNativeInlineChildren(string $type): bool
    {
        return in_array($type, [
            'plain',
            'paragraph',
            'heading',
            'definition_term',
            'term',
            'line',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'link',
            'image',
            'span',
            'citation',
        ], true);
    }

    /**
     * @return list<AstNode>
     */
    private function jsonNativeTextInlines(string $text): array
    {
        if ($text === '') {
            return [new AstNode('text', ['text' => ''])];
        }

        $parts = preg_split('/([ \t]+|\n)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [new AstNode('text', ['text' => $text])];
        }

        $inlines = [];
        foreach ($parts as $part) {
            if ($part === "\n") {
                $inlines[] = new AstNode('softbreak');
                continue;
            }
            if (preg_match('/^[ \t]+$/u', $part) === 1) {
                foreach (str_split($part) as $_) {
                    $inlines[] = new AstNode('space');
                }
                continue;
            }
            $inlines[] = new AstNode('text', ['text' => $part]);
        }

        return $inlines;
    }

    /**
     * @param array<mixed> $values
     */
    private function allAstNodes(array $values): bool
    {
        foreach ($values as $value) {
            if (!$value instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $nativeValues
     * @param array<string, mixed> $provenance
     */
    private function renderMeta(array $meta, array $nativeValues = [], array $provenance = []): string
    {
        $entries = $this->normalizedMetaEntries($meta);
        if ($entries === []) {
            return 'Meta { unMeta = fromList [] }';
        }

        $pairs = [];
        foreach ($entries as $key => $value) {
            $fieldPath = [$key];
            $sourceNative = $nativeValues[$key] ?? $this->metaProvenanceNative($provenance, $fieldPath);
            $pairs[] = '( ' . $this->quote($key) . ' , ' . $this->renderMetaValue($value, $sourceNative, $provenance, $fieldPath) . ' )';
        }

        return 'Meta { unMeta = fromList [ ' . implode(' , ', $pairs) . ' ] }';
    }

    /**
     * @return array<string, mixed>
     */
    private function metaNativeValues(AstNode $document): array
    {
        $values = $document->attr('metaNativeValues', []);

        return is_array($values) && !array_is_list($values) ? $values : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaConstructorProvenance(AstNode $document): array
    {
        $provenance = $document->attr('metaConstructorProvenance', []);

        return is_array($provenance) && !array_is_list($provenance) ? $provenance : [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function normalizedMetaEntries(array $meta): array
    {
        $entries = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, ['titleInlines', 'authorInlines', 'dateInlines', 'authors', 'abstractBlocks'], true)) {
                continue;
            }

            $entries[(string) $key] = $value;
        }

        if (isset($meta['titleInlines']) && is_array($meta['titleInlines'])) {
            $entries['title'] = ['type' => 'MetaInlines', 'value' => $meta['titleInlines']];
        } elseif (isset($meta['title'])) {
            $entries['title'] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($meta['title']))];
        }

        if (isset($meta['authorInlines']) && is_array($meta['authorInlines'])) {
            $authors = [];
            foreach ($meta['authorInlines'] as $author) {
                if (is_array($author)) {
                    $authors[] = ['type' => 'MetaInlines', 'value' => $author];
                }
            }
            if ($authors !== []) {
                $entries['author'] = ['type' => 'MetaList', 'value' => $authors];
            }
        } elseif (isset($meta['author']) && is_array($meta['author']) && !isset($meta['author']['type'])) {
            $authors = [];
            foreach ($meta['author'] as $author) {
                $authors[] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($author))];
            }
            if ($authors !== []) {
                $entries['author'] = ['type' => 'MetaList', 'value' => $authors];
            }
        }

        if (isset($meta['dateInlines']) && is_array($meta['dateInlines'])) {
            $entries['date'] = ['type' => 'MetaInlines', 'value' => $meta['dateInlines']];
        } elseif (isset($meta['date'])) {
            $entries['date'] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($meta['date']))];
        }

        if (isset($meta['abstractBlocks']) && is_array($meta['abstractBlocks']) && $this->isAstNodeList($meta['abstractBlocks'])) {
            $entries['abstract'] = ['type' => 'MetaBlocks', 'value' => $meta['abstractBlocks']];
        }

        ksort($entries);

        return $entries;
    }

    private function metaScalarText(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (!is_array($value)) {
            return '';
        }
        if (isset($value['text']) && is_scalar($value['text'])) {
            return (string) $value['text'];
        }

        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<string> $path
     */
    private function renderMetaValue(mixed $value, mixed $sourceNative = null, array $provenance = [], array $path = []): string
    {
        if (is_array($sourceNative) && $this->isTaggedMetaNativeValue($sourceNative)) {
            $rendered = $this->renderMetaValueFromNative($sourceNative, $value, $provenance, $path);
            if ($rendered !== null) {
                return $rendered;
            }
        }

        if (is_array($value) && isset($value['type'])) {
            $type = (string) $value['type'];
            $payload = $value['value'] ?? null;

            return match ($type) {
                'MetaInlines' => 'MetaInlines ' . $this->renderInlineList(is_array($payload) ? $payload : []),
                'MetaBlocks' => 'MetaBlocks ' . $this->renderBlockList(is_array($payload) ? $payload : [], 0),
                'MetaList' => 'MetaList [ ' . implode(' , ', $this->renderMetaListItems(is_array($payload) && array_is_list($payload) ? $payload : [], $provenance, $path)) . ' ]',
                'MetaMap' => 'MetaMap (fromList [ ' . implode(' , ', $this->renderMetaMapPairs(is_array($payload) && !array_is_list($payload) ? $payload : [], $provenance, $path)) . ' ])',
                'inlines' => 'MetaInlines ' . $this->renderInlineList($this->metaTypedChildren($value)),
                'blocks' => 'MetaBlocks ' . $this->renderBlockList($this->metaTypedChildren($value), 0),
                'list' => 'MetaList [ ' . implode(' , ', $this->renderMetaListItems(is_array($value['items'] ?? null) && array_is_list($value['items']) ? $value['items'] : [], $provenance, $path)) . ' ]',
                'map' => 'MetaMap (fromList [ ' . implode(' , ', $this->renderMetaMapPairs(is_array($value['items'] ?? null) && !array_is_list($value['items']) ? $value['items'] : [], $provenance, $path)) . ' ])',
                default => 'MetaString ' . $this->quote((string) $payload),
            };
        }

        if ($value instanceof AstNode) {
            if ($this->isInlineNode($value)) {
                return 'MetaInlines ' . $this->renderInlineList([$value]);
            }

            return 'MetaBlocks ' . $this->renderBlockList([$value], 0);
        }

        if (is_bool($value)) {
            return 'MetaBool ' . ($value ? 'True' : 'False');
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return 'MetaString ' . $this->quote((string) $value);
        }

        if (is_array($value)) {
            if ($this->isAstNodeList($value)) {
                $inlineNodes = array_filter($value, fn (mixed $node): bool => $node instanceof AstNode && $this->isInlineNode($node));
                if (count($inlineNodes) === count($value)) {
                    return 'MetaInlines ' . $this->renderInlineList($value);
                }

                return 'MetaBlocks ' . $this->renderBlockList($value, 0);
            }

            if ($this->isList($value)) {
                return 'MetaList [ ' . implode(' , ', $this->renderMetaListItems($value, $provenance, $path)) . ' ]';
            }

            return 'MetaMap (fromList [ ' . implode(' , ', $this->renderMetaMapPairs($value, $provenance, $path)) . ' ])';
        }

        return 'MetaString ' . $this->quote((string) $value);
    }

    /**
     * @param array<string, mixed> $native
     * @param list<string> $path
     */
    private function renderMetaValueFromNative(array $native, mixed $value, array $provenance, array $path): ?string
    {
        $constructor = (string) ($native['t'] ?? '');
        $content = $native['c'] ?? null;

        return match ($constructor) {
            'MetaString' => 'MetaString ' . $this->quote((string) ($this->singleWrappedMetaContent($content) ?? (is_scalar($value) ? $value : ''))),
            'MetaBool' => 'MetaBool ' . (($this->singleWrappedMetaContent($content) === true || $value === true) ? 'True' : 'False'),
            'MetaInlines' => 'MetaInlines ' . $this->renderNativeInlineListOrFallback($content, $value),
            'MetaBlocks' => 'MetaBlocks ' . $this->renderNativeBlockListOrFallback($content, $value),
            'MetaList' => 'MetaList [ ' . implode(' , ', $this->renderMetaListItems(is_array($value) && array_is_list($value) ? $value : [], $provenance, $path)) . ' ]',
            'MetaMap' => 'MetaMap (fromList [ ' . implode(' , ', $this->renderMetaMapPairs(is_array($value) && !array_is_list($value) ? $value : [], $provenance, $path)) . ' ])',
            default => null,
        };
    }

    /**
     * @param list<mixed> $items
     * @return list<string>
     */
    private function renderMetaListItems(array $items, array $provenance, array $path): array
    {
        $rendered = [];
        foreach ($items as $index => $item) {
            $itemPath = [...$path, (string) $index];
            $rendered[] = $this->renderMetaValue(
                $item,
                $this->metaProvenanceNative($provenance, $itemPath),
                $provenance,
                $itemPath
            );
        }

        return $rendered;
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $path
     * @return list<string>
     */
    private function renderMetaMapPairs(array $items, array $provenance, array $path): array
    {
        ksort($items);
        $pairs = [];
        foreach ($items as $key => $item) {
            $field = (string) $key;
            $fieldPath = [...$path, $field];
            $pairs[] = '( ' . $this->quote($field) . ' , ' . $this->renderMetaValue(
                $item,
                $this->metaProvenanceNative($provenance, $fieldPath),
                $provenance,
                $fieldPath
            ) . ' )';
        }

        return $pairs;
    }

    /**
     * @return list<AstNode>
     */
    private function metaTypedChildren(array $value): array
    {
        $children = $value['children'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            return [];
        }

        return array_values(array_filter($children, static fn (mixed $child): bool => $child instanceof AstNode));
    }

    /**
     * @param list<string> $path
     */
    private function metaProvenanceNative(array $provenance, array $path): mixed
    {
        $entry = $provenance[$this->metaProvenancePath($path)] ?? null;

        return is_array($entry) && is_array($entry['native'] ?? null) ? $entry['native'] : null;
    }

    /**
     * @param list<string> $path
     */
    private function metaProvenancePath(array $path): string
    {
        if ($path === []) {
            return '/';
        }

        return '/' . implode('/', array_map(
            static fn (string $part): string => strtr($part, ['~' => '~0', '/' => '~1']),
            $path
        ));
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isTaggedMetaNativeValue(array $value): bool
    {
        return !array_is_list($value)
            && is_string($value['t'] ?? null)
            && in_array($value['t'], self::NATIVE_METADATA_CONSTRUCTORS, true);
    }

    private function renderNativeInlineListOrFallback(mixed $content, mixed $value): string
    {
        $inlines = $this->singleWrappedMetaListContent($content);
        if (is_array($inlines) && array_is_list($inlines)) {
            return $this->renderNativeValue($inlines);
        }

        return $this->renderInlineList($this->textInlines(is_scalar($value) ? (string) $value : ''));
    }

    private function renderNativeBlockListOrFallback(mixed $content, mixed $value): string
    {
        $blocks = $this->singleWrappedMetaListContent($content);
        if (is_array($blocks) && array_is_list($blocks)) {
            return $this->renderNativeValue($blocks);
        }

        return $this->renderBlockList(is_string($value) && $value !== '' ? [
            new AstNode('paragraph', [], $this->textInlines($value)),
        ] : [], 0);
    }

    private function singleWrappedMetaContent(mixed $content): mixed
    {
        return is_array($content) && array_is_list($content) && count($content) === 1 ? $content[0] : $content;
    }

    private function singleWrappedMetaListContent(mixed $content): mixed
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            return $content[0];
        }

        return $content;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlockList(array $blocks, int $indent): string
    {
        if ($blocks === []) {
            return '[]';
        }

        $lines = ['[ ' . $this->renderBlock($blocks[0], $indent + 2)];
        for ($i = 1, $count = count($blocks); $i < $count; $i++) {
            $lines[] = str_repeat(' ', $indent) . ', ' . $this->renderBlock($blocks[$i], $indent + 2);
        }
        $lines[] = str_repeat(' ', $indent) . ']';

        return implode("\n", $lines);
    }

    private function renderBlock(AstNode $node, int $indent): string
    {
        return match ($node->type) {
            'plain' => 'Plain ' . $this->renderInlineList($node->children),
            'paragraph' => 'Para ' . $this->renderInlineList($node->children),
            'heading' => 'Header ' . max(1, min(6, (int) $node->attr('level', 1))) . ' ' . $this->renderAttrTuple($node) . ' ' . $this->renderInlineList($node->children === [] ? $this->textInlines((string) $node->attr('text', '')) : $node->children),
            'horizontal_rule' => 'HorizontalRule',
            'null_block' => 'Null',
            'code_block' => 'CodeBlock ' . $this->renderAttrTuple($node) . ' ' . $this->quote((string) $node->attr('text', '')),
            'blockquote' => 'BlockQuote ' . $this->renderBlockList($node->children, $indent),
            'bullet_list' => 'BulletList ' . $this->renderListItems($node->children, $indent),
            'ordered_list' => 'OrderedList ' . $this->renderOrderedListAttrs($node) . ' ' . $this->renderListItems($node->children, $indent),
            'definition_list' => 'DefinitionList ' . $this->renderDefinitionItems($node->children),
            'line_block' => 'LineBlock [ ' . implode(' , ', array_map(fn (AstNode $line): string => $this->renderInlineList($this->lineInlines($line)), $node->children)) . ' ]',
            'figure' => 'Figure ' . $this->renderAttrTuple($node) . ' ' . $this->renderCaption($node) . ' ' . $this->renderBlockList($this->figureBlocks($node), $indent),
            'table' => $this->renderTable($node, $indent),
            'raw_html' => 'RawBlock (Format ' . $this->quote($this->rawFormat($node, 'html')) . ') ' . $this->quote($this->rawText($node, 'html')),
            'raw_tex' => 'RawBlock (Format ' . $this->quote($this->rawFormat($node, 'tex')) . ') ' . $this->quote($this->rawText($node, 'tex')),
            'raw_block', 'raw_markdown' => 'RawBlock (Format ' . $this->quote($this->rawFormat($node, 'markdown')) . ') ' . $this->quote($this->rawText($node, 'markdown')),
            'div' => 'Div ' . $this->renderAttrTuple($node) . ' ' . $this->renderBlockList($node->children, $indent),
            'native_block' => $this->renderNativeFallback($node, 'block'),
            default => throw new \InvalidArgumentException("Native writer does not support block node '{$node->type}'"),
        };
    }

    /**
     * @param list<AstNode> $items
     */
    private function renderListItems(array $items, int $indent): string
    {
        $blocks = [];
        foreach ($items as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $blocks[] = $this->renderBlockList($this->listItemBlocks($item), $indent);
        }

        return '[ ' . implode(' , ', $blocks) . ' ]';
    }

    private function renderOrderedListAttrs(AstNode $node): string
    {
        $styleAttr = $node->attr('style', null);
        $style = match (is_string($styleAttr) ? $styleAttr : '') {
            'default' => 'DefaultStyle',
            'decimal' => 'Decimal',
            'lower_alpha' => 'LowerAlpha',
            'upper_alpha' => 'UpperAlpha',
            'lower_roman' => 'LowerRoman',
            'upper_roman' => 'UpperRoman',
            'example' => 'Example',
            default => 'Decimal',
        };
        $delimiterAttr = $node->attr('delimiter', null);
        $delimiter = match (is_string($delimiterAttr) ? $delimiterAttr : '') {
            'default' => 'DefaultDelim',
            'period' => 'Period',
            'one_paren' => 'OneParen',
            'two_parens' => 'TwoParens',
            default => 'Period',
        };

        return '( ' . (int) $node->attr('start', 1) . ' , ' . $style . ' , ' . $delimiter . ' )';
    }

    /**
     * @return list<AstNode>
     */
    private function listItemBlocks(AstNode $item): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('plain', [], $inlines);
                $inlines = [];
            }
            $blocks[] = $child;
        }

        if ($inlines !== []) {
            $blocks[] = new AstNode('plain', [], $inlines);
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $items
     */
    private function renderDefinitionItems(array $items): string
    {
        $rendered = [];
        foreach ($items as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $term = null;
            $definitions = [];
            foreach ($item->children as $child) {
                if (in_array($child->type, ['term', 'definition_term'], true)) {
                    $term = $child;
                } elseif ($child->type === 'definition') {
                    $definitions[] = $this->renderBlockList($child->children, 0);
                }
            }
            $termInlines = $term instanceof AstNode
                ? ($term->children === [] ? $this->textInlines((string) $term->attr('text', $item->attr('term', ''))) : $term->children)
                : $this->textInlines((string) $item->attr('term', ''));
            $rendered[] = '( ' . $this->renderInlineList($termInlines) . ' , [ ' . implode(' , ', $definitions) . ' ] )';
        }

        return '[ ' . implode(' , ', $rendered) . ' ]';
    }

    private function renderTable(AstNode $node, int $indent): string
    {
        $columnCount = $this->tableColumnCount($node);

        return 'Table '
            . $this->renderAttrTuple($node) . ' '
            . $this->renderCaption($node) . ' '
            . $this->renderTableColSpecs($node, $columnCount) . ' '
            . '(' . $this->renderTableHead($this->tableSection($node, 'table_head'), $indent) . ') '
            . $this->renderTableBodies($node, $indent) . ' '
            . '(' . $this->renderTableFoot($this->tableSection($node, 'table_foot'), $indent) . ')';
    }

    private function renderTableColSpecs(AstNode $table, int $columnCount): string
    {
        if ($columnCount <= 0) {
            return '[]';
        }

        $alignments = $this->tableAlignments($table, $columnCount);
        $widths = $this->tableWidths($table, $columnCount);
        $specs = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $specs[] = '( ' . $this->renderTableAlignment($alignments[$index] ?? 'default')
                . ' , ' . $this->renderTableColumnWidth($widths[$index] ?? null) . ' )';
        }

        return '[ ' . implode(' , ', $specs) . ' ]';
    }

    private function renderTableColumnWidth(mixed $width): string
    {
        if (is_numeric($width) && (float) $width > 0.0) {
            return 'ColWidth ' . $this->renderFloat((float) $width);
        }

        return 'ColWidthDefault';
    }

    private function renderTableHead(?AstNode $head, int $indent): string
    {
        return 'TableHead '
            . $this->renderAttrTuple($head ?? new AstNode('table_head')) . ' '
            . $this->renderTableRows($head instanceof AstNode ? $this->tableRowsFromChildren($head->children) : [], $indent);
    }

    private function renderTableBodies(AstNode $table, int $indent): string
    {
        $bodies = $this->tableBodies($table);
        if ($bodies === []) {
            return '[]';
        }

        $rendered = [];
        foreach ($bodies as $body) {
            $headRows = $this->tableBodyHeadRows($body);
            $bodyRows = $this->tableRowsFromChildren($body->children);
            $rendered[] = 'TableBody '
                . $this->renderAttrTuple($body) . ' '
                . '(RowHeadColumns ' . max(0, (int) $body->attr('rowHeadColumns', 0)) . ') '
                . $this->renderTableRows($headRows, $indent) . ' '
                . $this->renderTableRows($bodyRows, $indent);
        }

        return '[ ' . implode(' , ', $rendered) . ' ]';
    }

    private function renderTableFoot(?AstNode $foot, int $indent): string
    {
        return 'TableFoot '
            . $this->renderAttrTuple($foot ?? new AstNode('table_foot')) . ' '
            . $this->renderTableRows($foot instanceof AstNode ? $this->tableRowsFromChildren($foot->children) : [], $indent);
    }

    /**
     * @param list<AstNode> $rows
     */
    private function renderTableRows(array $rows, int $indent): string
    {
        $rendered = [];
        foreach ($rows as $row) {
            if ($row->type === 'table_row') {
                $rendered[] = $this->renderTableRow($row, $indent);
            }
        }

        return '[ ' . implode(' , ', $rendered) . ' ]';
    }

    private function renderTableRow(AstNode $row, int $indent): string
    {
        $cells = [];
        foreach ($row->children as $cell) {
            if ($cell->type === 'table_cell') {
                $cells[] = $this->renderTableCell($cell, $indent);
            }
        }

        return 'Row ' . $this->renderAttrTuple($row) . ' [ ' . implode(' , ', $cells) . ' ]';
    }

    private function renderTableCell(AstNode $cell, int $indent): string
    {
        return 'Cell '
            . $this->renderAttrTuple($cell) . ' '
            . $this->renderTableAlignment((string) $cell->attr('align', 'default')) . ' '
            . '(RowSpan ' . max(1, (int) $cell->attr('rowspan', 1)) . ') '
            . '(ColSpan ' . max(1, (int) $cell->attr('colspan', 1)) . ') '
            . $this->renderBlockList($this->tableCellBlocks($cell), $indent);
    }

    private function renderTableAlignment(string $alignment): string
    {
        return match ($alignment) {
            'left' => 'AlignLeft',
            'right' => 'AlignRight',
            'center' => 'AlignCenter',
            default => 'AlignDefault',
        };
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellBlocks(AstNode $cell): array
    {
        if ($cell->children === []) {
            $text = (string) $cell->attr('text', '');

            return $text === '' ? [] : [new AstNode('plain', [], $this->textInlines($text))];
        }

        return $this->mixedChildrenAsBlocks($cell->children);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlineList(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            array_push($parts, ...$this->renderInline($node));
        }

        return '[ ' . implode(' , ', $parts) . ' ]';
    }

    /**
     * @return list<string>
     */
    private function renderInline(AstNode $node): array
    {
        return match ($node->type) {
            'text' => $this->renderTextInlineNode($node),
            'space' => ['Space'],
            'softbreak' => ['SoftBreak'],
            'linebreak' => ['LineBreak'],
            'emph' => ['Emph ' . $this->renderInlineList($node->children)],
            'strong' => ['Strong ' . $this->renderInlineList($node->children)],
            'strikeout' => ['Strikeout ' . $this->renderInlineList($node->children)],
            'superscript' => ['Superscript ' . $this->renderInlineList($node->children)],
            'subscript' => ['Subscript ' . $this->renderInlineList($node->children)],
            'underline' => ['Underline ' . $this->renderInlineList($node->children)],
            'small_caps' => ['SmallCaps ' . $this->renderInlineList($node->children)],
            'code' => ['Code ' . $this->renderAttrTuple($node) . ' ' . $this->quote((string) $node->attr('text', ''))],
            'link' => ['Link ' . $this->renderAttrTuple($node) . ' ' . $this->renderInlineList($node->children) . ' ( ' . $this->quote((string) $node->attr('url', '')) . ' , ' . $this->quote((string) $node->attr('title', '')) . ' )'],
            'image' => ['Image ' . $this->renderAttrTuple($node) . ' ' . $this->renderImageAltInlineList($node) . ' ( ' . $this->quote((string) $node->attr('url', $node->attr('src', ''))) . ' , ' . $this->quote((string) $node->attr('title', '')) . ' )'],
            'note' => ['Note ' . $this->renderBlockList($node->children, 0)],
            'quoted' => ['Quoted ' . (((string) $node->attr('kind', 'double')) === 'single' ? 'SingleQuote' : 'DoubleQuote') . ' ' . $this->renderInlineList($node->children)],
            'math' => ['Math ' . ($this->mathIsDisplay($node) ? 'DisplayMath' : 'InlineMath') . ' ' . $this->quote((string) $node->attr('text', ''))],
            'citation', 'citation_group' => [$this->renderCitation($node)],
            'raw_html_inline' => ['RawInline (Format ' . $this->quote($this->rawFormat($node, 'html')) . ') ' . $this->quote($this->rawText($node, 'html'))],
            'raw_tex', 'raw_tex_inline' => ['RawInline (Format ' . $this->quote($this->rawFormat($node, 'tex')) . ') ' . $this->quote($this->rawText($node, 'tex'))],
            'raw_markdown', 'raw_inline' => ['RawInline (Format ' . $this->quote($this->rawFormat($node, 'markdown')) . ') ' . $this->quote($this->rawText($node, 'markdown'))],
            'span' => ['Span ' . $this->renderAttrTuple($node) . ' ' . $this->renderInlineList($node->children)],
            'native_inline' => [$this->renderNativeFallback($node, 'inline')],
            default => throw new \InvalidArgumentException("Native writer does not support inline node '{$node->type}'"),
        };
    }

    private function renderNativeFallback(AstNode $node, string $context): string
    {
        $native = $node->attr('native');
        if (!is_array($native) || array_is_list($native) || !is_string($native['t'] ?? null) || $native['t'] === '') {
            throw new \InvalidArgumentException("Native writer {$context} fallback node must carry a tagged native constructor");
        }

        $arguments = $node->attr('nativeTextArguments', null);
        if (is_array($arguments) && array_is_list($arguments)) {
            return trim($native['t'] . ' ' . implode(' ', array_map(
                fn (mixed $argument): string => $this->renderNativeValue($argument),
                $arguments
            )));
        }

        if (!array_key_exists('c', $native)) {
            return $native['t'];
        }

        return $native['t'] . ' ' . $this->renderNativeValue($native['c']);
    }

    private function renderNativeValue(mixed $value): string
    {
        if (is_string($value)) {
            return $this->quote($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->renderFloat($value);
        }

        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        if (!is_array($value)) {
            return $this->quote((string) $value);
        }

        if (!array_is_list($value) && is_string($value['t'] ?? null) && $value['t'] !== '') {
            if (!array_key_exists('c', $value)) {
                return $value['t'];
            }

            return $value['t'] . ' ' . $this->renderNativeValue($value['c']);
        }

        if (!array_is_list($value)) {
            $fields = [];
            foreach ($value as $field => $item) {
                $fields[] = (string) $field . ' = ' . $this->renderNativeValue($item);
            }

            return '{ ' . implode(' , ', $fields) . ' }';
        }

        return '[ ' . implode(' , ', array_map(
            fn (mixed $item): string => $this->renderNativeValue($item),
            $value
        )) . ' ]';
    }

    private function rawFormat(AstNode $node, string $default): string
    {
        $format = (string) $node->attr('format', '');

        return $format === '' ? $default : $format;
    }

    private function rawText(AstNode $node, string $kind): string
    {
        return match ($kind) {
            'html' => (string) $node->attr('text', $node->attr('html', '')),
            'tex' => (string) $node->attr('text', $node->attr('tex', '')),
            'markdown' => (string) $node->attr('text', $node->attr('markdown', '')),
            default => (string) $node->attr('text', ''),
        };
    }

    private function renderCaption(AstNode $node): string
    {
        $shortCaption = $this->captionInlines($node->attr('shortCaptionInlines', null), $node->attr('shortCaption', null));
        $short = $shortCaption === null ? 'Nothing' : '(Just ' . $this->renderInlineList($shortCaption) . ')';
        $longBlocks = $this->captionBlocks($node);

        return '(Caption ' . $short . ' ' . $this->renderBlockList($longBlocks, 0) . ')';
    }

    private function mathIsDisplay(AstNode $node): bool
    {
        $display = $node->attr('display', null);
        if (is_bool($display)) {
            return $display;
        }

        return (string) $node->attr('kind', $display ?? 'inline') === 'display';
    }

    /**
     * @return list<AstNode>
     */
    private function captionBlocks(AstNode $node): array
    {
        $captionBlocks = $node->attr('captionBlocks', null);
        if (is_array($captionBlocks) && $this->isAstNodeList($captionBlocks)) {
            return $this->mixedChildrenAsBlocks(array_values($captionBlocks));
        }

        $captionInlines = $this->captionInlines($node->attr('captionInlines', null), $node->attr('caption', null));
        if ($captionInlines === null) {
            return [];
        }

        return [new AstNode('plain', [], $captionInlines)];
    }

    /**
     * @return list<AstNode>|null
     */
    private function captionInlines(mixed $inlines, mixed $fallback): ?array
    {
        if (is_array($inlines) && $this->isAstNodeList($inlines)) {
            return $inlines;
        }

        if ($fallback instanceof AstNode) {
            return $this->isInlineNode($fallback) ? [$fallback] : null;
        }

        if (is_string($fallback) && $fallback !== '') {
            return $this->textInlines($fallback);
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function figureBlocks(AstNode $node): array
    {
        return $this->mixedChildrenAsBlocks($node->children);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function mixedChildrenAsBlocks(array $children): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('plain', [], $inlines);
                $inlines = [];
            }
            $blocks[] = $child;
        }

        if ($inlines !== []) {
            $blocks[] = new AstNode('plain', [], $inlines);
        }

        return $blocks;
    }

    private function renderCitation(AstNode $node): string
    {
        $citations = $this->citationEntries($node);
        $display = $this->citationDisplayInlines($node, $citations);

        return 'Cite [ ' . implode(' , ', array_map(fn (array $citation): string => $this->renderCitationEntry($citation), $citations)) . ' ] ' . $this->renderInlineList($display);
    }

    /**
     * @param list<array<string, mixed>> $citations
     * @return list<AstNode>
     */
    private function citationDisplayInlines(AstNode $node, array $citations): array
    {
        if ($node->type !== 'citation_group' && $node->children !== []) {
            return $node->children;
        }

        $source = $node->attr('citationSourceInlines', []);
        if (is_array($source) && array_is_list($source) && $this->allAstNodes($source)) {
            return $source;
        }

        return $this->textInlines((string) $node->attr('text', $this->citationDisplayText($citations)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationEntries(AstNode $node): array
    {
        if ($node->type === 'citation_group') {
            $entries = [];
            foreach ($node->children as $child) {
                if ($child->type === 'citation') {
                    $entries[] = $this->citationEntryFromNode($child);
                }
            }
            if ($entries !== []) {
                return $entries;
            }
        }

        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = $this->citationEntryFromNode($citation);
                } elseif (is_array($citation)) {
                    $entries[] = $citation;
                }
            }

            return $entries;
        }

        return [$this->citationEntryFromNode($node)];
    }

    /**
     * @return array<string, mixed>
     */
    private function citationEntryFromNode(AstNode $node): array
    {
        return [
            'id' => (string) $node->attr('id', ''),
            'prefix' => $node->attr('prefix', []),
            'suffix' => $node->attr('suffix', []),
            'mode' => (string) $node->attr('mode', 'normal'),
            'noteNum' => $node->attr('noteNum', $node->attr('citationNoteNum', 1)),
            'hash' => $node->attr('hash', $node->attr('citationHash', 0)),
        ];
    }

    /**
     * @param array<string, mixed> $citation
     */
    private function renderCitationEntry(array $citation): string
    {
        return 'Citation { citationId = ' . $this->quote((string) ($citation['id'] ?? ''))
            . ' , citationPrefix = ' . $this->renderCitationAffix($citation['prefix'] ?? [])
            . ' , citationSuffix = ' . $this->renderCitationAffix($citation['suffix'] ?? [])
            . ' , citationMode = ' . $this->renderCitationMode((string) ($citation['mode'] ?? 'normal'))
            . ' , citationNoteNum = ' . (int) ($citation['noteNum'] ?? $citation['citationNoteNum'] ?? 1)
            . ' , citationHash = ' . (int) ($citation['hash'] ?? $citation['citationHash'] ?? 0)
            . ' }';
    }

    private function renderCitationAffix(mixed $value): string
    {
        if (is_string($value)) {
            if ($value === '') {
                return '[]';
            }

            return $this->renderInlineList($this->textInlines($value));
        }

        if (!is_array($value)) {
            return '[]';
        }

        $nodes = [];
        foreach ($value as $inline) {
            if ($inline instanceof AstNode) {
                $nodes[] = $inline;
            } elseif (is_string($inline)) {
                array_push($nodes, ...$this->textInlines($inline));
            }
        }

        if ($nodes === []) {
            return '[]';
        }

        return $this->renderInlineList($nodes);
    }

    private function renderCitationMode(string $mode): string
    {
        return match (strtolower(str_replace(['-', '_'], '', $mode))) {
            'authorintext' => 'AuthorInText',
            'suppressauthor' => 'SuppressAuthor',
            default => 'NormalCitation',
        };
    }

    /**
     * @param list<array<string, mixed>> $citations
     */
    private function citationDisplayText(array $citations): string
    {
        if ($citations === []) {
            return '[]';
        }

        $parts = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $parts[] = '@' . $id;
        }

        return $parts === [] ? '[]' : '[' . implode('; ', $parts) . ']';
    }

    private function renderImageAltInlineList(AstNode $node): string
    {
        $alt = (string) $node->attr('alt', '');
        if ($alt !== '' && $node->attr('singleStrAlt') === true) {
            return '[ Str ' . $this->quote($alt) . ' ]';
        }

        return $this->renderInlineList($node->children === [] ? $this->textInlines($alt) : $node->children);
    }

    /**
     * @return list<AstNode>
     */
    private function lineInlines(AstNode $line): array
    {
        return $line->children === [] ? $this->textInlines((string) $line->attr('text', '')) : $line->children;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $nodes = [];
        foreach ($this->textParts($text) as $part) {
            $nodes[] = new AstNode('text', ['text' => $part]);
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function renderTextInlineNode(AstNode $node): array
    {
        if ($this->nativeInlinePartsMatchText($node)) {
            $rendered = [];
            $parts = $node->attr('nativeInlineParts', []);
            foreach ($parts as $part) {
                if (!is_array($part) || array_is_list($part)) {
                    return $this->renderTextInline((string) $node->attr('text', ''));
                }

                if (($part['t'] ?? null) === 'Str') {
                    $content = $this->nativeStringContent($part['c'] ?? null);
                    if ($content === null) {
                        return $this->renderTextInline((string) $node->attr('text', ''));
                    }
                    $rendered[] = 'Str ' . $this->quote($content);
                    continue;
                }

                if (in_array($part['t'] ?? null, ['Space', 'SoftBreak', 'LineBreak'], true)) {
                    $rendered[] = (string) $part['t'];
                    continue;
                }

                return $this->renderTextInline((string) $node->attr('text', ''));
            }

            return $rendered;
        }

        return $this->renderTextInline((string) $node->attr('text', ''));
    }

    /**
     * @return list<string>
     */
    private function renderTextInline(string $text): array
    {
        $parts = [];
        foreach ($this->textParts($text) as $part) {
            if (preg_match('/^[ \t]+$/', $part) === 1) {
                $parts[] = 'Space';
            } elseif ($part === "\n") {
                $parts[] = 'SoftBreak';
            } else {
                $parts[] = 'Str ' . $this->quote($part);
            }
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function textParts(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/([ \t]+|\n)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [$text] : $parts;
    }

    private function renderAttrTuple(AstNode $node): string
    {
        $id = (string) $node->attr('id', '');
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);
        if (!is_array($classes)) {
            $classes = [];
        }
        if (!is_array($attributes)) {
            $attributes = [];
        }
        ksort($attributes);

        $classList = '[ ' . implode(' , ', array_map(fn (mixed $class): string => $this->quote((string) $class), $classes)) . ' ]';
        $attrPairs = [];
        foreach ($attributes as $key => $value) {
            $attrPairs[] = '( ' . $this->quote((string) $key) . ' , ' . $this->quote((string) $value) . ' )';
        }

        return '( ' . $this->quote($id) . ' , ' . $classList . ' , [ ' . implode(' , ', $attrPairs) . ' ] )';
    }

    private function quote(string $value): string
    {
        $output = '"';
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            $chars = str_split($value);
        }

        foreach ($chars as $index => $char) {
            $escaped = match ($char) {
                "\\" => "\\\\",
                '"' => "\\\"",
                "\n" => "\\n",
                "\r" => "\\r",
                "\t" => "\\t",
                default => $this->quoteCharacter($char),
            };
            $output .= $escaped;
            $next = $chars[$index + 1] ?? null;
            if ($next !== null && preg_match('/^\\\\\d+$/', $escaped) === 1 && preg_match('/^\d$/', $next) === 1) {
                $output .= '\\&';
            }
        }

        return $output . '"';
    }

    private function quoteCharacter(string $char): string
    {
        $codepoint = $this->unicodeCodepoint($char);
        if ($codepoint < 32 || $codepoint === 127 || $codepoint > 126) {
            return '\\' . (string) $codepoint;
        }

        return $char;
    }

    private function unicodeCodepoint(string $char): int
    {
        if (function_exists('mb_ord')) {
            $codepoint = mb_ord($char, 'UTF-8');
            if (is_int($codepoint)) {
                return $codepoint;
            }
        }

        if (strlen($char) === 1) {
            return ord($char);
        }

        $encoded = @mb_convert_encoding($char, 'UCS-4BE', 'UTF-8');
        if (!is_string($encoded)) {
            return 0xFFFD;
        }
        $parts = unpack('N', $encoded);

        return (int) ($parts[1] ?? 0);
    }

    private function renderFloat(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    private function tableColumnCount(AstNode $table): int
    {
        $nativeColumnCount = $table->attr('nativeColumnCount');
        if (is_int($nativeColumnCount) || (is_string($nativeColumnCount) && preg_match('/^\d+$/', $nativeColumnCount) === 1)) {
            return max(0, (int) $nativeColumnCount);
        }

        $max = max(count($this->tableAlignments($table, 0)), count($this->tableWidths($table, 0)));
        foreach ($this->tableAllRows($table) as $row) {
            $columns = 0;
            foreach ($row->children as $cell) {
                if ($cell->type === 'table_cell') {
                    $columns += max(1, (int) $cell->attr('colspan', 1));
                }
            }
            $max = max($max, $columns);
        }

        return $max;
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(AstNode $table, int $columnCount): array
    {
        $alignments = $table->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }

        $normalized = [];
        foreach ($alignments as $alignment) {
            $normalized[] = in_array($alignment, ['left', 'right', 'center'], true) ? (string) $alignment : 'default';
        }
        while ($columnCount > 0 && count($normalized) < $columnCount) {
            $normalized[] = 'default';
        }

        return $columnCount > 0 ? array_slice($normalized, 0, $columnCount) : $normalized;
    }

    /**
     * @return list<float>
     */
    private function tableWidths(AstNode $table, int $columnCount): array
    {
        $widths = $table->attr('widths', []);
        if (!is_array($widths)) {
            $widths = [];
        }

        $normalized = [];
        foreach ($widths as $width) {
            $normalized[] = is_numeric($width) ? max(0.0, (float) $width) : 0.0;
        }
        while ($columnCount > 0 && count($normalized) < $columnCount) {
            $normalized[] = 0.0;
        }

        return $columnCount > 0 ? array_slice($normalized, 0, $columnCount) : $normalized;
    }

    private function tableSection(AstNode $table, string $type): ?AstNode
    {
        foreach ($table->children as $child) {
            if ($child->type === $type) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodies(AstNode $table): array
    {
        $bodies = [];
        foreach ($table->children as $child) {
            if ($child->type === 'table_body') {
                $bodies[] = $child;
            }
        }

        return $bodies;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyHeadRows(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);
        if (!is_array($headRows)) {
            return [];
        }

        return array_values(array_filter($headRows, static fn (mixed $row): bool => $row instanceof AstNode && $row->type === 'table_row'));
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function tableRowsFromChildren(array $children): array
    {
        return array_values(array_filter($children, static fn (AstNode $node): bool => $node->type === 'table_row'));
    }

    /**
     * @return list<AstNode>
     */
    private function tableAllRows(AstNode $table): array
    {
        $rows = [];
        $head = $this->tableSection($table, 'table_head');
        if ($head instanceof AstNode) {
            array_push($rows, ...$this->tableRowsFromChildren($head->children));
        }
        foreach ($this->tableBodies($table) as $body) {
            array_push($rows, ...$this->tableBodyHeadRows($body), ...$this->tableRowsFromChildren($body->children));
        }
        $foot = $this->tableSection($table, 'table_foot');
        if ($foot instanceof AstNode) {
            array_push($rows, ...$this->tableRowsFromChildren($foot->children));
        }

        return $rows;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'underline',
            'small_caps',
            'code',
            'link',
            'image',
            'note',
            'quoted',
            'math',
            'citation',
            'citation_group',
            'raw_html_inline',
            'raw_tex_inline',
            'raw_inline',
            'span',
        ], true);
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
