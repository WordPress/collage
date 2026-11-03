<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class MarkerAppPreview
{
    private const DEFAULT_PAGE_BBOX = [0.0, 0.0, 612.0, 792.0];
    private const MAX_PAGE_LABEL_GENERATED_SUFFIX_BYTES = 4096;
    private const MAX_PAGE_LABEL_PREFIX_BYTES = 4096;
    private const MAX_PAGE_LABEL_TREE_DEPTH = 100;
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

    private PdfImageRenderer $renderer;

    /**
     * @var array<int, array<int, string>>
     */
    private array $directObjectBodiesByGeneration = [];

    public function __construct(?PdfImageRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new PdfImageRenderer();
    }

    /**
     * Native boundary for marker_app.py::open_pdf plus page_count metadata.
     *
     * @return array{page_count: int, pages: list<array<string, mixed>>}
     */
    public function openPdfSummary(string $pdfBytes): array
    {
        $pages = $this->pageInventory($pdfBytes);

        return [
            'page_count' => count($pages),
            'pages' => $pages,
        ];
    }

    /**
     * Native boundary for marker_app.py::page_count.
     */
    public function pageCount(string $pdfBytes): int
    {
        return $this->openPdfSummary($pdfBytes)['page_count'];
    }

    /**
     * Native boundary for catalog /PageLabels number-tree metadata.
     *
     * @return list<string>
     */
    public function pageLabels(string $pdfBytes): array
    {
        return array_column($this->openPdfSummary($pdfBytes)['pages'], 'page_label');
    }

    /**
     * Plans marker_app.py::get_page_image without pypdfium/PIL rasterization.
     *
     * @return array<string, mixed>
     */
    public function getPageImagePlan(string $pdfBytes, int $pageNumber, float $dpi = 96.0): array
    {
        $summary = $this->openPdfSummary($pdfBytes);
        if ($pageNumber < 1 || $pageNumber > $summary['page_count']) {
            throw new InvalidArgumentException('Preview page number must be within the PDF page count.');
        }

        $page = $summary['pages'][$pageNumber - 1];

        return [
            'page_number' => $pageNumber,
            'page_index' => $pageNumber - 1,
            'page_label' => $page['page_label'],
            'page_count' => $summary['page_count'],
            'object_id' => $page['object_id'],
            'bbox_source' => $page['bbox_source'],
            'media_box' => $page['media_box'],
            'media_box_source' => $page['media_box_source'],
            'crop_box' => $page['crop_box'],
            'crop_box_source' => $page['crop_box_source'],
            'bleed_box' => $page['bleed_box'],
            'bleed_box_source' => $page['bleed_box_source'],
            'trim_box' => $page['trim_box'],
            'trim_box_source' => $page['trim_box_source'],
            'art_box' => $page['art_box'],
            'art_box_source' => $page['art_box_source'],
            'rotation' => $page['rotation'],
            'rotation_source' => $page['rotation_source'],
            'user_unit' => $page['user_unit'],
            'user_unit_source' => $page['user_unit_source'],
            'effective_crop_box' => $page['effective_crop_box'],
            'effective_crop_box_source' => $page['effective_crop_box_source'],
            'crop_box_clipped_to_media' => $page['crop_box_clipped_to_media'],
            'crop_box_intersects_media' => $page['crop_box_intersects_media'],
            'preview_zero_area' => $page['preview_zero_area'],
            'rotation_swaps_axes' => $page['rotation_swaps_axes'],
            'user_unit_applied_to_preview' => $page['user_unit_applied_to_preview'],
            'boundary_notes' => $page['boundary_notes'],
            'display_page_size' => $this->displayPageSize($page['bbox'], $page['rotation']),
            'physical_page_size' => $this->displayPageSize($page['bbox'], $page['rotation'], $page['user_unit']),
            'dpi' => $dpi,
            'scale' => $this->renderer->renderScale($dpi),
            'annotation_mode' => 'pypdfium-default',
            'color_mode' => 'RGB',
            'pypdfium_page_indices' => [$pageNumber - 1],
            'page_bbox' => $page['bbox'],
            'rendered_image_size' => $this->renderer->renderedImageSize($page['bbox'], $dpi, $page['rotation'], $page['user_unit']),
        ];
    }

    /**
     * Bundles marker_app.py-style page preview geometry with native review
     * overlays. Layout pages are supplied by the caller; this method does not
     * run Surya, pypdfium, PIL, PDF actions, or external PDF tooling.
     *
     * @param list<array<string, mixed>> $suppliedPages
     * @return array<string, mixed>
     */
    public function getPageLayoutPreviewBundle(
        string $pdfBytes,
        int $pageNumber,
        array $suppliedPages = [],
        float $dpi = 96.0
    ): array {
        $imagePlan = $this->getPageImagePlan($pdfBytes, $pageNumber, $dpi);
        $pageIndex = $imagePlan['page_index'];
        $pageReview = $this->pageReviewByPageIndex($pdfBytes)[$pageIndex] ?? [];
        $annotationPage = $this->annotationReviewByPageIndex($pdfBytes)[$pageIndex] ?? [];
        $suppliedPage = $this->suppliedPreviewPage($suppliedPages, $pageIndex);

        $annotations = $this->annotationPreviewRows(
            is_array($annotationPage['annotations'] ?? null) ? $annotationPage['annotations'] : [],
            $imagePlan
        );
        $textMarkupRows = $this->textMarkupPreviewRows(
            is_array($pageReview['text_markup_annotations'] ?? null) ? $pageReview['text_markup_annotations'] : [],
            $imagePlan
        );
        $annotationStructureRows = $this->annotationStructurePreviewRows(
            is_array($pageReview['annotation_structure_parent_rows'] ?? null) ? $pageReview['annotation_structure_parent_rows'] : [],
            $imagePlan
        );
        $structureRows = $this->structureMarkedContentPreviewRows(
            is_array($pageReview['structure_marked_content'] ?? null) ? $pageReview['structure_marked_content'] : []
        );
        $layoutBlocks = $this->layoutPreviewBlocks($suppliedPage, $imagePlan);

        return [
            'source' => 'marker_app_page_annotations_structtree_layout_preview_bundle',
            'page_number' => $pageNumber,
            'page_index' => $pageIndex,
            'page_count' => $imagePlan['page_count'],
            'page_object' => $imagePlan['object_id'],
            'image_plan' => $imagePlan,
            'page_review' => $this->compactPageReview($pageReview),
            'layout_blocks' => $layoutBlocks,
            'annotations' => $annotations,
            'text_markup_annotations' => $textMarkupRows,
            'annotation_structure_parent_rows' => $annotationStructureRows,
            'structure_marked_content' => $structureRows,
            'layout_block_count' => count($layoutBlocks),
            'annotation_count' => count($annotations),
            'text_markup_annotation_count' => count($textMarkupRows),
            'annotation_structure_parent_row_count' => count($annotationStructureRows),
            'structure_marked_content_count' => count($structureRows),
            'review_only' => true,
            'visible_text_source' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
            'executes_pdf_actions' => false,
            'overlay_coordinate_space' => 'rendered_image_pixels',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageReviewByPageIndex(string $pdfBytes): array
    {
        $reviews = [];
        foreach ((new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdfBytes) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $pnum = $row['pnum'] ?? null;
            if (is_int($pnum)) {
                $reviews[$pnum] = $row;
            }
        }

        return $reviews;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function annotationReviewByPageIndex(string $pdfBytes): array
    {
        $reviews = [];
        foreach ((new PdfAnnotationExtractor())->extractPageAnnotations($pdfBytes) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $pnum = $row['pnum'] ?? null;
            if (is_int($pnum)) {
                $reviews[$pnum] = $row;
            }
        }

        return $reviews;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return array<string, mixed>|null
     */
    private function suppliedPreviewPage(array $pages, int $pageIndex): ?array
    {
        foreach ($pages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $pnum = $page['pnum'] ?? $page['page_index'] ?? $index;
            if (is_int($pnum) && $pnum === $pageIndex) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $pageReview
     * @return array<string, mixed>
     */
    private function compactPageReview(array $pageReview): array
    {
        if ($pageReview === []) {
            return [];
        }

        $review = [
            'source' => 'marker_app_page_review_preview_context',
            'pnum' => $pageReview['pnum'] ?? null,
            'page_number' => $pageReview['page_number'] ?? null,
            'page_label' => $pageReview['page_label'] ?? null,
            'page_object' => $pageReview['page_object'] ?? null,
            'struct_parents' => $pageReview['struct_parents'] ?? null,
            'parent_tree' => $pageReview['parent_tree'] ?? null,
            'piece_info' => $pageReview['piece_info'] ?? null,
            'page_associated_files' => $pageReview['page_associated_files'] ?? null,
            'page_presentation' => $pageReview['page_presentation'] ?? null,
            'resources' => $pageReview['resources'] ?? null,
            'review_only' => true,
            'visible_text_source' => false,
        ];

        return $this->compactRow($review);
    }

    /**
     * @param array<string, mixed>|null $page
     * @param array<string, mixed> $imagePlan
     * @return list<array<string, mixed>>
     */
    private function layoutPreviewBlocks(?array $page, array $imagePlan): array
    {
        if ($page === null) {
            return [];
        }

        $blocks = is_array($page['blocks'] ?? null) ? array_values($page['blocks']) : [];
        $rows = [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            $bbox = $this->bboxFromValue($block['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $row = [
                'source' => 'supplied_marker_layout_preview_block',
                'block_index' => $index,
                'block_type' => $this->blockTypeLabel($block),
                'bbox' => $bbox,
                'preview_bbox' => $this->previewBbox($bbox, $imagePlan),
                'text_preview' => $this->blockTextPreview($block),
                'review_annotation_count' => $this->blockReviewAnnotationCount($block),
                'review_only' => false,
                'visible_text_source' => true,
            ];

            foreach (['layout_label', 'order_position'] as $key) {
                if (array_key_exists($key, $block)) {
                    $row[$key] = $block[$key];
                }
            }

            $rows[] = $this->compactRow($row);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $annotations
     * @param array<string, mixed> $imagePlan
     * @return list<array<string, mixed>>
     */
    private function annotationPreviewRows(array $annotations, array $imagePlan): array
    {
        $rows = [];
        foreach (array_values($annotations) as $index => $annotation) {
            if (!is_array($annotation)) {
                continue;
            }

            $rect = $this->bboxFromValue($annotation['rect'] ?? null);
            $row = [
                'source' => 'page_annotation_preview_overlay',
                'annotation_index' => $index,
                'annotation_object' => $annotation['annotation_object'] ?? null,
                'subtype' => $annotation['subtype'] ?? null,
                'rect' => $rect,
                'preview_bbox' => $rect === null ? null : $this->previewBbox($rect, $imagePlan),
                'contents' => $annotation['contents'] ?? null,
                'title' => $annotation['title'] ?? null,
                'name' => $annotation['name'] ?? null,
                'struct_parent' => $annotation['struct_parent'] ?? null,
                'structure_parent' => $annotation['structure_parent'] ?? null,
                'action_count' => count(is_array($annotation['actions'] ?? null) ? $annotation['actions'] : []),
                'additional_action_count' => count(is_array($annotation['additional_actions'] ?? null) ? $annotation['additional_actions'] : []),
                'executes_actions_on_import' => $annotation['executes_actions_on_import'] ?? false,
                'review_only' => true,
                'visible_text_source' => false,
                'renders_annotation_on_import' => false,
            ];

            $rows[] = $this->compactRow($row);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $markups
     * @param array<string, mixed> $imagePlan
     * @return list<array<string, mixed>>
     */
    private function textMarkupPreviewRows(array $markups, array $imagePlan): array
    {
        $rows = [];
        foreach ($markups as $index => $markup) {
            if (!is_array($markup)) {
                continue;
            }

            $quadPreviewBboxes = [];
            $quadRects = is_array($markup['quad_rects'] ?? null) ? $markup['quad_rects'] : [];
            foreach ($quadRects as $quadRect) {
                $bbox = $this->bboxFromValue($quadRect);
                if ($bbox !== null) {
                    $quadPreviewBboxes[] = $this->previewBbox($bbox, $imagePlan);
                }
            }

            $rect = $this->bboxFromValue($markup['rect'] ?? null);
            $rows[] = $this->compactRow([
                'source' => 'page_text_markup_preview_overlay',
                'markup_index' => $index,
                'annotation_object' => $markup['annotation_object'] ?? null,
                'subtype' => $markup['subtype'] ?? null,
                'rect' => $rect,
                'preview_bbox' => $rect === null ? null : $this->previewBbox($rect, $imagePlan),
                'quad_preview_bboxes' => $quadPreviewBboxes,
                'contents' => $markup['contents'] ?? null,
                'struct_parent' => $markup['struct_parent'] ?? null,
                'structure_parent' => $this->compactRow([
                    'struct_object' => $markup['struct_object'] ?? null,
                    'raw_role' => $markup['raw_role'] ?? null,
                    'role' => $markup['role'] ?? null,
                    'role_mapped' => $markup['role_mapped'] ?? null,
                    'title' => $markup['title'] ?? null,
                    'alternate_text' => $markup['alternate_text'] ?? null,
                    'actual_text' => $markup['actual_text'] ?? null,
                    'associated_file_count' => $markup['associated_file_count'] ?? null,
                    'associated_files' => $markup['associated_files'] ?? null,
                ]),
                'review_only' => true,
                'visible_text_source' => false,
                'renders_markup_on_import' => false,
            ]);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $imagePlan
     * @return list<array<string, mixed>>
     */
    private function annotationStructurePreviewRows(array $rows, array $imagePlan): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rect = $this->bboxFromValue($row['rect'] ?? null);
            $row['preview_source'] = 'page_annotation_struct_parent_preview_overlay';
            $row['preview_index'] = $index;
            if ($rect !== null) {
                $row['preview_bbox'] = $this->previewBbox($rect, $imagePlan);
            }
            $row['review_only'] = true;
            $row['visible_text_source'] = false;

            $out[] = $this->compactRow($row);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function structureMarkedContentPreviewRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['preview_source'] = 'page_structtree_marked_content_preview_context';
            $row['preview_index'] = $index;
            $row['review_only'] = true;
            $row['visible_text_source'] = false;
            $out[] = $this->compactRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockTypeLabel(array $block): string
    {
        foreach (['block_type', 'type', 'label'] as $key) {
            $value = $block[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'Text';
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockTextPreview(array $block): string
    {
        $parts = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineText = '';
            foreach (($line['spans'] ?? []) as $span) {
                if (!is_array($span)) {
                    continue;
                }

                $text = $span['text'] ?? null;
                if (is_string($text)) {
                    $lineText .= $text;
                }
            }

            if ($lineText !== '') {
                $parts[] = $lineText;
            }
        }

        $text = trim(implode(' ', $parts));
        if (strlen($text) > 160) {
            return substr($text, 0, 157) . '...';
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockReviewAnnotationCount(array $block): int
    {
        $count = 0;
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }

            foreach (($line['spans'] ?? []) as $span) {
                if (!is_array($span)) {
                    continue;
                }

                $annotations = $span['review_annotations'] ?? [];
                if (is_array($annotations)) {
                    $count += count($annotations);
                }
            }
        }

        return $count;
    }

    /**
     * @return list<float>|null
     */
    private function bboxFromValue(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        return [
            min($bbox[0], $bbox[2]),
            min($bbox[1], $bbox[3]),
            max($bbox[0], $bbox[2]),
            max($bbox[1], $bbox[3]),
        ];
    }

    /**
     * @param list<float> $bbox
     * @param array<string, mixed> $imagePlan
     * @return list<float>
     */
    private function previewBbox(array $bbox, array $imagePlan): array
    {
        $pageBbox = $this->bboxFromValue($imagePlan['page_bbox'] ?? null) ?? self::DEFAULT_PAGE_BBOX;
        $clipped = $this->intersectBoxes($pageBbox, $bbox);
        $rotation = is_int($imagePlan['rotation'] ?? null) ? $imagePlan['rotation'] : 0;
        $scale = (float) ($imagePlan['scale'] ?? 1.0) * (float) ($imagePlan['user_unit'] ?? 1.0);

        $points = [
            [$clipped[0], $clipped[1]],
            [$clipped[2], $clipped[1]],
            [$clipped[0], $clipped[3]],
            [$clipped[2], $clipped[3]],
        ];

        $mapped = array_map(
            fn (array $point): array => $this->previewPoint($point[0], $point[1], $pageBbox, $rotation, $scale),
            $points
        );
        $xs = array_column($mapped, 0);
        $ys = array_column($mapped, 1);

        return [
            $this->roundedPreviewCoordinate(min($xs)),
            $this->roundedPreviewCoordinate(min($ys)),
            $this->roundedPreviewCoordinate(max($xs)),
            $this->roundedPreviewCoordinate(max($ys)),
        ];
    }

    /**
     * @param list<float> $pageBbox
     * @return array{0: float, 1: float}
     */
    private function previewPoint(float $x, float $y, array $pageBbox, int $rotation, float $scale): array
    {
        $width = max(0.0, $pageBbox[2] - $pageBbox[0]);
        $height = max(0.0, $pageBbox[3] - $pageBbox[1]);
        $x -= $pageBbox[0];
        $y -= $pageBbox[1];

        switch ($this->normalizedRotation($rotation)) {
            case 90:
                $mapped = [$y, $x];
                break;
            case 180:
                $mapped = [$width - $x, $y];
                break;
            case 270:
                $mapped = [$height - $y, $width - $x];
                break;
            default:
                $mapped = [$x, $height - $y];
                break;
        }

        return [$mapped[0] * $scale, $mapped[1] * $scale];
    }

    private function roundedPreviewCoordinate(float $value): float
    {
        $rounded = round($value, 4);

        return abs($rounded) < 0.000001 ? 0.0 : $rounded;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function compactRow(array $row): array
    {
        return array_filter($row, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageInventory(string $pdfBytes): array
    {
        $this->assertPdfBytes($pdfBytes);

        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return [];
        }

        $pages = [];
        $catalogBody = null;
        $rootCatalog = $this->catalogFromTrailerRoot($pdfBytes, $objects);
        if ($rootCatalog !== null) {
            $catalogBody = $rootCatalog['body'];
            $pagesId = $this->reference($catalogBody, 'Pages');
            if ($pagesId !== null && isset($objects[$pagesId])) {
                $pages = $this->uniquePagesByObjectId($this->collectPages($pagesId, $objects));
            }
        }

        if ($pages === [] && $rootCatalog === null) {
            foreach ($objects as $objectId => $object) {
                if ($this->objectType($object['body']) !== 'Catalog') {
                    continue;
                }

                $catalogBody = $object['body'];
                $pagesId = $this->reference($object['body'], 'Pages');
                if ($pagesId !== null && isset($objects[$pagesId])) {
                    $pages = $this->uniquePagesByObjectId($this->collectPages($pagesId, $objects));
                    break;
                }
            }
        }

        if ($pages === []) {
            foreach ($objects as $objectId => $object) {
                if ($this->objectType($object['body']) !== 'Page') {
                    continue;
                }

                $pages[] = [
                    'object_id' => $objectId,
                    ...$this->pageGeometry($object['body'], $objects),
                ];
            }
        }

        foreach ($pages as $index => $page) {
            $pages[$index]['page_index'] = $index;
            $pages[$index]['page_number'] = $index + 1;
        }

        $pageLabels = $this->pageLabelsForInventory($pdfBytes, $catalogBody, $objects, count($pages));
        foreach ($pages as $index => $page) {
            $pages[$index]['page_label'] = $pageLabels[$index] ?? (string) ($index + 1);
        }

        return array_values($pages);
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    private function uniquePagesByObjectId(array $pages): array
    {
        $unique = [];
        $seen = [];
        foreach ($pages as $page) {
            $objectId = $page['object_id'] ?? null;
            if (!is_int($objectId)) {
                continue;
            }

            if (isset($seen[$objectId])) {
                continue;
            }

            $seen[$objectId] = true;
            $unique[] = $page;
        }

        return $unique;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int> $seen
     * @param array<string, mixed> $inherited
     * @return list<array<string, mixed>>
     */
    private function collectPages(
        int $objectId,
        array $objects,
        array $inherited = [],
        array $seen = []
    ): array {
        if (in_array($objectId, $seen, true) || !isset($objects[$objectId])) {
            return [];
        }

        $seen[] = $objectId;
        $body = $objects[$objectId]['body'];
        $type = $this->objectType($body);
        $nextInherited = $this->inheritedPageGeometry($body, $objects, $type, $inherited);

        if ($type === 'Page') {
            return [[
                'object_id' => $objectId,
                ...$this->pageGeometry($body, $objects, $nextInherited),
            ]];
        }

        if ($type !== 'Pages') {
            return [];
        }

        $pages = [];
        foreach ($this->kidReferences($body, $objects) as $kidId) {
            foreach ($this->collectPages($kidId, $objects, $nextInherited, $seen) as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * @return array<int, array{generation: int, body: string}>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $this->directObjectBodiesByGeneration = [];
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objects = [];
        foreach ($matches as $match) {
            $objectId = (int) $match[1];
            $generation = (int) $match[2];
            $body = $match[3];
            $this->directObjectBodiesByGeneration[$objectId][$generation] = $body;
            $objects[$objectId] = [
                'generation' => $generation,
                'body' => $body,
            ];
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return array{object_id: int, generation: int, body: string}|null
     */
    private function catalogFromTrailerRoot(string $pdfBytes, array $objects): ?array
    {
        $reference = $this->trailerRootReference($pdfBytes);
        if ($reference === null) {
            return null;
        }

        $body = $this->objectBodyForReference($objects, $reference['object_id'], $reference['generation'], []);
        if ($body === null || $this->objectType($body) !== 'Catalog') {
            return null;
        }

        return [
            'object_id' => $reference['object_id'],
            'generation' => $reference['generation'],
            'body' => $body,
        ];
    }

    /**
     * @return array{object_id: int, generation: int}|null
     */
    private function trailerRootReference(string $pdfBytes): ?array
    {
        $bodyRanges = $this->directObjectBodyRanges($pdfBytes);
        $startxrefOffset = $this->latestStartxrefTokenOffset($pdfBytes, $bodyRanges);
        $beforeOffset = $startxrefOffset ?? strlen($pdfBytes);
        if ($startxrefOffset !== null) {
            $declaredOffset = $this->startxrefDeclaredOffsetFromOperand(
                substr($pdfBytes, $startxrefOffset + strlen('startxref'), 64)
            );
            if ($declaredOffset !== null) {
                $xrefRoot = $this->xrefSectionRootReferenceAtOffset($pdfBytes, $declaredOffset, $bodyRanges);
                if ($xrefRoot !== null) {
                    return $xrefRoot;
                }
            }
        }

        if (preg_match_all('/\btrailer\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1];
            if (
                $tokenOffset >= $beforeOffset
                || !$this->pdfKeywordAt($pdfBytes, $tokenOffset, 'trailer')
                || $this->offsetInRanges($tokenOffset, $bodyRanges)
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $tokenOffset)
                || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset, $bodyRanges)
            ) {
                continue;
            }

            $offset = $tokenOffset + strlen($matches[0][$index][0]);
            $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
            if (substr($pdfBytes, $offset, 2) !== '<<') {
                continue;
            }

            $dictionary = $this->readBalancedDictionary($pdfBytes, $offset);
            if ($dictionary === null) {
                continue;
            }

            $root = $this->valueAfterName($dictionary[0], 'Root');
            if ($root === null || preg_match('/^(\d+)\s+(\d+)\s+R$/', trim($root), $match) !== 1) {
                continue;
            }

            return [
                'object_id' => (int) $match[1],
                'generation' => (int) $match[2],
            ];
        }

        return null;
    }

    /**
     * @return array{object_id: int, generation: int}|null
     */
    private function xrefSectionRootReferenceAtOffset(
        string $pdfBytes,
        int $offset,
        array $bodyRanges,
        array $seenOffsets = []
    ): ?array {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (
            $offset < 0
            || $offset >= strlen($pdfBytes)
            || isset($seenOffsets[$offset])
            || $this->offsetInRanges($offset, $bodyRanges)
            || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $bodyRanges)
        ) {
            return null;
        }
        $seenOffsets[$offset] = true;

        $xrefStream = $this->xrefStreamDictionaryAtOffset($pdfBytes, $offset);
        if ($xrefStream !== null) {
            $root = $this->referenceValue($this->valueAfterName($xrefStream, 'Root'));
            if ($root !== null) {
                return $root;
            }

            $previousOffset = $this->integerValueFromPdfValue($this->valueAfterName($xrefStream, 'Prev'));
            return $previousOffset === null
                ? null
                : $this->xrefSectionRootReferenceAtOffset($pdfBytes, $previousOffset, $bodyRanges, $seenOffsets);
        }

        $trailer = $this->classicXrefTrailerDictionaryAtOffset($pdfBytes, $offset, $bodyRanges);
        if ($trailer === null) {
            return null;
        }

        $root = $this->referenceValue($this->valueAfterName($trailer, 'Root'));
        if ($root !== null) {
            return $root;
        }

        $hybridOffset = $this->integerValueFromPdfValue($this->valueAfterName($trailer, 'XRefStm'));
        if ($hybridOffset !== null && !isset($seenOffsets[$hybridOffset])) {
            $hybridRoot = $this->xrefSectionRootReferenceAtOffset($pdfBytes, $hybridOffset, $bodyRanges, $seenOffsets);
            if ($hybridRoot !== null) {
                return $hybridRoot;
            }
        }

        $previousOffset = $this->integerValueFromPdfValue($this->valueAfterName($trailer, 'Prev'));
        return $previousOffset === null
            ? null
            : $this->xrefSectionRootReferenceAtOffset($pdfBytes, $previousOffset, $bodyRanges, $seenOffsets);
    }

    /**
     * @return array{object_id: int, generation: int}|null
     */
    private function referenceValue(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $reference = $this->pdfIndirectReferenceValueAt($value, 0);
        if ($reference === null) {
            return null;
        }

        $tailOffset = $this->skipPdfWhitespace($value, $reference['endOffset']);
        if ($tailOffset < strlen($value)) {
            return null;
        }

        return [
            'object_id' => $reference['objectNumber'],
            'generation' => $reference['generation'],
        ];
    }

    private function integerValueFromPdfValue(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : null;
    }

    private function xrefStreamDictionaryAtOffset(string $pdfBytes, int $offset): ?string
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $match, 0, $offset) !== 1) {
            return null;
        }

        $body = $match[3];
        if ($this->objectType($body) !== 'XRef') {
            return null;
        }

        $dictionaryOffset = strpos($body, '<<');
        if (!is_int($dictionaryOffset)) {
            return null;
        }

        $dictionary = $this->readBalancedDictionary($body, $dictionaryOffset);
        return $dictionary[0] ?? null;
    }

    /**
     * @param list<array{start: int, end: int}> $bodyRanges
     */
    private function classicXrefTrailerDictionaryAtOffset(string $pdfBytes, int $offset, array $bodyRanges): ?string
    {
        if (
            !$this->pdfKeywordAt($pdfBytes, $offset, 'xref')
            || $this->offsetInRanges($offset, $bodyRanges)
            || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $offset, $bodyRanges)
        ) {
            return null;
        }

        $index = $offset + strlen('xref');
        $length = strlen($pdfBytes);
        while ($index < $length) {
            if (substr($pdfBytes, $index, 5) === '%%EOF' || $this->pdfKeywordAt($pdfBytes, $index, 'startxref')) {
                return null;
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'trailer')) {
                $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $index + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
                    return null;
                }

                $dictionary = $this->readBalancedDictionary($pdfBytes, $dictionaryOffset);
                return $dictionary[0] ?? null;
            }

            $skipped = $this->skipCompositeValueBytes($pdfBytes, $index);
            if ($skipped !== null) {
                $index = $skipped;
                continue;
            }

            $index++;
        }

        return null;
    }

    /**
     * @param list<array{start: int, end: int}> $bodyRanges
     */
    private function latestStartxrefTokenOffset(string $pdfBytes, array $bodyRanges): ?int
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1];
            if (
                !$this->pdfKeywordAt($pdfBytes, $tokenOffset, 'startxref')
                || $this->offsetInRanges($tokenOffset, $bodyRanges)
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $tokenOffset)
                || $this->tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset, $bodyRanges)
            ) {
                continue;
            }

            if ($this->startxrefDeclaredOffsetFromOperand(substr($pdfBytes, $tokenOffset + strlen('startxref'), 64)) === null) {
                continue;
            }

            return $tokenOffset;
        }

        return null;
    }

    private function startxrefDeclaredOffsetFromOperand(string $operandBytes): ?int
    {
        $offset = 0;
        $length = strlen($operandBytes);
        while ($offset < $length && (ctype_space($operandBytes[$offset]) || $operandBytes[$offset] === "\0")) {
            $offset++;
        }

        if (preg_match('/\G[+-]?\d+/s', $operandBytes, $match, 0, $offset) !== 1) {
            return 0;
        }

        $after = $offset + strlen($match[0]);
        while ($after < $length) {
            $char = $operandBytes[$after];
            if ($char === "\n" || $char === "\r" || $char === '%') {
                return (int) $match[0];
            }
            if (!ctype_space($char) && $char !== "\0") {
                return null;
            }

            $after++;
        }

        return (int) $match[0];
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    private function directObjectBodyRanges(string $pdfBytes): array
    {
        if (preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $ranges = [];
        foreach ($matches as $match) {
            $body = $match[2][0];
            $start = $match[2][1];
            $ranges[] = [
                'start' => $start,
                'end' => $start + strlen($body),
            ];
        }

        return $ranges;
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function offsetInRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function pdfKeywordAt(string $value, int $offset, string $keyword): bool
    {
        $keywordLength = strlen($keyword);
        if (substr($value, $offset, $keywordLength) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $value[$offset - 1];
            if ($before === '/' || (!ctype_space($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + $keywordLength;
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return ctype_space($after) || str_contains('[]()<>{}/%', $after);
    }

    private function tokenStartsInPdfCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;
        $commentOffset = strpos($pdfBytes, '%', $lineStart);

        return $commentOffset !== false && $commentOffset < $tokenOffset;
    }

    /**
     * @param list<array{start: int, end: int}> $bodyRanges
     */
    private function tokenStartsInsidePdfCompositeToken(string $pdfBytes, int $tokenOffset, array $bodyRanges): bool
    {
        $length = strlen($pdfBytes);
        $index = 0;
        while ($index < $tokenOffset && $index < $length) {
            foreach ($bodyRanges as $range) {
                if ($index >= $range['start'] && $index <= $range['end']) {
                    $index = $range['end'] + 1;
                    continue 2;
                }
            }

            $char = $pdfBytes[$index];
            if ($char === '%') {
                while ($index < $length && $pdfBytes[$index] !== "\n" && $pdfBytes[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $literal = $this->readBalancedLiteralString($pdfBytes, $index);
                if ($literal !== null) {
                    if ($tokenOffset > $index && $tokenOffset < $literal[1]) {
                        return true;
                    }
                    $index = $literal[1];
                    continue;
                }
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $index);
            if ($compositeEnd !== null) {
                if ($tokenOffset > $index && $tokenOffset < $compositeEnd) {
                    return true;
                }
                $index = $compositeEnd;
                continue;
            }

            $index++;
        }

        return false;
    }

    private function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        if ($pdfBytes[$offset] === '[') {
            $array = $this->readBalancedArray($pdfBytes, $offset);
            return $array[1] ?? null;
        }

        if (substr($pdfBytes, $offset, 2) === '<<') {
            $dictionary = $this->readBalancedDictionary($pdfBytes, $offset);
            return $dictionary[1] ?? null;
        }

        if ($pdfBytes[$offset] === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
            $hex = $this->readHexString($pdfBytes, $offset);
            return $hex[1] ?? null;
        }

        return null;
    }

    private function objectType(string $body): ?string
    {
        if (!preg_match('/\/Type\s*\/([A-Za-z0-9_-]+)/', $body, $match)) {
            return null;
        }

        return $match[1];
    }

    private function reference(string $body, string $name): ?int
    {
        if (!preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return list<int>
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function kidReferences(string $body, array $objects): array
    {
        if (!preg_match('/\/Kids\s*(?:\[(.*?)\]|(\d+)\s+\d+\s+R\b)/s', $body, $match)) {
            return [];
        }

        $kidsBody = $match[1] ?? '';
        if ($kidsBody === '' && isset($match[2])) {
            $objectId = (int) $match[2];
            $objectBody = isset($objects[$objectId]) ? trim($objects[$objectId]['body']) : '';
            $kidsBody = preg_match('/^\[(.*?)\]$/s', $objectBody, $objectMatch) ? $objectMatch[1] : '';
        }

        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $kidsBody, $refs)) {
            return [];
        }

        return array_map(static fn (string $value): int => (int) $value, $refs[1]);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<string, mixed> $inherited
     * @return array<string, mixed>
     */
    private function inheritedPageGeometry(string $body, array $objects, ?string $type, array $inherited): array
    {
        $source = $type === 'Page' ? 'page' : 'pages';
        $mediaBox = $this->boxValue($body, 'MediaBox', $objects);
        if ($mediaBox !== null) {
            $inherited['media_box'] = $mediaBox;
            $inherited['media_box_source'] = $source;
        }

        $cropBox = $this->boxValue($body, 'CropBox', $objects);
        if ($cropBox !== null) {
            $inherited['crop_box'] = $cropBox;
            $inherited['crop_box_source'] = $source;
        }

        $rotation = $this->rotationValue($body, $objects);
        if ($rotation !== null) {
            $inherited['rotation'] = $rotation;
            $inherited['rotation_source'] = $source;
        }

        return $inherited;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<string, mixed> $inherited
     * @return array<string, mixed>
     */
    private function pageGeometry(string $pageBody, array $objects, array $inherited = []): array
    {
        if ($inherited === []) {
            $inherited = $this->parentInheritedGeometry($pageBody, $objects);
        }

        $source = 'page';
        $mediaBox = $this->boxValue($pageBody, 'MediaBox', $objects);
        $mediaBoxSource = $source;
        if ($mediaBox === null) {
            $mediaBox = $inherited['media_box'] ?? self::DEFAULT_PAGE_BBOX;
            $mediaBoxSource = $inherited['media_box_source'] ?? 'default';
        }

        $cropBox = $this->boxValue($pageBody, 'CropBox', $objects);
        $cropBoxSource = $source;
        if ($cropBox === null) {
            $cropBox = $inherited['crop_box'] ?? $mediaBox;
            $cropBoxSource = $inherited['crop_box_source'] ?? 'media_box';
        }

        $rotation = $this->rotationValue($pageBody, $objects);
        $rotationSource = $source;
        if ($rotation === null) {
            $rotation = $inherited['rotation'] ?? 0;
            $rotationSource = $inherited['rotation_source'] ?? 'default';
        }

        $userUnit = $this->numberValue($pageBody, 'UserUnit', $objects);
        $userUnitSource = 'page';
        if ($userUnit === null || $userUnit <= 0.0) {
            $userUnit = 1.0;
            $userUnitSource = 'default';
        }

        $bbox = $this->intersectBoxes($mediaBox, $cropBox);
        $cropBoxClippedToMedia = !$this->sameBox($cropBox, $bbox);
        $previewZeroArea = !$this->hasPositiveArea($bbox);
        $rotationSwapsAxes = in_array($rotation, [90, 270], true);
        $userUnitAppliedToPreview = abs($userUnit - 1.0) > 0.000001;
        $bboxSource = $cropBoxSource === 'media_box' ? $mediaBoxSource : 'crop_box';
        $bleedBox = $this->boxValue($pageBody, 'BleedBox', $objects);
        $trimBox = $this->boxValue($pageBody, 'TrimBox', $objects);
        $artBox = $this->boxValue($pageBody, 'ArtBox', $objects);

        return [
            'bbox' => $bbox,
            'bbox_source' => $bboxSource,
            'media_box' => $mediaBox,
            'media_box_source' => $mediaBoxSource,
            'crop_box' => $cropBox,
            'crop_box_source' => $cropBoxSource,
            'bleed_box' => $bleedBox ?? $cropBox,
            'bleed_box_source' => $bleedBox === null ? 'crop_box' : 'page',
            'trim_box' => $trimBox ?? $cropBox,
            'trim_box_source' => $trimBox === null ? 'crop_box' : 'page',
            'art_box' => $artBox ?? $cropBox,
            'art_box_source' => $artBox === null ? 'crop_box' : 'page',
            'rotation' => $rotation,
            'rotation_source' => $rotationSource,
            'user_unit' => $userUnit,
            'user_unit_source' => $userUnitSource,
            'effective_crop_box' => $bbox,
            'effective_crop_box_source' => $cropBoxClippedToMedia ? 'crop_box_clipped_to_media_box' : $bboxSource,
            'crop_box_clipped_to_media' => $cropBoxClippedToMedia,
            'crop_box_intersects_media' => $this->hasPositiveIntersection($mediaBox, $cropBox),
            'preview_zero_area' => $previewZeroArea,
            'rotation_swaps_axes' => $rotationSwapsAxes,
            'user_unit_applied_to_preview' => $userUnitAppliedToPreview,
            'boundary_notes' => $this->previewBoundaryNotes($cropBoxClippedToMedia, $previewZeroArea, $rotationSwapsAxes, $userUnitAppliedToPreview),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return array<string, mixed>
     */
    private function parentInheritedGeometry(string $pageBody, array $objects): array
    {
        $ancestors = [];
        $seen = [];
        $parentId = $this->reference($pageBody, 'Parent');
        while ($parentId !== null && !isset($seen[$parentId]) && isset($objects[$parentId])) {
            $seen[$parentId] = true;
            $body = $objects[$parentId]['body'];
            if ($this->objectType($body) !== 'Pages') {
                break;
            }

            $ancestors[] = $body;
            $parentId = $this->reference($body, 'Parent');
        }

        $inherited = [];
        foreach (array_reverse($ancestors) as $body) {
            $inherited = $this->inheritedPageGeometry($body, $objects, $this->objectType($body), $inherited);
        }

        return $inherited;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<float>|null
     */
    private function boxValue(string $body, string $name, array $objects): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\[([^\]]+)\]/s', $body, $match) === 1) {
            return $this->boxFromNumbers($match[1], $objects);
        }

        $objectId = $this->reference($body, $name);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        $objectBody = trim($objects[$objectId]['body']);
        if (preg_match('/^\[([^\]]+)\]$/s', $objectBody, $match) === 1) {
            return $this->boxFromNumbers($match[1], $objects);
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<float>|null
     */
    private function boxFromNumbers(string $body, array $objects): ?array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+R\b|[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $body, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $box = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $value = $this->numericObjectValue((int) $match[1], $objects);
                if ($value === null) {
                    return null;
                }

                $box[] = $value;
            } else {
                $box[] = (float) $match[0];
            }

            if (count($box) === 4) {
                break;
            }
        }

        if (count($box) < 4) {
            return null;
        }

        return [
            min($box[0], $box[2]),
            min($box[1], $box[3]),
            max($box[0], $box[2]),
            max($box[1], $box[3]),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param array<int, true> $seen
     */
    private function numericObjectValue(int $objectId, array $objects, array $seen = []): ?float
    {
        if (isset($seen[$objectId]) || !isset($objects[$objectId])) {
            return null;
        }

        $seen[$objectId] = true;
        $body = trim($objects[$objectId]['body']);
        if (preg_match('/^[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?$/', $body) === 1) {
            return (float) $body;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R$/', $body, $match) === 1) {
            return $this->numericObjectValue((int) $match[1], $objects, $seen);
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function integerValue(string $body, string $name, array $objects): ?int
    {
        $value = $this->numberValue($body, $name, $objects);

        return $value === null ? null : (int) round($value);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function rotationValue(string $body, array $objects): ?int
    {
        $value = $this->numberValue($body, 'Rotate', $objects);
        if ($value === null || abs($value - round($value)) > 0.000001) {
            return null;
        }

        $rotation = (int) round($value);
        if ($rotation % 90 !== 0) {
            return null;
        }

        return $this->normalizedRotation($rotation);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     */
    private function numberValue(string $body, string $name, array $objects): ?float
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?)(?!\s+\d+\s+R\b)/s', $body, $match) === 1) {
            return (float) $match[1];
        }

        $objectId = $this->reference($body, $name);
        if ($objectId === null || !isset($objects[$objectId])) {
            return null;
        }

        $objectBody = trim($objects[$objectId]['body']);
        if (preg_match('/^[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?$/', $objectBody) !== 1) {
            return null;
        }

        return (float) $objectBody;
    }

    /**
     * @param list<float> $mediaBox
     * @param list<float> $cropBox
     * @return list<float>
     */
    private function intersectBoxes(array $mediaBox, array $cropBox): array
    {
        $left = max($mediaBox[0], min($mediaBox[2], $cropBox[0]));
        $right = max($mediaBox[0], min($mediaBox[2], $cropBox[2]));
        $bottom = max($mediaBox[1], min($mediaBox[3], $cropBox[1]));
        $top = max($mediaBox[1], min($mediaBox[3], $cropBox[3]));

        return [
            min($left, $right),
            min($bottom, $top),
            max($left, $right),
            max($bottom, $top),
        ];
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function sameBox(array $left, array $right): bool
    {
        for ($index = 0; $index < 4; $index++) {
            if (abs($left[$index] - $right[$index]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float> $box
     */
    private function hasPositiveArea(array $box): bool
    {
        return ($box[2] - $box[0]) > 0.000001 && ($box[3] - $box[1]) > 0.000001;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function hasPositiveIntersection(array $left, array $right): bool
    {
        return min($left[2], $right[2]) - max($left[0], $right[0]) > 0.000001
            && min($left[3], $right[3]) - max($left[1], $right[1]) > 0.000001;
    }

    /**
     * @return list<string>
     */
    private function previewBoundaryNotes(
        bool $cropBoxClippedToMedia,
        bool $previewZeroArea,
        bool $rotationSwapsAxes,
        bool $userUnitAppliedToPreview
    ): array {
        $notes = [];
        if ($cropBoxClippedToMedia) {
            $notes[] = 'crop_box_clipped_to_media_box';
        }
        if ($previewZeroArea) {
            $notes[] = 'zero_area_preview_box';
        }
        if ($rotationSwapsAxes) {
            $notes[] = 'rotation_swaps_display_axes';
        }
        if ($userUnitAppliedToPreview) {
            $notes[] = 'user_unit_scales_rendered_preview';
        }

        return $notes;
    }

    /**
     * @param list<float> $bbox
     * @return array{width: float, height: float}
     */
    private function displayPageSize(array $bbox, int $rotation, float $userUnit = 1.0): array
    {
        $width = max(0.0, $bbox[2] - $bbox[0]) * $userUnit;
        $height = max(0.0, $bbox[3] - $bbox[1]) * $userUnit;
        if (in_array($this->normalizedRotation($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<string>
     */
    private function pageLabelsForInventory(?string $pdfBytes, ?string $catalogBody, array $objects, int $pageCount): array
    {
        if ($pageCount <= 0) {
            return [];
        }

        if ($pdfBytes !== null) {
            $textExtractor = new PdfTextExtractor();
            if ($textExtractor->isEncrypted($pdfBytes)) {
                return $this->defaultPageLabels($pageCount);
            }

            $textLabels = $textExtractor->extractPageLabels($pdfBytes);
            if (count($textLabels) === $pageCount) {
                return $textLabels;
            }
        }

        return $this->pageLabelsFromCatalog($catalogBody, $objects, $pageCount);
    }

    /**
     * @return list<string>
     */
    private function defaultPageLabels(int $pageCount): array
    {
        $labels = [];
        for ($index = 0; $index < $pageCount; $index++) {
            $labels[$index] = (string) ($index + 1);
        }

        return $labels;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @return list<string>
     */
    private function pageLabelsFromCatalog(?string $catalogBody, array $objects, int $pageCount): array
    {
        $labels = $this->defaultPageLabels($pageCount);

        if ($catalogBody === null || $pageCount === 0) {
            return $labels;
        }

        $sections = [];
        foreach ($this->valueEntriesAfterName($catalogBody, 'PageLabels') as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $candidateSections = $this->pageLabelSections($entry['value'], $objects);
            $candidateSections = array_filter(
                $candidateSections,
                static fn (array $section): bool => $section['page_index'] >= 0 && $section['page_index'] < $pageCount
            );
            usort($candidateSections, static fn (array $left, array $right): int => $left['page_index'] <=> $right['page_index']);
            if ($candidateSections !== []) {
                $sections = $candidateSections;
                break;
            }
        }
        if ($sections === []) {
            return $labels;
        }

        $count = count($sections);
        for ($sectionIndex = 0; $sectionIndex < $count; $sectionIndex++) {
            $section = $sections[$sectionIndex];
            $startPage = $section['page_index'];
            $endPage = $sections[$sectionIndex + 1]['page_index'] ?? $pageCount;
            for ($pageIndex = $startPage; $pageIndex < $endPage; $pageIndex++) {
                $number = $section['start'] + ($pageIndex - $startPage);
                $label = $this->formatPageLabel($section['prefix'], $section['style'], $number);
                $labels[$pageIndex] = $label !== '' ? $label : (string) ($pageIndex + 1);
            }
        }

        return $labels;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     * @param array{0: int, 1: int}|null $inheritedLimits
     * @return list<array{page_index: int, prefix: string, style: string|null, start: int}>
     */
    private function pageLabelSections(
        string $value,
        array $objects,
        array $seen = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): array
    {
        if ($depth >= self::MAX_PAGE_LABEL_TREE_DEPTH) {
            return [];
        }

        $value = $this->pageLabelDictionaryToken($this->resolvePageLabelPdfValue($value, $objects, $seen));
        if ($value === null) {
            return [];
        }

        if ($this->pageLabelLimitsInvalidRange($value, $objects, $seen)) {
            return [];
        }

        $sections = [];
        $limits = $inheritedLimits;
        $localLimits = $this->pageLabelLimits($value, $objects, $seen);
        if ($localLimits === null && $this->pageLabelLimitsReversed($value, $objects, $seen)) {
            return [];
        }

        if ($localLimits !== null) {
            if ($limits !== null) {
                $lower = max($limits[0], $localLimits[0]);
                $upper = min($limits[1], $localLimits[1]);
                if ($lower > $upper) {
                    return [];
                }
                $limits = [$lower, $upper];
            } else {
                $limits = $localLimits;
            }
        }

        foreach ($this->valueEntriesAfterName($value, 'Nums') as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $candidateSections = $this->pageLabelSectionsFromNums($entry['value'], $objects, $seen, $limits);
            if ($candidateSections === []) {
                continue;
            }

            $seenPageIndexes = [];
            foreach ($candidateSections as $section) {
                $pageIndex = $section['page_index'];
                if (isset($seenPageIndexes[$pageIndex])) {
                    continue;
                }

                $seenPageIndexes[$pageIndex] = true;
                $sections[] = $section;
            }

            break;
        }
        if ($sections !== [] && $localLimits === null) {
            // Unbounded /Nums nodes are number-tree leaves; keep the existing bounded repair path below.
            return $sections;
        }

        $kidNodeGroups = [];
        foreach ($this->valueEntriesAfterName($value, 'Kids') as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $kids = $entry['value'];
            $candidateKidNodes = [];
            $kidOrder = 0;
            foreach ($this->pageLabelArrayElements($this->resolvePageLabelPdfValue($kids, $objects, $seen)) as $kid) {
                $directKidBody = $this->pageLabelDictionaryToken($kid);
                if ($directKidBody !== null) {
                    if ($this->pageLabelLimitsMalformed($directKidBody, $objects, $seen)) {
                        continue;
                    }

                    if ($this->pageLabelLimitsInvalidRange($directKidBody, $objects, $seen)) {
                        continue;
                    }

                    $kidLocalLimits = $this->pageLabelLimits($directKidBody, $objects, $seen);
                    if ($kidLocalLimits === null && $this->pageLabelLimitsReversed($directKidBody, $objects, $seen)) {
                        continue;
                    }

                    $kidMergedLimits = $this->mergePageLabelLimits($limits, $kidLocalLimits);
                    if ($kidLocalLimits !== null && $kidMergedLimits === null) {
                        continue;
                    }

                    $candidateKidNodes[] = [
                        'body' => $directKidBody,
                        'seen' => $seen,
                        'limits' => $kidMergedLimits,
                        'local_limits' => $kidLocalLimits,
                        'order' => $kidOrder++,
                    ];
                    continue;
                }

                $reference = $this->pageLabelReferenceOperand($kid);
                if ($reference === null) {
                    continue;
                }

                $objectId = $reference['objectNumber'];
                $generation = $reference['generation'];
                $kidBody = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
                if ($kidBody === null) {
                    continue;
                }

                $kidSeen = [...$seen, $this->objectReferenceKey($objectId, $generation)];
                $kidDictionary = $this->pageLabelDictionaryToken(
                    $this->resolvePageLabelPdfValue($kidBody, $objects, $kidSeen)
                );
                if ($kidDictionary === null) {
                    continue;
                }

                if ($this->pageLabelLimitsMalformed($kidDictionary, $objects, $kidSeen)) {
                    continue;
                }

                if ($this->pageLabelLimitsInvalidRange($kidDictionary, $objects, $kidSeen)) {
                    continue;
                }

                $kidLocalLimits = $this->pageLabelLimits($kidDictionary, $objects, $kidSeen);
                if ($kidLocalLimits === null && $this->pageLabelLimitsReversed($kidDictionary, $objects, $kidSeen)) {
                    continue;
                }

                $kidMergedLimits = $this->mergePageLabelLimits($limits, $kidLocalLimits);
                if ($kidLocalLimits !== null && $kidMergedLimits === null) {
                    continue;
                }

                $candidateKidNodes[] = [
                    'body' => $kidDictionary,
                    'seen' => $kidSeen,
                    'limits' => $kidMergedLimits,
                    'local_limits' => $kidLocalLimits,
                    'order' => $kidOrder++,
                ];
            }

            if ($candidateKidNodes !== []) {
                $kidNodeGroups[] = $candidateKidNodes;
            }
        }

        foreach ($kidNodeGroups as $kidNodes) {
            foreach ($kidNodes as $nodeIndex => $kidNode) {
                $kidNodes[$nodeIndex]['sections'] = $this->pageLabelSections(
                    $kidNode['body'],
                    $objects,
                    $kidNode['seen'],
                    $limits,
                    $depth + 1
                );
            }

            $kidNodes = array_values(array_filter(
                $kidNodes,
                static fn (array $kidNode): bool => !($kidNode['local_limits'] === null && $kidNode['sections'] === [])
            ));

            $sortableKidLimits = true;
            foreach ($kidNodes as $kidNode) {
                if ($kidNode['local_limits'] === null || $kidNode['limits'] === null) {
                    $sortableKidLimits = false;
                    break;
                }
            }

            if ($sortableKidLimits) {
                usort(
                    $kidNodes,
                    static function (array $left, array $right): int {
                        $leftLimits = $left['limits'];
                        $rightLimits = $right['limits'];

                        return $leftLimits[0] <=> $rightLimits[0]
                            ?: $left['order'] <=> $right['order'];
                    }
                );
            }

            $groupContributed = false;
            $groupUsable = false;
            $claimedKidLimits = [];
            foreach ($kidNodes as $kidNode) {
                $kidLimits = $kidNode['limits'];
                $claimableLimits = $kidNode['local_limits'] === null ? null : $kidLimits;
                $kidContributed = false;
                $contributedPageIndexes = [];
                $seenPageIndexes = [];
                foreach ($sections as $section) {
                    $seenPageIndexes[$section['page_index']] = true;
                }

                foreach ($kidNode['sections'] as $section) {
                    $pageIndex = $section['page_index'];
                    if ($claimableLimits !== null) {
                        foreach ($claimedKidLimits as $claimedLimits) {
                            if ($this->pageLabelKidLimitsSuppressPage(
                                $claimedLimits,
                                $claimableLimits,
                                $kidNode['local_limits'],
                                $pageIndex
                            )) {
                                continue 2;
                            }
                        }
                    }

                    $groupUsable = true;
                    if (isset($seenPageIndexes[$pageIndex])) {
                        continue;
                    }

                    $seenPageIndexes[$pageIndex] = true;
                    $sections[] = $section;
                    $kidContributed = true;
                    $contributedPageIndexes[] = $pageIndex;
                    $groupContributed = true;
                }

                if ($claimableLimits !== null && $kidContributed) {
                    $claimLower = max($claimableLimits[0], min($contributedPageIndexes));
                    $claimedKidLimits[] = [
                        'limits' => [$claimLower, $claimableLimits[1]],
                        'local_limits' => $kidNode['local_limits'],
                        'claims_lower' => $claimLower === $claimableLimits[0],
                    ];
                }
            }

            if ($groupContributed || $groupUsable) {
                break;
            }
        }

        return $sections;
    }

    /**
     * @param array{limits: array{0: int, 1: int}, local_limits: array{0: int, 1: int}, claims_lower: bool} $claimedLimits
     * @param array{0: int, 1: int} $claimableLimits
     * @param array{0: int, 1: int} $localLimits
     */
    private function pageLabelKidLimitsSuppressPage(
        array $claimedLimits,
        array $claimableLimits,
        array $localLimits,
        int $pageIndex
    ): bool {
        // Keep accepted singleton endpoint kids, but block stale entries inside already claimed child ranges.
        $sameLowerBound = $claimedLimits['claims_lower']
            && $localLimits[0] === $claimedLimits['local_limits'][0];
        if ($sameLowerBound) {
            return true;
        }

        $claimedRange = $claimedLimits['limits'];
        $startsInsideClaim = $claimableLimits[0] > $claimedRange[0]
            && $claimableLimits[0] < $claimedRange[1];
        $crossesUpperEndpoint = $claimableLimits[0] === $claimedRange[1]
            && $claimableLimits[1] > $claimedRange[1];

        return ($startsInsideClaim || $crossesUpperEndpoint)
            && $pageIndex >= $claimedRange[0]
            && $pageIndex <= $claimedRange[1];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     * @param array{0: int, 1: int}|null $limits
     * @return list<array{page_index: int, prefix: string, style: string|null, start: int}>
     */
    private function pageLabelSectionsFromNums(string $nums, array $objects, array $seen, ?array $limits): array
    {
        $elements = $this->pageLabelArrayElements($this->resolvePageLabelPdfValue($nums, $objects, $seen));
        $sections = [];
        $seenPageIndexes = [];
        $lastSeenPageIndex = null;
        $count = count($elements);
        for ($index = 0; $index + 1 < $count;) {
            $valueIndex = $index + 1;
            $pageIndexValue = $this->pageLabelIndexOperand($elements[$index], $objects, $seen);
            if ($pageIndexValue === null) {
                $index += $this->pageLabelMalformedIndexOperandConsumesPairedValue($elements[$index], $objects, $seen)
                    && $valueIndex < $count
                    ? 2
                    : 1;
                continue;
            }

            $index += 2;

            if ($lastSeenPageIndex !== null && $pageIndexValue < $lastSeenPageIndex) {
                continue;
            }

            if ($lastSeenPageIndex === null || $pageIndexValue > $lastSeenPageIndex) {
                $lastSeenPageIndex = $pageIndexValue;
            }

            $sectionValue = $elements[$valueIndex];
            if ($this->pageLabelNullValue($sectionValue, $objects, $seen)) {
                if ($limits !== null && ($pageIndexValue < $limits[0] || $pageIndexValue > $limits[1])) {
                    continue;
                }

                if (isset($seenPageIndexes[$pageIndexValue])) {
                    continue;
                }

                $seenPageIndexes[$pageIndexValue] = true;
                $sections[] = [
                    'page_index' => $pageIndexValue,
                    'prefix' => '',
                    'style' => 'D',
                    'start' => $pageIndexValue + 1,
                ];
                continue;
            }

            $section = $this->parsePageLabelDictionary($sectionValue, $objects, $seen);
            if ($section === null) {
                $index = $valueIndex;
                continue;
            }

            if ($limits !== null && ($pageIndexValue < $limits[0] || $pageIndexValue > $limits[1])) {
                continue;
            }

            if (isset($seenPageIndexes[$pageIndexValue])) {
                continue;
            }

            $seenPageIndexes[$pageIndexValue] = true;
            $sections[] = [
                'page_index' => $pageIndexValue,
                'prefix' => $section['prefix'],
                'style' => $section['style'],
                'start' => $section['start'],
            ];
        }

        return $sections;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelMalformedIndexOperandConsumesPairedValue(string $value, array $objects, array $seen): bool
    {
        $value = trim($value);
        if ($this->pageLabelIntegerTokenValue($value) !== null) {
            return false;
        }

        return $this->pageLabelMalformedIndexScalarConsumesPairedValue($value, $objects, $seen);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelMalformedIndexScalarConsumesPairedValue(string $value, array $objects, array $seen): bool
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        if ($offset >= strlen($value)) {
            return false;
        }

        $reference = $this->pdfIndirectReferenceValueAt($value, $offset);
        if ($reference !== null) {
            $afterReference = $this->skipPdfWhitespace($value, $reference['endOffset']);
            if ($afterReference < strlen($value)) {
                return true;
            }

            $objectId = $reference['objectNumber'];
            $generation = $reference['generation'];
            $objectKey = $this->objectReferenceKey($objectId, $generation);
            if ($objectId <= 0 || $generation < 0 || in_array($objectKey, $seen, true)) {
                return false;
            }

            $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
            if ($body === null) {
                return false;
            }

            return $this->pageLabelMalformedIndexScalarConsumesPairedValue(
                $body,
                $objects,
                [...$seen, $objectKey]
            );
        }

        return preg_match('/\G[+-]?\d+(?:\.\d+)?/s', $value, $match, 0, $offset) === 1;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelIndexOperand(string $value, array $objects, array $seen): ?int
    {
        $value = trim($value);
        $integer = $this->pageLabelIntegerTokenValue($value);
        if ($integer !== null) {
            return $integer;
        }

        $reference = $this->pageLabelReferenceOperand($value);
        if ($reference === null) {
            return null;
        }

        $objectId = $reference['objectNumber'];
        $generation = $reference['generation'];
        $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
        if ($body === null) {
            return null;
        }

        return $this->pageLabelIndexOperand(
            $body,
            $objects,
            [...$seen, $this->objectReferenceKey($objectId, $generation)]
        );
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     * @return array{0: int, 1: int}|null
     */
    private function pageLabelLimits(string $dict, array $objects, array $seen): ?array
    {
        foreach ($this->pageLabelLimitCandidates($dict, $objects, $seen) as $candidate) {
            if ($candidate['status'] === 'valid') {
                return $candidate['limits'];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelLimitsReversed(string $dict, array $objects, array $seen): bool
    {
        $candidates = $this->pageLabelLimitCandidates($dict, $objects, $seen);
        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'valid') {
                return false;
            }
        }
        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'reversed') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelLimitsInvalidRange(string $dict, array $objects, array $seen): bool
    {
        $candidates = $this->pageLabelLimitCandidates($dict, $objects, $seen);
        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'valid') {
                return false;
            }
        }
        foreach ($candidates as $candidate) {
            if (
                $candidate['status'] === 'negative'
                || $candidate['status'] === 'reversed'
                || $candidate['status'] === 'malformed_operand'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelLimitsMalformed(string $dict, array $objects, array $seen): bool
    {
        $candidates = $this->pageLabelLimitCandidates($dict, $objects, $seen);
        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'valid') {
                return false;
            }
        }
        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'malformed' || $candidate['status'] === 'malformed_operand') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     * @return list<array{status: string, limits?: array{0: int, 1: int}}>
     */
    private function pageLabelLimitCandidates(string $dict, array $objects, array $seen): array
    {
        $candidates = [];
        foreach ($this->valueEntriesAfterName($dict, 'Limits') as $entry) {
            if ($entry['has_trailing_operand']) {
                $candidates[] = ['status' => 'malformed'];
                continue;
            }

            $elements = $this->pageLabelArrayElements($this->resolvePageLabelPdfValue($entry['value'], $objects, $seen));
            if (count($elements) !== 2) {
                $candidates[] = ['status' => 'malformed'];
                continue;
            }

            $lower = $this->pageLabelLimitOperand($elements[0], $objects, $seen);
            $upper = $this->pageLabelLimitOperand($elements[1], $objects, $seen);
            if ($lower === null || $upper === null) {
                $lowerStatus = $lower === null
                    ? $this->pageLabelLimitOperandFailureStatus($elements[0], $objects, $seen)
                    : 'valid';
                $upperStatus = $upper === null
                    ? $this->pageLabelLimitOperandFailureStatus($elements[1], $objects, $seen)
                    : 'valid';
                $candidates[] = [
                    'status' => in_array('malformed_operand', [$lowerStatus, $upperStatus], true)
                        ? 'malformed_operand'
                        : 'malformed',
                ];
                continue;
            }

            if ($lower < 0 || $upper < 0) {
                $candidates[] = ['status' => 'negative'];
                continue;
            }

            if ($lower > $upper) {
                $candidates[] = ['status' => 'reversed'];
                continue;
            }

            $candidates[] = [
                'status' => 'valid',
                'limits' => [$lower, $upper],
            ];
        }

        return $candidates;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelLimitOperandFailureStatus(string $value, array $objects, array $seen): string
    {
        $value = trim($value);
        if (preg_match('/^[+-]?\d+$/', $value) === 1) {
            return 'malformed';
        }

        $reference = $this->pageLabelReferenceOperand($value);
        if ($reference === null) {
            return 'malformed_operand';
        }

        $objectId = $reference['objectNumber'];
        $generation = $reference['generation'];
        $objectKey = $objectId . ':' . $generation;
        if (
            $objectId <= 0
            || $generation < 0
            || in_array($objectKey, $seen, true)
        ) {
            return 'malformed_operand';
        }

        $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
        if ($body === null) {
            return 'malformed_operand';
        }

        return $this->pageLabelLimitOperandFailureStatus($body, $objects, [...$seen, $objectKey]);
    }

    /**
     * @param array{0: int, 1: int}|null $parentLimits
     * @param array{0: int, 1: int}|null $localLimits
     * @return array{0: int, 1: int}|null
     */
    private function mergePageLabelLimits(?array $parentLimits, ?array $localLimits): ?array
    {
        if ($parentLimits === null) {
            return $localLimits;
        }
        if ($localLimits === null) {
            return $parentLimits;
        }

        $lower = max($parentLimits[0], $localLimits[0]);
        $upper = min($parentLimits[1], $localLimits[1]);

        return $lower <= $upper ? [$lower, $upper] : null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelLimitOperand(string $value, array $objects, array $seen): ?int
    {
        $value = trim($value);
        $integer = $this->pageLabelIntegerTokenValue($value);
        if ($integer !== null) {
            return $integer;
        }

        $reference = $this->pageLabelReferenceOperand($value);
        if ($reference === null) {
            return null;
        }

        $objectId = $reference['objectNumber'];
        $generation = $reference['generation'];
        $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
        if ($body === null) {
            return null;
        }

        return $this->pageLabelLimitOperand(
            $body,
            $objects,
            [...$seen, $this->objectReferenceKey($objectId, $generation)]
        );
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     * @return array{prefix: string, style: string|null, start: int}|null
     */
    private function parsePageLabelDictionary(string $value, array $objects, array $seen): ?array
    {
        $dict = $this->pageLabelDictionaryToken($this->resolvePageLabelPdfValue($value, $objects, $seen));
        if ($dict === null) {
            return null;
        }
        if (!$this->pageLabelDictionaryTypeIsValid($dict, $objects, $seen)) {
            return null;
        }
        if ($this->pageLabelDictionaryHasNumberTreeKeys($dict)) {
            return null;
        }

        return [
            'prefix' => $this->pageLabelPrefixValueAfterName($dict, 'P', $objects, $seen),
            'style' => $this->pageLabelStyleValueAfterName($dict, 'S', $objects, $seen),
            'start' => $this->pageLabelStartValueAfterName($dict, 'St', $objects, $seen),
        ];
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelDictionaryTypeIsValid(string $dict, array $objects, array $seen): bool
    {
        $sawType = false;
        foreach ($this->valueEntriesAfterName($dict, 'Type') as $entry) {
            $sawType = true;
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $typeValue = $this->pageLabelSinglePdfToken(
                $this->resolvePageLabelPdfValue($entry['value'], $objects, $seen)
            );
            if ($typeValue === null || !str_starts_with($typeValue, '/')) {
                continue;
            }

            if ($this->decodePdfName(substr($typeValue, 1)) === 'PageLabel') {
                return true;
            }
        }

        return !$sawType;
    }

    private function pageLabelDictionaryHasNumberTreeKeys(string $dict): bool
    {
        foreach (['Nums', 'Kids', 'Limits'] as $name) {
            foreach ($this->valueEntriesAfterName($dict, $name) as $entry) {
                if (!$entry['has_trailing_operand']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pageLabelDictionaryToken(string $value): ?string
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        if (!str_starts_with(substr($value, $offset), '<<')) {
            return null;
        }

        $dictionary = $this->readBalancedDictionary($value, $offset);
        if ($dictionary === null) {
            return null;
        }

        return $this->skipPdfWhitespace($value, $dictionary[1]) >= strlen($value) ? $dictionary[0] : null;
    }

    /**
     * @return list<string>
     */
    private function pageLabelArrayElements(string $value): array
    {
        $array = $this->pageLabelArrayToken($value);
        return $array === null ? [] : $this->arrayElements($array);
    }

    private function pageLabelArrayToken(string $value): ?string
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $array = $this->readBalancedArray($value, $offset);
        if ($array === null) {
            return null;
        }

        return $this->skipPdfWhitespace($value, $array[1]) >= strlen($value) ? $array[0] : null;
    }

    private function pageLabelIntegerTokenValue(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            return null;
        }

        $digits = $value;
        if ($digits[0] === '+' || $digits[0] === '-') {
            $digits = substr($digits, 1);
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $max = (string) PHP_INT_MAX;
        if (
            strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)
        ) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelStyleValueAfterName(string $dict, string $name, array $objects, array $seen): ?string
    {
        foreach ($this->valueEntriesAfterName($dict, $name) as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $styleValue = $this->pageLabelSinglePdfToken($this->resolvePageLabelPdfValue($entry['value'], $objects, $seen));
            if ($styleValue === null || !str_starts_with($styleValue, '/')) {
                continue;
            }

            $styleName = $this->decodePdfName(substr($styleValue, 1));
            if (in_array($styleName, ['D', 'R', 'r', 'A', 'a'], true)) {
                return $styleName;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelStartValueAfterName(string $dict, string $name, array $objects, array $seen): int
    {
        foreach ($this->valueEntriesAfterName($dict, $name) as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $startValue = $this->pageLabelSinglePdfToken($this->resolvePageLabelPdfValue($entry['value'], $objects, $seen));
            $startInteger = $startValue === null ? null : $this->pageLabelIntegerTokenValue($startValue);
            if ($startInteger !== null && $startInteger >= 1) {
                return $startInteger;
            }
        }

        return 1;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelPrefixValueAfterName(string $dict, string $name, array $objects, array $seen): string
    {
        foreach ($this->valueEntriesAfterName($dict, $name) as $entry) {
            if ($entry['has_trailing_operand']) {
                continue;
            }

            $prefix = $this->decodePdfStringValue($this->resolvePageLabelPdfValue($entry['value'], $objects, $seen));
            if ($prefix !== null && strlen($prefix) <= self::MAX_PAGE_LABEL_PREFIX_BYTES) {
                return $prefix;
            }
        }

        return '';
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function resolvePageLabelPdfValue(string $value, array $objects, array $seen = []): string
    {
        $value = trim($value);
        $reference = $this->pageLabelReferenceOperand($value);
        if ($reference === null) {
            return $value;
        }

        $objectId = $reference['objectNumber'];
        $generation = $reference['generation'];
        $objectKey = $objectId . ':' . $generation;
        if (
            $objectId <= 0
            || $generation < 0
            || in_array($objectKey, $seen, true)
        ) {
            return $value;
        }

        $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
        if ($body === null) {
            return $value;
        }

        return $this->resolvePageLabelPdfValue($body, $objects, [...$seen, $objectKey]);
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function pageLabelReferenceOperand(string $value): ?array
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        $reference = $this->pdfIndirectReferenceValueAt($value, $offset);
        if ($reference === null) {
            return null;
        }

        $endOffset = $this->skipPdfWhitespace($value, $reference['endOffset']);
        if ($endOffset < strlen($value)) {
            return null;
        }

        return [
            'objectNumber' => $reference['objectNumber'],
            'generation' => $reference['generation'],
        ];
    }

    private function pageLabelSinglePdfToken(string $value): ?string
    {
        $token = $this->readPdfValue($value, 0);
        if ($token === null) {
            return null;
        }

        $endOffset = $this->skipPdfWhitespace($value, $token[1]);
        if ($endOffset < strlen($value)) {
            return null;
        }

        return trim($token[0]);
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function pageLabelNullValue(string $value, array $objects, array $seen): bool
    {
        $value = $this->resolvePageLabelPdfValue($value, $objects, $seen);
        return $this->pageLabelSinglePdfToken($value) === 'null';
    }

    private function formatPageLabel(string $prefix, ?string $style, int $number): string
    {
        return $prefix . match ($style) {
            'D' => (string) $number,
            'R' => $this->romanNumeral($number),
            'r' => strtolower($this->romanNumeral($number)),
            'A' => $this->alphabeticLabel($number, false),
            'a' => $this->alphabeticLabel($number, true),
            default => '',
        };
    }

    private function romanNumeral(int $number): string
    {
        if ($number < 1) {
            return '';
        }
        $originalNumber = $number;
        if ($number > self::MAX_PAGE_LABEL_GENERATED_SUFFIX_BYTES * 1000 + 999) {
            return (string) $originalNumber;
        }

        $values = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $label = '';
        foreach ($values as $value => $glyph) {
            while ($number >= $value) {
                if (strlen($label) + strlen($glyph) > self::MAX_PAGE_LABEL_GENERATED_SUFFIX_BYTES) {
                    return (string) $originalNumber;
                }
                $label .= $glyph;
                $number -= $value;
            }
        }

        return $label;
    }

    private function alphabeticLabel(int $number, bool $lowercase): string
    {
        if ($number < 1) {
            return '';
        }

        $repeatCount = intdiv($number - 1, 26) + 1;
        if ($repeatCount > self::MAX_PAGE_LABEL_GENERATED_SUFFIX_BYTES) {
            return (string) $number;
        }

        $label = str_repeat(chr(ord('A') + (($number - 1) % 26)), $repeatCount);

        return $lowercase ? strtolower($label) : $label;
    }

    private function decodePdfStringValue(string $value): ?string
    {
        $value = $this->pageLabelSinglePdfToken($value);
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            return $this->decodePdfByteString($this->decodeLiteralString(substr($value, 1, -1)));
        }

        if (preg_match('/^<([\da-fA-F\s]*)>$/s', $value, $match) === 1) {
            $hex = preg_replace('/\s+/', '', $match[1]);
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? '' : $this->decodePdfByteString($bytes);
        }

        return null;
    }

    private function decodePdfByteString(string $bytes): string
    {
        if (str_starts_with($bytes, "\xfe\xff")) {
            $utf16 = substr($bytes, 2);
            if (strlen($utf16) % 2 !== 0 || !mb_check_encoding($utf16, 'UTF-16BE')) {
                return '';
            }

            $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $utf16);
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xff\xfe")) {
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

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function decodeLiteralString(string $value): string
    {
        $out = '';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                continue;
            }

            $next = $value[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && $index + 1 < $length && $value[$index + 1] === "\n") {
                    $index++;
                }
                continue;
            }

            if ($next >= '0' && $next <= '7') {
                $octal = $next;
                for ($count = 0; $count < 2 && $index + 1 < $length; $count++) {
                    $peek = $value[$index + 1];
                    if ($peek < '0' || $peek > '7') {
                        break;
                    }
                    $octal .= $peek;
                    $index++;
                }
                $out .= chr(octdec($octal) & 0xff);
                continue;
            }

            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\b",
                'f' => "\f",
                '(', ')', '\\' => $next,
                default => $next,
            };
        }

        return $out;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int> $seen
     */
    private function resolvePdfValue(string $value, array $objects, array $seen = []): string
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+(\d+)\s+R$/', $value, $match) === 1) {
            $objectId = (int) $match[1];
            $generation = (int) $match[2];
            $body = $this->objectBodyForReference($objects, $objectId, $generation, $seen);
            if ($body !== null) {
                return $body;
            }
        }

        return $value;
    }

    /**
     * @param array<int, array{generation: int, body: string}> $objects
     * @param list<int|string> $seen
     */
    private function objectBodyForReference(array $objects, int $objectId, int $generation, array $seen): ?string
    {
        $objectKey = $this->objectReferenceKey($objectId, $generation);
        if (
            $objectId <= 0
            || $generation < 0
            || in_array($objectId, $seen, true)
            || in_array($objectKey, $seen, true)
        ) {
            return null;
        }

        return $this->directObjectBodiesByGeneration[$objectId][$generation] ?? null;
    }

    private function objectReferenceKey(int $objectId, int $generation): string
    {
        return $objectId . ':' . $generation;
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        return $this->valuesAfterName($body, $name)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function valuesAfterName(string $body, string $name): array
    {
        return array_map(
            static fn (array $entry): string => $entry['value'],
            $this->valueEntriesAfterName($body, $name)
        );
    }

    /**
     * @return list<array{value: string, has_trailing_operand: bool}>
     */
    private function valueEntriesAfterName(string $body, string $name): array
    {
        $body = trim($body);
        if (str_starts_with($body, '<<')) {
            $dictionary = $this->readBalancedDictionary($body, 0);
            if ($dictionary !== null) {
                $body = substr($dictionary[0], 2, -2);
            }
        }

        $length = strlen($body);
        $entries = [];
        for ($offset = 0; $offset < $length;) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                return $entries;
            }

            $skipped = $this->skipCompositeValueBytes($body, $offset);
            if ($skipped !== null) {
                $offset = $skipped;
                continue;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', substr($body, $offset), $match) !== 1) {
                $offset++;
                continue;
            }

            $offset += strlen($match[0]);
            $value = $this->readPdfValue($body, $offset);
            if ($this->decodePdfName($match[1]) !== $name) {
                $offset = $value === null ? $offset : $value[1];
                continue;
            }

            if ($value === null) {
                return $entries;
            }

            $afterValue = $this->skipPdfWhitespace($body, $value[1]);
            $entries[] = [
                'value' => $value[0],
                'has_trailing_operand' => $afterValue < $length && $body[$afterValue] !== '/',
            ];
            $offset = $value[1];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function arrayElements(string $array): array
    {
        $array = trim($array);
        if (!str_starts_with($array, '[') || !str_ends_with($array, ']')) {
            return [];
        }

        $body = substr($array, 1, -1);
        $elements = [];
        $offset = 0;
        while (true) {
            $value = $this->readPdfValue($body, $offset);
            if ($value === null) {
                break;
            }

            $elements[] = $value[0];
            $offset = $value[1];
        }

        return $elements;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readPdfValue(string $body, int $offset): ?array
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (str_starts_with(substr($body, $offset), '<<')) {
            return $this->readBalancedDictionary($body, $offset);
        }

        $char = $body[$offset];
        if ($char === '[') {
            return $this->readBalancedArray($body, $offset);
        }

        if ($char === '(') {
            return $this->readBalancedLiteralString($body, $offset);
        }

        if ($char === '<') {
            return $this->readHexString($body, $offset);
        }

        if ($char === '/') {
            if (preg_match('/\/[^\s\[\]()<>{}\/%]+/A', substr($body, $offset), $match) === 1) {
                return [$match[0], $offset + strlen($match[0])];
            }

            return null;
        }

        $reference = $this->pdfIndirectReferenceValueAt($body, $offset);
        if ($reference !== null) {
            return [$reference['token'], $reference['endOffset']];
        }

        $tail = substr($body, $offset);
        if (preg_match('/[+-]?\d+(?:\.\d+)?/A', $tail, $number) === 1) {
            $end = $offset + strlen($number[0]);
            $afterFirst = $this->skipPdfWhitespace($body, $end);
            if (preg_match('/\d+\s+R\b/A', substr($body, $afterFirst), $referenceTail) === 1) {
                return [substr($body, $offset, ($afterFirst - $offset) + strlen($referenceTail[0])), $afterFirst + strlen($referenceTail[0])];
            }

            return [$number[0], $end];
        }

        if (preg_match('/[A-Za-z][A-Za-z0-9_-]*/A', $tail, $keyword) === 1) {
            return [$keyword[0], $offset + strlen($keyword[0])];
        }

        return null;
    }

    /**
     * @return array{token: string, objectNumber: int, generation: int, endOffset: int}|null
     */
    private function pdfIndirectReferenceValueAt(string $body, int $offset): ?array
    {
        $length = strlen($body);
        $start = $this->skipPdfWhitespace($body, $offset);
        if ($start >= $length || preg_match('/\G\d+/s', $body, $objectMatch, 0, $start) !== 1) {
            return null;
        }

        $afterObject = $start + strlen($objectMatch[0]);
        $generationOffset = $this->skipPdfWhitespace($body, $afterObject);
        if (
            $generationOffset <= $afterObject
            || $generationOffset >= $length
            || preg_match('/\G\d+/s', $body, $generationMatch, 0, $generationOffset) !== 1
        ) {
            return null;
        }

        $afterGeneration = $generationOffset + strlen($generationMatch[0]);
        $referenceOffset = $this->skipPdfWhitespace($body, $afterGeneration);
        if (
            $referenceOffset <= $afterGeneration
            || ($body[$referenceOffset] ?? '') !== 'R'
        ) {
            return null;
        }

        $endOffset = $referenceOffset + 1;
        if ($endOffset < $length) {
            $next = $body[$endOffset];
            if (!ctype_space($next) && !str_contains('[]()<>{}/%', $next)) {
                return null;
            }
        }

        return [
            'token' => (int) $objectMatch[0] . ' ' . (int) $generationMatch[0] . ' R',
            'objectNumber' => (int) $objectMatch[0],
            'generation' => (int) $generationMatch[0],
            'endOffset' => $endOffset,
        ];
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readBalancedDictionary(string $body, int $offset): ?array
    {
        $depth = 0;
        $length = strlen($body);
        for ($index = $offset; $index < $length;) {
            if (str_starts_with(substr($body, $index), '<<')) {
                $depth++;
                $index += 2;
                continue;
            }

            if (str_starts_with(substr($body, $index), '>>')) {
                $depth--;
                $index += 2;
                if ($depth === 0) {
                    return [substr($body, $offset, $index - $offset), $index];
                }
                continue;
            }

            $skipped = $this->skipCompositeValueBytes($body, $index);
            $index = $skipped ?? ($index + 1);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readBalancedArray(string $body, int $offset): ?array
    {
        $depth = 0;
        $length = strlen($body);
        for ($index = $offset; $index < $length;) {
            $char = $body[$index];
            if ($char === '[') {
                $depth++;
                $index++;
                continue;
            }

            if ($char === ']') {
                $depth--;
                $index++;
                if ($depth === 0) {
                    return [substr($body, $offset, $index - $offset), $index];
                }
                continue;
            }

            if (str_starts_with(substr($body, $index), '<<')) {
                $dict = $this->readBalancedDictionary($body, $index);
                if ($dict === null) {
                    return null;
                }
                $index = $dict[1];
                continue;
            }

            $skipped = $this->skipCompositeValueBytes($body, $index);
            $index = $skipped ?? ($index + 1);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readBalancedLiteralString(string $body, int $offset): ?array
    {
        $depth = 0;
        $length = strlen($body);
        for ($index = $offset; $index < $length; $index++) {
            $char = $body[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $end = $index + 1;
                    return [substr($body, $offset, $end - $offset), $end];
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readHexString(string $body, int $offset): ?array
    {
        $end = strpos($body, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        return [substr($body, $offset, $end - $offset + 1), $end + 1];
    }

    private function skipCompositeValueBytes(string $body, int $offset): ?int
    {
        $char = $body[$offset];
        if ($char === '(') {
            $literal = $this->readBalancedLiteralString($body, $offset);
            return $literal[1] ?? null;
        }

        if (
            $char === '<'
            && !str_starts_with(substr($body, $offset), '<<')
            && ($offset === 0 || $body[$offset - 1] !== '<')
        ) {
            $hex = $this->readHexString($body, $offset);
            return $hex[1] ?? null;
        }

        return null;
    }

    private function skipPdfWhitespace(string $body, int $offset): int
    {
        $length = strlen($body);
        while ($offset < $length) {
            $char = $body[$offset];
            if ($char === '%') {
                while ($offset < $length && $body[$offset] !== "\n" && $body[$offset] !== "\r") {
                    $offset++;
                }
                continue;
            }

            if (!ctype_space($char) && $char !== "\0") {
                break;
            }
            $offset++;
        }

        return $offset;
    }

    private function assertPdfBytes(string $pdfBytes): void
    {
        if (!str_starts_with(ltrim($pdfBytes), '%PDF-')) {
            throw new InvalidArgumentException('PDF preview requires PDF bytes.');
        }
    }
}
