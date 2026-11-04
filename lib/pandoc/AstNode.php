<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

class AstNode
{
    private const COMPOUND_STORAGE_MARKER = "\0pandoc-ast-compound";
    private const COMPACT_TEXT_CHILDREN_MARKER = "\0pandoc-ast-compact-text-children";
    private const COMPACT_TEXT_CHILDREN_WITH_ATTRS_MARKER = "\0pandoc-ast-compact-text-children-attrs";
    private const DERIVED_TEXT_CHILDREN_MARKER = "\0pandoc-ast-derived-text-children";
    private const DERIVED_TEXT_CHILDREN_WITH_ATTRS_MARKER = "\0pandoc-ast-derived-text-children-attrs";

    /** @var \WeakMap<AstNode, array<string, mixed>>|null */
    private static ?\WeakMap $resolvedAttributes = null;

    /**
     * `storage` deliberately has only the data needed by the node shape:
     *
     * - string: one `text` attribute and no children;
     * - AstNode: one child and no attributes;
     * - AstSerializedChildren: lazily materialized children and no attributes;
     * - list<AstNode>: children and no attributes;
     * - array<string, mixed>: attributes and no children;
     * - list{array<string, mixed>|string, AstNode|list<AstNode>|null, ?AstAttributeResolver}:
     *   attributes, children, and optional lazy attributes.
     * - tagged lists: direct text children represented as strings.
     *
     * This keeps the common text leaf, wrapper, and container forms to two
     * object properties: `type` and this value. PHP arrays have a large
     * per-instance cost, so avoiding an empty attribute map and an empty or
     * singleton children map has a material effect on document-sized ASTs.
     *
     * @var string|AstNode|AstSerializedChildren|array<mixed>|null
     */
    private readonly string|self|AstSerializedChildren|array|null $storage;

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     * @param list<AstNode|string>|null $compactTextChildren
     */
    public function __construct(
        public readonly string $type,
        array $attrs = [],
        array $children = [],
        ?AstAttributeResolver $attributeResolver = null,
        ?array $compactTextChildren = null,
        bool $deriveTextFromChildren = false,
        ?AstSerializedChildren $serializedChildren = null,
    ) {
        if ($compactTextChildren !== null) {
            $this->storage = self::compactTextChildrenStorage($attrs, $compactTextChildren, $deriveTextFromChildren);

            return;
        }

        $text = count($attrs) === 1
            && isset($attrs['text'])
            && is_string($attrs['text'])
            ? $attrs['text']
            : null;
        if ($serializedChildren !== null) {
            $this->storage = $this->compoundStorage(
                $text ?? $attrs,
                $serializedChildren,
                $attributeResolver,
                $text === null && self::hasNumericKeys($attrs),
            );

            return;
        }
        $childStorage = match (count($children)) {
            0 => null,
            1 => $children[0],
            default => $children,
        };

        if ($attributeResolver !== null || ($text !== null ? $childStorage !== null : ($attrs !== [] && $childStorage !== null))) {
            $this->storage = $this->compoundStorage(
                $text ?? $attrs,
                $childStorage,
                $attributeResolver,
                $text === null && self::hasNumericKeys($attrs),
            );

            return;
        }

        if ($text !== null) {
            $this->storage = $text;

            return;
        }

        if ($attrs !== []) {
            $this->storage = self::hasNumericKeys($attrs)
                ? [self::COMPOUND_STORAGE_MARKER, $attrs, null]
                : $attrs;

            return;
        }

        $this->storage = $childStorage;
    }

