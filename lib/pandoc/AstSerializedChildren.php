<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Immutable compact storage for a completed AST child list.
 *
 * The payload is produced only from in-memory AstNode instances. Text and
 * break leaves use compact tags; uncommon inline nodes retain exact PHP
 * attributes in a bounded length-prefixed record.
 */
final class AstSerializedChildren
{
    private const NODE_TEXT = 1;
    private const NODE_SOFTBREAK = 2;
    private const NODE_LINEBREAK = 3;
    private const NODE_GENERIC = 4;

    /** @var array<string, int> */
    private const TYPE_CODES = [
        'emph' => 1,
        'strong' => 2,
        'span' => 3,
        'link' => 4,
        'image' => 5,
        'subscript' => 6,
        'superscript' => 7,
        'underline' => 8,
        'strikeout' => 9,
        'smallcaps' => 10,
        'code' => 11,
        'raw_html_inline' => 12,
        'raw_tex_inline' => 13,
        'quoted' => 14,
        'cite' => 15,
        'note' => 16,
        'math' => 17,
        'space' => 18,
    ];

    /** @var array<int, string> */
    private const TYPE_NAMES = [
        1 => 'emph',
        2 => 'strong',
        3 => 'span',
        4 => 'link',
        5 => 'image',
        6 => 'subscript',
        7 => 'superscript',
        8 => 'underline',
        9 => 'strikeout',
        10 => 'smallcaps',
        11 => 'code',
        12 => 'raw_html_inline',
        13 => 'raw_tex_inline',
        14 => 'quoted',
        15 => 'cite',
        16 => 'note',
        17 => 'math',
        18 => 'space',
    ];

    /** @var string */
    private readonly string $payload;

    /**
     * @param list<AstNode> $children
     */
    public function __construct(array $children)
    {
        $this->payload = self::encodeChildren($children);
    }

    /**
     * @return list<AstNode>
     */
    public function materialize(): array
    {
        $offset = 0;
        $children = self::decodeChildren($this->payload, $offset);
        if ($offset !== strlen($this->payload)) {
            throw new \LogicException('Compact AST child payload has trailing bytes.');
        }

        return $children;
    }

    /**
     * @param list<AstNode> $children
     */
    private static function encodeChildren(array $children): string
    {
        $payload = self::encodeUnsignedInteger(count($children));
        foreach ($children as $child) {
            $payload .= self::encodeNode($child);
        }

        return $payload;
    }

    private static function encodeNode(AstNode $node): string
    {
        $attrs = $node->baseAttrs();
        $children = $node->children();
        if (
            $node->type === 'text'
            && $children === []
            && count($attrs) === 1
            && array_key_exists('text', $attrs)
            && is_string($attrs['text'])
        ) {
            return chr(self::NODE_TEXT) . self::encodeString($attrs['text']);
        }
        if ($node->type === 'softbreak' && $attrs === [] && $children === []) {
            return chr(self::NODE_SOFTBREAK);
        }
        if ($node->type === 'linebreak' && $attrs === [] && $children === []) {
            return chr(self::NODE_LINEBREAK);
        }

        $typeCode = self::TYPE_CODES[$node->type] ?? 0;
        $attrsPayload = serialize($attrs);
        if (!is_string($attrsPayload)) {
            throw new \LogicException('Unable to encode compact AST node attributes.');
        }

        $payload = chr(self::NODE_GENERIC) . self::encodeUnsignedInteger($typeCode);
        if ($typeCode === 0) {
            $payload .= self::encodeString($node->type);
        }

        return $payload
            . self::encodeString($attrsPayload)
            . self::encodeChildren($children);
    }

    /**
     * @return list<AstNode>
     */
    private static function decodeChildren(string $payload, int &$offset): array
    {
        $count = self::decodeUnsignedInteger($payload, $offset);
        $children = [];
        for ($index = 0; $index < $count; $index++) {
            $children[] = self::decodeNode($payload, $offset);
        }

        return $children;
    }

    private static function decodeNode(string $payload, int &$offset): AstNode
    {
        $tag = self::readByte($payload, $offset);
        if ($tag === self::NODE_TEXT) {
            return new AstNode('text', ['text' => self::decodeString($payload, $offset)]);
        }
        if ($tag === self::NODE_SOFTBREAK) {
            return new AstNode('softbreak');
        }
        if ($tag === self::NODE_LINEBREAK) {
            return new AstNode('linebreak');
        }
        if ($tag !== self::NODE_GENERIC) {
            throw new \LogicException('Compact AST child payload contains an unknown node tag.');
        }

        $typeCode = self::decodeUnsignedInteger($payload, $offset);
        $type = $typeCode === 0
            ? self::decodeString($payload, $offset)
            : (self::TYPE_NAMES[$typeCode] ?? null);
        if ($type === null || $type === '') {
            throw new \LogicException('Compact AST child payload contains an unknown node type.');
        }

        $attrs = unserialize(self::decodeString($payload, $offset), ['allowed_classes' => true]);
        if (!is_array($attrs)) {
            throw new \LogicException('Compact AST child attributes did not decode to an array.');
        }

        return new AstNode($type, $attrs, self::decodeChildren($payload, $offset));
    }

    private static function encodeString(string $value): string
    {
        return self::encodeUnsignedInteger(strlen($value)) . $value;
    }

    private static function decodeString(string $payload, int &$offset): string
    {
        $length = self::decodeUnsignedInteger($payload, $offset);
        if ($length < 0 || $offset + $length > strlen($payload)) {
            throw new \LogicException('Compact AST child payload ends inside a string.');
        }

        $value = substr($payload, $offset, $length);
        $offset += $length;

        return $value;
    }

    private static function encodeUnsignedInteger(int $value): string
    {
        if ($value < 0) {
            throw new \LogicException('Compact AST payload cannot encode a negative integer.');
        }

        $encoded = '';
        do {
            $byte = $value & 0x7f;
            $value = intdiv($value, 128);
            $encoded .= chr($value === 0 ? $byte : $byte | 0x80);
        } while ($value !== 0);

        return $encoded;
    }

    private static function decodeUnsignedInteger(string $payload, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        while (true) {
            $byte = self::readByte($payload, $offset);
            $value |= ($byte & 0x7f) << $shift;
            if (($byte & 0x80) === 0) {
                return $value;
            }
            $shift += 7;
            if ($shift > 56) {
                throw new \LogicException('Compact AST child payload contains an oversized integer.');
            }
        }
    }

    private static function readByte(string $payload, int &$offset): int
    {
        if ($offset >= strlen($payload)) {
            throw new \LogicException('Compact AST child payload ended unexpectedly.');
        }

        return ord($payload[$offset++]);
    }
}
