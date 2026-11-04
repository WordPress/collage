<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Shares repeated immutable HTML attribute maps within one imported document.
 *
 * PHP arrays use copy-on-write semantics, so readers can retain one map for
 * repeated table cells while writers remain free to derive and modify local
 * attribute arrays without changing the source AST.
 */
final class PandocHtmlAttributeMapPool
{
    /** @var array<string, array<mixed>> */
    private array $maps = [];

    /**
     * @param array<mixed> $map
     * @return array<mixed>
     */
    public function intern(array $map): array
    {
        if ($map === []) {
            return [];
        }

        $key = serialize($map);
        if (isset($this->maps[$key])) {
            return $this->maps[$key];
        }

        return $this->maps[$key] = $map;
    }
}
