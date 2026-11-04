<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Keeps table review metadata out of the live import tree until an inspector
 * explicitly asks for it. Normal writers consume the table structure itself.
 */
final class LazyTableGeometryAttributes implements AstAttributeResolver
{
    /** @var array<string, mixed>|null */
    private ?array $geometry = null;

    /** @param array<string, mixed> $options */
    public function __construct(private readonly array $options = [])
    {
    }

    public function has(string $name, AstNode $node): bool
    {
        return $name === 'tableGeometry';
    }

    public function get(string $name, AstNode $node): mixed
    {
        if ($name !== 'tableGeometry') {
            return null;
        }

        $geometryClass = __NAMESPACE__ . '\\TableGeometry';

        return $this->geometry ??= $geometryClass::reviewPacket($node, $this->options);
    }

    public function materialize(array $baseAttrs, AstNode $node): array
    {
        return array_replace($baseAttrs, ['tableGeometry' => $this->get('tableGeometry', $node)]);
    }

    public function forRebuiltNode(): self
    {
        return new self($this->options);
    }

    public function geometryIsMaterialized(): bool
    {
        return $this->geometry !== null;
    }
}