    /**
     * Keep the common HTML block shape as a direct child list and derive its
     * redundant plain-text attribute on demand.
     *
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    public static function withTextFromChildren(string $type, array $attrs, array $children): self
    {
        unset($attrs['text']);

        $singleText = $attrs === [] ? self::singlePlainTextChild($children) : null;
        if ($singleText !== null) {
            return new AstNodeWithDerivedTextChild($type, ['text' => $singleText]);
        }

        $compactChildren = self::compactTextChildren($children);
        if ($compactChildren !== null) {
            return new self($type, $attrs, compactTextChildren: $compactChildren, deriveTextFromChildren: true);
        }

        return new AstNodeWithDerivedText($type, $attrs, $children);
    }

    /**
     * Store direct plain-text children as strings and recreate their public
     * AstNode form only when a caller inspects the child list.
     *
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    public static function withCompactTextChildren(string $type, array $attrs, array $children): self
    {
        $singleText = $attrs === [] ? self::singlePlainTextChild($children) : null;
        if ($singleText !== null) {
            return new AstNodeWithTextChild($type, ['text' => $singleText]);
        }

        $compactChildren = self::compactTextChildren($children);
        if ($compactChildren === null) {
            return new self($type, $attrs, $children);
        }

        return new self($type, $attrs, compactTextChildren: $compactChildren);
    }

    /**
     * Retain a stable public AST while storing a completed child list as a
     * compact immutable payload. The list is recreated only for callers that
     * inspect this node's children.
     *
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    public static function withSerializedChildren(string $type, array $attrs, array $children): self
    {
        if ($children === []) {
            return new self($type, $attrs);
        }

        return new self(
            $type,
            $attrs,
            serializedChildren: new AstSerializedChildren($children),
        );
    }

    public function attr(string $name, mixed $default = null): mixed
    {
        $attrs = $this->directAttributes();
        if (is_string($attrs)) {
            if ($name === 'text') {
                return $attrs;
            }
        } elseif (array_key_exists($name, $attrs)) {
            return $attrs[$name];
        }

        if ($name === 'text' && $this->derivesTextFromChildren()) {
            return $this->textFromChildren();
        }

        $resolver = $this->attributeResolver();
        if ($resolver !== null && $resolver->has($name, $this)) {
            return $resolver->get($name, $this);
        }

        return $default;
    }

    public function hasAttr(string $name): bool
    {
        $attrs = $this->directAttributes();
        if (
            (is_string($attrs) && $name === 'text')
            || (is_array($attrs) && array_key_exists($name, $attrs))
            || ($name === 'text' && $this->derivesTextFromChildren())
        ) {
            return true;
        }

        $resolver = $this->attributeResolver();

        return $resolver !== null && $resolver->has($name, $this);
    }

    /**
     * Returns only attributes stored directly on this node.
     *
     * This is useful for transforms which deliberately do not need optional
     * lazy review metadata.
     *
     * @return array<string, mixed>
     */
    public function baseAttrs(): array
    {
        $attrs = $this->directAttributes();

        if (is_string($attrs)) {
            return ['text' => $attrs];
        }

        if ($this->derivesTextFromChildren()) {
            $attrs['text'] = $this->textFromChildren();
        }

        return $attrs;
    }

    public function attributeResolver(): ?AstAttributeResolver
    {
        $offset = $this->compoundStorageOffset();
        if ($offset === null || !is_array($this->storage)) {
            return null;
        }

        $resolver = $this->storage[$offset + 2] ?? null;

        return $resolver instanceof AstAttributeResolver ? $resolver : null;
    }

    /**
     * @return list<AstNode>
     */
    public function children(): array
    {
        $textChild = $this->textChildValue();
        if ($textChild !== null) {
            return [new self('text', ['text' => $textChild])];
        }

        $children = $this->directChildren();
        if ($children instanceof self) {
            return [$children];
        }

        if ($children instanceof AstSerializedChildren) {
            return $children->materialize();
        }

        if ($children === null) {
            return [];
        }

        $materialized = [];
        foreach ($children as $child) {
            $materialized[] = is_string($child) ? new self('text', ['text' => $child]) : $child;
        }

        return $materialized;
    }

