<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfOutlineExtractor
{
    /**
     * @var array<int, int>
     */
    private array $objectGenerations = [];

    /**
     * @var array<int, array<int, mixed>>
     */
    private array $objectValuesByGeneration = [];

    /**
     * @var array<int, string>
     */
    private array $objectBodies = [];

    private bool $objectSelectionHasXref = false;

    /**
     * @var array<int, bool>
     */
    private array $objectSingleTopLevelValues = [];

    /**
     * @var array{object: int, generation: int}|null
     */
    private ?array $trailerRootReference = null;

    private bool $trailerRootReferencePresent = false;

    private const VALID_DESTINATION_VIEW_NAMES = [
        'Fit' => true,
        'FitB' => true,
        'FitBH' => true,
        'FitBV' => true,
        'FitH' => true,
        'FitR' => true,
        'FitV' => true,
        'XYZ' => true,
    ];
    private const NAME_TREE_NODE_BOUNDARY_KEYS = ['Names', 'Kids', 'Limits'];
    private const DESTINATION_DICTIONARY_BOUNDARY_KEYS = ['D', 'S'];
    private const NON_OUTLINE_ITEM_TYPES = [
        'Action' => true,
        'Annot' => true,
        'Bead' => true,
        'Catalog' => true,
        'EmbeddedFile' => true,
        'Filespec' => true,
        'Font' => true,
        'Metadata' => true,
        'ObjStm' => true,
        'Outlines' => true,
        'Page' => true,
        'Pages' => true,
        'StructElem' => true,
        'StructTreeRoot' => true,
        'Thread' => true,
        'XObject' => true,
        'XRef' => true,
    ];

    private const PDF_DOC_ENCODING_OVERRIDES = [
        0x18 => 0x02d8,
        0x19 => 0x02c7,
        0x1a => 0x02c6,
        0x1b => 0x02d9,
        0x1c => 0x02dd,
        0x1d => 0x02db,
        0x1e => 0x02da,
        0x1f => 0x02dc,
        0x7f => 0xfffd,
        0x80 => 0x2022,
        0x81 => 0x2020,
        0x82 => 0x2021,
        0x83 => 0x2026,
        0x84 => 0x2014,
        0x85 => 0x2013,
        0x86 => 0x0192,
        0x87 => 0x2044,
        0x88 => 0x2039,
        0x89 => 0x203a,
        0x8a => 0x2212,
        0x8b => 0x2030,
        0x8c => 0x201e,
        0x8d => 0x201c,
        0x8e => 0x201d,
        0x8f => 0x2018,
        0x90 => 0x2019,
        0x91 => 0x201a,
        0x92 => 0x2122,
        0x93 => 0xfb01,
        0x94 => 0xfb02,
        0x95 => 0x0141,
        0x96 => 0x0152,
        0x97 => 0x0160,
        0x98 => 0x0178,
        0x99 => 0x017d,
        0x9a => 0x0131,
        0x9b => 0x0142,
        0x9c => 0x0153,
        0x9d => 0x0161,
        0x9e => 0x017e,
        0x9f => 0xfffd,
        0xa0 => 0x20ac,
    ];

    private const PAGE_ACTION_EVENT_LABELS = [
        'O' => 'page_open',
        'C' => 'page_close',
    ];

    /**
     * Native boundary for marker.cleaners.toc::get_pdf_toc when the PDF outline
     * uses named destinations that pypdfium would normally resolve for us.
     *
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    public function getPdfToc(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->catalogOutlineRootDictionary($catalog, $objects);
        if ($outlineRoot === null) {
            return [];
        }
        $outlineRootObject = $this->validReferenceObjectNumber($catalog['Outlines'] ?? null, $objects);
        if (!$this->outlineRootAllowsItemTraversal($outlineRoot, $objects, $outlineRootObject)) {
            return [];
        }

        $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
        $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;

        return $this->outlineItems(
            $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
            $objects,
            $pageIndexes,
            $actionDestinations,
            $outlineRootObject,
            $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
            max(1, $maxDepth)
        );
    }

    /**
     * Native boundary for PDF outline /S /GoToR actions. Marker receives link
     * and reference metadata from pdftext/pypdfium; this keeps remote document
     * targets reviewable without treating them as same-document page rows.
     *
     * @return list<array<string, mixed>>
     */
    public function getRemoteGoToActions(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $outlineRoot = $this->catalogOutlineRootDictionary($catalog, $objects);
        if ($outlineRoot === null) {
            return [];
        }
        $outlineRootObject = $this->validReferenceObjectNumber($catalog['Outlines'] ?? null, $objects);
        if (!$this->outlineRootAllowsItemTraversal($outlineRoot, $objects, $outlineRootObject)) {
            return [];
        }

        $destinations = $this->destinationActionReviewMap($catalog, $objects)
            + $this->destinationMap($catalog, $objects);
        $outlineReviewMetadataByObject = $this->outlineItemDocumentReviewMetadataByObject($pdfBytes);

        return $this->remoteGoToOutlineItems(
            $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
            $objects,
            $destinations,
            $outlineRootObject,
            $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
            max(1, $maxDepth),
            1,
            [],
            $outlineReviewMetadataByObject
        );
    }

    /**
     * Native boundary for catalog /OpenAction safety review. PDF viewers may
     * run these actions when the document opens; WordPress imports should
     * surface them as metadata only.
     *
     * @return list<array<string, mixed>>
     */
    public function getOpenActionReviewActions(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null || !array_key_exists('OpenAction', $catalog)) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }
        $destinations = $this->destinationActionReviewMap($catalog, $objects) + $this->destinationMap($catalog, $objects, $pageIndexes);

        $destinationAction = $this->destinationActionReviewValue($catalog['OpenAction'], $objects, $destinations);
        if ($destinationAction !== null) {
            $seen = [];
            $actions = $this->reviewActionsFromValue(
                $destinationAction['value'],
                $objects,
                $pageIndexes,
                $destinations,
                $seen
            );
            if ($actions !== []) {
                if ($destinationAction['destination_name'] !== null) {
                    foreach ($actions as &$action) {
                        $action['destination_action_name'] = $destinationAction['destination_name'];
                        if (
                            ($action['action_type'] ?? null) === 'GoTo'
                            && ($action['safety'] ?? null) === 'local-destination'
                            && ($action['destination'] ?? null) === null
                        ) {
                            $action['destination'] = $destinationAction['destination_name'];
                        }
                    }
                    unset($action);
                }

                return $actions;
            }
        }

        $action = $this->openActionReviewAction(
            $catalog['OpenAction'],
            $objects,
            $pageIndexes,
            $destinations
        );

        return $action === null ? [] : [$action];
    }

    /**
     * pypdfium exposes bookmark destination view metadata as a view mode and
     * page-position coordinates. Keep the existing getPdfToc() shape stable and
     * expose those fields through this richer review path for WordPress TOC UIs.
     *
     * @return list<array{
     *     title: string,
     *     level: int,
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }>
     */
    public function getPdfTocWithDestinationViews(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->catalogOutlineRootDictionary($catalog, $objects);
        if ($outlineRoot === null) {
            return [];
        }
        $outlineRootObject = $this->validReferenceObjectNumber($catalog['Outlines'] ?? null, $objects);
        if (!$this->outlineRootAllowsItemTraversal($outlineRoot, $objects, $outlineRootObject)) {
            return [];
        }

        $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
        $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;

        return $this->outlineItemsWithDestinationViews(
            $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
            $objects,
            $pageIndexes,
            $actionDestinations,
            $outlineRootObject,
            $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
            max(1, $maxDepth)
        );
    }

    /**
     * Review-only outline dictionary structure for WordPress TOC imports. The
     * upstream Marker TOC shape stays title/level/page based; this richer path
     * keeps PDF outline expansion state, style/color flags, and resolved
     * destination page context out of body text while preserving it for review.
     *
     * @return list<array<string, mixed>>
     */
    public function getOutlineStructureDestinationPageContext(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->catalogOutlineRootDictionary($catalog, $objects);
        if ($outlineRoot === null) {
            return [];
        }
        $outlineRootObject = $this->validReferenceObjectNumber($catalog['Outlines'] ?? null, $objects);
        if (!$this->outlineRootAllowsItemTraversal($outlineRoot, $objects, $outlineRootObject)) {
            return [];
        }

        $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
        $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $pagePresentations = $this->getPageTransitionActionMetadata($pdfBytes);
        $articleThreads = $this->articleThreadNavigationMetadata($catalog, $objects, $pageIndexes, $pageLabels);
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdfBytes);
        $outlineReviewMetadataByObject = $this->outlineItemDocumentReviewMetadataByObject($pdfBytes);

        return $this->withOutlineItemDocumentReviewMetadataRows(
            $this->outlineStructureDestinationPageContextItems(
                $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
                $objects,
                $pageIndexes,
                array_flip($pageIndexes),
                $destinations,
                $actionDestinations,
                $pageLabels,
                $this->pagePresentationsByPageIndex($pagePresentations),
                $this->articleBeadsByPageIndex($articleThreads),
                $this->pageReviewsByPageIndex($pageReviews),
                $this->taggedContentByPageIndex($pdfBytes),
                $outlineRootObject,
                $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
                max(1, $maxDepth)
            ),
            $outlineReviewMetadataByObject,
            false
        );
    }

    /**
     * @return array{
     *     source: list<string>,
     *     page_mode?: string,
     *     page_layout?: string,
     *     open_action?: array{
     *         page: int,
     *         destination: string|null,
     *         view_mode: string|null,
     *         view_position: list<float|null>,
     *         view_parameters: array<string, float|null>
     *     }
     * }
     */
    public function getCatalogPageViewMetadata(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return ['source' => []];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $metadata = ['source' => []];
        $pageMode = $this->nameValue($catalog['PageMode'] ?? null);
        if ($pageMode !== null) {
            $metadata['source'][] = 'page_mode';
            $metadata['page_mode'] = $pageMode;
        }

        $pageLayout = $this->nameValue($catalog['PageLayout'] ?? null);
        if ($pageLayout !== null) {
            $metadata['source'][] = 'page_layout';
            $metadata['page_layout'] = $pageLayout;
        }

        $destination = $this->catalogOpenActionDestination($catalog, $objects);
        if ($destination !== null) {
            $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
            $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $actionDestinations,
                $destination['name']
            );
            if ($details !== null) {
                $metadata['source'][] = 'open_action';
                $metadata['open_action'] = $details;
            }
        }

        return $metadata;
    }

    /**
     * PDF page dictionaries may carry presentation transitions and additional
     * actions that viewers can run when a page opens or closes. WordPress
     * imports keep these as review-only metadata.
     *
     * @return list<array{
     *     pnum: int,
     *     page_number: int,
     *     page_object: int,
     *     page_label: string,
     *     catalog_view?: array<string, mixed>,
     *     display_duration: float|null,
     *     transition: array{
     *         style: string|null,
     *         duration: float|null,
     *         dimension: string|null,
     *         motion: string|null,
     *         direction: float|string|null,
     *         scale: float|null,
     *         opaque_background: bool|null
     *     }|null,
     *     actions: list<array<string, mixed>>
     * }>
     */
    public function getPageTransitionActionMetadata(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }
        $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
        $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $catalogView = $this->catalogReviewContext($pdfBytes);

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $page = $this->resolveDictionary($this->refValue($pageObjectNumber), $objects);
            if ($page === null) {
                continue;
            }

            $displayDuration = $this->numericOrNullValue($this->resolveValue($page['Dur'] ?? null, $objects));
            $transition = $this->pageTransitionMetadata($page['Trans'] ?? null, $objects);
            $actions = $this->pageAdditionalActionMetadata($page['AA'] ?? null, $objects, $pageIndexes, $actionDestinations);
            if ($displayDuration === null && $transition === null && $actions === []) {
                continue;
            }

            $row = [
                'pnum' => $pnum,
                'page_number' => $pnum + 1,
                'page_object' => $pageObjectNumber,
                'page_label' => $pageLabels[$pnum] ?? (string) ($pnum + 1),
                'display_duration' => $displayDuration,
                'transition' => $transition,
                'actions' => $actions,
            ];
            if ($catalogView !== []) {
                $row['catalog_view'] = $catalogView;
            }

            $pages[] = $row;
        }

        return $pages;
    }

    /**
     * Composite navigation review for WordPress import UIs that need the
     * pypdfium-style outline destination, catalog OpenAction, page-label, and
     * transition metadata in one non-executing payload.
     *
     * @return array{
     *     source: list<string>,
     *     outline: list<array<string, mixed>>,
     *     outline_root_review?: array<string, mixed>,
     *     open_action_review_actions: list<array<string, mixed>>,
     *     outline_action_review_actions: list<array<string, mixed>>,
     *     page_presentations: list<array<string, mixed>>,
     *     page_review: list<array<string, mixed>>,
     *     article_threads?: list<array<string, mixed>>,
     *     open_action_destination?: array<string, mixed>
     * }
     */
    public function getNavigationReviewMetadata(string $pdfBytes, bool $includePageReview = true): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        $metadata = [
            'source' => [],
            'outline' => [],
            'open_action_review_actions' => [],
            'outline_action_review_actions' => [],
            'page_presentations' => [],
            'page_review' => [],
        ];
        if ($catalog === null) {
            return $metadata;
        }

        $documentOutlineReviewMetadata = $this->documentOutlineReviewMetadata($pdfBytes);
        $outlineRootReviewMetadata = $this->outlineRootDocumentReviewMetadata($documentOutlineReviewMetadata);

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            if ($outlineRootReviewMetadata !== []) {
                $metadata['source'][] = 'outline_root_review';
                $metadata['outline_root_review'] = $outlineRootReviewMetadata;
            }

            return $metadata;
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $destinations = $this->destinationMap($catalog, $objects, $pageIndexes);
        $actionDestinations = $this->destinationActionReviewMap($catalog, $objects) + $destinations;
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $pagePresentations = $this->getPageTransitionActionMetadata($pdfBytes);
        $pagePresentationsByPage = $this->pagePresentationsByPageIndex($pagePresentations);
        $taggedContentByPage = $this->taggedContentByPageIndex($pdfBytes);
        $articleThreads = $this->articleThreadNavigationMetadata($catalog, $objects, $pageIndexes, $pageLabels);
        $articleBeadsByPage = $this->articleBeadsByPageIndex($articleThreads);
        $pageReviews = $includePageReview ? (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdfBytes) : [];
        $pageReviewsByPage = $this->pageReviewsByPageIndex($pageReviews);
        $outlineReviewMetadataByObject = $this->outlineItemDocumentReviewMetadataByObjectFromOutline(
            $documentOutlineReviewMetadata
        );

        $outlineRoot = $this->catalogOutlineRootDictionary($catalog, $objects);
        if ($outlineRoot !== null) {
            if ($outlineRootReviewMetadata !== []) {
                $metadata['source'][] = 'outline_root_review';
                $metadata['outline_root_review'] = $outlineRootReviewMetadata;
            }

            $outlineRootObject = $this->validReferenceObjectNumber($catalog['Outlines'] ?? null, $objects);
            if ($this->outlineRootAllowsItemTraversal($outlineRoot, $objects, $outlineRootObject)) {
                foreach ($this->outlineStructureDestinationPageContextItems(
                    $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
                    $objects,
                    $pageIndexes,
                    array_flip($pageIndexes),
                    $destinations,
                    $actionDestinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $outlineRootObject,
                    $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
                    15
                ) as $item) {
                    $metadata['outline'][] = $this->withOutlineItemDocumentReviewMetadata($item, $outlineReviewMetadataByObject, false);
                }
                if ($metadata['outline'] !== []) {
                    $metadata['source'][] = 'outline';
                }

                $outlineActionReviews = $this->outlineActionReviewRows(
                    $this->outlineTraversalReferenceValue($outlineRoot, 'First', $outlineRootObject),
                    $objects,
                    $pageIndexes,
                    $actionDestinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $outlineRootObject,
                    $this->outlineTraversalReferenceObjectNumber($outlineRoot, 'Last', $objects, $outlineRootObject),
                    15
                );
                if ($outlineActionReviews !== []) {
                    $metadata['source'][] = 'outline_actions';
                    $metadata['outline_action_review_actions'] = $this->withOutlineItemDocumentReviewMetadataRows(
                        $outlineActionReviews,
                        $outlineReviewMetadataByObject,
                        true
                    );
                }
            }
        }

        if (array_key_exists('OpenAction', $catalog)) {
            $openActionReviews = $this->getOpenActionReviewActions($pdfBytes);
            if ($openActionReviews !== []) {
                $metadata['source'][] = 'open_action';
                $openActionDestinationContext = $this->destinationActionTargetContextFromReviewRows(
                    $openActionReviews,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                );
                foreach ($openActionReviews as $openActionReview) {
                    $row = $this->withNavigationTargetMetadata(
                        $openActionReview,
                        $pageLabels,
                        $pagePresentationsByPage,
                        $articleBeadsByPage,
                        $pageReviewsByPage,
                        $taggedContentByPage
                    );
                    if (is_string($row['destination_action_name'] ?? null)) {
                        foreach ($openActionDestinationContext as $key => $value) {
                            $row[$key] = $value;
                        }
                    }
                    if ($openActionDestinationContext !== []) {
                        $row = $this->withActionChainTargetContext($row, $openActionDestinationContext);
                    }

                    $metadata['open_action_review_actions'][] = $row;
                }
            }
        }

        if ($pagePresentations !== []) {
            $metadata['source'][] = 'page_presentations';
            $metadata['page_presentations'] = $pagePresentations;
        }

        $openActionDestination = $this->catalogOpenActionDestination($catalog, $objects);
        if ($openActionDestination !== null) {
            $details = $this->destinationViewDetails(
                $openActionDestination['value'],
                $objects,
                $pageIndexes,
                $actionDestinations,
                $openActionDestination['name']
            );
            if ($details !== null) {
                $details = $this->withNavigationTargetMetadata(
                    $details,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                );
                $metadata['open_action_destination'] = $details;
            }
        }

        if ($articleThreads !== []) {
            $metadata['source'][] = 'article_threads';
            $metadata['article_threads'] = $articleThreads;
        }

        if ($includePageReview && $pageReviews !== []) {
            $metadata['page_review'] = $pageReviews;
        }

        if ($this->metadataHasTaggedTargets($metadata)) {
            $metadata['source'][] = 'tagged_content';
        }

        if ($includePageReview && $pageReviews !== []) {
            $metadata['source'][] = 'page_review';
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogReviewContext(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $context = [];
        foreach (['page_layout', 'page_mode'] as $field) {
            if (is_string($metadata[$field] ?? null) && $metadata[$field] !== '') {
                $context[$field] = $metadata[$field];
            }
        }

        if (isset($metadata['viewer_preferences']) && is_array($metadata['viewer_preferences']) && $metadata['viewer_preferences'] !== []) {
            $context['viewer_preferences'] = $metadata['viewer_preferences'];
        }

        return $context;
    }

    /**
     * @param list<string> $pageLabels
     */
    private function pageLabelForIndex(int $pageIndex, array $pageLabels): string
    {
        return $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
    }

    /**
     * @param list<array<string, mixed>> $pagePresentations
     * @return array<int, array<string, mixed>>
     */
    private function pagePresentationsByPageIndex(array $pagePresentations): array
    {
        $indexed = [];
        foreach ($pagePresentations as $pagePresentation) {
            $pageIndex = $pagePresentation['pnum'] ?? null;
            if (is_int($pageIndex)) {
                $indexed[$pageIndex] = $pagePresentation;
            }
        }

        return $indexed;
    }

    /**
     * @param list<array<string, mixed>> $pageReviews
     * @return array<int, array<string, mixed>>
     */
    private function pageReviewsByPageIndex(array $pageReviews): array
    {
        $indexed = [];
        foreach ($pageReviews as $pageReview) {
            $pageIndex = $pageReview['pnum'] ?? null;
            if (is_int($pageIndex)) {
                $indexed[$pageIndex] = $pageReview;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @return array<string, mixed>
     */
    private function withTargetPagePresentation(array $item, array $pagePresentationsByPage): array
    {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex) || !array_key_exists($pageIndex, $pagePresentationsByPage)) {
            return $item;
        }

        $pagePresentation = $pagePresentationsByPage[$pageIndex];
        $item['target_display_duration'] = $pagePresentation['display_duration'] ?? null;
        $item['target_page_transition'] = $pagePresentation['transition'] ?? null;
        $item['target_page_actions'] = is_array($pagePresentation['actions'] ?? null)
            ? $pagePresentation['actions']
            : [];

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @return array<string, mixed>
     */
    private function withTargetPageReview(array $item, array $pageReviewsByPage): array
    {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex) || !array_key_exists($pageIndex, $pageReviewsByPage)) {
            return $item;
        }

        $item['target_page_review'] = $this->targetPageReviewMetadata($pageReviewsByPage[$pageIndex]);

        return $item;
    }

    /**
     * @param array<string, mixed> $pageReview
     * @return array<string, mixed>
     */
    private function targetPageReviewMetadata(array $pageReview): array
    {
        $metadata = [];
        foreach ([
            'pnum',
            'page_object',
            'mark_info',
            'piece_info',
            'page_associated_files',
            'mark_info_user_properties',
            'user_properties',
        ] as $key) {
            if (array_key_exists($key, $pageReview)) {
                $metadata[$key] = $pageReview[$key];
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @return array<string, mixed>
     */
    private function withNavigationTargetMetadata(
        array $item,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage
    ): array {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex)) {
            return $item;
        }

        if (!array_key_exists('page_label', $item)) {
            $item['page_label'] = $this->pageLabelForIndex($pageIndex, $pageLabels);
        }

        $item = $this->withTargetPagePresentation($item, $pagePresentationsByPage);
        $item = $this->withTargetPageReview($item, $pageReviewsByPage);
        $item = $this->withTargetTaggedContent($item, $taggedContentByPage);

        return $this->withTargetArticleBeads($item, $articleBeadsByPage);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @return array<string, mixed>
     */
    private function withTargetTaggedContent(array $item, array $taggedContentByPage): array
    {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex) || !array_key_exists($pageIndex, $taggedContentByPage)) {
            return $item;
        }

        $rows = $taggedContentByPage[$pageIndex];
        if ($rows === []) {
            return $item;
        }

        $item['target_tagged_content'] = $rows;
        $roles = [];
        foreach ($rows as $row) {
            $role = $row['role'] ?? null;
            if (is_string($role) && $role !== '') {
                $roles[$role] = $role;
            }
        }
        if ($roles !== []) {
            $item['target_structure_roles'] = array_values($roles);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @return array<string, mixed>
     */
    private function withTargetArticleBeads(array $item, array $articleBeadsByPage): array
    {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex) || !array_key_exists($pageIndex, $articleBeadsByPage)) {
            return $item;
        }

        $item['target_article_beads'] = $articleBeadsByPage[$pageIndex];

        $titles = [];
        foreach ($articleBeadsByPage[$pageIndex] as $bead) {
            $title = $bead['thread_title'] ?? null;
            if (is_string($title) && $title !== '') {
                $titles[$title] = $title;
            }
        }
        if ($titles !== []) {
            $item['target_article_thread_titles'] = array_values($titles);
        }

        return $item;
    }

    /**
     * Native boundary for PDF catalog /Threads article navigation. markerPDF
     * receives page text and navigation through pdfium/pdftext; this reduced
     * path keeps article bead chains reviewable for WordPress import without
     * executing a viewer action or promoting thread dictionaries into body text.
     *
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param list<string> $pageLabels
     * @return list<array<string, mixed>>
     */
    private function articleThreadNavigationMetadata(array $catalog, array $objects, array $pageIndexes, array $pageLabels): array
    {
        if (!array_key_exists('Threads', $catalog)) {
            return [];
        }

        $threadValues = $this->resolveArray($catalog['Threads'], $objects);
        if ($threadValues === null) {
            $threadValues = $this->resolveDictionary($catalog['Threads'], $objects) === null
                ? []
                : [$catalog['Threads']];
        }

        $threads = [];
        foreach ($threadValues as $threadIndex => $threadValue) {
            $thread = $this->resolveDictionary($threadValue, $objects);
            if ($thread === null) {
                continue;
            }

            $threadObject = $this->referenceObjectNumber($threadValue);
            $title = $this->articleThreadTitle($thread, $objects);
            $beads = $this->articleThreadBeads(
                $thread,
                $threadObject,
                (int) $threadIndex,
                $title,
                $objects,
                $pageIndexes,
                $pageLabels
            );
            if ($beads === []) {
                continue;
            }

            $threads[] = [
                'thread_index' => (int) $threadIndex,
                'thread_object' => $threadObject,
                'title' => $title,
                'bead_count' => count($beads),
                'beads' => $beads,
            ];
        }

        return $threads;
    }

    /**
     * @param array<string, mixed> $thread
     * @param array<int, mixed> $objects
     */
    private function articleThreadTitle(array $thread, array $objects): ?string
    {
        $info = $this->resolveDictionary($thread['I'] ?? null, $objects);
        if ($info === null) {
            return null;
        }

        $title = $this->stringOrNameValue($this->resolveValue($info['Title'] ?? null, $objects));

        return $title === null || trim($title) === '' ? null : $title;
    }

    /**
     * @param array<string, mixed> $thread
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param list<string> $pageLabels
     * @return list<array<string, mixed>>
     */
    private function articleThreadBeads(
        array $thread,
        ?int $threadObject,
        int $threadIndex,
        ?string $threadTitle,
        array $objects,
        array $pageIndexes,
        array $pageLabels
    ): array {
        $firstBead = $this->validReferenceObjectNumber($thread['F'] ?? null, $objects);
        if ($firstBead === null) {
            return [];
        }

        $beads = [];
        $seen = [];
        $beadObject = $firstBead;
        $beadIndex = 0;

        while (array_key_exists($beadObject, $objects) && !isset($seen[$beadObject]) && count($seen) < 128) {
            $seen[$beadObject] = true;
            $bead = $this->resolveDictionary($this->refValue($beadObject), $objects);
            if ($bead === null) {
                break;
            }

            $nextBead = $this->validReferenceObjectNumber($bead['N'] ?? null, $objects);
            $previousBead = $this->validReferenceObjectNumber($bead['V'] ?? null, $objects);
            $pageObject = $this->validReferenceObjectNumber($bead['P'] ?? null, $objects);
            $rectangle = $this->articleBeadRectangle($bead['R'] ?? null, $objects);
            if ($pageObject !== null && isset($pageIndexes[$pageObject]) && $rectangle !== null) {
                $pageIndex = $pageIndexes[$pageObject];
                $beads[] = [
                    'thread_index' => $threadIndex,
                    'thread_object' => $threadObject,
                    'thread_title' => $threadTitle,
                    'bead_index' => $beadIndex,
                    'bead_object' => $beadObject,
                    'page' => $pageIndex,
                    'page_number' => $pageIndex + 1,
                    'page_object' => $pageObject,
                    'page_label' => $this->pageLabelForIndex($pageIndex, $pageLabels),
                    'rect' => $rectangle,
                    'next_bead_object' => $nextBead,
                    'previous_bead_object' => $previousBead,
                ];
            }

            $beadIndex++;
            if ($nextBead === null || $nextBead === $firstBead || isset($seen[$nextBead])) {
                break;
            }

            $beadObject = $nextBead;
        }

        return $beads;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<float>|null
     */
    private function articleBeadRectangle(mixed $value, array $objects): ?array
    {
        $array = $this->resolveArray($value, $objects);
        if ($array === null || count($array) < 4) {
            return null;
        }

        $numbers = [];
        for ($index = 0; $index < 4; $index++) {
            $number = $this->numericOrNullValue($this->resolveValue($array[$index], $objects));
            if ($number === null) {
                return null;
            }
            $numbers[] = $number;
        }

        $left = min($numbers[0], $numbers[2]);
        $right = max($numbers[0], $numbers[2]);
        $bottom = min($numbers[1], $numbers[3]);
        $top = max($numbers[1], $numbers[3]);
        if ($right <= $left || $top <= $bottom) {
            return null;
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * @param list<array<string, mixed>> $articleThreads
     * @return array<int, list<array<string, mixed>>>
     */
    private function articleBeadsByPageIndex(array $articleThreads): array
    {
        $beadsByPage = [];
        foreach ($articleThreads as $thread) {
            $beads = $thread['beads'] ?? null;
            if (!is_array($beads)) {
                continue;
            }

            foreach ($beads as $bead) {
                if (!is_array($bead) || !is_int($bead['page'] ?? null)) {
                    continue;
                }
                $beadsByPage[$bead['page']][] = $bead;
            }
        }

        return $beadsByPage;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function taggedContentByPageIndex(string $pdfBytes): array
    {
        $taggedContentByPage = [];
        foreach ((new PdfTextExtractor())->extractTaggedContent($pdfBytes) as $row) {
            $pageIndex = $row['page_index'] ?? null;
            if (!is_int($pageIndex)) {
                continue;
            }

            $taggedContentByPage[$pageIndex][] = $row;
        }

        return $taggedContentByPage;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataHasTaggedTargets(array $metadata): bool
    {
        foreach (['outline', 'open_action_review_actions', 'outline_action_review_actions'] as $field) {
            $rows = $metadata[$field] ?? null;
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['target_tagged_content']) && $row['target_tagged_content'] !== []) {
                    return true;
                }
            }
        }

        $pageReviewRows = $metadata['page_review'] ?? null;
        if (is_array($pageReviewRows)) {
            foreach ($pageReviewRows as $row) {
                if (is_array($row) && isset($row['structure_marked_content']) && $row['structure_marked_content'] !== []) {
                    return true;
                }
            }
        }

        return isset($metadata['open_action_destination']['target_tagged_content'])
            && is_array($metadata['open_action_destination']['target_tagged_content'])
            && $metadata['open_action_destination']['target_tagged_content'] !== [];
    }

    /**
     * Document-outline metadata already applies the payload-safety checks for
     * `/SE` and `/Metadata`; navigation rows reuse those review summaries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outlineItemDocumentReviewMetadataByObject(string $pdfBytes): array
    {
        return $this->outlineItemDocumentReviewMetadataByObjectFromOutline(
            $this->documentOutlineReviewMetadata($pdfBytes)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentOutlineReviewMetadata(string $pdfBytes): array
    {
        $documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $outline = $documentMetadata['document_outline'] ?? null;

        return is_array($outline) ? $outline : [];
    }

    /**
     * @param array<string, mixed> $documentOutline
     * @return array<int, array<string, mixed>>
     */
    private function outlineItemDocumentReviewMetadataByObjectFromOutline(array $documentOutline): array
    {
        $items = $documentOutline['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $byObject = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['outline_object'] ?? null)) {
                continue;
            }

            $context = [];
            foreach ([
                'structure_element',
                'structure_element_object',
                'structure_element_raw_role',
                'structure_element_role',
                'structure_element_page',
                'structure_element_page_number',
                'structure_element_page_object',
                'structure_element_mcids',
                'structure_element_associated_file_count',
                'structure_element_boundary_review',
                'metadata_stream_review',
            ] as $key) {
                if (array_key_exists($key, $item)) {
                    $context[$key] = $item[$key];
                }
            }

            if ($context !== []) {
                $byObject[$item['outline_object']] = $context;
            }
        }

        return $byObject;
    }

    /**
     * Root `/Outlines /Metadata` is document-level navigation review metadata,
     * not an item row. Carry only the already redacted boundary summary.
     *
     * @param array<string, mixed> $outline
     * @return array<string, mixed>
     */
    private function outlineRootDocumentReviewMetadata(array $outline): array
    {
        if (
            !is_array($outline['metadata_stream_review'] ?? null)
            && !is_array($outline['outline_root_traversal_operand_boundary_review'] ?? null)
        ) {
            return [];
        }

        $context = [];
        foreach ([
            'outline_root_object',
            'first_item_object',
            'last_item_object',
            'has_children',
            'outline_count',
            'declared_visible_count',
            'descendant_count',
            'is_open',
            'is_collapsed',
            'structure_state',
            'metadata_stream_review',
            'outline_root_traversal_operand_boundary_count',
            'outline_root_traversal_operand_boundary_keys',
            'outline_root_traversal_operand_boundary_statuses',
            'outline_root_traversal_operand_boundary_trailing_reference_objects',
            'outline_root_traversal_operand_boundary_review_only',
            'outline_root_traversal_operand_boundary_payload_included',
            'outline_root_traversal_operand_boundary_navigation_promoted',
            'outline_root_traversal_operand_boundary_review',
        ] as $key) {
            if (array_key_exists($key, $outline)) {
                $context[$key] = $outline[$key];
            }
        }

        if ($context === []) {
            return [];
        }

        return [
            'source' => 'outline_root_review',
            'review_only' => true,
            'payload_included' => false,
            'visible_text_source' => false,
        ] + $context;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $outlineReviewMetadataByObject
     * @return list<array<string, mixed>>
     */
    private function withOutlineItemDocumentReviewMetadataRows(array $rows, array $outlineReviewMetadataByObject, bool $prefix): array
    {
        foreach ($rows as $index => $row) {
            if (is_array($row)) {
                $rows[$index] = $this->withOutlineItemDocumentReviewMetadata($row, $outlineReviewMetadataByObject, $prefix);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $outlineReviewMetadataByObject
     * @return array<string, mixed>
     */
    private function withOutlineItemDocumentReviewMetadata(array $row, array $outlineReviewMetadataByObject, bool $prefix): array
    {
        $outlineObject = $row['outline_object'] ?? null;
        if (!is_int($outlineObject) || !isset($outlineReviewMetadataByObject[$outlineObject])) {
            return $row;
        }

        foreach ($outlineReviewMetadataByObject[$outlineObject] as $key => $value) {
            $targetKey = $prefix ? 'outline_' . $key : $key;
            if (!array_key_exists($targetKey, $row)) {
                $row[$targetKey] = $value;
            }
        }

        return $row;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function outlineActionReviewRows(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validReferenceObjectNumber($firstItem, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            if (!$this->outlineObjectCanOwnDictionary($current)) {
                break;
            }
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }
            if (!$this->outlineItemDictionaryAllowsTraversal($dict, $objects, $current)) {
                break;
            }
            if (!$this->outlineItemParentMatches($dict, $objects, $expectedParentObject, $current)) {
                break;
            }
            if (!$this->outlineItemPrevMatches($dict, $objects, $previousSiblingObject, $current)) {
                break;
            }

            $title = $this->outlineTitleValue($dict, $objects, $current);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
                continue;
            }

            $outlineContext = $this->outlineActionStructureContext($dict, $objects, $current);
            if (array_key_exists('A', $dict) && !$this->outlineObjectDictionaryKeyHasTrailingOperands($current, 'A')) {
                $seenActions = [];
                $actions = $this->reviewActionsFromValue($dict['A'], $objects, $pageIndexes, $destinations, $seenActions);
                $seenTargetContext = [];
                $actionChainTargetContext = $this->actionChainTargetContext(
                    $dict['A'],
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $seenTargetContext
                );
                if ($this->shouldSurfaceOutlineActionRows($actions)) {
                    foreach ($actions as $action) {
                        $action = $this->withActionChainTargetContext($action, $actionChainTargetContext);
                        $row = [
                            'outline_title' => $title,
                            'outline_level' => $level,
                            'outline_object' => $current,
                        ] + $outlineContext + $action;

                        $page = $row['page'] ?? null;
                        if (is_int($page)) {
                            $row = $this->withNavigationTargetMetadata(
                                $row,
                                $pageLabels,
                                $pagePresentationsByPage,
                                $articleBeadsByPage,
                                $pageReviewsByPage,
                                $taggedContentByPage
                            );
                        }

                        $items[] = $row;
                    }
                }
            } else {
                $destination = $this->outlineDestination($dict, $objects, $current);
                $destinationAction = $this->destinationActionReviewValue($destination['value'], $objects, $destinations, $destination['name']);
                if ($destinationAction !== null) {
                    $seenTargetContext = [];
                    $destinationActionContext = $this->actionChainTargetContext(
                        $destinationAction['value'],
                        $objects,
                        $pageIndexes,
                        $destinations,
                        $pageLabels,
                        $pagePresentationsByPage,
                        $articleBeadsByPage,
                        $pageReviewsByPage,
                        $taggedContentByPage,
                        $seenTargetContext
                    );
                    if ($destinationAction['destination_name'] !== null) {
                        $details = $this->destinationViewDetails(
                            $destination['value'],
                            $objects,
                            $pageIndexes,
                            $destinations,
                            $destination['name']
                        );
                        if ($details !== null) {
                            $destinationActionContext = $this->destinationActionTargetContext(
                                $this->withNavigationTargetMetadata(
                                    $details,
                                    $pageLabels,
                                    $pagePresentationsByPage,
                                    $articleBeadsByPage,
                                    $pageReviewsByPage,
                                    $taggedContentByPage
                                )
                            );
                        }
                    }

                    $seenActions = [];
                    $actions = $this->reviewActionsFromValue($destinationAction['value'], $objects, $pageIndexes, $destinations, $seenActions);
                    if ($this->shouldSurfaceOutlineActionRows($actions)) {
                        foreach ($actions as $action) {
                            if ($destinationAction['destination_name'] !== null) {
                                $action['destination_action_name'] = $destinationAction['destination_name'];
                            }
                            $action = $this->withActionChainTargetContext($action, $destinationActionContext);

                            if (
                                ($action['action_type'] ?? null) === 'GoTo'
                                && ($action['safety'] ?? null) === 'local-destination'
                                && ($action['destination'] ?? null) === null
                                && $destinationAction['destination_name'] !== null
                            ) {
                                $action['destination'] = $destinationAction['destination_name'];
                            }

                            $row = [
                                'outline_title' => $title,
                                'outline_level' => $level,
                                'outline_object' => $current,
                            ] + $outlineContext + $action;

                            $page = $row['page'] ?? null;
                            if (is_int($page)) {
                                $row = $this->withNavigationTargetMetadata(
                                    $row,
                                    $pageLabels,
                                    $pagePresentationsByPage,
                                    $articleBeadsByPage,
                                    $pageReviewsByPage,
                                    $taggedContentByPage
                                );
                            }

                            $items[] = $row;
                        }
                    }
                }
            }

            if ($level < $maxDepth && $this->outlineItemAllowsChildTraversal($dict, $objects, $current)) {
                foreach ($this->outlineActionReviewRows(
                    $this->outlineTraversalReferenceValue($dict, 'First', $current),
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $current,
                    $this->outlineTraversalReferenceObjectNumber($dict, 'Last', $objects, $current),
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $outline
     */
    private function outlineTraversalReferenceValue(array $outline, string $key, ?int $outlineObject): mixed
    {
        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, $key)) {
            return null;
        }

        return $outline[$key] ?? null;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineTraversalReferenceObjectNumber(
        array $outline,
        string $key,
        array $objects,
        ?int $outlineObject
    ): ?int {
        return $this->validReferenceObjectNumber(
            $this->outlineTraversalReferenceValue($outline, $key, $outlineObject),
            $objects
        );
    }

    private function outlineObjectDictionaryKeyHasTrailingOperands(int $objectNumber, string $key): bool
    {
        $tokens = $this->tokens($this->objectBodies[$objectNumber] ?? '');
        if (($tokens[0] ?? null) !== '<<') {
            return false;
        }

        $selectedHasTrailingOperands = false;
        $found = false;
        $index = 1;
        $count = count($tokens);
        while ($index < $count && ($tokens[$index] ?? null) !== '>>') {
            $token = $tokens[$index] ?? null;
            if (!is_string($token) || !str_starts_with($token, '/')) {
                $index++;
                continue;
            }

            $decodedKey = $this->decodePdfName(substr($token, 1));
            $index++;
            $valueEnd = $this->pdfTokenValueEndOffset($tokens, $index);
            if ($decodedKey === $key) {
                $found = true;
                $selectedHasTrailingOperands = $this->dictionaryTokensHaveTrailingOperandsBeforeNextKey($tokens, $valueEnd);
            }
            $index = $valueEnd;
        }

        return $found && $selectedHasTrailingOperands;
    }

    /**
     * @param list<string> $tokens
     */
    private function dictionaryTokensHaveTrailingOperandsBeforeNextKey(array $tokens, int $offset): bool
    {
        for ($count = count($tokens); $offset < $count;) {
            $token = $tokens[$offset] ?? null;
            if ($token === null || $token === '>>') {
                return false;
            }
            if (is_string($token) && str_starts_with($token, '/')) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private function pdfTokenValueEndOffset(array $tokens, int $index): int
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return $index;
        }

        if ($token === '<<') {
            $index++;
            $count = count($tokens);
            while ($index < $count && ($tokens[$index] ?? null) !== '>>') {
                $key = $tokens[$index] ?? null;
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    continue;
                }
                $index = $this->pdfTokenValueEndOffset($tokens, $index);
            }

            return ($tokens[$index] ?? null) === '>>' ? $index + 1 : $index;
        }

        if ($token === '[') {
            $index++;
            $count = count($tokens);
            while ($index < $count && ($tokens[$index] ?? null) !== ']') {
                $index = $this->pdfTokenValueEndOffset($tokens, $index);
            }

            return ($tokens[$index] ?? null) === ']' ? $index + 1 : $index;
        }

        if (
            preg_match('/^[+-]?\d+$/', (string) $token) === 1
            && preg_match('/^[+-]?\d+$/', (string) ($tokens[$index + 1] ?? '')) === 1
            && ($tokens[$index + 2] ?? null) === 'R'
        ) {
            return $index + 3;
        }

        return $index + 1;
    }

    /**
     * @param array<string, mixed> $outline
     */
    private function outlineItemParentMatches(
        array $outline,
        array $objects,
        ?int $expectedParentObject,
        ?int $outlineObject
    ): bool
    {
        if (!array_key_exists('Parent', $outline)) {
            if ($expectedParentObject === null) {
                return true;
            }

            return $this->isOutlineRootObject($expectedParentObject, $objects);
        }

        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Parent')) {
            return false;
        }

        if ($expectedParentObject === null) {
            return false;
        }

        $parent = $this->validReferenceObjectNumber($outline['Parent'], $objects);

        return $parent === $expectedParentObject;
    }

    /**
     * PDF outline sibling chains expose /Next and /Prev links. Keep older
     * lightweight fixtures that omit /Prev valid, but stop on explicit
     * contradictory backlinks to avoid importing stale same-parent siblings.
     *
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineItemPrevMatches(
        array $outline,
        array $objects,
        ?int $previousSiblingObject,
        ?int $outlineObject
    ): bool
    {
        if (!array_key_exists('Prev', $outline)) {
            return true;
        }

        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Prev')) {
            return false;
        }

        $previous = $this->validReferenceObjectNumber($outline['Prev'], $objects);

        return $previous !== null && $previous === $previousSiblingObject;
    }

    /**
     * A zero `/Count` on an outline item declares no open descendants. Treat
     * contradictory `/First` links as review metadata only.
     *
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineItemAllowsChildTraversal(array $outline, array $objects, ?int $outlineObject = null): bool
    {
        if (
            $outlineObject !== null
            && (
                $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'First')
                || $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Last')
                || $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Count')
                || $this->outlineDictionaryKeyReferencesMalformedSingleValue($outline, 'Count', $objects)
            )
        ) {
            return false;
        }

        $count = $this->integerOrNullValue($this->resolveValue($outline['Count'] ?? null, $objects));

        return $count !== 0;
    }

    /**
     * A root `/Count 0` declares an empty visible outline tree. Keep root-level
     * metadata reviewable, but do not follow contradictory `/First` rows.
     *
     * @param array<string, mixed> $outlineRoot
     * @param array<int, mixed> $objects
     */
    private function outlineRootAllowsItemTraversal(array $outlineRoot, array $objects, ?int $outlineRootObject = null): bool
    {
        if (
            $outlineRootObject !== null
            && (
                $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineRootObject, 'First')
                || $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineRootObject, 'Last')
                || $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineRootObject, 'Count')
                || $this->outlineDictionaryKeyReferencesMalformedSingleValue($outlineRoot, 'Count', $objects)
            )
        ) {
            return false;
        }

        $count = $this->integerOrNullValue($this->resolveValue($outlineRoot['Count'] ?? null, $objects));

        return $count !== 0;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineTitleValue(array $outline, array $objects, ?int $outlineObject = null): ?string
    {
        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Title')) {
            return null;
        }

        $titleValue = $outline['Title'] ?? null;
        if ($this->isReferenceValue($titleValue) && !$this->referenceTargetsSingleTopLevelValue($titleValue, $objects)) {
            return null;
        }

        $title = $this->stringOrNameValue($this->resolveValue($titleValue, $objects));

        return $title === null || trim($title) === '' ? null : $title;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineDictionaryKeyReferencesMalformedSingleValue(
        array $outline,
        string $key,
        array $objects
    ): bool {
        $value = $outline[$key] ?? null;
        if (!$this->isReferenceValue($value)) {
            return false;
        }

        $objectNumber = $this->validReferenceObjectNumber($value, $objects);

        return $objectNumber !== null && !($this->objectSingleTopLevelValues[$objectNumber] ?? false);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function isOutlineRootObject(?int $objectNumber, array $objects): bool
    {
        if ($objectNumber === null) {
            return false;
        }

        $dict = $this->resolveDictionary($this->refValue($objectNumber), $objects);
        if ($dict === null) {
            return false;
        }

        return $this->isOutlineRootDictionary($dict, $objects);
    }

    /**
     * PDF outline roots are dictionaries. A stream object's leading dictionary
     * can carry `/Type /Outlines`, but its stream payload must not own the
     * document bookmark tree.
     *
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function outlineRootDictionary(mixed $value, array $objects): ?array
    {
        if ($this->isReferenceValue($value) && !$this->referenceTargetsSingleTopLevelValue($value, $objects)) {
            return null;
        }

        $dict = $this->resolveDictionary($value, $objects);
        if ($dict === null || !$this->isOutlineRootDictionary($dict, $objects)) {
            return null;
        }

        return $dict;
    }

    private function outlineObjectCanOwnDictionary(int $objectNumber): bool
    {
        return $this->objectSingleTopLevelValues[$objectNumber] ?? false;
    }

    /**
     * @param array<string, mixed> $dict
     * @param array<int, mixed> $objects
     */
    private function isOutlineRootDictionary(array $dict, array $objects): bool
    {
        if (array_key_exists('Type', $dict)) {
            return $this->nameValue($this->resolveValue($dict['Type'], $objects)) === 'Outlines';
        }

        return !array_key_exists('Title', $dict)
            && (
                array_key_exists('First', $dict)
                || array_key_exists('Last', $dict)
                || array_key_exists('Count', $dict)
            );
    }

    /**
     * Outline item dictionaries do not use `/Type` for ordinary item identity.
     * If a linked row is a typed page, annotation, action, stream carrier, or
     * other known non-outline object, do not trust its title or sibling
     * pointers as bookmark metadata.
     *
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     */
    private function outlineItemDictionaryAllowsTraversal(array $outline, array $objects, ?int $outlineObject = null): bool
    {
        if (array_key_exists('S', $outline)) {
            return false;
        }

        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Type')) {
            return false;
        }

        if (!array_key_exists('Type', $outline)) {
            return true;
        }

        $type = $this->nameValue($this->resolveValue($outline['Type'], $objects));

        return $type === null || !isset(self::NON_OUTLINE_ITEM_TYPES[$type]);
    }

    /**
     * Plain local GoTo outline actions are already represented by outline
     * destination rows; chained or non-local actions need a review-only row.
     *
     * @param list<array<string, mixed>> $actions
     */
    private function shouldSurfaceOutlineActionRows(array $actions): bool
    {
        foreach ($actions as $action) {
            if (($action['chained'] ?? false) === true) {
                return true;
            }

            if (($action['action_type'] ?? null) !== 'GoTo' || ($action['safety'] ?? null) !== 'local-destination') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function outlineActionStructureContext(array $outline, array $objects, ?int $outlineObject = null): array
    {
        $context = [
            'outline_parent_object' => $this->referenceObjectNumber($outline['Parent'] ?? null),
            'outline_previous_object' => $this->referenceObjectNumber($outline['Prev'] ?? null),
            'outline_next_object' => $this->referenceObjectNumber($outline['Next'] ?? null),
            'outline_first_child_object' => $this->referenceObjectNumber($outline['First'] ?? null),
            'outline_last_child_object' => $this->referenceObjectNumber($outline['Last'] ?? null),
        ];

        foreach ($this->outlineStructureState($outline, $objects, $outlineObject) as $key => $value) {
            $context[
                match ($key) {
                    'has_children' => 'outline_has_children',
                    'descendant_count' => 'outline_descendant_count',
                    'is_open' => 'outline_is_open',
                    'is_collapsed' => 'outline_is_collapsed',
                    'structure_state' => 'outline_structure_state',
                    default => $key,
                }
            ] = $value;
        }

        foreach ($this->outlineStyleMetadata($outline, $objects, $outlineObject) as $key => $value) {
            $context[
                match ($key) {
                    'style_flags' => 'outline_style_flags',
                    'is_italic' => 'outline_is_italic',
                    'is_bold' => 'outline_is_bold',
                    'text_color_rgb' => 'outline_text_color_rgb',
                    'text_color_hex' => 'outline_text_color_hex',
                    default => 'outline_' . $key,
                }
            ] = $value;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function withActionChainTargetContext(array $action, array $context): array
    {
        if ($context === [] || !$this->shouldApplyActionChainTargetContext($action)) {
            return $action;
        }

        foreach ($context as $key => $value) {
            if (!array_key_exists($key, $action)) {
                $action[$key] = $value;
            }
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function shouldApplyActionChainTargetContext(array $action): bool
    {
        if (($action['chained'] ?? false) === true) {
            return true;
        }

        if (($action['action_type'] ?? null) === 'Launch' && ($action['safety'] ?? null) === 'blocked-launch') {
            return true;
        }

        if (($action['action_type'] ?? null) === 'Thread' && ($action['safety'] ?? null) === 'article-thread-review') {
            return true;
        }

        return (
            ($action['action_type'] ?? null) === 'GoTo'
            && ($action['safety'] ?? null) === 'local-destination'
        ) || (
            ($action['action_type'] ?? null) === 'GoToR'
            && ($action['safety'] ?? null) === 'remote-document-review'
        );
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function destinationActionTargetContext(array $details): array
    {
        $context = [];
        if (is_int($details['page'] ?? null)) {
            $context['destination_action_target_page'] = $details['page'];
            $context['destination_action_target_page_number'] = $details['page'] + 1;
        }
        if (is_int($details['page_number'] ?? null)) {
            $context['destination_action_target_page_number'] = $details['page_number'];
        }
        if (is_int($details['page_object'] ?? null)) {
            $context['destination_action_target_page_object'] = $details['page_object'];
        }
        if (is_string($details['page_label'] ?? null)) {
            $context['destination_action_target_page_label'] = $details['page_label'];
        }
        if (array_key_exists('view_mode', $details)) {
            $context['destination_action_target_view_mode'] = $details['view_mode'];
        }
        if (is_array($details['view_position'] ?? null)) {
            $context['destination_action_target_view_position'] = $details['view_position'];
        }
        if (is_array($details['view_parameters'] ?? null)) {
            $context['destination_action_target_view_parameters'] = $details['view_parameters'];
        }
        if (array_key_exists('target_display_duration', $details)) {
            $context['destination_action_target_display_duration'] = $details['target_display_duration'];
        }
        if (array_key_exists('target_page_transition', $details)) {
            $context['destination_action_target_page_transition'] = $details['target_page_transition'];
        }
        if (is_array($details['target_page_actions'] ?? null)) {
            $context['destination_action_target_page_actions'] = $details['target_page_actions'];
        }
        if (is_array($details['target_article_beads'] ?? null)) {
            $context['destination_action_target_article_beads'] = $details['target_article_beads'];
        }
        if (is_array($details['target_article_thread_titles'] ?? null)) {
            $context['destination_action_target_article_thread_titles'] = $details['target_article_thread_titles'];
        }
        if (is_array($details['target_page_review'] ?? null)) {
            $context['destination_action_target_page_review'] = $details['target_page_review'];
        }
        if (is_array($details['target_tagged_content'] ?? null)) {
            $context['destination_action_target_tagged_content'] = $details['target_tagged_content'];
            foreach ($this->targetTaggedContentSummary($details['target_tagged_content']) as $key => $value) {
                $context['destination_action_target_' . $key] = $value;
            }
        }
        if (is_array($details['target_structure_roles'] ?? null)) {
            $context['destination_action_target_structure_roles'] = $details['target_structure_roles'];
        }
        foreach ([
            'thread_object',
            'thread_index',
            'thread_title',
            'thread_destination_type',
            'thread_destination',
            'thread_bead_object',
            'thread_bead_index',
            'thread_bead_rect',
            'thread_page_object',
        ] as $key) {
            if (array_key_exists($key, $details)) {
                $context['destination_action_target_' . $key] = $details[$key];
            }
        }

        return $context;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, list<int|string>>
     */
    private function targetTaggedContentSummary(array $rows): array
    {
        $mcids = [];
        $rawRoles = [];
        $roles = [];
        $texts = [];
        $structObjects = [];

        foreach ($rows as $row) {
            $mcid = $row['mcid'] ?? null;
            if (is_int($mcid) && !in_array($mcid, $mcids, true)) {
                $mcids[] = $mcid;
            }

            $rawRole = $row['raw_role'] ?? null;
            if (is_string($rawRole) && $rawRole !== '' && !in_array($rawRole, $rawRoles, true)) {
                $rawRoles[] = $rawRole;
            }

            $role = $row['role'] ?? null;
            if (is_string($role) && $role !== '' && !in_array($role, $roles, true)) {
                $roles[] = $role;
            }

            $text = $row['text'] ?? null;
            if (is_string($text) && $text !== '') {
                $texts[] = $text;
            }

            $structObject = $row['struct_object'] ?? null;
            if (is_int($structObject) && !in_array($structObject, $structObjects, true)) {
                $structObjects[] = $structObject;
            }
        }

        $summary = [];
        if ($mcids !== []) {
            $summary['structure_mcids'] = $mcids;
        }
        if ($rawRoles !== []) {
            $summary['structure_raw_roles'] = $rawRoles;
        }
        if ($roles !== []) {
            $summary['structure_roles'] = $roles;
        }
        if ($texts !== []) {
            $summary['structure_text'] = $texts;
        }
        if ($structObjects !== []) {
            $summary['structure_objects'] = $structObjects;
        }

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @return array<string, mixed>
     */
    private function destinationActionTargetContextFromReviewRows(
        array $actions,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage
    ): array {
        foreach ($actions as $action) {
            if (!is_int($action['page'] ?? null)) {
                continue;
            }

            return $this->destinationActionTargetContext(
                $this->withNavigationTargetMetadata(
                    $action,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                )
            );
        }

        return [];
    }

    /**
     * Resolve the first local GoTo target in an action chain so bounded /Next
     * rows can carry the same non-executing destination review context.
     *
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @param array<string, true> $seen
     * @return array<string, mixed>
     */
    private function actionChainTargetContext(
        mixed $value,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage,
        array &$seen,
        int $depth = 0
    ): array {
        if ($value === null || $depth > 20) {
            return [];
        }

        $resolved = $this->resolveValue($value, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            foreach ($array as $item) {
                $context = $this->actionChainTargetContext(
                    $item,
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $seen,
                    $depth + 1
                );
                if ($context !== []) {
                    return $context;
                }
            }

            return [];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return [];
        }

        $actionObject = $this->referenceObjectNumber($value);
        $identity = $actionObject === null ? 'dict:' . md5(serialize($dict)) : 'obj:' . $actionObject;
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $type = $this->nameValue($dict['S'] ?? null);
        if ($type === 'Thread') {
            $threadTarget = $this->threadActionTargetDetails($dict, $objects, $pageIndexes, false);
            if ($threadTarget !== null && is_int($threadTarget['page'] ?? null)) {
                return $this->destinationActionTargetContext(
                    $this->withNavigationTargetMetadata(
                        $this->threadActionDestinationDetails($threadTarget),
                        $pageLabels,
                        $pagePresentationsByPage,
                        $articleBeadsByPage,
                        $pageReviewsByPage,
                        $taggedContentByPage
                    )
                );
            }
        }

        if (
            ($type === null || $type === 'GoTo')
            && array_key_exists('D', $dict)
            && !$this->actionDictionaryValueHasTrailingOperands($value, 'D')
        ) {
            $destinationName = $this->stringOrNameValue($this->resolveValue($dict['D'], $objects));
            $details = $this->destinationViewDetails(
                $dict['D'],
                $objects,
                $pageIndexes,
                $destinations,
                $destinationName
            );
            if ($details !== null) {
                return $this->destinationActionTargetContext(
                    $this->withNavigationTargetMetadata(
                        $details,
                        $pageLabels,
                        $pagePresentationsByPage,
                        $articleBeadsByPage,
                        $pageReviewsByPage,
                        $taggedContentByPage
                    )
                );
            }

            $destinationAction = $this->destinationActionReviewValue($dict['D'], $objects, $destinations);
            if ($destinationAction !== null) {
                $context = $this->actionChainTargetContext(
                    $destinationAction['value'],
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $seen,
                    $depth + 1
                );
                if ($context !== []) {
                    return $context;
                }
            }
        }

        if (
            array_key_exists('Next', $dict)
            && !$this->actionDictionaryReferenceHasTrailingOperands($actionObject, 'Next')
        ) {
            return $this->actionChainTargetContext(
                $dict['Next'],
                $objects,
                $pageIndexes,
                $destinations,
                $pageLabels,
                $pagePresentationsByPage,
                $articleBeadsByPage,
                $pageReviewsByPage,
                $taggedContentByPage,
                $seen,
                $depth + 1
            );
        }

        return [];
    }

    /**
     * Some PDF producers place a GoTo action dictionary behind a name-tree
     * destination. PDF engines still resolve the `/D` target for outlines, but
     * WordPress import review must not drop any `/Next` followups attached to
     * that action dictionary.
     *
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{value: mixed, destination_name: string|null}|null
     */
    private function destinationActionReviewValue(
        mixed $destination,
        array $objects,
        array $destinations,
        ?string $destinationName = null,
        array $seenNames = []
    ): ?array {
        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null && $name !== '') {
            $lookupKey = $this->destinationLookupKeyForNameValue($resolved, $name);
            if (!array_key_exists($lookupKey, $destinations)) {
                $lookupKey = $name;
            }

            if (isset($seenNames[$lookupKey]) || !array_key_exists($lookupKey, $destinations)) {
                return null;
            }
            $seenNames[$lookupKey] = true;

            return $this->destinationActionReviewValue(
                $destinations[$lookupKey],
                $objects,
                $destinations,
                $this->destinationNameFromMapKey($lookupKey),
                $seenNames
            );
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        if (array_key_exists('S', $dict) || array_key_exists('Next', $dict)) {
            return [
                'value' => $destination,
                'destination_name' => $destinationName,
            ];
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        $selectedBodies = [];
        $this->objectGenerations = [];
        $this->objectValuesByGeneration = [];
        $this->objectBodies = [];
        $this->objectSelectionHasXref = false;
        $this->objectSingleTopLevelValues = [];
        $this->trailerRootReference = null;
        $this->trailerRootReferencePresent = false;
        $pdfBytes = $this->bytesThroughCurrentEof($pdfBytes);
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $values;
        }

        $xrefEntries = $this->currentXrefEntries($pdfBytes);
        $this->objectSelectionHasXref = $xrefEntries !== [];
        $candidates = [];
        foreach ($matches as $match) {
            $objectNumber = (int) $match[1][0];
            $candidates[$objectNumber][] = [
                'offset' => $match[0][1],
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
            ];
        }

        foreach ($candidates as $objectNumber => $objectCandidates) {
            if ($xrefEntries === []) {
                foreach ($objectCandidates as $objectCandidate) {
                    $tokens = $this->tokens(trim($objectCandidate['body']));
                    if ($tokens === []) {
                        continue;
                    }

                    $index = 0;
                    $this->objectValuesByGeneration[$objectNumber][$objectCandidate['generation']] = $this->parseValue($tokens, $index);
                }
            }

            $candidate = null;
            if ($xrefEntries !== [] && isset($xrefEntries[$objectNumber])) {
                $entry = $xrefEntries[$objectNumber];
                if ($entry['state'] !== 'n') {
                    continue;
                }

                foreach ($objectCandidates as $objectCandidate) {
                    if (
                        $objectCandidate['offset'] === $entry['offset']
                        && $objectCandidate['generation'] === $entry['generation']
                    ) {
                        $candidate = $objectCandidate;
                        break;
                    }
                }

                if ($candidate === null) {
                    continue;
                }
            } else {
                $candidate = $objectCandidates[array_key_last($objectCandidates)];
            }

            $tokens = $this->tokens(trim($candidate['body']));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $values[$objectNumber] = $this->parseValue($tokens, $index);
            $this->objectValuesByGeneration[$objectNumber][$candidate['generation']] = $values[$objectNumber];
            $this->objectSingleTopLevelValues[$objectNumber] = $index >= count($tokens);
            $selectedBodies[$objectNumber] = trim($candidate['body']);
            $this->objectBodies[$objectNumber] = trim($candidate['body']);
            $this->objectGenerations[$objectNumber] = $candidate['generation'];
        }

        $values = $this->withObjectStreamParsedValues($values, $selectedBodies);

        $rootSelection = $this->trailerRootReferenceSelection($pdfBytes);
        $this->trailerRootReferencePresent = $rootSelection['present'];
        $this->trailerRootReference = $rootSelection['reference'];
        $rootReference = $this->trailerRootReference;
        if (
            $rootReference !== null
            && isset($values[$rootReference['object']])
            && ($this->objectGenerations[$rootReference['object']] ?? 0) === $rootReference['generation']
        ) {
            $rootObjectNumber = $rootReference['object'];
            $values = [$rootObjectNumber => $values[$rootObjectNumber]]
                + array_diff_key($values, [$rootObjectNumber => true]);
        }

        return $values;
    }

    /**
     * @return array{present: bool, reference: array{object: int, generation: int}|null}
     */
    private function trailerRootReferenceSelection(string $pdfBytes): array
    {
        if (preg_match('/\bstartxref\s+[+-]?\d+/s', $pdfBytes) === 1) {
            $reference = $this->currentTrailerRootReference($pdfBytes);

            return [
                'present' => $reference !== null,
                'reference' => $reference,
            ];
        }

        $trailer = $this->latestTrailerDictionary($pdfBytes);
        if ($trailer === null || !array_key_exists('Root', $trailer)) {
            return [
                'present' => false,
                'reference' => null,
            ];
        }

        $root = $trailer['Root'];
        if (!$this->isReferenceValue($root)) {
            return [
                'present' => true,
                'reference' => null,
            ];
        }

        return [
            'present' => true,
            'reference' => [
                'object' => (int) $root['object'],
                'generation' => $this->referenceGeneration($root),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestTrailerDictionary(string $pdfBytes): ?array
    {
        if (preg_match_all('/\btrailer\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $trailerOffset = $matches[0][$index][1];
            $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset + strlen($matches[0][$index][0]));
            if ($dictionaryOffset === false) {
                continue;
            }

            $tokens = $this->tokens(substr($pdfBytes, $dictionaryOffset, 4096));
            if ($tokens === []) {
                continue;
            }

            $tokenIndex = 0;
            $dictionary = $this->dictionaryItems($this->parseValue($tokens, $tokenIndex));
            if ($dictionary !== null) {
                return $dictionary;
            }
        }

        return null;
    }

    /**
     * PDF 1.5 object streams may carry outline roots, items, and actions.
     * Expand non-stream members so TOC/navigation metadata follows the same
     * compressed-object boundary as the metadata and text import paths.
     *
     * @param array<int, mixed> $values
     * @param array<int, string> $objectBodies
     * @return array<int, mixed>
     */
    private function withObjectStreamParsedValues(array $values, array $objectBodies): array
    {
        $expanded = $values;
        foreach ($objectBodies as $body) {
            $memberTable = $this->decodedObjectStreamMemberTable($body, $expanded);
            if ($memberTable === null) {
                continue;
            }

            $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
            foreach ($memberTable['members'] as $member) {
                $objectNumber = $member['objectNumber'];
                if (isset($expanded[$objectNumber])) {
                    continue;
                }

                $nextOffset = $this->objectStreamMemberEndOffset(
                    $memberTable['members'],
                    $member['offset'],
                    $objectDataLength
                );
                if ($nextOffset === null || !$this->objectStreamMemberOffsetHasTokenBoundary($memberTable, $member)) {
                    continue;
                }

                $memberBody = trim(substr(
                    $memberTable['decoded'],
                    $memberTable['first'] + $member['offset'],
                    $nextOffset - $member['offset']
                ));
                if ($memberBody === '' || $this->objectStreamMemberIsTopLevelStreamObject($memberBody)) {
                    continue;
                }

                $tokens = $this->tokens($memberBody);
                if ($tokens === []) {
                    continue;
                }

                $index = 0;
                $expanded[$objectNumber] = $this->parseValue($tokens, $index);
                $this->objectSingleTopLevelValues[$objectNumber] = $index >= count($tokens);
                $this->objectBodies[$objectNumber] = $memberBody;
                $this->objectGenerations[$objectNumber] = 0;
            }
        }

        ksort($expanded, SORT_NUMERIC);
        return $expanded;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>}|null
     */
    private function decodedObjectStreamMemberTable(string $body, array $objects): ?array
    {
        $dictionary = $this->objectBodyDictionary($body);
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'ObjStm') {
            return null;
        }

        $count = $this->integerOrNullValue($this->resolveValue($dictionary['N'] ?? null, $objects));
        $first = $this->integerOrNullValue($this->resolveValue($dictionary['First'] ?? null, $objects));
        if ($count === null || $first === null || $count < 1 || $first < 0) {
            return null;
        }

        $length = $this->integerOrNullValue($this->resolveValue($dictionary['Length'] ?? null, $objects));
        $payload = $this->streamPayloadFromObjectBody($body, $length);
        if ($payload === null) {
            return null;
        }

        $decoded = $this->decodeObjectStreamPayload($payload, $dictionary, $objects);
        if ($decoded === null || $first > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $first), $count);
        if (count($members) !== $count) {
            return null;
        }

        return [
            'decoded' => $decoded,
            'first' => $first,
            'members' => $members,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function objectBodyDictionary(string $body): ?array
    {
        $dictionarySource = $body;
        if (preg_match('/\bstream(?:\r\n|\n|\r)/s', $body, $match, PREG_OFFSET_CAPTURE) === 1) {
            $dictionarySource = substr($body, 0, $match[0][1]);
        }

        $tokens = $this->tokens(trim($dictionarySource));
        if ($tokens === []) {
            return null;
        }

        $index = 0;
        return $this->dictionaryItems($this->parseValue($tokens, $index));
    }

    private function streamPayloadFromObjectBody(string $body, ?int $declaredLength): ?string
    {
        if (preg_match('/\bstream(?:\r\n|\n|\r)/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $streamStart = $match[0][1] + strlen($match[0][0]);
        if ($declaredLength !== null && $declaredLength >= 0 && $streamStart + $declaredLength <= strlen($body)) {
            return substr($body, $streamStart, $declaredLength);
        }

        $streamEnd = strpos($body, 'endstream', $streamStart);
        return $streamEnd === false ? null : substr($body, $streamStart, $streamEnd - $streamStart);
    }

    /**
     * @param array<string, mixed> $dictionary
     * @param array<int, mixed> $objects
     */
    private function decodeObjectStreamPayload(string $payload, array $dictionary, array $objects): ?string
    {
        $decoded = $payload;
        foreach ($this->objectStreamFilterNames($dictionary['Filter'] ?? null, $objects) as $filter) {
            if ($filter === 'FlateDecode' || $filter === 'Fl') {
                $inflated = @gzuncompress($decoded);
                if (!is_string($inflated)) {
                    return null;
                }
                $decoded = $inflated;
                continue;
            }

            return null;
        }

        return $decoded;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<string>
     */
    private function objectStreamFilterNames(mixed $value, array $objects): array
    {
        $resolved = $this->resolveValue($value, $objects);
        $name = $this->nameValue($resolved);
        if ($name !== null) {
            return [$name];
        }

        $array = $this->arrayItems($resolved);
        if ($array === null) {
            return [];
        }

        $names = [];
        foreach ($array as $item) {
            $name = $this->nameValue($this->resolveValue($item, $objects));
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<array{objectNumber: int, offset: int, index: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $count): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $count; $index++) {
            $objectNumber = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            $memberOffset = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            if ($objectNumber === null || $memberOffset === null) {
                return [];
            }

            if ($objectNumber > 0) {
                $members[] = [
                    'objectNumber' => $objectNumber,
                    'offset' => $memberOffset,
                    'index' => $index,
                ];
            }
        }

        if ($this->skipObjectStreamHeaderWhitespace($header, $offset) !== strlen($header)) {
            return [];
        }

        return $members;
    }

    private function readObjectStreamHeaderUnsignedInteger(string $header, int &$offset): ?int
    {
        $offset = $this->skipObjectStreamHeaderWhitespace($header, $offset);
        if (preg_match('/\G\+?(\d+)/s', $header, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);
        if ($offset < strlen($header) && !$this->isObjectStreamHeaderDelimiter($header[$offset])) {
            return null;
        }

        return (int) $match[1];
    }

    private function skipObjectStreamHeaderWhitespace(string $header, int $offset): int
    {
        $length = strlen($header);
        while ($offset < $length) {
            if ($this->isPdfWhitespace($header[$offset])) {
                $offset++;
                continue;
            }

            if ($header[$offset] === '%') {
                while ($offset < $length && !in_array($header[$offset], ["\r", "\n"], true)) {
                    $offset++;
                }
                continue;
            }

            break;
        }

        return $offset;
    }

    private function isObjectStreamHeaderDelimiter(string $char): bool
    {
        return $this->isPdfWhitespace($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     */
    private function objectStreamMemberEndOffset(array $members, int $memberOffset, int $objectDataLength): ?int
    {
        if ($memberOffset < 0 || $memberOffset >= $objectDataLength) {
            return null;
        }

        $endOffset = $objectDataLength;
        foreach ($members as $member) {
            if ($member['offset'] > $memberOffset) {
                $endOffset = min($endOffset, $member['offset']);
            }
        }

        return $endOffset > $memberOffset ? $endOffset : null;
    }

    /**
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberOffsetHasTokenBoundary(array $memberTable, array $member): bool
    {
        $absoluteOffset = $memberTable['first'] + $member['offset'];
        if ($absoluteOffset < $memberTable['first'] || $absoluteOffset >= strlen($memberTable['decoded'])) {
            return false;
        }

        if ($member['offset'] === 0) {
            return true;
        }

        $previous = $memberTable['decoded'][$absoluteOffset - 1];
        $current = $memberTable['decoded'][$absoluteOffset];

        return $this->isObjectStreamHeaderDelimiter($previous)
            && !$this->isPdfWhitespace($current);
    }

    private function objectStreamMemberIsTopLevelStreamObject(string $memberBody): bool
    {
        return preg_match('/^<<.*>>\s*stream\b/s', ltrim($memberBody)) === 1;
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function currentTrailerRootReference(string $pdfBytes): ?array
    {
        if (preg_match_all('/\bstartxref\s+([+-]?\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $latest = end($matches);
        if (!is_array($latest)) {
            return null;
        }

        $seenOffsets = [];
        return $this->trailerRootReferenceFromStartxrefOffset($pdfBytes, (int) $latest[1], $seenOffsets);
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array{object: int, generation: int}|null
     */
    private function trailerRootReferenceFromStartxrefOffset(string $pdfBytes, int $offset, array &$seenOffsets): ?array
    {
        $root = $this->trailerRootReferenceFromClassicXrefOffset($pdfBytes, $offset, $seenOffsets);
        if ($root !== null) {
            return $root;
        }

        return $this->trailerRootReferenceFromXrefStreamOffset($pdfBytes, $offset, $seenOffsets);
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array{object: int, generation: int}|null
     */
    private function trailerRootReferenceFromClassicXrefOffset(string $pdfBytes, int $offset, array &$seenOffsets): ?array
    {
        if ($offset < 0 || isset($seenOffsets[$offset]) || substr($pdfBytes, $this->skipWhitespace($pdfBytes, $offset), 4) !== 'xref') {
            return null;
        }
        $seenOffsets[$offset] = true;

        $trailer = $this->classicXrefTrailerDictionary($pdfBytes, $offset);
        if ($trailer === null) {
            return null;
        }

        $root = $trailer['Root'] ?? null;
        if ($this->isReferenceValue($root)) {
            return [
                'object' => (int) $root['object'],
                'generation' => $this->referenceGeneration($root),
            ];
        }

        $previousOffset = $this->integerOrNullValue($trailer['Prev'] ?? null);
        if ($previousOffset === null) {
            return null;
        }

        return $this->trailerRootReferenceFromStartxrefOffset($pdfBytes, $previousOffset, $seenOffsets);
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array{object: int, generation: int}|null
     */
    private function trailerRootReferenceFromXrefStreamOffset(string $pdfBytes, int $offset, array &$seenOffsets): ?array
    {
        if ($offset < 0) {
            return null;
        }

        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (isset($seenOffsets[$offset])) {
            return null;
        }

        $dictionary = $this->xrefStreamDictionaryAtOffset($pdfBytes, $offset);
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
            return null;
        }
        $seenOffsets[$offset] = true;

        $root = $dictionary['Root'] ?? null;
        if ($this->isReferenceValue($root)) {
            return [
                'object' => (int) $root['object'],
                'generation' => $this->referenceGeneration($root),
            ];
        }

        $previousOffset = $this->integerOrNullValue($dictionary['Prev'] ?? null);
        if ($previousOffset === null) {
            return null;
        }

        return $this->trailerRootReferenceFromStartxrefOffset($pdfBytes, $previousOffset, $seenOffsets);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xrefStreamDictionaryAtOffset(string $pdfBytes, int $offset): ?array
    {
        $remaining = substr($pdfBytes, $offset, 8192);
        if (preg_match('/^\d+\s+\d+\s+obj\b/s', $remaining, $headerMatch) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($remaining, '<<', strlen($headerMatch[0]));
        if ($dictionaryOffset === false) {
            return null;
        }

        $tokens = $this->tokens(substr($remaining, $dictionaryOffset, 4096));
        if ($tokens === []) {
            return null;
        }

        $index = 0;
        return $this->dictionaryItems($this->parseValue($tokens, $index));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function classicXrefTrailerDictionary(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) !== 'xref') {
            return null;
        }

        $trailerOffset = strpos($pdfBytes, 'trailer', $offset + 4);
        if ($trailerOffset === false) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        $tokens = $this->tokens(substr($pdfBytes, $dictionaryOffset, 4096));
        if ($tokens === []) {
            return null;
        }

        $index = 0;
        return $this->dictionaryItems($this->parseValue($tokens, $index));
    }

    /**
     * @return array<int, array{offset: int, generation: int, state: string}>
     */
    private function currentXrefEntries(string $pdfBytes): array
    {
        if (preg_match_all('/\bstartxref\s+([+-]?\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $latest = end($matches);
        if (!is_array($latest)) {
            return [];
        }

        $offset = (int) $latest[1];
        $entries = [];
        $seenOffsets = [];
        $this->collectXrefEntries($pdfBytes, $offset, $entries, $seenOffsets);

        return $entries;
    }

    /**
     * @param array<int, array{offset: int, generation: int, state: string}> $entries
     * @param array<int, true> $seenOffsets
     */
    private function collectXrefEntries(string $pdfBytes, int $offset, array &$entries, array &$seenOffsets): void
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return;
        }

        if (substr($pdfBytes, $offset, 4) === 'xref') {
            $this->collectClassicXrefEntries($pdfBytes, $offset, $entries, $seenOffsets);
            return;
        }

        $this->collectXrefStreamEntries($pdfBytes, $offset, $entries, $seenOffsets);
    }

    /**
     * @param array<int, array{offset: int, generation: int, state: string}> $entries
     * @param array<int, true> $seenOffsets
     */
    private function collectClassicXrefEntries(string $pdfBytes, int $offset, array &$entries, array &$seenOffsets): void
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if ($offset < 0 || isset($seenOffsets[$offset]) || substr($pdfBytes, $offset, 4) !== 'xref') {
            return;
        }
        $seenOffsets[$offset] = true;

        $position = $offset + 4;
        $length = strlen($pdfBytes);
        while ($position < $length) {
            $position = $this->skipWhitespace($pdfBytes, $position);
            if (substr($pdfBytes, $position, 7) === 'trailer') {
                break;
            }

            $remaining = substr($pdfBytes, $position);
            if (preg_match('/(\d+)\s+(\d+)/A', $remaining, $sectionMatch) !== 1) {
                break;
            }

            $firstObject = (int) $sectionMatch[1];
            $count = (int) $sectionMatch[2];
            $position += strlen($sectionMatch[0]);
            for ($index = 0; $index < $count; $index++) {
                $position = $this->skipWhitespace($pdfBytes, $position);
                $row = substr($pdfBytes, $position);
                if (preg_match('/(\d{10})\s+(\d{5})\s+([nf])\b/A', $row, $rowMatch) !== 1) {
                    break 2;
                }

                $objectNumber = $firstObject + $index;
                if (!isset($entries[$objectNumber])) {
                    $entries[$objectNumber] = [
                        'offset' => (int) $rowMatch[1],
                        'generation' => (int) $rowMatch[2],
                        'state' => $rowMatch[3],
                    ];
                }
                $position += strlen($rowMatch[0]);
            }
        }

        $trailer = $this->classicXrefTrailerDictionary($pdfBytes, $offset);
        if ($trailer === null) {
            return;
        }

        $previousOffset = $this->integerOrNullValue($trailer['Prev'] ?? null);
        $this->repairOmittedCurrentUpdateGraphRows($pdfBytes, $entries, $trailer, $previousOffset, $offset);
        if ($previousOffset !== null) {
            $this->collectXrefEntries($pdfBytes, $previousOffset, $entries, $seenOffsets);
        }
    }

    /**
     * @param array<int, array{offset: int, generation: int, state: string}> $entries
     * @param array<int, true> $seenOffsets
     */
    private function collectXrefStreamEntries(string $pdfBytes, int $offset, array &$entries, array &$seenOffsets): void
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return;
        }

        $definition = $this->directObjectDefinitionAtOffset($pdfBytes, $offset);
        if ($definition === null) {
            return;
        }

        $dictionary = $this->objectBodyDictionary($definition['body']);
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
            return;
        }
        $seenOffsets[$offset] = true;

        $length = $this->integerOrNullValue($dictionary['Length'] ?? null);
        $payload = $this->streamPayloadFromObjectBody($definition['body'], $length);
        if ($payload === null) {
            return;
        }

        $decoded = $this->decodeObjectStreamPayload($payload, $dictionary, []);
        if ($decoded === null) {
            return;
        }

        $widths = $this->xrefStreamFieldWidths($dictionary['W'] ?? null);
        if ($widths === null) {
            return;
        }

        $entrySize = array_sum($widths);
        if ($entrySize <= 0) {
            return;
        }

        $decodedEntryCount = intdiv(strlen($decoded), $entrySize);
        $fieldOffset = 0;
        foreach ($this->xrefStreamIndexRanges($dictionary, $decodedEntryCount) as $range) {
            for ($index = 0; $index < $range['count'] && $fieldOffset + $entrySize <= strlen($decoded); $index++) {
                $objectNumber = $range['first'] + $index;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if (isset($entries[$objectNumber])) {
                    continue;
                }

                if ($type === 0) {
                    $entries[$objectNumber] = [
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                        'state' => 'f',
                    ];
                    continue;
                }

                if ($type === 1) {
                    $entries[$objectNumber] = [
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                        'state' => 'n',
                    ];
                    continue;
                }

                if ($type === 2) {
                    $entries[$objectNumber] = [
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                        'state' => 'c',
                    ];
                }
            }
        }

        $previousOffset = $this->integerOrNullValue($dictionary['Prev'] ?? null);
        $this->repairOmittedCurrentUpdateGraphRows($pdfBytes, $entries, $dictionary, $previousOffset, $offset);
        if ($previousOffset !== null) {
            $this->collectXrefEntries($pdfBytes, $previousOffset, $entries, $seenOffsets);
        }
    }

    /**
     * Some incremental writers append same-generation outline/page/name/action
     * objects but omit their rows from the latest sparse xref section. Recover
     * only the graph reachable from the latest trailer root before inheriting
     * stale /Prev rows.
     *
     * @param array<int, array{offset: int, generation: int, state: string}> $entries
     * @param array<string, mixed> $sectionDictionary
     */
    private function repairOmittedCurrentUpdateGraphRows(
        string $pdfBytes,
        array &$entries,
        array $sectionDictionary,
        ?int $previousOffset,
        int $currentXrefOffset
    ): void {
        if ($previousOffset === null || $previousOffset < 0 || $currentXrefOffset <= $previousOffset) {
            return;
        }

        $root = $sectionDictionary['Root'] ?? null;
        if (!$this->isReferenceValue($root)) {
            return;
        }

        $pending = [[
            'object' => (int) $root['object'],
            'generation' => $this->referenceGeneration($root),
        ]];
        $seen = [];
        while ($pending !== [] && count($seen) < 128) {
            $reference = array_shift($pending);
            $objectNumber = $reference['object'];
            $generation = $reference['generation'];
            if ($objectNumber <= 0 || $generation < 0) {
                continue;
            }

            $key = $objectNumber . ':' . $generation;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (isset($entries[$objectNumber])) {
                $definition = $this->directObjectDefinitionForCurrentEntryBetweenOffsets(
                    $pdfBytes,
                    $objectNumber,
                    $generation,
                    $entries[$objectNumber],
                    $previousOffset,
                    $currentXrefOffset
                );
                if (
                    $definition === null
                    && ($entries[$objectNumber]['state'] ?? null) === 'n'
                    && $entries[$objectNumber]['generation'] === $generation
                ) {
                    $definition = $this->directObjectDefinitionForReferenceBetweenOffsets(
                        $pdfBytes,
                        $objectNumber,
                        $generation,
                        $previousOffset,
                        $currentXrefOffset
                    );
                    if ($definition !== null) {
                        $entries[$objectNumber] = [
                            'offset' => $definition['offset'],
                            'generation' => $definition['generation'],
                            'state' => 'n',
                        ];
                    }
                }
                if ($definition !== null) {
                    foreach ($this->directObjectReferencePairs($definition['body']) as $nestedReference) {
                        $pending[] = $nestedReference;
                    }
                }

                continue;
            }

            $definition = $this->directObjectDefinitionForReferenceBetweenOffsets(
                $pdfBytes,
                $objectNumber,
                $generation,
                $previousOffset,
                $currentXrefOffset
            );
            if ($definition === null) {
                continue;
            }

            $entries[$objectNumber] = [
                'offset' => $definition['offset'],
                'generation' => $definition['generation'],
                'state' => 'n',
            ];

            foreach ($this->directObjectReferencePairs($definition['body']) as $nestedReference) {
                $pending[] = $nestedReference;
            }
        }
    }

    /**
     * @return array{offset: int, generation: int, body: string}|null
     */
    private function directObjectDefinitionForReferenceBetweenOffsets(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $previousOffset,
        int $currentXrefOffset
    ): ?array {
        $windowStart = max(0, $previousOffset);
        $windowLength = max(0, $currentXrefOffset - $windowStart);
        if ($windowLength === 0) {
            return null;
        }

        $window = substr($pdfBytes, $windowStart, $windowLength);
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $window, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $selected = null;
        foreach ($matches as $match) {
            if ((int) $match[1][0] !== $objectNumber || (int) $match[2][0] !== $generation) {
                continue;
            }

            $offset = $windowStart + $match[0][1];
            if ($selected !== null && $offset <= $selected['offset']) {
                continue;
            }

            $selected = [
                'offset' => $offset,
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
            ];
        }

        return $selected;
    }

    /**
     * @param array{offset: int, generation: int, state: string} $entry
     * @return array{offset: int, generation: int, body: string}|null
     */
    private function directObjectDefinitionForCurrentEntryBetweenOffsets(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        array $entry,
        int $previousOffset,
        int $currentXrefOffset
    ): ?array {
        if (($entry['state'] ?? null) !== 'n' || $entry['generation'] !== $generation) {
            return null;
        }

        $offset = $entry['offset'];
        if ($offset <= $previousOffset || $offset >= $currentXrefOffset) {
            return null;
        }

        $remaining = substr($pdfBytes, $offset, max(0, $currentXrefOffset - $offset));
        $pattern = '/^' . preg_quote((string) $objectNumber, '/') . '\s+' . preg_quote((string) $generation, '/') . '\s+obj\b(.*?)\bendobj/s';
        if (preg_match($pattern, $remaining, $match) !== 1) {
            return null;
        }

        return [
            'offset' => $offset,
            'generation' => $generation,
            'body' => $match[1],
        ];
    }

    /**
     * @return list<array{object: int, generation: int}>
     */
    private function directObjectReferencePairs(string $body): array
    {
        $dictionary = $this->objectBodyDictionary($body);
        if ($dictionary !== null) {
            return $this->referencePairsFromValue([
                'pdfType' => 'dict',
                'items' => $dictionary,
            ]);
        }

        $tokens = $this->tokens(trim($body));
        if ($tokens === []) {
            return [];
        }

        $index = 0;
        return $this->referencePairsFromValue($this->parseValue($tokens, $index));
    }

    /**
     * @return list<array{object: int, generation: int}>
     */
    private function referencePairsFromValue(mixed $value, int $depth = 0): array
    {
        if ($depth > 32) {
            return [];
        }

        if ($this->isReferenceValue($value)) {
            return [[
                'object' => (int) $value['object'],
                'generation' => $this->referenceGeneration($value),
            ]];
        }

        if (!is_array($value)) {
            return [];
        }

        $children = [];
        if (($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)) {
            $children = $value['items'];
        } elseif (($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)) {
            $children = $value['items'];
        }

        $references = [];
        foreach ($children as $child) {
            foreach ($this->referencePairsFromValue($child, $depth + 1) as $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @return array{object: int, generation: int, body: string}|null
     */
    private function directObjectDefinitionAtOffset(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        $remaining = substr($pdfBytes, $offset);
        if (preg_match('/^(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $remaining, $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
            'body' => $match[3],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function xrefStreamFieldWidths(mixed $value): ?array
    {
        $items = $this->arrayItems($value);
        if ($items === null || count($items) < 3) {
            return null;
        }

        $widths = [];
        foreach (array_slice($items, 0, 3) as $item) {
            $width = $this->integerOrNullValue($item);
            if ($width === null || $width < 0) {
                return null;
            }
            $widths[] = $width;
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @param array<string, mixed> $dictionary
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(array $dictionary, int $decodedEntryCount): array
    {
        $indexItems = $this->arrayItems($dictionary['Index'] ?? null);
        if ($indexItems !== null) {
            $ranges = [];
            $consumed = 0;
            for ($index = 0, $count = count($indexItems); $index + 1 < $count; $index += 2) {
                $firstObject = $this->integerOrNullValue($indexItems[$index]);
                $entryCount = $this->integerOrNullValue($indexItems[$index + 1]);
                if ($firstObject === null || $entryCount === null || $firstObject < 0 || $entryCount < 0) {
                    continue;
                }

                $boundedCount = min($entryCount, max(0, $decodedEntryCount - $consumed));
                $ranges[] = [
                    'first' => $firstObject,
                    'count' => $boundedCount,
                ];
                $consumed += $boundedCount;
            }

            return $ranges;
        }

        $size = $this->integerOrNullValue($dictionary['Size'] ?? null);
        return [[
            'first' => 0,
            'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
        ]];
    }

    private function xrefStreamFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset] ?? "\0");
            $offset++;
        }

        return $value;
    }

    private function skipWhitespace(string $bytes, int $offset): int
    {
        $length = strlen($bytes);
        while ($offset < $length && ctype_space($bytes[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function isPdfWhitespace(string $char): bool
    {
        return $char === "\0" || ctype_space($char);
    }

    private function bytesThroughCurrentEof(string $pdfBytes): string
    {
        if (preg_match_all('/\bstartxref\s+[+-]?\d+/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) >= 1) {
            $latest = end($matches[0]);
            if (is_array($latest)) {
                $eofOffset = strpos($pdfBytes, '%%EOF', $latest[1]);
                if ($eofOffset !== false) {
                    return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
                }

                return $pdfBytes;
            }
        }

        $eofOffset = strrpos($pdfBytes, '%%EOF');
        if ($eofOffset !== false) {
            return substr($pdfBytes, 0, $eofOffset + strlen('%%EOF'));
        }

        return $pdfBytes;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects): ?array
    {
        $objectNumber = $this->catalogObjectNumber($objects);
        if ($objectNumber === null || !array_key_exists($objectNumber, $objects)) {
            return null;
        }

        $dict = $this->dictionaryItems($this->objectValueForCatalogObject($objects, $objectNumber));

        return $dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog' ? $dict : null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function catalogObjectNumber(array $objects): ?int
    {
        if ($this->trailerRootReferencePresent) {
            if ($this->trailerRootReference === null) {
                return null;
            }

            $objectNumber = $this->trailerRootReference['object'];
            $dict = $this->dictionaryItems($this->objectValueForReference(
                $objects,
                $objectNumber,
                $this->trailerRootReference['generation']
            ));

            return $dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog' ? $objectNumber : null;
        }

        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog') {
                return (int) $objectNumber;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function objectValueForCatalogObject(array $objects, int $objectNumber): mixed
    {
        if (
            $this->trailerRootReferencePresent
            && $this->trailerRootReference !== null
            && $this->trailerRootReference['object'] === $objectNumber
        ) {
            return $this->objectValueForReference(
                $objects,
                $objectNumber,
                $this->trailerRootReference['generation']
            );
        }

        return $objects[$objectNumber] ?? null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param list<string> $keys
     */
    private function catalogDictionaryHasDuplicateKeys(array $objects, array $keys): bool
    {
        $objectNumber = $this->catalogObjectNumber($objects);
        if ($objectNumber === null) {
            return false;
        }

        $value = $this->objectValueForCatalogObject($objects, $objectNumber);

        return $value !== null && $this->dictionaryHasDuplicateBoundaryKeys($value, $keys);
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogOutlineRootDictionary(array $catalog, array $objects): ?array
    {
        if (
            $this->catalogHasMalformedOutlinesOperand($objects)
            || $this->catalogDirectOutlineRootHasMalformedTraversalOperand($objects)
        ) {
            return null;
        }

        return $this->outlineRootDictionary($catalog['Outlines'] ?? null, $objects);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function catalogHasMalformedOutlinesOperand(array $objects): bool
    {
        $objectNumber = $this->catalogObjectNumber($objects);
        if ($objectNumber === null) {
            return false;
        }

        $body = $this->objectBodies[$objectNumber] ?? null;
        if ($body === null) {
            return false;
        }

        return $this->dictionaryNameHasTrailingTopLevelOperand($body, 'Outlines');
    }

    /**
     * Direct catalog /Outlines dictionaries have no root object body to inspect.
     * Keep their traversal operands under the same single-value boundary as
     * referenced outline root objects.
     *
     * @param array<int, mixed> $objects
     */
    private function catalogDirectOutlineRootHasMalformedTraversalOperand(array $objects): bool
    {
        $objectNumber = $this->catalogObjectNumber($objects);
        if ($objectNumber === null) {
            return false;
        }

        $body = $this->objectBodies[$objectNumber] ?? null;
        if ($body === null) {
            return false;
        }

        $tokens = $this->tokens($body);
        if (($tokens[0] ?? null) !== '<<') {
            return false;
        }

        $selectedValueStart = null;
        $index = 1;
        $count = count($tokens);
        while ($index < $count && ($tokens[$index] ?? null) !== '>>') {
            $key = $tokens[$index] ?? null;
            $index++;
            if (!is_string($key) || !str_starts_with($key, '/')) {
                continue;
            }

            $valueStart = $index;
            $valueEnd = $this->pdfTokenValueEndOffset($tokens, $valueStart);
            if ($this->decodePdfName(substr($key, 1)) === 'Outlines') {
                $selectedValueStart = $valueStart;
            }
            $index = $valueEnd;
        }

        if ($selectedValueStart === null || ($tokens[$selectedValueStart] ?? null) !== '<<') {
            return false;
        }

        return $this->directDictionaryHasTrailingOperandsForKeys(
            $tokens,
            $selectedValueStart,
            ['First', 'Last', 'Count']
        );
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $keys
     */
    private function directDictionaryHasTrailingOperandsForKeys(array $tokens, int $dictionaryStart, array $keys): bool
    {
        if (($tokens[$dictionaryStart] ?? null) !== '<<') {
            return false;
        }

        $keySet = array_fill_keys($keys, true);
        $index = $dictionaryStart + 1;
        $dictionaryEnd = $this->pdfTokenValueEndOffset($tokens, $dictionaryStart);
        while ($index < $dictionaryEnd && ($tokens[$index] ?? null) !== '>>') {
            $key = $tokens[$index] ?? null;
            $index++;
            if (!is_string($key) || !str_starts_with($key, '/')) {
                continue;
            }

            $valueEnd = $this->pdfTokenValueEndOffset($tokens, $index);
            if (
                isset($keySet[$this->decodePdfName(substr($key, 1))])
                && $this->dictionaryTokensHaveTrailingOperandsBeforeNextKey($tokens, $valueEnd)
            ) {
                return true;
            }

            $index = $valueEnd;
        }

        return false;
    }

    private function dictionaryNameHasTrailingTopLevelOperand(string $body, string $name): bool
    {
        $tokens = $this->tokens(trim($body));
        if (($tokens[0] ?? null) !== '<<') {
            return false;
        }

        $index = 1;
        $found = false;
        $selectedHasTrailingOperand = false;
        while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
            $key = $tokens[$index] ?? '';
            $index++;
            if (!is_string($key) || !str_starts_with($key, '/')) {
                continue;
            }

            $decodedKey = $this->decodePdfName(substr($key, 1));
            $this->parseValue($tokens, $index);
            if ($decodedKey !== $name) {
                continue;
            }

            $found = true;
            $next = $tokens[$index] ?? null;
            if ($next === null || $next === '>>') {
                $selectedHasTrailingOperand = false;
                continue;
            }

            if (is_string($next) && str_starts_with($next, '/')) {
                $selectedHasTrailingOperand = false;
                continue;
            }

            $selectedHasTrailingOperand = true;
        }

        return $found && $selectedHasTrailingOperand;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<int>
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        $catalog = $this->catalogDictionary($objects);
        if ($catalog !== null) {
        $pagesRoot = $this->validReferenceObjectNumber($catalog['Pages'] ?? null, $objects);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectNumbersFromTree($pagesRoot, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber), $objects);
        if ($dict === null) {
            return [];
        }

        $type = $this->nameValue($dict['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectNumber];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null, $objects);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidObjectNumber = $this->validReferenceObjectNumber($kid, $objects);
            if ($kidObjectNumber === null) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array<string, mixed>
     */
    private function destinationMap(array $catalog, array $objects, array $pageIndexes = []): array
    {
        $rawDestinations = [];

        $legacyDestsValue = $this->resolveValue($catalog['Dests'] ?? null, $objects);
        $legacyDuplicateNames = $this->dictionaryDuplicateKeySet($legacyDestsValue);
        $legacyDests = $this->dictionaryItems($legacyDestsValue);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                if (isset($legacyDuplicateNames[$name])) {
                    continue;
                }

                $rawDestinations[$name] = $destination;
            }
        }

        $catalogHasDuplicateNames = $this->catalogDictionaryHasDuplicateKeys($objects, ['Names']);
        $namesValue = $this->resolveValue($catalog['Names'] ?? null, $objects);
        $names = $this->dictionaryItems($namesValue);
        $nameTreeRootValue = $names['Dests'] ?? null;
        $nameTreeRootRejected = $catalogHasDuplicateNames
            || $names === null
            || isset($this->dictionaryDuplicateKeySet($namesValue)['Dests'])
            || $this->resolvedDictionaryHasDuplicateBoundaryKeys($nameTreeRootValue, $objects, self::NAME_TREE_NODE_BOUNDARY_KEYS);
        $nameTreeRoot = $nameTreeRootRejected ? null : $this->resolveDictionary($nameTreeRootValue, $objects);
        if (
            $nameTreeRoot !== null
            && !$this->nameTreeNodeReferenceHasTopLevelStream($nameTreeRootValue, $objects)
            && !$this->nameTreeNodeHasStreamCarrierType($nameTreeRoot, $objects)
        ) {
            $this->collectNameTreeDestinations($nameTreeRoot, $objects, $rawDestinations, [], [], $pageIndexes);
        }

        if ($pageIndexes === []) {
            return $rawDestinations;
        }

        $destinations = [];
        foreach ($rawDestinations as $name => $destination) {
            if ($this->destinationValueAllowedForMap($destination, $objects, $pageIndexes, $rawDestinations)) {
                $destinations[$name] = $destination;
            }
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function destinationActionReviewMap(array $catalog, array $objects): array
    {
        $destinations = [];

        $legacyDestsValue = $this->resolveValue($catalog['Dests'] ?? null, $objects);
        $legacyDuplicateNames = $this->dictionaryDuplicateKeySet($legacyDestsValue);
        $legacyDests = $this->dictionaryItems($legacyDestsValue);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                if (isset($legacyDuplicateNames[$name])) {
                    continue;
                }

                if ($this->destinationActionValueAllowedForMap($destination, $objects)) {
                    $destinations[$name] = $destination;
                }
            }
        }

        $catalogHasDuplicateNames = $this->catalogDictionaryHasDuplicateKeys($objects, ['Names']);
        $namesValue = $this->resolveValue($catalog['Names'] ?? null, $objects);
        $names = $this->dictionaryItems($namesValue);
        $nameTreeRootValue = $names['Dests'] ?? null;
        $nameTreeRootRejected = $catalogHasDuplicateNames
            || $names === null
            || isset($this->dictionaryDuplicateKeySet($namesValue)['Dests'])
            || $this->resolvedDictionaryHasDuplicateBoundaryKeys($nameTreeRootValue, $objects, self::NAME_TREE_NODE_BOUNDARY_KEYS);
        $nameTreeRoot = $nameTreeRootRejected ? null : $this->resolveDictionary($nameTreeRootValue, $objects);
        if (
            $nameTreeRoot !== null
            && !$this->nameTreeNodeReferenceHasTopLevelStream($nameTreeRootValue, $objects)
            && !$this->nameTreeNodeHasStreamCarrierType($nameTreeRoot, $objects)
        ) {
            $this->collectNameTreeActionDestinations($nameTreeRoot, $objects, $destinations);
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $activeLimits
     * @param array<int, int> $pageIndexes
     */
    private function collectNameTreeDestinations(array $node, array $objects, array &$destinations, array $seen = [], array $activeLimits = [], array $pageIndexes = []): void
    {
        if ($this->dictionaryHasDuplicateBoundaryKeys($node, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
            return;
        }
        if ($this->nameTreeNodeHasStreamCarrierType($node, $objects)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedIndirectArrayOperand($node, $objects)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedKidsOperand($node, $objects)) {
            return;
        }

        $inheritedLimits = $activeLimits;
        $kids = $this->resolveArray($node['Kids'] ?? null, $objects);
        $nodeLimits = $this->nameTreeLimits($node, $objects);
        if ($nodeLimits === null && $activeLimits === [] && array_key_exists('Limits', $node)) {
            return;
        }
        if ($nodeLimits !== null) {
            if ($this->nameTreeLimitStackDisjointFromNode($activeLimits, $nodeLimits)) {
                $names = $this->resolveArray($node['Names'] ?? null, $objects);
                if (
                    ($kids ?? []) !== []
                    || $names === null
                    || !$this->nameTreeLimitsMatchAnyPairKey($names, $objects, [$nodeLimits])
                ) {
                    return;
                }
            }

            $activeLimits = $this->nameTreeEffectiveLimitStack($activeLimits, $nodeLimits);
        }

        $names = $this->resolveArray($node['Names'] ?? null, $objects);
        if (($kids === null || $kids === []) && $names !== null) {
            $entryLimits = $inheritedLimits === [] || $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $activeLimits)
                ? $activeLimits
                : $inheritedLimits;

            for ($index = 0, $count = count($names); $index + 1 < $count;) {
                $name = $this->destinationNameDetails($names[$index], $objects);
                if ($name === null) {
                    $index++;
                    continue;
                }

                if ($this->nameTreeStringValueIsMissingBeforePair($names, $index, $objects)) {
                    $index++;
                    continue;
                }

                if (
                    $this->nameWithinNameTreeLimits($name['text'], $entryLimits, $name['bytes'])
                    && !$this->nameTreeValueHasUnbracketedDestinationViewTail($names, $index + 1, $objects, $pageIndexes)
                    && $this->destinationValueAllowedForMap($names[$index + 1], $objects, $pageIndexes)
                ) {
                    $this->addNameTreeDestinationMapEntry($destinations, $name, $names[$index + 1]);
                }
                $index += 2;
            }
        }

        if ($kids === null) {
            return;
        }

        foreach ($this->destinationNameTreeKidsSortedByLimits($kids, $objects, $activeLimits) as $kid) {
            $objectNumber = $this->validReferenceObjectNumber($kid, $objects);
            if ($objectNumber === null) {
                continue;
            }

            $seenKey = $objectNumber . ':' . $this->referenceGeneration($kid);
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            if ($this->nameTreeNodeReferenceHasTopLevelStream($kid, $objects)) {
                continue;
            }

            if ($this->resolvedDictionaryHasDuplicateBoundaryKeys($kid, $objects, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
                continue;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $objects, $destinations, $seen, $activeLimits, $pageIndexes);
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $activeLimits
     */
    private function collectNameTreeActionDestinations(array $node, array $objects, array &$destinations, array $seen = [], array $activeLimits = []): void
    {
        if ($this->dictionaryHasDuplicateBoundaryKeys($node, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
            return;
        }
        if ($this->nameTreeNodeHasStreamCarrierType($node, $objects)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedIndirectArrayOperand($node, $objects)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedKidsOperand($node, $objects)) {
            return;
        }

        $inheritedLimits = $activeLimits;
        $kids = $this->resolveArray($node['Kids'] ?? null, $objects);
        $nodeLimits = $this->nameTreeLimits($node, $objects);
        if ($nodeLimits === null && $activeLimits === [] && array_key_exists('Limits', $node)) {
            return;
        }
        if ($nodeLimits !== null) {
            if ($this->nameTreeLimitStackDisjointFromNode($activeLimits, $nodeLimits)) {
                $names = $this->resolveArray($node['Names'] ?? null, $objects);
                if (
                    ($kids ?? []) !== []
                    || $names === null
                    || !$this->nameTreeLimitsMatchAnyPairKey($names, $objects, [$nodeLimits])
                ) {
                    return;
                }
            }

            $activeLimits = $this->nameTreeEffectiveLimitStack($activeLimits, $nodeLimits);
        }

        $names = $this->resolveArray($node['Names'] ?? null, $objects);
        if (($kids === null || $kids === []) && $names !== null) {
            $entryLimits = $inheritedLimits === [] || $this->nameTreeLimitsMatchAnyPairKey($names, $objects, $activeLimits)
                ? $activeLimits
                : $inheritedLimits;

            for ($index = 0, $count = count($names); $index + 1 < $count;) {
                $name = $this->destinationNameDetails($names[$index], $objects);
                if ($name === null) {
                    $index++;
                    continue;
                }

                if ($this->nameTreeStringValueIsMissingBeforePair($names, $index, $objects)) {
                    $index++;
                    continue;
                }

                if (
                    $this->nameWithinNameTreeLimits($name['text'], $entryLimits, $name['bytes'])
                    && $this->destinationActionValueAllowedForMap($names[$index + 1], $objects)
                ) {
                    $this->addNameTreeDestinationMapEntry($destinations, $name, $names[$index + 1]);
                }
                $index += 2;
            }
        }

        if ($kids === null) {
            return;
        }

        foreach ($this->destinationNameTreeKidsSortedByLimits($kids, $objects, $activeLimits) as $kid) {
            $objectNumber = $this->validReferenceObjectNumber($kid, $objects);
            if ($objectNumber === null) {
                continue;
            }

            $seenKey = $objectNumber . ':' . $this->referenceGeneration($kid);
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            if ($this->nameTreeNodeReferenceHasTopLevelStream($kid, $objects)) {
                continue;
            }

            if ($this->resolvedDictionaryHasDuplicateBoundaryKeys($kid, $objects, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
                continue;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if ($child !== null) {
                $this->collectNameTreeActionDestinations($child, $objects, $destinations, $seen, $activeLimits);
            }
        }
    }

    /**
     * @param list<mixed> $kids
     * @param array<int, mixed> $objects
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $activeLimits
     * @return list<mixed>
     */
    private function destinationNameTreeKidsSortedByLimits(array $kids, array $objects, array $activeLimits): array
    {
        if (count($kids) < 2) {
            return $kids;
        }

        $kidNodes = [];
        $boundedNodes = [];
        foreach ($kids as $order => $kid) {
            $node = [
                'kid' => $kid,
                'limits' => null,
                'order' => $order,
                'bounded' => false,
            ];
            if ($this->validReferenceObjectNumber($kid, $objects) === null) {
                $kidNodes[] = $node;
                continue;
            }
            if ($this->nameTreeNodeReferenceHasTopLevelStream($kid, $objects)) {
                $kidNodes[] = $node;
                continue;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if (
                $child === null
                || $this->nameTreeNodeHasStreamCarrierType($child, $objects)
                || $this->nameTreeNodeHasMalformedIndirectArrayOperand($child, $objects)
            ) {
                $kidNodes[] = $node;
                continue;
            }

            $localLimits = $this->nameTreeLimits($child, $objects);
            $childLimitStack = $localLimits === null
                ? $activeLimits
                : $this->nameTreeEffectiveLimitStack($activeLimits, $localLimits);
            $limits = $this->nameTreeMergedLimits($childLimitStack);
            $node = [
                'kid' => $kid,
                'limits' => $limits,
                'order' => $order,
                'bounded' => $localLimits !== null && $limits !== null,
            ];
            $kidNodes[] = $node;
            if ($node['bounded']) {
                $boundedNodes[] = $node;
            }
        }

        if (count($boundedNodes) < 2) {
            return $kids;
        }

        usort(
            $boundedNodes,
            static function (array $left, array $right): int {
                return strcmp($left['limits']['lower_bytes'], $right['limits']['lower_bytes'])
                    ?: $left['order'] <=> $right['order'];
            }
        );

        $sortedKids = [];
        $boundedOffset = 0;
        foreach ($kidNodes as $node) {
            if (!$node['bounded']) {
                $sortedKids[] = $node['kid'];
                continue;
            }

            $sortedKids[] = $boundedNodes[$boundedOffset]['kid'];
            ++$boundedOffset;
        }

        return $sortedKids;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     */
    private function nameTreeNodeHasStreamCarrierType(array $node, array $objects): bool
    {
        $type = $this->nameValue($this->resolveValue($node['Type'] ?? null, $objects));

        return in_array($type, ['ObjStm', 'XRef', 'Metadata', 'EmbeddedFile', 'XObject'], true);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function nameTreeNodeHasMalformedKidsOperand(array $node, array $objects): bool
    {
        return array_key_exists('Kids', $node)
            && $this->resolveArray($node['Kids'], $objects) === null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     */
    private function nameTreeNodeHasMalformedIndirectArrayOperand(array $node, array $objects): bool
    {
        foreach (self::NAME_TREE_NODE_BOUNDARY_KEYS as $key) {
            if (!array_key_exists($key, $node) || !$this->isReferenceValue($node[$key])) {
                continue;
            }

            if (
                $this->resolveArray($node[$key], $objects) === null
                || !$this->referenceTargetsSingleTopLevelValue($node[$key], $objects)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function nameTreeNodeReferenceHasTopLevelStream(mixed $value, array $objects): bool
    {
        $objectNumber = $this->validReferenceObjectNumber($value, $objects);
        if ($objectNumber === null) {
            return false;
        }

        $body = $this->objectBodies[$objectNumber] ?? null;
        if ($body === null) {
            return false;
        }

        return $this->objectBodyHasTopLevelStream($body);
    }

    private function objectBodyHasTopLevelStream(string $body): bool
    {
        $tokens = $this->tokens(trim($body));
        if (($tokens[0] ?? null) !== '<<') {
            return false;
        }

        $index = 0;
        $value = $this->parseValue($tokens, $index);

        return $this->dictionaryItems($value) !== null && ($tokens[$index] ?? null) === 'stream';
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     */
    private function destinationValueAllowedForMap(mixed $value, array $objects, array $pageIndexes, array $destinations = []): bool
    {
        if ($pageIndexes === []) {
            return true;
        }
        if ($this->destinationValueIsStreamCarrier($value, $objects)) {
            return false;
        }

        $resolved = $this->resolveValue($value, $objects);
        if ($this->stringOrNameValue($resolved) !== null) {
            return true;
        }

        return $this->destinationViewDetails($value, $objects, $pageIndexes, $destinations, null) !== null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function destinationActionValueAllowedForMap(mixed $value, array $objects): bool
    {
        if ($this->destinationValueIsStreamCarrier($value, $objects)) {
            return false;
        }

        $resolved = $this->resolveValue($value, $objects);
        if ($this->stringOrNameValue($resolved) !== null) {
            return true;
        }

        $dict = $this->dictionaryItems($resolved);

        return $dict !== null && (array_key_exists('S', $dict) || array_key_exists('Next', $dict));
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function destinationValueIsStreamCarrier(mixed $value, array $objects): bool
    {
        if ($this->nameTreeNodeReferenceHasTopLevelStream($value, $objects)) {
            return true;
        }

        $dictionary = $this->dictionaryItems($this->resolveValue($value, $objects));

        return $dictionary !== null && $this->nameTreeNodeHasStreamCarrierType($dictionary, $objects);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeLimits(array $node, array $objects): ?array
    {
        $limits = $this->resolveArray($node['Limits'] ?? null, $objects);
        if ($limits === null || count($limits) !== 2) {
            return null;
        }
        if (
            ($this->isReferenceValue($limits[0]) && !$this->referenceTargetsSingleTopLevelValue($limits[0], $objects))
            || ($this->isReferenceValue($limits[1]) && !$this->referenceTargetsSingleTopLevelValue($limits[1], $objects))
        ) {
            return null;
        }

        $lower = $this->destinationNameDetails($limits[0], $objects);
        $upper = $this->destinationNameDetails($limits[1], $objects);
        if ($lower === null || $upper === null || strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return null;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $activeLimits
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string} $nodeLimits
     * @return list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}>
     */
    private function nameTreeEffectiveLimitStack(array $activeLimits, array $nodeLimits): array
    {
        if ($activeLimits === []) {
            return [$nodeLimits];
        }

        $inherited = $this->nameTreeMergedLimits($activeLimits);
        if ($inherited === null) {
            return $activeLimits;
        }

        $lower = strcmp($nodeLimits['lower_bytes'], $inherited['lower_bytes']) < 0
            ? ['text' => $inherited['lower'], 'bytes' => $inherited['lower_bytes']]
            : ['text' => $nodeLimits['lower'], 'bytes' => $nodeLimits['lower_bytes']];
        $upper = strcmp($nodeLimits['upper_bytes'], $inherited['upper_bytes']) > 0
            ? ['text' => $inherited['upper'], 'bytes' => $inherited['upper_bytes']]
            : ['text' => $nodeLimits['upper'], 'bytes' => $nodeLimits['upper_bytes']];

        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return $activeLimits;
        }

        return [[
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ]];
    }

    /**
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $activeLimits
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string} $nodeLimits
     */
    private function nameTreeLimitStackDisjointFromNode(array $activeLimits, array $nodeLimits): bool
    {
        $inherited = $this->nameTreeMergedLimits($activeLimits);
        if ($inherited === null) {
            return false;
        }

        $lowerBytes = strcmp($nodeLimits['lower_bytes'], $inherited['lower_bytes']) < 0
            ? $inherited['lower_bytes']
            : $nodeLimits['lower_bytes'];
        $upperBytes = strcmp($nodeLimits['upper_bytes'], $inherited['upper_bytes']) > 0
            ? $inherited['upper_bytes']
            : $nodeLimits['upper_bytes'];

        return strcmp($lowerBytes, $upperBytes) > 0;
    }

    /**
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $limits
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeMergedLimits(array $limits): ?array
    {
        $merged = $limits[0] ?? null;
        if ($merged === null) {
            return null;
        }

        for ($index = 1, $count = count($limits); $index < $count; $index++) {
            $limit = $limits[$index];
            $lower = strcmp($limit['lower_bytes'], $merged['lower_bytes']) < 0
                ? ['text' => $merged['lower'], 'bytes' => $merged['lower_bytes']]
                : ['text' => $limit['lower'], 'bytes' => $limit['lower_bytes']];
            $upper = strcmp($limit['upper_bytes'], $merged['upper_bytes']) > 0
                ? ['text' => $merged['upper'], 'bytes' => $merged['upper_bytes']]
                : ['text' => $limit['upper'], 'bytes' => $limit['upper_bytes']];

            if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
                return null;
            }

            $merged = [
                'lower' => $lower['text'],
                'upper' => $upper['text'],
                'lower_bytes' => $lower['bytes'],
                'upper_bytes' => $upper['bytes'],
            ];
        }

        return $merged;
    }

    /**
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $limits
     */
    private function nameWithinNameTreeLimits(string $name, array $limits, ?string $nameBytes = null): bool
    {
        $candidate = $nameBytes ?? $name;
        $bytesMatch = true;
        foreach ($limits as $limit) {
            if (strcmp($candidate, $limit['lower_bytes']) < 0 || strcmp($candidate, $limit['upper_bytes']) > 0) {
                $bytesMatch = false;
                break;
            }
        }

        if ($bytesMatch) {
            return true;
        }

        if ($nameBytes === null || !str_starts_with($nameBytes, "\xfe\xff") && !str_starts_with($nameBytes, "\xff\xfe")) {
            return false;
        }

        foreach ($limits as $limit) {
            if (strcmp($name, $limit['lower']) < 0 || strcmp($name, $limit['upper']) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $items
     * @param array<int, mixed> $objects
     * @param list<array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}> $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, array $objects, array $limits): bool
    {
        if ($limits === [] || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count;) {
            $name = $this->destinationNameDetails($items[$index], $objects);
            if ($name === null) {
                $index++;
                continue;
            }

            if ($this->nameTreeStringValueIsMissingBeforePair($items, $index, $objects)) {
                $index++;
                continue;
            }

            if ($this->nameWithinNameTreeLimits($name['text'], $limits, $name['bytes'])) {
                return true;
            }
            $index += 2;
        }

        return false;
    }

    /**
     * @param list<mixed> $items
     * @param array<int, mixed> $objects
     */
    private function nameTreeStringValueIsMissingBeforePair(array $items, int $index, array $objects): bool
    {
        $valueIndex = $index + 1;
        $nextIndex = $valueIndex + 1;
        if (!array_key_exists($valueIndex, $items) || !array_key_exists($nextIndex, $items)) {
            return false;
        }

        $valueName = $this->destinationNameDetails($items[$valueIndex], $objects);
        if ($valueName === null || $valueName['text'] === '') {
            return false;
        }

        if (!$this->nameTreeItemCanStartExplicitDestination($items[$nextIndex], $objects)) {
            return false;
        }

        return $this->destinationNameDetails($items[$nextIndex], $objects) === null;
    }

    /**
     * @param list<mixed> $items
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     */
    private function nameTreeValueHasUnbracketedDestinationViewTail(
        array $items,
        int $valueIndex,
        array $objects,
        array $pageIndexes
    ): bool {
        if (
            $pageIndexes === []
            || !array_key_exists($valueIndex, $items)
            || !array_key_exists($valueIndex + 1, $items)
            || !$this->nameTreeValueIsPageOnlyDestination($items[$valueIndex], $objects, $pageIndexes)
        ) {
            return false;
        }

        $viewMode = $this->nameValue($this->resolveValue($items[$valueIndex + 1], $objects));

        return $viewMode !== null && isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode]);
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     */
    private function nameTreeValueIsPageOnlyDestination(mixed $value, array $objects, array $pageIndexes): bool
    {
        if ($this->isReferenceValue($value) && !$this->referenceTargetsSingleTopLevelValue($value, $objects)) {
            return false;
        }

        $pageObjectNumber = $this->validReferenceObjectNumber($value, $objects);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return true;
        }

        $resolved = $this->resolveValue($value, $objects);
        $resolvedPageObjectNumber = $this->validReferenceObjectNumber($resolved, $objects);
        if ($resolvedPageObjectNumber !== null && isset($pageIndexes[$resolvedPageObjectNumber])) {
            return true;
        }

        return is_int($resolved) && $this->boundedDestinationPageIndex($resolved, $pageIndexes) !== null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function nameTreeItemCanStartExplicitDestination(mixed $value, array $objects): bool
    {
        $resolved = $this->resolveValue($value, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            return true;
        }

        $dictionary = $this->dictionaryItems($resolved);

        return $dictionary !== null && array_key_exists('D', $dictionary);
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    private function outlineItems(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validReferenceObjectNumber($firstItem, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            if (!$this->outlineObjectCanOwnDictionary($current)) {
                break;
            }
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }
            if (!$this->outlineItemDictionaryAllowsTraversal($dict, $objects, $current)) {
                break;
            }
            if (!$this->outlineItemParentMatches($dict, $objects, $expectedParentObject, $current)) {
                break;
            }
            if (!$this->outlineItemPrevMatches($dict, $objects, $previousSiblingObject, $current)) {
                break;
            }

            $title = $this->outlineTitleValue($dict, $objects, $current);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
                continue;
            }

            $destination = $this->outlineDestination($dict, $objects, $current);
            $page = $this->destinationPageIndex($destination['value'], $objects, $pageIndexes, $destinations);
            if ($page !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $page,
                    'destination' => $destination['name'],
                ];
            }

            if ($level < $maxDepth && $this->outlineItemAllowsChildTraversal($dict, $objects, $current)) {
                foreach ($this->outlineItems(
                    $this->outlineTraversalReferenceValue($dict, 'First', $current),
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $current,
                    $this->outlineTraversalReferenceObjectNumber($dict, 'Last', $objects, $current),
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{
     *     title: string,
     *     level: int,
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }>
     */
    private function outlineItemsWithDestinationViews(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validReferenceObjectNumber($firstItem, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            if (!$this->outlineObjectCanOwnDictionary($current)) {
                break;
            }
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }
            if (!$this->outlineItemDictionaryAllowsTraversal($dict, $objects, $current)) {
                break;
            }
            if (!$this->outlineItemParentMatches($dict, $objects, $expectedParentObject, $current)) {
                break;
            }
            if (!$this->outlineItemPrevMatches($dict, $objects, $previousSiblingObject, $current)) {
                break;
            }

            $title = $this->outlineTitleValue($dict, $objects, $current);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
                continue;
            }

            $destination = $this->outlineDestination($dict, $objects, $current);
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $destinations,
                $destination['name']
            );
            if ($details !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $details['page'],
                    'destination' => $details['destination'],
                    'view_mode' => $details['view_mode'],
                    'view_position' => $details['view_position'],
                    'view_parameters' => $details['view_parameters'],
                ];
            }

            if ($level < $maxDepth && $this->outlineItemAllowsChildTraversal($dict, $objects, $current)) {
                foreach ($this->outlineItemsWithDestinationViews(
                    $this->outlineTraversalReferenceValue($dict, 'First', $current),
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $current,
                    $this->outlineTraversalReferenceObjectNumber($dict, 'Last', $objects, $current),
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<int, int> $pageObjectsByIndex
     * @param array<string, mixed> $destinations
     * @param array<string, mixed> $actionDestinations
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function outlineStructureDestinationPageContextItems(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $pageObjectsByIndex,
        array $destinations,
        array $actionDestinations,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validReferenceObjectNumber($firstItem, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            if (!$this->outlineObjectCanOwnDictionary($current)) {
                break;
            }
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }
            if (!$this->outlineItemDictionaryAllowsTraversal($dict, $objects, $current)) {
                break;
            }
            if (!$this->outlineItemParentMatches($dict, $objects, $expectedParentObject, $current)) {
                break;
            }
            if (!$this->outlineItemPrevMatches($dict, $objects, $previousSiblingObject, $current)) {
                break;
            }

            $title = $this->outlineTitleValue($dict, $objects, $current);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
                continue;
            }

            $destination = $this->outlineDestination($dict, $objects, $current);
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $actionDestinations,
                $destination['name']
            );
            if ($details !== null) {
                $row = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $details['page'],
                    'page_number' => $details['page'] + 1,
                    'page_object' => $pageObjectsByIndex[$details['page']] ?? null,
                    'destination' => $details['destination'],
                    'view_mode' => $details['view_mode'],
                    'view_position' => $details['view_position'],
                    'view_parameters' => $details['view_parameters'],
                    'outline_object' => $current,
                    'parent_object' => $this->referenceObjectNumber($dict['Parent'] ?? null),
                    'previous_object' => $this->referenceObjectNumber($dict['Prev'] ?? null),
                    'next_object' => $this->referenceObjectNumber($dict['Next'] ?? null),
                    'first_child_object' => $this->referenceObjectNumber($dict['First'] ?? null),
                    'last_child_object' => $this->referenceObjectNumber($dict['Last'] ?? null),
                ];
                $row += $this->outlineStructureState($dict, $objects, $current);
                $row += $this->outlineStyleMetadata($dict, $objects, $current);
                $row += $this->destinationAliasReview($destination['value'], $objects, $destinations);
                $row = $this->withNavigationTargetMetadata(
                    $row,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                );
                $row += $this->outlineDestinationActionContext(
                    $destination,
                    $objects,
                    $pageIndexes,
                    $actionDestinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                );

                $items[] = $row;
            }

            if ($level < $maxDepth && $this->outlineItemAllowsChildTraversal($dict, $objects, $current)) {
                foreach ($this->outlineStructureDestinationPageContextItems(
                    $this->outlineTraversalReferenceValue($dict, 'First', $current),
                    $objects,
                    $pageIndexes,
                    $pageObjectsByIndex,
                    $destinations,
                    $actionDestinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage,
                    $current,
                    $this->outlineTraversalReferenceObjectNumber($dict, 'Last', $objects, $current),
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @return array<string, mixed>
     */
    private function destinationAliasReview(mixed $value, array $objects, array $destinations): array
    {
        $firstResolved = $this->resolveValue($value, $objects);
        $firstName = $this->stringOrNameValue($firstResolved);
        if ($firstName === null) {
            return [];
        }
        $firstKey = $this->destinationLookupKeyForNameValue($firstResolved, $firstName);
        if (!array_key_exists($firstKey, $destinations)) {
            $firstKey = $firstName;
        }

        $chain = [];
        $seen = [];
        $currentKey = $firstKey;
        for ($depth = 0; $depth < 32; $depth++) {
            $currentName = $this->destinationNameFromMapKey($currentKey);
            if (isset($seen[$currentKey])) {
                $chain[] = $currentName;

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_alias_resolved' => false,
                    'destination_alias_cycle' => true,
                    'destination_unresolved_reason' => 'destination_alias_cycle',
                ];
            }

            $chain[] = $currentName;
            $seen[$currentKey] = true;
            if (!array_key_exists($currentKey, $destinations)) {
                if (count($chain) < 2) {
                    return [];
                }

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_alias_resolved' => false,
                    'destination_alias_cycle' => false,
                    'destination_unresolved_reason' => 'destination_alias_missing_target',
                ];
            }

            $nextResolved = $this->resolveValue($destinations[$currentKey], $objects);
            $nextName = $this->stringOrNameValue($nextResolved);
            if ($nextName === null) {
                if (count($chain) < 2) {
                    return [];
                }

                return [
                    'declared_destination' => $firstName,
                    'destination_alias_chain' => $chain,
                    'destination_target' => $currentName,
                    'destination_alias_resolved' => true,
                    'destination_alias_cycle' => false,
                ];
            }

            $nextKey = $this->destinationLookupKeyForNameValue($nextResolved, $nextName);
            $currentKey = array_key_exists($nextKey, $destinations) ? $nextKey : $nextName;
        }

        return [
            'declared_destination' => $firstName,
            'destination_alias_chain' => $chain,
            'destination_alias_resolved' => false,
            'destination_alias_cycle' => false,
            'destination_unresolved_reason' => 'destination_alias_depth_limit',
        ];
    }

    /**
     * @param array{name: string|null, value: mixed} $destination
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, list<array<string, mixed>>> $articleBeadsByPage
     * @param array<int, array<string, mixed>> $pageReviewsByPage
     * @param array<int, list<array<string, mixed>>> $taggedContentByPage
     * @return array<string, mixed>
     */
    private function outlineDestinationActionContext(
        array $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $pageLabels,
        array $pagePresentationsByPage,
        array $articleBeadsByPage,
        array $pageReviewsByPage,
        array $taggedContentByPage
    ): array {
        $destinationAction = $this->destinationActionReviewValue($destination['value'], $objects, $destinations, $destination['name']);
        if ($destinationAction === null) {
            return [];
        }

        $seenTargetContext = [];
        $targetContext = $this->actionChainTargetContext(
            $destinationAction['value'],
            $objects,
            $pageIndexes,
            $destinations,
            $pageLabels,
            $pagePresentationsByPage,
            $articleBeadsByPage,
            $pageReviewsByPage,
            $taggedContentByPage,
            $seenTargetContext
        );

        if ($destinationAction['destination_name'] !== null) {
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $destinations,
                $destination['name']
            );
            if ($details !== null) {
                $targetContext = $this->destinationActionTargetContext(
                    $this->withNavigationTargetMetadata(
                        $details,
                        $pageLabels,
                        $pagePresentationsByPage,
                        $articleBeadsByPage,
                        $pageReviewsByPage,
                        $taggedContentByPage
                    )
                );
            }
        }

        $seenActions = [];
        $actions = [];
        foreach ($this->reviewActionsFromValue($destinationAction['value'], $objects, $pageIndexes, $destinations, $seenActions) as $action) {
            if ($destinationAction['destination_name'] !== null) {
                $action['destination_action_name'] = $destinationAction['destination_name'];
            }

            $action = $this->withActionChainTargetContext($action, $targetContext);
            if (
                ($action['action_type'] ?? null) === 'GoTo'
                && ($action['safety'] ?? null) === 'local-destination'
                && ($action['destination'] ?? null) === null
                && $destinationAction['destination_name'] !== null
            ) {
                $action['destination'] = $destinationAction['destination_name'];
            }

            if (is_int($action['page'] ?? null)) {
                $action = $this->withNavigationTargetMetadata(
                    $action,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $articleBeadsByPage,
                    $pageReviewsByPage,
                    $taggedContentByPage
                );
            }

            $actions[] = $action;
        }

        if ($actions === []) {
            return $targetContext;
        }

        $context = $targetContext;
        if ($destinationAction['destination_name'] !== null) {
            $context['destination_action_name'] = $destinationAction['destination_name'];
        }

        $actionObject = $this->referenceObjectNumber($destinationAction['value']);
        if ($actionObject !== null) {
            $context['destination_action_object'] = $actionObject;
        }

        $actionDictionary = $this->resolveDictionary($destinationAction['value'], $objects);
        $actionType = $actionDictionary === null ? null : $this->nameValue($actionDictionary['S'] ?? null);
        if ($actionType !== null) {
            $context['destination_action_type'] = $actionType;
        }

        $context['destination_action_types'] = array_values(array_map(
            static fn (array $action): ?string => is_string($action['action_type'] ?? null) ? $action['action_type'] : null,
            $actions
        ));
        $context['destination_action_safeties'] = array_values(array_map(
            static fn (array $action): ?string => is_string($action['safety'] ?? null) ? $action['safety'] : null,
            $actions
        ));
        $context['destination_action_chained_count'] = count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['chained'] ?? false) === true
        ));
        $context['destination_action_all_review_only'] = array_reduce(
            $actions,
            static fn (bool $carry, array $action): bool => $carry && ($action['executes_on_import'] ?? true) === false,
            true
        );
        $context['destination_action_review_actions'] = $actions;

        return $context;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function outlineStructureState(array $outline, array $objects, ?int $outlineObject = null): array
    {
        $firstChild = $this->referenceObjectNumber($outline['First'] ?? null);
        $lastChild = $this->referenceObjectNumber($outline['Last'] ?? null);
        $hasChildren = $firstChild !== null || $lastChild !== null;
        $count = $outlineObject !== null && (
            $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Count')
            || $this->outlineDictionaryKeyReferencesMalformedSingleValue($outline, 'Count', $objects)
        )
            ? null
            : $this->integerOrNullValue($this->resolveValue($outline['Count'] ?? null, $objects));

        $state = [
            'has_children' => $hasChildren,
            'outline_count' => $count,
            'descendant_count' => $count === null ? null : abs($count),
            'is_open' => null,
            'is_collapsed' => null,
            'structure_state' => $hasChildren ? 'parent' : 'leaf',
        ];

        if ($count !== null) {
            $state['is_open'] = $count >= 0;
            $state['is_collapsed'] = $count < 0;
            $state['structure_state'] = $count < 0 ? 'collapsed' : ($hasChildren ? 'expanded' : 'leaf');
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function outlineStyleMetadata(array $outline, array $objects, ?int $outlineObject = null): array
    {
        $metadata = [];
        $styleFlags = $outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'F')
            ? null
            : $this->integerOrNullValue($this->resolveValue($outline['F'] ?? null, $objects));
        if ($styleFlags !== null) {
            $metadata['style_flags'] = $styleFlags;
            $metadata['is_italic'] = ($styleFlags & 1) !== 0;
            $metadata['is_bold'] = ($styleFlags & 2) !== 0;
        }

        $color = $outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'C')
            ? null
            : $this->outlineColorRgb($outline['C'] ?? null, $objects);
        if ($color !== null) {
            $metadata['text_color_rgb'] = $color;
            $metadata['text_color_hex'] = $this->rgbUnitColorToHex($color);
        }

        return $metadata;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<float>|null
     */
    private function outlineColorRgb(mixed $value, array $objects): ?array
    {
        $array = $this->resolveArray($value, $objects);
        if ($array === null || count($array) !== 3) {
            return null;
        }

        $rgb = [];
        for ($index = 0; $index < 3; $index++) {
            $component = $this->numericOrNullValue($this->resolveValue($array[$index], $objects));
            if ($component === null) {
                return null;
            }

            $rgb[] = max(0.0, min(1.0, $component));
        }

        return $rgb;
    }

    /**
     * @param list<float> $rgb
     */
    private function rgbUnitColorToHex(array $rgb): string
    {
        return sprintf(
            '#%02x%02x%02x',
            (int) round($rgb[0] * 255),
            (int) round($rgb[1] * 255),
            (int) round($rgb[2] * 255)
        );
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @param array<int, array<string, mixed>> $outlineReviewMetadataByObject
     * @return list<array<string, mixed>>
     */
    private function remoteGoToOutlineItems(
        mixed $firstItem,
        array $objects,
        array $destinations,
        ?int $expectedParentObject,
        ?int $lastItemObject,
        int $maxDepth,
        int $level = 1,
        array $seen = [],
        array $outlineReviewMetadataByObject = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->validReferenceObjectNumber($firstItem, $objects);
        $previousSiblingObject = null;
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            if (!$this->outlineObjectCanOwnDictionary($current)) {
                break;
            }
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }
            if (!$this->outlineItemDictionaryAllowsTraversal($dict, $objects, $current)) {
                break;
            }
            if (!$this->outlineItemParentMatches($dict, $objects, $expectedParentObject, $current)) {
                break;
            }
            if (!$this->outlineItemPrevMatches($dict, $objects, $previousSiblingObject, $current)) {
                break;
            }

            $title = $this->outlineTitleValue($dict, $objects, $current);
            if ($title === null) {
                if ($lastItemObject === null || $current === $lastItemObject) {
                    break;
                }

                $previousSiblingObject = $current;
                $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
                continue;
            }

            $target = $this->remoteGoToActionTarget($dict, $objects, $destinations, $current);
            if ($target !== null) {
                $row = [
                    'title' => $title,
                    'level' => $level,
                    'file' => $target['file'],
                    'destination' => $target['destination'],
                    'page' => $target['page'],
                    'new_window' => $target['new_window'],
                ];
                if (isset($outlineReviewMetadataByObject[$current])) {
                    $row['outline_object'] = $current;
                    $row = $this->withOutlineItemDocumentReviewMetadata($row, $outlineReviewMetadataByObject, false);
                }

                $items[] = $row;
            }

            if ($level < $maxDepth && $this->outlineItemAllowsChildTraversal($dict, $objects, $current)) {
                foreach ($this->remoteGoToOutlineItems(
                    $this->outlineTraversalReferenceValue($dict, 'First', $current),
                    $objects,
                    $destinations,
                    $current,
                    $this->outlineTraversalReferenceObjectNumber($dict, 'Last', $objects, $current),
                    $maxDepth,
                    $level + 1,
                    $seen,
                    $outlineReviewMetadataByObject
                ) as $child) {
                    $items[] = $child;
                }
            }

            if ($lastItemObject !== null && $current === $lastItemObject) {
                break;
            }

            $previousSiblingObject = $current;
            $current = $this->outlineTraversalReferenceObjectNumber($dict, 'Next', $objects, $current);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToActionTarget(array $outline, array $objects, array $destinations, ?int $outlineObject = null): ?array
    {
        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'A')) {
            return null;
        }

        $action = $this->resolveDictionary($outline['A'] ?? null, $objects);
        if ($action !== null && $this->nameValue($action['S'] ?? null) === 'GoToR') {
            return $this->remoteGoToTargetFromAction($action, $objects);
        }

        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Dest')) {
            return null;
        }

        if (array_key_exists('Dest', $outline)) {
            return $this->remoteGoToTargetFromDestination($outline['Dest'], $objects, $destinations);
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromDestination(
        mixed $destination,
        array $objects,
        array $destinations,
        array $seenNames = []
    ): ?array {
        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            $lookupKey = $this->destinationLookupKeyForNameValue($resolved, $name);
            if (!array_key_exists($lookupKey, $destinations)) {
                $lookupKey = $name;
            }

            if (isset($seenNames[$lookupKey]) || !array_key_exists($lookupKey, $destinations)) {
                return null;
            }
            $seenNames[$lookupKey] = true;

            return $this->remoteGoToTargetFromDestination($destinations[$lookupKey], $objects, $destinations, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        $type = $this->nameValue($dict['S'] ?? null);
        if ($type === 'GoToR') {
            return $this->remoteGoToTargetFromAction($dict, $objects);
        }

        if ($type === null && array_key_exists('D', $dict)) {
            return $this->remoteGoToTargetFromDestination($dict['D'], $objects, $destinations, $seenNames);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, mixed> $objects
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromAction(array $action, array $objects): ?array
    {
        $file = $this->fileSpecValue($action['F'] ?? null, $objects);
        if ($file === null || !array_key_exists('D', $action)) {
            return null;
        }

        $destination = $this->remoteDestinationValue($action['D'], $objects);
        if ($destination === null) {
            return null;
        }

        return [
            'file' => $file,
            'destination' => $destination['destination'],
            'page' => $destination['page'],
            'new_window' => is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return array{action_type: string, safety: string, page: int|null, destination: string|null, uri: string|null, file: string|null, operation: string|null, new_window: bool|null, is_safe_uri: bool|null, executes_on_import: bool}|null
     */
    private function openActionReviewAction(mixed $value, array $objects, array $pageIndexes, array $destinations): ?array
    {
        $resolved = $this->resolveValue($value, $objects);
        $action = $this->dictionaryItems($resolved);
        if ($action === null || !array_key_exists('S', $action)) {
            return $this->localOpenDestinationReview($value, $objects, $pageIndexes, $destinations);
        }

        $type = $this->nameValue($action['S'] ?? null);
        if ($type === 'GoTo' && array_key_exists('D', $action)) {
            if ($this->actionDictionaryValueHasTrailingOperands($value, 'D')) {
                return null;
            }

            return $this->localOpenDestinationReview($action['D'], $objects, $pageIndexes, $destinations);
        }

        if ($type === 'GoToR') {
            $target = $this->remoteGoToTargetFromAction($action, $objects);
            if ($target === null) {
                return null;
            }

            return $this->reviewAction(
                'GoToR',
                'remote-document-review',
                $target['page'],
                $target['destination'],
                null,
                $target['file'],
                null,
                $target['new_window'],
                null
            );
        }

        if ($type === 'GoToE') {
            return $this->embeddedGoToActionReview($action, $objects);
        }

        if ($type === 'Thread') {
            return $this->threadActionReview($action, $objects, $pageIndexes);
        }

        if ($type === 'URI') {
            $uri = $this->stringOrNameValue($this->resolveValue($action['URI'] ?? null, $objects));
            if ($uri === null || trim($uri) === '') {
                return null;
            }

            $isSafeUri = $this->isSafeUri($uri);

            return $this->reviewAction(
                'URI',
                $isSafeUri ? 'review-uri' : 'blocked-unsafe-uri',
                null,
                null,
                $uri,
                null,
                null,
                null,
                $isSafeUri
            );
        }

        if ($type === 'Launch') {
            $file = $this->fileSpecValue($action['F'] ?? null, $objects);
            $win = $this->resolveDictionary($action['Win'] ?? null, $objects);
            if ($file === null && $win !== null) {
                $file = $this->fileSpecValue($win['F'] ?? null, $objects);
            }
            if ($file === null || trim($file) === '') {
                return null;
            }

            $operation = $win === null ? null : $this->stringOrNameValue($this->resolveValue($win['O'] ?? null, $objects));

            return $this->reviewAction(
                'Launch',
                'blocked-launch',
                null,
                null,
                null,
                $file,
                $operation,
                is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
                null
            );
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return array<string, mixed>|null
     */
    private function localOpenDestinationReview(mixed $destination, array $objects, array $pageIndexes, array $destinations): ?array
    {
        $details = $this->destinationViewDetails($destination, $objects, $pageIndexes, $destinations);
        if ($details === null) {
            return null;
        }

        $action = $this->reviewAction(
            'GoTo',
            'local-destination',
            $details['page'],
            $details['destination'],
            null,
            null,
            null,
            null,
            null
        );
        $action['view_mode'] = $details['view_mode'];
        $action['view_position'] = $details['view_position'];
        $action['view_parameters'] = $details['view_parameters'];

        return $action;
    }

    /**
     * @return array{action_type: string, safety: string, page: int|null, destination: string|null, uri: string|null, file: string|null, operation: string|null, new_window: bool|null, is_safe_uri: bool|null, executes_on_import: bool}
     */
    private function reviewAction(
        string $type,
        string $safety,
        ?int $page,
        ?string $destination,
        ?string $uri,
        ?string $file,
        ?string $operation,
        ?bool $newWindow,
        ?bool $isSafeUri
    ): array {
        return [
            'action_type' => $type,
            'safety' => $safety,
            'page' => $page,
            'destination' => $destination,
            'uri' => $uri,
            'file' => $file,
            'operation' => $operation,
            'new_window' => $newWindow,
            'is_safe_uri' => $isSafeUri,
            'executes_on_import' => false,
        ];
    }

    /**
     * PDF GoToE actions target embedded documents. Their destination page
     * numbers belong to the embedded document, so they must not populate the
     * current-document `page` field used for outline/page transition joins.
     *
     * @param array<string, mixed> $action
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function embeddedGoToActionReview(array $action, array $objects): array
    {
        $attachment = $this->fileSpecReviewValue($action['F'] ?? null, $objects);
        $destination = $this->embeddedDestinationDetails($action['D'] ?? null, $objects);
        $newWindow = is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null;

        $row = $this->reviewAction(
            'GoToE',
            'embedded-document-review',
            null,
            $destination['destination'] ?? null,
            null,
            $attachment['filename'] ?? $this->fileSpecValue($action['F'] ?? null, $objects),
            null,
            $newWindow,
            null
        );

        if ($attachment !== null) {
            $row['attachment'] = $attachment;
        }

        foreach (['destination_page', 'view_mode', 'view_position', 'view_parameters'] as $key) {
            if (array_key_exists($key, $destination)) {
                $row[$key] = $destination[$key];
            }
        }

        $target = $this->embeddedTargetDetails($action['T'] ?? null, $objects);
        if ($target !== null) {
            $row['target'] = $target;
        }

        return $row;
    }

    /**
     * PDF Thread actions ask the viewer to enter article-thread mode at a
     * selected bead. WordPress import records that target but never follows it.
     *
     * @param array<string, mixed> $action
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array<string, mixed>|null
     */
    private function threadActionReview(array $action, array $objects, array $pageIndexes): ?array
    {
        $file = $this->fileSpecValue($action['F'] ?? null, $objects);
        $external = $file !== null;
        $target = $this->threadActionTargetDetails($action, $objects, $pageIndexes, $external);
        if ($target === null && !$external) {
            return null;
        }

        $row = $this->reviewAction(
            'Thread',
            $external ? 'remote-thread-review' : 'article-thread-review',
            is_int($target['page'] ?? null) ? $target['page'] : null,
            is_string($target['thread_title'] ?? null) ? $target['thread_title'] : ($target['thread_destination'] ?? null),
            null,
            $file,
            null,
            null,
            null
        );

        foreach ([
            'thread_destination',
            'thread_destination_type',
            'thread_object',
            'thread_index',
            'thread_title',
            'thread_bead_object',
            'thread_bead_index',
            'thread_bead_rect',
            'thread_page_object',
        ] as $key) {
            if (is_array($target) && array_key_exists($key, $target)) {
                $row[$key] = $target[$key];
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    private function threadActionDestinationDetails(array $target): array
    {
        $details = [
            'page' => $target['page'],
            'destination' => is_string($target['thread_title'] ?? null) ? $target['thread_title'] : ($target['thread_destination'] ?? null),
            'view_mode' => null,
            'view_position' => [],
            'view_parameters' => [],
        ] + $target;

        if (is_int($target['thread_page_object'] ?? null)) {
            $details['page_object'] = $target['thread_page_object'];
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array<string, mixed>|null
     */
    private function threadActionTargetDetails(array $action, array $objects, array $pageIndexes, bool $external): ?array
    {
        if (!array_key_exists('D', $action)) {
            return null;
        }

        $destinationSummary = $this->threadDestinationSummary($action['D'], $objects);
        if ($external) {
            $details = $destinationSummary;
            $beadSummary = $this->threadBeadSummary($action['B'] ?? null, $objects);
            foreach ($beadSummary as $key => $value) {
                $details[$key] = $value;
            }

            return $details;
        }

        $thread = $this->localArticleThreadFromDestination($action['D'], $objects, $pageIndexes);
        if ($thread === null) {
            return null;
        }

        $bead = $this->threadActionBead($action['B'] ?? null, $thread['beads'], $objects);
        if ($bead === null) {
            return null;
        }

        return $destinationSummary + [
            'thread_object' => $thread['thread_object'],
            'thread_index' => $thread['thread_index'],
            'thread_title' => $thread['thread_title'],
            'thread_bead_object' => $bead['bead_object'],
            'thread_bead_index' => $bead['bead_index'],
            'thread_bead_rect' => $bead['rect'],
            'thread_page_object' => $bead['page_object'],
            'page' => $bead['page'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function threadDestinationSummary(mixed $value, array $objects): array
    {
        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber !== null) {
            return [
                'thread_destination_type' => 'object',
                'thread_destination' => (string) $objectNumber,
            ];
        }

        $resolved = $this->resolveValue($value, $objects);
        if (is_int($resolved) || is_float($resolved)) {
            return [
                'thread_destination_type' => 'index',
                'thread_destination' => (string) ((int) $resolved),
            ];
        }

        $title = $this->stringOrNameValue($resolved);
        if ($title !== null && $title !== '') {
            return [
                'thread_destination_type' => 'title',
                'thread_destination' => $title,
            ];
        }

        if ($this->dictionaryItems($resolved) !== null) {
            return [
                'thread_destination_type' => 'dictionary',
                'thread_destination' => null,
            ];
        }

        return [
            'thread_destination_type' => null,
            'thread_destination' => null,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function threadBeadSummary(mixed $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber !== null) {
            return [
                'thread_bead_object' => $objectNumber,
            ];
        }

        $resolved = $this->resolveValue($value, $objects);
        if (is_int($resolved) || is_float($resolved)) {
            return [
                'thread_bead_index' => (int) $resolved,
            ];
        }

        return [];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array{
     *     thread_object: int|null,
     *     thread_index: int|null,
     *     thread_title: string|null,
     *     beads: list<array<string, mixed>>
     * }|null
     */
    private function localArticleThreadFromDestination(mixed $destination, array $objects, array $pageIndexes): ?array
    {
        $threads = $this->localArticleThreads($objects, $pageIndexes);
        if ($threads === []) {
            return null;
        }

        $objectNumber = $this->referenceObjectNumber($destination);
        if ($objectNumber !== null) {
            foreach ($threads as $thread) {
                if (($thread['thread_object'] ?? null) === $objectNumber) {
                    return $thread;
                }
            }
        }

        $resolved = $this->resolveValue($destination, $objects);
        if (is_int($resolved) || is_float($resolved)) {
            $index = (int) $resolved;
            foreach ($threads as $thread) {
                if (($thread['thread_index'] ?? null) === $index) {
                    return $thread;
                }
            }
        }

        $title = $this->stringOrNameValue($resolved);
        if ($title !== null && $title !== '') {
            foreach ($threads as $thread) {
                if (($thread['thread_title'] ?? null) === $title) {
                    return $thread;
                }
            }
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Thread') {
            $title = $this->articleThreadTitle($dict, $objects);
            $beads = $this->articleThreadBeads(
                $dict,
                $objectNumber,
                -1,
                $title,
                $objects,
                $pageIndexes,
                $this->defaultPageLabels($pageIndexes)
            );
            if ($beads !== []) {
                return [
                    'thread_object' => $objectNumber,
                    'thread_index' => null,
                    'thread_title' => $title,
                    'beads' => $beads,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return list<array{
     *     thread_object: int|null,
     *     thread_index: int,
     *     thread_title: string|null,
     *     beads: list<array<string, mixed>>
     * }>
     */
    private function localArticleThreads(array $objects, array $pageIndexes): array
    {
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null || !array_key_exists('Threads', $catalog)) {
            return [];
        }

        $threadValues = $this->resolveArray($catalog['Threads'], $objects);
        if ($threadValues === null) {
            $threadValues = $this->resolveDictionary($catalog['Threads'], $objects) === null
                ? []
                : [$catalog['Threads']];
        }

        $threads = [];
        $pageLabels = $this->defaultPageLabels($pageIndexes);
        foreach ($threadValues as $threadIndex => $threadValue) {
            $thread = $this->resolveDictionary($threadValue, $objects);
            if ($thread === null) {
                continue;
            }

            $threadObject = $this->referenceObjectNumber($threadValue);
            $title = $this->articleThreadTitle($thread, $objects);
            $beads = $this->articleThreadBeads(
                $thread,
                $threadObject,
                (int) $threadIndex,
                $title,
                $objects,
                $pageIndexes,
                $pageLabels
            );
            if ($beads === []) {
                continue;
            }

            $threads[] = [
                'thread_object' => $threadObject,
                'thread_index' => (int) $threadIndex,
                'thread_title' => $title,
                'beads' => $beads,
            ];
        }

        return $threads;
    }

    /**
     * @param array<int, int> $pageIndexes
     * @return list<string>
     */
    private function defaultPageLabels(array $pageIndexes): array
    {
        $count = $pageIndexes === [] ? 0 : max($pageIndexes) + 1;
        $labels = [];
        for ($index = 0; $index < $count; $index++) {
            $labels[] = (string) ($index + 1);
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $beads
     * @return array<string, mixed>|null
     */
    private function threadActionBead(mixed $value, array $beads, array $objects): ?array
    {
        if ($beads === []) {
            return null;
        }

        if ($value === null) {
            return $beads[0];
        }

        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber !== null) {
            foreach ($beads as $bead) {
                if (($bead['bead_object'] ?? null) === $objectNumber) {
                    return $bead;
                }
            }
        }

        $resolved = $this->resolveValue($value, $objects);
        if (is_int($resolved) || is_float($resolved)) {
            $index = (int) $resolved;
            foreach ($beads as $bead) {
                if (($bead['bead_index'] ?? null) === $index) {
                    return $bead;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{
     *     style: string|null,
     *     duration: float|null,
     *     dimension: string|null,
     *     motion: string|null,
     *     direction: float|string|null,
     *     scale: float|null,
     *     opaque_background: bool|null
     * }|null
     */
    private function pageTransitionMetadata(mixed $value, array $objects): ?array
    {
        $transition = $this->resolveDictionary($value, $objects);
        if ($transition === null) {
            return null;
        }
        $opaqueBackground = $this->resolveValue($transition['B'] ?? null, $objects);

        return [
            'style' => $this->nameValue($this->resolveValue($transition['S'] ?? null, $objects)),
            'duration' => $this->numericOrNullValue($this->resolveValue($transition['D'] ?? null, $objects)),
            'dimension' => $this->nameValue($this->resolveValue($transition['Dm'] ?? null, $objects)),
            'motion' => $this->nameValue($this->resolveValue($transition['M'] ?? null, $objects)),
            'direction' => $this->directionValue($this->resolveValue($transition['Di'] ?? null, $objects)),
            'scale' => $this->numericOrNullValue($this->resolveValue($transition['SS'] ?? null, $objects)),
            'opaque_background' => is_bool($opaqueBackground) ? $opaqueBackground : null,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return list<array<string, mixed>>
     */
    private function pageAdditionalActionMetadata(mixed $value, array $objects, array $pageIndexes, array $destinations): array
    {
        $additionalActions = $this->resolveDictionary($value, $objects);
        if ($additionalActions === null) {
            return [];
        }

        $actions = [];
        foreach ($additionalActions as $event => $actionValue) {
            $seen = [];
            foreach ($this->reviewActionsFromValue($actionValue, $objects, $pageIndexes, $destinations, $seen) as $action) {
                $actions[] = [
                    'event' => $event,
                    'event_label' => self::PAGE_ACTION_EVENT_LABELS[$event] ?? 'page_additional_action',
                ] + $action;
            }
        }

        return $actions;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewActionsFromValue(
        mixed $value,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array &$seen,
        int $depth = 0
    ): array {
        if ($value === null || $depth > 20) {
            return [];
        }

        $resolved = $this->resolveValue($value, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            $actions = [];
            foreach ($array as $item) {
                foreach ($this->reviewActionsFromValue($item, $objects, $pageIndexes, $destinations, $seen, $depth + 1) as $action) {
                    $actions[] = $action;
                }
            }

            return $actions;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return [];
        }

        $actionObject = $this->referenceObjectNumber($value);
        $identity = $actionObject === null ? 'dict:' . md5(serialize($dict)) : 'obj:' . $actionObject;
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $type = $this->nameValue($dict['S'] ?? null);
        $action = $type === 'JavaScript'
            ? $this->reviewAction('JavaScript', 'blocked-javascript', null, null, null, null, null, null, null)
            : $this->openActionReviewAction($value, $objects, $pageIndexes, $destinations);
        $destinationActionRows = [];
        if (
            $action === null
            && $type === 'GoTo'
            && array_key_exists('D', $dict)
            && !$this->actionDictionaryValueHasTrailingOperands($value, 'D')
        ) {
            $destinationAction = $this->destinationActionReviewValue($dict['D'], $objects, $destinations);
            if ($destinationAction !== null) {
                foreach ($this->reviewActionsFromValue($destinationAction['value'], $objects, $pageIndexes, $destinations, $seen, $depth + 1) as $destinationActionRow) {
                    if ($destinationAction['destination_name'] !== null && !array_key_exists('destination_action_name', $destinationActionRow)) {
                        $destinationActionRow['destination_action_name'] = $destinationAction['destination_name'];
                    }

                    $destinationActionRows[] = $destinationActionRow;
                }
            }
        }

        if ($action === null && $destinationActionRows === [] && $type !== null) {
            $action = $this->reviewAction($type, 'unsupported-action-review', null, null, null, null, null, null, null);
        }

        $actions = [];
        if ($destinationActionRows !== []) {
            foreach ($destinationActionRows as $destinationActionRow) {
                $actions[] = $destinationActionRow;
            }
        } elseif ($action !== null) {
            if ($actionObject !== null) {
                $action['action_object'] = $actionObject;
            }
            $actions[] = $action;
        }

        if (
            array_key_exists('Next', $dict)
            && !$this->actionDictionaryReferenceHasTrailingOperands($actionObject, 'Next')
        ) {
            foreach ($this->reviewActionsFromValue($dict['Next'], $objects, $pageIndexes, $destinations, $seen, $depth + 1) as $nextAction) {
                $nextAction['chained'] = true;
                $actions[] = $nextAction;
            }
        }

        return $actions;
    }

    private function actionDictionaryReferenceHasTrailingOperands(?int $objectNumber, string $key): bool
    {
        return $objectNumber !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($objectNumber, $key);
    }

    private function actionDictionaryValueHasTrailingOperands(mixed $value, string $key): bool
    {
        return $this->actionDictionaryReferenceHasTrailingOperands($this->referenceObjectNumber($value), $key);
    }

    private function directionValue(mixed $value): float|string|null
    {
        $name = $this->nameValue($value);
        if ($name !== null) {
            return $name;
        }

        return $this->numericOrNullValue($value);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function fileSpecValue(mixed $value, array $objects): ?string
    {
        $resolved = $this->resolveValue($value, $objects);
        $file = $this->stringOrNameValue($resolved);
        if ($file !== null && $file !== '') {
            return $file;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $file = $this->stringOrNameValue($this->resolveValue($dict[$key] ?? null, $objects));
            if ($file !== null && $file !== '') {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function fileSpecReviewValue(mixed $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $fileSpecObject = $this->referenceObjectNumber($value);
        $resolved = $this->resolveValue($value, $objects);
        $file = $this->stringOrNameValue($resolved);
        if ($file !== null && $file !== '') {
            return [
                'filename' => $file,
                'has_embedded_file' => false,
            ];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        $unicodeFilename = $this->stringOrNameValue($this->resolveValue($dict['UF'] ?? null, $objects));
        $filename = $unicodeFilename;
        foreach (['F', 'DOS', 'Unix', 'Mac'] as $key) {
            if ($filename !== null && $filename !== '') {
                break;
            }

            $filename = $this->stringOrNameValue($this->resolveValue($dict[$key] ?? null, $objects));
        }

        if ($filename === null || $filename === '') {
            return null;
        }

        $details = [
            'filename' => $filename,
            'has_embedded_file' => false,
        ];
        if ($fileSpecObject !== null) {
            $details['file_spec_object'] = $fileSpecObject;
        }
        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $details['unicode_filename'] = $unicodeFilename;
        }

        $description = $this->stringOrNameValue($this->resolveValue($dict['Desc'] ?? null, $objects));
        if ($description !== null && $description !== '') {
            $details['description'] = $description;
        }

        $relationship = $this->nameValue($this->resolveValue($dict['AFRelationship'] ?? null, $objects));
        if ($relationship !== null && $relationship !== '') {
            $details['relationship'] = $relationship;
        }

        $embeddedFiles = $this->resolveDictionary($dict['EF'] ?? null, $objects);
        if ($embeddedFiles === null) {
            return $details;
        }

        $details['has_embedded_file'] = true;
        $embeddedObjects = [];
        $embeddedKeys = [];
        $mimeTypes = [];
        foreach (['F', 'UF', 'DOS', 'Unix', 'Mac'] as $key) {
            if (!array_key_exists($key, $embeddedFiles)) {
                continue;
            }

            $embeddedKeys[] = $key;
            $embeddedObject = $this->referenceObjectNumber($embeddedFiles[$key]);
            if ($embeddedObject !== null) {
                $embeddedObjects[] = $embeddedObject;
            }

            $streamDictionary = $this->resolveDictionary($embeddedFiles[$key], $objects);
            if ($streamDictionary === null) {
                continue;
            }

            $mimeType = $this->nameValue($this->resolveValue($streamDictionary['Subtype'] ?? null, $objects));
            if ($mimeType !== null && $mimeType !== '') {
                $mimeTypes[$mimeType] = $mimeType;
            }
        }

        if ($embeddedObjects !== []) {
            $details['embedded_file_objects'] = array_values(array_unique($embeddedObjects));
        }
        if ($embeddedKeys !== []) {
            $details['embedded_file_keys'] = array_values(array_unique($embeddedKeys));
        }
        if ($mimeTypes !== []) {
            $details['mime_types'] = array_values($mimeTypes);
        }

        return $details;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function embeddedDestinationDetails(mixed $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $resolved = $this->resolveValue($value, $objects);
        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->embeddedDestinationDetails($dict['D'], $objects);
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null && $name !== '') {
            return ['destination' => $name];
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return [];
        }

        $details = [];
        $first = $this->resolveValue($array[0], $objects);
        if (is_int($first) || is_float($first)) {
            if ($first >= 0) {
                $details['destination_page'] = (int) $first;
            }
        } else {
            $destination = $this->stringOrNameValue($first);
            if ($destination !== null && $destination !== '') {
                $details['destination'] = $destination;
            }
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null, $objects));
        if ($viewMode !== null && $viewMode !== '') {
            $details['view_mode'] = $viewMode;
        }

        $viewPosition = [];
        for ($index = 2, $count = count($array); $index < $count; $index++) {
            $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index], $objects));
        }
        $viewPosition = $this->normalizedViewPosition($viewMode, $viewPosition);
        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }
        if ($viewPosition !== []) {
            $details['view_position'] = $viewPosition;
            $details['view_parameters'] = $this->viewParameters($viewMode, $viewPosition);
        }

        return $details;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function embeddedTargetDetails(mixed $value, array $objects, int $depth = 0): ?array
    {
        if ($value === null || $depth > 8) {
            return null;
        }

        $targetObject = $this->referenceObjectNumber($value);
        $target = $this->resolveDictionary($value, $objects);
        if ($target === null) {
            return null;
        }

        $details = [];
        if ($targetObject !== null) {
            $details['target_object'] = $targetObject;
        }

        $relation = $this->nameValue($this->resolveValue($target['R'] ?? null, $objects));
        if ($relation !== null && $relation !== '') {
            $details['relation'] = $relation;
            $details['relation_label'] = $this->embeddedTargetRelationshipLabel($relation);
        }

        $name = $this->stringOrNameValue($this->resolveValue($target['N'] ?? null, $objects));
        if ($name !== null && $name !== '') {
            $details['name'] = $name;
        }

        $page = $this->resolveValue($target['P'] ?? null, $objects);
        if ((is_int($page) || is_float($page)) && $page >= 0) {
            $details['page'] = (int) $page;
        }

        $annotationObject = $this->referenceObjectNumber($target['A'] ?? null);
        if ($annotationObject !== null) {
            $details['annotation_object'] = $annotationObject;
        }

        $nestedTarget = $this->embeddedTargetDetails($target['T'] ?? null, $objects, $depth + 1);
        if ($nestedTarget !== null) {
            $details['nested_target'] = $nestedTarget;
        }

        return $details === [] ? null : $details;
    }

    private function embeddedTargetRelationshipLabel(string $relation): string
    {
        return match ($relation) {
            'C' => 'child',
            'P' => 'parent',
            'R' => 'root',
            default => $relation,
        };
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{destination: string|null, page: int|null}|null
     */
    private function remoteDestinationValue(mixed $value, array $objects): ?array
    {
        $resolved = $this->resolveValue($value, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->remoteDestinationValue($dict['D'], $objects);
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        $first = $this->resolveValue($array[0], $objects);
        if (is_int($first) && $first >= 0) {
            return ['destination' => null, 'page' => $first];
        }

        $name = $this->stringOrNameValue($first);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array{name: string|null, value: mixed}
     */
    private function outlineDestination(array $outline, array $objects, ?int $outlineObject = null): array
    {
        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'Dest')) {
            return ['name' => null, 'value' => null];
        }

        if (array_key_exists('Dest', $outline)) {
            return [
                'name' => $this->destinationNameValue($outline['Dest'], $objects),
                'value' => $outline['Dest'],
            ];
        }

        if ($outlineObject !== null && $this->outlineObjectDictionaryKeyHasTrailingOperands($outlineObject, 'A')) {
            return ['name' => null, 'value' => null];
        }

        $action = $this->resolveDictionary($outline['A'] ?? null, $objects);
        if ($action === null || $this->nameValue($action['S'] ?? null) !== 'GoTo' || !array_key_exists('D', $action)) {
            return ['name' => null, 'value' => null];
        }
        if ($this->actionDictionaryValueHasTrailingOperands($outline['A'] ?? null, 'D')) {
            return ['name' => null, 'value' => null];
        }

        return [
            'name' => $this->destinationNameValue($action['D'], $objects),
            'value' => $action['D'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function destinationNameValue(mixed $value, array $objects): ?string
    {
        return $this->stringOrNameValue($this->resolveValue($value, $objects));
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{text: string, bytes: string}|null
     */
    private function destinationNameDetails(mixed $value, array $objects): ?array
    {
        if ($this->isReferenceValue($value) && !$this->referenceTargetsSingleTopLevelValue($value, $objects)) {
            return null;
        }

        $resolved = $this->resolveValue($value, $objects);
        if (!is_array($resolved)
            || ($resolved['pdfType'] ?? null) !== 'string'
            || !is_string($resolved['value'] ?? null)
            || $resolved['value'] === ''
        ) {
            return null;
        }

        return [
            'text' => $resolved['value'],
            'bytes' => is_string($resolved['bytes'] ?? null) ? $resolved['bytes'] : $resolved['value'],
        ];
    }

    /**
     * @param array<string, mixed> $destinations
     * @param array{text: string, bytes: string} $name
     */
    private function addNameTreeDestinationMapEntry(array &$destinations, array $name, mixed $value): void
    {
        $rawKey = $this->destinationNameEntryKey($name['text'], $name['bytes']);
        $hasDecodedCollision = $this->destinationMapHasDifferentRawKey($destinations, $name['text'], $rawKey);

        $destinations[$rawKey] = $value;
        if ($hasDecodedCollision) {
            unset($destinations[$name['text']]);
            return;
        }

        $destinations[$name['text']] = $value;
    }

    /**
     * @param array<string, mixed> $destinations
     */
    private function destinationMapHasDifferentRawKey(array $destinations, string $name, string $rawKey): bool
    {
        foreach (array_keys($destinations) as $key) {
            if (!is_string($key) || $key === $rawKey || !$this->destinationMapKeyHasRawBytes($key)) {
                continue;
            }

            if ($this->destinationNameFromMapKey($key) === $name) {
                return true;
            }
        }

        return false;
    }

    private function destinationLookupKeyForNameValue(mixed $value, string $name): string
    {
        $string = $this->pdfStringDetails($value);
        if ($string === null) {
            return $name;
        }

        return $this->destinationNameEntryKey($string['text'], $string['bytes']);
    }

    private function destinationNameEntryKey(string $name, string $bytes): string
    {
        return $name . "\0" . bin2hex($bytes);
    }

    private function destinationNameFromMapKey(string $key): string
    {
        $offset = strrpos($key, "\0");
        if ($offset === false || !$this->destinationMapKeyHasRawBytes($key)) {
            return $key;
        }

        return substr($key, 0, $offset);
    }

    private function destinationMapKeyHasRawBytes(string $key): bool
    {
        $offset = strrpos($key, "\0");
        if ($offset === false) {
            return false;
        }

        $suffix = substr($key, $offset + 1);

        return $suffix !== '' && strlen($suffix) % 2 === 0 && preg_match('/^[\da-f]+$/', $suffix) === 1;
    }

    /**
     * @return array{text: string, bytes: string}|null
     */
    private function pdfStringDetails(mixed $value): ?array
    {
        if (!is_array($value) || ($value['pdfType'] ?? null) !== 'string' || !is_string($value['value'] ?? null)) {
            return null;
        }

        return [
            'text' => $value['value'],
            'bytes' => is_string($value['bytes'] ?? null) ? $value['bytes'] : $value['value'],
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array{name: string|null, value: mixed}|null
     */
    private function catalogOpenActionDestination(array $catalog, array $objects): ?array
    {
        if (!array_key_exists('OpenAction', $catalog)) {
            return null;
        }

        $openAction = $catalog['OpenAction'];
        $resolved = $this->resolveValue($openAction, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            return [
                'name' => $this->stringOrNameValue($openAction),
                'value' => $openAction,
            ];
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return ['name' => $name, 'value' => $openAction];
        }

        $action = $this->dictionaryItems($resolved);
        if ($action === null || $this->nameValue($action['S'] ?? null) !== 'GoTo' || !array_key_exists('D', $action)) {
            return null;
        }

        return [
            'name' => $this->stringOrNameValue($action['D']),
            'value' => $action['D'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }|null
     */
    private function destinationViewDetails(
        mixed $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        ?string $destinationName = null,
        array $seenNames = []
    ): ?array {
        if ($this->isReferenceValue($destination) && !$this->referenceTargetsSingleTopLevelValue($destination, $objects)) {
            return null;
        }

        $pageObjectNumber = $this->validReferenceObjectNumber($destination, $objects);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return [
                'page' => $pageIndexes[$pageObjectNumber],
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            $lookupKey = $this->destinationLookupKeyForNameValue($resolved, $name);
            if (!array_key_exists($lookupKey, $destinations)) {
                $lookupKey = $name;
            }

            if (isset($seenNames[$lookupKey]) || !array_key_exists($lookupKey, $destinations)) {
                return null;
            }
            $seenNames[$lookupKey] = true;

            return $this->destinationViewDetails(
                $destinations[$lookupKey],
                $objects,
                $pageIndexes,
                $destinations,
                $destinationName ?? $this->destinationNameFromMapKey($lookupKey),
                $seenNames
            );
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            if (
                $this->resolvedDictionaryHasDuplicateBoundaryKeys($destination, $objects, self::DESTINATION_DICTIONARY_BOUNDARY_KEYS)
                || $this->actionDictionaryValueHasTrailingOperands($destination, 'D')
            ) {
                return null;
            }

            $localDestination = $this->localDestinationDictionaryValue($dict, $objects);
            if ($localDestination !== null) {
                return $this->destinationViewDetails($localDestination['value'], $objects, $pageIndexes, $destinations, $destinationName, $seenNames);
            }
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            if (!$this->destinationArrayViewModeIsValid($array, $objects)) {
                return null;
            }

            return $this->explicitDestinationDetails($array, $objects, $pageIndexes, $destinationName);
        }

        $pageObjectNumber = $this->validReferenceObjectNumber($resolved, $objects);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return [
                'page' => $pageIndexes[$pageObjectNumber],
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $pageIndex = is_int($resolved) ? $this->boundedDestinationPageIndex($resolved, $pageIndexes) : null;
        if ($pageIndex !== null) {
            return [
                'page' => $pageIndex,
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $array
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array{
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }|null
     */
    private function explicitDestinationDetails(array $array, array $objects, array $pageIndexes, ?string $destinationName): ?array
    {
        $page = $this->destinationPageFromValue($array[0] ?? null, $objects, $pageIndexes);
        if ($page === null) {
            return null;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null, $objects));
        if ($viewMode !== null && !isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])) {
            return null;
        }

        $viewPosition = [];
        for ($index = 2, $count = count($array); $index < $count; $index++) {
            $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index], $objects));
        }
        $viewPosition = $this->normalizedViewPosition($viewMode, $viewPosition);

        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }

        return [
            'page' => $page,
            'destination' => $destinationName,
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $this->viewParameters($viewMode, $viewPosition),
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     */
    private function destinationPageFromValue(mixed $value, array $objects, array $pageIndexes): ?int
    {
        if ($this->isReferenceValue($value) && !$this->referenceTargetsSingleTopLevelValue($value, $objects)) {
            return null;
        }

        $pageObjectNumber = $this->validReferenceObjectNumber($value, $objects);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        $resolved = $this->resolveValue($value, $objects);
        $pageObjectNumber = $this->validReferenceObjectNumber($resolved, $objects);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        return is_int($resolved) ? $this->boundedDestinationPageIndex($resolved, $pageIndexes) : null;
    }

    /**
     * @param array<int, int> $pageIndexes
     */
    private function boundedDestinationPageIndex(int $pageIndex, array $pageIndexes): ?int
    {
        return $pageIndex >= 0 && $pageIndex < count($pageIndexes) ? $pageIndex : null;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return list<float|null>
     */
    private function normalizedViewPosition(?string $viewMode, array $viewPosition): array
    {
        $expectedCount = match ($viewMode) {
            'Fit', 'FitB' => 0,
            'FitH', 'FitBH', 'FitV', 'FitBV' => 1,
            'FitR' => 4,
            'XYZ' => 3,
            default => null,
        };

        return $expectedCount === null ? $viewPosition : array_slice($viewPosition, 0, $expectedCount);
    }

    private function numericOrNullValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function integerOrNullValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_float($value) && floor($value) === $value ? (int) $value : null;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return array<string, float|null>
     */
    private function viewParameters(?string $viewMode, array $viewPosition): array
    {
        $names = match ($viewMode) {
            'XYZ' => ['left', 'top', 'zoom'],
            'FitH', 'FitBH' => ['top'],
            'FitV', 'FitBV' => ['left'],
            'FitR' => ['left', 'bottom', 'right', 'top'],
            default => [],
        };

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[$name] = $viewPosition[$index] ?? null;
        }

        return $parameters;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     */
    private function destinationPageIndex(
        mixed $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $seenNames = []
    ): ?int {
        if ($this->isReferenceValue($destination) && !$this->referenceTargetsSingleTopLevelValue($destination, $objects)) {
            return null;
        }

        $pageObjectNumber = $this->validReferenceObjectNumber($destination, $objects);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return $pageIndexes[$pageObjectNumber];
        }

        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            $lookupKey = $this->destinationLookupKeyForNameValue($resolved, $name);
            if (!array_key_exists($lookupKey, $destinations)) {
                $lookupKey = $name;
            }

            if (isset($seenNames[$lookupKey]) || !array_key_exists($lookupKey, $destinations)) {
                return null;
            }
            $seenNames[$lookupKey] = true;

            return $this->destinationPageIndex($destinations[$lookupKey], $objects, $pageIndexes, $destinations, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            if (
                $this->resolvedDictionaryHasDuplicateBoundaryKeys($destination, $objects, self::DESTINATION_DICTIONARY_BOUNDARY_KEYS)
                || $this->actionDictionaryValueHasTrailingOperands($destination, 'D')
            ) {
                return null;
            }

            $localDestination = $this->localDestinationDictionaryValue($dict, $objects);
            if ($localDestination !== null) {
                return $this->destinationPageIndex($localDestination['value'], $objects, $pageIndexes, $destinations, $seenNames);
            }
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }
        if (!$this->destinationArrayViewModeIsValid($array, $objects)) {
            return null;
        }

        $first = $array[0];
        $pageObjectNumber = $this->validReferenceObjectNumber($first, $objects);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        if (is_int($first)) {
            return $this->boundedDestinationPageIndex($first, $pageIndexes);
        }

        return $this->destinationPageIndex($first, $objects, $pageIndexes, $destinations, $seenNames);
    }

    /**
     * @param list<mixed> $array
     * @param array<int, mixed> $objects
     */
    private function destinationArrayViewModeIsValid(array $array, array $objects): bool
    {
        if (count($array) < 2) {
            return true;
        }

        if ($this->isReferenceValue($array[1] ?? null) && !$this->referenceTargetsSingleTopLevelValue($array[1], $objects)) {
            return false;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null, $objects));

        return $viewMode !== null
            && isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])
            && $this->destinationViewCoordinateOperandsAreValid($array, $objects, $viewMode);
    }

    /**
     * @param list<mixed> $array
     * @param array<int, mixed> $objects
     */
    private function destinationViewCoordinateOperandsAreValid(array $array, array $objects, string $viewMode): bool
    {
        $requiredOperands = match ($viewMode) {
            'XYZ' => [2 => true, 3 => true, 4 => true],
            'FitH', 'FitBH', 'FitV', 'FitBV' => [2 => false],
            'FitR' => [2 => false, 3 => false, 4 => false, 5 => false],
            default => [],
        };

        foreach ($requiredOperands as $index => $allowsNull) {
            if (!array_key_exists($index, $array)) {
                return false;
            }

            if ($this->isReferenceValue($array[$index]) && !$this->referenceTargetsSingleTopLevelValue($array[$index], $objects)) {
                return false;
            }

            $resolved = $this->resolveValue($array[$index], $objects);
            if ($resolved === null) {
                if ($allowsNull) {
                    continue;
                }

                return false;
            }

            if (!is_int($resolved) && !is_float($resolved)) {
                return false;
            }
        }

        for ($index = 2 + count($requiredOperands), $count = count($array); $index < $count; $index++) {
            if (!$this->destinationSurplusOperandIsBenign($array[$index], $objects)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function destinationSurplusOperandIsBenign(mixed $value, array $objects): bool
    {
        if ($this->isReferenceValue($value) && !$this->referenceTargetsSingleTopLevelValue($value, $objects)) {
            return false;
        }

        if ($this->isReferenceValue($value) && $this->validReferenceObjectNumber($value, $objects) === null) {
            return false;
        }

        $resolved = $this->resolveValue($value, $objects);

        return $resolved === null || is_int($resolved) || is_float($resolved);
    }

    /**
     * @param array<string, mixed> $dict
     * @return array{value: mixed}|null
     */
    private function localDestinationDictionaryValue(array $dict, array $objects): ?array
    {
        if (!array_key_exists('D', $dict)) {
            return null;
        }

        if (array_key_exists('S', $dict)) {
            if (
                $this->isReferenceValue($dict['S'])
                && !$this->referenceTargetsSingleTopLevelValue($dict['S'], $objects)
            ) {
                return null;
            }

            $type = $this->nameValue($this->resolveValue($dict['S'], $objects));
            if ($type !== 'GoTo') {
                return null;
            }
        }

        return ['value' => $dict['D']];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $char = $value[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<' || $pair === '>>') {
                $tokens[] = $pair;
                $index += 2;
                continue;
            }

            if ($char === '[' || $char === ']') {
                $tokens[] = $char;
                $index++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($value, $index);
                continue;
            }

            if ($char === '<') {
                $tokens[] = $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '/') {
                $start = $index;
                $index++;
                while ($index < $length && !$this->isDelimiter($value[$index])) {
                    $index++;
                }
                $tokens[] = substr($value, $start, $index - $start);
                continue;
            }

            if ($this->isDelimiter($char)) {
                $index++;
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($value[$index])) {
                $index++;
            }
            $tokens[] = substr($value, $start, $index - $start);
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function parseValue(array $tokens, int &$index): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token === '<<') {
            $index++;
            $items = [];
            $entryCounts = [];
            $selectedEntryIndexes = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
                $key = $tokens[$index] ?? '';
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    continue;
                }

                $decodedKey = $this->decodePdfName(substr($key, 1));
                $entryIndex = $entryCounts[$decodedKey] ?? 0;
                $entryCounts[$decodedKey] = $entryIndex + 1;
                $selectedEntryIndexes[$decodedKey] = $entryIndex;
                $items[$decodedKey] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === '>>') {
                $index++;
            }

            $dictionary = ['pdfType' => 'dict', 'items' => $items];
            $duplicateKeys = [];
            $duplicateEntryCounts = [];
            $duplicateSelectedEntryIndexes = [];
            foreach ($entryCounts as $key => $count) {
                if ($count <= 1) {
                    continue;
                }

                $duplicateKeys[] = $key;
                $duplicateEntryCounts[$key] = $count;
                $duplicateSelectedEntryIndexes[$key] = $selectedEntryIndexes[$key];
            }
            if ($duplicateKeys !== []) {
                $dictionary['duplicateKeyReview'] = [
                    'source' => 'dictionary_duplicate_keys',
                    'review_only' => true,
                    'payload_included' => false,
                    'visible_text_source' => false,
                    'selected_entry_policy' => 'last_top_level_entry',
                    'keys' => $duplicateKeys,
                    'declared_entry_counts' => $duplicateEntryCounts,
                    'selected_entry_indexes' => $duplicateSelectedEntryIndexes,
                ];
            }

            return $dictionary;
        }

        if ($token === '[') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== ']') {
                $items[] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === ']') {
                $index++;
            }

            return ['pdfType' => 'array', 'items' => $items];
        }

        if (
            preg_match('/^[+-]?\d+$/', $token) === 1
            && preg_match('/^[+-]?\d+$/', (string) ($tokens[$index + 1] ?? '')) === 1
            && ($tokens[$index + 2] ?? null) === 'R'
        ) {
            $generation = (int) $tokens[$index + 1];
            $index += 3;

            return [
                'pdfType' => 'ref',
                'object' => (int) $token,
                'generation' => $generation,
            ];
        }

        $index++;
        if (str_starts_with($token, '/')) {
            return ['pdfType' => 'name', 'value' => $this->decodePdfName(substr($token, 1))];
        }

        if (str_starts_with($token, '(')) {
            $bytes = $this->literalStringBytes($token);

            return ['pdfType' => 'string', 'value' => $this->decodePdfStringBytes($bytes), 'bytes' => $bytes];
        }

        if (str_starts_with($token, '<')) {
            $bytes = $this->hexStringBytes($token);

            return ['pdfType' => 'string', 'value' => $this->decodePdfStringBytes($bytes), 'bytes' => $bytes];
        }

        if ($token === 'null') {
            return null;
        }

        if ($token === 'true' || $token === 'false') {
            return $token === 'true';
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        return ['pdfType' => 'keyword', 'value' => $token];
    }

    private function readLiteralToken(string $value, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($value);
        while ($index < $length) {
            $char = $value[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function readHexToken(string $value, int &$index): string
    {
        $start = $index;
        $length = strlen($value);
        $index++;
        while ($index < $length && $value[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function resolveValue(mixed $value, array $objects, int $depth = 0): mixed
    {
        $objectNumber = $this->validReferenceObjectNumber($value, $objects);
        if ($this->isReferenceValue($value) && ($objectNumber === null || $depth > 20)) {
            return null;
        }
        if ($objectNumber === null || $depth > 20 || !array_key_exists($objectNumber, $objects)) {
            return $value;
        }

        $generation = $this->referenceGeneration($value);
        $target = $this->objectValueForReference($objects, $objectNumber, $generation);

        return $target === null ? null : $this->resolveValue($target, $objects, $depth + 1);
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function resolveDictionary(mixed $value, array $objects): ?array
    {
        return $this->dictionaryItems($this->resolveValue($value, $objects));
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<mixed>|null
     */
    private function resolveArray(mixed $value, array $objects): ?array
    {
        return $this->arrayItems($this->resolveValue($value, $objects));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)
            ? $value['items']
            : null;
    }

    /**
     * @return array<string, true>
     */
    private function dictionaryDuplicateKeySet(mixed $value): array
    {
        if (
            !is_array($value)
            || ($value['pdfType'] ?? null) !== 'dict'
            || !is_array($value['duplicateKeyReview'] ?? null)
            || !is_array($value['duplicateKeyReview']['keys'] ?? null)
        ) {
            return [];
        }

        $keys = [];
        foreach ($value['duplicateKeyReview']['keys'] as $key) {
            if (is_string($key)) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $keys
     */
    private function dictionaryHasDuplicateBoundaryKeys(mixed $value, array $keys): bool
    {
        $duplicateKeys = $this->dictionaryDuplicateKeySet($value);
        foreach ($keys as $key) {
            if (isset($duplicateKeys[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $objects
     * @param list<string> $keys
     */
    private function resolvedDictionaryHasDuplicateBoundaryKeys(mixed $value, array $objects, array $keys): bool
    {
        return $this->dictionaryHasDuplicateBoundaryKeys($this->resolveValue($value, $objects), $keys);
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)
            ? array_values($value['items'])
            : null;
    }

    private function referenceObjectNumber(mixed $value): ?int
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'ref' && is_int($value['object'] ?? null)
            ? $value['object']
            : null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function validReferenceObjectNumber(mixed $value, array $objects): ?int
    {
        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber === null || !array_key_exists($objectNumber, $objects)) {
            return null;
        }

        return $this->objectValueForReference($objects, $objectNumber, $this->referenceGeneration($value)) === null
            ? null
            : $objectNumber;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function objectValueForReference(array $objects, int $objectNumber, int $generation): mixed
    {
        if (($this->objectGenerations[$objectNumber] ?? 0) === $generation) {
            return $objects[$objectNumber] ?? null;
        }

        if (!$this->objectSelectionHasXref && array_key_exists($generation, $this->objectValuesByGeneration[$objectNumber] ?? [])) {
            return $this->objectValuesByGeneration[$objectNumber][$generation];
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function referenceTargetsSingleTopLevelValue(mixed $value, array $objects): bool
    {
        $objectNumber = $this->validReferenceObjectNumber($value, $objects);

        return $objectNumber !== null && ($this->objectSingleTopLevelValues[$objectNumber] ?? false);
    }

    private function referenceGeneration(mixed $value): int
    {
        return is_array($value) && is_int($value['generation'] ?? null) ? $value['generation'] : 0;
    }

    private function isReferenceValue(mixed $value): bool
    {
        return $this->referenceObjectNumber($value) !== null;
    }

    /**
     * @return array{pdfType: string, object: int, generation: int}
     */
    private function refValue(int $objectNumber): array
    {
        return [
            'pdfType' => 'ref',
            'object' => $objectNumber,
            'generation' => $this->objectGenerations[$objectNumber] ?? 0,
        ];
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringOrNameValue(mixed $value): ?string
    {
        if (is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)) {
            return $value['value'];
        }

        return $this->nameValue($value);
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
    }

    private function decodeLiteralString(string $token): string
    {
        return $this->decodePdfStringBytes($this->literalStringBytes($token));
    }

    private function literalStringBytes(string $token): string
    {
        $bytes = substr($token, 1, -1);
        $decoded = '';
        $length = strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $char = $bytes[$index];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                break;
            }
            $next = $bytes[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && ($bytes[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if (preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($count = 0; $count < 2 && preg_match('/[0-7]/', (string) ($bytes[$index + 1] ?? '')) === 1; $count++) {
                    $octal .= $bytes[++$index];
                }
                $decoded .= chr(octdec($octal) & 0xff);
                continue;
            }

            $decoded .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $decoded;
    }

    private function decodeHexString(string $token): string
    {
        return $this->decodePdfStringBytes($this->hexStringBytes($token));
    }

    private function hexStringBytes(string $token): string
    {
        $hex = preg_replace('/\s+/', '', substr($token, 1, -1));
        if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $bytes;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16BE')) {
                return '';
            }

            $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16LE')) {
                return '';
            }

            $decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $decoded;
        }
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $utf8 = substr($bytes, 3);

            return mb_check_encoding($utf8, 'UTF-8') ? $utf8 : '';
        }

        return $this->decodePdfDocEncoding($bytes);
    }

    private function decodePdfDocEncoding(string $bytes): string
    {
        $decoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            $codepoint = self::PDF_DOC_ENCODING_OVERRIDES[$byte] ?? $byte;
            $char = mb_chr($codepoint, 'UTF-8');
            if ($char !== false) {
                $decoded .= $char;
            }
        }

        return $decoded;
    }
}
