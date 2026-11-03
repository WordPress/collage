<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable, JSON-safe evidence collected for one source PDF page.
 *
 * This is deliberately pre-semantic. It records what providers observed;
 * headings, paragraphs, tables, reading order, and artifact removal belong to
 * later document-level processors.
 */
final class PdfPageFacts implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, mixed> */
    private readonly array $data;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported PDF page facts schema version.');
        }
        if (!is_int($data['pageNumber'] ?? null) || $data['pageNumber'] < 1) {
            throw new InvalidArgumentException('PDF page facts require a positive pageNumber.');
        }
        if (($data['pageObject'] ?? null) !== null && !is_int($data['pageObject'])) {
            throw new InvalidArgumentException('PDF page facts pageObject must be an integer or null.');
        }
        if (!is_string($data['label'] ?? null)) {
            throw new InvalidArgumentException('PDF page facts require a string label.');
        }
        foreach (['geometry', 'text', 'graphics', 'annotations', 'structure', 'issues'] as $key) {
            if (!is_array($data[$key] ?? null)) {
                throw new InvalidArgumentException("PDF page facts require an array {$key} field.");
            }
        }

        return new self($data);
    }

    public function pageNumber(): int
    {
        return $this->data['pageNumber'];
    }

    public function pageObject(): ?int
    {
        return $this->data['pageObject'];
    }

    public function label(): string
    {
        return $this->data['label'];
    }

    /** @return array<string, mixed> */
    public function geometry(): array
    {
        return $this->data['geometry'];
    }

    /** @return array<string, mixed> */
    public function text(): array
    {
        return $this->data['text'];
    }

    /** @return array<string, mixed> */
    public function graphics(): array
    {
        return $this->data['graphics'];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function annotations(): array
    {
        return $this->data['annotations'];
    }

    /** @return list<array<string, mixed>> */
    public function issues(): array
    {
        return $this->data['issues'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
