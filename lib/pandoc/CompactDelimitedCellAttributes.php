<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CompactDelimitedCellAttributes implements AstAttributeResolver
{
    public function __construct(
        private readonly CompactDelimitedCellStore $store,
        private readonly int $index,
    ) {
    }

    public function has(string $name, AstNode $node): bool
    {
        return $this->store->hasAttribute($this->index, $name);
    }

    public function get(string $name, AstNode $node): mixed
    {
        return $this->store->attribute($this->index, $name);
    }

    public function materialize(array $baseAttrs, AstNode $node): array
    {
        return array_replace($baseAttrs, $this->store->attributes($this->index));
    }
}
