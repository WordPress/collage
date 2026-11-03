<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocJsonReader
{
    private const META_CONSTRUCTORS = [
        'MetaString',
        'MetaBool',
        'MetaInlines',
        'MetaBlocks',
        'MetaList',
        'MetaMap',
    ];

    public function read(string $json): AstNode
    {
        try {
            $packet = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid Pandoc JSON packet: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($packet)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must be an object');
        }

        return $this->readPacket($packet);
    }

    /**
     * @param array<array-key, mixed> $packet
     */
    public function readPacket(array $packet): AstNode
    {
        $documentNative = $this->documentNativePayload($packet);
        $legacyTuplePacket = array_is_list($packet);
        $packet = $this->normalizePacket($packet);
        $blocks = $packet['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must contain a blocks array');
        }

        $attrs = [];
        if ($documentNative !== null) {
            $attrs['documentConstructor'] = 'Pandoc';
            $attrs['documentNative'] = $documentNative;
        }
        $apiVersion = null;
        if (isset($packet['pandoc-api-version'])) {
            $apiVersion = $this->readApiVersion($packet['pandoc-api-version']);
            $attrs['pandocApiVersion'] = $apiVersion;
        }

        $rawMeta = $packet['meta'] ?? [];
        $metaConstructorProvenance = $this->metaConstructorProvenance($rawMeta, $apiVersion, $legacyTuplePacket);
        $meta = $this->normalizeMeta($rawMeta, $apiVersion, $legacyTuplePacket);
        if ($meta !== []) {
            $attrs['meta'] = $this->withStandardMetaHelpers($this->readMetaMap($meta));
        }
        $attrs = array_replace($attrs, $this->metaConstructorAttrs($rawMeta, $meta));
        if ($metaConstructorProvenance !== []) {
            $attrs['metaConstructorProvenance'] = $metaConstructorProvenance;
        }

        return new AstNode('document', $attrs, $this->readBlocks($blocks));
    }

    /**
     * @param array<array-key, mixed> $packet
     * @return array<string, mixed>
     */
    private function normalizePacket(array $packet): array
    {
        if ($this->isTaggedObject($packet) && $packet['t'] === 'Pandoc') {
            $content = $this->pandocConstructorContent($packet['c'] ?? null);
            $normalized = [
                'meta' => $content[0],
                'blocks' => $content[1],
            ];
            if (array_key_exists('pandoc-api-version', $packet)) {
                $normalized['pandoc-api-version'] = $packet['pandoc-api-version'];
            }

            return $normalized;
        }

        if (!array_is_list($packet)) {
            return $packet;
        }

        if (count($packet) !== 2) {
            throw new \InvalidArgumentException('Pandoc JSON packet must be an object or legacy [meta, blocks] tuple');
        }

        return [
            'meta' => $packet[0],
            'blocks' => $packet[1],
        ];
    }

    /**
     * @return list<mixed>
     */
    private function pandocConstructorContent(mixed $content): array
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            $content = $content[0];
        }

        return $this->tuple($content, 2, 'Pandoc');
    }

    /**
     * @param array<array-key, mixed> $packet
     * @return array<string, mixed>|null
     */
    private function documentNativePayload(array $packet): ?array
    {
        return $this->isTaggedObject($packet) && $packet['t'] === 'Pandoc' ? $packet : null;
    }

    /**
     * @param list<int>|null $apiVersion
     * @return array<string, mixed>
     */
    private function normalizeMeta(mixed $meta, ?array $apiVersion, bool $legacyTuplePacket): array
    {
        if ($this->looksLikeMetaConstructor($meta)) {
            [$tag, $content] = $this->tagged($meta, 'meta');
            if ($tag !== 'MetaMap') {
                throw new \InvalidArgumentException('Pandoc JSON meta constructor must be MetaMap');
            }

            return $this->metaMapContent($content);
        }

        $metaEnvelope = $this->metaEnvelopeContent($meta);
        if ($metaEnvelope !== null) {
            return $metaEnvelope;
        }

        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            throw new \InvalidArgumentException('Pandoc JSON meta must be an object');
        }

        if ($this->taggedMetaConstructor($meta) === 'MetaMap') {
            return $this->objectContent($meta['c'] ?? null, 'Pandoc JSON meta MetaMap');
        }

        if (
            count($meta) === 1
            && array_key_exists('unMeta', $meta)
            && !$this->isTaggedObject($meta['unMeta'])
            && $this->shouldUnwrapLegacyMetaEnvelope($apiVersion, $legacyTuplePacket)
        ) {
            $unMeta = $meta['unMeta'];
            if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
                throw new \InvalidArgumentException('Pandoc JSON meta.unMeta must be an object');
            }

            return $unMeta;
        }

        if (count($meta) === 1 && array_key_exists('unMeta', $meta) && $this->taggedMetaConstructor($meta['unMeta']) === 'MetaMap') {
            $unMeta = $meta['unMeta'];

            return $this->objectContent($unMeta['c'] ?? null, 'Pandoc JSON meta.unMeta MetaMap');
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metaEnvelopeContent(mixed $meta): ?array
    {
        if (!$this->isTaggedConstructor($meta, 'Meta')) {
            return null;
        }

        $content = $meta['c'] ?? null;
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        if ($this->taggedMetaConstructor($content) === 'MetaMap') {
            return $this->metaMapContent($content['c'] ?? null);
        }

        if (!is_array($content) || array_is_list($content) || count($content) !== 1 || !array_key_exists('unMeta', $content)) {
            return null;
        }

        $unMeta = $content['unMeta'];
        if ($this->taggedMetaConstructor($unMeta) === 'MetaMap') {
            return $this->metaMapContent($unMeta['c'] ?? null);
        }

        if ($this->isTaggedObject($unMeta)) {
            return null;
        }

        if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
            throw new \InvalidArgumentException('Pandoc JSON meta Meta.unMeta content must be an object');
        }

        return $unMeta;
    }

    /**
     * @param list<int>|null $apiVersion
     */
    private function shouldUnwrapLegacyMetaEnvelope(?array $apiVersion, bool $legacyTuplePacket): bool
    {
        if ($legacyTuplePacket || $apiVersion === null) {
            return true;
        }

        return ($apiVersion[0] ?? 0) === 1 && ($apiVersion[1] ?? 0) <= 17;
    }

    private function isTaggedObject(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value) && isset($value['t']) && is_string($value['t']);
    }

    private function taggedMetaConstructor(mixed $value): ?string
    {
        if (!is_array($value) || array_is_list($value) || !isset($value['t']) || !is_string($value['t'])) {
            return null;
        }

        return in_array($value['t'], self::META_CONSTRUCTORS, true) ? $value['t'] : null;
    }

    /**
     * @return list<int>
     */
    private function readApiVersion(mixed $version): array
    {
        if (!is_array($version) || !array_is_list($version) || $version === []) {
            throw new \InvalidArgumentException('pandoc-api-version must be a non-empty integer array');
        }

        return array_map(static function (mixed $part): int {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('pandoc-api-version entries must be integers');
            }

            return $part;
        }, $version);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function readMetaMap(array $meta): array
    {
        $mapped = [];
        foreach ($meta as $key => $value) {
            $mapped[$key] = $this->readMetaValue($value);
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function metaConstructorAttrs(mixed $rawMeta, array $meta): array
    {
        $attrs = [];
        if ($this->taggedMetaConstructor($rawMeta) === 'MetaMap') {
            $attrs['metaConstructor'] = 'MetaMap';
            $attrs['metaNative'] = $rawMeta;
        } elseif ($this->metaEnvelopeContent($rawMeta) !== null) {
            $attrs['metaConstructor'] = 'Meta';
            $attrs['metaNative'] = $rawMeta;
        } elseif (
            is_array($rawMeta)
            && !array_is_list($rawMeta)
            && count($rawMeta) === 1
            && array_key_exists('unMeta', $rawMeta)
            && $this->taggedMetaConstructor($rawMeta['unMeta']) === 'MetaMap'
        ) {
            $attrs['metaConstructor'] = 'MetaMap';
            $attrs['metaNative'] = $rawMeta['unMeta'];
        }

        $constructors = [];
        $nativeValues = [];
        foreach ($meta as $key => $value) {
            $tree = $this->metaConstructorTree($value);
            if ($tree !== null) {
                $constructors[(string) $key] = $tree;
            }

            if ($this->looksLikeMetaConstructor($value)) {
                $nativeValues[(string) $key] = $value;
            }
        }

        if ($constructors !== []) {
            $attrs['metaConstructors'] = $constructors;
        }
        if ($nativeValues !== []) {
            $attrs['metaNativeValues'] = $nativeValues;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metaConstructorTree(mixed $value): ?array
    {
        if (!$this->looksLikeMetaConstructor($value)) {
            return null;
        }

        [$tag, $content] = $this->tagged($value, 'meta value');
        $tree = ['_constructor' => $tag];
        if ($tag === 'MetaMap') {
            $items = [];
            foreach ($this->metaMapContent($content) as $key => $item) {
                $child = $this->metaConstructorTree($item);
                if ($child !== null) {
                    $items[(string) $key] = $child;
                }
            }
            $tree['items'] = $items;
        } elseif ($tag === 'MetaList') {
            $items = [];
            foreach ($this->metaConstructorListContent($content, 'MetaList') as $index => $item) {
                $child = $this->metaConstructorTree($item);
                if ($child !== null) {
                    $items[(int) $index] = $child;
                }
            }
            $tree['items'] = $items;
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function withStandardMetaHelpers(array $meta): array
    {
        $titleInlines = $this->metaInlineChildren($meta['title'] ?? null);
        if ($titleInlines !== null && !array_key_exists('titleInlines', $meta)) {
            $meta['titleInlines'] = $titleInlines;
        }

        $authorInlines = $this->metaListInlineChildren($meta['author'] ?? null);
        if ($authorInlines !== null && !array_key_exists('authorInlines', $meta)) {
            $meta['authorInlines'] = $authorInlines;
        }

        $dateInlines = $this->metaInlineChildren($meta['date'] ?? null);
        if ($dateInlines !== null && !array_key_exists('dateInlines', $meta)) {
            $meta['dateInlines'] = $dateInlines;
        }

        return $meta;
    }

    /**
     * @return list<AstNode>|null
     */
    private function metaInlineChildren(mixed $value): ?array
    {
        if (!is_array($value) || ($value['type'] ?? null) !== 'inlines') {
            return null;
        }

        $children = $value['children'] ?? null;
        if (!is_array($children) || !array_is_list($children)) {
            return null;
        }

        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                return null;
            }
        }

        return $children;
    }

    /**
     * @return list<list<AstNode>>|null
     */
    private function metaListInlineChildren(mixed $value): ?array
    {
        if (!is_array($value) || ($value['type'] ?? null) !== 'list') {
            return null;
        }

        $items = $value['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            return null;
        }

        $mapped = [];
        foreach ($items as $item) {
            $children = $this->metaInlineChildren($item);
            if ($children === null) {
                return null;
            }
            $mapped[] = $children;
        }

        return $mapped === [] ? null : $mapped;
    }

    private function readMetaValue(mixed $value): mixed
    {
        if (!$this->looksLikeMetaConstructor($value)) {
            return $this->readPlainMetaValue($value);
        }

        [$tag, $content] = $this->tagged($value, 'meta value');

        return match ($tag) {
            'MetaString' => is_string($content = $this->singleWrappedMetaContent($content)) ? $content : throw new \InvalidArgumentException('MetaString content must be a string'),
            'MetaBool' => is_bool($content = $this->singleWrappedMetaContent($content)) ? $content : throw new \InvalidArgumentException('MetaBool content must be a boolean'),
            'MetaInlines' => ['type' => 'inlines', 'children' => $this->readInlines($this->metaConstructorListContent($content, 'MetaInlines'))],
            'MetaBlocks' => ['type' => 'blocks', 'children' => $this->readBlocks($this->metaConstructorListContent($content, 'MetaBlocks'))],
            'MetaList' => ['type' => 'list', 'items' => array_map(fn (mixed $item): mixed => $this->readMetaValue($item), $this->metaConstructorListContent($content, 'MetaList'))],
            'MetaMap' => ['type' => 'map', 'items' => $this->readMetaMap($this->metaMapContent($content))],
            default => throw new \InvalidArgumentException("Unsupported Pandoc meta constructor: {$tag}"),
        };
    }

    private function looksLikeMetaConstructor(mixed $value): bool
    {
        return is_array($value)
            && !array_is_list($value)
            && isset($value['t'])
            && is_string($value['t'])
            && in_array($value['t'], self::META_CONSTRUCTORS, true);
    }

    private function readPlainMetaValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'type' => 'list',
                    'items' => array_map(fn (mixed $item): mixed => $this->readMetaValue($item), $value),
                ];
            }

            return [
                'type' => 'map',
                'items' => $this->readMetaMap($value),
            ];
        }

        throw new \InvalidArgumentException('Pandoc meta value must be a tagged object or JSON-compatible scalar, list, or object');
    }

    /**
     * @return array<string, mixed>
     */
    private function metaMapContent(mixed $content): array
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && !array_is_list($content[0])
        ) {
            $content = $content[0];
        }

        $map = $this->objectContent($content, 'MetaMap');
        if (count($map) !== 1 || !array_key_exists('unMeta', $map) || $this->isTaggedObject($map['unMeta'])) {
            return $map;
        }

        $unMeta = $map['unMeta'];
        if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
            throw new \InvalidArgumentException('MetaMap legacy unMeta content must be an object');
        }

        return $unMeta;
    }

    private function singleWrappedMetaContent(mixed $content): mixed
    {
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            return $content[0];
        }

        return $content;
    }

    /**
     * @return list<mixed>
     */
    private function metaConstructorListContent(mixed $content, string $context): array
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            $content = $content[0];
        }

        return $this->listContent($content, $context);
    }

    /**
     * @return array<string, array{constructor:string, native:array<string, mixed>}>
     * @param list<int>|null $apiVersion
     */
    private function metaConstructorProvenance(mixed $metadata, ?array $apiVersion, bool $legacyTuplePacket): array
    {
        $provenance = [];
        if ($this->looksLikeMetaConstructor($metadata)) {
            $this->collectMetaConstructorProvenance($metadata, [], $provenance);

            return $provenance;
        }

        $metaEnvelope = $this->metaEnvelopeContent($metadata);
        if ($metaEnvelope !== null && is_array($metadata) && !array_is_list($metadata)) {
            $provenance['/'] = [
                'constructor' => 'Meta',
                'native' => $metadata,
            ];
            foreach ($metaEnvelope as $key => $value) {
                $this->collectMetaConstructorProvenance($value, [(string) $key], $provenance);
            }

            return $provenance;
        }

        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            return [];
        }

        if ($this->taggedMetaConstructor($metadata) === 'MetaMap') {
            $this->collectMetaConstructorProvenance($metadata, [], $provenance);

            return $provenance;
        }

        if (
            count($metadata) === 1
            && array_key_exists('unMeta', $metadata)
            && !$this->isTaggedObject($metadata['unMeta'])
            && $this->shouldUnwrapLegacyMetaEnvelope($apiVersion, $legacyTuplePacket)
        ) {
            $metadata = $metadata['unMeta'];
            if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
                return [];
            }
        }

        foreach ($metadata as $key => $value) {
            $this->collectMetaConstructorProvenance($value, [(string) $key], $provenance);
        }

        return $provenance;
    }

    /**
     * @param list<string> $path
     * @param array<string, array{constructor:string, native:array<string, mixed>}> $provenance
     */
    private function collectMetaConstructorProvenance(mixed $value, array $path, array &$provenance): void
    {
        if ($this->looksLikeMetaConstructor($value)) {
            $constructor = $value['t'];
            $provenance[$this->metaProvenancePath($path)] = [
                'constructor' => $constructor,
                'native' => $value,
            ];

            if ($constructor === 'MetaMap') {
                foreach ($this->metaMapContent($value['c'] ?? []) as $key => $item) {
                    $this->collectMetaConstructorProvenance($item, [...$path, (string) $key], $provenance);
                }
            } elseif ($constructor === 'MetaList') {
                foreach ($this->metaConstructorListContent($value['c'] ?? [], 'MetaList provenance') as $index => $item) {
                    $this->collectMetaConstructorProvenance($item, [...$path, (string) $index], $provenance);
                }
            }

            return;
        }

        if (!is_array($value)) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                $this->collectMetaConstructorProvenance($item, [...$path, (string) $index], $provenance);
            }

            return;
        }

        foreach ($value as $key => $item) {
            $this->collectMetaConstructorProvenance($item, [...$path, (string) $key], $provenance);
        }
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
     * @return list<AstNode>
     */
    private function readBlocks(mixed $blocks): array
    {
        $blocks = $this->singleWrappedListContent($blocks, 'blocks');

        return array_map(fn (mixed $block): AstNode => $this->readBlock($block), $blocks);
    }

    private function readBlock(mixed $value): AstNode
    {
        [$tag, $content] = $this->tagged($value, 'block');

        $node = match ($tag) {
            'Plain' => $this->readInlineBlock('plain', $content, 'Plain'),
            'Para' => $this->readInlineBlock('paragraph', $content, 'Para'),
            'Header' => $this->readHeaderBlock($content),
            'CodeBlock' => $this->readCodeBlock($content),
            'RawBlock' => $this->readRawBlock($content),
            'BlockQuote' => $this->readBlockQuoteBlock($content),
            'OrderedList' => $this->readOrderedList($content),
            'BulletList' => $this->readBulletList($content),
            'DefinitionList' => $this->readDefinitionList($content),
            'LineBlock' => $this->readLineBlock($content),
            'HorizontalRule' => new AstNode('horizontal_rule'),
            'Null' => new AstNode('null_block'),
            'Div' => $this->readDivBlock($content),
            'Figure' => $this->readFigureBlock($content),
            'Table' => $this->readTableBlock($content),
            default => $this->nativeFallbackNode('native_block', $tag, $value),
        };

        return $this->withConstructorPayload($node, $tag, $value);
    }

    private function readInlineBlock(string $type, mixed $content, string $context): AstNode
    {
        $children = $this->readInlines($this->singleWrappedListContent($content, $context));
        if ($type === 'paragraph') {
            $figure = $this->legacySimpleFigureBlock($children);
            if ($figure instanceof AstNode) {
                return $figure;
            }
        }

        return new AstNode($type, ['text' => $this->plainText($children)], $children);
    }

    /**
     * @param list<AstNode> $children
     */
    private function legacySimpleFigureBlock(array $children): ?AstNode
    {
        if (count($children) !== 1 || $children[0]->type !== 'image') {
            return null;
        }

        $image = $children[0];
        $title = $image->attr('title', null);
        if (!is_string($title) || !str_starts_with($title, 'fig:')) {
            return null;
        }

        $captionInlines = $image->children;
        $attrs = [
            'caption' => $this->plainText($captionInlines),
            'simpleFigure' => true,
        ];
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        $imageAttrs = $image->attrs;
        $imageAttrs['title'] = substr($title, 4);

        return new AstNode('figure', $attrs, [new AstNode('image', $imageAttrs, $captionInlines)]);
    }

    private function readBlockQuoteBlock(mixed $content): AstNode
    {
        $blocks = $this->listContent($content, 'BlockQuote');

        return new AstNode('blockquote', [
            'blockQuoteBlocksNative' => $blocks,
        ], $this->readBlocks($blocks));
    }

    private function readHeaderBlock(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 3, 'Header');
        if (!is_int($tuple[0])) {
            throw new \InvalidArgumentException('Header level must be an integer');
        }

        $children = $this->readInlines($this->listContent($tuple[2], 'Header inlines'));

        return new AstNode('heading', array_merge(
            ['level' => $tuple[0]],
            $this->readAttrTuple($tuple[1]),
            ['text' => $this->plainText($children)]
        ), $children);
    }

    private function readCodeBlock(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'CodeBlock');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('CodeBlock text must be a string');
        }

        return new AstNode('code_block', array_merge($this->readAttrTuple($tuple[0]), ['text' => $tuple[1]]));
    }

    private function readRawBlock(mixed $content): AstNode
    {
        [$format, $formatNative, $text] = $this->formatTextTuple($content, 'RawBlock');
        $attrs = ['format' => $format, 'text' => $text];
        if ($formatNative !== null) {
            $attrs['formatConstructor'] = 'Format';
            $attrs['formatNative'] = $formatNative;
        }
        $normalized = strtolower($format);

        if ($this->isHtmlRawFormat($normalized)) {
            return new AstNode('raw_html', array_merge($attrs, ['html' => $text]));
        }

        if ($this->isTexRawFormat($normalized)) {
            return new AstNode('raw_tex', array_merge($attrs, ['tex' => $text]));
        }

        if ($this->isMarkdownRawFormat($normalized)) {
            return new AstNode('raw_markdown', array_merge($attrs, ['markdown' => $text]));
        }

        return new AstNode('raw_block', $attrs);
    }

    private function readOrderedList(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'OrderedList');
        $listAttributesNative = $tuple[0];
        $listAttributesContent = $this->constructorContent($listAttributesNative, 'ListAttributes', 'OrderedList attributes', false);
        if (
            is_array($listAttributesContent)
            && array_is_list($listAttributesContent)
            && count($listAttributesContent) === 1
            && is_array($listAttributesContent[0])
            && array_is_list($listAttributesContent[0])
        ) {
            $listAttributesContent = $listAttributesContent[0];
        }

        $listAttributes = $this->tuple($listAttributesContent, 3, 'OrderedList attributes');
        if (!is_int($listAttributes[0])) {
            throw new \InvalidArgumentException('OrderedList start number must be an integer');
        }

        $listStyleConstructor = $this->enumTag($listAttributes[1], 'list style');
        $listDelimiterConstructor = $this->enumTag($listAttributes[2], 'list delimiter');
        $attrs = [
            'start' => $listAttributes[0],
            'style' => $this->listStyleFromConstructor($listStyleConstructor),
            'delimiter' => $this->listDelimiterFromConstructor($listDelimiterConstructor),
            'listStyleConstructor' => $listStyleConstructor,
            'listStyleNative' => $listAttributes[1],
            'listDelimiterConstructor' => $listDelimiterConstructor,
            'listDelimiterNative' => $listAttributes[2],
        ];
        if ($this->isTaggedConstructor($listAttributesNative, 'ListAttributes')) {
            $attrs['listAttributesConstructor'] = 'ListAttributes';
            $attrs['listAttributesNative'] = $listAttributesNative;
        }

        return new AstNode('ordered_list', $attrs, $this->readListItems($this->listItemCollectionContent($tuple[1], 'OrderedList items')));
    }

    private function readBulletList(mixed $content): AstNode
    {
        $items = $this->readListItems($this->listItemCollectionContent($content, 'BulletList'));
        $attrs = $this->allListItemsAreTasks($items) ? ['taskList' => true] : [];

        return new AstNode('bullet_list', $attrs, $items);
    }

    /**
     * @param list<mixed> $items
     * @return list<AstNode>
     */
    private function readListItems(array $items): array
    {
        return array_map(function (mixed $item): AstNode {
            $attrs = ['listItemNative' => $item];
            $taskChecked = $this->listItemTaskChecked($item);
            if ($taskChecked !== null) {
                $attrs['taskChecked'] = $taskChecked;
            }

            return new AstNode(
                'list_item',
                $attrs,
                $this->readBlocks($this->listContent($item, 'list item'))
            );
        }, $items);
    }

    private function listItemTaskChecked(mixed $item): ?bool
    {
        $blocks = $this->listItemBlockPayload($item);
        if ($blocks === null || $blocks === []) {
            return null;
        }

        $firstBlock = $blocks[0];
        if (!is_array($firstBlock) || array_is_list($firstBlock) || !array_key_exists('taskChecked', $firstBlock)) {
            return null;
        }

        return is_bool($firstBlock['taskChecked']) ? $firstBlock['taskChecked'] : null;
    }

    /**
     * @return list<mixed>|null
     */
    private function listItemBlockPayload(mixed $item): ?array
    {
        if (!is_array($item) || !array_is_list($item) || $item === []) {
            return null;
        }

        if (
            count($item) === 1
            && is_array($item[0])
            && array_is_list($item[0])
        ) {
            return $item[0];
        }

        return $item;
    }

    /**
     * @param list<AstNode> $children
     */
    private function allListItemsAreTasks(array $children): bool
    {
        if ($children === []) {
            return false;
        }

        foreach ($children as $child) {
            if ($child->type !== 'list_item' || !is_bool($child->attr('taskChecked', null))) {
                return false;
            }
        }

        return true;
    }

    private function readDefinitionList(mixed $content): AstNode
    {
        $items = [];
        foreach ($this->definitionItemCollectionContent($content, 'DefinitionList') as $item) {
            $tuple = $this->singleWrappedTuple($item, 2, 'DefinitionList item');
            $definitions = [];
            foreach ($this->listContent($tuple[1], 'DefinitionList definitions') as $definition) {
                $definitions[] = new AstNode(
                    'definition',
                    ['definitionNative' => $definition],
                    $this->readBlocks($this->listContent($definition, 'definition blocks'))
                );
            }

            $termInlines = $this->readInlines($this->listContent($tuple[0], 'definition term'));
            $term = new AstNode('definition_term', [
                'text' => $this->plainText($termInlines),
                'definitionTermNative' => $tuple[0],
            ], $termInlines);
            $items[] = new AstNode('definition_item', [
                'definitionItemNative' => $item,
                'definitionTermNative' => $tuple[0],
                'definitionDefinitionsNative' => $tuple[1],
            ], [$term, ...$definitions]);
        }

        return new AstNode('definition_list', [], $items);
    }

    private function readLineBlock(mixed $content): AstNode
    {
        $lines = [];
        foreach ($this->inlineListCollectionContent($content, 'LineBlock') as $line) {
            $inlines = $this->readInlines($this->listContent($line, 'LineBlock line'));
            $lines[] = new AstNode('line', [
                'text' => $this->plainText($inlines),
                'lineNative' => $line,
            ], $inlines);
        }

        return new AstNode('line_block', [], $lines);
    }

    /**
     * @return list<mixed>
     */
    private function listItemCollectionContent(mixed $value, string $context): array
    {
        $items = $this->listContent($value, $context);
        if ($this->isSingleWrappedCollection($items, fn (mixed $item): bool => $this->looksLikeListPayload($item))) {
            return $items[0];
        }

        return $items;
    }

    /**
     * @return list<mixed>
     */
    private function definitionItemCollectionContent(mixed $value, string $context): array
    {
        $items = $this->listContent($value, $context);
        if ($this->isSingleWrappedCollection($items, fn (mixed $item): bool => $this->looksLikeDefinitionItemPayload($item))) {
            return $items[0];
        }

        return $items;
    }

    /**
     * @return list<mixed>
     */
    private function inlineListCollectionContent(mixed $value, string $context): array
    {
        $items = $this->listContent($value, $context);
        if ($this->isSingleWrappedCollection($items, fn (mixed $item): bool => $this->looksLikeListPayload($item))) {
            return $items[0];
        }

        return $items;
    }

    /**
     * @param list<mixed> $items
     */
    private function isSingleWrappedCollection(array $items, callable $itemPredicate): bool
    {
        if (
            count($items) !== 1
            || !is_array($items[0])
            || !array_is_list($items[0])
            || count($items[0]) <= 1
        ) {
            return false;
        }

        foreach ($items[0] as $item) {
            if (!$itemPredicate($item)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeListPayload(mixed $item): bool
    {
        return is_array($item) && array_is_list($item) && !$this->isTaggedObject($item);
    }

    private function looksLikeDefinitionItemPayload(mixed $item): bool
    {
        if (!$this->looksLikeListPayload($item)) {
            return false;
        }

        if (
            count($item) === 1
            && is_array($item[0])
            && array_is_list($item[0])
            && !$this->isTaggedObject($item[0])
        ) {
            $item = $item[0];
        }

        return count($item) === 2
            && is_array($item[0])
            && array_is_list($item[0])
            && is_array($item[1])
            && array_is_list($item[1])
            && !$this->isTaggedObject($item[0]);
    }

    private function readDivBlock(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Div');
        $blocks = $this->listContent($tuple[1], 'Div blocks');
        $attrs = $this->readAttrTuple($tuple[0]);
        $attrs['divBlocksNative'] = $blocks;

        return new AstNode('div', $attrs, $this->readBlocks($blocks));
    }

    private function readFigureBlock(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 3, 'Figure');
        $attrs = array_merge(
            $this->readAttrTuple($tuple[0]),
            $this->readCaptionAttrs($tuple[1], 'Figure')
        );
        $blocks = $this->listContent($tuple[2], 'Figure blocks');
        $attrs['figureBlocksNative'] = $blocks;

        return new AstNode('figure', $attrs, $this->figureChildren($this->readBlocks($blocks)));
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function figureChildren(array $blocks): array
    {
        if (
            count($blocks) === 1
            && in_array($blocks[0]->type, ['plain', 'paragraph'], true)
            && count($blocks[0]->children) === 1
            && $blocks[0]->children[0]->type === 'image'
        ) {
            return [$blocks[0]->children[0]];
        }

        return $blocks;
    }

    private function readTableBlock(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTupleContent($content, 'Table');
        if (count($tuple) === 5) {
            return $this->readLegacyTableBlock($tuple);
        }
        if (count($tuple) !== 6) {
            throw new \InvalidArgumentException('Table must have 6 entries');
        }

        $attrs = array_merge(
            $this->readAttrTuple($tuple[0]),
            $this->readTableCaptionAttrs($tuple[1]),
            $this->readTableColumnSpecAttrs($tuple[2])
        );

        $children = [];
        $head = $this->readTableSection($tuple[3], 'TableHead', 'table_head');
        if ($this->tableSectionHasContent($head)) {
            $children[] = $head;
        }

        $bodyCollection = $this->tableBodyCollectionContent($tuple[4]);
        if ($bodyCollection !== $tuple[4]) {
            $attrs['tableBodiesNative'] = $tuple[4];
        }

        foreach ($bodyCollection as $body) {
            $bodyNode = $this->readTableBody($body);
            if ($this->tableSectionHasContent($bodyNode)) {
                $children[] = $bodyNode;
            }
        }

        $foot = $this->readTableSection($tuple[5], 'TableFoot', 'table_foot');
        if ($this->tableSectionHasContent($foot)) {
            $children[] = $foot;
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @param list<mixed> $tuple
     */
    private function readLegacyTableBlock(array $tuple): AstNode
    {
        $captionInlines = $this->readInlines($this->listContent($tuple[0], 'legacy Table caption'));
        $attrs = $this->readLegacyTableColumnAttrs($tuple[1], $tuple[2]);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
            $attrs['captionBlocks'] = [new AstNode('plain', [], $captionInlines)];
            $attrs['caption'] = trim($this->plainText($captionInlines));
        } else {
            $attrs['caption'] = '';
        }

        $children = [];
        $headCells = $this->readLegacyTableCells($tuple[3], 'legacy Table header cells');
        if ($headCells !== []) {
            $children[] = new AstNode('table_head', [], [new AstNode('table_row', [], $headCells)]);
        }

        $bodyRows = $this->readLegacyTableRows($tuple[4]);
        if ($bodyRows !== []) {
            $children[] = new AstNode('table_body', [], $bodyRows);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function readLegacyTableColumnAttrs(mixed $alignments, mixed $widths): array
    {
        $alignmentValues = $this->listContent($alignments, 'legacy Table alignments');
        $widthValues = $this->listContent($widths, 'legacy Table widths');
        $alignmentConstructors = [];
        $alignmentNatives = [];
        $mappedAlignments = [];
        foreach ($alignmentValues as $alignment) {
            $constructor = $this->enumTag($alignment, 'legacy Table alignment');
            $alignmentConstructors[] = $constructor;
            $alignmentNatives[] = $alignment;
            $mappedAlignments[] = $this->tableAlignmentFromConstructor($constructor);
        }

        $mappedWidths = [];
        $columnWidthConstructors = [];
        $columnWidthNatives = [];
        foreach ($widthValues as $width) {
            [$mappedWidth, $constructor] = $this->readLegacyTableWidth($width);
            $mappedWidths[] = $mappedWidth;
            $columnWidthConstructors[] = $constructor;
            $columnWidthNatives[] = $width;
        }

        $attrs = [];
        if ($mappedAlignments !== []) {
            $attrs['alignments'] = $mappedAlignments;
            $attrs['alignmentConstructors'] = $alignmentConstructors;
            $attrs['alignmentNatives'] = $alignmentNatives;
        }
        if ($mappedWidths !== []) {
            $attrs['widths'] = $mappedWidths;
            $attrs['columnWidthConstructors'] = $columnWidthConstructors;
            $attrs['columnWidthNatives'] = $columnWidthNatives;
        }

        return $attrs;
    }

    /**
     * @return array{0:?float, 1:string}
     */
    private function readLegacyTableWidth(mixed $width): array
    {
        if (is_int($width) || is_float($width)) {
            return ((float) $width) === 0.0
                ? [null, 'ColWidthDefault']
                : [(float) $width, 'ColWidth'];
        }

        $constructor = $this->tableColumnWidthConstructor($width);

        return [$this->readTableColumnWidth($width), $constructor];
    }

    /**
     * @return list<AstNode>
     */
    private function readLegacyTableRows(mixed $rows): array
    {
        $nodes = [];
        foreach ($this->listContent($rows, 'legacy Table rows') as $row) {
            $nodes[] = new AstNode(
                'table_row',
                [],
                $this->readLegacyTableCells($row, 'legacy Table row cells')
            );
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function readLegacyTableCells(mixed $cells, string $context): array
    {
        $nodes = [];
        foreach ($this->listContent($cells, $context) as $cell) {
            $cellBlocks = $this->listContent($cell, 'legacy Table cell blocks');
            $blocks = $this->readBlocks($cellBlocks);
            $attrs = [
                'legacyTableCellBlocksNative' => $cellBlocks,
            ];
            $text = $this->plainTextFromBlocks($blocks);
            if ($text !== '') {
                $attrs['text'] = $text;
            }
            $nodes[] = new AstNode('table_cell', $attrs, $this->tableCellChildren($blocks));
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function readTableCaptionAttrs(mixed $caption): array
    {
        return $this->readCaptionAttrs($caption, 'Table');
    }

    /**
     * @return array<string, mixed>
     */
    private function readCaptionAttrs(mixed $caption, string $context): array
    {
        $content = $this->constructorContent($caption, 'Caption', "{$context} caption", false);
        $tuple = $this->captionTupleContent($content, "{$context} caption");
        $attrs = $this->captionConstructorAttrs($caption);

        $shortCaption = $this->readShortCaption($tuple[0], $context);
        $attrs = array_replace($attrs, $shortCaption['attrs']);
        $shortCaptionInlines = $shortCaption['children'];
        if ($shortCaptionInlines !== []) {
            $attrs['shortCaptionInlines'] = $shortCaptionInlines;
            $attrs['shortCaption'] = trim($this->plainText($shortCaptionInlines));
        }

        $captionBlocks = $this->readBlocks($this->listContent($tuple[1], "{$context} caption blocks"));
        if ($captionBlocks === []) {
            $attrs['caption'] = '';

            return $attrs;
        }

        $attrs['captionBlocks'] = $captionBlocks;
        $attrs['caption'] = $this->plainTextFromBlocks($captionBlocks);

        $captionInlines = $this->singleInlineCaptionBlock($captionBlocks);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        return $attrs;
    }

    /**
     * @return list<mixed>
     */
    private function captionTupleContent(mixed $content, string $context): array
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            $content = $content[0];
        }

        return $this->tuple($content, 2, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function captionConstructorAttrs(mixed $caption): array
    {
        if ($this->isTaggedConstructor($caption, 'Caption')) {
            return [
                'captionConstructor' => 'Caption',
                'captionNative' => $caption,
            ];
        }

        return [];
    }

    /**
     * @return array{children:list<AstNode>, attrs:array<string, mixed>}
     */
    private function readShortCaption(mixed $shortCaption, string $context): array
    {
        $attrs = [];
        if ($this->isTaggedConstructor($shortCaption, 'Just') || $this->isTaggedConstructor($shortCaption, 'Nothing')) {
            $attrs['shortCaptionMaybeConstructor'] = $shortCaption['t'];
            $attrs['shortCaptionMaybeNative'] = $shortCaption;
        }

        $shortCaption = $this->unwrapMaybeConstructor($shortCaption);
        if ($shortCaption === null || $shortCaption === []) {
            return ['children' => [], 'attrs' => $attrs];
        }

        if (
            is_array($shortCaption)
            && array_is_list($shortCaption)
            && count($shortCaption) === 1
            && $this->isTaggedConstructor($shortCaption[0], 'ShortCaption')
        ) {
            $shortCaption = $shortCaption[0];
        }

        if ($this->isTaggedConstructor($shortCaption, 'ShortCaption')) {
            $attrs['shortCaptionConstructor'] = 'ShortCaption';
            $attrs['shortCaptionNative'] = $shortCaption;
        }

        $content = $this->constructorContent($shortCaption, 'ShortCaption', "{$context} short caption", false);
        if (is_array($content) && array_is_list($content) && count($content) === 1 && is_array($content[0]) && array_is_list($content[0])) {
            $content = $content[0];
        }

        return [
            'children' => $this->readInlines($this->listContent($content, "{$context} short caption")),
            'attrs' => $attrs,
        ];
    }

    private function unwrapMaybeConstructor(mixed $value): mixed
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
            return $value;
        }

        if ($value['t'] === 'Just') {
            $content = $value['c'] ?? null;
            if (is_array($content) && array_is_list($content) && count($content) === 1) {
                return $content[0];
            }

            return $content;
        }

        if ($value['t'] === 'Nothing') {
            return [];
        }

        return $value;
    }

    private function isTaggedConstructor(mixed $value, string $constructor): bool
    {
        return $this->isTaggedObject($value) && $value['t'] === $constructor;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function singleInlineCaptionBlock(array $blocks): array
    {
        if (count($blocks) !== 1) {
            return [];
        }

        $block = $blocks[0];
        if (!in_array($block->type, ['plain', 'paragraph'], true)) {
            return [];
        }

        return $block->children;
    }

    /**
     * @return array<string, mixed>
     */
    private function readTableColumnSpecAttrs(mixed $colSpecs): array
    {
        $alignments = [];
        $widths = [];
        $alignmentConstructors = [];
        $alignmentNatives = [];
        $columnSpecNatives = [];
        $columnWidthConstructors = [];
        $columnWidthNatives = [];
        $columnSpecs = $this->tableColumnSpecCollectionContent($colSpecs);
        foreach ($columnSpecs as $colSpec) {
            $tuple = $this->singleWrappedTupleContent($colSpec, 2, 'Table column spec');
            $alignmentConstructor = $this->enumTag($tuple[0], 'table alignment');
            $columnWidthConstructor = $this->tableColumnWidthConstructor($tuple[1]);
            $alignments[] = $this->tableAlignmentFromConstructor($alignmentConstructor);
            $widths[] = $this->readTableColumnWidth($tuple[1]);
            $alignmentConstructors[] = $alignmentConstructor;
            $alignmentNatives[] = $tuple[0];
            $columnSpecNatives[] = $colSpec;
            $columnWidthConstructors[] = $columnWidthConstructor;
            $columnWidthNatives[] = $tuple[1];
        }

        if ($alignments === []) {
            return $columnSpecs !== $colSpecs ? ['columnSpecsNative' => $colSpecs] : [];
        }

        $attrs = [
            'alignments' => $alignments,
            'widths' => $widths,
            'alignmentConstructors' => $alignmentConstructors,
            'alignmentNatives' => $alignmentNatives,
            'columnSpecNatives' => $columnSpecNatives,
            'columnWidthConstructors' => $columnWidthConstructors,
            'columnWidthNatives' => $columnWidthNatives,
        ];

        if ($columnSpecs !== $colSpecs) {
            $attrs['columnSpecsNative'] = $colSpecs;
        }

        return $attrs;
    }

    /**
     * @return list<mixed>
     */
    private function tableColumnSpecCollectionContent(mixed $colSpecs): array
    {
        $items = $this->listContent($colSpecs, 'Table column specs');
        if (count($items) === 1 && is_array($items[0]) && array_is_list($items[0]) && $items[0] === []) {
            return [];
        }

        if (
            count($items) === 1
            && is_array($items[0])
            && array_is_list($items[0])
            && count($items[0]) > 1
            && $this->allColumnSpecPayloads($items[0])
        ) {
            return $items[0];
        }

        return $items;
    }

    /**
     * @param list<mixed> $items
     */
    private function allColumnSpecPayloads(array $items): bool
    {
        foreach ($items as $item) {
            if (!$this->looksLikeColumnSpecPayload($item)) {
                return false;
            }
        }

        return $items !== [];
    }

    private function looksLikeColumnSpecPayload(mixed $item): bool
    {
        if (!is_array($item) || !array_is_list($item)) {
            return false;
        }

        if (
            count($item) === 1
            && is_array($item[0])
            && array_is_list($item[0])
        ) {
            $item = $item[0];
        }

        return count($item) === 2
            && $this->looksLikeEnumConstructor($item[0])
            && (is_int($item[1]) || is_float($item[1]) || $this->looksLikeEnumConstructor($item[1]));
    }

    private function looksLikeEnumConstructor(mixed $value): bool
    {
        return is_string($value)
            || (
                is_array($value)
                && !array_is_list($value)
                && isset($value['t'])
                && is_string($value['t'])
            );
    }

    private function readTableSection(mixed $section, string $constructor, string $type): AstNode
    {
        $content = $this->constructorContent($section, $constructor, $constructor, false);
        $tuple = $this->singleWrappedTupleContent($content, 2, $constructor);
        $attrs = $this->readAttrTuple($tuple[0]);
        $rows = $this->tableRowCollectionContent($tuple[1], $constructor . ' rows');
        if ($rows !== $tuple[1]) {
            $attrs['tableRowsNative'] = $tuple[1];
        }

        return $this->withConstructorPayload(
            new AstNode($type, $attrs, $this->readTableRows($rows)),
            $constructor,
            $section
        );
    }

    private function readTableBody(mixed $body): AstNode
    {
        $content = $this->constructorContent($body, 'TableBody', 'TableBody', false);
        $tuple = $this->singleWrappedTupleContent($content, 4, 'TableBody');
        $attrs = $this->readAttrTuple($tuple[0]);

        $rowHeadColumns = $this->readTaggedInteger($tuple[1], 'RowHeadColumns', 'TableBody rowHeadColumns');
        if ($rowHeadColumns > 0) {
            $attrs['rowHeadColumns'] = $rowHeadColumns;
        }
        $attrs['rowHeadColumnsConstructor'] = 'RowHeadColumns';
        $attrs['rowHeadColumnsNative'] = $tuple[1];

        $headRowCollection = $this->tableRowCollectionContent($tuple[2], 'TableBody head rows');
        if ($headRowCollection !== $tuple[2]) {
            $attrs['headRowsNative'] = $tuple[2];
        }
        $headRows = $this->readTableRows($headRowCollection);
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
        }

        $rowCollection = $this->tableRowCollectionContent($tuple[3], 'TableBody rows');
        if ($rowCollection !== $tuple[3]) {
            $attrs['tableRowsNative'] = $tuple[3];
        }

        return $this->withConstructorPayload(
            new AstNode('table_body', $attrs, $this->readTableRows($rowCollection)),
            'TableBody',
            $body
        );
    }

    private function tableSectionHasContent(AstNode $section): bool
    {
        if ($section->children !== []) {
            return true;
        }

        if ($section->type === 'table_body') {
            $headRows = $section->attr('headRows', []);
            if (is_array($headRows) && $headRows !== []) {
                return true;
            }
        }

        if ($this->nativePayloadHasSidecars($section->attr('native'))) {
            return true;
        }

        return $this->hasContentAttrs($section);
    }

    private function nativePayloadHasSidecars(mixed $native): bool
    {
        if (!is_array($native) || array_is_list($native)) {
            return false;
        }

        foreach ($native as $key => $_value) {
            if ($key !== 't' && $key !== 'c') {
                return true;
            }
        }

        return false;
    }

    private function hasContentAttrs(AstNode $node): bool
    {
        foreach ($node->attrs as $key => $_value) {
            if (!in_array($key, ['constructor', 'native', 'attrConstructor', 'attrNative'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AstNode>
     */
    private function readTableRows(mixed $rows): array
    {
        $nodes = [];
        foreach ($this->listContent($rows, 'Table rows') as $row) {
            $content = $this->constructorContent($row, 'Row', 'Table row', false);
            $tuple = $this->singleWrappedTupleContent($content, 2, 'Table row');
            $attrs = $this->readAttrTuple($tuple[0]);
            $cells = $this->tableCellCollectionContent($tuple[1]);
            if ($cells !== $tuple[1]) {
                $attrs['tableCellsNative'] = $tuple[1];
            }
            $nodes[] = $this->withConstructorPayload(
                new AstNode('table_row', $attrs, $this->readTableCells($cells)),
                'Row',
                $row
            );
        }

        return $nodes;
    }

    /**
     * @return list<mixed>
     */
    private function tableBodyCollectionContent(mixed $bodies): array
    {
        return $this->singleWrappedTaggedCollection($bodies, 'Table bodies', 'TableBody');
    }

    /**
     * @return list<mixed>
     */
    private function tableRowCollectionContent(mixed $rows, string $context): array
    {
        return $this->singleWrappedTaggedCollection($rows, $context, 'Row');
    }

    /**
     * @return list<mixed>
     */
    private function tableCellCollectionContent(mixed $cells): array
    {
        return $this->singleWrappedTaggedCollection($cells, 'Table cells', 'Cell');
    }

    /**
     * @return list<mixed>
     */
    private function singleWrappedTaggedCollection(mixed $value, string $context, string $constructor): array
    {
        $items = $this->listContent($value, $context);
        if (count($items) === 1 && is_array($items[0]) && array_is_list($items[0]) && $items[0] === []) {
            return [];
        }

        if (
            count($items) === 1
            && is_array($items[0])
            && array_is_list($items[0])
            && $items[0] !== []
            && $this->allTaggedConstructorPayloads($items[0], $constructor)
        ) {
            return $items[0];
        }

        return $items;
    }

    /**
     * @param list<mixed> $items
     */
    private function allTaggedConstructorPayloads(array $items, string $constructor): bool
    {
        foreach ($items as $item) {
            if (!$this->isTaggedConstructor($item, $constructor)) {
                return false;
            }
        }

        return $items !== [];
    }

    /**
     * @return list<AstNode>
     */
    private function readTableCells(mixed $cells): array
    {
        $nodes = [];
        foreach ($this->listContent($cells, 'Table cells') as $cell) {
            $nodes[] = $this->readTableCell($cell);
        }

        return $nodes;
    }

    private function readTableCell(mixed $cell): AstNode
    {
        $content = $this->constructorContent($cell, 'Cell', 'Table cell', false);
        $tuple = $this->singleWrappedTupleContent($content, 5, 'Table cell');
        $attrs = $this->readAttrTuple($tuple[0]);

        $alignmentConstructor = $this->enumTag($tuple[1], 'table alignment');
        $alignment = $this->tableAlignmentFromConstructor($alignmentConstructor);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }
        $attrs['alignmentConstructor'] = $alignmentConstructor;
        $attrs['alignmentNative'] = $tuple[1];

        $rowspan = $this->readTaggedInteger($tuple[2], 'RowSpan', 'Table cell rowspan');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        $attrs['rowSpanConstructor'] = 'RowSpan';
        $attrs['rowSpanNative'] = $tuple[2];

        $colspan = $this->readTaggedInteger($tuple[3], 'ColSpan', 'Table cell colspan');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        $attrs['colSpanConstructor'] = 'ColSpan';
        $attrs['colSpanNative'] = $tuple[3];

        $blocks = $this->readBlocks($this->listContent($tuple[4], 'Table cell blocks'));
        $text = $this->plainTextFromBlocks($blocks);
        if ($text !== '') {
            $attrs['text'] = $text;
        }

        return $this->withConstructorPayload(
            new AstNode('table_cell', $attrs, $this->tableCellChildren($blocks)),
            'Cell',
            $cell
        );
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function tableCellChildren(array $blocks): array
    {
        if (count($blocks) === 1 && in_array($blocks[0]->type, ['plain', 'paragraph'], true)) {
            return $blocks[0]->children;
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function readInlines(mixed $inlines): array
    {
        $inlines = $this->singleWrappedListContent($inlines, 'inlines');

        return array_map(fn (mixed $inline): AstNode => $this->readInline($inline), $inlines);
    }

    private function readInline(mixed $value): AstNode
    {
        [$tag, $content] = $this->tagged($value, 'inline');

        $node = match ($tag) {
            'Str' => new AstNode('text', ['text' => $this->stringConstructorContent($content, 'Str content')]),
            'Space' => new AstNode('space'),
            'SoftBreak' => new AstNode('softbreak'),
            'LineBreak' => new AstNode('linebreak'),
            'Emph' => new AstNode('emph', [], $this->readInlines($this->listContent($content, 'Emph'))),
            'Strong' => new AstNode('strong', [], $this->readInlines($this->listContent($content, 'Strong'))),
            'Underline' => new AstNode('underline', [], $this->readInlines($this->listContent($content, 'Underline'))),
            'Strikeout' => new AstNode('strikeout', [], $this->readInlines($this->listContent($content, 'Strikeout'))),
            'Superscript' => new AstNode('superscript', [], $this->readInlines($this->listContent($content, 'Superscript'))),
            'Subscript' => new AstNode('subscript', [], $this->readInlines($this->listContent($content, 'Subscript'))),
            'SmallCaps' => new AstNode('small_caps', [], $this->readInlines($this->listContent($content, 'SmallCaps'))),
            'Quoted' => $this->readQuotedInline($content),
            'Code' => $this->readCodeInline($content),
            'Math' => $this->readMathInline($content),
            'RawInline' => $this->readRawInline($content),
            'Cite' => $this->readCiteInline($content),
            'Link' => $this->readTargetInline('link', $content),
            'Image' => $this->readTargetInline('image', $content),
            'Note' => new AstNode('note', $this->noteAttrs($value), $this->readBlocks($this->listContent($content, 'Note'))),
            'Span' => $this->readSpanInline($content),
            default => $this->nativeFallbackNode('native_inline', $tag, $value),
        };

        return $this->withConstructorPayload($node, $tag, $value);
    }

    private function withConstructorPayload(AstNode $node, string $constructor, mixed $native): AstNode
    {
        return new AstNode(
            $node->type,
            array_replace(['constructor' => $constructor, 'native' => $native], $node->attrs),
            $node->children
        );
    }

    /**
     * @return array{label?: string}
     */
    private function noteAttrs(mixed $native): array
    {
        if (!is_array($native) || array_is_list($native)) {
            return [];
        }

        $label = $native['noteLabel'] ?? null;
        if (!is_string($label) || !$this->isValidNoteLabel($label)) {
            return [];
        }

        return ['label' => $label];
    }

    private function isValidNoteLabel(string $label): bool
    {
        return trim($label) === $label
            && $label !== ''
            && preg_match('/[\]\s]/u', $label) !== 1;
    }

    private function nativeFallbackNode(string $type, string $constructor, mixed $native): AstNode
    {
        if (!is_array($native) || array_is_list($native)) {
            throw new \InvalidArgumentException("Pandoc {$type} fallback must carry a tagged native constructor");
        }

        return new AstNode($type, [
            'constructor' => $constructor,
            'native' => $native,
        ]);
    }

    private function readQuotedInline(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Quoted');
        $quoteTypeNative = $tuple[0];
        $quoteTypeConstructor = $this->enumTag($quoteTypeNative, 'quote type');

        return new AstNode('quoted', [
            'kind' => $this->quoteTypeFromConstructor($quoteTypeConstructor),
            'quoteTypeConstructor' => $quoteTypeConstructor,
            'quoteTypeNative' => $quoteTypeNative,
        ], $this->readInlines($this->listContent($tuple[1], 'Quoted inlines')));
    }

    private function readCodeInline(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Code');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Code text must be a string');
        }

        return new AstNode('code', array_merge($this->readAttrTuple($tuple[0]), ['text' => $tuple[1]]));
    }

    private function readMathInline(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Math');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Math text must be a string');
        }

        $mathTypeNative = $tuple[0];
        $mathTypeConstructor = $this->enumTag($mathTypeNative, 'Math type');

        return new AstNode('math', [
            'display' => $mathTypeConstructor === 'DisplayMath',
            'mathTypeConstructor' => $mathTypeConstructor,
            'mathTypeNative' => $mathTypeNative,
            'text' => $tuple[1],
        ]);
    }

    private function readRawInline(mixed $content): AstNode
    {
        [$format, $formatNative, $text] = $this->formatTextTuple($content, 'RawInline');
        $attrs = ['format' => $format, 'text' => $text];
        if ($formatNative !== null) {
            $attrs['formatConstructor'] = 'Format';
            $attrs['formatNative'] = $formatNative;
        }
        $normalized = strtolower($format);

        if ($this->isHtmlRawFormat($normalized)) {
            return new AstNode('raw_html_inline', array_merge($attrs, ['html' => $text]));
        }

        if ($this->isTexRawFormat($normalized)) {
            return new AstNode('raw_tex_inline', array_merge($attrs, ['tex' => $text]));
        }

        if ($this->isMarkdownRawFormat($normalized)) {
            return new AstNode('raw_markdown', array_merge($attrs, ['markdown' => $text]));
        }

        return new AstNode('raw_inline', $attrs);
    }

    private function readCiteInline(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Cite');
        $recordsNative = $this->listContent($tuple[0], 'Cite citation records');
        $records = $this->singleWrappedListContent($recordsNative, 'Cite citation records');
        if ($records === []) {
            throw new \InvalidArgumentException('Cite must contain at least one citation record');
        }

        $sourceInlines = $this->readInlines($this->listContent($tuple[1], 'Cite source inlines'));
        $sourceText = $this->plainText($sourceInlines);
        $citations = array_map(fn (mixed $record): AstNode => $this->readCitationRecord($record), $records);
        if (count($citations) === 1) {
            $attrs = array_replace(['citationRecordsNative' => $recordsNative], $citations[0]->attrs);
            if ($sourceText !== '') {
                $attrs['text'] = $sourceText;
            }

            return new AstNode('citation', $attrs, $sourceInlines);
        }

        $attrs = ['citationRecordsNative' => $recordsNative];
        if ($sourceText !== '') {
            $attrs['text'] = $sourceText;
        }
        if ($sourceInlines !== []) {
            $attrs['citationSourceInlines'] = $sourceInlines;
        }

        return new AstNode('citation_group', $attrs, $citations);
    }

    private function readCitationRecord(mixed $record): AstNode
    {
        $native = $record;
        $record = $this->citationRecordPayload($record);

        $id = $record['citationId'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new \InvalidArgumentException('Cite citation record must contain a non-empty citationId');
        }

        $prefixNative = $record['citationPrefix'] ?? [];
        $suffixNative = $record['citationSuffix'] ?? [];
        $prefix = $this->readInlines($this->singleWrappedListContent($prefixNative, 'Cite citationPrefix'));
        $suffix = $this->readInlines($this->singleWrappedListContent($suffixNative, 'Cite citationSuffix'));
        $citationModeNative = $record['citationMode'] ?? ['t' => 'NormalCitation'];
        $citationModeConstructor = $this->readCitationModeConstructor($citationModeNative);
        $mode = $this->readCitationMode($citationModeConstructor);
        $attrs = [
            'id' => $id,
            'text' => $this->citationRecordSourceText($id, $mode, $prefix, $suffix),
            'mode' => $mode,
            'citationModeConstructor' => $citationModeConstructor,
            'citationModeNative' => $citationModeNative,
        ];
        if ($prefix !== []) {
            $attrs['prefix'] = $prefix;
        }
        if ($prefix !== [] || $prefixNative !== []) {
            $attrs['citationPrefixNative'] = $prefixNative;
        }
        if ($suffix !== []) {
            $attrs['suffix'] = $suffix;
        }
        if ($suffix !== [] || $suffixNative !== []) {
            $attrs['citationSuffixNative'] = $suffixNative;
        }

        if (array_key_exists('citationNoteNum', $record)) {
            if (!is_int($record['citationNoteNum'])) {
                throw new \InvalidArgumentException('Cite citationNoteNum must be an integer');
            }
            $attrs['citationNoteNum'] = $record['citationNoteNum'];
        }
        if (array_key_exists('citationHash', $record)) {
            if (!is_int($record['citationHash'])) {
                throw new \InvalidArgumentException('Cite citationHash must be an integer');
            }
            $attrs['citationHash'] = $record['citationHash'];
        }

        return new AstNode('citation', array_replace([
            'citationConstructor' => 'Citation',
            'citationNative' => $native,
        ], $attrs), [
            new AstNode('text', ['text' => $attrs['text']]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function citationRecordPayload(mixed $record): array
    {
        if (!is_array($record) || array_is_list($record)) {
            throw new \InvalidArgumentException('Cite citation record must be an object');
        }

        if (!$this->isTaggedConstructor($record, 'Citation')) {
            return $record;
        }

        $content = $record['c'] ?? null;
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && !array_is_list($content[0])
        ) {
            $content = $content[0];
        }

        if (!is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new \InvalidArgumentException('Cite Citation constructor content must be an object');
        }

        return $content;
    }

    private function readCitationModeConstructor(mixed $value): string
    {
        return $this->enumTag($value, 'citation mode');
    }

    private function readCitationMode(string $constructor): string
    {
        return match ($constructor) {
            'NormalCitation' => 'normal',
            'AuthorInText' => 'author_in_text',
            'SuppressAuthor' => 'suppress_author',
            default => throw new \InvalidArgumentException('Unsupported Pandoc citation mode'),
        };
    }

    /**
     * @param list<AstNode> $prefix
     * @param list<AstNode> $suffix
     */
    private function citationRecordSourceText(string $id, string $mode, array $prefix, array $suffix): string
    {
        $prefixText = $this->plainText($prefix);
        $suffixText = $this->plainText($suffix);
        $token = ($mode === 'suppress_author' ? '-@' : '@') . $id;
        $text = $prefixText === '' ? $token : $prefixText . ' ' . $token;

        return $suffixText === '' ? $text : $text . ', ' . $suffixText;
    }

    private function readTargetInline(string $type, mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTupleContent($content, ucfirst($type));
        if (count($tuple) === 3) {
            $attrs = $this->readAttrTuple($tuple[0]);
            $labelContent = $tuple[1];
            $targetContent = $tuple[2];
        } elseif (count($tuple) === 2) {
            $attrs = [];
            $labelContent = $tuple[0];
            $targetContent = $tuple[1];
        } else {
            throw new \InvalidArgumentException(ucfirst($type) . ' must have 2 or 3 entries');
        }

        [$target, $targetNative] = $this->targetTupleContent($targetContent, ucfirst($type) . ' target');
        if (!is_string($target[0]) || !is_string($target[1])) {
            throw new \InvalidArgumentException(ucfirst($type) . ' target entries must be strings');
        }

        $label = $this->readInlines($this->listContent($labelContent, ucfirst($type) . ' label'));
        $targetAttrs = [
            'url' => $target[0],
            'title' => $target[1],
            'targetNative' => $targetNative,
        ];
        if ($this->isTaggedConstructor($targetNative, 'Target')) {
            $targetAttrs['targetConstructor'] = 'Target';
        }

        $attrs = array_merge($attrs, $targetAttrs);
        if ($type === 'image') {
            $alt = trim($this->plainText($label));
            if ($alt !== '') {
                $attrs['alt'] = $alt;
            }
        }

        return new AstNode($type, $attrs, $label);
    }

    /**
     * @return array{0:list<mixed>, 1:list<mixed>}
     */
    private function targetTupleContent(mixed $value, string $context): array
    {
        if ($this->isTaggedConstructor($value, 'Target')) {
            $target = $this->singleWrappedTupleContent($value['c'] ?? null, $context);
            if (count($target) < 2) {
                throw new \InvalidArgumentException("{$context} must have at least 2 entries");
            }

            return [$target, $value];
        }

        $native = $this->listContent($value, $context);
        $target = $native;
        if (
            count($target) === 1
            && is_array($target[0])
        ) {
            if ($this->isTaggedConstructor($target[0], 'Target')) {
                $wrappedTarget = $this->singleWrappedTupleContent($target[0]['c'] ?? null, $context);
                if (count($wrappedTarget) < 2) {
                    throw new \InvalidArgumentException("{$context} must have at least 2 entries");
                }

                return [$wrappedTarget, $native];
            }

            if (!array_is_list($target[0])) {
                return [$target, $native];
            }

            $target = $this->listContent($target[0], $context);
        }

        if (count($target) < 2) {
            throw new \InvalidArgumentException("{$context} must have at least 2 entries");
        }

        return [$target, $native];
    }

    private function readSpanInline(mixed $content): AstNode
    {
        $tuple = $this->singleWrappedTuple($content, 2, 'Span');

        return new AstNode('span', $this->readAttrTuple($tuple[0]), $this->readInlines($this->listContent($tuple[1], 'Span inlines')));
    }

    /**
     * @return array<string, mixed>
     */
    private function readAttrTuple(mixed $value): array
    {
        $native = $value;
        $content = $this->constructorContent($value, 'Attr', 'Attr', false);
        $content = $this->attrTupleContent($content);
        $attrTuple = $this->listContent($content, 'Attr');
        if (count($attrTuple) < 3) {
            throw new \InvalidArgumentException('Attr must have at least 3 entries');
        }
        $tuple = array_slice($attrTuple, 0, 3);
        if (!is_string($tuple[0])) {
            throw new \InvalidArgumentException('Attr identifier must be a string');
        }

        $classes = $this->listContent($tuple[1], 'Attr classes');
        foreach ($classes as $class) {
            if (!is_string($class)) {
                throw new \InvalidArgumentException('Attr classes must be strings');
            }
        }

        $attributes = $this->listContent($tuple[2], 'Attr key-values');
        $mappedAttributes = [];
        foreach ($attributes as $attribute) {
            $keyValue = $this->tuple($attribute, 2, 'Attr key-value');
            if (!is_string($keyValue[0]) || !is_string($keyValue[1])) {
                throw new \InvalidArgumentException('Attr key-value entries must be strings');
            }
            $mappedAttributes[$keyValue[0]] = $keyValue[1];
        }

        $attrs = [
            'attrConstructor' => 'Attr',
            'attrNative' => $native,
        ];
        if ($tuple[0] !== '') {
            $attrs['id'] = $tuple[0];
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($mappedAttributes !== []) {
            $attrs['attributes'] = $mappedAttributes;
        }

        return $attrs;
    }

    private function attrTupleContent(mixed $content): mixed
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
            && count($content[0]) >= 3
        ) {
            return $content[0];
        }

        return $content;
    }

    private function formatTextTuple(mixed $content, string $context): array
    {
        $tuple = $this->singleWrappedTuple($content, 2, $context);
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException("{$context} content must be [format, text]");
        }

        [$format, $native] = $this->readFormatValue($tuple[0], $context . ' format');

        return [$format, $native, $tuple[1]];
    }

    /**
     * @return array{0:string, 1:array<string, mixed>|null}
     */
    private function readFormatValue(mixed $value, string $context): array
    {
        if (is_string($value)) {
            return [$value, null];
        }

        $content = $this->singleWrappedScalarContent($this->constructorContent($value, 'Format', $context));
        if (!is_string($content)) {
            throw new \InvalidArgumentException("{$context} must contain a string");
        }
        if (!is_array($value) || array_is_list($value)) {
            return [$content, null];
        }

        return [$content, $value];
    }

    private function stringConstructorContent(mixed $content, string $context): string
    {
        $content = $this->singleWrappedScalarContent($content);
        if (!is_string($content)) {
            throw new \InvalidArgumentException("{$context} must be a string");
        }

        return $content;
    }

    private function singleWrappedScalarContent(mixed $content): mixed
    {
        while (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        return $content;
    }

    /**
     * @return array{string, mixed}
     */
    private function tagged(mixed $value, string $context): array
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
            throw new \InvalidArgumentException("Pandoc {$context} must be a tagged object");
        }

        return [$value['t'], $value['c'] ?? null];
    }

    private function enumTag(mixed $value, string $context): string
    {
        if (is_string($value)) {
            return $value;
        }

        [$tag] = $this->tagged($value, $context);

        return $tag;
    }

    /**
     * @return list<mixed>
     */
    private function listContent(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("{$context} content must be a list");
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function singleWrappedListContent(mixed $value, string $context): array
    {
        $list = $this->listContent($value, $context);
        if (
            count($list) === 1
            && is_array($list[0])
            && array_is_list($list[0])
        ) {
            return $list[0];
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectContent(mixed $value, string $context): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException("{$context} content must be an object");
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function tuple(mixed $value, int $size, string $context): array
    {
        $tuple = $this->singleWrappedTupleContent($value, $size, $context);
        if (count($tuple) !== $size) {
            throw new \InvalidArgumentException("{$context} must have {$size} entries");
        }

        return $tuple;
    }

    /**
     * @return list<mixed>
     */
    private function singleWrappedTuple(mixed $value, int $size, string $context): array
    {
        $tuple = $this->singleWrappedTupleContent($value, $context);
        if (count($tuple) !== $size) {
            throw new \InvalidArgumentException("{$context} must have {$size} entries");
        }

        return $tuple;
    }

    /**
     * @return list<mixed>
     */
    private function singleWrappedTupleContent(mixed $value, int|string $sizeOrContext, ?string $context = null): array
    {
        $size = is_int($sizeOrContext) ? $sizeOrContext : null;
        $context = $context ?? (string) $sizeOrContext;
        $tuple = $this->listContent($value, $context);

        if (
            count($tuple) === 1
            && is_array($tuple[0])
            && array_is_list($tuple[0])
            && ($size === null ? count($tuple[0]) > 1 : count($tuple[0]) === $size)
        ) {
            return $tuple[0];
        }

        if ($size !== null && count($tuple) !== $size) {
            throw new \InvalidArgumentException("{$context} must have {$size} entries");
        }

        return $tuple;
    }

    /**
     * @return list<mixed>
     */
    private function tuplePrefix(mixed $value, int $size, string $context): array
    {
        $tuple = $this->listContent($value, $context);
        if (count($tuple) < $size) {
            throw new \InvalidArgumentException("{$context} must have at least {$size} entries");
        }

        return array_slice($tuple, 0, $size);
    }

    private function listStyleFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'DefaultStyle' => 'default',
            'Decimal' => 'decimal',
            'Example' => 'example',
            'LowerRoman' => 'lower_roman',
            'UpperRoman' => 'upper_roman',
            'LowerAlpha' => 'lower_alpha',
            'UpperAlpha' => 'upper_alpha',
            default => throw new \InvalidArgumentException('Unsupported Pandoc list style'),
        };
    }

    private function listDelimiterFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'DefaultDelim' => 'default',
            'Period' => 'period',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => throw new \InvalidArgumentException('Unsupported Pandoc list delimiter'),
        };
    }

    private function tableAlignmentFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            'AlignDefault' => 'default',
            default => throw new \InvalidArgumentException('Unsupported Pandoc table alignment'),
        };
    }

    private function tableColumnWidthConstructor(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return 'ColWidth';
        }

        return $this->enumTag($value, 'table column width');
    }

    private function readTableColumnWidth(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $tag = $this->enumTag($value, 'table column width');
        if ($tag === 'ColWidthDefault') {
            return null;
        }
        if ($tag !== 'ColWidth') {
            throw new \InvalidArgumentException('Unsupported Pandoc table column width');
        }

        $content = $this->singleWrappedScalarContent($this->constructorContent($value, 'ColWidth', 'table column width'));
        if (!is_int($content) && !is_float($content)) {
            throw new \InvalidArgumentException('ColWidth content must be numeric');
        }

        return (float) $content;
    }

    private function readTaggedInteger(mixed $value, string $tag, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && array_is_list($value) && count($value) === 1 && is_int($value[0])) {
            return $value[0];
        }

        $content = $this->singleWrappedScalarContent($this->constructorContent($value, $tag, $context));
        if (!is_int($content)) {
            throw new \InvalidArgumentException("{$context} must contain an integer");
        }

        return $content;
    }

    private function constructorContent(mixed $value, string $tag, string $context, bool $requireTag = true): mixed
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
            if ($requireTag) {
                throw new \InvalidArgumentException("{$context} must be a {$tag} constructor");
            }

            return $value;
        }

        if ($value['t'] !== $tag) {
            throw new \InvalidArgumentException("{$context} must be a {$tag} constructor");
        }

        return $value['c'] ?? null;
    }

    private function quoteTypeFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'SingleQuote' => 'single',
            'DoubleQuote' => 'double',
            default => throw new \InvalidArgumentException('Unsupported Pandoc quote type'),
        };
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text',
                'code',
                'math',
                'code_block',
                'raw_block',
                'raw_html',
                'raw_html_inline',
                'raw_markdown',
                'raw_inline',
                'raw_tex',
                'raw_tex_inline' => (string) $node->attr('text', ''),
                'space', 'softbreak' => ' ',
                'linebreak' => "\n",
                default => $this->plainText($node->children),
            };
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainTextFromBlocks(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = trim($this->plainText([$block]));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = $this->rawFormatBase($format);

        return in_array($format, [
            'markdown',
            'markdown_strict',
            'markdown_phpextra',
            'markdown_github',
            'markdown_mmd',
            'pandoc',
            'commonmark',
            'commonmark_x',
            'gfm',
        ], true) || in_array($baseFormat, [
            'markdown',
            'markdown_strict',
            'markdown_phpextra',
            'markdown_github',
            'markdown_mmd',
            'pandoc',
            'commonmark',
            'commonmark_x',
            'gfm',
        ], true);
    }

    private function isHtmlRawFormat(string $format): bool
    {
        $baseFormat = $this->rawFormatBase($format);

        return in_array($format, ['html', 'html4', 'html5', 'xhtml'], true)
            || in_array($baseFormat, ['html', 'html4', 'html5', 'xhtml'], true);
    }

    private function isTexRawFormat(string $format): bool
    {
        $baseFormat = $this->rawFormatBase($format);

        return in_array($format, ['tex', 'latex', 'context'], true)
            || in_array($baseFormat, ['tex', 'latex', 'context'], true);
    }

    private function rawFormatBase(string $format): string
    {
        $format = strtolower($format);
        $format = str_replace('-', '+', $format);

        return explode('+', $format, 2)[0];
    }
}
