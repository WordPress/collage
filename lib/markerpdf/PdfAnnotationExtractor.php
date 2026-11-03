<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAnnotationExtractor
{
    private const BORDER_STYLE_NAMES = [
        'S' => 'solid',
        'D' => 'dashed',
        'B' => 'beveled',
        'I' => 'inset',
        'U' => 'underline',
    ];

    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    private const COMMON_FIELD_FLAGS = [
        1 => 'read_only',
        2 => 'required',
        3 => 'no_export',
    ];

    private const FIELD_TYPE_FLAGS = [
        'Tx' => [
            13 => 'multiline',
            14 => 'password',
            21 => 'file_select',
            23 => 'do_not_spell_check',
            24 => 'do_not_scroll',
            25 => 'comb',
            26 => 'rich_text',
        ],
        'Btn' => [
            15 => 'no_toggle_to_off',
            16 => 'radio',
            17 => 'push_button',
            26 => 'radios_in_unison',
        ],
        'Ch' => [
            18 => 'combo',
            19 => 'edit',
            20 => 'sort',
            22 => 'multi_select',
            23 => 'do_not_spell_check',
            27 => 'commit_on_sel_change',
        ],
    ];

    private const WIDGET_HIGHLIGHT_MODE_LABELS = [
        'N' => 'none',
        'I' => 'invert',
        'O' => 'outline',
        'P' => 'push',
        'T' => 'toggle',
    ];

    private const WIDGET_TEXT_POSITION_LABELS = [
        0 => 'caption_only',
        1 => 'caption_above_icon',
        2 => 'caption_below_icon',
        3 => 'caption_right_of_icon',
        4 => 'caption_left_of_icon',
        5 => 'caption_overlaid_icon',
        6 => 'icon_only',
    ];

    /** @var array<int, array<int, string>> */
    private array $objectBodiesByGeneration = [];

    /** @var array<int, true> */
    private array $xrefFreeObjectNumbers = [];

    /** @var array<int, true> */
    private array $xrefSelectedGenerationZeroObjects = [];

    /** @var array<int, true> */
    private array $xrefStreamSuppressedObjectNumbers = [];

    /**
     * Native boundary for PDF page /Annots presentation metadata.
     *
     * @return list<array{pnum: int, page_object: int, annotations: list<array<string, mixed>>}>
     */
    public function extractPageAnnotations(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $this->objectBodiesByGeneration = $this->pdfObjectBodiesByGeneration($pdfBytes);
        $this->xrefFreeObjectNumbers = PdfXrefFreeObjectMap::freeObjectNumbers($pdfBytes);
        foreach (array_keys($this->xrefFreeObjectNumbers) as $objectNumber) {
            unset($objects[$objectNumber], $this->objectBodiesByGeneration[$objectNumber]);
        }

        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $actionTargetContext = $this->annotationActionTargetContext($pdfBytes);
        $structureParentReviewByKey = $this->annotationStructureParentReviewByKey($pdfBytes, $objects);
        $structureReviewByAnnotationObject = $this->annotationStructTreeReviewByAnnotationObject($pdfBytes, $objects);
        $pageObjectReferences = $this->orderedPageObjectReferences($objects);
        $pages = [];

        foreach ($pageObjectReferences as $pnum => $pageReference) {
            $pageObjectNumber = $pageReference['object'];
            $pageGeneration = $pageReference['generation'];
            $pageBody = $this->objectBodyForReference($pageObjectNumber, $pageGeneration, $objects)
                ?? ($objects[$pageObjectNumber] ?? null);
            if ($pageBody === null) {
                continue;
            }

            $records = $this->annotationRecordsForPage($pageObjectNumber, $pageGeneration, $pageBody, $objects);
            if ($records === []) {
                continue;
            }

            $reversePopups = $this->popupRecordsByParentObject($records, $objects);
            $threadReview = $this->annotationThreadReviewForRecords($records, $objects);
            $annotations = [];
            foreach ($records as $record) {
                $subtype = $this->subtypeFromAnnotation($record['body'], $objects);
                if ($subtype === 'Popup' && $this->objectReferenceValueAfterName($record['body'], 'Parent') !== null) {
                    continue;
                }

                $annotations[] = $this->annotationReviewRow(
                    $record,
                    $objects,
                    $reversePopups,
                    $actionReviewer,
                    $threadReview['rows_by_object'],
                    $structureParentReviewByKey,
                    $structureReviewByAnnotationObject,
                    $actionTargetContext
                );
            }

            if ($annotations !== []) {
                $page = [
                    'pnum' => $pnum,
                    'page_object' => $pageObjectNumber,
                    'annotations' => $annotations,
                ];
                if ($threadReview['threads'] !== []) {
                    $page['annotation_threads'] = $threadReview['threads'];
                }
                if ($threadReview['detached_replies'] !== []) {
                    $page['detached_annotation_thread_replies'] = $threadReview['detached_replies'];
                }

                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * @param array{body: string, object: int|null, generation?: int|null} $record
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>
     */
    private function annotationReviewRow(
        array $record,
        array $objects,
        array $reversePopups,
        PdfActionReviewExtractor $actionReviewer,
        array $threadRowsByObject = [],
        array $structureParentReviewByKey = [],
        array $structureReviewByAnnotationObject = [],
        array $actionTargetContext = []
    ): array
    {
        $body = $record['body'];
        $subtype = $this->subtypeFromAnnotation($body, $objects);
        $rect = $this->rectFromAnnotation($body, $objects);
        $actionReview = $actionReviewer->reviewAnnotationActions($body);
        $actionReview['actions'] = $this->actionsWithAnnotationTargetPageContext(
            $actionReview['actions'],
            $actionTargetContext
        );
        $actionReview['additional_actions'] = $this->actionsWithAnnotationTargetPageContext(
            $actionReview['additional_actions'],
            $actionTargetContext
        );
        $actionReview['previous_uri_actions'] = $this->actionsWithAnnotationTargetPageContext(
            $actionReview['previous_uri_actions'] ?? [],
            $actionTargetContext
        );
        $annotationGeneration = is_int($record['generation'] ?? null) ? $record['generation'] : null;
        $structParent = $this->intValueAfterName($body, 'StructParent', $objects);
        $inheritedStructParent = $subtype === 'Widget' && $structParent === null
            ? $this->widgetStructParentFromFieldChain(
                $body,
                $objects,
                $record['object'],
                $annotationGeneration,
                $structureParentReviewByKey
            )
            : null;
        if ($inheritedStructParent !== null) {
            $structParent = $inheritedStructParent['key'];
        }
        $structTreeReview = null;
        if ($record['object'] !== null) {
            if ($annotationGeneration !== null) {
                $structTreeReview = $structureReviewByAnnotationObject[
                    $this->annotationReferenceKey($record['object'], $annotationGeneration)
                ] ?? null;
            }

            if ($structTreeReview === null && isset($structureReviewByAnnotationObject[$record['object']])) {
                $candidate = $structureReviewByAnnotationObject[$record['object']];
                $hasExactReferences = is_array($candidate['annotation_references'] ?? null)
                    && $candidate['annotation_references'] !== [];
                if ($annotationGeneration === null || !$hasExactReferences) {
                    $structTreeReview = $candidate;
                }
            }
        }
        $structureParent = null;
        $annotationFlags = $this->intValueAfterName($body, 'F', $objects) ?? 0;

        $row = [
            'subtype' => $subtype,
            'annotation_object' => $record['object'],
            'annotation_generation' => $annotationGeneration,
            'rect' => $rect,
            'annotation_flags' => $annotationFlags,
            'annotation_flag_names' => $this->annotationFlagNames($annotationFlags),
            'annotation_visibility' => $this->annotationVisibility($annotationFlags),
            'contents' => $this->pdfStringValueAfterName($body, 'Contents', $objects),
            'title' => $this->pdfStringValueAfterName($body, 'T', $objects),
            'name' => $this->pdfStringValueAfterName($body, 'NM', $objects),
            'modified_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'border_color' => $this->colorValueAfterName($body, 'C', $objects),
            'interior_color' => $this->colorValueAfterName($body, 'IC', $objects),
            'opacity' => $this->opacityFromAnnotation($body, $objects),
            'border' => $this->borderFromAnnotation($body, $objects),
            'popup' => $this->popupFromAnnotation($body, $objects, $record['object'], $reversePopups),
            'actions' => $actionReview['actions'],
            'additional_actions' => $actionReview['additional_actions'],
            'executes_actions_on_import' => $actionReview['executes_actions_on_import'],
        ];
        if ($actionReview['previous_uri_actions'] !== []) {
            $row['previous_uri_actions'] = $actionReview['previous_uri_actions'];
        }
        if (is_array($actionReview['duplicate_key_review'] ?? null)) {
            $row['duplicate_action_key_review'] = $actionReview['duplicate_key_review'];
            $row['duplicate_action_keys'] = $actionReview['duplicate_keys'] ?? $actionReview['duplicate_key_review']['keys'] ?? [];
        }
        if (is_array($actionReview['malformed_action_operand_review'] ?? null)) {
            $row['malformed_action_operand_review'] = $actionReview['malformed_action_operand_review'];
            $row['malformed_action_operand_keys'] = $actionReview['malformed_action_operand_keys']
                ?? $actionReview['malformed_action_operand_review']['keys']
                ?? [];
        }

        if ($structParent !== null) {
            $row['struct_parent'] = $structParent;
            $parentTreeReviewExists = array_key_exists($structParent, $structureParentReviewByKey);
            $structureParent = $this->structureParentReviewForAnnotation(
                $structureParentReviewByKey[$structParent] ?? [
                    'source' => 'annotation_struct_parent_parent_tree',
                    'key' => $structParent,
                    'entry_count' => 0,
                    'review_only' => true,
                    'visible_text_source' => false,
                ],
                $record['object'],
                $annotationGeneration
            );
            if (
                is_array($structTreeReview)
                && (
                    !$parentTreeReviewExists
                    || ($structureParent['current_annotation_object_ref_matched'] ?? false) !== true
                )
            ) {
                $structTreeReview['source'] = 'annotation_struct_tree_objr_parent_tree_fallback';
                $structTreeReview['key'] = $structParent;
                $structTreeReview['struct_parent'] = $structParent;
                if (!$parentTreeReviewExists) {
                    $structTreeReview['parent_tree_key_missing'] = true;
                }
                $structureParent = $this->structureParentReviewForAnnotation(
                    $structTreeReview,
                    $record['object'],
                    $annotationGeneration
                );
            }
            if ($inheritedStructParent !== null) {
                $row['struct_parent_source'] = $inheritedStructParent['source'];
                $row['struct_parent_field_object'] = $inheritedStructParent['field_object'];
                $row['struct_parent_field_chain'] = $inheritedStructParent['field_chain'];
                $structureParent['struct_parent_source'] = $inheritedStructParent['source'];
                $structureParent['field_object'] = $inheritedStructParent['field_object'];
                $structureParent['field_chain'] = $inheritedStructParent['field_chain'];
            }
        } elseif (is_array($structTreeReview)) {
            $structureParent = $this->structureParentReviewForAnnotation(
                $structTreeReview,
                $record['object'],
                $annotationGeneration
            );
        }

        if ($structureParent !== null) {
            $row['structure_parent'] = $structureParent;
        }

        if ($structureParent !== null && $structParent !== null) {
            $row['actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                $row['actions'],
                $record['object'],
                $structParent,
                $structureParent
            );
            $row['additional_actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                $row['additional_actions'],
                $record['object'],
                $structParent,
                $structureParent
            );
            if (isset($row['previous_uri_actions'])) {
                $row['previous_uri_actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                    $row['previous_uri_actions'],
                    $record['object'],
                    $structParent,
                    $structureParent
                );
            }
        }

        $appearance = $this->appearanceFromAnnotation($body, $objects);
        if ($appearance !== null) {
            $row['appearance'] = $appearance;
        }

        if ($subtype === 'Widget') {
            $row['widget'] = $this->widgetReviewFromAnnotation(
                $body,
                $objects,
                $record['object'],
                $appearance,
                $actionReview
            );
        }

        $sound = $this->soundFromAnnotation($body, $objects);
        if ($sound !== null) {
            $row['sound'] = $sound;
        }

        $geometry = $this->geometryFromAnnotation($body, $objects, $subtype, $rect);
        if ($geometry !== null) {
            $row['geometry'] = $geometry;
        }

        $annotationObject = $record['object'];
        if ($annotationObject !== null && isset($threadRowsByObject[$annotationObject])) {
            $row['reply_thread'] = $threadRowsByObject[$annotationObject];
        }

        return $row;
    }

    /**
     * @return array{
     *     page_labels: list<string>,
     *     page_presentations_by_page: array<int, array<string, mixed>>
     * }
     */
    private function annotationActionTargetContext(string $pdfBytes): array
    {
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdfBytes, false);
        $pagePresentationsByPage = [];
        foreach (($navigation['page_presentations'] ?? []) as $pagePresentation) {
            if (!is_array($pagePresentation)) {
                continue;
            }

            $pageIndex = $pagePresentation['pnum'] ?? null;
            if (is_int($pageIndex)) {
                $pagePresentationsByPage[$pageIndex] = $pagePresentation;
            }
        }

        return [
            'page_labels' => (new PdfTextExtractor())->extractPageLabels($pdfBytes),
            'page_presentations_by_page' => $pagePresentationsByPage,
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function actionsWithAnnotationTargetPageContext(array $actions, array $context): array
    {
        foreach ($actions as $index => $action) {
            if (($action['safety'] ?? null) !== 'local-destination') {
                continue;
            }

            $pageIndex = $action['destination_page'] ?? $action['page'] ?? null;
            if (!is_int($pageIndex)) {
                continue;
            }

            $pageLabels = $context['page_labels'] ?? [];
            if (is_array($pageLabels)) {
                $actions[$index]['destination_page_label'] = $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
            }

            $pagePresentationsByPage = $context['page_presentations_by_page'] ?? [];
            if (
                is_array($pagePresentationsByPage)
                && isset($pagePresentationsByPage[$pageIndex])
                && is_array($pagePresentationsByPage[$pageIndex])
            ) {
                $pagePresentation = $pagePresentationsByPage[$pageIndex];
                $actions[$index]['target_display_duration'] = $pagePresentation['display_duration'] ?? null;
                $actions[$index]['target_page_transition'] = $pagePresentation['transition'] ?? null;
                $actions[$index]['target_page_actions'] = is_array($pagePresentation['actions'] ?? null)
                    ? $pagePresentation['actions']
                    : [];
            }
        }

        return $actions;
    }

    /**
     * Annotation dictionaries use singular `/StructParent`, whose key points
     * into the structure ParentTree. The ParentTree value is a StructElem
     * object whose `/K` commonly contains an OBJR object-reference dictionary
     * back to the annotation.
     *
     * @param array<int, string> $objects
     * @return array<int, array<string, mixed>>
     */
    private function annotationStructureParentReviewByKey(string $pdfBytes, array $objects): array
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return [];
        }

        $structTreeRootValue = $this->dictionaryRawValue($catalog, 'StructTreeRoot');
        if ($structTreeRootValue === null) {
            return [];
        }

        $structTreeRoot = $this->resolvedDictionaryFromValue($structTreeRootValue, $objects);
        if ($structTreeRoot === null) {
            return [];
        }

        $parentTree = $this->resolvedDictionaryFromValue(
            $this->dictionaryRawValue($structTreeRoot['body'], 'ParentTree') ?? '',
            $objects
        );
        if ($parentTree === null) {
            return [];
        }

        $valuesByKey = [];
        $this->collectStructureParentTreeValues($parentTree['body'], $objects, $valuesByKey);
        if ($valuesByKey === []) {
            return [];
        }

        $roleMap = $this->structureRoleMap($structTreeRoot['body'], $objects);
        $elementsByObject = $this->structureElementsByObject($pdfBytes);
        $reviewByKey = [];
        foreach ($valuesByKey as $key => $value) {
            $review = $this->annotationStructureParentReviewFromValue(
                $key,
                $value,
                $objects,
                $roleMap,
                $elementsByObject
            );
            if ($review !== null) {
                $reviewByKey[$key] = $review;
            }
        }

        return $reviewByKey;
    }

    /**
     * Some tagged PDFs expose annotation associations through StructElem `/K`
     * object-reference dictionaries even when the annotation `/StructParent`
     * key or ParentTree number-tree row is missing. Keep that as review
     * metadata for current page annotations without making StructElem strings
     * visible document text.
     *
     * @param array<int, string> $objects
     * @return array<int|string, array<string, mixed>>
     */
    private function annotationStructTreeReviewByAnnotationObject(string $pdfBytes, array $objects): array
    {
        $elementsByObject = $this->structureElementsByObject($pdfBytes);
        if ($elementsByObject === []) {
            return [];
        }

        $reviews = [];
        foreach ($elementsByObject as $structObject => $metadata) {
            $dictionary = $this->dictionaryObjectBody($objects[$structObject] ?? '');
            if ($dictionary === null) {
                continue;
            }

            $annotationReferences = $this->structureAnnotationReferencesFromKidValue(
                $this->dictionaryRawValue($dictionary, 'K'),
                $objects
            );
            $annotationObjects = $this->annotationObjectsFromReferences($annotationReferences);
            if ($annotationObjects === []) {
                continue;
            }

            $row = [
                'source' => 'annotation_struct_tree_objr',
                'entry_count' => 1,
                'review_only' => true,
                'visible_text_source' => false,
            ];
            $this->copyStructureElementMetadata($row, $metadata);
            $row['struct_object'] = $structObject;
            $row['annotation_objects'] = $annotationObjects;
            $row['annotation_references'] = $annotationReferences;
            $row['annotation_reference_keys'] = array_map(
                fn (array $reference): string => $this->annotationReferenceKey($reference['object'], $reference['generation']),
                $annotationReferences
            );
            $row['object_reference_count'] = count($annotationObjects);

            foreach ($annotationReferences as $reference) {
                $key = $this->annotationReferenceKey($reference['object'], $reference['generation']);
                if (!isset($reviews[$key])) {
                    $reviews[$key] = $row;
                }
            }
        }

        return $reviews;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $valuesByKey
     * @param array<int, true> $seenObjects
     */
    private function collectStructureParentTreeValues(
        string $dictionary,
        array $objects,
        array &$valuesByKey,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $nums = $this->dictionaryRawValue($dictionary, 'Nums');
        if ($nums !== null) {
            $numsBody = $this->arrayBodyFromPdfValue($nums, $objects);
            if ($numsBody !== null) {
                $items = $this->arrayItemsFromBody($numsBody);
                for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
                    $key = trim($items[$index]);
                    if (preg_match('/^[+-]?\d+$/', $key) !== 1) {
                        continue;
                    }

                    $valuesByKey[(int) $key] = $items[$index + 1];
                }
            }
        }

        $kids = $this->dictionaryRawValue($dictionary, 'Kids');
        if ($kids === null) {
            return;
        }

        $kidsBody = $this->arrayBodyFromPdfValue($kids, $objects);
        if ($kidsBody === null) {
            return;
        }

        foreach ($this->objectReferences($kidsBody) as $kidObjectNumber) {
            if (isset($seenObjects[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seenObjects;
            $nextSeen[$kidObjectNumber] = true;
            $this->collectStructureParentTreeValues($kidDictionary, $objects, $valuesByKey, $nextSeen, $depth + 1);
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, array<string, mixed>> $elementsByObject
     * @return array<string, mixed>|null
     */
    private function annotationStructureParentReviewFromValue(
        int $key,
        string $value,
        array $objects,
        array $roleMap,
        array $elementsByObject
    ): ?array {
        $parent = $this->resolvedDictionaryFromValue($value, $objects);
        if ($parent === null) {
            return null;
        }

        $body = $parent['body'];
        $rawRole = $this->pdfNameValueAfterName($body, 'S');
        $type = $this->pdfNameValueAfterName($body, 'Type');
        if ($type !== 'StructElem' && $rawRole === null) {
            return null;
        }

        $row = [
            'source' => 'annotation_struct_parent_parent_tree',
            'key' => $key,
            'entry_count' => 1,
            'review_only' => true,
            'visible_text_source' => false,
        ];

        $structObject = $parent['object'];
        if ($structObject !== null) {
            $row['struct_object'] = $structObject;
            $metadata = $elementsByObject[$structObject] ?? [];
            $this->copyStructureElementMetadata($row, $metadata);
        }

        if ($rawRole !== null && !array_key_exists('raw_role', $row)) {
            $row['raw_role'] = $rawRole;
            $row['role'] = $roleMap[$rawRole] ?? $rawRole;
            $row['role_mapped'] = $row['role'] !== $rawRole;
        }

        foreach ([
            'title' => 'T',
            'alternate_text' => 'Alt',
            'actual_text' => 'ActualText',
            'expansion_text' => 'E',
            'id' => 'ID',
        ] as $targetKey => $pdfKey) {
            if (array_key_exists($targetKey, $row)) {
                continue;
            }

            $value = $this->pdfStringValueAfterName($body, $pdfKey, $objects);
            if ($value !== null && $value !== '') {
                $row[$targetKey] = $value;
            }
        }

        $pageObject = $this->objectReferenceValueAfterName($body, 'Pg');
        if ($pageObject !== null && !array_key_exists('page_object', $row)) {
            $row['page_object'] = $pageObject;
        }

        $annotationReferences = $this->structureAnnotationReferencesFromKidValue(
            $this->dictionaryRawValue($body, 'K'),
            $objects
        );
        $annotationObjects = $this->annotationObjectsFromReferences($annotationReferences);
        if ($annotationObjects !== []) {
            $row['annotation_objects'] = $annotationObjects;
            $row['annotation_references'] = $annotationReferences;
            $row['annotation_reference_keys'] = array_map(
                fn (array $reference): string => $this->annotationReferenceKey($reference['object'], $reference['generation']),
                $annotationReferences
            );
            $row['object_reference_count'] = count($annotationObjects);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $metadata
     */
    private function copyStructureElementMetadata(array &$row, array $metadata): void
    {
        foreach ([
            'object' => 'struct_object',
            'raw_role' => 'raw_role',
            'role' => 'role',
            'role_mapped' => 'role_mapped',
            'page_object' => 'page_object',
            'page' => 'page',
            'page_number' => 'page_number',
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
        ] as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $metadata)) {
                $row[$targetKey] = $metadata[$sourceKey];
            }
        }
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private function structureParentReviewForAnnotation(
        array $review,
        ?int $annotationObject,
        ?int $annotationGeneration = null
    ): array
    {
        $review['current_page_annotation'] = true;
        $review['review_only'] = true;
        $review['visible_text_source'] = false;

        if ($annotationObject !== null) {
            $review['annotation_object'] = $annotationObject;
            if ($annotationGeneration !== null) {
                $review['annotation_generation'] = $annotationGeneration;
            }
            $annotationObjects = $review['annotation_objects'] ?? [];
            if (is_array($annotationObjects) && $annotationObjects !== []) {
                $review['current_annotation_object_ref_matched'] = $this->structureReviewMatchesAnnotation(
                    $review,
                    $annotationObject,
                    $annotationGeneration
                );
            }
        }

        return $review;
    }

    private function structureReviewMatchesAnnotation(
        array $review,
        int $annotationObject,
        ?int $annotationGeneration
    ): bool {
        $annotationReferences = $review['annotation_references'] ?? [];
        if ($annotationGeneration !== null && is_array($annotationReferences) && $annotationReferences !== []) {
            foreach ($annotationReferences as $reference) {
                if (
                    is_array($reference)
                    && ($reference['object'] ?? null) === $annotationObject
                    && ($reference['generation'] ?? null) === $annotationGeneration
                ) {
                    return true;
                }
            }

            return false;
        }

        $annotationObjects = $review['annotation_objects'] ?? [];
        return is_array($annotationObjects) && in_array($annotationObject, $annotationObjects, true);
    }

    /**
     * Some AcroForm PDFs keep the singular /StructParent on the terminal field
     * dictionary while the page annotation row is the visible /Widget child.
     * Promote that key only when the ParentTree StructElem OBJR points back to
     * the current page widget, avoiding stale field-only structure entries.
     *
     * @param array<int, string> $objects
     * @param array<int, array<string, mixed>> $structureParentReviewByKey
     * @return array{key: int, source: string, field_object: int, field_chain: list<int>}|null
     */
    private function widgetStructParentFromFieldChain(
        string $body,
        array $objects,
        ?int $widgetObject,
        ?int $widgetGeneration,
        array $structureParentReviewByKey
    ): ?array {
        if ($widgetObject === null) {
            return null;
        }

        $parentObjects = $this->parentFieldObjects($body, $objects);
        foreach ($parentObjects as $parentObject) {
            $parentBody = $this->dictionaryObjectBody($objects[$parentObject] ?? '');
            if ($parentBody === null) {
                continue;
            }

            $structParent = $this->intValueAfterName($parentBody, 'StructParent', $objects);
            if ($structParent === null) {
                continue;
            }

            $review = $structureParentReviewByKey[$structParent] ?? null;
            if (
                !is_array($review)
                || !$this->structureReviewMatchesAnnotation($review, $widgetObject, $widgetGeneration)
            ) {
                continue;
            }

            return [
                'key' => $structParent,
                'source' => 'widget_parent_field_struct_parent',
                'field_object' => $parentObject,
                'field_chain' => $parentObjects,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function structureElementsByObject(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $elements = $metadata['structure_tree']['elements'] ?? [];
        if (!is_array($elements)) {
            return [];
        }

        $byObject = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $object = $element['object'] ?? null;
            if (is_int($object)) {
                $byObject[$object] = $element;
            }
        }

        return $byObject;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function structureRoleMap(string $structTreeRoot, array $objects): array
    {
        $roleMap = $this->resolvedDictionaryFromValue(
            $this->dictionaryRawValue($structTreeRoot, 'RoleMap') ?? '',
            $objects
        );
        if ($roleMap === null) {
            return [];
        }

        $roles = [];
        foreach ($this->dictionaryEntries($roleMap['body']) as $entry) {
            $role = $this->pdfValueToString($entry['value'], $objects);
            if ($role === null || $role === '') {
                continue;
            }

            $roles[$entry['name']] = $role;
        }

        return $roles;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function structureAnnotationObjectsFromKidValue(
        ?string $value,
        array $objects,
        array $seenObjects = [],
        int $depth = 0
    ): array {
        return $this->annotationObjectsFromReferences(
            $this->structureAnnotationReferencesFromKidValue($value, $objects, $seenObjects, $depth)
        );
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{object: int, generation: int}>
     */
    private function structureAnnotationReferencesFromKidValue(
        ?string $value,
        array $objects,
        array $seenObjects = [],
        int $depth = 0
    ): array {
        if ($value === null || $depth > 12) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $reference = $this->exactObjectReferenceWithGenerationFromValue($value);
        if ($reference !== null) {
            $referenceKey = $this->annotationReferenceKey($reference['object'], $reference['generation']);
            if (isset($seenObjects[$referenceKey]) || !isset($objects[$reference['object']])) {
                return [];
            }

            $dictionary = $this->resolvedDictionaryFromValue($value, $objects);
            if ($dictionary === null) {
                return [];
            }

            $nextSeen = $seenObjects;
            $nextSeen[$referenceKey] = true;
            return $this->structureAnnotationReferencesFromDictionary($dictionary['body'], $objects, $nextSeen, $depth + 1);
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromPdfValue($value, $objects);
            if ($body === null) {
                return [];
            }

            $referencesByKey = [];
            foreach ($this->arrayItemsFromBody($body) as $item) {
                foreach ($this->structureAnnotationReferencesFromKidValue($item, $objects, $seenObjects, $depth + 1) as $annotationReference) {
                    $referencesByKey[$this->annotationReferenceKey(
                        $annotationReference['object'],
                        $annotationReference['generation']
                    )] = $annotationReference;
                }
            }

            return array_values($referencesByKey);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null
                ? []
                : $this->structureAnnotationReferencesFromDictionary($dictionary, $objects, $seenObjects, $depth + 1);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{object: int, generation: int}>
     */
    private function structureAnnotationReferencesFromDictionary(
        string $dictionary,
        array $objects,
        array $seenObjects,
        int $depth
    ): array {
        $type = $this->pdfNameValueAfterName($dictionary, 'Type');
        $objectValue = $this->dictionaryRawValue($dictionary, 'Obj');
        if ($type === 'OBJR' || $objectValue !== null) {
            $reference = $objectValue === null ? null : $this->objectReferenceWithGenerationFromValue($objectValue);
            return $reference === null ? [] : [$reference];
        }

        return $this->structureAnnotationReferencesFromKidValue(
            $this->dictionaryRawValue($dictionary, 'K'),
            $objects,
            $seenObjects,
            $depth + 1
        );
    }

    /**
     * @param list<array{object: int, generation: int}> $references
     * @return list<int>
     */
    private function annotationObjectsFromReferences(array $references): array
    {
        $objectsByNumber = [];
        foreach ($references as $reference) {
            $objectsByNumber[$reference['object']] = $reference['object'];
        }

        return array_values($objectsByNumber);
    }

    private function annotationReferenceKey(int $objectNumber, int $generation): string
    {
        return $objectNumber . ':' . $generation;
    }

    /**
     * PDF annotation reply threads are page-local review metadata. `/IRT`
     * points at the annotation being replied to; `/RT`, `/State`, and
     * `/StateModel` describe the review state but must not execute or render.
     *
     * @param list<array{body: string, object: int|null}> $records
     * @param array<int, string> $objects
     * @return array{rows_by_object: array<int, array<string, mixed>>, threads: list<array<string, mixed>>, detached_replies: list<array<string, mixed>>}
     */
    private function annotationThreadReviewForRecords(array $records, array $objects): array
    {
        $bodiesByObject = [];
        $subtypesByObject = [];
        foreach ($records as $record) {
            $object = $record['object'];
            $subtype = $this->subtypeFromAnnotation($record['body'], $objects);
            if ($object === null || $subtype === 'Popup') {
                continue;
            }

            $bodiesByObject[$object] = $record['body'];
            $subtypesByObject[$object] = $subtype;
        }

        if ($bodiesByObject === []) {
            return ['rows_by_object' => [], 'threads' => [], 'detached_replies' => []];
        }

        $currentObjects = array_fill_keys(array_keys($bodiesByObject), true);
        $parentByObject = [];
        $repliesByObject = [];
        foreach ($bodiesByObject as $object => $body) {
            $parentObject = $this->objectReferenceValueAfterName($body, 'IRT');
            if ($parentObject === null) {
                continue;
            }

            $parentByObject[$object] = $parentObject;
            if (isset($currentObjects[$parentObject])) {
                $repliesByObject[$parentObject][] = $object;
            }
        }

        $rowsByObject = [];
        $detachedReplies = [];
        foreach ($bodiesByObject as $object => $body) {
            $parentObject = $parentByObject[$object] ?? null;
            $directReplies = $repliesByObject[$object] ?? [];
            $state = $this->annotationThreadValueAfterName($body, 'State', $objects);
            $stateModel = $this->annotationThreadValueAfterName($body, 'StateModel', $objects);
            if ($parentObject === null && $directReplies === [] && $state === null && $stateModel === null) {
                continue;
            }

            $root = $parentObject === null
                ? ['object' => $object, 'cycle_detected' => false]
                : $this->annotationThreadRootObject($object, $parentByObject, $currentObjects);
            $replyType = $this->pdfNameValueAfterName($body, 'RT') ?? ($parentObject === null ? null : 'R');

            $row = [
                'source' => 'page_annotation_reply_thread',
                'annotation_object' => $object,
                'root_annotation_object' => $root['object'],
                'in_reply_to_object' => $parentObject,
                'in_reply_to_current_page' => $parentObject !== null && isset($currentObjects[$parentObject]),
                'detached_in_reply_to' => $parentObject !== null && !isset($currentObjects[$parentObject]),
                'reply_type' => $replyType,
                'reply_type_label' => $this->annotationReplyTypeLabel($replyType),
                'state' => $state,
                'state_model' => $stateModel,
                'reply_count' => count($directReplies),
                'reply_annotation_objects' => $directReplies,
                'current_page_thread' => $root['object'] !== null,
                'review_only' => true,
                'visible_text_source' => false,
                'executes_actions_on_import' => false,
                'renders_annotation_thread' => false,
            ];
            if ($root['cycle_detected']) {
                $row['cycle_detected'] = true;
            }

            $row = array_filter($row, static fn (mixed $value): bool => $value !== null);
            $rowsByObject[$object] = $row;
            if (($row['detached_in_reply_to'] ?? false) === true) {
                $detachedReplies[] = $row;
            }
        }

        $threads = [];
        foreach ($repliesByObject as $rootObject => $_directReplies) {
            if (isset($parentByObject[$rootObject])) {
                continue;
            }

            $replyObjects = $this->annotationThreadReplyObjects($rootObject, $repliesByObject);
            if ($replyObjects === []) {
                continue;
            }

            $states = [];
            $stateModels = [];
            $replyTypes = [];
            foreach ($replyObjects as $replyObject) {
                $replyBody = $bodiesByObject[$replyObject] ?? null;
                if ($replyBody === null) {
                    continue;
                }

                $state = $this->annotationThreadValueAfterName($replyBody, 'State', $objects);
                if ($state !== null && $state !== '') {
                    $states[$state] = $state;
                }

                $stateModel = $this->annotationThreadValueAfterName($replyBody, 'StateModel', $objects);
                if ($stateModel !== null && $stateModel !== '') {
                    $stateModels[$stateModel] = $stateModel;
                }

                $replyType = $this->pdfNameValueAfterName($replyBody, 'RT') ?? 'R';
                $replyTypes[$replyType] = $this->annotationReplyTypeLabel($replyType);
            }

            $rootBody = $bodiesByObject[$rootObject];
            $threads[] = array_filter([
                'source' => 'page_annotation_reply_thread',
                'root_annotation_object' => $rootObject,
                'root_subtype' => $subtypesByObject[$rootObject] ?? null,
                'root_name' => $this->pdfStringValueAfterName($rootBody, 'NM', $objects),
                'root_title' => $this->pdfStringValueAfterName($rootBody, 'T', $objects),
                'reply_count' => count($replyObjects),
                'reply_annotation_objects' => $replyObjects,
                'reply_type_labels' => array_values($replyTypes),
                'states' => array_values($states),
                'state_models' => array_values($stateModels),
                'current_page_thread' => true,
                'review_only' => true,
                'visible_text_source' => false,
                'executes_actions_on_import' => false,
                'renders_annotation_thread' => false,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return [
            'rows_by_object' => $rowsByObject,
            'threads' => $threads,
            'detached_replies' => $detachedReplies,
        ];
    }

    /**
     * @param array<int, int> $parentByObject
     * @param array<int, true> $currentObjects
     * @return array{object: int|null, cycle_detected: bool}
     */
    private function annotationThreadRootObject(int $object, array $parentByObject, array $currentObjects): array
    {
        $seen = [];
        $current = $object;
        while (isset($parentByObject[$current])) {
            if (isset($seen[$current])) {
                return ['object' => $current, 'cycle_detected' => true];
            }

            $seen[$current] = true;
            $parent = $parentByObject[$current];
            if (!isset($currentObjects[$parent])) {
                return ['object' => null, 'cycle_detected' => false];
            }

            $current = $parent;
        }

        return ['object' => $current, 'cycle_detected' => false];
    }

    /**
     * @param array<int, list<int>> $repliesByObject
     * @return list<int>
     */
    private function annotationThreadReplyObjects(int $rootObject, array $repliesByObject): array
    {
        $replyObjects = [];
        $queue = $repliesByObject[$rootObject] ?? [];
        $seen = [$rootObject => true];
        while ($queue !== [] && count($seen) < 128) {
            $object = array_shift($queue);
            if (!is_int($object) || isset($seen[$object])) {
                continue;
            }

            $seen[$object] = true;
            $replyObjects[] = $object;
            foreach ($repliesByObject[$object] ?? [] as $childObject) {
                $queue[] = $childObject;
            }
        }

        return $replyObjects;
    }

    private function annotationThreadValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        return $this->pdfValueToString($value, $objects);
    }

    private function annotationReplyTypeLabel(?string $replyType): ?string
    {
        return match ($replyType) {
            'R' => 'reply',
            'Group' => 'group',
            null => null,
            default => strtolower($replyType),
        };
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $appearance
     * @param array{actions: list<array<string, mixed>>, additional_actions: list<array<string, mixed>>, previous_uri_actions?: list<array<string, mixed>>, executes_actions_on_import: false} $actionReview
     * @return array<string, mixed>
     */
    private function widgetReviewFromAnnotation(
        string $body,
        array $objects,
        ?int $widgetObject,
        ?array $appearance,
        array $actionReview
    ): array {
        $parentObjects = $this->parentFieldObjects($body, $objects);
        $parentBodies = [];
        foreach ($parentObjects as $parentObject) {
            $parentBody = $this->dictionaryObjectBody($objects[$parentObject] ?? '');
            if ($parentBody !== null) {
                $parentBodies[$parentObject] = $parentBody;
            }
        }

        $effectiveBodies = array_merge([$body], array_values($parentBodies));
        $fieldType = $this->pdfValueToStringFromFirst($effectiveBodies, 'FT', $objects);
        $fieldFlags = $this->intValueFromFirst($effectiveBodies, 'Ff', $objects) ?? 0;
        $annotationFlags = $this->intValueAfterName($body, 'F', $objects) ?? 0;
        $appearanceState = $this->pdfNameValueAfterName($body, 'AS', $objects);
        $appearanceSummary = $this->widgetAppearanceSummary($appearance, $appearanceState);
        $highlightMode = $this->pdfNameValueAfterName($body, 'H', $objects) ?? 'I';

        $review = [
            'source' => 'page_annotation_widget',
            'widget_object' => $widgetObject,
            'field_object' => $parentObjects[0] ?? ($this->widgetDictionaryHasFieldKeys($body) ? $widgetObject : null),
            'parent_field_objects' => $parentObjects,
            'field_name' => $this->widgetFieldName($body, $parentBodies, $objects),
            'mapping_name' => $this->pdfValueToStringFromFirst($effectiveBodies, 'TM', $objects),
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'field_flags' => $fieldFlags,
            'field_flag_names' => $this->fieldFlagNames($fieldFlags, $fieldType),
            'current_value' => $this->pdfValueToStringFromFirst($effectiveBodies, 'V', $objects),
            'default_value' => $this->pdfValueToStringFromFirst($effectiveBodies, 'DV', $objects),
            'annotation_flags' => $annotationFlags,
            'annotation_flag_names' => $this->annotationFlagNames($annotationFlags),
            'annotation_visibility' => $this->annotationVisibility($annotationFlags),
            'visible' => !$this->annotationFlagsHideWidget($annotationFlags),
            'hidden' => $this->annotationFlagsHideWidget($annotationFlags),
            'printable' => $this->hasFlagBit($annotationFlags, 3),
            'no_view' => $this->hasFlagBit($annotationFlags, 6),
            'highlight_mode' => $highlightMode,
            'highlight_mode_label' => self::WIDGET_HIGHLIGHT_MODE_LABELS[$highlightMode] ?? 'unknown',
            'appearance_state' => $appearanceState,
            'appearance_states' => $appearanceSummary['appearance_states'],
            'normal_appearance_type' => $appearanceSummary['normal_appearance_type'],
            'selected_appearance_object' => $appearanceSummary['selected_appearance_object'],
            'stale_appearance_state' => $appearanceSummary['stale_appearance_state'],
            'appearance_value_used_for_import' => false,
            'executes_appearance_streams' => false,
            'renders_appearance' => false,
            'executes_action' => false,
            'action_count' => count($actionReview['actions']),
            'additional_action_count' => count($actionReview['additional_actions']),
            'actions_are_review_only' => true,
            'current_page_annotation' => true,
            'detached_field_only' => false,
        ];

        $appearanceCharacteristics = $this->widgetAppearanceCharacteristics($body, $objects);
        if ($appearanceCharacteristics !== null) {
            $review['appearance_characteristics'] = $appearanceCharacteristics;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function parentFieldObjects(string $body, array $objects): array
    {
        $parents = [];
        $seen = [];
        $parentObject = $this->objectReferenceValueAfterName($body, 'Parent');
        while ($parentObject !== null && isset($objects[$parentObject]) && !isset($seen[$parentObject])) {
            $seen[$parentObject] = true;
            $parents[] = $parentObject;
            $parentBody = $this->dictionaryObjectBody($objects[$parentObject] ?? '');
            if ($parentBody === null) {
                break;
            }

            $parentObject = $this->objectReferenceValueAfterName($parentBody, 'Parent');
        }

        return $parents;
    }

    /**
     * @param array<int, string> $parentBodies
     */
    private function widgetFieldName(string $body, array $parentBodies, array $objects): ?string
    {
        $parts = [];
        foreach (array_reverse($parentBodies, true) as $parentBody) {
            $name = $this->pdfStringValueAfterName($parentBody, 'T', $objects);
            if ($name !== null && $name !== '') {
                $parts[] = $name;
            }
        }

        if ($parts === []) {
            $name = $this->pdfStringValueAfterName($body, 'T', $objects);
            if ($name !== null && $name !== '') {
                $parts[] = $name;
            }
        }

        return $parts === [] ? null : implode('.', $parts);
    }

    private function widgetDictionaryHasFieldKeys(string $body): bool
    {
        foreach (['FT', 'T', 'TM', 'V', 'DV', 'Ff', 'Kids'] as $name) {
            if ($this->valueAfterName($body, $name) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{appearance_states: list<string>, normal_appearance_type: string|null, selected_appearance_object: int|null, stale_appearance_state: bool|null}
     */
    private function widgetAppearanceSummary(?array $appearance, ?string $appearanceState): array
    {
        $summary = [
            'appearance_states' => [],
            'normal_appearance_type' => null,
            'selected_appearance_object' => null,
            'stale_appearance_state' => null,
        ];

        $normal = is_array($appearance['normal'] ?? null) ? $appearance['normal'] : null;
        if ($normal === null) {
            return $summary;
        }

        if (($normal['kind'] ?? null) === 'state-dictionary') {
            $selected = is_array($normal['selected'] ?? null) ? $normal['selected'] : null;
            return [
                'appearance_states' => array_values(array_filter(
                    $normal['states'] ?? [],
                    static fn (mixed $state): bool => is_string($state)
                )),
                'normal_appearance_type' => 'state-dictionary',
                'selected_appearance_object' => $selected['object'] ?? null,
                'stale_appearance_state' => $appearanceState === null ? null : $selected === null,
            ];
        }

        if (($normal['kind'] ?? null) === 'stream') {
            return [
                'appearance_states' => [],
                'normal_appearance_type' => 'direct_stream',
                'selected_appearance_object' => $normal['object'] ?? null,
                'stale_appearance_state' => null,
            ];
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function widgetAppearanceCharacteristics(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'MK') ?? $this->valueAfterName($body, 'MK');
        if ($value === null) {
            return null;
        }

        $mk = $this->resolvedDictionaryFromValue($value, $objects);
        if ($mk === null) {
            return null;
        }

        $textPosition = $this->intValueAfterName($mk['body'], 'TP', $objects);
        $iconFit = $this->widgetIconFitFromAppearanceCharacteristics($mk['body'], $objects);

        $review = [
            'source' => 'widget_mk_appearance_characteristics',
            'dictionary_object' => $mk['object'],
            'rotation' => $this->intValueAfterName($mk['body'], 'R', $objects),
            'border_color' => $this->colorValueAfterName($mk['body'], 'BC', $objects),
            'background_color' => $this->colorValueAfterName($mk['body'], 'BG', $objects),
            'normal_caption' => $this->pdfStringValueAfterName($mk['body'], 'CA', $objects),
            'rollover_caption' => $this->pdfStringValueAfterName($mk['body'], 'RC', $objects),
            'alternate_caption' => $this->pdfStringValueAfterName($mk['body'], 'AC', $objects),
            'text_position' => $textPosition,
            'text_position_label' => $textPosition === null ? null : (self::WIDGET_TEXT_POSITION_LABELS[$textPosition] ?? 'unknown'),
            'icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'I')),
            'rollover_icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'RI')),
            'alternate_icon_object' => $this->objectReferenceFromValue($this->dictionaryRawValue($mk['body'], 'IX')),
            'icon_fit' => $iconFit,
            'renders_appearance' => false,
            'executes_action' => false,
        ];

        return array_filter($review, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function widgetIconFitFromAppearanceCharacteristics(string $mkBody, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($mkBody, 'IF') ?? $this->valueAfterName($mkBody, 'IF');
        if ($value === null) {
            return null;
        }

        $iconFit = $this->resolvedDictionaryFromValue($value, $objects);
        if ($iconFit === null) {
            return null;
        }

        return array_filter([
            'scale_when' => $this->pdfNameValueAfterName($iconFit['body'], 'SW'),
            'scale_type' => $this->pdfNameValueAfterName($iconFit['body'], 'S'),
            'position' => $this->numberArrayValueAfterName($iconFit['body'], 'A', $objects),
            'fit_bounds' => $this->boolValueAfterName($iconFit['body'], 'FB'),
            'renders_icon' => false,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param list<string> $bodies
     * @param array<int, string> $objects
     */
    private function pdfValueToStringFromFirst(array $bodies, string $name, array $objects): ?string
    {
        foreach ($bodies as $body) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            return $this->pdfValueToString($value, $objects);
        }

        return null;
    }

    /**
     * @param list<string> $bodies
     * @param array<int, string> $objects
     */
    private function intValueFromFirst(array $bodies, string $name, array $objects): ?int
    {
        foreach ($bodies as $body) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            return $this->intValueFromPdfValue($value, $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function intValueFromPdfValue(string $value, array $objects): ?int
    {
        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->intValueFromPdfValue($objectBody, $objects);
        }

        return preg_match('/^[+-]?\d+/', $value, $match) === 1 ? (int) $match[0] : null;
    }

    /**
     * @return list<string>
     */
    private function fieldFlagNames(int $flags, ?string $fieldType): array
    {
        $names = [];
        foreach (self::COMMON_FIELD_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        foreach (self::FIELD_TYPE_FLAGS[$fieldType ?? ''] ?? [] as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function fieldTypeLabel(?string $fieldType): ?string
    {
        return match ($fieldType) {
            'Btn' => 'button',
            'Tx' => 'text',
            'Ch' => 'choice',
            'Sig' => 'signature',
            null => null,
            default => strtolower($fieldType),
        };
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationFlagsHideWidget(int $flags): bool
    {
        return $this->hasFlagBit($flags, 1)
            || $this->hasFlagBit($flags, 2)
            || $this->hasFlagBit($flags, 6);
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
    }

    private function hasFlagBit(int $flags, int $bit): bool
    {
        return ($flags & (1 << ($bit - 1))) !== 0;
    }

    private function objectReferenceFromValue(?string $value): ?int
    {
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'AP') ?? $this->valueAfterName($body, 'AP');
        if ($value === null) {
            return null;
        }

        $appearance = $this->resolvedDictionaryFromValue($value, $objects);
        if ($appearance === null) {
            return null;
        }

        $selectedState = $this->pdfNameValueAfterName($body, 'AS');
        $details = [
            'dictionary_object' => $appearance['object'],
            'renders_appearance' => false,
            'executes_actions' => false,
        ];

        foreach (['N' => 'normal', 'R' => 'rollover', 'D' => 'down'] as $pdfName => $key) {
            $entryValue = $this->dictionaryRawValue($appearance['body'], $pdfName);
            if ($entryValue === null) {
                continue;
            }

            $entry = $this->appearanceEntryFromValue($entryValue, $objects, $selectedState);
            if ($entry !== null) {
                $details[$key] = $entry;
            }
        }

        return count($details) > 3 ? $details : null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceEntryFromValue(string $value, array $objects, ?string $selectedState): ?array
    {
        $record = $this->resolvedDictionaryFromValue($value, $objects);
        if ($record === null) {
            return null;
        }

        if ($this->recordLooksLikeStream($record, $objects)) {
            return $this->appearanceStreamSummary($record, $objects);
        }

        $states = [];
        $appearances = [];
        foreach ($this->dictionaryEntries($record['body']) as $entry) {
            $summary = $this->appearanceStreamSummaryFromValue($entry['value'], $objects);
            if ($summary === null) {
                continue;
            }

            $states[] = $entry['name'];
            $appearances[$entry['name']] = $summary;
        }

        if ($appearances === []) {
            return null;
        }

        $selected = $selectedState !== null && isset($appearances[$selectedState])
            ? $appearances[$selectedState]
            : null;

        return [
            'kind' => 'state-dictionary',
            'states' => $states,
            'selected_state' => $selected === null ? null : $selectedState,
            'selected' => $selected,
            'appearances' => $appearances,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceStreamSummaryFromValue(string $value, array $objects): ?array
    {
        $record = $this->resolvedDictionaryFromValue($value, $objects);
        if ($record === null || !$this->recordLooksLikeStream($record, $objects)) {
            return null;
        }

        return $this->appearanceStreamSummary($record, $objects);
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function appearanceStreamSummary(array $record, array $objects): array
    {
        $filters = $this->nameArrayValueAfterName($record['body'], 'Filter', $objects) ?? [];
        $summary = [
            'kind' => 'stream',
            'object' => $record['object'],
            'type' => $this->pdfNameValueAfterName($record['body'], 'Type'),
            'subtype' => $this->pdfNameValueAfterName($record['body'], 'Subtype'),
            'bbox' => $this->numberArrayValueAfterName($record['body'], 'BBox', $objects),
            'matrix' => $this->numberArrayValueAfterName($record['body'], 'Matrix', $objects),
            'resource_keys' => $this->resourceKeysFromAppearanceStream($record['body'], $objects),
            'declared_length' => $this->intValueAfterName($record['body'], 'Length', $objects),
            'filters' => $filters,
            'has_stream_payload' => $this->recordLooksLikeStream($record, $objects),
            'payload_text_visible' => false,
        ];

        return array_filter($summary, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function resourceKeysFromAppearanceStream(string $streamDictionary, array $objects): array
    {
        $value = $this->dictionaryRawValue($streamDictionary, 'Resources');
        if ($value === null) {
            return [];
        }

        $resources = $this->resolvedDictionaryFromValue($value, $objects);
        if ($resources === null) {
            return [];
        }

        return array_values(array_map(
            static fn (array $entry): string => $entry['name'],
            $this->dictionaryEntries($resources['body'])
        ));
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     */
    private function recordLooksLikeStream(array $record, array $objects): bool
    {
        if ($record['object'] !== null && str_contains($objects[$record['object']] ?? '', 'stream')) {
            return true;
        }

        return $this->pdfNameValueAfterName($record['body'], 'Subtype') === 'Form'
            || $this->intValueAfterName($record['body'], 'Length', $objects) !== null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function soundFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->dictionaryRawValue($body, 'Sound');
        if ($value === null) {
            return null;
        }

        $sound = $this->resolvedDictionaryFromValue($value, $objects);
        if ($sound === null) {
            return null;
        }

        return [
            'stream_object' => $sound['object'],
            'icon_name' => $this->pdfNameValueAfterName($body, 'Name'),
            'sample_rate' => $this->floatValueAfterName($sound['body'], 'R', $objects),
            'channels' => $this->intValueAfterName($sound['body'], 'C', $objects) ?? 1,
            'bits_per_sample' => $this->intValueAfterName($sound['body'], 'B', $objects) ?? 8,
            'encoding' => $this->pdfNameValueAfterName($sound['body'], 'E') ?? 'Raw',
            'compression' => $this->pdfNameValueAfterName($sound['body'], 'CO'),
            'payload_length' => $this->intValueAfterName($sound['body'], 'Length', $objects),
            'filters' => $this->nameArrayValueAfterName($sound['body'], 'Filter', $objects) ?? [],
            'plays_on_import' => false,
            'payload_text_visible' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationRecordsForPage(int $pageObjectNumber, ?int $pageGeneration, string $pageBody, array $objects): array
    {
        $annots = $this->pageDictionaryValueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationRecordsFromValue($annots, $objects, $pageObjectNumber, $pageGeneration);
    }

    private function pageDictionaryValueAfterName(string $pageBody, string $name): ?string
    {
        $dictionary = $this->dictionaryObjectBody($pageBody);
        if ($dictionary !== null) {
            if ($name === 'Annots' && $this->dictionaryValueHasTrailingOperand($dictionary, $name)) {
                return null;
            }

            return $this->lastDictionaryRawValue($dictionary, $name);
        }

        return $this->valueAfterName($pageBody, $name);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationRecordsFromValue(
        string $value,
        array $objects,
        int $pageObjectNumber,
        ?int $pageGeneration,
        int $depth = 0,
        array $seen = []
    ): array
    {
        if ($depth > 12) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            if ($this->arrayValueHasTrailingOperand($value)) {
                return [];
            }

            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody($body, $objects, $pageObjectNumber, $pageGeneration, $depth, $seen);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null || !$this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration, $objects)
                ? []
                : [['body' => $dictionary, 'object' => null, 'generation' => null]];
        }

        $reference = $this->objectReferenceWithGenerationFromValue($value);
        if ($reference === null) {
            return [];
        }

        $referenceKey = $reference['object'] . ':' . $reference['generation'];
        if (isset($seen[$referenceKey])) {
            return [];
        }
        $seen[$referenceKey] = true;

        $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
        if ($objectBody === null) {
            return [];
        }

        $trimmedObjectBody = trim($objectBody);
        if ($this->topLevelValueHasTrailingOperand($trimmedObjectBody)) {
            return [];
        }

        if (str_starts_with($trimmedObjectBody, '[')) {
            if ($this->arrayValueHasTrailingOperand($trimmedObjectBody)) {
                return [];
            }

            $body = $this->arrayBodyFromValue($trimmedObjectBody);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody(
                $body,
                $objects,
                $pageObjectNumber,
                $pageGeneration,
                $depth + 1,
                $seen
            );
        }

        if ($this->exactObjectReferenceWithGenerationFromValue($trimmedObjectBody) !== null) {
            return $this->annotationRecordsFromValue(
                $trimmedObjectBody,
                $objects,
                $pageObjectNumber,
                $pageGeneration,
                $depth + 1,
                $seen
            );
        }

        $dictionary = $this->dictionaryObjectBody($trimmedObjectBody);
        return $dictionary === null || !$this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration, $objects) ? [] : [[
            'body' => $dictionary,
            'object' => $reference['object'],
            'generation' => $reference['generation'],
        ]];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationRecordsFromArrayBody(
        string $body,
        array $objects,
        int $pageObjectNumber,
        ?int $pageGeneration,
        int $depth = 0,
        array $seen = []
    ): array
    {
        if ($depth > 12) {
            return [];
        }

        $records = [];
        $emittedAnnotationReferences = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespaceAndComments($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $endOffset = null;
            $value = $this->valueStartingAtOffsetWithEnd($body, $offset, $endOffset);
            if ($value === null || $endOffset === null || $endOffset <= $offset) {
                $offset++;
                continue;
            }

            $value = trim($value);
            if (str_starts_with($value, '<<')) {
                $dictionary = $this->readPdfDictionaryAt($value, 0);
                if ($dictionary !== null && $this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration, $objects)) {
                    $this->appendAnnotationRecordIfNew(
                        $records,
                        ['body' => $dictionary, 'object' => null, 'generation' => null],
                        $emittedAnnotationReferences
                    );
                }
                $offset = $endOffset;
                continue;
            }

            $reference = $this->exactObjectReferenceWithGenerationFromValue($value);
            if ($reference !== null) {
                foreach ($this->annotationRecordsFromValue(
                    $value,
                    $objects,
                    $pageObjectNumber,
                    $pageGeneration,
                    $depth + 1,
                    $seen
                ) as $record) {
                    $this->appendAnnotationRecordIfNew($records, $record, $emittedAnnotationReferences);
                }
                $offset = $endOffset;
                continue;
            }

            $offset = $endOffset;
        }

        return $records;
    }

    /**
     * @param list<array{body: string, object: int|null, generation?: int|null}> $records
     * @param array{body: string, object: int|null, generation?: int|null} $record
     * @param array<string, true> $emittedAnnotationReferences
     */
    private function appendAnnotationRecordIfNew(array &$records, array $record, array &$emittedAnnotationReferences): void
    {
        $object = $record['object'] ?? null;
        $generation = $record['generation'] ?? null;
        if (is_int($object) && is_int($generation)) {
            $referenceKey = $this->annotationReferenceKey($object, $generation);
            if (isset($emittedAnnotationReferences[$referenceKey])) {
                return;
            }

            $emittedAnnotationReferences[$referenceKey] = true;
        }

        $records[] = $record;
    }

    /**
     * @param array<int, string> $objects
     */
    private function annotationBelongsToPage(string $annotationBody, int $pageObjectNumber, ?int $pageGeneration, array $objects = []): bool
    {
        $annotationType = $this->pdfNameValueAfterName($annotationBody, 'Type', $objects);
        if ($annotationType !== null && $annotationType !== 'Annot') {
            return false;
        }

        $value = $this->lastDictionaryRawValue($annotationBody, 'P');
        if ($value !== null && $this->dictionaryValueHasTrailingOperand($annotationBody, 'P')) {
            return false;
        }

        $annotationPageReference = $value === null ? null : $this->objectReferenceWithGenerationFromValue($value);

        if ($annotationPageReference === null) {
            return true;
        }

        if ($annotationPageReference['object'] !== $pageObjectNumber) {
            return false;
        }

        return $pageGeneration === null || $annotationPageReference['generation'] === $pageGeneration;
    }

    /**
     * @param list<array{body: string, object: int|null}> $records
     * @param array<int, string> $objects
     * @return array<int, array{body: string, object: int|null}>
     */
    private function popupRecordsByParentObject(array $records, array $objects): array
    {
        $popups = [];
        foreach ($records as $record) {
            if ($this->subtypeFromAnnotation($record['body'], $objects) !== 'Popup') {
                continue;
            }

            $parentObject = $this->objectReferenceValueAfterName($record['body'], 'Parent');
            if ($parentObject === null || isset($popups[$parentObject])) {
                continue;
            }

            $popups[$parentObject] = $record;
        }

        return $popups;
    }

    /**
     * @param array<int, string> $objects
     */
    private function subtypeFromAnnotation(string $body, array $objects = []): string
    {
        return $this->pdfNameValueAfterName($body, 'Subtype', $objects) ?? 'Unknown';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>|null
     */
    private function popupFromAnnotation(string $body, array $objects, ?int $annotationObject, array $reversePopups): ?array
    {
        $record = null;
        $popup = $this->valueAfterName($body, 'Popup');
        if ($popup !== null) {
            $record = $this->resolvedDictionaryFromValue($popup, $objects);
        }

        if ($record === null && $annotationObject !== null) {
            $record = $reversePopups[$annotationObject] ?? null;
        }

        if ($record === null) {
            return null;
        }

        return [
            'object' => $record['object'],
            'rect' => $this->rectFromAnnotation($record['body']),
            'open' => $this->boolValueAfterName($record['body'], 'Open'),
            'parent_object' => $this->objectReferenceValueAfterName($record['body'], 'Parent'),
            'contents' => $this->pdfStringValueAfterName($record['body'], 'Contents', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function borderFromAnnotation(string $body, array $objects): ?array
    {
        $bs = $this->valueAfterName($body, 'BS');
        if ($bs !== null) {
            if (
                $this->dictionaryValueHasTrailingOperand($body, 'BS')
                || $this->resolvedValueHasTrailingOperand($bs, $objects)
            ) {
                return null;
            }

            $dictionary = $this->resolvedDictionaryFromValue($bs, $objects);
            if ($dictionary !== null) {
                $width = $this->floatValueAfterName($dictionary['body'], 'W', $objects) ?? 1.0;
                $styleCode = $this->pdfNameValueAfterName($dictionary['body'], 'S', $objects) ?? 'S';
                $dashPattern = $this->numberArrayValueAfterName($dictionary['body'], 'D', $objects);

                return [
                    'source' => 'BS',
                    'width' => $width,
                    'style' => $width <= 0.0 ? 'none' : (self::BORDER_STYLE_NAMES[$styleCode] ?? strtolower($styleCode)),
                    'style_code' => $styleCode,
                    'dash_pattern' => $dashPattern,
                    'horizontal_corner_radius' => null,
                    'vertical_corner_radius' => null,
                ];
            }
        }

        $border = $this->valueAfterName($body, 'Border');
        if ($border === null) {
            return null;
        }
        if (
            $this->dictionaryValueHasTrailingOperand($body, 'Border')
            || $this->resolvedValueHasTrailingOperand($border, $objects)
        ) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($border, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = $this->dashPatternFromBorderArrayBody($arrayBody);
        $width = (float) $numbers[2];

        return [
            'source' => 'Border',
            'width' => $width,
            'style' => $width <= 0.0 ? 'none' : ($dashPattern === [] ? 'solid' : 'dashed'),
            'style_code' => $dashPattern === [] ? 'S' : 'D',
            'dash_pattern' => $dashPattern,
            'horizontal_corner_radius' => (float) $numbers[0],
            'vertical_corner_radius' => (float) $numbers[1],
        ];
    }

    /**
     * @return list<float>
     */
    private function dashPatternFromBorderArrayBody(string $arrayBody): array
    {
        $offset = 0;
        while (($start = strpos($arrayBody, '[', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }

            return $this->numbersFromPdfArray($body);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array{space: string, components: list<float>, hex: string|null}|null
     */
    private function colorValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }
        if (
            $this->dictionaryValueHasTrailingOperand($body, $name)
            || $this->resolvedValueHasTrailingOperand($value, $objects)
        ) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $components = array_map(fn (float $component): float => $this->clamp($component), $this->numbersFromPdfArray($arrayBody));
        $count = count($components);
        if ($count === 0) {
            return [
                'space' => 'transparent',
                'components' => [],
                'hex' => null,
            ];
        }

        if ($count === 1) {
            $rgb = [$components[0], $components[0], $components[0]];

            return [
                'space' => 'DeviceGray',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        if ($count === 3) {
            return [
                'space' => 'DeviceRGB',
                'components' => $components,
                'hex' => $this->rgbHex($components),
            ];
        }

        if ($count === 4) {
            [$c, $m, $y, $k] = $components;
            $rgb = [
                (1.0 - $c) * (1.0 - $k),
                (1.0 - $m) * (1.0 - $k),
                (1.0 - $y) * (1.0 - $k),
            ];

            return [
                'space' => 'DeviceCMYK',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        return [
            'space' => 'DeviceN',
            'components' => $components,
            'hex' => null,
        ];
    }

    private function opacityFromAnnotation(string $body, array $objects): ?float
    {
        $value = $this->valueAfterName($body, 'CA');
        if (
            $value !== null
            && (
                $this->dictionaryValueHasTrailingOperand($body, 'CA')
                || $this->resolvedValueHasTrailingOperand($value, $objects)
            )
        ) {
            return null;
        }

        $opacity = $this->floatValueAfterName($body, 'CA', $objects);

        return $opacity === null ? null : $this->clamp($opacity);
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function geometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        return match ($subtype) {
            'Line' => $this->lineGeometryFromAnnotation($body, $objects),
            'Ink' => $this->inkGeometryFromAnnotation($body, $objects),
            'Polygon', 'PolyLine' => $this->verticesGeometryFromAnnotation($body, $objects, $subtype),
            'Square', 'Circle' => $this->rectShapeGeometryFromAnnotation($body, $objects, $subtype, $rect),
            'FreeText' => $this->freeTextGeometryFromAnnotation($body, $objects, $rect),
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function lineGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $points = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'L', $objects), 2);
        if (count($points) !== 2) {
            return null;
        }

        return [
            'type' => 'line',
            'points' => $points,
            'bbox' => $this->bboxFromPoints($points),
            'line_endings' => $this->nameArrayValueAfterName($body, 'LE', $objects),
            'leader_line_length' => $this->floatValueAfterName($body, 'LL', $objects),
            'leader_line_extension' => $this->floatValueAfterName($body, 'LLE', $objects),
            'caption' => $this->boolValueAfterName($body, 'Cap'),
            'intent' => $this->pdfNameValueAfterName($body, 'IT'),
            'caption_offset' => $this->numberArrayValueAfterName($body, 'CO', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function inkGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->valueAfterName($body, 'InkList');
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $paths = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $start = strpos($arrayBody, '[', $offset);
            if ($start === false) {
                break;
            }

            $endOffset = null;
            $pathBody = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($pathBody === null || $endOffset === null) {
                break;
            }

            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($pathBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
            $offset = $endOffset;
        }

        if ($paths === []) {
            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($arrayBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
        }

        if ($paths === []) {
            return null;
        }

        $pathBboxes = array_map(fn (array $path): array => $this->bboxFromPoints($path), $paths);

        return [
            'type' => 'ink',
            'paths' => $paths,
            'path_bboxes' => $pathBboxes,
            'bbox' => $this->unionRects($pathBboxes),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function verticesGeometryFromAnnotation(string $body, array $objects, string $subtype): ?array
    {
        $vertices = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'Vertices', $objects));
        if (count($vertices) < 2) {
            return null;
        }

        $geometry = [
            'type' => $subtype === 'Polygon' ? 'polygon' : 'polyline',
            'vertices' => $vertices,
            'bbox' => $this->bboxFromPoints($vertices),
            'closed' => $subtype === 'Polygon',
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function rectShapeGeometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        if ($rect === null) {
            return null;
        }

        $rectDifference = $this->rectDifferenceFromAnnotation($body, $objects);
        $shapeRect = $rectDifference === null ? $rect : $this->insetRect($rect, $rectDifference);
        $size = [
            max(0.0, $shapeRect[2] - $shapeRect[0]),
            max(0.0, $shapeRect[3] - $shapeRect[1]),
        ];

        $geometry = [
            'type' => strtolower($subtype),
            'rect' => $rect,
            'rect_difference' => $rectDifference,
            'shape_rect' => $shapeRect,
            'center' => [
                ($shapeRect[0] + $shapeRect[2]) / 2.0,
                ($shapeRect[1] + $shapeRect[3]) / 2.0,
            ],
            'size' => $size,
        ];

        if ($subtype === 'Circle') {
            $geometry['radii'] = [$size[0] / 2.0, $size[1] / 2.0];
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function freeTextGeometryFromAnnotation(string $body, array $objects, ?array $rect): ?array
    {
        $calloutLine = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'CL', $objects));
        if (count($calloutLine) < 2) {
            return null;
        }

        $geometry = [
            'type' => 'callout',
            'callout_line' => $calloutLine,
            'bbox' => $this->bboxFromPoints($calloutLine),
            'rect' => $rect,
            'rect_difference' => $this->rectDifferenceFromAnnotation($body, $objects),
            'elbow_point' => count($calloutLine) >= 3 ? $calloutLine[1] : null,
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function rectDifferenceFromAnnotation(string $body, array $objects): ?array
    {
        $numbers = $this->numberArrayValueAfterName($body, 'RD', $objects);
        return $numbers !== null && count($numbers) >= 4 ? array_slice($numbers, 0, 4) : null;
    }

    /**
     * @param list<float> $rect
     * @param list<float> $difference
     * @return list<float>
     */
    private function insetRect(array $rect, array $difference): array
    {
        $shapeRect = [
            $rect[0] + $difference[0],
            $rect[1] + $difference[1],
            $rect[2] - $difference[2],
            $rect[3] - $difference[3],
        ];

        return $this->normalizeRect($shapeRect);
    }

    /**
     * @param list<float>|null $numbers
     * @return list<array{0: float, 1: float}>
     */
    private function pointsFromNumberArray(?array $numbers, ?int $limit = null): array
    {
        if ($numbers === null) {
            return [];
        }

        $points = [];
        for ($index = 0, $count = count($numbers) - 1; $index < $count; $index += 2) {
            $points[] = [(float) $numbers[$index], (float) $numbers[$index + 1]];
            if ($limit !== null && count($points) >= $limit) {
                break;
            }
        }

        return $points;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<float>
     */
    private function bboxFromPoints(array $points): array
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * @param list<list<float>> $rects
     * @return list<float>
     */
    private function unionRects(array $rects): array
    {
        return [
            min(array_column($rects, 0)),
            min(array_column($rects, 1)),
            max(array_column($rects, 2)),
            max(array_column($rects, 3)),
        ];
    }

    /**
     * @param list<float> $components
     */
    private function rgbHex(array $components): string
    {
        $parts = [];
        foreach (array_slice($components, 0, 3) as $component) {
            $parts[] = str_pad(dechex((int) round($this->clamp($component) * 255)), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode('', $parts);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody, array $objects = []): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null) {
            return null;
        }
        if (
            $this->dictionaryValueHasTrailingOperand($annotationBody, 'Rect')
            || $this->resolvedValueHasTrailingOperand($value, $objects)
        ) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->fixedNumericArrayPrefix($arrayBody, 4, $objects);
        if ($numbers === null) {
            return null;
        }

        return $this->normalizeRect($numbers);
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function fixedNumericArrayPrefix(string $arrayBody, int $expectedCount, array $objects = []): ?array
    {
        $numbers = [];
        $offset = 0;
        $length = strlen($arrayBody);

        while (count($numbers) < $expectedCount) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                return null;
            }

            $numberEnd = null;
            $number = $this->numericArrayElementAtOffset($arrayBody, $offset, $objects, $numberEnd);
            if ($numberEnd === null || $numberEnd <= $offset || $number === null) {
                return null;
            }

            $numbers[] = $number;
            $offset = $numberEnd;
        }

        $this->skipWhitespaceAndComments($arrayBody, $offset);
        if ($offset < $length) {
            return null;
        }

        return $numbers;
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function numberArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        return $arrayBody === null ? null : $this->numbersFromPdfArray($arrayBody, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>|null
     */
    private function nameArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody !== null) {
            if (!preg_match_all('/\/([^\s\[\]<>()\/%]+)/', $arrayBody, $matches)) {
                return [];
            }

            return array_map(fn (string $name): string => $this->decodePdfName($name), $matches[0]);
        }

        $value = trim($value);
        if (str_starts_with($value, '/')) {
            return [$this->decodePdfName($value)];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function floatValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->floatFromPdfValue($objectBody);
        }

        return $this->floatFromPdfValue($value);
    }

    private function floatFromPdfValue(string $value): ?float
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) === 1 ? (float) $match[0] : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function intValueAfterName(string $body, string $name, array $objects): ?int
    {
        $rawValue = $this->valueAfterName($body, $name);
        if ($rawValue === null) {
            return null;
        }
        if (
            $this->dictionaryValueHasTrailingOperand($body, $name)
            || $this->resolvedValueHasTrailingOperand($rawValue, $objects)
        ) {
            return null;
        }

        $value = $this->floatValueAfterName($body, $name, $objects);

        return $value === null ? null : (int) $value;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }
        if (
            $this->dictionaryValueHasTrailingOperand($body, $name)
            || $this->resolvedValueHasTrailingOperand($value, $objects)
        ) {
            return null;
        }

        return $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        return $value === 'null' ? null : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfNameValueAfterName(string $body, string $name, array $objects = []): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }
        if ($this->dictionaryValueHasTrailingOperand($body, $name) || $this->resolvedValueHasTrailingOperand($value, $objects)) {
            return null;
        }

        $trimmed = trim($value);
        $reference = $this->objectReferenceWithGenerationFromValue($trimmed);
        if ($reference !== null) {
            $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
            if ($objectBody !== null) {
                $trimmed = trim($objectBody);
            }
        }

        if (!str_starts_with($trimmed, '/')) {
            return null;
        }

        $nameEnd = $this->skipPdfName($trimmed, 0);

        return $this->decodePdfName(substr($trimmed, 0, $nameEnd));
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);

        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        $value = $this->lastDictionaryRawValue($body, $name);
        if ($value !== null) {
            return $value;
        }

        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffset($body, $offset);
    }

    private function valueStartingAtOffset(string $body, int $offset): ?string
    {
        return $this->valueStartingAtOffsetWithEnd($body, $offset);
    }

    private function valueStartingAtOffsetWithEnd(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespaceAndComments($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $referenceEndOffset = null;
        $reference = $this->objectReferenceStartingAtOffset($body, $offset, $referenceEndOffset);
        if ($reference !== null && $referenceEndOffset !== null) {
            $endOffset = $referenceEndOffset;
            return $reference;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $arrayEndOffset = null;
            $this->readPdfArrayAt($body, $offset, $arrayEndOffset);
            $endOffset = $arrayEndOffset;
            return $arrayEndOffset === null ? null : substr($body, $offset, $arrayEndOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $dictionaryEndOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $dictionaryEndOffset);
            $endOffset = $dictionaryEndOffset;
            return $dictionaryEndOffset === null ? null : substr($body, $offset, $dictionaryEndOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $literalEndOffset = $this->skipLiteralString($body, $offset);
            $endOffset = $literalEndOffset;
            return substr($body, $offset, $literalEndOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $hexEndOffset = $this->skipHexString($body, $offset);
            $endOffset = $hexEndOffset;
            return substr($body, $offset, $hexEndOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $nameEndOffset = $this->skipPdfName($body, $offset);
            $endOffset = $nameEndOffset;
            return substr($body, $offset, $nameEndOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        $endOffset = $end;
        return substr($body, $offset, max(0, $end - $offset));
    }

    private function objectReferenceStartingAtOffset(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        if (preg_match('/\G\d+/', $body, $objectMatch, 0, $offset) !== 1) {
            return null;
        }

        $cursor = $offset + strlen($objectMatch[0]);
        $beforeGap = $cursor;
        $this->skipWhitespaceAndComments($body, $cursor);
        if ($cursor === $beforeGap) {
            return null;
        }

        if (preg_match('/\G\d+/', $body, $generationMatch, 0, $cursor) !== 1) {
            return null;
        }

        $cursor += strlen($generationMatch[0]);
        $beforeGap = $cursor;
        $this->skipWhitespaceAndComments($body, $cursor);
        if ($cursor === $beforeGap) {
            return null;
        }

        if (($body[$cursor] ?? '') !== 'R') {
            return null;
        }

        $afterReference = $cursor + 1;
        $next = $body[$afterReference] ?? '';
        if ($next !== '' && !ctype_space($next) && !str_contains('[]()<>{}/%', $next)) {
            return null;
        }

        $endOffset = $afterReference;
        return $objectMatch[0] . ' ' . $generationMatch[0] . ' R';
    }

    private function dictionaryRawValue(string $body, string $name): ?string
    {
        foreach ($this->dictionaryEntries($body) as $entry) {
            if ($entry['name'] === $name) {
                return $entry['value'];
            }
        }

        return null;
    }

    private function lastDictionaryRawValue(string $body, string $name): ?string
    {
        $selected = null;
        foreach ($this->dictionaryEntries($body) as $entry) {
            if ($entry['name'] === $name) {
                $selected = $entry['value'];
            }
        }

        return $selected;
    }

    private function arrayValueHasTrailingOperand(string $value): bool
    {
        $offset = 0;
        $this->skipWhitespaceAndComments($value, $offset);
        if (($value[$offset] ?? '') !== '[') {
            return false;
        }

        $endOffset = null;
        $this->readPdfArrayAt($value, $offset, $endOffset);
        if ($endOffset === null) {
            return false;
        }

        $tailOffset = $endOffset;
        $this->skipWhitespaceAndComments($value, $tailOffset);

        return $tailOffset < strlen($value);
    }

    private function dictionaryValueHasTrailingOperand(string $body, string $name): bool
    {
        $dictionary = str_starts_with(ltrim($body), '<<') ? $this->dictionaryObjectBody($body) : null;
        $dictionary ??= $body;

        $selectedMalformed = false;
        $offset = 0;
        $length = strlen($dictionary);

        while ($offset < $length) {
            $this->skipWhitespaceAndComments($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionary[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($dictionary, $offset);
            $key = $this->decodePdfName(substr($dictionary, $offset + 1, $nameEnd - $offset - 1));
            $valueEnd = null;
            $value = $this->valueStartingAtOffsetWithEnd($dictionary, $nameEnd, $valueEnd);
            if ($value === null || $valueEnd === null || $valueEnd <= $nameEnd) {
                $offset = max($nameEnd, $offset + 1);
                continue;
            }

            if ($key === $name) {
                $tailOffset = $valueEnd;
                $this->skipWhitespaceAndComments($dictionary, $tailOffset);
                $selectedMalformed = $tailOffset < $length && $dictionary[$tailOffset] !== '/';
            }

            $offset = $valueEnd;
        }

        return $selectedMalformed;
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolvedValueHasTrailingOperand(string $value, array $objects): bool
    {
        $trimmed = trim($value);
        $reference = $this->objectReferenceWithGenerationFromValue($trimmed);
        if ($reference !== null) {
            $body = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
            if ($body === null) {
                return false;
            }

            $trimmed = trim($body);
        }

        return $this->topLevelValueHasTrailingOperand($trimmed);
    }

    private function topLevelValueHasTrailingOperand(string $value): bool
    {
        $offset = 0;
        $this->skipWhitespaceAndComments($value, $offset);
        if ($offset >= strlen($value)) {
            return false;
        }

        $endOffset = null;
        if (($value[$offset] ?? '') === '[') {
            $this->readPdfArrayAt($value, $offset, $endOffset);
        } elseif (substr($value, $offset, 2) === '<<') {
            $this->readPdfDictionaryAt($value, $offset, $endOffset);
        } else {
            $this->valueStartingAtOffsetWithEnd($value, $offset, $endOffset);
        }

        if ($endOffset === null || $endOffset <= $offset) {
            return false;
        }

        $tailOffset = $endOffset;
        $this->skipWhitespaceAndComments($value, $tailOffset);

        return $tailOffset < strlen($value);
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function dictionaryEntries(string $body): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespaceAndComments($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($body, $offset);
            $name = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
            $valueEnd = null;
            $value = $this->valueStartingAtOffsetWithEnd($body, $nameEnd, $valueEnd);
            if ($value === null || $valueEnd === null || $valueEnd <= $nameEnd) {
                $offset = max($nameEnd, $offset + 1);
                continue;
            }

            $entries[] = [
                'name' => $name,
                'value' => $value,
            ];
            $offset = $valueEnd;
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        $reference = $this->objectReferenceWithGenerationFromValue($value);
        if ($reference === null) {
            return null;
        }

        $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
        $body = $objectBody === null ? null : $this->dictionaryObjectBody($objectBody);
        return $body === null ? null : ['body' => $body, 'object' => $reference['object']];
    }

    /**
     * @param array<int, string> $objects
     */
    private function arrayBodyFromPdfValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            return $this->arrayBodyFromValue($value);
        }

        $reference = $this->objectReferenceWithGenerationFromValue($value);
        if ($reference !== null) {
            $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
            return $objectBody === null || $objectBody === '' ? null : $this->arrayBodyFromPdfValue($objectBody, $objects);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $this->xrefSelectedGenerationZeroObjects = [];
        $this->xrefStreamSuppressedObjectNumbers = [];
        $definitions = $this->pdfObjectDefinitions($pdfBytes);
        $objects = [];
        if ($definitions === []) {
            return $objects;
        }

        foreach ($definitions as $definition) {
            $objects[$definition['object_id']] = $definition['body'];
        }

        $xrefEntries = $this->xrefEntriesFromLatestStartxref($pdfBytes, $definitions);
        if ($xrefEntries === []) {
            return $objects;
        }

        foreach (array_keys($xrefEntries) as $objectNumber) {
            $this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber] = true;
        }

        $selectedObjects = $this->objectsFromXrefStreamEntries($definitions, $xrefEntries);
        if ($selectedObjects === []) {
            $this->xrefStreamSuppressedObjectNumbers = [];
            return $objects;
        }

        $selectedObjects = $this->withCompressedObjectStreamObjects($selectedObjects, $xrefEntries);
        foreach ($definitions as $definition) {
            if (array_key_exists($definition['object_id'], $xrefEntries)) {
                continue;
            }

            $selectedObjects[$definition['object_id']] = $definition['body'];
        }
        ksort($selectedObjects, SORT_NUMERIC);

        return $selectedObjects;
    }

    /**
     * @return list<array{object_id: int, generation: int, body: string, offset: int, body_start: int, body_end: int}>
     */
    private function pdfObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $definitions[] = [
                'object_id' => (int) $match[1][0],
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
                'offset' => (int) $match[0][1],
                'body_start' => (int) $match[3][1],
                'body_end' => (int) $match[3][1] + strlen($match[3][0]) - 1,
            ];
        }

        return $definitions;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int, body_start: int, body_end: int}> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = PdfClassicXrefRebuilder::startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($section !== null) {
            return $this->xrefStreamEntriesFromSection($section);
        }

        return PdfClassicXrefRebuilder::classicTableEntriesAt($pdfBytes, $offset) ?? [];
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array{dictionary: string, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['offset'] !== $offset) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($definition['body']);
            if ($dictionary === null || $this->pdfNameValueAfterName($dictionary, 'Type') !== 'XRef') {
                return null;
            }

            return [
                'dictionary' => $dictionary,
                'body' => $definition['body'],
            ];
        }

        return null;
    }

    /**
     * @param array{dictionary: string, body: string} $section
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $decoded = $this->decodedStreamBytes($section['body'], $section['dictionary']);
        if ($decoded === null) {
            return [];
        }

        $widths = $this->xrefStreamFieldWidths($section['dictionary']);
        if ($widths === null) {
            return [];
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return [];
        }

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
                        'object_stream' => $fieldTwo,
                        'index' => $fieldThree,
                        'index_is_explicit' => $widths[2] > 0,
                    ];
                    continue;
                }

                $entries[$objectNumber] = [
                    'type' => $type,
                    'generation' => $fieldThree,
                    'offset' => $fieldTwo,
                ];
            }
        }
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function xrefStreamFieldWidths(string $dictionary): ?array
    {
        $arrayBody = $this->arrayBodyValueAfterName($dictionary, 'W');
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $widths = [];
        foreach (array_slice($numbers, 0, 3) as $number) {
            if ($number < 0.0 || abs($number - round($number)) > 0.000001) {
                return null;
            }
            $widths[] = (int) round($number);
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(string $dictionary, int $decodedEntryCount): array
    {
        $arrayBody = $this->arrayBodyValueAfterName($dictionary, 'Index');
        if ($arrayBody === null) {
            $size = $this->directIntegerAfterName($dictionary, 'Size');

            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        $ranges = [];
        $consumed = 0;
        for ($index = 0, $count = count($numbers); $index + 1 < $count; $index += 2) {
            $first = $numbers[$index];
            $rowCount = $numbers[$index + 1];
            if ($first < 0.0 || $rowCount < 0.0 || abs($first - round($first)) > 0.000001 || abs($rowCount - round($rowCount)) > 0.000001) {
                continue;
            }

            $boundedCount = min((int) round($rowCount), max(0, $decodedEntryCount - $consumed));
            $ranges[] = [
                'first' => (int) round($first),
                'count' => $boundedCount,
            ];
            $consumed += $boundedCount;
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
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function objectsFromXrefStreamEntries(array $definitions, array $xrefEntries): array
    {
        $definitionsByOffset = [];
        foreach ($definitions as $definition) {
            $definitionsByOffset[$definition['offset']] = $definition;
        }

        $objects = [];
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1 || !isset($entry['offset'])) {
                continue;
            }

            $definition = $definitionsByOffset[$entry['offset']] ?? null;
            if ($definition === null
                || $definition['object_id'] !== $objectNumber
                || $definition['generation'] !== ($entry['generation'] ?? 0)
            ) {
                continue;
            }

            $objects[$objectNumber] = $definition['body'];
            unset($this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber]);
            if (($entry['generation'] ?? 0) === 0) {
                $this->xrefSelectedGenerationZeroObjects[(int) $objectNumber] = true;
            }
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withCompressedObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        $expanded = $objects;
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 2 || isset($expanded[$objectNumber])) {
                continue;
            }

            $body = $this->objectStreamMemberBody($expanded, $entry, (int) $objectNumber);
            if ($body === null) {
                continue;
            }

            $expanded[$objectNumber] = $body;
            unset($this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber]);
            $this->xrefSelectedGenerationZeroObjects[(int) $objectNumber] = true;
        }
        ksort($expanded, SORT_NUMERIC);

        return $expanded;
    }

    /**
     * @param array<int, string> $objects
     * @param array{type: int, object_stream?: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamMemberBody(array $objects, array $xrefEntry, int $requestedObjectNumber): ?string
    {
        $objectStreamNumber = $xrefEntry['object_stream'] ?? null;
        if (!is_int($objectStreamNumber) || !isset($objects[$objectStreamNumber])) {
            return null;
        }

        $objectStreamBody = $objects[$objectStreamNumber];
        $dictionary = $this->dictionaryObjectBody($objectStreamBody);
        if ($dictionary === null || $this->pdfNameValueAfterName($dictionary, 'Type') !== 'ObjStm') {
            return null;
        }

        $declaredCount = $this->directIntegerAfterName($dictionary, 'N', $objects);
        $firstOffset = $this->directIntegerAfterName($dictionary, 'First', $objects);
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytes($objectStreamBody, $dictionary, $objects);
        if ($decoded === null || $firstOffset > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $firstOffset), $declaredCount);
        if ($members === []) {
            return null;
        }

        $memberIndex = $this->objectStreamSelectedMemberIndex($members, $xrefEntry, $requestedObjectNumber);
        if ($memberIndex === null) {
            return null;
        }

        $data = substr($decoded, $firstOffset);
        $start = $members[$memberIndex]['offset'];
        if ($start < 0 || $start >= strlen($data)) {
            return null;
        }
        if (!$this->objectStreamMemberOffsetHasTokenBoundary($data, $start)) {
            return null;
        }

        $end = strlen($data);
        foreach ($members as $index => $member) {
            if ($index === $memberIndex || $member['offset'] <= $start) {
                continue;
            }
            if (!$this->objectStreamMemberOffsetHasTokenBoundary($data, $member['offset'])) {
                continue;
            }
            $end = min($end, $member['offset']);
        }
        if ($end <= $start) {
            return null;
        }

        return trim(substr($data, $start, $end - $start));
    }

    /**
     * @return list<array{object_id: int, offset: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $declaredCount): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $declaredCount; $index++) {
            $objectNumber = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            if ($objectNumber === null) {
                return [];
            }

            $memberOffset = $this->readObjectStreamHeaderUnsignedInteger($header, $offset);
            if ($memberOffset === null) {
                return [];
            }

            $members[] = [
                'object_id' => $objectNumber,
                'offset' => $memberOffset,
            ];
        }

        $this->skipWhitespaceAndComments($header, $offset);
        if ($offset !== strlen($header)) {
            return [];
        }

        return $members;
    }

    private function readObjectStreamHeaderUnsignedInteger(string $header, int &$offset): ?int
    {
        $this->skipWhitespaceAndComments($header, $offset);
        if (preg_match('/\G\+?(\d+)/s', $header, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);
        if ($offset < strlen($header) && !$this->isObjectStreamHeaderDelimiter($header[$offset])) {
            return null;
        }

        return (int) $match[1];
    }

    private function isObjectStreamHeaderDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param list<array{object_id: int, offset: int}> $members
     * @param array{type: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamSelectedMemberIndex(array $members, array $xrefEntry, int $requestedObjectNumber): ?int
    {
        $requestedIndex = $xrefEntry['index'] ?? null;
        if (is_int($requestedIndex) && ($xrefEntry['index_is_explicit'] ?? true) === true) {
            if (!isset($members[$requestedIndex]) || $members[$requestedIndex]['object_id'] !== $requestedObjectNumber) {
                return null;
            }

            return $requestedIndex;
        }

        foreach ($members as $index => $member) {
            if ($member['object_id'] === $requestedObjectNumber) {
                return $index;
            }
        }

        return null;
    }

    private function objectStreamMemberOffsetHasTokenBoundary(string $data, int $offset): bool
    {
        $length = strlen($data);
        if ($offset < 0 || $offset >= $length) {
            return false;
        }

        if (ctype_space($data[$offset]) || $data[$offset] === '%') {
            return false;
        }

        if ($offset === 0) {
            return true;
        }

        $index = 0;
        while ($index < $offset && $index < $length) {
            $char = $data[$index];
            if ($char === '(') {
                $end = $this->skipLiteralString($data, $index);
                if ($offset < $end || $end === $index) {
                    return false;
                }
                $index = $end;
                continue;
            }

            if ($char === '%') {
                $end = $index;
                while ($end < $length && $data[$end] !== "\n" && $data[$end] !== "\r") {
                    $end++;
                }
                if ($offset < $end || $end === $index) {
                    return false;
                }
                $index = $end;
                continue;
            }

            if ($char === '<' && substr($data, $index, 2) === '<<') {
                $end = null;
                $this->readPdfDictionaryAt($data, $index, $end);
                if ($end !== null) {
                    if ($offset < $end || $end === $index) {
                        return false;
                    }
                    $index = $end;
                    continue;
                }
            }

            if ($char === '<') {
                $end = $this->skipHexString($data, $index);
                if ($offset < $end || $end === $index) {
                    return false;
                }
                $index = $end;
                continue;
            }

            if ($char === '[') {
                $end = null;
                $this->readPdfArrayAt($data, $index, $end);
                if ($end !== null) {
                    if ($offset < $end || $end === $index) {
                        return false;
                    }
                    $index = $end;
                    continue;
                }
            }

            $index++;
        }

        if ($index !== $offset) {
            return false;
        }

        $previous = $data[$offset - 1] ?? '';
        return $previous !== ''
            && (ctype_space($previous) || str_contains('[]()<>{}/%', $previous));
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodedStreamBytes(string $body, string $dictionary, array $objects = []): ?string
    {
        $decoded = $this->streamBytesFromBody($body, $dictionary, $objects);
        if ($decoded === null) {
            return null;
        }

        foreach ($this->filterNamesAfterName($dictionary, 'Filter', $objects) as $filter) {
            $next = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($decoded),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($decoded),
                default => null,
            };
            if ($next === null) {
                return null;
            }
            $decoded = $next;
        }

        return $decoded;
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamBytesFromBody(string $body, string $dictionary, array $objects = []): ?string
    {
        $dictOffset = strpos($body, '<<');
        if ($dictOffset === false) {
            return null;
        }

        $endOffset = null;
        if ($this->readPdfDictionaryAt($body, $dictOffset, $endOffset) === null || $endOffset === null) {
            return null;
        }

        $offset = $endOffset;
        $this->skipWhitespaceAndComments($body, $offset);
        if (substr($body, $offset, strlen('stream')) !== 'stream') {
            return null;
        }

        $start = $offset + strlen('stream');
        if (substr($body, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (($body[$start] ?? '') === "\n" || ($body[$start] ?? '') === "\r") {
            $start++;
        }

        $end = strpos($body, 'endstream', $start);
        if ($end === false || $end < $start) {
            return null;
        }

        $stream = substr($body, $start, $end - $start);
        $length = $this->directIntegerAfterName($dictionary, 'Length', $objects);
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function filterNamesAfterName(string $dictionary, string $name, array $objects = []): array
    {
        $value = $this->valueAfterName($dictionary, $name);
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($objects !== []) {
            $reference = $this->objectReferenceWithGenerationFromValue($trimmed);
            if ($reference !== null) {
                $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
                if ($objectBody !== null) {
                    $trimmed = trim($objectBody);
                }
            }
        }
        if ($trimmed === '') {
            return [];
        }

        if ($trimmed[0] === '/') {
            $endOffset = $this->skipPdfName($trimmed, 0);
            return [$this->decodePdfName(substr($trimmed, 1, $endOffset - 1))];
        }

        if ($trimmed[0] !== '[') {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($trimmed);
        if ($arrayBody === null) {
            return [];
        }

        $filters = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($arrayBody[$offset] ?? '') !== '/') {
                $endOffset = null;
                $value = $this->valueStartingAtOffsetWithEnd($arrayBody, $offset, $endOffset);
                if ($objects !== [] && $value !== null) {
                    $reference = $this->objectReferenceWithGenerationFromValue($value);
                    $objectBody = $reference === null
                        ? null
                        : $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
                    if ($objectBody !== null && str_starts_with(trim($objectBody), '/')) {
                        $filters[] = $this->decodePdfName(trim($objectBody));
                    }
                }
                $offset = $value !== null && $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
                continue;
            }

            $endOffset = $this->skipPdfName($arrayBody, $offset);
            $filters[] = $this->decodePdfName(substr($arrayBody, $offset + 1, $endOffset - $offset - 1));
            $offset = $endOffset;
        }

        return $filters;
    }

    private function arrayBodyValueAfterName(string $dictionary, string $name): ?string
    {
        $value = $this->valueAfterName($dictionary, $name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return str_starts_with($trimmed, '[') ? $this->arrayBodyFromValue($trimmed) : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function directIntegerAfterName(string $body, string $name, array $objects = []): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($objects !== []) {
            $reference = $this->objectReferenceWithGenerationFromValue($trimmed);
            if ($reference !== null) {
                $objectBody = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
                if ($objectBody !== null) {
                    $trimmed = trim($objectBody);
                }
            }
        }
        if (preg_match('/^[+-]?\d+/', $trimmed, $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function decodeFlateStream(string $bytes): ?string
    {
        $decoded = @gzuncompress($bytes);
        if ($decoded === false) {
            $decoded = @gzinflate($bytes);
        }
        if ($decoded === false) {
            $decoded = @gzdecode($bytes);
        }

        return $decoded === false ? null : $decoded;
    }

    private function decodeAsciiHexStream(string $bytes): ?string
    {
        $body = strstr($bytes, '>', true);
        if ($body === false) {
            $body = $bytes;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);

        return $decoded === false ? null : $decoded;
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\s+([+-]?\d+)/s', $pdfBytes, $matches) < 1) {
            return null;
        }

        $offset = (int) end($matches[1]);

        return $offset >= 0 && $offset < strlen($pdfBytes) ? $offset : null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function pdfObjectBodiesByGeneration(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]][(int) $match[2]] = trim($match[3]);
        }

        return $objects;
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function objectReferenceWithGenerationFromValue(string $value): ?array
    {
        if (preg_match('/^(\d+)\s+(\d+)\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function exactObjectReferenceWithGenerationFromValue(string $value): ?array
    {
        if (preg_match('/^(\d+)\s+(\d+)\s+R$/', trim($value), $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForReference(int $objectNumber, int $generation, array $objects): ?string
    {
        if (isset($this->xrefFreeObjectNumbers[$objectNumber])) {
            return null;
        }

        if ($generation === 0 && isset($this->xrefSelectedGenerationZeroObjects[$objectNumber], $objects[$objectNumber])) {
            return trim($objects[$objectNumber]);
        }

        if (isset($this->xrefStreamSuppressedObjectNumbers[$objectNumber])) {
            return null;
        }

        if (array_key_exists($generation, $this->objectBodiesByGeneration[$objectNumber] ?? [])) {
            return $this->objectBodiesByGeneration[$objectNumber][$generation];
        }

        if ($generation === 0 && isset($objects[$objectNumber])) {
            return trim($objects[$objectNumber]);
        }

        return null;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        return array_map(
            static fn (array $reference): int => $reference['object'],
            $this->orderedPageObjectReferences($objects)
        );
    }

    /**
     * @return list<array{object: int, generation: int}>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectReferences(array $objects): array
    {
        foreach ($objects as $body) {
            if ($this->pdfNameValueAfterName($body, 'Type') !== 'Catalog') {
                continue;
            }

            $pagesValue = $this->valueAfterName($body, 'Pages');
            $pagesReference = $pagesValue === null ? null : $this->objectReferenceWithGenerationFromValue($pagesValue);
            if ($pagesReference === null) {
                continue;
            }

            $pages = $this->pageObjectReferencesFromTree($pagesReference['object'], $pagesReference['generation'], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->pdfNameValueAfterName($body, 'Type') === 'Page') {
                $pages[] = ['object' => $objectNumber, 'generation' => 0];
            }
        }

        return $pages;
    }

    /**
     * @return list<array{object: int, generation: int}>
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     */
    private function pageObjectReferencesFromTree(int $objectNumber, int $generation, array $objects, array $seen = []): array
    {
        $key = $objectNumber . ':' . $generation;
        if (isset($seen[$key])) {
            return [];
        }

        $body = $this->objectBodyForReference($objectNumber, $generation, $objects);
        if ($body === null) {
            return [];
        }

        $seen[$key] = true;
        if ($this->pdfNameValueAfterName($body, 'Type') === 'Page') {
            return [['object' => $objectNumber, 'generation' => $generation]];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferenceValues($arrayBody) as $childReference) {
            foreach ($this->pageObjectReferencesFromTree($childReference['object'], $childReference['generation'], $objects, $seen) as $pageReference) {
                $pages[] = $pageReference;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/s', $body) !== 1) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($body);
            if ($dictionary !== null) {
                return $dictionary;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<array{object: int, generation: int}>
     */
    private function objectReferenceValues(string $value): array
    {
        $references = [];
        foreach ($this->arrayItemsFromBody($value) as $item) {
            if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', trim($item), $match) !== 1) {
                continue;
            }

            $references[] = [
                'object' => (int) $match[1],
                'generation' => (int) $match[2],
            ];
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function arrayItemsFromBody(string $body): array
    {
        $items = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $endOffset = null;
            $value = $this->valueStartingAtOffsetWithEnd($body, $offset, $endOffset);
            if ($value === null || $endOffset === null || $endOffset <= $offset) {
                $offset++;
                continue;
            }

            $items[] = $value;
            $offset = $endOffset;
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        return array_map(
            static fn (array $reference): int => $reference['object'],
            $this->objectReferenceValues($value)
        );
    }

    private function objectNumberFromReference(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
    }

    private function skipWhitespaceAndComments(string $body, int &$offset): void
    {
        $length = strlen($body);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            if (($body[$offset] ?? '') !== '%') {
                return;
            }

            while ($offset < $length && $body[$offset] !== "\n" && $body[$offset] !== "\r") {
                $offset++;
            }
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody, array $objects = [], int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        $numbers = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($arrayBody[$offset] === '(') {
                $offset = $this->skipLiteralString($arrayBody, $offset);
                continue;
            }
            if ($arrayBody[$offset] === '<' && substr($arrayBody, $offset, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($arrayBody, $offset, $endDictionary);
                $offset = $endDictionary ?? ($offset + 2);
                continue;
            }
            if ($arrayBody[$offset] === '<') {
                $offset = $this->skipHexString($arrayBody, $offset);
                continue;
            }
            if ($arrayBody[$offset] === '[') {
                $endArray = null;
                $nested = $this->readPdfArrayAt($arrayBody, $offset, $endArray);
                if ($nested !== null && $endArray !== null) {
                    array_push($numbers, ...$this->numbersFromPdfArray($nested, $objects, $depth + 1));
                    $offset = $endArray;
                    continue;
                }
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $objectBody = $this->objectBodyForReference((int) $match[1], (int) $match[2], $objects);
                if ($objectBody !== null && preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', trim($objectBody)) === 1) {
                    $numbers[] = (float) trim($objectBody);
                }
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $match, 0, $offset) === 1) {
                $numbers[] = (float) $match[0];
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $numbers;
    }

    /**
     * @param array<int, string> $objects
     */
    private function numericArrayElementAtOffset(string $arrayBody, int $offset, array $objects, ?int &$endOffset): ?float
    {
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
            $endOffset = $offset + strlen($match[0]);
            $objectBody = $this->objectBodyForReference((int) $match[1], (int) $match[2], $objects);
            if ($objectBody !== null && preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', trim($objectBody)) === 1) {
                return (float) trim($objectBody);
            }

            return null;
        }

        if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?=$|[\s\[\]\(\)<>{}\/%])/s', $arrayBody, $match, 0, $offset) === 1) {
            $endOffset = $offset + strlen($match[0]);
            return (float) $match[0];
        }

        $endOffset = null;
        return null;
    }

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }
}
