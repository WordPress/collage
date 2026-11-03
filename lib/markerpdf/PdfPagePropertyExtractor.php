<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfPagePropertyExtractor
{
    /**
     * @var array<int, int>
     */
    private array $currentObjectGenerations = [];

    /**
     * Native boundary for page dictionary metadata that affects extraction but
     * should remain review-only for WordPress imports.
     *
     * @return list<array<string, mixed>>
     */
    public function extractPageBoundaryMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        return array_values($this->pageBoundaryMetadataByPageObject($pdfBytes, $catalog, $objects));
    }

    /**
     * Native boundary for page-scoped /PieceInfo and tagged-PDF /UserProperties
     * review metadata. The rows are non-executing metadata only.
     *
     * @return list<array<string, mixed>>
     */
    public function extractPageReviewMetadata(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($catalog, $objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $markInfo = $this->markInfoMetadata($catalog, $objects);
        $markInfoUserProperties = ($markInfo['user_properties'] ?? false) === true;
        $userPropertiesByPage = $markInfoUserProperties
            ? $this->structureUserPropertiesByPageObject($catalog, $objects)
            : [];
        $pageBoundaryByObject = $this->pageBoundaryMetadataByPageObject($pdfBytes, $catalog, $objects);
        $articleBeadsByObject = $this->articleThreadBeadsByPageObject($pdfBytes);
        $structureMarkedContentByObject = $this->structureMarkedContentByPageObject($pdfBytes);
        $structureTaggedTablesByObject = $this->structureTaggedTablesByPageObject($pdfBytes);
        $textMarkupAnnotationsByObject = $this->textMarkupAnnotationsByPageObject($pdfBytes);
        $annotationStructureRowsByObject = $this->annotationStructureRowsByPageObject($pdfBytes);

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObjectNumber] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $pieceInfo = $this->pieceInfoMetadata($this->dictionaryRawValue($pageBody, 'PieceInfo'), $objects);
            $associatedFiles = $this->pageAssociatedFilesMetadata($this->dictionaryRawValue($pageBody, 'AF'), $objects);
            $pageBoundary = $pageBoundaryByObject[$pageObjectNumber] ?? null;
            $structParents = is_int($pageBoundary['struct_parents'] ?? null)
                ? $pageBoundary['struct_parents']
                : null;
            $parentTree = is_array($pageBoundary['parent_tree'] ?? null)
                ? $pageBoundary['parent_tree']
                : null;
            $userProperties = $userPropertiesByPage[$pageObjectNumber] ?? [];
            $articleBeads = $articleBeadsByObject[$pageObjectNumber] ?? [];
            $structureMarkedContent = $structureMarkedContentByObject[$pageObjectNumber] ?? [];
            $structureMarkedContent = $this->structureMarkedContentWithPageAssociatedFiles(
                $structureMarkedContent,
                $associatedFiles
            );
            $structureTaggedTables = $structureTaggedTablesByObject[$pageObjectNumber] ?? [];
            $articleBeads = $this->articleBeadsWithStructureContext($articleBeads, $structureMarkedContent);
            $textMarkupAnnotations = $textMarkupAnnotationsByObject[$pageObjectNumber] ?? [];
            $annotationStructureRows = $this->annotationStructureRowsWithPageContext(
                $annotationStructureRowsByObject[$pageObjectNumber] ?? [],
                $pieceInfo,
                $structParents,
                $parentTree
            );
            if (
                $pieceInfo === []
                && $associatedFiles === []
                && $userProperties === []
                && $articleBeads === []
                && $structureMarkedContent === []
                && $structureTaggedTables === []
                && $textMarkupAnnotations === []
                && $annotationStructureRows === []
                && $structParents === null
                && $parentTree === null
            ) {
                continue;
            }
            $pagePresentation = $pageBoundary['page_presentation'] ?? null;

            $page = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
            ];

            if ($pageBoundary !== null) {
                foreach (['source', 'page_number', 'page_label'] as $key) {
                    if (array_key_exists($key, $pageBoundary)) {
                        $page[$key] = $pageBoundary[$key];
                    }
                }
            }

            if ($markInfo !== []) {
                $page['mark_info'] = $markInfo;
            }

            if ($pieceInfo !== []) {
                $page['piece_info'] = $pieceInfo;
            }

            if ($structParents !== null) {
                $page['struct_parents'] = $structParents;
                $page['parent_tree'] = $parentTree;
            }

            if (is_array($pageBoundary['resources'] ?? null)) {
                $page['resources'] = $pageBoundary['resources'];
            }

            if ($associatedFiles !== []) {
                $page['page_associated_files'] = $associatedFiles;
            }

            if ($pagePresentation !== null) {
                $page['page_presentation'] = $pagePresentation;
            }

            if ($articleBeads !== []) {
                $page['article_thread_beads'] = $articleBeads;
                $titles = $this->articleThreadTitlesFromBeads($articleBeads);
                if ($titles !== []) {
                    $page['article_thread_titles'] = $titles;
                }
            }

            if ($structureMarkedContent !== []) {
                $page['structure_marked_content'] = $structureMarkedContent;
            }

            if ($structureTaggedTables !== []) {
                $page['structure_tagged_tables'] = $structureTaggedTables;
                $page['structure_tagged_table_count'] = count($structureTaggedTables);
                $page['structure_nested_tagged_table_count'] = count(array_filter(
                    $structureTaggedTables,
                    static fn (array $row): bool => isset($row['parent_cell_object'])
                ));
            }

            if ($textMarkupAnnotations !== []) {
                $page['text_markup_annotations'] = $textMarkupAnnotations;
            }

            if ($annotationStructureRows !== []) {
                $page['annotation_structure_parent_rows'] = $annotationStructureRows;
            }

            if ($userProperties !== []) {
                $page['mark_info_user_properties'] = $markInfoUserProperties;
                $page['user_properties'] = array_values($userProperties);
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function articleThreadBeadsByPageObject(string $pdfBytes): array
    {
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdfBytes, false);
        $threads = $navigation['article_threads'] ?? [];
        if (!is_array($threads)) {
            return [];
        }

        $beadsByPage = [];
        foreach ($threads as $thread) {
            if (!is_array($thread)) {
                continue;
            }

            $beads = $thread['beads'] ?? [];
            if (!is_array($beads)) {
                continue;
            }

            foreach ($beads as $bead) {
                if (!is_array($bead)) {
                    continue;
                }

                $pageObject = $bead['page_object'] ?? null;
                if (!is_int($pageObject)) {
                    continue;
                }

                $beadsByPage[$pageObject][] = ['source' => 'catalog_article_threads'] + $bead;
            }
        }

        return $beadsByPage;
    }

    /**
     * @param list<array<string, mixed>> $beads
     * @return list<string>
     */
    private function articleThreadTitlesFromBeads(array $beads): array
    {
        $titles = [];
        foreach ($beads as $bead) {
            $title = $bead['thread_title'] ?? null;
            if (is_string($title) && $title !== '') {
                $titles[$title] = $title;
            }
        }

        return array_values($titles);
    }

    /**
     * @param list<array<string, mixed>> $structureMarkedContent
     * @param list<array<string, mixed>> $associatedFiles
     * @return list<array<string, mixed>>
     */
    private function structureMarkedContentWithPageAssociatedFiles(array $structureMarkedContent, array $associatedFiles): array
    {
        if ($structureMarkedContent === [] || $associatedFiles === []) {
            return $structureMarkedContent;
        }

        return array_map(
            function (array $row) use ($associatedFiles): array {
                $mergedFiles = $this->uniqueAssociatedFileReviews(array_merge(
                    is_array($row['page_associated_files'] ?? null) ? $row['page_associated_files'] : [],
                    $associatedFiles
                ));
                if ($mergedFiles === []) {
                    return $row;
                }

                $row['page_associated_file_count'] = count($mergedFiles);
                $row['page_associated_files'] = $mergedFiles;
                $row['page_associated_file_review_only'] = true;

                return $row;
            },
            $structureMarkedContent
        );
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array<string, mixed>>
     */
    private function uniqueAssociatedFileReviews(array $files): array
    {
        $unique = [];
        $seen = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $fileSpecObject = $file['file_spec_object'] ?? null;
            $filename = $file['filename'] ?? null;
            $hash = $file['content_sha256'] ?? null;
            $key = (is_int($fileSpecObject) ? (string) $fileSpecObject : '')
                . '|'
                . (is_string($filename) ? $filename : '')
                . '|'
                . (is_string($hash) ? $hash : '');
            if ($key === '||' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $file;
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $articleBeads
     * @param list<array<string, mixed>> $structureMarkedContent
     * @return list<array<string, mixed>>
     */
    private function articleBeadsWithStructureContext(array $articleBeads, array $structureMarkedContent): array
    {
        if ($articleBeads === [] || $structureMarkedContent === []) {
            return $articleBeads;
        }

        $structureRows = [];
        $mcids = [];
        $roles = [];
        $associatedFiles = [];
        $associatedFileKeys = [];
        foreach ($structureMarkedContent as $row) {
            if (!is_array($row)) {
                continue;
            }

            $structureRows[] = $this->compactReviewRow($row);

            $mcid = $row['mcid'] ?? null;
            if (is_int($mcid)) {
                $mcids[$mcid] = $mcid;
            }

            $role = $row['role'] ?? null;
            if (is_string($role) && $role !== '') {
                $roles[$role] = $role;
            }

            $files = $row['associated_files'] ?? [];
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $fileSpecObject = $file['file_spec_object'] ?? null;
                $filename = $file['filename'] ?? null;
                $hash = $file['content_sha256'] ?? null;
                $key = (is_int($fileSpecObject) ? (string) $fileSpecObject : '')
                    . '|'
                    . (is_string($filename) ? $filename : '')
                    . '|'
                    . (is_string($hash) ? $hash : '');
                if ($key === '||' || isset($associatedFileKeys[$key])) {
                    continue;
                }

                $associatedFileKeys[$key] = true;
                $associatedFiles[] = $file;
            }
        }

        if ($structureRows === []) {
            return $articleBeads;
        }

        $mcids = array_values($mcids);
        sort($mcids);
        $roles = array_values($roles);

        return array_map(
            static function (array $bead) use ($structureRows, $mcids, $roles, $associatedFiles): array {
                $bead['target_structure_marked_content'] = $structureRows;
                $bead['target_structure_mcids'] = $mcids;
                if ($roles !== []) {
                    $bead['target_structure_roles'] = $roles;
                }
                if ($associatedFiles !== []) {
                    $bead['target_structure_associated_file_count'] = count($associatedFiles);
                    $bead['target_structure_associated_files'] = $associatedFiles;
                }

                return $bead;
            },
            $articleBeads
        );
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function textMarkupAnnotationsByPageObject(string $pdfBytes): array
    {
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $parentTreeEntries = $this->parentTreeObjectEntriesByStructParent($pdfBytes);
        $elementsByObject = $this->structureElementsByObject($pdfBytes);
        $rowsByPage = [];

        foreach ((new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdfBytes) as $pageMarkups) {
            if (!is_array($pageMarkups)) {
                continue;
            }

            $pageObject = $pageMarkups['page_object'] ?? null;
            $pnum = $pageMarkups['pnum'] ?? null;
            if (!is_int($pageObject) || !is_int($pnum)) {
                continue;
            }

            $markups = $pageMarkups['markups'] ?? [];
            if (!is_array($markups)) {
                continue;
            }

            foreach ($markups as $markup) {
                if (!is_array($markup)) {
                    continue;
                }

                $row = [
                    'source' => 'page_text_markup_annotation',
                    'pnum' => $pnum,
                    'page' => $pnum,
                    'page_number' => $pnum + 1,
                    'page_label' => $pageLabels[$pnum] ?? (string) ($pnum + 1),
                    'page_object' => $pageObject,
                    'annotation_object' => $markup['annotation_object'] ?? null,
                    'subtype' => $markup['subtype'] ?? null,
                    'rect' => $markup['rect'] ?? null,
                    'quad_rects' => $markup['quad_rects'] ?? null,
                    'pdftext_quad_rects' => $markup['pdftext_quad_rects'] ?? null,
                    'contents' => $markup['contents'] ?? null,
                    'author' => $markup['author'] ?? null,
                    'subject' => $markup['subject'] ?? null,
                    'name' => $markup['name'] ?? null,
                    'modified_at' => $markup['modified_at'] ?? null,
                    'color' => $markup['color'] ?? null,
                    'opacity' => $markup['opacity'] ?? null,
                    'flags' => $markup['flags'] ?? null,
                    'actions' => $markup['actions'] ?? [],
                    'additional_actions' => $markup['additional_actions'] ?? [],
                    'executes_actions_on_import' => $markup['executes_actions_on_import'] ?? false,
                    'review_only' => true,
                    'visible_text_source' => false,
                    'renders_markup_on_import' => false,
                ];

                $structParent = $markup['struct_parent'] ?? null;
                if (is_int($structParent)) {
                    $row['struct_parent'] = $structParent;
                    $parentTreeEntry = $parentTreeEntries[$structParent] ?? null;
                    if (is_array($parentTreeEntry)) {
                        foreach (['struct_object', 'raw_role', 'role', 'role_mapped'] as $key) {
                            if (array_key_exists($key, $parentTreeEntry)) {
                                $row[$key] = $parentTreeEntry[$key];
                            }
                        }
                        $row['parent_tree'] = $parentTreeEntry;

                        $structObject = $parentTreeEntry['struct_object'] ?? null;
                        if (is_int($structObject) && isset($elementsByObject[$structObject])) {
                            $this->copyStructureElementReviewFields($row, $elementsByObject[$structObject]);
                        }
                    }
                }

                $rowsByPage[$pageObject][] = $this->compactReviewRow($row);
            }
        }

        return $rowsByPage;
    }

    /**
     * Annotation and XObject structure parents use singular /StructParent keys
     * whose ParentTree values are direct StructElem dictionaries, unlike page
     * /StructParents keys whose values are MCID arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parentTreeObjectEntriesByStructParent(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $structTreeRoot = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'StructTreeRoot'), $objects);
        if ($structTreeRoot === null) {
            return [];
        }

        $parentTree = $this->resolveDictionaryFromValue($this->dictionaryRawValue($structTreeRoot['body'], 'ParentTree'), $objects);
        if ($parentTree === null) {
            return [];
        }

        $entries = [];
        $this->collectParentTreeObjectEntries(
            $parentTree['body'],
            $objects,
            $this->structureRoleMap($structTreeRoot['body'], $objects),
            $entries
        );

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, array<string, mixed>> $entries
     * @param array<int, true> $seenObjects
     */
    private function collectParentTreeObjectEntries(
        string $dictionary,
        array $objects,
        array $roleMap,
        array &$entries,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $nums = $this->dictionaryRawValue($dictionary, 'Nums');
        if ($nums !== null) {
            $items = $this->arrayItemsFromValue($nums, $objects);
            for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
                $key = trim($items[$index]);
                if (preg_match('/^[+-]?\d+$/', $key) !== 1) {
                    continue;
                }

                $rawValue = trim($this->resolveRawValue($items[$index + 1], $objects) ?? $items[$index + 1]);
                if ($rawValue === '' || str_starts_with($rawValue, '[')) {
                    continue;
                }

                $parent = $this->resolveDictionaryFromValue($items[$index + 1], $objects);
                if ($parent === null) {
                    continue;
                }

                $rawRole = $this->dictionaryNameValue($parent['body'], 'S', $objects);
                if ($rawRole === null || $rawRole === '') {
                    continue;
                }

                $role = $roleMap[$rawRole] ?? $rawRole;
                $entries[(int) $key] = [
                    'source' => 'struct_tree_parent_tree_object',
                    'key' => (int) $key,
                    'struct_object' => $parent['object'],
                    'raw_role' => $rawRole,
                    'role' => $role,
                    'role_mapped' => $role !== $rawRole,
                ];
            }
        }

        $kids = $this->dictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return;
        }

        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $kidObjectNumber = $this->objectNumberFromReference($kidValue);
            if ($kidObjectNumber === null || isset($seenObjects[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seenObjects;
            $nextSeen[$kidObjectNumber] = true;
            $this->collectParentTreeObjectEntries($kidDictionary, $objects, $roleMap, $entries, $nextSeen, $depth + 1);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function structureElementsByObject(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $structureTree = $metadata['structure_tree'] ?? [];
        $elements = is_array($structureTree) ? ($structureTree['elements'] ?? []) : [];
        if (!is_array($elements)) {
            return [];
        }

        $elementsByObject = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $object = $element['object'] ?? null;
            if (is_int($object)) {
                $elementsByObject[$object] = $element;
            }
        }

        return $elementsByObject;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $element
     */
    private function copyStructureElementReviewFields(array &$row, array $element): void
    {
        foreach ([
            'title',
            'language',
            'language_inherited',
            'alternate_text',
            'actual_text',
            'expansion_text',
            'id',
            'classes',
            'inherited_classes',
            'classes_inherited',
            'attribute_count',
            'attributes',
            'attributes_inherited',
            'revision',
            'namespace',
            'reference_count',
            'references',
            'associated_file_count',
            'associated_files',
            'table_cell_structure',
            'table_cell_review_only',
            'table_cell_raw_scope',
            'table_cell_scope',
            'table_cell_scope_source',
            'table_cell_scope_candidates',
            'table_cell_scope_sources',
            'table_cell_header_ids',
            'table_cell_header_id_count',
            'table_cell_headers_source',
            'table_cell_header_sources',
            'table_cell_resolved_header_ids',
            'table_cell_grouped_header_ids',
            'table_cell_header_graph',
            'table_cell_multiple_headers',
            'table_cell_compound_header_graph',
            'table_cell_header_titles',
            'table_cell_missing_header_ids',
            'table_cell_duplicate_header_ids',
            'table_cell_cycle_header_ids',
        ] as $key) {
            if (array_key_exists($key, $element) && !array_key_exists($key, $row)) {
                $row[$key] = $element[$key];
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function compactReviewRow(array $row): array
    {
        return array_filter($row, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function structureTaggedTablesByPageObject(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $structureTree = $metadata['structure_tree'] ?? [];
        if (!is_array($structureTree)) {
            return [];
        }

        $taggedTables = $structureTree['tagged_tables'] ?? [];
        $tables = is_array($taggedTables) ? ($taggedTables['tables'] ?? []) : [];
        if (!is_array($tables)) {
            return [];
        }

        $rowsByPage = [];
        foreach ($tables as $table) {
            if (!is_array($table)) {
                continue;
            }

            $pageObject = $table['page_object'] ?? null;
            if (!is_int($pageObject)) {
                continue;
            }

            $row = [
                'source' => 'catalog_struct_tree_tagged_table',
                'review_only' => true,
                'visible_text_source' => false,
            ];

            foreach ([
                'struct_object',
                'raw_role',
                'role',
                'role_mapped',
                'page',
                'page_number',
                'page_object',
                'parent_object',
                'parent_role',
                'parent_cell_object',
                'row_count',
                'column_count',
                'cell_count',
                'header_cell_count',
                'nested_table_count',
                'has_nested_tables',
                'child_row_objects',
                'cell_objects',
                'rows',
                'nested_table_objects',
                'direct_mcids',
                'descendant_mcids',
                'unambiguous',
                'diagnostics',
            ] as $key) {
                if (array_key_exists($key, $table)) {
                    $row[$key] = $table[$key];
                }
            }

            $rowsByPage[$pageObject][] = $this->compactReviewRow($row);
        }

        return $rowsByPage;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function structureMarkedContentByPageObject(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $structureTree = $metadata['structure_tree'] ?? [];

        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $rowsByPage = [];
        $elementsByObject = [];
        if (is_array($structureTree)) {
            $elements = $structureTree['elements'] ?? [];
            if (is_array($elements)) {
                foreach ($elements as $elementIndex => $element) {
                    if (!is_array($element)) {
                        continue;
                    }

                    $object = $element['object'] ?? null;
                    if (is_int($object)) {
                        $elementsByObject[$object] = $element;
                    }

                    $markedContent = $element['marked_content'] ?? [];
                    if (!is_array($markedContent)) {
                        continue;
                    }

                    foreach ($markedContent as $markedContentRow) {
                        if (!is_array($markedContentRow)) {
                            continue;
                        }

                        $pageObject = $markedContentRow['page_object'] ?? $element['page_object'] ?? null;
                        $mcid = $markedContentRow['mcid'] ?? null;
                        if (!is_int($pageObject) || !is_int($mcid)) {
                            continue;
                        }

                        $row = [
                            'source' => 'catalog_struct_tree_mcr',
                            'structure_element_index' => (int) $elementIndex,
                            'mcid' => $mcid,
                            'page_object' => $pageObject,
                            'review_only' => true,
                            'visible_text_source' => false,
                        ];

                        foreach ([
                            'object' => 'struct_object',
                            'raw_role' => 'raw_role',
                            'role' => 'role',
                            'role_mapped' => 'role_mapped',
                            'title' => 'title',
                            'language' => 'language',
                            'language_inherited' => 'language_inherited',
                            'alternate_text' => 'alternate_text',
                            'actual_text' => 'actual_text',
                            'expansion_text' => 'expansion_text',
                            'id' => 'id',
                            'classes' => 'classes',
                            'inherited_classes' => 'inherited_classes',
                            'classes_inherited' => 'classes_inherited',
                            'attribute_count' => 'attribute_count',
                            'attributes' => 'attributes',
                            'attributes_inherited' => 'attributes_inherited',
                            'revision' => 'revision',
                            'namespace' => 'namespace',
                            'reference_count' => 'reference_count',
                            'references' => 'references',
                            'associated_file_count' => 'associated_file_count',
                            'associated_files' => 'associated_files',
                            'table_cell_structure' => 'table_cell_structure',
                            'table_cell_review_only' => 'table_cell_review_only',
                            'table_cell_raw_scope' => 'table_cell_raw_scope',
                            'table_cell_scope' => 'table_cell_scope',
                            'table_cell_scope_source' => 'table_cell_scope_source',
                            'table_cell_scope_candidates' => 'table_cell_scope_candidates',
                            'table_cell_scope_sources' => 'table_cell_scope_sources',
                            'table_cell_header_ids' => 'table_cell_header_ids',
                            'table_cell_header_id_count' => 'table_cell_header_id_count',
                            'table_cell_headers_source' => 'table_cell_headers_source',
                            'table_cell_header_sources' => 'table_cell_header_sources',
                            'table_cell_resolved_header_ids' => 'table_cell_resolved_header_ids',
                            'table_cell_grouped_header_ids' => 'table_cell_grouped_header_ids',
                            'table_cell_header_graph' => 'table_cell_header_graph',
                            'table_cell_multiple_headers' => 'table_cell_multiple_headers',
                            'table_cell_compound_header_graph' => 'table_cell_compound_header_graph',
                            'table_cell_header_titles' => 'table_cell_header_titles',
                            'table_cell_missing_header_ids' => 'table_cell_missing_header_ids',
                            'table_cell_duplicate_header_ids' => 'table_cell_duplicate_header_ids',
                            'table_cell_cycle_header_ids' => 'table_cell_cycle_header_ids',
                        ] as $sourceKey => $targetKey) {
                            if (array_key_exists($sourceKey, $element)) {
                                $row[$targetKey] = $element[$sourceKey];
                            }
                        }

                        foreach (['page', 'page_number'] as $pageKey) {
                            if (array_key_exists($pageKey, $markedContentRow)) {
                                $row[$pageKey] = $markedContentRow[$pageKey];
                            }
                        }

                        $pageIndex = $row['page'] ?? null;
                        if (is_int($pageIndex)) {
                            $row['page_label'] = $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
                        }

                        $rowsByPage[$pageObject][] = $row;
                    }
                }
            }
        }

        $fallbackRowsByPage = $this->decorateTaggedContentRowsWithStructureMetadata(
            $this->taggedContentReviewByPageObject($pdfBytes),
            $this->parentTreeEntriesByPageObject($pdfBytes),
            $elementsByObject
        );

        return $this->mergeStructureMarkedContentRows(
            $rowsByPage,
            $fallbackRowsByPage
        );
    }

    /**
     * @param array<int, list<array<string, mixed>>> $rowsByPage
     * @param array<int, array<int, array<string, mixed>>> $parentTreeEntriesByPage
     * @param array<int, array<string, mixed>> $elementsByObject
     * @return array<int, list<array<string, mixed>>>
     */
    private function decorateTaggedContentRowsWithStructureMetadata(
        array $rowsByPage,
        array $parentTreeEntriesByPage,
        array $elementsByObject
    ): array {
        foreach ($rowsByPage as $pageObject => &$rows) {
            foreach ($rows as &$row) {
                $mcid = $row['mcid'] ?? null;
                if (!is_int($mcid)) {
                    continue;
                }

                $parentEntry = $parentTreeEntriesByPage[$pageObject][$mcid] ?? null;
                if (!is_array($parentEntry)) {
                    continue;
                }

                foreach ([
                    'struct_object',
                    'raw_role',
                    'role',
                    'role_mapped',
                ] as $key) {
                    if (array_key_exists($key, $parentEntry) && !array_key_exists($key, $row)) {
                        $row[$key] = $parentEntry[$key];
                    }
                }

                $structObject = $row['struct_object'] ?? null;
                if (!is_int($structObject) || !isset($elementsByObject[$structObject])) {
                    continue;
                }

                foreach ([
                    'title',
                    'language',
                    'language_inherited',
                    'alternate_text',
                    'actual_text',
                    'expansion_text',
                    'id',
                    'classes',
                    'inherited_classes',
                    'classes_inherited',
                    'attribute_count',
                    'attributes',
                    'attributes_inherited',
                    'revision',
                    'namespace',
                    'reference_count',
                    'references',
                    'associated_file_count',
                    'associated_files',
                    'table_cell_structure',
                    'table_cell_review_only',
                    'table_cell_raw_scope',
                    'table_cell_scope',
                    'table_cell_scope_source',
                    'table_cell_scope_candidates',
                    'table_cell_scope_sources',
                    'table_cell_header_ids',
                    'table_cell_header_id_count',
                    'table_cell_headers_source',
                    'table_cell_header_sources',
                    'table_cell_resolved_header_ids',
                    'table_cell_grouped_header_ids',
                    'table_cell_header_graph',
                    'table_cell_multiple_headers',
                    'table_cell_compound_header_graph',
                    'table_cell_header_titles',
                    'table_cell_missing_header_ids',
                    'table_cell_duplicate_header_ids',
                    'table_cell_cycle_header_ids',
                ] as $key) {
                    if (array_key_exists($key, $elementsByObject[$structObject]) && !array_key_exists($key, $row)) {
                        $row[$key] = $elementsByObject[$structObject][$key];
                    }
                }
            }
            unset($row);
        }
        unset($rows);

        return $rowsByPage;
    }

    /**
     * Compose ordinary page annotations with their tagged-PDF StructParent
     * rows so page-level review metadata can carry non-text annotations beside
     * PieceInfo and ParentTree context without promoting annotation text.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function annotationStructureRowsByPageObject(string $pdfBytes): array
    {
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $rowsByPage = [];

        foreach ((new PdfAnnotationExtractor())->extractPageAnnotations($pdfBytes) as $pageAnnotations) {
            if (!is_array($pageAnnotations)) {
                continue;
            }

            $pageObject = $pageAnnotations['page_object'] ?? null;
            $pnum = $pageAnnotations['pnum'] ?? null;
            if (!is_int($pageObject) || !is_int($pnum)) {
                continue;
            }

            $annotations = $pageAnnotations['annotations'] ?? [];
            if (!is_array($annotations)) {
                continue;
            }

            foreach (array_values($annotations) as $annotationIndex => $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $structureParent = $annotation['structure_parent'] ?? null;
                if (
                    !is_array($structureParent)
                    || ($structureParent['current_annotation_object_ref_matched'] ?? false) !== true
                ) {
                    continue;
                }

                $actions = $this->compactReviewRows($annotation['actions'] ?? []);
                $additionalActions = $this->compactReviewRows($annotation['additional_actions'] ?? []);
                $replyThread = $this->compactReviewRowFromValue($annotation['reply_thread'] ?? null);

                $row = [
                    'source' => 'page_annotation_struct_parent_review',
                    'pnum' => $pnum,
                    'page' => $pnum,
                    'page_number' => $pnum + 1,
                    'page_label' => $pageLabels[$pnum] ?? (string) ($pnum + 1),
                    'page_object' => $pageObject,
                    'annotation_index' => $annotationIndex,
                    'annotation_object' => $annotation['annotation_object'] ?? null,
                    'subtype' => $annotation['subtype'] ?? null,
                    'rect' => $annotation['rect'] ?? null,
                    'contents' => $annotation['contents'] ?? null,
                    'title' => $annotation['title'] ?? null,
                    'name' => $annotation['name'] ?? null,
                    'modified_at' => $annotation['modified_at'] ?? null,
                    'struct_parent' => $annotation['struct_parent'] ?? null,
                    'structure_parent' => $structureParent,
                    'actions' => $actions,
                    'additional_actions' => $additionalActions,
                    'action_count' => count($actions),
                    'additional_action_count' => count($additionalActions),
                    'annotation_actions_review_only' => $actions !== [] || $additionalActions !== [],
                    'target_page_action_context_review_only' => $this->actionsHaveTargetPageContext($actions, $additionalActions),
                    'reply_thread' => $replyThread,
                    'executes_actions_on_import' => $annotation['executes_actions_on_import'] ?? false,
                    'review_only' => true,
                    'visible_text_source' => false,
                    'renders_annotation_on_import' => false,
                ];

                $rowsByPage[$pageObject][] = $this->compactReviewRow($row);
            }
        }

        return $rowsByPage;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compactReviewRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $compacted = $this->compactReviewRowFromValue($row);
            if ($compacted !== []) {
                $out[] = $compacted;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function compactReviewRowFromValue(mixed $row): array
    {
        return is_array($row) ? $this->compactReviewRow($row) : [];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<array<string, mixed>> $additionalActions
     */
    private function actionsHaveTargetPageContext(array $actions, array $additionalActions): bool
    {
        foreach (array_merge($actions, $additionalActions) as $action) {
            foreach (['target_display_duration', 'target_page_transition', 'target_page_actions'] as $key) {
                if (array_key_exists($key, $action)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, array<string, mixed>> $pieceInfo
     * @param array<string, mixed>|null $parentTree
     * @return list<array<string, mixed>>
     */
    private function annotationStructureRowsWithPageContext(
        array $rows,
        array $pieceInfo,
        ?int $structParents,
        ?array $parentTree
    ): array {
        if ($rows === []) {
            return [];
        }

        return array_map(
            function (array $row) use ($pieceInfo, $structParents, $parentTree): array {
                if ($structParents !== null) {
                    $row['page_struct_parents'] = $structParents;
                }

                if ($parentTree !== null) {
                    $row['page_parent_tree'] = $parentTree;
                }

                if ($pieceInfo !== []) {
                    $row['page_piece_info'] = $pieceInfo;
                    $row['page_piece_info_review_only'] = true;
                }

                return $this->compactReviewRow($row);
            },
            $rows
        );
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function parentTreeEntriesByPageObject(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        if ($catalog === null) {
            return [];
        }

        $entries = [];
        foreach ($this->pageBoundaryMetadataByPageObject($pdfBytes, $catalog, $objects) as $pageObject => $page) {
            $parentTree = $page['parent_tree']['entries'] ?? null;
            if (!is_array($parentTree)) {
                continue;
            }

            foreach ($parentTree as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $mcid = $entry['mcid'] ?? null;
                if (is_int($mcid)) {
                    $entries[$pageObject][$mcid] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function taggedContentReviewByPageObject(string $pdfBytes): array
    {
        $extractor = new PdfTextExtractor();
        $pageLabels = $extractor->extractPageLabels($pdfBytes);
        $rowsByPage = [];

        foreach ($extractor->extractTaggedContent($pdfBytes) as $taggedRow) {
            $pageObject = $taggedRow['page_object_number'] ?? null;
            $mcid = $taggedRow['mcid'] ?? null;
            if (!is_int($pageObject) || !is_int($mcid)) {
                continue;
            }

            $row = [
                'source' => 'page_structparents_parenttree_tagged_content',
                'mcid' => $mcid,
                'page_object' => $pageObject,
                'review_only' => true,
                'visible_text_source' => false,
                'resources_resolved_for_tagged_text' => true,
            ];

            foreach ([
                'page_index' => 'page',
                'page_number' => 'page_number',
                'raw_role' => 'raw_role',
                'role' => 'role',
                'role_mapped' => 'role_mapped',
                'content_tags' => 'content_tags',
            ] as $sourceKey => $targetKey) {
                if (array_key_exists($sourceKey, $taggedRow)) {
                    $row[$targetKey] = $taggedRow[$sourceKey];
                }
            }

            $pageIndex = $taggedRow['page_index'] ?? null;
            if (is_int($pageIndex)) {
                $row['page_label'] = $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
            }

            $rowsByPage[$pageObject][] = $row;
        }

        return $rowsByPage;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $rowsByPage
     * @param array<int, list<array<string, mixed>>> $fallbackRowsByPage
     * @return array<int, list<array<string, mixed>>>
     */
    private function mergeStructureMarkedContentRows(array $rowsByPage, array $fallbackRowsByPage): array
    {
        foreach ($fallbackRowsByPage as $pageObject => $fallbackRows) {
            $rowsByPage[$pageObject] ??= [];
            $knownMcids = [];
            foreach ($rowsByPage[$pageObject] as $row) {
                $mcid = $row['mcid'] ?? null;
                if (is_int($mcid)) {
                    $knownMcids[$mcid] = true;
                }
            }

            foreach ($fallbackRows as $fallbackRow) {
                $mcid = $fallbackRow['mcid'] ?? null;
                if (!is_int($mcid) || isset($knownMcids[$mcid])) {
                    continue;
                }

                $rowsByPage[$pageObject][] = $fallbackRow;
                $knownMcids[$mcid] = true;
            }
        }

        return $rowsByPage;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pagePresentationMetadataByPageObject(string $pdfBytes): array
    {
        $presentations = [];
        foreach ((new PdfOutlineExtractor())->getPageTransitionActionMetadata($pdfBytes) as $presentation) {
            $pageObject = $presentation['page_object'] ?? null;
            if (is_int($pageObject)) {
                $presentations[$pageObject] = $presentation;
            }
        }

        return $presentations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array<string, mixed>>
     */
    private function pageBoundaryMetadataByPageObject(string $pdfBytes, string $catalog, array $objects): array
    {
        $pageObjectNumbers = $this->orderedPageObjectNumbers($catalog, $objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $pagePresentationsByObject = $this->pagePresentationMetadataByPageObject($pdfBytes);
        $structTreeRoot = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'StructTreeRoot'), $objects);
        $roleMap = $structTreeRoot === null ? [] : $this->structureRoleMap($structTreeRoot['body'], $objects);
        $parentTreeArrays = $structTreeRoot === null ? [] : $this->structureParentTreeArrays($structTreeRoot['body'], $objects);

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObjectNumber] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $structParents = $this->dictionaryIntegerValue($pageBody, 'StructParents');
            $parentTree = $structParents === null
                ? null
                : $this->parentTreeMetadata($structParents, $parentTreeArrays[$structParents] ?? null, $objects, $roleMap);
            $resources = $this->effectivePageResourcesMetadata($pageObjectNumber, $objects, $catalog);
            $pagePresentation = $pagePresentationsByObject[$pageObjectNumber] ?? null;

            if ($structParents === null && $resources === null && $pagePresentation === null) {
                continue;
            }

            $page = [
                'source' => 'page_boundary_review',
                'pnum' => $pnum,
                'page_number' => $pnum + 1,
                'page_object' => $pageObjectNumber,
                'page_label' => $pageLabels[$pnum] ?? (string) ($pnum + 1),
            ];

            if ($structParents !== null) {
                $page['struct_parents'] = $structParents;
                $page['parent_tree'] = $parentTree;
            }

            if ($resources !== null) {
                $page['resources'] = $resources;
            }

            if ($pagePresentation !== null) {
                $page['page_presentation'] = $pagePresentation;
            }

            $pages[$pageObjectNumber] = $page;
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $parentTreeArrays
     * @param array<string, string> $roleMap
     * @return array<string, mixed>
     */
    private function parentTreeMetadata(int $structParents, ?string $parentTreeArray, array $objects, array $roleMap): array
    {
        $metadata = [
            'source' => 'struct_tree_parent_tree',
            'key' => $structParents,
            'entries' => [],
            'mcids' => [],
            'roles' => [],
        ];
        if ($parentTreeArray === null) {
            return $metadata;
        }

        $roles = [];
        foreach ($this->arrayItemsFromValue($parentTreeArray, $objects) as $mcid => $parentValue) {
            $parent = $this->resolveDictionaryFromValue($parentValue, $objects);
            if ($parent === null) {
                continue;
            }

            $rawRole = $this->dictionaryNameValue($parent['body'], 'S', $objects);
            if ($rawRole === null || $rawRole === '') {
                continue;
            }

            $role = $roleMap[$rawRole] ?? $rawRole;
            $entry = [
                'mcid' => $mcid,
                'struct_object' => $parent['object'],
                'raw_role' => $rawRole,
                'role' => $role,
                'role_mapped' => $role !== $rawRole,
            ];

            $metadata['entries'][] = $entry;
            $metadata['mcids'][] = $mcid;
            $roles[$role] = $role;
        }

        $metadata['roles'] = array_values($roles);
        $metadata['entry_count'] = count($metadata['entries']);

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function structureParentTreeArrays(string $structTreeRoot, array $objects): array
    {
        $parentTree = $this->resolveDictionaryFromValue($this->dictionaryRawValue($structTreeRoot, 'ParentTree'), $objects);
        if ($parentTree === null) {
            return [];
        }

        $arrays = [];
        $this->collectStructureParentTreeArrays($parentTree['body'], $objects, $arrays);

        return $arrays;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $arrays
     * @param array<int, true> $seenObjects
     */
    private function collectStructureParentTreeArrays(
        string $dictionary,
        array $objects,
        array &$arrays,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $nums = $this->dictionaryRawValue($dictionary, 'Nums');
        if ($nums !== null) {
            $items = $this->arrayItemsFromValue($nums, $objects);
            for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
                $key = trim($items[$index]);
                if (preg_match('/^[+-]?\d+$/', $key) !== 1) {
                    continue;
                }

                $array = $this->pdfArrayFromValue($items[$index + 1], $objects);
                if ($array !== null) {
                    $arrays[(int) $key] = $array;
                }
            }
        }

        $kids = $this->dictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return;
        }

        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $kidObjectNumber = $this->objectNumberFromReference($kidValue);
            if ($kidObjectNumber === null || isset($seenObjects[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seenObjects;
            $nextSeen[$kidObjectNumber] = true;
            $this->collectStructureParentTreeArrays($kidDictionary, $objects, $arrays, $nextSeen, $depth + 1);
        }
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function structureRoleMap(string $structTreeRoot, array $objects): array
    {
        $roleMap = $this->resolveDictionaryFromValue($this->dictionaryRawValue($structTreeRoot, 'RoleMap'), $objects);
        if ($roleMap === null) {
            return [];
        }

        $roles = [];
        foreach ($this->dictionaryEntries($roleMap['body']) as $sourceRole => $targetValue) {
            $resolved = trim($this->resolveRawValue($targetValue, $objects) ?? $targetValue);
            if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/s', $resolved, $match) !== 1) {
                continue;
            }

            $roles[$sourceRole] = $this->decodePdfName($match[1]);
        }

        return $roles;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function effectivePageResourcesMetadata(int $pageObjectNumber, array $objects, string $catalog): ?array
    {
        $resourceLookupObjects = [];
        foreach ($this->pageObjectLineage($pageObjectNumber, $objects, $catalog) as $objectNumber) {
            $objectDictionary = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
            if ($objectDictionary === null) {
                continue;
            }

            $resourceLookupObjects[] = $objectNumber;
            $resourceValue = $this->topLevelDictionaryRawValue($objectDictionary, 'Resources');
            if ($resourceValue === null) {
                continue;
            }

            if ($this->topLevelDirectDictionaryValueHasTrailingNonName($objectDictionary, 'Resources')) {
                return $this->malformedPageResourcesMetadata(
                    $pageObjectNumber,
                    $objectNumber,
                    $resourceValue,
                    $resourceLookupObjects
                );
            }

            if ($this->pageResourceValueAllowsInheritance($resourceValue, $objects)) {
                continue;
            }

            $resourceReference = $this->objectReferenceFromValue($resourceValue);
            $resourceObject = $resourceReference === null
                ? null
                : $this->resolvedObjectValueFromReference(
                    $objects,
                    $resourceReference['objectNumber'],
                    $resourceReference['generation']
                );
            if ($resourceObject !== null && $this->objectBodyIsStreamObject($resourceObject['body'])) {
                return $this->malformedPageResourcesMetadata(
                    $pageObjectNumber,
                    $objectNumber,
                    $resourceValue,
                    $resourceLookupObjects
                );
            }

            $resources = $this->resolvePageResourceDictionaryFromValue($resourceValue, $objects);
            if ($resources === null) {
                return $this->malformedPageResourcesMetadata(
                    $pageObjectNumber,
                    $objectNumber,
                    $resourceValue,
                    $resourceLookupObjects
                );
            }

            $resourceBody = $resources['body'];
            $metadata = [
                'source' => 'page_tree_resources',
                'status' => 'resolved',
                'resolved' => true,
                'resource_owner_object' => $objectNumber,
                'resource_object' => $resources['object'],
                'resource_generation' => $resources['generation'],
                'inherited' => $objectNumber !== $pageObjectNumber,
                'resource_lookup_objects' => $resourceLookupObjects,
                'categories' => $this->resourceDictionaryCategoryNames($resourceBody),
            ];

            foreach ([
                'Font' => 'font_names',
                'XObject' => 'xobject_names',
                'Properties' => 'properties_names',
                'ColorSpace' => 'color_space_names',
                'ExtGState' => 'extgstate_names',
                'Pattern' => 'pattern_names',
                'Shading' => 'shading_names',
            ] as $resourceKey => $metadataKey) {
                $names = $this->resourceSubdictionaryNames($resourceBody, $resourceKey, $objects);
                if ($names !== []) {
                    $metadata[$metadataKey] = $names;
                }
            }

            $procSetNames = $this->procSetNames($resourceBody, $objects);
            if ($procSetNames !== []) {
                $metadata['procset_names'] = $procSetNames;
            }

            return $metadata;
        }

        return null;
    }

    private function topLevelDirectDictionaryValueHasTrailingNonName(string $dictionary, string $key): bool
    {
        $invalid = false;
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $value = $this->readPdfValueAt($dictionary, $offset);
                $offset = $value !== null && $value['end'] > $offset ? $value['end'] : $offset + 1;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $value = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            if ($this->decodePdfName($match[1]) === $key) {
                $afterOffset = $this->skipWhitespace($dictionary, $value['end']);
                $invalid = $afterOffset < $length
                    && ($dictionary[$afterOffset] ?? '') !== '/';
            }

            $offset = $value['end'];
        }

        return $invalid;
    }

    /**
     * @return list<string>
     */
    private function resourceDictionaryCategoryNames(string $resourceBody): array
    {
        $allowed = [
            'ExtGState' => true,
            'ColorSpace' => true,
            'Pattern' => true,
            'Shading' => true,
            'XObject' => true,
            'Font' => true,
            'ProcSet' => true,
            'Properties' => true,
        ];

        $categories = [];
        foreach (array_keys($this->dictionaryEntries($resourceBody)) as $category) {
            if (isset($allowed[$category])) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Page /Resources references are parser lookup roots. Accept reference
     * wrappers, null wrappers, and one dictionary object; reject trailing tokens.
     *
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, generation: int|null}|null
     */
    private function resolvePageResourceDictionaryFromValue(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $resolved = $this->resolvedObjectValueFromReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation']
            );
            if ($resolved === null) {
                return null;
            }

            $body = $this->singleDictionaryObjectBody($resolved['body']);
            return $body === null
                ? null
                : [
                    'body' => $body,
                    'object' => $resolved['object'],
                    'generation' => $resolved['generation'],
                ];
        }

        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return null;
        }

        $trimmed = trim($resolved);
        if (!str_starts_with($trimmed, '<<')) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($trimmed, 0);
        if ($dictionary === null || $this->skipWhitespace($trimmed, $dictionary['end']) < strlen($trimmed)) {
            return null;
        }

        return ['body' => $dictionary['body'], 'object' => null, 'generation' => null];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageResourceValueAllowsInheritance(string $value, array $objects): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'null') {
            return true;
        }

        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference === null) {
            return false;
        }

        $resolved = $this->resolvedObjectValueFromReference(
            $objects,
            $reference['objectNumber'],
            $reference['generation']
        );

        return $resolved !== null && $this->objectBodyIsSingleNullValue($resolved['body']);
    }

    /**
     * @param list<int> $resourceLookupObjects
     * @return array<string, mixed>
     */
    private function malformedPageResourcesMetadata(
        int $pageObjectNumber,
        int $objectNumber,
        string $resourceValue,
        array $resourceLookupObjects
    ): array
    {
        return [
            'source' => 'page_tree_resources',
            'status' => 'unresolved_or_malformed',
            'resolved' => false,
            'resource_owner_object' => $objectNumber,
            'resource_object' => $this->objectNumberFromReference($resourceValue),
            'resource_generation' => $this->objectReferenceFromValue($resourceValue)['generation'] ?? null,
            'inherited' => $objectNumber !== $pageObjectNumber,
            'resource_lookup_objects' => $resourceLookupObjects,
            'categories' => [],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function resourceSubdictionaryNames(string $resourceDictionary, string $key, array $objects): array
    {
        if ($this->topLevelDirectDictionaryValueHasTrailingNonName($resourceDictionary, $key)) {
            return [];
        }

        $value = $this->dictionaryRawValue($resourceDictionary, $key);
        if ($this->resourceCategoryValueResolvesToStreamObject($value, $objects)) {
            return [];
        }

        $subdictionary = $this->resolveResourceCategoryDictionaryFromValue($value, $objects);
        if ($subdictionary === null) {
            return [];
        }

        $names = [];
        $duplicateReferenceNames = $this->duplicateResourceSubdictionaryEntryNames($subdictionary['body']);
        foreach ($this->resourceSubdictionaryEntries($subdictionary['body']) as $name => $entry) {
            if (isset($duplicateReferenceNames[$name])) {
                unset($names[$name]);
                continue;
            }

            if (
                !$entry['malformed_tail']
                && $this->resourceSubdictionaryEntryIsResolvable($entry['value'], $objects, $key)
            ) {
                $names[$name] = $name;
                continue;
            }

            unset($names[$name]);
        }

        return array_values($names);
    }

    /**
     * @return array<string, array{value: string, malformed_tail: bool}>
     */
    private function resourceSubdictionaryEntries(string $dictionary): array
    {
        $entries = [];
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            $value = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            $tailOffset = $this->skipWhitespace($dictionary, $value['end']);
            $entries[$name] = [
                'value' => $value['raw'],
                'malformed_tail' => $tailOffset < $length && ($dictionary[$tailOffset] ?? '') !== '/',
            ];
            $offset = $value['end'];
        }

        return $entries;
    }

    /**
     * @return array<string, true>
     */
    private function duplicateResourceSubdictionaryEntryNames(string $dictionary): array
    {
        $counts = [];
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $value = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $offset = $value['end'];
        }

        $duplicates = [];
        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $duplicates[$name] = true;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function procSetNames(string $resourceDictionary, array $objects): array
    {
        $value = $this->dictionaryRawValue($resourceDictionary, 'ProcSet');
        if (
            $value === null
            || $this->topLevelDirectDictionaryValueHasTrailingNonName($resourceDictionary, 'ProcSet')
            || $this->resourceCategoryValueResolvesToStreamObject($value, $objects)
        ) {
            return [];
        }

        $names = [];
        foreach ($this->arrayItemsFromStrictValue($value, $objects) as $item) {
            $resolved = trim($this->resolveRawValue($item, $objects) ?? $item);
            if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $resolved, $match) !== 1) {
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceCategoryValueResolvesToStreamObject(?string $value, array $objects): bool
    {
        if ($value === null) {
            return false;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return false;
        }

        $resolved = $this->resolvedObjectValueFromReference(
            $objects,
            $reference['objectNumber'],
            $reference['generation']
        );

        return $resolved !== null && $this->objectBodyIsStreamObject($resolved['body']);
    }

    /**
     * Resource category values are dictionary operands, not arbitrary object
     * bodies. A referenced object like `<<...>> 99 0 R` must fail closed even
     * though generic metadata resolvers may inspect stream dictionaries.
     *
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, generation: int|null}|null
     */
    private function resolveResourceCategoryDictionaryFromValue(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $resolved = $this->resolvedObjectValueFromReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation']
            );
            if ($resolved === null) {
                return null;
            }

            $body = $this->singleDictionaryObjectBody($resolved['body']);
            return $body === null
                ? null
                : [
                    'body' => $body,
                    'object' => $resolved['object'],
                    'generation' => $resolved['generation'],
                ];
        }

        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return null;
        }

        $trimmed = trim($resolved);
        $dictionary = $this->readPdfDictionaryAt($trimmed, 0);
        if ($dictionary === null || $this->skipWhitespace($trimmed, $dictionary['end']) < strlen($trimmed)) {
            return null;
        }

        return [
            'body' => $dictionary['body'],
            'object' => null,
            'generation' => null,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceSubdictionaryEntryIsResolvable(string $value, array $objects, string $category): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === 'null') {
            return false;
        }

        if ($category === 'ColorSpace') {
            return $this->colorSpaceResourceEntryIsResolvable($trimmed, $objects);
        }

        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference === null) {
            return str_starts_with($trimmed, '<<')
                && $this->readPdfDictionaryAt($trimmed, 0) !== null;
        }

        if (!$this->resourceCategoryAllowsStreamEntries($category)) {
            $resolvedObject = $this->resolvedObjectValueFromReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation']
            );
            if ($resolvedObject !== null && $this->objectBodyIsStreamObject($resolvedObject['body'])) {
                return false;
            }
        }

        $resolved = $this->resolveRawValue($trimmed, $objects);
        return $resolved !== null && $this->dictionaryObjectBody($resolved) !== null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function colorSpaceResourceEntryIsResolvable(string $value, array $objects): bool
    {
        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return false;
        }

        $trimmed = trim($resolved);
        if ($trimmed === '' || $trimmed === 'null') {
            return false;
        }

        if ($this->colorSpaceResourceEntryIsName($trimmed)) {
            return true;
        }

        $array = $this->readPdfArrayAt($trimmed, 0);
        return $array !== null && $this->skipWhitespace($trimmed, $array['end']) >= strlen($trimmed);
    }

    private function colorSpaceResourceEntryIsName(string $value): bool
    {
        if (($value[0] ?? '') !== '/') {
            return false;
        }

        $end = 1;
        for ($length = strlen($value); $end < $length; $end++) {
            if ($this->isPdfDelimiter($value[$end]) || $value[$end] === '/') {
                break;
            }
        }

        return $end > 1 && $this->skipWhitespace($value, $end) >= strlen($value);
    }

    private function resourceCategoryAllowsStreamEntries(string $category): bool
    {
        return in_array($category, ['XObject', 'Pattern', 'Shading'], true);
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function pageObjectLineage(int $pageObjectNumber, array $objects, string $catalog): array
    {
        $lineage = [];
        $seen = [];
        $objectNumber = $pageObjectNumber;

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            $lineage[] = $objectNumber;

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary === null) {
                break;
            }

            $parentValue = $this->dictionaryRawValue($dictionary, 'Parent');
            if ($this->topLevelDirectDictionaryValueHasTrailingNonName($dictionary, 'Parent')) {
                break;
            }

            if ($parentValue === null) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($this->pageObjectLineageIsPrefix($lineage, $catalogLineage)) {
                    return $catalogLineage;
                }

                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            if ($this->pageParentValueAllowsCatalogPath($parentValue, $objects)) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($this->pageObjectLineageIsPrefix($lineage, $catalogLineage)) {
                    return $catalogLineage;
                }

                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            if ($this->topLevelDirectDictionaryValueHasTrailingNonName($dictionary, 'Parent')) {
                break;
            }

            $parentReference = $this->objectReferenceFromValue($parentValue);
            if ($parentReference === null) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            $resolvedParent = $this->resolvedPageTreeReference(
                $objects,
                $parentReference['objectNumber'],
                $parentReference['generation']
            );
            if (
                $resolvedParent === null
                || $this->pdfObjectTypeName($resolvedParent['body'], $objects) !== 'Pages'
            ) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            $parentObjectNumber = $resolvedParent['objectNumber'];
            $parentDictionary = $this->dictionaryObjectBody($resolvedParent['body']);
            if ($parentDictionary === null) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            $childGeneration = $this->currentObjectGenerations[$objectNumber] ?? null;
            if (
                $childGeneration === null
                || !$this->pageTreeParentListsChild($parentDictionary, $objectNumber, $childGeneration, $objects)
            ) {
                $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
                if ($catalogLineage !== []) {
                    return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
                }

                break;
            }

            $objectNumber = $parentObjectNumber;
        }

        if (isset($objects[$objectNumber], $seen[$objectNumber])) {
            $catalogLineage = $this->pageObjectLineageFromCatalogPath($pageObjectNumber, $catalog, $objects, $lineage);
            if ($catalogLineage !== []) {
                return $this->pageObjectLineageCommonPrefix($lineage, $catalogLineage);
            }
        }

        return $lineage;
    }

    /**
     * @param array<int, string> $objects
     * @param list<int> $preferredPrefix
     * @return list<int>
     */
    private function pageObjectLineageFromCatalogPath(
        int $pageObjectNumber,
        string $catalog,
        array $objects,
        array $preferredPrefix = []
    ): array
    {
        $pagesValue = $this->dictionaryRawValue($catalog, 'Pages');
        $pagesReference = $pagesValue === null ? null : $this->objectReferenceFromValue($pagesValue);
        if ($pagesReference === null) {
            return [];
        }

        return $this->pageObjectLineageFromTreeReference(
            $pagesReference['objectNumber'],
            $pagesReference['generation'],
            $pageObjectNumber,
            $objects,
            [],
            $preferredPrefix
        ) ?? [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @param list<int> $preferredPrefix
     * @return list<int>|null
     */
    private function pageObjectLineageFromTreeReference(
        int $objectNumber,
        int $generation,
        int $targetPageObjectNumber,
        array $objects,
        array $seen = [],
        array $preferredPrefix = []
    ): ?array {
        $resolved = $this->resolvedPageTreeReference($objects, $objectNumber, $generation, $seen);
        if ($resolved === null) {
            return null;
        }

        $objectNumber = $resolved['objectNumber'];
        $generation = $resolved['generation'];
        $body = $resolved['body'];
        $referenceKey = $objectNumber . ':' . $generation;
        $seen[$referenceKey] = true;
        $type = $this->pdfObjectTypeName($body, $objects);
        if ($type === 'Page') {
            return $objectNumber === $targetPageObjectNumber ? [$objectNumber] : null;
        }

        if ($type !== 'Pages') {
            return null;
        }

        $dictionary = $this->dictionaryObjectBody($body);
        if ($dictionary === null) {
            return null;
        }

        $kids = $this->topLevelDictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return null;
        }

        $firstLineage = null;
        $bestLineage = null;
        $bestPrefixLength = -1;
        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $childReference = $this->objectReferenceFromValue($kidValue);
            if ($childReference === null) {
                continue;
            }

            $lineage = $this->pageObjectLineageFromTreeReference(
                $childReference['objectNumber'],
                $childReference['generation'],
                $targetPageObjectNumber,
                $objects,
                $seen,
                $preferredPrefix
            );
            if ($lineage !== null) {
                $lineage[] = $objectNumber;
                $firstLineage ??= $lineage;
                if ($preferredPrefix === []) {
                    return $lineage;
                }

                if ($this->pageObjectLineageIsPrefix($preferredPrefix, $lineage)) {
                    return $lineage;
                }

                $prefixLength = count($this->pageObjectLineageCommonPrefix($preferredPrefix, $lineage));
                if ($prefixLength > $bestPrefixLength) {
                    $bestPrefixLength = $prefixLength;
                    $bestLineage = $lineage;
                }
            }
        }

        return $bestLineage ?? $firstLineage;
    }

    /**
     * @param list<int> $lineage
     * @param list<int> $catalogLineage
     */
    private function pageObjectLineageIsPrefix(array $lineage, array $catalogLineage): bool
    {
        if ($lineage === [] || count($lineage) > count($catalogLineage)) {
            return false;
        }

        foreach ($lineage as $index => $objectNumber) {
            if (($catalogLineage[$index] ?? null) !== $objectNumber) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $lineage
     * @param list<int> $catalogLineage
     * @return list<int>
     */
    private function pageObjectLineageCommonPrefix(array $lineage, array $catalogLineage): array
    {
        $prefix = [];
        foreach ($lineage as $index => $objectNumber) {
            if (($catalogLineage[$index] ?? null) !== $objectNumber) {
                break;
            }

            $prefix[] = $objectNumber;
        }

        return $prefix;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageParentValueAllowsCatalogPath(string $parentValue, array $objects): bool
    {
        $trimmed = trim($parentValue);
        if ($trimmed === 'null') {
            return true;
        }

        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference === null) {
            return false;
        }

        $resolved = $this->resolvedObjectValueFromReference(
            $objects,
            $reference['objectNumber'],
            $reference['generation']
        );

        return $resolved !== null && trim($resolved['body']) === 'null';
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageTreeParentListsChild(string $parentDictionary, int $childObjectNumber, int $childGeneration, array $objects): bool
    {
        $kids = $this->topLevelDictionaryRawValue($parentDictionary, 'Kids');
        if ($kids === null) {
            return false;
        }

        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $reference = $this->objectReferenceFromValue($kidValue);
            $resolved = $reference === null
                ? null
                : $this->resolvedPageTreeReference(
                    $objects,
                    $reference['objectNumber'],
                    $reference['generation']
                );
            if (
                $resolved !== null
                && $resolved['objectNumber'] === $childObjectNumber
                && $resolved['generation'] === $childGeneration
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function markInfoMetadata(string $catalog, array $objects): array
    {
        $markInfo = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'MarkInfo'), $objects);
        if ($markInfo === null) {
            return [];
        }

        $metadata = ['source' => 'catalog_mark_info'];
        foreach ([
            'marked' => 'Marked',
            'user_properties' => 'UserProperties',
            'suspects' => 'Suspects',
        ] as $metadataKey => $pdfKey) {
            $value = $this->reviewValueFromRaw($this->dictionaryRawValue($markInfo['body'], $pdfKey), $objects);
            if (is_bool($value)) {
                $metadata[$metadataKey] = $value;
            }
        }

        return count($metadata) > 1 ? $metadata : [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, list<array<string, mixed>>>
     */
    private function structureUserPropertiesByPageObject(string $catalog, array $objects): array
    {
        $structTreeRoot = $this->resolveDictionaryFromValue($this->dictionaryRawValue($catalog, 'StructTreeRoot'), $objects);
        if ($structTreeRoot === null) {
            return [];
        }

        $propertiesByPage = [];
        $this->collectPageParentTreeUserProperties($catalog, $structTreeRoot['body'], $objects, $propertiesByPage);
        $this->collectStructureUserProperties(
            $this->dictionaryRawValue($structTreeRoot['body'], 'K'),
            $objects,
            $propertiesByPage,
            [],
            null
        );

        return $this->deduplicateUserPropertiesByPageObject($propertiesByPage);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array<string, mixed>>> $propertiesByPage
     */
    private function collectPageParentTreeUserProperties(
        string $catalog,
        string $structTreeRoot,
        array $objects,
        array &$propertiesByPage
    ): void {
        $roleMap = $this->structureRoleMap($structTreeRoot, $objects);
        $parentTreeArrays = $this->structureParentTreeArrays($structTreeRoot, $objects);
        if ($parentTreeArrays === []) {
            return;
        }

        foreach ($this->orderedPageObjectNumbers($catalog, $objects) as $pageObjectNumber) {
            $pageBody = $this->dictionaryObjectBody($objects[$pageObjectNumber] ?? '');
            if ($pageBody === null) {
                continue;
            }

            $structParents = $this->dictionaryIntegerValue($pageBody, 'StructParents');
            if ($structParents === null || !isset($parentTreeArrays[$structParents])) {
                continue;
            }

            foreach ($this->arrayItemsFromValue($parentTreeArrays[$structParents], $objects) as $mcid => $parentValue) {
                $parent = $this->resolveDictionaryFromValue($parentValue, $objects);
                if ($parent === null) {
                    continue;
                }

                $body = $parent['body'];
                $rawRole = $this->dictionaryNameValue($body, 'S', $objects);
                $role = ($rawRole !== null && $rawRole !== '') ? ($roleMap[$rawRole] ?? $rawRole) : null;
                $context = [
                    'source' => 'page_structparents_user_properties',
                    'struct_parents' => $structParents,
                    'mcid' => $mcid,
                    'struct_object' => $parent['object'],
                    'raw_role' => $rawRole,
                    'role' => $role,
                    'role_mapped' => $role !== null && $rawRole !== null && $role !== $rawRole,
                ];

                foreach ($this->userPropertiesFromAttributeValue(
                    $this->dictionaryRawValue($body, 'A'),
                    $objects,
                    $rawRole,
                    $this->dictionaryStringValue($body, 'T', $objects),
                    $context
                ) as $property) {
                    $propertiesByPage[$pageObjectNumber][] = $property;
                }
            }
        }
    }

    /**
     * @param array<int, list<array<string, mixed>>> $propertiesByPage
     * @return array<int, list<array<string, mixed>>>
     */
    private function deduplicateUserPropertiesByPageObject(array $propertiesByPage): array
    {
        foreach ($propertiesByPage as $pageObject => $properties) {
            $seen = [];
            $deduplicated = [];
            foreach ($properties as $property) {
                $key = implode("\0", [
                    (string) ($property['attribute_object'] ?? ''),
                    (string) ($property['name'] ?? ''),
                    json_encode($property['value'] ?? null, JSON_UNESCAPED_SLASHES) ?: 'null',
                    (string) ($property['formatted_value'] ?? ''),
                    (string) ($property['struct_type'] ?? ''),
                    (string) ($property['title'] ?? ''),
                ]);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $deduplicated[] = $property;
            }

            $propertiesByPage[$pageObject] = $deduplicated;
        }

        return $propertiesByPage;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array<string, mixed>>> $propertiesByPage
     * @param array<int, true> $seenObjects
     */
    private function collectStructureUserProperties(
        ?string $value,
        array $objects,
        array &$propertiesByPage,
        array $seenObjects,
        ?int $inheritedPageObject
    ): void
    {
        if ($value === null) {
            return;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return;
        }

        if (str_starts_with($resolved, '[')) {
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $this->collectStructureUserProperties($item, $objects, $propertiesByPage, $seenObjects, $inheritedPageObject);
            }
            return;
        }

        $struct = $this->resolveDictionaryFromValue($value, $objects);
        if ($struct === null) {
            return;
        }

        $objectNumber = $struct['object'];
        if ($objectNumber !== null) {
            if (isset($seenObjects[$objectNumber])) {
                return;
            }
            $seenObjects[$objectNumber] = true;
        }

        $body = $struct['body'];
        $kidValue = $this->dictionaryRawValue($body, 'K');
        $pageObject = $this->objectReferenceValueAfterName($body, 'Pg')
            ?? $this->pageObjectFromStructureKid($kidValue, $objects)
            ?? $inheritedPageObject;
        $structType = $this->dictionaryNameValue($body, 'S', $objects);
        $title = $this->dictionaryStringValue($body, 'T', $objects);

        if ($pageObject !== null) {
            foreach ($this->userPropertiesFromAttributeValue($this->dictionaryRawValue($body, 'A'), $objects, $structType, $title) as $property) {
                $propertiesByPage[$pageObject][] = $property;
            }
        }

        if ($kidValue !== null) {
            $this->collectStructureUserProperties($kidValue, $objects, $propertiesByPage, $seenObjects, $pageObject);
        }
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function userPropertiesFromAttributeValue(
        ?string $attributeValue,
        array $objects,
        ?string $structType,
        ?string $title,
        array $context = []
    ): array {
        $properties = [];
        foreach ($this->dictionariesFromValue($attributeValue, $objects) as $attribute) {
            $body = $attribute['body'];
            if ($this->dictionaryNameValue($body, 'O', $objects) !== 'UserProperties') {
                continue;
            }

            foreach ($this->dictionariesFromValue($this->dictionaryRawValue($body, 'P'), $objects) as $propertyDictionary) {
                $name = $this->dictionaryStringValue($propertyDictionary['body'], 'N', $objects);
                if ($name === null || $name === '') {
                    continue;
                }

                $property = [
                    'source' => is_string($context['source'] ?? null)
                        ? $context['source']
                        : 'structure_user_properties',
                    'name' => $name,
                    'hidden' => $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'H'), $objects) === true,
                ];

                foreach ($context as $key => $contextValue) {
                    if (
                        $key !== 'source'
                        && $contextValue !== null
                        && $contextValue !== ''
                    ) {
                        $property[$key] = $contextValue;
                    }
                }
                if ($attribute['object'] !== null) {
                    $property['attribute_object'] = $attribute['object'];
                }
                if ($structType !== null && $structType !== '') {
                    $property['struct_type'] = $structType;
                }
                if ($title !== null && $title !== '') {
                    $property['title'] = $title;
                }

                $value = $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'V'), $objects);
                if ($value !== null) {
                    $property['value'] = $value;
                }

                $formattedValue = $this->reviewValueFromRaw($this->dictionaryRawValue($propertyDictionary['body'], 'F'), $objects);
                if ($formattedValue !== null && $formattedValue !== '') {
                    $property['formatted_value'] = $formattedValue;
                }

                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function dictionariesFromValue(?string $value, array $objects): array
    {
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return [];
        }

        if (str_starts_with($resolved, '[')) {
            $dictionaries = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $dictionary = $this->resolveDictionaryFromValue($item, $objects);
                if ($dictionary !== null) {
                    $dictionaries[] = $dictionary;
                }
            }

            return $dictionaries;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        return $dictionary === null ? [] : [$dictionary];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageObjectFromStructureKid(?string $value, array $objects, int $depth = 0): ?int
    {
        if ($value === null || $depth > 5) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '[')) {
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $pageObject = $this->pageObjectFromStructureKid($item, $objects, $depth + 1);
                if ($pageObject !== null) {
                    return $pageObject;
                }
            }
            return null;
        }

        $dictionary = $this->resolveDictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            return null;
        }

        return $this->objectReferenceValueAfterName($dictionary['body'], 'Pg')
            ?? $this->pageObjectFromStructureKid($this->dictionaryRawValue($dictionary['body'], 'K'), $objects, $depth + 1);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function pieceInfoMetadata(?string $pieceInfoValue, array $objects): array
    {
        $pieceInfo = $this->resolveDictionaryFromValue($pieceInfoValue, $objects);
        if ($pieceInfo === null) {
            return [];
        }

        $metadata = [];
        foreach ($this->dictionaryEntries($pieceInfo['body']) as $application => $pieceValue) {
            $piece = $this->resolveDictionaryFromValue($pieceValue, $objects);
            if ($piece === null) {
                continue;
            }

            $entry = [];
            $lastModified = $this->dictionaryStringValue($piece['body'], 'LastModified', $objects);
            if ($lastModified !== null && $lastModified !== '') {
                $entry['last_modified'] = $lastModified;
            }

            $private = $this->resolveDictionaryFromValue($this->dictionaryRawValue($piece['body'], 'Private'), $objects);
            if ($private !== null) {
                $privateMetadata = [];
                foreach ($this->dictionaryEntries($private['body']) as $name => $privateValue) {
                    $reviewValue = $this->reviewValueFromRaw($privateValue, $objects);
                    if ($reviewValue !== null && $reviewValue !== '') {
                        $privateMetadata[$name] = $reviewValue;
                    }
                }

                if ($privateMetadata !== []) {
                    $entry['private'] = $privateMetadata;
                }
            }

            if ($entry !== []) {
                $metadata[$application] = $entry;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function pageAssociatedFilesMetadata(?string $arrayValue, array $objects): array
    {
        if ($arrayValue === null) {
            return [];
        }

        $files = [];
        foreach ($this->arrayItemsFromValue($arrayValue, $objects) as $index => $fileSpecValue) {
            $file = $this->fileSpecReviewMetadata($fileSpecValue, null, $objects);
            if ($file === null) {
                continue;
            }

            $file = [
                'source' => 'page_associated_files',
                'associated_file' => true,
                'associated_file_index' => $index,
            ] + $file;
            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fileSpecReviewMetadata(string $value, ?string $name, array $objects): ?array
    {
        $fileSpec = $this->resolveDictionaryFromValue($value, $objects);
        if ($fileSpec === null) {
            return null;
        }

        $body = $fileSpec['body'];
        $unicodeFilename = $this->dictionaryStringValue($body, 'UF', $objects);
        $filename = $unicodeFilename
            ?? $this->firstDictionaryString($body, ['F', 'DOS', 'Unix', 'Mac'], $objects)
            ?? $name
            ?? 'associated-file';
        $attachmentName = ($name !== null && $name !== '') ? $name : $filename;

        $file = [
            'name' => $attachmentName,
            'filename' => $filename,
            'file_spec_object' => $fileSpec['object'],
        ];

        if ($unicodeFilename !== null && $unicodeFilename !== '') {
            $file['unicode_filename'] = $unicodeFilename;
        }

        foreach ([
            'description' => $this->dictionaryStringValue($body, 'Desc', $objects),
            'relationship' => $this->dictionaryNameValue($body, 'AFRelationship', $objects),
        ] as $key => $metadataValue) {
            if (is_string($metadataValue) && $metadataValue !== '') {
                $file[$key] = $metadataValue;
            }
        }

        $ef = $this->resolveDictionaryFromValue($this->dictionaryRawValue($body, 'EF'), $objects);
        if ($ef === null) {
            return $file;
        }

        foreach ($this->embeddedFileKeys($unicodeFilename !== null) as $efKey) {
            $streamValue = $this->dictionaryRawValue($ef['body'], $efKey);
            if ($streamValue === null) {
                continue;
            }

            $stream = $this->embeddedFileStreamMetadata($streamValue, $objects);
            if ($stream === null) {
                continue;
            }

            $file['ef_key'] = $efKey;
            $file['embedded_file_object'] = $stream['object'];
            $file['size'] = strlen($stream['content']);
            $file['content_sha256'] = hash('sha256', $stream['content']);

            $mimeType = $this->dictionaryNameValue($stream['dictionary'], 'Subtype', $objects);
            if ($mimeType !== null && $mimeType !== '') {
                $file['mime_type'] = $mimeType;
            }

            if ($stream['filters'] !== []) {
                $file['filters'] = $stream['filters'];
            }

            foreach ($this->embeddedFileParams($stream['dictionary'], $objects, $stream['content']) as $key => $metadataValue) {
                $file[$key] = $metadataValue;
            }

            break;
        }

        return $file;
    }

    /**
     * Prefer the Unicode file stream when the FileSpec advertises /UF.
     *
     * @return list<string>
     */
    private function embeddedFileKeys(bool $hasUnicodeFilename): array
    {
        return $hasUnicodeFilename
            ? ['UF', 'F', 'DOS', 'Unix', 'Mac']
            : ['F', 'UF', 'DOS', 'Unix', 'Mac'];
    }

    /**
     * @param array<int, string> $objects
     * @return array{object: int|null, dictionary: string, content: string, filters: list<string>}|null
     */
    private function embeddedFileStreamMetadata(string $value, array $objects): ?array
    {
        $objectNumber = $this->objectNumberFromReference($value);
        $body = $objectNumber !== null ? ($objects[$objectNumber] ?? null) : trim($value);
        if ($body === null || $body === '') {
            return null;
        }

        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        return [
            'object' => $objectNumber,
            'dictionary' => $stream['dictionary'],
            'content' => $stream['content'],
            'filters' => $stream['filters'],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{dictionary: string, content: string, filters: list<string>}|null
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?array
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        $dictionary = $match[1];
        $stream = $match[2];
        $filters = $this->streamFilters($dictionary, $objects);
        foreach ($filters as $filter) {
            if (!$this->streamFilterInputHasBoundedEndMarker($filter, $stream)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                default => $stream,
            };
            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return [
            'dictionary' => $dictionary,
            'content' => $stream,
            'filters' => $filters,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dictionary, array $objects): array
    {
        $value = $this->dictionaryRawValue($dictionary, 'Filter');
        if ($value === null) {
            return [];
        }

        $resolved = $this->resolveRawValue($value, $objects) ?? $value;
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)/', $resolved, $matches);

        return array_map(fn (string $name): string => $this->decodePdfName($name), $matches[1] ?? []);
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/[\x00\x09\x0a\x0c\x0d\x20]+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeFlateStream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        return $inflated === false ? null : $inflated;
    }

    private function streamFilterInputHasBoundedEndMarker(string $filter, string $stream): bool
    {
        $offset = match ($filter) {
            'ASCIIHexDecode', 'AHx' => (($end = strpos($stream, '>')) !== false) ? $end + 1 : null,
            'FlateDecode', 'Fl' => $this->flateExplicitEndByteOffset($stream),
            default => null,
        };
        if ($offset === null) {
            return in_array($filter, ['ASCIIHexDecode', 'AHx'], true)
                ? $this->asciiHexStreamHasOnlyLengthBoundedData($stream)
                : !in_array($filter, ['FlateDecode', 'Fl'], true);
        }

        return $this->streamHasOnlyWhitespaceAfterOffset(
            $stream,
            $offset,
            !in_array($filter, ['FlateDecode', 'Fl'], true)
        );
    }

    private function asciiHexStreamHasOnlyLengthBoundedData(string $stream): bool
    {
        for ($index = 0, $length = strlen($stream); $index < $length; $index++) {
            $char = $stream[$index];
            if ($this->isPdfWhitespace($char)) {
                continue;
            }

            if (!ctype_xdigit($char)) {
                return false;
            }
        }

        return true;
    }

    private function streamHasOnlyWhitespaceAfterOffset(string $stream, int $offset, bool $allowComments = true): bool
    {
        for ($index = $offset, $length = strlen($stream); $index < $length;) {
            if ($this->isPdfWhitespace($stream[$index])) {
                $index++;
                continue;
            }

            if ($allowComments && $stream[$index] === '%') {
                $lineLength = strcspn($stream, "\r\n", $index);
                if ($index + $lineLength >= $length) {
                    return true;
                }
                $index += $lineLength;
                continue;
            }

            return false;
        }

        return true;
    }

    private function flateExplicitEndByteOffset(string $stream): ?int
    {
        if (
            !function_exists('inflate_init')
            || !function_exists('inflate_add')
            || !function_exists('inflate_get_status')
            || !function_exists('inflate_get_read_len')
        ) {
            return null;
        }

        $encodings = [];
        foreach (['ZLIB_ENCODING_DEFLATE', 'ZLIB_ENCODING_RAW', 'ZLIB_ENCODING_GZIP'] as $constant) {
            if (defined($constant)) {
                $encodings[] = constant($constant);
            }
        }

        $finish = defined('ZLIB_FINISH') ? constant('ZLIB_FINISH') : 4;
        $streamEnd = defined('ZLIB_STREAM_END') ? constant('ZLIB_STREAM_END') : 1;
        foreach (array_unique($encodings) as $encoding) {
            $context = @inflate_init($encoding);
            if ($context === false) {
                continue;
            }

            $decoded = @inflate_add($context, $stream, $finish);
            if ($decoded === false || @inflate_get_status($context) !== $streamEnd) {
                continue;
            }

            $readLength = @inflate_get_read_len($context);
            if (is_int($readLength) && $readLength > 0) {
                return $readLength;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileParams(string $streamDictionary, array $objects, string $content): array
    {
        $params = $this->resolveDictionaryFromValue($this->dictionaryRawValue($streamDictionary, 'Params'), $objects);
        if ($params === null) {
            return [];
        }

        $metadata = [];
        $size = $this->dictionaryIntegerValue($params['body'], 'Size');
        if ($size !== null) {
            $metadata['declared_size'] = $size;
        }

        $checksum = $this->dictionaryChecksumValue($params['body'], 'CheckSum', $objects);
        if ($checksum !== null && $checksum !== '') {
            $metadata['checksum'] = $checksum;
            $metadata['checksum_algorithm'] = 'md5';
            $metadata['computed_checksum'] = hash('md5', $content);
            $metadata['checksum_matches'] = hash_equals($metadata['computed_checksum'], $checksum);
        }

        $createdAt = $this->dictionaryStringValue($params['body'], 'CreationDate', $objects);
        if ($createdAt !== null && $createdAt !== '') {
            $metadata['created_at'] = $createdAt;
        }

        $modifiedAt = $this->dictionaryStringValue($params['body'], 'ModDate', $objects);
        if ($modifiedAt !== null && $modifiedAt !== '') {
            $metadata['modified_at'] = $modifiedAt;
        }

        return $metadata;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $this->currentObjectGenerations = [];

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }

        $xrefEntries = $this->xrefEntriesFromLatestStartxref($pdfBytes, $definitions);
        $objects = [];
        foreach ($definitions as $objectNumber => $candidates) {
            if ($xrefEntries !== [] && !array_key_exists($objectNumber, $xrefEntries)) {
                continue;
            }

            $definition = $this->selectedDirectObjectDefinition($candidates, $xrefEntries[$objectNumber] ?? null);
            if ($definition === null) {
                continue;
            }

            $objects[$objectNumber] = $definition['body'];
            $this->currentObjectGenerations[$objectNumber] = $definition['generation'];
        }

        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}> $xrefEntries
     * @return array<int, string>
     */
    private function withObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        if ($xrefEntries === []) {
            return $objects;
        }

        $expanded = $objects;
        $remaining = [];
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'], $entry['index'])) {
                continue;
            }

            $remaining[$objectNumber] = $entry;
        }

        for ($pass = 0; $pass < 4 && $remaining !== []; $pass++) {
            $resolvedThisPass = 0;
            foreach ($expanded as $objectStreamNumber => $body) {
                if (!$this->objectBodyHasTypeName($body, 'ObjStm')) {
                    continue;
                }

                $memberTable = $this->decodedObjectStreamMemberTable($body, $expanded);
                if ($memberTable === null) {
                    continue;
                }

                $memberObjectNumberCounts = $this->objectStreamMemberObjectNumberCounts($memberTable['members']);
                $memberOffsetCounts = $this->objectStreamMemberOffsetCounts($memberTable['members']);
                foreach ($remaining as $objectNumber => $entry) {
                    if (($entry['objectStream'] ?? null) !== $objectStreamNumber) {
                        continue;
                    }

                    if (($memberObjectNumberCounts[$objectNumber] ?? 0) !== 1) {
                        continue;
                    }

                    $member = $this->objectStreamMemberAtHeaderIndex($memberTable['members'], (int) $entry['index']);
                    if ($member === null || $member['objectNumber'] !== $objectNumber) {
                        continue;
                    }

                    if (($memberOffsetCounts[$member['offset']] ?? 0) > 1) {
                        continue;
                    }

                    $memberBody = $this->objectStreamMemberBody($memberTable, $member);
                    if ($memberBody === null || $memberBody === '' || $this->objectBodyIsStreamObject($memberBody)) {
                        continue;
                    }

                    $expanded[$objectNumber] = $memberBody;
                    $this->currentObjectGenerations[$objectNumber] = 0;
                    unset($remaining[$objectNumber]);
                    $resolvedThisPass++;
                }
            }

            if ($resolvedThisPass === 0) {
                break;
            }
        }

        return $expanded;
    }

    /**
     * @param array<int, string> $objects
     * @return array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>}|null
     */
    private function decodedObjectStreamMemberTable(string $body, array $objects): ?array
    {
        $stream = $this->decodeStreamObject($body, $objects);
        if ($stream === null) {
            return null;
        }

        $count = $this->dictionaryIntegerValue($stream['dictionary'], 'N');
        $first = $this->dictionaryIntegerValue($stream['dictionary'], 'First');
        if ($count === null || $first === null || $count <= 0 || $first < 0 || $first >= strlen($stream['content'])) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($stream['content'], 0, $first), $count);
        if ($members === []) {
            return null;
        }

        return [
            'decoded' => $stream['content'],
            'first' => $first,
            'members' => $members,
        ];
    }

    /**
     * @return list<array{objectNumber: int, offset: int, index: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $count): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $count; $index++) {
            $objectNumber = $this->readPdfUnsignedIntegerToken($header, $offset);
            if ($objectNumber === null) {
                return [];
            }

            $objectOffset = $this->readPdfUnsignedIntegerToken($header, $offset);
            if ($objectOffset === null) {
                return [];
            }

            if ($objectNumber === 0) {
                continue;
            }

            $members[] = [
                'objectNumber' => $objectNumber,
                'offset' => $objectOffset,
                'index' => $index,
            ];
        }

        return $this->skipWhitespace($header, $offset) === strlen($header) ? $members : [];
    }

    private function readPdfUnsignedIntegerToken(string $value, int &$offset): ?int
    {
        $offset = $this->skipWhitespace($value, $offset);
        if (preg_match('/\G\+?(\d+)(?=$|[\s\[\]()<>{}\/%])/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return (int) $match[1];
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @return array<int, int>
     */
    private function objectStreamMemberObjectNumberCounts(array $members): array
    {
        $counts = [];
        foreach ($members as $member) {
            $objectNumber = $member['objectNumber'];
            $counts[$objectNumber] = ($counts[$objectNumber] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @return array<int, int>
     */
    private function objectStreamMemberOffsetCounts(array $members): array
    {
        $counts = [];
        foreach ($members as $member) {
            $offset = $member['offset'];
            $counts[$offset] = ($counts[$offset] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @return array{objectNumber: int, offset: int, index: int}|null
     */
    private function objectStreamMemberAtHeaderIndex(array $members, int $headerIndex): ?array
    {
        foreach ($members as $member) {
            if ($member['index'] === $headerIndex) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberBody(array $memberTable, array $member): ?string
    {
        if (!$this->objectStreamMemberOffsetHasTokenBoundary($memberTable, $member)) {
            return null;
        }

        $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
        $endOffset = $this->objectStreamMemberEndOffset($memberTable, $member, $objectDataLength);
        if ($endOffset === null) {
            return null;
        }

        return trim(substr(
            $memberTable['decoded'],
            $memberTable['first'] + $member['offset'],
            $endOffset - $member['offset']
        ));
    }

    /**
     * Object-stream xref rows point to header indexes, but body slicing is
     * offset-owned. Ignore later malformed offsets so they cannot truncate an
     * earlier valid page or metadata object.
     *
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberEndOffset(array $memberTable, array $member, int $objectDataLength): ?int
    {
        if ($member['offset'] < 0 || $member['offset'] >= $objectDataLength) {
            return null;
        }

        $endOffset = $objectDataLength;
        foreach ($memberTable['members'] as $candidate) {
            $candidateOffset = $candidate['offset'];
            if ($candidateOffset > $member['offset'] && $candidateOffset < $endOffset) {
                if (!$this->objectStreamMemberOffsetHasTokenBoundary($memberTable, $candidate)) {
                    continue;
                }

                $endOffset = $candidateOffset;
            }
        }

        return $endOffset > $member['offset'] ? $endOffset : null;
    }

    /**
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberOffsetHasTokenBoundary(array $memberTable, array $member): bool
    {
        $decoded = $memberTable['decoded'];
        $absoluteOffset = $memberTable['first'] + $member['offset'];
        if (
            $member['offset'] < 0
            || $absoluteOffset < $memberTable['first']
            || $absoluteOffset >= strlen($decoded)
        ) {
            return false;
        }

        if ($this->isPdfWhitespace($decoded[$absoluteOffset]) || $decoded[$absoluteOffset] === '%') {
            return false;
        }

        if ($absoluteOffset === $memberTable['first']) {
            return true;
        }

        $index = $memberTable['first'];
        $length = strlen($decoded);
        while ($index < $absoluteOffset && $index < $length) {
            $char = $decoded[$index];
            if ($char === '<' && ($decoded[$index + 1] ?? '') === '<') {
                $dictionary = $this->readPdfDictionaryAt($decoded, $index);
                if ($dictionary === null || $dictionary['end'] <= $index) {
                    return false;
                }

                if ($absoluteOffset < $dictionary['end']) {
                    return false;
                }

                $index = $dictionary['end'];
                continue;
            }

            if ($char === '[') {
                $array = $this->readPdfArrayAt($decoded, $index);
                if ($array === null || $array['end'] <= $index) {
                    return false;
                }

                if ($absoluteOffset < $array['end']) {
                    return false;
                }

                $index = $array['end'];
                continue;
            }

            if ($char === '(') {
                $literal = $this->readLiteralStringAt($decoded, $index);
                if ($literal === null || $literal['end'] <= $index) {
                    return false;
                }

                if ($absoluteOffset < $literal['end']) {
                    return false;
                }

                $index = $literal['end'];
                continue;
            }

            if ($char === '<') {
                $end = $this->skipHexString($decoded, $index);
                if ($end === null || $end <= $index) {
                    return false;
                }

                if ($absoluteOffset < $end) {
                    return false;
                }

                $index = $end;
                continue;
            }

            if ($char === '%') {
                $end = $this->skipPdfCommentOffset($decoded, $index);
                if ($end <= $index) {
                    return false;
                }

                if ($absoluteOffset < $end) {
                    return false;
                }

                $index = $end;
                continue;
            }

            $index++;
        }

        if ($index !== $absoluteOffset) {
            return false;
        }

        return $this->isPdfDelimiter($decoded[$absoluteOffset - 1]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyHasTypeName(string $objectBody, string $typeName, array $objects = []): bool
    {
        $dictionaryOffset = strpos($objectBody, '<<');
        if ($dictionaryOffset === false) {
            return false;
        }

        $dictionary = $this->readPdfDictionaryAt($objectBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        return $this->dictionaryNameValue($dictionary['body'], 'Type', $objects) === $typeName;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $body = $match[3][0];
            $bodyStart = $match[3][1];
            $definitions[(int) $match[1][0]][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[0][1],
                'bodyStart' => $bodyStart,
                'bodyEnd' => $bodyStart + strlen($body),
                'body' => $body,
            ];
        }

        ksort($definitions, SORT_NUMERIC);

        return $definitions;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @param array{type: int, generation?: int, offset?: int}|null $xrefEntry
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function selectedDirectObjectDefinition(array $definitions, ?array $xrefEntry): ?array
    {
        if ($xrefEntry === null) {
            return $this->lastScannedDirectObjectDefinition($definitions);
        }

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $generation = $xrefEntry['generation'] ?? null;
        $offset = $xrefEntry['offset'] ?? null;
        if (is_int($offset)) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                if ($generation !== null && $definition['generation'] !== $generation) {
                    continue;
                }

                return $definition;
            }

            return null;
        }

        $candidates = [];
        foreach ($definitions as $definition) {
            if ($generation !== null && $definition['generation'] !== $generation) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function lastScannedDirectObjectDefinition(array $definitions): ?array
    {
        if ($definitions === []) {
            return null;
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']
        );

        $selected = end($definitions);

        return is_array($selected) ? $selected : null;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectDefinition(array $definitions): ?array
    {
        if ($definitions === []) {
            return null;
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => [$left['generation'], $left['offset']] <=> [$right['generation'], $right['offset']]
        );

        $selected = end($definitions);

        return is_array($selected) ? $selected : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     */
    private function xrefEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        $entries = $this->xrefEntriesAtOffset($pdfBytes, $offset, $definitions);
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\s+(\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches) - 1; $index >= 0; $index--) {
            $match = $matches[$index];
            $tokenOffset = $match[0][1] ?? null;
            if (!is_int($tokenOffset) || !$this->pdfKeywordAt($pdfBytes, $tokenOffset, 'startxref')) {
                continue;
            }

            return max(0, (int) ($match[1][0] ?? 0));
        }

        return null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, true> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     */
    private function xrefEntriesAtOffset(string $pdfBytes, int $offset, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $table = $this->xrefTableSectionAt($pdfBytes, $offset);
        if ($table !== null) {
            $entries = $table['entries'];
            $previousOffset = $this->previousXrefOffsetFromSectionDictionary($table['trailer'], $definitions, $offset);
            $entries = $this->repairCurrentUpdateXrefRows($entries, $definitions, $previousOffset, $offset);
            if ($previousOffset !== null) {
                foreach ($this->xrefEntriesAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            return $entries;
        }

        $stream = $this->xrefStreamSectionAt($offset, $definitions);
        if ($stream === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromSection($stream);
        $previousOffset = $this->previousXrefOffsetFromSectionDictionary($stream['dictionary'], $definitions, $offset);
        $entries = $this->repairCurrentUpdateXrefRows($entries, $definitions, $previousOffset, $offset);
        if ($previousOffset !== null) {
            foreach ($this->xrefEntriesAtOffset($pdfBytes, $previousOffset, $definitions, $seenOffsets) as $objectNumber => $entry) {
                $entries[$objectNumber] ??= $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function previousXrefOffsetFromSectionDictionary(
        string $dictionary,
        array $definitions,
        int $sectionOffset
    ): ?int {
        $direct = $this->dictionaryIntegerValue($dictionary, 'Prev');
        if ($direct !== null) {
            return $direct;
        }

        $value = $this->dictionaryRawValue($dictionary, 'Prev');
        if ($value === null) {
            return null;
        }

        for ($depth = 0; $depth < 8; $depth++) {
            $trimmed = trim($value);
            if (preg_match('/^[+-]?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            $reference = $this->objectReferenceFromValue($trimmed);
            if ($reference === null) {
                return null;
            }

            $definition = $this->latestDirectObjectDefinitionForReferenceBeforeOffset(
                $definitions,
                $reference['objectNumber'],
                $reference['generation'],
                $sectionOffset
            );
            $compressed = $this->compressedXrefPrevHelperBodyForReferenceBeforeOffset(
                $definitions,
                $reference['objectNumber'],
                $reference['generation'],
                $sectionOffset
            );
            $directBody = $definition === null ? null : trim($definition['body']);
            $compressedBody = $compressed === null ? null : trim($compressed['body']);

            if (
                $compressedBody !== null
                && $this->xrefPrevHelperBodyIsSafe($compressedBody)
                && ($definition === null || $compressed['carrierOffset'] > $definition['offset'])
            ) {
                $value = $compressedBody;
                continue;
            }

            if ($directBody !== null && $this->xrefPrevHelperBodyIsSafe($directBody)) {
                $value = $directBody;
                continue;
            }

            if ($compressedBody !== null && $this->xrefPrevHelperBodyIsSafe($compressedBody)) {
                $value = $compressedBody;
                continue;
            }

            if ($definition === null) {
                return null;
            }

            $value = $definition['body'];
        }

        return null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{body: string, carrierOffset: int}|null
     */
    private function compressedXrefPrevHelperBodyForReferenceBeforeOffset(
        array $definitions,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?array {
        if ($objectNumber <= 0 || $generation !== 0) {
            return null;
        }

        $selected = null;
        foreach ($definitions as $carrierDefinitions) {
            foreach ($carrierDefinitions as $definition) {
                if ($definition['offset'] >= $beforeOffset || !$this->objectBodyHasTypeName($definition['body'], 'ObjStm')) {
                    continue;
                }

                $memberTable = $this->decodedObjectStreamMemberTable($definition['body'], []);
                if ($memberTable === null) {
                    continue;
                }

                foreach ($memberTable['members'] as $member) {
                    if ($member['objectNumber'] !== $objectNumber) {
                        continue;
                    }

                    $body = $this->objectStreamMemberBody($memberTable, $member);
                    if ($body === null || !$this->xrefPrevHelperBodyIsSafe($body)) {
                        continue;
                    }

                    if ($selected === null || $definition['offset'] > $selected['carrierOffset']) {
                        $selected = [
                            'body' => $body,
                            'carrierOffset' => $definition['offset'],
                        ];
                    }
                }
            }
        }

        return $selected;
    }

    private function xrefPrevHelperBodyIsSafe(string $body): bool
    {
        return preg_match('/^\s*[+-]?\d+\s*\z/s', $body) === 1
            && preg_match('/\b(?:obj|endobj|stream|endstream|xref|trailer|startxref)\b/s', $body) !== 1;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectDefinitionForReferenceBeforeOffset(
        array $definitions,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?array {
        $candidates = [];
        foreach ($definitions[$objectNumber] ?? [] as $definition) {
            if (
                $definition['generation'] !== $generation
                || $definition['offset'] >= $beforeOffset
            ) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->lastScannedDirectObjectDefinition($candidates);
    }

    /**
     * Some producer incremental updates append current same-generation objects
     * but leave zero or stale offsets in the latest xref rows. Repair only rows
     * that the latest section explicitly owns, before inheriting /Prev rows.
     *
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     */
    private function repairCurrentUpdateXrefRows(
        array $entries,
        array $definitions,
        ?int $previousOffset,
        int $xrefOffset
    ): array {
        if ($previousOffset === null || $previousOffset < 0) {
            return $entries;
        }

        foreach ($entries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            $offset = $entry['offset'] ?? null;
            $offsetOwner = is_int($offset) ? $this->directObjectDefinitionAtOffset($definitions, $offset) : null;
            $updateOwner = $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                (int) $objectNumber,
                (int) ($entry['generation'] ?? 0),
                $offsetOwner,
                $previousOffset,
                $xrefOffset,
                $definitions
            );

            if ($offsetOwner !== null && $updateOwner === null) {
                continue;
            }

            if ($updateOwner === null) {
                continue;
            }

            $entries[$objectNumber]['offset'] = $updateOwner['offset'];
            $entries[$objectNumber]['generation'] = $updateOwner['generation'];
        }

        return $entries;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                return [
                    'objectNumber' => (int) $objectNumber,
                    'generation' => $definition['generation'],
                    'offset' => $definition['offset'],
                    'body' => $definition['body'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{objectNumber: int, generation: int, offset: int, body: string}|null $offsetOwner
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForStaleXrefOffset(
        int $objectNumber,
        int $generation,
        ?array $offsetOwner,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if (
            $offsetOwner !== null
            && $offsetOwner['offset'] > $previousOffset
            && $offsetOwner['offset'] < $xrefOffset
        ) {
            if (
                $offsetOwner['objectNumber'] === $objectNumber
                && $offsetOwner['generation'] === $generation
            ) {
                return null;
            }

            return $this->currentUpdateDirectObjectDefinitionForXrefRow(
                $objectNumber,
                $generation,
                $previousOffset,
                $xrefOffset,
                $definitions
            );
        }

        return $this->currentUpdateDirectObjectDefinitionForXrefRow(
            $objectNumber,
            $generation,
            $previousOffset,
            $xrefOffset,
            $definitions
        );
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForXrefRow(
        int $objectNumber,
        int $generation,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if ($objectNumber <= 0 || $previousOffset < 0 || $xrefOffset <= $previousOffset) {
            return null;
        }

        $candidates = [];
        foreach ($definitions[$objectNumber] ?? [] as $definition) {
            if (
                $definition['generation'] !== $generation
                || $definition['offset'] <= $previousOffset
                || $definition['offset'] >= $xrefOffset
            ) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @return array{entries: array<int, array{type: int, generation: int, offset: int}>, trailer: string}|null
     */
    private function xrefTableSectionAt(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipWhitespace($pdfBytes, $offset);
        if (!$this->pdfKeywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }

        $sectionBodyOffset = $offset + strlen('xref');
        if ($sectionBodyOffset >= strlen($pdfBytes) || !ctype_space($pdfBytes[$sectionBodyOffset])) {
            return null;
        }

        $trailerOffset = strpos($pdfBytes, 'trailer', $sectionBodyOffset);
        if ($trailerOffset === false || !$this->pdfKeywordAt($pdfBytes, $trailerOffset, 'trailer')) {
            return null;
        }

        $dictionaryOffset = $this->skipWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        $trailer = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($trailer === null) {
            return null;
        }

        $entries = $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer['body'],
        ];
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>|null
     */
    private function xrefTableRows(string $sectionBody): ?array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', $sectionBody);
        if (!is_array($lines)) {
            return null;
        }

        $foundSection = false;
        $lineCount = count($lines);
        for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^(\+?\d+)\s+(\+?\d+)(?:\s*(?:%.*)?)$/', $line, $section) !== 1) {
                continue;
            }

            $foundSection = true;
            $firstObject = (int) $section[1];
            $rowCount = (int) $section[2];
            if ($rowCount <= 0) {
                return $entries === [] ? null : $entries;
            }

            $rowIndex = 0;
            while ($rowIndex < $rowCount && ++$lineIndex < $lineCount) {
                $row = trim($lines[$lineIndex]);
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $row, $match) !== 1) {
                    return null;
                }

                $entries[$firstObject + $rowIndex] = [
                    'type' => $match[3] === 'n' ? 1 : 0,
                    'generation' => (int) $match[2],
                    'offset' => (int) $match[1],
                ];
                $rowIndex++;
            }

            if ($rowIndex < $rowCount) {
                return $entries === [] ? null : $entries;
            }
        }

        return $foundSection ? $entries : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{dictionary: string, stream: string, offset: int}|null
     */
    private function xrefStreamSectionAt(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] !== $offset) {
                    continue;
                }

                $stream = $this->decodeStreamObject($definition['body'], []);
                if ($stream === null || $this->dictionaryNameValue($stream['dictionary'], 'Type', []) !== 'XRef') {
                    return null;
                }

                return [
                    'dictionary' => $stream['dictionary'],
                    'stream' => $stream['content'],
                    'offset' => $definition['offset'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{dictionary: string, stream: string, offset?: int} $section
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $widths = $this->integerArrayFromRawValue($this->dictionaryRawValue($section['dictionary'], 'W'));
        if (count($widths) < 3) {
            return [];
        }

        $widths = array_slice($widths, 0, 3);
        if ($widths[0] < 0 || $widths[1] < 0 || $widths[2] < 0) {
            return [];
        }

        $entryWidth = $widths[0] + $widths[1] + $widths[2];
        if ($entryWidth <= 0) {
            return [];
        }

        $decoded = $section['stream'];
        $decodedEntryCount = intdiv(strlen($decoded), $entryWidth);
        $entries = [];
        $fieldOffset = 0;
        foreach ($this->xrefStreamIndexRanges($section['dictionary'], $decodedEntryCount) as $range) {
            for ($row = 0; $row < $range['count'] && $fieldOffset + $entryWidth <= strlen($decoded); $row++) {
                $objectNumber = $range['first'] + $row;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                    ];
                    continue;
                }

                if ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
                        'index' => $fieldThree,
                    ];
                    continue;
                }

                $entries[$objectNumber] = ['type' => $type];
            }
        }

        return $entries;
    }

    /**
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(string $dictionary, int $decodedEntryCount): array
    {
        $index = $this->integerArrayFromRawValue($this->dictionaryRawValue($dictionary, 'Index'));
        if ($index === []) {
            $size = $this->dictionaryIntegerValue($dictionary, 'Size');

            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $ranges = [];
        $consumed = 0;
        for ($offset = 0, $count = count($index); $offset + 1 < $count; $offset += 2) {
            if ($index[$offset] < 0 || $index[$offset + 1] < 0) {
                continue;
            }

            $rowCount = min($index[$offset + 1], max(0, $decodedEntryCount - $consumed));
            $ranges[] = [
                'first' => $index[$offset],
                'count' => $rowCount,
            ];
            $consumed += $rowCount;
        }

        return $ranges;
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

    /**
     * @return list<int>
     */
    private function integerArrayFromRawValue(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] !== '[') {
            return [];
        }

        $array = $this->readPdfArrayAt($trimmed, 0);
        if ($array === null) {
            return [];
        }

        if (preg_match_all('/-?\d+/', substr($array['raw'], 1, -1), $matches) < 1) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $trailer = $this->trailerDictionaryBody($pdfBytes);
        if ($trailer !== null) {
            $rootValue = $this->dictionaryRawValue($trailer, 'Root');
            $rootReference = $rootValue === null ? null : $this->objectReferenceFromValue($rootValue);
            if ($rootReference !== null) {
                $rootObjectNumber = $rootReference['objectNumber'];
                if (
                    isset($objects[$rootObjectNumber])
                    && ($this->currentObjectGenerations[$rootObjectNumber] ?? null) === $rootReference['generation']
                    && $this->pdfObjectTypeName($objects[$rootObjectNumber], $objects) === 'Catalog'
                ) {
                    return $this->dictionaryObjectBody($objects[$rootObjectNumber]);
                }

                return null;
            }

            if ($rootValue !== null) {
                return null;
            }
        }

        foreach ($objects as $body) {
            if ($this->pdfObjectTypeName($body, $objects) === 'Catalog') {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    private function trailerDictionaryBody(string $pdfBytes): ?string
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $latestStartxrefOffset = $this->latestStartxrefOffset($pdfBytes);
        if ($latestStartxrefOffset !== null && $definitions !== []) {
            $xrefTable = $this->xrefTableSectionAt($pdfBytes, $latestStartxrefOffset);
            if ($xrefTable !== null) {
                return $xrefTable['trailer'];
            }

            $xrefStream = $this->xrefStreamSectionAt($latestStartxrefOffset, $definitions);
            if ($xrefStream !== null) {
                return $xrefStream['dictionary'];
            }
        }

        $body = null;
        $offset = 0;
        while (($position = strpos($pdfBytes, 'trailer', $offset)) !== false) {
            $dictionaryOffset = strpos($pdfBytes, '<<', $position);
            if ($dictionaryOffset === false) {
                break;
            }

            $candidate = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
            if ($candidate !== null) {
                $body = $candidate['body'];
            }
            $offset = $position + 7;
        }

        return $body;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function orderedPageObjectNumbers(string $catalog, array $objects): array
    {
        $pagesValue = $this->dictionaryRawValue($catalog, 'Pages');
        $pagesReference = $pagesValue === null ? null : $this->objectReferenceFromValue($pagesValue);
        if ($pagesReference !== null) {
            return $this->pageObjectNumbersFromTree(
                $pagesReference['objectNumber'],
                $pagesReference['generation'],
                $objects
            );
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->pdfObjectTypeName($body, $objects) === 'Page') {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, int $generation, array $objects, array $seen = []): array
    {
        $resolved = $this->resolvedPageTreeReference($objects, $objectNumber, $generation, $seen);
        if ($resolved === null) {
            return [];
        }

        $objectNumber = $resolved['objectNumber'];
        $generation = $resolved['generation'];
        $body = $resolved['body'];
        $referenceKey = $objectNumber . ':' . $generation;
        $seen[$referenceKey] = true;
        if ($this->pdfObjectTypeName($body, $objects) === 'Page') {
            return [$objectNumber];
        }

        $dictionary = $this->dictionaryObjectBody($body);
        if ($dictionary === null) {
            return [];
        }

        $kids = $this->topLevelDictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($this->arrayItemsFromValue($kids, $objects) as $kidValue) {
            $childReference = $this->objectReferenceFromValue($kidValue);
            if (
                $childReference === null
                || $this->objectBodyForPageTreeReference(
                    $objects,
                    $childReference['objectNumber'],
                    $childReference['generation']
                ) === null
            ) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree(
                $childReference['objectNumber'],
                $childReference['generation'],
                $objects,
                $seen
            ) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForPageTreeReference(array $objects, int $objectNumber, int $generation): ?string
    {
        $resolved = $this->resolvedPageTreeReference($objects, $objectNumber, $generation);

        return $resolved['body'] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return array{objectNumber: int, generation: int, body: string}|null
     */
    private function resolvedPageTreeReference(array $objects, int $objectNumber, int $generation, array $seen = []): ?array
    {
        $referenceKey = $objectNumber . ':' . $generation;
        if (
            $objectNumber <= 0
            || $generation < 0
            || isset($seen[$referenceKey])
            || !isset($objects[$objectNumber])
            || ($this->currentObjectGenerations[$objectNumber] ?? null) !== $generation
        ) {
            return null;
        }

        $body = $objects[$objectNumber];
        $seen[$referenceKey] = true;
        $reference = $this->objectReferenceFromValue(trim($body));
        if ($reference !== null) {
            return $this->resolvedPageTreeReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation'],
                $seen
            );
        }

        return [
            'objectNumber' => $objectNumber,
            'generation' => $generation,
            'body' => $body,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromValue(string $value, array $objects): array
    {
        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return [];
        }

        $array = $this->readPdfArrayAt(trim($resolved), 0);
        if ($array === null) {
            return [];
        }

        return $this->arrayItemsFromRawArray($array['raw']);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function arrayItemsFromStrictValue(string $value, array $objects): array
    {
        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return [];
        }

        $trimmed = trim($resolved);
        $array = $this->readPdfArrayAt($trimmed, 0);
        if ($array === null || $this->skipWhitespace($trimmed, $array['end']) < strlen($trimmed)) {
            return [];
        }

        return $this->arrayItemsFromRawArray($array['raw']);
    }

    /**
     * @return list<string>
     */
    private function arrayItemsFromRawArray(string $arrayRaw): array
    {
        $items = [];
        $body = substr($arrayRaw, 1, -1);
        for ($offset = 0, $length = strlen($body); $offset < $length;) {
            $offset = $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $item = $this->readPdfValueAt($body, $offset);
            if ($item === null) {
                $offset++;
                continue;
            }

            $items[] = $item['raw'];
            $offset = $item['end'];
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayFromValue(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '' || !str_starts_with($resolved, '[')) {
            return null;
        }

        $array = $this->readPdfArrayAt($resolved, 0);

        return $array === null ? null : $array['raw'];
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null, generation: int|null}|null
     */
    private function resolveDictionaryFromValue(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $resolved = $this->resolvedObjectValueFromReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation']
            );
            if ($resolved === null) {
                return null;
            }

            $body = $this->dictionaryObjectBody($resolved['body']);
            return $body === null
                ? null
                : [
                    'body' => $body,
                    'object' => $resolved['object'],
                    'generation' => $resolved['generation'],
                ];
        }

        $resolved = $this->resolveRawValue($value, $objects);
        if ($resolved === null) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt(trim($resolved), 0);
        return $dictionary === null ? null : ['body' => $dictionary['body'], 'object' => null, 'generation' => null];
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolveRawValue(string $value, array $objects): ?string
    {
        $trimmed = trim($value);
        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference === null) {
            return $trimmed;
        }

        $resolved = $this->resolvedObjectValueFromReference(
            $objects,
            $reference['objectNumber'],
            $reference['generation']
        );

        return $resolved['body'] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     * @return array{body: string, object: int, generation: int}|null
     */
    private function resolvedObjectValueFromReference(
        array $objects,
        int $objectNumber,
        int $generation,
        array $seen = []
    ): ?array {
        $referenceKey = $objectNumber . ':' . $generation;
        if (
            isset($seen[$referenceKey])
            || !isset($objects[$objectNumber])
            || ($this->currentObjectGenerations[$objectNumber] ?? null) !== $generation
        ) {
            return null;
        }

        $body = $objects[$objectNumber];
        $seen[$referenceKey] = true;
        $trimmed = trim($body);
        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference !== null) {
            return $this->resolvedObjectValueFromReference(
                $objects,
                $reference['objectNumber'],
                $reference['generation'],
                $seen
            );
        }

        return [
            'body' => $body,
            'object' => $objectNumber,
            'generation' => $generation,
        ];
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        if ($offset === false) {
            return null;
        }

        $dictionary = $this->readPdfDictionaryAt($objectBody, $offset);
        return $dictionary === null ? null : $dictionary['body'];
    }

    private function singleDictionaryObjectBody(string $objectBody): ?string
    {
        $offset = $this->skipWhitespace($objectBody, 0);
        $dictionary = $this->readPdfDictionaryAt($objectBody, $offset);
        if ($dictionary === null) {
            return null;
        }

        return $this->skipWhitespace($objectBody, $dictionary['end']) >= strlen($objectBody)
            ? $dictionary['body']
            : null;
    }

    private function objectBodyIsSingleNullValue(string $objectBody): bool
    {
        $offset = $this->skipWhitespace($objectBody, 0);
        if (!$this->pdfKeywordAt($objectBody, $offset, 'null')) {
            return false;
        }

        $offset = $this->skipWhitespace($objectBody, $offset + strlen('null'));

        return $offset >= strlen($objectBody);
    }

    private function objectBodyIsStreamObject(string $objectBody): bool
    {
        $dictionaryOffset = $this->skipWhitespace($objectBody, 0);
        $dictionary = $this->readPdfDictionaryAt($objectBody, $dictionaryOffset);
        if ($dictionary === null) {
            return false;
        }

        $streamOffset = $this->skipWhitespace($objectBody, $dictionary['end']);
        return $this->pdfKeywordAt($objectBody, $streamOffset, 'stream');
    }

    private function pdfKeywordAt(string $value, int $offset, string $keyword): bool
    {
        $keywordLength = strlen($keyword);
        if (substr($value, $offset, $keywordLength) !== $keyword) {
            return false;
        }

        $afterOffset = $offset + $keywordLength;
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return ctype_space($after) || str_contains('[]()<>{}/%', $after);
    }

    private function dictionaryRawValue(string $dictionary, string $key): ?string
    {
        return $this->dictionaryEntries($dictionary)[$key] ?? null;
    }

    private function topLevelDictionaryRawValue(string $dictionary, string $key): ?string
    {
        $value = null;
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $entryValue = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($entryValue === null) {
                $offset += strlen($match[0]);
                continue;
            }

            if ($this->decodePdfName($match[1]) === $key) {
                $value = $entryValue['raw'];
            }

            $offset = $entryValue['end'];
        }

        return $value;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryStringValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        return $value === null ? null : $this->stringValueFromRaw($value, $objects);
    }

    /**
     * @param list<string> $keys
     * @param array<int, string> $objects
     */
    private function firstDictionaryString(string $dictionary, array $keys, array $objects): ?string
    {
        foreach ($keys as $key) {
            $value = $this->dictionaryStringValue($dictionary, $key, $objects);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryNameValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $resolved, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        return $this->stringValueFromRaw($resolved, $objects);
    }

    private function dictionaryIntegerValue(string $dictionary, string $key): ?int
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null || preg_match('/^-?\d+$/', trim($value)) !== 1) {
            return null;
        }

        return (int) trim($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryChecksumValue(string $dictionary, string $key, array $objects): ?string
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        if ($value === null) {
            return null;
        }

        $bytes = $this->byteStringValueFromRaw($value, $objects);
        if ($bytes === null) {
            return null;
        }

        if (strlen($bytes) === 32 && preg_match('/^[\da-fA-F]{32}$/', $bytes) === 1) {
            return strtolower($bytes);
        }

        return bin2hex($bytes);
    }

    private function objectReferenceValueAfterName(string $dictionary, string $key): ?int
    {
        $value = $this->dictionaryRawValue($dictionary, $key);
        return $value === null ? null : $this->objectNumberFromReference($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfObjectTypeName(string $body, array $objects): ?string
    {
        $trimmed = ltrim($body);
        $dictionary = str_starts_with($trimmed, '<<')
            ? ($this->dictionaryObjectBody($body) ?? $body)
            : $body;
        $value = $this->topLevelDictionaryRawValue($dictionary, 'Type');
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $resolved, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function objectNumberFromReference(string $value): ?int
    {
        $reference = $this->objectReferenceFromValue($value);
        return $reference === null ? null : $reference['objectNumber'];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function objectReferenceFromValue(string $value): ?array
    {
        $reference = $this->indirectReferenceTokenAt($value, 0);
        if ($reference === null) {
            return null;
        }

        if ($this->skipWhitespace($value, $reference['end']) < strlen($value)) {
            return null;
        }

        return [
            'objectNumber' => $reference['objectNumber'],
            'generation' => $reference['generation'],
        ];
    }

    /**
     * PDF comments are whitespace, including between indirect-reference operands.
     *
     * @return array{token: string, objectNumber: int, generation: int, end: int}|null
     */
    private function indirectReferenceTokenAt(string $value, int $offset): ?array
    {
        $length = strlen($value);
        $start = $this->skipWhitespace($value, $offset);
        if ($start >= $length || preg_match('/\G\d+/s', $value, $objectMatch, 0, $start) !== 1) {
            return null;
        }

        $afterObject = $start + strlen($objectMatch[0]);
        $generationOffset = $this->skipWhitespace($value, $afterObject);
        if (
            $generationOffset <= $afterObject
            || $generationOffset >= $length
            || preg_match('/\G\d+/s', $value, $generationMatch, 0, $generationOffset) !== 1
        ) {
            return null;
        }

        $afterGeneration = $generationOffset + strlen($generationMatch[0]);
        $referenceOffset = $this->skipWhitespace($value, $afterGeneration);
        if (
            $referenceOffset <= $afterGeneration
            || ($value[$referenceOffset] ?? '') !== 'R'
        ) {
            return null;
        }

        $end = $referenceOffset + 1;
        if ($end < $length && !$this->isBareTokenDelimiter($value[$end])) {
            return null;
        }

        return [
            'token' => (int) $objectMatch[0] . ' ' . (int) $generationMatch[0] . ' R',
            'objectNumber' => (int) $objectMatch[0],
            'generation' => (int) $generationMatch[0],
            'end' => $end,
        ];
    }

    private function isBareTokenDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryEntries(string $dictionary): array
    {
        $entries = [];
        for ($offset = 0, $length = strlen($dictionary); $offset < $length;) {
            $offset = $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($dictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            $remaining = substr($dictionary, $offset);
            if (preg_match('/\/([^\s\[\]()<>{}\/%]+)/A', $remaining, $match) !== 1) {
                $offset++;
                continue;
            }

            $name = $this->decodePdfName($match[1]);
            $value = $this->readPdfValueAt($dictionary, $offset + strlen($match[0]));
            if ($value === null) {
                $offset += strlen($match[0]);
                continue;
            }

            $entries[$name] = $value['raw'];
            $offset = $value['end'];
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     */
    private function reviewValueFromRaw(?string $value, array $objects, int $depth = 0): mixed
    {
        if ($value === null || $depth > 4) {
            return null;
        }

        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '[')) {
            $items = [];
            foreach ($this->arrayItemsFromValue($resolved, $objects) as $item) {
                $reviewValue = $this->reviewValueFromRaw($item, $objects, $depth + 1);
                if ($reviewValue !== null) {
                    $items[] = $reviewValue;
                }
            }

            return $items;
        }

        if (str_starts_with($resolved, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($resolved, 0);
            if ($dictionary === null) {
                return null;
            }

            $metadata = [];
            foreach ($this->dictionaryEntries($dictionary['body']) as $name => $entryValue) {
                $reviewValue = $this->reviewValueFromRaw($entryValue, $objects, $depth + 1);
                if ($reviewValue !== null && $reviewValue !== '') {
                    $metadata[$name] = $reviewValue;
                }
            }

            return $metadata === [] ? null : $metadata;
        }

        if ($resolved === 'true') {
            return true;
        }

        if ($resolved === 'false') {
            return false;
        }

        if ($resolved === 'null') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $resolved) === 1) {
            return (int) $resolved;
        }

        if (preg_match('/^-?(?:\d+\.\d*|\d*\.\d+)$/', $resolved) === 1) {
            return (float) $resolved;
        }

        return $this->stringValueFromRaw($resolved, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function stringValueFromRaw(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '(')) {
            $literal = $this->readLiteralStringAt($resolved, 0);
            return $literal === null ? null : $this->decodePdfStringBytes($this->decodeLiteralEscapes($literal['body']));
        }

        if (str_starts_with($resolved, '<') && !str_starts_with($resolved, '<<')) {
            $end = strpos($resolved, '>');
            if ($end === false) {
                return null;
            }

            $hex = preg_replace('/\s+/', '', substr($resolved, 1, $end - 1));
            if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if (str_starts_with($resolved, '/')) {
            return $this->decodePdfName(substr($resolved, 1));
        }

        return preg_match('/^[^\s\[\]()<>{}\/%]+$/', $resolved) === 1 ? $resolved : null;
    }

    /**
     * PDF byte strings such as embedded-file /CheckSum must remain binary-safe.
     *
     * @param array<int, string> $objects
     */
    private function byteStringValueFromRaw(string $value, array $objects): ?string
    {
        $resolved = trim($this->resolveRawValue($value, $objects) ?? $value);
        if ($resolved === '') {
            return null;
        }

        if (str_starts_with($resolved, '(')) {
            $literal = $this->readLiteralStringAt($resolved, 0);
            return $literal === null ? null : $this->decodeLiteralEscapes($literal['body']);
        }

        if (str_starts_with($resolved, '<') && !str_starts_with($resolved, '<<')) {
            $end = strpos($resolved, '>');
            if ($end === false) {
                return null;
            }

            $hex = preg_replace('/\s+/', '', substr($resolved, 1, $end - 1));
            if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? null : $bytes;
        }

        return null;
    }

    /**
     * @return array{raw: string, end: int}|null
     */
    private function readPdfValueAt(string $value, int $offset): ?array
    {
        $offset = $this->skipWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return null;
        }

        $char = $value[$offset];
        if ($char === '[') {
            return $this->readPdfArrayAt($value, $offset);
        }

        if (substr($value, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryAt($value, $offset);
            return $dictionary === null ? null : ['raw' => $dictionary['raw'], 'end' => $dictionary['end']];
        }

        if ($char === '(') {
            $literal = $this->readLiteralStringAt($value, $offset);
            return $literal === null ? null : ['raw' => substr($value, $offset, $literal['end'] - $offset), 'end' => $literal['end']];
        }

        if ($char === '<') {
            $end = $this->skipHexString($value, $offset);
            return $end === null ? null : ['raw' => substr($value, $offset, $end - $offset), 'end' => $end];
        }

        $reference = $this->indirectReferenceTokenAt($value, $offset);
        if ($reference !== null) {
            return ['raw' => $reference['token'], 'end' => $reference['end']];
        }

        $remaining = substr($value, $offset);
        if (preg_match('/\d+\s+\d+\s+R\b/A', $remaining, $match) === 1) {
            return ['raw' => $match[0], 'end' => $offset + strlen($match[0])];
        }

        if (preg_match('/\/[^\s\[\]()<>{}\/%]+|[^\s\[\]()<>{}\/%]+/A', $remaining, $match) === 1) {
            return ['raw' => $match[0], 'end' => $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * @return array{body: string, raw: string, end: int}|null
     */
    private function readPdfDictionaryAt(string $value, int $offset): ?array
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $literal = $this->readLiteralStringAt($value, $index);
                if ($literal === null) {
                    return null;
                }
                $index = $literal['end'] - 1;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $end = $this->skipHexString($value, $index);
                if ($end === null) {
                    return null;
                }
                $index = $end - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $end = $index + 2;
                return [
                    'body' => substr($value, $bodyStart, $index - $bodyStart),
                    'raw' => substr($value, $offset, $end - $offset),
                    'end' => $end,
                ];
            }
            $index++;
        }

        return null;
    }

    /**
     * @return array{raw: string, end: int}|null
     */
    private function readPdfArrayAt(string $value, int $offset): ?array
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $literal = $this->readLiteralStringAt($value, $index);
                if ($literal === null) {
                    return null;
                }
                $index = $literal['end'] - 1;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryAt($value, $index);
                if ($dictionary === null) {
                    return null;
                }
                $index = $dictionary['end'] - 1;
                continue;
            }

            if ($char === '<') {
                $end = $this->skipHexString($value, $index);
                if ($end === null) {
                    return null;
                }
                $index = $end - 1;
                continue;
            }

            if ($char === '[') {
                $depth++;
                continue;
            }

            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $end = $index + 1;
                return ['raw' => substr($value, $offset, $end - $offset), 'end' => $end];
            }
        }

        return null;
    }

    /**
     * @return array{body: string, end: int}|null
     */
    private function readLiteralStringAt(string $value, int $offset): ?array
    {
        if (($value[$offset] ?? '') !== '(') {
            return null;
        }

        $depth = 0;
        $body = '';
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    if ($depth > 0) {
                        $body .= $char . $value[$index + 1];
                    }
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $body .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return ['body' => $body, 'end' => $index + 1];
                }
                $body .= $char;
                continue;
            }

            if ($depth > 0) {
                $body .= $char;
            }
        }

        return null;
    }

    private function skipHexString(string $value, int $offset): ?int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? null : $end + 1;
    }

    private function skipWhitespace(string $value, int $offset): int
    {
        for ($length = strlen($value); $offset < $length;) {
            if ($this->isPdfWhitespace($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '%') {
                while ($offset < $length && $value[$offset] !== "\n" && $value[$offset] !== "\r") {
                    $offset++;
                }
                continue;
            }

            break;
        }

        return $offset;
    }

    private function skipPdfCommentOffset(string $value, int $offset): int
    {
        for ($length = strlen($value); $offset < $length; $offset++) {
            if ($value[$offset] === "\n" || $value[$offset] === "\r") {
                break;
            }
        }

        return $offset;
    }

    private function isPdfDelimiter(string $char): bool
    {
        return $this->isPdfWhitespace($char) || str_contains('[]()<>{}%', $char);
    }

    private function isPdfWhitespace(string $char): bool
    {
        return $char === "\0"
            || $char === "\t"
            || $char === "\n"
            || $char === "\f"
            || $char === "\r"
            || $char === ' ';
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function decodeLiteralEscapes(string $bytes): string
    {
        $decoded = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
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

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }
}
