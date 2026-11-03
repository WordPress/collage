<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Supplies attributes which are expensive to keep as one PHP array per node.
 *
 * Resolvers are intentionally read-only. AstNode exposes the resolved array
 * through its existing public `attrs` compatibility property when a caller
 * needs it, while ordinary `attr()` lookups can stay compact.
 */
interface AstAttributeResolver
{
    public function has(string $name, AstNode $node): bool;

    public function get(string $name, AstNode $node): mixed;

    /**
     * @param array<string, mixed> $baseAttrs
     * @return array<string, mixed>
     */
    public function materialize(array $baseAttrs, AstNode $node): array;
}
