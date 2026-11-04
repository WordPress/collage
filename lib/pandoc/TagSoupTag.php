<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupTag
{
    public const OPEN = 'open';
    public const CLOSE = 'close';
    public const TEXT = 'text';
    public const COMMENT = 'comment';
    public const WARNING = 'warning';
    public const POSITION = 'position';

    /**
     * The tokenizer creates a record for every tag and text span, so most
     * records must not carry five unused PHP properties. The payload is
     * deliberately shaped by token type:
     *
     * - opening tags use `[name]` or `[name, attributes]`;
     * - closing tags and text-like records use a string;
     * - positions use `[row, column]`.
     *
     * Public access to the established name, attributes, text, row, and
     * column fields remains available through `__get()` below.
     *
     * @var string|array<int, mixed>|null
     */
    private readonly string|array|null $storage;

    /**
     * @param string|array<int, mixed>|null $storage
     */
    private function __construct(public readonly string $type, string|array|null $storage = null)
    {
        $this->storage = $storage;
    }

    /**
     * @param list<array{name:string,value:string}> $attributes
     */
    public static function open(string $name, array $attributes = []): self
    {
        $attributes = self::normalizeAttributes($attributes);

        return new self(self::OPEN, $attributes === [] ? [$name] : [$name, $attributes]);
    }

    public static function close(string $name): self
    {
        return new self(self::CLOSE, $name);
    }

    public static function text(string $text): self
    {
        return new self(self::TEXT, $text);
    }

    public static function comment(string $text): self
    {
        return new self(self::COMMENT, $text);
    }

    public static function warning(string $text): self
    {
        return new self(self::WARNING, $text);
    }

    public static function position(int $row, int $column): self
    {
        return new self(self::POSITION, [$row, $column]);
    }

    public function isOpenName(string $name): bool
    {
        return $this->type === self::OPEN && $this->name() === $name;
    }

    public function isCloseName(string $name): bool
    {
        return $this->type === self::CLOSE && $this->name() === $name;
    }

    /**
     * @return string|list<array{name:string,value:string}>|?int
     */
    public function __get(string $name): string|array|null|int
    {
        return match ($name) {
            'name' => $this->name(),
            'attributes' => $this->attributes(),
            'text' => $this->textValue(),
            'row' => $this->positionValue(0),
            'column' => $this->positionValue(1),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'name', 'attributes', 'text' => true,
            'row', 'column' => $this->type === self::POSITION,
            default => false,
        };
    }

    /**
     * @internal Used by TagSoupTokenStream to retain only the payload needed
     * for this token kind.
     *
     * @return string|array<int, mixed>|null
     */
    public function storageForTokenStream(): string|array|null
    {
        return $this->storage;
    }

    /**
     * @internal Rehydrates a short-lived reader token from a compact stream.
     *
     * @param string|array<int, mixed>|null $storage
     */
    public static function fromTokenStreamStorage(string $type, string|array|null $storage): self
    {
        return new self($type, $storage);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name(),
            'attributes' => $this->attributes(),
            'text' => $this->textValue(),
            'row' => $this->positionValue(0),
            'column' => $this->positionValue(1),
        ];
    }

    private function name(): string
    {
        if ($this->type === self::OPEN && is_array($this->storage)) {
            return is_string($this->storage[0] ?? null) ? $this->storage[0] : '';
        }

        return $this->type === self::CLOSE && is_string($this->storage) ? $this->storage : '';
    }

    /**
     * @return list<array{name:string,value:string}>
     */
    private function attributes(): array
    {
        if ($this->type !== self::OPEN || !is_array($this->storage)) {
            return [];
        }

        $attributes = $this->storage[1] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    private function textValue(): string
    {
        return in_array($this->type, [self::TEXT, self::COMMENT, self::WARNING], true)
            && is_string($this->storage)
            ? $this->storage
            : '';
    }

    private function positionValue(int $offset): ?int
    {
        if ($this->type !== self::POSITION || !is_array($this->storage)) {
            return null;
        }

        $value = $this->storage[$offset] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param list<array{name:string,value:string}> $attributes
     * @return list<array{name:string,value:string}>
     */
    private static function normalizeAttributes(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $attribute) {
            $name = $attribute['name'] ?? null;
            $value = $attribute['value'] ?? null;
            if (!is_string($name) || !is_string($value)) {
                continue;
            }

            $normalized[] = ['name' => $name, 'value' => $value];
        }

        return $normalized;
    }
}
