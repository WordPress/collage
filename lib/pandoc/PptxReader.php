<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxReader
{
    private const TRANSITIONAL_OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const STRICT_OFFICE_DOCUMENT_RELATIONSHIP = 'http://purl.oclc.org/ooxml/officeDocument/relationships/officeDocument';
    private const OFFICE_DOCUMENT_RELATIONSHIP_TYPES = [
        self::TRANSITIONAL_OFFICE_DOCUMENT_RELATIONSHIP,
        self::STRICT_OFFICE_DOCUMENT_RELATIONSHIP,
    ];
    private const MISSING_RELATIONSHIP_TYPE = 'urn:port-libs:pandoc:pptx:missing-relationship-type';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const STRICT_RELATIONSHIP_NAMESPACE = 'http://purl.oclc.org/ooxml/officeDocument/relationships';
    private const RELATIONSHIP_NAMESPACES = [
        self::RELATIONSHIP_NAMESPACE,
        self::STRICT_RELATIONSHIP_NAMESPACE,
    ];
    private const PRESENTATION_NAMESPACE = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const STRICT_PRESENTATION_NAMESPACE = 'http://purl.oclc.org/ooxml/presentationml/main';
    private const PRESENTATION_NAMESPACES = [
        self::PRESENTATION_NAMESPACE,
        self::STRICT_PRESENTATION_NAMESPACE,
    ];
    private const PRESENTATION14_NAMESPACE = 'http://schemas.microsoft.com/office/powerpoint/2010/main';
    private const PRESENTATION14_SECTION_LIST_EXTENSION_URI = '{521415D9-36F7-43E2-AB2F-B90AF26B5E84}';
    private const DRAWING_NAMESPACE = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const STRICT_DRAWING_NAMESPACE = 'http://purl.oclc.org/ooxml/drawingml/main';
    private const DRAWING_NAMESPACES = [
        self::DRAWING_NAMESPACE,
        self::STRICT_DRAWING_NAMESPACE,
    ];
    private const DIAGRAM_NAMESPACE = 'http://schemas.openxmlformats.org/drawingml/2006/diagram';
    private const COMMENT_AUTHORS_PART = '/ppt/commentAuthors.xml';
    private const CORE_PROPERTIES_RELATIONSHIP_SUFFIX = '/metadata/core-properties';
    private const EXTENDED_PROPERTIES_RELATIONSHIP_SUFFIX = '/extended-properties';
    private const CUSTOM_PROPERTIES_RELATIONSHIP_SUFFIX = '/custom-properties';
    private const MAX_XML_PART_BYTES = 8_388_608;
    private const MAX_EMBEDDED_PACKAGE_PROVENANCE_BYTES = 16_777_216;
    private const EMUS_PER_INCH = 914_400;
    private const DEFAULT_SLIDE_WIDTH_EMU = 9_144_000;
    private const DEFAULT_SLIDE_HEIGHT_EMU = 6_858_000;
    private const HASKELL_INT_MIN_ABS_DECIMAL = '9223372036854775808';
    private const HASKELL_INT_MIN_ABS_HEX = '8000000000000000';
    private const HASKELL_INT_MIN_ABS_OCTAL = '1000000000000000000000';

    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $package = ZipPackage::fromString($bytes);

        return $this->readPackage($package, strlen($bytes));
    }

    private function readPackage(ZipPackage $package, int $sourceBytes): AstNode
    {
        $rootRelationships = $this->relationshipsOrEmpty($package, '/');
        $presentationRelationship = $this->presentationRelationship($package);
        $presentationPart = $this->upstreamPresentationPart($presentationRelationship->target);
        $presentation = $this->loadPackageXmlFromUpstreamPath($package, $presentationPart, 'PPTX presentation');
        $slides = $this->parsePresentationSlides($presentation);
        $slideSize = $this->presentationSlideSize($presentation);
        $presentationRelationships = $this->relationshipsOrEmpty($package, $presentationPart);
        $presentationSections = $this->presentationSections($presentation, $slides);
        $customShows = $this->presentationCustomShows($presentation, $slides, $presentationRelationships);
        $tableStyles = $this->presentationTableStyles($package, $presentationRelationships);
        $commentAuthors = $this->commentAuthors($package);
        $documentProperties = $this->documentProperties($package, $rootRelationships);

        $blocks = [];
        $slideReviews = [];
        $mediaBagSources = [];
        foreach ($slides as $slide) {
            $relationship = $presentationRelationships->byId($slide['relationshipId']);
            if (!$relationship instanceof OpcRelationship) {
                throw new \RuntimeException('Relationship not found: ' . $slide['relationshipId']);
            }

            $slidePart = $this->upstreamPresentationSlidePart($relationship->target);
            $slideDocument = $this->loadPackageXml($package, $slidePart, 'PPTX slide ' . $slide['index']);
            $slideRelationships = $this->relationshipsOrEmpty($package, $slidePart);
            $slideContext = $this->slideContext($package, $slideRelationships);
            $slideComments = $this->slideComments($package, $slideRelationships, $commentAuthors);
            $slideSpeakerNotes = $this->slideSpeakerNotes($package, $slideRelationships);
            $slideBackgrounds = $this->slideBackgrounds($package, $slideDocument, $slideRelationships);
            $slideTransition = $this->slideTransitionMetadata($slideDocument, $slideRelationships);
            $slideTiming = $this->slideTimingMetadata($slideDocument);
            $slideAnimations = $this->slideAnimationsMetadata($slideDocument);
            $imageIssues = [];
            $shapeIssues = [];
            $richMedia = [];
            $slideBlocks = $this->slideToBlocks($package, $slide['index'], $slideDocument, $slideRelationships, $slideContext, $slideComments, $slideSpeakerNotes, $tableStyles, $imageIssues, $shapeIssues, $richMedia);
            $this->recordPptxAstMediaBagSources($slideBlocks, $mediaBagSources, $slide['index']);
            $this->recordPptxBackgroundMediaBagSources($slideBackgrounds, $mediaBagSources, $slide['index']);
            $this->recordPptxTransitionMediaBagSources($slideTransition, $mediaBagSources, $slide['index']);
            $this->recordPptxRichMediaBagSources($richMedia, $mediaBagSources, $slide['index']);
            foreach ($slideBlocks as $block) {
                $blocks[] = $block;
            }

            $charts = $this->collectChartReviews($slideBlocks);
            $links = $this->collectLinkReviews($slideBlocks);
            $slideReviews[] = [
                'index' => $slide['index'],
                'slideId' => $slide['slideId'],
                'relationshipId' => $slide['relationshipId'],
                'partName' => ltrim($slidePart, '/'),
                'blockCount' => count($slideBlocks),
                'context' => $this->slideContextReview($slideContext),
                'commentCount' => count($slideComments),
                'comments' => $slideComments,
                'speakerNoteCount' => count($slideSpeakerNotes),
                'speakerNotes' => $this->speakerNoteReviews($slideSpeakerNotes),
                'backgroundCount' => count($slideBackgrounds),
                'backgrounds' => $slideBackgrounds,
                'transition' => $slideTransition,
                'timing' => $slideTiming,
                'animationCount' => count($slideAnimations),
                'animations' => $slideAnimations,
                'imageIssueCount' => count($imageIssues),
                'imageIssues' => $imageIssues,
                'shapeIssueCount' => count($shapeIssues),
                'shapeIssues' => $shapeIssues,
                'richMediaCount' => count($richMedia),
                'richMedia' => $richMedia,
                'linkCount' => count($links),
                'links' => $links,
                'chartCount' => count($charts),
                'charts' => $charts,
            ];
        }
        $mediaBag = $this->readPptxMediaBag($package, $mediaBagSources);

        return new AstNode('document', [
            'sourceFormat' => 'pptx',
            'meta' => [],
            'pptx' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-pptx-reader',
                'sourceBytes' => $sourceBytes,
                'entryCount' => count($package->names()),
                'presentationPart' => ltrim($presentationPart, '/'),
                'slideCount' => count($slides),
                'slideSize' => $slideSize,
                'slides' => $slideReviews,
                'presentationSections' => $presentationSections,
                'customShows' => $customShows,
                'documentProperties' => $documentProperties,
                'commentAuthors' => $commentAuthors,
                'tableStyles' => $tableStyles,
                'mediaBag' => [
                    'policy' => 'reader-media-bag-from-internal-pptx-media-parts',
                    'itemCount' => count($mediaBag['directory']),
                    'directory' => $mediaBag['directory'],
                    'diagnostics' => $mediaBag['diagnostics'],
                ],
                'payloadExposurePolicy' => 'xml-text-and-media-reference-only',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'covered' => 1,
                    'fixtures' => [
                        'test/pptx-reader/basic.pptx',
                        'test/pptx-reader/basic.native',
                    ],
                    'fixtureCommit' => '4f5226df4faa0d66dd2c089465b13886360ab3c2',
                    'source' => 'Pandoc test/Tests/Readers/Pptx.hs plus src/Text/Pandoc/Readers/Pptx.hs and src/Text/Pandoc/Readers/Pptx/{Parse,Slides,Shapes,SmartArt}.hs',
                ],
            ],
        ], $blocks);
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     */
    private function recordPptxAstMediaBagSources(array $nodes, array &$mediaBagSources, int $slideIndex): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            if ($node->type === 'image') {
                $partName = ltrim((string) $node->attr('url', $node->attr('src', '')), '/');
                if ($this->isPptxMediaPart($partName)) {
                    $this->recordPptxMediaBagSource($mediaBagSources, $partName, [
                        'role' => 'image',
                        'slideIndex' => $slideIndex,
                        'relationshipId' => (string) $node->attr('relationshipId', ''),
                        'relationshipAttribute' => (string) $node->attr('relationshipAttribute', ''),
                    ]);
                }
            }

            if ($node->children !== []) {
                $this->recordPptxAstMediaBagSources($node->children, $mediaBagSources, $slideIndex);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $backgrounds
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     */
    private function recordPptxBackgroundMediaBagSources(array $backgrounds, array &$mediaBagSources, int $slideIndex): void
    {
        foreach ($backgrounds as $index => $background) {
            if (($background['exists'] ?? false) !== true) {
                continue;
            }

            $partName = ltrim((string) ($background['partName'] ?? ''), '/');
            if (!$this->isPptxMediaPart($partName)) {
                continue;
            }

            $this->recordPptxMediaBagSource($mediaBagSources, $partName, [
                'role' => 'background',
                'slideIndex' => $slideIndex,
                'backgroundIndex' => $index,
                'relationshipId' => (string) ($background['relationshipId'] ?? ''),
                'relationshipAttribute' => (string) ($background['relationshipAttribute'] ?? ''),
                'relationshipType' => (string) ($background['relationshipType'] ?? ''),
                'target' => (string) ($background['target'] ?? ''),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $transition
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     */
    private function recordPptxTransitionMediaBagSources(array $transition, array &$mediaBagSources, int $slideIndex): void
    {
        $sound = is_array($transition['sound'] ?? null) ? $transition['sound'] : [];
        if ($sound === [] || ($sound['external'] ?? false) === true) {
            return;
        }

        $partName = ltrim((string) ($sound['partName'] ?? ''), '/');
        if (!$this->isPptxMediaPart($partName)) {
            return;
        }

        $this->recordPptxMediaBagSource($mediaBagSources, $partName, [
            'role' => 'transition-sound',
            'slideIndex' => $slideIndex,
            'action' => (string) ($sound['action'] ?? ''),
            'name' => (string) ($sound['name'] ?? ''),
            'relationshipId' => (string) ($sound['relationshipId'] ?? ''),
            'relationshipAttribute' => (string) ($sound['relationshipAttribute'] ?? ''),
            'relationshipType' => (string) ($sound['relationshipType'] ?? ''),
            'target' => (string) ($sound['target'] ?? ''),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $richMedia
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     */
    private function recordPptxRichMediaBagSources(array $richMedia, array &$mediaBagSources, int $slideIndex): void
    {
        foreach ($richMedia as $index => $media) {
            if (($media['external'] ?? false) === true) {
                continue;
            }

            $partName = ltrim((string) ($media['partName'] ?? ''), '/');
            if (!$this->isPptxMediaPart($partName)) {
                continue;
            }

            $shape = is_array($media['shape'] ?? null) ? $media['shape'] : [];
            $this->recordPptxMediaBagSource($mediaBagSources, $partName, [
                'role' => 'rich-media',
                'kind' => (string) ($media['kind'] ?? ''),
                'slideIndex' => $slideIndex,
                'richMediaIndex' => $index,
                'relationshipId' => (string) ($media['relationshipId'] ?? ''),
                'relationshipType' => (string) ($media['relationshipType'] ?? ''),
                'target' => (string) ($media['target'] ?? ''),
                'shapeName' => (string) ($shape['name'] ?? ''),
                'shapeElement' => (string) ($shape['element'] ?? ''),
                'shapeZOrder' => isset($shape['zOrder']) ? (int) $shape['zOrder'] : null,
            ]);
        }
    }

    /**
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     * @param array<string, mixed> $source
     */
    private function recordPptxMediaBagSource(array &$mediaBagSources, string $partName, array $source): void
    {
        $partName = ltrim($partName, '/');
        if (!$this->isPptxMediaPart($partName)) {
            return;
        }

        $source['partName'] = $partName;
        foreach ($source as $key => $value) {
            if ($value === '' || $value === null) {
                unset($source[$key]);
            }
        }

        foreach ($mediaBagSources[$partName] ?? [] as $existing) {
            if ($existing === $source) {
                return;
            }
        }

        $mediaBagSources[$partName][] = $source;
    }

    private function isPptxMediaPart(string $partName): bool
    {
        $partName = ltrim($partName, '/');

        return str_starts_with($partName, 'ppt/media/') && $partName !== 'ppt/media/';
    }

    /**
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     * @return array{directory: list<array<string, mixed>>, diagnostics: list<string>}
     */
    private function readPptxMediaBag(ZipPackage $package, array $mediaBagSources): array
    {
        $bag = new MediaBag();
        $diagnostics = [];

        foreach (array_keys($mediaBagSources) as $partName) {
            if (!$package->has($partName)) {
                $diagnostics[] = 'pptx-media-resource-missing:' . $partName;
                continue;
            }

            $bag->insertMedia($partName, null, $package->read($partName));
            $diagnostics[] = 'pptx-media-resource-loaded:' . $partName;
        }

        return [
            'directory' => $this->pptxMediaResourceDirectory($bag->directory(), $mediaBagSources),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $directory
     * @param array<string, list<array<string, mixed>>> $mediaBagSources
     * @return list<array<string, mixed>>
     */
    private function pptxMediaResourceDirectory(array $directory, array $mediaBagSources): array
    {
        $entries = [];
        foreach ($directory as $entry) {
            $partName = ltrim((string) ($entry['source'] ?? $entry['path'] ?? ''), '/');
            $sources = $mediaBagSources[$partName] ?? [];
            $entry['partName'] = $partName;
            $entry['zipEntry'] = $partName;
            $entry['sourceRoles'] = $this->uniquePptxMediaSourceValues($sources, 'role');
            $entry['slideIndexes'] = array_map('intval', $this->uniquePptxMediaSourceValues($sources, 'slideIndex'));
            $entry['relationshipIds'] = $this->uniquePptxMediaSourceValues($sources, 'relationshipId');
            $entry['sources'] = $sources;
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return list<string|int>
     */
    private function uniquePptxMediaSourceValues(array $sources, string $key): array
    {
        $values = [];
        foreach ($sources as $source) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if ($value === '' || $value === null || in_array($value, $values, true)) {
                continue;
            }
            $values[] = $value;
        }

        return $values;
    }

    private function presentationRelationship(ZipPackage $package): OpcRelationship
    {
        $relationshipPart = $this->upstreamRelationshipPart('/');
        if (!$package->has($relationshipPart)) {
            throw new \RuntimeException('Missing _rels/.rels');
        }

        $document = $this->loadPackageXml($package, $relationshipPart, 'PPTX relationships');
        $root = XmlHtmlDom::rootElement($document);
        $relationshipCount = 0;
        if ($root instanceof \DOMElement) {
            foreach ($this->childElements($root, null) as $relationshipElement) {
                $relationshipCount++;
                $type = $relationshipElement->getAttribute('Type');
                if (!in_array($type, self::OFFICE_DOCUMENT_RELATIONSHIP_TYPES, true)) {
                    continue;
                }
                if (!$relationshipElement->hasAttribute('Target')) {
                    throw new \RuntimeException('Missing Target attribute');
                }

                $targetMode = $relationshipElement->getAttribute('TargetMode');

                return new OpcRelationship(
                    $relationshipElement->hasAttribute('Id') ? $relationshipElement->getAttribute('Id') : '',
                    $type,
                    $relationshipElement->getAttribute('Target'),
                    $targetMode === OpcRelationship::TARGET_MODE_EXTERNAL
                        ? OpcRelationship::TARGET_MODE_EXTERNAL
                        : OpcRelationship::TARGET_MODE_INTERNAL,
                    $targetMode !== '',
                    false,
                    false
                );
            }
        }

        throw new \RuntimeException('No presentation.xml relationship found. Found ' . $relationshipCount . ' relationships.');
    }

    private function relationshipsOrEmpty(ZipPackage $package, string $sourcePart): OpcRelationships
    {
        $relationshipPart = $this->upstreamRelationshipPart($sourcePart);
        $relationshipSourcePart = $this->upstreamRelationshipResolutionSourcePart($sourcePart);
        if (!in_array($relationshipPart, $package->names(), true)) {
            return new OpcRelationships($relationshipSourcePart);
        }

        $document = $this->loadPackageXml($package, $relationshipPart, 'PPTX relationships');
        $root = XmlHtmlDom::rootElement($document);
        $relationships = new OpcRelationships($relationshipSourcePart);
        if (!$root instanceof \DOMElement) {
            return $relationships;
        }

        foreach ($this->childElements($root, null) as $relationshipElement) {
            if (!$relationshipElement->hasAttribute('Id') || !$relationshipElement->hasAttribute('Target')) {
                continue;
            }
            $id = $relationshipElement->getAttribute('Id');
            $target = $relationshipElement->getAttribute('Target');
            if ($relationships->byId($id) instanceof OpcRelationship) {
                continue;
            }

            $type = $relationshipElement->getAttribute('Type');
            $targetMode = $relationshipElement->getAttribute('TargetMode');
            $relationships->add(new OpcRelationship(
                $id,
                $type !== '' ? $type : self::MISSING_RELATIONSHIP_TYPE,
                $target,
                $targetMode === OpcRelationship::TARGET_MODE_EXTERNAL
                    ? OpcRelationship::TARGET_MODE_EXTERNAL
                    : OpcRelationship::TARGET_MODE_INTERNAL,
                $targetMode !== '',
                false,
                false
            ));
        }

        return $relationships;
    }

    private function optionalRelationshipsOrEmpty(ZipPackage $package, string $sourcePart): OpcRelationships
    {
        try {
            return $this->relationshipsOrEmpty($package, $sourcePart);
        } catch (\InvalidArgumentException | \RuntimeException) {
            return new OpcRelationships($sourcePart);
        }
    }

    private function optionalContentTypes(ZipPackage $package): ?OpcContentTypes
    {
        if (!$package->has('[Content_Types].xml')) {
            return null;
        }

        try {
            return OpcContentTypes::fromXml($package->read('[Content_Types].xml', self::MAX_XML_PART_BYTES));
        } catch (\InvalidArgumentException | \RuntimeException) {
            return null;
        }
    }

    private function upstreamRelationshipPart(string $sourcePart): string
    {
        if ($sourcePart === '/' || $sourcePart === '') {
            return '_rels/.rels';
        }

        $path = ltrim($sourcePart, '/');
        $directory = dirname($path);
        $file = basename($path);

        return ($directory === '.' ? '/' : $directory . '/') . '_rels/' . $file . '.rels';
    }

    private function upstreamRelationshipResolutionSourcePart(string $sourcePart): string
    {
        if ($sourcePart === '/' || $sourcePart === '') {
            return '/';
        }

        return OpcPackagePath::stripQueryAndFragment($sourcePart);
    }

    private function upstreamPresentationPart(string $target): string
    {
        return $this->upstreamArchiveLookupPath($target);
    }

    private function upstreamPresentationSlidePart(string $target): string
    {
        $path = 'ppt/' . $target;
        $segments = [];
        $rawSegments = explode('/', $path);
        $lastIndex = count($rawSegments) - 1;
        foreach ($rawSegments as $index => $segment) {
            if ($segment === '') {
                if (
                    $index === $lastIndex
                    || ($index > 0 && str_ends_with($rawSegments[$index - 1], ':'))
                ) {
                    $segments[] = $segment;
                }
                continue;
            }
            if ($segment === '.') {
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function upstreamArchiveLookupPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\\/\\//', $path) === 1) {
            return $path;
        }

        $trailingSlash = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        return $trailingSlash ? $normalized . '/' : $normalized;
    }

    private function loadPackageXml(ZipPackage $package, string $partName, string $label, bool $literalPath = true): \DOMDocument
    {
        if ($literalPath && !in_array($partName, $package->names(), true)) {
            throw new \RuntimeException('Entry not found: ' . $partName);
        }
        if (!$literalPath && !$package->has($partName)) {
            throw new \RuntimeException('Entry not found: ' . $partName);
        }

        return XmlHtmlDom::loadXmlDocument($package->read($partName, self::MAX_XML_PART_BYTES), $label, false);
    }

    private function loadPackageXmlFromUpstreamPath(ZipPackage $package, string $path, string $label): \DOMDocument
    {
        return $this->loadPackageXml($package, $path, $label);
    }

    /**
     * @return list<array{index:int, slideId:string, relationshipId:string}>
     */
    private function parsePresentationSlides(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX presentation XML must have a root element');
        }

        $presentationNamespace = $this->localNamespaceForPrefix($root, 'p');
        $slideIdList = $this->firstChildElementForPrefix($root, 'p', 'sldIdLst', $presentationNamespace);
        if (!$slideIdList instanceof \DOMElement) {
            return [];
        }

        $relationshipNamespace = $this->localNamespaceForPrefix($root, 'r');
        $slides = [];
        $index = 1;
        foreach ($this->childElementsForPrefix($slideIdList, 'p', 'sldId', $presentationNamespace) as $slideIdElement) {
            $slideId = trim($slideIdElement->getAttribute('id'));
            $relationshipId = $this->relationshipAttributeForPrefix($slideIdElement, 'r', 'id', $relationshipNamespace);
            if ($relationshipId === null) {
                throw new \RuntimeException('Missing r:id in slide ' . $index);
            }

            $slides[] = ['index' => $index, 'slideId' => $slideId, 'relationshipId' => $relationshipId];
            $index++;
        }

        return $slides;
    }

    /**
     * @param list<array{index:int, slideId:string, relationshipId:string}> $slides
     * @return array<string, mixed>
     */
    private function presentationSections(\DOMDocument $document, array $slides): array
    {
        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            return ['sectionCount' => 0, 'sections' => [], 'issues' => ['missing-presentation-root']];
        }

        $presentationNamespace = $this->localNamespaceForPrefix($root, 'p');
        $slideById = [];
        foreach ($slides as $slide) {
            if ($slide['slideId'] !== '') {
                $slideById[$slide['slideId']] = $slide;
            }
        }

        $sections = [];
        $issues = [];
        $extList = $this->firstChildElementForPrefix($root, 'p', 'extLst', $presentationNamespace);
        if (!$extList instanceof \DOMElement) {
            return ['sectionCount' => 0, 'sections' => [], 'issues' => []];
        }

        foreach ($this->childElementsForPrefix($extList, 'p', 'ext', $presentationNamespace) as $extension) {
            if (trim($extension->getAttribute('uri')) !== self::PRESENTATION14_SECTION_LIST_EXTENSION_URI) {
                continue;
            }

            foreach ($this->childElementsInNamespace($extension, self::PRESENTATION14_NAMESPACE, 'sectionLst') as $sectionList) {
                foreach ($this->childElementsInNamespace($sectionList, self::PRESENTATION14_NAMESPACE, 'section') as $sectionElement) {
                    $section = [
                        'name' => trim($sectionElement->getAttribute('name')),
                        'id' => trim($sectionElement->getAttribute('id')),
                        'slideCount' => 0,
                        'slides' => [],
                        'issues' => [],
                    ];
                    if ($section['name'] === '') {
                        $section['issues'][] = 'missing-section-name';
                    }
                    if ($section['id'] === '') {
                        $section['issues'][] = 'missing-section-id';
                    }

                    $slideIdList = $this->firstChildElementInNamespace($sectionElement, self::PRESENTATION14_NAMESPACE, 'sldIdLst');
                    if ($slideIdList instanceof \DOMElement) {
                        foreach ($this->childElementsInNamespace($slideIdList, self::PRESENTATION14_NAMESPACE, 'sldId') as $slideRef) {
                            $slideId = trim($slideRef->getAttribute('id'));
                            if ($slideId === '') {
                                $section['issues'][] = 'missing-section-slide-id';
                                $section['slides'][] = ['slideId' => '', 'resolved' => false];
                                continue;
                            }
                            if (!ctype_digit($slideId)) {
                                $section['issues'][] = 'non-integer-section-slide-id:' . $slideId;
                            }

                            $resolved = $slideById[$slideId] ?? null;
                            if ($resolved === null) {
                                $section['issues'][] = 'unknown-section-slide-id:' . $slideId;
                                $section['slides'][] = [
                                    'slideId' => $slideId,
                                    'resolved' => false,
                                ];
                                continue;
                            }

                            $section['slides'][] = [
                                'slideId' => $slideId,
                                'resolved' => true,
                                'slideIndex' => $resolved['index'],
                                'relationshipId' => $resolved['relationshipId'],
                            ];
                        }
                    }

                    $section['slideCount'] = count($section['slides']);
                    foreach ($section['issues'] as $issue) {
                        $issues[] = 'section:' . (count($sections) + 1) . ':' . $issue;
                    }
                    $sections[] = $section;
                }
            }
        }

        return [
            'sectionCount' => count($sections),
            'sections' => $sections,
            'issues' => $issues,
        ];
    }

    /**
     * @param list<array{index:int, slideId:string, relationshipId:string}> $slides
     * @return array<string, mixed>
     */
    private function presentationCustomShows(\DOMDocument $document, array $slides, OpcRelationships $presentationRelationships): array
    {
        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            return ['customShowCount' => 0, 'shows' => [], 'issues' => ['missing-presentation-root']];
        }

        $presentationNamespace = $this->localNamespaceForPrefix($root, 'p');
        $relationshipNamespace = $this->localNamespaceForPrefix($root, 'r');
        $slideByRelationshipId = [];
        foreach ($slides as $slide) {
            $slideByRelationshipId[$slide['relationshipId']] = $slide;
        }

        $shows = [];
        $issues = [];
        $ids = [];
        $names = [];
        $customShowList = $this->firstChildElementForPrefix($root, 'p', 'custShowLst', $presentationNamespace);
        if ($customShowList instanceof \DOMElement) {
            foreach ($this->childElementsForPrefix($customShowList, 'p', 'custShow', $presentationNamespace) as $showElement) {
                $show = [
                    'name' => trim($showElement->getAttribute('name')),
                    'id' => trim($showElement->getAttribute('id')),
                    'slideCount' => 0,
                    'slides' => [],
                    'issues' => [],
                ];
                if ($show['name'] === '') {
                    $show['issues'][] = 'missing-custom-show-name';
                } elseif (isset($names[$show['name']])) {
                    $show['issues'][] = 'duplicate-custom-show-name:' . $show['name'];
                }
                if ($show['name'] !== '') {
                    $names[$show['name']] = true;
                }
                if ($show['id'] === '') {
                    $show['issues'][] = 'missing-custom-show-id';
                } elseif (isset($ids[$show['id']])) {
                    $show['issues'][] = 'duplicate-custom-show-id:' . $show['id'];
                }
                if ($show['id'] !== '') {
                    $ids[$show['id']] = true;
                }

                $slideList = $this->firstChildElementForPrefix($showElement, 'p', 'sldLst', $presentationNamespace);
                if ($slideList instanceof \DOMElement) {
                    foreach ($this->childElementsForPrefix($slideList, 'p', 'sld', $presentationNamespace) as $slideRef) {
                        $relationshipId = $this->relationshipAttributeForPrefix($slideRef, 'r', 'id', $relationshipNamespace);
                        if ($relationshipId === null || trim($relationshipId) === '') {
                            $show['issues'][] = 'missing-custom-show-slide-relationship-id';
                            $show['slides'][] = ['relationshipId' => '', 'resolved' => false];
                            continue;
                        }
                        $relationshipId = trim($relationshipId);
                        $relationship = $presentationRelationships->byId($relationshipId);
                        $resolved = $slideByRelationshipId[$relationshipId] ?? null;
                        if (!$relationship instanceof OpcRelationship || $resolved === null) {
                            $show['issues'][] = 'unknown-custom-show-slide-relationship:' . $relationshipId;
                            $show['slides'][] = [
                                'relationshipId' => $relationshipId,
                                'relationshipFound' => $relationship instanceof OpcRelationship,
                                'resolved' => false,
                            ];
                            continue;
                        }

                        $slide = [
                            'relationshipId' => $relationshipId,
                            'relationshipFound' => true,
                            'resolved' => true,
                            'slideIndex' => $resolved['index'],
                            'slideId' => $resolved['slideId'],
                            'target' => $relationship->target,
                        ];
                        if (!$relationship->isExternal()) {
                            $partName = $this->reviewRelationshipPart($presentationRelationships, $relationship);
                            if ($partName !== null) {
                                $slide['partName'] = ltrim($partName, '/');
                            }
                        }
                        $show['slides'][] = $slide;
                    }
                }

                $show['slideCount'] = count($show['slides']);
                foreach ($show['issues'] as $issue) {
                    $issues[] = 'customShow:' . (count($shows) + 1) . ':' . $issue;
                }
                $shows[] = $show;
            }
        }

        $summary = [
            'customShowCount' => count($shows),
            'shows' => $shows,
            'issues' => $issues,
        ];

        $showProperties = $this->firstChildElementForPrefix($root, 'p', 'showPr', $presentationNamespace);
        if ($showProperties instanceof \DOMElement) {
            $activeCustomShow = $this->firstChildElementForPrefix($showProperties, 'p', 'custShow', $presentationNamespace);
            if ($activeCustomShow instanceof \DOMElement) {
                $activeId = trim($activeCustomShow->getAttribute('id'));
                $summary['activeCustomShowId'] = $activeId;
                $summary['activeCustomShowResolved'] = $activeId !== '' && isset($ids[$activeId]);
                if ($activeId === '') {
                    $summary['issues'][] = 'active-custom-show-missing-id';
                } elseif (!isset($ids[$activeId])) {
                    $summary['issues'][] = 'active-custom-show-unknown-id:' . $activeId;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array{cx:int, cy:int, width:int, height:int, emusPerInch:int, source:string}
     */
    private function presentationSlideSize(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX presentation XML must have a root element');
        }

        $presentationNamespace = $this->localNamespaceForPrefix($root, 'p');
        $sizeElement = $this->firstChildElementForPrefix($root, 'p', 'sldSz', $presentationNamespace);
        $source = 'default';
        $widthEmu = self::DEFAULT_SLIDE_WIDTH_EMU;
        $heightEmu = self::DEFAULT_SLIDE_HEIGHT_EMU;
        if ($sizeElement instanceof \DOMElement) {
            $source = 'presentation';
            $widthEmu = $this->presentationSizeAttribute($sizeElement, 'cx');
            $heightEmu = $this->presentationSizeAttribute($sizeElement, 'cy');
        }

        return [
            'cx' => $widthEmu,
            'cy' => $heightEmu,
            'width' => $this->haskellIntegerDiv($widthEmu, self::EMUS_PER_INCH),
            'height' => $this->haskellIntegerDiv($heightEmu, self::EMUS_PER_INCH),
            'emusPerInch' => self::EMUS_PER_INCH,
            'source' => $source,
        ];
    }

    private function presentationSizeAttribute(\DOMElement $element, string $name): int
    {
        return $this->integerAttribute($element, $name) ?? 0;
    }

    private function haskellIntegerDiv(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        return $numerator < 0 && $remainder !== 0 ? $quotient - 1 : $quotient;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @param list<array<string, mixed>> $slideComments
     * @param array<string, mixed> $tableStyles
     * @param list<array<string, mixed>> $imageIssues
     * @param list<array<string, mixed>> $shapeIssues
     * @param list<array<string, string|bool|array<string, mixed>>> $richMedia
     * @return list<AstNode>
     */
    private function slideToBlocks(ZipPackage $package, int $slideIndex, \DOMDocument $document, OpcRelationships $slideRelationships, array $slideContext, array $slideComments, array $slideSpeakerNotes, array $tableStyles, array &$imageIssues, array &$shapeIssues, array &$richMedia): array
    {
        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX slide XML must have a root element');
        }

        $presentationNamespace = $this->localNamespaceForPrefix($root, 'p');
        $title = $this->slideTitle($root, $presentationNamespace);
        if ($title === '') {
            $title = 'Slide ' . $slideIndex;
        }

        $blocks = [
            new AstNode('heading', ['level' => 2, 'id' => 'slide-' . $slideIndex, 'text' => $title], $this->textInlines($title)),
        ];
        $spTree = $this->shapeTree($root, $presentationNamespace);
        if (!$spTree instanceof \DOMElement) {
            return $blocks;
        }

        $relationshipNamespace = $this->localNamespaceForPrefix($root, 'r');
        $drawingNamespace = $this->localNamespaceForPrefix($root, 'a');
        $zOrder = 0;
        foreach ($this->childElements($spTree, null) as $shapeElement) {
            if (!$this->isDrawableShapeElement($shapeElement, $presentationNamespace)) {
                continue;
            }
            $zOrder++;
            if ($this->isTitlePlaceholderForPrefix($shapeElement, $presentationNamespace)) {
                continue;
            }
            foreach ($this->shapeToBlocks($package, $shapeElement, $slideRelationships, $slideContext, $tableStyles, $presentationNamespace, $relationshipNamespace, $drawingNamespace, $zOrder, $imageIssues, $shapeIssues, $richMedia) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function shapeTree(\DOMElement $slide, ?string $presentationNamespace = null): ?\DOMElement
    {
        $presentationNamespace ??= $this->localNamespaceForPrefix($slide, 'p');
        $commonSlideData = $this->firstChildElementForPrefix($slide, 'p', 'cSld', $presentationNamespace);

        return $commonSlideData instanceof \DOMElement ? $this->firstChildElementForPrefix($commonSlideData, 'p', 'spTree', $presentationNamespace) : null;
    }

    private function slideTitle(\DOMElement $slide, ?string $presentationNamespace = null): string
    {
        $spTree = $this->shapeTree($slide, $presentationNamespace);
        if (!$spTree instanceof \DOMElement) {
            return '';
        }

        foreach ($this->childElements($spTree, null) as $shapeElement) {
            if ($this->isTitlePlaceholderForPrefix($shapeElement, $presentationNamespace)) {
                return $this->drawingText($shapeElement);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function slideContext(ZipPackage $package, OpcRelationships $slideRelationships): array
    {
        $context = [
            'layoutPart' => '',
            'masterPart' => '',
            'layoutTitle' => '',
            'masterTitle' => '',
            'themePart' => '',
            'theme' => [],
            'layoutPlaceholders' => [],
            'masterPlaceholders' => [],
        ];

        $layoutRelationship = $this->firstRelationshipWithTypeSuffix($slideRelationships, '/slideLayout');
        if (!$layoutRelationship instanceof OpcRelationship || $layoutRelationship->isExternal()) {
            return $context;
        }

        $layoutPart = $this->reviewRelationshipPart($slideRelationships, $layoutRelationship);
        if ($layoutPart === null) {
            return $context;
        }
        $context['layoutPart'] = ltrim($layoutPart, '/');
        $layoutDocument = $this->optionalPackageXml($package, $layoutPart, 'PPTX slide layout');
        if (!$layoutDocument instanceof \DOMDocument) {
            return $context;
        }

        $layoutRoot = XmlHtmlDom::rootElement($layoutDocument);
        if ($layoutRoot instanceof \DOMElement) {
            $context['layoutTitle'] = $this->slideTitle($layoutRoot);
            $context['layoutPlaceholders'] = $this->placeholderBlocksByKey($layoutRoot);
            foreach ($this->slideSurfaceMetadata($layoutRoot, 'layout') as $key => $value) {
                $context[$key] = $value;
            }
        }

        $layoutRelationships = $this->optionalRelationshipsOrEmpty($package, $layoutPart);
        $masterRelationship = $this->firstRelationshipWithTypeSuffix($layoutRelationships, '/slideMaster');
        if (!$masterRelationship instanceof OpcRelationship || $masterRelationship->isExternal()) {
            return $context;
        }

        $masterPart = $this->reviewRelationshipPart($layoutRelationships, $masterRelationship);
        if ($masterPart === null) {
            return $context;
        }
        $context['masterPart'] = ltrim($masterPart, '/');
        $masterDocument = $this->optionalPackageXml($package, $masterPart, 'PPTX slide master');
        if (!$masterDocument instanceof \DOMDocument) {
            return $context;
        }

        $masterRoot = XmlHtmlDom::rootElement($masterDocument);
        if ($masterRoot instanceof \DOMElement) {
            $context['masterTitle'] = $this->slideTitle($masterRoot);
            $context['masterPlaceholders'] = $this->placeholderBlocksByKey($masterRoot);
            foreach ($this->slideSurfaceMetadata($masterRoot, 'master') as $key => $value) {
                $context[$key] = $value;
            }
        }

        $masterRelationships = $this->optionalRelationshipsOrEmpty($package, $masterPart);
        $themeRelationship = $this->firstRelationshipWithTypeSuffix($masterRelationships, '/theme');
        if (!$themeRelationship instanceof OpcRelationship || $themeRelationship->isExternal()) {
            return $context;
        }

        $themePart = $this->reviewRelationshipPart($masterRelationships, $themeRelationship);
        if ($themePart === null) {
            return $context;
        }
        $context['themePart'] = ltrim($themePart, '/');
        $themeDocument = $this->optionalPackageXml($package, $themePart, 'PPTX theme');
        if (!$themeDocument instanceof \DOMDocument) {
            return $context;
        }

        $themeRoot = XmlHtmlDom::rootElement($themeDocument, 'theme');
        if ($themeRoot instanceof \DOMElement) {
            $context['theme'] = $this->themeSummary($themeRoot);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @return array<string, mixed>
     */
    private function slideContextReview(array $slideContext): array
    {
        $review = [];
        foreach (['layoutPart', 'masterPart', 'themePart'] as $key) {
            $value = (string) ($slideContext[$key] ?? '');
            if ($value !== '') {
                $review[$key] = $value;
            }
        }

        foreach (['layoutName', 'masterName'] as $key) {
            $value = (string) ($slideContext[$key] ?? '');
            if ($value !== '') {
                $review[$key] = $value;
            }
        }

        foreach (['layoutBackground', 'layoutColorMapOverride', 'masterBackground', 'masterColorMap'] as $key) {
            $value = $slideContext[$key] ?? [];
            if (is_array($value) && $value !== []) {
                $review[$key] = $value;
            }
        }

        $layoutPlaceholderKeys = $this->placeholderBucketKeys($slideContext['layoutPlaceholders'] ?? []);
        if ($layoutPlaceholderKeys !== []) {
            $review['layoutPlaceholderCount'] = count($layoutPlaceholderKeys);
            $review['layoutPlaceholderKeys'] = $layoutPlaceholderKeys;
        }

        $masterPlaceholderKeys = $this->placeholderBucketKeys($slideContext['masterPlaceholders'] ?? []);
        if ($masterPlaceholderKeys !== []) {
            $review['masterPlaceholderCount'] = count($masterPlaceholderKeys);
            $review['masterPlaceholderKeys'] = $masterPlaceholderKeys;
        }

        $theme = $slideContext['theme'] ?? [];
        if (is_array($theme) && $theme !== []) {
            $review['theme'] = $theme;
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideSurfaceMetadata(\DOMElement $slideRoot, string $prefix): array
    {
        $metadata = [];
        $commonSlideData = $this->firstChildElement($slideRoot, 'cSld');
        if ($commonSlideData instanceof \DOMElement) {
            $name = trim($commonSlideData->getAttribute('name'));
            if ($name !== '') {
                $metadata[$prefix . 'Name'] = $name;
            }

            $background = $this->slideBackgroundMetadata($commonSlideData);
            if ($background !== []) {
                $metadata[$prefix . 'Background'] = $background;
            }
        }

        if ($prefix === 'master') {
            $colorMap = $this->firstChildElement($slideRoot, 'clrMap');
            if ($colorMap instanceof \DOMElement) {
                $metadata['masterColorMap'] = $this->slideColorMapAttributes($colorMap);
            }
        }

        if ($prefix === 'layout') {
            $colorMapOverride = $this->firstChildElement($slideRoot, 'clrMapOvr');
            if ($colorMapOverride instanceof \DOMElement) {
                $metadata['layoutColorMapOverride'] = $this->slideColorMapOverrideMetadata($colorMapOverride);
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideBackgroundMetadata(\DOMElement $commonSlideData): array
    {
        $background = $this->firstChildElement($commonSlideData, 'bg');
        if (!$background instanceof \DOMElement) {
            return [];
        }

        foreach ($this->childElements($background, null) as $child) {
            if (!in_array($child->localName, ['bgPr', 'bgRef'], true)) {
                continue;
            }

            $metadata = ['type' => $child->localName];
            $color = $this->drawingColorMetadata($child);
            if (is_string($color['color'] ?? null) && $color['color'] !== '') {
                $metadata['color'] = $color['color'];
                if (is_array($color['transforms'] ?? null) && $color['transforms'] !== []) {
                    $metadata['colorTransforms'] = $color['transforms'];
                }
            }
            $index = trim($child->getAttribute('idx'));
            if ($index !== '') {
                $metadata['index'] = $index;
            }

            return $metadata;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function slideColorMapAttributes(\DOMElement $colorMap): array
    {
        $attributes = [];
        foreach (['bg1', 'tx1', 'bg2', 'tx2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'] as $name) {
            $value = trim($colorMap->getAttribute($name));
            if ($value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideColorMapOverrideMetadata(\DOMElement $colorMapOverride): array
    {
        foreach ($this->childElements($colorMapOverride, null) as $child) {
            if ($child->localName === 'masterClrMapping') {
                return ['type' => 'masterClrMapping'];
            }
            if ($child->localName === 'overrideClrMapping') {
                $attributes = $this->slideColorMapAttributes($child);

                return $attributes === [] ? ['type' => 'overrideClrMapping'] : ['type' => 'overrideClrMapping', 'map' => $attributes];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentationTableStyles(ZipPackage $package, OpcRelationships $presentationRelationships): array
    {
        $relationship = $this->firstRelationshipWithTypeSuffix($presentationRelationships, '/tableStyles');
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $summary = [
            'relationshipId' => $relationship->id,
            'relationshipType' => $relationship->type,
            'target' => $relationship->target,
            'external' => $relationship->isExternal(),
            'partName' => '',
            'defaultStyleId' => '',
            'styleCount' => 0,
            'styles' => [],
            'issues' => [],
        ];

        if ($relationship->isExternal()) {
            $summary['externalTargetPolicy'] = $relationship->externalTargetPreflight();
            $summary['issues'][] = 'external-table-styles-part';

            return $summary;
        }

        $partName = $this->reviewRelationshipPart($presentationRelationships, $relationship);
        if ($partName === null) {
            $summary['issues'][] = 'invalid-table-styles-target';

            return $summary;
        }
        $summary['partName'] = ltrim($partName, '/');
        $document = $this->optionalPackageXml($package, $partName, 'PPTX table styles');
        if (!$document instanceof \DOMDocument) {
            $summary['issues'][] = 'missing-or-invalid-table-styles-part';

            return $summary;
        }

        $root = XmlHtmlDom::rootElement($document, 'tblStyleLst');
        if (!$root instanceof \DOMElement) {
            $summary['issues'][] = 'unexpected-table-styles-root';

            return $summary;
        }

        $defaultStyleId = trim($root->getAttribute('def'));
        if ($defaultStyleId !== '') {
            $summary['defaultStyleId'] = $defaultStyleId;
        }

        $styles = [];
        foreach ($this->childElements($root, 'tblStyle') as $styleElement) {
            $styleId = trim($styleElement->getAttribute('styleId'));
            if ($styleId === '') {
                continue;
            }

            $style = [
                'id' => $styleId,
                'sourcePart' => (string) $summary['partName'],
                'relationshipId' => $relationship->id,
            ];
            $name = trim($styleElement->getAttribute('styleName'));
            if ($name !== '') {
                $style['name'] = $name;
            }
            if ($defaultStyleId !== '' && $styleId === $defaultStyleId) {
                $style['default'] = true;
            }

            $styleParts = $this->tableStyleDefinitionParts($styleElement);
            if ($styleParts !== []) {
                $style['parts'] = $styleParts;
            }

            $styles[$styleId] = $style;
        }
        ksort($styles, SORT_STRING);

        $summary['styles'] = $styles;
        $summary['styleCount'] = count($styles);

        return $summary;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function documentProperties(ZipPackage $package, OpcRelationships $rootRelationships): array
    {
        return [
            'core' => $this->documentPropertyPart(
                $package,
                $rootRelationships,
                self::CORE_PROPERTIES_RELATIONSHIP_SUFFIX,
                'docProps/core.xml',
                'PPTX core properties',
                fn (\DOMDocument $document): array => $this->coreDocumentProperties($document)
            ),
            'extended' => $this->documentPropertyPart(
                $package,
                $rootRelationships,
                self::EXTENDED_PROPERTIES_RELATIONSHIP_SUFFIX,
                'docProps/app.xml',
                'PPTX extended properties',
                fn (\DOMDocument $document): array => $this->extendedDocumentProperties($document)
            ),
            'custom' => $this->documentPropertyPart(
                $package,
                $rootRelationships,
                self::CUSTOM_PROPERTIES_RELATIONSHIP_SUFFIX,
                'docProps/custom.xml',
                'PPTX custom properties',
                fn (\DOMDocument $document): array => $this->customDocumentProperties($document)
            ),
        ];
    }

    /**
     * @param callable(\DOMDocument): array<string, mixed> $extract
     * @return array<string, mixed>
     */
    private function documentPropertyPart(ZipPackage $package, OpcRelationships $rootRelationships, string $relationshipTypeSuffix, string $fallbackPartName, string $label, callable $extract): array
    {
        $relationship = $this->firstRelationshipWithTypeSuffix($rootRelationships, $relationshipTypeSuffix);
        $partName = $fallbackPartName;
        $review = [
            'partName' => $fallbackPartName,
            'exists' => false,
            'relationshipId' => '',
            'relationshipType' => '',
            'target' => '',
            'external' => false,
            'issues' => [],
        ];

        if ($relationship instanceof OpcRelationship) {
            $review['relationshipId'] = $relationship->id;
            $review['relationshipType'] = $relationship->type;
            $review['target'] = $relationship->target;
            $review['external'] = $relationship->isExternal();
            if ($relationship->isExternal()) {
                $review['externalTargetPolicy'] = $relationship->externalTargetPreflight();
                $review['issues'][] = 'external-document-properties-part';

                return $review;
            }

            $resolvedPartName = $this->reviewRelationshipPart($rootRelationships, $relationship);
            if ($resolvedPartName === null) {
                $review['issues'][] = 'invalid-document-properties-target';

                return $review;
            }

            $partName = ltrim($resolvedPartName, '/');
            $review['partName'] = $partName;
        }

        if (!$package->has($partName)) {
            $review['issues'][] = 'missing-document-properties-part';

            return $review;
        }

        $document = $this->optionalPackageXml($package, $partName, $label);
        if (!$document instanceof \DOMDocument) {
            $review['issues'][] = 'invalid-document-properties-part';

            return $review;
        }

        $review['exists'] = true;

        return array_replace($review, $extract($document));
    }

    /**
     * @return array{values:array<string, string>, valueCount:int}
     */
    private function coreDocumentProperties(\DOMDocument $document): array
    {
        $values = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $key = match ($element->localName) {
                'title' => 'title',
                'creator' => 'creator',
                'lastModifiedBy' => 'lastModifiedBy',
                'revision' => 'revision',
                'subject' => 'subject',
                'keywords' => 'keywords',
                'description' => 'description',
                'category' => 'category',
                'contentStatus' => 'contentStatus',
                'created' => 'created',
                'modified' => 'modified',
                default => '',
            };
            if ($key === '') {
                continue;
            }

            $value = $this->documentPropertyText($element);
            if ($value !== '') {
                $values[$key] = $value;
            }
        }

        return [
            'values' => $values,
            'valueCount' => count($values),
        ];
    }

    /**
     * @return array{values:array<string, string|int|bool>, valueCount:int}
     */
    private function extendedDocumentProperties(\DOMDocument $document): array
    {
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return ['values' => [], 'valueCount' => 0];
        }

        $integerKeys = array_fill_keys([
            'bytes',
            'characters',
            'charactersWithSpaces',
            'docSecurity',
            'hiddenSlides',
            'lines',
            'linksDirty',
            'mmClips',
            'notes',
            'pages',
            'paragraphs',
            'slides',
            'totalTime',
            'words',
        ], true);
        $booleanKeys = array_fill_keys([
            'hyperlinksChanged',
            'linksUpToDate',
            'scaleCrop',
            'sharedDoc',
        ], true);
        $values = [];
        foreach ($this->childElements($root, null) as $element) {
            $key = $this->lowerCamelName($element->localName);
            if ($key === 'headingPairs' || $key === 'titlesOfParts') {
                continue;
            }

            $text = $this->documentPropertyText($element);
            if ($text === '') {
                continue;
            }
            if (isset($integerKeys[$key]) && preg_match('/^-?\d+$/', $text) === 1) {
                $values[$key] = (int) $text;
                continue;
            }
            if (isset($booleanKeys[$key])) {
                $values[$key] = $this->documentPropertyBoolean($text);
                continue;
            }

            $values[$key] = $text;
        }

        return [
            'values' => $values,
            'valueCount' => count($values),
        ];
    }

    /**
     * @return array{count:int, duplicateNameCount:int, duplicateNames:list<string>, items:list<array<string, mixed>>, byName:array<string, mixed>}
     */
    private function customDocumentProperties(\DOMDocument $document): array
    {
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return ['count' => 0, 'duplicateNameCount' => 0, 'duplicateNames' => [], 'items' => [], 'byName' => []];
        }

        $items = [];
        $byName = [];
        $seen = [];
        $duplicates = [];
        foreach ($this->childElements($root, 'property') as $property) {
            $name = trim($property->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $valueElement = null;
            foreach ($this->childElements($property, null) as $child) {
                $valueElement = $child;
                break;
            }
            if (!$valueElement instanceof \DOMElement) {
                continue;
            }

            $value = $this->documentPropertyTypedValue($valueElement);
            $duplicate = isset($seen[$name]);
            $seen[$name] = true;
            if ($duplicate) {
                $duplicates[$name] = true;
            } else {
                $byName[$name] = $value;
            }

            $item = [
                'name' => $name,
                'valueType' => $valueElement->localName,
                'value' => $value,
                'duplicate' => $duplicate,
            ];
            $pid = $property->getAttribute('pid');
            if ($pid !== '' && preg_match('/^-?\d+$/', $pid) === 1) {
                $item['pid'] = (int) $pid;
            }
            $fmtid = trim($property->getAttribute('fmtid'));
            if ($fmtid !== '') {
                $item['fmtid'] = $fmtid;
            }
            $items[] = $item;
        }

        $duplicateNames = array_keys($duplicates);
        sort($duplicateNames, SORT_STRING);
        ksort($byName, SORT_STRING);

        return [
            'count' => count($items),
            'duplicateNameCount' => count($duplicateNames),
            'duplicateNames' => $duplicateNames,
            'items' => $items,
            'byName' => $byName,
        ];
    }

    private function documentPropertyTypedValue(\DOMElement $element): mixed
    {
        if (in_array($element->localName, ['array', 'vector'], true)) {
            $values = [];
            foreach ($this->childElements($element, null) as $child) {
                $values[] = $this->documentPropertyTypedValue($child);
            }

            return $values;
        }

        $text = $this->documentPropertyText($element);

        return match ($element->localName) {
            'bool' => $this->documentPropertyBoolean($text),
            'i1', 'i2', 'i4', 'i8', 'int', 'ui1', 'ui2', 'ui4', 'ui8', 'uint' => preg_match('/^-?\d+$/', $text) === 1 ? (int) $text : $text,
            'decimal', 'r4', 'r8' => is_numeric($text) ? (float) $text : $text,
            default => $text,
        };
    }

    private function documentPropertyBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    private function documentPropertyText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
    }

    private function lowerCamelName(string $name): string
    {
        if ($name === 'MMClips') {
            return 'mmClips';
        }

        return $name === '' ? '' : strtolower($name[0]) . substr($name, 1);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tableStyleDefinitionParts(\DOMElement $styleElement): array
    {
        $parts = [];
        foreach ($this->childElements($styleElement, null) as $partElement) {
            if ($partElement->localName === 'extLst') {
                continue;
            }

            $part = $this->tableStylePartMetadata($partElement);
            if ($part !== []) {
                $parts[$partElement->localName] = $part;
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableStylePartMetadata(\DOMElement $partElement): array
    {
        $part = [];
        $textStyle = $this->firstChildElement($partElement, 'tcTxStyle');
        if ($textStyle instanceof \DOMElement) {
            $metadata = $this->tableTextStyleMetadata($textStyle);
            if ($metadata !== []) {
                $part['text'] = $metadata;
            }
        }

        $cellStyle = $this->firstChildElement($partElement, 'tcStyle');
        if ($cellStyle instanceof \DOMElement) {
            $metadata = $this->tableCellStyleContainerMetadata($cellStyle);
            if ($metadata !== []) {
                $part['cell'] = $metadata;
            }
        }

        $background = $this->firstChildElement($partElement, 'fill') ?? $this->firstChildElement($partElement, 'fillRef');
        if ($background instanceof \DOMElement) {
            $color = $this->drawingColorValue($background);
            if ($color !== '') {
                $part['fillColor'] = $color;
            }
        }

        return $part;
    }

    /**
     * @return list<string>
     */
    private function placeholderBucketKeys(mixed $placeholders): array
    {
        if (!is_array($placeholders)) {
            return [];
        }

        $keys = array_values(array_filter(array_keys($placeholders), static fn (int|string $key): bool => is_string($key) && $key !== ''));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeSummary(\DOMElement $themeRoot): array
    {
        $theme = [];
        $name = trim($themeRoot->getAttribute('name'));
        if ($name !== '') {
            $theme['name'] = $name;
        }

        $colorScheme = $this->themeColorScheme($themeRoot);
        if ($colorScheme !== []) {
            $theme['colorScheme'] = $colorScheme;
        }

        $fontScheme = $this->themeFontScheme($themeRoot);
        if ($fontScheme !== []) {
            $theme['fontScheme'] = $fontScheme;
        }

        $formatScheme = $this->themeFormatScheme($themeRoot);
        if ($formatScheme !== []) {
            $theme['formatScheme'] = $formatScheme;
        }

        return $theme;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeColorScheme(\DOMElement $themeRoot): array
    {
        $colorScheme = $this->firstDescendantElement($themeRoot, 'clrScheme');
        if (!$colorScheme instanceof \DOMElement) {
            return [];
        }

        $colors = [];
        foreach ($this->childElements($colorScheme, null) as $colorElement) {
            if (!in_array($colorElement->localName, ['dk1', 'lt1', 'dk2', 'lt2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'], true)) {
                continue;
            }

            $value = $this->drawingColorValue($colorElement);
            if ($value !== '') {
                $colors[$colorElement->localName] = $value;
            }
        }

        if ($colors === []) {
            return [];
        }

        $scheme = ['colors' => $colors];
        $name = trim($colorScheme->getAttribute('name'));
        if ($name !== '') {
            $scheme = ['name' => $name] + $scheme;
        }

        return $scheme;
    }

    /**
     * @return array<string, string>
     */
    private function themeFontScheme(\DOMElement $themeRoot): array
    {
        $fontScheme = $this->firstDescendantElement($themeRoot, 'fontScheme');
        if (!$fontScheme instanceof \DOMElement) {
            return [];
        }

        $fonts = [];
        $name = trim($fontScheme->getAttribute('name'));
        if ($name !== '') {
            $fonts['name'] = $name;
        }

        foreach (['majorFont' => 'major', 'minorFont' => 'minor'] as $elementName => $prefix) {
            $fontElement = $this->firstChildElement($fontScheme, $elementName);
            if (!$fontElement instanceof \DOMElement) {
                continue;
            }

            foreach (['latin' => 'Latin', 'ea' => 'EastAsia', 'cs' => 'ComplexScript'] as $source => $target) {
                $sourceElement = $this->firstChildElement($fontElement, $source);
                if (!$sourceElement instanceof \DOMElement) {
                    continue;
                }

                $typeface = trim($sourceElement->getAttribute('typeface'));
                if ($typeface !== '') {
                    $fonts[$prefix . $target] = $typeface;
                }
            }
        }

        return $fonts;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeFormatScheme(\DOMElement $themeRoot): array
    {
        $formatScheme = $this->firstDescendantElement($themeRoot, 'fmtScheme');
        if (!$formatScheme instanceof \DOMElement) {
            return [];
        }

        $scheme = [];
        $name = trim($formatScheme->getAttribute('name'));
        if ($name !== '') {
            $scheme['name'] = $name;
        }

        $fillStyles = $this->themeFillStyles($this->firstChildElement($formatScheme, 'fillStyleLst'));
        if ($fillStyles !== []) {
            $scheme['fillStyleCount'] = count($fillStyles);
            $scheme['fillStyles'] = $fillStyles;
        }

        $lineStyles = $this->themeLineStyles($this->firstChildElement($formatScheme, 'lnStyleLst'));
        if ($lineStyles !== []) {
            $scheme['lineStyleCount'] = count($lineStyles);
            $scheme['lineStyles'] = $lineStyles;
        }

        $effectStyles = $this->themeEffectStyles($this->firstChildElement($formatScheme, 'effectStyleLst'));
        if ($effectStyles !== []) {
            $scheme['effectStyleCount'] = count($effectStyles);
            $scheme['effectStyles'] = $effectStyles;
        }

        $backgroundFillStyles = $this->themeFillStyles($this->firstChildElement($formatScheme, 'bgFillStyleLst'));
        if ($backgroundFillStyles !== []) {
            $scheme['backgroundFillStyleCount'] = count($backgroundFillStyles);
            $scheme['backgroundFillStyles'] = $backgroundFillStyles;
        }

        return $scheme;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function themeFillStyles(?\DOMElement $styleList): array
    {
        if (!$styleList instanceof \DOMElement) {
            return [];
        }

        $styles = [];
        foreach ($this->childElements($styleList, null) as $styleElement) {
            $style = ['type' => $styleElement->localName];
            $color = $this->drawingColorMetadata($styleElement);
            if (is_string($color['color'] ?? null) && $color['color'] !== '') {
                $style['color'] = $color['color'];
                if (is_array($color['transforms'] ?? null) && $color['transforms'] !== []) {
                    $style['colorTransforms'] = $color['transforms'];
                }
            }
            $styles[] = $style;
        }

        return $styles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function themeLineStyles(?\DOMElement $styleList): array
    {
        if (!$styleList instanceof \DOMElement) {
            return [];
        }

        $styles = [];
        foreach ($this->childElements($styleList, 'ln') as $lineElement) {
            $styles[] = $this->tableLineStyleMetadata($lineElement);
        }

        return $styles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function themeEffectStyles(?\DOMElement $styleList): array
    {
        if (!$styleList instanceof \DOMElement) {
            return [];
        }

        $styles = [];
        foreach ($this->childElements($styleList, 'effectStyle') as $styleElement) {
            $style = [];
            $effectTypes = [];
            foreach ($this->childElements($styleElement, null) as $child) {
                $style[$child->localName . 'Present'] = true;
                if ($child->localName !== 'effectLst') {
                    continue;
                }

                foreach ($this->childElements($child, null) as $effectElement) {
                    $effectTypes[] = $effectElement->localName;
                }
            }
            if ($effectTypes !== []) {
                $style['effectTypes'] = array_values(array_unique($effectTypes));
            }
            $styles[] = $style;
        }

        return $styles;
    }

    private function optionalPackageXml(ZipPackage $package, string $partName, string $label): ?\DOMDocument
    {
        try {
            return $this->loadPackageXml($package, $partName, $label, false);
        } catch (\InvalidArgumentException | \RuntimeException) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function commentAuthors(ZipPackage $package): array
    {
        if (!$package->has(self::COMMENT_AUTHORS_PART)) {
            return [];
        }

        $document = $this->optionalPackageXml($package, self::COMMENT_AUTHORS_PART, 'PPTX comment authors');
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $authors = [];
        foreach ($this->childElements($root, 'cmAuthor') as $authorElement) {
            $id = $authorElement->getAttribute('id');
            if ($id === '') {
                continue;
            }

            $author = [];
            foreach (['name', 'initials', 'lastIdx'] as $attribute) {
                $value = $authorElement->getAttribute($attribute);
                if ($value !== '') {
                    $author[$attribute] = $value;
                }
            }
            $authors[$id] = $author;
        }

        return $authors;
    }

    /**
     * @param array<string, array<string, string>> $commentAuthors
     * @return list<array<string, mixed>>
     */
    private function slideComments(ZipPackage $package, OpcRelationships $slideRelationships, array $commentAuthors): array
    {
        $comments = [];
        foreach ($slideRelationships->all() as $relationship) {
            if (!str_ends_with($relationship->type, '/comments') || $relationship->isExternal()) {
                continue;
            }

            $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
            if ($partName === null) {
                continue;
            }
            $document = $this->optionalPackageXml($package, $partName, 'PPTX slide comments');
            if (!$document instanceof \DOMDocument) {
                continue;
            }

            $root = XmlHtmlDom::rootElement($document);
            if (!$root instanceof \DOMElement) {
                continue;
            }

            $index = 0;
            foreach ($root->getElementsByTagName('*') as $commentElement) {
                if (!$commentElement instanceof \DOMElement || !in_array($commentElement->localName, ['cm', 'comment'], true)) {
                    continue;
                }

                $index++;
                $authorId = $commentElement->getAttribute('authorId');
                $author = $commentAuthors[$authorId] ?? [];
                $text = $this->commentText($commentElement);
                if ($text === '') {
                    continue;
                }

                $comment = [
                    'id' => $commentElement->getAttribute('idx') !== '' ? $commentElement->getAttribute('idx') : ltrim($partName, '/') . '#' . $index,
                    'authorId' => $authorId,
                    'author' => $author['name'] ?? '',
                    'initials' => $author['initials'] ?? '',
                    'date' => $commentElement->getAttribute('dt'),
                    'text' => $text,
                    'partName' => ltrim($partName, '/'),
                ];

                $position = $this->firstChildElement($commentElement, 'pos');
                if ($position instanceof \DOMElement) {
                    foreach (['x', 'y'] as $attribute) {
                        $value = $this->integerAttribute($position, $attribute);
                        if ($value !== null) {
                            $comment[$attribute] = $value;
                        }
                    }
                }

                $comments[] = $comment;
            }
        }

        return $comments;
    }

    private function commentText(\DOMElement $commentElement): string
    {
        $textElement = $this->firstChildElement($commentElement, 'text');
        if ($textElement instanceof \DOMElement) {
            return trim($this->allDescendantText($textElement));
        }

        return trim($this->drawingText($commentElement));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slideBackgrounds(ZipPackage $package, \DOMDocument $document, OpcRelationships $slideRelationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'sld');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $commonSlideData = $this->firstPresentationChildElement($root, 'cSld');
        $background = $commonSlideData instanceof \DOMElement ? $this->firstPresentationChildElement($commonSlideData, 'bg') : null;
        if (!$background instanceof \DOMElement) {
            return [];
        }

        $backgrounds = [];
        foreach ($background->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isDrawingElement($element, 'blip')) {
                continue;
            }

            $backgrounds[] = $this->backgroundMediaReview($package, $element, $slideRelationships);
        }

        return $backgrounds;
    }

    /**
     * @return array<string, mixed>
     */
    private function backgroundMediaReview(ZipPackage $package, \DOMElement $blip, OpcRelationships $slideRelationships): array
    {
        $relationshipAttribute = 'embed';
        $relationshipId = $this->relationshipAttribute($blip, $relationshipAttribute);
        if ($relationshipId === null) {
            $relationshipAttribute = 'link';
            $relationshipId = $this->relationshipAttribute($blip, $relationshipAttribute);
        }

        $review = [
            'relationshipId' => $relationshipId ?? '',
            'relationshipAttribute' => $relationshipAttribute,
            'relationshipType' => '',
            'target' => '',
            'external' => false,
            'exists' => false,
            'issues' => [],
        ];

        if ($relationshipId === null) {
            $review['issues'][] = 'missing-background-relationship-id';

            return $review;
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $review['issues'][] = 'unknown-background-relationship';

            return $review;
        }

        $review['relationshipType'] = $relationship->type;
        $review['target'] = $relationship->target;
        $review['external'] = $relationship->isExternal();
        if ($relationship->isExternal()) {
            $review['externalTargetPolicy'] = $relationship->externalTargetPreflight();
            $review['issues'][] = 'external-background-target';

            return $review;
        }

        if ($relationshipAttribute === 'link') {
            $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
            if ($partName === null) {
                $review['issues'][] = 'invalid-background-target';

                return $review;
            }
            $partName = ltrim($partName, '/');
            $review['partName'] = $partName;
            if (in_array($partName, $package->names(), true)) {
                $review['exists'] = true;
            } else {
                $review['issues'][] = 'missing-background-image-part';
            }
            $review['issues'][] = 'linked-background-target';

            return $review;
        }

        $mediaPart = $this->upstreamPictureMediaPart($relationship->target);
        $review['partName'] = $mediaPart;
        if (!in_array($mediaPart, $package->names(), true)) {
            $review['issues'][] = 'missing-background-image-part';

            return $review;
        }

        $review['exists'] = true;

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideTransitionMetadata(\DOMDocument $document, OpcRelationships $slideRelationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'sld');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $transition = $this->firstPresentationChildElement($root, 'transition');
        if (!$transition instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $effect = $this->transitionEffectMetadata($transition);
        if ($effect !== []) {
            $metadata = $effect;
        } else {
            $metadata['type'] = 'unknown';
        }

        $speed = trim($transition->getAttribute('spd'));
        if ($speed !== '') {
            $metadata['speed'] = $speed;
        }

        if ($transition->hasAttribute('advClick')) {
            $metadata['advanceOnClick'] = $this->xmlBooleanValue($transition->getAttribute('advClick'));
        }

        $advanceAfter = $this->integerAttribute($transition, 'advTm');
        if ($advanceAfter !== null) {
            $metadata['advanceAfterMilliseconds'] = $advanceAfter;
        }

        $sound = $this->transitionSoundMetadata($transition, $slideRelationships);
        if ($sound !== []) {
            $metadata['sound'] = $sound;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionEffectMetadata(\DOMElement $transition): array
    {
        foreach ($this->childElements($transition, null) as $child) {
            if (
                !self::isPresentationNamespace($child->namespaceURI)
                || in_array($child->localName, ['sndAc', 'extLst'], true)
            ) {
                continue;
            }

            $metadata = ['type' => $child->localName];
            $attributes = $this->elementStringAttributes($child, [
                'dir',
                'orient',
                'thruBlk',
                'spokes',
                'fadeColor',
            ]);
            if ($attributes !== []) {
                $metadata['attributes'] = $attributes;
            }

            return $metadata;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionSoundMetadata(\DOMElement $transition, OpcRelationships $slideRelationships): array
    {
        $soundAction = $this->firstPresentationChildElement($transition, 'sndAc');
        if (!$soundAction instanceof \DOMElement) {
            return [];
        }

        $startSound = $this->firstPresentationChildElement($soundAction, 'stSnd');
        if ($startSound instanceof \DOMElement) {
            $metadata = ['action' => 'start'];
            if ($startSound->hasAttribute('loop')) {
                $metadata['loop'] = $this->xmlBooleanValue($startSound->getAttribute('loop'));
            }

            $sound = $this->firstPresentationChildElement($startSound, 'snd');
            if ($sound instanceof \DOMElement) {
                $name = trim($sound->getAttribute('name'));
                if ($name !== '') {
                    $metadata['name'] = $name;
                }

                foreach (['embed', 'link'] as $relationshipAttribute) {
                    $relationshipId = $this->relationshipId($sound, $relationshipAttribute);
                    if ($relationshipId === '') {
                        continue;
                    }

                    return array_replace(
                        $metadata,
                        $this->transitionSoundRelationshipMetadata($slideRelationships, $relationshipAttribute, $relationshipId)
                    );
                }
            }

            return $metadata;
        }

        if ($this->firstPresentationChildElement($soundAction, 'endSnd') instanceof \DOMElement) {
            return ['action' => 'stop'];
        }

        return ['action' => 'unknown'];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionSoundRelationshipMetadata(OpcRelationships $slideRelationships, string $relationshipAttribute, string $relationshipId): array
    {
        $metadata = [
            'relationshipId' => $relationshipId,
            'relationshipAttribute' => $relationshipAttribute,
            'relationshipType' => '',
            'target' => '',
            'external' => false,
            'issues' => [],
        ];

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $metadata['issues'][] = 'unknown-transition-sound-relationship';

            return $metadata;
        }

        $metadata['relationshipType'] = $relationship->type;
        $metadata['target'] = $relationship->target;
        $metadata['external'] = $relationship->isExternal();
        if ($relationship->isExternal()) {
            $metadata['externalTargetPolicy'] = $relationship->externalTargetPreflight();

            return $metadata;
        }

        $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
        if ($partName === null) {
            $metadata['issues'][] = 'invalid-transition-sound-target';

            return $metadata;
        }

        $metadata['partName'] = ltrim($partName, '/');

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function slideTimingMetadata(\DOMDocument $document): array
    {
        $timing = $this->slideTimingElement($document);
        if (!$timing instanceof \DOMElement) {
            return [];
        }

        $metadata = [
            'timeNodeCount' => 0,
            'parallelCount' => 0,
            'sequenceCount' => 0,
            'conditionCount' => 0,
        ];
        $nodeTypes = [];
        foreach ($timing->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !self::isPresentationNamespace($element->namespaceURI)) {
                continue;
            }

            if ($element->localName === 'cTn') {
                $metadata['timeNodeCount']++;
                $nodeType = trim($element->getAttribute('nodeType'));
                if ($nodeType !== '') {
                    $nodeTypes[$nodeType] = true;
                }
                continue;
            }

            if ($element->localName === 'par') {
                $metadata['parallelCount']++;
                continue;
            }

            if ($element->localName === 'seq') {
                $metadata['sequenceCount']++;
                continue;
            }

            if ($element->localName === 'cond') {
                $metadata['conditionCount']++;
            }
        }

        if ($nodeTypes !== []) {
            $metadata['nodeTypes'] = array_keys($nodeTypes);
            sort($metadata['nodeTypes'], SORT_STRING);
        }

        $rootTimeNode = $this->firstDescendantElement($timing, 'cTn');
        if ($rootTimeNode instanceof \DOMElement && self::isPresentationNamespace($rootTimeNode->namespaceURI)) {
            $metadata['rootTimeNode'] = $this->timingNodeMetadata($rootTimeNode);
        }

        return $metadata;
    }

    private function slideTimingElement(\DOMDocument $document): ?\DOMElement
    {
        $root = XmlHtmlDom::rootElement($document, 'sld');
        if (!$root instanceof \DOMElement) {
            return null;
        }

        return $this->firstPresentationChildElement($root, 'timing');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slideAnimationsMetadata(\DOMDocument $document): array
    {
        $timing = $this->slideTimingElement($document);
        if (!$timing instanceof \DOMElement) {
            return [];
        }

        $animations = [];
        foreach ($timing->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isAnimationBehaviorElement($element)) {
                continue;
            }

            $animations[] = $this->animationBehaviorMetadata($element);
        }

        return $animations;
    }

    private function isAnimationBehaviorElement(\DOMElement $element): bool
    {
        return self::isPresentationNamespace($element->namespaceURI)
            && in_array($element->localName, ['anim', 'animClr', 'animEffect', 'animMotion', 'animRot', 'animScale', 'cmd', 'set'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function animationBehaviorMetadata(\DOMElement $animation): array
    {
        $metadata = ['kind' => $animation->localName];
        $attributes = $this->elementStringAttributes($animation, [
            'transition',
            'filter',
            'calcMode',
            'valueType',
            'from',
            'to',
            'by',
            'path',
            'origin',
            'ptsTypes',
            'cmd',
            'type',
        ]);
        if ($attributes !== []) {
            $metadata['attributes'] = $attributes;
        }

        $commonBehavior = $this->firstPresentationChildElement($animation, 'cBhvr');
        if ($commonBehavior instanceof \DOMElement) {
            $target = $this->animationTargetMetadata($commonBehavior);
            if ($target !== []) {
                $metadata['target'] = $target;
            }

            $timeNode = $this->firstPresentationChildElement($commonBehavior, 'cTn');
            if ($timeNode instanceof \DOMElement) {
                $metadata['behaviorTimeNode'] = $this->timingNodeMetadata($timeNode);
            }
        }

        $trigger = $this->animationAncestorTimingNodeMetadata($animation);
        if ($trigger !== []) {
            $metadata['triggerTimeNode'] = $trigger;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function animationTargetMetadata(\DOMElement $commonBehavior): array
    {
        $targetElement = $this->firstPresentationChildElement($commonBehavior, 'tgtEl');
        if (!$targetElement instanceof \DOMElement) {
            return [];
        }

        foreach ($this->childElements($targetElement, null) as $target) {
            if (!self::isPresentationNamespace($target->namespaceURI)) {
                continue;
            }

            $metadata = ['type' => $target->localName];
            $attributes = $this->elementStringAttributes($target, ['spid', 'id']);
            if ($attributes !== []) {
                $metadata['attributes'] = $attributes;
            }

            $range = $this->firstPresentationChildElement($target, 'charRg')
                ?? $this->firstPresentationChildElement($target, 'pRg');
            if ($range instanceof \DOMElement) {
                $rangeAttributes = $this->elementStringAttributes($range, ['st', 'end']);
                if ($rangeAttributes !== []) {
                    $metadata['range'] = $rangeAttributes;
                }
            }

            return $metadata;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function animationAncestorTimingNodeMetadata(\DOMElement $animation): array
    {
        $fallback = [];
        $node = $animation->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && self::isPresentationNamespace($node->namespaceURI) && $node->localName === 'cTn') {
                $metadata = $this->timingNodeMetadata($node);
                if ($fallback === []) {
                    $fallback = $metadata;
                }
                if (isset($metadata['presetClass']) || isset($metadata['presetID']) || isset($metadata['nodeType'])) {
                    return $metadata;
                }
            }
            $node = $node->parentNode;
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function timingNodeMetadata(\DOMElement $timeNode): array
    {
        $metadata = [];
        foreach (['id', 'presetID', 'presetSubtype', 'grpId'] as $attribute) {
            $value = $this->integerAttribute($timeNode, $attribute);
            if ($value !== null) {
                $metadata[$attribute] = $value;
            }
        }

        foreach (['dur', 'restart', 'fill', 'nodeType', 'presetClass', 'accel', 'decel', 'autoRev'] as $attribute) {
            $value = trim($timeNode->getAttribute($attribute));
            if ($value !== '') {
                $metadata[$attribute] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private function elementStringAttributes(\DOMElement $element, array $names): array
    {
        $attributes = [];
        foreach ($names as $name) {
            $value = trim($element->getAttribute($name));
            if ($value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slideSpeakerNotes(ZipPackage $package, OpcRelationships $slideRelationships): array
    {
        $notes = [];
        foreach ($slideRelationships->all() as $relationship) {
            if (!str_ends_with($relationship->type, '/notesSlide') || $relationship->isExternal()) {
                continue;
            }

            $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
            if ($partName === null) {
                continue;
            }
            $document = $this->optionalPackageXml($package, $partName, 'PPTX notes slide');
            if (!$document instanceof \DOMDocument) {
                continue;
            }

            $root = XmlHtmlDom::rootElement($document, 'notes');
            if (!$root instanceof \DOMElement) {
                continue;
            }

            $noteRelationships = $this->optionalRelationshipsOrEmpty($package, $partName);
            $blocks = [];
            $texts = [];
            $spTree = $this->shapeTree($root);
            if ($spTree instanceof \DOMElement) {
                foreach ($this->childElements($spTree, 'sp') as $shapeElement) {
                    if ($this->isNotesNonBodyPlaceholder($shapeElement)) {
                        continue;
                    }

                    $textBody = $this->firstChildElement($shapeElement, 'txBody');
                    if (!$textBody instanceof \DOMElement) {
                        continue;
                    }

                    $paragraphs = $this->parseParagraphs($textBody, $noteRelationships);
                    if (!$this->paragraphsContainText($paragraphs)) {
                        continue;
                    }

                    foreach ($paragraphs as $paragraph) {
                        $text = trim((string) ($paragraph['text'] ?? ''));
                        if ($text !== '') {
                            $texts[] = $text;
                        }
                    }
                    array_push($blocks, ...$this->paragraphsToBlocks($paragraphs));
                }
            }

            if ($blocks === []) {
                continue;
            }

            $notes[] = [
                'relationshipId' => $relationship->id,
                'relationshipType' => $relationship->type,
                'target' => $relationship->target,
                'partName' => ltrim($partName, '/'),
                'text' => implode("\n", $texts),
                'blockCount' => count($blocks),
                'blocks' => $blocks,
            ];
        }

        return $notes;
    }

    private function isNotesNonBodyPlaceholder(\DOMElement $shapeElement): bool
    {
        $placeholder = $this->placeholderElement($shapeElement);
        if (!$placeholder instanceof \DOMElement) {
            return false;
        }

        $type = $placeholder->getAttribute('type') !== '' ? $placeholder->getAttribute('type') : 'obj';

        return in_array($type, ['sldImg', 'sldNum', 'dt', 'hdr', 'ftr'], true);
    }

    /**
     * @param list<array<string, mixed>> $notes
     * @return list<array<string, mixed>>
     */
    private function speakerNoteReviews(array $notes): array
    {
        return array_map(fn (array $note): array => $this->speakerNoteReview($note), $notes);
    }

    /**
     * @param array<string, mixed> $note
     * @return array<string, mixed>
     */
    private function speakerNoteReview(array $note): array
    {
        unset($note['blocks']);

        return $note;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function collectLinkReviews(array $blocks): array
    {
        $links = [];
        $seen = [];
        foreach ($blocks as $block) {
            foreach ($this->collectLinkReviewsFromNode($block) as $record) {
                $key = (string) ($record['relationshipId'] ?? '') . "\0" . (string) ($record['url'] ?? '') . "\0" . (string) ($record['title'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $links[] = $record;
            }
        }

        return $links;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectLinkReviewsFromNode(AstNode $node): array
    {
        $links = [];
        if ($node->type === 'link') {
            $record = [];
            foreach ([
                'url',
                'title',
                'relationshipId',
                'relationshipType',
                'relationshipIssue',
                'hyperlinkKind',
                'target',
                'targetMode',
                'external',
                'externalTargetAllowed',
                'externalTargetIssues',
                'externalTargetScheme',
                'action',
                'targetFrame',
            ] as $attribute) {
                $value = $node->attr($attribute);
                if ($value !== null && $value !== '' && $value !== []) {
                    $record[$attribute] = $value;
                }
            }
            if ($record !== []) {
                $links[] = $record;
            }
        }
        foreach ($node->children as $child) {
            array_push($links, ...$this->collectLinkReviewsFromNode($child));
        }

        return $links;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function collectChartReviews(array $blocks): array
    {
        $charts = [];
        $seen = [];
        foreach ($blocks as $block) {
            foreach ($this->collectChartReviewsFromNode($block) as $record) {
                $issues = is_array($record['issues'] ?? null)
                    ? array_map(static fn ($issue): string => (string) $issue, $record['issues'])
                    : [];
                $key = (string) ($record['graphicUri'] ?? '') . "\0"
                    . (string) ($record['relationshipId'] ?? '') . "\0"
                    . (string) ($record['partName'] ?? '') . "\0"
                    . (string) ($record['title'] ?? '') . "\0"
                    . implode("\0", $issues);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $charts[] = $record;
            }
        }

        return $charts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectChartReviewsFromNode(AstNode $node): array
    {
        $charts = [];
        $record = $node->attr('pptxChart');
        if (is_array($record)) {
            $charts[] = $record;
        }
        foreach ($node->children as $child) {
            array_push($charts, ...$this->collectChartReviewsFromNode($child));
        }

        return $charts;
    }

    private function firstRelationshipWithTypeSuffix(OpcRelationships $relationships, string $suffix): ?OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_ends_with($relationship->type, $suffix)) {
                return $relationship;
            }
        }

        return null;
    }

    private function reviewRelationshipPart(OpcRelationships $relationships, OpcRelationship $relationship): ?string
    {
        try {
            return OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
        } catch (\InvalidArgumentException | \RuntimeException) {
            return null;
        }
    }

    /**
     * @return array<string, array{blocks:list<AstNode>, sourceShape:array<string, mixed>}>
     */
    private function placeholderBlocksByKey(\DOMElement $root): array
    {
        $spTree = $this->shapeTree($root);
        if (!$spTree instanceof \DOMElement) {
            return [];
        }

        $placeholders = [];
        $zOrder = 0;
        foreach ($this->childElements($spTree, 'sp') as $shapeElement) {
            $zOrder++;
            $keys = $this->placeholderLookupKeys($shapeElement);
            if ($keys === []) {
                continue;
            }
            $textBody = $this->firstChildElement($shapeElement, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                continue;
            }
            $paragraphs = $this->parseParagraphs($textBody);
            if (!$this->paragraphsContainText($paragraphs)) {
                continue;
            }
            $blocks = $this->paragraphsToBlocks($paragraphs);
            if ($blocks === []) {
                continue;
            }
            $record = [
                'blocks' => $blocks,
                'sourceShape' => $this->shapeMetadata($shapeElement, $zOrder),
            ];
            foreach ($keys as $key) {
                $placeholders[$key] ??= $record;
            }
        }

        return $placeholders;
    }

    /**
     * @return list<AstNode>
     */
    private function inheritedPlaceholderBlocks(\DOMElement $shapeElement, array $slideContext): array
    {
        $keys = $this->placeholderLookupKeys($shapeElement);
        if ($keys === []) {
            return [];
        }

        foreach ([
            'layoutPlaceholders' => ['source' => 'layout', 'sourcePartKey' => 'layoutPart'],
            'masterPlaceholders' => ['source' => 'master', 'sourcePartKey' => 'masterPart'],
        ] as $bucket => $source) {
            $placeholders = $slideContext[$bucket] ?? [];
            if (!is_array($placeholders)) {
                continue;
            }
            foreach ($keys as $key) {
                $record = $placeholders[$key] ?? null;
                $blocks = is_array($record) && is_array($record['blocks'] ?? null) ? $record['blocks'] : [];
                if ($blocks !== []) {
                    $sourceShape = is_array($record['sourceShape'] ?? null) ? $record['sourceShape'] : [];
                    return $this->withInheritedPlaceholderMetadata($blocks, [
                        'source' => $source['source'],
                        'sourcePart' => (string) ($slideContext[$source['sourcePartKey']] ?? ''),
                        'lookupKey' => $key,
                        'lookupKeys' => $keys,
                        'sourceShape' => $sourceShape,
                    ]);
                }
            }
        }

        return [];
    }

    /**
     * @param list<AstNode> $blocks
     * @param array<string, mixed> $metadata
     * @return list<AstNode>
     */
    private function withInheritedPlaceholderMetadata(array $blocks, array $metadata): array
    {
        return array_map(
            static function (AstNode $block) use ($metadata): AstNode {
                $attrs = $block->attrs;
                $existing = $attrs['pptxPlaceholderInheritance'] ?? [];
                $attrs['pptxPlaceholderInheritance'] = is_array($existing)
                    ? array_replace($metadata, $existing)
                    : $metadata;

                return new AstNode($block->type, $attrs, $block->children);
            },
            $blocks
        );
    }

    /**
     * @return list<string>
     */
    private function placeholderLookupKeys(\DOMElement $shapeElement): array
    {
        $placeholder = $this->placeholderElement($shapeElement);
        if (!$placeholder instanceof \DOMElement) {
            return [];
        }

        $type = $placeholder->getAttribute('type') !== '' ? $placeholder->getAttribute('type') : 'obj';
        $index = $placeholder->getAttribute('idx');
        $keys = [];
        if ($type !== '' && $index !== '') {
            $keys[] = 'type:' . $type . ';idx:' . $index;
        }
        if ($index !== '') {
            $keys[] = 'idx:' . $index;
        }
        if ($type !== '') {
            $keys[] = 'type:' . $type;
        }

        return array_values(array_unique($keys));
    }

    private function placeholderElement(\DOMElement $shapeElement): ?\DOMElement
    {
        foreach ($this->childElements($shapeElement, null) as $child) {
            if (!self::isPresentationNamespace($child->namespaceURI) || !str_starts_with($child->localName, 'nv')) {
                continue;
            }

            $nonVisualProperties = $this->firstPresentationChildElement($child, 'nvPr');

            return $nonVisualProperties instanceof \DOMElement ? $this->firstPresentationChildElement($nonVisualProperties, 'ph') : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @param array<string, mixed> $tableStyles
     * @param list<array<string, mixed>> $imageIssues
     * @param list<array<string, mixed>> $shapeIssues
     * @param list<array<string, string|bool|array<string, mixed>>> $richMedia
     * @return list<AstNode>
     */
    private function shapeToBlocks(ZipPackage $package, \DOMElement $shapeElement, OpcRelationships $slideRelationships, array $slideContext, array $tableStyles, ?string $presentationNamespace, ?string $relationshipNamespace, ?string $drawingNamespace, int $zOrder, array &$imageIssues, array &$shapeIssues, array &$richMedia): array
    {
        if ($shapeElement->localName === 'grpSp' && $this->readGroupedDrawableDescendants()) {
            return $this->groupShapeToBlocks($package, $shapeElement, $slideRelationships, $slideContext, $tableStyles, $presentationNamespace, $relationshipNamespace, $drawingNamespace, $zOrder, $imageIssues, $shapeIssues, $richMedia);
        }

        if ($shapeElement->localName === 'sp') {
            $this->appendRichMediaReviews($richMedia, $shapeElement, $slideRelationships, $zOrder);
            $textBody = $this->firstChildElementForOuterPrefix($shapeElement, 'p', 'txBody', $presentationNamespace);
            if (!$textBody instanceof \DOMElement) {
                return [];
            }

            $paragraphs = $this->parseParagraphs($textBody, $slideRelationships, $drawingNamespace);

            return $this->withShapeMetadata(
                $this->paragraphsToBlocks($paragraphs),
                $shapeElement,
                $zOrder
            );
        }

        if ($shapeElement->localName === 'pic') {
            $this->appendRichMediaReviews($richMedia, $shapeElement, $slideRelationships, $zOrder);
            $image = $this->pictureNode($package, $shapeElement, $slideRelationships, $presentationNamespace, $relationshipNamespace, $drawingNamespace, $imageIssues);

            return $this->withShapeMetadata($image instanceof AstNode ? [new AstNode('paragraph', [], [$image])] : [], $shapeElement, $zOrder);
        }

        if ($shapeElement->localName !== 'graphicFrame') {
            return $this->unsupportedDrawableShapeBlocks($shapeElement, $slideRelationships, $zOrder, $shapeIssues, $richMedia);
        }

        $graphicData = $this->graphicDataElement($shapeElement, $drawingNamespace);
        if (!$graphicData instanceof \DOMElement) {
            return [];
        }

        if (!$graphicData->hasAttribute('uri')) {
            return $this->withShapeMetadata([$this->paragraph('[Graphic: no-uri]')], $shapeElement, $zOrder);
        }

        $uri = $graphicData->getAttribute('uri');
        if (str_contains($uri, 'table')) {
            $table = $this->firstChildElementForOuterPrefix($graphicData, 'a', 'tbl', $drawingNamespace);
            $tableNode = $table instanceof \DOMElement ? $this->tableNode($table, $tableStyles, $slideContext, $drawingNamespace) : null;

            return $this->withShapeMetadata($tableNode instanceof AstNode ? [$tableNode] : [], $shapeElement, $zOrder);
        }
        if (str_contains($uri, 'diagram')) {
            $diagram = $this->diagramNode($package, $graphicData, $slideRelationships, $relationshipNamespace);

            return $this->withShapeMetadata($diagram instanceof AstNode ? [$diagram] : [], $shapeElement, $zOrder);
        }
        if (str_contains($uri, 'chart')) {
            $chart = $this->chartNode($package, $graphicData, $slideRelationships);

            return $this->withShapeMetadata($chart instanceof AstNode ? [$chart] : [], $shapeElement, $zOrder);
        }

        return $this->withShapeMetadata([$this->paragraph('[Graphic: other: ' . $uri . ']')], $shapeElement, $zOrder);
    }

    /**
     * @param array<string, mixed> $slideContext
     * @param array<string, mixed> $tableStyles
     * @param list<array<string, mixed>> $imageIssues
     * @param list<array<string, mixed>> $shapeIssues
     * @param list<array<string, string|bool|array<string, mixed>>> $richMedia
     * @return list<AstNode>
     */
    private function groupShapeToBlocks(ZipPackage $package, \DOMElement $groupShape, OpcRelationships $slideRelationships, array $slideContext, array $tableStyles, ?string $presentationNamespace, ?string $relationshipNamespace, ?string $drawingNamespace, int $zOrder, array &$imageIssues, array &$shapeIssues, array &$richMedia): array
    {
        $shapeIssueCountBefore = count($shapeIssues);
        $richMediaCountBefore = count($richMedia);
        $blocks = [];
        foreach ($this->childElements($groupShape, null) as $child) {
            if (!$this->isDrawableShapeElement($child, $presentationNamespace)) {
                continue;
            }

            foreach ($this->shapeToBlocks($package, $child, $slideRelationships, $slideContext, $tableStyles, $presentationNamespace, $relationshipNamespace, $drawingNamespace, $zOrder, $imageIssues, $shapeIssues, $richMedia) as $block) {
                $blocks[] = $block;
            }
        }

        if ($blocks === [] && count($shapeIssues) === $shapeIssueCountBefore && count($richMedia) === $richMediaCountBefore) {
            return $this->unsupportedDrawableShapeBlocks($groupShape, $slideRelationships, $zOrder, $shapeIssues, $richMedia);
        }

        return $blocks;
    }

    private function readGroupedDrawableDescendants(): bool
    {
        return ($this->options['pptxReadGroupedDrawableDescendants'] ?? false) === true;
    }

    /**
     * @param list<array<string, mixed>> $shapeIssues
     * @param list<array<string, string|bool|array<string, mixed>>> $richMedia
     * @return list<AstNode>
     */
    private function unsupportedDrawableShapeBlocks(\DOMElement $shapeElement, OpcRelationships $slideRelationships, int $zOrder, array &$shapeIssues, array &$richMedia): array
    {
        $richMediaCountBefore = count($richMedia);
        $hasRichMedia = $this->appendRichMediaReviews($richMedia, $shapeElement, $slideRelationships, $zOrder);
        if ($shapeElement->localName === 'grpSp') {
            $shapeIssue = array_replace(
                ['issue' => 'unsupported-drawable-shape'],
                $this->shapeMetadata($shapeElement, $zOrder),
                ['pptxGroup' => $this->groupedShapeReview($shapeElement, $slideRelationships)]
            );
            $richMediaReferenceCount = count($richMedia) - $richMediaCountBefore;
            if ($richMediaReferenceCount > 0) {
                $shapeIssue['richMediaReferenceCount'] = $richMediaReferenceCount;
            }
            $shapeIssues[] = $shapeIssue;
        } elseif (!$hasRichMedia) {
            $shapeIssue = array_replace(
                ['issue' => 'unsupported-drawable-shape'],
                $this->shapeMetadata($shapeElement, $zOrder)
            );
            $relationshipReviews = $this->shapeRelationshipReviews($shapeElement, $slideRelationships);
            if ($relationshipReviews !== []) {
                $shapeIssue['relationshipIds'] = array_map(
                    static fn (array $relationship): string => (string) ($relationship['relationshipId'] ?? ''),
                    $relationshipReviews
                );
                $shapeIssue['relationships'] = $relationshipReviews;
            }

            $shapeIssues[] = $shapeIssue;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupedShapeReview(\DOMElement $groupShape, OpcRelationships $slideRelationships): array
    {
        $directChildren = [];
        $countsByElement = [];
        $textSamples = [];
        $relationshipReviews = [];
        $relationshipIds = [];
        $descendantShapeCount = 0;

        foreach ($this->childElements($groupShape, null) as $child) {
            if (!$this->isGroupedShapeDrawable($child)) {
                continue;
            }

            $directChildren[] = $this->groupedShapeChildReview($child, 1);
        }

        $this->collectGroupedShapeReview(
            $groupShape,
            0,
            $slideRelationships,
            $countsByElement,
            $textSamples,
            $relationshipReviews,
            $relationshipIds,
            $descendantShapeCount
        );
        ksort($countsByElement, SORT_STRING);

        $review = [
            'directChildShapeCount' => count($directChildren),
            'descendantShapeCount' => $descendantShapeCount,
            'descendantShapeTypes' => $countsByElement,
            'directChildren' => $directChildren,
        ];

        if ($textSamples !== []) {
            $review['textSamples'] = $textSamples;
        }
        if ($relationshipReviews !== []) {
            $review['relationshipIds'] = array_values($relationshipIds);
            $review['relationships'] = $relationshipReviews;
        }

        return $review;
    }

    /**
     * @param array<string, int> $countsByElement
     * @param list<array<string, mixed>> $textSamples
     * @param list<array<string, mixed>> $relationshipReviews
     * @param list<string> $relationshipIds
     */
    private function collectGroupedShapeReview(\DOMElement $element, int $depth, OpcRelationships $slideRelationships, array &$countsByElement, array &$textSamples, array &$relationshipReviews, array &$relationshipIds, int &$descendantShapeCount): void
    {
        foreach ($this->childElements($element, null) as $child) {
            if (!$this->isGroupedShapeDrawable($child)) {
                continue;
            }

            $descendantShapeCount++;
            $countsByElement[$child->localName] = ($countsByElement[$child->localName] ?? 0) + 1;
            $text = $child->localName === 'grpSp' ? '' : trim($this->drawingText($child));
            if ($text !== '' && count($textSamples) < 8) {
                $textSamples[] = [
                    'depth' => $depth + 1,
                    'element' => $child->localName,
                    'text' => $text,
                ];
            }

            foreach ($this->shapeRelationshipReviews($child, $slideRelationships) as $relationshipReview) {
                $relationshipId = (string) ($relationshipReview['relationshipId'] ?? '');
                if ($relationshipId === '' || in_array($relationshipId, $relationshipIds, true)) {
                    continue;
                }
                $relationshipIds[] = $relationshipId;
                if (count($relationshipReviews) < 16) {
                    $relationshipReviews[] = $relationshipReview;
                }
            }

            $this->collectGroupedShapeReview(
                $child,
                $depth + 1,
                $slideRelationships,
                $countsByElement,
                $textSamples,
                $relationshipReviews,
                $relationshipIds,
                $descendantShapeCount
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function groupedShapeChildReview(\DOMElement $shapeElement, int $depth): array
    {
        $review = [
            'depth' => $depth,
            'element' => $shapeElement->localName,
        ];

        $properties = $this->nonVisualDrawingProperties($shapeElement);
        if ($properties instanceof \DOMElement) {
            foreach (['id', 'name', 'descr', 'title'] as $attribute) {
                $value = $properties->getAttribute($attribute);
                if ($value !== '') {
                    $review[$attribute] = $value;
                }
            }
            if ($properties->hasAttribute('hidden')) {
                $review['hidden'] = $this->xmlBooleanValue($properties->getAttribute('hidden'));
            }
        }

        $text = $shapeElement->localName === 'grpSp' ? '' : trim($this->drawingText($shapeElement));
        if ($text !== '') {
            $review['text'] = $text;
        }

        if ($shapeElement->localName === 'grpSp') {
            $childCount = 0;
            foreach ($this->childElements($shapeElement, null) as $child) {
                if ($this->isGroupedShapeDrawable($child)) {
                    $childCount++;
                }
            }
            $review['directChildShapeCount'] = $childCount;
        }

        return $review;
    }

    private function isGroupedShapeDrawable(\DOMElement $element): bool
    {
        return self::isPresentationNamespace($element->namespaceURI)
            && in_array($element->localName, ['sp', 'pic', 'graphicFrame', 'grpSp', 'cxnSp', 'contentPart'], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shapeRelationshipReviews(\DOMElement $shapeElement, OpcRelationships $slideRelationships): array
    {
        $reviews = [];
        $seen = [];
        foreach ($this->shapeAndDescendantElements($shapeElement) as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            foreach (['embed', 'link', 'id'] as $relationshipAttribute) {
                $relationshipId = $this->relationshipId($element, $relationshipAttribute);
                if ($relationshipId === '' || isset($seen[$relationshipId])) {
                    continue;
                }
                $seen[$relationshipId] = true;

                $review = [
                    'relationshipId' => $relationshipId,
                    'relationshipAttribute' => $relationshipAttribute,
                    'sourceElement' => $element->localName,
                    'relationshipFound' => false,
                ];
                $relationship = $slideRelationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    $review['relationshipFound'] = true;
                    $review['relationshipType'] = $relationship->type;
                    $review['target'] = $relationship->target;
                    $review['external'] = $relationship->isExternal();
                    if (!$relationship->isExternal()) {
                        $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
                        if ($partName !== null) {
                            $review['partName'] = ltrim($partName, '/');
                        }
                    }
                }
                $reviews[] = $review;
            }
        }

        return $reviews;
    }

    /**
     * @return list<\DOMElement>
     */
    private function shapeAndDescendantElements(\DOMElement $shapeElement): array
    {
        $elements = [$shapeElement];
        foreach ($shapeElement->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function isDrawableShapeElement(\DOMElement $element, ?string $presentationNamespace): bool
    {
        return $element->namespaceURI === $presentationNamespace
            && in_array($element->localName, ['sp', 'pic', 'graphicFrame', 'grpSp', 'cxnSp', 'contentPart'], true);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function withShapeMetadata(array $blocks, \DOMElement $shapeElement, int $zOrder): array
    {
        if ($blocks === []) {
            return [];
        }

        $metadata = $this->shapeMetadata($shapeElement, $zOrder);

        return array_map(fn (AstNode $block): AstNode => $this->withShapeMetadataNode($block, $metadata), $blocks);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function withShapeMetadataNode(AstNode $node, array $metadata): AstNode
    {
        $attrs = $node->attrs;
        $existing = $attrs['pptxShape'] ?? [];
        $attrs['pptxShape'] = is_array($existing) ? array_replace($metadata, $existing) : $metadata;

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            if (in_array($child->type, ['image', 'link'], true)) {
                $children[] = $this->withShapeMetadataNode($child, $metadata);
                $changed = true;
                continue;
            }

            $children[] = $child;
        }

        return new AstNode($node->type, $attrs, $changed ? $children : $node->children);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeMetadata(\DOMElement $shapeElement, int $zOrder): array
    {
        $metadata = [
            'element' => $shapeElement->localName,
            'zOrder' => $zOrder,
        ];

        $properties = $this->nonVisualDrawingProperties($shapeElement);
        if ($properties instanceof \DOMElement) {
            foreach (['id', 'name', 'descr', 'title'] as $attribute) {
                $value = $properties->getAttribute($attribute);
                if ($value !== '') {
                    $metadata[$attribute] = $value;
                }
            }
            if ($properties->hasAttribute('hidden')) {
                $metadata['hidden'] = $this->xmlBooleanValue($properties->getAttribute('hidden'));
            }
        }

        $placeholder = $this->placeholderElement($shapeElement);
        if ($placeholder instanceof \DOMElement) {
            $type = $placeholder->getAttribute('type') !== '' ? $placeholder->getAttribute('type') : 'obj';
            $metadata['placeholderType'] = $type;
            if ($placeholder->getAttribute('idx') !== '') {
                $metadata['placeholderIndex'] = $placeholder->getAttribute('idx');
            }
        }

        $layout = $this->shapeTransformMetadata($shapeElement);
        if ($layout !== []) {
            $metadata['layout'] = $layout;
        }

        return $metadata;
    }

    private function nonVisualDrawingProperties(\DOMElement $shapeElement): ?\DOMElement
    {
        foreach ($this->childElements($shapeElement, null) as $child) {
            if (!self::isPresentationNamespace($child->namespaceURI) || !str_starts_with($child->localName, 'nv')) {
                continue;
            }

            $properties = $this->firstPresentationChildElement($child, 'cNvPr');
            if ($properties instanceof \DOMElement) {
                return $properties;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function shapeTransformMetadata(\DOMElement $shapeElement): array
    {
        $properties = $this->firstChildElement($shapeElement, 'spPr')
            ?? $this->firstChildElement($shapeElement, 'grpSpPr')
            ?? $shapeElement;
        $transform = $this->firstChildElement($properties, 'xfrm') ?? $this->firstChildElement($shapeElement, 'xfrm');
        if (!$transform instanceof \DOMElement) {
            return [];
        }

        $layout = [];
        $offset = $this->firstChildElement($transform, 'off');
        if ($offset instanceof \DOMElement) {
            foreach (['x', 'y'] as $attribute) {
                $value = $this->integerAttribute($offset, $attribute);
                if ($value !== null) {
                    $layout[$attribute] = $value;
                }
            }
        }

        $extent = $this->firstChildElement($transform, 'ext');
        if ($extent instanceof \DOMElement) {
            foreach (['cx', 'cy'] as $attribute) {
                $value = $this->integerAttribute($extent, $attribute);
                if ($value !== null) {
                    $layout[$attribute] = $value;
                }
            }
        }

        $rotation = $this->integerAttribute($transform, 'rot');
        if ($rotation !== null) {
            $layout['rot'] = $rotation;
        }

        return $layout;
    }

    private function integerAttribute(\DOMElement $element, string $name): ?int
    {
        return $this->integerText($element->getAttribute($name));
    }

    private function integerText(string $value): ?int
    {
        $literal = $this->integerLiteralParts($value);
        if ($literal === null) {
            return null;
        }

        return $this->integerLiteralToInt($literal);
    }

    /**
     * @return array{sign:int, base:int, digits:string}|null
     */
    private function integerLiteralParts(string $value): ?array
    {
        $value = $this->trimUnicodeWhitespace($value);
        while (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $inner = $this->trimUnicodeWhitespace(substr($value, 1, -1));
            if ($inner === $value) {
                break;
            }
            $value = $inner;
        }

        $sign = 1;
        $digits = $value;
        if (str_starts_with($digits, '-')) {
            $sign = -1;
            $digits = substr($digits, 1);
        }
        if ($digits === '') {
            return null;
        }

        if (preg_match('/^0[xX]([0-9A-Fa-f]+)$/', $digits, $matches) === 1) {
            return ['sign' => $sign, 'base' => 16, 'digits' => strtolower($matches[1])];
        }
        if (preg_match('/^0[oO]([0-7]+)$/', $digits, $matches) === 1) {
            return ['sign' => $sign, 'base' => 8, 'digits' => $matches[1]];
        }

        return preg_match('/^[0-9]+$/', $digits) === 1 ? ['sign' => $sign, 'base' => 10, 'digits' => $digits] : null;
    }

    /**
     * @param array{sign:int, base:int, digits:string} $literal
     */
    private function integerLiteralToInt(array $literal): int
    {
        if ($this->integerLiteralIsHaskellIntMin($literal)) {
            return PHP_INT_MIN;
        }

        $magnitude = match ($literal['base']) {
            16 => (int) hexdec($literal['digits']),
            8 => intval($literal['digits'], 8),
            default => (int) $literal['digits'],
        };

        return $literal['sign'] < 0 ? -$magnitude : $magnitude;
    }

    private function readMaybeIntText(string $value): ?int
    {
        $literal = $this->integerLiteralParts($value);
        if ($literal === null) {
            return null;
        }

        return $this->integerLiteralToHaskellInt($literal);
    }

    /**
     * @param array{sign:int, base:int, digits:string} $literal
     */
    private function integerLiteralToHaskellInt(array $literal): int
    {
        [$high, $low] = $this->unsignedIntegerLiteralModuloWord64($literal);
        if ($literal['sign'] < 0 && ($high !== 0 || $low !== 0)) {
            [$high, $low] = $this->twosComplementWord64($high, $low);
        }

        return $this->signedIntFromWord64($high, $low);
    }

    /**
     * @param array{sign:int, base:int, digits:string} $literal
     * @return array{int, int}
     */
    private function unsignedIntegerLiteralModuloWord64(array $literal): array
    {
        $high = 0;
        $low = 0;
        $base = $literal['base'];
        foreach (str_split($literal['digits']) as $character) {
            $digit = intval($character, $base);
            $lowProduct = ($low * $base) + $digit;
            $carry = intdiv($lowProduct, 4_294_967_296);
            $low = $lowProduct % 4_294_967_296;
            $high = (($high * $base) + $carry) % 4_294_967_296;
        }

        return [$high, $low];
    }

    /**
     * @return array{int, int}
     */
    private function twosComplementWord64(int $high, int $low): array
    {
        $high = 4_294_967_295 - $high;
        $low = 4_294_967_295 - $low + 1;
        if ($low >= 4_294_967_296) {
            $low -= 4_294_967_296;
            $high = ($high + 1) % 4_294_967_296;
        }

        return [$high, $low];
    }

    private function signedIntFromWord64(int $high, int $low): int
    {
        if ($high < 2_147_483_648) {
            return (int) (($high * 4_294_967_296) + $low);
        }

        [$magnitudeHigh, $magnitudeLow] = $this->twosComplementWord64($high, $low);
        if ($magnitudeHigh === 2_147_483_648 && $magnitudeLow === 0) {
            return PHP_INT_MIN;
        }

        return -(int) (($magnitudeHigh * 4_294_967_296) + $magnitudeLow);
    }

    /**
     * @param array{sign:int, base:int, digits:string} $literal
     */
    private function integerLiteralIsHaskellIntMin(array $literal): bool
    {
        return $literal['sign'] < 0
            && $this->normalizedIntegerLiteralDigits($literal) === $this->haskellIntMinAbsLimit($literal['base']);
    }

    private function haskellIntMinAbsLimit(int $base): string
    {
        return match ($base) {
            16 => self::HASKELL_INT_MIN_ABS_HEX,
            8 => self::HASKELL_INT_MIN_ABS_OCTAL,
            default => self::HASKELL_INT_MIN_ABS_DECIMAL,
        };
    }

    /**
     * @param array{sign:int, base:int, digits:string} $literal
     */
    private function normalizedIntegerLiteralDigits(array $literal): string
    {
        $digits = ltrim(strtolower($literal['digits']), '0');

        return $digits === '' ? '0' : $digits;
    }

    private function trimUnicodeWhitespace(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);
    }

    private function xmlBooleanAttribute(\DOMElement $element, string $name): ?bool
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        return $this->xmlBooleanValue($element->getAttribute($name));
    }

    private function xmlBooleanValue(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on'], true);
    }

    private function drawingColorValue(\DOMElement $container): string
    {
        $metadata = $this->drawingColorMetadata($container);

        return is_string($metadata['color'] ?? null) ? $metadata['color'] : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function drawingColorMetadata(\DOMElement $container): array
    {
        $value = $this->drawingColorValueFromElement($container);
        if ($value !== '') {
            return $this->drawingColorMetadataFromValue($container, $value);
        }

        foreach ($container->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $value = $this->drawingColorValueFromElement($element);
            if ($value !== '') {
                return $this->drawingColorMetadataFromValue($element, $value);
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function drawingColorMetadataFromValue(\DOMElement $element, string $value): array
    {
        $metadata = ['color' => $value];
        $transforms = $this->drawingColorTransforms($element);
        if ($transforms !== []) {
            $metadata['transforms'] = $transforms;
        }

        return $metadata;
    }

    /**
     * @return array<string, int>
     */
    private function drawingColorTransforms(\DOMElement $colorElement): array
    {
        $transforms = [];
        foreach ($this->childElements($colorElement, null) as $child) {
            if (!in_array($child->localName, ['lumMod', 'lumOff', 'shade', 'tint'], true)) {
                continue;
            }
            $value = $this->integerAttribute($child, 'val');
            if ($value !== null) {
                $transforms[$child->localName] = $value;
            }
        }

        return $transforms;
    }

    private function drawingColorValueFromElement(\DOMElement $element): string
    {
        if ($element->localName === 'srgbClr') {
            $value = strtoupper(trim($element->getAttribute('val')));

            return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? $value : '';
        }

        if ($element->localName === 'sysClr') {
            $value = strtoupper(trim($element->getAttribute('lastClr')));
            if (preg_match('/^[0-9A-F]{6}$/', $value) === 1) {
                return $value;
            }

            $system = trim($element->getAttribute('val'));

            return $system !== '' ? 'system:' . $system : '';
        }

        if ($element->localName === 'schemeClr') {
            $value = trim($element->getAttribute('val'));

            return $value !== '' ? 'theme:' . $value : '';
        }

        if ($element->localName === 'prstClr') {
            $value = trim($element->getAttribute('val'));

            return $value !== '' ? 'preset:' . $value : '';
        }

        return '';
    }

    /**
     * @return list<array<string, string|bool>>
     */
    private function richMediaReferences(\DOMElement $shapeElement, OpcRelationships $slideRelationships): array
    {
        $media = [];
        $seen = [];
        foreach ($shapeElement->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isRichMediaElement($element)) {
                continue;
            }

            foreach (['embed', 'link', 'id'] as $relationshipAttribute) {
                $relationshipId = $this->relationshipId($element, $relationshipAttribute);
                if ($relationshipId === '' || isset($seen[$relationshipId])) {
                    continue;
                }

                $relationship = $slideRelationships->byId($relationshipId);
                if (!$relationship instanceof OpcRelationship) {
                    continue;
                }

                $kind = $this->relationshipRichMediaKind($relationship, $element);
                if ($kind === '') {
                    continue;
                }

                $seen[$relationshipId] = true;
                $partName = '';
                if (!$relationship->isExternal()) {
                    $resolvedPartName = $this->reviewRelationshipPart($slideRelationships, $relationship);
                    if ($resolvedPartName === null) {
                        continue;
                    }
                    $partName = ltrim($resolvedPartName, '/');
                }

                $media[] = [
                    'kind' => $kind,
                    'relationshipId' => $relationshipId,
                    'relationshipType' => $relationship->type,
                    'target' => $relationship->target,
                    'partName' => $partName,
                    'external' => $relationship->isExternal(),
                ];
            }
        }

        return $media;
    }

    /**
     * @param list<array<string, string|bool|array<string, mixed>>> $richMedia
     */
    private function appendRichMediaReviews(array &$richMedia, \DOMElement $shapeElement, OpcRelationships $slideRelationships, int $zOrder): bool
    {
        $added = false;
        $shape = $this->shapeMetadata($shapeElement, $zOrder);
        foreach ($this->richMediaReferences($shapeElement, $slideRelationships) as $media) {
            $record = $media;
            $record['shape'] = $shape;
            $key = (string) ($record['relationshipId'] ?? '') . "\0" . (string) ($record['partName'] ?? '') . "\0" . (string) ($record['target'] ?? '');
            $duplicate = false;
            foreach ($richMedia as $existing) {
                $existingKey = (string) ($existing['relationshipId'] ?? '') . "\0" . (string) ($existing['partName'] ?? '') . "\0" . (string) ($existing['target'] ?? '');
                if ($existingKey === $key) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }

            $richMedia[] = $record;
            $added = true;
        }

        return $added;
    }

    private function isRichMediaElement(\DOMElement $element): bool
    {
        return in_array($element->localName, ['audioFile', 'videoFile', 'wavAudioFile', 'media', 'audio', 'video'], true);
    }

    private function relationshipRichMediaKind(OpcRelationship $relationship, \DOMElement $element): string
    {
        $target = strtolower($relationship->target);
        $haystack = strtolower($relationship->type . ' ' . $target . ' ' . $element->localName);
        if (str_contains($haystack, 'audio') || str_contains($haystack, 'wav') || preg_match('/\.(?:aac|m4a|mp3|oga|ogg|wav|wma)(?:$|[?#])/i', $target) === 1) {
            return 'audio';
        }
        if (str_contains($haystack, 'video') || str_contains($haystack, 'movie') || preg_match('/\.(?:avi|m4v|mov|mp4|mpeg|mpg|ogv|webm|wmv)(?:$|[?#])/i', $target) === 1) {
            return 'video';
        }

        return str_contains($haystack, '/media') || $element->localName === 'media' ? 'media' : '';
    }

    /**
     * @param list<array{level:int, bullet:bool, listType:string, text:string, continuation?:bool, inlines?:list<AstNode>}> $paragraphs
     * @return list<AstNode>
     */
    private function paragraphsToBlocks(array $paragraphs): array
    {
        if ($paragraphs === []) {
            return [];
        }

        $hasLists = false;
        foreach ($paragraphs as $paragraph) {
            if (($paragraph['listType'] ?? '') !== '') {
                $hasLists = true;
                break;
            }
        }
        if (!$hasLists) {
            return array_map(fn (array $paragraph): AstNode => $this->parsedParagraphBlock($paragraph), $paragraphs);
        }

        $blocks = [];
        $index = 0;
        $count = count($paragraphs);
        while ($index < $count) {
            $paragraph = $paragraphs[$index];
            $listType = (string) ($paragraph['listType'] ?? '');
            if ($listType === '') {
                $blocks[] = $this->parsedParagraphBlock($paragraph);
                $index++;
                continue;
            }

            $level = (int) ($paragraph['level'] ?? 0);
            $items = [];
            while ($index < $count) {
                $current = $paragraphs[$index];
                if (($current['listType'] ?? '') !== $listType || (int) ($current['level'] ?? 0) !== $level) {
                    break;
                }

                $items[] = new AstNode('list_item', [], [
                    new AstNode('plain', [], $this->parsedParagraphInlines($current)),
                ]);
                $index++;
            }
            $blocks[] = new AstNode($listType, [], $items);
        }

        return $blocks;
    }

    /**
     * @param array{level:int, bullet:bool, listType:string, text:string, continuation?:bool, inlines?:list<AstNode>} $paragraph
     */
    private function parsedParagraphBlock(array $paragraph): AstNode
    {
        return new AstNode('paragraph', ['text' => $paragraph['text']], $this->parsedParagraphInlines($paragraph));
    }

    /**
     * @param array{level:int, bullet:bool, listType:string, text:string, continuation?:bool, inlines?:list<AstNode>} $paragraph
     * @return list<AstNode>
     */
    private function parsedParagraphInlines(array $paragraph): array
    {
        return isset($paragraph['inlines']) && is_array($paragraph['inlines'])
            ? $paragraph['inlines']
            : $this->pptxTextInlines($paragraph['text']);
    }

    /**
     * @return list<array{level:int, bullet:bool, listType:string, text:string, continuation?:bool, inlines?:list<AstNode>}>
     */
    private function parseParagraphs(\DOMElement $textBody, ?OpcRelationships $relationships = null, ?string $drawingNamespace = self::DRAWING_NAMESPACE): array
    {
        $paragraphNamespace = $drawingNamespace ?? $this->localNamespaceForPrefix($textBody, 'a');

        return array_map(
            function (\DOMElement $paragraphElement) use ($relationships, $drawingNamespace): array {
                $paragraph = array_replace([
                    'level' => $this->paragraphLevel($paragraphElement, $drawingNamespace),
                    'bullet' => false,
                    'listType' => '',
                    'text' => $this->drawingText($paragraphElement),
                ], $this->paragraphListMetadata($paragraphElement, $drawingNamespace));
                if ($relationships instanceof OpcRelationships) {
                    $inlines = $this->drawingParagraphStructuredInlines($paragraphElement, $relationships);
                } else {
                    $inlines = $this->drawingParagraphStructuredInlines($paragraphElement, null);
                }
                if ($inlines !== []) {
                    $paragraph['inlines'] = $inlines;
                }

                return $paragraph;
            },
            $this->childElementsForPrefix($textBody, 'a', 'p', $paragraphNamespace)
        );
    }

    /**
     * @return list<AstNode>
     */
    private function drawingParagraphStructuredInlines(\DOMElement $paragraphElement, ?OpcRelationships $relationships): array
    {
        $inlines = [];
        $hasStructuredInline = false;
        foreach ($this->childElements($paragraphElement, null) as $child) {
            if (!self::isDrawingNamespace($child->namespaceURI) || !in_array($child->localName, ['r', 'fld'], true)) {
                continue;
            }

            $runInlines = $this->drawingRunStructuredInlines($child);
            if ($runInlines === []) {
                continue;
            }

            foreach ($runInlines as $inline) {
                if ($inline->type !== 'text') {
                    $hasStructuredInline = true;
                }
                $inlines[] = $inline;
            }
        }

        return $hasStructuredInline ? $inlines : [];
    }

    /**
     * @return list<AstNode>
     */
    private function drawingRunStructuredInlines(\DOMElement $runElement): array
    {
        $inlines = [];
        foreach ($this->childElements($runElement, null) as $child) {
            if (self::isDrawingNamespace($child->namespaceURI) && $child->localName === 't') {
                if ($child->textContent !== '') {
                    $inlines[] = new AstNode('text', ['text' => $child->textContent]);
                }
                continue;
            }
        }

        return $inlines;
    }

    private function paragraphLevel(\DOMElement $paragraphElement, ?string $drawingNamespace): int
    {
        $properties = $this->firstChildElementForOuterPrefix($paragraphElement, 'a', 'pPr', $drawingNamespace);
        if (!$properties instanceof \DOMElement) {
            return 0;
        }

        return $this->readMaybeIntText($properties->getAttribute('lvl')) ?? 0;
    }

    /**
     * @return array{bullet:bool, listType:string, continuation?:bool}
     */
    private function paragraphListMetadata(\DOMElement $paragraphElement, ?string $drawingNamespace): array
    {
        $properties = $this->firstChildElementForOuterPrefix($paragraphElement, 'a', 'pPr', $drawingNamespace);
        if ($properties instanceof \DOMElement) {
            if ($this->firstChildElementForOuterPrefix($properties, 'a', 'buChar', $drawingNamespace) instanceof \DOMElement) {
                return ['bullet' => true, 'listType' => 'bullet_list'];
            }
        }

        if ($this->paragraphHasWingdingsRunSymbol($paragraphElement, $drawingNamespace)) {
            return ['bullet' => true, 'listType' => 'bullet_list'];
        }

        if ($properties instanceof \DOMElement && $this->firstChildElementForOuterPrefix($properties, 'a', 'buNone', $drawingNamespace) instanceof \DOMElement) {
            return ['bullet' => false, 'listType' => '', 'continuation' => true];
        }

        return ['bullet' => false, 'listType' => ''];
    }

    private function paragraphHasWingdingsRunSymbol(\DOMElement $paragraphElement, ?string $drawingNamespace): bool
    {
        foreach ($this->childElementsForOuterPrefix($paragraphElement, 'a', 'r', $drawingNamespace) as $runElement) {
            $properties = $this->firstChildElementForOuterPrefix($runElement, 'a', 'rPr', $drawingNamespace);
            $symbol = $properties instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($properties, 'a', 'sym', $drawingNamespace) : null;
            if ($symbol instanceof \DOMElement && str_contains($symbol->getAttribute('typeface'), 'Wingdings')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{level:int, bullet:bool, listType:string, text:string, continuation?:bool, inlines?:list<AstNode>}> $paragraphs
     */
    private function paragraphsContainText(array $paragraphs): bool
    {
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph['text']) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $imageIssues
     */
    private function pictureNode(ZipPackage $package, \DOMElement $pictureElement, OpcRelationships $slideRelationships, ?string $presentationNamespace, ?string $relationshipNamespace, ?string $drawingNamespace, array &$imageIssues): ?AstNode
    {
        $nonVisual = $this->firstChildElementForOuterPrefix($pictureElement, 'p', 'nvPicPr', $presentationNamespace);
        $properties = $nonVisual instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($nonVisual, 'p', 'cNvPr', $presentationNamespace) : null;
        if (!$properties instanceof \DOMElement) {
            $imageIssues[] = ['issue' => 'missing-picture-nonvisual-properties'];

            return null;
        }

        $title = $properties->getAttribute('name');
        $alt = $this->plainPptxImageAltText($properties->getAttribute('descr'));
        $blipFill = $this->firstChildElementForOuterPrefix($pictureElement, 'p', 'blipFill', $presentationNamespace);
        $blip = $blipFill instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($blipFill, 'a', 'blip', $drawingNamespace) : null;
        if (!$blip instanceof \DOMElement) {
            return null;
        }

        $relationshipAttribute = 'embed';
        $relationshipId = $this->relationshipAttributeForPrefix($blip, 'r', $relationshipAttribute, $relationshipNamespace);
        if ($relationshipId === null) {
            $relationshipAttribute = 'link';
            $relationshipId = $this->relationshipAttributeForPrefix($blip, 'r', $relationshipAttribute, $relationshipNamespace);
        }
        if ($relationshipId === null) {
            $imageIssues[] = ['issue' => 'missing-image-relationship-id'];

            return null;
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $imageIssues[] = [
                'issue' => 'unknown-image-relationship',
                'relationshipId' => $relationshipId,
                'relationshipAttribute' => $relationshipAttribute,
            ];

            return null;
        }

        if ($relationshipAttribute === 'link') {
            $issue = [
                'issue' => $relationship->isExternal() ? 'external-image-target' : 'linked-image-target',
                'relationshipId' => $relationshipId,
                'relationshipAttribute' => $relationshipAttribute,
                'target' => $relationship->target,
            ];
            if ($relationship->isExternal()) {
                $issue['externalTargetPolicy'] = $relationship->externalTargetPreflight();
            } else {
                $partName = $this->reviewRelationshipPart($slideRelationships, $relationship);
                if ($partName === null) {
                    $issue['issue'] = 'invalid-linked-image-target';
                } else {
                    $issue['partName'] = ltrim($partName, '/');
                }
            }
            $imageIssues[] = $issue;

            return null;
        }

        $mediaPart = $this->upstreamPictureMediaPart($relationship->target);
        if (!in_array($mediaPart, $package->names(), true)) {
            $imageIssues[] = [
                'issue' => 'missing-image-part',
                'relationshipId' => $relationshipId,
                'relationshipAttribute' => $relationshipAttribute,
                'target' => $relationship->target,
                'partName' => $mediaPart,
            ];

            return null;
        }

        $altInlines = $alt === ''
            ? []
            : [new AstNode('text', [
                'text' => $alt,
            ])];

        $image = new AstNode('image', [
            'url' => $mediaPart,
            'src' => $mediaPart,
            'title' => $title,
            'alt' => $alt,
            'singleStrAlt' => true,
            'relationshipId' => $relationshipId,
            'relationshipAttribute' => $relationshipAttribute,
        ], $altInlines);

        return $image;
    }

    private function upstreamPictureMediaPart(string $target): string
    {
        if (str_starts_with($target, '../media/')) {
            return 'ppt/media/' . substr($target, strlen('../media/'));
        }
        if (str_starts_with($target, 'media/')) {
            return 'ppt/' . $target;
        }

        return $target;
    }

    private function plainPptxImageAltText(string $text): string
    {
        return trim(preg_replace('/[ \t\r\n\f\v]+/', ' ', $text) ?? $text);
    }

    private function graphicDataElement(\DOMElement $graphicFrame, ?string $drawingNamespace): ?\DOMElement
    {
        $graphic = $this->firstChildElementForOuterPrefix($graphicFrame, 'a', 'graphic', $drawingNamespace);

        return $graphic instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($graphic, 'a', 'graphicData', $drawingNamespace) : null;
    }

    private function chartNode(ZipPackage $package, \DOMElement $graphicData, OpcRelationships $slideRelationships): ?AstNode
    {
        $graphicUri = $graphicData->getAttribute('uri');
        $chartElement = $this->firstDescendantElement($graphicData, 'chart');
        $relationshipId = $chartElement instanceof \DOMElement ? $this->relationshipId($chartElement, 'id') : '';
        $chart = [
            'graphicUri' => $graphicUri,
            'relationshipId' => $relationshipId,
            'relationshipType' => '',
            'target' => '',
            'partName' => '',
            'external' => false,
            'title' => '',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => [],
            'issues' => [],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
        if (!$chartElement instanceof \DOMElement) {
            $chart['issues'][] = 'missing-chart-element';

            return $this->chartReviewNode($chart);
        }

        if ($relationshipId === '') {
            $chart['issues'][] = 'missing-chart-relationship-id';

            return $this->chartReviewNode($chart);
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $chart['issues'][] = 'unknown-chart-relationship';

            return $this->chartReviewNode($chart);
        }

        $chart['relationshipType'] = $relationship->type;
        $chart['target'] = $relationship->target;
        $chart['external'] = $relationship->isExternal();
        if ($relationship->isExternal()) {
            $chart['issues'][] = 'external-chart-part';

            return $this->chartReviewNode($chart);
        }

        $chartPart = $this->reviewRelationshipPart($slideRelationships, $relationship);
        if ($chartPart === null) {
            $chart['issues'][] = 'invalid-chart-part-target';

            return $this->chartReviewNode($chart);
        }
        $chart['partName'] = ltrim($chartPart, '/');
        $document = $this->optionalPackageXml($package, $chartPart, 'PPTX chart');
        if (!$document instanceof \DOMDocument) {
            $chart['issues'][] = 'missing-or-invalid-chart-part';

            return $this->chartReviewNode($chart);
        }

        $root = XmlHtmlDom::rootElement($document, 'chartSpace');
        if (!$root instanceof \DOMElement) {
            $chart['issues'][] = 'unexpected-chart-root';

            return $this->chartReviewNode($chart);
        }

        $chartRelationships = $this->optionalRelationshipsOrEmpty($package, $chartPart);
        $contentTypes = $this->optionalContentTypes($package);

        return $this->chartReviewNode(array_replace($chart, $this->chartSummary($root, $chartRelationships, $package, $contentTypes)));
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function chartReviewNode(array $chart): AstNode
    {
        $label = '[Graphic: other: ' . (string) ($chart['graphicUri'] ?? '') . ']';

        return new AstNode('paragraph', [
            'text' => $label,
            'pptxChart' => $chart,
        ], $this->textInlines($label));
    }

    /**
     * @return array<string, mixed>
     */
    private function chartSummary(
        \DOMElement $chartSpace,
        OpcRelationships $chartRelationships,
        ZipPackage $package,
        ?OpcContentTypes $contentTypes
    ): array
    {
        $chartElement = $this->firstDescendantElement($chartSpace, 'chart');
        if (!$chartElement instanceof \DOMElement) {
            return [];
        }

        $summary = [];
        $styleElement = $this->firstChildElement($chartSpace, 'style');
        if ($styleElement instanceof \DOMElement && trim($styleElement->getAttribute('val')) !== '') {
            $summary['style'] = trim($styleElement->getAttribute('val'));
        }

        $roundedCorners = $this->firstChildElement($chartSpace, 'roundedCorners');
        if ($roundedCorners instanceof \DOMElement && $roundedCorners->hasAttribute('val')) {
            $summary['roundedCorners'] = $this->xmlBooleanValue($roundedCorners->getAttribute('val'));
        }

        $titleElement = $this->firstDescendantElement($chartElement, 'title');
        if ($titleElement instanceof \DOMElement) {
            $title = $this->chartElementText($titleElement);
            if ($title !== '') {
                $summary['title'] = $title;
            }
            $titleLayout = $this->chartLayoutMetadata($titleElement);
            if ($titleLayout !== []) {
                $summary['titleLayout'] = $titleLayout;
            }
        }

        $plotArea = $this->firstChildElement($chartElement, 'plotArea');
        if ($plotArea instanceof \DOMElement) {
            $plotAreaLayout = $this->chartLayoutMetadata($plotArea);
            if ($plotAreaLayout !== []) {
                $summary['plotAreaLayout'] = $plotAreaLayout;
            }
        }

        $plots = [];
        $series = [];
        foreach ($this->chartTypeElements($chartElement) as $chartTypeElement) {
            $plot = $this->chartPlotSummary($chartTypeElement);
            $plots[] = $plot;
            foreach ((is_array($plot['series'] ?? null) ? $plot['series'] : []) as $plotSeries) {
                if (is_array($plotSeries)) {
                    $series[] = $plotSeries;
                }
            }
        }
        if ($plots !== []) {
            $summary['chartType'] = (string) ($plots[0]['type'] ?? 'unknown');
            $summary['plots'] = $plots;
            $summary['chartTypes'] = array_values(array_unique(array_map(
                static fn (array $plot): string => (string) ($plot['type'] ?? 'unknown'),
                $plots
            )));
            $summary['chartTypeCount'] = count($plots);
            if ($series !== []) {
                $summary['series'] = $series;
            }
        }

        $axes = $this->chartAxes($chartElement);
        if ($axes !== []) {
            $summary['axes'] = $axes;
        }

        $legend = $this->chartLegend($chartElement);
        if ($legend !== []) {
            $summary['legend'] = $legend;
        }

        $plotVisibleOnly = $this->firstChildElement($chartElement, 'plotVisOnly');
        if ($plotVisibleOnly instanceof \DOMElement && $plotVisibleOnly->hasAttribute('val')) {
            $summary['plotVisibleOnly'] = $this->xmlBooleanValue($plotVisibleOnly->getAttribute('val'));
        }

        $displayBlanksAs = $this->firstChildElement($chartElement, 'dispBlanksAs');
        if ($displayBlanksAs instanceof \DOMElement && trim($displayBlanksAs->getAttribute('val')) !== '') {
            $summary['displayBlanksAs'] = trim($displayBlanksAs->getAttribute('val'));
        }

        $showDataLabelsOverMaximum = $this->firstChildElement($chartElement, 'showDLblsOverMax');
        if ($showDataLabelsOverMaximum instanceof \DOMElement && $showDataLabelsOverMaximum->hasAttribute('val')) {
            $summary['showDataLabelsOverMaximum'] = $this->xmlBooleanValue($showDataLabelsOverMaximum->getAttribute('val'));
        }

        $externalDataRelationshipIds = [];
        $externalDataRelationships = [];
        foreach ($chartSpace->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'externalData') {
                continue;
            }

            $relationshipId = $this->relationshipId($element, 'id');
            if ($relationshipId !== '') {
                $externalDataRelationshipIds[] = $relationshipId;
                $relationship = $chartRelationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    $externalDataRelationships[] = $this->chartRelationshipMetadata(
                        $relationship,
                        $chartRelationships,
                        $package,
                        $contentTypes
                    );
                }
            }
        }
        if ($externalDataRelationshipIds !== []) {
            $summary['externalDataRelationshipIds'] = array_values(array_unique($externalDataRelationshipIds));
        }
        if ($externalDataRelationships !== []) {
            $summary['externalDataRelationships'] = $externalDataRelationships;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartLegend(\DOMElement $chartElement): array
    {
        $legend = $this->firstChildElement($chartElement, 'legend');
        if (!$legend instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $position = $this->firstChildElement($legend, 'legendPos');
        if ($position instanceof \DOMElement && trim($position->getAttribute('val')) !== '') {
            $metadata['position'] = trim($position->getAttribute('val'));
        }

        $overlay = $this->firstChildElement($legend, 'overlay');
        if ($overlay instanceof \DOMElement && $overlay->hasAttribute('val')) {
            $metadata['overlay'] = $this->xmlBooleanValue($overlay->getAttribute('val'));
        }

        $layout = $this->chartLayoutMetadata($legend);
        if ($layout !== []) {
            $metadata['layout'] = $layout;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartLayoutMetadata(\DOMElement $container): array
    {
        $layout = $this->firstChildElement($container, 'layout');
        if (!$layout instanceof \DOMElement) {
            return [];
        }

        $manualLayout = $this->firstChildElement($layout, 'manualLayout');
        if (!$manualLayout instanceof \DOMElement) {
            return ['type' => 'layout'];
        }

        $metadata = ['type' => 'manual'];
        foreach ([
            'layoutTarget' => 'target',
            'xMode' => 'xMode',
            'yMode' => 'yMode',
            'wMode' => 'widthMode',
            'hMode' => 'heightMode',
        ] as $source => $target) {
            $element = $this->firstChildElement($manualLayout, $source);
            if ($element instanceof \DOMElement && trim($element->getAttribute('val')) !== '') {
                $metadata[$target] = trim($element->getAttribute('val'));
            }
        }

        foreach (['x' => 'x', 'y' => 'y', 'w' => 'width', 'h' => 'height'] as $source => $target) {
            $element = $this->firstChildElement($manualLayout, $source);
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $value = trim($element->getAttribute('val'));
            if (is_numeric($value)) {
                $metadata[$target] = (float) $value;
            }
        }

        return $metadata;
    }

    /**
     * @return list<\DOMElement>
     */
    private function chartTypeElements(\DOMElement $chartElement): array
    {
        $plotArea = $this->firstChildElement($chartElement, 'plotArea');
        if (!$plotArea instanceof \DOMElement) {
            return [];
        }

        $elements = [];
        foreach ($this->childElements($plotArea, null) as $element) {
            if (!str_ends_with($element->localName, 'Chart')) {
                continue;
            }
            if ($element->localName === 'chart') {
                continue;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartPlotSummary(\DOMElement $chartTypeElement): array
    {
        $type = $this->chartTypeName($chartTypeElement);
        $plot = ['type' => $type];

        foreach ([
            'barDir' => 'barDirection',
            'grouping' => 'grouping',
            'scatterStyle' => 'scatterStyle',
            'radarStyle' => 'radarStyle',
            'ofPieType' => 'ofPieType',
            'holeSize' => 'holeSize',
        ] as $source => $target) {
            $element = $this->firstChildElement($chartTypeElement, $source);
            if ($element instanceof \DOMElement && $element->getAttribute('val') !== '') {
                $plot[$target] = $element->getAttribute('val');
            }
        }

        $varyColors = $this->firstChildElement($chartTypeElement, 'varyColors');
        if ($varyColors instanceof \DOMElement && $varyColors->hasAttribute('val')) {
            $plot['varyColors'] = $this->xmlBooleanValue($varyColors->getAttribute('val'));
        }

        foreach ([
            'gapWidth' => 'gapWidth',
            'overlap' => 'overlap',
        ] as $source => $target) {
            $value = $this->chartIntChildValue($chartTypeElement, $source);
            if ($value !== null) {
                $plot[$target] = $value;
            }
        }

        $dataLabels = $this->chartDataLabels($chartTypeElement);
        if ($dataLabels !== []) {
            $plot['dataLabels'] = $dataLabels;
        }

        $axisIds = [];
        foreach ($this->childElements($chartTypeElement, 'axId') as $axisIdElement) {
            $axisId = trim($axisIdElement->getAttribute('val'));
            if ($axisId !== '') {
                $axisIds[] = $axisId;
            }
        }
        if ($axisIds !== []) {
            $plot['axisIds'] = array_values(array_unique($axisIds));
        }

        $series = [];
        foreach ($this->childElements($chartTypeElement, 'ser') as $seriesElement) {
            $series[] = $this->chartSeries($seriesElement, $type);
        }
        $plot['seriesCount'] = count($series);
        if ($series !== []) {
            $plot['series'] = $series;
        }

        return $plot;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chartAxes(\DOMElement $chartElement): array
    {
        $plotArea = $this->firstChildElement($chartElement, 'plotArea');
        if (!$plotArea instanceof \DOMElement) {
            return [];
        }

        $axes = [];
        foreach ($this->childElements($plotArea, null) as $axisElement) {
            if (!in_array($axisElement->localName, ['catAx', 'dateAx', 'valAx', 'serAx'], true)) {
                continue;
            }

            $axis = ['type' => $axisElement->localName];
            foreach (['axId' => 'id', 'crossAx' => 'crossAxisId'] as $source => $target) {
                $element = $this->firstChildElement($axisElement, $source);
                if ($element instanceof \DOMElement && $element->getAttribute('val') !== '') {
                    $axis[$target] = $element->getAttribute('val');
                }
            }

            $position = $this->firstChildElement($axisElement, 'axPos');
            if ($position instanceof \DOMElement && $position->getAttribute('val') !== '') {
                $axis['position'] = $position->getAttribute('val');
            }

            $title = $this->firstChildElement($axisElement, 'title');
            if ($title instanceof \DOMElement) {
                $text = $this->chartElementText($title);
                if ($text !== '') {
                    $axis['title'] = $text;
                }
            }

            $numberFormat = $this->firstChildElement($axisElement, 'numFmt');
            if ($numberFormat instanceof \DOMElement) {
                $format = trim($numberFormat->getAttribute('formatCode'));
                if ($format !== '') {
                    $axis['numberFormat'] = $format;
                }
                if ($numberFormat->hasAttribute('sourceLinked')) {
                    $axis['sourceLinked'] = $this->xmlBooleanValue($numberFormat->getAttribute('sourceLinked'));
                }
            }

            $scaling = $this->chartAxisScaling($axisElement);
            if ($scaling !== []) {
                $axis['scaling'] = $scaling;
            }

            foreach ([
                'delete' => 'deleted',
                'auto' => 'auto',
                'noMultiLvlLbl' => 'noMultiLevelLabels',
            ] as $source => $target) {
                $value = $this->chartBooleanChildValue($axisElement, $source);
                if ($value !== null) {
                    $axis[$target] = $value;
                }
            }

            foreach ([
                'majorTickMark' => 'majorTickMark',
                'minorTickMark' => 'minorTickMark',
                'tickLblPos' => 'tickLabelPosition',
                'crosses' => 'crosses',
                'crossBetween' => 'crossBetween',
                'lblAlgn' => 'labelAlignment',
                'baseTimeUnit' => 'baseTimeUnit',
                'majorTimeUnit' => 'majorTimeUnit',
                'minorTimeUnit' => 'minorTimeUnit',
            ] as $source => $target) {
                $value = $this->chartStringChildValue($axisElement, $source);
                if ($value !== null) {
                    $axis[$target] = $value;
                }
            }

            $labelOffset = $this->chartIntChildValue($axisElement, 'lblOffset');
            if ($labelOffset !== null) {
                $axis['labelOffset'] = $labelOffset;
            }

            foreach ([
                'majorUnit' => 'majorUnit',
                'minorUnit' => 'minorUnit',
                'crossesAt' => 'crossesAt',
            ] as $source => $target) {
                $value = $this->chartFloatChildValue($axisElement, $source);
                if ($value !== null) {
                    $axis[$target] = $value;
                }
            }

            foreach (['majorGridlines' => 'majorGridlines', 'minorGridlines' => 'minorGridlines'] as $source => $target) {
                $gridlines = $this->chartAxisGridlines($axisElement, $source);
                if ($gridlines !== []) {
                    $axis[$target] = $gridlines;
                }
            }

            $axes[] = $axis;
        }

        return $axes;
    }

    private function chartStringChildValue(\DOMElement $parent, string $localName): ?string
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = trim($child->getAttribute('val'));

        return $value !== '' ? $value : null;
    }

    private function chartFloatChildValue(\DOMElement $parent, string $localName): ?float
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = trim($child->getAttribute('val'));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartAxisScaling(\DOMElement $axisElement): array
    {
        $scaling = $this->firstChildElement($axisElement, 'scaling');
        if (!$scaling instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $orientation = $this->chartStringChildValue($scaling, 'orientation');
        if ($orientation !== null) {
            $metadata['orientation'] = $orientation;
        }

        foreach (['logBase' => 'logBase', 'min' => 'min', 'max' => 'max'] as $source => $target) {
            $value = $this->chartFloatChildValue($scaling, $source);
            if ($value !== null) {
                $metadata[$target] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartAxisGridlines(\DOMElement $axisElement, string $localName): array
    {
        $gridlines = $this->firstChildElement($axisElement, $localName);
        if (!$gridlines instanceof \DOMElement) {
            return [];
        }

        $metadata = ['present' => true];
        $shapeProperties = $this->firstChildElement($gridlines, 'spPr');
        if ($shapeProperties instanceof \DOMElement) {
            $shape = $this->chartShapePropertiesMetadata($shapeProperties);
            if ($shape !== []) {
                $metadata['shape'] = $shape;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartRelationshipMetadata(
        OpcRelationship $relationship,
        OpcRelationships $chartRelationships,
        ZipPackage $package,
        ?OpcContentTypes $contentTypes
    ): array
    {
        $metadata = [
            'relationshipId' => $relationship->id,
            'relationshipType' => $relationship->type,
            'target' => $relationship->target,
            'external' => $relationship->isExternal(),
        ];

        if ($relationship->isExternal()) {
            $metadata['externalTargetPolicy'] = $relationship->externalTargetPreflight();

            return $metadata;
        }

        $partName = $this->reviewRelationshipPart($chartRelationships, $relationship);
        if ($partName !== null && $partName !== '') {
            $metadata['partName'] = ltrim($partName, '/');
            $metadata += $this->chartRelationshipPackagePartMetadata($package, $partName, $relationship, $contentTypes);
        } elseif ($partName === null) {
            $metadata['targetIssue'] = 'invalid-chart-relationship-target';
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartRelationshipPackagePartMetadata(
        ZipPackage $package,
        string $partName,
        OpcRelationship $relationship,
        ?OpcContentTypes $contentTypes
    ): array {
        $metadata = [
            'exists' => $package->has($partName),
            'byteExposurePolicy' => 'package-part-bytes-hashed-not-exposed',
        ];
        $contentType = $contentTypes?->contentTypeForPart($partName);
        if ($contentType !== null) {
            $metadata['contentType'] = $contentType;
        }
        if ($this->isPptxEmbeddedWorkbookRelationship($relationship, $partName, $contentType)) {
            $metadata['packageRelationshipRole'] = 'embedded-workbook';
            $metadata['embeddedWorkbook'] = true;
        }

        if (!$metadata['exists']) {
            return $metadata;
        }

        $entry = $package->entry($partName);
        $metadata['zipEntry'] = $entry->name;
        $metadata['byteLength'] = $entry->uncompressedSize;
        $metadata['compressedByteLength'] = $entry->compressedSize;
        $metadata['compressionMethod'] = $entry->compressionMethod;
        if ($entry->uncompressedSize > self::MAX_EMBEDDED_PACKAGE_PROVENANCE_BYTES) {
            $metadata['byteHashPolicy'] = 'skipped-over-embedded-package-provenance-byte-limit';

            return $metadata;
        }

        $bytes = $package->read($partName, self::MAX_EMBEDDED_PACKAGE_PROVENANCE_BYTES);
        $metadata['sha256'] = hash('sha256', $bytes);

        return $metadata;
    }

    private function isPptxEmbeddedWorkbookRelationship(
        OpcRelationship $relationship,
        string $partName,
        ?string $contentType
    ): bool {
        $partName = ltrim($partName, '/');
        if (
            !str_ends_with($relationship->type, '/package')
            || !str_starts_with($partName, 'ppt/embeddings/')
        ) {
            return false;
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        if (in_array($extension, ['xlsx', 'xlsm', 'xlsb', 'xls'], true)) {
            return true;
        }

        return $contentType !== null
            && str_contains(strtolower($contentType), 'spreadsheet');
    }

    private function chartTypeName(\DOMElement $chartTypeElement): string
    {
        $type = preg_replace('/Chart$/', '', $chartTypeElement->localName) ?? $chartTypeElement->localName;

        return strtolower($type);
    }

    /**
     * @return array<string, mixed>
     */
    private function chartSeries(\DOMElement $seriesElement, string $plotType = ''): array
    {
        $categoryCache = $this->chartCacheSummaryFor($seriesElement, ['cat', 'xVal']);
        $valueCache = $this->chartCacheSummaryFor($seriesElement, ['val', 'yVal']);
        $series = [
            'name' => '',
            'categories' => $categoryCache['values'],
            'values' => $valueCache['values'],
        ];
        if ($plotType !== '') {
            $series['plotType'] = $plotType;
        }
        $this->addChartCacheMetadata($series, 'category', $categoryCache);
        $this->addChartCacheMetadata($series, 'value', $valueCache);

        $index = $this->firstChildElement($seriesElement, 'idx');
        if ($index instanceof \DOMElement && $index->getAttribute('val') !== '') {
            $series['index'] = $index->getAttribute('val');
        }

        $order = $this->firstChildElement($seriesElement, 'order');
        if ($order instanceof \DOMElement && $order->getAttribute('val') !== '') {
            $series['order'] = $order->getAttribute('val');
        }

        $text = $this->firstChildElement($seriesElement, 'tx');
        if ($text instanceof \DOMElement) {
            $series['name'] = $this->chartElementText($text);
            $this->addChartCacheMetadata($series, 'name', $this->chartCacheSummary($text));
        }

        $dataLabels = $this->chartDataLabels($seriesElement);
        if ($dataLabels !== []) {
            $series['dataLabels'] = $dataLabels;
        }

        foreach ([
            'invertIfNegative' => 'invertIfNegative',
            'smooth' => 'smooth',
        ] as $source => $target) {
            $value = $this->chartBooleanChildValue($seriesElement, $source);
            if ($value !== null) {
                $series[$target] = $value;
            }
        }

        $explosion = $this->chartIntChildValue($seriesElement, 'explosion');
        if ($explosion !== null) {
            $series['explosion'] = $explosion;
        }

        $marker = $this->chartMarkerMetadata($seriesElement);
        if ($marker !== []) {
            $series['marker'] = $marker;
        }

        $shapeProperties = $this->firstChildElement($seriesElement, 'spPr');
        if ($shapeProperties instanceof \DOMElement) {
            $shape = $this->chartShapePropertiesMetadata($shapeProperties);
            if ($shape !== []) {
                $series['shape'] = $shape;
            }
        }

        $dataPoints = $this->chartDataPoints($seriesElement);
        if ($dataPoints !== []) {
            $series['dataPoints'] = $dataPoints;
            $series['dataPointCount'] = count($dataPoints);
        }

        return $series;
    }

    private function chartBooleanChildValue(\DOMElement $parent, string $localName): ?bool
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        return $child->hasAttribute('val') ? $this->xmlBooleanValue($child->getAttribute('val')) : true;
    }

    private function chartIntChildValue(\DOMElement $parent, string $localName): ?int
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        return $this->integerAttribute($child, 'val');
    }

    /**
     * @return array<string, mixed>
     */
    private function chartMarkerMetadata(\DOMElement $container): array
    {
        $marker = $this->firstChildElement($container, 'marker');
        if (!$marker instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $symbol = $this->firstChildElement($marker, 'symbol');
        if ($symbol instanceof \DOMElement && trim($symbol->getAttribute('val')) !== '') {
            $metadata['symbol'] = trim($symbol->getAttribute('val'));
        }

        $size = $this->chartIntChildValue($marker, 'size');
        if ($size !== null) {
            $metadata['size'] = $size;
        }

        return $metadata;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chartDataPoints(\DOMElement $seriesElement): array
    {
        $points = [];
        foreach ($this->childElements($seriesElement, 'dPt') as $dataPoint) {
            $point = [];
            $index = $this->chartIntChildValue($dataPoint, 'idx');
            if ($index !== null) {
                $point['pointIndex'] = $index;
            }

            foreach ([
                'invertIfNegative' => 'invertIfNegative',
                'bubble3D' => 'bubble3D',
            ] as $source => $target) {
                $value = $this->chartBooleanChildValue($dataPoint, $source);
                if ($value !== null) {
                    $point[$target] = $value;
                }
            }

            $explosion = $this->chartIntChildValue($dataPoint, 'explosion');
            if ($explosion !== null) {
                $point['explosion'] = $explosion;
            }

            $marker = $this->chartMarkerMetadata($dataPoint);
            if ($marker !== []) {
                $point['marker'] = $marker;
            }

            $shapeProperties = $this->firstChildElement($dataPoint, 'spPr');
            if ($shapeProperties instanceof \DOMElement) {
                $shape = $this->chartShapePropertiesMetadata($shapeProperties);
                if ($shape !== []) {
                    $point['shape'] = $shape;
                }
            }

            if ($point !== []) {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartShapePropertiesMetadata(\DOMElement $shapeProperties): array
    {
        $metadata = [];
        $fill = $this->tableFillMetadata($shapeProperties);
        if (is_string($fill['color'] ?? null) && $fill['color'] !== '') {
            $metadata['fillColor'] = $fill['color'];
            if (is_array($fill['transforms'] ?? null) && $fill['transforms'] !== []) {
                $metadata['fillColorTransforms'] = $fill['transforms'];
            }
        }

        $line = $this->firstChildElement($shapeProperties, 'ln');
        if ($line instanceof \DOMElement) {
            $lineStyle = $this->tableLineStyleMetadata($line);
            if ($lineStyle !== []) {
                $metadata['line'] = $lineStyle;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartDataLabels(\DOMElement $container): array
    {
        $dataLabels = $this->firstChildElement($container, 'dLbls');
        if (!$dataLabels instanceof \DOMElement) {
            return [];
        }

        $metadata = $this->chartDataLabelMetadata($dataLabels);
        $points = [];
        foreach ($this->childElements($dataLabels, 'dLbl') as $dataLabel) {
            $point = $this->chartDataLabelMetadata($dataLabel);
            $index = $this->firstChildElement($dataLabel, 'idx');
            if ($index instanceof \DOMElement && $index->getAttribute('val') !== '') {
                $point = ['pointIndex' => $index->getAttribute('val')] + $point;
            }
            if ($point !== []) {
                $points[] = $point;
            }
        }

        if ($points !== []) {
            $metadata['points'] = $points;
            $metadata['pointCount'] = count($points);
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartDataLabelMetadata(\DOMElement $dataLabel): array
    {
        $metadata = [];
        $position = $this->firstChildElement($dataLabel, 'dLblPos');
        if ($position instanceof \DOMElement && $position->getAttribute('val') !== '') {
            $metadata['position'] = $position->getAttribute('val');
        }

        $numberFormat = $this->firstChildElement($dataLabel, 'numFmt');
        if ($numberFormat instanceof \DOMElement) {
            $format = trim($numberFormat->getAttribute('formatCode'));
            if ($format !== '') {
                $metadata['numberFormat'] = $format;
            }
            if ($numberFormat->hasAttribute('sourceLinked')) {
                $metadata['sourceLinked'] = $this->xmlBooleanValue($numberFormat->getAttribute('sourceLinked'));
            }
        }

        $separator = $this->firstChildElement($dataLabel, 'separator');
        if ($separator instanceof \DOMElement) {
            $text = trim($this->allDescendantText($separator));
            if ($text !== '') {
                $metadata['separator'] = $text;
            }
        }

        foreach ([
            'showLegendKey' => 'showLegendKey',
            'showVal' => 'showValue',
            'showCatName' => 'showCategoryName',
            'showSerName' => 'showSeriesName',
            'showPercent' => 'showPercent',
            'showBubbleSize' => 'showBubbleSize',
            'showLeaderLines' => 'showLeaderLines',
        ] as $source => $target) {
            $element = $this->firstChildElement($dataLabel, $source);
            if ($element instanceof \DOMElement) {
                $metadata[$target] = $element->hasAttribute('val')
                    ? $this->xmlBooleanValue($element->getAttribute('val'))
                    : true;
            }
        }

        return $metadata;
    }

    /**
     * @param list<string> $containers
     * @return array{values:list<string>, pointIndexes:list<string>, pointCount?:int, formula?:string}
     */
    private function chartCacheSummaryFor(\DOMElement $seriesElement, array $containers): array
    {
        foreach ($this->childElements($seriesElement, null) as $child) {
            if (in_array($child->localName, $containers, true)) {
                return $this->chartCacheSummary($child);
            }
        }

        return [
            'values' => [],
            'pointIndexes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $series
     * @param array{values:list<string>, pointIndexes:list<string>, pointCount?:int, formula?:string} $cache
     */
    private function addChartCacheMetadata(array &$series, string $prefix, array $cache): void
    {
        if (is_string($cache['formula'] ?? null) && $cache['formula'] !== '') {
            $series[$prefix . 'Formula'] = $cache['formula'];
        }
        if (is_int($cache['pointCount'] ?? null)) {
            $series[$prefix . 'PointCount'] = $cache['pointCount'];
        }
        if ($cache['pointIndexes'] !== []) {
            $series[$prefix . 'PointIndexes'] = $cache['pointIndexes'];
        }
    }

    /**
     * @return array{values:list<string>, pointIndexes:list<string>, pointCount?:int, formula?:string}
     */
    private function chartCacheSummary(\DOMElement $container): array
    {
        $summary = [
            'values' => [],
            'pointIndexes' => [],
        ];
        $formula = $this->firstDescendantElement($container, 'f');
        if ($formula instanceof \DOMElement) {
            $text = trim($this->allDescendantText($formula));
            if ($text !== '') {
                $summary['formula'] = $text;
            }
        }
        foreach ($container->getElementsByTagName('*') as $count) {
            if (!$count instanceof \DOMElement || $count->localName !== 'ptCount') {
                continue;
            }
            $pointCount = $this->integerAttribute($count, 'val');
            if ($pointCount !== null) {
                $summary['pointCount'] = $pointCount;
            }
            break;
        }

        $values = [];
        $pointIndexes = [];
        foreach ($container->getElementsByTagName('*') as $point) {
            if (!$point instanceof \DOMElement || $point->localName !== 'pt') {
                continue;
            }
            $pointIndex = trim($point->getAttribute('idx'));
            if ($pointIndex !== '') {
                $pointIndexes[] = $pointIndex;
            }

            $value = $this->firstChildElement($point, 'v');
            if ($value instanceof \DOMElement) {
                $text = $this->chartElementText($value);
                if ($text !== '') {
                    $values[] = $text;
                }
            }
        }
        if ($values !== []) {
            $summary['values'] = $values;
            $summary['pointIndexes'] = array_values(array_unique($pointIndexes));

            return $summary;
        }

        foreach ($container->getElementsByTagName('*') as $value) {
            if (!$value instanceof \DOMElement || $value->localName !== 'v') {
                continue;
            }

            $text = $this->chartElementText($value);
            if ($text !== '') {
                $values[] = $text;
            }
        }

        $summary['values'] = $values;

        return $summary;
    }

    private function chartElementText(\DOMElement $element): string
    {
        $texts = [];
        if (in_array($element->localName, ['t', 'v'], true)) {
            $text = trim($this->allDescendantText($element));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        foreach ($element->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement || !in_array($child->localName, ['t', 'v'], true)) {
                continue;
            }

            $text = trim($this->allDescendantText($child));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return trim(implode(' ', $texts));
    }

    /**
     * @param array<string, mixed> $tableStyles
     */
    private function tableNode(\DOMElement $tableElement, array $tableStyles, array $slideContext, ?string $drawingNamespace): ?AstNode
    {
        $theme = is_array($slideContext['theme'] ?? null) ? $slideContext['theme'] : [];
        $style = $this->tableStyleMetadata($tableElement, $tableStyles);
        $rowElements = $this->childElementsForOuterPrefix($tableElement, 'a', 'tr', $drawingNamespace);
        $rowCount = count($rowElements);
        $columnCount = 0;
        foreach ($rowElements as $rowElement) {
            $columnCount = max($columnCount, count($this->childElementsForOuterPrefix($rowElement, 'a', 'tc', $drawingNamespace)));
        }

        $rows = [];
        foreach ($rowElements as $rowIndex => $rowElement) {
            $row = [];
            foreach ($this->childElementsForOuterPrefix($rowElement, 'a', 'tc', $drawingNamespace) as $columnIndex => $cellElement) {
                $row[] = $this->tableCellData($cellElement, $theme, $drawingNamespace, $style, $rowIndex, $columnIndex, $rowCount, $columnCount);
            }
            $rows[] = $row;
        }
        if ($rows === []) {
            return null;
        }

        $header = array_shift($rows) ?? [];
        $attrs = [
            'caption' => '',
            'nativeColumnCount' => count($header),
            'pptxTable' => true,
        ];
        if ($header !== []) {
            $attrs['alignments'] = array_fill(0, count($header), 'default');
        }
        if ($style !== []) {
            $attrs['pptxTableStyle'] = $style;
        }
        $columnWidths = $this->tableColumnWidths($tableElement, $drawingNamespace);
        if ($columnWidths !== []) {
            $attrs['columnWidths'] = $columnWidths;
        }

        return new AstNode('table', $attrs, [
            new AstNode('table_head', [], [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $rows)),
        ]);
    }

     /**
     * @return array{attrs:array<string, mixed>, text:string}
     */
    private function tableCellData(\DOMElement $cellElement, array $theme, ?string $drawingNamespace, array $tableStyle = [], int $rowIndex = 0, int $columnIndex = 0, int $rowCount = 0, int $columnCount = 0): array
    {
        $textBody = $this->firstChildElementForOuterPrefix($cellElement, 'a', 'txBody', $drawingNamespace);
        $text = $textBody instanceof \DOMElement ? $this->drawingText($textBody) : '';
        $attrs = ['text' => $text];
        $pptxCell = [];

        foreach (['gridSpan', 'rowSpan'] as $source) {
            $value = $this->integerAttribute($cellElement, $source);
            if ($value !== null && $value > 1) {
                $pptxCell[$source] = $value;
            }
        }

        foreach (['hMerge', 'vMerge'] as $source) {
            $value = $this->xmlBooleanAttribute($cellElement, $source);
            if ($value !== null) {
                $pptxCell[$source] = $value;
            }
        }

        if ($pptxCell !== []) {
            $attrs['pptxCell'] = $pptxCell;
        }

        $tableStyleApplied = $this->tableStyleForCell($tableStyle, $rowIndex, $columnIndex, $rowCount, $columnCount, $theme);
        if ($tableStyleApplied !== []) {
            $attrs['pptxTableStyleApplied'] = $tableStyleApplied;
        }

        $style = $this->tableCellStyleMetadata($cellElement, $theme);
        if ($style !== []) {
            $attrs['pptxCellStyle'] = $style;
            if (isset($style['fillColor'])) {
                $attrs['backgroundColor'] = $style['fillColor'];
            }
            if (isset($style['verticalAlign'])) {
                $attrs['verticalAlign'] = $style['verticalAlign'];
            }
        }

        return ['attrs' => $attrs, 'text' => $text];
    }

    /**
     * @param list<array{attrs:array<string, mixed>, text:string}> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            fn (array $cell): AstNode => $this->tableCellNode($cell, $header),
            $row
        ));
    }

    /**
     * @param array{attrs:array<string, mixed>, text:string} $cell
     */
    private function tableCellNode(array $cell, bool $header): AstNode
    {
        $attrs = $cell['attrs'];
        $attrs['header'] = $header;
        $text = $cell['text'];

        return new AstNode('table_cell', $attrs, [
            new AstNode('plain', [], $this->pptxTextInlines($text)),
        ]);
    }

    /**
     * @param array<string, mixed> $tableStyles
     * @return array<string, mixed>
     */
    private function tableStyleMetadata(\DOMElement $tableElement, array $tableStyles): array
    {
        $properties = $this->firstChildElement($tableElement, 'tblPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $style = [];
        foreach (['firstRow', 'firstCol', 'lastRow', 'lastCol', 'bandRow', 'bandCol'] as $attribute) {
            $value = $this->xmlBooleanAttribute($properties, $attribute);
            if ($value !== null) {
                $style[$attribute] = $value;
            }
        }

        $styleId = $this->firstChildElement($properties, 'tableStyleId');
        if ($styleId instanceof \DOMElement) {
            $id = trim($this->allDescendantText($styleId));
            if ($id !== '') {
                $style['id'] = $id;
            }
        }

        $styleCatalog = $tableStyles['styles'] ?? [];
        $styleId = is_string($style['id'] ?? null) ? $style['id'] : '';
        $resolvedStyle = is_array($styleCatalog) && $styleId !== '' && is_array($styleCatalog[$styleId] ?? null)
            ? $styleCatalog[$styleId]
            : [];
        if ($resolvedStyle !== []) {
            foreach (['name', 'sourcePart', 'relationshipId'] as $key) {
                $value = $resolvedStyle[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    $style[$key] = $value;
                }
            }
            if (is_array($resolvedStyle['parts'] ?? null)) {
                $style['parts'] = $resolvedStyle['parts'];
            }
            if (($resolvedStyle['default'] ?? false) === true) {
                $style['default'] = true;
            }
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableStyleForCell(array $tableStyle, int $rowIndex, int $columnIndex, int $rowCount, int $columnCount, array $theme): array
    {
        $parts = is_array($tableStyle['parts'] ?? null) ? $tableStyle['parts'] : [];
        if ($parts === []) {
            return [];
        }

        $appliedParts = [];
        $textStyle = [];
        $cellStyle = [];
        foreach ($this->tableStyleCellPartNames($tableStyle, $rowIndex, $columnIndex, $rowCount, $columnCount) as $partName) {
            $part = $parts[$partName] ?? null;
            if (!is_array($part)) {
                continue;
            }

            $appliedParts[] = $partName;
            if (is_array($part['text'] ?? null)) {
                $textStyle = array_replace($textStyle, $part['text']);
            }

            $partCellStyle = is_array($part['cell'] ?? null) ? $part['cell'] : [];
            if (is_string($part['fillColor'] ?? null) && !isset($partCellStyle['fillColor'])) {
                $partCellStyle['fillColor'] = $part['fillColor'];
            }
            if ($partCellStyle !== []) {
                $cellStyle = array_replace_recursive($cellStyle, $partCellStyle);
            }
        }

        if ($appliedParts === []) {
            return [];
        }

        $style = [
            'appliedParts' => $appliedParts,
            'appliedPartCount' => count($appliedParts),
        ];
        if ($textStyle !== []) {
            $style['text'] = $textStyle;
        }
        if ($cellStyle !== []) {
            $style['cell'] = $this->withResolvedThemeColors($cellStyle, $theme);
        }

        return $style;
    }

    /**
     * @return list<string>
     */
    private function tableStyleCellPartNames(array $tableStyle, int $rowIndex, int $columnIndex, int $rowCount, int $columnCount): array
    {
        $partNames = ['wholeTbl'];
        $firstRow = ($tableStyle['firstRow'] ?? false) === true;
        $lastRow = ($tableStyle['lastRow'] ?? false) === true;
        $firstColumn = ($tableStyle['firstCol'] ?? false) === true;
        $lastColumn = ($tableStyle['lastCol'] ?? false) === true;

        if (($tableStyle['bandRow'] ?? false) === true) {
            $bandRowIndex = $rowIndex - ($firstRow ? 1 : 0);
            if ($bandRowIndex >= 0 && !($lastRow && $rowIndex === $rowCount - 1)) {
                $partNames[] = $bandRowIndex % 2 === 0 ? 'band1H' : 'band2H';
            }
        }
        if (($tableStyle['bandCol'] ?? false) === true) {
            $bandColumnIndex = $columnIndex - ($firstColumn ? 1 : 0);
            if ($bandColumnIndex >= 0 && !($lastColumn && $columnIndex === $columnCount - 1)) {
                $partNames[] = $bandColumnIndex % 2 === 0 ? 'band1V' : 'band2V';
            }
        }

        if ($firstRow && $rowIndex === 0) {
            $partNames[] = 'firstRow';
        }
        if ($lastRow && $rowCount > 0 && $rowIndex === $rowCount - 1) {
            $partNames[] = 'lastRow';
        }
        if ($firstColumn && $columnIndex === 0) {
            $partNames[] = 'firstCol';
        }
        if ($lastColumn && $columnCount > 0 && $columnIndex === $columnCount - 1) {
            $partNames[] = 'lastCol';
        }

        if ($firstRow && $firstColumn && $rowIndex === 0 && $columnIndex === 0) {
            $partNames[] = 'nwCell';
        }
        if ($firstRow && $lastColumn && $rowIndex === 0 && $columnCount > 0 && $columnIndex === $columnCount - 1) {
            $partNames[] = 'neCell';
        }
        if ($lastRow && $firstColumn && $rowCount > 0 && $rowIndex === $rowCount - 1 && $columnIndex === 0) {
            $partNames[] = 'swCell';
        }
        if ($lastRow && $lastColumn && $rowCount > 0 && $columnCount > 0 && $rowIndex === $rowCount - 1 && $columnIndex === $columnCount - 1) {
            $partNames[] = 'seCell';
        }

        return array_values(array_unique($partNames));
    }

    /**
     * @return list<int>
     */
    private function tableColumnWidths(\DOMElement $tableElement, ?string $drawingNamespace): array
    {
        $grid = $this->firstChildElementForOuterPrefix($tableElement, 'a', 'tblGrid', $drawingNamespace);
        if (!$grid instanceof \DOMElement) {
            return [];
        }

        $widths = [];
        foreach ($this->childElementsForOuterPrefix($grid, 'a', 'gridCol', $drawingNamespace) as $column) {
            $width = $this->integerAttribute($column, 'w');
            if ($width !== null) {
                $widths[] = $width;
            }
        }

        return $widths;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableTextStyleMetadata(\DOMElement $textStyle): array
    {
        $style = [];
        foreach (['b' => 'bold', 'i' => 'italic'] as $source => $target) {
            if ($textStyle->hasAttribute($source)) {
                $style[$target] = $this->xmlBooleanValue($textStyle->getAttribute($source));
            }
        }

        foreach (['u' => 'underline', 'strike' => 'strike'] as $source => $target) {
            $value = trim($textStyle->getAttribute($source));
            if ($value !== '') {
                $style[$target] = $value;
            }
        }

        $fontSize = $this->integerAttribute($textStyle, 'sz');
        if ($fontSize !== null) {
            $style['fontSize'] = $fontSize;
        }

        $fontRef = $this->firstChildElement($textStyle, 'fontRef');
        if ($fontRef instanceof \DOMElement) {
            $index = trim($fontRef->getAttribute('idx'));
            if ($index !== '') {
                $style['fontRef'] = $index;
            }

            $color = $this->drawingColorValue($fontRef);
            if ($color !== '') {
                $style['fontRefColor'] = $color;
            }
        }

        $color = $this->drawingColorValue($textStyle);
        if ($color !== '') {
            $style['textColor'] = $color;
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellStyleContainerMetadata(\DOMElement $properties): array
    {
        $style = $this->tableCellPropertyAttributes($properties);
        $fillColor = $this->tableFillMetadata($properties);
        if (is_string($fillColor['color'] ?? null) && $fillColor['color'] !== '') {
            $style['fillColor'] = $fillColor['color'];
            if (is_array($fillColor['transforms'] ?? null) && $fillColor['transforms'] !== []) {
                $style['fillColorTransforms'] = $fillColor['transforms'];
            }
        }

        $borders = [];
        $borderStyles = [];
        $borderNames = ['lnL' => 'left', 'lnR' => 'right', 'lnT' => 'top', 'lnB' => 'bottom', 'lnTlToBr' => 'diagonalDown', 'lnBlToTr' => 'diagonalUp'];
        foreach ($this->childElements($properties, null) as $child) {
            if (!isset($borderNames[$child->localName])) {
                continue;
            }

            $border = $this->tableLineStyleMetadata($child);
            if ($border === []) {
                continue;
            }

            $side = $borderNames[$child->localName];
            $borderStyles[$side] = $border;
            if (is_string($border['color'] ?? null) && $border['color'] !== '') {
                $borders[$side] = $border['color'];
            }
        }
        if ($borders !== []) {
            $style['borders'] = $borders;
        }
        if ($borderStyles !== []) {
            $style['borderStyles'] = $borderStyles;
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellPropertyAttributes(\DOMElement $properties): array
    {
        $style = [];
        foreach ([
            'anchor' => 'verticalAlign',
            'vert' => 'textDirection',
            'horzOverflow' => 'horizontalOverflow',
        ] as $source => $target) {
            $value = trim($properties->getAttribute($source));
            if ($value !== '') {
                $style[$target] = $value;
            }
        }

        $anchorCentered = $this->xmlBooleanAttribute($properties, 'anchorCtr');
        if ($anchorCentered !== null) {
            $style['anchorCentered'] = $anchorCentered;
        }

        foreach (['marL' => 'marginLeft', 'marR' => 'marginRight', 'marT' => 'marginTop', 'marB' => 'marginBottom'] as $source => $target) {
            $value = $this->integerAttribute($properties, $source);
            if ($value !== null) {
                $style[$target] = $value;
            }
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableFillMetadata(\DOMElement $properties): array
    {
        foreach ($this->childElements($properties, null) as $child) {
            if (!in_array($child->localName, ['solidFill', 'fill', 'fillRef'], true)) {
                continue;
            }

            $metadata = $this->drawingColorMetadata($child);
            if (is_string($metadata['color'] ?? null) && $metadata['color'] !== '') {
                return $metadata;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function tableLineStyleMetadata(\DOMElement $line): array
    {
        $style = [];
        $color = $this->drawingColorMetadata($line);
        if (is_string($color['color'] ?? null) && $color['color'] !== '') {
            $style['color'] = $color['color'];
            if (is_array($color['transforms'] ?? null) && $color['transforms'] !== []) {
                $style['colorTransforms'] = $color['transforms'];
            }
        }

        $width = $this->integerAttribute($line, 'w');
        if ($width !== null) {
            $style['width'] = $width;
        }

        foreach (['cap', 'cmpd', 'algn'] as $attribute) {
            $value = trim($line->getAttribute($attribute));
            if ($value !== '') {
                $style[$attribute] = $value;
            }
        }

        $dash = $this->firstChildElement($line, 'prstDash');
        if ($dash instanceof \DOMElement && $dash->getAttribute('val') !== '') {
            $style['dash'] = $dash->getAttribute('val');
        }

        foreach (['round', 'bevel', 'miter'] as $joinName) {
            $join = $this->firstChildElement($line, $joinName);
            if (!$join instanceof \DOMElement) {
                continue;
            }

            $style['join'] = $joinName;
            $miterLimit = $this->integerAttribute($join, 'lim');
            if ($miterLimit !== null) {
                $style['miterLimit'] = $miterLimit;
            }
            break;
        }

        foreach (['headEnd', 'tailEnd'] as $endName) {
            $lineEnd = $this->firstChildElement($line, $endName);
            if ($lineEnd instanceof \DOMElement) {
                $metadata = $this->lineEndMetadata($lineEnd);
                if ($metadata !== []) {
                    $style[$endName] = $metadata;
                }
            }
        }

        return $style;
    }

    /**
     * @return array<string, string>
     */
    private function lineEndMetadata(\DOMElement $lineEnd): array
    {
        $metadata = [];
        foreach (['type', 'w', 'len'] as $attribute) {
            $value = trim($lineEnd->getAttribute($attribute));
            if ($value !== '') {
                $metadata[$attribute] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function withResolvedThemeColors(array $style, array $theme): array
    {
        $fillColor = is_string($style['fillColor'] ?? null) ? $style['fillColor'] : '';
        $fillTransforms = is_array($style['fillColorTransforms'] ?? null) ? $style['fillColorTransforms'] : [];
        $resolvedFillColor = $this->resolveThemeColor($fillColor, $theme, $fillTransforms);
        if ($resolvedFillColor !== '' && $resolvedFillColor !== $fillColor) {
            $style['resolvedFillColor'] = $resolvedFillColor;
        }

        $borders = is_array($style['borders'] ?? null) ? $style['borders'] : [];
        $resolvedBorders = [];
        foreach ($borders as $side => $color) {
            if (!is_string($side) || !is_string($color)) {
                continue;
            }

            $borderTransforms = [];
            if (is_array($style['borderStyles'][$side]['colorTransforms'] ?? null)) {
                $borderTransforms = $style['borderStyles'][$side]['colorTransforms'];
            }
            $resolved = $this->resolveThemeColor($color, $theme, $borderTransforms);
            if ($resolved !== '' && $resolved !== $color) {
                $resolvedBorders[$side] = $resolved;
            }
        }
        if ($resolvedBorders !== []) {
            $style['resolvedBorders'] = $resolvedBorders;
        }

        if (is_array($style['borderStyles'] ?? null)) {
            foreach ($style['borderStyles'] as $side => $border) {
                if (!is_string($side) || !is_array($border)) {
                    continue;
                }

                $color = is_string($border['color'] ?? null) ? $border['color'] : '';
                $borderTransforms = is_array($border['colorTransforms'] ?? null) ? $border['colorTransforms'] : [];
                $resolved = $this->resolveThemeColor($color, $theme, $borderTransforms);
                if ($resolved !== '' && $resolved !== $color) {
                    $style['borderStyles'][$side]['resolvedColor'] = $resolved;
                }
            }
        }

        return $style;
    }

    /**
     * @param array<string, int> $transforms
     */
    private function resolveThemeColor(string $color, array $theme, array $transforms = []): string
    {
        $resolved = $color;
        if (str_starts_with($color, 'theme:')) {
            $key = substr($color, 6);
            $colors = $theme['colorScheme']['colors'] ?? [];
            $resolved = is_array($colors) && is_string($colors[$key] ?? null) ? $colors[$key] : '';
        }

        if ($resolved === '' || preg_match('/^[0-9A-F]{6}$/', $resolved) !== 1) {
            return $resolved;
        }

        return $transforms !== [] ? $this->applyColorTransforms($resolved, $transforms) : $resolved;
    }

    /**
     * @param array<string, int> $transforms
     */
    private function applyColorTransforms(string $hex, array $transforms): string
    {
        $channels = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        if (isset($transforms['lumMod'])) {
            $factor = max(0, $transforms['lumMod']) / 100000;
            foreach ($channels as $index => $channel) {
                $channels[$index] = $channel * $factor;
            }
        }
        if (isset($transforms['lumOff'])) {
            $offset = 255 * (max(0, $transforms['lumOff']) / 100000);
            foreach ($channels as $index => $channel) {
                $channels[$index] = $channel + $offset;
            }
        }
        if (isset($transforms['shade'])) {
            $factor = max(0, $transforms['shade']) / 100000;
            foreach ($channels as $index => $channel) {
                $channels[$index] = $channel * $factor;
            }
        }
        if (isset($transforms['tint'])) {
            $factor = max(0, $transforms['tint']) / 100000;
            foreach ($channels as $index => $channel) {
                $channels[$index] = $channel + ((255 - $channel) * $factor);
            }
        }

        return strtoupper(implode('', array_map(
            static fn (float|int $channel): string => sprintf('%02X', max(0, min(255, (int) round($channel)))),
            $channels
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellStyleMetadata(\DOMElement $cellElement, array $theme): array
    {
        $properties = $this->firstChildElement($cellElement, 'tcPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $style = $this->tableCellStyleContainerMetadata($properties);
        $style = $this->withResolvedThemeColors($style, $theme);

        return $style;
    }

    private function diagramNode(ZipPackage $package, \DOMElement $graphicData, OpcRelationships $slideRelationships, ?string $relationshipNamespace): ?AstNode
    {
        $relIds = $this->firstChildElement($graphicData, 'relIds');
        if (!$relIds instanceof \DOMElement) {
            return $this->paragraph('[Graphic: diagram-no-relIds]');
        }

        $dataRelId = $this->relationshipAttributeForPrefix($relIds, 'r', 'dm', $relationshipNamespace);
        $layoutRelId = $this->relationshipAttributeForPrefix($relIds, 'r', 'lo', $relationshipNamespace);
        if ($dataRelId === null || $layoutRelId === null) {
            return $this->paragraph('[Graphic: diagram-missing-rels]');
        }

        $dataRelationship = $slideRelationships->byId($dataRelId);
        $layoutRelationship = $slideRelationships->byId($layoutRelId);
        if (!$dataRelationship instanceof OpcRelationship) {
            return $this->paragraph('[Diagram parse error: Relationship not found: ' . $dataRelId . ']');
        }
        if (!$layoutRelationship instanceof OpcRelationship) {
            return $this->paragraph('[Diagram parse error: Relationship not found: ' . $layoutRelId . ']');
        }

        $dataPart = $this->upstreamDiagramPart($dataRelationship->target);
        $layoutPart = $this->upstreamDiagramPart($layoutRelationship->target);
        if (!in_array($dataPart, $package->names(), true)) {
            return $this->paragraph('[Diagram parse error: File not found in archive: ' . $dataPart . ']');
        }

        $dataDocument = $this->diagramPackageXml($package, $dataPart, 'PPTX SmartArt data', $dataError);
        if (!$dataDocument instanceof \DOMDocument) {
            return $this->paragraph('[Diagram parse error: ' . $dataError . ']');
        }

        if (!in_array($layoutPart, $package->names(), true)) {
            return $this->paragraph('[Diagram parse error: File not found in archive: ' . $layoutPart . ']');
        }

        $layoutDocument = $this->diagramPackageXml($package, $layoutPart, 'PPTX SmartArt layout', $layoutError);
        if (!$layoutDocument instanceof \DOMDocument) {
            return $this->paragraph('[Diagram parse error: ' . $layoutError . ']');
        }

        $dataRoot = XmlHtmlDom::rootElement($dataDocument);
        $layoutRoot = XmlHtmlDom::rootElement($layoutDocument);
        if (!$dataRoot instanceof \DOMElement || !$layoutRoot instanceof \DOMElement) {
            return $this->paragraph('[Diagram parse error: diagram-invalid-xml]');
        }
        $dataDiagramNamespace = $this->localNamespaceForPrefix($dataRoot, 'dgm');
        $layoutDiagramNamespace = $this->localNamespaceForPrefix($layoutRoot, 'dgm');
        if (!$this->firstDiagramChildElement($dataRoot, 'ptLst', $dataDiagramNamespace) instanceof \DOMElement) {
            return $this->paragraph('[Diagram parse error: Missing dgm:ptLst]');
        }

        $layoutType = $this->diagramLayoutType($layoutRoot, $layoutDiagramNamespace);
        $children = [];
        foreach ($this->diagramNodes($dataRoot, $dataDiagramNamespace) as $node) {
            $children[] = new AstNode('paragraph', [], [
                new AstNode('strong', [], $this->textInlines($node['text'])),
            ]);
            if ($node['children'] !== []) {
                $children[] = new AstNode('bullet_list', [], array_map(
                    fn (string $child): AstNode => new AstNode('list_item', [], [
                        new AstNode('plain', [], $this->textInlines($child)),
                    ]),
                    $node['children']
                ));
            }
        }

        return new AstNode('div', [
            'classes' => ['smartart', $layoutType],
            'attributes' => ['layout' => $layoutType],
        ], $children);
    }

    private function diagramPackageXml(ZipPackage $package, string $partName, string $label, ?string &$error): ?\DOMDocument
    {
        try {
            $error = null;

            return $this->loadPackageXml($package, $partName, $label);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $error = $exception->getMessage();

            return null;
        }
    }

    private function upstreamDiagramPart(string $target): string
    {
        if (str_starts_with($target, '../diagrams/')) {
            return 'ppt/diagrams/' . substr($target, strlen('../diagrams/'));
        }

        return $target;
    }

    private function diagramLayoutType(\DOMElement $layoutRoot, ?string $diagramNamespace): string
    {
        if ($layoutRoot->hasAttribute('uniqueId')) {
            $uniqueId = $layoutRoot->getAttribute('uniqueId');
            $position = strrpos($uniqueId, '/');

            return $position === false ? $uniqueId : substr($uniqueId, $position + 1);
        }

        $title = $this->firstDiagramChildElement($layoutRoot, 'title', $diagramNamespace);

        return $title instanceof \DOMElement && $title->hasAttribute('val') ? $title->getAttribute('val') : 'unknown';
    }

    /**
     * @return list<array{text:string, children:list<string>}>
     */
    private function diagramNodes(\DOMElement $dataRoot, ?string $diagramNamespace): array
    {
        $pointList = $this->firstDiagramChildElement($dataRoot, 'ptLst', $diagramNamespace);
        if (!$pointList instanceof \DOMElement) {
            return [];
        }

        $nodeText = [];
        foreach ($this->diagramChildElements($pointList, 'pt', $diagramNamespace) as $pointElement) {
            if (!$pointElement->hasAttribute('modelId')) {
                continue;
            }
            $modelId = $pointElement->getAttribute('modelId');
            $textElement = $this->firstDiagramChildElement($pointElement, 't', $diagramNamespace);
            $text = $textElement instanceof \DOMElement ? $this->allDescendantText($textElement) : '';
            if ($this->hasNonWhitespaceText($text)) {
                $nodeText[$modelId] = $text;
            }
        }

        $childrenByParent = [];
        $connectionList = $this->firstDiagramChildElement($dataRoot, 'cxnLst', $diagramNamespace);
        if ($connectionList instanceof \DOMElement) {
            foreach ($this->diagramChildElements($connectionList, 'cxn', $diagramNamespace) as $connectionElement) {
                if ($connectionElement->getAttribute('type') !== '') {
                    continue;
                }
                if (!$connectionElement->hasAttribute('srcId') || !$connectionElement->hasAttribute('destId')) {
                    continue;
                }
                $sourceId = $connectionElement->getAttribute('srcId');
                $destinationId = $connectionElement->getAttribute('destId');
                $childrenByParent[$sourceId] ??= [];
                $childrenByParent[$sourceId][] = $destinationId;
            }
        }

        $parentIds = array_keys($childrenByParent);
        sort($parentIds, SORT_STRING);
        $nodes = [];
        foreach ($parentIds as $parentId) {
            $text = $nodeText[$parentId] ?? '';
            if ($text === '') {
                continue;
            }

            $children = [];
            foreach ($childrenByParent[$parentId] as $childId) {
                $childText = $nodeText[$childId] ?? '';
                if ($childText !== '') {
                    $children[] = $childText;
                }
            }
            $nodes[] = ['text' => $text, 'children' => $children];
        }

        return $nodes;
    }

    private function hasNonWhitespaceText(string $text): bool
    {
        return preg_match('/\S/u', $text) === 1;
    }

    private function firstDiagramChildElement(\DOMElement $parent, string $localName, ?string $diagramNamespace): ?\DOMElement
    {
        foreach ($this->diagramChildElements($parent, $localName, $diagramNamespace) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function diagramChildElements(\DOMElement $parent, string $localName, ?string $diagramNamespace): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => $child->namespaceURI === $diagramNamespace
                && ($diagramNamespace !== null || $child->prefix === 'dgm')
        ));
    }

    private function firstPresentationChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($this->presentationChildElements($parent, $localName) as $child) {
            return $child;
        }

        return null;
    }

    private function firstDrawingChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($this->drawingChildElements($parent, $localName) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function presentationChildElements(\DOMElement $parent, string $localName): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => self::isPresentationNamespace($child->namespaceURI)
        ));
    }

    /**
     * @return list<\DOMElement>
     */
    private function drawingChildElements(\DOMElement $parent, string $localName): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => self::isDrawingNamespace($child->namespaceURI)
        ));
    }

    private function firstNamespacedChildElement(\DOMElement $parent, string $localName, string $namespace): ?\DOMElement
    {
        foreach ($this->namespacedChildElements($parent, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function namespacedChildElements(\DOMElement $parent, string $localName, string $namespace): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => $child->namespaceURI === $namespace
        ));
    }

    private function isPresentationElement(\DOMElement $element, string $localName): bool
    {
        return $element->localName === $localName
            && self::isPresentationNamespace($element->namespaceURI);
    }

    private function isDrawingElement(\DOMElement $element, string $localName): bool
    {
        return $element->localName === $localName
            && self::isDrawingNamespace($element->namespaceURI);
    }

    private static function isPresentationNamespace(?string $namespace): bool
    {
        return in_array($namespace, self::PRESENTATION_NAMESPACES, true);
    }

    private static function isDrawingNamespace(?string $namespace): bool
    {
        return in_array($namespace, self::DRAWING_NAMESPACES, true);
    }

    private function isTitlePlaceholder(\DOMElement $shapeElement): bool
    {
        $nonVisualProperties = $this->firstPresentationChildElement($shapeElement, 'nvSpPr');
        $placeholderContainer = $nonVisualProperties instanceof \DOMElement ? $this->firstPresentationChildElement($nonVisualProperties, 'nvPr') : null;
        $placeholder = $placeholderContainer instanceof \DOMElement ? $this->firstPresentationChildElement($placeholderContainer, 'ph') : null;
        if (!$placeholder instanceof \DOMElement) {
            return false;
        }

        $type = $placeholder->getAttribute('type');

        return $type === 'title' || $type === 'ctrTitle';
    }

    private function isTitlePlaceholderForPrefix(\DOMElement $shapeElement, ?string $presentationNamespace): bool
    {
        $nonVisualProperties = $this->firstChildElementForOuterPrefix($shapeElement, 'p', 'nvSpPr', $presentationNamespace);
        $placeholderContainer = $nonVisualProperties instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($nonVisualProperties, 'p', 'nvPr', $presentationNamespace) : null;
        $placeholder = $placeholderContainer instanceof \DOMElement ? $this->firstChildElementForOuterPrefix($placeholderContainer, 'p', 'ph', $presentationNamespace) : null;
        if (!$placeholder instanceof \DOMElement) {
            return false;
        }

        $type = $placeholder->getAttribute('type');

        return $type === 'title' || $type === 'ctrTitle';
    }

    private function paragraph(string $text): AstNode
    {
        return new AstNode('paragraph', ['text' => $text], $this->textInlines($text));
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @return list<AstNode>
     */
    private function pptxTextInlines(string $text): array
    {
        if ($text !== '') {
            return $this->textInlines($text);
        }

        return [
            new AstNode('text', [
                'text' => '',
            ]),
        ];
    }

    private function drawingText(\DOMElement $element): string
    {
        $texts = [];
        $this->collectDrawingText($element, $texts);

        return implode(' ', $texts);
    }

    /**
     * @param list<string> $texts
     */
    private function collectDrawingText(\DOMElement $element, array &$texts): void
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 't') {
                $text = $this->directTextContent($child);
                if ($text !== '') {
                    $texts[] = $text;
                }
                continue;
            }

            $this->collectDrawingText($child, $texts);
        }
    }

    private function directTextContent(\DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text .= $child->nodeValue ?? '';
            }
        }

        return $text;
    }

    private function allDescendantText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (($child->nodeValue ?? '') !== '') {
                    $texts[] = $child->nodeValue ?? '';
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                $text = $this->allDescendantText($child);
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode(' ', $texts);
    }

    private function relationshipId(\DOMElement $element, string $localName): string
    {
        return $this->relationshipAttribute($element, $localName) ?? '';
    }

    private function relationshipAttribute(\DOMElement $element, string $localName): ?string
    {
        foreach (self::RELATIONSHIP_NAMESPACES as $namespace) {
            if ($element->hasAttributeNS($namespace, $localName)) {
                return $element->getAttributeNS($namespace, $localName);
            }
        }

        return null;
    }

    private function relationshipAttributeForPrefix(\DOMElement $element, string $prefix, string $localName, ?string $outerNamespace): ?string
    {
        $namespace = $outerNamespace ?? $this->localNamespaceForPrefix($element, $prefix);
        if ($namespace === null) {
            return null;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            if (
                $attribute->localName === $localName
                && $attribute->namespaceURI === $namespace
            ) {
                return $attribute->value;
            }
        }

        return null;
    }

    private function localNamespaceForPrefix(\DOMElement $element, string $prefix): ?string
    {
        $attribute = $prefix === '' ? 'xmlns' : 'xmlns:' . $prefix;
        if ($element->hasAttribute($attribute)) {
            return $element->getAttribute($attribute);
        }

        if ($prefix !== '' && $element->hasAttributeNS('http://www.w3.org/2000/xmlns/', $prefix)) {
            return $element->getAttributeNS('http://www.w3.org/2000/xmlns/', $prefix);
        }

        return null;
    }

    private function firstChildElementForPrefix(\DOMElement $parent, string $prefix, string $localName, ?string $namespace): ?\DOMElement
    {
        foreach ($this->childElementsForPrefix($parent, $prefix, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    private function firstChildElementForOuterPrefix(\DOMElement $parent, string $prefix, string $localName, ?string $outerNamespace): ?\DOMElement
    {
        return $this->firstChildElementForPrefix(
            $parent,
            $prefix,
            $localName,
            $outerNamespace ?? $this->localNamespaceForPrefix($parent, $prefix)
        );
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElementsForOuterPrefix(\DOMElement $parent, string $prefix, string $localName, ?string $outerNamespace): array
    {
        return $this->childElementsForPrefix(
            $parent,
            $prefix,
            $localName,
            $outerNamespace ?? $this->localNamespaceForPrefix($parent, $prefix)
        );
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElementsForPrefix(\DOMElement $parent, string $prefix, string $localName, ?string $namespace): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => $child->namespaceURI === $namespace
                && ($namespace !== null || $child->prefix === $prefix)
        ));
    }

    private function firstChildElementInNamespace(\DOMElement $parent, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($this->childElementsInNamespace($parent, $namespace, $localName) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElementsInNamespace(\DOMElement $parent, string $namespace, string $localName): array
    {
        return array_values(array_filter(
            $this->childElements($parent, $localName),
            static fn (\DOMElement $child): bool => $child->namespaceURI === $namespace
        ));
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, ?string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && ($localName === null || $child->localName === $localName)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function firstChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}
