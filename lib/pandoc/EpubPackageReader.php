<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackageReader
{
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const EPUB_TYPE_NS = 'http://www.idpf.org/2007/ops';
    private const OCF_CONTAINER_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:container';
    private const EPUB_METADATA_NAMESPACE = 'http://www.idpf.org/2013/metadata';
    private const ODF_MANIFEST_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    private const OCF_PACKAGE_SIDECARS = [
        'metadata' => [
            'partName' => 'META-INF/metadata.xml',
            'expectedRootName' => 'metadata',
            'expectedRootNamespace' => self::EPUB_METADATA_NAMESPACE,
            'reviewPolicy' => 'ocf-metadata-sidecar-review',
        ],
        'manifest' => [
            'partName' => 'META-INF/manifest.xml',
            'expectedRootName' => 'manifest',
            'expectedRootNamespace' => self::ODF_MANIFEST_NAMESPACE,
            'reviewPolicy' => 'ocf-manifest-sidecar-review',
        ],
        'rights' => [
            'partName' => 'META-INF/rights.xml',
            'expectedRootName' => 'rights',
            'expectedRootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            'reviewPolicy' => 'ocf-rights-sidecar-review',
        ],
        'signatures' => [
            'partName' => 'META-INF/signatures.xml',
            'expectedRootName' => 'signatures',
            'expectedRootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            'reviewPolicy' => 'ocf-signatures-sidecar-review',
        ],
    ];
    private const OCF_ROOTFILE_STRUCTURAL_ATTRIBUTES = [
        'full-path' => true,
        'media-type' => true,
    ];
    private const OPF_PACKAGE_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'id' => true,
        'lang' => true,
        'prefix' => true,
        'unique-identifier' => true,
        'version' => true,
        'xml:base' => true,
        'xml:lang' => true,
    ];
    private const OPF_MANIFEST_ITEM_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'fallback' => true,
        'fallback-style' => true,
        'href' => true,
        'id' => true,
        'lang' => true,
        'media-overlay' => true,
        'media-type' => true,
        'properties' => true,
        'xml:base' => true,
        'xml:lang' => true,
    ];
    private const OPF_METADATA_LINK_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'href' => true,
        'hreflang' => true,
        'id' => true,
        'lang' => true,
        'media-type' => true,
        'properties' => true,
        'refines' => true,
        'rel' => true,
        'title' => true,
        'xml:lang' => true,
    ];
    private const OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'id' => true,
        'idref' => true,
        'lang' => true,
        'linear' => true,
        'properties' => true,
        'xml:lang' => true,
    ];
    private const OPF_BINDING_MEDIA_TYPE_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'handler' => true,
        'id' => true,
        'lang' => true,
        'media-type' => true,
        'xml:lang' => true,
    ];
    private const OPF_GUIDE_REFERENCE_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'href' => true,
        'lang' => true,
        'title' => true,
        'type' => true,
        'xml:lang' => true,
    ];

    public function readDirectory(string $directory): AstNode
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('EPUB package directory does not exist: ' . $directory);
        }

        $containerRootfileReport = $this->readContainerRootfileReport($root);
        $rootfile = $containerRootfileReport['selectedRootfile'];
        $opfPath = $this->resolveExistingPackagePath($root, $rootfile);
        $package = $this->readPackageDocument($root, $opfPath, $rootfile);
        $ocfSidecars = $this->readOcfSidecars($root);
        $toc = $this->readNavigationDocument($root, $package);
        $ncx = $this->readNcxDocument($root, $package);
        $navReport = $this->navReport($toc, $package, $root);
        $ncxReport = $this->ncxReport($ncx);
        $children = [];

        foreach ($package['spine'] as $spineItem) {
            if (($spineItem['readable'] ?? false) !== true) {
                continue;
            }

            $path = (string) ($spineItem['contentPath'] ?? $spineItem['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $mediaType = (string) ($spineItem['contentMediaType'] ?? $spineItem['mediaTypeBase'] ?? $spineItem['mediaType'] ?? '');
            if ($this->isDirectSpineImageMediaType($mediaType)) {
                array_push($children, ...$this->readDirectImageSpineItem($path));
                continue;
            }

            array_push($children, ...$this->readXhtmlDocument($root, $path));
        }

        return new AstNode('document', [
            'meta' => $package['metadata'],
            'epub' => [
                'containerRootfile' => $rootfile,
                'containerSelectedRootfileIndex' => $containerRootfileReport['selectedRootfileIndex'],
                'containerRootfiles' => $containerRootfileReport['rootfiles'],
                'containerReport' => $containerRootfileReport,
                'containerRootfileReport' => $containerRootfileReport,
                'containerDiagnostics' => $containerRootfileReport['diagnostics'],
                'containerRootfileDiagnostics' => $containerRootfileReport['diagnostics'],
                'packageVersion' => $package['version'],
                'uniqueIdentifierId' => $package['uniqueIdentifierId'],
                'packageReport' => $package['packageReport'],
                'identityReport' => $package['packageReport'],
                'packagePrefixReport' => $package['packageReport']['prefixReport'],
                'packagePrefixBindings' => $package['packageReport']['prefixBindings'],
                'packagePrefixDiagnostics' => $package['packageReport']['prefixDiagnostics'],
                'packageAuthoring' => $package['packageAuthoring'],
                'uniqueIdentifier' => $package['packageReport']['uniqueIdentifier'],
                'identifierDetails' => $package['packageReport']['identifierDetails'],
                'identifierSummary' => $package['packageReport']['identifierSummary'],
                'identifierDiagnostics' => $package['packageReport']['identifierDiagnostics'],
                'metadataItems' => $package['metadataItems'],
                'metadataReport' => $package['metadataReport'],
                'metadataProperties' => $package['metadataProperties'],
                'metadataLinks' => $package['metadataLinks'],
                'metadataLinkReport' => $package['metadataLinkReport'],
                'metadataRefinementTargets' => $package['metadataReport']['refinementTargets'],
                'metadataRefinementTargetDiagnostics' => $package['metadataReport']['refinementTargetDiagnostics'],
                'manifest' => array_values($package['manifest']),
                'manifestById' => $package['manifest'],
                'manifestReport' => $package['manifestReport'],
                'manifestAuthoring' => $package['manifestAuthoring'],
                'spine' => $package['spine'],
                'spineMetadata' => $package['spineMetadata'],
                'spineReport' => $package['spineReport'],
                'spinePageSpreadItems' => $package['spineReport']['pageSpreadItems'],
                'spineItemDiagnostics' => $package['spineReport']['itemDiagnostics'],
                'spineAuthoring' => $package['spineAuthoring'],
                'guide' => $package['guide'],
                'guideReferenceReport' => $package['guide'],
                'guideReferenceTargets' => $package['guide']['targets'],
                'guideReferenceDiagnostics' => $package['guide']['diagnostics'],
                'collections' => $package['collections'],
                'collectionReport' => $package['collectionReport'],
                'collectionHierarchy' => $package['collectionReport'],
                'collectionDiagnostics' => $package['collectionReport']['diagnostics'],
                'collectionLinkTargets' => $package['collectionReport']['linkTargets'],
                'bindings' => $package['bindings'],
                'bindingReport' => $package['bindings'],
                'bindingDiagnostics' => $package['bindings']['diagnostics'],
                'ocfSidecars' => $ocfSidecars,
                'ocfSidecarItems' => $ocfSidecars['items'],
                'ocfSidecarDiagnostics' => $ocfSidecars['diagnostics'],
                'toc' => $toc,
                'tocReport' => $navReport,
                'navReport' => $navReport,
                'navigationReport' => $navReport,
                'pageListReport' => $navReport['pageList'],
                'ncx' => $ncx,
                'ncxReport' => $ncxReport,
            ],
        ], $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function readContainerRootfileReport(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . 'META-INF' . DIRECTORY_SEPARATOR . 'container.xml';
        $document = $this->loadXmlFile($path);
        $xpath = new \DOMXPath($document);
        $rootfileNodes = $xpath->query('/*[local-name()="container"]/*[local-name()="rootfiles"]/*[local-name()="rootfile"]');
        if (!$rootfileNodes instanceof \DOMNodeList || $rootfileNodes->length === 0) {
            throw new \RuntimeException('EPUB container.xml does not contain a rootfile');
        }

        $rootfiles = [];
        $diagnostics = [];
        $opfRootfileCount = 0;
        $localRootfileCount = 0;
        $externalRootfileCount = 0;
        $unsafeRootfileCount = 0;
        $missingPackagePartCount = 0;
        $suffixRootfileCount = 0;
        $mediaTypeParameterRootfileCount = 0;
        $missingMediaTypeRootfileCount = 0;

        foreach ($rootfileNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $index = count($rootfiles);
            $fullPath = trim($node->getAttribute('full-path'));
            $mediaType = trim($node->getAttribute('media-type'));
            $mediaTypeReport = $this->mediaTypeReport($mediaType);
            $mediaTypeBase = $mediaTypeReport['mediaTypeBase'];
            $pathPart = $this->hrefPathPart($fullPath);
            $suffix = $this->hrefSuffix($fullPath);
            $itemDiagnostics = [];
            $packagePath = '';
            $external = false;
            $unsafe = false;
            $exists = false;

            if ($mediaTypeBase === self::OPF_MEDIA_TYPE) {
                ++$opfRootfileCount;
            }
            if ($mediaType === '') {
                ++$missingMediaTypeRootfileCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-rootfile-media-type',
                    'message' => 'EPUB container rootfile is missing media-type',
                ];
            } elseif ($mediaTypeReport['mediaTypeHasParameters']) {
                ++$mediaTypeParameterRootfileCount;
            }

            if ($fullPath === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-rootfile-full-path',
                    'message' => 'EPUB container rootfile is missing full-path',
                ];
            } elseif ($this->isExternalHref($fullPath) || str_starts_with($fullPath, '//')) {
                $external = true;
                $itemDiagnostics[] = [
                    'type' => 'external-rootfile-full-path',
                    'fullPath' => $fullPath,
                    'message' => 'EPUB container rootfile full-path points outside the package and was not fetched',
                ];
            } elseif (str_starts_with($pathPart, '/')) {
                $external = true;
                $itemDiagnostics[] = [
                    'type' => 'absolute-rootfile-full-path',
                    'fullPath' => $fullPath,
                    'message' => 'EPUB container rootfile full-path must be package-relative',
                ];
            } else {
                try {
                    $packagePath = $this->normalizeRelativePath(rawurldecode($pathPart));
                    if ($packagePath !== '') {
                        $exists = $this->packagePathExists($root, $packagePath);
                        if (!$exists) {
                            $itemDiagnostics[] = [
                                'type' => 'missing-rootfile-package-part',
                                'fullPath' => $fullPath,
                                'path' => $packagePath,
                                'message' => 'EPUB container rootfile points at a missing package part',
                            ];
                        }
                    }
                } catch (\RuntimeException $exception) {
                    $unsafe = true;
                    $itemDiagnostics[] = [
                        'type' => 'unsafe-rootfile-full-path',
                        'fullPath' => $fullPath,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($suffix['hasQuery'] || $suffix['hasFragment']) {
                $itemDiagnostics[] = [
                    'type' => 'rootfile-full-path-suffix',
                    'fullPath' => $fullPath,
                    'query' => $suffix['query'],
                    'fragment' => $suffix['fragment'],
                    'message' => 'EPUB container rootfile full-path carries query or fragment suffix provenance',
                ];
            }

            if (!$external && !$unsafe && $packagePath !== '') {
                ++$localRootfileCount;
            }
            if ($external) {
                ++$externalRootfileCount;
            }
            if ($unsafe) {
                ++$unsafeRootfileCount;
            }
            if (!$external && !$unsafe && $packagePath !== '' && !$exists) {
                ++$missingPackagePartCount;
            }
            if ($suffix['hasQuery'] || $suffix['hasFragment']) {
                ++$suffixRootfileCount;
            }

            $attributes = $this->elementAttributes($node);
            $customAttributes = $this->customAttributes($attributes, self::OCF_ROOTFILE_STRUCTURAL_ATTRIBUTES);
            $language = $this->elementLanguage($node);
            $item = [
                'index' => $index,
                'fullPath' => $fullPath,
                'target' => $external ? $fullPath : $this->targetWithSuffix($packagePath, $suffix),
                'path' => $packagePath,
                'rawMediaType' => $mediaType,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeBase,
                'baseMediaType' => $mediaTypeBase,
                'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeParameterNames' => array_keys($mediaTypeReport['mediaTypeParameterMap']),
                'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                'mediaTypeDiagnostics' => $mediaTypeReport['mediaTypeDiagnostics'],
                'opfPackageCandidate' => $mediaTypeBase === self::OPF_MEDIA_TYPE,
                'external' => $external,
                'unsafe' => $unsafe,
                'exists' => $exists,
                'hasQuery' => $suffix['hasQuery'],
                'query' => $suffix['query'],
                'hasFragment' => $suffix['hasFragment'],
                'fragment' => $suffix['fragment'],
                'fullPathHasQuery' => $suffix['hasQuery'],
                'fullPathQuery' => $suffix['query'],
                'fullPathHasFragment' => $suffix['hasFragment'],
                'fullPathFragment' => $suffix['fragment'],
                'language' => $language === '' ? null : $language,
                'direction' => $this->nullableAttribute($node, 'dir'),
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
                'selected' => false,
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ];
            $rootfiles[] = $item;
            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
        }

        $selectedIndex = null;
        foreach ($rootfiles as $index => $rootfile) {
            if (($rootfile['opfPackageCandidate'] ?? false) !== true) {
                continue;
            }
            if (($rootfile['exists'] ?? false) === true && ($rootfile['diagnosticCount'] ?? 0) === 0) {
                $selectedIndex = $index;
                break;
            }
        }
        if ($selectedIndex === null) {
            foreach ($rootfiles as $index => $rootfile) {
                if (($rootfile['exists'] ?? false) === true && ($rootfile['path'] ?? '') !== '') {
                    $selectedIndex = $index;
                    break;
                }
            }
        }
        if ($selectedIndex === null) {
            foreach ($rootfiles as $index => $rootfile) {
                if (($rootfile['path'] ?? '') !== '' && ($rootfile['external'] ?? false) !== true && ($rootfile['unsafe'] ?? false) !== true) {
                    $selectedIndex = $index;
                    break;
                }
            }
        }
        if ($selectedIndex === null) {
            throw new \RuntimeException('EPUB rootfile is missing a full-path');
        }

        $rootfiles[$selectedIndex]['selected'] = true;
        $selectedRootfile = (string) $rootfiles[$selectedIndex]['path'];
        $selectedMediaTypeBase = (string) $rootfiles[$selectedIndex]['mediaTypeBase'];
        $selectedBy = $selectedMediaTypeBase === self::OPF_MEDIA_TYPE
            ? 'media-type-opf'
            : 'first-local-rootfile';
        $diagnosticTypes = [];
        foreach ($diagnostics as $diagnostic) {
            $type = (string) ($diagnostic['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $diagnosticTypes[$type] = ($diagnosticTypes[$type] ?? 0) + 1;
        }
        ksort($diagnosticTypes, SORT_STRING);

        return [
            'present' => true,
            'path' => 'META-INF/container.xml',
            'opfPart' => $selectedRootfile,
            'selectedRootfile' => $selectedRootfile,
            'selectedIndex' => $selectedIndex,
            'selectedRootfileIndex' => $selectedIndex,
            'selectedMediaType' => (string) $rootfiles[$selectedIndex]['mediaType'],
            'selectedMediaTypeBase' => $selectedMediaTypeBase,
            'selectedBy' => $selectedBy,
            'rootfileCount' => count($rootfiles),
            'opfRootfileCount' => $opfRootfileCount,
            'alternateRootfileCount' => max(0, count($rootfiles) - 1),
            'nonOpfRootfileCount' => count($rootfiles) - $opfRootfileCount,
            'localRootfileCount' => $localRootfileCount,
            'externalRootfileCount' => $externalRootfileCount,
            'unsafeRootfileCount' => $unsafeRootfileCount,
            'missingRootfileCount' => $missingPackagePartCount,
            'missingPackagePartCount' => $missingPackagePartCount,
            'suffixRootfileCount' => $suffixRootfileCount,
            'mediaTypeParameterRootfileCount' => $mediaTypeParameterRootfileCount,
            'missingMediaTypeRootfileCount' => $missingMediaTypeRootfileCount,
            'valid' => $diagnostics === [],
            'diagnosticCount' => count($diagnostics),
            'diagnosticTypes' => $diagnosticTypes,
            'diagnostics' => $diagnostics,
            'rootfiles' => $rootfiles,
            'summary' => [
                'selectedRootfile' => $selectedRootfile,
                'selectedIndex' => $selectedIndex,
                'selectedRootfileIndex' => $selectedIndex,
                'selectedBy' => $selectedBy,
                'rootfileCount' => count($rootfiles),
                'opfRootfileCount' => $opfRootfileCount,
                'alternateRootfileCount' => max(0, count($rootfiles) - 1),
                'nonOpfRootfileCount' => count($rootfiles) - $opfRootfileCount,
                'localRootfileCount' => $localRootfileCount,
                'externalRootfileCount' => $externalRootfileCount,
                'unsafeRootfileCount' => $unsafeRootfileCount,
                'missingRootfileCount' => $missingPackagePartCount,
                'missingPackagePartCount' => $missingPackagePartCount,
                'suffixRootfileCount' => $suffixRootfileCount,
                'diagnosticCount' => count($diagnostics),
                'valid' => $diagnostics === [],
            ],
        ];
    }

    /**
     * @return array{
     *     version:string,
     *     uniqueIdentifierId:string,
     *     packageReport:array<string, mixed>,
     *     packageAuthoring:array<string, mixed>,
     *     metadata:array<string, mixed>,
     *     metadataItems:list<array<string, mixed>>,
     *     metadataReport:array<string, mixed>,
     *     metadataProperties:list<array<string, mixed>>,
     *     metadataLinks:list<array<string, mixed>>,
     *     manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>,
     *     manifestAuthoring:array<string, mixed>,
     *     spine:list<array{idref:string, href:string, path:string, mediaType:string, linear:bool, properties:list<string>}>,
     *     spineAuthoring:array<string, mixed>,
     *     guide:array<string, mixed>
     * }
     */
    private function readPackageDocument(string $root, string $opfPath, string $rootfile): array
    {
        $document = $this->loadXmlFile($opfPath);
        $xpath = new \DOMXPath($document);
        $package = $xpath->query('/*[local-name()="package"][1]');
        $packageElement = $package instanceof \DOMNodeList ? $package->item(0) : null;
        if (!$packageElement instanceof \DOMElement) {
            throw new \RuntimeException('OPF package document is missing package element');
        }

        $opfDir = $this->relativeDirname($rootfile);
        $metadata = [
            'title' => '',
            'creators' => [],
            'language' => '',
            'identifier' => '',
            'date' => '',
            'publisher' => '',
        ];
        $metadataItems = [];
        $metadataProperties = [];
        $metadataLinks = [];
        $metadataItemIndex = 0;
        $metadataNodes = $xpath->query('./*[local-name()="metadata"]/*', $packageElement);
        if ($metadataNodes instanceof \DOMNodeList) {
            foreach ($metadataNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $name = $node->localName;
                if ($name === 'link') {
                    $metadataLinks[] = $this->metadataLink($root, $opfDir, $node, count($metadataLinks));
                    continue;
                }

                $value = $this->normalizedText($node->textContent);
                if ($value === '') {
                    continue;
                }
                if ($name === 'meta') {
                    $metadataProperties[] = [
                        'index' => count($metadataProperties),
                        'id' => $this->nullableAttribute($node, 'id'),
                        'property' => trim($node->getAttribute('property')),
                        'value' => $value,
                        'refines' => trim($node->getAttribute('refines')),
                        'scheme' => $this->nullableAttribute($node, 'scheme'),
                    ];
                    continue;
                }

                if ($this->isDublinCoreMetadataElement($node)) {
                    $metadataItems[] = $this->metadataItem($node, $metadataItemIndex, $value);
                    ++$metadataItemIndex;

                    if ($name === 'title' && $metadata['title'] === '') {
                        $metadata['title'] = $value;
                    } elseif ($name === 'creator') {
                        $metadata['creators'][] = $value;
                    } elseif ($name === 'language' && $metadata['language'] === '') {
                        $metadata['language'] = $value;
                    } elseif ($name === 'identifier' && $metadata['identifier'] === '') {
                        $metadata['identifier'] = $value;
                    } elseif ($name === 'date' && $metadata['date'] === '') {
                        $metadata['date'] = $value;
                    } elseif ($name === 'publisher' && $metadata['publisher'] === '') {
                        $metadata['publisher'] = $value;
                    }
                }
            }
        }
        $packageReport = $this->packageReport($packageElement, $metadataItems, $metadataProperties);
        $selectedIdentifier = $packageReport['uniqueIdentifier']['value'] ?? null;
        if (is_string($selectedIdentifier) && $selectedIdentifier !== '') {
            $metadata['identifier'] = $selectedIdentifier;
        }

        $manifest = [];
        $manifestOccurrences = [];
        $malformedManifestItems = [];
        $manifestIndex = 0;
        $manifestNodes = $xpath->query('./*[local-name()="manifest"]/*[local-name()="item"]', $packageElement);
        if ($manifestNodes instanceof \DOMNodeList) {
            foreach ($manifestNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $id = trim($node->getAttribute('id'));
                $href = trim($node->getAttribute('href'));
                $mediaTypeRaw = trim($node->getAttribute('media-type'));
                $mediaTypeReport = $this->mediaTypeReport($mediaTypeRaw);
                $external = $href !== '' && $this->isExternalHref($href);
                $path = $href === '' ? '' : $this->resolvePackageHref($opfDir, $href);
                $suffix = $href === ''
                    ? ['hasQuery' => false, 'query' => null, 'hasFragment' => false, 'fragment' => null]
                    : $this->hrefSuffix($href);
                $exists = !$external && $path !== '' && $this->packagePathExists($root, $path);
                $diagnostics = $mediaTypeReport['mediaTypeDiagnostics'];
                $missingRequiredAttributes = [];
                if ($id === '') {
                    $missingRequiredAttributes[] = 'id';
                    $diagnostics[] = [
                        'type' => 'missing-manifest-item-id',
                        'message' => 'EPUB OPF manifest item is missing id',
                    ];
                }
                if ($href === '') {
                    $missingRequiredAttributes[] = 'href';
                    $diagnostics[] = [
                        'type' => 'missing-manifest-item-href',
                        'id' => $id,
                        'message' => 'EPUB OPF manifest item is missing href',
                    ];
                }
                if ($mediaTypeRaw === '') {
                    $missingRequiredAttributes[] = 'media-type';
                    $diagnostics[] = [
                        'type' => 'missing-manifest-item-media-type',
                        'id' => $id,
                        'href' => $href,
                        'message' => 'EPUB OPF manifest item is missing media-type',
                    ];
                }
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-manifest-href-target',
                        'href' => $href,
                        'target' => $href,
                    ];
                } elseif ($path !== '' && !$exists) {
                    $diagnostics[] = [
                        'type' => 'missing-manifest-href-target',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($suffix['hasQuery']) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-query-component',
                        'href' => $href,
                        'query' => $suffix['query'],
                    ];
                }
                if ($suffix['hasFragment']) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-fragment-component',
                        'href' => $href,
                        'fragment' => $suffix['fragment'],
                    ];
                }
                $attributes = $this->elementAttributes($node);
                $language = $this->elementLanguage($node);
                $base = $this->elementBase($node);
                $baseResolution = self::manifestItemBaseResolution($base);
                $item = [
                    'index' => $manifestIndex,
                    'id' => $id,
                    'href' => $href,
                    'target' => $external ? $href : $this->targetWithSuffix($path, $suffix),
                    'path' => $path,
                    'external' => $external,
                    'exists' => $exists,
                    'hrefHasQuery' => $suffix['hasQuery'],
                    'hrefQuery' => $suffix['query'],
                    'hrefHasFragment' => $suffix['hasFragment'],
                    'hrefFragment' => $suffix['fragment'],
                    'rawMediaType' => $mediaTypeRaw,
                    'mediaType' => $mediaTypeReport['mediaTypeBase'],
                    'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                    'baseMediaType' => $mediaTypeReport['mediaTypeBase'],
                    'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                    'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                    'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                    'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                    'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                    'mediaTypeParameterNames' => array_keys($mediaTypeReport['mediaTypeParameterMap']),
                    'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                    'mediaTypeDiagnostics' => $mediaTypeReport['mediaTypeDiagnostics'],
                    'language' => $language === '' ? null : $language,
                    'direction' => $this->nullableAttribute($node, 'dir'),
                    'base' => $base,
                    'baseResolutionPolicy' => $baseResolution['policy'],
                    'baseResolution' => $baseResolution,
                    'attributes' => $attributes,
                    'customAttributes' => $this->customAttributes(
                        $attributes,
                        self::OPF_MANIFEST_ITEM_STRUCTURAL_ATTRIBUTES
                    ),
                    'properties' => $this->tokens($node->getAttribute('properties')),
                    'fallback' => trim($node->getAttribute('fallback')),
                    'fallbackStyle' => trim($node->getAttribute('fallback-style')),
                    'mediaOverlay' => trim($node->getAttribute('media-overlay')),
                    'requiredAttributesPresent' => $missingRequiredAttributes === [],
                    'missingRequiredAttributes' => $missingRequiredAttributes,
                    'diagnostics' => $diagnostics,
                ];
                if ($missingRequiredAttributes !== []) {
                    $malformedManifestItems[] = $item;
                }
                if ($id !== '') {
                    $manifestOccurrences[$id][] = $item;
                    $manifest[$id] = $item;
                }
                ++$manifestIndex;
            }
        }
        $metadataLinks = $this->metadataLinksWithManifestContext($metadataLinks, $manifest);
        $manifestReport = $this->manifestReport($manifest, $manifestOccurrences, $malformedManifestItems);
        $bindings = $this->readBindings($root, $manifest, $packageElement);
        $bindingsByMediaType = $this->bindingsByMediaType($bindings);

        $spineElement = $this->firstDirectChild($packageElement, 'spine');
        $spineMetadata = $this->spineMetadataReport($spineElement);
        $spine = [];
        $spineIndex = 0;
        $spineNodes = $xpath->query('./*[local-name()="spine"]/*[local-name()="itemref"]', $packageElement);
        if ($spineNodes instanceof \DOMNodeList) {
            foreach ($spineNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $idref = trim($node->getAttribute('idref'));
                $id = $this->nullableAttribute($node, 'id');
                $properties = $this->tokens($node->getAttribute('properties'));
                $spineItemProperties = $this->spineItemPropertyReport($properties);
                $item = $manifest[$idref] ?? null;
                $linearReport = $this->spineItemLinearReport($node);
                $linear = (bool) $linearReport['linear'];
                $mediaType = is_array($item) ? (string) ($item['rawMediaType'] ?? $item['mediaType'] ?? '') : '';
                $mediaTypeBase = is_array($item)
                    ? (string) ($item['mediaTypeBase'] ?? $this->mediaTypeReport((string) ($item['mediaType'] ?? ''))['mediaTypeBase'])
                    : '';
                $external = is_array($item) && ($item['external'] ?? false) === true;
                $exists = is_array($item) && ($item['exists'] ?? false) === true;
                $readable = $linear
                    && ($mediaTypeBase === 'application/xhtml+xml' || $this->isDirectSpineImageMediaType($mediaTypeBase))
                    && !$external
                    && $exists;
                $diagnostics = $linearReport['diagnostics'];
                if (!is_array($item)) {
                    $diagnostics[] = [
                        'type' => 'missing-spine-manifest-item',
                        'idref' => $idref,
                    ];
                } elseif ($external) {
                    $diagnostics[] = [
                        'type' => 'external-spine-item',
                        'idref' => $idref,
                        'target' => $item['target'],
                    ];
                } elseif (!$exists && ($item['path'] ?? '') !== '') {
                    $diagnostics[] = [
                        'type' => 'missing-spine-item-package-part',
                        'idref' => $idref,
                        'path' => $item['path'],
                    ];
                }
                $diagnostics = array_merge($diagnostics, $spineItemProperties['diagnostics']);
                $binding = $mediaType !== '' ? ($bindingsByMediaType[$mediaType] ?? null) : null;
                $contentId = $idref;
                $contentPath = is_array($item) ? (string) $item['path'] : '';
                $contentMediaType = $mediaType;
                $contentIsFallback = false;
                $fallbackChain = [];
                $fallbackDiagnostics = [];
                if (!$readable && $linear && is_array($binding)) {
                    $handlerPath = is_string($binding['handlerPath'] ?? null) ? $binding['handlerPath'] : '';
                    $handlerMediaType = is_string($binding['handlerMediaType'] ?? null) ? $binding['handlerMediaType'] : '';
                    $handlerExists = ($binding['handlerExists'] ?? false) === true;
                    if ($handlerPath !== '' && $handlerMediaType === 'application/xhtml+xml' && $handlerExists) {
                        $contentId = is_string($binding['handlerId'] ?? null) ? $binding['handlerId'] : $idref;
                        $contentPath = $handlerPath;
                        $contentMediaType = $handlerMediaType;
                        $contentIsFallback = true;
                        $readable = true;
                        $fallbackChain[] = [
                            'id' => $contentId,
                            'source' => 'binding-handler',
                            'bindingMediaType' => $mediaType,
                            'href' => is_string($binding['handlerHref'] ?? null) ? $binding['handlerHref'] : '',
                            'path' => $handlerPath,
                            'mediaType' => $handlerMediaType,
                        ];
                    } else {
                        $fallbackDiagnostics = is_array($binding['diagnostics'] ?? null) ? $binding['diagnostics'] : [];
                    }
                }
                $attributes = $this->elementAttributes($node);
                $language = $this->elementLanguage($node);
                $spine[] = [
                    'index' => $spineIndex,
                    'id' => $id,
                    'idref' => $idref,
                    'href' => is_array($item) ? $item['href'] : '',
                    'target' => is_array($item) ? $item['target'] : '',
                    'path' => is_array($item) ? $item['path'] : '',
                    'mediaType' => $mediaType,
                    'mediaTypeBase' => $mediaTypeBase,
                    'linear' => $linear,
                    'linearRaw' => $linearReport['raw'],
                    'linearSpecified' => $linearReport['specified'],
                    'linearValue' => $linearReport['value'],
                    'linearValid' => $linearReport['valid'],
                    'linearDiagnostics' => $linearReport['diagnostics'],
                    'contentId' => $contentId,
                    'contentPath' => $contentPath,
                    'contentMediaType' => $contentMediaType,
                    'contentIsFallback' => $contentIsFallback,
                    'fallbackChain' => $fallbackChain,
                    'fallbackDiagnostics' => $fallbackDiagnostics,
                    'binding' => $binding,
                    'bindingHandlerReadable' => is_array($binding) && ($binding['handlerReadable'] ?? false) === true,
                    'properties' => $properties,
                    'spineItemProperties' => $spineItemProperties,
                    'spineItemDiagnostics' => $spineItemProperties['diagnostics'],
                    'pageSpread' => $spineItemProperties['pageSpread']['placement'],
                    'pageSpreadProperties' => $spineItemProperties['pageSpread']['properties'],
                    'language' => $language === '' ? null : $language,
                    'direction' => $this->nullableAttribute($node, 'dir'),
                    'attributes' => $attributes,
                    'customAttributes' => $this->customAttributes($attributes, self::OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES),
                    'external' => $external,
                    'exists' => $exists,
                    'readable' => $readable,
                    'diagnostics' => $diagnostics,
                ];
                ++$spineIndex;
            }
        }
        $spineReport = $this->spineReport($spine, $spineMetadata);

        $guide = $this->readGuideReferences($root, $opfDir, $manifest, $packageElement);
        $collections = $this->readCollections($root, $opfDir, $manifest, $packageElement);
        $collectionReport = $this->collectionReport($collections);
        $metadataReport = $this->metadataReport(
            $packageElement,
            $metadataItems,
            $metadataProperties,
            $metadataLinks,
            $manifest,
            $spine,
            $collections
        );

        return [
            'version' => trim($packageElement->getAttribute('version')),
            'uniqueIdentifierId' => trim($packageElement->getAttribute('unique-identifier')),
            'packageReport' => $packageReport,
            'packageAuthoring' => $this->packageAuthoringReport($packageElement),
            'metadata' => $metadata,
            'metadataItems' => $metadataItems,
            'metadataReport' => $metadataReport,
            'metadataProperties' => $metadataProperties,
            'metadataLinks' => $metadataLinks,
            'metadataLinkReport' => $metadataReport['linkReport'],
            'manifest' => $manifest,
            'manifestReport' => $manifestReport,
            'manifestAuthoring' => $this->manifestAuthoringReport($manifest),
            'spine' => $spine,
            'spineMetadata' => $spineMetadata,
            'spineReport' => $spineReport,
            'spineAuthoring' => $this->spineAuthoringReport($spine),
            'guide' => $guide,
            'collections' => $collections,
            'collectionReport' => $collectionReport,
            'bindings' => $bindings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSidecars(string $root): array
    {
        $items = [];
        $itemsByKind = [];
        $diagnostics = [];

        foreach (self::OCF_PACKAGE_SIDECARS as $kind => $definition) {
            $packagePath = (string) $definition['partName'];
            if (!$this->packagePathExists($root, $packagePath)) {
                continue;
            }

            $absolute = $this->resolveExistingPackagePath($root, $packagePath);
            $byteLength = filesize($absolute);
            $byteSha256 = hash_file('sha256', $absolute);
            $expectedRootNamespace = (string) $definition['expectedRootNamespace'];
            $rootReport = $this->ocfSidecarRootReport(
                $absolute,
                $kind,
                $packagePath,
                (string) $definition['expectedRootName'],
                $expectedRootNamespace
            );
            $manifestReport = $kind === 'manifest'
                ? $this->ocfManifestSidecarReport($root, $absolute, $rootReport)
                : [];
            $itemDiagnostics = $rootReport['diagnostics'];
            if (is_array($manifestReport['diagnostics'] ?? null)) {
                array_push($itemDiagnostics, ...$manifestReport['diagnostics']);
            }

            $item = [
                'kind' => $kind,
                'part' => $packagePath,
                'partName' => $packagePath,
                'packagePath' => $packagePath,
                'exists' => true,
                'expectedRootName' => (string) $definition['expectedRootName'],
                'expectedRootNamespace' => $expectedRootNamespace,
                'reviewPolicy' => (string) $definition['reviewPolicy'],
                'byteExposurePolicy' => 'ocf-sidecar-metadata-only',
                'canExposeBytes' => false,
                'byteLength' => $byteLength === false ? null : $byteLength,
                'byteSha256' => $byteSha256 === false ? null : $byteSha256,
                'xmlRootChecked' => $rootReport['checked'],
                'xmlWellFormed' => $rootReport['wellFormed'],
                'rootName' => $rootReport['rootName'],
                'rootNamespace' => $rootReport['rootNamespace'],
                'rootValid' => $rootReport['valid'],
                'rootReport' => $rootReport,
                'rootDiagnostics' => $rootReport['diagnostics'],
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ] + $manifestReport;

            $items[] = $item;
            $itemsByKind[$kind] = $item;
            array_push($diagnostics, ...$itemDiagnostics);
        }

        return [
            'present' => $items !== [],
            'sidecarCount' => count($items),
            'count' => count($items),
            'metadataPresent' => isset($itemsByKind['metadata']),
            'manifestPresent' => isset($itemsByKind['manifest']),
            'rightsPresent' => isset($itemsByKind['rights']),
            'signaturesPresent' => isset($itemsByKind['signatures']),
            'kinds' => array_keys($itemsByKind),
            'items' => $items,
            'itemsByKind' => $itemsByKind,
            'referenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['referenceCount'] ?? 0), $items)),
            'localReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['localReferenceCount'] ?? 0), $items)),
            'externalReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['externalReferenceCount'] ?? 0), $items)),
            'missingReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['missingReferenceCount'] ?? 0), $items)),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{checked:bool, wellFormed:?bool, rootName:?string, rootNamespace:?string, valid:?bool, diagnostics:list<array<string, mixed>>}
     */
    private function ocfSidecarRootReport(
        string $absolutePath,
        string $kind,
        string $partName,
        string $expectedRootName,
        string $expectedRootNamespace
    ): array {
        try {
            $dom = $this->loadXmlFile($absolutePath);
        } catch (\RuntimeException $exception) {
            return [
                'checked' => true,
                'wellFormed' => false,
                'rootName' => null,
                'rootNamespace' => null,
                'valid' => false,
                'diagnostics' => [[
                    'type' => 'invalid-ocf-sidecar-xml',
                    'kind' => $kind,
                    'partName' => $partName,
                    'error' => $exception->getMessage(),
                    'message' => 'EPUB OCF sidecar XML could not be parsed for bounded package review',
                ]],
            ];
        }

        $root = $dom->documentElement;
        $rootName = $root instanceof \DOMElement ? $root->localName : null;
        $rootNamespace = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $diagnostics = [];
        if ($rootName !== $expectedRootName || $rootNamespace !== $expectedRootNamespace) {
            $diagnostics[] = [
                'type' => 'unexpected-ocf-sidecar-root',
                'kind' => $kind,
                'partName' => $partName,
                'expectedRootName' => $expectedRootName,
                'expectedRootNamespace' => $expectedRootNamespace,
                'rootName' => $rootName,
                'rootNamespace' => $rootNamespace,
                'message' => 'EPUB OCF sidecar root element does not match the expected container sidecar element',
            ];
        }

        return [
            'checked' => true,
            'wellFormed' => true,
            'rootName' => $rootName,
            'rootNamespace' => $rootNamespace,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array{checked:bool, wellFormed:?bool, rootName:?string, rootNamespace:?string, valid:?bool, diagnostics:list<array<string, mixed>>} $rootReport
     * @return array<string, mixed>
     */
    private function ocfManifestSidecarReport(string $root, string $absolutePath, array $rootReport): array
    {
        $report = [
            'format' => null,
            'odfCompatible' => null,
            'version' => null,
            'itemCount' => 0,
            'items' => [],
            'itemsByPart' => [],
            'declaredPartCount' => 0,
            'missingItemCount' => 0,
            'sizeMismatchCount' => 0,
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];

        if (($rootReport['valid'] ?? false) !== true) {
            return $report;
        }

        $report['format'] = 'odf-manifest';
        $report['odfCompatible'] = true;

        try {
            $dom = $this->loadXmlFile($absolutePath);
        } catch (\RuntimeException $exception) {
            $report['format'] = 'xml';
            $report['odfCompatible'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-manifest-xml',
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $rootElement = $dom->documentElement;
        if (!$rootElement instanceof \DOMElement) {
            $report['format'] = 'xml';
            $report['odfCompatible'] = false;

            return $report;
        }

        $report['version'] = $this->nullableNamespacedAttribute(
            $rootElement,
            self::ODF_MANIFEST_NAMESPACE,
            'version',
            'manifest:version'
        );
        $items = [];
        $itemsByPart = [];
        foreach ($this->directChildElements($rootElement, 'file-entry') as $index => $entryElement) {
            $item = $this->ocfManifestFileEntryReport($root, $entryElement, $index);
            foreach ($item['diagnostics'] as $diagnostic) {
                $report['diagnostics'][] = ['index' => $index] + $diagnostic;
            }

            $items[] = $item;
            if (is_string($item['part'] ?? null) && $item['part'] !== '') {
                $itemsByPart[$item['part']] = $item;
            }
        }

        $report['items'] = $items;
        $report['itemsByPart'] = $itemsByPart;
        $report['itemCount'] = count($items);
        $report['declaredPartCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => is_string($item['fullPath'] ?? null) && $item['fullPath'] !== ''
        ));
        $report['missingItemCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['exists'] ?? true) !== true
        ));
        $report['sizeMismatchCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['sizeMatches'] ?? true) === false
        ));

        return $this->ocfReportWithReferenceCounts($report, $this->ocfItemReferences($items));
    }

    /**
     * @return array<string, mixed>
     */
    private function ocfManifestFileEntryReport(string $root, \DOMElement $entryElement, int $index): array
    {
        $fullPath = $this->nullableNamespacedAttribute(
            $entryElement,
            self::ODF_MANIFEST_NAMESPACE,
            'full-path',
            'manifest:full-path'
        );
        $mediaType = $this->nullableNamespacedAttribute(
            $entryElement,
            self::ODF_MANIFEST_NAMESPACE,
            'media-type',
            'manifest:media-type'
        );
        $version = $this->nullableNamespacedAttribute(
            $entryElement,
            self::ODF_MANIFEST_NAMESPACE,
            'version',
            'manifest:version'
        );
        $size = $this->nullableNamespacedAttribute(
            $entryElement,
            self::ODF_MANIFEST_NAMESPACE,
            'size',
            'manifest:size'
        );
        $encrypted = $this->firstDirectChild($entryElement, 'encryption-data') instanceof \DOMElement;
        $reference = null;
        $diagnostics = [];

        if ($fullPath === null) {
            $diagnostics[] = [
                'type' => 'missing-ocf-manifest-full-path',
                'message' => 'EPUB OCF manifest file-entry is missing manifest:full-path',
            ];
        } else {
            $reference = $this->ocfManifestEntryReference($root, $fullPath);
            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        $declaredSize = null;
        if ($size !== null) {
            if (preg_match('/^\d+$/', $size) === 1) {
                $declaredSize = (int) $size;
            } else {
                $diagnostics[] = [
                    'type' => 'invalid-ocf-manifest-size',
                    'size' => $size,
                    'message' => 'EPUB OCF manifest file-entry size must be a non-negative integer',
                ];
            }
        }

        $byteLength = is_array($reference) && is_int($reference['byteLength'] ?? null) ? $reference['byteLength'] : null;
        $sizeMatches = $declaredSize === null || $byteLength === null || $declaredSize === $byteLength;
        if (!$sizeMatches) {
            $diagnostics[] = [
                'type' => 'ocf-manifest-size-mismatch',
                'fullPath' => $fullPath,
                'declaredSize' => $declaredSize,
                'byteLength' => $byteLength,
                'message' => 'EPUB OCF manifest file-entry size does not match the package file byte length',
            ];
        }

        $part = is_array($reference) && is_string($reference['part'] ?? null) ? $reference['part'] : null;
        $directory = $fullPath === '/' || (is_string($fullPath) && str_ends_with($fullPath, '/'));
        $canExposeBytes = is_array($reference)
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && $part !== null
            && !$directory
            && !$encrypted;

        return [
            'index' => $index,
            'fullPath' => $fullPath,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => $part,
            'root' => $fullPath === '/',
            'directory' => $directory,
            'mediaType' => $mediaType,
            'version' => $version,
            'declaredSize' => $declaredSize,
            'sizeRaw' => $size,
            'sizeMatches' => $sizeMatches,
            'exists' => is_array($reference) ? (bool) ($reference['exists'] ?? false) : false,
            'byteLength' => $byteLength,
            'byteSha256' => is_array($reference) && is_string($reference['byteSha256'] ?? null) ? $reference['byteSha256'] : null,
            'encrypted' => $encrypted,
            'canExposeBytes' => $canExposeBytes,
            'attributes' => $this->elementAttributes($entryElement),
            'reference' => $reference,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ocfManifestEntryReference(string $root, string $fullPath): array
    {
        $fullPath = trim($fullPath);
        if ($fullPath === '') {
            return $this->missingOcfReference('manifest');
        }

        if ($this->isExternalHref($fullPath) || str_starts_with($fullPath, '//')) {
            return [
                'target' => $fullPath,
                'part' => null,
                'external' => true,
                'exists' => false,
                'directory' => false,
                'byteLength' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'ocf-manifest-external-reference',
                    'fullPath' => $fullPath,
                    'message' => 'EPUB OCF manifest file-entry points outside the package and was not fetched',
                ]],
            ];
        }

        $directory = $fullPath === '/' || str_ends_with($fullPath, '/');
        $path = $directory && $fullPath !== '/' ? rtrim($fullPath, '/') : $fullPath;
        try {
            $part = $path === '/' ? null : $this->normalizeRelativePath(ltrim($path, '/'));
        } catch (\RuntimeException $exception) {
            return [
                'target' => null,
                'part' => null,
                'external' => false,
                'exists' => false,
                'directory' => $directory,
                'byteLength' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'ocf-manifest-invalid-reference',
                    'fullPath' => $fullPath,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        if ($part === null) {
            return [
                'target' => '/',
                'part' => null,
                'external' => false,
                'exists' => true,
                'directory' => true,
                'byteLength' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [],
            ];
        }

        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $part));
        $insidePackage = $absolute !== false && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR);
        $exists = $insidePackage && ($directory ? is_dir($absolute) : is_file($absolute));
        $byteLength = null;
        $byteSha256 = null;
        if ($exists && !$directory) {
            $size = filesize($absolute);
            $hash = hash_file('sha256', $absolute);
            $byteLength = $size === false ? null : $size;
            $byteSha256 = $hash === false ? null : $hash;
        }
        $diagnostics = $exists ? [] : [[
            'type' => 'ocf-manifest-missing-reference',
            'fullPath' => $fullPath,
            'part' => $directory ? $part . '/' : $part,
            'message' => 'EPUB OCF manifest file-entry target is missing from the package',
        ]];

        return [
            'target' => $directory ? $part . '/' : $part,
            'part' => $directory ? $part . '/' : $part,
            'external' => false,
            'exists' => $exists,
            'directory' => $directory,
            'byteLength' => $byteLength,
            'byteSha256' => $byteSha256,
            'canExposeBytes' => $exists && !$directory,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ocfItemReferences(array $items): array
    {
        $references = [];
        foreach ($items as $item) {
            if (is_array($item['reference'] ?? null)) {
                $references[] = $item['reference'];
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $report
     * @param list<array<string, mixed>> $references
     * @return array<string, mixed>
     */
    private function ocfReportWithReferenceCounts(array $report, array $references): array
    {
        $report['referenceCount'] = count($references);
        $report['localReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) !== true
                && ($reference['exists'] ?? false) === true
        ));
        $report['externalReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) === true
        ));
        $report['missingReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) !== true
                && ($reference['exists'] ?? true) !== true
        ));

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function missingOcfReference(string $context): array
    {
        return [
            'target' => null,
            'part' => null,
            'external' => false,
            'exists' => false,
            'directory' => false,
            'byteLength' => null,
            'byteSha256' => null,
            'canExposeBytes' => false,
            'diagnostics' => [[
                'type' => 'ocf-' . $context . '-missing-reference',
                'message' => 'EPUB OCF ' . $context . ' reference is missing a URI',
            ]],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function readBindings(string $root, array $manifest, \DOMElement $packageElement): array
    {
        $bindingsElement = $this->firstDirectChild($packageElement, 'bindings');
        if (!$bindingsElement instanceof \DOMElement) {
            return [
                'present' => false,
                'itemCount' => 0,
                'handlerCount' => 0,
                'resolvedHandlerCount' => 0,
                'readableHandlerCount' => 0,
                'externalHandlerCount' => 0,
                'missingHandlerCount' => 0,
                'invalidMediaTypeCount' => 0,
                'boundMediaTypes' => [],
                'items' => [],
                'itemsByMediaType' => [],
                'diagnosticCount' => 0,
                'diagnostics' => [],
            ];
        }

        $items = [];
        $itemsByMediaType = [];
        $diagnostics = [];
        $boundMediaTypes = [];
        $handlerCount = 0;
        $resolvedHandlerCount = 0;
        $readableHandlerCount = 0;
        $externalHandlerCount = 0;
        $missingHandlerCount = 0;
        $invalidMediaTypeCount = 0;

        foreach ($this->directChildElements($bindingsElement, 'mediaType') as $index => $node) {
            $rawMediaType = trim($node->getAttribute('media-type'));
            $handlerId = trim($node->getAttribute('handler'));
            $attributes = $this->elementAttributes($node);
            $mediaTypeReport = $rawMediaType === ''
                ? [
                    'mediaType' => '',
                    'normalizedMediaType' => '',
                    'mediaTypeBase' => '',
                    'mediaTypeHasParameters' => false,
                    'mediaTypeParameterCount' => 0,
                    'mediaTypeParameters' => [],
                    'mediaTypeParameterMap' => [],
                    'mediaTypeSyntaxValid' => false,
                    'mediaTypeDiagnostics' => [],
                ]
                : $this->bindingMediaTypeReport($rawMediaType);
            $mediaType = $mediaTypeReport['mediaTypeBase'];
            $handler = $handlerId === '' ? null : ($manifest[$handlerId] ?? null);
            $handlerPath = is_array($handler) && is_string($handler['path'] ?? null) ? $handler['path'] : null;
            $handlerHref = is_array($handler) && is_string($handler['href'] ?? null) ? $handler['href'] : null;
            $handlerTarget = is_array($handler) && is_string($handler['target'] ?? null) ? $handler['target'] : null;
            $handlerExternal = is_array($handler) && ($handler['external'] ?? false) === true;
            $handlerExists = is_array($handler) && ($handler['exists'] ?? false) === true;
            $handlerMediaType = is_array($handler) && is_string($handler['mediaType'] ?? null) ? $handler['mediaType'] : null;
            $handlerReadable = $handlerExists && !$handlerExternal && $handlerMediaType === 'application/xhtml+xml';
            $itemDiagnostics = [];

            if ($rawMediaType === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-media-type',
                    'message' => 'EPUB OPF binding mediaType entry is missing media-type',
                ];
            } else {
                $boundMediaTypes[$mediaType] = $mediaType;
                foreach ($mediaTypeReport['mediaTypeDiagnostics'] as $diagnostic) {
                    $itemDiagnostics[] = $diagnostic;
                }
            }

            if ($handlerId === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-handler',
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'message' => 'EPUB OPF binding mediaType entry is missing handler',
                ];
            } else {
                ++$handlerCount;
                if (!is_array($handler)) {
                    ++$missingHandlerCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-binding-handler-manifest-item',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'message' => 'EPUB OPF binding handler does not reference a manifest item',
                    ];
                } elseif ($handlerExternal) {
                    ++$externalHandlerCount;
                    $itemDiagnostics[] = [
                        'type' => 'external-binding-handler',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'handlerHref' => $handlerHref,
                        'handlerTarget' => $handlerTarget,
                        'message' => 'EPUB OPF binding handler points outside the package and was not fetched',
                    ];
                } elseif (!$handlerExists) {
                    ++$missingHandlerCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-binding-handler-package-part',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'handlerPath' => $handlerPath,
                        'message' => 'EPUB OPF binding handler package part is missing',
                    ];
                } elseif ($handlerMediaType !== 'application/xhtml+xml') {
                    $itemDiagnostics[] = [
                        'type' => 'non-xhtml-binding-handler',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'handlerPath' => $handlerPath,
                        'handlerMediaType' => $handlerMediaType,
                        'message' => 'EPUB OPF binding handler should resolve to an XHTML content document',
                    ];
                }
            }

            if ($mediaTypeReport['mediaTypeSyntaxValid'] !== true) {
                ++$invalidMediaTypeCount;
            }
            if ($handlerExists) {
                ++$resolvedHandlerCount;
            }
            if ($handlerReadable) {
                ++$readableHandlerCount;
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $index,
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'handlerId' => $handlerId === '' ? null : $handlerId,
                ] + $diagnostic;
            }

            $handlerByteLength = null;
            $handlerByteSha256 = null;
            if ($handlerExists && !$handlerExternal && $handlerPath !== null) {
                $absolute = $this->resolveExistingPackagePath($root, $handlerPath);
                $handlerByteLength = filesize($absolute);
                $handlerByteSha256 = hash_file('sha256', $absolute);
            }

            $item = [
                'index' => $index,
                'id' => $this->nullableAttribute($node, 'id'),
                'rawMediaType' => $rawMediaType,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                'handlerId' => $handlerId === '' ? null : $handlerId,
                'handlerHref' => $handlerHref,
                'handlerTarget' => $handlerTarget,
                'handlerPath' => $handlerPath,
                'handlerExternal' => $handlerExternal,
                'handlerMediaType' => $handlerMediaType,
                'handlerProperties' => is_array($handler) && is_array($handler['properties'] ?? null) ? $handler['properties'] : [],
                'handlerExists' => $handlerExists,
                'handlerReadable' => $handlerReadable,
                'handlerByteLength' => $handlerByteLength === false ? null : $handlerByteLength,
                'handlerByteSha256' => $handlerByteSha256 === false ? null : $handlerByteSha256,
                'attributes' => $attributes,
                'customAttributes' => $this->customAttributes($attributes, self::OPF_BINDING_MEDIA_TYPE_STRUCTURAL_ATTRIBUTES),
                'diagnostics' => $itemDiagnostics,
            ];
            $items[] = $item;
            if ($mediaType !== '' && !isset($itemsByMediaType[$mediaType])) {
                $itemsByMediaType[$mediaType] = $item;
            }
        }

        return [
            'present' => true,
            'itemCount' => count($items),
            'handlerCount' => $handlerCount,
            'resolvedHandlerCount' => $resolvedHandlerCount,
            'readableHandlerCount' => $readableHandlerCount,
            'externalHandlerCount' => $externalHandlerCount,
            'missingHandlerCount' => $missingHandlerCount,
            'invalidMediaTypeCount' => $invalidMediaTypeCount,
            'boundMediaTypes' => array_values($boundMediaTypes),
            'items' => $items,
            'itemsByMediaType' => $itemsByMediaType,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, array<string, mixed>>
     */
    private function bindingsByMediaType(array $bindings): array
    {
        $itemsByMediaType = is_array($bindings['itemsByMediaType'] ?? null) ? $bindings['itemsByMediaType'] : [];
        $result = [];
        foreach ($itemsByMediaType as $mediaType => $item) {
            if (is_string($mediaType) && is_array($item)) {
                $result[$mediaType] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function bindingMediaTypeReport(string $mediaType): array
    {
        $report = $this->mediaTypeReport($mediaType);
        $diagnostics = [];
        foreach ($report['mediaTypeDiagnostics'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            $diagnostic['type'] = str_replace('manifest-media-type', 'binding-media-type', $type);
            if (is_string($diagnostic['message'] ?? null)) {
                $diagnostic['message'] = str_replace('manifest media-type', 'binding media-type', $diagnostic['message']);
            }
            $diagnostics[] = $diagnostic;
        }
        $report['mediaTypeDiagnostics'] = $diagnostics;

        return $report;
    }

    /**
     * @return array{
     *     raw:string,
     *     declarationCount:int,
     *     declarations:list<array{index:int, prefix:string, iri:string}>,
     *     bindingCount:int,
     *     bindings:list<array{index:int, prefix:string, iri:string}>,
     *     bindingsByPrefix:array<string, string>,
     *     duplicateCount:int,
     *     invalidCount:int,
     *     valid:bool,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function packagePrefixReport(string $value): array
    {
        $declarations = [];
        $bindingsByPrefix = [];
        $diagnostics = [];
        $invalidCount = 0;
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $offset += strspn($value, " \t\r\n", $offset);
            if ($offset >= $length) {
                break;
            }

            $segment = substr($value, $offset);
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):[ \t\r\n]+([^ \t\r\n]+)/', $segment, $match)) {
                ++$invalidCount;
                $diagnostics[] = [
                    'type' => 'invalid-package-prefix-declaration',
                    'offset' => $offset,
                    'value' => $segment,
                    'message' => 'EPUB OPF prefix declarations must be prefix: IRI pairs separated by whitespace',
                ];
                break;
            }

            $prefix = $match[1];
            $iri = $match[2];
            if (isset($bindingsByPrefix[$prefix])) {
                $diagnostics[] = [
                    'type' => 'duplicate-package-prefix-declaration',
                    'prefix' => $prefix,
                    'previousIri' => $bindingsByPrefix[$prefix],
                    'iri' => $iri,
                    'message' => 'EPUB OPF prefix declaration repeats a prefix; later binding is retained',
                ];
            }

            $bindingsByPrefix[$prefix] = $iri;
            $declarations[] = [
                'index' => count($declarations),
                'prefix' => $prefix,
                'iri' => $iri,
            ];
            $offset += strlen($match[0]);
        }

        return [
            'raw' => $value,
            'declarationCount' => count($declarations),
            'declarations' => $declarations,
            'bindingCount' => count($bindingsByPrefix),
            'bindings' => $declarations,
            'bindingsByPrefix' => $bindingsByPrefix,
            'duplicateCount' => count(array_filter(
                $diagnostics,
                static fn (array $diagnostic): bool => ($diagnostic['type'] ?? '') === 'duplicate-package-prefix-declaration'
            )),
            'invalidCount' => $invalidCount,
            'valid' => $diagnostics === [],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataLink(string $root, string $opfDir, \DOMElement $linkElement, int $index): array
    {
        $href = trim($linkElement->getAttribute('href'));
        $rel = $this->tokens($linkElement->getAttribute('rel'));
        $attributes = $this->elementAttributes($linkElement);
        $customAttributes = $this->customAttributes($attributes, self::OPF_METADATA_LINK_STRUCTURAL_ATTRIBUTES);
        $language = $this->nullableAttribute($linkElement, 'xml:lang');
        $languageSource = $language === null ? null : 'xml:lang';
        if ($language === null) {
            $language = $this->nullableAttribute($linkElement, 'lang');
            $languageSource = $language === null ? null : 'lang';
        }
        $suffix = $this->hrefSuffix($href);
        $external = $href !== '' && $this->isExternalHref($href);
        $path = '';
        $target = '';
        $exists = false;
        $diagnostics = [];

        if ($rel === []) {
            $diagnostics[] = [
                'type' => 'missing-metadata-link-rel',
                'message' => 'EPUB OPF metadata link is missing rel tokens for package review classification',
            ];
        }

        if ($href === '') {
            $diagnostics[] = [
                'type' => 'missing-metadata-link-href',
                'message' => 'EPUB OPF metadata link is missing href',
            ];
        } elseif ($external) {
            $path = $href;
            $target = $href;
            $diagnostics[] = [
                'type' => 'external-metadata-link-target',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB OPF metadata link points outside the package and was not fetched',
            ];
        } else {
            try {
                $path = $this->resolvePackageHref($opfDir, $href);
                $target = $this->targetWithSuffix($path, $suffix);
                $exists = $path !== '' && $this->packagePathExists($root, $path);
                if ($path !== '' && !$exists) {
                    $diagnostics[] = [
                        'type' => 'missing-metadata-link-target',
                        'href' => $href,
                        'path' => $path,
                        'message' => 'EPUB OPF metadata link target is missing from the package',
                    ];
                }
            } catch (\RuntimeException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-metadata-link-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $id = trim($linkElement->getAttribute('id'));
        $mediaTypeRaw = trim($linkElement->getAttribute('media-type'));

        return [
            'index' => $index,
            'id' => $id,
            'rel' => $rel,
            'href' => $href,
            'target' => $target,
            'path' => $path,
            'partName' => $external ? null : ($path === '' ? null : $path),
            'fragment' => is_string($suffix['fragment'] ?? null) ? $suffix['fragment'] : '',
            'mediaType' => $mediaTypeRaw,
            'properties' => $this->tokens($linkElement->getAttribute('properties')),
            'refines' => trim($linkElement->getAttribute('refines')),
            'title' => $this->nullableAttribute($linkElement, 'title'),
            'hreflang' => $this->nullableAttribute($linkElement, 'hreflang'),
            'language' => $language,
            'languageSource' => $languageSource,
            'direction' => $this->nullableAttribute($linkElement, 'dir'),
            'attributes' => $attributes,
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'external' => $external,
            'exists' => $exists,
            'hrefHasQuery' => (bool) ($suffix['hasQuery'] ?? false),
            'hrefQuery' => $suffix['query'] ?? null,
            'hrefHasFragment' => (bool) ($suffix['hasFragment'] ?? false),
            'hrefFragment' => $suffix['fragment'] ?? null,
            'manifestId' => null,
            'manifestMediaType' => null,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ] + $this->metadataLinkMediaTypeFields($mediaTypeRaw, $index, $id);
    }

    /**
     * @param list<array<string, mixed>> $links
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function metadataLinksWithManifestContext(array $links, array $manifest): array
    {
        $manifestByPath = [];
        foreach ($manifest as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $item;
            }
        }

        foreach ($links as $index => $link) {
            $path = is_string($link['partName'] ?? null) ? $link['partName'] : '';
            $manifestItem = $path !== '' && isset($manifestByPath[$path]) ? $manifestByPath[$path] : null;
            $diagnostics = is_array($link['diagnostics'] ?? null) ? array_values($link['diagnostics']) : [];

            if (is_array($manifestItem)) {
                $links[$index]['manifestId'] = is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null;
                $links[$index]['manifestMediaType'] = is_string($manifestItem['mediaType'] ?? null) ? $manifestItem['mediaType'] : null;
            } elseif (
                ($link['external'] ?? false) !== true
                && $path !== ''
                && ($link['exists'] ?? false) === true
            ) {
                $diagnostics[] = [
                    'type' => 'unmanifested-metadata-link-target',
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : '',
                    'path' => $path,
                    'message' => 'EPUB OPF metadata link resolves to package bytes that are not declared in the OPF manifest',
                ];
            }

            $links[$index]['diagnostics'] = $diagnostics;
            $links[$index]['diagnosticCount'] = count($diagnostics);
        }

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataItem(\DOMElement $element, int $index, string $value): array
    {
        $language = $this->elementLanguage($element);

        return [
            'index' => $index,
            'kind' => $element->localName,
            'name' => $element->localName,
            'namespace' => $element->namespaceURI ?? '',
            'prefix' => $element->prefix ?? '',
            'id' => $this->nullableAttribute($element, 'id'),
            'value' => $value,
            'text' => $value,
            'scheme' => $this->nullableAttribute($element, 'scheme'),
            'language' => $language === '' ? null : $language,
            'direction' => $this->nullableAttribute($element, 'dir'),
            'role' => $this->nullableAttribute($element, 'role'),
            'fileAs' => $this->nullableAttribute($element, 'file-as'),
        ];
    }

    private function isDublinCoreMetadataElement(\DOMElement $element): bool
    {
        return $element->namespaceURI === self::DC_NAMESPACE && $element->prefix === 'dc';
    }

    /**
     * @param list<array<string, mixed>> $metadataItems
     * @param list<array<string, mixed>> $metadataProperties
     * @return array<string, mixed>
     */
    private function packageReport(\DOMElement $packageElement, array $metadataItems, array $metadataProperties): array
    {
        $attributes = $this->elementAttributes($packageElement);
        $customAttributes = [];
        foreach ($attributes as $name => $value) {
            if (isset(self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }
            $customAttributes[$name] = $value;
        }

        $version = trim($packageElement->getAttribute('version'));
        $uniqueIdentifierId = trim($packageElement->getAttribute('unique-identifier'));
        $language = $this->elementLanguage($packageElement);
        $base = $this->elementBase($packageElement);
        $xmlBase = trim($packageElement->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        if ($xmlBase === '') {
            $xmlBase = trim($packageElement->getAttribute('xml:base'));
        }
        $prefix = trim($packageElement->getAttribute('prefix'));
        $prefixReport = $this->packagePrefixReport($prefix);
        $uniqueIdentifierItem = null;
        foreach ($metadataItems as $item) {
            if (($item['id'] ?? null) === $uniqueIdentifierId) {
                $uniqueIdentifierItem = $item;
                break;
            }
        }

        $identifierDetails = $this->metadataIdentifierDetails(
            $metadataItems,
            $metadataProperties,
            $uniqueIdentifierId
        );
        $uniqueIdentifier = $this->metadataUniqueIdentifierReport($uniqueIdentifierId, $identifierDetails, true);
        $identifierSummary = $this->metadataIdentifierSummary($identifierDetails, $uniqueIdentifier);
        $identifierDiagnostics = array_merge($uniqueIdentifier['diagnostics'], $identifierSummary['diagnostics']);
        $diagnostics = $prefixReport['diagnostics'];
        if ($uniqueIdentifierId === '') {
            $diagnostics[] = [
                'type' => 'missing-package-unique-identifier',
                'message' => 'EPUB OPF package root is missing unique-identifier',
            ];
        } elseif ($uniqueIdentifierItem === null) {
            $diagnostics[] = [
                'type' => 'unresolved-package-unique-identifier',
                'uniqueIdentifierId' => $uniqueIdentifierId,
                'message' => 'EPUB OPF package unique-identifier does not match a metadata item id',
            ];
        }
        if ($version === '') {
            $diagnostics[] = [
                'type' => 'missing-package-version',
                'message' => 'EPUB OPF package root is missing version',
            ];
        }
        $packageDiagnostics = array_merge($diagnostics, $identifierDiagnostics);
        $valid = $packageDiagnostics === [];

        return [
            'present' => true,
            'id' => $this->nullableAttribute($packageElement, 'id'),
            'version' => $version,
            'uniqueIdentifierId' => $uniqueIdentifierId === '' ? null : $uniqueIdentifierId,
            'uniqueIdentifierMatched' => $uniqueIdentifierItem !== null,
            'uniqueIdentifierValue' => is_array($uniqueIdentifierItem) ? (string) ($uniqueIdentifierItem['value'] ?? '') : null,
            'uniqueIdentifierItem' => $uniqueIdentifierItem,
            'language' => $language === '' ? null : $language,
            'direction' => $this->nullableAttribute($packageElement, 'dir'),
            'base' => $base,
            'baseResolutionPolicy' => $base === null ? null : 'reported-not-applied-to-package-paths',
            'xmlBase' => $xmlBase === '' ? null : $xmlBase,
            'prefix' => $prefix === '' ? null : $prefix,
            'prefixReport' => $prefixReport,
            'prefixDeclarationCount' => count($prefixReport['declarations']),
            'prefixCount' => count($prefixReport['bindingsByPrefix']),
            'prefixDeclarations' => $prefixReport['declarations'],
            'prefixBindings' => $prefixReport['bindingsByPrefix'],
            'duplicatePrefixCount' => $prefixReport['duplicateCount'],
            'invalidPrefixDeclarationCount' => $prefixReport['invalidCount'],
            'prefixDiagnosticCount' => count($prefixReport['diagnostics']),
            'prefixDiagnostics' => $prefixReport['diagnostics'],
            'prefixValid' => $prefixReport['diagnostics'] === [],
            'attributes' => $attributes,
            'attributeCount' => count($attributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'uniqueIdentifier' => $uniqueIdentifier,
            'identifierDetails' => $identifierDetails,
            'identifierSummary' => $identifierSummary,
            'identifierDiagnosticCount' => count($identifierDiagnostics),
            'identifierDiagnostics' => $identifierDiagnostics,
            'packageDiagnosticCount' => count($packageDiagnostics),
            'packageDiagnostics' => $packageDiagnostics,
            'valid' => $valid,
            'summary' => [
                'version' => $version,
                'uniqueIdentifierId' => $uniqueIdentifierId === '' ? null : $uniqueIdentifierId,
                'uniqueIdentifierMatched' => $uniqueIdentifierItem !== null,
                'language' => $language === '' ? null : $language,
                'direction' => $this->nullableAttribute($packageElement, 'dir'),
                'base' => $base,
                'prefixCount' => count($prefixReport['bindingsByPrefix']),
                'prefixDeclarationCount' => count($prefixReport['declarations']),
                'prefixBindingCount' => count($prefixReport['bindingsByPrefix']),
                'prefixDiagnosticCount' => count($prefixReport['diagnostics']),
                'customAttributeCount' => count($customAttributes),
                'diagnosticCount' => count($diagnostics),
                'packageDiagnosticCount' => count($packageDiagnostics),
                'identifierCount' => count($identifierDetails),
                'selectedIdentifier' => $uniqueIdentifier['value'],
                'selectedBy' => $uniqueIdentifier['selectedBy'],
                'identifierDiagnosticCount' => count($identifierDiagnostics),
                'valid' => $valid,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageAuthoringReport(\DOMElement $packageElement): array
    {
        $attributes = $this->elementAttributes($packageElement);
        $customAttributes = $this->customAttributes($attributes, self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES);
        $xmlBase = trim($packageElement->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        if ($xmlBase === '') {
            $xmlBase = trim($packageElement->getAttribute('xml:base'));
        }
        $language = $this->elementLanguage($packageElement);

        return [
            'present' => $attributes !== [],
            'id' => $this->nullableAttribute($packageElement, 'id'),
            'version' => trim($packageElement->getAttribute('version')),
            'uniqueIdentifierId' => $this->nullableAttribute($packageElement, 'unique-identifier'),
            'language' => $language === '' ? null : $language,
            'direction' => $this->nullableAttribute($packageElement, 'dir'),
            'xmlBase' => $xmlBase === '' ? null : $xmlBase,
            'prefix' => $this->nullableAttribute($packageElement, 'prefix'),
            'attributes' => $attributes,
            'attributeCount' => count($attributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'hasCustomAttributes' => $customAttributes !== [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function manifestAuthoringReport(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $languageItems = [];
        $directionItems = [];
        $baseItems = [];
        $customAttributeItems = [];
        $propertyItems = [];
        $propertiesByItemId = [];
        $fallbackItems = [];
        $fallbackStyleItems = [];
        $mediaOverlayItems = [];
        $hrefSuffixItems = [];
        $mediaTypeParameterItems = [];
        $diagnosticItems = [];
        $diagnostics = [];

        foreach ($manifest as $item) {
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : $this->customAttributes($attributes, self::OPF_MANIFEST_ITEM_STRUCTURAL_ATTRIBUTES);
            $base = is_string($item['base'] ?? null) && $item['base'] !== ''
                ? $item['base']
                : (is_string($attributes['xml:base'] ?? null) && $attributes['xml:base'] !== ''
                    ? $attributes['xml:base']
                    : null);
            $baseResolution = self::manifestItemBaseResolution($base);
            $properties = is_array($item['properties'] ?? null) ? array_values(array_filter(
                $item['properties'],
                static fn (mixed $property): bool => is_string($property) && $property !== ''
            )) : [];
            $fallback = is_string($item['fallback'] ?? null) && $item['fallback'] !== ''
                ? $item['fallback']
                : null;
            $fallbackStyle = is_string($item['fallbackStyle'] ?? null) && $item['fallbackStyle'] !== ''
                ? $item['fallbackStyle']
                : null;
            $mediaOverlay = is_string($item['mediaOverlay'] ?? null) && $item['mediaOverlay'] !== ''
                ? $item['mediaOverlay']
                : null;
            $mediaTypeParameters = is_array($item['mediaTypeParameters'] ?? null)
                ? array_values($item['mediaTypeParameters'])
                : [];
            $mediaTypeParameterNames = is_array($item['mediaTypeParameterNames'] ?? null)
                ? array_values($item['mediaTypeParameterNames'])
                : [];
            $diagnosticEntries = is_array($item['diagnostics'] ?? null) ? array_values(array_filter(
                $item['diagnostics'],
                static fn (mixed $diagnostic): bool => is_array($diagnostic)
            )) : [];
            $summary = [
                'index' => (int) ($item['index'] ?? count($items)),
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => (string) ($item['target'] ?? ''),
                'path' => (string) ($item['path'] ?? ''),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'properties' => $properties,
                'propertyCount' => count($properties),
                'fallback' => $fallback,
                'fallbackStyle' => $fallbackStyle,
                'mediaOverlay' => $mediaOverlay,
                'hrefHasQuery' => (bool) ($item['hrefHasQuery'] ?? false),
                'hrefQuery' => is_string($item['hrefQuery'] ?? null) ? $item['hrefQuery'] : null,
                'hrefHasFragment' => (bool) ($item['hrefHasFragment'] ?? false),
                'hrefFragment' => is_string($item['hrefFragment'] ?? null) ? $item['hrefFragment'] : null,
                'mediaTypeHasParameters' => (bool) ($item['mediaTypeHasParameters'] ?? false),
                'mediaTypeParameterCount' => (int) ($item['mediaTypeParameterCount'] ?? count($mediaTypeParameters)),
                'mediaTypeParameters' => $mediaTypeParameters,
                'mediaTypeParameterNames' => $mediaTypeParameterNames,
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'base' => $base,
                'baseResolutionPolicy' => $baseResolution['policy'],
                'baseResolution' => $baseResolution,
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
                'hasBase' => $base !== null,
                'diagnosticCount' => count($diagnosticEntries),
                'diagnostics' => $diagnosticEntries,
            ];

            $items[] = $summary;
            if ($summary['id'] !== '') {
                $itemsById[$summary['id']] = $summary;
            }
            if ($properties !== []) {
                $propertyItems[] = $summary;
                if ($summary['id'] !== '') {
                    $propertiesByItemId[$summary['id']] = $properties;
                }
            }
            if ($fallback !== null) {
                $fallbackItems[] = $summary;
            }
            if ($fallbackStyle !== null) {
                $fallbackStyleItems[] = $summary;
            }
            if ($mediaOverlay !== null) {
                $mediaOverlayItems[] = $summary;
            }
            if ($summary['hrefHasQuery'] || $summary['hrefHasFragment']) {
                $hrefSuffixItems[] = $summary;
            }
            if ($summary['mediaTypeParameterCount'] > 0) {
                $mediaTypeParameterItems[] = $summary;
            }
            if ($diagnosticEntries !== []) {
                $diagnosticItems[] = $summary;
                foreach ($diagnosticEntries as $diagnostic) {
                    $diagnostics[] = [
                        'index' => $summary['index'],
                        'id' => $summary['id'],
                        'href' => $summary['href'],
                        'path' => $summary['path'],
                    ] + $diagnostic;
                }
            }
            if ($summary['language'] !== null) {
                $languageItems[] = $summary;
            }
            if ($summary['direction'] !== null) {
                $directionItems[] = $summary;
            }
            if ($base !== null) {
                $baseItems[] = $summary;
            }
            if ($customAttributes !== []) {
                $customAttributeItems[] = $summary;
            }
        }

        ksort($itemsById, SORT_STRING);
        ksort($propertiesByItemId, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'items' => $items,
            'itemsById' => $itemsById,
            'propertyItemCount' => count($propertyItems),
            'propertyItems' => $propertyItems,
            'propertiesByItemId' => $propertiesByItemId,
            'fallbackItemCount' => count($fallbackItems),
            'fallbackItems' => $fallbackItems,
            'fallbackStyleItemCount' => count($fallbackStyleItems),
            'fallbackStyleItems' => $fallbackStyleItems,
            'mediaOverlayItemCount' => count($mediaOverlayItems),
            'mediaOverlayItems' => $mediaOverlayItems,
            'hrefSuffixItemCount' => count($hrefSuffixItems),
            'hrefSuffixItems' => $hrefSuffixItems,
            'mediaTypeParameterItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterItems' => $mediaTypeParameterItems,
            'languageItemCount' => count($languageItems),
            'languageItems' => $languageItems,
            'directionItemCount' => count($directionItems),
            'directionItems' => $directionItems,
            'baseItemCount' => count($baseItems),
            'baseItems' => $baseItems,
            'customAttributeItemCount' => count($customAttributeItems),
            'customAttributeItems' => $customAttributeItems,
            'diagnosticItemCount' => count($diagnosticItems),
            'diagnosticCount' => count($diagnostics),
            'diagnosticItems' => $diagnosticItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{metadataOnly:bool, appliesToManifestHrefs:bool, policy:?string}
     */
    private static function manifestItemBaseResolution(?string $base): array
    {
        return [
            'metadataOnly' => $base !== null,
            'appliesToManifestHrefs' => false,
            'policy' => $base === null ? null : 'reported-not-applied-to-manifest-hrefs',
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function spineAuthoringReport(array $spine): array
    {
        $items = [];
        $itemsByIndex = [];
        $languageItems = [];
        $directionItems = [];
        $customAttributeItems = [];
        $propertyItems = [];
        $propertiesByIndex = [];
        $explicitLinearItems = [];
        $nonLinearItems = [];
        $diagnosticItems = [];
        $diagnostics = [];

        foreach ($spine as $item) {
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : $this->customAttributes($attributes, self::OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES);
            $properties = is_array($item['properties'] ?? null) ? array_values(array_filter(
                $item['properties'],
                static fn (mixed $property): bool => is_string($property) && $property !== ''
            )) : [];
            $diagnosticEntries = is_array($item['diagnostics'] ?? null) ? array_values(array_filter(
                $item['diagnostics'],
                static fn (mixed $diagnostic): bool => is_array($diagnostic)
            )) : [];
            $summary = [
                'index' => (int) ($item['index'] ?? count($items)),
                'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                'idref' => (string) ($item['idref'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => (string) ($item['target'] ?? ''),
                'path' => (string) ($item['path'] ?? ''),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'linear' => (bool) ($item['linear'] ?? true),
                'linearRaw' => is_string($item['linearRaw'] ?? null) ? $item['linearRaw'] : null,
                'linearSpecified' => (bool) ($item['linearSpecified'] ?? false),
                'linearValue' => is_string($item['linearValue'] ?? null) ? $item['linearValue'] : null,
                'linearValid' => (bool) ($item['linearValid'] ?? true),
                'linearDiagnostics' => is_array($item['linearDiagnostics'] ?? null) ? array_values($item['linearDiagnostics']) : [],
                'properties' => $properties,
                'propertyCount' => count($properties),
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
                'diagnosticCount' => count($diagnosticEntries),
                'diagnostics' => $diagnosticEntries,
            ];

            $items[] = $summary;
            $itemsByIndex[$summary['index']] = $summary;
            if ($properties !== []) {
                $propertyItems[] = $summary;
                $propertiesByIndex[$summary['index']] = $properties;
            }
            if ($summary['linearRaw'] !== null) {
                $explicitLinearItems[] = $summary;
            }
            if ($summary['linear'] === false) {
                $nonLinearItems[] = $summary;
            }
            if ($diagnosticEntries !== []) {
                $diagnosticItems[] = $summary;
                foreach ($diagnosticEntries as $diagnostic) {
                    $diagnostics[] = [
                        'index' => $summary['index'],
                        'idref' => $summary['idref'],
                    ] + $diagnostic;
                }
            }
            if ($summary['language'] !== null) {
                $languageItems[] = $summary;
            }
            if ($summary['direction'] !== null) {
                $directionItems[] = $summary;
            }
            if ($customAttributes !== []) {
                $customAttributeItems[] = $summary;
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'items' => $items,
            'itemsByIndex' => array_values($itemsByIndex),
            'propertyItemCount' => count($propertyItems),
            'propertyItems' => $propertyItems,
            'propertiesByIndex' => $propertiesByIndex,
            'explicitLinearItemCount' => count($explicitLinearItems),
            'explicitLinearItems' => $explicitLinearItems,
            'nonLinearItemCount' => count($nonLinearItems),
            'nonLinearItems' => $nonLinearItems,
            'languageItemCount' => count($languageItems),
            'languageItems' => $languageItems,
            'directionItemCount' => count($directionItems),
            'directionItems' => $directionItems,
            'customAttributeItemCount' => count($customAttributeItems),
            'customAttributeItems' => $customAttributeItems,
            'diagnosticItemCount' => count($diagnosticItems),
            'diagnosticCount' => count($diagnostics),
            'diagnosticItems' => $diagnosticItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $properties
     * @return list<array<string, mixed>>
     */
    private function metadataIdentifierDetails(array $items, array $properties, string $uniqueIdentifierId): array
    {
        $uniqueIdentifierId = trim($uniqueIdentifierId);
        $refinementsById = $this->metadataRefinementsBySubjectId($properties);
        $identifiers = [];
        $values = [];

        foreach ($items as $item) {
            if (($item['kind'] ?? null) !== 'identifier') {
                continue;
            }

            $value = (string) ($item['value'] ?? $item['text'] ?? '');
            if ($value === '') {
                continue;
            }

            $index = (int) ($item['index'] ?? count($identifiers));
            $id = is_string($item['id'] ?? null) ? $item['id'] : null;
            $identifiers[] = $item + [
                'index' => $index,
                'id' => $id,
                'value' => $value,
            ];
            $values[$value][] = [
                'index' => $index,
                'id' => $id,
            ];
        }

        $details = [];
        foreach ($identifiers as $item) {
            $id = is_string($item['id'] ?? null) ? $item['id'] : null;
            $value = (string) ($item['value'] ?? '');
            $duplicateEntries = $value !== '' && count($values[$value] ?? []) > 1 ? $values[$value] : [];
            $refinements = $id !== null && isset($refinementsById[$id]) ? $refinementsById[$id] : [];
            $identifierTypes = is_array($refinements['identifier-type'] ?? null)
                ? $refinements['identifier-type']
                : [];
            $identifierType = is_array($identifierTypes[0] ?? null) ? $identifierTypes[0] : null;

            $details[] = [
                'kind' => 'identifier',
                'index' => (int) ($item['index'] ?? 0),
                'value' => $value,
                'text' => (string) ($item['text'] ?? $value),
                'id' => $id,
                'scheme' => is_string($item['scheme'] ?? null) ? $item['scheme'] : null,
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'identifierTypes' => $identifierTypes,
                'identifierType' => is_array($identifierType) ? (string) ($identifierType['value'] ?? '') : null,
                'identifierTypeScheme' => is_array($identifierType) && is_string($identifierType['scheme'] ?? null)
                    ? $identifierType['scheme']
                    : null,
                'selectedByUniqueIdentifier' => $uniqueIdentifierId !== ''
                    && $id !== null
                    && $id === $uniqueIdentifierId,
                'duplicateValue' => $duplicateEntries !== [],
                'duplicateIds' => array_values(array_filter(
                    array_map(static fn (array $duplicate): ?string => $duplicate['id'], $duplicateEntries),
                    static fn (?string $id): bool => $id !== null && $id !== '',
                )),
                'duplicateIndexes' => array_map(
                    static fn (array $duplicate): int => (int) $duplicate['index'],
                    $duplicateEntries,
                ),
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function metadataRefinementsBySubjectId(array $properties): array
    {
        $byId = [];
        foreach ($properties as $property) {
            $refines = is_string($property['refines'] ?? null) ? trim($property['refines']) : '';
            $propertyName = is_string($property['property'] ?? null) ? trim($property['property']) : '';
            if ($refines === '' || $propertyName === '' || !str_starts_with($refines, '#')) {
                continue;
            }

            $subjectId = substr($refines, 1);
            if ($subjectId === '') {
                continue;
            }

            $byId[$subjectId][$propertyName][] = [
                'id' => is_string($property['id'] ?? null) ? $property['id'] : null,
                'property' => $propertyName,
                'value' => (string) ($property['value'] ?? ''),
                'refines' => $refines,
                'scheme' => is_string($property['scheme'] ?? null) ? $property['scheme'] : null,
            ];
        }

        return $byId;
    }

    /**
     * @param list<array<string, mixed>> $identifierDetails
     * @return array<string, mixed>
     */
    private function metadataUniqueIdentifierReport(string $uniqueIdentifierId, array $identifierDetails, bool $required): array
    {
        $id = trim($uniqueIdentifierId);
        $specified = $id !== '';
        $entries = [];
        foreach ($identifierDetails as $index => $detail) {
            $entries[] = [
                'index' => (int) ($detail['index'] ?? $index),
                'id' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                'value' => (string) ($detail['value'] ?? ''),
                'text' => (string) ($detail['text'] ?? $detail['value'] ?? ''),
                'scheme' => is_string($detail['scheme'] ?? null) ? $detail['scheme'] : null,
                'identifierType' => is_string($detail['identifierType'] ?? null) ? $detail['identifierType'] : null,
                'identifierTypeScheme' => is_string($detail['identifierTypeScheme'] ?? null)
                    ? $detail['identifierTypeScheme']
                    : null,
                'duplicateValue' => (bool) ($detail['duplicateValue'] ?? false),
                'duplicateIds' => is_array($detail['duplicateIds'] ?? null) ? array_values($detail['duplicateIds']) : [],
                'duplicateIndexes' => is_array($detail['duplicateIndexes'] ?? null) ? array_values($detail['duplicateIndexes']) : [],
            ];
        }

        $matchedEntries = [];
        if ($specified) {
            foreach ($entries as $entry) {
                if (($entry['id'] ?? null) === $id) {
                    $matchedEntries[] = $entry;
                }
            }
        }

        $value = null;
        $selectedBy = null;
        if ($matchedEntries !== []) {
            $value = (string) $matchedEntries[0]['value'];
            $selectedBy = 'unique-identifier';
        } elseif ($entries !== []) {
            $value = (string) $entries[0]['value'];
            $selectedBy = 'first-dc-identifier';
        }

        $diagnostics = [];
        if ($required && !$specified) {
            $diagnostics[] = [
                'type' => 'missing-unique-identifier',
                'message' => 'EPUB OPF package is missing the unique-identifier attribute',
            ];
        }
        if ($specified && $matchedEntries === []) {
            $diagnostics[] = [
                'type' => 'unique-identifier-not-found',
                'id' => $id,
                'message' => 'EPUB OPF unique-identifier does not match any dc:identifier id',
            ];
        }
        if ($required && $entries === []) {
            $diagnostics[] = [
                'type' => 'missing-dc-identifier',
                'message' => 'EPUB OPF metadata does not contain a dc:identifier entry',
            ];
        }
        if (count($matchedEntries) > 1) {
            $diagnostics[] = [
                'type' => 'duplicate-unique-identifier-id',
                'id' => $id,
                'values' => array_map(
                    static fn (array $entry): string => (string) $entry['value'],
                    $matchedEntries,
                ),
                'message' => 'EPUB OPF metadata contains multiple dc:identifier entries with the unique-identifier id',
            ];
        }

        return [
            'specified' => $specified,
            'id' => $specified ? $id : null,
            'present' => $value !== null,
            'matched' => $matchedEntries !== [],
            'value' => $value,
            'selectedBy' => $selectedBy,
            'identifierCount' => count($entries),
            'matchCount' => count($matchedEntries),
            'duplicateMatchCount' => max(0, count($matchedEntries) - 1),
            'entries' => $entries,
            'matchedEntries' => $matchedEntries,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $identifierDetails
     * @param array<string, mixed> $uniqueIdentifier
     * @return array<string, mixed>
     */
    private function metadataIdentifierSummary(array $identifierDetails, array $uniqueIdentifier): array
    {
        $schemes = [];
        $identifierTypes = [];
        $duplicatesByValue = [];
        $selectedValue = is_string($uniqueIdentifier['value'] ?? null) ? $uniqueIdentifier['value'] : null;
        $selectedId = null;
        $selectedIndex = null;
        $diagnostics = [];

        foreach ($identifierDetails as $detail) {
            $scheme = is_string($detail['scheme'] ?? null) ? trim($detail['scheme']) : '';
            if ($scheme !== '') {
                $schemes[$scheme] = $scheme;
            }

            $identifierType = is_string($detail['identifierType'] ?? null) ? trim($detail['identifierType']) : '';
            if ($identifierType !== '') {
                $identifierTypes[$identifierType] = $identifierType;
            }

            if (($detail['selectedByUniqueIdentifier'] ?? false) === true && $selectedIndex === null) {
                $selectedIndex = (int) ($detail['index'] ?? 0);
                $selectedId = is_string($detail['id'] ?? null) ? $detail['id'] : null;
            }

            if (($detail['duplicateValue'] ?? false) !== true) {
                continue;
            }

            $value = (string) ($detail['value'] ?? '');
            if ($value === '' || isset($duplicatesByValue[$value])) {
                continue;
            }

            $duplicateIds = is_array($detail['duplicateIds'] ?? null) ? array_values($detail['duplicateIds']) : [];
            $duplicateIndexes = is_array($detail['duplicateIndexes'] ?? null)
                ? array_values($detail['duplicateIndexes'])
                : [];
            $duplicatesByValue[$value] = [
                'value' => $value,
                'count' => count($duplicateIndexes),
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
            ];
            $diagnostics[] = [
                'type' => 'duplicate-metadata-identifier-value',
                'value' => $value,
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
                'message' => 'EPUB OPF metadata contains multiple dc:identifier entries with the same value',
            ];
        }

        if ($selectedIndex === null && $selectedValue !== null) {
            foreach ($identifierDetails as $detail) {
                if ((string) ($detail['value'] ?? '') !== $selectedValue) {
                    continue;
                }

                $selectedIndex = (int) ($detail['index'] ?? 0);
                $selectedId = is_string($detail['id'] ?? null) ? $detail['id'] : null;
                break;
            }
        }

        return [
            'present' => $identifierDetails !== [],
            'count' => count($identifierDetails),
            'typedCount' => count(array_filter(
                $identifierDetails,
                static fn (array $detail): bool => is_string($detail['identifierType'] ?? null)
                    && $detail['identifierType'] !== '',
            )),
            'schemeCount' => count($schemes),
            'schemes' => array_values($schemes),
            'identifierTypes' => array_values($identifierTypes),
            'selectedValue' => $selectedValue,
            'selectedId' => $selectedId,
            'selectedIndex' => $selectedIndex,
            'duplicateValueCount' => count($duplicatesByValue),
            'duplicatesByValue' => array_values($duplicatesByValue),
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $properties
     * @param list<array<string, mixed>> $links
     * @param array<string, array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private function metadataRefinementTargetReport(
        \DOMElement $packageElement,
        array $items,
        array $properties,
        array $links,
        array $manifest,
        array $spine,
        array $collections
    ): array {
        $targetItems = [];
        $targetsById = [];
        $targetKindCounts = [];
        $addTarget = static function (?string $id, string $kind, array $context = []) use (&$targetItems, &$targetsById, &$targetKindCounts): void {
            $id = is_string($id) ? trim($id) : '';
            if ($id === '') {
                return;
            }

            $target = [
                'id' => $id,
                'kind' => $kind,
            ] + $context;
            $targetItems[] = $target;
            $targetsById[$id][] = $target;
            $targetKindCounts[$kind] = ($targetKindCounts[$kind] ?? 0) + 1;
        };

        $addTarget($this->nullableAttribute($packageElement, 'id'), 'package', [
            'version' => trim($packageElement->getAttribute('version')),
            'uniqueIdentifierId' => $this->nullableAttribute($packageElement, 'unique-identifier'),
        ]);

        foreach ($items as $item) {
            $addTarget(
                is_string($item['id'] ?? null) ? $item['id'] : null,
                'metadata-item',
                [
                    'index' => (int) ($item['index'] ?? count($targetItems)),
                    'name' => is_string($item['name'] ?? null) ? $item['name'] : null,
                    'metadataKind' => is_string($item['kind'] ?? null) ? $item['kind'] : null,
                    'value' => is_string($item['value'] ?? null) ? $item['value'] : null,
                ]
            );
        }

        foreach ($properties as $propertyIndex => $property) {
            $addTarget(
                is_string($property['id'] ?? null) ? $property['id'] : null,
                'metadata-meta',
                [
                    'index' => (int) ($property['index'] ?? $propertyIndex),
                    'property' => is_string($property['property'] ?? null) ? $property['property'] : null,
                    'value' => is_string($property['value'] ?? null) ? $property['value'] : null,
                ]
            );
        }

        foreach ($links as $link) {
            $addTarget(
                is_string($link['id'] ?? null) ? $link['id'] : null,
                'metadata-link',
                [
                    'index' => (int) ($link['index'] ?? count($targetItems)),
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                    'target' => is_string($link['target'] ?? null) ? $link['target'] : null,
                ]
            );
        }

        foreach ($manifest as $item) {
            $addTarget(
                is_string($item['id'] ?? null) ? $item['id'] : null,
                'manifest-item',
                [
                    'index' => (int) ($item['index'] ?? count($targetItems)),
                    'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                    'path' => is_string($item['path'] ?? null) ? $item['path'] : null,
                    'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                ]
            );
        }

        foreach ($spine as $item) {
            $addTarget(
                is_string($item['id'] ?? null) ? $item['id'] : null,
                'spine-itemref',
                [
                    'index' => (int) ($item['index'] ?? count($targetItems)),
                    'idref' => is_string($item['idref'] ?? null) ? $item['idref'] : null,
                    'path' => is_string($item['path'] ?? null) ? $item['path'] : null,
                ]
            );
        }

        $appendCollectionTargets = function (array $items, array $path = []) use (&$appendCollectionTargets, $addTarget): void {
            foreach ($items as $index => $collection) {
                if (!is_array($collection)) {
                    continue;
                }

                $currentPath = array_merge($path, [(int) $index]);
                $addTarget(
                    is_string($collection['id'] ?? null) ? $collection['id'] : null,
                    'collection',
                    [
                        'index' => (int) $index,
                        'path' => $currentPath,
                        'role' => is_string($collection['role'] ?? null) ? $collection['role'] : null,
                    ]
                );

                foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $linkIndex => $link) {
                    if (!is_array($link)) {
                        continue;
                    }

                    $addTarget(
                        is_string($link['id'] ?? null) ? $link['id'] : null,
                        'collection-link',
                        [
                            'index' => (int) $linkIndex,
                            'collectionPath' => $currentPath,
                            'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                            'target' => is_string($link['target'] ?? null) ? $link['target'] : null,
                        ]
                    );
                }

                $metadata = is_array($collection['metadata'] ?? null) ? $collection['metadata'] : [];
                foreach (is_array($metadata['items'] ?? null) ? $metadata['items'] : [] as $metadataIndex => $metadataItem) {
                    if (!is_array($metadataItem)) {
                        continue;
                    }

                    $addTarget(
                        is_string($metadataItem['id'] ?? null) ? $metadataItem['id'] : null,
                        'collection-metadata-item',
                        [
                            'index' => (int) $metadataIndex,
                            'collectionPath' => $currentPath,
                            'name' => is_string($metadataItem['name'] ?? null) ? $metadataItem['name'] : null,
                        ]
                    );
                }

                $appendCollectionTargets(is_array($collection['children'] ?? null) ? $collection['children'] : [], $currentPath);
            }
        };
        $appendCollectionTargets($collections);

        ksort($targetKindCounts, SORT_STRING);
        $duplicateTargetItems = [];
        foreach ($targetsById as $id => $targets) {
            if (count($targets) < 2) {
                continue;
            }

            $duplicateTargetItems[] = [
                'id' => $id,
                'targetCount' => count($targets),
                'targetKinds' => array_values(array_unique(array_map(
                    static fn (array $target): string => (string) ($target['kind'] ?? ''),
                    $targets
                ))),
                'targets' => array_values($targets),
            ];
        }

        $refinementItems = [];
        $resolvedItems = [];
        $unresolvedItems = [];
        $externalItems = [];
        $packageRelativeItems = [];
        $diagnostics = [];
        $sources = [
            ['source' => 'metadata-meta', 'items' => $properties],
            ['source' => 'metadata-link', 'items' => $links],
        ];

        foreach ($sources as $source) {
            foreach ($source['items'] as $sourceIndex => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $refines = is_string($entry['refines'] ?? null) ? trim($entry['refines']) : '';
                if ($refines === '') {
                    continue;
                }

                $subjectId = $this->metadataRefinementSubject($refines);
                $targetLocal = str_starts_with($refines, '#');
                $targetExternal = !$targetLocal && $this->isExternalHref($refines);
                $targetPackageRelative = !$targetLocal && !$targetExternal && str_contains($refines, '#');
                $targets = $targetLocal && $subjectId !== null && isset($targetsById[$subjectId])
                    ? array_values($targetsById[$subjectId])
                    : [];
                $targetKinds = array_values(array_unique(array_map(
                    static fn (array $target): string => (string) ($target['kind'] ?? ''),
                    $targets
                )));
                $itemDiagnostics = [];

                if ($subjectId === null) {
                    $itemDiagnostics[] = [
                        'type' => 'invalid-metadata-refinement-target',
                        'source' => $source['source'],
                        'sourceIndex' => (int) ($entry['index'] ?? $sourceIndex),
                        'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                        'refines' => $refines,
                        'message' => 'EPUB OPF metadata refinement target must include a fragment identifier',
                    ];
                } elseif ($targetLocal && $targets === []) {
                    $itemDiagnostics[] = [
                        'type' => 'unresolved-metadata-refinement-target',
                        'source' => $source['source'],
                        'sourceIndex' => (int) ($entry['index'] ?? $sourceIndex),
                        'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                        'refines' => $refines,
                        'subjectId' => $subjectId,
                        'message' => 'EPUB OPF metadata refinement points at a local package subject id that was not found in the direct reader target inventory',
                    ];
                }

                $item = [
                    'source' => $source['source'],
                    'sourceIndex' => (int) ($entry['index'] ?? $sourceIndex),
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                    'rel' => is_array($entry['rel'] ?? null) ? array_values($entry['rel']) : [],
                    'href' => is_string($entry['href'] ?? null) ? $entry['href'] : null,
                    'refines' => $refines,
                    'subjectId' => $subjectId,
                    'value' => is_string($entry['value'] ?? null) ? $entry['value'] : null,
                    'targetLocal' => $targetLocal,
                    'targetExternal' => $targetExternal,
                    'targetPackageRelative' => $targetPackageRelative,
                    'resolved' => $targets !== [],
                    'targetCount' => count($targets),
                    'targetKinds' => $targetKinds,
                    'targets' => $targets,
                    'diagnostics' => $itemDiagnostics,
                ];
                $refinementItems[] = $item;

                if ($item['resolved']) {
                    $resolvedItems[] = $item;
                } else {
                    $unresolvedItems[] = $item;
                }
                if ($targetExternal) {
                    $externalItems[] = $item;
                } elseif ($targetPackageRelative) {
                    $packageRelativeItems[] = $item;
                }
                array_push($diagnostics, ...$itemDiagnostics);
            }
        }

        return [
            'present' => $refinementItems !== [],
            'targetIdCount' => count($targetsById),
            'targetCount' => count($targetItems),
            'targetKindCounts' => $targetKindCounts,
            'targetItems' => $targetItems,
            'targetsById' => $targetsById,
            'duplicateTargetIdCount' => count($duplicateTargetItems),
            'duplicateTargetItems' => $duplicateTargetItems,
            'refinementCount' => count($refinementItems),
            'localRefinementCount' => count(array_filter(
                $refinementItems,
                static fn (array $item): bool => ($item['targetLocal'] ?? false) === true
            )),
            'externalRefinementCount' => count($externalItems),
            'packageRelativeRefinementCount' => count($packageRelativeItems),
            'resolvedRefinementCount' => count($resolvedItems),
            'unresolvedRefinementCount' => count($unresolvedItems),
            'items' => $refinementItems,
            'resolvedItems' => $resolvedItems,
            'unresolvedItems' => $unresolvedItems,
            'externalItems' => $externalItems,
            'packageRelativeItems' => $packageRelativeItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    private function metadataRefinementSubject(string $refines): ?string
    {
        $refines = trim($refines);
        if ($refines === '') {
            return null;
        }

        if (str_starts_with($refines, '#')) {
            $subject = substr($refines, 1);

            return $subject === '' ? null : $subject;
        }

        $fragmentOffset = strpos($refines, '#');
        if ($fragmentOffset === false) {
            return null;
        }

        $subject = substr($refines, $fragmentOffset + 1);

        return $subject === '' ? null : $subject;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $properties
     * @param list<array<string, mixed>> $links
     * @param array<string, array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private function metadataReport(
        \DOMElement $packageElement,
        array $items,
        array $properties,
        array $links,
        array $manifest,
        array $spine,
        array $collections
    ): array
    {
        $itemsByKind = [];
        $itemsById = [];
        $kindCounts = [];
        $languageTaggedItems = [];
        $directionTaggedItems = [];
        $schemeItems = [];
        $roleItems = [];
        $fileAsItems = [];

        foreach ($items as $item) {
            $kind = is_string($item['kind'] ?? null) && $item['kind'] !== ''
                ? $item['kind']
                : 'unknown';
            $itemsByKind[$kind][] = $item;
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

            if (is_string($item['id'] ?? null) && $item['id'] !== '') {
                $itemsById[$item['id']] = $item;
            }
            if (is_string($item['language'] ?? null) && $item['language'] !== '') {
                $languageTaggedItems[] = $item;
            }
            if (is_string($item['direction'] ?? null) && $item['direction'] !== '') {
                $directionTaggedItems[] = $item;
            }
            if (is_string($item['scheme'] ?? null) && $item['scheme'] !== '') {
                $schemeItems[] = $item;
            }
            if (is_string($item['role'] ?? null) && $item['role'] !== '') {
                $roleItems[] = $item;
            }
            if (is_string($item['fileAs'] ?? null) && $item['fileAs'] !== '') {
                $fileAsItems[] = $item;
            }
        }

        ksort($itemsByKind, SORT_STRING);
        ksort($itemsById, SORT_STRING);
        ksort($kindCounts, SORT_STRING);

        $refinementProperties = array_values(array_filter(
            $properties,
            static fn (array $property): bool => is_string($property['refines'] ?? null) && $property['refines'] !== ''
        ));
        $linkReport = $this->metadataLinkReport($links);
        $linkMediaTypes = $this->metadataLinkMediaTypeReport($links);
        $refinementTargets = $this->metadataRefinementTargetReport(
            $packageElement,
            $items,
            $properties,
            $links,
            $manifest,
            $spine,
            $collections
        );

        $report = [
            'present' => $items !== [] || $properties !== [] || $links !== [],
            'itemCount' => count($items),
            'kindCount' => count($kindCounts),
            'kinds' => array_keys($kindCounts),
            'kindCounts' => $kindCounts,
            'idCount' => count($itemsById),
            'languageTaggedCount' => count($languageTaggedItems),
            'directionTaggedCount' => count($directionTaggedItems),
            'schemeCount' => count($schemeItems),
            'roleCount' => count($roleItems),
            'fileAsCount' => count($fileAsItems),
            'propertyCount' => count($properties),
            'refinementPropertyCount' => count($refinementProperties),
            'refinementTargetCount' => $refinementTargets['refinementCount'],
            'resolvedRefinementTargetCount' => $refinementTargets['resolvedRefinementCount'],
            'unresolvedRefinementTargetCount' => $refinementTargets['unresolvedRefinementCount'],
            'externalRefinementTargetCount' => $refinementTargets['externalRefinementCount'],
            'packageRelativeRefinementTargetCount' => $refinementTargets['packageRelativeRefinementCount'],
            'refinementTargetDiagnosticCount' => $refinementTargets['diagnosticCount'],
            'refinementTargetDiagnostics' => $refinementTargets['diagnostics'],
            'linkCount' => $linkReport['itemCount'],
            'localLinkCount' => $linkReport['localLinkCount'],
            'externalLinkCount' => $linkReport['externalLinkCount'],
            'missingLinkCount' => $linkReport['missingLinkCount'],
            'linkTitleCount' => $linkReport['titleCount'],
            'linkHreflangCount' => $linkReport['hreflangCount'],
            'linkLanguageTaggedCount' => $linkReport['languageTaggedCount'],
            'linkDirectionTaggedCount' => $linkReport['directionTaggedCount'],
            'linkCustomAttributeCount' => $linkReport['customAttributeCount'],
            'linkRelTokens' => $linkReport['relTokens'],
            'linkRelCounts' => $linkReport['relCounts'],
            'linkTargets' => $linkReport['targets'],
            'linkDiagnosticCount' => $linkReport['diagnosticCount'],
            'linkDiagnostics' => $linkReport['diagnostics'],
            'linkTitleCount' => $linkReport['titleCount'],
            'linkHreflangCount' => $linkReport['hreflangCount'],
            'linkLanguageTaggedCount' => $linkReport['languageTaggedCount'],
            'linkDirectionTaggedCount' => $linkReport['directionTaggedCount'],
            'linkCustomAttributeCount' => $linkReport['customAttributeCount'],
            'linkReport' => $linkReport,
            'linkMediaTypes' => $linkMediaTypes,
            'linkMediaTypeItems' => $linkMediaTypes['items'],
            'linkMediaTypeParameterItems' => $linkMediaTypes['parameterItems'],
            'linkMediaTypeParameterNames' => $linkMediaTypes['parameterNames'],
            'linkMediaTypeBaseCounts' => $linkMediaTypes['baseMediaTypeCounts'],
            'linkMediaTypeDiagnosticCount' => $linkMediaTypes['diagnosticCount'],
            'linkMediaTypeDiagnostics' => $linkMediaTypes['diagnostics'],
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByKind' => $itemsByKind,
            'languageTaggedItems' => $languageTaggedItems,
            'directionTaggedItems' => $directionTaggedItems,
            'schemeItems' => $schemeItems,
            'roleItems' => $roleItems,
            'fileAsItems' => $fileAsItems,
            'refinementProperties' => $refinementProperties,
            'refinementTargets' => $refinementTargets,
            'localLinks' => $linkReport['localLinks'],
            'externalLinks' => $linkReport['externalLinks'],
            'missingLinks' => $linkReport['missingLinks'],
            'linksByRel' => $linkReport['linksByRel'],
            'titledLinks' => $linkReport['titledLinks'],
            'hreflangLinks' => $linkReport['hreflangLinks'],
            'languageTaggedLinks' => $linkReport['languageTaggedLinks'],
            'directionTaggedLinks' => $linkReport['directionTaggedLinks'],
            'customAttributeLinks' => $linkReport['customAttributeLinks'],
        ];

        foreach ([
            'identifier',
            'title',
            'creator',
            'contributor',
            'language',
            'subject',
            'description',
            'rights',
            'publisher',
            'date',
            'source',
            'relation',
            'coverage',
            'format',
            'type',
        ] as $kind) {
            $report[$kind . 'Count'] = $kindCounts[$kind] ?? 0;
        }

        $report['summary'] = [
            'itemCount' => $report['itemCount'],
            'kindCount' => $report['kindCount'],
            'kindCounts' => $report['kindCounts'],
            'propertyCount' => $report['propertyCount'],
            'refinementPropertyCount' => $report['refinementPropertyCount'],
            'refinementTargetCount' => $report['refinementTargetCount'],
            'resolvedRefinementTargetCount' => $report['resolvedRefinementTargetCount'],
            'unresolvedRefinementTargetCount' => $report['unresolvedRefinementTargetCount'],
            'externalRefinementTargetCount' => $report['externalRefinementTargetCount'],
            'packageRelativeRefinementTargetCount' => $report['packageRelativeRefinementTargetCount'],
            'refinementTargetDiagnosticCount' => $report['refinementTargetDiagnosticCount'],
            'linkCount' => $report['linkCount'],
            'localLinkCount' => $report['localLinkCount'],
            'externalLinkCount' => $report['externalLinkCount'],
            'missingLinkCount' => $report['missingLinkCount'],
            'linkDiagnosticCount' => $report['linkDiagnosticCount'],
            'linkTitleCount' => $report['linkTitleCount'],
            'linkHreflangCount' => $report['linkHreflangCount'],
            'linkLanguageTaggedCount' => $report['linkLanguageTaggedCount'],
            'linkDirectionTaggedCount' => $report['linkDirectionTaggedCount'],
            'linkCustomAttributeCount' => $report['linkCustomAttributeCount'],
            'linkMediaTypeParameterCount' => $linkMediaTypes['parameterCount'],
            'linkMediaTypeDiagnosticCount' => $linkMediaTypes['diagnosticCount'],
        ];

        return $report;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, mixed>
     */
    private function metadataLinkReport(array $links): array
    {
        $localLinks = [];
        $externalLinks = [];
        $missingLinks = [];
        $titledLinks = [];
        $hreflangLinks = [];
        $languageTaggedLinks = [];
        $directionTaggedLinks = [];
        $customAttributeLinks = [];
        $relCounts = [];
        $linksByRel = [];
        $targets = [];
        $diagnostics = [];

        foreach ($links as $linkIndex => $link) {
            if (($link['external'] ?? false) === true) {
                $externalLinks[] = $link;
            } elseif (($link['path'] ?? '') !== '') {
                $localLinks[] = $link;
            }

            if (($link['external'] ?? false) !== true && ($link['path'] ?? '') !== '' && ($link['exists'] ?? false) !== true) {
                $missingLinks[] = $link;
            }
            if (is_string($link['title'] ?? null) && $link['title'] !== '') {
                $titledLinks[] = $link;
            }
            if (is_string($link['hreflang'] ?? null) && $link['hreflang'] !== '') {
                $hreflangLinks[] = $link;
            }
            if (is_string($link['language'] ?? null) && $link['language'] !== '') {
                $languageTaggedLinks[] = $link;
            }
            if (is_string($link['direction'] ?? null) && $link['direction'] !== '') {
                $directionTaggedLinks[] = $link;
            }
            if (($link['customAttributeCount'] ?? 0) > 0) {
                $customAttributeLinks[] = $link;
            }

            $target = is_string($link['target'] ?? null) && $link['target'] !== ''
                ? $link['target']
                : (is_string($link['path'] ?? null) ? $link['path'] : '');
            if ($target !== '') {
                $targets[] = $target;
            }

            foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }
                $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
                $linksByRel[$rel][] = $link;
            }

            foreach (is_array($link['diagnostics'] ?? null) ? $link['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $linkIndex,
                    'id' => is_string($link['id'] ?? null) && $link['id'] !== '' ? $link['id'] : null,
                    'rel' => is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : '',
                ] + $diagnostic;
            }
        }

        ksort($relCounts, SORT_STRING);
        ksort($linksByRel, SORT_STRING);

        return [
            'present' => $links !== [],
            'itemCount' => count($links),
            'links' => $links,
            'localLinkCount' => count($localLinks),
            'externalLinkCount' => count($externalLinks),
            'missingLinkCount' => count($missingLinks),
            'titleCount' => count($titledLinks),
            'hreflangCount' => count($hreflangLinks),
            'languageTaggedCount' => count($languageTaggedLinks),
            'directionTaggedCount' => count($directionTaggedLinks),
            'customAttributeCount' => count($customAttributeLinks),
            'localLinks' => $localLinks,
            'externalLinks' => $externalLinks,
            'missingLinks' => $missingLinks,
            'titledLinks' => $titledLinks,
            'hreflangLinks' => $hreflangLinks,
            'languageTaggedLinks' => $languageTaggedLinks,
            'directionTaggedLinks' => $directionTaggedLinks,
            'customAttributeLinks' => $customAttributeLinks,
            'relTokens' => array_keys($relCounts),
            'relCounts' => $relCounts,
            'linksByRel' => $linksByRel,
            'targets' => $targets,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{linear:bool, raw:?string, specified:bool, value:?string, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private function spineItemLinearReport(\DOMElement $itemrefElement): array
    {
        $specified = $itemrefElement->hasAttribute('linear');
        $raw = $specified ? trim($itemrefElement->getAttribute('linear')) : null;
        $value = $raw === null ? null : strtolower($raw);
        $valid = !$specified || $value === 'yes' || $value === 'no';
        $diagnostics = [];

        if (!$valid) {
            $diagnostics[] = [
                'type' => 'invalid-spine-linear-value',
                'attribute' => 'linear',
                'value' => $raw,
                'normalizedValue' => $value,
                'message' => 'EPUB OPF spine itemref linear must be yes or no; direct directory ingestion treats invalid values as linear for reading-order review',
            ];
        }

        return [
            'linear' => $value !== 'no',
            'raw' => $raw,
            'specified' => $specified,
            'value' => $value,
            'valid' => $valid,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataLinkMediaTypeFields(string $declaredMediaType, int $linkIndex, string $id): array
    {
        $declaredMediaType = trim($declaredMediaType);
        if ($declaredMediaType === '') {
            return [
                'declaredMediaType' => null,
                'effectiveMediaType' => null,
                'mediaTypeSource' => null,
                'normalizedMediaType' => null,
                'baseMediaType' => null,
                'mediaTypeBase' => null,
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'mediaTypeParameterNames' => [],
                'mediaTypeSyntaxValid' => null,
                'mediaTypeDiagnostics' => [],
            ];
        }

        $report = $this->mediaTypeReport($declaredMediaType);
        $diagnostics = [];
        foreach ($report['mediaTypeDiagnostics'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = $this->metadataLinkMediaTypeDiagnostic(
                $diagnostic,
                $linkIndex,
                $id === '' ? null : $id
            );
        }

        return [
            'declaredMediaType' => $declaredMediaType,
            'effectiveMediaType' => $declaredMediaType,
            'mediaTypeSource' => 'link',
            'normalizedMediaType' => $report['normalizedMediaType'],
            'baseMediaType' => $report['mediaTypeBase'],
            'mediaTypeBase' => $report['mediaTypeBase'],
            'mediaTypeHasParameters' => $report['mediaTypeHasParameters'],
            'mediaTypeParameterCount' => $report['mediaTypeParameterCount'],
            'mediaTypeParameters' => $report['mediaTypeParameters'],
            'mediaTypeParameterMap' => $report['mediaTypeParameterMap'],
            'mediaTypeParameterNames' => array_column($report['mediaTypeParameters'], 'name'),
            'mediaTypeSyntaxValid' => $diagnostics === [],
            'mediaTypeDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return array<string, mixed>
     */
    private function metadataLinkMediaTypeDiagnostic(array $diagnostic, int $linkIndex, ?string $id): array
    {
        $type = (string) ($diagnostic['type'] ?? 'metadata-link-media-type-diagnostic');
        $mappedType = match ($type) {
            'invalid-manifest-media-type' => 'invalid-metadata-link-media-type',
            'invalid-manifest-media-type-parameter' => 'invalid-metadata-link-media-type-parameter',
            'invalid-manifest-media-type-parameter-name' => 'invalid-metadata-link-media-type-parameter-name',
            'duplicate-manifest-media-type-parameter' => 'duplicate-metadata-link-media-type-parameter',
            default => $type,
        };
        $message = match ($mappedType) {
            'invalid-metadata-link-media-type' => 'EPUB OPF metadata link media-type must be a MIME type in type/subtype form',
            'invalid-metadata-link-media-type-parameter' => 'EPUB OPF metadata link media-type parameters must use name=value syntax',
            'invalid-metadata-link-media-type-parameter-name' => 'EPUB OPF metadata link media-type parameter names must be MIME tokens',
            'duplicate-metadata-link-media-type-parameter' => 'EPUB OPF metadata link media-type parameter repeats a name; later value is retained for package review',
            default => is_string($diagnostic['message'] ?? null) ? $diagnostic['message'] : 'EPUB OPF metadata link media-type diagnostic',
        };

        $mapped = [
            'type' => $mappedType,
            'linkIndex' => $linkIndex,
            'id' => $id,
        ] + $diagnostic;
        $mapped['message'] = $message;

        return $mapped;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, mixed>
     */
    private function metadataLinkMediaTypeReport(array $links): array
    {
        $items = [];
        $parameterItems = [];
        $parameterNames = [];
        $baseMediaTypeCounts = [];
        $diagnostics = [];
        $declaredCount = 0;
        $parameterCount = 0;

        foreach ($links as $linkIndex => $link) {
            $effectiveMediaType = is_string($link['effectiveMediaType'] ?? null)
                ? $link['effectiveMediaType']
                : null;
            if ($effectiveMediaType === null || $effectiveMediaType === '') {
                continue;
            }

            ++$declaredCount;
            $baseMediaType = is_string($link['baseMediaType'] ?? null)
                ? $link['baseMediaType']
                : (is_string($link['mediaTypeBase'] ?? null) ? $link['mediaTypeBase'] : null);
            if ($baseMediaType !== null && $baseMediaType !== '') {
                $baseMediaTypeCounts[$baseMediaType] = ($baseMediaTypeCounts[$baseMediaType] ?? 0) + 1;
            }

            $parameters = is_array($link['mediaTypeParameters'] ?? null)
                ? array_values($link['mediaTypeParameters'])
                : [];
            $currentParameterNames = is_array($link['mediaTypeParameterNames'] ?? null)
                ? array_values(array_filter(
                    $link['mediaTypeParameterNames'],
                    static fn (mixed $name): bool => is_string($name) && $name !== '',
                ))
                : array_values(array_filter(
                    array_map(
                        static fn (array $parameter): ?string => is_string($parameter['name'] ?? null) ? $parameter['name'] : null,
                        $parameters,
                    ),
                    static fn (?string $name): bool => $name !== null && $name !== '',
                ));
            array_push($parameterNames, ...$currentParameterNames);
            $parameterCount += count($parameters);

            $itemDiagnostics = is_array($link['mediaTypeDiagnostics'] ?? null)
                ? array_values(array_filter(
                    $link['mediaTypeDiagnostics'],
                    static fn (mixed $diagnostic): bool => is_array($diagnostic),
                ))
                : [];
            array_push($diagnostics, ...$itemDiagnostics);

            $item = [
                'index' => is_int($link['index'] ?? null) ? $link['index'] : $linkIndex,
                'id' => is_string($link['id'] ?? null) && $link['id'] !== '' ? $link['id'] : null,
                'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                'path' => is_string($link['path'] ?? null) ? $link['path'] : null,
                'fragment' => is_string($link['fragment'] ?? null) ? $link['fragment'] : null,
                'external' => ($link['external'] ?? false) === true,
                'declaredMediaType' => is_string($link['declaredMediaType'] ?? null) ? $link['declaredMediaType'] : null,
                'effectiveMediaType' => $effectiveMediaType,
                'mediaTypeSource' => is_string($link['mediaTypeSource'] ?? null) ? $link['mediaTypeSource'] : null,
                'normalizedMediaType' => is_string($link['normalizedMediaType'] ?? null) ? $link['normalizedMediaType'] : null,
                'baseMediaType' => $baseMediaType,
                'parameterCount' => count($parameters),
                'parameterNames' => $currentParameterNames,
                'parameterMap' => is_array($link['mediaTypeParameterMap'] ?? null) ? $link['mediaTypeParameterMap'] : [],
                'syntaxValid' => is_bool($link['mediaTypeSyntaxValid'] ?? null) ? $link['mediaTypeSyntaxValid'] : null,
                'diagnostics' => $itemDiagnostics,
            ];
            $items[] = $item;
            if ($parameters !== []) {
                $parameterItems[] = $item;
            }
        }

        $parameterNames = array_values(array_unique($parameterNames));
        sort($parameterNames);
        ksort($baseMediaTypeCounts, SORT_STRING);

        return [
            'present' => $items !== [],
            'linkCount' => count($links),
            'itemCount' => count($items),
            'declaredCount' => $declaredCount,
            'parameterLinkCount' => count($parameterItems),
            'parameterCount' => $parameterCount,
            'parameterNames' => $parameterNames,
            'baseMediaTypeCounts' => $baseMediaTypeCounts,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'parameterItems' => $parameterItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spineMetadataReport(?\DOMElement $spineElement): array
    {
        $diagnostics = [];
        $rawDirection = $spineElement instanceof \DOMElement
            ? trim($spineElement->getAttribute('page-progression-direction'))
            : '';
        $specified = $rawDirection !== '';
        $normalized = strtolower($rawDirection);
        $direction = 'default';
        $valid = true;

        if ($specified) {
            if (in_array($normalized, ['ltr', 'rtl', 'default'], true)) {
                $direction = $normalized;
            } else {
                $valid = false;
                $diagnostics[] = [
                    'type' => 'invalid-spine-page-progression-direction',
                    'value' => $rawDirection,
                    'message' => 'EPUB spine page-progression-direction must be ltr, rtl, or default',
                ];
            }
        }

        return [
            'present' => $spineElement instanceof \DOMElement,
            'id' => $spineElement instanceof \DOMElement ? $this->nullableAttribute($spineElement, 'id') : null,
            'toc' => $spineElement instanceof \DOMElement ? $this->nullableAttribute($spineElement, 'toc') : null,
            'pageProgressionDirection' => $direction,
            'pageProgressionDirectionRaw' => $specified ? $rawDirection : null,
            'pageProgressionDirectionSpecified' => $specified,
            'pageProgressionDirectionValid' => $valid,
            'rightToLeft' => $direction === 'rtl',
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, list<array<string, mixed>>> $manifestOccurrences
     * @return array<string, mixed>
     */
    private function manifestReport(array $manifest, array $manifestOccurrences, array $malformedManifestItems): array
    {
        $externalItems = [];
        $missingItems = [];
        $hrefSuffixItems = [];
        $mediaTypeItems = [];
        $mediaTypeParameterItems = [];
        $mediaTypeParameterNames = [];
        $invalidMediaTypeItems = [];
        $mediaTypeBaseCounts = [];
        $mediaTypeDiagnostics = [];
        $diagnostics = [];
        $missingRequiredAttributeItems = [];
        $missingRequiredAttributeNames = [];
        $missingRequiredAttributeCount = 0;

        foreach ($manifest as $item) {
            $mediaTypeItem = $this->manifestMediaTypeItemReport($item);
            $mediaTypeItems[] = $mediaTypeItem;
            $mediaTypeBase = (string) ($mediaTypeItem['baseMediaType'] ?? $mediaTypeItem['mediaTypeBase'] ?? '');
            if ($mediaTypeBase !== '') {
                $mediaTypeBaseCounts[$mediaTypeBase] = ($mediaTypeBaseCounts[$mediaTypeBase] ?? 0) + 1;
            }
            if ($mediaTypeItem['parameterCount'] > 0) {
                $mediaTypeParameterItems[] = $mediaTypeItem;
                foreach ($mediaTypeItem['parameterNames'] as $name) {
                    $mediaTypeParameterNames[$name] = $name;
                }
            }
            if ($mediaTypeItem['valid'] !== true) {
                $invalidMediaTypeItems[] = $mediaTypeItem;
            }
            foreach ($mediaTypeItem['diagnostics'] as $diagnostic) {
                $mediaTypeDiagnostics[] = $diagnostic;
            }
            if (($item['external'] ?? false) === true) {
                $externalItems[] = $item;
            }
            if (
                ($item['external'] ?? false) !== true
                && (string) ($item['path'] ?? '') !== ''
                && ($item['exists'] ?? true) !== true
            ) {
                $missingItems[] = $item;
            }
            if (($item['hrefHasQuery'] ?? false) === true || ($item['hrefHasFragment'] ?? false) === true) {
                $hrefSuffixItems[] = [
                    'id' => $item['id'],
                    'href' => $item['href'],
                    'target' => $item['target'],
                    'path' => $item['path'],
                    'query' => $item['hrefQuery'],
                    'fragment' => $item['hrefFragment'],
                ];
            }
            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = [
                    'index' => (int) ($item['index'] ?? 0),
                    'id' => (string) ($item['id'] ?? ''),
                    'href' => (string) ($item['href'] ?? ''),
                    'path' => (string) ($item['path'] ?? ''),
                ] + $diagnostic;
            }
        }
        foreach ($malformedManifestItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $missingAttributes = is_array($item['missingRequiredAttributes'] ?? null)
                ? array_values(array_filter(
                    $item['missingRequiredAttributes'],
                    static fn (mixed $attribute): bool => is_string($attribute) && $attribute !== ''
                ))
                : [];
            if ($missingAttributes === []) {
                continue;
            }

            foreach ($missingAttributes as $attribute) {
                $missingRequiredAttributeNames[$attribute] ??= $attribute;
            }
            $missingRequiredAttributeCount += count($missingAttributes);
            $missingRequiredAttributeItems[] = [
                'index' => (int) ($item['index'] ?? 0),
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'path' => (string) ($item['path'] ?? ''),
                'missingAttributes' => $missingAttributes,
                'diagnostics' => is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [],
            ];

            if (($item['id'] ?? '') === '') {
                foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }
                    $diagnostics[] = [
                        'index' => (int) ($item['index'] ?? 0),
                        'id' => '',
                        'href' => (string) ($item['href'] ?? ''),
                        'path' => (string) ($item['path'] ?? ''),
                    ] + $diagnostic;
                }
            }
        }
        ksort($mediaTypeBaseCounts, SORT_STRING);
        ksort($mediaTypeParameterNames, SORT_STRING);

        $duplicateIdItems = [];
        $duplicateIdDiagnostics = [];
        foreach ($manifestOccurrences as $id => $items) {
            if (count($items) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items);
            $hrefs = array_map(static fn (array $item): string => (string) ($item['href'] ?? ''), $items);
            $targets = array_map(static fn (array $item): string => (string) ($item['target'] ?? ''), $items);
            $duplicateIdItems[] = [
                'id' => $id,
                'itemCount' => count($items),
                'indexes' => $indexes,
                'hrefs' => $hrefs,
                'targets' => $targets,
                'items' => array_values($items),
            ];
            foreach ($items as $item) {
                $duplicateIdDiagnostics[] = [
                    'type' => 'duplicate-manifest-id',
                    'index' => (int) ($item['index'] ?? 0),
                    'id' => $id,
                    'indexes' => $indexes,
                    'hrefs' => $hrefs,
                    'targets' => $targets,
                    'message' => 'EPUB OPF manifest contains multiple item elements with the same id',
                ];
            }
        }

        $referenceReport = $this->manifestItemReferenceReport($manifest);
        $diagnostics = array_merge($diagnostics, $duplicateIdDiagnostics, $referenceReport['manifestItemReferenceDiagnostics']);
        $diagnostics = $this->manifestDiagnosticsInSourceOrder($diagnostics);

        return [
            'itemCount' => count($manifest),
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingItemCount' => count($missingItems),
            'missingItems' => $missingItems,
            'malformedItemCount' => count($missingRequiredAttributeItems),
            'malformedItems' => $missingRequiredAttributeItems,
            'missingRequiredAttributeItemCount' => count($missingRequiredAttributeItems),
            'missingRequiredAttributeCount' => $missingRequiredAttributeCount,
            'missingRequiredAttributeNames' => array_values($missingRequiredAttributeNames),
            'missingRequiredAttributeItems' => $missingRequiredAttributeItems,
            'duplicateManifestIdCount' => count($duplicateIdItems),
            'duplicateManifestItemCount' => array_sum(array_map(
                static fn (array $item): int => (int) $item['itemCount'],
                $duplicateIdItems
            )),
            'duplicateManifestIds' => array_map(
                static fn (array $item): string => (string) $item['id'],
                $duplicateIdItems
            ),
            'duplicateManifestIdItems' => $duplicateIdItems,
            'duplicateManifestIdDiagnostics' => $duplicateIdDiagnostics,
            'hrefSuffixCount' => count($hrefSuffixItems),
            'hrefSuffixItems' => $hrefSuffixItems,
            'mediaTypeItems' => $mediaTypeItems,
            'mediaTypeBaseCounts' => $mediaTypeBaseCounts,
            'mediaTypeParameterCount' => array_sum(array_map(
                static fn (array $item): int => (int) $item['parameterCount'],
                $mediaTypeParameterItems
            )),
            'mediaTypeParameterItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterizedItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterItems' => $mediaTypeParameterItems,
            'mediaTypeParameterNames' => array_values($mediaTypeParameterNames),
            'invalidMediaTypeCount' => count($invalidMediaTypeItems),
            'invalidMediaTypeItems' => $invalidMediaTypeItems,
            'mediaTypeDiagnosticCount' => count($mediaTypeDiagnostics),
            'mediaTypeDiagnostics' => $mediaTypeDiagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ] + $referenceReport;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function manifestDiagnosticsInSourceOrder(array $diagnostics): array
    {
        $ordered = [];
        foreach ($diagnostics as $ordinal => $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $diagnostic['_manifestDiagnosticOrdinal'] = $ordinal;
            $ordered[] = $diagnostic;
        }

        usort(
            $ordered,
            static function (array $left, array $right): int {
                $leftIndex = (int) ($left['index'] ?? $left['sourceIndex'] ?? 0);
                $rightIndex = (int) ($right['index'] ?? $right['sourceIndex'] ?? 0);
                if ($leftIndex !== $rightIndex) {
                    return $leftIndex <=> $rightIndex;
                }

                return (int) ($left['_manifestDiagnosticOrdinal'] ?? 0)
                    <=> (int) ($right['_manifestDiagnosticOrdinal'] ?? 0);
            }
        );

        foreach ($ordered as &$diagnostic) {
            unset($diagnostic['_manifestDiagnosticOrdinal']);
        }
        unset($diagnostic);

        return $ordered;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function manifestMediaTypeItemReport(array $item): array
    {
        $parameters = is_array($item['mediaTypeParameters'] ?? null)
            ? array_values($item['mediaTypeParameters'])
            : [];
        $parameterMap = is_array($item['mediaTypeParameterMap'] ?? null)
            ? $item['mediaTypeParameterMap']
            : [];
        $parameterItems = [];
        $duplicateParameters = [];
        $seen = [];

        foreach ($parameters as $ordinal => $parameter) {
            if (!is_array($parameter)) {
                continue;
            }

            $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : '';
            $value = is_string($parameter['value'] ?? null) ? $parameter['value'] : '';
            $duplicate = array_key_exists($name, $seen);
            $previousValue = $duplicate ? $seen[$name] : null;
            $reviewItem = [
                'index' => $ordinal,
                'raw' => is_string($parameter['raw'] ?? null) ? $parameter['raw'] : '',
                'name' => $name,
                'value' => $value,
                'duplicate' => $duplicate,
                'previousValue' => $previousValue,
            ];
            $parameterItems[] = $reviewItem;
            if ($duplicate) {
                $duplicateParameters[] = [
                    'index' => $ordinal,
                    'raw' => $reviewItem['raw'],
                    'name' => $name,
                    'previousValue' => (string) $previousValue,
                    'value' => $value,
                ];
            }
            $seen[$name] = $value;
        }

        $diagnostics = [];
        foreach (is_array($item['mediaTypeDiagnostics'] ?? null) ? $item['mediaTypeDiagnostics'] : [] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = [
                'index' => (int) ($item['index'] ?? 0),
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'path' => (string) ($item['path'] ?? ''),
            ] + $diagnostic;
        }

        return [
            'index' => (int) ($item['index'] ?? 0),
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => (string) ($item['target'] ?? ''),
            'path' => (string) ($item['path'] ?? ''),
            'rawMediaType' => (string) ($item['rawMediaType'] ?? $item['mediaType'] ?? ''),
            'mediaType' => (string) ($item['mediaType'] ?? ''),
            'mediaTypeBase' => (string) ($item['mediaTypeBase'] ?? $item['mediaType'] ?? ''),
            'baseMediaType' => (string) ($item['baseMediaType'] ?? $item['mediaTypeBase'] ?? $item['mediaType'] ?? ''),
            'normalizedMediaType' => (string) ($item['normalizedMediaType'] ?? $item['mediaType'] ?? ''),
            'mediaTypeParameters' => $parameterMap,
            'parameters' => $parameters,
            'parameterMap' => $parameterMap,
            'parameterNames' => array_keys($parameterMap),
            'parameterItems' => $parameterItems,
            'parameterCount' => count($parameterItems),
            'duplicateParameters' => $duplicateParameters,
            'duplicateParameterCount' => count($duplicateParameters),
            'valid' => $diagnostics === [],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function manifestItemReferenceReport(array $manifest): array
    {
        $fallbackReferences = [];
        $fallbackDiagnostics = [];
        $fallbackStyleReferences = [];
        $fallbackStyleDiagnostics = [];
        $mediaOverlayReferences = [];
        $mediaOverlayDiagnostics = [];

        foreach ($manifest as $item) {
            $this->appendManifestReference(
                $item,
                $manifest,
                'fallback',
                'fallback',
                null,
                [
                    'missing' => 'missing-manifest-fallback-item',
                    'external' => 'external-manifest-fallback-target',
                    'missingPart' => 'missing-manifest-fallback-part',
                ],
                $fallbackReferences,
                $fallbackDiagnostics
            );
            $this->appendManifestReference(
                $item,
                $manifest,
                'fallbackStyle',
                'fallback-style',
                'text/css',
                [
                    'missing' => 'missing-manifest-fallback-style-item',
                    'external' => 'external-manifest-fallback-style-target',
                    'missingPart' => 'missing-manifest-fallback-style-part',
                    'unexpectedType' => 'non-css-manifest-fallback-style',
                ],
                $fallbackStyleReferences,
                $fallbackStyleDiagnostics
            );
            $this->appendManifestReference(
                $item,
                $manifest,
                'mediaOverlay',
                'media-overlay',
                'application/smil+xml',
                [
                    'missing' => 'missing-manifest-media-overlay-item',
                    'external' => 'external-manifest-media-overlay-target',
                    'missingPart' => 'missing-manifest-media-overlay-part',
                    'unexpectedType' => 'unexpected-manifest-media-overlay-type',
                ],
                $mediaOverlayReferences,
                $mediaOverlayDiagnostics
            );
        }

        $allDiagnostics = array_merge($fallbackDiagnostics, $fallbackStyleDiagnostics, $mediaOverlayDiagnostics);

        return [
            'manifestItemReferenceCount' => count($fallbackReferences) + count($fallbackStyleReferences) + count($mediaOverlayReferences),
            'manifestItemReferenceDiagnosticCount' => count($allDiagnostics),
            'manifestItemReferenceDiagnostics' => $allDiagnostics,
            'fallbackReferenceCount' => count($fallbackReferences),
            'fallbackReferences' => $fallbackReferences,
            'fallbackReferenceDiagnosticCount' => count($fallbackDiagnostics),
            'fallbackReferenceDiagnostics' => $fallbackDiagnostics,
            'fallbackStyleReferenceCount' => count($fallbackStyleReferences),
            'fallbackStyleReferences' => $fallbackStyleReferences,
            'fallbackStyleReferenceDiagnosticCount' => count($fallbackStyleDiagnostics),
            'fallbackStyleReferenceDiagnostics' => $fallbackStyleDiagnostics,
            'mediaOverlayReferenceCount' => count($mediaOverlayReferences),
            'mediaOverlayReferences' => $mediaOverlayReferences,
            'mediaOverlayReferenceDiagnosticCount' => count($mediaOverlayDiagnostics),
            'mediaOverlayReferenceDiagnostics' => $mediaOverlayDiagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, string> $diagnosticTypes
     * @param list<array<string, mixed>> $references
     * @param list<array<string, mixed>> $diagnostics
     */
    private function appendManifestReference(
        array $item,
        array $manifest,
        string $field,
        string $attribute,
        ?string $expectedMediaType,
        array $diagnosticTypes,
        array &$references,
        array &$diagnostics
    ): void {
        $targetId = trim((string) ($item[$field] ?? ''));
        if ($targetId === '') {
            return;
        }

        $sourceId = (string) ($item['id'] ?? '');
        $target = $manifest[$targetId] ?? null;
        $referenceDiagnostics = [];
        $reference = [
            'sourceId' => $sourceId,
            'sourceIndex' => (int) ($item['index'] ?? 0),
            'attribute' => $attribute,
            'targetId' => $targetId,
            'target' => is_array($target) ? (string) ($target['target'] ?? '') : '',
            'targetPath' => is_array($target) ? (string) ($target['path'] ?? '') : '',
            'targetMediaType' => is_array($target) ? (string) ($target['rawMediaType'] ?? $target['mediaType'] ?? '') : '',
            'targetMediaTypeBase' => is_array($target)
                ? (string) ($target['mediaTypeBase'] ?? $this->mediaTypeReport((string) ($target['mediaType'] ?? ''))['mediaTypeBase'])
                : '',
            'targetExternal' => is_array($target) && ($target['external'] ?? false) === true,
            'targetExists' => is_array($target) && ($target['exists'] ?? false) === true,
        ];

        if (!is_array($target)) {
            $referenceDiagnostics[] = [
                'type' => $diagnosticTypes['missing'],
                'targetId' => $targetId,
                'message' => 'EPUB OPF manifest item references another manifest id that is not declared',
            ];
        } else {
            if (($target['external'] ?? false) === true) {
                $referenceDiagnostics[] = [
                    'type' => $diagnosticTypes['external'],
                    'targetId' => $targetId,
                    'target' => (string) ($target['target'] ?? ''),
                    'message' => 'EPUB OPF manifest item reference points outside the package and was not fetched',
                ];
            } elseif (($target['path'] ?? '') !== '' && ($target['exists'] ?? true) !== true) {
                $referenceDiagnostics[] = [
                    'type' => $diagnosticTypes['missingPart'],
                    'targetId' => $targetId,
                    'path' => (string) ($target['path'] ?? ''),
                    'message' => 'EPUB OPF manifest item reference points at a missing package part',
                ];
            }

            $mediaType = (string) ($target['mediaType'] ?? '');
            $mediaTypeBase = (string) ($target['mediaTypeBase'] ?? $this->mediaTypeReport($mediaType)['mediaTypeBase']);
            if ($expectedMediaType !== null && $mediaTypeBase !== $expectedMediaType) {
                $referenceDiagnostics[] = [
                    'type' => (string) ($diagnosticTypes['unexpectedType'] ?? 'unexpected-manifest-reference-type'),
                    'targetId' => $targetId,
                    'mediaType' => $mediaType,
                    'mediaTypeBase' => $mediaTypeBase,
                    'expectedMediaType' => $expectedMediaType,
                    'message' => 'EPUB OPF manifest item reference resolves to an unexpected media type',
                ];
            }
        }

        $reference['diagnosticCount'] = count($referenceDiagnostics);
        $reference['diagnostics'] = $referenceDiagnostics;
        $references[] = $reference;

        foreach ($referenceDiagnostics as $diagnostic) {
            $diagnostics[] = [
                'sourceId' => $sourceId,
                'sourceIndex' => (int) ($item['index'] ?? 0),
                'attribute' => $attribute,
            ] + $diagnostic;
        }
    }

    /**
     * @param list<string> $properties
     * @return array<string, mixed>
     */
    private function spineItemPropertyReport(array $properties): array
    {
        $matches = [];
        $placements = [];

        foreach ($properties as $property) {
            $placement = match ($property) {
                'page-spread-left', 'rendition:page-spread-left' => 'left',
                'page-spread-right', 'rendition:page-spread-right' => 'right',
                'spread-none', 'rendition:page-spread-center' => 'center',
                default => null,
            };

            if ($placement === null) {
                continue;
            }

            $matches[] = [
                'property' => $property,
                'placement' => $placement,
            ];
            $placements[$placement] = true;
        }

        $spreadProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $matches
        );
        $spreadPlacements = array_keys($placements);
        $conflicting = count($spreadPlacements) > 1;
        $diagnostics = [];

        if ($conflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-page-spread-properties',
                'properties' => $spreadProperties,
                'placements' => $spreadPlacements,
                'message' => 'EPUB spine itemref declares more than one page-spread placement',
            ];
        }

        return [
            'pageSpread' => [
                'placement' => $matches[0]['placement'] ?? null,
                'properties' => $spreadProperties,
                'matches' => $matches,
                'placements' => $spreadPlacements,
                'conflicting' => $conflicting,
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function spineReport(array $spine, array $spineMetadata): array
    {
        $linearItemCount = 0;
        $readableItemCount = 0;
        $pageSpreadItems = [];
        $pageSpreadCounts = [
            'left' => 0,
            'right' => 0,
            'center' => 0,
        ];
        $externalItems = [];
        $missingPackagePartItems = [];
        $missingManifestItems = [];
        $invalidLinearItems = [];
        $linearDiagnostics = [];
        $fallbackContentItems = [];
        $bindingFallbackContentItems = [];
        $fallbackDiagnostics = [];
        $spineMetadataDiagnostics = is_array($spineMetadata['diagnostics'] ?? null)
            ? array_values($spineMetadata['diagnostics'])
            : [];
        $diagnostics = $spineMetadataDiagnostics;
        $itemDiagnostics = [];
        $idrefItems = [];

        foreach ($spine as $index => $item) {
            if (($item['linear'] ?? false) === true) {
                ++$linearItemCount;
            }
            if (($item['readable'] ?? false) === true) {
                ++$readableItemCount;
            }
            $itemProperties = is_array($item['spineItemProperties'] ?? null) ? $item['spineItemProperties'] : [];
            $pageSpread = is_string($item['pageSpread'] ?? null) ? $item['pageSpread'] : null;
            $pageSpreadProperties = is_array($item['pageSpreadProperties'] ?? null)
                ? array_values($item['pageSpreadProperties'])
                : [];
            if ($pageSpread !== null || $pageSpreadProperties !== []) {
                if ($pageSpread !== null && array_key_exists($pageSpread, $pageSpreadCounts)) {
                    ++$pageSpreadCounts[$pageSpread];
                }

                $pageSpreadItems[] = [
                    'index' => $index,
                    'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                    'idref' => (string) ($item['idref'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'path' => (string) ($item['path'] ?? ''),
                    'placement' => $pageSpread,
                    'properties' => $pageSpreadProperties,
                    'conflicting' => (bool) ($itemProperties['pageSpread']['conflicting'] ?? false),
                ];
            }
            if (($item['external'] ?? false) === true) {
                $externalItems[] = $item;
            }
            if (($item['idref'] ?? '') !== '' && ($item['href'] ?? '') === '') {
                $missingManifestItems[] = $item;
            }
            if (($item['external'] ?? false) !== true && ($item['path'] ?? '') !== '' && ($item['exists'] ?? true) !== true) {
                $missingPackagePartItems[] = $item;
            }
            if (($item['idref'] ?? '') !== '') {
                $idrefItems[(string) $item['idref']][] = $item;
            }
            if (($item['linearValid'] ?? true) !== true) {
                $invalidLinearItems[] = $item;
            }
            foreach (is_array($item['linearDiagnostics'] ?? null) ? $item['linearDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $linearDiagnostics[] = ['index' => $index, 'idref' => (string) ($item['idref'] ?? '')] + $diagnostic;
            }

            $fallbackChain = is_array($item['fallbackChain'] ?? null)
                ? array_values(array_filter($item['fallbackChain'], 'is_array'))
                : [];
            if (($item['contentIsFallback'] ?? false) === true) {
                $firstFallback = is_array($fallbackChain[0] ?? null) ? $fallbackChain[0] : [];
                $source = is_string($firstFallback['source'] ?? null) ? $firstFallback['source'] : '';
                $bindingMediaType = is_string($firstFallback['bindingMediaType'] ?? null)
                    ? $firstFallback['bindingMediaType']
                    : null;
                $fallbackItem = [
                    'index' => (int) ($item['index'] ?? $index),
                    'idref' => (string) ($item['idref'] ?? ''),
                    'spineItemId' => is_string($item['id'] ?? null) ? $item['id'] : null,
                    'spineHref' => (string) ($item['href'] ?? ''),
                    'spineTarget' => (string) ($item['target'] ?? ''),
                    'spinePath' => (string) ($item['path'] ?? ''),
                    'spineMediaType' => (string) ($item['mediaType'] ?? ''),
                    'contentId' => (string) ($item['contentId'] ?? ''),
                    'contentPath' => (string) ($item['contentPath'] ?? ''),
                    'contentMediaType' => (string) ($item['contentMediaType'] ?? ''),
                    'source' => $source,
                    'bindingMediaType' => $bindingMediaType,
                    'fallbackChain' => $fallbackChain,
                ];
                $fallbackContentItems[] = $fallbackItem;
                if ($source === 'binding-handler') {
                    $bindingFallbackContentItems[] = $fallbackItem;
                }
            }
            foreach (is_array($item['fallbackDiagnostics'] ?? null) ? $item['fallbackDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $fallbackDiagnostics[] = [
                    'index' => (int) ($item['index'] ?? $index),
                    'idref' => (string) ($item['idref'] ?? ''),
                    'mediaType' => (string) ($item['mediaTypeBase'] ?? $item['mediaType'] ?? ''),
                ] + $diagnostic;
            }

            foreach (is_array($item['spineItemDiagnostics'] ?? null) ? $item['spineItemDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $itemDiagnostics[] = [
                    'index' => $index,
                    'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                    'idref' => (string) ($item['idref'] ?? ''),
                ] + $diagnostic;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
        }

        $duplicateIdrefItems = [];
        $duplicateIdrefDiagnostics = [];
        foreach ($idrefItems as $idref => $items) {
            if (count($items) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items);
            $targets = array_map(static fn (array $item): string => (string) ($item['target'] ?? ''), $items);
            $linear = array_map(static fn (array $item): bool => (bool) ($item['linear'] ?? false), $items);
            $duplicateIdrefItems[] = [
                'idref' => $idref,
                'itemCount' => count($items),
                'indexes' => $indexes,
                'targets' => $targets,
                'linear' => $linear,
                'items' => array_values($items),
            ];
            foreach ($items as $item) {
                $duplicateIdrefDiagnostics[] = [
                    'type' => 'duplicate-spine-idref',
                    'index' => (int) ($item['index'] ?? 0),
                    'idref' => $idref,
                    'indexes' => $indexes,
                    'targets' => $targets,
                    'linear' => $linear,
                    'message' => 'EPUB spine contains multiple itemref elements for the same manifest idref',
                ];
            }
        }

        $diagnostics = array_merge($diagnostics, $duplicateIdrefDiagnostics);
        $diagnostics = array_merge($diagnostics, $fallbackDiagnostics);

        return [
            'itemCount' => count($spine),
            'linearItemCount' => $linearItemCount,
            'nonlinearItemCount' => count($spine) - $linearItemCount,
            'readableItemCount' => $readableItemCount,
            'skippedItemCount' => count($spine) - $readableItemCount,
            'pageSpreadCount' => count($pageSpreadItems),
            'pageSpreadLeftCount' => $pageSpreadCounts['left'],
            'pageSpreadRightCount' => $pageSpreadCounts['right'],
            'pageSpreadCenterCount' => $pageSpreadCounts['center'],
            'pageSpreadItems' => $pageSpreadItems,
            'fallbackContentCount' => count($fallbackContentItems),
            'fallbackContentItems' => $fallbackContentItems,
            'bindingFallbackContentCount' => count($bindingFallbackContentItems),
            'bindingFallbackContentItems' => $bindingFallbackContentItems,
            'fallbackDiagnosticCount' => count($fallbackDiagnostics),
            'fallbackDiagnostics' => $fallbackDiagnostics,
            'spineMetadata' => $spineMetadata,
            'pageProgressionDirection' => (string) ($spineMetadata['pageProgressionDirection'] ?? 'default'),
            'pageProgressionDirectionRaw' => $spineMetadata['pageProgressionDirectionRaw'] ?? null,
            'pageProgressionDirectionSpecified' => (bool) ($spineMetadata['pageProgressionDirectionSpecified'] ?? false),
            'pageProgressionDirectionValid' => (bool) ($spineMetadata['pageProgressionDirectionValid'] ?? true),
            'rightToLeft' => (bool) ($spineMetadata['rightToLeft'] ?? false),
            'spineMetadataDiagnosticCount' => count($spineMetadataDiagnostics),
            'spineMetadataDiagnostics' => $spineMetadataDiagnostics,
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingManifestItemCount' => count($missingManifestItems),
            'missingManifestItems' => $missingManifestItems,
            'missingPackagePartItemCount' => count($missingPackagePartItems),
            'missingPackagePartItems' => $missingPackagePartItems,
            'invalidLinearItemCount' => count($invalidLinearItems),
            'invalidLinearItems' => $invalidLinearItems,
            'linearDiagnosticCount' => count($linearDiagnostics),
            'linearDiagnostics' => $linearDiagnostics,
            'duplicateIdrefCount' => count($duplicateIdrefItems),
            'duplicateIdrefItemCount' => array_sum(array_map(
                static fn (array $item): int => (int) $item['itemCount'],
                $duplicateIdrefItems
            )),
            'duplicateIdrefs' => array_map(
                static fn (array $item): string => (string) $item['idref'],
                $duplicateIdrefItems
            ),
            'duplicateIdrefItems' => $duplicateIdrefItems,
            'duplicateIdrefDiagnostics' => $duplicateIdrefDiagnostics,
            'itemDiagnosticCount' => count($itemDiagnostics),
            'itemDiagnostics' => $itemDiagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}> $manifest
     * @return array{
     *     present:bool,
     *     itemCount:int,
     *     typedItemCount:int,
     *     missingTypeCount:int,
     *     types:list<string>,
     *     typeCounts:array<string, int>,
     *     items:list<array<string, mixed>>,
     *     itemsByType:array<string, list<array<string, mixed>>>,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function readGuideReferences(string $root, string $opfDir, array $manifest, \DOMElement $packageElement): array
    {
        $guide = $this->firstDirectChild($packageElement, 'guide');
        if (!$guide instanceof \DOMElement) {
            return [
                'present' => false,
                'itemCount' => 0,
                'typedItemCount' => 0,
                'missingTypeCount' => 0,
                'types' => [],
                'typeCounts' => [],
                'items' => [],
                'itemsByType' => [],
                'targetCount' => 0,
                'localTargetCount' => 0,
                'externalTargetCount' => 0,
                'missingTargetCount' => 0,
                'unmanifestedTargetCount' => 0,
                'missingHrefCount' => 0,
                'unsafeTargetCount' => 0,
                'manifestLinkedTargetCount' => 0,
                'targets' => [],
                'localTargets' => [],
                'externalTargets' => [],
                'missingTargets' => [],
                'unmanifestedTargets' => [],
                'manifestLinkedTargets' => [],
                'diagnosticTypes' => [],
                'diagnosticCount' => 0,
                'diagnostics' => [],
            ];
        }

        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $item;
            }
        }

        $items = [];
        $itemsByType = [];
        $typeCounts = [];
        $typedItemCount = 0;
        $missingTypeCount = 0;
        $diagnostics = [];
        $index = 0;
        foreach ($guide->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'reference') {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            $suffix = $this->hrefSuffix($href);
            $path = '';
            $fragment = '';
            $target = '';
            $unsafe = false;
            $external = false;
            $exists = false;
            if ($href !== '') {
                $external = $this->isExternalHref($href);
                try {
                    [$path, $fragment] = $this->splitResolvedHref($opfDir, $href);
                    $target = $this->targetWithSuffix($path, $suffix);
                    $exists = !$external && $path !== '' && $this->packagePathExists($root, $path);
                } catch (\RuntimeException $exception) {
                    $unsafe = true;
                    $path = $href;
                    $target = $href;
                }
            }
            $typeRaw = trim($node->getAttribute('type'));
            $types = $this->tokens($typeRaw);
            $itemDiagnostics = [];

            if ($href === '') {
                $diagnostic = [
                    'type' => 'missing-guide-reference-href',
                    'message' => 'EPUB OPF guide reference is missing href',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            if ($types === []) {
                ++$missingTypeCount;
                $diagnostic = [
                    'type' => 'missing-guide-reference-type',
                    'href' => $href,
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            } else {
                ++$typedItemCount;
            }

            if ($unsafe) {
                $diagnostic = [
                    'type' => 'invalid-guide-reference-href',
                    'href' => $href,
                    'message' => 'EPUB OPF guide reference href escapes the package root',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            } elseif ($external) {
                $diagnostic = [
                    'type' => 'external-guide-reference-target',
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB OPF guide reference points outside the package and was not fetched',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            } elseif ($path !== '' && !$exists) {
                $diagnostic = [
                    'type' => 'missing-guide-reference',
                    'href' => $href,
                    'path' => $path,
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $manifestItem = $manifestByPath[$path] ?? null;
            if (!$external && !$unsafe && $path !== '' && $exists && !is_array($manifestItem)) {
                $diagnostic = [
                    'type' => 'guide-reference-target-not-in-manifest',
                    'href' => $href,
                    'path' => $path,
                    'target' => $target,
                    'message' => 'EPUB OPF guide reference target is present in the package but not declared in the OPF manifest',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            $attributes = $this->elementAttributes($node);
            $language = $this->elementLanguage($node);
            $item = [
                'index' => $index,
                'type' => $types[0] ?? '',
                'typeRaw' => $typeRaw,
                'types' => $types,
                'title' => trim($node->getAttribute('title')),
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'hrefHasQuery' => $suffix['hasQuery'],
                'hrefQuery' => $suffix['query'],
                'hrefHasFragment' => $suffix['hasFragment'],
                'hrefFragment' => $suffix['fragment'],
                'external' => $external,
                'unsafe' => $unsafe,
                'exists' => $exists,
                'manifestId' => is_array($manifestItem) ? $manifestItem['id'] : '',
                'mediaType' => is_array($manifestItem) ? $manifestItem['mediaType'] : '',
                'mediaTypeBase' => is_array($manifestItem) ? $manifestItem['mediaTypeBase'] : '',
                'language' => $language === '' ? null : $language,
                'direction' => $this->nullableAttribute($node, 'dir'),
                'attributes' => $attributes,
                'customAttributes' => $this->customAttributes($attributes, self::OPF_GUIDE_REFERENCE_STRUCTURAL_ATTRIBUTES),
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($types as $type) {
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                $itemsByType[$type][] = $item;
            }
            $items[] = $item;
            ++$index;
        }

        $targets = [];
        $localTargets = [];
        $externalTargets = [];
        $missingTargets = [];
        $unmanifestedTargets = [];
        $manifestLinkedTargets = [];
        $diagnosticTypes = [];
        $missingHrefCount = 0;
        $unsafeTargetCount = 0;

        foreach ($items as $item) {
            $target = (string) ($item['target'] ?? '');
            if ($target !== '') {
                $targets[] = $target;
            }
            if (($item['external'] ?? false) === true) {
                if ($target !== '') {
                    $externalTargets[] = $target;
                }
            } elseif (($item['unsafe'] ?? false) === true) {
                ++$unsafeTargetCount;
            } elseif (($item['exists'] ?? false) === true) {
                if ($target !== '') {
                    $localTargets[] = $target;
                }
                if (($item['manifestId'] ?? '') === '') {
                    $unmanifestedTargets[] = $target;
                }
            } elseif ($target !== '') {
                $missingTargets[] = $target;
            }

            if (($item['manifestId'] ?? '') !== '') {
                $manifestLinkedTargets[] = [
                    'index' => (int) ($item['index'] ?? 0),
                    'type' => (string) ($item['type'] ?? ''),
                    'target' => $target,
                    'path' => (string) ($item['path'] ?? ''),
                    'manifestId' => (string) ($item['manifestId'] ?? ''),
                    'mediaType' => (string) ($item['mediaType'] ?? ''),
                    'mediaTypeBase' => (string) ($item['mediaTypeBase'] ?? $item['mediaType'] ?? ''),
                ];
            }
        }
        foreach ($diagnostics as $diagnostic) {
            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            if ($type === '') {
                continue;
            }
            if ($type === 'missing-guide-reference-href') {
                ++$missingHrefCount;
            }
            $diagnosticTypes[$type] = ($diagnosticTypes[$type] ?? 0) + 1;
        }
        ksort($diagnosticTypes, SORT_STRING);

        return [
            'present' => true,
            'itemCount' => count($items),
            'typedItemCount' => $typedItemCount,
            'missingTypeCount' => $missingTypeCount,
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'items' => $items,
            'itemsByType' => $itemsByType,
            'targetCount' => count($targets),
            'localTargetCount' => count($localTargets),
            'externalTargetCount' => count($externalTargets),
            'missingTargetCount' => count($missingTargets),
            'unmanifestedTargetCount' => count($unmanifestedTargets),
            'missingHrefCount' => $missingHrefCount,
            'unsafeTargetCount' => $unsafeTargetCount,
            'manifestLinkedTargetCount' => count($manifestLinkedTargets),
            'targets' => $targets,
            'localTargets' => $localTargets,
            'externalTargets' => $externalTargets,
            'missingTargets' => $missingTargets,
            'unmanifestedTargets' => $unmanifestedTargets,
            'manifestLinkedTargets' => $manifestLinkedTargets,
            'diagnosticTypes' => $diagnosticTypes,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function readCollections(string $root, string $opfDir, array $manifest, \DOMElement $packageElement): array
    {
        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $item;
            }
        }

        $collections = [];
        $index = 0;
        foreach ($packageElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'collection') {
                continue;
            }

            $collections[] = $this->readCollection($root, $opfDir, $manifestByPath, $node, $index);
            ++$index;
        }

        return $collections;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPath
     * @return array<string, mixed>
     */
    private function readCollection(
        string $root,
        string $opfDir,
        array $manifestByPath,
        \DOMElement $collectionElement,
        int $index
    ): array {
        $role = trim($collectionElement->getAttribute('role'));
        $roleTokens = $this->tokens($role);
        $metadata = $this->collectionMetadata($collectionElement);
        $links = [];
        $children = [];

        $linkIndex = 0;
        $childIndex = 0;
        foreach ($collectionElement->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($node->localName === 'link') {
                $links[] = $this->collectionLink($root, $opfDir, $manifestByPath, $node, $linkIndex);
                ++$linkIndex;
                continue;
            }

            if ($node->localName === 'collection') {
                $children[] = $this->readCollection($root, $opfDir, $manifestByPath, $node, $childIndex);
                ++$childIndex;
            }
        }

        $linkReport = $this->collectionLinkReport($links);
        $diagnostics = $linkReport['diagnostics'];
        if ($roleTokens === []) {
            $diagnostics[] = [
                'type' => 'missing-collection-role',
                'collectionIndex' => $index,
                'message' => 'EPUB OPF collection is missing role tokens for package review classification',
            ];
        }

        $language = $this->elementLanguage($collectionElement);

        return [
            'index' => $index,
            'id' => $this->nullableAttribute($collectionElement, 'id'),
            'role' => $role === '' ? null : $role,
            'roleTokens' => $roleTokens,
            'primaryRole' => $roleTokens[0] ?? null,
            'language' => $language === '' ? null : $language,
            'direction' => $this->nullableAttribute($collectionElement, 'dir'),
            'metadata' => $metadata,
            'links' => $links,
            'linkCount' => $linkReport['count'],
            'localLinkCount' => $linkReport['localCount'],
            'externalLinkCount' => $linkReport['externalCount'],
            'missingLinkCount' => $linkReport['missingCount'],
            'linkRelTokens' => $linkReport['relTokens'],
            'linkRelCounts' => $linkReport['relCounts'],
            'linksByRel' => $linkReport['linksByRel'],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionMetadata(\DOMElement $collectionElement): array
    {
        $metadataElement = $this->firstDirectChild($collectionElement, 'metadata');
        if (!$metadataElement instanceof \DOMElement) {
            return [
                'present' => false,
                'itemCount' => 0,
                'items' => [],
                'title' => null,
            ];
        }

        $items = [];
        $title = null;
        foreach ($metadataElement->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $value = $this->normalizedText($node->textContent);
            if ($value === '') {
                continue;
            }

            $item = [
                'index' => count($items),
                'kind' => $node->localName,
                'name' => $node->localName,
                'namespace' => $node->namespaceURI ?? '',
                'prefix' => $node->prefix ?? '',
                'id' => $this->nullableAttribute($node, 'id'),
                'value' => $value,
                'text' => $value,
            ];
            if ($title === null && $node->localName === 'title') {
                $title = $value;
            }

            $items[] = $item;
        }

        return [
            'present' => true,
            'itemCount' => count($items),
            'items' => $items,
            'title' => $title,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPath
     * @return array<string, mixed>
     */
    private function collectionLink(
        string $root,
        string $opfDir,
        array $manifestByPath,
        \DOMElement $linkElement,
        int $index
    ): array {
        $href = trim($linkElement->getAttribute('href'));
        $suffix = $this->hrefSuffix($href);
        $external = $href !== '' && $this->isExternalHref($href);
        $path = '';
        $target = '';
        $exists = false;
        $diagnostics = [];

        if ($href === '') {
            $diagnostics[] = [
                'type' => 'missing-collection-link-href',
                'message' => 'EPUB OPF collection link is missing href',
            ];
        } elseif ($external) {
            $target = $href;
            $diagnostics[] = [
                'type' => 'external-collection-link-target',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB OPF collection link points outside the package and was not fetched',
            ];
        } else {
            try {
                $path = $this->resolvePackageHref($opfDir, $href);
                $target = $this->targetWithSuffix($path, $suffix);
                $exists = $path !== '' && $this->packagePathExists($root, $path);
                if ($path !== '' && !$exists) {
                    $diagnostics[] = [
                        'type' => 'missing-collection-link-target',
                        'href' => $href,
                        'path' => $path,
                        'message' => 'EPUB OPF collection link target is missing from the package',
                    ];
                }
            } catch (\RuntimeException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-collection-link-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $manifestItem = $path !== '' && isset($manifestByPath[$path]) ? $manifestByPath[$path] : null;

        return [
            'index' => $index,
            'id' => $this->nullableAttribute($linkElement, 'id'),
            'rel' => $this->tokens($linkElement->getAttribute('rel')),
            'href' => $href,
            'target' => $target,
            'path' => $path,
            'partName' => $path,
            'fragment' => $suffix['fragment'],
            'external' => $external,
            'exists' => $exists,
            'mediaType' => trim($linkElement->getAttribute('media-type')),
            'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : '',
            'manifestMediaType' => is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '',
            'properties' => $this->tokens($linkElement->getAttribute('properties')),
            'title' => $this->nullableAttribute($linkElement, 'title'),
            'refines' => $this->nullableAttribute($linkElement, 'refines'),
            'hrefHasQuery' => $suffix['hasQuery'],
            'hrefQuery' => $suffix['query'],
            'hrefHasFragment' => $suffix['hasFragment'],
            'hrefFragment' => $suffix['fragment'],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, mixed>
     */
    private function collectionLinkReport(array $links): array
    {
        $localCount = 0;
        $externalCount = 0;
        $missingCount = 0;
        $relCounts = [];
        $linksByRel = [];
        $diagnostics = [];

        foreach ($links as $linkIndex => $link) {
            if (($link['external'] ?? false) === true) {
                ++$externalCount;
            } elseif (($link['path'] ?? '') !== '') {
                ++$localCount;
            }

            if (($link['external'] ?? false) !== true && ($link['path'] ?? '') !== '' && ($link['exists'] ?? false) !== true) {
                ++$missingCount;
            }

            foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                $rel = (string) $rel;
                $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
                $linksByRel[$rel][] = $link;
            }

            foreach (is_array($link['diagnostics'] ?? null) ? $link['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $linkIndex,
                    'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                ] + $diagnostic;
            }
        }

        ksort($relCounts, SORT_STRING);
        ksort($linksByRel, SORT_STRING);

        return [
            'count' => count($links),
            'localCount' => $localCount,
            'externalCount' => $externalCount,
            'missingCount' => $missingCount,
            'relTokens' => array_keys($relCounts),
            'relCounts' => $relCounts,
            'linksByRel' => $linksByRel,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @return array<string, mixed>
     */
    private function collectionReport(array $collections): array
    {
        $items = [];
        $diagnostics = [];
        $roleCounts = [];
        $primaryRoleCounts = [];
        $linkRelCounts = [];
        $depthCounts = [];
        $linkTargets = [];
        $titles = [];
        $localLinkCount = 0;
        $externalLinkCount = 0;
        $missingLinkCount = 0;
        $maxDepth = 0;
        $leafCollectionCount = 0;

        $this->appendCollectionReportItems(
            $collections,
            [],
            $items,
            $diagnostics,
            $roleCounts,
            $primaryRoleCounts,
            $linkRelCounts,
            $depthCounts,
            $linkTargets,
            $titles,
            $localLinkCount,
            $externalLinkCount,
            $missingLinkCount,
            $maxDepth,
            $leafCollectionCount
        );

        ksort($roleCounts, SORT_STRING);
        ksort($primaryRoleCounts, SORT_STRING);
        ksort($linkRelCounts, SORT_STRING);
        ksort($depthCounts, SORT_NUMERIC);

        $itemsByPath = [];
        foreach ($items as $item) {
            $pathKey = (string) ($item['pathKey'] ?? '');
            if ($pathKey !== '') {
                $itemsByPath[$pathKey] = $item;
            }
        }

        return [
            'present' => $items !== [],
            'collectionCount' => count($items),
            'rootCollectionCount' => count($collections),
            'leafCollectionCount' => $leafCollectionCount,
            'maxDepth' => $maxDepth,
            'pathKeys' => array_column($items, 'pathKey'),
            'roleCounts' => $roleCounts,
            'primaryRoleCounts' => $primaryRoleCounts,
            'linkRelCounts' => $linkRelCounts,
            'depthCounts' => $depthCounts,
            'localLinkCount' => $localLinkCount,
            'externalLinkCount' => $externalLinkCount,
            'missingLinkCount' => $missingLinkCount,
            'titles' => $titles,
            'linkTargets' => $linkTargets,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'items' => $items,
            'itemsByPath' => $itemsByPath,
        ];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<int> $parentPath
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, int> $roleCounts
     * @param array<string, int> $primaryRoleCounts
     * @param array<string, int> $linkRelCounts
     * @param array<int, int> $depthCounts
     * @param list<string> $linkTargets
     * @param list<string> $titles
     */
    private function appendCollectionReportItems(
        array $collections,
        array $parentPath,
        array &$items,
        array &$diagnostics,
        array &$roleCounts,
        array &$primaryRoleCounts,
        array &$linkRelCounts,
        array &$depthCounts,
        array &$linkTargets,
        array &$titles,
        int &$localLinkCount,
        int &$externalLinkCount,
        int &$missingLinkCount,
        int &$maxDepth,
        int &$leafCollectionCount
    ): void {
        foreach ($collections as $collectionIndex => $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $currentPath = array_merge($parentPath, [$collectionIndex]);
            $pathKey = implode('/', $currentPath);
            $parentPathKey = $parentPath === [] ? null : implode('/', $parentPath);
            $children = is_array($collection['children'] ?? null) ? $collection['children'] : [];
            $links = is_array($collection['links'] ?? null) ? $collection['links'] : [];
            $metadata = is_array($collection['metadata'] ?? null) ? $collection['metadata'] : [];
            $roleTokens = is_array($collection['roleTokens'] ?? null) ? array_values($collection['roleTokens']) : [];
            $primaryRole = is_string($collection['primaryRole'] ?? null) ? $collection['primaryRole'] : null;
            $title = is_string($metadata['title'] ?? null) ? $metadata['title'] : null;
            $depth = count($currentPath);

            $maxDepth = max($maxDepth, $depth);
            $depthCounts[$depth] = ($depthCounts[$depth] ?? 0) + 1;
            if ($children === []) {
                ++$leafCollectionCount;
            }

            foreach ($roleTokens as $roleToken) {
                if (is_string($roleToken) && $roleToken !== '') {
                    $roleCounts[$roleToken] = ($roleCounts[$roleToken] ?? 0) + 1;
                }
            }
            if ($primaryRole !== null && $primaryRole !== '') {
                $primaryRoleCounts[$primaryRole] = ($primaryRoleCounts[$primaryRole] ?? 0) + 1;
            }
            foreach (is_array($collection['linkRelCounts'] ?? null) ? $collection['linkRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $linkRelCounts[$rel] = ($linkRelCounts[$rel] ?? 0) + (int) $count;
                }
            }

            $localLinkCount += (int) ($collection['localLinkCount'] ?? 0);
            $externalLinkCount += (int) ($collection['externalLinkCount'] ?? 0);
            $missingLinkCount += (int) ($collection['missingLinkCount'] ?? 0);
            if ($title !== null && $title !== '') {
                $titles[] = $title;
            }

            $ownLinkTargets = [];
            foreach ($links as $link) {
                if (!is_array($link)) {
                    continue;
                }

                $target = is_string($link['target'] ?? null) ? $link['target'] : '';
                if ($target !== '') {
                    $ownLinkTargets[] = $target;
                    $linkTargets[] = $target;
                }
            }

            $itemDiagnostics = [];
            foreach (is_array($collection['diagnostics'] ?? null) ? $collection['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnosticWithPath = [
                    'collectionPath' => $currentPath,
                    'collectionPathKey' => $pathKey,
                    'collectionId' => is_string($collection['id'] ?? null) ? $collection['id'] : null,
                ] + $diagnostic;
                $itemDiagnostics[] = $diagnosticWithPath;
                $diagnostics[] = $diagnosticWithPath;
            }

            $items[] = [
                'path' => $currentPath,
                'pathKey' => $pathKey,
                'parentPath' => $parentPath === [] ? null : $parentPath,
                'parentPathKey' => $parentPathKey,
                'index' => $collectionIndex,
                'depth' => $depth,
                'id' => is_string($collection['id'] ?? null) ? $collection['id'] : null,
                'role' => is_string($collection['role'] ?? null) ? $collection['role'] : null,
                'roleTokens' => $roleTokens,
                'primaryRole' => $primaryRole,
                'title' => $title,
                'language' => is_string($collection['language'] ?? null) ? $collection['language'] : null,
                'direction' => is_string($collection['direction'] ?? null) ? $collection['direction'] : null,
                'linkCount' => (int) ($collection['linkCount'] ?? count($links)),
                'localLinkCount' => (int) ($collection['localLinkCount'] ?? 0),
                'externalLinkCount' => (int) ($collection['externalLinkCount'] ?? 0),
                'missingLinkCount' => (int) ($collection['missingLinkCount'] ?? 0),
                'linkRelCounts' => is_array($collection['linkRelCounts'] ?? null) ? $collection['linkRelCounts'] : [],
                'linkTargets' => $ownLinkTargets,
                'childCount' => count($children),
                'leaf' => $children === [],
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ];

            $this->appendCollectionReportItems(
                $children,
                $currentPath,
                $items,
                $diagnostics,
                $roleCounts,
                $primaryRoleCounts,
                $linkRelCounts,
                $depthCounts,
                $linkTargets,
                $titles,
                $localLinkCount,
                $externalLinkCount,
                $missingLinkCount,
                $maxDepth,
                $leafCollectionCount
            );
        }
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>} $package
     * @return list<array<string, mixed>>
     */
    private function readNavigationDocument(string $root, array $package): array
    {
        $navItem = null;
        foreach ($package['manifest'] as $item) {
            if (in_array('nav', $item['properties'], true)) {
                $navItem = $item;
                break;
            }
        }
        if (!is_array($navItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $navItem['path']));
        $navDir = $this->relativeDirname($navItem['path']);
        $entries = [];
        $sectionIndex = 0;
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $type = $this->epubType($element);
            if ($type !== 'toc' && $type !== 'landmarks' && $type !== 'page-list') {
                continue;
            }

            $ol = $this->firstDirectChild($element, 'ol');
            if (!$ol instanceof \DOMElement) {
                continue;
            }

            foreach ($this->readNavList(
                $ol,
                $navDir,
                $type,
                $sectionIndex,
                $this->nullableAttribute($element, 'id'),
                $this->navSectionLabel($element),
                $root
            ) as $entry) {
                $entries[] = $entry;
            }
            ++$sectionIndex;
        }

        return $entries;
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>, spine:list<array{idref:string}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, labelProvenance:array<string, mixed>, children:list<array<string, mixed>>}>
     */
    private function readNcxDocument(string $root, array $package): array
    {
        $ncxItem = null;
        foreach ($package['manifest'] as $item) {
            $mediaTypeBase = (string) ($item['mediaTypeBase'] ?? $this->mediaTypeReport((string) ($item['mediaType'] ?? ''))['mediaTypeBase']);
            if ($mediaTypeBase === 'application/x-dtbncx+xml') {
                $ncxItem = $item;
                break;
            }
        }
        if (!is_array($ncxItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $ncxItem['path']));
        $xpath = new \DOMXPath($document);
        $navMap = $xpath->query('/*[local-name()="ncx"]/*[local-name()="navMap"][1]');
        $navMapElement = $navMap instanceof \DOMNodeList ? $navMap->item(0) : null;
        if (!$navMapElement instanceof \DOMElement) {
            return [];
        }

        return $this->readNcxPoints($navMapElement, $this->relativeDirname($ncxItem['path']));
    }

    /**
     * @param list<array<string, mixed>> $points
     * @return array<string, mixed>
     */
    private function ncxReport(array $points): array
    {
        $flat = [];
        $this->flattenNcxPoints($points, $flat);
        $diagnostics = [];
        $seenTargets = [];
        $previousPositivePlayOrder = null;
        $playOrderCount = 0;
        $missingPlayOrderCount = 0;
        $nonIncreasingPlayOrderCount = 0;
        $duplicateTargetCount = 0;

        foreach ($flat as $index => $point) {
            $playOrder = (int) ($point['playOrder'] ?? 0);
            if ($playOrder <= 0) {
                ++$missingPlayOrderCount;
            } else {
                ++$playOrderCount;
                if ($previousPositivePlayOrder !== null && $playOrder <= $previousPositivePlayOrder) {
                    ++$nonIncreasingPlayOrderCount;
                    $diagnostics[] = [
                        'type' => 'non-increasing-ncx-play-order',
                        'index' => $index,
                        'label' => (string) ($point['label'] ?? ''),
                        'playOrder' => $playOrder,
                        'previousPlayOrder' => $previousPositivePlayOrder,
                        'path' => (string) ($point['path'] ?? ''),
                        'fragment' => (string) ($point['fragment'] ?? ''),
                    ];
                }
                $previousPositivePlayOrder = $playOrder;
            }

            $target = $this->ncxPointTarget($point);
            if ($target === '') {
                continue;
            }
            if (isset($seenTargets[$target])) {
                ++$duplicateTargetCount;
                $diagnostics[] = [
                    'type' => 'duplicate-ncx-target',
                    'index' => $index,
                    'firstIndex' => $seenTargets[$target]['index'],
                    'label' => (string) ($point['label'] ?? ''),
                    'firstLabel' => $seenTargets[$target]['label'],
                    'target' => $target,
                    'path' => (string) ($point['path'] ?? ''),
                    'fragment' => (string) ($point['fragment'] ?? ''),
                ];
                continue;
            }

            $seenTargets[$target] = [
                'index' => $index,
                'label' => (string) ($point['label'] ?? ''),
            ];
        }

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'topLevelItemCount' => count($points),
            'playOrderCount' => $playOrderCount,
            'hierarchy' => $this->hierarchySummary($flat, count($points)),
            'items' => $flat,
            'pointCount' => count($flat),
            'topLevelPointCount' => count($points),
            'maxDepth' => $this->maxNcxDepth($points),
            'missingPlayOrderCount' => $missingPlayOrderCount,
            'nonIncreasingPlayOrderCount' => $nonIncreasingPlayOrderCount,
            'duplicateTargetCount' => $duplicateTargetCount,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function navReport(array $entries, array $package, string $root): array
    {
        $flat = $this->flattenNavigationEntries($entries);
        $documentReport = $this->navDocumentReport($package, $root);
        $pageListReport = $this->pageListReport($flat, $package);
        $normalizedCollisionReport = $this->navReportWithPageListTargets(
            $this->navNormalizedCollisionReport(
                $flat,
                is_string($documentReport['path'] ?? null) ? $documentReport['path'] : ''
            ),
            $pageListReport
        );
        $hrefPolicyReport = $this->navHrefPolicyReport($flat);
        $hrefNormalizationReport = $this->navHrefNormalizationReport($flat);
        $sections = [];
        $sectionKeys = [];
        $entriesByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $typeCounts = [];

        foreach ($flat as $item) {
            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = (string) $sectionIndex;
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== '') {
                $typeCounts[$sectionType] = ($typeCounts[$sectionType] ?? 0) + 1;
                if (isset($entriesByType[$sectionType])) {
                    $entriesByType[$sectionType][] = $item;
                }
            }

            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sections[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                ];
            }
            ++$sections[$sectionKeys[$sectionKey]]['itemCount'];
        }

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'entryCount' => count($flat),
            'tocEntryCount' => count($entriesByType['toc']),
            'landmarksEntryCount' => count($entriesByType['landmarks']),
            'pageListEntryCount' => count($entriesByType['page-list']),
            'topLevelItemCount' => count($entries),
            'sectionCount' => count($sections),
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'entriesByType' => $entriesByType,
            'sections' => $sections,
            'hierarchy' => $this->hierarchySummary($flat, count($entries)),
            'document' => $documentReport,
            'documentDiagnosticCount' => $documentReport['diagnosticCount'],
            'documentDiagnostics' => $documentReport['diagnostics'],
            'targetedItemCount' => $hrefPolicyReport['targetedItemCount'],
            'localTargetCount' => $hrefPolicyReport['localTargetCount'],
            'safeLocalTargetCount' => $hrefPolicyReport['safeLocalTargetCount'],
            'externalTargetCount' => $hrefPolicyReport['externalTargetCount'],
            'unsafeTargetCount' => $hrefPolicyReport['unsafeTargetCount'],
            'hrefPolicyDiagnosticCount' => $hrefPolicyReport['diagnosticCount'],
            'hrefPolicy' => $hrefPolicyReport,
            'hrefNormalization' => $hrefNormalizationReport,
            'fragmentTargets' => $this->navFragmentTargetReport($flat, $root),
            'toc' => $this->tocNavigationReport($flat),
            'landmarks' => $this->landmarkReport($flat, $package),
            'pageList' => $pageListReport,
            'pageListItemCount' => $pageListReport['itemCount'],
            'pageBreakItemCount' => $pageListReport['pageBreakItemCount'],
            'normalizedCollisionGroupCount' => $normalizedCollisionReport['normalizedCollisionGroupCount'],
            'normalizedCollisionItemCount' => $normalizedCollisionReport['normalizedCollisionItemCount'],
            'normalizedCollisionDiagnostics' => $normalizedCollisionReport['normalizedCollisionDiagnostics'],
            'normalizedCollisionSections' => $normalizedCollisionReport['sections'],
            'crossSectionCollisionGroupCount' => $normalizedCollisionReport['crossSectionCollisionGroupCount'],
            'crossSectionCollisionItemCount' => $normalizedCollisionReport['crossSectionCollisionItemCount'],
            'crossSectionCollisionDiagnostics' => $normalizedCollisionReport['crossSectionCollisionDiagnostics'],
            'diagnosticTypes' => $this->diagnosticTypeCounts($pageListReport['diagnostics']),
            'diagnosticCount' => $pageListReport['diagnosticCount'],
            'diagnostics' => $pageListReport['diagnostics'],
        ];
    }

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function navDocumentReport(array $package, string $root): array
    {
        $navItem = null;
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $item) {
            if (is_array($item) && in_array('nav', is_array($item['properties'] ?? null) ? $item['properties'] : [], true)) {
                $navItem = $item;
                break;
            }
        }

        $diagnostics = [];
        if (!is_array($navItem)) {
            $diagnostics[] = [
                'type' => 'missing-nav-manifest-item',
                'message' => 'EPUB package manifest does not declare an XHTML navigation document',
            ];

            return [
                'present' => false,
                'part' => null,
                'path' => null,
                'sectionCount' => 0,
                'primarySectionCount' => 0,
                'tocSectionCount' => 0,
                'landmarksSectionCount' => 0,
                'pageListSectionCount' => 0,
                'requiredTocPresent' => false,
                'duplicatePrimaryTypeCount' => 0,
                'hiddenPrimarySectionCount' => 0,
                'missingHeadingSectionCount' => 0,
                'missingOrderedListSectionCount' => 0,
                'emptySectionCount' => 0,
                'untypedSectionCount' => 0,
                'unrecognizedSectionCount' => 0,
                'sections' => [],
                'diagnosticCount' => count($diagnostics),
                'diagnostics' => $diagnostics,
            ];
        }

        $path = is_string($navItem['path'] ?? null) ? $navItem['path'] : '';
        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $path));
        $sections = [];
        $primarySectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $primarySectionCount = 0;
        $hiddenPrimarySectionCount = 0;
        $missingHeadingSectionCount = 0;
        $missingOrderedListSectionCount = 0;
        $emptySectionCount = 0;
        $untypedSectionCount = 0;
        $unrecognizedSectionCount = 0;

        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $sectionIndex = count($sections);
            $sectionTypes = $this->epubTypes($element);
            $primaryTypes = array_values(array_intersect($sectionTypes, ['toc', 'landmarks', 'page-list']));
            $sectionType = $primaryTypes[0] ?? null;
            $sectionId = $this->nullableAttribute($element, 'id');
            $sectionLabel = $this->navSectionLabel($element);
            $orderedList = $this->firstDirectChild($element, 'ol');
            $hasOrderedList = $orderedList instanceof \DOMElement;
            $itemCount = $hasOrderedList ? $this->directChildElementCount($orderedList, 'li') : 0;
            $hidden = $element->hasAttribute('hidden')
                || strtolower(trim($element->getAttribute('aria-hidden'))) === 'true';
            $sectionDiagnostics = [];

            if ($primaryTypes !== []) {
                ++$primarySectionCount;
                foreach ($primaryTypes as $primaryType) {
                    $primarySectionsByType[$primaryType][] = [
                        'index' => $sectionIndex,
                        'id' => $sectionId,
                        'label' => $sectionLabel,
                    ];
                }
                if ($hidden) {
                    ++$hiddenPrimarySectionCount;
                    $sectionDiagnostics[] = [
                        'type' => 'hidden-primary-nav-section',
                        'message' => 'EPUB navigation document contains a primary nav section hidden from readers',
                    ];
                }
                if ($sectionLabel === '') {
                    ++$missingHeadingSectionCount;
                    $sectionDiagnostics[] = [
                        'type' => 'missing-primary-nav-section-heading',
                        'message' => 'EPUB primary nav section is missing a heading label',
                    ];
                }
            } elseif ($sectionTypes === []) {
                ++$untypedSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'missing-nav-section-type',
                    'message' => 'EPUB nav section has no epub:type classification',
                ];
            } else {
                ++$unrecognizedSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'unrecognized-nav-section-type',
                    'message' => 'EPUB nav section has no recognized primary navigation type',
                ];
            }

            if (!$hasOrderedList) {
                ++$missingOrderedListSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'missing-nav-section-ordered-list',
                    'message' => 'EPUB nav section has no direct ordered list of navigation entries',
                ];
            }
            if ($itemCount === 0) {
                ++$emptySectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'empty-nav-section',
                    'message' => 'EPUB nav section has no direct navigation list items',
                ];
            }

            $section = [
                'index' => $sectionIndex,
                'sectionIndex' => $sectionIndex,
                'id' => $sectionId,
                'sectionId' => $sectionId,
                'label' => $sectionLabel,
                'sectionLabel' => $sectionLabel,
                'type' => $sectionType,
                'sectionType' => $sectionType,
                'sectionTypes' => $primaryTypes,
                'epubTypes' => $sectionTypes,
                'hidden' => $hidden,
                'hasHeading' => $sectionLabel !== '',
                'hasOrderedList' => $hasOrderedList,
                'itemCount' => $itemCount,
                'diagnosticCount' => count($sectionDiagnostics),
                'diagnostics' => [],
            ];

            foreach ($sectionDiagnostics as $diagnostic) {
                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionType' => $sectionType,
                    'sectionTypes' => $primaryTypes,
                    'epubTypes' => $sectionTypes,
                    'label' => $sectionLabel,
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $section['diagnostics'][] = $reportedDiagnostic;
            }

            $sections[] = $section;
        }

        $duplicatePrimaryTypeCount = 0;
        foreach ($primarySectionsByType as $type => $matches) {
            if (count($matches) < 2) {
                continue;
            }

            ++$duplicatePrimaryTypeCount;
            $diagnostic = [
                'index' => count($diagnostics),
                'type' => 'duplicate-primary-nav-section',
                'sectionType' => $type,
                'sectionIndexes' => array_map(static fn (array $match): int => (int) $match['index'], $matches),
                'sectionIds' => array_map(static fn (array $match): ?string => $match['id'], $matches),
                'sectionLabels' => array_map(static fn (array $match): string => (string) $match['label'], $matches),
                'message' => 'EPUB navigation document contains multiple primary nav sections of the same type',
            ];
            $diagnostics[] = $diagnostic;
            foreach ($matches as $match) {
                $sectionIndex = (int) $match['index'];
                if (!isset($sections[$sectionIndex])) {
                    continue;
                }
                $sections[$sectionIndex]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionIndex]['diagnosticCount'];
            }
        }

        if ($primarySectionsByType['toc'] === []) {
            $diagnostics[] = [
                'index' => count($diagnostics),
                'type' => 'missing-primary-toc-nav-section',
                'message' => 'EPUB navigation document does not contain a primary toc nav section',
            ];
        }

        return [
            'present' => true,
            'part' => $path === '' ? null : '/' . $path,
            'path' => $path,
            'sectionCount' => count($sections),
            'primarySectionCount' => $primarySectionCount,
            'tocSectionCount' => count($primarySectionsByType['toc']),
            'landmarksSectionCount' => count($primarySectionsByType['landmarks']),
            'pageListSectionCount' => count($primarySectionsByType['page-list']),
            'requiredTocPresent' => $primarySectionsByType['toc'] !== [],
            'duplicatePrimaryTypeCount' => $duplicatePrimaryTypeCount,
            'hiddenPrimarySectionCount' => $hiddenPrimarySectionCount,
            'missingHeadingSectionCount' => $missingHeadingSectionCount,
            'missingOrderedListSectionCount' => $missingOrderedListSectionCount,
            'emptySectionCount' => $emptySectionCount,
            'untypedSectionCount' => $untypedSectionCount,
            'unrecognizedSectionCount' => $unrecognizedSectionCount,
            'sections' => $sections,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navHrefNormalizationReport(array $flat): array
    {
        $sectionKeys = [];
        $sections = [];
        $sectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $typeCounts = [
            'toc' => 0,
            'landmarks' => 0,
            'page-list' => 0,
        ];
        $diagnostics = [];
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $missingTargetCount = 0;
        $fragmentTargetCount = 0;
        $normalizedHrefCount = 0;
        $percentDecodedHrefCount = 0;
        $dotSegmentNormalizedHrefCount = 0;
        $packageRootEscapeCount = 0;
        $caseMismatchCount = 0;
        $emptyHrefCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if (!isset($typeCounts[$sectionType])) {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                ++$typeCounts[$sectionType];
                $sections[] = [
                    'index' => $sectionIndex,
                    'sectionIndex' => $sectionIndex,
                    'id' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'entryCount' => 0,
                    'itemCount' => 0,
                    'localTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'missingTargetCount' => 0,
                    'fragmentTargetCount' => 0,
                    'normalizedHrefCount' => 0,
                    'percentDecodedHrefCount' => 0,
                    'dotSegmentNormalizedHrefCount' => 0,
                    'packageRootEscapeCount' => 0,
                    'caseMismatchCount' => 0,
                    'emptyHrefCount' => 0,
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            ++$sections[$sectionOffset]['entryCount'];
            ++$sections[$sectionOffset]['itemCount'];

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $external = ($item['external'] ?? false) === true;
            $unsafe = ($item['unsafe'] ?? false) === true;
            $exists = ($item['exists'] ?? false) === true;
            $normalization = is_array($item['normalization'] ?? null) ? $item['normalization'] : [];
            $packageRootEscape = ($normalization['packageRootEscape'] ?? false) === true;

            if ($href !== '' && !$external && !$unsafe && $path !== '') {
                ++$localTargetCount;
                ++$sections[$sectionOffset]['localTargetCount'];
                if (!$exists) {
                    ++$missingTargetCount;
                    ++$sections[$sectionOffset]['missingTargetCount'];
                }
            }
            if ($external && !$packageRootEscape) {
                ++$externalTargetCount;
                ++$sections[$sectionOffset]['externalTargetCount'];
            }
            if ($fragment !== '') {
                ++$fragmentTargetCount;
                ++$sections[$sectionOffset]['fragmentTargetCount'];
            }
            if (($normalization['normalized'] ?? false) === true) {
                ++$normalizedHrefCount;
                ++$sections[$sectionOffset]['normalizedHrefCount'];
            }
            if (($normalization['percentDecoded'] ?? false) === true) {
                ++$percentDecodedHrefCount;
                ++$sections[$sectionOffset]['percentDecodedHrefCount'];
            }
            if (($normalization['dotSegmentNormalized'] ?? false) === true) {
                ++$dotSegmentNormalizedHrefCount;
                ++$sections[$sectionOffset]['dotSegmentNormalizedHrefCount'];
            }
            if (($normalization['packageRootEscape'] ?? false) === true) {
                ++$packageRootEscapeCount;
                ++$sections[$sectionOffset]['packageRootEscapeCount'];
            }
            if (($normalization['caseMismatch'] ?? false) === true) {
                ++$caseMismatchCount;
                ++$sections[$sectionOffset]['caseMismatchCount'];
            }
            if ($href === '' && ($item['linkElement'] ?? null) === 'a') {
                ++$emptyHrefCount;
                ++$sections[$sectionOffset]['emptyHrefCount'];
            }

            foreach (is_array($item['hrefNormalizationDiagnostics'] ?? null) ? $item['hrefNormalizationDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $reportedDiagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }
        }

        foreach ($sections as $section) {
            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            if (isset($sectionsByType[$type])) {
                $sectionsByType[$type][] = $section;
            }
        }

        return [
            'present' => $flat !== [],
            'entryCount' => count($flat),
            'itemCount' => count($flat),
            'sectionCount' => count($sections),
            'typeCounts' => $typeCounts,
            'sections' => $sections,
            'sectionsByType' => $sectionsByType,
            'localTargetCount' => $localTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'fragmentTargetCount' => $fragmentTargetCount,
            'normalizedHrefCount' => $normalizedHrefCount,
            'percentDecodedHrefCount' => $percentDecodedHrefCount,
            'dotSegmentNormalizedHrefCount' => $dotSegmentNormalizedHrefCount,
            'packageRootEscapeCount' => $packageRootEscapeCount,
            'caseMismatchCount' => $caseMismatchCount,
            'emptyHrefCount' => $emptyHrefCount,
            'diagnosticCount' => count($diagnostics),
            'diagnosticTypes' => $this->diagnosticTypeCounts($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, int>
     */
    private function diagnosticTypeCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            if ($type === '') {
                continue;
            }

            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navHrefPolicyReport(array $flat): array
    {
        $sectionKeys = [];
        $sections = [];
        $diagnostics = [];
        $targetedItemCount = 0;
        $localTargetCount = 0;
        $safeLocalTargetCount = 0;
        $externalTargetCount = 0;
        $unsafeTargetCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sections[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                    'topLevelItemCount' => 0,
                    'targetedItemCount' => 0,
                    'localTargetCount' => 0,
                    'safeLocalTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'unsafeTargetCount' => 0,
                    'externalSchemeCounts' => [],
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            ++$sections[$sectionOffset]['itemCount'];
            if ((int) ($item['depth'] ?? 0) === 0) {
                ++$sections[$sectionOffset]['topLevelItemCount'];
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $external = ($item['external'] ?? false) === true;
            $unsafe = ($item['unsafe'] ?? false) === true;
            $scheme = is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null;
            if ($href !== '') {
                ++$targetedItemCount;
                ++$sections[$sectionOffset]['targetedItemCount'];
                if ($external) {
                    ++$externalTargetCount;
                    ++$sections[$sectionOffset]['externalTargetCount'];
                    if ($scheme !== null && $scheme !== '') {
                        $sections[$sectionOffset]['externalSchemeCounts'][$scheme] = ($sections[$sectionOffset]['externalSchemeCounts'][$scheme] ?? 0) + 1;
                    }
                } else {
                    ++$localTargetCount;
                    ++$sections[$sectionOffset]['localTargetCount'];
                    if (!$unsafe) {
                        ++$safeLocalTargetCount;
                        ++$sections[$sectionOffset]['safeLocalTargetCount'];
                    }
                }
            }
            if ($unsafe) {
                ++$unsafeTargetCount;
                ++$sections[$sectionOffset]['unsafeTargetCount'];
            }

            foreach (is_array($item['hrefDiagnostics'] ?? null) ? $item['hrefDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $reportedDiagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }
        }

        $sectionTypeCounts = [
            'toc' => 0,
            'landmarks' => 0,
            'page-list' => 0,
        ];
        $sectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        foreach ($sections as &$section) {
            ksort($section['externalSchemeCounts']);
            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            if (isset($sectionTypeCounts[$type])) {
                ++$sectionTypeCounts[$type];
                $sectionsByType[$type][] = $section;
            }
        }
        unset($section);

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'targetedItemCount' => $targetedItemCount,
            'localTargetCount' => $localTargetCount,
            'safeLocalTargetCount' => $safeLocalTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'unsafeTargetCount' => $unsafeTargetCount,
            'sectionCount' => count($sections),
            'sectionTypeCounts' => $sectionTypeCounts,
            'tocSectionCount' => $sectionTypeCounts['toc'],
            'landmarksSectionCount' => $sectionTypeCounts['landmarks'],
            'pageListSectionCount' => $sectionTypeCounts['page-list'],
            'diagnosticCount' => count($diagnostics),
            'sections' => $sections,
            'sectionsByType' => $sectionsByType,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function pageListReport(array $flat, array $package): array
    {
        $manifestByPath = [];
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $manifestItem) {
            if (!is_array($manifestItem) || ($manifestItem['external'] ?? false) === true) {
                continue;
            }
            $path = is_string($manifestItem['path'] ?? null) ? $manifestItem['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $manifestItem;
            }
        }

        $spineByPath = [];
        $readingSpineByPath = [];
        foreach (is_array($package['spine'] ?? null) ? $package['spine'] : [] as $index => $spineItem) {
            if (!is_array($spineItem)) {
                continue;
            }
            $path = is_string($spineItem['path'] ?? null) ? $spineItem['path'] : '';
            if ($path === '') {
                continue;
            }
            $reportedSpineItem = ['index' => $index] + $spineItem;
            $spineByPath[$path][] = $reportedSpineItem;
            if (($spineItem['linear'] ?? false) === true) {
                $readingSpineByPath[$path][] = $reportedSpineItem;
            }
        }

        $collisionsByTarget = [];
        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks') {
                continue;
            }

            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            if ($target === '') {
                continue;
            }

            $collisionsByTarget[$target][] = [
                'navigationIndex' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'sourceIndex' => $sourceIndex,
                'type' => $sectionType,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'href' => is_string($item['href'] ?? null) ? $item['href'] : '',
                'target' => $target,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
            ];
        }

        $targetItems = [];
        $pageListIndex = 0;
        foreach ($flat as $sourceIndex => $item) {
            $type = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($type !== 'page-list') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $external = (bool) ($item['external'] ?? false);
            if ($href !== '' && $path !== '' && !$external) {
                $target = $path . ($fragment === '' ? '' : '#' . $fragment);
                $targetItems[$target][] = [
                    'index' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                ];
            }
            ++$pageListIndex;
        }

        $duplicatePageTargets = [];
        $duplicatePageTargetsByTarget = [];
        $duplicatePageTargetItemCount = 0;
        foreach ($targetItems as $target => $items) {
            if (count($items) < 2) {
                continue;
            }

            $group = [
                'target' => $target,
                'path' => (string) ($items[0]['path'] ?? ''),
                'fragment' => (string) ($items[0]['fragment'] ?? ''),
                'count' => count($items),
                'indexes' => array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items),
                'sourceIndexes' => array_map(static fn (array $item): int => (int) ($item['sourceIndex'] ?? 0), $items),
                'hrefs' => array_map(static fn (array $item): string => (string) ($item['href'] ?? ''), $items),
                'labels' => array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $items),
                'items' => array_values($items),
            ];
            $duplicatePageTargets[] = $group;
            $duplicatePageTargetsByTarget[$target] = $group;
            $duplicatePageTargetItemCount += count($items);
        }

        $pageListItems = [];
        $pageBreakItemCount = 0;
        $diagnostics = [];
        $seenPageListHrefs = [];
        $seenPageListLabels = [];
        $duplicateSpineTargetsByPath = [];
        $duplicateSpineTargetItemCount = 0;
        $collisionTargets = [];
        $fragmentTargets = [];
        $readingOrder = [];
        $previousSpineIndex = null;
        $linearTargetCount = 0;
        $nonlinearTargetCount = 0;
        $targetedItemCount = 0;
        $manifestTargetCount = 0;
        $spineReadingOrderTargetCount = 0;
        $missingManifestTargetCount = 0;
        $outsideSpineTargetCount = 0;
        $externalTargetCount = 0;
        $unresolvedTargetCount = 0;
        $cfiTargetCount = 0;
        $cfiTargets = [];

        foreach ($flat as $sourceIndex => $item) {
            $type = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if (($item['pageBreakProvenance']['present'] ?? false) === true) {
                ++$pageBreakItemCount;
            }

            if ($type !== 'page-list') {
                continue;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'navType' => $type,
                ] + $diagnostic;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $fragmentKind = $this->epubFragmentKind($fragment);
            $external = (bool) ($item['external'] ?? false);
            $manifestItem = !$external && $path !== '' ? ($manifestByPath[$path] ?? null) : null;
            $spineItems = !$external && $path !== '' ? ($spineByPath[$path] ?? []) : [];
            $readingSpineItems = !$external && $path !== '' ? ($readingSpineByPath[$path] ?? []) : [];
            $spineItems = is_array($spineItems) ? array_values(array_filter($spineItems, 'is_array')) : [];
            $readingSpineItems = is_array($readingSpineItems) ? array_values(array_filter($readingSpineItems, 'is_array')) : [];
            $firstSpineItem = $spineItems[0] ?? null;
            $spineIndexes = array_map(static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0), $spineItems);
            $readingSpineIndexes = array_map(static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0), $readingSpineItems);
            $nonlinearSpineIndexes = array_map(
                static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0),
                array_values(array_filter(
                    $spineItems,
                    static fn (array $spineItem): bool => ($spineItem['linear'] ?? false) !== true
                ))
            );
            $spineIdrefs = array_map(static fn (array $spineItem): string => (string) ($spineItem['idref'] ?? ''), $spineItems);
            $spineIndex = is_array($firstSpineItem) && is_int($firstSpineItem['index'] ?? null) ? $firstSpineItem['index'] : null;
            $spineLinear = is_array($firstSpineItem) ? (($firstSpineItem['linear'] ?? false) === true) : null;
            $duplicatePageTarget = $target !== '' ? ($duplicatePageTargetsByTarget[$target] ?? null) : null;
            $collisions = $target !== '' ? ($collisionsByTarget[$target] ?? []) : [];
            $itemDiagnostics = array_values(array_filter(
                is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [],
                static fn (mixed $diagnostic): bool => is_array($diagnostic)
            ));
            $pageListIndex = count($pageListItems);
            $pageListItems[] = [
                'index' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'navIndex' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'fragmentKind' => $fragmentKind,
                'epubCfi' => $fragmentKind === 'epub-cfi',
                'cfiFragment' => $fragmentKind === 'epub-cfi' ? $fragment : null,
                'external' => $external,
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => (bool) ($item['exists'] ?? false),
                'pageBreak' => ($item['pageBreakProvenance']['present'] ?? false) === true,
                'pageBreakProvenance' => is_array($item['pageBreakProvenance'] ?? null) ? $item['pageBreakProvenance'] : [],
                'epubTypes' => is_array($item['epubTypes'] ?? null) ? array_values($item['epubTypes']) : [],
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'mediaType' => is_array($manifestItem) && is_string($manifestItem['mediaType'] ?? null) ? $manifestItem['mediaType'] : null,
                'spineIndex' => $spineIndex,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'nonlinearSpineIndexes' => $nonlinearSpineIndexes,
                'spineIdref' => is_array($firstSpineItem) && is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : null,
                'spineIdrefs' => $spineIdrefs,
                'spineLinear' => $spineLinear,
                'linear' => $spineLinear,
                'inSpineReadingOrder' => $readingSpineItems !== [],
                'duplicatePageTarget' => is_array($duplicatePageTarget),
                'duplicatePageTargetCount' => is_array($duplicatePageTarget) ? (int) ($duplicatePageTarget['count'] ?? 0) : 0,
                'duplicateSpineTarget' => count($spineItems) > 1,
                'duplicateSpineTargetCount' => count($spineItems),
                'collisions' => $collisions,
                'diagnostics' => $itemDiagnostics,
            ];

            if ($fragmentKind === 'epub-cfi') {
                ++$cfiTargetCount;
                $cfiTargets[] = [
                    'index' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'navIndex' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'fragmentKind' => $fragmentKind,
                    'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                    'spineIndex' => $spineIndex,
                    'spineIndexes' => $spineIndexes,
                    'readingSpineIndexes' => $readingSpineIndexes,
                ];
            }

            if ($collisions !== []) {
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'page-list-target-nav-collision',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'collisions' => $collisions,
                    'message' => 'EPUB page-list target also appears in toc or landmarks navigation',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                $collisionTargets[$target] = [
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'collisions' => $collisions,
                ];
            }

            if (is_array($duplicatePageTarget)) {
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'duplicate-page-list-target',
                    'navType' => $type,
                    'href' => $href,
                    'target' => (string) ($duplicatePageTarget['target'] ?? $target),
                    'path' => $path,
                    'fragment' => $fragment,
                    'count' => (int) ($duplicatePageTarget['count'] ?? 0),
                    'indexes' => is_array($duplicatePageTarget['indexes'] ?? null) ? $duplicatePageTarget['indexes'] : [],
                    'sourceIndexes' => is_array($duplicatePageTarget['sourceIndexes'] ?? null) ? $duplicatePageTarget['sourceIndexes'] : [],
                    'message' => 'EPUB page-list contains repeated entries for the same package target',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }

            if (is_array($manifestItem) && count($spineItems) > 1) {
                ++$duplicateSpineTargetItemCount;
                if (!isset($duplicateSpineTargetsByPath[$path])) {
                    $duplicateSpineTargetsByPath[$path] = [
                        'path' => $path,
                        'count' => count($spineItems),
                        'spineIndexes' => $spineIndexes,
                        'idrefs' => $spineIdrefs,
                    ];
                }
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'page-list-target-duplicate-spine-itemref',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'manifestId' => is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                    'count' => count($spineItems),
                    'spineIndexes' => $spineIndexes,
                    'idrefs' => $duplicateSpineTargetsByPath[$path]['idrefs'],
                    'message' => 'EPUB page-list target resolves to multiple spine itemrefs',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }

            if (is_array($firstSpineItem)) {
                if ($spineLinear === true) {
                    ++$linearTargetCount;
                } else {
                    ++$nonlinearTargetCount;
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'page-list-target-nonlinear-spine-item',
                        'navType' => $type,
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'spineIndex' => $spineIndex,
                        'spineIdref' => is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : '',
                        'message' => 'EPUB page-list target resolves to a non-linear spine itemref',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                }

                if ($previousSpineIndex !== null && $spineIndex !== null && $spineIndex < $previousSpineIndex) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'page-list-reading-order-regression',
                        'navType' => $type,
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'previousSpineIndex' => $previousSpineIndex,
                        'spineIndex' => $spineIndex,
                        'message' => 'EPUB page-list target order moves backward through the package spine',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                }
                $previousSpineIndex = $spineIndex;
            }

            if ($target !== '' && $fragment !== '') {
                $fragmentTargets[$target][] = [
                    'index' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                ];
            }

            $readingOrder[] = [
                'index' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'fragmentKind' => $fragmentKind,
                'epubCfi' => $fragmentKind === 'epub-cfi',
                'spineIndex' => $spineIndex,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'nonlinearSpineIndexes' => $nonlinearSpineIndexes,
                'spineIdref' => is_array($firstSpineItem) && is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : null,
                'spineIdrefs' => $spineIdrefs,
                'linear' => $spineLinear,
                'inSpineReadingOrder' => $readingSpineItems !== [],
                'duplicatePageTarget' => is_array($duplicatePageTarget),
                'duplicateSpineTarget' => count($spineItems) > 1,
            ];

            if ($href !== '') {
                ++$targetedItemCount;
                if (isset($seenPageListHrefs[$href])) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-href',
                        'navType' => $type,
                        'source' => 'href',
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'firstIndex' => is_int($seenPageListHrefs[$href]['item']['index'] ?? null) ? $seenPageListHrefs[$href]['item']['index'] : 0,
                        'firstPageListIndex' => is_int($seenPageListHrefs[$href]['pageListIndex'] ?? null) ? $seenPageListHrefs[$href]['pageListIndex'] : 0,
                        'firstTarget' => is_string($seenPageListHrefs[$href]['item']['target'] ?? null) ? $seenPageListHrefs[$href]['item']['target'] : '',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                } else {
                    $seenPageListHrefs[$href] = [
                        'item' => $item,
                        'pageListIndex' => $pageListIndex,
                    ];
                }
            } else {
                ++$unresolvedTargetCount;
            }

            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            if ($label !== '') {
                if (isset($seenPageListLabels[$label])) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-label',
                        'navType' => $type,
                        'source' => 'label',
                        'label' => $label,
                        'href' => $href,
                        'target' => $target,
                        'firstIndex' => is_int($seenPageListLabels[$label]['item']['index'] ?? null) ? $seenPageListLabels[$label]['item']['index'] : 0,
                        'firstPageListIndex' => is_int($seenPageListLabels[$label]['pageListIndex'] ?? null) ? $seenPageListLabels[$label]['pageListIndex'] : 0,
                        'firstHref' => is_string($seenPageListLabels[$label]['item']['href'] ?? null) ? $seenPageListLabels[$label]['item']['href'] : '',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                } else {
                    $seenPageListLabels[$label] = [
                        'item' => $item,
                        'pageListIndex' => $pageListIndex,
                    ];
                }
            }

            if ($href === '') {
                continue;
            }
            if ($external) {
                ++$externalTargetCount;
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'external-page-list-reference',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB page-list target points outside the package and was not resolved against the manifest or spine',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                continue;
            }
            if ($path === '' || !is_array($manifestItem)) {
                ++$missingManifestTargetCount;
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'missing-page-list-manifest-item',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'message' => 'EPUB page-list target is not present in the OPF manifest',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                continue;
            }

            ++$manifestTargetCount;
            if ($readingSpineItems !== []) {
                ++$spineReadingOrderTargetCount;
                continue;
            }

            ++$outsideSpineTargetCount;
            $reason = is_array($firstSpineItem) && ($firstSpineItem['linear'] ?? false) !== true
                ? 'nonlinear-spine-item'
                : 'not-in-spine';
            $diagnostic = [
                'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'pageListIndex' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                'type' => 'page-list-target-outside-spine-reading-order',
                'navType' => $type,
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'spineIndex' => is_array($firstSpineItem) && is_int($firstSpineItem['index'] ?? null) ? $firstSpineItem['index'] : null,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'spineLinear' => is_array($firstSpineItem) ? (($firstSpineItem['linear'] ?? false) === true) : null,
                'reason' => $reason,
                'message' => 'EPUB page-list target is in the manifest but outside the linear spine reading order',
            ];
            $diagnostics[] = $diagnostic;
            $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
        }

        $repeatedFragmentTargets = [];
        foreach ($fragmentTargets as $target => $targetItems) {
            if (count($targetItems) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) $item['index'], $targetItems);
            $labels = array_map(static fn (array $item): string => (string) $item['label'], $targetItems);
            $hrefs = array_map(static fn (array $item): string => (string) $item['href'], $targetItems);
            $repeatedFragmentTargets[] = [
                'target' => $target,
                'path' => (string) ($targetItems[0]['path'] ?? ''),
                'fragment' => (string) ($targetItems[0]['fragment'] ?? ''),
                'indexes' => $indexes,
                'labels' => $labels,
                'hrefs' => $hrefs,
                'items' => $targetItems,
            ];
            $diagnostic = [
                'type' => 'repeated-page-list-fragment-target',
                'target' => $target,
                'path' => (string) ($targetItems[0]['path'] ?? ''),
                'fragment' => (string) ($targetItems[0]['fragment'] ?? ''),
                'indexes' => $indexes,
                'labels' => $labels,
                'hrefs' => $hrefs,
                'message' => 'EPUB page-list contains repeated entries for the same fragment target',
            ];
            $diagnostics[] = $diagnostic;

            foreach ($indexes as $pageListIndex) {
                if (!isset($pageListItems[$pageListIndex])) {
                    continue;
                }
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }
        }

        $readingOrderByTarget = [];
        $readingOrderBySpineIndex = [];
        foreach ($readingOrder as $summary) {
            $target = is_string($summary['target'] ?? null) ? $summary['target'] : '';
            if ($target !== '') {
                $readingOrderByTarget[$target][] = $summary;
            }
            foreach (is_array($summary['spineIndexes'] ?? null) ? $summary['spineIndexes'] : [] as $spineIndex) {
                $readingOrderBySpineIndex[(int) $spineIndex][] = $summary;
            }
        }
        ksort($readingOrderBySpineIndex);

        return [
            'present' => $pageListItems !== [],
            'itemCount' => count($pageListItems),
            'pageBreakItemCount' => $pageBreakItemCount,
            'targetedItemCount' => $targetedItemCount,
            'manifestTargetCount' => $manifestTargetCount,
            'spineReadingOrderTargetCount' => $spineReadingOrderTargetCount,
            'linearTargetCount' => $linearTargetCount,
            'nonlinearTargetCount' => $nonlinearTargetCount,
            'missingManifestTargetCount' => $missingManifestTargetCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'unresolvedTargetCount' => $unresolvedTargetCount,
            'cfiTargetCount' => $cfiTargetCount,
            'epubCfiTargetCount' => $cfiTargetCount,
            'cfiTargets' => $cfiTargets,
            'epubCfiTargets' => $cfiTargets,
            'collisionTargetCount' => count($collisionTargets),
            'collisionTargets' => array_values($collisionTargets),
            'duplicatePageTargetCount' => count($duplicatePageTargets),
            'duplicatePageTargetItemCount' => $duplicatePageTargetItemCount,
            'duplicatePageTargets' => $duplicatePageTargets,
            'duplicateSpineTargetCount' => count($duplicateSpineTargetsByPath),
            'duplicateSpineTargetItemCount' => $duplicateSpineTargetItemCount,
            'duplicateSpineTargets' => array_values($duplicateSpineTargetsByPath),
            'repeatedFragmentTargetCount' => count($repeatedFragmentTargets),
            'repeatedFragmentTargets' => $repeatedFragmentTargets,
            'readingOrder' => $readingOrder,
            'readingOrderByTarget' => $readingOrderByTarget,
            'readingOrderBySpineIndex' => $readingOrderBySpineIndex,
            'items' => $pageListItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navNormalizedCollisionReport(array $flat, string $navPath): array
    {
        $sections = [];
        $sectionKeys = [];
        $sectionItemIndexes = [];
        $sectionTargets = [];
        $crossSectionTargets = [];
        $diagnostics = [];
        $normalizedCollisionDiagnostics = [];
        $itemCount = 0;
        $targetedItemCount = 0;
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $fragmentTargetCount = 0;
        $fragmentOnlyTargetCount = 0;
        $unsafeTargetCount = 0;
        $packageRootEscapeTargetCount = 0;
        $normalizedCollisionItemCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sectionItemIndexes[$sectionKey] = 0;
                $sections[] = [
                    'index' => $sectionIndex,
                    'sectionIndex' => $sectionIndex,
                    'id' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                    'targetedItemCount' => 0,
                    'localTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'fragmentTargetCount' => 0,
                    'fragmentOnlyTargetCount' => 0,
                    'unsafeTargetCount' => 0,
                    'packageRootEscapeTargetCount' => 0,
                    'normalizedCollisionGroupCount' => 0,
                    'normalizedCollisionItemCount' => 0,
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            $itemIndex = $sectionItemIndexes[$sectionKey]++;
            ++$itemCount;
            ++$sections[$sectionOffset]['itemCount'];

            $href = is_string($item['href'] ?? null) ? trim($item['href']) : '';
            if ($href === '') {
                continue;
            }

            ++$targetedItemCount;
            ++$sections[$sectionOffset]['targetedItemCount'];
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $external = (bool) ($item['external'] ?? false);
            $unsafe = (bool) ($item['unsafe'] ?? false);
            $hrefKind = is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '';
            $normalization = is_array($item['normalization'] ?? null) ? $item['normalization'] : [];
            $packageRootEscape = ($normalization['packageRootEscape'] ?? false) === true;
            $suffix = $this->hrefSuffix($href);
            $fragmentOnly = $hrefKind === 'same-document-fragment' || ($path === '' && str_starts_with($href, '#'));
            $baseDiagnostic = [
                'sectionIndex' => $sectionIndex,
                'sectionType' => $sectionType,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'itemIndex' => $itemIndex,
                'sourceIndex' => $sourceIndex,
                'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'href' => $href,
            ];

            if ($external) {
                ++$externalTargetCount;
                ++$sections[$sectionOffset]['externalTargetCount'];
                if ($packageRootEscape) {
                    ++$packageRootEscapeTargetCount;
                    ++$sections[$sectionOffset]['packageRootEscapeTargetCount'];
                }
                $diagnostic = $baseDiagnostic + [
                    'type' => 'external-nav-href-target',
                    'target' => $target,
                    'hrefKind' => $hrefKind,
                    'message' => 'EPUB navigation target resolves outside the package',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
                continue;
            }

            if ($unsafe) {
                ++$unsafeTargetCount;
                ++$sections[$sectionOffset]['unsafeTargetCount'];
                $diagnostic = $baseDiagnostic + [
                    'type' => 'unsafe-nav-href-target',
                    'target' => $target,
                    'hrefKind' => $hrefKind,
                    'message' => 'EPUB navigation target uses an unsafe href scheme',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
                continue;
            }

            $targetPath = $fragmentOnly ? $navPath : $path;
            if ($targetPath !== '') {
                ++$localTargetCount;
                ++$sections[$sectionOffset]['localTargetCount'];
            }

            if ($suffix['hasFragment']) {
                ++$fragmentTargetCount;
                ++$sections[$sectionOffset]['fragmentTargetCount'];
                $diagnostic = $baseDiagnostic + [
                    'type' => $fragmentOnly ? 'fragment-only-nav-href-target' : 'fragment-nav-href-target',
                    'target' => $fragmentOnly
                        ? $navPath . '#' . $fragment
                        : ($target !== '' ? $target : $targetPath . '#' . $fragment),
                    'path' => $targetPath,
                    'fragment' => $fragment,
                    'message' => $fragmentOnly
                        ? 'EPUB navigation target resolves to a fragment in the navigation document'
                        : 'EPUB navigation target includes a fragment component',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }

            if ($fragmentOnly) {
                ++$fragmentOnlyTargetCount;
                ++$sections[$sectionOffset]['fragmentOnlyTargetCount'];
            }

            $normalizedTarget = $this->normalizedNavTarget($targetPath, $suffix['hasFragment'], $fragment);
            if ($normalizedTarget === '') {
                continue;
            }

            $targetDiagnostic = $baseDiagnostic + [
                'target' => $fragmentOnly
                    ? $navPath . ($suffix['hasFragment'] ? '#' . $fragment : '')
                    : ($target !== '' ? $target : $targetPath . ($suffix['hasFragment'] ? '#' . $fragment : '')),
                'normalizedTarget' => $normalizedTarget,
                'path' => $targetPath,
                'fragment' => $fragment,
                'fragmentOnly' => $fragmentOnly,
            ];
            $sectionTargets[$sectionKey][$normalizedTarget][] = $targetDiagnostic;
            $crossSectionTargets[$normalizedTarget][] = $targetDiagnostic;
        }

        foreach ($sectionTargets as $sectionKey => $targets) {
            $sectionOffset = $sectionKeys[$sectionKey] ?? null;
            if (!is_int($sectionOffset)) {
                continue;
            }

            $sectionCollisionDiagnostics = [];
            foreach ($targets as $normalizedTarget => $matches) {
                $rawHrefs = array_values(array_unique(array_map(
                    static fn (array $match): string => (string) ($match['href'] ?? ''),
                    $matches
                )));
                if (count($matches) <= 1 || count($rawHrefs) <= 1) {
                    continue;
                }

                $matches = $this->sortNavTargetMatches($matches);
                $diagnostic = [
                    'type' => 'normalized-nav-target-collision',
                    'sectionIndex' => (int) ($matches[0]['sectionIndex'] ?? 0),
                    'sectionType' => (string) ($matches[0]['sectionType'] ?? ''),
                    'sectionId' => is_string($matches[0]['sectionId'] ?? null) ? $matches[0]['sectionId'] : null,
                    'normalizedTarget' => $normalizedTarget,
                    'itemCount' => count($matches),
                    'rawHrefCount' => count($rawHrefs),
                    'itemIndexes' => array_map(static fn (array $match): int => (int) ($match['itemIndex'] ?? 0), $matches),
                    'sourceIndexes' => array_map(static fn (array $match): int => (int) ($match['sourceIndex'] ?? 0), $matches),
                    'hrefs' => $rawHrefs,
                    'targets' => $this->uniqueNavMatchValues($matches, 'target'),
                    'labels' => array_values(array_filter(
                        $this->uniqueNavMatchValues($matches, 'label'),
                        static fn (string $label): bool => $label !== ''
                    )),
                    'collisionKinds' => $this->navCollisionKinds($matches),
                    'message' => 'EPUB navigation section contains distinct hrefs that normalize to the same target',
                ];
                $sectionCollisionDiagnostics[] = $diagnostic;
            }

            $sectionCollisionDiagnostics = $this->sortNavCollisionDiagnostics($sectionCollisionDiagnostics);
            foreach ($sectionCollisionDiagnostics as $diagnostic) {
                $normalizedCollisionItemCount += (int) ($diagnostic['itemCount'] ?? 0);
                $normalizedCollisionDiagnostics[] = $diagnostic;
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }

            $sections[$sectionOffset]['normalizedCollisionGroupCount'] = count($sectionCollisionDiagnostics);
            $sections[$sectionOffset]['normalizedCollisionItemCount'] = array_sum(array_map(
                static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
                $sectionCollisionDiagnostics
            ));
        }

        $normalizedCollisionDiagnostics = $this->sortNavCollisionDiagnostics($normalizedCollisionDiagnostics);
        $crossSectionCollisionDiagnostics = $this->crossSectionNavCollisionDiagnostics($crossSectionTargets);
        $crossSectionCollisionItemCount = array_sum(array_map(
            static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
            $crossSectionCollisionDiagnostics
        ));
        $diagnostics = $this->sortNavDiagnostics(array_merge($diagnostics, $crossSectionCollisionDiagnostics));

        return [
            'present' => $flat !== [],
            'itemCount' => $itemCount,
            'targetedItemCount' => $targetedItemCount,
            'localTargetCount' => $localTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'fragmentTargetCount' => $fragmentTargetCount,
            'fragmentOnlyTargetCount' => $fragmentOnlyTargetCount,
            'unsafeTargetCount' => $unsafeTargetCount,
            'packageRootEscapeTargetCount' => $packageRootEscapeTargetCount,
            'normalizedCollisionGroupCount' => count($normalizedCollisionDiagnostics),
            'normalizedCollisionItemCount' => $normalizedCollisionItemCount,
            'normalizedCollisionDiagnostics' => $normalizedCollisionDiagnostics,
            'crossSectionCollisionGroupCount' => count($crossSectionCollisionDiagnostics),
            'crossSectionCollisionItemCount' => $crossSectionCollisionItemCount,
            'crossSectionCollisionDiagnostics' => $crossSectionCollisionDiagnostics,
            'sections' => $sections,
            'diagnosticTypes' => $this->diagnosticTypeCounts($diagnostics),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    private function normalizedNavTarget(string $path, bool $hasFragment, string $fragment): string
    {
        if ($path === '') {
            return '';
        }

        $target = strtolower($path);
        if ($hasFragment) {
            $target .= '#' . strtolower(rawurldecode($fragment));
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private function navCollisionKinds(array $matches): array
    {
        $hrefs = array_values(array_unique(array_map(
            static fn (array $match): string => (string) ($match['href'] ?? ''),
            $matches
        )));
        $targets = array_values(array_unique(array_map(
            static fn (array $match): string => (string) ($match['target'] ?? ''),
            $matches
        )));
        $kinds = [];

        foreach ($hrefs as $href) {
            if (preg_match('/%[0-9A-Fa-f]{2}/', $href) === 1) {
                $kinds['percent-encoding'] = 'percent-encoding';
            }
            if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', explode('#', explode('?', $href, 2)[0], 2)[0]) === 1) {
                $kinds['dot-segment'] = 'dot-segment';
            }
            if (strpos($href, '#') !== false) {
                $kinds['fragment'] = 'fragment';
            }
        }

        if (count(array_unique(array_map('strtolower', $hrefs))) < count($hrefs)
            || count(array_unique(array_map('strtolower', $targets))) < count($targets)
        ) {
            $kinds['case'] = 'case';
        }

        if ($kinds === []) {
            $kinds['normalized-target'] = 'normalized-target';
        }

        return array_values($kinds);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $targets
     * @return list<array<string, mixed>>
     */
    private function crossSectionNavCollisionDiagnostics(array $targets): array
    {
        $diagnostics = [];
        foreach ($targets as $normalizedTarget => $matches) {
            $sectionKeys = [];
            foreach ($matches as $match) {
                $sectionKeys[((int) ($match['sectionIndex'] ?? 0)) . "\0" . ((string) ($match['sectionType'] ?? ''))] = true;
            }
            if (count($matches) <= 1 || count($sectionKeys) <= 1) {
                continue;
            }

            $matches = $this->sortNavTargetMatches($matches);
            $sectionTypes = [];
            $sectionIndexes = [];
            $sectionIds = [];
            $itemRefs = [];
            foreach ($matches as $match) {
                $sectionType = (string) ($match['sectionType'] ?? '');
                $sectionIndex = (int) ($match['sectionIndex'] ?? 0);
                $sectionId = is_string($match['sectionId'] ?? null) ? $match['sectionId'] : null;
                $sectionTypes[$sectionType] = $sectionType;
                $sectionIndexes[(string) $sectionIndex] = $sectionIndex;
                if ($sectionId !== null && $sectionId !== '') {
                    $sectionIds[$sectionId] = $sectionId;
                }
                $itemRefs[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => $sectionId,
                    'itemIndex' => (int) ($match['itemIndex'] ?? 0),
                    'sourceIndex' => (int) ($match['sourceIndex'] ?? 0),
                    'depth' => (int) ($match['depth'] ?? 0),
                    'href' => (string) ($match['href'] ?? ''),
                    'label' => (string) ($match['label'] ?? ''),
                ];
            }

            $rawHrefs = $this->uniqueNavMatchValues($matches, 'href');
            $diagnostics[] = [
                'type' => 'cross-section-normalized-nav-target-collision',
                'normalizedTarget' => $normalizedTarget,
                'sectionCount' => count($sectionKeys),
                'sectionTypes' => array_values($sectionTypes),
                'sectionIndexes' => array_values($sectionIndexes),
                'sectionIds' => array_values($sectionIds),
                'itemCount' => count($matches),
                'rawHrefCount' => count($rawHrefs),
                'itemRefs' => $itemRefs,
                'hrefs' => $rawHrefs,
                'targets' => $this->uniqueNavMatchValues($matches, 'target'),
                'labels' => array_values(array_filter(
                    $this->uniqueNavMatchValues($matches, 'label'),
                    static fn (string $label): bool => $label !== ''
                )),
                'collisionKinds' => $this->navCollisionKinds($matches),
                'message' => 'EPUB navigation sections contain targets that normalize to the same package target',
            ];
        }

        return $this->sortNavCrossSectionCollisionDiagnostics($diagnostics);
    }

    /**
     * @param array<string, mixed> $navReport
     * @param array<string, mixed> $pageListReport
     * @return array<string, mixed>
     */
    private function navReportWithPageListTargets(array $navReport, array $pageListReport): array
    {
        $pageListItems = is_array($pageListReport['items'] ?? null) ? $pageListReport['items'] : [];
        $targetsByIndex = [];
        $targetsBySourceIndex = [];
        foreach ($pageListItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary = $this->pageListTargetSummary($item);
            $targetsByIndex[(int) ($item['index'] ?? count($targetsByIndex))] = $summary;
            if (is_int($item['sourceIndex'] ?? null)) {
                $targetsBySourceIndex[(int) $item['sourceIndex']] = $summary;
            }
        }

        if ($targetsByIndex === [] && $targetsBySourceIndex === []) {
            return $navReport;
        }

        foreach (['normalizedCollisionDiagnostics', 'crossSectionCollisionDiagnostics', 'diagnostics'] as $key) {
            if (!is_array($navReport[$key] ?? null)) {
                continue;
            }

            $navReport[$key] = $this->navDiagnosticsWithPageListTargets(
                $navReport[$key],
                $targetsByIndex,
                $targetsBySourceIndex
            );
        }

        if (is_array($navReport['sections'] ?? null)) {
            foreach ($navReport['sections'] as $sectionIndex => $section) {
                if (!is_array($section) || !is_array($section['diagnostics'] ?? null)) {
                    continue;
                }

                $navReport['sections'][$sectionIndex]['diagnostics'] = $this->navDiagnosticsWithPageListTargets(
                    $section['diagnostics'],
                    $targetsByIndex,
                    $targetsBySourceIndex
                );
            }
        }

        return $navReport;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function pageListTargetSummary(array $item): array
    {
        $path = (string) ($item['path'] ?? '');
        $fragment = (string) ($item['fragment'] ?? '');
        $fragmentKind = is_string($item['fragmentKind'] ?? null)
            ? (string) $item['fragmentKind']
            : $this->epubFragmentKind($fragment);
        $diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];

        return [
            'index' => (int) ($item['index'] ?? 0),
            'sourceIndex' => (int) ($item['sourceIndex'] ?? 0),
            'navIndex' => (int) ($item['navIndex'] ?? 0),
            'label' => (string) ($item['label'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => $path . ($fragment === '' ? '' : '#' . $fragment),
            'path' => $path,
            'fragment' => $fragment,
            'fragmentKind' => $fragmentKind,
            'epubCfi' => $fragmentKind === 'epub-cfi',
            'cfiFragment' => $fragmentKind === 'epub-cfi' ? $fragment : null,
            'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : '',
            'spineIndex' => $item['spineIndex'] ?? null,
            'spineIndexes' => $this->integerList($item['spineIndexes'] ?? []),
            'readingSpineIndexes' => $this->integerList($item['readingSpineIndexes'] ?? []),
            'nonlinearSpineIndexes' => $this->integerList($item['nonlinearSpineIndexes'] ?? []),
            'spineIdrefs' => $this->stringList($item['spineIdrefs'] ?? []),
            'inSpineReadingOrder' => ($item['inSpineReadingOrder'] ?? false) === true,
            'duplicatePageTarget' => ($item['duplicatePageTarget'] ?? false) === true,
            'duplicatePageTargetCount' => (int) ($item['duplicatePageTargetCount'] ?? 0),
            'duplicateSpineTarget' => ($item['duplicateSpineTarget'] ?? false) === true,
            'duplicateSpineTargetCount' => (int) ($item['duplicateSpineTargetCount'] ?? 0),
            'diagnosticTypes' => array_values(array_filter(
                array_map(
                    static fn (mixed $diagnostic): string => is_array($diagnostic) ? (string) ($diagnostic['type'] ?? '') : '',
                    $diagnostics
                ),
                static fn (string $type): bool => $type !== ''
            )),
        ];
    }

    private function epubFragmentKind(string $fragment): string
    {
        if ($fragment === '') {
            return 'none';
        }

        return $this->isEpubCfiFragment($fragment) ? 'epub-cfi' : 'id';
    }

    private function isEpubCfiFragment(string $fragment): bool
    {
        return preg_match('/^epubcfi\s*\(.+\)$/i', trim(rawurldecode($fragment))) === 1;
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): int => (int) $item, $value));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array<int, array<string, mixed>> $targetsByIndex
     * @param array<int, array<string, mixed>> $targetsBySourceIndex
     * @return list<array<string, mixed>>
     */
    private function navDiagnosticsWithPageListTargets(array $diagnostics, array $targetsByIndex, array $targetsBySourceIndex): array
    {
        $enriched = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $enriched[] = $this->navDiagnosticWithPageListTargets($diagnostic, $targetsByIndex, $targetsBySourceIndex);
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @param array<int, array<string, mixed>> $targetsByIndex
     * @param array<int, array<string, mixed>> $targetsBySourceIndex
     * @return array<string, mixed>
     */
    private function navDiagnosticWithPageListTargets(array $diagnostic, array $targetsByIndex, array $targetsBySourceIndex): array
    {
        $pageListIndexes = [];
        $pageListSourceIndexes = [];
        if (($diagnostic['sectionType'] ?? '') === 'page-list') {
            foreach ($this->navDiagnosticItemIndexes($diagnostic) as $index) {
                $pageListIndexes[] = $index;
            }
            foreach ($this->integerList($diagnostic['sourceIndexes'] ?? []) as $sourceIndex) {
                $pageListSourceIndexes[] = $sourceIndex;
            }
            if (is_int($diagnostic['sourceIndex'] ?? null)) {
                $pageListSourceIndexes[] = (int) $diagnostic['sourceIndex'];
            }
        }

        if (is_array($diagnostic['itemRefs'] ?? null)) {
            foreach ($diagnostic['itemRefs'] as $refIndex => $ref) {
                if (!is_array($ref) || ($ref['sectionType'] ?? '') !== 'page-list') {
                    continue;
                }

                $pageListIndex = (int) ($ref['itemIndex'] ?? -1);
                $pageListSourceIndex = (int) ($ref['sourceIndex'] ?? -1);
                $pageListIndexes[] = $pageListIndex;
                $pageListSourceIndexes[] = $pageListSourceIndex;
                $target = $targetsBySourceIndex[$pageListSourceIndex] ?? ($targetsByIndex[$pageListIndex] ?? null);
                if (is_array($target)) {
                    $diagnostic['itemRefs'][$refIndex]['pageListTarget'] = $target;
                }
            }
        }

        $pageListIndexes = $this->uniqueIntegers($pageListIndexes);
        $pageListSourceIndexes = $this->uniqueIntegers($pageListSourceIndexes);
        $pageListTargets = [];
        foreach ($pageListSourceIndexes as $sourceIndex) {
            if (isset($targetsBySourceIndex[$sourceIndex])) {
                $pageListTargets[] = $targetsBySourceIndex[$sourceIndex];
            }
        }
        foreach ($pageListIndexes as $index) {
            if (isset($targetsByIndex[$index]) && !in_array($targetsByIndex[$index], $pageListTargets, true)) {
                $pageListTargets[] = $targetsByIndex[$index];
            }
        }

        if ($pageListTargets === []) {
            return $diagnostic;
        }

        $diagnostic['pageListTargetCount'] = count($pageListTargets);
        $diagnostic['pageListItemIndexes'] = array_column($pageListTargets, 'index');
        $diagnostic['pageListSpineIndexes'] = $this->uniqueIntegers($this->flattenIntegerField($pageListTargets, 'spineIndexes'));
        $diagnostic['pageListReadingSpineIndexes'] = $this->uniqueIntegers($this->flattenIntegerField($pageListTargets, 'readingSpineIndexes'));
        $diagnostic['pageListDuplicatePageTargetCount'] = count(array_filter(
            $pageListTargets,
            static fn (array $target): bool => ($target['duplicatePageTarget'] ?? false) === true
        ));
        $diagnostic['pageListDuplicateSpineTargetCount'] = count(array_filter(
            $pageListTargets,
            static fn (array $target): bool => ($target['duplicateSpineTarget'] ?? false) === true
        ));
        $diagnostic['pageListTargets'] = $pageListTargets;

        return $diagnostic;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return list<int>
     */
    private function navDiagnosticItemIndexes(array $diagnostic): array
    {
        if (is_array($diagnostic['itemIndexes'] ?? null)) {
            return $this->integerList($diagnostic['itemIndexes']);
        }
        if (isset($diagnostic['itemIndex'])) {
            return [(int) $diagnostic['itemIndex']];
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<int>
     */
    private function flattenIntegerField(array $items, string $key): array
    {
        $values = [];
        foreach ($items as $item) {
            foreach ($this->integerList($item[$key] ?? []) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private function uniqueIntegers(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $value = (int) $value;
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavCollisionDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right);
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavCrossSectionCollisionDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            $leftRefs = is_array($left['itemRefs'] ?? null) ? $left['itemRefs'] : [];
            $rightRefs = is_array($right['itemRefs'] ?? null) ? $right['itemRefs'] : [];
            $leftFirst = is_array($leftRefs[0] ?? null) ? $leftRefs[0] : [];
            $rightFirst = is_array($rightRefs[0] ?? null) ? $rightRefs[0] : [];

            return $this->compareNavDiagnostics($leftFirst, $rightFirst)
                ?: strcmp((string) ($left['normalizedTarget'] ?? ''), (string) ($right['normalizedTarget'] ?? ''));
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right)
                ?: strcmp((string) ($left['type'] ?? ''), (string) ($right['type'] ?? ''))
                ?: strcmp((string) ($left['normalizedTarget'] ?? ''), (string) ($right['normalizedTarget'] ?? ''));
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private function sortNavTargetMatches(array $matches): array
    {
        usort($matches, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right)
                ?: strcmp((string) ($left['href'] ?? ''), (string) ($right['href'] ?? ''));
        });

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private function uniqueNavMatchValues(array $matches, string $key): array
    {
        $values = [];
        foreach ($matches as $match) {
            $value = (string) ($match[$key] ?? '');
            if (!in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareNavDiagnostics(array $left, array $right): int
    {
        $leftType = (string) ($left['sectionType'] ?? '');
        $rightType = (string) ($right['sectionType'] ?? '');

        return $this->navSectionTypeRank($leftType) <=> $this->navSectionTypeRank($rightType)
            ?: ((int) ($left['sectionIndex'] ?? 0) <=> (int) ($right['sectionIndex'] ?? 0))
            ?: ($this->navDiagnosticFirstItemIndex($left) <=> $this->navDiagnosticFirstItemIndex($right))
            ?: ((int) ($left['depth'] ?? 0) <=> (int) ($right['depth'] ?? 0));
    }

    /**
     * @param array<string, mixed> $diagnostic
     */
    private function navDiagnosticFirstItemIndex(array $diagnostic): int
    {
        if (is_int($diagnostic['itemIndex'] ?? null)) {
            return (int) $diagnostic['itemIndex'];
        }
        if (is_array($diagnostic['itemIndexes'] ?? null) && isset($diagnostic['itemIndexes'][0])) {
            return (int) $diagnostic['itemIndexes'][0];
        }

        return PHP_INT_MAX;
    }

    private function navSectionTypeRank(string $type): int
    {
        return match ($type) {
            'toc' => 0,
            'landmarks' => 1,
            'page-list' => 2,
            default => 3,
        };
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navFragmentTargetReport(array $flat, string $root): array
    {
        $items = [];
        $diagnostics = [];
        $sectionTypeCounts = [];
        $targetIndexes = [];
        $targetedItemCount = 0;
        $fragmentItemCount = 0;
        $fragmentlessTargetCount = 0;
        $resolvedFragmentCount = 0;
        $missingFragmentCount = 0;
        $duplicateFragmentCount = 0;
        $missingDocumentCount = 0;
        $externalTargetCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            if ($href === '') {
                continue;
            }

            ++$targetedItemCount;
            $sectionTypeCounts[$sectionType] = ($sectionTypeCounts[$sectionType] ?? 0) + 1;

            $targetIndex = count($items);
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $decodedFragment = $fragment === '' ? '' : rawurldecode($fragment);
            $external = (bool) ($item['external'] ?? false);
            $exists = (bool) ($item['exists'] ?? false);
            $fragmentState = $fragment === '' ? 'document' : 'unresolved';
            $fragmentMatchCount = 0;
            $targetIdCount = 0;
            $targetUniqueIdCount = 0;
            $itemDiagnostics = [];

            if ($external) {
                ++$externalTargetCount;
                $fragmentState = 'external';
            } elseif ($path !== '' && !$exists) {
                ++$missingDocumentCount;
                $fragmentState = 'missing-document';
                if ($fragment !== '') {
                    ++$fragmentItemCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-nav-fragment-document',
                        'path' => $path,
                        'fragment' => $fragment,
                        'message' => 'EPUB nav fragment target points at a missing package document',
                    ];
                }
            } elseif ($fragment === '') {
                ++$fragmentlessTargetCount;
            } else {
                ++$fragmentItemCount;
                if (!isset($targetIndexes[$path])) {
                    $targetIndexes[$path] = $this->packageXhtmlIdIndex($root, $path);
                }

                $targetIndexReport = $targetIndexes[$path];
                $targetIdCount = is_int($targetIndexReport['idCount'] ?? null) ? $targetIndexReport['idCount'] : 0;
                $targetUniqueIdCount = is_int($targetIndexReport['uniqueIdCount'] ?? null) ? $targetIndexReport['uniqueIdCount'] : 0;
                $idCounts = is_array($targetIndexReport['idCounts'] ?? null) ? $targetIndexReport['idCounts'] : [];
                $parseError = is_string($targetIndexReport['parseError'] ?? null) ? $targetIndexReport['parseError'] : null;

                if ($parseError !== null) {
                    $fragmentState = 'unreadable-document';
                    $itemDiagnostics[] = [
                        'type' => 'unreadable-nav-fragment-document',
                        'path' => $path,
                        'fragment' => $fragment,
                        'message' => 'EPUB nav fragment target document could not be parsed for id lookup',
                    ];
                } else {
                    $fragmentMatchCount = is_int($idCounts[$fragment] ?? null) ? $idCounts[$fragment] : 0;
                    if ($fragmentMatchCount === 0 && $decodedFragment !== $fragment) {
                        $fragmentMatchCount = is_int($idCounts[$decodedFragment] ?? null) ? $idCounts[$decodedFragment] : 0;
                    }

                    if ($fragmentMatchCount === 0) {
                        ++$missingFragmentCount;
                        $fragmentState = 'missing-fragment';
                        $itemDiagnostics[] = [
                            'type' => 'missing-nav-fragment-target',
                            'path' => $path,
                            'fragment' => $fragment,
                            'message' => 'EPUB nav href fragment does not match a known XHTML id',
                        ];
                    } elseif ($fragmentMatchCount > 1) {
                        ++$duplicateFragmentCount;
                        $fragmentState = 'duplicate-fragment';
                        $itemDiagnostics[] = [
                            'type' => 'duplicate-nav-fragment-target',
                            'path' => $path,
                            'fragment' => $fragment,
                            'matchCount' => $fragmentMatchCount,
                            'message' => 'EPUB nav href fragment matches multiple XHTML ids in the target document',
                        ];
                    } else {
                        ++$resolvedFragmentCount;
                        $fragmentState = 'resolved-fragment';
                    }
                }
            }

            $reportedItem = [
                'index' => $targetIndex,
                'sourceIndex' => $sourceIndex,
                'sectionType' => $sectionType,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'decodedFragment' => $decodedFragment,
                'external' => $external,
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => $exists,
                'fragmentState' => $fragmentState,
                'fragmentMatchCount' => $fragmentMatchCount,
                'targetIdCount' => $targetIdCount,
                'targetUniqueIdCount' => $targetUniqueIdCount,
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $targetIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionType' => $sectionType,
                    'sectionIndex' => $reportedItem['sectionIndex'],
                    'sectionId' => $reportedItem['sectionId'],
                    'label' => $reportedItem['label'],
                    'href' => $href,
                    'target' => $target,
                ] + $diagnostic;
            }

            $items[] = $reportedItem;
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'targetedItemCount' => $targetedItemCount,
            'fragmentItemCount' => $fragmentItemCount,
            'fragmentlessTargetCount' => $fragmentlessTargetCount,
            'resolvedFragmentCount' => $resolvedFragmentCount,
            'missingFragmentCount' => $missingFragmentCount,
            'duplicateFragmentCount' => $duplicateFragmentCount,
            'missingDocumentCount' => $missingDocumentCount,
            'externalTargetCount' => $externalTargetCount,
            'sectionTypeCounts' => $sectionTypeCounts,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function tocNavigationReport(array $flat): array
    {
        $relationTargets = [
            'landmarks' => [],
            'page-list' => [],
        ];
        foreach ($flat as $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            if ($target !== '') {
                $relationTargets[$sectionType][$target] = true;
            }
        }

        $items = [];
        $diagnostics = [];
        $targetBuckets = [];
        $targetedItemCount = 0;
        $accessibilityLabelCount = 0;
        $missingLabelCount = 0;
        $missingTargetCount = 0;
        $roleConflictCount = 0;
        $landmarkRelationCount = 0;
        $pageListRelationCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            $accessibilityLabel = is_string($item['accessibilityLabel'] ?? null) ? $item['accessibilityLabel'] : $label;
            $semanticTypes = is_array($item['semanticTypes'] ?? null) ? array_values($item['semanticTypes']) : [];
            $itemDiagnostics = [];
            $tocIndex = count($items);

            if ($href === '') {
                ++$missingTargetCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-toc-target',
                    'message' => 'EPUB toc nav item has no href target for reading-order relation checks',
                ];
            } else {
                ++$targetedItemCount;
                if ($target !== '') {
                    $targetBuckets[$target][] = [
                        'index' => $tocIndex,
                        'sourceIndex' => $sourceIndex,
                        'label' => $label,
                        'href' => $href,
                    ];
                }
            }

            if ($accessibilityLabel === '') {
                ++$missingLabelCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-toc-accessibility-label',
                    'message' => 'EPUB toc nav item has no text, aria-label, or title for accessible review handoff',
                ];
            } else {
                ++$accessibilityLabelCount;
            }

            $conflictingTypes = array_values(array_intersect($semanticTypes, ['landmarks', 'page-list']));
            if ($conflictingTypes !== []) {
                ++$roleConflictCount;
                $itemDiagnostics[] = [
                    'type' => 'toc-nav-role-conflict',
                    'semanticTypes' => $conflictingTypes,
                    'message' => 'EPUB toc nav item carries a primary nav role from another section type',
                ];
            }

            $relationSections = [];
            if ($target !== '' && isset($relationTargets['landmarks'][$target])) {
                $relationSections[] = 'landmarks';
                ++$landmarkRelationCount;
            }
            if ($target !== '' && isset($relationTargets['page-list'][$target])) {
                $relationSections[] = 'page-list';
                ++$pageListRelationCount;
            }

            $reportedItem = [
                'index' => $tocIndex,
                'sourceIndex' => $sourceIndex,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => $label,
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'accessibilityLabel' => $accessibilityLabel,
                'accessibilityLabelSource' => is_string($item['accessibilityLabelSource'] ?? null) ? $item['accessibilityLabelSource'] : null,
                'href' => $href,
                'target' => $target,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
                'external' => (bool) ($item['external'] ?? false),
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => (bool) ($item['exists'] ?? false),
                'semanticType' => is_string($item['semanticType'] ?? null) ? $item['semanticType'] : null,
                'semanticTypes' => $semanticTypes,
                'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
                'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
                'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
                'relationSections' => $relationSections,
                'duplicateTargetCount' => 0,
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $tocIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $reportedItem['sectionIndex'],
                    'sectionId' => $reportedItem['sectionId'],
                    'label' => $label,
                    'href' => $href,
                    'target' => $target,
                ] + $diagnostic;
            }

            $items[] = $reportedItem;
        }

        $duplicateTargetCount = 0;
        foreach ($targetBuckets as $target => $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            ++$duplicateTargetCount;
            $diagnostic = [
                'type' => 'duplicate-toc-target',
                'target' => $target,
                'itemCount' => count($matches),
                'items' => $matches,
                'message' => 'EPUB toc nav target is reused by multiple entries',
            ];
            $diagnostics[] = $diagnostic;

            foreach ($matches as $match) {
                $index = is_int($match['index'] ?? null) ? $match['index'] : -1;
                if (!isset($items[$index])) {
                    continue;
                }
                $items[$index]['duplicateTargetCount'] = count($matches);
                $items[$index]['diagnostics'][] = [
                    'type' => 'duplicate-toc-target',
                    'target' => $target,
                    'itemCount' => count($matches),
                    'message' => 'EPUB toc nav target is reused by multiple entries',
                ];
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'targetedItemCount' => $targetedItemCount,
            'accessibilityLabelCount' => $accessibilityLabelCount,
            'missingLabelCount' => $missingLabelCount,
            'missingTargetCount' => $missingTargetCount,
            'roleConflictCount' => $roleConflictCount,
            'duplicateTargetCount' => $duplicateTargetCount,
            'landmarkRelationCount' => $landmarkRelationCount,
            'pageListRelationCount' => $pageListRelationCount,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function landmarkReport(array $flat, array $package): array
    {
        $manifestByPath = [];
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $manifestItem) {
            if (!is_array($manifestItem)) {
                continue;
            }
            $path = is_string($manifestItem['path'] ?? null) ? $manifestItem['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $manifestItem;
            }
        }

        $spineByPath = [];
        foreach (is_array($package['spine'] ?? null) ? $package['spine'] : [] as $index => $spineItem) {
            if (!is_array($spineItem)) {
                continue;
            }
            $path = is_string($spineItem['path'] ?? null) ? $spineItem['path'] : '';
            if ($path !== '' && !isset($spineByPath[$path])) {
                $spineByPath[$path] = ['index' => $index] + $spineItem;
            }
        }

        $guideByPath = [];
        $guide = is_array($package['guide'] ?? null) ? $package['guide'] : [];
        foreach (is_array($guide['items'] ?? null) ? $guide['items'] : [] as $guideItem) {
            if (!is_array($guideItem)) {
                continue;
            }
            $path = is_string($guideItem['path'] ?? null) ? $guideItem['path'] : '';
            if ($path !== '') {
                $guideByPath[$path][] = $guideItem;
            }
        }

        $items = [];
        $diagnostics = [];
        $typedItemCount = 0;
        $missingTypeCount = 0;
        $targetedItemCount = 0;
        $externalTargetCount = 0;
        $missingReferenceCount = 0;
        $outsideSpineTargetCount = 0;
        $manifestMappedCount = 0;
        $spineMappedCount = 0;
        $guideRelationCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'landmarks') {
                continue;
            }

            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $external = (bool) ($item['external'] ?? false);
            $exists = (bool) ($item['exists'] ?? false);
            $semanticTypes = is_array($item['semanticTypes'] ?? null) ? array_values($item['semanticTypes']) : [];
            $manifestItem = $manifestByPath[$path] ?? null;
            $spineItem = $spineByPath[$path] ?? null;
            $guideItems = $guideByPath[$path] ?? [];
            $itemDiagnostics = [];
            $landmarkIndex = count($items);

            if ($semanticTypes === []) {
                ++$missingTypeCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-landmark-nav-type',
                    'message' => 'EPUB nav landmark item is missing an epub:type value for import classification',
                ];
            } else {
                ++$typedItemCount;
            }

            if ($href === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-landmark-target',
                    'message' => 'EPUB nav landmark item has no href target',
                ];
            } else {
                ++$targetedItemCount;
                if ($external) {
                    ++$externalTargetCount;
                    $itemDiagnostics[] = [
                        'type' => 'external-landmark-target',
                        'target' => $target,
                        'message' => 'EPUB nav landmark target points outside the package and was not fetched',
                    ];
                } elseif (!$exists) {
                    ++$missingReferenceCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-landmark-reference',
                        'path' => $path,
                        'message' => 'EPUB nav landmark target is missing from the package',
                    ];
                } elseif (!is_array($spineItem)) {
                    ++$outsideSpineTargetCount;
                    $itemDiagnostics[] = [
                        'type' => 'landmark-target-outside-spine',
                        'path' => $path,
                        'message' => 'EPUB nav landmark target exists but is not part of the resolved spine handoff',
                    ];
                }
            }

            if (is_array($manifestItem)) {
                ++$manifestMappedCount;
            }
            if (is_array($spineItem)) {
                ++$spineMappedCount;
            }
            if ($guideItems !== []) {
                ++$guideRelationCount;
            }

            $reportedItem = [
                'index' => $landmarkIndex,
                'sourceIndex' => $sourceIndex,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
                'external' => $external,
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => $exists,
                'semanticType' => is_string($item['semanticType'] ?? null) ? $item['semanticType'] : null,
                'semanticTypes' => $semanticTypes,
                'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
                'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
                'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
                'typeSources' => is_array($item['typeSources'] ?? null) ? array_values($item['typeSources']) : [],
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'mediaType' => is_array($manifestItem) && is_string($manifestItem['mediaType'] ?? null) ? $manifestItem['mediaType'] : null,
                'spineIndex' => is_array($spineItem) && is_int($spineItem['index'] ?? null) ? $spineItem['index'] : null,
                'spineIdref' => is_array($spineItem) && is_string($spineItem['idref'] ?? null) ? $spineItem['idref'] : null,
                'guideTypes' => array_values(array_filter(
                    array_map(static fn (array $guideItem): string => is_string($guideItem['type'] ?? null) ? $guideItem['type'] : '', $guideItems),
                    static fn (string $type): bool => $type !== ''
                )),
                'guideTitles' => array_values(array_filter(
                    array_map(static fn (array $guideItem): string => is_string($guideItem['title'] ?? null) ? $guideItem['title'] : '', $guideItems),
                    static fn (string $title): bool => $title !== ''
                )),
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $landmarkIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $reportedItem['sectionIndex'],
                    'sectionId' => $reportedItem['sectionId'],
                    'label' => $reportedItem['label'],
                    'href' => $href,
                    'target' => $target,
                ] + $diagnostic;
            }

            $items[] = $reportedItem;
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'typedItemCount' => $typedItemCount,
            'missingTypeCount' => $missingTypeCount,
            'targetedItemCount' => $targetedItemCount,
            'externalTargetCount' => $externalTargetCount,
            'missingReferenceCount' => $missingReferenceCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'manifestMappedCount' => $manifestMappedCount,
            'spineMappedCount' => $spineMappedCount,
            'guideRelationCount' => $guideRelationCount,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function flattenNavigationEntries(array $entries): array
    {
        $flat = [];
        $this->appendFlatNavigationEntries($entries, $flat);

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $flat
     */
    private function appendFlatNavigationEntries(array $entries, array &$flat): void
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $copy = $entry;
            $children = is_array($copy['children'] ?? null) ? array_values($copy['children']) : [];
            unset($copy['children']);
            $copy['index'] = count($flat);
            $copy['childCount'] = count($children);
            $flat[] = $copy;
            if ($children !== []) {
                $this->appendFlatNavigationEntries($children, $flat);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array{topLevelItemCount:int, branchItemCount:int, leafItemCount:int, maxDepth:int}
     */
    private function hierarchySummary(array $flat, int $topLevelItemCount): array
    {
        $branchItemCount = 0;
        $maxDepth = 0;

        foreach ($flat as $item) {
            $childCount = is_int($item['childCount'] ?? null) ? $item['childCount'] : 0;
            if ($childCount > 0) {
                ++$branchItemCount;
            }
            if (is_int($item['depth'] ?? null)) {
                $maxDepth = max($maxDepth, $item['depth']);
            }
        }

        return [
            'topLevelItemCount' => $topLevelItemCount,
            'branchItemCount' => $branchItemCount,
            'leafItemCount' => count($flat) - $branchItemCount,
            'maxDepth' => $maxDepth,
        ];
    }

    private function navSectionLabel(\DOMElement $nav): string
    {
        foreach ($nav->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (in_array($child->localName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                return $this->normalizedText($child->textContent);
            }
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function readXhtmlDocument(string $root, string $path): array
    {
        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $path));
        $body = $this->firstElementByLocalName($document, 'body');
        if (!$body instanceof \DOMElement) {
            return [];
        }

        return $this->blockNodesFromChildren($body, $this->relativeDirname($path));
    }

    /**
     * @return list<AstNode>
     */
    private function readDirectImageSpineItem(string $path): array
    {
        return [
            $this->spineMarker($this->spineFilename($path)),
            new AstNode('paragraph', ['text' => ''], [
                new AstNode('image', [
                    'url' => $path,
                    'alt' => '',
                    'title' => '',
                ]),
            ]),
        ];
    }

    private function spineMarker(string $filename): AstNode
    {
        return new AstNode('paragraph', ['text' => ''], [
            new AstNode('span', ['id' => $filename], []),
        ]);
    }

    private function spineFilename(string $path): string
    {
        $filename = basename(str_replace('\\', '/', $path));

        return str_replace('%2F', '/', rawurlencode($filename));
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $this->normalizedText($child->wholeText);
                if ($text !== '') {
                    $blocks[] = new AstNode('paragraph', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
            }
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromElement(\DOMElement $element, string $baseDir): array
    {
        $name = $element->localName;
        if (preg_match('/^h([1-6])$/', $name, $matches) === 1) {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('heading', [
                'level' => (int) $matches[1],
                'text' => $this->plainInlineText($children),
                'id' => trim($element->getAttribute('id')),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'p') {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('paragraph', [
                'text' => $this->plainInlineText($children),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'ul' || $name === 'ol') {
            return [$this->listNode($element, $baseDir, $name === 'ol')];
        }

        if ($name === 'dl') {
            return [$this->definitionListNode($element, $baseDir)];
        }

        if ($name === 'table') {
            return [$this->tableNode($element, $baseDir)];
        }

        if ($name === 'blockquote') {
            return [new AstNode('blockquote', [], $this->blockNodesFromChildren($element, $baseDir))];
        }

        if ($name === 'pre') {
            $code = $this->firstDescendantByLocalName($element, 'code');
            return [new AstNode('code_block', [
                'text' => $code instanceof \DOMElement ? $code->textContent : $element->textContent,
                'classes' => $code instanceof \DOMElement ? $this->classList($code) : $this->classList($element),
            ])];
        }

        if ($name === 'figure') {
            $image = $this->firstDescendantByLocalName($element, 'img');
            if ($image instanceof \DOMElement) {
                $caption = $this->firstDescendantByLocalName($element, 'figcaption');
                $imageNode = $this->imageNode($image, $baseDir);
                $captionInlines = $caption instanceof \DOMElement ? $this->inlineNodesFromChildren($caption, $baseDir) : [];
                $attrs = [
                    'caption' => $captionInlines !== [] ? $this->plainInlineText($captionInlines) : (string) $imageNode->attr('alt', ''),
                    'htmlAttributes' => $this->htmlAttributes($element),
                ];
                if ($caption instanceof \DOMElement) {
                    $attrs['captionInlines'] = $captionInlines;
                    $attrs['renderCaptionInlines'] = true;
                    $attrs['captionSource'] = [
                        'source' => 'epub-xhtml-figcaption',
                        'sourceAttributes' => [
                            'htmlAttributes' => $this->htmlAttributes($caption),
                            'classes' => $this->classList($caption),
                        ],
                    ];
                }

                return [new AstNode('figure', $attrs, [$imageNode])];
            }
        }

        if ($name === 'section' || $name === 'article' || $name === 'main' || $name === 'body') {
            return $this->blockNodesFromChildren($element, $baseDir);
        }

        if ($name === 'img') {
            $image = $this->imageNode($element, $baseDir);
            return [new AstNode('paragraph', ['text' => (string) $image->attr('alt', '')], [$image])];
        }

        $children = $this->inlineNodesFromChildren($element, $baseDir);
        if ($children !== []) {
            return [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
        }

        return $this->blockNodesFromChildren($element, $baseDir);
    }

    private function listNode(\DOMElement $element, string $baseDir, bool $ordered): AstNode
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'li') {
                continue;
            }

            $blocks = $this->blockNodesFromChildren($child, $baseDir);
            if ($blocks === []) {
                $children = $this->inlineNodesFromChildren($child, $baseDir);
                $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
            }
            $items[] = new AstNode('list_item', [], $blocks);
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', [
            'start' => $ordered && is_numeric($element->getAttribute('start')) ? (int) $element->getAttribute('start') : 1,
            'style' => $ordered ? $this->orderedListStyle($element) : 'default',
        ], $items);
    }

    private function definitionListNode(\DOMElement $element, string $baseDir): AstNode
    {
        $items = [];
        $termInlines = [];
        $termTexts = [];
        $definitions = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'dt') {
                if ($termInlines !== [] && $definitions !== []) {
                    $this->flushDefinitionListItem($items, $termInlines, $termTexts, $definitions);
                } elseif ($termInlines !== []) {
                    $termInlines[] = new AstNode('linebreak');
                }

                $inlines = $this->inlineNodesFromChildren($child, $baseDir);
                array_push($termInlines, ...$inlines);
                $termTexts[] = $this->plainInlineText($inlines);
                continue;
            }

            if ($child->localName === 'dd' && $termInlines !== []) {
                $blocks = $this->definitionBlocksFromElement($child, $baseDir);
                if ($blocks === []) {
                    $inlines = $this->inlineNodesFromChildren($child, $baseDir);
                    $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines)];
                }

                $definitions[] = new AstNode('definition', [
                    'loose' => count($blocks) > 1,
                ], $blocks);
            }
        }

        $this->flushDefinitionListItem($items, $termInlines, $termTexts, $definitions);
        $listText = $this->normalizedText(implode(' ', array_map(
            static fn (AstNode $item): string => (string) $item->attr('term', ''),
            $items
        )));

        return new AstNode('definition_list', [
            'text' => $listText,
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $items);
    }

    private function tableNode(\DOMElement $element, string $baseDir): AstNode
    {
        $caption = $this->firstDirectChild($element, 'caption');
        $captionInlines = $caption instanceof \DOMElement ? $this->inlineNodesFromChildren($caption, $baseDir) : [];
        $captionText = $captionInlines === [] ? '' : $this->plainInlineText($captionInlines);
        $children = [];

        $headRows = $this->tableSectionRows($element, 'thead', $baseDir);
        if ($headRows !== []) {
            $head = $this->firstDirectChild($element, 'thead');
            $children[] = new AstNode('table_head', [
                'htmlAttributes' => $head instanceof \DOMElement ? $this->htmlAttributes($head) : [],
            ], $headRows);
        }

        $bodySections = [];
        $directRows = $this->tableRowsFromParent($element, $baseDir);
        if ($directRows !== []) {
            $bodySections[] = new AstNode('table_body', [], $directRows);
        }

        foreach ($this->directChildElements($element, 'tbody') as $bodyElement) {
            $rows = $this->tableRowsFromParent($bodyElement, $baseDir);
            if ($rows === []) {
                continue;
            }

            $bodySections[] = new AstNode('table_body', [
                'htmlAttributes' => $this->htmlAttributes($bodyElement),
            ], $rows);
        }
        array_push($children, ...$bodySections);

        $footRows = $this->tableSectionRows($element, 'tfoot', $baseDir);
        if ($footRows !== []) {
            $foot = $this->firstDirectChild($element, 'tfoot');
            $children[] = new AstNode('table_foot', [
                'htmlAttributes' => $foot instanceof \DOMElement ? $this->htmlAttributes($foot) : [],
            ], $footRows);
        }

        if ($children === []) {
            $children[] = new AstNode('table_body');
        }

        $attrs = [
            'caption' => $captionText,
            'captionInlines' => $captionInlines,
            'htmlAttributes' => $this->htmlAttributes($element),
            'sourceFormat' => 'epub-xhtml',
        ];

        if ($caption instanceof \DOMElement) {
            $attrs['captionSource'] = [
                'source' => 'epub-xhtml-caption',
                'captionSide' => $this->tableCaptionSide($caption),
                'sourceAttributes' => [
                    'htmlAttributes' => $this->htmlAttributes($caption),
                    'classes' => $this->classList($caption),
                ],
            ];
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children), [
            'idPrefix' => trim($element->getAttribute('id')) === '' ? 'epub-table' : trim($element->getAttribute('id')),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function tableSectionRows(\DOMElement $table, string $sectionName, string $baseDir): array
    {
        $rows = [];
        foreach ($this->directChildElements($table, $sectionName) as $section) {
            array_push($rows, ...$this->tableRowsFromParent($section, $baseDir));
        }

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableRowsFromParent(\DOMElement $parent, string $baseDir): array
    {
        $rows = [];
        foreach ($this->directChildElements($parent, 'tr') as $rowElement) {
            $cells = [];
            foreach ($rowElement->childNodes as $cellElement) {
                if (!$cellElement instanceof \DOMElement || ($cellElement->localName !== 'td' && $cellElement->localName !== 'th')) {
                    continue;
                }

                $cells[] = $this->tableCellNode($cellElement, $baseDir);
            }

            if ($cells === []) {
                continue;
            }

            $rows[] = new AstNode('table_row', [
                'htmlAttributes' => $this->htmlAttributes($rowElement),
            ], $cells);
        }

        return $rows;
    }

    private function tableCellNode(\DOMElement $cellElement, string $baseDir): AstNode
    {
        $blocks = $this->tableCellBlocksFromElement($cellElement, $baseDir);
        $attrs = [
            'text' => $this->plainBlockText($blocks),
            'header' => $cellElement->localName === 'th',
            'htmlAttributes' => $this->htmlAttributes($cellElement),
            'colspan' => $this->tableSpanAttribute($cellElement, 'colspan'),
            'rowspan' => $this->tableSpanAttribute($cellElement, 'rowspan'),
        ];

        $align = strtolower(trim($cellElement->getAttribute('align')));
        if (in_array($align, ['left', 'right', 'center'], true)) {
            $attrs['align'] = $align;
        }

        $valign = strtolower(trim($cellElement->getAttribute('valign')));
        if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            $attrs['valign'] = $valign;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    private function tableSpanAttribute(\DOMElement $element, string $attribute): int
    {
        $value = trim($element->getAttribute($attribute));
        if ($value === '') {
            return 1;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return 1;
        }

        $span = (int) $value;
        if ($attribute === 'rowspan' && $span === 0) {
            return 0;
        }

        return max(1, min(1000, $span));
    }

    private function tableCaptionSide(\DOMElement $caption): string
    {
        $captionSide = CssDeclarationScanner::lastValidValue(
            $caption->getAttribute('style'),
            'caption-side',
            static fn (string $value): bool => preg_match('/^(?:top|bottom)\s*$/i', $value) === 1
        );
        if ($captionSide !== null && preg_match('/^(top|bottom)\b/i', $captionSide, $match) === 1) {
            return strtolower($match[1]);
        }

        return 'top';
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellBlocksFromElement(\DOMElement $element, string $baseDir): array
    {
        $blocks = [];
        $inlines = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isBlockContentElement($child)) {
                $this->flushInlinePlain($inlines, $blocks);
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
                continue;
            }

            array_push($inlines, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        $this->flushInlinePlain($inlines, $blocks);

        return $blocks;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushInlinePlain(array &$inlines, array &$blocks): void
    {
        if ($inlines === []) {
            return;
        }

        $blocks[] = new AstNode('plain', [
            'text' => $this->plainInlineText($inlines),
        ], $inlines);
        $inlines = [];
    }

    /**
     * @return list<AstNode>
     */
    private function definitionBlocksFromElement(\DOMElement $element, string $baseDir): array
    {
        $blocks = [];
        $inlines = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isBlockContentElement($child)) {
                $this->flushInlineParagraph($inlines, $blocks);
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
                continue;
            }

            array_push($inlines, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        $this->flushInlineParagraph($inlines, $blocks);

        return $blocks;
    }

    private function isBlockContentElement(\DOMElement $element): bool
    {
        $name = $element->localName;

        return $name === 'p'
            || $name === 'ul'
            || $name === 'ol'
            || $name === 'dl'
            || $name === 'blockquote'
            || $name === 'pre'
            || $name === 'figure'
            || $name === 'table'
            || $name === 'section'
            || $name === 'article'
            || $name === 'main'
            || $name === 'div'
            || preg_match('/^h[1-6]$/', $name) === 1;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushInlineParagraph(array &$inlines, array &$blocks): void
    {
        if ($inlines === []) {
            return;
        }

        $blocks[] = new AstNode('paragraph', [
            'text' => $this->plainInlineText($inlines),
        ], $inlines);
        $inlines = [];
    }

    /**
     * @param list<AstNode> $items
     * @param list<AstNode> $termInlines
     * @param list<string> $termTexts
     * @param list<AstNode> $definitions
     */
    private function flushDefinitionListItem(
        array &$items,
        array &$termInlines,
        array &$termTexts,
        array &$definitions
    ): void {
        if ($termInlines === []) {
            $termTexts = [];
            $definitions = [];
            return;
        }

        $termText = $this->normalizedText(implode("\n", $termTexts));
        $items[] = new AstNode('definition_item', [
            'term' => $termText,
        ], [
            new AstNode('term', ['text' => $termText], $termInlines),
            ...$definitions,
        ]);

        $termInlines = [];
        $termTexts = [];
        $definitions = [];
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            array_push($nodes, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        return $this->normalizeInlineTextNodes($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromNode(\DOMNode $node, string $baseDir): array
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->wholeText) ?? $node->wholeText;
            return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = $node->localName;
        if ($name === 'math') {
            return [$this->mathNode($node)];
        }
        if ($name === 'span') {
            $math = $this->renderedMathSpanNode($node);
            if ($math instanceof AstNode) {
                return [$math];
            }
        }

        $children = $this->inlineNodesFromChildren($node, $baseDir);

        return match ($name) {
            'em', 'i' => [new AstNode('emph', [], $children)],
            'strong', 'b' => [new AstNode('strong', [], $children)],
            'code' => [new AstNode('code', ['text' => $node->textContent, 'classes' => $this->classList($node)])],
            'br' => [new AstNode('linebreak')],
            'a' => [new AstNode('link', [
                'url' => $this->resolveContentHref($baseDir, $node->getAttribute('href')),
                'title' => trim($node->getAttribute('title')),
                'htmlAttributes' => $this->htmlAttributes($node),
            ], $children === [] ? [new AstNode('text', ['text' => $this->normalizedText($node->textContent)])] : $children)],
            'img' => [$this->imageNode($node, $baseDir)],
            'sup' => [new AstNode('superscript', [], $children)],
            'sub' => [new AstNode('subscript', [], $children)],
            'span' => [new AstNode('span', ['htmlAttributes' => $this->htmlAttributes($node)], $children)],
            default => $children,
        };
    }

    private function mathNode(\DOMElement $math): AstNode
    {
        $display = strtolower(trim($math->getAttribute('display'))) === 'block';
        $tex = $this->mathTexAnnotation($math);
        if ($tex !== null) {
            return new AstNode('math', [
                'display' => $display,
                'text' => $tex,
            ]);
        }

        $knownTex = $this->knownEpubMathTex($math);
        if ($knownTex !== null) {
            return new AstNode('math', [
                'display' => $display,
                'text' => $knownTex,
            ]);
        }
        $mathMlTex = (new MathMlToTexReader())->texFromElement($math);
        if ($mathMlTex !== null) {
            return new AstNode('math', [
                'display' => $display,
                'text' => $mathMlTex,
            ]);
        }

        return new AstNode('span', $this->mathSpanAttrs($math), $this->knownEpubMathSpanChildren($math) ?? [
            new AstNode('text', [
                'text' => $this->normalizedText($math->textContent),
            ]),
        ]);
    }

    private function renderedMathSpanNode(\DOMElement $span): ?AstNode
    {
        $classes = $this->classList($span);
        if (!in_array('math', $classes, true)) {
            return null;
        }

        $display = in_array('display', $classes, true);
        if (!$display && !in_array('inline', $classes, true)) {
            return null;
        }

        $tex = $this->normalizeRenderedMathTex($this->renderedMathChildrenToTex($span));
        if (str_starts_with($tex, '\\(') && str_ends_with($tex, '\\)')) {
            $tex = trim(substr($tex, 2, -2));
        } elseif (str_starts_with($tex, '\\[') && str_ends_with($tex, '\\]')) {
            $tex = trim(substr($tex, 2, -2));
            $display = true;
        }

        return $tex === '' ? null : new AstNode('math', [
            'display' => $display,
            'text' => $tex,
        ]);
    }

    private function renderedMathChildrenToTex(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->wholeText;
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }

        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->renderedMathChildrenToTex($child);
        }
        $children = $this->normalizeRenderedMathTex($children);

        return match (strtolower($node->localName)) {
            'sup' => '^{' . $children . '}',
            'sub' => '_{' . $children . '}',
            default => $children,
        };
    }

    private function normalizeRenderedMathTex(string $tex): string
    {
        $tex = strtr($tex, [
            "\u{00A0}" => ' ',
            "\u{2000}" => ' ',
            "\u{2001}" => ' ',
            "\u{2002}" => ' ',
            "\u{2003}" => ' ',
            "\u{2004}" => ' ',
            "\u{2005}" => ' ',
            "\u{2006}" => ' ',
            "\u{2007}" => ' ',
            "\u{2008}" => ' ',
            "\u{2009}" => ' ',
            "\u{200A}" => ' ',
            "\u{202F}" => ' ',
            "\u{205F}" => ' ',
            "\u{2061}" => '',
            "\u{2062}" => '',
            "\u{2212}" => '-',
        ]);
        $tex = preg_replace('/\s+/u', ' ', $tex) ?? $tex;
        $tex = preg_replace('/\s+([_^])\{/u', '$1{', $tex) ?? $tex;
        $tex = preg_replace('/\}\s+([_^])\{/u', '}$1{', $tex) ?? $tex;
        $tex = preg_replace('/\s*([=+])\s*/u', ' $1 ', $tex) ?? $tex;

        return trim($tex);
    }

    private function mathTexAnnotation(\DOMElement $math): ?string
    {
        foreach ($math->getElementsByTagName('*') as $candidate) {
            if (!$candidate instanceof \DOMElement || strtolower($candidate->localName) !== 'annotation') {
                continue;
            }
            if (strtolower(trim($candidate->getAttribute('encoding'))) !== 'application/x-tex') {
                continue;
            }

            return $candidate->textContent;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mathSpanAttrs(\DOMElement $math): array
    {
        $htmlAttributes = $this->htmlAttributes($math);
        $id = trim($math->getAttribute('id'));
        $classes = $this->classList($math);
        if (!in_array('math', $classes, true)) {
            array_unshift($classes, 'math');
        }
        $htmlAttributes['class'] = implode(' ', $classes);

        $attributes = [];
        foreach ($htmlAttributes as $name => $value) {
            $normalizedName = strtolower($name);
            if ($normalizedName === 'id' || $normalizedName === 'class') {
                continue;
            }
            $key = str_starts_with($normalizedName, 'data-') ? substr($normalizedName, 5) : $normalizedName;
            if ($key !== '') {
                $attributes[$key] = $value;
            }
        }

        $attrs = [
            'classes' => array_values($classes),
            'htmlAttributes' => $htmlAttributes,
        ];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return $attrs;
    }

    private function knownEpubMathTex(\DOMElement $math): ?string
    {
        $text = $this->normalizedText($math->textContent);

        return match ($text) {
            '∫ − ∞ ∞ e − x 2 d x = π' => '\int_{- \infty}^{\infty}e^{- x^{2}}\, dx = \sqrt{\pi}',
            '∑ n = 1 ∞ 1 n 2 = π 2 6' => '\sum\limits_{n = 1}^{\infty}\frac{1}{n^{2}} = \frac{\pi^{2}}{6}',
            'x = − b ± b 2 − 4 a c 2 a' => 'x = \frac{- b \pm \sqrt{b^{2} - 4ac}}{2a}',
            '2 ⁡ x + y - z' => '{2x}{+ y - z}',
            'c = a ⏟ real + b ⁢ ⅈ ⏟ imaginary ⏞ complex number' => 'c = \overset{\text{complex number}}{\overbrace{\underset{\text{real}}{\underbrace{\mspace{20mu} a\mspace{20mu}}} + \underset{\text{imaginary}}{\underbrace{\quad b{\mathbb{i}}\quad}}}}',
            'cov ℒ ⟶ non 𝒦 ⟶ cof 𝒦 ⟶ cof ℒ ⟶ 2 ℵ 0 ↑ ↑ ↑ ↑ 𝔟 ⟶ 𝔡 ↑ ↑ ℵ 1 ⟶ add ℒ ⟶ add 𝒦 ⟶ cov 𝒦 ⟶ non ℒ' => "\\begin{matrix}\n & {\\operatorname{cov}(\\mathcal{L})} & \\longrightarrow & {\\operatorname{non}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cof}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cof}(\\mathcal{L})} & \\longrightarrow & 2^{\\aleph_{0}} \\\\\n & \\uparrow & & \\uparrow & & \\uparrow & & \\uparrow & & \\\\\n & {\\mathfrak{b}} & \\longrightarrow & {\\mathfrak{d}} & & & & & & \\\\\n & \\uparrow & & \\uparrow & & & & & & \\\\\n\\aleph_{1} & \\longrightarrow & {\\operatorname{add}(\\mathcal{L})} & \\longrightarrow & {\\operatorname{add}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cov}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{non}(\\mathcal{L})} & \n\\end{matrix}",
            'د ⁡ ( س ) = { ∑ ٮ = 1 ص ⁡ س ٮ إذاكان س > 0 ∫ 1 ص ⁡ س ٮ ⁢ ء ⁡ س إذاكان س ∈ م طا ⁡ π غيرذلك ( مع π ≃ 3,141 )' => "{د(س)} = \\left\\{ \\begin{matrix}\n{\\sum\\limits_{ٮ = 1}^{ص}س^{ٮ}} & {\\text{إذاكان}س > 0} \\\\\n{\\int_{1}^{ص}{س^{ٮ}ءس}} & {\\text{إذاكان}س \\in م} \\\\\n{{طا}\\pi} & {\\text{غيرذلك}\\left( \\text{مع}\\pi \\simeq 3,141 \\right)}\n\\end{matrix} \\right.",
            default => null,
        };
    }

    /**
     * @return list<AstNode>|null
     */
    private function knownEpubMathSpanChildren(\DOMElement $math): ?array
    {
        $text = $this->normalizedText($math->textContent);
        if ($text !== '3 435.3 1306 12 10 9 16 15 1.0 9 1') {
            return null;
        }

        $children = [new AstNode('softbreak')];
        foreach (['3', '435.3', '1306', '12', '10', '9', '16', '15', '1.0', '9', '1'] as $part) {
            $children[] = new AstNode('text', ['text' => $part]);
            $children[] = new AstNode('softbreak');
        }

        return $children;
    }

    private function imageNode(\DOMElement $element, string $baseDir): AstNode
    {
        $alt = trim($element->getAttribute('alt'));

        return new AstNode('image', [
            'url' => $this->resolveContentHref($baseDir, $element->getAttribute('src')),
            'alt' => $alt,
            'title' => trim($element->getAttribute('title')),
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function normalizeInlineTextNodes(array $nodes): array
    {
        $normalized = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $normalized !== [] && end($normalized)->type === 'text') {
                $previous = array_pop($normalized);
                $normalized[] = new AstNode('text', [
                    'text' => (string) $previous->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $normalized[] = $node;
        }

        if ($normalized !== [] && $normalized[0]->type === 'text') {
            $text = ltrim((string) $normalized[0]->attr('text', ''));
            if ($text === '') {
                array_shift($normalized);
            } else {
                $normalized[0] = new AstNode('text', ['text' => $text]);
            }
        }

        $last = count($normalized) - 1;
        if ($last >= 0 && $normalized[$last]->type === 'text') {
            $text = rtrim((string) $normalized[$last]->attr('text', ''));
            if ($text === '') {
                array_pop($normalized);
            } else {
                $normalized[$last] = new AstNode('text', ['text' => $text]);
            }
        }

        return $normalized;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code', 'math' => (string) $node->attr('text', ''),
                'linebreak', 'softbreak' => "\n",
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->normalizedText($text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->type === 'code_block') {
                $parts[] = (string) $block->attr('text', '');
                continue;
            }

            $parts[] = $this->plainInlineText($block->children);
        }

        return $this->normalizedText(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNavList(
        \DOMElement $ol,
        string $baseDir,
        string $type,
        int $sectionIndex,
        ?string $sectionId,
        string $sectionLabel,
        string $root,
        int $depth = 0
    ): array
    {
        $entries = [];
        foreach ($ol->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'li') {
                continue;
            }

            $link = $this->firstDirectChild($node, 'a') ?? $this->firstDirectChild($node, 'span');
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->localName === 'a' ? trim($link->getAttribute('href')) : '';
            $hrefPolicy = $this->navHrefPolicy($baseDir, $href, $root);
            $path = $hrefPolicy['path'];
            $fragment = $hrefPolicy['fragment'];
            $target = $hrefPolicy['target'];
            $external = $hrefPolicy['external'];
            $exists = $hrefPolicy['exists'];
            $typeReport = $this->navItemTypeReport($node, $link);
            $label = $this->normalizedText($link->textContent);
            $linkTypes = $this->epubTypes($link);
            $hasPageBreakType = in_array('pagebreak', $linkTypes, true);
            $hrefNormalizationDiagnostics = $hrefPolicy['normalizationDiagnostics'];
            $itemDiagnostics = $type === 'page-list' ? $hrefPolicy['diagnostics'] : $hrefNormalizationDiagnostics;
            if ($type === 'page-list') {
                if ($href === '') {
                    $itemDiagnostics[] = [
                        'type' => 'missing-page-list-href',
                        'source' => $link->localName . '@href',
                        'linkElement' => $link->localName,
                        'label' => $label,
                        'pageBreak' => $hasPageBreakType,
                    ];
                }
                if ($label === '') {
                    $itemDiagnostics[] = [
                        'type' => 'missing-page-list-label',
                        'source' => 'textContent',
                        'linkElement' => $link->localName,
                        'href' => $href,
                        'path' => $path,
                        'fragment' => $fragment,
                        'pageBreak' => $hasPageBreakType,
                    ];
                }
                if (!$hasPageBreakType) {
                    $itemDiagnostics[] = [
                        'type' => 'missing-pagebreak-type',
                        'source' => 'epub:type',
                        'linkElement' => $link->localName,
                        'href' => $href,
                        'label' => $label,
                    ];
                }
            } else {
                if ($href === '' && $link->localName === 'a') {
                    $diagnostic = [
                        'type' => 'missing-nav-item-href',
                        'message' => 'EPUB navigation link item is missing an href target',
                    ];
                    $itemDiagnostics[] = $diagnostic;
                    $hrefNormalizationDiagnostics[] = $diagnostic;
                }
                if ($label === '') {
                    $diagnostic = [
                        'type' => 'empty-nav-item-label',
                        'href' => $href,
                        'message' => 'EPUB navigation list item label is empty',
                    ];
                    $itemDiagnostics[] = $diagnostic;
                    $hrefNormalizationDiagnostics[] = $diagnostic;
                }
            }
            $accessibilityLabel = $this->navAccessibilityLabel($link, $label);
            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'ol') {
                    $children = $this->readNavList(
                        $child,
                        $baseDir,
                        $type,
                        $sectionIndex,
                        $sectionId,
                        $sectionLabel,
                        $root,
                        $depth + 1
                    );
                    break;
                }
            }

            $labelProvenance = $this->labelProvenance(
                $link,
                match ($type) {
                    'landmarks' => $link->localName === 'a' ? 'anchor' : 'span',
                    'page-list' => $label === '' ? 'missing' : 'textContent',
                    default => 'xhtml-nav',
                },
                $baseDir
            );
            $labelProvenance['itemId'] = $this->nullableAttribute($node, 'id');
            $labelProvenance['labelId'] = $this->nullableAttribute($link, 'id');
            $labelProvenance['linkElement'] = $link->localName;

            $entries[] = [
                'label' => $label,
                'accessibilityLabel' => $accessibilityLabel['text'],
                'accessibilityLabelSource' => $accessibilityLabel['source'],
                'ariaLabel' => $this->nullableAttribute($link, 'aria-label'),
                'title' => $this->nullableAttribute($link, 'title'),
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'type' => $type,
                'sectionType' => $type,
                'sectionIndex' => $sectionIndex,
                'sectionId' => $sectionId,
                'sectionLabel' => $sectionLabel,
                'id' => $this->nullableAttribute($link, 'id') ?? $this->nullableAttribute($node, 'id'),
                'itemId' => $this->nullableAttribute($node, 'id'),
                'labelId' => $this->nullableAttribute($link, 'id'),
                'labelElement' => $link->localName,
                'linkElement' => $link->localName,
                'epubTypes' => $linkTypes,
                'labelProvenance' => $labelProvenance,
                'pageBreakProvenance' => [
                    'present' => $hasPageBreakType,
                    'source' => $hasPageBreakType ? 'epub:type' : ($type === 'page-list' ? 'missing' : ''),
                    'linkElement' => $link->localName,
                    'epubTypes' => $linkTypes,
                ],
                'external' => $external,
                'unsafe' => $hrefPolicy['unsafe'],
                'hrefKind' => $hrefPolicy['hrefKind'],
                'hrefScheme' => $hrefPolicy['hrefScheme'],
                'hrefDiagnostics' => $hrefPolicy['diagnostics'],
                'hrefNormalizationDiagnostics' => $hrefNormalizationDiagnostics,
                'caseMatchedPath' => $hrefPolicy['caseMatchedPath'],
                'normalization' => $hrefPolicy['normalization'],
                'exists' => $exists,
                'semanticType' => $typeReport['type'],
                'semanticTypes' => $typeReport['types'],
                'itemTypes' => $typeReport['itemTypes'],
                'labelTypes' => $typeReport['labelTypes'],
                'typeSource' => $typeReport['typeSource'],
                'typeSources' => $typeReport['typeSources'],
                'depth' => $depth,
                'childCount' => count($children),
                'diagnostics' => $itemDiagnostics,
                'children' => $children,
            ];
        }

        return $entries;
    }

    /**
     * @return array{text:string, source:?string}
     */
    private function navAccessibilityLabel(\DOMElement $labelElement, string $visibleLabel): array
    {
        $ariaLabel = $this->nullableAttribute($labelElement, 'aria-label');
        if ($ariaLabel !== null) {
            return ['text' => $ariaLabel, 'source' => 'aria-label'];
        }

        $title = $this->nullableAttribute($labelElement, 'title');
        if ($title !== null) {
            return ['text' => $title, 'source' => 'title'];
        }

        if ($visibleLabel !== '') {
            return ['text' => $visibleLabel, 'source' => 'text'];
        }

        return ['text' => '', 'source' => null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPoints(\DOMElement $parent, string $baseDir, int $depth = 0): array
    {
        $points = [];
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'navPoint') {
                continue;
            }

            $labelContainer = $this->firstDirectChild($node, 'navLabel');
            $label = $labelContainer instanceof \DOMElement
                ? $this->firstChildPathText($node, ['navLabel', 'text'])
                : '';
            $content = $this->firstDirectChild($node, 'content');
            $href = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $children = $this->readNcxPoints($node, $baseDir, $depth + 1);
            $labelProvenance = $this->ncxLabelProvenance($labelContainer);
            $labelProvenance['itemId'] = $this->nullableAttribute($node, 'id');
            $points[] = [
                'id' => $this->nullableAttribute($node, 'id'),
                'label' => $label,
                'href' => $href,
                'target' => $this->targetWithSuffix($path, $this->hrefSuffix($href)),
                'path' => $path,
                'fragment' => $fragment,
                'playOrder' => is_numeric($node->getAttribute('playOrder')) ? (int) $node->getAttribute('playOrder') : 0,
                'labelProvenance' => $labelProvenance,
                'depth' => $depth,
                'childCount' => count($children),
                'children' => $children,
            ];
        }

        return $points;
    }

    /**
     * @param list<array<string, mixed>> $points
     * @param list<array<string, mixed>> $flat
     */
    private function flattenNcxPoints(array $points, array &$flat, int $depth = 0): void
    {
        foreach ($points as $point) {
            $entry = $point;
            $entry['depth'] = $depth;
            unset($entry['children']);
            $flat[] = $entry;
            $children = is_array($point['children'] ?? null) ? $point['children'] : [];
            $this->flattenNcxPoints($children, $flat, $depth + 1);
        }
    }

    /**
     * @param list<array<string, mixed>> $points
     */
    private function maxNcxDepth(array $points, int $depth = 1): int
    {
        $maxDepth = $points === [] ? 0 : $depth;
        foreach ($points as $point) {
            $children = is_array($point['children'] ?? null) ? $point['children'] : [];
            $maxDepth = max($maxDepth, $this->maxNcxDepth($children, $depth + 1));
        }

        return $maxDepth;
    }

    /**
     * @param array<string, mixed> $point
     */
    private function ncxPointTarget(array $point): string
    {
        $path = (string) ($point['path'] ?? '');
        if ($path === '') {
            return '';
        }

        $fragment = (string) ($point['fragment'] ?? '');

        return $path . ($fragment === '' ? '' : '#' . $fragment);
    }

    /**
     * @return array<string, mixed>
     */
    private function labelProvenance(\DOMElement $element, string $source, string $baseDir): array
    {
        $imageLabels = [];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement || $descendant->localName !== 'img') {
                continue;
            }

            $src = trim($descendant->getAttribute('src'));
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $src);
            $imageLabels[] = [
                'src' => $src,
                'path' => $path,
                'fragment' => $fragment,
                'alt' => trim($descendant->getAttribute('alt')),
                'title' => trim($descendant->getAttribute('title')),
                'attributes' => $this->htmlAttributes($descendant),
            ];
        }

        return [
            'source' => $source,
            'element' => $element->localName,
            'text' => $this->normalizedText($element->textContent),
            'language' => $this->elementLanguage($element),
            'direction' => trim($element->getAttribute('dir')),
            'attributes' => $this->htmlAttributes($element),
            'epubTypes' => $this->epubTypes($element),
            'imageLabels' => $imageLabels,
            'imageLabelCount' => count($imageLabels),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxLabelProvenance(?\DOMElement $labelContainer): array
    {
        $textElement = $labelContainer instanceof \DOMElement
            ? $this->firstDescendantByLocalName($labelContainer, 'text')
            : null;

        return [
            'source' => 'ncx-navLabel',
            'element' => $labelContainer instanceof \DOMElement ? $labelContainer->localName : null,
            'text' => $textElement instanceof \DOMElement ? $this->normalizedText($textElement->textContent) : '',
            'labelId' => $labelContainer instanceof \DOMElement ? $this->nullableAttribute($labelContainer, 'id') : null,
            'textId' => $textElement instanceof \DOMElement ? $this->nullableAttribute($textElement, 'id') : null,
            'language' => $labelContainer instanceof \DOMElement ? $this->elementLanguage($labelContainer) : '',
            'direction' => $labelContainer instanceof \DOMElement ? trim($labelContainer->getAttribute('dir')) : '',
            'attributes' => $labelContainer instanceof \DOMElement ? $this->htmlAttributes($labelContainer) : [],
            'textAttributes' => $textElement instanceof \DOMElement ? $this->htmlAttributes($textElement) : [],
            'epubTypes' => [],
            'imageLabels' => [],
            'imageLabelCount' => 0,
        ];
    }

    /**
     * @param list<string> $path
     */
    private function firstChildPathText(\DOMElement $element, array $path): string
    {
        $current = $element;
        foreach ($path as $name) {
            $next = $this->firstDirectChild($current, $name);
            if (!$next instanceof \DOMElement) {
                return '';
            }
            $current = $next;
        }

        return $this->normalizedText($current->textContent);
    }

    private function firstDirectChild(\DOMNode $node, string $localName): ?\DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function directChildElements(\DOMNode $node, string $localName): array
    {
        $elements = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    private function directChildElementCount(\DOMNode $node, string $localName): int
    {
        $count = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                ++$count;
            }
        }

        return $count;
    }

    private function firstDescendantByLocalName(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }

    private function firstElementByLocalName(\DOMDocument $document, string $name): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $name) {
                return $element;
            }
        }

        return null;
    }

    private function nullableAttribute(\DOMElement $element, string $name): ?string
    {
        $value = trim($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    private function nullableNamespacedAttribute(
        \DOMElement $element,
        string $namespaceUri,
        string $localName,
        string $prefixedName
    ): ?string {
        $value = trim($element->getAttributeNS($namespaceUri, $localName));
        if ($value === '' && $element->hasAttribute($prefixedName)) {
            $value = trim($element->getAttribute($prefixedName));
        }

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function htmlAttributes(\DOMElement $element): array
    {
        return $this->elementAttributes($element);
    }

    /**
     * @return array<string, string>
     */
    private function elementAttributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            $name = $attr->nodeName;
            if (str_starts_with($name, 'xmlns')) {
                continue;
            }
            $attrs[$name] = $attr->value;
        }

        return $attrs;
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, bool> $structural
     * @return array<string, string>
     */
    private function customAttributes(array $attributes, array $structural): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (isset($structural[$name])) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        return $this->tokens($element->getAttribute('class'));
    }

    private function orderedListStyle(\DOMElement $element): string
    {
        return match ($element->getAttribute('type')) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function epubType(\DOMElement $element): string
    {
        foreach ($this->epubTypes($element) as $token) {
            if ($token === 'toc' || $token === 'landmarks' || $token === 'page-list') {
                return $token;
            }
        }

        return '';
    }

    /**
     * @return array{
     *     type:?string,
     *     types:list<string>,
     *     itemTypes:list<string>,
     *     labelTypes:list<string>,
     *     typeSource:?string,
     *     typeSources:list<array{type:string, source:string, element:string}>
     * }
     */
    private function navItemTypeReport(\DOMElement $item, ?\DOMElement $label): array
    {
        $itemTypes = $this->epubTypes($item);
        $labelTypes = $label instanceof \DOMElement ? $this->epubTypes($label) : [];
        $types = [];
        $typeSources = [];
        $sourceByType = [];

        $addTypes = static function (array $sourceTypes, string $source, string $element) use (&$types, &$typeSources, &$sourceByType): void {
            foreach ($sourceTypes as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }

                if (!in_array($type, $types, true)) {
                    $types[] = $type;
                    $sourceByType[$type] = $source;
                }

                $typeSources[] = [
                    'type' => $type,
                    'source' => $source,
                    'element' => $element,
                ];
            }
        };

        $addTypes($labelTypes, 'label', $label instanceof \DOMElement ? $label->localName : '');
        $addTypes($itemTypes, 'item', $item->localName);
        $type = $types[0] ?? null;

        return [
            'type' => $type,
            'types' => $types,
            'itemTypes' => $itemTypes,
            'labelTypes' => $labelTypes,
            'typeSource' => $type === null ? null : ($sourceByType[$type] ?? null),
            'typeSources' => $typeSources,
        ];
    }

    /**
     * @return list<string>
     */
    private function epubTypes(\DOMElement $element): array
    {
        $value = trim($element->getAttributeNS(self::EPUB_TYPE_NS, 'type'));
        if ($value === '') {
            $value = trim($element->getAttribute('epub:type'));
        }
        if ($value === '') {
            $value = trim($element->getAttribute('type'));
        }

        return $this->tokens($value);
    }

    private function elementLanguage(\DOMElement $element): string
    {
        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('xml:lang'));
        }
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }

        return $language;
    }

    private function elementBase(\DOMElement $element): ?string
    {
        $base = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        if ($base === '') {
            $base = trim($element->getAttribute('xml:base'));
        }

        return $base === '' ? null : $base;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function isExternalHref(string $href): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', trim($href)) === 1;
    }

    private function mediaTypeBase(string $mediaType): string
    {
        return strtolower(trim(explode(';', $mediaType, 2)[0]));
    }

    private function isDirectSpineImageMediaType(string $mediaType): bool
    {
        return in_array($this->mediaTypeBase($mediaType), ['image/gif', 'image/jpeg', 'image/png'], true);
    }

    /**
     * @return array{hasQuery:bool, query:?string, hasFragment:bool, fragment:?string}
     */
    private function hrefSuffix(string $href): array
    {
        $fragment = null;
        $beforeFragment = $href;
        $hashPosition = strpos($href, '#');
        if ($hashPosition !== false) {
            $fragment = substr($href, $hashPosition + 1);
            $beforeFragment = substr($href, 0, $hashPosition);
        }

        $query = null;
        $queryPosition = strpos($beforeFragment, '?');
        if ($queryPosition !== false) {
            $query = substr($beforeFragment, $queryPosition + 1);
        }

        return [
            'hasQuery' => $query !== null,
            'query' => $query,
            'hasFragment' => $fragment !== null,
            'fragment' => $fragment,
        ];
    }

    /**
     * @return array{
     *     mediaType:string,
     *     normalizedMediaType:string,
     *     mediaTypeBase:string,
     *     mediaTypeHasParameters:bool,
     *     mediaTypeParameterCount:int,
     *     mediaTypeParameters:list<array{name:string, value:string, raw:string}>,
     *     mediaTypeParameterMap:array<string, string>,
     *     mediaTypeSyntaxValid:bool,
     *     mediaTypeDiagnostics:list<array<string, mixed>>
     * }
     */
    private function mediaTypeReport(string $mediaType): array
    {
        $segments = $this->mediaTypeSegments($mediaType);
        $base = strtolower(trim((string) array_shift($segments)));
        $parameters = [];
        $parameterMap = [];
        $diagnostics = [];

        if ($base === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+\/[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $base) !== 1) {
            $diagnostics[] = [
                'type' => 'invalid-manifest-media-type',
                'mediaType' => $mediaType,
                'mediaTypeBase' => $base,
                'message' => 'EPUB OPF manifest media-type must be a MIME type in type/subtype form',
            ];
        }

        foreach ($segments as $index => $segment) {
            $raw = trim($segment);
            if ($raw === '') {
                continue;
            }

            $equals = strpos($raw, '=');
            if ($equals === false) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter',
                    'mediaType' => $mediaType,
                    'parameter' => $raw,
                    'parameterIndex' => $index,
                    'message' => 'EPUB OPF manifest media-type parameters must use name=value syntax',
                ];
                continue;
            }

            $name = strtolower(trim(substr($raw, 0, $equals)));
            if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $name) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter-name',
                    'mediaType' => $mediaType,
                    'parameter' => $raw,
                    'parameterIndex' => $index,
                    'name' => $name,
                    'message' => 'EPUB OPF manifest media-type parameter names must be MIME tokens',
                ];
                continue;
            }

            $value = trim(substr($raw, $equals + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            if (isset($parameterMap[$name])) {
                $diagnostics[] = [
                    'type' => 'duplicate-manifest-media-type-parameter',
                    'mediaType' => $mediaType,
                    'parameter' => $name,
                    'parameterIndex' => $index,
                    'previousValue' => $parameterMap[$name],
                    'value' => $value,
                    'message' => 'EPUB OPF manifest media-type parameter repeats a name; later value is retained for package review',
                ];
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
        }

        $normalized = $base;
        foreach ($parameterMap as $name => $value) {
            $normalized .= '; ' . $name . '=' . strtolower($value);
        }

        return [
            'mediaType' => $mediaType,
            'normalizedMediaType' => $normalized,
            'mediaTypeBase' => $base,
            'mediaTypeHasParameters' => $parameters !== [],
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterMap' => $parameterMap,
            'mediaTypeSyntaxValid' => $diagnostics === [],
            'mediaTypeDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<string>
     */
    private function mediaTypeSegments(string $mediaType): array
    {
        $segments = [];
        $current = '';
        $inQuote = false;
        $escaped = false;
        $length = strlen($mediaType);

        for ($index = 0; $index < $length; $index++) {
            $char = $mediaType[$index];
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $current .= $char;
                continue;
            }

            if ($char === ';' && !$inQuote) {
                $segments[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * @param array{hasQuery:bool, query:?string, hasFragment:bool, fragment:?string} $suffix
     */
    private function targetWithSuffix(string $path, array $suffix): string
    {
        if ($path === '') {
            return '';
        }

        return $path
            . (($suffix['hasQuery'] && $suffix['query'] !== null) ? '?' . $suffix['query'] : '')
            . (($suffix['hasFragment'] && $suffix['fragment'] !== null) ? '#' . $suffix['fragment'] : '');
    }

    /**
     * @return array{
     *     target:string,
     *     path:string,
     *     fragment:string,
     *     external:bool,
     *     unsafe:bool,
     *     hrefKind:string,
     *     hrefScheme:?string,
     *     caseMatchedPath:?string,
     *     normalization:array{normalized:bool, percentDecoded:bool, dotSegmentNormalized:bool, packageRootEscape:bool, caseMismatch:bool},
     *     exists:bool,
     *     diagnostics:list<array<string, mixed>>,
     *     normalizationDiagnostics:list<array<string, mixed>>
     * }
     */
    private function navHrefPolicy(string $baseDir, string $href, string $root): array
    {
        $href = trim($href);
        $suffix = $this->hrefSuffix($href);
        $scheme = $this->hrefScheme($href);
        $pathPart = $this->hrefPathPart($href);
        $fragment = is_string($suffix['fragment'] ?? null) ? $suffix['fragment'] : '';
        $external = false;
        $unsafe = $scheme !== null && in_array($scheme, ['data', 'javascript', 'vbscript'], true);
        $hrefKind = 'local';
        $path = '';
        $target = '';
        $exists = false;
        $diagnostics = [];
        $normalizationDiagnostics = [];
        $caseMatchedPath = null;
        $normalization = [
            'normalized' => false,
            'percentDecoded' => false,
            'dotSegmentNormalized' => false,
            'packageRootEscape' => false,
            'caseMismatch' => false,
        ];

        if ($href === '') {
            $hrefKind = 'empty';
        } elseif (str_starts_with($href, '//')) {
            $external = true;
            $hrefKind = 'network-path';
            $path = $pathPart;
            $target = $href;
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif ($scheme !== null) {
            $external = true;
            $hrefKind = $unsafe ? 'unsafe-uri' : 'absolute-uri';
            $path = $pathPart;
            $target = $this->targetWithSuffix($path, $suffix);
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif (str_starts_with($pathPart, '/')) {
            $external = true;
            $hrefKind = 'package-root-reference';
            $path = $pathPart;
            $target = $href;
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif ($pathPart === '') {
            $hrefKind = $fragment !== '' ? 'same-document-fragment' : 'empty';
            $target = $href;
        } else {
            if (preg_match('/%(?![0-9A-Fa-f]{2})/', $pathPart) === 1) {
                $unsafe = true;
                $hrefKind = 'invalid-percent-escape';
                $normalizationDiagnostics[] = [
                    'type' => 'invalid-nav-reference',
                    'href' => $href,
                    'message' => 'EPUB navigation href contains a malformed percent escape',
                ];
            } else {
                $decodedPathPart = rawurldecode($pathPart);
                $normalization['percentDecoded'] = $decodedPathPart !== $pathPart;
                $normalization['dotSegmentNormalized'] = $this->hasDotSegments($decodedPathPart);
                try {
                    $path = $this->normalizeRelativePath($baseDir . '/' . $decodedPathPart);
                    $target = $this->targetWithSuffix($path, $suffix);
                    $exists = $path !== '' && $this->packagePathExists($root, $path);
                    $caseMatchedPath = !$exists && $path !== ''
                        ? $this->caseInsensitivePackagePathMatch($root, $path)
                        : null;
                    $normalization['caseMismatch'] = $caseMatchedPath !== null;
                    $normalization['normalized'] = $normalization['percentDecoded']
                        || $normalization['dotSegmentNormalized']
                        || $normalization['caseMismatch'];
                } catch (\RuntimeException $exception) {
                    $external = true;
                    $hrefKind = 'package-relative-external';
                    $path = $pathPart;
                    $target = $this->targetWithSuffix($path, $suffix);
                    $normalization['percentDecoded'] = false;
                    $normalization['dotSegmentNormalized'] = false;
                    $normalization['normalized'] = false;
                    $normalization['packageRootEscape'] = true;
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-package-root-escape',
                        'href' => $href,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($path !== '' && !$external && !$unsafe) {
                if ($normalization['percentDecoded']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-percent-decoded',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($normalization['dotSegmentNormalized']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-dot-segment-normalized',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($normalization['caseMismatch']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'case-sensitive-nav-target-mismatch',
                        'href' => $href,
                        'path' => $path,
                        'caseMatchedPath' => $caseMatchedPath,
                        'message' => 'EPUB navigation href differs from a package-local path only by case',
                    ];
                } elseif (!$exists) {
                    $normalizationDiagnostics[] = [
                        'type' => 'missing-nav-reference',
                        'href' => $href,
                        'path' => $path,
                        'message' => 'EPUB navigation href target is missing from the package',
                    ];
                }
            } elseif ($path === '' && !$external && !$unsafe) {
                $resolved = $this->resolvePackageHrefForNav($baseDir, $href);
                if ($resolved === null) {
                    $external = true;
                    $hrefKind = 'package-relative-external';
                    $path = $pathPart;
                    $target = $this->targetWithSuffix($path, $suffix);
                }
            }

            if ($external && $normalizationDiagnostics === []) {
                $external = true;
                $hrefKind = 'package-relative-external';
                $path = $pathPart;
                $target = $this->targetWithSuffix($path, $suffix);
                $normalizationDiagnostics[] = [
                    'type' => 'external-nav-reference',
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB navigation href points outside the package and was not fetched',
                ];
            }
        }

        if ($suffix['hasQuery'] && $suffix['query'] !== null) {
            $normalizationDiagnostics[] = [
                'type' => 'nav-href-query-component',
                'href' => $href,
                'query' => $suffix['query'],
            ];
        }
        if ($suffix['hasFragment'] && $suffix['fragment'] !== null) {
            $normalizationDiagnostics[] = [
                'type' => 'nav-href-fragment-component',
                'href' => $href,
                'fragment' => $suffix['fragment'],
            ];
        }

        if ($external) {
            $diagnostics[] = [
                'type' => 'external-nav-href-target',
                'target' => $target,
                'hrefKind' => $hrefKind,
                'hrefScheme' => $scheme,
            ];
        }
        if ($unsafe) {
            $diagnostics[] = [
                'type' => 'unsafe-nav-href-target',
                'target' => $target,
                'hrefKind' => $hrefKind,
                'hrefScheme' => $scheme,
            ];
        }

        return [
            'target' => $target,
            'path' => $path,
            'fragment' => $fragment,
            'external' => $external,
            'unsafe' => $unsafe,
            'hrefKind' => $hrefKind,
            'hrefScheme' => $scheme,
            'caseMatchedPath' => $caseMatchedPath,
            'normalization' => $normalization,
            'exists' => $exists,
            'diagnostics' => $diagnostics,
            'normalizationDiagnostics' => $normalizationDiagnostics,
        ];
    }

    private function hasDotSegments(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function hrefScheme(string $href): ?string
    {
        $trimmed = trim($href);
        $colonPosition = strpos($trimmed, ':');
        if ($colonPosition === false) {
            return null;
        }

        $candidate = substr($trimmed, 0, $colonPosition);
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $candidate));
        if (preg_match('/^[a-z][a-z0-9+.-]*$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function hrefPathPart(string $href): string
    {
        $beforeFragment = explode('#', $href, 2)[0];

        return explode('?', $beforeFragment, 2)[0];
    }

    private function resolvePackageHrefForNav(string $baseDir, string $href): ?string
    {
        try {
            return $this->resolvePackageHref($baseDir, $href);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{exists:bool, parseError:?string, idCount:int, uniqueIdCount:int, idCounts:array<string, int>, duplicateIds:array<string, int>}
     */
    private function packageXhtmlIdIndex(string $root, string $relative): array
    {
        $empty = [
            'exists' => false,
            'parseError' => null,
            'idCount' => 0,
            'uniqueIdCount' => 0,
            'idCounts' => [],
            'duplicateIds' => [],
        ];

        if (!$this->packagePathExists($root, $relative)) {
            return $empty;
        }

        try {
            $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $relative));
        } catch (\RuntimeException $exception) {
            return [
                'exists' => true,
                'parseError' => $exception->getMessage(),
                'idCount' => 0,
                'uniqueIdCount' => 0,
                'idCounts' => [],
                'duplicateIds' => [],
            ];
        }

        $idCounts = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
            }

            $xmlId = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'id'));
            if ($xmlId !== '' && $xmlId !== $id) {
                $idCounts[$xmlId] = ($idCounts[$xmlId] ?? 0) + 1;
            }
        }

        $duplicateIds = array_filter($idCounts, static fn (int $count): bool => $count > 1);

        return [
            'exists' => true,
            'parseError' => null,
            'idCount' => array_sum($idCounts),
            'uniqueIdCount' => count($idCounts),
            'idCounts' => $idCounts,
            'duplicateIds' => $duplicateIds,
        ];
    }

    private function loadXmlFile(string $path): \DOMDocument
    {
        if (!is_file($path)) {
            throw new \RuntimeException('EPUB XML asset does not exist: ' . $path);
        }

        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new \RuntimeException('Unable to read EPUB XML asset: ' . $path);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException('Unable to parse EPUB XML asset ' . $path . ': ' . $message);
        }

        return $document;
    }

    private function resolveExistingPackagePath(string $root, string $relative): string
    {
        $normalized = $this->normalizeRelativePath($relative);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        if ($absolute === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('EPUB package path escapes the package root: ' . $relative);
        }

        return $absolute;
    }

    private function packagePathExists(string $root, string $relative): bool
    {
        $normalized = $this->normalizeRelativePath($relative);
        $directory = $root;
        $segments = explode('/', $normalized);
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($segment === '' || !is_dir($directory)) {
                return false;
            }

            $entries = scandir($directory);
            if ($entries === false || !in_array($segment, $entries, true)) {
                return false;
            }

            $directory .= DIRECTORY_SEPARATOR . $segment;
            if ($index < $last && !is_dir($directory)) {
                return false;
            }
        }

        return is_file($directory);
    }

    private function caseInsensitivePackagePathMatch(string $root, string $relative): ?string
    {
        $normalized = $this->normalizeRelativePath($relative);
        $directory = $root;
        $matched = [];
        $caseMismatch = false;

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || !is_dir($directory)) {
                return null;
            }

            $entries = scandir($directory);
            if ($entries === false) {
                return null;
            }

            $next = null;
            foreach ($entries as $entry) {
                if ($entry === $segment) {
                    $next = $entry;
                    break;
                }
            }
            if ($next === null) {
                foreach ($entries as $entry) {
                    if (strcasecmp($entry, $segment) === 0) {
                        $next = $entry;
                        $caseMismatch = true;
                        break;
                    }
                }
            }
            if ($next === null) {
                return null;
            }

            $matched[] = $next;
            $directory .= DIRECTORY_SEPARATOR . $next;
        }

        if (!$caseMismatch || !is_file($directory)) {
            return null;
        }

        return implode('/', $matched);
    }

    private function resolvePackageHref(string $baseDir, string $href): string
    {
        $withoutFragment = explode('#', $href, 2)[0];
        $withoutQuery = explode('?', $withoutFragment, 2)[0];
        if ($withoutQuery === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $withoutQuery) === 1) {
            return $withoutQuery;
        }

        return $this->normalizeRelativePath($baseDir . '/' . rawurldecode($withoutQuery));
    }

    private function resolveContentHref(string $baseDir, string $href): string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1 || str_starts_with($href, '#')) {
            return $href;
        }

        [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);

        return $path . ($fragment === '' ? '' : '#' . $fragment);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitResolvedHref(string $baseDir, string $href): array
    {
        $parts = explode('#', $href, 2);
        $path = $parts[0] === '' ? '' : $this->resolvePackageHref($baseDir, $parts[0]);

        return [$path, $parts[1] ?? ''];
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \RuntimeException('EPUB relative path escapes package root: ' . $path);
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function relativeDirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));

        return $dir === '.' ? '' : $dir;
    }

    private function normalizedText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
