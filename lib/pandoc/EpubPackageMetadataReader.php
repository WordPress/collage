<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackageMetadataReader
{
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    /**
     * @var array<string, string>
     */
    private const DC_FIELD_KEYS = [
        'publisher' => 'publisher',
        'description' => 'description',
        'rights' => 'rights',
        'source' => 'source',
        'relation' => 'relation',
        'coverage' => 'coverage',
        'type' => 'type',
        'format' => 'format',
    ];

    public function readEpubFile(string $path): AstNode
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to open EPUB package '{$path}'");
        }

        return $this->read($bytes);
    }

    public function read(string $bytes): AstNode
    {
        $zip = ZipPackage::fromString($bytes);
        $containerXml = $this->zipEntryContents($zip, 'META-INF/container.xml');
        if (!is_string($containerXml)) {
            throw new \InvalidArgumentException('EPUB package is missing META-INF/container.xml');
        }

        $rootfilePath = $this->rootfilePath($containerXml);
        $packageXml = $this->zipEntryContents($zip, $rootfilePath);
        if (!is_string($packageXml)) {
            throw new \InvalidArgumentException("EPUB package is missing rootfile '{$rootfilePath}'");
        }

        return $this->readPackageXml($packageXml);
    }

    public function readPackageXml(string $xml): AstNode
    {
        $dom = $this->loadXml($xml, 'EPUB package document');
        $package = $dom->documentElement;
        if (!$package instanceof \DOMElement || $package->localName !== 'package') {
            throw new \InvalidArgumentException('EPUB package document must have a package root element');
        }

        $meta = $this->packageMetadata($package);

        return new AstNode('document', $meta === [] ? [] : ['meta' => $meta]);
    }

    private function rootfilePath(string $containerXml): string
    {
        $dom = $this->loadXml($containerXml, 'EPUB container document');
        $xpath = new \DOMXPath($dom);
        $rootfiles = $xpath->query('//*[local-name()="rootfile"]');
        if (!$rootfiles instanceof \DOMNodeList) {
            throw new \InvalidArgumentException('EPUB container rootfile list cannot be read');
        }

        $fallback = null;
        foreach ($rootfiles as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }

            $path = trim($rootfile->getAttribute('full-path'));
            if ($path === '') {
                continue;
            }

            if ($this->mediaTypeBase($rootfile->getAttribute('media-type')) === self::OPF_MEDIA_TYPE) {
                return $this->normalizeZipPath($path);
            }

            $fallback ??= $path;
        }

        if ($fallback !== null) {
            return $this->normalizeZipPath($fallback);
        }

        throw new \InvalidArgumentException('EPUB container does not declare an OPF rootfile');
    }

    private function mediaTypeBase(string $mediaType): string
    {
        return strtolower(trim(explode(';', $mediaType, 2)[0]));
    }

    /**
     * @return array<string, mixed>
     */
    private function packageMetadata(\DOMElement $package): array
    {
        $metadata = null;
        foreach ($package->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'metadata') {
                $metadata = $child;
                break;
            }
        }

        if (!$metadata instanceof \DOMElement) {
            return [];
        }

        /** @var array<string, list<array{value:string, id:string}>> $dcValues */
        $dcValues = [];
        /** @var array<string, list<string>> $propertyValues */
        $propertyValues = [];

        foreach ($metadata->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isDublinCoreElement($child)) {
                $value = $this->elementText($child);
                if ($value === '') {
                    continue;
                }

                $dcValues[$child->localName][] = [
                    'value' => $value,
                    'id' => trim($child->getAttribute('id')),
                ];
                continue;
            }

            if ($child->localName !== 'meta') {
                continue;
            }

            $property = trim($child->getAttribute('property'));
            $value = $this->elementText($child);
            if ($property === '' || $value === '') {
                continue;
            }

            $propertyValues[$property][] = $value;
        }

        $meta = [];

        $title = $this->firstDcValue($dcValues, 'title');
        if ($title !== null) {
            $meta['title'] = $title;
            $meta['titleInlines'] = $this->textInlines($title);
        }

        $creators = $this->dcValueList($dcValues, 'creator');
        if ($creators !== []) {
            $meta['author'] = $this->collapseValueList($creators);
            $meta['authorInlines'] = array_map(fn (string $author): array => $this->textInlines($author), $creators);
        }

        $date = $this->firstDcValue($dcValues, 'date');
        if ($date !== null) {
            $meta['date'] = $date;
            $meta['dateInlines'] = $this->textInlines($date);
        }

        $languages = $this->dcValueList($dcValues, 'language');
        if ($languages !== []) {
            $meta['lang'] = $languages[0];
            $meta['language'] = $languages[0];
            if (count($languages) > 1) {
                $meta['languages'] = $languages;
            }
        }

        $identifier = $this->selectedIdentifier($dcValues['identifier'] ?? [], trim($package->getAttribute('unique-identifier')));
        if ($identifier !== null) {
            $meta['identifier'] = $identifier;
        }

        foreach (self::DC_FIELD_KEYS as $dcName => $metaKey) {
            $values = $this->dcValueList($dcValues, $dcName);
            if ($values !== []) {
                $meta[$metaKey] = $this->collapseValueList($values);
            }
        }

        $subjects = $this->dcValueList($dcValues, 'subject');
        if ($subjects !== []) {
            $meta['subject'] = $this->collapseValueList($subjects);
        }

        $modified = $this->firstPropertyValue($propertyValues, 'dcterms:modified');
        if ($modified !== null) {
            $meta['modified'] = $modified;
        }

        if ($propertyValues !== []) {
            ksort($propertyValues, SORT_STRING);
            $meta['epubProperties'] = $propertyValues;
        }

        return $meta;
    }

    private function isDublinCoreElement(\DOMElement $element): bool
    {
        return $element->namespaceURI === self::DC_NAMESPACE && $element->prefix === 'dc';
    }

    /**
     * @param array<string, list<array{value:string, id:string}>> $values
     */
    private function firstDcValue(array $values, string $name): ?string
    {
        return $values[$name][0]['value'] ?? null;
    }

    /**
     * @param array<string, list<array{value:string, id:string}>> $values
     * @return list<string>
     */
    private function dcValueList(array $values, string $name): array
    {
        $items = [];
        foreach ($values[$name] ?? [] as $item) {
            $items[] = $item['value'];
        }

        return $items;
    }

    /**
     * @param list<array{value:string, id:string}> $identifiers
     */
    private function selectedIdentifier(array $identifiers, string $uniqueIdentifierId): ?string
    {
        foreach ($identifiers as $identifier) {
            if ($uniqueIdentifierId !== '' && $identifier['id'] === $uniqueIdentifierId) {
                return $identifier['value'];
            }
        }

        return $identifiers[0]['value'] ?? null;
    }

    /**
     * @param array<string, list<string>> $values
     */
    private function firstPropertyValue(array $values, string $name): ?string
    {
        return $values[$name][0] ?? null;
    }

    /**
     * @param list<string> $values
     * @return string|list<string>
     */
    private function collapseValueList(array $values): string|array
    {
        return count($values) === 1 ? $values[0] : $values;
    }

    private function elementText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    private function normalizeZipPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException("EPUB rootfile path escapes the archive: '{$path}'");
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            throw new \InvalidArgumentException('EPUB rootfile path is empty');
        }

        return implode('/', $parts);
    }

    private function zipEntryContents(ZipPackage $zip, string $path): ?string
    {
        if (!$zip->has($path)) {
            return null;
        }

        return $zip->read($path);
    }

    private function loadXml(string $xml, string $context): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException("{$context} is not well-formed XML");
        }

        return $dom;
    }
}