    /**
     * Preserve the established public `$node->attrs` and `$node->children`
     * access patterns while keeping their compact forms internal.
     *
     * @return array<string, mixed>|list<AstNode>
     */
    public function __get(string $name): mixed
    {
        if ($name === 'children') {
            return $this->children();
        }

        if ($name !== 'attrs') {
            return null;
        }

        $resolver = $this->attributeResolver();
        if ($resolver === null) {
            return $this->baseAttrs();
        }

        $resolved = self::$resolvedAttributes;
        if ($resolved !== null && isset($resolved[$this])) {
            return $resolved[$this];
        }

        $attrs = $resolver->materialize($this->baseAttrs(), $this);
        $resolved = self::$resolvedAttributes ??= new \WeakMap();
        $resolved[$this] = $attrs;

        return $attrs;
    }

    public function __isset(string $name): bool
    {
        return $name === 'attrs' || $name === 'children';
    }

    /**
     * @param array<string, mixed>|string $attrs
     * @param AstNode|AstSerializedChildren|list<AstNode>|null $children
     * @return list<mixed>
     */
    private function compoundStorage(
        array|string $attrs,
        AstNode|AstSerializedChildren|array|null $children,
        ?AstAttributeResolver $resolver,
        bool $mustMark,
    ): array {
        if ($mustMark) {
            return [self::COMPOUND_STORAGE_MARKER, $attrs, $children, $resolver];
        }

        return $resolver === null
            ? [$attrs, $children]
            : [$attrs, $children, $resolver];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function directAttributes(): array|string
    {
        if (is_string($this->storage)) {
            if ($this->textChildValue() !== null) {
                return [];
            }

            return $this->storage;
        }

        $compactChildrenOffset = $this->compactChildrenOffset();
        if ($compactChildrenOffset !== null) {
            return $compactChildrenOffset === 2 && is_array($this->storage[1] ?? null)
                ? $this->storage[1]
                : [];
        }

        $offset = $this->compoundStorageOffset();
        if ($offset !== null && is_array($this->storage)) {
            return $this->storage[$offset];
        }

        if (is_array($this->storage) && !(($this->storage[0] ?? null) instanceof self)) {
            return $this->storage;
        }

        return [];
    }

    /**
     * @return AstNode|AstSerializedChildren|list<AstNode|string>|null
     */
    private function directChildren(): AstNode|AstSerializedChildren|array|null
    {
        if ($this->storage instanceof self || $this->storage instanceof AstSerializedChildren) {
            return $this->storage;
        }

        $compactChildrenOffset = $this->compactChildrenOffset();
        if ($compactChildrenOffset !== null && is_array($this->storage)) {
            return array_slice($this->storage, $compactChildrenOffset);
        }

        $offset = $this->compoundStorageOffset();
        if ($offset !== null && is_array($this->storage)) {
            return $this->storage[$offset + 1];
        }

        if (is_array($this->storage) && ($this->storage[0] ?? null) instanceof self) {
            return $this->storage;
        }

        return null;
    }

    /**
     * Returns the index of the direct attributes in a compound storage array.
     */
    private function compoundStorageOffset(): ?int
    {
        if (!is_array($this->storage)) {
            return null;
        }

        if ($this->compactChildrenOffset() !== null) {
            return null;
        }

        if (($this->storage[0] ?? null) === self::COMPOUND_STORAGE_MARKER) {
            return 1;
        }

        if (!array_key_exists(1, $this->storage)) {
            return null;
        }

        $attrs = $this->storage[0] ?? null;

        return is_string($attrs) || is_array($attrs) ? 0 : null;
    }

    /**
     * Numeric root keys are not a normal AST attribute shape, but retaining
     * them avoids an ambiguity with a list of child nodes.
     *
     * @param array<string, mixed> $attrs
     */
    private static function hasNumericKeys(array $attrs): bool
    {
        foreach (array_keys($attrs) as $key) {
            if (is_int($key)) {
                return true;
            }
        }

        return false;
    }

    private function derivesTextFromChildren(): bool
    {
        return $this instanceof AstNodeWithDerivedText
            || (is_array($this->storage) && in_array(
                $this->storage[0] ?? null,
                [self::DERIVED_TEXT_CHILDREN_MARKER, self::DERIVED_TEXT_CHILDREN_WITH_ATTRS_MARKER],
                true,
            ));
    }

    private function textFromChildren(): string
    {
        return self::plainTextFromChildren($this->children());
    }

    /**
     * @param list<AstNode> $children
     */
    private static function plainTextFromChildren(array $children): string
    {
        $text = '';
        foreach ($children as $child) {
            if ($child->type === 'text' || $child->type === 'code') {
                $text .= (string) $child->attr('text', '');
            } elseif ($child->type === 'linebreak') {
                $text .= "\n";
            } else {
                $text .= self::plainTextFromChildren($child->children);
            }
        }

        return trim(preg_replace('/[ \t\f\v]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode|string>|null
     */
    private static function compactTextChildren(array $children): ?array
    {
        $compact = [];
        $textCount = 0;
        foreach ($children as $child) {
            $attrs = $child->baseAttrs();
            if (
                $child->type === 'text'
                && $child->children === []
                && count($attrs) === 1
                && isset($attrs['text'])
                && is_string($attrs['text'])
            ) {
                $compact[] = $attrs['text'];
                ++$textCount;
                continue;
            }

            $compact[] = $child;
        }

        // A tagged array is more compact than multiple text objects, but it
        // costs more than AstNode's direct singleton-child storage. Restrict
        // it to runs with at least two direct text leaves.
        return $textCount >= 2 ? $compact : null;
    }

    /**
     * @param list<AstNode> $children
     */
    private static function singlePlainTextChild(array $children): ?string
    {
        if (count($children) !== 1) {
            return null;
        }

        $child = $children[0];
        $attrs = $child->baseAttrs();
        if (
            $child->type !== 'text'
            || $child->children !== []
            || count($attrs) !== 1
            || !isset($attrs['text'])
            || !is_string($attrs['text'])
        ) {
            return null;
        }

        return $attrs['text'];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode|string> $children
     * @return list<AstNode|string|array<string, mixed>>
     */
    private static function compactTextChildrenStorage(array $attrs, array $children, bool $deriveText): array
    {
        $hasAttrs = $attrs !== [];
        $marker = match ([$deriveText, $hasAttrs]) {
            [false, false] => self::COMPACT_TEXT_CHILDREN_MARKER,
            [false, true] => self::COMPACT_TEXT_CHILDREN_WITH_ATTRS_MARKER,
            [true, false] => self::DERIVED_TEXT_CHILDREN_MARKER,
            [true, true] => self::DERIVED_TEXT_CHILDREN_WITH_ATTRS_MARKER,
        };

        return $hasAttrs ? [$marker, $attrs, ...$children] : [$marker, ...$children];
    }

    private function compactChildrenOffset(): ?int
    {
        if (!is_array($this->storage)) {
            return null;
        }

        return match ($this->storage[0] ?? null) {
            self::COMPACT_TEXT_CHILDREN_MARKER,
            self::DERIVED_TEXT_CHILDREN_MARKER => 1,
            self::COMPACT_TEXT_CHILDREN_WITH_ATTRS_MARKER,
            self::DERIVED_TEXT_CHILDREN_WITH_ATTRS_MARKER => 2,
            default => null,
        };
    }

    private function textChildValue(): ?string
    {
        if (
            !$this instanceof AstNodeWithTextChild
            && !$this instanceof AstNodeWithDerivedTextChild
        ) {
            return null;
        }

        return is_string($this->storage) ? $this->storage : null;
    }
}

/** @internal Keeps a derived text attribute without a per-node marker. */
class AstNodeWithDerivedText extends AstNode
{
}

/** @internal Stores one direct text child in AstNode's existing string slot. */
class AstNodeWithTextChild extends AstNode
{
}

/** @internal Combines lazy block text with a direct singleton text child. */
final class AstNodeWithDerivedTextChild extends AstNodeWithDerivedText
{
}
