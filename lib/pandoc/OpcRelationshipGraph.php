<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    public const CORE_PROPERTIES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    public const EXTENDED_PROPERTIES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    public const CUSTOM_PROPERTIES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    public const THUMBNAIL_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
    public const DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
    public const DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
    public const ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/encrypted-package';
    public const WORDPROCESSING_STYLES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    public const WORDPROCESSING_NUMBERING_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    public const WORDPROCESSING_FOOTNOTES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    public const WORDPROCESSING_ENDNOTES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes';
    public const WORDPROCESSING_COMMENTS_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    public const WORDPROCESSING_SETTINGS_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    public const WORDPROCESSING_THEME_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    public const WORDPROCESSING_FONT_TABLE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    public const WORDPROCESSING_WEB_SETTINGS_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings';
    public const WORDPROCESSING_HEADER_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
    public const WORDPROCESSING_FOOTER_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';
    public const WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    public const WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    public const WORDPROCESSING_CUSTOM_XML_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
    public const WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
    public const WORDPROCESSING_COMMENTS_EXTENDED_RELATIONSHIP_TYPE = 'http://schemas.microsoft.com/office/2011/relationships/commentsExtended';
    public const WORDPROCESSING_GLOSSARY_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument';
    public const WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';
    public const DRAWINGML_CHART_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
    public const DRAWINGML_DIAGRAM_DATA_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData';
    public const DRAWINGML_DIAGRAM_LAYOUT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout';
    public const DRAWINGML_DIAGRAM_QUICK_STYLE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle';
    public const DRAWINGML_DIAGRAM_COLORS_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors';
    public const RELATIONSHIP_TRANSFORM_ALGORITHM = 'http://schemas.openxmlformats.org/package/2006/RelationshipTransform';
    public const XML_SIGNATURE_NAMESPACE_URI = 'http://www.w3.org/2000/09/xmldsig#';
    public const DIGITAL_SIGNATURE_NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/digital-signature';
    public const CUSTOM_XML_DATA_STORE_NAMESPACE_URI = 'http://schemas.openxmlformats.org/officeDocument/2006/customXml';
    public const EMBEDDED_PACKAGE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
    public const EMBEDDED_OBJECT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
    public const WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        'application/vnd.ms-word.document.macroEnabled.main+xml',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml',
        'application/vnd.ms-word.template.macroEnabledTemplate.main+xml',
    ];

    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';
    private const ZIP_MANIFEST_LARGEST_PAYLOAD_ENTRY_LIMIT = 5;
    private const CORE_PROPERTIES_CONTENT_TYPE = 'application/vnd.openxmlformats-package.core-properties+xml';
    private const EXTENDED_PROPERTIES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.extended-properties+xml';
    private const CUSTOM_PROPERTIES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';
    private const DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-origin';
    private const DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml';
    private const ENCRYPTED_PACKAGE_CONTENT_TYPE = 'application/vnd.openxmlformats-package.encrypted-package';
    private const EMBEDDED_PACKAGE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.package';
    private const EMBEDDED_OBJECT_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.oleObject';
    private const XML_SIGNATURE_ENVELOPED_SIGNATURE_TRANSFORM_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const XML_SIGNATURE_DIGEST_ALGORITHMS = [
        'http://www.w3.org/2000/09/xmldsig#sha1' => ['profile' => 'sha1', 'expectedDecodedBytes' => 20],
        'http://www.w3.org/2001/04/xmlenc#sha256' => ['profile' => 'sha256', 'expectedDecodedBytes' => 32],
        'http://www.w3.org/2001/04/xmldsig-more#sha384' => ['profile' => 'sha384', 'expectedDecodedBytes' => 48],
        'http://www.w3.org/2001/04/xmlenc#sha512' => ['profile' => 'sha512', 'expectedDecodedBytes' => 64],
        'http://www.w3.org/2001/04/xmlenc#ripemd160' => ['profile' => 'ripemd160', 'expectedDecodedBytes' => 20],
    ];
    private const WORDPROCESSING_STYLES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml';
    private const WORDPROCESSING_NUMBERING_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml';
    private const WORDPROCESSING_FOOTNOTES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml';
    private const WORDPROCESSING_ENDNOTES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml';
    private const WORDPROCESSING_COMMENTS_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml';
    private const WORDPROCESSING_SETTINGS_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml';
    private const WORDPROCESSING_FONT_TABLE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml';
    private const WORDPROCESSING_WEB_SETTINGS_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml';
    private const WORDPROCESSING_HEADER_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml';
    private const WORDPROCESSING_FOOTER_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml';
    private const WORDPROCESSING_CUSTOM_XML_CONTENT_TYPE = 'application/xml';
    private const WORDPROCESSING_CUSTOM_XML_PROPERTIES_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml';
    private const WORDPROCESSING_COMMENTS_EXTENDED_CONTENT_TYPE = 'application/vnd.ms-word.commentsExt+xml';
    private const WORDPROCESSING_GLOSSARY_DOCUMENT_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml';
    private const DRAWINGML_CHART_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';
    private const DRAWINGML_DIAGRAM_DATA_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml';
    private const DRAWINGML_DIAGRAM_LAYOUT_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml';
    private const DRAWINGML_DIAGRAM_QUICK_STYLE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml';
    private const DRAWINGML_DIAGRAM_COLORS_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml';
    private const OFFICE_THEME_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.theme+xml';

    /**
     * @param array<string, OpcRelationships> $relationshipsBySource
     * @param array<string, string> $packagePartNamesByEquivalenceKey
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly OpcContentTypes $contentTypes,
        private readonly array $relationshipsBySource,
        private readonly array $packagePartNamesByEquivalenceKey,
    ) {
        foreach (array_keys($relationshipsBySource) as $sourcePartName) {
            $this->relationshipSourceNamesByEquivalenceKey[self::partNameEquivalenceKey($sourcePartName)] = $sourcePartName;
        }
    }

    /** @var array<string, string> */
    private array $relationshipSourceNamesByEquivalenceKey = [];

    public static function fromPackage(ZipPackage $package): self
    {
        $contentTypesItemName = self::contentTypesItemNameInPackage($package);
        if ($contentTypesItemName === null) {
            throw new \RuntimeException('OPC package is missing [Content_Types].xml');
        }

        $partNamePreflight = self::preflightPackagePartNames($package);
        if (!$partNamePreflight['valid']) {
            throw new \RuntimeException(
                'OPC package contains invalid part names: '
                . implode(', ', $partNamePreflight['invalidPartNames'])
            );
        }

        foreach (self::preflightPackagePartNameEquivalence($package) as $partNameEquivalence) {
            if ($partNameEquivalence['valid']) {
                continue;
            }

            throw new \RuntimeException(
                'OPC package contains equivalent part names that differ only by ASCII case: '
                . implode(', ', $partNameEquivalence['equivalentPartNames'])
            );
        }

        $contentTypes = OpcContentTypes::fromXml($package->read($contentTypesItemName));
        $packagePartNamesByEquivalenceKey = self::packagePartNamesByEquivalenceKey($package);
        $relationshipsBySource = [];

        foreach ($package->names() as $name) {
            if (!self::isRelationshipPartName($name)) {
                continue;
            }

            $relationshipPartName = OpcPackagePath::canonicalPartName($name);
            if (!self::contentTypeMatches($contentTypes->contentTypeForPart($relationshipPartName), self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                continue;
            }

            $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
            if ($sourcePartName !== '/' && OpcRelationships::isRelationshipPartName($sourcePartName)) {
                continue;
            }

            if ($sourcePartName !== '/' && self::isContentTypesItemName($sourcePartName)) {
                continue;
            }

            if ($sourcePartName !== '/' && !isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($sourcePartName)])) {
                continue;
            }

            if (isset($relationshipsBySource[$sourcePartName])) {
                throw new \RuntimeException('Duplicate OPC relationship part source: ' . $sourcePartName);
            }

            $relationshipsBySource[$sourcePartName] = OpcRelationships::fromXml(
                $package->read($relationshipPartName),
                $sourcePartName,
            );
        }

        return new self(
            $package,
            $contentTypes,
            $relationshipsBySource,
            $packagePartNamesByEquivalenceKey,
        );
    }

    /**
     * @return array{partName:string, present:bool, valid:bool, parseError:?string, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>}
     */
    public static function preflightContentTypesInPackage(ZipPackage $package): array
    {
        $contentTypesItemName = self::contentTypesItemNameInPackage($package);
        if ($contentTypesItemName === null) {
            return [
                'partName' => '/[Content_Types].xml',
                'present' => false,
                'valid' => false,
                'parseError' => null,
                'recordCount' => 0,
                'defaultCount' => 0,
                'overrideCount' => 0,
                'invalidCount' => 0,
                'duplicateDefaultExtensionCount' => 0,
                'duplicateOverridePartNameCount' => 0,
                'duplicateDefaultExtensions' => [],
                'duplicateOverridePartNames' => [],
                'duplicateDefaultExtensionGroups' => [],
                'duplicateOverridePartNameGroups' => [],
                'issueCounts' => ['missing-content-types-item' => 1],
                'issues' => ['missing-content-types-item'],
                'records' => [],
            ];
        }

        return [
            'partName' => OpcPackagePath::canonicalPartName($contentTypesItemName),
            'present' => true,
            ...OpcContentTypes::preflightXml($package->read($contentTypesItemName)),
        ];
    }

    /**
     * Resolve content types for a caller-selected part list without reading the selected part payloads.
     *
     * The selection policy is intentionally narrow: callers provide exact OPC part names or URI
     * references, query and fragment suffixes are ignored for content-type resolution, and package
     * existence is matched by OPC's case-insensitive part-name equivalence key.
     *
     * @param list<string> $selectedPartNames
     * @return array<string, mixed>
     */
    public static function preflightSelectedContentTypes(ZipPackage $package, array $selectedPartNames): array
    {
        $contentTypesItemName = self::contentTypesItemNameInPackage($package);
        $contentTypes = null;
        $contentTypesParseError = null;
        if ($contentTypesItemName !== null) {
            try {
                $contentTypes = OpcContentTypes::fromXml($package->read($contentTypesItemName));
            } catch (\Throwable $exception) {
                $contentTypesParseError = $exception->getMessage();
            }
        }

        $packagePartNamesByEquivalenceKey = self::packagePartNamesByEquivalenceKey($package);
        $firstSelectedIndexByEquivalenceKey = [];
        $records = [];

        foreach ($selectedPartNames as $selectedIndex => $selectedPartName) {
            $record = [
                'selectedIndex' => $selectedIndex,
                'selectedPartName' => $selectedPartName,
                'partName' => null,
                'equivalenceKey' => null,
                'packagePartName' => null,
                'exists' => false,
                'matchKind' => 'invalid',
                'partNameExactMatch' => false,
                'partNameEquivalentMatch' => false,
                'duplicateOfIndex' => null,
                'contentType' => null,
                'contentTypeMediaType' => null,
                'contentTypeHasParameters' => false,
                'contentTypeParameterCount' => 0,
                'contentTypeParameterNames' => [],
                'contentTypeParameterMap' => [],
                'contentTypeParameters' => [],
                'contentTypeQuotedParameterCount' => 0,
                'contentTypeSource' => null,
                'contentTypeDefaultExtension' => null,
                'contentTypeOverridePartName' => null,
                'contentTypeOverridePartNameExactMatch' => null,
                'contentTypeOverridePartNameEquivalentMatch' => null,
                'valid' => true,
                'issues' => [],
                'parseError' => null,
            ];

            try {
                $partName = OpcPackagePath::canonicalPartNameFromUri(
                    OpcPackagePath::stripQueryAndFragment($selectedPartName)
                );
                $equivalenceKey = self::partNameEquivalenceKey($partName);
                $packagePartName = $packagePartNamesByEquivalenceKey[$equivalenceKey] ?? null;

                $record['partName'] = $partName;
                $record['equivalenceKey'] = $equivalenceKey;
                $record['packagePartName'] = $packagePartName;
                $record['exists'] = $packagePartName !== null;
                $record['matchKind'] = $packagePartName === null
                    ? 'missing'
                    : ($packagePartName === $partName ? 'exact' : 'equivalent');
                $record['partNameExactMatch'] = $packagePartName === $partName;
                $record['partNameEquivalentMatch'] = $packagePartName !== null && $packagePartName !== $partName;

                if ($packagePartName === null) {
                    $record['issues'][] = 'selected-part-missing';
                }

                if (isset($firstSelectedIndexByEquivalenceKey[$equivalenceKey])) {
                    $record['duplicateOfIndex'] = $firstSelectedIndexByEquivalenceKey[$equivalenceKey];
                    $record['issues'][] = 'duplicate-selected-part';
                } else {
                    $firstSelectedIndexByEquivalenceKey[$equivalenceKey] = $selectedIndex;
                }

                if ($contentTypes instanceof OpcContentTypes) {
                    $resolution = $contentTypes->contentTypeResolutionForPart($selectedPartName);
                    $record['contentType'] = $resolution['contentType'];
                    $record['contentTypeMediaType'] = $resolution['contentTypeMediaType'];
                    $record['contentTypeHasParameters'] = $resolution['contentTypeHasParameters'];
                    $record['contentTypeParameterCount'] = $resolution['contentTypeParameterCount'];
                    $record['contentTypeParameterNames'] = $resolution['contentTypeParameterNames'];
                    $record['contentTypeParameterMap'] = $resolution['contentTypeParameterMap'];
                    $record['contentTypeParameters'] = $resolution['contentTypeParameters'];
                    $record['contentTypeQuotedParameterCount'] = $resolution['contentTypeQuotedParameterCount'];
                    $record['contentTypeSource'] = $resolution['contentTypeSource'];
                    $record['contentTypeDefaultExtension'] = $resolution['defaultExtension'];
                    $record['contentTypeOverridePartName'] = $resolution['overridePartName'];
                    $record['contentTypeOverridePartNameExactMatch'] = $resolution['overridePartNameExactMatch'];
                    $record['contentTypeOverridePartNameEquivalentMatch'] = $resolution['overridePartNameEquivalentMatch'];

                    if ($resolution['contentType'] === null) {
                        $record['issues'][] = 'missing-content-type';
                        if (self::partNameExtension($partName) === '') {
                            $record['issues'][] = 'missing-content-type-extension';
                        } else {
                            $record['issues'][] = 'missing-content-type-default';
                        }
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $record['parseError'] = $exception->getMessage();
                $record['issues'][] = 'invalid-selected-part-name';
            }

            $record['issues'] = array_values(array_unique($record['issues']));
            $record['valid'] = $record['issues'] === [];
            $records[] = $record;
        }

        $issueCounts = [];
        $issues = [];
        $partNamesByIssue = [];
        $selectedPartNamesByIssue = [];
        $contentTypeSourceCounts = [];
        $partNamesByContentTypeSource = [];
        $selectedPartNamesByContentTypeSource = [];
        $selectedPartNamesByMatchKind = [];
        $selectedPartCount = count($records);
        $uniqueSelectedPartCount = count($firstSelectedIndexByEquivalenceKey);
        $duplicateSelectedPartCount = 0;
        $invalidSelectedPartCount = 0;
        $existingSelectedPartCount = 0;
        $missingSelectedPartCount = 0;
        $exactSelectedPartCount = 0;
        $equivalentSelectedPartCount = 0;
        $contentTypeResolvedPartCount = 0;
        $contentTypeDefaultResolvedPartCount = 0;
        $contentTypeOverrideResolvedPartCount = 0;
        $missingContentTypePartCount = 0;
        $missingContentTypeDefaultCount = 0;
        $missingContentTypeExtensionlessCount = 0;
        $missingContentTypeParts = [];
        $missingSelectedPartNames = [];
        $duplicateSelectedPartNames = [];
        $invalidSelectedPartNames = [];

        if ($contentTypesItemName === null) {
            $issueCounts['missing-content-types-item'] = 1;
            $issues[] = 'missing-content-types-item';
            $partNamesByIssue['missing-content-types-item'] = ['/[Content_Types].xml'];
        } elseif (!$contentTypes instanceof OpcContentTypes) {
            $issueCounts['content-types-xml-parse-error'] = 1;
            $issues[] = 'content-types-xml-parse-error';
            $partNamesByIssue['content-types-xml-parse-error'] = ['/[Content_Types].xml'];
        }

        foreach ($records as $record) {
            if ($record['partName'] === null) {
                $invalidSelectedPartCount++;
                $invalidSelectedPartNames[] = $record['selectedPartName'];
            } else {
                $selectedPartNamesByMatchKind[$record['matchKind']] ??= [];
                self::appendUniqueString($selectedPartNamesByMatchKind[$record['matchKind']], $record['selectedPartName']);
            }

            if ($record['exists']) {
                $existingSelectedPartCount++;
                if ($record['partNameExactMatch']) {
                    $exactSelectedPartCount++;
                } elseif ($record['partNameEquivalentMatch']) {
                    $equivalentSelectedPartCount++;
                }
            } elseif ($record['partName'] !== null) {
                $missingSelectedPartCount++;
                self::appendUniqueString($missingSelectedPartNames, $record['partName']);
            }

            if ($record['duplicateOfIndex'] !== null) {
                $duplicateSelectedPartCount++;
                self::appendUniqueString($duplicateSelectedPartNames, $record['selectedPartName']);
            }

            if (is_string($record['contentTypeSource'])) {
                $contentTypeSourceCounts[$record['contentTypeSource']] = ($contentTypeSourceCounts[$record['contentTypeSource']] ?? 0) + 1;
                $partNamesByContentTypeSource[$record['contentTypeSource']] ??= [];
                $selectedPartNamesByContentTypeSource[$record['contentTypeSource']] ??= [];
                if ($record['partName'] !== null) {
                    self::appendUniqueString($partNamesByContentTypeSource[$record['contentTypeSource']], $record['partName']);
                }
                self::appendUniqueString($selectedPartNamesByContentTypeSource[$record['contentTypeSource']], $record['selectedPartName']);

                if ($record['contentTypeSource'] === 'default') {
                    $contentTypeResolvedPartCount++;
                    $contentTypeDefaultResolvedPartCount++;
                } elseif ($record['contentTypeSource'] === 'override') {
                    $contentTypeResolvedPartCount++;
                    $contentTypeOverrideResolvedPartCount++;
                } elseif ($record['contentTypeSource'] === 'missing') {
                    $missingContentTypePartCount++;
                    if ($record['partName'] !== null) {
                        self::appendUniqueString($missingContentTypeParts, $record['partName']);
                        if (self::partNameExtension($record['partName']) === '') {
                            $missingContentTypeExtensionlessCount++;
                        } else {
                            $missingContentTypeDefaultCount++;
                        }
                    }
                }
            }

            foreach ($record['issues'] as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                self::appendUniqueString($issues, $issue);
                $selectedPartNamesByIssue[$issue] ??= [];
                self::appendUniqueString($selectedPartNamesByIssue[$issue], $record['selectedPartName']);
                if ($record['partName'] !== null) {
                    $partNamesByIssue[$issue] ??= [];
                    self::appendUniqueString($partNamesByIssue[$issue], $record['partName']);
                }
            }
        }

        ksort($issueCounts);
        sort($issues, SORT_STRING);
        ksort($contentTypeSourceCounts);
        sort($missingContentTypeParts, SORT_STRING);
        sort($missingSelectedPartNames, SORT_STRING);
        sort($duplicateSelectedPartNames, SORT_STRING);
        sort($invalidSelectedPartNames, SORT_STRING);
        self::sortStringListMap($partNamesByIssue);
        self::sortStringListMap($selectedPartNamesByIssue);
        self::sortStringListMap($partNamesByContentTypeSource);
        self::sortStringListMap($selectedPartNamesByContentTypeSource);
        self::sortStringListMap($selectedPartNamesByMatchKind);

        return [
            'valid' => $issueCounts === [],
            'selectionPolicy' => 'caller-provided-part-list',
            'normalizesQueryAndFragment' => true,
            'matchesEquivalentPartNames' => true,
            'readsSelectedPartPayloadBytes' => false,
            'contentTypesItemPresent' => $contentTypesItemName !== null,
            'contentTypeDeclarationAvailable' => $contentTypes instanceof OpcContentTypes,
            'contentTypesParseError' => $contentTypesParseError,
            'selectedPartCount' => $selectedPartCount,
            'uniqueSelectedPartCount' => $uniqueSelectedPartCount,
            'duplicateSelectedPartCount' => $duplicateSelectedPartCount,
            'invalidSelectedPartCount' => $invalidSelectedPartCount,
            'existingSelectedPartCount' => $existingSelectedPartCount,
            'missingSelectedPartCount' => $missingSelectedPartCount,
            'exactSelectedPartCount' => $exactSelectedPartCount,
            'equivalentSelectedPartCount' => $equivalentSelectedPartCount,
            'contentTypeResolvedPartCount' => $contentTypeResolvedPartCount,
            'contentTypeDefaultResolvedPartCount' => $contentTypeDefaultResolvedPartCount,
            'contentTypeOverrideResolvedPartCount' => $contentTypeOverrideResolvedPartCount,
            'missingContentTypePartCount' => $missingContentTypePartCount,
            'missingContentTypeDefaultCount' => $missingContentTypeDefaultCount,
            'missingContentTypeExtensionlessCount' => $missingContentTypeExtensionlessCount,
            'missingContentTypeParts' => $missingContentTypeParts,
            'missingSelectedPartNames' => $missingSelectedPartNames,
            'duplicateSelectedPartNames' => $duplicateSelectedPartNames,
            'invalidSelectedPartNames' => $invalidSelectedPartNames,
            'issueCounts' => $issueCounts,
            'issues' => $issues,
            'partNamesByIssue' => $partNamesByIssue,
            'selectedPartNamesByIssue' => $selectedPartNamesByIssue,
            'contentTypeSourceCounts' => $contentTypeSourceCounts,
            'partNamesByContentTypeSource' => $partNamesByContentTypeSource,
            'selectedPartNamesByContentTypeSource' => $selectedPartNamesByContentTypeSource,
            'selectedPartNamesByMatchKind' => $selectedPartNamesByMatchKind,
            'records' => $records,
        ];
    }

    /**
     * @return array{valid:bool, entryCount:int, packagePartCount:int, directoryEntryCount:int, invalidPartCount:int, invalidPartNames:list<string>, issues:list<string>, parts:list<array{entryName:string, partName:?string, isDirectory:bool, isPackagePart:bool, valid:bool, issues:list<string>, parseError:?string}>}
     */
    public static function preflightPackagePartNames(ZipPackage $package): array
    {
        $parts = [];
        $issues = [];
        $invalidPartNames = [];
        $packagePartCount = 0;
        $directoryEntryCount = 0;

        foreach ($package->names() as $name) {
            $isDirectory = str_ends_with($name, '/');
            $partName = null;
            $partIssues = [];
            $parseError = null;
            if ($isDirectory) {
                $directoryEntryCount++;
            } else {
                $packagePartCount++;
                try {
                    $partName = OpcPackagePath::canonicalPartName($name);
                } catch (\InvalidArgumentException $exception) {
                    $parseError = $exception->getMessage();
                    $partIssues = self::packagePartNameIssuesForParseError($parseError);
                }
            }

            if ($partIssues !== []) {
                $invalidPartNames[] = $name;
                foreach ($partIssues as $issue) {
                    self::appendUniqueString($issues, $issue);
                }
            }

            $parts[] = [
                'entryName' => $name,
                'partName' => $partName,
                'isDirectory' => $isDirectory,
                'isPackagePart' => !$isDirectory,
                'valid' => $partIssues === [],
                'issues' => $partIssues,
                'parseError' => $parseError,
            ];
        }

        return [
            'valid' => $invalidPartNames === [],
            'entryCount' => count($parts),
            'packagePartCount' => $packagePartCount,
            'directoryEntryCount' => $directoryEntryCount,
            'invalidPartCount' => count($invalidPartNames),
            'invalidPartNames' => $invalidPartNames,
            'issues' => $issues,
            'parts' => $parts,
        ];
    }

    /**
     * @return array{valid:bool, isSupportedByBoundedReader:bool, entryCount:int, fileEntryCount:int, directoryEntryCount:int, packagePartCount:int, contentTypesItemCount:int, contentTypeDeclarationAvailable:bool, contentTypesParseError:?string, contentTypeResolvedPartCount:int, contentTypeDefaultResolvedPartCount:int, contentTypeOverrideResolvedPartCount:int, missingContentTypePartCount:int, missingContentTypeDefaultCount:int, missingContentTypeExtensionlessCount:int, missingContentTypeParts:list<string>, missingContentTypeExtensions:list<string>, contentTypeOverrideDeclarationCount:int, contentTypeUsedOverrideDeclarationCount:int, contentTypeUnusedOverrideDeclarationCount:int, contentTypeInvalidOverrideDeclarationCount:int, contentTypeUnusedOverridePartNames:list<string>, contentTypeOverrideDeclarationIssueCounts:array<string, int>, equivalentPackagePartNameGroupCount:int, equivalentPackagePartNameEntryCount:int, relationshipPartCount:int, rootRelationshipPartCount:int, partRelationshipPartCount:int, invalidRelationshipPartCount:int, reservedRelationshipDirectoryPartCount:int, orphanRelationshipPartCount:int, relationshipPartSourceCount:int, contentTypesItemRelationshipSourceCount:int, documentPropertyPartCount:int, digitalSignaturePartCount:int, embeddedPackageCandidateCount:int, mediaPartCandidateCount:int, xmlPayloadPartCount:int, binaryPayloadPartCount:int, issueCounts:array<string, int>, issues:list<string>, entryNamesByIssue:array<string, list<string>>, partNamesByIssue:array<string, list<string>>, roleCounts:array<string, int>, contentTypesItems:list<string>, largestPayloadEntryLimit:int, largestPayloadEntryCount:int, largestPayloadEntries:list<array{entryName:string, partName:?string, role:string, handoffKind:string, compressionMethod:int, compressionMethodName:string, compressedSize:int, uncompressedSize:int}>, equivalentPackagePartNameGroups:list<array{equivalenceKey:string, partNames:list<string>, entryNames:list<string>}>, relationshipParts:list<array{entryName:string, partName:string, relationshipSource:?string, relationshipSourceExists:?bool, issues:list<string>}>, contentTypeOverrideDeclarations:list<array{partName:string, contentType:string, exists:bool, packagePartName:?string, matchKind:string, partNameExactMatch:bool, partNameEquivalentMatch:bool, relationshipPart:bool, relationshipSource:?string, relationshipSourceExists:?bool, valid:bool, issues:list<string>}>, entries:list<array{entryIndex:int, entryName:string, partName:?string, equivalenceKey:?string, equivalentPartNames:list<string>, isDirectory:bool, isPackagePart:bool, compressionMethod:int, compressedSize:int, uncompressedSize:int, crc32Hex:string, role:string, handoffKind:string, contentTypesItem:bool, contentType:?string, contentTypeSource:?string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string, contentTypeOverridePartNameExactMatch:?bool, contentTypeOverridePartNameEquivalentMatch:?bool, relationshipPart:bool, relationshipPartCandidate:bool, relationshipSource:?string, relationshipSourceExists:?bool, valid:bool, issues:list<string>, parseError:?string}>}
     */
    public static function preflightZipEntryManifest(ZipPackage $package): array
    {
        $entries = [];
        $contentTypesItems = [];
        $contentTypesEntryIndexes = [];
        $packagePartNamesByEquivalenceKey = [];
        $packagePartEntryIndexesByEquivalenceKey = [];
        $contentTypes = null;
        $contentTypesParseError = null;
        $localHeaderOrder = $package->localHeaderOrderPreflight();
        $packageManifest = $package->packageManifestPreflight();
        $zipExtraFields = null;
        $zipExtraFieldPreflightError = null;
        try {
            $zipExtraFields = $package->extraFieldPreflight();
        } catch (\Throwable $exception) {
            $zipExtraFieldPreflightError = $exception->getMessage();
        }
        $localHeaderOrderByCentralDirectoryIndex = [];
        foreach ($localHeaderOrder['entries'] as $orderEntry) {
            $localHeaderOrderByCentralDirectoryIndex[$orderEntry['centralDirectoryIndex']] = $orderEntry;
        }
        $packageManifestEntriesByCentralDirectoryIndex = [];
        foreach ($packageManifest['entries'] as $manifestEntry) {
            $packageManifestEntriesByCentralDirectoryIndex[$manifestEntry['centralDirectoryIndex']] = $manifestEntry;
        }
        $zipExtraFieldsByCentralDirectoryIndex = [];
        if (is_array($zipExtraFields)) {
            foreach ($zipExtraFields['entries'] as $extraFieldIndex => $extraFieldEntry) {
                $zipExtraFieldsByCentralDirectoryIndex[$extraFieldIndex] = $extraFieldEntry;
            }
        }

        foreach ($package->entries() as $entryIndex => $entry) {
            $isDirectory = $entry->isDirectory();
            $partName = null;
            $equivalenceKey = null;
            $parseError = null;
            $issues = [];
            $contentTypesItem = false;
            $orderEntry = $localHeaderOrderByCentralDirectoryIndex[$entryIndex] ?? null;
            $manifestEntry = $packageManifestEntriesByCentralDirectoryIndex[$entryIndex] ?? [];
            $zipExtraFieldEntry = $zipExtraFieldsByCentralDirectoryIndex[$entryIndex] ?? null;
            $zipExtraFieldEntryFields = self::zipExtraFieldManifestEntryFields($zipExtraFieldEntry);
            $issues = array_values(array_unique(array_merge(
                $issues,
                $zipExtraFieldEntryFields['zipExtraFieldIssues']
            )));
            $centralDirectoryRecordOffset = $manifestEntry['centralDirectoryRecordOffset']
                ?? $entry->centralDirectoryRecordOffset;
            $centralDirectoryRecordEnd = $manifestEntry['centralDirectoryRecordEnd']
                ?? $entry->centralDirectoryRecordEnd;
            $centralDirectoryRecordBytes = is_int($centralDirectoryRecordOffset) && is_int($centralDirectoryRecordEnd)
                ? max(0, $centralDirectoryRecordEnd - $centralDirectoryRecordOffset)
                : null;
            $localHeaderOffset = $orderEntry['localHeaderOffset'] ?? $entry->localHeaderOffset;

            if (!$isDirectory) {
                try {
                    $partName = OpcPackagePath::canonicalPartName($entry->name);
                    $equivalenceKey = self::partNameEquivalenceKey($partName);
                    $packagePartNamesByEquivalenceKey[$equivalenceKey] = $partName;
                    $packagePartEntryIndexesByEquivalenceKey[$equivalenceKey][] = $entryIndex;
                    if (self::isContentTypesItemName($partName)) {
                        $contentTypesItem = true;
                        $contentTypesItems[] = $partName;
                        $contentTypesEntryIndexes[] = $entryIndex;
                    }
                } catch (\InvalidArgumentException $exception) {
                    $parseError = $exception->getMessage();
                    $issues = self::packagePartNameIssuesForParseError($parseError);
                }
            }

            $entries[] = [
                'entryIndex' => $entryIndex,
                'entryName' => $entry->name,
                'directoryRoot' => self::zipEntryManifestDirectoryRoot($entry->name),
                'pathSegments' => $manifestEntry['pathSegments'] ?? [],
                'pathSegmentPositionReviews' => $manifestEntry['pathSegmentPositionReviews'] ?? [],
                'pathSegmentCount' => $manifestEntry['pathSegmentCount'] ?? 0,
                'directoryDepth' => $manifestEntry['directoryDepth'] ?? 0,
                'versionMadeBy' => $manifestEntry['versionMadeBy'] ?? null,
                'madeByHostSystem' => $manifestEntry['madeByHostSystem'] ?? null,
                'madeByHostSystemName' => $manifestEntry['madeByHostSystemName'] ?? null,
                'madeByVersion' => $manifestEntry['madeByVersion'] ?? null,
                'versionNeededToExtract' => $manifestEntry['versionNeededToExtract'] ?? null,
                'creatorVersionMeetsNeeded' => $manifestEntry['creatorVersionMeetsNeeded'] ?? null,
                'creatorVersionComparison' => $manifestEntry['creatorVersionComparison'] ?? null,
                'creatorVersionDelta' => $manifestEntry['creatorVersionDelta'] ?? null,
                'creatorHostSystemIsKnown' => $manifestEntry['creatorHostSystemIsKnown'] ?? null,
                'creatorHostSystemIssues' => $manifestEntry['creatorHostSystemIssues'] ?? [],
                'partName' => $partName,
                'equivalenceKey' => $equivalenceKey,
                'equivalentPartNames' => [],
                'centralDirectoryIndex' => $entryIndex,
                'centralDirectoryRecordOffset' => $centralDirectoryRecordOffset,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryRecordEnd' => $centralDirectoryRecordEnd,
                'centralDirectoryRecordSha256' => $manifestEntry['centralDirectoryRecordSha256'] ?? null,
                'centralDirectoryFixedHeaderBytes' => $manifestEntry['centralDirectoryFixedHeaderBytes'] ?? null,
                'centralDirectoryVariableFieldOffset' => $manifestEntry['centralDirectoryVariableFieldOffset'] ?? null,
                'centralDirectoryVariableFieldBytes' => $manifestEntry['centralDirectoryVariableFieldBytes'] ?? null,
                'centralDirectoryVariableFieldSha256' => $manifestEntry['centralDirectoryVariableFieldSha256'] ?? null,
                'centralDirectoryRawNameOffset' => $manifestEntry['centralDirectoryRawNameOffset'] ?? null,
                'centralDirectoryRawNameBytes' => $manifestEntry['centralDirectoryRawNameBytes'] ?? null,
                'centralDirectoryRawNameSha256' => $manifestEntry['centralDirectoryRawNameSha256'] ?? null,
                'centralDirectoryExtraFieldOffset' => $manifestEntry['centralDirectoryExtraFieldOffset'] ?? null,
                'centralDirectoryExtraFieldBytes' => $manifestEntry['centralDirectoryExtraFieldBytes'] ?? null,
                'centralDirectoryExtraFieldSha256' => $manifestEntry['centralDirectoryExtraFieldSha256'] ?? null,
                ...$zipExtraFieldEntryFields,
                'centralDirectoryRawCommentOffset' => $manifestEntry['centralDirectoryRawCommentOffset'] ?? null,
                'centralDirectoryRawCommentBytes' => $manifestEntry['centralDirectoryRawCommentBytes'] ?? null,
                'centralDirectoryRawCommentSha256' => $manifestEntry['centralDirectoryRawCommentSha256'] ?? null,
                'centralDirectoryReviewFieldBytes' => $manifestEntry['centralDirectoryReviewFieldBytes'] ?? null,
                'localHeaderOrder' => $orderEntry['localHeaderOrder'] ?? $entryIndex,
                'localHeaderOffset' => $localHeaderOffset,
                'localHeaderLength' => $manifestEntry['localHeaderLength'] ?? null,
                'localHeaderSha256' => $manifestEntry['localHeaderSha256'] ?? null,
                'localHeaderFixedHeaderBytes' => $manifestEntry['localHeaderFixedHeaderBytes'] ?? null,
                'localHeaderVariableFieldOffset' => $manifestEntry['localHeaderVariableFieldOffset'] ?? null,
                'localHeaderVariableFieldBytes' => $manifestEntry['localHeaderVariableFieldBytes'] ?? null,
                'localHeaderVariableFieldSha256' => $manifestEntry['localHeaderVariableFieldSha256'] ?? null,
                'localHeaderRawNameOffset' => $manifestEntry['localHeaderRawNameOffset'] ?? null,
                'localHeaderRawNameBytes' => $manifestEntry['localHeaderRawNameBytes'] ?? null,
                'localHeaderRawNameSha256' => $manifestEntry['localHeaderRawNameSha256'] ?? null,
                'localHeaderExtraFieldOffset' => $manifestEntry['localHeaderExtraFieldOffset'] ?? null,
                'localHeaderExtraFieldBytes' => $manifestEntry['localHeaderExtraFieldBytes'] ?? null,
                'localHeaderExtraFieldSha256' => $manifestEntry['localHeaderExtraFieldSha256'] ?? null,
                'localHeaderReviewFieldBytes' => $manifestEntry['localHeaderReviewFieldBytes'] ?? null,
                'localRecordOffset' => $manifestEntry['localRecordOffset'] ?? null,
                'localRecordBytes' => $manifestEntry['localRecordBytes'] ?? null,
                'localRecordEnd' => $manifestEntry['localRecordEnd'] ?? null,
                'localRecordSha256' => $manifestEntry['localRecordSha256'] ?? null,
                'compressedDataOffset' => $manifestEntry['compressedDataOffset'] ?? null,
                'compressedDataEnd' => $manifestEntry['compressedDataEnd'] ?? null,
                'compressedDataSha256' => $manifestEntry['compressedDataSha256'] ?? null,
                'dataDescriptorOffset' => $manifestEntry['dataDescriptorOffset'] ?? null,
                'dataDescriptorBytes' => $manifestEntry['dataDescriptorBytes'] ?? 0,
                'usesDataDescriptor' => $manifestEntry['usesDataDescriptor'] ?? (($entry->generalPurposeFlags & 0x0008) !== 0),
                'dataDescriptorEnd' => $manifestEntry['dataDescriptorEnd'] ?? null,
                'dataDescriptorSha256' => $manifestEntry['dataDescriptorSha256'] ?? null,
                'sourceRecordBytes' => $manifestEntry['sourceRecordBytes'] ?? null,
                'localHeaderNameAtCentralDirectoryIndex' => $orderEntry['localHeaderNameAtCentralDirectoryIndex'] ?? $entry->name,
                'centralDirectoryNameAtLocalHeaderOrder' => $orderEntry['centralDirectoryNameAtLocalHeaderOrder'] ?? $entry->name,
                'matchesCentralDirectoryOrder' => $orderEntry['matchesCentralDirectoryOrder'] ?? true,
                'isDirectory' => $isDirectory,
                'isPackagePart' => !$isDirectory,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::zipCompressionMethodName($entry->compressionMethod),
                'generalPurposeFlags' => $entry->generalPurposeFlags,
                ...self::zipGeneralPurposeFlagManifestEntry($entry->generalPurposeFlags),
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
                'byteCountsAreExact' => true,
                'crc32Hex' => $entry->crc32Hex(),
                'role' => $isDirectory ? 'directory' : 'package-part',
                'handoffKind' => $isDirectory ? 'directory' : 'binary',
                'contentTypesItem' => $contentTypesItem,
                'contentType' => null,
                'contentTypeMediaType' => null,
                'contentTypeHasParameters' => false,
                'contentTypeParameterCount' => 0,
                'contentTypeParameterNames' => [],
                'contentTypeParameterMap' => [],
                'contentTypeParameters' => [],
                'contentTypeQuotedParameterCount' => 0,
                'contentTypeSource' => null,
                'contentTypeDefaultExtension' => null,
                'contentTypeOverridePartName' => null,
                'contentTypeOverridePartNameExactMatch' => null,
                'contentTypeOverridePartNameEquivalentMatch' => null,
                'relationshipPart' => false,
                'relationshipPartCandidate' => false,
                'relationshipSource' => null,
                'relationshipSourceExists' => null,
                'valid' => $issues === [],
                'issues' => $issues,
                'parseError' => $parseError,
            ];
        }

        $equivalentPackagePartNameGroups = [];
        $equivalentPackagePartNameEntryCount = 0;
        foreach ($packagePartEntryIndexesByEquivalenceKey as $equivalenceKey => $entryIndexes) {
            if (count($entryIndexes) < 2) {
                continue;
            }

            $partNames = [];
            $entryNames = [];
            foreach ($entryIndexes as $entryIndex) {
                $partName = $entries[$entryIndex]['partName'];
                if ($partName !== null) {
                    $partNames[] = $partName;
                    $entryNames[] = $entries[$entryIndex]['entryName'];
                }
            }

            sort($partNames, SORT_STRING);
            sort($entryNames, SORT_STRING);
            $equivalentPackagePartNameEntryCount += count($entryIndexes);
            $equivalentPackagePartNameGroups[] = [
                'equivalenceKey' => $equivalenceKey,
                'partNames' => $partNames,
                'entryNames' => $entryNames,
            ];

            foreach ($entryIndexes as $entryIndex) {
                $entries[$entryIndex]['equivalentPartNames'] = $partNames;
                $entries[$entryIndex]['issues'][] = 'equivalent-part-name-case-collision';
            }
        }

        usort(
            $equivalentPackagePartNameGroups,
            static fn (array $left, array $right): int => $left['equivalenceKey'] <=> $right['equivalenceKey'],
        );

        if (count($contentTypesEntryIndexes) > 1) {
            foreach ($contentTypesEntryIndexes as $entryIndex) {
                $entries[$entryIndex]['issues'][] = 'duplicate-content-types-item';
            }
        }

        if (count($contentTypesEntryIndexes) === 1) {
            $contentTypesEntry = $entries[$contentTypesEntryIndexes[0]];
            try {
                $contentTypes = OpcContentTypes::fromXml($package->read($contentTypesEntry['entryName']));
            } catch (\Throwable $exception) {
                $contentTypesParseError = $exception->getMessage();
            }
        }

        $contentTypeOverrideDeclarations = [];
        $contentTypeUnusedOverridePartNames = [];
        $contentTypeExactOverridePartNames = [];
        $contentTypeEquivalentOverridePartNames = [];
        $contentTypeInvalidOverridePartNames = [];
        $contentTypeRelationshipOverridePartNames = [];
        $contentTypeRelationshipContentTypePartNames = [];
        $contentTypeNonRelationshipRelationshipContentTypePartNames = [];
        $contentTypeContentTypesItemOverridePartNames = [];
        $contentTypeReservedRelationshipDirectoryOverridePartNames = [];
        $contentTypeOverrideDeclarationIssueCounts = [];
        if ($contentTypes instanceof OpcContentTypes) {
            foreach ($contentTypes->overrides() as $overridePartName => $contentType) {
                $packagePartName = $packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($overridePartName)] ?? null;
                $exists = $packagePartName !== null;
                $relationshipPart = OpcRelationships::isRelationshipPartName($overridePartName);
                $relationshipContentType = self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE);
                $contentTypesItem = self::isContentTypesItemName($overridePartName);
                $reservedRelationshipDirectoryPart = !$relationshipPart
                    && self::isReservedRelationshipDirectoryPartName($overridePartName);
                $relationshipSource = null;
                $relationshipSourceExists = null;
                $relationshipSourceIsRelationshipPart = null;
                $issues = [];

                if (!$exists) {
                    $issues[] = 'override-target-missing-part';
                    $contentTypeUnusedOverridePartNames[] = $overridePartName;
                }

                if ($contentTypesItem) {
                    $issues[] = 'content-types-override-target';
                }

                if ($relationshipPart) {
                    $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($overridePartName);
                    $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                        && OpcRelationships::isRelationshipPartName($relationshipSource);
                    $relationshipSourceExists = $relationshipSource === '/'
                        || isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($relationshipSource)]);

                    if (!$relationshipContentType) {
                        $issues[] = 'invalid-relationship-content-type';
                    }

                    if ($relationshipSourceIsRelationshipPart) {
                        $issues[] = 'relationship-part-source';
                    }

                    if ($relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                        $issues[] = 'content-types-item-source';
                    }

                    if (!$relationshipSourceExists) {
                        $issues[] = 'relationship-override-source-missing';
                    }
                } elseif ($relationshipContentType) {
                    $issues[] = 'relationship-content-type-on-non-relationship-part';
                }

                if ($reservedRelationshipDirectoryPart) {
                    $issues[] = 'reserved-relationship-directory-override';
                }

                $issues = array_values(array_unique($issues));
                foreach ($issues as $issue) {
                    $contentTypeOverrideDeclarationIssueCounts[$issue] = ($contentTypeOverrideDeclarationIssueCounts[$issue] ?? 0) + 1;
                }

                $contentTypeOverrideDeclarations[] = [
                    'partName' => $overridePartName,
                    'contentType' => $contentType,
                    'exists' => $exists,
                    'packagePartName' => $packagePartName,
                    'matchKind' => $packagePartName === null ? 'missing' : ($packagePartName === $overridePartName ? 'exact' : 'equivalent'),
                    'partNameExactMatch' => $packagePartName === $overridePartName,
                    'partNameEquivalentMatch' => $packagePartName !== null && $packagePartName !== $overridePartName,
                    'relationshipPart' => $relationshipPart,
                    'relationshipContentType' => $relationshipContentType,
                    'contentTypesItem' => $contentTypesItem,
                    'reservedRelationshipDirectoryPart' => $reservedRelationshipDirectoryPart,
                    'relationshipSource' => $relationshipSource,
                    'relationshipSourceExists' => $relationshipSourceExists,
                    'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
            }
        }

        foreach ($entries as &$entry) {
            if ($entry['partName'] === null) {
                $entry['role'] = $entry['isDirectory'] ? 'directory' : 'invalid-opc-part';
                $entry['handoffKind'] = $entry['isDirectory'] ? 'directory' : 'blocked';
                $entry['valid'] = $entry['issues'] === [];
                continue;
            }

            $partName = $entry['partName'];
            if (!$entry['contentTypesItem'] && $contentTypes instanceof OpcContentTypes) {
                $contentTypeResolution = $contentTypes->contentTypeResolutionForPart($partName);
                $entry['contentType'] = $contentTypeResolution['contentType'];
                $entry['contentTypeMediaType'] = $contentTypeResolution['contentTypeMediaType'];
                $entry['contentTypeHasParameters'] = $contentTypeResolution['contentTypeHasParameters'];
                $entry['contentTypeParameterCount'] = $contentTypeResolution['contentTypeParameterCount'];
                $entry['contentTypeParameterNames'] = $contentTypeResolution['contentTypeParameterNames'];
                $entry['contentTypeParameterMap'] = $contentTypeResolution['contentTypeParameterMap'];
                $entry['contentTypeParameters'] = $contentTypeResolution['contentTypeParameters'];
                $entry['contentTypeQuotedParameterCount'] = $contentTypeResolution['contentTypeQuotedParameterCount'];
                $entry['contentTypeSource'] = $contentTypeResolution['contentTypeSource'];
                $entry['contentTypeDefaultExtension'] = $contentTypeResolution['defaultExtension'];
                $entry['contentTypeOverridePartName'] = $contentTypeResolution['overridePartName'];
                $entry['contentTypeOverridePartNameExactMatch'] = $contentTypeResolution['overridePartNameExactMatch'];
                $entry['contentTypeOverridePartNameEquivalentMatch'] = $contentTypeResolution['overridePartNameEquivalentMatch'];

                if ($contentTypeResolution['contentType'] === null) {
                    $entry['issues'][] = 'missing-content-type';
                    if (self::partNameExtension($partName) === '') {
                        $entry['issues'][] = 'missing-content-type-extension';
                    } else {
                        $entry['issues'][] = 'missing-content-type-default';
                    }
                }
            }

            $relationshipPartCandidate = self::isRelationshipPartNameCandidate($partName);
            $relationshipPart = false;
            $relationshipSource = null;
            $relationshipSourceExists = null;

            if ($relationshipPartCandidate) {
                if (OpcRelationships::isRelationshipPartName($partName)) {
                    try {
                        $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                        $relationshipPart = true;
                        $relationshipSourceExists = $relationshipSource === '/'
                            || isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($relationshipSource)]);

                        if ($relationshipSource !== '/' && OpcRelationships::isRelationshipPartName($relationshipSource)) {
                            $entry['issues'][] = 'relationship-part-source';
                        }

                        if ($relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                            $entry['issues'][] = 'content-types-item-source';
                        }

                        if (!$relationshipSourceExists) {
                            $entry['issues'][] = 'orphan-relationship-part';
                        }
                    } catch (\InvalidArgumentException $exception) {
                        $entry['parseError'] ??= $exception->getMessage();
                        $entry['issues'][] = 'invalid-relationship-part-name';
                    }
                } else {
                    $entry['issues'][] = 'invalid-relationship-part-name';
                }
            } elseif (self::isReservedRelationshipDirectoryPartName($partName)) {
                $entry['issues'][] = 'reserved-relationship-directory-part';
            }

            $entry['relationshipPart'] = $relationshipPart;
            $entry['relationshipPartCandidate'] = $relationshipPartCandidate;
            $entry['relationshipSource'] = $relationshipSource;
            $entry['relationshipSourceExists'] = $relationshipSourceExists;
            $entry['role'] = self::zipEntryManifestRole(
                $partName,
                $entry['isDirectory'],
                $entry['contentTypesItem'],
                $relationshipPart,
                $relationshipPartCandidate,
                $relationshipSource,
                $entry['contentType']
            );
            $entry['handoffKind'] = self::zipEntryManifestHandoffKind($entry['role'], $partName);
            $entry['issues'] = array_values(array_unique($entry['issues']));
            $entry['valid'] = $entry['issues'] === [];
        }
        unset($entry);

        $issues = [];
        $issueCounts = [];
        $entryNamesByIssue = [];
        $partNamesByIssue = [];
        if ($zipExtraFieldPreflightError !== null) {
            $issueCounts['extra-field-policy-preflight-error'] = 1;
            self::appendUniqueString($issues, 'extra-field-policy-preflight-error');
        }
        $roleCounts = [];
        $byteCountsByRole = [];
        $byteCountsByHandoffKind = [];
        $byteCountsByContentType = [];
        $byteCountsByContentTypeSource = [];
        $entryNamesByContentType = [];
        $entryNamesByContentTypeSource = [];
        $directoryRootCounts = [];
        $entryNamesByDirectoryRoot = [];
        $directoryRootSummariesByRoot = [];
        $contentTypeSummariesByType = [];
        $contentTypeSourceSummariesBySource = [];
        $packagePartExtensionCounts = [];
        $entryNamesByPackagePartExtension = [];
        $packagePartExtensionSummariesByExtension = [];
        $compressionMethodCounts = [];
        $entryNamesByCompressionMethod = [];
        $compressionMethodNamesByRole = [];
        $compressionMethodNamesByHandoffKind = [];
        $generalPurposeFlagCounts = [];
        $entryNamesByGeneralPurposeFlag = [];
        $generalPurposeFlagNamesByRole = [];
        $generalPurposeFlagNamesByHandoffKind = [];
        $generalPurposeFlagIssueCounts = [];
        $entryNamesByGeneralPurposeFlagIssue = [];
        $creatorHostSystemCounts = [];
        $entryNamesByCreatorHostSystem = [];
        $creatorHostSystemNamesByRole = [];
        $creatorHostSystemNamesByHandoffKind = [];
        $creatorVersionComparisonCounts = [
            'below-needed' => 0,
            'equals-needed' => 0,
            'above-needed' => 0,
        ];
        $entryNamesByCreatorVersionComparison = [];
        $creatorVersionComparisonsByRole = [];
        $creatorVersionComparisonsByHandoffKind = [];
        $creatorHostSystemIssueCounts = [];
        $entryNamesByCreatorHostSystemIssue = [];
        $crc32SummariesByHex = [];
        $pathSegmentPositionRoleEntryCounts = [];
        $entryNamesByPathSegmentPositionRole = [];
        $pathSegmentPositionHandoffKindEntryCounts = [];
        $entryNamesByPathSegmentPositionHandoffKind = [];
        $relationshipParts = [];
        $fileEntryCount = 0;
        $directoryEntryCount = 0;
        $packagePartCount = 0;
        $extensionlessPackagePartCount = 0;
        $fileCompressedBytes = 0;
        $fileUncompressedBytes = 0;
        $directoryCompressedBytes = 0;
        $directoryUncompressedBytes = 0;
        $relationshipPartCount = 0;
        $rootRelationshipPartCount = 0;
        $partRelationshipPartCount = 0;
        $invalidRelationshipPartCount = 0;
        $reservedRelationshipDirectoryPartCount = 0;
        $orphanRelationshipPartCount = 0;
        $relationshipPartSourceCount = 0;
        $contentTypesItemRelationshipSourceCount = 0;
        $contentTypeResolvedPartCount = 0;
        $contentTypeDefaultResolvedPartCount = 0;
        $contentTypeOverrideResolvedPartCount = 0;
        $missingContentTypePartCount = 0;
        $missingContentTypeDefaultCount = 0;
        $missingContentTypeExtensionlessCount = 0;
        $missingContentTypeParts = [];
        $missingContentTypeExtensions = [];
        $documentPropertyPartCount = 0;
        $digitalSignaturePartCount = 0;
        $embeddedPackageCandidateCount = 0;
        $mediaPartCandidateCount = 0;
        $xmlPayloadPartCount = 0;
        $binaryPayloadPartCount = 0;
        $largestPayloadEntry = null;
        $payloadEntries = [];

        if ($contentTypesItems === []) {
            $issueCounts['missing-content-types-item'] = 1;
            $issues[] = 'missing-content-types-item';
            self::recordZipManifestIssueProvenance(
                $entryNamesByIssue,
                $partNamesByIssue,
                'missing-content-types-item',
                null,
                '/[Content_Types].xml',
            );
        }

        foreach ($entries as $entry) {
            if ($entry['isDirectory']) {
                $directoryEntryCount++;
                $directoryCompressedBytes += $entry['compressedSize'];
                $directoryUncompressedBytes += $entry['uncompressedSize'];
            } else {
                $fileEntryCount++;
                $fileCompressedBytes += $entry['compressedSize'];
                $fileUncompressedBytes += $entry['uncompressedSize'];
            }
            if ($entry['isPackagePart']) {
                $packagePartCount++;
            }

            $directoryRoot = $entry['directoryRoot'];
            $directoryRootCounts[$directoryRoot] = ($directoryRootCounts[$directoryRoot] ?? 0) + 1;
            $entryNamesByDirectoryRoot[$directoryRoot] ??= [];
            self::appendUniqueString($entryNamesByDirectoryRoot[$directoryRoot], $entry['entryName']);
            self::recordZipEntryManifestDirectoryRootSummary(
                $directoryRootSummariesByRoot,
                $directoryRoot,
                $entry,
            );

            if ($contentTypes instanceof OpcContentTypes && $entry['isPackagePart'] && !$entry['contentTypesItem']) {
                if ($entry['contentTypeSource'] === 'default') {
                    $contentTypeResolvedPartCount++;
                    $contentTypeDefaultResolvedPartCount++;
                } elseif ($entry['contentTypeSource'] === 'override') {
                    $contentTypeResolvedPartCount++;
                    $contentTypeOverrideResolvedPartCount++;
                } elseif ($entry['contentTypeSource'] === 'missing') {
                    $missingContentTypePartCount++;
                    $missingContentTypeParts[] = $entry['partName'] ?? $entry['entryName'];
                    $extension = $entry['partName'] === null ? '' : self::partNameExtension($entry['partName']);
                    if ($extension === '') {
                        $missingContentTypeExtensionlessCount++;
                    } else {
                        $missingContentTypeDefaultCount++;
                        self::appendUniqueString($missingContentTypeExtensions, $extension);
                    }
                }
            }

            $roleCounts[$entry['role']] = ($roleCounts[$entry['role']] ?? 0) + 1;
            self::recordZipEntryManifestPathSegmentPositionProvenance(
                $pathSegmentPositionRoleEntryCounts,
                $entryNamesByPathSegmentPositionRole,
                $pathSegmentPositionHandoffKindEntryCounts,
                $entryNamesByPathSegmentPositionHandoffKind,
                $entry,
            );
            self::incrementZipEntryManifestByteBucket(
                $byteCountsByRole,
                $entry['role'],
                $entry['compressedSize'],
                $entry['uncompressedSize'],
            );
            self::incrementZipEntryManifestByteBucket(
                $byteCountsByHandoffKind,
                $entry['handoffKind'],
                $entry['compressedSize'],
                $entry['uncompressedSize'],
            );
            if ($entry['isPackagePart'] && !$entry['contentTypesItem'] && $entry['partName'] !== null) {
                self::incrementZipEntryManifestByteBucket(
                    $byteCountsByContentTypeSource,
                    $entry['contentTypeSource'] ?? 'unavailable',
                    $entry['compressedSize'],
                    $entry['uncompressedSize'],
                );
                $contentTypeSource = $entry['contentTypeSource'] ?? 'unavailable';
                $entryNamesByContentTypeSource[$contentTypeSource] ??= [];
                self::appendUniqueString($entryNamesByContentTypeSource[$contentTypeSource], $entry['entryName']);
                self::recordZipEntryManifestContentTypeSourceSummary(
                    $contentTypeSourceSummariesBySource,
                    $contentTypeSource,
                    $entry,
                );
                if (is_string($entry['contentType'])) {
                    self::incrementZipEntryManifestByteBucket(
                        $byteCountsByContentType,
                        $entry['contentType'],
                        $entry['compressedSize'],
                        $entry['uncompressedSize'],
                    );
                    $entryNamesByContentType[$entry['contentType']] ??= [];
                    self::appendUniqueString($entryNamesByContentType[$entry['contentType']], $entry['entryName']);
                    self::recordZipEntryManifestContentTypeSummary(
                        $contentTypeSummariesByType,
                        $entry['contentType'],
                        $contentTypeSource,
                        $entry,
                    );
                }
            }
            if ($entry['isPackagePart'] && is_string($entry['partName'])) {
                $extension = self::partNameExtension($entry['partName']);
                $extensionKey = $extension === '' ? '(none)' : $extension;
                if ($extension === '') {
                    $extensionlessPackagePartCount++;
                }
                $packagePartExtensionCounts[$extensionKey] = ($packagePartExtensionCounts[$extensionKey] ?? 0) + 1;
                $entryNamesByPackagePartExtension[$extensionKey] ??= [];
                self::appendUniqueString($entryNamesByPackagePartExtension[$extensionKey], $entry['entryName']);
                self::recordZipEntryManifestExtensionSummary(
                    $packagePartExtensionSummariesByExtension,
                    $extensionKey,
                    $extension === '' ? null : $extension,
                    $entry,
                );
            }
            self::recordZipEntryManifestCompressionMethodProvenance(
                $compressionMethodCounts,
                $entryNamesByCompressionMethod,
                $compressionMethodNamesByRole,
                $compressionMethodNamesByHandoffKind,
                $entry['compressionMethodName'],
                $entry['entryName'],
                $entry['role'],
                $entry['handoffKind'],
            );
            self::recordZipEntryManifestGeneralPurposeFlagProvenance(
                $generalPurposeFlagCounts,
                $entryNamesByGeneralPurposeFlag,
                $generalPurposeFlagNamesByRole,
                $generalPurposeFlagNamesByHandoffKind,
                $generalPurposeFlagIssueCounts,
                $entryNamesByGeneralPurposeFlagIssue,
                $entry,
            );
            self::recordZipEntryManifestCreatorHostProvenance(
                $creatorHostSystemCounts,
                $entryNamesByCreatorHostSystem,
                $creatorHostSystemNamesByRole,
                $creatorHostSystemNamesByHandoffKind,
                $creatorVersionComparisonCounts,
                $entryNamesByCreatorVersionComparison,
                $creatorVersionComparisonsByRole,
                $creatorVersionComparisonsByHandoffKind,
                $creatorHostSystemIssueCounts,
                $entryNamesByCreatorHostSystemIssue,
                $entry,
            );
            self::recordZipEntryManifestCrc32Summary($crc32SummariesByHex, $entry);

            if (
                $largestPayloadEntry === null
                || $entry['uncompressedSize'] > $largestPayloadEntry['uncompressedSize']
            ) {
                $largestPayloadEntry = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'role' => $entry['role'],
                    'handoffKind' => $entry['handoffKind'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'compressedSize' => $entry['compressedSize'],
                    'uncompressedSize' => $entry['uncompressedSize'],
                ];
            }
            $payloadEntries[] = [
                'entryName' => $entry['entryName'],
                'partName' => $entry['partName'],
                'role' => $entry['role'],
                'handoffKind' => $entry['handoffKind'],
                'compressionMethod' => $entry['compressionMethod'],
                'compressionMethodName' => $entry['compressionMethodName'],
                'compressedSize' => $entry['compressedSize'],
                'uncompressedSize' => $entry['uncompressedSize'],
            ];

            foreach ($entry['issues'] as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                self::appendUniqueString($issues, $issue);
                self::recordZipManifestIssueProvenance(
                    $entryNamesByIssue,
                    $partNamesByIssue,
                    $issue,
                    $entry['entryName'],
                    $entry['partName'],
                );
            }

            if ($entry['relationshipPart']) {
                $relationshipPartCount++;
                if ($entry['relationshipSource'] === '/') {
                    $rootRelationshipPartCount++;
                } else {
                    $partRelationshipPartCount++;
                }

                $relationshipParts[] = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'relationshipSource' => $entry['relationshipSource'],
                    'relationshipSourceExists' => $entry['relationshipSourceExists'],
                    'issues' => $entry['issues'],
                ];
            }

            if (in_array('invalid-relationship-part-name', $entry['issues'], true)) {
                $invalidRelationshipPartCount++;
            }

            if (in_array('reserved-relationship-directory-part', $entry['issues'], true)) {
                $reservedRelationshipDirectoryPartCount++;
            }

            if (in_array('orphan-relationship-part', $entry['issues'], true)) {
                $orphanRelationshipPartCount++;
            }

            if (in_array('relationship-part-source', $entry['issues'], true)) {
                $relationshipPartSourceCount++;
            }

            if (in_array('content-types-item-source', $entry['issues'], true)) {
                $contentTypesItemRelationshipSourceCount++;
            }

            if ($entry['role'] === 'document-properties') {
                $documentPropertyPartCount++;
            } elseif ($entry['role'] === 'digital-signature') {
                $digitalSignaturePartCount++;
            } elseif ($entry['role'] === 'embedded-package-candidate') {
                $embeddedPackageCandidateCount++;
            } elseif ($entry['role'] === 'media') {
                $mediaPartCandidateCount++;
            }

            if (in_array($entry['handoffKind'], ['content-types+xml', 'relationships+xml', 'xml'], true)) {
                $xmlPayloadPartCount++;
            } elseif (!$entry['isDirectory'] && $entry['handoffKind'] !== 'blocked') {
                $binaryPayloadPartCount++;
            }
        }

        foreach ($contentTypeOverrideDeclarations as $declaration) {
            if ($declaration['partNameExactMatch']) {
                self::appendUniqueString($contentTypeExactOverridePartNames, $declaration['partName']);
            } elseif ($declaration['partNameEquivalentMatch']) {
                self::appendUniqueString($contentTypeEquivalentOverridePartNames, $declaration['partName']);
            }

            if (!$declaration['valid']) {
                self::appendUniqueString($contentTypeInvalidOverridePartNames, $declaration['partName']);
            }

            if ($declaration['relationshipPart']) {
                self::appendUniqueString($contentTypeRelationshipOverridePartNames, $declaration['partName']);
            }

            if ($declaration['relationshipContentType']) {
                self::appendUniqueString($contentTypeRelationshipContentTypePartNames, $declaration['partName']);
                if (!$declaration['relationshipPart']) {
                    self::appendUniqueString($contentTypeNonRelationshipRelationshipContentTypePartNames, $declaration['partName']);
                }
            }

            if ($declaration['contentTypesItem']) {
                self::appendUniqueString($contentTypeContentTypesItemOverridePartNames, $declaration['partName']);
            }

            if ($declaration['reservedRelationshipDirectoryPart']) {
                self::appendUniqueString($contentTypeReservedRelationshipDirectoryOverridePartNames, $declaration['partName']);
            }

            foreach ($declaration['issues'] as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                self::appendUniqueString($issues, $issue);
                self::recordZipManifestIssueProvenance(
                    $entryNamesByIssue,
                    $partNamesByIssue,
                    $issue,
                    null,
                    $declaration['partName'],
                );
            }
        }

        ksort($issueCounts);
        self::sortZipManifestIssueProvenance($entryNamesByIssue);
        self::sortZipManifestIssueProvenance($partNamesByIssue);
        ksort($contentTypeOverrideDeclarationIssueCounts);
        ksort($roleCounts);
        self::sortZipEntryManifestPathSegmentPositionProvenance(
            $pathSegmentPositionRoleEntryCounts,
            $entryNamesByPathSegmentPositionRole,
            $pathSegmentPositionHandoffKindEntryCounts,
            $entryNamesByPathSegmentPositionHandoffKind,
        );
        ksort($byteCountsByRole);
        ksort($byteCountsByHandoffKind);
        ksort($byteCountsByContentType);
        ksort($byteCountsByContentTypeSource);
        ksort($directoryRootCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByContentType);
        self::sortStringListMap($entryNamesByContentTypeSource);
        self::sortStringListMap($entryNamesByDirectoryRoot);
        $directoryRootSummaries = self::zipEntryManifestDirectoryRootSummaries($directoryRootSummariesByRoot);
        $contentTypeSummaries = self::zipEntryManifestContentSummaries($contentTypeSummariesByType);
        $contentTypeSourceSummaries = self::zipEntryManifestContentSummaries($contentTypeSourceSummariesBySource);
        ksort($packagePartExtensionCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByPackagePartExtension);
        $packagePartExtensionSummaries = self::zipEntryManifestContentSummaries($packagePartExtensionSummariesByExtension);
        self::sortZipManifestCompressionMethodProvenance(
            $compressionMethodCounts,
            $entryNamesByCompressionMethod,
            $compressionMethodNamesByRole,
            $compressionMethodNamesByHandoffKind,
        );
        self::sortZipManifestGeneralPurposeFlagProvenance(
            $generalPurposeFlagCounts,
            $entryNamesByGeneralPurposeFlag,
            $generalPurposeFlagNamesByRole,
            $generalPurposeFlagNamesByHandoffKind,
            $generalPurposeFlagIssueCounts,
            $entryNamesByGeneralPurposeFlagIssue,
        );
        self::sortZipManifestCreatorHostProvenance(
            $creatorHostSystemCounts,
            $entryNamesByCreatorHostSystem,
            $creatorHostSystemNamesByRole,
            $creatorHostSystemNamesByHandoffKind,
            $creatorVersionComparisonCounts,
            $entryNamesByCreatorVersionComparison,
            $creatorVersionComparisonsByRole,
            $creatorVersionComparisonsByHandoffKind,
            $creatorHostSystemIssueCounts,
            $entryNamesByCreatorHostSystemIssue,
        );
        $crc32Provenance = self::zipEntryManifestCrc32Provenance($crc32SummariesByHex);
        sort($contentTypesItems, SORT_STRING);
        sort($contentTypeUnusedOverridePartNames, SORT_STRING);
        sort($contentTypeExactOverridePartNames, SORT_STRING);
        sort($contentTypeEquivalentOverridePartNames, SORT_STRING);
        sort($contentTypeInvalidOverridePartNames, SORT_STRING);
        sort($contentTypeRelationshipOverridePartNames, SORT_STRING);
        sort($contentTypeRelationshipContentTypePartNames, SORT_STRING);
        sort($contentTypeNonRelationshipRelationshipContentTypePartNames, SORT_STRING);
        sort($contentTypeContentTypesItemOverridePartNames, SORT_STRING);
        sort($contentTypeReservedRelationshipDirectoryOverridePartNames, SORT_STRING);
        sort($missingContentTypeParts, SORT_STRING);
        sort($missingContentTypeExtensions, SORT_STRING);
        usort(
            $relationshipParts,
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );
        usort(
            $contentTypeOverrideDeclarations,
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );
        $largestPayloadEntries = self::largestZipManifestPayloadEntries(
            $payloadEntries,
            self::ZIP_MANIFEST_LARGEST_PAYLOAD_ENTRY_LIMIT
        );
        $zipSourceRecordManifest = self::zipEntrySourceRecordManifest($entries);

        return [
            'valid' => $issues === [],
            'isSupportedByBoundedReader' => $issues === [],
            'packageSource' => $packageManifest['packageSource'],
            'packageByteLayout' => $packageManifest['packageByteLayout'],
            'packageByteLayoutVersion' => $packageManifest['packageByteLayoutVersion'],
            'packageByteLayoutIssueCount' => $packageManifest['packageByteLayoutIssueCount'],
            'packageByteLayoutIssues' => $packageManifest['packageByteLayoutIssues'],
            'packageByteLayoutIsLocalRegionContiguous' => $packageManifest['packageByteLayoutIsLocalRegionContiguous'],
            'packageByteLayoutIsArchiveLayoutContiguous' => $packageManifest['packageByteLayoutIsArchiveLayoutContiguous'],
            'packageByteLayoutUnclaimedLocalBytes' => $packageManifest['packageByteLayoutUnclaimedLocalBytes'],
            'packageByteLayoutInterEntryGapCount' => $packageManifest['packageByteLayoutInterEntryGapCount'],
            'packageByteLayoutUnaccountedArchiveBytes' => $packageManifest['packageByteLayoutUnaccountedArchiveBytes'],
            'packageByteLayoutTrailingByteCount' => $packageManifest['packageByteLayoutTrailingByteCount'],
            'zipPackageManifestSha256' => $packageManifest['manifestSha256'],
            'archiveLength' => $packageManifest['archiveLength'],
            'archiveSha256' => $packageManifest['archiveSha256'],
            'centralDirectoryOffset' => $packageManifest['centralDirectoryOffset'],
            'centralDirectoryBytes' => $packageManifest['centralDirectoryBytes'],
            'centralDirectoryEnd' => $packageManifest['centralDirectoryEnd'],
            'centralDirectorySha256' => $packageManifest['centralDirectorySha256'],
            'centralDirectoryToEocdGapOffset' => $packageManifest['centralDirectoryToEocdGapOffset'],
            'centralDirectoryToEocdGapBytes' => $packageManifest['centralDirectoryToEocdGapBytes'],
            'centralDirectoryToEocdGapSha256' => $packageManifest['centralDirectoryToEocdGapSha256'],
            'endOfCentralDirectoryOffset' => $packageManifest['endOfCentralDirectoryOffset'],
            'endOfCentralDirectoryBytes' => $packageManifest['endOfCentralDirectoryBytes'],
            'endOfCentralDirectoryEnd' => $packageManifest['endOfCentralDirectoryEnd'],
            'endOfCentralDirectorySha256' => $packageManifest['endOfCentralDirectorySha256'],
            'endOfCentralDirectoryFixedFields' => $packageManifest['endOfCentralDirectoryFixedFields'],
            'endOfCentralDirectoryFixedFieldIssueCount' => $packageManifest['endOfCentralDirectoryFixedFieldIssueCount'],
            'hasEndOfCentralDirectoryFixedFieldIssues' => $packageManifest['hasEndOfCentralDirectoryFixedFieldIssues'],
            'endOfCentralDirectoryFixedFieldIssues' => $packageManifest['endOfCentralDirectoryFixedFieldIssues'],
            'packageCommentOffset' => $packageManifest['packageCommentOffset'],
            'packageCommentBytes' => $packageManifest['packageCommentBytes'],
            'packageCommentEnd' => $packageManifest['packageCommentEnd'],
            'packageCommentSha256' => $packageManifest['packageCommentSha256'],
            'packageCommentPreviewHex' => $packageManifest['packageCommentPreviewHex'],
            'packageCommentPreviewByteCount' => $packageManifest['packageCommentPreviewByteCount'],
            'packageCommentByteExposurePolicy' => $packageManifest['packageCommentByteExposurePolicy'],
            'canExposePackageCommentBytes' => $packageManifest['canExposePackageCommentBytes'],
            'hasPackageComment' => $packageManifest['hasPackageComment'],
            'hasCentralDirectorySignature' => $packageManifest['hasCentralDirectorySignature'],
            'centralDirectorySignatureOffset' => $packageManifest['centralDirectorySignatureOffset'],
            'centralDirectorySignatureDataOffset' => $packageManifest['centralDirectorySignatureDataOffset'],
            'centralDirectorySignatureEnd' => $packageManifest['centralDirectorySignatureEnd'],
            'centralDirectorySignatureBytes' => $packageManifest['centralDirectorySignatureBytes'],
            'centralDirectorySignatureRecordBytes' => $packageManifest['centralDirectorySignatureRecordBytes'],
            'centralDirectorySignaturePreviewHex' => $packageManifest['centralDirectorySignaturePreviewHex'],
            'centralDirectorySignaturePreviewByteCount' => $packageManifest['centralDirectorySignaturePreviewByteCount'],
            'centralDirectorySignatureSha256' => $packageManifest['centralDirectorySignatureSha256'],
            'centralDirectorySignatureLocation' => $packageManifest['centralDirectorySignatureLocation'],
            'centralDirectorySignatureVerification' => $packageManifest['centralDirectorySignatureVerification'],
            'centralDirectorySignatureByteExposurePolicy' => $packageManifest['centralDirectorySignatureByteExposurePolicy'],
            'centralDirectorySignatureCanExposeBytes' => $packageManifest['centralDirectorySignatureCanExposeBytes'],
            'entryCount' => count($entries),
            'fileEntryCount' => $fileEntryCount,
            'directoryEntryCount' => $directoryEntryCount,
            'packagePartCount' => $packagePartCount,
            'compressedPayloadBytes' => $fileCompressedBytes + $directoryCompressedBytes,
            'uncompressedPayloadBytes' => $fileUncompressedBytes + $directoryUncompressedBytes,
            'fileCompressedBytes' => $fileCompressedBytes,
            'fileUncompressedBytes' => $fileUncompressedBytes,
            'directoryCompressedBytes' => $directoryCompressedBytes,
            'directoryUncompressedBytes' => $directoryUncompressedBytes,
            'zipSourceRecordManifestVersion' => $zipSourceRecordManifest['manifestVersion'],
            'zipSourceRecordManifestSha256' => $zipSourceRecordManifest['manifestSha256'],
            'zipSourceRecordByteCountsAreExact' => $zipSourceRecordManifest['byteCountsAreExact'],
            'zipSourceRecordEntryCount' => $zipSourceRecordManifest['entryCount'],
            'zipSourceRecordUnknownEntryCount' => $zipSourceRecordManifest['unknownSourceRecordEntryCount'],
            'zipSourceRecordLocalRecordBytes' => $zipSourceRecordManifest['localRecordBytes'],
            'zipSourceRecordKnownLocalRecordBytes' => $zipSourceRecordManifest['knownLocalRecordBytes'],
            'zipSourceRecordLocalHeaderBytes' => $zipSourceRecordManifest['localHeaderBytes'],
            'zipSourceRecordCompressedDataBytes' => $zipSourceRecordManifest['compressedDataBytes'],
            'zipSourceRecordDataDescriptorBytes' => $zipSourceRecordManifest['dataDescriptorBytes'],
            'zipSourceRecordDataDescriptorEntryCount' => $zipSourceRecordManifest['dataDescriptorEntryCount'],
            'zipSourceRecordCentralDirectoryRecordBytes' => $zipSourceRecordManifest['centralDirectoryRecordBytes'],
            'zipSourceRecordKnownCentralDirectoryRecordBytes' => $zipSourceRecordManifest['knownCentralDirectoryRecordBytes'],
            'zipSourceRecordBytes' => $zipSourceRecordManifest['sourceRecordBytes'],
            'zipSourceRecordKnownBytes' => $zipSourceRecordManifest['knownSourceRecordBytes'],
            'zipSourceRecordReviewFieldByteCountsAreExact' => $zipSourceRecordManifest['reviewFieldByteCountsAreExact'],
            'zipSourceRecordLocalHeaderReviewFieldBytes' => $zipSourceRecordManifest['localHeaderReviewFieldBytes'],
            'zipSourceRecordKnownLocalHeaderReviewFieldBytes' => $zipSourceRecordManifest['knownLocalHeaderReviewFieldBytes'],
            'zipSourceRecordCentralDirectoryReviewFieldBytes' => $zipSourceRecordManifest['centralDirectoryReviewFieldBytes'],
            'zipSourceRecordKnownCentralDirectoryReviewFieldBytes' => $zipSourceRecordManifest['knownCentralDirectoryReviewFieldBytes'],
            'zipSourceRecordReviewFieldBytes' => $zipSourceRecordManifest['reviewFieldBytes'],
            'zipSourceRecordKnownReviewFieldBytes' => $zipSourceRecordManifest['knownReviewFieldBytes'],
            'zipSourceRecordReviewFieldBytesByRole' => $zipSourceRecordManifest['reviewFieldBytesByRole'],
            'zipSourceRecordReviewFieldBytesByHandoffKind' => $zipSourceRecordManifest['reviewFieldBytesByHandoffKind'],
            'zipSourceRecordHandoffKindSummaryCount' => $zipSourceRecordManifest['handoffKindSummaryCount'],
            'zipSourceRecordHandoffKindSummaries' => $zipSourceRecordManifest['handoffKindSummaries'],
            'zipSourceRecordManifest' => $zipSourceRecordManifest,
            ...self::zipExtraFieldManifestSummaryFields($zipExtraFields, $zipExtraFieldPreflightError),
            'directoryRootCount' => count($directoryRootCounts),
            'directoryRootCounts' => $directoryRootCounts,
            'entryNamesByDirectoryRoot' => $entryNamesByDirectoryRoot,
            'directoryRootSummaries' => $directoryRootSummaries,
            'pathSegmentPositionSummaryCount' => $packageManifest['pathSegmentPositionSummaryCount'],
            'pathSegmentPositionOccurrenceCount' => $packageManifest['pathSegmentPositionOccurrenceCount'],
            'pathSegmentPositionCounts' => $packageManifest['pathSegmentPositionCounts'],
            'pathSegmentPositionEntryCounts' => $packageManifest['pathSegmentPositionEntryCounts'],
            'pathSegmentPositionSummaries' => $packageManifest['pathSegmentPositionSummaries'],
            'pathSegmentPositionRoleEntryCounts' => $pathSegmentPositionRoleEntryCounts,
            'entryNamesByPathSegmentPositionRole' => $entryNamesByPathSegmentPositionRole,
            'pathSegmentPositionHandoffKindEntryCounts' => $pathSegmentPositionHandoffKindEntryCounts,
            'entryNamesByPathSegmentPositionHandoffKind' => $entryNamesByPathSegmentPositionHandoffKind,
            'contentTypesItemCount' => count($contentTypesItems),
            'contentTypeDeclarationAvailable' => $contentTypes instanceof OpcContentTypes,
            'contentTypesParseError' => $contentTypesParseError,
            'contentTypeResolvedPartCount' => $contentTypeResolvedPartCount,
            'contentTypeDefaultResolvedPartCount' => $contentTypeDefaultResolvedPartCount,
            'contentTypeOverrideResolvedPartCount' => $contentTypeOverrideResolvedPartCount,
            'missingContentTypePartCount' => $missingContentTypePartCount,
            'missingContentTypeDefaultCount' => $missingContentTypeDefaultCount,
            'missingContentTypeExtensionlessCount' => $missingContentTypeExtensionlessCount,
            'missingContentTypeParts' => $missingContentTypeParts,
            'missingContentTypeExtensions' => $missingContentTypeExtensions,
            'extensionlessPackagePartCount' => $extensionlessPackagePartCount,
            'packagePartExtensionCounts' => $packagePartExtensionCounts,
            'entryNamesByPackagePartExtension' => $entryNamesByPackagePartExtension,
            'packagePartExtensionSummaries' => $packagePartExtensionSummaries,
            'contentTypeOverrideDeclarationCount' => count($contentTypeOverrideDeclarations),
            'contentTypeUsedOverrideDeclarationCount' => count($contentTypeOverrideDeclarations) - count($contentTypeUnusedOverridePartNames),
            'contentTypeUnusedOverrideDeclarationCount' => count($contentTypeUnusedOverridePartNames),
            'contentTypeExactOverrideDeclarationCount' => count($contentTypeExactOverridePartNames),
            'contentTypeEquivalentOverrideDeclarationCount' => count($contentTypeEquivalentOverridePartNames),
            'contentTypeInvalidOverrideDeclarationCount' => count(array_filter(
                $contentTypeOverrideDeclarations,
                static fn (array $declaration): bool => !$declaration['valid']
            )),
            'contentTypeRelationshipOverrideDeclarationCount' => count($contentTypeRelationshipOverridePartNames),
            'contentTypeRelationshipContentTypeDeclarationCount' => count($contentTypeRelationshipContentTypePartNames),
            'contentTypeNonRelationshipRelationshipContentTypeDeclarationCount' => count($contentTypeNonRelationshipRelationshipContentTypePartNames),
            'contentTypeContentTypesItemOverrideDeclarationCount' => count($contentTypeContentTypesItemOverridePartNames),
            'contentTypeReservedRelationshipDirectoryOverrideDeclarationCount' => count($contentTypeReservedRelationshipDirectoryOverridePartNames),
            'contentTypeUnusedOverridePartNames' => $contentTypeUnusedOverridePartNames,
            'contentTypeExactOverridePartNames' => $contentTypeExactOverridePartNames,
            'contentTypeEquivalentOverridePartNames' => $contentTypeEquivalentOverridePartNames,
            'contentTypeInvalidOverridePartNames' => $contentTypeInvalidOverridePartNames,
            'contentTypeRelationshipOverridePartNames' => $contentTypeRelationshipOverridePartNames,
            'contentTypeRelationshipContentTypePartNames' => $contentTypeRelationshipContentTypePartNames,
            'contentTypeNonRelationshipRelationshipContentTypePartNames' => $contentTypeNonRelationshipRelationshipContentTypePartNames,
            'contentTypeContentTypesItemOverridePartNames' => $contentTypeContentTypesItemOverridePartNames,
            'contentTypeReservedRelationshipDirectoryOverridePartNames' => $contentTypeReservedRelationshipDirectoryOverridePartNames,
            'contentTypeOverrideDeclarationIssueCounts' => $contentTypeOverrideDeclarationIssueCounts,
            'equivalentPackagePartNameGroupCount' => count($equivalentPackagePartNameGroups),
            'equivalentPackagePartNameEntryCount' => $equivalentPackagePartNameEntryCount,
            'relationshipPartCount' => $relationshipPartCount,
            'rootRelationshipPartCount' => $rootRelationshipPartCount,
            'partRelationshipPartCount' => $partRelationshipPartCount,
            'invalidRelationshipPartCount' => $invalidRelationshipPartCount,
            'reservedRelationshipDirectoryPartCount' => $reservedRelationshipDirectoryPartCount,
            'orphanRelationshipPartCount' => $orphanRelationshipPartCount,
            'relationshipPartSourceCount' => $relationshipPartSourceCount,
            'contentTypesItemRelationshipSourceCount' => $contentTypesItemRelationshipSourceCount,
            'documentPropertyPartCount' => $documentPropertyPartCount,
            'digitalSignaturePartCount' => $digitalSignaturePartCount,
            'embeddedPackageCandidateCount' => $embeddedPackageCandidateCount,
            'mediaPartCandidateCount' => $mediaPartCandidateCount,
            'xmlPayloadPartCount' => $xmlPayloadPartCount,
            'binaryPayloadPartCount' => $binaryPayloadPartCount,
            'issueCounts' => $issueCounts,
            'issues' => $issues,
            'entryNamesByIssue' => $entryNamesByIssue,
            'partNamesByIssue' => $partNamesByIssue,
            'roleCounts' => $roleCounts,
            'byteCountsByRole' => $byteCountsByRole,
            'byteCountsByHandoffKind' => $byteCountsByHandoffKind,
            'byteCountsByContentType' => $byteCountsByContentType,
            'byteCountsByContentTypeSource' => $byteCountsByContentTypeSource,
            'entryNamesByContentType' => $entryNamesByContentType,
            'entryNamesByContentTypeSource' => $entryNamesByContentTypeSource,
            'contentTypeSummaries' => $contentTypeSummaries,
            'contentTypeSourceSummaries' => $contentTypeSourceSummaries,
            'compressionMethodCounts' => $compressionMethodCounts,
            'entryNamesByCompressionMethod' => $entryNamesByCompressionMethod,
            'compressionMethodNamesByRole' => $compressionMethodNamesByRole,
            'compressionMethodNamesByHandoffKind' => $compressionMethodNamesByHandoffKind,
            'generalPurposeFlagCounts' => $generalPurposeFlagCounts,
            'entryNamesByGeneralPurposeFlag' => $entryNamesByGeneralPurposeFlag,
            'generalPurposeFlagNamesByRole' => $generalPurposeFlagNamesByRole,
            'generalPurposeFlagNamesByHandoffKind' => $generalPurposeFlagNamesByHandoffKind,
            'generalPurposeFlagIssueCounts' => $generalPurposeFlagIssueCounts,
            'entryNamesByGeneralPurposeFlagIssue' => $entryNamesByGeneralPurposeFlagIssue,
            'creatorHostSystemCounts' => $creatorHostSystemCounts,
            'entryNamesByCreatorHostSystem' => $entryNamesByCreatorHostSystem,
            'creatorHostSystemNamesByRole' => $creatorHostSystemNamesByRole,
            'creatorHostSystemNamesByHandoffKind' => $creatorHostSystemNamesByHandoffKind,
            'creatorVersionComparisonCounts' => $creatorVersionComparisonCounts,
            'entryNamesByCreatorVersionComparison' => $entryNamesByCreatorVersionComparison,
            'creatorVersionComparisonsByRole' => $creatorVersionComparisonsByRole,
            'creatorVersionComparisonsByHandoffKind' => $creatorVersionComparisonsByHandoffKind,
            'creatorHostSystemIssueCounts' => $creatorHostSystemIssueCounts,
            'entryNamesByCreatorHostSystemIssue' => $entryNamesByCreatorHostSystemIssue,
            'crc32SummaryCount' => $crc32Provenance['crc32SummaryCount'],
            'crc32Summaries' => $crc32Provenance['crc32Summaries'],
            'crc32HexCounts' => $crc32Provenance['crc32HexCounts'],
            'entryNamesByCrc32Hex' => $crc32Provenance['entryNamesByCrc32Hex'],
            'crc32HexesByRole' => $crc32Provenance['crc32HexesByRole'],
            'crc32HexesByHandoffKind' => $crc32Provenance['crc32HexesByHandoffKind'],
            'duplicateCrc32HexCount' => $crc32Provenance['duplicateCrc32HexCount'],
            'duplicateCrc32EntryCount' => $crc32Provenance['duplicateCrc32EntryCount'],
            'hasDuplicateCrc32Entries' => $crc32Provenance['hasDuplicateCrc32Entries'],
            'duplicateCrc32Hexes' => $crc32Provenance['duplicateCrc32Hexes'],
            'duplicateCrc32Summaries' => $crc32Provenance['duplicateCrc32Summaries'],
            'largestPayloadEntry' => $largestPayloadEntry,
            'largestPayloadEntryLimit' => self::ZIP_MANIFEST_LARGEST_PAYLOAD_ENTRY_LIMIT,
            'largestPayloadEntryCount' => count($largestPayloadEntries),
            'largestPayloadEntries' => $largestPayloadEntries,
            'localHeaderOrder' => $localHeaderOrder,
            'contentTypesItems' => $contentTypesItems,
            'equivalentPackagePartNameGroups' => $equivalentPackagePartNameGroups,
            'relationshipParts' => $relationshipParts,
            'contentTypeOverrideDeclarations' => $contentTypeOverrideDeclarations,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function zipPackageSourcePreflightFromBytes(string $bytes): array
    {
        $archive = ZipPackage::endOfCentralDirectoryPreflight($bytes);
        $archiveLength = strlen($bytes);
        $centralDirectoryOffset = $archive['centralDirectoryOffset'];
        $centralDirectoryBytes = $archive['centralDirectorySize'];
        $centralDirectoryEnd = $archive['centralDirectoryEnd'];
        $endOfCentralDirectoryOffset = $archive['eocdOffset'];
        $packageCommentOffset = $endOfCentralDirectoryOffset + 22;
        $packageCommentBytes = $archive['packageCommentLength'];
        $packageCommentEnd = $packageCommentOffset + $packageCommentBytes;
        $packageCommentPreviewByteCount = min(16, $packageCommentBytes);
        $packageCommentPreviewHex = $packageCommentPreviewByteCount > 0
            ? bin2hex(substr($bytes, $packageCommentOffset, $packageCommentPreviewByteCount))
            : '';
        $packageCommentByteExposurePolicy = 'zip-package-comment-source-metadata-only';
        $canExposePackageCommentBytes = false;
        $endOfCentralDirectoryFixedFields = ZipPackage::endOfCentralDirectoryFixedFieldsPreflight($bytes);
        unset($endOfCentralDirectoryFixedFields['packageComment'], $endOfCentralDirectoryFixedFields['packageCommentHex']);
        $endOfCentralDirectoryFixedFields['packageCommentByteExposurePolicy'] = $packageCommentByteExposurePolicy;
        $endOfCentralDirectoryFixedFields['canExposePackageCommentBytes'] = $canExposePackageCommentBytes;
        $endOfCentralDirectoryFixedFieldIssues = $endOfCentralDirectoryFixedFields['issues'];
        $centralDirectoryToEocdGapBytes = max(0, $endOfCentralDirectoryOffset - $centralDirectoryEnd);
        $centralDirectoryToEocdGapOffset = $centralDirectoryToEocdGapBytes > 0 ? $centralDirectoryEnd : null;
        $endOfCentralDirectoryBytes = 22 + $packageCommentBytes;
        $endOfCentralDirectoryEnd = $endOfCentralDirectoryOffset + $endOfCentralDirectoryBytes;

        $centralDirectorySha256 = hash(
            'sha256',
            substr($bytes, $centralDirectoryOffset, $centralDirectoryBytes)
        );
        $centralDirectoryToEocdGapSha256 = $centralDirectoryToEocdGapBytes > 0
            ? hash('sha256', substr($bytes, $centralDirectoryEnd, $centralDirectoryToEocdGapBytes))
            : null;
        $endOfCentralDirectorySha256 = hash(
            'sha256',
            substr($bytes, $endOfCentralDirectoryOffset, $endOfCentralDirectoryBytes)
        );
        $packageCommentSha256 = $packageCommentBytes > 0
            ? hash('sha256', substr($bytes, $packageCommentOffset, $packageCommentBytes))
            : null;

        $centralDirectorySignatureOffset = null;
        $centralDirectorySignatureDataOffset = null;
        $centralDirectorySignatureEnd = null;
        $centralDirectorySignatureBytes = 0;
        $centralDirectorySignatureRecordBytes = 0;
        $centralDirectorySignaturePreviewHex = '';
        $centralDirectorySignaturePreviewByteCount = 0;
        $centralDirectorySignatureSha256 = null;
        $centralDirectorySignatureLocation = null;
        $centralDirectorySignatureVerification = 'not-present';
        $centralDirectorySignatureByteExposurePolicy = 'not-present';
        try {
            $centralDirectorySignature = ZipPackage::centralDirectorySignaturePolicyPreflight($bytes);
            if ($centralDirectorySignature['present']) {
                $centralDirectorySignatureOffset = $centralDirectorySignature['offset'];
                $centralDirectorySignatureDataOffset = $centralDirectorySignature['dataOffset'];
                $centralDirectorySignatureEnd = $centralDirectorySignature['endOffset'];
                $centralDirectorySignatureBytes = $centralDirectorySignature['signatureLength'];
                $centralDirectorySignatureRecordBytes = $centralDirectorySignatureEnd - $centralDirectorySignatureOffset;
                $centralDirectorySignaturePreviewHex = $centralDirectorySignature['signaturePreviewHex'];
                $centralDirectorySignaturePreviewByteCount = intdiv(strlen($centralDirectorySignaturePreviewHex), 2);
                $centralDirectorySignatureSha256 = $centralDirectorySignature['signatureSha256'];
                $centralDirectorySignatureLocation = $centralDirectorySignature['location'];
                $centralDirectorySignatureVerification = $centralDirectorySignature['cryptographicVerification'];
                $centralDirectorySignatureByteExposurePolicy = 'central-directory-signature-metadata-only';
            }
        } catch (\Throwable) {
            // Keep package-level source spans available even when signature policy
            // parsing is blocked by another raw central-directory issue.
        }

        $packageSource = [
            'archiveLength' => $archiveLength,
            'archiveSha256' => hash('sha256', $bytes),
            'centralDirectoryOffset' => $centralDirectoryOffset,
            'centralDirectoryBytes' => $centralDirectoryBytes,
            'centralDirectoryEnd' => $centralDirectoryEnd,
            'centralDirectorySha256' => $centralDirectorySha256,
            'centralDirectoryToEocdGapOffset' => $centralDirectoryToEocdGapOffset,
            'centralDirectoryToEocdGapBytes' => $centralDirectoryToEocdGapBytes,
            'centralDirectoryToEocdGapSha256' => $centralDirectoryToEocdGapSha256,
            'endOfCentralDirectoryOffset' => $endOfCentralDirectoryOffset,
            'endOfCentralDirectoryBytes' => $endOfCentralDirectoryBytes,
            'endOfCentralDirectoryEnd' => $endOfCentralDirectoryEnd,
            'endOfCentralDirectorySha256' => $endOfCentralDirectorySha256,
            'endOfCentralDirectoryFixedFields' => $endOfCentralDirectoryFixedFields,
            'endOfCentralDirectoryFixedFieldIssueCount' => count($endOfCentralDirectoryFixedFieldIssues),
            'hasEndOfCentralDirectoryFixedFieldIssues' => $endOfCentralDirectoryFixedFieldIssues !== [],
            'endOfCentralDirectoryFixedFieldIssues' => $endOfCentralDirectoryFixedFieldIssues,
            'packageCommentOffset' => $packageCommentOffset,
            'packageCommentBytes' => $packageCommentBytes,
            'packageCommentEnd' => $packageCommentEnd,
            'packageCommentSha256' => $packageCommentSha256,
            'packageCommentPreviewHex' => $packageCommentPreviewHex,
            'packageCommentPreviewByteCount' => $packageCommentPreviewByteCount,
            'packageCommentByteExposurePolicy' => $packageCommentByteExposurePolicy,
            'canExposePackageCommentBytes' => $canExposePackageCommentBytes,
            'hasPackageComment' => $packageCommentBytes > 0,
            'hasCentralDirectorySignature' => $centralDirectorySignatureOffset !== null,
            'centralDirectorySignatureOffset' => $centralDirectorySignatureOffset,
            'centralDirectorySignatureDataOffset' => $centralDirectorySignatureDataOffset,
            'centralDirectorySignatureEnd' => $centralDirectorySignatureEnd,
            'centralDirectorySignatureBytes' => $centralDirectorySignatureBytes,
            'centralDirectorySignatureRecordBytes' => $centralDirectorySignatureRecordBytes,
            'centralDirectorySignaturePreviewHex' => $centralDirectorySignaturePreviewHex,
            'centralDirectorySignaturePreviewByteCount' => $centralDirectorySignaturePreviewByteCount,
            'centralDirectorySignatureSha256' => $centralDirectorySignatureSha256,
            'centralDirectorySignatureLocation' => $centralDirectorySignatureLocation,
            'centralDirectorySignatureVerification' => $centralDirectorySignatureVerification,
            'centralDirectorySignatureByteExposurePolicy' => $centralDirectorySignatureByteExposurePolicy,
            'centralDirectorySignatureCanExposeBytes' => false,
        ];

        return [
            'packageSource' => $packageSource,
            'archiveLength' => $archiveLength,
            'archiveSha256' => $packageSource['archiveSha256'],
            'centralDirectoryOffset' => $centralDirectoryOffset,
            'centralDirectoryBytes' => $centralDirectoryBytes,
            'centralDirectoryEnd' => $centralDirectoryEnd,
            'centralDirectorySha256' => $centralDirectorySha256,
            'centralDirectoryToEocdGapOffset' => $centralDirectoryToEocdGapOffset,
            'centralDirectoryToEocdGapBytes' => $centralDirectoryToEocdGapBytes,
            'centralDirectoryToEocdGapSha256' => $centralDirectoryToEocdGapSha256,
            'endOfCentralDirectoryOffset' => $endOfCentralDirectoryOffset,
            'endOfCentralDirectoryBytes' => $endOfCentralDirectoryBytes,
            'endOfCentralDirectoryEnd' => $endOfCentralDirectoryEnd,
            'endOfCentralDirectorySha256' => $endOfCentralDirectorySha256,
            'endOfCentralDirectoryFixedFields' => $endOfCentralDirectoryFixedFields,
            'endOfCentralDirectoryFixedFieldIssueCount' => count($endOfCentralDirectoryFixedFieldIssues),
            'hasEndOfCentralDirectoryFixedFieldIssues' => $endOfCentralDirectoryFixedFieldIssues !== [],
            'endOfCentralDirectoryFixedFieldIssues' => $endOfCentralDirectoryFixedFieldIssues,
            'packageCommentOffset' => $packageCommentOffset,
            'packageCommentBytes' => $packageCommentBytes,
            'packageCommentEnd' => $packageCommentEnd,
            'packageCommentSha256' => $packageCommentSha256,
            'packageCommentPreviewHex' => $packageCommentPreviewHex,
            'packageCommentPreviewByteCount' => $packageCommentPreviewByteCount,
            'packageCommentByteExposurePolicy' => $packageCommentByteExposurePolicy,
            'canExposePackageCommentBytes' => $canExposePackageCommentBytes,
            'hasPackageComment' => $packageCommentBytes > 0,
            'hasCentralDirectorySignature' => $centralDirectorySignatureOffset !== null,
            'centralDirectorySignatureOffset' => $centralDirectorySignatureOffset,
            'centralDirectorySignatureDataOffset' => $centralDirectorySignatureDataOffset,
            'centralDirectorySignatureEnd' => $centralDirectorySignatureEnd,
            'centralDirectorySignatureBytes' => $centralDirectorySignatureBytes,
            'centralDirectorySignatureRecordBytes' => $centralDirectorySignatureRecordBytes,
            'centralDirectorySignaturePreviewHex' => $centralDirectorySignaturePreviewHex,
            'centralDirectorySignaturePreviewByteCount' => $centralDirectorySignaturePreviewByteCount,
            'centralDirectorySignatureSha256' => $centralDirectorySignatureSha256,
            'centralDirectorySignatureLocation' => $centralDirectorySignatureLocation,
            'centralDirectorySignatureVerification' => $centralDirectorySignatureVerification,
            'centralDirectorySignatureByteExposurePolicy' => $centralDirectorySignatureByteExposurePolicy,
            'centralDirectorySignatureCanExposeBytes' => false,
        ];
    }

    /**
     * Classify OPC package parts directly from the ZIP central directory,
     * before local-header validation or package construction has succeeded.
     *
     * @return array<string, mixed>
     */
    public static function preflightZipCentralDirectoryManifest(string $bytes): array
    {
        $centralDirectory = ZipPackage::centralDirectorySizePreflight($bytes);
        $packageSource = self::zipPackageSourcePreflightFromBytes($bytes);
        $localHeaderOrder = ZipPackage::centralDirectoryLocalHeaderOrderPreflight($bytes);
        $zipExtraFields = null;
        $zipExtraFieldPreflightError = null;
        try {
            $zipExtraFields = ZipPackage::extraFieldPolicyPreflight($bytes);
        } catch (\Throwable $exception) {
            $zipExtraFieldPreflightError = $exception->getMessage();
        }
        $localHeaderOrderByCentralDirectoryIndex = [];
        foreach ($localHeaderOrder['entries'] as $orderEntry) {
            $localHeaderOrderByCentralDirectoryIndex[$orderEntry['centralDirectoryIndex']] = $orderEntry;
        }
        $zipExtraFieldsByCentralDirectoryIndex = [];
        if (is_array($zipExtraFields)) {
            foreach ($zipExtraFields['entries'] as $extraFieldEntry) {
                $zipExtraFieldsByCentralDirectoryIndex[$extraFieldEntry['centralDirectoryIndex']] = $extraFieldEntry;
            }
        }
        $localHeaderNames = null;
        $localHeaderNamePreflightError = null;
        $localHeaderNameByCentralDirectoryIndex = [];
        try {
            $localHeaderNames = ZipPackage::localHeaderNamePreflight($bytes);
            foreach ($localHeaderNames['entries'] as $nameEntry) {
                $localHeaderNameByCentralDirectoryIndex[$nameEntry['centralDirectoryIndex']] = $nameEntry;
            }
        } catch (\Throwable $exception) {
            $localHeaderNamePreflightError = $exception->getMessage();
        }
        $localHeaderMetadata = null;
        $localHeaderMetadataPreflightError = null;
        $localHeaderMetadataByCentralDirectoryIndex = [];
        try {
            $localHeaderMetadata = ZipPackage::localHeaderMetadataPreflight($bytes);
            foreach ($localHeaderMetadata['entries'] as $metadataEntry) {
                $localHeaderMetadataByCentralDirectoryIndex[$metadataEntry['centralDirectoryIndex']] = $metadataEntry;
            }
        } catch (\Throwable $exception) {
            $localHeaderMetadataPreflightError = $exception->getMessage();
        }
        $localHeaderSpans = null;
        $localHeaderSpanPreflightError = null;
        $localHeaderSpanByCentralDirectoryIndex = [];
        try {
            $localHeaderSpans = ZipPackage::localHeaderSpanPreflight($bytes);
            foreach ($localHeaderSpans['entries'] as $spanEntry) {
                $localHeaderSpanByCentralDirectoryIndex[$spanEntry['centralDirectoryIndex']] = $spanEntry;
            }
        } catch (\Throwable $exception) {
            $localHeaderSpanPreflightError = $exception->getMessage();
        }
        $centralDirectoryFixedHeaderByCentralDirectoryIndex = [];
        try {
            $centralDirectoryFixedHeaders = ZipPackage::centralDirectoryFixedHeaderPreflight($bytes);
            foreach ($centralDirectoryFixedHeaders['entries'] as $fixedHeaderEntry) {
                $centralDirectoryFixedHeaderByCentralDirectoryIndex[$fixedHeaderEntry['centralDirectoryIndex']] = $fixedHeaderEntry;
            }
        } catch (\Throwable) {
            $centralDirectoryFixedHeaderByCentralDirectoryIndex = [];
        }
        $centralDirectoryVariableFieldByCentralDirectoryIndex = [];
        try {
            $centralDirectoryVariableFields = ZipPackage::centralDirectoryVariableFieldsPreflight($bytes);
            foreach ($centralDirectoryVariableFields['entries'] as $variableFieldEntry) {
                $centralDirectoryVariableFieldByCentralDirectoryIndex[$variableFieldEntry['centralDirectoryIndex']] = $variableFieldEntry;
            }
        } catch (\Throwable) {
            $centralDirectoryVariableFieldByCentralDirectoryIndex = [];
        }
        $localHeaderVariableFieldByCentralDirectoryIndex = [];
        try {
            $localHeaderVariableFields = ZipPackage::localHeaderVariableFieldsPreflight($bytes);
            foreach ($localHeaderVariableFields['entries'] as $variableFieldEntry) {
                $localHeaderVariableFieldByCentralDirectoryIndex[$variableFieldEntry['centralDirectoryIndex']] = $variableFieldEntry;
            }
        } catch (\Throwable) {
            $localHeaderVariableFieldByCentralDirectoryIndex = [];
        }
        $creatorHostSystemByCentralDirectoryIndex = [];
        try {
            $creatorHostSystems = ZipPackage::creatorHostSystemPolicyPreflight($bytes);
            foreach ($creatorHostSystems['entries'] as $creatorHostEntry) {
                $creatorHostSystemByCentralDirectoryIndex[$creatorHostEntry['centralDirectoryIndex']] = $creatorHostEntry;
            }
        } catch (\Throwable) {
            $creatorHostSystemByCentralDirectoryIndex = [];
        }
        $hashByteSlice = static function (?int $offset, ?int $bytesToHash) use ($bytes): ?string {
            if (
                !is_int($offset)
                || !is_int($bytesToHash)
                || $offset < 0
                || $bytesToHash < 0
                || $offset + $bytesToHash > strlen($bytes)
            ) {
                return null;
            }

            return hash('sha256', substr($bytes, $offset, $bytesToHash));
        };
        $entries = [];
        $contentTypesItems = [];
        $contentTypesEntryIndexes = [];
        $packagePartNamesByEquivalenceKey = [];
        $packagePartEntryIndexesByEquivalenceKey = [];
        $localHeaderMetadataManifestIssues = [];
        $localHeaderMetadataManifestMismatchedEntries = [];

        foreach ($centralDirectory['entries'] as $entryIndex => $centralEntry) {
            $isDirectory = $centralEntry['isDirectory'];
            $partName = null;
            $equivalenceKey = null;
            $parseError = null;
            $issues = $centralEntry['issues'];
            $contentTypesItem = false;
            $byteCountsAreExact = !$centralEntry['hasZip64SizeSentinel'];
            $orderEntry = $localHeaderOrderByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $zipExtraFieldEntry = $zipExtraFieldsByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $zipExtraFieldEntryFields = self::zipExtraFieldManifestEntryFields($zipExtraFieldEntry);
            $issues = array_values(array_unique(array_merge(
                $issues,
                $zipExtraFieldEntryFields['zipExtraFieldIssues']
            )));
            $localHeaderNameEntry = $localHeaderNameByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $localHeaderMetadataEntry = $localHeaderMetadataByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $localHeaderSpanEntry = $localHeaderSpanByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $centralDirectoryFixedHeaderEntry = $centralDirectoryFixedHeaderByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $centralDirectoryVariableFieldEntry = $centralDirectoryVariableFieldByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $localHeaderVariableFieldEntry = $localHeaderVariableFieldByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $creatorHostEntry = $creatorHostSystemByCentralDirectoryIndex[$centralEntry['centralDirectoryIndex']] ?? null;
            $localHeaderMetadataIssues = $localHeaderMetadataEntry['issues'] ?? [];
            if (($centralEntry['hasZip64SizeSentinel'] ?? false) === true) {
                $localHeaderMetadataIssues = array_values(array_diff(
                    $localHeaderMetadataIssues,
                    [
                        'local-header-compressed-size-mismatch',
                        'local-header-uncompressed-size-mismatch',
                    ],
                ));
            }
            if ($localHeaderNameEntry !== null && $localHeaderNameEntry['issues'] !== []) {
                $issues = array_values(array_unique(array_merge($issues, $localHeaderNameEntry['issues'])));
            }
            if ($localHeaderMetadataEntry !== null && $localHeaderMetadataIssues !== []) {
                $issues = array_values(array_unique(array_merge($issues, $localHeaderMetadataIssues)));
                $mismatchedMetadataEntry = $localHeaderMetadataEntry;
                $mismatchedMetadataEntry['issues'] = $localHeaderMetadataIssues;
                $mismatchedMetadataEntry['hasMetadataMismatch'] = true;
                $localHeaderMetadataManifestMismatchedEntries[] = $mismatchedMetadataEntry;
                foreach ($localHeaderMetadataIssues as $metadataIssue) {
                    self::appendUniqueString($localHeaderMetadataManifestIssues, $metadataIssue);
                }
            }

            if (!$isDirectory) {
                try {
                    $partName = OpcPackagePath::canonicalPartName($centralEntry['name']);
                    $equivalenceKey = self::partNameEquivalenceKey($partName);
                    $packagePartNamesByEquivalenceKey[$equivalenceKey] = $partName;
                    $packagePartEntryIndexesByEquivalenceKey[$equivalenceKey][] = $entryIndex;
                    if (self::isContentTypesItemName($partName)) {
                        $contentTypesItem = true;
                        $contentTypesItems[] = $partName;
                        $contentTypesEntryIndexes[] = $entryIndex;
                    }
                } catch (\InvalidArgumentException $exception) {
                    $parseError = $exception->getMessage();
                    $issues = array_values(array_unique(array_merge(
                        $issues,
                        self::packagePartNameIssuesForParseError($parseError),
                    )));
                }
            }
            $centralDirectoryRecordBytes = max(0, $centralEntry['recordEnd'] - $centralEntry['centralDirectoryOffset']);
            $localHeaderOffset = $localHeaderSpanEntry['localHeaderOffset'] ?? $centralEntry['localHeaderOffset'];
            $localHeaderLength = $localHeaderSpanEntry['localHeaderLength'] ?? null;
            $centralDirectoryFixedHeaderBytes = $centralDirectoryFixedHeaderEntry['fixedHeaderLength'] ?? 46;
            $centralDirectoryVariableFieldOffset = $centralDirectoryVariableFieldEntry['variableFieldsOffset']
                ?? ($centralEntry['centralDirectoryOffset'] + $centralDirectoryFixedHeaderBytes);
            $centralDirectoryVariableFieldBytes = $centralDirectoryVariableFieldEntry['variableFieldsLength']
                ?? max(0, $centralDirectoryRecordBytes - $centralDirectoryFixedHeaderBytes);
            $centralDirectoryRawNameOffset = $centralDirectoryVariableFieldEntry['rawNameOffset']
                ?? $centralDirectoryVariableFieldOffset;
            $centralDirectoryRawNameBytes = $centralDirectoryVariableFieldEntry['rawNameLength']
                ?? strlen($centralEntry['rawName'] ?? '');
            $centralDirectoryExtraFieldOffset = $centralDirectoryVariableFieldEntry['centralExtraFieldOffset']
                ?? (is_int($centralDirectoryRawNameOffset) && is_int($centralDirectoryRawNameBytes)
                    ? $centralDirectoryRawNameOffset + $centralDirectoryRawNameBytes
                    : null);
            $centralDirectoryExtraFieldBytes = $centralDirectoryVariableFieldEntry['centralExtraFieldLength'] ?? null;
            $centralDirectoryRawCommentOffset = $centralDirectoryVariableFieldEntry['rawCommentOffset'] ?? null;
            $centralDirectoryRawCommentBytes = $centralDirectoryVariableFieldEntry['rawCommentLength'] ?? null;
            $centralDirectoryReviewFieldBytes = is_int($centralDirectoryExtraFieldBytes)
                && is_int($centralDirectoryRawCommentBytes)
                    ? $centralDirectoryExtraFieldBytes + $centralDirectoryRawCommentBytes
                    : null;
            $localHeaderFixedHeaderBytes = $localHeaderVariableFieldEntry['fixedHeaderLength'] ?? (
                is_int($localHeaderLength) ? 30 : null
            );
            $localHeaderVariableFieldOffset = $localHeaderVariableFieldEntry['variableFieldsOffset'] ?? (
                is_int($localHeaderOffset) ? $localHeaderOffset + 30 : null
            );
            $localHeaderVariableFieldBytes = $localHeaderVariableFieldEntry['variableFieldsLength'] ?? (
                is_int($localHeaderLength) ? max(0, $localHeaderLength - 30) : null
            );
            $localHeaderRawNameOffset = $localHeaderVariableFieldEntry['rawNameOffset'] ?? $localHeaderVariableFieldOffset;
            $localHeaderRawNameBytes = $localHeaderVariableFieldEntry['rawNameLength'] ?? null;
            $localHeaderExtraFieldOffset = $localHeaderVariableFieldEntry['localExtraFieldOffset'] ?? (
                is_int($localHeaderRawNameOffset) && is_int($localHeaderRawNameBytes)
                    ? $localHeaderRawNameOffset + $localHeaderRawNameBytes
                    : null
            );
            $localHeaderExtraFieldBytes = $localHeaderVariableFieldEntry['localExtraFieldLength'] ?? null;
            $localHeaderReviewFieldBytes = $localHeaderExtraFieldBytes;
            $localHeaderSha256 = null;
            if (
                is_int($localHeaderOffset)
                && is_int($localHeaderLength)
                && $localHeaderOffset >= 0
                && $localHeaderLength >= 0
                && $localHeaderOffset + $localHeaderLength <= strlen($bytes)
            ) {
                $localHeaderSha256 = hash('sha256', substr($bytes, $localHeaderOffset, $localHeaderLength));
            }
            $compressedDataOffset = $byteCountsAreExact ? ($localHeaderSpanEntry['dataStart'] ?? null) : null;
            $compressedDataEnd = $byteCountsAreExact ? ($localHeaderSpanEntry['compressedDataEnd'] ?? null) : null;
            $compressedDataSha256 = null;
            $localRecordOffset = is_int($localHeaderOffset) ? $localHeaderOffset : null;
            $localRecordEnd = $byteCountsAreExact ? ($localHeaderSpanEntry['recordEnd'] ?? null) : null;
            $localRecordBytes = null;
            $localRecordSha256 = null;
            if (
                is_int($localRecordOffset)
                && is_int($localRecordEnd)
                && $localRecordOffset >= 0
                && $localRecordEnd >= $localRecordOffset
                && $localRecordEnd <= strlen($bytes)
            ) {
                $localRecordBytes = $localRecordEnd - $localRecordOffset;
                $localRecordSha256 = hash('sha256', substr($bytes, $localRecordOffset, $localRecordBytes));
            }
            if (
                is_int($compressedDataOffset)
                && is_int($compressedDataEnd)
                && $compressedDataOffset >= 0
                && $compressedDataEnd >= $compressedDataOffset
                && $compressedDataEnd <= strlen($bytes)
            ) {
                $compressedDataSha256 = hash(
                    'sha256',
                    substr($bytes, $compressedDataOffset, $compressedDataEnd - $compressedDataOffset)
                );
            }
            $dataDescriptorOffset = $byteCountsAreExact ? ($localHeaderSpanEntry['descriptorOffset'] ?? null) : null;
            $dataDescriptorBytes = $byteCountsAreExact ? ($localHeaderSpanEntry['descriptorLength'] ?? 0) : null;
            $dataDescriptorEnd = is_int($dataDescriptorOffset) && is_int($dataDescriptorBytes)
                ? $dataDescriptorOffset + $dataDescriptorBytes
                : null;
            $dataDescriptorSha256 = null;
            if (
                is_int($dataDescriptorOffset)
                && is_int($dataDescriptorBytes)
                && $dataDescriptorBytes > 0
                && $dataDescriptorOffset >= 0
                && $dataDescriptorOffset + $dataDescriptorBytes <= strlen($bytes)
            ) {
                $dataDescriptorSha256 = hash('sha256', substr($bytes, $dataDescriptorOffset, $dataDescriptorBytes));
            }
            $sourceRecordBytes = $localRecordBytes === null ? null : $localRecordBytes + $centralDirectoryRecordBytes;
            $pathSegments = self::zipEntryManifestPathSegments($centralEntry['name']);
            $pathSegmentPositionReviews = self::zipEntryManifestPathSegmentPositionReviews($pathSegments);
            $pathSegmentCount = count($pathSegments);

            $entries[] = [
                'entryIndex' => $entryIndex,
                'entryName' => $centralEntry['name'],
                'directoryRoot' => self::zipEntryManifestDirectoryRoot($centralEntry['name']),
                'pathSegments' => $pathSegments,
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews,
                'pathSegmentCount' => $pathSegmentCount,
                'directoryDepth' => max(0, $pathSegmentCount - 1),
                'versionMadeBy' => $creatorHostEntry['versionMadeBy'] ?? $centralDirectoryFixedHeaderEntry['versionMadeBy'] ?? null,
                'madeByHostSystem' => $creatorHostEntry['madeByHostSystem'] ?? $centralDirectoryFixedHeaderEntry['creatorHostSystem'] ?? null,
                'madeByHostSystemName' => $creatorHostEntry['madeByHostSystemName'] ?? null,
                'madeByVersion' => $creatorHostEntry['madeByVersion'] ?? $centralDirectoryFixedHeaderEntry['creatorVersion'] ?? null,
                'versionNeededToExtract' => $creatorHostEntry['versionNeededToExtract'] ?? $centralDirectoryFixedHeaderEntry['versionNeededToExtract'] ?? null,
                'creatorVersionMeetsNeeded' => $creatorHostEntry['creatorVersionMeetsNeeded'] ?? null,
                'creatorVersionComparison' => $creatorHostEntry['creatorVersionComparison'] ?? null,
                'creatorVersionDelta' => $creatorHostEntry['creatorVersionDelta'] ?? null,
                'creatorHostSystemIsKnown' => $creatorHostEntry['isKnown'] ?? null,
                'creatorHostSystemIssues' => $creatorHostEntry['issues'] ?? [],
                'partName' => $partName,
                'equivalenceKey' => $equivalenceKey,
                'equivalentPartNames' => [],
                'localHeaderOrder' => $orderEntry['localHeaderOrder'] ?? $entryIndex,
                'localHeaderNameAtCentralDirectoryIndex' => $orderEntry['localHeaderNameAtCentralDirectoryIndex'] ?? $centralEntry['name'],
                'centralDirectoryNameAtLocalHeaderOrder' => $orderEntry['centralDirectoryNameAtLocalHeaderOrder'] ?? $centralEntry['name'],
                'matchesCentralDirectoryOrder' => $orderEntry['matchesCentralDirectoryOrder'] ?? true,
                'centralName' => $localHeaderNameEntry['centralName'] ?? $centralEntry['name'],
                'localHeaderName' => $localHeaderNameEntry['localName'] ?? null,
                'localHeaderRawName' => $localHeaderNameEntry['localRawName'] ?? null,
                'localHeaderNameEncoding' => $localHeaderNameEntry['localNameEncoding'] ?? null,
                'localHeaderNameMatchesCentral' => $localHeaderNameEntry['rawNameMatchesCentral'] ?? null,
                'localHeaderDecodedNameMatchesCentral' => $localHeaderNameEntry['decodedNameMatchesCentral'] ?? null,
                'localHeaderGeneralPurposeFlagsMatchCentral' => $localHeaderNameEntry['generalPurposeFlagsMatchCentral'] ?? null,
                'localHeaderNameIssues' => $localHeaderNameEntry['issues'] ?? [],
                'localHeaderMetadataMatchesCentral' => $localHeaderMetadataEntry === null
                    ? null
                    : $localHeaderMetadataIssues === [],
                'localHeaderHasMetadataMismatch' => $localHeaderMetadataEntry === null
                    ? null
                    : $localHeaderMetadataIssues !== [],
                'localHeaderMetadataIssues' => $localHeaderMetadataIssues,
                'centralVersionNeededToExtract' => $localHeaderMetadataEntry['centralVersionNeededToExtract'] ?? null,
                'localVersionNeededToExtract' => $localHeaderMetadataEntry['localVersionNeededToExtract'] ?? null,
                'centralGeneralPurposeFlags' => $localHeaderMetadataEntry['centralGeneralPurposeFlags'] ?? null,
                'localGeneralPurposeFlags' => $localHeaderMetadataEntry['localGeneralPurposeFlags'] ?? null,
                'generalPurposeFlags' => $localHeaderMetadataEntry['centralGeneralPurposeFlags'] ?? null,
                ...self::zipGeneralPurposeFlagManifestEntry(
                    $localHeaderMetadataEntry['centralGeneralPurposeFlags'] ?? null,
                    $localHeaderMetadataEntry['localGeneralPurposeFlags'] ?? null,
                ),
                'centralCompressionMethod' => $localHeaderMetadataEntry['centralCompressionMethod'] ?? $centralEntry['compressionMethod'],
                'localCompressionMethod' => $localHeaderMetadataEntry['localCompressionMethod'] ?? null,
                'centralModifiedDosTime' => $localHeaderMetadataEntry['centralModifiedDosTime'] ?? null,
                'localModifiedDosTime' => $localHeaderMetadataEntry['localModifiedDosTime'] ?? null,
                'centralModifiedDosDate' => $localHeaderMetadataEntry['centralModifiedDosDate'] ?? null,
                'localModifiedDosDate' => $localHeaderMetadataEntry['localModifiedDosDate'] ?? null,
                'centralCrc32' => $localHeaderMetadataEntry['centralCrc32'] ?? null,
                'localCrc32' => $localHeaderMetadataEntry['localCrc32'] ?? null,
                'centralCrc32Hex' => isset($localHeaderMetadataEntry['centralCrc32'])
                    ? sprintf('%08x', $localHeaderMetadataEntry['centralCrc32'])
                    : null,
                'localCrc32Hex' => isset($localHeaderMetadataEntry['localCrc32'])
                    ? sprintf('%08x', $localHeaderMetadataEntry['localCrc32'])
                    : null,
                'crc32Hex' => is_int($centralEntry['crc32'] ?? null)
                    ? sprintf('%08x', $centralEntry['crc32'])
                    : null,
                'centralCompressedSize' => $localHeaderMetadataEntry['centralCompressedSize'] ?? $centralEntry['compressedSize'],
                'localCompressedSize' => $localHeaderMetadataEntry['localCompressedSize'] ?? null,
                'centralUncompressedSize' => $localHeaderMetadataEntry['centralUncompressedSize'] ?? $centralEntry['uncompressedSize'],
                'localUncompressedSize' => $localHeaderMetadataEntry['localUncompressedSize'] ?? null,
                'usesDataDescriptor' => $localHeaderMetadataEntry['usesDataDescriptor'] ?? null,
                'hasZeroLocalHeaderPlaceholders' => $localHeaderMetadataEntry['hasZeroLocalHeaderPlaceholders'] ?? null,
                'isDirectory' => $isDirectory,
                'isPackagePart' => !$isDirectory,
                'centralDirectoryIndex' => $centralEntry['centralDirectoryIndex'],
                'centralDirectoryOffset' => $centralEntry['centralDirectoryOffset'],
                'centralDirectoryRecordOffset' => $centralEntry['centralDirectoryOffset'],
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryRecordEnd' => $centralEntry['recordEnd'],
                'centralDirectoryRecordSha256' => hash(
                    'sha256',
                    substr($bytes, $centralEntry['centralDirectoryOffset'], $centralDirectoryRecordBytes)
                ),
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldOffset' => $centralDirectoryVariableFieldOffset,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryVariableFieldSha256' => $hashByteSlice(
                    $centralDirectoryVariableFieldOffset,
                    $centralDirectoryVariableFieldBytes
                ),
                'centralDirectoryRawNameOffset' => $centralDirectoryRawNameOffset,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryRawNameSha256' => $hashByteSlice(
                    $centralDirectoryRawNameOffset,
                    $centralDirectoryRawNameBytes
                ),
                'centralDirectoryExtraFieldOffset' => $centralDirectoryExtraFieldOffset,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryExtraFieldSha256' => $hashByteSlice(
                    $centralDirectoryExtraFieldOffset,
                    $centralDirectoryExtraFieldBytes
                ),
                ...$zipExtraFieldEntryFields,
                'centralDirectoryRawCommentOffset' => $centralDirectoryRawCommentOffset,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryRawCommentSha256' => $hashByteSlice(
                    $centralDirectoryRawCommentOffset,
                    $centralDirectoryRawCommentBytes
                ),
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'localHeaderOffset' => $localHeaderOffset,
                'localHeaderLength' => $localHeaderLength,
                'localHeaderSha256' => $localHeaderSha256,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldOffset' => $localHeaderVariableFieldOffset,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderVariableFieldSha256' => $hashByteSlice(
                    $localHeaderVariableFieldOffset,
                    $localHeaderVariableFieldBytes
                ),
                'localHeaderRawNameOffset' => $localHeaderRawNameOffset,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderRawNameSha256' => $hashByteSlice(
                    $localHeaderRawNameOffset,
                    $localHeaderRawNameBytes
                ),
                'localHeaderExtraFieldOffset' => $localHeaderExtraFieldOffset,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderExtraFieldSha256' => $hashByteSlice(
                    $localHeaderExtraFieldOffset,
                    $localHeaderExtraFieldBytes
                ),
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'localRecordOffset' => $localRecordOffset,
                'localRecordBytes' => $localRecordBytes,
                'localRecordEnd' => $localRecordEnd,
                'localRecordSha256' => $localRecordSha256,
                'compressedDataOffset' => $compressedDataOffset,
                'compressedDataEnd' => $compressedDataEnd,
                'compressedDataSha256' => $compressedDataSha256,
                'dataDescriptorOffset' => $dataDescriptorOffset,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'dataDescriptorEnd' => $dataDescriptorEnd,
                'dataDescriptorSha256' => $dataDescriptorSha256,
                'sourceRecordBytes' => $sourceRecordBytes,
                'compressionMethod' => $centralEntry['compressionMethod'],
                'compressionMethodName' => $centralEntry['compressionMethodName'],
                'compressedSize' => $centralEntry['compressedSize'],
                'uncompressedSize' => $centralEntry['uncompressedSize'],
                'byteCountsAreExact' => $byteCountsAreExact,
                'exactCompressedSize' => $byteCountsAreExact ? $centralEntry['compressedSize'] : null,
                'exactUncompressedSize' => $byteCountsAreExact ? $centralEntry['uncompressedSize'] : null,
                'hasZip64SizeSentinel' => $centralEntry['hasZip64SizeSentinel'],
                'role' => $isDirectory ? 'directory' : 'package-part',
                'handoffKind' => $isDirectory ? 'directory' : 'binary',
                'contentTypesItem' => $contentTypesItem,
                'relationshipPart' => false,
                'relationshipPartCandidate' => false,
                'relationshipSource' => null,
                'relationshipSourceExists' => null,
                'valid' => $issues === [],
                'issues' => $issues,
                'parseError' => $parseError,
            ];
        }

        $equivalentPackagePartNameGroups = [];
        $equivalentPackagePartNameEntryCount = 0;
        foreach ($packagePartEntryIndexesByEquivalenceKey as $equivalenceKey => $entryIndexes) {
            if (count($entryIndexes) < 2) {
                continue;
            }

            $partNames = [];
            $entryNames = [];
            foreach ($entryIndexes as $entryIndex) {
                $partName = $entries[$entryIndex]['partName'];
                if ($partName !== null) {
                    $partNames[] = $partName;
                    $entryNames[] = $entries[$entryIndex]['entryName'];
                }
            }

            sort($partNames, SORT_STRING);
            sort($entryNames, SORT_STRING);
            $equivalentPackagePartNameEntryCount += count($entryIndexes);
            $equivalentPackagePartNameGroups[] = [
                'equivalenceKey' => $equivalenceKey,
                'partNames' => $partNames,
                'entryNames' => $entryNames,
            ];

            foreach ($entryIndexes as $entryIndex) {
                $entries[$entryIndex]['equivalentPartNames'] = $partNames;
                $entries[$entryIndex]['issues'][] = 'equivalent-part-name-case-collision';
            }
        }

        usort(
            $equivalentPackagePartNameGroups,
            static fn (array $left, array $right): int => $left['equivalenceKey'] <=> $right['equivalenceKey'],
        );

        if (count($contentTypesEntryIndexes) > 1) {
            foreach ($contentTypesEntryIndexes as $entryIndex) {
                $entries[$entryIndex]['issues'][] = 'duplicate-content-types-item';
            }
        }

        foreach ($entries as &$entry) {
            if ($entry['partName'] === null) {
                $entry['role'] = $entry['isDirectory'] ? 'directory' : 'invalid-opc-part';
                $entry['handoffKind'] = $entry['isDirectory'] ? 'directory' : 'blocked';
                $entry['valid'] = $entry['issues'] === [];
                continue;
            }

            $partName = $entry['partName'];
            $relationshipPartCandidate = self::isRelationshipPartNameCandidate($partName);
            $relationshipPart = false;
            $relationshipSource = null;
            $relationshipSourceExists = null;

            if ($relationshipPartCandidate) {
                if (OpcRelationships::isRelationshipPartName($partName)) {
                    try {
                        $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                        $relationshipPart = true;
                        $relationshipSourceExists = $relationshipSource === '/'
                            || isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($relationshipSource)]);

                        if ($relationshipSource !== '/' && OpcRelationships::isRelationshipPartName($relationshipSource)) {
                            $entry['issues'][] = 'relationship-part-source';
                        }

                        if ($relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                            $entry['issues'][] = 'content-types-item-source';
                        }

                        if (!$relationshipSourceExists) {
                            $entry['issues'][] = 'orphan-relationship-part';
                        }
                    } catch (\InvalidArgumentException $exception) {
                        $entry['parseError'] ??= $exception->getMessage();
                        $entry['issues'][] = 'invalid-relationship-part-name';
                    }
                } else {
                    $entry['issues'][] = 'invalid-relationship-part-name';
                }
            } elseif (self::isReservedRelationshipDirectoryPartName($partName)) {
                $entry['issues'][] = 'reserved-relationship-directory-part';
            }

            $entry['relationshipPart'] = $relationshipPart;
            $entry['relationshipPartCandidate'] = $relationshipPartCandidate;
            $entry['relationshipSource'] = $relationshipSource;
            $entry['relationshipSourceExists'] = $relationshipSourceExists;
            $entry['role'] = self::zipEntryManifestRole(
                $partName,
                $entry['isDirectory'],
                $entry['contentTypesItem'],
                $relationshipPart,
                $relationshipPartCandidate,
                $relationshipSource
            );
            $entry['handoffKind'] = self::zipEntryManifestHandoffKind($entry['role'], $partName);
            $entry['issues'] = array_values(array_unique($entry['issues']));
            $entry['valid'] = $entry['issues'] === [];
        }
        unset($entry);

        $issues = $centralDirectory['issues'];
        $issueCounts = [];
        $entryNamesByIssue = [];
        $partNamesByIssue = [];
        foreach ($issues as $issue) {
            $issueCounts[$issue] = 1;
        }
        if ($zipExtraFieldPreflightError !== null) {
            $issueCounts['extra-field-policy-preflight-error'] = 1;
            self::appendUniqueString($issues, 'extra-field-policy-preflight-error');
        }
        if ($localHeaderNamePreflightError !== null) {
            $issueCounts['local-header-name-preflight-error'] = 1;
            self::appendUniqueString($issues, 'local-header-name-preflight-error');
        }
        if ($localHeaderMetadataPreflightError !== null) {
            $issueCounts['local-header-metadata-preflight-error'] = 1;
            self::appendUniqueString($issues, 'local-header-metadata-preflight-error');
        }
        $roleCounts = [];
        $byteCountsByRole = [];
        $byteCountsByHandoffKind = [];
        $compressionMethodCounts = [];
        $entryNamesByCompressionMethod = [];
        $compressionMethodNamesByRole = [];
        $compressionMethodNamesByHandoffKind = [];
        $generalPurposeFlagCounts = [];
        $entryNamesByGeneralPurposeFlag = [];
        $generalPurposeFlagNamesByRole = [];
        $generalPurposeFlagNamesByHandoffKind = [];
        $generalPurposeFlagIssueCounts = [];
        $entryNamesByGeneralPurposeFlagIssue = [];
        $creatorHostSystemCounts = [];
        $entryNamesByCreatorHostSystem = [];
        $creatorHostSystemNamesByRole = [];
        $creatorHostSystemNamesByHandoffKind = [];
        $creatorVersionComparisonCounts = [
            'below-needed' => 0,
            'equals-needed' => 0,
            'above-needed' => 0,
        ];
        $entryNamesByCreatorVersionComparison = [];
        $creatorVersionComparisonsByRole = [];
        $creatorVersionComparisonsByHandoffKind = [];
        $creatorHostSystemIssueCounts = [];
        $entryNamesByCreatorHostSystemIssue = [];
        $crc32SummariesByHex = [];
        $pathSegmentPositionRoleEntryCounts = [];
        $entryNamesByPathSegmentPositionRole = [];
        $pathSegmentPositionHandoffKindEntryCounts = [];
        $entryNamesByPathSegmentPositionHandoffKind = [];
        $directoryRootCounts = [];
        $entryNamesByDirectoryRoot = [];
        $directoryRootSummariesByRoot = [];
        $packagePartExtensionCounts = [];
        $entryNamesByPackagePartExtension = [];
        $packagePartExtensionSummariesByExtension = [];
        $relationshipParts = [];
        $fileEntryCount = 0;
        $directoryEntryCount = 0;
        $packagePartCount = 0;
        $extensionlessPackagePartCount = 0;
        $fileCompressedBytes = 0;
        $fileUncompressedBytes = 0;
        $directoryCompressedBytes = 0;
        $directoryUncompressedBytes = 0;
        $relationshipPartCount = 0;
        $rootRelationshipPartCount = 0;
        $partRelationshipPartCount = 0;
        $invalidRelationshipPartCount = 0;
        $reservedRelationshipDirectoryPartCount = 0;
        $orphanRelationshipPartCount = 0;
        $relationshipPartSourceCount = 0;
        $contentTypesItemRelationshipSourceCount = 0;
        $documentPropertyPartCount = 0;
        $digitalSignaturePartCount = 0;
        $embeddedPackageCandidateCount = 0;
        $mediaPartCandidateCount = 0;
        $xmlPayloadPartCount = 0;
        $binaryPayloadPartCount = 0;
        $unknownByteCountEntries = [];
        $largestPayloadEntry = null;
        $payloadEntries = [];

        if ($contentTypesItems === []) {
            $issueCounts['missing-content-types-item'] = ($issueCounts['missing-content-types-item'] ?? 0) + 1;
            self::appendUniqueString($issues, 'missing-content-types-item');
            self::recordZipManifestIssueProvenance(
                $entryNamesByIssue,
                $partNamesByIssue,
                'missing-content-types-item',
                null,
                '/[Content_Types].xml',
            );
        }

        foreach ($entries as $entry) {
            $exactCompressedSize = $entry['exactCompressedSize'];
            $exactUncompressedSize = $entry['exactUncompressedSize'];
            if ($entry['isDirectory']) {
                $directoryEntryCount++;
                if ($entry['byteCountsAreExact']) {
                    $directoryCompressedBytes += $exactCompressedSize;
                    $directoryUncompressedBytes += $exactUncompressedSize;
                }
            } else {
                $fileEntryCount++;
                if ($entry['byteCountsAreExact']) {
                    $fileCompressedBytes += $exactCompressedSize;
                    $fileUncompressedBytes += $exactUncompressedSize;
                }
            }
            if ($entry['isPackagePart']) {
                $packagePartCount++;
            }

            $directoryRoot = $entry['directoryRoot'];
            $directoryRootCounts[$directoryRoot] = ($directoryRootCounts[$directoryRoot] ?? 0) + 1;
            $entryNamesByDirectoryRoot[$directoryRoot] ??= [];
            self::appendUniqueString($entryNamesByDirectoryRoot[$directoryRoot], $entry['entryName']);
            $directorySummaryEntry = $entry;
            $directorySummaryEntry['compressedSize'] = $entry['byteCountsAreExact'] ? (int) $exactCompressedSize : 0;
            $directorySummaryEntry['uncompressedSize'] = $entry['byteCountsAreExact'] ? (int) $exactUncompressedSize : 0;
            self::recordZipEntryManifestDirectoryRootSummary(
                $directoryRootSummariesByRoot,
                $directoryRoot,
                $directorySummaryEntry,
            );

            if ($entry['isPackagePart'] && is_string($entry['partName'])) {
                $extension = self::partNameExtension($entry['partName']);
                $extensionKey = $extension === '' ? '(none)' : $extension;
                if ($extension === '') {
                    $extensionlessPackagePartCount++;
                }
                $packagePartExtensionCounts[$extensionKey] = ($packagePartExtensionCounts[$extensionKey] ?? 0) + 1;
                $entryNamesByPackagePartExtension[$extensionKey] ??= [];
                self::appendUniqueString($entryNamesByPackagePartExtension[$extensionKey], $entry['entryName']);

                $extensionSummaryEntry = $entry;
                $extensionSummaryEntry['compressedSize'] = $entry['byteCountsAreExact'] ? (int) $exactCompressedSize : 0;
                $extensionSummaryEntry['uncompressedSize'] = $entry['byteCountsAreExact'] ? (int) $exactUncompressedSize : 0;
                self::recordZipEntryManifestExtensionSummary(
                    $packagePartExtensionSummariesByExtension,
                    $extensionKey,
                    $extension === '' ? null : $extension,
                    $extensionSummaryEntry,
                );
            }

            $roleCounts[$entry['role']] = ($roleCounts[$entry['role']] ?? 0) + 1;
            self::recordZipEntryManifestPathSegmentPositionProvenance(
                $pathSegmentPositionRoleEntryCounts,
                $entryNamesByPathSegmentPositionRole,
                $pathSegmentPositionHandoffKindEntryCounts,
                $entryNamesByPathSegmentPositionHandoffKind,
                $entry,
            );
            self::incrementZipEntryManifestByteBucket(
                $byteCountsByRole,
                $entry['role'],
                $exactCompressedSize ?? 0,
                $exactUncompressedSize ?? 0,
            );
            self::incrementZipEntryManifestByteBucket(
                $byteCountsByHandoffKind,
                $entry['handoffKind'],
                $exactCompressedSize ?? 0,
                $exactUncompressedSize ?? 0,
            );
            self::recordZipEntryManifestCompressionMethodProvenance(
                $compressionMethodCounts,
                $entryNamesByCompressionMethod,
                $compressionMethodNamesByRole,
                $compressionMethodNamesByHandoffKind,
                $entry['compressionMethodName'],
                $entry['entryName'],
                $entry['role'],
                $entry['handoffKind'],
            );
            self::recordZipEntryManifestGeneralPurposeFlagProvenance(
                $generalPurposeFlagCounts,
                $entryNamesByGeneralPurposeFlag,
                $generalPurposeFlagNamesByRole,
                $generalPurposeFlagNamesByHandoffKind,
                $generalPurposeFlagIssueCounts,
                $entryNamesByGeneralPurposeFlagIssue,
                $entry,
            );
            self::recordZipEntryManifestCreatorHostProvenance(
                $creatorHostSystemCounts,
                $entryNamesByCreatorHostSystem,
                $creatorHostSystemNamesByRole,
                $creatorHostSystemNamesByHandoffKind,
                $creatorVersionComparisonCounts,
                $entryNamesByCreatorVersionComparison,
                $creatorVersionComparisonsByRole,
                $creatorVersionComparisonsByHandoffKind,
                $creatorHostSystemIssueCounts,
                $entryNamesByCreatorHostSystemIssue,
                $entry,
            );
            self::recordZipEntryManifestCrc32Summary($crc32SummariesByHex, $entry);

            if (!$entry['byteCountsAreExact']) {
                $unknownByteCountEntries[] = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'role' => $entry['role'],
                    'handoffKind' => $entry['handoffKind'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'compressedSize' => $entry['compressedSize'],
                    'uncompressedSize' => $entry['uncompressedSize'],
                    'issues' => $entry['issues'],
                ];
            } elseif (
                $largestPayloadEntry === null
                || $exactUncompressedSize > $largestPayloadEntry['uncompressedSize']
            ) {
                $largestPayloadEntry = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'role' => $entry['role'],
                    'handoffKind' => $entry['handoffKind'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'compressedSize' => $exactCompressedSize,
                    'uncompressedSize' => $exactUncompressedSize,
                ];
            }
            if ($entry['byteCountsAreExact']) {
                $payloadEntries[] = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'role' => $entry['role'],
                    'handoffKind' => $entry['handoffKind'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'compressedSize' => $exactCompressedSize,
                    'uncompressedSize' => $exactUncompressedSize,
                ];
            }

            foreach ($entry['issues'] as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                self::appendUniqueString($issues, $issue);
                self::recordZipManifestIssueProvenance(
                    $entryNamesByIssue,
                    $partNamesByIssue,
                    $issue,
                    $entry['entryName'],
                    $entry['partName'],
                );
            }

            if ($entry['relationshipPart']) {
                $relationshipPartCount++;
                if ($entry['relationshipSource'] === '/') {
                    $rootRelationshipPartCount++;
                } else {
                    $partRelationshipPartCount++;
                }

                $relationshipParts[] = [
                    'entryName' => $entry['entryName'],
                    'partName' => $entry['partName'],
                    'relationshipSource' => $entry['relationshipSource'],
                    'relationshipSourceExists' => $entry['relationshipSourceExists'],
                    'issues' => $entry['issues'],
                ];
            }

            if (in_array('invalid-relationship-part-name', $entry['issues'], true)) {
                $invalidRelationshipPartCount++;
            }
            if (in_array('reserved-relationship-directory-part', $entry['issues'], true)) {
                $reservedRelationshipDirectoryPartCount++;
            }
            if (in_array('orphan-relationship-part', $entry['issues'], true)) {
                $orphanRelationshipPartCount++;
            }
            if (in_array('relationship-part-source', $entry['issues'], true)) {
                $relationshipPartSourceCount++;
            }
            if (in_array('content-types-item-source', $entry['issues'], true)) {
                $contentTypesItemRelationshipSourceCount++;
            }

            if ($entry['role'] === 'document-properties') {
                $documentPropertyPartCount++;
            } elseif ($entry['role'] === 'digital-signature') {
                $digitalSignaturePartCount++;
            } elseif ($entry['role'] === 'embedded-package-candidate') {
                $embeddedPackageCandidateCount++;
            } elseif ($entry['role'] === 'media') {
                $mediaPartCandidateCount++;
            }

            if (in_array($entry['handoffKind'], ['content-types+xml', 'relationships+xml', 'xml'], true)) {
                $xmlPayloadPartCount++;
            } elseif (!$entry['isDirectory'] && $entry['handoffKind'] !== 'blocked') {
                $binaryPayloadPartCount++;
            }
        }

        ksort($issueCounts);
        self::sortZipManifestIssueProvenance($entryNamesByIssue);
        self::sortZipManifestIssueProvenance($partNamesByIssue);
        ksort($roleCounts);
        self::sortZipEntryManifestPathSegmentPositionProvenance(
            $pathSegmentPositionRoleEntryCounts,
            $entryNamesByPathSegmentPositionRole,
            $pathSegmentPositionHandoffKindEntryCounts,
            $entryNamesByPathSegmentPositionHandoffKind,
        );
        ksort($byteCountsByRole);
        ksort($byteCountsByHandoffKind);
        ksort($directoryRootCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByDirectoryRoot);
        $directoryRootSummaries = self::zipEntryManifestDirectoryRootSummaries($directoryRootSummariesByRoot);
        ksort($packagePartExtensionCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByPackagePartExtension);
        $packagePartExtensionSummaries = self::zipEntryManifestContentSummaries($packagePartExtensionSummariesByExtension);
        self::sortZipManifestCompressionMethodProvenance(
            $compressionMethodCounts,
            $entryNamesByCompressionMethod,
            $compressionMethodNamesByRole,
            $compressionMethodNamesByHandoffKind,
        );
        self::sortZipManifestGeneralPurposeFlagProvenance(
            $generalPurposeFlagCounts,
            $entryNamesByGeneralPurposeFlag,
            $generalPurposeFlagNamesByRole,
            $generalPurposeFlagNamesByHandoffKind,
            $generalPurposeFlagIssueCounts,
            $entryNamesByGeneralPurposeFlagIssue,
        );
        self::sortZipManifestCreatorHostProvenance(
            $creatorHostSystemCounts,
            $entryNamesByCreatorHostSystem,
            $creatorHostSystemNamesByRole,
            $creatorHostSystemNamesByHandoffKind,
            $creatorVersionComparisonCounts,
            $entryNamesByCreatorVersionComparison,
            $creatorVersionComparisonsByRole,
            $creatorVersionComparisonsByHandoffKind,
            $creatorHostSystemIssueCounts,
            $entryNamesByCreatorHostSystemIssue,
        );
        $crc32Provenance = self::zipEntryManifestCrc32Provenance($crc32SummariesByHex);
        sort($contentTypesItems, SORT_STRING);
        usort(
            $relationshipParts,
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );
        $largestPayloadEntries = self::largestZipManifestPayloadEntries(
            $payloadEntries,
            self::ZIP_MANIFEST_LARGEST_PAYLOAD_ENTRY_LIMIT
        );
        $zipSourceRecordManifest = self::zipEntrySourceRecordManifest($entries);

        return [
            'valid' => $issues === [],
            'isSupportedByBoundedReader' => $issues === [],
            'packageSource' => $packageSource['packageSource'],
            'archiveLength' => $packageSource['archiveLength'],
            'archiveSha256' => $packageSource['archiveSha256'],
            'centralDirectoryOffset' => $packageSource['centralDirectoryOffset'],
            'centralDirectoryBytes' => $packageSource['centralDirectoryBytes'],
            'centralDirectoryEnd' => $packageSource['centralDirectoryEnd'],
            'centralDirectorySha256' => $packageSource['centralDirectorySha256'],
            'centralDirectoryToEocdGapOffset' => $packageSource['centralDirectoryToEocdGapOffset'],
            'centralDirectoryToEocdGapBytes' => $packageSource['centralDirectoryToEocdGapBytes'],
            'centralDirectoryToEocdGapSha256' => $packageSource['centralDirectoryToEocdGapSha256'],
            'endOfCentralDirectoryOffset' => $packageSource['endOfCentralDirectoryOffset'],
            'endOfCentralDirectoryBytes' => $packageSource['endOfCentralDirectoryBytes'],
            'endOfCentralDirectoryEnd' => $packageSource['endOfCentralDirectoryEnd'],
            'endOfCentralDirectorySha256' => $packageSource['endOfCentralDirectorySha256'],
            'endOfCentralDirectoryFixedFields' => $packageSource['endOfCentralDirectoryFixedFields'],
            'endOfCentralDirectoryFixedFieldIssueCount' => $packageSource['endOfCentralDirectoryFixedFieldIssueCount'],
            'hasEndOfCentralDirectoryFixedFieldIssues' => $packageSource['hasEndOfCentralDirectoryFixedFieldIssues'],
            'endOfCentralDirectoryFixedFieldIssues' => $packageSource['endOfCentralDirectoryFixedFieldIssues'],
            'packageCommentOffset' => $packageSource['packageCommentOffset'],
            'packageCommentBytes' => $packageSource['packageCommentBytes'],
            'packageCommentEnd' => $packageSource['packageCommentEnd'],
            'packageCommentSha256' => $packageSource['packageCommentSha256'],
            'packageCommentPreviewHex' => $packageSource['packageCommentPreviewHex'],
            'packageCommentPreviewByteCount' => $packageSource['packageCommentPreviewByteCount'],
            'packageCommentByteExposurePolicy' => $packageSource['packageCommentByteExposurePolicy'],
            'canExposePackageCommentBytes' => $packageSource['canExposePackageCommentBytes'],
            'hasPackageComment' => $packageSource['hasPackageComment'],
            'hasCentralDirectorySignature' => $packageSource['hasCentralDirectorySignature'],
            'centralDirectorySignatureOffset' => $packageSource['centralDirectorySignatureOffset'],
            'centralDirectorySignatureDataOffset' => $packageSource['centralDirectorySignatureDataOffset'],
            'centralDirectorySignatureEnd' => $packageSource['centralDirectorySignatureEnd'],
            'centralDirectorySignatureBytes' => $packageSource['centralDirectorySignatureBytes'],
            'centralDirectorySignatureRecordBytes' => $packageSource['centralDirectorySignatureRecordBytes'],
            'centralDirectorySignaturePreviewHex' => $packageSource['centralDirectorySignaturePreviewHex'],
            'centralDirectorySignaturePreviewByteCount' => $packageSource['centralDirectorySignaturePreviewByteCount'],
            'centralDirectorySignatureSha256' => $packageSource['centralDirectorySignatureSha256'],
            'centralDirectorySignatureLocation' => $packageSource['centralDirectorySignatureLocation'],
            'centralDirectorySignatureVerification' => $packageSource['centralDirectorySignatureVerification'],
            'centralDirectorySignatureByteExposurePolicy' => $packageSource['centralDirectorySignatureByteExposurePolicy'],
            'centralDirectorySignatureCanExposeBytes' => $packageSource['centralDirectorySignatureCanExposeBytes'],
            'zipCentralDirectoryValid' => $centralDirectory['isSupportedByBoundedReader'],
            'centralDirectoryIssues' => $centralDirectory['issues'],
            'localHeaderNamesValid' => $localHeaderNames !== null
                && $localHeaderNames['isSupportedByBoundedReader'],
            'localHeaderNameIssues' => $localHeaderNames['issues'] ?? (
                $localHeaderNamePreflightError === null ? [] : ['local-header-name-preflight-error']
            ),
            'localHeaderNamePreflightError' => $localHeaderNamePreflightError,
            'localHeaderNameMismatchEntryCount' => $localHeaderNames['mismatchedEntryCount'] ?? 0,
            'localHeaderNameMismatchedEntries' => $localHeaderNames['mismatchedEntries'] ?? [],
            'localHeaderMetadataValid' => $localHeaderMetadata !== null
                && $localHeaderMetadataManifestIssues === [],
            'localHeaderMetadataIssues' => $localHeaderMetadata !== null ? $localHeaderMetadataManifestIssues : (
                $localHeaderMetadataPreflightError === null ? [] : ['local-header-metadata-preflight-error']
            ),
            'localHeaderMetadataPreflightError' => $localHeaderMetadataPreflightError,
            'localHeaderMetadataMismatchEntryCount' => count($localHeaderMetadataManifestMismatchedEntries),
            'localHeaderMetadataMismatchedEntries' => $localHeaderMetadataManifestMismatchedEntries,
            'localHeaderSpansValid' => $localHeaderSpans !== null
                && $localHeaderSpans['isSupportedByBoundedReader'],
            'localHeaderSpanIssues' => $localHeaderSpans['issues'] ?? (
                $localHeaderSpanPreflightError === null ? [] : ['local-header-span-preflight-error']
            ),
            'localHeaderSpanPreflightError' => $localHeaderSpanPreflightError,
            'byteCountsAreExact' => $centralDirectory['totalsAreExact'],
            'unknownByteCountEntryCount' => count($unknownByteCountEntries),
            'declaredEntryCount' => $centralDirectory['declaredEntryCount'],
            'entryCount' => count($entries),
            'fileEntryCount' => $fileEntryCount,
            'directoryEntryCount' => $directoryEntryCount,
            'packagePartCount' => $packagePartCount,
            'compressedPayloadBytes' => $fileCompressedBytes + $directoryCompressedBytes,
            'uncompressedPayloadBytes' => $fileUncompressedBytes + $directoryUncompressedBytes,
            'fileCompressedBytes' => $fileCompressedBytes,
            'fileUncompressedBytes' => $fileUncompressedBytes,
            'directoryCompressedBytes' => $directoryCompressedBytes,
            'directoryUncompressedBytes' => $directoryUncompressedBytes,
            'zipSourceRecordManifestVersion' => $zipSourceRecordManifest['manifestVersion'],
            'zipSourceRecordManifestSha256' => $zipSourceRecordManifest['manifestSha256'],
            'zipSourceRecordByteCountsAreExact' => $zipSourceRecordManifest['byteCountsAreExact'],
            'zipSourceRecordEntryCount' => $zipSourceRecordManifest['entryCount'],
            'zipSourceRecordUnknownEntryCount' => $zipSourceRecordManifest['unknownSourceRecordEntryCount'],
            'zipSourceRecordLocalRecordBytes' => $zipSourceRecordManifest['localRecordBytes'],
            'zipSourceRecordKnownLocalRecordBytes' => $zipSourceRecordManifest['knownLocalRecordBytes'],
            'zipSourceRecordLocalHeaderBytes' => $zipSourceRecordManifest['localHeaderBytes'],
            'zipSourceRecordCompressedDataBytes' => $zipSourceRecordManifest['compressedDataBytes'],
            'zipSourceRecordDataDescriptorBytes' => $zipSourceRecordManifest['dataDescriptorBytes'],
            'zipSourceRecordDataDescriptorEntryCount' => $zipSourceRecordManifest['dataDescriptorEntryCount'],
            'zipSourceRecordCentralDirectoryRecordBytes' => $zipSourceRecordManifest['centralDirectoryRecordBytes'],
            'zipSourceRecordKnownCentralDirectoryRecordBytes' => $zipSourceRecordManifest['knownCentralDirectoryRecordBytes'],
            'zipSourceRecordBytes' => $zipSourceRecordManifest['sourceRecordBytes'],
            'zipSourceRecordKnownBytes' => $zipSourceRecordManifest['knownSourceRecordBytes'],
            'zipSourceRecordReviewFieldByteCountsAreExact' => $zipSourceRecordManifest['reviewFieldByteCountsAreExact'],
            'zipSourceRecordLocalHeaderReviewFieldBytes' => $zipSourceRecordManifest['localHeaderReviewFieldBytes'],
            'zipSourceRecordKnownLocalHeaderReviewFieldBytes' => $zipSourceRecordManifest['knownLocalHeaderReviewFieldBytes'],
            'zipSourceRecordCentralDirectoryReviewFieldBytes' => $zipSourceRecordManifest['centralDirectoryReviewFieldBytes'],
            'zipSourceRecordKnownCentralDirectoryReviewFieldBytes' => $zipSourceRecordManifest['knownCentralDirectoryReviewFieldBytes'],
            'zipSourceRecordReviewFieldBytes' => $zipSourceRecordManifest['reviewFieldBytes'],
            'zipSourceRecordKnownReviewFieldBytes' => $zipSourceRecordManifest['knownReviewFieldBytes'],
            'zipSourceRecordReviewFieldBytesByRole' => $zipSourceRecordManifest['reviewFieldBytesByRole'],
            'zipSourceRecordReviewFieldBytesByHandoffKind' => $zipSourceRecordManifest['reviewFieldBytesByHandoffKind'],
            'zipSourceRecordHandoffKindSummaryCount' => $zipSourceRecordManifest['handoffKindSummaryCount'],
            'zipSourceRecordHandoffKindSummaries' => $zipSourceRecordManifest['handoffKindSummaries'],
            'zipSourceRecordManifest' => $zipSourceRecordManifest,
            ...self::zipExtraFieldManifestSummaryFields($zipExtraFields, $zipExtraFieldPreflightError),
            'directoryRootCount' => count($directoryRootCounts),
            'directoryRootCounts' => $directoryRootCounts,
            'entryNamesByDirectoryRoot' => $entryNamesByDirectoryRoot,
            'directoryRootSummaries' => $directoryRootSummaries,
            'pathSegmentPositionRoleEntryCounts' => $pathSegmentPositionRoleEntryCounts,
            'entryNamesByPathSegmentPositionRole' => $entryNamesByPathSegmentPositionRole,
            'pathSegmentPositionHandoffKindEntryCounts' => $pathSegmentPositionHandoffKindEntryCounts,
            'entryNamesByPathSegmentPositionHandoffKind' => $entryNamesByPathSegmentPositionHandoffKind,
            'contentTypesItemCount' => count($contentTypesItems),
            'extensionlessPackagePartCount' => $extensionlessPackagePartCount,
            'packagePartExtensionCounts' => $packagePartExtensionCounts,
            'entryNamesByPackagePartExtension' => $entryNamesByPackagePartExtension,
            'packagePartExtensionSummaries' => $packagePartExtensionSummaries,
            'equivalentPackagePartNameGroupCount' => count($equivalentPackagePartNameGroups),
            'equivalentPackagePartNameEntryCount' => $equivalentPackagePartNameEntryCount,
            'relationshipPartCount' => $relationshipPartCount,
            'rootRelationshipPartCount' => $rootRelationshipPartCount,
            'partRelationshipPartCount' => $partRelationshipPartCount,
            'invalidRelationshipPartCount' => $invalidRelationshipPartCount,
            'reservedRelationshipDirectoryPartCount' => $reservedRelationshipDirectoryPartCount,
            'orphanRelationshipPartCount' => $orphanRelationshipPartCount,
            'relationshipPartSourceCount' => $relationshipPartSourceCount,
            'contentTypesItemRelationshipSourceCount' => $contentTypesItemRelationshipSourceCount,
            'documentPropertyPartCount' => $documentPropertyPartCount,
            'digitalSignaturePartCount' => $digitalSignaturePartCount,
            'embeddedPackageCandidateCount' => $embeddedPackageCandidateCount,
            'mediaPartCandidateCount' => $mediaPartCandidateCount,
            'xmlPayloadPartCount' => $xmlPayloadPartCount,
            'binaryPayloadPartCount' => $binaryPayloadPartCount,
            'issueCounts' => $issueCounts,
            'issues' => $issues,
            'entryNamesByIssue' => $entryNamesByIssue,
            'partNamesByIssue' => $partNamesByIssue,
            'roleCounts' => $roleCounts,
            'byteCountsByRole' => $byteCountsByRole,
            'byteCountsByHandoffKind' => $byteCountsByHandoffKind,
            'compressionMethodCounts' => $compressionMethodCounts,
            'entryNamesByCompressionMethod' => $entryNamesByCompressionMethod,
            'compressionMethodNamesByRole' => $compressionMethodNamesByRole,
            'compressionMethodNamesByHandoffKind' => $compressionMethodNamesByHandoffKind,
            'generalPurposeFlagCounts' => $generalPurposeFlagCounts,
            'entryNamesByGeneralPurposeFlag' => $entryNamesByGeneralPurposeFlag,
            'generalPurposeFlagNamesByRole' => $generalPurposeFlagNamesByRole,
            'generalPurposeFlagNamesByHandoffKind' => $generalPurposeFlagNamesByHandoffKind,
            'generalPurposeFlagIssueCounts' => $generalPurposeFlagIssueCounts,
            'entryNamesByGeneralPurposeFlagIssue' => $entryNamesByGeneralPurposeFlagIssue,
            'creatorHostSystemCounts' => $creatorHostSystemCounts,
            'entryNamesByCreatorHostSystem' => $entryNamesByCreatorHostSystem,
            'creatorHostSystemNamesByRole' => $creatorHostSystemNamesByRole,
            'creatorHostSystemNamesByHandoffKind' => $creatorHostSystemNamesByHandoffKind,
            'creatorVersionComparisonCounts' => $creatorVersionComparisonCounts,
            'entryNamesByCreatorVersionComparison' => $entryNamesByCreatorVersionComparison,
            'creatorVersionComparisonsByRole' => $creatorVersionComparisonsByRole,
            'creatorVersionComparisonsByHandoffKind' => $creatorVersionComparisonsByHandoffKind,
            'creatorHostSystemIssueCounts' => $creatorHostSystemIssueCounts,
            'entryNamesByCreatorHostSystemIssue' => $entryNamesByCreatorHostSystemIssue,
            'crc32SummaryCount' => $crc32Provenance['crc32SummaryCount'],
            'crc32Summaries' => $crc32Provenance['crc32Summaries'],
            'crc32HexCounts' => $crc32Provenance['crc32HexCounts'],
            'entryNamesByCrc32Hex' => $crc32Provenance['entryNamesByCrc32Hex'],
            'crc32HexesByRole' => $crc32Provenance['crc32HexesByRole'],
            'crc32HexesByHandoffKind' => $crc32Provenance['crc32HexesByHandoffKind'],
            'duplicateCrc32HexCount' => $crc32Provenance['duplicateCrc32HexCount'],
            'duplicateCrc32EntryCount' => $crc32Provenance['duplicateCrc32EntryCount'],
            'hasDuplicateCrc32Entries' => $crc32Provenance['hasDuplicateCrc32Entries'],
            'duplicateCrc32Hexes' => $crc32Provenance['duplicateCrc32Hexes'],
            'duplicateCrc32Summaries' => $crc32Provenance['duplicateCrc32Summaries'],
            'largestPayloadEntry' => $largestPayloadEntry,
            'largestPayloadEntryLimit' => self::ZIP_MANIFEST_LARGEST_PAYLOAD_ENTRY_LIMIT,
            'largestPayloadEntryCount' => count($largestPayloadEntries),
            'largestPayloadEntries' => $largestPayloadEntries,
            'unknownByteCountEntries' => $unknownByteCountEntries,
            'localHeaderOrder' => $localHeaderOrder,
            'localHeaderNames' => $localHeaderNames,
            'localHeaderMetadata' => $localHeaderMetadata,
            'localHeaderSpans' => $localHeaderSpans,
            'contentTypesItems' => $contentTypesItems,
            'equivalentPackagePartNameGroups' => $equivalentPackagePartNameGroups,
            'relationshipParts' => $relationshipParts,
            'entries' => $entries,
            'centralDirectory' => $centralDirectory,
        ];
    }

    /**
     * @return list<array{partName:string, equivalenceKey:string, equivalentPartNames:list<string>, valid:bool, issues:list<string>}>
     */
    public static function preflightPackagePartNameEquivalence(ZipPackage $package): array
    {
        $preflight = [];
        $indexesByEquivalenceKey = [];

        foreach (self::preflightPackagePartNames($package)['parts'] as $part) {
            if (!$part['isPackagePart'] || !$part['valid'] || $part['partName'] === null) {
                continue;
            }

            $partName = $part['partName'];
            $equivalenceKey = self::partNameEquivalenceKey($partName);
            $preflight[] = [
                'partName' => $partName,
                'equivalenceKey' => $equivalenceKey,
                'equivalentPartNames' => [],
                'valid' => true,
                'issues' => [],
            ];
            $indexesByEquivalenceKey[$equivalenceKey][] = array_key_last($preflight);
        }

        foreach ($indexesByEquivalenceKey as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($rowIndexes as $rowIndex) {
                $partNames[] = $preflight[$rowIndex]['partName'];
            }
            sort($partNames, SORT_STRING);

            foreach ($rowIndexes as $rowIndex) {
                $preflight[$rowIndex]['equivalentPartNames'] = $partNames;
                $preflight[$rowIndex]['valid'] = false;
                $preflight[$rowIndex]['issues'] = ['equivalent-part-name-case-collision'];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{entryName:string, partName:string, compressionMethod:int, compressionMethodName:string, compressedSize:int, uncompressedSize:int, contentType:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string, contentTypeOverridePartNameExactMatch:bool, contentTypeOverridePartNameEquivalentMatch:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceKind:string, sourceExists:?bool, duplicateRelationshipPartNames:list<string>, loaded:bool, loadAction:string, loadReason:string, relationshipCount:?int, valid:bool, issues:list<string>, parseError:?string, relationshipXmlIssueRecords:list<array{relationshipOrdinal:int, id:?string, type:?string, target:?string, targetMode:?string, duplicateOfOrdinal:?int, issues:list<string>}>}>
     */
    public static function preflightRelationshipPartsInPackage(ZipPackage $package): array
    {
        $contentTypesItemName = self::contentTypesItemNameInPackage($package);
        if ($contentTypesItemName === null) {
            throw new \RuntimeException('OPC package is missing [Content_Types].xml');
        }

        $contentTypes = OpcContentTypes::fromXml($package->read($contentTypesItemName));
        $packagePartNamesByEquivalenceKey = self::packagePartNamesByEquivalenceKey($package);
        $preflight = [];
        $sourceIndexes = [];
        foreach ($package->names() as $name) {
            if (!self::isRelationshipPartName($name)) {
                continue;
            }

            $entry = $package->entry($name);
            $partName = OpcPackagePath::canonicalPartName($name);
            $contentTypeResolution = $contentTypes->contentTypeResolutionForPart($partName);
            $contentType = $contentTypeResolution['contentType'];
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $sourceExists = null;
            $loaded = false;
            $relationshipCount = null;
            $parseError = null;
            $issues = [];

            try {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && self::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($relationshipSource)]);
            } catch (\InvalidArgumentException $exception) {
                $issues[] = 'invalid-relationship-part-name';
                $parseError = $exception->getMessage();
            }

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            } elseif (!self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                $issues[] = 'invalid-relationship-content-type';
            }

            if ($relationshipSourceIsRelationshipPart === true) {
                $issues[] = 'relationship-part-source';
            }

            if ($relationshipSource !== null && $relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                $issues[] = 'content-types-item-source';
            }

            if ($sourceExists === false) {
                $issues[] = 'orphan-relationship-part';
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'entryName' => $name,
                'partName' => $partName,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::zipCompressionMethodName($entry->compressionMethod),
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
                'contentType' => $contentType,
                'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
                'contentTypeDefaultExtension' => $contentTypeResolution['defaultExtension'],
                'contentTypeOverridePartName' => $contentTypeResolution['overridePartName'],
                'contentTypeOverridePartNameExactMatch' => $contentTypeResolution['overridePartNameExactMatch'],
                'contentTypeOverridePartNameEquivalentMatch' => $contentTypeResolution['overridePartNameEquivalentMatch'],
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'relationshipSourceKind' => self::relationshipSourceKind(
                    $relationshipSource,
                    $relationshipSourceIsRelationshipPart,
                    $sourceExists,
                ),
                'sourceExists' => $sourceExists,
                'duplicateRelationshipPartNames' => [],
                'loaded' => $loaded,
                'loadAction' => 'skipped',
                'loadReason' => self::relationshipPartLoadReason($issues, false),
                'relationshipCount' => $relationshipCount,
                'valid' => $issues === [],
                'issues' => $issues,
                'parseError' => $parseError,
                'relationshipXmlIssueRecords' => [],
            ];

            if ($relationshipSource !== null) {
                $sourceIndexes[$relationshipSource][] = array_key_last($preflight);
            }
        }

        foreach ($sourceIndexes as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($rowIndexes as $rowIndex) {
                $partNames[] = $preflight[$rowIndex]['partName'];
            }
            sort($partNames, SORT_STRING);

            foreach ($rowIndexes as $rowIndex) {
                $preflight[$rowIndex]['duplicateRelationshipPartNames'] = $partNames;
                $preflight[$rowIndex]['issues'][] = 'duplicate-relationship-source';
                $preflight[$rowIndex]['issues'] = array_values(array_unique($preflight[$rowIndex]['issues']));
                $preflight[$rowIndex]['valid'] = false;
                self::refreshRelationshipPartLoadDecision($preflight[$rowIndex]);
            }
        }

        foreach ($preflight as &$row) {
            if ($row['issues'] !== [] || $row['relationshipSource'] === null) {
                continue;
            }

            try {
                $relationshipXml = $package->read($row['partName']);
                $relationships = OpcRelationships::fromXml($relationshipXml, $row['relationshipSource']);
                $row['loaded'] = true;
                $row['loadAction'] = 'loaded';
                $row['loadReason'] = 'loaded';
                $row['relationshipCount'] = count($relationships->all());
            } catch (\Throwable $exception) {
                self::appendUniqueString($row['issues'], 'malformed-relationship-xml');
                $relationshipXmlDiagnostics = self::relationshipXmlIssueDiagnostics($relationshipXml ?? '');
                foreach ($relationshipXmlDiagnostics['issues'] as $issue) {
                    self::appendUniqueString($row['issues'], $issue);
                }
                $row['relationshipXmlIssueRecords'] = $relationshipXmlDiagnostics['records'];
                $row['issues'] = array_values(array_unique($row['issues']));
                $row['parseError'] = $exception->getMessage();
                $row['valid'] = false;
                self::refreshRelationshipPartLoadDecision($row);
            }
        }
        unset($row);

        return $preflight;
    }

    /**
     * @return array{valid:bool, relationshipPartCount:int, loadedCount:int, skippedCount:int, validCount:int, invalidCount:int, relationshipCount:int, compressedRelationshipPartBytes:int, uncompressedRelationshipPartBytes:int, loadedCompressedRelationshipPartBytes:int, loadedUncompressedRelationshipPartBytes:int, skippedCompressedRelationshipPartBytes:int, skippedUncompressedRelationshipPartBytes:int, relationshipXmlIssueRecordCount:int, loadedPartNames:list<string>, skippedPartNames:list<string>, loadedSources:list<string>, skippedSources:list<string>, loadActionCounts:array<string, int>, loadReasonCounts:array<string, int>, contentTypeSourceCounts:array<string, int>, sourceKindCounts:array<string, int>, byteCountsByLoadAction:array<string, array{entryCount:int, compressedBytes:int, uncompressedBytes:int}>, byteCountsByLoadReason:array<string, array{entryCount:int, compressedBytes:int, uncompressedBytes:int}>, byteCountsBySourceKind:array<string, array{entryCount:int, compressedBytes:int, uncompressedBytes:int}>, partNamesByLoadReason:array<string, list<string>>, partNamesByContentTypeSource:array<string, list<string>>, partNamesBySourceKind:array<string, list<string>>, issueCounts:array<string, int>, partNamesByIssue:array<string, list<string>>, relationshipXmlIssueRecords:list<array{partName:string, relationshipSource:?string, relationshipOrdinal:int, id:?string, type:?string, target:?string, targetMode:?string, duplicateOfOrdinal:?int, issues:list<string>}>, issues:list<string>}
     */
    public static function relationshipPartLoadSummary(ZipPackage $package): array
    {
        $summary = [
            'valid' => true,
            'relationshipPartCount' => 0,
            'loadedCount' => 0,
            'skippedCount' => 0,
            'validCount' => 0,
            'invalidCount' => 0,
            'relationshipCount' => 0,
            'compressedRelationshipPartBytes' => 0,
            'uncompressedRelationshipPartBytes' => 0,
            'loadedCompressedRelationshipPartBytes' => 0,
            'loadedUncompressedRelationshipPartBytes' => 0,
            'skippedCompressedRelationshipPartBytes' => 0,
            'skippedUncompressedRelationshipPartBytes' => 0,
            'relationshipXmlIssueRecordCount' => 0,
            'loadedPartNames' => [],
            'skippedPartNames' => [],
            'loadedSources' => [],
            'skippedSources' => [],
            'loadActionCounts' => [],
            'loadReasonCounts' => [],
            'contentTypeSourceCounts' => [],
            'sourceKindCounts' => [],
            'byteCountsByLoadAction' => [],
            'byteCountsByLoadReason' => [],
            'byteCountsBySourceKind' => [],
            'partNamesByLoadReason' => [],
            'partNamesByContentTypeSource' => [],
            'partNamesBySourceKind' => [],
            'issueCounts' => [],
            'partNamesByIssue' => [],
            'relationshipXmlIssueRecords' => [],
            'issues' => [],
        ];

        foreach (self::preflightRelationshipPartsInPackage($package) as $part) {
            $summary['relationshipPartCount']++;
            $summary['loadActionCounts'][$part['loadAction']] = ($summary['loadActionCounts'][$part['loadAction']] ?? 0) + 1;
            $summary['loadReasonCounts'][$part['loadReason']] = ($summary['loadReasonCounts'][$part['loadReason']] ?? 0) + 1;
            $summary['contentTypeSourceCounts'][$part['contentTypeSource']] = ($summary['contentTypeSourceCounts'][$part['contentTypeSource']] ?? 0) + 1;
            $summary['sourceKindCounts'][$part['relationshipSourceKind']] = ($summary['sourceKindCounts'][$part['relationshipSourceKind']] ?? 0) + 1;
            $summary['partNamesByLoadReason'][$part['loadReason']][] = $part['partName'];
            $summary['partNamesByContentTypeSource'][$part['contentTypeSource']][] = $part['partName'];
            $summary['partNamesBySourceKind'][$part['relationshipSourceKind']][] = $part['partName'];
            $summary['compressedRelationshipPartBytes'] += $part['compressedSize'];
            $summary['uncompressedRelationshipPartBytes'] += $part['uncompressedSize'];
            self::incrementZipEntryManifestByteBucket(
                $summary['byteCountsByLoadAction'],
                $part['loadAction'],
                $part['compressedSize'],
                $part['uncompressedSize'],
            );
            self::incrementZipEntryManifestByteBucket(
                $summary['byteCountsByLoadReason'],
                $part['loadReason'],
                $part['compressedSize'],
                $part['uncompressedSize'],
            );
            self::incrementZipEntryManifestByteBucket(
                $summary['byteCountsBySourceKind'],
                $part['relationshipSourceKind'],
                $part['compressedSize'],
                $part['uncompressedSize'],
            );

            if ($part['loaded']) {
                $summary['loadedCount']++;
                $summary['loadedPartNames'][] = $part['partName'];
                $summary['loadedCompressedRelationshipPartBytes'] += $part['compressedSize'];
                $summary['loadedUncompressedRelationshipPartBytes'] += $part['uncompressedSize'];
                $summary['relationshipCount'] += $part['relationshipCount'] ?? 0;
                if ($part['relationshipSource'] !== null) {
                    self::appendUniqueString($summary['loadedSources'], $part['relationshipSource']);
                }
            } else {
                $summary['skippedCount']++;
                $summary['skippedPartNames'][] = $part['partName'];
                $summary['skippedCompressedRelationshipPartBytes'] += $part['compressedSize'];
                $summary['skippedUncompressedRelationshipPartBytes'] += $part['uncompressedSize'];
                if ($part['relationshipSource'] !== null) {
                    self::appendUniqueString($summary['skippedSources'], $part['relationshipSource']);
                }
            }

            if ($part['valid']) {
                $summary['validCount']++;
            } else {
                $summary['invalidCount']++;
                $summary['valid'] = false;
            }

            foreach ($part['issues'] as $issue) {
                self::appendUniqueString($summary['issues'], $issue);
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                $summary['partNamesByIssue'][$issue][] = $part['partName'];
            }

            foreach ($part['relationshipXmlIssueRecords'] as $record) {
                $summary['relationshipXmlIssueRecordCount']++;
                $summary['relationshipXmlIssueRecords'][] = [
                    'partName' => $part['partName'],
                    'relationshipSource' => $part['relationshipSource'],
                ] + $record;
            }
        }

        foreach ([
            'loadedPartNames',
            'skippedPartNames',
            'loadedSources',
            'skippedSources',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }

        ksort($summary['loadActionCounts'], SORT_STRING);
        ksort($summary['loadReasonCounts'], SORT_STRING);
        ksort($summary['contentTypeSourceCounts'], SORT_STRING);
        ksort($summary['sourceKindCounts'], SORT_STRING);
        ksort($summary['byteCountsByLoadAction'], SORT_STRING);
        ksort($summary['byteCountsByLoadReason'], SORT_STRING);
        ksort($summary['byteCountsBySourceKind'], SORT_STRING);
        ksort($summary['partNamesByLoadReason'], SORT_STRING);
        foreach ($summary['partNamesByLoadReason'] as &$partNames) {
            sort($partNames, SORT_STRING);
        }
        unset($partNames);
        ksort($summary['partNamesByContentTypeSource'], SORT_STRING);
        foreach ($summary['partNamesByContentTypeSource'] as &$partNames) {
            sort($partNames, SORT_STRING);
        }
        unset($partNames);
        ksort($summary['partNamesBySourceKind'], SORT_STRING);
        foreach ($summary['partNamesBySourceKind'] as &$partNames) {
            sort($partNames, SORT_STRING);
        }
        unset($partNames);

        ksort($summary['issueCounts'], SORT_STRING);
        ksort($summary['partNamesByIssue'], SORT_STRING);
        foreach ($summary['partNamesByIssue'] as &$partNames) {
            sort($partNames, SORT_STRING);
        }
        unset($partNames);

        usort(
            $summary['relationshipXmlIssueRecords'],
            static fn (array $left, array $right): int => [$left['partName'], $left['relationshipOrdinal']]
                <=> [$right['partName'], $right['relationshipOrdinal']],
        );

        return $summary;
    }

    public function package(): ZipPackage
    {
        return $this->package;
    }

    public function contentTypes(): OpcContentTypes
    {
        return $this->contentTypes;
    }

    public function hasRelationshipsForSource(string $sourcePartName = '/'): bool
    {
        return isset($this->relationshipsBySource[$this->relationshipSourceNameForEquivalent($sourcePartName)]);
    }

    public function relationshipsForSource(string $sourcePartName = '/'): ?OpcRelationships
    {
        return $this->relationshipsBySource[$this->relationshipSourceNameForEquivalent($sourcePartName)] ?? null;
    }

    public function requireRelationshipsForSource(string $sourcePartName = '/'): OpcRelationships
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $relationships = $this->relationshipsBySource[$sourcePartName] ?? null;
        if (!$relationships instanceof OpcRelationships) {
            throw new \RuntimeException('OPC relationship part not found: ' . OpcRelationships::relationshipPartNameForSource($sourcePartName));
        }

        return $relationships;
    }

    /**
     * @return list<string>
     */
    public function sourcePartNames(): array
    {
        $sourcePartNames = array_keys($this->relationshipsBySource);
        sort($sourcePartNames, SORT_STRING);

        return array_values($sourcePartNames);
    }

    public function firstTargetOfType(string $relationshipType, string $sourcePartName = '/'): ?string
    {
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return null;
        }

        $relationship = $relationships->firstOfType($relationshipType);
        if (!$relationship instanceof OpcRelationship) {
            return null;
        }

        $target = $relationships->resolveTarget($relationship);
        if ($relationship->isExternal()) {
            return $target;
        }

        return $this->packageEquivalentTarget($target);
    }

    /**
     * @return list<array{id:string, type:string, target:string, contentType:?string, external:bool}>
     */
    public function summarizeTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $items = $relationshipType === null
            ? $relationships->all()
            : $relationships->ofType($relationshipType);

        $summary = [];
        foreach ($items as $relationship) {
            $target = $relationships->resolveTarget($relationship);
            if (!$relationship->isExternal()) {
                $target = $this->packageEquivalentTarget($target);
            }
            $summary[] = [
                'id' => $relationship->id,
                'type' => $relationship->type,
                'target' => $target,
                'contentType' => $relationship->isExternal() ? null : $this->contentTypes->contentTypeForPart($target),
                'external' => $relationship->isExternal(),
            ];
        }

        return $summary;
    }

    /**
     * @return list<array{id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $items = $relationshipType === null
            ? $relationships->all()
            : $relationships->ofType($relationshipType);

        $preflight = [];
        foreach ($items as $relationship) {
            $typePreflight = $relationship->relationshipTypePreflight();
            if ($relationship->isExternal()) {
                $externalTarget = $relationship->externalTargetPreflight();
                $externalRewrite = self::externalTargetRewritePolicy($sourcePartName, $externalTarget);
                $externalPackagePartIssues = $this->externalTargetPackagePartIssues(
                    $sourcePartName,
                    $relationship->target,
                    $externalTarget,
                );
                $issues = array_values(array_unique(array_merge(
                    $typePreflight['issues'],
                    $externalTarget['issues'],
                    $externalRewrite['issues'],
                    $externalPackagePartIssues,
                )));
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'relationshipTypeKind' => $typePreflight['kind'],
                    'relationshipTypeScheme' => $typePreflight['scheme'],
                    'relationshipTypeValid' => $typePreflight['valid'],
                    'relationshipTypeIssues' => $typePreflight['issues'],
                    'target' => $relationship->target,
                    'contentType' => null,
                    'contentTypeSource' => null,
                    'contentTypeDefaultExtension' => null,
                    'contentTypeOverridePartName' => null,
                    'contentTypeOverridePartNameExactMatch' => null,
                    'contentTypeOverridePartNameEquivalentMatch' => null,
                    'external' => true,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => $externalTarget['kind'],
                    'externalTargetScheme' => $externalTarget['scheme'],
                    'externalTargetAllowed' => $externalTarget['allowed'],
                    'externalTargetRequiresBaseUri' => $externalRewrite['requiresBaseUri'],
                    'externalTargetRewriteBasePart' => $externalRewrite['basePart'],
                    'externalTargetRewriteReason' => $externalRewrite['reason'],
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
                continue;
            }

            try {
                self::assertInternalRelationshipTargetUriReferenceSuffix($relationship->target);
                $target = $relationships->resolveTarget($relationship);
            } catch (\InvalidArgumentException $exception) {
                $issues = array_values(array_unique(array_merge(
                    $typePreflight['issues'],
                    ['invalid-target'],
                    self::internalTargetIssues($relationship->target, $exception->getMessage()),
                )));
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'relationshipTypeKind' => $typePreflight['kind'],
                    'relationshipTypeScheme' => $typePreflight['scheme'],
                    'relationshipTypeValid' => $typePreflight['valid'],
                    'relationshipTypeIssues' => $typePreflight['issues'],
                    'target' => $relationship->target,
                    'contentType' => null,
                    'contentTypeSource' => null,
                    'contentTypeDefaultExtension' => null,
                    'contentTypeOverridePartName' => null,
                    'contentTypeOverridePartNameExactMatch' => null,
                    'contentTypeOverridePartNameEquivalentMatch' => null,
                    'external' => false,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => null,
                    'externalTargetScheme' => null,
                    'externalTargetAllowed' => null,
                    'externalTargetRequiresBaseUri' => null,
                    'externalTargetRewriteBasePart' => null,
                    'externalTargetRewriteReason' => null,
                    'valid' => false,
                    'issues' => $issues,
                ];
                continue;
            }

            $targetPartName = OpcPackagePath::stripQueryAndFragment($target);
            $target = $this->packageEquivalentTarget($target);
            $targetPartName = OpcPackagePath::stripQueryAndFragment($target);
            $exists = $this->packagePartNameForEquivalent($targetPartName) !== null;
            $contentTypeResolution = $this->contentTypes->contentTypeResolutionForPart($targetPartName);
            $contentType = $contentTypeResolution['contentType'];
            $relationshipPartTarget = self::isRelationshipPartName($targetPartName);
            $reservedRelationshipDirectoryTarget = !$relationshipPartTarget
                && self::isReservedRelationshipDirectoryPartName($targetPartName);
            $issues = $typePreflight['issues'];

            if (!$exists) {
                $issues[] = 'missing-in-package';
            }

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            }

            if (
                $contentType !== null
                && self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)
                && !$relationshipPartTarget
            ) {
                $issues[] = 'relationship-content-type-on-non-relationship-part';
            }

            if ($relationshipPartTarget) {
                $issues[] = 'targets-relationship-part';
            }

            if ($reservedRelationshipDirectoryTarget) {
                $issues[] = 'targets-reserved-relationship-directory-part';
            }

            if (self::isContentTypesItemName($targetPartName)) {
                $issues[] = 'targets-content-types-item';
            }

            $preflight[] = [
                'id' => $relationship->id,
                'type' => $relationship->type,
                'relationshipTypeKind' => $typePreflight['kind'],
                'relationshipTypeScheme' => $typePreflight['scheme'],
                'relationshipTypeValid' => $typePreflight['valid'],
                'relationshipTypeIssues' => $typePreflight['issues'],
                'target' => $target,
                'contentType' => $contentType,
                'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
                'contentTypeDefaultExtension' => $contentTypeResolution['defaultExtension'],
                'contentTypeOverridePartName' => $contentTypeResolution['overridePartName'],
                'contentTypeOverridePartNameExactMatch' => $contentTypeResolution['overridePartNameExactMatch'],
                'contentTypeOverridePartNameEquivalentMatch' => $contentTypeResolution['overridePartNameEquivalentMatch'],
                'external' => false,
                'exists' => $exists,
                'relationshipPartTarget' => $relationshipPartTarget,
                'externalTargetKind' => null,
                'externalTargetScheme' => null,
                'externalTargetAllowed' => null,
                'externalTargetRequiresBaseUri' => null,
                'externalTargetRewriteBasePart' => null,
                'externalTargetRewriteReason' => null,
                'valid' => $issues === [],
                'issues' => array_values(array_unique($issues)),
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, relationshipPartLoadAction:?string, relationshipPartLoadReason:?string, sourceExists:?bool, valid:bool, issues:list<string>}>
     */
    public function preflightPackageParts(): array
    {
        $preflight = [];
        foreach ($this->package->names() as $name) {
            if (str_ends_with($name, '/') || self::isContentTypesItemName($name)) {
                continue;
            }

            $partName = OpcPackagePath::canonicalPartName($name);
            $contentTypeResolution = $this->contentTypes->contentTypeResolutionForPart($partName);
            $contentType = $contentTypeResolution['contentType'];
            $relationshipPart = self::isRelationshipPartName($partName);
            $reservedRelationshipDirectoryPart = !$relationshipPart
                && self::isReservedRelationshipDirectoryPartName($partName);
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $relationshipSourceLoaded = null;
            $relationshipPartLoadAction = null;
            $relationshipPartLoadReason = null;
            $sourceExists = null;
            $issues = [];

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            }

            if ($relationshipPart) {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && OpcRelationships::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || $this->packagePartNameForEquivalent($relationshipSource) !== null;
                $relationshipSourceLoaded = $this->hasRelationshipsForSource($relationshipSource);

                if ($contentType !== null && !self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                    $issues[] = 'invalid-relationship-content-type';
                }

                if ($relationshipSourceIsRelationshipPart) {
                    $issues[] = 'relationship-part-source';
                }

                if ($relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                    $issues[] = 'content-types-item-source';
                }

                if (!$sourceExists) {
                    $issues[] = 'orphan-relationship-part';
                }

                $relationshipPartLoadAction = $relationshipSourceLoaded === true && $issues === []
                    ? 'loaded'
                    : 'skipped';
                $relationshipPartLoadReason = self::relationshipPartLoadReason($issues, $relationshipSourceLoaded === true && $issues === []);
            } elseif (
                $contentType !== null
                && self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)
            ) {
                $issues[] = 'relationship-content-type-on-non-relationship-part';
            }

            if ($reservedRelationshipDirectoryPart) {
                $issues[] = 'reserved-relationship-directory-part';
            }

            $preflight[] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
                'contentTypeDefaultExtension' => $contentTypeResolution['defaultExtension'],
                'contentTypeOverridePartName' => $contentTypeResolution['overridePartName'],
                'contentTypeOverridePartNameExactMatch' => $contentTypeResolution['overridePartNameExactMatch'],
                'contentTypeOverridePartNameEquivalentMatch' => $contentTypeResolution['overridePartNameEquivalentMatch'],
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'relationshipSourceLoaded' => $relationshipSourceLoaded,
                'relationshipPartLoadAction' => $relationshipPartLoadAction,
                'relationshipPartLoadReason' => $relationshipPartLoadReason,
                'sourceExists' => $sourceExists,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:string, exists:bool, packagePartName:?string, partNameExactMatch:bool, partNameEquivalentMatch:bool, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>
     */
    public function preflightContentTypeOverrides(): array
    {
        $preflight = [];
        foreach ($this->contentTypes->overrides() as $partName => $contentType) {
            $packagePartName = $this->packagePartNameForEquivalent($partName);
            $exists = $packagePartName !== null;
            $partNameExactMatch = $packagePartName === $partName;
            $partNameEquivalentMatch = $packagePartName !== null && $packagePartName !== $partName;
            $relationshipPart = self::isRelationshipPartName($partName);
            $reservedRelationshipDirectoryPart = !$relationshipPart
                && self::isReservedRelationshipDirectoryPartName($partName);
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $relationshipSourceLoaded = null;
            $sourceExists = null;
            $issues = [];

            if (!$exists) {
                $issues[] = 'override-target-missing-part';
            }

            if (self::isContentTypesItemName($partName)) {
                $issues[] = 'content-types-override-target';
            }

            if ($relationshipPart) {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && OpcRelationships::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || $this->packagePartNameForEquivalent($relationshipSource) !== null;
                $relationshipSourceLoaded = $this->hasRelationshipsForSource($relationshipSource);

                if (!self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                    $issues[] = 'invalid-relationship-content-type';
                }

                if ($relationshipSourceIsRelationshipPart) {
                    $issues[] = 'relationship-part-source';
                }

                if ($relationshipSource !== '/' && self::isContentTypesItemName($relationshipSource)) {
                    $issues[] = 'content-types-item-source';
                }

                if (!$sourceExists) {
                    $issues[] = 'relationship-override-source-missing';
                }
            } elseif (self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                $issues[] = 'relationship-content-type-on-non-relationship-part';
            }

            if ($reservedRelationshipDirectoryPart) {
                $issues[] = 'reserved-relationship-directory-override';
            }

            $preflight[] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'exists' => $exists,
                'packagePartName' => $packagePartName,
                'partNameExactMatch' => $partNameExactMatch,
                'partNameEquivalentMatch' => $partNameEquivalentMatch,
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'relationshipSourceLoaded' => $relationshipSourceLoaded,
                'sourceExists' => $sourceExists,
                'valid' => $issues === [],
                'issues' => array_values(array_unique($issues)),
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightAllRelationshipTargets(?string $relationshipType = null): array
    {
        $preflight = [];
        foreach ($this->sourcePartNames() as $sourcePartName) {
            foreach ($this->preflightTargetsForSource($sourcePartName, $relationshipType) as $target) {
                $preflight[] = [
                    'source' => $sourcePartName,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'target' => $target['target'],
                    'targetPart' => self::targetPartFromPreflightTarget($target),
                    'contentType' => $target['contentType'],
                    'contentTypeSource' => $target['contentTypeSource'],
                    'contentTypeDefaultExtension' => $target['contentTypeDefaultExtension'],
                    'contentTypeOverridePartName' => $target['contentTypeOverridePartName'],
                    'contentTypeOverridePartNameExactMatch' => $target['contentTypeOverridePartNameExactMatch'],
                    'contentTypeOverridePartNameEquivalentMatch' => $target['contentTypeOverridePartNameEquivalentMatch'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, targetQuery:?string, targetFragment:?string, sameSourceReference:bool, contentType:?string, exists:?bool, relationshipPartTarget:bool, valid:bool, issues:list<string>}>
     */
    public function preflightInternalTargetReferences(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $references = [];

        foreach ($this->preflightTargetsForSource($sourcePartName, $relationshipType) as $target) {
            if ($target['external']) {
                continue;
            }

            $targetPart = self::targetPartFromPreflightTarget($target);
            $suffix = $targetPart === null
                ? ['query' => null, 'fragment' => null]
                : self::targetQueryAndFragment($target['target']);

            $references[] = [
                'source' => $sourcePartName,
                'id' => $target['id'],
                'type' => $target['type'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => $targetPart,
                'targetQuery' => $suffix['query'],
                'targetFragment' => $suffix['fragment'],
                'sameSourceReference' => $targetPart !== null
                    && self::partNameEquivalenceKey($targetPart) === self::partNameEquivalenceKey($sourcePartName),
                'contentType' => $target['contentType'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
        }

        return $references;
    }

    /**
     * @return array{relationshipType:?string, valid:bool, relationshipCount:int, validTargetCount:int, invalidTargetCount:int, internalTargetCount:int, externalTargetCount:int, existingInternalTargetCount:int, missingInternalTargetCount:int, queryTargetCount:int, fragmentTargetCount:int, sameSourceReferenceCount:int, relationshipPartTargetCount:int, contentTypesItemTargetCount:int, reservedRelationshipDirectoryTargetCount:int, unsafeExternalTargetCount:int, relativeExternalTargetCount:int, rewriteRequiredExternalTargetCount:int, sourceCount:int, targetPartCount:int, sourcePartCounts:array<string, int>, relationshipTypeCounts:array<string, int>, targetResolutionKindCounts:array<string, int>, targetKeysByResolutionKind:array<string, list<string>>, targetNamesByResolutionKind:array<string, list<string>>, externalTargetKindCounts:array<string, int>, externalTargetSchemeCounts:array<string, int>, contentTypeCounts:array<string, int>, targetParts:list<string>, missingTargetParts:list<string>, externalTargets:list<string>, contentTypes:list<string>, contentTypeSourceCounts:array<string, int>, issueCounts:array<string, int>, issues:list<string>, targets:list<array{source:string, id:string, type:string, target:string, targetPart:?string, targetQuery:?string, targetFragment:?string, sameSourceReference:bool, targetResolutionKind:string, contentType:?string, contentTypeSource:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function relationshipTargetSummary(?string $relationshipType = null): array
    {
        $summary = [
            'relationshipType' => $relationshipType,
            'valid' => true,
            'relationshipCount' => 0,
            'validTargetCount' => 0,
            'invalidTargetCount' => 0,
            'internalTargetCount' => 0,
            'externalTargetCount' => 0,
            'existingInternalTargetCount' => 0,
            'missingInternalTargetCount' => 0,
            'queryTargetCount' => 0,
            'fragmentTargetCount' => 0,
            'sameSourceReferenceCount' => 0,
            'relationshipPartTargetCount' => 0,
            'contentTypesItemTargetCount' => 0,
            'reservedRelationshipDirectoryTargetCount' => 0,
            'unsafeExternalTargetCount' => 0,
            'relativeExternalTargetCount' => 0,
            'rewriteRequiredExternalTargetCount' => 0,
            'sourceCount' => 0,
            'targetPartCount' => 0,
            'sourcePartCounts' => [],
            'relationshipTypeCounts' => [],
            'targetResolutionKindCounts' => [],
            'targetKeysByResolutionKind' => [],
            'targetNamesByResolutionKind' => [],
            'externalTargetKindCounts' => [],
            'externalTargetSchemeCounts' => [],
            'contentTypeCounts' => [],
            'targetParts' => [],
            'missingTargetParts' => [],
            'externalTargets' => [],
            'contentTypes' => [],
            'contentTypeSourceCounts' => [],
            'issueCounts' => [],
            'issues' => [],
            'targets' => [],
        ];

        foreach ($this->preflightAllRelationshipTargets($relationshipType) as $target) {
            $summary['relationshipCount']++;
            $summary['sourcePartCounts'][$target['source']] = ($summary['sourcePartCounts'][$target['source']] ?? 0) + 1;
            $summary['relationshipTypeCounts'][$target['type']] = ($summary['relationshipTypeCounts'][$target['type']] ?? 0) + 1;
            if ($target['valid']) {
                $summary['validTargetCount']++;
            } else {
                $summary['invalidTargetCount']++;
                $summary['valid'] = false;
            }

            $targetPart = $target['targetPart'];
            $suffix = ['query' => null, 'fragment' => null];
            $sameSourceReference = false;
            if ($targetPart !== null) {
                $suffix = self::targetQueryAndFragment($target['target']);
                $sameSourceReference = self::partNameEquivalenceKey($targetPart)
                    === self::partNameEquivalenceKey($target['source']);
            }
            $targetResolutionKind = self::relationshipTargetResolutionKind(
                $target,
                $targetPart,
                $sameSourceReference,
            );
            $targetResolutionKey = $target['source'] . ':' . $target['id'];
            $targetResolutionName = $targetPart ?? $target['target'];
            $summary['targetResolutionKindCounts'][$targetResolutionKind]
                = ($summary['targetResolutionKindCounts'][$targetResolutionKind] ?? 0) + 1;
            $summary['targetKeysByResolutionKind'][$targetResolutionKind][] = $targetResolutionKey;
            $summary['targetNamesByResolutionKind'][$targetResolutionKind] ??= [];
            self::appendUniqueString(
                $summary['targetNamesByResolutionKind'][$targetResolutionKind],
                $targetResolutionName,
            );

            if ($target['external']) {
                $summary['externalTargetCount']++;
                $externalKind = $target['externalTargetKind'] ?? 'unknown';
                $summary['externalTargetKindCounts'][$externalKind] = ($summary['externalTargetKindCounts'][$externalKind] ?? 0) + 1;
                $externalScheme = $target['externalTargetScheme'] ?? 'none';
                $summary['externalTargetSchemeCounts'][$externalScheme] = ($summary['externalTargetSchemeCounts'][$externalScheme] ?? 0) + 1;
                self::appendUniqueString($summary['externalTargets'], $target['target']);
                if ($target['externalTargetAllowed'] === false) {
                    $summary['unsafeExternalTargetCount']++;
                }
                if (
                    $target['externalTargetKind'] === 'relative-reference'
                    || $target['externalTargetKind'] === 'fragment-reference'
                ) {
                    $summary['relativeExternalTargetCount']++;
                }
                if ($target['externalTargetRequiresBaseUri'] === true) {
                    $summary['rewriteRequiredExternalTargetCount']++;
                }
            } else {
                $summary['internalTargetCount']++;
                if ($target['exists'] === true) {
                    $summary['existingInternalTargetCount']++;
                } elseif ($target['exists'] === false) {
                    $summary['missingInternalTargetCount']++;
                }

                if ($targetPart !== null) {
                    self::appendUniqueString($summary['targetParts'], $targetPart);
                    if ($target['exists'] === false) {
                        self::appendUniqueString($summary['missingTargetParts'], $targetPart);
                    }
                }

                if ($suffix['query'] !== null) {
                    $summary['queryTargetCount']++;
                }
                if ($suffix['fragment'] !== null) {
                    $summary['fragmentTargetCount']++;
                }
                if ($sameSourceReference) {
                    $summary['sameSourceReferenceCount']++;
                }
                if ($target['relationshipPartTarget']) {
                    $summary['relationshipPartTargetCount']++;
                }
                if (in_array('targets-content-types-item', $target['issues'], true)) {
                    $summary['contentTypesItemTargetCount']++;
                }
                if (in_array('targets-reserved-relationship-directory-part', $target['issues'], true)) {
                    $summary['reservedRelationshipDirectoryTargetCount']++;
                }
            }

            if ($target['contentType'] !== null) {
                self::appendUniqueString($summary['contentTypes'], $target['contentType']);
                $summary['contentTypeCounts'][$target['contentType']] = ($summary['contentTypeCounts'][$target['contentType']] ?? 0) + 1;
            }
            if ($target['contentTypeSource'] !== null) {
                $summary['contentTypeSourceCounts'][$target['contentTypeSource']]
                    = ($summary['contentTypeSourceCounts'][$target['contentTypeSource']] ?? 0) + 1;
            }

            foreach ($target['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }

            $summary['targets'][] = [
                'source' => $target['source'],
                'id' => $target['id'],
                'type' => $target['type'],
                'target' => $target['target'],
                'targetPart' => $targetPart,
                'targetQuery' => $suffix['query'],
                'targetFragment' => $suffix['fragment'],
                'sameSourceReference' => $sameSourceReference,
                'targetResolutionKind' => $targetResolutionKind,
                'contentType' => $target['contentType'],
                'contentTypeSource' => $target['contentTypeSource'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
        }

        foreach ([
            'targetParts',
            'missingTargetParts',
            'externalTargets',
            'contentTypes',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }
        $summary['sourceCount'] = count($summary['sourcePartCounts']);
        $summary['targetPartCount'] = count($summary['targetParts']);
        ksort($summary['sourcePartCounts'], SORT_STRING);
        ksort($summary['relationshipTypeCounts'], SORT_STRING);
        ksort($summary['targetResolutionKindCounts'], SORT_STRING);
        ksort($summary['targetKeysByResolutionKind'], SORT_STRING);
        foreach ($summary['targetKeysByResolutionKind'] as &$targetKeys) {
            sort($targetKeys, SORT_STRING);
        }
        unset($targetKeys);
        ksort($summary['targetNamesByResolutionKind'], SORT_STRING);
        foreach ($summary['targetNamesByResolutionKind'] as &$targetNames) {
            sort($targetNames, SORT_STRING);
        }
        unset($targetNames);
        ksort($summary['externalTargetKindCounts'], SORT_STRING);
        ksort($summary['externalTargetSchemeCounts'], SORT_STRING);
        ksort($summary['contentTypeCounts'], SORT_STRING);
        ksort($summary['contentTypeSourceCounts'], SORT_STRING);
        ksort($summary['issueCounts'], SORT_STRING);
        usort(
            $summary['targets'],
            static fn (array $left, array $right): int => [$left['source'], $left['id']]
                <=> [$right['source'], $right['id']],
        );

        return $summary;
    }

    /**
     * @return array{source:?string, relationshipType:?string, valid:bool, relationshipCount:int, sourceCount:int, internalCount:int, externalCount:int, explicitTargetModeCount:int, implicitTargetModeCount:int, relationshipRecordTargetModeCounts:array<string, int>, relationshipRecordImplicitInternalTargetModeCount:int, relationshipRecordExplicitInternalTargetModeCount:int, relationshipRecordExplicitExternalTargetModeCount:int, relationshipRecordUnexpectedTargetModeCount:int, targetModeCounts:array<string, int>, targetModeDeclarationCounts:array<string, int>, sources:list<string>, sourcesWithExplicitInternalTargetMode:list<string>, relationshipPartsWithExplicitInternalTargetMode:list<string>, relationshipsWithExplicitInternalTargetMode:list<array{source:string, relationshipPartName:string, id:string, type:string, target:string, targetMode:string, targetModeExplicit:bool, targetModeDeclaration:string, external:bool}>, relationships:list<array{source:string, relationshipPartName:string, id:string, type:string, target:string, targetMode:string, targetModeExplicit:bool, targetModeDeclaration:string, external:bool}>}
     */
    public function relationshipTargetModeSummary(?string $sourcePartName = null, ?string $relationshipType = null): array
    {
        $summary = [
            'source' => $sourcePartName === null ? null : $this->relationshipSourceNameForEquivalent($sourcePartName),
            'relationshipType' => $relationshipType,
            'valid' => true,
            'relationshipCount' => 0,
            'sourceCount' => 0,
            'internalCount' => 0,
            'externalCount' => 0,
            'explicitTargetModeCount' => 0,
            'implicitTargetModeCount' => 0,
            'relationshipRecordTargetModeCounts' => [],
            'relationshipRecordImplicitInternalTargetModeCount' => 0,
            'relationshipRecordExplicitInternalTargetModeCount' => 0,
            'relationshipRecordExplicitExternalTargetModeCount' => 0,
            'relationshipRecordUnexpectedTargetModeCount' => 0,
            'targetModeCounts' => [],
            'targetModeDeclarationCounts' => [],
            'sources' => [],
            'sourcesWithExplicitInternalTargetMode' => [],
            'relationshipPartsWithExplicitInternalTargetMode' => [],
            'relationshipsWithExplicitInternalTargetMode' => [],
            'relationships' => [],
        ];

        $sourcePartNames = $sourcePartName === null
            ? $this->sourcePartNames()
            : [$summary['source']];

        foreach ($sourcePartNames as $source) {
            if (!is_string($source)) {
                continue;
            }

            $relationships = $this->relationshipsForSource($source);
            if (!$relationships instanceof OpcRelationships) {
                continue;
            }

            $items = $relationshipType === null
                ? $relationships->all()
                : $relationships->ofType($relationshipType);
            if ($items === []) {
                continue;
            }

            self::appendUniqueString($summary['sources'], $source);
            $relationshipPartName = OpcRelationships::relationshipPartNameForSource($source);
            foreach ($items as $relationship) {
                $summary['relationshipCount']++;
                $targetMode = $relationship->targetMode;
                $targetModeDeclaration = $relationship->targetModeExplicit ? 'explicit' : 'implicit';
                $recordTargetMode = $relationship->targetModeExplicit ? $targetMode : '(implicit-internal)';

                $summary['targetModeCounts'][$targetMode] = ($summary['targetModeCounts'][$targetMode] ?? 0) + 1;
                $summary['targetModeDeclarationCounts'][$targetModeDeclaration] = ($summary['targetModeDeclarationCounts'][$targetModeDeclaration] ?? 0) + 1;
                $summary['relationshipRecordTargetModeCounts'][$recordTargetMode] = ($summary['relationshipRecordTargetModeCounts'][$recordTargetMode] ?? 0) + 1;

                if ($relationship->targetModeExplicit) {
                    $summary['explicitTargetModeCount']++;
                } else {
                    $summary['implicitTargetModeCount']++;
                }

                if ($relationship->isExternal()) {
                    $summary['externalCount']++;
                    if ($relationship->targetModeExplicit) {
                        $summary['relationshipRecordExplicitExternalTargetModeCount']++;
                    } else {
                        $summary['relationshipRecordUnexpectedTargetModeCount']++;
                        $summary['valid'] = false;
                    }
                } else {
                    $summary['internalCount']++;
                    if ($relationship->targetModeExplicit) {
                        $summary['relationshipRecordExplicitInternalTargetModeCount']++;
                    } else {
                        $summary['relationshipRecordImplicitInternalTargetModeCount']++;
                    }
                }

                $row = [
                    'source' => $source,
                    'relationshipPartName' => $relationshipPartName,
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'target' => $relationship->target,
                    'targetMode' => $targetMode,
                    'targetModeExplicit' => $relationship->targetModeExplicit,
                    'targetModeDeclaration' => $targetModeDeclaration,
                    'external' => $relationship->isExternal(),
                ];
                $summary['relationships'][] = $row;

                if ($relationship->isExternal() || !$relationship->targetModeExplicit) {
                    continue;
                }

                self::appendUniqueString($summary['sourcesWithExplicitInternalTargetMode'], $source);
                self::appendUniqueString($summary['relationshipPartsWithExplicitInternalTargetMode'], $relationshipPartName);
                $summary['relationshipsWithExplicitInternalTargetMode'][] = $row;
            }
        }

        $summary['sourceCount'] = count($summary['sources']);
        foreach ([
            'sources',
            'sourcesWithExplicitInternalTargetMode',
            'relationshipPartsWithExplicitInternalTargetMode',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }
        ksort($summary['relationshipRecordTargetModeCounts'], SORT_STRING);
        ksort($summary['targetModeCounts'], SORT_STRING);
        ksort($summary['targetModeDeclarationCounts'], SORT_STRING);
        usort(
            $summary['relationships'],
            static fn (array $left, array $right): int => [$left['source'], $left['id']]
                <=> [$right['source'], $right['id']],
        );
        usort(
            $summary['relationshipsWithExplicitInternalTargetMode'],
            static fn (array $left, array $right): int => [$left['source'], $left['id']]
                <=> [$right['source'], $right['id']],
        );

        return $summary;
    }

    /**
     * @return list<array{source:string, sourceExists:bool, sourceContentType:?string, relationshipPartName:string, relationshipPartExists:bool, relationshipPartContentType:?string, relationshipPartLoaded:bool, relationshipPartLoadAction:?string, relationshipPartLoadReason:?string, relationshipPartIssues:list<string>, relationshipCount:int, internalCount:int, externalCount:int, validTargetCount:int, invalidTargetCount:int, relationshipTypes:list<string>, targetParts:list<string>, contentTypes:list<string>, externalTargets:list<string>, missingTargetParts:list<string>, issues:list<string>, valid:bool}>
     */
    public function relationshipSourceInventory(): array
    {
        $relationshipPartsBySource = [];
        foreach ($this->preflightPackageParts() as $part) {
            if (!$part['relationshipPart'] || $part['relationshipSource'] === null) {
                continue;
            }

            $relationshipPartsBySource[$part['relationshipSource']] = $part;
        }

        $inventory = [];
        foreach ($this->sourcePartNames() as $sourcePartName) {
            $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
            $relationshipPart = $relationshipPartsBySource[$sourcePartName] ?? null;
            $targets = $this->preflightTargetsForSource($sourcePartName);

            $sourceExists = $sourcePartName === '/' || $this->packagePartNameForEquivalent($sourcePartName) !== null;
            $sourceContentType = $sourcePartName === '/' ? null : $this->contentTypes->contentTypeForPart($sourcePartName);
            $relationshipPartIssues = $relationshipPart['issues'] ?? [];
            $relationshipPartLoaded = ($relationshipPart['relationshipSourceLoaded'] ?? null) === true;

            $entry = [
                'source' => $sourcePartName,
                'sourceExists' => $sourceExists,
                'sourceContentType' => $sourceContentType,
                'relationshipPartName' => $relationshipPart['partName'] ?? OpcRelationships::relationshipPartNameForSource($sourcePartName),
                'relationshipPartExists' => $relationshipPart !== null,
                'relationshipPartContentType' => $relationshipPart['contentType'] ?? null,
                'relationshipPartLoaded' => $relationshipPartLoaded,
                'relationshipPartLoadAction' => $relationshipPart['relationshipPartLoadAction'] ?? null,
                'relationshipPartLoadReason' => $relationshipPart['relationshipPartLoadReason'] ?? null,
                'relationshipPartIssues' => $relationshipPartIssues,
                'relationshipCount' => count($targets),
                'internalCount' => 0,
                'externalCount' => 0,
                'validTargetCount' => 0,
                'invalidTargetCount' => 0,
                'relationshipTypes' => [],
                'targetParts' => [],
                'contentTypes' => [],
                'externalTargets' => [],
                'missingTargetParts' => [],
                'issues' => $relationshipPartIssues,
                'valid' => $sourceExists && $relationshipPartLoaded && $relationshipPartIssues === [],
            ];

            foreach ($targets as $target) {
                self::appendUniqueString($entry['relationshipTypes'], $target['type']);

                if ($target['external']) {
                    $entry['externalCount']++;
                    self::appendUniqueString($entry['externalTargets'], $target['target']);
                } else {
                    $entry['internalCount']++;
                    $targetPart = self::targetPartFromPreflightTarget($target);
                    if ($targetPart !== null) {
                        self::appendUniqueString($entry['targetParts'], $targetPart);
                        if ($target['exists'] === false) {
                            self::appendUniqueString($entry['missingTargetParts'], $targetPart);
                        }
                    }
                }

                if ($target['contentType'] !== null) {
                    self::appendUniqueString($entry['contentTypes'], $target['contentType']);
                }

                if ($target['valid']) {
                    $entry['validTargetCount']++;
                } else {
                    $entry['invalidTargetCount']++;
                    $entry['valid'] = false;
                }

                foreach ($target['issues'] as $issue) {
                    self::appendUniqueString($entry['issues'], $issue);
                }
            }

            foreach ([
                'relationshipPartIssues',
                'relationshipTypes',
                'targetParts',
                'contentTypes',
                'externalTargets',
                'missingTargetParts',
                'issues',
            ] as $listKey) {
                sort($entry[$listKey], SORT_STRING);
            }

            $inventory[] = $entry;
        }

        usort(
            $inventory,
            static fn (array $left, array $right): int => $left['source'] <=> $right['source'],
        );

        return $inventory;
    }

    /**
     * @return list<array{type:string, relationshipCount:int, sourceCount:int, sources:list<string>, idsBySource:array<string, list<string>>, internalCount:int, externalCount:int, validCount:int, invalidCount:int, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, targetParts:list<string>, contentTypes:list<string>, knownRole:?string, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>, issues:list<string>}>
     */
    public function relationshipTypeInventory(?string $sourcePartName = null): array
    {
        $sourcePartNames = $sourcePartName === null
            ? $this->sourcePartNames()
            : [$this->relationshipSourceNameForEquivalent($sourcePartName)];

        $byType = [];
        foreach ($sourcePartNames as $source) {
            foreach ($this->preflightTargetsForSource($source) as $target) {
                $type = $target['type'];
                if (!isset($byType[$type])) {
                    $byType[$type] = [
                        'type' => $type,
                        'relationshipCount' => 0,
                        'sourceCount' => 0,
                        'sources' => [],
                        'idsBySource' => [],
                        'internalCount' => 0,
                        'externalCount' => 0,
                        'validCount' => 0,
                        'invalidCount' => 0,
                        'relationshipTypeValid' => true,
                        'relationshipTypeIssues' => [],
                        'targetParts' => [],
                        'contentTypes' => [],
                        'knownRole' => null,
                        'sourceScope' => 'any-source',
                        'singletonScope' => null,
                        'policyValid' => true,
                        'policyIssues' => [],
                        'issues' => [],
                    ];
                }

                if (!isset($byType[$type]['idsBySource'][$source])) {
                    $byType[$type]['idsBySource'][$source] = [];
                    $byType[$type]['sources'][] = $source;
                    $byType[$type]['sourceCount']++;
                }

                $byType[$type]['relationshipCount']++;
                $byType[$type]['idsBySource'][$source][] = $target['id'];
                if ($target['external']) {
                    $byType[$type]['externalCount']++;
                } else {
                    $byType[$type]['internalCount']++;
                }

                if ($target['valid']) {
                    $byType[$type]['validCount']++;
                } else {
                    $byType[$type]['invalidCount']++;
                }

                if (!$target['relationshipTypeValid']) {
                    $byType[$type]['relationshipTypeValid'] = false;
                }

                foreach ($target['relationshipTypeIssues'] as $issue) {
                    self::appendUniqueString($byType[$type]['relationshipTypeIssues'], $issue);
                }

                $targetPart = self::targetPartFromPreflightTarget($target);
                if ($targetPart !== null) {
                    self::appendUniqueString($byType[$type]['targetParts'], $targetPart);
                }

                if ($target['contentType'] !== null) {
                    self::appendUniqueString($byType[$type]['contentTypes'], $target['contentType']);
                }

                foreach ($target['issues'] as $issue) {
                    self::appendUniqueString($byType[$type]['issues'], $issue);
                }
            }
        }

        ksort($byType, SORT_STRING);
        foreach ($byType as &$entry) {
            sort($entry['sources'], SORT_STRING);
            ksort($entry['idsBySource'], SORT_STRING);
            sort($entry['targetParts'], SORT_STRING);
            sort($entry['contentTypes'], SORT_STRING);
            sort($entry['relationshipTypeIssues'], SORT_STRING);
            sort($entry['issues'], SORT_STRING);
            $policy = self::relationshipTypePolicyForInventoryEntry($entry);
            $entry['knownRole'] = $policy['knownRole'];
            $entry['sourceScope'] = $policy['sourceScope'];
            $entry['singletonScope'] = $policy['singletonScope'];
            $entry['policyValid'] = $policy['policyValid'];
            $entry['policyIssues'] = $policy['policyIssues'];
        }
        unset($entry);

        return array_values($byType);
    }

    /**
     * @return array{source:?string, valid:bool, knownRoleCount:int, relationshipCount:int, validPolicyCount:int, invalidPolicyCount:int, packageScopedCount:int, sourceScopedCount:int, unscopedCount:int, packageSingletonCount:int, sourceSingletonCount:int, issueCounts:array<string, int>, issues:list<string>, roles:list<array{role:string, type:string, relationshipCount:int, sourceCount:int, sources:list<string>, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>, targetParts:list<string>, contentTypes:list<string>}>}
     */
    public function relationshipRolePolicySummary(?string $sourcePartName = null): array
    {
        $sourceFilter = $sourcePartName === null
            ? null
            : $this->relationshipSourceNameForEquivalent($sourcePartName);
        $summary = [
            'source' => $sourceFilter,
            'valid' => true,
            'knownRoleCount' => 0,
            'relationshipCount' => 0,
            'validPolicyCount' => 0,
            'invalidPolicyCount' => 0,
            'packageScopedCount' => 0,
            'sourceScopedCount' => 0,
            'unscopedCount' => 0,
            'packageSingletonCount' => 0,
            'sourceSingletonCount' => 0,
            'issueCounts' => [],
            'issues' => [],
            'roles' => [],
        ];

        foreach ($this->relationshipTypeInventory($sourceFilter) as $entry) {
            if ($entry['knownRole'] === null) {
                continue;
            }

            $role = [
                'role' => $entry['knownRole'],
                'type' => $entry['type'],
                'relationshipCount' => $entry['relationshipCount'],
                'sourceCount' => $entry['sourceCount'],
                'sources' => $entry['sources'],
                'sourceScope' => $entry['sourceScope'],
                'singletonScope' => $entry['singletonScope'],
                'policyValid' => $entry['policyValid'],
                'policyIssues' => $entry['policyIssues'],
                'targetParts' => $entry['targetParts'],
                'contentTypes' => $entry['contentTypes'],
            ];

            $summary['roles'][] = $role;
            $summary['knownRoleCount']++;
            $summary['relationshipCount'] += $entry['relationshipCount'];

            if ($entry['policyValid']) {
                $summary['validPolicyCount']++;
            } else {
                $summary['invalidPolicyCount']++;
                $summary['valid'] = false;
            }

            if ($entry['sourceScope'] === 'package-root') {
                $summary['packageScopedCount']++;
            } elseif ($entry['singletonScope'] === 'source') {
                $summary['sourceScopedCount']++;
            } elseif ($entry['singletonScope'] === null) {
                $summary['unscopedCount']++;
            }

            if ($entry['singletonScope'] === 'package') {
                $summary['packageSingletonCount']++;
            } elseif ($entry['singletonScope'] === 'source') {
                $summary['sourceSingletonCount']++;
            }

            foreach ($entry['policyIssues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }
        }

        usort(
            $summary['roles'],
            static function (array $left, array $right): int {
                $roleComparison = $left['role'] <=> $right['role'];

                return $roleComparison !== 0 ? $roleComparison : $left['type'] <=> $right['type'];
            },
        );
        ksort($summary['issueCounts'], SORT_STRING);
        sort($summary['issues'], SORT_STRING);

        return $summary;
    }

    /**
     * @return list<array{contentType:string, packagePartCount:int, overrideCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipSourceCount:int, relationshipTargetReferenceCount:int, relationshipTargetPartCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, parts:list<string>, overrideParts:list<string>, defaultParts:list<string>, relationshipParts:list<string>, relationshipSources:list<string>, relationshipTargetParts:list<string>, reachableTargetParts:list<string>, missingOverrideParts:list<string>, relationshipTargetReferences:list<array{source:string, id:string, targetPart:string, valid:bool, issues:list<string>}>, issues:list<string>}>
     */
    public function contentTypeInventory(): array
    {
        $inventory = [];
        $overridePartNamesByEquivalenceKey = [];
        foreach ($this->contentTypes->overrides() as $partName => $contentType) {
            $overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] = $partName;
            $entry =& self::contentTypeInventoryEntry($inventory, $contentType);
            self::appendUniqueString($entry['overrideParts'], $partName);
            unset($entry);
        }

        foreach ($this->preflightPackageParts() as $part) {
            $contentType = $part['contentType'];
            if ($contentType === null) {
                continue;
            }

            $partName = $part['partName'];
            $entry =& self::contentTypeInventoryEntry($inventory, $contentType);
            self::appendUniqueString($entry['parts'], $partName);

            if (isset($overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)])) {
                self::appendUniqueString($entry['overrideParts'], $partName);
            } else {
                self::appendUniqueString($entry['defaultParts'], $partName);
            }

            if ($part['relationshipPart']) {
                self::appendUniqueString($entry['relationshipParts'], $partName);
                if ($part['relationshipSource'] !== null) {
                    self::appendUniqueString($entry['relationshipSources'], $part['relationshipSource']);
                }
            }

            if (!$part['valid']) {
                $entry['invalidPackagePartCount']++;
                foreach ($part['issues'] as $issue) {
                    self::appendUniqueString($entry['issues'], $issue);
                }
            }
            unset($entry);
        }

        foreach ($this->preflightContentTypeOverrides() as $override) {
            $entry =& self::contentTypeInventoryEntry($inventory, $override['contentType']);
            self::appendUniqueString($entry['overrideParts'], $override['partName']);
            if (!$override['exists']) {
                self::appendUniqueString($entry['missingOverrideParts'], $override['partName']);
            }

            foreach ($override['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        foreach ($this->preflightAllRelationshipTargets() as $target) {
            if ($target['external'] || $target['targetPart'] === null || $target['contentType'] === null) {
                continue;
            }

            $entry =& self::contentTypeInventoryEntry($inventory, $target['contentType']);
            self::appendUniqueString($entry['relationshipTargetParts'], $target['targetPart']);
            $entry['relationshipTargetReferences'][] = [
                'source' => $target['source'],
                'id' => $target['id'],
                'targetPart' => $target['targetPart'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        foreach ($this->reachableTargetsForSource('/') as $target) {
            if ($target['external'] || $target['targetPart'] === null || $target['contentType'] === null) {
                continue;
            }

            $entry =& self::contentTypeInventoryEntry($inventory, $target['contentType']);
            self::appendUniqueString($entry['reachableTargetParts'], $target['targetPart']);
            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        ksort($inventory, SORT_STRING);
        foreach ($inventory as &$entry) {
            sort($entry['parts'], SORT_STRING);
            sort($entry['overrideParts'], SORT_STRING);
            sort($entry['defaultParts'], SORT_STRING);
            sort($entry['relationshipParts'], SORT_STRING);
            sort($entry['relationshipSources'], SORT_STRING);
            sort($entry['relationshipTargetParts'], SORT_STRING);
            sort($entry['reachableTargetParts'], SORT_STRING);
            sort($entry['missingOverrideParts'], SORT_STRING);
            sort($entry['issues'], SORT_STRING);
            usort(
                $entry['relationshipTargetReferences'],
                static fn (array $left, array $right): int => [
                    $left['source'],
                    $left['id'],
                    $left['targetPart'],
                ] <=> [
                    $right['source'],
                    $right['id'],
                    $right['targetPart'],
                ]
            );

            $entry['packagePartCount'] = count($entry['parts']);
            $entry['overrideCount'] = count($entry['overrideParts']);
            $entry['defaultPartCount'] = count($entry['defaultParts']);
            $entry['relationshipPartCount'] = count($entry['relationshipParts']);
            $entry['relationshipSourceCount'] = count($entry['relationshipSources']);
            $entry['relationshipTargetReferenceCount'] = count($entry['relationshipTargetReferences']);
            $entry['relationshipTargetPartCount'] = count($entry['relationshipTargetParts']);
            $entry['reachableTargetCount'] = count($entry['reachableTargetParts']);
            $entry['missingOverrideCount'] = count($entry['missingOverrideParts']);
        }
        unset($entry);

        return array_values($inventory);
    }

    /**
     * @return array{valid:bool, contentTypeCount:int, packagePartCount:int, overridePartCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipSourceCount:int, relationshipTargetReferenceCount:int, relationshipTargetPartCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, invalidContentTypeCount:int, missingOverrideContentTypeCount:int, relationshipPartContentTypeCount:int, mediaContentTypeCount:int, embeddedPackageContentTypeCount:int, contentTypeNames:list<string>, invalidContentTypes:list<string>, missingOverrideContentTypes:list<string>, relationshipPartContentTypes:list<string>, mediaContentTypes:list<string>, embeddedPackageContentTypes:list<string>, contentTypesByIssue:array<string,list<string>>, issueCounts:array<string,int>, issues:list<string>, contentTypes:list<array{contentType:string, packagePartCount:int, overrideCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipTargetReferenceCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, valid:bool, issues:list<string>}>}
     */
    public function contentTypeInventorySummary(): array
    {
        $summary = [
            'valid' => true,
            'contentTypeCount' => 0,
            'packagePartCount' => 0,
            'overridePartCount' => 0,
            'defaultPartCount' => 0,
            'relationshipPartCount' => 0,
            'relationshipSourceCount' => 0,
            'relationshipTargetReferenceCount' => 0,
            'relationshipTargetPartCount' => 0,
            'reachableTargetCount' => 0,
            'missingOverrideCount' => 0,
            'invalidPackagePartCount' => 0,
            'invalidContentTypeCount' => 0,
            'missingOverrideContentTypeCount' => 0,
            'relationshipPartContentTypeCount' => 0,
            'mediaContentTypeCount' => 0,
            'embeddedPackageContentTypeCount' => 0,
            'contentTypeNames' => [],
            'invalidContentTypes' => [],
            'missingOverrideContentTypes' => [],
            'relationshipPartContentTypes' => [],
            'mediaContentTypes' => [],
            'embeddedPackageContentTypes' => [],
            'contentTypesByIssue' => [],
            'issueCounts' => [],
            'issues' => [],
            'contentTypes' => [],
        ];

        foreach ($this->contentTypeInventory() as $entry) {
            $contentType = $entry['contentType'];
            $hasMediaPart = false;
            $hasEmbeddedPackagePart = false;

            $summary['contentTypeCount']++;
            $summary['packagePartCount'] += $entry['packagePartCount'];
            $summary['overridePartCount'] += $entry['overrideCount'];
            $summary['defaultPartCount'] += $entry['defaultPartCount'];
            $summary['relationshipPartCount'] += $entry['relationshipPartCount'];
            $summary['relationshipSourceCount'] += $entry['relationshipSourceCount'];
            $summary['relationshipTargetReferenceCount'] += $entry['relationshipTargetReferenceCount'];
            $summary['relationshipTargetPartCount'] += $entry['relationshipTargetPartCount'];
            $summary['reachableTargetCount'] += $entry['reachableTargetCount'];
            $summary['missingOverrideCount'] += $entry['missingOverrideCount'];
            $summary['invalidPackagePartCount'] += $entry['invalidPackagePartCount'];
            $summary['contentTypeNames'][] = $contentType;

            foreach ($entry['parts'] as $partName) {
                $hasMediaPart = $hasMediaPart || self::isMediaPartCandidate($partName, $contentType);
                $hasEmbeddedPackagePart = $hasEmbeddedPackagePart || self::isEmbeddedPackageCandidate($partName, $contentType);
            }

            if (
                $entry['relationshipPartCount'] > 0
                || self::contentTypeMatches($contentType, self::RELATIONSHIP_PART_CONTENT_TYPE)
            ) {
                $summary['relationshipPartContentTypeCount']++;
                $summary['relationshipPartContentTypes'][] = $contentType;
            }

            if ($hasMediaPart) {
                $summary['mediaContentTypeCount']++;
                $summary['mediaContentTypes'][] = $contentType;
            }

            if ($hasEmbeddedPackagePart) {
                $summary['embeddedPackageContentTypeCount']++;
                $summary['embeddedPackageContentTypes'][] = $contentType;
            }

            if ($entry['missingOverrideCount'] > 0) {
                $summary['missingOverrideContentTypeCount']++;
                $summary['missingOverrideContentTypes'][] = $contentType;
            }

            if ($entry['issues'] !== []) {
                $summary['valid'] = false;
                $summary['invalidContentTypeCount']++;
                $summary['invalidContentTypes'][] = $contentType;
            }

            foreach ($entry['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
                if (!isset($summary['contentTypesByIssue'][$issue])) {
                    $summary['contentTypesByIssue'][$issue] = [];
                }
                self::appendUniqueString($summary['contentTypesByIssue'][$issue], $contentType);
            }

            $summary['contentTypes'][] = [
                'contentType' => $contentType,
                'packagePartCount' => $entry['packagePartCount'],
                'overrideCount' => $entry['overrideCount'],
                'defaultPartCount' => $entry['defaultPartCount'],
                'relationshipPartCount' => $entry['relationshipPartCount'],
                'relationshipTargetReferenceCount' => $entry['relationshipTargetReferenceCount'],
                'reachableTargetCount' => $entry['reachableTargetCount'],
                'missingOverrideCount' => $entry['missingOverrideCount'],
                'invalidPackagePartCount' => $entry['invalidPackagePartCount'],
                'valid' => $entry['issues'] === [],
                'issues' => $entry['issues'],
            ];
        }

        foreach ([
            'contentTypeNames',
            'invalidContentTypes',
            'missingOverrideContentTypes',
            'relationshipPartContentTypes',
            'mediaContentTypes',
            'embeddedPackageContentTypes',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }

        ksort($summary['issueCounts'], SORT_STRING);
        ksort($summary['contentTypesByIssue'], SORT_STRING);
        foreach ($summary['contentTypesByIssue'] as &$contentTypes) {
            sort($contentTypes, SORT_STRING);
        }
        unset($contentTypes);

        usort(
            $summary['contentTypes'],
            static fn (array $left, array $right): int => $left['contentType'] <=> $right['contentType'],
        );

        return $summary;
    }

    /**
     * @return array{valid:bool, defaultCount:int, usedDefaultCount:int, unusedDefaultCount:int, packagePartCount:int, defaultResolvedPartCount:int, overrideResolvedPartCount:int, missingContentTypePartCount:int, relationshipPartDefaultResolvedCount:int, mediaDefaultResolvedCount:int, embeddedPackageDefaultResolvedCount:int, extensionlessMissingPartCount:int, defaultExtensions:list<string>, unusedDefaultExtensions:list<string>, missingExtensions:list<string>, issueCounts:array<string,int>, issues:list<string>, defaults:list<array{extension:string, normalizedExtension:string, contentType:string, packagePartCount:int, relationshipPartCount:int, mediaPartCount:int, embeddedPackageCandidateCount:int, packageParts:list<string>, valid:bool, issues:list<string>}>, missingParts:list<array{partName:string, extension:?string, relationshipPart:bool, issues:list<string>}>}
     */
    public function contentTypeDefaultUsageSummary(): array
    {
        $defaults = [];
        foreach ($this->contentTypes->defaults() as $extension => $contentType) {
            $normalizedExtension = strtolower($extension);
            $defaults[$normalizedExtension] = [
                'extension' => $extension,
                'normalizedExtension' => $normalizedExtension,
                'contentType' => $contentType,
                'packagePartCount' => 0,
                'relationshipPartCount' => 0,
                'mediaPartCount' => 0,
                'embeddedPackageCandidateCount' => 0,
                'packageParts' => [],
                'valid' => true,
                'issues' => [],
            ];
        }

        $summary = [
            'valid' => true,
            'defaultCount' => count($defaults),
            'usedDefaultCount' => 0,
            'unusedDefaultCount' => 0,
            'packagePartCount' => 0,
            'defaultResolvedPartCount' => 0,
            'overrideResolvedPartCount' => 0,
            'missingContentTypePartCount' => 0,
            'relationshipPartDefaultResolvedCount' => 0,
            'mediaDefaultResolvedCount' => 0,
            'embeddedPackageDefaultResolvedCount' => 0,
            'extensionlessMissingPartCount' => 0,
            'defaultExtensions' => [],
            'unusedDefaultExtensions' => [],
            'missingExtensions' => [],
            'issueCounts' => [],
            'issues' => [],
            'defaults' => [],
            'missingParts' => [],
        ];

        foreach ($defaults as $default) {
            $summary['defaultExtensions'][] = $default['extension'];
        }

        foreach ($this->preflightPackageParts() as $part) {
            $summary['packagePartCount']++;
            $partName = $part['partName'];

            if ($part['contentTypeSource'] === 'override') {
                $summary['overrideResolvedPartCount']++;
                continue;
            }

            if ($part['contentTypeSource'] === 'default') {
                $summary['defaultResolvedPartCount']++;
                $defaultExtension = strtolower((string) $part['contentTypeDefaultExtension']);
                if (isset($defaults[$defaultExtension])) {
                    $defaults[$defaultExtension]['packagePartCount']++;
                    $defaults[$defaultExtension]['packageParts'][] = $partName;
                    if ($part['relationshipPart']) {
                        $defaults[$defaultExtension]['relationshipPartCount']++;
                        $summary['relationshipPartDefaultResolvedCount']++;
                    }
                    if (self::isMediaPartCandidate($partName, $part['contentType'])) {
                        $defaults[$defaultExtension]['mediaPartCount']++;
                        $summary['mediaDefaultResolvedCount']++;
                    }
                    if (self::isEmbeddedPackageCandidate($partName, $part['contentType'])) {
                        $defaults[$defaultExtension]['embeddedPackageCandidateCount']++;
                        $summary['embeddedPackageDefaultResolvedCount']++;
                    }
                }
                continue;
            }

            $extension = self::partNameExtension($partName);
            $issues = ['missing-content-type'];
            if ($extension === '') {
                $summary['extensionlessMissingPartCount']++;
                $issues[] = 'missing-content-type-extension';
            } else {
                self::appendUniqueString($summary['missingExtensions'], $extension);
                $issues[] = 'missing-content-type-default';
            }

            $summary['missingContentTypePartCount']++;
            $summary['valid'] = false;
            foreach ($issues as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }
            $summary['missingParts'][] = [
                'partName' => $partName,
                'extension' => $extension === '' ? null : $extension,
                'relationshipPart' => $part['relationshipPart'],
                'issues' => $issues,
            ];
        }

        foreach ($defaults as &$default) {
            sort($default['packageParts'], SORT_STRING);
            if ($default['packagePartCount'] > 0) {
                $summary['usedDefaultCount']++;
            } else {
                $summary['unusedDefaultCount']++;
                $summary['unusedDefaultExtensions'][] = $default['extension'];
            }
        }
        unset($default);

        sort($summary['unusedDefaultExtensions'], SORT_STRING);
        sort($summary['missingExtensions'], SORT_STRING);
        sort($summary['issues'], SORT_STRING);
        ksort($summary['issueCounts'], SORT_STRING);
        usort(
            $summary['missingParts'],
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );

        $summary['defaults'] = array_values($defaults);

        return $summary;
    }

    /**
     * @return array{valid:bool, overrideCount:int, usedOverrideCount:int, exactMatchCount:int, equivalentMatchCount:int, missingPartCount:int, invalidOverrideCount:int, relationshipPartOverrideCount:int, relationshipContentTypeOverrideCount:int, nonRelationshipPartRelationshipContentTypeCount:int, contentTypesItemOverrideCount:int, reservedRelationshipDirectoryOverrideCount:int, contentTypeCounts:array<string,int>, issueCounts:array<string,int>, issues:list<string>, exactMatchParts:list<string>, equivalentMatchParts:list<string>, missingParts:list<string>, invalidParts:list<string>, relationshipPartOverrides:list<string>, relationshipContentTypeOverrideParts:list<string>, nonRelationshipPartRelationshipContentTypeParts:list<string>, contentTypesItemOverrides:list<string>, reservedRelationshipDirectoryOverrides:list<string>, overrides:list<array{partName:string, contentType:string, packagePartName:?string, matchKind:string, relationshipPart:bool, relationshipSource:?string, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>}
     */
    public function contentTypeOverrideUsageSummary(): array
    {
        $summary = [
            'valid' => true,
            'overrideCount' => 0,
            'usedOverrideCount' => 0,
            'exactMatchCount' => 0,
            'equivalentMatchCount' => 0,
            'missingPartCount' => 0,
            'invalidOverrideCount' => 0,
            'relationshipPartOverrideCount' => 0,
            'relationshipContentTypeOverrideCount' => 0,
            'nonRelationshipPartRelationshipContentTypeCount' => 0,
            'contentTypesItemOverrideCount' => 0,
            'reservedRelationshipDirectoryOverrideCount' => 0,
            'contentTypeCounts' => [],
            'issueCounts' => [],
            'issues' => [],
            'exactMatchParts' => [],
            'equivalentMatchParts' => [],
            'missingParts' => [],
            'invalidParts' => [],
            'relationshipPartOverrides' => [],
            'relationshipContentTypeOverrideParts' => [],
            'nonRelationshipPartRelationshipContentTypeParts' => [],
            'contentTypesItemOverrides' => [],
            'reservedRelationshipDirectoryOverrides' => [],
            'overrides' => [],
        ];

        foreach ($this->preflightContentTypeOverrides() as $override) {
            $summary['overrideCount']++;
            $summary['contentTypeCounts'][$override['contentType']] = ($summary['contentTypeCounts'][$override['contentType']] ?? 0) + 1;

            if ($override['partNameExactMatch']) {
                $matchKind = 'exact';
                $summary['usedOverrideCount']++;
                $summary['exactMatchCount']++;
                self::appendUniqueString($summary['exactMatchParts'], $override['partName']);
            } elseif ($override['partNameEquivalentMatch']) {
                $matchKind = 'equivalent';
                $summary['usedOverrideCount']++;
                $summary['equivalentMatchCount']++;
                self::appendUniqueString($summary['equivalentMatchParts'], $override['partName']);
            } else {
                $matchKind = 'missing';
                $summary['missingPartCount']++;
                self::appendUniqueString($summary['missingParts'], $override['partName']);
            }

            if ($override['relationshipPart']) {
                $summary['relationshipPartOverrideCount']++;
                self::appendUniqueString($summary['relationshipPartOverrides'], $override['partName']);
            }

            if (self::contentTypeMatches($override['contentType'], self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                $summary['relationshipContentTypeOverrideCount']++;
                self::appendUniqueString($summary['relationshipContentTypeOverrideParts'], $override['partName']);
                if (!$override['relationshipPart']) {
                    $summary['nonRelationshipPartRelationshipContentTypeCount']++;
                    self::appendUniqueString($summary['nonRelationshipPartRelationshipContentTypeParts'], $override['partName']);
                }
            }

            if (in_array('content-types-override-target', $override['issues'], true)) {
                $summary['contentTypesItemOverrideCount']++;
                self::appendUniqueString($summary['contentTypesItemOverrides'], $override['partName']);
            }

            if (in_array('reserved-relationship-directory-override', $override['issues'], true)) {
                $summary['reservedRelationshipDirectoryOverrideCount']++;
                self::appendUniqueString($summary['reservedRelationshipDirectoryOverrides'], $override['partName']);
            }

            if (!$override['valid']) {
                $summary['valid'] = false;
                $summary['invalidOverrideCount']++;
                self::appendUniqueString($summary['invalidParts'], $override['partName']);
            }

            foreach ($override['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }

            $summary['overrides'][] = [
                'partName' => $override['partName'],
                'contentType' => $override['contentType'],
                'packagePartName' => $override['packagePartName'],
                'matchKind' => $matchKind,
                'relationshipPart' => $override['relationshipPart'],
                'relationshipSource' => $override['relationshipSource'],
                'relationshipSourceLoaded' => $override['relationshipSourceLoaded'],
                'sourceExists' => $override['sourceExists'],
                'valid' => $override['valid'],
                'issues' => $override['issues'],
            ];
        }

        foreach ([
            'exactMatchParts',
            'equivalentMatchParts',
            'missingParts',
            'invalidParts',
            'relationshipPartOverrides',
            'relationshipContentTypeOverrideParts',
            'nonRelationshipPartRelationshipContentTypeParts',
            'contentTypesItemOverrides',
            'reservedRelationshipDirectoryOverrides',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }

        ksort($summary['contentTypeCounts'], SORT_STRING);
        ksort($summary['issueCounts'], SORT_STRING);
        usort(
            $summary['overrides'],
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );

        return $summary;
    }

    /**
     * @return list<array{partName:string, exists:bool, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, packagePartValid:bool, packagePartIssues:list<string>, directReferenceCount:int, reachableReferenceCount:int, directReferences:list<array{source:string, id:string, type:string, target:string, targetPart:string, targetQuery:?string, targetFragment:?string, sameSourceReference:bool, contentType:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, valid:bool, issues:list<string>}>, reachableReferences:list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:string, targetQuery:?string, targetFragment:?string, sameSourceReference:bool, contentType:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, valid:bool, issues:list<string>}>, valid:bool, issues:list<string>}>
     */
    public function packagePartReferenceInventory(string $reachableSourcePartName = '/', ?string $reachableRelationshipType = null): array
    {
        $inventory = [];

        foreach ($this->preflightPackageParts() as $part) {
            $partName = $part['partName'];
            $inventory[$partName] = [
                'partName' => $partName,
                'exists' => true,
                'contentType' => $part['contentType'],
                'relationshipPart' => $part['relationshipPart'],
                'relationshipSource' => $part['relationshipSource'],
                'relationshipSourceIsRelationshipPart' => $part['relationshipSourceIsRelationshipPart'],
                'relationshipSourceLoaded' => $part['relationshipSourceLoaded'],
                'sourceExists' => $part['sourceExists'],
                'packagePartValid' => $part['valid'],
                'packagePartIssues' => $part['issues'],
                'directReferenceCount' => 0,
                'reachableReferenceCount' => 0,
                'directReferences' => [],
                'reachableReferences' => [],
                'valid' => $part['valid'],
                'issues' => $part['issues'],
            ];
        }

        foreach ($this->preflightAllRelationshipTargets() as $target) {
            if ($target['external'] || $target['targetPart'] === null) {
                continue;
            }

            $entry =& self::packagePartReferenceInventoryEntry(
                $inventory,
                $target['targetPart'],
                $target['contentType'],
                $target['exists'] === true,
                $target['relationshipPartTarget'],
            );

            $suffix = self::targetQueryAndFragment($target['target']);
            $entry['directReferences'][] = [
                'source' => $target['source'],
                'id' => $target['id'],
                'type' => $target['type'],
                'target' => $target['target'],
                'targetPart' => $target['targetPart'],
                'targetQuery' => $suffix['query'],
                'targetFragment' => $suffix['fragment'],
                'sameSourceReference' => self::partNameEquivalenceKey($target['targetPart'])
                    === self::partNameEquivalenceKey($target['source']),
                'contentType' => $target['contentType'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];

            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            if (!$target['valid']) {
                $entry['valid'] = false;
            }
            unset($entry);
        }

        foreach ($this->reachableTargetsForSource($reachableSourcePartName, $reachableRelationshipType) as $target) {
            if ($target['external'] || $target['targetPart'] === null) {
                continue;
            }

            $entry =& self::packagePartReferenceInventoryEntry(
                $inventory,
                $target['targetPart'],
                $target['contentType'],
                $target['exists'] === true,
                $target['relationshipPartTarget'],
            );

            $suffix = self::targetQueryAndFragment($target['target']);
            $entry['reachableReferences'][] = [
                'source' => $target['source'],
                'depth' => $target['depth'],
                'id' => $target['id'],
                'type' => $target['type'],
                'target' => $target['target'],
                'targetPart' => $target['targetPart'],
                'targetQuery' => $suffix['query'],
                'targetFragment' => $suffix['fragment'],
                'sameSourceReference' => self::partNameEquivalenceKey($target['targetPart'])
                    === self::partNameEquivalenceKey($target['source']),
                'contentType' => $target['contentType'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];

            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            if (!$target['valid']) {
                $entry['valid'] = false;
            }
            unset($entry);
        }

        ksort($inventory, SORT_STRING);
        foreach ($inventory as &$entry) {
            usort(
                $entry['directReferences'],
                static fn (array $left, array $right): int => [
                    $left['source'],
                    $left['id'],
                    $left['targetPart'],
                ] <=> [
                    $right['source'],
                    $right['id'],
                    $right['targetPart'],
                ]
            );
            usort(
                $entry['reachableReferences'],
                static fn (array $left, array $right): int => [
                    $left['depth'],
                    $left['source'],
                    $left['id'],
                    $left['targetPart'],
                ] <=> [
                    $right['depth'],
                    $right['source'],
                    $right['id'],
                    $right['targetPart'],
                ]
            );
            sort($entry['issues'], SORT_STRING);
            sort($entry['packagePartIssues'], SORT_STRING);
            $entry['directReferenceCount'] = count($entry['directReferences']);
            $entry['reachableReferenceCount'] = count($entry['reachableReferences']);
            $entry['valid'] = $entry['valid'] && $entry['exists'];
        }
        unset($entry);

        return array_values($inventory);
    }

    /**
     * @return array{source:string, relationshipType:?string, valid:bool, inventoryPartCount:int, packagePartCount:int, relationshipPartCount:int, relationshipSourcePartCount:int, directReferencePartCount:int, reachableReferencePartCount:int, directReferenceCount:int, reachableReferenceCount:int, directQueryReferenceCount:int, directFragmentReferenceCount:int, directSameSourceReferenceCount:int, reachableQueryReferenceCount:int, reachableFragmentReferenceCount:int, reachableSameSourceReferenceCount:int, directOnlyPartCount:int, missingReferencedPartCount:int, unreferencedPackagePartCount:int, unreferencedRelationshipPartCount:int, invalidPartCount:int, externalDirectReferenceCount:int, externalReachableReferenceCount:int, invalidExternalReferenceCount:int, referencedPartNames:list<string>, reachablePartNames:list<string>, directOnlyPartNames:list<string>, missingReferencedPartNames:list<string>, unreferencedPackagePartNames:list<string>, unreferencedRelationshipPartNames:list<string>, invalidPartNames:list<string>, externalTargets:list<string>, reachableExternalTargets:list<string>, issueCounts:array<string,int>, issues:list<string>, parts:list<array{partName:string, exists:bool, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceLoaded:?bool, coverage:string, directReferenceCount:int, reachableReferenceCount:int, valid:bool, issues:list<string>}>}
     */
    public function packagePartRelationshipCoverageSummary(
        string $reachableSourcePartName = '/',
        ?string $reachableRelationshipType = null,
    ): array {
        $reachableSourcePartName = $this->relationshipSourceNameForEquivalent($reachableSourcePartName);
        $summary = [
            'source' => $reachableSourcePartName,
            'relationshipType' => $reachableRelationshipType,
            'valid' => true,
            'inventoryPartCount' => 0,
            'packagePartCount' => 0,
            'relationshipPartCount' => 0,
            'relationshipSourcePartCount' => 0,
            'directReferencePartCount' => 0,
            'reachableReferencePartCount' => 0,
            'directReferenceCount' => 0,
            'reachableReferenceCount' => 0,
            'directQueryReferenceCount' => 0,
            'directFragmentReferenceCount' => 0,
            'directSameSourceReferenceCount' => 0,
            'reachableQueryReferenceCount' => 0,
            'reachableFragmentReferenceCount' => 0,
            'reachableSameSourceReferenceCount' => 0,
            'directOnlyPartCount' => 0,
            'missingReferencedPartCount' => 0,
            'unreferencedPackagePartCount' => 0,
            'unreferencedRelationshipPartCount' => 0,
            'invalidPartCount' => 0,
            'externalDirectReferenceCount' => 0,
            'externalReachableReferenceCount' => 0,
            'invalidExternalReferenceCount' => 0,
            'referencedPartNames' => [],
            'reachablePartNames' => [],
            'directOnlyPartNames' => [],
            'missingReferencedPartNames' => [],
            'unreferencedPackagePartNames' => [],
            'unreferencedRelationshipPartNames' => [],
            'invalidPartNames' => [],
            'externalTargets' => [],
            'reachableExternalTargets' => [],
            'issueCounts' => [],
            'issues' => [],
            'parts' => [],
        ];

        foreach ($this->packagePartReferenceInventory($reachableSourcePartName, $reachableRelationshipType) as $part) {
            $summary['inventoryPartCount']++;
            $summary['directReferenceCount'] += $part['directReferenceCount'];
            $summary['reachableReferenceCount'] += $part['reachableReferenceCount'];

            foreach ($part['directReferences'] as $reference) {
                if ($reference['targetQuery'] !== null) {
                    $summary['directQueryReferenceCount']++;
                }
                if ($reference['targetFragment'] !== null) {
                    $summary['directFragmentReferenceCount']++;
                }
                if ($reference['sameSourceReference']) {
                    $summary['directSameSourceReferenceCount']++;
                }
            }

            foreach ($part['reachableReferences'] as $reference) {
                if ($reference['targetQuery'] !== null) {
                    $summary['reachableQueryReferenceCount']++;
                }
                if ($reference['targetFragment'] !== null) {
                    $summary['reachableFragmentReferenceCount']++;
                }
                if ($reference['sameSourceReference']) {
                    $summary['reachableSameSourceReferenceCount']++;
                }
            }

            if ($part['exists']) {
                $summary['packagePartCount']++;
            }

            if ($part['relationshipPart']) {
                $summary['relationshipPartCount']++;
                if ($part['relationshipSourceLoaded'] === true) {
                    $summary['relationshipSourcePartCount']++;
                }
            }

            if ($part['directReferenceCount'] > 0) {
                $summary['directReferencePartCount']++;
                self::appendUniqueString($summary['referencedPartNames'], $part['partName']);
            }

            if ($part['reachableReferenceCount'] > 0) {
                $summary['reachableReferencePartCount']++;
                self::appendUniqueString($summary['reachablePartNames'], $part['partName']);
            }

            $referenced = $part['directReferenceCount'] > 0 || $part['reachableReferenceCount'] > 0;
            if (!$part['exists'] && $referenced) {
                $coverage = 'missing-referenced-part';
                $summary['missingReferencedPartCount']++;
                self::appendUniqueString($summary['missingReferencedPartNames'], $part['partName']);
            } elseif ($part['directReferenceCount'] > 0 && $part['reachableReferenceCount'] > 0) {
                $coverage = 'direct-and-reachable';
            } elseif ($part['directReferenceCount'] > 0) {
                $coverage = 'direct-only';
                $summary['directOnlyPartCount']++;
                self::appendUniqueString($summary['directOnlyPartNames'], $part['partName']);
            } elseif ($part['reachableReferenceCount'] > 0) {
                $coverage = 'reachable-only';
            } elseif ($part['relationshipPart']) {
                $coverage = 'unreferenced-relationship-part';
                $summary['unreferencedRelationshipPartCount']++;
                self::appendUniqueString($summary['unreferencedRelationshipPartNames'], $part['partName']);
            } else {
                $coverage = 'unreferenced-package-part';
                $summary['unreferencedPackagePartCount']++;
                self::appendUniqueString($summary['unreferencedPackagePartNames'], $part['partName']);
            }

            if (!$part['valid']) {
                $summary['invalidPartCount']++;
                self::appendUniqueString($summary['invalidPartNames'], $part['partName']);
            }

            foreach ($part['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }

            $summary['parts'][] = [
                'partName' => $part['partName'],
                'exists' => $part['exists'],
                'contentType' => $part['contentType'],
                'relationshipPart' => $part['relationshipPart'],
                'relationshipSource' => $part['relationshipSource'],
                'relationshipSourceLoaded' => $part['relationshipSourceLoaded'],
                'coverage' => $coverage,
                'directReferenceCount' => $part['directReferenceCount'],
                'reachableReferenceCount' => $part['reachableReferenceCount'],
                'valid' => $part['valid'],
                'issues' => $part['issues'],
            ];
        }

        foreach ($this->preflightAllRelationshipTargets() as $target) {
            if (!$target['external']) {
                continue;
            }

            $summary['externalDirectReferenceCount']++;
            self::appendUniqueString($summary['externalTargets'], $target['target']);
            if (!$target['valid']) {
                $summary['invalidExternalReferenceCount']++;
                foreach ($target['issues'] as $issue) {
                    $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                    self::appendUniqueString($summary['issues'], $issue);
                }
            }
        }

        foreach ($this->reachableTargetsForSource($reachableSourcePartName, $reachableRelationshipType) as $target) {
            if (!$target['external']) {
                continue;
            }

            $summary['externalReachableReferenceCount']++;
            self::appendUniqueString($summary['reachableExternalTargets'], $target['target']);
        }

        foreach ([
            'referencedPartNames',
            'reachablePartNames',
            'directOnlyPartNames',
            'missingReferencedPartNames',
            'unreferencedPackagePartNames',
            'unreferencedRelationshipPartNames',
            'invalidPartNames',
            'externalTargets',
            'reachableExternalTargets',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }

        ksort($summary['issueCounts'], SORT_STRING);
        usort(
            $summary['parts'],
            static fn (array $left, array $right): int => $left['partName'] <=> $right['partName'],
        );

        $summary['valid'] = $summary['invalidPartCount'] === 0
            && $summary['invalidExternalReferenceCount'] === 0;

        return $summary;
    }

    /**
     * @return array{source:string, relationshipType:?string, valid:bool, issues:list<string>, expandedSourceCount:int, outsideSourceCount:int, stopCount:int, externalStopCount:int, invalidStopCount:int, missingStopCount:int, relationshipPartStopCount:int, cycleStopCount:int, unloadedStopCount:int, sources:list<array<string, mixed>>, stops:list<array<string, mixed>>}
     */
    public function relationshipSourceClosureInventory(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $queue = [[$sourcePartName, $relationshipType, 0]];
        $queuedSources = [$sourcePartName => true];
        $expandedSources = [];
        $expandedDepths = [];
        $stops = [];
        $issues = [];

        while ($queue !== []) {
            [$source, $filter, $depth] = array_shift($queue);
            $source = $this->relationshipSourceNameForEquivalent($source);
            if (isset($expandedSources[$source])) {
                continue;
            }

            $expandedSources[$source] = true;
            $expandedDepths[$source] = $depth;

            foreach ($this->preflightTargetsForSource($source, $filter) as $target) {
                $targetPart = self::targetPartFromPreflightTarget($target);
                $targetSuffix = $targetPart === null
                    ? ['query' => null, 'fragment' => null]
                    : self::targetQueryAndFragment($target['target']);
                $sameSourceReference = $targetPart !== null
                    && self::partNameEquivalenceKey($targetPart) === self::partNameEquivalenceKey($source);
                $stopReason = null;
                $targetSource = null;

                if ($target['external']) {
                    $stopReason = 'external-target';
                } elseif (in_array('invalid-target', $target['issues'], true)) {
                    $stopReason = 'invalid-target';
                } elseif ($targetPart === null) {
                    $stopReason = 'invalid-target';
                } elseif ($target['exists'] !== true) {
                    $stopReason = 'missing-target';
                } elseif ($target['relationshipPartTarget']) {
                    $stopReason = 'relationship-part-target';
                } else {
                    $targetSource = $this->relationshipSourceNameForEquivalent($targetPart);
                    if (isset($expandedSources[$targetSource]) || isset($queuedSources[$targetSource])) {
                        $stopReason = 'cycle-target';
                    } elseif (!($this->relationshipsForSource($targetSource) instanceof OpcRelationships)) {
                        $stopReason = 'target-source-not-loaded';
                    } else {
                        $queuedSources[$targetSource] = true;
                        $queue[] = [$targetSource, null, $depth + 1];
                    }
                }

                if ($stopReason === null) {
                    continue;
                }

                foreach ($target['issues'] as $issue) {
                    self::appendUniqueString($issues, $issue);
                }

                $stops[] = [
                    'source' => $source,
                    'depth' => $depth,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'targetQuery' => $targetSuffix['query'],
                    'targetFragment' => $targetSuffix['fragment'],
                    'sameSourceReference' => $sameSourceReference,
                    'targetSource' => $targetSource,
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'stopReason' => $stopReason,
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ];
            }
        }

        if (!isset($this->relationshipsBySource[$sourcePartName])) {
            self::appendUniqueString($issues, 'closure-source-not-loaded');
        }

        $sources = [];
        $outsideSourceCount = 0;
        foreach ($this->relationshipSourceInventory() as $source) {
            $sourceName = $source['source'];
            $reachable = isset($expandedSources[$sourceName]);
            if (!$reachable) {
                $outsideSourceCount++;
            }

            $source['reachable'] = $reachable;
            $source['depth'] = $expandedDepths[$sourceName] ?? null;
            $source['closureAction'] = $reachable ? 'expanded' : 'outside-selected-closure';
            $sources[] = $source;
        }

        $stopCounts = [
            'external-target' => 0,
            'invalid-target' => 0,
            'missing-target' => 0,
            'relationship-part-target' => 0,
            'cycle-target' => 0,
            'target-source-not-loaded' => 0,
        ];
        foreach ($stops as $stop) {
            $stopCounts[$stop['stopReason']] = ($stopCounts[$stop['stopReason']] ?? 0) + 1;
        }

        sort($issues, SORT_STRING);
        usort(
            $stops,
            static fn (array $left, array $right): int => [
                $left['depth'],
                $left['source'],
                $left['id'],
            ] <=> [
                $right['depth'],
                $right['source'],
                $right['id'],
            ]
        );

        return [
            'source' => $sourcePartName,
            'relationshipType' => $relationshipType,
            'valid' => $issues === [],
            'issues' => $issues,
            'expandedSourceCount' => count($expandedSources),
            'outsideSourceCount' => $outsideSourceCount,
            'stopCount' => count($stops),
            'externalStopCount' => $stopCounts['external-target'],
            'invalidStopCount' => $stopCounts['invalid-target'],
            'missingStopCount' => $stopCounts['missing-target'],
            'relationshipPartStopCount' => $stopCounts['relationship-part-target'],
            'cycleStopCount' => $stopCounts['cycle-target'],
            'unloadedStopCount' => $stopCounts['target-source-not-loaded'],
            'sources' => $sources,
            'stops' => $stops,
        ];
    }

    /**
     * @return array{source:string, relationshipType:?string, valid:bool, issues:list<string>, sourceCount:int, expandedSourceCount:int, outsideSourceCount:int, stopCount:int, expandedSourceNames:list<string>, outsideSourceNames:list<string>, sourceDepths:array<string,int>, stopReasonCounts:array<string,int>, stopIdsByReason:array<string,list<string>>, stopTargetsByReason:array<string,list<string>>, stopQueryTargetCount:int, stopFragmentTargetCount:int, stopSameSourceReferenceCount:int, invalidStopCount:int, invalidStopIds:list<string>, missingTargetParts:list<string>, relationshipPartTargetParts:list<string>, unloadedTargetSources:list<string>, externalTargets:list<string>}
     */
    public function relationshipSourceClosureCoverageSummary(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $closure = $this->relationshipSourceClosureInventory($sourcePartName, $relationshipType);
        $summary = [
            'source' => $closure['source'],
            'relationshipType' => $closure['relationshipType'],
            'valid' => $closure['valid'],
            'issues' => $closure['issues'],
            'sourceCount' => count($closure['sources']),
            'expandedSourceCount' => $closure['expandedSourceCount'],
            'outsideSourceCount' => $closure['outsideSourceCount'],
            'stopCount' => $closure['stopCount'],
            'expandedSourceNames' => [],
            'outsideSourceNames' => [],
            'sourceDepths' => [],
            'stopReasonCounts' => [],
            'stopIdsByReason' => [],
            'stopTargetsByReason' => [],
            'stopQueryTargetCount' => 0,
            'stopFragmentTargetCount' => 0,
            'stopSameSourceReferenceCount' => 0,
            'invalidStopCount' => 0,
            'invalidStopIds' => [],
            'missingTargetParts' => [],
            'relationshipPartTargetParts' => [],
            'unloadedTargetSources' => [],
            'externalTargets' => [],
        ];

        foreach ($closure['sources'] as $source) {
            $sourceName = (string) $source['source'];
            if ($source['reachable']) {
                self::appendUniqueString($summary['expandedSourceNames'], $sourceName);
                if ($source['depth'] !== null) {
                    $summary['sourceDepths'][$sourceName] = (int) $source['depth'];
                }
            } else {
                self::appendUniqueString($summary['outsideSourceNames'], $sourceName);
            }
        }

        foreach ($closure['stops'] as $stop) {
            $reason = (string) $stop['stopReason'];
            $summary['stopReasonCounts'][$reason] = ($summary['stopReasonCounts'][$reason] ?? 0) + 1;
            $summary['stopIdsByReason'][$reason] ??= [];
            $summary['stopTargetsByReason'][$reason] ??= [];
            self::appendUniqueString($summary['stopIdsByReason'][$reason], (string) $stop['id']);

            $stopTarget = $stop['targetPart'] ?? $stop['target'] ?? null;
            if (is_string($stopTarget) && $stopTarget !== '') {
                self::appendUniqueString($summary['stopTargetsByReason'][$reason], $stopTarget);
            }

            if (($stop['targetQuery'] ?? null) !== null) {
                $summary['stopQueryTargetCount']++;
            }
            if (($stop['targetFragment'] ?? null) !== null) {
                $summary['stopFragmentTargetCount']++;
            }
            if (($stop['sameSourceReference'] ?? false) === true) {
                $summary['stopSameSourceReferenceCount']++;
            }

            if (!$stop['valid']) {
                $summary['invalidStopCount']++;
                self::appendUniqueString($summary['invalidStopIds'], (string) $stop['id']);
            }

            if ($reason === 'missing-target' && is_string($stop['targetPart'] ?? null)) {
                self::appendUniqueString($summary['missingTargetParts'], $stop['targetPart']);
            }

            if ($reason === 'relationship-part-target' && is_string($stop['targetPart'] ?? null)) {
                self::appendUniqueString($summary['relationshipPartTargetParts'], $stop['targetPart']);
            }

            if ($reason === 'target-source-not-loaded') {
                $targetSource = $stop['targetSource'] ?? $stop['targetPart'] ?? null;
                if (is_string($targetSource) && $targetSource !== '') {
                    self::appendUniqueString($summary['unloadedTargetSources'], $targetSource);
                }
            }

            if ($reason === 'external-target') {
                self::appendUniqueString($summary['externalTargets'], (string) $stop['target']);
            }
        }

        foreach ([
            'expandedSourceNames',
            'outsideSourceNames',
            'invalidStopIds',
            'missingTargetParts',
            'relationshipPartTargetParts',
            'unloadedTargetSources',
            'externalTargets',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }

        ksort($summary['sourceDepths'], SORT_STRING);
        ksort($summary['stopReasonCounts'], SORT_STRING);
        ksort($summary['stopIdsByReason'], SORT_STRING);
        foreach ($summary['stopIdsByReason'] as &$ids) {
            sort($ids, SORT_STRING);
        }
        unset($ids);
        ksort($summary['stopTargetsByReason'], SORT_STRING);
        foreach ($summary['stopTargetsByReason'] as &$targets) {
            sort($targets, SORT_STRING);
        }
        unset($targets);

        return $summary;
    }

    /**
     * @return array{valid:bool, packagePartsValid:bool, contentTypeOverridesValid:bool, relationshipTargetsValid:bool, relationshipTypePoliciesValid:bool, packageParts:list<array{partName:string, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, relationshipPartLoadAction:?string, relationshipPartLoadReason:?string, sourceExists:?bool, valid:bool, issues:list<string>}>, contentTypeOverrides:list<array{partName:string, contentType:string, exists:bool, packagePartName:?string, partNameExactMatch:bool, partNameEquivalentMatch:bool, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>, relationshipTargets:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>, relationshipTypePolicies:list<array{type:string, relationshipCount:int, sourceCount:int, sources:list<string>, idsBySource:array<string, list<string>>, internalCount:int, externalCount:int, validCount:int, invalidCount:int, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, targetParts:list<string>, contentTypes:list<string>, knownRole:?string, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>, issues:list<string>}>}
     */
    public function preflightPackageConsistency(): array
    {
        $packageParts = $this->preflightPackageParts();
        $contentTypeOverrides = $this->preflightContentTypeOverrides();
        $relationshipTargets = $this->preflightAllRelationshipTargets();
        $relationshipTypePolicies = self::knownRelationshipTypePolicies($this->relationshipTypeInventory());
        $packagePartsValid = self::allRowsValid($packageParts);
        $contentTypeOverridesValid = self::allRowsValid($contentTypeOverrides);
        $relationshipTargetsValid = self::allRowsValid($relationshipTargets);
        $relationshipTypePoliciesValid = self::allRelationshipTypePoliciesValid($relationshipTypePolicies);

        return [
            'valid' => $packagePartsValid
                && $contentTypeOverridesValid
                && $relationshipTargetsValid
                && $relationshipTypePoliciesValid,
            'packagePartsValid' => $packagePartsValid,
            'contentTypeOverridesValid' => $contentTypeOverridesValid,
            'relationshipTargetsValid' => $relationshipTargetsValid,
            'relationshipTypePoliciesValid' => $relationshipTypePoliciesValid,
            'packageParts' => $packageParts,
            'contentTypeOverrides' => $contentTypeOverrides,
            'relationshipTargets' => $relationshipTargets,
            'relationshipTypePolicies' => $relationshipTypePolicies,
        ];
    }

    /**
     * @return array{valid:bool, packagePartsValid:bool, contentTypeOverridesValid:bool, relationshipTargetsValid:bool, relationshipTypePoliciesValid:bool, packagePartCount:int, invalidPackagePartCount:int, contentTypeOverrideCount:int, invalidContentTypeOverrideCount:int, relationshipTargetCount:int, invalidRelationshipTargetCount:int, relationshipTypePolicyCount:int, invalidRelationshipTypePolicyCount:int, invalidPackagePartNames:list<string>, invalidContentTypeOverrideParts:list<string>, invalidRelationshipTargetKeys:list<string>, invalidRelationshipTypePolicyTypes:list<string>, issueCounts:array<string,int>, sectionIssueCounts:array<string,array<string,int>>, issues:list<string>}
     */
    public function packageConsistencySummary(): array
    {
        $consistency = $this->preflightPackageConsistency();
        $sectionIssueCounts = [
            'packageParts' => [],
            'contentTypeOverrides' => [],
            'relationshipTargets' => [],
            'relationshipTypePolicies' => [],
        ];
        $issueCounts = [];
        $issues = [];
        $invalidPackagePartNames = [];
        $invalidContentTypeOverrideParts = [];
        $invalidRelationshipTargetKeys = [];
        $invalidRelationshipTypePolicyTypes = [];

        $recordIssue = static function (string $section, string $issue) use (&$sectionIssueCounts, &$issueCounts, &$issues): void {
            $sectionIssueCounts[$section][$issue] = ($sectionIssueCounts[$section][$issue] ?? 0) + 1;
            $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
            self::appendUniqueString($issues, $issue);
        };

        foreach ($consistency['packageParts'] as $part) {
            if (!$part['valid']) {
                self::appendUniqueString($invalidPackagePartNames, $part['partName']);
            }

            foreach ($part['issues'] as $issue) {
                $recordIssue('packageParts', $issue);
            }
        }

        foreach ($consistency['contentTypeOverrides'] as $override) {
            if (!$override['valid']) {
                self::appendUniqueString($invalidContentTypeOverrideParts, $override['partName']);
            }

            foreach ($override['issues'] as $issue) {
                $recordIssue('contentTypeOverrides', $issue);
            }
        }

        foreach ($consistency['relationshipTargets'] as $target) {
            if (!$target['valid']) {
                self::appendUniqueString($invalidRelationshipTargetKeys, $target['source'] . ':' . $target['id']);
            }

            foreach ($target['issues'] as $issue) {
                $recordIssue('relationshipTargets', $issue);
            }
        }

        foreach ($consistency['relationshipTypePolicies'] as $policy) {
            if (!$policy['policyValid']) {
                self::appendUniqueString($invalidRelationshipTypePolicyTypes, $policy['type']);
            }

            foreach ($policy['policyIssues'] as $issue) {
                $recordIssue('relationshipTypePolicies', $issue);
            }
        }

        foreach ($sectionIssueCounts as &$counts) {
            ksort($counts, SORT_STRING);
        }
        unset($counts);

        sort($invalidPackagePartNames, SORT_STRING);
        sort($invalidContentTypeOverrideParts, SORT_STRING);
        sort($invalidRelationshipTargetKeys, SORT_STRING);
        sort($invalidRelationshipTypePolicyTypes, SORT_STRING);
        ksort($issueCounts, SORT_STRING);
        sort($issues, SORT_STRING);

        return [
            'valid' => $consistency['valid'],
            'packagePartsValid' => $consistency['packagePartsValid'],
            'contentTypeOverridesValid' => $consistency['contentTypeOverridesValid'],
            'relationshipTargetsValid' => $consistency['relationshipTargetsValid'],
            'relationshipTypePoliciesValid' => $consistency['relationshipTypePoliciesValid'],
            'packagePartCount' => count($consistency['packageParts']),
            'invalidPackagePartCount' => count($invalidPackagePartNames),
            'contentTypeOverrideCount' => count($consistency['contentTypeOverrides']),
            'invalidContentTypeOverrideCount' => count($invalidContentTypeOverrideParts),
            'relationshipTargetCount' => count($consistency['relationshipTargets']),
            'invalidRelationshipTargetCount' => count($invalidRelationshipTargetKeys),
            'relationshipTypePolicyCount' => count($consistency['relationshipTypePolicies']),
            'invalidRelationshipTypePolicyCount' => count($invalidRelationshipTypePolicyTypes),
            'invalidPackagePartNames' => $invalidPackagePartNames,
            'invalidContentTypeOverrideParts' => $invalidContentTypeOverrideParts,
            'invalidRelationshipTargetKeys' => $invalidRelationshipTargetKeys,
            'invalidRelationshipTypePolicyTypes' => $invalidRelationshipTypePolicyTypes,
            'issueCounts' => $issueCounts,
            'sectionIssueCounts' => $sectionIssueCounts,
            'issues' => $issues,
        ];
    }

    /**
     * @param list<string> $expectedContentTypes
     * @return array{relationshipCount:int, expectedContentTypes:list<string>, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightOfficeDocumentRoot(array $expectedContentTypes = []): array
    {
        $expectedContentTypes = array_values(array_unique($expectedContentTypes));
        $targets = $this->preflightTargetsForSource('/', self::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
        $relationshipCount = count($targets);
        $issues = [];
        if ($relationshipCount === 0) {
            $issues[] = 'missing-office-document-relationship';
        } elseif ($relationshipCount > 1) {
            $issues[] = 'multiple-office-document-relationships';
        }

        $relationships = [];
        foreach ($targets as $target) {
            $targetIssues = $target['issues'];
            if ($target['external']) {
                $targetIssues[] = 'external-office-document-target';
            }

            if (
                $expectedContentTypes !== []
                && !$target['external']
                && $target['contentType'] !== null
                && !self::contentTypeMatchesAny($target['contentType'], $expectedContentTypes)
            ) {
                $targetIssues[] = 'invalid-office-document-content-type';
            }

            $targetIssues = array_values(array_unique($targetIssues));
            $relationships[] = [
                'source' => '/',
                'id' => $target['id'],
                'type' => $target['type'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $targetIssues === [],
                'issues' => $targetIssues,
            ];
        }

        return [
            'relationshipCount' => $relationshipCount,
            'expectedContentTypes' => $expectedContentTypes,
            'valid' => $issues === [] && array_reduce(
                $relationships,
                static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
                true,
            ),
            'issues' => $issues,
            'relationships' => $relationships,
        ];
    }

    /**
     * @param list<string>|null $expectedContentTypes
     * @return array{valid:bool, issues:list<string>, documentPart:?string, documentContentType:?string, documentRelationshipPartName:?string, documentRelationshipPartLoaded:bool, officeDocument:array<string, mixed>, relationshipClosure:array<string, mixed>, relationshipRoleCount:int, relationshipRoleCounts:array<string, int>, invalidRelationshipRoleCount:int, invalidRelationshipRoleIssues:list<string>, invalidRelationshipRoles:list<array{id:string, role:string, type:string, targetPart:?string, external:bool, valid:bool, issues:list<string>}>, documentRelationshipRoles:list<array<string, mixed>>}
     */
    public function preflightOfficeDocumentRelationshipReadiness(?array $expectedContentTypes = null): array
    {
        $officeDocument = $this->preflightOfficeDocumentRoot(
            $expectedContentTypes ?? self::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES
        );
        $documentRelationship = null;
        foreach ($officeDocument['relationships'] as $relationship) {
            if ($relationship['external'] || $relationship['targetPart'] === null) {
                continue;
            }

            $documentRelationship = $relationship;
            break;
        }

        $documentPart = $documentRelationship['targetPart'] ?? null;
        $documentContentType = $documentRelationship['contentType'] ?? null;
        $documentRelationshipPartName = $documentPart === null
            ? null
            : OpcRelationships::relationshipPartNameForSource($documentPart);
        $documentRelationshipPartLoaded = $documentPart !== null
            && $this->relationshipsForSource($documentPart) instanceof OpcRelationships;
        $documentRelationshipRoles = $documentPart === null
            ? []
            : $this->preflightWordprocessingDocumentRelationships($documentPart);
        $relationshipClosure = $this->relationshipSourceClosureInventory('/', self::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);

        $roleCounts = [];
        $invalidRoles = [];
        $invalidRoleIssues = [];
        foreach ($documentRelationshipRoles as $role) {
            $roleName = $role['role'];
            $roleCounts[$roleName] = ($roleCounts[$roleName] ?? 0) + 1;

            if ($role['valid']) {
                continue;
            }

            $invalidRoles[] = [
                'id' => $role['id'],
                'role' => $roleName,
                'type' => $role['type'],
                'targetPart' => $role['targetPart'],
                'external' => $role['external'],
                'valid' => false,
                'issues' => $role['issues'],
            ];
            foreach ($role['issues'] as $issue) {
                self::appendUniqueString($invalidRoleIssues, $issue);
            }
        }
        ksort($roleCounts, SORT_STRING);
        sort($invalidRoleIssues, SORT_STRING);

        $issues = $officeDocument['issues'];
        foreach ($officeDocument['relationships'] as $relationship) {
            foreach ($relationship['issues'] as $issue) {
                self::appendUniqueString($issues, $issue);
            }
        }
        foreach ($relationshipClosure['issues'] as $issue) {
            self::appendUniqueString($issues, $issue);
        }
        foreach ($invalidRoleIssues as $issue) {
            self::appendUniqueString($issues, $issue);
        }
        sort($issues, SORT_STRING);

        return [
            'valid' => $officeDocument['valid']
                && $relationshipClosure['valid']
                && $invalidRoles === [],
            'issues' => $issues,
            'documentPart' => $documentPart,
            'documentContentType' => $documentContentType,
            'documentRelationshipPartName' => $documentRelationshipPartName,
            'documentRelationshipPartLoaded' => $documentRelationshipPartLoaded,
            'officeDocument' => $officeDocument,
            'relationshipClosure' => [
                'valid' => $relationshipClosure['valid'],
                'issues' => $relationshipClosure['issues'],
                'expandedSourceCount' => $relationshipClosure['expandedSourceCount'],
                'outsideSourceCount' => $relationshipClosure['outsideSourceCount'],
                'stopCount' => $relationshipClosure['stopCount'],
                'externalStopCount' => $relationshipClosure['externalStopCount'],
                'invalidStopCount' => $relationshipClosure['invalidStopCount'],
                'missingStopCount' => $relationshipClosure['missingStopCount'],
                'relationshipPartStopCount' => $relationshipClosure['relationshipPartStopCount'],
                'cycleStopCount' => $relationshipClosure['cycleStopCount'],
                'unloadedStopCount' => $relationshipClosure['unloadedStopCount'],
            ],
            'relationshipRoleCount' => count($documentRelationshipRoles),
            'relationshipRoleCounts' => $roleCounts,
            'invalidRelationshipRoleCount' => count($invalidRoles),
            'invalidRelationshipRoleIssues' => $invalidRoleIssues,
            'invalidRelationshipRoles' => $invalidRoles,
            'documentRelationshipRoles' => $documentRelationshipRoles,
        ];
    }

    /**
     * @return array{valid:bool, source:?string, roleTargetCount:int, validRoleTargetCount:int, invalidRoleTargetCount:int, roleCounts:array<string,int>, issueCounts:array<string,int>, issues:list<string>, relationships:list<array<string,mixed>>}
     */
    public function preflightRelationshipRoleTargets(?string $sourcePartName = null): array
    {
        $sourceFilter = $sourcePartName === null
            ? null
            : $this->relationshipSourceNameForEquivalent($sourcePartName);
        $sources = $sourceFilter === null ? $this->sourcePartNames() : [$sourceFilter];
        $relationships = [];

        /**
         * @param list<array<string,mixed>> $rows
         * @param array<string,mixed> $extra
         */
        $appendRows = function (array $rows, ?string $roleOverride = null, array $extra = []) use (&$relationships): void {
            foreach ($rows as $row) {
                $source = (string) ($row['source'] ?? $extra['source'] ?? '/');
                $role = (string) ($roleOverride ?? $row['role'] ?? $row['kind'] ?? $extra['role'] ?? 'relationship');
                $target = (string) $row['target'];
                $targetSuffix = self::targetQueryAndFragment($target);
                $relationships[] = [
                    'source' => $source,
                    'sourceContentType' => $row['sourceContentType'] ?? (
                        $source === '/' ? null : $this->contentTypes->contentTypeForPart($source)
                    ),
                    'id' => (string) $row['id'],
                    'role' => $role,
                    'type' => (string) $row['type'],
                    'target' => $target,
                    'targetPart' => $row['targetPart'] ?? null,
                    'targetReferenceSuffix' => substr($target, strcspn($target, '?#')),
                    'targetQuery' => $targetSuffix['query'],
                    'targetFragment' => $targetSuffix['fragment'],
                    'contentType' => $row['contentType'] ?? null,
                    'expectedContentType' => $row['expectedContentType'] ?? $extra['expectedContentType'] ?? null,
                    'expectedContentTypes' => $row['expectedContentTypes'] ?? $extra['expectedContentTypes'] ?? null,
                    'expectedContentTypePrefix' => $row['expectedContentTypePrefix'] ?? $extra['expectedContentTypePrefix'] ?? null,
                    'expectedSource' => $row['expectedSource'] ?? $extra['expectedSource'] ?? null,
                    'sourceAllowed' => $row['sourceAllowed'] ?? $extra['sourceAllowed'] ?? null,
                    'expectedSourceContentTypes' => $row['expectedSourceContentTypes'] ?? $extra['expectedSourceContentTypes'] ?? null,
                    'expectedExternal' => $row['expectedExternal'] ?? $extra['expectedExternal'] ?? null,
                    'external' => (bool) $row['external'],
                    'exists' => $row['exists'] ?? null,
                    'relationshipPartTarget' => (bool) ($row['relationshipPartTarget'] ?? false),
                    'relationshipTypeKind' => (string) ($row['relationshipTypeKind'] ?? 'unknown'),
                    'relationshipTypeScheme' => $row['relationshipTypeScheme'] ?? null,
                    'relationshipTypeValid' => (bool) ($row['relationshipTypeValid'] ?? true),
                    'relationshipTypeIssues' => $row['relationshipTypeIssues'] ?? [],
                    'externalTargetKind' => $row['externalTargetKind'] ?? null,
                    'externalTargetScheme' => $row['externalTargetScheme'] ?? null,
                    'externalTargetAllowed' => $row['externalTargetAllowed'] ?? null,
                    'externalTargetRequiresBaseUri' => $row['externalTargetRequiresBaseUri'] ?? null,
                    'externalTargetRewriteBasePart' => $row['externalTargetRewriteBasePart'] ?? null,
                    'externalTargetRewriteReason' => $row['externalTargetRewriteReason'] ?? null,
                    'valid' => (bool) $row['valid'],
                    'issues' => array_values(array_unique($row['issues'] ?? [])),
                ];
            }
        };

        if ($sourceFilter === null || $sourceFilter === '/') {
            $officeDocument = $this->preflightOfficeDocumentRoot(self::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
            $appendRows(
                $officeDocument['relationships'],
                'office-document',
                [
                    'expectedSource' => '/',
                    'expectedContentTypes' => $officeDocument['expectedContentTypes'],
                    'expectedExternal' => false,
                ],
            );

            $documentProperties = $this->preflightDocumentProperties();
            foreach ([
                'core' => 'core-properties',
                'extended' => 'extended-properties',
                'custom' => 'custom-properties',
            ] as $propertyRole => $roleName) {
                $property = $documentProperties['roles'][$propertyRole];
                $appendRows(
                    $property['relationships'],
                    $roleName,
                    [
                        'expectedSource' => '/',
                        'expectedContentType' => $property['expectedContentType'],
                        'expectedExternal' => false,
                    ],
                );
            }
        }

        $digitalSignatureRoles = $this->preflightDigitalSignatureRelationshipRoles();
        foreach ($digitalSignatureRoles['roles'] as $role) {
            if ($sourceFilter !== null && $role['source'] !== $sourceFilter) {
                continue;
            }

            $appendRows([$role]);
        }

        $appendRows($this->preflightEncryptedPackages($sourceFilter));
        $appendRows($this->preflightThumbnails($sourceFilter), 'thumbnail', ['expectedExternal' => false]);

        foreach ($sources as $source) {
            $appendRows($this->preflightEmbeddedPackages($source));
            $appendRows($this->preflightWordprocessingDocumentRelationships($source));
        }

        usort(
            $relationships,
            static fn (array $left, array $right): int => [
                $left['source'],
                $left['id'],
                $left['role'],
                $left['type'],
            ] <=> [
                $right['source'],
                $right['id'],
                $right['role'],
                $right['type'],
            ],
        );

        $roleCounts = [];
        $issueCounts = [];
        $issues = [];
        $validRoleTargetCount = 0;
        foreach ($relationships as $relationship) {
            $role = $relationship['role'];
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;

            if ($relationship['valid']) {
                $validRoleTargetCount++;
            }

            foreach ($relationship['issues'] as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
                self::appendUniqueString($issues, $issue);
            }
        }

        ksort($roleCounts, SORT_STRING);
        ksort($issueCounts, SORT_STRING);
        sort($issues, SORT_STRING);

        return [
            'valid' => $validRoleTargetCount === count($relationships),
            'source' => $sourceFilter,
            'roleTargetCount' => count($relationships),
            'validRoleTargetCount' => $validRoleTargetCount,
            'invalidRoleTargetCount' => count($relationships) - $validRoleTargetCount,
            'roleCounts' => $roleCounts,
            'issueCounts' => $issueCounts,
            'issues' => $issues,
            'relationships' => $relationships,
        ];
    }

    /**
     * @return array{relationshipCount:int, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightCoreProperties(): array
    {
        $targets = $this->preflightTargetsForSource('/', self::CORE_PROPERTIES_RELATIONSHIP_TYPE);
        $relationshipCount = count($targets);
        $issues = [];
        if ($relationshipCount > 1) {
            $issues[] = 'multiple-core-properties-relationships';
        }

        $relationships = [];
        foreach ($targets as $target) {
            $targetIssues = $target['issues'];
            if ($target['external']) {
                $targetIssues[] = 'external-core-properties-target';
            }

            if (
                !$target['external']
                && $target['contentType'] !== null
                && !self::contentTypeMatches($target['contentType'], self::CORE_PROPERTIES_CONTENT_TYPE)
            ) {
                $targetIssues[] = 'invalid-core-properties-content-type';
            }

            $targetIssues = array_values(array_unique($targetIssues));
            $relationships[] = [
                'source' => '/',
                'id' => $target['id'],
                'type' => $target['type'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $targetIssues === [],
                'issues' => $targetIssues,
            ];
        }

        return [
            'relationshipCount' => $relationshipCount,
            'valid' => $issues === [] && array_reduce(
                $relationships,
                static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
                true,
            ),
            'issues' => $issues,
            'relationships' => $relationships,
        ];
    }

    /**
     * @return array{valid:bool, roles:array<string,array{role:string, relationshipType:string, expectedContentType:string, relationshipCount:int, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}>}
     */
    public function preflightDocumentProperties(): array
    {
        $definitions = [
            'core' => [
                'relationshipType' => self::CORE_PROPERTIES_RELATIONSHIP_TYPE,
                'expectedContentType' => self::CORE_PROPERTIES_CONTENT_TYPE,
                'multipleIssue' => 'multiple-core-properties-relationships',
                'externalIssue' => 'external-core-properties-target',
                'invalidContentTypeIssue' => 'invalid-core-properties-content-type',
            ],
            'extended' => [
                'relationshipType' => self::EXTENDED_PROPERTIES_RELATIONSHIP_TYPE,
                'expectedContentType' => self::EXTENDED_PROPERTIES_CONTENT_TYPE,
                'multipleIssue' => 'multiple-extended-properties-relationships',
                'externalIssue' => 'external-extended-properties-target',
                'invalidContentTypeIssue' => 'invalid-extended-properties-content-type',
            ],
            'custom' => [
                'relationshipType' => self::CUSTOM_PROPERTIES_RELATIONSHIP_TYPE,
                'expectedContentType' => self::CUSTOM_PROPERTIES_CONTENT_TYPE,
                'multipleIssue' => 'multiple-custom-properties-relationships',
                'externalIssue' => 'external-custom-properties-target',
                'invalidContentTypeIssue' => 'invalid-custom-properties-content-type',
            ],
        ];

        $roles = [];
        foreach ($definitions as $role => $definition) {
            $targets = $this->preflightTargetsForSource('/', $definition['relationshipType']);
            $relationshipCount = count($targets);
            $issues = [];

            if ($relationshipCount > 1) {
                $issues[] = $definition['multipleIssue'];
            }

            $relationships = [];
            foreach ($targets as $target) {
                $targetIssues = $target['issues'];
                if ($target['external']) {
                    $targetIssues[] = $definition['externalIssue'];
                }

                if (
                    !$target['external']
                    && $target['contentType'] !== null
                    && !self::contentTypeMatches($target['contentType'], $definition['expectedContentType'])
                ) {
                    $targetIssues[] = $definition['invalidContentTypeIssue'];
                }

                $targetIssues = array_values(array_unique($targetIssues));
                $relationships[] = [
                    'source' => '/',
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'target' => $target['target'],
                    'targetPart' => self::targetPartFromPreflightTarget($target),
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $targetIssues === [],
                    'issues' => $targetIssues,
                ];
            }

            $relationshipsValid = array_reduce(
                $relationships,
                static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
                true,
            );

            $roles[$role] = [
                'role' => $role,
                'relationshipType' => $definition['relationshipType'],
                'expectedContentType' => $definition['expectedContentType'],
                'relationshipCount' => $relationshipCount,
                'valid' => $issues === [] && $relationshipsValid,
                'issues' => $issues,
                'relationships' => $relationships,
            ];
        }

        return [
            'valid' => array_reduce(
                $roles,
                static fn (bool $valid, array $role): bool => $valid && $role['valid'],
                true,
            ),
            'roles' => $roles,
        ];
    }

    /**
     * @return list<array{source:string, sourceContentType:?string, id:string, role:string, type:string, target:string, targetPart:?string, contentType:?string, expectedContentType:?string, expectedContentTypes:?list<string>, expectedContentTypePrefix:?string, expectedSourceContentTypes:?list<string>, expectedExternal:?bool, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightWordprocessingDocumentRelationships(string $sourcePartName): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $sourceContentType = $sourcePartName === '/'
            ? null
            : $this->contentTypes->contentTypeForPart($sourcePartName);
        $definitions = self::wordprocessingDocumentRelationshipRoleDefinitions();
        $preflight = [];

        foreach ($this->preflightTargetsForSource($sourcePartName) as $target) {
            if (!isset($definitions[$target['type']])) {
                continue;
            }

            $definition = $definitions[$target['type']];
            $role = $definition['role'];
            $expectedContentType = $definition['expectedContentType'] ?? null;
            $expectedContentTypes = $definition['expectedContentTypes'] ?? null;
            $expectedContentTypePrefix = $definition['expectedContentTypePrefix'] ?? null;
            $expectedSourceContentTypes = $definition['expectedSourceContentTypes'] ?? null;
            $expectedExternal = $definition['expectedExternal'] ?? null;
            $targetPart = self::targetPartFromPreflightTarget($target);
            $issues = $target['issues'];

            if (
                $expectedSourceContentTypes !== null
                && !self::contentTypeMatchesAny($sourceContentType, $expectedSourceContentTypes)
            ) {
                $issues[] = 'invalid-' . $role . '-source-content-type';
            }

            if ($expectedExternal !== null && $target['external'] !== $expectedExternal) {
                $issues[] = ($target['external'] ? 'external-' : 'internal-') . $role . '-target';
            }

            if (
                !$target['external']
                && $expectedContentType !== null
                && $target['contentType'] !== null
                && !self::contentTypeMatches($target['contentType'], $expectedContentType)
            ) {
                $issues[] = 'invalid-' . $role . '-content-type';
            }

            if (
                !$target['external']
                && $expectedContentTypes !== null
                && $target['contentType'] !== null
                && !self::contentTypeMediaTypeMatchesAny($target['contentType'], $expectedContentTypes)
            ) {
                $issues[] = 'invalid-' . $role . '-content-type';
            }

            if (
                !$target['external']
                && $expectedContentTypePrefix !== null
                && $target['contentType'] !== null
                && !self::contentTypeHasPrefix($target['contentType'], $expectedContentTypePrefix)
            ) {
                $issues[] = 'invalid-' . $role . '-content-type';
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'source' => $sourcePartName,
                'sourceContentType' => $sourceContentType,
                'id' => $target['id'],
                'role' => $role,
                'type' => $target['type'],
                'target' => $target['target'],
                'targetPart' => $targetPart,
                'contentType' => $target['contentType'],
                'expectedContentType' => $expectedContentType,
                'expectedContentTypes' => $expectedContentTypes,
                'expectedContentTypePrefix' => $expectedContentTypePrefix,
                'expectedSourceContentTypes' => $expectedSourceContentTypes,
                'expectedExternal' => $expectedExternal,
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, sourceContentType:?string, id:string, role:string, type:string, target:string, targetPart:?string, contentType:?string, expectedContentType:?string, expectedSourceContentTypes:?list<string>, expectedExternal:?bool, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, rootName:?string, rootNamespace:?string, itemId:?string, itemIdValid:?bool, schemaRefCount:int, schemaRefUris:list<string>, parseError:?string, valid:bool, issues:list<string>}>
     */
    public function preflightCustomXmlProperties(?string $sourcePartName = null): array
    {
        $sources = $sourcePartName === null
            ? $this->sourcePartNames()
            : [$this->relationshipSourceNameForEquivalent($sourcePartName)];

        $preflight = [];
        foreach ($sources as $source) {
            foreach ($this->preflightWordprocessingDocumentRelationships($source) as $relationship) {
                if ($relationship['role'] !== 'custom-xml-properties') {
                    continue;
                }

                $issues = $relationship['issues'];
                $rootName = null;
                $rootNamespace = null;
                $itemId = null;
                $itemIdValid = null;
                $schemaRefCount = 0;
                $schemaRefUris = [];
                $parseError = null;

                if (
                    !$relationship['external']
                    && $relationship['targetPart'] !== null
                    && $relationship['exists'] === true
                    && self::contentTypeMatches($relationship['contentType'], self::WORDPROCESSING_CUSTOM_XML_PROPERTIES_CONTENT_TYPE)
                ) {
                    try {
                        $metadata = self::customXmlPropertiesMetadata($this->package->read($relationship['targetPart']));
                        $rootName = $metadata['rootName'];
                        $rootNamespace = $metadata['rootNamespace'];
                        $itemId = $metadata['itemId'];
                        $itemIdValid = $metadata['itemIdValid'];
                        $schemaRefCount = $metadata['schemaRefCount'];
                        $schemaRefUris = $metadata['schemaRefUris'];

                        foreach ($metadata['issues'] as $issue) {
                            self::appendUniqueString($issues, $issue);
                        }
                    } catch (\InvalidArgumentException | \RuntimeException $exception) {
                        self::appendUniqueString($issues, 'custom-xml-properties-parse-error');
                        $parseError = $exception->getMessage();
                    }
                }

                $issues = array_values(array_unique($issues));
                $preflight[] = [
                    'source' => $relationship['source'],
                    'sourceContentType' => $relationship['sourceContentType'],
                    'id' => $relationship['id'],
                    'role' => $relationship['role'],
                    'type' => $relationship['type'],
                    'target' => $relationship['target'],
                    'targetPart' => $relationship['targetPart'],
                    'contentType' => $relationship['contentType'],
                    'expectedContentType' => $relationship['expectedContentType'],
                    'expectedSourceContentTypes' => $relationship['expectedSourceContentTypes'],
                    'expectedExternal' => $relationship['expectedExternal'],
                    'external' => $relationship['external'],
                    'exists' => $relationship['exists'],
                    'relationshipPartTarget' => $relationship['relationshipPartTarget'],
                    'relationshipTypeKind' => $relationship['relationshipTypeKind'],
                    'relationshipTypeScheme' => $relationship['relationshipTypeScheme'],
                    'relationshipTypeValid' => $relationship['relationshipTypeValid'],
                    'relationshipTypeIssues' => $relationship['relationshipTypeIssues'],
                    'externalTargetKind' => $relationship['externalTargetKind'],
                    'externalTargetScheme' => $relationship['externalTargetScheme'],
                    'externalTargetAllowed' => $relationship['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $relationship['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $relationship['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $relationship['externalTargetRewriteReason'],
                    'rootName' => $rootName,
                    'rootNamespace' => $rootNamespace,
                    'itemId' => $itemId,
                    'itemIdValid' => $itemIdValid,
                    'schemaRefCount' => $schemaRefCount,
                    'schemaRefUris' => $schemaRefUris,
                    'parseError' => $parseError,
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, target:string, targetPart:?string, contentType:?string, expectedContentTypePrefix:string, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightThumbnails(?string $sourcePartName = null): array
    {
        $sources = $sourcePartName === null
            ? $this->sourcePartNames()
            : [$this->relationshipSourceNameForEquivalent($sourcePartName)];

        $preflight = [];
        foreach ($sources as $source) {
            $sourceTargets = $this->preflightTargetsForSource($source, self::THUMBNAIL_RELATIONSHIP_TYPE);
            $sourceHasMultipleThumbnails = count($sourceTargets) > 1;

            foreach ($sourceTargets as $target) {
                $targetPart = self::targetPartFromPreflightTarget($target);
                $issues = $target['issues'];
                if ($sourceHasMultipleThumbnails) {
                    $issues[] = 'multiple-thumbnail-relationships-for-source';
                }

                if ($target['external']) {
                    $issues[] = 'external-thumbnail-target';
                }

                if (
                    $target['contentType'] !== null
                    && !self::isImageContentType($target['contentType'])
                ) {
                    $issues[] = 'invalid-thumbnail-content-type';
                }

                if ($targetPart !== null && $this->hasRelationshipsForSource($targetPart)) {
                    $issues[] = 'thumbnail-target-has-relationships';
                }

                $issues = array_values(array_unique($issues));
                $preflight[] = [
                    'source' => $source,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $target['contentType'],
                    'expectedContentTypePrefix' => 'image/',
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartName:?string, valid:bool, issues:list<string>, signatures:list<array{id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, valid:bool, issues:list<string>}>}>
     */
    public function preflightDigitalSignatures(): array
    {
        $origins = $this->preflightTargetsForSource('/', self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE);
        $originCount = count($origins);
        $preflight = [];

        foreach ($origins as $origin) {
            $targetPart = self::targetPartFromPreflightTarget($origin);
            $issues = $origin['issues'];
            $relationshipPartName = null;
            $signatures = [];

            if ($originCount > 1) {
                $issues[] = 'multiple-digital-signature-origins';
            }

            if ($origin['external']) {
                $issues[] = 'external-digital-signature-origin';
            }

            if ($origin['contentType'] !== null && !self::contentTypeMatches($origin['contentType'], self::DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE)) {
                $issues[] = 'invalid-digital-signature-origin-content-type';
            }

            if (
                $targetPart !== null
                && $origin['external'] === false
                && $origin['exists'] === true
                && $origin['relationshipPartTarget'] === false
            ) {
                if ($this->hasRelationshipsForSource($targetPart)) {
                    $relationshipPartName = OpcRelationships::relationshipPartNameForSource($targetPart);
                    foreach ($this->preflightTargetsForSource($targetPart, self::DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE) as $signature) {
                        $signatureIssues = $signature['issues'];
                        if ($signature['external']) {
                            $signatureIssues[] = 'external-digital-signature-target';
                        }

                        if ($signature['contentType'] !== null && !self::contentTypeMatches($signature['contentType'], self::DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE)) {
                            $signatureIssues[] = 'invalid-digital-signature-content-type';
                        }

                        $signatureIssues = array_values(array_unique($signatureIssues));
                        $signatures[] = [
                            'id' => $signature['id'],
                            'type' => $signature['type'],
                            'target' => $signature['target'],
                            'targetPart' => self::targetPartFromPreflightTarget($signature),
                            'contentType' => $signature['contentType'],
                            'external' => $signature['external'],
                            'exists' => $signature['exists'],
                            'valid' => $signatureIssues === [],
                            'issues' => $signatureIssues,
                        ];
                    }

                    if ($signatures === []) {
                        $issues[] = 'missing-digital-signature-signature-relationships';
                    }
                } else {
                    $issues[] = 'missing-digital-signature-origin-relationships';
                }
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'id' => $origin['id'],
                'type' => $origin['type'],
                'target' => $origin['target'],
                'targetPart' => $targetPart,
                'contentType' => $origin['contentType'],
                'external' => $origin['external'],
                'exists' => $origin['exists'],
                'relationshipPartName' => $relationshipPartName,
                'valid' => $issues === [] && array_reduce(
                    $signatures,
                    static fn (bool $valid, array $signature): bool => $valid && $signature['valid'],
                    true
                ),
                'issues' => $issues,
                'signatures' => $signatures,
            ];
        }

        return $preflight;
    }

    /**
     * @return array{valid:bool, originCount:int, signatureCount:int, allowedSignatureSources:list<string>, roles:list<array{source:string, id:string, role:string, type:string, target:string, targetPart:?string, contentType:?string, expectedSource:?string, allowedSignatureSources:list<string>, sourceAllowed:bool, expectedContentType:string, expectedExternal:bool, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, valid:bool, issues:list<string>}>}
     */
    public function preflightDigitalSignatureRelationshipRoles(): array
    {
        $allowedSignatureSources = [];
        foreach ($this->preflightTargetsForSource('/', self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE) as $origin) {
            $targetPart = self::targetPartFromPreflightTarget($origin);
            if (
                $origin['external']
                || $targetPart === null
                || $origin['exists'] !== true
                || !$origin['valid']
                || !self::contentTypeMatches($origin['contentType'], self::DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE)
            ) {
                continue;
            }

            self::appendUniqueString($allowedSignatureSources, $targetPart);
        }
        sort($allowedSignatureSources, SORT_STRING);

        $roles = [];
        $originCount = 0;
        $signatureCount = 0;
        foreach ($this->sourcePartNames() as $sourcePartName) {
            foreach ($this->preflightTargetsForSource($sourcePartName) as $target) {
                if (
                    $target['type'] !== self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE
                    && $target['type'] !== self::DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE
                ) {
                    continue;
                }

                $issues = $target['issues'];
                $targetPart = self::targetPartFromPreflightTarget($target);
                if ($target['type'] === self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE) {
                    $originCount++;
                    $role = 'digital-signature-origin';
                    $expectedSource = '/';
                    $sourceAllowed = $sourcePartName === '/';
                    $expectedContentType = self::DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE;
                    if (!$sourceAllowed) {
                        $issues[] = 'digital-signature-origin-source-not-package-root';
                    }

                    if ($target['external']) {
                        $issues[] = 'external-digital-signature-origin';
                    }

                    if (
                        !$target['external']
                        && $target['contentType'] !== null
                        && !self::contentTypeMatches($target['contentType'], $expectedContentType)
                    ) {
                        $issues[] = 'invalid-digital-signature-origin-content-type';
                    }
                } else {
                    $signatureCount++;
                    $role = 'digital-signature-signature';
                    $expectedSource = null;
                    $sourceAllowed = in_array($sourcePartName, $allowedSignatureSources, true);
                    $expectedContentType = self::DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE;
                    if (!$sourceAllowed) {
                        $issues[] = 'digital-signature-signature-source-not-origin';
                    }

                    if ($target['external']) {
                        $issues[] = 'external-digital-signature-target';
                    }

                    if (
                        !$target['external']
                        && $target['contentType'] !== null
                        && !self::contentTypeMatches($target['contentType'], $expectedContentType)
                    ) {
                        $issues[] = 'invalid-digital-signature-content-type';
                    }
                }

                $issues = array_values(array_unique($issues));
                $roles[] = [
                    'source' => $sourcePartName,
                    'id' => $target['id'],
                    'role' => $role,
                    'type' => $target['type'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $target['contentType'],
                    'expectedSource' => $expectedSource,
                    'allowedSignatureSources' => $allowedSignatureSources,
                    'sourceAllowed' => $sourceAllowed,
                    'expectedContentType' => $expectedContentType,
                    'expectedExternal' => false,
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
            }
        }

        return [
            'valid' => array_reduce(
                $roles,
                static fn (bool $valid, array $role): bool => $valid && $role['valid'],
                true,
            ),
            'originCount' => $originCount,
            'signatureCount' => $signatureCount,
            'allowedSignatureSources' => $allowedSignatureSources,
            'roles' => $roles,
        ];
    }

    /**
     * @return array{signaturePart:string, contentType:?string, expectedContentType:string, objectCount:int, objectIds:list<string>, duplicateObjectIds:list<string>, certificateCount:int, valid:bool, issues:list<string>, objects:list<array<string, mixed>>, certificates:list<array{index:int, base64Length:int, decodedBytes:?int, sha256:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightDigitalSignatureMetadata(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        if (!$this->package->has($signaturePartName)) {
            throw new \RuntimeException('OPC signature part not found: ' . $signaturePartName);
        }

        $contentType = $this->contentTypes->contentTypeForPart($signaturePartName);
        $issues = [];
        if ($contentType !== null && !self::contentTypeMatches($contentType, self::DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE)) {
            $issues[] = 'invalid-digital-signature-content-type';
        }

        $dom = XmlHtmlDom::loadXmlDocument($this->package->read($signaturePartName), 'OPC digital signature XML');
        $root = $dom->documentElement;
        if (
            !$root instanceof \DOMElement
            || $root->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
            || $root->localName !== 'Signature'
        ) {
            $issues[] = 'missing-xml-signature-root';
        }

        $objects = [];
        $relationshipTransformTargetIndex = $root instanceof \DOMElement
            ? self::signatureRelationshipTransformTargetIndex($this->preflightSignatureRelationshipTransforms($signaturePartName))
            : [];
        $sameDocumentIdIndex = $root instanceof \DOMElement
            ? self::xmlSignatureSameDocumentIdIndex($root)
            : [];
        $signatureObjectIdCounts = $root instanceof \DOMElement
            ? self::directXmlSignatureObjectIdCounts($root)
            : [];
        $objectIds = array_keys($signatureObjectIdCounts);
        sort($objectIds, SORT_STRING);
        $duplicateObjectIds = array_keys(array_filter(
            $signatureObjectIdCounts,
            static fn (int $count): bool => $count > 1,
        ));
        sort($duplicateObjectIds, SORT_STRING);
        if ($root instanceof \DOMElement) {
            foreach ($root->childNodes as $child) {
                if (
                    $child instanceof \DOMElement
                    && $child->namespaceURI === self::XML_SIGNATURE_NAMESPACE_URI
                    && $child->localName === 'Object'
                ) {
                    $objectMetadata = self::digitalSignatureObjectMetadata(
                        $child,
                        $signaturePartName,
                        $this->package,
                        $this->contentTypes,
                        $relationshipTransformTargetIndex,
                        $sameDocumentIdIndex,
                        $signatureObjectIdCounts,
                    );
                    foreach ($objectMetadata['issues'] as $issue) {
                        self::appendUniqueString($issues, $issue);
                    }
                    $objects[] = $objectMetadata;
                }
            }
        }

        $certificates = [];
        $certificateIndex = 0;
        foreach ($dom->getElementsByTagNameNS(self::XML_SIGNATURE_NAMESPACE_URI, 'X509Certificate') as $certificate) {
            if (!$certificate instanceof \DOMElement) {
                continue;
            }

            $certificateMetadata = self::x509CertificateMetadata($certificate, $certificateIndex);
            foreach ($certificateMetadata['issues'] as $issue) {
                self::appendUniqueString($issues, $issue);
            }
            $certificates[] = $certificateMetadata;
            $certificateIndex++;
        }

        return [
            'signaturePart' => $signaturePartName,
            'contentType' => $contentType,
            'expectedContentType' => self::DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE,
            'objectCount' => count($objects),
            'objectIds' => $objectIds,
            'duplicateObjectIds' => $duplicateObjectIds,
            'certificateCount' => count($certificates),
            'valid' => $issues === [],
            'issues' => array_values(array_unique($issues)),
            'objects' => $objects,
            'certificates' => $certificates,
        ];
    }

    /**
     * @return list<array{signaturePart:string, referenceIndex:int, uri:?string, targetPart:?string, exists:?bool, contentType:?string, sameDocumentReference:bool, sameDocumentFragment:?string, sameDocumentTargetMatched:bool, sameDocumentTargetMatchCount:int, sameDocumentTargetMatchedElementNames:list<string>, relationshipPart:bool, referenceContentType:?string, referenceContentTypeMatches:?bool, transformAlgorithms:list<string>, relationshipTransformIndexes:list<int>, canonicalizationTransformIndexes:list<int>, relationshipTransformCount:int, canonicalizationTransformCount:int, canonicalizationTransformAlgorithms:list<string>, canonicalizationTransforms:list<array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}>, relationshipTransformFollowingCanonicalization:?array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}, relationshipTransformFollowedByCanonicalization:?bool, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValue:?string, digestValueBase64Length:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>, parseError:?string}>
     */
    public function preflightDigitalSignatureSignedInfoReferences(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        if (!$this->package->has($signaturePartName)) {
            throw new \RuntimeException('OPC signature part not found: ' . $signaturePartName);
        }

        $dom = XmlHtmlDom::loadXmlDocument($this->package->read($signaturePartName), 'OPC digital signature XML');
        $root = $dom->documentElement;
        if (
            !$root instanceof \DOMElement
            || $root->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
            || $root->localName !== 'Signature'
        ) {
            return [];
        }

        $signedInfo = self::firstChildElementByNamespace($root, self::XML_SIGNATURE_NAMESPACE_URI, 'SignedInfo');
        if (!$signedInfo instanceof \DOMElement) {
            return [];
        }

        $sameDocumentIdIndex = self::xmlSignatureSameDocumentIdIndex($root);
        $references = [];
        foreach ($signedInfo->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
                || $child->localName !== 'Reference'
            ) {
                continue;
            }

            $references[] = self::digitalSignatureSignedInfoReferenceMetadata(
                $child,
                count($references),
                $signaturePartName,
                $this->package,
                $this->contentTypes,
                $sameDocumentIdIndex,
            );
        }

        return $references;
    }

    /**
     * @return list<array{source:string, id:string, role:string, type:string, target:string, targetPart:?string, contentType:?string, expectedSource:string, sourceAllowed:bool, expectedContentType:string, expectedExternal:bool, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightEncryptedPackages(?string $sourcePartName = null): array
    {
        $sources = $sourcePartName === null
            ? $this->sourcePartNames()
            : [$this->relationshipSourceNameForEquivalent($sourcePartName)];

        $preflight = [];
        foreach ($sources as $source) {
            foreach ($this->preflightTargetsForSource($source, self::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE) as $target) {
                $sourceAllowed = $source === '/';
                $issues = $target['issues'];

                if (!$sourceAllowed) {
                    $issues[] = 'encrypted-package-source-not-package-root';
                }

                if ($target['external']) {
                    $issues[] = 'external-encrypted-package-target';
                }

                if (
                    !$target['external']
                    && $target['contentType'] !== null
                    && !self::contentTypeMatches($target['contentType'], self::ENCRYPTED_PACKAGE_CONTENT_TYPE)
                ) {
                    $issues[] = 'invalid-encrypted-package-content-type';
                }

                $issues = array_values(array_unique($issues));
                $preflight[] = [
                    'source' => $source,
                    'id' => $target['id'],
                    'role' => 'encrypted-package',
                    'type' => $target['type'],
                    'target' => $target['target'],
                    'targetPart' => self::targetPartFromPreflightTarget($target),
                    'contentType' => $target['contentType'],
                    'expectedSource' => '/',
                    'sourceAllowed' => $sourceAllowed,
                    'expectedContentType' => self::ENCRYPTED_PACKAGE_CONTENT_TYPE,
                    'expectedExternal' => false,
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, kind:string, target:string, targetPart:?string, contentType:?string, expectedContentType:string, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightEmbeddedPackages(string $sourcePartName = '/'): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $preflight = [];

        foreach ($this->preflightTargetsForSource($sourcePartName) as $target) {
            $kind = null;
            $expectedContentType = null;
            $invalidContentTypeIssue = null;

            if ($target['type'] === self::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE) {
                $kind = 'embedded-package';
                $expectedContentType = self::EMBEDDED_PACKAGE_CONTENT_TYPE;
                $invalidContentTypeIssue = 'invalid-embedded-package-content-type';
            } elseif ($target['type'] === self::EMBEDDED_OBJECT_RELATIONSHIP_TYPE) {
                $kind = 'embedded-object';
                $expectedContentType = self::EMBEDDED_OBJECT_CONTENT_TYPE;
                $invalidContentTypeIssue = 'invalid-embedded-object-content-type';
            } else {
                continue;
            }

            $issues = $target['issues'];
            if (!$target['external'] && $target['contentType'] !== null && !self::contentTypeMatches($target['contentType'], $expectedContentType)) {
                $issues[] = $invalidContentTypeIssue;
            }
            $issues = array_values(array_unique($issues));

            $preflight[] = [
                'source' => $sourcePartName,
                'id' => $target['id'],
                'type' => $target['type'],
                'kind' => $kind,
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'expectedContentType' => $expectedContentType,
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, expanded:bool, nestedPackagePartCount:?int, nestedRelationshipSourceCount:?int, nestedSourcePartNames:list<string>, nestedOfficeDocument:?array{relationshipCount:int, expectedContentTypes:list<string>, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}, nestedRelationshipClosure:?array<string,mixed>, parseError:?string, valid:bool, issues:list<string>}>
     */
    public function preflightEmbeddedPackageGraphs(string $sourcePartName = '/'): array
    {
        $preflight = [];

        foreach ($this->preflightEmbeddedPackages($sourcePartName) as $embeddedPackage) {
            if ($embeddedPackage['kind'] !== 'embedded-package') {
                continue;
            }

            $issues = $embeddedPackage['issues'];
            $expanded = false;
            $nestedPackagePartCount = null;
            $nestedRelationshipSourceCount = null;
            $nestedSourcePartNames = [];
            $nestedOfficeDocument = null;
            $nestedRelationshipClosure = null;
            $parseError = null;

            if ($embeddedPackage['external']) {
                $issues[] = 'external-embedded-package-not-expanded';
            } elseif (
                $embeddedPackage['valid']
                && $embeddedPackage['targetPart'] !== null
                && $embeddedPackage['exists'] === true
            ) {
                try {
                    $nestedPackage = ZipPackage::fromString($this->package->read($embeddedPackage['targetPart']));
                    $nestedGraph = self::fromPackage($nestedPackage);
                    $nestedSourcePartNames = $nestedGraph->sourcePartNames();
                    $nestedOfficeDocument = $nestedGraph->preflightOfficeDocumentRoot();
                    $nestedRelationshipClosure = $nestedGraph->relationshipSourceClosureInventory(
                        '/',
                        self::OFFICE_DOCUMENT_RELATIONSHIP_TYPE,
                    );
                    $nestedPackagePartCount = count($nestedPackage->names());
                    $nestedRelationshipSourceCount = count($nestedSourcePartNames);
                    $expanded = true;

                    if (!$nestedOfficeDocument['valid']) {
                        $issues[] = 'embedded-office-document-root-invalid';
                    }
                    foreach ($nestedRelationshipClosure['issues'] as $issue) {
                        $issues[] = 'embedded-' . $issue;
                    }
                } catch (\Throwable $exception) {
                    $issues[] = 'embedded-package-parse-error';
                    $parseError = $exception->getMessage();
                }
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'source' => $embeddedPackage['source'],
                'id' => $embeddedPackage['id'],
                'type' => $embeddedPackage['type'],
                'target' => $embeddedPackage['target'],
                'targetPart' => $embeddedPackage['targetPart'],
                'contentType' => $embeddedPackage['contentType'],
                'external' => $embeddedPackage['external'],
                'exists' => $embeddedPackage['exists'],
                'expanded' => $expanded,
                'nestedPackagePartCount' => $nestedPackagePartCount,
                'nestedRelationshipSourceCount' => $nestedRelationshipSourceCount,
                'nestedSourcePartNames' => $nestedSourcePartNames,
                'nestedOfficeDocument' => $nestedOfficeDocument,
                'nestedRelationshipClosure' => $nestedRelationshipClosure,
                'parseError' => $parseError,
                'valid' => $expanded && $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return array{source:string, valid:bool, embeddedPackageCount:int, validPackageCount:int, invalidPackageCount:int, expandedCount:int, blockedCount:int, externalCount:int, missingTargetCount:int, parseErrorCount:int, nestedPackagePartCount:int, nestedRelationshipSourceCount:int, nestedRelationshipStopCount:int, nestedMissingStopCount:int, nestedExternalStopCount:int, nestedUnloadedStopCount:int, nestedInvalidStopCount:int, nestedOfficeDocumentInvalidCount:int, expandedIds:list<string>, blockedIds:list<string>, externalIds:list<string>, missingTargetParts:list<string>, parseErrorIds:list<string>, nestedInvalidOfficeDocumentIds:list<string>, issueCounts:array<string,int>, issues:list<string>, packages:list<array{id:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, expanded:bool, nestedPackagePartCount:?int, nestedRelationshipSourceCount:?int, nestedRelationshipStopCount:?int, nestedRelationshipIssues:list<string>, valid:bool, issues:list<string>}>}
     */
    public function embeddedPackageGraphSummary(string $sourcePartName = '/'): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $summary = [
            'source' => $sourcePartName,
            'valid' => true,
            'embeddedPackageCount' => 0,
            'validPackageCount' => 0,
            'invalidPackageCount' => 0,
            'expandedCount' => 0,
            'blockedCount' => 0,
            'externalCount' => 0,
            'missingTargetCount' => 0,
            'parseErrorCount' => 0,
            'nestedPackagePartCount' => 0,
            'nestedRelationshipSourceCount' => 0,
            'nestedRelationshipStopCount' => 0,
            'nestedMissingStopCount' => 0,
            'nestedExternalStopCount' => 0,
            'nestedUnloadedStopCount' => 0,
            'nestedInvalidStopCount' => 0,
            'nestedOfficeDocumentInvalidCount' => 0,
            'expandedIds' => [],
            'blockedIds' => [],
            'externalIds' => [],
            'missingTargetParts' => [],
            'parseErrorIds' => [],
            'nestedInvalidOfficeDocumentIds' => [],
            'issueCounts' => [],
            'issues' => [],
            'packages' => [],
        ];

        foreach ($this->preflightEmbeddedPackageGraphs($sourcePartName) as $embeddedPackage) {
            $summary['embeddedPackageCount']++;
            $nestedClosure = is_array($embeddedPackage['nestedRelationshipClosure'] ?? null)
                ? $embeddedPackage['nestedRelationshipClosure']
                : null;
            $nestedRelationshipIssues = $nestedClosure !== null && is_array($nestedClosure['issues'] ?? null)
                ? $nestedClosure['issues']
                : [];

            if ($embeddedPackage['valid']) {
                $summary['validPackageCount']++;
            } else {
                $summary['invalidPackageCount']++;
                $summary['valid'] = false;
            }

            if ($embeddedPackage['expanded']) {
                $summary['expandedCount']++;
                $summary['expandedIds'][] = $embeddedPackage['id'];
            } else {
                $summary['blockedCount']++;
                $summary['blockedIds'][] = $embeddedPackage['id'];
            }

            if ($embeddedPackage['external']) {
                $summary['externalCount']++;
                $summary['externalIds'][] = $embeddedPackage['id'];
            }

            if ($embeddedPackage['exists'] === false && is_string($embeddedPackage['targetPart'] ?? null)) {
                $summary['missingTargetCount']++;
                self::appendUniqueString($summary['missingTargetParts'], $embeddedPackage['targetPart']);
            }

            if (($embeddedPackage['parseError'] ?? null) !== null) {
                $summary['parseErrorCount']++;
                $summary['parseErrorIds'][] = $embeddedPackage['id'];
            }

            if (is_int($embeddedPackage['nestedPackagePartCount'] ?? null)) {
                $summary['nestedPackagePartCount'] += $embeddedPackage['nestedPackagePartCount'];
            }

            if (is_int($embeddedPackage['nestedRelationshipSourceCount'] ?? null)) {
                $summary['nestedRelationshipSourceCount'] += $embeddedPackage['nestedRelationshipSourceCount'];
            }

            if ($nestedClosure !== null) {
                $summary['nestedRelationshipStopCount'] += (int) ($nestedClosure['stopCount'] ?? 0);
                $summary['nestedMissingStopCount'] += (int) ($nestedClosure['missingStopCount'] ?? 0);
                $summary['nestedExternalStopCount'] += (int) ($nestedClosure['externalStopCount'] ?? 0);
                $summary['nestedUnloadedStopCount'] += (int) ($nestedClosure['unloadedStopCount'] ?? 0);
                $summary['nestedInvalidStopCount'] += (int) ($nestedClosure['invalidStopCount'] ?? 0);
            }

            if (
                is_array($embeddedPackage['nestedOfficeDocument'] ?? null)
                && ($embeddedPackage['nestedOfficeDocument']['valid'] ?? true) !== true
            ) {
                $summary['nestedOfficeDocumentInvalidCount']++;
                $summary['nestedInvalidOfficeDocumentIds'][] = $embeddedPackage['id'];
            }

            foreach ($embeddedPackage['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }

            $summary['packages'][] = [
                'id' => $embeddedPackage['id'],
                'target' => $embeddedPackage['target'],
                'targetPart' => $embeddedPackage['targetPart'],
                'contentType' => $embeddedPackage['contentType'],
                'external' => $embeddedPackage['external'],
                'exists' => $embeddedPackage['exists'],
                'expanded' => $embeddedPackage['expanded'],
                'nestedPackagePartCount' => $embeddedPackage['nestedPackagePartCount'],
                'nestedRelationshipSourceCount' => $embeddedPackage['nestedRelationshipSourceCount'],
                'nestedRelationshipStopCount' => $nestedClosure['stopCount'] ?? null,
                'nestedRelationshipIssues' => $nestedRelationshipIssues,
                'valid' => $embeddedPackage['valid'],
                'issues' => $embeddedPackage['issues'],
            ];
        }

        foreach ([
            'expandedIds',
            'blockedIds',
            'externalIds',
            'missingTargetParts',
            'parseErrorIds',
            'nestedInvalidOfficeDocumentIds',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }
        ksort($summary['issueCounts'], SORT_STRING);
        usort(
            $summary['packages'],
            static fn (array $left, array $right): int => $left['id'] <=> $right['id'],
        );

        return $summary;
    }

    /**
     * @param list<string> $sourceIds
     * @param list<string> $sourceTypes
     * @return array{source:string, sourceIds:list<string>, sourceTypes:list<string>, invalidSourceTypes:list<string>, sourceTypeIssues:array<string, list<string>>, unmatchedSourceIds:list<string>, unmatchedSourceTypes:list<string>, selectorOverlappingRelationshipIds:list<string>, selectorOverlapCount:int, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightRelationshipSelector(string $sourcePartName = '/', array $sourceIds = [], array $sourceTypes = []): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $sourceIds = self::normalizeSelectorValues($sourceIds, 'OPC relationship selector SourceId');
        $sourceTypes = self::normalizeSelectorValues($sourceTypes, 'OPC relationship selector SourceType');

        foreach ($sourceIds as $sourceId) {
            self::assertSelectorSourceId($sourceId);
        }

        $sourceTypeIssues = [];
        foreach ($sourceTypes as $sourceType) {
            $issuesForSourceType = self::selectorSourceTypeIssues($sourceType);
            if ($issuesForSourceType !== []) {
                $sourceTypeIssues[$sourceType] = $issuesForSourceType;
            }
        }
        $invalidSourceTypes = array_keys($sourceTypeIssues);
        $validSourceTypes = array_values(array_filter(
            $sourceTypes,
            static fn (string $sourceType): bool => !isset($sourceTypeIssues[$sourceType]),
        ));

        $targets = $this->preflightTargetsForSource($sourcePartName);
        $knownIds = [];
        $knownTypes = [];
        $relationships = [];
        $selectorOverlappingRelationshipIds = [];

        foreach ($targets as $target) {
            $knownIds[$target['id']] = true;
            $knownTypes[$target['type']] = true;

            $selectedBySourceId = in_array($target['id'], $sourceIds, true);
            $selectedBySourceType = in_array($target['type'], $validSourceTypes, true);
            if (!$selectedBySourceId && !$selectedBySourceType) {
                continue;
            }

            if ($selectedBySourceId && $selectedBySourceType) {
                $selectorOverlappingRelationshipIds[] = $target['id'];
            }

            $relationships[] = [
                'source' => $sourcePartName,
                'id' => $target['id'],
                'type' => $target['type'],
                'selectedBySourceId' => $selectedBySourceId,
                'selectedBySourceType' => $selectedBySourceType,
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
        }

        $unmatchedSourceIds = array_values(array_filter(
            $sourceIds,
            static fn (string $sourceId): bool => !isset($knownIds[$sourceId]),
        ));
        $unmatchedSourceTypes = array_values(array_filter(
            $sourceTypes,
            static fn (string $sourceType): bool => !isset($knownTypes[$sourceType])
                && !isset($sourceTypeIssues[$sourceType]),
        ));

        $issues = [];
        if (!isset($this->relationshipsBySource[$sourcePartName])) {
            $issues[] = 'relationship-source-not-loaded';
        }

        if ($sourceIds === [] && $sourceTypes === []) {
            $issues[] = 'empty-relationship-selector';
        }

        if ($unmatchedSourceIds !== []) {
            $issues[] = 'unmatched-source-id';
        }

        if ($invalidSourceTypes !== []) {
            $issues[] = 'invalid-source-type';
        }

        if ($unmatchedSourceTypes !== []) {
            $issues[] = 'unmatched-source-type';
        }

        sort($selectorOverlappingRelationshipIds, SORT_STRING);
        $relationshipsValid = array_reduce(
            $relationships,
            static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
            true,
        );

        return [
            'source' => $sourcePartName,
            'sourceIds' => $sourceIds,
            'sourceTypes' => $sourceTypes,
            'invalidSourceTypes' => $invalidSourceTypes,
            'sourceTypeIssues' => $sourceTypeIssues,
            'unmatchedSourceIds' => $unmatchedSourceIds,
            'unmatchedSourceTypes' => $unmatchedSourceTypes,
            'selectorOverlappingRelationshipIds' => $selectorOverlappingRelationshipIds,
            'selectorOverlapCount' => count($selectorOverlappingRelationshipIds),
            'valid' => $issues === [] && $relationshipsValid,
            'issues' => $issues,
            'relationships' => $relationships,
        ];
    }

    /**
     * @param list<string> $sourceIds
     * @param list<string> $sourceTypes
     * @return array{source:string, relationshipPartName:string, transformAlgorithm:string, sourceIds:list<string>, sourceTypes:list<string>, invalidSourceTypes:list<string>, sourceTypeIssues:array<string, list<string>>, selectorOverlappingRelationshipIds:list<string>, selectorOverlapCount:int, relationshipIds:list<string>, relationshipCount:int, selectorValid:bool, relationshipTargetsValid:bool, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>, relationshipXml:?string, relationshipXmlBytes:?int, relationshipXmlSha256:?string}
     */
    public function materializeRelationshipTransform(string $sourcePartName = '/', array $sourceIds = [], array $sourceTypes = []): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $selector = $this->preflightRelationshipSelector($sourcePartName, $sourceIds, $sourceTypes);
        $relationships = $this->relationshipsBySource[$sourcePartName] ?? null;
        $validSourceTypes = array_values(array_filter(
            $selector['sourceTypes'],
            static fn (string $sourceType): bool => !isset($selector['sourceTypeIssues'][$sourceType]),
        ));

        $selectedForTransform = [];
        if ($relationships instanceof OpcRelationships) {
            foreach ($relationships->all() as $relationship) {
                if (
                    in_array($relationship->id, $selector['sourceIds'], true)
                    || in_array($relationship->type, $validSourceTypes, true)
                ) {
                    $selectedForTransform[] = $relationship;
                }
            }
        }

        usort(
            $selectedForTransform,
            static fn (OpcRelationship $left, OpcRelationship $right): int => strcmp($left->id, $right->id),
        );

        $relationshipTargetsValid = array_reduce(
            $selector['relationships'],
            static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
            true,
        );
        $issues = $selector['issues'];
        if (!$relationshipTargetsValid) {
            $issues[] = 'selected-relationship-target-issues';
        }

        $relationshipXml = $relationships instanceof OpcRelationships
            ? self::relationshipTransformXml($selectedForTransform)
            : null;

        return [
            'source' => $sourcePartName,
            'relationshipPartName' => OpcRelationships::relationshipPartNameForSource($sourcePartName),
            'transformAlgorithm' => self::RELATIONSHIP_TRANSFORM_ALGORITHM,
            'sourceIds' => $selector['sourceIds'],
            'sourceTypes' => $selector['sourceTypes'],
            'invalidSourceTypes' => $selector['invalidSourceTypes'],
            'sourceTypeIssues' => $selector['sourceTypeIssues'],
            'selectorOverlappingRelationshipIds' => $selector['selectorOverlappingRelationshipIds'],
            'selectorOverlapCount' => $selector['selectorOverlapCount'],
            'relationshipIds' => array_map(
                static fn (OpcRelationship $relationship): string => $relationship->id,
                $selectedForTransform,
            ),
            'relationshipCount' => count($selectedForTransform),
            'selectorValid' => $selector['valid'],
            'relationshipTargetsValid' => $relationshipTargetsValid,
            'valid' => $issues === [],
            'issues' => array_values(array_unique($issues)),
            'relationships' => $selector['relationships'],
            'relationshipXml' => $relationshipXml,
            'relationshipXmlBytes' => $relationshipXml === null ? null : strlen($relationshipXml),
            'relationshipXmlSha256' => $relationshipXml === null ? null : hash('sha256', $relationshipXml),
        ];
    }

    /**
     * @return list<array{signaturePart:string, referenceIndex:int, referenceUri:string, relationshipPartName:?string, referenceRelationshipPartExists:?bool, referenceTargetContentType:?string, referenceContentType:?string, referenceContentTypeMatches:?bool, source:?string, transformAlgorithm:string, sourceIds:list<string>, sourceTypes:list<string>, duplicateSourceIds:list<string>, duplicateSourceTypes:list<string>, selectorDuplicateSourceIdCount:int, selectorDuplicateSourceTypeCount:int, selectorOverlappingRelationshipIds:list<string>, selectorOverlapCount:int, invalidSourceTypes:list<string>, sourceTypeIssues:array<string, list<string>>, selectorChildCount:int, selectorRelationshipReferenceCount:int, selectorRelationshipGroupReferenceCount:int, selectorUnsupportedChildCount:int, selectorUnsupportedContentCount:int, followingCanonicalizationAlgorithm:?string, followingCanonicalization:?array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}, followedByCanonicalization:bool, relationshipIds:list<string>, relationshipCount:int, selectorValid:?bool, relationshipTargetsValid:?bool, valid:bool, issues:list<string>, parseError:?string, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>, relationshipXml:?string, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>
     */
    public function preflightSignatureRelationshipTransforms(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        if (!$this->package->has($signaturePartName)) {
            throw new \RuntimeException('OPC signature part not found: ' . $signaturePartName);
        }

        $dom = XmlHtmlDom::loadXmlDocument($this->package->read($signaturePartName), 'OPC digital signature XML');
        $rows = [];
        $rowsByRelationshipPart = [];
        $referenceIndex = -1;

        foreach ($dom->getElementsByTagNameNS(self::XML_SIGNATURE_NAMESPACE_URI, 'Reference') as $reference) {
            if (!$reference instanceof \DOMElement) {
                continue;
            }

            $referenceIndex++;
            $referenceUri = $reference->hasAttribute('URI') ? $reference->getAttribute('URI') : '';
            $transforms = self::xmlSignatureReferenceTransforms($reference);
            $transformAlgorithms = [];
            foreach ($transforms as $referenceTransform) {
                $transformAlgorithms[] = $referenceTransform->hasAttribute('Algorithm')
                    ? trim($referenceTransform->getAttribute('Algorithm'))
                    : '';
            }
            $hasEnvelopedSignatureTransform = in_array(
                self::XML_SIGNATURE_ENVELOPED_SIGNATURE_TRANSFORM_ALGORITHM,
                $transformAlgorithms,
                true,
            );

            foreach ($transforms as $transformIndex => $transform) {
                if ($transform->getAttribute('Algorithm') !== self::RELATIONSHIP_TRANSFORM_ALGORITHM) {
                    continue;
                }

                $relationshipPartName = null;
                $referenceRelationshipPartExists = null;
                $referenceTargetContentType = null;
                $referenceContentType = null;
                $referenceContentTypeMatches = null;
                $sourcePartName = null;
                $parseError = null;
                $issues = [];

                $referenceUriPolicy = self::relationshipTransformReferenceUriPolicy($referenceUri);
                $issues = array_merge($issues, $referenceUriPolicy['issues']);
                if ($parseError === null && $referenceUriPolicy['parseError'] !== null) {
                    $parseError = $referenceUriPolicy['parseError'];
                }
                if ($referenceUriPolicy['resolvable']) {
                    try {
                        $resolvedReference = OpcPackagePath::resolveInternalTarget($signaturePartName, $referenceUri);
                        $relationshipPartName = OpcPackagePath::stripQueryAndFragment($resolvedReference);
                        if (!OpcRelationships::isRelationshipPartName($relationshipPartName)) {
                            $issues[] = 'reference-not-relationship-part';
                        } else {
                            $equivalentRelationshipPartName = $this->packagePartNameForEquivalent($relationshipPartName);
                            $referenceRelationshipPartExists = $equivalentRelationshipPartName !== null;
                            if ($equivalentRelationshipPartName !== null) {
                                $relationshipPartName = $equivalentRelationshipPartName;
                            }
                            if (!$referenceRelationshipPartExists) {
                                $issues[] = 'reference-relationship-part-missing-in-package';
                            }
                            $referenceTargetContentType = $this->contentTypes->contentTypeForPart($relationshipPartName);
                            if ($referenceTargetContentType === null) {
                                $issues[] = 'reference-relationship-content-type-missing';
                            } elseif (!self::contentTypeMatches($referenceTargetContentType, self::RELATIONSHIP_PART_CONTENT_TYPE)) {
                                $issues[] = 'reference-relationship-content-type-invalid';
                            }
                            $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
                            $rowsByRelationshipPart[self::partNameEquivalenceKey($relationshipPartName)][] = count($rows);
                        }
                    } catch (\InvalidArgumentException $exception) {
                        $issues[] = 'invalid-reference-uri';
                        $issues = array_merge($issues, self::relationshipTransformReferenceUriIssues($referenceUri, $exception->getMessage()));
                        $parseError = $exception->getMessage();
                    }
                }

                $referenceContentTypeQuery = self::referenceContentTypeQuery($referenceUri);
                $referenceContentType = $referenceContentTypeQuery['contentType'];
                $issues = array_merge($issues, $referenceContentTypeQuery['issues']);
                if ($parseError === null && $referenceContentTypeQuery['parseError'] !== null) {
                    $parseError = $referenceContentTypeQuery['parseError'];
                }

                if ($referenceUri !== '' && str_contains($referenceUri, '#')) {
                    $issues[] = 'relationship-transform-reference-has-fragment';
                }

                if ($referenceContentType !== null) {
                    $referenceContentTypeMatches = self::contentTypeMatches($referenceTargetContentType, $referenceContentType);
                    if (!$referenceContentTypeMatches) {
                        $issues[] = 'reference-content-type-mismatch';
                    }
                }

                $selector = self::relationshipTransformSelectors($transform);
                $issues = array_merge($issues, $selector['issues']);
                $invalidSourceTypes = $selector['invalidSourceTypes'];
                $sourceTypeIssues = $selector['sourceTypeIssues'];
                $followingCanonicalizationAlgorithm = self::followingTransformAlgorithm($transforms, $transformIndex);
                $followingCanonicalization = self::canonicalizationTransformMetadata($followingCanonicalizationAlgorithm);
                $followedByCanonicalization = $followingCanonicalization !== null;
                if (!$followedByCanonicalization) {
                    $issues[] = 'relationship-transform-not-followed-by-canonicalization';
                }
                if ($hasEnvelopedSignatureTransform) {
                    $issues[] = 'relationship-transform-with-enveloped-signature-transform';
                }

                $relationshipIds = [];
                $relationshipCount = 0;
                $selectorOverlappingRelationshipIds = [];
                $selectorOverlapCount = 0;
                $selectorValid = null;
                $relationshipTargetsValid = null;
                $relationships = [];
                $relationshipXml = null;
                $relationshipXmlBytes = null;
                $relationshipXmlSha256 = null;

                if ($sourcePartName !== null) {
                    try {
                        $materialized = $this->materializeRelationshipTransform(
                            $sourcePartName,
                            $selector['sourceIds'],
                            $selector['sourceTypes'],
                        );
                        $relationshipIds = $materialized['relationshipIds'];
                        $relationshipCount = $materialized['relationshipCount'];
                        $selectorOverlappingRelationshipIds = $materialized['selectorOverlappingRelationshipIds'];
                        $selectorOverlapCount = $materialized['selectorOverlapCount'];
                        $invalidSourceTypes = $materialized['invalidSourceTypes'];
                        $sourceTypeIssues = $materialized['sourceTypeIssues'];
                        $selectorValid = $materialized['selectorValid'];
                        $relationshipTargetsValid = $materialized['relationshipTargetsValid'];
                        $relationships = $materialized['relationships'];
                        $relationshipXml = $materialized['relationshipXml'];
                        $relationshipXmlBytes = $materialized['relationshipXmlBytes'];
                        $relationshipXmlSha256 = $materialized['relationshipXmlSha256'];
                        $issues = array_merge($issues, $materialized['issues']);
                    } catch (\InvalidArgumentException $exception) {
                        $issues[] = 'invalid-relationship-transform-selector';
                        $parseError = $exception->getMessage();
                    }
                }

                $issues = array_values(array_unique($issues));
                $rows[] = [
                    'signaturePart' => $signaturePartName,
                    'referenceIndex' => $referenceIndex,
                    'referenceUri' => $referenceUri,
                    'relationshipPartName' => $relationshipPartName,
                    'referenceRelationshipPartExists' => $referenceRelationshipPartExists,
                    'referenceTargetContentType' => $referenceTargetContentType,
                    'referenceContentType' => $referenceContentType,
                    'referenceContentTypeMatches' => $referenceContentTypeMatches,
                    'source' => $sourcePartName,
                    'transformAlgorithm' => self::RELATIONSHIP_TRANSFORM_ALGORITHM,
                    'sourceIds' => $selector['sourceIds'],
                    'sourceTypes' => $selector['sourceTypes'],
                    'duplicateSourceIds' => $selector['duplicateSourceIds'],
                    'duplicateSourceTypes' => $selector['duplicateSourceTypes'],
                    'selectorDuplicateSourceIdCount' => $selector['selectorDuplicateSourceIdCount'],
                    'selectorDuplicateSourceTypeCount' => $selector['selectorDuplicateSourceTypeCount'],
                    'selectorOverlappingRelationshipIds' => $selectorOverlappingRelationshipIds,
                    'selectorOverlapCount' => $selectorOverlapCount,
                    'invalidSourceTypes' => $invalidSourceTypes,
                    'sourceTypeIssues' => $sourceTypeIssues,
                    'selectorChildCount' => $selector['selectorChildCount'],
                    'selectorRelationshipReferenceCount' => $selector['selectorRelationshipReferenceCount'],
                    'selectorRelationshipGroupReferenceCount' => $selector['selectorRelationshipGroupReferenceCount'],
                    'selectorUnsupportedChildCount' => $selector['selectorUnsupportedChildCount'],
                    'selectorUnsupportedContentCount' => $selector['selectorUnsupportedContentCount'],
                    'followingCanonicalizationAlgorithm' => $followingCanonicalizationAlgorithm,
                    'followingCanonicalization' => $followingCanonicalization,
                    'followedByCanonicalization' => $followedByCanonicalization,
                    'relationshipIds' => $relationshipIds,
                    'relationshipCount' => $relationshipCount,
                    'selectorValid' => $selectorValid,
                    'relationshipTargetsValid' => $relationshipTargetsValid,
                    'valid' => $issues === [],
                    'issues' => $issues,
                    'parseError' => $parseError,
                    'relationships' => $relationships,
                    'relationshipXml' => $relationshipXml,
                    'relationshipXmlBytes' => $relationshipXmlBytes,
                    'relationshipXmlSha256' => $relationshipXmlSha256,
                ];
            }
        }

        foreach ($rowsByRelationshipPart as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            foreach ($rowIndexes as $rowIndex) {
                $rows[$rowIndex]['issues'][] = 'multiple-relationship-transforms-for-part';
                $rows[$rowIndex]['issues'] = array_values(array_unique($rows[$rowIndex]['issues']));
                $rows[$rowIndex]['valid'] = false;
            }
        }

        return $rows;
    }

    /**
     * @return array{signaturePart:string, valid:bool, transformCount:int, validTransformCount:int, invalidTransformCount:int, relationshipPartCount:int, sourceCount:int, selectedRelationshipCount:int, selectedInternalTargetPartCount:int, selectedExternalTargetCount:int, relationshipXmlPayloadCount:int, relationshipPartNames:list<string>, sources:list<string>, selectedRelationshipIds:list<string>, selectedInternalTargetParts:list<string>, selectedExternalTargets:list<string>, invalidReferenceUris:list<string>, invalidRelationshipPartNames:list<string>, relationshipXmlSha256s:list<string>, issueCounts:array<string, int>, issues:list<string>, transforms:list<array{referenceIndex:int, referenceUri:string, relationshipPartName:?string, source:?string, relationshipCount:int, relationshipXmlBytes:?int, relationshipXmlSha256:?string, valid:bool, issues:list<string>}>}
     */
    public function signatureRelationshipTransformSummary(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        $summary = [
            'signaturePart' => $signaturePartName,
            'valid' => true,
            'transformCount' => 0,
            'validTransformCount' => 0,
            'invalidTransformCount' => 0,
            'relationshipPartCount' => 0,
            'sourceCount' => 0,
            'selectedRelationshipCount' => 0,
            'selectedInternalTargetPartCount' => 0,
            'selectedExternalTargetCount' => 0,
            'relationshipXmlPayloadCount' => 0,
            'relationshipPartNames' => [],
            'sources' => [],
            'selectedRelationshipIds' => [],
            'selectedInternalTargetParts' => [],
            'selectedExternalTargets' => [],
            'invalidReferenceUris' => [],
            'invalidRelationshipPartNames' => [],
            'relationshipXmlSha256s' => [],
            'issueCounts' => [],
            'issues' => [],
            'transforms' => [],
        ];

        foreach ($this->preflightSignatureRelationshipTransforms($signaturePartName) as $transform) {
            $summary['transformCount']++;
            if ($transform['valid']) {
                $summary['validTransformCount']++;
            } else {
                $summary['valid'] = false;
                $summary['invalidTransformCount']++;
                self::appendUniqueString($summary['invalidReferenceUris'], $transform['referenceUri']);
                if (
                    $transform['relationshipPartName'] !== null
                    && OpcRelationships::isRelationshipPartName($transform['relationshipPartName'])
                ) {
                    self::appendUniqueString($summary['invalidRelationshipPartNames'], $transform['relationshipPartName']);
                }
            }

            if (
                $transform['relationshipPartName'] !== null
                && OpcRelationships::isRelationshipPartName($transform['relationshipPartName'])
            ) {
                self::appendUniqueString($summary['relationshipPartNames'], $transform['relationshipPartName']);
            }
            if ($transform['source'] !== null) {
                self::appendUniqueString($summary['sources'], $transform['source']);
            }
            if ($transform['relationshipXmlSha256'] !== null) {
                $summary['relationshipXmlPayloadCount']++;
                self::appendUniqueString($summary['relationshipXmlSha256s'], $transform['relationshipXmlSha256']);
            }

            foreach ($transform['relationshipIds'] as $relationshipId) {
                self::appendUniqueString($summary['selectedRelationshipIds'], $relationshipId);
            }
            foreach ($transform['relationships'] as $relationship) {
                if ($relationship['external']) {
                    self::appendUniqueString($summary['selectedExternalTargets'], $relationship['target']);
                } elseif ($relationship['targetPart'] !== null) {
                    self::appendUniqueString($summary['selectedInternalTargetParts'], $relationship['targetPart']);
                }
            }
            foreach ($transform['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
            }

            $summary['transforms'][] = [
                'referenceIndex' => $transform['referenceIndex'],
                'referenceUri' => $transform['referenceUri'],
                'relationshipPartName' => $transform['relationshipPartName'],
                'source' => $transform['source'],
                'relationshipCount' => $transform['relationshipCount'],
                'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
                'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
                'valid' => $transform['valid'],
                'issues' => $transform['issues'],
            ];
        }

        foreach ([
            'relationshipPartNames',
            'sources',
            'selectedRelationshipIds',
            'selectedInternalTargetParts',
            'selectedExternalTargets',
            'invalidReferenceUris',
            'invalidRelationshipPartNames',
            'relationshipXmlSha256s',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey]);
        }
        ksort($summary['issueCounts']);

        $summary['relationshipPartCount'] = count($summary['relationshipPartNames']);
        $summary['sourceCount'] = count($summary['sources']);
        $summary['selectedRelationshipCount'] = count($summary['selectedRelationshipIds']);
        $summary['selectedInternalTargetPartCount'] = count($summary['selectedInternalTargetParts']);
        $summary['selectedExternalTargetCount'] = count($summary['selectedExternalTargets']);

        return $summary;
    }

    /**
     * @param list<string> $allowedRelationshipTypes
     * @return array{signaturePart:string, valid:bool, allowedRelationshipTypes:list<string>, transformCount:int, selectedRelationshipCount:int, allowedRelationshipCount:int, disallowedRelationshipCount:int, externalRelationshipCount:int, internalRelationshipCount:int, invalidRelationshipCount:int, unsafeExternalRelationshipCount:int, missingTargetRelationshipCount:int, selectedRelationshipIds:list<string>, selectedRelationshipTypes:list<string>, disallowedRelationshipTypes:list<string>, selectedInternalTargetParts:list<string>, selectedExternalTargets:list<string>, issueCounts:array<string, int>, issues:list<string>, disallowedRelationships:list<array{source:string, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, selectedBySourceId:bool, selectedBySourceType:bool, allowedType:bool, externalTargetAllowed:?bool, valid:bool, issues:list<string>, policyIssues:list<string>}>, invalidRelationships:list<array{source:string, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, selectedBySourceId:bool, selectedBySourceType:bool, allowedType:bool, externalTargetAllowed:?bool, valid:bool, issues:list<string>, policyIssues:list<string>}>, transforms:list<array{referenceIndex:int, referenceUri:string, relationshipPartName:?string, source:?string, selectedRelationshipCount:int, disallowedRelationshipCount:int, invalidRelationshipCount:int, valid:bool, issues:list<string>}>}
     */
    public function signedRelationshipPolicySummary(string $signaturePartName, array $allowedRelationshipTypes): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        $allowedRelationshipTypes = array_values(array_unique(array_map(
            static function (string $relationshipType): string {
                return trim($relationshipType);
            },
            $allowedRelationshipTypes,
        )));
        $allowedRelationshipTypes = array_values(array_filter(
            $allowedRelationshipTypes,
            static fn (string $relationshipType): bool => $relationshipType !== '',
        ));

        $typeRank = static fn (string $relationshipType): array => match ($relationshipType) {
            self::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE => [0, $relationshipType],
            self::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE => [1, $relationshipType],
            self::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE => [2, $relationshipType],
            default => [3, $relationshipType],
        };
        $sortTypes = static function (array &$relationshipTypes) use ($typeRank): void {
            usort(
                $relationshipTypes,
                static fn (string $left, string $right): int => $typeRank($left) <=> $typeRank($right),
            );
        };
        $sortTypes($allowedRelationshipTypes);
        $allowedRelationshipTypeIndex = array_fill_keys($allowedRelationshipTypes, true);

        $summary = [
            'signaturePart' => $signaturePartName,
            'valid' => true,
            'allowedRelationshipTypes' => $allowedRelationshipTypes,
            'transformCount' => 0,
            'selectedRelationshipCount' => 0,
            'allowedRelationshipCount' => 0,
            'disallowedRelationshipCount' => 0,
            'externalRelationshipCount' => 0,
            'internalRelationshipCount' => 0,
            'invalidRelationshipCount' => 0,
            'unsafeExternalRelationshipCount' => 0,
            'missingTargetRelationshipCount' => 0,
            'selectedRelationshipIds' => [],
            'selectedRelationshipTypes' => [],
            'disallowedRelationshipTypes' => [],
            'selectedInternalTargetParts' => [],
            'selectedExternalTargets' => [],
            'issueCounts' => [],
            'issues' => [],
            'disallowedRelationships' => [],
            'invalidRelationships' => [],
            'transforms' => [],
        ];

        foreach ($this->preflightSignatureRelationshipTransforms($signaturePartName) as $transform) {
            $summary['transformCount']++;
            $transformIssues = [];
            foreach ($transform['issues'] as $issue) {
                $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                self::appendUniqueString($summary['issues'], $issue);
                self::appendUniqueString($transformIssues, $issue);
            }

            $transformDisallowedRelationshipCount = 0;
            $transformInvalidRelationshipCount = 0;
            foreach ($transform['relationships'] as $relationship) {
                self::appendUniqueString($summary['selectedRelationshipIds'], $relationship['id']);
                self::appendUniqueString($summary['selectedRelationshipTypes'], $relationship['type']);

                $allowedType = $allowedRelationshipTypes === []
                    || isset($allowedRelationshipTypeIndex[$relationship['type']]);
                if ($allowedType) {
                    $summary['allowedRelationshipCount']++;
                } else {
                    $summary['disallowedRelationshipCount']++;
                    $transformDisallowedRelationshipCount++;
                    self::appendUniqueString($summary['disallowedRelationshipTypes'], $relationship['type']);
                }

                if ($relationship['external']) {
                    $summary['externalRelationshipCount']++;
                    self::appendUniqueString($summary['selectedExternalTargets'], $relationship['target']);
                } else {
                    $summary['internalRelationshipCount']++;
                    if ($relationship['targetPart'] !== null) {
                        self::appendUniqueString($summary['selectedInternalTargetParts'], $relationship['targetPart']);
                    }
                }

                if (!$relationship['valid']) {
                    $summary['invalidRelationshipCount']++;
                    $transformInvalidRelationshipCount++;
                }

                if ($relationship['external'] && $relationship['externalTargetAllowed'] === false) {
                    $summary['unsafeExternalRelationshipCount']++;
                }
                if (in_array('missing-in-package', $relationship['issues'], true)) {
                    $summary['missingTargetRelationshipCount']++;
                }

                $policyIssues = [];
                if ($relationship['external']) {
                    $policyIssues[] = 'external-signed-relationship';
                }
                if (!$allowedType) {
                    $policyIssues[] = 'signed-relationship-type-not-allowed';
                }
                if ($relationship['external'] && $relationship['externalTargetAllowed'] === false) {
                    $policyIssues[] = 'unsafe-external-signed-relationship';
                }
                foreach ($relationship['issues'] as $issue) {
                    if ($issue === 'external-target-unsafe-scheme') {
                        continue;
                    }
                    self::appendUniqueString($policyIssues, $issue);
                }
                sort($policyIssues, SORT_STRING);

                foreach ($policyIssues as $issue) {
                    $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
                    self::appendUniqueString($summary['issues'], $issue);
                    self::appendUniqueString($transformIssues, $issue);
                }

                $relationshipRow = [
                    'source' => $relationship['source'],
                    'id' => $relationship['id'],
                    'type' => $relationship['type'],
                    'target' => $relationship['target'],
                    'targetPart' => $relationship['targetPart'],
                    'contentType' => $relationship['contentType'],
                    'external' => $relationship['external'],
                    'selectedBySourceId' => $relationship['selectedBySourceId'],
                    'selectedBySourceType' => $relationship['selectedBySourceType'],
                    'allowedType' => $allowedType,
                    'externalTargetAllowed' => $relationship['externalTargetAllowed'],
                    'valid' => $relationship['valid'],
                    'issues' => $relationship['issues'],
                    'policyIssues' => $policyIssues,
                ];
                if (!$allowedType) {
                    $summary['disallowedRelationships'][] = $relationshipRow;
                }
                if (!$relationship['valid']) {
                    $summary['invalidRelationships'][] = $relationshipRow;
                }
            }

            sort($transformIssues, SORT_STRING);
            $summary['transforms'][] = [
                'referenceIndex' => $transform['referenceIndex'],
                'referenceUri' => $transform['referenceUri'],
                'relationshipPartName' => $transform['relationshipPartName'],
                'source' => $transform['source'],
                'selectedRelationshipCount' => count($transform['relationships']),
                'disallowedRelationshipCount' => $transformDisallowedRelationshipCount,
                'invalidRelationshipCount' => $transformInvalidRelationshipCount,
                'valid' => $transformIssues === [],
                'issues' => $transformIssues,
            ];
        }

        foreach ([
            'selectedRelationshipIds',
            'selectedInternalTargetParts',
            'selectedExternalTargets',
            'disallowedRelationshipTypes',
            'issues',
        ] as $listKey) {
            sort($summary[$listKey], SORT_STRING);
        }
        $sortTypes($summary['selectedRelationshipTypes']);
        $sortTypes($summary['disallowedRelationshipTypes']);
        foreach (['disallowedRelationships', 'invalidRelationships'] as $rowListKey) {
            usort(
                $summary[$rowListKey],
                static fn (array $left, array $right): int => [$left['source'], $left['id']]
                    <=> [$right['source'], $right['id']],
            );
        }
        ksort($summary['issueCounts'], SORT_STRING);

        $summary['selectedRelationshipCount'] = count($summary['selectedRelationshipIds']);
        $summary['valid'] = $summary['issues'] === [];

        return $summary;
    }

    /**
     * @return array{signaturePart:string, valid:bool, referenceCount:int, signedInfoReferenceCount:int, manifestReferenceCount:int, validDigestPolicyCount:int, invalidDigestPolicyCount:int, knownDigestAlgorithmCount:int, unknownDigestAlgorithmCount:int, missingDigestMethodCount:int, missingDigestValueCount:int, invalidDigestValueBase64Count:int, digestValueLengthMismatchCount:int, algorithmCounts:array<string, int>, profileCounts:array<string, int>, issueCounts:array<string, int>, issues:list<string>, invalidReferences:list<array{section:string, referenceIndex:int, manifestId:?string, uri:?string, targetPart:?string, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>}>, references:list<array{section:string, referenceIndex:int, manifestId:?string, uri:?string, targetPart:?string, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>}>}
     */
    public function digitalSignatureDigestPolicySummary(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        $summary = [
            'signaturePart' => $signaturePartName,
            'valid' => true,
            'referenceCount' => 0,
            'signedInfoReferenceCount' => 0,
            'manifestReferenceCount' => 0,
            'validDigestPolicyCount' => 0,
            'invalidDigestPolicyCount' => 0,
            'knownDigestAlgorithmCount' => 0,
            'unknownDigestAlgorithmCount' => 0,
            'missingDigestMethodCount' => 0,
            'missingDigestValueCount' => 0,
            'invalidDigestValueBase64Count' => 0,
            'digestValueLengthMismatchCount' => 0,
            'algorithmCounts' => [],
            'profileCounts' => [],
            'issueCounts' => [],
            'issues' => [],
            'invalidReferences' => [],
            'references' => [],
        ];

        foreach ($this->preflightDigitalSignatureSignedInfoReferences($signaturePartName) as $reference) {
            self::appendDigitalSignatureDigestPolicySummaryReference($summary, 'signed-info', $reference, null);
        }

        $metadata = $this->preflightDigitalSignatureMetadata($signaturePartName);
        foreach ($metadata['objects'] as $object) {
            foreach ($object['manifestReferences'] as $reference) {
                self::appendDigitalSignatureDigestPolicySummaryReference(
                    $summary,
                    'manifest',
                    $reference,
                    $reference['manifestId'],
                );
            }
        }

        sort($summary['issues'], SORT_STRING);
        ksort($summary['algorithmCounts'], SORT_STRING);
        ksort($summary['profileCounts'], SORT_STRING);
        ksort($summary['issueCounts'], SORT_STRING);

        return $summary;
    }

    /**
     * @return list<array{source:string, depth:int, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function reachableTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $queue = [[$sourcePartName, $relationshipType, 0]];
        $queuedSources = [$sourcePartName => true];
        $visitedSources = [];
        $targets = [];

        while ($queue !== []) {
            [$source, $filter, $depth] = array_shift($queue);
            $source = $this->relationshipSourceNameForEquivalent($source);
            if (isset($visitedSources[$source])) {
                continue;
            }

            $visitedSources[$source] = true;

            foreach ($this->preflightTargetsForSource($source, $filter) as $target) {
                $invalidTarget = in_array('invalid-target', $target['issues'], true);
                $targetPart = null;
                if (!$target['external'] && !$invalidTarget) {
                    $targetPart = OpcPackagePath::stripQueryAndFragment($target['target']);
                }

                $targets[] = [
                    'source' => $source,
                    'depth' => $depth,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ];

                if (
                    $targetPart === null
                    || $target['external']
                    || $invalidTarget
                    || $target['exists'] !== true
                    || $target['relationshipPartTarget']
                    || isset($visitedSources[$targetPart])
                    || isset($queuedSources[$targetPart])
                    || !($this->relationshipsForSource($targetPart) instanceof OpcRelationships)
                ) {
                    continue;
                }

                $queuedSources[$targetPart] = true;
                $queue[] = [$targetPart, null, $depth + 1];
            }
        }

        return $targets;
    }

    /**
     * @param list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, target:string, targetPart:?string, contentType:?string, external:bool, valid:bool, issues:list<string>}>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}> $transforms
     *
     * @return array<string, list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>>
     */
    private static function signatureRelationshipTransformTargetIndex(array $transforms): array
    {
        $index = [];
        foreach ($transforms as $transform) {
            foreach ($transform['relationships'] as $relationship) {
                if ($relationship['external'] || $relationship['targetPart'] === null) {
                    continue;
                }

                $targetPart = $relationship['targetPart'];
                $index[self::partNameEquivalenceKey($targetPart)][] = [
                    'signaturePart' => $transform['signaturePart'],
                    'referenceIndex' => $transform['referenceIndex'],
                    'relationshipPartName' => $transform['relationshipPartName'],
                    'source' => $relationship['source'],
                    'id' => $relationship['id'],
                    'type' => $relationship['type'],
                    'target' => $relationship['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $relationship['contentType'],
                    'selectedBySourceId' => $relationship['selectedBySourceId'],
                    'selectedBySourceType' => $relationship['selectedBySourceType'],
                    'relationshipValid' => $relationship['valid'],
                    'relationshipIssues' => $relationship['issues'],
                    'transformValid' => $transform['valid'],
                    'transformIssues' => $transform['issues'],
                    'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
                    'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
                ];
            }
        }

        foreach ($index as &$matches) {
            usort(
                $matches,
                static fn (array $left, array $right): int => [
                    $left['source'] ?? '',
                    $left['id'],
                    $left['targetPart'],
                ] <=> [
                    $right['source'] ?? '',
                    $right['id'],
                    $right['targetPart'],
                ],
            );
        }
        unset($matches);
        ksort($index, SORT_STRING);

        return $index;
    }

    /**
     * @param array<string, list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>> $relationshipTransformTargetIndex
     * @param array<string, list<string>> $sameDocumentIdIndex
     * @param array<string, int> $signatureObjectIdCounts
     *
     * @return array<string, mixed>
     */
    private static function digitalSignatureObjectMetadata(
        \DOMElement $object,
        string $signaturePartName,
        ZipPackage $package,
        OpcContentTypes $contentTypes,
        array $relationshipTransformTargetIndex,
        array $sameDocumentIdIndex,
        array $signatureObjectIdCounts,
    ): array
    {
        $issues = [];
        $id = self::optionalElementAttribute($object, 'Id');
        if ($id === null) {
            $issues[] = 'missing-signature-object-id';
        }
        $idOccurrenceCount = $id === null ? 0 : ($signatureObjectIdCounts[$id] ?? 0);
        $idDuplicate = $idOccurrenceCount > 1;
        if ($idDuplicate) {
            $issues[] = 'duplicate-signature-object-id';
        }

        $signatureTime = self::firstDescendantElementByNamespace($object, self::DIGITAL_SIGNATURE_NAMESPACE_URI, 'SignatureTime');
        $signatureTimeFormat = null;
        $signatureTimeValue = null;
        $signatureTimeValid = null;
        if ($signatureTime instanceof \DOMElement) {
            $signatureTimeFormat = self::firstChildTextByNamespace($signatureTime, self::DIGITAL_SIGNATURE_NAMESPACE_URI, 'Format');
            $signatureTimeValue = self::firstChildTextByNamespace($signatureTime, self::DIGITAL_SIGNATURE_NAMESPACE_URI, 'Value');
            if ($signatureTimeValue === null || $signatureTimeValue === '') {
                $signatureTimeValid = false;
                $issues[] = 'missing-signature-time-value';
            } else {
                $signatureTimeValid = self::isXmlSignatureTimeValue($signatureTimeValue);
                if (!$signatureTimeValid) {
                    $issues[] = 'invalid-signature-time-value';
                }
            }
        }

        $signatureProperties = self::digitalSignatureObjectSignaturePropertyTargets($object, $sameDocumentIdIndex);
        foreach ($signatureProperties['targets'] as $signaturePropertyTarget) {
            foreach ($signaturePropertyTarget['issues'] as $issue) {
                self::appendUniqueString($issues, $issue);
            }
        }

        $manifestPreflight = self::digitalSignatureObjectManifestReferences(
            $object,
            $signaturePartName,
            $package,
            $contentTypes,
            $relationshipTransformTargetIndex,
        );
        foreach ($manifestPreflight['references'] as $manifestReference) {
            foreach ($manifestReference['issues'] as $issue) {
                self::appendUniqueString($issues, $issue);
            }
        }
        foreach ($manifestPreflight['issues'] as $issue) {
            self::appendUniqueString($issues, $issue);
        }

        return [
            'id' => $id,
            'idDuplicate' => $idDuplicate,
            'idOccurrenceCount' => $idOccurrenceCount,
            'mimeType' => self::optionalElementAttribute($object, 'MimeType'),
            'encoding' => self::optionalElementAttribute($object, 'Encoding'),
            'signatureTimeFormat' => $signatureTimeFormat,
            'signatureTimeValue' => $signatureTimeValue,
            'signatureTimeValid' => $signatureTimeValid,
            'signaturePropertyCount' => $signatureProperties['propertyCount'],
            'signaturePropertyTargetCount' => $signatureProperties['targetCount'],
            'signaturePropertyTargets' => $signatureProperties['targets'],
            'packageSignatureElements' => self::descendantElementLocalNamesByNamespace($object, self::DIGITAL_SIGNATURE_NAMESPACE_URI),
            'manifestCount' => $manifestPreflight['manifestCount'],
            'manifestIds' => $manifestPreflight['manifestIds'],
            'duplicateManifestIds' => $manifestPreflight['duplicateManifestIds'],
            'missingManifestIdCount' => $manifestPreflight['missingManifestIdCount'],
            'manifestReferenceCount' => count($manifestPreflight['references']),
            'manifestReferences' => $manifestPreflight['references'],
            'valid' => $issues === [],
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * @param array<string, list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>> $relationshipTransformTargetIndex
     *
     * @return array{manifestCount:int, manifestIds:list<string>, duplicateManifestIds:list<string>, missingManifestIdCount:int, issues:list<string>, references:list<array{manifestId:?string, referenceIndex:int, uri:?string, targetPart:?string, exists:?bool, contentType:?string, relationshipTransformTargetMatched:bool, relationshipTransformTargetMatchCount:int, relationshipTransformPayloadByteCounts:list<int>, relationshipTransformPayloadSha256s:list<string>, relationshipTransformTargetMatches:list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValue:?string, digestValueBase64Length:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>, parseError:?string}>}
     */
    private static function digitalSignatureObjectManifestReferences(
        \DOMElement $object,
        string $signaturePartName,
        ZipPackage $package,
        OpcContentTypes $contentTypes,
        array $relationshipTransformTargetIndex,
    ): array {
        $references = [];
        $manifests = self::descendantElementsByNamespace($object, self::XML_SIGNATURE_NAMESPACE_URI, 'Manifest');
        $manifestIds = [];
        $manifestIdCounts = [];
        $missingManifestIdCount = 0;
        foreach ($manifests as $manifest) {
            $manifestId = self::optionalElementAttribute($manifest, 'Id');
            if ($manifestId === null) {
                $missingManifestIdCount++;
            } else {
                $manifestIds[] = $manifestId;
                $manifestIdCounts[$manifestId] = ($manifestIdCounts[$manifestId] ?? 0) + 1;
            }
            foreach ($manifest->childNodes as $child) {
                if (
                    !$child instanceof \DOMElement
                    || $child->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
                    || $child->localName !== 'Reference'
                ) {
                    continue;
                }

                $references[] = self::digitalSignatureManifestReferenceMetadata(
                    $child,
                    count($references),
                    $manifestId,
                    $signaturePartName,
                    $package,
                    $contentTypes,
                    $relationshipTransformTargetIndex,
                );
            }
        }
        $duplicateManifestIds = array_keys(array_filter(
            $manifestIdCounts,
            static fn (int $count): bool => $count > 1,
        ));
        sort($duplicateManifestIds, SORT_STRING);

        return [
            'manifestCount' => count($manifests),
            'manifestIds' => $manifestIds,
            'duplicateManifestIds' => $duplicateManifestIds,
            'missingManifestIdCount' => $missingManifestIdCount,
            'issues' => $duplicateManifestIds === [] ? [] : ['duplicate-manifest-id'],
            'references' => $references,
        ];
    }

    /**
     * @param array<string, list<string>> $sameDocumentIdIndex
     * @return array{propertyCount:int, targetCount:int, targets:list<array{propertyIndex:int, target:?string, targetKind:?string, targetFragment:?string, targetMatched:bool, targetMatchedElementNames:list<string>, valid:bool, issues:list<string>}>}
     */
    private static function digitalSignatureObjectSignaturePropertyTargets(\DOMElement $object, array $sameDocumentIdIndex): array
    {
        $targets = [];
        $propertyIndex = 0;
        $targetCount = 0;
        foreach (self::descendantElementsByNamespace($object, self::XML_SIGNATURE_NAMESPACE_URI, 'SignatureProperty') as $property) {
            $target = self::optionalElementAttribute($property, 'Target');
            $targetKind = null;
            $targetFragment = null;
            $targetMatched = false;
            $targetMatchedElementNames = [];
            $issues = [];

            if ($target === null) {
                $issues[] = 'missing-signature-property-target';
            } else {
                $targetCount++;
                if (str_starts_with($target, '#')) {
                    $targetKind = 'same-document-fragment';
                    $targetFragment = substr($target, 1);
                    if ($targetFragment === '') {
                        $issues[] = 'invalid-signature-property-target';
                    } else {
                        $targetMatchedElementNames = $sameDocumentIdIndex[$targetFragment] ?? [];
                        $targetMatched = $targetMatchedElementNames !== [];
                        if (!$targetMatched) {
                            $issues[] = 'unmatched-signature-property-target';
                        } elseif (count($targetMatchedElementNames) > 1) {
                            $issues[] = 'ambiguous-signature-property-target';
                        }
                    }
                } elseif (
                    preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1
                    || str_starts_with($target, '//')
                ) {
                    $targetKind = 'external-uri';
                    $issues[] = 'signature-property-target-not-same-document';
                } else {
                    $targetKind = 'relative-reference';
                    $issues[] = 'signature-property-target-not-same-document';
                }
            }

            $targets[] = [
                'propertyIndex' => $propertyIndex,
                'target' => $target,
                'targetKind' => $targetKind,
                'targetFragment' => $targetFragment,
                'targetMatched' => $targetMatched,
                'targetMatchedElementNames' => $targetMatchedElementNames,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
            $propertyIndex++;
        }

        return [
            'propertyCount' => $propertyIndex,
            'targetCount' => $targetCount,
            'targets' => $targets,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function xmlSignatureSameDocumentIdIndex(\DOMElement $root): array
    {
        $index = [];
        self::indexXmlSignatureSameDocumentIds($root, $index);
        foreach ($index as &$elementNames) {
            sort($elementNames, SORT_STRING);
        }
        unset($elementNames);
        ksort($index, SORT_STRING);

        return $index;
    }

    /**
     * @param array<string, list<string>> $index
     */
    private static function indexXmlSignatureSameDocumentIds(\DOMElement $element, array &$index): void
    {
        $id = self::optionalElementAttribute($element, 'Id');
        if ($id !== null) {
            $index[$id][] = $element->localName;
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                self::indexXmlSignatureSameDocumentIds($child, $index);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private static function directXmlSignatureObjectIdCounts(\DOMElement $root): array
    {
        $counts = [];
        foreach ($root->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
                || $child->localName !== 'Object'
            ) {
                continue;
            }

            $id = self::optionalElementAttribute($child, 'Id');
            if ($id !== null) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>> $relationshipTransformTargetIndex
     *
     * @return array{manifestId:?string, referenceIndex:int, uri:?string, targetPart:?string, exists:?bool, contentType:?string, relationshipTransformTargetMatched:bool, relationshipTransformTargetMatchCount:int, relationshipTransformPayloadByteCounts:list<int>, relationshipTransformPayloadSha256s:list<string>, relationshipTransformTargetMatches:list<array{signaturePart:string, referenceIndex:int, relationshipPartName:?string, source:?string, id:string, type:string, target:string, targetPart:string, contentType:?string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipValid:bool, relationshipIssues:list<string>, transformValid:bool, transformIssues:list<string>, relationshipXmlBytes:?int, relationshipXmlSha256:?string}>, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValue:?string, digestValueBase64Length:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>, parseError:?string}
     */
    private static function digitalSignatureManifestReferenceMetadata(
        \DOMElement $reference,
        int $referenceIndex,
        ?string $manifestId,
        string $signaturePartName,
        ZipPackage $package,
        OpcContentTypes $contentTypes,
        array $relationshipTransformTargetIndex,
    ): array {
        $issues = [];
        $parseError = null;
        $targetPart = null;
        $exists = null;
        $contentType = null;
        $uri = $reference->hasAttribute('URI') ? trim($reference->getAttribute('URI')) : null;

        if ($uri === null || $uri === '') {
            $issues[] = 'missing-manifest-reference-uri';
        } elseif (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $uri) === 1
            || str_starts_with($uri, '//')
        ) {
            $issues[] = 'manifest-reference-external-uri';
        } elseif (str_starts_with($uri, '#')) {
            $issues[] = 'manifest-reference-fragment-uri';
        } else {
            try {
                $targetPart = OpcPackagePath::stripQueryAndFragment(
                    OpcPackagePath::resolveInternalTarget($signaturePartName, $uri),
                );
                $exists = $package->has($targetPart);
                $contentType = $contentTypes->contentTypeForPart($targetPart);
                if (!$exists) {
                    $issues[] = 'manifest-reference-target-missing';
                }
                if ($contentType === null) {
                    $issues[] = 'manifest-reference-content-type-missing';
                }
            } catch (\InvalidArgumentException $exception) {
                $issues[] = 'invalid-manifest-reference-uri';
                $parseError = $exception->getMessage();
            }
        }

        $digestMethod = self::firstChildElementByNamespace($reference, self::XML_SIGNATURE_NAMESPACE_URI, 'DigestMethod');
        $digestAlgorithm = $digestMethod instanceof \DOMElement
            ? self::optionalElementAttribute($digestMethod, 'Algorithm')
            : null;
        if ($digestAlgorithm === null) {
            $issues[] = 'missing-manifest-reference-digest-method';
        }

        $digestValueElement = self::firstChildElementByNamespace($reference, self::XML_SIGNATURE_NAMESPACE_URI, 'DigestValue');
        $digestValue = null;
        $digestValueBase64Length = null;
        $digestValueDecodedBytes = null;
        if ($digestValueElement instanceof \DOMElement) {
            $digestValue = preg_replace('/\s+/', '', trim($digestValueElement->textContent));
            if (!is_string($digestValue)) {
                $digestValue = trim($digestValueElement->textContent);
            }
        }

        if ($digestValue === null || $digestValue === '') {
            $issues[] = 'missing-manifest-reference-digest-value';
        } else {
            $digestValueBase64Length = strlen($digestValue);
            $decodedDigest = base64_decode($digestValue, true);
            if ($decodedDigest === false) {
                $issues[] = 'invalid-manifest-reference-digest-value-base64';
            } else {
                $digestValueDecodedBytes = strlen($decodedDigest);
            }
        }

        $digestPolicy = self::digitalSignatureDigestPolicy($digestAlgorithm, $digestValueDecodedBytes);
        $issues = array_values(array_unique($issues));
        $relationshipTransformTargetMatches = $targetPart === null
            ? []
            : ($relationshipTransformTargetIndex[self::partNameEquivalenceKey($targetPart)] ?? []);
        $relationshipTransformPayloadByteCounts = [];
        $relationshipTransformPayloadSha256s = [];
        foreach ($relationshipTransformTargetMatches as $match) {
            if (
                $match['relationshipXmlBytes'] !== null
                && !in_array($match['relationshipXmlBytes'], $relationshipTransformPayloadByteCounts, true)
            ) {
                $relationshipTransformPayloadByteCounts[] = $match['relationshipXmlBytes'];
            }

            if (
                $match['relationshipXmlSha256'] !== null
                && !in_array($match['relationshipXmlSha256'], $relationshipTransformPayloadSha256s, true)
            ) {
                $relationshipTransformPayloadSha256s[] = $match['relationshipXmlSha256'];
            }
        }
        sort($relationshipTransformPayloadByteCounts, SORT_NUMERIC);
        sort($relationshipTransformPayloadSha256s, SORT_STRING);

        return [
            'manifestId' => $manifestId,
            'referenceIndex' => $referenceIndex,
            'uri' => $uri,
            'targetPart' => $targetPart,
            'exists' => $exists,
            'contentType' => $contentType,
            'relationshipTransformTargetMatched' => $relationshipTransformTargetMatches !== [],
            'relationshipTransformTargetMatchCount' => count($relationshipTransformTargetMatches),
            'relationshipTransformPayloadByteCounts' => $relationshipTransformPayloadByteCounts,
            'relationshipTransformPayloadSha256s' => $relationshipTransformPayloadSha256s,
            'relationshipTransformTargetMatches' => $relationshipTransformTargetMatches,
            'digestAlgorithm' => $digestAlgorithm,
            'digestAlgorithmKnown' => $digestPolicy['known'],
            'digestAlgorithmProfile' => $digestPolicy['profile'],
            'digestExpectedDecodedBytes' => $digestPolicy['expectedDecodedBytes'],
            'digestValue' => $digestValue,
            'digestValueBase64Length' => $digestValueBase64Length,
            'digestValueDecodedBytes' => $digestValueDecodedBytes,
            'digestValueLengthValid' => $digestPolicy['valueLengthValid'],
            'valid' => $issues === [],
            'issues' => $issues,
            'parseError' => $parseError,
        ];
    }

    /**
     * @param array<string, list<string>> $sameDocumentIdIndex
     * @return array{signaturePart:string, referenceIndex:int, uri:?string, targetPart:?string, exists:?bool, contentType:?string, sameDocumentReference:bool, sameDocumentFragment:?string, sameDocumentTargetMatched:bool, sameDocumentTargetMatchCount:int, sameDocumentTargetMatchedElementNames:list<string>, relationshipPart:bool, referenceContentType:?string, referenceContentTypeMatches:?bool, transformAlgorithms:list<string>, relationshipTransformIndexes:list<int>, canonicalizationTransformIndexes:list<int>, relationshipTransformCount:int, canonicalizationTransformCount:int, canonicalizationTransformAlgorithms:list<string>, canonicalizationTransforms:list<array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}>, relationshipTransformFollowingCanonicalization:?array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}, relationshipTransformFollowedByCanonicalization:?bool, digestAlgorithm:?string, digestAlgorithmKnown:?bool, digestAlgorithmProfile:?string, digestExpectedDecodedBytes:?int, digestValue:?string, digestValueBase64Length:?int, digestValueDecodedBytes:?int, digestValueLengthValid:?bool, valid:bool, issues:list<string>, parseError:?string}
     */
    private static function digitalSignatureSignedInfoReferenceMetadata(
        \DOMElement $reference,
        int $referenceIndex,
        string $signaturePartName,
        ZipPackage $package,
        OpcContentTypes $contentTypes,
        array $sameDocumentIdIndex,
    ): array {
        $issues = [];
        $parseError = null;
        $targetPart = null;
        $exists = null;
        $contentType = null;
        $sameDocumentReference = false;
        $sameDocumentFragment = null;
        $sameDocumentTargetMatched = false;
        $sameDocumentTargetMatchedElementNames = [];
        $relationshipPart = false;
        $referenceContentTypeMatches = null;
        $uri = $reference->hasAttribute('URI') ? trim($reference->getAttribute('URI')) : null;

        if ($uri === null || $uri === '') {
            $issues[] = 'missing-signed-info-reference-uri';
        } elseif (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $uri) === 1
            || str_starts_with($uri, '//')
        ) {
            $issues[] = 'signed-info-reference-external-uri';
        } elseif (str_starts_with($uri, '#')) {
            $sameDocumentReference = true;
            $sameDocumentFragment = substr($uri, 1);
            if ($sameDocumentFragment === '') {
                $issues[] = 'invalid-signed-info-same-document-reference';
            } else {
                $sameDocumentTargetMatchedElementNames = $sameDocumentIdIndex[$sameDocumentFragment] ?? [];
                $sameDocumentTargetMatched = $sameDocumentTargetMatchedElementNames !== [];
                if (!$sameDocumentTargetMatched) {
                    $issues[] = 'unmatched-signed-info-same-document-reference';
                } elseif (count($sameDocumentTargetMatchedElementNames) > 1) {
                    $issues[] = 'ambiguous-signed-info-same-document-reference';
                }
            }
        } else {
            try {
                $targetPart = OpcPackagePath::stripQueryAndFragment(
                    OpcPackagePath::resolveInternalTarget($signaturePartName, $uri),
                );
                $exists = $package->has($targetPart);
                $contentType = $contentTypes->contentTypeForPart($targetPart);
                $relationshipPart = OpcRelationships::isRelationshipPartName($targetPart);
                if (!$exists) {
                    $issues[] = 'signed-info-reference-target-missing';
                }
                if ($contentType === null) {
                    $issues[] = 'signed-info-reference-content-type-missing';
                }
            } catch (\InvalidArgumentException $exception) {
                $issues[] = 'invalid-signed-info-reference-uri';
                $parseError = $exception->getMessage();
            }
        }

        $referenceContentTypeQuery = self::referenceContentTypeQuery($uri ?? '');
        $referenceContentType = $referenceContentTypeQuery['contentType'];
        $issues = array_merge($issues, $referenceContentTypeQuery['issues']);
        if ($parseError === null && $referenceContentTypeQuery['parseError'] !== null) {
            $parseError = $referenceContentTypeQuery['parseError'];
        }

        if ($referenceContentType !== null) {
            $referenceContentTypeMatches = self::contentTypeMatches($contentType, $referenceContentType);
            if (!$referenceContentTypeMatches) {
                $issues[] = 'signed-info-reference-content-type-mismatch';
            }
        }

        $transforms = self::xmlSignatureReferenceTransforms($reference);
        $transformAlgorithms = [];
        foreach ($transforms as $transform) {
            $transformAlgorithms[] = $transform->hasAttribute('Algorithm')
                ? trim($transform->getAttribute('Algorithm'))
                : '';
        }

        $relationshipTransformCount = 0;
        $relationshipTransformIndexes = [];
        $canonicalizationTransformCount = 0;
        $canonicalizationTransformIndexes = [];
        $canonicalizationTransformAlgorithms = [];
        $canonicalizationTransforms = [];
        $relationshipTransformFollowingCanonicalization = null;
        $relationshipTransformFollowedByCanonicalization = null;
        foreach ($transformAlgorithms as $transformIndex => $algorithm) {
            if ($algorithm === self::RELATIONSHIP_TRANSFORM_ALGORITHM) {
                $relationshipTransformCount++;
                $relationshipTransformIndexes[] = $transformIndex;
                $followingAlgorithm = self::followingTransformAlgorithm($transforms, $transformIndex);
                $followingCanonicalization = self::canonicalizationTransformMetadata($followingAlgorithm);
                $followedByCanonicalization = $followingCanonicalization !== null;
                if ($relationshipTransformFollowingCanonicalization === null && $followingCanonicalization !== null) {
                    $relationshipTransformFollowingCanonicalization = $followingCanonicalization;
                }
                $relationshipTransformFollowedByCanonicalization = $relationshipTransformFollowedByCanonicalization === null
                    ? $followedByCanonicalization
                    : $relationshipTransformFollowedByCanonicalization && $followedByCanonicalization;
            }
            $canonicalizationTransform = self::canonicalizationTransformMetadata($algorithm);
            if ($canonicalizationTransform !== null) {
                $canonicalizationTransformCount++;
                $canonicalizationTransformIndexes[] = $transformIndex;
                $canonicalizationTransformAlgorithms[] = $algorithm;
                $canonicalizationTransforms[] = $canonicalizationTransform;
            }
        }

        if ($relationshipPart && $relationshipTransformCount === 0) {
            $issues[] = 'relationship-part-reference-missing-relationship-transform';
        } elseif (!$relationshipPart && $relationshipTransformCount > 0) {
            $issues[] = 'relationship-transform-reference-not-relationship-part';
        }
        if (
            $relationshipPart
            && $relationshipTransformCount > 0
            && $relationshipTransformFollowedByCanonicalization === false
        ) {
            $issues[] = 'signed-info-relationship-transform-not-followed-by-canonicalization';
        }
        if ($relationshipPart && $relationshipTransformCount > 1) {
            $issues[] = 'signed-info-multiple-relationship-transforms';
        }
        if (
            $relationshipPart
            && $relationshipTransformCount > 0
            && in_array(self::XML_SIGNATURE_ENVELOPED_SIGNATURE_TRANSFORM_ALGORITHM, $transformAlgorithms, true)
        ) {
            $issues[] = 'signed-info-relationship-transform-with-enveloped-signature-transform';
        }
        if (
            $relationshipPart
            && $relationshipTransformIndexes !== []
            && $canonicalizationTransformIndexes !== []
            && min($canonicalizationTransformIndexes) < $relationshipTransformIndexes[0]
        ) {
            $issues[] = 'signed-info-relationship-transform-after-canonicalization';
        }

        $digestMethod = self::firstChildElementByNamespace($reference, self::XML_SIGNATURE_NAMESPACE_URI, 'DigestMethod');
        $digestAlgorithm = $digestMethod instanceof \DOMElement
            ? self::optionalElementAttribute($digestMethod, 'Algorithm')
            : null;
        if ($digestAlgorithm === null) {
            $issues[] = 'missing-signed-info-reference-digest-method';
        }

        $digestValueElement = self::firstChildElementByNamespace($reference, self::XML_SIGNATURE_NAMESPACE_URI, 'DigestValue');
        $digestValue = null;
        $digestValueBase64Length = null;
        $digestValueDecodedBytes = null;
        if ($digestValueElement instanceof \DOMElement) {
            $digestValue = preg_replace('/\s+/', '', trim($digestValueElement->textContent));
            if (!is_string($digestValue)) {
                $digestValue = trim($digestValueElement->textContent);
            }
        }

        if ($digestValue === null || $digestValue === '') {
            $issues[] = 'missing-signed-info-reference-digest-value';
        } else {
            $digestValueBase64Length = strlen($digestValue);
            $decodedDigest = base64_decode($digestValue, true);
            if ($decodedDigest === false) {
                $issues[] = 'invalid-signed-info-reference-digest-value-base64';
            } else {
                $digestValueDecodedBytes = strlen($decodedDigest);
            }
        }

        $digestPolicy = self::digitalSignatureDigestPolicy($digestAlgorithm, $digestValueDecodedBytes);
        $issues = array_values(array_unique($issues));

        return [
            'signaturePart' => $signaturePartName,
            'referenceIndex' => $referenceIndex,
            'uri' => $uri,
            'targetPart' => $targetPart,
            'exists' => $exists,
            'contentType' => $contentType,
            'sameDocumentReference' => $sameDocumentReference,
            'sameDocumentFragment' => $sameDocumentFragment,
            'sameDocumentTargetMatched' => $sameDocumentTargetMatched,
            'sameDocumentTargetMatchCount' => count($sameDocumentTargetMatchedElementNames),
            'sameDocumentTargetMatchedElementNames' => $sameDocumentTargetMatchedElementNames,
            'relationshipPart' => $relationshipPart,
            'referenceContentType' => $referenceContentType,
            'referenceContentTypeMatches' => $referenceContentTypeMatches,
            'transformAlgorithms' => $transformAlgorithms,
            'relationshipTransformIndexes' => $relationshipTransformIndexes,
            'canonicalizationTransformIndexes' => $canonicalizationTransformIndexes,
            'relationshipTransformCount' => $relationshipTransformCount,
            'canonicalizationTransformCount' => $canonicalizationTransformCount,
            'canonicalizationTransformAlgorithms' => $canonicalizationTransformAlgorithms,
            'canonicalizationTransforms' => $canonicalizationTransforms,
            'relationshipTransformFollowingCanonicalization' => $relationshipTransformFollowingCanonicalization,
            'relationshipTransformFollowedByCanonicalization' => $relationshipTransformFollowedByCanonicalization,
            'digestAlgorithm' => $digestAlgorithm,
            'digestAlgorithmKnown' => $digestPolicy['known'],
            'digestAlgorithmProfile' => $digestPolicy['profile'],
            'digestExpectedDecodedBytes' => $digestPolicy['expectedDecodedBytes'],
            'digestValue' => $digestValue,
            'digestValueBase64Length' => $digestValueBase64Length,
            'digestValueDecodedBytes' => $digestValueDecodedBytes,
            'digestValueLengthValid' => $digestPolicy['valueLengthValid'],
            'valid' => $issues === [],
            'issues' => $issues,
            'parseError' => $parseError,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $reference
     */
    private static function appendDigitalSignatureDigestPolicySummaryReference(
        array &$summary,
        string $section,
        array $reference,
        ?string $manifestId,
    ): void {
        $issuePrefix = $section === 'signed-info' ? 'signed-info-reference' : 'manifest-reference';
        $issues = self::digitalSignatureDigestPolicyIssues($section, $reference);
        $row = [
            'section' => $section,
            'referenceIndex' => $reference['referenceIndex'],
            'manifestId' => $section === 'manifest' ? $manifestId : null,
            'uri' => $reference['uri'] ?? null,
            'targetPart' => $reference['targetPart'] ?? null,
            'digestAlgorithm' => $reference['digestAlgorithm'] ?? null,
            'digestAlgorithmKnown' => $reference['digestAlgorithmKnown'] ?? null,
            'digestAlgorithmProfile' => $reference['digestAlgorithmProfile'] ?? null,
            'digestExpectedDecodedBytes' => $reference['digestExpectedDecodedBytes'] ?? null,
            'digestValueDecodedBytes' => $reference['digestValueDecodedBytes'] ?? null,
            'digestValueLengthValid' => $reference['digestValueLengthValid'] ?? null,
            'valid' => $issues === [],
            'issues' => $issues,
        ];

        $summary['referenceCount']++;
        if ($section === 'signed-info') {
            $summary['signedInfoReferenceCount']++;
        } else {
            $summary['manifestReferenceCount']++;
        }

        if (is_string($row['digestAlgorithm']) && $row['digestAlgorithm'] !== '') {
            $summary['algorithmCounts'][$row['digestAlgorithm']] = ($summary['algorithmCounts'][$row['digestAlgorithm']] ?? 0) + 1;
        }
        if (is_string($row['digestAlgorithmProfile']) && $row['digestAlgorithmProfile'] !== '') {
            $summary['profileCounts'][$row['digestAlgorithmProfile']] = ($summary['profileCounts'][$row['digestAlgorithmProfile']] ?? 0) + 1;
        }
        if ($row['digestAlgorithmKnown'] === true) {
            $summary['knownDigestAlgorithmCount']++;
        } elseif ($row['digestAlgorithmKnown'] === false) {
            $summary['unknownDigestAlgorithmCount']++;
        }

        if ($row['valid']) {
            $summary['validDigestPolicyCount']++;
        } else {
            $summary['valid'] = false;
            $summary['invalidDigestPolicyCount']++;
            $summary['invalidReferences'][] = $row;
        }
        $summary['references'][] = $row;

        if (in_array('missing-' . $issuePrefix . '-digest-method', $issues, true)) {
            $summary['missingDigestMethodCount']++;
        }
        if (in_array('missing-' . $issuePrefix . '-digest-value', $issues, true)) {
            $summary['missingDigestValueCount']++;
        }
        if (in_array('invalid-' . $issuePrefix . '-digest-value-base64', $issues, true)) {
            $summary['invalidDigestValueBase64Count']++;
        }
        if (in_array('invalid-' . $issuePrefix . '-digest-value-length', $issues, true)) {
            $summary['digestValueLengthMismatchCount']++;
        }

        foreach ($issues as $issue) {
            $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
            self::appendUniqueString($summary['issues'], $issue);
        }
    }

    /**
     * @param array<string, mixed> $reference
     * @return list<string>
     */
    private static function digitalSignatureDigestPolicyIssues(string $section, array $reference): array
    {
        $issuePrefix = $section === 'signed-info' ? 'signed-info-reference' : 'manifest-reference';
        $issues = [];
        foreach ($reference['issues'] ?? [] as $issue) {
            if (!is_string($issue) || !str_contains($issue, $issuePrefix . '-digest')) {
                continue;
            }

            self::appendUniqueString($issues, $issue);
        }

        if (($reference['digestAlgorithmKnown'] ?? null) === false) {
            self::appendUniqueString($issues, 'unknown-' . $issuePrefix . '-digest-algorithm');
        }
        if (($reference['digestValueLengthValid'] ?? null) === false) {
            self::appendUniqueString($issues, 'invalid-' . $issuePrefix . '-digest-value-length');
        }

        sort($issues, SORT_STRING);

        return $issues;
    }

    /**
     * @return array{known:?bool, profile:?string, expectedDecodedBytes:?int, valueLengthValid:?bool}
     */
    private static function digitalSignatureDigestPolicy(?string $algorithm, ?int $decodedBytes): array
    {
        if ($algorithm === null || $algorithm === '') {
            return [
                'known' => null,
                'profile' => null,
                'expectedDecodedBytes' => null,
                'valueLengthValid' => null,
            ];
        }

        $policy = self::XML_SIGNATURE_DIGEST_ALGORITHMS[$algorithm] ?? null;
        if ($policy === null) {
            return [
                'known' => false,
                'profile' => null,
                'expectedDecodedBytes' => null,
                'valueLengthValid' => null,
            ];
        }

        $expectedDecodedBytes = $policy['expectedDecodedBytes'];

        return [
            'known' => true,
            'profile' => $policy['profile'],
            'expectedDecodedBytes' => $expectedDecodedBytes,
            'valueLengthValid' => $decodedBytes === null ? null : $decodedBytes === $expectedDecodedBytes,
        ];
    }

    /**
     * @return array{index:int, base64Length:int, decodedBytes:?int, sha256:?string, valid:bool, issues:list<string>}
     */
    private static function x509CertificateMetadata(\DOMElement $certificate, int $index): array
    {
        $base64 = preg_replace('/\s+/', '', trim($certificate->textContent));
        if (!is_string($base64)) {
            $base64 = trim($certificate->textContent);
        }

        $issues = [];
        $decodedBytes = null;
        $sha256 = null;
        if ($base64 === '') {
            $issues[] = 'empty-x509-certificate';
        } else {
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                $issues[] = 'invalid-x509-certificate-base64';
            } else {
                $decodedBytes = strlen($decoded);
                $sha256 = hash('sha256', $decoded);
            }
        }

        return [
            'index' => $index,
            'base64Length' => strlen($base64),
            'decodedBytes' => $decodedBytes,
            'sha256' => $sha256,
            'valid' => $issues === [],
            'issues' => $issues,
        ];
    }

    private static function optionalElementAttribute(\DOMElement $element, string $name): ?string
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));
        return $value === '' ? null : $value;
    }

    private static function firstChildTextByNamespace(\DOMElement $element, string $namespaceUri, string $localName): ?string
    {
        foreach ($element->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->namespaceURI === $namespaceUri
                && $child->localName === $localName
            ) {
                return trim($child->textContent);
            }
        }

        return null;
    }

    private static function firstChildElementByNamespace(\DOMElement $element, string $namespaceUri, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->namespaceURI === $namespaceUri
                && $child->localName === $localName
            ) {
                return $child;
            }
        }

        return null;
    }

    private static function firstDescendantElementByNamespace(\DOMElement $element, string $namespaceUri, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === $namespaceUri && $child->localName === $localName) {
                return $child;
            }

            $descendant = self::firstDescendantElementByNamespace($child, $namespaceUri, $localName);
            if ($descendant instanceof \DOMElement) {
                return $descendant;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function descendantElementsByNamespace(\DOMElement $element, string $namespaceUri, string $localName): array
    {
        $elements = [];
        foreach ($element->getElementsByTagNameNS($namespaceUri, $localName) as $child) {
            if ($child instanceof \DOMElement) {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    /**
     * @return array{rootName:?string, rootNamespace:?string, itemId:?string, itemIdValid:?bool, schemaRefCount:int, schemaRefUris:list<string>, valid:bool, issues:list<string>}
     */
    private static function customXmlPropertiesMetadata(string $xml): array
    {
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'OPC custom XML properties XML');
        $root = $dom->documentElement;
        $rootName = $root instanceof \DOMElement ? $root->localName : null;
        $rootNamespace = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $issues = [];
        $itemId = null;
        $itemIdValid = null;
        $schemaRefCount = 0;
        $schemaRefUris = [];

        if (
            !$root instanceof \DOMElement
            || $rootNamespace !== self::CUSTOM_XML_DATA_STORE_NAMESPACE_URI
            || $rootName !== 'datastoreItem'
        ) {
            $issues[] = 'missing-custom-xml-datastore-item-root';

            return [
                'rootName' => $rootName,
                'rootNamespace' => $rootNamespace,
                'itemId' => $itemId,
                'itemIdValid' => $itemIdValid,
                'schemaRefCount' => $schemaRefCount,
                'schemaRefUris' => $schemaRefUris,
                'valid' => false,
                'issues' => $issues,
            ];
        }

        $itemId = trim($root->getAttributeNS(self::CUSTOM_XML_DATA_STORE_NAMESPACE_URI, 'itemID'));
        if ($itemId === '') {
            $issues[] = 'missing-custom-xml-item-id';
            $itemId = null;
        } else {
            $itemIdValid = self::isCustomXmlItemId($itemId);
            if (!$itemIdValid) {
                $issues[] = 'invalid-custom-xml-item-id';
            }
        }

        $schemaRefs = self::firstChildElementByNamespace($root, self::CUSTOM_XML_DATA_STORE_NAMESPACE_URI, 'schemaRefs');
        if ($schemaRefs instanceof \DOMElement) {
            foreach ($schemaRefs->childNodes as $schemaRef) {
                if (
                    !$schemaRef instanceof \DOMElement
                    || $schemaRef->namespaceURI !== self::CUSTOM_XML_DATA_STORE_NAMESPACE_URI
                    || $schemaRef->localName !== 'schemaRef'
                ) {
                    continue;
                }

                $schemaRefCount++;
                $uri = trim($schemaRef->getAttributeNS(self::CUSTOM_XML_DATA_STORE_NAMESPACE_URI, 'uri'));
                if ($uri === '') {
                    self::appendUniqueString($issues, 'missing-custom-xml-schema-ref-uri');
                    continue;
                }

                if (preg_match('/[\x00-\x20\x7f]/', $uri) === 1) {
                    self::appendUniqueString($issues, 'invalid-custom-xml-schema-ref-uri');
                    continue;
                }

                $schemaRefUris[] = $uri;
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'rootName' => $rootName,
            'rootNamespace' => $rootNamespace,
            'itemId' => $itemId,
            'itemIdValid' => $itemIdValid,
            'schemaRefCount' => $schemaRefCount,
            'schemaRefUris' => $schemaRefUris,
            'valid' => $issues === [],
            'issues' => $issues,
        ];
    }

    private static function isCustomXmlItemId(string $itemId): bool
    {
        $hasOpeningBrace = str_starts_with($itemId, '{');
        $hasClosingBrace = str_ends_with($itemId, '}');
        if ($hasOpeningBrace !== $hasClosingBrace) {
            return false;
        }

        $guid = $hasOpeningBrace ? substr($itemId, 1, -1) : $itemId;

        return preg_match(
            '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/D',
            $guid
        ) === 1;
    }

    /**
     * @return list<string>
     */
    private static function descendantElementLocalNamesByNamespace(\DOMElement $element, string $namespaceUri): array
    {
        $names = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === $namespaceUri) {
                self::appendUniqueString($names, $child->localName);
            }

            foreach (self::descendantElementLocalNamesByNamespace($child, $namespaceUri) as $name) {
                self::appendUniqueString($names, $name);
            }
        }

        return $names;
    }

    private static function isXmlSignatureTimeValue(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return false;
        }

        foreach ([
            '!Y-m-d\TH:i:s\Z',
            '!Y-m-d\TH:i:sP',
            '!Y-m-d\TH:i:s.u\Z',
            '!Y-m-d\TH:i:s.uP',
        ] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (
                $date instanceof \DateTimeImmutable
                && (
                    $errors === false
                    || (
                        ($errors['warning_count'] ?? 0) === 0
                        && ($errors['error_count'] ?? 0) === 0
                    )
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function xmlSignatureReferenceTransforms(\DOMElement $reference): array
    {
        $transforms = [];
        foreach ($reference->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
                || $child->localName !== 'Transforms'
            ) {
                continue;
            }

            foreach ($child->childNodes as $transform) {
                if (
                    $transform instanceof \DOMElement
                    && $transform->namespaceURI === self::XML_SIGNATURE_NAMESPACE_URI
                    && $transform->localName === 'Transform'
                ) {
                    $transforms[] = $transform;
                }
            }
        }

        return $transforms;
    }

    /**
     * @return array{sourceIds:list<string>, sourceTypes:list<string>, duplicateSourceIds:list<string>, duplicateSourceTypes:list<string>, selectorDuplicateSourceIdCount:int, selectorDuplicateSourceTypeCount:int, invalidSourceTypes:list<string>, sourceTypeIssues:array<string, list<string>>, selectorChildCount:int, selectorRelationshipReferenceCount:int, selectorRelationshipGroupReferenceCount:int, selectorUnsupportedChildCount:int, selectorUnsupportedContentCount:int, issues:list<string>}
     */
    private static function relationshipTransformSelectors(\DOMElement $transform): array
    {
        $sourceIds = [];
        $sourceTypes = [];
        $sourceIdOccurrences = [];
        $sourceTypeOccurrences = [];
        $duplicateSourceIds = [];
        $duplicateSourceTypes = [];
        $sourceTypeIssues = [];
        $selectorChildCount = 0;
        $selectorRelationshipReferenceCount = 0;
        $selectorRelationshipGroupReferenceCount = 0;
        $selectorUnsupportedChildCount = 0;
        $selectorUnsupportedContentCount = 0;
        $issues = [];

        foreach ($transform->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') === '') {
                continue;
            }

            $selectorChildCount++;

            if (!$child instanceof \DOMElement) {
                if (($child->nodeValue ?? '') !== '') {
                    $selectorUnsupportedContentCount++;
                    $issues[] = 'unsupported-relationship-transform-content';
                }
                continue;
            }

            if ($child->namespaceURI !== self::DIGITAL_SIGNATURE_NAMESPACE_URI) {
                $selectorUnsupportedChildCount++;
                $issues[] = 'unsupported-relationship-transform-child';
                continue;
            }

            if ($child->localName === 'RelationshipReference') {
                $selectorRelationshipReferenceCount++;
                $issues = array_merge($issues, self::relationshipTransformSelectorShapeIssues($child, ['SourceId']));
                $sourceId = $child->getAttribute('SourceId');
                if ($sourceId === '') {
                    $issues[] = 'missing-source-id';
                    continue;
                }

                $sourceIdOccurrences[$sourceId] = ($sourceIdOccurrences[$sourceId] ?? 0) + 1;
                if ($sourceIdOccurrences[$sourceId] === 2) {
                    $duplicateSourceIds[] = $sourceId;
                    $issues[] = 'duplicate-source-id';
                }

                if (!in_array($sourceId, $sourceIds, true)) {
                    $sourceIds[] = $sourceId;
                }
                continue;
            }

            if ($child->localName === 'RelationshipsGroupReference') {
                $selectorRelationshipGroupReferenceCount++;
                $issues = array_merge($issues, self::relationshipTransformSelectorShapeIssues($child, ['SourceType']));
                $sourceType = $child->getAttribute('SourceType');
                if ($sourceType === '') {
                    $issues[] = 'missing-source-type';
                    continue;
                }

                $sourceTypeOccurrences[$sourceType] = ($sourceTypeOccurrences[$sourceType] ?? 0) + 1;
                if ($sourceTypeOccurrences[$sourceType] === 2) {
                    $duplicateSourceTypes[] = $sourceType;
                    $issues[] = 'duplicate-source-type';
                }

                if (!in_array($sourceType, $sourceTypes, true)) {
                    $sourceTypes[] = $sourceType;
                }
                $issuesForSourceType = self::selectorSourceTypeIssues($sourceType);
                if ($issuesForSourceType !== []) {
                    $sourceTypeIssues[$sourceType] = $issuesForSourceType;
                    $issues[] = 'invalid-source-type';
                }
                continue;
            }

            $selectorUnsupportedChildCount++;
            $issues[] = 'unsupported-relationship-transform-child';
        }

        if ($sourceIds === [] && $sourceTypes === []) {
            $issues[] = 'empty-relationship-selector';
        }

        return [
            'sourceIds' => $sourceIds,
            'sourceTypes' => $sourceTypes,
            'duplicateSourceIds' => $duplicateSourceIds,
            'duplicateSourceTypes' => $duplicateSourceTypes,
            'selectorDuplicateSourceIdCount' => count($duplicateSourceIds),
            'selectorDuplicateSourceTypeCount' => count($duplicateSourceTypes),
            'invalidSourceTypes' => array_keys($sourceTypeIssues),
            'sourceTypeIssues' => $sourceTypeIssues,
            'selectorChildCount' => $selectorChildCount,
            'selectorRelationshipReferenceCount' => $selectorRelationshipReferenceCount,
            'selectorRelationshipGroupReferenceCount' => $selectorRelationshipGroupReferenceCount,
            'selectorUnsupportedChildCount' => $selectorUnsupportedChildCount,
            'selectorUnsupportedContentCount' => $selectorUnsupportedContentCount,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * @param list<string> $allowedAttributes
     * @return list<string>
     */
    private static function relationshipTransformSelectorShapeIssues(\DOMElement $element, array $allowedAttributes): array
    {
        $issues = [];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (OpcMarkupCompatibility::isNamespaceDeclaration($attribute)) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') !== '' || !in_array($attribute->name, $allowedAttributes, true)) {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-attribute');
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-child');
                continue;
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-content');
            }
        }

        return $issues;
    }

    /**
     * @param list<\DOMElement> $transforms
     */
    private static function followingTransformAlgorithm(array $transforms, int $transformIndex): ?string
    {
        $following = $transforms[$transformIndex + 1] ?? null;
        if (!$following instanceof \DOMElement || !$following->hasAttribute('Algorithm')) {
            return null;
        }

        return $following->getAttribute('Algorithm');
    }

    /**
     * @return array{algorithm:string, profile:string, version:string, exclusive:bool, withComments:bool}|null
     */
    private static function canonicalizationTransformMetadata(?string $algorithm): ?array
    {
        return match ($algorithm) {
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315' => [
                'algorithm' => $algorithm,
                'profile' => 'inclusive-c14n-1.0',
                'version' => '1.0',
                'exclusive' => false,
                'withComments' => false,
            ],
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments' => [
                'algorithm' => $algorithm,
                'profile' => 'inclusive-c14n-1.0-with-comments',
                'version' => '1.0',
                'exclusive' => false,
                'withComments' => true,
            ],
            'http://www.w3.org/2001/10/xml-exc-c14n#' => [
                'algorithm' => $algorithm,
                'profile' => 'exclusive-c14n-1.0',
                'version' => '1.0',
                'exclusive' => true,
                'withComments' => false,
            ],
            'http://www.w3.org/2001/10/xml-exc-c14n#WithComments' => [
                'algorithm' => $algorithm,
                'profile' => 'exclusive-c14n-1.0-with-comments',
                'version' => '1.0',
                'exclusive' => true,
                'withComments' => true,
            ],
            'http://www.w3.org/2006/12/xml-c14n11' => [
                'algorithm' => $algorithm,
                'profile' => 'c14n-1.1',
                'version' => '1.1',
                'exclusive' => false,
                'withComments' => false,
            ],
            'http://www.w3.org/2006/12/xml-c14n11#WithComments' => [
                'algorithm' => $algorithm,
                'profile' => 'c14n-1.1-with-comments',
                'version' => '1.1',
                'exclusive' => false,
                'withComments' => true,
            ],
            default => null,
        };
    }

    /**
     * @return array{resolvable:bool, issues:list<string>, parseError:?string}
     */
    private static function relationshipTransformReferenceUriPolicy(string $referenceUri): array
    {
        if ($referenceUri === '') {
            return [
                'resolvable' => false,
                'issues' => ['missing-reference-uri'],
                'parseError' => null,
            ];
        }

        $issues = [];
        $parseError = null;
        $resolvable = true;
        if (str_starts_with($referenceUri, '#')) {
            $issues[] = 'relationship-transform-reference-same-document';
            $resolvable = false;
        } elseif (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $referenceUri) === 1
            || str_starts_with($referenceUri, '//')
        ) {
            $issues[] = 'relationship-transform-reference-external-uri';
            $resolvable = false;
        }

        if ($resolvable && str_starts_with($referenceUri, '/')) {
            try {
                self::assertRelationshipTransformAbsolutePartUriShape($referenceUri);
            } catch (\InvalidArgumentException $exception) {
                $issues[] = 'relationship-transform-reference-invalid-part-name';
                $parseError = $exception->getMessage();
                $resolvable = false;
            }
        }

        return [
            'resolvable' => $resolvable,
            'issues' => $issues,
            'parseError' => $parseError,
        ];
    }

    private static function assertRelationshipTransformAbsolutePartUriShape(string $referenceUri): void
    {
        $path = substr($referenceUri, 0, strcspn($referenceUri, '?#'));
        if ($path === '/') {
            throw new \InvalidArgumentException('OPC relationship transform reference URI must identify a package part');
        }

        $segments = explode('/', $path);
        array_shift($segments);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('OPC relationship transform reference URI must not contain empty or dot path segments');
            }

            if (str_ends_with($segment, '.')) {
                throw new \InvalidArgumentException('OPC relationship transform reference URI segments must not end with a dot');
            }
        }
    }

    /**
     * @return array{contentType:?string, issues:list<string>, parseError:?string}
     */
    private static function referenceContentTypeQuery(string $referenceUri): array
    {
        $queryStart = strpos($referenceUri, '?');
        if ($queryStart === false) {
            return [
                'contentType' => null,
                'issues' => [],
                'parseError' => null,
            ];
        }

        $queryEnd = strpos($referenceUri, '#', $queryStart + 1);
        $query = $queryEnd === false
            ? substr($referenceUri, $queryStart + 1)
            : substr($referenceUri, $queryStart + 1, $queryEnd - $queryStart - 1);

        $contentType = null;
        $issues = [];
        $parseError = null;
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            try {
                $name = self::decodeReferenceQueryComponent($parts[0]);
                $value = self::decodeReferenceQueryComponent($parts[1] ?? '');
            } catch (\InvalidArgumentException $exception) {
                self::appendUniqueString($issues, 'invalid-reference-content-type-query');
                $parseError = $exception->getMessage();
                continue;
            }

            if ($name !== 'ContentType') {
                continue;
            }

            if ($contentType !== null) {
                self::appendUniqueString($issues, 'duplicate-reference-content-type-query');
                continue;
            }

            if ($value === '') {
                self::appendUniqueString($issues, 'empty-reference-content-type-query');
                continue;
            }

            $contentType = $value;
            if (!OpcContentTypes::isValidContentType($value)) {
                self::appendUniqueString($issues, 'invalid-reference-content-type-query');
            }
        }

        return [
            'contentType' => $contentType,
            'issues' => $issues,
            'parseError' => $parseError,
        ];
    }

    private static function decodeReferenceQueryComponent(string $value): string
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1) {
            throw new \InvalidArgumentException('OPC signature reference query contains malformed percent escape');
        }

        return rawurldecode($value);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function normalizeSelectorValues(array $values, string $label): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException($label . ' values must be strings');
            }

            if ($value === '') {
                throw new \InvalidArgumentException($label . ' values must be non-empty strings');
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private static function assertSelectorSourceId(string $sourceId): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $sourceId) !== 1) {
            throw new \InvalidArgumentException('OPC relationship selector SourceId must be an XML NCName-style identifier');
        }
    }

    /**
     * @return list<string>
     */
    private static function selectorSourceTypeIssues(string $sourceType): array
    {
        $preflight = (new OpcRelationship('rIdSelectorSourceType', $sourceType, 'target.xml'))->relationshipTypePreflight();
        $issues = [];
        foreach ($preflight['issues'] as $issue) {
            $issues[] = str_starts_with($issue, 'relationship-type-')
                ? 'source-type-' . substr($issue, strlen('relationship-type-'))
                : 'source-type-' . $issue;
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param list<OpcRelationship> $relationships
     */
    private static function relationshipTransformXml(array $relationships): string
    {
        $xml = '<Relationships xmlns="' . self::escapeXmlAttribute(OpcRelationships::NAMESPACE_URI) . '">';
        foreach ($relationships as $relationship) {
            $xml .= '<Relationship'
                . ' Id="' . self::escapeXmlAttribute($relationship->id) . '"'
                . ' Target="' . self::escapeXmlAttribute($relationship->target) . '"'
                . ($relationship->targetMode === OpcRelationship::TARGET_MODE_INTERNAL
                    ? ''
                    : ' TargetMode="' . self::escapeXmlAttribute($relationship->targetMode) . '"')
                . ' Type="' . self::escapeXmlAttribute($relationship->type) . '"'
                . '></Relationship>';
        }

        return $xml . '</Relationships>';
    }

    private static function escapeXmlAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function isRelationshipPartName(string $name): bool
    {
        return OpcRelationships::isRelationshipPartName($name);
    }

    private static function isReservedRelationshipDirectoryPartName(string $name): bool
    {
        return str_contains(OpcPackagePath::canonicalPartName($name), '/_rels/');
    }

    private static function isRelationshipPartNameCandidate(string $name): bool
    {
        $name = OpcPackagePath::canonicalPartName($name);

        return self::partNameEquivalenceKey($name) === '/_rels/.rels'
            || (str_ends_with(strtolower($name), '.rels') && str_contains($name, '/_rels/'));
    }

    private static function isContentTypesItemName(string $partName): bool
    {
        return self::partNameEquivalenceKey(OpcPackagePath::canonicalPartName($partName)) === '/[content_types].xml';
    }

    /**
     * @param ?array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function zipExtraFieldManifestEntryFields(?array $entry): array
    {
        $issues = $entry === null ? [] : self::zipExtraFieldEntryIssues($entry);

        return [
            'zipExtraFieldIssues' => $issues,
            'centralExtraFieldIds' => $entry['centralExtraFieldIds'] ?? [],
            'centralExtraFieldIdHexes' => $entry['centralExtraFieldIdHexes'] ?? [],
            'localExtraFieldIds' => $entry['localExtraFieldIds'] ?? [],
            'localExtraFieldIdHexes' => $entry['localExtraFieldIdHexes'] ?? [],
            'duplicateCentralExtraFieldIds' => $entry['duplicateCentralExtraFieldIds'] ?? [],
            'duplicateCentralExtraFieldIdHexes' => $entry['duplicateCentralExtraFieldIdHexes'] ?? [],
            'duplicateLocalExtraFieldIds' => $entry['duplicateLocalExtraFieldIds'] ?? [],
            'duplicateLocalExtraFieldIdHexes' => $entry['duplicateLocalExtraFieldIdHexes'] ?? [],
            'centralOnlyExtraFieldIds' => $entry['centralOnlyExtraFieldIds'] ?? [],
            'centralOnlyExtraFieldIdHexes' => $entry['centralOnlyExtraFieldIdHexes'] ?? [],
            'localOnlyExtraFieldIds' => $entry['localOnlyExtraFieldIds'] ?? [],
            'localOnlyExtraFieldIdHexes' => $entry['localOnlyExtraFieldIdHexes'] ?? [],
            'mismatchedExtraFieldValueIds' => $entry['mismatchedExtraFieldValueIds'] ?? [],
            'mismatchedExtraFieldValueIdHexes' => $entry['mismatchedExtraFieldValueIdHexes'] ?? [],
            'hasDuplicateExtraFieldIds' => (bool) ($entry['hasDuplicateExtraFieldIds'] ?? false),
            'hasMismatchedExtraFieldIds' => (bool) ($entry['hasMismatchedExtraFieldIds'] ?? false),
            'hasMismatchedExtraFieldValues' => (bool) ($entry['hasMismatchedExtraFieldValues'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private static function zipExtraFieldEntryIssues(array $entry): array
    {
        $issues = [];
        foreach ($entry['issues'] ?? [] as $issue) {
            if (is_string($issue)) {
                self::appendUniqueString($issues, $issue);
            }
        }
        if (($entry['duplicateCentralExtraFieldIds'] ?? []) !== []) {
            self::appendUniqueString($issues, 'duplicate-central-extra-field-ids');
        }
        if (($entry['duplicateLocalExtraFieldIds'] ?? []) !== []) {
            self::appendUniqueString($issues, 'duplicate-local-extra-field-ids');
        }
        if (($entry['centralOnlyExtraFieldIds'] ?? []) !== []) {
            self::appendUniqueString($issues, 'central-only-extra-field-ids');
        }
        if (($entry['localOnlyExtraFieldIds'] ?? []) !== []) {
            self::appendUniqueString($issues, 'local-only-extra-field-ids');
        }
        if (($entry['mismatchedExtraFieldValueIds'] ?? []) !== []) {
            self::appendUniqueString($issues, 'central-local-extra-field-value-mismatch');
        }
        if (($entry['localHeaderAvailable'] ?? true) === false) {
            self::appendUniqueString($issues, 'extra-field-local-header-unavailable');
        }

        return $issues;
    }

    /**
     * @param ?array<string, mixed> $extraFields
     * @return array<string, mixed>
     */
    private static function zipExtraFieldManifestSummaryFields(?array $extraFields, ?string $preflightError): array
    {
        $issues = self::zipExtraFieldSummaryIssues($extraFields, $preflightError);

        return [
            'zipExtraFieldsValid' => $extraFields !== null && $issues === [],
            'zipExtraFieldIssues' => $issues,
            'zipExtraFieldPreflightError' => $preflightError,
            'zipExtraFieldEntryCount' => (int) ($extraFields['extraFieldEntryCount'] ?? 0),
            'zipDuplicateExtraFieldEntryCount' => (int) ($extraFields['duplicateExtraFieldEntryCount'] ?? 0),
            'zipDuplicateCentralExtraFieldEntryCount' => (int) ($extraFields['duplicateCentralExtraFieldEntryCount'] ?? 0),
            'zipDuplicateLocalExtraFieldEntryCount' => (int) ($extraFields['duplicateLocalExtraFieldEntryCount'] ?? 0),
            'zipMismatchedExtraFieldEntryCount' => (int) ($extraFields['mismatchedExtraFieldEntryCount'] ?? 0),
            'zipMismatchedExtraFieldValueEntryCount' => (int) ($extraFields['mismatchedExtraFieldValueEntryCount'] ?? 0),
            'zipCentralOnlyExtraFieldEntryCount' => (int) ($extraFields['centralOnlyExtraFieldEntryCount'] ?? 0),
            'zipLocalOnlyExtraFieldEntryCount' => (int) ($extraFields['localOnlyExtraFieldEntryCount'] ?? 0),
            'zipExtraFieldIdCount' => (int) ($extraFields['extraFieldIdCount'] ?? 0),
            'zipCentralExtraFieldIdCount' => (int) ($extraFields['centralExtraFieldIdCount'] ?? 0),
            'zipLocalExtraFieldIdCount' => (int) ($extraFields['localExtraFieldIdCount'] ?? 0),
            'zipSharedExtraFieldIdCount' => (int) ($extraFields['sharedExtraFieldIdCount'] ?? 0),
            'zipCentralOnlyExtraFieldIdCount' => (int) ($extraFields['centralOnlyExtraFieldIdCount'] ?? 0),
            'zipLocalOnlyExtraFieldIdCount' => (int) ($extraFields['localOnlyExtraFieldIdCount'] ?? 0),
            'zipExtraFieldIdHexes' => $extraFields['extraFieldIdHexes'] ?? [],
            'zipCentralExtraFieldIdHexes' => $extraFields['centralExtraFieldIdHexes'] ?? [],
            'zipLocalExtraFieldIdHexes' => $extraFields['localExtraFieldIdHexes'] ?? [],
            'zipSharedExtraFieldIdHexes' => $extraFields['sharedExtraFieldIdHexes'] ?? [],
            'zipCentralOnlyExtraFieldIdHexes' => $extraFields['centralOnlyExtraFieldIdHexes'] ?? [],
            'zipLocalOnlyExtraFieldIdHexes' => $extraFields['localOnlyExtraFieldIdHexes'] ?? [],
            'zipExtraFieldIdUsage' => $extraFields['extraFieldIdUsage'] ?? [],
            'zipExtraFields' => $extraFields,
        ];
    }

    /**
     * @param ?array<string, mixed> $extraFields
     * @return list<string>
     */
    private static function zipExtraFieldSummaryIssues(?array $extraFields, ?string $preflightError): array
    {
        $issues = [];
        if ($preflightError !== null) {
            $issues[] = 'extra-field-policy-preflight-error';
        }
        if ($extraFields === null) {
            return $issues;
        }
        if ((int) ($extraFields['duplicateExtraFieldEntryCount'] ?? 0) > 0) {
            self::appendUniqueString($issues, 'duplicate-extra-field-ids');
        }
        if ((int) ($extraFields['mismatchedExtraFieldEntryCount'] ?? 0) > 0) {
            self::appendUniqueString($issues, 'central-local-extra-field-id-mismatch');
        }
        if ((int) ($extraFields['mismatchedExtraFieldValueEntryCount'] ?? 0) > 0) {
            self::appendUniqueString($issues, 'central-local-extra-field-value-mismatch');
        }

        return $issues;
    }

    private static function zipEntryManifestRole(
        string $partName,
        bool $isDirectory,
        bool $contentTypesItem,
        bool $relationshipPart,
        bool $relationshipPartCandidate,
        ?string $relationshipSource,
        ?string $contentType = null
    ): string {
        if ($isDirectory) {
            return 'directory';
        }

        if ($contentTypesItem) {
            return 'content-types';
        }

        if ($relationshipPart && $relationshipSource === '/') {
            return 'package-relationships';
        }

        if ($relationshipPart) {
            return 'part-relationships';
        }

        if ($relationshipPartCandidate) {
            return 'invalid-relationship-part';
        }

        if (self::isReservedRelationshipDirectoryPartName($partName)) {
            return 'reserved-relationship-directory-part';
        }

        if (str_starts_with($partName, '/docProps/')) {
            return 'document-properties';
        }

        if (str_starts_with($partName, '/_xmlsignatures/')) {
            return 'digital-signature';
        }

        if (self::isEmbeddedPackageCandidate($partName, $contentType)) {
            return 'embedded-package-candidate';
        }

        if (self::isMediaPartCandidate($partName, $contentType)) {
            return 'media';
        }

        if (self::isXmlLikePartName($partName, $contentType)) {
            return 'xml-part';
        }

        return 'binary-part';
    }

    private static function zipEntryManifestHandoffKind(string $role, string $partName): string
    {
        return match ($role) {
            'directory' => 'directory',
            'invalid-opc-part', 'invalid-relationship-part', 'reserved-relationship-directory-part' => 'blocked',
            'content-types' => 'content-types+xml',
            'package-relationships', 'part-relationships' => 'relationships+xml',
            'embedded-package-candidate' => 'embedded-package',
            'media' => 'media',
            'xml-part' => 'xml',
            default => self::isXmlLikePartName($partName) ? 'xml' : 'binary',
        };
    }

    private static function zipCompressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    /**
     * @param list<array{entryName:string, partName:?string, role:string, handoffKind:string, compressionMethod:int, compressionMethodName:string, compressedSize:int, uncompressedSize:int}> $entries
     * @return list<array{entryName:string, partName:?string, role:string, handoffKind:string, compressionMethod:int, compressionMethodName:string, compressedSize:int, uncompressedSize:int}>
     */
    private static function largestZipManifestPayloadEntries(array $entries, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        usort(
            $entries,
            static function (array $left, array $right): int {
                $byUncompressedSize = $right['uncompressedSize'] <=> $left['uncompressedSize'];
                if ($byUncompressedSize !== 0) {
                    return $byUncompressedSize;
                }

                $byCompressedSize = $right['compressedSize'] <=> $left['compressedSize'];
                if ($byCompressedSize !== 0) {
                    return $byCompressedSize;
                }

                $byPartName = strcmp((string) ($left['partName'] ?? ''), (string) ($right['partName'] ?? ''));
                if ($byPartName !== 0) {
                    return $byPartName;
                }

                return strcmp($left['entryName'], $right['entryName']);
            }
        );

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param array<string, array{entryCount:int, compressedBytes:int, uncompressedBytes:int}> $buckets
     */
    private static function incrementZipEntryManifestByteBucket(
        array &$buckets,
        string $bucket,
        int $compressedSize,
        int $uncompressedSize
    ): void {
        $buckets[$bucket] ??= [
            'entryCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
        ];

        $buckets[$bucket]['entryCount']++;
        $buckets[$bucket]['compressedBytes'] += $compressedSize;
        $buckets[$bucket]['uncompressedBytes'] += $uncompressedSize;
    }

    private static function zipEntryManifestDirectoryRoot(string $entryName): string
    {
        $entryName = ltrim($entryName, '/');
        if ($entryName === '') {
            return '/';
        }

        $separator = strpos($entryName, '/');
        if ($separator === false) {
            return '/';
        }

        return substr($entryName, 0, $separator + 1);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function zipEntrySourceRecordManifest(array $entries): array
    {
        $manifestVersion = 'zip-opc-source-record-manifest-v1';
        $manifestEntries = [];
        $localRecordBytes = 0;
        $localHeaderBytes = 0;
        $compressedDataBytes = 0;
        $dataDescriptorBytes = 0;
        $centralDirectoryRecordBytes = 0;
        $sourceRecordBytes = 0;
        $dataDescriptorEntryCount = 0;
        $unknownSourceRecordEntryCount = 0;
        $byteCountsAreExact = true;
        $localHeaderReviewFieldBytes = 0;
        $centralDirectoryReviewFieldBytes = 0;
        $reviewFieldBytes = 0;
        $localHeaderReviewFieldByteCountsAreExact = true;
        $centralDirectoryReviewFieldByteCountsAreExact = true;
        $reviewFieldBytesByRole = [];
        $reviewFieldBytesByHandoffKind = [];
        $handoffKindSummaries = [];
        $recordReviewFieldBucket = static function (
            array &$buckets,
            string $bucketName,
            int $localHeaderBytes,
            int $centralDirectoryBytes,
            bool $byteCountsAreExact
        ): void {
            $bucketBytes = $localHeaderBytes + $centralDirectoryBytes;
            if ($bucketBytes === 0) {
                return;
            }

            $buckets[$bucketName] ??= [
                'reviewFieldEntryCount' => 0,
                'reviewFieldByteCountsAreExact' => true,
                'localHeaderReviewFieldBytes' => 0,
                'centralDirectoryReviewFieldBytes' => 0,
                'reviewFieldBytes' => 0,
            ];

            $buckets[$bucketName]['reviewFieldEntryCount']++;
            $buckets[$bucketName]['reviewFieldByteCountsAreExact'] =
                $buckets[$bucketName]['reviewFieldByteCountsAreExact'] && $byteCountsAreExact;
            $buckets[$bucketName]['localHeaderReviewFieldBytes'] += $localHeaderBytes;
            $buckets[$bucketName]['centralDirectoryReviewFieldBytes'] += $centralDirectoryBytes;
            $buckets[$bucketName]['reviewFieldBytes'] += $bucketBytes;
        };

        foreach ($entries as $entry) {
            $entryName = is_string($entry['entryName'] ?? null) ? $entry['entryName'] : '';
            $partName = is_string($entry['partName'] ?? null) ? $entry['partName'] : null;
            $role = is_string($entry['role'] ?? null) ? $entry['role'] : 'unknown';
            $handoffKind = is_string($entry['handoffKind'] ?? null) ? $entry['handoffKind'] : 'unknown';
            $entryLocalRecordBytes = is_int($entry['localRecordBytes'] ?? null)
                ? $entry['localRecordBytes']
                : null;
            $entryLocalHeaderBytes = is_int($entry['localHeaderLength'] ?? null)
                ? $entry['localHeaderLength']
                : null;
            $entryCompressedDataBytes = (($entry['byteCountsAreExact'] ?? true) === true)
                && is_int($entry['compressedSize'] ?? null)
                    ? $entry['compressedSize']
                    : null;
            $entryDataDescriptorBytes = is_int($entry['dataDescriptorBytes'] ?? null)
                ? $entry['dataDescriptorBytes']
                : 0;
            $entryCentralDirectoryRecordBytes = is_int($entry['centralDirectoryRecordBytes'] ?? null)
                ? $entry['centralDirectoryRecordBytes']
                : null;
            $entryLocalHeaderReviewFieldBytes = is_int($entry['localHeaderReviewFieldBytes'] ?? null)
                ? $entry['localHeaderReviewFieldBytes']
                : null;
            $entryCentralDirectoryReviewFieldBytes = is_int($entry['centralDirectoryReviewFieldBytes'] ?? null)
                ? $entry['centralDirectoryReviewFieldBytes']
                : null;
            $entryReviewFieldByteCountsAreExact = $entryLocalHeaderReviewFieldBytes !== null
                && $entryCentralDirectoryReviewFieldBytes !== null;
            $entryKnownLocalHeaderReviewFieldBytes = $entryLocalHeaderReviewFieldBytes ?? 0;
            $entryKnownCentralDirectoryReviewFieldBytes = $entryCentralDirectoryReviewFieldBytes ?? 0;
            $entryKnownReviewFieldBytes = $entryKnownLocalHeaderReviewFieldBytes
                + $entryKnownCentralDirectoryReviewFieldBytes;
            $entryReviewFieldBytes = $entryReviewFieldByteCountsAreExact ? $entryKnownReviewFieldBytes : null;
            $entryByteCountsAreExact = ($entry['byteCountsAreExact'] ?? true) === true
                && $entryLocalRecordBytes !== null
                && $entryLocalHeaderBytes !== null
                && $entryCompressedDataBytes !== null
                && $entryCentralDirectoryRecordBytes !== null;
            $entrySourceRecordBytes = $entryLocalRecordBytes !== null && $entryCentralDirectoryRecordBytes !== null
                ? $entryLocalRecordBytes + $entryCentralDirectoryRecordBytes
                : null;
            $sourceRecordIssues = [];
            if (!$entryByteCountsAreExact) {
                $sourceRecordIssues[] = 'source-record-byte-count-not-exact';
            }
            if ($entryLocalRecordBytes === null) {
                $sourceRecordIssues[] = 'local-record-byte-count-unavailable';
            }
            if ($entryCentralDirectoryRecordBytes === null) {
                $sourceRecordIssues[] = 'central-directory-record-byte-count-unavailable';
            }
            if (!$entryReviewFieldByteCountsAreExact) {
                $sourceRecordIssues[] = 'source-record-review-field-byte-count-not-exact';
            }
            if ($entryLocalHeaderReviewFieldBytes === null) {
                $sourceRecordIssues[] = 'local-header-review-field-byte-count-unavailable';
            }
            if ($entryCentralDirectoryReviewFieldBytes === null) {
                $sourceRecordIssues[] = 'central-directory-review-field-byte-count-unavailable';
            }

            if ($entryByteCountsAreExact) {
                $localRecordBytes += $entryLocalRecordBytes;
                $localHeaderBytes += $entryLocalHeaderBytes;
                $compressedDataBytes += $entryCompressedDataBytes;
                $sourceRecordBytes += $entrySourceRecordBytes ?? 0;
            } else {
                $byteCountsAreExact = false;
                ++$unknownSourceRecordEntryCount;
                $localRecordBytes += $entryLocalRecordBytes ?? 0;
                $localHeaderBytes += $entryLocalHeaderBytes ?? 0;
                $compressedDataBytes += $entryCompressedDataBytes ?? 0;
                $sourceRecordBytes += $entrySourceRecordBytes ?? 0;
            }
            $dataDescriptorBytes += $entryDataDescriptorBytes;
            if ($entryDataDescriptorBytes > 0) {
                ++$dataDescriptorEntryCount;
            }
            $centralDirectoryRecordBytes += $entryCentralDirectoryRecordBytes ?? 0;
            if ($entryLocalHeaderReviewFieldBytes === null) {
                $localHeaderReviewFieldByteCountsAreExact = false;
            }
            if ($entryCentralDirectoryReviewFieldBytes === null) {
                $centralDirectoryReviewFieldByteCountsAreExact = false;
            }
            $localHeaderReviewFieldBytes += $entryKnownLocalHeaderReviewFieldBytes;
            $centralDirectoryReviewFieldBytes += $entryKnownCentralDirectoryReviewFieldBytes;
            $reviewFieldBytes += $entryKnownReviewFieldBytes;
            $recordReviewFieldBucket(
                $reviewFieldBytesByRole,
                $role,
                $entryKnownLocalHeaderReviewFieldBytes,
                $entryKnownCentralDirectoryReviewFieldBytes,
                $entryReviewFieldByteCountsAreExact,
            );
            $recordReviewFieldBucket(
                $reviewFieldBytesByHandoffKind,
                $handoffKind,
                $entryKnownLocalHeaderReviewFieldBytes,
                $entryKnownCentralDirectoryReviewFieldBytes,
                $entryReviewFieldByteCountsAreExact,
            );
            self::recordZipEntrySourceRecordHandoffKindSummary(
                $handoffKindSummaries,
                $handoffKind,
                $entryName,
                $partName,
                $entryByteCountsAreExact,
                $entryLocalRecordBytes,
                $entryLocalHeaderBytes,
                $entryCompressedDataBytes,
                $entryDataDescriptorBytes,
                $entryCentralDirectoryRecordBytes,
                $entrySourceRecordBytes,
            );

            $manifestEntries[] = [
                'entryIndex' => $entry['entryIndex'] ?? null,
                'entryName' => $entryName,
                'partName' => $partName,
                'role' => $role,
                'handoffKind' => $handoffKind,
                'byteCountsAreExact' => $entryByteCountsAreExact,
                'localRecordOffset' => $entry['localRecordOffset'] ?? null,
                'localRecordBytes' => $entryLocalRecordBytes,
                'localRecordEnd' => $entry['localRecordEnd'] ?? null,
                'localRecordSha256' => $entry['localRecordSha256'] ?? null,
                'localHeaderBytes' => $entryLocalHeaderBytes,
                'localHeaderSha256' => $entry['localHeaderSha256'] ?? null,
                'compressedDataOffset' => $entry['compressedDataOffset'] ?? null,
                'compressedDataBytes' => $entryCompressedDataBytes,
                'compressedDataEnd' => $entry['compressedDataEnd'] ?? null,
                'compressedDataSha256' => $entry['compressedDataSha256'] ?? null,
                'usesDataDescriptor' => ($entry['usesDataDescriptor'] ?? $entry['zipUsesDataDescriptorFlag'] ?? false) === true,
                'dataDescriptorOffset' => $entry['dataDescriptorOffset'] ?? null,
                'dataDescriptorBytes' => $entryDataDescriptorBytes,
                'dataDescriptorEnd' => $entry['dataDescriptorEnd'] ?? null,
                'dataDescriptorSha256' => $entry['dataDescriptorSha256'] ?? null,
                'centralDirectoryRecordOffset' => $entry['centralDirectoryRecordOffset'] ?? null,
                'centralDirectoryRecordBytes' => $entryCentralDirectoryRecordBytes,
                'centralDirectoryRecordEnd' => $entry['centralDirectoryRecordEnd'] ?? null,
                'centralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'] ?? null,
                'sourceRecordBytes' => $entrySourceRecordBytes,
                'reviewFieldByteCountsAreExact' => $entryReviewFieldByteCountsAreExact,
                'localHeaderReviewFieldBytes' => $entryLocalHeaderReviewFieldBytes,
                'centralDirectoryReviewFieldBytes' => $entryCentralDirectoryReviewFieldBytes,
                'reviewFieldBytes' => $entryReviewFieldBytes,
                'knownReviewFieldBytes' => $entryKnownReviewFieldBytes,
                'sourceRecordIssues' => $sourceRecordIssues,
            ];
        }
        ksort($reviewFieldBytesByRole, SORT_STRING);
        ksort($reviewFieldBytesByHandoffKind, SORT_STRING);
        $reviewFieldByteCountsAreExact = $localHeaderReviewFieldByteCountsAreExact
            && $centralDirectoryReviewFieldByteCountsAreExact;
        $handoffKindSummaries = self::zipEntrySourceRecordHandoffKindSummaries($handoffKindSummaries);

        $payload = [
            'manifestVersion' => $manifestVersion,
            'entryCount' => count($manifestEntries),
            'byteCountsAreExact' => $byteCountsAreExact,
            'unknownSourceRecordEntryCount' => $unknownSourceRecordEntryCount,
            'localRecordBytes' => $byteCountsAreExact ? $localRecordBytes : null,
            'knownLocalRecordBytes' => $localRecordBytes,
            'localHeaderBytes' => $byteCountsAreExact ? $localHeaderBytes : null,
            'knownLocalHeaderBytes' => $localHeaderBytes,
            'compressedDataBytes' => $byteCountsAreExact ? $compressedDataBytes : null,
            'knownCompressedDataBytes' => $compressedDataBytes,
            'dataDescriptorBytes' => $dataDescriptorBytes,
            'dataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
            'knownCentralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
            'sourceRecordBytes' => $byteCountsAreExact ? $sourceRecordBytes : null,
            'knownSourceRecordBytes' => $sourceRecordBytes,
            'reviewFieldByteCountsAreExact' => $reviewFieldByteCountsAreExact,
            'localHeaderReviewFieldBytes' => $localHeaderReviewFieldByteCountsAreExact
                ? $localHeaderReviewFieldBytes
                : null,
            'knownLocalHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
            'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldByteCountsAreExact
                ? $centralDirectoryReviewFieldBytes
                : null,
            'knownCentralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
            'reviewFieldBytes' => $reviewFieldByteCountsAreExact ? $reviewFieldBytes : null,
            'knownReviewFieldBytes' => $reviewFieldBytes,
            'reviewFieldBytesByRole' => $reviewFieldBytesByRole,
            'reviewFieldBytesByHandoffKind' => $reviewFieldBytesByHandoffKind,
            'handoffKindSummaryCount' => count($handoffKindSummaries),
            'handoffKindSummaries' => $handoffKindSummaries,
            'entries' => $manifestEntries,
        ];
        $manifestJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return [
            ...$payload,
            'manifestSha256' => hash('sha256', $manifestJson),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $summaries
     */
    private static function recordZipEntrySourceRecordHandoffKindSummary(
        array &$summaries,
        string $handoffKind,
        string $entryName,
        ?string $partName,
        bool $entryByteCountsAreExact,
        ?int $localRecordBytes,
        ?int $localHeaderBytes,
        ?int $compressedDataBytes,
        int $dataDescriptorBytes,
        ?int $centralDirectoryRecordBytes,
        ?int $sourceRecordBytes
    ): void {
        $summaries[$handoffKind] ??= [
            'handoffKind' => $handoffKind,
            'entryCount' => 0,
            'unknownSourceRecordEntryCount' => 0,
            'byteCountsAreExact' => true,
            'localRecordBytes' => 0,
            'knownLocalRecordBytes' => 0,
            'localHeaderBytes' => 0,
            'knownLocalHeaderBytes' => 0,
            'compressedDataBytes' => 0,
            'knownCompressedDataBytes' => 0,
            'dataDescriptorBytes' => 0,
            'dataDescriptorEntryCount' => 0,
            'centralDirectoryRecordBytes' => 0,
            'knownCentralDirectoryRecordBytes' => 0,
            'sourceRecordBytes' => 0,
            'knownSourceRecordBytes' => 0,
            'entryNames' => [],
            'partNames' => [],
        ];

        $summary =& $summaries[$handoffKind];
        $summary['entryCount']++;
        if (!$entryByteCountsAreExact) {
            $summary['byteCountsAreExact'] = false;
            $summary['unknownSourceRecordEntryCount']++;
        }

        $summary['knownLocalRecordBytes'] += $localRecordBytes ?? 0;
        $summary['knownLocalHeaderBytes'] += $localHeaderBytes ?? 0;
        $summary['knownCompressedDataBytes'] += $compressedDataBytes ?? 0;
        $summary['dataDescriptorBytes'] += $dataDescriptorBytes;
        $summary['knownCentralDirectoryRecordBytes'] += $centralDirectoryRecordBytes ?? 0;
        $summary['knownSourceRecordBytes'] += $sourceRecordBytes ?? 0;
        if ($entryByteCountsAreExact) {
            $summary['localRecordBytes'] += $localRecordBytes ?? 0;
            $summary['localHeaderBytes'] += $localHeaderBytes ?? 0;
            $summary['compressedDataBytes'] += $compressedDataBytes ?? 0;
            $summary['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes ?? 0;
            $summary['sourceRecordBytes'] += $sourceRecordBytes ?? 0;
        }
        if ($dataDescriptorBytes > 0) {
            $summary['dataDescriptorEntryCount']++;
        }
        self::appendUniqueString($summary['entryNames'], $entryName);
        if ($partName !== null) {
            self::appendUniqueString($summary['partNames'], $partName);
        }
        unset($summary);
    }

    /**
     * @param array<string, array<string, mixed>> $summaries
     * @return list<array<string, mixed>>
     */
    private static function zipEntrySourceRecordHandoffKindSummaries(array $summaries): array
    {
        ksort($summaries, SORT_STRING);
        foreach ($summaries as &$summary) {
            if (($summary['byteCountsAreExact'] ?? true) !== true) {
                $summary['localRecordBytes'] = null;
                $summary['localHeaderBytes'] = null;
                $summary['compressedDataBytes'] = null;
                $summary['centralDirectoryRecordBytes'] = null;
                $summary['sourceRecordBytes'] = null;
            }
            sort($summary['entryNames'], SORT_STRING);
            sort($summary['partNames'], SORT_STRING);
        }
        unset($summary);

        return array_values($summaries);
    }

    private static function recordZipEntryManifestDirectoryRootSummary(
        array &$summaries,
        string $directoryRoot,
        array $entry
    ): void {
        $summaries[$directoryRoot] ??= [
            'directoryRoot' => $directoryRoot,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'packagePartCount' => 0,
            'validEntryCount' => 0,
            'invalidEntryCount' => 0,
            'unknownByteCountEntryCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'roleCounts' => [],
            'handoffKindCounts' => [],
            'issueCounts' => [],
            'issues' => [],
            'entryNames' => [],
            'partNames' => [],
        ];

        self::recordZipEntryManifestContentSummaryEntry($summaries[$directoryRoot], $entry);

        if (($entry['valid'] ?? true) === true) {
            $summaries[$directoryRoot]['validEntryCount']++;
        } else {
            $summaries[$directoryRoot]['invalidEntryCount']++;
        }
        if (($entry['byteCountsAreExact'] ?? true) === false) {
            $summaries[$directoryRoot]['unknownByteCountEntryCount']++;
        }

        foreach (array_values(array_filter($entry['issues'] ?? [], 'is_string')) as $issue) {
            self::appendUniqueString($summaries[$directoryRoot]['issues'], $issue);
            $summaries[$directoryRoot]['issueCounts'][$issue] =
                ($summaries[$directoryRoot]['issueCounts'][$issue] ?? 0) + 1;
        }
    }

    private static function zipEntryManifestDirectoryRootSummaries(array $summaries): array
    {
        ksort($summaries, SORT_STRING);
        foreach ($summaries as &$summary) {
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['handoffKindCounts'], SORT_STRING);
            ksort($summary['issueCounts'], SORT_STRING);
            sort($summary['issues'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            sort($summary['partNames'], SORT_STRING);
        }
        unset($summary);

        return array_values($summaries);
    }

    private static function recordZipEntryManifestContentTypeSourceSummary(
        array &$summaries,
        string $contentTypeSource,
        array $entry
    ): void {
        $summaries[$contentTypeSource] ??= [
            'contentTypeSource' => $contentTypeSource,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'packagePartCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'roleCounts' => [],
            'handoffKindCounts' => [],
            'entryNames' => [],
            'partNames' => [],
        ];

        self::recordZipEntryManifestContentSummaryEntry($summaries[$contentTypeSource], $entry);
    }

    private static function recordZipEntryManifestContentTypeSummary(
        array &$summaries,
        string $contentType,
        string $contentTypeSource,
        array $entry
    ): void {
        $summaries[$contentType] ??= [
            'contentType' => $contentType,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'packagePartCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'contentTypeSourceCounts' => [],
            'roleCounts' => [],
            'handoffKindCounts' => [],
            'entryNames' => [],
            'partNames' => [],
        ];

        $summaries[$contentType]['contentTypeSourceCounts'][$contentTypeSource] =
            ($summaries[$contentType]['contentTypeSourceCounts'][$contentTypeSource] ?? 0) + 1;
        self::recordZipEntryManifestContentSummaryEntry($summaries[$contentType], $entry);
    }

    private static function recordZipEntryManifestExtensionSummary(
        array &$summaries,
        string $extensionKey,
        ?string $extension,
        array $entry
    ): void {
        $summaries[$extensionKey] ??= [
            'extensionKey' => $extensionKey,
            'extension' => $extension,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'packagePartCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'roleCounts' => [],
            'handoffKindCounts' => [],
            'entryNames' => [],
            'partNames' => [],
        ];

        self::recordZipEntryManifestContentSummaryEntry($summaries[$extensionKey], $entry);
    }

    private static function recordZipEntryManifestContentSummaryEntry(array &$summary, array $entry): void
    {
        $summary['entryCount']++;
        if ($entry['isDirectory']) {
            $summary['directoryEntryCount']++;
        } else {
            $summary['fileEntryCount']++;
        }
        if ($entry['isPackagePart']) {
            $summary['packagePartCount']++;
        }

        $summary['compressedBytes'] += $entry['compressedSize'];
        $summary['uncompressedBytes'] += $entry['uncompressedSize'];
        $summary['roleCounts'][$entry['role']] = ($summary['roleCounts'][$entry['role']] ?? 0) + 1;
        $summary['handoffKindCounts'][$entry['handoffKind']] =
            ($summary['handoffKindCounts'][$entry['handoffKind']] ?? 0) + 1;
        self::appendUniqueString($summary['entryNames'], $entry['entryName']);
        if (is_string($entry['partName'])) {
            self::appendUniqueString($summary['partNames'], $entry['partName']);
        }
    }

    private static function zipEntryManifestContentSummaries(array $summaries): array
    {
        ksort($summaries, SORT_STRING);
        foreach ($summaries as &$summary) {
            if (isset($summary['contentTypeSourceCounts'])) {
                ksort($summary['contentTypeSourceCounts'], SORT_STRING);
            }
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['handoffKindCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            sort($summary['partNames'], SORT_STRING);
        }
        unset($summary);

        return array_values($summaries);
    }

    /**
     * @param array<string, int> $methodCounts
     * @param array<string, list<string>> $entryNamesByMethod
     * @param array<string, list<string>> $methodNamesByRole
     * @param array<string, list<string>> $methodNamesByHandoffKind
     */
    private static function recordZipEntryManifestCompressionMethodProvenance(
        array &$methodCounts,
        array &$entryNamesByMethod,
        array &$methodNamesByRole,
        array &$methodNamesByHandoffKind,
        string $methodName,
        string $entryName,
        string $role,
        string $handoffKind
    ): void {
        $methodCounts[$methodName] = ($methodCounts[$methodName] ?? 0) + 1;
        $entryNamesByMethod[$methodName] ??= [];
        $entryNamesByMethod[$methodName][] = $entryName;
        $methodNamesByRole[$role] ??= [];
        self::appendUniqueString($methodNamesByRole[$role], $methodName);
        $methodNamesByHandoffKind[$handoffKind] ??= [];
        self::appendUniqueString($methodNamesByHandoffKind[$handoffKind], $methodName);
    }

    /**
     * @param array<string, int> $methodCounts
     * @param array<string, list<string>> $entryNamesByMethod
     * @param array<string, list<string>> $methodNamesByRole
     * @param array<string, list<string>> $methodNamesByHandoffKind
     */
    private static function sortZipManifestCompressionMethodProvenance(
        array &$methodCounts,
        array &$entryNamesByMethod,
        array &$methodNamesByRole,
        array &$methodNamesByHandoffKind
    ): void {
        ksort($methodCounts, SORT_STRING);
        ksort($entryNamesByMethod, SORT_STRING);
        foreach ($entryNamesByMethod as &$entryNames) {
            sort($entryNames, SORT_STRING);
        }
        unset($entryNames);

        ksort($methodNamesByRole, SORT_STRING);
        foreach ($methodNamesByRole as &$methodNames) {
            sort($methodNames, SORT_STRING);
        }
        unset($methodNames);

        ksort($methodNamesByHandoffKind, SORT_STRING);
        foreach ($methodNamesByHandoffKind as &$methodNames) {
            sort($methodNames, SORT_STRING);
        }
        unset($methodNames);
    }

    /**
     * @return array<string, mixed>
     */
    private static function zipGeneralPurposeFlagManifestEntry(?int $flags, ?int $localFlags = null): array
    {
        if ($flags === null) {
            return [
                'generalPurposeFlagHex' => null,
                'generalPurposeFlagNames' => [],
                'generalPurposeUnsupportedFlagBits' => null,
                'generalPurposeFlagsSupportedByReader' => null,
                'zipUsesUtf8Names' => null,
                'zipUsesDataDescriptorFlag' => null,
                'zipDeflateOptionFlags' => null,
                'zipDeflateOptionName' => null,
                'zipRequiresGeneralPurposeFlagReview' => null,
                'generalPurposeFlagIssues' => [],
            ];
        }

        $unsupportedFlagBits = $flags & ~0x080e;
        $usesDataDescriptor = ($flags & 0x0008) !== 0;
        $deflateOptionFlags = $flags & 0x0006;
        $issues = [];
        if ($unsupportedFlagBits !== 0) {
            $issues[] = 'unsupported-general-purpose-flags';
        }
        if ($usesDataDescriptor) {
            $issues[] = 'data-descriptor-entry';
        }
        if ($deflateOptionFlags !== 0) {
            $issues[] = 'deflate-option-flags';
        }
        if ($localFlags !== null && $localFlags !== $flags) {
            $issues[] = 'local-header-flags-mismatch';
        }

        return [
            'generalPurposeFlagHex' => sprintf('0x%04x', $flags),
            'generalPurposeFlagNames' => self::zipGeneralPurposeFlagNames($flags),
            'generalPurposeUnsupportedFlagBits' => $unsupportedFlagBits,
            'generalPurposeFlagsSupportedByReader' => $unsupportedFlagBits === 0,
            'zipUsesUtf8Names' => ($flags & 0x0800) !== 0,
            'zipUsesDataDescriptorFlag' => $usesDataDescriptor,
            'zipDeflateOptionFlags' => $deflateOptionFlags,
            'zipDeflateOptionName' => self::zipDeflateOptionFlagName($deflateOptionFlags),
            'zipRequiresGeneralPurposeFlagReview' => $issues !== [],
            'generalPurposeFlagIssues' => $issues,
        ];
    }

    /**
     * @return list<string>
     */
    private static function zipGeneralPurposeFlagNames(int $flags): array
    {
        $names = [];
        $deflateOptionName = self::zipDeflateOptionFlagName($flags & 0x0006);
        if ($deflateOptionName !== null) {
            $names[] = $deflateOptionName;
        }
        if (($flags & 0x0008) !== 0) {
            $names[] = 'data-descriptor';
        }
        if (($flags & 0x0800) !== 0) {
            $names[] = 'utf-8-names';
        }

        $unsupportedFlags = $flags & ~0x080e;
        if ($unsupportedFlags !== 0) {
            $names[] = sprintf('unsupported-0x%04x', $unsupportedFlags);
        }

        return $names;
    }

    private static function zipDeflateOptionFlagName(int $flags): ?string
    {
        return match ($flags) {
            0x0002 => 'deflate-maximum-compression',
            0x0004 => 'deflate-fast',
            0x0006 => 'deflate-super-fast',
            default => null,
        };
    }

    /**
     * @param array<string, int> $flagCounts
     * @param array<string, list<string>> $entryNamesByFlag
     * @param array<string, list<string>> $flagNamesByRole
     * @param array<string, list<string>> $flagNamesByHandoffKind
     * @param array<string, int> $issueCounts
     * @param array<string, list<string>> $entryNamesByIssue
     */
    private static function recordZipEntryManifestGeneralPurposeFlagProvenance(
        array &$flagCounts,
        array &$entryNamesByFlag,
        array &$flagNamesByRole,
        array &$flagNamesByHandoffKind,
        array &$issueCounts,
        array &$entryNamesByIssue,
        array $entry
    ): void {
        $flags = $entry['generalPurposeFlags'] ?? null;
        if (!is_int($flags)) {
            return;
        }

        $flagKey = sprintf('0x%04x', $flags);
        $flagCounts[$flagKey] = ($flagCounts[$flagKey] ?? 0) + 1;
        $entryNamesByFlag[$flagKey] ??= [];
        self::appendUniqueString($entryNamesByFlag[$flagKey], $entry['entryName']);

        foreach (is_array($entry['generalPurposeFlagNames'] ?? null) ? $entry['generalPurposeFlagNames'] : [] as $flagName) {
            if (!is_string($flagName)) {
                continue;
            }

            $flagNamesByRole[$entry['role']] ??= [];
            self::appendUniqueString($flagNamesByRole[$entry['role']], $flagName);
            $flagNamesByHandoffKind[$entry['handoffKind']] ??= [];
            self::appendUniqueString($flagNamesByHandoffKind[$entry['handoffKind']], $flagName);
        }

        foreach (is_array($entry['generalPurposeFlagIssues'] ?? null) ? $entry['generalPurposeFlagIssues'] : [] as $issue) {
            if (!is_string($issue)) {
                continue;
            }

            $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
            $entryNamesByIssue[$issue] ??= [];
            self::appendUniqueString($entryNamesByIssue[$issue], $entry['entryName']);
        }
    }

    /**
     * @param array<string, int> $flagCounts
     * @param array<string, list<string>> $entryNamesByFlag
     * @param array<string, list<string>> $flagNamesByRole
     * @param array<string, list<string>> $flagNamesByHandoffKind
     * @param array<string, int> $issueCounts
     * @param array<string, list<string>> $entryNamesByIssue
     */
    private static function sortZipManifestGeneralPurposeFlagProvenance(
        array &$flagCounts,
        array &$entryNamesByFlag,
        array &$flagNamesByRole,
        array &$flagNamesByHandoffKind,
        array &$issueCounts,
        array &$entryNamesByIssue
    ): void {
        ksort($flagCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByFlag);
        self::sortStringListMap($flagNamesByRole);
        self::sortStringListMap($flagNamesByHandoffKind);
        ksort($issueCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByIssue);
    }

    /**
     * @return list<string>
     */
    private static function zipEntryManifestPathSegments(string $entryName): array
    {
        $path = rtrim($entryName, '/');

        return $path === '' ? [] : explode('/', $path);
    }

    /**
     * @param list<string> $segments
     * @return list<array{pathSegmentIndex:int, segment:string, position:string, isFirst:bool, isLast:bool, isOnly:bool}>
     */
    private static function zipEntryManifestPathSegmentPositionReviews(array $segments): array
    {
        $reviews = [];
        $segmentCount = count($segments);
        foreach ($segments as $segmentIndex => $segment) {
            if (!is_string($segment) || $segment === '') {
                continue;
            }

            $isFirst = $segmentIndex === 0;
            $isLast = $segmentIndex === $segmentCount - 1;
            $isOnly = $segmentCount === 1;
            $position = match (true) {
                $isOnly => 'only',
                $isFirst => 'first',
                $isLast => 'last',
                default => 'middle',
            };

            $reviews[] = [
                'pathSegmentIndex' => $segmentIndex,
                'segment' => $segment,
                'position' => $position,
                'isFirst' => $isFirst,
                'isLast' => $isLast,
                'isOnly' => $isOnly,
            ];
        }

        return $reviews;
    }

    private static function recordZipEntryManifestPathSegmentPositionProvenance(
        array &$roleEntryCounts,
        array &$entryNamesByRole,
        array &$handoffKindEntryCounts,
        array &$entryNamesByHandoffKind,
        array $entry
    ): void {
        $entryPathSegmentPositions = [];
        foreach (is_array($entry['pathSegmentPositionReviews'] ?? null) ? $entry['pathSegmentPositionReviews'] : [] as $review) {
            $position = is_array($review) && is_string($review['position'] ?? null)
                ? $review['position']
                : '';
            if ($position !== '') {
                $entryPathSegmentPositions[$position] = true;
            }
        }

        $role = is_string($entry['role'] ?? null) ? $entry['role'] : 'unknown';
        $handoffKind = is_string($entry['handoffKind'] ?? null) ? $entry['handoffKind'] : 'unknown';
        $entryName = is_string($entry['entryName'] ?? null) ? $entry['entryName'] : '';
        foreach (array_keys($entryPathSegmentPositions) as $position) {
            $roleEntryCounts[$position] ??= [];
            $roleEntryCounts[$position][$role] = ($roleEntryCounts[$position][$role] ?? 0) + 1;
            $entryNamesByRole[$position] ??= [];
            $entryNamesByRole[$position][$role] ??= [];
            self::appendUniqueString($entryNamesByRole[$position][$role], $entryName);

            $handoffKindEntryCounts[$position] ??= [];
            $handoffKindEntryCounts[$position][$handoffKind] =
                ($handoffKindEntryCounts[$position][$handoffKind] ?? 0) + 1;
            $entryNamesByHandoffKind[$position] ??= [];
            $entryNamesByHandoffKind[$position][$handoffKind] ??= [];
            self::appendUniqueString($entryNamesByHandoffKind[$position][$handoffKind], $entryName);
        }
    }

    private static function sortZipEntryManifestPathSegmentPositionProvenance(
        array &$roleEntryCounts,
        array &$entryNamesByRole,
        array &$handoffKindEntryCounts,
        array &$entryNamesByHandoffKind
    ): void {
        foreach ($roleEntryCounts as &$roleCountsByPosition) {
            ksort($roleCountsByPosition, SORT_STRING);
        }
        unset($roleCountsByPosition);
        ksort($roleEntryCounts, SORT_STRING);
        self::sortNestedStringListMap($entryNamesByRole);

        foreach ($handoffKindEntryCounts as &$handoffKindCountsByPosition) {
            ksort($handoffKindCountsByPosition, SORT_STRING);
        }
        unset($handoffKindCountsByPosition);
        ksort($handoffKindEntryCounts, SORT_STRING);
        self::sortNestedStringListMap($entryNamesByHandoffKind);
    }

    private static function recordZipEntryManifestCrc32Summary(array &$summaries, array $entry): void
    {
        $crc32Hex = $entry['crc32Hex'] ?? $entry['centralCrc32Hex'] ?? null;
        if (!is_string($crc32Hex) || $crc32Hex === '') {
            return;
        }

        $summaries[$crc32Hex] ??= [
            'crc32Hex' => $crc32Hex,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'packagePartCount' => 0,
            'unknownByteCountEntryCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'roleCounts' => [],
            'handoffKindCounts' => [],
            'entryNames' => [],
            'partNames' => [],
        ];

        $byteCountsAreExact = ($entry['byteCountsAreExact'] ?? true) === true;
        $compressedSize = $byteCountsAreExact
            ? (int) ($entry['exactCompressedSize'] ?? $entry['compressedSize'] ?? 0)
            : 0;
        $uncompressedSize = $byteCountsAreExact
            ? (int) ($entry['exactUncompressedSize'] ?? $entry['uncompressedSize'] ?? 0)
            : 0;
        $role = (string) ($entry['role'] ?? '');
        $handoffKind = (string) ($entry['handoffKind'] ?? '');

        $summaries[$crc32Hex]['entryCount']++;
        if (($entry['isDirectory'] ?? false) === true) {
            $summaries[$crc32Hex]['directoryEntryCount']++;
        } else {
            $summaries[$crc32Hex]['fileEntryCount']++;
        }
        if (($entry['isPackagePart'] ?? false) === true) {
            $summaries[$crc32Hex]['packagePartCount']++;
        }
        if (!$byteCountsAreExact) {
            $summaries[$crc32Hex]['unknownByteCountEntryCount']++;
        }
        $summaries[$crc32Hex]['compressedBytes'] += $compressedSize;
        $summaries[$crc32Hex]['uncompressedBytes'] += $uncompressedSize;

        if ($role !== '') {
            $summaries[$crc32Hex]['roleCounts'][$role] = ($summaries[$crc32Hex]['roleCounts'][$role] ?? 0) + 1;
        }
        if ($handoffKind !== '') {
            $summaries[$crc32Hex]['handoffKindCounts'][$handoffKind] =
                ($summaries[$crc32Hex]['handoffKindCounts'][$handoffKind] ?? 0) + 1;
        }

        self::appendUniqueString($summaries[$crc32Hex]['entryNames'], (string) ($entry['entryName'] ?? ''));
        if (is_string($entry['partName'] ?? null)) {
            self::appendUniqueString($summaries[$crc32Hex]['partNames'], $entry['partName']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function zipEntryManifestCrc32Provenance(array $summariesByHex): array
    {
        ksort($summariesByHex, SORT_STRING);
        $crc32HexCounts = [];
        $entryNamesByCrc32Hex = [];
        $crc32HexesByRole = [];
        $crc32HexesByHandoffKind = [];

        foreach ($summariesByHex as &$summary) {
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['handoffKindCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            sort($summary['partNames'], SORT_STRING);

            $crc32Hex = (string) $summary['crc32Hex'];
            $crc32HexCounts[$crc32Hex] = (int) $summary['entryCount'];
            $entryNamesByCrc32Hex[$crc32Hex] = $summary['entryNames'];
            foreach (array_keys($summary['roleCounts']) as $role) {
                $crc32HexesByRole[$role] ??= [];
                self::appendUniqueString($crc32HexesByRole[$role], $crc32Hex);
            }
            foreach (array_keys($summary['handoffKindCounts']) as $handoffKind) {
                $crc32HexesByHandoffKind[$handoffKind] ??= [];
                self::appendUniqueString($crc32HexesByHandoffKind[$handoffKind], $crc32Hex);
            }
        }
        unset($summary);

        self::sortStringListMap($crc32HexesByRole);
        self::sortStringListMap($crc32HexesByHandoffKind);
        $crc32Summaries = array_values($summariesByHex);
        $duplicateCrc32Summaries = array_values(array_filter(
            $crc32Summaries,
            static fn (array $summary): bool => (int) $summary['entryCount'] > 1
        ));
        $duplicateCrc32Hexes = array_map(
            static fn (array $summary): string => (string) $summary['crc32Hex'],
            $duplicateCrc32Summaries
        );
        $duplicateCrc32EntryCount = array_sum(array_map(
            static fn (array $summary): int => (int) $summary['entryCount'],
            $duplicateCrc32Summaries
        ));

        return [
            'crc32SummaryCount' => count($crc32Summaries),
            'crc32Summaries' => $crc32Summaries,
            'crc32HexCounts' => $crc32HexCounts,
            'entryNamesByCrc32Hex' => $entryNamesByCrc32Hex,
            'crc32HexesByRole' => $crc32HexesByRole,
            'crc32HexesByHandoffKind' => $crc32HexesByHandoffKind,
            'duplicateCrc32HexCount' => count($duplicateCrc32Summaries),
            'duplicateCrc32EntryCount' => $duplicateCrc32EntryCount,
            'hasDuplicateCrc32Entries' => $duplicateCrc32Summaries !== [],
            'duplicateCrc32Hexes' => $duplicateCrc32Hexes,
            'duplicateCrc32Summaries' => $duplicateCrc32Summaries,
        ];
    }

    /**
     * @param array<string, int> $hostSystemCounts
     * @param array<string, list<string>> $entryNamesByHostSystem
     * @param array<string, list<string>> $hostSystemNamesByRole
     * @param array<string, list<string>> $hostSystemNamesByHandoffKind
     * @param array<string, int> $versionComparisonCounts
     * @param array<string, list<string>> $entryNamesByVersionComparison
     * @param array<string, list<string>> $versionComparisonsByRole
     * @param array<string, list<string>> $versionComparisonsByHandoffKind
     * @param array<string, int> $issueCounts
     * @param array<string, list<string>> $entryNamesByIssue
     * @param array<string, mixed> $entry
     */
    private static function recordZipEntryManifestCreatorHostProvenance(
        array &$hostSystemCounts,
        array &$entryNamesByHostSystem,
        array &$hostSystemNamesByRole,
        array &$hostSystemNamesByHandoffKind,
        array &$versionComparisonCounts,
        array &$entryNamesByVersionComparison,
        array &$versionComparisonsByRole,
        array &$versionComparisonsByHandoffKind,
        array &$issueCounts,
        array &$entryNamesByIssue,
        array $entry
    ): void {
        $entryName = is_string($entry['entryName'] ?? null) ? $entry['entryName'] : '';
        $role = is_string($entry['role'] ?? null) ? $entry['role'] : 'unknown';
        $handoffKind = is_string($entry['handoffKind'] ?? null) ? $entry['handoffKind'] : 'unknown';
        $hostSystemName = is_string($entry['madeByHostSystemName'] ?? null)
            ? $entry['madeByHostSystemName']
            : 'unknown';

        $hostSystemCounts[$hostSystemName] = ($hostSystemCounts[$hostSystemName] ?? 0) + 1;
        $entryNamesByHostSystem[$hostSystemName] ??= [];
        self::appendUniqueString($entryNamesByHostSystem[$hostSystemName], $entryName);

        $hostSystemNamesByRole[$role] ??= [];
        self::appendUniqueString($hostSystemNamesByRole[$role], $hostSystemName);
        $hostSystemNamesByHandoffKind[$handoffKind] ??= [];
        self::appendUniqueString($hostSystemNamesByHandoffKind[$handoffKind], $hostSystemName);

        $versionComparison = is_string($entry['creatorVersionComparison'] ?? null)
            ? $entry['creatorVersionComparison']
            : null;
        if ($versionComparison !== null) {
            $versionComparisonCounts[$versionComparison] = ($versionComparisonCounts[$versionComparison] ?? 0) + 1;
            $entryNamesByVersionComparison[$versionComparison] ??= [];
            self::appendUniqueString($entryNamesByVersionComparison[$versionComparison], $entryName);

            $versionComparisonsByRole[$role] ??= [];
            self::appendUniqueString($versionComparisonsByRole[$role], $versionComparison);
            $versionComparisonsByHandoffKind[$handoffKind] ??= [];
            self::appendUniqueString($versionComparisonsByHandoffKind[$handoffKind], $versionComparison);
        }

        foreach (is_array($entry['creatorHostSystemIssues'] ?? null) ? $entry['creatorHostSystemIssues'] : [] as $issue) {
            if (!is_string($issue)) {
                continue;
            }

            $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
            $entryNamesByIssue[$issue] ??= [];
            self::appendUniqueString($entryNamesByIssue[$issue], $entryName);
        }
    }

    /**
     * @param array<string, int> $hostSystemCounts
     * @param array<string, list<string>> $entryNamesByHostSystem
     * @param array<string, list<string>> $hostSystemNamesByRole
     * @param array<string, list<string>> $hostSystemNamesByHandoffKind
     * @param array<string, int> $versionComparisonCounts
     * @param array<string, list<string>> $entryNamesByVersionComparison
     * @param array<string, list<string>> $versionComparisonsByRole
     * @param array<string, list<string>> $versionComparisonsByHandoffKind
     * @param array<string, int> $issueCounts
     * @param array<string, list<string>> $entryNamesByIssue
     */
    private static function sortZipManifestCreatorHostProvenance(
        array &$hostSystemCounts,
        array &$entryNamesByHostSystem,
        array &$hostSystemNamesByRole,
        array &$hostSystemNamesByHandoffKind,
        array &$versionComparisonCounts,
        array &$entryNamesByVersionComparison,
        array &$versionComparisonsByRole,
        array &$versionComparisonsByHandoffKind,
        array &$issueCounts,
        array &$entryNamesByIssue
    ): void {
        ksort($hostSystemCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByHostSystem);
        self::sortStringListMap($hostSystemNamesByRole);
        self::sortStringListMap($hostSystemNamesByHandoffKind);
        self::sortStringListMap($entryNamesByVersionComparison);
        self::sortStringListMap($versionComparisonsByRole);
        self::sortStringListMap($versionComparisonsByHandoffKind);
        ksort($issueCounts, SORT_STRING);
        self::sortStringListMap($entryNamesByIssue);
    }

    /**
     * @param array<string, list<string>> $entryNamesByIssue
     * @param array<string, list<string>> $partNamesByIssue
     */
    private static function recordZipManifestIssueProvenance(
        array &$entryNamesByIssue,
        array &$partNamesByIssue,
        string $issue,
        ?string $entryName,
        ?string $partName
    ): void {
        if ($entryName !== null) {
            $entryNamesByIssue[$issue] ??= [];
            self::appendUniqueString($entryNamesByIssue[$issue], $entryName);
        }

        if ($partName !== null) {
            $partNamesByIssue[$issue] ??= [];
            self::appendUniqueString($partNamesByIssue[$issue], $partName);
        }
    }

    /**
     * @param array<string, list<string>> $groups
     */
    private static function sortZipManifestIssueProvenance(array &$groups): void
    {
        ksort($groups, SORT_STRING);
        foreach ($groups as &$values) {
            sort($values, SORT_STRING);
        }
        unset($values);
    }

    private static function isEmbeddedPackageCandidate(string $partName, ?string $contentType = null): bool
    {
        if (in_array(self::partNameExtension($partName), [
            'docx',
            'docm',
            'dotx',
            'dotm',
            'epub',
            'odp',
            'ods',
            'odt',
            'pptx',
            'pptm',
            'xlsx',
            'xlsm',
            'zip',
        ], true)) {
            return true;
        }

        return $contentType !== null && self::isEmbeddedPackageContentType($contentType);
    }

    private static function isMediaPartCandidate(string $partName, ?string $contentType = null): bool
    {
        if (in_array(self::partNameExtension($partName), [
            'avi',
            'bmp',
            'emf',
            'gif',
            'jpeg',
            'jpg',
            'm4a',
            'mov',
            'mp3',
            'mp4',
            'png',
            'svg',
            'tif',
            'tiff',
            'wav',
            'webp',
            'wmf',
        ], true)) {
            return true;
        }

        return $contentType !== null && self::isMediaContentType($contentType);
    }

    private static function isXmlLikePartName(string $partName, ?string $contentType = null): bool
    {
        if (in_array(self::partNameExtension($partName), ['html', 'htm', 'ncx', 'opf', 'rels', 'xhtml', 'xml'], true)) {
            return true;
        }

        return $contentType !== null && self::isXmlLikeContentType($contentType);
    }

    private static function isEmbeddedPackageContentType(string $contentType): bool
    {
        return in_array(self::contentTypeMediaTypeKey($contentType), [
            'application/epub+zip',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.openxmlformats-officedocument.oleobject',
            'application/vnd.openxmlformats-officedocument.package',
            'application/vnd.openxmlformats-package.encrypted-package',
            'application/x-zip-compressed',
            'application/zip',
        ], true);
    }

    private static function isMediaContentType(string $contentType): bool
    {
        $mediaType = self::contentTypeMediaTypeKey($contentType);

        return str_starts_with($mediaType, 'image/')
            || str_starts_with($mediaType, 'audio/')
            || str_starts_with($mediaType, 'video/');
    }

    private static function isXmlLikeContentType(string $contentType): bool
    {
        $mediaType = self::contentTypeMediaTypeKey($contentType);

        return in_array($mediaType, [
            'application/oebps-package+xml',
            'application/xhtml+xml',
            'application/xml',
            'text/html',
            'text/xml',
        ], true)
            || str_ends_with($mediaType, '+xml');
    }

    private static function partNameExtension(string $partName): string
    {
        $basename = basename($partName);
        $position = strrpos($basename, '.');

        return $position === false ? '' : strtolower(substr($basename, $position + 1));
    }

    private static function contentTypesItemNameInPackage(ZipPackage $package): ?string
    {
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            try {
                if (self::isContentTypesItemName($name)) {
                    return $name;
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return null;
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    private static function contentTypeMatches(?string $actual, string $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        return self::contentTypeComparisonKey($actual) === self::contentTypeComparisonKey($expected);
    }

    /**
     * @param list<string> $expectedContentTypes
     */
    private static function contentTypeMatchesAny(?string $actual, array $expectedContentTypes): bool
    {
        foreach ($expectedContentTypes as $expectedContentType) {
            if (self::contentTypeMatches($actual, $expectedContentType)) {
                return true;
            }
        }

        return false;
    }

    private static function contentTypeComparisonKey(string $contentType): string
    {
        return self::contentTypeMediaTypeKey($contentType);
    }

    /**
     * @param list<string> $expectedContentTypes
     */
    private static function contentTypeMediaTypeMatchesAny(?string $actual, array $expectedContentTypes): bool
    {
        if ($actual === null) {
            return false;
        }

        $actualMediaType = self::contentTypeMediaTypeKey($actual);
        foreach ($expectedContentTypes as $expectedContentType) {
            if ($actualMediaType === self::contentTypeMediaTypeKey($expectedContentType)) {
                return true;
            }
        }

        return false;
    }

    private static function contentTypeMediaTypeKey(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private static function contentTypeHasPrefix(string $contentType, string $prefix): bool
    {
        return str_starts_with(self::contentTypeMediaTypeKey($contentType), strtolower($prefix));
    }

    private static function isImageContentType(string $contentType): bool
    {
        return self::contentTypeHasPrefix($contentType, 'image/');
    }

    /**
     * @return array<string, array{role:string, expectedContentType?:string, expectedContentTypes?:list<string>, expectedContentTypePrefix?:string, expectedSourceContentTypes?:list<string>, expectedExternal?:bool}>
     */
    private static function wordprocessingDocumentRelationshipRoleDefinitions(): array
    {
        $documentSourceContentTypes = self::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES;

        return [
            self::WORDPROCESSING_STYLES_RELATIONSHIP_TYPE => [
                'role' => 'styles',
                'expectedContentType' => self::WORDPROCESSING_STYLES_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_NUMBERING_RELATIONSHIP_TYPE => [
                'role' => 'numbering',
                'expectedContentType' => self::WORDPROCESSING_NUMBERING_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_FOOTNOTES_RELATIONSHIP_TYPE => [
                'role' => 'footnotes',
                'expectedContentType' => self::WORDPROCESSING_FOOTNOTES_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_ENDNOTES_RELATIONSHIP_TYPE => [
                'role' => 'endnotes',
                'expectedContentType' => self::WORDPROCESSING_ENDNOTES_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_COMMENTS_RELATIONSHIP_TYPE => [
                'role' => 'comments',
                'expectedContentType' => self::WORDPROCESSING_COMMENTS_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_SETTINGS_RELATIONSHIP_TYPE => [
                'role' => 'settings',
                'expectedContentType' => self::WORDPROCESSING_SETTINGS_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_THEME_RELATIONSHIP_TYPE => [
                'role' => 'theme',
                'expectedContentType' => self::OFFICE_THEME_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_FONT_TABLE_RELATIONSHIP_TYPE => [
                'role' => 'font-table',
                'expectedContentType' => self::WORDPROCESSING_FONT_TABLE_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_WEB_SETTINGS_RELATIONSHIP_TYPE => [
                'role' => 'web-settings',
                'expectedContentType' => self::WORDPROCESSING_WEB_SETTINGS_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_HEADER_RELATIONSHIP_TYPE => [
                'role' => 'header',
                'expectedContentType' => self::WORDPROCESSING_HEADER_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_FOOTER_RELATIONSHIP_TYPE => [
                'role' => 'footer',
                'expectedContentType' => self::WORDPROCESSING_FOOTER_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE => [
                'role' => 'image',
                'expectedContentTypePrefix' => 'image/',
            ],
            self::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE => [
                'role' => 'hyperlink',
                'expectedExternal' => true,
            ],
            self::WORDPROCESSING_CUSTOM_XML_RELATIONSHIP_TYPE => [
                'role' => 'custom-xml',
                'expectedContentType' => self::WORDPROCESSING_CUSTOM_XML_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE => [
                'role' => 'custom-xml-properties',
                'expectedContentType' => self::WORDPROCESSING_CUSTOM_XML_PROPERTIES_CONTENT_TYPE,
                'expectedSourceContentTypes' => [self::WORDPROCESSING_CUSTOM_XML_CONTENT_TYPE],
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_COMMENTS_EXTENDED_RELATIONSHIP_TYPE => [
                'role' => 'comments-extended',
                'expectedContentType' => self::WORDPROCESSING_COMMENTS_EXTENDED_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_GLOSSARY_DOCUMENT_RELATIONSHIP_TYPE => [
                'role' => 'glossary-document',
                'expectedContentType' => self::WORDPROCESSING_GLOSSARY_DOCUMENT_CONTENT_TYPE,
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE => [
                'role' => 'alternative-format-import',
                'expectedContentTypes' => [
                    'text/html',
                    'application/xhtml+xml',
                    'text/plain',
                ],
                'expectedSourceContentTypes' => $documentSourceContentTypes,
                'expectedExternal' => false,
            ],
            self::DRAWINGML_CHART_RELATIONSHIP_TYPE => [
                'role' => 'chart',
                'expectedContentType' => self::DRAWINGML_CHART_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::DRAWINGML_DIAGRAM_DATA_RELATIONSHIP_TYPE => [
                'role' => 'diagram-data',
                'expectedContentType' => self::DRAWINGML_DIAGRAM_DATA_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::DRAWINGML_DIAGRAM_LAYOUT_RELATIONSHIP_TYPE => [
                'role' => 'diagram-layout',
                'expectedContentType' => self::DRAWINGML_DIAGRAM_LAYOUT_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::DRAWINGML_DIAGRAM_QUICK_STYLE_RELATIONSHIP_TYPE => [
                'role' => 'diagram-quick-style',
                'expectedContentType' => self::DRAWINGML_DIAGRAM_QUICK_STYLE_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
            self::DRAWINGML_DIAGRAM_COLORS_RELATIONSHIP_TYPE => [
                'role' => 'diagram-colors',
                'expectedContentType' => self::DRAWINGML_DIAGRAM_COLORS_CONTENT_TYPE,
                'expectedExternal' => false,
            ],
        ];
    }

    /**
     * @param array{type:string, relationshipCount:int, sources:list<string>, idsBySource:array<string, list<string>>} $entry
     * @return array{knownRole:?string, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>}
     */
    private static function relationshipTypePolicyForInventoryEntry(array $entry): array
    {
        $definition = self::relationshipTypePolicyDefinitions()[$entry['type']] ?? null;
        if ($definition === null) {
            return [
                'knownRole' => null,
                'sourceScope' => 'any-source',
                'singletonScope' => null,
                'policyValid' => true,
                'policyIssues' => [],
            ];
        }

        $issues = [];
        if (($definition['sourceScope'] ?? 'any-source') === 'package-root') {
            foreach ($entry['sources'] as $source) {
                if ($source !== '/') {
                    self::appendUniqueString($issues, $definition['sourceIssue']);
                }
            }
        }

        if (($definition['singletonScope'] ?? null) === 'package' && $entry['relationshipCount'] > 1) {
            self::appendUniqueString($issues, $definition['multipleIssue']);
        }

        if (($definition['singletonScope'] ?? null) === 'source') {
            foreach ($entry['idsBySource'] as $relationshipIds) {
                if (count($relationshipIds) > 1) {
                    self::appendUniqueString($issues, $definition['multipleIssue']);
                }
            }
        }

        sort($issues, SORT_STRING);

        return [
            'knownRole' => $definition['role'],
            'sourceScope' => $definition['sourceScope'] ?? 'any-source',
            'singletonScope' => $definition['singletonScope'] ?? null,
            'policyValid' => $issues === [],
            'policyIssues' => $issues,
        ];
    }

    /**
     * @return array<string, array{role:string, sourceScope?:string, singletonScope?:string, sourceIssue?:string, multipleIssue?:string}>
     */
    private static function relationshipTypePolicyDefinitions(): array
    {
        $definitions = [
            self::OFFICE_DOCUMENT_RELATIONSHIP_TYPE => [
                'role' => 'office-document',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'office-document-relationship-source-not-package-root',
                'multipleIssue' => 'multiple-office-document-relationships',
            ],
            self::CORE_PROPERTIES_RELATIONSHIP_TYPE => [
                'role' => 'core-properties',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'core-properties-relationship-source-not-package-root',
                'multipleIssue' => 'multiple-core-properties-relationships',
            ],
            self::EXTENDED_PROPERTIES_RELATIONSHIP_TYPE => [
                'role' => 'extended-properties',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'extended-properties-relationship-source-not-package-root',
                'multipleIssue' => 'multiple-extended-properties-relationships',
            ],
            self::CUSTOM_PROPERTIES_RELATIONSHIP_TYPE => [
                'role' => 'custom-properties',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'custom-properties-relationship-source-not-package-root',
                'multipleIssue' => 'multiple-custom-properties-relationships',
            ],
            self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE => [
                'role' => 'digital-signature-origin',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'digital-signature-origin-source-not-package-root',
                'multipleIssue' => 'multiple-digital-signature-origins',
            ],
            self::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE => [
                'role' => 'encrypted-package',
                'sourceScope' => 'package-root',
                'singletonScope' => 'package',
                'sourceIssue' => 'encrypted-package-source-not-package-root',
                'multipleIssue' => 'multiple-encrypted-package-relationships',
            ],
            self::THUMBNAIL_RELATIONSHIP_TYPE => [
                'role' => 'thumbnail',
                'sourceScope' => 'any-source',
                'singletonScope' => 'source',
                'multipleIssue' => 'multiple-thumbnail-relationships-for-source',
            ],
        ];

        foreach (self::wordprocessingSourceSingletonRelationshipRoles() as $relationshipType => $role) {
            $definitions[$relationshipType] = [
                'role' => $role,
                'sourceScope' => 'any-source',
                'singletonScope' => 'source',
                'multipleIssue' => 'multiple-' . $role . '-relationships-for-source',
            ];
        }

        foreach (self::wordprocessingUnscopedRelationshipRoles() as $relationshipType => $role) {
            $definitions[$relationshipType] = [
                'role' => $role,
            ];
        }

        foreach (self::drawingMlRelationshipRoles() as $relationshipType => $role) {
            $definitions[$relationshipType] = [
                'role' => $role,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, string>
     */
    private static function wordprocessingSourceSingletonRelationshipRoles(): array
    {
        return [
            self::WORDPROCESSING_STYLES_RELATIONSHIP_TYPE => 'styles',
            self::WORDPROCESSING_NUMBERING_RELATIONSHIP_TYPE => 'numbering',
            self::WORDPROCESSING_FOOTNOTES_RELATIONSHIP_TYPE => 'footnotes',
            self::WORDPROCESSING_ENDNOTES_RELATIONSHIP_TYPE => 'endnotes',
            self::WORDPROCESSING_COMMENTS_RELATIONSHIP_TYPE => 'comments',
            self::WORDPROCESSING_SETTINGS_RELATIONSHIP_TYPE => 'settings',
            self::WORDPROCESSING_THEME_RELATIONSHIP_TYPE => 'theme',
            self::WORDPROCESSING_FONT_TABLE_RELATIONSHIP_TYPE => 'font-table',
            self::WORDPROCESSING_WEB_SETTINGS_RELATIONSHIP_TYPE => 'web-settings',
            self::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE => 'custom-xml-properties',
            self::WORDPROCESSING_COMMENTS_EXTENDED_RELATIONSHIP_TYPE => 'comments-extended',
            self::WORDPROCESSING_GLOSSARY_DOCUMENT_RELATIONSHIP_TYPE => 'glossary-document',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function wordprocessingUnscopedRelationshipRoles(): array
    {
        return [
            self::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE => 'alternative-format-import',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function drawingMlRelationshipRoles(): array
    {
        return [
            self::DRAWINGML_CHART_RELATIONSHIP_TYPE => 'chart',
            self::DRAWINGML_DIAGRAM_DATA_RELATIONSHIP_TYPE => 'diagram-data',
            self::DRAWINGML_DIAGRAM_LAYOUT_RELATIONSHIP_TYPE => 'diagram-layout',
            self::DRAWINGML_DIAGRAM_QUICK_STYLE_RELATIONSHIP_TYPE => 'diagram-quick-style',
            self::DRAWINGML_DIAGRAM_COLORS_RELATIONSHIP_TYPE => 'diagram-colors',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function packagePartNamesByEquivalenceKey(ZipPackage $package): array
    {
        $partNamesByEquivalenceKey = [];
        foreach (self::preflightPackagePartNames($package)['parts'] as $part) {
            if (!$part['isPackagePart'] || !$part['valid'] || $part['partName'] === null) {
                continue;
            }

            $partName = $part['partName'];
            $partNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] = $partName;
        }

        return $partNamesByEquivalenceKey;
    }

    /**
     * @return list<string>
     */
    private static function packagePartNameIssuesForParseError(string $parseError): array
    {
        $issues = ['invalid-opc-part-name'];
        if (str_contains($parseError, 'query or fragment')) {
            $issues[] = 'part-name-query-or-fragment';
        }
        if (str_contains($parseError, 'empty path segments')) {
            $issues[] = 'part-name-empty-segment';
        }
        if (str_contains($parseError, 'end with a dot')) {
            $issues[] = 'part-name-trailing-dot-segment';
        }
        if (str_contains($parseError, 'control characters')) {
            $issues[] = 'part-name-control-character';
        }
        if (str_contains($parseError, 'slash-separated')) {
            $issues[] = 'part-name-backslash-or-nul';
        }
        if (str_contains($parseError, 'above the package root')) {
            $issues[] = 'part-name-root-traversal';
        }
        if (str_contains($parseError, 'must not be empty') || str_contains($parseError, 'must identify a package part')) {
            $issues[] = 'part-name-empty';
        }

        return array_values(array_unique($issues));
    }

    private function packagePartNameForEquivalent(string $partName): ?string
    {
        $partName = OpcPackagePath::canonicalPartName($partName);

        return $this->packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] ?? null;
    }

    private function packageEquivalentTarget(string $target): string
    {
        $split = strcspn($target, '?#');
        $partName = substr($target, 0, $split);
        $suffix = substr($target, $split);
        if ($partName === '') {
            return $target;
        }

        $equivalentPartName = $this->packagePartNameForEquivalent($partName);

        return ($equivalentPartName ?? $partName) . $suffix;
    }

    private function relationshipSourceNameForEquivalent(string $sourcePartName): string
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);

        return $this->relationshipSourceNamesByEquivalenceKey[self::partNameEquivalenceKey($sourcePartName)] ?? $sourcePartName;
    }

    /**
     * @param list<array{valid:bool}> $rows
     */
    private static function allRowsValid(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['valid'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{type:string, relationshipCount:int, sourceCount:int, sources:list<string>, idsBySource:array<string, list<string>>, internalCount:int, externalCount:int, validCount:int, invalidCount:int, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, targetParts:list<string>, contentTypes:list<string>, knownRole:?string, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>, issues:list<string>}> $inventory
     * @return list<array{type:string, relationshipCount:int, sourceCount:int, sources:list<string>, idsBySource:array<string, list<string>>, internalCount:int, externalCount:int, validCount:int, invalidCount:int, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, targetParts:list<string>, contentTypes:list<string>, knownRole:?string, sourceScope:string, singletonScope:?string, policyValid:bool, policyIssues:list<string>, issues:list<string>}>
     */
    private static function knownRelationshipTypePolicies(array $inventory): array
    {
        $policies = [];
        foreach ($inventory as $entry) {
            if ($entry['knownRole'] === null) {
                continue;
            }

            $policies[] = $entry;
        }

        return $policies;
    }

    /**
     * @param list<array{policyValid:bool}> $policies
     */
    private static function allRelationshipTypePoliciesValid(array $policies): bool
    {
        foreach ($policies as $policy) {
            if ($policy['policyValid'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{loaded:bool, issues:list<string>, loadAction:string, loadReason:string} $row
     */
    private static function refreshRelationshipPartLoadDecision(array &$row): void
    {
        $row['loadAction'] = $row['loaded'] ? 'loaded' : 'skipped';
        $row['loadReason'] = self::relationshipPartLoadReason($row['issues'], $row['loaded']);
    }

    /**
     * @param list<string> $issues
     */
    private static function relationshipPartLoadReason(array $issues, bool $loaded): string
    {
        if ($loaded) {
            return 'loaded';
        }

        foreach ([
            'invalid-relationship-part-name',
            'duplicate-relationship-source',
            'missing-content-type',
            'invalid-relationship-content-type',
            'relationship-part-source',
            'content-types-item-source',
            'orphan-relationship-part',
            'malformed-relationship-xml',
        ] as $issue) {
            if (in_array($issue, $issues, true)) {
                return $issue;
            }
        }

        return 'not-loaded';
    }

    private static function relationshipSourceKind(
        ?string $relationshipSource,
        ?bool $relationshipSourceIsRelationshipPart,
        ?bool $sourceExists,
    ): string {
        if ($relationshipSource === null) {
            return 'invalid-source';
        }

        if ($relationshipSource === '/') {
            return 'package-root';
        }

        if ($relationshipSourceIsRelationshipPart === true) {
            return 'relationship-part';
        }

        if (self::isContentTypesItemName($relationshipSource)) {
            return 'content-types-item';
        }

        if ($sourceExists === false) {
            return 'missing-source';
        }

        return 'package-part';
    }

    /**
     * @param array{target:string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, issues:list<string>} $target
     */
    private static function relationshipTargetResolutionKind(
        array $target,
        ?string $targetPart,
        bool $sameSourceReference,
    ): string {
        if ($target['external']) {
            if (in_array('external-target-matches-package-part', $target['issues'], true)) {
                return 'external-package-shadow';
            }

            if ($target['externalTargetAllowed'] === false) {
                return 'external-blocked';
            }

            if ($target['externalTargetKind'] === 'network-path-reference') {
                return 'external-network-path-reference';
            }

            if ($target['externalTargetKind'] === 'fragment-reference') {
                return 'external-fragment-reference';
            }

            if ($target['externalTargetKind'] === 'relative-reference') {
                return 'external-relative-reference';
            }

            if ($target['externalTargetKind'] === 'absolute-uri') {
                return 'external-absolute-uri';
            }

            return 'external-' . ($target['externalTargetKind'] ?? 'unknown');
        }

        if ($targetPart === null || in_array('invalid-target', $target['issues'], true)) {
            return 'internal-invalid-target';
        }

        if ($target['relationshipPartTarget']) {
            return 'internal-relationship-part';
        }

        if (in_array('targets-content-types-item', $target['issues'], true)) {
            return 'internal-content-types-item';
        }

        if (in_array('targets-reserved-relationship-directory-part', $target['issues'], true)) {
            return 'internal-reserved-relationship-directory';
        }

        if ($sameSourceReference) {
            return 'internal-same-source';
        }

        if ($target['exists'] === false) {
            return 'internal-missing-part';
        }

        if ($target['exists'] === true) {
            return 'internal-existing-part';
        }

        return 'internal-unresolved';
    }

    /**
     * @return array{issues:list<string>, records:list<array{relationshipOrdinal:int, id:?string, type:?string, target:?string, targetMode:?string, duplicateOfOrdinal:?int, issues:list<string>}>}
     */
    private static function relationshipXmlIssueDiagnostics(string $xml): array
    {
        if ($xml === '') {
            return [
                'issues' => [],
                'records' => [],
            ];
        }

        try {
            $dom = XmlHtmlDom::loadXmlDocument($xml, 'OPC relationships XML');
        } catch (\Throwable) {
            return [
                'issues' => [],
                'records' => [],
            ];
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'Relationships' || $root->namespaceURI !== OpcRelationships::NAMESPACE_URI) {
            return [
                'issues' => [],
                'records' => [],
            ];
        }

        $issues = [];
        $records = [];
        $seenIds = [];
        $relationshipOrdinal = 0;
        foreach ($root->getElementsByTagNameNS(OpcRelationships::NAMESPACE_URI, 'Relationship') as $relationship) {
            if (!$relationship instanceof \DOMElement) {
                continue;
            }

            $relationshipOrdinal++;
            $recordIssues = [];
            $duplicateOfOrdinal = null;
            if (!$relationship->hasAttribute('Id') || $relationship->getAttribute('Id') === '') {
                self::appendUniqueString($issues, 'missing-relationship-id');
                self::appendUniqueString($recordIssues, 'missing-relationship-id');
            } else {
                $id = $relationship->getAttribute('Id');
                if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $id) !== 1) {
                    self::appendUniqueString($issues, 'invalid-relationship-id');
                    self::appendUniqueString($recordIssues, 'invalid-relationship-id');
                } elseif (isset($seenIds[$id])) {
                    self::appendUniqueString($issues, 'duplicate-relationship-id');
                    self::appendUniqueString($recordIssues, 'duplicate-relationship-id');
                    $duplicateOfOrdinal = $seenIds[$id];
                } else {
                    $seenIds[$id] = $relationshipOrdinal;
                }
            }

            if (!$relationship->hasAttribute('Type') || $relationship->getAttribute('Type') === '') {
                self::appendUniqueString($issues, 'missing-relationship-type');
                self::appendUniqueString($recordIssues, 'missing-relationship-type');
            }

            if (!$relationship->hasAttribute('Target') || $relationship->getAttribute('Target') === '') {
                self::appendUniqueString($issues, 'missing-relationship-target');
                self::appendUniqueString($recordIssues, 'missing-relationship-target');
            }

            if ($relationship->hasAttribute('TargetMode')) {
                $targetMode = $relationship->getAttribute('TargetMode');
                if ($targetMode !== OpcRelationship::TARGET_MODE_INTERNAL && $targetMode !== OpcRelationship::TARGET_MODE_EXTERNAL) {
                    self::appendUniqueString($issues, 'invalid-relationship-target-mode');
                    self::appendUniqueString($recordIssues, 'invalid-relationship-target-mode');
                }
            }

            if ($recordIssues !== []) {
                $records[] = [
                    'relationshipOrdinal' => $relationshipOrdinal,
                    'id' => $relationship->hasAttribute('Id') && $relationship->getAttribute('Id') !== ''
                        ? $relationship->getAttribute('Id')
                        : null,
                    'type' => $relationship->hasAttribute('Type') && $relationship->getAttribute('Type') !== ''
                        ? $relationship->getAttribute('Type')
                        : null,
                    'target' => $relationship->hasAttribute('Target') && $relationship->getAttribute('Target') !== ''
                        ? $relationship->getAttribute('Target')
                        : null,
                    'targetMode' => $relationship->hasAttribute('TargetMode')
                        ? $relationship->getAttribute('TargetMode')
                        : null,
                    'duplicateOfOrdinal' => $duplicateOfOrdinal,
                    'issues' => $recordIssues,
                ];
            }
        }

        return [
            'issues' => $issues,
            'records' => $records,
        ];
    }

    /**
     * @return array{contentType:string, packagePartCount:int, overrideCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipSourceCount:int, relationshipTargetReferenceCount:int, relationshipTargetPartCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, parts:list<string>, overrideParts:list<string>, defaultParts:list<string>, relationshipParts:list<string>, relationshipSources:list<string>, relationshipTargetParts:list<string>, reachableTargetParts:list<string>, missingOverrideParts:list<string>, relationshipTargetReferences:list<array{source:string, id:string, targetPart:string, valid:bool, issues:list<string>}>, issues:list<string>}
     */
    private static function &contentTypeInventoryEntry(array &$inventory, string $contentType): array
    {
        if (!isset($inventory[$contentType])) {
            $inventory[$contentType] = [
                'contentType' => $contentType,
                'packagePartCount' => 0,
                'overrideCount' => 0,
                'defaultPartCount' => 0,
                'relationshipPartCount' => 0,
                'relationshipSourceCount' => 0,
                'relationshipTargetReferenceCount' => 0,
                'relationshipTargetPartCount' => 0,
                'reachableTargetCount' => 0,
                'missingOverrideCount' => 0,
                'invalidPackagePartCount' => 0,
                'parts' => [],
                'overrideParts' => [],
                'defaultParts' => [],
                'relationshipParts' => [],
                'relationshipSources' => [],
                'relationshipTargetParts' => [],
                'reachableTargetParts' => [],
                'missingOverrideParts' => [],
                'relationshipTargetReferences' => [],
                'issues' => [],
            ];
        }

        return $inventory[$contentType];
    }

    /**
     * @return array{partName:string, exists:bool, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, packagePartValid:bool, packagePartIssues:list<string>, directReferenceCount:int, reachableReferenceCount:int, directReferences:list<array<string, mixed>>, reachableReferences:list<array<string, mixed>>, valid:bool, issues:list<string>}
     */
    private static function &packagePartReferenceInventoryEntry(
        array &$inventory,
        string $partName,
        ?string $contentType,
        bool $exists,
        bool $relationshipPart,
    ): array {
        if (!isset($inventory[$partName])) {
            $issues = $exists ? [] : ['missing-in-package'];
            $inventory[$partName] = [
                'partName' => $partName,
                'exists' => $exists,
                'contentType' => $contentType,
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipPart
                    ? OpcRelationships::sourcePartNameForRelationshipPart($partName)
                    : null,
                'relationshipSourceIsRelationshipPart' => $relationshipPart
                    ? OpcRelationships::isRelationshipPartName(OpcRelationships::sourcePartNameForRelationshipPart($partName))
                    : null,
                'relationshipSourceLoaded' => null,
                'sourceExists' => null,
                'packagePartValid' => $exists,
                'packagePartIssues' => [],
                'directReferenceCount' => 0,
                'reachableReferenceCount' => 0,
                'directReferences' => [],
                'reachableReferences' => [],
                'valid' => $exists,
                'issues' => $issues,
            ];
        } elseif ($inventory[$partName]['contentType'] === null && $contentType !== null) {
            $inventory[$partName]['contentType'] = $contentType;
        }

        return $inventory[$partName];
    }

    /**
     * @param list<string> $values
     */
    private static function appendUniqueString(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param array<string, list<string>> $map
     */
    private static function sortStringListMap(array &$map): void
    {
        ksort($map, SORT_STRING);
        foreach ($map as &$values) {
            sort($values, SORT_STRING);
        }
        unset($values);
    }

    /**
     * @param array<string, array<string, list<string>>> $map
     */
    private static function sortNestedStringListMap(array &$map): void
    {
        ksort($map, SORT_STRING);
        foreach ($map as &$nestedMap) {
            self::sortStringListMap($nestedMap);
        }
        unset($nestedMap);
    }

    /**
     * @return list<string>
     */
    private static function internalTargetIssues(string $target, string $parseError): array
    {
        $issues = [];
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            self::appendUniqueString($issues, 'internal-target-absolute-uri');
        }

        if (str_starts_with($target, '//')) {
            self::appendUniqueString($issues, 'internal-target-network-path-reference');
        }

        if (str_contains($target, "\0") || str_contains($target, '\\')) {
            self::appendUniqueString($issues, 'internal-target-unsafe-path-byte');
        }

        if (preg_match('/[\x00-\x20\x7F]/', $target) === 1) {
            self::appendUniqueString($issues, 'internal-target-invalid-uri-byte');
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $target) === 1) {
            self::appendUniqueString($issues, 'internal-target-malformed-percent-escape');
        } elseif (self::internalTargetContainsUnsafePercentEncodedPathByte($target)) {
            self::appendUniqueString($issues, 'internal-target-unsafe-percent-encoded-path-byte');
        } elseif (self::internalTargetSuffixContainsUnsafePercentEncodedByte($target)) {
            self::appendUniqueString($issues, 'internal-target-unsafe-percent-encoded-byte');
        }

        if (str_contains($parseError, 'unsafe percent-encoded dot segment')) {
            self::appendUniqueString($issues, 'internal-target-unsafe-percent-encoded-dot-segment');
        }

        if (str_contains($parseError, 'traverse above the package root')) {
            self::appendUniqueString($issues, 'internal-target-package-root-traversal');
        }

        if (str_contains($parseError, 'segments must not end with a dot')) {
            self::appendUniqueString($issues, 'internal-target-trailing-dot-segment');
        }

        if (str_contains($parseError, 'empty path segments')) {
            self::appendUniqueString($issues, 'internal-target-empty-path-segment');
        }

        if (str_contains($parseError, 'target path must not be empty') || str_contains($parseError, 'target must not be empty')) {
            self::appendUniqueString($issues, 'internal-target-empty-path');
        }

        return $issues;
    }

    private static function assertInternalRelationshipTargetUriReferenceSuffix(string $target): void
    {
        $suffix = substr($target, strcspn($target, '?#'));
        if ($suffix === '') {
            return;
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $suffix) === 1) {
            throw new \InvalidArgumentException(
                'OPC internal relationship target query or fragment contains malformed percent escape'
            );
        }

        if (self::containsPercentEncodedControlByte($suffix)) {
            throw new \InvalidArgumentException(
                'OPC internal relationship target query or fragment contains unsafe percent-encoded byte'
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function relationshipTransformReferenceUriIssues(string $referenceUri, string $parseError): array
    {
        $issues = [];
        if (str_contains($referenceUri, "\0") || str_contains($referenceUri, '\\')) {
            self::appendUniqueString($issues, 'relationship-transform-reference-unsafe-path-byte');
        }

        if (preg_match('/[\x00-\x20\x7F]/', $referenceUri) === 1) {
            self::appendUniqueString($issues, 'relationship-transform-reference-invalid-uri-byte');
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $referenceUri) === 1) {
            self::appendUniqueString($issues, 'relationship-transform-reference-malformed-percent-escape');
        } elseif (self::internalTargetContainsUnsafePercentEncodedPathByte($referenceUri)) {
            self::appendUniqueString($issues, 'relationship-transform-reference-unsafe-percent-encoded-path-byte');
        }

        if (str_contains($parseError, 'unsafe percent-encoded dot segment')) {
            self::appendUniqueString($issues, 'relationship-transform-reference-unsafe-percent-encoded-dot-segment');
        }

        if (str_contains($parseError, 'traverse above the package root')) {
            self::appendUniqueString($issues, 'relationship-transform-reference-package-root-traversal');
        }

        if (str_contains($parseError, 'segments must not end with a dot')) {
            self::appendUniqueString($issues, 'relationship-transform-reference-trailing-dot-segment');
        }

        if (str_contains($parseError, 'target path must not be empty') || str_contains($parseError, 'target must not be empty')) {
            self::appendUniqueString($issues, 'relationship-transform-reference-empty-path');
        }

        return $issues;
    }

    private static function internalTargetContainsUnsafePercentEncodedPathByte(string $target): bool
    {
        $split = strcspn($target, '?#');
        $path = substr($target, 0, $split);
        if ($path === '') {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment);
            if (str_contains($decoded, "\0") || str_contains($decoded, '/') || str_contains($decoded, '\\')) {
                return true;
            }
        }

        return false;
    }

    private static function internalTargetSuffixContainsUnsafePercentEncodedByte(string $target): bool
    {
        $suffix = substr($target, strcspn($target, '?#'));

        return $suffix !== '' && self::containsPercentEncodedControlByte($suffix);
    }

    private static function containsPercentEncodedControlByte(string $value): bool
    {
        if (preg_match_all('/%([0-9A-Fa-f]{2})/', $value, $matches) === 0) {
            return false;
        }

        foreach ($matches[1] as $hex) {
            $byte = hexdec($hex);
            if ($byte < 0x20 || $byte === 0x7f) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{kind:string, scheme:?string, allowed:bool, issues:list<string>} $externalTarget
     * @return array{requiresBaseUri:bool, basePart:?string, reason:?string, issues:list<string>}
     */
    private static function externalTargetRewritePolicy(string $sourcePartName, array $externalTarget): array
    {
        $kind = $externalTarget['kind'];
        if ($kind === 'network-path-reference') {
            return [
                'requiresBaseUri' => true,
                'basePart' => null,
                'reason' => 'external-target-network-path-reference',
                'issues' => ['external-target-network-path-base-uri'],
            ];
        }

        if ($kind === 'relative-reference' || $kind === 'fragment-reference') {
            $basePart = OpcPackagePath::canonicalPartName($sourcePartName, true);

            return [
                'requiresBaseUri' => true,
                'basePart' => $basePart === '/' ? null : $basePart,
                'reason' => $kind === 'relative-reference'
                    ? 'external-target-relative-reference'
                    : 'external-target-fragment-reference',
                'issues' => $basePart === '/' ? ['external-target-package-root-base-uri'] : [],
            ];
        }

        return [
            'requiresBaseUri' => false,
            'basePart' => null,
            'reason' => null,
            'issues' => [],
        ];
    }

    /**
     * @param array{kind:string, scheme:?string, allowed:bool, issues:list<string>} $externalTarget
     * @return list<string>
     */
    private function externalTargetPackagePartIssues(string $sourcePartName, string $target, array $externalTarget): array
    {
        if ($externalTarget['kind'] !== 'relative-reference' && $externalTarget['kind'] !== 'fragment-reference') {
            return [];
        }

        if (strcspn($target, '?#') === 0) {
            return [];
        }

        try {
            $resolvedTarget = OpcPackagePath::resolveInternalTarget($sourcePartName, $target);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $targetPartName = OpcPackagePath::stripQueryAndFragment($resolvedTarget);
        if ($targetPartName === '/' || $this->packagePartNameForEquivalent($targetPartName) === null) {
            return [];
        }

        return ['external-target-matches-package-part'];
    }

    /**
     * @param array{target:string, external:bool, issues:list<string>} $target
     */
    private static function targetPartFromPreflightTarget(array $target): ?string
    {
        if ($target['external'] || in_array('invalid-target', $target['issues'], true)) {
            return null;
        }

        return OpcPackagePath::stripQueryAndFragment($target['target']);
    }

    /**
     * @return array{query:?string, fragment:?string}
     */
    private static function targetQueryAndFragment(string $target): array
    {
        $suffixOffset = strcspn($target, '?#');
        $suffix = substr($target, $suffixOffset);
        if ($suffix === '') {
            return ['query' => null, 'fragment' => null];
        }

        $query = null;
        $fragment = null;
        $queryPosition = strpos($suffix, '?');
        $fragmentPosition = strpos($suffix, '#');

        if ($queryPosition !== false) {
            $queryStart = $queryPosition + 1;
            $queryEnd = $fragmentPosition === false ? strlen($suffix) : $fragmentPosition;
            $query = substr($suffix, $queryStart, $queryEnd - $queryStart);
        }

        if ($fragmentPosition !== false) {
            $fragment = substr($suffix, $fragmentPosition + 1);
        }

        return ['query' => $query, 'fragment' => $fragment];
    }
}
