<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Compact internal storage for a tag-soup token sequence.
 *
 * HTML readers only need a token object while examining the current token.
 * Keeping a `TagSoupTag` object and nested attribute arrays for every text
 * span and tag dominates memory on ordinary prose documents, so this stream
 * stores one byte of type data, fixed-width payload descriptors, and compact
 * payload chunks. `tokenAt()` reconstructs a short-lived token on demand and
 * consumed chunks can be released without changing indices.
 *
 * @implements \IteratorAggregate<int, TagSoupTag>
 */
final class TagSoupTokenStream implements \Countable, \IteratorAggregate
{
    private const PAYLOAD_CHUNK_SIZE = 1024;
    private const DESCRIPTOR_BYTES = 8;
    private const HOT_ATTRIBUTE_CACHE_MIN_COUNT = 2;
    private const OPEN = "\x01";
    private const CLOSE = "\x02";
    private const TEXT = "\x03";
    private const COMMENT = "\x04";
    private const WARNING = "\x05";
    private const POSITION = "\x06";

    private string $types = '';

    /** One byte per token for the common interned element-name range. */
    private string $nameCodes = '';

    /** @var array<int, int> */
    private array $wideNameIds = [];

    /** @var array<int, string> */
    private array $payloadChunks = [];

    /** @var array<int, string> */
    private array $descriptorChunks = [];

    /** @var array<int, array<int, list<array{name:string,value:string}>>> */
    private array $openAttributeChunks = [];

    /** @var array<string, int> */
    private array $stringIds = [];

    /** @var list<string> */
    private array $strings = [];

    private int $decodedTokenChunk = -1;

    /** @var array<int, TagSoupTag> */
    private array $decodedTokens = [];

    private int $released = 0;

    public function append(TagSoupTag $token): void
    {
        $index = $this->count();
        $storage = $token->storageForTokenStream();
        $nameId = $this->nameIdForStorage($token->type, $storage);
        $this->types .= self::codeForType($token->type);
        $this->nameCodes .= $this->nameCodeForId($index, $nameId);
        $this->recordOpenAttributes($index, $token->type, $storage);
        $this->appendPayload($index, $this->encodePayload($token->type, $storage, $nameId));
    }

    public function replaceAt(int $index, TagSoupTag $token): void
    {
        if ($index < $this->released || $index < 0 || $index >= $this->count()) {
            throw new \OutOfBoundsException('Cannot replace a token outside the retained stream.');
        }

        $storage = $token->storageForTokenStream();
        $nameId = $this->nameIdForStorage($token->type, $storage);
        $this->types[$index] = self::codeForType($token->type);
        $this->nameCodes[$index] = $this->nameCodeForId($index, $nameId);
        $this->recordOpenAttributes($index, $token->type, $storage);
        $this->replacePayload($index, $this->encodePayload($token->type, $storage, $nameId));
        if ($this->decodedTokenChunk === intdiv($index, self::PAYLOAD_CHUNK_SIZE)) {
            unset($this->decodedTokens[$index % self::PAYLOAD_CHUNK_SIZE]);
        }
    }

    public function tokenAt(int $index): ?TagSoupTag
    {
        $type = $this->typeAt($index);
        if ($type === null) {
            return null;
        }

        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $offset = $index % self::PAYLOAD_CHUNK_SIZE;
        if ($this->decodedTokenChunk !== $chunk) {
            $this->decodedTokenChunk = $chunk;
            $this->decodedTokens = [];
        }
        if (isset($this->decodedTokens[$offset])) {
            return $this->decodedTokens[$offset];
        }

        $nameId = $this->nameIdAt($index);
        if ($type === TagSoupTag::CLOSE) {
            if ($nameId === null) {
                return null;
            }

            return $this->decodedTokens[$offset] = TagSoupTag::fromTokenStreamStorage(
                TagSoupTag::CLOSE,
                $this->stringForId($nameId)
            );
        }

        if ($type === TagSoupTag::OPEN) {
            $attributes = $this->openAttributesAt($index);
            if ($attributes !== null) {
                return $this->decodedTokens[$offset] = TagSoupTag::fromTokenStreamStorage(
                    TagSoupTag::OPEN,
                    [$this->stringForId($nameId ?? 0), $attributes]
                );
            }
        }

        $payload = $this->payloadAt($index);
        if ($payload === null) {
            return null;
        }

        return $this->decodedTokens[$offset] = $this->decodeToken($type, $payload, $nameId);
    }

