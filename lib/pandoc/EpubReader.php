<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubReader
{
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const EPUB_FOOTNOTE_DEFINITION_LINK_ATTR = '_epubFootnoteDefinitionLink';
    private const EPUB_SEMANTIC_TYPES_ATTR = '_epubSemanticTypes';
    /** @var array<string, true> */
    private const HTML_VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];
    /**
     * @var array<string, string>
     */
    private const DC_METADATA_FIELD_KEYS = [
        'description' => 'description',
        'publisher' => 'publisher',
        'rights' => 'rights',
        'source' => 'source',
        'relation' => 'relation',
        'coverage' => 'coverage',
        'type' => 'type',
        'format' => 'format',
    ];

    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        try {
            $package = EpubArchiveFactory::fromString($bytes);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return $this->fallbackDocument($bytes, $exception->getMessage());
        }

        return $this->readZipPackage($package);
    }

    /**
     * Yield completed top-level EPUB nodes as each spine item is processed.
     * The generator return value is the final package metadata; callers that
     * only need rendered output can avoid retaining the document-wide AST.
     *
     * @return \Generator<int, AstNode, mixed, array<string, mixed>>
     */
    public function streamNodes(string $bytes): \Generator
    {
        try {
            $package = EpubArchiveFactory::fromString($bytes);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $document = $this->fallbackDocument($bytes, $exception->getMessage());
            foreach ($document->children as $child) {
                yield $child;
            }

            return is_array($document->attr('meta', [])) ? $document->attr('meta', []) : [];
        }

        return yield from $this->streamZipPackageNodes($package);
    }

    public function readEpubFile(string $path): AstNode
    {
        try {
            $package = EpubArchiveFactory::fromFile($path);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $bytes = @file_get_contents($path);
            if ($bytes === false) {
                throw new \InvalidArgumentException("Unable to open EPUB package '{$path}'.");
            }

            return $this->fallbackDocument($bytes, $exception->getMessage());
        }

        return $this->readZipPackage($package);
    }

    /**
     * @return \Generator<int, AstNode, mixed, array<string, mixed>>
     */
    public function streamEpubFileNodes(string $path): \Generator
    {
        try {
            $package = EpubArchiveFactory::fromFile($path);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $bytes = @file_get_contents($path);
            if ($bytes === false) {
                throw new \InvalidArgumentException("Unable to open EPUB package '{$path}'.");
            }
            $document = $this->fallbackDocument($bytes, $exception->getMessage());
            foreach ($document->children as $child) {
                yield $child;
            }

            return is_array($document->attr('meta', [])) ? $document->attr('meta', []) : [];
        }

        return yield from $this->streamZipPackageNodes($package);
    }

    private function fallbackDocument(string $bytes, string $error): AstNode
    {
        $meta = [
            'sourceFormat' => 'epub',
            'epubFallback' => [
                'reason' => 'invalid-epub-package',
                'error' => $error,
                'sourceBytes' => strlen($bytes),
                'sourceSha256' => hash('sha256', $bytes),
            ],
        ];

        if ($this->looksLikeHtmlDocument($bytes)) {
            try {
                $document = (new HtmlReader())->read($bytes);

                return new AstNode('document', [
                    'sourceFormat' => 'epub',
                    'meta' => array_replace($document->attr('meta', []), $meta),
                ], $document->children);
            } catch (\Throwable) {
            }
        }

        return new AstNode('document', [
            'sourceFormat' => 'epub',
            'meta' => $meta,
        ], [
            new AstNode('code_block', [
                'text' => $this->sourcePreview($bytes),
                'classes' => ['epub-source'],
            ]),
        ]);
    }

    private function looksLikeHtmlDocument(string $bytes): bool
    {
        $prefix = strtolower(ltrim(substr($bytes, 0, 2048)));

        return str_starts_with($prefix, '<!doctype html')
            || str_starts_with($prefix, '<html')
            || str_contains($prefix, '<html ');
    }

    private function sourcePreview(string $bytes): string
    {
        if (strlen($bytes) <= 8192) {
            return $bytes;
        }

        return substr($bytes, 0, 8192) . "\n...";
    }

    private function readZipPackage(EpubArchive $zip): AstNode
    {
        $nodes = $this->streamZipPackageNodes($zip);
        $children = [];
        foreach ($nodes as $node) {
            $children[] = $node;
        }
        $metadata = $nodes->getReturn();

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    /**
     * @return \Generator<int, AstNode, mixed, array<string, mixed>>
     */
    private function streamZipPackageNodes(EpubArchive $zip): \Generator
    {
        $container_xml = $this->zipEntryContents($zip, 'META-INF/container.xml');
        if (!is_string($container_xml)) {
            throw new \InvalidArgumentException('EPUB package is missing META-INF/container.xml.');
        }

        $rootfile = $this->rootfilePath($container_xml);
        $opf_xml = $this->zipEntryContents($zip, $rootfile);
        if (!is_string($opf_xml)) {
            throw new \InvalidArgumentException("EPUB package is missing OPF rootfile '{$rootfile}'.");
        }

        return yield from $this->streamPackageNodes($zip, $rootfile, $opf_xml);
    }

    /**
     * @return \Generator<int, AstNode, mixed, array<string, mixed>>
     */
    private function streamPackageNodes(EpubArchive $zip, string $rootfile, string $opf_xml): \Generator
    {
        $dom = $this->loadXml($opf_xml, 'EPUB OPF package');
        $package = $dom->documentElement;
        if (!$package instanceof \DOMElement || $package->localName !== 'package') {
            throw new \InvalidArgumentException('EPUB OPF root must be a package element.');
        }

        $base_path = $this->dirname($rootfile);
        $metadata = $this->metadata($package);
        $manifest = $this->manifest($package);
        $package_links = $this->packageLinks($package, $rootfile, $base_path, $manifest);
        $package_link_vocabulary = null;
        if ($this->canAnnotateEpubPackageLinks($zip, $package)) {
            $package_link_annotations = EpubLinkVocabulary::annotate($package_links, $package->getAttribute('prefix'));
            $package_links = $package_link_annotations['links'];
            $package_link_vocabulary = $package_link_annotations['summary'];
        }
        $spine_items = $this->spineItems($package, $base_path, $manifest);
        $guide_references = $this->guideReferences($package, $base_path, $manifest);
        $accessibility = $this->accessibilityMetadata($package, $package_links);
        $toc = $this->toc($zip, $base_path, $manifest, $this->spineTocId($package));
        $resources = [];
        $referenced_resources = [];
        $media_bag_resources = [];
        $media_bag_sources = [];
        $image_resources = $this->imageResources($base_path, $manifest);
        $spine_filenames = array_map(
            fn (array $spine_item): string => $this->spineFilename((string) ($spine_item['href'] ?? '')),
            array_values(array_filter(
                $spine_items,
                fn (array $spine_item): bool => ($spine_item['linear'] ?? true) === true
            ))
        );

        $cover = $this->coverImageHref($package, $manifest);
        if ($cover !== null) {
            $cover_resource = $this->recordMediaBagResource($cover, '', $base_path, $media_bag_resources);
            if ($cover_resource !== null) {
                $this->recordMediaBagSource($this->mediaBagSourceUrl($cover), $cover_resource, $media_bag_sources);
            }
            yield new AstNode('paragraph', ['text' => ''], [
                new AstNode('image', [
                    'url' => $cover,
                    'title' => '',
                    'alt' => '',
                ]),
            ]);
        }

        foreach ($spine_items as $spine_item) {
            if (($spine_item['linear'] ?? true) !== true) {
                continue;
            }
            $idref = $spine_item['idref'];
            if (!isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            $media_type = $item['media-type'];
            if ($this->isAbsoluteUrl($item['href'])) {
                continue;
            }
            $href = $this->packagePartPath($item['href'], $base_path);
            if ($this->isDirectSpineImageMediaType($media_type)) {
                $resources[] = $href;
                $this->recordReferencedResource($item['href'], '', $base_path, $referenced_resources);
                $media_bag_resources[] = $href;
                $this->recordMediaBagSource($this->mediaBagSourceUrl($item['href']), $href, $media_bag_sources);
                yield $this->spineMarker($this->spineFilename($item['href']));
                yield $this->directImageSpineBlock($item['href']);
                continue;
            }
            if (!$this->isReadablePackageXhtml($media_type)) {
                yield $this->spineMarker($this->spineFilename($item['href']));
                continue;
            }
            $xhtml = $this->zipEntryContents($zip, $href);
            if (!is_string($xhtml)) {
                continue;
            }
            $xhtml = $this->normalizeEpubXmlSource($xhtml);
            $resources[] = $href;
            $content_document_was_xml = false;
            $content_dom = $this->contentDocumentDom($xhtml, $content_document_was_xml);
            $content_base_href = $content_dom instanceof \DOMDocument
                ? $this->epubContentDocumentBaseHref($content_dom)
                : null;
            $footnote_definitions = $this->epubFootnoteDefinitionsInReferenceOrder(
                $content_dom,
                $item['href'],
                $content_document_was_xml
            );
            $note_reference_hrefs = $this->epubNoteReferenceHrefs($content_dom, $content_base_href);
            $link_attribute_overlays_by_href = $this->epubBodyLinkAttributeOverlaysByHref($content_dom, $content_base_href);
            $picture_raw_html_overlays = $this->epubPictureRawHtmlOverlaysInImageOrder($content_dom, $content_base_href);
            $this->recordEpubContentRawMediaResources(
                $content_dom,
                $xhtml,
                $this->dirname($this->stripUrlQueryAndFragment($item['href'])),
                $base_path,
                $referenced_resources,
                $media_bag_resources,
                $media_bag_sources
            );
            $document = $this->epubContentHtmlReader($content_document_was_xml)->read($this->contentDocumentMarkupForHtmlReader(
                $xhtml,
                $content_dom,
                $content_base_href,
                $content_document_was_xml,
            ));
            $document = $this->normalizeEpubMediaRawBlocks($document);
            $document = $this->normalizeEpubRawInlineVoidElements($document);
            if ($picture_raw_html_overlays !== []) {
                $picture_image_index = 0;
                $document = $this->restoreEpubPictureRawHtml($document, $picture_raw_html_overlays, $picture_image_index);
            }
            if ($footnote_definitions !== []) {
                $footnote_index = 0;
                $document = $this->fillEmptyEpubFootnoteNotes($document, $footnote_definitions, $footnote_index);
            }
            $document = $this->fixEpubContentReferences(
                $document,
                $item['href'],
                $base_path,
                $spine_filenames,
                $note_reference_hrefs,
                $link_attribute_overlays_by_href,
                $referenced_resources,
                $media_bag_resources,
                $media_bag_sources
            );
            $document = $this->normalizeEpubChapterSectioningContent($document);
            $document = $this->compactEpubInlineChildren($document);
            yield $this->spineMarker($this->spineFilename($item['href']));
            foreach ($document->children as $child) {
                yield $child;
            }
            unset(
                $document,
                $content_dom,
                $xhtml,
                $footnote_definitions,
                $note_reference_hrefs,
                $link_attribute_overlays_by_href,
                $picture_raw_html_overlays
            );
        }

        $metadata['epubRootfile'] = $rootfile;
        $metadata['epubManifestItemCount'] = count($manifest);
        $metadata['epubManifestItems'] = $this->manifestItemsMetadata($base_path, $manifest);
        $metadata['epubPackageLinkCount'] = count($package_links);
        $metadata['epubPackageLinkRelCounts'] = $this->packageLinkRelCounts($package_links);
        $metadata['epubPackageLinkTargets'] = $this->packageLinkTargets($package_links);
        if ($package_link_vocabulary !== null) {
            $metadata['epubPackageLinkVocabulary'] = $package_link_vocabulary;
            if (is_array($package_link_vocabulary['diagnostics'] ?? null)) {
                $metadata['epubPackageLinkVocabularyDiagnostics'] = $package_link_vocabulary['diagnostics'];
            }
        }
        if ($package_links !== []) {
            $metadata['epubPackageLinks'] = $package_links;
        }
        $metadata['epubSpineItems'] = count($spine_items);
        $metadata['epubSpineItemRefs'] = $spine_items;
        $metadata['epubGuideReferenceCount'] = count($guide_references);
        $metadata['epubGuideReferenceTypes'] = $this->guideReferenceTypes($guide_references);
        $metadata['epubGuideReferenceTypeCounts'] = $this->guideReferenceTypeCounts($guide_references);
        if ($guide_references !== []) {
            $metadata['epubGuideReferences'] = $guide_references;
        }
        $metadata['epubAccessibilityPresent'] = $accessibility['present'];
        $metadata['epubAccessibilityEntryCount'] = count($accessibility['entries']);
        $metadata['epubAccessibilityLinkedRecordCount'] = count($accessibility['linkedRecords']);
        $metadata['epubAccessibilityPropertyCounts'] = $accessibility['propertyCounts'];
        if ($accessibility['present'] === true) {
            $metadata['epubAccessibility'] = $accessibility;
        }
        $metadata['epubReadableResources'] = $resources;
        $metadata['epubReferencedResources'] = array_values(array_unique($referenced_resources));
        $metadata['epubImageResources'] = $image_resources;
        $metadata['epubMediaBagResources'] = array_values(array_unique($media_bag_resources));
        $media_bag = $this->readEpubMediaBag($zip, $base_path, $manifest, $media_bag_sources);
        $metadata['epubMediaResourcePolicy'] = 'reader-media-bag-from-emitted-local-media-resources';
        $metadata['epubMediaResourceDirectory'] = $media_bag['directory'];
        $metadata['epubMediaResourceCount'] = count($media_bag['directory']);
        $metadata['epubMediaResourceDiagnostics'] = $media_bag['diagnostics'];
        $metadata['epubTocResources'] = $toc['resources'];
        $metadata['epubTocEntryCount'] = count($toc['entries']);
        $metadata['epubLandmarkEntryCount'] = count($toc['landmarks']);
        $metadata['epubPageListEntryCount'] = count($toc['pageList']);
        $metadata['epubAuxiliaryNavigationEntryCount'] = count($toc['auxiliary']);
        $metadata['epubNavigationSectionCount'] = count($toc['sections']);
        $metadata['epubNavigationSectionTypes'] = $toc['sectionTypes'];
        if ($toc['entries'] !== []) {
            $metadata['epubTocEntries'] = $toc['entries'];
        }
        if ($toc['landmarks'] !== []) {
            $metadata['epubLandmarkEntries'] = $toc['landmarks'];
        }
        if ($toc['pageList'] !== []) {
            $metadata['epubPageListEntries'] = $toc['pageList'];
        }
        if ($toc['auxiliary'] !== []) {
            $metadata['epubAuxiliaryNavigationEntries'] = $toc['auxiliary'];
        }
        if ($toc['sections'] !== []) {
            $metadata['epubNavigationSections'] = $toc['sections'];
        }

        return $metadata;
    }

    private function rootfilePath(string $container_xml): string
    {
        $dom = $this->loadXml($container_xml, 'EPUB container');
        $xpath = new \DOMXPath($dom);
        $rootfiles = $xpath->query('//*[local-name()="rootfile"]');
        if (!$rootfiles instanceof \DOMNodeList) {
            throw new \InvalidArgumentException('EPUB container rootfile list cannot be read.');
        }

        $fallback = '';
        foreach ($rootfiles as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }
            $path = trim($rootfile->getAttribute('full-path'));
            if ($path === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $path;
            }
            if ($this->mediaTypeBase($rootfile->getAttribute('media-type')) === self::OPF_MEDIA_TYPE) {
                return $this->packagePartPath($path);
            }
        }

        if ($fallback !== '') {
            return $this->packagePartPath($fallback);
        }

        throw new \InvalidArgumentException('EPUB container does not declare an OPF rootfile.');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMElement $package): array
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

        $dc_values = [];
        $property_values = [];
        foreach ($metadata->childNodes as $entry) {
            if (!$entry instanceof \DOMElement) {
                continue;
            }

            $text = $this->metadataElementText($entry);
            if ($this->isDublinCoreMetadataElement($entry)) {
                if ($text === '') {
                    continue;
                }
                $dc_values[$entry->localName][] = [
                    'value' => $text,
                    'id' => trim($entry->getAttribute('id')),
                ];
                continue;
            }

            if ($entry->localName !== 'meta' || $text === '') {
                continue;
            }

            $property = trim($entry->getAttribute('property'));
            if ($property !== '') {
                $property_values[$property][] = $text;
            }
        }

        $meta = [];
        $titles = $this->metadataValueList($dc_values, 'title');
        if ($titles !== []) {
            $meta['title'] = $this->metadataScalarOrRepeatedInlineMetaValue($titles);
            $meta['titleInlines'] = $this->metadataTextInlines($titles[0]);
        }

        $creators = $this->metadataValueList($dc_values, 'creator');
        if ($creators !== []) {
            if (count($creators) > 1) {
                $creators = array_reverse($creators);
            }
            $meta['author'] = $this->collapseMetadataValueList($creators);
            $meta['authorInlines'] = array_map(fn (string $author): array => $this->metadataTextInlines($author), $creators);
        }

        $dates = $this->metadataValueList($dc_values, 'date');
        if ($dates !== []) {
            $meta['date'] = $this->metadataScalarOrRepeatedInlineMetaValue($dates);
            $meta['dateInlines'] = $this->metadataTextInlines($dates[0]);
        }

        $languages = $this->metadataValueList($dc_values, 'language');
        if ($languages !== []) {
            $meta['lang'] = $languages[0];
            $meta['language'] = $this->metadataScalarOrRepeatedInlineMetaValue($languages);
            if (count($languages) > 1) {
                $meta['languages'] = $languages;
            }
        }

        $identifiers = $this->metadataValueList($dc_values, 'identifier');
        if ($identifiers !== []) {
            $meta['identifier'] = $this->metadataScalarOrRepeatedInlineMetaValue($identifiers);
        }
        $selected_identifier = $this->selectedMetadataIdentifier($dc_values['identifier'] ?? [], trim($package->getAttribute('unique-identifier')));
        if ($selected_identifier !== null) {
            $meta['epubSelectedIdentifier'] = $selected_identifier;
        }

        foreach (self::DC_METADATA_FIELD_KEYS as $dc_name => $meta_key) {
            $values = $this->metadataValueList($dc_values, $dc_name);
            if ($values !== []) {
                $meta[$meta_key] = $this->metadataScalarOrRepeatedInlineMetaValue($values);
            }
        }

        $subjects = $this->metadataValueList($dc_values, 'subject');
        if ($subjects !== []) {
            $meta['subject'] = $this->metadataScalarOrRepeatedInlineMetaValue($subjects);
        }

        $contributor = $this->metadataInlineMetaValue($this->metadataValueList($dc_values, 'contributor'));
        if ($contributor !== null) {
            $meta['contributor'] = $contributor;
        }

        if (isset($property_values['dcterms:modified'][0])) {
            $meta['modified'] = $property_values['dcterms:modified'][0];
        }
        if ($property_values !== []) {
            ksort($property_values, SORT_STRING);
            $meta['epubProperties'] = $property_values;
        }

        return $meta;
    }

    private function isDublinCoreMetadataElement(\DOMElement $element): bool
    {
        return $element->namespaceURI === self::DC_NAMESPACE && $element->prefix === 'dc';
    }

    /**
     * @param array<string, list<array{value: string, id: string}>> $values
     * @return list<string>
     */
    private function metadataValueList(array $values, string $name): array
    {
        $items = [];
        foreach ($values[$name] ?? [] as $item) {
            $items[] = $item['value'];
        }

        return $items;
    }

    /**
     * @param list<string> $values
     * @return string|list<string>
     */
    private function collapseMetadataValueList(array $values): string|array
    {
        return count($values) === 1 ? $values[0] : $values;
    }

    /**
     * @param list<array{value: string, id: string}> $identifiers
     */
    private function selectedMetadataIdentifier(array $identifiers, string $unique_identifier_id): ?string
    {
        foreach ($identifiers as $identifier) {
            if ($unique_identifier_id !== '' && $identifier['id'] === $unique_identifier_id) {
                return $identifier['value'];
            }
        }

        return $identifiers[0]['value'] ?? null;
    }

    private function metadataElementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
    }

    /**
     * @return list<AstNode>
     */
    private function metadataTextInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<string> $values
     * @return array{type: string, value: mixed}|null
     */
    private function metadataInlineMetaValue(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        $items = [];
        // Pandoc's addMetaField prepends duplicate metadata values.
        foreach (array_reverse($values) as $value) {
            $items[] = [
                'type' => 'MetaInlines',
                'value' => $this->metadataTextInlines($value),
            ];
        }

        if (count($items) === 1) {
            return $items[0];
        }

        return [
            'type' => 'MetaList',
            'value' => $items,
        ];
    }

    /**
     * @param list<string> $values
     * @return string|array{type: string, value: mixed}
     */
    private function metadataScalarOrRepeatedInlineMetaValue(array $values): string|array
    {
        if (count($values) === 1) {
            return $values[0];
        }

        return $this->metadataInlineMetaValue($values) ?? '';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifest(\DOMElement $package): array
    {
        $items = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'item') {
                continue;
            }
            $id = trim($element->getAttribute('id'));
            $href = trim($element->getAttribute('href'));
            if ($id === '' || $href === '') {
                continue;
            }
            $items[$id] = [
                'href' => html_entity_decode($href, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                'media-type' => trim($element->getAttribute('media-type')),
                'properties' => array_values(array_filter(
                    preg_split('/\s+/', trim($element->getAttribute('properties'))) ?: [],
                    static fn (string $property): bool => $property !== ''
                )),
                'fallback' => $this->nullableManifestReferenceId($element, 'fallback'),
                'fallback-style' => $this->nullableManifestReferenceId($element, 'fallback-style'),
                'media-overlay' => $this->nullableManifestReferenceId($element, 'media-overlay'),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<string>
     */
    private function imageResources(string $base_path, array $manifest): array
    {
        $resources = [];
        foreach ($manifest as $item) {
            $item_href = (string) ($item['href'] ?? '');
            if ($this->isAbsoluteUrl($item_href)) {
                continue;
            }
            $href = $this->packagePartPath($item_href, $base_path);
            $media_type = strtolower((string) ($item['media-type'] ?? ''));
            if (str_starts_with($media_type, 'image/') || $this->pathLooksLikeImage($href)) {
                $resources[] = $href;
            }
        }

        return array_values(array_unique($resources));
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function manifestItemsMetadata(string $base_path, array $manifest): array
    {
        $items = [];
        foreach ($manifest as $id => $item) {
            $href = (string) ($item['href'] ?? '');
            $path = $this->rewriteRelativeResourceUrl($href, $base_path);
            $part_path = $this->isAbsoluteUrl($href) ? $path : $this->packagePartPath($href, $base_path);
            $media_type = (string) ($item['media-type'] ?? '');
            $properties = is_array($item['properties'] ?? null) ? array_values($item['properties']) : [];
            $lower_properties = array_map('strtolower', $properties);
            $media_type_lower = strtolower($media_type);

            $metadata = [
                'id' => $id,
                'href' => $href,
                'path' => $path,
                'mediaType' => $media_type,
                'properties' => $properties,
                'external' => $this->isAbsoluteUrl($href),
                'readable' => !$this->isAbsoluteUrl($href) && $this->isReadableSpineItem($media_type),
                'navigation' => in_array('nav', $lower_properties, true),
                'ncx' => str_contains($media_type_lower, 'x-dtbncx') || str_ends_with(strtolower($part_path), '.ncx'),
                'coverImage' => in_array('cover-image', $lower_properties, true),
            ];
            $metadata += $this->manifestItemReferenceMetadata(
                'fallback',
                is_string($item['fallback'] ?? null) ? $item['fallback'] : null,
                $base_path,
                $manifest
            );
            $metadata += $this->manifestItemReferenceMetadata(
                'fallbackStyle',
                is_string($item['fallback-style'] ?? null) ? $item['fallback-style'] : null,
                $base_path,
                $manifest
            );
            $metadata += $this->manifestItemReferenceMetadata(
                'mediaOverlay',
                is_string($item['media-overlay'] ?? null) ? $item['media-overlay'] : null,
                $base_path,
                $manifest
            );
            $items[] = $metadata;
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function manifestItemReferenceMetadata(string $prefix, ?string $id, string $base_path, array $manifest): array
    {
        if ($id === null || $id === '') {
            return [];
        }

        $metadata = [
            $prefix . 'Id' => $id,
            $prefix . 'Missing' => !isset($manifest[$id]),
        ];
        $target = $manifest[$id] ?? null;
        if (!is_array($target)) {
            return $metadata;
        }

        $href = (string) ($target['href'] ?? '');
        $media_type = (string) ($target['media-type'] ?? '');
        $properties = is_array($target['properties'] ?? null) ? array_values($target['properties']) : [];
        $external = $href !== '' && $this->isAbsoluteUrl($href);

        return $metadata + [
            $prefix . 'Href' => $href,
            $prefix . 'Path' => $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path),
            $prefix . 'MediaType' => $media_type,
            $prefix . 'Properties' => $properties,
            $prefix . 'External' => $external,
            $prefix . 'Readable' => !$external && $this->isReadableSpineItem($media_type),
        ];
    }

    private function nullableManifestReferenceId(\DOMElement $element, string $attribute): ?string
    {
        $id = trim($element->getAttribute($attribute));

        return $id === '' ? null : $id;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function packageLinks(\DOMElement $package, string $rootfile, string $base_path, array $manifest): array
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

        $manifest_by_path = [];
        foreach ($manifest as $id => $item) {
            $href = (string) ($item['href'] ?? '');
            if ($this->isAbsoluteUrl($href)) {
                continue;
            }

            $path = $this->packagePartPath($href, $base_path);
            if ($path !== '' && !isset($manifest_by_path[$path])) {
                $manifest_by_path[$path] = [
                    'id' => $id,
                    'media-type' => (string) ($item['media-type'] ?? ''),
                    'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
                ];
            }
        }

        $links = [];
        foreach ($metadata->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'link') {
                continue;
            }

            $href = html_entity_decode(trim($child->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $external = $href !== '' && $this->isAbsoluteUrl($href);
            [$target, $path, $query, $fragment] = $this->packageLinkTargetParts($href, $rootfile, $base_path);
            $manifest_item = $path !== '' ? ($manifest_by_path[$path] ?? null) : null;
            $media_type = trim($child->getAttribute('media-type'));
            $title = trim($child->getAttribute('title'));
            $hreflang = trim($child->getAttribute('hreflang'));
            $refines = trim($child->getAttribute('refines'));

            $links[] = [
                'index' => count($links),
                'id' => trim($child->getAttribute('id')) !== '' ? trim($child->getAttribute('id')) : null,
                'rel' => $this->tokenList($child->getAttribute('rel')),
                'href' => $href,
                'target' => $target,
                'path' => $external ? '' : $path,
                'external' => $external,
                'mediaType' => $media_type !== '' ? $media_type : null,
                'properties' => $this->tokenList($child->getAttribute('properties')),
                'title' => $title !== '' ? $title : null,
                'hreflang' => $hreflang !== '' ? $hreflang : null,
                'refines' => $refines !== '' ? $refines : null,
                'subjectId' => $this->metadataRefinementSubjectId($refines),
                'hrefHasQuery' => $query !== null,
                'hrefQuery' => $query,
                'hrefHasFragment' => $fragment !== null,
                'hrefFragment' => $fragment,
                'manifestId' => is_array($manifest_item) ? (string) $manifest_item['id'] : null,
                'manifestMediaType' => is_array($manifest_item) ? (string) $manifest_item['media-type'] : null,
                'manifestProperties' => is_array($manifest_item) ? array_values($manifest_item['properties']) : [],
            ];
        }

        return $links;
    }

    private function canAnnotateEpubPackageLinks(EpubArchive $zip, \DOMElement $package): bool
    {
        if ($package->namespaceURI !== 'http://www.idpf.org/2007/opf' || !$zip->has('mimetype')) {
            return false;
        }

        try {
            return trim($zip->read('mimetype')) === 'application/epub+zip';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: ?string}
     */
    private function packageLinkTargetParts(string $href, string $rootfile, string $base_path): array
    {
        if ($href === '') {
            return ['', '', null, null];
        }

        if ($this->isAbsoluteUrl($href)) {
            [, $query, $fragment] = $this->splitUrlSuffix($href);

            return [$href, '', $query, $fragment];
        }

        [$href_path, $query, $fragment] = $this->splitUrlSuffix($href);
        if ($href_path === '') {
            $path = $this->normalizeZipPath($rootfile);

            return [$this->appendUrlSuffix($path, $query, $fragment), $path, $query, $fragment];
        }

        $target = $this->rewriteRelativeResourceUrl($href, $base_path);
        $path = $this->packagePartPath($href, $base_path);

        return [$target, $path, $query, $fragment];
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, int>
     */
    private function packageLinkRelCounts(array $links): array
    {
        $counts = [];
        foreach ($links as $link) {
            $rels = $link['rel'] ?? [];
            if (!is_array($rels)) {
                continue;
            }
            foreach ($rels as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }
                $counts[$rel] = ($counts[$rel] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return list<string>
     */
    private function packageLinkTargets(array $links): array
    {
        $targets = [];
        foreach ($links as $link) {
            $target = $link['target'] ?? null;
            if (is_string($target) && $target !== '') {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function spineItems(\DOMElement $package, string $base_path, array $manifest): array
    {
        $items = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'itemref') {
                continue;
            }
            $idref = trim($element->getAttribute('idref'));
            if ($idref === '') {
                continue;
            }
            $manifest_item = $manifest[$idref] ?? null;
            $href = is_array($manifest_item) ? (string) ($manifest_item['href'] ?? '') : '';
            $path = $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path);
            $part_path = $href === '' || $this->isAbsoluteUrl($href) ? $path : $this->packagePartPath($href, $base_path);
            $media_type = is_array($manifest_item) ? (string) ($manifest_item['media-type'] ?? '') : '';
            $manifest_properties = is_array($manifest_item) && is_array($manifest_item['properties'] ?? null)
                ? array_values($manifest_item['properties'])
                : [];
            $external = $href !== '' && $this->isAbsoluteUrl($href);
            $linear = !$element->hasAttribute('linear') || $element->getAttribute('linear') === 'yes';

            $item = [
                'index' => count($items),
                'id' => trim($element->getAttribute('id')) !== '' ? trim($element->getAttribute('id')) : null,
                'idref' => $idref,
                'href' => $href,
                'path' => $path,
                'mediaType' => $media_type,
                'linear' => $linear,
                'properties' => $this->tokenList($element->getAttribute('properties')),
                'manifestProperties' => $manifest_properties,
                'missingManifestItem' => !is_array($manifest_item),
                'external' => $external,
                'readable' => is_array($manifest_item) && !$external && $this->isReadableSpineItem($media_type),
            ];
            if (is_array($manifest_item)) {
                $item += $this->manifestItemReferenceMetadata(
                    'fallback',
                    is_string($manifest_item['fallback'] ?? null) ? $manifest_item['fallback'] : null,
                    $base_path,
                    $manifest
                );
                $item += $this->manifestItemReferenceMetadata(
                    'fallbackStyle',
                    is_string($manifest_item['fallback-style'] ?? null) ? $manifest_item['fallback-style'] : null,
                    $base_path,
                    $manifest
                );
                $item += $this->manifestItemReferenceMetadata(
                    'mediaOverlay',
                    is_string($manifest_item['media-overlay'] ?? null) ? $manifest_item['media-overlay'] : null,
                    $base_path,
                    $manifest
                );
            }
            $items[] = $item;
        }

        return $items;
    }

    private function spineTocId(\DOMElement $package): string
    {
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'spine') {
                continue;
            }

            return trim($element->getAttribute('toc'));
        }

        return '';
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array{index: int, type: string, typeRaw: string, types: list<string>, title: string, href: string, target: string, path: string, fragment: ?string, hrefHasQuery: bool, hrefQuery: ?string, hrefHasFragment: bool, hrefFragment: ?string, external: bool, manifestId: ?string, manifestMediaType: ?string, manifestProperties: list<string>}>
     */
    private function guideReferences(\DOMElement $package, string $base_path, array $manifest): array
    {
        $guide = null;
        foreach ($package->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'guide') {
                $guide = $child;
                break;
            }
        }
        if (!$guide instanceof \DOMElement) {
            return [];
        }

        $manifest_by_path = [];
        foreach ($manifest as $id => $item) {
            $href = (string) ($item['href'] ?? '');
            if ($this->isAbsoluteUrl($href)) {
                continue;
            }

            $path = $this->packagePartPath($href, $base_path);
            if ($path !== '' && !isset($manifest_by_path[$path])) {
                $manifest_by_path[$path] = [
                    'id' => $id,
                    'media-type' => (string) ($item['media-type'] ?? ''),
                    'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
                ];
            }
        }

        $references = [];
        foreach ($guide->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'reference') {
                continue;
            }

            $href = html_entity_decode(trim($child->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $external = $href !== '' && $this->isAbsoluteUrl($href);
            [, $query, $fragment] = $href === '' ? ['', null, null] : $this->splitUrlSuffix($href);
            $target = $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path);
            $path = (!$external && $href !== '') ? $this->packagePartPath($href, $base_path) : '';
            $manifest_item = $path !== '' ? ($manifest_by_path[$path] ?? null) : null;
            $type_raw = trim($child->getAttribute('type'));
            $types = $this->tokenList($type_raw);

            $references[] = [
                'index' => count($references),
                'type' => $types[0] ?? '',
                'typeRaw' => $type_raw,
                'types' => $types,
                'title' => trim($child->getAttribute('title')),
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'hrefHasQuery' => $query !== null,
                'hrefQuery' => $query,
                'hrefHasFragment' => $fragment !== null,
                'hrefFragment' => $fragment,
                'external' => $external,
                'manifestId' => is_array($manifest_item) ? (string) $manifest_item['id'] : null,
                'manifestMediaType' => is_array($manifest_item) ? (string) $manifest_item['media-type'] : null,
                'manifestProperties' => is_array($manifest_item) ? array_values($manifest_item['properties']) : [],
            ];
        }

        return $references;
    }

    /**
     * @param list<array<string, mixed>> $references
     * @return list<string>
     */
    private function guideReferenceTypes(array $references): array
    {
        $types = [];
        foreach ($references as $reference) {
            $reference_types = $reference['types'] ?? [];
            if (!is_array($reference_types)) {
                continue;
            }
            foreach ($reference_types as $type) {
                if (is_string($type) && $type !== '' && !isset($types[$type])) {
                    $types[$type] = true;
                }
            }
        }

        return array_keys($types);
    }

    /**
     * @param list<array<string, mixed>> $references
     * @return array<string, int>
     */
    private function guideReferenceTypeCounts(array $references): array
    {
        $counts = [];
        foreach ($references as $reference) {
            $reference_types = $reference['types'] ?? [];
            if (!is_array($reference_types)) {
                continue;
            }
            foreach ($reference_types as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $package_links
     * @return array{present: bool, entries: list<array<string, mixed>>, propertyCounts: array<string, int>, accessModes: list<string>, accessModeSufficient: list<array{text: string, modes: list<string>, source: string, id: ?string}>, accessibilityFeatures: list<string>, accessibilityHazards: list<string>, accessibilityControls: list<string>, accessibilityApis: list<string>, accessibilitySummary: ?string, certification: array{certifiedBy: ?string, certifierCredential: ?string, certifierReport: ?string, conformsTo: list<string>}, linkedRecords: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>}
     */
    private function accessibilityMetadata(\DOMElement $package, array $package_links): array
    {
        $metadata = null;
        foreach ($package->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'metadata') {
                $metadata = $child;
                break;
            }
        }

        $entries = [];
        if ($metadata instanceof \DOMElement) {
            foreach ($metadata->childNodes as $entry) {
                if (!$entry instanceof \DOMElement || $entry->localName !== 'meta') {
                    continue;
                }

                $raw_property = trim($entry->getAttribute('property'));
                $raw_name = trim($entry->getAttribute('name'));
                $property = $this->canonicalAccessibilityProperty($raw_property);
                $source = 'property';
                if ($property === null) {
                    $property = $this->canonicalAccessibilityProperty($raw_name);
                    $source = 'name';
                }
                if ($property === null) {
                    continue;
                }

                $text = $this->metadataElementText($entry);
                $content = trim($entry->getAttribute('content'));
                if ($text === '' && $content !== '') {
                    $text = $content;
                }
                if ($text === '') {
                    continue;
                }

                $id = trim($entry->getAttribute('id'));
                $refines = trim($entry->getAttribute('refines'));
                $entries[] = [
                    'property' => $property,
                    'source' => $source,
                    'rawProperty' => $raw_property !== '' ? $raw_property : null,
                    'rawName' => $raw_name !== '' ? $raw_name : null,
                    'text' => $text,
                    'content' => $content !== '' ? $content : null,
                    'id' => $id !== '' ? $id : null,
                    'refines' => $refines !== '' ? $refines : null,
                    'subjectId' => $this->metadataRefinementSubjectId($refines),
                ];
            }
        }

        $entries_by_property = [];
        $property_counts = [];
        foreach ($entries as $entry) {
            $property = (string) $entry['property'];
            $entries_by_property[$property][] = $entry;
            $property_counts[$property] = ($property_counts[$property] ?? 0) + 1;
        }

        $linked_records = $this->accessibilityLinkedRecords($package_links);

        return [
            'present' => $entries !== [] || $linked_records !== [],
            'entries' => $entries,
            'propertyCounts' => $property_counts,
            'accessModes' => $this->accessibilityValues($entries_by_property, 'accessMode'),
            'accessModeSufficient' => $this->accessModeSufficientEntries($entries_by_property),
            'accessibilityFeatures' => $this->accessibilityValues($entries_by_property, 'accessibilityFeature'),
            'accessibilityHazards' => $this->accessibilityValues($entries_by_property, 'accessibilityHazard'),
            'accessibilityControls' => $this->accessibilityValues($entries_by_property, 'accessibilityControl'),
            'accessibilityApis' => $this->accessibilityValues($entries_by_property, 'accessibilityAPI'),
            'accessibilitySummary' => $this->firstAccessibilityValue($entries_by_property, 'accessibilitySummary'),
            'certification' => [
                'certifiedBy' => $this->firstAccessibilityValue($entries_by_property, 'certifiedBy'),
                'certifierCredential' => $this->firstAccessibilityValue($entries_by_property, 'certifierCredential'),
                'certifierReport' => $this->firstAccessibilityValue($entries_by_property, 'certifierReport'),
                'conformsTo' => $this->accessibilityValues($entries_by_property, 'conformsTo'),
            ],
            'linkedRecords' => $linked_records,
            'diagnostics' => [],
        ];
    }

    private function canonicalAccessibilityProperty(string $property): ?string
    {
        $normalized = strtolower(trim($property));
        if ($normalized === '') {
            return null;
        }

        return [
            'accessmode' => 'accessMode',
            'schema:accessmode' => 'accessMode',
            'accessmodesufficient' => 'accessModeSufficient',
            'schema:accessmodesufficient' => 'accessModeSufficient',
            'accessibilityapi' => 'accessibilityAPI',
            'schema:accessibilityapi' => 'accessibilityAPI',
            'accessibilitycontrol' => 'accessibilityControl',
            'schema:accessibilitycontrol' => 'accessibilityControl',
            'accessibilityfeature' => 'accessibilityFeature',
            'schema:accessibilityfeature' => 'accessibilityFeature',
            'accessibilityhazard' => 'accessibilityHazard',
            'schema:accessibilityhazard' => 'accessibilityHazard',
            'accessibilitysummary' => 'accessibilitySummary',
            'schema:accessibilitysummary' => 'accessibilitySummary',
            'certifiedby' => 'certifiedBy',
            'a11y:certifiedby' => 'certifiedBy',
            'certifiercredential' => 'certifierCredential',
            'a11y:certifiercredential' => 'certifierCredential',
            'certifierreport' => 'certifierReport',
            'a11y:certifierreport' => 'certifierReport',
            'conformsto' => 'conformsTo',
            'dcterms:conformsto' => 'conformsTo',
        ][$normalized] ?? null;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entries_by_property
     * @return list<string>
     */
    private function accessibilityValues(array $entries_by_property, string $property): array
    {
        $values = [];
        foreach ($entries_by_property[$property] ?? [] as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '' && !isset($values[$text])) {
                $values[$text] = $text;
            }
        }

        return array_values($values);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entries_by_property
     */
    private function firstAccessibilityValue(array $entries_by_property, string $property): ?string
    {
        foreach ($entries_by_property[$property] ?? [] as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entries_by_property
     * @return list<array{text: string, modes: list<string>, source: string, id: ?string}>
     */
    private function accessModeSufficientEntries(array $entries_by_property): array
    {
        $entries = [];
        foreach ($entries_by_property['accessModeSufficient'] ?? [] as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $entries[] = [
                'text' => $text,
                'modes' => array_values(array_filter(
                    preg_split('/[\s,]+/', $text) ?: [],
                    static fn (string $mode): bool => $mode !== ''
                )),
                'source' => (string) ($entry['source'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $package_links
     * @return list<array<string, mixed>>
     */
    private function accessibilityLinkedRecords(array $package_links): array
    {
        $records = [];
        foreach ($package_links as $link) {
            $rel = is_array($link['rel'] ?? null) ? array_values($link['rel']) : [];
            $properties = is_array($link['properties'] ?? null) ? array_values($link['properties']) : [];
            if (
                !in_array('accessibility', $rel, true)
                && !in_array('accessibility-summary', $rel, true)
                && !in_array('accessibility-metadata', $properties, true)
                && !in_array('a11y', $properties, true)
            ) {
                continue;
            }

            $records[] = [
                'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                'rel' => $rel,
                'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                'target' => is_string($link['target'] ?? null) ? $link['target'] : null,
                'path' => is_string($link['path'] ?? null) ? $link['path'] : null,
                'external' => ($link['external'] ?? false) === true,
                'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
                'properties' => $properties,
                'manifestId' => is_string($link['manifestId'] ?? null) ? $link['manifestId'] : null,
                'manifestMediaType' => is_string($link['manifestMediaType'] ?? null) ? $link['manifestMediaType'] : null,
            ];
        }

        return $records;
    }

    private function metadataRefinementSubjectId(string $refines): ?string
    {
        $refines = trim($refines);
        if (str_starts_with($refines, '#') && strlen($refines) > 1) {
            return substr($refines, 1);
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array{resources: list<string>, entries: list<array{text: string, href: string, level: int}>, landmarks: list<array{text: string, href: string, level: int, epubTypes: list<string>}>, pageList: list<array{text: string, href: string, level: int}>, auxiliary: list<array{text: string, href: string, level: int, sectionType: string}>, sections: list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}>, sectionTypes: list<string>}
     */
    private function toc(EpubArchive $zip, string $base_path, array $manifest, string $spine_toc_id): array
    {
        $resources = [];
        $nav_entries = [];
        $ncx_entries = [];
        $landmark_entries = [];
        $page_list_entries = [];
        $ncx_page_list_entries = [];
        $auxiliary_entries = [];
        $navigation_sections = [];
        $section_types = [];
        foreach ($manifest as $id => $item) {
            $item_href = (string) ($item['href'] ?? '');
            if ($this->isAbsoluteUrl($item_href)) {
                continue;
            }
            $href = $this->packagePartPath($item_href, $base_path);
            $media_type = strtolower((string) ($item['media-type'] ?? ''));
            $properties = array_map(
                'strtolower',
                is_array($item['properties'] ?? null) ? array_values($item['properties']) : []
            );
            $is_nav = in_array('nav', $properties, true);
            $is_ncx = $id === $spine_toc_id || str_contains($media_type, 'x-dtbncx') || str_ends_with(strtolower($href), '.ncx');
            if (!$is_nav && !$is_ncx) {
                continue;
            }

            $xml = $this->zipEntryContents($zip, $href);
            if (!is_string($xml)) {
                continue;
            }
            $resources[] = $href;
            try {
                if ($is_nav) {
                    $sections = $this->xhtmlNavigationSections($xml, $this->dirname($href), $href);
                    array_push($navigation_sections, ...$sections);
                    foreach ($sections as $section) {
                        foreach ($section['types'] as $type) {
                            $section_types[$type] = true;
                        }
                    }
                    array_push($nav_entries, ...$this->tocEntriesFromNavigationSections($sections));
                    array_push($landmark_entries, ...$this->navigationSectionEntriesByType($sections, 'landmarks'));
                    array_push($page_list_entries, ...$this->navigationSectionEntriesByType($sections, 'page-list'));
                    array_push($auxiliary_entries, ...$this->auxiliaryNavigationSectionEntries($sections));
                }
                if ($is_ncx) {
                    array_push($ncx_entries, ...$this->ncxTocEntries($xml, $this->dirname($href)));
                    array_push($ncx_page_list_entries, ...$this->ncxPageListEntries($xml, $this->dirname($href)));
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        $section_types = array_keys($section_types);
        sort($section_types, SORT_STRING);

        return [
            'resources' => array_values(array_unique($resources)),
            'entries' => $nav_entries !== [] ? $nav_entries : $ncx_entries,
            'landmarks' => $landmark_entries,
            'pageList' => $page_list_entries !== [] ? $page_list_entries : $ncx_page_list_entries,
            'auxiliary' => $auxiliary_entries,
            'sections' => $navigation_sections,
            'sectionTypes' => $section_types,
        ];
    }

    /**
     * @return list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}>
     */
    private function xhtmlNavigationSections(string $xml, string $base_path, string $resource): array
    {
        $dom = $this->loadXml($xml, 'EPUB nav document');
        $sections = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $types = $this->tokenList($this->epubTypeAttribute($element));
            $type = $this->navigationSectionPrimaryType($types);
            $entries = $type === 'landmarks'
                ? $this->xhtmlLandmarkListEntries($element, $base_path, 1)
                : $this->xhtmlNavListEntries($element, $base_path, 1);
            $sections[] = [
                'type' => $type,
                'types' => $types,
                'label' => $this->xhtmlNavigationSectionLabel($element),
                'resource' => $resource,
                'entryCount' => count($entries),
                'entries' => $entries,
            ];
        }

        return $sections;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array{text: string, href: string, level: int}>
     */
    private function tocEntriesFromNavigationSections(array $sections): array
    {
        $tocSections = array_values(array_filter(
            $sections,
            static fn (array $section): bool => in_array('toc', $section['types'], true)
        ));
        $source = $tocSections !== [] ? $tocSections : $sections;
        $entries = [];
        foreach ($source as $section) {
            foreach ($section['entries'] as $entry) {
                $entries[] = [
                    'text' => (string) ($entry['text'] ?? ''),
                    'href' => (string) ($entry['href'] ?? ''),
                    'level' => (int) ($entry['level'] ?? 1),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array<string, mixed>>
     */
    private function navigationSectionEntriesByType(array $sections, string $type): array
    {
        $entries = [];
        foreach ($sections as $section) {
            if (!in_array($type, $section['types'], true)) {
                continue;
            }
            array_push($entries, ...$section['entries']);
        }

        return $entries;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array{text: string, href: string, level: int, sectionType: string}>
     */
    private function auxiliaryNavigationSectionEntries(array $sections): array
    {
        $entries = [];
        foreach ($sections as $section) {
            foreach ($section['types'] as $type) {
                if (in_array($type, ['toc', 'landmarks', 'page-list'], true)) {
                    continue;
                }
                foreach ($section['entries'] as $entry) {
                    $entries[] = [
                        'text' => (string) ($entry['text'] ?? ''),
                        'href' => (string) ($entry['href'] ?? ''),
                        'level' => (int) ($entry['level'] ?? 1),
                        'sectionType' => $type,
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $types
     */
    private function navigationSectionPrimaryType(array $types): string
    {
        foreach (['toc', 'landmarks', 'page-list'] as $primary) {
            if (in_array($primary, $types, true)) {
                return $primary;
            }
        }

        return $types[0] ?? '';
    }

    private function xhtmlNavigationSectionLabel(\DOMElement $nav): string
    {
        foreach ($nav->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isHeadingElementName($child->localName)) {
                continue;
            }

            return trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
        }

        return '';
    }

    private function isHeadingElementName(string $name): bool
    {
        return strlen($name) === 2
            && ($name[0] === 'h' || $name[0] === 'H')
            && $name[1] >= '1'
            && $name[1] <= '6';
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function xhtmlNavListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlNavListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlNavListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int}|null
     */
    private function xhtmlNavListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->childNodes as $element) {
            if (!$element instanceof \DOMElement || !in_array($element->localName, ['a', 'span'], true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                return null;
            }
            $href = $element->localName === 'a'
                ? html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8')
                : '';

            return [
                'text' => $text,
                'href' => $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int, epubTypes: list<string>}>
     */
    private function xhtmlLandmarkListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlLandmarkListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlLandmarkListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int, epubTypes: list<string>}|null
     */
    private function xhtmlLandmarkListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if (!in_array($element->localName, ['a', 'span'], true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                return null;
            }
            $href = $element->localName === 'a'
                ? html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8')
                : '';
            $epub_types = $this->tokenList($this->epubTypeAttribute($element));
            if ($epub_types === []) {
                $epub_types = $this->tokenList($this->epubTypeAttribute($item));
            }

            return [
                'text' => $text,
                'href' => $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
                'epubTypes' => $epub_types,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxTocEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
        $navMap = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'navMap') {
                $navMap = $element;
                break;
            }
        }

        return $navMap instanceof \DOMElement ? $this->ncxNavPointEntries($navMap, $base_path, 1) : [];
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxPageListEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX page list');
        $pageList = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'pageList') {
                $pageList = $element;
                break;
            }
        }

        return $pageList instanceof \DOMElement ? $this->ncxPageTargetEntries($pageList, $base_path, 1) : [];
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxNavPointEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'navPoint') {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($child, 'text')) ?? $this->firstDescendantText($child, 'text'));
            $content = $this->firstDescendantElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entries[] = [
                    'text' => $text,
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                    'level' => $level,
                ];
            }
            array_push($entries, ...$this->ncxNavPointEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxPageTargetEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'pageTarget') {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($child, 'text')) ?? $this->firstDescendantText($child, 'text'));
            $content = $this->firstDescendantElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entries[] = [
                    'text' => $text,
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                    'level' => $level,
                ];
            }
            array_push($entries, ...$this->ncxPageTargetEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    private function contentDocumentMarkup(string $xhtml): string
    {
        return Html5Dom::stripContentDocumentPreamble($xhtml);
    }

    private function contentDocumentMarkupForHtmlReader(
        string $xhtml,
        ?\DOMDocument $dom,
        ?string $html_base_href,
        bool $document_was_xml,
    ): string
    {
        $markup = $this->contentDocumentMarkup($xhtml);
        if (!$dom instanceof \DOMDocument || !$dom->documentElement instanceof \DOMElement) {
            return $markup;
        }

        $document = $dom->cloneNode(true);
        if (!$document instanceof \DOMDocument || !$document->documentElement instanceof \DOMElement) {
            return $markup;
        }

        if ($html_base_href === null && $this->epubContentDocumentHasXmlBase($document)) {
            $this->resolveEpubContentXmlBaseReferences($document->documentElement, null);
        }

        // EPUB XHTML is XML. Re-serialize valid XML before the HTML bridge so
        // `<div/>` stays an empty element instead of opening an HTML `<div>`.
        if ($document_was_xml) {
            $xml_markup = $this->xhtmlMarkupForHtmlReader($document);
            if ($xml_markup !== null) {
                return $xml_markup;
            }
        }

        if ($html_base_href === null && $this->epubContentDocumentHasXmlBase($dom)) {
            return XmlHtmlDom::serializeHtmlNode($document->documentElement);
        }

        return $markup;
    }

    private function xhtmlMarkupForHtmlReader(\DOMDocument $document): ?string
    {
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $markup = $document->saveXML($root);
        if (!is_string($markup)) {
            return null;
        }

        return $this->expandXhtmlSelfClosingNonVoidElements($markup);
    }

    private function expandXhtmlSelfClosingNonVoidElements(string $markup): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($markup);
        $opaqueSegments = [
            ['prefix' => '<!--', 'suffix' => '-->'],
            ['prefix' => '<![CDATA[', 'suffix' => ']]>'],
            ['prefix' => '<?', 'suffix' => '?>'],
        ];

        while (($start = strpos($markup, '<', $offset)) !== false) {
            $result .= substr($markup, $offset, $start - $offset);

            foreach ($opaqueSegments as $opaque) {
                if (substr_compare($markup, $opaque['prefix'], $start, strlen($opaque['prefix'])) !== 0) {
                    continue;
                }

                $end = strpos($markup, $opaque['suffix'], $start + strlen($opaque['prefix']));
                if ($end === false) {
                    return $result . substr($markup, $start);
                }

                $next = $end + strlen($opaque['suffix']);
                $result .= substr($markup, $start, $next - $start);
                $offset = $next;
                continue 2;
            }

            $tag = Html5Dom::rawHtmlOpeningTagAt($markup, $start);
            if ($tag === null) {
                $result .= '<';
                $offset = $start + 1;
                continue;
            }

            if ($tag['selfClosing'] && !isset(self::HTML_VOID_ELEMENTS[$tag['name']])) {
                $result .= substr($tag['source'], 0, -2) . '></' . $tag['name'] . '>';
            } else {
                $result .= $tag['source'];
            }
            $offset = $tag['next'];
        }

        return $result . substr($markup, $offset, $length - $offset);
    }

    private function contentDocumentDom(string $xhtml, bool &$document_was_xml = false): ?\DOMDocument
    {
        try {
            $document_was_xml = true;

            return Html5Dom::parseXmlDocument(
                $this->contentDocumentMarkup($xhtml),
                'EPUB XHTML content document',
            );
        } catch (\Throwable) {
            $document_was_xml = false;
        }

        try {
            return Html5Dom::parseHtmlDocument($this->contentDocumentMarkup($xhtml));
        } catch (\Throwable) {
            return null;
        }
    }

    private function epubContentHtmlReader(bool $source_prevalidated = false): HtmlReader
    {
        return new HtmlReader([
            'htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE,
            'htmlNativeDivs' => true,
            'htmlEpubExtensions' => true,
            'htmlPreserveStyleAttributes' => true,
            'htmlPreserveEmptySpanNodes' => true,
            'htmlImplicitHeadingIds' => false,
            'htmlPlainInlineBlocks' => true,
            'htmlPreserveSoftBreaks' => true,
            'htmlRawHtml' => true,
            'htmlConsumeFootnoteContainers' => false,
            'htmlStripRawInlineWrappers' => false,
            'htmlFlattenDetailsSummaryContainers' => false,
            'htmlSourcePrevalidated' => $source_prevalidated,
            'htmlSkipEmptyMicrodataMetadata' => $source_prevalidated,
            'htmlSkipStandaloneFragmentProbes' => $source_prevalidated,
            'htmlLazyTableGeometry' => $source_prevalidated,
        ]);
    }

    /**
     * @return list<list<AstNode>>
     */
    private function epubFootnoteDefinitionsInReferenceOrder(
        ?\DOMDocument $dom,
        string $content_path,
        bool $source_prevalidated = false
    ): array
    {
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $definitions_by_id = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isEpubFootnoteDefinitionElement($element)) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id === '' || isset($definitions_by_id[$id])) {
                continue;
            }

            $blocks = $this->epubFootnoteDefinitionBlocks($element, $source_prevalidated);
            if ($blocks !== []) {
                $definitions_by_id[$id] = $blocks;
            }
        }

        if ($definitions_by_id === []) {
            return [];
        }

        $definitions = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isEpubNoteReferenceElement($element)) {
                continue;
            }

            $id = $this->epubFootnoteReferenceId($element, $content_path);
            if ($id === null) {
                continue;
            }

            $definition = $definitions_by_id[$id] ?? $definitions_by_id[rawurldecode($id)] ?? null;
            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return array<string, true>
     */
    private function epubNoteReferenceHrefs(?\DOMDocument $dom, ?string $base_href): array
    {
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $hrefs = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isEpubNoteReferenceElement($element)) {
                continue;
            }

            $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href !== '') {
                foreach ($this->epubContentHrefKeys($href, $this->epubContentElementBaseHref($element, $base_href)) as $key) {
                    $hrefs[$key] = true;
                }
            }
        }

        return $hrefs;
    }

    /**
     * @return array<string, list<array{id: string, classes: list<string>, attributes: array<string, string>}>>
     */
    private function epubBodyLinkAttributeOverlaysByHref(?\DOMDocument $dom, ?string $base_href): array
    {
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $overlays_by_href = [];
        $has_attributes = false;
        foreach ($dom->getElementsByTagName('a') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            if ($this->hasEpubFootnoteDefinitionAncestor($link)) {
                continue;
            }

            $href = html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '') {
                continue;
            }

            $overlay = $this->epubBodyLinkAttributeOverlay($link);
            if ($overlay['id'] !== '' || $overlay['classes'] !== [] || $overlay['attributes'] !== []) {
                $has_attributes = true;
            }
            foreach ($this->epubContentHrefKeys($href, $this->epubContentElementBaseHref($link, $base_href)) as $key) {
                $overlays_by_href[$key][] = $overlay;
            }
        }

        return $has_attributes ? $overlays_by_href : [];
    }

    private function epubContentDocumentBaseHref(\DOMDocument $dom): ?string
    {
        foreach ($dom->getElementsByTagName('base') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href !== '') {
                return $href;
            }
        }

        return null;
    }

    private function epubContentDocumentHasXmlBase(\DOMDocument $dom): bool
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->xmlBaseAttribute($element) !== '') {
                return true;
            }
        }

        return false;
    }

    private function xmlBaseAttribute(\DOMElement $element): string
    {
        if ($element->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'base')) {
            return trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                strtolower($attribute->nodeName) === 'xml:base'
                || ($attribute->localName === 'base' && ($attribute->prefix === 'xml' || $attribute->namespaceURI === 'http://www.w3.org/XML/1998/namespace'))
            ) {
                return trim($attribute->value);
            }
        }

        return '';
    }

    private function epubContentElementBaseHref(\DOMElement $element, ?string $html_base_href): ?string
    {
        if ($html_base_href !== null && $html_base_href !== '') {
            return $html_base_href;
        }

        $ancestors = [];
        for ($node = $element; $node instanceof \DOMElement; $node = $node->parentNode) {
            array_unshift($ancestors, $node);
        }

        $base_href = null;
        foreach ($ancestors as $ancestor) {
            $xml_base = $this->xmlBaseAttribute($ancestor);
            if ($xml_base === '') {
                continue;
            }

            $base_href = $this->resolveEpubXmlBaseHref($xml_base, $base_href);
        }

        return $base_href;
    }

    private function resolveEpubXmlBaseHref(string $xml_base, ?string $base_href): string
    {
        $xml_base = trim($xml_base);
        if ($xml_base === '') {
            return $base_href ?? '';
        }

        return HtmlResourceUrlResolver::resolve($xml_base, $base_href) ?? $xml_base;
    }

    private function resolveEpubContentXmlBaseReferences(\DOMElement $element, ?string $base_href): void
    {
        $xml_base = $this->xmlBaseAttribute($element);
        $effective_base_href = $xml_base === '' ? $base_href : $this->resolveEpubXmlBaseHref($xml_base, $base_href);

        if ($effective_base_href !== null && $effective_base_href !== '') {
            foreach ($this->epubContentXmlBaseResourceAttributes($element) as $attribute) {
                $this->resolveEpubContentXmlBaseAttribute($element, $attribute, $effective_base_href);
            }
        }

        $this->removeEpubXmlBaseAttribute($element);

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->resolveEpubContentXmlBaseReferences($child, $effective_base_href);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function epubContentXmlBaseResourceAttributes(\DOMElement $element): array
    {
        return match (strtolower($element->localName)) {
            'a', 'area', 'link' => ['href'],
            'audio', 'embed', 'iframe', 'img', 'script', 'source', 'track' => ['src'],
            'blockquote', 'del', 'ins', 'q' => ['cite'],
            'object' => ['data'],
            'video' => ['poster', 'src'],
            default => [],
        };
    }

    private function resolveEpubContentXmlBaseAttribute(\DOMElement $element, string $attribute, string $base_href): void
    {
        if (!$element->hasAttribute($attribute)) {
            return;
        }

        $value = html_entity_decode(trim($element->getAttribute($attribute)), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($value === '') {
            return;
        }

        $resolved = HtmlResourceUrlResolver::resolve($value, $base_href);
        if ($resolved === null || $resolved === $value) {
            return;
        }

        $element->setAttribute($attribute, $resolved);
    }

    private function removeEpubXmlBaseAttribute(\DOMElement $element): void
    {
        if ($element->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'base')) {
            $element->removeAttributeNS('http://www.w3.org/XML/1998/namespace', 'base');
        }
        if ($element->hasAttribute('xml:base')) {
            $element->removeAttribute('xml:base');
        }
    }

    private function firstHtmlElement(\DOMDocument $dom, string $name): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName($name) as $element) {
            if ($element instanceof \DOMElement && strtolower($element->localName) === strtolower($name)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function epubContentHrefKeys(string $href, ?string $base_href): array
    {
        $keys = [$href];
        $resolved = HtmlResourceUrlResolver::resolve($href, $base_href);
        if ($resolved !== null && $resolved !== '' && !in_array($resolved, $keys, true)) {
            $keys[] = $resolved;
        }

        return $keys;
    }

    /**
     * @return list<array{keys: list<string>, picture: string, sources: list<string>, block: bool}|null>
     */
    private function epubPictureRawHtmlOverlaysInImageOrder(?\DOMDocument $dom, ?string $base_href): array
    {
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $overlays = [];
        $has_picture_overlay = false;
        $seen_pictures = new \SplObjectStorage();
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || strtolower($element->localName) !== 'img') {
                continue;
            }

            $picture = $this->nearestAncestorElementByName($element, 'picture');
            if (!$picture instanceof \DOMElement || $seen_pictures->offsetExists($picture)) {
                $overlays[] = null;
                continue;
            }

            $seen_pictures->offsetSet($picture);
            $keys = [];
            $src = html_entity_decode($element->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($src !== '') {
                $keys = $this->epubContentHrefKeys($src, $this->epubContentElementBaseHref($element, $base_href));
            }

            $sources = [];
            foreach ($picture->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->localName) === 'source') {
                    $sources[] = $this->epubRawHtmlStartTag($child);
                }
            }

            $overlays[] = [
                'keys' => $keys,
                'picture' => $this->epubRawHtmlStartTag($picture),
                'sources' => $sources,
                'block' => !$this->nearestAncestorElementByName($picture, 'p') instanceof \DOMElement,
            ];
            $has_picture_overlay = true;
        }

        return $has_picture_overlay ? $overlays : [];
    }

    private function nearestAncestorElementByName(\DOMElement $element, string $name): ?\DOMElement
    {
        $name = strtolower($name);
        for ($node = $element->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            if (strtolower($node->localName) === $name) {
                return $node;
            }
        }

        return null;
    }

    private function epubRawHtmlStartTag(\DOMElement $element): string
    {
        $html = '<' . strtolower($element->localName);
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || str_starts_with(strtolower($attribute->nodeName), 'xmlns')) {
                continue;
            }

            $html .= ' ' . strtolower($attribute->nodeName) . '="'
                . htmlspecialchars($attribute->value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
                . '"';
        }

        return $html . '>';
    }

    /**
     * @param list<array{keys: list<string>, picture: string, sources: list<string>, block: bool}|null> $picture_overlays
     */
    private function restoreEpubPictureRawHtml(AstNode $node, array $picture_overlays, int &$image_index): AstNode
    {
        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $restored_block_nodes = $this->restoreEpubPictureRawHtmlWholeBlock($child, $picture_overlays, $image_index);
            if ($restored_block_nodes !== null) {
                array_push($children, ...$restored_block_nodes);
                if (count($restored_block_nodes) !== 1 || $restored_block_nodes[0] !== $child) {
                    $changed = true;
                }
                continue;
            }

            if ($child->type === 'image') {
                $overlay = $picture_overlays[$image_index] ?? null;
                ++$image_index;
                if (is_array($overlay) && $this->epubPictureOverlayMatchesImage($overlay, (string) $child->attr('url', ''))) {
                    array_push($children, ...$this->epubPictureRawHtmlInlineOpenNodes($overlay));
                    $children[] = $child;
                    $children[] = new AstNode('raw_html_inline', ['html' => '</picture>']);
                    $changed = true;
                    continue;
                }
            }

            $updated = $this->restoreEpubPictureRawHtml($child, $picture_overlays, $image_index);
            $children[] = $updated;
            if ($updated !== $child) {
                $changed = true;
            }
        }

        return $changed ? $this->epubNodeFrom($node, $node->baseAttrs(), $children) : $node;
    }

    /**
     * @param list<array{keys: list<string>, picture: string, sources: list<string>, block: bool}|null> $picture_overlays
     * @return list<AstNode>|null
     */
    private function restoreEpubPictureRawHtmlWholeBlock(AstNode $node, array $picture_overlays, int &$image_index): ?array
    {
        if (!in_array($node->type, ['paragraph', 'plain'], true) || count($node->children) !== 1) {
            return null;
        }

        $image = $node->children[0];
        if (!$image instanceof AstNode || $image->type !== 'image') {
            return null;
        }

        $overlay = $picture_overlays[$image_index] ?? null;
        if (!is_array($overlay) || !$this->epubPictureOverlayMatchesImage($overlay, (string) $image->attr('url', ''))) {
            return null;
        }

        ++$image_index;
        if (($overlay['block'] ?? false) === true && $overlay['sources'] !== []) {
            $blocks = [
                $this->epubNodeFrom($node, $node->baseAttrs(), [
                    new AstNode('raw_html_inline', ['html' => $overlay['picture']]),
                ]),
            ];
            foreach ($overlay['sources'] as $source) {
                $blocks[] = new AstNode('raw_html', ['html' => $source]);
                $blocks[] = new AstNode('raw_html', ['html' => '</source>']);
            }
            $blocks[] = $this->epubNodeFrom($node, $node->baseAttrs(), [
                $image,
                new AstNode('raw_html_inline', ['html' => '</picture>']),
            ]);

            return $blocks;
        }

        return [
            $this->epubNodeFrom(
                $node,
                $node->baseAttrs(),
                [
                    ...$this->epubPictureRawHtmlInlineOpenNodes($overlay),
                    $image,
                    new AstNode('raw_html_inline', ['html' => '</picture>']),
                ]
            ),
        ];
    }

    /**
     * @param array{keys: list<string>, picture: string, sources: list<string>, block: bool} $overlay
     */
    private function epubPictureOverlayMatchesImage(array $overlay, string $url): bool
    {
        $keys = $overlay['keys'];

        return $keys === [] ? $url === '' : in_array($url, $keys, true);
    }

    /**
     * @param array{keys: list<string>, picture: string, sources: list<string>, block: bool} $overlay
     * @return list<AstNode>
     */
    private function epubPictureRawHtmlInlineOpenNodes(array $overlay): array
    {
        $nodes = [new AstNode('raw_html_inline', ['html' => $overlay['picture']])];
        foreach ($overlay['sources'] as $source) {
            $nodes[] = new AstNode('raw_html_inline', ['html' => $source]);
            $nodes[] = new AstNode('raw_html_inline', ['html' => '</source>']);
        }

        return $nodes;
    }

    /**
     * @return array{id: string, classes: list<string>, attributes: array<string, string>}
     */
    private function epubBodyLinkAttributeOverlay(\DOMElement $link): array
    {
        $id = '';
        $classes = [];
        $attributes = [];
        foreach ($link->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->nodeName);
            if (str_starts_with($name, 'xmlns')) {
                continue;
            }

            $value = trim($attribute->value);
            if ($name === 'href' || $name === 'title') {
                continue;
            }
            if ($name === 'id') {
                $id = $value;
                continue;
            }
            if ($name === 'class') {
                foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                    if (!in_array($class, $classes, true)) {
                        $classes[] = $class;
                    }
                }
                continue;
            }
            if ($this->isEpubTypeAttribute($attribute)) {
                foreach ($this->tokenList($value) as $epub_type) {
                    if (!in_array($epub_type, $classes, true)) {
                        $classes[] = $epub_type;
                    }
                }
                continue;
            }

            $key = $this->epubPandocHtmlAttributeName($name);
            if ($key !== '') {
                $attributes[$key] = $value;
            }
        }

        return [
            'id' => $id,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    private function epubPandocHtmlAttributeName(string $name): string
    {
        if (!str_starts_with($name, 'data-')) {
            return $name;
        }

        $data_name = substr($name, 5);
        if ($data_name === '') {
            return '';
        }

        return in_array($data_name, ['class', 'href', 'id', 'kind', 'rel', 'role', 'style', 'target', 'title', 'type'], true)
            ? $name
            : $data_name;
    }

    private function isEpubFootnoteDefinitionElement(\DOMElement $element): bool
    {
        if (trim($element->getAttribute('id')) === '') {
            return false;
        }

        $types = $this->tokenList($this->epubTypeAttribute($element));

        return in_array('footnote', $types, true) || in_array('rearnote', $types, true);
    }

    private function isEpubNoteReferenceElement(\DOMElement $element): bool
    {
        if (strtolower(trim($element->getAttribute('role'))) === 'doc-noteref') {
            return true;
        }

        return in_array('noteref', $this->tokenList($this->epubTypeAttribute($element)), true);
    }

    private function hasEpubFootnoteDefinitionAncestor(\DOMElement $element): bool
    {
        for ($node = $element; $node instanceof \DOMElement; $node = $node->parentNode) {
            if ($this->isEpubFootnoteDefinitionElement($node)) {
                return true;
            }
        }

        return false;
    }

    private function epubFootnoteReferenceId(\DOMElement $element, string $content_path): ?string
    {
        $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href === '' || $this->isAbsoluteUrl($href)) {
            return null;
        }

        [$path, , $fragment] = $this->splitUrlSuffix($href);
        if ($path !== '' && !$this->epubFootnoteReferenceTargetsContentDocument($path, $content_path)) {
            return null;
        }

        return $fragment === null || $fragment === '' ? null : $fragment;
    }

    private function epubFootnoteReferenceTargetsContentDocument(string $path, string $content_path): bool
    {
        $path = $this->decodePackagePathPercentEscapes($path);
        $content_part = $this->decodePackagePathPercentEscapes($this->stripUrlQueryAndFragment($content_path));
        $content_dir = $this->dirname($content_part);

        return $this->normalizeZipPath($content_dir . '/' . $path) === $this->normalizeZipPath($content_part);
    }

    /**
     * @return list<AstNode>
     */
    private function epubFootnoteDefinitionBlocks(\DOMElement $definition, bool $source_prevalidated = false): array
    {
        $clone = $definition->cloneNode(true);
        if (!$clone instanceof \DOMElement) {
            return [];
        }

        $link_attribute_overlays = $this->epubFootnoteLinkAttributeOverlays($clone);
        $body = $this->serializeEpubFootnoteDefinitionChildren($clone);
        if (trim($body) === '') {
            return [];
        }

        $wrapped = '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body>'
            . $body
            . '</body></html>';

        $blocks = $this->epubContentHtmlReader($source_prevalidated)->read($wrapped)->children;
        if ($link_attribute_overlays === []) {
            return $blocks;
        }

        $link_index = 0;
        $restored_blocks = [];
        foreach ($blocks as $block) {
            $restored_blocks[] = $this->restoreEpubFootnoteLinkAttributes($block, $link_attribute_overlays, $link_index);
        }

        return $restored_blocks;
    }

    private function serializeEpubFootnoteDefinitionChildren(\DOMElement $definition): string
    {
        $document = $definition->ownerDocument;
        if (
            $document instanceof \DOMDocument
            && $document->documentElement instanceof \DOMElement
            && $document->documentElement->namespaceURI === 'http://www.w3.org/1999/xhtml'
        ) {
            $markup = '';
            foreach ($definition->childNodes as $child) {
                $serialized = $document->saveXML($child);
                if (is_string($serialized)) {
                    $markup .= $serialized;
                }
            }

            return $markup;
        }

        return Html5Dom::serializeHtmlChildren($definition);
    }

    /**
     * @return list<array{id: string, classes: list<string>, attributes: array<string, string>}>
     */
    private function epubFootnoteLinkAttributeOverlays(\DOMElement $root): array
    {
        $overlays = [];
        foreach ($root->getElementsByTagName('a') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $overlays[] = $this->epubBodyLinkAttributeOverlay($link);
        }

        return $overlays;
    }

    /**
     * @param list<array{id: string, classes: list<string>, attributes: array<string, string>}> $link_attribute_overlays
     */
    private function restoreEpubFootnoteLinkAttributes(AstNode $node, array $link_attribute_overlays, int &$link_index): AstNode
    {
        $base_attrs = $node->baseAttrs();
        $attrs = $base_attrs;
        if ($node->type === 'link') {
            $has_overlay = array_key_exists($link_index, $link_attribute_overlays);
            $overlay = $has_overlay ? $link_attribute_overlays[$link_index] : ['id' => '', 'classes' => [], 'attributes' => []];
            ++$link_index;
            if ($has_overlay) {
                $attrs[self::EPUB_FOOTNOTE_DEFINITION_LINK_ATTR] = true;
            }

            $id = trim((string) ($overlay['id'] ?? ''));
            if ($id !== '' && trim((string) ($attrs['id'] ?? '')) === '') {
                $attrs['id'] = $id;
            }

            $classes = isset($attrs['classes']) && is_array($attrs['classes'])
                ? array_values(array_map('strval', $attrs['classes']))
                : [];
            $overlay_classes = isset($overlay['classes']) && is_array($overlay['classes']) ? $overlay['classes'] : [];
            foreach ($overlay_classes as $class) {
                $class = trim((string) $class);
                if ($class !== '' && !in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }
            if ($classes !== []) {
                $attrs['classes'] = $classes;
            }

            if ($overlay['attributes'] !== []) {
                $attributes = isset($attrs['attributes']) && is_array($attrs['attributes'])
                    ? $attrs['attributes']
                    : [];
                foreach ($overlay['attributes'] as $name => $value) {
                    $attributes[$name] ??= $value;
                }
                $attrs['attributes'] = $attributes;
            }
        }

        $children = [];
        $changed = $attrs !== $base_attrs;
        foreach ($node->children as $child) {
            $updated = $this->restoreEpubFootnoteLinkAttributes($child, $link_attribute_overlays, $link_index);
            $children[] = $updated;
            if ($updated !== $child) {
                $changed = true;
            }
        }

        return $changed ? $this->epubNodeFrom($node, $attrs, $children) : $node;
    }

    /**
     * @param list<list<AstNode>> $definitions
     */
    private function fillEmptyEpubFootnoteNotes(AstNode $node, array $definitions, int &$index): AstNode
    {
        if ($node->type === 'note' && isset($definitions[$index])) {
            $children = $node->children === [] ? $definitions[$index] : $node->children;
            ++$index;

            return $this->epubNodeFrom($node, $node->baseAttrs(), $children);
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $updated = $this->fillEmptyEpubFootnoteNotes($child, $definitions, $index);
            $children[] = $updated;
            if ($updated !== $child) {
                $changed = true;
            }
        }

        return $changed ? $this->epubNodeFrom($node, $node->baseAttrs(), $children) : $node;
    }

    private function normalizeEpubMediaRawBlocks(AstNode $node): AstNode
    {
        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $normalized = $this->normalizeEpubMediaRawBlockNode($child);
            array_push($children, ...$normalized);
            if (count($normalized) !== 1 || $normalized[0] !== $child) {
                $changed = true;
            }
        }

        return $changed ? $this->epubNodeFrom($node, $node->baseAttrs(), $children) : $node;
    }

    private function normalizeEpubRawInlineVoidElements(AstNode $node): AstNode
    {
        $children = [];
        $changed = false;
        foreach ($node->children as $index => $child) {
            $normalized = $this->normalizeEpubRawInlineVoidElements($child);
            $children[] = $normalized;
            if ($normalized !== $child) {
                $changed = true;
            }

            if (
                $this->epubRawInlineNeedsExplicitClose($normalized, 'wbr')
                && !$this->nextAstNodeIsClosingRawHtml($node->children, $index, 'wbr')
            ) {
                $children[] = new AstNode('raw_html_inline', ['html' => '</wbr>']);
                $changed = true;
            }
        }

        return $changed ? $this->epubNodeFrom($node, $node->baseAttrs(), $children) : $node;
    }

    private function epubRawInlineNeedsExplicitClose(AstNode $node, string $tag): bool
    {
        if ($node->type !== 'raw_html_inline') {
            return false;
        }

        $html = trim((string) $node->attr('html', $node->attr('text', '')));

        return $this->isEpubOpeningRawHtmlTag($html, $tag)
            && !$this->rawHtmlTagClosesElement($html, $tag);
    }

    /**
     * @return list<AstNode>
     */
    private function normalizeEpubMediaRawBlockNode(AstNode $node): array
    {
        $raw_blocks = $this->epubMediaRawInlineBlocks($node);
        if ($raw_blocks !== null) {
            return $raw_blocks;
        }

        return [$this->normalizeEpubMediaRawBlocks($node)];
    }

    /**
     * @return list<AstNode>|null
     */
    private function epubMediaRawInlineBlocks(AstNode $node): ?array
    {
        if (!in_array($node->type, ['paragraph', 'plain'], true) || $node->children === []) {
            return null;
        }

        $html = [];
        $has_media_tag = false;
        foreach ($node->children as $child) {
            if ($child->type !== 'raw_html_inline') {
                return null;
            }

            $raw = trim((string) $child->attr('html', $child->attr('text', '')));
            if ($raw === '') {
                return null;
            }
            if ($this->isEpubMediaRawHtmlTag($raw)) {
                $has_media_tag = true;
            }
            $html[] = $raw;
        }

        if (!$has_media_tag) {
            return null;
        }

        $blocks = [];
        foreach ($html as $index => $raw) {
            $blocks[] = new AstNode('raw_html', ['html' => $raw]);
            if (
                $this->epubMediaRawHtmlTagNeedsExplicitClose($raw, ['source', 'track'])
                && !$this->nextRawHtmlClosesSameMediaTag($html, $index, ['source', 'track'])
            ) {
                $blocks[] = new AstNode('raw_html', ['html' => '</' . $this->epubOpeningRawHtmlTagName($raw) . '>']);
            }
        }

        return $blocks;
    }

    /**
     * @param list<string> $tags
     */
    private function epubMediaRawHtmlTagNeedsExplicitClose(string $html, array $tags): bool
    {
        $tag = $this->epubOpeningRawHtmlTagName($html);

        return $tag !== null
            && in_array($tag, $tags, true)
            && !$this->rawHtmlTagClosesElement($html, $tag);
    }

    private function epubOpeningRawHtmlTagName(string $html): ?string
    {
        $opening = Html5Dom::rawHtmlOpeningTagAt($html);

        return $opening === null ? null : $opening['name'];
    }

    private function isEpubMediaRawHtmlTag(string $html): bool
    {
        foreach (['audio', 'source', 'track', 'video'] as $tag) {
            if ($this->isEpubOpeningRawHtmlTag($html, $tag) || $this->isEpubClosingRawHtmlTag($html, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function epubRawHtmlMediaResourceUrls(string $html): array
    {
        $opening = Html5Dom::rawHtmlOpeningTagAt($html);
        if ($opening === null || !in_array($opening['name'], ['audio', 'source', 'track', 'video'], true)) {
            return [];
        }

        $element = $this->rawHtmlOpeningElement($opening['source'], $opening['name']);
        if (!$element instanceof \DOMElement) {
            return [];
        }

        $names = $opening['name'] === 'video' ? ['src', 'poster'] : ['src'];
        $urls = [];
        foreach ($names as $name) {
            $url = $element->hasAttribute($name) ? trim($element->getAttribute($name)) : '';
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param list<string> $referenced_resources
     * @param list<string> $media_bag_resources
     * @param array<string, string> $media_bag_sources
     */
    private function recordEpubContentRawMediaResources(
        ?\DOMDocument $content_dom,
        string $xhtml,
        string $content_dir,
        string $package_base_path,
        array &$referenced_resources,
        array &$media_bag_resources,
        array &$media_bag_sources
    ): void {
        $dom = $content_dom;
        if (!$dom instanceof \DOMDocument) {
            if (!$this->epubMarkupMayContainRawMediaElements($xhtml)) {
                return;
            }
            try {
                $dom = Html5Dom::parseHtmlDocument($this->contentDocumentMarkup($xhtml));
            } catch (\Throwable) {
                return;
            }
        }
        if (!$dom instanceof \DOMDocument) {
            return;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $names = match (strtolower($element->localName)) {
                'audio', 'source', 'track' => ['src'],
                'video' => ['src', 'poster'],
                default => [],
            };
            foreach ($names as $name) {
                $url = $element->hasAttribute($name) ? trim($element->getAttribute($name)) : '';
                if ($url === '') {
                    continue;
                }

                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $resource = $this->recordMediaBagResource($url, $content_dir, $package_base_path, $media_bag_resources);
                if ($resource !== null) {
                    $this->recordMediaBagSource($this->fixEpubImageUrl($url, $content_dir), $resource, $media_bag_sources);
                }
            }
        }
    }

    private function epubMarkupMayContainRawMediaElements(string $markup): bool
    {
        foreach (['<audio', '<source', '<track', '<video'] as $needle) {
            if (stripos($markup, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function rawHtmlOpeningElement(string $source, string $name): ?\DOMElement
    {
        try {
            $body = Html5Dom::parseHtmlFragment($source);
        } catch (\Throwable) {
            return null;
        }

        foreach ($body->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && strtolower($element->localName) === $name) {
                return $element;
            }
        }

        return null;
    }

    private function isEpubOpeningRawHtmlTag(string $html, string $tag): bool
    {
        $opening = Html5Dom::rawHtmlOpeningTagAt($html);

        return $opening !== null && $opening['name'] === strtolower($tag);
    }

    private function isEpubClosingRawHtmlTag(string $html, string $tag): bool
    {
        $closing = Html5Dom::rawHtmlClosingTagAt($html);

        return $closing !== null && $closing['name'] === strtolower($tag);
    }

    private function rawHtmlTagClosesElement(string $html, string $tag): bool
    {
        return Html5Dom::rawHtmlSourceContainsClosingTag($html, $tag);
    }

    /**
     * @param list<string> $html
     */
    private function nextRawHtmlClosesSameMediaTag(array $html, int $index, array $tags): bool
    {
        $tag = $this->epubOpeningRawHtmlTagName($html[$index] ?? '');
        if ($tag === null || !in_array($tag, $tags, true)) {
            return false;
        }

        $next = $html[$index + 1] ?? null;

        return is_string($next) && $this->isEpubClosingRawHtmlTag($next, $tag);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function nextAstNodeIsClosingRawHtml(array $nodes, int $index, string $tag): bool
    {
        $next = $nodes[$index + 1] ?? null;
        if (!$next instanceof AstNode || $next->type !== 'raw_html_inline') {
            return false;
        }

        $html = trim((string) $next->attr('html', $next->attr('text', '')));

        return $this->isEpubClosingRawHtmlTag($html, $tag);
    }

    private function spineMarker(string $filename): AstNode
    {
        return new AstNode('paragraph', ['text' => ''], [
            new AstNode('span', ['id' => $filename], []),
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     */
    private function coverImageHref(\DOMElement $package, array $manifest): ?string
    {
        $cover_id = '';
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'meta') {
                continue;
            }
            if (strtolower(trim($element->getAttribute('name'))) === 'cover') {
                $cover_id = trim($element->getAttribute('content'));
                break;
            }
        }

        foreach ($manifest as $id => $item) {
            $properties = array_map(
                'strtolower',
                is_array($item['properties'] ?? null) ? array_values($item['properties']) : []
            );
            if ($id === $cover_id || in_array('cover-image', $properties, true)) {
                return (string) ($item['href'] ?? '');
            }
        }

        return $cover_id === '' ? null : $cover_id;
    }

    /**
     * @param list<string> $spine_filenames
     * @param array<string, true> $note_reference_hrefs
     * @param array<string, list<array{id: string, classes: list<string>, attributes: array<string, string>}>> $link_attribute_overlays_by_href
     * @param list<string> $referenced_resources
     * @param list<string> $media_bag_resources
     */
    private function fixEpubContentReferences(
        AstNode $document,
        string $content_path,
        string $package_base_path,
        array $spine_filenames,
        array $note_reference_hrefs,
        array $link_attribute_overlays_by_href,
        array &$referenced_resources,
        array &$media_bag_resources,
        array &$media_bag_sources
    ): AstNode {
        $content_part = $this->stripUrlQueryAndFragment($content_path);
        $filename = $this->spineFilename($content_part);
        $content_dir = $this->dirname($content_part);

        return $this->fixEpubNode($document, $filename, $content_dir, $package_base_path, $spine_filenames, $note_reference_hrefs, $link_attribute_overlays_by_href, $referenced_resources, $media_bag_resources, $media_bag_sources);
    }

    /**
     * @param list<string> $spine_filenames
     * @param array<string, true> $note_reference_hrefs
     * @param array<string, list<array{id: string, classes: list<string>, attributes: array<string, string>}>> $link_attribute_overlays_by_href
     * @param list<string> $referenced_resources
     * @param list<string> $media_bag_resources
     * @param array<string, string> $media_bag_sources
     */
    private function fixEpubNode(
        AstNode $node,
        string $filename,
        string $content_dir,
        string $package_base_path,
        array $spine_filenames,
        array $note_reference_hrefs,
        array &$link_attribute_overlays_by_href,
        array &$referenced_resources,
        array &$media_bag_resources,
        array &$media_bag_sources
    ): AstNode {
        $base_attrs = $node->baseAttrs();
        $is_footnote_definition_link = $node->type === 'link'
            && ($base_attrs[self::EPUB_FOOTNOTE_DEFINITION_LINK_ATTR] ?? false) === true;
        $attrs = in_array($node->type, ['blockquote', 'definition_list'], true)
            ? []
            : $this->fixEpubNodeAttrs($base_attrs, $filename, $this->shouldPrefixEpubNodeId($node->type));
        if ($node->type === 'list_item') {
            unset($attrs['text']);
        }
        if ($node->type === 'link') {
            $url = (string) ($attrs['url'] ?? '');
            if ($url !== '') {
                if (!$is_footnote_definition_link) {
                    $attrs = $this->restoreEpubBodyLinkAttributes($attrs, $url, $filename, $link_attribute_overlays_by_href);
                }
                if (isset($note_reference_hrefs[$url])) {
                    $attrs = $this->attrsWithClass($attrs, 'noteref');
                }
                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $attrs['url'] = $this->fixEpubLinkUrl($url, $filename, $spine_filenames);
            }
        } elseif ($node->type === 'image') {
            $url = (string) ($attrs['url'] ?? '');
            if ($url !== '') {
                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $resource = $this->recordMediaBagResource($url, $content_dir, $package_base_path, $media_bag_resources);
                $attrs['url'] = $this->fixEpubImageUrl($url, $content_dir);
                if ($resource !== null) {
                    $this->recordMediaBagSource($this->mediaBagSourceUrl($attrs['url']), $resource, $media_bag_sources);
                }
            }
        } elseif ($node->type === 'raw_html') {
            foreach ($this->epubRawHtmlMediaResourceUrls((string) ($attrs['html'] ?? '')) as $url) {
                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $resource = $this->recordMediaBagResource($url, $content_dir, $package_base_path, $media_bag_resources);
                if ($resource !== null) {
                    $this->recordMediaBagSource($this->fixEpubImageUrl($url, $content_dir), $resource, $media_bag_sources);
                }
            }
        }

        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->fixEpubNode($child, $filename, $content_dir, $package_base_path, $spine_filenames, $note_reference_hrefs, $link_attribute_overlays_by_href, $referenced_resources, $media_bag_resources, $media_bag_sources);
        }
        $children = $this->trimTextBeforeInlineImages($children);

        return $this->epubNodeFrom($node, $attrs, $children);
    }

    /**
     * EPUB normalizers rebuild only the nodes whose content or attributes
     * changed. Preserve AstNode's compact direct-text representation when
     * doing so; constructing a plain node here otherwise turns every text
     * child back into a standalone PHP object.
     *
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    private function epubNode(string $type, array $attrs, array $children = []): AstNode
    {
        if ($children === []) {
            return new AstNode($type, $attrs);
        }

        return AstNode::withCompactTextChildren($type, $attrs, $children);
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    private function epubNodeFrom(AstNode $source, array $attrs, array $children = []): AstNode
    {
        $resolver = $source->attributeResolver();
        if ($resolver instanceof LazyTableGeometryAttributes) {
            return new AstNode($source->type, $attrs, $children, $resolver->forRebuiltNode());
        }

        return $this->epubNode($source->type, $attrs, $children);
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<string, list<array{id: string, classes: list<string>, attributes: array<string, string>}>> $link_attribute_overlays_by_href
     * @return array<string, mixed>
     */
    private function restoreEpubBodyLinkAttributes(array $attrs, string $url, string $filename, array &$link_attribute_overlays_by_href): array
    {
        if (!isset($link_attribute_overlays_by_href[$url]) || $link_attribute_overlays_by_href[$url] === []) {
            return $attrs;
        }

        $queue = $link_attribute_overlays_by_href[$url];
        $overlay = array_shift($queue);
        if ($queue === []) {
            unset($link_attribute_overlays_by_href[$url]);
        } else {
            $link_attribute_overlays_by_href[$url] = $queue;
        }
        if (!is_array($overlay)) {
            return $attrs;
        }

        $id = isset($overlay['id']) && is_string($overlay['id']) ? trim($overlay['id']) : '';
        if ($id !== '' && trim((string) ($attrs['id'] ?? '')) === '') {
            $attrs['id'] = $this->prefixedEpubId($filename, $id);
        }

        $classes = isset($attrs['classes']) && is_array($attrs['classes'])
            ? array_values(array_map('strval', $attrs['classes']))
            : [];
        $overlay_classes = isset($overlay['classes']) && is_array($overlay['classes']) ? $overlay['classes'] : [];
        foreach ($overlay_classes as $class) {
            $class = trim((string) $class);
            if ($class !== '' && !in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }

        $overlay_attributes = isset($overlay['attributes']) && is_array($overlay['attributes'])
            ? $overlay['attributes']
            : [];
        if ($overlay_attributes === []) {
            return $attrs;
        }

        $attributes = isset($attrs['attributes']) && is_array($attrs['attributes'])
            ? $attrs['attributes']
            : [];
        foreach ($overlay_attributes as $name => $value) {
            $attributes[$name] ??= $value;
        }
        $attrs['attributes'] = $attributes;

        return $attrs;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function attrsWithClass(array $attrs, string $class): array
    {
        $classes = [];
        if (isset($attrs['classes']) && is_array($attrs['classes'])) {
            foreach ($attrs['classes'] as $existing) {
                $existing = trim((string) $existing);
                if ($existing !== '' && !in_array($existing, $classes, true)) {
                    $classes[] = $existing;
                }
            }
        }
        if (!in_array($class, $classes, true)) {
            $classes[] = $class;
        }
        $attrs['classes'] = $classes;

        return $attrs;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function trimTextBeforeInlineImages(array $children): array
    {
        foreach ($children as $index => $child) {
            if ($child->type !== 'image' || $index === 0) {
                continue;
            }

            $previous = $children[$index - 1] ?? null;
            if (!$previous instanceof AstNode || $previous->type !== 'text') {
                continue;
            }

            $text = rtrim((string) $previous->attr('text', ''));
            if ($text === '') {
                unset($children[$index - 1]);
                continue;
            }
            if ($text !== $previous->attr('text', '')) {
                $children[$index - 1] = new AstNode('text', array_merge($previous->attrs, ['text' => $text]), $previous->children);
            }
        }

        return array_values($children);
    }

    private function normalizeEpubChapterSectioningContent(AstNode $node): AstNode
    {
        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $updated = $this->normalizeEpubChapterSectioningContent($child);
            if ($this->isEpubChapterSectioningDiv($child)) {
                array_push($children, ...$updated->children);
                $changed = true;
                continue;
            }

            $children[] = $updated;
            if ($updated !== $child) {
                $changed = true;
            }
        }

        $attrs = $node->baseAttrs();
        if (array_key_exists(self::EPUB_SEMANTIC_TYPES_ATTR, $attrs)) {
            unset($attrs[self::EPUB_SEMANTIC_TYPES_ATTR]);
            $changed = true;
        }

        return $changed ? $this->epubNodeFrom($node, $attrs, $children) : $node;
    }

    /**
     * The EPUB reader performs all reference and raw-markup repairs before
     * this pass. Paragraph-like blocks can therefore retain their completed
     * inline subtree as a lazy payload, keeping long prose books from holding
     * one PHP object per word and soft break.
     */
    private function compactEpubInlineChildren(AstNode $node): AstNode
    {
        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $updated = $this->compactEpubInlineChildren($child);
            $children[] = $updated;
            if ($updated !== $child) {
                $changed = true;
            }
        }

        $attrs = $node->baseAttrs();
        if ($this->canSerializeEpubInlineChildren($node, $children)) {
            return AstNode::withSerializedChildren($node->type, $attrs, $children);
        }

        return $changed ? $this->epubNode($node->type, $attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $children
     */
    private function canSerializeEpubInlineChildren(AstNode $node, array $children): bool
    {
        if (count($children) < 2) {
            return false;
        }

        return in_array($node->type, ['paragraph', 'plain', 'heading'], true);
    }

    private function isEpubChapterSectioningDiv(AstNode $node): bool
    {
        if ($node->type !== 'div') {
            return false;
        }

        $classes = isset($node->attrs['classes']) && is_array($node->attrs['classes'])
            ? array_values(array_map('strval', $node->attrs['classes']))
            : [];
        $is_sectioning = in_array('section', $classes, true) || in_array('aside', $classes, true);
        if (!$is_sectioning) {
            return false;
        }

        $semantic_types = isset($node->attrs[self::EPUB_SEMANTIC_TYPES_ATTR]) && is_array($node->attrs[self::EPUB_SEMANTIC_TYPES_ATTR])
            ? array_values(array_map('strval', $node->attrs[self::EPUB_SEMANTIC_TYPES_ATTR]))
            : [];
        $attributes = isset($node->attrs['attributes']) && is_array($node->attrs['attributes'])
            ? $node->attrs['attributes']
            : [];
        if (isset($attributes['type']) && is_scalar($attributes['type'])) {
            $semantic_types[] = strtolower((string) $attributes['type']);
        }

        foreach ($semantic_types as $semantic_type) {
            if (str_contains(strtolower($semantic_type), 'chapter')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixEpubNodeAttrs(array $attrs, string $filename, bool $prefix_id): array
    {
        unset($attrs['htmlAttributes']);
        unset($attrs[self::EPUB_FOOTNOTE_DEFINITION_LINK_ATTR]);

        $attributes = [];
        $epub_semantic_types = [];
        if (isset($attrs['attributes']) && is_array($attrs['attributes'])) {
            foreach ($attrs['attributes'] as $name => $value) {
                $name = (string) $name;
                if (str_starts_with($name, 'epub:')) {
                    $epub_types = $this->tokenList((string) $value);
                    if ($name === 'epub:type') {
                        $epub_semantic_types = array_values(array_unique([...$epub_semantic_types, ...$epub_types]));
                    }
                    foreach ($epub_types as $epub_type) {
                        $attrs['classes'][] = $epub_type;
                    }
                    continue;
                }
                if ($name === 'xml:lang') {
                    $name = 'lang';
                }
                $attributes[$name] = $value;
            }
        }
        if ($attributes === []) {
            unset($attrs['attributes']);
        } else {
            $attrs['attributes'] = $attributes;
        }
        if ($epub_semantic_types !== []) {
            $attrs[self::EPUB_SEMANTIC_TYPES_ATTR] = $epub_semantic_types;
        }

        if ($prefix_id && isset($attrs['id']) && is_string($attrs['id']) && $attrs['id'] !== '') {
            $attrs['id'] = $this->prefixedEpubId($filename, $attrs['id']);
        }

        if (isset($attrs['classes']) && is_array($attrs['classes'])) {
            $classes = [];
            foreach ($attrs['classes'] as $class) {
                $class = trim((string) $class);
                if ($class !== '' && !in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }
            if ($classes === []) {
                unset($attrs['classes']);
            } else {
                $attrs['classes'] = $classes;
            }
        }

        if (isset($attrs['html']) && is_string($attrs['html']) && $attrs['html'] !== '') {
            $attrs['format'] ??= 'html';
            $attrs['text'] ??= $attrs['html'];
        }
        if (array_key_exists('loose', $attrs)) {
            unset($attrs['loose']);
        }

        return $attrs;
    }

    private function shouldPrefixEpubNodeId(string $type): bool
    {
        return in_array($type, ['code', 'code_block', 'div', 'heading', 'link', 'span'], true);
    }

    /**
     * @param list<string> $spine_filenames
     */
    private function fixEpubLinkUrl(string $url, string $filename, array $spine_filenames): string
    {
        if (
            $this->isAbsoluteUrl($url)
            || str_starts_with(strtolower($url), 'data:')
            || str_starts_with(strtolower($url), 'mailto:')
        ) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        if ($query !== null && $path !== '') {
            $target = $this->spineFilename($path);
            if (in_array($target, $spine_filenames, true)) {
                return '#' . $this->appendUrlSuffix($target, $query, $fragment);
            }
        }
        if ($fragment !== null && $fragment !== '') {
            $target = $path === '' ? $filename : $this->spineFilename($path);
            $url = $this->prefixedEpubId($target, $fragment);
        }

        foreach ($spine_filenames as $spine_filename) {
            if ($spine_filename !== '' && str_starts_with($url, $spine_filename)) {
                return '#' . $url;
            }
        }

        return $url;
    }

    private function fixEpubImageUrl(string $url, string $content_dir): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        $normalized = $this->normalizeZipPath($content_dir . '/' . $path);

        return $this->appendUrlSuffix($normalized, $query, $fragment);
    }

    private function directImageSpineBlock(string $href): AstNode
    {
        return new AstNode('paragraph', ['text' => ''], [
            new AstNode('image', [
                'url' => $this->normalizeImageHrefPreservingSuffix($href),
                'title' => '',
                'alt' => '',
            ]),
        ]);
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function recordReferencedResource(string $url, string $content_dir, string $package_base_path, array &$referenced_resources): void
    {
        $this->recordPackageRelativeResource($url, $content_dir, $package_base_path, $referenced_resources, true);
    }

    /**
     * @param list<string> $media_bag_resources
     */
    private function recordMediaBagResource(string $url, string $content_dir, string $package_base_path, array &$media_bag_resources): ?string
    {
        return $this->recordPackageRelativeResource($url, $content_dir, $package_base_path, $media_bag_resources, false);
    }

    /**
     * @param array<string, string> $media_bag_sources
     */
    private function recordMediaBagSource(string $source, string $resource, array &$media_bag_sources): void
    {
        $source = $this->mediaBagSourceUrl($source);
        if ($source === '' || $resource === '') {
            return;
        }

        $media_bag_sources[$source] ??= $resource;
    }

    /**
     * @param list<string> $resources
     */
    private function recordPackageRelativeResource(
        string $url,
        string $content_dir,
        string $package_base_path,
        array &$resources,
        bool $include_fragment
    ): ?string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return null;
        }

        [$path, , $fragment] = $this->splitUrlSuffix($url);
        if ($path === '') {
            return null;
        }

        $path = $this->decodePackagePathPercentEscapes($path);
        $resource = $this->normalizeZipPath($package_base_path . '/' . $content_dir . '/' . $path);
        if ($include_fragment && $fragment !== null && $fragment !== '') {
            $resource .= '#' . $fragment;
        }
        $resources[] = $resource;

        return $resource;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, string> $media_bag_sources
     * @return array{directory: list<array<string, mixed>>, diagnostics: list<string>}
     */
    private function readEpubMediaBag(EpubArchive $zip, string $base_path, array $manifest, array $media_bag_sources): array
    {
        $diagnostics = [];
        $entries = [];
        $media_types = $this->manifestMediaTypesByResourcePath($base_path, $manifest);

        foreach ($media_bag_sources as $source => $resource) {
            if (!$zip->has($resource)) {
                $diagnostics[] = 'epub-media-resource-missing:' . $resource;
                continue;
            }

            try {
                $digest = $zip->entryDigest($resource);
            } catch (\Throwable) {
                $diagnostics[] = 'epub-media-resource-missing:' . $resource;
                continue;
            }
            $entries[] = MediaBag::directoryEntry(
                $source,
                $media_types[$resource] ?? null,
                (int) $digest['byteLength'],
                (string) $digest['sha1']
            );
            $diagnostics[] = 'epub-media-resource-loaded:' . $resource;
        }

        return [
            'directory' => $this->epubMediaResourceDirectory($entries, $media_bag_sources, $base_path),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, string>
     */
    private function manifestMediaTypesByResourcePath(string $base_path, array $manifest): array
    {
        $media_types = [];
        foreach ($manifest as $item) {
            $href = (string) ($item['href'] ?? '');
            if ($this->isAbsoluteUrl($href)) {
                continue;
            }
            $path = $this->packagePartPath($href, $base_path);
            $media_type = $this->mediaTypeBase((string) ($item['media-type'] ?? ''));
            if ($path !== '' && $media_type !== '') {
                $media_types[$path] = $media_type;
            }
        }

        return $media_types;
    }

    /**
     * @param list<array<string, mixed>> $directory
     * @param array<string, string> $media_bag_sources
     * @return list<array<string, mixed>>
     */
    private function epubMediaResourceDirectory(array $directory, array $media_bag_sources, string $base_path): array
    {
        $entries = [];
        foreach ($directory as $entry) {
            $source = is_string($entry['source'] ?? null) ? $entry['source'] : '';
            $zip_entry = $media_bag_sources[$source] ?? $source;
            $entry['zipEntry'] = $zip_entry;
            $entry['path'] = $this->pathRelativeToPackageBase($zip_entry, $base_path);
            $entries[] = $entry;
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => [
                (string) ($left['path'] ?? ''),
                (string) ($left['source'] ?? ''),
            ] <=> [
                (string) ($right['path'] ?? ''),
                (string) ($right['source'] ?? ''),
            ]
        );

        return $entries;
    }

    private function mediaBagSourceUrl(string $url): string
    {
        $source = str_replace('\\', '/', $url);
        $query = strpos($source, '?');
        $fragment = strpos($source, '#');
        $positions = array_filter(
            [$query, $fragment],
            static fn (int|false $position): bool => $position !== false
        );
        if ($positions !== []) {
            $source = substr($source, 0, min($positions));
        }

        return $source;
    }

    private function pathRelativeToPackageBase(string $path, string $base_path): string
    {
        $path = $this->normalizeZipPath($path);
        $base_path = $this->normalizeZipPath($base_path);
        if ($base_path !== '' && str_starts_with($path, $base_path . '/')) {
            return substr($path, strlen($base_path) + 1);
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function splitUrlSuffix(string $url): array
    {
        $hash = strpos($url, '#');
        $without_fragment = $hash === false ? $url : substr($url, 0, $hash);
        $fragment = $hash === false ? null : substr($url, $hash + 1);
        $query_offset = strpos($without_fragment, '?');
        $path = $query_offset === false ? $without_fragment : substr($without_fragment, 0, $query_offset);
        $query = $query_offset === false ? null : substr($without_fragment, $query_offset + 1);

        return [$path, $query, $fragment];
    }

    private function appendUrlSuffix(string $path, ?string $query, ?string $fragment): string
    {
        if ($query !== null) {
            $path .= '?' . $query;
        }
        if ($fragment !== null) {
            $path .= '#' . $fragment;
        }

        return $path;
    }

    private function stripUrlQueryAndFragment(string $url): string
    {
        return $this->splitUrlSuffix($url)[0];
    }

    private function packagePartPath(string $url, string $base_path = ''): string
    {
        $path = $this->stripUrlQueryAndFragment($url);
        if ($path === '') {
            return '';
        }

        $path = $this->decodePackagePathPercentEscapes($path);

        return $this->normalizeZipPath($base_path === '' ? $path : $base_path . '/' . $path);
    }

    private function prefixedEpubId(string $filename, string $id): string
    {
        return $id === '' ? '' : $filename . '_' . $id;
    }

    private function spineFilename(string $path): string
    {
        $path = $this->stripUrlQueryAndFragment($path);
        $path = $this->decodePackagePathPercentEscapes($path);
        $filename = basename(str_replace('\\', '/', $path));

        return str_replace('%2F', '/', rawurlencode($filename));
    }

    private function rewriteRelativeResourceUrl(string $url, string $base_path): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        if ($path === '') {
            return $url;
        }

        $path = $this->decodePackagePathPercentEscapes($path);

        return $this->appendUrlSuffix($this->normalizeZipPath($base_path . '/' . $path), $query, $fragment);
    }

    private function normalizeImageHrefPreservingSuffix(string $url): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        if ($path === '') {
            return $url;
        }

        return $this->appendUrlSuffix($this->normalizeZipPath($path), $query, $fragment);
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        return !$this->isAbsoluteUrl($url)
            && !str_starts_with($url, '/')
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:');
    }

    private function isReadablePackageXhtml(string $media_type): bool
    {
        return $media_type === 'application/xhtml+xml';
    }

    private function isReadableSpineItem(string $media_type): bool
    {
        return $this->isReadablePackageXhtml($media_type)
            || $this->isDirectSpineImageMediaType($media_type);
    }

    private function isDirectSpineImageMediaType(string $media_type): bool
    {
        return in_array($media_type, ['image/gif', 'image/jpeg', 'image/png'], true);
    }

    private function mediaTypeBase(string $media_type): string
    {
        return strtolower(trim(explode(';', $media_type, 2)[0]));
    }

    private function zipEntryContents(EpubArchive $zip, string $path): ?string
    {
        if (!$zip->has($path)) {
            return null;
        }

        return $zip->read($path);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $xml = $this->normalizeEpubXmlSource($xml);
        try {
            return Html5Dom::parseXmlDocument($this->xmlWithoutSafeHtmlDoctype($xml), $label);
        } catch (\RuntimeException) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }
    }

    /**
     * Normalize EPUB XML resources before the hardened XML and HTML parsers
     * see them. EPUBs in the wild regularly preserve their exporter's
     * UTF-16 XML and stylesheet processing instructions. Neither is content
     * that can be represented in the document AST, and raw UTF-16 contains
     * NUL bytes that strict parser preflight must reject.
     */
    private function normalizeEpubXmlSource(string $source): string
    {
        $encoding = $this->epubXmlByteEncoding($source);
        if ($encoding !== null) {
            $source = UnicodeText::decodeBytes($source, $encoding)['text'];
        }

        return $this->epubXmlSourceWithoutProcessingInstructions($source);
    }

    /**
     * Identify Unicode XML byte streams before strict parser preflight sees
     * UTF-16/32's structural NUL bytes. The no-BOM forms use the XML byte
     * order signatures that begin an XML declaration or document element.
     */
    private function epubXmlByteEncoding(string $source): ?string
    {
        if (!str_contains($source, "\0")) {
            return null;
        }

        $declared = UnicodeText::declaredCharset($source);
        $bom_encoding = $declared['source'] === 'byte-order-mark' ? $declared['encoding'] : null;
        if (in_array($bom_encoding, ['utf-16le', 'utf-16be', 'utf-32le', 'utf-32be'], true)) {
            return $bom_encoding;
        }

        if (str_starts_with($source, "\x00\x00\x00<")) {
            return 'utf-32be';
        }
        if (str_starts_with($source, "<\x00\x00\x00")) {
            return 'utf-32le';
        }
        if (str_starts_with($source, "\x00<")) {
            return 'utf-16be';
        }
        if (str_starts_with($source, "<\x00")) {
            return 'utf-16le';
        }

        return null;
    }

    /**
     * Drop XML processing instructions without touching declaration-looking
     * text in comments or CDATA. EPUB styling and exporter PIs are not part
     * of the converted document; removing them avoids asking the strict
     * HTML/XML facades to treat an inert instruction as active input.
     */
    private function epubXmlSourceWithoutProcessingInstructions(string $source): string
    {
        if (!str_contains($source, '<?')) {
            return $source;
        }

        $result = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $start = strpos($source, '<', $offset);
            if ($start === false) {
                return $result . substr($source, $offset);
            }

            $result .= substr($source, $offset, $start - $offset);
            foreach ([['<!--', '-->'], ['<![CDATA[', ']]>']] as [$prefix, $suffix]) {
                if (substr_compare($source, $prefix, $start, strlen($prefix)) !== 0) {
                    continue;
                }

                $end = strpos($source, $suffix, $start + strlen($prefix));
                if ($end === false) {
                    return $result . substr($source, $start);
                }

                $next = $end + strlen($suffix);
                $result .= substr($source, $start, $next - $start);
                $offset = $next;
                continue 2;
            }

            if (substr_compare($source, '<?', $start, 2) === 0) {
                $end = strpos($source, '?>', $start + 2);
                if ($end === false) {
                    // Preserve malformed PI-looking text as visible text so
                    // the HTML recovery path can still import the chapter.
                    $result .= '&lt;?';
                    $offset = $start + 2;
                    continue;
                }

                $offset = $end + 2;
                continue;
            }

            $result .= '<';
            $offset = $start + 1;
        }

        return $result;
    }

    private function xmlWithoutSafeHtmlDoctype(string $xml): string
    {
        return preg_replace(
            '/^(\s*(?:<\?xml[^?]*\?>\s*)?)<!DOCTYPE\s+html(?:\s+(?:PUBLIC\s+"[^"]*"\s+"[^"]*"|SYSTEM\s+"[^"]*"))?\s*>\s*/i',
            '$1',
            $xml,
            1,
        ) ?? $xml;
    }

    private function attributeByLocalName(\DOMElement $element, string $name): string
    {
        if ($element->hasAttribute($name)) {
            return $element->getAttribute($name);
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function epubTypeAttribute(\DOMElement $element): string
    {
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || !$this->isEpubTypeAttribute($attribute)) {
                continue;
            }

            return $attribute->value;
        }

        return $this->attributeByLocalName($element, 'type');
    }

    private function isEpubTypeAttribute(\DOMAttr $attribute): bool
    {
        return ($attribute->localName === 'type' && ($attribute->prefix === 'epub' || $attribute->namespaceURI === 'http://www.idpf.org/2007/ops'))
            || strtolower($attribute->nodeName) === 'epub:type';
    }

    /**
     * @return list<string>
     */
    private function tokenList(string $value): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($value))) ?: [],
            static fn (string $token): bool => $token !== ''
        ));
    }

    private function hasToken(string $value, string $token): bool
    {
        return in_array(strtolower($token), $this->tokenList($value), true);
    }

    private function firstDescendantText(\DOMElement $element, string $localName): string
    {
        $descendant = $this->firstDescendantElement($element, $localName);

        return $descendant instanceof \DOMElement ? $descendant->textContent : '';
    }

    private function firstDescendantElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $descendant->localName === $localName) {
                return $descendant;
            }
        }

        return null;
    }

    private function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));
        return $dir === '.' ? '' : $dir;
    }

    private function normalizeZipPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function decodePackagePathPercentEscapes(string $path): string
    {
        return preg_replace_callback(
            '/%[0-9A-Fa-f]{2}/',
            static function (array $match): string {
                $byte = hexdec(substr($match[0], 1));
                if ($byte === 0x00 || $byte === 0x2f || $byte === 0x5c) {
                    return $match[0];
                }

                return chr($byte);
            },
            $path
        ) ?? $path;
    }
}
