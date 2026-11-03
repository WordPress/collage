<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfActionReviewExtractor
{
    private const MAX_ACTION_CHAIN_DEPTH = 20;

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
    private const FILESPEC_FILE_NAME_BOUNDARY_KEYS = ['UF', 'F', 'DOS', 'Unix', 'Mac'];

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

    private const SUBMIT_FORM_FLAG_NAMES = [
        2 => 'include_no_value_fields',
        3 => 'html_format',
        4 => 'get_method',
        5 => 'submit_coordinates',
        6 => 'xfdf_format',
        7 => 'include_append_saves',
        8 => 'include_annotations',
        9 => 'submit_pdf',
        10 => 'canonical_format',
        11 => 'exclude_non_user_annotations',
        12 => 'exclude_f_key',
        14 => 'embed_form',
    ];

    private const ANNOTATION_ACTION_EVENT_LABELS = [
        'E' => 'cursor_enter',
        'X' => 'cursor_exit',
        'D' => 'mouse_down',
        'U' => 'mouse_up',
        'Fo' => 'focus',
        'Bl' => 'blur',
        'PO' => 'page_open',
        'PC' => 'page_close',
        'PV' => 'page_visible',
        'PI' => 'page_invisible',
    ];

    /** @var array<int, mixed> */
    private array $objects;

    /** @var array<int, array<int, mixed>> */
    private array $objectsByGeneration = [];

    /** @var array<int, array<int, string>> */
    private array $objectBodiesByGeneration = [];

    /** @var array<string, int> */
    private array $pageIndexesByReference;

    /** @var array<string, mixed> */
    private array $destinations;

    private ?string $uriBase;

    public function __construct(string $pdfBytes)
    {
        $this->objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($this->objects);

        $pageObjectReferences = $this->orderedPageObjectReferences($this->objects, $catalog);
        $this->pageIndexesByReference = [];
        foreach ($pageObjectReferences as $index => $reference) {
            $this->pageIndexesByReference[$this->referenceKey($reference['object'], $reference['generation'])] = $index;
        }

        $this->destinations = $catalog === null ? [] : $this->destinationMap($catalog, $this->objects);
        $this->uriBase = $catalog === null ? null : $this->catalogUriBase($catalog);
    }

    /**
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     additional_actions: list<array<string, mixed>>,
     *     previous_uri_actions: list<array<string, mixed>>,
     *     executes_actions_on_import: false
     * }
     */
    public function reviewAnnotationActions(string $annotationBody): array
    {
        $dictValue = $this->dictionaryValueFromBody($annotationBody);
        $dict = $this->dictionaryItems($dictValue);
        if ($dict === null) {
            return [
                'actions' => [],
                'additional_actions' => [],
                'previous_uri_actions' => [],
                'executes_actions_on_import' => false,
            ];
        }

        $malformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($dictValue);
        $seen = [];
        $actions = [];
        if (array_key_exists('A', $dict) && !isset($malformedValueKeys['A'])) {
            $actions = $this->reviewPrimaryAnnotationActionsFromValue($dict['A'], $seen);
        } elseif (array_key_exists('Dest', $dict) && !isset($malformedValueKeys['Dest'])) {
            $action = $this->localDestinationReview($dict['Dest']);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        $previousUriActions = [];
        if (
            array_key_exists('PA', $dict)
            && !isset($malformedValueKeys['PA'])
            && !$this->valueHasTrailingOperandAfterResolution($dict['PA'])
        ) {
            $seen = [];
            foreach ($this->reviewActionsFromValue($dict['PA'], $seen) as $action) {
                $action['previous_uri_action'] = true;
                $previousUriActions[] = $action;
            }
        }

        return [
            'actions' => $actions,
            'additional_actions' => $this->additionalActionMetadata($dict['AA'] ?? null),
            'previous_uri_actions' => $previousUriActions,
            'executes_actions_on_import' => false,
        ] + $this->duplicateKeyReviewFields($dictValue, 'annotation_action_duplicate_keys')
            + $this->malformedValueOperandReviewFields($dictValue, 'annotation_action_malformed_value_operands');
    }

    /**
     * Link and annotation `/A` entries are a single action dictionary with an
     * `/S` action subtype. Arrays are valid under action `/Next`, and local
     * destinations are valid under `/Dest`, but malformed top-level `/A`
     * values must not donate a primary WordPress link target.
     *
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewPrimaryAnnotationActionsFromValue(mixed $value, array &$seen): array
    {
        $resolved = $this->resolveValue($value);
        $dict = $this->dictionaryItems($resolved);
        if ($dict === null || $this->nameValue($this->resolveValue($dict['S'] ?? null)) === null) {
            return [];
        }

        return $this->reviewActionsFromValue($value, $seen);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $structureParent
     * @return list<array<string, mixed>>
     */
    public static function actionsWithAnnotationStructureParentContext(
        array $actions,
        ?int $annotationObject,
        int $structParent,
        array $structureParent
    ): array {
        if ($actions === []) {
            return [];
        }

        $context = self::compactAnnotationStructureParentActionContext($structureParent);
        $associatedFiles = is_array($structureParent['associated_files'] ?? null)
            ? array_values($structureParent['associated_files'])
            : [];
        $associatedFileCount = is_int($structureParent['associated_file_count'] ?? null)
            ? $structureParent['associated_file_count']
            : count($associatedFiles);

        return array_map(
            static function (array $action) use (
                $annotationObject,
                $structParent,
                $context,
                $associatedFiles,
                $associatedFileCount
            ): array {
                $action['source_annotation_object'] = $annotationObject;
                $action['annotation_struct_parent'] = $structParent;
                $action['annotation_structure_parent'] = $context;
                if ($associatedFileCount > 0) {
                    $action['annotation_associated_file_count'] = $associatedFileCount;
                    $action['annotation_associated_files'] = $associatedFiles;
                }

                return $action;
            },
            $actions
        );
    }

    /**
     * @param array<string, mixed> $structureParent
     * @return array<string, mixed>
     */
    private static function compactAnnotationStructureParentActionContext(array $structureParent): array
    {
        $context = [];
        foreach ([
            'source',
            'key',
            'struct_object',
            'annotation_object',
            'raw_role',
            'role',
            'role_mapped',
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
            'reference_count',
            'references',
            'associated_file_count',
            'associated_files',
            'current_annotation_object_ref_matched',
            'current_page_annotation',
            'review_only',
            'visible_text_source',
        ] as $key) {
            if (array_key_exists($key, $structureParent)) {
                $context[$key] = $structureParent[$key];
            }
        }

        return array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function additionalActionMetadata(mixed $value): array
    {
        $resolvedAdditionalActions = $this->resolveValue($value);
        $additionalActions = $this->dictionaryItems($resolvedAdditionalActions);
        if ($additionalActions === null) {
            return [];
        }

        $malformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($resolvedAdditionalActions);
        $actions = [];
        foreach ($additionalActions as $event => $actionValue) {
            if (isset($malformedValueKeys[$event])) {
                $actions[] = [
                    'event' => $event,
                    'event_label' => self::ANNOTATION_ACTION_EVENT_LABELS[$event] ?? 'annotation_additional_action',
                ] + $this->malformedAdditionalActionEventReview($event, $resolvedAdditionalActions);
                continue;
            }

            $seen = [];
            foreach ($this->reviewActionsFromValue($actionValue, $seen) as $action) {
                $actions[] = [
                    'event' => $event,
                    'event_label' => self::ANNOTATION_ACTION_EVENT_LABELS[$event] ?? 'annotation_additional_action',
                ] + $action;
            }
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function malformedAdditionalActionEventReview(string $event, mixed $additionalActions): array
    {
        $reviewFields = $this->malformedValueOperandReviewFields(
            $additionalActions,
            'annotation_additional_action_malformed_value_operands'
        );
        $review = is_array($reviewFields['malformed_action_operand_review'] ?? null)
            ? $reviewFields['malformed_action_operand_review']
            : [
                'source' => 'annotation_additional_action_malformed_value_operands',
                'review_only' => true,
                'payload_included' => false,
                'visible_text_source' => false,
                'selected_entry_policy' => 'fail_closed_for_malformed_value',
            ];

        $review['keys'] = [$event];
        if (is_array($review['unexpected_operand_counts'] ?? null)) {
            $review['unexpected_operand_counts'] = array_intersect_key($review['unexpected_operand_counts'], [$event => true]);
        }

        return $this->reviewAction('unknown', 'malformed-action-dictionary', null, null, null, [], [], null, null, null, null)
            + [
                'malformed_action_operand_review' => $review,
                'malformed_action_operand_keys' => [$event],
            ];
    }

    /**
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewActionsFromValue(mixed $value, array &$seen, int $depth = 0, bool $allowDestinationFallback = true): array
    {
        if ($value === null || $depth > self::MAX_ACTION_CHAIN_DEPTH) {
            return [];
        }

        $resolved = $this->resolveValue($value);
        $array = $this->arrayItems($resolved);
        if ($array !== null && $this->objectValueHasTrailingOperand($resolved)) {
            return [];
        }
        if ($array !== null) {
            $actions = [];
            foreach ($array as $item) {
                foreach ($this->reviewActionsFromValue($item, $seen, $depth + 1, $allowDestinationFallback) as $action) {
                    $actions[] = $action;
                }
            }

            return $actions;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            $action = $allowDestinationFallback
                ? $this->localDestinationReview($value)
                : $this->malformedActionDictionaryReview('unknown');
            if ($action === null) {
                return [];
            }
            if ($depth > 0) {
                $action['chained'] = true;
                $action['chain_index'] = $depth;
            }

            return [$action];
        }

        $actionReference = $this->referenceObject($value);
        $actionObject = $actionReference['object'] ?? null;
        $identity = $actionReference === null
            ? 'dict:' . hash('sha256', serialize($dict))
            : 'obj:' . $actionReference['object'] . ':' . $actionReference['generation'];
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $malformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($resolved);
        $type = $this->nameValue($this->resolveValue($dict['S'] ?? null));
        $hasObjectTrailingOperand = $this->objectValueHasTrailingOperand($resolved);
        if (
            $hasObjectTrailingOperand
            || $this->resolvedDictionaryHasDuplicateKeys($resolved, ['S'])
            || isset($malformedValueKeys['S'])
        ) {
            $action = $this->reviewAction($type ?? 'unknown', 'malformed-action-dictionary', null, null, null, [], [], null, null, null, null);
        } else {
            $action = $this->reviewActionFromDictionary($dict, $value, $type, $allowDestinationFallback);
            if ($action === null && $type !== null) {
                $action = $this->reviewAction($type, 'unsupported-action-review', null, null, null, [], [], null, null, null, null);
            }
        }

        $actions = [];
        if ($action !== null) {
            if ($actionObject !== null) {
                $action['action_object'] = $actionObject;
                $action['action_generation'] = $actionReference['generation'] ?? 0;
            }
            $action += $this->objectTrailingOperandReviewFields($resolved, 'action_object_trailing_operands');
            $action += $this->duplicateKeyReviewFields($resolved, 'action_dictionary_duplicate_keys');
            $action += $this->malformedValueOperandReviewFields($resolved, 'action_dictionary_malformed_value_operands');
            if ($depth > 0) {
                $action['chained'] = true;
                $action['chain_index'] = $depth;
            }
            $actions[] = $action;
        }

        if (!$hasObjectTrailingOperand && array_key_exists('Next', $dict) && !isset($malformedValueKeys['Next'])) {
            foreach ($this->reviewActionsFromValue($dict['Next'], $seen, $depth + 1, false) as $nextAction) {
                $nextAction['chained'] = true;
                $nextAction['chain_index'] = $nextAction['chain_index'] ?? ($depth + 1);
                $actions[] = $nextAction;
            }
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function reviewActionFromDictionary(
        array $action,
        mixed $originalValue,
        ?string $type,
        bool $allowDestinationFallback = true
    ): ?array
    {
        $malformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($this->resolveValue($originalValue));

        if ($type === 'GoTo' && array_key_exists('D', $action)) {
            if (isset($malformedValueKeys['D']) || $this->objectValueHasTrailingOperand($this->resolveValue($action['D']))) {
                return $this->malformedActionDictionaryReview('GoTo');
            }

            return $this->localDestinationReview($action['D']);
        }

        if ($type === 'URI') {
            if (isset($malformedValueKeys['URI']) || $this->objectValueHasTrailingOperand($this->resolveValue($action['URI'] ?? null))) {
                return $this->malformedActionDictionaryReview('URI');
            }

            $uri = $this->stringOrNameValue($this->resolveValue($action['URI'] ?? null));
            if ($uri === null || trim($uri) === '') {
                return null;
            }

            $uriReview = $this->uriReview($uri);
            $isMapReview = $this->uriIsMapReview(
                $action['IsMap'] ?? null,
                isset($malformedValueKeys['IsMap']),
                array_key_exists('IsMap', $action)
            );
            $isMap = $isMapReview['is_map'];
            $safety = $uriReview['is_safe_uri']
                ? ($isMap ? 'coordinate-dependent-uri-review' : 'review-uri')
                : 'blocked-unsafe-uri';

            return $this->reviewAction(
                'URI',
                $safety,
                null,
                null,
                null,
                [],
                [],
                $uriReview['uri'],
                null,
                null,
                $uriReview['is_safe_uri']
            ) + $uriReview['metadata'] + $isMapReview['metadata'] + [
                'uri_is_map' => $isMap,
                'requires_activation_coordinates' => $isMap,
            ];
        }

        if ($type === 'GoToR') {
            if (
                isset($malformedValueKeys['F'])
                || isset($malformedValueKeys['D'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['F'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['D'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('GoToR');
            }

            $target = $this->remoteGoToTargetFromAction($action);
            if ($target === null) {
                return null;
            }

            return $this->reviewAction(
                'GoToR',
                'remote-document-review',
                $target['page'],
                $target['destination'],
                $target['view_mode'],
                $target['view_position'],
                $target['view_parameters'],
                null,
                $target['file'],
                null,
                null,
                $target['new_window']
            );
        }

        if ($type === 'GoToE') {
            if (
                isset($malformedValueKeys['F'])
                || isset($malformedValueKeys['D'])
                || isset($malformedValueKeys['T'])
                || isset($malformedValueKeys['NewWindow'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['F'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['D'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['T'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['NewWindow'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('GoToE');
            }

            return $this->embeddedGoToActionReview($action);
        }

        if ($type === 'Launch') {
            if (
                isset($malformedValueKeys['F'])
                || isset($malformedValueKeys['Win'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['F'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Win'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Launch');
            }

            $file = $this->fileSpecValue($action['F'] ?? null);
            $win = $this->resolveDictionary($action['Win'] ?? null);
            $winMalformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($this->resolveValue($action['Win'] ?? null));
            if ($file === null && isset($winMalformedValueKeys['F'])) {
                return $this->malformedActionDictionaryReview('Launch');
            }
            if ($file === null && $win !== null) {
                $file = $this->fileSpecValue($win['F'] ?? null);
            }
            if ($file === null || trim($file) === '') {
                return null;
            }

            $operation = $win === null ? null : $this->stringOrNameValue($this->resolveValue($win['O'] ?? null));

            return $this->reviewAction(
                'Launch',
                'blocked-launch',
                null,
                null,
                null,
                [],
                [],
                null,
                $file,
                $operation,
                null,
                $this->boolValue($action['NewWindow'] ?? null)
            );
        }

        if ($type === 'JavaScript') {
            return $this->reviewAction('JavaScript', 'blocked-javascript', null, null, null, [], [], null, null, null, null);
        }

        if ($type === 'Named') {
            if (isset($malformedValueKeys['N']) || $this->objectValueHasTrailingOperand($this->resolveValue($action['N'] ?? null))) {
                return $this->malformedActionDictionaryReview('Named');
            }

            return $this->namedActionReview($action);
        }

        if ($type === 'ImportData') {
            if (isset($malformedValueKeys['F']) || $this->objectValueHasTrailingOperand($this->resolveValue($action['F'] ?? null))) {
                return $this->malformedActionDictionaryReview('ImportData');
            }

            return $this->importDataActionReview($action);
        }

        if ($type === 'Hide') {
            if (
                isset($malformedValueKeys['T'])
                || isset($malformedValueKeys['H'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['T'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['H'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Hide');
            }

            return $this->hideActionReview($action);
        }

        if ($type === 'SubmitForm' || $type === 'ResetForm') {
            if (
                isset($malformedValueKeys['Fields'])
                || isset($malformedValueKeys['Flags'])
                || ($type === 'SubmitForm' && isset($malformedValueKeys['F']))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Fields'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Flags'] ?? null))
                || ($type === 'SubmitForm' && $this->objectValueHasTrailingOperand($this->resolveValue($action['F'] ?? null)))
            ) {
                return $this->malformedActionDictionaryReview($type);
            }

            return $this->formActionReview($action, $type);
        }

        if ($type === 'Thread') {
            if (
                isset($malformedValueKeys['D'])
                || isset($malformedValueKeys['B'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['D'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['B'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Thread');
            }

            return $this->threadActionReview($action);
        }

        if ($type === 'Movie') {
            if (
                isset($malformedValueKeys['Annotation'])
                || isset($malformedValueKeys['T'])
                || isset($malformedValueKeys['Operation'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Annotation'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['T'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Operation'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Movie');
            }

            return $this->movieActionReview($action);
        }

        if ($type === 'Sound') {
            if (
                isset($malformedValueKeys['Sound'])
                || isset($malformedValueKeys['Volume'])
                || isset($malformedValueKeys['Synchronous'])
                || isset($malformedValueKeys['Repeat'])
                || isset($malformedValueKeys['Mix'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Sound'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Volume'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Synchronous'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Repeat'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Mix'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Sound');
            }

            return $this->soundActionReview($action);
        }

        if ($type === 'Rendition') {
            if (
                isset($malformedValueKeys['OP'])
                || isset($malformedValueKeys['AN'])
                || isset($malformedValueKeys['R'])
                || isset($malformedValueKeys['JS'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['OP'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['AN'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['R'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['JS'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Rendition');
            }

            return $this->renditionActionReview($action);
        }

        if ($type === 'RichMediaExecute') {
            if (
                isset($malformedValueKeys['TA'])
                || isset($malformedValueKeys['AN'])
                || isset($malformedValueKeys['TI'])
                || isset($malformedValueKeys['C'])
                || isset($malformedValueKeys['CMD'])
                || isset($malformedValueKeys['A'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['TA'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['AN'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['TI'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['C'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['CMD'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['A'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('RichMediaExecute');
            }

            return $this->richMediaExecuteActionReview($action);
        }

        if ($type === 'SetOCGState') {
            if (
                isset($malformedValueKeys['State'])
                || isset($malformedValueKeys['PreserveRB'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['State'] ?? null))
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['PreserveRB'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('SetOCGState');
            }

            return $this->setOcgStateActionReview($action);
        }

        if ($type === 'Trans') {
            if (
                isset($malformedValueKeys['Trans'])
                || $this->objectValueHasTrailingOperand($this->resolveValue($action['Trans'] ?? null))
            ) {
                return $this->malformedActionDictionaryReview('Trans');
            }

            return $this->transitionActionReview($action);
        }

        if ($type === null) {
            return $allowDestinationFallback
                ? $this->localDestinationReview($originalValue)
                : $this->malformedActionDictionaryReview('unknown');
        }

        return null;
    }

    private function malformedActionDictionaryReview(string $type): array
    {
        return $this->reviewAction($type, 'malformed-action-dictionary', null, null, null, [], [], null, null, null, null);
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function namedActionReview(array $action): array
    {
        $name = $this->stringOrNameValue($this->resolveValue($action['N'] ?? null));

        return $this->reviewAction('Named', 'named-action-review', null, null, null, [], [], null, null, null, null)
            + ['named_action' => $name];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function importDataActionReview(array $action): array
    {
        $file = $this->fileSpecValue($action['F'] ?? null);

        return $this->reviewAction('ImportData', 'import-data-action-review', null, null, null, [], [], null, $file, null, null)
            + [
                'target' => $file,
                'target_scheme' => $this->uriScheme($file),
                'imports_form_data' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function hideActionReview(array $action): array
    {
        $selection = $this->actionTargetSelection($action['T'] ?? null);
        $hide = is_bool($action['H'] ?? null) ? $action['H'] : true;

        $row = $this->reviewAction('Hide', 'hide-action-review', null, null, null, [], [], null, null, null, null);
        $row['hide'] = $hide;
        $row['operation'] = $hide ? 'hide' : 'show';
        $row['field_objects'] = $selection['field_objects'];
        $row['field_names'] = $selection['field_names'];
        $row['unresolved_field_objects'] = $selection['unresolved_field_objects'];

        return $row;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function formActionReview(array $action, string $type): array
    {
        $flags = $this->intValue($action['Flags'] ?? null) ?? 0;
        $selection = $this->actionFieldSelection($action['Fields'] ?? null, $type, $flags);
        $file = $type === 'SubmitForm' ? $this->fileSpecValue($action['F'] ?? null) : null;

        $row = $this->reviewAction(
            $type,
            $type === 'SubmitForm' ? 'submit-form-action-review' : 'reset-form-action-review',
            null,
            null,
            null,
            [],
            [],
            null,
            $file,
            null,
            null
        ) + [
            'flags' => $flags,
            'flag_names' => $this->actionFlagNames($type, $flags),
            'fields_mode' => $selection['fields_mode'],
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
        ];

        if ($type === 'SubmitForm') {
            $row += [
                'target' => $file,
                'target_scheme' => $this->uriScheme($file),
                'submit_format' => $this->submitFormatFromFlags($flags),
                'requested_submit_format' => $this->submitFormatFromFlags($flags),
                'include_no_value_fields' => $this->hasFlagBit($flags, 2),
                'html_format_requested' => $this->hasFlagBit($flags, 3),
                'get_method_requested' => $this->hasFlagBit($flags, 4),
                'submit_coordinates_requested' => $this->hasFlagBit($flags, 5),
                'xfdf_requested' => $this->hasFlagBit($flags, 6),
                'include_append_saves_requested' => $this->hasFlagBit($flags, 7),
                'include_annotations_requested' => $this->hasFlagBit($flags, 8),
                'submit_pdf_requested' => $this->hasFlagBit($flags, 9),
                'canonical_format_requested' => $this->hasFlagBit($flags, 10),
                'exclude_non_user_annotations_requested' => $this->hasFlagBit($flags, 11),
                'exclude_f_key_requested' => $this->hasFlagBit($flags, 12),
                'embed_form_requested' => $this->hasFlagBit($flags, 14),
                'submits_pdf_on_import' => false,
                'embeds_form_on_import' => false,
                'includes_annotations_on_import' => false,
                'uses_get_method_on_import' => false,
                'default_excludes_no_export' => true,
            ];
        } else {
            $row['reset_to_default'] = true;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function embeddedGoToActionReview(array $action): array
    {
        $target = $this->remoteDestinationValue($action['D'] ?? null) ?? [
            'destination' => null,
            'page' => null,
            'view_mode' => null,
            'view_position' => [],
            'view_parameters' => [],
        ];
        $file = $this->fileSpecValue($action['F'] ?? null);

        return $this->reviewAction(
            'GoToE',
            'embedded-document-review',
            $target['page'],
            $target['destination'],
            $target['view_mode'],
            $target['view_position'],
            $target['view_parameters'],
            null,
            $file,
            null,
            null,
            $this->boolValue($action['NewWindow'] ?? null)
        ) + [
            'target' => $this->embeddedTargetReview($action['T'] ?? null),
            'executes_embedded_document' => false,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function threadActionReview(array $action): array
    {
        $destination = $this->stringOrNameValue($this->resolveValue($action['D'] ?? null));
        $beadReference = $this->referenceObject($action['B'] ?? null);

        return $this->reviewAction('Thread', 'article-thread-review', null, $destination, null, [], [], null, null, null, null)
            + [
                'thread_bead_object' => $beadReference['object'] ?? null,
                'thread_bead_generation' => $beadReference['generation'] ?? null,
                'enters_article_thread_mode_on_import' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function movieActionReview(array $action): array
    {
        $annotationReference = $this->referenceObject($action['Annotation'] ?? null);

        $row = $this->reviewAction('Movie', 'movie-action-review', null, null, null, [], [], null, null, null, null);
        $row['target_annotation_object'] = $annotationReference['object'] ?? null;
        $row['target_annotation_generation'] = $annotationReference['generation'] ?? null;
        $row['title'] = $this->stringOrNameValue($this->resolveValue($action['T'] ?? null));
        $row['operation'] = $this->stringOrNameValue($this->resolveValue($action['Operation'] ?? null));
        $row['executes_media'] = false;

        return $row;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function soundActionReview(array $action): array
    {
        $soundReference = $this->referenceObject($action['Sound'] ?? null);

        return $this->reviewAction('Sound', 'sound-action-review', null, null, null, [], [], null, null, null, null)
            + [
                'sound_object' => $soundReference['object'] ?? null,
                'sound_generation' => $soundReference['generation'] ?? null,
                'volume' => $this->numericOrNullValue($this->resolveValue($action['Volume'] ?? null)),
                'synchronous' => $this->boolValue($action['Synchronous'] ?? null),
                'repeat' => $this->boolValue($action['Repeat'] ?? null),
                'mix' => $this->boolValue($action['Mix'] ?? null),
                'executes_media' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function renditionActionReview(array $action): array
    {
        $operation = $this->intValue($action['OP'] ?? null);
        $annotationReference = $this->referenceObject($action['AN'] ?? null);
        $script = $this->stringOrNameValue($this->resolveValue($action['JS'] ?? null));

        $row = $this->reviewAction('Rendition', 'media-rendition-review', null, null, null, [], [], null, null, null, null)
            + [
                'operation_code' => $operation,
                'operation_label' => $operation === null ? null : $this->renditionOperationLabel($operation),
                'target_annotation_object' => $annotationReference['object'] ?? null,
                'target_annotation_generation' => $annotationReference['generation'] ?? null,
                'file_names' => $this->fileNamesFromNestedValue($action['R'] ?? null),
                'executes_media' => false,
            ];

        if ($script !== null) {
            $row['script_preview'] = $script;
            $row['script_sha256'] = hash('sha256', $script);
            $row['executes_javascript'] = false;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function richMediaExecuteActionReview(array $action): array
    {
        $commandDictionary = $this->resolveDictionary($action['CMD'] ?? null);
        $command = $this->stringOrNameValue($this->resolveValue($action['C'] ?? null));
        if ($command === null && $commandDictionary !== null) {
            $command = $this->stringOrNameValue($this->resolveValue($commandDictionary['C'] ?? null));
        }

        $targetAnnotation = $this->referenceObject($action['TA'] ?? null)
            ?? $this->referenceObject($action['AN'] ?? null);
        $targetInstance = $this->referenceObject($action['TI'] ?? null);

        return $this->reviewAction('RichMediaExecute', 'rich-media-execute-review', null, null, null, [], [], null, null, null, null)
            + [
                'target_annotation_object' => $targetAnnotation['object'] ?? null,
                'target_annotation_generation' => $targetAnnotation['generation'] ?? null,
                'target_instance_object' => $targetInstance['object'] ?? null,
                'target_instance_generation' => $targetInstance['generation'] ?? null,
                'command' => $command,
                'argument_count' => count($this->resolveArray($action['A'] ?? null) ?? []),
                'executes_media' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function setOcgStateActionReview(array $action): array
    {
        $state = $this->resolveArray($action['State'] ?? null) ?? [];
        $operations = [];
        $targetObjects = [];
        foreach ($state as $item) {
            $operation = $this->nameValue($this->resolveValue($item));
            if ($operation !== null) {
                $operations[] = $operation;
                continue;
            }

            $reference = $this->referenceObject($item);
            if ($reference !== null) {
                $targetObjects[] = $reference['object'];
            }
        }

        return $this->reviewAction('SetOCGState', 'optional-content-state-review', null, null, null, [], [], null, null, null, null)
            + [
                'operations' => $operations,
                'target_optional_content_objects' => array_values(array_unique($targetObjects)),
                'preserve_radio_button_state' => $this->boolValue($action['PreserveRB'] ?? null),
                'changes_optional_content_on_import' => false,
            ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function transitionActionReview(array $action): array
    {
        $transition = $this->resolveDictionary($action['Trans'] ?? null) ?? $action;
        $style = $this->stringOrNameValue($this->resolveValue($transition['S'] ?? null));

        return $this->reviewAction('Trans', 'page-transition-review', null, null, null, [], [], null, null, null, null)
            + [
                'transition_style' => $style,
                'duration' => $this->numericOrNullValue($this->resolveValue($transition['D'] ?? null)),
                'dimension' => $this->stringOrNameValue($this->resolveValue($transition['Dm'] ?? null)),
                'motion' => $this->stringOrNameValue($this->resolveValue($transition['M'] ?? null)),
                'direction' => $this->numericOrNullValue($this->resolveValue($transition['Di'] ?? null)),
                'scale' => $this->numericOrNullValue($this->resolveValue($transition['SS'] ?? null)),
                'rectangular' => $this->boolValue($transition['B'] ?? null),
                'applies_page_transition_on_import' => false,
            ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function embeddedTargetReview(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $targetReference = $this->referenceObject($value);
        $target = $this->resolveDictionary($value);
        if ($target === null) {
            return $targetReference === null ? null : ['target_object' => $targetReference['object']];
        }

        $relation = $this->stringOrNameValue($this->resolveValue($target['R'] ?? null));
        $nested = $this->embeddedTargetReview($target['T'] ?? null);
        $review = [
            'target_object' => $targetReference['object'] ?? null,
            'relation' => $relation,
            'relation_label' => $this->embeddedTargetRelationLabel($relation),
            'name' => $this->stringOrNameValue($this->resolveValue($target['N'] ?? null)),
            'page' => $this->intValue($target['P'] ?? null),
        ];
        $annotationReference = $this->referenceObject($target['A'] ?? null);
        if ($annotationReference !== null) {
            $review['annotation_object'] = $annotationReference['object'];
            $review['annotation_generation'] = $annotationReference['generation'];
        }
        if ($nested !== null) {
            $review['nested_target'] = $nested;
        }

        return array_filter($review, static fn (mixed $item): bool => $item !== null && $item !== []);
    }

    private function embeddedTargetRelationLabel(?string $relation): ?string
    {
        return match ($relation) {
            'C' => 'child',
            'P' => 'parent',
            'R' => 'root',
            default => $relation,
        };
    }

    private function renditionOperationLabel(int $operation): string
    {
        return match ($operation) {
            0 => 'play',
            1 => 'stop',
            2 => 'pause',
            3 => 'resume',
            4 => 'play_or_resume',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function fileNamesFromNestedValue(mixed $value, int $depth = 0, array $seen = []): array
    {
        if ($value === null || $depth > 8) {
            return [];
        }

        $reference = $this->referenceObject($value);
        if ($reference !== null) {
            $key = $reference['object'] . ':' . $reference['generation'];
            if (isset($seen[$key])) {
                return [];
            }
            $seen[$key] = true;
        }

        $resolved = $this->resolveValue($value);
        $names = [];
        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            $file = $this->fileSpecValue($resolved);
            if ($file !== null) {
                $names[] = $file;
            }
            foreach (['C', 'D', 'R', 'F', 'UF'] as $key) {
                if (array_key_exists($key, $dict)) {
                    array_push($names, ...$this->fileNamesFromNestedValue($dict[$key], $depth + 1, $seen));
                }
            }
            return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
        }

        foreach ($this->arrayItems($resolved) ?? [] as $item) {
            array_push($names, ...$this->fileNamesFromNestedValue($item, $depth + 1, $seen));
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function localDestinationReview(mixed $destination): ?array
    {
        $details = $this->destinationViewDetails($destination);
        if ($details === null) {
            return null;
        }

        return $this->reviewAction(
            'GoTo',
            'local-destination',
            $details['page'],
            $details['destination'],
            $details['view_mode'],
            $details['view_position'],
            $details['view_parameters'],
            null,
            null,
            null,
            null
        );
    }

    /**
     * @param list<float|null> $viewPosition
     * @param array<string, float|null> $viewParameters
     * @return array<string, mixed>
     */
    private function reviewAction(
        string $type,
        string $safety,
        ?int $page,
        ?string $destination,
        ?string $viewMode,
        array $viewPosition,
        array $viewParameters,
        ?string $uri,
        ?string $file,
        ?string $operation,
        ?bool $isSafeUri,
        ?bool $newWindow = null
    ): array {
        return [
            'action_type' => $type,
            'safety' => $safety,
            'page' => $page,
            'destination_page' => $page,
            'destination' => $destination,
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $viewParameters,
            'uri' => $uri,
            'file' => $file,
            'operation' => $operation,
            'new_window' => $newWindow,
            'is_safe_uri' => $isSafeUri,
            'executes_on_import' => false,
        ];
    }

    /**
     * @return array{file: string, destination: string|null, page: int|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromAction(array $action): ?array
    {
        $file = $this->fileSpecValue($action['F'] ?? null);
        if ($file === null || !array_key_exists('D', $action)) {
            return null;
        }

        $destination = $this->remoteDestinationValue($action['D']);
        if ($destination === null) {
            return null;
        }

        return [
            'file' => $file,
            'destination' => $destination['destination'],
            'page' => $destination['page'],
            'view_mode' => $destination['view_mode'],
            'view_position' => $destination['view_position'],
            'view_parameters' => $destination['view_parameters'],
            'new_window' => $this->boolValue($action['NewWindow'] ?? null),
        ];
    }

    /**
     * @return array{destination: string|null, page: int|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function remoteDestinationValue(mixed $value): ?array
    {
        $resolved = $this->resolveValue($value);
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return null;
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return [
                'destination' => $name,
                'page' => null,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            if ($this->resolvedDictionaryHasDuplicateKeys($resolved, self::DESTINATION_DICTIONARY_BOUNDARY_KEYS)) {
                return null;
            }

            return $this->remoteDestinationValue($dict['D']);
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        if ($this->valueHasTrailingOperandAfterResolution($array[0])) {
            return null;
        }

        $first = $this->resolveValue($array[0]);
        if (is_int($first) && $first >= 0) {
            if (!$this->destinationArrayViewModeIsValid($array)) {
                return null;
            }

            $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));
            $viewPosition = [];
            for ($index = 2, $count = count($array); $index < $count; $index++) {
                $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index]));
            }
            if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
                $viewPosition[2] = null;
            }

            return [
                'destination' => null,
                'page' => $first,
                'view_mode' => $viewMode,
                'view_position' => $viewPosition,
                'view_parameters' => $this->viewParameters($viewMode, $viewPosition),
            ];
        }

        $name = $this->stringOrNameValue($first);
        if ($name !== null) {
            return [
                'destination' => $name,
                'page' => null,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        return null;
    }

    private function fileSpecValue(mixed $value): ?string
    {
        $resolved = $this->resolveValue($value);
        if ($this->objectValueHasTrailingOperand($resolved)) {
            return null;
        }

        $file = $this->stringOrNameValue($resolved);
        if ($file !== null && $file !== '') {
            return $file;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        if ($this->resolvedDictionaryHasDuplicateKeys($resolved, self::FILESPEC_FILE_NAME_BOUNDARY_KEYS)) {
            return null;
        }

        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $file = $this->stringOrNameValue($this->resolveValue($dict[$key] ?? null));
            if ($file !== null && $file !== '') {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{fields_mode: string, field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function actionFieldSelection(mixed $value, string $actionType, int $flags): array
    {
        if ($value === null) {
            return [
                'fields_mode' => $actionType === 'SubmitForm' ? 'all_exportable' : 'all',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $selection = $this->actionTargetSelection($value);

        return [
            'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
        ];
    }

    /**
     * @return array{field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function actionTargetSelection(mixed $value): array
    {
        $selection = [
            'field_objects' => [],
            'field_names' => [],
            'unresolved_field_objects' => [],
        ];

        $this->collectActionTarget($value, $selection);

        $selection['field_objects'] = array_values(array_unique($selection['field_objects']));
        $selection['field_names'] = array_values(array_unique($selection['field_names']));
        $selection['unresolved_field_objects'] = array_values(array_unique($selection['unresolved_field_objects']));

        return $selection;
    }

    /**
     * @param array{field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>} $selection
     */
    private function collectActionTarget(mixed $value, array &$selection, int $depth = 0): void
    {
        if ($value === null || $depth > 20) {
            return;
        }

        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber !== null) {
            $resolved = $this->resolveValue($value);
            $array = $this->arrayItems($resolved);
            if ($array !== null) {
                foreach ($array as $item) {
                    $this->collectActionTarget($item, $selection, $depth + 1);
                }

                return;
            }

            $selection['field_objects'][] = $objectNumber;
            $dict = $this->dictionaryItems($resolved);
            if ($dict !== null) {
                $name = $this->stringOrNameValue($this->resolveValue($dict['T'] ?? null));
                if ($name !== null && $name !== '') {
                    $selection['field_names'][] = $name;
                } else {
                    $selection['unresolved_field_objects'][] = $objectNumber;
                }

                return;
            }

            $name = $this->stringOrNameValue($resolved);
            if ($name !== null && $name !== '') {
                $selection['field_names'][] = $name;
            } else {
                $selection['unresolved_field_objects'][] = $objectNumber;
            }

            return;
        }

        $resolved = $this->resolveValue($value);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            foreach ($array as $item) {
                $this->collectActionTarget($item, $selection, $depth + 1);
            }

            return;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            $this->collectActionTarget($dict['T'] ?? null, $selection, $depth + 1);

            return;
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null && $name !== '') {
            $selection['field_names'][] = $name;
        }
    }

    /**
     * @return list<string>
     */
    private function actionFlagNames(string $actionType, int $flags): array
    {
        $names = [];
        if ($this->hasFlagBit($flags, 1)) {
            $names[] = 'exclude_list';
        }

        if ($actionType === 'SubmitForm') {
            foreach (self::SUBMIT_FORM_FLAG_NAMES as $bit => $name) {
                if ($this->hasFlagBit($flags, $bit)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private function submitFormatFromFlags(int $flags): string
    {
        if ($this->hasFlagBit($flags, 9)) {
            return 'pdf';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'xfdf';
        }

        if ($this->hasFlagBit($flags, 3)) {
            return 'html';
        }

        return 'fdf';
    }

    private function hasFlagBit(int $flags, int $oneBasedBit): bool
    {
        return ($flags & (1 << ($oneBasedBit - 1))) !== 0;
    }

    private function intValue(mixed $value): ?int
    {
        $resolved = $this->resolveValue($value);
        if (is_int($resolved)) {
            return $resolved;
        }

        return is_float($resolved) ? (int) $resolved : null;
    }

    private function boolValue(mixed $value): ?bool
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return null;
        }

        $resolved = $this->resolveValue($value);

        return is_bool($resolved) ? $resolved : null;
    }

    /**
     * @return array{is_map: bool, metadata: array<string, mixed>}
     */
    private function uriIsMapReview(mixed $value, bool $malformedValueOperand, bool $hasOperand): array
    {
        if (!$hasOperand) {
            return ['is_map' => false, 'metadata' => []];
        }

        $hasTrailingOperand = $this->valueHasTrailingOperandAfterResolution($value);
        $resolved = $hasTrailingOperand ? null : $this->resolveValue($value);
        if (!$malformedValueOperand && !$hasTrailingOperand && is_bool($resolved)) {
            return ['is_map' => $resolved, 'metadata' => []];
        }

        return [
            'is_map' => true,
            'metadata' => [
                'uri_is_map_operand_malformed' => true,
                'uri_is_map_operand_review' => [
                    'source' => 'uri_action_ismap_boolean_operand',
                    'review_only' => true,
                    'payload_included' => false,
                    'visible_text_source' => false,
                    'selected_value_policy' => 'coordinate_dependent_review_for_malformed_boolean',
                ],
            ],
        ];
    }

    /**
     * @return array{page: int, destination: string|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function destinationViewDetails(mixed $destination, ?string $destinationName = null, array $seenNames = []): ?array
    {
        if ($this->destinationValueIsStreamCarrier($destination)) {
            return null;
        }

        $pageReference = $this->referenceObject($destination);
        $pageIndex = $pageReference === null ? null : $this->pageIndexForReference($pageReference);
        if ($pageIndex !== null) {
            return [
                'page' => $pageIndex,
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $resolved = $this->resolveValue($destination);
        if ($this->valueHasTrailingOperandAfterResolution($destination)) {
            return null;
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            $lookupKey = $this->destinationLookupKeyForNameValue($resolved, $name);
            if (!array_key_exists($lookupKey, $this->destinations)) {
                $lookupKey = $name;
            }

            if (isset($seenNames[$lookupKey]) || !array_key_exists($lookupKey, $this->destinations)) {
                return null;
            }
            $seenNames[$lookupKey] = true;

            return $this->destinationViewDetails(
                $this->destinations[$lookupKey],
                $this->destinationNameFromMapKey($lookupKey),
                $seenNames
            );
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            if ($this->resolvedDictionaryHasDuplicateKeys($destination, self::DESTINATION_DICTIONARY_BOUNDARY_KEYS)) {
                return null;
            }

            $localDestination = $this->localDestinationDictionaryValue($dict);
            if ($localDestination === null) {
                return null;
            }

            return $this->destinationViewDetails($localDestination['value'], $destinationName, $seenNames);
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            if (!$this->destinationArrayViewModeIsValid($array)) {
                return null;
            }

            return $this->explicitDestinationDetails($array, $destinationName);
        }

        if (is_int($resolved) && $resolved >= 0 && $resolved < count($this->pageIndexesByReference)) {
            return [
                'page' => $resolved,
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
     * @return array{page: int, destination: string|null, view_mode: string|null, view_position: list<float|null>, view_parameters: array<string, float|null>}|null
     */
    private function explicitDestinationDetails(array $array, ?string $destinationName): ?array
    {
        $page = $this->destinationPageFromValue($array[0] ?? null);
        if ($page === null) {
            return null;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));
        $viewPosition = [];
        for ($index = 2, $count = count($array); $index < $count; $index++) {
            $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index]));
        }

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

    private function destinationPageFromValue(mixed $value): ?int
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return null;
        }

        $pageReference = $this->referenceObject($value);
        if ($pageReference !== null) {
            $pageIndex = $this->pageIndexForReference($pageReference);
            if ($pageIndex !== null) {
                return $pageIndex;
            }
        }

        $resolved = $this->resolveValue($value);
        $resolvedPageReference = $this->referenceObject($resolved);
        if ($resolvedPageReference !== null) {
            $pageIndex = $this->pageIndexForReference($resolvedPageReference);
            if ($pageIndex !== null) {
                return $pageIndex;
            }
        }

        if (is_int($resolved) && $resolved >= 0 && $resolved < count($this->pageIndexesByReference)) {
            return $resolved;
        }

        return null;
    }

    private function destinationValueAllowedForMap(mixed $value, int $depth = 0): bool
    {
        if ($depth > 20) {
            return false;
        }
        if ($this->destinationValueIsStreamCarrier($value)) {
            return false;
        }

        $pageReference = $this->referenceObject($value);
        if ($pageReference !== null) {
            $pageIndex = $this->pageIndexForReference($pageReference);
            if ($pageIndex !== null) {
                return true;
            }
        }

        $resolved = $this->resolveValue($value);
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return false;
        }

        if ($this->stringOrNameValue($resolved) !== null) {
            return true;
        }

        $resolvedPageReference = $this->referenceObject($resolved);
        if ($resolvedPageReference !== null) {
            $pageIndex = $this->pageIndexForReference($resolvedPageReference);
            if ($pageIndex !== null) {
                return true;
            }
        }

        if (is_int($resolved)) {
            return $resolved >= 0 && $resolved < count($this->pageIndexesByReference);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            if ($this->resolvedDictionaryHasDuplicateKeys($value, self::DESTINATION_DICTIONARY_BOUNDARY_KEYS)) {
                return false;
            }

            $localDestination = $this->localDestinationDictionaryValue($dict);
            return $localDestination !== null
                && $this->destinationValueAllowedForMap($localDestination['value'], $depth + 1);
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            if (!$this->destinationArrayViewModeIsValid($array)) {
                return false;
            }

            return $this->destinationPageFromValue($array[0]) !== null;
        }

        return false;
    }

    private function destinationValueIsStreamCarrier(mixed $value): bool
    {
        if ($this->nameTreeNodeReferenceHasTopLevelStream($value)) {
            return true;
        }

        $dictionary = $this->dictionaryItems($this->resolveValue($value));

        return $dictionary !== null && $this->nameTreeNodeHasStreamCarrierType($dictionary);
    }

    /**
     * @param array<string, mixed> $dict
     * @return array{value: mixed}|null
     */
    private function localDestinationDictionaryValue(array $dict): ?array
    {
        if (!array_key_exists('D', $dict)) {
            return null;
        }

        if (array_key_exists('S', $dict)) {
            if ($this->valueHasTrailingOperandAfterResolution($dict['S'])) {
                return null;
            }

            $type = $this->nameValue($this->resolveValue($dict['S']));
            if ($type !== 'GoTo') {
                return null;
            }
        }

        return ['value' => $dict['D']];
    }

    /**
     * @param list<mixed> $array
     */
    private function destinationArrayViewModeIsValid(array $array): bool
    {
        if (count($array) < 2) {
            return false;
        }

        if ($this->valueHasTrailingOperandAfterResolution($array[1] ?? null)) {
            return false;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null));

        return $viewMode !== null
            && isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode])
            && $this->destinationViewCoordinateOperandsAreValid($array, $viewMode);
    }

    /**
     * @param list<mixed> $array
     */
    private function destinationViewCoordinateOperandsAreValid(array $array, string $viewMode): bool
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

            if ($this->valueHasTrailingOperandAfterResolution($array[$index])) {
                return false;
            }

            $resolved = $this->resolveValue($array[$index]);
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
            if (!$this->destinationSurplusOperandIsBenign($array[$index])) {
                return false;
            }
        }

        return true;
    }

    private function destinationSurplusOperandIsBenign(mixed $value): bool
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return false;
        }

        $resolved = $this->resolveValue($value);

        return $resolved === null || is_int($resolved) || is_float($resolved);
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

    private function numericOrNullValue(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        $this->objectsByGeneration = [];
        $this->objectBodiesByGeneration = [];

        $definitions = $this->rawObjectDefinitions($pdfBytes);
        $selectedDefinitions = $this->selectedObjectDefinitionsFromXrefSection($pdfBytes, $definitions);
        $usesXrefSelection = $selectedDefinitions !== [];
        if ($selectedDefinitions !== []) {
            $definitions = $selectedDefinitions;
        }
        $freeObjectNumbers = $usesXrefSelection ? [] : PdfXrefFreeObjectMap::freeObjectNumbers($pdfBytes);

        foreach ($definitions as $definition) {
            $objectNumber = $definition['object'];
            if (isset($freeObjectNumbers[$objectNumber])) {
                continue;
            }

            $definitionBody = trim($definition['body']);
            $tokens = $this->tokens($this->firstObjectValue($definitionBody));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $value = $this->parseValue($tokens, $index);
            $value = $this->valueWithObjectTrailingOperandReview($value, $definitionBody);
            $generation = $definition['generation'];
            $this->objectBodiesByGeneration[$objectNumber][$generation] = trim($definition['body']);
            $this->objectsByGeneration[$objectNumber][$generation] = $value;
            $values[$objectNumber] = $value;
        }

        return $values;
    }

    /**
     * @return list<array{object: int, generation: int, body: string, offset: int}>
     */
    private function rawObjectDefinitions(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[] = [
                'object' => (int) $match[1][0],
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
                'offset' => (int) $match[0][1],
                'body_start' => (int) $match[3][1],
                'body_end' => (int) $match[3][1] + strlen($match[3][0]) - 1,
            ];
        }

        return $objects;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return list<array{object: int, generation: int, body: string, offset: int}>
     */
    private function selectedObjectDefinitionsFromXrefSection(string $pdfBytes, array $definitions): array
    {
        $xrefEntries = $this->xrefEntriesFromLatestStartxref($pdfBytes, $definitions);
        if ($xrefEntries === []) {
            return [];
        }

        $definitionsByOffset = [];
        foreach ($definitions as $definition) {
            $definitionsByOffset[$definition['offset']] = $definition;
        }

        $selected = [];
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1 || !isset($entry['offset'])) {
                continue;
            }

            $definition = $definitionsByOffset[$entry['offset']] ?? null;
            if (
                $definition === null
                || $definition['object'] !== $objectNumber
                || $definition['generation'] !== ($entry['generation'] ?? 0)
            ) {
                continue;
            }

            $selected[(int) $objectNumber] = $definition;
        }

        if ($selected === []) {
            return [];
        }

        for ($pass = 0; $pass < 4; $pass++) {
            $added = false;
            foreach ($xrefEntries as $objectNumber => $entry) {
                if (($entry['type'] ?? null) !== 2 || isset($selected[(int) $objectNumber])) {
                    continue;
                }

                $body = $this->objectStreamMemberBody($selected, $entry, (int) $objectNumber);
                if ($body === null) {
                    continue;
                }

                $selected[(int) $objectNumber] = [
                    'object' => (int) $objectNumber,
                    'generation' => 0,
                    'body' => $body,
                    'offset' => -1,
                ];
                $added = true;
            }

            if (!$added) {
                break;
            }
        }

        foreach ($definitions as $definition) {
            if (array_key_exists($definition['object'], $xrefEntries)) {
                continue;
            }

            $selected[$definition['object']] = $definition;
        }
        ksort($selected, SORT_NUMERIC);

        return array_values($selected);
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = PdfClassicXrefRebuilder::startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        return $this->xrefEntriesFromOffsetChain($pdfBytes, $offset, $definitions);
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, true> $visited
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $definitions, array $visited = []): array
    {
        if (isset($visited[$offset])) {
            return [];
        }
        $visited[$offset] = true;

        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($section !== null) {
            $previousOffset = $this->previousXrefStreamOffset($pdfBytes, $section, $definitions, $offset);
            $entries = $this->repairCurrentUpdateXrefEntries(
                $this->xrefStreamEntriesFromSection($section),
                $definitions,
                $previousOffset,
                $offset
            );
            $entries = $this->repairOmittedCurrentUpdateGraphEntries(
                $entries,
                $pdfBytes,
                $definitions,
                $section['dictionary'],
                $previousOffset,
                $offset
            );

            if ($previousOffset !== null && $previousOffset >= 0 && $previousOffset < $offset) {
                foreach ($this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $definitions, $visited) as $objectNumber => $entry) {
                    $entries[$objectNumber] ??= $entry;
                }
            }

            ksort($entries, SORT_NUMERIC);

            return $entries;
        }

        $tableSection = $this->xrefTableSectionAtOffset($pdfBytes, $offset);
        if ($tableSection === null) {
            return [];
        }

        $previousOffset = $this->previousXrefTableOffset($pdfBytes, $tableSection, $definitions, $offset);
        $entries = $this->repairCurrentUpdateXrefEntries(
            $tableSection['entries'],
            $definitions,
            $previousOffset,
            $offset
        );
        $entries = $this->repairOmittedCurrentUpdateGraphEntries(
            $entries,
            $pdfBytes,
            $definitions,
            $tableSection['trailer'],
            $previousOffset,
            $offset
        );

        if ($previousOffset !== null && $previousOffset >= 0 && $previousOffset < $offset) {
            foreach ($this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $definitions, $visited) as $objectNumber => $entry) {
                $entries[$objectNumber] ??= $entry;
            }
        }

        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @param array{dictionary: array<string, mixed>, body: string} $section
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function previousXrefStreamOffset(string $pdfBytes, array $section, array $definitions, int $currentOffset): ?int
    {
        return $this->repairPreviousXrefOffset(
            $pdfBytes,
            $this->previousXrefOffsetFromValue($section['dictionary']['Prev'] ?? null, $definitions, $currentOffset),
            $definitions,
            $currentOffset
        );
    }

    /**
     * @return array{entries: array<int, array{type: int, generation: int, offset: int}>, trailer: array<string, mixed>}|null
     */
    private function xrefTableSectionAtOffset(string $pdfBytes, int $offset): ?array
    {
        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (!$this->pdfKeywordAt($pdfBytes, $offset, 'xref')) {
            return null;
        }

        $afterKeywordOffset = $offset + strlen('xref');
        if ($afterKeywordOffset >= strlen($pdfBytes)) {
            return null;
        }

        $afterKeyword = $pdfBytes[$afterKeywordOffset];
        if ($afterKeyword !== '%' && !ctype_space($afterKeyword)) {
            return null;
        }

        $trailerOffset = $this->xrefTableTrailerKeywordOffset($pdfBytes, $afterKeywordOffset);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
            return null;
        }

        $dictionaryEnd = $this->dictionaryEndOffset($pdfBytes, $dictionaryOffset);
        if ($dictionaryEnd === null) {
            return null;
        }

        $tokens = $this->tokens(substr($pdfBytes, $dictionaryOffset, $dictionaryEnd - $dictionaryOffset));
        $index = 0;
        $trailer = $this->dictionaryItems($this->parseValue($tokens, $index));
        if ($trailer === null) {
            return null;
        }

        $entries = $this->xrefTableRows(substr($pdfBytes, $afterKeywordOffset, $trailerOffset - $afterKeywordOffset));
        if ($entries === null) {
            return null;
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
    }

    /**
     * @param array{trailer: array<string, mixed>} $section
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function previousXrefTableOffset(string $pdfBytes, array $section, array $definitions, int $currentOffset): ?int
    {
        return $this->repairPreviousXrefOffset(
            $pdfBytes,
            $this->previousXrefOffsetFromValue($section['trailer']['Prev'] ?? null, $definitions, $currentOffset),
            $definitions,
            $currentOffset
        );
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function previousXrefOffsetFromValue(mixed $value, array $definitions, int $currentOffset): ?int
    {
        $arrayItems = $this->arrayItems($value);
        if ($arrayItems !== null) {
            if (count($arrayItems) !== 1) {
                return null;
            }
            $value = $arrayItems[0];
        }

        $seenReferences = [];
        for ($depth = 0; $depth < 8; $depth++) {
            $offset = $this->directIntegerValue($value);
            if ($offset !== null) {
                return $offset >= 0 ? $offset : null;
            }

            $reference = $this->referenceObject($value);
            if ($reference === null) {
                return null;
            }

            $referenceKey = $this->referenceKey($reference['object'], $reference['generation']);
            if (isset($seenReferences[$referenceKey])) {
                return null;
            }
            $seenReferences[$referenceKey] = true;

            $definition = $this->latestDefinitionForReferenceBeforeOffset(
                $definitions,
                $reference['object'],
                $reference['generation'],
                $currentOffset
            );
            if ($definition === null) {
                return null;
            }

            $tokens = $this->tokens($this->firstObjectValue(trim($definition['body'])));
            if ($tokens === []) {
                return null;
            }

            $index = 0;
            $value = $this->parseValue($tokens, $index);
        }

        return null;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function repairPreviousXrefOffset(string $pdfBytes, ?int $previousOffset, array $definitions, int $currentOffset): ?int
    {
        if ($previousOffset === null || $previousOffset < 0) {
            return $previousOffset;
        }

        if ($previousOffset < $currentOffset && $this->xrefSectionExistsAtOffset($pdfBytes, $previousOffset, $definitions)) {
            return $previousOffset;
        }

        if ($previousOffset >= $currentOffset) {
            return $this->latestXrefSectionOffsetBefore($pdfBytes, $definitions, $currentOffset);
        }

        return $this->latestXrefSectionOffsetBefore($pdfBytes, $definitions, $previousOffset + 1)
            ?? $this->latestXrefSectionOffsetBefore($pdfBytes, $definitions, $currentOffset);
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function xrefSectionExistsAtOffset(string $pdfBytes, int $offset, array $definitions): bool
    {
        return $this->xrefStreamSectionAtOffset($offset, $definitions) !== null
            || $this->xrefTableSectionAtOffset($pdfBytes, $offset) !== null;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     */
    private function latestXrefSectionOffsetBefore(string $pdfBytes, array $definitions, int $currentOffset): ?int
    {
        $offsets = [];
        if (preg_match_all('/\bxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) >= 1) {
            foreach ($matches[0] as $match) {
                $offset = $match[1] ?? null;
                if (is_int($offset)) {
                    $offsets[] = $offset;
                }
            }
        }

        foreach ($definitions as $definition) {
            $offset = $definition['offset'];
            if ($offset < $currentOffset && $this->xrefStreamSectionAtOffset($offset, $definitions) !== null) {
                $offsets[] = $offset;
            }
        }

        rsort($offsets, SORT_NUMERIC);
        $seen = [];
        foreach ($offsets as $offset) {
            if ($offset >= $currentOffset || isset($seen[$offset])) {
                continue;
            }
            $seen[$offset] = true;

            if ($this->xrefSectionExistsAtOffset($pdfBytes, $offset, $definitions)) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array{object: int, generation: int, body: string, offset: int}|null
     */
    private function latestDefinitionForReferenceBeforeOffset(
        array $definitions,
        int $objectNumber,
        int $generation,
        int $currentOffset
    ): ?array {
        $selected = null;
        foreach ($definitions as $definition) {
            if (
                $definition['object'] !== $objectNumber
                || $definition['generation'] !== $generation
                || $definition['offset'] < 0
                || $definition['offset'] >= $currentOffset
            ) {
                continue;
            }

            if ($selected === null || $definition['offset'] > $selected['offset']) {
                $selected = $definition;
            }
        }

        return $selected;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>|null
     */
    private function xrefTableRows(string $sectionBody): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim(str_replace("\f", ' ', $sectionBody)));
        if ($lines === false) {
            return null;
        }

        $entries = [];
        $foundSection = false;
        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^(\+?\d+)\s+(\+?\d+)(?:\s*(?:%.*)?)$/', $line, $header) !== 1) {
                if (!$foundSection) {
                    return null;
                }

                continue;
            }

            $foundSection = true;
            $startObject = (int) $header[1];
            $count = max(0, (int) $header[2]);
            for ($entryIndex = 0; $entryIndex < $count;) {
                if (++$lineIndex >= $lineCount) {
                    return $entries === [] ? null : $entries;
                }

                $row = trim($lines[$lineIndex]);
                if ($row === '' || str_starts_with($row, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $row, $rowMatch) !== 1) {
                    return $entries === [] ? null : $entries;
                }

                $entries[$startObject + $entryIndex] = [
                    'type' => $rowMatch[3] === 'n' ? 1 : 0,
                    'generation' => (int) $rowMatch[2],
                    'offset' => (int) $rowMatch[1],
                ];
                $entryIndex++;
            }
        }

        return $foundSection ? $entries : null;
    }

    private function xrefTableTrailerKeywordOffset(string $pdfBytes, int $offset): ?int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length) {
            $char = $pdfBytes[$offset];

            if (substr($pdfBytes, $offset, 5) === '%%EOF' || $this->pdfKeywordAt($pdfBytes, $offset, 'startxref')) {
                return null;
            }

            if ($char === '%') {
                $this->skipPdfCommentLine($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $end = $this->skipPdfLiteralStringAt($pdfBytes, $offset);
                if ($end === null) {
                    return null;
                }

                $offset = $end;
                continue;
            }

            $compositeEnd = $this->skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $end = $this->skipPdfHexStringAt($pdfBytes, $offset);
                if ($end !== null) {
                    $offset = $end;
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $offset, 'trailer')) {
                $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $offset + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $offset;
                }
            }

            $offset++;
        }

        return null;
    }

    private function skipPdfCommentLine(string $pdfBytes, int &$offset): void
    {
        $length = strlen($pdfBytes);
        while ($offset < $length && $pdfBytes[$offset] !== "\n" && $pdfBytes[$offset] !== "\r") {
            $offset++;
        }
    }

    private function skipPdfLiteralStringAt(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '(') {
            return null;
        }

        $cursor = $offset;
        $this->readLiteralToken($pdfBytes, $cursor);

        return $cursor > $offset ? $cursor : null;
    }

    private function skipPdfHexStringAt(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '<' || ($pdfBytes[$offset + 1] ?? '') === '<') {
            return null;
        }

        $cursor = $offset;
        $this->readHexToken($pdfBytes, $cursor);

        return $cursor > $offset ? $cursor : null;
    }

    private function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') === '[') {
            return $this->arrayEndOffset($pdfBytes, $offset);
        }

        if (substr($pdfBytes, $offset, 2) === '<<') {
            return $this->dictionaryEndOffset($pdfBytes, $offset);
        }

        return null;
    }

    private function skipPdfWhitespace(string $pdfBytes, int $offset): int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length && ctype_space($pdfBytes[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function pdfKeywordAt(string $pdfBytes, int $offset, string $keyword): bool
    {
        if (substr($pdfBytes, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        $before = $offset === 0 ? '' : $pdfBytes[$offset - 1];
        $after = $pdfBytes[$offset + strlen($keyword)] ?? '';

        return ($before === '' || $this->isDelimiter($before))
            && ($after === '' || $this->isDelimiter($after));
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $entries
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function repairCurrentUpdateXrefEntries(
        array $entries,
        array $definitions,
        ?int $previousOffset,
        int $currentOffset
    ): array {
        if ($previousOffset === null || $previousOffset < 0 || $previousOffset >= $currentOffset) {
            return $entries;
        }

        foreach ($entries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1 || !isset($entry['offset'])) {
                continue;
            }

            $generation = $entry['generation'] ?? 0;
            if (!is_int($generation)) {
                continue;
            }

            $offset = $entry['offset'];
            if (!is_int($offset)) {
                continue;
            }

            $candidate = $this->currentUpdateDefinitionForXrefEntry(
                $definitions,
                (int) $objectNumber,
                $generation,
                $previousOffset,
                $currentOffset
            );
            if ($candidate === null) {
                continue;
            }

            $owner = $this->definitionAtOffset($definitions, $offset);
            $pointsAtCurrentOwner = $owner !== null
                && $owner['object'] === (int) $objectNumber
                && $owner['generation'] === $generation
                && $offset > $previousOffset
                && $offset < $currentOffset;
            if ($pointsAtCurrentOwner) {
                continue;
            }

            $entries[$objectNumber]['offset'] = $candidate['offset'];
            $entries[$objectNumber]['generation'] = $candidate['generation'];
        }

        return $entries;
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $entries
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @param array<string, mixed> $sectionDictionary
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function repairOmittedCurrentUpdateGraphEntries(
        array $entries,
        string $pdfBytes,
        array $definitions,
        array $sectionDictionary,
        ?int $previousOffset,
        int $currentOffset
    ): array {
        if ($previousOffset === null || $previousOffset < 0 || $previousOffset >= $currentOffset) {
            return $entries;
        }

        $pending = [];
        foreach (['Root', 'Info', 'Encrypt'] as $name) {
            $hasCurrentValue = array_key_exists($name, $sectionDictionary);
            $reference = $this->referenceObject($sectionDictionary[$name] ?? null);
            if ($reference !== null) {
                $pending[] = $reference;
                continue;
            }

            if ($hasCurrentValue) {
                continue;
            }

            $inheritedReference = $this->inheritedTrailerGraphReference(
                $pdfBytes,
                $previousOffset,
                $name,
                $definitions
            );
            if ($inheritedReference !== null) {
                $pending[] = $inheritedReference;
            }
        }

        $seen = [];
        while ($pending !== [] && count($seen) < 128) {
            $reference = array_shift($pending);
            if (!is_array($reference)) {
                continue;
            }

            $objectNumber = $reference['object'];
            $generation = $reference['generation'];
            $key = $objectNumber . ':' . $generation;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $definition = null;
            $bodyForReferences = null;
            $entry = $entries[$objectNumber] ?? null;
            if ($entry !== null) {
                if (($entry['type'] ?? null) !== 1 || ($entry['generation'] ?? 0) !== $generation) {
                    continue;
                }

                $offset = $entry['offset'] ?? null;
                $owner = is_int($offset) ? $this->definitionAtOffset($definitions, $offset) : null;
                if (
                    $owner !== null
                    && $owner['object'] === $objectNumber
                    && $owner['generation'] === $generation
                    && $owner['offset'] > $previousOffset
                    && $owner['offset'] < $currentOffset
                ) {
                    $definition = $owner;
                    $bodyForReferences = $definition['body'];
                }
            } else {
                $definition = $this->currentUpdateDefinitionForXrefEntry(
                    $definitions,
                    $objectNumber,
                    $generation,
                    $previousOffset,
                    $currentOffset
                );
                if ($definition !== null) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'generation' => $definition['generation'],
                        'offset' => $definition['offset'],
                    ];
                    $bodyForReferences = $definition['body'];
                } else {
                    $compressedMember = $this->currentUpdateObjectStreamMemberEntryForGraphReference(
                        $objectNumber,
                        $generation,
                        $entries,
                        $definitions,
                        $previousOffset,
                        $currentOffset
                    );
                    if ($compressedMember !== null) {
                        $entries[$objectNumber] = $compressedMember['entry'];
                        $bodyForReferences = $compressedMember['body'];
                    }
                }
            }

            if ($bodyForReferences === null) {
                continue;
            }

            foreach ($this->objectReferencesInBody($bodyForReferences) as $nestedReference) {
                $pending[] = $nestedReference;
            }
        }

        return $entries;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, true> $visited
     * @return array{object: int, generation: int}|null
     */
    private function inheritedTrailerGraphReference(
        string $pdfBytes,
        int $offset,
        string $name,
        array $definitions,
        array $visited = []
    ): ?array {
        if ($offset < 0 || isset($visited[$offset])) {
            return null;
        }
        $visited[$offset] = true;

        $tableSection = $this->xrefTableSectionAtOffset($pdfBytes, $offset);
        if ($tableSection !== null) {
            if (array_key_exists($name, $tableSection['trailer'])) {
                return $this->referenceObject($tableSection['trailer'][$name]);
            }

            $previousOffset = $this->previousXrefTableOffset($pdfBytes, $tableSection, $definitions, $offset);

            return $previousOffset === null
                ? null
                : $this->inheritedTrailerGraphReference($pdfBytes, $previousOffset, $name, $definitions, $visited);
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection !== null) {
            if (array_key_exists($name, $streamSection['dictionary'])) {
                return $this->referenceObject($streamSection['dictionary'][$name]);
            }

            $previousOffset = $this->previousXrefStreamOffset($pdfBytes, $streamSection, $definitions, $offset);

            return $previousOffset === null
                ? null
                : $this->inheritedTrailerGraphReference($pdfBytes, $previousOffset, $name, $definitions, $visited);
        }

        return null;
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $entries
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array{entry: array{type: int, object_stream: int, index: int, index_is_explicit: bool}, body: string}|null
     */
    private function currentUpdateObjectStreamMemberEntryForGraphReference(
        int $objectNumber,
        int $generation,
        array $entries,
        array $definitions,
        int $previousOffset,
        int $currentOffset
    ): ?array {
        if ($objectNumber < 1 || $generation !== 0) {
            return null;
        }

        $candidates = [];
        foreach ($entries as $carrierObjectNumber => $carrierEntry) {
            if (($carrierEntry['type'] ?? null) !== 1 || !isset($carrierEntry['offset'])) {
                continue;
            }

            $carrierOffset = $carrierEntry['offset'];
            if (!is_int($carrierOffset) || $carrierOffset <= $previousOffset || $carrierOffset >= $currentOffset) {
                continue;
            }

            $carrierDefinition = $this->definitionAtOffset($definitions, $carrierOffset);
            if (
                $carrierDefinition === null
                || $carrierDefinition['object'] !== (int) $carrierObjectNumber
                || $carrierDefinition['generation'] !== ($carrierEntry['generation'] ?? 0)
            ) {
                continue;
            }

            $member = $this->currentUpdateObjectStreamMemberFromCarrier(
                $carrierDefinition,
                $objectNumber
            );
            if ($member === null) {
                continue;
            }

            $candidates[] = [
                'entry' => [
                    'type' => 2,
                    'object_stream' => (int) $carrierObjectNumber,
                    'index' => $member['index'],
                    'index_is_explicit' => true,
                ],
                'body' => $member['body'],
            ];
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * @param array{object: int, generation: int, body: string, offset: int} $carrierDefinition
     * @return array{index: int, body: string}|null
     */
    private function currentUpdateObjectStreamMemberFromCarrier(array $carrierDefinition, int $objectNumber): ?array
    {
        $dictionary = $this->dictionaryItems($this->parseFirstObjectValue($carrierDefinition['body']));
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'ObjStm') {
            return null;
        }

        $objects = [
            $carrierDefinition['object'] => $carrierDefinition,
        ];
        $dictionary = $this->objectStreamDictionaryWithResolvedOperands($dictionary, $objects);

        $declaredCount = $this->directIntegerValue($dictionary['N'] ?? null);
        $firstOffset = $this->directIntegerValue($dictionary['First'] ?? null);
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytesFromDictionary($carrierDefinition['body'], $dictionary);
        if ($decoded === null || $firstOffset > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $firstOffset), $declaredCount);
        if ($members === []) {
            return null;
        }

        $memberIndexes = [];
        foreach ($members as $index => $member) {
            if ($member['object_id'] === $objectNumber) {
                $memberIndexes[] = $index;
            }
        }
        if (count($memberIndexes) !== 1) {
            return null;
        }

        $memberIndex = $memberIndexes[0];
        $body = $this->objectStreamMemberBody(
            $objects,
            [
                'type' => 2,
                'object_stream' => $carrierDefinition['object'],
                'index' => $memberIndex,
                'index_is_explicit' => true,
            ],
            $objectNumber
        );
        if ($body === null || trim($body) === '') {
            return null;
        }

        return [
            'index' => $memberIndex,
            'body' => $body,
        ];
    }

    /**
     * @return list<array{object: int, generation: int}>
     */
    private function objectReferencesInBody(string $body): array
    {
        $tokens = $this->tokens($this->firstObjectValue(trim($body)));
        if ($tokens === []) {
            return [];
        }

        $index = 0;
        return $this->objectReferencesInValue($this->parseValue($tokens, $index));
    }

    /**
     * @return list<array{object: int, generation: int}>
     */
    private function objectReferencesInValue(mixed $value): array
    {
        $reference = $this->referenceObject($value);
        if ($reference !== null) {
            return [$reference];
        }

        $references = [];
        foreach ($this->dictionaryItems($value) ?? $this->arrayItems($value) ?? [] as $child) {
            foreach ($this->objectReferencesInValue($child) as $childReference) {
                $references[] = $childReference;
            }
        }

        return $references;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array{object: int, generation: int, body: string, offset: int}|null
     */
    private function currentUpdateDefinitionForXrefEntry(
        array $definitions,
        int $objectNumber,
        int $generation,
        int $previousOffset,
        int $currentOffset
    ): ?array {
        $candidate = null;
        foreach ($definitions as $definition) {
            if (
                $definition['object'] !== $objectNumber
                || $definition['generation'] !== $generation
                || $definition['offset'] <= $previousOffset
                || $definition['offset'] >= $currentOffset
            ) {
                continue;
            }

            if ($candidate === null || $definition['offset'] > $candidate['offset']) {
                $candidate = $definition;
            }
        }

        return $candidate;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array{object: int, generation: int, body: string, offset: int}|null
     */
    private function definitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['offset'] === $offset) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param list<array{object: int, generation: int, body: string, offset: int}> $definitions
     * @return array{dictionary: array<string, mixed>, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['offset'] !== $offset) {
                continue;
            }

            $dictionary = $this->dictionaryItems($this->parseFirstObjectValue($definition['body']));
            if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'XRef') {
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
     * @param array{dictionary: array<string, mixed>, body: string} $section
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $decoded = $this->decodedStreamBytesFromDictionary($section['body'], $section['dictionary']);
        if ($decoded === null) {
            return [];
        }

        $widths = $this->xrefStreamFieldWidths($section['dictionary']['W'] ?? null);
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

                if (isset($entries[$objectNumber])) {
                    continue;
                }

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
    private function xrefStreamFieldWidths(mixed $value): ?array
    {
        $items = $this->arrayItems($value);
        if ($items === null || count($items) < 3) {
            return null;
        }

        $widths = [];
        foreach (array_slice($items, 0, 3) as $item) {
            $integer = $this->directIntegerValue($item);
            if ($integer === null || $integer < 0) {
                return null;
            }
            $widths[] = $integer;
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @param array<string, mixed> $dictionary
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(array $dictionary, int $decodedEntryCount): array
    {
        $index = $this->arrayItems($dictionary['Index'] ?? null);
        if ($index === null || $index === []) {
            $size = $this->directIntegerValue($dictionary['Size'] ?? null);

            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $ranges = [];
        $consumed = 0;
        for ($offset = 0, $count = count($index); $offset + 1 < $count; $offset += 2) {
            $first = $this->directIntegerValue($index[$offset]);
            $rowCount = $this->directIntegerValue($index[$offset + 1]);
            if ($first === null || $rowCount === null || $rowCount < 0) {
                continue;
            }

            $boundedCount = min($rowCount, max(0, $decodedEntryCount - $consumed));
            $ranges[] = [
                'first' => $first,
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
     * @param array<int, array{object: int, generation: int, body: string, offset: int}> $objects
     * @param array{type: int, object_stream?: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamMemberBody(array $objects, array $xrefEntry, int $requestedObjectNumber): ?string
    {
        $objectStreamNumber = $xrefEntry['object_stream'] ?? null;
        if (!is_int($objectStreamNumber) || !isset($objects[$objectStreamNumber])) {
            return null;
        }

        $objectStreamBody = $objects[$objectStreamNumber]['body'];
        $dictionary = $this->dictionaryItems($this->parseFirstObjectValue($objectStreamBody));
        if ($dictionary === null || $this->nameValue($dictionary['Type'] ?? null) !== 'ObjStm') {
            return null;
        }
        $dictionary = $this->objectStreamDictionaryWithResolvedOperands($dictionary, $objects);

        $declaredCount = $this->directIntegerValue($dictionary['N'] ?? null);
        $firstOffset = $this->directIntegerValue($dictionary['First'] ?? null);
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytesFromDictionary($objectStreamBody, $dictionary);
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

        return $end <= $start ? null : trim(substr($data, $start, $end - $start));
    }

    /**
     * @param array<string, mixed> $dictionary
     * @param array<int, array{object: int, generation: int, body: string, offset: int}> $objects
     * @return array<string, mixed>
     */
    private function objectStreamDictionaryWithResolvedOperands(array $dictionary, array $objects): array
    {
        foreach (['Length', 'Filter', 'DecodeParms', 'N', 'First'] as $key) {
            if (array_key_exists($key, $dictionary)) {
                $dictionary[$key] = $this->resolveSelectedObjectValue($dictionary[$key], $objects);
            }
        }

        return $dictionary;
    }

    /**
     * @param array<int, array{object: int, generation: int, body: string, offset: int}> $objects
     */
    private function resolveSelectedObjectValue(mixed $value, array $objects, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return $value;
        }

        $reference = $this->referenceObject($value);
        if ($reference !== null) {
            $definition = $objects[$reference['object']] ?? null;
            if ($definition === null || $definition['generation'] !== $reference['generation']) {
                return $value;
            }

            return $this->resolveSelectedObjectValue($this->parseFirstObjectValue($definition['body']), $objects, $depth + 1);
        }

        if (is_array($value) && ($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)) {
            $items = [];
            foreach ($value['items'] as $item) {
                $items[] = $this->resolveSelectedObjectValue($item, $objects, $depth + 1);
            }
            $value['items'] = $items;

            return $value;
        }

        if (is_array($value) && ($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)) {
            foreach ($value['items'] as $key => $item) {
                $value['items'][$key] = $this->resolveSelectedObjectValue($item, $objects, $depth + 1);
            }
        }

        return $value;
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

        $this->skipObjectStreamHeaderWhitespaceAndComments($header, $offset);
        if ($offset !== strlen($header)) {
            return [];
        }

        return $members;
    }

    private function readObjectStreamHeaderUnsignedInteger(string $header, int &$offset): ?int
    {
        $this->skipObjectStreamHeaderWhitespaceAndComments($header, $offset);
        if (preg_match('/\G\+?(\d+)/s', $header, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);
        if ($offset < strlen($header) && !$this->isDelimiter($header[$offset])) {
            return null;
        }

        return (int) $match[1];
    }

    private function skipObjectStreamHeaderWhitespaceAndComments(string $header, int &$offset): void
    {
        $length = strlen($header);
        while ($offset < $length) {
            if (ctype_space($header[$offset])) {
                $offset++;
                continue;
            }

            if ($header[$offset] === '%') {
                while ($offset < $length && $header[$offset] !== "\n" && $header[$offset] !== "\r") {
                    $offset++;
                }
                continue;
            }

            break;
        }
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
                $end = $index;
                $this->readLiteralToken($data, $end);
                if ($end <= $index || $offset < $end) {
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
                if ($end <= $index || $offset < $end) {
                    return false;
                }
                $index = $end;
                continue;
            }

            if ($char === '<' && substr($data, $index, 2) === '<<') {
                $end = $this->dictionaryEndOffset($data, $index);
                if ($end !== null) {
                    if ($end <= $index || $offset < $end) {
                        return false;
                    }
                    $index = $end;
                    continue;
                }
            }

            if ($char === '<') {
                $end = $index;
                $this->readHexToken($data, $end);
                if ($end <= $index || $offset < $end) {
                    return false;
                }
                $index = $end;
                continue;
            }

            if ($char === '[') {
                $end = $this->arrayEndOffset($data, $index);
                if ($end !== null) {
                    if ($end <= $index || $offset < $end) {
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
        return $previous !== '' && $this->isDelimiter($previous);
    }

    private function arrayEndOffset(string $value, int $offset): ?int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $this->readLiteralToken($value, $index);
                $index--;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionaryEnd = $this->dictionaryEndOffset($value, $index);
                if ($dictionaryEnd === null) {
                    return null;
                }
                $index = $dictionaryEnd - 1;
                continue;
            }

            if ($char === '<') {
                $this->readHexToken($value, $index);
                $index--;
                continue;
            }

            if ($char === '[') {
                $depth++;
                continue;
            }

            if ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return $index + 1;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $dictionary
     */
    private function decodedStreamBytesFromDictionary(string $body, array $dictionary): ?string
    {
        $decoded = $this->streamBytesFromBodyAndDictionary($body, $dictionary);
        if ($decoded === null) {
            return null;
        }

        foreach ($this->filterNamesFromValue($dictionary['Filter'] ?? null) as $filter) {
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
     * @param array<string, mixed> $dictionary
     */
    private function streamBytesFromBodyAndDictionary(string $body, array $dictionary): ?string
    {
        $body = trim($body);
        if (!str_starts_with($body, '<<')) {
            return null;
        }

        $dictionaryEnd = $this->dictionaryEndOffset($body, 0);
        if ($dictionaryEnd === null) {
            return null;
        }

        $offset = $dictionaryEnd;
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
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
        $length = $this->directIntegerValue($dictionary['Length'] ?? null);
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    /**
     * @return list<string>
     */
    private function filterNamesFromValue(mixed $value): array
    {
        $name = $this->nameValue($value);
        if ($name !== null) {
            return [$name];
        }

        $filters = [];
        foreach ($this->arrayItems($value) ?? [] as $filter) {
            $filterName = $this->nameValue($filter);
            if ($filterName !== null) {
                $filters[] = $filterName;
            }
        }

        return $filters;
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

    private function parseFirstObjectValue(string $body): mixed
    {
        $tokens = $this->tokens($this->firstObjectValue(trim($body)));
        if ($tokens === []) {
            return null;
        }

        $index = 0;
        return $this->parseValue($tokens, $index);
    }

    private function directIntegerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && abs($value - round($value)) <= 0.000001) {
            return (int) round($value);
        }

        return null;
    }

    private function firstObjectValue(string $body): string
    {
        if (!str_starts_with($body, '<<')) {
            return $body;
        }

        $endOffset = $this->dictionaryEndOffset($body, 0);
        return $endOffset === null ? $body : substr($body, 0, $endOffset);
    }

    private function dictionaryEndOffset(string $value, int $offset): ?int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $this->readLiteralToken($value, $index);
                $index--;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $this->readHexToken($value, $index);
                $index--;
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
                return $index + 2;
            }

            $index++;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryValueFromBody(string $body): mixed
    {
        $tokens = $this->tokens('<< ' . $body . ' >>');
        $index = 0;

        return $this->parseValue($tokens, $index);
    }

    /**
     * @return array{duplicate_key_review?: array<string, mixed>, duplicate_keys?: list<string>}
     */
    private function duplicateKeyReviewFields(mixed $value, string $source): array
    {
        if (
            !is_array($value)
            || ($value['pdfType'] ?? null) !== 'dict'
            || !is_array($value['duplicateKeyReview'] ?? null)
        ) {
            return [];
        }

        $review = $value['duplicateKeyReview'];
        if (($review['keys'] ?? []) === []) {
            return [];
        }

        $review['source'] = $source;

        return [
            'duplicate_key_review' => $review,
            'duplicate_keys' => $review['keys'],
        ];
    }

    /**
     * @return array{malformed_action_operand_review?: array<string, mixed>, malformed_action_operand_keys?: list<string>}
     */
    private function malformedValueOperandReviewFields(mixed $value, string $source): array
    {
        if (
            !is_array($value)
            || ($value['pdfType'] ?? null) !== 'dict'
            || !is_array($value['malformedValueOperandReview'] ?? null)
        ) {
            return [];
        }

        $review = $value['malformedValueOperandReview'];
        if (($review['keys'] ?? []) === []) {
            return [];
        }

        $review['source'] = $source;

        return [
            'malformed_action_operand_review' => $review,
            'malformed_action_operand_keys' => $review['keys'],
        ];
    }

    /**
     * @return array{object_trailing_operand_review?: array<string, mixed>}
     */
    private function objectTrailingOperandReviewFields(mixed $value, string $source): array
    {
        if (
            !is_array($value)
            || !is_array($value['objectTrailingOperandReview'] ?? null)
        ) {
            return [];
        }

        $review = $value['objectTrailingOperandReview'];
        $review['source'] = $source;

        return ['object_trailing_operand_review' => $review];
    }

    private function objectValueHasTrailingOperand(mixed $value): bool
    {
        return is_array($value) && is_array($value['objectTrailingOperandReview'] ?? null);
    }

    private function valueHasTrailingOperandAfterResolution(mixed $value): bool
    {
        if ($this->objectValueHasTrailingOperand($value)) {
            return true;
        }

        $reference = $this->referenceObject($value);
        if ($reference !== null && $this->referenceObjectBodyHasTrailingOperand($reference)) {
            return true;
        }

        return $this->objectValueHasTrailingOperand($this->resolveValue($value));
    }

    /**
     * @param array{object: int, generation: int} $reference
     */
    private function referenceObjectBodyHasTrailingOperand(array $reference): bool
    {
        $body = $this->objectBodiesByGeneration[$reference['object']][$reference['generation']] ?? null;

        return is_string($body) && $this->objectBodyHasTrailingOperand($body);
    }

    private function valueWithObjectTrailingOperandReview(mixed $value, string $objectBody): mixed
    {
        if (!is_array($value) || !$this->objectBodyHasTrailingOperand($objectBody)) {
            return $value;
        }

        $value['objectTrailingOperandReview'] = [
            'source' => 'object_trailing_operands',
            'review_only' => true,
            'payload_included' => false,
            'visible_text_source' => false,
            'selected_value_policy' => 'reject_indirect_object_for_action_or_destination',
        ];

        return $value;
    }

    private function objectBodyHasTrailingOperand(string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }

        $endOffset = $this->firstObjectValueEndOffset($body);
        if ($endOffset === null || $endOffset <= 0) {
            return false;
        }

        $tailOffset = $endOffset;
        $this->skipPdfWhitespaceAndCommentsAt($body, $tailOffset);
        if ($tailOffset >= strlen($body)) {
            return false;
        }

        if (str_starts_with($body, '<<') && $this->pdfKeywordAt($body, $tailOffset, 'stream')) {
            return false;
        }

        return true;
    }

    private function firstObjectValueEndOffset(string $body): ?int
    {
        $offset = 0;
        $this->skipPdfWhitespaceAndCommentsAt($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (substr($body, $offset, 2) === '<<') {
            return $this->dictionaryEndOffset($body, $offset);
        }

        if (($body[$offset] ?? '') === '[') {
            return $this->arrayEndOffset($body, $offset);
        }

        if (($body[$offset] ?? '') === '(') {
            $cursor = $offset;
            $this->readLiteralToken($body, $cursor);

            return $cursor;
        }

        if (($body[$offset] ?? '') === '<') {
            $cursor = $offset;
            $this->readHexToken($body, $cursor);

            return $cursor;
        }

        if (($body[$offset] ?? '') === '/') {
            $cursor = $offset + 1;
            while ($cursor < strlen($body) && !$this->isDelimiter($body[$cursor])) {
                $cursor++;
            }

            return $cursor;
        }

        if (preg_match('/\G[+-]?\d+/s', $body, $firstInteger, 0, $offset) === 1) {
            $cursor = $offset + strlen($firstInteger[0]);
            $afterFirstInteger = $cursor;
            $this->skipPdfWhitespaceAndCommentsAt($body, $cursor);
            if (
                $cursor > $afterFirstInteger
                && preg_match('/\G[+-]?\d+/s', $body, $secondInteger, 0, $cursor) === 1
            ) {
                $cursor += strlen($secondInteger[0]);
                $afterSecondInteger = $cursor;
                $this->skipPdfWhitespaceAndCommentsAt($body, $cursor);
                if (
                    $cursor > $afterSecondInteger
                    && ($body[$cursor] ?? '') === 'R'
                    && $this->tokenBoundaryAfterOffset($body, $cursor + 1)
                ) {
                    return $cursor + 1;
                }
            }
        }

        $cursor = $offset;
        while ($cursor < strlen($body) && !$this->isDelimiter($body[$cursor])) {
            $cursor++;
        }

        return $cursor > $offset ? $cursor : null;
    }

    private function tokenBoundaryAfterOffset(string $body, int $offset): bool
    {
        $next = $body[$offset] ?? '';

        return $next === '' || $this->isDelimiter($next);
    }

    private function skipPdfWhitespaceAndCommentsAt(string $body, int &$offset): void
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

    /**
     * @param list<string> $keys
     */
    private function resolvedDictionaryHasDuplicateKeys(mixed $value, array $keys): bool
    {
        $duplicateKeys = $this->dictionaryDuplicateKeySet($this->resolveValue($value));
        foreach ($keys as $key) {
            if (isset($duplicateKeys[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function dictionaryDuplicateKeySet(mixed $resolved): array
    {
        if (
            !is_array($resolved)
            || ($resolved['pdfType'] ?? null) !== 'dict'
            || !is_array($resolved['duplicateKeyReview'] ?? null)
            || !is_array($resolved['duplicateKeyReview']['keys'] ?? null)
        ) {
            return [];
        }

        $keys = [];
        foreach ($resolved['duplicateKeyReview']['keys'] as $key) {
            if (is_string($key)) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    private function dictionaryMalformedValueOperandKeySet(mixed $resolved): array
    {
        if (
            !is_array($resolved)
            || ($resolved['pdfType'] ?? null) !== 'dict'
            || !is_array($resolved['malformedValueOperandReview'] ?? null)
            || !is_array($resolved['malformedValueOperandReview']['keys'] ?? null)
        ) {
            return [];
        }

        $keys = [];
        foreach ($resolved['malformedValueOperandReview']['keys'] as $key) {
            if (is_string($key)) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects): ?array
    {
        foreach ($objects as $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog') {
                return $dict;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param list<string> $keys
     */
    private function catalogDictionaryHasDuplicateKeys(array $objects, array $keys): bool
    {
        foreach ($objects as $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict === null || $this->nameValue($dict['Type'] ?? null) !== 'Catalog') {
                continue;
            }

            $duplicateKeys = $this->dictionaryDuplicateKeySet($value);
            foreach ($keys as $key) {
                if (isset($duplicateKeys[$key])) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed>|null $catalog
     * @return list<array{object: int, generation: int}>
     */
    private function orderedPageObjectReferences(array $objects, ?array $catalog): array
    {
        if ($catalog !== null) {
            $pagesRoot = $this->referenceObject($catalog['Pages'] ?? null);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectReferencesFromTree($pagesRoot['object'], $pagesRoot['generation'], $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($this->objectsByGeneration as $objectNumber => $generations) {
            foreach ($generations as $generation => $value) {
                $dict = $this->dictionaryItems($value);
                if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                    $pages[] = ['object' => $objectNumber, 'generation' => $generation];
                }
            }
        }

        if ($pages !== []) {
            return $pages;
        }

        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = ['object' => $objectNumber, 'generation' => 0];
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, true> $seen
     * @return list<array{object: int, generation: int}>
     */
    private function pageObjectReferencesFromTree(int $objectNumber, int $generation, array $objects, array $seen = []): array
    {
        $key = $this->referenceKey($objectNumber, $generation);
        if (isset($seen[$key])) {
            return [];
        }
        $seen[$key] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber, $generation));
        if ($dict === null) {
            return [];
        }

        if ($this->nameValue($dict['Type'] ?? null) === 'Page') {
            return [['object' => $objectNumber, 'generation' => $generation]];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidReference = $this->referenceObject($kid);
            if ($kidReference === null) {
                continue;
            }

            foreach ($this->pageObjectReferencesFromTree($kidReference['object'], $kidReference['generation'], $objects, $seen) as $pageReference) {
                $pages[] = $pageReference;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function destinationMap(array $catalog, array $objects): array
    {
        $destinations = [];

        $legacyDestsValue = $this->resolveValue($catalog['Dests'] ?? null);
        $legacyDuplicateNames = $this->dictionaryDuplicateKeySet($legacyDestsValue);
        $legacyDests = $this->dictionaryItems($legacyDestsValue);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                if (isset($legacyDuplicateNames[$name])) {
                    continue;
                }

                if ($this->destinationValueAllowedForMap($destination)) {
                    $destinations[$name] = $destination;
                }
            }
        }

        $catalogHasDuplicateNames = $this->catalogDictionaryHasDuplicateKeys($objects, ['Names']);
        $namesValue = $catalog['Names'] ?? null;
        $names = $this->resolveDictionary($namesValue);
        $nameTreeRootValue = $names['Dests'] ?? null;
        $nameTreeRootRejected = $catalogHasDuplicateNames
            || $names === null
            || $this->resolvedDictionaryHasDuplicateKeys($namesValue, ['Dests'])
            || $this->resolvedDictionaryHasDuplicateKeys($nameTreeRootValue, self::NAME_TREE_NODE_BOUNDARY_KEYS);
        $nameTreeRoot = $nameTreeRootRejected ? null : $this->resolveDictionary($nameTreeRootValue);
        if (
            $nameTreeRoot !== null
            && !$this->nameTreeNodeReferenceHasTopLevelStream($nameTreeRootValue)
            && !$this->nameTreeNodeHasStreamCarrierType($nameTreeRoot)
        ) {
            $this->collectNameTreeDestinations($nameTreeRoot, $destinations);
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $catalog
     */
    private function catalogUriBase(array $catalog): ?string
    {
        $resolvedUriValue = $this->resolveValue($catalog['URI'] ?? null);
        $malformedValueKeys = $this->dictionaryMalformedValueOperandKeySet($resolvedUriValue);
        if (isset($malformedValueKeys['Base']) || $this->resolvedDictionaryHasDuplicateKeys($resolvedUriValue, ['Base'])) {
            return null;
        }

        $uriDictionary = $this->dictionaryItems($resolvedUriValue);
        if ($uriDictionary === null) {
            return null;
        }

        $base = $this->stringOrNameValue($this->resolveValue($uriDictionary['Base'] ?? null));
        if ($base === null) {
            return null;
        }

        $base = trim($base);
        $scheme = $this->uriScheme($base);
        if ($base === '' || $scheme === null || !$this->isSafeUri($base)) {
            return null;
        }

        return in_array($scheme, ['http', 'https', 'ftp'], true) ? $base : null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seen
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     */
    private function collectNameTreeDestinations(
        array $node,
        array &$destinations,
        array $seen = [],
        ?array $inheritedLimits = null,
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        if ($this->resolvedDictionaryHasDuplicateKeys($node, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
            return;
        }
        if ($this->nameTreeNodeHasStreamCarrierType($node)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedIndirectArrayOperand($node)) {
            return;
        }
        if ($this->nameTreeNodeHasMalformedKidsOperand($node)) {
            return;
        }

        if ($inheritedLimits === null && $this->nameTreeNodeHasMalformedRootLimits($node)) {
            return;
        }

        $limits = $this->nameTreeEffectiveLimits($node, $inheritedLimits);
        $kids = $this->resolveArray($node['Kids'] ?? null) ?? [];
        $names = $this->resolveArray($node['Names'] ?? null);
        if ($this->nameTreeLocalLimitsDisjointFromInherited($node, $inheritedLimits)) {
            $localLimits = $this->nameTreeNodeLimits($node);
            if (
                $kids !== []
                || $names === null
                || $localLimits === null
                || !$this->nameTreeLimitsMatchAnyPairKey($names, $localLimits)
            ) {
                return;
            }
        }

        if ($kids === [] && $names !== null) {
            $entryLimits = $this->nameTreeLimitsMatchAnyPairKey($names, $limits)
                ? $limits
                : $inheritedLimits;

            $leafEntries = [];
            for ($index = 0, $count = count($names); $index + 1 < $count;) {
                $name = $this->destinationNameDetails($names[$index]);
                if ($name === null || $name['text'] === '') {
                    $index++;
                    continue;
                }

                if ($this->nameTreeStringValueIsMissingBeforePair($names, $index)) {
                    $index++;
                    continue;
                }

                if (
                    $this->nameTreeNameWithinLimits($name['text'], $entryLimits, $name['bytes'])
                    && !$this->nameTreeValueHasUnbracketedDestinationViewTail($names, $index + 1)
                    && $this->destinationValueAllowedForMap($names[$index + 1])
                ) {
                    $leafEntries[] = [
                        'name' => $name['text'],
                        'name_bytes' => $name['bytes'],
                        'name_key' => $this->destinationNameEntryKey($name['text'], $name['bytes']),
                        'value' => $names[$index + 1],
                        'order' => count($leafEntries),
                    ];
                }
                $index += 2;
            }

            foreach ($this->nameTreeLeafEntriesSortedByNameBytes($leafEntries) as $entry) {
                $this->addNameTreeDestinationMapEntry($destinations, $entry);
            }
        }

        if ($kids === []) {
            return;
        }

        foreach ($this->nameTreeKidsSortedByLimits($kids, $limits) as $kid) {
            $reference = $this->referenceObject($kid);
            if ($reference === null) {
                continue;
            }

            $seenKey = $reference['object'] . ':' . $reference['generation'];
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            if ($this->nameTreeNodeReferenceHasTopLevelStream($kid)) {
                continue;
            }

            if ($this->resolvedDictionaryHasDuplicateKeys($kid, self::NAME_TREE_NODE_BOUNDARY_KEYS)) {
                continue;
            }

            $child = $this->resolveDictionary($kid);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $destinations, $seen, $limits, $depth + 1);
            }
        }
    }

    /**
     * @param list<array{name: string, name_bytes: string, name_key: string, value: mixed, order: int}> $entries
     * @return list<array{name: string, name_bytes: string, name_key: string, value: mixed, order: int}>
     */
    private function nameTreeLeafEntriesSortedByNameBytes(array $entries): array
    {
        if (count($entries) < 2) {
            return $entries;
        }
        if ($this->nameTreeLeafEntriesContainDuplicateRawName($entries)) {
            usort(
                $entries,
                static function (array $left, array $right): int {
                    return strcmp($left['name_bytes'], $right['name_bytes'])
                        ?: $left['order'] <=> $right['order'];
                }
            );

            return $entries;
        }

        $duplicateNames = $this->nameTreeLeafDuplicateDecodedNames($entries);
        if ($duplicateNames === []) {
            return $entries;
        }

        $groups = [];
        foreach ($entries as $entry) {
            if (isset($duplicateNames[$entry['name']])) {
                $groups[$entry['name']][] = $entry;
            }
        }
        foreach ($groups as &$group) {
            usort(
                $group,
                static function (array $left, array $right): int {
                    return strcmp($left['name_bytes'], $right['name_bytes'])
                        ?: $left['order'] <=> $right['order'];
                }
            );
        }
        unset($group);

        foreach ($entries as $index => $entry) {
            if (isset($duplicateNames[$entry['name']])) {
                $entries[$index] = array_shift($groups[$entry['name']]);
            }
        }

        return $entries;
    }

    /**
     * @param list<array{name: string, name_bytes: string, name_key: string, value: mixed, order: int}> $entries
     */
    private function nameTreeLeafEntriesContainDuplicateRawName(array $entries): bool
    {
        $seen = [];
        foreach ($entries as $entry) {
            if (isset($seen[$entry['name_key']])) {
                return true;
            }
            $seen[$entry['name_key']] = true;
        }

        return false;
    }

    /**
     * @param list<array{name: string, name_bytes: string, name_key: string, value: mixed, order: int}> $entries
     * @return array<string, true>
     */
    private function nameTreeLeafDuplicateDecodedNames(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $counts[$entry['name']] = ($counts[$entry['name']] ?? 0) + 1;
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
     * @param array<string, mixed> $destinations
     * @param array{name: string, name_bytes: string, name_key: string, value: mixed, order: int} $entry
     */
    private function addNameTreeDestinationMapEntry(array &$destinations, array $entry): void
    {
        $name = $entry['name'];
        $rawKey = $this->destinationNameEntryKey($name, $entry['name_bytes']);
        $hasDecodedCollision = $this->destinationMapHasDifferentRawKey($destinations, $name, $rawKey);

        $destinations[$rawKey] = $entry['value'];
        if ($hasDecodedCollision) {
            unset($destinations[$name]);
            return;
        }

        $destinations[$name] = $entry['value'];
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

    /**
     * @param list<mixed> $kids
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     * @return list<mixed>
     */
    private function nameTreeKidsSortedByLimits(array $kids, ?array $inheritedLimits): array
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
            if ($this->referenceObject($kid) === null) {
                $kidNodes[] = $node;
                continue;
            }
            if ($this->nameTreeNodeReferenceHasTopLevelStream($kid)) {
                $kidNodes[] = $node;
                continue;
            }

            $child = $this->resolveDictionary($kid);
            if (
                $child === null
                || $this->nameTreeNodeHasStreamCarrierType($child)
                || $this->nameTreeNodeHasMalformedIndirectArrayOperand($child)
            ) {
                $kidNodes[] = $node;
                continue;
            }

            $localLimits = $this->nameTreeNodeLimits($child);
            $limits = $this->nameTreeEffectiveLimits($child, $inheritedLimits);
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
     */
    private function nameTreeNodeHasStreamCarrierType(array $node): bool
    {
        $type = $this->nameValue($this->resolveValue($node['Type'] ?? null));

        return in_array($type, ['ObjStm', 'XRef', 'Metadata', 'EmbeddedFile', 'XObject'], true);
    }

    private function nameTreeNodeHasMalformedKidsOperand(array $node): bool
    {
        return array_key_exists('Kids', $node)
            && $this->resolveArray($node['Kids']) === null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function nameTreeNodeHasMalformedIndirectArrayOperand(array $node): bool
    {
        foreach (self::NAME_TREE_NODE_BOUNDARY_KEYS as $key) {
            if (!array_key_exists($key, $node)) {
                continue;
            }

            $reference = $this->referenceObject($node[$key]);
            if ($reference === null) {
                continue;
            }

            if (
                $this->resolveArray($node[$key]) === null
                || $this->referenceObjectBodyHasTrailingOperand($reference)
            ) {
                return true;
            }
        }

        return false;
    }

    private function nameTreeNodeReferenceHasTopLevelStream(mixed $value): bool
    {
        $reference = $this->referenceObject($value);
        if ($reference === null) {
            return false;
        }

        $body = $this->objectBodiesByGeneration[$reference['object']][$reference['generation']] ?? null;

        return $body !== null && $this->objectBodyHasTopLevelStream($body);
    }

    private function objectBodyHasTopLevelStream(string $body): bool
    {
        $body = trim($body);
        if (!str_starts_with($body, '<<')) {
            return false;
        }

        $dictionaryEnd = $this->dictionaryEndOffset($body, 0);
        if ($dictionaryEnd === null) {
            return false;
        }

        $offset = $this->offsetAfterWhitespaceAndComments($body, $dictionaryEnd);
        if (substr($body, $offset, strlen('stream')) !== 'stream') {
            return false;
        }

        $next = $body[$offset + strlen('stream')] ?? '';

        return $next === '' || ctype_space($next);
    }

    private function offsetAfterWhitespaceAndComments(string $body, int $offset): int
    {
        $length = strlen($body);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($body[$offset])) {
                ++$offset;
            }

            if (($body[$offset] ?? '') !== '%') {
                break;
            }

            while ($offset < $length && $body[$offset] !== "\n" && $body[$offset] !== "\r") {
                ++$offset;
            }
        }

        return $offset;
    }

    /**
     * @param array<string, mixed> $node
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeEffectiveLimits(array $node, ?array $inheritedLimits): ?array
    {
        $nodeLimits = $this->nameTreeNodeLimits($node);
        if ($nodeLimits === null) {
            return $inheritedLimits;
        }

        if ($inheritedLimits === null) {
            return $nodeLimits;
        }

        $lower = strcmp($nodeLimits['lower_bytes'], $inheritedLimits['lower_bytes']) < 0
            ? ['text' => $inheritedLimits['lower'], 'bytes' => $inheritedLimits['lower_bytes']]
            : ['text' => $nodeLimits['lower'], 'bytes' => $nodeLimits['lower_bytes']];
        $upper = strcmp($nodeLimits['upper_bytes'], $inheritedLimits['upper_bytes']) > 0
            ? ['text' => $inheritedLimits['upper'], 'bytes' => $inheritedLimits['upper_bytes']]
            : ['text' => $nodeLimits['upper'], 'bytes' => $nodeLimits['upper_bytes']];

        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
            return $inheritedLimits;
        }

        return [
            'lower' => $lower['text'],
            'upper' => $upper['text'],
            'lower_bytes' => $lower['bytes'],
            'upper_bytes' => $upper['bytes'],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null $inheritedLimits
     */
    private function nameTreeLocalLimitsDisjointFromInherited(array $node, ?array $inheritedLimits): bool
    {
        if ($inheritedLimits === null) {
            return false;
        }

        $nodeLimits = $this->nameTreeNodeLimits($node);
        if ($nodeLimits === null) {
            return false;
        }

        $lowerBytes = strcmp($nodeLimits['lower_bytes'], $inheritedLimits['lower_bytes']) < 0
            ? $inheritedLimits['lower_bytes']
            : $nodeLimits['lower_bytes'];
        $upperBytes = strcmp($nodeLimits['upper_bytes'], $inheritedLimits['upper_bytes']) > 0
            ? $inheritedLimits['upper_bytes']
            : $nodeLimits['upper_bytes'];

        return strcmp($lowerBytes, $upperBytes) > 0;
    }

    /**
     * @param array<string, mixed> $node
     * @return array{lower: string, upper: string, lower_bytes: string, upper_bytes: string}|null
     */
    private function nameTreeNodeLimits(array $node): ?array
    {
        $limits = $this->resolveArray($node['Limits'] ?? null);
        if ($limits === null || count($limits) !== 2) {
            return null;
        }
        if (
            $this->valueHasTrailingOperandAfterResolution($limits[0])
            || $this->valueHasTrailingOperandAfterResolution($limits[1])
        ) {
            return null;
        }

        $lower = $this->pdfStringDetails($this->resolveValue($limits[0]));
        $upper = $this->pdfStringDetails($this->resolveValue($limits[1]));
        if ($lower === null || $upper === null || $lower['text'] === '' || $upper['text'] === '') {
            return null;
        }
        if (strcmp($lower['bytes'], $upper['bytes']) > 0) {
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
     * @param array<string, mixed> $node
     */
    private function nameTreeNodeHasMalformedRootLimits(array $node): bool
    {
        return array_key_exists('Limits', $node)
            && $this->nameTreeNodeLimits($node) === null;
    }

    /**
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeNameWithinLimits(string $name, ?array $limits, ?string $nameBytes = null): bool
    {
        if ($limits === null) {
            return true;
        }

        $candidate = $nameBytes ?? $name;
        $lower = $limits['lower_bytes'] ?? $limits['lower'];
        $upper = $limits['upper_bytes'] ?? $limits['upper'];

        return strcmp($lower, $upper) <= 0
            && strcmp($candidate, $lower) >= 0
            && strcmp($candidate, $upper) <= 0;
    }

    /**
     * @param list<mixed> $items
     * @param array{lower: string, upper: string, lower_bytes?: string, upper_bytes?: string}|null $limits
     */
    private function nameTreeLimitsMatchAnyPairKey(array $items, ?array $limits): bool
    {
        if ($limits === null || $items === []) {
            return true;
        }

        for ($index = 0, $count = count($items); $index + 1 < $count;) {
            $name = $this->destinationNameDetails($items[$index]);
            if ($name === null) {
                $index++;
                continue;
            }

            if ($this->nameTreeStringValueIsMissingBeforePair($items, $index)) {
                $index++;
                continue;
            }

            if ($this->nameTreeNameWithinLimits($name['text'], $limits, $name['bytes'])) {
                return true;
            }
            $index += 2;
        }

        return false;
    }

    /**
     * @param list<mixed> $items
     */
    private function nameTreeStringValueIsMissingBeforePair(array $items, int $index): bool
    {
        $valueIndex = $index + 1;
        $nextIndex = $valueIndex + 1;
        if (!array_key_exists($valueIndex, $items) || !array_key_exists($nextIndex, $items)) {
            return false;
        }

        $valueName = $this->destinationNameDetails($items[$valueIndex]);
        if ($valueName === null || $valueName['text'] === '') {
            return false;
        }

        if (!$this->nameTreeItemCanStartExplicitDestination($items[$nextIndex])) {
            return false;
        }

        return $this->destinationNameDetails($items[$nextIndex]) === null;
    }

    /**
     * @param list<mixed> $items
     */
    private function nameTreeValueHasUnbracketedDestinationViewTail(array $items, int $valueIndex): bool
    {
        if (
            !array_key_exists($valueIndex, $items)
            || !array_key_exists($valueIndex + 1, $items)
            || !$this->nameTreeValueIsPageOnlyDestination($items[$valueIndex])
        ) {
            return false;
        }

        $viewMode = $this->nameValue($this->resolveValue($items[$valueIndex + 1]));

        return $viewMode !== null && isset(self::VALID_DESTINATION_VIEW_NAMES[$viewMode]);
    }

    private function nameTreeValueIsPageOnlyDestination(mixed $value): bool
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return false;
        }

        $pageReference = $this->referenceObject($value);
        if ($pageReference !== null && $this->pageIndexForReference($pageReference) !== null) {
            return true;
        }

        $resolved = $this->resolveValue($value);
        $resolvedPageReference = $this->referenceObject($resolved);
        if ($resolvedPageReference !== null && $this->pageIndexForReference($resolvedPageReference) !== null) {
            return true;
        }

        return is_int($resolved) && $resolved >= 0 && $resolved < count($this->pageIndexesByReference);
    }

    private function nameTreeItemCanStartExplicitDestination(mixed $value): bool
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return false;
        }

        $resolved = $this->resolveValue($value);
        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            return true;
        }

        $dictionary = $this->dictionaryItems($resolved);

        return $dictionary !== null && array_key_exists('D', $dictionary);
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
            $malformedOperandCounts = [];
            $lastKey = null;
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
                $key = $tokens[$index] ?? '';
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    if ($lastKey !== null) {
                        $malformedOperandCounts[$lastKey] = ($malformedOperandCounts[$lastKey] ?? 0) + 1;
                    }
                    continue;
                }

                $decodedKey = $this->decodePdfName(substr($key, 1));
                $entryIndex = $entryCounts[$decodedKey] ?? 0;
                $entryCounts[$decodedKey] = $entryIndex + 1;
                $selectedEntryIndexes[$decodedKey] = $entryIndex;
                $items[$decodedKey] = $this->parseValue($tokens, $index);
                $lastKey = $decodedKey;
            }
            if (($tokens[$index] ?? null) === '>>') {
                $index++;
            }

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

            $dictionary = ['pdfType' => 'dict', 'items' => $items];
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
            if ($malformedOperandCounts !== []) {
                $dictionary['malformedValueOperandReview'] = [
                    'source' => 'dictionary_malformed_value_operands',
                    'review_only' => true,
                    'payload_included' => false,
                    'visible_text_source' => false,
                    'selected_entry_policy' => 'fail_closed_for_malformed_value',
                    'keys' => array_keys($malformedOperandCounts),
                    'unexpected_operand_counts' => $malformedOperandCounts,
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

    private function resolveValue(mixed $value, int $depth = 0): mixed
    {
        $reference = $this->referenceObject($value);
        if ($reference === null || $depth > 20) {
            return $value;
        }

        $objectNumber = $reference['object'];
        $generation = $reference['generation'];
        if (array_key_exists($generation, $this->objectsByGeneration[$objectNumber] ?? [])) {
            return $this->resolveValue($this->objectsByGeneration[$objectNumber][$generation], $depth + 1);
        }

        if ($generation === 0 && array_key_exists($objectNumber, $this->objects)) {
            return $this->resolveValue($this->objects[$objectNumber], $depth + 1);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDictionary(mixed $value): ?array
    {
        return $this->dictionaryItems($this->resolveValue($value));
    }

    /**
     * @return list<mixed>|null
     */
    private function resolveArray(mixed $value): ?array
    {
        return $this->arrayItems($this->resolveValue($value));
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
        $reference = $this->referenceObject($value);
        return $reference === null ? null : $reference['object'];
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function referenceObject(mixed $value): ?array
    {
        if (!is_array($value) || ($value['pdfType'] ?? null) !== 'ref' || !is_int($value['object'] ?? null)) {
            return null;
        }

        return [
            'object' => $value['object'],
            'generation' => is_int($value['generation'] ?? null) ? $value['generation'] : 0,
        ];
    }

    /**
     * @return array{pdfType: string, object: int, generation: int}
     */
    private function refValue(int $objectNumber, int $generation = 0): array
    {
        return ['pdfType' => 'ref', 'object' => $objectNumber, 'generation' => $generation];
    }

    /**
     * @param array{object: int, generation: int} $reference
     */
    private function pageIndexForReference(array $reference): ?int
    {
        return $this->pageIndexesByReference[$this->referenceKey($reference['object'], $reference['generation'])] ?? null;
    }

    private function referenceKey(int $objectNumber, int $generation): string
    {
        return $objectNumber . ':' . $generation;
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    /**
     * @return array{text: string, bytes: string}|null
     */
    private function destinationNameDetails(mixed $value): ?array
    {
        if ($this->valueHasTrailingOperandAfterResolution($value)) {
            return null;
        }

        return $this->pdfStringDetails($this->resolveValue($value));
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

    private function stringOrNameValue(mixed $value): ?string
    {
        $string = $this->stringValue($value);
        if ($string !== null) {
            return $string;
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

        if (preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {
            return false;
        }

        if (str_starts_with($trimmed, '//')) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return !str_contains($trimmed, '\\');
    }

    /**
     * @return array{uri: string, is_safe_uri: bool, metadata: array<string, mixed>}
     */
    private function uriReview(string $rawUri): array
    {
        $relative = $this->isRelativeUri($rawUri);
        $resolved = $relative && $this->uriBase !== null
            ? $this->resolveRelativeUriAgainstBase($rawUri, $this->uriBase)
            : null;
        $uri = $resolved ?? $rawUri;
        $metadata = [
            'uri_relative' => $relative,
            'uri_resolved_from_base' => $resolved !== null,
        ];

        if ($resolved !== null) {
            $metadata['raw_uri'] = $rawUri;
            $metadata['uri_base'] = $this->uriBase;
        }

        return [
            'uri' => $uri,
            'is_safe_uri' => $this->isSafeUri($uri),
            'metadata' => $metadata,
        ];
    }

    private function isRelativeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '' || preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) !== 1
            && !str_starts_with($trimmed, '//');
    }

    private function resolveRelativeUriAgainstBase(string $relativeUri, string $baseUri): ?string
    {
        $base = parse_url($baseUri);
        if (!is_array($base) || !is_string($base['scheme'] ?? null) || !is_string($base['host'] ?? null)) {
            return null;
        }

        $relative = parse_url($relativeUri);
        if ($relative === false) {
            return null;
        }

        $scheme = strtolower($base['scheme']);
        if (!in_array($scheme, ['http', 'https', 'ftp'], true)) {
            return null;
        }

        $basePath = is_string($base['path'] ?? null) && $base['path'] !== '' ? $base['path'] : '/';
        $baseQuery = is_string($base['query'] ?? null) ? $base['query'] : null;
        $relativePath = is_string($relative['path'] ?? null) ? $relative['path'] : '';
        $path = $basePath;
        $query = null;
        $fragment = is_string($relative['fragment'] ?? null) ? $relative['fragment'] : null;

        if ($relativePath !== '') {
            $baseDirectory = str_ends_with($basePath, '/')
                ? $basePath
                : substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
            $path = str_starts_with($relativePath, '/')
                ? $relativePath
                : $baseDirectory . $relativePath;
            $query = is_string($relative['query'] ?? null) ? $relative['query'] : null;
        } else {
            $query = is_string($relative['query'] ?? null) ? $relative['query'] : $baseQuery;
        }

        return $this->buildUri(
            $scheme,
            $base['host'],
            is_int($base['port'] ?? null) ? $base['port'] : null,
            $this->normalizeUriPath($path),
            $query,
            $fragment
        );
    }

    private function buildUri(string $scheme, string $host, ?int $port, string $path, ?string $query, ?string $fragment): string
    {
        $uri = $scheme . '://' . $host;
        if ($port !== null) {
            $uri .= ':' . $port;
        }

        $uri .= $path === '' || $path[0] !== '/' ? '/' . $path : $path;
        if ($query !== null && $query !== '') {
            $uri .= '?' . $query;
        }
        if ($fragment !== null && $fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    private function normalizeUriPath(string $path): string
    {
        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        return ($absolute ? '/' : '') . $normalized;
    }

    private function uriScheme(?string $uri): ?string
    {
        if ($uri === null || preg_match('/^([a-z][a-z0-9+.-]*):/i', trim($uri), $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
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