    /**
     * Read a token type without allocating a token object or its attributes.
     */
    public function typeAt(int $index): ?string
    {
        if ($index < $this->released || $index < 0 || $index >= $this->count()) {
            return null;
        }

        return self::typeForCode($this->types[$index]);
    }

    /**
     * Read an element name without reconstructing the token's attributes.
     */
    public function nameAt(int $index): ?string
    {
        $type = $this->typeAt($index);
        if (!in_array($type, [TagSoupTag::OPEN, TagSoupTag::CLOSE], true)) {
            return null;
        }

        $nameId = $this->nameIdAt($index);

        return $nameId === null ? null : $this->stringForId($nameId);
    }

    /**
     * Read a text-like token payload without allocating a token object.
     */
    public function textAt(int $index): ?string
    {
        $type = $this->typeAt($index);
        if (!in_array($type, [TagSoupTag::TEXT, TagSoupTag::COMMENT, TagSoupTag::WARNING], true)) {
            return null;
        }

        return $this->payloadAt($index);
    }

    /**
     * Return one opening-tag attribute without rebuilding the full attribute
     * list. This is used by document-level scans before the regular parser
     * visits a token and needs every attribute.
     */
    public function attributeAt(int $index, string $name): string
    {
        if ($this->typeAt($index) !== TagSoupTag::OPEN) {
            return '';
        }

        $cachedAttributes = $this->openAttributesAt($index);
        if ($cachedAttributes !== null) {
            foreach ($cachedAttributes as $attribute) {
                if (($attribute['name'] ?? null) === $name && is_string($attribute['value'] ?? null)) {
                    return $attribute['value'];
                }
            }

            return '';
        }

        $payload = $this->payloadAt($index);
        if ($payload === null) {
            return '';
        }

        $payloadLength = strlen($payload);
        $offset = 0;
        if (self::compactUintAt($payload, $offset, $payloadLength) === null) {
            return '';
        }
        $attributeCount = self::compactUintAt($payload, $offset, $payloadLength);
        if ($attributeCount === null) {
            return '';
        }
        for ($attribute = 0; $attribute < $attributeCount; ++$attribute) {
            $attributeNameId = self::compactUintAt($payload, $offset, $payloadLength);
            $valueLength = self::compactUintAt($payload, $offset, $payloadLength);
            if ($attributeNameId === null || $valueLength === null || $valueLength > $payloadLength - $offset) {
                break;
            }
            $attributeName = $this->stringForId($attributeNameId);
            if ($attributeName === $name) {
                return substr($payload, $offset, $valueLength);
            }
            $offset += $valueLength;
        }

        return '';
    }

    public function lastToken(): ?TagSoupTag
    {
        return $this->tokenAt($this->count() - 1);
    }

