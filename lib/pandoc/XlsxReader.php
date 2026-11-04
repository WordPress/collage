<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxReader
{
    private const TRANSITIONAL_OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const STRICT_OFFICE_DOCUMENT_RELATIONSHIP = 'http://purl.oclc.org/ooxml/officeDocument/relationships/officeDocument';
    private const OFFICE_DOCUMENT_RELATIONSHIP_TYPES = [
        self::TRANSITIONAL_OFFICE_DOCUMENT_RELATIONSHIP,
        self::STRICT_OFFICE_DOCUMENT_RELATIONSHIP,
    ];
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const COMMENTS_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    private const STRICT_COMMENTS_RELATIONSHIP = 'http://purl.oclc.org/ooxml/officeDocument/relationships/comments';
    private const COMMENTS_RELATIONSHIP_TYPES = [
        self::COMMENTS_RELATIONSHIP,
        self::STRICT_COMMENTS_RELATIONSHIP,
    ];
    private const THREADED_COMMENTS_RELATIONSHIP = 'http://schemas.microsoft.com/office/2017/10/relationships/threadedComment';
    private const THREADED_COMMENT_PERSON_RELATIONSHIP = 'http://schemas.microsoft.com/office/2017/10/relationships/person';
    private const THREADED_COMMENTS_NAMESPACE = 'http://schemas.microsoft.com/office/spreadsheetml/2018/threadedcomments';
    private const MAX_XML_PART_BYTES = 8_388_608;
    private const MAX_RELATIONSHIP_INVENTORY_PARTS = 4_096;
    private const MAX_RELATIONSHIP_INVENTORY_BYTES = 33_554_432;
    private const MAX_MEDIA_METADATA_BYTES = 16_777_216;
    private const EMUS_PER_PIXEL = 9525;
    private const FEATURE_SPECS = [
        'drawing' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.drawing+xml'],
            'pathMarkers' => ['/drawings/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing',
            'rootLocalName' => 'wsDr',
        ],
        'image' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'],
            'contentTypes' => ['image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'],
            'pathMarkers' => ['/media/'],
            'rootNamespace' => null,
            'rootLocalName' => null,
            'xml' => false,
        ],
        'chart' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.drawingml.chart+xml'],
            'pathMarkers' => ['/charts/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'rootLocalName' => 'chartSpace',
        ],
        'pivotTable' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotTable'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivottable+xml'],
            'pathMarkers' => ['/pivotTables/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotTableDefinition',
        ],
        'pivotCacheDefinition' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheDefinition'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcachedefinition+xml'],
            'pathMarkers' => ['/pivotCache/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotCacheDefinition',
        ],
        'pivotCacheRecords' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheRecords'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcacherecords+xml'],
            'pathMarkers' => ['/pivotCache/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotCacheRecords',
        ],
        'slicer' => [
            'relationshipTypes' => ['http://schemas.microsoft.com/office/2007/relationships/slicer'],
            'contentTypes' => ['application/vnd.ms-excel.slicer+xml'],
            'pathMarkers' => ['/slicers/'],
            'rootNamespace' => 'http://schemas.microsoft.com/office/spreadsheetml/2009/9/main',
            'rootLocalName' => 'slicers',
        ],
        'slicerCache' => [
            'relationshipTypes' => ['http://schemas.microsoft.com/office/2007/relationships/slicerCache'],
            'contentTypes' => ['application/vnd.ms-excel.slicercache+xml'],
            'pathMarkers' => ['/slicerCaches/'],
            'rootNamespace' => 'http://schemas.microsoft.com/office/spreadsheetml/2009/9/main',
            'rootLocalName' => 'slicerCaches',
        ],
        'comments' => [
            'relationshipTypes' => self::COMMENTS_RELATIONSHIP_TYPES,
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml'],
            'pathMarkers' => ['/comments'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootNamespaces' => [
                'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                'http://purl.oclc.org/ooxml/spreadsheetml/main',
            ],
            'rootLocalName' => 'comments',
        ],
        'threadedComments' => [
            'relationshipTypes' => [self::THREADED_COMMENTS_RELATIONSHIP],
            'contentTypes' => ['application/vnd.ms-excel.threadedcomments+xml'],
            'pathMarkers' => ['/threadedComments/'],
            'rootNamespace' => self::THREADED_COMMENTS_NAMESPACE,
            'rootLocalName' => 'ThreadedComments',
        ],
        'threadedCommentPerson' => [
            'relationshipTypes' => [self::THREADED_COMMENT_PERSON_RELATIONSHIP],
            'contentTypes' => ['application/vnd.ms-excel.person+xml'],
            'pathMarkers' => ['/persons/'],
            'rootNamespace' => self::THREADED_COMMENTS_NAMESPACE,
            'rootLocalName' => 'personList',
        ],
    ];

    public function read(string $bytes): AstNode
    {
        try {
            $package = ZipPackage::fromString($bytes);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return $this->fallbackDocument($bytes, $exception->getMessage());
        }

        return $this->readPackage($package, strlen($bytes));
    }

    private function fallbackDocument(string $bytes, string $error): AstNode
    {
        return new AstNode('document', [
            'sourceFormat' => 'xlsx',
            'meta' => [
                'sourceFormat' => 'xlsx',
                'xlsxFallback' => [
                    'reason' => 'invalid-xlsx-package',
                    'error' => $error,
                    'sourceBytes' => strlen($bytes),
                    'sourceSha256' => hash('sha256', $bytes),
                ],
            ],
        ], [
            new AstNode('code_block', [
                'text' => $this->sourcePreview($bytes),
                'classes' => ['xlsx-source'],
            ]),
        ]);
    }

    private function sourcePreview(string $bytes): string
    {
        if (strlen($bytes) <= 8192) {
            return $bytes;
        }

        return substr($bytes, 0, 8192) . "\n...";
    }

    private function readPackage(ZipPackage $package, int $sourceBytes): AstNode
    {
        $relationshipInventory = OpcRelationshipInventory::fromPackage(
            $package,
            self::MAX_XML_PART_BYTES,
            self::MAX_RELATIONSHIP_INVENTORY_PARTS,
            self::MAX_RELATIONSHIP_INVENTORY_BYTES,
        );
        $rootRelationships = $relationshipInventory->relationshipsForSource('/');
        $workbookRelationship = $this->workbookRelationship($rootRelationships);
        $workbookPart = OpcPackagePath::stripQueryAndFragment($rootRelationships->resolveTarget($workbookRelationship));
        $workbook = $this->loadPackageXml($package, $workbookPart, 'XLSX workbook');
        $workbookInfo = $this->parseWorkbook($workbook);
        $sheets = $workbookInfo['sheets'];
        $workbookRelationships = $this->relationshipsOrEmpty($relationshipInventory, $workbookPart);
        $sharedStrings = $this->readSharedStrings($package, $workbookRelationships);
        $styles = $this->readStyles($package, $workbookRelationships);
        $contentTypeReview = $this->readContentTypesReview($relationshipInventory);
        $featureMetadata = $this->readFeatureMetadata($package, $contentTypeReview['types'], $relationshipInventory);

        $blocks = [];
        $sheetReviews = [];
        $tableCount = 0;
        $formulaCellCount = 0;
        $formulaCachedValueCount = 0;
        $sharedFormulaCellCount = 0;
        $sharedFormulaMasterCount = 0;
        $sharedFormulaFollowerCount = 0;
        $errorCellCount = 0;
        $tablePartCount = 0;
        $autoFilterCount = 0;
        $autoFilterDetailCount = 0;
        $commentCount = 0;
        $hiddenRowCount = 0;
        $hiddenColumnCount = 0;
        $customRowHeightCount = 0;
        $customColumnWidthCount = 0;
        $sheetViewCount = 0;
        $frozenPaneCount = 0;
        $splitPaneCount = 0;
        $dataValidationCount = 0;
        $dataValidationSheetCount = 0;
        $dataValidationRangeCount = 0;
        $failedSheetCount = 0;
        $sheetAnchorIds = [];
        $threadedCommentPersonReview = null;
        foreach ($sheets as $sheet) {
            $sheetAnchorIds[(string) $sheet['name']] = 'sheet-' . $sheet['index'];
        }
        foreach ($sheets as $sheet) {
            $sheetPart = null;
            try {
                $relationship = $workbookRelationships->byId($sheet['relationshipId']);
                if (!$relationship instanceof OpcRelationship) {
                    throw new \RuntimeException('XLSX sheet relationship not found: ' . $sheet['relationshipId']);
                }
                if ($relationship->isExternal()) {
                    throw new \RuntimeException('XLSX external worksheet relationships are not supported');
                }

                $sheetPart = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
                $sheetDocument = $this->loadPackageXml($package, $sheetPart, 'XLSX worksheet ' . $sheet['name']);
                $sheetRelationships = $this->relationshipsOrEmpty($relationshipInventory, $sheetPart);
                $sheetLayout = $this->parseSheetLayoutMetadata($sheetDocument);
                $sheetComments = $this->parseSheetComments(
                    $package,
                    $sheetPart,
                    $sheetRelationships,
                    $relationshipInventory,
                    $threadedCommentPersonReview,
                );
                $sheetDiagnostics = $this->parseSheetDiagnostics($sheetDocument);
                $sheetDataValidations = $this->parseSheetDataValidations($sheetDocument);
                $sheetTableMetadata = $this->parseSheetTableMetadata($package, $sheetPart, $sheetDocument, $sheetRelationships);
                $cells = $this->parseSheetCells($sheetDocument, $sharedStrings, $styles, $workbookInfo['date1904'], $sheetRelationships, $sheetComments['commentsByCell'], $sheetLayout, (string) $sheet['name'], $sheetAnchorIds);
                $table = $this->cellsToTable($sheet['name'], $cells);
                $blocks[] = new AstNode('heading', [
                    'level' => 2,
                    'id' => 'sheet-' . $sheet['index'],
                    'text' => $sheet['name'],
                ], [new AstNode('text', ['text' => $sheet['name']])]);
                if ($table instanceof AstNode) {
                    $blocks[] = $table;
                    $tableCount++;
                }
            } catch (\RuntimeException|\InvalidArgumentException $exception) {
                ++$failedSheetCount;
                $blocks[] = new AstNode('heading', [
                    'level' => 2,
                    'id' => 'sheet-' . $sheet['index'],
                    'text' => $sheet['name'],
                ], [new AstNode('text', ['text' => $sheet['name']])]);
                $blocks[] = new AstNode('paragraph', [
                    'text' => 'Worksheet could not be imported: ' . $exception->getMessage(),
                    'classes' => ['xlsx-sheet-import-warning'],
                ], [
                    new AstNode('text', ['text' => 'Worksheet could not be imported: ' . $exception->getMessage()]),
                ]);
                $sheetReviews[] = [
                    'index' => $sheet['index'],
                    'name' => $sheet['name'],
                    'relationshipId' => $sheet['relationshipId'],
                    'partName' => is_string($sheetPart) ? ltrim($sheetPart, '/') : null,
                    'state' => $sheet['state'],
                    'hidden' => $sheet['hidden'],
                    'veryHidden' => $sheet['veryHidden'],
                    'readable' => false,
                    'error' => $exception->getMessage(),
                    'tableEmitted' => false,
                ];
                continue;
            }

            $formulaCellCount += $sheetDiagnostics['formulaCellCount'];
            $formulaCachedValueCount += $sheetDiagnostics['formulaCachedValueCount'];
            $sharedFormulaCellCount += $sheetDiagnostics['sharedFormulaCellCount'];
            $sharedFormulaMasterCount += $sheetDiagnostics['sharedFormulaMasterCount'];
            $sharedFormulaFollowerCount += $sheetDiagnostics['sharedFormulaFollowerCount'];
            $errorCellCount += $sheetDiagnostics['errorCellCount'];
            $tablePartCount += count($sheetTableMetadata['tableParts']);
            $autoFilterCount += count($sheetTableMetadata['autoFilterRanges']);
            $autoFilterDetailCount += count($sheetTableMetadata['autoFilters']);
            $commentCount += $sheetComments['commentCount'];
            $hiddenRowCount += $sheetLayout['hiddenRowCount'];
            $hiddenColumnCount += $sheetLayout['hiddenColumnCount'];
            $customRowHeightCount += $sheetLayout['customRowHeightCount'];
            $customColumnWidthCount += $sheetLayout['customColumnWidthCount'];
            $sheetViewCount += $sheetLayout['sheetViewCount'];
            $frozenPaneCount += $sheetLayout['frozenPaneCount'];
            $splitPaneCount += $sheetLayout['splitPaneCount'];
            $dataValidationCount += $sheetDataValidations['validationCount'];
            $dataValidationRangeCount += $sheetDataValidations['rangeCount'];
            if ($sheetDataValidations['validationCount'] > 0) {
                ++$dataValidationSheetCount;
            }

            $sheetReviews[] = [
                'index' => $sheet['index'],
                'name' => $sheet['name'],
                'relationshipId' => $sheet['relationshipId'],
                'partName' => ltrim($sheetPart, '/'),
                'state' => $sheet['state'],
                'hidden' => $sheet['hidden'],
                'veryHidden' => $sheet['veryHidden'],
                'cellCount' => count($cells),
                'hyperlinkCount' => count(array_filter($cells, static fn (array $cell): bool => ($cell['url'] ?? '') !== '')),
                'mergedCellCount' => count(array_filter($cells, static fn (array $cell): bool => (int) ($cell['colspan'] ?? 1) > 1 || (int) ($cell['rowspan'] ?? 1) > 1)),
                'formulaCellCount' => $sheetDiagnostics['formulaCellCount'],
                'formulaCachedValueCount' => $sheetDiagnostics['formulaCachedValueCount'],
                'sharedFormulaCellCount' => $sheetDiagnostics['sharedFormulaCellCount'],
                'sharedFormulaMasterCount' => $sheetDiagnostics['sharedFormulaMasterCount'],
                'sharedFormulaFollowerCount' => $sheetDiagnostics['sharedFormulaFollowerCount'],
                'formulaDiagnostics' => $sheetDiagnostics['formulaDiagnostics'],
                'errorCellCount' => $sheetDiagnostics['errorCellCount'],
                'errorDiagnostics' => $sheetDiagnostics['errorDiagnostics'],
                'hiddenRowCount' => $sheetLayout['hiddenRowCount'],
                'hiddenColumnCount' => $sheetLayout['hiddenColumnCount'],
                'customRowHeightCount' => $sheetLayout['customRowHeightCount'],
                'customColumnWidthCount' => $sheetLayout['customColumnWidthCount'],
                'rowMetadata' => $sheetLayout['rows'],
                'columnMetadata' => $sheetLayout['columnRanges'],
                'sheetViewCount' => $sheetLayout['sheetViewCount'],
                'sheetViews' => $sheetLayout['sheetViews'],
                'frozenPaneCount' => $sheetLayout['frozenPaneCount'],
                'splitPaneCount' => $sheetLayout['splitPaneCount'],
                'dataValidationCount' => $sheetDataValidations['validationCount'],
                'dataValidationDeclaredCount' => $sheetDataValidations['declaredCount'],
                'dataValidationRangeCount' => $sheetDataValidations['rangeCount'],
                'dataValidationTypeCounts' => $sheetDataValidations['typeCounts'],
                'dataValidationRanges' => $sheetDataValidations['ranges'],
                'dataValidations' => $sheetDataValidations['validations'],
                'dataValidationDiagnostics' => $sheetDataValidations['diagnostics'],
                'commentCount' => $sheetComments['commentCount'],
                'comments' => $sheetComments['comments'],
                'commentDiagnostics' => $sheetComments['commentDiagnostics'],
                'autoFilterRanges' => $sheetTableMetadata['autoFilterRanges'],
                'autoFilters' => $sheetTableMetadata['autoFilters'],
                'tableParts' => $sheetTableMetadata['tableParts'],
                'tablePartDiagnostics' => $sheetTableMetadata['tablePartDiagnostics'],
                'tableEmitted' => $table instanceof AstNode,
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'xlsx',
            'meta' => [],
            'xlsx' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-xlsx-reader',
                'sourceBytes' => $sourceBytes,
                'entryCount' => count($package->names()),
                'workbookPart' => ltrim($workbookPart, '/'),
                'sheetCount' => count($sheets),
                'sheetNavigationEmitted' => false,
                'failedSheetCount' => $failedSheetCount,
                'tableCount' => $tableCount,
                'hiddenSheetPolicy' => 'emit-all-sheets-record-visibility',
                'hiddenSheetCount' => count(array_filter($sheets, static fn (array $sheet): bool => $sheet['hidden'])),
                'veryHiddenSheetCount' => count(array_filter($sheets, static fn (array $sheet): bool => $sheet['veryHidden'])),
                'workbookProperties' => $workbookInfo['workbookProperties'],
                'calculationProperties' => $workbookInfo['calculationProperties'],
                'definedNamePolicy' => 'defined-name-formula-metadata-only',
                'definedNameCount' => $workbookInfo['definedNameCount'],
                'definedNames' => $workbookInfo['definedNames'],
                'definedNameNames' => $workbookInfo['definedNameNames'],
                'hiddenDefinedNameCount' => $workbookInfo['hiddenDefinedNameCount'],
                'printAreaDefinedNameCount' => $workbookInfo['printAreaDefinedNameCount'],
                'filterDatabaseDefinedNameCount' => $workbookInfo['filterDatabaseDefinedNameCount'],
                'externalReferenceCount' => $workbookInfo['externalReferenceCount'],
                'sharedStringCount' => count($sharedStrings),
                'styleFontCount' => count($styles['fonts']),
                'styleCellStyleFormatCount' => count($styles['cellStyleFormats']),
                'styleCellFormatCount' => count($styles['cellFormats']),
                'styleNamedCellStyleCount' => count($styles['cellStyles']),
                'styleFillCount' => count($styles['fills']),
                'styleGradientFillCount' => count(array_filter(
                    $styles['fills'],
                    static fn (array $fill): bool => ($fill['gradientFill'] ?? false) === true
                )),
                'styleBorderCount' => count($styles['borders']),
                'styleCustomNumberFormatCount' => count($styles['customNumberFormats']),
                'cellStyles' => $styles['cellStyles'],
                'date1904' => $workbookInfo['date1904'],
                'formulaCellCount' => $formulaCellCount,
                'formulaCachedValueCount' => $formulaCachedValueCount,
                'sharedFormulaCellCount' => $sharedFormulaCellCount,
                'sharedFormulaMasterCount' => $sharedFormulaMasterCount,
                'sharedFormulaFollowerCount' => $sharedFormulaFollowerCount,
                'errorCellCount' => $errorCellCount,
                'commentCount' => $commentCount,
                'hiddenRowCount' => $hiddenRowCount,
                'hiddenColumnCount' => $hiddenColumnCount,
                'customRowHeightCount' => $customRowHeightCount,
                'customColumnWidthCount' => $customColumnWidthCount,
                'sheetViewPolicy' => 'xlsx-sheet-view-metadata-only',
                'sheetViewCount' => $sheetViewCount,
                'frozenPaneCount' => $frozenPaneCount,
                'splitPaneCount' => $splitPaneCount,
                'dataValidationPolicy' => 'xlsx-data-validation-metadata-only',
                'dataValidationCount' => $dataValidationCount,
                'dataValidationSheetCount' => $dataValidationSheetCount,
                'dataValidationRangeCount' => $dataValidationRangeCount,
                'tablePartCount' => $tablePartCount,
                'autoFilterCount' => $autoFilterCount,
                'autoFilterDetailCount' => $autoFilterDetailCount,
                'sheets' => $sheetReviews,
                'featureMetadata' => $featureMetadata,
                'contentTypesAvailable' => $contentTypeReview['available'],
                'contentTypesParseError' => $contentTypeReview['parseError'],
                'payloadExposurePolicy' => 'xml-text-only',
                'formulaPolicy' => 'cached-values-only-no-formula-evaluation',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'fixtures' => [
                        'test/xlsx-reader/basic.xlsx',
                        'test/xlsx-reader/basic.native',
                    ],
                    'source' => 'Pandoc 4f5226df src/Text/Pandoc/Readers/Xlsx.hs and src/Text/Pandoc/Readers/Xlsx/{Parse,Sheets,Cells}.hs',
                ],
            ],
        ], $blocks);
    }

    private function workbookRelationship(OpcRelationships $relationships): OpcRelationship
    {
        $relationship = $relationships->firstOfTypes(self::OFFICE_DOCUMENT_RELATIONSHIP_TYPES);
        if ($relationship instanceof OpcRelationship) {
            return $relationship;
        }

        throw new \RuntimeException('XLSX package does not declare a workbook relationship');
    }

    private function relationshipsOrEmpty(OpcRelationshipInventory $relationshipInventory, string $sourcePart): OpcRelationships
    {
        if (!$relationshipInventory->hasRelationshipsForSource($sourcePart)) {
            return new OpcRelationships($sourcePart);
        }

        return $relationshipInventory->relationshipsForSource($sourcePart);
    }

    private function loadPackageXml(ZipPackage $package, string $partName, string $label): \DOMDocument
    {
        $xml = $package->read($partName, self::MAX_XML_PART_BYTES);

        return XmlHtmlDom::loadXmlDocument($xml, $label, false);
    }

    /**
     * @return array{available:bool, parseError:?string, types:?OpcContentTypes}
     */
    private function readContentTypesReview(OpcRelationshipInventory $relationshipInventory): array
    {
        if (!$relationshipInventory->hasContentTypes()) {
            return [
                'available' => false,
                'parseError' => null,
                'types' => null,
            ];
        }

        return [
            'available' => true,
            'parseError' => $relationshipInventory->contentTypesParseError(),
            'types' => $relationshipInventory->contentTypes(),
        ];
    }

    /**
     * @return array{
     *     summary:array<string, mixed>,
     *     byKind:array<string, array{count:int, existingCount:int, missingCount:int, externalCount:int, relationshipCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}>,
     *     items:list<array<string, mixed>>
     * }
     */
    private function readFeatureMetadata(
        ZipPackage $package,
        ?OpcContentTypes $contentTypes,
        OpcRelationshipInventory $relationshipInventory,
    ): array
    {
        $itemsByKey = [];
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            try {
                $partName = OpcPackagePath::canonicalPartName($name);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($partName === '/[Content_Types].xml' || OpcRelationships::isRelationshipPartName($partName)) {
                continue;
            }

            $contentType = $this->contentTypeInfo($contentTypes, $partName);
            $kind = $this->featureKindForPart($partName, $contentType['contentTypeBase']);
            if ($kind === null) {
                continue;
            }

            $key = $this->featureItemKey($kind, $partName, null);
            $itemsByKey[$key] = $this->featureItem($kind, $partName, null, true, false, $contentType);
        }

        $relationshipSets = $this->packageRelationshipSets($relationshipInventory);
        foreach ($relationshipSets['sets'] as $relationshipSet) {
            $relationships = $relationshipSet['relationships'];
            foreach ($relationships->all() as $relationship) {
                $kind = $this->featureKindForRelationship($relationship);
                if ($kind === null) {
                    continue;
                }

                $targetPart = null;
                $externalTarget = null;
                $targetSuffix = [
                    'targetQuery' => null,
                    'targetFragment' => null,
                    'targetSuffix' => '',
                ];

                if ($relationship->isExternal()) {
                    $externalTarget = $relationship->target;
                    $targetSuffix = $this->targetSuffix($relationship->target);
                    $contentType = $this->emptyContentTypeInfo('external');
                    $key = $this->featureItemKey($kind, null, $relationshipSet['sourcePart'] . ':' . $relationship->id);
                    if (!isset($itemsByKey[$key])) {
                        $itemsByKey[$key] = $this->featureItem($kind, null, $externalTarget, false, true, $contentType);
                    }
                } else {
                    try {
                        $resolvedTarget = $relationships->resolveTarget($relationship);
                        $targetSuffix = $this->targetSuffix($resolvedTarget);
                        $targetPart = OpcPackagePath::stripQueryAndFragment($resolvedTarget);
                    } catch (\Throwable $exception) {
                        $externalTarget = $relationship->target;
                        $contentType = $this->emptyContentTypeInfo('unresolved');
                        $key = $this->featureItemKey($kind, null, $relationshipSet['sourcePart'] . ':' . $relationship->id);
                        if (!isset($itemsByKey[$key])) {
                            $itemsByKey[$key] = $this->featureItem($kind, null, $externalTarget, false, false, $contentType);
                            $itemsByKey[$key]['issues'][] = $this->featureIssueStem($kind) . '-target-resolution-error';
                            $itemsByKey[$key]['targetResolutionError'] = $exception->getMessage();
                        }
                    }

                    if ($targetPart !== null) {
                        $contentType = $this->contentTypeInfo($contentTypes, $targetPart);
                        $key = $this->featureItemKey($kind, $targetPart, null);
                        if (!isset($itemsByKey[$key])) {
                            $itemsByKey[$key] = $this->featureItem(
                                $kind,
                                $targetPart,
                                null,
                                $package->has($targetPart),
                                false,
                                $contentType
                            );
                        }
                    }
                }

                $itemsByKey[$key]['relationshipCount']++;
                $itemsByKey[$key]['relationshipRefs'][] = [
                    'sourcePart' => ltrim($relationshipSet['sourcePart'], '/'),
                    'relationshipPart' => ltrim($relationshipSet['relationshipPart'], '/'),
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'target' => $relationship->target,
                    'targetMode' => $relationship->targetMode,
                    'targetPart' => $targetPart === null ? null : ltrim($targetPart, '/'),
                    'targetQuery' => $targetSuffix['targetQuery'],
                    'targetFragment' => $targetSuffix['targetFragment'],
                    'targetSuffix' => $targetSuffix['targetSuffix'],
                ];
            }
        }

        $items = array_values($itemsByKey);
        usort($items, static function (array $left, array $right): int {
            return [
                $left['kind'],
                $left['partName'] ?? '',
                $left['externalTarget'] ?? '',
            ] <=> [
                $right['kind'],
                $right['partName'] ?? '',
                $right['externalTarget'] ?? '',
            ];
        });

        foreach ($items as $index => $item) {
            $items[$index] = $this->finalizeFeatureItem($package, $item);
        }

        return $this->featureMetadataSummary($items, $relationshipSets['parseErrors']);
    }

    /**
     * @return array{sets:list<array{sourcePart:string, relationshipPart:string, relationships:OpcRelationships}>, parseErrors:list<array{relationshipPart:string, sourcePart:?string, error:string}>}
     */
    private function packageRelationshipSets(OpcRelationshipInventory $relationshipInventory): array
    {
        $sets = [];
        $parseErrors = $relationshipInventory->parseErrors();
        foreach ($relationshipInventory->sourceParts() as $part) {
            $relationshipPart = $part['relationshipPartName'];
            $sourcePart = $part['sourcePartName'];
            try {
                $sets[] = [
                    'sourcePart' => $sourcePart,
                    'relationshipPart' => $relationshipPart,
                    'relationships' => $relationshipInventory->relationshipsForSource($sourcePart),
                ];
            } catch (\Throwable $exception) {
                $parseErrors[] = [
                    'relationshipPart' => ltrim($relationshipPart, '/'),
                    'sourcePart' => ltrim($sourcePart, '/'),
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'sets' => $sets,
            'parseErrors' => $parseErrors,
        ];
    }

    /**
     * @return array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string}
     */
    private function contentTypeInfo(?OpcContentTypes $contentTypes, string $partName): array
    {
        if (!$contentTypes instanceof OpcContentTypes) {
            return $this->emptyContentTypeInfo('unavailable');
        }

        $resolution = $contentTypes->contentTypeResolutionForPart($partName);
        $contentType = $resolution['contentType'];

        return [
            'contentType' => $contentType,
            'contentTypeBase' => is_string($contentType) ? $this->contentTypeBase($contentType) : null,
            'contentTypeSource' => $resolution['contentTypeSource'],
            'contentTypeDefaultExtension' => $resolution['defaultExtension'],
            'contentTypeOverridePartName' => $resolution['overridePartName'] === null
                ? null
                : ltrim((string) $resolution['overridePartName'], '/'),
        ];
    }

    /**
     * @return array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string}
     */
    private function emptyContentTypeInfo(string $source): array
    {
        return [
            'contentType' => null,
            'contentTypeBase' => null,
            'contentTypeSource' => $source,
            'contentTypeDefaultExtension' => null,
            'contentTypeOverridePartName' => null,
        ];
    }

    private function contentTypeBase(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private function featureKindForRelationship(OpcRelationship $relationship): ?string
    {
        foreach (self::FEATURE_SPECS as $kind => $spec) {
            if (in_array($relationship->type, $spec['relationshipTypes'], true)) {
                return $kind;
            }
        }

        return null;
    }

    private function featureKindForPart(string $partName, ?string $contentTypeBase): ?string
    {
        foreach (self::FEATURE_SPECS as $kind => $spec) {
            if ($contentTypeBase !== null && in_array($contentTypeBase, $spec['contentTypes'], true)) {
                return $kind;
            }
        }

        $path = strtolower($partName);
        if (str_contains($path, '/pivotcache/pivotcachedefinition')) {
            return 'pivotCacheDefinition';
        }
        if (str_contains($path, '/pivotcache/pivotcacherecords')) {
            return 'pivotCacheRecords';
        }
        if (str_contains($path, '/slicercaches/')) {
            return 'slicerCache';
        }

        foreach (self::FEATURE_SPECS as $kind => $spec) {
            foreach ($spec['pathMarkers'] as $marker) {
                if (str_contains($path, strtolower($marker))) {
                    return $kind;
                }
            }
        }

        return null;
    }

    private function featureItemKey(string $kind, ?string $partName, ?string $externalKey): string
    {
        if ($partName !== null) {
            return $kind . ':part:' . ltrim($partName, '/');
        }

        return $kind . ':external:' . (string) $externalKey;
    }

    /**
     * @param array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string} $contentType
     * @return array<string, mixed>
     */
    private function featureItem(
        string $kind,
        ?string $partName,
        ?string $externalTarget,
        bool $exists,
        bool $external,
        array $contentType
    ): array {
        $spec = self::FEATURE_SPECS[$kind];
        $contentTypeMatchesExpected = $contentType['contentTypeBase'] === null
            ? null
            : in_array($contentType['contentTypeBase'], $spec['contentTypes'], true);
        $issueStem = $this->featureIssueStem($kind);
        $issues = [];
        if ($external) {
            $issues[] = 'external-' . $issueStem . '-part';
        } elseif (!$exists) {
            $issues[] = 'missing-' . $issueStem . '-part';
        }
        if (!$external && $contentType['contentTypeSource'] !== 'unavailable') {
            if ($contentType['contentTypeBase'] === null) {
                $issues[] = 'missing-' . $issueStem . '-content-type';
            } elseif ($contentTypeMatchesExpected !== true) {
                $issues[] = 'unexpected-' . $issueStem . '-content-type';
            }
        }

        return [
            'kind' => $kind,
            'partName' => $partName === null ? null : ltrim($partName, '/'),
            'externalTarget' => $externalTarget,
            'exists' => $exists,
            'external' => $external,
            'contentType' => $contentType['contentType'],
            'contentTypeBase' => $contentType['contentTypeBase'],
            'contentTypeSource' => $contentType['contentTypeSource'],
            'contentTypeDefaultExtension' => $contentType['contentTypeDefaultExtension'],
            'contentTypeOverridePartName' => $contentType['contentTypeOverridePartName'],
            'contentTypeMatchesExpected' => $contentTypeMatchesExpected,
            'expectedContentTypes' => $spec['contentTypes'],
            'expectedRootNamespace' => $spec['rootNamespace'],
            'expectedRootLocalName' => $spec['rootLocalName'],
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'xmlParseError' => null,
            'relationshipCount' => 0,
            'relationshipRefs' => [],
            'issues' => $issues,
            'reviewPolicy' => $issueStem . '-metadata-only',
            'byteExposurePolicy' => $issueStem . '-part-bytes-blocked',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function finalizeFeatureItem(ZipPackage $package, array $item): array
    {
        if (($item['exists'] ?? false) !== true || ($item['external'] ?? false) === true || !is_string($item['partName'] ?? null)) {
            $item['issues'] = array_values(array_unique($item['issues']));
            return $item;
        }

        $partName = (string) $item['partName'];
        $kind = (string) $item['kind'];
        $issueStem = $this->featureIssueStem($kind);
        if ((self::FEATURE_SPECS[$kind]['xml'] ?? true) === false) {
            return $this->finalizeBinaryFeatureItem($package, $item, $partName, $issueStem);
        }

        try {
            $document = $this->loadPackageXml($package, $partName, 'XLSX ' . $issueStem . ' metadata ' . $partName);
            $root = $document->documentElement;
            if ($root instanceof \DOMElement) {
                $item['rootNamespace'] = $root->namespaceURI;
                $item['rootLocalName'] = $root->localName;
                $expectedRootNamespaces = self::FEATURE_SPECS[$kind]['rootNamespaces'] ?? [$item['expectedRootNamespace']];
                $item['validRoot'] = in_array($root->namespaceURI, $expectedRootNamespaces, true)
                    && $root->localName === $item['expectedRootLocalName'];
                if ($item['validRoot'] !== true) {
                    $item['issues'][] = 'unexpected-' . $issueStem . '-root';
                }
            } else {
                $item['validRoot'] = false;
                $item['issues'][] = 'missing-' . $issueStem . '-root';
            }
        } catch (\Throwable $exception) {
            $item['validRoot'] = false;
            $item['xmlParseError'] = $exception->getMessage();
            $item['issues'][] = 'invalid-' . $issueStem . '-xml';
        }

        $item['issues'] = array_values(array_unique($item['issues']));

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function finalizeBinaryFeatureItem(ZipPackage $package, array $item, string $partName, string $issueStem): array
    {
        try {
            $entry = $package->entry($partName);
            $item['byteSize'] = $entry->uncompressedSize;
            $bytes = $package->read($partName, self::MAX_MEDIA_METADATA_BYTES);
            $item['sha256'] = hash('sha256', $bytes);
            $dimensions = $this->imageDimensions($bytes);
            $item['imageWidthPixels'] = $dimensions['width'] ?? null;
            $item['imageHeightPixels'] = $dimensions['height'] ?? null;
            if ($dimensions === null) {
                $item['issues'][] = 'unknown-' . $issueStem . '-dimensions';
            }
        } catch (\Throwable $exception) {
            $item['byteSize'] ??= null;
            $item['sha256'] = null;
            $item['imageWidthPixels'] = null;
            $item['imageHeightPixels'] = null;
            $item['issues'][] = $issueStem . '-metadata-read-error';
            $item['metadataReadError'] = $exception->getMessage();
        }

        if ((string) ($item['kind'] ?? '') === 'image') {
            $item['drawingAnchors'] = $this->imageDrawingAnchors($package, $item);
            $item['drawingAnchorCount'] = count($item['drawingAnchors']);
        }

        $item['issues'] = array_values(array_unique($item['issues']));

        return $item;
    }

    /**
     * @return array{width:int, height:int}|null
     */
    private function imageDimensions(string $bytes): ?array
    {
        $length = strlen($bytes);
        if ($length >= 24 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $size = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

            return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
        }

        if ($length >= 10 && (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a')) {
            $size = unpack('vwidth/vheight', substr($bytes, 6, 4));

            return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
        }

        if ($length >= 26 && substr($bytes, 0, 2) === 'BM') {
            return $this->bmpDimensions($bytes);
        }

        if ($length >= 4 && substr($bytes, 0, 2) === "\xff\xd8") {
            return $this->jpegDimensions($bytes);
        }

        return null;
    }

    /**
     * @return array{width:int, height:int}|null
     */
    private function bmpDimensions(string $bytes): ?array
    {
        $header = unpack('VdibHeaderSize', substr($bytes, 14, 4));
        if (!is_array($header)) {
            return null;
        }

        $dibHeaderSize = (int) $header['dibHeaderSize'];
        if ($dibHeaderSize === 12) {
            $size = unpack('vwidth/vheight', substr($bytes, 18, 4));
            if (!is_array($size)) {
                return null;
            }

            $width = (int) $size['width'];
            $height = (int) $size['height'];
        } elseif ($dibHeaderSize >= 40) {
            $width = $this->littleEndianSignedInt32(substr($bytes, 18, 4));
            $height = $this->littleEndianSignedInt32(substr($bytes, 22, 4));
        } else {
            return null;
        }

        if ($width === 0 || $height === 0) {
            return null;
        }

        return [
            'width' => abs($width),
            'height' => abs($height),
        ];
    }

    private function littleEndianSignedInt32(string $bytes): int
    {
        $value = unpack('Vvalue', $bytes);
        if (!is_array($value)) {
            return 0;
        }

        $value = (int) $value['value'];

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    /**
     * @return array{width:int, height:int}|null
     */
    private function jpegDimensions(string $bytes): ?array
    {
        $length = strlen($bytes);
        $offset = 2;
        while ($offset + 9 < $length) {
            if ($bytes[$offset] !== "\xff") {
                $offset++;
                continue;
            }
            while ($offset < $length && $bytes[$offset] === "\xff") {
                $offset++;
            }
            if ($offset >= $length) {
                return null;
            }

            $marker = ord($bytes[$offset]);
            $offset++;
            if ($marker === 0xd9 || $marker === 0xda) {
                return null;
            }
            if ($offset + 2 > $length) {
                return null;
            }

            $segmentLength = unpack('nlength', substr($bytes, $offset, 2));
            if (!is_array($segmentLength)) {
                return null;
            }
            $segmentLength = (int) $segmentLength['length'];
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                return null;
            }

            if (in_array($marker, [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf], true)) {
                $size = unpack('nheight/nwidth', substr($bytes, $offset + 3, 4));

                return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
            }

            $offset += $segmentLength;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function imageDrawingAnchors(ZipPackage $package, array $item): array
    {
        $anchors = [];
        foreach (($item['relationshipRefs'] ?? []) as $relationshipRef) {
            if (!is_array($relationshipRef)) {
                continue;
            }
            $sourcePart = (string) ($relationshipRef['sourcePart'] ?? '');
            $relationshipId = (string) ($relationshipRef['id'] ?? '');
            if ($sourcePart === '' || $relationshipId === '' || !str_contains(strtolower($sourcePart), '/drawings/')) {
                continue;
            }
            if (!$package->has($sourcePart)) {
                continue;
            }

            try {
                $document = $this->loadPackageXml($package, $sourcePart, 'XLSX drawing anchors ' . $sourcePart);
            } catch (\Throwable) {
                continue;
            }

            $root = $document->documentElement;
            if (!$root instanceof \DOMElement) {
                continue;
            }

            foreach ($this->descendantElements($root, 'blip') as $blip) {
                if ($this->relationshipReferenceId($blip) !== $relationshipId) {
                    continue;
                }
                $anchor = $this->ancestorDrawingAnchor($blip);
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }

                $anchors[] = $this->drawingAnchorMetadata($anchor, $blip, $sourcePart, $relationshipId, $relationshipRef);
            }
        }

        return $anchors;
    }

    private function relationshipReferenceId(\DOMElement $element): string
    {
        foreach (['embed', 'link', 'id'] as $localName) {
            $id = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, $localName);
            if ($id !== '') {
                return $id;
            }
            if ($element->hasAttribute('r:' . $localName)) {
                return $element->getAttribute('r:' . $localName);
            }
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && in_array($attribute->localName, ['embed', 'link', 'id'], true)) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function ancestorDrawingAnchor(\DOMElement $element): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && in_array($node->localName, ['twoCellAnchor', 'oneCellAnchor', 'absoluteAnchor'], true)) {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private function ancestorDrawingPicture(\DOMElement $element): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && $node->localName === 'pic') {
                return $node;
            }
            if ($node instanceof \DOMElement && in_array($node->localName, ['twoCellAnchor', 'oneCellAnchor', 'absoluteAnchor'], true)) {
                return null;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function drawingAnchorMetadata(\DOMElement $anchor, \DOMElement $blip, string $sourcePart, string $relationshipId, array $relationshipRef): array
    {
        $picture = $this->ancestorDrawingPicture($blip);
        $shapeProperties = $picture instanceof \DOMElement ? $this->firstChildElement($picture, 'spPr') : null;
        $extent = $this->firstChildElement($anchor, 'ext');
        $position = $this->firstChildElement($anchor, 'pos');
        $properties = $picture instanceof \DOMElement
            ? $this->firstDescendantElement($picture, 'cNvPr')
            : $this->firstDescendantElement($anchor, 'cNvPr');
        $from = $this->drawingMarker($this->firstChildElement($anchor, 'from'));
        $to = $this->drawingMarker($this->firstChildElement($anchor, 'to'));
        $extentEmu = $extent instanceof \DOMElement ? [
            'cx' => $this->integerAttribute($extent, 'cx'),
            'cy' => $this->integerAttribute($extent, 'cy'),
        ] : null;
        $positionEmu = $position instanceof \DOMElement ? [
            'x' => $this->integerAttribute($position, 'x'),
            'y' => $this->integerAttribute($position, 'y'),
        ] : null;

        return [
            'sourcePart' => ltrim($sourcePart, '/'),
            'relationshipId' => $relationshipId,
            'targetPart' => is_string($relationshipRef['targetPart'] ?? null) ? $relationshipRef['targetPart'] : null,
            'targetQuery' => is_string($relationshipRef['targetQuery'] ?? null) ? $relationshipRef['targetQuery'] : null,
            'targetFragment' => is_string($relationshipRef['targetFragment'] ?? null) ? $relationshipRef['targetFragment'] : null,
            'targetSuffix' => is_string($relationshipRef['targetSuffix'] ?? null) ? $relationshipRef['targetSuffix'] : '',
            'anchorType' => $anchor->localName,
            'editAs' => $anchor->hasAttribute('editAs') && trim($anchor->getAttribute('editAs')) !== '' ? trim($anchor->getAttribute('editAs')) : null,
            'from' => $from,
            'fromCell' => $this->drawingMarkerCellReference($from),
            'to' => $to,
            'toCell' => $this->drawingMarkerCellReference($to),
            'positionEmu' => $positionEmu,
            'positionPixels' => $this->drawingPointPixels($positionEmu),
            'extentEmu' => $extentEmu,
            'extentPixels' => $this->drawingExtentPixels($extentEmu),
            'blipReferenceKind' => $this->drawingBlipReferenceKind($blip),
            'blipCompressionState' => $this->nonEmptyAttribute($blip, 'cstate'),
            'crop' => $this->drawingCropMetadata($this->firstChildElement($blip, 'srcRect')),
            'transform' => $shapeProperties instanceof \DOMElement
                ? $this->drawingTransformMetadata($this->firstChildElement($shapeProperties, 'xfrm'))
                : null,
            'presetGeometry' => $shapeProperties instanceof \DOMElement
                ? $this->drawingPresetGeometry($this->firstChildElement($shapeProperties, 'prstGeom'))
                : null,
            'nonVisualPropertyId' => $properties instanceof \DOMElement ? $this->integerAttribute($properties, 'id') : null,
            'name' => $properties instanceof \DOMElement && trim($properties->getAttribute('name')) !== '' ? trim($properties->getAttribute('name')) : null,
            'description' => $properties instanceof \DOMElement && trim($properties->getAttribute('descr')) !== '' ? trim($properties->getAttribute('descr')) : null,
            'title' => $properties instanceof \DOMElement && trim($properties->getAttribute('title')) !== '' ? trim($properties->getAttribute('title')) : null,
            'hidden' => $properties instanceof \DOMElement ? $this->booleanAttribute($properties, 'hidden') : null,
            'clientData' => $this->drawingAnchorClientData($this->firstChildElement($anchor, 'clientData')),
        ];
    }

    private function drawingBlipReferenceKind(\DOMElement $blip): ?string
    {
        foreach (['embed', 'link', 'id'] as $localName) {
            if ($blip->getAttributeNS(self::RELATIONSHIP_NAMESPACE, $localName) !== '' || $blip->hasAttribute('r:' . $localName)) {
                return $localName;
            }
        }

        foreach ($blip->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && in_array($attribute->localName, ['embed', 'link', 'id'], true)) {
                return $attribute->localName;
            }
        }

        return null;
    }

    /**
     * @return array{left:?int, top:?int, right:?int, bottom:?int}|null
     */
    private function drawingCropMetadata(?\DOMElement $sourceRectangle): ?array
    {
        if (!$sourceRectangle instanceof \DOMElement) {
            return null;
        }

        return [
            'left' => $this->integerAttribute($sourceRectangle, 'l'),
            'top' => $this->integerAttribute($sourceRectangle, 't'),
            'right' => $this->integerAttribute($sourceRectangle, 'r'),
            'bottom' => $this->integerAttribute($sourceRectangle, 'b'),
        ];
    }

    /**
     * @return array{rotation:?int, flipHorizontal:?bool, flipVertical:?bool, offsetEmu:array{x:?int, y:?int}|null, offsetPixels:array{x:?float, y:?float}|null, extentEmu:array{cx:?int, cy:?int}|null, extentPixels:array{width:?float, height:?float}|null}|null
     */
    private function drawingTransformMetadata(?\DOMElement $transform): ?array
    {
        if (!$transform instanceof \DOMElement) {
            return null;
        }

        $offset = $this->firstChildElement($transform, 'off');
        $extent = $this->firstChildElement($transform, 'ext');
        $offsetEmu = $offset instanceof \DOMElement ? [
            'x' => $this->integerAttribute($offset, 'x'),
            'y' => $this->integerAttribute($offset, 'y'),
        ] : null;
        $extentEmu = $extent instanceof \DOMElement ? [
            'cx' => $this->integerAttribute($extent, 'cx'),
            'cy' => $this->integerAttribute($extent, 'cy'),
        ] : null;

        return [
            'rotation' => $this->integerAttribute($transform, 'rot'),
            'flipHorizontal' => $this->booleanAttribute($transform, 'flipH'),
            'flipVertical' => $this->booleanAttribute($transform, 'flipV'),
            'offsetEmu' => $offsetEmu,
            'offsetPixels' => $this->drawingPointPixels($offsetEmu),
            'extentEmu' => $extentEmu,
            'extentPixels' => $this->drawingExtentPixels($extentEmu),
        ];
    }

    private function drawingPresetGeometry(?\DOMElement $presetGeometry): ?string
    {
        if (!$presetGeometry instanceof \DOMElement) {
            return null;
        }

        return $this->nonEmptyAttribute($presetGeometry, 'prst');
    }

    /**
     * @return array{locksWithSheet:?bool, printsWithSheet:?bool}|null
     */
    private function drawingAnchorClientData(?\DOMElement $clientData): ?array
    {
        if (!$clientData instanceof \DOMElement) {
            return null;
        }

        return [
            'locksWithSheet' => $this->booleanAttribute($clientData, 'fLocksWithSheet'),
            'printsWithSheet' => $this->booleanAttribute($clientData, 'fPrintsWithSheet'),
        ];
    }

    /**
     * @return array{column:int|null, row:int|null, columnOffsetEmu:int|null, rowOffsetEmu:int|null, columnOffsetPixels:float|null, rowOffsetPixels:float|null}|null
     */
    private function drawingMarker(?\DOMElement $marker): ?array
    {
        if (!$marker instanceof \DOMElement) {
            return null;
        }

        $markerData = [
            'column' => $this->firstChildIntegerText($marker, 'col'),
            'row' => $this->firstChildIntegerText($marker, 'row'),
            'columnOffsetEmu' => $this->firstChildIntegerText($marker, 'colOff'),
            'rowOffsetEmu' => $this->firstChildIntegerText($marker, 'rowOff'),
        ];

        $markerData['columnOffsetPixels'] = $this->emuToPixels($markerData['columnOffsetEmu']);
        $markerData['rowOffsetPixels'] = $this->emuToPixels($markerData['rowOffsetEmu']);

        return $markerData;
    }

    /**
     * @param array<string, mixed>|null $marker
     */
    private function drawingMarkerCellReference(?array $marker): ?string
    {
        $column = $marker['column'] ?? null;
        $row = $marker['row'] ?? null;
        if (!is_int($column) || !is_int($row) || $column < 0 || $row < 0) {
            return null;
        }

        return $this->cellReferenceFromCoordinates($row + 1, $column + 1);
    }

    /**
     * @param array{x:int|null, y:int|null}|null $point
     * @return array{x:float|null, y:float|null}|null
     */
    private function drawingPointPixels(?array $point): ?array
    {
        if ($point === null) {
            return null;
        }

        return [
            'x' => $this->emuToPixels($point['x']),
            'y' => $this->emuToPixels($point['y']),
        ];
    }

    /**
     * @param array{cx:int|null, cy:int|null}|null $extent
     * @return array{width:float|null, height:float|null}|null
     */
    private function drawingExtentPixels(?array $extent): ?array
    {
        if ($extent === null) {
            return null;
        }

        return [
            'width' => $this->emuToPixels($extent['cx']),
            'height' => $this->emuToPixels($extent['cy']),
        ];
    }

    private function emuToPixels(?int $emu): ?float
    {
        return $emu === null ? null : round($emu / self::EMUS_PER_PIXEL, 4);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array{relationshipPart:string, sourcePart:?string, error:string}> $relationshipParseErrors
     * @return array{
     *     summary:array<string, mixed>,
     *     byKind:array<string, array{count:int, existingCount:int, missingCount:int, externalCount:int, relationshipCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}>,
     *     items:list<array<string, mixed>>
     * }
     */
    private function featureMetadataSummary(array $items, array $relationshipParseErrors): array
    {
        $byKind = [];
        $summary = [
            'readerPolicy' => 'xlsx-feature-metadata-only',
            'byteExposurePolicy' => 'xlsx-feature-part-bytes-blocked',
            'nonGoals' => [
                'chart-rendering',
                'pivot-computation',
                'formula-evaluation',
                'scripting',
            ],
            'relationshipParseErrorCount' => count($relationshipParseErrors),
            'relationshipParseErrors' => $relationshipParseErrors,
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => ($item['exists'] ?? false) === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-' . self::featureIssueStemStatic((string) $item['kind']) . '-part', $item['issues'] ?? [], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => ($item['external'] ?? false) === true)),
            'relationshipCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['relationshipCount'] ?? 0), $items)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => $this->featureIssueCodes($items),
            'countsByKind' => [],
            'existingCountsByKind' => [],
            'missingCountsByKind' => [],
            'externalCountsByKind' => [],
            'relationshipCountsByKind' => [],
            'issueCountsByKind' => [],
            'issueCodesByKind' => [],
        ];

        foreach (array_keys(self::FEATURE_SPECS) as $kind) {
            $kindItems = array_values(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === $kind));
            $issueCodes = $this->featureIssueCodes($kindItems);
            $byKind[$kind] = [
                'count' => count($kindItems),
                'existingCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['exists'] ?? false) === true)),
                'missingCount' => count(array_filter($kindItems, static fn (array $item): bool => in_array('missing-' . self::featureIssueStemStatic($kind) . '-part', $item['issues'] ?? [], true))),
                'externalCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['external'] ?? false) === true)),
                'relationshipCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['relationshipCount'] ?? 0), $kindItems)),
                'issueCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
                'issueCodes' => $issueCodes,
                'items' => $kindItems,
            ];
            $summary['countsByKind'][$kind] = $byKind[$kind]['count'];
            $summary['existingCountsByKind'][$kind] = $byKind[$kind]['existingCount'];
            $summary['missingCountsByKind'][$kind] = $byKind[$kind]['missingCount'];
            $summary['externalCountsByKind'][$kind] = $byKind[$kind]['externalCount'];
            $summary['relationshipCountsByKind'][$kind] = $byKind[$kind]['relationshipCount'];
            $summary['issueCountsByKind'][$kind] = $byKind[$kind]['issueCount'];
            $summary['issueCodesByKind'][$kind] = $issueCodes;
        }

        return [
            'summary' => $summary,
            'byKind' => $byKind,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function featureIssueCodes(array $items): array
    {
        $issueCodes = [];
        foreach ($items as $item) {
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && !in_array($issue, $issueCodes, true)) {
                    $issueCodes[] = $issue;
                }
            }
        }
        sort($issueCodes);

        return $issueCodes;
    }

    private function featureIssueStem(string $kind): string
    {
        return self::featureIssueStemStatic($kind);
    }

    private static function featureIssueStemStatic(string $kind): string
    {
        $stem = preg_replace('/(?<!^)[A-Z]/', '-$0', $kind);

        return strtolower(is_string($stem) ? $stem : $kind);
    }

    /**
     * @return array{targetQuery:?string, targetFragment:?string, targetSuffix:string}
     */
    private function targetSuffix(string $target): array
    {
        $offset = strcspn($target, '?#');
        $suffix = substr($target, $offset);
        $query = null;
        $fragment = null;
        if ($suffix !== '') {
            if ($suffix[0] === '?') {
                $fragmentOffset = strpos($suffix, '#');
                if ($fragmentOffset === false) {
                    $query = substr($suffix, 1);
                } else {
                    $query = substr($suffix, 1, $fragmentOffset - 1);
                    $fragment = substr($suffix, $fragmentOffset + 1);
                }
            } elseif ($suffix[0] === '#') {
                $fragment = substr($suffix, 1);
            }
        }

        return [
            'targetQuery' => $query,
            'targetFragment' => $fragment,
            'targetSuffix' => $suffix,
        ];
    }

    /**
     * @return array{
     *     date1904:bool,
     *     workbookProperties:array<string, bool|string|null>,
     *     calculationProperties:array<string, bool|int|string|null>,
     *     definedNameCount:int,
     *     definedNames:list<array<string, mixed>>,
     *     definedNameNames:list<string>,
     *     hiddenDefinedNameCount:int,
     *     printAreaDefinedNameCount:int,
     *     filterDatabaseDefinedNameCount:int,
     *     externalReferenceCount:int,
     *     sheets:list<array{index:int, name:string, relationshipId:string, state:string, hidden:bool, veryHidden:bool}>
     * }
     */
    private function parseWorkbook(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'workbook');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML must have a workbook root');
        }

        $sheetsElement = $this->firstChildElement($root, 'sheets');
        if (!$sheetsElement instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML is missing <sheets>');
        }

        $sheets = [];
        $index = 1;
        foreach ($this->childElements($sheetsElement, 'sheet') as $sheetElement) {
            $name = trim($sheetElement->getAttribute('name'));
            if ($name === '') {
                $name = 'Sheet' . $index;
            }
            $relationshipId = $this->relationshipId($sheetElement);
            if ($relationshipId === '') {
                throw new \RuntimeException('XLSX workbook sheet is missing r:id');
            }

            $state = match (strtolower(trim($sheetElement->getAttribute('state')))) {
                'hidden' => 'hidden',
                'veryhidden' => 'veryHidden',
                default => 'visible',
            };
            $sheets[] = [
                'index' => $index,
                'name' => $name,
                'relationshipId' => $relationshipId,
                'state' => $state,
                'hidden' => $state !== 'visible',
                'veryHidden' => $state === 'veryHidden',
            ];
            $index++;
        }

        $workbookProperties = $this->firstChildElement($root, 'workbookPr');
        $date1904 = $workbookProperties instanceof \DOMElement
            ? ($this->booleanAttribute($workbookProperties, 'date1904') ?? false)
            : false;
        $calculationProperties = $this->firstChildElement($root, 'calcPr');
        $definedNames = $this->firstChildElement($root, 'definedNames');
        $externalReferences = $this->firstChildElement($root, 'externalReferences');
        $definedNameMetadata = $this->parseDefinedNames($definedNames, $sheets);

        return [
            'date1904' => $date1904,
            'workbookProperties' => [
                'date1904' => $date1904,
                'filterPrivacy' => $workbookProperties instanceof \DOMElement ? $this->booleanAttribute($workbookProperties, 'filterPrivacy') : null,
                'backupFile' => $workbookProperties instanceof \DOMElement ? $this->booleanAttribute($workbookProperties, 'backupFile') : null,
                'showObjects' => $workbookProperties instanceof \DOMElement && $workbookProperties->hasAttribute('showObjects')
                    ? $workbookProperties->getAttribute('showObjects')
                    : null,
                'updateLinks' => $workbookProperties instanceof \DOMElement && $workbookProperties->hasAttribute('updateLinks')
                    ? $workbookProperties->getAttribute('updateLinks')
                    : null,
                'codeNamePresent' => $workbookProperties instanceof \DOMElement && trim($workbookProperties->getAttribute('codeName')) !== '',
            ],
            'calculationProperties' => [
                'present' => $calculationProperties instanceof \DOMElement,
                'calcId' => $calculationProperties instanceof \DOMElement ? $this->integerAttribute($calculationProperties, 'calcId') : null,
                'calcMode' => $calculationProperties instanceof \DOMElement && $calculationProperties->hasAttribute('calcMode')
                    ? $calculationProperties->getAttribute('calcMode')
                    : null,
                'fullCalcOnLoad' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'fullCalcOnLoad') : null,
                'forceFullCalc' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'forceFullCalc') : null,
                'iterate' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'iterate') : null,
            ],
            'definedNameCount' => count($definedNameMetadata),
            'definedNames' => $definedNameMetadata,
            'definedNameNames' => array_values(array_unique(array_map(
                static fn (array $definedName): string => is_string($definedName['name'] ?? null) ? $definedName['name'] : '',
                array_filter($definedNameMetadata, static fn (array $definedName): bool => is_string($definedName['name'] ?? null) && $definedName['name'] !== '')
            ))),
            'hiddenDefinedNameCount' => count(array_filter($definedNameMetadata, static fn (array $definedName): bool => ($definedName['hidden'] ?? null) === true)),
            'printAreaDefinedNameCount' => count(array_filter($definedNameMetadata, static fn (array $definedName): bool => ($definedName['nameClass'] ?? null) === 'printArea')),
            'filterDatabaseDefinedNameCount' => count(array_filter($definedNameMetadata, static fn (array $definedName): bool => ($definedName['nameClass'] ?? null) === 'filterDatabase')),
            'externalReferenceCount' => $externalReferences instanceof \DOMElement ? count($this->childElements($externalReferences, 'externalReference')) : 0,
            'sheets' => $sheets,
        ];
    }

    /**
     * @param list<array{index:int, name:string, relationshipId:string, state:string, hidden:bool, veryHidden:bool}> $sheets
     * @return list<array<string, mixed>>
     */
    private function parseDefinedNames(?\DOMElement $definedNames, array $sheets): array
    {
        if (!$definedNames instanceof \DOMElement) {
            return [];
        }

        $items = [];
        foreach ($this->childElements($definedNames, 'definedName') as $definedName) {
            $name = $this->nonEmptyAttribute($definedName, 'name');
            $localSheetId = $this->integerAttribute($definedName, 'localSheetId');
            $formula = trim($definedName->textContent);
            $references = $this->parseDefinedNameReferences($formula);
            $sheetName = $localSheetId !== null && isset($sheets[$localSheetId])
                ? $sheets[$localSheetId]['name']
                : null;

            $items[] = [
                'name' => $name,
                'nameClass' => $this->definedNameClass($name),
                'scope' => $localSheetId === null ? 'workbook' : 'sheet',
                'localSheetId' => $localSheetId,
                'sheetName' => $sheetName,
                'hidden' => $this->booleanAttribute($definedName, 'hidden'),
                'function' => $this->booleanAttribute($definedName, 'function'),
                'vbProcedure' => $this->booleanAttribute($definedName, 'vbProcedure'),
                'xlm' => $this->booleanAttribute($definedName, 'xlm'),
                'publishToServer' => $this->booleanAttribute($definedName, 'publishToServer'),
                'workbookParameter' => $this->booleanAttribute($definedName, 'workbookParameter'),
                'functionGroupId' => $this->integerAttribute($definedName, 'functionGroupId'),
                'shortcutKey' => $this->nonEmptyAttribute($definedName, 'shortcutKey'),
                'commentPresent' => $this->nonEmptyAttribute($definedName, 'comment') !== null,
                'customMenuPresent' => $this->nonEmptyAttribute($definedName, 'customMenu') !== null,
                'descriptionPresent' => $this->nonEmptyAttribute($definedName, 'description') !== null,
                'helpPresent' => $this->nonEmptyAttribute($definedName, 'help') !== null,
                'statusBarPresent' => $this->nonEmptyAttribute($definedName, 'statusBar') !== null,
                'formulaTextBytes' => strlen($formula),
                'formulaSha256' => hash('sha256', $formula),
                'referenceCount' => count($references),
                'references' => $references,
            ];
        }

        return $items;
    }

    private function definedNameClass(?string $name): string
    {
        $normalized = strtolower((string) $name);

        return match ($normalized) {
            '_xlnm.print_area' => 'printArea',
            '_xlnm.print_titles' => 'printTitles',
            '_xlnm._filterdatabase' => 'filterDatabase',
            default => str_starts_with($normalized, '_xlnm.') ? 'builtIn' : 'custom',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseDefinedNameReferences(string $formula): array
    {
        $references = [];
        foreach ($this->splitDefinedNameReferences($formula) as $referenceText) {
            $reference = $this->parseDefinedNameReference($referenceText);
            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function splitDefinedNameReferences(string $formula): array
    {
        $parts = [];
        $buffer = '';
        $inQuotedSheet = false;
        $length = strlen($formula);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $formula[$offset];
            if ($char === "'") {
                $buffer .= $char;
                if ($inQuotedSheet && $offset + 1 < $length && $formula[$offset + 1] === "'") {
                    $buffer .= "'";
                    $offset++;
                    continue;
                }
                $inQuotedSheet = !$inQuotedSheet;
                continue;
            }
            if ($char === ',' && !$inQuotedSheet) {
                $part = trim($buffer);
                if ($part !== '') {
                    $parts[] = $part;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $part = trim($buffer);
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseDefinedNameReference(string $referenceText): ?array
    {
        $referenceText = trim($referenceText);
        if ($referenceText === '') {
            return null;
        }

        $sheetName = null;
        $reference = $referenceText;
        if (preg_match("/^'((?:[^']|'')+)'!(.+)$/", $referenceText, $matches) === 1) {
            $sheetName = str_replace("''", "'", $matches[1]);
            $reference = trim($matches[2]);
        } elseif (preg_match('/^([^!]+)!(.+)$/', $referenceText, $matches) === 1) {
            $sheetName = trim($matches[1]);
            $reference = trim($matches[2]);
        }

        $normalizedReference = str_replace('$', '', $reference);
        $parsedRange = $this->parseCellRange($normalizedReference);
        $parsedCell = $parsedRange === null ? $this->parseCellReference($normalizedReference) : null;
        if ($parsedRange === null && $parsedCell === null) {
            return null;
        }

        return [
            'sheetName' => $sheetName,
            'reference' => $reference,
            'normalizedReference' => $normalizedReference,
            'referenceKind' => $parsedRange !== null ? 'range' : 'cell',
            'range' => $parsedRange,
            'cell' => $parsedCell,
        ];
    }

    /**
     * @return list<array{text:string, inlines:list<AstNode>}>
     */
    private function readSharedStrings(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'sharedStrings');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX shared strings');
        $root = XmlHtmlDom::rootElement($document, 'sst');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $strings = [];
        foreach ($this->childElements($root, 'si') as $stringElement) {
            $strings[] = $this->richTextValue($stringElement);
        }

        return $strings;
    }

    /**
     * @return array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     borders:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * }
     */
    private function readStyles(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $empty = [
            'fonts' => [],
            'fills' => [],
            'borders' => [],
            'cellStyleFormats' => [],
            'cellFormats' => [],
            'cellStyles' => [],
            'customNumberFormats' => [],
        ];
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'styles');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return $empty;
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX styles');
        $root = XmlHtmlDom::rootElement($document, 'styleSheet');
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        $customNumberFormats = [];
        $numFmts = $this->firstChildElement($root, 'numFmts');
        if ($numFmts instanceof \DOMElement) {
            foreach ($this->childElements($numFmts, 'numFmt') as $formatElement) {
                $id = trim($formatElement->getAttribute('numFmtId'));
                if (preg_match('/^\d+$/', $id) === 1) {
                    $customNumberFormats[(int) $id] = $formatElement->getAttribute('formatCode');
                }
            }
        }

        $fontsList = [];
        $fonts = $this->firstChildElement($root, 'fonts');
        if ($fonts instanceof \DOMElement) {
            foreach ($this->childElements($fonts, 'font') as $fontElement) {
                $fontsList[] = $this->parseStyleFont($fontElement);
            }
        }

        $fillsList = [];
        $fills = $this->firstChildElement($root, 'fills');
        if ($fills instanceof \DOMElement) {
            foreach ($this->childElements($fills, 'fill') as $fillElement) {
                $fillsList[] = $this->parseStyleFill($fillElement);
            }
        }

        $bordersList = [];
        $borders = $this->firstChildElement($root, 'borders');
        if ($borders instanceof \DOMElement) {
            foreach ($this->childElements($borders, 'border') as $borderElement) {
                $bordersList[] = $this->parseStyleBorder($borderElement);
            }
        }

        $cellStyleFormats = [];
        $cellStyleXfs = $this->firstChildElement($root, 'cellStyleXfs');
        if ($cellStyleXfs instanceof \DOMElement) {
            foreach ($this->childElements($cellStyleXfs, 'xf') as $xfElement) {
                $cellStyleFormats[] = $this->parseStyleXf($xfElement, $fontsList, $fillsList, $bordersList, $customNumberFormats, null);
            }
        }

        $cellStyles = $this->parseCellStyles($root);
        $cellStylesByXfId = [];
        foreach ($cellStyles as $cellStyle) {
            $xfId = $cellStyle['xfId'] ?? null;
            if (is_int($xfId) && !array_key_exists($xfId, $cellStylesByXfId)) {
                $cellStylesByXfId[$xfId] = $cellStyle;
            }
        }

        $cellFormats = [];
        $cellXfs = $this->firstChildElement($root, 'cellXfs');
        if ($cellXfs instanceof \DOMElement) {
            foreach ($this->childElements($cellXfs, 'xf') as $xfElement) {
                $xfId = $this->integerAttribute($xfElement, 'xfId');
                $baseStyle = $xfId !== null && array_key_exists($xfId, $cellStyleFormats)
                    ? $cellStyleFormats[$xfId]
                    : null;
                $style = $this->parseStyleXf($xfElement, $fontsList, $fillsList, $bordersList, $customNumberFormats, $baseStyle);
                if ($xfId !== null && array_key_exists($xfId, $cellStylesByXfId)) {
                    $namedStyle = $cellStylesByXfId[$xfId];
                    $style['cellStyleName'] = $namedStyle['name'] ?? null;
                    $style['cellStyleBuiltinId'] = $namedStyle['builtinId'] ?? null;
                    $style['cellStyleCustomBuiltin'] = $namedStyle['customBuiltin'] ?? null;
                }
                $cellFormats[] = $style;
            }
        }

        return [
            'fonts' => $fontsList,
            'fills' => $fillsList,
            'borders' => $bordersList,
            'cellStyleFormats' => $cellStyleFormats,
            'cellFormats' => $cellFormats,
            'cellStyles' => $cellStyles,
            'customNumberFormats' => $customNumberFormats,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fontsList
     * @param list<array<string, mixed>> $fillsList
     * @param list<array<string, mixed>> $bordersList
     * @param array<int, string> $customNumberFormats
     * @param array<string, mixed>|null $baseStyle
     * @return array<string, mixed>
     */
    private function parseStyleXf(\DOMElement $xfElement, array $fontsList, array $fillsList, array $bordersList, array $customNumberFormats, ?array $baseStyle): array
    {
        $style = $baseStyle ?? $this->defaultCellStyle();
        $xfId = $this->integerAttribute($xfElement, 'xfId');
        if ($xfId !== null) {
            $style['xfId'] = $xfId;
        }

        foreach ([
            'applyNumberFormat',
            'applyFont',
            'applyFill',
            'applyBorder',
            'applyAlignment',
            'applyProtection',
            'quotePrefix',
            'pivotButton',
        ] as $attribute) {
            $value = $this->booleanAttribute($xfElement, $attribute);
            if ($value !== null) {
                $style[$attribute] = $value;
            }
        }

        $fontId = $this->integerAttribute($xfElement, 'fontId');
        if ($fontId !== null) {
            $font = $fontsList[$fontId] ?? $this->defaultStyleFont();
            $style['fontId'] = $fontId;
            $style['bold'] = $font['bold'];
            $style['italic'] = $font['italic'];
            $style['underline'] = $font['underline'];
            $style['strike'] = $font['strike'];
            $style['fontName'] = $font['name'];
            $style['fontSize'] = $font['size'];
            $style['fontColor'] = $font['color'];
            $style['fontColorMetadata'] = $font['colorMetadata'];
            $style['fontFamily'] = $font['family'];
            $style['fontCharset'] = $font['charset'];
            $style['fontScheme'] = $font['scheme'];
            $style['fontUnderlineStyle'] = $font['underlineStyle'];
            $style['fontVerticalAlign'] = $font['verticalAlign'];
        }

        $fillId = $this->integerAttribute($xfElement, 'fillId');
        if ($fillId !== null) {
            $fill = $fillsList[$fillId] ?? $this->defaultStyleFill();
            $style['fillId'] = $fillId;
            $style['fillPatternType'] = $fill['patternType'];
            $style['fillForegroundColor'] = $fill['foregroundColor'];
            $style['fillBackgroundColor'] = $fill['backgroundColor'];
            $style['fillForegroundColorMetadata'] = $fill['foregroundColorMetadata'];
            $style['fillBackgroundColorMetadata'] = $fill['backgroundColorMetadata'];
            $style['fillGradientType'] = $fill['gradientType'];
            $style['fillGradientDegree'] = $fill['gradientDegree'];
            $style['fillGradientEdges'] = $fill['gradientEdges'];
            $style['fillGradientStops'] = $fill['gradientStops'];
            $style['fillGradientStopCount'] = $fill['gradientStopCount'];
        }

        $borderId = $this->integerAttribute($xfElement, 'borderId');
        if ($borderId !== null) {
            $border = $bordersList[$borderId] ?? $this->defaultStyleBorder();
            $style['borderId'] = $borderId;
            $style['borderLeftStyle'] = $border['leftStyle'];
            $style['borderLeftColor'] = $border['leftColor'];
            $style['borderRightStyle'] = $border['rightStyle'];
            $style['borderRightColor'] = $border['rightColor'];
            $style['borderTopStyle'] = $border['topStyle'];
            $style['borderTopColor'] = $border['topColor'];
            $style['borderBottomStyle'] = $border['bottomStyle'];
            $style['borderBottomColor'] = $border['bottomColor'];
            $style['borderDiagonalStyle'] = $border['diagonalStyle'];
            $style['borderDiagonalColor'] = $border['diagonalColor'];
            $style['borderLeftColorMetadata'] = $border['leftColorMetadata'];
            $style['borderRightColorMetadata'] = $border['rightColorMetadata'];
            $style['borderTopColorMetadata'] = $border['topColorMetadata'];
            $style['borderBottomColorMetadata'] = $border['bottomColorMetadata'];
            $style['borderDiagonalColorMetadata'] = $border['diagonalColorMetadata'];
            $style['borderDiagonalUp'] = $border['diagonalUp'];
            $style['borderDiagonalDown'] = $border['diagonalDown'];
            $style['borderOutline'] = $border['outline'];
        }

        $numFmtId = $this->integerAttribute($xfElement, 'numFmtId');
        if ($numFmtId !== null) {
            $style['numFmtId'] = $numFmtId;
            $style['formatCode'] = $customNumberFormats[$numFmtId] ?? $this->builtInNumberFormat($numFmtId);
        }

        $alignment = $this->firstChildElement($xfElement, 'alignment');
        if ($alignment instanceof \DOMElement) {
            $alignmentTextAttributes = [
                'horizontal' => 'horizontalAlign',
                'vertical' => 'verticalAlign',
            ];
            foreach ($alignmentTextAttributes as $source => $target) {
                $value = trim($alignment->getAttribute($source));
                if ($value !== '') {
                    $style[$target] = $value;
                }
            }

            foreach ([
                'wrapText',
                'shrinkToFit',
                'justifyLastLine',
            ] as $attribute) {
                $value = $this->booleanAttribute($alignment, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }

            foreach ([
                'textRotation',
                'indent',
                'relativeIndent',
                'readingOrder',
            ] as $attribute) {
                $value = $this->integerAttribute($alignment, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }
        }

        $protection = $this->firstChildElement($xfElement, 'protection');
        if ($protection instanceof \DOMElement) {
            foreach (['locked', 'hidden'] as $attribute) {
                $value = $this->booleanAttribute($protection, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }
        }

        return $style;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCellStyles(\DOMElement $root): array
    {
        $cellStylesElement = $this->firstChildElement($root, 'cellStyles');
        if (!$cellStylesElement instanceof \DOMElement) {
            return [];
        }

        $cellStyles = [];
        foreach ($this->childElements($cellStylesElement, 'cellStyle') as $cellStyleElement) {
            $name = trim($cellStyleElement->getAttribute('name'));
            $cellStyles[] = [
                'name' => $name !== '' ? $name : null,
                'xfId' => $this->integerAttribute($cellStyleElement, 'xfId'),
                'builtinId' => $this->integerAttribute($cellStyleElement, 'builtinId'),
                'customBuiltin' => $this->booleanAttribute($cellStyleElement, 'customBuiltin'),
                'hidden' => $this->booleanAttribute($cellStyleElement, 'hidden'),
                'iLevel' => $this->integerAttribute($cellStyleElement, 'iLevel'),
            ];
        }

        return $cellStyles;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleFont(\DOMElement $fontElement): array
    {
        $name = $this->firstChildElement($fontElement, 'name');
        $size = $this->firstChildElement($fontElement, 'sz');
        $color = $this->firstChildElement($fontElement, 'color');
        $underline = $this->firstChildElement($fontElement, 'u');
        $family = $this->firstChildElement($fontElement, 'family');
        $charset = $this->firstChildElement($fontElement, 'charset');
        $scheme = $this->firstChildElement($fontElement, 'scheme');
        $verticalAlign = $this->firstChildElement($fontElement, 'vertAlign');
        $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];
        $underlineStyle = null;
        if ($underline instanceof \DOMElement) {
            $underlineValue = strtolower(trim($underline->getAttribute('val')));
            $underlineStyle = $underlineValue !== '' ? $underlineValue : 'single';
        }

        return [
            'bold' => $this->firstChildElement($fontElement, 'b') instanceof \DOMElement,
            'italic' => $this->firstChildElement($fontElement, 'i') instanceof \DOMElement,
            'underline' => $underline instanceof \DOMElement && strtolower(trim($underline->getAttribute('val'))) !== 'none',
            'strike' => $this->firstChildElement($fontElement, 'strike') instanceof \DOMElement,
            'name' => $name instanceof \DOMElement && trim($name->getAttribute('val')) !== '' ? trim($name->getAttribute('val')) : null,
            'size' => $size instanceof \DOMElement && is_numeric(trim($size->getAttribute('val'))) ? (float) trim($size->getAttribute('val')) : null,
            'color' => $colorMetadata['token'] ?? null,
            'colorMetadata' => $colorMetadata,
            'family' => $family instanceof \DOMElement ? $this->integerAttribute($family, 'val') : null,
            'charset' => $charset instanceof \DOMElement ? $this->integerAttribute($charset, 'val') : null,
            'scheme' => $scheme instanceof \DOMElement && trim($scheme->getAttribute('val')) !== '' ? trim($scheme->getAttribute('val')) : null,
            'underlineStyle' => $underlineStyle,
            'verticalAlign' => $verticalAlign instanceof \DOMElement && trim($verticalAlign->getAttribute('val')) !== '' ? trim($verticalAlign->getAttribute('val')) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleFont(): array
    {
        return [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'name' => null,
            'size' => null,
            'color' => null,
            'colorMetadata' => [],
            'family' => null,
            'charset' => null,
            'scheme' => null,
            'underlineStyle' => null,
            'verticalAlign' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleFill(\DOMElement $fillElement): array
    {
        $patternFill = $this->firstChildElement($fillElement, 'patternFill');
        if ($patternFill instanceof \DOMElement) {
            $foreground = $this->firstChildElement($patternFill, 'fgColor');
            $background = $this->firstChildElement($patternFill, 'bgColor');
            $foregroundMetadata = $foreground instanceof \DOMElement ? $this->styleColorMetadata($foreground) : [];
            $backgroundMetadata = $background instanceof \DOMElement ? $this->styleColorMetadata($background) : [];

            return array_replace($this->defaultStyleFill(), [
                'patternType' => trim($patternFill->getAttribute('patternType')) !== '' ? trim($patternFill->getAttribute('patternType')) : null,
                'foregroundColor' => $foregroundMetadata['token'] ?? null,
                'backgroundColor' => $backgroundMetadata['token'] ?? null,
                'foregroundColorMetadata' => $foregroundMetadata,
                'backgroundColorMetadata' => $backgroundMetadata,
            ]);
        }

        $gradientFill = $this->firstChildElement($fillElement, 'gradientFill');
        if ($gradientFill instanceof \DOMElement) {
            return array_replace($this->defaultStyleFill(), $this->parseStyleGradientFill($gradientFill));
        }

        return $this->defaultStyleFill();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleGradientFill(\DOMElement $gradientFill): array
    {
        $edges = [];
        foreach (['left', 'right', 'top', 'bottom'] as $edge) {
            $value = $this->numericAttribute($gradientFill, $edge);
            if ($value !== null) {
                $edges[$edge] = $value;
            }
        }

        $stops = [];
        foreach ($this->childElements($gradientFill, 'stop') as $stop) {
            $color = $this->firstChildElement($stop, 'color');
            $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];
            $stops[] = [
                'position' => $this->numericAttribute($stop, 'position'),
                'color' => $colorMetadata['token'] ?? null,
                'colorMetadata' => $colorMetadata,
            ];
        }

        return [
            'gradientFill' => true,
            'gradientType' => $this->nonEmptyAttribute($gradientFill, 'type'),
            'gradientDegree' => $this->numericAttribute($gradientFill, 'degree'),
            'gradientEdges' => $edges,
            'gradientStops' => $stops,
            'gradientStopCount' => count($stops),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleFill(): array
    {
        return [
            'patternType' => null,
            'foregroundColor' => null,
            'backgroundColor' => null,
            'foregroundColorMetadata' => [],
            'backgroundColorMetadata' => [],
            'gradientFill' => false,
            'gradientType' => null,
            'gradientDegree' => null,
            'gradientEdges' => null,
            'gradientStops' => null,
            'gradientStopCount' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleBorder(\DOMElement $borderElement): array
    {
        $left = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'left'));
        $right = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'right'));
        $top = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'top'));
        $bottom = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'bottom'));
        $diagonal = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'diagonal'));

        return [
            'leftStyle' => $left['style'],
            'leftColor' => $left['color'],
            'rightStyle' => $right['style'],
            'rightColor' => $right['color'],
            'topStyle' => $top['style'],
            'topColor' => $top['color'],
            'bottomStyle' => $bottom['style'],
            'bottomColor' => $bottom['color'],
            'diagonalStyle' => $diagonal['style'],
            'diagonalColor' => $diagonal['color'],
            'leftColorMetadata' => $left['colorMetadata'],
            'rightColorMetadata' => $right['colorMetadata'],
            'topColorMetadata' => $top['colorMetadata'],
            'bottomColorMetadata' => $bottom['colorMetadata'],
            'diagonalColorMetadata' => $diagonal['colorMetadata'],
            'diagonalUp' => $this->booleanAttribute($borderElement, 'diagonalUp'),
            'diagonalDown' => $this->booleanAttribute($borderElement, 'diagonalDown'),
            'outline' => $this->booleanAttribute($borderElement, 'outline'),
        ];
    }

    /**
     * @return array{style:?string, color:?string, colorMetadata:array<string, mixed>}
     */
    private function parseStyleBorderSide(?\DOMElement $sideElement): array
    {
        if (!$sideElement instanceof \DOMElement) {
            return [
                'style' => null,
                'color' => null,
                'colorMetadata' => [],
            ];
        }

        $color = $this->firstChildElement($sideElement, 'color');
        $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];

        return [
            'style' => trim($sideElement->getAttribute('style')) !== '' ? trim($sideElement->getAttribute('style')) : null,
            'color' => $colorMetadata['token'] ?? null,
            'colorMetadata' => $colorMetadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleBorder(): array
    {
        return [
            'leftStyle' => null,
            'leftColor' => null,
            'rightStyle' => null,
            'rightColor' => null,
            'topStyle' => null,
            'topColor' => null,
            'bottomStyle' => null,
            'bottomColor' => null,
            'diagonalStyle' => null,
            'diagonalColor' => null,
            'leftColorMetadata' => [],
            'rightColorMetadata' => [],
            'topColorMetadata' => [],
            'bottomColorMetadata' => [],
            'diagonalColorMetadata' => [],
            'diagonalUp' => null,
            'diagonalDown' => null,
            'outline' => null,
        ];
    }

    private function styleColorValue(\DOMElement $colorElement): ?string
    {
        $metadata = $this->styleColorMetadata($colorElement);

        return is_string($metadata['token'] ?? null) ? $metadata['token'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function styleColorMetadata(\DOMElement $colorElement): array
    {
        foreach (['rgb', 'indexed', 'theme', 'auto'] as $attribute) {
            $value = trim($colorElement->getAttribute($attribute));
            if ($value !== '') {
                $metadata = [
                    'source' => $attribute,
                    'value' => $value,
                    'token' => $attribute . ':' . $value,
                ];
                if ($attribute === 'rgb') {
                    $argb = strtoupper($value);
                    if (preg_match('/^[0-9A-F]{8}$/', $argb) === 1) {
                        $metadata['argb'] = $argb;
                        $metadata['alpha'] = substr($argb, 0, 2);
                        $metadata['rgb'] = substr($argb, 2);
                    } elseif (preg_match('/^[0-9A-F]{6}$/', $argb) === 1) {
                        $metadata['rgb'] = $argb;
                    }
                } elseif (in_array($attribute, ['indexed', 'theme'], true) && preg_match('/^-?\d+$/', $value) === 1) {
                    $metadata[$attribute] = (int) $value;
                } elseif ($attribute === 'auto') {
                    $metadata['auto'] = in_array(strtolower($value), ['1', 'true', 'on'], true);
                }

                $tint = trim($colorElement->getAttribute('tint'));
                if ($tint !== '' && is_numeric($tint)) {
                    $metadata['tint'] = (float) $tint;
                }

                return $metadata;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCellStyle(): array
    {
        return [
            'fontId' => null,
            'fillId' => null,
            'borderId' => null,
            'xfId' => null,
            'cellStyleName' => null,
            'cellStyleBuiltinId' => null,
            'cellStyleCustomBuiltin' => null,
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'fontName' => null,
            'fontSize' => null,
            'fontColor' => null,
            'fontColorMetadata' => [],
            'fontFamily' => null,
            'fontCharset' => null,
            'fontScheme' => null,
            'fontUnderlineStyle' => null,
            'fontVerticalAlign' => null,
            'fillPatternType' => null,
            'fillForegroundColor' => null,
            'fillBackgroundColor' => null,
            'fillForegroundColorMetadata' => [],
            'fillBackgroundColorMetadata' => [],
            'fillGradientType' => null,
            'fillGradientDegree' => null,
            'fillGradientEdges' => null,
            'fillGradientStops' => null,
            'fillGradientStopCount' => null,
            'numFmtId' => null,
            'formatCode' => null,
            'horizontalAlign' => null,
            'verticalAlign' => null,
            'wrapText' => null,
            'textRotation' => null,
            'indent' => null,
            'relativeIndent' => null,
            'shrinkToFit' => null,
            'readingOrder' => null,
            'justifyLastLine' => null,
            'locked' => null,
            'hidden' => null,
            'applyNumberFormat' => null,
            'applyFont' => null,
            'applyFill' => null,
            'applyBorder' => null,
            'applyAlignment' => null,
            'applyProtection' => null,
            'quotePrefix' => null,
            'pivotButton' => null,
            'borderLeftStyle' => null,
            'borderLeftColor' => null,
            'borderRightStyle' => null,
            'borderRightColor' => null,
            'borderTopStyle' => null,
            'borderTopColor' => null,
            'borderBottomStyle' => null,
            'borderBottomColor' => null,
            'borderDiagonalStyle' => null,
            'borderDiagonalColor' => null,
            'borderLeftColorMetadata' => [],
            'borderRightColorMetadata' => [],
            'borderTopColorMetadata' => [],
            'borderBottomColorMetadata' => [],
            'borderDiagonalColorMetadata' => [],
            'borderDiagonalUp' => null,
            'borderDiagonalDown' => null,
            'borderOutline' => null,
        ];
    }

    private function firstRelationshipWithTarget(OpcRelationships $relationships, string $needle): ?OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_contains($relationship->target, $needle)) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     columnRanges:list<array<string, mixed>>,
     *     columnsByIndex:array<int, array<string, mixed>>,
     *     rows:list<array<string, mixed>>,
     *     rowsByIndex:array<int, array<string, mixed>>,
     *     hiddenColumnCount:int,
     *     hiddenRowCount:int,
     *     customColumnWidthCount:int,
     *     customRowHeightCount:int,
     *     sheetViews:list<array<string, mixed>>,
     *     sheetViewCount:int,
     *     frozenPaneCount:int,
     *     splitPaneCount:int
     * }
     */
    private function parseSheetLayoutMetadata(\DOMDocument $document): array
    {
        $empty = [
            'columnRanges' => [],
            'columnsByIndex' => [],
            'rows' => [],
            'rowsByIndex' => [],
            'hiddenColumnCount' => 0,
            'hiddenRowCount' => 0,
            'customColumnWidthCount' => 0,
            'customRowHeightCount' => 0,
            'sheetViews' => [],
            'sheetViewCount' => 0,
            'frozenPaneCount' => 0,
            'splitPaneCount' => 0,
        ];

        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        $columnRanges = [];
        $columnsByIndex = [];
        $hiddenColumnCount = 0;
        $customColumnWidthCount = 0;
        $cols = $this->firstChildElement($root, 'cols');
        if ($cols instanceof \DOMElement) {
            foreach ($this->childElements($cols, 'col') as $columnElement) {
                $min = $this->integerAttribute($columnElement, 'min');
                $max = $this->integerAttribute($columnElement, 'max');
                if ($min === null || $max === null || $min < 1 || $max < $min) {
                    continue;
                }

                $record = [
                    'min' => $min,
                    'max' => $max,
                    'range' => $this->columnNameFromIndex($min) . ($max === $min ? '' : ':' . $this->columnNameFromIndex($max)),
                    'width' => $this->numericAttribute($columnElement, 'width'),
                    'styleIndex' => $this->integerAttribute($columnElement, 'style'),
                    'hidden' => $this->booleanAttribute($columnElement, 'hidden'),
                    'customWidth' => $this->booleanAttribute($columnElement, 'customWidth'),
                    'bestFit' => $this->booleanAttribute($columnElement, 'bestFit'),
                    'outlineLevel' => $this->integerAttribute($columnElement, 'outlineLevel'),
                    'collapsed' => $this->booleanAttribute($columnElement, 'collapsed'),
                ];
                $columnRanges[] = $record;

                $span = $max - $min + 1;
                if (($record['hidden'] ?? false) === true) {
                    $hiddenColumnCount += $span;
                }
                if (($record['customWidth'] ?? false) === true) {
                    $customColumnWidthCount += $span;
                }
                if ($span <= 16384) {
                    for ($column = $min; $column <= $max; $column++) {
                        $columnsByIndex[$column] = $record;
                    }
                }
            }
        }

        $rows = [];
        $rowsByIndex = [];
        $hiddenRowCount = 0;
        $customRowHeightCount = 0;
        $sheetData = $this->firstChildElement($root, 'sheetData');
        if ($sheetData instanceof \DOMElement) {
            foreach ($this->childElements($sheetData, 'row') as $rowElement) {
                $rowNumber = $this->integerAttribute($rowElement, 'r');
                if ($rowNumber === null || $rowNumber < 1) {
                    continue;
                }

                $record = [
                    'row' => $rowNumber,
                    'height' => $this->numericAttribute($rowElement, 'ht'),
                    'styleIndex' => $this->integerAttribute($rowElement, 's'),
                    'hidden' => $this->booleanAttribute($rowElement, 'hidden'),
                    'customHeight' => $this->booleanAttribute($rowElement, 'customHeight'),
                    'outlineLevel' => $this->integerAttribute($rowElement, 'outlineLevel'),
                    'collapsed' => $this->booleanAttribute($rowElement, 'collapsed'),
                ];
                if (
                    $record['height'] === null
                    && $record['styleIndex'] === null
                    && $record['hidden'] === null
                    && $record['customHeight'] === null
                    && $record['outlineLevel'] === null
                    && $record['collapsed'] === null
                ) {
                    continue;
                }
                $rows[] = $record;
                $rowsByIndex[$rowNumber] = $record;

                if (($record['hidden'] ?? false) === true) {
                    $hiddenRowCount++;
                }
                if (($record['customHeight'] ?? false) === true) {
                    $customRowHeightCount++;
                }
            }
        }

        $sheetViews = $this->parseSheetViews($root);
        $frozenPaneCount = count(array_filter($sheetViews, static fn (array $view): bool => ($view['hasFrozenPane'] ?? false) === true));
        $splitPaneCount = count(array_filter($sheetViews, static fn (array $view): bool => ($view['hasSplitPane'] ?? false) === true));

        return [
            'columnRanges' => $columnRanges,
            'columnsByIndex' => $columnsByIndex,
            'rows' => $rows,
            'rowsByIndex' => $rowsByIndex,
            'hiddenColumnCount' => $hiddenColumnCount,
            'hiddenRowCount' => $hiddenRowCount,
            'customColumnWidthCount' => $customColumnWidthCount,
            'customRowHeightCount' => $customRowHeightCount,
            'sheetViews' => $sheetViews,
            'sheetViewCount' => count($sheetViews),
            'frozenPaneCount' => $frozenPaneCount,
            'splitPaneCount' => $splitPaneCount,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSheetViews(\DOMElement $worksheet): array
    {
        $sheetViewsElement = $this->firstChildElement($worksheet, 'sheetViews');
        if (!$sheetViewsElement instanceof \DOMElement) {
            return [];
        }

        $views = [];
        foreach ($this->childElements($sheetViewsElement, 'sheetView') as $sheetView) {
            $pane = $this->parseSheetViewPane($this->firstChildElement($sheetView, 'pane'));
            $selections = [];
            foreach ($this->childElements($sheetView, 'selection') as $selection) {
                $selections[] = [
                    'pane' => $this->nonEmptyAttribute($selection, 'pane'),
                    'activeCell' => $this->nonEmptyAttribute($selection, 'activeCell'),
                    'activeCellId' => $this->integerAttribute($selection, 'activeCellId'),
                    'sqref' => $this->nonEmptyAttribute($selection, 'sqref'),
                ];
            }

            $views[] = [
                'workbookViewId' => $this->integerAttribute($sheetView, 'workbookViewId'),
                'view' => $this->nonEmptyAttribute($sheetView, 'view'),
                'topLeftCell' => $this->nonEmptyAttribute($sheetView, 'topLeftCell'),
                'tabSelected' => $this->booleanAttribute($sheetView, 'tabSelected'),
                'showGridLines' => $this->booleanAttribute($sheetView, 'showGridLines'),
                'showRowColHeaders' => $this->booleanAttribute($sheetView, 'showRowColHeaders'),
                'showZeros' => $this->booleanAttribute($sheetView, 'showZeros'),
                'rightToLeft' => $this->booleanAttribute($sheetView, 'rightToLeft'),
                'zoomScale' => $this->integerAttribute($sheetView, 'zoomScale'),
                'zoomScaleNormal' => $this->integerAttribute($sheetView, 'zoomScaleNormal'),
                'zoomScalePageLayoutView' => $this->integerAttribute($sheetView, 'zoomScalePageLayoutView'),
                'zoomScaleSheetLayoutView' => $this->integerAttribute($sheetView, 'zoomScaleSheetLayoutView'),
                'pane' => $pane,
                'hasFrozenPane' => is_array($pane) && (($pane['state'] ?? null) === 'frozen' || ($pane['state'] ?? null) === 'frozenSplit'),
                'hasSplitPane' => is_array($pane) && (($pane['state'] ?? null) === 'split' || ($pane['state'] ?? null) === 'frozenSplit'),
                'selectionCount' => count($selections),
                'selections' => $selections,
            ];
        }

        return $views;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSheetViewPane(?\DOMElement $pane): ?array
    {
        if (!$pane instanceof \DOMElement) {
            return null;
        }

        return [
            'xSplit' => $this->numericAttribute($pane, 'xSplit'),
            'ySplit' => $this->numericAttribute($pane, 'ySplit'),
            'topLeftCell' => $this->nonEmptyAttribute($pane, 'topLeftCell'),
            'activePane' => $this->nonEmptyAttribute($pane, 'activePane'),
            'state' => $this->nonEmptyAttribute($pane, 'state'),
        ];
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     borders:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @param array<string, mixed> $sheetLayout
     * @return array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }>
     */
    private function parseSheetCells(\DOMDocument $document, array $sharedStrings, array $styles, bool $date1904, OpcRelationships $relationships, array $commentsByCell, array $sheetLayout, string $sheetName = '', array $sheetAnchorIds = []): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX worksheet XML must have a worksheet root');
        }

        $hyperlinks = $this->parseHyperlinks($root, $relationships, $sheetName, $sheetAnchorIds);
        $mergeRegions = $this->parseMergeRegions($root);
        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [];
        }

        $cells = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $cell = $this->parseCell($cellElement, $sharedStrings, $styles, $date1904, $hyperlinks, $commentsByCell);
                if ($cell !== null) {
                    $row = $cell['row'];
                    $column = $cell['column'];
                    $rowMetadata = is_array($sheetLayout['rowsByIndex'][$row] ?? null) ? $sheetLayout['rowsByIndex'][$row] : null;
                    $columnMetadata = is_array($sheetLayout['columnsByIndex'][$column] ?? null) ? $sheetLayout['columnsByIndex'][$column] : null;
                    $cell['rowMetadata'] = $rowMetadata;
                    $cell['columnMetadata'] = $columnMetadata;
                    $cells[$cell['row'] . ':' . $cell['column']] = $cell;
                }
            }
        }

        $this->applyMergeRegions($cells, $mergeRegions);

        return $cells;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @param array<string, array{url:string, title:string}> $hyperlinks
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @return array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }|null
     */
    private function parseCell(\DOMElement $cellElement, array $sharedStrings, array $styles, bool $date1904, array $hyperlinks, array $commentsByCell): ?array
    {
        $ref = trim($cellElement->getAttribute('r'));
        if ($ref === '') {
            return null;
        }
        $cellRef = $this->parseCellReference($ref);
        if ($cellRef === null) {
            return null;
        }

        $cellType = trim($cellElement->getAttribute('t'));
        $styleIndex = trim($cellElement->getAttribute('s'));
        $styleIndex = preg_match('/^\d+$/', $styleIndex) === 1 ? (int) $styleIndex : null;
        $style = $this->styleForIndex($styleIndex, $styles);
        $valueElement = $this->firstChildElement($cellElement, 'v');
        $rawValue = $valueElement instanceof \DOMElement ? $valueElement->textContent : '';
        $formula = $this->formulaMetadataForCell($cellElement);
        $empty = false;
        $valueType = 'text';
        $inlines = [];
        $numericValue = null;
        $numberFormatSection = null;
        $numberFormatKind = null;

        if ($cellType === 's') {
            if (preg_match('/^-?\d+$/', trim($rawValue)) === 1) {
                $index = (int) trim($rawValue);
                if ($index >= 0 && array_key_exists($index, $sharedStrings)) {
                    $sharedString = $sharedStrings[$index];
                    if (is_array($sharedString)) {
                        $text = (string) ($sharedString['text'] ?? '');
                        $sharedStringInlines = $sharedString['inlines'] ?? [];
                        $inlines = is_array($sharedStringInlines) ? $sharedStringInlines : [];
                    } else {
                        $text = (string) $sharedString;
                    }
                } else {
                    $text = '';
                    $empty = true;
                    $valueType = 'empty';
                }
            } else {
                $text = '';
                $empty = true;
                $valueType = 'empty';
            }
        } elseif ($cellType === 'inlineStr') {
            $inlineString = $this->firstChildElement($cellElement, 'is');
            $value = $inlineString instanceof \DOMElement ? $this->richTextValue($inlineString) : $this->emptyRichTextValue();
            $text = $value['text'];
            $inlines = $value['inlines'];
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'text';
        } elseif ($cellType === 'b') {
            $text = $this->formatBooleanCellValue($rawValue);
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'boolean';
        } elseif ($cellType === 'e') {
            $text = trim($rawValue);
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'error';
        } elseif ($cellType === 'str') {
            $text = $rawValue;
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'formula-string';
        } elseif (trim($rawValue) === '') {
            $text = '';
            $empty = true;
            $valueType = 'empty';
        } elseif (is_numeric(trim($rawValue))) {
            $number = (float) trim($rawValue);
            $numericValue = $number;
            $numberFormatSection = $this->selectedNumberFormatSectionForStyle($style, $number);
            if ($this->isDateStyle($style)) {
                $text = $this->formatDateSerial($number, $style, $date1904);
                $valueType = 'date';
            } else {
                $text = $this->formatNumberForStyle($number, $style);
                $valueType = 'number';
            }
            $numberFormatKind = $this->numberFormatKindForStyle($style, $numberFormatSection);
        } else {
            $text = $rawValue;
        }

        $hyperlink = $hyperlinks[$ref] ?? ['url' => '', 'title' => ''];
        $comments = $commentsByCell[$ref] ?? [];

        return [
            'row' => $cellRef['row'],
            'column' => $cellRef['column'],
            'ref' => $ref,
            'styleIndex' => $styleIndex,
            'valueType' => $valueType,
            'rawValue' => $numericValue === null ? null : trim($rawValue),
            'numericValue' => $numericValue,
            'numberFormatSection' => $numberFormatSection,
            'numberFormatKind' => $numberFormatKind,
            'text' => $text,
            'inlines' => $inlines,
            'bold' => $style['bold'],
            'italic' => $style['italic'],
            'underline' => $style['underline'],
            'strike' => $style['strike'],
            'style' => $style,
            'formula' => $formula,
            'empty' => $empty,
            'url' => $hyperlink['url'],
            'title' => $hyperlink['title'],
            'comments' => $comments,
            'colspan' => 1,
            'rowspan' => 1,
            'covered' => false,
        ];
    }

    /**
     * @return array{text:string, inlines:list<AstNode>}
     */
    private function richTextValue(\DOMElement $stringElement): array
    {
        $runs = $this->childElements($stringElement, 'r');
        if ($runs === []) {
            $directText = $this->firstChildElement($stringElement, 't');
            $text = $directText instanceof \DOMElement ? $directText->textContent : $this->allDescendantText($stringElement);

            return [
                'text' => $text,
                'inlines' => $this->textInlines($text),
            ];
        }

        $inlines = [];
        foreach ($runs as $runElement) {
            $text = $this->richTextRunText($runElement);
            if ($text === '') {
                continue;
            }

            array_push($inlines, ...$this->applyInlineStyle(
                $this->textInlines($text),
                $this->richTextRunStyle($runElement)
            ));
        }

        return [
            'text' => $this->plainTextFromInlines($inlines),
            'inlines' => $inlines,
        ];
    }

    /**
     * @return array{text:string, inlines:list<AstNode>}
     */
    private function emptyRichTextValue(): array
    {
        return [
            'text' => '',
            'inlines' => [],
        ];
    }

    private function richTextRunText(\DOMElement $runElement): string
    {
        $texts = [];
        foreach ($this->childElements($runElement, 't') as $textElement) {
            $texts[] = $textElement->textContent;
        }

        return implode('', $texts);
    }

    /**
     * @return array{bold:bool, italic:bool, underline:bool, strike:bool}
     */
    private function richTextRunStyle(\DOMElement $runElement): array
    {
        $properties = $this->firstChildElement($runElement, 'rPr');
        if (!$properties instanceof \DOMElement) {
            return [
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'strike' => false,
            ];
        }

        $underline = $this->firstChildElement($properties, 'u');

        return [
            'bold' => $this->firstChildElement($properties, 'b') instanceof \DOMElement,
            'italic' => $this->firstChildElement($properties, 'i') instanceof \DOMElement,
            'underline' => $underline instanceof \DOMElement && strtolower(trim($underline->getAttribute('val'))) !== 'none',
            'strike' => $this->firstChildElement($properties, 'strike') instanceof \DOMElement,
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{bold:bool, italic:bool, underline:bool, strike:bool} $style
     * @return list<AstNode>
     */
    private function applyInlineStyle(array $inlines, array $style): array
    {
        if ($inlines === []) {
            return [];
        }

        if ($style['bold']) {
            $inlines = [new AstNode('strong', [], $inlines)];
        }
        if ($style['italic']) {
            $inlines = [new AstNode('emph', [], $inlines)];
        }
        if ($style['underline']) {
            $inlines = [new AstNode('underline', [], $inlines)];
        }
        if ($style['strike']) {
            $inlines = [new AstNode('strikeout', [], $inlines)];
        }

        return $inlines;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainTextFromInlines(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            if ($inline->type === 'text') {
                $text .= (string) $inline->attr('text', '');
                continue;
            }
            if ($inline->type === 'space') {
                $text .= ' ';
                continue;
            }
            if ($inline->type === 'softbreak' || $inline->type === 'linebreak') {
                $text .= "\n";
                continue;
            }

            $text .= $this->plainTextFromInlines($inline->children);
        }

        return $text;
    }

    private function isAstNodeList(mixed $nodes): bool
    {
        if (!is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function formatBooleanCellValue(string $rawValue): string
    {
        $value = strtolower(trim($rawValue));
        if ($value === '') {
            return '';
        }

        return match ($value) {
            '1', 'true' => 'TRUE',
            '0', 'false' => 'FALSE',
            default => trim($rawValue),
        };
    }

    /**
     * @return array{present:bool, formulaType:string, sharedIndex:int|null, formulaRef:string|null, hasCachedValue:bool, cachedValueType:string, formulaTextBytes:int, formulaSha256:string, sharedFormulaRole:string|null}|null
     */
    private function formulaMetadataForCell(\DOMElement $cellElement): ?array
    {
        $formulaElement = $this->firstChildElement($cellElement, 'f');
        if (!$formulaElement instanceof \DOMElement) {
            return null;
        }

        $formulaType = trim($formulaElement->getAttribute('t')) !== '' ? trim($formulaElement->getAttribute('t')) : 'normal';
        $formulaText = $formulaElement->textContent;
        $formulaRef = trim($formulaElement->getAttribute('ref'));
        $formulaRef = $formulaRef === '' ? null : $formulaRef;
        $formulaTextBytes = strlen($formulaText);

        return [
            'present' => true,
            'formulaType' => $formulaType,
            'sharedIndex' => $this->integerAttribute($formulaElement, 'si'),
            'formulaRef' => $formulaRef,
            'hasCachedValue' => $this->cellHasCachedValue($cellElement),
            'cachedValueType' => $this->cachedValueTypeForCell($cellElement),
            'formulaTextBytes' => $formulaTextBytes,
            'formulaSha256' => hash('sha256', $formulaText),
            'sharedFormulaRole' => $formulaType === 'shared'
                ? ($formulaTextBytes > 0 || $formulaRef !== null ? 'master' : 'follower')
                : null,
        ];
    }

    private function cellHasCachedValue(\DOMElement $cellElement): bool
    {
        $valueElement = $this->firstChildElement($cellElement, 'v');

        return $valueElement instanceof \DOMElement && $valueElement->textContent !== '';
    }

    /**
     * @return array{column:int, row:int}|null
     */
    private function parseCellReference(string $ref): ?array
    {
        if (preg_match('/^([A-Za-z]+)([1-9][0-9]*)$/', $ref, $matches) !== 1) {
            return null;
        }

        $column = 0;
        foreach (str_split($matches[1]) as $char) {
            $column = $column * 26 + (ord(strtoupper($char)) - ord('A') + 1);
        }

        return [
            'column' => $column,
            'row' => (int) $matches[2],
        ];
    }

    private function formatNumber(float $number): string
    {
        if (is_finite($number) && floor($number) === $number) {
            return number_format($number, 1, '.', '');
        }

        $formatted = rtrim(rtrim(sprintf('%.15G', $number), '0'), '.');

        return $formatted === '' ? '0.0' : $formatted;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatNumberForStyle(float $number, array $style): string
    {
        $formatCode = $style['formatCode'];
        if ($formatCode === null || $formatCode === 'General') {
            return $this->formatNumber($number);
        }

        $formatSection = $this->selectNumberFormatSection($formatCode, $number);
        if (strcasecmp(trim($formatSection), 'General') === 0 || trim($formatSection) === '') {
            return $this->formatNumber($number);
        }

        $normalizedSection = $this->numberFormatCodeForFormatting($formatSection);
        $isPercent = str_contains($formatSection, '%');
        if ($isPercent) {
            $number *= 100;
        }

        if ($this->isScientificNumberFormat($normalizedSection)) {
            $text = $this->formatScientificNumber($number, $normalizedSection);

            return $isPercent ? $text . '%' : $text;
        }

        if ($this->isFractionNumberFormat($normalizedSection)) {
            $text = $this->formatFractionNumber($number, $normalizedSection);

            return $isPercent ? $text . '%' : $text;
        }

        $decimals = $this->numberFormatDecimalPlaces($normalizedSection);
        $useGrouping = preg_match('/[0#?],[0#?]{3}/', $normalizedSection) === 1;
        $currency = $this->numberFormatCurrencySymbol($formatSection);
        $negative = $number < 0;
        $displayNumber = $negative ? abs($number) : $number;

        $text = $decimals === null
            ? $this->formatNumber($displayNumber)
            : number_format($displayNumber, $decimals, '.', $useGrouping ? ',' : '');

        if ($currency !== '') {
            $text = $currency . $text;
        }

        if ($negative) {
            $text = str_contains($formatSection, '(') && str_contains($formatSection, ')')
                ? '(' . $text . ')'
                : '-' . $text;
        }

        return $isPercent ? $text . '%' : $text;
    }

    private function isScientificNumberFormat(string $formatSection): bool
    {
        return preg_match('/e[+-]?[0#]+/i', $formatSection) === 1;
    }

    private function formatScientificNumber(float $number, string $formatSection): string
    {
        $decimals = 0;
        $exponentDigits = 2;
        if (preg_match('/\.([0#?]+).*?e[+-]?[0#]+/i', $formatSection, $matches) === 1) {
            $decimals = strlen($matches[1]);
        }
        if (preg_match('/e[+-]?([0#]+)/i', $formatSection, $matches) === 1) {
            $exponentDigits = max(1, strlen($matches[1]));
        }

        $formatted = sprintf('%.' . $decimals . 'E', $number);
        if (preg_match('/^(-?\d+(?:\.\d+)?)E([+-])(\d+)$/', $formatted, $matches) !== 1) {
            return $formatted;
        }

        return $matches[1] . 'E' . $matches[2] . str_pad($matches[3], $exponentDigits, '0', STR_PAD_LEFT);
    }

    private function isFractionNumberFormat(string $formatSection): bool
    {
        return preg_match('/[0#?]+\s*\/\s*[0#?]+/', $formatSection) === 1;
    }

    private function formatFractionNumber(float $number, string $formatSection): string
    {
        $negative = $number < 0;
        $absolute = abs($number);
        $whole = (int) floor($absolute);
        $fraction = $absolute - $whole;
        $denominatorDigits = 1;
        if (preg_match('/\/\s*([0#?]+)/', $formatSection, $matches) === 1) {
            $denominatorDigits = max(1, strlen($matches[1]));
        }
        $maxDenominator = (10 ** $denominatorDigits) - 1;
        [$numerator, $denominator] = $this->decimalFraction($fraction, $maxDenominator);
        if ($numerator === $denominator) {
            $whole++;
            $numerator = 0;
        }

        $prefix = $negative ? '-' : '';
        if ($numerator === 0) {
            return $prefix . (string) $whole;
        }

        $hasWholePlaceholder = preg_match('/[0#?]+\s+[0#?]+\s*\/\s*[0#?]+/', $formatSection) === 1;
        if ($whole > 0 && $hasWholePlaceholder) {
            return $prefix . $whole . ' ' . $numerator . '/' . $denominator;
        }
        if ($whole > 0) {
            $numerator += $whole * $denominator;
        }

        return $prefix . $numerator . '/' . $denominator;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function decimalFraction(float $fraction, int $maxDenominator): array
    {
        if ($fraction <= 0.0000001) {
            return [0, 1];
        }

        $bestNumerator = 1;
        $bestDenominator = 1;
        $bestError = PHP_FLOAT_MAX;
        for ($denominator = 1; $denominator <= max(1, $maxDenominator); $denominator++) {
            $numerator = (int) round($fraction * $denominator);
            if ($numerator < 1) {
                continue;
            }
            $error = abs($fraction - ($numerator / $denominator));
            if ($error < $bestError) {
                $bestNumerator = $numerator;
                $bestDenominator = $denominator;
                $bestError = $error;
            }
            if ($error <= 0.0000001) {
                break;
            }
        }

        $divisor = $this->greatestCommonDivisor($bestNumerator, $bestDenominator);

        return [intdiv($bestNumerator, $divisor), intdiv($bestDenominator, $divisor)];
    }

    private function greatestCommonDivisor(int $left, int $right): int
    {
        $left = abs($left);
        $right = abs($right);
        while ($right !== 0) {
            $next = $left % $right;
            $left = $right;
            $right = $next;
        }

        return max(1, $left);
    }

    /**
     * @param array{formatCode:string|null} $style
     */
    private function selectedNumberFormatSectionForStyle(array $style, float $number): ?string
    {
        $formatCode = $style['formatCode'];
        if (!is_string($formatCode) || $formatCode === '' || strcasecmp($formatCode, 'General') === 0) {
            return null;
        }

        return $this->selectNumberFormatSection($formatCode, $number);
    }

    /**
     * @param array{numFmtId:int|null, formatCode:string|null} $style
     */
    private function numberFormatKindForStyle(array $style, ?string $formatSection): ?string
    {
        if ($formatSection === null || trim($formatSection) === '') {
            return null;
        }

        if ($this->isDateStyle($style)) {
            $formatCode = (string) ($style['formatCode'] ?? '');
            if ($this->isElapsedTimeFormat($formatCode)) {
                return 'elapsed-time';
            }

            $normalized = strtolower($this->numberFormatCodeForDetection($formatCode));
            $hasTime = preg_match('/[hs]/', $normalized) === 1;
            $hasDate = preg_match('/[yd]/', $normalized) === 1;
            if ($hasTime && !$hasDate) {
                return 'time';
            }

            return $hasTime ? 'date-time' : 'date';
        }

        $normalizedSection = $this->numberFormatCodeForFormatting($formatSection);
        if (str_contains($formatSection, '%')) {
            return 'percentage';
        }
        if ($this->numberFormatCurrencySymbol($formatSection) !== '') {
            return 'currency';
        }
        if (preg_match('/e[+-]?[0#]+/i', $normalizedSection) === 1) {
            return 'scientific';
        }
        if (str_contains($normalizedSection, '/')) {
            return 'fraction';
        }

        return 'number';
    }

    private function selectNumberFormatSection(string $formatCode, float $number): string
    {
        $sections = explode(';', $formatCode);
        if (count($sections) === 1) {
            return $sections[0];
        }

        if ($number < 0) {
            return trim($sections[1] ?? '') !== '' ? $sections[1] : $sections[0];
        }
        if ($number == 0.0 && isset($sections[2]) && trim($sections[2]) !== '') {
            return $sections[2];
        }

        return $sections[0];
    }

    private function numberFormatDecimalPlaces(string $formatSection): ?int
    {
        if (preg_match('/\.([0#?]+)/', $formatSection, $matches) === 1) {
            return strlen($matches[1]);
        }
        if (preg_match('/[0#?]/', $formatSection) === 1) {
            return 0;
        }

        return null;
    }

    private function numberFormatCurrencySymbol(string $formatSection): string
    {
        if (preg_match('/\[\$([^-\\]]+)/', $formatSection, $matches) === 1) {
            return $matches[1];
        }

        return str_contains($formatSection, '$') ? '$' : '';
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function isDateStyle(array $style): bool
    {
        $numFmtId = $style['numFmtId'];
        if ($numFmtId !== null && in_array($numFmtId, [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57], true)) {
            return true;
        }

        $formatCode = $style['formatCode'];
        if ($formatCode === null) {
            return false;
        }

        $normalized = strtolower($this->numberFormatCodeForDetection($formatCode));
        if ($normalized === '') {
            return false;
        }

        return preg_match('/[ymdhs]/', $normalized) === 1
            && preg_match('/[0#?]/', $normalized) !== 1;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatDateSerial(float $serial, array $style, bool $date1904): string
    {
        $rawFormatCode = (string) ($style['formatCode'] ?? '');
        if ($this->isElapsedTimeFormat($rawFormatCode)) {
            return $this->formatElapsedTimeSerial($serial, $rawFormatCode);
        }

        $days = (int) floor($serial);
        $fraction = $serial - $days;
        if ($fraction < 0) {
            $fraction += 1.0;
            $days--;
        }

        $date = new \DateTimeImmutable($date1904 ? '1904-01-01 00:00:00 UTC' : '1899-12-31 00:00:00 UTC');
        if (!$date1904 && $days >= 60) {
            $days--;
        }
        if ($days !== 0) {
            $date = $date->modify(($days >= 0 ? '+' : '') . $days . ' days') ?: $date;
        }

        $seconds = (int) round($fraction * 86400);
        if ($seconds !== 0) {
            $date = $date->modify('+' . $seconds . ' seconds') ?: $date;
        }

        $formatCode = strtolower($this->numberFormatCodeForDetection($rawFormatCode));
        $hasTime = preg_match('/[hs]/', $formatCode) === 1;
        $hasDate = preg_match('/[yd]/', $formatCode) === 1;
        if ($hasTime && !$hasDate) {
            return $date->format($this->timeFormatForStyle($rawFormatCode));
        }

        return $hasTime ? $date->format('Y-m-d ' . $this->timeFormatForStyle($rawFormatCode)) : $date->format('Y-m-d');
    }

    private function timeFormatForStyle(string $formatCode): string
    {
        $hasSeconds = preg_match('/s+/i', $this->numberFormatCodeForDetection($formatCode)) === 1;
        $usesMeridiem = preg_match('/am\/pm|a\/p/i', $formatCode) === 1;
        if ($usesMeridiem) {
            return $hasSeconds ? 'g:i:s A' : 'g:i A';
        }

        return $hasSeconds ? 'H:i:s' : 'H:i';
    }

    private function isElapsedTimeFormat(string $formatCode): bool
    {
        return preg_match('/\[(h|m|s)\]/i', $formatCode) === 1;
    }

    private function formatElapsedTimeSerial(float $serial, string $formatCode): string
    {
        $negative = $serial < 0;
        $totalSeconds = (int) round(abs($serial) * 86400);
        $prefix = $negative ? '-' : '';
        if (preg_match('/\[m\]/i', $formatCode) === 1) {
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;

            return $prefix . sprintf('%d:%02d', $minutes, $seconds);
        }
        if (preg_match('/\[s\]/i', $formatCode) === 1) {
            return $prefix . (string) $totalSeconds;
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return str_contains(strtolower($formatCode), ':ss')
            ? $prefix . sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : $prefix . sprintf('%d:%02d', $hours, $minutes);
    }

    private function numberFormatCodeForDetection(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./', '', $formatCode) ?? $formatCode;
        $formatCode = explode(';', $formatCode)[0] ?? $formatCode;

        return trim($formatCode);
    }

    private function numberFormatCodeForFormatting(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./', '', $formatCode) ?? $formatCode;
        $formatCode = str_replace(['_', '*'], '', $formatCode);

        return trim($formatCode);
    }

    private function builtInNumberFormat(int $id): ?string
    {
        return [
            0 => 'General',
            1 => '0',
            2 => '0.00',
            3 => '#,##0',
            4 => '#,##0.00',
            5 => '$#,##0;($#,##0)',
            6 => '$#,##0;[Red]($#,##0)',
            7 => '$#,##0.00;($#,##0.00)',
            8 => '$#,##0.00;[Red]($#,##0.00)',
            9 => '0%',
            10 => '0.00%',
            11 => '0.00E+00',
            12 => '# ?/?',
            13 => '# ??/??',
            14 => 'm/d/yy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            18 => 'h:mm AM/PM',
            19 => 'h:mm:ss AM/PM',
            20 => 'h:mm',
            21 => 'h:mm:ss',
            22 => 'm/d/yy h:mm',
            27 => '[$-404]e/m/d',
            30 => 'm/d/yy',
            36 => '[$-404]e/m/d',
            37 => '#,##0;(#,##0)',
            38 => '#,##0;[Red](#,##0)',
            39 => '#,##0.00;(#,##0.00)',
            40 => '#,##0.00;[Red](#,##0.00)',
            41 => '_(* #,##0_);_(* (#,##0);_(* "-"_);_(@_)',
            42 => '_($* #,##0_);_($* (#,##0);_($* "-"_);_(@_)',
            43 => '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)',
            44 => '_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
            48 => '##0.0E+0',
            49 => '@',
            50 => '[$-404]e/m/d',
            57 => '[$-404]e/m/d',
        ][$id] ?? null;
    }

    /**
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @return array<string, mixed>
     */
    private function styleForIndex(?int $styleIndex, array $styles): array
    {
        $default = $this->defaultCellStyle();
        if ($styleIndex === null) {
            return $default;
        }

        if (array_key_exists($styleIndex, $styles['cellFormats'])) {
            return $styles['cellFormats'][$styleIndex];
        }

        $font = $styles['fonts'][$styleIndex] ?? null;
        if (is_array($font)) {
            return array_replace($default, [
                'bold' => (bool) ($font['bold'] ?? false),
                'italic' => (bool) ($font['italic'] ?? false),
                'underline' => (bool) ($font['underline'] ?? false),
                'strike' => (bool) ($font['strike'] ?? false),
                'fontName' => $font['name'] ?? null,
                'fontSize' => $font['size'] ?? null,
                'fontColor' => $font['color'] ?? null,
                'fontColorMetadata' => $font['colorMetadata'] ?? [],
                'fontFamily' => $font['family'] ?? null,
                'fontCharset' => $font['charset'] ?? null,
                'fontScheme' => $font['scheme'] ?? null,
                'fontUnderlineStyle' => $font['underlineStyle'] ?? null,
                'fontVerticalAlign' => $font['verticalAlign'] ?? null,
            ]);
        }

        return $default;
    }

    /**
     * @param array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }> $cells
     */
    private function cellsToTable(string $sheetName, array $cells): ?AstNode
    {
        if ($cells === []) {
            return null;
        }

        $rows = array_column($cells, 'row');
        $columns = array_column($cells, 'column');
        $minRow = min($rows);
        $maxRow = max($rows);
        $minColumn = min($columns);
        $maxColumn = max($columns);

        $grid = [];
        for ($row = $minRow; $row <= $maxRow; $row++) {
            $gridRow = [];
            for ($column = $minColumn; $column <= $maxColumn; $column++) {
                $gridRow[] = $cells[$row . ':' . $column] ?? null;
            }
            $grid[] = $gridRow;
        }

        $header = array_shift($grid);
        if (!is_array($header)) {
            return null;
        }
        while ($grid !== [] && $this->isEmptyRow($grid[count($grid) - 1])) {
            array_pop($grid);
        }

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, count($header), 'default'),
            'xlsxSheetName' => $sheetName,
        ], [
            new AstNode('table_head', [], [
                $this->tableRow($header, true),
            ]),
            new AstNode('table_body', [], array_map(
                fn (array $row): AstNode => $this->tableRow($row, false),
                $grid
            )),
        ]);
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        $cells = [];
        foreach ($row as $columnIndex => $cell) {
            if (is_array($cell) && ($cell['covered'] ?? false) === true) {
                continue;
            }
            $text = is_array($cell) ? (string) $cell['text'] : '';
            $attrs = [
                'header' => $header,
                'text' => $text,
                'sourceCell' => is_array($cell) ? (string) $cell['ref'] : null,
                'sourceColumn' => $columnIndex,
                'xlsxValueType' => is_array($cell) ? (string) $cell['valueType'] : 'empty',
            ];
            if (is_array($cell)) {
                $attrs += $this->cellValueAttributes($cell);
                $attrs += $this->cellLayoutAttributes($cell);
                $attrs += $this->cellStyleAttributes($cell);
                $formulaAttributes = $this->cellFormulaAttributes($cell);
                if ($formulaAttributes !== []) {
                    $attrs += $formulaAttributes;
                }
                $commentAttributes = $this->cellCommentAttributes($cell);
                if ($commentAttributes !== []) {
                    $attrs += $commentAttributes;
                }
                $horizontalAlign = (string) ($cell['style']['horizontalAlign'] ?? '');
                if (in_array($horizontalAlign, ['left', 'right', 'center'], true)) {
                    $attrs['align'] = $horizontalAlign;
                }
            }
            if (is_array($cell) && (int) ($cell['colspan'] ?? 1) > 1) {
                $attrs['colspan'] = (int) $cell['colspan'];
            }
            if (is_array($cell) && (int) ($cell['rowspan'] ?? 1) > 1) {
                $attrs['rowspan'] = (int) $cell['rowspan'];
            }

            $cells[] = new AstNode('table_cell', $attrs, [
                new AstNode('plain', [], $this->cellInlines(is_array($cell) ? $cell : null)),
            ]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @param array<string, mixed>|null $cell
     * @return list<AstNode>
     */
    private function cellInlines(?array $cell): array
    {
        if ($cell === null || ($cell['empty'] ?? true) === true) {
            return [];
        }

        $cellInlines = $cell['inlines'] ?? [];
        $inlines = is_array($cellInlines) && $cellInlines !== [] && $this->isAstNodeList($cellInlines)
            ? $cellInlines
            : $this->textInlines((string) $cell['text']);
        $inlines = $this->applyInlineStyle($inlines, [
            'bold' => ($cell['bold'] ?? false) === true,
            'italic' => ($cell['italic'] ?? false) === true,
            'underline' => ($cell['underline'] ?? false) === true,
            'strike' => ($cell['strike'] ?? false) === true,
        ]);
        if (($cell['url'] ?? '') !== '') {
            $inlines = [new AstNode('link', [
                'url' => (string) $cell['url'],
                'title' => (string) ($cell['title'] ?? ''),
            ], $inlines)];
        }

        return $inlines;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellValueAttributes(array $cell): array
    {
        $attrs = [];
        $map = [
            'rawValue' => 'xlsxRawValue',
            'numericValue' => 'xlsxNumericValue',
            'numberFormatSection' => 'xlsxNumberFormatSection',
            'numberFormatKind' => 'xlsxNumberFormatKind',
        ];

        foreach ($map as $source => $target) {
            $value = $cell[$source] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $attrs[$target] = $value;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellFormulaAttributes(array $cell): array
    {
        $formula = $cell['formula'] ?? null;
        if (!is_array($formula) || ($formula['present'] ?? false) !== true) {
            return [];
        }

        $attrs = [
            'xlsxFormula' => true,
            'xlsxFormulaType' => (string) ($formula['formulaType'] ?? 'normal'),
            'xlsxFormulaHasCachedValue' => ($formula['hasCachedValue'] ?? false) === true,
            'xlsxFormulaCachedValueType' => (string) ($formula['cachedValueType'] ?? 'missing'),
            'xlsxFormulaTextBytes' => (int) ($formula['formulaTextBytes'] ?? 0),
            'xlsxFormulaSha256' => (string) ($formula['formulaSha256'] ?? ''),
        ];

        if (($formula['sharedIndex'] ?? null) !== null) {
            $attrs['xlsxSharedFormulaIndex'] = (int) $formula['sharedIndex'];
        }
        if (($formula['formulaRef'] ?? null) !== null) {
            $attrs['xlsxFormulaRef'] = (string) $formula['formulaRef'];
        }
        if (($formula['sharedFormulaRole'] ?? null) !== null) {
            $attrs['xlsxSharedFormulaRole'] = (string) $formula['sharedFormulaRole'];
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellLayoutAttributes(array $cell): array
    {
        $attrs = [];
        $row = $cell['rowMetadata'] ?? null;
        if (is_array($row)) {
            $map = [
                'height' => 'xlsxRowHeight',
                'styleIndex' => 'xlsxRowStyleIndex',
                'hidden' => 'xlsxRowHidden',
                'customHeight' => 'xlsxRowCustomHeight',
                'outlineLevel' => 'xlsxRowOutlineLevel',
                'collapsed' => 'xlsxRowCollapsed',
            ];
            foreach ($map as $source => $target) {
                $value = $row[$source] ?? null;
                if ($value !== null && $value !== '') {
                    $attrs[$target] = $value;
                }
            }
        }

        $column = $cell['columnMetadata'] ?? null;
        if (is_array($column)) {
            $map = [
                'range' => 'xlsxColumnRange',
                'width' => 'xlsxColumnWidth',
                'styleIndex' => 'xlsxColumnStyleIndex',
                'hidden' => 'xlsxColumnHidden',
                'customWidth' => 'xlsxColumnCustomWidth',
                'bestFit' => 'xlsxColumnBestFit',
                'outlineLevel' => 'xlsxColumnOutlineLevel',
                'collapsed' => 'xlsxColumnCollapsed',
            ];
            foreach ($map as $source => $target) {
                $value = $column[$source] ?? null;
                if ($value !== null && $value !== '') {
                    $attrs[$target] = $value;
                }
            }
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellStyleAttributes(array $cell): array
    {
        $style = $cell['style'] ?? [];
        if (!is_array($style)) {
            $style = [];
        }

        $attrs = [];
        $map = [
            'styleIndex' => 'xlsxStyleIndex',
            'xfId' => 'xlsxStyleXfId',
            'cellStyleName' => 'xlsxCellStyleName',
            'cellStyleBuiltinId' => 'xlsxCellStyleBuiltinId',
            'cellStyleCustomBuiltin' => 'xlsxCellStyleCustomBuiltin',
            'numFmtId' => 'xlsxNumberFormatId',
            'formatCode' => 'xlsxNumberFormatCode',
            'fontId' => 'xlsxFontId',
            'fontName' => 'xlsxFontName',
            'fontSize' => 'xlsxFontSize',
            'fontColor' => 'xlsxFontColor',
            'fontColorMetadata' => 'xlsxFontColorMetadata',
            'fontFamily' => 'xlsxFontFamily',
            'fontCharset' => 'xlsxFontCharset',
            'fontScheme' => 'xlsxFontScheme',
            'fontUnderlineStyle' => 'xlsxFontUnderlineStyle',
            'fontVerticalAlign' => 'xlsxFontVerticalAlign',
            'fillId' => 'xlsxFillId',
            'fillPatternType' => 'xlsxFillPatternType',
            'fillForegroundColor' => 'xlsxFillForegroundColor',
            'fillBackgroundColor' => 'xlsxFillBackgroundColor',
            'fillForegroundColorMetadata' => 'xlsxFillForegroundColorMetadata',
            'fillBackgroundColorMetadata' => 'xlsxFillBackgroundColorMetadata',
            'fillGradientType' => 'xlsxFillGradientType',
            'fillGradientDegree' => 'xlsxFillGradientDegree',
            'fillGradientEdges' => 'xlsxFillGradientEdges',
            'fillGradientStops' => 'xlsxFillGradientStops',
            'fillGradientStopCount' => 'xlsxFillGradientStopCount',
            'borderId' => 'xlsxBorderId',
            'borderLeftStyle' => 'xlsxBorderLeftStyle',
            'borderLeftColor' => 'xlsxBorderLeftColor',
            'borderLeftColorMetadata' => 'xlsxBorderLeftColorMetadata',
            'borderRightStyle' => 'xlsxBorderRightStyle',
            'borderRightColor' => 'xlsxBorderRightColor',
            'borderRightColorMetadata' => 'xlsxBorderRightColorMetadata',
            'borderTopStyle' => 'xlsxBorderTopStyle',
            'borderTopColor' => 'xlsxBorderTopColor',
            'borderTopColorMetadata' => 'xlsxBorderTopColorMetadata',
            'borderBottomStyle' => 'xlsxBorderBottomStyle',
            'borderBottomColor' => 'xlsxBorderBottomColor',
            'borderBottomColorMetadata' => 'xlsxBorderBottomColorMetadata',
            'borderDiagonalStyle' => 'xlsxBorderDiagonalStyle',
            'borderDiagonalColor' => 'xlsxBorderDiagonalColor',
            'borderDiagonalColorMetadata' => 'xlsxBorderDiagonalColorMetadata',
            'borderDiagonalUp' => 'xlsxBorderDiagonalUp',
            'borderDiagonalDown' => 'xlsxBorderDiagonalDown',
            'borderOutline' => 'xlsxBorderOutline',
            'horizontalAlign' => 'xlsxHorizontalAlign',
            'verticalAlign' => 'xlsxVerticalAlign',
            'wrapText' => 'xlsxWrapText',
            'textRotation' => 'xlsxTextRotation',
            'indent' => 'xlsxIndent',
            'relativeIndent' => 'xlsxRelativeIndent',
            'shrinkToFit' => 'xlsxShrinkToFit',
            'readingOrder' => 'xlsxReadingOrder',
            'justifyLastLine' => 'xlsxJustifyLastLine',
            'locked' => 'xlsxLocked',
            'hidden' => 'xlsxHidden',
            'applyNumberFormat' => 'xlsxApplyNumberFormat',
            'applyFont' => 'xlsxApplyFont',
            'applyFill' => 'xlsxApplyFill',
            'applyBorder' => 'xlsxApplyBorder',
            'applyAlignment' => 'xlsxApplyAlignment',
            'applyProtection' => 'xlsxApplyProtection',
            'quotePrefix' => 'xlsxQuotePrefix',
            'pivotButton' => 'xlsxPivotButton',
        ];

        foreach ($map as $source => $target) {
            $value = $source === 'styleIndex' ? ($cell['styleIndex'] ?? null) : ($style[$source] ?? null);
            if ($value === null || $value === '') {
                continue;
            }
            $attrs[$target] = $value;
        }

        if (($style['underline'] ?? false) === true) {
            $attrs['xlsxUnderline'] = true;
        }
        if (($style['strike'] ?? false) === true) {
            $attrs['xlsxStrike'] = true;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellCommentAttributes(array $cell): array
    {
        $comments = $cell['comments'] ?? [];
        if (!is_array($comments) || $comments === []) {
            return [];
        }

        return [
            'xlsxCommentCount' => count($comments),
            'xlsxComments' => $comments,
            'xlsxCommentAuthors' => array_values(array_unique(array_filter(array_map(
                static fn (mixed $comment): ?string => is_array($comment) && is_string($comment['author'] ?? null) ? $comment['author'] : null,
                $comments
            )))),
        ];
    }

    /**
     * @return array{
     *     commentCount:int,
     *     comments:list<array<string, mixed>>,
     *     commentsByCell:array<string, list<array<string, mixed>>>,
     *     commentDiagnostics:list<string>
     * }
     */
    private function parseSheetComments(
        ZipPackage $package,
        string $sheetPart,
        OpcRelationships $relationships,
        OpcRelationshipInventory $relationshipInventory,
        ?array &$threadedCommentPersonReview,
    ): array
    {
        $comments = [];
        $commentsByCell = [];
        $diagnostics = [];
        $threadedPersonsById = null;

        foreach ($relationships->all() as $relationship) {
            if (in_array($relationship->type, self::COMMENTS_RELATIONSHIP_TYPES, true)) {
                $this->appendLegacyCommentsForRelationship($package, $sheetPart, $relationships, $relationship, $comments, $commentsByCell, $diagnostics);
                continue;
            }

            if ($relationship->type !== self::THREADED_COMMENTS_RELATIONSHIP) {
                continue;
            }

            if ($threadedPersonsById === null) {
                if ($threadedCommentPersonReview === null) {
                    $threadedCommentPersonReview = $this->readThreadedCommentPersons($package, $relationshipInventory);
                }
                $personReview = $threadedCommentPersonReview;
                $threadedPersonsById = $personReview['personsById'];
                $diagnostics = array_merge($diagnostics, $personReview['diagnostics']);
            }

            $this->appendThreadedCommentsForRelationship($package, $sheetPart, $relationships, $relationship, $threadedPersonsById, $comments, $commentsByCell, $diagnostics);
        }

        return [
            'commentCount' => count($comments),
            'comments' => $comments,
            'commentsByCell' => $commentsByCell,
            'commentDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseRichTextRuns(\DOMElement $textElement): array
    {
        $runs = [];
        foreach ($this->childElements($textElement, 'r') as $runElement) {
            $text = '';
            foreach ($this->childElements($runElement, 't') as $textRun) {
                $text .= $textRun->textContent;
            }
            if ($text === '') {
                continue;
            }

            $record = [
                'text' => $text,
                'textBytes' => strlen($text),
                'textSha256' => hash('sha256', $text),
            ];

            $properties = $this->firstChildElement($runElement, 'rPr');
            if ($properties instanceof \DOMElement) {
                $record += $this->richTextRunProperties($properties);
            }

            $runs[] = $record;
        }

        if ($runs !== []) {
            return $runs;
        }

        $text = $this->allDescendantText($textElement);
        if ($text === '') {
            return [];
        }

        return [[
            'text' => $text,
            'textBytes' => strlen($text),
            'textSha256' => hash('sha256', $text),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function richTextRunProperties(\DOMElement $properties): array
    {
        $underline = $this->firstChildElement($properties, 'u');
        $size = $this->firstChildElement($properties, 'sz');
        $font = $this->firstChildElement($properties, 'rFont');
        $family = $this->firstChildElement($properties, 'family');
        $charset = $this->firstChildElement($properties, 'charset');
        $scheme = $this->firstChildElement($properties, 'scheme');
        $verticalAlign = $this->firstChildElement($properties, 'vertAlign');
        $color = $this->firstChildElement($properties, 'color');
        $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];

        $attrs = [
            'bold' => $this->firstChildElement($properties, 'b') instanceof \DOMElement,
            'italic' => $this->firstChildElement($properties, 'i') instanceof \DOMElement,
            'strike' => $this->firstChildElement($properties, 'strike') instanceof \DOMElement,
        ];

        if ($underline instanceof \DOMElement) {
            $underlineStyle = strtolower(trim($underline->getAttribute('val')));
            $attrs['underline'] = $underlineStyle !== 'none';
            $attrs['underlineStyle'] = $underlineStyle !== '' ? $underlineStyle : 'single';
        }
        if ($size instanceof \DOMElement && is_numeric(trim($size->getAttribute('val')))) {
            $attrs['fontSize'] = (float) trim($size->getAttribute('val'));
        }
        if ($font instanceof \DOMElement && trim($font->getAttribute('val')) !== '') {
            $attrs['fontName'] = trim($font->getAttribute('val'));
        }
        if ($colorMetadata !== []) {
            $attrs['fontColor'] = $colorMetadata['token'] ?? null;
            $attrs['fontColorMetadata'] = $colorMetadata;
        }
        if ($family instanceof \DOMElement) {
            $attrs['fontFamily'] = $this->integerAttribute($family, 'val');
        }
        if ($charset instanceof \DOMElement) {
            $attrs['fontCharset'] = $this->integerAttribute($charset, 'val');
        }
        if ($scheme instanceof \DOMElement && trim($scheme->getAttribute('val')) !== '') {
            $attrs['fontScheme'] = trim($scheme->getAttribute('val'));
        }
        if ($verticalAlign instanceof \DOMElement && trim($verticalAlign->getAttribute('val')) !== '') {
            $attrs['fontVerticalAlign'] = trim($verticalAlign->getAttribute('val'));
        }

        return array_filter(
            $attrs,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    /**
     * @param list<array<string, mixed>> $comments
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @param list<string> $diagnostics
     */
    private function appendLegacyCommentsForRelationship(
        ZipPackage $package,
        string $sheetPart,
        OpcRelationships $relationships,
        OpcRelationship $relationship,
        array &$comments,
        array &$commentsByCell,
        array &$diagnostics
    ): void {
        $part = $this->commentRelationshipPart($package, $relationships, $relationship, 'comments', $diagnostics);
        if ($part === null) {
            return;
        }

        try {
            $document = $this->loadPackageXml($package, $part, 'XLSX comments ' . $relationship->id . ' for ' . ltrim($sheetPart, '/'));
        } catch (\InvalidArgumentException|\RuntimeException) {
            $diagnostics[] = 'comments-part-unreadable:' . ltrim($part, '/');
            return;
        }

        $root = XmlHtmlDom::rootElement($document, 'comments');
        if (!$root instanceof \DOMElement) {
            $diagnostics[] = 'comments-root-missing:' . ltrim($part, '/');
            return;
        }

        $authors = [];
        $authorsElement = $this->firstChildElement($root, 'authors');
        if ($authorsElement instanceof \DOMElement) {
            foreach ($this->childElements($authorsElement, 'author') as $authorElement) {
                $authors[] = $authorElement->textContent;
            }
        }

        $commentList = $this->firstChildElement($root, 'commentList');
        if (!$commentList instanceof \DOMElement) {
            return;
        }

        foreach ($this->childElements($commentList, 'comment') as $commentElement) {
            $ref = trim($commentElement->getAttribute('ref'));
            if ($this->parseCellReference($ref) === null) {
                $diagnostics[] = 'comments-invalid-ref:' . ($ref === '' ? '<empty>' : $ref);
                continue;
            }

            $authorId = $this->integerAttribute($commentElement, 'authorId');
            $textElement = $this->firstChildElement($commentElement, 'text');
            $text = $this->commentText($textElement);
            $richTextRuns = $textElement instanceof \DOMElement ? $this->parseRichTextRuns($textElement) : [];
            $this->appendCellCommentRecord([
                'kind' => 'legacy',
                'ref' => $ref,
                'authorId' => $authorId,
                'author' => $authorId !== null ? ($authors[$authorId] ?? null) : null,
                'text' => $text,
                'textBytes' => strlen($text),
                'textSha256' => hash('sha256', $text),
                'richTextRunCount' => count($richTextRuns),
                'richTextRuns' => $richTextRuns,
                'partName' => ltrim($part, '/'),
                'relationshipId' => $relationship->id,
            ], $comments, $commentsByCell);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $personsById
     * @param list<array<string, mixed>> $comments
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @param list<string> $diagnostics
     */
    private function appendThreadedCommentsForRelationship(
        ZipPackage $package,
        string $sheetPart,
        OpcRelationships $relationships,
        OpcRelationship $relationship,
        array $personsById,
        array &$comments,
        array &$commentsByCell,
        array &$diagnostics
    ): void {
        $part = $this->commentRelationshipPart($package, $relationships, $relationship, 'threaded-comments', $diagnostics);
        if ($part === null) {
            return;
        }

        try {
            $document = $this->loadPackageXml($package, $part, 'XLSX threaded comments ' . $relationship->id . ' for ' . ltrim($sheetPart, '/'));
        } catch (\InvalidArgumentException|\RuntimeException) {
            $diagnostics[] = 'threaded-comments-part-unreadable:' . ltrim($part, '/');
            return;
        }

        $root = XmlHtmlDom::rootElement($document, 'ThreadedComments') ?? XmlHtmlDom::rootElement($document, 'threadedComments');
        if (!$root instanceof \DOMElement) {
            $diagnostics[] = 'threaded-comments-root-missing:' . ltrim($part, '/');
            return;
        }

        foreach ($this->childElements($root, 'threadedComment') as $commentElement) {
            $ref = trim($commentElement->getAttribute('ref'));
            if ($this->parseCellReference($ref) === null) {
                $diagnostics[] = 'threaded-comments-invalid-ref:' . ($ref === '' ? '<empty>' : $ref);
                continue;
            }

            $personId = $this->stringAttribute($commentElement, 'personId');
            $person = $personId === null ? null : ($personsById[$personId] ?? null);
            if ($personId !== null && $person === null) {
                $diagnostics[] = 'threaded-comments-person-missing:' . $personId;
            }

            $textElement = $this->firstChildElement($commentElement, 'text');
            $text = $this->commentText($textElement);
            $author = is_array($person)
                ? ($person['displayName'] ?? $person['userId'] ?? null)
                : null;

            $this->appendCellCommentRecord([
                'kind' => 'threaded',
                'ref' => $ref,
                'id' => $this->stringAttribute($commentElement, 'id'),
                'parentId' => $this->stringAttribute($commentElement, 'parentId'),
                'personId' => $personId,
                'author' => $author,
                'person' => $person,
                'dateTime' => $this->stringAttribute($commentElement, 'dT'),
                'done' => $this->booleanAttribute($commentElement, 'done'),
                'text' => $text,
                'textBytes' => strlen($text),
                'textSha256' => hash('sha256', $text),
                'partName' => ltrim($part, '/'),
                'relationshipId' => $relationship->id,
            ], $comments, $commentsByCell);
        }
    }

    /**
     * @return array{personsById:array<string, array<string, mixed>>, diagnostics:list<string>}
     */
    private function readThreadedCommentPersons(ZipPackage $package, OpcRelationshipInventory $relationshipInventory): array
    {
        $personsById = [];
        $diagnostics = [];
        $parsedParts = [];
        $relationshipSets = $this->packageRelationshipSets($relationshipInventory);

        foreach ($relationshipSets['parseErrors'] as $parseError) {
            $diagnostics[] = 'threaded-comment-person-relationship-parse-error:' . $parseError['relationshipPart'];
        }

        foreach ($relationshipSets['sets'] as $relationshipSet) {
            $relationships = $relationshipSet['relationships'];
            foreach ($relationships->all() as $relationship) {
                if ($relationship->type !== self::THREADED_COMMENT_PERSON_RELATIONSHIP) {
                    continue;
                }

                $part = $this->commentRelationshipPart($package, $relationships, $relationship, 'threaded-comment-person', $diagnostics);
                if ($part === null) {
                    continue;
                }

                $partKey = ltrim($part, '/');
                if (isset($parsedParts[$partKey])) {
                    continue;
                }

                $parsedParts[$partKey] = true;
                $this->appendThreadedCommentPersonsFromPart($package, $part, $personsById, $diagnostics);
            }
        }

        foreach ($package->names() as $name) {
            try {
                $part = OpcPackagePath::canonicalPartName($name);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $partKey = ltrim($part, '/');
            if (isset($parsedParts[$partKey]) || !str_contains(strtolower($part), '/persons/')) {
                continue;
            }

            $parsedParts[$partKey] = true;
            $this->appendThreadedCommentPersonsFromPart($package, $part, $personsById, $diagnostics);
        }

        return [
            'personsById' => $personsById,
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $personsById
     * @param list<string> $diagnostics
     */
    private function appendThreadedCommentPersonsFromPart(ZipPackage $package, string $part, array &$personsById, array &$diagnostics): void
    {
        if (!$package->has($part)) {
            $diagnostics[] = 'threaded-comment-person-part-missing:' . ltrim($part, '/');
            return;
        }

        try {
            $document = $this->loadPackageXml($package, $part, 'XLSX threaded comment persons ' . ltrim($part, '/'));
        } catch (\InvalidArgumentException|\RuntimeException) {
            $diagnostics[] = 'threaded-comment-person-part-unreadable:' . ltrim($part, '/');
            return;
        }

        $root = XmlHtmlDom::rootElement($document, 'personList');
        if (!$root instanceof \DOMElement) {
            $diagnostics[] = 'threaded-comment-person-root-missing:' . ltrim($part, '/');
            return;
        }

        foreach ($this->childElements($root, 'person') as $personElement) {
            $id = $this->stringAttribute($personElement, 'id');
            if ($id === null) {
                $diagnostics[] = 'threaded-comment-person-invalid-id:' . ltrim($part, '/');
                continue;
            }

            $personsById[$id] = [
                'id' => $id,
                'displayName' => $this->stringAttribute($personElement, 'displayName'),
                'userId' => $this->stringAttribute($personElement, 'userId'),
                'providerId' => $this->stringAttribute($personElement, 'providerId'),
                'partName' => ltrim($part, '/'),
            ];
        }
    }

    /**
     * @param list<string> $diagnostics
     */
    private function commentRelationshipPart(ZipPackage $package, OpcRelationships $relationships, OpcRelationship $relationship, string $diagnosticPrefix, array &$diagnostics): ?string
    {
        if ($relationship->isExternal()) {
            $diagnostics[] = $diagnosticPrefix . '-external-relationship-skipped:' . $relationship->id;
            return null;
        }

        try {
            $part = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
        } catch (\Throwable) {
            $diagnostics[] = $diagnosticPrefix . '-target-resolution-error:' . $relationship->id;
            return null;
        }

        if (!$package->has($part)) {
            $diagnostics[] = $diagnosticPrefix . '-part-missing:' . ltrim($part, '/');
            return null;
        }

        return $part;
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array<string, mixed>> $comments
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     */
    private function appendCellCommentRecord(array $record, array &$comments, array &$commentsByCell): void
    {
        $comments[] = $record;
        $ref = (string) ($record['ref'] ?? '');
        $commentsByCell[$ref] ??= [];
        $commentsByCell[$ref][] = $record;
    }

    private function stringAttribute(\DOMElement $element, string $attribute): ?string
    {
        if (!$element->hasAttribute($attribute)) {
            return null;
        }

        $value = trim($element->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    private function commentText(?\DOMElement $textElement): string
    {
        if (!$textElement instanceof \DOMElement) {
            return '';
        }

        $richText = $this->allDescendantText($textElement);

        return $richText === '' ? $textElement->textContent : $richText;
    }

    /**
     * @return array{
     *     formulaCellCount:int,
     *     formulaCachedValueCount:int,
     *     sharedFormulaCellCount:int,
     *     sharedFormulaMasterCount:int,
     *     sharedFormulaFollowerCount:int,
     *     formulaDiagnostics:list<array{ref:string, present:bool, formulaType:string, sharedIndex:int|null, formulaRef:string|null, hasCachedValue:bool, cachedValueType:string, formulaTextBytes:int, formulaSha256:string, sharedFormulaRole:string|null}>,
     *     errorCellCount:int,
     *     errorDiagnostics:list<array{ref:string, code:string, fromFormula:bool}>
     * }
     */
    private function parseSheetDiagnostics(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return [
                'formulaCellCount' => 0,
                'formulaCachedValueCount' => 0,
                'sharedFormulaCellCount' => 0,
                'sharedFormulaMasterCount' => 0,
                'sharedFormulaFollowerCount' => 0,
                'formulaDiagnostics' => [],
                'errorCellCount' => 0,
                'errorDiagnostics' => [],
            ];
        }

        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [
                'formulaCellCount' => 0,
                'formulaCachedValueCount' => 0,
                'sharedFormulaCellCount' => 0,
                'sharedFormulaMasterCount' => 0,
                'sharedFormulaFollowerCount' => 0,
                'formulaDiagnostics' => [],
                'errorCellCount' => 0,
                'errorDiagnostics' => [],
            ];
        }

        $formulaDiagnostics = [];
        $errorDiagnostics = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $ref = trim($cellElement->getAttribute('r'));
                if ($ref === '' || $this->parseCellReference($ref) === null) {
                    continue;
                }

                $valueElement = $this->firstChildElement($cellElement, 'v');
                $rawValue = $valueElement instanceof \DOMElement ? trim($valueElement->textContent) : '';
                $formula = $this->formulaMetadataForCell($cellElement);
                if ($formula !== null) {
                    $formulaDiagnostics[] = ['ref' => $ref] + $formula;
                }

                if (trim($cellElement->getAttribute('t')) === 'e' && $rawValue !== '') {
                    $errorDiagnostics[] = [
                        'ref' => $ref,
                        'code' => $rawValue,
                        'fromFormula' => $formula !== null,
                    ];
                }
            }
        }

        $sharedFormulaDiagnostics = array_filter(
            $formulaDiagnostics,
            static fn (array $diagnostic): bool => ($diagnostic['formulaType'] ?? '') === 'shared'
        );

        return [
            'formulaCellCount' => count($formulaDiagnostics),
            'formulaCachedValueCount' => count(array_filter($formulaDiagnostics, static fn (array $diagnostic): bool => $diagnostic['hasCachedValue'])),
            'sharedFormulaCellCount' => count($sharedFormulaDiagnostics),
            'sharedFormulaMasterCount' => count(array_filter($sharedFormulaDiagnostics, static fn (array $diagnostic): bool => ($diagnostic['sharedFormulaRole'] ?? null) === 'master')),
            'sharedFormulaFollowerCount' => count(array_filter($sharedFormulaDiagnostics, static fn (array $diagnostic): bool => ($diagnostic['sharedFormulaRole'] ?? null) === 'follower')),
            'formulaDiagnostics' => $formulaDiagnostics,
            'errorCellCount' => count($errorDiagnostics),
            'errorDiagnostics' => $errorDiagnostics,
        ];
    }

    /**
     * @return array{
     *     declaredCount:int|null,
     *     validationCount:int,
     *     rangeCount:int,
     *     typeCounts:array<string, int>,
     *     ranges:list<string>,
     *     validations:list<array<string, mixed>>,
     *     diagnostics:list<string>
     * }
     */
    private function parseSheetDataValidations(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return [
                'declaredCount' => null,
                'validationCount' => 0,
                'rangeCount' => 0,
                'typeCounts' => [],
                'ranges' => [],
                'validations' => [],
                'diagnostics' => [],
            ];
        }

        $dataValidations = $this->firstChildElement($root, 'dataValidations');
        if (!$dataValidations instanceof \DOMElement) {
            return [
                'declaredCount' => null,
                'validationCount' => 0,
                'rangeCount' => 0,
                'typeCounts' => [],
                'ranges' => [],
                'validations' => [],
                'diagnostics' => [],
            ];
        }

        $validations = [];
        $typeCounts = [];
        $ranges = [];
        $diagnostics = [];
        foreach ($this->childElements($dataValidations, 'dataValidation') as $validation) {
            $sqref = trim($validation->getAttribute('sqref'));
            $validationRanges = [];
            if ($sqref === '') {
                $diagnostics[] = 'data-validation-missing-sqref';
            } else {
                foreach (preg_split('/\s+/', $sqref) ?: [] as $range) {
                    $range = trim($range);
                    if ($range === '') {
                        continue;
                    }
                    if ($this->parseCellRange($range) === null && $this->parseCellReference($range) === null) {
                        $diagnostics[] = 'data-validation-invalid-range:' . $range;
                        continue;
                    }
                    if (!in_array($range, $validationRanges, true)) {
                        $validationRanges[] = $range;
                    }
                    if (!in_array($range, $ranges, true)) {
                        $ranges[] = $range;
                    }
                }
            }

            $type = $this->nonEmptyAttribute($validation, 'type') ?? 'any';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

            $validations[] = [
                'type' => $type,
                'operator' => $this->nonEmptyAttribute($validation, 'operator'),
                'errorStyle' => $this->nonEmptyAttribute($validation, 'errorStyle'),
                'imeMode' => $this->nonEmptyAttribute($validation, 'imeMode'),
                'allowBlank' => $this->booleanAttribute($validation, 'allowBlank'),
                'showDropDown' => $this->booleanAttribute($validation, 'showDropDown'),
                'showInputMessage' => $this->booleanAttribute($validation, 'showInputMessage'),
                'showErrorMessage' => $this->booleanAttribute($validation, 'showErrorMessage'),
                'sqref' => $sqref === '' ? null : $sqref,
                'rangeCount' => count($validationRanges),
                'ranges' => $validationRanges,
            ] + $this->textElementDigestMetadata($this->firstChildElement($validation, 'formula1'), 'formula1')
                + $this->textElementDigestMetadata($this->firstChildElement($validation, 'formula2'), 'formula2')
                + $this->attributeDigestMetadata($validation, 'promptTitle')
                + $this->attributeDigestMetadata($validation, 'prompt')
                + $this->attributeDigestMetadata($validation, 'errorTitle')
                + $this->attributeDigestMetadata($validation, 'error');
        }

        ksort($typeCounts, SORT_STRING);
        $declaredCount = $this->integerAttribute($dataValidations, 'count');
        if ($declaredCount !== null && $declaredCount !== count($validations)) {
            $diagnostics[] = 'data-validation-count-mismatch';
        }

        return [
            'declaredCount' => $declaredCount,
            'validationCount' => count($validations),
            'rangeCount' => count($ranges),
            'typeCounts' => $typeCounts,
            'ranges' => $ranges,
            'validations' => $validations,
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textElementDigestMetadata(?\DOMElement $element, string $prefix): array
    {
        if (!$element instanceof \DOMElement) {
            return [
                $prefix . 'Present' => false,
                $prefix . 'TextBytes' => 0,
                $prefix . 'Sha256' => null,
            ];
        }

        $text = trim($element->textContent);

        return [
            $prefix . 'Present' => true,
            $prefix . 'TextBytes' => strlen($text),
            $prefix . 'Sha256' => hash('sha256', $text),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeDigestMetadata(\DOMElement $element, string $attribute): array
    {
        $prefix = $attribute;
        if (!$element->hasAttribute($attribute)) {
            return [
                $prefix . 'Present' => false,
                $prefix . 'TextBytes' => 0,
                $prefix . 'Sha256' => null,
            ];
        }

        $text = trim($element->getAttribute($attribute));

        return [
            $prefix . 'Present' => true,
            $prefix . 'TextBytes' => strlen($text),
            $prefix . 'Sha256' => hash('sha256', $text),
        ];
    }

    /**
     * @return array{
     *     autoFilterRanges:list<string>,
     *     autoFilters:list<array<string, mixed>>,
     *     tableParts:list<array<string, mixed>>,
     *     tablePartDiagnostics:list<string>
     * }
     */
    private function parseSheetTableMetadata(ZipPackage $package, string $sheetPart, \DOMDocument $document, OpcRelationships $relationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return ['autoFilterRanges' => [], 'autoFilters' => [], 'tableParts' => [], 'tablePartDiagnostics' => []];
        }

        $autoFilterRanges = [];
        $autoFilters = [];
        $worksheetAutoFilter = $this->firstChildElement($root, 'autoFilter');
        if ($worksheetAutoFilter instanceof \DOMElement) {
            $this->appendAutoFilterRange($autoFilterRanges, $worksheetAutoFilter->getAttribute('ref'));
            $autoFilters[] = $this->parseAutoFilter($worksheetAutoFilter, 'worksheet');
        }

        $tableParts = [];
        $diagnostics = [];
        $tablePartsElement = $this->firstChildElement($root, 'tableParts');
        if (!$tablePartsElement instanceof \DOMElement) {
            return [
                'autoFilterRanges' => $autoFilterRanges,
                'autoFilters' => $autoFilters,
                'tableParts' => [],
                'tablePartDiagnostics' => [],
            ];
        }

        foreach ($this->childElements($tablePartsElement, 'tablePart') as $tablePartElement) {
            $relationshipId = $this->relationshipId($tablePartElement);
            if ($relationshipId === '') {
                $diagnostics[] = 'table-part-missing-relationship-id';
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if (!$relationship instanceof OpcRelationship) {
                $diagnostics[] = 'table-part-relationship-missing:' . $relationshipId;
                continue;
            }
            if ($relationship->isExternal()) {
                $diagnostics[] = 'table-part-external-relationship-skipped:' . $relationshipId;
                continue;
            }

            $part = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
            $available = $package->has($part);
            $tablePart = [
                'relationshipId' => $relationshipId,
                'partName' => ltrim($part, '/'),
                'available' => $available,
            ];
            if (!$available) {
                $diagnostics[] = 'table-part-missing:' . ltrim($part, '/');
                $tableParts[] = $tablePart;
                continue;
            }

            try {
                $tableDocument = $this->loadPackageXml($package, $part, 'XLSX table ' . $relationshipId . ' for ' . ltrim($sheetPart, '/'));
            } catch (\InvalidArgumentException|\RuntimeException) {
                $diagnostics[] = 'table-part-unreadable:' . ltrim($part, '/');
                $tableParts[] = $tablePart;
                continue;
            }
            $tableMetadata = $this->parseTablePart($tableDocument);
            if (($tableMetadata['autoFilterRef'] ?? null) !== null) {
                $this->appendAutoFilterRange($autoFilterRanges, (string) $tableMetadata['autoFilterRef']);
            }
            if (is_array($tableMetadata['autoFilter'] ?? null)) {
                $tableAutoFilter = $tableMetadata['autoFilter'];
                $tableAutoFilter['tableRelationshipId'] = $relationshipId;
                $tableAutoFilter['tablePartName'] = ltrim($part, '/');
                $autoFilters[] = $tableAutoFilter;
            }
            $tableParts[] = array_merge($tablePart, $tableMetadata);
        }

        return [
            'autoFilterRanges' => $autoFilterRanges,
            'autoFilters' => $autoFilters,
            'tableParts' => $tableParts,
            'tablePartDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTablePart(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'table');
        if (!$root instanceof \DOMElement) {
            return [
                'id' => null,
                'name' => null,
                'displayName' => null,
                'ref' => null,
                'autoFilterRef' => null,
                'autoFilter' => null,
                'headerRowCount' => null,
                'totalsRowCount' => null,
                'totalsRowShown' => null,
                'published' => null,
                'columnCount' => 0,
                'columns' => [],
                'columnNames' => [],
                'autoFilterColumnCount' => 0,
                'autoFilterColumns' => [],
                'tableStyleInfo' => null,
            ];
        }

        $autoFilter = $this->firstChildElement($root, 'autoFilter');
        $tableColumns = $this->firstChildElement($root, 'tableColumns');
        $columns = $tableColumns instanceof \DOMElement ? $this->parseTableColumns($tableColumns) : [];
        $autoFilterMetadata = $autoFilter instanceof \DOMElement ? $this->parseAutoFilter($autoFilter, 'table') : null;

        return [
            'id' => $this->integerAttribute($root, 'id'),
            'name' => $root->hasAttribute('name') ? $root->getAttribute('name') : null,
            'displayName' => $root->hasAttribute('displayName') ? $root->getAttribute('displayName') : null,
            'ref' => $root->hasAttribute('ref') ? $root->getAttribute('ref') : null,
            'autoFilterRef' => $autoFilter instanceof \DOMElement && trim($autoFilter->getAttribute('ref')) !== ''
                ? trim($autoFilter->getAttribute('ref'))
                : null,
            'autoFilter' => $autoFilterMetadata,
            'headerRowCount' => $this->integerAttribute($root, 'headerRowCount'),
            'totalsRowCount' => $this->integerAttribute($root, 'totalsRowCount'),
            'totalsRowShown' => $this->booleanAttribute($root, 'totalsRowShown'),
            'published' => $this->booleanAttribute($root, 'published'),
            'columnCount' => count($columns),
            'columns' => $columns,
            'columnNames' => array_values(array_filter(
                array_map(
                    static fn (array $column): ?string => is_string($column['name'] ?? null) ? $column['name'] : null,
                    $columns
                ),
                static fn (?string $name): bool => $name !== null && $name !== ''
            )),
            'autoFilterColumnCount' => is_array($autoFilterMetadata) ? (int) ($autoFilterMetadata['filterColumnCount'] ?? 0) : 0,
            'autoFilterColumns' => is_array($autoFilterMetadata['filterColumns'] ?? null) ? $autoFilterMetadata['filterColumns'] : [],
            'tableStyleInfo' => $this->parseTableStyleInfo($this->firstChildElement($root, 'tableStyleInfo')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAutoFilter(\DOMElement $autoFilter, string $source): array
    {
        $filterColumns = [];
        foreach ($this->childElements($autoFilter, 'filterColumn') as $filterColumn) {
            $filterColumns[] = $this->parseFilterColumn($filterColumn);
        }

        $ref = trim($autoFilter->getAttribute('ref'));

        return [
            'source' => $source,
            'ref' => $ref !== '' ? $ref : null,
            'filterColumnCount' => count($filterColumns),
            'filterColumns' => $filterColumns,
            'sortState' => $this->parseSortState($this->firstChildElement($autoFilter, 'sortState')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilterColumn(\DOMElement $filterColumn): array
    {
        $filters = $this->firstChildElement($filterColumn, 'filters');
        $customFilters = $this->firstChildElement($filterColumn, 'customFilters');
        $dynamicFilter = $this->firstChildElement($filterColumn, 'dynamicFilter');
        $top10 = $this->firstChildElement($filterColumn, 'top10');
        $colorFilter = $this->firstChildElement($filterColumn, 'colorFilter');
        $iconFilter = $this->firstChildElement($filterColumn, 'iconFilter');

        $details = null;
        $type = null;
        if ($filters instanceof \DOMElement) {
            $type = 'filters';
            $details = $this->parseFilters($filters);
        } elseif ($customFilters instanceof \DOMElement) {
            $type = 'customFilters';
            $details = $this->parseCustomFilters($customFilters);
        } elseif ($dynamicFilter instanceof \DOMElement) {
            $type = 'dynamicFilter';
            $details = $this->parseDynamicFilter($dynamicFilter);
        } elseif ($top10 instanceof \DOMElement) {
            $type = 'top10';
            $details = $this->parseTop10Filter($top10);
        } elseif ($colorFilter instanceof \DOMElement) {
            $type = 'colorFilter';
            $details = [
                'dxfId' => $this->integerAttribute($colorFilter, 'dxfId'),
                'cellColor' => $this->booleanAttribute($colorFilter, 'cellColor'),
            ];
        } elseif ($iconFilter instanceof \DOMElement) {
            $type = 'iconFilter';
            $details = [
                'iconSet' => trim($iconFilter->getAttribute('iconSet')) !== '' ? trim($iconFilter->getAttribute('iconSet')) : null,
                'iconId' => $this->integerAttribute($iconFilter, 'iconId'),
            ];
        }

        return [
            'colId' => $this->integerAttribute($filterColumn, 'colId'),
            'hiddenButton' => $this->booleanAttribute($filterColumn, 'hiddenButton'),
            'showButton' => $this->booleanAttribute($filterColumn, 'showButton'),
            'filterType' => $type,
            'details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilters(\DOMElement $filters): array
    {
        $values = [];
        foreach ($this->childElements($filters, 'filter') as $filter) {
            $values[] = $filter->getAttribute('val');
        }

        $dateGroups = [];
        foreach ($this->childElements($filters, 'dateGroupItem') as $dateGroupItem) {
            $dateGroups[] = [
                'year' => $this->integerAttribute($dateGroupItem, 'year'),
                'month' => $this->integerAttribute($dateGroupItem, 'month'),
                'day' => $this->integerAttribute($dateGroupItem, 'day'),
                'hour' => $this->integerAttribute($dateGroupItem, 'hour'),
                'minute' => $this->integerAttribute($dateGroupItem, 'minute'),
                'second' => $this->integerAttribute($dateGroupItem, 'second'),
                'dateTimeGrouping' => trim($dateGroupItem->getAttribute('dateTimeGrouping')) !== '' ? trim($dateGroupItem->getAttribute('dateTimeGrouping')) : null,
            ];
        }

        return [
            'blank' => $this->booleanAttribute($filters, 'blank'),
            'calendarType' => trim($filters->getAttribute('calendarType')) !== '' ? trim($filters->getAttribute('calendarType')) : null,
            'values' => $values,
            'dateGroups' => $dateGroups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCustomFilters(\DOMElement $customFilters): array
    {
        $filters = [];
        foreach ($this->childElements($customFilters, 'customFilter') as $customFilter) {
            $filters[] = [
                'operator' => trim($customFilter->getAttribute('operator')) !== '' ? trim($customFilter->getAttribute('operator')) : null,
                'value' => $customFilter->hasAttribute('val') ? $customFilter->getAttribute('val') : null,
            ];
        }

        return [
            'and' => $this->booleanAttribute($customFilters, 'and'),
            'filters' => $filters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDynamicFilter(\DOMElement $dynamicFilter): array
    {
        return [
            'type' => trim($dynamicFilter->getAttribute('type')) !== '' ? trim($dynamicFilter->getAttribute('type')) : null,
            'value' => $this->numericAttribute($dynamicFilter, 'val'),
            'maxValue' => $this->numericAttribute($dynamicFilter, 'maxVal'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTop10Filter(\DOMElement $top10): array
    {
        return [
            'top' => $this->booleanAttribute($top10, 'top'),
            'percent' => $this->booleanAttribute($top10, 'percent'),
            'value' => $this->numericAttribute($top10, 'val'),
            'filterValue' => $this->numericAttribute($top10, 'filterVal'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSortState(?\DOMElement $sortState): ?array
    {
        if (!$sortState instanceof \DOMElement) {
            return null;
        }

        $conditions = [];
        foreach ($this->childElements($sortState, 'sortCondition') as $condition) {
            $conditions[] = [
                'ref' => trim($condition->getAttribute('ref')) !== '' ? trim($condition->getAttribute('ref')) : null,
                'descending' => $this->booleanAttribute($condition, 'descending'),
                'sortBy' => trim($condition->getAttribute('sortBy')) !== '' ? trim($condition->getAttribute('sortBy')) : null,
                'customList' => trim($condition->getAttribute('customList')) !== '' ? trim($condition->getAttribute('customList')) : null,
                'dxfId' => $this->integerAttribute($condition, 'dxfId'),
            ];
        }

        return [
            'ref' => trim($sortState->getAttribute('ref')) !== '' ? trim($sortState->getAttribute('ref')) : null,
            'caseSensitive' => $this->booleanAttribute($sortState, 'caseSensitive'),
            'columnSort' => $this->booleanAttribute($sortState, 'columnSort'),
            'conditionCount' => count($conditions),
            'conditions' => $conditions,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTableColumns(\DOMElement $tableColumns): array
    {
        $columns = [];
        foreach ($this->childElements($tableColumns, 'tableColumn') as $tableColumn) {
            $columns[] = [
                'id' => $this->integerAttribute($tableColumn, 'id'),
                'name' => $tableColumn->hasAttribute('name') ? $tableColumn->getAttribute('name') : null,
                'uniqueName' => $tableColumn->hasAttribute('uniqueName') ? $tableColumn->getAttribute('uniqueName') : null,
                'totalsRowFunction' => trim($tableColumn->getAttribute('totalsRowFunction')) !== '' ? trim($tableColumn->getAttribute('totalsRowFunction')) : null,
                'totalsRowLabel' => $tableColumn->hasAttribute('totalsRowLabel') ? $tableColumn->getAttribute('totalsRowLabel') : null,
                'queryTableFieldId' => $this->integerAttribute($tableColumn, 'queryTableFieldId'),
                'dataDxfId' => $this->integerAttribute($tableColumn, 'dataDxfId'),
                'headerRowDxfId' => $this->integerAttribute($tableColumn, 'headerRowDxfId'),
                'totalsRowDxfId' => $this->integerAttribute($tableColumn, 'totalsRowDxfId'),
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseTableStyleInfo(?\DOMElement $tableStyleInfo): ?array
    {
        if (!$tableStyleInfo instanceof \DOMElement) {
            return null;
        }

        return [
            'name' => $tableStyleInfo->hasAttribute('name') ? $tableStyleInfo->getAttribute('name') : null,
            'showFirstColumn' => $this->booleanAttribute($tableStyleInfo, 'showFirstColumn'),
            'showLastColumn' => $this->booleanAttribute($tableStyleInfo, 'showLastColumn'),
            'showRowStripes' => $this->booleanAttribute($tableStyleInfo, 'showRowStripes'),
            'showColumnStripes' => $this->booleanAttribute($tableStyleInfo, 'showColumnStripes'),
        ];
    }

    private function cachedValueTypeForCell(\DOMElement $cellElement): string
    {
        $valueElement = $this->firstChildElement($cellElement, 'v');
        if (!$valueElement instanceof \DOMElement || $valueElement->textContent === '') {
            return 'missing';
        }

        $cellType = trim($cellElement->getAttribute('t'));

        return match ($cellType) {
            's' => 'shared-string',
            'inlineStr' => 'inline-string',
            'b' => 'boolean',
            'e' => 'error',
            'str' => 'formula-string',
            default => is_numeric(trim($valueElement->textContent)) ? 'number' : 'text',
        };
    }

    /**
     * @return array<string, array{url:string, title:string}>
     */
    private function parseHyperlinks(\DOMElement $worksheet, OpcRelationships $relationships, string $sheetName = '', array $sheetAnchorIds = []): array
    {
        $hyperlinksElement = $this->firstChildElement($worksheet, 'hyperlinks');
        if (!$hyperlinksElement instanceof \DOMElement) {
            return [];
        }

        $hyperlinks = [];
        foreach ($this->childElements($hyperlinksElement, 'hyperlink') as $hyperlinkElement) {
            $ref = trim($hyperlinkElement->getAttribute('ref'));
            $refs = [];
            $cellReference = $this->parseCellReference($ref);
            if ($cellReference !== null) {
                $refs[] = $ref;
            } else {
                $range = $this->parseCellRange($ref);
                if ($range !== null) {
                    for ($row = $range['firstRow']; $row <= $range['lastRow']; $row++) {
                        for ($column = $range['firstColumn']; $column <= $range['lastColumn']; $column++) {
                            $refs[] = $this->cellReferenceFromCoordinates($row, $column);
                        }
                    }
                }
            }
            if ($refs === []) {
                continue;
            }

            $url = '';
            $relationshipId = $this->relationshipId($hyperlinkElement);
            if ($relationshipId !== '') {
                $relationship = $relationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    if ($relationship->isExternal()) {
                        $preflight = $relationship->externalTargetPreflight();
                        if ($preflight['allowed']) {
                            $url = $relationship->target;
                        }
                    } else {
                        $url = ltrim(OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship)), '/');
                    }
                }
            }

            $location = trim($hyperlinkElement->getAttribute('location'));
            if ($url === '' && $location !== '') {
                $url = $this->workbookLocationUrl($location, $sheetName, $sheetAnchorIds);
            }
            if ($url === '') {
                continue;
            }

            foreach ($refs as $cellRef) {
                $hyperlinks[$cellRef] = [
                    'url' => $url,
                    'title' => trim($hyperlinkElement->getAttribute('tooltip')),
                ];
            }
        }

        return $hyperlinks;
    }

    /**
     * @param array<string,string> $sheetAnchorIds
     */
    private function workbookLocationUrl(string $location, string $currentSheetName, array $sheetAnchorIds): string
    {
        $reference = $this->parseDefinedNameReference($location);
        if ($reference !== null) {
            $targetSheetName = (string) ($reference['sheetName'] ?? '');
            if ($targetSheetName === '') {
                $targetSheetName = $currentSheetName;
            }
            if ($targetSheetName !== '' && isset($sheetAnchorIds[$targetSheetName])) {
                return '#' . $sheetAnchorIds[$targetSheetName];
            }
        }

        return '#' . $location;
    }

    /**
     * @return list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}>
     */
    private function parseMergeRegions(\DOMElement $worksheet): array
    {
        $mergeCellsElement = $this->firstChildElement($worksheet, 'mergeCells');
        if (!$mergeCellsElement instanceof \DOMElement) {
            return [];
        }

        $regions = [];
        foreach ($this->childElements($mergeCellsElement, 'mergeCell') as $mergeCellElement) {
            $range = $this->parseCellRange(trim($mergeCellElement->getAttribute('ref')));
            if ($range === null) {
                continue;
            }
            if ($range['firstRow'] === $range['lastRow'] && $range['firstColumn'] === $range['lastColumn']) {
                continue;
            }
            $regions[] = $range;
        }

        return $regions;
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @param list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}> $mergeRegions
     */
    private function applyMergeRegions(array &$cells, array $mergeRegions): void
    {
        foreach ($mergeRegions as $region) {
            $topLeftKey = $region['firstRow'] . ':' . $region['firstColumn'];
            if (!isset($cells[$topLeftKey])) {
                continue;
            }

            $cells[$topLeftKey]['colspan'] = $region['lastColumn'] - $region['firstColumn'] + 1;
            $cells[$topLeftKey]['rowspan'] = $region['lastRow'] - $region['firstRow'] + 1;

            for ($row = $region['firstRow']; $row <= $region['lastRow']; $row++) {
                for ($column = $region['firstColumn']; $column <= $region['lastColumn']; $column++) {
                    if ($row === $region['firstRow'] && $column === $region['firstColumn']) {
                        continue;
                    }

                    $cells[$row . ':' . $column] = [
                        'row' => $row,
                        'column' => $column,
                        'ref' => $this->cellReferenceFromCoordinates($row, $column),
                        'valueType' => 'empty',
                        'text' => '',
                        'bold' => false,
                        'italic' => false,
                        'empty' => true,
                        'url' => '',
                        'title' => '',
                        'colspan' => 1,
                        'rowspan' => 1,
                        'covered' => true,
                    ];
                }
            }
        }
    }

    /**
     * @return array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}|null
     */
    private function parseCellRange(string $range): ?array
    {
        if (preg_match('/^([A-Za-z]+[1-9][0-9]*):([A-Za-z]+[1-9][0-9]*)$/', $range, $matches) !== 1) {
            return null;
        }

        $first = $this->parseCellReference($matches[1]);
        $last = $this->parseCellReference($matches[2]);
        if ($first === null || $last === null) {
            return null;
        }

        return [
            'firstRow' => min($first['row'], $last['row']),
            'firstColumn' => min($first['column'], $last['column']),
            'lastRow' => max($first['row'], $last['row']),
            'lastColumn' => max($first['column'], $last['column']),
        ];
    }

    private function cellReferenceFromCoordinates(int $row, int $column): string
    {
        return $this->columnNameFromIndex($column) . $row;
    }

    private function columnNameFromIndex(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(ord('A') + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }

        return $letters;
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null || ($cell['empty'] ?? false) === true) {
                continue;
            }
            if (trim((string) ($cell['text'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function relationshipId(\DOMElement $element): string
    {
        $id = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
        if ($id !== '') {
            return $id;
        }
        if ($element->hasAttribute('r:id')) {
            return $element->getAttribute('r:id');
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === 'id') {
                return $attribute->value;
            }
        }

        return '';
    }

    private function booleanAttribute(\DOMElement $element, string $attribute): ?bool
    {
        if (!$element->hasAttribute($attribute)) {
            return null;
        }

        return in_array(strtolower(trim($element->getAttribute($attribute))), ['1', 'true', 'on'], true);
    }

    private function integerAttribute(\DOMElement $element, string $attribute): ?int
    {
        $value = trim($element->getAttribute($attribute));
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function numericAttribute(\DOMElement $element, string $attribute): int|float|null
    {
        $value = trim($element->getAttribute($attribute));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return str_contains($value, '.') || stripos($value, 'e') !== false ? (float) $value : (int) $value;
    }

    private function nonEmptyAttribute(\DOMElement $element, string $attribute): ?string
    {
        $value = trim($element->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    /**
     * @param list<string> $ranges
     */
    private function appendAutoFilterRange(array &$ranges, string $range): void
    {
        $range = trim($range);
        if ($range === '' || !($this->parseCellRange($range) !== null || $this->parseCellReference($range) !== null)) {
            return;
        }

        if (!in_array($range, $ranges, true)) {
            $ranges[] = $range;
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @return list<\DOMElement>
     */
    private function descendantElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
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

    private function firstChildIntegerText(\DOMElement $parent, string $localName): ?int
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement || preg_match('/^-?\d+$/', trim($child->textContent)) !== 1) {
            return null;
        }

        return (int) trim($child->textContent);
    }

    private function allDescendantText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === 't') {
                $text = $child->textContent;
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode('', $texts);
    }
}
