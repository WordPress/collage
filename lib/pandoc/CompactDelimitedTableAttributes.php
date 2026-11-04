<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Defers the expanded table review packet until an inspector asks for it.
 */
final class CompactDelimitedTableAttributes implements AstAttributeResolver
{
    /** @var array<string, mixed>|null */
    private ?array $geometry = null;

    public function has(string $name, AstNode $node): bool
    {
        return $name === 'tableGeometry';
    }

    public function get(string $name, AstNode $node): mixed
    {
        if ($name !== 'tableGeometry') {
            return null;
        }

        return $this->geometry ??= TableGeometry::reviewPacket($node);
    }

    public function materialize(array $baseAttrs, AstNode $node): array
    {
        return array_replace($baseAttrs, ['tableGeometry' => $this->get('tableGeometry', $node)]);
    }

    public function geometryIsMaterialized(): bool
    {
        return $this->geometry !== null;
    }
}