    public function replaceLast(TagSoupTag $token): void
    {
        $last = $this->count() - 1;
        if ($last < $this->released) {
            throw new \OutOfBoundsException('Cannot replace a token in an empty stream.');
        }

        $this->replaceAt($last, $token);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function releaseBefore(int $exclusive): void
    {
        $limit = min(max($exclusive, $this->released), $this->count());
        $firstChunkToRetain = intdiv($limit, self::PAYLOAD_CHUNK_SIZE);
        foreach (array_keys($this->descriptorChunks) as $chunk) {
            if ($chunk < $firstChunkToRetain) {
                unset($this->payloadChunks[$chunk], $this->descriptorChunks[$chunk]);
            }
        }
        foreach (array_keys($this->openAttributeChunks) as $chunk) {
            if ($chunk < $firstChunkToRetain) {
                unset($this->openAttributeChunks[$chunk]);
            }
        }
        $firstRetainedOffset = $limit % self::PAYLOAD_CHUNK_SIZE;
        if ($firstRetainedOffset > 0 && isset($this->openAttributeChunks[$firstChunkToRetain])) {
            foreach (array_keys($this->openAttributeChunks[$firstChunkToRetain]) as $offset) {
                if ($offset < $firstRetainedOffset) {
                    unset($this->openAttributeChunks[$firstChunkToRetain][$offset]);
                }
            }
        }

        if ($this->decodedTokenChunk >= 0 && $this->decodedTokenChunk < $firstChunkToRetain) {
            $this->decodedTokenChunk = -1;
            $this->decodedTokens = [];
        }
        foreach (array_keys($this->wideNameIds) as $index) {
            if ($index < $limit) {
                unset($this->wideNameIds[$index]);
            }
        }

        // Keeping the partial chunk until the next release bounds retained
        // stale payload to one chunk while avoiding per-token array deletes.
        $this->released = $limit;
    }

    /**
     * @return list<TagSoupTag>
     */
    public function slice(int $offset, ?int $length = null): array
    {
        $end = $length === null
            ? $this->count()
            : min($this->count(), max($offset, 0) + max($length, 0));
        $tokens = [];
        for ($index = max($offset, $this->released); $index < $end; ++$index) {
            $token = $this->tokenAt($index);
            if ($token instanceof TagSoupTag) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    public function count(): int
    {
        return strlen($this->types);
    }

    /**
     * @return \Traversable<int, TagSoupTag>
     */
    public function getIterator(): \Traversable
    {
        $count = $this->count();
        for ($index = $this->released; $index < $count; ++$index) {
            $token = $this->tokenAt($index);
            if ($token instanceof TagSoupTag) {
                yield $index => $token;
            }
        }
    }

    private static function codeForType(string $type): string
    {
        return match ($type) {
            TagSoupTag::OPEN => self::OPEN,
            TagSoupTag::CLOSE => self::CLOSE,
            TagSoupTag::TEXT => self::TEXT,
            TagSoupTag::COMMENT => self::COMMENT,
            TagSoupTag::WARNING => self::WARNING,
            TagSoupTag::POSITION => self::POSITION,
            default => throw new \InvalidArgumentException("Unknown tag-soup token type '{$type}'."),
        };
    }

    private static function typeForCode(string $code): string
    {
        return match ($code) {
            self::OPEN => TagSoupTag::OPEN,
            self::CLOSE => TagSoupTag::CLOSE,
            self::TEXT => TagSoupTag::TEXT,
            self::COMMENT => TagSoupTag::COMMENT,
            self::WARNING => TagSoupTag::WARNING,
            self::POSITION => TagSoupTag::POSITION,
            default => throw new \UnexpectedValueException('Unknown compact tag-soup token code.'),
        };
    }

    private function appendPayload(int $index, string $payload): void
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        if (!isset($this->payloadChunks[$chunk])) {
            $this->payloadChunks[$chunk] = '';
            $this->descriptorChunks[$chunk] = '';
        }

        $offset = strlen($this->payloadChunks[$chunk]);
        $this->payloadChunks[$chunk] .= $payload;
        $this->descriptorChunks[$chunk] .= pack('N2', $offset, strlen($payload));
    }

    private function replacePayload(int $index, string $payload): void
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        if (!isset($this->payloadChunks[$chunk], $this->descriptorChunks[$chunk])) {
            throw new \OutOfBoundsException('Cannot replace a token outside the retained stream.');
        }

        $offset = strlen($this->payloadChunks[$chunk]);
        $this->payloadChunks[$chunk] .= $payload;
        $descriptorOffset = ($index % self::PAYLOAD_CHUNK_SIZE) * self::DESCRIPTOR_BYTES;
        $this->descriptorChunks[$chunk] = substr_replace(
            $this->descriptorChunks[$chunk],
            pack('N2', $offset, strlen($payload)),
            $descriptorOffset,
            self::DESCRIPTOR_BYTES
        );
    }

    /**
     * @param string|array<int, mixed>|null $storage
     */
    private function encodePayload(string $type, string|array|null $storage, ?int $nameId = null): string
    {
        if ($type === TagSoupTag::OPEN && is_array($storage)) {
            $name = is_string($storage[0] ?? null) ? $storage[0] : '';
            $attributes = is_array($storage[1] ?? null) ? $storage[1] : [];
            $payload = self::encodeCompactUint($nameId ?? $this->stringId($name))
                . self::encodeCompactUint(count($attributes));
            foreach ($attributes as $attribute) {
                $attributeName = is_string($attribute['name'] ?? null) ? $attribute['name'] : '';
                $attributeValue = is_string($attribute['value'] ?? null) ? $attribute['value'] : '';
                $payload .= self::encodeCompactUint($this->stringId($attributeName))
                    . self::encodeCompactUint(strlen($attributeValue))
                    . $attributeValue;
            }

            return $payload;
        }

        if ($type === TagSoupTag::CLOSE && is_string($storage)) {
            return self::encodeCompactUint($nameId ?? $this->stringId($storage));
        }

        if ($type === TagSoupTag::POSITION && is_array($storage)) {
            $row = is_int($storage[0] ?? null) ? $storage[0] : 0;
            $column = is_int($storage[1] ?? null) ? $storage[1] : 0;

            return self::encodeCompactUint($row) . self::encodeCompactUint($column);
        }

        return is_string($storage) ? $storage : '';
    }

    private function decodeToken(string $type, string $payload, ?int $knownNameId = null): TagSoupTag
    {
        if ($type === TagSoupTag::OPEN) {
            $payloadLength = strlen($payload);
            $offset = 0;
            $nameId = self::compactUintAt($payload, $offset, $payloadLength);
            $attributeCount = self::compactUintAt($payload, $offset, $payloadLength);
            $name = $this->stringForId($knownNameId ?? $nameId ?? 0);
            $attributes = [];
            for ($index = 0; $index < ($attributeCount ?? 0); ++$index) {
                $attributeNameId = self::compactUintAt($payload, $offset, $payloadLength);
                $valueLength = self::compactUintAt($payload, $offset, $payloadLength);
                if ($attributeNameId === null || $valueLength === null || $valueLength > $payloadLength - $offset) {
                    break;
                }
                $attributes[] = [
                    'name' => $this->stringForId($attributeNameId),
                    'value' => substr($payload, $offset, $valueLength),
                ];
                $offset += $valueLength;
            }

            return TagSoupTag::fromTokenStreamStorage(
                TagSoupTag::OPEN,
                $attributes === [] ? [$name] : [$name, $attributes]
            );
        }

        if ($type === TagSoupTag::CLOSE) {
            $offset = 0;
            $nameId = self::compactUintAt($payload, $offset, strlen($payload));

            return TagSoupTag::fromTokenStreamStorage(TagSoupTag::CLOSE, $this->stringForId($nameId ?? 0));
        }

        if ($type === TagSoupTag::POSITION) {
            $offset = 0;
            $payloadLength = strlen($payload);
            $row = self::compactUintAt($payload, $offset, $payloadLength);
            $column = self::compactUintAt($payload, $offset, $payloadLength);

            return TagSoupTag::fromTokenStreamStorage(TagSoupTag::POSITION, [
                $row ?? 0,
                $column ?? 0,
            ]);
        }

        return TagSoupTag::fromTokenStreamStorage($type, $payload);
    }

    private static function uint32At(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            return 0;
        }

        return (ord($bytes[$offset]) << 24)
            | (ord($bytes[$offset + 1]) << 16)
            | (ord($bytes[$offset + 2]) << 8)
            | ord($bytes[$offset + 3]);
    }

