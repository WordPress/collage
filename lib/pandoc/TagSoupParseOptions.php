<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupParseOptions
{
    public function __construct(
        public readonly bool $includePositions = false,
        public readonly bool $includeWarnings = false,
        public readonly bool $decodeEntities = true,
        public readonly bool $mergeAdjacentText = true,
        public readonly bool $canonicalizeTags = false,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    public static function fast(): self
    {
        return new self(mergeAdjacentText: false);
    }

    public function withPositions(bool $includePositions = true): self
    {
        return new self(
            includePositions: $includePositions,
            includeWarnings: $this->includeWarnings,
            decodeEntities: $this->decodeEntities,
            mergeAdjacentText: $this->mergeAdjacentText,
            canonicalizeTags: $this->canonicalizeTags,
        );
    }

    public function withWarnings(bool $includeWarnings = true): self
    {
        return new self(
            includePositions: $this->includePositions,
            includeWarnings: $includeWarnings,
            decodeEntities: $this->decodeEntities,
            mergeAdjacentText: $this->mergeAdjacentText,
            canonicalizeTags: $this->canonicalizeTags,
        );
    }

    public function withoutEntityDecoding(): self
    {
        return new self(
            includePositions: $this->includePositions,
            includeWarnings: $this->includeWarnings,
            decodeEntities: false,
            mergeAdjacentText: $this->mergeAdjacentText,
            canonicalizeTags: $this->canonicalizeTags,
        );
    }

    public function withCanonicalizedTags(bool $canonicalizeTags = true): self
    {
        return new self(
            includePositions: $this->includePositions,
            includeWarnings: $this->includeWarnings,
            decodeEntities: $this->decodeEntities,
            mergeAdjacentText: $this->mergeAdjacentText,
            canonicalizeTags: $canonicalizeTags,
        );
    }
}
