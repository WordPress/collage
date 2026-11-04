<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * Serializable provider-neutral PDF evidence for a whole document or range.
 */
final class PdfDocumentFacts implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /** @var list<PdfPageFacts> */
    private readonly array $pages;

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $inventory
     * @param list<PdfPageFacts> $pages
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $diagnostics
     * @param array<string, list<array<string, mixed>>> $unassignedAnnotations
     */
    public function __construct(
        private readonly string $provider,
        private readonly array $source,
        private readonly array $inventory,
        array $pages,
        private readonly array $structure = [],
        private readonly array $diagnostics = [],
        private readonly array $unassignedAnnotations = []
    ) {
        if ($provider === '') {
            throw new InvalidArgumentException('PDF document facts require a provider identifier.');
        }
        if (!is_string($source['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $source['sha256']) !== 1) {
            throw new InvalidArgumentException('PDF document facts require a SHA-256 source digest.');
        }
        if (!is_int($source['byteLength'] ?? null) || $source['byteLength'] < 0) {
            throw new InvalidArgumentException('PDF document facts require a non-negative source byte length.');
        }

        $seenPages = [];
        foreach ($pages as $page) {
            if (!$page instanceof PdfPageFacts) {
                throw new InvalidArgumentException('PDF document facts pages must contain PdfPageFacts values.');
            }
            if (isset($seenPages[$page->pageNumber()])) {
                throw new InvalidArgumentException('PDF document facts cannot contain duplicate page numbers.');
            }
            $seenPages[$page->pageNumber()] = true;
        }
        $this->pages = array_values($pages);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported PDF document facts schema version.');
        }
        foreach (['source', 'inventory', 'structure', 'diagnostics', 'unassignedAnnotations'] as $key) {
            if (!is_array($data[$key] ?? null)) {
                throw new InvalidArgumentException("PDF document facts require an array {$key} field.");
            }
        }
        if (!is_array($data['pages'] ?? null)) {
            throw new InvalidArgumentException('PDF document facts require a pages array.');
        }

        $pages = [];
        foreach ($data['pages'] as $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Serialized PDF pages must be arrays.');
            }
            $pages[] = PdfPageFacts::fromArray($page);
        }

        return new self(
            is_string($data['provider'] ?? null) ? $data['provider'] : '',
            $data['source'],
            $data['inventory'],
            $pages,
            $data['structure'],
            $data['diagnostics'],
            $data['unassignedAnnotations']
        );
    }

    /** @throws JsonException */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Serialized PDF document facts must decode to an object.');
        }

        return self::fromArray($data);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /** @return array<string, mixed> */
    public function source(): array
    {
        return $this->source;
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        return $this->inventory;
    }

    /** @return list<PdfPageFacts> */
    public function pages(): array
    {
        return $this->pages;
    }

    public function page(int $pageNumber): ?PdfPageFacts
    {
        foreach ($this->pages as $page) {
            if ($page->pageNumber() === $pageNumber) {
                return $page;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function structure(): array
    {
        return $this->structure;
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'provider' => $this->provider,
            'source' => $this->source,
            'inventory' => $this->inventory,
            'pages' => array_map(
                static fn (PdfPageFacts $page): array => $page->toArray(),
                $this->pages
            ),
            'structure' => $this->structure,
            'diagnostics' => $this->diagnostics,
            'unassignedAnnotations' => $this->unassignedAnnotations,
        ];
    }

    /** @throws JsonException */
    public function toJson(): string
    {
        return json_encode(
            $this,
            JSON_THROW_ON_ERROR
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