    private static function encodeCompactUint(int $value): string
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \OverflowException('Compact tag-soup integer is outside the unsigned 32-bit range.');
        }
        if ($value < 0xff) {
            return chr($value);
        }
        if ($value < 0xffff) {
            return "\xff" . pack('n', $value);
        }

        return "\xff\xff\xff" . pack('N', $value);
    }

    private static function compactUintAt(string $bytes, int &$offset, int $length): ?int
    {
        if ($offset >= $length) {
            return null;
        }

        $first = ord($bytes[$offset++]);
        if ($first !== 0xff) {
            return $first;
        }
        if ($offset + 2 > $length) {
            return null;
        }

        $value = (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);
        $offset += 2;
        if ($value !== 0xffff) {
            return $value;
        }
        if ($offset + 4 > $length) {
            return null;
        }

        $value = self::uint32At($bytes, $offset);
        $offset += 4;

        return $value;
    }

    private function stringId(string $value): int
    {
        if (isset($this->stringIds[$value])) {
            return $this->stringIds[$value];
        }

        $id = count($this->strings);
        $this->stringIds[$value] = $id;
        $this->strings[] = $value;

        return $id;
    }

    /**
     * @param string|array<int, mixed>|null $storage
     */
    private function nameIdForStorage(string $type, string|array|null $storage): ?int
    {
        if ($type === TagSoupTag::OPEN && is_array($storage) && is_string($storage[0] ?? null)) {
            return $this->stringId($storage[0]);
        }
        if ($type === TagSoupTag::CLOSE && is_string($storage)) {
            return $this->stringId($storage);
        }

        return null;
    }

    /**
     * Retain only attribute-heavy opening tags in direct PHP form. These are
     * expensive to rebuild and are released with their payload chunk once the
     * reader has passed them; simpler tags stay in the compact byte payload.
     *
     * @param string|array<int, mixed>|null $storage
     */
    private function recordOpenAttributes(int $index, string $type, string|array|null $storage): void
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $offset = $index % self::PAYLOAD_CHUNK_SIZE;
        if (isset($this->openAttributeChunks[$chunk])) {
            unset($this->openAttributeChunks[$chunk][$offset]);
        }
        if (
            $type !== TagSoupTag::OPEN
            || !is_array($storage)
            || !is_array($storage[1] ?? null)
            || count($storage[1]) < self::HOT_ATTRIBUTE_CACHE_MIN_COUNT
        ) {
            return;
        }

        $this->openAttributeChunks[$chunk][$offset] = $storage[1];
    }

    /**
     * @return list<array{name:string,value:string}>|null
     */
    private function openAttributesAt(int $index): ?array
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $offset = $index % self::PAYLOAD_CHUNK_SIZE;

        return $this->openAttributeChunks[$chunk][$offset] ?? null;
    }

    private function nameCodeForId(int $index, ?int $nameId): string
    {
        unset($this->wideNameIds[$index]);
        if ($nameId === null) {
            return "\0";
        }
        if ($nameId < 0xfe) {
            return chr($nameId + 1);
        }

        $this->wideNameIds[$index] = $nameId;

        return "\xff";
    }

    private function nameIdAt(int $index): ?int
    {
        $code = ord($this->nameCodes[$index]);
        if ($code === 0) {
            return null;
        }
        if ($code === 0xff) {
            return $this->wideNameIds[$index] ?? null;
        }

        return $code - 1;
    }

    private function stringForId(int $id): string
    {
        return $this->strings[$id] ?? '';
    }

    private function payloadAt(int $index): ?string
    {
        $chunk = intdiv($index, self::PAYLOAD_CHUNK_SIZE);
        $descriptorOffset = ($index % self::PAYLOAD_CHUNK_SIZE) * self::DESCRIPTOR_BYTES;
        $descriptors = $this->descriptorChunks[$chunk] ?? null;
        $payloads = $this->payloadChunks[$chunk] ?? null;
        if (!is_string($descriptors) || !is_string($payloads) || $descriptorOffset + self::DESCRIPTOR_BYTES > strlen($descriptors)) {
            return null;
        }

        $offset = self::uint32At($descriptors, $descriptorOffset);
        $length = self::uint32At($descriptors, $descriptorOffset + 4);
        if ($offset > strlen($payloads) || $length > strlen($payloads) - $offset) {
            return null;
        }

        return substr($payloads, $offset, $length);
    }
}
