<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibliographyReader
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $format,
        private readonly array $options = [],
    ) {
    }

    public function read(string $bytes): AstNode
    {
        $sourceItems = $this->items($bytes);
        $items = CitationCslProcessor::normalizeItems($sourceItems);
        $ids = $this->itemIds($items);
        $publicItems = $this->publicCslItems($items);
        $processor = CitationCslProcessor::fromItems($sourceItems);
        $bibliography = $processor->bibliographyDefinitionList($ids);
        $attrs = [
            'sourceFormat' => $this->format,
            'bibliography' => [
                'format' => $this->format,
                'reader' => self::class,
                'parser' => $this->parserName(),
                'itemCount' => count($items),
                'itemIds' => $ids,
                'sourceBytes' => strlen($bytes),
                'sourceSha256' => hash('sha256', $bytes),
                'payloadExposurePolicy' => 'source-bytes-omitted',
            ],
            'cslItemCount' => count($items),
            'cslItemIds' => $ids,
            'cslItems' => $publicItems,
        ];

        if ($this->format === 'csljson') {
            $review = $this->cslJsonReview($sourceItems, $ids);
            $attrs['bibliography']['cslJsonReview'] = $review;
            $attrs['cslJsonReview'] = $review;
            $attrs['cslJsonItemReviews'] = $review['items'];
        }
        if ($this->format === 'ris') {
            $review = $this->risReview($sourceItems, $ids);
            $attrs['bibliography']['risReview'] = $review;
            $attrs['risReview'] = $review;
            $attrs['risItemReviews'] = $review['items'];
        }
        if ($this->format === 'endnotexml') {
            $review = $this->endnoteXmlReview($sourceItems, $ids);
            $attrs['bibliography']['endnoteXmlReview'] = $review;
            $attrs['endnoteXmlReview'] = $review;
            $attrs['endnoteXmlItemReviews'] = $review['items'];
        }
        if ($this->format === 'bibtex' || $this->format === 'biblatex') {
            $review = $this->bibtexReview($sourceItems, $ids);
            $attrs['bibliography']['bibtexReview'] = $review;
            $attrs['bibtexReview'] = $review;
            $attrs['bibtexItemReviews'] = $review['items'];
        }

        return new AstNode('document', $attrs, $bibliography->children === [] ? [] : [$bibliography]);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function publicCslItems(array $items): array
    {
        return array_map(fn (array $item): array => $this->publicCslItem($item), $items);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function publicCslItem(array $item): array
    {
        $public = $item;
        $raw = is_array($item['raw'] ?? null) ? $item['raw'] : [];
        foreach ($raw as $key => $value) {
            if (!array_key_exists($key, $public) || !$this->fieldHasValue($public[$key])) {
                $public[$key] = $value;
            }
        }

        foreach ($this->publicDateAliases() as $publicKey => $internalKey) {
            if (array_key_exists($publicKey, $public) && $this->fieldHasValue($public[$publicKey])) {
                continue;
            }
            $date = $this->publicCslDate($item[$internalKey] ?? null);
            if ($date !== null) {
                $public[$publicKey] = $date;
            }
        }

        foreach ($this->publicNameAliases() as $publicKey => $internalKey) {
            if (array_key_exists($publicKey, $public) && $this->fieldHasValue($public[$publicKey])) {
                continue;
            }
            $names = $this->publicCslNames($item[$internalKey] ?? null);
            if ($names !== []) {
                $public[$publicKey] = $names;
            }
        }

        return $public;
    }

    /**
     * @return array<string, string>
     */
    private function publicDateAliases(): array
    {
        return [
            'issued' => 'issuedDate',
            'accessed' => 'accessedDate',
            'original-date' => 'originalDate',
            'available-date' => 'availableDate',
            'accepted-date' => 'acceptedDate',
            'revised-date' => 'revisedDate',
            'reprint-date' => 'reprintDate',
            'submitted' => 'submittedDate',
            'event-date' => 'eventDate',
            'label-date' => 'labelDate',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function publicNameAliases(): array
    {
        return [
            'author' => 'authors',
            'editor' => 'editors',
            'translator' => 'translators',
            'recipient' => 'recipients',
            'container-author' => 'containerAuthors',
            'collection-editor' => 'collectionEditors',
            'composer' => 'composers',
            'director' => 'directors',
            'illustrator' => 'illustrators',
            'interviewer' => 'interviewers',
            'reviewed-author' => 'reviewedAuthors',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicCslDate(mixed $date): ?array
    {
        if (!is_array($date)) {
            return null;
        }

        $parts = is_array($date['parts'] ?? null) ? array_values(array_filter(
            $date['parts'],
            static fn (mixed $part): bool => is_int($part) || is_float($part) || (is_string($part) && trim($part) !== ''),
        )) : [];
        $rangeParts = is_array($date['rangeParts'] ?? null) ? $date['rangeParts'] : null;
        $public = [];
        if ($rangeParts !== null && $rangeParts !== []) {
            $public['date-parts'] = $rangeParts;
        } elseif ($parts !== []) {
            $public['date-parts'] = [$parts];
        }

        $literal = trim((string) ($date['literal'] ?? ''));
        if ($literal !== '') {
            $public['literal'] = $literal;
        }

        $openEnded = trim((string) ($date['openEnded'] ?? ''));
        if ($openEnded !== '') {
            $public['open-ended'] = $openEnded;
        }

        return $public === [] ? null : $public;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicCslNames(mixed $names): array
    {
        if (!is_array($names)) {
            return [];
        }

        $public = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }
            $entry = [];
            foreach ([
                'family' => 'family',
                'given' => 'given',
                'literal' => 'literal',
                'suffix' => 'suffix',
                'dropping-particle' => 'droppingParticle',
                'non-dropping-particle' => 'nonDroppingParticle',
                'comma-suffix' => 'commaSuffix',
                'static-ordering' => 'staticOrdering',
                'parse-names' => 'parseNames',
            ] as $publicKey => $internalKey) {
                if (!array_key_exists($internalKey, $name) || !$this->fieldHasValue($name[$internalKey])) {
                    continue;
                }
                $entry[$publicKey] = $name[$internalKey];
            }
            if ($entry !== []) {
                $public[] = $entry;
            }
        }

        return $public;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $bytes): array
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => CitationCslProcessor::bibtexItems($bytes),
            'csljson' => $this->cslJsonItems($bytes),
            'endnotexml' => CitationCslProcessor::endnoteXmlItems($bytes),
            'ris' => CitationCslProcessor::risItems($bytes),
            default => throw new \InvalidArgumentException("Unsupported bibliography input format '{$this->format}'."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cslJsonItems(string $json): array
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid CSL JSON: ' . json_last_error_msg());
        }
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('CSL JSON bibliography must be an item object or a list of item objects');
        }
        if (!$this->decodedJsonIsList($decoded, $json)) {
            $decoded = [$decoded];
        }
        foreach ($decoded as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $item['id'] ?? null;
            if ((!is_string($id) && !is_int($id)) || trim((string) $id) === '') {
                $item['id'] = $this->derivedCslItemId($item, $index);
                $decoded[$index] = $item;
            }
        }

        /** @var list<array<string, mixed>> $decoded */
        $decoded = CitationCslProcessor::sanitizeCslJsonInputItems($decoded);
        CitationCslProcessor::fromItems($decoded);

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private function cslJsonReview(array $items, array $ids): array
    {
        $reviews = [];
        $fieldNames = [];
        $typeCounts = [];
        $nameVariableCounts = [];
        $dateVariableCounts = [];
        $identifierFieldCounts = [];
        $relationFieldCounts = [];
        $relationReferenceCounts = [];
        $titleBearingItemCount = 0;
        $linkBearingItemCount = 0;
        $relationBearingItemCount = 0;

        foreach ($items as $index => $item) {
            $review = $this->cslJsonItemReview($item, $index);
            $reviews[] = $review;

            foreach ($review['fieldNames'] as $fieldName) {
                $fieldNames[$fieldName] = true;
            }

            $type = (string) $review['type'];
            if ($type !== '') {
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            }

            foreach ($review['nameVariableCounts'] as $variable => $count) {
                $nameVariableCounts[$variable] = ($nameVariableCounts[$variable] ?? 0) + $count;
            }
            foreach ($review['datePartCounts'] as $variable => $count) {
                $dateVariableCounts[$variable] = ($dateVariableCounts[$variable] ?? 0) + $count;
            }
            foreach ($review['identifierFields'] as $fieldName) {
                $identifierFieldCounts[$fieldName] = ($identifierFieldCounts[$fieldName] ?? 0) + 1;
            }
            foreach ($review['relationFields'] as $fieldName) {
                $relationFieldCounts[$fieldName] = ($relationFieldCounts[$fieldName] ?? 0) + 1;
            }
            $this->mergeCountMap($relationReferenceCounts, $review['relationReferenceCounts']);

            if ($review['titleBearing']) {
                $titleBearingItemCount++;
            }
            if ($review['linkBearing']) {
                $linkBearingItemCount++;
            }
            if ($review['relationFieldCount'] > 0) {
                $relationBearingItemCount++;
            }
        }

        ksort($typeCounts);
        ksort($nameVariableCounts);
        ksort($dateVariableCounts);
        ksort($identifierFieldCounts);
        ksort($relationFieldCounts);
        ksort($relationReferenceCounts);

        return [
            'scope' => 'csl-json-bibliography',
            'byteExposurePolicy' => 'metadata-only',
            'externalTooling' => false,
            'itemCount' => count($items),
            'itemIds' => $ids,
            'fieldNameCount' => count($fieldNames),
            'fieldNames' => $this->uniqueSortedStrings(array_keys($fieldNames)),
            'typeCounts' => $typeCounts,
            'titleBearingItemCount' => $titleBearingItemCount,
            'linkBearingItemCount' => $linkBearingItemCount,
            'relationBearingItemCount' => $relationBearingItemCount,
            'nameVariableCounts' => $nameVariableCounts,
            'dateVariableCounts' => $dateVariableCounts,
            'identifierFieldCounts' => $identifierFieldCounts,
            'relationFieldCounts' => $relationFieldCounts,
            'relationReferenceCount' => array_sum($relationReferenceCounts),
            'relationReferenceCounts' => $relationReferenceCounts,
            'items' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function cslJsonItemReview(array $item, int $index): array
    {
        $fieldNames = $this->uniqueSortedStrings(array_map('strval', array_keys($item)));
        $nameVariableCounts = $this->cslJsonNameVariableCounts($item);
        $datePartCounts = $this->cslJsonDatePartCounts($item);
        $identifierFields = $this->presentFieldNames($item, [
            'DOI', 'doi',
            'ISBN', 'isbn', 'ISBN-13', 'isbn-13', 'ISBN13', 'isbn13', 'ISBN-10', 'isbn-10', 'ISBN10', 'isbn10',
            'ISSN', 'issn', 'printISSN', 'print-issn', 'pISSN', 'p-issn', 'eISSN', 'e-issn', 'onlineISSN', 'online-issn',
            'PMID', 'pmid', 'PMCID', 'pmcid', 'ISAN', 'isan', 'ISMN', 'ismn', 'ISRN', 'isrn', 'ISWC', 'iswc',
            'MRNumber', 'mrNumber', 'mrnumber', 'Zbl', 'zbl', 'JSTOR', 'jstor', 'HDL', 'hdl', 'LCCN', 'lccn', 'OCLC', 'oclc',
            'ORCID', 'orcid', 'ISNI', 'isni', 'VIAF', 'viaf', 'ROR', 'ror', 'Wikidata', 'wikidata',
        ]);
        $linkFields = $this->presentFieldNames($item, [
            'URL', 'url', 'DOI', 'doi',
            'sourceFiles', 'source-files', 'sourceFile', 'source-file',
            'file', 'files', 'pdf', 'PDF',
        ]);
        $titleFields = $this->presentFieldNames($item, [
            'title', 'title-short', 'shortTitle', 'short-title',
            'container-title', 'containerTitle', 'collection-title', 'collectionTitle',
            'original-title', 'originalTitle', 'reviewed-title', 'reviewedTitle',
            'event', 'event-title', 'eventTitle',
        ]);
        $relationFields = $this->presentFieldNames($item, [
            'references', 'related', 'relatedKeys', 'related-keys', 'relatedItems', 'related-items',
            'crossref', 'crossrefKeys', 'crossref-keys', 'crossrefItems', 'crossref-items',
            'xref', 'xrefKeys', 'xref-keys', 'xdata', 'xdataKeys', 'xdata-keys',
            'entrySet', 'entry-set', 'entryset',
        ]);
        $relationReferenceCounts = [];
        foreach ($relationFields as $relationField) {
            $relationReferenceCounts[$relationField] = $this->cslJsonListValueCount($item[$relationField]);
        }
        ksort($relationReferenceCounts);
        $id = $item['id'] ?? '';
        $type = $item['type'] ?? '';

        return [
            'index' => $index,
            'id' => is_scalar($id) ? trim((string) $id) : '',
            'type' => is_scalar($type) ? trim((string) $type) : '',
            'fieldCount' => count($fieldNames),
            'fieldNames' => $fieldNames,
            'titleBearing' => $titleFields !== [],
            'titleFields' => $titleFields,
            'nameVariableCount' => count($nameVariableCounts),
            'nameVariableCounts' => $nameVariableCounts,
            'nameCount' => array_sum($nameVariableCounts),
            'dateVariableCount' => count($datePartCounts),
            'datePartCounts' => $datePartCounts,
            'identifierFieldCount' => count($identifierFields),
            'identifierFields' => $identifierFields,
            'linkBearing' => $linkFields !== [],
            'linkFields' => $linkFields,
            'relationFieldCount' => count($relationFields),
            'relationFields' => $relationFields,
            'relationReferenceCount' => array_sum($relationReferenceCounts),
            'relationReferenceCounts' => $relationReferenceCounts,
            'payloadExposurePolicy' => 'source-values-omitted',
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private function risReview(array $items, array $ids): array
    {
        $reviews = [];
        $recordTypeCounts = [];
        $cslTypeCounts = [];
        $fieldTagCounts = [];
        $attachmentTagCounts = [];
        $mappedFieldCounts = [];
        $duplicateMappedFieldCount = 0;
        $conflictingMappedFieldCount = 0;
        $sourceFileCandidateCount = 0;

        foreach ($items as $index => $item) {
            $review = $this->risItemReview($item, $index);
            $reviews[] = $review;

            $recordType = (string) $review['recordType'];
            if ($recordType !== '') {
                $recordTypeCounts[$recordType] = ($recordTypeCounts[$recordType] ?? 0) + 1;
            }

            $cslType = (string) $review['cslType'];
            if ($cslType !== '') {
                $cslTypeCounts[$cslType] = ($cslTypeCounts[$cslType] ?? 0) + 1;
            }

            foreach ($review['fieldValueCounts'] as $tag => $count) {
                $fieldTagCounts[$tag] = ($fieldTagCounts[$tag] ?? 0) + $count;
            }
            foreach ($review['attachmentTagCounts'] as $tag => $count) {
                $attachmentTagCounts[$tag] = ($attachmentTagCounts[$tag] ?? 0) + $count;
            }
            foreach ($review['mappedFields'] as $fieldName) {
                $mappedFieldCounts[$fieldName] = ($mappedFieldCounts[$fieldName] ?? 0) + 1;
            }

            $duplicateMappedFieldCount += $review['duplicateMappedFieldCount'];
            $conflictingMappedFieldCount += $review['conflictingMappedFieldCount'];
            $sourceFileCandidateCount += $review['sourceFileCandidateCount'];
        }

        ksort($recordTypeCounts);
        ksort($cslTypeCounts);
        ksort($fieldTagCounts);
        ksort($attachmentTagCounts);
        ksort($mappedFieldCounts);

        return [
            'scope' => 'ris-bibliography',
            'byteExposurePolicy' => 'metadata-only',
            'externalTooling' => false,
            'itemCount' => count($items),
            'itemIds' => $ids,
            'recordTypeCounts' => $recordTypeCounts,
            'cslTypeCounts' => $cslTypeCounts,
            'fieldTagCount' => count($fieldTagCounts),
            'fieldValueCounts' => $fieldTagCounts,
            'attachmentTagCounts' => $attachmentTagCounts,
            'sourceFileCandidateCount' => $sourceFileCandidateCount,
            'mappedFieldCounts' => $mappedFieldCounts,
            'duplicateMappedFieldCount' => $duplicateMappedFieldCount,
            'conflictingMappedFieldCount' => $conflictingMappedFieldCount,
            'items' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function risItemReview(array $item, int $index): array
    {
        $rawRis = $item['rawRis'] ?? [];
        $fields = is_array($rawRis) && is_array($rawRis['fields'] ?? null) ? $rawRis['fields'] : [];
        $recordType = is_array($rawRis) && is_scalar($rawRis['type'] ?? null) ? trim((string) $rawRis['type']) : '';
        $fieldValueCounts = [];
        foreach ($fields as $tag => $values) {
            if (!is_array($values)) {
                continue;
            }

            $count = 0;
            foreach ($values as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $count++;
                }
            }
            if ($count > 0) {
                $fieldValueCounts[(string) $tag] = $count;
            }
        }
        ksort($fieldValueCounts);

        $attachmentTagCounts = [];
        foreach (['L1', 'L2', 'L3', 'L4'] as $tag) {
            if (isset($fieldValueCounts[$tag])) {
                $attachmentTagCounts[$tag] = $fieldValueCounts[$tag];
            }
        }

        $mappedFields = [];
        $duplicateMappedFields = [];
        $conflictingMappedFields = [];
        $provenance = is_array($item['risFieldProvenance'] ?? null) ? $item['risFieldProvenance'] : [];
        foreach ($provenance as $row) {
            if (!is_array($row) || !is_scalar($row['field'] ?? null)) {
                continue;
            }

            $field = trim((string) $row['field']);
            if ($field === '') {
                continue;
            }

            $mappedFields[] = $field;
            if (($row['duplicate'] ?? false) === true) {
                $duplicateMappedFields[] = $field;
            }
            if (($row['conflict'] ?? false) === true) {
                $conflictingMappedFields[] = $field;
            }
        }

        $mappedFields = $this->uniqueSortedStrings($mappedFields);
        $duplicateMappedFields = $this->uniqueSortedStrings($duplicateMappedFields);
        $conflictingMappedFields = $this->uniqueSortedStrings($conflictingMappedFields);
        $id = $item['id'] ?? '';
        $type = $item['type'] ?? '';

        return [
            'index' => $index,
            'id' => is_scalar($id) ? trim((string) $id) : '',
            'recordType' => $recordType,
            'cslType' => is_scalar($type) ? trim((string) $type) : '',
            'fieldTagCount' => count($fieldValueCounts),
            'fieldValueCount' => array_sum($fieldValueCounts),
            'fieldTags' => array_keys($fieldValueCounts),
            'fieldValueCounts' => $fieldValueCounts,
            'attachmentTagCounts' => $attachmentTagCounts,
            'sourceFileCandidateCount' => array_sum($attachmentTagCounts),
            'mappedFields' => $mappedFields,
            'mappedFieldCount' => count($mappedFields),
            'duplicateMappedFields' => $duplicateMappedFields,
            'duplicateMappedFieldCount' => count($duplicateMappedFields),
            'conflictingMappedFields' => $conflictingMappedFields,
            'conflictingMappedFieldCount' => count($conflictingMappedFields),
            'payloadExposurePolicy' => 'source-values-omitted',
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private function endnoteXmlReview(array $items, array $ids): array
    {
        $reviews = [];
        $cslTypeCounts = [];
        $titleFieldCounts = [];
        $nameVariableCounts = [];
        $dateVariableCounts = [];
        $identifierFieldCounts = [];
        $publicationTypeHintFieldCounts = [];
        $publicationTypeHintReasonCounts = [];
        $dateFieldCounts = [];
        $dateDiagnosticReasonCounts = [];
        $nameGroupCounts = [];
        $nameRoleCounts = [];
        $nameParsedAsCounts = [];
        $nameDiagnosticReasonCounts = [];
        $unsupportedFieldCounts = [];
        $unsupportedFieldReasonCounts = [];
        $sourceFileDiagnosticReasonCounts = [];
        $titleBearingItemCount = 0;
        $linkBearingItemCount = 0;
        $sourceFileCandidateCount = 0;
        $endnoteRefTypeItemCount = 0;
        $endnoteDatabaseItemCount = 0;

        foreach ($items as $index => $item) {
            $review = $this->endnoteXmlItemReview($item, $index);
            $reviews[] = $review;

            $cslType = (string) $review['cslType'];
            if ($cslType !== '') {
                $cslTypeCounts[$cslType] = ($cslTypeCounts[$cslType] ?? 0) + 1;
            }

            $this->mergeStringListCounts($identifierFieldCounts, $review['identifierFields']);
            $this->mergeCountMap($titleFieldCounts, $review['endnoteTitleFieldCounts']);
            $this->mergeCountMap($nameVariableCounts, $review['nameVariableCounts']);
            $this->mergeCountMap($dateVariableCounts, $review['datePartCounts']);
            $this->mergeCountMap($publicationTypeHintFieldCounts, $review['endnotePublicationTypeHintFieldCounts']);
            $this->mergeCountMap($publicationTypeHintReasonCounts, $review['endnotePublicationTypeHintReasonCounts']);
            $this->mergeCountMap($dateFieldCounts, $review['endnoteDateFieldCounts']);
            $this->mergeCountMap($dateDiagnosticReasonCounts, $review['endnoteDateDiagnosticReasonCounts']);
            $this->mergeCountMap($nameGroupCounts, $review['endnoteNameGroupCounts']);
            $this->mergeCountMap($nameRoleCounts, $review['endnoteNameRoleCounts']);
            $this->mergeCountMap($nameParsedAsCounts, $review['endnoteNameParsedAsCounts']);
            $this->mergeCountMap($nameDiagnosticReasonCounts, $review['endnoteNameDiagnosticReasonCounts']);
            $this->mergeCountMap($unsupportedFieldCounts, $review['endnoteUnsupportedFieldCounts']);
            $this->mergeCountMap($unsupportedFieldReasonCounts, $review['endnoteUnsupportedFieldReasonCounts']);
            $this->mergeCountMap($sourceFileDiagnosticReasonCounts, $review['sourceFileDiagnosticReasonCounts']);

            if ($review['titleBearing']) {
                $titleBearingItemCount++;
            }
            if ($review['linkBearing']) {
                $linkBearingItemCount++;
            }
            if ($review['endnoteRefTypePresent']) {
                $endnoteRefTypeItemCount++;
            }
            if ($review['endnoteDatabasePresent']) {
                $endnoteDatabaseItemCount++;
            }
            $sourceFileCandidateCount += $review['sourceFileCandidateCount'];
        }

        ksort($cslTypeCounts);
        ksort($titleFieldCounts);
        ksort($nameVariableCounts);
        ksort($dateVariableCounts);
        ksort($identifierFieldCounts);
        ksort($publicationTypeHintFieldCounts);
        ksort($publicationTypeHintReasonCounts);
        ksort($dateFieldCounts);
        ksort($dateDiagnosticReasonCounts);
        ksort($nameGroupCounts);
        ksort($nameRoleCounts);
        ksort($nameParsedAsCounts);
        ksort($nameDiagnosticReasonCounts);
        ksort($unsupportedFieldCounts);
        ksort($unsupportedFieldReasonCounts);
        ksort($sourceFileDiagnosticReasonCounts);

        return [
            'scope' => 'endnote-xml-bibliography',
            'byteExposurePolicy' => 'metadata-only',
            'externalTooling' => false,
            'itemCount' => count($items),
            'itemIds' => $ids,
            'cslTypeCounts' => $cslTypeCounts,
            'titleBearingItemCount' => $titleBearingItemCount,
            'linkBearingItemCount' => $linkBearingItemCount,
            'nameVariableCounts' => $nameVariableCounts,
            'dateVariableCounts' => $dateVariableCounts,
            'identifierFieldCounts' => $identifierFieldCounts,
            'sourceFileCandidateCount' => $sourceFileCandidateCount,
            'sourceFileDiagnosticReasonCounts' => $sourceFileDiagnosticReasonCounts,
            'endnoteRefTypeItemCount' => $endnoteRefTypeItemCount,
            'endnoteDatabaseItemCount' => $endnoteDatabaseItemCount,
            'endnoteTitleFieldCounts' => $titleFieldCounts,
            'endnotePublicationTypeHintFieldCounts' => $publicationTypeHintFieldCounts,
            'endnotePublicationTypeHintReasonCounts' => $publicationTypeHintReasonCounts,
            'endnoteDateFieldCounts' => $dateFieldCounts,
            'endnoteDateDiagnosticReasonCounts' => $dateDiagnosticReasonCounts,
            'endnoteNameGroupCounts' => $nameGroupCounts,
            'endnoteNameRoleCounts' => $nameRoleCounts,
            'endnoteNameParsedAsCounts' => $nameParsedAsCounts,
            'endnoteNameDiagnosticReasonCounts' => $nameDiagnosticReasonCounts,
            'endnoteUnsupportedFieldCounts' => $unsupportedFieldCounts,
            'endnoteUnsupportedFieldReasonCounts' => $unsupportedFieldReasonCounts,
            'items' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function endnoteXmlItemReview(array $item, int $index): array
    {
        $rawEndnoteXml = is_array($item['rawEndnoteXml'] ?? null) ? $item['rawEndnoteXml'] : [];
        $titleFieldCounts = $this->rowScalarValueCounts($rawEndnoteXml['titleFields'] ?? [], 'field');
        $publicationTypeHintFieldCounts = $this->rowScalarValueCounts($rawEndnoteXml['publicationTypeHints'] ?? [], 'field');
        $publicationTypeHintReasonCounts = $this->rowScalarValueCounts($rawEndnoteXml['publicationTypeHints'] ?? [], 'reason');
        $dateFieldCounts = $this->rowScalarValueCounts($rawEndnoteXml['dateFields'] ?? [], 'field');
        $dateDiagnosticReasonCounts = $this->rowScalarValueCounts($rawEndnoteXml['dateDiagnostics'] ?? [], 'reason');
        $nameGroupCounts = $this->rowScalarValueCounts($rawEndnoteXml['nameGroups'] ?? [], 'group');
        $nameRoleCounts = $this->rowScalarValueCounts($rawEndnoteXml['nameGroups'] ?? [], 'role');
        $nameParsedAsCounts = $this->rowScalarValueCounts($rawEndnoteXml['nameGroups'] ?? [], 'parsedAs');
        $nameDiagnosticReasonCounts = $this->rowScalarValueCounts($rawEndnoteXml['nameGroupDiagnostics'] ?? [], 'reason');
        $unsupportedFieldCounts = $this->rowScalarValueCounts($rawEndnoteXml['unsupportedFields'] ?? [], 'field');
        $unsupportedFieldReasonCounts = $this->rowScalarValueCounts($rawEndnoteXml['unsupportedFields'] ?? [], 'reason');
        $sourceFileDiagnosticReasonCounts = $this->sourceFileDiagnosticReasonCounts($item['sourceFileDiagnostics'] ?? []);
        $identifierFields = $this->presentFieldNames($item, [
            'DOI', 'doi',
            'ISBN', 'isbn', 'ISSN', 'issn',
        ]);
        $linkFields = $this->presentFieldNames($item, [
            'URL', 'url', 'DOI', 'doi', 'sourceFileDiagnostics',
        ]);
        $titleFields = $this->presentFieldNames($item, [
            'title', 'title-short', 'shortTitle', 'short-title',
            'container-title', 'containerTitle', 'collection-title', 'collectionTitle',
        ]);
        $nameVariableCounts = $this->cslJsonNameVariableCounts($item);
        $datePartCounts = $this->cslJsonDatePartCounts($item);
        $id = $item['id'] ?? '';
        $type = $item['type'] ?? '';

        return [
            'index' => $index,
            'id' => is_scalar($id) ? trim((string) $id) : '',
            'cslType' => is_scalar($type) ? trim((string) $type) : '',
            'titleBearing' => $titleFields !== [],
            'titleFields' => $titleFields,
            'nameVariableCount' => count($nameVariableCounts),
            'nameVariableCounts' => $nameVariableCounts,
            'nameCount' => array_sum($nameVariableCounts),
            'dateVariableCount' => count($datePartCounts),
            'datePartCounts' => $datePartCounts,
            'identifierFieldCount' => count($identifierFields),
            'identifierFields' => $identifierFields,
            'linkBearing' => $linkFields !== [],
            'linkFields' => $linkFields,
            'sourceFileCandidateCount' => array_sum($sourceFileDiagnosticReasonCounts),
            'sourceFileDiagnosticReasonCounts' => $sourceFileDiagnosticReasonCounts,
            'endnoteRefTypePresent' => trim((string) ($rawEndnoteXml['refType'] ?? '')) !== '',
            'endnoteDatabasePresent' => trim((string) ($rawEndnoteXml['database'] ?? '')) !== '',
            'endnoteTitleFieldCount' => array_sum($titleFieldCounts),
            'endnoteTitleFieldCounts' => $titleFieldCounts,
            'endnotePublicationTypeHintCount' => array_sum($publicationTypeHintReasonCounts),
            'endnotePublicationTypeHintFieldCounts' => $publicationTypeHintFieldCounts,
            'endnotePublicationTypeHintReasonCounts' => $publicationTypeHintReasonCounts,
            'endnoteDateFieldCount' => array_sum($dateFieldCounts),
            'endnoteDateFieldCounts' => $dateFieldCounts,
            'endnoteDateDiagnosticReasonCounts' => $dateDiagnosticReasonCounts,
            'endnoteNameGroupCount' => array_sum($nameGroupCounts),
            'endnoteNameGroupCounts' => $nameGroupCounts,
            'endnoteNameRoleCounts' => $nameRoleCounts,
            'endnoteNameParsedAsCounts' => $nameParsedAsCounts,
            'endnoteNameDiagnosticReasonCounts' => $nameDiagnosticReasonCounts,
            'endnoteUnsupportedFieldCounts' => $unsupportedFieldCounts,
            'endnoteUnsupportedFieldReasonCounts' => $unsupportedFieldReasonCounts,
            'payloadExposurePolicy' => 'source-values-omitted',
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private function bibtexReview(array $items, array $ids): array
    {
        $reviews = [];
        $entryTypeCounts = [];
        $cslTypeCounts = [];
        $fieldValueCounts = [];
        $nameVariableCounts = [];
        $dateVariableCounts = [];
        $dateRangeEndpointCounts = [];
        $openEndedDateVariableCounts = [];
        $openEndedDateDirectionCounts = [];
        $literalDateVariableCounts = [];
        $identifierFieldCounts = [];
        $sourceFileDiagnosticReasonCounts = [];
        $relationReferenceCounts = [];
        $missingRelationReferenceCounts = [];
        $biblatexCustomFieldCounts = [];
        $biblatexCustomListCounts = [];
        $biblatexCustomNameCounts = [];
        $biblatexFieldAnnotationCounts = [];
        $biblatexOptionNameCounts = [];
        $biblatexLanguageOptionNameCounts = [];
        $biblatexBibliographyVisibilityCounts = [];
        $titleBearingItemCount = 0;
        $linkBearingItemCount = 0;
        $sourceFileCandidateCount = 0;
        $biblatexSkipBibliographyItemCount = 0;
        $biblatexBibliographyVisibleItemCount = 0;

        foreach ($items as $index => $item) {
            $review = $this->bibtexItemReview($item, $index);
            $reviews[] = $review;

            $entryType = (string) $review['entryType'];
            if ($entryType !== '') {
                $entryTypeCounts[$entryType] = ($entryTypeCounts[$entryType] ?? 0) + 1;
            }

            $cslType = (string) $review['cslType'];
            if ($cslType !== '') {
                $cslTypeCounts[$cslType] = ($cslTypeCounts[$cslType] ?? 0) + 1;
            }

            $this->mergeCountMap($fieldValueCounts, $review['fieldValueCounts']);
            $this->mergeCountMap($nameVariableCounts, $review['nameVariableCounts']);
            $this->mergeCountMap($dateVariableCounts, $review['datePartCounts']);
            $this->mergeCountMap($dateRangeEndpointCounts, $review['dateRangeEndpointCounts']);
            $this->mergeCountMap($openEndedDateVariableCounts, $review['openEndedDateVariableCounts']);
            $this->mergeCountMap($openEndedDateDirectionCounts, $review['openEndedDateDirectionCounts']);
            $this->mergeCountMap($literalDateVariableCounts, $review['literalDateVariableCounts']);
            $this->mergeStringListCounts($identifierFieldCounts, $review['identifierFields']);
            $this->mergeCountMap($sourceFileDiagnosticReasonCounts, $review['sourceFileDiagnosticReasonCounts']);
            $this->mergeCountMap($relationReferenceCounts, $review['relationReferenceCounts']);
            $this->mergeCountMap($missingRelationReferenceCounts, $review['missingRelationReferenceCounts']);
            $this->mergeStringListCounts($biblatexCustomFieldCounts, $review['biblatexCustomFields']);
            $this->mergeStringListCounts($biblatexCustomListCounts, $review['biblatexCustomLists']);
            $this->mergeStringListCounts($biblatexCustomNameCounts, $review['biblatexCustomNames']);
            $this->mergeCountMap($biblatexFieldAnnotationCounts, $review['biblatexFieldAnnotationCounts']);
            $this->mergeStringListCounts($biblatexOptionNameCounts, $review['biblatexOptionNames']);
            $this->mergeStringListCounts($biblatexLanguageOptionNameCounts, $review['biblatexLanguageOptionNames']);

            $visibility = (string) ($review['biblatexBibliographyVisibility'] ?? '');
            if ($visibility !== '') {
                $biblatexBibliographyVisibilityCounts[$visibility] =
                    ($biblatexBibliographyVisibilityCounts[$visibility] ?? 0) + 1;
            }

            if ($review['titleBearing']) {
                $titleBearingItemCount++;
            }
            if ($review['linkBearing']) {
                $linkBearingItemCount++;
            }
            $sourceFileCandidateCount += $review['sourceFileCandidateCount'];
            if (($review['biblatexSkipsBibliography'] ?? false) === true) {
                $biblatexSkipBibliographyItemCount++;
            } else {
                $biblatexBibliographyVisibleItemCount++;
            }
        }

        ksort($entryTypeCounts);
        ksort($cslTypeCounts);
        ksort($fieldValueCounts);
        ksort($nameVariableCounts);
        ksort($dateVariableCounts);
        ksort($dateRangeEndpointCounts);
        ksort($openEndedDateVariableCounts);
        ksort($openEndedDateDirectionCounts);
        ksort($literalDateVariableCounts);
        ksort($identifierFieldCounts);
        ksort($sourceFileDiagnosticReasonCounts);
        ksort($relationReferenceCounts);
        ksort($missingRelationReferenceCounts);
        ksort($biblatexCustomFieldCounts);
        ksort($biblatexCustomListCounts);
        ksort($biblatexCustomNameCounts);
        ksort($biblatexFieldAnnotationCounts);
        ksort($biblatexOptionNameCounts);
        ksort($biblatexLanguageOptionNameCounts);
        ksort($biblatexBibliographyVisibilityCounts);

        return [
            'scope' => $this->format . '-bibliography',
            'byteExposurePolicy' => 'metadata-only',
            'externalTooling' => false,
            'itemCount' => count($items),
            'itemIds' => $ids,
            'entryTypeCounts' => $entryTypeCounts,
            'cslTypeCounts' => $cslTypeCounts,
            'fieldNameCount' => count($fieldValueCounts),
            'fieldValueCounts' => $fieldValueCounts,
            'titleBearingItemCount' => $titleBearingItemCount,
            'linkBearingItemCount' => $linkBearingItemCount,
            'nameVariableCounts' => $nameVariableCounts,
            'dateVariableCounts' => $dateVariableCounts,
            'dateRangeEndpointCounts' => $dateRangeEndpointCounts,
            'openEndedDateVariableCounts' => $openEndedDateVariableCounts,
            'openEndedDateDirectionCounts' => $openEndedDateDirectionCounts,
            'literalDateVariableCounts' => $literalDateVariableCounts,
            'identifierFieldCounts' => $identifierFieldCounts,
            'sourceFileCandidateCount' => $sourceFileCandidateCount,
            'sourceFileDiagnosticReasonCounts' => $sourceFileDiagnosticReasonCounts,
            'relationReferenceCounts' => $relationReferenceCounts,
            'missingRelationReferenceCounts' => $missingRelationReferenceCounts,
            'biblatexCustomFieldCounts' => $biblatexCustomFieldCounts,
            'biblatexCustomListCounts' => $biblatexCustomListCounts,
            'biblatexCustomNameCounts' => $biblatexCustomNameCounts,
            'biblatexFieldAnnotationCounts' => $biblatexFieldAnnotationCounts,
            'biblatexOptionNameCounts' => $biblatexOptionNameCounts,
            'biblatexLanguageOptionNameCounts' => $biblatexLanguageOptionNameCounts,
            'biblatexSkipBibliographyItemCount' => $biblatexSkipBibliographyItemCount,
            'biblatexBibliographyVisibleItemCount' => $biblatexBibliographyVisibleItemCount,
            'biblatexBibliographyVisibilityCounts' => $biblatexBibliographyVisibilityCounts,
            'items' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function bibtexItemReview(array $item, int $index): array
    {
        $rawBibtex = is_array($item['rawBibtex'] ?? null) ? $item['rawBibtex'] : [];
        $fields = is_array($rawBibtex['fields'] ?? null) ? $rawBibtex['fields'] : [];
        $fieldValueCounts = [];
        foreach ($fields as $field => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $fieldValueCounts[(string) $field] = 1;
            }
        }
        ksort($fieldValueCounts);

        $sourceFileCount = is_array($item['sourceFiles'] ?? null) ? count($item['sourceFiles']) : 0;
        $sourceFileDiagnosticReasonCounts = $this->sourceFileDiagnosticReasonCounts($item['sourceFileDiagnostics'] ?? []);
        $relationReferenceCounts = $this->bibtexRelationReferenceCounts($item, false);
        $missingRelationReferenceCounts = $this->bibtexRelationReferenceCounts($item, true);
        $biblatexFieldAnnotationCounts = $this->biblatexFieldAnnotationCounts($item['biblatex-field-annotations'] ?? []);
        $biblatexOptions = $item['biblatex-options'] ?? [];
        $biblatexLanguageOptions = $item['biblatex-language-options'] ?? [];
        $biblatexSkipsBibliography = $this->biblatexSkipsBibliography($biblatexOptions);
        $identifierFields = $this->presentFieldNames($item, [
            'DOI', 'doi',
            'ISBN', 'isbn', 'ISSN', 'issn',
            'ISAN', 'isan', 'ISMN', 'ismn', 'ISRN', 'isrn', 'ISWC', 'iswc',
            'PMID', 'pmid', 'PMCID', 'pmcid', 'MRNumber', 'mrNumber', 'mrnumber',
            'Zbl', 'zbl', 'JSTOR', 'jstor', 'HDL', 'hdl', 'LCCN', 'lccn', 'OCLC', 'oclc',
            'ORCID', 'orcid', 'ISNI', 'isni', 'VIAF', 'viaf', 'ROR', 'ror', 'Wikidata', 'wikidata',
        ]);
        $identifierFields = $this->deduplicateIdentifierFieldNames($identifierFields);
        $linkFields = $this->presentFieldNames($item, [
            'URL', 'url', 'DOI', 'doi',
            'sourceFiles', 'source-files', 'sourceFile', 'source-file',
            'file', 'files', 'pdf', 'PDF',
        ]);
        $titleFields = $this->presentFieldNames($item, [
            'title', 'title-short', 'short-title',
            'container-title', 'collection-title', 'main-title', 'volume-title', 'part-title',
            'original-title', 'reviewed-title', 'translated-title', 'event', 'issue-title',
        ]);
        $nameVariableCounts = $this->cslJsonNameVariableCounts($item);
        $datePartCounts = $this->cslJsonDatePartCounts($item);
        $dateShapeReview = $this->dateShapeReview($item);
        $id = $item['id'] ?? '';
        $type = $item['type'] ?? '';

        return [
            'index' => $index,
            'id' => is_scalar($id) ? trim((string) $id) : '',
            'entryType' => is_scalar($rawBibtex['type'] ?? null) ? trim((string) $rawBibtex['type']) : '',
            'cslType' => is_scalar($type) ? trim((string) $type) : '',
            'fieldNameCount' => count($fieldValueCounts),
            'fieldNames' => array_keys($fieldValueCounts),
            'fieldValueCount' => array_sum($fieldValueCounts),
            'fieldValueCounts' => $fieldValueCounts,
            'titleBearing' => $titleFields !== [],
            'titleFields' => $titleFields,
            'nameVariableCount' => count($nameVariableCounts),
            'nameVariableCounts' => $nameVariableCounts,
            'nameCount' => array_sum($nameVariableCounts),
            'dateVariableCount' => count($datePartCounts),
            'datePartCounts' => $datePartCounts,
            'dateRangeEndpointCounts' => $dateShapeReview['dateRangeEndpointCounts'],
            'dateRangeVariableCount' => count($dateShapeReview['dateRangeEndpointCounts']),
            'openEndedDateVariableCounts' => $dateShapeReview['openEndedDateVariableCounts'],
            'openEndedDateVariableCount' => array_sum($dateShapeReview['openEndedDateVariableCounts']),
            'openEndedDateDirectionCounts' => $dateShapeReview['openEndedDateDirectionCounts'],
            'literalDateVariableCounts' => $dateShapeReview['literalDateVariableCounts'],
            'literalDateVariableCount' => array_sum($dateShapeReview['literalDateVariableCounts']),
            'identifierFieldCount' => count($identifierFields),
            'identifierFields' => $identifierFields,
            'linkBearing' => $linkFields !== [],
            'linkFields' => $linkFields,
            'sourceFileCandidateCount' => $sourceFileCount + array_sum($sourceFileDiagnosticReasonCounts),
            'sourceFileDiagnosticReasonCounts' => $sourceFileDiagnosticReasonCounts,
            'relationReferenceCounts' => $relationReferenceCounts,
            'missingRelationReferenceCounts' => $missingRelationReferenceCounts,
            'biblatexCustomFields' => $this->biblatexMapKeys($item['biblatex-custom-fields'] ?? []),
            'biblatexCustomLists' => $this->biblatexMapKeys($item['biblatex-custom-lists'] ?? []),
            'biblatexCustomNames' => $this->biblatexMapKeys($item['biblatex-custom-names'] ?? []),
            'biblatexFieldAnnotationCounts' => $biblatexFieldAnnotationCounts,
            'biblatexOptionCount' => is_array($biblatexOptions) ? count($biblatexOptions) : 0,
            'biblatexOptionNames' => $this->biblatexOptionNames($biblatexOptions),
            'biblatexLanguageOptionCount' => is_array($biblatexLanguageOptions) ? count($biblatexLanguageOptions) : 0,
            'biblatexLanguageOptionNames' => $this->biblatexOptionNames($biblatexLanguageOptions),
            'biblatexSkipsBibliography' => $biblatexSkipsBibliography,
            'biblatexBibliographyVisibility' => $biblatexSkipsBibliography ? 'omit' : 'include',
            'payloadExposurePolicy' => 'source-values-omitted',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     dateRangeEndpointCounts: array<string, int>,
     *     openEndedDateVariableCounts: array<string, int>,
     *     openEndedDateDirectionCounts: array<string, int>,
     *     literalDateVariableCounts: array<string, int>
     * }
     */
    private function dateShapeReview(array $item): array
    {
        $rangeEndpointCounts = [];
        $openEndedVariableCounts = [];
        $openEndedDirectionCounts = [];
        $literalVariableCounts = [];

        foreach ($this->dateVariableFields() as $variable => $fieldNames) {
            $value = $this->firstPresentValue($item, $fieldNames);
            if (!$this->fieldHasValue($value)) {
                continue;
            }

            if (!is_array($value)) {
                $literalVariableCounts[$variable] = 1;
                continue;
            }

            $dateParts = $value['date-parts'] ?? null;
            if (is_array($dateParts) && count($dateParts) > 1) {
                $rangeEndpointCounts[$variable] = count($dateParts);
            }

            $openEnded = $value['open-ended'] ?? null;
            if (is_scalar($openEnded)) {
                $openEnded = strtolower(trim((string) $openEnded));
                if (in_array($openEnded, ['start', 'end'], true)) {
                    $openEndedVariableCounts[$variable] = 1;
                    $openEndedDirectionCounts[$openEnded] = ($openEndedDirectionCounts[$openEnded] ?? 0) + 1;
                }
            }

            if (
                array_key_exists('literal', $value)
                && $this->fieldHasValue($value['literal'])
                && (!is_array($dateParts) || $dateParts === [])
            ) {
                $literalVariableCounts[$variable] = 1;
            }
        }

        ksort($rangeEndpointCounts);
        ksort($openEndedVariableCounts);
        ksort($openEndedDirectionCounts);
        ksort($literalVariableCounts);

        return [
            'dateRangeEndpointCounts' => $rangeEndpointCounts,
            'openEndedDateVariableCounts' => $openEndedVariableCounts,
            'openEndedDateDirectionCounts' => $openEndedDirectionCounts,
            'literalDateVariableCounts' => $literalVariableCounts,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, int>
     */
    private function bibtexRelationReferenceCounts(array $item, bool $missing): array
    {
        $fields = $missing
            ? [
                'xdata' => 'missingXdataKeys',
                'entrySet' => 'missingEntrySetKeys',
                'related' => 'missingRelatedKeys',
                'crossref' => 'missingCrossrefKeys',
                'xref' => 'missingXrefKeys',
            ]
            : [
                'xdata' => 'xdataKeys',
                'entrySet' => 'entrySet',
                'related' => 'relatedKeys',
                'crossref' => 'crossrefKeys',
                'xref' => 'xrefKeys',
            ];
        $counts = [];
        foreach ($fields as $label => $field) {
            if (is_array($item[$field] ?? null) && $item[$field] !== []) {
                $counts[$label] = count($item[$field]);
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function sourceFileDiagnosticReasonCounts(mixed $diagnostics): array
    {
        if (!is_array($diagnostics)) {
            return [];
        }

        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic) || !is_scalar($diagnostic['reason'] ?? null)) {
                continue;
            }

            $reason = trim((string) $diagnostic['reason']);
            if ($reason !== '') {
                $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function rowScalarValueCounts(mixed $rows, string $key): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_scalar($row[$key] ?? null)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value === '') {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function biblatexMapKeys(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }

        return $this->uniqueSortedStrings(array_map('strval', array_keys($map)));
    }

    /**
     * @return array<string, int>
     */
    private function biblatexFieldAnnotationCounts(mixed $annotations): array
    {
        if (!is_array($annotations)) {
            return [];
        }

        $counts = [];
        foreach ($annotations as $field => $entries) {
            if (is_array($entries) && $entries !== []) {
                $counts[(string) $field] = count($entries);
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function biblatexOptionNames(mixed $options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $names = [];
        foreach ($options as $option) {
            if (!is_scalar($option)) {
                continue;
            }

            $option = strtolower(trim(str_replace('_', '-', (string) $option)));
            if ($option === '') {
                continue;
            }

            $name = trim(explode('=', $option, 2)[0]);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    private function biblatexSkipsBibliography(mixed $options): bool
    {
        if (!is_array($options)) {
            return false;
        }

        $skip = false;
        foreach ($options as $option) {
            if (!is_scalar($option)) {
                continue;
            }

            $option = strtolower(trim(str_replace('_', '-', (string) $option)));
            if ($option === '') {
                continue;
            }

            if ($option === 'skipbib') {
                $skip = true;
                continue;
            }

            if (!str_starts_with($option, 'skipbib=')) {
                continue;
            }

            $value = trim(substr($option, strlen('skipbib=')));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                $skip = true;
            } elseif (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                $skip = false;
            }
        }

        return $skip;
    }

    /**
     * @param array<string, int> $target
     * @param array<string, int> $source
     */
    private function mergeCountMap(array &$target, array $source): void
    {
        foreach ($source as $key => $count) {
            $target[(string) $key] = ($target[(string) $key] ?? 0) + (int) $count;
        }
    }

    /**
     * @param array<string, int> $target
     * @param list<string> $strings
     */
    private function mergeStringListCounts(array &$target, array $strings): void
    {
        foreach ($strings as $string) {
            $target[$string] = ($target[$string] ?? 0) + 1;
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, int>
     */
    private function cslJsonNameVariableCounts(array $item): array
    {
        $counts = [];
        foreach ([
            'author',
            'editor',
            'short-author',
            'short-editor',
            'holder',
            'translator',
            'chair',
            'container-author',
            'original-author',
            'recipient',
            'reviewed-author',
            'event-organizer',
            'organizer',
            'interviewer',
            'compiler',
            'composer',
            'contributor',
            'producer',
            'performer',
            'narrator',
            'host',
            'guest',
            'executive-producer',
            'script-writer',
            'director',
            'editorial-director',
            'illustrator',
            'curator',
            'collection-editor',
            'series-creator',
            'editor-translator',
            'editortranslator',
            'redactor',
            'commentator',
            'annotator',
            'founder',
            'continuator',
            'reviser',
            'collaborator',
            'introduction',
            'foreword',
            'afterword',
            'authority',
            'namea',
            'nameb',
            'namec',
        ] as $variable) {
            if (!array_key_exists($variable, $item) || !$this->fieldHasValue($item[$variable])) {
                continue;
            }

            $counts[$variable] = $this->cslJsonListValueCount($item[$variable]);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, int>
     */
    private function cslJsonDatePartCounts(array $item): array
    {
        $counts = [];
        foreach ($this->dateVariableFields() as $variable => $fieldNames) {
            $value = $this->firstPresentValue($item, $fieldNames);
            if (!$this->fieldHasValue($value)) {
                continue;
            }

            $count = $this->cslJsonDatePartCount($value);
            if ($count === 0 && !$this->dateValueHasLiteral($value)) {
                continue;
            }

            $counts[$variable] = $count;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, list<string>>
     */
    private function dateVariableFields(): array
    {
        return [
            'issued' => ['issued', 'issuedDate', 'issued-date', 'date'],
            'accessed' => ['accessed', 'accessedDate', 'accessed-date', 'URLDate', 'URL-date', 'urlDate', 'url-date'],
            'original-date' => ['original-date', 'originalDate'],
            'available-date' => ['available-date', 'availableDate'],
            'accepted-date' => ['accepted-date', 'acceptedDate'],
            'revised-date' => ['revised-date', 'revisedDate'],
            'reprint-date' => ['reprint-date', 'reprintDate'],
            'submitted' => ['submitted', 'submittedDate', 'submitted-date'],
            'event-date' => ['event-date', 'eventDate'],
            'label-date' => ['label-date', 'labelDate'],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $fieldNames
     */
    private function firstPresentValue(array $item, array $fieldNames): mixed
    {
        foreach ($fieldNames as $fieldName) {
            if (array_key_exists($fieldName, $item) && $this->fieldHasValue($item[$fieldName])) {
                return $item[$fieldName];
            }
        }

        return null;
    }

    private function cslJsonDatePartCount(mixed $value): int
    {
        if (!is_array($value)) {
            return 1;
        }

        $dateParts = $value['date-parts'] ?? null;
        if (is_array($dateParts) && $dateParts !== []) {
            $first = $dateParts[0] ?? null;
            return is_array($first) ? count($first) : 0;
        }

        $rangeParts = $value['rangeParts'] ?? null;
        if (is_array($rangeParts) && $rangeParts !== []) {
            $first = $rangeParts[0] ?? null;
            return is_array($first) ? count($first) : 0;
        }

        $parts = $value['parts'] ?? null;
        if (!is_array($parts) || $parts === []) {
            return 0;
        }

        return count($parts);
    }

    private function dateValueHasLiteral(mixed $value): bool
    {
        return is_array($value) && trim((string) ($value['literal'] ?? '')) !== '';
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function deduplicateIdentifierFieldNames(array $fields): array
    {
        $seen = [];
        $deduplicated = [];
        foreach ($fields as $field) {
            $key = strtolower($field);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduplicated[] = $field;
        }

        return $deduplicated;
    }

    private function cslJsonListValueCount(mixed $value): int
    {
        if (!is_array($value)) {
            return $this->fieldHasValue($value) ? 1 : 0;
        }

        return array_is_list($value) ? count($value) : 1;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $fieldNames
     * @return list<string>
     */
    private function presentFieldNames(array $item, array $fieldNames): array
    {
        $present = [];
        foreach ($fieldNames as $fieldName) {
            if (array_key_exists($fieldName, $item) && $this->fieldHasValue($item[$fieldName])) {
                $present[] = $fieldName;
            }
        }

        return $this->uniqueSortedStrings($present);
    }

    private function fieldHasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function uniqueSortedStrings(array $strings): array
    {
        $strings = array_values(array_unique($strings));
        sort($strings, SORT_STRING);

        return $strings;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function decodedJsonIsList(array $decoded, string $json): bool
    {
        if ($decoded === []) {
            return str_starts_with(ltrim($json), '[');
        }

        return array_is_list($decoded);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function itemIds(array $items): array
    {
        $ids = [];
        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            if (!is_string($id) && !is_int($id)) {
                $id = $this->derivedCslItemId($item, $index);
            }

            $id = trim((string) $id);
            if ($id === '') {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' has an empty id');
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function derivedCslItemId(array $item, int $index): string
    {
        foreach (['DOI', 'doi', 'URL', 'url'] as $field) {
            $value = $item[$field] ?? null;
            if (is_string($value) || is_int($value)) {
                $id = trim((string) $value);
                if ($id !== '') {
                    return $id;
                }
            }
        }

        $title = $item['title'] ?? null;
        if (is_array($title)) {
            $title = reset($title);
        }
        if (is_string($title) || is_int($title)) {
            $id = trim((string) $title);
            if ($id !== '') {
                return 'item-' . substr(hash('sha256', $id), 0, 16);
            }
        }

        return 'item-' . ($index + 1);
    }

    private function parserName(): string
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => BibtexCslParser::class,
            'csljson' => 'CSL JSON',
            'endnotexml' => 'EndNote XML',
            'ris' => 'RIS',
            default => 'unknown',
        };
    }
}
